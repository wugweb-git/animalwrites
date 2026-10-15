<?php
/**
 * Open Graph / Twitter details Rank Math's settings cannot express. Rank Math prints the tags; a share image or text
 * chosen in Rank Math always wins.
 *
 * - One share image: the item's own stored image when landscape (featured image, topic photo, hosted PDF cover), else
 *   the site share image, with its size (helpers.php). Never the publisher's remote image.
 * - og:type "article" only for news, Library, Resources and campaigns; "website" elsewhere.
 * - og:locale en_IN.
 * - og:image:alt: the picture's own alt text, else the page's title (never the focus keyword, which Rank Math would use).
 * - A missing page shares no image.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Post types shared as articles. */
const AW_SEO_ARTICLE_TYPES = array( 'aw_news', 'aw_legal', 'post', 'aw_campaign' );

foreach ( array( 'facebook', 'twitter' ) as $aw_seo_net ) {
	add_action(
		"rank_math/opengraph/{$aw_seo_net}/add_images",
		function ( $image ) {
			if ( is_404() || ! is_object( $image ) || ! method_exists( $image, 'add_image' ) ) {
				return;
			}
			if ( aw_seo_typed( 'facebook_image_id' ) || aw_seo_typed( 'facebook_image' ) || aw_seo_typed( 'twitter_image_id' ) ) {
				return;
			}
			$img = aw_seo_image();
			if ( $img ) {
				$alt = trim( (string) get_post_meta( (int) ( $img['id'] ?? 0 ), '_wp_attachment_image_alt', true ) ); // the picture's own alt text
				if ( '' === $alt ) {
					$obj = get_queried_object();
					$alt = $obj instanceof WP_Post ? aw_seo_plain( $obj->post_title ) : ( $obj instanceof WP_Term ? aw_seo_plain( $obj->name ) : get_bloginfo( 'name' ) );
				}
				$image->add_image( array_filter( array( 'url' => $img['url'], 'width' => $img['width'], 'height' => $img['height'], 'type' => (string) get_post_mime_type( (int) ( $img['id'] ?? 0 ) ), 'alt' => $alt ) ) );
			}
		}
	);
}
unset( $aw_seo_net );

add_filter( 'rank_math/opengraph/pre_set_default_image', fn( $skip ) => is_404() ? true : $skip );
add_filter( 'rank_math/opengraph/type', fn( $type ) => is_singular( AW_SEO_ARTICLE_TYPES ) ? 'article' : 'website' );
add_filter( 'rank_math/opengraph/facebook/og_locale', fn( $l ) => 'en_IN' );
