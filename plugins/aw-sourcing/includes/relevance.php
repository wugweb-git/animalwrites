<?php
/**
 * Relevance gate (owner, 10 Oct 2026): the workers ask a cheap model whether each item is about animal welfare in
 * India, fits a Category (WordPress's category) and, for documents, was issued by a known body, before anything is created.
 *
 * Endpoints (the ingest application-password user, edit_posts):
 * - GET  aw/v1/relevance-config    Categories (slug => name), each section's allowed categories and tags (sections:
 *                                  type => {categories: {slug: name}, tags: {slug: name}}), Directory organisation names (known issuers) and the
 *                                  titles of News from the last 4 days (recent), for the same-story check.
 * - GET  aw/v1/relevance-items     Existing News, Library, Resources, Opportunities and Press items, any status but
 *                                  trash; paginated (page, per_page ≤ 200, missing=1 for items without a verdict).
 * - POST aw/v1/relevance-verdicts  {verdicts: [{id, relevant, category, india, issuer, reason, model, at}]} → meta
 *                                  aw_relevance (JSON). Never changes an item's status.
 * - POST aw/v1/relevance-rejected  {title, source_url, reason, type, feed, model}, or {entries: [...]} for a run's
 *                                  rejections in one request → the Rejected log: option
 *                                  aw_relevance_rejected, newest first, last 500, one entry per URL. Shown under
 *                                  Settings → Sourcing → Rejected, to administrators only; never in To review.
 *
 * Inbox → To review shows only items the check passed (or has not checked): an item whose stored verdict is "not
 * relevant" is left out of the list and its counts.
 *
 * @package aw-sourcing
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

/** Categories: slug => name. */
function aw_relevance_categories() {
	$out = array();
	foreach ( get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false ) ) as $t ) {
		$out[ $t->slug ] = html_entity_decode( $t->name );
	}
	return $out;
}

/** Each section's allowed categories and tags (Settings → Sourcing → Sections): type => {categories, tags} as slug => name. */
function aw_relevance_sections() {
	$out   = array();
	$names = function ( $tax, $slugs ) {
		$o = array();
		foreach ( $slugs ? get_terms( array( 'taxonomy' => $tax, 'slug' => $slugs, 'hide_empty' => false ) ) : array() as $t ) {
			$o[ $t->slug ] = html_entity_decode( $t->name );
		}
		return (object) $o;
	};
	foreach ( array_keys( aw_post_types() ) as $type ) {
		$out[ $type ] = array( 'categories' => $names( 'category', aw_section_categories( $type ) ), 'tags' => $names( 'post_tag', aw_section_tags( $type ) ) );
	}
	return $out;
}

/** Directory organisations (animal-welfare organisations, ABC centres, wildlife rescue): published names. */
function aw_relevance_directory() {
	global $wpdb;
	return $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		"SELECT DISTINCT p.post_title FROM {$wpdb->posts} p
		 JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
		 JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'post_tag'
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
	$rtypes  = 'post' === $p->post_type ? wp_list_pluck( aw_item_tags( $id, aw_section_tags( 'post' ) ), 'slug' ) : array();
	$row     = array(
		'id'          => $id,
		'type'        => $p->post_type,
		'status'      => $p->post_status,
		'title'       => html_entity_decode( get_the_title( $p ), ENT_QUOTES ),
		'summary'     => html_entity_decode( (string) $text, ENT_QUOTES ),
		'source_url'  => (string) get_post_meta( $id, $press ? 'aw_press_url' : 'aw_source_url', true ),
		'source_name' => (string) get_post_meta( $id, $press ? 'aw_press_publisher' : 'aw_source_name', true ),
		'issuer'      => $issuer ? $issuer->name : '',
		'resource_types' => $rtypes, // the Resource type tags (research, guide ...)
		'tags'           => 'aw_press' === $p->post_type ? array() : wp_list_pluck( aw_item_tags( $id ), 'slug' ),
		'categories'     => 'aw_press' === $p->post_type ? array() : wp_get_post_terms( $id, 'category', array( 'fields' => 'slugs' ) ),
		'verdict'     => $verdict ? json_decode( $verdict, true ) : null,
	);
	return $row;
}

/** Titles of News published or waiting from the last 4 days: the workers ask the AI whether a new item is one of these stories. */
function aw_relevance_recent_titles(): array {
	$ids = get_posts(
		array(
			'post_type'      => array( 'aw_news', 'aw_legal' ), // court reports in the Library repeat each other too (owner, 14 Oct 2026)
			'post_status'    => array( 'publish', 'pending', 'draft', 'future' ),
			'date_query'     => array( array( 'after' => '4 days ago' ) ),
			'posts_per_page' => 400,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	return array_values( array_map( fn( $id ) => html_entity_decode( get_the_title( $id ), ENT_QUOTES ), $ids ) );
}

/** The same stories with their IDs, so the workers can add a later report's facts to the first one (same-story.php). */
function aw_relevance_recent_items(): array {
	$ids = get_posts(
		array(
			'post_type'      => array( 'aw_news', 'aw_legal' ),
			'post_status'    => array( 'publish', 'pending', 'draft', 'future' ),
			'date_query'     => array( array( 'after' => '4 days ago' ) ),
			'posts_per_page' => 400,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	return array_values( array_map( fn( $id ) => array( 'id' => (int) $id, 'title' => html_entity_decode( get_the_title( $id ), ENT_QUOTES ) ), $ids ) );
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
				'callback'            => fn() => new WP_REST_Response( array( 'categories' => aw_relevance_categories(), 'sections' => aw_relevance_sections(), 'directory' => aw_relevance_directory(), 'recent' => aw_relevance_recent_titles(), 'recent_items' => aw_relevance_recent_items() ) ),
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
					$batch = $req->get_param( 'entries' ); // the workers send a run's rejections in one request
					$batch = is_array( $batch ) ? array_slice( $batch, 0, AW_RELEVANCE_LOG_MAX ) : array( $req->get_params() );
					$log   = (array) get_option( AW_RELEVANCE_LOG, array() );
					foreach ( $batch as $e ) {
						$e     = (array) $e;
						$url   = esc_url_raw( (string) ( $e['source_url'] ?? '' ) );
						$entry = array(
							'title'  => sanitize_text_field( (string) ( $e['title'] ?? '' ) ),
							'url'    => $url,
							'reason' => sanitize_text_field( (string) ( $e['reason'] ?? '' ) ),
							'type'   => sanitize_key( (string) ( $e['type'] ?? '' ) ),
							'feed'   => sanitize_text_field( (string) ( $e['feed'] ?? '' ) ),
							'kw'     => function_exists( 'aw_sourcing_feed_keyword' ) ? aw_sourcing_feed_keyword( (string) ( $e['feed'] ?? '' ) ) : '',
							'model'  => sanitize_text_field( (string) ( $e['model'] ?? '' ) ),
							'at'     => current_time( 'Y-m-d H:i' ),
						);
						$log = array_values( array_filter( $log, fn( $x ) => is_array( $x ) && ( $x['url'] ?? '' ) !== $url ) );
						array_unshift( $log, $entry );
					}
					update_option( AW_RELEVANCE_LOG, array_slice( $log, 0, AW_RELEVANCE_LOG_MAX ), false );
					return new WP_REST_Response( array( 'status' => 'logged' ) );
				},
			)
		);
	}
);

/**
 * Why an incoming item is not News (owner, 20 Oct 2026), '' when it may be: an archive or tag page of a news site
 * ("Read all latest updates on and about …", "Latest news on …"), or a job vacancy from a source that is not an
 * opportunity source ("… vacancy at …", "… recruitment 2026 - apply offline", /job-updates/ addresses). The same rules
 * as the workers' sources.not_an_article(); a story about vacancies ("flags 63.81% vacancies in …") passes.
 *
 * @param string $title Headline.
 * @param string $url   Source URL.
 * @param string $type  Post type it would become.
 * @return string
 */
function aw_not_news_reason( $title, $url, $type ) {
	$title = (string) $title;
	$path  = (string) wp_parse_url( (string) $url, PHP_URL_PATH ) . '/';
	$host  = strtolower( (string) wp_parse_url( (string) $url, PHP_URL_HOST ) );
	if ( preg_match( '#/(?:tags?|topics?|category|categories|author|authors|search|section)/#i', $path )
		|| preg_match( '/^\s*(?:read\s+)?(?:all\s+)?(?:the\s+)?latest\s+(?:news|updates|stories|headlines|articles)\b(?:\s*(?:,|and|&)\s*\w+){0,3}\s+(?:on|about|from|for|of)\b|\blatest\s+news\s*,\s*(?:photos|videos|updates)\b|^\s*(?:all\s+)?(?:news|updates)\s+(?:on\s+and\s+)?about\b/i', $title ) ) {
		return 'an archive or tag page of a news site, not a news report';
	}
	if ( 'aw_opportunity' !== $type && (
		preg_match( '/(?:^|\.)(?:freejobalert|sarkariresult|sarkarinaukri\w*|employmentnews|naukri|indeed|freshersworld|jobsarkari|govtjobsalert\w*)\.(?:com|in|co\.in|gov\.in|org)$/i', $host )
		|| preg_match( '#/(?:jobs?|job-updates?|careers?|recruitments?|vacanc(?:y|ies))(?:/|-)#i', $path )
		|| preg_match( '/\bvacancy\s+(?:at|in|for|with)\b|\bvacancies\s+(?:at|in|for|with)\b.{0,80}\b(?:apply|recruitment|notification|last\s+date)\b|\brecruitment\s+20\d\d\b|\b(?:apply|applications?)\s+(?:online|offline|now|invited)\b|\bwalk[- ]in\s+interview\b|\b(?:job|career)\s+(?:opening|openings|alert|notification|vacancy)\b|\bnow\s+hiring\b|\bis\s+hiring\b/i', $title ) ) ) {
		return 'a job vacancy, not a news report (jobs come from the opportunity sources)';
	}
	return '';
}

/** Add one entry to the Rejected log (Settings → Sourcing → Rejected), as the relevance-rejected endpoint does. */
function aw_relevance_log_one( $title, $url, $reason, $type, $feed = '' ) {
	$url = esc_url_raw( (string) $url );
	$log = array_values( array_filter( (array) get_option( AW_RELEVANCE_LOG, array() ), fn( $x ) => is_array( $x ) && ( $x['url'] ?? '' ) !== $url ) );
	array_unshift(
		$log,
		array(
			'title'  => sanitize_text_field( (string) $title ),
			'url'    => $url,
			'reason' => sanitize_text_field( (string) $reason ),
			'type'   => sanitize_key( (string) $type ),
			'feed'   => sanitize_text_field( (string) $feed ),
			'kw'     => function_exists( 'aw_sourcing_feed_keyword' ) ? aw_sourcing_feed_keyword( (string) $feed ) : '',
			'model'  => 'site rule',
			'at'     => current_time( 'Y-m-d H:i' ),
		)
	);
	update_option( AW_RELEVANCE_LOG, array_slice( $log, 0, AW_RELEVANCE_LOG_MAX ), false );
}

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
	echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>Item</th><th style="width:9%">Type</th><th style="width:16%">Found by</th><th style="width:30%">Reason</th><th style="width:12%">Date</th></tr></thead><tbody>';
	foreach ( $log as $e ) {
		$host = (string) wp_parse_url( (string) ( $e['url'] ?? '' ), PHP_URL_HOST );
		echo '<tr><td><a href="' . esc_url( (string) ( $e['url'] ?? '' ) ) . '" target="_blank" rel="noopener">' . esc_html( (string) ( $e['title'] ?? '' ) ?: $host ) . '</a><br><span style="color:#50575e">' . esc_html( $host ) . '</span></td>'
			. '<td>' . esc_html( (string) ( $e['type'] ?? '' ) ) . '</td><td>' . esc_html( '' !== (string) ( $e['kw'] ?? '' ) ? 'Keyword: ' . $e['kw'] : (string) ( $e['feed'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $e['reason'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $e['at'] ?? '' ) ) . '</td></tr>';
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

/**
 * Unix time of the newest News item published, or 0. A story carries its source's date (an item found at 5 am about
 * yesterday's news is dated yesterday), so the date says nothing about when it went live: the five most recently added
 * items are asked for the time they were last saved (owner, 21 Oct 2026; the notice and the e-mail said "no News for
 * 16 hours" while the workers were publishing).
 */
function aw_newest_published_news() {
	global $wpdb;
	// Straight from the table: the News lists' pre_get_posts (display.php) re-sorts every front-end aw_news query by
	// source date, so get_posts() returned yesterday-dated items added last night after older ones, and the watchdog
	// e-mailed "no News for 12 hours" while 13 items had gone live overnight (24 Oct 2026).
	$ids  = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'aw_news' AND post_status = 'publish' ORDER BY ID DESC LIMIT 5" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$best = 0;
	foreach ( $ids as $id ) {
		$best = max( $best, (int) get_post_time( 'U', true, $id ), (int) get_post_modified_time( 'U', true, $id ) );
	}
	return $best;
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
	// Scheduled tasks (cron-health.php): no trigger has called wp-cron.php for 30 minutes.
	$cron = function_exists( 'aw_cron_last' ) ? aw_cron_last() : 0;
	if ( $cron && $now - $cron >= AW_CRON_STALE ) {
		$out['cron'] = sprintf( 'Scheduled tasks have not run for %d minutes (last: %s). Hostinger\'s server cron, the GitHub "WordPress cron" job and the outside monitor have all stopped.', floor( ( $now - $cron ) / MINUTE_IN_SECONDS ), wp_date( 'j M Y, g:i a', $cron ) );
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
	update_option( 'aw_ingest_watchdog_at', time(), true );
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
// The check must not depend on what it watches (cron, GitHub): any request that reaches PHP also runs it when the last
// one is an hour old, or 10 minutes old while scheduled tasks look stopped (two autoloaded option reads otherwise).
add_action(
	'shutdown',
	function () {
		$age   = time() - (int) get_option( 'aw_ingest_watchdog_at', 0 );
		$cron  = function_exists( 'aw_cron_last' ) ? aw_cron_last() : 0;
		$stale = $cron && time() - $cron >= AW_CRON_STALE;
		if ( ( $age >= HOUR_IN_SECONDS || ( $stale && $age >= 10 * MINUTE_IN_SECONDS ) ) && ! wp_installing() ) {
			aw_ingest_watchdog();
		}
	}
);
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
					$hb['lists']          = sanitize_key( (string) $req->get_param( 'lists' ) ); // site or yaml: which lists the run used
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
