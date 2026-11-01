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

add_action(
	'init',
	function () {
		register_post_meta(
			'attachment',
			'kits_image_context',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_textarea_field',
				'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
			)
		);
	}
);

// The note is for editors only. REST returns registered meta to anyone who can read the attachment, so it is removed from
// every response (including embeds) unless the viewer can edit that attachment.
add_filter(
	'rest_prepare_attachment',
	function ( $response, $post ) {
		$data = $response->get_data();
		if ( isset( $data['meta'] ) && is_array( $data['meta'] ) && array_key_exists( 'kits_image_context', $data['meta'] ) && ! current_user_can( 'edit_post', $post->ID ) ) {
			unset( $data['meta']['kits_image_context'] );
			$response->set_data( $data );
		}
		return $response;
	},
	10,
	2
);
