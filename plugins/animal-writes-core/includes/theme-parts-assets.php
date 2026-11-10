<?php
/**
 * See theme-parts.php. Styles, scripts, presets and head tags the animal-writes child theme adds today (its
 * theme.json and functions.php), for a site that runs Kits by Wugweb with no child. While the child is active only the
 * component stylesheets load from here (aw_child_theme_on()); the rest comes from the child.
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

/**
 * Component stylesheets (theme/assets/css/aw-*.css): each loads only on the pages that print its component, joined
 * with aw.css into one file per page by Kits (kits_component_styles, Kits functions.php). handle => [
 *   'file'   => path relative to the theme root; the .min.css next to it is served when it exists,
 *   'when'   => optional callable, no arguments: a template condition, e.g. 'is_front_page',
 *   'blocks' => optional [ block name => class prefix or list of prefixes ]: stored content no PHP prints, e.g. [ 'core/group' => 'aw-about-' ],
 * ]. With no 'when', the file loads on pages whose PHP called aw_use_component_style( $handle ) while printing.
 * The order here is the order in the joined file, after aw.css. Editors load every file (below). For example:
 *
 *     'aw-home'     => array( 'file' => 'assets/css/aw-home.css', 'when' => 'is_front_page' ),
 *     'aw-type-ico' => array( 'file' => 'assets/css/aw-type-ico.css' ),
 *
 * tools/ci/css_min.py writes and checks the .min.css of every theme/assets/css/aw*.css.
 *
 * @return array<string,array>
 */
function aw_component_style_files(): array {
	return array(
		// Campaign blocks and campaign pages: first, so they print right after aw.css, where their rules were.
		'aw-countdown' => array( // [aw_countdown]: the Home hero, the Supreme Court card, campaign heroes.
			'file' => 'assets/css/aw-countdown.css',
		),
		'aw-campaign-page' => array( // Campaign pages; the steps list wherever it is placed.
			'file'   => 'assets/css/aw-campaign-page.css',
			'when'   => fn() => is_singular( 'aw_campaign' ),
			'blocks' => array( 'core/list' => 'aw-steps' ),
		),
		'aw-camp-band-aw' => array( // The campaign band's overrides, where the Home campaign hero or the open band prints.
			'file' => 'assets/css/aw-camp-band-aw.css',
		),
		'aw-sc-card' => array( // The Supreme Court campaign card.
			'file' => 'assets/css/aw-sc-card.css',
		),
		'aw-camp-story' => array( // The Community dogs story band.
			'file'   => 'assets/css/aw-camp-story.css',
			'blocks' => array( 'core/cover' => 'aw-ed-story' ),
		),
		'aw-festive' => array( // The festive card.
			'file'   => 'assets/css/aw-festive.css',
			'blocks' => array( 'core/cover' => 'aw-festive' ),
		),
		// These four follow, where their rules were in aw.css: aw-meta must come before aw-listing (.aw-claim-meta).
		'aw-meta' => array( // An item's meta row and the claim head; places and News card rows by flag.
			'file' => 'assets/css/aw-meta.css',
			'when' => fn() => is_singular( array( 'post', 'aw_news', 'aw_legal', 'aw_listing', 'aw_opportunity' ) ) || is_page( 'claim' ),
		),
		'aw-volunteer' => array( // The volunteer page; the volunteer form and banner wherever they print.
			'file'   => 'assets/css/aw-volunteer.css',
			'when'   => fn() => is_page( 'volunteer' ),
			'blocks' => array( 'core/group' => 'aw-vol-' ),
		),
		'aw-contact' => array( // The contact page; the Elsewhere list by flag.
			'file'   => 'assets/css/aw-contact.css',
			'when'   => fn() => is_page( 'contact' ),
			'blocks' => array( 'core/group' => 'aw-contact' ),
		),
		'aw-search' => array( // The search page (?s= redirects to /search/?q=).
			'file' => 'assets/css/aw-search.css',
			'when' => fn() => is_page( 'search' ),
		),
		'aw-home' => array( // Home sections: the front page, plus the Home section patterns and blocks wherever they are placed.
			'file'   => 'assets/css/aw-home.css',
			'when'   => 'is_front_page',
			'blocks' => array( 'core/group' => array( 'aw-ed-', 'aw-pair' ) ), // help-now, find-help, animal-law, learn, whats-happening, aw-pair.
		),
		'aw-type-ico' => array( // Type icons: wherever aw_type_icon() or the Directory map prints one.
			'file' => 'assets/css/aw-type-ico.css',
		),
		'aw-single' => array( // Single items, plus a flag from the code that prints More, Sources, share, take-part, find-help and the rest elsewhere.
			'file' => 'assets/css/aw-single.css',
			'when' => fn() => is_singular( array( 'post', 'aw_news', 'aw_legal', 'aw_listing', 'aw_opportunity' ) ),
		),
		'aw-forms' => array( // Fluent Forms skin of the site's own forms (aw_ff_form()) and the form-for line; the newsletter keeps its skin in aw.css.
			'file' => 'assets/css/aw-forms.css',
		),
		'aw-lists' => array( // News, hub and related lists, hub groups, tasks, story and sponsored cards, the verified mark.
			'file'   => 'assets/css/aw-lists.css',
			'when'   => 'is_front_page', // Home's lists are stored blocks
			'blocks' => array(
				'core/query'         => 'aw-hub-list', // hub list patterns and stored pages
				'core/post-template' => array( 'aw-news-list', 'aw-tasks', 'aw-news-grid' ),
			),
		),
		'aw-directory' => array( // The Directory: search, filters, organisation rows, helplines, local cards; flags print it elsewhere.
			'file'   => 'assets/css/aw-directory.css',
			'when'   => fn() => function_exists( 'aw_is_directory_view' ) && aw_is_directory_view(), // /directory/ and its place and type URLs
			'blocks' => array(
				'core/post-template' => 'aw-orgs', // the Directory list pattern, placed elsewhere
			),
		),
		'aw-listing' => array( // Directory listing pages and the claim page.
			'file' => 'assets/css/aw-listing.css',
			'when' => fn() => is_singular( 'aw_listing' ) || is_page( 'claim' ),
		),
		'aw-hubs' => array( // Hub pages, the category hub and index, term chips, the adoption page; flags print it elsewhere.
			'file'   => 'assets/css/aw-hubs.css',
			'when'   => fn() => is_page_template( 'page-hub' ) || ( function_exists( 'aw_hub_type' ) && '' !== aw_hub_type() ) || is_category() || is_tag() || is_page( 'adoption' ),
			'blocks' => array(
				'core/query'         => array( 'aw-hub-news', 'aw-hub-library', 'aw-hub-directory', 'aw-camp-more' ), // hub list patterns placed elsewhere
				'core/post-template' => 'aw-cases',
			),
		),
	);
}

/**
 * Called by the PHP that prints a component: this page loads that component's stylesheet.
 *
 * @param string $handle Handle from aw_component_style_files().
 */
function aw_use_component_style( string $handle ): void {
	if ( function_exists( 'kits_use_component_style' ) ) {
		kits_use_component_style( $handle );
	}
}

/**
 * The served copy of a theme CSS file: its .min.css (tools/ci/css_min.py) when that exists.
 *
 * @param string $file Path relative to the theme root.
 */
function aw_theme_css_served( string $file ): string {
	$min = (string) preg_replace( '/\.css$/', '.min.css', $file );
	return is_readable( aw_theme_parts_path( $min ) ) ? $min : $file;
}

/**
 * The animal-writes child theme is active (the way back from serving the theme parts). Its aw.css is the same file as
 * this plugin's (tools/ci/theme_copies.py), so it has no component rules: the component files load from this plugin.
 */
function aw_child_theme_on(): bool {
	return 'animal-writes' === get_stylesheet() && 'kits-by-wugweb' === get_template();
}

/**
 * Address of this plugin's copy of a theme file, whichever theme is active.
 *
 * @param string $file Path relative to the theme root.
 */
function aw_theme_parts_uri( string $file ): string {
	return plugins_url( 'theme/' . ltrim( $file, '/' ), AW_CORE_DIR . 'animal-writes-core.php' );
}

/**
 * Stylesheets the editors show: tokens, aw.css, then every component file (sources, not minified). Under the child
 * theme only the component files: the child's add_editor_style() gives tokens and aw.css.
 */
function aw_editor_style_files(): array {
	$parts = array_column( aw_component_style_files(), 'file' );
	return aw_child_theme_on() ? $parts : array_merge( array( 'assets/css/tokens.css', 'assets/css/aw.css' ), $parts );
}

/** The editors load this plugin's files: while it serves the theme parts, and the component files under the child. */
function aw_editor_styles_on(): bool {
	return aw_core_theme_parts_on() || aw_child_theme_on();
}

/*
 * aw.css first (every page), then the component files, through the Kits registry. Under the child theme the child
 * registers aw-components (its own aw.css) and the component files come from this plugin, after it.
 */
add_filter(
	'kits_component_styles',
	function ( $styles ) {
		$child = aw_child_theme_on();
		if ( ( ! $child && ! aw_core_theme_parts_on() ) || ! is_array( $styles ) ) {
			return $styles;
		}
		if ( ! $child ) {
			// Registered and enqueued below (wp_enqueue_scripts 20). 'file' lets Kits join it when its address cannot be mapped.
			$styles['aw-components'] = array(
				'file' => aw_theme_parts_path( aw_theme_css_served( 'assets/css/aw.css' ) ),
				'when' => '__return_true',
			);
		}
		foreach ( aw_component_style_files() as $handle => $c ) {
			if ( empty( $c['file'] ) ) {
				continue;
			}
			$file              = aw_theme_css_served( (string) $c['file'] );
			$styles[ $handle ] = array(
				'src'    => aw_theme_parts_uri( $file ),
				'file'   => aw_theme_parts_path( $file ),
				'deps'   => array( 'aw-components' ),
				'when'   => $c['when'] ?? null,
				'blocks' => $c['blocks'] ?? array(),
			);
		}
		return $styles;
	}
);

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
				$v   = fn( $f ) => (string) filemtime( aw_theme_parts_path( $f ) );
				wp_enqueue_style( 'aw-tokens', aw_theme_file_uri( 'assets/css/tokens.css' ), array( 'kit-tokens' ), $v( 'assets/css/tokens.css' ) ); // Brand values over the Kits defaults.
				$css = is_readable( aw_theme_parts_path( 'assets/css/aw.min.css' ) ) ? 'assets/css/aw.min.css' : 'assets/css/aw.css'; // aw.min.css: aw.css minified (tools/ci/css_min.py).
				wp_enqueue_style( 'aw-components', aw_theme_file_uri( $css ), array( 'aw-tokens', 'kit-components' ), $v( $css ) );
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
 * The editors (post editor, Site Editor) show content with the site's tokens and components, after the Kits styles
 * (every component file, whatever the page prints):
 * what add_editor_style() gives a theme, which a plugin cannot call with its own paths. Same entries WordPress builds
 * for a theme's editor styles (get_block_editor_theme_styles()), added at the end of the list.
 */
add_filter(
	'block_editor_settings_all',
	function ( $settings ) {
		if ( ! aw_editor_styles_on() || ! current_theme_supports( 'editor-styles' ) ) {
			return $settings;
		}
		foreach ( aw_editor_style_files() as $f ) {
			$path = aw_theme_parts_path( $f );
			if ( is_readable( $path ) ) {
				$settings['styles'][] = array(
					'css'            => (string) file_get_contents( $path ), // phpcs:ignore WordPress.WP.AlternativeFunctions
					'baseURL'        => aw_theme_parts_uri( $f ),
					'__unstableType' => 'theme',
					'isGlobalStyles' => false,
				);
			}
		}
		return $settings;
	}
);

/*
 * The classic editor (TinyMCE) gets the same files, as it does for a theme's add_editor_style() files: appended
 * to its list of stylesheets in the admin, as core adds get_editor_stylesheets().
 */
add_filter(
	'mce_css',
	function ( $mce_css ) {
		if ( ! is_admin() || ! aw_editor_styles_on() ) {
			return $mce_css;
		}
		foreach ( aw_editor_style_files() as $f ) {
			if ( is_readable( aw_theme_parts_path( $f ) ) ) {
				$mce_css .= ',' . str_replace( ',', '%2C', aw_theme_parts_uri( $f ) );
			}
		}
		return trim( (string) $mce_css, ' ,' );
	}
);

// Kits Core prints the relative time (aw-rel-time) in its post-date filter (priority 20); Kits names no Animal Writes
// handle, so Animal Writes records aw-lists after it.
add_filter(
	'render_block_core/post-date',
	function ( $html ) {
		if ( false !== strpos( (string) $html, 'aw-rel-time' ) ) {
			aw_use_component_style( 'aw-lists' );
		}
		return $html;
	},
	21
);
