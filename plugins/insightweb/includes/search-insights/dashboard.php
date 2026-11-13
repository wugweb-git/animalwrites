<?php
/**
 * Dashboard: SEO (owner, 3 Nov 2026: one Dashboard box per custom plugin). Cards (Search Console clicks and
 * impressions for 28 days, 404s still open, sources with a problem), clicks per day, the sources with their last update
 * and status (problems first) and the 404s still open. Read-only: Search Insights' stored rows and run log; no remote
 * calls.
 *
 * @package insightweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Search Console site totals (obs rows src gsc, metric total) for the 28 days to the newest stored day, the 28 days
 * before, and the clicks per day. Anchored on the newest stored day, since Search Console runs a few days behind.
 *
 * @return array|null clicks, impr, prev_clicks, prev_impr (null unless all 28 earlier days are stored), days (Y-m-d => clicks, oldest first), end (Y-m-d); null without rows.
 */
function aw_seo_dash_gsc(): ?array {
	global $wpdb;
	if ( ! aw_si_ready() ) {
		return null;
	}
	$o   = aw_si_table( 'obs' );
	$end = (string) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(d) FROM %i WHERE src = 'gsc' AND metric = 'total' AND period = 'day'", $o ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	if ( '' === $end ) {
		return null;
	}
	$e    = new DateTimeImmutable( $end, wp_timezone() );
	$from = $e->modify( '-27 days' )->format( 'Y-m-d' );
	$back = $e->modify( '-55 days' )->format( 'Y-m-d' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT d, SUM(clicks) c, SUM(impr) i FROM %i WHERE src = 'gsc' AND metric = 'total' AND period = 'day' AND d BETWEEN %s AND %s GROUP BY d ORDER BY d", $o, $back, $end ) );
	$out  = array(
		'clicks'      => 0,
		'impr'        => 0,
		'prev_clicks' => 0,
		'prev_impr'   => 0,
		'days'        => array(),
		'end'         => $end,
	);
	$prev = 0;
	foreach ( $rows as $r ) {
		if ( (string) $r->d >= $from ) {
			$out['clicks']                += (int) $r->c;
			$out['impr']                  += (int) $r->i;
			$out['days'][ (string) $r->d ] = (int) $r->c;
		} else {
			$out['prev_clicks'] += (int) $r->c;
			$out['prev_impr']   += (int) $r->i;
			++$prev;
		}
	}
	if ( $prev < 28 ) {
		$out['prev_clicks'] = null; // the earlier window is not fully stored: no change shown
		$out['prev_impr']   = null;
	}
	return $out;
}

/**
 * The paths in the stored 28-day 404 rows (GA4 404_view, as in the Overview's Not found box) that no redirect or old
 * slug leads away from now: the Overview's "Still 404" (aw_si_final_path()). Most views first.
 *
 * @return array[] path, views.
 */
function aw_seo_dash_nf_open(): array {
	global $wpdb;
	if ( ! aw_si_ready() ) {
		return array();
	}
	$o    = aw_si_table( 'obs' );
	$open = array();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT txt, value FROM %i WHERE src = 'ga4' AND metric = 'nf' AND period = '28d' ORDER BY value DESC, txt", $o ) ) as $r ) {
		$path = (string) $r->txt;
		if ( ! aw_si_nf_is_test( $path ) && untrailingslashit( aw_si_final_path( $path ) ) === untrailingslashit( $path ) ) {
			$open[] = array(
				'path'  => $path,
				'views' => (int) $r->value,
			);
		}
	}
	return $open;
}

/**
 * One row per source (aw_si_sources()), problems first. The same states as the Overview's Sources box and notices:
 * Failed (aw_si_run_problem()), Late (ran before, now aw_si_stale()), Refused (a rising source the service refuses,
 * aw_si_rising_blocked(): a known limit, not a problem), Not run, OK.
 *
 * @return array[] name, last (Unix or 0), status (failed, late, ok, refused, notrun), problem (bool).
 */
function aw_seo_dash_sources(): array {
	$runs = aw_si_runs();
	$rows = array();
	foreach ( aw_si_sources() as $src => $s ) {
		$ok  = (int) ( $runs[ $src ]['ok'] ?? 0 );
		$err = aw_si_run_problem( $src );
		if ( $err && aw_si_rising_blocked( $src ) ) {
			$status = 'refused';
		} elseif ( $err ) {
			$status = 'failed';
		} elseif ( ! $ok ) {
			$status = 'notrun';
		} elseif ( aw_si_stale( $src ) ) {
			$status = 'late';
		} else {
			$status = 'ok';
		}
		$rows[] = array(
			'name'    => (string) $s['label'],
			'last'    => $ok,
			'status'  => $status,
			'problem' => in_array( $status, array( 'failed', 'late' ), true ),
			'why'     => $err,
		);
	}
	$order = array_flip( array( 'failed', 'late', 'refused', 'notrun', 'ok' ) );
	usort( $rows, fn( $a, $b ) => $order[ $a['status'] ] <=> $order[ $b['status'] ] ); // stable: aw_si_sources() order within a status
	return $rows;
}

add_action(
	'wp_dashboard_setup',
	function () {
		if ( ! current_user_can( aw_si_cap() ) ) {
			return; // Search Insights' own capability
		}
		wp_add_dashboard_widget(
			'aw_seo_dashboard',
			__( 'SEO', 'insightweb' ),
			function () {
				$gsc     = aw_seo_dash_gsc();
				$nf      = aw_seo_dash_nf_open();
				$sources = aw_seo_dash_sources();
				$need    = count( array_filter( $sources, fn( $r ) => $r['problem'] ) );
				$before  = __( 'on the 28 days before', 'insightweb' );
				if ( function_exists( 'kits_dash_cards' ) ) {
					$cards = array(
						array(
							'label' => __( 'Clicks, 28 days', 'insightweb' ),
							'value' => $gsc ? number_format_i18n( $gsc['clicks'] ) : '–',
						),
						array(
							'label' => __( 'Impressions, 28 days', 'insightweb' ),
							'value' => $gsc ? number_format_i18n( $gsc['impr'] ) : '–',
						),
						array(
							'label' => __( '404s still open', 'insightweb' ),
							'value' => number_format_i18n( count( $nf ) ),
						),
						array(
							'label' => __( 'Sources with a problem', 'insightweb' ),
							'value' => number_format_i18n( $need ),
						),
					);
					if ( $gsc && null !== $gsc['prev_clicks'] ) {
						$cards[0] += array(
							'delta'      => $gsc['clicks'] - $gsc['prev_clicks'],
							'delta_text' => $before,
						);
						$cards[1] += array(
							'delta'      => $gsc['impr'] - $gsc['prev_impr'],
							'delta_text' => $before,
						);
					}
					echo kits_dash_cards( $cards ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
				}
				if ( $gsc ) {
					/* translators: %s: date. */
					echo '<p class="description">' . esc_html( sprintf( __( 'Search Console, the 28 days to %s.', 'insightweb' ), wp_date( 'j M Y', strtotime( $gsc['end'] ) ) ) ) . '</p>';
					if ( count( $gsc['days'] ) > 1 && function_exists( 'kits_dash_bars' ) ) {
						$days = array();
						foreach ( $gsc['days'] as $d => $c ) {
							$days[ wp_date( 'j M', strtotime( $d ) ) ] = $c;
						}
						echo kits_dash_bars( $days, __( 'Clicks per day, last 28 days', 'insightweb' ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside; true: the figures table under the chart.
					}
				}

				$status = array(
					'failed'  => array( 'dismiss', '#b32d2e', __( 'Failed', 'insightweb' ) ),
					'late'    => array( 'clock', '#b32d2e', __( 'Late', 'insightweb' ) ),
					'refused' => array( 'info-outline', '#50575e', __( 'Refused', 'insightweb' ) ),
					'notrun'  => array( 'minus', '#50575e', __( 'Not run', 'insightweb' ) ),
					'ok'      => array( 'yes-alt', '#007017', __( 'OK', 'insightweb' ) ),
				);
				/* translators: 1: number of sources, 2: how many have a problem. */
				$summary = sprintf( __( 'Sources (%1$s, %2$s with a problem)', 'insightweb' ), number_format_i18n( count( $sources ) ), number_format_i18n( $need ) );
				echo '<details' . ( $need ? ' open' : '' ) . '><summary>' . esc_html( $summary ) . '</summary>';
				$cells = array();
				foreach ( $sources as $r ) {
					$s       = $status[ $r['status'] ];
					$cells[] = array(
						'source' => '<div style="overflow-wrap:anywhere">' . esc_html( $r['name'] ) . '</div>',
						'last'   => esc_html( $r['last'] ? wp_date( 'j M, g:i a', $r['last'] ) : __( 'never', 'insightweb' ) ),
						'status' => '<span class="dashicons dashicons-' . esc_attr( $s[0] ) . '" style="color:' . esc_attr( $s[1] ) . '" aria-hidden="true"></span> ' . esc_html( $s[2] ),
					);
				}
				aw_si_simple_table(
					'seo-sources',
					array(
						'source' => esc_html__( 'Source', 'insightweb' ),
						'last'   => esc_html__( 'Last update', 'insightweb' ),
						'status' => esc_html__( 'Status', 'insightweb' ),
					),
					$cells,
					array( 'foot' => false ) // a short table in a Dashboard widget: no repeated header row
				);
				echo '</details>';

				/* translators: %s: number of paths. */
				echo '<details><summary>' . esc_html( sprintf( __( '404s still open (%s)', 'insightweb' ), number_format_i18n( count( $nf ) ) ) ) . '</summary>';
				if ( $nf ) {
					$cells = array();
					foreach ( array_slice( $nf, 0, 10 ) as $r ) {
						$cells[] = array(
							'path'  => '<div style="overflow-wrap:anywhere">' . esc_html( $r['path'] ) . '</div>',
							'views' => esc_html( number_format_i18n( $r['views'] ) ),
						);
					}
					aw_si_simple_table(
						'seo-404s',
						array(
							'path'  => esc_html__( 'Path', 'insightweb' ),
							'views' => esc_html__( 'Views', 'insightweb' ),
						),
						$cells,
						array( 'foot' => false )
					);
				} else {
					echo '<p>' . esc_html__( 'None.', 'insightweb' ) . '</p>';
				}
				echo '</details>';

				echo '<p><a href="' . esc_url( aw_si_url() ) . '">' . esc_html__( 'Search Insights', 'insightweb' ) . '</a></p>';
			}
		);
	},
	20
);
