<?php
/**
 * Claim or update a directory listing: /claim/?listing=<ID> (form-blocks.php) shows the Fluent Forms form "Claim this
 * listing", the only claim form since 25 Oct 2026 (owner: the site's own form and its handler were removed). Claims are
 * Fluent Forms entries; the logo and proof uploads use aw_claim_logo_ok() and aw_claim_logo_store() below.
 *
 * Claims sent before Fluent Forms are private aw_claim posts (still registered so they stay readable), administrators
 * only. An administrator reviews current against proposed values and approves, which applies the changes and marks
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

/** What a listing shows now for a claim field: phones from the Phone numbers list, the rest from its meta. */
function aw_claim_current( $lid, $key ) {
	if ( 'aw_phone' === $key || 'aw_whatsapp' === $key ) {
		return aw_listing_first_phone( $lid, 'aw_whatsapp' === $key );
	}
	return (string) get_post_meta( $lid, $key, true );
}

/** The claim form for a listing: the Fluent Forms form. */
function aw_claim_form( $listing ) {
	return aw_ff_form( 'claim', array( 'listing' => $listing ) );
}

/** Is the uploaded logo a PNG, JPG or WebP under 2 MB? */
function aw_claim_logo_ok( $logo ) {
	$ok = $logo && UPLOAD_ERR_OK === (int) $logo['error'] && is_uploaded_file( $logo['tmp_name'] );
	$ft = $ok ? wp_check_filetype_and_ext( $logo['tmp_name'], $logo['name'], array( 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' ) ) : array();
	return $ok && ! empty( $ft['type'] ) && (int) $logo['size'] <= AW_CLAIM_LOGO_BYTES;
}

/** A logo as a PNG of at most 800 px in the private folder: its file name, or ''. */
function aw_claim_logo_store( $tmp, $name = '' ) {
	$editor = wp_get_image_editor( $tmp );
	if ( is_wp_error( $editor ) ) {
		return '';
	}
	$editor->resize( 800, 800, false );
	$name  = $name ? $name : wp_generate_password( 20, false ) . '.png';
	$saved = $editor->save( aw_private_dir() . '/' . $name, 'image/png' );
	return is_wp_error( $saved ) ? '' : $name;
}

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
	$cells = array();
	foreach ( AW_CLAIM_FIELDS as $key => $def ) {
		if ( isset( $proposed[ $key ] ) ) {
			$cells[] = array( 'field' => esc_html( $def[0] ), 'now' => esc_html( aw_claim_current( $lid, $key ) ), 'proposed' => '<strong>' . esc_html( $proposed[ $key ] ) . '</strong>' );
		}
	}
	if ( $logo ) {
		$src     = add_query_arg( array( 'action' => 'aw_claim_logo', 'claim' => $post->ID, '_wpnonce' => wp_create_nonce( 'aw_claim_logo' ) ), admin_url( 'admin-post.php' ) );
		$cells[] = array( 'field' => 'Logo', 'now' => get_the_post_thumbnail( $lid, 'thumbnail' ), 'proposed' => '<img src="' . esc_url( $src ) . '" alt="Proposed logo" style="max-width:150px;height:auto">' );
	}
	if ( get_post_meta( $post->ID, 'aw_c_feature', true ) ) { // asked before featured listings were dropped (owner, 24 Oct 2026)
		$cells[] = array( 'field' => 'Featured', 'now' => 'Asked to be featured; featured listings are no longer offered, so tell them when you reply.' );
	}
	aw_core_simple_table( 'claim-fields', array( 'field' => 'Field', 'now' => 'Now', 'proposed' => 'Proposed' ), $cells, array( 'empty' => 'No changes proposed.' ) );
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
			do_action( 'aw_claim_before_apply', $lid, (array) get_post_meta( $id, 'aw_c_proposed', true ), $id ); // enrichment.php logs the organisation as the source
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
			if ( function_exists( 'aw_org_people_from_claim' ) ) {
				aw_org_people_from_claim( $id, $lid ); // the claimant becomes one of its people, not shown (org-people.php)
			}
			update_post_meta( $id, 'aw_c_status', 'approved' );
		} else {
			update_post_meta( $id, 'aw_c_status', 'rejected' );
		}
		wp_safe_redirect( admin_url( 'post.php?post=' . $id . '&action=edit' ) );
		exit;
	}
);
