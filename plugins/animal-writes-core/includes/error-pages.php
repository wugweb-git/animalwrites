<?php
/**
 * Branded server error pages: the PHP fatal error page (500) and the update maintenance page (503).
 *
 * WordPress only reads these as drop-ins in wp-content (php-error.php and maintenance.php), which is outside what a
 * deploy updates, so the sources live in this plugin's dropins/ folder and are copied there. A copy is made when the
 * file is missing or differs from the plugin's version, on the first web request after a deploy (when the deployed
 * commit changes, see deploy-purge.php) and on admin screens. A file at that path without the marker
 * "Animal Writes error page" belongs to something else and is never replaced. Nothing here can stop a request: if
 * wp-content is not writable the copy is skipped and the result is kept in the option aw_error_pages.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Marker that every page in dropins/ carries; only files with it are replaced. */
const AW_ERROR_PAGE_MARKER = 'Animal Writes error page';

/** Drop-in file names (the same in dropins/ and wp-content/). */
const AW_ERROR_PAGE_FILES = array( 'php-error.php', 'maintenance.php' );

/**
 * Copy each drop-in into wp-content when it is missing or out of date.
 *
 * @return array<string,string> File name => 'current', 'installed', 'updated', 'foreign', 'unwritable' or 'no-source'.
 */
function aw_error_pages_sync() {
	$status = array();
	foreach ( AW_ERROR_PAGE_FILES as $name ) {
		$status[ $name ] = aw_error_page_sync_one( AW_CORE_DIR . 'dropins/' . $name, WP_CONTENT_DIR . '/' . $name );
	}
	if ( get_option( 'aw_error_pages' ) !== $status ) {
		update_option( 'aw_error_pages', $status, false );
	}
	return $status;
}

/**
 * Bring one drop-in up to date.
 *
 * @param string $src Source file in the plugin.
 * @param string $dst Drop-in path in wp-content.
 * @return string Result, see aw_error_pages_sync().
 */
function aw_error_page_sync_one( $src, $dst ) {
	$new = is_readable( $src ) ? (string) file_get_contents( $src ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( '' === $new || false === strpos( $new, AW_ERROR_PAGE_MARKER ) ) {
		return 'no-source';
	}
	$exists = file_exists( $dst );
	if ( $exists ) {
		$old = is_readable( $dst ) ? (string) file_get_contents( $dst ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( $old === $new ) {
			return 'current';
		}
		if ( false === strpos( $old, AW_ERROR_PAGE_MARKER ) ) {
			return 'foreign'; // another plugin's or a person's drop-in: leave it alone.
		}
	}
	$dir = dirname( $dst );
	if ( ! is_writable( $dir ) || ( $exists && ! is_writable( $dst ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
		return 'unwritable';
	}
	// Write next to the target, then rename: a half-written drop-in must never be what WordPress loads.
	$tmp = $dir . '/.' . basename( $dst ) . '.' . wp_generate_password( 8, false ) . '.tmp';
	if ( strlen( $new ) !== @file_put_contents( $tmp, $new, LOCK_EX ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors
		@unlink( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors
		return 'unwritable';
	}
	if ( ! @rename( $tmp, $dst ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors
		@unlink( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors
		return 'unwritable';
	}
	@chmod( $dst, 0644 ); // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors
	if ( function_exists( 'opcache_invalidate' ) ) {
		@opcache_invalidate( $dst, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	return $exists ? 'updated' : 'installed';
}

// After a deploy: deploy-purge.php stores the new commit on the first web request that sees it.
add_action( 'add_option_aw_deployed_head', 'aw_error_pages_sync' );
add_action( 'update_option_aw_deployed_head', 'aw_error_pages_sync' );

// On admin screens (not Ajax or the heartbeat), at most once an hour: puts the pages back if one was deleted, and covers a first install.
add_action(
	'admin_init',
	function () {
		if ( wp_doing_ajax() || get_transient( 'aw_error_pages_checked' ) ) {
			return;
		}
		set_transient( 'aw_error_pages_checked', 1, HOUR_IN_SECONDS );
		aw_error_pages_sync();
	}
);

// Tell administrators on the dashboard when a page could not be installed.
add_action(
	'admin_notices',
	function () {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'dashboard' !== $screen->id || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$bad = array_keys( array_filter( (array) get_option( 'aw_error_pages', array() ), fn( $s ) => in_array( $s, array( 'unwritable', 'foreign', 'no-source' ), true ) ) );
		if ( $bad ) {
			wp_admin_notice( esc_html( 'The branded error pages are not installed (' . implode( ', ', $bad ) . '): WordPress shows its own pages instead. Check that wp-content is writable.' ), array( 'type' => 'warning' ) );
		}
	}
);
