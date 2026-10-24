<?php
/**
 * "Also reported by": publisher logos in an item's source row (meta aw_also_reported, written by the same-story fold
 * in aw-sourcing). Kept in core because it is part of the item template.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SAME_STORY_TYPES = array( 'aw_news', 'aw_legal' );

/**
 * "Also reported by" as publisher logos in the item's source row, after the main publisher (owner, 15 Oct 2026):
 * the same logo tiles as the source row, each linked to that publisher's report, with an accessible name.
 *
 * @param int $id Post ID.
 * @return string
 */
function aw_also_logos_html( $id ) {
	$out  = '';
	$seen = array();
	foreach ( array_filter( (array) get_post_meta( $id, 'aw_also_reported', true ) ) as $a ) {
		$url = (string) ( $a['url'] ?? '' );
		if ( '' === $url || isset( $seen[ $url ] ) ) {
			continue;
		}
		$seen[ $url ] = true;
		$host         = function_exists( 'aw_host' ) ? aw_host( $url ) : (string) wp_parse_url( $url, PHP_URL_HOST );
		$name         = '' !== (string) ( $a['name'] ?? '' ) ? (string) $a['name'] : $host;
		$icon         = $host && function_exists( 'aw_favicon_url' ) ? aw_favicon_url( $host ) : '';
		$mark         = $icon
			? '<img class="aw-src-icon" src="' . esc_url( $icon ) . '" alt="" width="16" height="16" loading="lazy" decoding="async">'
			: '<span class="aw-src-icon aw-src-icon--letter" aria-hidden="true">' . esc_html( mb_strtoupper( mb_substr( $name, 0, 1 ) ) ) . '</span>';
		$label        = sprintf( 'Also reported by %s (opens in a new tab)', $name );
		$out         .= '<li><a class="aw-src-also-link" href="' . esc_url( $url ) . '" target="_blank" rel="noopener nofollow" aria-label="' . esc_attr( $label ) . '" title="' . esc_attr( 'Also reported by ' . $name ) . '">' . $mark . '</a></li>';
	}
	return $out ? '<ul class="aw-src-also" aria-label="Also reported by">' . $out . '</ul>' : '';
}

// The item page's source row ([aw_source_row link="1"]): the logos go inside the row, after the main publisher.
add_filter(
	'do_shortcode_tag',
	function ( $html, $tag, $attr ) {
		if ( 'aw_source_row' !== $tag || '' === $html || '1' !== (string) ( $attr['link'] ?? '' ) || ! is_singular( AW_SAME_STORY_TYPES ) || get_the_ID() !== get_queried_object_id() ) {
			return $html;
		}
		$logos = aw_also_logos_html( get_queried_object_id() );
		if ( '' === $logos ) {
			return $html;
		}
		$open = '<p class="aw-src aw-src--link">';
		$at   = strrpos( $html, '</p>' );
		if ( 0 !== strpos( $html, $open ) || false === $at ) {
			return $html;
		}
		return '<div class="aw-src aw-src--link aw-src--also">' . substr( $html, strlen( $open ), $at - strlen( $open ) ) . $logos . '</div>';
	},
	10,
	3
);
