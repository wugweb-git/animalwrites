<?php
/**
 * Media → Duplicate files: merges the duplicate copies of a file that the Media Library audit found.
 *
 * Trash is off on this site (MEDIA_TRASH), so deleting an attachment would be permanent. The retired copies get no trash
 * time on purpose: WordPress's daily clean-up removes trash older than 30 days by that time, and this tool never deletes. This tool never deletes: for each
 * group it repoints every reference from the extra copies to one kept copy, checks that nothing still points at them, and
 * retires the extras by giving them the Trash status (files stay on disk). Every change is saved with its old value before
 * it is made, so Undo (or a half-finished group) puts each reference and the copies back. Permanent deletion is a separate,
 * later step.
 *
 * A group is blocked, with the reason shown, when an extra is the featured image of a post, a hosted file or document
 * preview, a category or tag photo, the site icon, named in an option, or the two files differ. Only the source image,
 * social image and share image of a post (with the matching Rank Math address), a campaign's share images, a listing's
 * photo list, and the image addresses and block image IDs written in post content, are repointed.
 * Admin screen: core list table and core notices only (no CSS, no scripts).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Post meta keys that hold an attachment ID and may be repointed (the same picture; nothing is "used once"). */
function aw_media_merge_meta_keys() {
	return array( 'aw_source_image_att', 'rank_math_facebook_image_id', 'rank_math_twitter_image_id', 'kits_og_image', 'aw_campaign_share_story', 'aw_campaign_share_post' );
}

/** Post meta keys that hold the address of the picture next to an ID key above (repointed with it). */
function aw_media_merge_url_keys() {
	return array( 'rank_math_facebook_image', 'rank_math_twitter_image' );
}

/** The groups to merge: keep ID => extra IDs. */
function aw_media_merge_groups() {
	static $groups = null;
	if ( null === $groups ) {
		$groups = (array) require __DIR__ . '/data/media-duplicate-groups.php';
	}
	return $groups;
}

/** What happened to each group: keep ID => array( status pending|merged|undone|blocked|failed, why, time ). */
function aw_media_merge_log() {
	return (array) get_option( 'aw_media_merge_log', array() );
}

/** Save one group's status line. */
function aw_media_merge_set_status( $keep, $status, $why = '', array $more = array() ) {
	$log          = aw_media_merge_log();
	$log[ $keep ] = array( 'status' => $status, 'why' => $why, 'time' => time() ) + $more;
	update_option( 'aw_media_merge_log', $log, false );
}

/** The old values of one group (one option per group, so no option grows large). */
function aw_media_merge_data( $keep ) {
	return (array) get_option( 'aw_media_merge_data_' . (int) $keep, array() );
}

/** Save the old values of one group. */
function aw_media_merge_save_data( $keep, array $data ) {
	$key = 'aw_media_merge_data_' . (int) $keep;
	return update_option( $key, $data, false ) || get_option( $key ) === $data;
}

/**
 * Every address of an attachment (full size and each resized copy), by size name.
 *
 * @param int  $id      Attachment ID.
 * @param bool $on_disk Only the sizes whose file exists (for the kept copy, so a rewritten address never points at a missing file).
 * @return string[]
 */
function aw_media_merge_urls( $id, $on_disk = false ) {
	$full = (string) wp_get_attachment_url( $id );
	if ( '' === $full ) {
		return array();
	}
	$urls = array( 'full' => $full );
	$meta = wp_get_attachment_metadata( $id );
	$dir  = trailingslashit( dirname( $full ) );
	$path = $on_disk ? trailingslashit( dirname( (string) get_attached_file( $id ) ) ) : '';
	foreach ( (array) ( $meta['sizes'] ?? array() ) as $size => $info ) {
		if ( ! empty( $info['file'] ) && ( ! $on_disk || is_file( $path . $info['file'] ) ) ) {
			$urls[ $size ] = $dir . $info['file'];
		}
	}
	return $urls;
}

/**
 * The extra copies of a group without the kept one.
 *
 * @param int $keep Copy to keep.
 * @return int[]
 */
function aw_media_merge_extras( $keep ) {
	$groups = aw_media_merge_groups();
	return array_values( array_diff( array_map( 'intval', (array) ( $groups[ $keep ] ?? array() ) ), array( (int) $keep ) ) );
}

/**
 * Whether a group can be merged now, and what it would change. Reads only.
 *
 * @param int   $keep   Copy to keep.
 * @param int[] $extras Copies to retire.
 * @return array ok (bool), why (string), meta (rows to repoint: meta_id, post_id, key, old, new), posts (IDs whose content has an old address or block ID).
 */
function aw_media_merge_check( $keep, array $extras ) {
	global $wpdb;
	$fail   = function ( $why ) {
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
	$ids     = array_map( 'strval', $extras ); // Exact text match, so '14468abc' or '14468.5' never counts as an ID.
	$in      = implode( ',', array_fill( 0, count( $ids ), '%s' ) );
	$allowed = aw_media_merge_meta_keys();
	$known   = array_values( array_unique( array_merge( function_exists( 'aw_media_post_meta_uses' ) ? array_keys( aw_media_post_meta_uses() ) : array(), $allowed ) ) );
	$kin     = $known ? implode( ',', array_fill( 0, count( $known ), '%s' ) ) : "''";
	// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- the lists are only %s placeholders joined with implode or array_fill; every value is bound through prepare()
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_value IN ($in) AND ( meta_key REGEXP 'image|img|logo|thumb|icon|photo|file|preview|_att|banner|picture|attachment|hero|cover|poster|avatar|_id$|_og' OR meta_key IN ($kin) )", array_merge( $ids, $known ) ) );
	$meta = array();
	foreach ( (array) $rows as $r ) {
		if ( '_wp_attachment_context' === $r->meta_key ) {
			continue;
		}
		if ( ! in_array( $r->meta_key, $allowed, true ) ) {
			return $fail( 'used by ' . $r->meta_key . ' on post ' . (int) $r->post_id );
		}
		$meta[] = array( 'meta_id' => (int) $r->meta_id, 'post_id' => (int) $r->post_id, 'key' => $r->meta_key, 'old' => (string) $r->meta_value, 'new' => (string) $keep );
	}
	// Listing photo lists (aw_gallery, comma-separated IDs): each extra becomes the kept copy, without repeats.
	$grows = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'aw_gallery' AND meta_value REGEXP %s", '(^|,)[[:space:]]*(' . implode( '|', $ids ) . ')[[:space:]]*(,|$)' ) );
	foreach ( (array) $grows as $r ) {
		$list = array();
		foreach ( explode( ',', (string) $r->meta_value ) as $one ) {
			$one = trim( $one );
			if ( '' === $one ) {
				continue;
			}
			$one = in_array( $one, $ids, true ) ? (string) $keep : $one;
			if ( ! in_array( $one, $list, true ) ) {
				$list[] = $one;
			}
		}
		$meta[] = array( 'meta_id' => (int) $r->meta_id, 'post_id' => (int) $r->post_id, 'key' => 'aw_gallery', 'old' => (string) $r->meta_value, 'new' => implode( ',', $list ) );
	}
	// The picture addresses Rank Math keeps beside its ID keys.
	$kurls = aw_media_merge_urls( $keep );
	$ukeys = aw_media_merge_url_keys();
	$uin   = implode( ',', array_fill( 0, count( $ukeys ), '%s' ) );
	foreach ( $extras as $id ) {
		$full = (string) wp_get_attachment_url( $id );
		if ( '' === $full || empty( $kurls['full'] ) ) {
			continue;
		}
		$urows = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key IN ($uin) AND meta_value = %s", array_merge( $ukeys, array( $full ) ) ) );
		foreach ( (array) $urows as $r ) {
			$meta[] = array( 'meta_id' => (int) $r->meta_id, 'post_id' => (int) $r->post_id, 'key' => $r->meta_key, 'old' => (string) $r->meta_value, 'new' => $kurls['full'] );
		}
	}
	$trows = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->termmeta} WHERE meta_value IN ($in)", $ids ) );
	if ( (int) $trows ) {
		return $fail( 'used as a category or tag photo' );
	}
	$terms = array();
	$args  = array();
	foreach ( $extras as $id ) {
		$terms[] = 'option_value = %s OR option_value LIKE %s OR option_value LIKE %s OR option_value LIKE %s OR option_value LIKE %s OR option_value LIKE %s OR option_value LIKE %s';
		array_push( $args, (string) $id, '%i:' . $id . ';%', '%"' . $id . '"%', '%:' . $id . ',%', '%:' . $id . '}%', '%[' . $id . ',%', '%,' . $id . ']%' );
	}
	$hit = $wpdb->get_var( $wpdb->prepare( 'SELECT option_name FROM ' . $wpdb->options . " WHERE option_name NOT LIKE %s AND option_name NOT LIKE %s AND option_name NOT LIKE %s AND ( " . implode( ' OR ', $terms ) . ' ) LIMIT 1', array_merge( array( $wpdb->esc_like( 'aw_media_merge_' ) . '%', $wpdb->esc_like( '_transient_' ) . '%', $wpdb->esc_like( '_site_transient_' ) . '%' ), $args ) ) );
	if ( $hit ) {
		return $fail( 'named in the option ' . $hit );
	}
	// Posts that write one of the old addresses or block image IDs (exact addresses only).
	$likes  = array();
	$largs  = array();
	foreach ( $extras as $id ) {
		foreach ( array_unique( aw_media_merge_urls( $id ) ) as $url ) {
			$likes[] = 'post_content LIKE %s';
			$largs[] = '%' . $wpdb->esc_like( $url ) . '%';
		}
	}
	$rx      = '"(id|mediaId)":(' . implode( '|', $ids ) . ')([^0-9]|$)|data-id="(' . implode( '|', $ids ) . ')"|wp-image-(' . implode( '|', $ids ) . ')([^0-9]|$)';
	$likes[] = 'post_content REGEXP %s';
	$largs[] = $rx;
	$found   = $wpdb->get_col( $wpdb->prepare( 'SELECT ID FROM ' . $wpdb->posts . " WHERE post_type NOT IN ( 'attachment', 'revision' ) AND ( " . implode( ' OR ', $likes ) . ' ) LIMIT 300', $largs ) );
	if ( count( $found ) >= 300 ) {
		return $fail( 'too many pages (300 or more) write one of the addresses' );
	}
	$posts = array_map( 'intval', $found );
	// A gallery lists image IDs in one place; a group used there is blocked rather than rewritten.
	$gal = $wpdb->get_var( $wpdb->prepare( 'SELECT ID FROM ' . $wpdb->posts . " WHERE post_type NOT IN ( 'attachment', 'revision' ) AND post_content LIKE %s AND post_content REGEXP %s LIMIT 1", '%ids%', '[^0-9](' . implode( '|', $ids ) . ')[^0-9]' ) );
	if ( $gal ) {
		return $fail( 'listed in a gallery on post ' . (int) $gal );
	}
	// phpcs:enable
	return array( 'ok' => true, 'why' => '', 'meta' => $meta, 'posts' => array_values( array_unique( $posts ) ) );
}

/** Give the extras their Trash status (files stay) or put them back. */
function aw_media_merge_retire( array $extras, $keep, $retire ) {
	global $wpdb;
	foreach ( $extras as $id ) {
		if ( $retire ) {
			update_post_meta( $id, '_wp_trash_meta_status', 'inherit' );
			update_post_meta( $id, 'aw_merged_into', (int) $keep );
		} else {
			delete_post_meta( $id, '_wp_trash_meta_status' );
			delete_post_meta( $id, '_wp_trash_meta_time' );
			delete_post_meta( $id, 'aw_merged_into' );
		}
		$wpdb->update( $wpdb->posts, array( 'post_status' => $retire ? 'trash' : 'inherit' ), array( 'ID' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		clean_post_cache( (int) $id );
	}
}

/**
 * Put the references and post content of a group back as they were. A value or a post that changed after the merge (an
 * edit, or another group's merge) is left alone, so a later change is never overwritten.
 *
 * @param array $data Saved old values of the group.
 * @return int Number of things left as they are.
 */
function aw_media_merge_restore( array $data ) {
	global $wpdb;
	$skipped = 0;
	foreach ( array_reverse( (array) ( $data['meta'] ?? array() ) ) as $m ) {
		$now = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_id = %d", $m['meta_id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( (string) $now !== (string) $m['new'] && (string) $now !== (string) $m['old'] ) {
			++$skipped;
			continue;
		}
		$wpdb->update( $wpdb->postmeta, array( 'meta_value' => $m['old'] ), array( 'meta_id' => $m['meta_id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_cache_delete( $m['post_id'], 'post_meta' );
	}
	foreach ( array_reverse( (array) ( $data['content'] ?? array() ), true ) as $pid => $c ) {
		$now = (string) get_post_field( 'post_content', (int) $pid );
		if ( md5( $now ) !== $c['hash'] && $now !== $c['old'] ) {
			++$skipped;
			continue;
		}
		$wpdb->update( $wpdb->posts, array( 'post_content' => $c['old'] ), array( 'ID' => (int) $pid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		clean_post_cache( (int) $pid );
	}
	return $skipped;
}

/**
 * Merge one group: save, repoint, check, retire. If the check after repointing finds a reference, everything is put back.
 * The old values are saved before each step, so a group that stopped half way can be put back too.
 *
 * @param int $keep Copy to keep.
 * @return array status (merged|blocked|failed), why.
 */
function aw_media_merge_group( $keep ) {
	global $wpdb;
	$keep   = (int) $keep;
	$extras = aw_media_merge_extras( $keep );
	$log    = aw_media_merge_log();
	$state  = $log[ $keep ]['status'] ?? '';
	if ( 'merged' === $state ) {
		return array( 'status' => 'merged', 'why' => 'already merged' );
	}
	if ( 'pending' === $state ) { // An earlier run of this group stopped half way: put it back first.
		aw_media_merge_restore( aw_media_merge_data( $keep ) );
		aw_media_merge_retire( $extras, $keep, false );
	}
	$c = aw_media_merge_check( $keep, $extras );
	if ( ! $c['ok'] ) {
		aw_media_merge_set_status( $keep, 'blocked', $c['why'] );
		return array( 'status' => 'blocked', 'why' => $c['why'] );
	}
	$data = array( 'keep' => $keep, 'extras' => $extras, 'meta' => array(), 'content' => array(), 'time' => time(), 'user' => get_current_user_id() );
	aw_media_merge_set_status( $keep, 'pending' );
	// 1. Repoint the picture references (the old values are saved first).
	$data['meta'] = $c['meta'];
	if ( ! aw_media_merge_save_data( $keep, $data ) ) {
		aw_media_merge_set_status( $keep, 'failed', 'the old values could not be saved; nothing was changed' );
		return array( 'status' => 'failed', 'why' => 'the old values could not be saved' );
	}
	foreach ( $c['meta'] as $m ) {
		$wpdb->update( $wpdb->postmeta, array( 'meta_value' => $m['new'] ), array( 'meta_id' => $m['meta_id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_cache_delete( $m['post_id'], 'post_meta' );
	}
	// 2. Replace the image addresses and block image IDs written in post content (each post's old text is saved first).
	$kurls = aw_media_merge_urls( $keep, true );
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
			$new = preg_replace( '/wp-image-' . (int) $id . '(?!\d)/', 'wp-image-' . $keep, $new );
			$new = preg_replace( '/"(id|mediaId)":' . (int) $id . '(?!\d)/', '"$1":' . $keep, $new );
			$new = preg_replace( '/data-id="' . (int) $id . '"/', 'data-id="' . $keep . '"', $new );
		}
		if ( $new !== $post->post_content ) {
			$data['content'][ $pid ] = array( 'old' => $post->post_content, 'hash' => md5( $new ) );
			if ( ! aw_media_merge_save_data( $keep, $data ) ) {
				unset( $data['content'][ $pid ] );
				$left = aw_media_merge_restore( $data );
				aw_media_merge_set_status( $keep, $left ? 'pending' : 'failed', 'the old values could not be saved; the group was put back' );
				return array( 'status' => 'failed', 'why' => 'the old values could not be saved' );
			}
			$wpdb->update( $wpdb->posts, array( 'post_content' => $new ), array( 'ID' => $pid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			clean_post_cache( $pid );
		}
	}
	// 3. Check that nothing still points at the extras.
	$left = aw_media_merge_check( $keep, $extras );
	if ( ! $left['ok'] || $left['meta'] || $left['posts'] ) {
		$kept = aw_media_merge_restore( $data );
		aw_media_merge_set_status( $keep, $kept ? 'pending' : 'failed', 'a reference was still left after repointing' . ( $kept ? '; ' . $kept . ' item(s) were edited meanwhile and still need Put back' : '; everything was put back' ) );
		return array( 'status' => 'failed', 'why' => 'a reference was still left after repointing' );
	}
	// 4. Retire the extras (Trash status; files stay).
	aw_media_merge_retire( $extras, $keep, true );
	aw_media_merge_set_status( $keep, 'merged', '', array( 'extras' => $extras ) );
	return array( 'status' => 'merged', 'why' => '' );
}

/** Undo a merged group: references, content and the copies come back. */
function aw_media_merge_undo( $keep ) {
	$keep = (int) $keep;
	$log  = aw_media_merge_log();
	if ( empty( $log[ $keep ] ) || ! in_array( $log[ $keep ]['status'], array( 'merged', 'pending' ), true ) ) {
		return false;
	}
	$skipped = aw_media_merge_restore( aw_media_merge_data( $keep ) );
	aw_media_merge_retire( aw_media_merge_extras( $keep ), $keep, false );
	aw_media_merge_set_status( $keep, 'undone', '', array( 'skipped' => $skipped ) );
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
			wp_die( esc_html( 'Not allowed.' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'aw_media_merge' );
		$do   = sanitize_text_field( wp_unslash( $_POST['do'] ?? '' ) );
		$args = array( 'page' => 'aw-media-duplicates' );
		if ( 'batch' === $do ) {
			$done  = 0;
			$stop  = microtime( true ) + 40;
			$heavy = 0;
			if ( function_exists( 'set_time_limit' ) ) {
				set_time_limit( 120 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
			}
			$log  = aw_media_merge_log();
			foreach ( array_keys( aw_media_merge_groups() ) as $keep ) {
				if ( isset( $log[ $keep ] ) && in_array( $log[ $keep ]['status'], array( 'merged', 'blocked', 'failed', 'undone' ), true ) ) {
					continue;
				}
				if ( $done >= 10 || $heavy > 40 || microtime( true ) > $stop ) {
					break;
				}
				aw_media_merge_group( (int) $keep );
				$heavy += count( aw_media_merge_extras( (int) $keep ) );
				++$done;
			}
			$args['aw_mm'] = $done;
		} elseif ( preg_match( '/^(merge|undo|retry):(\d+)$/', $do, $m ) ) {
			$keep = (int) $m[2];
			if ( ! isset( aw_media_merge_groups()[ $keep ] ) ) {
				wp_safe_redirect( add_query_arg( $args, admin_url( 'upload.php' ) ) );
				exit;
			}
			if ( 'undo' === $m[1] ) {
				aw_media_merge_undo( $keep );
				$args['aw_mm_undo'] = $keep;
				$after              = aw_media_merge_log();
				$args['aw_mm_skip'] = (int) ( $after[ $keep ]['skipped'] ?? 0 );
			} else {
				$log = aw_media_merge_log();
				if ( 'retry' === $m[1] && ! in_array( $log[ $keep ]['status'] ?? '', array( 'merged', 'pending' ), true ) ) {
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
	if ( isset( $_GET['aw_mm'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-success is-dismissible"><p>Checked ' . (int) $_GET['aw_mm'] . ' group(s). Merged groups show the date; blocked ones show why.</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	if ( isset( $_GET['aw_mm_undo'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-success is-dismissible"><p>Group ' . (int) $_GET['aw_mm_undo'] . ' put back as it was.' . ( ! empty( $_GET['aw_mm_skip'] ) ? ' ' . (int) $_GET['aw_mm_skip'] . ' item(s) changed after the merge and keep their newer value.' : '' ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
		} elseif ( 'pending' === $status ) {
			$state  = 'Stopped half way' . ( ! empty( $e['why'] ) ? ': ' . esc_html( $e['why'] ) : '' );
			$action = '<button type="submit" name="do" value="undo:' . (int) $keep . '" class="button">Put back</button>';
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
