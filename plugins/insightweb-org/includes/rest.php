<?php
/**
 * The insightweb/v1 REST route the admin Overview chart component reads: GET /overview returns the same figures the
 * server-rendered Overview shows (totals, the priority and status splits, the suggested-action split, the 28-day
 * trend and the top queries), so the shared overview-charts component can draw them with the admin shell's palette.
 *
 * Read-only and admin-only: the permission callback requires a logged-in user with InsightWeb's capability and a valid
 * REST nonce. No route here writes anything. One prefix: insightweb_.
 *
 * @package InsightWeb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the current request may read InsightWeb's admin data: a logged-in user with the capability. The REST nonce
 * is checked by core (rest_cookie_check_errors) for cookie-authenticated requests.
 *
 * @return bool
 */
function insightweb_rest_can(): bool {
	return is_user_logged_in() && current_user_can( insightweb_cap() );
}

add_action( 'rest_api_init', 'insightweb_register_routes' );

/**
 * Register the insightweb/v1 routes.
 *
 * @return void
 */
function insightweb_register_routes(): void {
	register_rest_route(
		INSIGHTWEB_REST,
		'/overview',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'insightweb_rest_overview',
			'permission_callback' => 'insightweb_rest_can',
		)
	);
}

/**
 * GET /overview: the Overview figures.
 *
 * @return WP_REST_Response
 */
function insightweb_rest_overview(): WP_REST_Response {
	return rest_ensure_response( insightweb_overview() );
}
