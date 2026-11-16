<?php
/**
 * InsightWeb's own keyword table — the public edition's replacement for Kits Core's shared `aw_kw_*` table. One record
 * per normalised keyword with its Search Console figures (copied in by the daily calculation so the Keywords list
 * sorts and filters without aggregating on every view), a priority, a status and a suggested action.
 *
 * This is the search-insights subset only: the sourcing/news columns (variants, query, feed_id, search_on, dismissed,
 * src_note) and the category/section map table from the shared Animal Writes table are intentionally dropped — that is
 * part of the #6 decouple. One prefix: insightweb_ / INSIGHTWEB_. No `aw_` / `kits_`.
 *
 * @package InsightWeb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const INSIGHTWEB_KW_DB_VERSION = '1';
const INSIGHTWEB_KW_DB_OPTION  = 'insightweb_kw_db';

// Role bits (InsightWeb's own; a keyword may hold several).
const INSIGHTWEB_ROLE_TRACKED  = 1;
const INSIGHTWEB_ROLE_FOCUS    = 2;
const INSIGHTWEB_ROLE_BRAND    = 4;
const INSIGHTWEB_ROLE_SEARCHED = 8;

/**
 * Full name of an InsightWeb table.
 *
 * @param string $name Short table name, e.g. "keywords".
 * @return string
 */
function insightweb_kw_table( string $name ): string {
	global $wpdb;
	return $wpdb->prefix . 'insightweb_' . $name;
}

/**
 * Current time in MySQL format, site time zone.
 *
 * @return string
 */
function insightweb_kw_now(): string {
	return current_time( 'mysql' );
}

/**
 * Normalise a phrase to its lookup key: lower case, tags and punctuation stripped, whitespace collapsed. Pure (no
 * WordPress), so it is testable and stable.
 *
 * @param string $s Phrase as written.
 * @return string
 */
function insightweb_kw_norm( string $s ): string {
	$s = strtolower( strip_tags( $s ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- plain text key, not output.
	$s = (string) preg_replace( '/[^\p{L}\p{N}\s]+/u', ' ', $s );
	$s = (string) preg_replace( '/\s+/', ' ', $s );
	return trim( $s );
}

/**
 * The CREATE TABLE statement for the keywords table. Pure (name + collate in), so the schema is testable without a
 * database.
 *
 * @param string $table   Full table name.
 * @param string $collate Charset/collate clause.
 * @return string
 */
function insightweb_kw_schema_sql( string $table, string $collate ): string {
	return "CREATE TABLE {$table} (
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
		quick_win tinyint(1) NOT NULL DEFAULT 0,
		created datetime NOT NULL,
		updated datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY norm (norm),
		KEY priority (priority),
		KEY group_id (group_id),
		KEY impr28 (impr28),
		KEY roles (roles)
		) {$collate};";
}

/**
 * Create or update the keyword table when the version changes. Cheap when nothing changed (one autoloaded option read).
 *
 * @return void
 */
function insightweb_kw_install(): void {
	if ( get_option( INSIGHTWEB_KW_DB_OPTION ) === INSIGHTWEB_KW_DB_VERSION ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( insightweb_kw_schema_sql( insightweb_kw_table( 'keywords' ), $wpdb->get_charset_collate() ) );
	update_option( INSIGHTWEB_KW_DB_OPTION, INSIGHTWEB_KW_DB_VERSION, true ); // Autoloaded: checked on every admin request.
}
add_action( 'admin_init', 'insightweb_kw_install', 1 );

/**
 * Whether the table is at the current version (the install may not have run yet).
 *
 * @return bool
 */
function insightweb_kw_ready(): bool {
	return get_option( INSIGHTWEB_KW_DB_OPTION ) === INSIGHTWEB_KW_DB_VERSION;
}

/**
 * The id of the record with this normal form, or 0.
 *
 * @param string $norm insightweb_kw_norm() form.
 * @return int
 */
function insightweb_kw_id_by_norm( string $norm ): int {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE norm = %s', insightweb_kw_table( 'keywords' ), $norm ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/**
 * One record by id as an array, or null.
 *
 * @param int $id Record id.
 * @return array<string, mixed>|null
 */
function insightweb_kw_get( int $id ): ?array {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', insightweb_kw_table( 'keywords' ), $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return $row ? (array) $row : null;
}

/**
 * Create a record, or return the id of the one with the same normal form. A new record is its own group.
 *
 * @param string               $label  Phrase as written.
 * @param array<string, mixed> $fields Optional lang and roles on a new record.
 * @return int Record id (0 when the phrase is empty or the insert failed).
 */
function insightweb_kw_create( string $label, array $fields = array() ): int {
	global $wpdb;
	$label = trim( $label );
	$norm  = insightweb_kw_norm( $label );
	if ( '' === $norm ) {
		return 0;
	}
	$id = insightweb_kw_id_by_norm( $norm );
	if ( $id ) {
		return $id;
	}
	$now   = insightweb_kw_now();
	$table = insightweb_kw_table( 'keywords' );
	$row   = array(
		'norm'    => $norm,
		'label'   => mb_substr( $label, 0, 255 ),
		'lang'    => isset( $fields['lang'] ) ? (string) $fields['lang'] : 'en',
		'roles'   => isset( $fields['roles'] ) ? (int) $fields['roles'] : 0,
		'created' => $now,
		'updated' => $now,
	);
	if ( false === $wpdb->insert( $table, $row ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return 0;
	}
	$id = (int) $wpdb->insert_id;
	$wpdb->update( $table, array( 'group_id' => $id ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- a new record is its own group.
	return $id;
}

/**
 * Write the Search Console figures and derived fields onto a record (the daily calculation's writer).
 *
 * @param int                  $id     Record id.
 * @param array<string, mixed> $fields Any of trends, impr28, clicks28, pos28, pos_prev, live_pos, live_txt, live_date,
 *                                     priority, status, action, entity, page, quick_win, roles.
 * @return bool Whether anything was written.
 */
function insightweb_kw_set_figures( int $id, array $fields ): bool {
	global $wpdb;
	$allowed = array( 'trends', 'impr28', 'clicks28', 'pos28', 'pos_prev', 'live_pos', 'live_txt', 'live_date', 'priority', 'priority_override', 'status', 'action', 'entity', 'note', 'page', 'quick_win', 'roles', 'topic', 'page_type', 'intent' );
	$set     = array_intersect_key( $fields, array_flip( $allowed ) );
	if ( ! $set ) {
		return false;
	}
	$set['updated'] = insightweb_kw_now();
	return false !== $wpdb->update( insightweb_kw_table( 'keywords' ), $set, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}
