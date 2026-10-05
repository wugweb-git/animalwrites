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
