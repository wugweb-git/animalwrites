<?php
/**
 * MailWeb in the Kits admin: a row in Kits → Home's System Status, through Kits
 * Core's filter kits_status_rows. Only when Kits Core is loaded; without it nothing here runs and
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
		add_filter( 'kits_status_rows', 'mailweb_kits_status_row' );
		add_filter( 'kits_settings_transfer_options', 'mailweb_kits_export' );
		foreach ( mailweb_kits_export( array() ) as $name ) {
			add_filter( 'sanitize_option_' . $name, 'mailweb_option_clean', 10, 2 );
		}
	},
	20
);

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

/**
 * Kits → Tools export: the settings the Settings screen saves, except the ones that hold a password or key
 * (mailweb_smtp, mailweb_api_token, mailweb_secret_*), which never leave the site.
 *
 * @param array $names Option names in the export.
 */
function mailweb_kits_export( $names ) {
	return array_merge( (array) $names, array( 'mailweb_cap', 'mailweb_mailboxes', 'mailweb_default_from', 'mailweb_aliases', 'mailweb_profiles', 'mailweb_crm' ) );
}
