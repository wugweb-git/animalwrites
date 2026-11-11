<?php
/**
 * PlanWeb in the Kits admin: a row on Kits → Plugins and a row in Kits → Welcome's System Status, through Kits Core's
 * filters kits_plugin_screens and kits_status_rows. Kits Core is checked when it runs (it may be loaded by another
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
		add_filter( 'kits_plugin_screens', 'planweb_kits_tab' );
		add_filter( 'kits_status_rows', 'planweb_kits_status_row' );
	},
	20
);

/** Without Kits Core: one notice, on PlanWeb's own screen only. */
function planweb_no_kits_notice() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && 'toplevel_page_' . PLANWEB_MENU === $screen->id ) {
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'PlanWeb is a Kits plugin: Kits Core is not loaded, so it is not listed under Kits.', 'planweb' ) . '</p></div>';
	}
}

/**
 * The PlanWeb row on Kits → Plugins.
 *
 * @param array $tabs slug => [name, address relative to wp-admin, capability, what it is].
 */
function planweb_kits_tab( $tabs ) {
	if ( planweb_on() ) {
		$tabs[ PLANWEB_MENU ] = array( __( 'PlanWeb', 'planweb' ), 'admin.php?page=' . PLANWEB_MENU, 'edit_planweb_entries', __( 'The team\'s calendar: dates, plans and reminders.', 'planweb' ) );
	}
	return $tabs;
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
		$rows[] = array( __( 'PlanWeb', 'planweb' ), esc_html( $text . '. ' . __( 'Switched off (Kits → Modules); Kits → Dates is in use.', 'planweb' ) ) );
		return $rows;
	}
	$v    = planweb_verify();
	$bad  = $v['differs'] + $v['missing'] + $v['extra'];
	$note = get_option( 'planweb_import_done' )
		/* translators: %d: number of dates that do not match. */
		? ( $bad ? sprintf( _n( 'Import check: %d date does not match.', 'Import check: %d dates do not match.', $bad, 'planweb' ), $bad ) : __( 'Import check: every date matches.', 'planweb' ) )
		: __( 'Import not run yet.', 'planweb' );
	$link   = ' <a href="' . esc_url( planweb_url() . '#planweb-import' ) . '">' . esc_html__( 'Calendar', 'planweb' ) . '</a>';
	$rows[] = array( __( 'PlanWeb', 'planweb' ), ( function_exists( 'kits_status_cell' ) && $bad ? kits_status_cell( $text, $note ) : esc_html( $text . '. ' . $note ) ) . $link );
	return $rows;
}
