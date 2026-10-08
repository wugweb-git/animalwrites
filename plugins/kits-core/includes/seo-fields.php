<?php
/**
 * Editable SEO fields, no SEO plugin needed.
 *
 * - Categories and topics (every public taxonomy): SEO title and meta description, on the term edit screen.
 * - Pages and posts: SEO title, meta description, share title, share description and share image (media ID),
 *   in an "SEO" box on the edit screen.
 * Empty fields fall back to what seo.php generates. A title without the site name gets " – {site}" added.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KITS_SEO_POST_FIELDS = array(
	'kits_seo_title' => 'SEO title',
	'kits_seo_desc'  => 'Meta description',
	'kits_og_title'  => 'Share title',
	'kits_og_desc'   => 'Share description',
	'kits_og_image'  => 'Share image (media ID, 1200×630)',
);

/** The override for the current view: key => value. */
function kits_seo_override( $key ) {
	if ( is_singular() ) {
		return trim( (string) get_post_meta( get_queried_object_id(), $key, true ) );
	}
	if ( ( is_tax() || is_category() || is_tag() ) && in_array( $key, array( 'kits_seo_title', 'kits_seo_desc' ), true ) ) {
		return trim( (string) get_term_meta( get_queried_object_id(), $key, true ) );
	}
	return '';
}

add_filter(
	'pre_get_document_title',
	function ( $title ) {
		$t = kits_seo_override( 'kits_seo_title' );
		if ( '' === $t || is_front_page() ) {
			return $title;
		}
		$site = get_bloginfo( 'name' );
		return false !== stripos( $t, $site ) ? $t : $t . ' – ' . $site;
	},
	20
);

/* ---------- Term screens ---------- */

add_action(
	'admin_init',
	function () {
		foreach ( get_taxonomies( array( 'public' => true ) ) as $tax ) {
			add_action(
				"{$tax}_edit_form_fields",
				function ( $term ) {
					foreach ( array( 'kits_seo_title' => 'SEO title', 'kits_seo_desc' => 'Meta description' ) as $k => $label ) {
						$v = (string) get_term_meta( $term->term_id, $k, true );
						echo '<tr class="form-field"><th scope="row"><label for="' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label></th><td>'
							. ( 'kits_seo_desc' === $k ? '<textarea id="' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '" rows="3">' . esc_textarea( $v ) . '</textarea>' : '<input type="text" id="' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">' )
							. '<p class="description">' . esc_html( 'kits_seo_desc' === $k ? 'About 150 characters. Empty: the description above is used.' : 'Empty: the name is used.' ) . '</p></td></tr>';
					}
				},
				30
			);
			add_action(
				"edited_{$tax}",
				function ( $term_id ) {
					if ( ! current_user_can( 'manage_categories' ) || ! isset( $_POST['kits_seo_title'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
						return;
					}
					update_term_meta( $term_id, 'kits_seo_title', sanitize_text_field( wp_unslash( $_POST['kits_seo_title'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
					update_term_meta( $term_id, 'kits_seo_desc', sanitize_textarea_field( wp_unslash( $_POST['kits_seo_desc'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
				}
			);
		}
	}
);

/* ---------- Page and post screens ---------- */

add_action(
	'add_meta_boxes',
	function ( $type ) {
		$obj = get_post_type_object( $type );
		if ( $obj && $obj->public ) {
			add_meta_box( 'kits_seo', 'SEO', 'kits_seo_box', $type, 'normal', 'low' );
		}
	}
);

function kits_seo_box( $post ) {
	wp_nonce_field( 'kits_seo_' . $post->ID, '_kits_seo' );
	echo '<table class="form-table">';
	foreach ( KITS_SEO_POST_FIELDS as $k => $label ) {
		$v = (string) get_post_meta( $post->ID, $k, true );
		echo '<tr><th><label for="' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label></th><td>'
			. ( in_array( $k, array( 'kits_seo_desc', 'kits_og_desc' ), true ) ? '<textarea class="large-text" rows="2" id="' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '">' . esc_textarea( $v ) . '</textarea>' : '<input class="large-text" type="text" id="' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">' )
			. '</td></tr>';
	}
	echo '</table><p class="description">Empty fields use the page title, excerpt and featured image.</p>';
}

add_action(
	'save_post',
	function ( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || ! isset( $_POST['_kits_seo'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_kits_seo'] ) ), 'kits_seo_' . $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		foreach ( array_keys( KITS_SEO_POST_FIELDS ) as $k ) {
			$v = wp_unslash( $_POST[ $k ] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$v = 'kits_og_image' === $k ? (string) absint( $v ) : ( in_array( $k, array( 'kits_seo_desc', 'kits_og_desc' ), true ) ? sanitize_textarea_field( $v ) : sanitize_text_field( $v ) );
			if ( '' === $v || '0' === $v ) {
				delete_post_meta( $post_id, $k );
			} else {
				update_post_meta( $post_id, $k, $v );
			}
		}
	}
);
