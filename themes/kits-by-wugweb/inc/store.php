<?php
/**
 * Kits by Wugweb: the theme's one option (kits_by_wugweb) and its tidy-up.
 *
 * @package kits-by-wugweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The theme's one database option, kits_by_wugweb: an array of the theme's own state (the pattern files' signature).
 * WordPress.org's theme rules allow one option, named after the theme. The joined stylesheets' state moved to Kits
 * Core (option kits_core_joined) on 18 Nov 2026; kits_theme_store_tidy() drops the entries left from before.
 *
 * @param string $key     Entry.
 * @param mixed  $fallback What to return when the entry is not set.
 * @return mixed
 */
function kits_theme_store( string $key, $fallback = null ) {
	$all = get_option( 'kits_by_wugweb', array() );
	return is_array( $all ) && array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
}

/**
 * Sets one entry of the theme's option (written only when it changes).
 *
 * @param string $key   Entry.
 * @param mixed  $value Value.
 */
function kits_theme_store_set( string $key, $value ): void {
	$all = get_option( 'kits_by_wugweb', array() );
	$all = is_array( $all ) ? $all : array();
	if ( array_key_exists( $key, $all ) && $all[ $key ] === $value ) {
		return;
	}
	$all[ $key ] = $value;
	update_option( 'kits_by_wugweb', $all, true );
}

/**
 * Removes one entry of the theme's option.
 *
 * @param string $key Entry.
 */
function kits_theme_store_delete( string $key ): void {
	$all = get_option( 'kits_by_wugweb', array() );
	if ( is_array( $all ) && array_key_exists( $key, $all ) ) {
		unset( $all[ $key ] );
		update_option( 'kits_by_wugweb', $all, true );
	}
}

/*
 * Joined stylesheets (the five core files as one, the component files as one per kind of page): Kits Core Performance
 * (plugins/kits-core/includes/joined-css.php) since 18 Nov 2026. Without Kits Core every file loads on its own.
 */

/**
 * Drops the joined stylesheets' entries left in the theme's option from before they moved to Kits Core (18 Nov 2026):
 * kits_bundle_ver, kits_parts_kinds, kits_parts_sweep and kits_parts_ver_*. Runs in wp-admin and writes only when such
 * an entry is there.
 */
function kits_theme_store_tidy() {
	$all = get_option( 'kits_by_wugweb', array() );
	if ( ! is_array( $all ) ) {
		return;
	}
	$keep = array_filter( $all, fn( $k ) => ! preg_match( '/^kits_(?:bundle_ver|parts_kinds|parts_sweep|parts_ver_.*)$/', (string) $k ), ARRAY_FILTER_USE_KEY );
	if ( count( $keep ) !== count( $all ) ) {
		update_option( 'kits_by_wugweb', $keep, true );
	}
}
add_action( 'admin_init', 'kits_theme_store_tidy' );
