<?php
/**
 * Uninstall Animal Writes Core. By default it deletes nothing: settings, listings, campaigns, letters, places, form
 * records, media groups and every table it keeps stay, so reinstalling finds everything as it was.
 *
 * Only when Kits → Modules → Animal Writes Core data → "Delete this plugin's data when it is deleted" is ticked
 * (aw_delete_data_on_uninstall is true) does it delete the plugin's own settings, caches, scheduled events and
 * tables, exactly this:
 *
 * Options:
 *   Settings: aw_cdn_ttl, aw_cdn_ttl_long, aw_social_on, aw_social_show, aw_ff_send_email, aw_nl_ff_form_full,
 *   aw_sample_listing_ids, aw_district_helplines, aw_issuer_short, aw_follow_reddit, aw_follow_discord, aw_keys
 *   (Animal Writes → Keys; InsightWeb reads it), aw_ff_forms, aw_place_intros, aw_elsewhere, aw_internal_org,
 *   aw_category_images, aw_press_logos, aw_team_dates (Kits → Dates; PlanWeb's own entries are posts and stay).
 *   Records, logs and markers: aw_backup_last, aw_camp_close_jobs_removed, aw_camp_dogs_post_set, aw_camp_letters_db,
 *   aw_form_raw_db, aw_cc0_log, aw_cl_ref, aw_cron_runs, aw_deployed_head, aw_dispatch_log, aw_error_count,
 *   aw_error_pages, aw_geo_last_request, aw_gov_verified_live, aw_helpers_setup, aw_hub_hashes, aw_hub_pages,
 *   aw_hub_rewrite, aw_hubs_version, aw_issuers_added, aw_mail_in_old_seen, aw_media_merge_log, aw_media_merge_data_*,
 *   aw_media_reuse_log, aw_media_webp_log, aw_model_seeded, aw_ov_last, aw_ov_quota, aw_place_counts,
 *   aw_places_rewrite, aw_resources_rewrite, aw_services_seeded, aw_team_dates_1101, aw_team_dates_1101b,
 *   aw_team_dates_faq_holdout_off, aw_team_dates_links_note_1111, aw_team_dates_links_read, aw_theme_switch_log,
 *   aw_thumb_map, aw_lf_gen_*, aw_delete_data_on_uninstall.
 * Transients (caches; they expire anyway): aw_cc0_lock, aw_dir_points, aw_error_count_mailed, aw_error_pages_checked,
 *   aw_faq_state_type_counts, aw_faq_study_subjects, aw_featured_aliases, aw_featured_links, aw_featured_names,
 *   aw_featured_shared, aw_find_help_types, aw_gov_verified_count, aw_law_mentions, aw_ov_pause, aw_thumb_dupes_n,
 *   aw_verified_count, directoryweb_stats, and those starting aw_camp_short_notice_, aw_cl_cap_, aw_cl_h_, aw_cl_s_,
 *   aw_faq_tag_, aw_media_reuse_notice_, aw_pin_, aw_place_error_, aw_near_opps_card_, aw_clause_, aw_cs_, aw_ov_,
 *   aw_orgq_rl_, aw_related_, aw_more_, aw_lf_, aw_geo_.
 * Scheduled events (all of each hook, whatever its arguments): aw_daily, aw_daily_backup, aw_cc0_run, aw_raw_prune,
 *   aw_error_count_daily, directoryweb_stats_build, aw_nl_ff_phone_sync, aw_site_check_deploy, aw_featured_rescan,
 *   aw_geocode_one, aw_thumb_rebuild.
 * Tables: {prefix}aw_form_raw (raw campaign form records) and {prefix}aw_camp_letters (campaign letters).
 *
 * Never deleted, even when ticked:
 * - Posts of any type (listings, News, Library, campaigns, places, social posts, resources), terms, users,
 *   attachments and any post, term or user meta.
 * - Other plugins' data, also where this plugin writes it: Kits Core's (keyword tables, aw_sources, aw_publishers,
 *   aw_relevance), SourceWeb's (aw_enrich and its options), RankWeb's (aw_seo_keep_listings), FluentCRM's
 *   (contact_custom_fields, contact_field_groups, fluentcrm-global-settings), the theme's theme_mods_*, and the form
 *   rate-limit transients aw_rl_* (Kits Core's forms use the same prefix; they expire within the hour).
 * - Files: backups on disk and the drop-ins.
 *
 * Runs for the current site only.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( true !== (bool) get_option( 'aw_delete_data_on_uninstall', false ) ) {
	return; // The default: keep everything.
}

foreach ( array(
	'aw_cdn_ttl',
	'aw_cdn_ttl_long',
	'aw_social_on',
	'aw_social_show',
	'aw_ff_send_email',
	'aw_nl_ff_form_full',
	'aw_sample_listing_ids',
	'aw_district_helplines',
	'aw_issuer_short',
	'aw_follow_reddit',
	'aw_follow_discord',
	'aw_keys',
	'aw_ff_forms',
	'aw_place_intros',
	'aw_elsewhere',
	'aw_internal_org',
	'aw_category_images',
	'aw_press_logos',
	'aw_team_dates',
	'aw_backup_last',
	'aw_camp_close_jobs_removed',
	'aw_camp_dogs_post_set',
	'aw_camp_letters_db',
	'aw_form_raw_db',
	'aw_cc0_log',
	'aw_cl_ref',
	'aw_cron_runs',
	'aw_deployed_head',
	'aw_dispatch_log',
	'aw_error_count',
	'aw_error_pages',
	'aw_geo_last_request',
	'aw_gov_verified_live',
	'aw_helpers_setup',
	'aw_hub_hashes',
	'aw_hub_pages',
	'aw_hub_rewrite',
	'aw_hubs_version',
	'aw_issuers_added',
	'aw_mail_in_old_seen',
	'aw_media_merge_log',
	'aw_media_reuse_log',
	'aw_media_webp_log',
	'aw_model_seeded',
	'aw_ov_last',
	'aw_ov_quota',
	'aw_place_counts',
	'aw_places_rewrite',
	'aw_resources_rewrite',
	'aw_services_seeded',
	'aw_team_dates_1101',
	'aw_team_dates_1101b',
	'aw_team_dates_faq_holdout_off',
	'aw_team_dates_links_note_1111',
	'aw_team_dates_links_read',
	'aw_theme_switch_log',
	'aw_thumb_map',
) as $aw_option ) {
	delete_option( $aw_option );
}

global $wpdb;
$aw_like = static function ( string $start ) use ( $wpdb ): void {
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $start ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- uninstall: options by prefix, once.
};

// Options with a number or a type at the end.
$aw_like( 'aw_media_merge_data_' );
$aw_like( 'aw_lf_gen_' );

foreach ( array(
	'aw_cc0_lock',
	'aw_dir_points',
	'aw_error_count_mailed',
	'aw_error_pages_checked',
	'aw_faq_state_type_counts',
	'aw_faq_study_subjects',
	'aw_featured_aliases',
	'aw_featured_links',
	'aw_featured_names',
	'aw_featured_shared',
	'aw_find_help_types',
	'aw_gov_verified_count',
	'aw_law_mentions',
	'aw_ov_pause',
	'aw_thumb_dupes_n',
	'aw_verified_count',
	'directoryweb_stats',
) as $aw_transient ) {
	delete_transient( $aw_transient );
}
foreach ( array( 'aw_camp_short_notice_', 'aw_cl_cap_', 'aw_cl_h_', 'aw_cl_s_', 'aw_faq_tag_', 'aw_media_reuse_notice_', 'aw_pin_', 'aw_place_error_', 'aw_near_opps_card_', 'aw_clause_', 'aw_cs_', 'aw_ov_', 'aw_orgq_rl_', 'aw_related_', 'aw_more_', 'aw_lf_', 'aw_geo_' ) as $aw_prefix ) {
	$aw_like( '_transient_' . $aw_prefix );
	$aw_like( '_transient_timeout_' . $aw_prefix );
}

foreach ( array(
	'aw_daily',
	'aw_daily_backup',
	'aw_cc0_run',
	'aw_raw_prune',
	'aw_error_count_daily',
	'directoryweb_stats_build',
	'aw_nl_ff_phone_sync',
	'aw_site_check_deploy',
	'aw_featured_rescan',
	'aw_geocode_one',
	'aw_thumb_rebuild',
) as $aw_hook ) {
	wp_unschedule_hook( $aw_hook );
}

foreach ( array( 'aw_form_raw', 'aw_camp_letters' ) as $aw_table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $wpdb->prefix . $aw_table ) . '`' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- uninstall: the plugin's own tables, names fixed above.
}

delete_option( 'aw_delete_data_on_uninstall' ); // Last, so a run that stops half-way can be repeated.
