<?php
/**
 * Newsletter sign-up in Fluent Forms (owner, 16 Oct 2026).
 *
 * - [aw_newsletter] (footer, the "Newsletter box" pattern, campaign pages) shows the Fluent Forms form "Newsletter",
 *   the only sign-up form since 25 Oct 2026 (owner: the site's own form and its handler were removed). Without it:
 *   nothing for visitors, a short note for administrators.
 * - Each sign-up goes to FluentCRM's Newsletter list (subscribed, tag newsletter) by the form's own FluentCRM link.
 * - Fluent Forms' confirmation email (off since 21 Oct 2026: aw_ff_mail_off in forms-fluentforms.php) carries the
 *   unsubscribe link when it is on.
 * The one-time commands `wp aw nl-form` (made the form) and `wp aw nl-import-volunteers` ran and were removed (owner,
 * 25 Oct 2026; see git history).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_NL_FF_OPTION = 'aw_nl_ff_form';

/** The Fluent Forms form ID, or 0 when Fluent Forms is off or the form is missing. */
function aw_nl_ff_id() {
	$id = (int) get_option( AW_NL_FF_OPTION );
	return $id && defined( 'FLUENTFORM' ) ? $id : 0;
}

/* ---------- The form ---------- */

/**
 * [aw_newsletter]: the Fluent Forms form, in the same box (data-kit-form names it for analytics), after what an old
 * confirm or unsubscribe link did (newsletter.php). Without the form: nothing for visitors, a note for administrators.
 */
function aw_newsletter_ff_form() {
	$id = aw_nl_ff_id();
	if ( ! $id ) {
		return aw_nl_link_note() . aw_ff_missing( 'Newsletter', 'wp option update ' . AW_NL_FF_OPTION . ' <form ID>' );
	}
	return aw_nl_link_note() . '<div class="aw-newsletter-form" data-kit-form="newsletter">' . ( function_exists( 'aw_ff_kit_wrap' ) ? aw_ff_kit_wrap( '[fluentform id="' . $id . '"]' ) : do_shortcode( '[fluentform id="' . $id . '"]' ) ) . '</div>';
}
add_shortcode( 'aw_newsletter', 'aw_newsletter_ff_form' );

/* ---------- Mobile (optional) and "On WhatsApp" (owner, 12 Nov 2026) ---------- */

/** Whether a form is one of the two Newsletter forms (#3 and the full #9). */
function aw_nl_ff_is( $form ) {
	$id = (int) ( is_object( $form ) ? $form->id : $form );
	return $id && ( aw_nl_ff_id() === $id || (int) get_option( 'aw_nl_ff_form_full' ) === $id );
}

// The same check as the other forms: 10 digits, or nothing.
add_filter(
	'fluentform/validation_errors',
	function ( $errors, $data, $form ) {
		$v = trim( (string) ( is_string( $data['phone'] ?? '' ) ? ( $data['phone'] ?? '' ) : '' ) );
		if ( aw_nl_ff_is( $form ) && '' !== $v && function_exists( 'aw_ff_phone10' ) && '' === aw_ff_phone10( $v ) && empty( $errors['phone'] ) ) {
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
		if ( ! aw_nl_ff_is( $form_id ) || ! function_exists( 'aw_ff_phone_store' ) ) {
			return $data;
		}
		$ph = trim( (string) ( is_string( $data['phone'] ?? '' ) ? ( $data['phone'] ?? '' ) : '' ) );
		if ( '' !== $ph ) {
			$data['phone'] = aw_ff_phone_store( $ph );
			if ( aw_ff_ticked( $data, 'wa_optin' ) ) {
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
function aw_nl_ff_phone_sync( $email, $phone, $wa, $again = true ) {
	$c = function_exists( 'aw_crm_on' ) && aw_crm_on() && is_email( $email ) ? FluentCrmApi( 'contacts' )->getContact( $email ) : null;
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
add_action( 'aw_nl_ff_phone_sync', 'aw_nl_ff_phone_sync', 10, 4 );

add_action(
	'fluentform/submission_inserted',
	function ( $entry_id, $data, $form ) {
		$ph = trim( sanitize_text_field( (string) ( $data['phone'] ?? '' ) ) );
		if ( aw_nl_ff_is( $form ) && '' !== $ph && function_exists( 'aw_ff_ticked' ) ) {
			$ph = aw_ff_phone_store( $ph );
			aw_nl_ff_phone_sync( strtolower( trim( (string) ( $data['email'] ?? '' ) ) ), $ph, aw_ff_ticked( $data, 'wa_optin' ) );
		}
	},
	20,
	3
);

// Each sign-up is a FluentCRM contact through the form's own Fluent Forms → FluentCRM link (everyone → Newsletter list,
// tag newsletter; /forms/3-newsletter.json). The site's subscriber table is no longer written by the form (owner, 21 Oct
// 2026: FluentCRM keeps people); its rows stay as the backup.

// The confirmation email: plain (no Fluent Forms template or credit) and with this person's unsubscribe link.
add_filter( 'fluentform/send_plain_html_email', fn( $plain, $form ) => (int) $form->id === aw_nl_ff_id() ? true : $plain, 10, 2 );
add_filter(
	'fluentform/email_body',
	function ( $body, $notification, $data, $form ) {
		if ( (int) $form->id !== aw_nl_ff_id() ) {
			return $body;
		}
		$row = aw_nl_get( 'email', strtolower( trim( (string) ( $data['email'] ?? '' ) ) ) );
		return $row ? str_replace( '%%aw_unsubscribe%%', '<a href="' . esc_url( aw_nl_link( 'unsubscribe', $row['token'] ) ) . '">' . esc_html( aw_nl_link( 'unsubscribe', $row['token'] ) ) . '</a>', $body ) : $body;
	},
	10,
	4
);

