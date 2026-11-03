<?php
/**
 * Updates for the Kits by Wugweb theme, the way WordPress updates any theme.
 *
 * The theme's style.css names an Update URI on wugweb.com, so WordPress asks this file instead of WordPress.org
 * (core filter update_themes_{hostname}, WordPress 6.1+). The answer comes from a small JSON file:
 *
 *     { "version": "2.3.0", "package": "https://…/kits-by-wugweb-2.3.0.zip", "url": "https://…/changelog",
 *       "requires": "6.6", "requires_php": "7.4", "tested": "7.1" }
 *
 * With it, Appearance → Themes shows "New version available", "View version details" (the change log) and
 * "Update now", and the auto-updates toggle works. The JSON address can be changed with the kits_update_url filter.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Where the Kits update JSON lives. */
function kits_update_url() {
	return (string) apply_filters( 'kits_update_url', 'https://wugweb.com/kits/update.json' );
}

/** The change log that ships with the installed theme (readme.txt), for "read before updating". */
function kits_changelog_url() {
	$theme = wp_get_theme( 'kits-by-wugweb' );
	return $theme->exists() ? $theme->get_stylesheet_directory_uri() . '/readme.txt' : '';
}

/**
 * The latest release as WordPress wants it, or false. Cached for 12 hours (1 hour after a failed check).
 *
 * @return array|false
 */
function kits_update_release() {
	$cached = get_site_transient( 'kits_update_release' );
	if ( is_array( $cached ) ) {
		return $cached['version'] ? $cached : false;
	}
	$release = array( 'version' => '' );
	$res     = wp_remote_get( kits_update_url(), array( 'timeout' => 10 ) );
	if ( ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res ) ) {
		$json = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( is_array( $json ) && ! empty( $json['version'] ) && preg_match( '/^\d+(\.\d+)*$/', (string) $json['version'] ) ) {
			$release = array(
				'version'      => (string) $json['version'],
				'package'      => esc_url_raw( (string) ( $json['package'] ?? '' ) ),
				'url'          => esc_url_raw( (string) ( $json['url'] ?? '' ) ),
				'requires'     => (string) ( $json['requires'] ?? '' ),
				'requires_php' => (string) ( $json['requires_php'] ?? '' ),
				'tested'       => (string) ( $json['tested'] ?? '' ),
			);
		}
	}
	set_site_transient( 'kits_update_release', $release, $release['version'] ? 12 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
	return $release['version'] ? $release : false;
}

add_filter(
	'update_themes_wugweb.com',
	/**
	 * Answer WordPress's update check for Kits by Wugweb.
	 *
	 * @param array|false $update     Earlier answer, if any.
	 * @param array       $theme_data The theme's headers.
	 * @param string      $stylesheet The theme's folder name.
	 * @return array|false
	 */
	function ( $update, $theme_data, $stylesheet ) {
		if ( $update || 'kits-by-wugweb' !== $stylesheet ) {
			return $update;
		}
		$release = kits_update_release();
		if ( ! $release ) {
			return false;
		}
		return array(
			'id'           => (string) ( $theme_data['UpdateURI'] ?? '' ),
			'theme'        => $stylesheet,
			'version'      => $release['version'],
			'url'          => $release['url'] ? $release['url'] : kits_changelog_url(),
			'package'      => $release['package'],
			'requires'     => $release['requires'],
			'requires_php' => $release['requires_php'],
			'tested'       => $release['tested'],
		);
	},
	10,
	3
);

// A manual "Check again" on Dashboard → Updates asks the JSON again.
add_action(
	'load-update-core.php',
	function () {
		if ( isset( $_GET['force-check'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- core's own link, read only.
			delete_site_transient( 'kits_update_release' );
		}
	}
);
