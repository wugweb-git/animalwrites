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

/**
 * Where each module is set up besides RankWeb's own Settings, as parts: { label, url } is a link, { text } is words.
 * Most are Rank Math's screens (the module adds to Rank Math; Rank Math holds the rest).
 *
 * @return array key => parts[].
 */
function rankweb_module_where(): array {
	$rm      = fn( string $label, string $page ) => array(
		'label' => $label,
		'url'   => add_query_arg( 'page', $page, admin_url( 'admin.php' ) ),
	);
	$titles  = array( $rm( __( 'Rank Math → Titles & Meta', 'aw-seo-extras' ), 'rank-math-options-titles' ) );
	$general = array( $rm( __( 'Rank Math → General Settings', 'aw-seo-extras' ), 'rank-math-options-general' ) );
	return array(
		'titles'           => array_merge( $titles, array( array( 'text' => __( 'for single items', 'aw-seo-extras' ) ) ) ),
		'canonical'        => $titles,
		'robots'           => $titles,
		'filtered'         => $titles,
		'sitemap'          => array( $rm( __( 'Rank Math → Sitemap Settings', 'aw-seo-extras' ), 'rank-math-options-sitemap' ) ),
		'llms'             => $general,
		'breadcrumbs'      => $general,
		'redirects'        => array( $rm( __( 'Rank Math → Redirections', 'aw-seo-extras' ), 'rank-math-redirections' ) ),
		'redirect-tools'   => array( $rm( __( 'Rank Math → Redirections', 'aw-seo-extras' ), 'rank-math-redirections' ) ),
		'schema'           => array( array( 'text' => __( 'Each item’s Rank Math Schema tab (read-only for these types)', 'aw-seo-extras' ) ) ),
		'rank-math-schema' => array( array( 'text' => __( 'Each item’s Rank Math Schema tab (read-only for these types)', 'aw-seo-extras' ) ) ),
		'opengraph'        => array( array( 'text' => __( 'Each item’s Rank Math Social tab', 'aw-seo-extras' ) ) ),
		'focus-keywords'   => array( array( 'text' => __( 'Each item’s Rank Math box', 'aw-seo-extras' ) ) ),
		'analysis'         => array( array( 'text' => __( 'Rank Math box in the editor', 'aw-seo-extras' ) ) ),
		'faq'              => array(
			array(
				'label' => __( 'RankWeb → FAQ', 'aw-seo-extras' ),
				'url'   => add_query_arg( 'page', 'aw-faq', admin_url( 'admin.php' ) ),
			),
			array( 'text' => __( 'each item’s FAQ box', 'aw-seo-extras' ) ),
		),
	);
}

/**
 * A module's status in words, from the checks that already run (stored results; nothing is counted here).
 *
 * @param string $key Module key.
 * @return array { text, warn }.
 */
function rankweb_module_status( string $key ): array {
	$text = '';
	if ( in_array( $key, array( 'sitemap', 'llms', 'health' ), true ) && function_exists( 'aw_seo_overview_endpoints_status' ) ) {
		$text = aw_seo_overview_endpoints_status();
	} elseif ( 'faq' === $key && function_exists( 'aw_seo_overview_faq_status' ) ) {
		$text = aw_seo_overview_faq_status();
	}
	return array(
		'text' => trim( wp_strip_all_tags( $text ) ),
		'warn' => false !== strpos( $text, 'dashicons-warning' ),
	);
}

/** One card per module. Read only: nothing is counted here. */
function rankweb_module_cards(): array {
	$admin = current_user_can( 'manage_options' );
	$jobs  = rankweb_module_jobs();
	$cards = array();
	$where = rankweb_module_where();
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
			'where'       => $where[ $key ] ?? array(),
			'status'      => $on ? rankweb_module_status( $key ) : array(
				'text' => '',
				'warn' => false,
			),
		);
	}
	return $cards;
}
