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

/*
 * The Animal Writes presets (colour palette, Inter) from Kits' style variation styles/animal-writes.json, which
 * tools/aw-style-variation.py copies from the child's theme.json. Merged into the theme's own data the way a child
 * theme.json is, so they are the theme's presets without the variation being picked in the Site Editor.
 */
add_filter(
	'wp_theme_json_data_theme',
	function ( $theme_json ) {
		if ( ! aw_core_theme_parts_on() ) {
			return $theme_json;
		}
		$file = get_template_directory() . '/styles/animal-writes.json';
		$data = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! is_array( $data ) || empty( $data['settings'] ) ) {
			return $theme_json;
		}
		unset( $data['$schema'], $data['title'] );
		return $theme_json->update_with( $data );
	}
);

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
 * place the child's functions.php has, so each one keeps its order among other hooks of the same priority.
 */
add_action(
	'setup_theme',
	function () {
		if ( ! aw_core_theme_parts_on() ) {
			return;
		}

		// Design tokens, component styles and interactions, over the Kits ones.
		add_action(
			'wp_enqueue_scripts',
			function () {
				$v = fn( $f ) => (string) filemtime( aw_theme_parts_path( $f ) );
				wp_enqueue_style( 'aw-tokens', aw_theme_file_uri( 'assets/css/tokens.css' ), array( 'kit-tokens' ), $v( 'assets/css/tokens.css' ) ); // brand values over the Kits defaults
				wp_enqueue_style( 'aw-components', aw_theme_file_uri( 'assets/css/aw.css' ), array( 'aw-tokens', 'kit-components' ), $v( 'assets/css/aw.css' ) );
				wp_enqueue_script( 'aw-interactions', aw_theme_file_uri( 'assets/js/aw.js' ), array( 'kit-interactions' ), $v( 'assets/js/aw.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
			},
			20
		);

		// Reusable sections (patterns) appear under their own category in the block inserter.
		add_action(
			'init',
			function () {
				register_block_pattern_category( 'animal-writes', array( 'label' => __( 'Animal Writes', 'animal-writes-core' ) ) );
			}
		);

		// Home page: the hero photograph (hero pattern) is the largest thing on screen; the browser starts on it at once.
		add_action(
			'wp_head',
			function () {
				if ( ! is_front_page() || ( function_exists( 'aw_home_hero_is_campaign' ) && aw_home_hero_is_campaign() ) ) { // the campaign hero has its own artwork
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

		// Home page: the navigation and cover block rules it uses are printed inline in place of WordPress's two
		// stylesheets, same position in the cascade. Only while WordPress is the version they were made from.
		add_filter(
			'style_loader_tag',
			function ( $tag, $handle ) {
				$files = array( 'wp-block-navigation' => 'home-navigation.css', 'wp-block-cover' => 'home-cover.css' );
				if ( ! is_front_page() || is_admin() || ! isset( $files[ $handle ] ) || get_bloginfo( 'version' ) !== '7.1.3' ) { // the child theme's AW_HOME_CORE_CSS_WP
					return $tag;
				}
				$file = aw_theme_parts_path( 'assets/vendor/wp-core/' . $files[ $handle ] );
				return is_readable( $file ) ? '<style id="' . esc_attr( $handle ) . '-home-css">' . file_get_contents( $file ) . "</style>\n" : $tag; // phpcs:ignore WordPress.WP.AlternativeFunctions
			},
			10,
			2
		);

		// tokens.css is a few custom properties: printed inline in its link's place on every page. Same position in the cascade.
		add_filter(
			'style_loader_tag',
			function ( $tag, $handle ) {
				$file = aw_theme_parts_path( 'assets/css/tokens.css' );
				return 'aw-tokens' === $handle && ! is_admin() && is_readable( $file ) ? '<style id="aw-tokens-css">' . file_get_contents( $file ) . "</style>\n" : $tag; // phpcs:ignore WordPress.WP.AlternativeFunctions
			},
			10,
			2
		);
	}
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
