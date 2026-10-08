<?php
/**
 * DPDP "Remove my data": [aw_remove_data] on /remove-my-data/.
 *
 * 1. A visitor types their email (ALTCHA, rate limited). Nothing is deleted on a typed email.
 * 2. WordPress's own data request is made and its confirmation email sent (Tools → Erase Personal Data lists it).
 * 3. Only when the link in that email is opened, every eraser runs (ours below, plus WordPress's comments and
 *    media), the request is marked completed and the person gets WordPress's "data erased" email.
 * The request record (email, dates, items removed) stays in Tools → Erase Personal Data as the log.
 * There is no CRM connected; if one is added, it registers its own eraser on wp_privacy_personal_data_erasers.
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
	$form  = 'aw_remove_data';
	$html  = aw_form_open( $form, array( 'class' => 'kit-form aw-remove-data-form' ) );
	$html .= kits_dict_field( $form, 'email' );
	$html .= '<button type="submit" class="kit-btn">Send confirmation link</button></form>';
	return $html;
}
add_shortcode( 'aw_remove_data', 'aw_remove_data_form' );

function aw_remove_data_submit() {
	$form = 'aw_remove_data';
	$back = aw_form_back( '/remove-my-data/' );
	$rl   = aw_form_guard( $form, $back, 'remove-data', add_query_arg( 'aw_done', 'rd_check', $back ), 3 );
	$v    = array( 'email' => aw_clean_email( sanitize_text_field( wp_unslash( $_POST['email'] ?? '' ) ) ) );
	$errors = aw_form_validate( array( 'email' => array( 'required', 'email' ) ), $v, array( 'email' => 'Email' ) );
	if ( $errors ) {
		aw_form_fail( $form, $errors, $v, $back, 'remove-data' );
	}
	$id = wp_create_user_request( $v['email'], 'remove_personal_data' );
	if ( ! is_wp_error( $id ) ) {
		wp_send_user_request( $id ); // WordPress's confirmation email
	}
	// The same answer whether or not a request was already open, so the form says nothing about who is on file.
	aw_form_count( $rl );
	aw_form_success( add_query_arg( array( 'aw_done' => 'rd_check', 'to' => $v['email'] ), $back ), 'Check your inbox', sprintf( 'We\'ve sent a link to %s. Open it to confirm, and your details are deleted.', $v['email'] ) );
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
		update_post_meta( $request_id, '_aw_erasure_log', array( 'at' => current_time( 'mysql' ), 'removed' => $removed, 'retained' => $kept ) );
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
