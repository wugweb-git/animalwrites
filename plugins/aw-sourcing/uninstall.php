<?php
/**
 * Uninstall SourceWeb. By default it deletes nothing: sources, keywords, rules, review queues, logs, AI model settings
 * and the source icons in the media library stay, so reinstalling finds everything as it was.
 *
 * Only when SourceWeb → Settings → Uninstall → "Delete this plugin's data when it is deleted" is ticked
 * (aw_src_delete_data_on_uninstall is true) does it delete SourceWeb's own data, exactly this:
 *
 * Options:
 *   Settings: aw_backfill_floor, aw_enrich_sources, aw_enrich_google_key, aw_enrich_live_cap, aw_enrich_field_rules,
 *   aw_summary_rules, aw_sourcing_dismissed, aw_workers_groups.
 *   Records, logs and markers: aw_ai_free_caps, aw_ai_usage, aw_ai_provider_icons, aw_enrich_db, aw_enrich_hosts,
 *   aw_enrich_ledger, aw_ingest_heartbeat, aw_ingest_watchdog_at, aw_ingest_watchdog_last, aw_relay_log,
 *   aw_relevance_alert_notice, aw_relevance_alert_pending, aw_relevance_rejected, aw_site_opportunities_last,
 *   aw_source_icons, aw_sourcing_migrating, aw_sourcing_phase, aw_sourcing_phase1_record … aw_sourcing_phase4_record,
 *   aw_sourcing_seed_hc, aw_sourcing_seed_s35, aw_sourcing_v1_archive, kits_sourcing_log (the retired v1 log),
 *   aw_worker_dispatch_log, aw_workers_meta, aw_workers_tick, aw_workers_tick_lock, aw_workers_watchdog_at,
 *   sourceweb_dashboard_log, aw_worker_runs_*, the migration backups aw_backup_1_* … aw_backup_4_* and
 *   aw_backup_latest_1 … aw_backup_latest_4, aw_src_delete_data_on_uninstall.
 * Transients (caches; they expire anyway): aw_ingest_alert_mailed, aw_relevance_alert_sent, aw_review_count,
 *   aw_sourcing_host_stats, aw_summary_below, aw_workers_alert_mailed, sourceweb_dashboard, and those starting
 *   aw_newsm_, aw_glive_, aw_sourcing_kw_stats_, aw_relay_rl_.
 * Scheduled events (all of each hook, whatever its arguments): aw_sourcing_site_meta, aw_ai_check_all,
 *   aw_ai_check_all_now, aw_ai_check_new, aw_ingest_watchdog, aw_workers_watchdog, sourceweb_dashboard_build,
 *   aw_sources_sync_dispatch.
 * Tables: {prefix}aw_enrich (the enrichment proposals and their sources; the values applied to listings are post meta
 *   and stay).
 *
 * Never deleted, even when ticked:
 * - Posts (News items, review queue items, listings), terms, users, attachments (the source icons stay in the media
 *   library) and any post meta.
 * - The AI model rows and keys (aw_ai_models, aw_ai_keys): keys are removed only by hand.
 * - Data shared with Kits Core and the other plugins: aw_sources, aw_publishers, aw_relevance, the keyword tables
 *   ({prefix}aw_si_keywords, {prefix}aw_si_kw_map) and aw_kw_db; Animal Writes Core's aw_social_on and aw_social_show
 *   (SourceWeb's Settings also saves them); the aw_research_citation event (its work is Animal Writes Core's).
 *
 * Before deleting, writes a backup to wp-content/uploads/wugweb-backups/aw-sourcing-uninstall-<YYYYmmdd-His>.jsonl.gz
 * (includes/uninstall-backup.php): a header line, then every option below with its value as stored, then every row of
 * every table below (transients are caches and are not kept). If the backup cannot be written, nothing is deleted.
 *
 * Runs for the current site only.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( true !== (bool) get_option( 'aw_src_delete_data_on_uninstall', false ) ) {
	return; // The default: keep everything.
}

$aw_src_options = array(
	'aw_backfill_floor',
	'aw_enrich_sources',
	'aw_enrich_google_key',
	'aw_enrich_live_cap',
	'aw_enrich_field_rules',
	'aw_summary_rules',
	'aw_sourcing_dismissed',
	'aw_workers_groups',
	'aw_ai_free_caps',
	'aw_ai_usage',
	'aw_ai_provider_icons',
	'aw_enrich_db',
	'aw_enrich_hosts',
	'aw_enrich_ledger',
	'aw_ingest_heartbeat',
	'aw_ingest_watchdog_at',
	'aw_ingest_watchdog_last',
	'aw_relay_log',
	'aw_relevance_alert_notice',
	'aw_relevance_alert_pending',
	'aw_relevance_rejected',
	'aw_site_opportunities_last',
	'aw_source_icons',
	'aw_sourcing_migrating',
	'aw_sourcing_phase',
	'aw_sourcing_phase1_record',
	'aw_sourcing_phase2_record',
	'aw_sourcing_phase3_record',
	'aw_sourcing_phase4_record',
	'aw_sourcing_seed_hc',
	'aw_sourcing_seed_s35',
	'aw_sourcing_v1_archive',
	'kits_sourcing_log',
	'aw_worker_dispatch_log',
	'aw_workers_meta',
	'aw_workers_tick',
	'aw_workers_tick_lock',
	'aw_workers_watchdog_at',
	'sourceweb_dashboard_log',
	'aw_backup_latest_1',
	'aw_backup_latest_2',
	'aw_backup_latest_3',
	'aw_backup_latest_4',
);

global $wpdb;
require_once __DIR__ . '/includes/uninstall-backup.php';
if ( ! aw_src_uninstall_backup( 'aw-sourcing', __DIR__ . '/aw-sourcing.php', array_merge( $aw_src_options, array( 'aw_src_delete_data_on_uninstall' ) ), array( 'aw_worker_runs_', 'aw_backup_1_', 'aw_backup_2_', 'aw_backup_3_', 'aw_backup_4_' ), array( $wpdb->prefix . 'aw_enrich' ) ) ) {
	return; // No backup, no delete (the reason is in the error log).
}

foreach ( $aw_src_options as $aw_src_option ) {
	delete_option( $aw_src_option );
}

$aw_src_like = static function ( string $start ) use ( $wpdb ): void {
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $start ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- uninstall: options by prefix, once.
};

// Options with a name or a date at the end: each workflow's runs, the migration backups (aw_backup_<phase>_<time>).
$aw_src_like( 'aw_worker_runs_' );
foreach ( array( 1, 2, 3, 4 ) as $aw_src_phase ) {
	$aw_src_like( 'aw_backup_' . $aw_src_phase . '_' );
}

foreach ( array( 'aw_ingest_alert_mailed', 'aw_relevance_alert_sent', 'aw_review_count', 'aw_sourcing_host_stats', 'aw_summary_below', 'aw_workers_alert_mailed', 'sourceweb_dashboard' ) as $aw_src_transient ) {
	delete_transient( $aw_src_transient );
}
foreach ( array( 'aw_newsm_', 'aw_glive_', 'aw_sourcing_kw_stats_', 'aw_relay_rl_' ) as $aw_src_prefix ) {
	$aw_src_like( '_transient_' . $aw_src_prefix );
	$aw_src_like( '_transient_timeout_' . $aw_src_prefix );
}

foreach ( array( 'aw_sourcing_site_meta', 'aw_ai_check_all', 'aw_ai_check_all_now', 'aw_ai_check_new', 'aw_ingest_watchdog', 'aw_workers_watchdog', 'sourceweb_dashboard_build', 'aw_sources_sync_dispatch' ) as $aw_src_hook ) {
	wp_unschedule_hook( $aw_src_hook );
}

$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $wpdb->prefix . 'aw_enrich' ) . '`' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- uninstall: SourceWeb's own table, name fixed.

delete_option( 'aw_src_delete_data_on_uninstall' ); // Last, so a run that stops half-way can be repeated.
