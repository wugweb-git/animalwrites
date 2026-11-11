<?php
/**
 * Kits by Wugweb — theme setup.
 *
 * @package kits-by-wugweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Theme supports. Block themes get most supports automatically; these are the extras.
 */
function kits_setup() {
	add_theme_support( 'editor-styles' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'post-thumbnails' );
	add_editor_style( 'assets/css/kits-v42.css' );
	add_editor_style( 'assets/css/kit-tokens.css' );
	add_editor_style( 'assets/css/kits.css' );
	add_editor_style( 'assets/css/kit.css' ); // components and primitives: the editor shows content as the site does
	add_editor_style( 'assets/css/kit-primitives.css' );
	if ( kits_uses_inter_tight() ) {
		add_editor_style( kits_font_url() );
	}
}
add_action( 'after_setup_theme', 'kits_setup' );

/**
 * Inter Tight from Google Fonts (400–800). A child theme that sets its own base font does not load it.
 */
function kits_font_url() {
	return 'https://fonts.googleapis.com/css2?family=Inter+Tight:wght@400;500;600;700;800&display=swap';
}

/** Whether the active base font family is Inter Tight. */
function kits_uses_inter_tight() {
	foreach ( (array) wp_get_global_settings( array( 'typography', 'fontFamilies', 'theme' ) ) as $family ) {
		if ( 'base' === ( $family['slug'] ?? '' ) ) {
			return false !== stripos( (string) ( $family['fontFamily'] ?? '' ), 'Inter Tight' );
		}
	}
	return true;
}

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( kits_uses_inter_tight() ) {
			wp_enqueue_style( 'kits-font', kits_font_url(), array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		}
	},
	5
);
add_filter(
	'wp_resource_hints',
	function ( $urls, $type ) {
		if ( 'preconnect' === $type && kits_uses_inter_tight() ) {
			$urls[] = 'https://fonts.googleapis.com';
			$urls[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' );
		}
		return $urls;
	},
	10,
	2
);

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
		(string) filemtime( get_template_directory() . '/assets/css/kits.css' ) // a new version on every change, so browsers never keep an old copy
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
	wp_enqueue_script( 'kit-interactions', $uri . '/assets/js/kit.js', array(), (string) filemtime( $dir . '/assets/js/kit.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) ); // scroll-in motion: the browser's own (element.animate), no GSAP (owner, 2 Nov 2026)
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
		'core/button'    => array(
			'kits-secondary' => __( 'Secondary', 'kits-by-wugweb' ),
			'kits-ghost'     => __( 'Ghost', 'kits-by-wugweb' ),
			'kits-brand'     => __( 'Brand', 'kits-by-wugweb' ),
		),
		// Section and Head band: their padding, background and gap are settings (theme.json styles.blocks.core/group.variations;
		// Appearance > Editor > Styles > Blocks > Group), not CSS. docs/kits-control-map.md.
		'core/group'     => array(
			'kits-card'      => __( 'Card', 'kits-by-wugweb' ),
			'kits-surface'   => __( 'Surface', 'kits-by-wugweb' ),
			'kits-section'   => __( 'Section', 'kits-by-wugweb' ),
			'kits-head-band' => __( 'Head band', 'kits-by-wugweb' ),
		),
		'core/separator' => array(
			'kits-hairline' => __( 'Hairline', 'kits-by-wugweb' ),
		),
		'core/paragraph' => array(
			'kits-label' => __( 'Label', 'kits-by-wugweb' ),
			'kits-note'  => __( 'Note', 'kits-by-wugweb' ),
		),
		'core/list'      => array(
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
	5 // before core's block style variation support (10), which prints the style's CSS
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
		if ( get_option( 'kits_pattern_sig' ) !== $sig ) {
			wp_get_theme()->delete_pattern_cache();
			update_option( 'kits_pattern_sig', $sig, false );
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

/* ---------- Joined stylesheets ---------- */

/** The Joined stylesheet switch: Theme Options (css_bundle), then the kits_bundle_css filter. */
function kits_bundle_css_switch(): bool {
	return (bool) apply_filters( 'kits_bundle_css', 'off' !== ( function_exists( 'kits_option' ) ? kits_option( 'css_bundle' ) : 'on' ) );
}

/**
 * Stored content hash of a joined file, if its parts are unchanged.
 *
 * The hash names the file, so its address changes only when the CSS does. It is kept with the parts' signature
 * (path, mtime, size), so the parts are read again only when one of them changes (after a deploy, for example).
 *
 * @param string $key Option name.
 * @param string $sig Signature of the parts.
 * @return string Hash, or '' when the parts changed or nothing is stored.
 */
function kits_joined_hash( string $key, string $sig ): string {
	$memo = get_option( $key );
	return is_array( $memo ) && isset( $memo['sig'], $memo['hash'] ) && $memo['sig'] === $sig ? (string) $memo['hash'] : '';
}

/**
 * Store the content hash of a joined file with its parts' signature.
 *
 * @param string $key  Option name.
 * @param string $sig  Signature of the parts.
 * @param string $hash Content hash.
 */
function kits_joined_hash_save( string $key, string $sig, string $hash ): void {
	if ( kits_joined_hash( $key, $sig ) !== $hash ) {
		update_option( $key, compact( 'sig', 'hash' ), true );
	}
}

/**
 * Write a joined file: a temp file in the same folder, then a rename, so a reader never sees half a file.
 * A failed write is remembered for an hour (transient $fail: one per joiner), so pages do not retry on every view.
 *
 * @param string $path Target path.
 * @param string $css  Contents.
 * @param string $fail Transient that remembers a failed write.
 * @return bool Whether the file is in place.
 */
function kits_joined_write( string $path, string $css, string $fail = 'kits_joined_write_fail' ): bool {
	$dir = dirname( $path );
	$tmp = $path . '.' . wp_generate_password( 6, false ) . '.tmp';
	if ( wp_mkdir_p( $dir ) && wp_is_writable( $dir ) && false !== @file_put_contents( $tmp, $css ) && @rename( $tmp, $path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors -- a failure is handled below.
		return true;
	}
	wp_delete_file( $tmp );
	set_transient( $fail, time(), HOUR_IN_SECONDS );
	return false;
}

/**
 * Older versions of one joined file: each is deleted once the version that replaced it has been in place for a day,
 * so cached pages that still link to it keep it that long. A file's mtime is when it was written (or last touched
 * while newest), so the next newer file's mtime is when this one was replaced.
 *
 * @param array<string,int> $files Path => mtime, newest first.
 */
function kits_joined_prune( array $files ): void {
	$paths = array_keys( $files );
	$count = count( $paths );
	for ( $i = 1; $i < $count; $i++ ) {
		if ( $files[ $paths[ $i - 1 ] ] < time() - DAY_IN_SECONDS ) {
			wp_delete_file( $paths[ $i ] );
		}
	}
}

/**
 * One stylesheet instead of five: the Kits files (kits-v42, kit-tokens, kits, kit, kit-primitives) are joined, in the
 * same order, into uploads/kits-cache/kits-{hash}.css. The hash is of the joined CSS, so the address changes only when
 * a file's contents change. The five handles stay registered (child themes depend on them) but print nothing. If the
 * bundle cannot be written, the five files load as before.
 */
function kits_bundle_core_css() {
	$styles  = wp_styles();
	$handles = array( 'kits-v42', 'kit-tokens', 'kits', 'kit-components', 'kit-primitives' );
	$dir     = get_template_directory();
	$uri     = get_template_directory_uri();
	$files   = array();
	foreach ( $handles as $h ) {
		$src = isset( $styles->registered[ $h ] ) ? (string) $styles->registered[ $h ]->src : '';
		if ( ( ! wp_style_is( $h, 'enqueued' ) && ! wp_style_is( $h, 'registered' ) ) || 0 !== strpos( $src, $uri ) || ! empty( $styles->registered[ $h ]->extra['after'] ) || ! empty( $styles->registered[ $h ]->extra['before'] ) ) {
			return; // Not ours, or carries inline CSS: leave the files as they are.
		}
		$files[ $h ] = $dir . substr( strtok( $src, '?' ), strlen( $uri ) );
	}
	$sig   = md5( implode( ',', array_map( fn( $f ) => $f . ':' . ( file_exists( $f ) ? filemtime( $f ) . ':' . filesize( $f ) : 0 ), $files ) ) );
	$up    = wp_upload_dir();
	$cache = trailingslashit( $up['basedir'] ) . 'kits-cache';
	$hash  = kits_joined_hash( 'kits_bundle_ver', $sig );
	if ( '' === $hash || ! file_exists( $cache . '/kits-' . $hash . '.css' ) ) {
		if ( get_transient( 'kits_joined_write_fail' ) ) {
			return;
		}
		$css = '';
		foreach ( $files as $h => $f ) {
			$part = file_exists( $f ) ? (string) file_get_contents( $f ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( '' === $part ) {
				return;
			}
			$css .= "/* {$h} */\n" . $part . "\n";
		}
		$hash = substr( md5( $css ), 0, 12 );
		$path = $cache . '/kits-' . $hash . '.css';
		if ( ! file_exists( $path ) && ! kits_joined_write( $path, $css ) ) {
			return;
		}
		kits_joined_hash_save( 'kits_bundle_ver', $sig, $hash );
		$list = array();
		foreach ( (array) glob( $cache . '/kits-*.css' ) as $f ) {
			$list[ $f ] = (int) filemtime( $f );
		}
		arsort( $list );
		kits_joined_prune( $list );
	}
	$first = $styles->registered[ $handles[0] ];
	wp_register_style( 'kits-bundle', trailingslashit( $up['baseurl'] ) . 'kits-cache/kits-' . $hash . '.css', $first->deps, null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- the file name carries the hash.
	foreach ( $handles as $h ) {
		$styles->registered[ $h ]->src  = false; // An alias: prints nothing, keeps its place for dependants.
		$styles->registered[ $h ]->deps = array( 'kits-bundle' );
	}
}

/**
 * Folder of the joined component stylesheets: {set}-{hash}.css. Its own folder, so the Kits clean-up never sees it.
 *
 * @param bool $create Passed to wp_upload_dir(): create the month folder.
 * @return array{path:string,url:string}
 */
function kits_parts_bundle_dir( bool $create = false ): array {
	$up = wp_upload_dir( null, $create );
	return array(
		'path' => trailingslashit( $up['basedir'] ) . 'kits-cache/parts',
		'url'  => trailingslashit( $up['baseurl'] ) . 'kits-cache/parts',
	);
}

/**
 * The joined component files on disk, by set.
 *
 * @param string $dir Folder.
 * @return array<string,array<string,int>> set => [ path => mtime ], newest first.
 */
function kits_parts_bundle_sets( string $dir ): array {
	$sets = array();
	foreach ( (array) glob( $dir . '/*.css' ) as $f ) {
		if ( preg_match( '/^([0-9a-f]{10})-[0-9a-f]{10}\.css$/', basename( (string) $f ), $m ) ) {
			$sets[ $m[1] ][ $f ] = (int) filemtime( $f );
		}
	}
	foreach ( $sets as &$files ) {
		arsort( $files );
	}
	return $sets;
}

/**
 * The joined sets the cap governs: every set except the kinds' own (a kind's set is named after md5( 'kind:' . kind ),
 * for each kind in the kits_parts_kinds option). Kind sets are always written; only sets keyed on a page's own
 * components (a kits_parts_page_kind filter that returned '') count against kits_parts_bundle_cap().
 *
 * @param array $sets kits_parts_bundle_sets().
 * @return array The other sets, keyed as given.
 */
function kits_parts_bundle_other_sets( array $sets ): array {
	$kinds = get_option( 'kits_parts_kinds', array() );
	foreach ( array_keys( is_array( $kinds ) ? $kinds : array() ) as $kind ) {
		unset( $sets[ substr( md5( 'kind:' . $kind ), 0, 10 ) ] );
	}
	return $sets;
}

/**
 * Clean-up of the joined component files, on each rebuild and when the cap is reached.
 *
 * - Any file no page has used for kits_parts_bundle_max_age() goes (files in use are touched hourly): an older version
 *   of a set once no cached page can still link to it, a whole set once no page makes it any more.
 * - Temp files left by a failed write go after an hour.
 *
 * @param string $dir Folder.
 */
function kits_parts_bundle_clean( string $dir ): void {
	clearstatcache();
	$old = time() - kits_parts_bundle_max_age();
	foreach ( kits_parts_bundle_sets( $dir ) as $set => $list ) {
		$left = count( $list );
		foreach ( $list as $f => $m ) {
			if ( $m < $old ) {
				wp_delete_file( $f );
				--$left;
			}
		}
		if ( ! $left ) {
			delete_option( 'kits_parts_ver_' . $set );
		}
	}
	foreach ( (array) glob( $dir . '/*.tmp' ) as $t ) {
		if ( filemtime( $t ) < time() - HOUR_IN_SECONDS ) {
			wp_delete_file( $t );
		}
	}
}

/**
 * Sweep of the joined component files after a release, for a plugin to start from its deploy step. Files no page has
 * used since now are deleted once $delay has passed, so sets no page uses any more stop counting against the cap.
 * Each page served before then touches the file it links to, so pages a cache made after the release keep theirs;
 * $delay must outlast the longest time a cache keeps a page made before the release.
 *
 * @param int $delay Seconds before the sweep runs.
 */
function kits_parts_bundle_sweep_start( int $delay ): void {
	delete_option( 'kits_parts_kinds' ); // Each kind's list starts again from what the release's pages print.
	update_option(
		'kits_parts_sweep',
		array(
			'at'  => time(),
			'run' => time() + max( 0, $delay ),
		),
		true
	);
}

/**
 * The sweep kits_parts_bundle_sweep_start() asked for, once its time has come: files untouched since the release go,
 * with the version option of a set left with no file.
 *
 * @param string $dir Folder.
 */
function kits_parts_bundle_sweep( string $dir ): void {
	$sweep = get_option( 'kits_parts_sweep' );
	if ( ! is_array( $sweep ) || time() < (int) ( $sweep['run'] ?? 0 ) ) {
		return;
	}
	delete_option( 'kits_parts_sweep' );
	clearstatcache();
	foreach ( kits_parts_bundle_sets( $dir ) as $set => $list ) {
		$left = count( $list );
		foreach ( $list as $f => $m ) {
			if ( $m < (int) $sweep['at'] ) {
				wp_delete_file( $f );
				--$left;
			}
		}
		if ( ! $left ) {
			delete_option( 'kits_parts_ver_' . $set );
		}
	}
}

/**
 * How long a joined component file no page has used is kept (kits_parts_bundle_max_age filter): 7 days, plus 3 hours
 * so that a page a cache made just before the file's last use (the touch is hourly) and kept for 7 days, with a CDN
 * copy on top, never links to a removed file. Never less than that: raise it if a page cache keeps pages longer.
 */
function kits_parts_bundle_max_age(): int {
	$min = 7 * DAY_IN_SECONDS + 3 * HOUR_IN_SECONDS;
	return max( $min, (int) apply_filters( 'kits_parts_bundle_max_age', $min ) );
}

/**
 * The kind of page this is, for the joined component file: pages of one kind share one file, which holds every
 * component that kind has needed so far (kits_parts_bundle_members). A filter returning '' keys the file on this page's
 * own components instead.
 * Filter: kits_parts_page_kind (a site can split a kind, e.g. one of its own views).
 */
function kits_parts_page_kind(): string {
	$kind = '';
	if ( is_front_page() ) {
		$kind = 'front';
	} elseif ( is_home() ) {
		$kind = 'posts';
	} elseif ( is_page() ) {
		$tpl  = (string) get_page_template_slug();
		$kind = 'page' . ( '' !== $tpl && 'default' !== $tpl ? '-' . $tpl : '' );
	} elseif ( is_singular() ) {
		$kind = 'single-' . get_post_type();
	} elseif ( is_post_type_archive() ) {
		$kind = 'archive-' . (string) get_query_var( 'post_type' );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();
		$kind = 'term-' . ( $term instanceof WP_Term ? $term->taxonomy : 'any' );
	} elseif ( is_author() ) {
		$kind = 'author';
	} elseif ( is_date() ) {
		$kind = 'date';
	} elseif ( is_search() ) {
		$kind = 'search';
	} elseif ( is_404() ) {
		$kind = '404';
	} else {
		$kind = 'other'; // Feeds, embeds and routes a plugin adds: one shared file.
	}
	$kind = (string) apply_filters( 'kits_parts_page_kind', $kind );
	return (string) preg_replace( '/[^a-z0-9_-]/', '', strtolower( $kind ) );
}

/**
 * The components of this kind of page's joined file: the ones this page needs, plus every one an earlier page of the
 * kind needed (kept in the kits_parts_kinds option, in registry order). A kind's list grows until the next release
 * (kits_parts_bundle_sweep_start empties it, so a component no page prints any more drops out), so its file changes
 * only when the kind first meets a component; registry entries that are gone drop out at once. Two pages adding at
 * the same moment can lose one addition; the next page that needs it adds it again.
 *
 * @param string $kind     Page kind ('' = none).
 * @param array  $members  Handles this page needs, in registry order.
 * @param array  $registry Component registry.
 * @return array Handles, in registry order.
 */
function kits_parts_bundle_members( string $kind, array $members, array $registry ): array {
	if ( '' === $kind ) {
		return $members;
	}
	$kinds = get_option( 'kits_parts_kinds', array() );
	$kinds = is_array( $kinds ) ? $kinds : array();
	$known = array_values( array_filter( (array) ( $kinds[ $kind ] ?? array() ), fn( $h ) => is_string( $h ) && isset( $registry[ $h ] ) ) );
	$all   = array_values( array_filter( array_keys( $registry ), fn( $h ) => in_array( $h, $known, true ) || in_array( $h, $members, true ) ) );
	if ( $all !== $known ) {
		$kinds[ $kind ] = $all;
		update_option( 'kits_parts_kinds', $kinds, true );
	}
	return $all;
}

/** Most joined component files kept (Kits → Settings → Performance → Joined files cap; kits_parts_bundle_cap filter). */
function kits_parts_bundle_cap(): int {
	$cap = function_exists( 'kits_option' ) ? (int) kits_option( 'css_parts_cap' ) : 0;
	return max( 1, (int) apply_filters( 'kits_parts_bundle_cap', $cap > 0 ? $cap : 50 ) );
}

/**
 * Path on disk of a registered stylesheet address, if it is a .css file of this site's plugins, themes or wp-content.
 *
 * @param string $src Registered src.
 */
function kits_style_src_file( string $src ): string {
	$bare = fn( $u ) => (string) preg_replace( '#^(?:https?:)?//#i', '//', (string) $u );
	$src  = $bare( (string) preg_replace( '/[?#].*$/', '', $src ) );
	foreach ( array(
		WP_PLUGIN_DIR    => plugins_url(),
		get_theme_root() => get_theme_root_uri(),
		WP_CONTENT_DIR   => content_url(),
	) as $dir => $url ) {
		$url = trailingslashit( $bare( untrailingslashit( $url ) ) );
		if ( 0 !== strpos( $src, $url ) ) {
			continue;
		}
		$rel = rawurldecode( substr( $src, strlen( $url ) ) );
		if ( '.css' !== substr( $rel, -4 ) || preg_match( '#(^|/)\.\.(/|$)#', $rel ) ) {
			return '';
		}
		return trailingslashit( $dir ) . $rel;
	}
	return '';
}

/**
 * Relative url() addresses in a moved stylesheet, made root-relative against the folder it came from (no scheme or
 * host, so the joined file is the same whichever address the site answers on).
 *
 * @param string $css  CSS.
 * @param string $base URL of the file's folder.
 */
function kits_css_absolute_urls( string $css, string $base ): string {
	$base = trailingslashit( wp_make_link_relative( $base ) );
	return (string) preg_replace_callback(
		'/url\(\s*([\'"]?)([^\'")]+)\1\s*\)/i',
		function ( $m ) use ( $base ) {
			$u = trim( $m[2] );
			if ( preg_match( '#^(?:[a-z][a-z0-9+.-]*:|/|\#)#i', $u ) ) {
				return $m[0]; // Data:, http(s):, root-relative and fragment addresses stay.
			}
			return 'url(' . $m[1] . $base . $u . $m[1] . ')';
		},
		$css
	);
}

/**
 * Handles that will print: the queue (or the given handles) and everything they depend on.
 *
 * @param WP_Styles  $styles Styles.
 * @param array|null $from   Handles to start from; null for the queue.
 * @return array<string,bool>
 */
function kits_styles_needed( WP_Styles $styles, ?array $from = null ): array {
	$seen = array();
	$todo = array_map( fn( $h ) => explode( '?', (string) $h )[0], null === $from ? (array) $styles->queue : $from );
	while ( $todo ) {
		$h = array_shift( $todo );
		if ( isset( $seen[ $h ] ) || ! isset( $styles->registered[ $h ] ) ) {
			continue;
		}
		$seen[ $h ] = true;
		$todo       = array_merge( $todo, (array) $styles->registered[ $h ]->deps );
	}
	return $seen;
}

/**
 * One component stylesheet per page: the component stylesheets this page loads (kits_component_styles, in
 * registry order) are joined into uploads/kits-cache/parts/{set}-{hash}.css. {set} hashes the kind of page
 * (kits_parts_page_kind), so pages of one kind share one file holding every component that kind has needed; {hash}
 * hashes the joined CSS, so the address changes only when the CSS does. The handles stay registered as aliases that print
 * nothing, with deps = [ kits-parts ], so the joined file prints where the first of them would.
 *
 * The separate files load instead when one of them carries inline CSS, another media, an RTL copy or an @import, when
 * one of their outside deps is missing or needs one of them, when the file cannot be written, or when the cap on
 * joined files is reached for a set that has none yet.
 */
function kits_bundle_component_css() {
	$registry = kits_component_styles();
	if ( ! $registry ) {
		return;
	}
	$styles  = wp_styles();
	$needed  = kits_styles_needed( $styles );
	$members = array_values( array_filter( array_keys( $registry ), fn( $h ) => isset( $needed[ $h ] ) ) );
	if ( ! $members ) {
		return;
	}
	$kind = kits_parts_page_kind();
	foreach ( kits_parts_bundle_members( $kind, $members, $registry ) as $h ) {
		if ( ! in_array( $h, $members, true ) && kits_component_style_register( $h ) ) {
			$members[] = $h; // Another page of this kind needed it: in the file too, so the kind shares one file.
		}
	}
	$members = array_values( array_filter( array_keys( $registry ), fn( $h ) => in_array( $h, $members, true ) ) );
	$deps = array();
	foreach ( $members as $h ) {
		foreach ( (array) $styles->registered[ $h ]->deps as $d ) {
			if ( ! in_array( $d, $members, true ) && ! in_array( $d, $deps, true ) ) {
				if ( ! isset( $styles->registered[ $d ] ) ) {
					return; // A missing dep would stop the joined file printing at all: separate files.
				}
				$deps[] = $d;
			}
		}
	}
	if ( array_intersect( $members, array_keys( kits_styles_needed( $styles, $deps ) ) ) ) {
		return; // An outside dep that needs a member would make a loop through kits-parts.
	}
	$files = array();
	$bases = array();
	foreach ( $members as $h ) {
		$o = $styles->registered[ $h ];
		if ( ! is_string( $o->src ) || '' === $o->src || ! empty( $o->extra['after'] ) || ! empty( $o->extra['before'] ) || ! empty( $o->extra['conditional'] ) || ! empty( $o->extra['alt'] ) || ( is_rtl() && ! empty( $o->extra['rtl'] ) ) || ! in_array( (string) $o->args, array( '', 'all' ), true ) ) {
			return; // Inline CSS, a condition, another media or an RTL copy: leave the files as they are.
		}
		$f = kits_style_src_file( $o->src );
		if ( '' === $f || ! is_readable( $f ) ) {
			$f = (string) $registry[ $h ]['file'];
		}
		if ( '' === $f || ! is_readable( $f ) ) {
			return;
		}
		$files[ $h ] = $f;
		$bases[ $h ] = dirname( (string) preg_replace( '/[?#].*$/', '', $o->src ) );
	}
	$set   = substr( md5( '' !== $kind ? 'kind:' . $kind : implode( ',', $members ) ), 0, 10 );
	$sig   = md5( implode( ',', array_map( fn( $f ) => $f . ':' . filemtime( $f ) . ':' . filesize( $f ), $files ) ) );
	$dir   = kits_parts_bundle_dir();
	$key   = 'kits_parts_ver_' . $set;
	$hash  = kits_joined_hash( $key, $sig );
	$path  = $dir['path'] . '/' . $set . '-' . $hash . '.css';
	$clean = false;
	if ( '' === $hash || ! file_exists( $path ) ) {
		if ( get_transient( 'kits_parts_write_fail' ) ) {
			return; // Uploads could not be written lately: separate files, no retry on every view.
		}
		$sets = kits_parts_bundle_sets( $dir['path'] );
		// A kind's set is always written (kinds are few, set by the page types); the cap holds back only sets keyed on a
		// page's own components, from a kits_parts_page_kind filter that returned ''.
		$capped = '' === $kind && ! isset( $sets[ $set ] ) && count( kits_parts_bundle_other_sets( $sets ) ) >= kits_parts_bundle_cap();
		if ( $capped && false === get_transient( 'kits_parts_cap_clean' ) ) {
			set_transient( 'kits_parts_cap_clean', 1, HOUR_IN_SECONDS ); // At most once an hour: unused sets go, then count again.
			kits_parts_bundle_clean( $dir['path'] );
			$sets = kits_parts_bundle_sets( $dir['path'] );
		}
		if ( $capped && count( kits_parts_bundle_other_sets( $sets ) ) >= kits_parts_bundle_cap() ) {
			if ( false === get_transient( 'kits_parts_cap_hit' ) ) {
				set_transient( 'kits_parts_cap_hit', time(), WEEK_IN_SECONDS ); // Shown on Welcome and Site check.
			}
			return; // Cap reached: this set loads its separate files, nothing new is written.
		}
		$css = '';
		foreach ( $files as $h => $f ) {
			$part = file_get_contents( $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( false === $part || preg_match( '/@import\b/i', $part ) ) {
				return; // An @import must open a stylesheet; joined, it would be dropped.
			}
			$part = (string) preg_replace( '/@charset\s+[^;]+;/i', '', $part );
			$css .= '/* ' . str_replace( '*/', '', $h ) . " */\n" . kits_css_absolute_urls( $part, $bases[ $h ] ) . "\n";
		}
		$hash = substr( md5( $css ), 0, 10 );
		$path = $dir['path'] . '/' . $set . '-' . $hash . '.css';
		if ( ! file_exists( $path ) && ! kits_joined_write( $path, $css, 'kits_parts_write_fail' ) ) {
			return;
		}
		kits_joined_hash_save( $key, $sig, $hash );
		$clean = true;
	}
	clearstatcache( true, $path );
	$mtime = file_exists( $path ) ? (int) filemtime( $path ) : 0;
	if ( ! $mtime ) {
		return; // Removed since the check above: separate files for this view.
	}
	$sweep = get_option( 'kits_parts_sweep' );
	$since = is_array( $sweep ) ? (int) ( $sweep['at'] ?? 0 ) : 0;
	if ( ( $mtime < time() - HOUR_IN_SECONDS || $mtime < $since ) && wp_is_writable( $path ) ) {
		touch( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- in use: the unused-age clean-up and the release sweep keep it.
	}
	if ( $since ) {
		kits_parts_bundle_sweep( $dir['path'] );
	}
	if ( $clean ) {
		kits_parts_bundle_clean( $dir['path'] );
	}
	wp_register_style( 'kits-parts', $dir['url'] . '/' . $set . '-' . $hash . '.css', $deps, null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- the file name carries the hash.
	foreach ( $members as $h ) {
		$styles->registered[ $h ]->src  = false; // An alias: prints nothing, keeps its place for dependants.
		$styles->registered[ $h ]->deps = array( 'kits-parts' );
	}
}

/**
 * The joined component files against the cap, for Kits → Home and a site's own checks. The kinds' sets are counted
 * apart: the cap, the near-the-cap warning and the cap-reached notice concern only the other sets.
 *
 * @return array{on:bool,kinds:int,count:int,cap:int,warn:bool,hit:int,fail:int,text:string,fix:string}
 */
function kits_parts_bundle_status(): array {
	$on    = kits_bundle_css_switch();
	$cap   = kits_parts_bundle_cap();
	$sets  = kits_parts_bundle_sets( kits_parts_bundle_dir()['path'] );
	$count = count( kits_parts_bundle_other_sets( $sets ) ); // What the cap governs.
	$kinds = count( $sets ) - $count;
	$hit   = (int) get_transient( 'kits_parts_cap_hit' );
	$fail  = (int) get_transient( 'kits_parts_write_fail' );
	if ( $hit && $count < $cap ) {
		delete_transient( 'kits_parts_cap_hit' ); // Room again (cap raised or files removed).
		$hit = 0;
	}
	$warn = $on && ( $fail > 0 || $hit > 0 || $count >= (int) ceil( $cap * 0.8 ) );
	$text = $on ? sprintf( '%1$d page kinds · %2$d of %3$d other sets', $kinds, $count, $cap ) : 'Joined stylesheet is off: every component file loads on its own';
	$fix  = '';
	if ( $warn && $fail ) {
		$fix = 'uploads/kits-cache/parts/ could not be written on ' . wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $fail ) . ': pages load separate files. Make the folder writable; the next try is an hour after the failure. ';
	}
	if ( $warn && ( $hit || $count >= (int) ceil( $cap * 0.8 ) ) ) {
		$fix .= $hit ? 'The cap was reached on ' . wp_date( get_option( 'date_format' ), $hit ) . ': a page outside the page kinds with a new set of components loads separate files. ' : 'Near the cap: once it is reached, a page outside the page kinds with a new set of components loads separate files. ';
		$fix .= 'Raise the cap in Kits → Settings → Performance (Joined files cap), or empty uploads/kits-cache/parts/ (the files are made again as pages are visited).';
	}
	return compact( 'on', 'kinds', 'count', 'cap', 'warn', 'hit', 'fail', 'text', 'fix' );
}

// Kits → Welcome: one status row, shown once a plugin registers component styles.
add_filter(
	'kits_status_rows',
	function ( $rows ) {
		if ( ! kits_component_styles() ) {
			return $rows;
		}
		$s      = kits_parts_bundle_status();
		$rows[] = array( 'Joined component files', function_exists( 'kits_status_cell' ) ? kits_status_cell( $s['text'], $s['fix'] ) : esc_html( trim( $s['text'] . ' ' . $s['fix'] ) ) );
		return $rows;
	}
);

/*
 * Both joiners run late (1000), after every plugin queued its files and the component flags became enqueues (20).
 * Turned off together in Theme Options (Joined stylesheet) or with add_filter( 'kits_bundle_css', '__return_false' ).
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( is_admin() || ! kits_bundle_css_switch() ) {
			return;
		}
		kits_bundle_core_css();
		kits_bundle_component_css();
	},
	1000
);

/**
 * Card (kit-card kit-card--{state}, kit.css "Card states"): one Query Loop item in core blocks. A site adds its own
 * meta row and chips blocks in its lists; these patterns use core's date and category terms.
 *
 * @param string $state block-lg|block-md|block-sm|stacked-lg|stacked-md|stacked-sm.
 * @return string Block markup.
 */
function kits_card_markup( $state = 'block-md' ) {
	$media   = 'stacked-sm' === $state ? '' : '<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"4/3","className":"kit-card-media"} /-->

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
