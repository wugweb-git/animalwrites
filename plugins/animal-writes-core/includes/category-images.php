<?php
/**
 * Category photos, set in WP Admin on each list's screen (Categories, Listing types, Opportunity types): add or edit
 * a category's name, slug, write-up (Description) and photo (pick or upload from the media library).
 * Category photos are also used on news and Library items in that category.
 * News and Library items show their category's photo (see aw_item_image() in media.php). Stored as term meta
 * `aw_category_image` (attachment ID). The general photos for items without a category stay in the option aw_category_images['default'].
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Category screens that get the photo field. */
const AW_PHOTO_TAXONOMIES = array( 'category', 'aw_listing_type', 'aw_opportunity_type' );

/** Attachment ID of a category's photo (term meta; for Categories, the older option mapping as fallback). */
function aw_category_image_id( $term ) {
	$term = is_object( $term ) ? $term : get_term( $term );
	if ( ! $term || is_wp_error( $term ) ) {
		return 0;
	}
	$id = (int) get_term_meta( $term->term_id, 'aw_category_image', true );
	if ( ! $id ) {
		$map = (array) get_option( 'aw_category_images', array() );
		$id  = (int) ( $map[ $term->slug ] ?? 0 );
	}
	return $id;
}

/** The picker: a hidden ID field, a preview and two buttons (wp.media). */
function aw_category_image_field( $id ) {
	$src = $id ? wp_get_attachment_image_url( $id, 'medium' ) : '';
	echo '<div class="aw-category-image">';
	echo '<input type="hidden" name="aw_category_image" value="' . esc_attr( (string) $id ) . '">';
	echo '<p><img src="' . esc_url( (string) $src ) . '" alt="" style="max-width:300px;height:auto;' . ( $src ? '' : 'display:none' ) . '"></p>';
	echo '<p><button type="button" class="button aw-category-image-pick">Choose photo</button> ';
	echo '<button type="button" class="button-link-delete aw-category-image-clear"' . ( $src ? '' : ' style="display:none"' ) . '>Remove</button></p>';
	echo '<p class="description">Shown at the top of this category\'s page (and, for categories, on news and Legal Watch items in it). Use a photo you have the rights to (your own, or openly licensed). Landscape, at least 1200 px wide.</p>';
	echo '</div>';
}

foreach ( AW_PHOTO_TAXONOMIES as $aw_tx ) {
	add_action(
		"{$aw_tx}_add_form_fields",
		function () {
			echo '<div class="form-field"><label>Photo</label>';
			aw_category_image_field( 0 );
			echo '</div>';
		}
	);
	add_action(
		"{$aw_tx}_edit_form_fields",
		function ( $term ) {
			echo '<tr class="form-field"><th scope="row"><label>Photo</label></th><td>';
			aw_category_image_field( aw_category_image_id( $term ) );
			echo '</td></tr>';
		}
	);
	add_action( "created_{$aw_tx}", 'aw_category_image_save' );
	add_action( "edited_{$aw_tx}", 'aw_category_image_save' );
	add_filter( "manage_edit-{$aw_tx}_columns", fn( $c ) => array_slice( $c, 0, 1, true ) + array( 'aw_category_image' => 'Photo' ) + array_slice( $c, 1, null, true ) );
	add_filter( "manage_{$aw_tx}_custom_column", 'aw_category_image_column', 10, 3 );
}

/** Save the field (term edit screens only; WordPress checks the term nonce before these hooks run). */
function aw_category_image_save( $term_id ) {
	if ( ! isset( $_POST['aw_category_image'] ) || ! current_user_can( 'manage_categories' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	$id = absint( wp_unslash( $_POST['aw_category_image'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( $id && wp_attachment_is_image( $id ) ) {
		update_term_meta( $term_id, 'aw_category_image', $id );
	} else {
		delete_term_meta( $term_id, 'aw_category_image' );
	}
	$term = get_term( $term_id );
	if ( $term && 'category' === $term->taxonomy ) {
		$map = (array) get_option( 'aw_category_images', array() ); // keep the fallback mapping in step
		unset( $map[ $term->slug ] );
		update_option( 'aw_category_images', $map, false );
	}
}

// Media picker script on the category screens.
add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( ! in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) || ! in_array( $_GET['taxonomy'] ?? '', AW_PHOTO_TAXONOMIES, true ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		wp_enqueue_media();
		wp_add_inline_script(
			'media-editor',
			"jQuery(function($){
				$(document).on('click','.aw-category-image-pick',function(e){
					e.preventDefault(); var box=$(this).closest('.aw-category-image');
					var frame=wp.media({title:'Category photo',button:{text:'Use this photo'},library:{type:'image'},multiple:false});
					frame.on('select',function(){ var a=frame.state().get('selection').first().toJSON();
						box.find('input').val(a.id); box.find('img').attr('src',(a.sizes&&a.sizes.medium?a.sizes.medium.url:a.url)).show(); box.find('.aw-category-image-clear').show(); });
					frame.open();
				});
				$(document).on('click','.aw-category-image-clear',function(e){ e.preventDefault(); var box=$(this).closest('.aw-category-image');
					box.find('input').val(''); box.find('img').attr('src','').hide(); $(this).hide(); });
			});"
		);
	}
);

/** Thumbnail column on the category lists. */
function aw_category_image_column( $out, $col, $term_id ) {
	if ( 'aw_category_image' !== $col ) {
		return $out;
	}
	$id = aw_category_image_id( $term_id );
	return $id ? wp_get_attachment_image( $id, array( 60, 34 ) ) : '—';
}

/* ---------- Category banner (Phase 11 G4): optional; empty = no banner on the category page ---------- */

add_action(
	'category_edit_form_fields',
	function ( $term ) {
		$id = (int) get_term_meta( $term->term_id, 'aw_banner_image', true );
		echo '<tr class="form-field"><th scope="row"><label for="aw_banner_image">Banner image</label></th><td><input type="number" min="0" id="aw_banner_image" name="aw_banner_image" value="' . esc_attr( $id ? (string) $id : '' ) . '" style="width:8em">'
			. '<p class="description">Optional. Media ID of a wide image (16:5, up to 5 MB, WebP or AVIF) shown at the top of this category\'s page. Leave empty for no banner.</p></td></tr>';
	},
	20
);
add_action(
	'edited_category',
	function ( $term_id ) {
		if ( ! isset( $_POST['aw_banner_image'] ) || ! current_user_can( 'manage_categories' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$id = absint( wp_unslash( $_POST['aw_banner_image'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$id && wp_attachment_is_image( $id ) ? update_term_meta( $term_id, 'aw_banner_image', $id ) : delete_term_meta( $term_id, 'aw_banner_image' );
	}
);

/** The category page's banner (same markup and rules as the Banner pattern), or ''. */
function aw_category_banner_html() {
	if ( ! is_category() ) {
		return '';
	}
	$id = (int) get_term_meta( get_queried_object_id(), 'aw_banner_image', true );
	if ( ! $id ) {
		return '';
	}
	return '<div class="wp-block-cover alignwide kit-banner"><span aria-hidden="true" class="wp-block-cover__background has-secondary-background-color has-background-dim-30 has-background-dim"></span>'
		. wp_get_attachment_image( $id, 'full', false, array( 'class' => 'wp-block-cover__image-background', 'alt' => '', 'fetchpriority' => 'high', 'loading' => 'eager', 'data-object-fit' => 'cover' ) ) . '</div>';
}
add_filter( 'do_shortcode_tag', fn( $html, $tag ) => 'aw_section_head' === $tag ? aw_category_banner_html() . $html : $html, 6, 2 );
