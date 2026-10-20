<?php
/**
 * Page speed on the front end (Core Web Vitals):
 *
 * - jQuery (and jQuery Migrate) print at the end of the page instead of in the head, so they no longer hold back the
 *   first paint. Scripts that need jQuery all print in the footer after it; a script that asks for it in the head still
 *   gets it there (WordPress moves a dependency up to its dependant's group).
 * - The main picture keeps its high priority on wide screens until Optimization Detective has measured them.
 *   Optimization Detective takes fetchpriority="high" off the picture the page marks as its main one as soon as it has
 *   measurements from any screen size, and preloads the measured main picture only for the sizes it has measured. Wide
 *   screens (few visits, so often no measurements) were then left with no priority on any picture. When no preload
 *   covers the widest group, that picture is preloaded with high priority for the widest group only.
 * - Render-blocking files (owner, 20 Oct 2026):
 *   - the footer scripts jQuery, jQuery Migrate, Fluent Forms' submit script, wp-hooks, wp-i18n and Accessibility
 *     Checker's front-end fixes are deferred. WordPress's own strategy check keeps any of them blocking when an inline
 *     script after it, or a blocking script that needs it, would break;
 *   - Fluent Forms' two stylesheets load without blocking the first paint, except on a page whose own content has a
 *     form (there the form may be near the top; a site marks its own form shortcodes and blocks with the
 *     kits_page_has_form filter). Elsewhere the only form is the footer sign-up, far below the fold;
 *   - Accessibility Checker's sr-only stylesheet loads only on a page whose content uses its sr-only text format;
 *   - no emoji script or styles (the site shows emoji as text).
 *   Ads scripts (Advanced Ads, AdSense) are left as they are.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_enqueue_scripts',
	function () {
		$scripts = wp_scripts();
		foreach ( array( 'jquery', 'jquery-core', 'jquery-migrate' ) as $handle ) {
			if ( isset( $scripts->registered[ $handle ] ) ) {
				$scripts->add_data( $handle, 'group', 1 );
			}
		}
	},
	1
);

add_filter(
	'od_template_output_buffer',
	function ( $html ) {
		if ( false === strpos( $html, 'data-od-removed-fetchpriority' ) ) {
			return $html;
		}
		// Preloads Optimization Detective added: media "screen and (600px < width <= 782px)" covers one group; one with no
		// upper bound ("screen", "screen and (782px < width)") covers the widest.
		if ( preg_match_all( '/<link\b[^>]*\bdata-od-added-tag\b[^>]*>/', $html, $links ) ) {
			foreach ( $links[0] as $link ) {
				if ( false !== strpos( $link, 'rel="preload"' ) && false === strpos( $link, '&lt;=' ) && false === strpos( $link, '<=' ) ) {
					return $html; // the widest group has its own preload
				}
			}
		}
		$widths = function_exists( 'od_get_breakpoint_max_widths' ) ? (array) od_get_breakpoint_max_widths() : array();
		$min    = $widths ? (int) max( $widths ) : 782;
		$p      = new WP_HTML_Tag_Processor( $html );
		while ( $p->next_tag( 'IMG' ) ) {
			if ( 'high' !== $p->get_attribute( 'data-od-removed-fetchpriority' ) ) {
				continue;
			}
			$src = (string) $p->get_attribute( 'src' );
			if ( '' === $src || 0 === strpos( $src, 'data:' ) ) {
				return $html;
			}
			$attrs = array(
				'rel'            => 'preload',
				'as'             => 'image',
				'fetchpriority'  => 'high',
				'href'           => $src,
				'imagesrcset'    => (string) $p->get_attribute( 'srcset' ),
				'imagesizes'     => preg_replace( '/^\s*auto\s*,\s*/i', '', (string) $p->get_attribute( 'sizes' ) ), // "auto" is for lazy images only
				'crossorigin'    => is_string( $p->get_attribute( 'crossorigin' ) ) ? (string) $p->get_attribute( 'crossorigin' ) : '',
				'referrerpolicy' => (string) $p->get_attribute( 'referrerpolicy' ),
				'media'          => 'screen and (' . $min . 'px < width)',
			);
			$link = '<link data-kits-lcp-preload';
			foreach ( $attrs as $k => $v ) {
				if ( '' !== $v ) {
					$link .= ' ' . $k . '="' . esc_attr( $v ) . '"';
				}
			}
			$link .= '>' . "\n";
			$at    = stripos( $html, '</head>' );
			return false === $at ? $html : substr_replace( $html, $link, $at, 0 );
		}
		return $html;
	},
	20
);

/** Whether the main content of the current singular page contains $needle (a form, a format class). */
function kits_content_has( $needle ) {
	return is_singular() && false !== strpos( (string) get_post_field( 'post_content', get_queried_object_id() ), $needle );
}

remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
add_filter( 'emoji_svg_url', '__return_false' );

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( is_admin() ) {
			return;
		}
		$scripts = wp_scripts();
		foreach ( array( 'jquery-core', 'jquery-migrate', 'fluent-form-submission', 'wp-hooks', 'wp-i18n', 'edac-frontend-fixes' ) as $handle ) {
			if ( isset( $scripts->registered[ $handle ] ) && ! $scripts->get_data( $handle, 'strategy' ) ) {
				$scripts->add_data( $handle, 'strategy', 'defer' );
			}
		}
		if ( ! kits_content_has( 'text-format-sr-only' ) ) {
			wp_dequeue_style( 'edac-sr-only-format' );
		}
	},
	PHP_INT_MAX
);

add_filter(
	'style_loader_tag',
	function ( $tag, $handle ) {
		if ( is_admin() || ! in_array( $handle, array( 'fluent-form-styles', 'fluentform-public-default' ), true ) || apply_filters( 'kits_page_has_form', kits_content_has( 'fluentform' ) ) ) {
			return $tag;
		}
		$late = preg_replace( "/\smedia=(['\"])[^'\"]*\\1/", " media='print' onload=\"this.media='all'\"", $tag, 1 );
		return $late === $tag ? $tag : $late . '<noscript>' . trim( $tag ) . "</noscript>\n";
	},
	10,
	2
);
