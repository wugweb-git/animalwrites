<?php
/**
 * Preset kits-person: a team member or contributor: role, photo and public profile links. No private data (no phone,
 * email or address fields). Schema: Person (worksFor the site's Organization, added by the SEO plugin).
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'post_type'   => 'kits_person',
	'title'       => __( 'People', 'kits-core' ),
	'description' => __( 'Team members and contributors: role, photo and public profile links. No private details.', 'kits-core' ),
	'args'        => array(
		'labels'    => array(
			'name'                     => __( 'People', 'kits-core' ),
			'singular_name'            => __( 'Person', 'kits-core' ),
			'add_new_item'             => __( 'Add person', 'kits-core' ),
			'edit_item'                => __( 'Edit person', 'kits-core' ),
			'view_item'                => __( 'View person', 'kits-core' ),
			'search_items'             => __( 'Search people', 'kits-core' ),
			'not_found'                => __( 'No people found.', 'kits-core' ),
			'all_items'                => __( 'All people', 'kits-core' ),
			'add_new'                  => __( 'Add person', 'kits-core' ),
			'new_item'                 => __( 'New person', 'kits-core' ),
			'view_items'               => __( 'View people', 'kits-core' ),
			'not_found_in_trash'       => __( 'No people found in the Trash.', 'kits-core' ),
			'archives'                 => __( 'Person archives', 'kits-core' ),
			'attributes'               => __( 'Person attributes', 'kits-core' ),
			'insert_into_item'         => __( 'Insert into person', 'kits-core' ),
			'uploaded_to_this_item'    => __( 'Uploaded to this person', 'kits-core' ),
			'filter_items_list'        => __( 'Filter people list', 'kits-core' ),
			'items_list_navigation'    => __( 'People list navigation', 'kits-core' ),
			'items_list'               => __( 'People list', 'kits-core' ),
			'item_published'           => __( 'Person published.', 'kits-core' ),
			'item_published_privately' => __( 'Person published privately.', 'kits-core' ),
			'item_reverted_to_draft'   => __( 'Person reverted to draft.', 'kits-core' ),
			'item_scheduled'           => __( 'Person scheduled.', 'kits-core' ),
			'item_updated'             => __( 'Person updated.', 'kits-core' ),
			'item_link'                => __( 'Person link', 'kits-core' ),
			'item_link_description'    => __( 'A link to a person.', 'kits-core' ),
		),
		'rewrite'   => array(
			'slug'       => 'people',
			'with_front' => false,
		),
		'menu_icon' => 'dashicons-groups',
	),
	'fields'      => array(
		'kits_person_role'  => array(
			'label'  => __( 'Role', 'kits-core' ),
			'format' => 'text',
		),
		'kits_person_photo' => array(
			'label'  => __( 'Photo', 'kits-core' ),
			'format' => 'attachment',
		),
		'kits_person_links' => array(
			'label'  => __( 'Public profile links (one per line)', 'kits-core' ),
			'format' => 'urls',
		),
	),
	'schema'      => array(
		'type'   => 'Person',
		'fields' => array(
			'jobTitle' => 'kits_person_role',
			'image'    => 'kits_person_photo',
		),
	),
);
