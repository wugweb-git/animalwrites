<?php
/**
 * Claim or update a directory listing: [aw_claim_box] on each listing.
 *
 * Requests are private aw_claim posts, readable by administrators only. An administrator
 * reviews current against proposed values and approves, which applies the changes and marks
 * the listing verified today (and features it, free, when the claimant asked), or rejects. Nothing changes on the
 * public listing until then.
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

/**
 * Listing fields a claim can change: key => label, validation rule. aw_phone and aw_whatsapp are the claim's own keys for
 * a phone and a WhatsApp number; on approval they go into the listing's Phone numbers list (aw_listing_put_phone), not
 * into single fields.
 */
const AW_CLAIM_FIELDS = array(
	'aw_phone'    => array( 'Phone', 'phone' ),
	'aw_whatsapp' => array( 'WhatsApp', 'phone' ),
	'aw_email'    => array( 'Public email', 'email' ),
	'aw_website'  => array( 'Website', 'url' ),
	'aw_area'     => array( 'Area or locality', 'max:120' ),
	'aw_services' => array( 'Services', 'max:300' ), // ticked from the fixed list (AW_SERVICES); saved as service tags
);

const AW_CLAIM_LOGO_BYTES = 2 * MB_IN_BYTES;

/** Featured listings are free for now: an approved claim that asks for it is featured for this many days. */
const AW_FEATURE_DAYS = 90;

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
 * The claim form (S7.2): step 1 "About you", step 2 "What should change" (all optional; empty keeps what is shown).
 *
 * @param WP_Post $listing Listing.
 * @return string
 */
/** Services as tick boxes from the fixed list; the listing's current services start ticked. */
function aw_claim_services_field( $form, $listing ) {
	$state = aw_form_state( $form );
	$kept  = isset( $state['values']['aw_services'] ) ? explode( ',', (string) $state['values']['aw_services'] ) : wp_list_pluck( aw_item_tags( $listing->ID, aw_service_slugs() ), 'slug' );
	$kept  = is_wp_error( $kept ) ? array() : $kept;
	$boxes = '';
	foreach ( AW_SERVICES as $slug => $name ) {
		$boxes .= '<label class="kit-checkbox"><input type="checkbox" name="aw_services[]" value="' . esc_attr( $slug ) . '"' . checked( in_array( $slug, $kept, true ), true, false ) . '> ' . esc_html( $name ) . '</label>';
	}
	return '<fieldset class="kit-field kit-check-group aw-claim-services"><legend>Services</legend>' . $boxes . '</fieldset>';
}

/** What a listing shows now for a claim field: phones from the Phone numbers list, the rest from its meta. */
function aw_claim_current( $lid, $key ) {
	if ( 'aw_phone' === $key || 'aw_whatsapp' === $key ) {
		return aw_listing_first_phone( $lid, 'aw_whatsapp' === $key );
	}
	return (string) get_post_meta( $lid, $key, true );
}

function aw_claim_form( $listing ) {
	$form = 'aw_claim';
	$about  = kits_dict_field( $form, 'name', array( 'required' => true ) )
		. kits_dict_field( $form, 'role', array( 'options' => AW_CLAIM_ROLES ) )
		. kits_dict_field( $form, 'email', array( 'hint' => 'We use this only to check this request. It is not shown on the page.' ) )
		. kits_dict_field( $form, 'phone' );
	$cur    = fn( $k ) => aw_claim_current( $listing->ID, $k );
	$keep   = fn( $k ) => array( 'placeholder' => '' !== $cur( $k ) ? $cur( $k ) : 'Not listed' );
	$change = '<p class="kit-field-hint">Leave a field empty to keep what is shown now.</p>'
		. kits_dict_field( $form, 'phone', array( 'name' => 'aw_phone', 'attrs' => $keep( 'aw_phone' ) ) )
		. kits_dict_field( $form, 'whatsapp', array( 'name' => 'aw_whatsapp', 'attrs' => $keep( 'aw_whatsapp' ) ) )
		. kits_dict_field( $form, 'email', array( 'name' => 'aw_email', 'label' => 'Public email', 'required' => false, 'attrs' => $keep( 'aw_email' ) ) )
		. kits_dict_field( $form, 'website', array( 'name' => 'aw_website', 'attrs' => $keep( 'aw_website' ) ) )
		. kits_dict_field( $form, 'area', array( 'name' => 'aw_area', 'attrs' => $keep( 'aw_area' ) ) )
		. aw_claim_services_field( $form, $listing )
		. kits_dict_field( $form, 'logo', array( 'hint' => 'PNG, JPG or WebP, up to 2 MB.' ) )
		. aw_field( $form, array( 'name' => 'notes', 'label' => 'Anything else we should change', 'type' => 'textarea', 'hint' => 'For example a new address area, a closed branch or a wrong name.', 'attrs' => array( 'maxlength' => 1000, 'rows' => 3 ) ) )
		. ( get_post_meta( $listing->ID, 'aw_featured', true ) ? '' : aw_field( $form, array( 'name' => 'feature', 'label' => 'Also feature this listing', 'type' => 'checkbox' ) ) )
		. ( function_exists( 'aw_nl_optin_box' ) ? aw_nl_optin_box( $form ) : '' );
	return aw_form_open( $form, array( 'enctype' => 'multipart/form-data', 'class' => 'kit-form aw-claim-form', 'id' => 'aw-form-aw_claim', 'data-key' => (string) $listing->ID, 'data-source' => trim( wp_make_link_relative( (string) get_permalink( $listing ) ), '/' ) ) )
		. '<input type="hidden" name="listing" value="' . esc_attr( (string) $listing->ID ) . '">'
		. kits_form_steps( array( array( 'About you', $about ), array( 'What should change', $change ) ), 'Send for review' )
		. '</form>';
}

/** Back-compat: [aw_claim_box] prints the claim form of the current listing. */
add_shortcode( 'aw_claim_box', fn() => get_post() ? aw_claim_form( get_post() ) : '' );

/**
 * Handle a claim.
 */
function aw_claim_submit() {
	$form    = 'aw_claim';
	$listing = get_post( (int) ( $_POST['listing'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$lid     = $listing ? (int) $listing->ID : 0;
	$back    = aw_claim_url( $lid ); // errors: back to /claim/?listing=<ID>, details kept
	$done    = kits_thanks_url( 'claim', array( 'listing' => $lid, 'from' => false ) );
	$rl      = aw_form_guard( $form, $back, 'claim', $done );
	if ( ! $listing || 'aw_listing' !== $listing->post_type || 'publish' !== $listing->post_status ) {
		aw_form_fail( $form, array(), array(), $back, 'claim', 'This listing could not be found.' );
	}

	$v = array(
		'name'  => trim( sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ) ),
		'role'  => sanitize_key( wp_unslash( $_POST['role'] ?? '' ) ),
		'email' => aw_clean_email( sanitize_text_field( wp_unslash( $_POST['email'] ?? '' ) ) ),
		'phone' => trim( sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ) ),
		'notes' => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ),
		'feature' => empty( $_POST['feature'] ) ? '' : '1', // phpcs:ignore WordPress.Security.NonceVerification
	);
	$rules  = array( 'name' => array( 'required', 'max:80' ), 'role' => array( 'required' ), 'email' => array( 'required', 'email' ), 'phone' => array( 'phone' ), 'notes' => array( 'max:1000' ) );
	$labels = array( 'name' => 'Your name', 'role' => 'Your role here', 'email' => 'Your email', 'phone' => 'Your phone', 'notes' => 'Anything else' );
	foreach ( AW_CLAIM_FIELDS as $key => $def ) {
		$raw       = 'aw_services' === $key ? implode( ',', array_intersect( array_map( 'sanitize_title', (array) wp_unslash( $_POST['aw_services'] ?? array() ) ), array_keys( AW_SERVICES ) ) ) : (string) wp_unslash( $_POST[ $key ] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$v[ $key ] = 'aw_website' === $key ? esc_url_raw( kits_clean_url( $raw ) ) : ( 'phone' === $def[1] ? trim( sanitize_text_field( $raw ) ) : sanitize_text_field( $raw ) );
		if ( 'aw_website' === $key && '' !== trim( $raw ) && '' === $v[ $key ] ) {
			$v[ $key ] = trim( $raw ); // Keep what they typed so validation can say what is wrong.
		}
		$rules[ $key ]  = array( $def[1] );
		$labels[ $key ] = $def[0];
	}
	$errors = aw_form_validate( $rules, $v, $labels, array( 'name' => array( 'required' => 'Enter your name' ), 'role' => array( 'required' => 'Choose your role' ) ) );
	if ( $v['role'] && ! isset( AW_CLAIM_ROLES[ $v['role'] ] ) ) {
		$errors['role'] = 'Choose your role';
	}

	$logo = $_FILES['logo'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$has_logo = $logo && UPLOAD_ERR_NO_FILE !== (int) $logo['error'];
	if ( $has_logo ) {
		$ok = UPLOAD_ERR_OK === (int) $logo['error'] && is_uploaded_file( $logo['tmp_name'] );
		$ft = $ok ? wp_check_filetype_and_ext( $logo['tmp_name'], $logo['name'], array( 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' ) ) : array();
		if ( ! $ok || empty( $ft['type'] ) ) {
			$errors['logo'] = 'Use a PNG, JPG or WebP under 2 MB.';
		} elseif ( (int) $logo['size'] > AW_CLAIM_LOGO_BYTES ) {
			$errors['logo'] = 'Use a PNG, JPG or WebP under 2 MB.';
		}
	}

	$proposed = array_filter( array_intersect_key( $v, AW_CLAIM_FIELDS ), fn( $x ) => '' !== $x );
	if ( ! $errors && ! $proposed && ! $has_logo && '' === $v['notes'] && '' === $v['feature'] ) {
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
		aw_form_fail( $form, array(), $v, $back, 'claim', aw_form_generic_error( $form ) );
	}
	update_post_meta( $id, 'aw_c_listing', $listing->ID );
	update_post_meta( $id, 'aw_c_status', 'pending' );
	foreach ( array( 'name', 'role', 'email', 'phone', 'notes', 'feature' ) as $k ) {
		update_post_meta( $id, 'aw_c_' . $k, $v[ $k ] );
	}
	update_post_meta( $id, 'aw_c_proposed', $proposed );
	update_post_meta( $id, 'aw_c_logo', $file );
	update_post_meta( $id, 'aw_c_source', kits_submitted_source() ); // the page it came from (S5.8)
	foreach ( array( 'aw_phone', 'aw_whatsapp' ) as $pk ) { // phones as 10 digits
		if ( isset( $proposed[ $pk ] ) && kits_clean_phone( $proposed[ $pk ] ) ) {
			$proposed[ $pk ] = kits_clean_phone( $proposed[ $pk ] );
		}
	}
	update_post_meta( $id, 'aw_c_proposed', $proposed );
	aw_form_count( $rl );
	wp_mail( 'contact@animalwrites.in', 'Listing claim: ' . $listing->post_title, "Someone asked to claim or update a directory listing" . ( $v['feature'] ? ' and to feature it' : '' ) . ".\nListing: {$listing->post_title}\nReview it in WP Admin > Listing claims." );
	if ( function_exists( 'aw_nl_optin' ) ) {
		aw_nl_optin( $v['email'], $v['name'], 'claim' );
	}
	$text = sprintf( 'We\'ll check it and email you at %s.', $v['email'] );
	aw_form_success( kits_thanks_url( 'claim', array( 'listing' => $lid, 'from' => false ), 'Request sent.', $text ), 'Request sent.', $text );
}
add_action( 'admin_post_nopriv_aw_claim', 'aw_claim_submit' );
add_action( 'admin_post_aw_claim', 'aw_claim_submit' );

/* ---------- Admin review ---------- */

add_filter( 'manage_aw_claim_posts_columns', fn( $c ) => array( 'cb' => $c['cb'], 'title' => 'Listing and claimant', 'aw_status' => 'Status', 'aw_changes' => 'Changes', 'date' => 'Received' ) );
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
			if ( get_post_meta( $id, 'aw_c_feature', true ) ) {
				$n[] = 'Featured';
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
			echo '<tr><td>' . esc_html( $def[0] ) . '</td><td>' . esc_html( aw_claim_current( $lid, $key ) ) . '</td><td><strong>' . esc_html( $proposed[ $key ] ) . '</strong></td></tr>';
		}
	}
	if ( $logo ) {
		$src = add_query_arg( array( 'action' => 'aw_claim_logo', 'claim' => $post->ID, '_wpnonce' => wp_create_nonce( 'aw_claim_logo' ) ), admin_url( 'admin-post.php' ) );
		echo '<tr><td>Logo</td><td>' . get_the_post_thumbnail( $lid, 'thumbnail' ) . '</td><td><img src="' . esc_url( $src ) . '" alt="Proposed logo" style="max-width:150px;height:auto"></td></tr>';
	}
	$feature = (bool) get_post_meta( $post->ID, 'aw_c_feature', true );
	if ( $feature ) {
		echo '<tr><td>Featured</td><td>' . esc_html( get_post_meta( $lid, 'aw_featured', true ) ? 'Yes, until ' . get_post_meta( $lid, 'aw_featured_until', true ) : 'No' ) . '</td><td><strong>Yes, for ' . (int) AW_FEATURE_DAYS . ' days (free)</strong></td></tr>';
	}
	echo '</tbody></table>';
	$notes = (string) get_post_meta( $post->ID, 'aw_c_notes', true );
	if ( $notes ) {
		echo '<p><strong>Notes:</strong><br>' . nl2br( esc_html( $notes ) ) . '</p>';
	}
	if ( 'pending' === $status ) {
		foreach ( array( 'approve' => 'Approve: apply changes and mark verified' . ( $feature ? ', and feature it' : '' ), 'reject' => 'Reject' ) as $do => $label ) {
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
					if ( 'aw_services' === $key ) {
						aw_set_item_tags( $lid, explode( ',', (string) $val ), aw_service_slugs() ); // the services only; the listing's types stay
					} elseif ( 'aw_phone' === $key ) {
						aw_listing_put_phone( $lid, (string) $val, '' ); // into the Phone numbers list
					} elseif ( 'aw_whatsapp' === $key ) {
						aw_listing_put_phone( $lid, '', (string) $val );
					} else {
						update_post_meta( $lid, $key, $val );
					}
					if ( 'aw_services' === $key ) { // services are what the organisation says it offers; record that
						update_post_meta( $lid, 'aw_services_source', 'the organisation' );
						update_post_meta( $lid, 'aw_services_on', wp_date( 'Y-m-d' ) );
					}
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
			if ( get_post_meta( $id, 'aw_c_feature', true ) ) {
				update_post_meta( $lid, 'aw_featured', 1 );
				update_post_meta( $lid, 'aw_featured_until', wp_date( 'Y-m-d', time() + AW_FEATURE_DAYS * DAY_IN_SECONDS ) );
			}
			update_post_meta( $id, 'aw_c_status', 'approved' );
		} else {
			update_post_meta( $id, 'aw_c_status', 'rejected' );
		}
		wp_safe_redirect( admin_url( 'post.php?post=' . $id . '&action=edit' ) );
		exit;
	}
);
