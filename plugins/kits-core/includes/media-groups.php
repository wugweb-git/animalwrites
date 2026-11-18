<?php
/**
 * Media group: a label for every attachment (favicon, logo, news image ...) as a core taxonomy.
 *
 * - The Media list gets a filterable "Media group" column and groups can be assigned through the REST API
 *   (/wp/v2/media-groups).
 * - The taxonomy is never public: no rewrite, no query var, not in menus.
 * - A new upload gets a group from its title, file name, type and parent, once; an existing assignment is never changed.
 * Moved from Animal Writes Core on 18 Nov 2026. The taxonomy name is KITS_MEDIA_GROUP when the site defines it (Tools →
 * Taxonomy rename), else aw_media_group, the name it has on the live site.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Name of the Media group taxonomy: KITS_MEDIA_GROUP when defined, else the active name (kits_media_group since the rename ran on live, 18 Nov 2026). */
function kits_media_group_taxonomy() {
	if ( defined( 'KITS_MEDIA_GROUP' ) ) {
		return (string) KITS_MEDIA_GROUP;
	}
	return function_exists( 'kits_taxonomy_current' ) ? (string) kits_taxonomy_current( 'kits_media_group' ) : 'kits_media_group';
}

/** Register the Media group taxonomy for attachments. */
function kits_media_group_register() {
	register_taxonomy(
		kits_media_group_taxonomy(),
		'attachment',
		array(
			'labels'               => array(
				'name'          => 'Media groups',
				'singular_name' => 'Media group',
			),
			'public'                => false,
			'show_ui'               => true,
			'show_admin_column'     => true,
			'show_in_rest'          => true,
			'rest_base'             => 'media-groups',
			'hierarchical'          => false,
			'rewrite'               => false,
			'query_var'             => false,
			'show_in_nav_menus'     => false,
			'show_tagcloud'         => false,
			'update_count_callback' => '_update_generic_term_count',
		)
	);
}
add_action( 'init', 'kits_media_group_register' );

/**
 * Name of the group for a file, by the first matching rule. Pure: no WordPress calls.
 *
 * @param string $title       Attachment title.
 * @param string $file        File name (with or without a path).
 * @param string $mime        MIME type.
 * @param string $parent_type Post type of the parent, or an empty string.
 * @return string
 */
function kits_media_group_for( $title, $file, $mime, $parent_type ) {
	$title = trim( (string) $title );
	$base  = pathinfo( basename( (string) $file ), PATHINFO_FILENAME );

	if ( 0 === stripos( $title, 'Favicon:' ) ) {
		return 'Favicon';
	}
	if ( 0 === stripos( $title, 'Email banner' ) ) {
		return 'Email banner';
	}
	if ( ' logo' === strtolower( substr( $title, -5 ) ) || 0 === stripos( $title, 'Logo:' ) ) {
		return 'Logo';
	}
	if ( '-source' === substr( $base, -7 ) ) {
		return 'News image';
	}
	if ( 'aw_listing' === $parent_type ) {
		return 'Listing photo';
	}
	if ( 'aw_legal' === $parent_type ) {
		return 'Library document';
	}
	if ( 'aw_press' === $parent_type ) {
		return 'Press image';
	}
	if ( preg_match( '/^Animal Writes campaign photo/i', $title ) || 0 === strpos( $base, 'aw1-im' ) ) {
		return 'Campaign photo';
	}
	if ( 0 === strpos( $base, 'photo-' ) || preg_match( '/^\d\d_/', $base ) ) {
		return 'Stock photo';
	}
	if ( 'application/pdf' === $mime ) {
		return 'Document';
	}
	if ( 'post' === $parent_type || 'page' === $parent_type ) {
		return 'Guide image';
	}
	return 'Other';
}

/**
 * Give a new attachment its group, unless it already has one. Runs after the title auto-fill (priority 10).
 *
 * @param int $attachment_id Attachment ID.
 */
function kits_media_group_assign( $attachment_id ) {
	if ( wp_get_object_terms( $attachment_id, kits_media_group_taxonomy(), array( 'fields' => 'ids' ) ) ) {
		return;
	}
	$post   = get_post( $attachment_id );
	$file   = (string) get_attached_file( $attachment_id );
	$parent = ( $post && $post->post_parent ) ? (string) get_post_type( $post->post_parent ) : '';
	$name   = kits_media_group_for( $post ? $post->post_title : '', basename( $file ), $post ? $post->post_mime_type : '', $parent );
	wp_set_object_terms( $attachment_id, $name, kits_media_group_taxonomy() );
}
add_action( 'add_attachment', 'kits_media_group_assign', 20 );
