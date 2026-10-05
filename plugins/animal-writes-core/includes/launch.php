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

/**
 * One-time media restore after the move: put back each attachment's original file (from the repo's
 * media-restore/ folder, or the official source for government PDFs), then let WordPress rebuild
 * thumbnails and PDF previews. A few files per page view, so no request runs long.
 */
const AW_MEDIA_RESTORE_VERSION = '4';

add_action(
	'init',
	function () {
		if ( AW_MEDIA_RESTORE_VERSION === get_option( 'aw_media_restore_version' ) || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) || get_transient( 'aw_media_restore_lock' ) ) {
			return;
		}
		set_transient( 'aw_media_restore_lock', 1, MINUTE_IN_SECONDS );
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$repo     = dirname( AW_CORE_DIR, 2 ) . '/media-restore/';
		$manifest = is_readable( $repo . 'manifest.json' ) ? (array) json_decode( (string) file_get_contents( $repo . 'manifest.json' ), true ) : array(); // phpcs:ignore
		$uploads  = wp_upload_dir();
		$done     = 0;
		$left     = 0;
		foreach ( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) {
			$rel  = (string) get_post_meta( $id, '_wp_attached_file', true );
			$dest = trailingslashit( $uploads['basedir'] ) . $rel;
			$orig = preg_replace( '/(-scaled)+\./', '.', $rel );
			if ( $orig !== $rel && isset( $manifest[ $orig ] ) && ! file_exists( trailingslashit( $uploads['basedir'] ) . $orig ) && $done < 4 ) {
				$full = trailingslashit( $uploads['basedir'] ) . $orig;
				if ( copy( $repo . basename( $manifest[ $orig ] ), $full ) ) {
					update_post_meta( $id, '_wp_attached_file', $orig );
					wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $full ) );
				}
				$done++;
				continue;
			}
			if ( ! $rel || file_exists( $dest ) || ( get_post_meta( $id, '_aw_restore_failed', true ) && ! isset( $manifest[ $rel ] ) ) ) {
				continue;
			}
			if ( $done >= 4 ) {
				$left++;
				continue;
			}
			wp_mkdir_p( dirname( $dest ) );
			$ok = false;
			if ( isset( $manifest[ $rel ] ) && is_readable( $repo . basename( $manifest[ $rel ] ) ) ) {
				$ok = copy( $repo . basename( $manifest[ $rel ] ), $dest );
			} elseif ( str_ends_with( strtolower( $rel ), '.pdf' ) ) {
				$owner = get_posts( array( 'post_type' => 'any', 'post_status' => 'any', 'meta_key' => 'aw_file_id', 'meta_value' => $id, 'fields' => 'ids', 'numberposts' => 1 ) );
				$src   = $owner ? (string) get_post_meta( $owner[0], 'aw_source_url', true ) : '';
				$tmp   = $src ? download_url( $src, 30 ) : new WP_Error( 'nosrc' );
				if ( ! is_wp_error( $tmp ) ) {
					$ok = rename( $tmp, $dest ) || copy( $tmp, $dest );
				}
			}
			if ( $ok ) {
				wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $dest ) );
			} else {
				update_post_meta( $id, '_aw_restore_failed', 1 );
			}
			$done++;
		}
		delete_transient( 'aw_media_restore_lock' );
		if ( 0 === $left ) {
			update_option( 'aw_media_restore_version', AW_MEDIA_RESTORE_VERSION, false );
		}
	},
	100
);
