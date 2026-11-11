<?php
/**
 * SourceWeb in the Kits admin: a row in Kits → Home's System Status, through Kits
 * Core's filter kits_status_rows. Only when Kits Core is loaded; without it nothing here runs and
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
		add_filter( 'kits_status_rows', 'aw_sourcing_kits_status_row' );
	},
	20
);

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
