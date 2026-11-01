<?php
/**
 * Bing Webmaster (owner, 31 Oct 2026), free JSON API, monthly on the 2nd at 07:40 IST. Only with
 * AW_BING_WEBMASTER_KEY under Animal Writes → Keys or in wp-config.php (the Webmaster API key, not an IndexNow key); without it nothing runs and the
 * source is not listed. The key is never logged or shown.
 *
 * - Links: GetLinkCounts (our pages with inbound links, paged), then GetUrlLinks for each page (linking page and anchor),
 *   into wp_aw_si_links with source "Bing Webmaster": new rows get first_seen today, rows already there move last_seen.
 *   A new row whose anchor sells links or gambling is typed spam and hidden; the rest stay unsorted for the owner.
 * - Figures: GetQueryStats and GetPageStats into obs, src bing, period week (Bing groups them by week; each row keeps
 *   Bing's own date, and is never spread over days; owner, 31 Oct 2026), as Search Console's:
 *   q (query, matched to an existing keyword record; other queries are skipped, no record is created) and p (page,
 *   kw 0). Weekly rows are not rolled up; a later run replaces the same week's row.
 *
 * A failed run is logged with aw_si_run_fail( 'bing' ) and the stored rows stay. A failed or timed-out GetUrlLinks call
 * only makes the run partial: what was read is saved, with a warning.
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
const AW_SI_BING_CURSOR = 'aw_si_bing_cursor'; // Option: where the last cut-short link read stopped (targets, next, page).
const AW_SI_BING_DAYS   = 85; // Figures stored: at most this many days, and 5 fewer than the rollup keeps (aw_si_bing_days()).

/** Whether the Webmaster API key is set. */
function aw_si_bing_on(): bool {
	return '' !== aw_si_key( 'AW_BING_WEBMASTER_KEY' );
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
	$key = aw_si_key( 'AW_BING_WEBMASTER_KEY' );
	$url = add_query_arg( array_map( 'rawurlencode', array( 'siteUrl' => home_url( '/' ) ) + $args + array( 'apikey' => $key ) ), AW_SI_BING_API . $method );
	$res = wp_remote_get(
		$url,
		array(
			'timeout' => 20,
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

/** Days of figures stored: fewer than the rollup keeps as days, so a day already rolled up is never stored again. */
function aw_si_bing_days(): int {
	$keep = defined( 'AW_SI_KEEP_DAYS' ) ? (int) AW_SI_KEEP_DAYS : 90;
	return max( 1, min( AW_SI_BING_DAYS, $keep - 5 ) );
}

/**
 * Inbound links: [linked_url, from_url, anchor] for every page Bing lists, within the time budget. A failed
 * GetLinkCounts call fails the run; a failed GetUrlLinks call stops here and keeps what was read (partial). A read cut
 * short by time, a page cap or an error saves where it stopped (AW_SI_BING_CURSOR) and the next run carries on there
 * (Codex, PR #91): links are upserted, so reading a page twice only moves its dates.
 *
 * @param float $until Stop time (microtime).
 * @param bool  $dry   Read only: the cursor is neither used up nor saved.
 * @return array|WP_Error array{links: array, partial: bool, warning: string}
 */
function aw_si_bing_links( float $until, bool $dry = false ) {
	$partial = false;
	$warning = '';
	$cur     = (array) get_option( AW_SI_BING_CURSOR, array() );
	if ( ! empty( $cur['targets'] ) && time() - (int) ( $cur['at'] ?? 0 ) < 45 * DAY_IN_SECONDS ) {
		$ours = array_values( (array) $cur['targets'] ); // Carry on with last run's list of our linked pages.
		$next = (int) ( $cur['next'] ?? 0 );
		$from = (int) ( $cur['page'] ?? 0 );
	} else {
		$ours = array();
		$next = 0;
		$from = 0;
		for ( $page = 0; $page < AW_SI_BING_PAGES; $page++ ) {
			if ( microtime( true ) > $until ) {
				$partial = true;
				break;
			}
			$d = aw_si_bing_call( 'GetLinkCounts', array( 'page' => $page ) );
			if ( is_wp_error( $d ) ) {
				return $d;
			}
			foreach ( (array) ( $d['Links'] ?? array() ) as $l ) {
				if ( ! empty( $l['Url'] ) ) {
					$ours[] = (string) $l['Url'];
				}
			}
			$total = (int) ( $d['TotalPages'] ?? 0 );
			if ( $page + 1 >= $total ) {
				break;
			}
			if ( $page + 1 >= AW_SI_BING_PAGES ) {
				$partial = true; // More pages of our linked pages than one run reads.
			}
		}
		$ours = array_values( array_unique( $ours ) );
	}
	$out  = array();
	$stop = null; // [target index, page] where this run stopped.
	for ( $i = $next; $i < count( $ours ); $i++ ) {
		$to = $ours[ $i ];
		for ( $page = $i === $next ? $from : 0; ; $page++ ) {
			if ( microtime( true ) > $until || $page >= ( $i === $next ? $from : 0 ) + AW_SI_BING_PAGES ) {
				$partial = true;
				$stop    = array( $i, $page );
				break 2;
			}
			$d = aw_si_bing_url_links( $to, $page );
			if ( is_wp_error( $d ) ) {
				$partial = true;
				$warning = $d->get_error_message();
				$stop    = array( $i, $page );
				break 2;
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
	if ( ! $dry ) {
		if ( $stop ) {
			update_option(
				AW_SI_BING_CURSOR,
				array(
					'targets' => $ours,
					'next'    => $stop[0],
					'page'    => $stop[1],
					'at'      => time(),
				),
				false
			);
		} else {
			delete_option( AW_SI_BING_CURSOR );
		}
	}
	return array(
		'links'   => $out,
		'partial' => $partial,
		'warning' => $warning,
		'resume'  => (bool) $stop,
	);
}

/**
 * GetUrlLinks for one of our pages. Bing's JSON examples pass "link" as a JSON string; a plain URL is tried first and a
 * 400 is retried JSON-quoted (Codex, PR #91), so either form works.
 *
 * @param string $to   Our page.
 * @param int    $page Result page.
 * @return array|WP_Error
 */
function aw_si_bing_url_links( string $to, int $page ) {
	$d = aw_si_bing_call(
		'GetUrlLinks',
		array(
			'link' => $to,
			'page' => $page,
		)
	);
	if ( is_wp_error( $d ) && false !== strpos( $d->get_error_message(), ' 400' ) ) {
		$d = aw_si_bing_call(
			'GetUrlLinks',
			array(
				'link' => (string) wp_json_encode( $to, JSON_UNESCAPED_SLASHES ),
				'page' => $page,
			)
		);
	}
	return $d;
}

/**
 * Query and page figures as obs rows [kw_id, page, metric, d, clicks, impr, pos], merged per key.
 *
 * @param array $queries GetQueryStats rows.
 * @param array $pages   GetPageStats rows.
 * @return array array{rows: array, skipped: int}
 */
function aw_si_bing_obs_rows( array $queries, array $pages ): array {
	$since   = wp_date( 'Y-m-d', time() - aw_si_bing_days() * DAY_IN_SECONDS );
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
 * The monthly run. Never throws; failures go to the run log and stored rows stay. Figures are saved before the
 * per-page link calls, so a link call that fails or runs out of time never loses them.
 *
 * @param array $args dry (fetch and report only).
 * @return array Report.
 */
function aw_si_bing_run( array $args = array() ): array {
	if ( ! aw_si_bing_on() ) {
		return array( 'error' => __( 'Bing Webmaster is not set up: add its API key under Animal Writes → Keys.', 'aw-seo-extras' ) );
	}
	$dry = ! empty( $args['dry'] );
	if ( ! $dry && get_option( 'aw_si_off' ) ) { // After Delete data nothing is written until Search Insights is switched back on.
		return array( 'error' => __( 'Search Insights is off (its data was deleted), so Bing Webmaster did not run.', 'aw-seo-extras' ) );
	}
	@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	if ( ! $dry ) {
		aw_si_install();
		aw_si_run_start( 'bing' );
	}
	$rep = array(
		'rows'     => 0,
		'added'    => 0,
		'updated'  => 0,
		'skipped'  => 0,
		'failed'   => 0,
		'partial'  => false,
		'warnings' => array(),
	);
	try {
		$until = microtime( true ) + AW_SI_BING_BUDGET;
		$q     = aw_si_bing_call( 'GetQueryStats' );
		$p     = is_wp_error( $q ) ? $q : aw_si_bing_call( 'GetPageStats' );
		if ( is_wp_error( $p ) ) {
			$rep['error'] = $p->get_error_message();
		} else {
			$obs                  = aw_si_bing_obs_rows( (array) $q, (array) $p );
			$rep['queries']       = count( (array) $q );
			$rep['pages']         = count( (array) $p );
			$rep['obs']           = count( $obs['rows'] );
			$rep['queries_no_kw'] = $obs['skipped'];
			if ( ! $dry ) {
				foreach ( $obs['rows'] as $v ) {
					$ok = aw_si_obs_put(
						$v[0],
						$v[1],
						'bing',
						$v[2],
						'week', // Bing's own weekly grouping (owner, 31 Oct 2026).
						$v[3],
						array(
							'clicks' => $v[4],
							'impr'   => $v[5],
							'pos'    => round( $v[6], 2 ),
						)
					);
					$ok ? ++$rep['rows'] : ++$rep['failed'];
				}
			}
			$l = aw_si_bing_links( $until, $dry );
			if ( is_wp_error( $l ) ) {
				$rep['error'] = $l->get_error_message();
			} else {
				$rep['links']   = count( $l['links'] );
				$rep['partial'] = $l['partial'];
				if ( '' !== $l['warning'] ) {
					/* translators: %s: Bing's error. */
					$rep['warnings'][] = sprintf( __( 'Some linked pages could not be read (%s); the next run carries on from there.', 'aw-seo-extras' ), $l['warning'] );
				} elseif ( $l['partial'] ) {
					$rep['warnings'][] = __( 'Not every linked page was read in time; the next run carries on from there.', 'aw-seo-extras' );
				}
				if ( ! $dry && ! empty( $l['resume'] ) && ! wp_next_scheduled( AW_SI_BING_CRON ) ) {
					wp_schedule_single_event( time() + HOUR_IN_SECONDS, AW_SI_BING_CRON ); // Carry on in an hour, not next month.
				}
				if ( $dry ) {
					$rep['sample'] = array(
						'obs'   => array_slice( $obs['rows'], 0, 5 ),
						'links' => array_slice( $l['links'], 0, 10 ),
					);
					return $rep;
				}
				foreach ( $l['links'] as $v ) {
					if ( '' === aw_si_link_domain( $v[1] ) || '' === esc_url_raw( $v[0] ) ) {
						++$rep['skipped']; // No usable address: nothing to store.
						continue;
					}
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
				if ( $rep['skipped'] ) {
					/* translators: %d: links. */
					$rep['warnings'][] = sprintf( __( '%d links had no usable address and were skipped.', 'aw-seo-extras' ), $rep['skipped'] );
				}
			}
			if ( empty( $rep['error'] ) && $rep['failed'] ) {
				/* translators: %d: rows. */
				$rep['error'] = sprintf( __( '%d Bing rows could not be saved.', 'aw-seo-extras' ), $rep['failed'] );
			}
		}
	} catch ( Throwable $e ) {
		$rep['error'] = str_replace( aw_si_key( 'AW_BING_WEBMASTER_KEY' ), '…', $e->getMessage() );
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

/**
 * Bing searches without a keyword record (1 Nov 2026): Bing's figures are stored only for searches that already have a
 * record, so its own searches were skipped (46 of 57 on 1 Nov). Settings → Bing searches counts them first, then adds a
 * record for each (role "seen in search") and stores Bing's figures for them at once.
 *
 * @return array{labels: string[], error: string} Searches Bing reported that have no record.
 */
function aw_si_bing_missing(): array {
	$q = aw_si_bing_call( 'GetQueryStats' );
	if ( is_wp_error( $q ) ) {
		return array( 'labels' => array(), 'error' => $q->get_error_message() );
	}
	$map  = aw_si_kw_map();
	$want = array();
	foreach ( (array) $q as $r ) {
		$label = trim( (string) ( $r['Query'] ?? '' ) );
		$norm  = aw_si_norm( $label );
		if ( '' === $norm || isset( $map[ $norm ] ) || mb_strlen( $label ) < 3 || mb_strlen( $label ) > 100 || preg_match( '/^(?:site|inurl|url):/i', $label ) ) {
			continue;
		}
		$want[ $norm ] = $label;
	}
	return array( 'labels' => array_values( $want ), 'error' => '' );
}

add_action(
	'aw_si_settings_rows',
	function () {
		if ( ! aw_si_bing_on() ) {
			return;
		}
		echo '<tr><th scope="row">' . esc_html__( 'Bing searches', 'aw-seo-extras' ) . '</th><td>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_si_bing_kw">';
		wp_nonce_field( 'aw_si_bing_kw' );
		echo '<p><button type="submit" name="what" value="count" class="button">' . esc_html__( 'Count Bing searches without a keyword record', 'aw-seo-extras' ) . '</button> ';
		echo '<button type="submit" name="what" value="add" class="button">' . esc_html__( 'Add keyword records for them', 'aw-seo-extras' ) . '</button></p>';
		echo '</form>';
		echo '<p class="description">' . esc_html__( 'Bing\'s figures are stored only for searches that have a keyword record. Adding records (role: seen in search) runs Bing Webmaster again, so their figures are stored too. Nothing is added to Rank Math or Sourcing.', 'aw-seo-extras' ) . '</p></td></tr>';
	}
);

add_action(
	'admin_post_aw_si_bing_kw',
	function () {
		if ( ! current_user_can( aw_si_cap() ) || ! check_admin_referer( 'aw_si_bing_kw' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'aw-seo-extras' ), 403 );
		}
		$what = isset( $_POST['what'] ) ? sanitize_key( wp_unslash( $_POST['what'] ) ) : 'count';
		$miss = aw_si_bing_missing();
		$res  = array(
			'error' => $miss['error'],
			'count' => count( $miss['labels'] ),
			'added' => 0,
			'list'  => array_slice( $miss['labels'], 0, 60 ),
		);
		if ( '' === $miss['error'] && 'add' === $what && $miss['labels'] && ! get_option( 'aw_si_off' ) ) {
			$res['added'] = count( aw_si_kw_ids( $miss['labels'], AW_SI_ROLE_SEARCHED ) );
			aw_si_bing_run();
		}
		set_transient( 'aw_si_bing_kw_' . get_current_user_id(), $res, 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'bingkw', 1, aw_si_url( 'settings' ) ) );
		exit;
	}
);

add_action(
	'aw_si_settings_notices',
	function () {
		if ( ! isset( $_GET['bingkw'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- message only.
			return;
		}
		$r = get_transient( 'aw_si_bing_kw_' . get_current_user_id() );
		if ( ! is_array( $r ) ) {
			return;
		}
		if ( '' !== $r['error'] ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( $r['error'] ) . '</p></div>';
			return;
		}
		/* translators: 1: searches without a record, 2: records written, 3: the searches. */
		echo '<div class="notice notice-success inline"><p>' . esc_html( sprintf( __( 'Bing searches without a keyword record: %1$s. Records written: %2$s. %3$s', 'aw-seo-extras' ), number_format_i18n( (int) $r['count'] ), number_format_i18n( (int) $r['added'] ), implode( ', ', (array) $r['list'] ) ) ) . '</p></div>';
	}
);
