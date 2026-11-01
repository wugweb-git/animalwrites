<?php
/**
 * GA4 through Site Kit (plan §5.1), read only, daily at 07:20 IST, as the analytics-4 module's own owner. Nothing is sent
 * to GA4 and nothing in GA4 or GTM changes. Three reports per day, for the last 3 days (GA4 finishes processing late;
 * stored days are replaced):
 * - search: view_search_results events by search term (terms that look like personal data are dropped);
 * - ai: sessions from AI assistants (chatgpt.com, perplexity.ai, gemini.google.com, copilot …) by landing page;
 * - organic: sessions from Organic Search by landing page;
 * - ref, social: every referral (channel Referral, Organic Social, Paid Social) and the social sources below, by source
 *   and landing page, with sessions, engaged sessions and key events (owner, 31 Oct 2026);
 * - nf: 404_view events by path for the last 28 days, one report per run (1 Nov 2026). The path is the event's own
 *   parameter (custom dimension customEvent:path); while that dimension is not registered in GA4 the report falls back
 *   to pagePath, which is the same address on a 404 page.
 *
 * Rows in obs: src ga4, period day; search (txt = term, page_key = md5 of the term), ai (page = landing path,
 * txt = source), organic (page = landing path), ref / ref_eng / ref_key (page = landing path, txt = source). value =
 * events or sessions. Referral counts leave out AW_SI_GA4_SKIP_DAYS (22 Oct 2026: test events). nf rows: period 28d,
 * d = the last day of the window, page = txt = path, value = events; each run replaces the earlier window, and a failed
 * report keeps it.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SI_GA4_DAYS = 3;
const AW_SI_GA4_CRON = 'aw_si_ga4';
const AW_SI_GA4_ROWS = 10000; // Rows per report; a report that returns this many is marked truncated.

/** Days left out of referral counts: 22 Oct 2026 has GA4 test events (owner, 31 Oct 2026). */
const AW_SI_GA4_SKIP_DAYS = array( '2026-10-22' );

/** Social sources counted as referrals even when GA4 files them under another channel. Filter aw_si_social_sources. */
function aw_si_social_sources(): array {
	return (array) apply_filters( 'aw_si_social_sources', array( 'ig', 'instagram.com', 'l.instagram.com', 'instagram', 'facebook.com', 'm.facebook.com', 'l.facebook.com', 'lm.facebook.com', 'facebook', 't.co', 'x.com', 'twitter.com', 'linkedin.com', 'lnkd.in', 'linkedin', 'reddit.com', 'out.reddit.com', 'reddit', 'whatsapp', 'wa.me', 'web.whatsapp.com', 'telegram', 't.me', 'web.telegram.org', 'linktr.ee' ) );
}

/** SQL condition that leaves out AW_SI_GA4_SKIP_DAYS (column d). */
function aw_si_ga4_skip_sql(): string {
	return AW_SI_GA4_SKIP_DAYS ? " AND d NOT IN ('" . implode( "', '", array_map( 'esc_sql', AW_SI_GA4_SKIP_DAYS ) ) . "')" : '';
}

/** Session sources counted as AI referrals. Filter aw_si_ai_sources adds more. */
function aw_si_ai_sources(): array {
	return (array) apply_filters( 'aw_si_ai_sources', array( 'chatgpt.com', 'chat.openai.com', 'perplexity.ai', 'www.perplexity.ai', 'gemini.google.com', 'copilot.microsoft.com', 'copilot.com', 'claude.ai' ) );
}

/**
 * Rows of a GA4 report response as [dimension values, metric values]. Accepts Site Kit's response object or an array.
 *
 * @param mixed $resp Report response.
 * @return array<int, array{0: string[], 1: float[]}>
 */
function aw_si_ga4_rows( $resp ): array {
	if ( is_object( $resp ) && method_exists( $resp, 'toSimpleObject' ) ) {
		$resp = json_decode( (string) wp_json_encode( $resp->toSimpleObject() ), true );
	} elseif ( is_object( $resp ) ) {
		$resp = json_decode( (string) wp_json_encode( $resp ), true );
	}
	$out = array();
	foreach ( (array) ( $resp['rows'] ?? array() ) as $row ) {
		$dims  = array_map( fn( $v ) => (string) ( $v['value'] ?? '' ), (array) ( $row['dimensionValues'] ?? array() ) );
		$mets  = array_map( fn( $v ) => (float) ( $v['value'] ?? 0 ), (array) ( $row['metricValues'] ?? array() ) );
		$out[] = array( $dims, $mets );
	}
	return $out;
}

/**
 * One report.
 *
 * @param object $module Site Kit's analytics-4 module.
 * @param array  $args   Report arguments (Site Kit's report datapoint).
 * @return array|WP_Error Rows, as aw_si_ga4_rows().
 */
function aw_si_ga4_report( $module, array $args ) {
	try {
		$resp = $module->get_data( 'report', $args + array( 'limit' => AW_SI_GA4_ROWS ) );
	} catch ( Throwable $e ) {
		return new WP_Error( 'aw_si_ga4', $e->getMessage() );
	}
	return is_wp_error( $resp ) ? $resp : aw_si_ga4_rows( $resp );
}

/**
 * The reports for one day.
 *
 * @param object $module Site Kit's analytics-4 module.
 * @param string $day    Date (Y-m-d).
 * @return array|WP_Error array{search: array, ai: array, organic: array, ref: array, social: array}
 */
function aw_si_ga4_fetch_day( $module, string $day ) {
	$base = array(
		'startDate' => $day,
		'endDate'   => $day,
	);
	$reqs = array(
		'search'  => array(
			'dimensions'       => array( 'searchTerm' ),
			'metrics'          => array( 'eventCount' ),
			'dimensionFilters' => array( 'eventName' => 'view_search_results' ),
		),
		'ai'      => array(
			'dimensions'       => array( 'sessionSource', 'landingPage' ),
			'metrics'          => array( 'sessions' ),
			'dimensionFilters' => array( 'sessionSource' => array_values( aw_si_ai_sources() ) ),
		),
		'organic' => array(
			'dimensions'       => array( 'landingPage' ),
			'metrics'          => array( 'sessions' ),
			'dimensionFilters' => array( 'sessionDefaultChannelGroup' => 'Organic Search' ),
		),
		'ref'     => array(
			'dimensions'       => array( 'sessionSource', 'landingPage' ),
			'metrics'          => array( 'sessions', 'engagedSessions', 'keyEvents' ),
			'dimensionFilters' => array( 'sessionDefaultChannelGroup' => array( 'Referral', 'Organic Social', 'Paid Social' ) ),
		),
		'social'  => array(
			'dimensions'       => array( 'sessionSource', 'landingPage', 'sessionDefaultChannelGroup' ),
			'metrics'          => array( 'sessions', 'engagedSessions', 'keyEvents' ),
			'dimensionFilters' => array( 'sessionSource' => array_values( aw_si_social_sources() ) ),
		),
	);
	$out  = array();
	foreach ( $reqs as $key => $args ) {
		$rows = aw_si_ga4_report( $module, $base + $args );
		if ( is_wp_error( $rows ) && in_array( $key, array( 'ref', 'social' ), true ) ) {
			$out['warnings'][] = $key . ': ' . $rows->get_error_message(); // The referral reports never stop the older three.
			$rows              = array();
		} elseif ( is_wp_error( $rows ) ) {
			return $rows;
		}
		$out[ $key ] = $rows;
	}
	return $out;
}

/**
 * GA4 rows of one day as obs rows: [kw_id 0, page, page_key, metric, value, txt]. Merges duplicates (two landing
 * pages with different query strings are one path).
 *
 * @param array $data What aw_si_ga4_fetch_day() returned.
 */
function aw_si_ga4_obs_rows( array $data ): array {
	$out = array();
	$add = function ( string $metric, string $page, string $txt, float $value ) use ( &$out ) {
		$key = $metric . '|' . $page . '|' . $txt;
		if ( isset( $out[ $key ] ) ) {
			$out[ $key ][4] += $value;
			return;
		}
		$pk          = 'search' === $metric ? md5( 'term:' . $txt ) : md5( $page . ( '' !== $txt ? '|' . $txt : '' ) );
		$out[ $key ] = array( 0, $page, $pk, $metric, $value, $txt );
	};
	foreach ( (array) ( $data['search'] ?? array() ) as $r ) {
		$term = aw_si_term_ok( (string) ( $r[0][0] ?? '' ) );
		if ( '' !== $term ) {
			$add( 'search', '', $term, (float) ( $r[1][0] ?? 0 ) );
		}
	}
	foreach ( (array) ( $data['ai'] ?? array() ) as $r ) {
		$path = aw_si_landing_path( (string) ( $r[0][1] ?? '' ) );
		if ( '' !== $path ) {
			$add( 'ai', $path, mb_substr( (string) ( $r[0][0] ?? '' ), 0, 100 ), (float) ( $r[1][0] ?? 0 ) );
		}
	}
	foreach ( (array) ( $data['organic'] ?? array() ) as $r ) {
		$path = aw_si_landing_path( (string) ( $r[0][0] ?? '' ) );
		if ( '' !== $path ) {
			$add( 'organic', $path, '', (float) ( $r[1][0] ?? 0 ) );
		}
	}
	// Referrals: the channel report, then the social sources' sessions in any other channel (the social report is split
	// by channel, so sessions the channel report already has are not counted twice).
	$channels = array( 'Referral', 'Organic Social', 'Paid Social' );
	foreach ( array( 'ref', 'social' ) as $rep_key ) {
		foreach ( (array) ( $data[ $rep_key ] ?? array() ) as $r ) {
			$path = aw_si_landing_path( (string) ( $r[0][1] ?? '' ) );
			$src  = mb_substr( strtolower( (string) ( $r[0][0] ?? '' ) ), 0, 100 );
			if ( '' === $path || '' === $src || ( 'social' === $rep_key && in_array( (string) ( $r[0][2] ?? '' ), $channels, true ) ) ) {
				continue;
			}
			$add( 'ref', $path, $src, (float) ( $r[1][0] ?? 0 ) );
			$add( 'ref_eng', $path, $src, (float) ( $r[1][1] ?? 0 ) );
			$add( 'ref_key', $path, $src, (float) ( $r[1][2] ?? 0 ) );
		}
	}
	return array_values( $out );
}

/**
 * 404_view events by path for the 28 days up to $end, without AW_SI_GA4_SKIP_DAYS.
 *
 * @param object $module Site Kit's analytics-4 module.
 * @param string $end    Last day (Y-m-d).
 * @return array|WP_Error array{dim: string, rows: array<string, float>} Path => events.
 */
function aw_si_ga4_fetch_nf( $module, string $end ) {
	$args = array(
		'startDate'        => ( new DateTimeImmutable( $end, wp_timezone() ) )->modify( '-27 days' )->format( 'Y-m-d' ),
		'endDate'          => $end,
		'metrics'          => array( 'eventCount' ),
		'dimensionFilters' => array( 'eventName' => '404_view' ),
	);
	$err  = null;
	foreach ( array( 'customEvent:path', 'pagePath' ) as $dim ) {
		$res = aw_si_ga4_report( $module, $args + array( 'dimensions' => array( $dim, 'date' ) ) );
		if ( ! is_wp_error( $res ) ) {
			break;
		}
		$err = $res; // Most often: the custom dimension is not registered yet. Try pagePath.
	}
	if ( is_wp_error( $res ) ) {
		return $err;
	}
	$skip = array_map( fn( $d ) => str_replace( '-', '', $d ), AW_SI_GA4_SKIP_DAYS );
	$out  = array();
	foreach ( $res as $r ) {
		$path = aw_si_landing_path( (string) ( $r[0][0] ?? '' ) );
		if ( '' === $path || in_array( (string) ( $r[0][1] ?? '' ), $skip, true ) ) {
			continue;
		}
		$path         = mb_substr( $path, 0, 255 );
		$out[ $path ] = ( $out[ $path ] ?? 0 ) + (float) ( $r[1][0] ?? 0 );
	}
	return array(
		'dim'       => $dim,
		'rows'      => $out,
		'truncated' => count( $res ) >= AW_SI_GA4_ROWS,
	);
}

/**
 * Store a 28-day 404 window, replacing the earlier one.
 *
 * @param string $end  Last day of the window (Y-m-d).
 * @param array  $rows Path => events.
 * @return int|WP_Error Rows written.
 */
function aw_si_ga4_store_nf( string $end, array $rows ) {
	global $wpdb;
	$obs = array();
	foreach ( $rows as $path => $n ) {
		$obs[] = array( 0, (string) $path, md5( 'nf|' . $path ), 'nf', (float) $n, (string) $path );
	}
	$n = aw_si_obs_replace_day( 'ga4', $end, $obs, '28d' );
	if ( ! is_wp_error( $n ) ) {
		$o = aw_si_table( 'obs' );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$o} WHERE src = 'ga4' AND metric = 'nf' AND period = '28d' AND d <> %s", $end ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	}
	return $n;
}

/**
 * A day's new GA4 rows without referral rows, plus the referral rows already stored for that day (a referral report
 * failed, so the day's replace must not delete them).
 *
 * @param string $day  Date (Y-m-d).
 * @param array  $rows New rows, as aw_si_ga4_obs_rows().
 */
function aw_si_ga4_keep_ref( string $day, array $rows ): array {
	global $wpdb;
	$o    = aw_si_table( 'obs' );
	$rows = array_values( array_filter( $rows, fn( $r ) => ! in_array( $r[3], array( 'ref', 'ref_eng', 'ref_key' ), true ) ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT kw_id, page, page_key, metric, value, txt FROM {$o} WHERE src = 'ga4' AND period = 'day' AND d = %s AND metric IN ('ref', 'ref_eng', 'ref_key')", $day ) ) as $r ) {
		$rows[] = array( (int) $r->kw_id, (string) $r->page, (string) $r->page_key, (string) $r->metric, (float) $r->value, (string) $r->txt );
	}
	return $rows;
}

/**
 * Site path of a GA4 landing page ('/news/x/?utm…' → '/news/x/'), or '' for "(not set)".
 *
 * @param string $lp Landing page as GA4 reports it.
 */
function aw_si_landing_path( string $lp ): string {
	if ( '' === $lp || '(' === $lp[0] ) {
		return '';
	}
	return aw_si_path( 0 === strpos( $lp, '/' ) ? home_url( $lp ) : $lp );
}

/**
 * Replace one day's rows of a source with new ones, in one transaction (a failed write keeps the old rows).
 *
 * @param string $src    Source key in obs.
 * @param string $day    Date (Y-m-d).
 * @param array  $rows   Each [kw_id, page, page_key, metric, value, txt].
 * @param string $period Period of the rows: day, or 28d for a 28-day window ending on $day.
 * @return int|WP_Error Rows written.
 */
function aw_si_obs_replace_day( string $src, string $day, array $rows, string $period = 'day' ) {
	global $wpdb;
	$o   = aw_si_table( 'obs' );
	$now = aw_si_now();
	$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$ok = false !== $wpdb->query( $wpdb->prepare( "DELETE FROM {$o} WHERE src = %s AND period = %s AND d = %s", $src, $period, $day ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	foreach ( array_chunk( $rows, 300 ) as $chunk ) {
		$place = array();
		$args  = array();
		foreach ( $chunk as $v ) {
			$place[] = '(%d, %s, %s, %s, %s, %s, %s, %f, %s, %s)';
			array_push( $args, (int) $v[0], mb_substr( (string) $v[1], 0, 255 ), (string) $v[2], $src, (string) $v[3], $period, $day, (float) $v[4], mb_substr( (string) $v[5], 0, 255 ), $now );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- one placeholder group per row, built above.
		$ok = $ok && false !== $wpdb->query( $wpdb->prepare( "INSERT INTO {$o} (kw_id, page, page_key, src, metric, period, d, value, txt, fetched) VALUES " . implode( ', ', $place ), $args ) );
		if ( ! $ok ) {
			break;
		}
	}
	if ( ! $ok ) {
		$error = $wpdb->last_error;
		$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		/* translators: 1: source, 2: date, 3: database error. */
		return new WP_Error( 'aw_si_store', sprintf( __( '%1$s rows for %2$s could not be saved, so the earlier rows were kept: %3$s', 'aw-seo-extras' ), $src, $day, $error ) );
	}
	$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return count( $rows );
}

/**
 * The daily GA4 run. Never throws; failures go to the run log and the stored data stays.
 *
 * @param array $args days (override), dry (fetch only).
 * @return array Report: days, rows, error.
 */
function aw_si_ga4_run( array $args = array() ): array {
	$dry  = ! empty( $args['dry'] );
	$days = $args['days'] ?? array_map( fn( $i ) => ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( "-{$i} days" )->format( 'Y-m-d' ), range( 1, AW_SI_GA4_DAYS ) );
	if ( ! $dry ) {
		aw_si_run_start( 'ga4' );
	}
	$rep = aw_si_with_module(
		'analytics-4',
		function ( $module ) use ( $days, $dry ) {
			$rep = array(
				'days'  => array(),
				'rows'  => 0,
				'error' => '',
			);
			foreach ( $days as $day ) {
				$data = aw_si_ga4_fetch_day( $module, $day );
				if ( is_wp_error( $data ) ) {
					/* translators: 1: date, 2: error message. */
					$rep['error'] = sprintf( __( 'GA4 could not be read for %1$s: %2$s', 'aw-seo-extras' ), $day, $data->get_error_message() );
					break;
				}
				$rows = aw_si_ga4_obs_rows( $data );
				if ( ! empty( $data['warnings'] ) && ! $dry ) {
					$rows = aw_si_ga4_keep_ref( $day, $rows ); // A referral report failed: the day's earlier referral rows stay.
				}
				$n    = $dry ? count( $rows ) : aw_si_obs_replace_day( 'ga4', $day, $rows );
				if ( is_wp_error( $n ) ) {
					$rep['error'] = $n->get_error_message();
					break;
				}
				foreach ( array( 'search', 'ai', 'organic', 'ref', 'social' ) as $key ) {
					if ( count( $data[ $key ] ) >= AW_SI_GA4_ROWS ) {
						$rep['truncated'][ $day ][] = $key;
					}
				}
				if ( ! empty( $data['warnings'] ) ) {
					$rep['warnings'][ $day ] = $data['warnings'];
				}
				$rep['days'][ $day ] = array(
					'search'  => count( $data['search'] ),
					'ai'      => count( $data['ai'] ),
					'organic' => count( $data['organic'] ),
					'ref'     => count( $data['ref'] ) + count( $data['social'] ),
				);
				$rep['rows']        += $n;
				if ( $dry ) {
					$rep['sample'][ $day ] = array_slice( $rows, 0, 5 );
				}
			}
			if ( '' === $rep['error'] ) {
				// 404s, 28 days to yesterday. A failure is a warning: the earlier window stays.
				$end = ( new DateTimeImmutable( 'yesterday', wp_timezone() ) )->format( 'Y-m-d' );
				$nf  = aw_si_ga4_fetch_nf( $module, $end );
				if ( ! is_wp_error( $nf ) && ! $dry ) {
					$w  = aw_si_ga4_store_nf( $end, $nf['rows'] );
					$nf = is_wp_error( $w ) ? $w : $nf;
				}
				if ( is_wp_error( $nf ) ) {
					$rep['nf_error'] = $nf->get_error_message();
				} else {
					$rep['nf'] = array(
						'dim'       => $nf['dim'],
						'paths'     => count( $nf['rows'] ),
						'truncated' => $nf['truncated'],
					);
					if ( $dry ) {
						$rep['sample']['nf'] = array_slice( $nf['rows'], 0, 5, true );
					}
				}
			}
			return $rep;
		}
	);
	if ( is_wp_error( $rep ) ) {
		$rep = array( 'error' => $rep->get_error_message() );
	}
	if ( ! $dry ) {
		if ( ! empty( $rep['error'] ) ) {
			aw_si_run_fail( 'ga4', $rep['error'] );
		} else {
			aw_si_run_ok(
				'ga4',
				array(
					'rows'      => $rep['rows'],
					'truncated' => $rep['truncated'] ?? array(),
					'warnings'  => $rep['warnings'] ?? array(),
					'up_to'     => $rep['days'] ? max( array_keys( $rep['days'] ) ) : '',
					'nf'        => $rep['nf'] ?? array(),
				)
			);
			if ( ! empty( $rep['warnings'] ) ) {
				/* translators: 1: dates, 2: GA4's error. */
				aw_si_run_fail( 'ga4', sprintf( __( 'GA4 referral figures could not be read for %1$s (the earlier figures were kept): %2$s', 'aw-seo-extras' ), implode( ', ', array_keys( $rep['warnings'] ) ), (string) reset( $rep['warnings'] )[0] ) );
			}
			if ( ! empty( $rep['nf_error'] ) ) {
				/* translators: %s: GA4's error. */
				aw_si_run_fail( 'ga4', sprintf( __( 'GA4 404 figures could not be read (the earlier figures were kept): %s', 'aw-seo-extras' ), $rep['nf_error'] ) );
			}
		}
	}
	return $rep;
}
add_action( AW_SI_GA4_CRON, fn() => get_option( 'aw_si_off' ) ? null : aw_si_ga4_run() );
