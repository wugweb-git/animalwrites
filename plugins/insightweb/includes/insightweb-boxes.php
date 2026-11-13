<?php
/**
 * The Overview boxes on the Dashboard: GET insightweb/v1/dashboard/box (aw_si_cap()) runs the box's
 * own function (aw_si_overview_boxes()) and hands its output over as plain parts
 * the shell draws: paragraphs, notes, lists, tables and notices. Text and links only: no markup goes out, and a link
 * is kept only when it is http(s) or a relative address.
 *
 * @package insightweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'rest_api_init',
	function () {
		if ( ! function_exists( 'kits_shell_rest_can' ) ) {
			return;
		}
		register_rest_route(
			INSIGHTWEB_REST,
			'/dashboard/box',
			array(
				'methods'             => 'GET',
				'permission_callback' => kits_shell_rest_can( aw_si_cap() ),
				'callback'            => 'insightweb_rest_box',
				'args'                => array(
					'key' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}
);

/** The boxes the Dashboard shows below its tiles: [ { key, title } ]. */
function insightweb_box_list(): array {
	$out = array();
	foreach ( aw_si_overview_boxes() as $key => $box ) {
		$out[] = array(
			'key'   => $key,
			'title' => $box[0],
		);
	}
	return $out;
}

/**
 * GET dashboard/box: one box's parts.
 *
 * @param WP_REST_Request $request Request.
 */
function insightweb_rest_box( WP_REST_Request $request ) {
	$key   = sanitize_key( (string) $request['key'] );
	$boxes = aw_si_overview_boxes();
	if ( ! isset( $boxes[ $key ] ) || ! is_callable( $boxes[ $key ][1] ) ) {
		return new WP_Error( 'insightweb_no_box', __( 'No such box.', 'insightweb' ), array( 'status' => 404 ) );
	}
	if ( get_option( 'aw_si_off' ) ) {
		return rest_ensure_response(
			array(
				'key'    => $key,
				'title'  => $boxes[ $key ][0],
				'blocks' => array(
					array(
						'type' => 'p',
						'text' => __( 'Search Insights is off: its data was deleted. Settings → Run the daily update starts it again.', 'insightweb' ),
					),
				),
			)
		);
	}
	ob_start();
	call_user_func( $boxes[ $key ][1] );
	$html = (string) ob_get_clean();
	return rest_ensure_response(
		array(
			'key'    => $key,
			'title'  => $boxes[ $key ][0],
			'blocks' => insightweb_html_blocks( $html ),
		)
	);
}

/**
 * A box's output as plain parts (Kits Core's page parts, admin-shell-parts.php).
 *
 * @param string $html The box's output.
 * @return array[]
 */
function insightweb_html_blocks( string $html ): array {
	if ( function_exists( 'kits_shell_html_blocks' ) ) {
		return kits_shell_html_blocks( $html );
	}
	$t = trim( wp_strip_all_tags( $html ) );
	return '' === $t ? array() : array(
		array(
			'type' => 'p',
			'text' => $t,
		),
	);
}
