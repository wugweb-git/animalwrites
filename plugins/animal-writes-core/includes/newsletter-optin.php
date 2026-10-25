<?php
/**
 * Newsletter growth, consent only (owner, 9 Oct 2026; DPDP: consent per purpose).
 *
 * - Contact and Listing claim forms carry an unticked box with the footer form's wording. Ticked → the
 *   person goes onto the newsletter list as "active" with the form as source (16 Oct 2026). Unticked → nothing is added. (The Report form asks for no email, so it has no box.)
 * - Volunteers: the updates box is shown ticked and volunteers join the newsletter whether or not it stays ticked
 *   (owner, 20 Oct 2026; volunteers.php). Claimants, contact senders and incoming emails are never added on their own.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_NL_OPTIN_LABEL = 'Email me Animal Writes updates. I can unsubscribe at any time.';

/** The opt-in box for a form: unticked, or ticked when $ticked (the volunteer form, owner 20 Oct 2026). */
function aw_nl_optin_box( $form, $ticked = false ) {
	return aw_field( $form, array( 'name' => 'nl_optin', 'label' => AW_NL_OPTIN_LABEL, 'type' => 'checkbox', 'value' => $ticked ? '1' : '' ) );
}

/** After a form succeeds: only when the box was ticked, add to the newsletter (site list active, FluentCRM subscribed;
 *  owner, 16 Oct 2026: no double opt-in; the form's own email is the only one sent). */
function aw_nl_optin( $email, $name, $source, $ticked = null, $consent = AW_NL_CONSENT ) {
	$ticked = null === $ticked ? ! empty( $_POST['nl_optin'] ) : (bool) $ticked; // phpcs:ignore WordPress.Security.NonceVerification -- the site's own forms post it; Fluent Forms passes it
	if ( ! $ticked || ! is_email( $email ) || ! function_exists( 'aw_newsletter_join' ) ) {
		return;
	}
	aw_newsletter_join( $email, $name, 'site:' . $source, $consent, array(), array( 'newsletter' ) ); // site list and FluentCRM
}
