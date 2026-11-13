<?php
/**
 * Animal Writes theme parts (once the animal-writes child theme's), brought by this plugin so the site runs Kits by
 * Wugweb with no child theme (as Uncode sites run Uncode with Uncode Core). The plugin's theme/ folder is their one
 * source; the child theme was retired (its folder left the repository on 14 Nov 2026).
 *
 * With Kits as the active theme, theme-parts-assets.php (styles, scripts, files, fonts) and theme-parts-templates.php
 * (templates, template parts, patterns) serve them. Were an animal-writes child theme active again, nothing here would
 * run and it would serve its own files.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Folder of the files this plugin carries for the theme (fonts, images, CSS, JS). */
define( 'AW_THEME_PARTS_DIR', AW_CORE_DIR . 'theme/' );

/**
 * Whether this plugin serves the Animal Writes theme parts: true when Kits by Wugweb itself is the active theme
 * (no animal-writes child). Filter aw_core_theme_parts_on to force it either way.
 */
function aw_core_theme_parts_on(): bool {
	$on = 'animal-writes' !== get_stylesheet() && 'kits-by-wugweb' === get_template();
	return (bool) apply_filters( 'aw_core_theme_parts_on', $on );
}

/**
 * Address of a theme file (path relative to the theme root, e.g. 'assets/img/google-maps.png'): the child theme's copy
 * while it is active, else this plugin's copy.
 *
 * @param string $file Path relative to the theme root.
 */
function aw_theme_file_uri( string $file ): string {
	$file = ltrim( $file, '/' );
	return aw_core_theme_parts_on() ? plugins_url( 'theme/' . $file, AW_CORE_DIR . 'animal-writes-core.php' ) : get_theme_file_uri( $file );
}

require_once AW_CORE_DIR . 'includes/theme-parts-assets.php';
require_once AW_CORE_DIR . 'includes/theme-parts-templates.php';
require_once AW_CORE_DIR . 'includes/theme-switch.php';
