<?php
/**
 * Retired (Phase 11 G, I): Settings → Banners and the Settings-based Media & PR list.
 * - Banners are now a Kits block pattern ("Banner") placed in page content or templates; topic hubs read an optional
 *   banner image from the topic (term meta aw_banner_image). All banner slots were empty when this changed
 *   (banners removed on 8 Oct 2026; the earlier settings are archived in the option aw_banners_removed_2026_10_08).
 * - Media & PR coverage is the aw_press item type (press.php). The old option aw_press is kept as an archive.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Old content may still contain [aw_banner]: it prints nothing.
add_shortcode( 'aw_banner', '__return_empty_string' );

// The empty Settings → Banners option goes (its earlier values stay archived in aw_banners_removed_2026_10_08).
add_action(
	'admin_init',
	function () {
		if ( false !== get_option( 'aw_banners' ) && current_user_can( 'manage_options' ) ) {
			$left = array_filter( (array) get_option( 'aw_banners' ), fn( $b ) => ! empty( $b['desktop'] ) || ! empty( $b['mobile'] ) );
			if ( ! $left ) {
				delete_option( 'aw_banners' );
			}
		}
	}
);
