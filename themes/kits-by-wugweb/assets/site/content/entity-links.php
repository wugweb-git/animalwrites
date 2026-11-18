<?php
/**
 * Names linked in a summary (SEO Phase 7, internal linking). Summaries stay plain text in the database; the entities a
 * summary names are stored on the item (aw_entities, summary-rules.php: laws and organisations with their page ID,
 * places with their LGD codes).
 *
 * On the item's own page only (never on cards or lists), the first mention of up to AW_ENTITY_LINKS_MAX entities is
 * linked to its page: a law to its Library item, an organisation to its listing, a place to its Directory page.
 * - Only the entity's canonical name, exactly as stored (case included), whole words; other spellings are not linked.
 * - Only to a published page: a law or organisation whose item is published, a place with listings.
 * - Never to the page itself.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Most names linked in one summary. */
const AW_ENTITY_LINKS_MAX = 4;

/**
 * The pages an item's stored entities point to: list of [canonical name, URL, kind (law, organisation, place)]. Entities without a published page are
 * left out, and so is the item's own page.
 *
 * @param int $id Item.
 * @return array
 */
function aw_entity_targets( $id ) {
	$e    = json_decode( (string) get_post_meta( $id, 'aw_entities', true ), true );
	$self = untrailingslashit( (string) get_permalink( $id ) );
	$out  = array();
	if ( ! is_array( $e ) ) {
		return $out;
	}
	foreach ( array( 'laws', 'organisations' ) as $group ) {
		foreach ( (array) ( $e[ $group ] ?? array() ) as $x ) {
			$pid = (int) ( $x['id'] ?? 0 );
			if ( $pid && $pid !== (int) $id && 'publish' === get_post_status( $pid ) && ! in_array( $pid, function_exists( 'kits_preview_only_ids' ) ? kits_preview_only_ids() : array(), true ) ) {
				$out[] = array( (string) ( $x['name'] ?? '' ), (string) get_permalink( $pid ), 'laws' === $group ? 'law' : 'organisation' );
			}
		}
	}
	foreach ( (array) ( $e['places'] ?? array() ) as $x ) {
		$p = ! empty( $x['district'] ) ? aw_place_district( (int) $x['district'] ) : aw_place_state( (int) ( $x['state'] ?? 0 ) );
		if ( $p && aw_place_count( $p ) > 0 ) { // a Directory page with listings
			$out[] = array( (string) ( $x['name'] ?? '' ), aw_directory_url( $p->slug ), 'place' );
		}
	}
	return array_values( array_filter( $out, fn( $t ) => '' !== trim( $t[0] ) && untrailingslashit( $t[1] ) !== $self ) );
}

/**
 * Link the first mention of each target in a summary's text (escaped HTML without tags), in reading order, at most
 * AW_ENTITY_LINKS_MAX. A longer name wins over a shorter one at the same place.
 *
 * @param string $text    Escaped text.
 * @param array  $targets [name, URL, kind] (kind: law, organisation or place; data-entity, for analytics).
 * @return string
 */
function aw_entity_link_text( $text, $targets ) {
	$hits = array();
	foreach ( $targets as $t ) {
		$name = esc_html( $t[0] );
		if ( preg_match( '/(?<![\p{L}\p{N}])' . preg_quote( $name, '/' ) . '(?![\p{L}\p{N}])/u', $text, $m, PREG_OFFSET_CAPTURE ) ) {
			$hits[] = array( $m[0][1], strlen( $m[0][0] ), $t[1], (string) ( $t[2] ?? '' ) );
		}
	}
	usort( $hits, fn( $a, $b ) => ( $a[0] <=> $b[0] ) ?: ( $b[1] <=> $a[1] ) );
	$keep = array();
	$end  = -1;
	$urls = array();
	foreach ( $hits as $h ) {
		if ( $h[0] < $end || isset( $urls[ $h[2] ] ) ) {
			continue; // inside a name already linked, or a page already linked
		}
		$keep[]          = $h;
		$end             = $h[0] + $h[1];
		$urls[ $h[2] ]   = true;
		if ( count( $keep ) >= AW_ENTITY_LINKS_MAX ) {
			break;
		}
	}
	foreach ( array_reverse( $keep ) as $h ) {
		$text = substr( $text, 0, $h[0] ) . '<a href="' . esc_url( $h[2] ) . '"' . ( '' !== $h[3] ? ' data-entity="' . esc_attr( $h[3] ) . '"' : '' ) . '>' . substr( $text, $h[0], $h[1] ) . '</a>' . substr( $text, $h[0] + $h[1] );
	}
	return $text;
}

// The item's own summary (the page's post-excerpt block, not a card's): its named entities linked.
add_filter(
	'render_block_core/post-excerpt',
	function ( $html, $block, $instance ) {
		$id = get_queried_object_id();
		if ( ! is_singular( array( 'aw_news', 'aw_legal', 'post' ) ) || isset( $instance->context['queryId'] ) || (int) ( $instance->context['postId'] ?? 0 ) !== (int) $id ) {
			return $html;
		}
		$targets = aw_entity_targets( $id );
		if ( ! $targets ) {
			return $html;
		}
		return preg_replace_callback(
			'#(<p class="wp-block-post-excerpt__excerpt">)(.*?)(</p>)#s',
			fn( $m ) => false !== strpos( $m[2], '<' ) ? $m[0] : $m[1] . aw_entity_link_text( $m[2], $targets ) . $m[3], // plain text only
			$html,
			1
		);
	},
	5,
	3
);
