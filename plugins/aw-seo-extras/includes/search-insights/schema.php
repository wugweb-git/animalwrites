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

const AW_SI_DB_VERSION = '2'; // 2: links table (31 Oct 2026).
const AW_SI_DB_OPTION  = 'aw_si_db';

// aw_si_table() and aw_si_now() moved to Animal Writes Core (includes/keyword-table.php) with the keyword table.

/** Create or update the tables when the version changes. Cheap when nothing changed (one option read). */
function aw_si_install(): void {
	if ( get_option( AW_SI_DB_OPTION ) === AW_SI_DB_VERSION ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$c = $wpdb->get_charset_collate();
	$p = aw_si_table( 'kw_pages' );
	$o = aw_si_table( 'obs' );
	$r = aw_si_table( 'rising' );
	$l = aw_si_table( 'links' );
	// The keyword table itself is Animal Writes Core's (aw_kw_install(), owner's decision 3, 25 Oct 2026).
	aw_kw_install();
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
	// Who links to us (Links tab). link_key = md5 of linking page (or domain), linked page, anchor and source, so a
	// source seen again only moves last_seen. Spam is hidden, never deleted.
	dbDelta(
		"CREATE TABLE {$l} (
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
		) {$c};"
	);
	update_option( AW_SI_DB_OPTION, AW_SI_DB_VERSION, true ); // Autoloaded: checked on every admin request.
	add_option( 'aw_si_installed', time(), '', false );
	if ( function_exists( 'aw_si_links_seed' ) ) {
		aw_si_links_seed(); // Once (option aw_si_links_seeded); a repeat only moves dates.
	}
}
add_action( 'admin_init', 'aw_si_install', 1 ); // Also called by the cron and WP-CLI; there are no REST routes yet.

/** Whether the tables exist (the install may not have run yet, e.g. on a fresh install before any admin visit). */
function aw_si_ready(): bool {
	return get_option( AW_SI_DB_OPTION ) === AW_SI_DB_VERSION;
}

/**
 * Drop Search Insights' tables and options, and switch Search Insights off so the daily run does not rebuild them. The
 * keyword table is not dropped (it is Animal Writes Core's): records the owner gave a Sourcing, Press or Tracked role
 * stay (sourcing phase 2, plan §10), the rest are deleted.
 * Only from the owner's typed confirmation (admin.php) or `wp aw-si delete-data --yes`. "Run the daily update" (or
 * `wp aw-si run`) switches it on again.
 */
function aw_si_drop_all(): void {
	global $wpdb;
	foreach ( array( 'kw_pages', 'obs', 'rising', 'links' ) as $t ) {
		$wpdb->query( 'DROP TABLE IF EXISTS ' . aw_si_table( $t ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange -- fixed table names.
	}
	// The keyword table is Animal Writes Core's and holds the owner's Sourcing, Press and Tracked roles (sourcing phase 2):
	// records with one of those (or a dismissed suggestion, or a migrated spelling) stay, with Search Insights' own roles and figures cleared; the others are deleted.
	$k = aw_si_table( 'keywords' );
	$m = aw_si_table( 'kw_map' );
	$keep = aw_kw_ready() ? " AND dismissed = 0 AND (src_note IS NULL OR src_note = '')" : ''; // a dismissed suggestion or a migrated spelling is the owner's too
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$k} WHERE roles & %d = 0{$keep}", AW_KW_OWNER_ROLES ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	$set = aw_si_reset_sql( AW_KW_OWNER_ROLES ); // every Search Insights column back to a new record's (Codex, PR #75)
	$wpdb->query( "UPDATE {$k} SET {$set}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name and fixed columns only.
	if ( aw_kw_ready() ) {
		$wpdb->query( "DELETE FROM {$m} WHERE kw_id NOT IN (SELECT id FROM {$k})" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names only.
	}
	foreach ( array( AW_SI_DB_OPTION, 'aw_si_runs', 'aw_si_seeded', 'aw_si_backfill', 'aw_si_installed', 'aw_si_psi_queue', 'aw_si_clarity_calls', 'aw_si_crawl_inside', 'aw_si_crawl_outside', 'aw_si_crawl_mailed', 'aw_si_clarity_retried', 'aw_si_bing_cursor', 'aw_si_links_seeded', 'aw_si_links_seeded_20261102' ) as $opt ) {
		delete_option( $opt );
	}
	delete_transient( 'aw_si_redirects' );
	delete_transient( 'aw_si_redirects_rx' );
	update_option( 'aw_si_off', time(), false );
	foreach ( aw_si_cron_hooks() as $hook ) {
		wp_clear_scheduled_hook( $hook );
	}
}

