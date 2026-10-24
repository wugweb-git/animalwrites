<?php
/**
 * Volunteers table: the 1.0 campaign volunteers and the 2.0 sign-ups up to 24 Oct 2026, kept as a read-only backup.
 *
 * FluentCRM is the one volunteers table (owner, 24 Oct 2026): sign-ups write there only (volunteers.php) and the
 * readers use it (aw_crm_volunteers() in fluentcrm.php). Nothing writes to {prefix}aw_volunteers any more; its rows
 * were copied into FluentCRM with `wp aw vol-to-crm` (fills gaps, overwrites nothing). Two things still touch it:
 * "Remove my data" deletes a person's row (privacy-requests.php), and administrators can view, filter and export it
 * at WP Admin → Volunteers (backup) (admin.php?page=aw-volunteers; hidden from the menu, admin-ia.php).
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

/* ---------- WP Admin: Volunteers (backup), read-only ---------- */

add_action(
	'admin_menu',
	function () {
		add_menu_page( 'Volunteers (backup)', 'Volunteers (backup)', AW_VOL_CAP, 'aw-volunteers', 'aw_vol_admin_page', 'dashicons-groups', 26 );
	}
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

/**
 * Filtered rows.
 *
 * @param array $f   Filters (s, source, status, state).
 * @param int   $lim Limit (0 = all).
 * @param int   $off Offset.
 * @return array{0: array, 1: int}
 */
function aw_vol_query( $f, $lim = 50, $off = 0 ) {
	global $wpdb;
	$t     = aw_vol_table();
	$where = array( '1=1' );
	$args  = array();
	if ( ! empty( $f['s'] ) ) {
		$like    = '%' . $wpdb->esc_like( $f['s'] ) . '%';
		$where[] = '(name LIKE %s OR email LIKE %s OR city_text LIKE %s)';
		array_push( $args, $like, $like, $like );
	}
	foreach ( array( 'source', 'status' ) as $k ) {
		if ( ! empty( $f[ $k ] ) ) {
			$where[] = "{$k} = %s";
			$args[]  = $f[ $k ];
		}
	}
	if ( ! empty( $f['state'] ) ) {
		$where[] = 'state_id = %d';
		$args[]  = (int) $f['state'];
	}
	$sql   = implode( ' AND ', $where );
	$total = (int) $wpdb->get_var( $args ? $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE {$sql}", $args ) : "SELECT COUNT(*) FROM {$t} WHERE {$sql}" ); // phpcs:ignore
	$q     = "SELECT * FROM {$t} WHERE {$sql} ORDER BY created_at DESC" . ( $lim ? $wpdb->prepare( ' LIMIT %d OFFSET %d', $lim, $off ) : '' );
	$rows  = $wpdb->get_results( $args ? $wpdb->prepare( $q, $args ) : $q, ARRAY_A ); // phpcs:ignore
	return array( $rows, $total );
}

/** Current filters from the request. */
function aw_vol_filters() {
	$g = wp_unslash( $_REQUEST ); // phpcs:ignore WordPress.Security.NonceVerification
	return array(
		's'      => sanitize_text_field( $g['s'] ?? '' ),
		'source' => in_array( $g['source'] ?? '', array( 'v1_campaign', 'v2_form' ), true ) ? $g['source'] : '',
		'status' => in_array( $g['status'] ?? '', array( 'active', 'unsubscribed', 'deleted' ), true ) ? $g['status'] : '',
		'state'  => absint( $g['state'] ?? 0 ),
	);
}

/** Admin list page (read-only). */
function aw_vol_admin_page() {
	if ( ! current_user_can( AW_VOL_CAP ) ) {
		return;
	}
	$f     = aw_vol_filters();
	$page  = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification
	list( $rows, $total ) = aw_vol_query( $f, 50, ( $page - 1 ) * 50 );
	$names = array();
	foreach ( aw_lgd()['ids'] as $t ) {
		$names[ $t->term_id ] = $t->name;
	}
	echo '<div class="wrap"><h1 class="wp-heading-inline">Volunteers (backup)</h1> <span class="subtitle">' . (int) $total . ' matching</span>';
	echo '<div class="notice notice-info inline"><p>Read-only backup since 24 Oct 2026: new sign-ups and changes are not shown here. Volunteers are kept in <a href="' . esc_url( admin_url( 'admin.php?page=fluentcrm-admin#/subscribers' ) ) . '">FluentCRM → Contacts</a>, tag Volunteer.</p></div>';
	echo '<form method="get"><input type="hidden" name="page" value="aw-volunteers"><p class="search-box"><input type="search" name="s" value="' . esc_attr( $f['s'] ) . '" placeholder="Name, email or city"> ';
	echo '<select name="source"><option value="">All sources</option><option value="v1_campaign"' . selected( $f['source'], 'v1_campaign', false ) . '>1.0 campaign</option><option value="v2_form"' . selected( $f['source'], 'v2_form', false ) . '>2.0 form</option></select> ';
	echo '<select name="status"><option value="">All statuses</option>';
	foreach ( array( 'active', 'unsubscribed', 'deleted' ) as $s ) {
		echo '<option value="' . esc_attr( $s ) . '"' . selected( $f['status'], $s, false ) . '>' . esc_html( ucfirst( $s ) ) . '</option>';
	}
	echo '</select> <select name="state"><option value="0">All states</option>';
	foreach ( aw_place_states() as $st ) {
		echo '<option value="' . (int) $st->term_id . '"' . selected( $f['state'], $st->term_id, false ) . '>' . esc_html( $st->name ) . '</option>';
	}
	echo '</select> ';
	submit_button( 'Filter', 'secondary', '', false );
	$export = wp_nonce_url( add_query_arg( array_merge( array( 'action' => 'aw_vol_export' ), array_filter( $f ) ), admin_url( 'admin-post.php' ) ), 'aw_vol_export' );
	echo ' <a class="button" href="' . esc_url( $export ) . '">Export CSV (' . (int) $total . ')</a></p></form>';
	$cells = array();
	foreach ( $rows as $r ) {
		$place   = trim( ( $r['district_id'] ? ( $names[ $r['district_id'] ] ?? '' ) . ', ' : '' ) . ( $r['state_id'] ? ( $names[ $r['state_id'] ] ?? '' ) : '' ), ', ' );
		$cells[] = array(
			'name'    => esc_html( $r['name'] ),
			'email'   => esc_html( $r['email'] ),
			'phone'   => esc_html( $r['phone'] ),
			'place'   => esc_html( $place ? $place : $r['city_text'] ),
			'role'    => esc_html( $r['role'] ),
			'source'  => esc_html( 'v1_campaign' === $r['source'] ? '1.0 campaign' : '2.0 form' ),
			'page'    => esc_html( (string) ( $r['page_source'] ?? '' ) ),
			'consent' => esc_html( $r['consent_scope'] ),
			'status'  => esc_html( $r['status'] ),
			'joined'  => esc_html( substr( (string) $r['created_at'], 0, 10 ) ),
		);
	}
	aw_core_simple_table(
		'volunteers',
		array( 'name' => 'Name', 'email' => 'Email', 'phone' => 'Phone', 'place' => 'Place', 'role' => 'Role', 'source' => 'Source', 'page' => 'Signed up on', 'consent' => 'Consent', 'status' => 'Status', 'joined' => 'Joined' ),
		$cells,
		array( 'per_page' => 50, 'total' => $total, 'empty' => 'No volunteers match.' )
	);
	echo '</div>';
}

add_action(
	'admin_post_aw_vol_export',
	function () {
		if ( ! current_user_can( AW_VOL_CAP ) || ! check_admin_referer( 'aw_vol_export' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		list( $rows ) = aw_vol_query( aw_vol_filters(), 0 );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="aw-volunteers-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore
		$cols = array( 'id', 'name', 'email', 'phone', 'state_id', 'district_id', 'city_text', 'pincode', 'page_source', 'role', 'source', 'consent_scope', 'consent_text_version', 'consent_at', 'status', 'created_at', 'updated_at' );
		fputcsv( $out, $cols );
		foreach ( $rows as $r ) {
			// Neutralise spreadsheet formulas in free text.
			$r = array_map( fn( $c ) => $r[ $c ] ?? '', $cols ); // same order as the header, whatever the table's column order
			fputcsv( $out, array_map( fn( $v ) => is_string( $v ) && preg_match( '/^[=+\-@]/', $v ) ? "'" . $v : $v, $r ) );
		}
		fclose( $out ); // phpcs:ignore
		exit;
	}
);
