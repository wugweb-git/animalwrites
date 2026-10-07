<?php
/**
 * Meta description, Open Graph and Twitter card tags.
 * Skipped automatically if a dedicated SEO plugin (Yoast, Rank Math, SEOPress, AIOSEO) is active.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether another plugin already prints these tags.
 *
 * @return bool
 */
function aw_seo_plugin_active() {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'AIOSEO_VERSION' );
}

/**
 * Description for the current view.
 *
 * @return string
 */
function aw_seo_description() {
	$desc = '';
	if ( is_singular() ) {
		$post = get_queried_object();
		$desc = has_excerpt( $post ) ? $post->post_excerpt : wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
	} elseif ( is_post_type_archive() ) {
		$descs = (array) apply_filters( 'kits_seo_archive_descriptions', array() );
		$desc = $descs[ get_query_var( 'post_type' ) ] ?? '';
	} elseif ( is_tax() || is_category() || is_tag() ) {
		$desc = term_description() ?: single_term_title( '', false ) . ' on ' . get_bloginfo( 'name' ) . '.';
	}
	if ( '' === trim( $desc ) ) {
		$desc = get_bloginfo( 'description' );
	}
	$desc = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $desc ) ) );
	return mb_strlen( $desc ) > 160 ? rtrim( mb_substr( $desc, 0, 157 ) ) . '…' : $desc;
}

add_action(
	'wp_head',
	function () {
		if ( aw_seo_plugin_active() || is_404() ) {
			return;
		}
		$desc  = aw_seo_description();
		$title = wp_get_document_title();
		$url   = is_singular() ? get_permalink() : home_url( add_query_arg( array() ) );
		$image = '';
		if ( is_singular() && has_post_thumbnail() ) {
			$image = get_the_post_thumbnail_url( null, 'large' );
		}
		if ( ! $image && is_singular() ) {
			$image = (string) apply_filters( 'kits_seo_fallback_image', '', get_queried_object_id() );
		}
		$large = (bool) $image;
		if ( ! $image ) {
			$default = (int) get_option( 'aw_default_share_image' );
			$image   = $default ? (string) wp_get_attachment_image_url( $default, 'full' ) : '';
			$large   = (bool) $image;
		}
		if ( ! $image ) {
			$image = get_site_icon_url( 512 );
		}
		$type = is_singular( (array) apply_filters( 'kits_seo_article_types', array( 'post' ) ) ) ? 'article' : 'website';
		$tags = array(
			'<meta name="description" content="%s">'         => $desc,
			'<meta property="og:site_name" content="%s">'    => get_bloginfo( 'name' ),
			'<meta property="og:type" content="%s">'         => $type,
			'<meta property="og:title" content="%s">'        => $title,
			'<meta property="og:description" content="%s">'  => $desc,
			'<meta property="og:url" content="%s">'          => $url,
			'<meta property="og:image" content="%s">'        => $image,
			'<meta property="og:locale" content="%s">'       => (string) apply_filters( 'kits_seo_locale', str_replace( '-', '_', get_bloginfo( 'language' ) ) ),
			'<meta name="twitter:card" content="%s">'        => $large ? 'summary_large_image' : 'summary',
			'<meta name="twitter:site" content="%s">'        => (string) apply_filters( 'kits_seo_twitter_site', '' ),
		);
		echo '<!-- ' . esc_html( get_bloginfo( 'name' ) ) . " SEO -->\n";
		foreach ( $tags as $format => $value ) {
			if ( $value ) {
				printf( $format . "\n", esc_attr( $value ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
		}
	},
	2
);
