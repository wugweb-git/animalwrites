<?php
/**
 * Relevance gate (owner, 10 Oct 2026): the workers ask a cheap model whether each item is about animal welfare in
 * India, fits a Category (aw_topic) and, for documents, was issued by a known body, before anything is created.
 *
 * Endpoints (the ingest application-password user, edit_posts):
 * - GET  aw/v1/relevance-config    Categories (slug => name) and Directory organisation names (known issuers).
 * - GET  aw/v1/relevance-items     Existing News, Library, Resources, Opportunities and Press items, any status but
 *                                  trash; paginated (page, per_page ≤ 200, missing=1 for items without a verdict).
 * - POST aw/v1/relevance-verdicts  {verdicts: [{id, relevant, category, india, issuer, reason, model, at}]} → meta
 *                                  aw_relevance (JSON). Never changes an item's status.
 * - POST aw/v1/relevance-rejected  {title, source_url, reason, type, feed, model} → the Rejected log: option
 *                                  aw_relevance_rejected, newest first, last 500, one entry per URL. Shown under
 *                                  Settings → Sourcing → Rejected, to administrators only; never in To review.
 *
 * Inbox → To review shows only items the check passed (or has not checked): an item whose stored verdict is "not
 * relevant" is left out of the list and its counts.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_RELEVANCE_TYPES   = array( 'aw_news', 'aw_legal', 'post', 'aw_opportunity', 'aw_press' );
const AW_RELEVANCE_LOG     = 'aw_relevance_rejected';
const AW_RELEVANCE_LOG_MAX = 500;

/**
 * A verdict as stored on an item (meta aw_relevance): JSON {relevant, category, india, issuer, reason, model, at}.
 *
 * @param array $v Verdict as received.
 * @return string JSON.
 */
function aw_relevance_clean( array $v ) {
	return wp_json_encode(
		array(
			'relevant' => ! empty( $v['relevant'] ) && 'no' !== $v['relevant'],
			'category' => sanitize_title( (string) ( $v['category'] ?? '' ) ),
			'india'    => ! empty( $v['india'] ) && 'no' !== $v['india'],
			'issuer'   => sanitize_text_field( (string) ( $v['issuer'] ?? '' ) ),
			'reason'   => sanitize_text_field( (string) ( $v['reason'] ?? '' ) ),
			'model'    => sanitize_text_field( (string) ( $v['model'] ?? '' ) ),
			'at'       => sanitize_text_field( (string) ( $v['at'] ?? gmdate( 'Y-m-d\TH:i:s\Z' ) ) ),
		)
	);
}

/** Store the gate's verdict on a new item (ingest and press ingest). */
function aw_relevance_store( $post_id, $verdict ) {
	if ( is_array( $verdict ) && $verdict ) {
		update_post_meta( $post_id, 'aw_relevance', wp_slash( aw_relevance_clean( $verdict ) ) );
	}
}

/**
 * Title for duplicate checks (same rule as the workers' norm_title): lower case, letters and digits only, a trailing
 * " - Publisher" or leading "Section | " removed.
 */
function aw_norm_title( $title ) {
	$t = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $title ) : strtolower( (string) $title );
	$t = preg_replace( '/\s+[-–—|]\s+[^-–—|]{2,40}$/u', '', $t );
	$t = preg_replace( '/^[^|]{2,30}\|\s+/u', '', $t );
	$t = preg_replace( '/[^\p{L}\p{N}\s]|_/u', ' ', $t );
	return trim( preg_replace( '/\s+/u', ' ', $t ) );
}

/**
 * An existing item (any status but trash, drafts included) with the same normalised title and a date within a day.
 *
 * @param string[] $types    Post types.
 * @param string   $title    Title.
 * @param string   $day      YYYY-MM-DD.
 * @param string   $date_key Meta holding the item's own date.
 * @return int Post ID or 0.
 */
function aw_find_by_title( array $types, $title, $day, $date_key = 'aw_source_date' ) {
	$key = aw_norm_title( $title );
	$day = substr( (string) $day, 0, 10 );
	if ( '' === $key || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) ) {
		return 0;
	}
	$from = gmdate( 'Y-m-d', strtotime( $day . ' -1 day' ) );
	$to   = gmdate( 'Y-m-d', strtotime( $day . ' +1 day' ) );
	$ids  = get_posts(
		array(
			'post_type'      => $types,
			'post_status'    => array( 'publish', 'pending', 'draft', 'private', 'future', 'trash' ), // trash: never re-created
			'fields'         => 'ids',
			'posts_per_page' => 500,
			'no_found_rows'  => true,
			'meta_query'     => array( array( 'key' => $date_key, 'value' => array( $from, $to . ' 23:59:59' ), 'compare' => 'BETWEEN', 'type' => 'CHAR' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	foreach ( $ids as $id ) {
		if ( aw_norm_title( get_post_field( 'post_title', $id ) ) === $key ) {
			return (int) $id;
		}
	}
	return 0;
}

/** Categories: aw_topic slug => name. */
function aw_relevance_categories() {
	$out = array();
	foreach ( get_terms( array( 'taxonomy' => 'aw_topic', 'hide_empty' => false ) ) as $t ) {
		$out[ $t->slug ] = html_entity_decode( $t->name );
	}
	return $out;
}

/** Directory organisations (animal-welfare organisations, ABC centres, wildlife rescue): published names. */
function aw_relevance_directory() {
	global $wpdb;
	return $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		"SELECT DISTINCT p.post_title FROM {$wpdb->posts} p
		 JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
		 JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'aw_listing_type'
		 JOIN {$wpdb->terms} t ON t.term_id = tt.term_id AND t.slug IN ('animal-welfare-organisation','abc-centre','wildlife-rescue')
		 WHERE p.post_type = 'aw_listing' AND p.post_status = 'publish'"
	);
}

/** One item for the classifier. */
function aw_relevance_row( WP_Post $p ) {
	$id      = $p->ID;
	$press   = 'aw_press' === $p->post_type;
	$text    = $p->post_excerpt ? $p->post_excerpt : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $p->post_content ) ), 150 );
	$issuer  = function_exists( 'aw_item_issuer' ) ? aw_item_issuer( $id ) : null;
	$verdict = (string) get_post_meta( $id, 'aw_relevance', true );
	return array(
		'id'          => $id,
		'type'        => $p->post_type,
		'status'      => $p->post_status,
		'title'       => html_entity_decode( get_the_title( $p ), ENT_QUOTES ),
		'summary'     => html_entity_decode( (string) $text, ENT_QUOTES ),
		'source_url'  => (string) get_post_meta( $id, $press ? 'aw_press_url' : 'aw_source_url', true ),
		'source_name' => (string) get_post_meta( $id, $press ? 'aw_press_publisher' : 'aw_source_name', true ),
		'issuer'      => $issuer ? $issuer->name : '',
		'categories'  => 'post' === $p->post_type ? wp_get_post_terms( $id, 'category', array( 'fields' => 'slugs' ) ) : array(),
		'topics'      => 'aw_press' === $p->post_type ? array() : wp_get_post_terms( $id, 'aw_topic', array( 'fields' => 'slugs' ) ),
		'verdict'     => $verdict ? json_decode( $verdict, true ) : null,
	);
}

add_action(
	'rest_api_init',
	function () {
		$can = fn() => current_user_can( 'edit_posts' );
		register_rest_route(
			'aw/v1',
			'/relevance-config',
			array(
				'methods'             => 'GET',
				'permission_callback' => $can,
				'callback'            => fn() => new WP_REST_Response( array( 'categories' => aw_relevance_categories(), 'directory' => aw_relevance_directory() ) ),
			)
		);
		register_rest_route(
			'aw/v1',
			'/relevance-items',
			array(
				'methods'             => 'GET',
				'permission_callback' => $can,
				'callback'            => function ( WP_REST_Request $req ) {
					$args = array(
						'post_type'      => AW_RELEVANCE_TYPES,
						'post_status'    => array( 'publish', 'pending', 'draft', 'private', 'future' ),
						'posts_per_page' => max( 1, min( 200, (int) ( $req->get_param( 'per_page' ) ?: 100 ) ) ),
						'paged'          => max( 1, (int) $req->get_param( 'page' ) ),
						'orderby'        => 'ID',
						'order'          => 'ASC',
					);
					if ( $req->get_param( 'missing' ) ) {
						$args['meta_query'] = array( array( 'key' => 'aw_relevance', 'compare' => 'NOT EXISTS' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
					}
					$q = new WP_Query( $args );
					return new WP_REST_Response(
						array(
							'total' => (int) $q->found_posts,
							'pages' => (int) $q->max_num_pages,
							'items' => array_map( 'aw_relevance_row', $q->posts ),
						)
					);
				},
			)
		);
		register_rest_route(
			'aw/v1',
			'/relevance-verdicts',
			array(
				'methods'             => 'POST',
				'permission_callback' => $can,
				'callback'            => function ( WP_REST_Request $req ) {
					$n = 0;
					foreach ( (array) $req->get_param( 'verdicts' ) as $v ) {
						$id = (int) ( $v['id'] ?? 0 );
						if ( ! is_array( $v ) || ! $id || ! in_array( get_post_type( $id ), AW_RELEVANCE_TYPES, true ) ) {
							continue;
						}
						update_post_meta( $id, 'aw_relevance', wp_slash( aw_relevance_clean( $v ) ) ); // meta only: status untouched
						++$n;
					}
					return new WP_REST_Response( array( 'stored' => $n ) );
				},
			)
		);
		// The workers' check could not run (no AI service answered): items were held back, so the owner gets an email
		// (owner, 12 Oct 2026). At most one email per 6 hours; the count adds up in between.
		register_rest_route(
			'aw/v1',
			'/relevance-alert',
			array(
				'methods'             => 'POST',
				'permission_callback' => $can,
				'callback'            => function ( WP_REST_Request $req ) {
					$n    = max( 0, (int) $req->get_param( 'held' ) );
					$run  = sanitize_text_field( (string) $req->get_param( 'run' ) );
					$link = esc_url_raw( (string) $req->get_param( 'link' ) );
					if ( ! $n ) { // a run where the check worked clears the dashboard notice
						delete_option( 'aw_relevance_alert_notice' );
						return new WP_REST_Response( array( 'status' => 'clear' ) );
					}
					$prev = (array) get_option( 'aw_relevance_alert_notice', array() );
					update_option( 'aw_relevance_alert_notice', array( 'held' => $n + (int) ( $prev['held'] ?? 0 ), 'since' => $prev['since'] ?? current_time( 'j M Y, g:i a' ), 'run' => $link ), false );
					$total = $n + (int) get_option( 'aw_relevance_alert_pending', 0 );
					if ( get_transient( 'aw_relevance_alert_sent' ) ) {
						update_option( 'aw_relevance_alert_pending', $total, false );
						return new WP_REST_Response( array( 'status' => 'queued', 'held' => $total ) );
					}
					$sent = wp_mail(
						get_option( 'admin_email' ),
						'Animal Writes: ' . $total . ' item(s) held — the relevance check could not run',
						"The workers found {$total} new item(s) but no AI service answered the relevance check, so nothing was created for them. They are tried again on the next run (every 6 hours).\n\nRun: {$run}\n" . ( $link ? "Details: {$link}\n" : '' ) . "\nUsually this means the free daily limit of the AI keys is used up. Adding credit to a key (OpenRouter, OpenAI, DeepSeek …) clears it."
					);
					if ( $sent ) {
						set_transient( 'aw_relevance_alert_sent', 1, 6 * HOUR_IN_SECONDS );
						delete_option( 'aw_relevance_alert_pending' );
					}
					return new WP_REST_Response( array( 'status' => $sent ? 'sent' : 'mail failed', 'held' => $total ) );
				},
			)
		);
		register_rest_route(
			'aw/v1',
			'/relevance-rejected',
			array(
				'methods'             => 'POST',
				'permission_callback' => $can,
				'callback'            => function ( WP_REST_Request $req ) {
					$url   = esc_url_raw( (string) $req->get_param( 'source_url' ) );
					$entry = array(
						'title'  => sanitize_text_field( (string) $req->get_param( 'title' ) ),
						'url'    => $url,
						'reason' => sanitize_text_field( (string) $req->get_param( 'reason' ) ),
						'type'   => sanitize_key( (string) $req->get_param( 'type' ) ),
						'feed'   => sanitize_text_field( (string) $req->get_param( 'feed' ) ),
						'model'  => sanitize_text_field( (string) $req->get_param( 'model' ) ),
						'at'     => current_time( 'Y-m-d H:i' ),
					);
					$log = array_values( array_filter( (array) get_option( AW_RELEVANCE_LOG, array() ), fn( $e ) => is_array( $e ) && ( $e['url'] ?? '' ) !== $url ) );
					array_unshift( $log, $entry );
					update_option( AW_RELEVANCE_LOG, array_slice( $log, 0, AW_RELEVANCE_LOG_MAX ), false );
					return new WP_REST_Response( array( 'status' => 'logged' ) );
				},
			)
		);
	}
);

/** Stored verdict says "not relevant". */
function aw_relevance_failed( $post_id ) {
	$v = (string) get_post_meta( $post_id, 'aw_relevance', true );
	if ( '' === $v ) {
		return false;
	}
	$v = json_decode( $v, true );
	return is_array( $v ) && array_key_exists( 'relevant', $v ) && ! $v['relevant'];
}

// Settings → Sourcing → Rejected: the log of items the relevance check refused (never created). Administrators only.
add_filter(
	'kits_sourcing_tabs',
	function ( $tabs ) {
		if ( current_user_can( 'manage_options' ) ) {
			$tabs['rejected'] = array( 'Rejected', 'aw_relevance_rejected_tab' );
		}
		return $tabs;
	},
	20
);

/** The Rejected tab. */
function aw_relevance_rejected_tab() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$log = (array) get_option( AW_RELEVANCE_LOG, array() );
	echo '<p>' . esc_html( sprintf( 'Rejected: %d', count( $log ) ) ) . '</p>';
	if ( ! $log ) {
		return;
	}
	echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>Item</th><th style="width:9%">Type</th><th style="width:34%">Reason</th><th style="width:12%">Date</th></tr></thead><tbody>';
	foreach ( $log as $e ) {
		$host = (string) wp_parse_url( (string) ( $e['url'] ?? '' ), PHP_URL_HOST );
		echo '<tr><td><a href="' . esc_url( (string) ( $e['url'] ?? '' ) ) . '" target="_blank" rel="noopener">' . esc_html( (string) ( $e['title'] ?? '' ) ?: $host ) . '</a><br><span style="color:#50575e">' . esc_html( $host ) . '</span></td>'
			. '<td>' . esc_html( (string) ( $e['type'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $e['reason'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $e['at'] ?? '' ) ) . '</td></tr>';
	}
	echo '</tbody></table>';
}

// Dashboard: the same alert while items are held (owner, 12 Oct 2026); it clears after a run where the check works.
add_action(
	'admin_notices',
	function () {
		$a = get_option( 'aw_relevance_alert_notice' );
		if ( ! is_array( $a ) || empty( $a['held'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p><strong>Relevance check could not run.</strong> ' . esc_html( (int) $a['held'] . ' new item(s) held since ' . $a['since'] . ': no AI service answered, so nothing was created for them. They are tried again every 6 hours. Adding credit to an AI key clears it.' ) . ( ! empty( $a['run'] ) ? ' <a href="' . esc_url( $a['run'] ) . '" target="_blank" rel="noopener">Run details</a>' : '' ) . '</p></div>';
	}
);

/*
 * Ingestion heartbeat and watchdog (owner, 12 Oct 2026; no new News 10-12 Oct went unnoticed).
 * - Every worker run reports what it did: items created and published, sources tried and failed (POST ingest-heartbeat).
 * - An hourly check on the site itself (event aw_ingest_watchdog, and after every heartbeat) emails the owner and shows
 *   a dashboard notice when: no News has been published for 12 hours; no worker run has reported for 8 hours (Actions
 *   stopped, a run crashed or timed out, login refused); or half the sources failed in the last run.
 * One email per 12 hours for the same problems; a new kind of problem is emailed at once.
 */

/** Unix time of the newest published News item, or 0. */
function aw_newest_published_news() {
	$ids = get_posts( array( 'post_type' => 'aw_news', 'post_status' => 'publish', 'numberposts' => 1, 'orderby' => 'date', 'order' => 'DESC', 'fields' => 'ids', 'suppress_filters' => true ) );
	return $ids ? (int) get_post_time( 'U', true, $ids[0] ) : 0;
}

/**
 * What is wrong with ingestion right now, as key => sentence (empty when all is well).
 *
 * @param int|null $now Unix time (tests).
 * @return array<string,string>
 */
function aw_ingest_problems( $now = null ) {
	$now  = $now ? $now : time();
	$hb   = (array) get_option( 'aw_ingest_heartbeat', array() );
	$out  = array();
	$news = aw_newest_published_news();
	if ( $news && $now - $news >= 12 * HOUR_IN_SECONDS ) {
		$out['quiet'] = sprintf( 'No News has been published for %d hours (last: %s).', floor( ( $now - $news ) / HOUR_IN_SECONDS ), wp_date( 'j M Y, g:i a', $news ) );
	}
	$last = (int) ( $hb['last_run'] ?? 0 );
	if ( ! $last || $now - $last >= 8 * HOUR_IN_SECONDS ) {
		$out['no_run'] = $last ? sprintf( 'No worker run has reported for %d hours (last: %s). The GitHub Actions schedule may have stopped, or runs are failing before the end.', floor( ( $now - $last ) / HOUR_IN_SECONDS ), wp_date( 'j M Y, g:i a', $last ) ) : 'No worker run has reported yet.';
	}
	$n    = (int) ( $hb['sources'] ?? 0 );
	$fail = (int) ( $hb['sources_failed'] ?? 0 );
	if ( $n && $fail * 2 >= $n ) {
		$out['sources'] = sprintf( '%d of %d sources could not be fetched in the last run%s', $fail, $n, empty( $hb['errors'] ) ? '.' : ': ' . implode( '; ', array_slice( (array) $hb['errors'], 0, 3 ) ) );
	}
	return $out;
}

/** Email the owner about ingestion problems (hourly event, and after every heartbeat). */
function aw_ingest_watchdog() {
	$problems = aw_ingest_problems();
	update_option( 'aw_ingest_watchdog_last', array( 'at' => time(), 'problems' => $problems ), false );
	if ( ! $problems ) {
		delete_transient( 'aw_ingest_alert_mailed' );
		return;
	}
	$kinds = implode( ',', array_keys( $problems ) );
	$sent  = (string) get_transient( 'aw_ingest_alert_mailed' );
	if ( $sent && ! array_diff( array_keys( $problems ), explode( ',', $sent ) ) ) {
		return; // the same problems were emailed in the last 12 hours
	}
	$hb   = (array) get_option( 'aw_ingest_heartbeat', array() );
	$body = "Animal Writes ingestion needs attention:\n\n- " . implode( "\n- ", $problems ) . "\n\n" . ( ! empty( $hb['last_link'] ) ? 'Last run: ' . $hb['last_link'] . "\n" : '' ) . "Runs: https://github.com/wugweb-git/animal-writes-workers/actions\n";
	if ( wp_mail( get_option( 'admin_email' ), 'Animal Writes: ingestion problem — ' . reset( $problems ), $body ) ) {
		set_transient( 'aw_ingest_alert_mailed', $kinds, 12 * HOUR_IN_SECONDS );
	}
}
add_action( 'aw_ingest_watchdog', 'aw_ingest_watchdog' );
add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'aw_ingest_watchdog' ) ) {
			wp_schedule_event( time() + 600, 'hourly', 'aw_ingest_watchdog' );
		}
	}
);

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'aw/v1',
			'/ingest-heartbeat',
			array(
				'methods'             => 'POST',
				'permission_callback' => fn() => current_user_can( 'edit_posts' ),
				'callback'            => function ( WP_REST_Request $req ) {
					$now       = time();
					$new       = max( 0, (int) $req->get_param( 'new' ) );
					$published = null === $req->get_param( 'published' ) ? $new : max( 0, (int) $req->get_param( 'published' ) );
					$hb        = (array) get_option( 'aw_ingest_heartbeat', array() );
					$hb['last_run']       = $now;
					$hb['last_link']      = esc_url_raw( (string) $req->get_param( 'link' ) );
					$hb['new']            = $new;
					$hb['published']      = $published;
					$hb['sources']        = max( 0, (int) $req->get_param( 'sources' ) );
					$hb['sources_failed'] = max( 0, (int) $req->get_param( 'sources_failed' ) );
					$hb['errors']         = array_map( 'sanitize_text_field', array_slice( array_filter( (array) $req->get_param( 'errors' ), 'is_scalar' ), 0, 10 ) );
					if ( $published > 0 || empty( $hb['last_new'] ) ) {
						$hb['last_new'] = $now;
					}
					update_option( 'aw_ingest_heartbeat', $hb, false );
					aw_ingest_watchdog();
					return new WP_REST_Response( array( 'problems' => array_keys( aw_ingest_problems( $now ) ) ) );
				},
			)
		);
	}
);
add_action(
	'admin_notices',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$problems = aw_ingest_problems();
		if ( ! $problems ) {
			return;
		}
		$hb = (array) get_option( 'aw_ingest_heartbeat', array() );
		echo '<div class="notice notice-error"><p><strong>Ingestion needs attention.</strong> ' . esc_html( implode( ' ', $problems ) ) . ( ! empty( $hb['last_link'] ) ? ' <a href="' . esc_url( $hb['last_link'] ) . '" target="_blank" rel="noopener">Last run</a>' : '' ) . '</p></div>';
	}
);
