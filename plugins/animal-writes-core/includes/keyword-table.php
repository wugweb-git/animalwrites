<?php
/**
 * The keyword table (owner's decision 3, 25 Oct 2026): wp_aw_si_keywords, the same table and name Search Insights
 * created, now installed and owned by Animal Writes Core so sourcing never depends on aw-seo-extras. One record per
 * normalised keyword with its roles (keyword-model.php); Search Insights adds its own columns' figures, AW Sourcing
 * its roles, other spellings, search and category mapping.
 *
 * Version 2 (sourcing restructure phase 2) adds to each record:
 * - variants: other spellings, one per line (a trailing * matches the start of a word);
 * - query, feed_id, search_on: its Google News search (an override of the query, the workers' feed id, on or off);
 * - dismissed: a Search Insights suggestion the owner dismissed;
 * - src_note: where the migration found it (JSON: list => [[position, spelling], ...]), so the old lists can be
 *   rebuilt exactly as they were (read-only mirrors until phase 4).
 * and a map table, wp_aw_si_kw_map: (kw_id, kind, value), kind "category" (a category slug) or "section" (a post type).
 * Version 3 (1 Nov 2026) adds quick_win: 1 when Search Insights' daily calculation finds the keyword at position 8–20
 * with enough impressions (aw_si_quick_win), so the Quick wins view, the weekly read and exports count the same rows.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/keyword-model.php';

const AW_KW_DB_VERSION = '3';
const AW_KW_DB_OPTION  = 'aw_kw_db';

/**
 * Full name of a Search Insights / keyword table.
 *
 * @param string $name keywords, kw_map, kw_pages, obs or rising.
 */
function aw_si_table( string $name ): string {
	global $wpdb;
	return $wpdb->prefix . 'aw_si_' . $name;
}

/** Current time in MySQL format, site time zone (IST). */
function aw_si_now(): string {
	return current_time( 'mysql' );
}

/** Role bits with their labels (Search Insights' filters and AW Sourcing's Keywords screen). */
function aw_si_roles(): array {
	return array(
		AW_SI_ROLE_TRACKED  => __( 'Tracked', 'animal-writes-core' ),
		AW_SI_ROLE_FOCUS    => __( 'Focus keyphrase', 'animal-writes-core' ),
		AW_SI_ROLE_SOURCING => __( 'Include', 'animal-writes-core' ),
		AW_KW_ROLE_ANIMAL   => __( 'Animal', 'animal-writes-core' ),
		AW_KW_ROLE_CONTEXT  => __( 'Context', 'animal-writes-core' ),
		AW_KW_ROLE_EXCLUDE  => __( 'Exclude', 'animal-writes-core' ),
		AW_KW_ROLE_SEARCH   => __( 'Search', 'animal-writes-core' ),
		AW_KW_ROLE_PRESS    => __( 'Press', 'animal-writes-core' ),
		AW_KW_ROLE_CATEGORY => __( 'Category word', 'animal-writes-core' ),
		AW_SI_ROLE_BRAND    => __( 'Brand', 'animal-writes-core' ),
		AW_SI_ROLE_SEARCHED => __( 'Searched', 'animal-writes-core' ),
	);
}

/**
 * Create or update the keyword table and its map table when the version changes. Cheap when nothing changed (one
 * autoloaded option read). dbDelta adds the version 2 columns to the table Search Insights created; nothing is dropped.
 */
function aw_kw_install(): void {
	if ( get_option( AW_KW_DB_OPTION ) === AW_KW_DB_VERSION ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$c = $wpdb->get_charset_collate();
	$k = aw_si_table( 'keywords' );
	$m = aw_si_table( 'kw_map' );
	// One record per normalised keyword. The 28-day figures and the latest live check are copied here by Search
	// Insights' daily calculation so its Keywords list sorts and filters without aggregating on every view.
	dbDelta(
		"CREATE TABLE {$k} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		norm varchar(191) NOT NULL,
		label varchar(255) NOT NULL DEFAULT '',
		lang varchar(8) NOT NULL DEFAULT 'en',
		group_id bigint(20) unsigned NOT NULL DEFAULT 0,
		roles int(10) unsigned NOT NULL DEFAULT 0,
		topic varchar(100) NOT NULL DEFAULT '',
		page_type varchar(32) NOT NULL DEFAULT '',
		intent varchar(16) NOT NULL DEFAULT '',
		priority char(2) NOT NULL DEFAULT '',
		priority_override char(2) NOT NULL DEFAULT '',
		status varchar(16) NOT NULL DEFAULT '',
		action varchar(32) NOT NULL DEFAULT '',
		entity tinyint(1) NOT NULL DEFAULT 0,
		note varchar(255) NOT NULL DEFAULT '',
		page varchar(255) NOT NULL DEFAULT '',
		trends int(11) DEFAULT NULL,
		impr28 int(11) NOT NULL DEFAULT 0,
		clicks28 int(11) NOT NULL DEFAULT 0,
		pos28 float DEFAULT NULL,
		pos_prev float DEFAULT NULL,
		live_pos float DEFAULT NULL,
		live_txt varchar(16) NOT NULL DEFAULT '',
		live_date date DEFAULT NULL,
		variants text NULL,
		query varchar(500) NOT NULL DEFAULT '',
		feed_id varchar(100) NOT NULL DEFAULT '',
		search_on tinyint(1) NOT NULL DEFAULT 1,
		dismissed tinyint(1) NOT NULL DEFAULT 0,
		quick_win tinyint(1) NOT NULL DEFAULT 0,
		src_note text NULL,
		created datetime NOT NULL,
		updated datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY norm (norm),
		KEY priority (priority),
		KEY group_id (group_id),
		KEY impr28 (impr28),
		KEY roles (roles)
		) {$c};"
	);
	dbDelta(
		"CREATE TABLE {$m} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		kw_id bigint(20) unsigned NOT NULL,
		kind varchar(16) NOT NULL,
		value varchar(191) NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY kw_kind_value (kw_id,kind,value),
		KEY kind_value (kind,value)
		) {$c};"
	);
	update_option( AW_KW_DB_OPTION, AW_KW_DB_VERSION, true ); // Autoloaded: checked on every admin request.
}
add_action( 'admin_init', 'aw_kw_install', 1 ); // Also called by WP-CLI (wp aw-sourcing migrate) and Search Insights.

/** Whether the table is at the current version (the install may not have run yet). */
function aw_kw_ready(): bool {
	return get_option( AW_KW_DB_OPTION ) === AW_KW_DB_VERSION;
}

/**
 * A record as an array, with its other spellings as a list, its migration note decoded and (when asked) its maps.
 *
 * @param object|array $r Row.
 * @return array
 */
function aw_kw_decode( $r ) {
	$r              = (array) $r;
	$r['id']        = (int) ( $r['id'] ?? 0 );
	$r['roles']     = (int) ( $r['roles'] ?? 0 );
	$r['search_on'] = (int) ( $r['search_on'] ?? 1 );
	$r['dismissed'] = (int) ( $r['dismissed'] ?? 0 );
	$r['variants']  = aw_kw_variants_list( (string) ( $r['variants'] ?? '' ) );
	$src            = json_decode( (string) ( $r['src_note'] ?? '' ), true );
	$r['src']       = is_array( $src ) ? $src : array();
	return $r;
}

/**
 * Records with any of these roles (all records for 0), each with its category and section maps.
 *
 * @param int  $mask    Role bits (0: every record; careful, about 4,700).
 * @param bool $en_only Only lang = en.
 * @return array[] id => record.
 */
function aw_kw_rows( $mask = AW_KW_OWNER_ROLES, $en_only = false ) {
	global $wpdb;
	$k    = aw_si_table( 'keywords' );
	$sql  = "SELECT id, norm, label, lang, roles, variants, query, feed_id, search_on, dismissed, src_note, impr28 FROM {$k} WHERE 1=1";
	$args = array();
	if ( $mask ) {
		$sql   .= ' AND roles & %d';
		$args[] = (int) $mask;
	}
	if ( $en_only ) {
		$sql .= " AND lang = 'en'";
	}
	$sql .= ' ORDER BY id';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- table name only; placeholders built above.
	$rows = (array) $wpdb->get_results( $args ? $wpdb->prepare( $sql, $args ) : $sql, ARRAY_A );
	return aw_kw_attach_maps( $rows );
}

/**
 * Rows decoded (aw_kw_decode) and given their category and section maps, by id.
 *
 * @param array $rows Rows as read.
 * @return array[] id => record.
 */
function aw_kw_attach_maps( array $rows ) {
	$out = array();
	foreach ( $rows as $r ) {
		$r               = aw_kw_decode( $r );
		$r['categories'] = array();
		$r['sections']   = array();
		$out[ $r['id'] ] = $r;
	}
	foreach ( aw_kw_maps( array_keys( $out ) ) as $m ) {
		if ( isset( $out[ $m['kw_id'] ] ) ) {
			$out[ $m['kw_id'] ][ 'category' === $m['kind'] ? 'categories' : 'sections' ][] = $m['value'];
		}
	}
	return $out;
}

/**
 * One record by id (with its maps), or null.
 *
 * @param int $id Record id.
 * @return array|null
 */
function aw_kw_get( $id ) {
	global $wpdb;
	$k = aw_si_table( 'keywords' );
	$r = $wpdb->get_row( $wpdb->prepare( "SELECT id, norm, label, lang, roles, variants, query, feed_id, search_on, dismissed, src_note, impr28 FROM {$k} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	if ( ! $r ) {
		return null;
	}
	$out = aw_kw_attach_maps( array( $r ) );
	return reset( $out );
}

/**
 * The id of the record with this normal form, or 0.
 *
 * @param string $norm aw_si_norm() form.
 * @return int
 */
function aw_kw_id_by_norm( $norm ) {
	global $wpdb;
	$k = aw_si_table( 'keywords' );
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$k} WHERE norm = %s", (string) $norm ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
}

/**
 * Map rows of these records.
 *
 * @param int[] $ids Record ids.
 * @return array[] kw_id, kind, value.
 */
function aw_kw_maps( array $ids ) {
	global $wpdb;
	$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
	if ( ! $ids || ! aw_kw_ready() ) {
		return array();
	}
	$m   = aw_si_table( 'kw_map' );
	$out = array();
	foreach ( array_chunk( $ids, 500 ) as $chunk ) {
		$in  = implode( ',', $chunk );
		$out = array_merge( $out, (array) $wpdb->get_results( "SELECT kw_id, kind, value FROM {$m} WHERE kw_id IN ({$in}) ORDER BY id", ARRAY_A ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integers.
	}
	foreach ( $out as $i => $r ) {
		$out[ $i ]['kw_id'] = (int) $r['kw_id'];
	}
	return $out;
}

/**
 * Create a record (or return the one with the same normal form). Roles go through aw_kw_set_roles() afterwards.
 *
 * @param string $label  Phrase as written.
 * @param array  $fields Other columns to set on a new record (variants as a list, query, feed_id, search_on, src_note).
 * @return int Record id (0 when the phrase is empty or the insert failed).
 */
function aw_kw_create( $label, array $fields = array() ) {
	global $wpdb;
	$label = trim( (string) $label );
	$norm  = aw_si_norm( $label );
	if ( '' === $norm ) {
		return 0;
	}
	$id = aw_kw_id_by_norm( $norm );
	if ( $id ) {
		return $id;
	}
	$now = aw_si_now();
	$row = array(
		'norm'    => $norm,
		'label'   => mb_substr( $label, 0, 255 ),
		'lang'    => isset( $fields['lang'] ) ? (string) $fields['lang'] : aw_si_lang( $label ),
		'roles'   => aw_si_is_brand( $label ) ? AW_SI_ROLE_BRAND : 0,
		'created' => $now,
		'updated' => $now,
	);
	foreach ( array( 'query', 'feed_id', 'src_note' ) as $f ) {
		if ( isset( $fields[ $f ] ) ) {
			$row[ $f ] = (string) $fields[ $f ];
		}
	}
	foreach ( array( 'search_on', 'dismissed' ) as $f ) {
		if ( isset( $fields[ $f ] ) ) {
			$row[ $f ] = (int) $fields[ $f ];
		}
	}
	if ( isset( $fields['variants'] ) ) {
		$row['variants'] = implode( "\n", aw_kw_variants_list( $fields['variants'] ) );
	}
	if ( false === $wpdb->insert( aw_si_table( 'keywords' ), $row ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return 0;
	}
	$id = (int) $wpdb->insert_id;
	$wpdb->update( aw_si_table( 'keywords' ), array( 'group_id' => $id ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- a new record is its own group, as Search Insights does.
	return $id;
}

/**
 * Set roles on a record: the one role setter (owner, 25 Oct 2026). Refuses Sourcing and Press roles on a record that is
 * not English, and Include with Exclude (aw_kw_roles_check()).
 *
 * @param int $id     Record id.
 * @param int $add    Bits to add.
 * @param int $remove Bits to remove.
 * @return int|WP_Error The new roles, or why nothing changed.
 */
function aw_kw_set_roles( $id, $add, $remove = 0 ) {
	global $wpdb;
	$k   = aw_si_table( 'keywords' );
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT roles, lang FROM {$k} WHERE id = %d", (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	if ( ! $row ) {
		return new WP_Error( 'aw_kw_missing', 'No such keyword.' );
	}
	list( $new, $why ) = aw_kw_roles_check( (int) $row->roles, (int) $add, (int) $remove, (string) $row->lang );
	if ( '' !== $why ) {
		return new WP_Error( 'aw_kw_refused', $why );
	}
	if ( $new !== (int) $row->roles ) {
		$wpdb->update( $k, array( 'roles' => $new, 'updated' => aw_si_now() ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		do_action( 'aw_kw_saved', (int) $id );
	}
	return $new;
}

/**
 * Update a record's own columns (not roles: aw_kw_set_roles()). A language change that would leave Sourcing or Press
 * roles on a record that is not English is refused.
 *
 * @param int   $id     Record id.
 * @param array $fields Any of label, lang, variants (list or lines), query, feed_id, search_on, dismissed, src_note.
 * @return bool|WP_Error Whether anything was written.
 */
function aw_kw_update( $id, array $fields ) {
	global $wpdb;
	$cur = aw_kw_get( $id );
	if ( ! $cur ) {
		return new WP_Error( 'aw_kw_missing', 'No such keyword.' );
	}
	$set = array();
	if ( isset( $fields['lang'] ) && in_array( $fields['lang'], array( 'en', 'hi', 'hinglish' ), true ) && $fields['lang'] !== $cur['lang'] ) {
		if ( 'en' !== $fields['lang'] && ( $cur['roles'] & AW_KW_WORKER_ROLES ) ) {
			return new WP_Error( 'aw_kw_refused', 'Remove its Sourcing and Press roles first: they are for English keywords only.' );
		}
		$set['lang'] = $fields['lang'];
	}
	if ( isset( $fields['variants'] ) ) {
		$v = implode( "\n", aw_kw_variants_list( $fields['variants'] ) );
		if ( $v !== implode( "\n", $cur['variants'] ) ) {
			$set['variants'] = $v;
		}
	}
	foreach ( array( 'query', 'feed_id', 'src_note' ) as $f ) {
		if ( isset( $fields[ $f ] ) && (string) $fields[ $f ] !== (string) ( $cur[ $f ] ?? '' ) ) {
			$set[ $f ] = (string) $fields[ $f ];
		}
	}
	foreach ( array( 'search_on', 'dismissed' ) as $f ) {
		if ( isset( $fields[ $f ] ) && (int) $fields[ $f ] !== (int) $cur[ $f ] ) {
			$set[ $f ] = (int) $fields[ $f ];
		}
	}
	if ( ! $set ) {
		return false;
	}
	$changed        = array_keys( $set );
	$set['updated'] = aw_si_now();
	$wpdb->update( aw_si_table( 'keywords' ), $set, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	do_action( 'aw_kw_saved', (int) $id, $changed );
	return true;
}

/**
 * Replace a record's maps of one kind (categories or sections).
 *
 * @param int      $id     Record id.
 * @param string   $kind   category or section.
 * @param string[] $values Category slugs or post types.
 * @return bool Whether anything changed.
 */
function aw_kw_map_set( $id, $kind, array $values ) {
	global $wpdb;
	$m      = aw_si_table( 'kw_map' );
	$values = array_values( array_unique( array_filter( array_map( 'strval', $values ), 'strlen' ) ) );
	$now    = array();
	foreach ( aw_kw_maps( array( $id ) ) as $r ) {
		if ( $r['kind'] === $kind ) {
			$now[] = $r['value'];
		}
	}
	$add = array_diff( $values, $now );
	$del = array_diff( $now, $values );
	foreach ( $del as $v ) {
		$wpdb->delete( $m, array( 'kw_id' => (int) $id, 'kind' => $kind, 'value' => $v ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	foreach ( $add as $v ) {
		$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$m} (kw_id, kind, value) VALUES (%d, %s, %s)", (int) $id, $kind, $v ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	}
	if ( $add || $del ) {
		do_action( 'aw_kw_saved', (int) $id );
		return true;
	}
	return false;
}
