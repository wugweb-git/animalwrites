<?php
/**
 * Meta description, Open Graph and Twitter card tags.
 * Skipped automatically if a dedicated SEO plugin (Yoast, Rank Math, SEOPress, AIOSEO) is active.
 *
 * @package animal-writes-core
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
		$descs = array(
			'aw_news'        => 'Animal-welfare news from across India, summarised with a link to every source.',
			'aw_legal'       => 'Court orders, notifications and rules that affect animals in India, explained in plain words.',
			'aw_research'    => 'The laws, rules and research that protect animals in India, from official sources.',
			'aw_guide'       => 'Practical guides: what to do for an injured animal, how to report cruelty, Animal Birth Control and more.',
			'aw_campaign'    => 'Citizen campaigns for animals in India, starting with Animal Writes 1.0.',
			'aw_opportunity' => 'Volunteer, foster, internship and job opportunities in animal welfare in India.',
			'aw_resource'    => 'Letter templates and practical tools for animal welfare action in India.',
			'aw_listing'     => 'Find shelters, vets, ambulances and rescue groups by state and district across India.',
		);
		$desc = $descs[ get_query_var( 'post_type' ) ] ?? '';
	} elseif ( is_tax() || is_category() || is_tag() ) {
		$desc = term_description() ?: single_term_title( '', false ) . ' on Animal Writes.';
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
			$image = (string) get_post_meta( get_queried_object_id(), 'aw_source_image', true );
			$fid   = (int) get_post_meta( get_queried_object_id(), 'aw_file_id', true );
			if ( ! $image && $fid ) {
				$image = (string) wp_get_attachment_image_url( $fid, 'large' );
			}
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
		$type = is_singular( array( 'aw_news', 'aw_legal', 'aw_guide', 'aw_campaign' ) ) ? 'article' : 'website';
		$tags = array(
			'<meta name="description" content="%s">'         => $desc,
			'<meta property="og:site_name" content="%s">'    => get_bloginfo( 'name' ),
			'<meta property="og:type" content="%s">'         => $type,
			'<meta property="og:title" content="%s">'        => $title,
			'<meta property="og:description" content="%s">'  => $desc,
			'<meta property="og:url" content="%s">'          => $url,
			'<meta property="og:image" content="%s">'        => $image,
			'<meta property="og:locale" content="%s">'       => 'en_IN',
			'<meta name="twitter:card" content="%s">'        => $large ? 'summary_large_image' : 'summary',
			'<meta name="twitter:site" content="%s">'        => '@AnimalWrites',
		);
		echo "<!-- Animal Writes SEO -->\n";
		foreach ( $tags as $format => $value ) {
			if ( $value ) {
				printf( $format . "\n", esc_attr( $value ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
		}
	},
	2
);
