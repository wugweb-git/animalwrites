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
 *
 * @return array slug => [ singular, plural, rewrite slug, supports ]
 */
function aw_post_types() {
	return array(
		'aw_news'        => array( 'News item', 'News', 'news', array( 'title', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ) ),
		'aw_legal'       => array( 'Library item', 'Library', 'library', array( 'title', 'editor', 'excerpt', 'custom-fields', 'revisions' ) ),
		'aw_research'    => array( 'Research item (old)', 'Research (old)', 'research', array( 'title', 'excerpt', 'custom-fields', 'revisions' ) ),
		'aw_guide'       => array( 'Resource', 'Resources', 'resources', array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ) ),
		'aw_campaign'    => array( 'Campaign', 'Campaign Hub', 'campaigns', array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ) ),
		'aw_opportunity' => array( 'Opportunity', 'Opportunities', 'opportunities', array( 'title', 'editor', 'excerpt', 'custom-fields', 'revisions' ) ),
		'aw_resource'    => array( 'Template (old)', 'Templates (old)', 'old-resources', array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ) ),
		'aw_listing'     => array( 'Listing', 'Directory', 'directory', array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ) ),
	);
}

/**
 * Register post types and taxonomies.
 */
function aw_register_content_types() {
	foreach ( aw_post_types() as $type => $def ) {
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
				// Research and the old Resources type were folded into Resources and Library (7 Oct 2026); kept for admin only.
				'public'       => ! in_array( $type, array( 'aw_research', 'aw_resource' ), true ),
				'show_ui'      => true,
				'show_in_rest' => true,
				'has_archive'  => in_array( $type, array( 'aw_research', 'aw_resource' ), true ) ? false : $slug,
				'rewrite'      => in_array( $type, array( 'aw_research', 'aw_resource' ), true ) ? false : $rewrite,
				'supports'     => $supports,
				'menu_icon'    => 'dashicons-pets',
			)
		);
	}

	$all = array_keys( aw_post_types() );

	register_taxonomy(
		'aw_topic',
		$all,
		array(
			'labels'            => array( 'name' => 'Topics', 'singular_name' => 'Topic' ),
			'hierarchical'      => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'topic', 'with_front' => false ),
		)
	);

	register_taxonomy(
		'aw_city',
		array( 'aw_listing', 'aw_opportunity', 'aw_campaign', 'aw_news' ),
		array(
			'labels'            => array( 'name' => 'Locations', 'singular_name' => 'Location' ),
			'hierarchical'      => true, // State > City.
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'location', 'with_front' => false, 'hierarchical' => false ),
		)
	);

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

	register_taxonomy(
		'aw_source_type',
		array( 'aw_legal', 'aw_research' ),
		array(
			'labels'            => array( 'name' => 'Source types', 'singular_name' => 'Source type' ),
			'hierarchical'      => true, // e.g. Supreme Court, High Court, AWBI, Gazette.
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'source', 'with_front' => false ),
		)
	);

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
