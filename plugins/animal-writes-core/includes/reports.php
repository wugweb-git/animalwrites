<?php
/**
 * "Report an error" on every directory listing: [aw_report_box].
 *
 * No account needed: a reason, optional details, and the shared spam guard (nonce, honeypot, timing, rate limit).
 * Reports are private aw_report posts that only administrators can see, with New / Resolved status.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Reasons: key => placeholder label. */
function aw_report_reasons() {
	return array(
		'contact'   => 'Phone, email or website is wrong',
		'location'  => 'Location is wrong',
		'closed'    => 'Closed or no longer operating',
		'duplicate' => 'Listed more than once',
		'not_org'   => 'Not an animal welfare organisation',
		'other'     => 'Something else',
	);
}

add_action(
	'init',
	function () {
		register_post_type(
			'aw_report',
			array(
				'labels'          => array( 'name' => 'Listing reports', 'singular_name' => 'Listing report', 'menu_name' => 'Listing reports' ),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'edit.php?post_type=aw_listing',
				'show_in_rest'    => false,
				'supports'        => array( 'title' ),
				'capability_type' => array( 'aw_volunteer', 'aw_volunteers' ), // administrators only, as for volunteers
				'map_meta_cap'    => true,
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
			)
		);
	}
);

/** The disclosure + form on a listing. */
function aw_report_box() {
	$listing = get_post();
	if ( ! $listing || 'aw_listing' !== $listing->post_type ) {
		return '';
	}
	$form = 'aw_report';
	if ( isset( $_GET['aw_done'] ) && 'report' === $_GET['aw_done'] ) { // phpcs:ignore WordPress.Security.NonceVerification
		return '<section class="aw-report" id="report"><div class="aw-form-done" role="status" tabindex="-1"><p>Thank you. We will check this listing and correct it.</p></div></section>';
	}
	$state = aw_form_state( $form );
	$open  = $state['errors'] || $state['notice'];
	$html  = '<section class="aw-report" id="report"><details' . ( $open ? ' open' : '' ) . '><summary>Report an error on this listing</summary>';
	$html .= aw_form_open( $form, array( 'class' => 'aw-form aw-report-form' ) );
	$html .= '<input type="hidden" name="listing" value="' . esc_attr( (string) $listing->ID ) . '">';
	$html .= aw_field( $form, array( 'name' => 'reason', 'label' => 'What is wrong?', 'type' => 'select', 'required' => true, 'options' => array( '' => 'Choose one' ) + aw_report_reasons() ) );
	$html .= aw_field( $form, array( 'name' => 'details', 'label' => 'Details', 'type' => 'textarea', 'hint' => 'Optional. Tell us the correct details if you know them.', 'attrs' => array( 'maxlength' => 1000 ) ) );
	$html .= '<p><button type="submit" class="kit-btn">Send report</button></p></form></details></section>';
	return $html;
}
add_shortcode( 'aw_report_box', 'aw_report_box' );

/** Handle a report. */
function aw_report_submit() {
	$form    = 'aw_report';
	$listing = get_post( (int) ( $_POST['listing'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$back    = $listing && 'aw_listing' === $listing->post_type ? get_permalink( $listing ) : aw_form_back( '/directory/' );
	$done    = add_query_arg( 'aw_done', 'report', $back ) . '#report';
	$rl      = aw_form_guard( $form, $back, 'report', $done, 10 );
	if ( ! $listing || 'aw_listing' !== $listing->post_type || 'publish' !== $listing->post_status ) {
		aw_form_fail( $form, array(), array(), $back, 'report', 'This listing could not be found.' );
	}
	$v = array(
		'reason'  => sanitize_key( wp_unslash( $_POST['reason'] ?? '' ) ),
		'details' => sanitize_textarea_field( wp_unslash( $_POST['details'] ?? '' ) ),
	);
	$errors = aw_form_validate( array( 'reason' => array( 'required' ), 'details' => array( 'max:1000' ) ), $v, array( 'reason' => 'What is wrong?', 'details' => 'Details' ) );
	if ( $v['reason'] && ! isset( aw_report_reasons()[ $v['reason'] ] ) ) {
		$errors['reason'] = 'What is wrong?: choose one of the options.';
	}
	if ( $errors ) {
		aw_form_fail( $form, $errors, $v, $back, 'report' );
	}
	$id = wp_insert_post( array( 'post_type' => 'aw_report', 'post_status' => 'private', 'post_title' => $listing->post_title ), true );
	if ( is_wp_error( $id ) ) {
		aw_form_fail( $form, array(), $v, $back, 'report', 'Your report could not be saved. Please try again.' );
	}
	update_post_meta( $id, 'aw_r_listing', $listing->ID );
	update_post_meta( $id, 'aw_r_reason', $v['reason'] );
	update_post_meta( $id, 'aw_r_details', $v['details'] );
	update_post_meta( $id, 'aw_r_status', 'new' );
	aw_form_count( $rl );
	aw_form_success( $done, 'Thank you. We will check this listing and correct it.' );
}
add_action( 'admin_post_nopriv_aw_report', 'aw_report_submit' );
add_action( 'admin_post_aw_report', 'aw_report_submit' );

/* ---------- Admin review ---------- */

add_filter( 'manage_aw_report_posts_columns', fn( $c ) => array( 'cb' => $c['cb'], 'title' => 'Listing', 'aw_reason' => 'Reason', 'aw_details' => 'Details', 'aw_status' => 'Status', 'date' => 'Received' ) );
add_action(
	'manage_aw_report_posts_custom_column',
	function ( $col, $id ) {
		$lid = (int) get_post_meta( $id, 'aw_r_listing', true );
		if ( 'aw_reason' === $col ) {
			echo esc_html( aw_report_reasons()[ get_post_meta( $id, 'aw_r_reason', true ) ] ?? '' );
		} elseif ( 'aw_details' === $col ) {
			echo esc_html( wp_trim_words( (string) get_post_meta( $id, 'aw_r_details', true ), 30 ) );
		} elseif ( 'aw_status' === $col ) {
			$st = (string) get_post_meta( $id, 'aw_r_status', true );
			echo esc_html( ucfirst( $st ) );
			if ( 'new' === $st ) {
				$url = wp_nonce_url( add_query_arg( array( 'action' => 'aw_report_resolve', 'report' => $id ), admin_url( 'admin-post.php' ) ), 'aw_report_resolve_' . $id );
				echo ' · <a href="' . esc_url( $url ) . '">Mark resolved</a>';
			}
			if ( $lid ) {
				echo ' · <a href="' . esc_url( (string) get_edit_post_link( $lid ) ) . '">Edit listing</a> · <a href="' . esc_url( (string) get_permalink( $lid ) ) . '" target="_blank">View</a>';
			}
		}
	},
	10,
	2
);

add_action(
	'admin_post_aw_report_resolve',
	function () {
		$id = (int) ( $_GET['report'] ?? 0 );
		if ( ! current_user_can( 'edit_aw_volunteers' ) || ! check_admin_referer( 'aw_report_resolve_' . $id ) || 'aw_report' !== get_post_type( $id ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		update_post_meta( $id, 'aw_r_status', 'resolved' );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=aw_report' ) );
		exit;
	}
);
