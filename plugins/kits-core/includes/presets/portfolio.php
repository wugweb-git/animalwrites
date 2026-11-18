<?php
/**
 * Preset kits-portfolio: work a site shows (client, year, role, link). Schema: CreativeWork.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'post_type'   => 'kits_portfolio',
	'title'       => __( 'Portfolio', 'kits-core' ),
	'description' => __( 'Work the site shows: client, year, role and a link.', 'kits-core' ),
	'args'        => array(
		'labels'    => array(
			'name'          => __( 'Portfolio', 'kits-core' ),
			'singular_name' => __( 'Portfolio item', 'kits-core' ),
			'add_new_item'  => __( 'Add portfolio item', 'kits-core' ),
			'edit_item'     => __( 'Edit portfolio item', 'kits-core' ),
			'view_item'     => __( 'View portfolio item', 'kits-core' ),
			'search_items'  => __( 'Search portfolio', 'kits-core' ),
			'not_found'     => __( 'No portfolio items found.', 'kits-core' ),
			'all_items'     => __( 'All portfolio items', 'kits-core' ),
		),
		'rewrite'   => array(
			'slug'       => 'portfolio',
			'with_front' => false,
		),
		'menu_icon' => 'dashicons-portfolio',
	),
	'fields'      => array(
		'kits_portfolio_client' => array(
			'label'  => __( 'Client', 'kits-core' ),
			'format' => 'text',
		),
		'kits_portfolio_year'   => array(
			'label'  => __( 'Year', 'kits-core' ),
			'format' => 'text',
		),
		'kits_portfolio_role'   => array(
			'label'  => __( 'Role', 'kits-core' ),
			'format' => 'text',
		),
		'kits_portfolio_link'   => array(
			'label'  => __( 'Link', 'kits-core' ),
			'format' => 'url',
		),
	),
	'schema'      => array(
		'type'   => 'CreativeWork',
		'fields' => array(
			'sourceOrganization' => 'kits_portfolio_client',
			'dateCreated'        => 'kits_portfolio_year',
			'url'                => 'kits_portfolio_link',
		),
	),
);
