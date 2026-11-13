<?php
/**
 * Map picker on the listing edit screen (wp-admin only): an OpenStreetMap map beside the Latitude and Longitude fields.
 * Click the map or drag the pin to fill aw_lat / aw_lng; typing in the fields moves the pin. It writes the same two
 * fields (no other storage) and never touches aw_maps_url. No Google key needed.
 *
 * Leaflet 1.9.4 served from this plugin (assets/vendor/leaflet-1.9.4, copied from the npm package; see its README for
 * the file hashes), so no third-party script loads; tiles from tile.openstreetmap.org with the OSM attribution, as
 * its tile usage policy asks (a few editors, a handful of tiles each: well within it). Loaded only on the listing
 * add and edit screens.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_LEAFLET_VER = '1.9.4';

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'aw_listing' !== $screen->post_type ) {
			return;
		}
		$base = plugins_url( 'assets/vendor/leaflet-' . AW_LEAFLET_VER . '/', AW_CORE_DIR . 'animal-writes-core.php' );
		wp_enqueue_style( 'aw-leaflet', $base . 'leaflet.css', array(), AW_LEAFLET_VER );
		wp_enqueue_script( 'aw-leaflet', $base . 'leaflet.js', array(), AW_LEAFLET_VER, true );
		wp_enqueue_script( 'aw-map-picker', plugins_url( 'assets/js/map-picker.js', AW_CORE_DIR . 'animal-writes-core.php' ), array( 'aw-leaflet' ), (string) filemtime( AW_CORE_DIR . 'assets/js/map-picker.js' ), true );
		wp_localize_script( 'aw-map-picker', 'awMapPicker', array( 'icons' => $base . 'images/' ) );
		wp_add_inline_style( 'aw-leaflet', '.aw-map-picker{height:320px;margin:8px 0 4px;border:1px solid #c3c4c7;border-radius:4px}.aw-map-picker-help{margin:0 0 8px;color:#646970;font-size:12px}' );
	}
);
