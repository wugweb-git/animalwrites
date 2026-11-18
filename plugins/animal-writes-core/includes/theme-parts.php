<?php
/**
 * The Animal Writes theme parts moved to the Kits theme's site folder (themes/kits-by-wugweb/assets/site/, served by
 * the theme's inc/site-layer.php) on 18 Nov 2026; they were this plugin's theme/ folder. These are the old names, kept
 * for the callers that still use them; each one hands over to the theme.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Whether the theme serves the Animal Writes site layer (Kits by Wugweb with its assets/site/ folder). */
function aw_core_theme_parts_on(): bool {
	return function_exists( 'kits_site_on' ) && kits_site_on();
}

/**
 * Address of a site file, given by its old path below the theme root (e.g. 'assets/img/google-maps.png', now
 * assets/site/img/google-maps.png in the Kits theme).
 *
 * @param string $file Old path relative to the theme root.
 */
function aw_theme_file_uri( string $file ): string {
	$file = (string) preg_replace( '#^assets/#', '', ltrim( $file, '/' ) );
	return function_exists( 'kits_site_uri' ) ? kits_site_uri( $file ) : '';
}

/**
 * Called by the PHP that prints a component: this page loads that component's stylesheet.
 *
 * @param string $handle Handle from the site's component list (kits_site_component_files()).
 */
function aw_use_component_style( string $handle ): void {
	if ( function_exists( 'kits_use_component_style' ) ) {
		kits_use_component_style( $handle );
	}
}
