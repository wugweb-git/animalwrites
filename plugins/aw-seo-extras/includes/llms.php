<?php
/**
 * llms.txt (SEO Phase 5, AI visibility, 15 Oct 2026). Rank Math's llms.txt module writes the file; its settings
 * (General → Edit llms.txt) choose the post types, taxonomies, limit and the summary under the title. This file only
 * adds what those settings cannot express:
 *
 * - The file starts with its "# Animal Writes" title (no credit line above it, as the llms.txt format expects).
 * - Each item is described by its Rank Math description (the same words as its search snippet), else its excerpt.
 * - Each section hub's line ends with its live count and the date (no stale "995 stories" copy).
 * - Pages come in the site's order: the hubs, then About and the policies, then the forms.
 * - Resources list every guide first, then the newest research summaries.
 * - A Directory section from live counts (how the pages are arranged, and every state with listings), in place of
 *   a sample of single listings.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The order of Pages in llms.txt, by slug ("" = the home page). Pages not named here follow, as Rank Math lists them. */
const AW_LLMS_PAGE_ORDER = array( '', 'news', 'library', 'resources', 'directory', 'opportunities', 'campaigns', 'press-coverage', 'categories', 'about', 'editorial-policy', 'corrections', 'contact', 'privacy-policy', 'terms', 'disclaimer', 'volunteer', 'claim' );

/** Resource types that are guides (listed before research). */
const AW_LLMS_GUIDE_TYPES = array( 'guide', 'faq' );

add_filter( 'rank_math/llms_txt/remove_credit', '__return_true' );

add_filter(
	'rank_math/llms_txt/post_description',
	function ( $desc, $post ) {
		$rm = trim( (string) get_post_meta( $post->ID, 'rank_math_description', true ) );
		if ( '' !== $rm ) {
			$rm   = class_exists( '\RankMath\Helper' ) ? \RankMath\Helper::replace_vars( $rm, $post ) : $rm;
			$desc = '' !== trim( wp_strip_all_tags( $rm ) ) ? $rm : $desc;
		}
		return aw_llms_hub_count( $post, $desc );
	},
	10,
	2
);

/**
 * A section hub's line ends with what it holds today, counted live as the hub counts it ("1,006 stories",
 * "15 guides, 1 FAQ page and 143 research summaries"), with the date: the counts are never a stale copy.
 *
 * @param WP_Post $post Post.
 * @param string  $desc Its description.
 * @return string
 */
function aw_llms_hub_count( $post, $desc ) {
	if ( 'page' !== $post->post_type || $post->post_parent || ! function_exists( 'aw_hub_count_label' ) || ! defined( 'AW_HUBS' ) ) {
		return $desc;
	}
	foreach ( AW_HUBS as $type => $h ) {
		if ( $h[0] === $post->post_name ) {
			$label = aw_hub_count_label( $type );
			return '' !== $label ? rtrim( (string) $desc ) . ' ' . aw_llms_ucfirst( $label ) . ' on ' . wp_date( 'j M Y' ) . '.' : $desc;
		}
	}
	return $desc;
}

/** First letter upper case (a count starts with a digit, so usually unchanged). */
function aw_llms_ucfirst( $text ) {
	return mb_strtoupper( mb_substr( $text, 0, 1 ) ) . mb_substr( $text, 1 );
}

// The counts are live: the page cache keeps llms.txt for an hour at most.
add_action( 'rank_math/llms_txt/before_output', fn() => do_action( 'litespeed_control_set_ttl', HOUR_IN_SECONDS ) );

add_filter(
	'rank_math/llms_txt/posts',
	function ( $posts, $args ) {
		$type = $args['post_type'] ?? '';
		if ( 'page' === $type ) {
			$front = (int) get_option( 'page_on_front' );
			$rank  = array_flip( AW_LLMS_PAGE_ORDER );
			$key   = fn( $p ) => $rank[ (int) $p->ID === $front ? '' : $p->post_name ] ?? 999;
			usort( $posts, fn( $a, $b ) => $key( $a ) <=> $key( $b ) );
			return $posts;
		}
		if ( 'post' === $type ) {
			$limit  = max( 1, (int) ( $args['posts_per_page'] ?? 50 ) );
			$guides = get_posts(
				array(
					'post_type'      => 'post',
					'post_status'    => 'publish',
					'posts_per_page' => $limit,
					'has_password'   => false,
					'tax_query'      => array( array( 'taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => AW_LLMS_GUIDE_TYPES ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
				)
			);
			$seen   = array_flip( wp_list_pluck( $guides, 'ID' ) );
			$rest   = array_filter( (array) $posts, fn( $p ) => ! isset( $seen[ $p->ID ] ) );
			return array_slice( array_merge( $guides, array_values( $rest ) ), 0, $limit );
		}
		return $posts;
	},
	10,
	2
);

/**
 * The Directory section: how its pages are arranged, and each state with listings (most first) with its count.
 *
 * @return string
 */
function aw_llms_directory_section() {
	if ( ! function_exists( 'aw_seo_dir_children' ) || ! function_exists( 'aw_directory_url' ) ) {
		return '';
	}
	$hub   = get_page_by_path( 'directory' );
	$desc  = $hub ? trim( (string) get_post_meta( $hub->ID, 'rank_math_description', true ) ) : '';
	$lines = array( '## Directory' );
	$lines[] = '- [Directory](' . esc_url_raw( home_url( '/directory/' ) ) . ')' . ( '' !== $desc ? ': ' . $desc : '' );
	$lines[] = '- Pages by place: /directory/{state}/, /directory/{state}/{district}/ and /directory/{state}/{district}/{type}/; each organisation has its own page at /directory/org/{name}/.';
	foreach ( aw_seo_dir_children( null ) as $row ) {
		list( $place, $n ) = $row;
		$lines[] = '- [' . $place->name . '](' . esc_url_raw( aw_directory_url( $place->slug ) ) . '): ' . number_format_i18n( $n ) . ( 1 === $n ? ' listing' : ' listings' );
	}
	return implode( "\n", $lines ) . "\n";
}

add_filter(
	'rank_math/llms_txt/extra_content',
	function ( $extra ) {
		$dir = aw_llms_directory_section();
		return trim( $dir . ( '' !== trim( (string) $extra ) ? "\n" . $extra : '' ) );
	}
);
