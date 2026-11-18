<?php
/**
 * Preset kits-infographic: a picture with a long text alternative (WCAG 1.1.1), its source and a link to the data.
 * Schema: ImageObject (caption, isBasedOn the data source).
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'post_type'   => 'kits_infographic',
	'title'       => __( 'Infographics', 'kits-core' ),
	'description' => __( 'Infographics, each with a long text description for people who cannot see the picture, its source and a link to the data.', 'kits-core' ),
	'args'        => array(
		'labels'    => array(
			'name'                     => __( 'Infographics', 'kits-core' ),
			'singular_name'            => __( 'Infographic', 'kits-core' ),
			'add_new_item'             => __( 'Add infographic', 'kits-core' ),
			'edit_item'                => __( 'Edit infographic', 'kits-core' ),
			'view_item'                => __( 'View infographic', 'kits-core' ),
			'search_items'             => __( 'Search infographics', 'kits-core' ),
			'not_found'                => __( 'No infographics found.', 'kits-core' ),
			'all_items'                => __( 'All infographics', 'kits-core' ),
			'add_new'                  => __( 'Add infographic', 'kits-core' ),
			'new_item'                 => __( 'New infographic', 'kits-core' ),
			'view_items'               => __( 'View infographics', 'kits-core' ),
			'not_found_in_trash'       => __( 'No infographics found in the Trash.', 'kits-core' ),
			'archives'                 => __( 'Infographic archives', 'kits-core' ),
			'attributes'               => __( 'Infographic attributes', 'kits-core' ),
			'insert_into_item'         => __( 'Insert into infographic', 'kits-core' ),
			'uploaded_to_this_item'    => __( 'Uploaded to this infographic', 'kits-core' ),
			'filter_items_list'        => __( 'Filter infographics list', 'kits-core' ),
			'items_list_navigation'    => __( 'Infographics list navigation', 'kits-core' ),
			'items_list'               => __( 'Infographics list', 'kits-core' ),
			'item_published'           => __( 'Infographic published.', 'kits-core' ),
			'item_published_privately' => __( 'Infographic published privately.', 'kits-core' ),
			'item_reverted_to_draft'   => __( 'Infographic reverted to draft.', 'kits-core' ),
			'item_scheduled'           => __( 'Infographic scheduled.', 'kits-core' ),
			'item_updated'             => __( 'Infographic updated.', 'kits-core' ),
			'item_link'                => __( 'Infographic link', 'kits-core' ),
			'item_link_description'    => __( 'A link to an infographic.', 'kits-core' ),
		),
		'rewrite'   => array(
			'slug'       => 'infographics',
			'with_front' => false,
		),
		'menu_icon' => 'dashicons-chart-pie', // The picture and its long description are fields (one place each), not blocks.
	),
	'fields'      => array(
		'kits_infographic_image'       => array(
			'label'  => __( 'Picture', 'kits-core' ),
			'format' => 'attachment',
		),
		'kits_infographic_description' => array(
			'label'  => __( 'Long description (text alternative)', 'kits-core' ),
			'format' => 'textarea',
		),
		'kits_infographic_source'      => array(
			'label'  => __( 'Source', 'kits-core' ),
			'format' => 'text',
		),
		'kits_infographic_data_url'    => array(
			'label'  => __( 'Data link', 'kits-core' ),
			'format' => 'url',
		),
	),
	'schema'      => array(
		'type'   => 'ImageObject',
		'fields' => array(
			'contentUrl' => 'kits_infographic_image',
			'caption'    => 'kits_infographic_description',
			'isBasedOn'  => 'kits_infographic_data_url',
		),
	),
);
