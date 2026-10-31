<?php
/**
 * Volunteers table: the 1.0 campaign volunteers and the 2.0 sign-ups up to 24 Oct 2026, kept as a read-only backup.
 *
 * FluentCRM is the one volunteers table (owner, 24 Oct 2026): sign-ups write there only (volunteers.php) and the
 * readers use it. Nothing writes to {prefix}aw_volunteers any more; its rows were copied into FluentCRM once with
 * `wp aw vol-to-crm` (removed 25 Oct 2026, see git history). Nothing creates or upgrades the table any more (owner,
 * 25 Oct 2026). Removal requests are handled by hand (owner, 31 Oct 2026). It has no admin screen (owner,
 * 25 Oct 2026); read it with WP-CLI.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_VOL_CAP = 'edit_aw_volunteers';

/** Table name. */
function aw_vol_table() {
	global $wpdb;
	return $wpdb->prefix . 'aw_volunteers';
}

// The old post-based screen is replaced by the table.
add_filter(
	'register_post_type_args',
	function ( $args, $type ) {
		if ( 'aw_volunteer' === $type ) {
			$args['show_ui'] = false;
		}
		return $args;
	},
	10,
	2
);
