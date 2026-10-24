<?php
/**
 * Tools → Search demand (15 Oct 2026): what people search and how Animal Writes shows up for it.
 *
 * - One table (owner, 15 Oct 2026): every Search Console query of the latest 3 months merged with the keyword map's
 *   target phrases, ranked by Importance; Gaps sit collapsed under it. No filters, no CSV.
 * - Search Console (last 28 days and last 3 months) comes through Site Kit's own search-console module, the same
 *   call the 15 Oct research used. It refreshes daily by WP-Cron (19 Oct 2026; weekly before, so a reload showed
 *   week-old figures) and on "Refresh now". Site Kit keeps the Google
 *   connection; this file never reads its tokens.
 * - Targets ("Our target?") and Gaps come from Search Insights' keyword table (search-insights/searches.php), seeded
 *   once from docs/seo/keyword-map.md and the 21 Oct rankings (24 Oct 2026; the hard-coded 15 Oct snapshot is gone).
 * - The Search Console snapshot sits in one option, aw_search_demand (not autoloaded).
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SD_OPTION = 'aw_search_demand';
const AW_SD_CRON   = 'aw_search_demand_refresh';

/** Brand searches: the site's own names and addresses (not the generic "animal rights"). */
const AW_SD_BRAND = '/animal\s*-?\s*writ|animalwrit|animal\s*-?\s*rights?\s*\.\s*in|animalrights?\.in|india\s*writes/i';

/**
 * The stored Search Console snapshot. The hard-coded 15 Oct keyword map that used to sit here ('static') was retired on
 * 24 Oct 2026: targets and gaps come from Search Insights' keyword table. An old copy is dropped on first read.
 */
function aw_sd_get(): array {
	$data = get_option( AW_SD_OPTION, array() );
	$data = is_array( $data ) ? $data : array();
	if ( isset( $data['static'] ) ) {
		unset( $data['static'] );
		update_option( AW_SD_OPTION, $data, false );
	}
	return $data;
}

function aw_sd_save( array $data ): void {
	update_option( AW_SD_OPTION, $data, false );
}

/** A date with the site's calendar icon (aw_date from Animal Writes core), in IST. */
function aw_sd_date( $when, string $fmt = 'j M Y' ): string {
	if ( ! $when ) {
		return 'Never';
	}
	return function_exists( 'aw_date' ) ? aw_date( $when, $fmt ) : esc_html( wp_date( $fmt, is_numeric( $when ) ? (int) $when : strtotime( (string) $when ) ) );
}

/**
 * Site Kit's Search Console module, or why it cannot be used.
 *
 * @return array{0: object|null, 1: string, 2: int} Module, problem in plain words ('' if none), owner user ID.
 */
function aw_sd_sitekit(): array {
	if ( ! class_exists( '\Google\Site_Kit\Plugin' ) || ! class_exists( '\Google\Site_Kit\Core\Modules\Modules' ) ) {
		return array( null, 'Site Kit by Google is not active, so Search Console cannot be read.', 0 );
	}
	$settings = get_option( 'googlesitekit_search-console_settings' );
	$owner    = is_array( $settings ) ? (int) ( $settings['ownerID'] ?? 0 ) : 0;
	if ( empty( $settings['propertyID'] ) || ! $owner || ! get_userdata( $owner ) ) {
		return array( null, 'Search Console is not connected in Site Kit. Connect it under Site Kit → Settings.', 0 );
	}
	$was = get_current_user_id();
	wp_set_current_user( $owner ); // Site Kit binds the Google client to the current user when the module is built.
	try {
		if ( defined( 'GOOGLESITEKIT_PLUGIN_MAIN_FILE' ) && class_exists( '\Google\Site_Kit\Context' ) ) {
			$ctx = new \Google\Site_Kit\Context( GOOGLESITEKIT_PLUGIN_MAIN_FILE );
		} else {
			$plugin = \Google\Site_Kit\Plugin::instance();
			$prop   = ( new ReflectionClass( $plugin ) )->getProperty( 'context' );
			$prop->setAccessible( true );
			$ctx = $prop->getValue( $plugin );
		}
		$module = ( new \Google\Site_Kit\Core\Modules\Modules( $ctx ) )->get_module( 'search-console' );
	} catch ( Throwable $e ) {
		wp_set_current_user( $was );
		return array( null, 'Site Kit\'s Search Console module could not be loaded.', 0 );
	}
	wp_set_current_user( $was );
	if ( method_exists( $module, 'is_connected' ) && ! $module->is_connected() ) {
		return array( null, 'Search Console is not connected in Site Kit. Connect it under Site Kit → Settings.', 0 );
	}
	return array( $module, '', $owner );
}

/**
 * Rows of one searchanalytics request as plain arrays: keys, clicks, impressions, position.
 * $url limits the rows to one page (Site Kit's only filter; it does not pass startRow, so this is how a day over the
 * 5,000-row limit is split).
 *
 * @param object $module Site Kit's Search Console module.
 * @param array  $dims   Dimensions.
 * @param string $start  First date (Y-m-d).
 * @param string $end    Last date (Y-m-d).
 * @param string $url    Only this page (full URL), or '' for the whole site.
 */
function aw_sd_query( $module, array $dims, string $start, string $end, string $url = '' ) {
	$args = array( 'dimensions' => $dims, 'startDate' => $start, 'endDate' => $end, 'limit' => 5000 );
	if ( '' !== $url ) {
		$args['url'] = $url;
	}
	try {
		$r = $module->get_data( 'searchanalytics', $args );
	} catch ( Throwable $e ) {
		return new WP_Error( 'aw_sd', $e->getMessage() );
	}
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$rows = array();
	foreach ( (array) $r as $row ) {
		if ( is_object( $row ) && method_exists( $row, 'getKeys' ) ) {
			$rows[] = array( (array) $row->getKeys(), (int) $row->getClicks(), (int) $row->getImpressions(), (float) $row->getPosition() );
		} elseif ( is_array( $row ) ) {
			$rows[] = array( (array) ( $row['keys'] ?? array() ), (int) ( $row['clicks'] ?? 0 ), (int) ( $row['impressions'] ?? 0 ), (float) ( $row['position'] ?? 0 ) );
		}
	}
	return $rows;
}

/** Clicks, impressions and impression-weighted position of a set of rows. */
function aw_sd_sum( array $rows ): array {
	$c = 0;
	$i = 0;
	$p = 0.0;
	foreach ( $rows as $r ) {
		$c += $r[1];
		$i += $r[2];
		$p += $r[3] * $r[2];
	}
	return array( 'clicks' => $c, 'impr' => $i, 'pos' => $i ? round( $p / $i, 1 ) : 0 );
}

/**
 * Fetch the Search Console part through Site Kit and store it. Runs as the Site Kit owner of Search Console.
 *
 * @return string '' on success, else the problem in plain words.
 */
function aw_sd_refresh(): string {
	@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	$data = aw_sd_get();
	list( $module, $problem, $owner ) = aw_sd_sitekit();
	if ( ! $module ) {
		$data['gsc_error'] = array( 'time' => time(), 'message' => $problem );
		aw_sd_save( $data );
		return $problem;
	}
	$was = get_current_user_id();
	wp_set_current_user( $owner );
	$tz      = wp_timezone();
	$end     = new DateTimeImmutable( 'yesterday', $tz );
	$periods = array(
		'28d' => $end->modify( '-27 days' ),
		'3m'  => $end->modify( '-3 months' )->modify( '+1 day' ),
	);
	$home    = untrailingslashit( home_url() );
	$out     = array();
	$error   = '';
	foreach ( $periods as $key => $start ) {
		$s     = $start->format( 'Y-m-d' );
		$e     = $end->format( 'Y-m-d' );
		$dates = aw_sd_query( $module, array( 'date' ), $s, $e );
		$qs    = is_wp_error( $dates ) ? $dates : aw_sd_query( $module, array( 'query' ), $s, $e );
		$qp    = is_wp_error( $qs ) ? $qs : aw_sd_query( $module, array( 'query', 'page' ), $s, $e );
		if ( is_wp_error( $qp ) ) {
			$error = 'Site Kit could not read Search Console: ' . wp_strip_all_tags( $qp->get_error_message() );
			break;
		}
		$page = array(); // Query → [impressions, page] of the page Google showed most.
		foreach ( $qp as $r ) {
			$q = (string) ( $r[0][0] ?? '' );
			if ( ! isset( $page[ $q ] ) || $r[2] > $page[ $q ][0] ) {
				$path       = str_replace( array( $home, 'https://www.animalwrites.in', 'https://animalwrites.in' ), '', (string) ( $r[0][1] ?? '' ) );
				$page[ $q ] = array( $r[2], '' === $path ? '/' : $path );
			}
		}
		$brand    = array();
		$nonbrand = array();
		$queries  = array();
		foreach ( $qs as $r ) {
			$q         = (string) ( $r[0][0] ?? '' );
			$is_brand  = (bool) preg_match( AW_SD_BRAND, $q );
			$queries[] = array( $q, $r[1], $r[2], round( $r[3], 1 ), $page[ $q ][1] ?? '', $is_brand ? 1 : 0 );
			if ( $is_brand ) {
				$brand[] = $r;
			} else {
				$nonbrand[] = $r;
			}
		}
		usort( $queries, fn( $a, $b ) => $b[2] <=> $a[2] ?: $b[1] <=> $a[1] );
		$out[ $key ] = array(
			'start'    => $s,
			'end'      => $e,
			'totals'   => aw_sd_sum( $dates ),
			'named'    => aw_sd_sum( $qs ),
			'brand'    => aw_sd_sum( $brand ),
			'nonbrand' => aw_sd_sum( $nonbrand ),
			'queries'  => $queries, // Query, clicks, impressions, position, page, brand.
		);
	}
	wp_set_current_user( $was );
	if ( $error ) {
		$data['gsc_error'] = array( 'time' => time(), 'message' => $error );
		aw_sd_save( $data );
		return $error;
	}
	$settings    = get_option( 'googlesitekit_search-console_settings' );
	$data['gsc'] = array( 'fetched' => time(), 'property' => (string) ( $settings['propertyID'] ?? '' ), 'periods' => $out );
	unset( $data['gsc_error'] );
	aw_sd_save( $data );
	return '';
}

add_action( AW_SD_CRON, 'aw_sd_refresh' );
add_action(
	'init',
	function () {
		if ( 'daily' !== wp_get_schedule( AW_SD_CRON ) ) {
			wp_clear_scheduled_hook( AW_SD_CRON ); // The weekly event of 15 Oct 2026.
			$next = new DateTimeImmutable( 'tomorrow 07:00', wp_timezone() ); // Daily, 07:00 IST.
			wp_schedule_event( $next->getTimestamp(), 'daily', AW_SD_CRON );
		}
	}
);


/** Capability of the Sourcing screen (Kits Core), so both screens open for the same people. */
function aw_sd_cap(): string {
	return defined( 'AW_SRC_CAP' ) ? AW_SRC_CAP : 'manage_options';
}

/*
 * Tools → Search demand (owner, 15 Oct 2026: Settings holds configuration, Tools holds reports and jobs). It was under
 * Settings next to Sourcing; the old address redirects here.
 */
add_action(
	'admin_menu',
	function () {
		add_management_page( 'Search demand', 'Search demand', aw_sd_cap(), 'aw-search-demand', 'aw_sd_screen' );
	},
	99
);
add_filter( 'aw_admin_moved_pages', fn( $m ) => $m + array( 'aw-search-demand' => 'tools.php' ) ); // Old Settings address → Tools (admin-ia.php).

add_action(
	'admin_post_aw_search_demand_refresh',
	function () {
		if ( ! current_user_can( aw_sd_cap() ) || ! check_admin_referer( 'aw_search_demand_refresh' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$problem = aw_sd_refresh();
		$back    = wp_get_referer() ? remove_query_arg( array( 'refreshed', 'refresh_failed' ), wp_get_referer() ) : admin_url( 'tools.php?page=aw-search-demand' );
		wp_safe_redirect( add_query_arg( $problem ? 'refresh_failed' : 'refreshed', 1, $back ) );
		exit;
	}
);

/** Lowercase, single-spaced. */
function aw_sd_norm( string $s ): string {
	return trim( (string) preg_replace( '/\s+/u', ' ', mb_strtolower( $s ) ) );
}

/**
 * Keyword-map targets, one per phrase: phrase, pages ("Section: Page") and a regex for matching queries (null when the
 * phrase is all placeholder, like "{organisation name}" or "{type} in {district}", which would match nearly anything).
 * A plain phrase matches a query that contains it as whole words; a phrase with {placeholders} must match the whole query.
 */
function aw_sd_targets( array $map ): array {
	$out = array();
	foreach ( $map as $m ) {
		$phrase = aw_sd_norm( (string) $m[2] );
		$page   = $m[0] . ': ' . $m[1];
		if ( isset( $out[ $phrase ] ) ) {
			$out[ $phrase ]['pages'][] = $page;
			continue;
		}
		$re = null;
		if ( false === strpos( $phrase, '{' ) ) {
			$re = '/(?:^|\s)' . preg_quote( $phrase, '/' ) . '(?:\s|$)/u';
		} else {
			$literal = preg_split( '/\s+/', trim( (string) preg_replace( '/\{[^}]*\}/', ' ', $phrase ) ) );
			$words   = array_filter( (array) $literal, fn( $w ) => mb_strlen( $w ) >= 3 && ! in_array( $w, array( 'the', 'and', 'for' ), true ) );
			if ( $words ) {
				$parts = preg_split( '/\{[^}]*\}/', $phrase );
				$re    = '/^' . implode( '.+', array_map( fn( $x ) => preg_quote( $x, '/' ), $parts ) ) . '$/u';
			}
		}
		$out[ $phrase ] = array( 'phrase' => $phrase, 'pages' => array( $page ), 're' => $re );
	}
	return $out;
}

/**
 * The one table: Search Console queries (latest 3 months) merged with the keyword-map targets.
 *
 * Importance (owner, 15 Oct 2026). Each non-brand query weighs its impressions over the latest 3 months, doubled
 * when it sits at average position 5–20 with 0 clicks (Google shows us on page 1–2 and nobody clicks: an opportunity).
 * Brand queries and target phrases with no Search Console data weigh 0. Importance % = weight ÷ sum of all weights
 * × 100, rounded to 2 decimals by largest remainder so the column adds up to exactly 100.
 *
 * @return array<int, array{q:string, clicks:?int, impr:?int, pos:?float, page:string, brand:bool, targets:array, imp:float}>
 */
function aw_sd_table( array $data ): array {
	$targets = aw_sd_targets( function_exists( 'aw_si_searches_targets' ) ? aw_si_searches_targets() : array() );
	$rows    = array();
	$seen    = array();
	foreach ( (array) ( $data['gsc']['periods']['3m']['queries'] ?? array() ) as $r ) {
		$q     = (string) $r[0];
		$nq    = aw_sd_norm( $q );
		$brand = (bool) preg_match( AW_SD_BRAND, $q );
		$hits  = array();
		foreach ( $targets as $t ) {
			if ( $t['phrase'] === $nq || ( $t['re'] && preg_match( $t['re'], $nq ) ) ) {
				$hits[] = $t;
				if ( $t['phrase'] === $nq ) {
					$seen[ $t['phrase'] ] = true;
				}
			}
		}
		usort( $hits, fn( $a, $b ) => ( $b['phrase'] === $nq ) <=> ( $a['phrase'] === $nq ) ); // The exact phrase first.
		$opp    = 0 === (int) $r[1] && $r[3] >= 5 && $r[3] <= 20;
		$rows[] = array(
			'q'       => $q,
			'clicks'  => (int) $r[1],
			'impr'    => (int) $r[2],
			'pos'     => (float) $r[3],
			'page'    => (string) $r[4],
			'brand'   => $brand,
			'targets' => $hits,
			'w'       => $brand ? 0 : (int) $r[2] * ( $opp ? 2 : 1 ),
		);
	}
	foreach ( $targets as $t ) {
		if ( empty( $seen[ $t['phrase'] ] ) ) { // No query is exactly this phrase: it still gets its own row.
			$rows[] = array( 'q' => $t['phrase'], 'clicks' => null, 'impr' => null, 'pos' => null, 'page' => '', 'brand' => false, 'targets' => array( $t ), 'w' => 0 );
		}
	}
	// Largest remainder, in hundredths of a per cent.
	$sum   = array_sum( array_column( $rows, 'w' ) );
	$units = array();
	$rest  = array();
	foreach ( $rows as $i => $row ) {
		$exact       = $sum ? $row['w'] * 10000 / $sum : 0;
		$units[ $i ] = (int) floor( $exact );
		$rest[ $i ]  = $exact - $units[ $i ];
	}
	arsort( $rest );
	$left = $sum ? 10000 - array_sum( $units ) : 0;
	foreach ( array_keys( $rest ) as $i ) {
		if ( $left-- <= 0 ) {
			break;
		}
		++$units[ $i ];
	}
	foreach ( $rows as $i => &$row ) {
		$row['imp'] = $units[ $i ] / 100;
	}
	unset( $row );
	usort( $rows, fn( $a, $b ) => $b['imp'] <=> $a['imp'] ?: (int) $b['impr'] <=> (int) $a['impr'] ?: strcmp( $a['q'], $b['q'] ) );
	return $rows;
}

function aw_sd_num( $n ): string {
	return esc_html( number_format_i18n( (float) $n ) );
}

/**
 * The Search demand screen. Since 24 Oct 2026 it is the Searches tab of Tools → Search Insights, which calls it with
 * $embedded = true (no wrap or heading of its own); the old address redirects to that tab (search-insights/admin.php).
 *
 * @param bool $embedded Inside the Search Insights screen.
 */
function aw_sd_screen( bool $embedded = false ) {
	if ( ! current_user_can( aw_sd_cap() ) ) {
		return;
	}
	$data              = aw_sd_get();
	$gsc               = $data['gsc'] ?? array();
	list( , $problem ) = aw_sd_sitekit();
	$rows              = aw_sd_table( $data );

	if ( ! $embedded ) {
		echo '<div class="wrap"><h1>Search demand</h1>';
	}
	if ( isset( $_GET['refreshed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$up_to = $gsc['periods']['28d']['end'] ?? '';
		$count = count( $gsc['periods']['3m']['queries'] ?? array() );
		echo '<div class="notice notice-success is-dismissible"><p>Refreshed at ' . esc_html( wp_date( 'H:i', (int) ( $gsc['fetched'] ?? time() ) ) ) . ' IST: ' . esc_html( aw_sd_num( $count ) ) . ' searches from Search Console, figures up to ' . ( $up_to ? aw_sd_date( $up_to ) : 'yesterday' ) . '. Google adds a new day once a day, so a second refresh the same day shows the same figures.</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- aw_sd_date escapes.
	}
	if ( isset( $_GET['refresh_failed'] ) && ! empty( $data['gsc_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-error"><p>Refresh failed: ' . esc_html( $data['gsc_error']['message'] ) . '</p></div>';
	}
	if ( $problem ) {
		echo '<div class="notice notice-warning"><p>' . esc_html( $problem ) . ' The figures below are the last ones fetched.</p></div>';
	}

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_search_demand_refresh">';
	wp_nonce_field( 'aw_search_demand_refresh' );
	$up_to = $gsc['periods']['28d']['end'] ?? '';
	echo '<p>Search Console figures up to ' . ( $up_to ? aw_sd_date( $up_to ) : 'never fetched' ) . ' · fetched ' . ( ! empty( $gsc['fetched'] ) ? aw_sd_date( $gsc['fetched'] ) . ' ' . esc_html( wp_date( 'H:i', (int) $gsc['fetched'] ) ) . ' IST' : 'never' ) . ' · fetches by itself daily at 07:00 IST '; // phpcs:ignore WordPress.Security.EscapeOutput -- aw_sd_date escapes.
	submit_button( 'Refresh now', 'secondary', 'submit', false );
	echo '</p></form>';

	echo '<h2>Searches and targets <span class="count">(' . aw_sd_num( count( $rows ) ) . ')</span></h2>';
	echo '<p class="description">Importance: each non-brand search\'s share of all non-brand impressions over the last 3 months, counted double when Google shows us at position 5–20 and nobody clicks; the column adds up to 100%.</p>';
	$cols = array( 'Query', 'Importance (%)', 'Clicks', 'Impressions', 'Avg. position', 'Page Google shows', 'Our target?' );
	echo '<table id="aw-sd-table" class="wp-list-table widefat striped"><thead><tr>';
	foreach ( $cols as $n => $label ) {
		$state = 1 === $n ? 'sorted desc' : 'sortable asc';
		echo '<th scope="col" class="manage-column ' . esc_attr( $state ) . '"' . ( 1 === $n ? ' aria-sort="descending"' : '' ) . ' data-col="' . (int) $n . '"><a href="#"><span>' . esc_html( $label ) . '</span><span class="sorting-indicators"><span class="sorting-indicator asc" aria-hidden="true"></span><span class="sorting-indicator desc" aria-hidden="true"></span></span></a></th>';
	}
	echo '</tr></thead><tbody>';
	if ( ! $rows ) {
		echo '<tr><td colspan="7">Not fetched yet. Use Refresh now.</td></tr>';
	}
	$dash = '—';
	foreach ( $rows as $r ) {
		$target = $dash;
		$tsort  = '';
		if ( $r['targets'] ) {
			$t      = $r['targets'][0];
			$tsort  = implode( ', ', $t['pages'] );
			$target = 'Yes: ' . esc_html( $tsort ) . ( aw_sd_norm( $r['q'] ) !== $t['phrase'] ? '<br><span class="description">' . esc_html( '"' . $t['phrase'] . '"' ) . '</span>' : '' );
		}
		$none = null === $r['impr'];
		echo '<tr>'
			. '<td data-v="' . esc_attr( $r['q'] ) . '">' . esc_html( $r['q'] ) . ( $r['brand'] ? ' — <span class="post-state">Brand</span>' : '' ) . '</td>'
			. '<td data-v="' . esc_attr( (string) $r['imp'] ) . '">' . esc_html( number_format_i18n( $r['imp'], 2 ) ) . '</td>'
			. '<td data-v="' . ( $none ? -1 : (int) $r['clicks'] ) . '">' . ( $none ? $dash : aw_sd_num( $r['clicks'] ) ) . '</td>'
			. '<td data-v="' . ( $none ? -1 : (int) $r['impr'] ) . '">' . ( $none ? $dash : aw_sd_num( $r['impr'] ) ) . '</td>'
			. '<td data-v="' . ( $none ? 9999 : esc_attr( (string) $r['pos'] ) ) . '">' . ( $none ? $dash : esc_html( number_format_i18n( $r['pos'], 1 ) ) ) . '</td>'
			. '<td data-v="' . esc_attr( $r['page'] ) . '">' . ( $r['page'] ? '<a href="' . esc_url( home_url( $r['page'] ) ) . '" target="_blank" rel="noopener">' . esc_html( $r['page'] ) . '</a>' : $dash ) . '</td>'
			. '<td data-v="' . esc_attr( $tsort ) . '">' . $target . '</td>' // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
			. '</tr>';
	}
	echo '</tbody></table>';
	?>
<script>
( function () {
	// A browser reload after Refresh now shows the page, not the old "Refreshed" notice again.
	if ( window.history.replaceState && /[?&](refreshed|refresh_failed)=/.test( location.search ) ) {
		window.history.replaceState( null, '', location.href.replace( /[?&](refreshed|refresh_failed)=1/, '' ) );
	}
	var table = document.getElementById( 'aw-sd-table' );
	if ( ! table ) { return; }
	var heads = table.tHead.rows[0].cells, body = table.tBodies[0];
	Array.prototype.forEach.call( heads, function ( th ) {
		th.querySelector( 'a' ).addEventListener( 'click', function ( e ) {
			e.preventDefault();
			var col = +th.getAttribute( 'data-col' ), text = col === 0 || col >= 5;
			var desc = th.getAttribute( 'aria-sort' ) ? th.getAttribute( 'aria-sort' ) === 'ascending' : ! text;
			var rows = Array.prototype.slice.call( body.rows );
			rows.sort( function ( a, b ) {
				var x = a.cells[ col ].getAttribute( 'data-v' ), y = b.cells[ col ].getAttribute( 'data-v' );
				var c = text ? x.localeCompare( y ) : ( +x - +y );
				return desc ? -c : c;
			} );
			rows.forEach( function ( r ) { body.appendChild( r ); } );
			Array.prototype.forEach.call( heads, function ( h ) {
				h.removeAttribute( 'aria-sort' );
				h.className = 'manage-column sortable asc';
			} );
			th.className = 'manage-column sorted ' + ( desc ? 'desc' : 'asc' );
			th.setAttribute( 'aria-sort', desc ? 'descending' : 'ascending' );
		} );
	} );
}() );
</script>
	<?php

	$gaps = function_exists( 'aw_si_searches_gaps' ) ? aw_si_searches_gaps() : array();
	echo '<details style="margin-top:1.5em"><summary><strong>Gaps (' . aw_sd_num( count( $gaps ) ) . ')</strong>: searches with demand that no page targets yet. Suggestions only.</summary>';
	echo '<table class="widefat striped" style="margin-top:.5em"><thead><tr><th>#</th><th>Gap</th><th>Detail</th></tr></thead><tbody>';
	foreach ( $gaps as $i => $g ) {
		echo '<tr><td>' . (int) ( $i + 1 ) . '</td><td><strong>' . esc_html( $g[0] ) . '</strong></td><td>' . esc_html( $g[1] ) . '</td></tr>';
	}
	echo '</tbody></table></details>';
	if ( ! $embedded ) {
		echo '</div>';
	}
}
