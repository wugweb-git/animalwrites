<?php
/**
 * SourceWeb in the Kits admin: a row on Kits → Plugins and a row in Kits → Welcome's System Status, through Kits
 * Core's filters kits_plugin_screens and kits_status_rows. Only when Kits Core is loaded; without it nothing here runs and
 * the Sourcing menu works as before.
 *
 * @package aw-sourcing
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
		add_filter( 'kits_plugin_screens', 'aw_sourcing_kits_tab' );
		add_filter( 'kits_status_rows', 'aw_sourcing_kits_status_row' );
	},
	20
);

/**
 * The SourceWeb row on Kits → Plugins: Sourcing → Overview.
 *
 * @param array $tabs Kits plugin screens: slug => [name, address relative to wp-admin, capability, what it is].
 */
function aw_sourcing_kits_tab( $tabs ) {
	$tabs[ AW_SRC_MENU ] = array( 'SourceWeb', 'admin.php?page=' . AW_SRC_MENU, AW_REVIEW_CAP, 'What the workers fetch and how it arrives: Sourcing → Overview.' );
	return $tabs;
}

/**
 * The SourceWeb row of the System Status table: version, how it is loaded, items waiting for review.
 *
 * @param array $rows Rows: [label, HTML value].
 */
function aw_sourcing_kits_status_row( $rows ) {
	$own  = function_exists( 'is_plugin_active' ) && is_plugin_active( plugin_basename( AW_SOURCING_DIR . 'aw-sourcing.php' ) ); // network-active too.
	$text = 'Active, ' . AW_SOURCING_LOADED . ( $own ? '' : ' (loaded by Animal Writes Core)' );
	if ( function_exists( 'aw_review_count' ) ) {
		$text .= '; ' . (int) aw_review_count() . ' to review';
	}
	$rows[] = array( 'SourceWeb', esc_html( $text ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=' . AW_SRC_MENU ) ) . '">' . esc_html( 'Overview' ) . '</a>' );
	return $rows;
}
