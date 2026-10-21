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
			if ( is_tag() && '' !== aw_seo_typed( 'facebook_image' ) && ! aw_seo_typed( 'facebook_image_id' ) ) {
				// A Directory type's share card is set as a URL (the JPG kept beside its AVIF; share sites do not read AVIF), which Rank Math does not print for a term.
				$url  = aw_seo_typed( 'facebook_image' );
				$up   = wp_get_upload_dir();
				$base = set_url_scheme( $up['baseurl'], 'https' );
				$size = 0 === strpos( set_url_scheme( $url, 'https' ), $base ) ? wp_getimagesize( $up['basedir'] . substr( set_url_scheme( $url, 'https' ), strlen( $base ) ) ) : false;
				$image->add_image( array_filter( array( 'url' => $url, 'width' => $size ? $size[0] : 0, 'height' => $size ? $size[1] : 0, 'type' => 'image/jpeg', 'alt' => aw_term_share_alt( get_queried_object() ) ) ) );
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

/* og:image and twitter:image are never AVIF (owner, 21 Oct 2026, site-wide): the original upload or a 1200x630 JPEG, with
   its own type and size (aw_seo_share_safe_url). */
foreach ( array( 'facebook/og_image', 'facebook/og_image_secure_url', 'twitter/twitter_image' ) as $aw_seo_tag ) {
	add_filter(
		"rank_math/opengraph/{$aw_seo_tag}",
		function ( $url ) {
			$safe = aw_seo_share_safe_url( (string) $url );
			if ( $safe[0] !== $url ) {
				$GLOBALS['aw_seo_og_swap'] = $safe;
			}
			return $safe[0];
		}
	);
}
unset( $aw_seo_tag );
add_filter( 'rank_math/opengraph/facebook/og_image_type', fn( $type ) => ! empty( $GLOBALS['aw_seo_og_swap'][3] ) ? $GLOBALS['aw_seo_og_swap'][3] : $type );
add_filter( 'rank_math/opengraph/facebook/og_image_width', fn( $w ) => ! empty( $GLOBALS['aw_seo_og_swap'][1] ) ? $GLOBALS['aw_seo_og_swap'][1] : $w );
add_filter( 'rank_math/opengraph/facebook/og_image_height', fn( $h ) => ! empty( $GLOBALS['aw_seo_og_swap'][2] ) ? $GLOBALS['aw_seo_og_swap'][2] : $h );

add_filter( 'rank_math/opengraph/pre_set_default_image', fn( $skip ) => is_404() ? true : $skip );
add_filter( 'rank_math/opengraph/type', fn( $type ) => is_singular( AW_SEO_ARTICLE_TYPES ) ? 'article' : 'website' );
add_filter( 'rank_math/opengraph/facebook/og_locale', fn( $l ) => 'en_IN' );

/** A term's share picture alt text: "<focus keyphrase> in India – Animal Writes" (owner, 20 Oct 2026), else its name. */
function aw_term_share_alt( $term ) {
	$kw = trim( explode( ',', (string) get_term_meta( $term->term_id, 'rank_math_focus_keyword', true ) )[0] );
	return ( '' !== $kw ? $kw : aw_seo_plain( $term->name ) ) . ' in India – Animal Writes';
}
