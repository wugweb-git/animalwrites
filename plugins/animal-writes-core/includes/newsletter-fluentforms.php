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
 * - "Remove my data" also removes the person's Fluent Forms entries.
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
	return aw_nl_link_note() . '<div class="aw-newsletter-form" data-kit-form="newsletter">' . do_shortcode( '[fluentform id="' . $id . '"]' ) . '</div>';
}
add_shortcode( 'aw_newsletter', 'aw_newsletter_ff_form' );

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

/* ---------- "Remove my data": Fluent Forms entries ---------- */

add_filter(
	'wp_privacy_personal_data_erasers',
	function ( $erasers ) {
		$erasers['aw-fluentforms'] = array(
			'eraser_friendly_name' => 'Form entries (Fluent Forms)',
			'callback'             => function ( $email ) {
				global $wpdb;
				$ids = array();
				if ( defined( 'FLUENTFORM' ) ) {
					$email = strtolower( trim( (string) $email ) );
					$like  = '%' . $wpdb->esc_like( '"' . $email . '"' ) . '%';
					$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, form_id FROM {$wpdb->prefix}fluentform_submissions WHERE LOWER(response) LIKE %s OR id IN (SELECT submission_id FROM {$wpdb->prefix}fluentform_entry_details WHERE LOWER(field_value) = %s)", $like, $email ) ); // phpcs:ignore
					$by    = array();
					foreach ( $rows as $r ) {
						$by[ (int) $r->form_id ][] = (int) $r->id;
						$ids[]                     = (int) $r->id;
					}
					foreach ( $by as $form_id => $sub_ids ) { // Fluent Forms removes entries one form at a time
						\FluentForm\App\Models\Submission::remove( $sub_ids, $form_id );
					}
				}
				return array( 'items_removed' => (bool) $ids, 'items_retained' => false, 'messages' => array(), 'done' => true );
			},
		);
		return $erasers;
	}
);
