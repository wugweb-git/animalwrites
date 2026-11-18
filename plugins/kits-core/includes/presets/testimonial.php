<?php
/**
 * Preset kits-testimonial: a quote with the person's name and role, shown only once their consent date is recorded.
 * The consent date is private: not in the REST API and never shown. Schema: Review, and only when the reviewed item
 * is named; otherwise no node.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'post_type'     => 'kits_testimonial',
	'title'         => __( 'Testimonials', 'kits-core' ),
	'description'   => __( 'Quotes from people, each shown only after the date of their consent is filled in.', 'kits-core' ),
	'args'          => array(
		'labels'    => array(
			'name'                     => __( 'Testimonials', 'kits-core' ),
			'singular_name'            => __( 'Testimonial', 'kits-core' ),
			'add_new_item'             => __( 'Add testimonial', 'kits-core' ),
			'edit_item'                => __( 'Edit testimonial', 'kits-core' ),
			'view_item'                => __( 'View testimonial', 'kits-core' ),
			'search_items'             => __( 'Search testimonials', 'kits-core' ),
			'not_found'                => __( 'No testimonials found.', 'kits-core' ),
			'all_items'                => __( 'All testimonials', 'kits-core' ),
			'add_new'                  => __( 'Add testimonial', 'kits-core' ),
			'new_item'                 => __( 'New testimonial', 'kits-core' ),
			'view_items'               => __( 'View testimonials', 'kits-core' ),
			'not_found_in_trash'       => __( 'No testimonials found in the Trash.', 'kits-core' ),
			'archives'                 => __( 'Testimonial archives', 'kits-core' ),
			'attributes'               => __( 'Testimonial attributes', 'kits-core' ),
			'insert_into_item'         => __( 'Insert into testimonial', 'kits-core' ),
			'uploaded_to_this_item'    => __( 'Uploaded to this testimonial', 'kits-core' ),
			'filter_items_list'        => __( 'Filter testimonials list', 'kits-core' ),
			'items_list_navigation'    => __( 'Testimonials list navigation', 'kits-core' ),
			'items_list'               => __( 'Testimonials list', 'kits-core' ),
			'item_published'           => __( 'Testimonial published.', 'kits-core' ),
			'item_published_privately' => __( 'Testimonial published privately.', 'kits-core' ),
			'item_reverted_to_draft'   => __( 'Testimonial reverted to draft.', 'kits-core' ),
			'item_scheduled'           => __( 'Testimonial scheduled.', 'kits-core' ),
			'item_updated'             => __( 'Testimonial updated.', 'kits-core' ),
			'item_link'                => __( 'Testimonial link', 'kits-core' ),
			'item_link_description'    => __( 'A link to a testimonial.', 'kits-core' ),
		),
		'rewrite'   => array(
			'slug'       => 'testimonials',
			'with_front' => false,
		),
		'menu_icon' => 'dashicons-format-quote',
		'template'  => array(
			array( 'core/quote' ),
		),
	),
	'fields'        => array(
		'kits_testimonial_quote'   => array(
			'label'  => __( 'Quote', 'kits-core' ),
			'format' => 'textarea',
		),
		'kits_testimonial_name'    => array(
			'label'  => __( 'Name', 'kits-core' ),
			'format' => 'text',
		),
		'kits_testimonial_role'    => array(
			'label'  => __( 'Role', 'kits-core' ),
			'format' => 'text',
		),
		'kits_testimonial_item'    => array(
			'label'  => __( 'What it is about (product, service or event; for search engines)', 'kits-core' ),
			'format' => 'text',
		),
		'kits_testimonial_consent' => array(
			'label'   => __( 'Consent date (DD-MM-YY)', 'kits-core' ),
			'format'  => 'date',
			'private' => true,
		),
	),
	'consent_field' => 'kits_testimonial_consent',
	'schema'        => array(
		'type'     => 'Review',
		'fields'   => array(
			'reviewBody'   => 'kits_testimonial_quote',
			'author'       => 'kits_testimonial_name',
			'itemReviewed' => 'kits_testimonial_item',
		),
		'requires' => array( 'itemReviewed', 'reviewBody' ),
	),
);
