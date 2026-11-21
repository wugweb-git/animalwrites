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
/**
 * Return the primary post type used for directory listings.
 * Filterable via 'directoryweb_post_type'.
 * Supports 'wpbdp_listing' (Business Directory Plugin) if active and chosen.
 */
function directoryweb_post_type(): string {
	static $post_type = null;
	if ( null !== $post_type ) {
		return $post_type;
	}

	if ( defined( 'WPBDP_VERSION' ) && apply_filters( 'directoryweb_consume_bdp', false ) ) {
		$default = 'wpbdp_listing';
	} elseif ( function_exists( 'aw_content_type_args' ) || post_type_exists( 'aw_listing' ) ) {
		$default = 'aw_listing';
	} else {
		$default = 'directory_listing';
	}

	$post_type = (string) apply_filters( 'directoryweb_post_type', $default );
	return $post_type;
}

function directoryweb_register_listing_type(): void {
	$cpt = directoryweb_post_type();

	// If Business Directory Plugin already registered wpbdp_listing, bind category and return
	if ( 'wpbdp_listing' === $cpt && post_type_exists( 'wpbdp_listing' ) ) {
		register_taxonomy_for_object_type( 'category', $cpt );
		return;
	}

	if ( function_exists( 'aw_content_type_args' ) ) {
		$args = aw_content_type_args( $cpt );
	} else {
		$args = array(
			'labels'       => array(
				'name'          => __( 'Directory Listings', 'directoryweb' ),
				'singular_name' => __( 'Directory Listing', 'directoryweb' ),
			),
			'public'       => true,
			'show_ui'      => true,
			'show_in_menu' => true,
			'show_in_rest' => true,
			'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
			'has_archive'  => true,
			'rewrite'      => array( 'slug' => 'directory' ),
		);
	}
	register_post_type( $cpt, $args );
	register_taxonomy_for_object_type( 'category', $cpt );
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
