<?php
/**
 * Sourcing policy managed in WP Admin (News → Sourcing): publishers and relevance keywords.
 *
 * - Publishers: every host the workers send is recorded automatically. Each is Allowed, Neutral or Blocked.
 *   Blocked hosts are refused by the ingest endpoint. With "Only allowed publishers from aggregator feeds" on
 *   (off by default), aggregator items (Google News, Apple News...) from hosts that are not Allowed are refused too.
 * - Keywords: strong / animal / context / exclude lists. When the relevance filter is on, an item is kept if
 *   it has a strong term, or an animal term and a context term, and no exclude term.
 * - The workers read the same policy from GET /wp-json/aw/v1/sourcing-policy before each run, so blocked or
 *   irrelevant items are skipped before they are summarised; the endpoint enforces it again on arrival.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Post types fed by sources (news-like). Sites set them with the kits_sourced_post_types filter. */
function kits_sourced_types() {
	return (array) apply_filters( 'kits_sourced_post_types', array( 'post' ) );
}

/** The post type whose admin menu holds the Sourcing screen (the first sourced type). */
function kits_sourcing_menu_type() {
	$t = kits_sourced_types();
	return (string) reset( $t );
}

const AW_PUB_OPTION = 'aw_publishers';
const AW_REL_OPTION = 'aw_relevance';
const AW_SRC_CAP    = 'manage_options';

/** Normalised host of a URL (lowercase, no www.). */
function aw_host( $url ) {
	$h = strtolower( (string) wp_parse_url( (string) $url, PHP_URL_HOST ) );
	return preg_replace( '/^www\./', '', $h );
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

/** Publisher records: host => [status, name, count, first, last]. */
function aw_publishers() {
	$p = get_option( AW_PUB_OPTION );
	return is_array( $p ) ? $p : array();
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

/**
 * Change log of the Sourcing screen: who changed what, when (newest first, last 300 kept).
 *
 * @param string $tab  Tab.
 * @param string $what What changed, in words.
 */
function kits_sourcing_log( $tab, $what ) {
	if ( '' === trim( $what ) ) {
		return;
	}
	$log = (array) get_option( 'kits_sourcing_log', array() );
	array_unshift( $log, array( 'at' => current_time( 'mysql' ), 'user' => wp_get_current_user()->display_name, 'tab' => $tab, 'what' => $what ) );
	update_option( 'kits_sourcing_log', array_slice( $log, 0, 300 ), false );
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

/** Save handlers. */
add_action(
	'admin_post_aw_sourcing',
	function () {
		if ( ! current_user_can( AW_SRC_CAP ) || ! check_admin_referer( 'aw_sourcing' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$tab = sanitize_key( $_POST['tab'] ?? 'publishers' );
		if ( ! in_array( $tab, array( 'publishers', 'keywords' ), true ) ) {
			do_action( 'kits_sourcing_save_' . $tab ); // a site's own tabs save themselves (and log)
		} elseif ( 'keywords' === $tab ) {
			$rel  = aw_relevance();
			$old  = $rel;
			$list = fn( $k ) => array_values( array_unique( array_filter( array_map( 'trim', explode( "\n", sanitize_textarea_field( wp_unslash( $_POST[ $k ] ?? '' ) ) ) ) ) ) );
			$rel['enabled']          = ! empty( $_POST['enabled'] );
			$rel['gnews_allow_only'] = ! empty( $_POST['gnews_allow_only'] );
			foreach ( array( 'strong', 'animal', 'context', 'exclude' ) as $k ) {
				$rel[ $k ] = $list( $k );
			}
			update_option( AW_REL_OPTION, $rel, false );
			$what = array();
			foreach ( array( 'strong' => 'Include', 'animal' => 'Animal words', 'context' => 'Context words', 'exclude' => 'Exclude' ) as $k => $l ) {
				$what[] = kits_sourcing_diff( $l, $old[ $k ] ?? array(), $rel[ $k ] );
			}
			if ( ! empty( $old['enabled'] ) !== $rel['enabled'] ) {
				$what[] = 'keyword check ' . ( $rel['enabled'] ? 'on' : 'off' );
			}
			if ( ! empty( $old['gnews_allow_only'] ) !== $rel['gnews_allow_only'] ) {
				$what[] = 'aggregators allowed-only ' . ( $rel['gnews_allow_only'] ? 'on' : 'off' );
			}
			kits_sourcing_log( 'keywords', implode( ' · ', array_filter( $what ) ) );
		} else {
			$pubs   = aw_publishers();
			$status = sanitize_key( $_POST['bulk_status'] ?? '' );
			$hosts  = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['hosts'] ?? array() ) );
			if ( in_array( $status, array( 'allow', 'neutral', 'block' ), true ) ) {
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
			update_option( AW_PUB_OPTION, $pubs, false );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'aw-sourcing', 'tab' => $tab, 'updated' => 1 ), admin_url( 'options-general.php' ) ) );
		exit;
	}
);

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
					$st = aw_publishers()[ $host ]['status'] ?? 'neutral';
					echo esc_html( $host ) . ' <span class="aw-pub-st aw-pub-st--' . esc_attr( $st ) . '">' . esc_html( ucfirst( $st ) ) . '</span>';
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
			$url                     = wp_nonce_url( add_query_arg( array( 'action' => 'aw_block_publisher', 'host' => $host ), admin_url( 'admin-post.php' ) ), 'aw_block_' . $host );
			$actions['aw_block_pub'] = '<a href="' . esc_url( $url ) . '" style="color:#b32d2e">Block ' . esc_html( $host ) . '</a>';
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
			echo '<div class="notice notice-success is-dismissible"><p>Blocked ' . esc_html( sanitize_text_field( wp_unslash( $_GET['aw_blocked'] ) ) ) . '. New items from it will be refused. Items already on the site are unchanged. <a href="' . esc_url( admin_url( 'options-general.php?page=aw-sourcing' ) ) . '">Manage publishers</a></p></div>'; // phpcs:ignore WordPress.Security.NonceVerification
		}
	}
);

add_action(
	'admin_head',
	function () {
		echo '<style>.aw-pub-st{display:inline-block;padding:1px 6px;border-radius:3px;font-size:11px;font-weight:600;background:#f0f0f1}.aw-pub-st--block{background:#fcf0f1;color:#b32d2e}.aw-pub-st--allow{background:#edfaef;color:#00701a}.aw-src-lists{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px}.aw-src-lists textarea{width:100%;min-height:220px;font-family:monospace}</style>';
	}
);

/** Admin page. */
function aw_sourcing_page() {
	if ( ! current_user_can( AW_SRC_CAP ) ) {
		return;
	}
	$tab  = sanitize_key( $_GET['tab'] ?? 'publishers' ); // phpcs:ignore WordPress.Security.NonceVerification
	$base = admin_url( 'options-general.php?page=aw-sourcing' );
	echo '<div class="wrap"><h1>Sourcing</h1>';
	if ( ! empty( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success is-dismissible"><p>Saved. The workers use this from their next run.</p></div>';
	}
	// Tabs: Publishers, Keywords, the site's own (kits_sourcing_tabs: slug => [label, render callback]), Change log.
	$extra = (array) apply_filters( 'kits_sourcing_tabs', array() );
	$tabs  = array( 'publishers' => 'Publishers', 'keywords' => 'Keywords' ) + array_map( fn( $t ) => $t[0], $extra ) + array( 'log' => 'Change log' );
	$tab   = isset( $tabs[ $tab ] ) ? $tab : 'publishers';
	do_action( 'kits_sourcing_page_top' );
	echo '<nav class="nav-tab-wrapper">';
	foreach ( $tabs as $k => $l ) {
		echo '<a class="nav-tab' . ( $tab === $k ? ' nav-tab-active' : '' ) . '" href="' . esc_url( $base . ( 'publishers' === $k ? '' : '&tab=' . $k ) ) . '">' . esc_html( $l ) . '</a>';
	}
	echo '</nav>';
	if ( 'log' === $tab ) {
		echo '<table class="widefat striped" style="margin-top:12px"><thead><tr><th>When</th><th>Who</th><th>Tab</th><th>What changed</th></tr></thead><tbody>';
		foreach ( (array) get_option( 'kits_sourcing_log', array() ) as $e ) {
			echo '<tr><td>' . esc_html( $e['at'] ) . '</td><td>' . esc_html( $e['user'] ) . '</td><td>' . esc_html( $tabs[ $e['tab'] ] ?? $e['tab'] ) . '</td><td>' . esc_html( $e['what'] ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
		return;
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_sourcing"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';
	wp_nonce_field( 'aw_sourcing' );
	if ( isset( $extra[ $tab ] ) ) {
		call_user_func( $extra[ $tab ][1] );
		echo '</form></div>';
		return;
	}

	if ( 'keywords' === $tab ) {
		$r = aw_relevance();
		echo '<p>An item from an <strong>allowed</strong> publisher goes live when it has an <strong>include</strong> word (or an animal word with a context word) and no <strong>exclude</strong> word; otherwise it waits in review. Blocked publishers are refused. One term per line; Hindi works.</p>';
		echo '<p><label><input type="checkbox" name="enabled" value="1"' . checked( ! empty( $r['enabled'] ), true, false ) . '> <strong>Relevance filter on</strong> (off: every item from a non-blocked publisher is kept)</label></p>';
		echo '<p><label><input type="checkbox" name="gnews_allow_only" value="1"' . checked( ! empty( $r['gnews_allow_only'] ), true, false ) . '> <strong>Only allowed publishers from aggregator feeds</strong> (Google News, Apple News and similar: items from Neutral publishers are refused). Keep off unless the allow list is complete.</label></p>';
		echo '<div class="aw-src-lists">';
		foreach ( array( 'strong' => 'Include (one is enough)', 'animal' => 'Animal words', 'context' => 'Context words (an animal word with one of these also counts)', 'exclude' => 'Exclude (always drop)' ) as $k => $label ) {
			echo '<p><label for="aw-' . esc_attr( $k ) . '"><strong>' . esc_html( $label ) . '</strong></label><br><textarea id="aw-' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '">' . esc_textarea( implode( "\n", (array) $r[ $k ] ) ) . '</textarea></p>';
		}
		echo '</div>';
		$try = sanitize_text_field( wp_unslash( $_GET['try'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		submit_button( 'Save keywords' );
		echo '</form><h2>Test a headline</h2><form method="get"><input type="hidden" name="page" value="aw-sourcing"><input type="hidden" name="tab" value="keywords"><input type="text" name="try" class="large-text" value="' . esc_attr( $try ) . '" placeholder="Paste a headline">';
		submit_button( 'Test', 'secondary', '', false );
		if ( $try ) {
			$v = aw_relevance_verdict( $try );
			echo '<p><strong>' . ( $v[0] ? 'Kept' : 'Dropped' ) . '</strong> (' . esc_html( $v[1] ) . ')</p>';
		}
		echo '</form></div>';
		return;
	}

	$pubs   = aw_publishers();
	$filter = sanitize_key( $_GET['status'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	uasort( $pubs, fn( $a, $b ) => ( $b['count'] <=> $a['count'] ) ?: strcmp( (string) $b['last'], (string) $a['last'] ) );
	$counts = array_count_values( array_map( fn( $p ) => $p['status'], $pubs ) );
	// Add first, as everywhere in WordPress.
	echo '<p class="aw-src-add"><label for="aw-new-host"><strong>Add a publisher</strong></label> <input type="text" id="aw-new-host" name="new_host" placeholder="example.com" class="regular-text"> <select name="new_status" aria-label="Status"><option value="block">Blocked</option><option value="allow">Allowed</option><option value="neutral">Neutral</option></select> ';
	submit_button( 'Add', 'secondary', '', false );
	echo '</p>';
	echo '<ul class="subsubsub">';
	foreach ( array( '' => 'All', 'neutral' => 'Neutral (new finds)', 'allow' => 'Allowed', 'block' => 'Blocked' ) as $k => $l ) {
		$n = '' === $k ? count( $pubs ) : (int) ( $counts[ $k ] ?? 0 );
		echo '<li><a href="' . esc_url( $base . ( $k ? '&status=' . $k : '' ) ) . '"' . ( $filter === $k ? ' class="current"' : '' ) . '>' . esc_html( $l ) . ' <span class="count">(' . (int) $n . ')</span></a> | </li>';
	}
	echo '</ul><br class="clear">';
	echo '<p>Blocked publishers are refused when the workers send their items. Neutral is the default for new hosts the workers find. Allowed publishers skip the keyword check.</p>';
	echo '<div class="tablenav top"><select name="bulk_status"><option value="">Change status to…</option><option value="block">Blocked</option><option value="neutral">Neutral</option><option value="allow">Allowed</option></select> ';
	submit_button( 'Apply', 'action', '', false );
	echo '</div><table class="widefat striped"><thead><tr><td class="check-column"><input type="checkbox" onclick="document.querySelectorAll(\'.aw-h\').forEach(c=>c.checked=this.checked)" aria-label="Select all"></td><th>Host</th><th>Name</th><th>Status</th><th>Rating</th><th>Items</th><th>First seen</th><th>Last seen</th></tr></thead><tbody>';
	foreach ( $pubs as $h => $p ) {
		if ( $filter && $filter !== $p['status'] ) {
			continue;
		}
		echo '<tr><th class="check-column"><input class="aw-h" type="checkbox" name="hosts[]" value="' . esc_attr( $h ) . '" aria-label="' . esc_attr( 'Select ' . $h ) . '"></th><td><strong>' . esc_html( $h ) . '</strong></td><td>' . esc_html( $p['name'] ) . '</td><td><span class="aw-pub-st aw-pub-st--' . esc_attr( $p['status'] ) . '">' . esc_html( ucfirst( $p['status'] ) ) . '</span></td><td>' . aw_publisher_rating_html( $p ) . '</td><td>' . (int) $p['count'] . '</td><td>' . esc_html( $p['first'] ) . '</td><td>' . esc_html( $p['last'] ) . '</td></tr>';
	}
	echo '</tbody></table></form></div>';
}
