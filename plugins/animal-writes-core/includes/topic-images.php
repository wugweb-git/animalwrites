<?php
/**
 * Topic photos, set in WP Admin: News → Topics → edit a topic → "Topic photo" (pick or upload from the media library).
 * News and Legal Watch items show their topic's photo (see aw_item_image() in media.php). Stored as term meta
 * `aw_topic_image` (attachment ID). The general photos for items without a topic stay in the option aw_topic_images['default'].
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Attachment ID of a topic's photo (term meta; older option mapping as fallback). */
function aw_topic_image_id( $term ) {
	$term = is_object( $term ) ? $term : get_term( $term, 'aw_topic' );
	if ( ! $term || is_wp_error( $term ) ) {
		return 0;
	}
	$id = (int) get_term_meta( $term->term_id, 'aw_topic_image', true );
	if ( ! $id ) {
		$map = (array) get_option( 'aw_topic_images', array() );
		$id  = (int) ( $map[ $term->slug ] ?? 0 );
	}
	return $id;
}

/** The picker: a hidden ID field, a preview and two buttons (wp.media). */
function aw_topic_image_field( $id ) {
	$src = $id ? wp_get_attachment_image_url( $id, 'medium' ) : '';
	echo '<div class="aw-topic-image">';
	echo '<input type="hidden" name="aw_topic_image" value="' . esc_attr( (string) $id ) . '">';
	echo '<p><img src="' . esc_url( (string) $src ) . '" alt="" style="max-width:300px;height:auto;' . ( $src ? '' : 'display:none' ) . '"></p>';
	echo '<p><button type="button" class="button aw-topic-image-pick">Choose photo</button> ';
	echo '<button type="button" class="button-link-delete aw-topic-image-clear"' . ( $src ? '' : ' style="display:none"' ) . '>Remove</button></p>';
	echo '<p class="description">Shown on news and Legal Watch items in this topic. Use a photo you have the rights to (your own, or openly licensed). Landscape, at least 1200 px wide.</p>';
	echo '</div>';
}

add_action(
	'aw_topic_add_form_fields',
	function () {
		echo '<div class="form-field"><label>Topic photo</label>';
		aw_topic_image_field( 0 );
		echo '</div>';
	}
);
add_action(
	'aw_topic_edit_form_fields',
	function ( $term ) {
		echo '<tr class="form-field"><th scope="row"><label>Topic photo</label></th><td>';
		aw_topic_image_field( aw_topic_image_id( $term ) );
		echo '</td></tr>';
	}
);

/** Save the field (term edit screens only; WordPress checks the term nonce before these hooks run). */
function aw_topic_image_save( $term_id ) {
	if ( ! isset( $_POST['aw_topic_image'] ) || ! current_user_can( 'manage_categories' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	$id = absint( wp_unslash( $_POST['aw_topic_image'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( $id && wp_attachment_is_image( $id ) ) {
		update_term_meta( $term_id, 'aw_topic_image', $id );
	} else {
		delete_term_meta( $term_id, 'aw_topic_image' );
	}
	$map  = (array) get_option( 'aw_topic_images', array() ); // keep the fallback mapping in step
	$slug = get_term( $term_id, 'aw_topic' )->slug;
	unset( $map[ $slug ] );
	update_option( 'aw_topic_images', $map, false );
}
add_action( 'created_aw_topic', 'aw_topic_image_save' );
add_action( 'edited_aw_topic', 'aw_topic_image_save' );

// Media picker script on the Topics screens.
add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( ! in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) || 'aw_topic' !== ( $_GET['taxonomy'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		wp_enqueue_media();
		wp_add_inline_script(
			'media-editor',
			"jQuery(function($){
				$(document).on('click','.aw-topic-image-pick',function(e){
					e.preventDefault(); var box=$(this).closest('.aw-topic-image');
					var frame=wp.media({title:'Topic photo',button:{text:'Use this photo'},library:{type:'image'},multiple:false});
					frame.on('select',function(){ var a=frame.state().get('selection').first().toJSON();
						box.find('input').val(a.id); box.find('img').attr('src',(a.sizes&&a.sizes.medium?a.sizes.medium.url:a.url)).show(); box.find('.aw-topic-image-clear').show(); });
					frame.open();
				});
				$(document).on('click','.aw-topic-image-clear',function(e){ e.preventDefault(); var box=$(this).closest('.aw-topic-image');
					box.find('input').val(''); box.find('img').attr('src','').hide(); $(this).hide(); });
			});"
		);
	}
);

// Topics list: a thumbnail column.
add_filter( 'manage_edit-aw_topic_columns', fn( $c ) => array_slice( $c, 0, 1, true ) + array( 'aw_topic_image' => 'Photo' ) + array_slice( $c, 1, null, true ) );
add_filter(
	'manage_aw_topic_custom_column',
	function ( $out, $col, $term_id ) {
		if ( 'aw_topic_image' !== $col ) {
			return $out;
		}
		$id = aw_topic_image_id( $term_id );
		return $id ? wp_get_attachment_image( $id, array( 60, 34 ) ) : '—';
	},
	10,
	3
);
