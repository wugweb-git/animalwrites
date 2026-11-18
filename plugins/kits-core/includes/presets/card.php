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
			'name'                     => __( 'Cards', 'kits-core' ),
			'singular_name'            => __( 'Card', 'kits-core' ),
			'add_new_item'             => __( 'Add card', 'kits-core' ),
			'edit_item'                => __( 'Edit card', 'kits-core' ),
			'view_item'                => __( 'View card', 'kits-core' ),
			'search_items'             => __( 'Search cards', 'kits-core' ),
			'not_found'                => __( 'No cards found.', 'kits-core' ),
			'all_items'                => __( 'All cards', 'kits-core' ),
			'add_new'                  => __( 'Add card', 'kits-core' ),
			'new_item'                 => __( 'New card', 'kits-core' ),
			'view_items'               => __( 'View cards', 'kits-core' ),
			'not_found_in_trash'       => __( 'No cards found in the Trash.', 'kits-core' ),
			'archives'                 => __( 'Card archives', 'kits-core' ),
			'attributes'               => __( 'Card attributes', 'kits-core' ),
			'insert_into_item'         => __( 'Insert into card', 'kits-core' ),
			'uploaded_to_this_item'    => __( 'Uploaded to this card', 'kits-core' ),
			'filter_items_list'        => __( 'Filter cards list', 'kits-core' ),
			'items_list_navigation'    => __( 'Cards list navigation', 'kits-core' ),
			'items_list'               => __( 'Cards list', 'kits-core' ),
			'item_published'           => __( 'Card published.', 'kits-core' ),
			'item_published_privately' => __( 'Card published privately.', 'kits-core' ),
			'item_reverted_to_draft'   => __( 'Card reverted to draft.', 'kits-core' ),
			'item_scheduled'           => __( 'Card scheduled.', 'kits-core' ),
			'item_updated'             => __( 'Card updated.', 'kits-core' ),
			'item_link'                => __( 'Card link', 'kits-core' ),
			'item_link_description'    => __( 'A link to a card.', 'kits-core' ),
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
