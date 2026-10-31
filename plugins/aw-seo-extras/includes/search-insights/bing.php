<?php
/**
 * Bing Webmaster (owner, 31 Oct 2026), free JSON API, monthly on the 2nd at 07:40 IST. Only with
 * AW_BING_WEBMASTER_KEY in wp-config.php (the Webmaster API key, not an IndexNow key); without it nothing runs and the
 * source is not listed. The key is never logged or shown.
 *
 * - Links: GetLinkCounts (our pages with inbound links, paged), then GetUrlLinks for each page (linking page and anchor),
 *   into wp_aw_si_links with source "Bing Webmaster": new rows get first_seen today, rows already there move last_seen.
 *   A new row whose anchor sells links or gambling is typed spam and hidden; the rest stay unsorted for the owner.
 * - Figures: GetQueryStats and GetPageStats into obs, src bing, period day (Bing's own dates), as Search Console's:
 *   q (query, matched to an existing keyword record; other queries are skipped, no record is created) and p (page,
 *   kw 0). Only the last 85 days are stored, so the 90-day rollup never counts a day twice.
 *
 * A failed run is logged with aw_si_run_fail( 'bing' ) and the stored rows stay.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SI_BING_CRON   = 'aw_si_bing';
const AW_SI_BING_API    = 'https://ssl.bing.com/webmaster/api.svc/json/';
const AW_SI_BING_SOURCE = 'Bing Webmaster';
const AW_SI_BING_PAGES  = 20; // Pages of GetLinkCounts, and of GetUrlLinks per linked page, at most.
const AW_SI_BING_BUDGET = 120; // Seconds per run: stop before a host time limit, so the run is always logged.
const AW_SI_BING_DAYS   = 85; // Figures older than this are not stored (the rollup turns 90-day-old rows into months).

/** Whether the Webmaster API key is set. */
function aw_si_bing_on(): bool {
	return defined( 'AW_BING_WEBMASTER_KEY' ) && '' !== (string) AW_BING_WEBMASTER_KEY;
}

add_filter(
	'aw_si_sources',
	function ( $s ) {
		if ( aw_si_bing_on() ) { // Listed only once the key is set, so it never shows as late or failed before.
			$s['bing'] = array(
				'label' => __( 'Bing Webmaster', 'aw-seo-extras' ),
				'every' => 31 * DAY_IN_SECONDS,
			);
		}
		return $s;
	}
);

/**
 * One API call. The answer is inside {"d": …}.
 *
 * @param string $method GetLinkCounts, GetUrlLinks, GetQueryStats or GetPageStats.
 * @param array  $args   Query arguments besides siteUrl and the key.
 * @return mixed|WP_Error The "d" part.
 */
function aw_si_bing_call( string $method, array $args = array() ) {
	$key = (string) AW_BING_WEBMASTER_KEY;
	$url = add_query_arg( array_map( 'rawurlencode', array( 'siteUrl' => home_url( '/' ) ) + $args + array( 'apikey' => $key ) ), AW_SI_BING_API . $method );
	$res = wp_remote_get(
		$url,
		array(
			'timeout' => 60,
			'headers' => array( 'Accept' => 'application/json' ),
		)
	);
	if ( is_wp_error( $res ) ) {
		return new WP_Error( 'aw_si_bing', str_replace( array( $key, rawurlencode( $key ) ), '…', $res->get_error_message() ) );
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
	if ( 200 !== $code || ! is_array( $body ) || ! array_key_exists( 'd', $body ) ) {
		$msg = is_array( $body ) && ! empty( $body['Message'] ) ? (string) $body['Message'] : '';
		/* translators: 1: API method, 2: HTTP status, 3: Bing's message. */
		return new WP_Error( 'aw_si_bing', str_replace( array( $key, rawurlencode( $key ) ), '…', trim( sprintf( __( 'Bing %1$s answered %2$d. %3$s', 'aw-seo-extras' ), $method, $code, mb_substr( $msg, 0, 200 ) ) ) ) );
	}
	return $body['d'];
}

/**
 * A Bing date ("/Date(1729123200000-0700)/") as Y-m-d in the site time zone, or ''.
 *
 * @param string $d Bing date.
 */
function aw_si_bing_date( string $d ): string {
	if ( ! preg_match( '/\((-?\d+)/', $d, $m ) ) {
		return '';
	}
	return wp_date( 'Y-m-d', (int) floor( (int) $m[1] / 1000 ) );
}

/**
 * Inbound links: [linked_url, from_url, anchor] for every page Bing lists, within the time budget.
 *
 * @param float $until Stop time (microtime).
 * @return array|WP_Error array{links: array, partial: bool}
 */
function aw_si_bing_links( float $until ) {
	$ours    = array();
	$partial = false;
	for ( $page = 0; $page < AW_SI_BING_PAGES; $page++ ) {
		$d = aw_si_bing_call( 'GetLinkCounts', array( 'page' => $page ) );
		if ( is_wp_error( $d ) ) {
			return $d;
		}
		foreach ( (array) ( $d['Links'] ?? array() ) as $l ) {
			if ( ! empty( $l['Url'] ) ) {
				$ours[] = (string) $l['Url'];
			}
		}
		if ( $page + 1 >= (int) ( $d['TotalPages'] ?? 0 ) ) {
			break;
		}
	}
	$out = array();
	foreach ( array_unique( $ours ) as $to ) {
		for ( $page = 0; $page < AW_SI_BING_PAGES; $page++ ) {
			if ( microtime( true ) > $until ) {
				$partial = true;
				break 2;
			}
			$d = aw_si_bing_call(
				'GetUrlLinks',
				array(
					'link' => $to,
					'page' => $page,
				)
			);
			if ( is_wp_error( $d ) ) {
				return $d;
			}
			foreach ( (array) ( $d['Details'] ?? array() ) as $r ) {
				if ( ! empty( $r['Url'] ) ) {
					$out[] = array( $to, (string) $r['Url'], (string) ( $r['AnchorText'] ?? '' ) );
				}
			}
			if ( $page + 1 >= (int) ( $d['TotalPages'] ?? 0 ) ) {
				break;
			}
		}
	}
	return array(
		'links'   => $out,
		'partial' => $partial,
	);
}

/**
 * Query and page figures as obs rows [kw_id, page, metric, d, clicks, impr, pos], merged per key.
 *
 * @param array $queries GetQueryStats rows.
 * @param array $pages   GetPageStats rows.
 * @return array array{rows: array, skipped: int}
 */
function aw_si_bing_obs_rows( array $queries, array $pages ): array {
	$since   = wp_date( 'Y-m-d', time() - AW_SI_BING_DAYS * DAY_IN_SECONDS );
	$map     = aw_si_ready() ? aw_si_kw_map() : array();
	$out     = array();
	$skipped = 0;
	$add     = function ( int $kw, string $page, string $metric, array $r ) use ( &$out, $since ) {
		$d = aw_si_bing_date( (string) ( $r['Date'] ?? '' ) );
		if ( '' === $d || $d < $since ) {
			return;
		}
		$c   = (int) ( $r['Clicks'] ?? 0 );
		$i   = (int) ( $r['Impressions'] ?? 0 );
		$p   = (float) ( $r['AvgImpressionPosition'] ?? 0 );
		$key = $kw . '|' . $page . '|' . $metric . '|' . $d;
		if ( isset( $out[ $key ] ) ) {
			$m           = $out[ $key ];
			$impr        = $m[5] + $i;
			$out[ $key ] = array( $kw, $page, $metric, $d, $m[4] + $c, $impr, $impr ? ( $m[6] * $m[5] + $p * $i ) / $impr : 0 );
			return;
		}
		$out[ $key ] = array( $kw, $page, $metric, $d, $c, $i, $p );
	};
	foreach ( $queries as $r ) {
		$kw = (int) ( $map[ aw_si_norm( (string) ( $r['Query'] ?? '' ) ) ] ?? 0 );
		if ( ! $kw ) {
			++$skipped;
			continue;
		}
		$add( $kw, '', 'q', (array) $r );
	}
	foreach ( $pages as $r ) {
		$path = aw_si_path( (string) ( $r['Query'] ?? '' ) );
		if ( '' !== $path ) {
			$add( 0, $path, 'p', (array) $r );
		}
	}
	return array(
		'rows'    => array_values( $out ),
		'skipped' => $skipped,
	);
}

/**
 * The monthly run. Never throws; failures go to the run log and stored rows stay.
 *
 * @param array $args dry (fetch and report only).
 * @return array Report.
 */
function aw_si_bing_run( array $args = array() ): array {
	if ( ! aw_si_bing_on() ) {
		return array( 'error' => __( 'Bing Webmaster is not set up: add AW_BING_WEBMASTER_KEY to wp-config.php.', 'aw-seo-extras' ) );
	}
	$dry = ! empty( $args['dry'] );
	if ( ! $dry ) {
		aw_si_install();
		aw_si_run_start( 'bing' );
	}
	$rep = array();
	try {
		$until = microtime( true ) + AW_SI_BING_BUDGET;
		$q     = aw_si_bing_call( 'GetQueryStats' );
		$p     = is_wp_error( $q ) ? $q : aw_si_bing_call( 'GetPageStats' );
		$l     = is_wp_error( $p ) ? $p : aw_si_bing_links( $until );
		if ( is_wp_error( $l ) ) {
			$rep['error'] = $l->get_error_message();
		} else {
			$obs = aw_si_bing_obs_rows( (array) $q, (array) $p );
			$rep = array(
				'queries'  => count( (array) $q ),
				'pages'    => count( (array) $p ),
				'obs'      => count( $obs['rows'] ),
				'skipped'  => $obs['skipped'],
				'links'    => count( $l['links'] ),
				'added'    => 0,
				'updated'  => 0,
				'partial'  => $l['partial'],
				'rows'     => 0,
				'failed'   => 0,
				'warnings' => array(),
			);
			if ( $dry ) {
				$rep['sample'] = array(
					'obs'   => array_slice( $obs['rows'], 0, 5 ),
					'links' => array_slice( $l['links'], 0, 10 ),
				);
				return $rep;
			}
			foreach ( $obs['rows'] as $v ) {
				$ok = aw_si_obs_put(
					$v[0],
					$v[1],
					'bing',
					$v[2],
					'day',
					$v[3],
					array(
						'clicks' => $v[4],
						'impr'   => $v[5],
						'pos'    => round( $v[6], 2 ),
					)
				);
				$ok ? ++$rep['rows'] : ++$rep['failed'];
			}
			foreach ( $l['links'] as $v ) {
				$n = aw_si_link_put(
					array(
						'from_url'   => $v[1],
						'linked_url' => $v[0],
						'anchor'     => $v[2],
						'source'     => AW_SI_BING_SOURCE,
						'type'       => aw_si_link_auto_type( $v[2] ),
					)
				);
				if ( 1 === $n ) {
					++$rep['added'];
				} elseif ( $n >= 0 ) {
					++$rep['updated'];
				} else {
					++$rep['failed'];
				}
			}
			if ( $l['partial'] ) {
				$rep['warnings'][] = __( 'Time ran out before every linked page was read; the rest wait for next month.', 'aw-seo-extras' );
			}
			if ( $rep['failed'] ) {
				/* translators: %d: rows. */
				$rep['error'] = sprintf( __( '%d Bing rows could not be saved.', 'aw-seo-extras' ), $rep['failed'] );
			}
		}
	} catch ( Throwable $e ) {
		$rep['error'] = str_replace( (string) AW_BING_WEBMASTER_KEY, '…', $e->getMessage() );
	}
	if ( ! $dry ) {
		if ( ! empty( $rep['error'] ) ) {
			aw_si_run_fail( 'bing', $rep['error'] );
		} else {
			aw_si_run_ok( 'bing', $rep );
		}
	}
	return $rep;
}
add_action( AW_SI_BING_CRON, fn() => get_option( 'aw_si_off' ) || ! aw_si_bing_on() ? null : aw_si_bing_run() );

/** Next 2nd of the month at 07:40 IST (site time). */
function aw_si_bing_next(): int {
	$tz   = wp_timezone();
	$next = ( new DateTimeImmutable( 'first day of this month 07:40', $tz ) )->modify( '+1 day' );
	if ( $next->getTimestamp() <= time() ) {
		$next = ( new DateTimeImmutable( 'first day of next month 07:40', $tz ) )->modify( '+1 day' );
	}
	return $next->getTimestamp();
}

// One event at a time: after it runs, the next 2nd is booked (WP-cron has no monthly schedule).
add_action(
	'init',
	function () {
		if ( get_option( 'aw_si_off' ) || ! aw_si_bing_on() || wp_next_scheduled( AW_SI_BING_CRON ) ) {
			return;
		}
		wp_schedule_single_event( aw_si_bing_next(), AW_SI_BING_CRON );
	}
);
