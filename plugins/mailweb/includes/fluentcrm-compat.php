<?php
/**
 * Old names of the FluentCRM, newsletter and website-forms functions and constants (Animal Writes Core, before 18 Nov 2026).
 *
 * Callers in other plugins and older code still use these names; each one hands over to the new function or
 * constant. Remove a wrapper once nothing calls it (docs/aw-core-callers.md).
 *
 * @package mailweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'AW_CRM_LIST' ) ) {
	define( 'AW_CRM_LIST', MAILWEB_FCRM_LIST );
}
if ( ! defined( 'AW_CRM_BLOCKED_EMAILS' ) ) {
	define( 'AW_CRM_BLOCKED_EMAILS', MAILWEB_FCRM_BLOCKED_EMAILS );
}
if ( ! function_exists( 'aw_crm_blocked' ) ) {
	/** Back-compat: mailweb_fcrm_blocked(). */
	function aw_crm_blocked( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return mailweb_fcrm_blocked( ...$args );
	}
}
if ( ! function_exists( 'aw_crm_on' ) ) {
	/** Back-compat: mailweb_fcrm_on(). */
	function aw_crm_on( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return mailweb_fcrm_on( ...$args );
	}
}
if ( ! function_exists( 'aw_crm_list_id' ) ) {
	/** Back-compat: mailweb_fcrm_list_id(). */
	function aw_crm_list_id( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return mailweb_fcrm_list_id( ...$args );
	}
}
if ( ! defined( 'AW_CRM_TAG_TITLES' ) ) {
	define( 'AW_CRM_TAG_TITLES', MAILWEB_FCRM_TAG_TITLES );
}
if ( ! function_exists( 'aw_crm_tag_ids' ) ) {
	/** Back-compat: mailweb_fcrm_tag_ids(). */
	function aw_crm_tag_ids( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return mailweb_fcrm_tag_ids( ...$args );
	}
}
if ( ! function_exists( 'aw_crm_subscribe' ) ) {
	/** Back-compat: mailweb_fcrm_subscribe(). */
	function aw_crm_subscribe( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return mailweb_fcrm_subscribe( ...$args );
	}
}
if ( ! function_exists( 'aw_crm_unticked' ) ) {
	/** Back-compat: mailweb_fcrm_unticked(). */
	function aw_crm_unticked( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return mailweb_fcrm_unticked( ...$args );
	}
}
if ( ! function_exists( 'aw_crm_claim_note' ) ) {
	/** Back-compat: mailweb_fcrm_claim_note(). */
	function aw_crm_claim_note( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return mailweb_fcrm_claim_note( ...$args );
	}
}
if ( ! function_exists( 'aw_crm_contact_for' ) ) {
	/** Back-compat: mailweb_fcrm_contact_for(). */
	function aw_crm_contact_for( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return mailweb_fcrm_contact_for( ...$args );
	}
}
if ( ! function_exists( 'aw_crm_reply_to' ) ) {
	/** Back-compat: mailweb_fcrm_reply_to(). */
	function aw_crm_reply_to( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return mailweb_fcrm_reply_to( ...$args );
	}
}
if ( ! defined( 'AW_CRM_VOL_FIELDS' ) ) {
	define( 'AW_CRM_VOL_FIELDS', MAILWEB_FCRM_VOL_FIELDS );
}
if ( ! defined( 'AW_CRM_FORM_FIELDS' ) ) {
	define( 'AW_CRM_FORM_FIELDS', MAILWEB_FCRM_FORM_FIELDS );
}
if ( ! defined( 'AW_UPDATE_LINK_DAYS' ) ) {
	define( 'AW_UPDATE_LINK_DAYS', MAILWEB_FCRM_UPDATE_LINK_DAYS );
}
if ( ! function_exists( 'aw_update_link' ) ) {
	/** Back-compat: mailweb_fcrm_update_link(). */
	function aw_update_link( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return mailweb_fcrm_update_link( ...$args );
	}
}
if ( ! function_exists( 'aw_update_link_contact' ) ) {
	/** Back-compat: mailweb_fcrm_update_link_contact(). */
	function aw_update_link_contact( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return mailweb_fcrm_update_link_contact( ...$args );
	}
}
if ( ! function_exists( 'aw_crm_fields_setup' ) ) {
	/** Back-compat: mailweb_fcrm_fields_setup(). */
	function aw_crm_fields_setup( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return mailweb_fcrm_fields_setup( ...$args );
	}
}
if ( ! function_exists( 'aw_crm_nl_box' ) ) {
	/** Back-compat: mailweb_fcrm_nl_box(). */
	function aw_crm_nl_box( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return mailweb_fcrm_nl_box( ...$args );
	}
}
if ( ! function_exists( 'aw_crm_entry_note' ) ) {
	/** Back-compat: mailweb_fcrm_entry_note(). */
	function aw_crm_entry_note( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return mailweb_fcrm_entry_note( ...$args );
	}
}
if ( ! function_exists( 'aw_crm_campaign_signer' ) ) {
	/** Back-compat: mailweb_fcrm_campaign_signer(). */
	function aw_crm_campaign_signer( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return mailweb_fcrm_campaign_signer( ...$args );
	}
}
if ( ! function_exists( 'aw_crm_utm_campaign' ) ) {
	/** Back-compat: mailweb_fcrm_utm_campaign(). */
	function aw_crm_utm_campaign( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return mailweb_fcrm_utm_campaign( ...$args );
	}
}
if ( ! defined( 'AW_MAIL_FF_ID' ) ) {
	define( 'AW_MAIL_FF_ID', MAILWEB_WF_ID );
}
if ( ! defined( 'AW_MAIL_FF_KEYS' ) ) {
	define( 'AW_MAIL_FF_KEYS', MAILWEB_WF_KEYS );
}
if ( ! function_exists( 'aw_mail_forms_where' ) ) {
	/** Back-compat: mailweb_wf_where(). */
	function aw_mail_forms_where( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return mailweb_wf_where( ...$args );
	}
}
if ( ! function_exists( 'aw_mail_ff_forms' ) ) {
	/** Back-compat: mailweb_wf_forms(). */
	function aw_mail_ff_forms( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return mailweb_wf_forms( ...$args );
	}
}
if ( ! function_exists( 'aw_mail_ff_entry' ) ) {
	/** Back-compat: mailweb_wf_entry(). */
	function aw_mail_ff_entry( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return mailweb_wf_entry( ...$args );
	}
}
if ( ! function_exists( 'aw_mail_ff_key' ) ) {
	/** Back-compat: mailweb_wf_key(). */
	function aw_mail_ff_key( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return mailweb_wf_key( ...$args );
	}
}
if ( ! function_exists( 'aw_mail_ff_ref' ) ) {
	/** Back-compat: mailweb_wf_ref(). */
	function aw_mail_ff_ref( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return mailweb_wf_ref( ...$args );
	}
}
if ( ! function_exists( 'aw_mail_ff_subject' ) ) {
	/** Back-compat: mailweb_wf_subject(). */
	function aw_mail_ff_subject( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return mailweb_wf_subject( ...$args );
	}
}
if ( ! function_exists( 'aw_mail_ff_fields' ) ) {
	/** Back-compat: mailweb_wf_fields(). */
	function aw_mail_ff_fields( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return mailweb_wf_fields( ...$args );
	}
}
if ( ! function_exists( 'aw_mail_ff_value' ) ) {
	/** Back-compat: mailweb_wf_value(). */
	function aw_mail_ff_value( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return mailweb_wf_value( ...$args );
	}
}
if ( ! function_exists( 'aw_mail_ff_text' ) ) {
	/** Back-compat: mailweb_wf_text(). */
	function aw_mail_ff_text( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return mailweb_wf_text( ...$args );
	}
}
if ( ! function_exists( 'aw_mail_ff_unread_counts' ) ) {
	/** Back-compat: mailweb_wf_unread_counts(). */
	function aw_mail_ff_unread_counts( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return mailweb_wf_unread_counts( ...$args );
	}
}
if ( ! function_exists( 'aw_mail_site_check' ) ) {
	/** Back-compat: mailweb_wf_site_check(). */
	function aw_mail_site_check( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return mailweb_wf_site_check( ...$args );
	}
}
if ( ! defined( 'AW_NL_FF_OPTION' ) ) {
	define( 'AW_NL_FF_OPTION', MAILWEB_NL_FF_OPTION );
}
if ( ! function_exists( 'aw_nl_ff_id' ) ) {
	/** Back-compat: mailweb_nl_ff_id(). */
	function aw_nl_ff_id( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return mailweb_nl_ff_id( ...$args );
	}
}
if ( ! function_exists( 'aw_newsletter_ff_form' ) ) {
	/** Back-compat: mailweb_nl_ff_form(). */
	function aw_newsletter_ff_form( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return mailweb_nl_ff_form( ...$args );
	}
}
if ( ! function_exists( 'aw_nl_ff_is' ) ) {
	/** Back-compat: mailweb_nl_ff_is(). */
	function aw_nl_ff_is( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return mailweb_nl_ff_is( ...$args );
	}
}
if ( ! function_exists( 'aw_nl_ff_phone_sync' ) ) {
	/** Back-compat: mailweb_nl_ff_phone_sync(). */
	function aw_nl_ff_phone_sync( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return mailweb_nl_ff_phone_sync( ...$args );
	}
}
if ( ! defined( 'AW_NL_CONSENT' ) ) {
	define( 'AW_NL_CONSENT', MAILWEB_NL_CONSENT );
}
if ( ! function_exists( 'aw_nl_table' ) ) {
	/** Back-compat: mailweb_nl_table(). */
	function aw_nl_table( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return mailweb_nl_table( ...$args );
	}
}
if ( ! function_exists( 'aw_nl_get' ) ) {
	/** Back-compat: mailweb_nl_get(). */
	function aw_nl_get( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return mailweb_nl_get( ...$args );
	}
}
if ( ! function_exists( 'aw_nl_link' ) ) {
	/** Back-compat: mailweb_nl_link(). */
	function aw_nl_link( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return mailweb_nl_link( ...$args );
	}
}
if ( ! function_exists( 'aw_nl_link_note' ) ) {
	/** Back-compat: mailweb_nl_link_note(). */
	function aw_nl_link_note( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return mailweb_nl_link_note( ...$args );
	}
}
