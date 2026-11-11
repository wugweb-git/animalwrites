<?php
/**
 * RankWeb in the Kits admin: a row on Kits → Plugins and a row in Kits → Home's System Status, through Kits
 * Core's filters kits_plugin_screens and kits_status_rows. Only when Kits Core is loaded; without it nothing here runs and
 * the plugin's own menu works as before. Loaded whether or not Rank Math is active, so the row can say it is missing.
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
		add_filter( 'kits_plugin_screens', 'aw_seo_extras_kits_tab' );
		add_filter( 'kits_status_rows', 'aw_seo_extras_kits_status_row' );
	},
	20 // after the plugin's own plugins_loaded (5), which loads the menu.
);

/**
 * The RankWeb row on Kits → Plugins: its Overview, when the menu is there (it needs Rank Math, aw-seo-extras.php).
 *
 * @param array $tabs Kits plugin screens: slug => [name, address relative to wp-admin, capability, what it is].
 */
function aw_seo_extras_kits_tab( $tabs ) {
	if ( defined( 'AW_SEO_MENU' ) ) {
		$tabs[ AW_SEO_MENU ] = array( __( 'RankWeb', 'aw-seo-extras' ), 'admin.php?page=' . AW_SEO_MENU, 'manage_categories', __( 'What Rank Math doesn\'t do or keeps for Premium.', 'aw-seo-extras' ) );
	}
	return $tabs;
}

/**
 * The RankWeb row of the System Status table.
 *
 * @param array $rows Rows: [label, HTML value].
 */
function aw_seo_extras_kits_status_row( $rows ) {
	$head = get_file_data( AW_SEO_EXTRAS_DIR . 'aw-seo-extras.php', array( 'Version' => 'Version' ) );
	/* translators: %s: plugin version. */
	$text = sprintf( __( 'Active, %s', 'aw-seo-extras' ), $head['Version'] );
	if ( ! aw_seo_extras_rank_math() ) {
		$fix    = __( 'Does nothing until Rank Math SEO is active.', 'aw-seo-extras' );
		$rows[] = array( __( 'RankWeb', 'aw-seo-extras' ), function_exists( 'kits_status_cell' ) ? kits_status_cell( $text, $fix ) : esc_html( $text . '. ' . $fix ) );
		return $rows;
	}
	$link   = defined( 'AW_SEO_MENU' ) ? ' <a href="' . esc_url( admin_url( 'admin.php?page=' . AW_SEO_MENU ) ) . '">' . esc_html__( 'Overview', 'aw-seo-extras' ) . '</a>' : '';
	$rows[] = array( __( 'RankWeb', 'aw-seo-extras' ), esc_html( $text ) . $link );
	return $rows;
}
