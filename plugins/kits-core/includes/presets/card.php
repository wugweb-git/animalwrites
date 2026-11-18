<?php
/**
 * Preset kits-card: a reusable small card (a tip, a fact): picture, one line, a link. Its own pages are
 * noindex and it has no list page. Schema: CreativeWork.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'post_type'   => 'kits_card',
	'title'       => __( 'Cards', 'kits-core' ),
	'description' => __( 'Small reusable cards, such as tips and facts: a picture, one line and a link, shown inside other pages (no list page, not in search engines).', 'kits-core' ),
	'args'        => array(
		'labels'              => array(
			'name'          => __( 'Cards', 'kits-core' ),
			'singular_name' => __( 'Card', 'kits-core' ),
			'add_new_item'  => __( 'Add card', 'kits-core' ),
			'edit_item'     => __( 'Edit card', 'kits-core' ),
			'view_item'     => __( 'View card', 'kits-core' ),
			'search_items'  => __( 'Search cards', 'kits-core' ),
			'not_found'     => __( 'No cards found.', 'kits-core' ),
			'all_items'     => __( 'All cards', 'kits-core' ),
		),
		// Cards are shown inside other pages (Query Loop, kits/post-field bindings). They stay viewable, so Query Loop
		// can pick the type, but get no list page, no search results and no place in search engines (noindex below).
		'has_archive'         => false,
		'exclude_from_search' => true,
		'show_in_nav_menus'   => false,
		'rewrite'             => array(
			'slug'       => 'cards',
			'with_front' => false,
		),
		'menu_icon'           => 'dashicons-index-card',
	),
	'noindex'     => true,
	'fields'      => array(
		'kits_card_image' => array(
			'label'  => __( 'Picture', 'kits-core' ),
			'format' => 'attachment',
		),
		'kits_card_line'  => array(
			'label'  => __( 'Line', 'kits-core' ),
			'format' => 'text',
		),
		'kits_card_link'  => array(
			'label'  => __( 'Link', 'kits-core' ),
			'format' => 'url',
		),
	),
	'schema'      => array(
		'type'   => 'CreativeWork',
		'fields' => array(
			'image'    => 'kits_card_image',
			'abstract' => 'kits_card_line',
			'url'      => 'kits_card_link',
		),
	),
);
