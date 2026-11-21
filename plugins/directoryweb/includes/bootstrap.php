<?php
/**
 * DirectoryWeb's own functions: the listing type, the field group lists, activation and deactivation.
 *
 * @package directoryweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The listing post type, with the arguments Animal Writes Core's content-types.php builds for it (one function builds
 * them, aw_content_type_args()), and the core category on it, as Core's loop gave it.
 */
function directoryweb_register_listing_type(): void {
	if ( ! function_exists( 'aw_content_type_args' ) ) {
		return;
	}
	register_post_type( 'aw_listing', aw_content_type_args( 'aw_listing' ) );
	register_taxonomy_for_object_type( 'category', 'aw_listing' );
}
/** One flush of the rewrite rules after the first deploy of a version (the Directory addresses come from this plugin now). */
add_action(
	'init',
	function () {
		if ( DIRECTORYWEB_VERSION !== get_option( 'directoryweb_rewrite_ver' ) ) {
			flush_rewrite_rules();
			update_option( 'directoryweb_rewrite_ver', DIRECTORYWEB_VERSION, false );
		}
	},
	99
);

/** Activation: rewrite rules for the Directory addresses, the capability, one flush. */
function directoryweb_activate(): void {
	if ( ! defined( 'DIRECTORYWEB_LOADED' ) ) {
		return;
	}
	directoryweb_register_listing_type();
	aw_directory_rewrites();
	directoryweb_register_meta();
	if ( function_exists( 'aw_register_meta' ) ) {
		aw_register_meta();
	}
	directoryweb_caps_grant();
	flush_rewrite_rules();
	update_option( 'directoryweb_rewrite_ver', DIRECTORYWEB_VERSION, false );
}

/** Deactivation: only the rewrite rules are flushed; nothing is deleted. */
function directoryweb_deactivate(): void {
	flush_rewrite_rules();
}
