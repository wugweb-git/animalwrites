<?php
/**
 * Media → Duplicate files (owner, 1 Nov 2026): merges the duplicate copies of a file that the Media Library audit found.
 *
 * Trash is off on this site (MEDIA_TRASH), so deleting an attachment would be permanent. This tool never deletes: for each
 * group it repoints every reference from the extra copies to one kept copy, checks that nothing still points at them, and
 * retires the extras by giving them the Trash status (files stay on disk). Every change is logged with its old value, so
 * Undo puts each reference and the copies back exactly. Permanent deletion is a separate, later step.
 *
 * A group is blocked, with the reason shown, when an extra is the featured image of a post, a hosted file or document
 * preview, a category or tag photo, the site icon, named in an option, or the two files differ. Only the source image,
 * social image and share image of a post, and the image URLs written in post content, are repointed.
 * Admin screen: core list table and core notices only (no CSS, no scripts).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Post meta keys that this tool may repoint (the same picture; nothing is "used once"). */
function aw_media_merge_meta_keys() {
	return array( 'aw_source_image_att', 'rank_math_facebook_image_id', 'kits_og_image' );
}

/** The groups to merge: keep ID => extra IDs. */
function aw_media_merge_groups() {
	static $groups = null;
	if ( null === $groups ) {
		$groups = (array) require __DIR__ . '/data/media-duplicate-groups.php';
	}
	return $groups;
}

/** The log of what the tool did: keep ID => entry (status merged, undone or blocked, time, extras, meta rows, content). */
function aw_media_merge_log() {
	return (array) get_option( 'aw_media_merge_log', array() );
}

/**
 * Every URL of an attachment (full size and each resized copy), by size name.
 *
 * @param int $id Attachment ID.
 * @return string[]
 */
function aw_media_merge_urls( $id ) {
	$full = (string) wp_get_attachment_url( $id );
	if ( '' === $full ) {
		return array();
	}
	$urls = array( 'full' => $full );
	$meta = wp_get_attachment_metadata( $id );
	$dir  = trailingslashit( dirname( $full ) );
	foreach ( (array) ( $meta['sizes'] ?? array() ) as $size => $info ) {
		if ( ! empty( $info['file'] ) ) {
			$urls[ $size ] = $dir . $info['file'];
		}
	}
	return $urls;
}

/**
 * Whether a group can be merged now, and what it would change. Reads only.
 *
 * @param int   $keep   Copy to keep.
 * @param int[] $extras Copies to retire.
 * @return array ok (bool), why (string), meta (rows to repoint: meta_id, post_id, key, old), posts (IDs whose content has an old URL).
 */
function aw_media_merge_check( $keep, array $extras ) {
	global $wpdb;
	$fail  = function ( $why ) {
		return array( 'ok' => false, 'why' => $why, 'meta' => array(), 'posts' => array() );
	};
	$extras = array_values( array_diff( array_map( 'intval', $extras ), array( (int) $keep ) ) );
	if ( ! $extras ) {
		return $fail( 'no extra copies' );
	}
	$kp = get_post( $keep );
	if ( ! $kp || 'attachment' !== $kp->post_type || 'inherit' !== $kp->post_status ) {
		return $fail( 'kept copy ' . (int) $keep . ' is missing' );
	}
	$kfile = get_attached_file( $keep );
	if ( ! $kfile || ! is_file( $kfile ) ) {
		return $fail( 'kept file is not on disk' );
	}
	$khash = md5_file( $kfile );
	foreach ( $extras as $id ) {
		$p = get_post( $id );
		if ( ! $p || 'attachment' !== $p->post_type ) {
			return $fail( 'copy ' . $id . ' is missing' );
		}
		if ( 'inherit' !== $p->post_status ) {
			return $fail( 'copy ' . $id . ' is already retired' );
		}
		if ( $p->post_mime_type !== $kp->post_mime_type ) {
			return $fail( 'copy ' . $id . ' is a different type' );
		}
		$file = get_attached_file( $id );
		if ( ! $file || ! is_file( $file ) || md5_file( $file ) !== $khash ) {
			return $fail( 'copy ' . $id . ' is not the same file' );
		}
	}
	$in      = implode( ',', array_fill( 0, count( $extras ), '%d' ) );
	$allowed = aw_media_merge_meta_keys();
	// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the IN list is only %d placeholders built with array_fill
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_value IN ($in) AND meta_key REGEXP 'image|img|logo|thumb|icon|photo|file|preview|_att|banner|picture|attachment'", $extras ) );
	$meta = array();
	foreach ( (array) $rows as $r ) {
		if ( in_array( $r->meta_key, array( '_wp_attachment_context' ), true ) ) {
			continue;
		}
		if ( ! in_array( $r->meta_key, $allowed, true ) ) {
			return $fail( 'used by ' . $r->meta_key . ' on post ' . (int) $r->post_id );
		}
		$meta[] = array( 'meta_id' => (int) $r->meta_id, 'post_id' => (int) $r->post_id, 'key' => $r->meta_key, 'old' => (string) $r->meta_value );
	}
	$trows = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->termmeta} WHERE meta_value IN ($in)", $extras ) );
	if ( (int) $trows ) {
		return $fail( 'used as a category or tag photo' );
	}
	foreach ( $extras as $id ) {
		$hit = $wpdb->get_var( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name <> 'aw_media_merge_log' AND ( option_value = %s OR option_value LIKE %s OR option_value LIKE %s ) LIMIT 1", (string) $id, '%i:' . $id . ';%', '%"' . $id . '"%' ) );
		if ( $hit ) {
			return $fail( 'named in the option ' . $hit );
		}
	}
	$posts = array();
	foreach ( $extras as $id ) {
		$full = (string) wp_get_attachment_url( $id );
		$stem = '' === $full ? '' : pathinfo( $full, PATHINFO_FILENAME );
		if ( '' === $stem ) {
			continue;
		}
		$found = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type NOT IN ( 'attachment', 'revision' ) AND ( post_content LIKE %s OR post_content LIKE %s ) LIMIT 200", '%' . $wpdb->esc_like( $stem ) . '%', '%wp-image-' . (int) $id . '%' ) );
		$posts = array_merge( $posts, array_map( 'intval', $found ) );
	}
	// phpcs:enable
	return array( 'ok' => true, 'why' => '', 'meta' => $meta, 'posts' => array_values( array_unique( $posts ) ) );
}

/**
 * Merge one group: repoint, check, retire. Rolls back by itself if the check after repointing still finds a reference.
 *
 * @param int $keep Copy to keep.
 * @return array status (merged|blocked|failed), why.
 */
function aw_media_merge_group( $keep ) {
	global $wpdb;
	$groups = aw_media_merge_groups();
	$extras = $groups[ $keep ] ?? array();
	$log    = aw_media_merge_log();
	if ( isset( $log[ $keep ] ) && 'merged' === $log[ $keep ]['status'] ) {
		return array( 'status' => 'merged', 'why' => 'already merged' );
	}
	$c = aw_media_merge_check( $keep, $extras );
	if ( ! $c['ok'] ) {
		$log[ $keep ] = array( 'status' => 'blocked', 'why' => $c['why'], 'time' => time() );
		update_option( 'aw_media_merge_log', $log, false );
		return array( 'status' => 'blocked', 'why' => $c['why'] );
	}
	$entry = array( 'status' => 'merged', 'why' => '', 'time' => time(), 'user' => get_current_user_id(), 'extras' => $extras, 'meta' => array(), 'content' => array() );
	// 1. Repoint the picture references.
	foreach ( $c['meta'] as $m ) {
		$wpdb->update( $wpdb->postmeta, array( 'meta_value' => (string) $keep ), array( 'meta_id' => $m['meta_id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$entry['meta'][] = $m;
		wp_cache_delete( $m['post_id'], 'post_meta' );
	}
	// 2. Replace the image addresses written in post content.
	$kurls = aw_media_merge_urls( $keep );
	foreach ( $c['posts'] as $pid ) {
		$post = get_post( $pid );
		if ( ! $post ) {
			continue;
		}
		$new = $post->post_content;
		foreach ( $extras as $id ) {
			$map = array();
			foreach ( aw_media_merge_urls( $id ) as $size => $url ) {
				$map[ $url ] = $kurls[ $size ] ?? ( $kurls['full'] ?? $url );
			}
			uksort( $map, function ( $a, $b ) {
				return strlen( $b ) <=> strlen( $a );
			} );
			$new = str_replace( array_keys( $map ), array_values( $map ), $new );
			$new = preg_replace( '/wp-image-' . (int) $id . '\b/', 'wp-image-' . (int) $keep, $new );
		}
		if ( $new !== $post->post_content ) {
			$entry['content'][ $pid ] = $post->post_content;
			$wpdb->update( $wpdb->posts, array( 'post_content' => $new ), array( 'ID' => $pid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			clean_post_cache( $pid );
		}
	}
	// 3. Check that nothing still points at the extras.
	$left = aw_media_merge_check( $keep, $extras );
	$bad  = ! $left['ok'] || $left['meta'];
	if ( ! $bad ) {
		foreach ( $extras as $id ) {
			foreach ( aw_media_merge_urls( $id ) as $url ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				if ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type NOT IN ( 'attachment', 'revision' ) AND post_content LIKE %s", '%' . $wpdb->esc_like( $url ) . '%' ) ) ) {
					$bad = true;
					break 2;
				}
			}
		}
	}
	if ( $bad ) {
		aw_media_merge_restore( $entry );
		$log[ $keep ] = array( 'status' => 'failed', 'why' => 'a reference was still left after repointing; nothing was kept', 'time' => time() );
		update_option( 'aw_media_merge_log', $log, false );
		return array( 'status' => 'failed', 'why' => $log[ $keep ]['why'] );
	}
	// 4. Retire the extras (Trash status; files stay).
	foreach ( $extras as $id ) {
		update_post_meta( $id, '_wp_trash_meta_status', 'inherit' );
		update_post_meta( $id, '_wp_trash_meta_time', time() );
		update_post_meta( $id, 'aw_merged_into', (int) $keep );
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'trash' ), array( 'ID' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		clean_post_cache( $id );
	}
	$log[ $keep ] = $entry;
	update_option( 'aw_media_merge_log', $log, false );
	return array( 'status' => 'merged', 'why' => '' );
}

/** Put the references and post content of a log entry back as they were. */
function aw_media_merge_restore( array $entry ) {
	global $wpdb;
	foreach ( (array) ( $entry['meta'] ?? array() ) as $m ) {
		$wpdb->update( $wpdb->postmeta, array( 'meta_value' => $m['old'] ), array( 'meta_id' => $m['meta_id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_cache_delete( $m['post_id'], 'post_meta' );
	}
	foreach ( (array) ( $entry['content'] ?? array() ) as $pid => $old ) {
		$wpdb->update( $wpdb->posts, array( 'post_content' => $old ), array( 'ID' => (int) $pid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		clean_post_cache( (int) $pid );
	}
}

/** Undo a merged group: references, content and the copies come back. */
function aw_media_merge_undo( $keep ) {
	global $wpdb;
	$log = aw_media_merge_log();
	if ( empty( $log[ $keep ] ) || 'merged' !== $log[ $keep ]['status'] ) {
		return false;
	}
	aw_media_merge_restore( $log[ $keep ] );
	foreach ( (array) $log[ $keep ]['extras'] as $id ) {
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'inherit' ), array( 'ID' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		delete_post_meta( $id, '_wp_trash_meta_status' );
		delete_post_meta( $id, '_wp_trash_meta_time' );
		delete_post_meta( $id, 'aw_merged_into' );
		clean_post_cache( (int) $id );
	}
	$log[ $keep ]['status'] = 'undone';
	$log[ $keep ]['time']   = time();
	update_option( 'aw_media_merge_log', $log, false );
	return true;
}

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'upload.php', 'Duplicate files', 'Duplicate files', 'manage_options', 'aw-media-duplicates', 'aw_media_merge_screen' );
	}
);

add_action(
	'admin_post_aw_media_merge',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.', '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'aw_media_merge' );
		$do   = sanitize_text_field( wp_unslash( $_POST['do'] ?? '' ) );
		$args = array( 'page' => 'aw-media-duplicates' );
		if ( 'batch' === $do ) {
			$done   = 0;
			$stop   = microtime( true ) + 25;
			$log    = aw_media_merge_log();
			foreach ( array_keys( aw_media_merge_groups() ) as $keep ) {
				if ( isset( $log[ $keep ] ) && in_array( $log[ $keep ]['status'], array( 'merged', 'blocked', 'failed' ), true ) ) {
					continue;
				}
				if ( $done >= 10 || microtime( true ) > $stop ) {
					break;
				}
				aw_media_merge_group( (int) $keep );
				++$done;
			}
			$args['aw_mm'] = $done;
		} elseif ( preg_match( '/^(merge|undo|retry):(\d+)$/', $do, $m ) ) {
			$keep = (int) $m[2];
			if ( 'undo' === $m[1] ) {
				aw_media_merge_undo( $keep );
				$args['aw_mm_undo'] = $keep;
			} else {
				if ( 'retry' === $m[1] ) {
					$log = aw_media_merge_log();
					unset( $log[ $keep ] );
					update_option( 'aw_media_merge_log', $log, false );
				}
				aw_media_merge_group( $keep );
				$args['aw_mm'] = 1;
			}
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'upload.php' ) ) );
		exit;
	}
);

/** The Media → Duplicate files screen. */
function aw_media_merge_screen() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$groups = aw_media_merge_groups();
	$log    = aw_media_merge_log();
	echo '<div class="wrap"><h1>Duplicate files</h1>';
	if ( isset( $_GET['aw_mm'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success is-dismissible"><p>Checked ' . (int) $_GET['aw_mm'] . ' group(s). Merged groups show the date; blocked ones show why.</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification
	}
	if ( isset( $_GET['aw_mm_undo'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success is-dismissible"><p>Group ' . (int) $_GET['aw_mm_undo'] . ' put back as it was.</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification
	}
	echo '<p>The same picture uploaded more than once. A merge points every use at one copy and gives the extra copies the Trash status; files stay on disk and nothing is deleted. Undo puts everything back. A group is blocked when an extra is a featured image, hosted file, category photo or named in an option.</p>';
	$merged  = 0;
	$blocked = 0;
	foreach ( $log as $e ) {
		$merged  += 'merged' === $e['status'] ? 1 : 0;
		$blocked += in_array( $e['status'], array( 'blocked', 'failed' ), true ) ? 1 : 0;
	}
	echo '<p>' . count( $groups ) . ' groups: ' . (int) $merged . ' merged, ' . (int) $blocked . ' blocked.</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'aw_media_merge' );
	echo '<input type="hidden" name="action" value="aw_media_merge">';
	echo '<p><button type="submit" name="do" value="batch" class="button button-primary">Merge the next 10 groups</button></p>';
	$ids = array();
	foreach ( $groups as $keep => $extras ) {
		$ids = array_merge( $ids, array( $keep ), $extras );
	}
	$use  = aw_media_usage( $ids );
	$rows = array();
	foreach ( $groups as $keep => $extras ) {
		$e      = $log[ $keep ] ?? array();
		$status = $e['status'] ?? '';
		$links  = array();
		foreach ( $extras as $id ) {
			$links[] = '<a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . (int) $id . '</a>';
		}
		$uses = 0;
		foreach ( $extras as $id ) {
			$uses += count( $use[ $id ]['uses'] ?? array() );
		}
		if ( 'merged' === $status ) {
			$state  = 'Merged ' . esc_html( wp_date( 'j M Y', (int) $e['time'] ) );
			$action = '<button type="submit" name="do" value="undo:' . (int) $keep . '" class="button">Undo</button>';
		} elseif ( in_array( $status, array( 'blocked', 'failed' ), true ) ) {
			$state  = 'Blocked: ' . esc_html( $e['why'] );
			$action = '<button type="submit" name="do" value="retry:' . (int) $keep . '" class="button">Check again</button>';
		} elseif ( 'undone' === $status ) {
			$state  = 'Undone ' . esc_html( wp_date( 'j M Y', (int) $e['time'] ) );
			$action = '<button type="submit" name="do" value="retry:' . (int) $keep . '" class="button">Merge</button>';
		} else {
			$state  = 'Not merged yet';
			$action = '<button type="submit" name="do" value="merge:' . (int) $keep . '" class="button">Merge</button>';
		}
		$rows[] = array(
			'keep'   => '<a href="' . esc_url( get_edit_post_link( $keep ) ) . '">' . esc_html( wp_html_excerpt( (string) get_the_title( $keep ), 50, '…' ) ) . '</a> (' . (int) $keep . ')',
			'extras' => implode( ', ', $links ),
			'uses'   => (int) $uses,
			'state'  => $state,
			'action' => $action,
		);
	}
	aw_core_simple_table(
		'aw-media-duplicates',
		array( 'keep' => 'Copy kept', 'extras' => 'Extra copies (IDs)', 'uses' => 'Places using the extras', 'state' => 'Status', 'action' => 'Action' ),
		$rows,
		array( 'primary' => 'keep' )
	);
	echo '</form></div>';
}
