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
			'name'          => __( 'Infographics', 'kits-core' ),
			'singular_name' => __( 'Infographic', 'kits-core' ),
			'add_new_item'  => __( 'Add infographic', 'kits-core' ),
			'edit_item'     => __( 'Edit infographic', 'kits-core' ),
			'view_item'     => __( 'View infographic', 'kits-core' ),
			'search_items'  => __( 'Search infographics', 'kits-core' ),
			'not_found'     => __( 'No infographics found.', 'kits-core' ),
			'all_items'     => __( 'All infographics', 'kits-core' ),
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
