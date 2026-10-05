<?php
/**
 * Claim or update a directory listing: [aw_claim_box] on each listing.
 *
 * Requests are private aw_claim posts, readable by administrators only. An administrator
 * reviews current against proposed values and approves, which applies the changes and marks
 * the listing verified today, or rejects. Nothing changes on the public listing until then.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_CLAIM_ROLES = array(
	''          => 'Choose one',
	'founder'   => 'Founder, trustee or director',
	'staff'     => 'Staff member',
	'volunteer' => 'Volunteer',
	'other'     => 'Other',
);

/** Listing fields a claim can change: meta key => label, validation rule. */
const AW_CLAIM_FIELDS = array(
	'aw_phone'    => array( 'Phone', 'phone' ),
	'aw_whatsapp' => array( 'WhatsApp', 'phone' ),
	'aw_email'    => array( 'Public email', 'email' ),
	'aw_website'  => array( 'Website', 'url' ),
	'aw_area'     => array( 'Area or locality', 'max:120' ),
	'aw_services' => array( 'Services (separate with commas)', 'max:300' ),
);

const AW_CLAIM_LOGO_BYTES = 2 * MB_IN_BYTES;

add_action(
	'init',
	function () {
		register_post_type(
			'aw_claim',
			array(
				'labels'          => array( 'name' => 'Listing claims', 'singular_name' => 'Claim', 'menu_name' => 'Listing claims' ),
				'public'          => false,
				'show_ui'         => true,
				'show_in_rest'    => false,
				'menu_icon'       => 'dashicons-yes-alt',
				'supports'        => array( 'title' ),
				'capability_type' => array( 'aw_volunteer', 'aw_volunteers' ), // Same admin-only capabilities as volunteers.
				'map_meta_cap'    => true,
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
			)
		);
	}
);

/**
 * The claim panel for the current listing.
 *
 * @return string
 */
function aw_claim_box() {
	$listing = get_post();
	if ( ! $listing || 'aw_listing' !== $listing->post_type ) {
		return '';
	}
	$form     = 'aw_claim';
	$state    = aw_form_state( $form );
	$verified = (bool) get_post_meta( $listing->ID, 'aw_verified', true );

	if ( isset( $_GET['aw_done'] ) && 'claim' === $_GET['aw_done'] ) { // phpcs:ignore WordPress.Security.NonceVerification
		return '<section class="aw-claim" id="claim"><div class="aw-form-done" role="status" tabindex="-1"><h2>Request received</h2>'
			. '<p>We will check the details with you before anything on this page changes.</p></div></section>';
	}

	$open  = $state['errors'] || $state['notice'];
	$intro = $verified
		? 'Details out of date? Send the correct ones and we will update this page after checking.'
		: 'This listing comes from public records and has not been confirmed. If you run or work here, confirm the details and we will mark it verified.';

	$html  = '<section class="aw-claim" id="claim" aria-labelledby="claim-title">';
	$html .= '<details' . ( $open ? ' open' : '' ) . '><summary><span id="claim-title">' . esc_html( $verified ? 'Update this listing' : 'Is this your organisation?' ) . '</span></summary>';
	$html .= '<p>' . esc_html( $intro ) . '</p>';
	$html .= aw_form_open( $form, array( 'enctype' => 'multipart/form-data', 'class' => 'aw-form aw-claim-form' ) );
	$html .= '<input type="hidden" name="listing" value="' . esc_attr( (string) $listing->ID ) . '">';

	$html .= '<fieldset><legend>About you</legend>';
	$html .= aw_field( $form, array( 'name' => 'name', 'label' => 'Your name', 'required' => true, 'attrs' => array( 'autocomplete' => 'name', 'maxlength' => 120 ) ) );
	$html .= aw_field( $form, array( 'name' => 'role', 'label' => 'Your role here', 'type' => 'select', 'required' => true, 'options' => AW_CLAIM_ROLES ) );
	$html .= aw_field( $form, array( 'name' => 'email', 'label' => 'Your email', 'type' => 'email', 'required' => true, 'hint' => 'We use this only to check this request. It is not shown on the page.', 'attrs' => array( 'autocomplete' => 'email', 'maxlength' => 160 ) ) );
	$html .= aw_field( $form, array( 'name' => 'phone', 'label' => 'Your phone', 'type' => 'tel', 'attrs' => array( 'autocomplete' => 'tel', 'inputmode' => 'tel', 'maxlength' => 20, 'pattern' => '\+?[0-9 ]{8,15}', 'data-error' => 'Enter 8 to 15 digits, for example +91 98765 43210.' ) ) );
	$html .= '</fieldset>';

	$html .= '<fieldset><legend>Correct details</legend><p class="aw-field-hint">Leave a field empty to keep what is shown now.</p>';
	foreach ( AW_CLAIM_FIELDS as $key => $def ) {
		$current = (string) get_post_meta( $listing->ID, $key, true );
		$type    = array( 'phone' => 'tel', 'email' => 'email', 'url' => 'url' )[ $def[1] ] ?? ( 'aw_services' === $key ? 'textarea' : 'text' );
		$attrs   = array( 'placeholder' => $current ? $current : 'Not listed' );
		if ( 'phone' === $def[1] ) {
			$attrs += array( 'inputmode' => 'tel', 'pattern' => '\+?[0-9 ]{8,15}', 'data-error' => 'Enter 8 to 15 digits, for example +91 98765 43210.' );
		}
		if ( str_starts_with( $def[1], 'max:' ) ) {
			$attrs['maxlength'] = (int) substr( $def[1], 4 );
		}
		$html .= aw_field( $form, array( 'name' => $key, 'label' => $def[0], 'type' => $type, 'attrs' => $attrs ) );
	}
	$html .= aw_field( $form, array( 'name' => 'logo', 'label' => 'Logo', 'type' => 'file', 'hint' => 'PNG, JPG or WebP, up to 2 MB.', 'attrs' => array( 'accept' => 'image/png,image/jpeg,image/webp', 'data-max-bytes' => AW_CLAIM_LOGO_BYTES ) ) );
	$html .= aw_field( $form, array( 'name' => 'notes', 'label' => 'Anything else we should change', 'type' => 'textarea', 'hint' => 'For example a new address area, a closed branch or a wrong name.', 'attrs' => array( 'maxlength' => 1000 ) ) );
	$html .= '</fieldset>';

	$html .= '<p><button type="submit" class="wp-element-button">Send for review</button></p></form></details></section>';
	return $html;
}
add_shortcode( 'aw_claim_box', 'aw_claim_box' );

/**
 * Handle a claim.
 */
function aw_claim_submit() {
	$form    = 'aw_claim';
	$listing = get_post( (int) ( $_POST['listing'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$back    = $listing && 'aw_listing' === $listing->post_type ? get_permalink( $listing ) : aw_form_back( '/directory/' );
	$done    = add_query_arg( 'aw_done', 'claim', $back ) . '#claim';
	$rl      = aw_form_guard( $form, $back, 'claim', $done );
	if ( ! $listing || 'aw_listing' !== $listing->post_type || 'publish' !== $listing->post_status ) {
		aw_form_fail( $form, array(), array(), $back, 'claim', 'This listing could not be found.' );
	}

	$v = array(
		'name'  => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
		'role'  => sanitize_key( wp_unslash( $_POST['role'] ?? '' ) ),
		'email' => trim( sanitize_text_field( wp_unslash( $_POST['email'] ?? '' ) ) ),
		'phone' => trim( preg_replace( '/[^0-9+ ]/', '', (string) wp_unslash( $_POST['phone'] ?? '' ) ) ),
		'notes' => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ),
	);
	$rules  = array( 'name' => array( 'required', 'max:120' ), 'role' => array( 'required' ), 'email' => array( 'required', 'email' ), 'phone' => array( 'phone' ), 'notes' => array( 'max:1000' ) );
	$labels = array( 'name' => 'Your name', 'role' => 'Your role here', 'email' => 'Your email', 'phone' => 'Your phone', 'notes' => 'Anything else' );
	foreach ( AW_CLAIM_FIELDS as $key => $def ) {
		$raw       = (string) wp_unslash( $_POST[ $key ] ?? '' );
		$v[ $key ] = 'aw_website' === $key ? esc_url_raw( trim( $raw ) ) : ( 'phone' === $def[1] ? trim( preg_replace( '/[^0-9+ ]/', '', $raw ) ) : sanitize_text_field( $raw ) );
		if ( 'aw_website' === $key && '' !== trim( $raw ) && '' === $v[ $key ] ) {
			$v[ $key ] = trim( $raw ); // Keep what they typed so validation can say what is wrong.
		}
		$rules[ $key ]  = array( $def[1] );
		$labels[ $key ] = $def[0];
	}
	$errors = aw_form_validate( $rules, $v, $labels );
	if ( $v['role'] && ! isset( AW_CLAIM_ROLES[ $v['role'] ] ) ) {
		$errors['role'] = 'Your role here: choose one of the options.';
	}

	$logo = $_FILES['logo'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$has_logo = $logo && UPLOAD_ERR_NO_FILE !== (int) $logo['error'];
	if ( $has_logo ) {
		$ok = UPLOAD_ERR_OK === (int) $logo['error'] && is_uploaded_file( $logo['tmp_name'] );
		$ft = $ok ? wp_check_filetype_and_ext( $logo['tmp_name'], $logo['name'], array( 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' ) ) : array();
		if ( ! $ok || empty( $ft['type'] ) ) {
			$errors['logo'] = 'Logo: use a PNG, JPG or WebP image.';
		} elseif ( (int) $logo['size'] > AW_CLAIM_LOGO_BYTES ) {
			$errors['logo'] = 'Logo: the file is larger than 2 MB.';
		}
	}

	$proposed = array_filter( array_intersect_key( $v, AW_CLAIM_FIELDS ), fn( $x ) => '' !== $x );
	if ( ! $errors && ! $proposed && ! $has_logo && '' === $v['notes'] ) {
		$errors['notes'] = 'Anything else: tell us at least one detail to confirm or change.';
	}
	if ( $errors ) {
		aw_form_fail( $form, $errors, $v, $back, 'claim' );
	}

	$file = '';
	if ( $has_logo ) {
		$editor = wp_get_image_editor( $logo['tmp_name'] );
		if ( ! is_wp_error( $editor ) ) {
			$editor->resize( 800, 800, false );
			$name  = wp_generate_password( 20, false ) . '.png';
			$saved = $editor->save( aw_private_dir() . '/' . $name, 'image/png' );
			$file  = is_wp_error( $saved ) ? '' : $name;
		}
	}

	$id = wp_insert_post(
		array( 'post_type' => 'aw_claim', 'post_status' => 'private', 'post_title' => $listing->post_title . ': ' . $v['name'] ),
		true
	);
	if ( is_wp_error( $id ) ) {
		aw_form_fail( $form, array(), $v, $back, 'claim', 'The request could not be saved. Please try again.' );
	}
	update_post_meta( $id, 'aw_c_listing', $listing->ID );
	update_post_meta( $id, 'aw_c_status', 'pending' );
	foreach ( array( 'name', 'role', 'email', 'phone', 'notes' ) as $k ) {
		update_post_meta( $id, 'aw_c_' . $k, $v[ $k ] );
	}
	update_post_meta( $id, 'aw_c_proposed', $proposed );
	update_post_meta( $id, 'aw_c_logo', $file );
	aw_form_count( $rl );
	wp_mail( 'contact@animalwrites.in', 'Listing claim: ' . $listing->post_title, "Someone asked to claim or update a directory listing.\nListing: {$listing->post_title}\nReview it in WP Admin > Listing claims." );
	wp_safe_redirect( $done );
	exit;
}
add_action( 'admin_post_nopriv_aw_claim', 'aw_claim_submit' );
add_action( 'admin_post_aw_claim', 'aw_claim_submit' );

/* ---------- Admin review ---------- */

add_filter( 'manage_aw_claim_posts_columns', fn( $c ) => array( 'cb' => $c['cb'], 'title' => 'Listing: claimant', 'aw_status' => 'Status', 'aw_changes' => 'Changes', 'date' => 'Received' ) );
add_action(
	'manage_aw_claim_posts_custom_column',
	function ( $col, $id ) {
		if ( 'aw_status' === $col ) {
			echo esc_html( ucfirst( (string) get_post_meta( $id, 'aw_c_status', true ) ) );
		} elseif ( 'aw_changes' === $col ) {
			$p = (array) get_post_meta( $id, 'aw_c_proposed', true );
			$n = array_map( fn( $k ) => AW_CLAIM_FIELDS[ $k ][0] ?? $k, array_keys( $p ) );
			if ( get_post_meta( $id, 'aw_c_logo', true ) ) {
				$n[] = 'Logo';
			}
			echo esc_html( $n ? implode( ', ', $n ) : 'Notes only' );
		}
	},
	10,
	2
);

add_action(
	'add_meta_boxes_aw_claim',
	function () {
		add_meta_box( 'aw_claim_review', 'Review', 'aw_claim_review_box', 'aw_claim', 'normal', 'high' );
	}
);

/**
 * Current against proposed values, with Approve and Reject.
 *
 * @param WP_Post $post Claim.
 */
function aw_claim_review_box( $post ) {
	$lid      = (int) get_post_meta( $post->ID, 'aw_c_listing', true );
	$status   = (string) get_post_meta( $post->ID, 'aw_c_status', true );
	$proposed = (array) get_post_meta( $post->ID, 'aw_c_proposed', true );
	$logo     = (string) get_post_meta( $post->ID, 'aw_c_logo', true );
	$roles    = AW_CLAIM_ROLES;
	echo '<p><strong>Listing:</strong> <a href="' . esc_url( (string) get_permalink( $lid ) ) . '" target="_blank">' . esc_html( get_the_title( $lid ) ) . '</a> · <strong>Status:</strong> ' . esc_html( ucfirst( $status ) ) . '</p>';
	echo '<p><strong>From:</strong> ' . esc_html( get_post_meta( $post->ID, 'aw_c_name', true ) ) . ' (' . esc_html( $roles[ get_post_meta( $post->ID, 'aw_c_role', true ) ] ?? '' ) . '), '
		. esc_html( get_post_meta( $post->ID, 'aw_c_email', true ) ) . ' ' . esc_html( get_post_meta( $post->ID, 'aw_c_phone', true ) ) . '</p>';
	echo '<table class="widefat striped"><thead><tr><th>Field</th><th>Now</th><th>Proposed</th></tr></thead><tbody>';
	foreach ( AW_CLAIM_FIELDS as $key => $def ) {
		if ( isset( $proposed[ $key ] ) ) {
			echo '<tr><td>' . esc_html( $def[0] ) . '</td><td>' . esc_html( (string) get_post_meta( $lid, $key, true ) ) . '</td><td><strong>' . esc_html( $proposed[ $key ] ) . '</strong></td></tr>';
		}
	}
	if ( $logo ) {
		$src = add_query_arg( array( 'action' => 'aw_claim_logo', 'claim' => $post->ID, '_wpnonce' => wp_create_nonce( 'aw_claim_logo' ) ), admin_url( 'admin-post.php' ) );
		echo '<tr><td>Logo</td><td>' . get_the_post_thumbnail( $lid, 'thumbnail' ) . '</td><td><img src="' . esc_url( $src ) . '" alt="Proposed logo" style="max-width:150px;height:auto"></td></tr>';
	}
	echo '</tbody></table>';
	$notes = (string) get_post_meta( $post->ID, 'aw_c_notes', true );
	if ( $notes ) {
		echo '<p><strong>Notes:</strong><br>' . nl2br( esc_html( $notes ) ) . '</p>';
	}
	if ( 'pending' === $status ) {
		foreach ( array( 'approve' => 'Approve: apply changes and mark verified', 'reject' => 'Reject' ) as $do => $label ) {
			$url = wp_nonce_url( add_query_arg( array( 'action' => 'aw_claim_decide', 'claim' => $post->ID, 'do' => $do ), admin_url( 'admin-post.php' ) ), 'aw_claim_decide_' . $post->ID );
			echo '<a class="button ' . ( 'approve' === $do ? 'button-primary' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a> ';
		}
		echo '<p class="description">Check the claimant first, for example by calling the number already listed. Notes are not applied automatically; edit the listing for those.</p>';
	}
}

/** Stream a private proposed logo to administrators. */
add_action(
	'admin_post_aw_claim_logo',
	function () {
		$id = (int) ( $_GET['claim'] ?? 0 );
		if ( ! current_user_can( 'edit_aw_volunteers' ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'aw_claim_logo' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$path = aw_private_dir() . '/' . basename( (string) get_post_meta( $id, 'aw_c_logo', true ) );
		if ( ! is_file( $path ) ) {
			wp_die( 'Not found.', 404 );
		}
		header( 'Content-Type: image/png' );
		readfile( $path ); // phpcs:ignore
		exit;
	}
);

/** Approve or reject. */
add_action(
	'admin_post_aw_claim_decide',
	function () {
		$id = (int) ( $_GET['claim'] ?? 0 );
		if ( ! current_user_can( 'edit_aw_volunteers' ) || ! check_admin_referer( 'aw_claim_decide_' . $id ) || 'aw_claim' !== get_post_type( $id ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$lid = (int) get_post_meta( $id, 'aw_c_listing', true );
		if ( 'approve' === ( $_GET['do'] ?? '' ) && 'aw_listing' === get_post_type( $lid ) ) {
			foreach ( (array) get_post_meta( $id, 'aw_c_proposed', true ) as $key => $val ) {
				if ( isset( AW_CLAIM_FIELDS[ $key ] ) ) {
					update_post_meta( $lid, $key, $val );
				}
			}
			$logo = (string) get_post_meta( $id, 'aw_c_logo', true );
			$path = $logo ? aw_private_dir() . '/' . basename( $logo ) : '';
			if ( $path && is_file( $path ) ) {
				require_once ABSPATH . 'wp-admin/includes/media.php';
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';
				$tmp = wp_tempnam( $logo );
				copy( $path, $tmp );
				$att = media_handle_sideload( array( 'name' => sanitize_title( get_the_title( $lid ) ) . '-logo.png', 'tmp_name' => $tmp ), $lid, get_the_title( $lid ) . ' logo' );
				if ( ! is_wp_error( $att ) ) {
					update_post_meta( $att, '_wp_attachment_image_alt', get_the_title( $lid ) . ' logo' );
					set_post_thumbnail( $lid, $att );
				}
			}
			update_post_meta( $lid, 'aw_verified', true );
			update_post_meta( $lid, 'aw_verified_on', wp_date( 'Y-m-d' ) );
			update_post_meta( $lid, 'aw_needs_reverify', false );
			update_post_meta( $id, 'aw_c_status', 'approved' );
		} else {
			update_post_meta( $id, 'aw_c_status', 'rejected' );
		}
		wp_safe_redirect( admin_url( 'post.php?post=' . $id . '&action=edit' ) );
		exit;
	}
);
