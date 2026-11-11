<?php
/**
 * Kits admin app: the site's Global Styles (user origin), for the sections that edit Styles values (Colours,
 * Typography, Buttons, Cards). The app saves them with one GET + POST to /wp/v2/global-styles/<id>
 * (src/theme-options/global-styles.js), sending back every key it did not change.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'kits_admin_app_data', 'kits_admin_app_global_styles_data' );

// A Global Styles save (this screen, or Styles in the Site Editor) changes the CSS every page prints inline: empty the
// page cache so the change shows. LiteSpeed Cache's own hook; nothing happens when it is not active.
add_action(
	'rest_after_insert_wp_global_styles',
	function () {
		do_action( 'litespeed_purge_all' );
	}
);

/**
 * Adds globalStyles: { id, settings, styles } (the user origin as saved; id 0 when this user cannot edit Styles).
 *
 * @param array $data window.kitsAdminApp.
 */
function kits_admin_app_global_styles_data( array $data ): array {
	if ( ! current_user_can( 'edit_theme_options' ) || ! class_exists( 'WP_Theme_JSON_Resolver' ) ) {
		$data['globalStyles'] = array( 'id' => 0 );
		return $data;
	}
	$id  = (int) WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
	$raw = $id ? json_decode( (string) get_post_field( 'post_content', $id ), true ) : null;
	$raw = is_array( $raw ) ? $raw : array();
	// An empty list ("styles": []) is sent as {}.
	$data['globalStyles'] = array(
		'id'       => $id,
		'settings' => (object) ( is_array( $raw['settings'] ?? null ) ? $raw['settings'] : array() ),
		'styles'   => (object) ( is_array( $raw['styles'] ?? null ) ? $raw['styles'] : array() ),
	);
	return $data;
}
