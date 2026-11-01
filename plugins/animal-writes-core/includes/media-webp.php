<?php
/**
 * WebP for resized copies (owner, 1 Nov 2026): the sizes WordPress makes of a JPEG or PNG upload are saved as WebP,
 * using core's image conversion (the image_editor_output_format filter, WordPress 6.5+). The uploaded file itself is
 * never converted, and nothing is deleted.
 *
 * Left alone on purpose: AVIF, GIF (animated GIFs stay GIFs), WebP and every other type. Share pictures stay JPEG
 * (og:image and twitter:image are never AVIF or WebP): the share JPEG that aw-seo-extras makes ({name}-og.jpg, see
 * aw_seo_share_jpeg_build()) is not a registered image size, so it is only recognised by its file name, and no
 * mapping is added when the file being read or written is named -og.jpg or -og.jpeg.
 *
 * Media → Make WebP converts the existing images in batches of 20: only their resized copies are made again (through
 * core), the old copies stay on disk, and the old list of sizes is kept in option aw_media_webp_log so Undo can put it
 * back. Admin screen: core list table and core notices only (no CSS, no scripts).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** True for the share JPEG file name ({name}-og.jpg), which must stay a JPEG. */
function aw_media_webp_is_share_file( $file ) {
	return (bool) preg_match( '/-og\.jpe?g$/i', (string) $file );
}

/**
 * Output formats for the resized copies: JPEG and PNG become WebP when this server can write WebP.
 *
 * @param array  $formats   Source mime => output mime.
 * @param string $filename  File being edited.
 * @param string $mime_type Mime type of that file.
 * @param bool   $supported Whether the server can write WebP (passed in so the mapping can be tested).
 * @return array
 */
function aw_media_webp_map_formats( $formats, $filename, $mime_type, $supported ) {
	$formats = is_array( $formats ) ? $formats : array();
	if ( ! $supported || aw_media_webp_is_share_file( $filename ) ) {
		return $formats;
	}
	$formats['image/jpeg'] = 'image/webp';
	$formats['image/png']  = 'image/webp';
	return $formats;
}

add_filter(
	'image_editor_output_format',
	function ( $formats, $filename = '', $mime_type = '' ) {
		return aw_media_webp_map_formats( $formats, $filename, $mime_type, wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) );
	},
	99,
	3
);

/** The conversion log: attachment ID => array( sizes (the old list), time, status converted|failed|undone, why ). */
function aw_media_webp_log() {
	$log = get_option( 'aw_media_webp_log', array() );
	return is_array( $log ) ? $log : array();
}

/** Save the log; false when it could not be written. */
function aw_media_webp_log_save( array $log ) {
	if ( update_option( 'aw_media_webp_log', $log, false ) ) {
		return true;
	}
	return get_option( 'aw_media_webp_log' ) === $log;
}

/** True when a size entry still points at a JPEG or PNG file that is not a share file. */
function aw_media_webp_old_size( $size ) {
	$file = is_array( $size ) ? (string) ( $size['file'] ?? '' ) : '';
	return '' !== $file && preg_match( '/\.(jpe?g|png)$/i', $file ) && ! aw_media_webp_is_share_file( $file );
}

/**
 * JPEG and PNG attachments whose sizes still point at JPEG or PNG files, not yet in the log.
 *
 * @param int $limit Stop after this many (0 = all, for the counter).
 * @return int[]
 */
function aw_media_webp_pending( $limit = 0 ) {
	global $wpdb;
	$log = aw_media_webp_log();
	$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type IN ('image/jpeg','image/png') ORDER BY ID ASC" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$out = array();
	foreach ( array_chunk( array_map( 'intval', (array) $ids ), 200 ) as $chunk ) {
		update_meta_cache( 'post', $chunk );
		foreach ( $chunk as $id ) {
			if ( isset( $log[ $id ] ) ) {
				continue;
			}
			$file = (string) get_post_meta( $id, '_wp_attached_file', true );
			if ( '' === $file || aw_media_webp_is_share_file( $file ) ) {
				continue;
			}
			$meta = wp_get_attachment_metadata( $id );
			foreach ( (array) ( is_array( $meta ) ? ( $meta['sizes'] ?? array() ) : array() ) as $size ) {
				if ( aw_media_webp_old_size( $size ) ) {
					$out[] = $id;
					break;
				}
			}
			if ( $limit && count( $out ) >= $limit ) {
				return $out;
			}
		}
	}
	return $out;
}

/**
 * Make the resized copies of one attachment again, through core. The old list is logged first; old files stay.
 *
 * @param int $id Attachment ID.
 * @return bool
 */
function aw_media_webp_convert( $id ) {
	$id   = (int) $id;
	$meta = wp_get_attachment_metadata( $id );
	$file = get_attached_file( $id );
	$log  = aw_media_webp_log();
	if ( ! is_array( $meta ) || empty( $meta['sizes'] ) || ! $file || ! file_exists( $file ) ) {
		return false;
	}
	$log[ $id ] = array( 'sizes' => $meta['sizes'], 'time' => time(), 'status' => 'failed', 'why' => 'Stopped before it finished' );
	if ( ! aw_media_webp_log_save( $log ) ) {
		return false; // nothing is changed without the old list saved
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$fresh = $meta;
	unset( $fresh['sizes'] ); // core only makes sizes that are missing
	$why = '';
	try {
		wp_update_attachment_metadata( $id, $fresh );
		// The file the attachment points at (already within the big-image limit), so no new "-scaled" copy is made.
		$new = wp_create_image_subsizes( $file, $id );
		if ( is_wp_error( $new ) ) {
			$why = $new->get_error_message();
		} elseif ( empty( $new['sizes'] ) ) {
			$why = 'No resized copies were made';
		}
	} catch ( Throwable $e ) {
		$why = $e->getMessage();
	}
	if ( '' !== $why ) {
		wp_update_attachment_metadata( $id, $meta ); // put the old list back
		$log[ $id ]['why'] = $why;
		aw_media_webp_log_save( $log );
		return false;
	}
	$log[ $id ]['status'] = 'converted';
	$log[ $id ]['why']    = '';
	return aw_media_webp_log_save( $log );
}

/** Put the saved list of sizes back (the old files are still on disk). */
function aw_media_webp_undo( $id ) {
	$id   = (int) $id;
	$log  = aw_media_webp_log();
	$meta = wp_get_attachment_metadata( $id );
	if ( empty( $log[ $id ]['sizes'] ) || 'converted' !== ( $log[ $id ]['status'] ?? '' ) || ! is_array( $meta ) ) {
		return false;
	}
	$meta['sizes'] = $log[ $id ]['sizes'];
	wp_update_attachment_metadata( $id, $meta );
	$back = wp_get_attachment_metadata( $id );
	if ( ! is_array( $back ) || ( $back['sizes'] ?? null ) !== $log[ $id ]['sizes'] ) {
		return false;
	}
	$log[ $id ]['status'] = 'undone';
	$log[ $id ]['time']   = time();
	return aw_media_webp_log_save( $log );
}

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'upload.php', 'Make WebP', 'Make WebP', 'manage_options', 'aw-media-webp', 'aw_media_webp_screen' );
	}
);

add_action(
	'admin_post_aw_media_webp',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( 'Not allowed.' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'aw_media_webp' );
		$do   = sanitize_text_field( wp_unslash( $_POST['do'] ?? '' ) );
		$args = array( 'page' => 'aw-media-webp' );
		if ( 'batch' === $do ) {
			if ( ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
				$args['aw_mw_off'] = 1;
			} else {
				$stop = microtime( true ) + 25;
				if ( function_exists( 'set_time_limit' ) ) {
					set_time_limit( 120 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
				}
				$ok   = 0;
				$fail = 0;
				foreach ( aw_media_webp_pending( 20 ) as $id ) {
					if ( microtime( true ) > $stop ) {
						break;
					}
					if ( aw_media_webp_convert( $id ) ) {
						++$ok;
					} else {
						++$fail;
					}
				}
				$args['aw_mw']      = $ok;
				$args['aw_mw_fail'] = $fail;
			}
		} elseif ( preg_match( '/^undo:(\d+)$/', $do, $m ) ) {
			$args[ aw_media_webp_undo( (int) $m[1] ) ? 'aw_mw_undo' : 'aw_mw_undo_fail' ] = (int) $m[1];
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'upload.php' ) ) );
		exit;
	}
);

/** The Media → Make WebP screen. */
function aw_media_webp_screen() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	echo '<div class="wrap"><h1>Make WebP</h1>';
	if ( isset( $_GET['aw_mw'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . (int) $_GET['aw_mw'] . ' image(s) converted.' . ( ! empty( $_GET['aw_mw_fail'] ) ? ' ' . (int) $_GET['aw_mw_fail'] . ' could not be converted and keep their old sizes (see Status).' : '' ) . '</p></div>';
	}
	if ( isset( $_GET['aw_mw_undo'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>Image ' . (int) $_GET['aw_mw_undo'] . ' has its old sizes back.</p></div>';
	}
	if ( isset( $_GET['aw_mw_undo_fail'] ) ) {
		echo '<div class="notice notice-error is-dismissible"><p>Image ' . (int) $_GET['aw_mw_undo_fail'] . ' could not be put back.</p></div>';
	}
	if ( isset( $_GET['aw_mw_off'] ) ) {
		echo '<div class="notice notice-error is-dismissible"><p>This server cannot write WebP, so nothing was converted.</p></div>';
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	$supported = wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) );
	$pending   = count( aw_media_webp_pending() );
	$log       = aw_media_webp_log();
	echo '<p>New JPEG and PNG uploads get WebP resized copies. The uploaded file is never changed and no file is deleted: converting an existing image makes its resized copies again as WebP and keeps the old ones on disk. Undo puts the old list of sizes back.</p>';
	echo '<p>' . (int) $pending . ' JPEG/PNG images still have JPEG/PNG sizes.' . ( $supported ? '' : ' This server cannot write WebP.' ) . '</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'aw_media_webp' );
	echo '<input type="hidden" name="action" value="aw_media_webp">';
	echo '<p><button type="submit" name="do" value="batch" class="button button-primary"' . ( $supported && $pending ? '' : ' disabled' ) . '>Convert the next 20</button></p>';
	$rows = array();
	foreach ( array_reverse( $log, true ) as $id => $e ) {
		$status = (string) ( $e['status'] ?? '' );
		if ( 'converted' === $status ) {
			$state  = 'Converted ' . esc_html( wp_date( 'j M Y H:i', (int) $e['time'] ) );
			$action = '<button type="submit" name="do" value="undo:' . (int) $id . '" class="button">Undo</button>';
		} elseif ( 'undone' === $status ) {
			$state  = 'Undone ' . esc_html( wp_date( 'j M Y H:i', (int) $e['time'] ) );
			$action = '';
		} else {
			$state  = 'Not converted: ' . esc_html( (string) ( $e['why'] ?? '' ) );
			$action = '';
		}
		$rows[] = array(
			'image'  => '<a href="' . esc_url( (string) get_edit_post_link( (int) $id ) ) . '">' . esc_html( wp_html_excerpt( (string) get_the_title( (int) $id ), 50, '…' ) ) . '</a> (' . (int) $id . ')',
			'sizes'  => (int) count( (array) ( $e['sizes'] ?? array() ) ),
			'state'  => $state,
			'action' => $action,
		);
	}
	aw_core_simple_table(
		'aw-media-webp',
		array( 'image' => 'Image', 'sizes' => 'Old sizes kept', 'state' => 'Status', 'action' => 'Action' ),
		$rows,
		array( 'primary' => 'image', 'empty' => 'Nothing converted yet.' )
	);
	echo '</form></div>';
}
