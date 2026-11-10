<?php
/**
 * MailWeb in the Kits admin: a tab on the Kits screens and a row in Kits → Welcome's System Status, through Kits
 * Core's filters kits_admin_tabs and kits_status_rows. Only when Kits Core is loaded; without it nothing here runs and
 * the Mail menu works as before.
 *
 * @package mailweb
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
		add_filter( 'kits_admin_tabs', 'mailweb_kits_tab' );
		add_filter( 'kits_status_rows', 'mailweb_kits_status_row' );
	},
	20
);

/**
 * The MailWeb tab: Mail → Overview.
 *
 * @param array $tabs Kits tabs: slug => [label, address relative to wp-admin, capability].
 */
function mailweb_kits_tab( $tabs ) {
	$tabs['mailweb-overview'] = array( __( 'MailWeb', 'mailweb' ), 'admin.php?page=mailweb-overview', MAILWEB_CAP );
	return $tabs;
}

/**
 * The MailWeb row of the System Status table.
 *
 * @param array $rows Rows: [label, HTML value].
 */
function mailweb_kits_status_row( $rows ) {
	/* translators: %s: plugin version. */
	$text   = sprintf( __( 'Active, %s', 'mailweb' ), MAILWEB_VERSION );
	$rows[] = array( __( 'MailWeb', 'mailweb' ), esc_html( $text ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=mailweb-overview' ) ) . '">' . esc_html__( 'Overview', 'mailweb' ) . '</a>' );
	return $rows;
}
