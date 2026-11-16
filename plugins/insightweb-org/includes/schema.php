<?php
/**
 * InsightWeb's observation tables — the data the daily recompute reads and the sources (Site Kit, PageSpeed, Clarity,
 * Bing) write into. Four tables, all owned by the plugin, built with dbDelta and a version option:
 *
 * - kw_pages: which page owns or carries which keyword (one row per keyword, path and role).
 * - obs: every measurement, long format (one row per source/metric/period/day/keyword/page).
 * - rising: rising terms.
 * - links: who links to the site (the Links view).
 *
 * The schemas are generic; the Animal Writes links seed is not carried (#6). Deactivating or deleting the plugin keeps
 * the tables; only the owner's opt-in "delete data" removes them. One prefix: insightweb_ / INSIGHTWEB_.
 *
 * @package InsightWeb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const INSIGHTWEB_DB_VERSION = '1';
const INSIGHTWEB_DB_OPTION  = 'insightweb_db';

/**
 * CREATE TABLE for kw_pages (keyword → owning page). Pure.
 *
 * @param string $table   Full table name.
 * @param string $collate Charset/collate clause.
 * @return string
 */
function insightweb_pages_schema_sql( string $table, string $collate ): string {
	return "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		kw_id bigint(20) unsigned NOT NULL,
		obj_type varchar(16) NOT NULL DEFAULT '',
		obj_id bigint(20) unsigned NOT NULL DEFAULT 0,
		path varchar(255) NOT NULL DEFAULT '',
		path_key char(32) NOT NULL,
		role varchar(16) NOT NULL DEFAULT '',
		src varchar(16) NOT NULL DEFAULT '',
		conflict tinyint(1) NOT NULL DEFAULT 0,
		updated datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY kw_page_role (kw_id,path_key,role),
		KEY obj (obj_type,obj_id),
		KEY src (src)
		) {$collate};";
}

/**
 * CREATE TABLE for obs (every measurement, long format). GSC rows fill clicks/impr/pos; other sources use value/txt.
 * Pure.
 *
 * @param string $table   Full table name.
 * @param string $collate Charset/collate clause.
 * @return string
 */
function insightweb_obs_schema_sql( string $table, string $collate ): string {
	return "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		kw_id bigint(20) unsigned NOT NULL DEFAULT 0,
		page varchar(255) NOT NULL DEFAULT '',
		page_key char(32) NOT NULL DEFAULT '',
		src varchar(24) NOT NULL,
		metric varchar(24) NOT NULL,
		period varchar(8) NOT NULL DEFAULT 'day',
		d date NOT NULL,
		clicks int(11) DEFAULT NULL,
		impr int(11) DEFAULT NULL,
		pos float DEFAULT NULL,
		value double DEFAULT NULL,
		txt varchar(255) NOT NULL DEFAULT '',
		fetched datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY obs (src,metric,period,d,kw_id,page_key),
		KEY kw_d (kw_id,d),
		KEY period_d (period,d)
		) {$collate};";
}

/**
 * CREATE TABLE for rising (rising terms). Pure.
 *
 * @param string $table   Full table name.
 * @param string $collate Charset/collate clause.
 * @return string
 */
function insightweb_rising_schema_sql( string $table, string $collate ): string {
	return "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		norm varchar(191) NOT NULL,
		term varchar(255) NOT NULL DEFAULT '',
		sources varchar(255) NOT NULL DEFAULT '',
		growth float DEFAULT NULL,
		first_seen date DEFAULT NULL,
		last_seen date DEFAULT NULL,
		kw_id bigint(20) unsigned NOT NULL DEFAULT 0,
		page varchar(255) NOT NULL DEFAULT '',
		action varchar(32) NOT NULL DEFAULT '',
		status varchar(16) NOT NULL DEFAULT 'new',
		note varchar(255) NOT NULL DEFAULT '',
		PRIMARY KEY  (id),
		UNIQUE KEY norm (norm),
		KEY status (status)
		) {$collate};";
}

/**
 * CREATE TABLE for links (who links to the site). link_key = md5 of the linking page, linked page, anchor and source.
 * Pure.
 *
 * @param string $table   Full table name.
 * @param string $collate Charset/collate clause.
 * @return string
 */
function insightweb_links_schema_sql( string $table, string $collate ): string {
	return "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		link_key char(32) NOT NULL,
		domain varchar(191) NOT NULL DEFAULT '',
		from_url varchar(500) NOT NULL DEFAULT '',
		linked_url varchar(255) NOT NULL DEFAULT '',
		anchor varchar(255) NOT NULL DEFAULT '',
		links int(11) NOT NULL DEFAULT 1,
		first_seen date DEFAULT NULL,
		last_seen date DEFAULT NULL,
		source varchar(100) NOT NULL DEFAULT '',
		type varchar(16) NOT NULL DEFAULT '',
		hidden tinyint(1) NOT NULL DEFAULT 0,
		note varchar(255) NOT NULL DEFAULT '',
		PRIMARY KEY  (id),
		UNIQUE KEY link_key (link_key),
		KEY domain (domain),
		KEY type_hidden (type,hidden),
		KEY last_seen (last_seen)
		) {$collate};";
}

/**
 * Create or update the observation tables (and the keyword table) when the version changes. Cheap when nothing changed.
 *
 * @return void
 */
function insightweb_install(): void {
	if ( get_option( INSIGHTWEB_DB_OPTION ) === INSIGHTWEB_DB_VERSION ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$collate = $wpdb->get_charset_collate();
	insightweb_kw_install();
	dbDelta( insightweb_pages_schema_sql( insightweb_kw_table( 'kw_pages' ), $collate ) );
	dbDelta( insightweb_obs_schema_sql( insightweb_kw_table( 'obs' ), $collate ) );
	dbDelta( insightweb_rising_schema_sql( insightweb_kw_table( 'rising' ), $collate ) );
	dbDelta( insightweb_links_schema_sql( insightweb_kw_table( 'links' ), $collate ) );
	update_option( INSIGHTWEB_DB_OPTION, INSIGHTWEB_DB_VERSION, true ); // Autoloaded: checked on every admin request.
}
add_action( 'admin_init', 'insightweb_install', 2 ); // After the keyword table (priority 1).

/**
 * Whether the observation tables exist at the current version (the install may not have run yet).
 *
 * @return bool
 */
function insightweb_ready(): bool {
	return get_option( INSIGHTWEB_DB_OPTION ) === INSIGHTWEB_DB_VERSION;
}
