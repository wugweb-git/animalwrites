<?php
/**
 * REST: the Overview dashboard reads its numbers from `GET sourceweb/v1/stats`, so the chart rendering is a
 * thin client over a documented endpoint. When the suite's shared overview-charts component lands it consumes
 * this same endpoint — the data contract does not change with the renderer.
 *
 * @package sourceweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the stats route.
 *
 * @return void
 */
function sourceweb_rest_register() {
	register_rest_route(
		'sourceweb/v1',
		'/stats',
		array(
			'methods'             => 'GET',
			'callback'            => 'sourceweb_rest_stats',
			'permission_callback' => 'sourceweb_stats_rest_can',
			'args'                => array(
				'days' => array(
					'type'              => 'integer',
					'default'           => 30,
					'sanitize_callback' => 'absint',
					'validate_callback' => static function ( $value ) {
						$value = (int) $value;
						return $value >= 1 && $value <= 120;
					},
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'sourceweb_rest_register' );

/**
 * Permission check: the caller must hold the plugin capability (same gate as the admin screens).
 *
 * @return bool
 */
function sourceweb_stats_rest_can() {
	return current_user_can( SOURCEWEB_CAP );
}

/**
 * The stats payload: per-metric daily series, window totals, period-over-period deltas, the dedupe rate and
 * the latest review-queue size — all for the requested window.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function sourceweb_rest_stats( $request ) {
	$days  = (int) $request->get_param( 'days' );
	$days  = $days >= 1 && $days <= 120 ? $days : 30;
	$today = gmdate( 'Y-m-d' );
	$stats = sourceweb_stats_get();

	$series = array();
	$deltas = array();
	foreach ( sourceweb_stats_metrics() as $metric ) {
		$series[ $metric ] = sourceweb_stats_series( $stats, $metric, $days, $today );
		$deltas[ $metric ] = sourceweb_stats_period_delta( $stats, $metric, $days, $today );
	}

	return rest_ensure_response(
		array(
			'days'        => $days,
			'metrics'     => sourceweb_stats_metrics(),
			'series'      => $series,
			'totals'      => sourceweb_stats_totals( $stats, $days, $today ),
			'deltas'      => $deltas,
			'dedupe_rate' => sourceweb_stats_dedupe_rate( $stats, $days, $today ),
			'queue_size'  => sourceweb_stats_queue_latest( $stats, $today ),
			'generated'   => $today,
		)
	);
}
