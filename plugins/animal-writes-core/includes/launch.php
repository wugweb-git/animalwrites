<?php
/**
 * One-time server setup after a move: write WordPress's rewrite rules and the launch redirects
 * into .htaccess from a normal web request (WP-CLI cannot detect mod_rewrite on this host).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_HTACCESS_VERSION = '1';

add_action(
	'init',
	function () {
		if ( AW_HTACCESS_VERSION === get_option( 'aw_htaccess_version' ) || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}
		$file = ABSPATH . '.htaccess';
		if ( file_exists( $file ) && ! is_writable( $file ) ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$redirects = array(
			'RewriteEngine On',
			'RewriteCond %{HTTP_HOST} ^www\.animalwrites\.in$ [NC]',
			'RewriteRule ^(.*)$ https://animalwrites.in/$1 [R=301,L]',
			'RewriteRule ^campaign/?$ /campaigns/animal-writes-1-0/ [R=301,L]',
			'RedirectMatch 404 /\.git',
		);
		$wp_rules = array(
			'<IfModule mod_rewrite.c>',
			'RewriteEngine On',
			'RewriteBase /',
			'RewriteRule ^index\.php$ - [L]',
			'RewriteCond %{REQUEST_FILENAME} !-f',
			'RewriteCond %{REQUEST_FILENAME} !-d',
			'RewriteRule . /index.php [L]',
			'</IfModule>',
		);
		$ok = insert_with_markers( $file, 'Animal Writes redirects', $redirects )
			&& insert_with_markers( $file, 'WordPress', $wp_rules );
		if ( ! $ok ) {
			return;
		}
		// Redirects must run before the WordPress catch-all: put that section first.
		$body = (string) file_get_contents( $file ); // phpcs:ignore
		if ( preg_match( '/# BEGIN Animal Writes redirects.*?# END Animal Writes redirects\n?/s', $body, $m ) ) {
			$body = $m[0] . "\n" . str_replace( $m[0], '', $body );
			file_put_contents( $file, $body ); // phpcs:ignore
		}
		update_option( 'aw_htaccess_version', AW_HTACCESS_VERSION, false );
		flush_rewrite_rules( false );
	},
	99
);
