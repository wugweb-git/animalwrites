<?php
/**
 * Map picker on the listing edit screen (wp-admin only): an OpenStreetMap map beside the Latitude and Longitude fields.
 * Click the map or drag the pin to fill aw_lat / aw_lng; typing in the fields moves the pin. It writes the same two
 * fields (no other storage) and never touches aw_maps_url. No Google key needed.
 *
 * Leaflet 1.9.4 from cdnjs (pinned, with integrity); tiles from tile.openstreetmap.org with the OSM attribution, as
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
		$base = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/' . AW_LEAFLET_VER . '/';
		wp_enqueue_style( 'aw-leaflet', $base . 'leaflet.min.css', array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- the version is in the URL
		wp_enqueue_script( 'aw-leaflet', $base . 'leaflet.min.js', array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_enqueue_script( 'aw-map-picker', plugins_url( 'assets/js/map-picker.js', AW_CORE_DIR . 'animal-writes-core.php' ), array( 'aw-leaflet' ), (string) filemtime( AW_CORE_DIR . 'assets/js/map-picker.js' ), true );
		wp_localize_script( 'aw-map-picker', 'awMapPicker', array( 'icons' => $base . 'images/' ) );
		wp_add_inline_style( 'aw-leaflet', '.aw-map-picker{height:320px;margin:8px 0 4px;border:1px solid #c3c4c7;border-radius:4px}.aw-map-picker-help{margin:0 0 8px;color:#646970;font-size:12px}' );
	}
);

// Subresource integrity for the two pinned Leaflet files.
add_filter(
	'script_loader_tag',
	fn( $tag, $handle ) => 'aw-leaflet' === $handle ? str_replace( ' src=', ' integrity="sha512-puJW3E/qXDqYp9IfhAI54BJEaWIfloJ7JWs7OeD5i6ruC9JZL1gERT1wjtwXFlh7CjE7ZJ+/vcRZRkIYIb6p4g==" crossorigin="anonymous" src=', $tag ) : $tag,
	10,
	2
);
add_filter(
	'style_loader_tag',
	fn( $tag, $handle ) => 'aw-leaflet' === $handle ? str_replace( ' href=', ' integrity="sha512-h9FcoyWjHcOcmEVkxOfTLnmZFWIH0iZhZT1H2TbOq55xssQGEJHEaIm+PgoUaZbRvQTNTluNOEfb1ZRy6D3BOw==" crossorigin="anonymous" href=', $tag ) : $tag,
	10,
	2
);
