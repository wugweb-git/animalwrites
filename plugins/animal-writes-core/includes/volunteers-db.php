<?php
/**
 * Volunteers table: the 1.0 campaign volunteers and the 2.0 sign-ups up to 24 Oct 2026, kept as a read-only backup.
 *
 * FluentCRM is the one volunteers table (owner, 24 Oct 2026): sign-ups write there only (volunteers.php) and the
 * readers use it (aw_crm_volunteers() in fluentcrm.php). Nothing writes to {prefix}aw_volunteers any more; its rows
 * were copied into FluentCRM with `wp aw vol-to-crm` (fills gaps, overwrites nothing). "Remove my data" still
 * deletes a person's row (privacy-requests.php). It has no admin screen (owner, 25 Oct 2026); read it with WP-CLI.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_VOL_DB_VERSION = '2'; // 2: page_source and pincode columns (S5.8, S6.6)
const AW_VOL_CAP        = 'edit_aw_volunteers';

/** Table name. */
function aw_vol_table() {
	global $wpdb;
	return $wpdb->prefix . 'aw_volunteers';
}

add_action(
	'init',
	function () {
		if ( AW_VOL_DB_VERSION === get_option( 'aw_volunteers_db' ) ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t = aw_vol_table();
		dbDelta(
			"CREATE TABLE {$t} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL,
			phone varchar(32) NOT NULL DEFAULT '',
			state_id bigint(20) unsigned NULL,
			district_id bigint(20) unsigned NULL,
			city_text varchar(190) NOT NULL DEFAULT '',
			pincode varchar(6) NOT NULL DEFAULT '',
			page_source varchar(120) NOT NULL DEFAULT '',
			role varchar(255) NOT NULL DEFAULT '',
			source varchar(20) NOT NULL,
			consent_scope varchar(40) NOT NULL,
			consent_text_version varchar(40) NOT NULL DEFAULT '',
			consent_at datetime NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email),
			KEY source (source),
			KEY status (status),
			KEY state_id (state_id)
			) {$wpdb->get_charset_collate()};"
		);
		update_option( 'aw_volunteers_db', AW_VOL_DB_VERSION, false );
	},
	5
);

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
