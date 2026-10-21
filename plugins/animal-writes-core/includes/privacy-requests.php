<?php
/**
 * DPDP "Remove my data": [aw_remove_data] on /remove-my-data/ (owner, 21 Oct 2026: collect only).
 *
 * 1. The Fluent Forms form #8 collects the request (email, name, what to remove, reason; ALTCHA). It is a Fluent Forms
 *    entry; nothing is created or erased by the form.
 * 2. The owner opens Tools → Erase Personal Data, adds the email (WordPress sends its own confirmation email), and once
 *    it is confirmed clicks "Erase personal data", which runs every eraser: WordPress's comments and media, Simple
 *    History, and the site's own below. Neither FluentCRM nor Fluent Forms registers an eraser of its own, so the
 *    site's cover them (aw-fluentcrm in fluentcrm.php, aw-fluentforms in newsletter-fluentforms.php).
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
		return '<div class="kit-form-done" role="status" tabindex="-1"><p class="aw-form-done-title">Request received</p><p>' . esc_html( sprintf( 'We\'ll email %s to confirm it before removing anything.', $to ) ) . '</p></div>';
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
 * The site's own form (shown only when Fluent Forms is off): the request is kept as a contact message tagged "Data
 * removal" for the owner, who then starts it in Tools → Erase Personal Data. Nothing is created or erased here.
 *
 * @return array array{url, title, text} for the thank-you page.
 */
function aw_remove_data_save( $email ) {
	$id = wp_insert_post( array( 'post_type' => 'aw_message', 'post_status' => 'private', 'post_title' => 'Remove my data: ' . $email, 'post_content' => 'Remove my data (the site\'s own form). Start it in Tools → Erase Personal Data.' ) );
	if ( $id && ! is_wp_error( $id ) ) {
		update_post_meta( $id, 'aw_m_email', $email );
		update_post_meta( $id, 'aw_m_about', 'remove-data' );
		update_post_meta( $id, 'aw_m_tag', 'Data removal' );
	}
	$text = sprintf( 'We\'ll email %s to confirm it before removing anything.', $email );
	return array( 'url' => kits_thanks_url( 'remove-data', array(), 'Request received', $text ), 'title' => 'Request received', 'text' => $text );
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
