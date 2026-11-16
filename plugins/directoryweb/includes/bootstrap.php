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
/**
 * The listing field groups' keys, for the plugins that read them (SourceWeb's rules).
 *
 * @return string[]
 */
function directoryweb_scf_groups(): array {
	return DIRECTORYWEB_SCF_GROUPS;
}

/**
 * The listing field group files, for the plugins that read them (SourceWeb's rules and tests).
 *
 * @return string[] Absolute paths.
 */
function directoryweb_field_files(): array {
	return (array) glob( DIRECTORYWEB_DIR . 'acf-json/group_aw_listing*.json' );
}

/** Activation: rewrite rules for the Directory addresses, the capability, one flush. */
function directoryweb_activate(): void {
	if ( ! defined( 'DIRECTORYWEB_LOADED' ) ) {
		return;
	}
	directoryweb_register_listing_type();
	aw_directory_rewrites();
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
