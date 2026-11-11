<?php
/**
 * InsightWeb in the Kits admin and on the Plugins screen: a row in Kits → Home's System
 * Status (Kits Core's filter kits_status_rows), and a Search Insights link under the plugin's
 * name. The screen itself is unchanged; its menu item is Plugins → InsightWeb (11 Nov 2026).
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'plugins_loaded',
	function () {
		if ( ! function_exists( 'kits_admin_tabs' ) ) {
			return;
		}
		add_filter( 'kits_status_rows', 'insightweb_kits_status_row' );
	},
	20 // after the bootstrap (5), which loads the screen.
);

// Plugins screen: Search Insights under the plugin's name.
add_filter(
	'plugin_action_links_' . plugin_basename( INSIGHTWEB_FILE ),
	function ( $links ) {
		if ( function_exists( 'aw_si_url' ) && current_user_can( aw_si_cap() ) ) {
			array_unshift( $links, '<a href="' . esc_url( aw_si_url() ) . '">' . esc_html__( 'Search Insights', 'aw-seo-extras' ) . '</a>' );
		}
		return $links;
	}
);

/**
 * The InsightWeb row of the System Status table.
 *
 * @param array $rows Rows: [label, HTML value].
 */
function insightweb_kits_status_row( $rows ) {
	/* translators: %s: plugin version. */
	$text = sprintf( __( 'Active, %s', 'aw-seo-extras' ), INSIGHTWEB_VERSION );
	if ( ! function_exists( 'aw_si_url' ) ) {
		$fix    = __( 'Does nothing until Kits Core, Animal Writes Core and Rank Math SEO are active.', 'aw-seo-extras' );
		$rows[] = array( __( 'InsightWeb', 'aw-seo-extras' ), function_exists( 'kits_status_cell' ) ? kits_status_cell( $text, $fix ) : esc_html( $text . '. ' . $fix ) );
		return $rows;
	}
	$rows[] = array( __( 'InsightWeb', 'aw-seo-extras' ), esc_html( $text ) . ' <a href="' . esc_url( aw_si_url() ) . '">' . esc_html__( 'Search Insights', 'aw-seo-extras' ) . '</a>' );
	return $rows;
}
