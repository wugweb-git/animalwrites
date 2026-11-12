<?php
/**
 * Acts and Rules: crawlable links to every Act and Rule in the Library (indexing work, owner 12 Nov 2026).
 *
 * Search Console found Library Acts and Rules with no page linking to them ("Referring page: None detected" for the
 * Wildlife Protection Act 1972 and the Fines Rules 1978): the Library hub lists items newest first, so an Act added
 * early sits pages deep. This file adds plain links, no new styles:
 *
 * - "Acts and Rules" on the Library hub (every page of /library/) and on a category page that has Acts or Rules
 *   (/category/laws-rules/ has them all), A–Z, as a link list (`wp-block-list kit-links`, kit.css).
 * - Guides ↔ Acts and Rules both ways: a guide that names an Act or Rule in its text (AW_LAW_NAMES) shows it in
 *   "From the Library", after the ones linked by hand (aw_rel_resource); the Act or Rule shows that guide in
 *   "Guides on this", after the ones linked by hand (item-head.php, aw_item_more_groups()).
 *
 * An Act or Rule is a published Library item tagged `act` or `rule`. The guide map is cached for 12 hours and worked
 * out again when a guide or a Library item is saved.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * How a guide names each Act or Rule (Library slug => regular expression, case-insensitive). The names are the short
 * titles people write; the year is left out so "the Pet Shop Rules" matches too.
 */
const AW_LAW_NAMES = array(
	'wildlife-protection-act-1972'                                       => 'wild\s*life\s*\(?\s*protection\s*\)?\s*act|\bWLPA\b',
	'the-prevention-of-cruelty-to-animals-act-1960-as-amended-in-1982'   => 'prevention\s+of\s+cruelty\s+to\s+animals\s+act|\bPCA\s+Act\b',
	'the-prevention-of-cruelty-to-animals-application-of-fines-rules-1978' => 'application\s+of\s+fines\s*\)?\s*rules|\bfines\s+rules\b',
	'animal-birth-control-rules-2023'                                    => 'animal\s+birth\s+control\s*(\(\s*dogs\s*\)\s*)?rules|\bABC\s+rules\b',
	'the-prevention-of-cruelty-to-animals-pet-shop-rules-2018'           => 'pet\s+shop\s*\)?\s*rules',
	'the-prevention-of-cruelty-to-animals-dog-breeding-and-marketing-rules-2017' => 'dog\s+breeding\s+and\s+marketing\s*\)?\s*rules',
	'the-transport-of-animals-rules-1978'                                => 'transport\s+of\s+animals\s*,?\s*rules',
	'prevention-of-cruelty-to-animals-transport-of-animals-on-foot-rules-2001' => 'transport\s+of\s+animals\s+on\s+foot\s*\)?\s*rules',
	'prevention-of-cruelty-to-animals-slaughter-house-rules-2001'        => 'slaughter\s+house\s*\)?\s*rules',
	'performing-animals-registration-rules-2001'                         => 'performing\s+animals\s*\(?\s*registration\s*\)?\s*rules',
	'draft-laboratory-animals-rules-2026'                                => 'laboratory\s+animals\s*\)?\s*rules|draft\s+(lab|laboratory)\s+(animal\s+)?rules',
	'breeding-experiments-animals-rules-1998'                            => 'breeding\s+of\s+and\s+experiments\s+on\s+animals',
	'spca-rules-2001'                                                    => '\bSPCA\s+rules\b|societies\s+for\s+prevention\s+of\s+cruelty\s+to\s+animals\s*\)?\s*rules',
	'new-drugs-clinical-trials-amendment-rules-2023-non-animal-methods' => 'new\s+drugs\s+and\s+clinical\s+trials',
);

/** Heading of the list (owner's words in the 12 Nov 2026 brief). */
function aw_law_index_head() {
	return __( 'Acts and Rules', 'animal-writes-core' );
}

/**
 * Every published Act and Rule, A–Z by title without a leading "The" (cached per request).
 *
 * @return WP_Post[]
 */
function aw_law_items() {
	static $items = null;
	if ( null !== $items ) {
		return $items;
	}
	$items = get_posts(
		array(
			'post_type'        => 'aw_legal',
			'post_status'      => 'publish',
			'numberposts'      => 100,
			'tag_slug__in'     => array( 'act', 'rule' ),
			'orderby'          => 'title',
			'order'            => 'ASC',
			'suppress_filters' => true, // the Library's own sorting (aw-news-query-resort and the hubs) does not apply here
		)
	);
	$key = fn( $p ) => mb_strtolower( preg_replace( '/^the\s+/i', '', html_entity_decode( $p->post_title, ENT_QUOTES ) ) );
	usort( $items, fn( $a, $b ) => strcmp( $key( $a ), $key( $b ) ) );
	return $items;
}

/**
 * Guides and the Acts and Rules they name: guide ID => Library IDs. Cached for 12 hours.
 *
 * @param bool $fresh Work it out again within this request (after a save).
 * @return array<int,int[]>
 */
function aw_law_mentions( $fresh = false ) {
	static $map = null;
	if ( null !== $map && ! $fresh ) {
		return $map;
	}
	$map = get_transient( 'aw_law_mentions' );
	if ( is_array( $map ) ) {
		return $map;
	}
	$laws = array();
	foreach ( aw_law_items() as $p ) {
		if ( isset( AW_LAW_NAMES[ $p->post_name ] ) ) {
			$laws[ (int) $p->ID ] = '/' . AW_LAW_NAMES[ $p->post_name ] . '/iu';
		}
	}
	$map = array();
	if ( $laws ) {
		$guides = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => -1, 'suppress_filters' => true ) );
		foreach ( $guides as $g ) {
			$text = html_entity_decode( wp_strip_all_tags( $g->post_title . ' ' . $g->post_excerpt . ' ' . $g->post_content ), ENT_QUOTES );
			$text = str_replace( "\u{00a0}", ' ', $text );
			foreach ( $laws as $lid => $re ) {
				if ( preg_match( $re, $text ) ) {
					$map[ (int) $g->ID ][] = $lid;
				}
			}
		}
	}
	set_transient( 'aw_law_mentions', $map, 12 * HOUR_IN_SECONDS );
	return $map;
}

// A guide or a Library item saved: the guide map is worked out again, and the More groups ("From the Library",
// "Guides on this") of every guide and Act or Rule in the old or the new map are cleared, so a new mention links at once.
add_action(
	'save_post',
	function ( $id, $post ) {
		if ( wp_is_post_revision( $id ) || ! in_array( $post->post_type, array( 'post', 'aw_legal' ), true ) ) {
			return;
		}
		$old = get_transient( 'aw_law_mentions' );
		delete_transient( 'aw_law_mentions' );
		$new = aw_law_mentions( true );
		$ids = array( (int) $id );
		foreach ( array( is_array( $old ) ? $old : array(), $new ) as $map ) {
			foreach ( $map as $gid => $laws ) {
				$ids[] = (int) $gid;
				$ids   = array_merge( $ids, array_map( 'intval', (array) $laws ) );
			}
		}
		foreach ( array_unique( $ids ) as $one ) {
			delete_transient( 'aw_more_' . $one );
		}
	},
	20,
	2
);

/**
 * The Acts and Rules a guide names (in the order of the A–Z list).
 *
 * @param int $id Guide.
 * @return int[]
 */
function aw_law_named_in( $id ) {
	return array_map( 'intval', aw_law_mentions()[ (int) $id ] ?? array() );
}

/**
 * The guides that name an Act or Rule, newest first.
 *
 * @param int $id Library item.
 * @return int[]
 */
function aw_law_guides_naming( $id ) {
	$ids = array();
	foreach ( aw_law_mentions() as $gid => $laws ) {
		if ( in_array( (int) $id, array_map( 'intval', $laws ), true ) ) {
			$ids[] = (int) $gid;
		}
	}
	if ( ! $ids ) {
		return array();
	}
	return array_map( 'intval', get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'post__in' => $ids, 'orderby' => 'date', 'order' => 'DESC', 'numberposts' => count( $ids ), 'fields' => 'ids', 'suppress_filters' => true ) ) );
}

/**
 * The "Acts and Rules" section: a link list, A–Z.
 *
 * @param int $cat Only the Acts and Rules in this category (0 = all).
 * @return string HTML ('' when there are none).
 */
function aw_law_index_html( $cat = 0 ) {
	$items = aw_law_items();
	if ( $cat ) {
		$items = array_values( array_filter( $items, fn( $p ) => has_term( (int) $cat, 'category', $p ) ) );
	}
	if ( ! $items ) {
		return '';
	}
	$li = '';
	foreach ( $items as $p ) {
		$li .= '<li><a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( html_entity_decode( get_the_title( $p ), ENT_QUOTES ) ) . '</a></li>';
	}
	aw_use_component_style( 'aw-single' ); // aw-more-group, aw-more-head
	return '<section class="aw-more-group aw-law-index" aria-labelledby="aw-law-index-h"><h2 class="aw-more-head has-x-large-font-size" id="aw-law-index-h">' . esc_html( aw_law_index_head() ) . '</h2><ul class="wp-block-list kit-links">' . $li . '</ul></section>';
}

// The Library hub: "Acts and Rules" after its list, on every page of /library/ (the list is its Query Loop, hubs.php).
add_filter(
	'render_block_core/query',
	function ( $html, $block ) {
		$qid = (int) ( $block['attrs']['queryId'] ?? 0 );
		if ( ! defined( 'AW_HUBS' ) || AW_HUBS['aw_legal'][1] !== $qid || 'aw_legal' !== ( $block['attrs']['query']['postType'] ?? '' ) || is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $html;
		}
		return $html . aw_law_index_html();
	},
	20,
	2
);
