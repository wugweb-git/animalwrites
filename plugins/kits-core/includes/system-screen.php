<?php
/**
 * Appearance → Kits system: a read-only index of the design system, generated from kits-registry.json in the Kits
 * master theme and in the site's child theme. Every component, block, pattern and menu type with its states, its
 * accessibility record and its SEO output; where each is used (from the theme and plugin files); the colour pairs
 * with their contrast worked out from the live tokens; and the tokens themselves. Classes in use with no registry
 * entry are listed at the top (the deploy check fails on them too). Nothing here changes the site.
 *
 * Every state of every component is drawn on the states sheet (/kits-demo/, private).
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The registry: master first, then the child theme's own entries. */
function kits_registry() {
	static $reg = null;
	if ( null !== $reg ) {
		return $reg;
	}
	$reg = array( 'items' => array(), 'nav' => array(), 'nav_status' => array() );
	foreach ( array_unique( array( get_template_directory(), get_stylesheet_directory() ) ) as $dir ) {
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

/** Custom properties declared in :root of the token stylesheets (base values), name => value. */
function kits_token_values() {
	$vals = array();
	$css  = array( get_template_directory() . '/assets/css/kits-v42.css', get_template_directory() . '/assets/css/kit-tokens.css', get_stylesheet_directory() . '/assets/css/tokens.css' );
	foreach ( array_unique( $css ) as $f ) {
		$s = is_readable( $f ) ? (string) file_get_contents( $f ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( preg_match( '/:root\s*\{(.*?)\n\}/s', $s, $m ) && preg_match_all( '/--([a-z0-9-]+):\s*([^;]+);/', $m[1], $mm, PREG_SET_ORDER ) ) {
			foreach ( $mm as $x ) {
				$vals[ $x[1] ] = trim( $x[2] );
			}
		}
	}
	return $vals;
}

/** A token's value with var() references followed. */
function kits_token_resolve( $name, $vals ) {
	$v = $vals[ $name ] ?? '';
	for ( $i = 0; $i < 10 && preg_match( '/var\(--([a-z0-9-]+)(?:,\s*([^)]+))?\)/', $v, $m ); $i++ ) {
		$v = str_replace( $m[0], $vals[ $m[1] ] ?? ( $m[2] ?? '' ), $v );
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

/** Where each class is used: class => list of files (theme and plugin files; kept 12 hours). */
function kits_class_usage() {
	$key    = 'kits_class_usage_' . md5( implode( '|', array_map( fn( $f ) => is_readable( $f ) ? filemtime( $f ) : 0, array( get_template_directory() . '/kits-registry.json', get_stylesheet_directory() . '/kits-registry.json', get_template_directory() . '/assets/css/kit.css', get_stylesheet_directory() . '/assets/css/aw.css' ) ) ) ); // new files, new list
	$cached = get_transient( $key );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$used  = array();
	$roots = array_unique( array( get_template_directory(), get_stylesheet_directory(), WP_PLUGIN_DIR . '/kits-core', WP_PLUGIN_DIR . '/animal-writes-core' ) );
	foreach ( $roots as $root ) {
		if ( ! is_dir( $root ) ) {
			continue;
		}
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $file ) {
			$ext = strtolower( $file->getExtension() );
			if ( ! in_array( $ext, array( 'php', 'html', 'css', 'js' ), true ) || false !== strpos( $file->getPathname(), '/node_modules/' ) ) {
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
			$rel = str_replace( array( dirname( get_template_directory() ) . '/', WP_PLUGIN_DIR . '/' ), '', $file->getPathname() );
			foreach ( array_unique( $found ) as $c ) {
				$used[ rtrim( explode( '--', $c )[0], '-' ) ][ $rel ] = true;
			}
		}
	}
	$used = array_map( 'array_keys', $used );
	set_transient( $key, $used, 12 * HOUR_IN_SECONDS );
	return $used;
}

add_action(
	'admin_menu',
	function () {
		add_theme_page( 'Kits system', 'Kits system', 'manage_options', 'kits-system', 'kits_system_page' );
	}
);

function kits_system_page() {
	$reg   = kits_registry();
	$tab   = sanitize_key( $_GET['tab'] ?? 'components' ); // phpcs:ignore WordPress.Security.NonceVerification
	$type  = sanitize_key( $_GET['type'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	$base  = admin_url( 'themes.php?page=kits-system' );
	$used  = kits_class_usage();
	$vals  = kits_token_values();
	$demo  = get_page_by_path( 'kits-demo' );
	$tabs  = array( 'components' => 'Components, blocks and patterns', 'menus' => 'Menu types', 'contrast' => 'Colour contrast', 'tokens' => 'Tokens' );
	$pref  = array();
	foreach ( $reg['items'] as $it ) {
		$pref = array_merge( $pref, (array) ( $it['classes'] ?? array() ) );
	}
	$missing = array_filter( array_keys( $used ), fn( $c ) => ! array_filter( $pref, fn( $p ) => $c === $p || 0 === strpos( $c, $p . '-' ) ) );

	echo '<div class="wrap"><h1 class="wp-heading-inline">Kits system</h1>' . ( $demo ? ' <a class="page-title-action" href="' . esc_url( get_permalink( $demo ) ) . '">States sheet</a>' : '' ) . '<hr class="wp-header-end">';
	echo '<p>The design system as it is in the code: ' . count( $reg['items'] ) . ' entries from the Kits master and this site\'s theme. Read-only; change a component in its file, then its entry in <code>kits-registry.json</code>. Colours, fonts and sizes: Appearance → Editor → Styles. Patterns: Editor → Patterns. Menus: Editor → Navigation.</p>';
	if ( $missing ) {
		echo '<div class="notice notice-error inline"><p><strong>' . count( $missing ) . ' class(es) in use with no registry entry:</strong> ' . esc_html( implode( ', ', $missing ) ) . '. The deploy check stops on these.</p></div>';
	}
	echo '<nav class="nav-tab-wrapper">';
	foreach ( $tabs as $k => $label ) {
		echo '<a class="nav-tab' . ( $k === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( add_query_arg( 'tab', $k, $base ) ) . '">' . esc_html( $label ) . '</a>';
	}
	echo '</nav>';

	if ( 'menus' === $tab ) {
		echo '<table class="widefat striped" style="margin-top:1em"><thead><tr><th>Menu type</th><th>On this site</th><th>Where</th><th>Accessibility</th></tr></thead><tbody>';
		foreach ( $reg['nav'] as $n ) {
			$st = $reg['nav_status'][ $n['slug'] ] ?? $n['status'];
			echo '<tr><td><strong>' . esc_html( $n['name'] ) . '</strong></td><td>' . esc_html( $st ) . '</td><td>' . esc_html( $n['where'] ) . '</td><td>' . esc_html( $n['a11y'] ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
		return;
	}
	if ( 'tokens' === $tab ) {
		echo '<table class="widefat striped" style="margin-top:1em;max-width:900px"><thead><tr><th>Token</th><th>Value</th><th>Resolves to</th></tr></thead><tbody>';
		foreach ( $vals as $k => $v ) {
			if ( 0 !== strpos( $k, 'kit-' ) ) {
				continue;
			}
			$r  = kits_token_resolve( $k, $vals );
			$sw = preg_match( '/^#[0-9a-f]{3,8}$/', $r ) ? '<span style="display:inline-block;width:14px;height:14px;border:1px solid #ccc;vertical-align:middle;margin-right:6px;background:' . esc_attr( $r ) . '"></span>' : '';
			echo '<tr><td><code>--' . esc_html( $k ) . '</code></td><td><code>' . esc_html( $v ) . '</code></td><td>' . $sw . '<code>' . esc_html( $r ) . '</code></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</tbody></table><p>Base values (phones). Sizes that change at 640px and 1200px are fluid (clamp) or set in the token file.</p></div>';
		return;
	}
	if ( 'contrast' === $tab ) {
		echo '<table class="widefat striped" style="margin-top:1em;max-width:900px"><thead><tr><th>Component</th><th>Text</th><th>On</th><th>Ratio</th><th>AA (4.5:1 text)</th></tr></thead><tbody>';
		foreach ( $reg['items'] as $it ) {
			$pair = $it['a11y']['contrast'] ?? null;
			if ( ! $pair ) {
				continue;
			}
			$fg = kits_token_resolve( $pair[0], $vals );
			$bg = kits_token_resolve( $pair[1], $vals );
			$r  = kits_contrast( $fg, $bg );
			echo '<tr><td>' . esc_html( $it['name'] ) . '</td><td><code>' . esc_html( $pair[0] ) . '</code> ' . esc_html( $fg ) . '</td><td><code>' . esc_html( $pair[1] ) . '</code> ' . esc_html( $bg ) . '</td><td>' . ( $r ? esc_html( number_format( $r, 2 ) ) . ':1' : '—' ) . '</td><td>' . ( $r ? ( $r >= 4.5 ? '✓ pass' : '<strong style="color:#b32d2e">✗ fail</strong>' ) : 'not a plain colour' ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</tbody></table></div>';
		return;
	}
	// Components, blocks and patterns.
	$types = array_unique( array_map( fn( $i ) => $i['type'], $reg['items'] ) );
	echo '<ul class="subsubsub" style="float:none"><li><a href="' . esc_url( $base ) . '"' . ( '' === $type ? ' class="current"' : '' ) . '>All (' . count( $reg['items'] ) . ')</a></li>';
	foreach ( $types as $t ) {
		$n = count( array_filter( $reg['items'], fn( $i ) => $i['type'] === $t ) );
		echo '<li> | <a href="' . esc_url( add_query_arg( 'type', $t, $base ) ) . '"' . ( $t === $type ? ' class="current"' : '' ) . '>' . esc_html( ucfirst( $t ) ) . ' (' . (int) $n . ')</a></li>';
	}
	echo '</ul><table class="widefat striped"><thead><tr><th style="width:15%">Name</th><th style="width:8%">From</th><th style="width:14%">Classes or block</th><th style="width:13%">States</th><th>Accessibility</th><th style="width:16%">SEO</th><th style="width:9%">Used in</th></tr></thead><tbody>';
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
		$what = ! empty( $it['block'] ) ? '<code>' . esc_html( $it['block'] ) . '</code>' : implode( ' ', array_map( fn( $c ) => '<code>' . esc_html( $c ) . '</code>', (array) $it['classes'] ) );
		echo '<tr><td><strong>' . esc_html( $it['name'] ) . '</strong><br><span style="color:#646970">' . esc_html( $it['type'] . ( 'live' !== $it['status'] ? ' · ' . $it['status'] : '' ) ) . '</span>' . ( ! empty( $it['note'] ) ? '<br><em>' . esc_html( $it['note'] ) . '</em>' : '' ) . '</td>'
			. '<td>' . esc_html( $it['owner'] ) . '</td><td>' . $what . '</td><td>' . esc_html( implode( ' · ', (array) $it['states'] ) ) . '</td>'
			. '<td>' . implode( '<br>', $acc ) . '</td><td>' . ( $seo ? implode( '<br>', $seo ) : '—' ) . '</td>'
			. '<td>' . ( ! empty( $it['block'] ) ? '—' : ( $files ? '<details><summary>' . count( $files ) . ' file' . ( 1 === count( $files ) ? '' : 's' ) . '</summary>' . implode( '<br>', array_map( 'esc_html', $files ) ) . '</details>' : '<span style="color:#b32d2e">not used</span>' ) ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</tbody></table></div>';
}
