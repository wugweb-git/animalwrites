<?php
/**
 * Old names of the form functions and constants (Animal Writes Core, before 18 Nov 2026).
 *
 * Callers in other plugins and older code still use these names; each one hands over to the new function or
 * constant. Remove a wrapper once nothing calls it (docs/aw-core-callers.md).
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'aw_forms_widget_allowed' ) ) {
	/** Back-compat: kits_forms_widget_allowed(). */
	function aw_forms_widget_allowed( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_forms_widget_allowed( ...$args );
	}
}
if ( ! function_exists( 'aw_forms_widget_rows' ) ) {
	/** Back-compat: kits_forms_widget_rows(). */
	function aw_forms_widget_rows( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_forms_widget_rows( ...$args );
	}
}
if ( ! function_exists( 'aw_volunteer_source' ) ) {
	/** Back-compat: kits_volunteer_source(). */
	function aw_volunteer_source( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_volunteer_source( ...$args );
	}
}
if ( ! function_exists( 'aw_block_volunteer' ) ) {
	/** Back-compat: kits_block_volunteer(). */
	function aw_block_volunteer( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_block_volunteer( ...$args );
	}
}
if ( ! defined( 'AW_CORRECTION_FIELDS' ) ) {
	define( 'AW_CORRECTION_FIELDS', KITS_CORRECTION_FIELDS );
}
if ( ! function_exists( 'aw_report_url' ) ) {
	/** Back-compat: kits_report_url(). */
	function aw_report_url( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_report_url( ...$args );
	}
}
if ( ! function_exists( 'aw_form_page_for' ) ) {
	/** Back-compat: kits_form_page_for(). */
	function aw_form_page_for( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_form_page_for( ...$args );
	}
}
if ( ! function_exists( 'aw_thanks_forms' ) ) {
	/** Back-compat: kits_ff_thanks_forms(). */
	function aw_thanks_forms( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_ff_thanks_forms( ...$args );
	}
}
if ( ! function_exists( 'aw_thanks_head' ) ) {
	/** Back-compat: kits_ff_thanks_head(). */
	function aw_thanks_head( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_ff_thanks_head( ...$args );
	}
}
if ( ! function_exists( 'aw_ff_mail_off' ) ) {
	/** Back-compat: kits_ff_mail_off(). */
	function aw_ff_mail_off( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_ff_mail_off( ...$args );
	}
}
if ( ! defined( 'AW_FF_OPTION' ) ) {
	define( 'AW_FF_OPTION', KITS_FF_OPTION );
}
if ( ! defined( 'AW_FF_FORMS' ) ) {
	define( 'AW_FF_FORMS', KITS_FF_FORMS );
}
if ( ! defined( 'AW_FF_EXTRA' ) ) {
	define( 'AW_FF_EXTRA', KITS_FF_EXTRA );
}
if ( ! defined( 'AW_FF_UPLOADS' ) ) {
	define( 'AW_FF_UPLOADS', KITS_FF_UPLOADS );
}
if ( ! defined( 'AW_FF_PHONES' ) ) {
	define( 'AW_FF_PHONES', KITS_FF_PHONES );
}
if ( ! defined( 'AW_FF_SOCIAL' ) ) {
	define( 'AW_FF_SOCIAL', KITS_FF_SOCIAL );
}
if ( ! defined( 'AW_FF_DATES' ) ) {
	define( 'AW_FF_DATES', KITS_FF_DATES );
}
if ( ! function_exists( 'aw_ff_phone10' ) ) {
	/** Back-compat: kits_ff_phone10(). */
	function aw_ff_phone10( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_ff_phone10( ...$args );
	}
}
if ( ! function_exists( 'aw_ff_phone_store' ) ) {
	/** Back-compat: kits_ff_phone_store(). */
	function aw_ff_phone_store( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_ff_phone_store( ...$args );
	}
}
if ( ! defined( 'AW_FF_SOCIAL_HOSTS' ) ) {
	define( 'AW_FF_SOCIAL_HOSTS', KITS_FF_SOCIAL_HOSTS );
}
if ( ! function_exists( 'aw_ff_social_user' ) ) {
	/** Back-compat: kits_ff_social_user(). */
	function aw_ff_social_user( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_ff_social_user( ...$args );
	}
}
if ( ! function_exists( 'aw_ff_date_ymd' ) ) {
	/** Back-compat: kits_ff_date_ymd(). */
	function aw_ff_date_ymd( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_ff_date_ymd( ...$args );
	}
}
if ( ! function_exists( 'aw_ff_normalise' ) ) {
	/** Back-compat: kits_ff_normalise(). */
	function aw_ff_normalise( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_ff_normalise( ...$args );
	}
}
if ( ! function_exists( 'aw_ff_id' ) ) {
	/** Back-compat: kits_ff_id(). */
	function aw_ff_id( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_ff_id( ...$args );
	}
}
if ( ! function_exists( 'aw_ff_key' ) ) {
	/** Back-compat: kits_ff_key(). */
	function aw_ff_key( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_ff_key( ...$args );
	}
}
if ( ! function_exists( 'aw_ff_styled' ) ) {
	/** Back-compat: kits_ff_styled(). */
	function aw_ff_styled( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_ff_styled( ...$args );
	}
}
if ( ! function_exists( 'aw_ff_in_kit_wrap' ) ) {
	/** Back-compat: kits_ff_in_kit_wrap(). */
	function aw_ff_in_kit_wrap( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_ff_in_kit_wrap( ...$args );
	}
}
if ( ! function_exists( 'aw_ff_kit_wrap' ) ) {
	/** Back-compat: kits_ff_kit_wrap(). */
	function aw_ff_kit_wrap( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_ff_kit_wrap( ...$args );
	}
}
if ( ! function_exists( 'aw_ff_ctx' ) ) {
	/** Back-compat: kits_ff_ctx(). */
	function aw_ff_ctx( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_ff_ctx( ...$args );
	}
}
if ( ! function_exists( 'aw_ff_form' ) ) {
	/** Back-compat: kits_ff_form(). */
	function aw_ff_form( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_ff_form( ...$args );
	}
}
if ( ! function_exists( 'aw_ff_missing' ) ) {
	/** Back-compat: kits_ff_missing(). */
	function aw_ff_missing( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_ff_missing( ...$args );
	}
}
if ( ! function_exists( 'aw_ff_source' ) ) {
	/** Back-compat: kits_ff_source(). */
	function aw_ff_source( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_ff_source( ...$args );
	}
}
if ( ! function_exists( 'aw_ff_group' ) ) {
	/** Back-compat: kits_ff_group(). */
	function aw_ff_group( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_ff_group( ...$args );
	}
}
if ( ! function_exists( 'aw_ff_render_places' ) ) {
	/** Back-compat: kits_ff_render_places(). */
	function aw_ff_render_places( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_ff_render_places( ...$args );
	}
}
if ( ! function_exists( 'aw_ff_render_check' ) ) {
	/** Back-compat: kits_ff_render_check(). */
	function aw_ff_render_check( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_ff_render_check( ...$args );
	}
}
if ( ! function_exists( 'aw_ff_render_logo' ) ) {
	/** Back-compat: kits_ff_render_logo(). */
	function aw_ff_render_logo( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_ff_render_logo( ...$args );
	}
}
if ( ! defined( 'AW_FF_OPTION_ICONS' ) ) {
	define( 'AW_FF_OPTION_ICONS', KITS_FF_OPTION_ICONS );
}
if ( ! defined( 'AW_FF_ICONS' ) ) {
	define( 'AW_FF_ICONS', KITS_FF_ICONS );
}
if ( ! function_exists( 'aw_ff_ticked' ) ) {
	/** Back-compat: kits_ff_ticked(). */
	function aw_ff_ticked( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_ff_ticked( ...$args );
	}
}
if ( ! function_exists( 'aw_ff_logo_file' ) ) {
	/** Back-compat: kits_ff_logo_file(). */
	function aw_ff_logo_file( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_ff_logo_file( ...$args );
	}
}
if ( ! function_exists( 'aw_ff_keep_upload' ) ) {
	/** Back-compat: kits_ff_keep_upload(). */
	function aw_ff_keep_upload( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_ff_keep_upload( ...$args );
	}
}
if ( ! function_exists( 'aw_ff_ref' ) ) {
	/** Back-compat: kits_ff_ref(). */
	function aw_ff_ref( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_ff_ref( ...$args );
	}
}
if ( ! function_exists( 'aw_ff_errors' ) ) {
	/** Back-compat: kits_ff_errors(). */
	function aw_ff_errors( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_ff_errors( ...$args );
	}
}
if ( ! function_exists( 'aw_ff_rl' ) ) {
	/** Back-compat: kits_ff_rl(). */
	function aw_ff_rl( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_ff_rl( ...$args );
	}
}
if ( ! function_exists( 'aw_ff_upload' ) ) {
	/** Back-compat: kits_ff_upload(). */
	function aw_ff_upload( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_ff_upload( ...$args );
	}
}
if ( ! function_exists( 'aw_report_reasons' ) ) {
	/** Back-compat: kits_report_reasons(). */
	function aw_report_reasons( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_report_reasons( ...$args );
	}
}
if ( ! defined( 'AW_REPORT_OLD_REASONS' ) ) {
	define( 'AW_REPORT_OLD_REASONS', KITS_REPORT_OLD_REASONS );
}
if ( ! defined( 'AW_REPORT_TYPES' ) ) {
	define( 'AW_REPORT_TYPES', KITS_REPORT_TYPES );
}
if ( ! defined( 'AW_REPORT_STATUSES' ) ) {
	define( 'AW_REPORT_STATUSES', KITS_REPORT_STATUSES );
}
if ( ! function_exists( 'aw_report_item' ) ) {
	/** Back-compat: kits_report_item(). */
	function aw_report_item( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_report_item( ...$args );
	}
}
if ( ! function_exists( 'aw_report_get_item' ) ) {
	/** Back-compat: kits_report_get_item(). */
	function aw_report_get_item( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_report_get_item( ...$args );
	}
}
if ( ! function_exists( 'aw_report_form' ) ) {
	/** Back-compat: kits_report_form(). */
	function aw_report_form( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_report_form( ...$args );
	}
}
if ( ! function_exists( 'aw_report_status' ) ) {
	/** Back-compat: kits_report_status(). */
	function aw_report_status( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_report_status( ...$args );
	}
}
if ( ! defined( 'AW_CF_OPTION' ) ) {
	define( 'AW_CF_OPTION', KITS_CF_OPTION );
}
if ( ! defined( 'AW_RAW_DB' ) ) {
	define( 'AW_RAW_DB', KITS_RAW_DB );
}
if ( ! defined( 'AW_RAW_DAYS' ) ) {
	define( 'AW_RAW_DAYS', KITS_RAW_DAYS );
}
if ( ! defined( 'AW_CF_RL_LIMIT' ) ) {
	define( 'AW_CF_RL_LIMIT', KITS_CF_RL_LIMIT );
}
if ( ! function_exists( 'aw_cf_id' ) ) {
	/** Back-compat: kits_cf_id(). */
	function aw_cf_id( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_cf_id( ...$args );
	}
}
if ( ! function_exists( 'aw_raw_table' ) ) {
	/** Back-compat: kits_raw_table(). */
	function aw_raw_table( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_raw_table( ...$args );
	}
}
if ( ! function_exists( 'aw_raw_save' ) ) {
	/** Back-compat: kits_raw_save(). */
	function aw_raw_save( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_raw_save( ...$args );
	}
}
if ( ! function_exists( 'aw_raw_mark' ) ) {
	/** Back-compat: kits_raw_mark(). */
	function aw_raw_mark( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_raw_mark( ...$args );
	}
}
if ( ! function_exists( 'aw_cf_campaign' ) ) {
	/** Back-compat: kits_cf_campaign(). */
	function aw_cf_campaign( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_cf_campaign( ...$args );
	}
}
if ( ! function_exists( 'aw_cf_altcha' ) ) {
	/** Back-compat: kits_cf_altcha(). */
	function aw_cf_altcha( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_cf_altcha( ...$args );
	}
}
if ( ! function_exists( 'aw_cf_done' ) ) {
	/** Back-compat: kits_cf_done(). */
	function aw_cf_done( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_cf_done( ...$args );
	}
}
if ( ! defined( 'AW_FF_BOXES' ) ) {
	define( 'AW_FF_BOXES', KITS_FF_BOXES );
}
if ( ! defined( 'AW_VOL_ROLES' ) ) {
	define( 'AW_VOL_ROLES', KITS_VOL_ROLES );
}
if ( ! function_exists( 'aw_volunteer_form' ) ) {
	/** Back-compat: kits_volunteer_form(). */
	function aw_volunteer_form( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_volunteer_form( ...$args );
	}
}
if ( ! function_exists( 'aw_volunteer_values' ) ) {
	/** Back-compat: kits_volunteer_values(). */
	function aw_volunteer_values( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_volunteer_values( ...$args );
	}
}
if ( ! function_exists( 'aw_volunteer_errors' ) ) {
	/** Back-compat: kits_volunteer_errors(). */
	function aw_volunteer_errors( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_volunteer_errors( ...$args );
	}
}
if ( ! function_exists( 'aw_ff_update_values' ) ) {
	/** Back-compat: kits_ff_update_values(). */
	function aw_ff_update_values( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_ff_update_values( ...$args );
	}
}
if ( ! defined( 'AW_VOL_CAP' ) ) {
	define( 'AW_VOL_CAP', KITS_VOL_CAP );
}
if ( ! function_exists( 'aw_vol_table' ) ) {
	/** Back-compat: kits_vol_table(). */
	function aw_vol_table( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return kits_vol_table( ...$args );
	}
}
