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
 * The listing post type, with the arguments built for it by one function, directoryweb_content_type_args(), and the
 * core category registered on it.
 */
function directoryweb_register_listing_type(): void {
	register_post_type( 'directoryweb_listing', directoryweb_content_type_args( 'directoryweb_listing' ) );
	register_taxonomy_for_object_type( 'category', 'directoryweb_listing' );
	register_taxonomy_for_object_type( 'post_tag', 'directoryweb_listing' ); // listing types and services (own taxonomies: a later phase)
}
/** One flush of the rewrite rules the first time a version loads (the Directory addresses come from this plugin). */
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
	directoryweb_directory_rewrites();
	directoryweb_register_meta();
	directoryweb_caps_grant();
	flush_rewrite_rules();
	update_option( 'directoryweb_rewrite_ver', DIRECTORYWEB_VERSION, false );
}

/** Deactivation: only the rewrite rules are flushed; nothing is deleted. */
function directoryweb_deactivate(): void {
	flush_rewrite_rules();
}
