<?php
/**
 * Rank Math SEO bridge (owner, 10 Oct 2026: Rank Math is the one SEO system).
 *
 * When Rank Math is active it prints the <title>, meta description, canonical, robots, Open Graph / Twitter tags,
 * the JSON-LD graph, the sitemap, redirects and verification codes; this kit's own printers (seo.php) stay quiet.
 * The site's own rules are fed into Rank Math's filters, and only where the editor left Rank Math's field empty:
 * a title, description, canonical or share image typed in Rank Math always wins.
 * Without Rank Math (a fresh install, the deploy check), seo.php prints as before.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Whether Rank Math is active (checked at run time: it loads after this plugin). */
function kits_rank_math() {
	return defined( 'RANK_MATH_VERSION' ) && class_exists( '\RankMath\Helper' );
}

/** Whether a Rank Math module is on. */
function kits_rank_math_module( $id ) {
	return kits_rank_math() && \RankMath\Helper::is_module_active( $id );
}

/**
 * What the editor typed in Rank Math for the current view: post or term meta "rank_math_{$key}" ('' when empty).
 *
 * @param string $key Field, e.g. title, description, canonical_url, facebook_image_id.
 * @return string
 */
function kits_rank_math_typed( $key ) {
	$id = get_queried_object_id();
	if ( ! $id ) {
		return '';
	}
	if ( is_singular() ) {
		return trim( (string) get_post_meta( $id, 'rank_math_' . $key, true ) );
	}
	if ( is_category() || is_tag() || is_tax() ) {
		return trim( (string) get_term_meta( $id, 'rank_math_' . $key, true ) );
	}
	return '';
}

/* ---------- Title ---------- */

/** True while the site's own title is being built (Rank Math's title filter then steps aside). */
function kits_rank_math_building_title( $set = null ) {
	static $busy = false;
	if ( null !== $set ) {
		$busy = (bool) $set;
	}
	return $busy;
}

// While building the site's own title, the document title comes from its parts (document_title_parts), as without Rank Math.
add_filter( 'pre_get_document_title', fn( $t ) => kits_rank_math_building_title() ? '' : $t, 16 );

add_filter(
	'rank_math/frontend/title',
	function ( $title ) {
		if ( kits_rank_math_building_title() || '' !== kits_rank_math_typed( 'title' ) ) {
			return $title; // typed in Rank Math: used as is
		}
		kits_rank_math_building_title( true );
		$ours = wp_get_document_title();
		kits_rank_math_building_title( false );
		$ours = trim( html_entity_decode( wp_strip_all_tags( $ours ), ENT_QUOTES, 'UTF-8' ) );
		return '' !== $ours ? $ours : $title;
	},
	20
);

/* ---------- Meta description ---------- */

add_filter(
	'rank_math/frontend/description',
	function ( $desc ) {
		if ( is_404() || '' !== kits_rank_math_typed( 'description' ) || ! function_exists( 'aw_seo_description' ) ) {
			return $desc;
		}
		$ours = aw_seo_description();
		return '' !== $ours ? $ours : $desc;
	},
	20
);

/* ---------- Canonical (og:url uses it) ---------- */

add_filter(
	'rank_math/frontend/canonical',
	function ( $url ) {
		if ( is_404() || '' !== kits_rank_math_typed( 'canonical_url' ) || ! function_exists( 'kits_seo_canonical' ) ) {
			return $url;
		}
		$ours = kits_seo_canonical();
		return '' !== $ours ? $ours : $url;
	},
	20
);

/* ---------- Robots ---------- */

// Rank Math removes every wp_robots filter. The site's noindex rules (search, form pages, thin pages, closed items …)
// are wp_robots filters, so a copy is kept before Rank Math starts and is asked here.
add_action(
	'wp',
	function () {
		global $wp_filter;
		if ( isset( $wp_filter['wp_robots'] ) && $wp_filter['wp_robots'] instanceof WP_Hook ) {
			$GLOBALS['kits_wp_robots'] = clone $wp_filter['wp_robots'];
		}
	},
	1
);

add_filter(
	'rank_math/frontend/robots',
	function ( $robots ) {
		$hook = $GLOBALS['kits_wp_robots'] ?? null;
		if ( ! $hook instanceof WP_Hook ) {
			return $robots;
		}
		$ours = (array) $hook->apply_filters( array(), array( array() ) );
		if ( ! empty( $ours['noindex'] ) ) {
			$robots['index'] = 'noindex';
		}
		if ( ! empty( $ours['nofollow'] ) ) {
			$robots['follow'] = 'nofollow';
		}
		return $robots;
	},
	20
);

/* ---------- Open Graph / Twitter ---------- */

// One share image: the item's own stored image (landscape) or the site share image, with its size (seo.php).
// A share image chosen in Rank Math wins.
foreach ( array( 'facebook', 'twitter' ) as $kits_net ) {
	add_action(
		"rank_math/opengraph/{$kits_net}/add_images",
		function ( $image ) {
			if ( is_404() || ! function_exists( 'kits_seo_image' ) || ! is_object( $image ) || ! method_exists( $image, 'add_image' ) ) {
				return;
			}
			if ( kits_rank_math_typed( 'facebook_image_id' ) || kits_rank_math_typed( 'facebook_image' ) || kits_rank_math_typed( 'twitter_image_id' ) ) {
				return;
			}
			$img = kits_seo_image();
			if ( $img ) {
				$image->add_image( array_filter( array( 'url' => $img['url'], 'width' => $img['width'], 'height' => $img['height'], 'type' => (string) get_post_mime_type( (int) ( $img['id'] ?? 0 ) ) ) ) );
			}
		}
	);
}
unset( $kits_net );

// A missing page shares no image (as before).
add_filter( 'rank_math/opengraph/pre_set_default_image', fn( $skip ) => is_404() ? true : $skip );

add_filter( 'rank_math/opengraph/type', fn( $type ) => is_singular( (array) apply_filters( 'kits_seo_article_types', array( 'post' ) ) ) ? 'article' : 'website' );
add_filter( 'rank_math/opengraph/facebook/og_locale', fn( $l ) => (string) apply_filters( 'kits_seo_locale', $l ) );

/* ---------- JSON-LD: the FAQ field joins Rank Math's graph ---------- */

add_filter(
	'rank_math/json_ld',
	function ( $data ) {
		if ( ! is_singular() || ! function_exists( 'kits_seo_faqs' ) || ! apply_filters( 'kits_seo_print_faq_schema', true ) ) {
			return $data;
		}
		$faqs = kits_seo_faqs( get_queried_object_id() );
		if ( $faqs ) {
			$data['kits_faq'] = array(
				'@type'      => 'FAQPage',
				'@id'        => get_permalink( get_queried_object_id() ) . '#faq',
				'mainEntity' => array_map( fn( $f ) => array( '@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $f[1] ) ), $faqs ),
			);
		}
		return $data;
	},
	50
);

/* ---------- Sitemap ---------- */

// Items the site does not index stay out of Rank Math's sitemap: hidden items, items without a slug, and what a site
// adds through kits_sitemap_skip (closed opportunities, form pages …).
add_filter(
	'rank_math/sitemap/entry',
	function ( $url, $type, $obj ) {
		$obj = ( 'post' === $type && is_object( $obj ) && ! empty( $obj->ID ) ) ? get_post( (int) $obj->ID ) : null; // Rank Math passes a database row
		if ( ! $obj instanceof WP_Post ) {
			return $url;
		}
		if ( '' === (string) $obj->post_name || '1' === (string) get_post_meta( $obj->ID, 'kits_noindex', true ) || apply_filters( 'kits_sitemap_skip', false, $obj ) ) {
			return false;
		}
		return $url;
	},
	10,
	3
);

// The page that serves the IndexNow key keeps working when Rank Math serves it (same key, indexnow.php).
add_filter( 'rank_math/instant_indexing/indexnow_key', fn( $k ) => $k ? $k : ( function_exists( 'kits_indexnow_key' ) ? kits_indexnow_key() : $k ) );
