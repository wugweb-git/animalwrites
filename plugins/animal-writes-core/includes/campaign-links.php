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

/**
 * Campaign links by keyword for Media & PR items (owner, 31 Oct 2026: a social post that matches a campaign's keywords
 * shows on that campaign's page). The keywords are the campaign rules in Sourcing (aw_sources['topic_tags'][slug]['any']),
 * the same rules the workers apply at ingest; this also covers items added by hand and by the press search. Checks the
 * title, text, excerpt and link; adds links only, never removes one.
 *
 * @param int $id Media & PR item.
 * @return int[] Term IDs added.
 */
function aw_campaign_links_by_keyword( $id ) {
	$rules = (array) ( ( (array) get_option( 'aw_sources', array() ) )['topic_tags'] ?? array() );
	$hay   = strtolower( get_the_title( $id ) . ' ' . get_post_field( 'post_content', $id ) . ' ' . get_post_field( 'post_excerpt', $id ) . ' ' . get_post_meta( $id, 'aw_press_url', true ) );
	$hits  = array();
	foreach ( $rules as $slug => $rule ) {
		foreach ( (array) ( $rule['any'] ?? array() ) as $k ) {
			$k = strtolower( trim( (string) $k ) );
			if ( '' !== $k && false !== strpos( $hay, $k ) ) {
				$hits[] = (string) $slug;
				break;
			}
		}
	}
	return $hits ? aw_set_campaign_links( $id, $hits ) : array();
}
add_action(
	'save_post_aw_press',
	function ( $id, $post ) {
		if ( wp_is_post_revision( $id ) || 'auto-draft' === $post->post_status ) {
			return;
		}
		aw_campaign_links_by_keyword( $id );
	},
	30, // after press.php's fetch (10) has filled the title
	2
);

/**
 * A line under a News item's summary that links to its campaign (owner, 1 Nov 2026). Only campaigns listed here get a
 * line; the words are the owner's. Keyed by the campaign link's slug: [campaign post ID, text before the link, link words].
 */
const AW_CAMPAIGN_NEWS_LINES = array(
	'campaign-supreme-court-stray-dogs' => array( 23225, 'This is part of the Supreme Court stray dogs case: see what every state has done in ', 'our tracker' ),
);

// The News item's own summary (not a card's): its campaign line after it.
add_filter(
	'render_block_core/post-excerpt',
	function ( $html, $block, $instance ) {
		$id = get_queried_object_id();
		if ( '' === trim( (string) $html ) || ! is_singular( 'aw_news' ) || isset( $instance->context['queryId'] ) || (int) ( $instance->context['postId'] ?? 0 ) !== (int) $id ) {
			return $html;
		}
		foreach ( aw_campaign_links( $id ) as $t ) {
			$line = AW_CAMPAIGN_NEWS_LINES[ $t->slug ] ?? null;
			if ( $line && 'publish' === get_post_status( $line[0] ) ) {
				return $html . '<p>' . esc_html( $line[1] ) . '<a href="' . esc_url( get_permalink( $line[0] ) ) . '">' . esc_html( $line[2] ) . '</a>.</p>';
			}
		}
		return $html;
	},
	20,
	3
);
