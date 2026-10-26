<?php
/**
 * The page for a missing address (owner, 26 Oct 2026).
 *
 * - A News or Library item that went to the trash answers 410 Gone, not 404, so search engines drop the address sooner.
 *   It says so ("This item was removed") and lists the newest items of that section. A draft, a private item or an
 *   address that never existed stays 404. Nothing redirects: a missing address is never sent to a guessed item
 *   (aw-seo-extras redirects.php).
 * - Every 404 fills the search field with the words of the missing address (/news/stray-dog-protest/ → "stray dog
 *   protest") and, under /news/, /library/ or /opportunities/, lists that section's three newest items.
 *
 * [aw_not_found] in the theme's 404 template (themes/animal-writes/templates/404.html) prints the page.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Sections whose missing items get the newest-items list: first part of the address => post type, label. */
const AW_NOT_FOUND_SECTIONS = array(
	'news'          => array( 'aw_news', 'News' ),
	'library'       => array( 'aw_legal', 'Library' ),
	'opportunities' => array( 'aw_opportunity', 'Opportunities' ),
);

/** Post types whose trashed items answer 410. */
const AW_GONE_TYPES = array( 'aw_news', 'aw_legal' );

/** The missing address as its parts, e.g. array( 'news', 'some-story' ). */
function aw_not_found_parts() {
	$path = (string) wp_parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	return array_values( array_filter( explode( '/', rawurldecode( $path ) ), 'strlen' ) );
}

/**
 * The trashed News or Library item at the missing address, or null. WordPress keeps the slug a trashed item had in
 * _wp_desired_post_slug (its own slug becomes {slug}__trashed).
 *
 * @return WP_Post|null
 */
function aw_removed_item() {
	static $item = false;
	if ( false !== $item ) {
		return $item;
	}
	$item  = null;
	$parts = aw_not_found_parts();
	if ( ! is_404() || 2 !== count( $parts ) || ! isset( AW_NOT_FOUND_SECTIONS[ $parts[0] ] ) ) {
		return $item;
	}
	$type = AW_NOT_FOUND_SECTIONS[ $parts[0] ][0];
	if ( ! in_array( $type, AW_GONE_TYPES, true ) ) {
		return $item;
	}
	global $wpdb;
	$slug = sanitize_title( $parts[1] );
	$id   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_wp_desired_post_slug' WHERE p.post_status = 'trash' AND p.post_type = %s AND m.meta_value = %s LIMIT 1", $type, $slug ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	if ( $id ) {
		$item = get_post( $id );
	}
	return $item;
}

// 410 for a trashed item. Late, so a redirect in Rank Math's list for the same address still wins.
add_action(
	'template_redirect',
	function () {
		if ( aw_removed_item() ) {
			status_header( 410 );
		}
	},
	99
);

/** Words for the search field from the missing address: its last part, with hyphens as spaces; '' for file paths. */
function aw_not_found_query() {
	$parts = aw_not_found_parts();
	$last  = (string) end( $parts );
	if ( '' === $last || false !== strpos( $last, '.' ) || preg_match( '#^(wp-|_)#', (string) ( $parts[0] ?? '' ) ) ) {
		return '';
	}
	$words = preg_split( '/[-_\s]+/u', preg_replace( '/__trashed$/', '', $last ) );
	$words = array_filter( $words, fn( $w ) => '' !== $w && ! is_numeric( $w ) );
	return sanitize_text_field( implode( ' ', array_slice( $words, 0, 8 ) ) );
}

add_shortcode(
	'aw_not_found',
	function () {
		$gone    = aw_removed_item();
		$parts   = aw_not_found_parts();
		$section = isset( $parts[0], AW_NOT_FOUND_SECTIONS[ $parts[0] ] ) && count( $parts ) > 1 ? AW_NOT_FOUND_SECTIONS[ $parts[0] ] : null;
		$q       = aw_not_found_query();

		$html = '<p class="kit-error-code">' . ( $gone ? '410' : '404' ) . '</p>';
		if ( $gone ) {
			$html .= '<h1 class="wp-block-heading">This item was removed</h1>'
				. '<p class="kit-error-lead">' . ( 'aw_news' === $gone->post_type ? 'We took this story off Animal Writes. Here’s the latest from News instead.' : 'We took this item off Animal Writes. Here’s the latest from the Library instead.' ) . '</p>';
		} else {
			$html .= '<h1 class="wp-block-heading">Page not found</h1><p class="kit-error-lead">This page doesn’t exist or has moved. Search the site, or go to a section.</p>';
		}
		$html .= '<form class="kit-error-search" action="' . esc_url( home_url( '/search/' ) ) . '" method="get" role="search"><label class="screen-reader-text" for="kit-404-q">Search</label><input id="kit-404-q" type="search" name="q" value="' . esc_attr( $q ) . '" placeholder="Search" autocomplete="off"><button type="submit" class="kit-btn"><span class="kit-ico kit-ico--search" aria-hidden="true"></span>Search</button></form>';

		if ( $section ) {
			$posts = get_posts( array( 'post_type' => $section[0], 'post_status' => 'publish', 'numberposts' => 3, 'orderby' => 'date', 'order' => 'DESC', 'no_found_rows' => true ) );
			if ( $posts ) {
				$html .= '<section class="aw-hub-group"><h2>Latest from ' . esc_html( $section[1] ) . '</h2><ul' . ( 'aw_news' === $section[0] ? ' class="aw-hub-news"' : '' ) . '>';
				foreach ( $posts as $p ) {
					$html .= '<li>' . ( 'aw_news' === $p->post_type && function_exists( 'aw_news_meta_row' ) ? aw_news_meta_row( $p->ID ) : '' ) . ( 'aw_legal' === $p->post_type && function_exists( 'aw_legal_kind_tag' ) && ( $k = aw_legal_kind_tag( $p->ID ) ) ? '<p class="kit-tags">' . $k . '</p>' : '' ) . '<a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a></li>';
				}
				$html .= '</ul><p><a href="' . esc_url( aw_hub_url( $section[0] ) ) . '">All ' . esc_html( strtolower( $section[1] ) ) . '</a></p></section>';
			}
		}

		return $html . '<nav class="kit-error-links" aria-label="Sections"><ul><li><a href="/directory/">Directory</a></li><li><a href="/news/">News</a></li><li><a href="/resources/">Resources</a></li><li><a href="/library/">Library</a></li><li><a href="/opportunities/">Opportunities</a></li><li><a href="/contact/">Contact</a></li><li><a href="/">Home</a></li></ul></nav>';
	}
);
