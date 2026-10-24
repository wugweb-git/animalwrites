<?php
/**
 * mailweb_simple_table(): the plugin's plain admin tables as a core list table (admin UI is
 * core WordPress only). The class loads only when a screen draws one.
 *
 * @package mailweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Print a read-only core list table.
 *
 * @param string $plural Table name (CSS class).
 * @param array  $cols   Column => label ('cb' => '<input type="checkbox">' for a checkbox column).
 * @param array  $rows   Rows of escaped HTML cells, column => HTML.
 * @param array  $opts   per_page, total (rows are already that page), primary, fixed, empty.
 */
function mailweb_simple_table( $plural, array $cols, array $rows, array $opts = array() ) {
	if ( ! class_exists( 'WP_List_Table' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	}
	require_once __DIR__ . '/class-mailweb-simple-table.php';
	$t = new Mailweb_Simple_Table( $plural, $cols, $rows, $opts );
	$t->prepare_items();
	$t->display();
}
