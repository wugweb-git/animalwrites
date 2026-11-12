<?php
/**
 * Kits → Help → Design system: a read-only index of the design system, generated from kits-registry.json in the Kits
 * master theme, in a child theme when there is one, and in active plugins that ship one. Every component, block,
 * pattern and menu type with its states, its accessibility record and its SEO output; where each is used (from the
 * theme and plugin files); the colour pairs with their contrast worked out from the live values (colours, fonts and
 * sizes from Styles, other tokens from the theme's CSS); and the tokens themselves. Classes in use with no registry
 * entry are listed at the top (the deploy check fails on them too). Nothing here changes the site.
 *
 * Every state of every component is drawn on the states sheet (/kits-demo/, private).
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Folders that carry the site's design system files: the Kits master theme, a child theme when there is one, then
 * every active plugin that ships a kits-registry.json in its own folder. With no child theme the theme folder is
 * listed once.
 *
 * @return array Folder paths, master first.
 */
function kits_system_dirs() {
	static $dirs = null;
	if ( null !== $dirs ) {
		return $dirs;
	}
	$dirs = array_values( array_unique( array( get_template_directory(), get_stylesheet_directory() ) ) );

	$files = function_exists( 'wp_get_active_and_valid_plugins' ) ? (array) wp_get_active_and_valid_plugins() : array(); // Full paths.
	if ( is_multisite() && function_exists( 'wp_get_active_network_plugins' ) ) {
		$files = array_merge( $files, (array) wp_get_active_network_plugins() );
	}
	$root = realpath( WP_PLUGIN_DIR );
	if ( false === $root ) {
		$dirs = array_values( (array) apply_filters( 'kits_system_dirs', $dirs ) );
		return $dirs;
	}
	foreach ( array_unique( $files ) as $file ) {
		$dir  = dirname( (string) $file );
		$real = realpath( $dir );
		if ( false === $real || $real === $root || 0 !== strpos( $real, $root . DIRECTORY_SEPARATOR ) ) { // A single-file plugin has no folder of its own; nothing outside the plugins folder.
			continue;
		}
		if ( is_readable( $dir . '/kits-registry.json' ) && ! in_array( $dir, $dirs, true ) ) {
			$dirs[] = $dir;
		}
	}
	/**
	 * Filters the folders with design system files, e.g. a site plugin that carries the theme's files in a
	 * folder of its own.
	 *
	 * @param string[] $dirs Folder paths, master first.
	 */
	$dirs = array_values( (array) apply_filters( 'kits_system_dirs', $dirs ) );
	return $dirs;
}

/** The registry: master first, then the child theme's entries (if any), then those of active plugins. */
function kits_registry() {
	static $reg = null;
	if ( null !== $reg ) {
		return $reg;
	}
	$reg = array( 'items' => array(), 'nav' => array(), 'nav_status' => array() );
	foreach ( kits_system_dirs() as $dir ) {
		$f = $dir . '/kits-registry.json';
		$j = is_readable( $f ) ? json_decode( (string) file_get_contents( $f ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! is_array( $j ) ) {
			continue;
		}
		foreach ( (array) ( $j['items'] ?? array() ) as $it ) {
			$it['owner']    = $j['owner'] ?? '';
			$reg['items'][] = $it;
		}
		$reg['nav']        = array_merge( $reg['nav'], (array) ( $j['nav'] ?? array() ) );
		$reg['nav_status'] = array_merge( $reg['nav_status'], (array) ( $j['nav_status'] ?? array() ) );
	}
	return $reg;
}

/**
 * The values Appearance → Editor → Styles owns (colour palette, font families, font sizes, spacing sizes, radius
 * sizes and the rest), read from the preset custom properties WordPress itself prints in :root / body for the merged
 * theme.json, style variation and the site's own Styles. Name (without the leading --) => value; later rules win.
 */
function kits_styles_values() {
	static $vals = null;
	if ( null !== $vals ) {
		return $vals;
	}
	$vals = array();
	if ( ! function_exists( 'wp_get_global_stylesheet' ) ) {
		return $vals;
	}
	$css = (string) wp_get_global_stylesheet( array( 'variables' ) );
	if ( ! preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER ) ) {
		return $vals;
	}
	foreach ( $rules as $rule ) {
		$selectors = array_map( 'trim', explode( ',', $rule[1] ) );
		if ( ! array_intersect( $selectors, array( ':root', 'body' ) ) ) { // Presets scoped to one block are not site-wide.
			continue;
		}
		if ( preg_match_all( '/--(wp--preset--[a-z0-9-]+)\s*:\s*([^;]+);?/i', $rule[2], $decls, PREG_SET_ORDER ) ) {
			foreach ( $decls as $d ) {
				$vals[ strtolower( $d[1] ) ] = trim( $d[2] );
			}
		}
	}
	return $vals;
}

/**
 * Site-wide choices in Styles (text, background and link colour, body font and size): label => value, with
 * var:preset|… references written as the custom property they stand for.
 */
function kits_styles_root() {
	$out = array();
	if ( ! function_exists( 'wp_get_global_styles' ) ) {
		return $out;
	}
	$rows = array(
		'Text colour'       => array( 'color', 'text' ),
		'Background colour' => array( 'color', 'background' ),
		'Link colour'       => array( 'elements', 'link', 'color', 'text' ),
		'Body font'         => array( 'typography', 'fontFamily' ),
		'Body font size'    => array( 'typography', 'fontSize' ),
	);
	foreach ( $rows as $label => $path ) {
		$v = wp_get_global_styles( $path );
		if ( ! is_string( $v ) || '' === $v ) {
			continue;
		}
		if ( preg_match( '/^var:preset\|([a-z0-9-]+)\|([a-z0-9-]+)$/i', $v, $m ) ) {
			$v = 'var(--wp--preset--' . $m[1] . '--' . $m[2] . ')';
		}
		$out[ $label ] = $v;
	}
	return $out;
}

/**
 * Custom properties declared in :root of the token stylesheets (base values), then the values from Styles on top,
 * name => value. Kits tokens that point at a Styles preset (var(--wp--preset--…, fallback)) so resolve to the colour
 * or size the site uses now, not the fallback written in the CSS.
 */
function kits_token_values() {
	$vals = array();
	$css  = array( get_template_directory() . '/assets/css/kits-v42.css', get_template_directory() . '/assets/css/kit-tokens.css' );
	foreach ( kits_system_dirs() as $dir ) { // The child theme's or the site plugin's own tokens.
		$css[] = $dir . '/assets/css/tokens.css';
	}
	foreach ( array_unique( $css ) as $f ) {
		$s = is_readable( $f ) ? (string) file_get_contents( $f ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( preg_match( '/:root\s*\{(.*?)\n\}/s', $s, $m ) && preg_match_all( '/--([a-z0-9-]+):\s*([^;]+);/', $m[1], $mm, PREG_SET_ORDER ) ) {
			foreach ( $mm as $x ) {
				$vals[ $x[1] ] = trim( $x[2] );
			}
		}
	}
	return array_merge( $vals, kits_styles_values() );
}

/**
 * Where a token's value comes from: the Styles preset it reaches through var(), or the theme's CSS.
 *
 * @param string $name Token name without the leading dashes.
 * @param array  $vals Every token's value, keyed the same way.
 */
function kits_token_source( $name, $vals ) {
	$styles = kits_styles_values();
	$seen   = array();
	for ( $i = 0; $i < 10 && isset( $vals[ $name ] ) && ! isset( $seen[ $name ] ); $i++ ) {
		if ( isset( $styles[ $name ] ) ) {
			return 'Styles (<code>--' . esc_html( $name ) . '</code>)';
		}
		$seen[ $name ] = true;
		$ref           = kits_css_var_find( $vals[ $name ] );
		while ( null !== $ref && ! isset( $vals[ $ref['name'] ] ) && null !== $ref['fallback'] && null !== kits_css_var_find( $ref['fallback'] ) ) {
			$ref = kits_css_var_find( $ref['fallback'] ); // An unset property: the value comes from its fallback.
		}
		if ( null === $ref ) {
			break;
		}
		$name = $ref['name'];
	}
	return isset( $styles[ $name ] ) ? 'Styles (<code>--' . esc_html( $name ) . '</code>)' : 'the theme\'s CSS (not in Styles)';
}

/**
 * The first var(--name[, fallback]) in a CSS value, with brackets counted so a fallback such as
 * clamp(1rem, 2vw, 2rem) or var(--other) is kept whole.
 *
 * @param string $v CSS value.
 * @return array|null 'whole' (the var() text), 'name' (without the dashes), 'fallback' (string or null); null if none.
 */
function kits_css_var_find( $v ) {
	$v = (string) $v;
	if ( ! preg_match( '/var\(\s*--([a-z0-9-]+)\s*/i', $v, $m, PREG_OFFSET_CAPTURE ) ) {
		return null;
	}
	$start = $m[0][1];
	$depth = 0;
	$comma = null;
	$len   = strlen( $v );
	for ( $j = $start + 3; $j < $len; $j++ ) { // From the "(" of var(.
		$c = $v[ $j ];
		if ( '(' === $c ) {
			++$depth;
		} elseif ( ')' === $c ) {
			--$depth;
			if ( 0 === $depth ) {
				break;
			}
		} elseif ( ',' === $c && 1 === $depth && null === $comma ) {
			$comma = $j;
		}
	}
	if ( $j >= $len ) { // No closing bracket.
		return null;
	}
	return array(
		'whole'    => substr( $v, $start, $j - $start + 1 ),
		'name'     => $m[1][0],
		'fallback' => null === $comma ? null : trim( substr( $v, $comma + 1, $j - $comma - 1 ) ),
	);
}

/**
 * A token's value with var() references followed.
 *
 * @param string $name Token name without the leading dashes.
 * @param array  $vals Every token's value, keyed the same way.
 */
function kits_token_resolve( $name, $vals ) {
	$v = $vals[ $name ] ?? '';
	for ( $i = 0; $i < 10; $i++ ) {
		$ref = kits_css_var_find( $v );
		if ( null === $ref ) {
			break;
		}
		$v = str_replace( $ref['whole'], $vals[ $ref['name'] ] ?? ( $ref['fallback'] ?? '' ), $v );
	}
	return strtolower( trim( $v ) );
}

/** Contrast ratio of two #rrggbb colours, or 0 when either is not a plain hex colour. */
function kits_contrast( $a, $b ) {
	$lum = function ( $hex ) {
		if ( ! preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $hex ) ) {
			return null;
		}
		$hex = 4 === strlen( $hex ) ? '#' . $hex[1] . $hex[1] . $hex[2] . $hex[2] . $hex[3] . $hex[3] : $hex;
		$c   = array_map( fn( $i ) => hexdec( substr( $hex, $i, 2 ) ) / 255, array( 1, 3, 5 ) );
		$c   = array_map( fn( $x ) => $x <= 0.03928 ? $x / 12.92 : ( ( $x + 0.055 ) / 1.055 ) ** 2.4, $c );
		return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
	};
	$la = $lum( $a );
	$lb = $lum( $b );
	if ( null === $la || null === $lb ) {
		return 0;
	}
	return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
}

/**
 * A file's path relative to wp-content (plugins/…, themes/…), built from the plugins, themes and content folders (also
 * through a symlink, each plugin and theme folder by its real path as well), so the screen never prints the server's own
 * folders. A file elsewhere in the site: relative to the site's folder (ABSPATH); outside it: its name only.
 *
 * @param string $path Full path.
 */
function kits_content_path( $path ) {
	static $roots = null;
	if ( null === $roots ) {
		$roots = array();
		$list  = array();
		// Each plugin and theme folder by its own real path too: a folder linked in from a deploy folder outside
		// wp-content still reads plugins/…, themes/….
		foreach ( array( array( WP_PLUGIN_DIR, 'plugins/' ), array( get_theme_root(), 'themes/' ) ) as list( $base, $name ) ) {
			foreach ( (array) glob( trailingslashit( $base ) . '*', GLOB_ONLYDIR ) as $sub ) {
				$list[] = array( (string) $sub, $name . wp_basename( (string) $sub ) . '/' );
			}
		}
		$list[] = array( WP_PLUGIN_DIR, 'plugins/' );
		$list[] = array( get_theme_root(), 'themes/' );
		$list[] = array( WP_CONTENT_DIR, '' );
		$list[] = array( ABSPATH, '' ); // The site's folder: never the server's own path.
		foreach ( $list as list( $dir, $name ) ) {
			$real = realpath( $dir );
			foreach ( array( $dir, false === $real ? '' : $real ) as $d ) {
				if ( '' !== $d ) {
					$roots[ trailingslashit( wp_normalize_path( $d ) ) ] = $name;
				}
			}
		}
		uksort( $roots, fn( $a, $b ) => strlen( $b ) <=> strlen( $a ) ); // The longest match first.
	}
	$path = wp_normalize_path( (string) $path );
	$real = realpath( $path );
	foreach ( array( $path, false === $real ? '' : wp_normalize_path( $real ) ) as $p ) {
		foreach ( $roots as $root => $name ) {
			if ( '' !== $p && 0 === strpos( $p, $root ) ) {
				return $name . substr( $p, strlen( $root ) );
			}
		}
	}
	return wp_basename( $path );
}

/** Where each class is used: class => list of files relative to wp-content, each once (theme and plugin files; kept 12 hours). */
function kits_class_usage() {
	$watch = array( get_template_directory() . '/assets/css/kit.css' );
	foreach ( kits_system_dirs() as $dir ) {
		$watch[] = $dir . '/kits-registry.json';
		$watch[] = $dir . '/assets/css/aw.css';
	}
	$watch  = array_unique( $watch );
	$key    = 'kits_class_usage_4_' . md5( implode( '|', array_merge( $watch, array_map( fn( $f ) => is_readable( $f ) ? filemtime( $f ) : 0, $watch ) ) ) ); // New files or folders, new list.
	$cached = get_transient( $key );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$used  = array();
	$roots = array_unique( array_merge( kits_system_dirs(), array( WP_PLUGIN_DIR . '/kits-core', WP_PLUGIN_DIR . '/animal-writes-core' ) ) );
	// animal-writes-core/theme/ holds copies of the child theme's files, read only while it stands in for the child (one
	// of the design system folders); otherwise its files would be counted next to the child's.
	$copies = WP_PLUGIN_DIR . '/animal-writes-core/theme';
	$real   = realpath( $copies );
	$skip   = false !== $real && ! in_array( $real, array_filter( array_map( 'realpath', kits_system_dirs() ) ), true ) ? $real . DIRECTORY_SEPARATOR : '';
	foreach ( $roots as $root ) {
		if ( ! is_dir( $root ) ) {
			continue;
		}
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $file ) {
			$ext = strtolower( $file->getExtension() );
			if ( ! in_array( $ext, array( 'php', 'html', 'css', 'js' ), true ) || false !== strpos( $file->getPathname(), '/node_modules/' ) || str_ends_with( $file->getFilename(), '.min.css' ) || ( '' !== $skip && 0 === strpos( (string) realpath( $file->getPathname() ), $skip ) ) ) {
				continue;
			}
			$s     = (string) file_get_contents( $file->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$found = array();
			if ( 'css' === $ext ) {
				preg_match_all( '/\.((?:aw|kit)-[a-z0-9-]+)/', $s, $m );
				$found = $m[1];
			} elseif ( preg_match_all( '/class(?:Name)?\s*[=:]\s*\\\\?["\']([^"\']*)|"className":"([^"]*)"/', $s, $m, PREG_SET_ORDER ) ) {
				foreach ( $m as $x ) {
					foreach ( preg_split( '/\s+/', trim( ( $x[1] ?? '' ) . ' ' . ( $x[2] ?? '' ) ) ) as $c ) {
						if ( preg_match( '/^(aw|kit)-[a-z0-9-]+$/', $c ) ) {
							$found[] = $c;
						}
					}
				}
			}
			$rel = kits_content_path( $file->getPathname() );
			foreach ( array_unique( $found ) as $c ) {
				$used[ rtrim( explode( '--', $c )[0], '-' ) ][ $rel ] = true;
			}
		}
	}
	$used = array_map( fn( $files ) => array_map( 'strval', array_keys( $files ) ), $used );
	set_transient( $key, $used, 12 * HOUR_IN_SECONDS );
	return $used;
}

/*
 * Kits → Help → Design system (admin-menu.php): read-only. Its old address, kits-system, redirects there.
 */

/**
 * The contrast of each component's text on its background, from the live values.
 *
 * @param array $reg  kits_registry().
 * @param array $vals kits_token_values().
 */
function kits_system_contrast_table( array $reg, array $vals ): void {
	$cells = array();
	foreach ( $reg['items'] as $it ) {
		$pair = $it['a11y']['contrast'] ?? null;
		if ( ! $pair ) {
			continue;
		}
		$fg      = kits_token_resolve( $pair[0], $vals );
		$bg      = kits_token_resolve( $pair[1], $vals );
		$r       = kits_contrast( $fg, $bg );
		$cells[] = array(
			'name'  => esc_html( $it['name'] ),
			'text'  => '<code>' . esc_html( $pair[0] ) . '</code> ' . esc_html( $fg ),
			'on'    => '<code>' . esc_html( $pair[1] ) . '</code> ' . esc_html( $bg ),
			'ratio' => $r ? esc_html( number_format( $r, 2 ) ) . ':1' : '—',
			'aa'    => $r ? ( $r >= 4.5 ? '✓ pass' : '<strong class="error-message">✗ fail</strong>' ) : 'not a plain colour',
		);
	}
	kits_simple_table( 'contrast', array( 'name' => 'Component', 'text' => 'Text', 'on' => 'On', 'ratio' => 'Ratio', 'aa' => 'AA (4.5:1 text)' ), $cells );
}

/** Kits → Help → Design system. */
function kits_system_page() {
	$reg   = kits_registry();
	$tab   = sanitize_key( $_GET['tab'] ?? 'components' ); // phpcs:ignore WordPress.Security.NonceVerification
	$type  = sanitize_key( $_GET['type'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	$base  = admin_url( 'admin.php?page=kits-help' ) . '#design-system';
	$used  = kits_class_usage();
	$vals  = kits_token_values();
	$demo  = get_page_by_path( 'kits-demo' );
	$tabs  = array( 'components' => 'Components, blocks and patterns', 'states' => 'States sheet', 'menus' => 'Menu types', 'contrast' => 'Colour contrast', 'tokens' => 'Tokens' );
	$pref  = array();
	foreach ( $reg['items'] as $it ) {
		$pref = array_merge( $pref, (array) ( $it['classes'] ?? array() ) );
	}
	$missing = array_filter( array_keys( $used ), fn( $c ) => ! array_filter( $pref, fn( $p ) => $c === $p || 0 === strpos( $c, $p . '-' ) ) );

	echo '<div class="wrap"><h1 class="wp-heading-inline">Design system</h1><hr class="wp-header-end">';
	kits_admin_tabs( 'kits-system' );
	echo '<p>The design system as it is in the code: ' . count( $reg['items'] ) . ' entries from the Kits master and this site\'s theme. Read-only; change a component in its file, then its entry in <code>kits-registry.json</code>. Colours, fonts and sizes: Appearance → Editor → Styles. Patterns: Editor → Patterns. Menus: Editor → Navigation.</p>';
	if ( $missing ) {
		wp_admin_notice( '<p><strong>' . count( $missing ) . ' class(es) in use with no registry entry.</strong> The deploy check stops on these.</p><details><summary>' . esc_html( 'Classes' ) . '</summary><p>' . esc_html( implode( ', ', $missing ) ) . '</p></details>', array( 'type' => 'error', 'additional_classes' => array( 'inline' ), 'paragraph_wrap' => false ) );
	}
	// The Kits tabs are above; this screen's own parts are links under them, as on core list screens.
	echo '<ul class="subsubsub">';
	$last = array_key_last( $tabs );
	foreach ( $tabs as $k => $label ) {
		echo '<li><a' . ( $k === $tab ? ' class="current" aria-current="page"' : '' ) . ' href="' . esc_url( add_query_arg( 'tab', $k, $base ) ) . '">' . esc_html( $label ) . '</a>' . ( $k === $last ? '' : ' |' ) . '</li> ';
	}
	echo '</ul><br class="clear">';

	if ( 'states' === $tab ) { // every primitive and block in every state, drawn by the site's own styles (the private page /kits-demo/)
		if ( ! $demo ) {
			echo '<p>No states sheet yet: create a private page with the slug <code>kits-demo</code> holding <code>[kits_demo]</code>.</p></div>';
			return;
		}
		echo '<p><a href="' . esc_url( add_query_arg( 'kits_embed', '1', get_permalink( $demo ) ) ) . '" target="_blank" rel="noopener">Open the sheet on its own</a></p>'
			. '<iframe class="widefat" height="800" title="States sheet" src="' . esc_url( add_query_arg( 'kits_embed', '1', get_permalink( $demo ) ) ) . '"></iframe></div>';
		return;
	}
	if ( 'menus' === $tab ) {
		$cells = array();
		foreach ( $reg['nav'] as $n ) {
			$st      = $reg['nav_status'][ $n['slug'] ] ?? $n['status'];
			$cells[] = array( 'name' => '<strong>' . esc_html( $n['name'] ) . '</strong>', 'status' => esc_html( $st ), 'where' => esc_html( $n['where'] ), 'a11y' => esc_html( $n['a11y'] ) );
		}
		kits_simple_table( 'menu-types', array( 'name' => 'Menu type', 'status' => 'On this site', 'where' => 'Where', 'a11y' => 'Accessibility' ), $cells );
		echo '</div>';
		return;
	}
	if ( 'tokens' === $tab ) {
		$cells  = array();
		$swatch = fn( $r ) => preg_match( '/^#[0-9a-f]{3,8}$/', $r ) ? '<span class="dashicons" aria-hidden="true" style="background:' . esc_attr( $r ) . '"></span> ' : '';
		foreach ( kits_styles_root() as $label => $v ) { // Site-wide choices in Styles.
			$r       = kits_token_resolve( '_', array_merge( $vals, array( '_' => $v ) ) );
			$cells[] = array(
				'token'    => esc_html( $label ),
				'value'    => '<code>' . esc_html( $v ) . '</code>',
				'resolves' => $swatch( $r ) . '<code>' . esc_html( $r ) . '</code>',
				'from'     => 'Styles',
			);
		}
		foreach ( $vals as $k => $v ) {
			if ( 0 !== strpos( $k, 'kit-' ) && 0 !== strpos( $k, 'wp--preset--' ) ) {
				continue;
			}
			$r       = kits_token_resolve( $k, $vals );
			$cells[] = array(
				'token'    => '<code>--' . esc_html( $k ) . '</code>',
				'value'    => '<code>' . esc_html( $v ) . '</code>',
				'resolves' => $swatch( $r ) . '<code>' . esc_html( $r ) . '</code>',
				'from'     => kits_token_source( $k, $vals ),
			);
		}
		kits_simple_table(
			'tokens',
			array(
				'token'    => 'Token',
				'value'    => 'Value',
				'resolves' => 'Resolves to',
				'from'     => 'From',
			),
			$cells
		);
		echo '<p>Base values (phones). Sizes that change at 768px and 1200px are fluid (clamp) or set in the token file. Values from Styles are the ones the site uses now (theme.json, the style variation and any change made in Appearance → Editor → Styles); change them there.</p></div>';
		return;
	}
	if ( 'contrast' === $tab ) {
		kits_system_contrast_table( $reg, $vals );
		echo '<p>Change the colours, and see this table update before saving, in <a href="' . esc_url( admin_url( 'admin.php?page=kits-options#colours' ) ) . '">Theme Options → Colours</a>.</p></div>';
		return;
	}
	// Components, blocks and patterns.
	$types = array_unique( array_map( fn( $i ) => $i['type'], $reg['items'] ) );
	echo '<ul class="subsubsub"><li><a href="' . esc_url( $base ) . '"' . ( '' === $type ? ' class="current"' : '' ) . '>All (' . count( $reg['items'] ) . ')</a></li>';
	foreach ( $types as $t ) {
		$n = count( array_filter( $reg['items'], fn( $i ) => $i['type'] === $t ) );
		echo '<li> | <a href="' . esc_url( add_query_arg( 'type', $t, $base ) ) . '"' . ( $t === $type ? ' class="current"' : '' ) . '>' . esc_html( ucfirst( $t ) ) . ' (' . (int) $n . ')</a></li>';
	}
	echo '</ul><br class="clear">';
	$cells = array();
	foreach ( $reg['items'] as $it ) {
		if ( $type && $it['type'] !== $type ) {
			continue;
		}
		$files = array();
		foreach ( (array) ( $it['classes'] ?? array() ) as $p ) {
			foreach ( $used as $c => $fs ) {
				if ( $c === $p || 0 === strpos( $c, $p . '-' ) ) {
					$files = array_merge( $files, $fs );
				}
			}
		}
		$files = array_unique( $files );
		$a     = (array) ( $it['a11y'] ?? array() );
		$s     = (array) ( $it['seo'] ?? array() );
		$acc   = array();
		foreach ( array( 'element' => 'Element', 'aria' => 'ARIA', 'keyboard' => 'Keyboard', 'focus' => 'Focus', 'target' => 'Target', 'motion' => 'Motion', 'sr' => 'Screen reader' ) as $k => $l ) {
			if ( ! empty( $a[ $k ] ) && 'none' !== $a[ $k ] && 'n/a' !== $a[ $k ] && 'not interactive' !== $a[ $k ] ) {
				$acc[] = '<strong>' . esc_html( $l ) . ':</strong> ' . esc_html( $a[ $k ] );
			}
		}
		$seo = array();
		foreach ( array( 'heading' => 'Heading', 'link' => 'Links', 'alt' => 'Images', 'schema' => 'Schema' ) as $k => $l ) {
			if ( ! empty( $s[ $k ] ) && ! in_array( $s[ $k ], array( 'none', 'n/a' ), true ) ) {
				$seo[] = '<strong>' . esc_html( $l ) . ':</strong> ' . esc_html( $s[ $k ] );
			}
		}
		$what    = ! empty( $it['block'] ) ? '<code>' . esc_html( $it['block'] ) . '</code>' : implode( ' ', array_map( fn( $c ) => '<code>' . esc_html( $c ) . '</code>', (array) $it['classes'] ) );
		$states  = (array) ( $it['states'] ?? array() );
		$style   = kits_registry_style( $it );
		$cells[] = array(
			'name'   => '<strong>' . esc_html( $it['name'] ) . '</strong><br><span class="description">' . esc_html( $it['type'] . ( 'live' !== $it['status'] ? ' · ' . $it['status'] : '' ) ) . '</span>' . ( ! empty( $it['note'] ) ? '<br><em>' . esc_html( $it['note'] ) . '</em>' : '' ) . ( $style ? '<br>' . kits_block_style_where( $style[0], $style[1] ) : '' ),
			'owner'  => esc_html( $it['owner'] ),
			'what'   => count( (array) ( $it['classes'] ?? array() ) ) > 3 ? '<details><summary>' . esc_html( count( (array) $it['classes'] ) . ' classes' ) . '</summary>' . $what . '</details>' : $what,
			'states' => $states ? kits_system_fold( count( $states ) . ' state' . ( 1 === count( $states ) ? '' : 's' ), implode( '<br>', array_map( 'esc_html', $states ) ) ) : '—',
			'a11y'   => $acc ? kits_system_fold( count( $acc ) . ' note' . ( 1 === count( $acc ) ? '' : 's' ), implode( '<br>', $acc ) ) : '—',
			'seo'    => $seo ? kits_system_fold( count( $seo ) . ' note' . ( 1 === count( $seo ) ? '' : 's' ), implode( '<br>', $seo ) ) : '—',
			'used'   => ! empty( $it['block'] ) ? '—' : ( $files ? kits_system_fold( count( $files ) . ' file' . ( 1 === count( $files ) ? '' : 's' ), implode( '<br>', array_map( 'esc_html', $files ) ) ) : '<span class="error-message">not used</span>' ),
			'_after' => kits_system_visual_row( $it, $vals, $demo ),
		);
	}
	kits_simple_table(
		'kits-entries',
		array( 'name' => 'Name', 'owner' => 'From', 'what' => 'Classes or block', 'states' => 'States', 'a11y' => 'Accessibility', 'seo' => 'SEO', 'used' => 'Used in' ),
		$cells,
		array( 'empty' => 'No entries.' )
	);
	echo '</div>';
}

/**
 * A long cell folded: a summary (plain text) that opens to the whole (escaped HTML).
 *
 * @param string $summary Plain text.
 * @param string $html    Escaped HTML.
 */
function kits_system_fold( $summary, $html ) {
	return '<details><summary>' . esc_html( $summary ) . '</summary>' . $html . '</details>';
}

/**
 * A registry entry's block style ("style": {"block": "core/group", "variation": "kits-card"}): [block, variation], or
 * an empty array when it has none.
 *
 * @param array $it Registry entry.
 */
function kits_registry_style( $it ) {
	$s = (array) ( $it['style'] ?? array() );
	return ! empty( $s['block'] ) && ! empty( $s['variation'] ) ? array( (string) $s['block'], (string) $s['variation'] ) : array();
}

/**
 * Where a block style's values are set: the block, the style and its place in theme.json. The Site Editor's Styles
 * lists no styles for some blocks (Group in WordPress 7.1), so this names the file rather than linking to a screen.
 *
 * @param string $block     Block name, e.g. core/group.
 * @param string $variation Block style name.
 */
function kits_block_style_where( $block, $variation ) {
	$type  = class_exists( 'WP_Block_Type_Registry' ) ? WP_Block_Type_Registry::get_instance()->get_registered( $block ) : null;
	$style = class_exists( 'WP_Block_Styles_Registry' ) ? WP_Block_Styles_Registry::get_instance()->get_registered( $block, $variation ) : null;
	$label = ( $type && $type->title ? $type->title : $block ) . ' → ' . ( is_array( $style ) && ! empty( $style['label'] ) ? $style['label'] : $variation ) . ' style';
	return esc_html( $label ) . ', in the theme\'s theme.json: <code>' . esc_html( 'styles.blocks.' . $block . '.variations.' . $variation ) . '</code>';
}

/**
 * A block style's values in Styles: padding (the spacing step), corner radius, background and border, with each
 * preset written as its custom property and the value it stands for. Label => [value as set, value it resolves to].
 *
 * @param string $block     Block name.
 * @param string $variation Block style name.
 */
function kits_block_style_values( $block, $variation ) {
	$out = array();
	if ( ! function_exists( 'wp_get_global_styles' ) ) {
		return $out;
	}
	$v    = (array) wp_get_global_styles( array( 'variations', $variation ), array( 'block_name' => $block ) );
	$pad  = array_values( array_map( 'strval', (array) ( $v['spacing']['padding'] ?? array() ) ) );
	$rows = array(
		'Padding'       => count( array_unique( $pad ) ) > 1 ? implode( ' ', $pad ) : (string) ( $pad[0] ?? '' ),
		'Corner radius' => $v['border']['radius'] ?? '',
		'Background'    => $v['color']['background'] ?? '',
		'Border'        => trim( (string) ( $v['border']['width'] ?? '' ) . ' ' . (string) ( $v['border']['style'] ?? '' ) . ' ' . (string) ( $v['border']['color'] ?? '' ) ),
	);
	$vals = kits_token_values();
	foreach ( $rows as $label => $value ) {
		if ( ! is_string( $value ) || '' === $value ) {
			continue;
		}
		$value         = preg_replace( '/var:preset\|([a-z0-9-]+)\|([a-z0-9-]+)/i', 'var(--wp--preset--$1--$2)', $value );
		$out[ $label ] = array( $value, kits_token_resolve( '_', array_merge( $vals, array( '_' => $value ) ) ) );
	}
	return $out;
}

/**
 * Under each entry: its states, its token values and its visual (owner, 18 Oct 2026). The visual is the states sheet
 * showing only that entry (its classes, or its block in the heading), a pattern drawn on its own, or a live page at the
 * entry's first use; it loads only when opened.
 */
function kits_system_visual_row( $it, $vals, $demo ) {
	$toks = array();
	foreach ( (array) ( $it['tokens'] ?? array() ) as $t ) {
		$t     = ltrim( (string) $t, '-' );
		$names = '*' === substr( $t, -1 ) ? array_filter( array_keys( $vals ), fn( $k ) => 0 === strpos( $k, substr( $t, 0, -1 ) ) ) : ( isset( $vals[ $t ] ) ? array( $t ) : array() );
		foreach ( array_slice( array_values( $names ), 0, 12 ) as $k ) {
			$r      = kits_token_resolve( $k, $vals );
			$toks[] = '<tr><td><code>--' . esc_html( $k ) . '</code></td><td><code>' . esc_html( $r ) . '</code></td></tr>';
		}
		if ( ! $names ) {
			$toks[] = '<tr><td colspan="2"><code>' . esc_html( $t ) . '</code></td></tr>';
		}
	}
	$src = '';
	if ( $demo ) {
		$url = add_query_arg( 'kits_embed', '1', get_permalink( $demo ) );
		if ( 'pattern' === ( $it['type'] ?? '' ) && ! empty( $it['block'] ) ) {
			$src = add_query_arg( 'kits_pattern', rawurlencode( $it['block'] ), $url );
		} elseif ( ! empty( $it['block'] ) ) {
			$src = add_query_arg( 'kits_only_block', rawurlencode( $it['block'] ), $url );
		} elseif ( ! empty( $it['classes'] ) ) {
			$src = add_query_arg( 'kits_only_classes', rawurlencode( implode( ',', (array) $it['classes'] ) ), $url );
		}
		if ( ! $src && $demo && 'pattern' !== ( $it['type'] ?? '' ) ) { // no class or block (e.g. core block styles): its section by name
			$src = add_query_arg( 'kits_only_classes', '', add_query_arg( 'kits_embed', '1', get_permalink( $demo ) ) );
		}
		if ( $src && 'pattern' !== ( $it['type'] ?? '' ) ) {
			$src = add_query_arg( 'kits_only_name', rawurlencode( $it['name'] ), $src );
		}
		if ( $src && 'pattern' !== ( $it['type'] ?? '' ) ) { // where it is used, for an entry the sheet does not draw
			$spot = kits_spot_map()[ $it['name'] ] ?? '';
			$src  = add_query_arg( 'kits_spot_url', rawurlencode( '' !== $spot ? $spot : 'none' ), $src );
		}
	}
	$style = kits_registry_style( $it );
	if ( $style ) { // The block style's own values in Styles first (e.g. the card's padding step).
		$rows = array();
		foreach ( kits_block_style_values( $style[0], $style[1] ) as $label => $v ) {
			$rows[] = '<tr><td>' . esc_html( $label ) . '</td><td><code>' . esc_html( $v[0] ) . '</code> ' . esc_html( $v[1] ) . '</td></tr>';
		}
		$toks = array_merge( $rows, $toks );
	}
	return '<tr class="kits-vis"><td colspan="7"><details><summary>Visual and values</summary>' // The states are in the States column.
		. '<p><strong>Values</strong></p>' . ( $toks ? '<table class="widefat striped"><tbody>' . implode( '', $toks ) . '</tbody></table>' : '<p>No tokens listed.</p>' )
		. ( $src ? '<p><iframe class="widefat" height="560" loading="lazy" title="' . esc_attr( $it['name'] . ' in its states' ) . '" src="' . esc_url( $src ) . '"></iframe></p>' : '<p>No visual: this entry styles core blocks (see Appearance &gt; Editor &gt; Styles).</p>' )
		. '</details></td></tr>';
}

/**
 * Where each registry entry is first used on the site's sample pages (kits_spot_pages(), demo.php): read once a day from
 * the pages' HTML as a visitor gets it, so an entry's visual opens on that page at once.
 *
 * @return array Entry name => page address.
 */
function kits_spot_map( $rebuild = false ) {
	$map = get_option( 'kits_spot_map' );
	if ( is_array( $map ) && ! $rebuild ) {
		return $map;
	}
	$found = array(); // class => first page using it
	foreach ( function_exists( 'kits_spot_pages' ) ? kits_spot_pages() : array() as $url ) {
		$r = wp_remote_get( $url, array( 'timeout' => 30, 'cookies' => array() ) );
		if ( is_wp_error( $r ) ) {
			continue;
		}
		preg_match_all( '/class="([^"]+)"/', (string) wp_remote_retrieve_body( $r ), $m );
		foreach ( $m[1] as $list ) {
			foreach ( preg_split( '/\s+/', $list ) as $c ) {
				if ( '' !== $c && ! isset( $found[ $c ] ) ) {
					$found[ $c ] = $url;
				}
			}
		}
	}
	$map = array();
	foreach ( kits_registry()['items'] as $it ) {
		$want = (array) ( $it['classes'] ?? array() );
		if ( ! empty( $it['block'] ) ) {
			$want[] = 'wp-block-' . str_replace( '/', '-', $it['block'] );
		}
		foreach ( $found as $c => $url ) {
			foreach ( $want as $p ) {
				if ( $c === $p || 0 === strpos( $c, $p . '-' ) ) {
					$map[ $it['name'] ] = $url;
					break 2; // next entry
				}
			}
		}
	}
	update_option( 'kits_spot_map', $map, false );
	return $map;
}
add_action( 'kits_spot_map_daily', fn() => kits_spot_map( true ) );
add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'kits_spot_map_daily' ) ) {
			wp_schedule_event( time() + 300, 'daily', 'kits_spot_map_daily' );
		}
	}
);