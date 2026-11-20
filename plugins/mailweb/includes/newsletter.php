<?php
/**
 * Newsletter: the old sign-up list and its confirm and unsubscribe links. The sign-up form is Fluent Forms
 * (below in this file) and the list is FluentCRM; sending happens in FluentCRM (S2.4).
 *
 * Table {prefix}aw_subscribers: the backup of the list from before FluentCRM (no admin screen since 25 Oct 2026; read
 * it with WP-CLI). Nothing creates or upgrades it any more (owner, 25 Oct 2026: the site's own form and its handler were
 * removed); FluentCRM's unsubscribe marks a row
 * (fluentcrm.php). Statuses: pending, invited, active, unsubscribed. Emails sent before carry a confirm and an
 * unsubscribe link with the row's token; those links keep working (below).
 *
 * Newsletter sign-up in Fluent Forms (owner, 16 Oct 2026).
 *
 * - [aw_newsletter] (footer, the "Newsletter box" pattern, campaign pages) shows the Fluent Forms form "Newsletter",
 *   the only sign-up form since 25 Oct 2026 (owner: the site's own form and its handler were removed). Without it:
 *   nothing for visitors, a short note for administrators.
 * - Each sign-up goes to FluentCRM's Newsletter list (subscribed, tag newsletter) by the form's own FluentCRM link.
 * - Fluent Forms' confirmation email (off since 21 Oct 2026: kits_ff_mail_off in Kits Core forms/fluentforms.php) carries the
 *   unsubscribe link when it is on.
 * The one-time commands `wp aw nl-form` (made the form) and `wp aw nl-import-volunteers` ran and were removed (owner,
 * 25 Oct 2026; see git history).
 *
 * Moved from Animal Writes Core `includes/newsletter.php` and `newsletter-fluentforms.php` (18 Nov 2026); options, meta keys, tags, hooks and
 * slugs are unchanged, and the old function names still work (back-compat wrappers).
 *
 * @package mailweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MAILWEB_NL_CONSENT = 'v2-newsletter-1';

function mailweb_nl_table() {
	global $wpdb;
	return $wpdb->prefix . 'aw_subscribers';
}

function mailweb_nl_get( $field, $value ) {
	global $wpdb;
	$t = mailweb_nl_table();
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE {$field} = %s", $value ), ARRAY_A ); // phpcs:ignore
}

function mailweb_nl_link( $do, $token ) {
	return add_query_arg( array( 'aw_nl' => $do, 't' => $token ), home_url( '/' ) );
}

/** What a confirm or unsubscribe link did, shown where the sign-up sits (?aw_done=, set below), or ''. */
function mailweb_nl_link_note() {
	$msgs = array(
		'nl_confirmed' => 'Confirmed. You are on the list.',
		'nl_unsub'     => 'You are unsubscribed and will not get these emails again.',
		'nl_badlink'   => 'This link has expired or was already used.',
	);
	$done = sanitize_key( wp_unslash( $_GET['aw_done'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	return isset( $msgs[ $done ] ) ? '<div class="kit-form-done" role="status" tabindex="-1"><p class="aw-form-done-title">' . esc_html( $msgs[ $done ] ) . '</p></div>' : '';
}

// Confirm and unsubscribe links (old emails carry them; owner, 25 Oct 2026: keep).
add_action(
	'template_redirect',
	function () {
		$do = sanitize_key( $_GET['aw_nl'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! in_array( $do, array( 'confirm', 'unsubscribe' ), true ) ) {
			return;
		}
		$token = preg_replace( '/[^a-f0-9]/', '', (string) wp_unslash( $_GET['t'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- preg_replace() keeps letters and digits only
		$row   = 32 === strlen( $token ) ? mailweb_nl_get( 'token', $token ) : null;
		$note  = 'nl_badlink';
		global $wpdb;
		$now = current_time( 'mysql', true );
		if ( $row && 'confirm' === $do && in_array( $row['status'], array( 'pending', 'invited', 'active' ), true ) ) {
			if ( 'active' !== $row['status'] ) {
				$wpdb->update( mailweb_nl_table(), array( 'status' => 'active', 'consent_text_version' => MAILWEB_NL_CONSENT . ( 'invited' === $row['status'] ? '-invite' : '' ), 'consent_at' => $now, 'updated_at' => $now ), array( 'id' => (int) $row['id'] ) ); // phpcs:ignore
			}
			// A sign-up confirmed from an old email joins the FluentCRM list too, so it is managed with everyone else.
			if ( function_exists( 'mailweb_fcrm_on' ) && mailweb_fcrm_on() ) {
				mailweb_fcrm_subscribe( $row['email'], (string) ( $row['name'] ?? '' ), array( 'source' => (string) ( $row['source'] ?? '' ) ), array( 'newsletter' ) );
			}
			$note = 'nl_confirmed';
		} elseif ( $row && 'unsubscribe' === $do ) {
			$wpdb->update( mailweb_nl_table(), array( 'status' => 'unsubscribed', 'updated_at' => $now ), array( 'id' => (int) $row['id'] ) ); // phpcs:ignore
			if ( function_exists( 'mailweb_fcrm_on' ) && mailweb_fcrm_on() ) { // and in FluentCRM, where the list is kept
				$c = FluentCrmApi( 'contacts' )->getContact( $row['email'] );
				if ( $c && 'unsubscribed' !== $c->status ) {
					$c->updateStatus( 'unsubscribed' ); // FluentCRM's own status change, so its unsubscribe hooks run
				}
			}
			$note = 'nl_unsub';
		}
		nocache_headers();
		wp_safe_redirect( add_query_arg( 'aw_done', $note, home_url( '/' ) ) . '#newsletter' ); // the sign-up sits just above the footer on every page
		exit;
	}
);

/* ---------- Sending ---------- */

// Newsletters are sent from an external CRM (S2.4, 8 Oct 2026): WordPress keeps the sign-up list only. The old screen's
// last draft and job stay in the options aw_nl_draft, aw_nl_job; its aw_nl_batch job is long gone (none scheduled on
// live, 25 Oct 2026), so the check that cleared it on every load was removed (audit item 17).

/* ---------- The sign-up form in Fluent Forms (was newsletter-fluentforms.php) ---------- */

const MAILWEB_NL_FF_OPTION = 'aw_nl_ff_form';

/** The Fluent Forms form ID, or 0 when Fluent Forms is off or the form is missing. */
function mailweb_nl_ff_id() {
	$id = (int) get_option( MAILWEB_NL_FF_OPTION );
	return $id && defined( 'FLUENTFORM' ) ? $id : 0;
}

/* ---------- The form ---------- */

/**
 * [aw_newsletter]: the Fluent Forms form, in the same box (data-kit-form names it for analytics), after what an old
 * confirm or unsubscribe link did (newsletter.php). Without the form: nothing for visitors, a note for administrators.
 */
function mailweb_nl_ff_form( $attributes = array() ) {
	$id = mailweb_nl_ff_id();
	if ( ! $id ) {
		return mailweb_nl_link_note() . ( function_exists( 'kits_ff_missing' ) ? kits_ff_missing( 'Newsletter', 'wp option update ' . MAILWEB_NL_FF_OPTION . ' <form ID>' ) : '' );
	}

	$layout     = ! empty( $attributes['layout'] ) ? sanitize_html_class( $attributes['layout'] ) : 'default';
	$heading    = ! empty( $attributes['heading'] ) ? '<h3 class="mailweb-nl-heading">' . esc_html( $attributes['heading'] ) . '</h3>' : '';
	$subheading = ! empty( $attributes['subheading'] ) ? '<p class="mailweb-nl-subheading">' . esc_html( $attributes['subheading'] ) . '</p>' : '';
	$align      = ! empty( $attributes['align'] ) ? ' align' . sanitize_html_class( $attributes['align'] ) : '';

	$inner = ( function_exists( 'kits_ff_kit_wrap' ) ? kits_ff_kit_wrap( '[fluentform id="' . $id . '"]' ) : do_shortcode( '[fluentform id="' . $id . '"]' ) );

	return mailweb_nl_link_note()
		. '<div class="aw-newsletter-form aw-newsletter-form--' . esc_attr( $layout ) . esc_attr( $align ) . '" data-kit-form="newsletter">'
		. $heading . $subheading . $inner
		. '</div>';
}
add_shortcode( 'aw_newsletter', 'mailweb_nl_ff_form' );
add_shortcode( 'mailweb_newsletter', 'mailweb_nl_ff_form' );

add_action(
	'init',
	function () {
		if ( function_exists( 'register_block_type' ) ) {
			register_block_type(
				'mailweb/newsletter',
				array(
					'title'           => __( 'Newsletter Signup', 'mailweb' ),
					'description'     => __( 'Newsletter subscription form powered by MailWeb & FluentCRM.', 'mailweb' ),
					'category'        => 'widgets',
					'render_callback' => 'mailweb_nl_ff_form',
					'attributes'      => array(
						'align'      => array(
							'type'    => 'string',
							'default' => '',
						),
						'layout'     => array(
							'type'    => 'string',
							'default' => 'default',
						),
						'heading'    => array(
							'type'    => 'string',
							'default' => '',
						),
						'subheading' => array(
							'type'    => 'string',
							'default' => '',
						),
					),
				)
			);
		}
	}
);

/* ---------- Mobile (optional) and "On WhatsApp" (owner, 12 Nov 2026) ---------- */

/** Whether a form is one of the two Newsletter forms (#3 and the full #9). */
function mailweb_nl_ff_is( $form ) {
	$id = (int) ( is_object( $form ) ? $form->id : $form );
	return $id && ( mailweb_nl_ff_id() === $id || (int) get_option( 'aw_nl_ff_form_full' ) === $id );
}

// The same check as the other forms: 10 digits, or nothing.
add_filter(
	'fluentform/validation_errors',
	function ( $errors, $data, $form ) {
		$v = trim( (string) ( is_string( $data['phone'] ?? '' ) ? ( $data['phone'] ?? '' ) : '' ) );
		if ( mailweb_nl_ff_is( $form ) && '' !== $v && function_exists( 'kits_ff_phone10' ) && '' === kits_ff_phone10( $v ) && empty( $errors['phone'] ) ) {
			$errors['phone'] = array( 'aw' => 'Enter 10 digits.' );
		}
		return $errors;
	},
	20,
	3
);

// Saved as "+91 …"; ticked "On WhatsApp" = the same number is their WhatsApp.
add_filter(
	'fluentform/insert_response_data',
	function ( $data, $form_id ) {
		if ( ! mailweb_nl_ff_is( $form_id ) || ! function_exists( 'kits_ff_phone_store' ) ) {
			return $data;
		}
		$ph = trim( (string) ( is_string( $data['phone'] ?? '' ) ? ( $data['phone'] ?? '' ) : '' ) );
		if ( '' !== $ph ) {
			$data['phone'] = kits_ff_phone_store( $ph );
			if ( kits_ff_ticked( $data, 'wa_optin' ) ) {
				$data['whatsapp'] = $data['phone'];
			}
		}
		return $data;
	},
	10,
	2
);

/**
 * The Mobile into the FluentCRM contact (its phone when it has none; WhatsApp and the tick as 1/0). The form's own link
 * makes the contact and may run later, so a contact not there yet is tried once more two minutes on.
 */
function mailweb_nl_ff_phone_sync( $email, $phone, $wa, $again = true ) {
	$c = function_exists( 'mailweb_fcrm_on' ) && mailweb_fcrm_on() && is_email( $email ) ? FluentCrmApi( 'contacts' )->getContact( $email ) : null;
	if ( ! $c ) {
		if ( $again ) {
			wp_schedule_single_event( time() + 120, 'aw_nl_ff_phone_sync', array( $email, $phone, $wa, false ) );
		}
		return;
	}
	if ( '' === (string) $c->phone ) {
		$c->phone = $phone;
		$c->save();
	}
	$c->syncCustomFieldValues( array_filter( array( 'whatsapp' => $wa ? $phone : '' ) ) + array( 'wa_optin' => $wa ? '1' : '0' ), false );
}
add_action( 'aw_nl_ff_phone_sync', 'mailweb_nl_ff_phone_sync', 10, 4 );

add_action(
	'fluentform/submission_inserted',
	function ( $entry_id, $data, $form ) {
		$ph = trim( sanitize_text_field( (string) ( $data['phone'] ?? '' ) ) );
		if ( mailweb_nl_ff_is( $form ) && '' !== $ph && function_exists( 'kits_ff_ticked' ) ) {
			$ph = kits_ff_phone_store( $ph );
			mailweb_nl_ff_phone_sync( strtolower( trim( (string) ( $data['email'] ?? '' ) ) ), $ph, kits_ff_ticked( $data, 'wa_optin' ) );
		}
	},
	20,
	3
);

// Each sign-up is a FluentCRM contact through the form's own Fluent Forms → FluentCRM link (everyone → Newsletter list,
// tag newsletter; /forms/3-newsletter.json). The site's subscriber table is no longer written by the form (owner, 21 Oct
// 2026: FluentCRM keeps people); its rows stay as the backup.

// The confirmation email: plain (no Fluent Forms template or credit) and with this person's unsubscribe link.
add_filter( 'fluentform/send_plain_html_email', fn( $plain, $form ) => (int) $form->id === mailweb_nl_ff_id() ? true : $plain, 10, 2 );
add_filter(
	'fluentform/email_body',
	function ( $body, $notification, $data, $form ) {
		if ( (int) $form->id !== mailweb_nl_ff_id() ) {
			return $body;
		}
		$row = mailweb_nl_get( 'email', strtolower( trim( (string) ( $data['email'] ?? '' ) ) ) );
		return $row ? str_replace( '%%aw_unsubscribe%%', '<a href="' . esc_url( mailweb_nl_link( 'unsubscribe', $row['token'] ) ) . '">' . esc_html( mailweb_nl_link( 'unsubscribe', $row['token'] ) ) . '</a>', $body ) : $body;
	},
	10,
	4
);
