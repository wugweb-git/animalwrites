<?php
/**
 * Sourcing policy managed in WP Admin (Settings → Sourcing): sources (publishers) and relevance keywords. Moved from Kits Core
 * (24 Oct 2026); the sourced post types, aw_host(), aw_publishers() and the option constants stay there.
 *
 * - Publishers: every host the workers send is recorded automatically. Each is Allowed, Neutral or Blocked.
 *   Blocked hosts are refused by the ingest endpoint. With "Only allowed publishers from aggregator feeds" on
 *   (off by default), aggregator items (Google News, Apple News...) from hosts that are not Allowed are refused too.
 * - Keywords: strong / animal / context / exclude lists. When the relevance filter is on, an item is kept if
 *   it has a strong term, or an animal term and a context term, and no exclude term.
 * - The workers read the same policy from GET /wp-json/aw/v1/sourcing-policy before each run, so blocked or
 *   irrelevant items are skipped before they are summarised; the endpoint enforces it again on arrival.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Aggregator feeds (Google News today; Apple News or others later): feed ids starting gnews-, applenews- or agg-.
 *
 * @param string $feed Worker feed id.
 * @return bool
 */
function aw_is_aggregator_feed( $feed ) {
	return (bool) preg_match( '/^(gnews|applenews|agg)-/', (string) $feed );
}

/**
 * A publisher's outside credibility rating (Media Bias/Fact Check: factual reporting · credibility), when recorded.
 * Stored on the publisher record as rating, credibility and rating_url. Shown for information; it does not change the status.
 *
 * @param array $p Publisher record.
 * @return string
 */
function aw_publisher_rating_html( $p ) {
	if ( empty( $p['rating'] ) ) {
		return '<span aria-label="Not rated">–</span>';
	}
	$txt = $p['rating'] . ( ! empty( $p['credibility'] ) ? ' · ' . $p['credibility'] . ' credibility' : '' );
	return ! empty( $p['rating_url'] ) ? '<a href="' . esc_url( $p['rating_url'] ) . '" target="_blank" rel="noopener">' . esc_html( $txt ) . '</a>' : esc_html( $txt );
}

/** A publisher's status in words with a core dashicon (no custom CSS). */
function aw_publisher_status_html( $st ) {
	$map = array(
		'allow'   => array( 'yes-alt', 'Allowed' ),
		'neutral' => array( 'minus', 'Neutral' ),
		'block'   => array( 'dismiss', 'Blocked' ),
	);
	$m = $map[ $st ] ?? $map['neutral'];
	return '<span class="dashicons dashicons-' . esc_attr( $m[0] ) . '" aria-hidden="true"></span> ' . esc_html( $m[1] );
}

/**
 * Change log of the Sourcing screen: who changed what, when (newest first, last 1,000 kept; 300 before 25 Oct 2026).
 * An add, edit or delete also gives the value before and after (shown as "old → new"); both are optional, so the
 * existing calls and entries stay as they are.
 *
 * @param string $tab  Tab.
 * @param string $what What changed, in words.
 * @param mixed  $old  Value before (null: not given; for an add, '' or an empty list).
 * @param mixed  $new  Value after (null: not given; for a delete, '' or an empty list).
 */
function kits_sourcing_log( $tab, $what, $old = null, $new = null ) {
	if ( '' === trim( $what ) ) {
		return;
	}
	$log = (array) get_option( 'kits_sourcing_log', array() );
	$log = aw_src_log_push( $log, array( 'at' => current_time( 'mysql' ), 'user' => wp_get_current_user()->display_name, 'tab' => $tab, 'what' => $what ), $old, $new );
	update_option( 'kits_sourcing_log', $log, false );
}

/** What changed between two lists, in words ("added: a, b; removed: c"). */
function kits_sourcing_diff( $label, $old, $new ) {
	$add = array_diff( (array) $new, (array) $old );
	$del = array_diff( (array) $old, (array) $new );
	$out = array();
	if ( $add ) {
		$out[] = 'added ' . implode( ', ', $add );
	}
	if ( $del ) {
		$out[] = 'removed ' . implode( ', ', $del );
	}
	return $out ? $label . ': ' . implode( '; ', $out ) : '';
}

/** Relevance settings. */
function aw_relevance() {
	$r = get_option( AW_REL_OPTION );
	return is_array( $r ) ? $r : array( 'enabled' => false, 'gnews_allow_only' => false, 'strong' => array(), 'animal' => array(), 'context' => array(), 'exclude' => array() );
}

/** Seed once: the two blocks you asked for, the proposed allow list (inactive until the switch is on), the proposed keyword lists (filter off). */
add_action(
	'init',
	function () {
		if ( false !== get_option( AW_REL_OPTION ) ) {
			return;
		}
		$now  = current_time( 'Y-m-d' );
		$pubs = array();
		foreach ( array( 'bollyspice.com', 'storyboard18.com' ) as $h ) {
			$pubs[ $h ] = array( 'status' => 'block', 'name' => '', 'count' => 0, 'first' => $now, 'last' => '' );
		}
		foreach ( array( 'thehindu.com', 'indianexpress.com', 'hindustantimes.com', 'timesofindia.indiatimes.com', 'ndtv.com', 'theprint.in', 'scroll.in', 'thewire.in', 'newindianexpress.com', 'deccanherald.com', 'indiatoday.in', 'news18.com', 'aninews.in', 'ptinews.com', 'wionews.com', 'mid-day.com', 'thenewsmill.com', 'kashmirobserver.net', 'downtoearth.org.in', 'news.mongabay.com', 'livelaw.in', 'barandbench.com', 'scobserver.in', 'pib.gov.in', 'awbi.gov.in', 'petaindia.com' ) as $h ) {
			$pubs[ $h ] = array( 'status' => 'allow', 'name' => '', 'count' => 0, 'first' => $now, 'last' => '' );
		}
		// Hosts already in the content, with their counts.
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT m.meta_value url, n.meta_value name, p.post_date d FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'aw_source_url' LEFT JOIN {$wpdb->postmeta} n ON n.post_id = p.ID AND n.meta_key = 'aw_source_name' WHERE p.post_type IN ('" . implode( "','", array_map( 'esc_sql', kits_sourced_types() ) ) . "')" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( $rows as $r ) {
			$h = aw_host( $r->url );
			if ( ! $h ) {
				continue;
			}
			$rec            = $pubs[ $h ] ?? array( 'status' => 'neutral', 'name' => '', 'count' => 0, 'first' => substr( $r->d, 0, 10 ), 'last' => '' );
			$rec['count']   = (int) $rec['count'] + 1;
			$rec['name']    = $rec['name'] ? $rec['name'] : (string) $r->name;
			$rec['last']    = max( (string) $rec['last'], substr( $r->d, 0, 10 ) );
			$rec['first']   = min( (string) $rec['first'], substr( $r->d, 0, 10 ) );
			$pubs[ $h ]     = $rec;
		}
		update_option( AW_PUB_OPTION, $pubs, false );
		update_option(
			AW_REL_OPTION,
			array(
				'enabled'          => false,
				'gnews_allow_only' => false,
				'strong'           => array( 'animal welfare', 'animal cruelty', 'cruelty to animals', 'stray dog', 'street dog', 'community dog', 'animal birth control', 'ABC Rules', 'PCA Act', 'Prevention of Cruelty', 'AWBI', 'Animal Welfare Board', 'gaushala', 'rabies', 'animal rescue', 'animal shelter', 'wildlife crime', 'poaching', 'पशु क्रूरता', 'रेबीज' ),
				'animal'           => array( 'animal', 'animals', 'dog', 'dogs', 'cat', 'cats', 'cattle', 'cow', 'cows', 'bull', 'buffalo', 'horse', 'donkey', 'elephant', 'leopard', 'tiger', 'monkey', 'bird', 'birds', 'wildlife', 'livestock', 'पशु', 'जानवर', 'कुत्ता', 'गाय' ),
				'context'          => array( 'court', 'judgment', 'rules', 'law', 'police', 'FIR', 'arrested', 'municipal', 'ministry', 'minister', 'policy', 'rescue', 'shelter', 'veterinary', 'vet', 'hospital', 'sterilisation', 'vaccination', 'feeding', 'attack', 'bite', 'cruelty', 'adoption', 'smuggling', 'slaughter', 'transport' ),
				'exclude'          => array( 'party animal', 'bull market', 'bear market', 'cattle class', 'dog-whistle', 'top dog', 'underdog', 'celebrity pet', 'pet project', 'brand campaign', 'IPO', 'box office' ),
			),
			false
		);
	}
);

/**
 * Does text contain a term? Whole-word for short ASCII terms, substring otherwise (Hindi, phrases).
 *
 * @param string $hay  Lowercased text.
 * @param string $term Term.
 * @return bool
 */
function aw_has_term( $hay, $term ) {
	$t = mb_strtolower( trim( $term ) );
	if ( '' === $t ) {
		return false;
	}
	if ( str_ends_with( $t, '*' ) ) { // a prefix ("steriliz*"): the shared matcher, as in the workers (policy.has_term)
		return aw_match_term( $hay, $t );
	}
	if ( preg_match( "/^[a-z0-9 \\-'’]+$/u", $t ) ) { // Latin terms: whole words only, plural allowed ("hen" no longer matches "when")
		return (bool) preg_match( '/(?<![a-z0-9])' . preg_quote( $t, '/' ) . '(?:s|es)?(?![a-z0-9])/u', $hay );
	}
	return false !== mb_strpos( $hay, $t );
}

/**
 * Relevance verdict for a piece of text under the current keyword lists.
 *
 * @param string $text Title and summary.
 * @return array{0: bool, 1: string} keep?, reason.
 */
function aw_relevance_verdict( $text ) {
	$r   = aw_relevance();
	$hay = mb_strtolower( $text );
	foreach ( (array) $r['exclude'] as $t ) {
		if ( aw_has_term( $hay, $t ) ) {
			return array( false, 'exclude term: ' . $t );
		}
	}
	foreach ( (array) $r['strong'] as $t ) {
		if ( aw_has_term( $hay, $t ) ) {
			return array( true, 'strong term: ' . $t );
		}
	}
	$a = null;
	foreach ( (array) $r['animal'] as $t ) {
		if ( aw_has_term( $hay, $t ) ) {
			$a = $t;
			break;
		}
	}
	if ( $a ) {
		foreach ( (array) $r['context'] as $t ) {
			if ( aw_has_term( $hay, $t ) ) {
				return array( true, "animal term: {$a} + context term: {$t}" );
			}
		}
		return array( false, "animal term ({$a}) without a context term" );
	}
	return array( false, 'no animal or strong term' );
}

/**
 * Record a host and decide whether an incoming item may be stored.
 * Called by aw_ingest() before anything is created.
 *
 * @param string $url   Source URL.
 * @param string $name  Publisher name.
 * @param string $feed  Worker feed id (gnews-* for Google News).
 * @param string $text  Title + summary.
 * @return string '' to accept, or 'blocked' / 'filtered'.
 */
function aw_sourcing_gate( $url, $name, $feed, $text ) {
	$host = aw_host( $url );
	if ( ! $host ) {
		return '';
	}
	$pubs = aw_publishers();
	$rec  = $pubs[ $host ] ?? array( 'status' => 'neutral', 'name' => '', 'count' => 0, 'first' => current_time( 'Y-m-d' ), 'last' => '' );
	$rec['name']  = $rec['name'] ? $rec['name'] : sanitize_text_field( $name );
	$rec['last']  = current_time( 'Y-m-d' );
	$rec['count'] = (int) $rec['count'] + 1;
	$pubs[ $host ] = $rec;
	update_option( AW_PUB_OPTION, $pubs, false );

	$rel = aw_relevance();
	if ( 'block' === $rec['status'] ) {
		return 'blocked';
	}
	if ( ! empty( $rel['gnews_allow_only'] ) && aw_is_aggregator_feed( $feed ) && 'allow' !== $rec['status'] ) {
		return 'blocked';
	}
	if ( ! empty( $rel['enabled'] ) && 'allow' !== $rec['status'] && ! aw_relevance_verdict( $text )[0] ) {
		return 'filtered';
	}
	return '';
}

/* ---------- Policy endpoint for the workers ---------- */

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'aw/v1',
			'/sourcing-policy',
			array(
				'methods'             => 'GET',
				'permission_callback' => fn() => current_user_can( 'edit_posts' ),
				'callback'            => function () {
					$pubs = aw_publishers();
					$rel  = aw_relevance();
					$by   = fn( $s ) => array_values( array_keys( array_filter( $pubs, fn( $p ) => ( $p['status'] ?? '' ) === $s ) ) );
					$data = array(
						'publishers' => array( 'blocked' => $by( 'block' ), 'allowed' => $by( 'allow' ), 'gnews_allow_only' => ! empty( $rel['gnews_allow_only'] ) ),
						'relevance'  => array( 'enabled' => ! empty( $rel['enabled'] ), 'strong' => $rel['strong'], 'animal' => $rel['animal'], 'context' => $rel['context'], 'exclude' => $rel['exclude'] ),
					);
					return apply_filters( 'kits_sourcing_policy', $data ); // a site adds its source lists here
				},
			)
		);
	}
);

/* ---------- WP Admin: News → Sourcing ---------- */

add_action(
	'admin_menu',
	function () {
		add_options_page( 'Sourcing', 'Sourcing', AW_SRC_CAP, 'aw-sourcing', 'aw_sourcing_page' ); // Settings → Sourcing
	}
);

// Screen options: sources per page on the Sources tab (the publishers' option, as before).
add_action(
	'load-settings_page_aw-sourcing',
	function () {
		$tab = sanitize_key( $_GET['tab'] ?? 'sources' ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( in_array( $tab, array( '', 'sources' ), true ) && empty( $_GET['edit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			add_screen_option( 'per_page', array( 'label' => 'Sources per page', 'default' => 50, 'option' => 'aw_publishers_per_page' ) );
		} elseif ( 'keywords' === $tab && 'suggestions' !== sanitize_key( $_GET['kw'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- the Suggestions view has its own
			add_screen_option( 'per_page', array( 'label' => 'Words per page', 'default' => 100, 'option' => 'aw_keywords_per_page' ) );
		}
	}
);
add_filter( 'set_screen_option_aw_publishers_per_page', fn( $keep, $option, $value ) => max( 1, min( 500, (int) $value ) ), 10, 3 );
add_filter( 'set_screen_option_aw_keywords_per_page', fn( $keep, $option, $value ) => max( 1, min( 500, (int) $value ) ), 10, 3 );

/** Save handlers. */
add_action(
	'admin_post_aw_sourcing',
	function () {
		if ( ! current_user_can( AW_SRC_CAP ) || ! check_admin_referer( 'aw_sourcing' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$tab = sanitize_key( $_POST['tab'] ?? 'sources' );
		$tab = array( 'publishers' => 'sources', 'library' => 'feeds', 'opportunities' => 'feeds' )[ $tab ] ?? $tab; // a form from before the tabs were merged
		$err = '';
		if ( 'sources' !== $tab ) {
			do_action( 'kits_sourcing_save_' . $tab ); // a site's own tabs save themselves (and log)
		} elseif ( ! empty( $_POST['src_edit'] ) ) { // the Add New / Edit Source form (source-sites.php)
			list( $changed, $err ) = aw_sourcing_source_save();
		} else {
			$pubs   = aw_publishers();
			$before = $pubs;
			$row    = sanitize_text_field( wp_unslash( $_POST['row_status'] ?? '' ) ); // a row action: "status:host"
			$prow   = sanitize_text_field( wp_unslash( $_POST['row_press'] ?? '' ) ); // a row action: "add|remove:host"
			if ( false !== strpos( $row, ':' ) ) {
				list( $status, $one ) = explode( ':', $row, 2 );
				$status               = sanitize_key( $status );
				$hosts                = array( $one );
			} elseif ( false !== strpos( $prow, ':' ) ) {
				list( $status, $one ) = explode( ':', $prow, 2 );
				$status               = 'press_' . sanitize_key( $status );
				$hosts                = array( $one );
			} else { // bulk action, from the top or the bottom of the list
				$status = sanitize_key( $_POST['bulk_status'] ?? '' );
				$status = '' !== $status ? $status : sanitize_key( $_POST['bulk_status2'] ?? '' );
				$hosts  = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['hosts'] ?? array() ) );
			}
			$press = false;
			if ( in_array( $status, array( 'press_add', 'press_remove' ), true ) ) { // Search here for coverage / Stop searching here
				$hosts = array_values( array_filter( array_map( 'aw_sourcing_norm_host', $hosts ) ) );
				$press = aw_sourcing_press_change( 'press_add' === $status ? $hosts : array(), 'press_remove' === $status ? $hosts : array() );
			} elseif ( in_array( $status, array( 'allow', 'neutral', 'block' ), true ) ) {
				foreach ( $hosts as $h ) {
					if ( isset( $pubs[ $h ] ) ) {
						$pubs[ $h ]['status'] = $status;
					}
				}
				if ( $hosts ) {
					kits_sourcing_log( 'publishers', implode( ', ', $hosts ) . ' → ' . $status );
				}
			}
			$new = aw_host( 'https://' . preg_replace( '#^https?://#', '', sanitize_text_field( wp_unslash( $_POST['new_host'] ?? '' ) ) ) );
			$ns  = sanitize_key( $_POST['new_status'] ?? 'block' );
			if ( $new && in_array( $ns, array( 'allow', 'neutral', 'block' ), true ) ) {
				$pubs[ $new ] = array_merge( array( 'name' => '', 'count' => 0, 'first' => current_time( 'Y-m-d' ), 'last' => '' ), $pubs[ $new ] ?? array(), array( 'status' => $ns ) );
				kits_sourcing_log( 'publishers', 'added ' . $new . ' as ' . $ns );
			}
			$shorts = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['short'] ?? array() ) ); // short names for card chips
			foreach ( $shorts as $h => $v ) {
				if ( isset( $pubs[ $h ] ) && (string) ( $pubs[ $h ]['short'] ?? '' ) !== $v ) {
					$pubs[ $h ]['short'] = $v;
					kits_sourcing_log( 'publishers', $h . ' short name → ' . ( '' === $v ? '(none)' : $v ) );
				}
			}
			update_option( AW_PUB_OPTION, $pubs, false );
			$changed = $pubs !== $before || $press; // a page change alone saves nothing
		}
		$back = add_query_arg( array( 'page' => 'aw-sourcing', 'tab' => $tab, 'updated' => 1 ), admin_url( 'options-general.php' ) );
		if ( 'sources' === $tab && ! empty( $_POST['src_edit'] ) ) { // the edit form: back to the list, or to the form with what was wrong
			$back = add_query_arg( array( 'page' => 'aw-sourcing', 'tab' => 'sources' ), admin_url( 'options-general.php' ) );
			$back = $err ? add_query_arg( 'src_err', $err, wp_get_referer() ? wp_get_referer() : $back ) : ( $changed ? add_query_arg( 'updated', 1, $back ) : $back );
		} elseif ( 'sources' === $tab ) { // back to the same view, sort and page of the list, with its search
			$back = remove_query_arg( array( 'updated', 'paged', 'src_err' ), wp_get_referer() ? wp_get_referer() : $back );
			$page = absint( wp_unslash( $_POST['paged'] ?? 0 ) ); // the page-number box of the list
			$back = $page > 1 ? add_query_arg( 'paged', $page, $back ) : $back;
			$back = empty( $changed ) ? $back : add_query_arg( 'updated', 1, $back );
		}
		wp_safe_redirect( $back );
		exit;
	}
);

/**
 * The Keywords tab's word lists: add words (the form above the list) and the list table's row and bulk actions
 * (remove, move to another list). Logged in the Change log like every Sourcing change.
 */
add_action(
	'admin_post_aw_sourcing_keywords',
	function () {
		$adding = isset( $_POST['kw_add'] ); // phpcs:ignore WordPress.Security.NonceVerification -- picks which nonce is checked next
		if ( ! current_user_can( AW_SRC_CAP ) || ! check_admin_referer( $adding ? 'aw_sourcing_keywords_add' : 'bulk-keywords' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$lists = array( 'strong' => 'Include', 'animal' => 'Animal words', 'context' => 'Context words', 'exclude' => 'Exclude' );
		$rel   = aw_relevance();
		$old   = $rel;
		$n     = 0;
		$op    = 'add';
		$has   = function ( $k, $t ) use ( &$rel ) {
			return in_array( mb_strtolower( $t ), array_map( 'mb_strtolower', (array) ( $rel[ $k ] ?? array() ) ), true );
		};
		if ( $adding ) {
			$to = sanitize_key( $_POST['kw_list'] ?? '' );
			$to = isset( $lists[ $to ] ) ? $to : 'strong';
			foreach ( explode( "\n", sanitize_textarea_field( wp_unslash( $_POST['kw_new'] ?? '' ) ) ) as $t ) {
				$t = trim( $t );
				if ( '' !== $t && ! $has( $to, $t ) ) {
					$rel[ $to ][] = $t;
					++$n;
				}
			}
		} else {
			$row = sanitize_text_field( wp_unslash( $_POST['kw_row'] ?? '' ) ); // a row action: "op:list|word"
			if ( false !== strpos( $row, ':' ) ) {
				list( $op, $one ) = explode( ':', $row, 2 );
				$ids              = array( $one );
			} else { // bulk action, from the top or the bottom of the list
				$op  = sanitize_key( $_POST['kw_bulk'] ?? '' );
				$op  = '' !== $op ? $op : sanitize_key( $_POST['kw_bulk2'] ?? '' );
				$ids = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['kw'] ?? array() ) );
			}
			$op = sanitize_key( $op );
			$to = 0 === strpos( $op, 'to_' ) ? substr( $op, 3 ) : '';
			if ( 'remove' === $op || isset( $lists[ $to ] ) ) {
				foreach ( $ids as $id ) {
					list( $from, $t ) = array_pad( explode( '|', (string) $id, 2 ), 2, '' );
					if ( ! isset( $lists[ $from ] ) || $from === $to ) {
						continue;
					}
					$at = array_search( $t, (array) ( $rel[ $from ] ?? array() ), true );
					if ( false === $at ) {
						continue;
					}
					unset( $rel[ $from ][ $at ] );
					$rel[ $from ] = array_values( $rel[ $from ] );
					if ( $to && ! $has( $to, $t ) ) {
						$rel[ $to ][] = $t;
					}
					++$n;
				}
			}
		}
		if ( $n ) {
			update_option( AW_REL_OPTION, $rel, false );
			delete_transient( 'aw_sourcing_kw_stats_30' ); // Search Insights' per-keyword rows follow the new lists at once
			$what = array();
			foreach ( $lists as $k => $l ) {
				$what[] = kits_sourcing_diff( $l, $old[ $k ] ?? array(), $rel[ $k ] ?? array() );
			}
			kits_sourcing_log( 'keywords', implode( ' · ', array_filter( $what ) ) );
		}
		$ref  = wp_get_referer();
		$back = remove_query_arg( array( 'updated', 'kw_done', 'kw_n', 'paged' ), $ref ? $ref : admin_url( 'options-general.php?page=aw-sourcing&tab=keywords' ) );
		$page = absint( wp_unslash( $_POST['paged'] ?? 0 ) ); // the page-number box of the list
		$back = $page > 1 ? add_query_arg( 'paged', $page, $back ) : $back;
		parse_str( (string) wp_parse_url( (string) $ref, PHP_URL_QUERY ), $was );
		if ( ! $n && ! $adding && '' === $op && $page && absint( $was['paged'] ?? 1 ) !== $page ) { // the page box alone: navigation
			wp_safe_redirect( $back );
			exit;
		}
		wp_safe_redirect( add_query_arg( array( 'kw_done' => $n ? ( $adding ? 'add' : ( 'remove' === $op ? 'remove' : 'move' ) ) : 'none', 'kw_n' => $n ), $back ) );
		exit;
	}
);
add_filter( 'removable_query_args', fn( $args ) => 'aw-sourcing' === sanitize_key( $_GET['page'] ?? '' ) ? array_merge( $args, array( 'kw_done', 'kw_n' ) ) : $args ); // phpcs:ignore WordPress.Security.NonceVerification -- only on this screen

/** One-click block from the News / Legal Watch lists. */
add_action(
	'admin_post_aw_block_publisher',
	function () {
		$host = sanitize_text_field( wp_unslash( $_GET['host'] ?? '' ) );
		if ( ! current_user_can( AW_SRC_CAP ) || ! check_admin_referer( 'aw_block_' . $host ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$pubs            = aw_publishers();
		$pubs[ $host ]   = array_merge( array( 'name' => '', 'count' => 0, 'first' => current_time( 'Y-m-d' ), 'last' => '' ), $pubs[ $host ] ?? array(), array( 'status' => 'block' ) );
		update_option( AW_PUB_OPTION, $pubs, false );
		kits_sourcing_log( 'publishers', $host . ' → block (from the item list)' );
		wp_safe_redirect( add_query_arg( 'aw_blocked', rawurlencode( $host ), wp_get_referer() ? wp_get_referer() : admin_url() ) );
		exit;
	}
);

// Publisher column on the sourced types' admin lists (registered once the site's filters are in place).
add_action(
	'admin_init',
	function () {
		foreach ( kits_sourced_types() as $aw_pt ) {
			add_filter(
				"manage_{$aw_pt}_posts_columns",
				function ( $cols ) {
					$cols['aw_publisher'] = 'Publisher';
					return $cols;
				}
			);
			add_action(
				"manage_{$aw_pt}_posts_custom_column",
				function ( $col, $id ) {
					if ( 'aw_publisher' !== $col ) {
						return;
					}
					$host = aw_host( get_post_meta( $id, 'aw_source_url', true ) );
					if ( ! $host ) {
						return;
					}
					echo esc_html( $host ) . '<br>' . aw_publisher_status_html( (string) ( aw_publishers()[ $host ]['status'] ?? 'neutral' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper
				},
				10,
				2
			);
		}
	}
);

add_filter(
	'post_row_actions',
	function ( $actions, $post ) {
		if ( ! in_array( $post->post_type, kits_sourced_types(), true ) || ! current_user_can( AW_SRC_CAP ) ) {
			return $actions;
		}
		$host = aw_host( get_post_meta( $post->ID, 'aw_source_url', true ) );
		if ( $host && 'block' !== ( aw_publishers()[ $host ]['status'] ?? '' ) ) {
			$url             = wp_nonce_url( add_query_arg( array( 'action' => 'aw_block_publisher', 'host' => $host ), admin_url( 'admin-post.php' ) ), 'aw_block_' . $host );
			$actions['spam'] = '<a href="' . esc_url( $url ) . '">Block ' . esc_html( $host ) . '</a>'; // core colours the "spam" row action red (as for comments); posts have no spam action
		}
		return $actions;
	},
	10,
	2
);

add_action(
	'admin_notices',
	function () {
		if ( ! empty( $_GET['aw_blocked'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>Blocked ' . esc_html( sanitize_text_field( wp_unslash( $_GET['aw_blocked'] ) ) ) . '. New items from it will be refused. Items already on the site are unchanged. <a href="' . esc_url( admin_url( 'options-general.php?page=aw-sourcing&tab=sources&status=block' ) ) . '">Manage sources</a></p></div>'; // phpcs:ignore WordPress.Security.NonceVerification
		}
	}
);

/** Admin page. */
function aw_sourcing_page() {
	if ( ! current_user_can( AW_SRC_CAP ) ) {
		return;
	}
	$tab  = sanitize_key( $_GET['tab'] ?? 'sources' ); // phpcs:ignore WordPress.Security.NonceVerification
	$base = admin_url( 'options-general.php?page=aw-sourcing' );
	// Tabs: Sources, Keywords, the site's own (kits_sourcing_tabs: slug => [label, render callback, own form?]), Change log.
	// A tab whose third value is true is drawn outside the shared aw_sourcing form (a Settings API form, its own forms, or nothing to save).
	$extra = (array) apply_filters( 'kits_sourcing_tabs', array() );
	$tabs  = array( 'sources' => 'Sources', 'keywords' => 'Keywords' ) + array_map( fn( $t ) => $t[0], $extra ) + array( 'log' => 'Change log' );
	$tab   = isset( $tabs[ $tab ] ) ? $tab : 'sources';
	// Core's "Add New" button beside the heading, on the lists that have one (kits_sourcing_title_action: tab => [label, url]).
	$add = (array) apply_filters( 'kits_sourcing_title_action', array( 'sources' => array( 'Add New Source', $base . '&tab=sources&edit=new' ) ) );
	echo '<div class="wrap"><h1 class="wp-heading-inline">Sourcing</h1>';
	if ( isset( $add[ $tab ] ) && empty( $_GET['edit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo ' <a href="' . esc_url( $add[ $tab ][1] ) . '" class="page-title-action">' . esc_html( $add[ $tab ][0] ) . '</a>';
	}
	echo '<hr class="wp-header-end">';
	if ( ! empty( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success is-dismissible"><p>Saved. The workers use this from their next run.</p></div>';
	}
	do_action( 'kits_sourcing_page_top' );
	echo '<nav class="nav-tab-wrapper">';
	foreach ( $tabs as $k => $l ) {
		echo '<a class="nav-tab' . ( $tab === $k ? ' nav-tab-active' : '' ) . '" href="' . esc_url( $base . ( 'sources' === $k ? '' : '&tab=' . $k ) ) . '">' . esc_html( $l ) . '</a>';
	}
	echo '</nav>';
	if ( 'log' === $tab ) {
		// Entries keep the tab they were made on; tabs since merged are named after their new home (Feeds and Relay
		// moved into Sources on 25 Oct 2026).
		$old  = array( 'publishers' => 'Sources', 'feeds' => 'Sources (feeds)', 'library' => 'Sources (Library feeds)', 'opportunities' => 'Sources (Opportunity feeds)', 'relay' => 'Sources (relay)' );
		$rows = array();
		foreach ( (array) get_option( 'kits_sourcing_log', array() ) as $e ) {
			$tl     = (string) ( $old[ $e['tab'] ] ?? ( $tabs[ $e['tab'] ] ?? $e['tab'] ) );
			$has    = array_key_exists( 'old', (array) $e ) || array_key_exists( 'new', (array) $e );
			$change = $has ? aw_src_log_value( $e['old'] ?? '' ) . ' → ' . aw_src_log_value( $e['new'] ?? '' ) : '';
			$rows[] = array(
				'at'     => esc_html( $e['at'] ),
				'user'   => esc_html( $e['user'] ),
				'tab'    => esc_html( $tl ),
				'what'   => esc_html( $e['what'] ),
				'change' => $has ? esc_html( $change ) : '—',
				'_find'  => $e['user'] . ' ' . $tl . ' ' . $e['what'] . ' ' . $change,
			);
		}
		aw_sourcing_simple_table(
			'changes',
			array( 'at' => 'When', 'user' => 'Who', 'tab' => 'Tab', 'what' => 'What changed', 'change' => 'Old → new' ),
			$rows,
			array( 'per_page' => 50, 'sortable' => array( 'at', 'user', 'tab' ), 'empty' => 'No changes yet.', 'search' => 'Search changes', 'hidden' => array( 'page' => 'aw-sourcing', 'tab' => 'log' ) )
		);
		echo '</div>';
		return;
	}
	if ( 'sources' === $tab ) { // its own forms: the search (GET), the list or the edit form (source-sites.php)
		aw_sourcing_sources_tab();
		echo '</div>';
		return;
	}
	if ( 'keywords' === $tab ) { // its own forms: the switches, add words, the word list
		aw_sourcing_keywords_tab();
		echo '</div>';
		return;
	}
	if ( ! empty( $extra[ $tab ][2] ) ) { // a tab with its own forms (a Settings API form to options.php, or a list)
		call_user_func( $extra[ $tab ][1] );
		echo '</div>';
		return;
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_sourcing"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';
	wp_nonce_field( 'aw_sourcing' );
	if ( isset( $extra[ $tab ] ) ) {
		call_user_func( $extra[ $tab ][1] );
	}
	echo '</form></div>';
}

/**
 * The Keywords tab, two views so each list table has its own paging, sorting, search and screen option: Words (the two
 * switches, add words, the word lists, the headline test) and Suggestions from Search Insights (&kw=suggestions).
 */
function aw_sourcing_keywords_tab() {
	$base = admin_url( 'options-general.php?page=aw-sourcing&tab=keywords' );
	$sugg = 'suggestions' === sanitize_key( $_GET['kw'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	echo '<ul class="subsubsub"><li><a href="' . esc_url( $base ) . '"' . ( $sugg ? '' : ' class="current" aria-current="page"' ) . '>Words</a> | </li>'
		. '<li><a href="' . esc_url( $base . '&kw=suggestions' ) . '"' . ( $sugg ? ' class="current" aria-current="page"' : '' ) . '>Suggestions from Search Insights</a></li></ul><br class="clear">';
	if ( $sugg ) {
		if ( ! has_action( 'aw_sourcing_keywords_tab_end' ) || ( function_exists( 'aw_sourcing_si_available' ) && ! aw_sourcing_si_available() ) ) {
			echo '<div class="notice notice-info inline"><p>Suggestions need Search Insights (aw-seo-extras), which is off.</p></div>';
		}
		do_action( 'aw_sourcing_keywords_tab_end' ); // suggestions from Search Insights (keyword-suggestions.php)
		return;
	}
	$post = admin_url( 'admin-post.php' );
	$done = sanitize_key( $_GET['kw_done'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( 'none' === $done ) {
		echo '<div class="notice notice-warning is-dismissible"><p>Nothing changed: choose an action and at least one word (a word already in a list is not added to it again).</p></div>';
	} elseif ( $done ) {
		$n   = absint( wp_unslash( $_GET['kw_n'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$msg = array(
			/* translators: %d: number of words. */
			'add'    => _n( '%d word added.', '%d words added.', $n ),
			/* translators: %d: number of words. */
			'remove' => _n( '%d word removed.', '%d words removed.', $n ),
			/* translators: %d: number of words. */
			'move'   => _n( '%d word moved.', '%d words moved.', $n ),
		);
		if ( isset( $msg[ $done ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( $msg[ $done ], $n ) . ' The workers use the lists from their next run.' ) . '</p></div>';
		}
	}
	echo '<p>An item from an <strong>allowed</strong> publisher goes live when it has an <strong>include</strong> word (or an animal word with a context word) and no <strong>exclude</strong> word; otherwise it waits in review. Blocked publishers are refused. Hindi works.</p>';

	aw_sourcing_settings_form( 'aw_sourcing_switches', 'aw-sourcing-switches' );

	if ( ! class_exists( 'WP_List_Table' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	}
	require_once __DIR__ . '/class-aw-sourcing-keywords-table.php';
	$table = new AW_Sourcing_Keywords_Table();
	$table->prepare_items();

	// As on Posts → Tags: the search above, the add form on the left, the list on the right.
	$list = sanitize_key( $_GET['list'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	echo '<form class="search-form wp-clearfix" method="get"><input type="hidden" name="page" value="aw-sourcing"><input type="hidden" name="tab" value="keywords">' . ( isset( AW_Sourcing_Keywords_Table::LISTS[ $list ] ) ? '<input type="hidden" name="list" value="' . esc_attr( $list ) . '">' : '' );
	$table->search_box( 'Search words', 'aw-kw' );
	echo '</form>';

	echo '<div id="col-container" class="wp-clearfix"><div id="col-left"><div class="col-wrap"><div class="form-wrap">';
	echo '<h2>Add words</h2><form method="post" action="' . esc_url( $post ) . '"><input type="hidden" name="action" value="aw_sourcing_keywords"><input type="hidden" name="kw_add" value="1">';
	wp_nonce_field( 'aw_sourcing_keywords_add' );
	echo '<div class="form-field form-required"><label for="aw-kw-new">Words</label><textarea id="aw-kw-new" name="kw_new" rows="5" cols="40" aria-describedby="aw-kw-new-description"></textarea><p id="aw-kw-new-description">One per line. Hindi works. A word already in the list is not added again.</p></div>';
	echo '<div class="form-field"><label for="aw-kw-list">Add to</label><select id="aw-kw-list" name="kw_list" aria-describedby="aw-kw-list-description">';
	foreach ( array( 'strong' => 'Include (one is enough)', 'animal' => 'Animal words', 'context' => 'Context words (an animal word with one of these also counts)', 'exclude' => 'Exclude (always drop)' ) as $k => $l ) {
		echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
	}
	echo '</select><p id="aw-kw-list-description">Include: keeps an item on its own (an English one is also a Google News search). Animal and Context: keep an item together. Exclude: always drops it.</p></div>';
	submit_button( 'Add words', 'primary' );
	echo '</form></div></div></div>';

	echo '<div id="col-right"><div class="col-wrap"><h2 class="screen-reader-text">Words</h2>';
	$table->views();
	echo '<form method="post" action="' . esc_url( $post ) . '"><input type="hidden" name="action" value="aw_sourcing_keywords">';
	$table->display();
	echo '</form></div></div></div>';

	$try = sanitize_text_field( wp_unslash( $_GET['try'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	echo '<h2>Test a headline</h2><form method="get"><input type="hidden" name="page" value="aw-sourcing"><input type="hidden" name="tab" value="keywords"><input type="text" name="try" class="large-text" value="' . esc_attr( $try ) . '" placeholder="Paste a headline" aria-label="Headline">';
	submit_button( 'Test', 'secondary', '', false );
	if ( $try ) {
		$v = aw_relevance_verdict( $try );
		echo '<p><strong>' . ( $v[0] ? 'Kept' : 'Dropped' ) . '</strong> (' . esc_html( $v[1] ) . ')</p>';
	}
	echo '</form>';
}

/**
 * Show plain rows as a core list table.
 *
 * @param string $plural Table name.
 * @param array  $cols   Column => label.
 * @param array  $rows   Rows (cells as escaped HTML).
 * @param array  $opts   AW_Sourcing_Simple_Table options; search: the search button's text; hidden: query args the search keeps.
 */
function aw_sourcing_simple_table( $plural, array $cols, array $rows, array $opts = array() ) {
	if ( ! class_exists( 'WP_List_Table' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	}
	require_once __DIR__ . '/class-aw-sourcing-simple-table.php';
	$t = new AW_Sourcing_Simple_Table( $plural, $cols, $rows, $opts );
	$t->prepare_items();
	if ( ! empty( $opts['search'] ) ) { // a GET form with the screen's own query args (opts hidden)
		echo '<form method="get">';
		foreach ( (array) ( $opts['hidden'] ?? array() ) as $k => $v ) {
			echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
		}
		$t->search_box( (string) $opts['search'], 'aw-' . sanitize_key( $plural ) );
		echo '</form>';
	}
	$t->display();
}

/**
 * The plugins list's problem row (core notice classes) under a list-table row: a blocked publisher, a source that is
 * off, a rejected item from an allowed publisher. As on the plugins list for a plugin with a problem.
 *
 * @param WP_List_Table $table The table (for the column count).
 * @param string        $msg   Plain text.
 * @param string        $tone  error, warning or info.
 */
function aw_sourcing_problem_row( $table, $msg, $tone = 'warning' ) {
	echo '<tr class="plugin-update-tr"><td colspan="' . (int) $table->get_column_count() . '" class="plugin-update colspanchange"><div class="update-message notice inline notice-' . esc_attr( $tone ) . ' notice-alt"><p>' . esc_html( $msg ) . '</p></div></td></tr>';
}

/* ---------- Settings API (owner, 24 Oct 2026): the Sourcing settings forms post to options.php ---------- */

/**
 * A Settings API form: the group's hidden fields and nonce, its sections and fields, Save.
 *
 * @param string $group  Option group (register_setting).
 * @param string $page   Settings page slug the sections are added to.
 * @param string $button Button text.
 */
function aw_sourcing_settings_form( $group, $page, $button = 'Save' ) {
	echo '<form method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '">';
	settings_fields( $group );
	do_settings_sections( $page );
	submit_button( $button );
	echo '</form>';
}

/**
 * Was this value posted by one of the Sourcing forms? Each form sends a hidden _aw_tab with its option. A sanitize
 * callback runs on every update_option() of its option, also the site's own saves (list tables, suggestions, the
 * workers' seed), so those pass through unchanged.
 *
 * @param mixed  $value Value being saved.
 * @param string $tab   Tab that posts it ('' for any).
 * @return string The tab, or ''.
 */
function aw_sourcing_settings_tab( $value, $tab = '' ) {
	$t = is_array( $value ) ? sanitize_key( $value['_aw_tab'] ?? '' ) : '';
	return ( '' === $tab || $t === $tab ) ? $t : '';
}

/** The marker field of a Sourcing form (see aw_sourcing_settings_tab). */
function aw_sourcing_settings_marker( $option, $tab ) {
	echo '<input type="hidden" name="' . esc_attr( $option ) . '[_aw_tab]" value="' . esc_attr( $tab ) . '">';
}

/** After a Sourcing form saved: one notice in place of core's "Settings saved." */
function aw_sourcing_settings_saved() {
	add_settings_error( 'aw_sourcing', 'saved', 'Saved. The workers use this from their next run.', 'success' );
}

/** A textarea list (one per line) as a clean array. */
function aw_sourcing_lines( $text ) {
	return array_values( array_unique( array_filter( array_map( 'trim', explode( "\n", sanitize_textarea_field( (string) $text ) ) ) ) ) );
}

/** A field's textarea (Settings API callback): args name, value (list or text), rows, help, class. */
function aw_sourcing_textarea_field( $args ) {
	$v = is_array( $args['value'] ) ? implode( "\n", $args['value'] ) : (string) $args['value'];
	echo '<textarea id="' . esc_attr( $args['label_for'] ) . '" name="' . esc_attr( $args['name'] ) . '" rows="' . (int) ( $args['rows'] ?? 6 ) . '" class="' . esc_attr( $args['class_attr'] ?? 'large-text code' ) . '">' . esc_textarea( $v ) . '</textarea>';
	if ( ! empty( $args['help'] ) ) {
		echo '<p class="description">' . esc_html( $args['help'] ) . '</p>';
	}
}

// Options.php checks the group's capability: the Sourcing screen's own.
foreach ( array( 'aw_sourcing_switches', 'aw_sourcing_sources', 'aw_sourcing_summary', 'aw_sourcing_enrich' ) as $aw_group ) {
	add_filter( 'option_page_capability_' . $aw_group, fn() => AW_SRC_CAP );
}

// Keywords tab: the two switches (option aw_relevance; the word lists save from their list table).
add_action(
	'admin_init',
	function () {
		register_setting( 'aw_sourcing_switches', AW_REL_OPTION, array( 'type' => 'array', 'sanitize_callback' => 'aw_relevance_switches_sanitize', 'show_in_rest' => false ) );
		add_settings_section(
			'aw_switches',
			'',
			fn() => aw_sourcing_settings_marker( AW_REL_OPTION, 'keywords' ),
			'aw-sourcing-switches'
		);
		add_settings_field(
			'aw_rel_enabled',
			'Relevance filter',
			function () {
				$r = aw_relevance();
				echo '<label><input type="checkbox" id="aw-rel-enabled" name="' . esc_attr( AW_REL_OPTION ) . '[enabled]" value="1"' . checked( ! empty( $r['enabled'] ), true, false ) . '> On</label><p class="description">Off: every item from a non-blocked publisher is kept.</p>';
			},
			'aw-sourcing-switches',
			'aw_switches'
		);
		add_settings_field(
			'aw_rel_gnews',
			'Aggregator feeds',
			function () {
				$r = aw_relevance();
				echo '<label><input type="checkbox" id="aw-rel-gnews" name="' . esc_attr( AW_REL_OPTION ) . '[gnews_allow_only]" value="1"' . checked( ! empty( $r['gnews_allow_only'] ), true, false ) . '> Only allowed publishers</label><p class="description">Google News, Apple News and similar: items from Neutral publishers are refused. Keep off unless the allow list is complete.</p>';
			},
			'aw-sourcing-switches',
			'aw_switches'
		);
	}
);

/** The two switches from the Keywords tab; any other save of aw_relevance passes through. */
function aw_relevance_switches_sanitize( $value ) {
	if ( ! aw_sourcing_settings_tab( $value, 'keywords' ) ) {
		return $value;
	}
	$rel                     = aw_relevance();
	$old                     = $rel;
	$rel['enabled']          = ! empty( $value['enabled'] );
	$rel['gnews_allow_only'] = ! empty( $value['gnews_allow_only'] );
	$what                    = array();
	if ( ! empty( $old['enabled'] ) !== $rel['enabled'] ) {
		$what[] = 'keyword check ' . ( $rel['enabled'] ? 'on' : 'off' );
	}
	if ( ! empty( $old['gnews_allow_only'] ) !== $rel['gnews_allow_only'] ) {
		$what[] = 'aggregators allowed-only ' . ( $rel['gnews_allow_only'] ? 'on' : 'off' );
	}
	aw_sourcing_settings_saved();
	if ( ! $what ) {
		return get_option( AW_REL_OPTION ); // Save with no change writes nothing
	}
	kits_sourcing_log( 'keywords', implode( ' · ', $what ) );
	return $rel;
}
