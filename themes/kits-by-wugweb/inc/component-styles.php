<?php
/**
 * Kits by Wugweb: component styles, one file per component, loaded on the pages that print it (kits_component_styles).
 *
 * @package kits-by-wugweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
			'deps' => array( 'kits-components' ),
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
