<?php
/**
 * See theme-parts.php. Styles, scripts, presets and head tags the animal-writes child theme adds today (its
 * theme.json and functions.php), for a site that runs Kits by Wugweb with no child. Nothing here runs while the child
 * is active.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Animal Writes theme.json (this plugin's copy of the child's), merged over Kits' theme data the way core merges
 * a child theme's theme.json over its parent's (WP_Theme_JSON_Resolver::get_theme_data(): the parent's data, merged
 * with the child's): settings, styles, custom templates and template parts. Font files given as file:./ are resolved
 * by core against the active theme, Kits, whose assets/fonts/inter/inter-latin.woff2 is the same file as the child's
 * (tools/ci/theme_copies.py checks it).
 *
 * @param WP_Theme_JSON_Data $theme_json Kits' theme.json data.
 */
function aw_core_theme_json_data( $theme_json ) {
	if ( ! aw_core_theme_parts_on() || ! is_object( $theme_json ) || ! method_exists( $theme_json, 'update_with' ) ) {
		return $theme_json;
	}
	$file = AW_THEME_PARTS_DIR . 'theme.json';
	$data = is_readable( $file ) ? wp_json_file_decode( $file, array( 'associative' => true ) ) : null;
	if ( ! is_array( $data ) ) {
		return $theme_json;
	}
	unset( $data['$schema'] );
	return $theme_json->update_with( $data );
}
add_filter( 'wp_theme_json_data_theme', 'aw_core_theme_json_data' );

/**
 * Path or address of a file this plugin carries for the theme (theme/assets/...).
 *
 * @param string $file Path relative to the theme root, e.g. 'assets/css/aw.css'.
 */
function aw_theme_parts_path( string $file ): string {
	return AW_THEME_PARTS_DIR . ltrim( $file, '/' );
}

/*
 * The child theme's hooks, added at setup_theme: after every plugin and before the parent theme's functions.php, the
 * place the child's functions.php has, so each one keeps its order among other hooks of the same priority. Priority 11
 * is after the Customizer's own setup_theme (10), which is when a theme being previewed takes effect; each hook still
 * asks aw_core_theme_parts_on() when it runs, so it does nothing while the child theme is active.
 */
add_action(
	'setup_theme',
	function () {
		// Design tokens, component styles and interactions, over the Kits ones.
		add_action(
			'wp_enqueue_scripts',
			function () {
				if ( ! aw_core_theme_parts_on() ) {
					return;
				}
				$v = fn( $f ) => (string) filemtime( aw_theme_parts_path( $f ) );
				wp_enqueue_style( 'aw-tokens', aw_theme_file_uri( 'assets/css/tokens.css' ), array( 'kit-tokens' ), $v( 'assets/css/tokens.css' ) ); // Brand values over the Kits defaults.
				wp_enqueue_style( 'aw-components', aw_theme_file_uri( 'assets/css/aw.css' ), array( 'aw-tokens', 'kit-components' ), $v( 'assets/css/aw.css' ) );
				wp_enqueue_script(
					'aw-interactions',
					aw_theme_file_uri( 'assets/js/aw.js' ),
					array( 'kit-interactions' ),
					$v( 'assets/js/aw.js' ),
					array(
						'in_footer' => true,
						'strategy'  => 'defer',
					)
				);
			},
			20
		);

		// Reusable sections (patterns) appear under their own category in the block inserter.
		add_action(
			'init',
			function () {
				if ( ! aw_core_theme_parts_on() ) {
					return;
				}
				register_block_pattern_category( 'animal-writes', array( 'label' => __( 'Animal Writes', 'animal-writes-core' ) ) );
			}
		);

		// Home page: the hero photograph (hero pattern) is the largest thing on screen; the browser starts on it at once.
		add_action(
			'wp_head',
			function () {
				if ( ! aw_core_theme_parts_on() || ! is_front_page() || ( function_exists( 'aw_home_hero_is_campaign' ) && aw_home_hero_is_campaign() ) ) { // The campaign hero has its own artwork.
					return;
				}
				$base = '/wp-content/uploads/2026/10/aw1-im5-';
				$set  = implode( ', ', array_map( fn( $s ) => esc_url( home_url( $base . $s[0] . '.jpeg' ) ) . ' ' . $s[1] . 'w', array( array( '768x433', 768 ), array( '1024x578', 1024 ), array( '1536x866', 1536 ), array( '2048x1155', 2048 ) ) ) );
				// With Optimization Detective on, it already preloads the hero for the widths it has measured (up to 480px
				// and over 600px), so this one covers only the widths in between.
				$media = function_exists( 'od_get_breakpoint_max_widths' ) ? ' media="screen and (480px &lt; width) and (width &lt;= 600px)"' : '';
				echo '<link rel="preload" as="image" href="' . esc_url( home_url( $base . '1536x866.jpeg' ) ) . '" imagesrcset="' . esc_attr( $set ) . '" imagesizes="100vw" fetchpriority="high"' . $media . '>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
			},
			1
		);

		// tokens.css is a few custom properties: printed inline in its link's place on every page. Same position in the cascade.
		add_filter(
			'style_loader_tag',
			function ( $tag, $handle ) {
				if ( 'aw-tokens' !== $handle || ! aw_core_theme_parts_on() ) {
					return $tag;
				}
				$file = aw_theme_parts_path( 'assets/css/tokens.css' );
				return ! is_admin() && is_readable( $file ) ? '<style id="aw-tokens-css">' . file_get_contents( $file ) . "</style>\n" : $tag; // phpcs:ignore WordPress.WP.AlternativeFunctions
			},
			10,
			2
		);
	},
	11
);

/*
 * The editors (post editor, Site Editor) show content with the site's tokens and components, after the Kits styles:
 * what add_editor_style() gives a theme, which a plugin cannot call with its own paths. Same entries WordPress builds
 * for a theme's editor styles (get_block_editor_theme_styles()), added at the end of the list.
 */
add_filter(
	'block_editor_settings_all',
	function ( $settings ) {
		if ( ! aw_core_theme_parts_on() || ! current_theme_supports( 'editor-styles' ) ) {
			return $settings;
		}
		foreach ( array( 'assets/css/tokens.css', 'assets/css/aw.css' ) as $f ) {
			$path = aw_theme_parts_path( $f );
			if ( is_readable( $path ) ) {
				$settings['styles'][] = array(
					'css'            => (string) file_get_contents( $path ), // phpcs:ignore WordPress.WP.AlternativeFunctions
					'baseURL'        => aw_theme_file_uri( $f ),
					'__unstableType' => 'theme',
					'isGlobalStyles' => false,
				);
			}
		}
		return $settings;
	}
);

/*
 * The classic editor (TinyMCE) gets the same two files, as it does for a theme's add_editor_style() files: appended
 * to its list of stylesheets in the admin, as core adds get_editor_stylesheets().
 */
add_filter(
	'mce_css',
	function ( $mce_css ) {
		if ( ! is_admin() || ! aw_core_theme_parts_on() ) {
			return $mce_css;
		}
		foreach ( array( 'assets/css/tokens.css', 'assets/css/aw.css' ) as $f ) {
			if ( is_readable( aw_theme_parts_path( $f ) ) ) {
				$mce_css .= ',' . str_replace( ',', '%2C', aw_theme_file_uri( $f ) );
			}
		}
		return trim( (string) $mce_css, ' ,' );
	}
);
