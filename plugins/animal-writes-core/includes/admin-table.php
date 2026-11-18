<?php
/**
 * aw_core_simple_table(): the plugin's plain admin tables as a core list table (admin UI is
 * core WordPress only). A thin back-compat wrapper over Kits Core's generic kits_simple_table()
 * so the two identical list-table classes are no longer kept in parallel (Kits Core is always
 * loaded; animal-writes-core.php requires kits-core.php before this file). Callers are unchanged.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Print a read-only core list table.
 *
 * @param string $plural Table name (CSS class; prefix of its sort and header ids).
 * @param array  $cols   Column => label ('cb' => '<input type="checkbox">' for a checkbox column).
 * @param array  $rows   Rows of escaped HTML cells, column => HTML (_sort: column => raw value; _after: a row printed under it).
 * @param array  $opts   per_page, total (rows are already that page), primary, fixed, empty, foot, sortable, caption.
 */
function aw_core_simple_table( $plural, array $cols, array $rows, array $opts = array() ) {
	kits_simple_table( $plural, $cols, $rows, $opts );
}
