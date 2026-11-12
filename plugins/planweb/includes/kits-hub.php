<?php
/**
 * PlanWeb in the Kits admin: a row in Kits → Home's System Status, through Kits Core's
 * filter kits_status_rows. Kits Core is checked when it runs (it may be loaded by another
 * plugin rather than activated, so the Requires Plugins header is not used).
 *
 * @package planweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'plugins_loaded',
	function () {
		if ( ! function_exists( 'kits_admin_tabs' ) ) {
			add_action( 'admin_notices', 'planweb_no_kits_notice' );
			return;
		}
		add_filter( 'kits_status_rows', 'planweb_kits_status_row' );
		add_filter( 'kits_settings_transfer_options', 'planweb_kits_export' );
	},
	20
);

/**
 * Kits → Tools export: the one option PlanWeb's Settings saves (the switch). Entries are content, not settings.
 *
 * @param array $names Option names.
 */
function planweb_kits_export( $names ) {
	$names[] = 'planweb_on';
	return $names;
}

/** Without Kits Core: one notice, on PlanWeb's own screen only. */
function planweb_no_kits_notice() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && 'toplevel_page_' . PLANWEB_MENU === $screen->id ) {
		wp_admin_notice( esc_html__( 'PlanWeb is a Kits plugin: Kits Core is not loaded, so it is not listed under Kits.', 'planweb' ), array( 'type' => 'warning', 'dismissible' => true ) );
	}
}

/**
 * The PlanWeb row of the System Status table: on or off, and the import check.
 *
 * @param array $rows Rows: [label, HTML value].
 */
function planweb_kits_status_row( $rows ) {
	/* translators: %s: plugin version. */
	$text = sprintf( __( 'Active, %s', 'planweb' ), PLANWEB_VERSION );
	if ( ! planweb_on() ) {
		$rows[] = array( __( 'PlanWeb', 'planweb' ), esc_html( $text . '. ' . __( 'Switched off (Appearance → Kits → Modules); Appearance → Kits → Dates is in use.', 'planweb' ) ) );
		return $rows;
	}
	$v    = planweb_verify();
	$bad  = $v['differs'] + $v['missing'] + $v['extra'];
	$note = get_option( 'planweb_import_done' )
		/* translators: %d: number of dates that do not match. */
		? ( $bad ? sprintf( _n( 'Import check: %d date does not match.', 'Import check: %d dates do not match.', $bad, 'planweb' ), $bad ) : __( 'Import check: every date matches.', 'planweb' ) )
		: __( 'Import not run yet.', 'planweb' );
	$link   = ' <a href="' . esc_url( ( function_exists( 'planweb_ui_url' ) ? planweb_ui_url( 'settings' ) : planweb_url() ) . '#import' ) . '">' . esc_html__( 'Calendar', 'planweb' ) . '</a>';
	$rows[] = array( __( 'PlanWeb', 'planweb' ), ( function_exists( 'kits_status_cell' ) && $bad ? kits_status_cell( $text, $note ) : esc_html( $text . '. ' . $note ) ) . $link );
	return $rows;
}
