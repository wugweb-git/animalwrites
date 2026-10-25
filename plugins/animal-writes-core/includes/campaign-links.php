<?php
/**
 * Campaign link (owner, 20 Oct 2026: a campaign "shouldn't be a tag"). Which campaign a news item, Library document,
 * Resource, press item or opportunity belongs to. A private taxonomy: no public pages, no chips on cards, never in the
 * sitemap. Its terms keep the old tag slugs (campaign-lab-animals-2026, campaign-india-writes-community-dogs), so the
 * workers' topic rules (sources.yaml topic_tags) still name them; ingest.php and press.php file those slugs here.
 * Each term's meta aw_campaign_page holds its campaign's post ID; the old tag addresses (/tag/campaign-...) 301 to it.
 *
 * Used by: the Media & PR Campaign filter (systems.php), related items (related.php), the campaign record schema
 * (aw-seo-extras schema.php), campaign pages' query lists ("taxQuery":{"aw_campaign_link":[id]}, systems.php restores
 * them), and the stock-photo rule (cc0-images.php).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_CAMPAIGN_LINK       = 'aw_campaign_link';
const AW_CAMPAIGN_LINK_TYPES = array( 'aw_news', 'aw_legal', 'post', 'aw_press', 'aw_opportunity', 'aw_campaign' );

add_action(
	'init',
	function () {
		register_taxonomy(
			AW_CAMPAIGN_LINK,
			AW_CAMPAIGN_LINK_TYPES,
			array(
				'labels'             => array(
					'name'          => 'Linked campaigns', // owner, 25 Oct 2026: not "Campaigns", which is the Campaigns menu
					'singular_name' => 'Linked campaign',
					'menu_name'     => 'Linked campaigns',
					'all_items'     => 'All linked campaigns',
					'edit_item'     => 'Edit linked campaign',
					'add_new_item'  => 'Add linked campaign',
					'search_items'  => 'Search linked campaigns',
					'not_found'     => 'No linked campaigns',
				),
				'description'        => 'Which campaign an item belongs to. Not a tag: no public page.',
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_admin_column'  => true,
				'show_in_rest'       => true, // campaign pages' query lists filter by it
				'show_in_nav_menus'  => false,
				'show_tagcloud'      => false,
				'hierarchical'       => false,
				'rewrite'            => false,
				'query_var'          => false,
			)
		);
	},
	5
);

/**
 * The campaigns an item belongs to.
 *
 * @param int $id Post ID.
 * @return WP_Term[]
 */
function aw_campaign_links( $id ) {
	$t = get_the_terms( $id, AW_CAMPAIGN_LINK );
	return $t && ! is_wp_error( $t ) ? $t : array();
}

/**
 * Add campaign links by slug (only existing campaign-* terms; never replaces the item's other links).
 *
 * @param int      $id    Post ID.
 * @param string[] $slugs Slugs such as campaign-lab-animals-2026.
 * @return int[] Term IDs added.
 */
function aw_set_campaign_links( $id, $slugs ) {
	$ids = array();
	foreach ( array_filter( (array) $slugs, 'is_scalar' ) as $s ) {
		$s = sanitize_title( (string) $s );
		if ( 0 !== strpos( $s, 'campaign-' ) ) {
			continue;
		}
		$t = get_term_by( 'slug', $s, AW_CAMPAIGN_LINK );
		if ( $t ) {
			$ids[] = (int) $t->term_id;
		}
	}
	if ( $ids ) {
		wp_set_object_terms( $id, $ids, AW_CAMPAIGN_LINK, true );
	}
	return $ids;
}
