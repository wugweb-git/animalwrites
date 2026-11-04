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
