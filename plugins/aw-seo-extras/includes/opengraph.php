<?php
/**
 * Open Graph / Twitter details Rank Math's settings cannot express. Rank Math prints the tags; a share image or text
 * chosen in Rank Math always wins.
 *
 * - One share image (helpers.php): the item's own image when landscape: for News, Library, research and Opportunities
 *   the publisher's image, linked; else the featured image (a listing's logo), a Library item's hosted PDF cover, a
 *   Page's own band photo. Category pages: the category photo, stored in Rank Math's own share-image fields
 *   (programmatic.php), which Rank Math prints. Tags and everything else: the site share image.
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
			if ( aw_seo_is_category_page() && get_term_meta( get_queried_object_id(), 'rank_math_facebook_image_id', true ) ) {
				return; // the category photo, stored in Rank Math's fields: Rank Math prints it
			}
			$img = aw_seo_image();
			if ( $img ) {
				$alt = trim( (string) get_post_meta( (int) ( $img['id'] ?? 0 ), '_wp_attachment_image_alt', true ) ); // the picture's own alt text
				if ( '' === $alt ) {
					$obj = get_queried_object();
					$alt = $obj instanceof WP_Post ? aw_seo_plain( $obj->post_title ) : ( $obj instanceof WP_Term ? aw_seo_plain( $obj->name ) : get_bloginfo( 'name' ) );
				}
				$image->add_image( array_filter( array( 'url' => $img['url'], 'width' => $img['width'], 'height' => $img['height'], 'type' => (string) ( $img['type'] ?? get_post_mime_type( (int) ( $img['id'] ?? 0 ) ) ), 'alt' => $alt ) ) );
			}
		}
	);
}
unset( $aw_seo_net );

/* On a campaign page, a share image typed in Rank Math that is AVIF goes out as its WebP copy too, with its type. */
foreach ( array( 'facebook/og_image', 'facebook/og_image_secure_url', 'twitter/twitter_image' ) as $aw_seo_tag ) {
	add_filter(
		"rank_math/opengraph/{$aw_seo_tag}",
		function ( $url ) {
			$safe = aw_seo_share_safe_url( (string) $url )[0];
			if ( $safe !== $url ) {
				$GLOBALS['aw_seo_og_webp'] = true;
			}
			return $safe;
		}
	);
}
unset( $aw_seo_tag );
add_filter( 'rank_math/opengraph/facebook/og_image_type', fn( $type ) => ! empty( $GLOBALS['aw_seo_og_webp'] ) || 'image/avif' === $type ? 'image/webp' : $type );

add_filter( 'rank_math/opengraph/pre_set_default_image', fn( $skip ) => is_404() ? true : $skip );
add_filter( 'rank_math/opengraph/type', fn( $type ) => is_singular( AW_SEO_ARTICLE_TYPES ) ? 'article' : 'website' );
add_filter( 'rank_math/opengraph/facebook/og_locale', fn( $l ) => 'en_IN' );
