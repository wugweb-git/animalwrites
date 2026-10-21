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
				'labels'          => array( 'name' => 'Reports', 'singular_name' => 'Report', 'menu_name' => 'Reports', 'all_items' => 'Reports' ),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'aw-inbox', // Inbox → Reports (S2.3)
				'show_in_rest'    => false,
				'supports'        => array( 'title' ),
				'capability_type' => array( 'aw_volunteer', 'aw_volunteers' ), // administrators only, as for volunteers
				'map_meta_cap'    => true,
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
			)
		);
	}
);

/** The report form (S7.3): what is wrong, and an optional note. */
function aw_report_form( $listing ) {
	$ff = apply_filters( 'aw_form_html', '', 'report', array( 'listing' => $listing ) ); // the Fluent Forms version when it is set up (forms-fluentforms.php)
	if ( '' !== $ff ) {
		return $ff;
	}
	$form = 'aw_report';
	return aw_form_open( $form, array( 'class' => 'kit-form aw-report-form', 'id' => 'aw-form-aw_report', 'data-key' => (string) $listing->ID, 'data-source' => trim( wp_make_link_relative( (string) get_permalink( $listing ) ), '/' ) ) )
		. '<input type="hidden" name="listing" value="' . esc_attr( (string) $listing->ID ) . '">'
		. aw_field( $form, array( 'name' => 'reason', 'label' => 'What is wrong?', 'type' => 'select', 'required' => true, 'options' => array( '' => 'Choose one' ) + aw_report_reasons(), 'messages' => array( 'required' => 'Choose what is wrong' ) ) )
		. aw_field( $form, array( 'name' => 'details', 'label' => 'Details', 'type' => 'textarea', 'hint' => 'Tell us the correct details if you know them.', 'attrs' => array( 'maxlength' => 1000, 'rows' => 4 ) ) )
		. '<div class="kit-form-actions"><button type="submit" class="kit-btn">Send report</button></div></form>';
}

/** Back-compat: [aw_report_box] prints the report form of the current listing. */
add_shortcode( 'aw_report_box', fn() => get_post() ? aw_report_form( get_post() ) : '' );

/** A report's values from submitted data (already unslashed). */
function aw_report_values( $in ) {
	return array(
		'reason'  => sanitize_key( (string) ( $in['reason'] ?? '' ) ),
		'details' => sanitize_textarea_field( (string) ( $in['details'] ?? '' ) ),
	);
}

/** Server rules for a report: field => message. */
function aw_report_errors( $v ) {
	$errors = aw_form_validate( array( 'reason' => array( 'required' ), 'details' => array( 'max:1000' ) ), $v, array( 'reason' => 'What is wrong?', 'details' => 'Details' ), array( 'reason' => array( 'required' => 'Choose what is wrong' ) ) );
	if ( $v['reason'] && ! isset( aw_report_reasons()[ $v['reason'] ] ) ) {
		$errors['reason'] = 'Choose what is wrong';
	}
	return $errors;
}

/**
 * Save a checked report (Inbox > Reports, status New). Never calls aw_crm_sync(): the form asks for no email, so there
 * is no one to add to FluentCRM, and reporting a listing is not a reason to keep a record of the sender.
 *
 * @return array|false array{url, title, text}, or false when it could not be saved.
 */
function aw_report_save( $v, $listing, $source ) {
	$id = wp_insert_post( array( 'post_type' => 'aw_report', 'post_status' => 'private', 'post_title' => $listing->post_title ), true );
	if ( is_wp_error( $id ) ) {
		return false;
	}
	update_post_meta( $id, 'aw_r_listing', $listing->ID );
	update_post_meta( $id, 'aw_r_reason', $v['reason'] );
	update_post_meta( $id, 'aw_r_details', $v['details'] );
	update_post_meta( $id, 'aw_r_status', 'new' );
	update_post_meta( $id, 'aw_r_source', $source ); // the page it came from (S5.8)
	return array( 'url' => kits_thanks_url( 'report', array( 'listing' => (int) $listing->ID, 'from' => false ), 'Thanks — we\'ll check it.' ), 'title' => 'Thanks — we\'ll check it.', 'text' => '' );
}

/** Handle a report. */
function aw_report_submit() {
	$form    = 'aw_report';
	$listing = get_post( (int) ( $_POST['listing'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$lid     = $listing ? (int) $listing->ID : 0;
	$back    = aw_report_url( $lid ); // errors: back to /report/?listing=<ID>, details kept
	$done    = kits_thanks_url( 'report', array( 'listing' => $lid, 'from' => false ) );
	$rl      = aw_form_guard( $form, $back, 'report', $done, 10 );
	if ( ! $listing || 'aw_listing' !== $listing->post_type || 'publish' !== $listing->post_status ) {
		aw_form_fail( $form, array(), array(), $back, 'report', 'This listing could not be found.' );
	}
	$v      = aw_report_values( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification -- checked in aw_form_guard()
	$errors = aw_report_errors( $v );
	if ( $errors ) {
		aw_form_fail( $form, $errors, $v, $back, 'report' );
	}
	$res = aw_report_save( $v, $listing, kits_submitted_source() );
	if ( ! $res ) {
		aw_form_fail( $form, array(), $v, $back, 'report', aw_form_generic_error( $form ) );
	}
	aw_form_count( $rl );
	aw_form_success( $res['url'], $res['title'] );
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
