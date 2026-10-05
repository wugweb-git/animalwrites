<?php
/**
 * Small front-end display tweaks.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Section pages read "Directory", not "Archives: Directory".
add_filter( 'get_the_archive_title_prefix', '__return_empty_string' );

// Empty sections explain themselves instead of suggesting a search.
add_filter(
	'gettext',
	function ( $translation, $text, $domain ) {
		if ( 'kits-by-wugweb' === $domain && 'Try another search or browse the latest updates.' === $text ) {
			return __( 'New items appear here after review. Know something that belongs here? Write to contact@animalwrites.in.', 'animal-writes-core' );
		}
		return $translation;
	},
	10,
	3
);

// Content is written for India: <html lang="en-IN">.
add_filter(
	'language_attributes',
	function ( $output ) {
		return preg_replace( '/lang="en-US"/', 'lang="en-IN"', $output );
	}
);

/**
 * Cards and single items show when the source published something, not when we added it.
 * Applies to every Post Date block whose post has an aw_source_date.
 */
add_filter(
	'render_block_core/post-date',
	function ( $content, $block, $instance ) {
		$post_id = $instance->context['postId'] ?? get_the_ID();
		$ymd     = $post_id ? (string) get_post_meta( $post_id, 'aw_source_date', true ) : '';
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ymd ) ) {
			return $content;
		}
		$label = function_exists( 'aw_format_date' ) ? aw_format_date( $ymd ) : $ymd;
		$content = preg_replace( '/datetime="[^"]*"/', 'datetime="' . esc_attr( $ymd ) . '"', $content, 1 );
		return preg_replace( '#(<time[^>]*>)(.*?)(</time>)#s', '$1' . esc_html( $label ) . '$3', $content, 1 );
	},
	10,
	3
);

/**
 * External links get a small ↗ after the text, so readers know they are leaving the site.
 * Internal links (same host or root-relative), mailto/tel and image links are left alone.
 */
add_action(
	'wp_head',
	function () {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$sel  = 'a[href^="http"]:not([href*="//' . esc_attr( $host ) . '"]):not(:has(img)):not(.wp-block-button__link)';
		printf(
			'<style id="aw-external-links">%1$s::after{content:"\2197" / "";display:inline-block;margin-left:.2em;font-size:.85em;text-decoration:none}.wp-block-button__link[href^="http"]:not([href*="//%2$s"])::after{content:"\2197" / "";margin-left:.35em}</style>' . "\n",
			$sel, // phpcs:ignore WordPress.Security.EscapeOutput
			esc_attr( $host )
		);
	},
	20
);

/**
 * News, Legal Watch and Research lists are ordered by source date (newest first), then by date added.
 * Items without a source date are kept (named meta clause with NOT EXISTS).
 */
add_action(
	'pre_get_posts',
	function ( $q ) {
		if ( is_admin() ) {
			return;
		}
		$types = (array) $q->get( 'post_type' );
		if ( ! $types || array_diff( $types, array( 'aw_news', 'aw_legal', 'aw_research' ) ) ) {
			return;
		}
		$q->set(
			'meta_query',
			array(
				'relation'    => 'OR',
				'source_date' => array( 'key' => 'aw_source_date', 'compare' => 'EXISTS' ),
				'no_date'     => array( 'key' => 'aw_source_date', 'compare' => 'NOT EXISTS' ),
			)
		);
		$q->set( 'orderby', array( 'source_date' => 'DESC', 'date' => 'DESC' ) );
	}
);

// Homepage: start downloading the hero photo before the body is parsed (largest element on screen).
add_action(
	'wp_head',
	function () {
		if ( ! is_front_page() ) {
			return;
		}
		$post = get_queried_object();
		if ( $post && preg_match( '/<img[^>]+fetchpriority="high"[^>]*>/', (string) $post->post_content, $m ) && preg_match( '/src="([^"]+)"/', $m[0], $src ) ) {
			printf( '<link rel="preload" as="image" href="%s" fetchpriority="high">' . "\n", esc_url( $src[1] ) );
		}
	},
	1
);

// Phones: a slim bar that keeps help one tap away (shown by CSS under 700px only).
add_action(
	'wp_footer',
	function () {
		if ( is_admin() ) {
			return;
		}
		echo '<nav class="aw-sticky-help" aria-label="' . esc_attr__( 'Quick help', 'animal-writes-core' ) . '">'
			. '<a class="aw-sh-primary" href="/directory/">' . esc_html__( 'Find help near you', 'animal-writes-core' ) . '</a>'
			. '<a class="aw-sh-secondary" href="/knowledge/found-an-injured-animal-what-to-do/">' . esc_html__( 'Injured animal?', 'animal-writes-core' ) . '</a>'
			. '</nav>' . "\n";
	}
);

// Empty states sit under the page's h1, so their heading is an h2 (the pattern ships an h3).
add_filter(
	'render_block_core/query-no-results',
	fn( $html ) => preg_replace( array( '/<h3\b/', '/<\/h3>/' ), array( '<h2', '</h2>' ), $html )
);
