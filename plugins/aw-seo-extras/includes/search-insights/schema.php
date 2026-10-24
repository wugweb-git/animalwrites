<?php
/**
 * Search Insights tables (plan §3). Built with dbDelta and a version option, like the enrichment table in AW Sourcing.
 * Deactivating or deleting the plugin keeps the tables and options; only the owner's "Delete Search Insights data"
 * (Settings tab, nonce + typed confirmation) drops them.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SI_DB_VERSION = '1';
const AW_SI_DB_OPTION  = 'aw_si_db';

/**
 * Full name of a Search Insights table.
 *
 * @param string $name keywords, kw_pages, obs or rising.
 */
function aw_si_table( string $name ): string {
	global $wpdb;
	return $wpdb->prefix . 'aw_si_' . $name;
}

/** Create or update the tables when the version changes. Cheap when nothing changed (one option read). */
function aw_si_install(): void {
	if ( get_option( AW_SI_DB_OPTION ) === AW_SI_DB_VERSION ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$c = $wpdb->get_charset_collate();
	$k = aw_si_table( 'keywords' );
	$p = aw_si_table( 'kw_pages' );
	$o = aw_si_table( 'obs' );
	$r = aw_si_table( 'rising' );
	// One record per normalised keyword. The 28-day figures and the latest live check are copied here by
	// aw_si_compute() so the Keywords list sorts and filters without aggregating obs on every view.
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
		created datetime NOT NULL,
		updated datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY norm (norm),
		KEY priority (priority),
		KEY group_id (group_id),
		KEY impr28 (impr28)
		) {$c};"
	);
	// Which page owns which keyword. path_key = md5 of the object or path, so one page holds one row per keyword and role.
	dbDelta(
		"CREATE TABLE {$p} (
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
		) {$c};"
	);
	// Every measurement, long format. period: day (rolled up after 90 days), month (kept), or a rolling window such as
	// 28d / 12m that is never summed (plan §3, retention). GSC rows fill clicks / impr / pos; other sources use value / txt.
	dbDelta(
		"CREATE TABLE {$o} (
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
		) {$c};"
	);
	// Rising terms (phase 3 fills it; created now so the schema is stable).
	dbDelta(
		"CREATE TABLE {$r} (
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
		) {$c};"
	);
	update_option( AW_SI_DB_OPTION, AW_SI_DB_VERSION, true ); // Autoloaded: checked on every admin request.
	add_option( 'aw_si_installed', time(), '', false );
}
add_action( 'admin_init', 'aw_si_install', 1 ); // Also called by the cron and WP-CLI; there are no REST routes yet.

/** Whether the tables exist (the install may not have run yet, e.g. on a fresh install before any admin visit). */
function aw_si_ready(): bool {
	return get_option( AW_SI_DB_OPTION ) === AW_SI_DB_VERSION;
}

/**
 * Drop every Search Insights table and option, and switch Search Insights off so the daily run does not rebuild them.
 * Only from the owner's typed confirmation (admin.php) or `wp aw-si delete-data --yes`. "Run the daily update" (or
 * `wp aw-si run`) switches it on again.
 */
function aw_si_drop_all(): void {
	global $wpdb;
	foreach ( array( 'keywords', 'kw_pages', 'obs', 'rising' ) as $t ) {
		$wpdb->query( 'DROP TABLE IF EXISTS ' . aw_si_table( $t ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange -- fixed table names.
	}
	foreach ( array( AW_SI_DB_OPTION, 'aw_si_runs', 'aw_si_seeded', 'aw_si_backfill', 'aw_si_installed', 'aw_si_psi_queue', 'aw_si_clarity_calls' ) as $opt ) {
		delete_option( $opt );
	}
	delete_transient( 'aw_si_redirects' );
	update_option( 'aw_si_off', time(), false );
	foreach ( aw_si_cron_hooks() as $hook ) {
		wp_clear_scheduled_hook( $hook );
	}
}

/** Current time in MySQL format, site time zone (IST). */
function aw_si_now(): string {
	return current_time( 'mysql' );
}
