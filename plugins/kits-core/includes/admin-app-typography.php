<?php
/**
 * Kits admin app, Typography section (docs/admin-ui-spec.md D1.6): the data the screen needs, added to
 * window.kitsAdminApp through kits_admin_app_data. The screen saves to the user Global Styles post through the shared
 * store (src/theme-options/global-styles.js, data from admin-app-global-styles.php), so nothing is saved here.
 *
 * - typography.families: the installed font families (theme and Font Library), for the three font pickers.
 * - typography.roles: body, heading and button font, each with its Global Styles path and the theme's value.
 * - typography.sizes: the theme's font sizes (theme.json as resolved, before user Styles).
 * - typography.fluid: the screen widths the fluid sizes scale between (docs/design-system.md, Breakpoints:
 *   "Fluid type scales from 375 to 1200").
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Typography section's data.
 *
 * @return array families, roles, sizes, fluid, links.
 */
function kits_admin_app_typography_data(): array {
	$families = array();
	$seen     = array();
	$all      = (array) wp_get_global_settings( array( 'typography', 'fontFamilies' ) );
	foreach ( array( 'theme', 'custom' ) as $origin ) {
		foreach ( (array) ( $all[ $origin ] ?? array() ) as $f ) {
			$slug = (string) ( $f['slug'] ?? '' );
			if ( '' === $slug || isset( $seen[ $slug ] ) ) {
				continue;
			}
			$seen[ $slug ] = true;
			$families[]    = array(
				'slug'       => $slug,
				'name'       => (string) ( $f['name'] ?? $slug ),
				'fontFamily' => (string) ( $f['fontFamily'] ?? '' ),
				'origin'     => $origin,
			);
		}
	}

	$theme  = WP_Theme_JSON_Resolver::get_theme_data()->get_raw_data();
	$styles = (array) ( $theme['styles'] ?? array() );
	$roles  = array(
		'body'    => array(
			'label' => __( 'Body font', 'kits-core' ),
			'note'  => __( 'Text in posts, pages, lists and forms.', 'kits-core' ),
			'path'  => array( 'styles', 'typography', 'fontFamily' ),
			'theme' => (string) ( $styles['typography']['fontFamily'] ?? '' ),
		),
		'heading' => array(
			'label' => __( 'Heading font', 'kits-core' ),
			'note'  => __( 'Headings H1 to H6.', 'kits-core' ),
			'path'  => array( 'styles', 'elements', 'heading', 'typography', 'fontFamily' ),
			'theme' => (string) ( $styles['elements']['heading']['typography']['fontFamily'] ?? '' ),
		),
		'button'  => array(
			'label' => __( 'Button and label font', 'kits-core' ),
			'note'  => __( 'Buttons across the site, in the content, the header, forms and cards.', 'kits-core' ),
			'path'  => array( 'styles', 'elements', 'button', 'typography', 'fontFamily' ),
			'theme' => (string) ( $styles['elements']['button']['typography']['fontFamily'] ?? '' ),
		),
	);

	$sizes = array();
	foreach ( (array) ( $theme['settings']['typography']['fontSizes']['theme'] ?? array() ) as $s ) {
		if ( ! empty( $s['slug'] ) && isset( $s['size'] ) ) {
			$sizes[] = array(
				'slug' => (string) $s['slug'],
				'name' => (string) ( $s['name'] ?? $s['slug'] ),
				'size' => (string) $s['size'],
			);
		}
	}

	return array(
		'families' => $families,
		'roles'    => $roles,
		'sizes'    => $sizes,
		'fluid'    => array(
			'min' => 375,
			'max' => 1200,
		),
		'links'    => array(
			'fonts' => admin_url( 'font-library.php' ),
		),
	);
}

add_filter(
	'kits_admin_app_data',
	function ( $data ) {
		$data               = (array) $data;
		$data['typography'] = kits_admin_app_typography_data();
		return $data;
	}
);

// The installed fonts' @font-face rules on the new screen, so each font's sample line shows in that font.
add_action(
	'admin_print_styles',
	function () {
		if ( function_exists( 'kits_admin_app_wanted' ) && kits_admin_app_wanted() && current_user_can( 'manage_options' ) && function_exists( 'wp_print_font_faces' ) ) {
			wp_print_font_faces();
		}
	}
);
