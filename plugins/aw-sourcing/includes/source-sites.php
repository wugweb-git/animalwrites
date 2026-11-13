<?php
/**
 * Sourcing → Sources (owner, 24 Oct 2026): one row per host, publishers and press sites together. The screen is the
 * Kits shell's (sourceweb-ui.php, sourceweb/v1 routes); this file has its rows, saves, help tabs, the figures it
 * shows and how they are gathered:
 *
 * - Items: fetched in 30 days and published (all time), from one query over the sourced types' aw_source_url meta,
 *   kept for an hour in a transient.
 * - Search value: Search Console impressions and clicks over 28 days and 3 months (90 days) of the host's published
 *   items' pages (aw_si_page_metrics, from aw-seo-extras), in the same transient; none when Search Console has not
 *   run within 28 days or Search Insights is off (the rule of the Sourcing keywords table).
 * - SEO: each site's own home page title and description, read in the background only (WP-Cron, hourly, 15 hosts a
 *   run, never on page load) into the option aw_source_site_meta (autoload off).
 * - Domain Rating: publisher record keys dr and dr_at, set by `wp aw-sourcing import-dr <file.json>` (values from
 *   Ahrefs' free Domain Rating lookup, run by Claude).
 *
 * Status changes, short names and press sites save through the shared functions (aw_sourcing_sources_apply(),
 * aw_sourcing_source_save()), logged in the Change log as before (tab publishers; press-site changes as tab press).
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Option: host => [title, desc, code, at] from the background home-page check. */
const AW_SOURCING_SITE_META = 'aw_source_site_meta';

/** Cron hook of the home-page check. */
const AW_SOURCING_SITE_META_CRON = 'aw_sourcing_site_meta';

/**
 * The sourcing restructure phase migrated on this site (option aw_sourcing_phase, set by `wp aw-sourcing migrate`,
 * put back by `wp aw-sourcing rollback`): 0 before phase 1. Phase 1 makes the relay switches the allow list and adds
 * sources_v2 to the policy.
 */
function aw_sourcing_phase() {
	return (int) get_option( 'aw_sourcing_phase', 0 );
}

/**
 * Every source (feeds and sources read by code) by its publisher host, in the lists' order.
 *
 * @return array<string, array[]>
 */
function aw_sourcing_feeds_by_host() {
	$out = array();
	foreach ( (array) ( aw_sources_config()['sources'] ?? array() ) as $s ) {
		if ( is_array( $s ) ) {
			$out[ aw_src_source_host( $s ) ][] = $s;
		}
	}
	return $out;
}

/** A host typed or posted (with or without https://, www.) as aw_host() keeps it, or ''. */
function aw_sourcing_norm_host( $in ) {
	$in = trim( (string) $in );
	if ( '' === $in ) {
		return '';
	}
	$h = aw_host( 'https://' . preg_replace( '#^[a-z]+://#i', '', $in ) );
	return preg_match( '/^[a-z0-9.-]+\.[a-z0-9-]+$/', $h ) ? $h : '';
}

/** The press-coverage sites (aw_sources press sites) as hosts, once each. */
function aw_sourcing_press_hosts() {
	$out = array();
	foreach ( (array) ( aw_sources_config()['press']['sites'] ?? array() ) as $s ) {
		$h = aw_sourcing_norm_host( $s );
		if ( '' !== $h ) {
			$out[ $h ] = true;
		}
	}
	return array_keys( $out );
}

/**
 * Add hosts to or remove hosts from the press sites, logged as the Press queries tab logs them.
 *
 * @param string[] $add    Hosts to search for coverage.
 * @param string[] $remove Hosts to stop searching.
 * @return bool Something changed.
 */
function aw_sourcing_press_change( array $add, array $remove ) {
	if ( aw_sourcing_phase() >= 2 ) { // press sites were dropped with Brave in phase 2 (owner's decision 7)
		return false;
	}
	$c     = aw_sources_config();
	$old   = array_values( (array) ( $c['press']['sites'] ?? array() ) );
	$sites = array();
	foreach ( $old as $s ) { // a site removed by its host, however it was written
		if ( ! in_array( aw_sourcing_norm_host( $s ), $remove, true ) ) {
			$sites[] = $s;
		}
	}
	$have = array_map( 'aw_sourcing_norm_host', $sites );
	foreach ( $add as $h ) {
		if ( '' !== $h && ! in_array( $h, $have, true ) ) {
			$sites[] = $h;
			$have[]  = $h;
		}
	}
	if ( $sites === $old ) {
		return false;
	}
	$c['press']['sites'] = $sites;
	update_option( 'aw_sources', $c, false ); // no _aw_tab: aw_sources_sanitize() lets it through as it is
	aw_src_log( 'press', aw_src_diff( 'Sites', $old, $sites ) );
	return true;
}

/**
 * Per host: items fetched in 30 days, published (all time), and their pages' Search Console figures.
 *
 * @return array{si: bool, stale: bool, hosts: array<string, array{f30: int, pub: int, impr: int, clicks: int, impr3: int, clicks3: int}>}
 */
function aw_sourcing_host_stats() {
	$hit = get_transient( 'aw_sourcing_host_stats' );
	if ( is_array( $hit ) ) {
		return $hit;
	}
	global $wpdb;
	$types = array_values( array_filter( array_map( 'strval', kits_sourced_types() ) ) );
	$since = time() - 30 * DAY_IN_SECONDS;
	$hosts = array();
	$pub   = array(); // post ID => host
	if ( $types ) {
		// Arrival as in keyword-stats.php: aw_ingested_at, else post_date (pending items have no GMT date).
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- one %s per post type.
				"SELECT p.ID, p.post_status, u.meta_value url, ( CAST(i.meta_value AS UNSIGNED) >= %d OR ( i.meta_value IS NULL AND p.post_date >= %s ) ) recent
				FROM {$wpdb->posts} p
				JOIN {$wpdb->postmeta} u ON u.post_id = p.ID AND u.meta_key = 'aw_source_url'
				LEFT JOIN {$wpdb->postmeta} i ON i.post_id = p.ID AND i.meta_key = 'aw_ingested_at'
				WHERE p.post_type IN (" . implode( ',', array_fill( 0, count( $types ), '%s' ) ) . ") AND p.post_status NOT IN ('auto-draft', 'inherit')",
				array_merge( array( $since, wp_date( 'Y-m-d H:i:s', $since ) ), $types )
			)
		);
		foreach ( (array) $rows as $r ) {
			$h = aw_host( (string) $r->url );
			if ( '' === $h ) {
				continue;
			}
			$hosts[ $h ] = $hosts[ $h ] ?? array( 'f30' => 0, 'pub' => 0, 'impr' => 0, 'clicks' => 0, 'impr3' => 0, 'clicks3' => 0 );
			$hosts[ $h ]['f30'] += (int) $r->recent ? 1 : 0;
			if ( 'publish' === $r->post_status ) {
				++$hosts[ $h ]['pub'];
				$pub[ (int) $r->ID ] = $h;
			}
		}
	}
	// Search Console figures count only when it last ran within the 28-day window (as the Sourcing keywords table).
	$ok    = function_exists( 'aw_si_runs' ) ? (int) ( aw_si_runs()['gsc']['ok'] ?? 0 ) : 0;
	$si    = function_exists( 'aw_sourcing_si_available' ) && aw_sourcing_si_available() && function_exists( 'aw_si_page_metrics' ) && $ok > time() - 28 * DAY_IN_SECONDS;
	$stale = $ok && ! $si;
	if ( $si && $pub ) {
		_prime_post_caches( array_keys( $pub ), false, false ); // one query for every item, not one per permalink
		$paths = array();
		foreach ( $pub as $id => $h ) {
			$url          = (string) get_permalink( $id );
			$paths[ $id ] = function_exists( 'aw_si_path' ) ? aw_si_path( $url ) : (string) wp_parse_url( $url, PHP_URL_PATH );
		}
		$list = array_values( array_filter( array_unique( $paths ) ) );
		$m28  = aw_si_page_metrics( $list, 28 );
		$m90  = aw_si_page_metrics( $list, 90 ); // daily rows are kept 90 days
		foreach ( $pub as $id => $h ) {
			$p                       = $paths[ $id ];
			$hosts[ $h ]['impr']    += (int) ( $m28[ $p ]['impr'] ?? 0 );
			$hosts[ $h ]['clicks']  += (int) ( $m28[ $p ]['clicks'] ?? 0 );
			$hosts[ $h ]['impr3']   += (int) ( $m90[ $p ]['impr'] ?? 0 );
			$hosts[ $h ]['clicks3'] += (int) ( $m90[ $p ]['clicks'] ?? 0 );
		}
	}
	$out = array( 'si' => $si, 'stale' => (bool) $stale, 'hosts' => $hosts );
	set_transient( 'aw_sourcing_host_stats', $out, HOUR_IN_SECONDS );
	return $out;
}

/* ---------- SEO: each site's own home page title and description, in the background ---------- */

add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( AW_SOURCING_SITE_META_CRON ) ) {
			wp_schedule_event( time() + 20 * MINUTE_IN_SECONDS, 'hourly', AW_SOURCING_SITE_META_CRON );
		}
	}
);
add_action( AW_SOURCING_SITE_META_CRON, 'aw_sourcing_site_meta_run' );

/**
 * Title and description from a page's HTML: <title> and <meta name="description">, og:title / og:description when
 * those are missing. Plain text, whitespace folded, title at most 200 and description 400 characters. Also the
 * address of the site's icon as the page gives it (icon), or ''.
 *
 * @param string $html Page HTML (the start of it is enough).
 * @return array{title: string, desc: string, icon: string}
 */
function aw_sourcing_parse_site_meta( $html ) {
	$html = (string) $html;
	if ( ! wp_check_invalid_utf8( $html ) && function_exists( 'mb_convert_encoding' ) ) {
		$html = (string) mb_convert_encoding( $html, 'UTF-8', 'ISO-8859-1' ); // an old site in Latin-1
	}
	$head  = stristr( $html, '</head>', true );
	$head  = false === $head ? $html : $head;
	$clean = function ( $s, $n ) {
		$s = html_entity_decode( (string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return mb_substr( trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $s ) ) ), 0, $n );
	};
	$meta = array();
	if ( preg_match_all( '#<meta\s[^>]*>#i', $head, $tags ) ) {
		foreach ( $tags[0] as $tag ) {
			preg_match_all( '#([a-zA-Z:_-]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))#', $tag, $at, PREG_SET_ORDER );
			$a = array();
			foreach ( $at as $x ) { // double-quoted, single-quoted or bare value
				$a[ strtolower( $x[1] ) ] = isset( $x[4] ) ? $x[4] : ( isset( $x[3] ) ? $x[3] : $x[2] );
			}
			$key = strtolower( (string) ( $a['name'] ?? ( $a['property'] ?? '' ) ) );
			if ( in_array( $key, array( 'description', 'og:title', 'og:description' ), true ) && ! isset( $meta[ $key ] ) ) {
				$meta[ $key ] = (string) ( $a['content'] ?? '' );
			}
		}
	}
	// The site's icon: a <link rel="icon"> (or "shortcut icon", "apple-touch-icon"), PNG or ICO before anything else;
	// SVG is never taken (an SVG file can carry script). Kept as written; source-icons.php makes it absolute.
	$icon = '';
	$rank = -1;
	if ( preg_match_all( '#<link\s[^>]*>#i', $head, $links ) ) {
		foreach ( $links[0] as $tag ) {
			preg_match_all( '#([a-zA-Z:_-]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))#', $tag, $at, PREG_SET_ORDER );
			$a = array();
			foreach ( $at as $x ) {
				$a[ strtolower( $x[1] ) ] = isset( $x[4] ) ? $x[4] : ( isset( $x[3] ) ? $x[3] : $x[2] );
			}
			$rel  = ' ' . strtolower( (string) ( $a['rel'] ?? '' ) ) . ' ';
			$href = trim( html_entity_decode( (string) ( $a['href'] ?? '' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			if ( '' === $href || ( false === strpos( $rel, ' icon ' ) && false === strpos( $rel, ' apple-touch-icon ' ) ) ) {
				continue;
			}
			$type = strtolower( (string) ( $a['type'] ?? '' ) );
			if ( 'image/svg+xml' === $type || preg_match( '#\.svgz?(\?|$)#i', $href ) || 0 === stripos( $href, 'data:' ) ) {
				continue;
			}
			$score = false !== strpos( $rel, ' apple-touch-icon ' ) ? 1 : ( preg_match( '#\.(png|ico)(\?|$)#i', $href ) || in_array( $type, array( 'image/png', 'image/x-icon', 'image/vnd.microsoft.icon' ), true ) ? 3 : 2 );
			if ( $score > $rank ) {
				$rank = $score;
				$icon = mb_substr( $href, 0, 500 );
			}
		}
	}
	$title = preg_match( '#<title[^>]*>(.*?)</title>#is', $head, $m ) ? $clean( $m[1], 200 ) : '';
	$desc  = $clean( $meta['description'] ?? '', 400 );
	return array(
		'title' => '' !== $title ? $title : $clean( $meta['og:title'] ?? '', 200 ),
		'desc'  => '' !== $desc ? $desc : $clean( $meta['og:description'] ?? '', 400 ),
		'icon'  => $icon,
	);
}

/**
 * One background run: up to $limit hosts not checked in 30 days, hosts with items or press sites first, then the
 * oldest checked. Each home page is fetched once (wp_safe_remote_get, 8 seconds, 3 redirects); a failure, or an
 * outbound HTTP block on the site, is recorded as the result.
 *
 * @param int $limit Hosts per run.
 */
function aw_sourcing_site_meta_run( $limit = 15 ) {
	$meta  = get_option( AW_SOURCING_SITE_META, array() );
	$meta  = is_array( $meta ) ? $meta : array();
	$want  = array();
	foreach ( aw_publishers() as $h => $p ) {
		if ( empty( $p['kind'] ) ) {
			$want[ $h ] = (int) ( $p['count'] ?? 0 ) > 0;
		}
	}
	foreach ( aw_sourcing_press_hosts() as $h ) {
		$want[ $h ] = true;
	}
	$fresh = gmdate( 'Y-m-d H:i', time() - 30 * DAY_IN_SECONDS );
	$todo  = array_filter( array_keys( $want ), fn( $h ) => (string) ( $meta[ $h ]['at'] ?? '' ) < $fresh );
	usort( $todo, fn( $a, $b ) => ( (int) $want[ $b ] <=> (int) $want[ $a ] ) ?: strcmp( (string) ( $meta[ $a ]['at'] ?? '' ), (string) ( $meta[ $b ]['at'] ?? '' ) ) ?: strcmp( $a, $b ) );
	foreach ( array_slice( $todo, 0, max( 1, (int) $limit ) ) as $h ) {
		$rec = array( 'title' => '', 'desc' => '', 'icon' => '', 'code' => '', 'at' => gmdate( 'Y-m-d H:i' ) );
		if ( ! preg_match( '/^[a-z0-9.-]+$/', $h ) ) {
			$rec['code'] = 'not a host';
		} else {
			$r = wp_safe_remote_get(
				'https://' . $h . '/',
				array(
					'timeout'             => 8,
					'redirection'         => 3,
					'limit_response_size' => 300000,
					'user-agent'          => 'AnimalWrites-SourceCheck/1.0 (+' . home_url( '/' ) . '; home page title and description of a news source)',
					'headers'             => array( 'Accept' => 'text/html' ),
				)
			);
			if ( is_wp_error( $r ) ) {
				$rec['code'] = mb_substr( 'error: ' . $r->get_error_message(), 0, 120 );
			} else {
				$rec['code'] = (string) (int) wp_remote_retrieve_response_code( $r );
				if ( (int) $rec['code'] >= 200 && (int) $rec['code'] < 300 ) {
					$rec = array_merge( $rec, aw_sourcing_parse_site_meta( wp_remote_retrieve_body( $r ) ) );
				}
			}
		}
		$meta[ $h ] = $rec;
	}
	update_option( AW_SOURCING_SITE_META, array_intersect_key( $meta, $want ), false ); // hosts no longer listed drop out
}

/* ---------- Domain Rating from a JSON file (WP-CLI) ---------- */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Set Domain Rating on publisher records from a JSON object { "host": 0-100, ... } (Ahrefs' free Domain Rating
	 * lookup, run by Claude). Sets dr and dr_at (today) on matching publishers; unknown hosts are skipped and listed.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : JSON file: an object of host => Domain Rating.
	 *
	 * [--dry-run]
	 * : Show what would change; write nothing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp aw-sourcing import-dr dr.json --dry-run
	 */
	WP_CLI::add_command(
		'aw-sourcing import-dr',
		function ( $args, $assoc ) {
			$file = (string) ( $args[0] ?? '' );
			if ( '' === $file || ! is_readable( $file ) ) {
				WP_CLI::error( 'Usage: wp aw-sourcing import-dr <file.json> [--dry-run]' );
			}
			$in = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( ! is_array( $in ) || ( $in && array_keys( $in ) === range( 0, count( $in ) - 1 ) ) ) {
				WP_CLI::error( 'The file must be a JSON object: { "host": Domain Rating, ... }' );
			}
			$dry     = ! empty( $assoc['dry-run'] );
			$today   = current_time( 'Y-m-d' );
			$pubs    = aw_publishers();
			$set     = array();
			$same    = 0;
			$unknown = array();
			$bad     = array();
			foreach ( $in as $raw => $v ) {
				$h = aw_sourcing_norm_host( $raw );
				if ( ! is_numeric( $v ) || (float) $v < 0 || (float) $v > 100 ) {
					$bad[] = (string) $raw;
					continue;
				}
				if ( '' === $h || ! isset( $pubs[ $h ] ) || ! empty( $pubs[ $h ]['kind'] ) ) { // no record is created here
					$unknown[] = (string) $raw;
					continue;
				}
				$dr = (int) round( (float) $v );
				if ( isset( $pubs[ $h ]['dr'] ) && (int) $pubs[ $h ]['dr'] === $dr && ( $pubs[ $h ]['dr_at'] ?? '' ) === $today ) {
					++$same;
					continue;
				}
				$set[ $h ] = $dr;
				WP_CLI::log( ( $dry ? 'would set ' : 'set ' ) . $h . ': ' . ( isset( $pubs[ $h ]['dr'] ) ? (int) $pubs[ $h ]['dr'] . ' → ' : '' ) . $dr );
			}
			if ( $set && ! $dry ) {
				$pubs = aw_publishers(); // read again just before the write: the ingest gate updates the same option
				foreach ( $set as $h => $dr ) {
					if ( isset( $pubs[ $h ] ) ) {
						$pubs[ $h ]['dr']    = $dr;
						$pubs[ $h ]['dr_at'] = $today;
					}
				}
				update_option( AW_PUB_OPTION, $pubs, false );
				aw_src_log( 'publishers', 'Domain Rating imported for ' . count( $set ) . ' publishers (WP-CLI)' );
			}
			foreach ( $unknown as $h ) {
				WP_CLI::log( 'skip ' . $h . ': not a publisher' );
			}
			foreach ( $bad as $h ) {
				WP_CLI::log( 'skip ' . $h . ': value is not a number from 0 to 100' );
			}
			WP_CLI::success( ( $dry ? 'Dry run: ' . count( $set ) . ' would be set' : count( $set ) . ' set' ) . ", {$same} unchanged, " . count( $unknown ) . ' unknown hosts skipped, ' . count( $bad ) . ' bad values skipped.' );
		}
	);
}

/* ---------- The Sources tab ---------- */

/** The Sources tab's screen: list (''), feeds (all feeds), or an edit form (edit=host, feed=id). */
function aw_sourcing_sources_screen() {
	// phpcs:disable WordPress.Security.NonceVerification -- which screen to show, nothing saved.
	if ( '' !== sanitize_text_field( wp_unslash( $_GET['edit'] ?? '' ) ) ) {
		return 'edit';
	}
	if ( '' !== sanitize_key( $_GET['feed'] ?? '' ) ) {
		return 'feed';
	}
	$v = sanitize_key( $_GET['view'] ?? '' );
	// phpcs:enable WordPress.Security.NonceVerification
	return 'feeds' === $v ? $v : ''; // the Relay log is its own screen (Sourcing → Relay log, relay.php)
}

// Help tabs (core contextual help): the screen's explanation and what each column means.
add_action(
	'load-' . AW_SRC_SCREEN,
	function () {
		$tab = sanitize_key( $_GET['tab'] ?? 'sources' ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! in_array( $tab, array( '', 'sources' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		$view   = aw_sourcing_sources_screen();
		$relay  = '<p><strong>Relay</strong> ("Fetch through this site"): some publishers refuse GitHub\'s servers but answer this site. When one of them answers the workers with 403, the workers ask this site to fetch the feed or page for them. The switch is on the source; its subdomains are included. Plugins → SourceWeb → Relay log lists every such request (the last 200), and the list shows each source\'s last result.</p>'
			. ( aw_sourcing_phase() < 1 ? '<p>Until the phase 1 migration (<code>wp aw-sourcing migrate --phase=1</code>) the relay list is the list of before: ' . esc_html( implode( ', ', aw_relay_hosts_legacy() ) ) . '.</p>' : '' );
		$screen->add_help_tab(
			array(
				'id'      => 'aw-sources-overview',
				'title'   => 'Overview',
				'content' => '<p>Every host the workers have sent, the sites searched for press coverage, and every site a feed or a source read by code comes from. Blocked publishers are refused; Neutral is the default for new hosts; Allowed publishers skip the keyword check. Short name: what cards show on the source chip (e.g. TOI, PIB); the full name stays on the item page.</p>'
					. '<p>Each source lists its feeds on its edit screen. The Feeds view lists every feed and source read by code in one list (News, Library and Opportunity), with the publisher each belongs to. A Google News search limited to one site belongs to that site; a search of the open web belongs to news.google.com.</p>',
			)
		);
		$screen->add_help_tab(
			array(
				'id'      => 'aw-sources-feeds',
				'title'   => 'Feeds',
				'content' => '<p>News: RSS feeds and Google News searches. Library: where Library documents and legal news come from (Gazette, AWBI and court-judgment sources need code to read; only their name and on/off can change here). Opportunity: where opportunities come from (only name and on/off; new kinds of site need code).</p>'
					. '<p>"Keyword check" keeps only items with a word from the Keywords tab (an include word or an animal word). A feed can be deleted; a source read by code cannot (it needs code), but it can be turned off.</p>',
			)
		);
		$screen->add_help_tab( array( 'id' => 'aw-sources-relay', 'title' => 'Relay', 'content' => $relay ) );
		if ( '' === $view ) {
			$screen->add_help_tab(
				array(
					'id'      => 'aw-sources-columns',
					'title'   => 'Columns',
					'content' => '<p><strong>Status</strong>: Blocked publishers are refused when the workers send their items; Neutral is the default for new hosts; Allowed publishers skip the keyword check. A site that is not a publisher (a press site, a relay-only site, or a site only feeds come from) shows —.</p>'
						. '<p><strong>Rating</strong>: Media Bias/Fact Check factual reporting and credibility, where recorded.</p>'
						. '<p><strong>Domain Rating</strong>: Ahrefs Domain Rating (0–100) from Ahrefs\' free Domain Rating lookup, run by Claude and imported with <code>wp aw-sourcing import-dr</code>; the date is when it was looked up.</p>'
						. '<p><strong>Feeds</strong>: the feeds and sources read by code that belong to this site (how many are on).</p>'
						. '<p><strong>Relay</strong>: whether the workers may fetch this site through this one, and the last relay result.</p>'
						. '<p><strong>Items</strong>: fetched in the last 30 days (every status) / published (all time).</p>'
						. '<p><strong>Search value</strong>: Search Console impressions and clicks of this source\'s published items\' pages over 28 days and 3 months. Shown only when Search Insights has a Search Console run within 28 days. Figures are refreshed hourly.</p>'
						. '<p><strong>SEO</strong>: the site\'s own home page title and description, read in the background (a few sites an hour, each again after 30 days), with the date and the HTTP result.</p>'
						. '<p><strong>Search here for coverage</strong>: the press queries (Press queries tab) also run on this site.</p>',
				)
			);
		}
		$screen->set_help_sidebar( '<p><strong>Change log</strong></p><p>Every add, edit and delete here is in the Change log tab, with the value before and after.</p>' );
	}
);

/**
 * Every source of the Sources list (publishers, relay-only sites, press sites and the sites feeds come from), host =>
 * row, with the numbers of the list's columns, for the Sources screen.
 *
 * @return array{rows: array, si: bool, stale: bool}
 */
function aw_sourcing_source_rows() {
	$all = array();
	$stats       = aw_sourcing_host_stats();
	$si          = ! empty( $stats['si'] );
	$stale       = ! empty( $stats['stale'] );
	$meta        = get_option( AW_SOURCING_SITE_META, array() );
	$meta        = is_array( $meta ) ? $meta : array();
	$press       = array_flip( aw_sourcing_press_hosts() );
	$pubs        = aw_publishers();
	$feeds       = aw_sourcing_feeds_by_host();
	$relay       = aw_relay_hosts();
	$log         = aw_src_relay_index( (array) get_option( 'aw_relay_log', array() ) ); // newest row per host named
	// A host kept only for its icon (kind=directory-source) is not listed; a relay-only one (kind=relay) is.
	$hosts = array_keys( array_filter( $pubs, fn( $p ) => empty( $p['kind'] ) || 'relay' === $p['kind'] ) );
	foreach ( array_unique( array_merge( $hosts, array_keys( $press ), array_keys( $feeds ) ) ) as $h ) {
		if ( '' === (string) $h ) {
			continue;
		}
		$p   = $pubs[ $h ] ?? array();
		$pub = isset( $pubs[ $h ] ) && empty( $p['kind'] );
		$s   = $stats['hosts'][ $h ] ?? array();
		$m   = $meta[ $h ] ?? array();
		$fl  = $feeds[ $h ] ?? array();
		$rel = aw_src_host_in( $h, $relay );
		$all[ $h ] = array(
			'host'    => $h,
			'pub'     => $pub,
			'kind'    => $pub ? 'publisher' : ( 'relay' === ( $p['kind'] ?? '' ) ? 'relay' : ( $fl ? 'feeds' : 'press' ) ),
			'feeds'   => count( $fl ),
			'feeds_on' => count( array_filter( $fl, fn( $x ) => ! empty( $x['on'] ) ) ),
			'code_n'  => count( array_filter( $fl, fn( $x ) => 'code' === aw_src_read_by( $x ) ) ), // sources read by code ('code' is the SEO HTTP status below)
			'fnames'  => implode( ' ', array_map( fn( $x ) => (string) ( $x['name'] ?? '' ), $fl ) ),
			'relay'   => $rel,
			'relay_own' => ! empty( $p['relay'] ),
			'relay_last' => aw_src_relay_last( $log, $h ),
			'count'   => (int) ( $p['count'] ?? 0 ),
			'press'   => isset( $press[ $h ] ),
			'name'    => $pub || 'relay' === ( $p['kind'] ?? '' ) ? (string) ( $p['name'] ?? '' ) : '',
			'short'   => $pub ? (string) ( $p['short'] ?? '' ) : '',
			'status'  => $pub ? (string) ( $p['status'] ?? 'neutral' ) : '',
			'rec'     => $pub ? $p : array(),
			'dr'      => $pub && isset( $p['dr'] ) && is_numeric( $p['dr'] ) ? (int) $p['dr'] : null,
			'dr_at'   => $pub ? (string) ( $p['dr_at'] ?? '' ) : '',
			'f30'     => (int) ( $s['f30'] ?? 0 ),
			'pub_n'   => (int) ( $s['pub'] ?? 0 ),
			'impr'    => (int) ( $s['impr'] ?? 0 ),
			'clicks'  => (int) ( $s['clicks'] ?? 0 ),
			'impr3'   => (int) ( $s['impr3'] ?? 0 ),
			'clicks3' => (int) ( $s['clicks3'] ?? 0 ),
			'title'   => (string) ( $m['title'] ?? '' ),
			'desc'    => (string) ( $m['desc'] ?? '' ),
			'code'    => (string) ( $m['code'] ?? '' ),
			'at'      => (string) ( $m['at'] ?? '' ),
			'last'    => $pub ? (string) ( $p['last'] ?? '' ) : '',
		);
	}
	return array(
		'rows'  => $all,
		'si'    => $si,
		'stale' => $stale,
	);
}

/**
 * Save the Add New / Edit Source form of the Sources screen (POST sourceweb/v1/sources/save). Logged with old → new:
 * status, name and short name as tab publishers, press sites as tab press, the relay switch as tab relay.
 *
 * @param array $in { host, status, name, short, press, relay_shown, relay }.
 * @return array{0: bool, 1: string} changed, error code ('' when none).
 */
function aw_sourcing_source_save( array $in ) {
	$host   = aw_sourcing_norm_host( sanitize_text_field( (string) ( $in['host'] ?? '' ) ) );
	$status = sanitize_key( (string) ( $in['status'] ?? '' ) );
	$name   = sanitize_text_field( (string) ( $in['name'] ?? '' ) );
	$short  = mb_substr( sanitize_text_field( (string) ( $in['short'] ?? '' ) ), 0, 24 );
	$press  = ! empty( $in['press'] );
	$shown  = ! empty( $in['relay_shown'] ) && aw_sourcing_phase() >= 1; // the relay switch was on the form
	$relay  = ! empty( $in['relay'] );
	if ( '' === $host ) {
		return array( false, 'host' );
	}
	$pubs    = aw_publishers(); // read just before the write: the ingest gate updates the same option
	$is_pub  = isset( $pubs[ $host ] ) && empty( $pubs[ $host ]['kind'] );
	$was     = in_array( $host, aw_sourcing_press_hosts(), true );
	$feeds   = ! empty( aw_sourcing_feeds_by_host()[ $host ] );
	$changed = false;
	if ( in_array( $status, array( 'allow', 'neutral', 'block' ), true ) ) {
		$before = $pubs[ $host ] ?? null;
		// An existing record keeps its keys in their order (an unchanged save then writes nothing); a new one gets the defaults.
		$rec = null === $before ? array( 'name' => '', 'count' => 0, 'first' => current_time( 'Y-m-d' ), 'last' => '', 'status' => $status ) : array_merge( $before, array( 'status' => $status ) );
		if ( 'relay' === ( $rec['kind'] ?? '' ) ) { // a relay-only site becomes a publisher
			unset( $rec['kind'] );
			aw_src_log( 'publishers', 'added ' . $host . ' as ' . $status . ' (was relay only)', 'relay only', $status );
		} elseif ( null === $before ) {
			aw_src_log( 'publishers', 'added ' . $host . ' as ' . $status, '', $status );
		} elseif ( (string) ( $before['status'] ?? 'neutral' ) !== $status ) {
			aw_src_log( 'publishers', $host . ' → ' . $status, (string) ( $before['status'] ?? 'neutral' ), $status );
		}
		if ( (string) $rec['name'] !== $name ) {
			if ( null !== $before ) {
				aw_src_log( 'publishers', $host . ' name → ' . ( '' === $name ? '(none)' : $name ), (string) $rec['name'], $name );
			}
			$rec['name'] = $name;
		}
		if ( (string) ( $rec['short'] ?? '' ) !== $short && ( '' !== $short || isset( $rec['short'] ) ) ) {
			aw_src_log( 'publishers', $host . ' short name → ' . ( '' === $short ? '(none)' : $short ), (string) ( $rec['short'] ?? '' ), $short );
			$rec['short'] = $short;
		}
		if ( $shown && $relay !== ! empty( $rec['relay'] ) ) {
			if ( $relay ) {
				$rec['relay'] = true;
			} else {
				unset( $rec['relay'] ); // no relay key rather than false: an unchanged record keeps its shape
			}
			aw_src_log( 'relay', $host . ': fetch through this site', $relay ? 'off' : 'on', $relay ? 'on' : 'off' );
		}
		if ( $rec !== $before ) {
			$pubs[ $host ] = $rec;
			update_option( AW_PUB_OPTION, $pubs, false );
			$changed = true;
		}
	} elseif ( 'none' !== $status || $is_pub ) {
		return array( false, 'status' );
	} else {
		$is_relay = ! empty( $pubs[ $host ]['relay'] );
		$existed  = isset( $pubs[ $host ] ) && 'relay' === ( $pubs[ $host ]['kind'] ?? '' ); // a relay-only record the form showed
		if ( ! $press && ! $was && ! $feeds && ! ( $shown ? $relay : $is_relay ) ) {
			return array( false, 'nothing' ); // not a publisher, not searched for coverage, no feeds, not relayed: nothing to keep
		}
		if ( $shown && $relay !== $is_relay ) {
			$pubs = aw_relay_switch( $pubs, array( $host ), $relay );
			update_option( AW_PUB_OPTION, $pubs, false );
			$changed = true;
		}
		if ( $existed && isset( $pubs[ $host ] ) && (string) ( $pubs[ $host ]['name'] ?? '' ) !== $name ) { // its name, as the form showed it
			aw_src_log( 'relay', $host . ' name → ' . ( '' === $name ? '(none)' : $name ), (string) ( $pubs[ $host ]['name'] ?? '' ), $name );
			$pubs[ $host ]['name'] = $name;
			update_option( AW_PUB_OPTION, $pubs, false );
			$changed = true;
		}
	}
	if ( $press !== $was ) {
		$changed = aw_sourcing_press_change( $press ? array( $host ) : array(), $press ? array() : array( $host ) ) || $changed;
	}
	return array( $changed, '' );
}

/**
 * Delete a source (Sources list row action): a publisher record with no items, no feeds and no press search, or a
 * relay-only record with no feeds. Logged with the record as it was.
 *
 * @param array  $pubs aw_publishers (read just before).
 * @param string $host Host.
 * @return array The records.
 */
function aw_sourcing_source_delete( array $pubs, $host ) {
	$host  = aw_sourcing_norm_host( $host );
	$stats = aw_sourcing_host_stats()['hosts'][ $host ] ?? array();
	$rec   = $pubs[ $host ] ?? null;
	if ( null === $rec || ! empty( aw_sourcing_feeds_by_host()[ $host ] ) || in_array( $host, aw_sourcing_press_hosts(), true )
		|| (int) ( $rec['count'] ?? 0 ) > 0 || (int) ( $stats['f30'] ?? 0 ) > 0 || (int) ( $stats['pub'] ?? 0 ) > 0
		|| ! in_array( (string) ( $rec['kind'] ?? '' ), array( '', 'relay' ), true ) ) {
		return $pubs; // only a source with nothing attached is deleted
	}
	unset( $pubs[ $host ] );
	aw_src_log( 'publishers', 'deleted ' . $host, $rec, '' );
	return $pubs;
}
