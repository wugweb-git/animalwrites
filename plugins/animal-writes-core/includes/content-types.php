<?php
/**
 * Post types and taxonomies. Each pillar keeps its own URL space for SEO.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pillar post types.
 *
 * Thin cards (news, legal, research) store a summary + source link, never the source content.
 * Owned content (guides, campaigns, resources, opportunities, listings) is authored on site.
 * Resources are WordPress's own Posts (owner, 10 Oct 2026): relabelled, at /resources/{slug}/, with the Category list
 * as "Resource types" (Guide, How-to, FAQ, Research). See aw_resources_post_args() below.
 *
 * @return array slug => [ singular, plural, rewrite slug, supports ]
 */
function aw_post_types() {
	return array(
		'aw_news'        => array( 'News item', 'News', 'news', array( 'title', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ) ),
		'aw_legal'       => array( 'Library item', 'Library', 'library', array( 'title', 'editor', 'excerpt', 'custom-fields', 'revisions' ) ),
		'post'           => array( 'Resource', 'Resources', 'resources', array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ) ),
		'aw_campaign'    => array( 'Campaign', 'Campaign Hub', 'campaigns', array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ) ),
		'aw_opportunity' => array( 'Opportunity', 'Opportunities', 'opportunities', array( 'title', 'editor', 'excerpt', 'custom-fields', 'revisions' ) ),
		'aw_listing'     => array( 'Listing', 'Directory', 'directory', array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ) ),
	);
}

/**
 * Register post types and taxonomies.
 */
function aw_register_content_types() {
	foreach ( aw_post_types() as $type => $def ) {
		if ( 'post' === $type ) {
			continue; // built in; relabelled by aw_resources_post_args()
		}
		list( $singular, $plural, $slug, $supports ) = $def;

		$rewrite = array( 'slug' => $slug, 'with_front' => false );
		if ( 'aw_listing' === $type ) {
			// Singles live under /directory/org/ so /directory/{city}/{type}/ stays free.
			$rewrite['slug'] = 'directory/org';
		}

		register_post_type(
			$type,
			array(
				'labels'       => array(
					'name'          => $plural,
					'singular_name' => $singular,
					'menu_name'     => $plural,
					'add_new_item'  => sprintf( 'Add %s', $singular ),
					'edit_item'     => sprintf( 'Edit %s', $singular ),
				),
				'public'       => true,
				'show_in_rest' => true,
				'has_archive'  => $slug,
				'rewrite'      => $rewrite,
				'supports'     => $supports,
				'menu_icon'    => array( 'aw_listing' => 'dashicons-location', 'aw_news' => 'dashicons-media-text', 'post' => 'dashicons-book', 'aw_legal' => 'dashicons-bank', 'aw_opportunity' => 'dashicons-businessperson', 'aw_campaign' => 'dashicons-flag' )[ $type ] ?? 'dashicons-pets', // one icon each
			)
		);
	}

	$all = array_keys( aw_post_types() );

	register_taxonomy(
		'aw_topic',
		$all,
		array(
			'labels'            => array( 'name' => 'Categories', 'singular_name' => 'Category' ), // shared by every section; URLs stay /topic/{slug}/
			'hierarchical'      => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'topic', 'with_front' => false ),
		)
	);

	// Places are not a WordPress list: states and districts come from the LGD file (places.php).

	register_taxonomy(
		'aw_listing_type',
		array( 'aw_listing' ),
		array(
			'labels'            => array( 'name' => 'Listing types', 'singular_name' => 'Listing type' ),
			'hierarchical'      => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'directory-type', 'with_front' => false ),
		)
	);

	// Issuer is a label on Library items (meta aw_issuer, issuers.php), not a list.

	register_taxonomy(
		'aw_opportunity_type',
		array( 'aw_opportunity' ),
		array(
			'labels'            => array( 'name' => 'Opportunity types', 'singular_name' => 'Opportunity type' ),
			'hierarchical'      => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'opportunity-type', 'with_front' => false ),
		)
	);
}
add_action( 'init', 'aw_register_content_types' );

/* ---------- Resources = Posts ---------- */

/** Single Resources live at /resources/{slug}/ (the Posts permalink); the list is the Resources Page (hubs.php). */
const AW_RESOURCES_PERMALINK = '/resources/%postname%/';

add_filter( 'option_permalink_structure', fn( $s ) => '' === (string) $s ? $s : AW_RESOURCES_PERMALINK );

/** Posts relabelled as Resources in WP Admin; same supports and icon the Resources type had. */
function aw_resources_post_args( $args, $type ) {
	if ( 'post' !== $type ) {
		return $args;
	}
	list( $singular, $plural, , $supports ) = aw_post_types()['post'];
	$args['labels']    = array(
		'name'                  => $plural,
		'singular_name'         => $singular,
		'menu_name'             => $plural,
		'name_admin_bar'        => $singular,
		'all_items'             => 'All ' . $plural,
		'add_new'               => 'Add New Resource',
		'add_new_item'          => 'Add New Resource',
		'edit_item'             => 'Edit Resource',
		'new_item'              => 'New Resource',
		'view_item'             => 'View Resource',
		'view_items'            => 'View Resources',
		'search_items'          => 'Search Resources',
		'not_found'             => 'No resources found.',
		'not_found_in_trash'    => 'No resources found in Trash.',
		'archives'              => 'Resource archives',
		'attributes'            => 'Resource attributes',
		'insert_into_item'      => 'Insert into resource',
		'uploaded_to_this_item' => 'Uploaded to this resource',
		'filter_items_list'     => 'Filter resources list',
		'items_list_navigation' => 'Resources list navigation',
		'items_list'            => 'Resources list',
		'item_published'        => 'Resource published.',
		'item_updated'          => 'Resource updated.',
		'item_link'             => 'Resource link',
	);
	$args['menu_icon'] = 'dashicons-book';
	$args['supports']  = $supports;
	return $args;
}
add_filter( 'register_post_type_args', 'aw_resources_post_args', 10, 2 );

/** The Category list is "Resource types": labels and list filters only, no archive pages (as before). */
add_filter(
	'register_taxonomy_args',
	function ( $args, $tax ) {
		if ( 'category' !== $tax ) {
			return $args;
		}
		$args['labels']             = array(
			'name'          => 'Resource types',
			'singular_name' => 'Resource type',
			'menu_name'     => 'Resource types',
			'all_items'     => 'All resource types',
			'edit_item'     => 'Edit resource type',
			'view_item'     => 'View resource type',
			'update_item'   => 'Update resource type',
			'add_new_item'  => 'Add New Resource type',
			'new_item_name' => 'New resource type name',
			'search_items'  => 'Search resource types',
			'not_found'     => 'No resource types found.',
			'back_to_items' => '&larr; Go to Resource types',
		);
		$args['publicly_queryable'] = false;
		$args['rewrite']            = false;
		$args['show_admin_column']  = true;
		return $args;
	},
	10,
	2
);

// Resources have no tags.
add_action( 'init', fn() => unregister_taxonomy_for_object_type( 'post_tag', 'post' ), 20 );

// Rewrite rules follow the Resources permalink once (and again whenever it changes).
add_action(
	'init',
	function () {
		if ( AW_RESOURCES_PERMALINK !== get_option( 'aw_resources_rewrite' ) ) {
			flush_rewrite_rules( false );
			update_option( 'aw_resources_rewrite', AW_RESOURCES_PERMALINK, true );
		}
	},
	99
);
