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

/** Sections whose missing items get the newest-items list: post type => label. Their addresses come from AW_HUBS. */
const AW_NOT_FOUND_SECTIONS = array(
	'aw_news'        => 'News',
	'aw_legal'       => 'Library',
	'aw_opportunity' => 'Opportunities',
);

/** The section (post type => label) a missing address is under, e.g. array( 'aw_news', 'News' ), or null. */
function aw_not_found_section() {
	$parts = aw_not_found_parts();
	if ( count( $parts ) < 2 ) {
		return null;
	}
	foreach ( AW_NOT_FOUND_SECTIONS as $type => $label ) {
		if ( isset( AW_HUBS[ $type ] ) && AW_HUBS[ $type ][0] === $parts[0] ) {
			return array( $type, $label );
		}
	}
	return null;
}

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
	$item    = null;
	$parts   = aw_not_found_parts();
	$section = aw_not_found_section();
	if ( ! is_404() || 2 !== count( $parts ) || ! $section || ! in_array( $section[0], AW_GONE_TYPES, true ) ) {
		return $item;
	}
	$type = $section[0];
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

/**
 * Words for the search field from the missing address: its last part, with hyphens as spaces. '' for files, WordPress
 * and dot paths (/wp-…, /_…, /.well-known/…) and script leftovers such as /null or /undefined.
 */
function aw_not_found_query() {
	$parts = aw_not_found_parts();
	$last  = (string) end( $parts );
	if ( '' === $last || false !== strpos( $last, '.' ) || in_array( strtolower( $last ), array( 'null', 'undefined', 'false' ), true ) || preg_match( '#^(wp-|_|\.)#', (string) ( $parts[0] ?? '' ) ) ) {
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
		$section = aw_not_found_section();
		$q       = aw_not_found_query();
		$media   = aw_error_picture( '404' );

		$html = '<p class="kit-error-code">' . ( $gone ? '410' : '404' ) . '</p>';
		if ( $gone ) {
			$html .= '<h1 class="wp-block-heading">This item was removed</h1>'
				. '<p class="kit-error-lead">' . ( 'aw_news' === $gone->post_type ? 'We took this story off Animal Writes. Here’s the latest from News instead.' : 'We took this item off Animal Writes. Here’s the latest from the Library instead.' ) . '</p>';
		} else {
			$html .= '<h1 class="wp-block-heading">Page not found</h1><p class="kit-error-lead">This page doesn’t exist or has moved. Search the site, or go to a section.</p>';
		}
		$html .= $media; // after the lead on phones and tablets; from 1200px the photo fills the section behind the text
		$html .= '<form class="kit-error-search" action="' . esc_url( home_url( '/search/' ) ) . '" method="get" role="search"><label class="screen-reader-text" for="kit-404-q">Search</label><span class="kit-error-field"><span class="kit-ico kit-ico--search" aria-hidden="true"></span><input id="kit-404-q" type="search" name="q" value="' . esc_attr( $q ) . '" placeholder="Search" autocomplete="off"></span><button type="submit" class="kit-btn kit-btn--brand kit-btn--label">Search</button></form>';

		if ( $section ) {
			$posts = get_posts( array( 'post_type' => $section[0], 'post_status' => 'publish', 'numberposts' => 3, 'orderby' => 'date', 'order' => 'DESC' ) );
			if ( $posts ) {
				$html .= '<section class="aw-hub-group"><h2>Latest from ' . esc_html( $section[1] ) . '</h2><ul' . ( 'aw_news' === $section[0] ? ' class="aw-hub-news"' : '' ) . '>';
				foreach ( $posts as $p ) {
					$html .= '<li>' . ( 'aw_news' === $p->post_type && function_exists( 'aw_news_meta_row' ) ? aw_news_meta_row( $p->ID ) : '' ) . ( 'aw_legal' === $p->post_type && function_exists( 'aw_legal_kind_tag' ) && ( $k = aw_legal_kind_tag( $p->ID ) ) ? '<p class="kit-tags">' . $k . '</p>' : '' ) . '<a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a></li>';
				}
				$html .= '</ul><p><a href="' . esc_url( aw_hub_url( $section[0] ) ) . '">All ' . esc_html( strtolower( $section[1] ) ) . '</a></p></section>';
			}
		}

		$links = array( '/directory/' => array( 'Directory', 'directory' ), '/news/' => array( 'News', 'news' ), '/resources/' => array( 'Resources', 'knowledge' ), '/library/' => array( 'Library', 'legal' ), '/opportunities/' => array( 'Opportunities', 'opportunities' ), '/contact/' => array( 'Contact', 'mail' ), '/' => array( 'Home', 'home' ) );
		$html .= '<nav class="kit-error-links" aria-label="Sections"><ul>';
		foreach ( $links as $href => $l ) {
			$html .= '<li><a href="' . esc_url( $href ) . '"><span class="kit-ico kit-ico--' . esc_attr( $l[1] ) . '" aria-hidden="true"></span>' . esc_html( $l[0] ) . '</a></li>';
		}
		return $html . '</ul></nav>';
	}
);

/**
 * An error page's photo (owner, 2 Nov 2026: the animals blended into the page, reference 7). Files in assets/error/:
 * {name}-wide.webp (16:9, 2400 × 1350: from 768px it fills the section behind the text, so its left half is a plain
 * wall and the animals sit bottom right) and {name}-phone.webp (4:3, 1200 × 900: on phones, after the lead, animals
 * in the middle). Either file alone is used at every width. '' with neither: the page keeps its one text column.
 * Decorative (empty alt): the words say everything.
 *
 * @param string $name 404, 500 or 503.
 * @return string
 */
function aw_error_picture( $name ) {
	$dir   = AW_CORE_DIR . 'assets/error/';
	$url   = plugins_url( 'assets/error/', AW_CORE_DIR . 'animal-writes-core.php' );
	$wide  = is_readable( $dir . $name . '-wide.webp' ) ? $url . $name . '-wide.webp' : '';
	$phone = is_readable( $dir . $name . '-phone.webp' ) ? $url . $name . '-phone.webp' : '';
	if ( '' === $wide && '' === $phone ) {
		return '';
	}
	$src    = $phone ? $phone : $wide;
	$source = $wide && $phone ? '<source media="(min-width: 768px)" srcset="' . esc_url( $wide ) . '" width="2400" height="1350">' : '';
	return '<figure class="kit-error-media"><picture>' . $source . '<img src="' . esc_url( $src ) . '" width="1200" height="900" alt="" decoding="async" fetchpriority="high"></picture></figure>';
}
