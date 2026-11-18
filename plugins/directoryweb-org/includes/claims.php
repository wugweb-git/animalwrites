<?php
/**
 * Claim or update a directory listing: /claim/?listing=<ID> (form-blocks.php) shows the Fluent Forms form "Claim this
 * listing", the only claim form (an earlier native form and its handler were removed). Claims are
 * Fluent Forms entries; the logo and proof uploads use directoryweb_claim_logo_ok() and directoryweb_claim_logo_store() below.
 *
 * Claims sent before Fluent Forms are private directoryweb_claim posts (still registered so they stay readable), administrators
 * only. An administrator reviews current against proposed values and approves, which applies the changes and marks
 * the listing verified today, or rejects. Nothing changes on the public listing until then.
 *
 * @package directoryweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const DIRECTORYWEB_CLAIM_ROLES = array(
	''          => 'Choose one',
	'founder'   => 'Founder, trustee or director',
	'staff'     => 'Staff member',
	'volunteer' => 'Volunteer',
	'other'     => 'Other',
);

/**
 * Listing fields a claim can change: key => label, validation rule. directoryweb_phone and directoryweb_whatsapp are the claim's own keys for
 * a phone and a WhatsApp number; on approval they go into the listing's Phone numbers list (directoryweb_listing_put_phone), not
 * into single fields.
 */
const DIRECTORYWEB_CLAIM_FIELDS = array(
	'directoryweb_phone'    => array( 'Phone', 'phone' ),
	'directoryweb_whatsapp' => array( 'WhatsApp', 'phone' ),
	'directoryweb_email'    => array( 'Public email', 'email' ),
	'directoryweb_website'  => array( 'Website', 'url' ),
	'directoryweb_area'     => array( 'Area or locality', 'max:120' ),
	'directoryweb_services' => array( 'Services', 'max:300' ), // ticked from the fixed list (DIRECTORYWEB_SERVICES); saved as service tags
);

const DIRECTORYWEB_CLAIM_LOGO_BYTES = 2 * MB_IN_BYTES;

/**
 * The directoryweb_claim post type's primitive capabilities: all DIRECTORYWEB_CAP (manage_directoryweb, directoryweb-caps.php),
 * except create_posts (claims come from the claim form only).
 *
 * @return array<string, string>
 */
function directoryweb_claim_caps(): array {
	$cap  = defined( 'DIRECTORYWEB_CAP' ) ? DIRECTORYWEB_CAP : 'manage_directoryweb';
	$caps = array( 'create_posts' => 'do_not_allow' );
	foreach ( array( 'edit_posts', 'edit_others_posts', 'edit_private_posts', 'edit_published_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'delete_others_posts', 'delete_private_posts', 'delete_published_posts' ) as $key ) {
		$caps[ $key ] = $cap;
	}
	return $caps;
}

add_action(
	'init',
	function () {
		register_post_type(
			'directoryweb_claim',
			array(
				'labels'          => array( 'name' => 'Listing claims', 'singular_name' => 'Claim', 'menu_name' => 'Listing claims' ),
				'public'          => false,
				'show_ui'         => true,
				'show_in_rest'    => false,
				'menu_icon'       => 'dashicons-yes-alt',
				'supports'        => array( 'title' ),
				'map_meta_cap'    => true,
				// Every claim capability is DirectoryWeb's own, like the Claims screens; claims are never
				// added by hand. map_meta_cap turns edit_post, delete_post and read_post into these.
				'capabilities'    => directoryweb_claim_caps(),
			)
		);
	}
);

/** What a listing shows now for a claim field: phones from the Phone numbers list, the rest from its meta. */
function directoryweb_claim_current( $lid, $key ) {
	if ( 'directoryweb_phone' === $key || 'directoryweb_whatsapp' === $key ) {
		return directoryweb_listing_first_phone( $lid, 'directoryweb_whatsapp' === $key );
	}
	return (string) get_post_meta( $lid, $key, true );
}

/** The claim form for a listing: the Fluent Forms form. */
function directoryweb_claim_form( $listing ) {
	return directoryweb_ff_form( 'claim', array( 'listing' => $listing ) );
}

/** Is the uploaded logo a PNG, JPG or WebP under 2 MB? */
function directoryweb_claim_logo_ok( $logo ) {
	$ok = $logo && UPLOAD_ERR_OK === (int) $logo['error'] && is_uploaded_file( $logo['tmp_name'] );
	$ft = $ok ? wp_check_filetype_and_ext( $logo['tmp_name'], $logo['name'], array( 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' ) ) : array();
	return $ok && ! empty( $ft['type'] ) && (int) $logo['size'] <= DIRECTORYWEB_CLAIM_LOGO_BYTES;
}

/** A logo as a PNG of at most 800 px in the private folder: its file name, or ''. */
function directoryweb_claim_logo_store( $tmp, $name = '' ) {
	$editor = wp_get_image_editor( $tmp );
	if ( is_wp_error( $editor ) ) {
		return '';
	}
	$editor->resize( 800, 800, false );
	$name  = $name ? $name : wp_generate_password( 20, false ) . '.png';
	$saved = $editor->save( directoryweb_private_dir() . '/' . $name, 'image/png' );
	return is_wp_error( $saved ) ? '' : $name;
}

/* ---------- Admin review ---------- */

add_filter( 'manage_aw_claim_posts_columns', fn( $c ) => array( 'cb' => $c['cb'], 'title' => 'Listing and claimant', 'directoryweb_status' => 'Status', 'directoryweb_changes' => 'Changes', 'date' => 'Received' ) );
add_action(
	'manage_aw_claim_posts_custom_column',
	function ( $col, $id ) {
		if ( 'directoryweb_status' === $col ) {
			echo esc_html( ucfirst( (string) get_post_meta( $id, 'directoryweb_c_status', true ) ) );
		} elseif ( 'directoryweb_changes' === $col ) {
			$p = (array) get_post_meta( $id, 'directoryweb_c_proposed', true );
			$n = array_map( fn( $k ) => DIRECTORYWEB_CLAIM_FIELDS[ $k ][0] ?? $k, array_keys( $p ) );
			if ( get_post_meta( $id, 'directoryweb_c_logo', true ) ) {
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
		add_meta_box( 'directoryweb_claim_review', 'Review', 'directoryweb_claim_review_box', 'directoryweb_claim', 'normal', 'high' );
	}
);

/**
 * Current against proposed values, with Approve and Reject.
 *
 * @param WP_Post $post Claim.
 */
function directoryweb_claim_review_box( $post ) {
	$lid      = (int) get_post_meta( $post->ID, 'directoryweb_c_listing', true );
	$status   = (string) get_post_meta( $post->ID, 'directoryweb_c_status', true );
	$proposed = (array) get_post_meta( $post->ID, 'directoryweb_c_proposed', true );
	$logo     = (string) get_post_meta( $post->ID, 'directoryweb_c_logo', true );
	$roles    = DIRECTORYWEB_CLAIM_ROLES;
	echo '<p><strong>Listing:</strong> <a href="' . esc_url( (string) get_permalink( $lid ) ) . '" target="_blank">' . esc_html( get_the_title( $lid ) ) . '</a> · <strong>Status:</strong> ' . esc_html( ucfirst( $status ) ) . '</p>';
	echo '<p><strong>From:</strong> ' . esc_html( get_post_meta( $post->ID, 'directoryweb_c_name', true ) ) . ' (' . esc_html( $roles[ get_post_meta( $post->ID, 'directoryweb_c_role', true ) ] ?? '' ) . '), '
		. esc_html( get_post_meta( $post->ID, 'directoryweb_c_email', true ) ) . ' ' . esc_html( get_post_meta( $post->ID, 'directoryweb_c_phone', true ) ) . '</p>';
	$cells = array();
	foreach ( DIRECTORYWEB_CLAIM_FIELDS as $key => $def ) {
		if ( isset( $proposed[ $key ] ) ) {
			$cells[] = array( 'field' => esc_html( $def[0] ), 'now' => esc_html( directoryweb_claim_current( $lid, $key ) ), 'proposed' => '<strong>' . esc_html( $proposed[ $key ] ) . '</strong>' );
		}
	}
	if ( $logo ) {
		$src     = add_query_arg( array( 'action' => 'directoryweb_claim_logo', 'claim' => $post->ID, '_wpnonce' => wp_create_nonce( 'directoryweb_claim_logo' ) ), admin_url( 'admin-post.php' ) );
		$cells[] = array( 'field' => 'Logo', 'now' => get_the_post_thumbnail( $lid, 'thumbnail' ), 'proposed' => '<img src="' . esc_url( $src ) . '" alt="Proposed logo" style="max-width:150px;height:auto">' );
	}
	if ( get_post_meta( $post->ID, 'directoryweb_c_feature', true ) ) { // asked before featured listings were dropped.
		$cells[] = array( 'field' => 'Featured', 'now' => 'Asked to be featured; featured listings are no longer offered, so tell them when you reply.' );
	}
	directoryweb_core_simple_table( 'claim-fields', array( 'field' => 'Field', 'now' => 'Now', 'proposed' => 'Proposed' ), $cells, array( 'empty' => 'No changes proposed.' ) );
	$notes = (string) get_post_meta( $post->ID, 'directoryweb_c_notes', true );
	if ( $notes ) {
		echo '<p><strong>Notes:</strong><br>' . nl2br( esc_html( $notes ) ) . '</p>';
	}
	if ( 'pending' === $status ) {
		foreach ( array( 'approve' => 'Approve: apply changes and mark verified', 'reject' => 'Reject' ) as $do => $label ) {
			$url = wp_nonce_url( add_query_arg( array( 'action' => 'directoryweb_claim_decide', 'claim' => $post->ID, 'do' => $do ), admin_url( 'admin-post.php' ) ), 'directoryweb_claim_decide_' . $post->ID );
			echo '<a class="button ' . ( 'approve' === $do ? 'button-primary' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a> ';
		}
		echo '<p class="description">Check the claimant first, for example by calling the number already listed. Notes are not applied automatically; edit the listing for those.</p>';
	}
}

/** Stream a private proposed logo to administrators. */
add_action(
	'admin_post_aw_claim_logo',
	function () {
		$id = absint( $_GET['claim'] ?? 0 );
		if ( ! current_user_can( DIRECTORYWEB_CAP ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'directoryweb_claim_logo' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- DirectoryWeb's own capability (directoryweb-caps.php)
			wp_die( 'Not allowed.', 403 );
		}
		$path = directoryweb_private_dir() . '/' . basename( (string) get_post_meta( $id, 'directoryweb_c_logo', true ) );
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
		$id = absint( $_GET['claim'] ?? 0 );
		if ( ! current_user_can( DIRECTORYWEB_CAP ) || ! check_admin_referer( 'directoryweb_claim_decide_' . $id ) || 'directoryweb_claim' !== get_post_type( $id ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- DirectoryWeb's own capability (directoryweb-caps.php)
			wp_die( 'Not allowed.', 403 );
		}
		$lid = (int) get_post_meta( $id, 'directoryweb_c_listing', true );
		if ( 'approve' === sanitize_key( wp_unslash( $_GET['do'] ?? '' ) ) && 'directoryweb_listing' === get_post_type( $lid ) ) {
			do_action( 'directoryweb_claim_before_apply', $lid, (array) get_post_meta( $id, 'directoryweb_c_proposed', true ), $id ); // enrichment.php logs the organisation as the source
			foreach ( (array) get_post_meta( $id, 'directoryweb_c_proposed', true ) as $key => $val ) {
				if ( isset( DIRECTORYWEB_CLAIM_FIELDS[ $key ] ) ) {
					if ( 'directoryweb_services' === $key ) {
						directoryweb_set_item_tags( $lid, explode( ',', (string) $val ), directoryweb_service_slugs() ); // the services only; the listing's types stay
					} elseif ( 'directoryweb_phone' === $key ) {
						directoryweb_listing_put_phone( $lid, (string) $val, '' ); // into the Phone numbers list
					} elseif ( 'directoryweb_whatsapp' === $key ) {
						directoryweb_listing_put_phone( $lid, '', (string) $val );
					} else {
						update_post_meta( $lid, $key, $val );
					}
					if ( 'directoryweb_services' === $key ) { // services are what the organisation says it offers; record that
						update_post_meta( $lid, 'directoryweb_services_source', 'the organisation' );
						update_post_meta( $lid, 'directoryweb_services_on', wp_date( 'Y-m-d' ) );
					}
				}
			}
			$logo = (string) get_post_meta( $id, 'directoryweb_c_logo', true );
			$path = $logo ? directoryweb_private_dir() . '/' . basename( $logo ) : '';
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
			update_post_meta( $lid, 'directoryweb_verified', true );
			update_post_meta( $lid, 'directoryweb_verified_on', wp_date( 'Y-m-d' ) );
			update_post_meta( $lid, 'directoryweb_needs_reverify', false );
			if ( function_exists( 'directoryweb_org_people_from_claim' ) ) {
				directoryweb_org_people_from_claim( $id, $lid ); // the claimant becomes one of its people, not shown (org-people.php)
			}
			update_post_meta( $id, 'directoryweb_c_status', 'approved' );
		} else {
			update_post_meta( $id, 'directoryweb_c_status', 'rejected' );
		}
		wp_safe_redirect( admin_url( 'post.php?post=' . $id . '&action=edit' ) );
		exit;
	}
);
