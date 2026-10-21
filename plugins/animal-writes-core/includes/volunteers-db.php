<?php
/**
 * Volunteers database: one table for 1.0 campaign volunteers and 2.0 sign-ups.
 *
 * Table {prefix}aw_volunteers (dbDelta). Email is unique and lowercased; a person who signs up again is updated,
 * not duplicated. Administrators only: WP Admin → Volunteers (list, filter, search, export CSV, delete).
 * 1.0 import: `wp aw-volunteers import --source=v1_campaign < volunteers.jsonl` (counts only, never prints rows).
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

/**
 * State and district term IDs for free-text city, matched against district names (and state names).
 *
 * @param string $city       Free text.
 * @param string $state_slug Optional state slug from a form.
 * @return array{0: int|null, 1: int|null}
 */
function aw_vol_place( $city, $state_slug = '' ) {
	static $districts = null, $states = null;
	if ( null === $districts ) {
		$districts = array();
		$states    = array();
		foreach ( array_merge( aw_place_states(), aw_lgd()['districts'] ) as $t ) {
			$key = preg_replace( '/[^a-z]/', '', strtolower( $t->name ) );
			if ( $t->parent ) {
				$districts[ $key ][] = $t;
			} else {
				$states[ $key ] = $t;
			}
		}
		// Common city names that are not district names.
		$alias = array( 'bangalore' => 'bengaluruurban', 'bengaluru' => 'bengaluruurban', 'bombay' => 'mumbai', 'gurgaon' => 'gurugram', 'calcutta' => 'kolkata', 'madras' => 'chennai', 'noida' => 'gautambuddhanagar', 'greaternoida' => 'gautambuddhanagar', 'mysore' => 'mysuru', 'trivandrum' => 'thiruvananthapuram', 'cochin' => 'ernakulam', 'kochi' => 'ernakulam', 'vizag' => 'visakhapatnam', 'secunderabad' => 'hyderabad', 'navimumbai' => 'thane', 'faridabad' => 'faridabad', 'ghaziabad' => 'ghaziabad', 'pondicherry' => 'puducherry', 'allahabad' => 'prayagraj', 'baroda' => 'vadodara', 'mohali' => 'sasnagar', 'newdelhi' => 'newdelhi', 'delhi' => 'newdelhi' );
		foreach ( $alias as $from => $to ) {
			if ( isset( $districts[ $to ] ) && ! isset( $districts[ $from ] ) ) {
				$districts[ $from ] = $districts[ $to ];
			}
		}
	}
	$state = $state_slug ? aw_place_by_slug( $state_slug ) : null;
	$key   = preg_replace( '/[^a-z]/', '', strtolower( (string) $city ) );
	$cands = $districts[ $key ] ?? array();
	if ( $state ) {
		$cands = array_values( array_filter( $cands, fn( $d ) => (int) $d->parent === (int) $state->term_id ) );
	}
	if ( 1 === count( $cands ) ) {
		return array( (int) $cands[0]->parent, (int) $cands[0]->term_id );
	}
	if ( $state ) {
		return array( (int) $state->term_id, null );
	}
	if ( isset( $states[ $key ] ) ) {
		return array( (int) $states[ $key ]->term_id, null );
	}
	return array( null, null );
}

/**
 * Insert or update a volunteer by email.
 *
 * @param array $v name, email, phone, city_text, state_slug, role, source, consent_scope, consent_text_version, consent_at, created_at.
 * @return string 'created' | 'updated' | 'invalid'
 */
function aw_vol_upsert( $v ) {
	global $wpdb;
	$email = strtolower( trim( (string) ( $v['email'] ?? '' ) ) );
	if ( ! is_email( $email ) ) {
		return 'invalid';
	}
	$t   = aw_vol_table();
	$now = current_time( 'mysql', true );
	list( $state_id, $district_id ) = aw_vol_place( (string) ( $v['city_text'] ?? '' ), (string) ( $v['state_slug'] ?? '' ) );
	$picked = ! empty( $v['district_slug'] ) ? aw_place_by_slug( (string) $v['district_slug'] ) : null; // chosen from the LGD list
	if ( $picked && 'district' === $picked->kind ) {
		list( $state_id, $district_id ) = array( (int) $picked->parent, (int) $picked->term_id );
	}
	$row = array(
		'name'                 => sanitize_text_field( (string) ( $v['name'] ?? '' ) ),
		'email'                => $email,
		'phone'                => preg_replace( '/[^0-9+ ]/', '', (string) ( $v['phone'] ?? '' ) ),
		'state_id'             => $state_id,
		'district_id'          => $district_id,
		'city_text'            => sanitize_text_field( (string) ( $v['city_text'] ?? '' ) ),
		'pincode'              => preg_replace( '/\D/', '', (string) ( $v['pincode'] ?? '' ) ),
		'page_source'          => sanitize_text_field( (string) ( $v['page_source'] ?? '' ) ),
		'role'                 => sanitize_text_field( (string) ( $v['role'] ?? '' ) ),
		'source'               => in_array( $v['source'] ?? '', array( 'v1_campaign', 'v2_form', 'claim' ), true ) ? $v['source'] : 'v2_form',
		'consent_scope'        => in_array( $v['consent_scope'] ?? '', array( 'campaign_1_0', 'volunteering', 'volunteering_and_updates', 'listing_claim' ), true ) ? $v['consent_scope'] : 'volunteering',
		'consent_text_version' => sanitize_text_field( (string) ( $v['consent_text_version'] ?? '' ) ),
		'consent_at'           => $v['consent_at'] ?? $now,
		'created_at'           => $v['created_at'] ?? $now,
		'updated_at'           => $now,
	);
	$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE email = %s", $email ), ARRAY_A ); // phpcs:ignore
	if ( ! $existing ) {
		$wpdb->insert( $t, $row + array( 'status' => 'active' ) ); // phpcs:ignore
		return 'created';
	}
	// Keep what is known; a newer sign-up fills gaps and widens consent (campaign_1_0 < volunteering < volunteering_and_updates).
	$rank = array( 'campaign_1_0' => 1, 'volunteering' => 2, 'volunteering_and_updates' => 3 );
	$upd  = array( 'updated_at' => $now );
	foreach ( array( 'name', 'phone', 'state_id', 'district_id', 'city_text', 'pincode', 'page_source', 'role' ) as $k ) {
		if ( '' !== (string) $row[ $k ] && null !== $row[ $k ] && ( 'v2_form' === $row['source'] || '' === (string) $existing[ $k ] ) ) {
			$upd[ $k ] = $row[ $k ];
		}
	}
	if ( ( $rank[ $row['consent_scope'] ] ?? 0 ) > ( $rank[ $existing['consent_scope'] ] ?? 0 ) ) {
		$upd['consent_scope']        = $row['consent_scope'];
		$upd['consent_text_version'] = $row['consent_text_version'];
		$upd['consent_at']           = $row['consent_at'];
	}
	if ( 'v2_form' === $row['source'] && 'active' !== $existing['status'] ) {
		$upd['status'] = 'active'; // signing up again re-activates
	}
	if ( strcmp( (string) $row['created_at'], (string) $existing['created_at'] ) < 0 ) {
		$upd['created_at'] = $row['created_at'];
	}
	$wpdb->update( $t, $upd, array( 'id' => (int) $existing['id'] ) ); // phpcs:ignore
	return 'updated';
}

/* ---------- One-time: 2.0 sign-ups stored as aw_volunteer posts move into the table ---------- */

add_action(
	'init',
	function () {
		if ( '1' === get_option( 'aw_volunteers_migrated' ) || AW_VOL_DB_VERSION !== get_option( 'aw_volunteers_db' ) ) {
			return;
		}
		$n = 0;
		foreach ( get_posts( array( 'post_type' => 'aw_volunteer', 'post_status' => 'any', 'numberposts' => -1 ) ) as $p ) {
			$state = aw_place_state_by_name( (string) get_post_meta( $p->ID, 'aw_v_state', true ) );
			aw_vol_upsert(
				array(
					'name'                 => get_post_meta( $p->ID, 'aw_v_name', true ),
					'email'                => get_post_meta( $p->ID, 'aw_v_email', true ),
					'phone'                => get_post_meta( $p->ID, 'aw_v_phone', true ),
					'city_text'            => get_post_meta( $p->ID, 'aw_v_city', true ),
					'state_slug'           => $state ? $state->slug : '',
					'role'                 => get_post_meta( $p->ID, 'aw_v_roles', true ),
					'source'               => 'v2_form',
					'consent_scope'        => 'volunteering',
					'consent_text_version' => 'v2-form-no-checkbox',
					'consent_at'           => get_gmt_from_date( $p->post_date ),
					'created_at'           => get_gmt_from_date( $p->post_date ),
				)
			);
			$n++;
		}
		update_option( 'aw_volunteers_migrated', '1', false );
		update_option( 'aw_volunteers_migrated_count', $n, false );
	},
	20
);

/* ---------- WP-CLI: 1.0 import (JSON lines on STDIN; prints counts only) ---------- */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'aw-volunteers import',
		function ( $args, $assoc ) {
			$source = $assoc['source'] ?? 'v1_campaign';
			$counts = array( 'read' => 0, 'created' => 0, 'updated' => 0, 'invalid' => 0, 'with_district' => 0 );
			$in     = fopen( 'php://stdin', 'r' ); // phpcs:ignore
			while ( ( $line = fgets( $in ) ) !== false ) { // phpcs:ignore
				$r = json_decode( $line, true );
				if ( ! is_array( $r ) ) {
					continue;
				}
				$counts['read']++;
				$res = aw_vol_upsert(
					array(
						'name'                 => $r['name'] ?? '',
						'email'                => $r['email'] ?? '',
						'phone'                => $r['phone'] ?? '',
						'city_text'            => $r['city'] ?? '',
						'role'                 => $r['role'] ?? '',
						'source'               => $source,
						'consent_scope'        => 'v1_campaign' === $source ? 'campaign_1_0' : 'volunteering',
						'consent_text_version' => 'v1_campaign' === $source ? 'aw1-volunteer-form' : 'v2-form-no-checkbox',
						'consent_at'           => $r['created_at'] ?? null,
						'created_at'           => $r['created_at'] ?? null,
					)
				);
				$counts[ $res ]++;
				if ( 'invalid' !== $res && aw_vol_place( (string) ( $r['city'] ?? '' ) )[1] ) {
					$counts['with_district']++;
				}
			}
			foreach ( $counts as $k => $v ) {
				WP_CLI::log( "{$k}: {$v}" );
			}
		}
	);
}

/* ---------- WP Admin: Volunteers ---------- */

add_action(
	'admin_menu',
	function () {
		add_menu_page( 'Volunteers', 'Volunteers', AW_VOL_CAP, 'aw-volunteers', 'aw_vol_admin_page', 'dashicons-groups', 26 );
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

/** Admin list page. */
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
	echo '<div class="wrap"><h1 class="wp-heading-inline">Volunteers</h1> <span class="subtitle">' . (int) $total . ' matching</span>';
	if ( ! empty( $_GET['deleted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success is-dismissible"><p>Volunteer deleted.</p></div>';
	}
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
	echo '<table class="widefat striped"><thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Place</th><th>Role</th><th>Source</th><th>Signed up on</th><th>Consent</th><th>Status</th><th>Joined</th><th></th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		$place = trim( ( $r['district_id'] ? ( $names[ $r['district_id'] ] ?? '' ) . ', ' : '' ) . ( $r['state_id'] ? ( $names[ $r['state_id'] ] ?? '' ) : '' ), ', ' );
		$del   = wp_nonce_url( add_query_arg( array( 'action' => 'aw_vol_delete', 'id' => (int) $r['id'] ), admin_url( 'admin-post.php' ) ), 'aw_vol_delete_' . (int) $r['id'] );
		echo '<tr><td>' . esc_html( $r['name'] ) . '</td><td>' . esc_html( $r['email'] ) . '</td><td>' . esc_html( $r['phone'] ) . '</td><td>' . esc_html( $place ? $place : $r['city_text'] ) . '</td><td>' . esc_html( $r['role'] ) . '</td><td>' . esc_html( 'v1_campaign' === $r['source'] ? '1.0 campaign' : '2.0 form' ) . '</td><td>' . esc_html( (string) ( $r['page_source'] ?? '' ) ) . '</td><td>' . esc_html( $r['consent_scope'] ) . '</td><td>' . esc_html( $r['status'] ) . '</td><td>' . esc_html( substr( (string) $r['created_at'], 0, 10 ) ) . '</td>'
			. '<td><a href="' . esc_url( $del ) . '" style="color:#b32d2e" onclick="return confirm(\'Delete this volunteer permanently?\')">Delete</a></td></tr>';
	}
	if ( ! $rows ) {
		echo '<tr><td colspan="10">No volunteers match.</td></tr>';
	}
	echo '</tbody></table>';
	$pages = (int) ceil( $total / 50 );
	if ( $pages > 1 ) {
		echo '<div class="tablenav"><div class="tablenav-pages">' . paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'current' => $page, 'total' => $pages ) ) . '</div></div>'; // phpcs:ignore
	}
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

add_action(
	'admin_post_aw_vol_delete',
	function () {
		$id = absint( $_GET['id'] ?? 0 );
		if ( ! current_user_can( AW_VOL_CAP ) || ! check_admin_referer( 'aw_vol_delete_' . $id ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		global $wpdb;
		$wpdb->delete( aw_vol_table(), array( 'id' => $id ) ); // phpcs:ignore
		wp_safe_redirect( add_query_arg( array( 'page' => 'aw-volunteers', 'deleted' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}
);
