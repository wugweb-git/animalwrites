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
 * Resources are WordPress's own Posts (owner, 10 Oct 2026): relabelled, at /resources/{slug}/; their Resource types
 * (Guide, How-to, FAQ, Research) are Tags (tags.php). See aw_resources_post_args() below.
 * Categories are WordPress's own category (owner, 15 Oct 2026), shared by every section, at /category/{slug}/.
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

	// Categories are WordPress's own (owner, 15 Oct 2026): the core category, on every section, at /category/{slug}/.
	foreach ( $all as $type ) {
		register_taxonomy_for_object_type( 'category', $type );
	}

	// Labels (Library, Resource, Opportunity and Listing types, Services) are WordPress's own Tags (tags.php).
	// Places are not a WordPress list: states and districts come from the LGD file (places.php).
	// Issuer is a label on Library items (meta aw_issuer, issuers.php), not a list.
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

/**
 * Categories: WordPress's own list, labelled as on the site, at /category/{slug}/ (not under /resources/, the Posts
 * permalink's front). Listed once, under Classification in WP Admin (admin-ia.php).
 */
add_filter(
	'register_taxonomy_args',
	function ( $args, $tax ) {
		if ( 'category' !== $tax ) {
			return $args;
		}
		$args['labels']            = array_merge( (array) ( $args['labels'] ?? array() ), array( 'name' => 'Categories', 'singular_name' => 'Category', 'menu_name' => 'Categories', 'all_items' => 'All categories', 'edit_item' => 'Edit category', 'add_new_item' => 'Add category', 'search_items' => 'Search categories' ) );
		$args['rewrite']           = array( 'slug' => 'category', 'with_front' => false, 'hierarchical' => true, 'ep_mask' => EP_CATEGORIES );
		$args['show_admin_column'] = true;
		return $args;
	},
	10,
	2
);

/** The sections a category page lists (WordPress lists only Posts on a category page unless told). */
add_action(
	'pre_get_posts',
	function ( $q ) {
		if ( ! is_admin() && $q->is_main_query() && $q->is_category() && ! $q->get( 'post_type' ) ) {
			$q->set( 'post_type', array_keys( aw_post_types() ) );
		}
	}
);

/** No item is filed under a default category: WordPress's "Uncategorized" is not used (owner, 15 Oct 2026). */
add_filter( 'pre_option_default_category', fn() => 0 );

// Resources have no author box or post formats (core registers Posts with them before this plugin loads).
add_action(
	'init',
	function () {
		foreach ( array( 'author', 'trackbacks', 'post-formats' ) as $feature ) {
			remove_post_type_support( 'post', $feature );
		}
	},
	20
);

// Rewrite rules follow the Resources permalink once (and again whenever it changes).
add_action(
	'init',
	function () {
		$want = AW_RESOURCES_PERMALINK . ' tags-2026-10-15'; // and once more when Categories moved to /category/
		if ( $want !== get_option( 'aw_resources_rewrite' ) ) {
			flush_rewrite_rules( false );
			update_option( 'aw_resources_rewrite', $want, true );
		}
	},
	99
);
