<?php
/**
 * DPDP "Remove my data": [aw_remove_data] on /remove-my-data/ (the Fluent Forms form #8). Request-only (owner, 21 Oct 2026).
 *
 * 1. A visitor gives their email, optionally their name, what to remove (Everything, Newsletter only, Volunteer details,
 *    My messages) and a reason (ALTCHA, rate limited). Nothing is erased by the form.
 * 2. WordPress's own data request is made (New: "pending"), with the answers and its reference (AW-D-…), and its
 *    confirmation email is sent.
 * 3. The link in that email only marks the request "Email verified" (WordPress's "confirmed"). Nothing is erased.
 * 4. An administrator reviews it and erases by hand in Tools → Erase Personal Data ("Erase personal data" runs every
 *    eraser below plus WordPress's comments and media, then marks it completed: Done), or declines it from the Dashboard.
 * The Dashboard notice and the Inbox count show requests while New or Email verified (form-alerts.php).
 * Contact messages (Inbox › Messages) go too (owner, 16 Oct 2026); the log keeps only how many.
 * The FluentCRM contact goes too (fluentcrm.php), with its notes (the copies of their messages), volunteer fields and
 * the emails sent to them.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The form. */
function aw_remove_data_form() {
	$done = sanitize_key( wp_unslash( $_GET['aw_done'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( 'rd_check' === $done ) {
		$to = sanitize_email( wp_unslash( $_GET['to'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		return '<div class="kit-form-done" role="status" tabindex="-1"><p class="aw-form-done-title">Check your inbox</p><p>' . esc_html( sprintf( 'We\'ve sent a link to %s. Open it to confirm the request; we then review it and remove your details.', $to ) ) . '</p></div>';
	}
	$ff = apply_filters( 'aw_form_html', '', 'remove_data', array() ); // the Fluent Forms version when it is set up (forms-fluentforms.php)
	if ( '' !== $ff ) {
		return $ff;
	}
	$form  = 'aw_remove_data';
	$html  = aw_form_open( $form, array( 'class' => 'kit-form aw-remove-data-form' ) );
	$html .= kits_dict_field( $form, 'email' );
	$html .= '<button type="submit" class="kit-btn">Send confirmation link</button></form>';
	return $html;
}
add_shortcode( 'aw_remove_data', 'aw_remove_data_form' );

/**
 * WordPress's "Remove my data" confirmation email: the one email a form still sends (owner, 21 Oct 2026), even while form
 * submissions send nothing else (aw_ff_mail_off, forms-fluentforms.php). Used by this form and the Contact form's
 * "Remove my data" topic (pages.php).
 *
 * @param int $request_id user_request ID.
 */
function aw_send_removal_link( $request_id ) {
	$blocked = remove_filter( 'pre_wp_mail', '__return_true', 999 );
	wp_send_user_request( $request_id );
	if ( $blocked ) {
		add_filter( 'pre_wp_mail', '__return_true', 999 );
	}
}

/**
 * Start a removal for an email: WordPress's own request and its confirmation email. The same answer whether or not a
 * request was already open, so the form says nothing about who is on file.
 *
 * @return array array{url, title, text} for the thank-you page.
 */
/** What a removal request may ask to remove. */
const AW_REMOVAL_SCOPES = array( 'everything' => 'Everything', 'newsletter' => 'Newsletter only', 'volunteer' => 'Volunteer details', 'messages' => 'My messages' );

function aw_remove_data_save( $email, $extra = array() ) {
	// Never FluentCRM: this person asks us to hold less, so no contact is added or updated here.
	$id = wp_create_user_request( $email, 'remove_personal_data' );
	if ( ! is_wp_error( $id ) ) {
		foreach ( array( 'name', 'reason', 'ref', 'entry' ) as $k ) {
			if ( ! empty( $extra[ $k ] ) ) {
				update_post_meta( $id, 'aw_dr_' . $k, $extra[ $k ] );
			}
		}
		update_post_meta( $id, 'aw_dr_scope', isset( AW_REMOVAL_SCOPES[ $extra['scope'] ?? '' ] ) ? $extra['scope'] : 'everything' );
		aw_send_removal_link( $id );
	}
	$text = sprintf( 'We\'ve sent a link to %s. Open it to confirm the request; we then review it and remove your details.', $email );
	return array( 'url' => kits_thanks_url( 'remove-data', array(), 'Check your inbox', $text ), 'title' => 'Check your inbox', 'text' => $text );
}

function aw_remove_data_submit() {
	$form = 'aw_remove_data';
	$back = aw_form_back( '/remove-my-data/' );
	$rl   = aw_form_guard( $form, $back, 'remove-data', kits_thanks_url( 'remove-data' ), 3 );
	$v    = array( 'email' => aw_clean_email( sanitize_text_field( wp_unslash( $_POST['email'] ?? '' ) ) ) );
	$errors = aw_form_validate( array( 'email' => array( 'required', 'email' ) ), $v, array( 'email' => 'Email' ) );
	if ( $errors ) {
		aw_form_fail( $form, $errors, $v, $back, 'remove-data' );
	}
	$res = aw_remove_data_save( $v['email'] );
	aw_form_count( $rl );
	aw_form_success( $res['url'], $res['title'], $res['text'] );
}
add_action( 'admin_post_nopriv_aw_remove_data', 'aw_remove_data_submit' );
add_action( 'admin_post_aw_remove_data', 'aw_remove_data_submit' );

/* ---------- Erasers (one per place we keep a person's details) ---------- */

add_filter(
	'wp_privacy_personal_data_erasers',
	function ( $erasers ) {
		$erasers['aw-volunteers'] = array(
			'eraser_friendly_name' => 'Volunteers',
			'callback'             => function ( $email ) {
				global $wpdb;
				$n = (int) $wpdb->delete( aw_vol_table(), array( 'email' => strtolower( $email ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				return array( 'items_removed' => $n > 0, 'items_retained' => false, 'messages' => array(), 'done' => true );
			},
		);
		$erasers['aw-subscribers'] = array(
			'eraser_friendly_name' => 'Newsletter',
			'callback'             => function ( $email ) {
				global $wpdb;
				$n = (int) $wpdb->delete( aw_nl_table(), array( 'email' => strtolower( $email ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				return array( 'items_removed' => $n > 0, 'items_retained' => false, 'messages' => array(), 'done' => true );
			},
		);
		$erasers['aw-claims'] = array(
			'eraser_friendly_name' => 'Listing claims',
			'callback'             => function ( $email ) {
				$ids = get_posts( array( 'post_type' => 'aw_claim', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1, 'meta_key' => 'aw_c_email', 'meta_value' => $email ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
				foreach ( $ids as $cid ) { // the claim stays (the listing's history); the person's details go
					$proof = (string) get_post_meta( $cid, 'aw_c_proof', true );
					if ( '' !== $proof ) {
						wp_delete_file( aw_private_dir() . '/' . basename( $proof ) );
					}
					foreach ( array( 'name', 'role', 'email', 'phone', 'notes', 'whatsapp', 'wa_optin', 'proof' ) as $k ) {
						delete_post_meta( $cid, 'aw_c_' . $k );
					}
					$lid = (int) get_post_meta( $cid, 'aw_c_listing', true );
					wp_update_post( array( 'ID' => $cid, 'post_title' => ( $lid ? get_the_title( $lid ) : 'Claim' ) . ': details removed' ) );
				}
				return array( 'items_removed' => (bool) $ids, 'items_retained' => false, 'messages' => array(), 'done' => true );
			},
		);
		// Contact messages (owner, 16 Oct 2026): the site's form and incoming email both keep the sender in aw_m_email.
		// Matched without regard to case; the message, anything attached to it, and its Fluent Forms copy (the
		// aw-fluentforms eraser) go. Only the number removed is logged.
		$erasers['aw-messages'] = array(
			'eraser_friendly_name' => 'Contact messages (Inbox)',
			'callback'             => function ( $email ) {
				global $wpdb;
				$ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'aw_m_email' WHERE p.post_type = 'aw_message' AND LOWER(TRIM(m.meta_value)) = %s", strtolower( trim( (string) $email ) ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$n   = 0;
				foreach ( array_unique( $ids ) as $mid ) {
					foreach ( get_children( array( 'post_parent' => $mid, 'post_type' => 'attachment', 'fields' => 'ids' ) ) as $att ) {
						wp_delete_attachment( $att, true );
					}
					$n += wp_delete_post( $mid, true ) ? 1 : 0;
				}
				$GLOBALS['aw_erased_messages'] = (int) ( $GLOBALS['aw_erased_messages'] ?? 0 ) + $n;
				return array( 'items_removed' => $n > 0, 'items_retained' => false, 'messages' => array(), 'done' => true );
			},
		);
		return $erasers;
	}
);

// The link in the confirmation email only marks the request "Email verified" (WordPress does that itself: status
// "request-confirmed"). Nothing is erased automatically; an administrator erases in Tools → Erase Personal Data.

add_filter(
	'user_request_action_confirmed_message',
	function ( $message, $request_id ) {
		$request = wp_get_user_request( $request_id );
		return $request && 'remove_personal_data' === $request->action_name ? '<p class="message">Thanks, your request is confirmed. We’ll review it and remove your details.</p>' : $message;
	},
	10,
	2
);
