<?php
/**
 * The Dashboard's module cards: one per module, with its on/off switch, under rankweb/v1:
 *
 *   GET modules  manage_rankweb  each module: label, what it does, switchable, on, its Settings group, its next job run
 *
 * The switch saves through POST settings/modules (rankweb-rest.php, manage_options), which keeps every other switch as
 * it is. A module switched off is not loaded on the next request and its scheduled job is taken off (aw-seo-extras.php).
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			RANKWEB_REST,
			'/modules',
			array(
				'methods'             => 'GET',
				'permission_callback' => rankweb_rest_can( RANKWEB_CAP ),
				'callback'            => fn() => rest_ensure_response( array( 'modules' => rankweb_module_cards() ) ),
			)
		);
	}
);

/** The scheduled job of each module that has one (the same hooks aw-seo-extras.php takes off a module switched off). */
function rankweb_module_jobs(): array {
	return array(
		'titles'         => 'aw_seo_fill_daily',
		'health'         => 'aw_seo_endpoints_watchdog',
		'sitemap'        => 'aw_seo_sitemap_daily',
		'internal-links' => 'aw_link_index_build',
	);
}

/** One card per module. Read only: nothing is counted here. */
function rankweb_module_cards(): array {
	$admin = current_user_can( 'manage_options' );
	$jobs  = rankweb_module_jobs();
	$cards = array();
	foreach ( aw_seo_modules() as $key => $mod ) {
		$on      = aw_seo_module_on( $key );
		$next    = isset( $jobs[ $key ] ) && $on ? wp_next_scheduled( $jobs[ $key ] ) : false;
		$tab     = aw_seo_module_tab( $key );
		$cards[] = array(
			'key'         => $key,
			'label'       => $mod['label'],
			'desc'        => $mod['desc'] ?? '',
			'switch'      => ! empty( $mod['switch'] ),
			'on'          => $on,
			'settingsUrl' => $admin && 'modules' !== $tab ? aw_seo_settings_url( $tab ) : '',
			'next'        => $next ? (int) $next : 0,
		);
	}
	return $cards;
}
