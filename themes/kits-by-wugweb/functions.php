<?php
/**
 * Kits by Wugweb — theme setup.
 *
 * @package kits-by-wugweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// A site's own templates, parts, patterns and files (assets/site/, never in the distributed theme): loaded first, where
// a child theme's functions.php would load, so the site's hooks come before the theme's own.
require_once get_template_directory() . '/inc/site-layer.php';

/**
 * The theme's one database option, kits_by_wugweb: an array of the theme's own state (the pattern files' signature).
 * WordPress.org's theme rules allow one option, named after the theme. The joined stylesheets' state moved to Kits
 * Core (option kits_core_joined) on 18 Nov 2026; kits_theme_store_tidy() drops the entries left from before.
 *
 * @param string $key     Entry.
 * @param mixed  $fallback What to return when the entry is not set.
 * @return mixed
 */
function kits_theme_store( string $key, $fallback = null ) {
	$all = get_option( 'kits_by_wugweb', array() );
	return is_array( $all ) && array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
}

/**
 * Sets one entry of the theme's option (written only when it changes).
 *
 * @param string $key   Entry.
 * @param mixed  $value Value.
 */
function kits_theme_store_set( string $key, $value ): void {
	$all = get_option( 'kits_by_wugweb', array() );
	$all = is_array( $all ) ? $all : array();
	if ( array_key_exists( $key, $all ) && $all[ $key ] === $value ) {
		return;
	}
	$all[ $key ] = $value;
	update_option( 'kits_by_wugweb', $all, true );
}

/**
 * Removes one entry of the theme's option.
 *
 * @param string $key Entry.
 */
function kits_theme_store_delete( string $key ): void {
	$all = get_option( 'kits_by_wugweb', array() );
	if ( is_array( $all ) && array_key_exists( $key, $all ) ) {
		unset( $all[ $key ] );
		update_option( 'kits_by_wugweb', $all, true );
	}
}

/**
 * Editor styles. A block theme gets editor-styles, responsive-embeds and post-thumbnails from core on its own.
 */
function kits_setup() {
	add_editor_style( 'assets/css/kits-v42.css' );
	add_editor_style( 'assets/css/kit-tokens.css' );
	add_editor_style( 'assets/css/kits.css' );
	add_editor_style( 'assets/css/kit.css' ); // components and primitives: the editor shows content as the site does.
	add_editor_style( 'assets/css/kit-primitives.css' );
	if ( is_readable( get_template_directory() . '/assets/site/site.css' ) ) {
		add_editor_style( 'assets/site/site.css' ); // the site's own CSS (kits_site_component_style).
	}
}
add_action( 'after_setup_theme', 'kits_setup' );

/*
 * Fonts: Inter, declared once in theme.json (settings.typography.fontFamilies, with its fontFace in
 * assets/fonts/inter/inter-latin.woff2). WordPress prints the @font-face on the site and in the editor, and the Font
 * Library and Styles manage it; nothing loads from another server.
 */

if ( ! function_exists( 'kits_css_file' ) ) {
	/**
	 * The file a Kits stylesheet is served from: its .min.css (tools/ci/css_min.py writes it from the source) when it is
	 * there, else the source. The block editor keeps loading the sources.
	 *
	 * @param string $name File name without .css, in assets/css (kit-tokens, kit, kit-primitives).
	 * @return string Path relative to the theme folder.
	 */
	function kits_css_file( $name ) {
		$min = 'assets/css/' . $name . '.min.css';
		return is_readable( get_template_directory() . '/' . $min ) ? $min : 'assets/css/' . $name . '.css';
	}
}

/**
 * Front-end styles that theme.json cannot express.
 */
function kits_enqueue_styles() {
	$dir = get_template_directory();
	$uri = get_template_directory_uri();
	// Kits v4.2 tokens (bare names: --neutral-*, --color-*, --s-*, --r-*, --opacity-*, --fluid-*). Loads before kit-tokens so a child theme can override.
	wp_enqueue_style( 'kits-v42', $uri . '/assets/css/kits-v42.css', array( 'global-styles' ), (string) filemtime( $dir . '/assets/css/kits-v42.css' ) );
	// Design tokens (--kit-*): neutral defaults; a child theme overrides them with its own token file.
	$css = kits_css_file( 'kit-tokens' );
	wp_enqueue_style( 'kit-tokens', $uri . '/' . $css, array( 'global-styles' ), (string) filemtime( $dir . '/' . $css ) );
	wp_enqueue_style(
		'kits',
		get_template_directory_uri() . '/assets/css/kits.css',
		array(),
		(string) filemtime( get_template_directory() . '/assets/css/kits.css' ) // a new version on every change, so browsers never keep an old copy.
	);
}
add_action( 'wp_enqueue_scripts', 'kits_enqueue_styles' );

/**
 * Kits components and interactions (token-driven; a child theme restyles them by overriding --kit-* tokens).
 */
function kits_enqueue_components() {
	$dir = get_template_directory();
	$uri = get_template_directory_uri();
	$css = kits_css_file( 'kit' );
	wp_enqueue_style( 'kit-components', $uri . '/' . $css, array( 'kit-tokens', 'kits' ), (string) filemtime( $dir . '/' . $css ) );
	$css = kits_css_file( 'kit-primitives' );
	wp_enqueue_style( 'kit-primitives', $uri . '/' . $css, array( 'kit-components' ), (string) filemtime( $dir . '/' . $css ) );
	wp_enqueue_script(
		'kit-interactions',
		$uri . '/assets/js/kit.js',
		array(),
		(string) filemtime( $dir . '/assets/js/kit.js' ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	); // scroll-in motion: the browser's own (element.animate), no GSAP (owner, 2 Nov 2026).
}
add_action( 'wp_enqueue_scripts', 'kits_enqueue_components', 15 );

/**
 * Pattern categories (Editor → Patterns): Kits: Components and Kits: Lists.
 */
function kits_pattern_categories() {
	register_block_pattern_category( 'kits-components', array( 'label' => __( 'Kits: Components', 'kits-by-wugweb' ) ) );
	register_block_pattern_category( 'kits-lists', array( 'label' => __( 'Kits: Lists', 'kits-by-wugweb' ) ) );
}
add_action( 'init', 'kits_pattern_categories' );

/**
 * Block styles (Appearance > Editor > Styles > Blocks lists them under each block). Their colours, borders, padding and
 * sizes are in theme.json (styles.blocks.*.variations), so they are edited there; kit.css adds height and hover.
 */
function kits_block_styles() {
	$styles = array(
		// Default (core "fill", shown as Primary) + these three. Size and content are extra CSS classes (docs/buttons.md).
		// Retired names still render: kits-primary (= Brand), kits-small (= small Primary), core outline (= Secondary).
		'core/button'          => array(
			'kits-secondary' => __( 'Secondary', 'kits-by-wugweb' ),
			'kits-ghost'     => __( 'Ghost', 'kits-by-wugweb' ),
			'kits-brand'     => __( 'Brand', 'kits-by-wugweb' ),
		),
		// Section and Head band: their padding, background and gap are settings (theme.json styles.blocks.core/group.variations;
		// Appearance > Editor > Styles > Blocks > Group), not CSS. docs/kits-control-map.md.
		'core/group'           => array(
			'kits-card'      => __( 'Card', 'kits-by-wugweb' ),
			'kits-surface'   => __( 'Surface', 'kits-by-wugweb' ),
			'kits-section'   => __( 'Section', 'kits-by-wugweb' ),
			'kits-head-band' => __( 'Head band', 'kits-by-wugweb' ),
		),
		'core/separator'       => array(
			'kits-hairline' => __( 'Hairline', 'kits-by-wugweb' ),
		),
		'core/paragraph'       => array(
			'kits-label' => __( 'Label', 'kits-by-wugweb' ),
			'kits-note'  => __( 'Note', 'kits-by-wugweb' ),
		),
		'core/list'            => array(
			'kits-sources' => __( 'Sources', 'kits-by-wugweb' ),
		),
		// A menu link with a "Beta" badge after its label (kits_nav_badge below; kit-primitives.css section 6).
		'core/navigation-link' => array(
			'kits-beta' => __( 'Beta', 'kits-by-wugweb' ),
		),
	);
	foreach ( $styles as $block => $list ) {
		foreach ( $list as $name => $label ) {
			register_block_style(
				$block,
				array(
					'name'  => $name,
					'label' => $label,
				)
			);
		}
	}
}
add_action( 'init', 'kits_block_styles' );

/**
 * Groups saved before the Section and Head band styles existed carry the old classes (kit-section, kit-section-band).
 * They get the matching style before they render (WordPress prints a style's CSS for the blocks that have it), so they
 * follow the Styles settings without a content change. Re-saving such a block with the style picked makes this a no-op.
 */
add_filter(
	'render_block_data',
	function ( $block ) {
		if ( 'core/group' !== ( $block['blockName'] ?? '' ) ) {
			return $block;
		}
		static $on = null;
		if ( null === $on ) {
			$on = 'off' !== ( function_exists( 'kits_option' ) ? kits_option( 'legacy_group_styles' ) : 'on' ); // Theme Options: Old section classes.
		}
		$class   = (string) ( $block['attrs']['className'] ?? '' );
		$classes = preg_split( '/\s+/', $class );
		if ( ! $on || false !== strpos( $class, 'is-style-' ) ) {
			return $block;
		}
		$style = in_array( 'kit-section-band', $classes, true ) ? 'is-style-kits-head-band' : ( in_array( 'kit-section', $classes, true ) ? 'is-style-kits-section' : '' );
		if ( '' !== $style ) {
			$block['attrs']['className'] = trim( $style . ' ' . $class );
		}
		return $block;
	},
	5 // before core's block style variation support (10), which prints the style's CSS.
);

/**
 * The same in the editor (assets/js/legacy-styles.js): older groups get the style when they open, so the editor
 * shows the Styles values on them and the next save keeps the style in the content.
 */
function kits_legacy_styles_editor() {
	if ( 'off' === ( function_exists( 'kits_option' ) ? kits_option( 'legacy_group_styles' ) : 'on' ) ) {
		return;
	}
	$f = get_template_directory() . '/assets/js/legacy-styles.js';
	wp_enqueue_script( 'kits-legacy-styles', get_template_directory_uri() . '/assets/js/legacy-styles.js', array( 'wp-hooks', 'wp-compose', 'wp-element', 'wp-data', 'wp-block-editor' ), (string) filemtime( $f ), true );
}
add_action( 'enqueue_block_editor_assets', 'kits_legacy_styles_editor' );

/**
 * Badge after a menu label: "Beta" (block style kits-beta). The badge sits inside the link, so the link's name reads
 * "{label}, Beta" (the comma is for screen readers only) and its target size is unchanged.
 *
 * @param string $text Badge text.
 * @return string
 */
function kits_nav_badge( $text ) {
	return '<span class="kit-badge kit-badge--beta"><span class="screen-reader-text">, </span>' . esc_html( $text ) . '</span>';
}

add_filter(
	'render_block_core/navigation-link',
	function ( $html, $block ) {
		if ( false === strpos( (string) ( $block['attrs']['className'] ?? '' ), 'is-style-kits-beta' ) ) {
			return $html;
		}
		return preg_replace( '#(<span class="wp-block-navigation-item__label">.*?</span>)#s', '$1' . kits_nav_badge( __( 'Beta', 'kits-by-wugweb' ) ), $html, 1 );
	},
	10,
	2
);

/**
 * Core Button keeps one built-in style, its default, named Primary; core Outline is replaced by Kits Secondary.
 */
add_filter(
	'block_type_metadata',
	function ( $meta ) {
		if ( 'core/button' === ( $meta['name'] ?? '' ) ) {
			$meta['styles'] = array(
				array(
					'name'      => 'fill',
					'label'     => __( 'Primary', 'kits-by-wugweb' ),
					'isDefault' => true,
				),
			);
		}
		return $meta;
	}
);

/**
 * Link buttons: an absolute link shows the "leaves the site" arrow (kit-primitives.css section 10) unless it points at
 * this site's own host, which is only known here.
 */
function kits_button_own_host_css() {
	$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	if ( '' === $host ) {
		return;
	}
	$host = preg_replace( '/^www\./', '', $host );
	$sel  = array();
	foreach ( array( $host, 'www.' . $host ) as $h ) {
		$sel[] = ':is(a.kit-btn, a.wp-block-button__link, a.wp-element-button)[href^="http"][href*="//' . esc_attr( $h ) . '/"]';
		$sel[] = ':is(a.kit-btn, a.wp-block-button__link, a.wp-element-button)[href^="http"][href$="//' . esc_attr( $h ) . '"]';
	}
	wp_register_style( 'kits-button-host', false, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	wp_enqueue_style( 'kits-button-host' );
	wp_add_inline_style( 'kits-button-host', implode( ',', $sel ) . '{--kit-cta-arrow:var(--kit-ico-arrow-right);--_nudge:translateX(var(--kit-btn-nudge))}' );
}
add_action( 'enqueue_block_assets', 'kits_button_own_host_css' );

/**
 * Button block sidebar: Size, Content and Icon (assets/js/button-options.js), so size and content combine with any style.
 */
function kits_button_editor_options() {
	$f = get_template_directory() . '/assets/js/button-options.js';
	wp_enqueue_script( 'kits-button-options', get_template_directory_uri() . '/assets/js/button-options.js', array( 'wp-hooks', 'wp-compose', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ), (string) filemtime( $f ), true );
	wp_set_script_translations( 'kits-button-options', 'kits-by-wugweb', get_template_directory() . '/languages' ); // so the editor control's strings load from a locale's JSON.
}
add_action( 'enqueue_block_editor_assets', 'kits_button_editor_options' );

/**
 * Theme pattern files are cached by WordPress; refresh the cache when the pattern files of the active theme
 * (and its parent) change.
 */
add_action(
	'init',
	function () {
		$files = array_merge( (array) glob( get_template_directory() . '/patterns/*.php' ), (array) glob( get_stylesheet_directory() . '/patterns/*.php' ) );
		$sig   = md5( implode( ',', array_map( fn( $f ) => $f . ':' . filemtime( $f ), array_unique( $files ) ) ) );
		if ( kits_theme_store( 'kits_pattern_sig' ) !== $sig ) {
			wp_get_theme()->delete_pattern_cache();
			kits_theme_store_set( 'kits_pattern_sig', $sig );
		}
	},
	1
);

/**
 * Banner pattern (kit-banner): shows nothing until it has an image; the first banner on a page loads first.
 */
add_filter(
	'render_block_core/cover',
	function ( $html, $block ) {
		if ( false === strpos( (string) ( $block['attrs']['className'] ?? '' ), 'kit-banner' ) ) {
			return $html;
		}
		if ( empty( $block['attrs']['url'] ) || false === stripos( $html, '<img' ) ) {
			return '';
		}
		static $first = true;
		if ( $first ) {
			$first = false;
			$html  = preg_replace( '/<img(?![^>]*fetchpriority)/', '<img fetchpriority="high"', $html, 1 );
			$html  = str_replace( 'loading="lazy"', 'loading="eager"', $html );
		}
		return $html;
	},
	10,
	2
);

/* ---------- Component styles: one file per component, loaded on the pages that print it ---------- */

/**
 * Component stylesheets a plugin or child theme adds with the kits_component_styles filter, in the order they join
 * the page's stylesheet (registry order). Each entry is handle => args:
 *
 * - 'src'    URL of the file. Leave it out for a handle the plugin registers itself.
 * - 'file'   Path of the file on disk: its version (filemtime) and, when the URL cannot be mapped, the joined copy.
 * - 'deps'   Handles it prints after.
 * - 'when'   Callable, no arguments: true on pages that need the file whatever they print (a template condition,
 *            checked at wp_enqueue_scripts 20), for example 'is_front_page'.
 * - 'blocks' block name => class prefix (or a list of them): a block of that name with a class that starts so flags
 *            the file. Only for stored content that no PHP prints, shown on the page (not excerpts or feeds).
 *
 * A handle with no 'when' loads only on pages whose PHP called kits_use_component_style( $handle ) while it rendered.
 *
 * @return array<string,array> handle => args, normalised.
 */
function kits_component_styles(): array {
	static $list = null;
	if ( null !== $list ) {
		return $list;
	}
	if ( ! kits_component_styles_ready() ) {
		return array(); // Too early for the filter; asked again later.
	}
	$list = array();
	foreach ( (array) apply_filters( 'kits_component_styles', array() ) as $handle => $args ) {
		if ( ! is_string( $handle ) || '' === $handle || ! is_array( $args ) ) {
			continue;
		}
		$args            = wp_parse_args(
			$args,
			array(
				'src'    => '',
				'file'   => '',
				'deps'   => array(),
				'when'   => null,
				'blocks' => array(),
			)
		);
		$args['deps']    = array_values( array_filter( (array) $args['deps'], 'is_string' ) );
		$args['blocks']  = array_filter( array_map( fn( $p ) => array_values( array_filter( (array) $p, fn( $x ) => is_string( $x ) && '' !== $x ) ), (array) $args['blocks'] ) );
		$list[ $handle ] = $args;
	}
	return $list;
}

/**
 * The site's own CSS (assets/site/site.css), when the site has one: rules only this site uses. It joins every page's
 * component file first, before the component files a plugin adds (priority 5), and the editors load it too
 * (kits_setup). The distributed theme has no assets/site/ folder (tools/build-dist.py), so nothing loads there.
 *
 * @param array $styles kits_component_styles entries.
 * @return array
 */
function kits_site_component_style( $styles ) {
	$file = get_template_directory() . '/assets/site/site.css';
	if ( ! is_array( $styles ) || ! is_readable( $file ) ) {
		return $styles;
	}
	return array(
		'kits-site' => array(
			'src'  => get_template_directory_uri() . '/assets/site/site.css',
			'file' => $file,
			'deps' => array( 'kit-components' ),
			'when' => '__return_true',
		),
	) + $styles;
}
add_filter( 'kits_component_styles', 'kits_site_component_style', 5 );

/** Whether init has finished, so every kits_component_styles filter is in place. */
function kits_component_styles_ready(): bool {
	return did_action( 'init' ) && ! doing_action( 'init' );
}

/**
 * The component flags of this request: handles whose component was printed.
 *
 * @param string $add Handle to add; '' only reads.
 * @return array<string,bool>
 */
function kits_component_style_flags( string $add = '' ): array {
	static $set = array();
	if ( '' !== $add ) {
		$set[ $add ] = true;
	}
	return $set;
}

/**
 * Called by the PHP that prints a component: the page loads that component's stylesheet. In a block theme the whole
 * template renders before wp_head, so the file prints in the head with the others. A component first printed after
 * the styles were queued (for example in wp_footer) gets its file at once, which prints in the footer.
 *
 * @param string $handle Handle given in kits_component_styles.
 */
function kits_use_component_style( string $handle ): void {
	if ( '' === $handle || isset( kits_component_style_flags()[ $handle ] ) ) {
		return;
	}
	kits_component_style_flags( $handle );
	if ( did_action( 'kits_component_styles_enqueued' ) && kits_component_style_register( $handle ) ) {
		wp_enqueue_style( $handle );
	}
}

/**
 * Register a component stylesheet from its registry entry, unless that handle is registered already.
 *
 * @param string $handle Handle.
 * @return bool Whether the handle is registered now.
 */
function kits_component_style_register( string $handle ): bool {
	if ( wp_style_is( $handle, 'registered' ) ) {
		return true;
	}
	$s = kits_component_styles()[ $handle ] ?? null;
	if ( ! $s || ! is_string( $s['src'] ) || '' === $s['src'] ) {
		return false;
	}
	$file = (string) $s['file'];
	$ver  = '' !== $file && is_readable( $file ) ? (string) filemtime( $file ) : false;
	return wp_register_style( $handle, $s['src'], $s['deps'], $ver );
}

// Template conditions and flags turn into enqueues once the template has rendered (block themes) and before the joiner (1000).
add_action(
	'wp_enqueue_scripts',
	function () {
		$flags = kits_component_style_flags();
		foreach ( kits_component_styles() as $handle => $s ) {
			$on = isset( $flags[ $handle ] ) || ( is_callable( $s['when'] ) && call_user_func( $s['when'] ) );
			if ( $on && kits_component_style_register( $handle ) ) {
				wp_enqueue_style( $handle );
			}
		}
		do_action( 'kits_component_styles_enqueued' );
	},
	20
);

// Fallback for stored content no PHP prints: a block whose class starts with an entry's prefix flags that entry.
// For the content of the page itself (singular views): excerpts and feeds do not flag.
add_filter(
	'render_block',
	function ( $html, $block ) {
		static $map = null;
		if ( null === $map ) {
			if ( ! kits_component_styles_ready() ) {
				return $html; // Too early: the map is built on a later block.
			}
			$map = array();
			foreach ( kits_component_styles() as $handle => $s ) {
				foreach ( $s['blocks'] as $name => $prefixes ) {
					$map[ $name ][ $handle ] = $prefixes;
				}
			}
		}
		$name = (string) ( $block['blockName'] ?? '' );
		if ( isset( $map[ $name ] ) && ! doing_filter( 'get_the_excerpt' ) && ! is_feed() ) { // Excerpts and feeds do not flag.
			$class = ' ' . (string) ( $block['attrs']['className'] ?? '' );
			foreach ( $map[ $name ] as $handle => $prefixes ) {
				foreach ( $prefixes as $prefix ) {
					if ( preg_match( '/\s' . preg_quote( $prefix, '/' ) . '/', $class ) ) {
						kits_use_component_style( $handle );
						break;
					}
				}
			}
		}
		return $html;
	},
	10,
	2
);

/*
 * Joined stylesheets (the five core files as one, the component files as one per kind of page): Kits Core Performance
 * (plugins/kits-core/includes/joined-css.php) since 18 Nov 2026. Without Kits Core every file loads on its own.
 */

/**
 * Drops the joined stylesheets' entries left in the theme's option from before they moved to Kits Core (18 Nov 2026):
 * kits_bundle_ver, kits_parts_kinds, kits_parts_sweep and kits_parts_ver_*. Runs in wp-admin and writes only when such
 * an entry is there.
 */
function kits_theme_store_tidy() {
	$all = get_option( 'kits_by_wugweb', array() );
	if ( ! is_array( $all ) ) {
		return;
	}
	$keep = array_filter( $all, fn( $k ) => ! preg_match( '/^kits_(?:bundle_ver|parts_kinds|parts_sweep|parts_ver_.*)$/', (string) $k ), ARRAY_FILTER_USE_KEY );
	if ( count( $keep ) !== count( $all ) ) {
		update_option( 'kits_by_wugweb', $keep, true );
	}
}
add_action( 'admin_init', 'kits_theme_store_tidy' );

/**
 * Card (kit-card kit-card--{state}, kit.css "Card states"): one Query Loop item in core blocks. A site adds its own
 * meta row and chips blocks in its lists; these patterns use core's date and category terms.
 *
 * @param string $state block-lg|block-md|block-sm|stacked-lg|stacked-md|stacked-sm.
 * @return string Block markup.
 */
function kits_card_markup( $state = 'block-md' ) {
	$media   = 'stacked-sm' === $state ? '' : '<!-- wp:post-featured-image {"isLink":true,"className":"kit-card-media"} /-->

';
	$summary = in_array( $state, array( 'block-lg', 'block-md', 'stacked-lg' ), true ) ? '

<!-- wp:post-excerpt {"excerptLength":30,"className":"kit-card-sum"} /-->' : '';
	return '<!-- wp:group {"tagName":"article","className":"kit-card kit-card--' . $state . '","layout":{"type":"default"}} -->
<article class="wp-block-group kit-card kit-card--' . $state . '">' . $media . '<!-- wp:group {"className":"kit-card-body","layout":{"type":"default"}} -->
<div class="wp-block-group kit-card-body"><!-- wp:post-date {"className":"kit-card-meta kit-meta-row"} /-->

<!-- wp:post-terms {"term":"category","className":"kit-card-chips kit-tags"} /-->

<!-- wp:post-title {"level":3,"isLink":true,"className":"kit-card-title"} /-->' . $summary . '</div>
<!-- /wp:group --></article>
<!-- /wp:group -->';
}

/** The six card states as patterns (Editor › Patterns › Kits: Lists): a Query Loop of cards in that state. */
add_action(
	'init',
	function () {
		foreach ( array( 'block-lg', 'block-md', 'block-sm', 'stacked-lg', 'stacked-md', 'stacked-sm' ) as $state ) {
			$grid = 0 === strpos( $state, 'block' ) ? '{"type":"grid","columnCount":' . ( 'block-lg' === $state ? 2 : 3 ) . '}' : '{"type":"default"}';
			register_block_pattern(
				'kits-by-wugweb/card-' . $state,
				array(
					'title'      => sprintf( /* translators: %s: card state */ __( 'Card: %s', 'kits-by-wugweb' ), $state ),
					'categories' => array( 'kits-lists' ),
					'blockTypes' => array( 'core/query' ),
					'content'    => '<!-- wp:query {"query":{"perPage":6,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":false},"align":"wide"} -->
<div class="wp-block-query alignwide"><!-- wp:post-template {"layout":' . $grid . '} -->
' . kits_card_markup( $state ) . '
<!-- /wp:post-template --></div>
<!-- /wp:query -->',
				)
			);
		}
	}
);
