<?php
/**
 * The private upload folder: uploads/aw-private, blocked from the web. Claim logos wait here between the form and the
 * review (forms/fluentforms.php, DirectoryWeb claims). Moved from Animal Writes Core's campaigns.php (aw_private_dir()),
 * which is being retired; the folder name stays, so nothing already in it moves.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Private folder (blocked from the web), used for claim logos. Created on first use. */
function kits_private_dir(): string {
	$up  = wp_upload_dir();
	$dir = trailingslashit( $up['basedir'] ) . 'aw-private';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
		file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- the web-block file of a folder this function makes.
		file_put_contents( $dir . '/index.html', '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- as above.
	}
	return $dir;
}
