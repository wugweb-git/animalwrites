<?php
/**
 * DPDP "Remove my data": [aw_remove_data] on /remove-my-data/.
 *
 * 1. A visitor types their email (ALTCHA, rate limited). Nothing is deleted on a typed email.
 * 2. WordPress's own data request is made and its confirmation email sent (Tools → Erase Personal Data lists it).
 * 3. Only when the link in that email is opened, every eraser runs (ours below, plus WordPress's comments and
 *    media), the request is marked completed and the person gets WordPress's "data erased" email.
 * The request record (email, dates, items removed) stays in Tools → Erase Personal Data as the log.
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
		return '<div class="kit-form-done" role="status" tabindex="-1"><p class="aw-form-done-title">Check your inbox</p><p>' . esc_html( sprintf( 'We\'ve sent a link to %s. Open it to confirm, and your details are deleted.', $to ) ) . '</p></div>';
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
function aw_remove_data_save( $email ) {
	// Never aw_crm_sync(): the person is asking us to delete what we hold, so this form must not add or update a
	// FluentCRM contact. Once they confirm, the aw-fluentcrm eraser (fluentcrm.php) deletes the contact.
	$id = wp_create_user_request( $email, 'remove_personal_data' );
	if ( ! is_wp_error( $id ) ) {
		aw_send_removal_link( $id );
	}
	$text = sprintf( 'We\'ve sent a link to %s. Open it to confirm, and your details are deleted.', $email );
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
					foreach ( array( 'name', 'role', 'email', 'phone', 'notes' ) as $k ) {
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

/** When the link is opened: run every eraser, mark the request completed (the log), email the person. */
add_action(
	'user_request_action_confirmed',
	function ( $request_id ) {
		$request = wp_get_user_request( $request_id );
		if ( ! $request || 'remove_personal_data' !== $request->action_name || 'request-confirmed' !== $request->status ) {
			return;
		}
		$removed = 0;
		$kept    = 0;
		$GLOBALS['aw_erased_messages'] = 0;
		foreach ( (array) apply_filters( 'wp_privacy_personal_data_erasers', array() ) as $eraser ) {
			for ( $page = 1; $page <= 50; $page++ ) {
				$r        = call_user_func( $eraser['callback'], $request->email, $page );
				$removed += empty( $r['items_removed'] ) ? 0 : 1;
				$kept    += empty( $r['items_retained'] ) ? 0 : 1;
				if ( ! empty( $r['done'] ) ) {
					break;
				}
			}
		}
		update_post_meta( $request_id, '_aw_erasure_log', array( 'at' => current_time( 'mysql' ), 'removed' => $removed, 'retained' => $kept, 'messages' => (int) $GLOBALS['aw_erased_messages'] ) );
		wp_update_post( array( 'ID' => $request_id, 'post_status' => 'request-completed' ) );
		update_post_meta( $request_id, '_wp_user_request_completed_timestamp', time() );
		do_action( 'wp_privacy_personal_data_erased', $request_id ); // WordPress emails the person that it is done
	},
	20
);

add_filter(
	'user_request_action_confirmed_message',
	function ( $message, $request_id ) {
		$request = wp_get_user_request( $request_id );
		return $request && 'remove_personal_data' === $request->action_name ? '<p class="message">Your details have been deleted from Animal Writes. We\'ve emailed you to confirm.</p>' : $message;
	},
	10,
	2
);
