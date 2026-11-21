<?php
/**
 * Business Directory Plugin (WPBDP) Compatibility & Consumption Bridge.
 *
 * Allows DirectoryWeb to consume Business Directory Plugin data,
 * providing modern Gutenberg FSE blocks, interactive maps, and schema
 * directly on top of wpbdp_listing records.
 *
 * @package directoryweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DirectoryWeb_BDP_Adapter {

	/** Initialize the adapter if WPBDP is active. */
	public static function init(): void {
		if ( ! defined( 'WPBDP_VERSION' ) && ! class_exists( 'WPBDP_Plugin' ) ) {
			return;
		}

		// Allow DirectoryWeb to target wpbdp_listing if selected
		add_filter( 'directoryweb_consume_bdp', '__return_true' );

		// Bridge metadata lookups
		add_filter( 'directoryweb_listing_meta', array( __CLASS__, 'map_bdp_meta' ), 10, 3 );
	}

	/**
	 * Map BDP custom fields to DirectoryWeb virtual meta fields.
	 *
	 * @param mixed  $value Existing value.
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Meta key.
	 * @return mixed
	 */
	public static function map_bdp_meta( $value, int $post_id, string $meta_key ) {
		if ( 'wpbdp_listing' !== get_post_type( $post_id ) ) {
			return $value;
		}

		switch ( $meta_key ) {
			case 'phone':
			case 'aw_phone':
				return get_post_meta( $post_id, '_wpbdp[fields][phone]', true ) ?: $value;

			case 'address':
			case 'aw_address':
				return get_post_meta( $post_id, '_wpbdp[fields][address]', true ) ?: $value;

			case 'website':
			case 'aw_website':
				return get_post_meta( $post_id, '_wpbdp[fields][website]', true ) ?: $value;

			case 'latitude':
			case 'aw_lat':
				return get_post_meta( $post_id, '_wpbdp[latitude]', true ) ?: $value;

			case 'longitude':
			case 'aw_lng':
				return get_post_meta( $post_id, '_wpbdp[longitude]', true ) ?: $value;
		}

		return $value;
	}
}

add_action( 'plugins_loaded', array( 'DirectoryWeb_BDP_Adapter', 'init' ), 20 );
