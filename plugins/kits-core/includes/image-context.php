<?php
/**
 * imageContext: an editor-facing note on the copy an image supports (Media Library). Never shown on the site.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'attachment_fields_to_edit',
	function ( $fields, $post ) {
		$fields['kits_image_context'] = array( 'label' => 'Image context', 'input' => 'textarea', 'value' => (string) get_post_meta( $post->ID, 'kits_image_context', true ), 'helps' => 'Note for editors: the copy this image supports. Not shown on the site.' );
		return $fields;
	},
	10,
	2
);
add_filter(
	'attachment_fields_to_save',
	function ( $post, $att ) {
		if ( isset( $att['kits_image_context'] ) ) {
			update_post_meta( $post['ID'], 'kits_image_context', sanitize_textarea_field( $att['kits_image_context'] ) );
		}
		return $post;
	},
	10,
	2
);

