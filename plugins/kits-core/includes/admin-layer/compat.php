<?php
/**
 * The admin layer's older names (Animal Writes Core, before 18 Nov 2026), as thin wrappers over the kits_ functions,
 * for the callers that still use them (Animal Writes Core's own screens, SourceWeb's menu counts, PlanWeb's Reset this
 * section). No new caller uses these; each goes once nothing calls it.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- the older names, kept for their callers.

if ( ! defined( 'AW_CORE_MENU' ) ) {
	define( 'AW_CORE_MENU', 'animal-writes' ); // the Overview's old slug; never the menu parent with Kits Core loaded
}
if ( ! defined( 'AW_FOLLOW_EXTRA' ) ) {
	define( 'AW_FOLLOW_EXTRA', KITS_FOLLOW_EXTRA );
}

/** Older name of kits_site_menu_parent(). */
function aw_core_menu_parent(): string {
	return kits_site_menu_parent();
}

/**
 * Older name of kits_site_screens().
 *
 * @param bool $all Include screens that left.
 */
function aw_core_screens( bool $all = false ): array {
	return kits_site_screens( $all );
}

/**
 * Older name of kits_site_menu_priority().
 *
 * @param string $slug Screen slug.
 */
function aw_core_menu_priority( string $slug ): int {
	return kits_site_menu_priority( $slug );
}

/**
 * Older name of kits_site_screen_cap().
 *
 * @param string $slug Screen slug.
 */
function aw_core_screen_cap( string $slug ): string {
	return kits_site_screen_cap( $slug );
}

/**
 * Older name of kits_site_screen_nav().
 *
 * @param string $current Screen slug.
 */
function aw_core_screen_nav( string $current ): void {
	kits_site_screen_nav( $current );
}

/**
 * Older name of kits_site_link().
 *
 * @param string $label Link text.
 * @param string $path  Path relative to wp-admin.
 */
function aw_core_link( string $label, string $path ): string {
	return kits_site_link( $label, $path );
}

/**
 * Older name of kits_site_status_cell().
 *
 * @param bool   $ok   Fine or a warning.
 * @param string $text Text.
 */
function aw_core_status( bool $ok, string $text ): string {
	return kits_site_status_cell( $ok, $text );
}

/**
 * Older name of kits_site_status().
 *
 * @param bool $full False: only backup, check, cron and deploy.
 */
function aw_core_overview_status( bool $full = true ): array {
	return kits_site_status( $full );
}

/**
 * Older name of kits_site_screen_url().
 *
 * @param string $slug Screen slug.
 */
function aw_core_screen_url( string $slug ): string {
	return kits_site_screen_url( $slug );
}

/** Older name of kits_site_overview_page(). */
function aw_core_overview_page() {
	kits_site_overview_page();
}

/** Older name of kits_site_reset_sections(). */
function aw_core_reset_sections(): array {
	return kits_site_reset_sections();
}

/**
 * Older name of kits_site_reset_options().
 *
 * @param string[] $names Option names.
 */
function aw_core_reset_options( array $names ): bool {
	return kits_site_reset_options( $names );
}

/**
 * Older name of kits_site_reset_form().
 *
 * @param string $section Section id.
 */
function aw_core_reset_form( string $section ): void {
	kits_site_reset_form( $section );
}

/**
 * Older name of kits_site_reset_button().
 *
 * @param string $section Section id.
 */
function aw_core_reset_button( string $section ): void {
	kits_site_reset_button( $section );
}

/** Older name of kits_modules_sections(). */
function aw_modules_sections(): void {
	kits_modules_sections();
}

/** Older name of kits_modules_reset_forms(). */
function aw_modules_reset_forms(): void {
	kits_modules_reset_forms();
}

/**
 * Older name of kits_menu_count().
 *
 * @param int $n Count.
 */
function aw_menu_count( $n ) {
	return kits_menu_count( $n );
}

/**
 * Older name of kits_edit_fields_metrics_box().
 *
 * @param WP_Post $post Campaign.
 */
function aw_edit_fields_metrics_box( $post ): void {
	kits_edit_fields_metrics_box( $post );
}

/** Older name of kits_edit_screens_page(). */
function aw_edit_screens_page(): void {
	kits_edit_screens_page();
}

/**
 * Older name of kits_follow_url_clean().
 *
 * @param mixed    $value Posted value.
 * @param string[] $hosts Allowed hosts.
 */
function aw_follow_url_clean( $value, array $hosts ): string {
	return kits_follow_url_clean( $value, $hosts );
}
