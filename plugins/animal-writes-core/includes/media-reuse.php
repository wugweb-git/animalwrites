<?php
/**
 * Media -> Reuse pictures: puts a picture that is already in the Media Library on a page as its cover (featured
 * image), one item at a time, with Undo.
 *
 * The list is data/media-reuse-plan.php (picture ID, page ID, confidence, undo-only). Nothing is applied by itself,
 * and no attachment is deleted, trashed or edited (its parent and text fields stay as they are).
 *
 * Apply first checks that the picture still exists and is an image, that it is not the cover (or the stored
 * publisher image) of another page, that no other page's cover is the same file, and that the page still exists
 * and shows a cover. It then saves the old values in option aw_media_reuse_log and sets the cover. A news item,
 * Library item or post that shows the publisher's image (cc0-images.php) also gets the flag aw_source_image_off = 1,
 * which aw_publisher_image() honours and the weekly re-check skips, so the cover leads and the re-check cannot
 * switch the publisher image back on. Undo puts the old cover back and removes the flag, but only while the page
 * still has the values Apply set.
 * Admin screen: core list table and core notices only (no CSS, no scripts).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Page types the screen may change. */
function aw_media_reuse_types() {
	return array( 'aw_news', 'aw_legal', 'aw_listing', 'post' );
}

/** Types whose page may show the publisher's image ahead of the cover (media.php). */
function aw_media_reuse_source_types() {
	return array( 'aw_news', 'aw_legal', 'post' );
}

/**
 * The plan rows, one per picture and one per page.
 *
 * @return array[] Each: picture, target, confidence, undo_only.
 */
function aw_media_reuse_plan() {
	static $plan = null;
	if ( null === $plan ) {
		$plan = array();
		foreach ( (array) require __DIR__ . '/data/media-reuse-plan.php' as $r ) {
			$plan[ (int) $r[0] ] = array(
				'picture'    => (int) $r[0],
				'target'     => (int) $r[1],
				'confidence' => (string) $r[2],
				'undo_only'  => ! empty( $r[3] ),
			);
		}
	}
	return $plan;
}

/** What happened to each picture: picture ID => array( status applied|undone, time, target, old cover, old flag, new flag ). */
function aw_media_reuse_log() {
	return (array) get_option( 'aw_media_reuse_log', array() );
}

/**
 * Save the log.
 *
 * @param array $log Whole log.
 * @return bool Whether the stored value is now the log.
 */
function aw_media_reuse_save_log( array $log ) {
	return update_option( 'aw_media_reuse_log', $log, false ) || get_option( 'aw_media_reuse_log' ) === $log;
}

/**
 * Whether a page shows its own document (hosted PDF or its first page) instead of a cover.
 *
 * @param int $id Page ID.
 * @return bool
 */
function aw_media_reuse_is_document( $id ) {
	return (int) get_post_meta( $id, 'aw_file_id', true ) > 0 || (int) get_post_meta( $id, 'aw_doc_preview', true ) > 0;
}

/**
 * What a page shows now.
 *
 * @param int $id Page ID.
 * @return array kind (publisher|cover|none), cover (attachment ID), text (for the list).
 */
function aw_media_reuse_current( $id ) {
	$cover  = (int) get_post_meta( $id, '_thumbnail_id', true );
	$source = in_array( get_post_type( $id ), aw_media_reuse_source_types(), true ) && function_exists( 'aw_publisher_image' ) && aw_is_ingested( $id ) && '' !== aw_publisher_image( $id );
	if ( aw_media_reuse_is_document( $id ) ) {
		return array( 'kind' => 'document', 'cover' => $cover, 'text' => 'Its own document page' );
	}
	if ( $source ) {
		return array( 'kind' => 'publisher', 'cover' => $cover, 'text' => 'Publisher image' . ( $cover ? ' (cover ' . $cover . ' behind it)' : '' ) );
	}
	if ( $cover ) {
		return array( 'kind' => 'cover', 'cover' => $cover, 'text' => 'Cover ' . $cover );
	}
	return array( 'kind' => 'none', 'cover' => 0, 'text' => 'None' );
}

/**
 * Whether another page already uses the picture (or the same file) as its cover or stored publisher image.
 *
 * @param int $picture Attachment ID.
 * @param int $target  Page that is about to get it.
 * @return string Why not, or '' when it is free.
 */
function aw_media_reuse_taken( $picture, $target ) {
	global $wpdb;
	$other = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('_thumbnail_id','aw_source_image_att') AND meta_value = %s AND post_id <> %d LIMIT 1",
			(string) $picture,
			$target
		)
	);
	if ( $other ) {
		return 'the picture is already used by page ' . (int) $other;
	}
	if ( function_exists( 'aw_thumb_hash' ) ) { // the site rule: the same file is not a cover twice (thumbs.php)
		$hash = aw_thumb_hash( $picture );
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT pm.post_id, pm.meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_thumbnail_id' AND p.post_status = 'publish' AND pm.post_id <> %d",
				$target
			)
		);
		foreach ( (array) $rows as $r ) {
			if ( (int) $r->meta_value !== $picture && (int) $r->meta_value > 0 && aw_thumb_hash( (int) $r->meta_value ) === $hash ) {
				return 'the same file is already the cover of page ' . (int) $r->post_id;
			}
		}
	}
	return '';
}

/**
 * Whether a plan row can be applied now. Reads only.
 *
 * @param array $row Plan row.
 * @return string Why not, or '' when it can.
 */
function aw_media_reuse_why_not( array $row ) {
	$picture = get_post( $row['picture'] );
	if ( ! $picture || 'attachment' !== $picture->post_type ) {
		return 'picture ' . $row['picture'] . ' is missing';
	}
	if ( ! wp_attachment_is_image( $picture ) ) {
		return 'picture ' . $row['picture'] . ' is not an image';
	}
	$target = get_post( $row['target'] );
	if ( ! $target || ! in_array( $target->post_type, aw_media_reuse_types(), true ) || in_array( $target->post_status, array( 'trash', 'auto-draft' ), true ) ) {
		return 'page ' . $row['target'] . ' is missing';
	}
	$log = aw_media_reuse_log();
	if ( isset( $log[ $row['picture'] ] ) && 'applied' === $log[ $row['picture'] ]['status'] ) {
		return 'already applied';
	}
	if ( aw_media_reuse_is_document( $row['target'] ) ) {
		return 'page ' . $row['target'] . ' shows its own document page, not a cover';
	}
	if ( (int) get_post_meta( $row['target'], '_thumbnail_id', true ) === $row['picture'] ) {
		return 'the picture is already the cover of page ' . $row['target'];
	}
	return aw_media_reuse_taken( $row['picture'], $row['target'] );
}

/**
 * Apply one row: save the old values, then set the cover (and switch the publisher image off when it leads).
 *
 * @param int $picture Attachment ID of a plan row.
 * @return string Why it was not applied, or '' when it was.
 */
function aw_media_reuse_apply( $picture ) {
	$plan = aw_media_reuse_plan();
	if ( empty( $plan[ $picture ] ) ) {
		return 'picture ' . (int) $picture . ' is not in the list';
	}
	$row = $plan[ $picture ];
	$why = aw_media_reuse_why_not( $row );
	if ( '' !== $why ) {
		return ucfirst( $why );
	}
	$target   = $row['target'];
	$old_off  = metadata_exists( 'post', $target, 'aw_source_image_off' ) ? (string) get_post_meta( $target, 'aw_source_image_off', true ) : null;
	$set_off  = 'publisher' === aw_media_reuse_current( $target )['kind'];
	$log      = aw_media_reuse_log();
	$log[ $picture ] = array(
		'status'    => 'pending',
		'time'      => time(),
		'target'    => $target,
		'old_cover' => (int) get_post_meta( $target, '_thumbnail_id', true ),
		'old_off'   => $old_off,
		'set_off'   => $set_off,
	);
	if ( ! aw_media_reuse_save_log( $log ) ) {
		return 'The old values could not be saved, so nothing was changed.';
	}
	update_post_meta( $target, '_thumbnail_id', $picture );
	if ( $set_off ) {
		update_post_meta( $target, 'aw_source_image_off', '1' );
	}
	$ok = (int) get_post_meta( $target, '_thumbnail_id', true ) === $picture && ( ! $set_off || '1' === (string) get_post_meta( $target, 'aw_source_image_off', true ) );
	if ( ! $ok ) {
		aw_media_reuse_restore( $log[ $picture ], $picture, true );
		unset( $log[ $picture ] );
		aw_media_reuse_save_log( $log );
		return 'The change did not stick, so the old values were put back.';
	}
	$log[ $picture ]['status'] = 'applied';
	aw_media_reuse_save_log( $log );
	aw_media_reuse_refresh();
	return '';
}

/**
 * Put the old values of one entry back.
 *
 * @param array $e       Log entry.
 * @param int   $picture The picture Apply set.
 * @param bool  $force   Restore without checking the current values (used when Apply itself failed).
 * @return string Why not, or '' when restored.
 */
function aw_media_reuse_restore( array $e, $picture, $force = false ) {
	$target = (int) $e['target'];
	if ( ! $force ) {
		if ( ! get_post( $target ) ) {
			return 'page ' . $target . ' is gone';
		}
		if ( (int) get_post_meta( $target, '_thumbnail_id', true ) !== (int) $picture ) {
			return 'page ' . $target . ' has a different cover now, so it was left as it is';
		}
		if ( ! empty( $e['set_off'] ) && '1' !== (string) get_post_meta( $target, 'aw_source_image_off', true ) ) {
			return 'page ' . $target . ' changed its publisher image setting since, so it was left as it is';
		}
	}
	if ( (int) $e['old_cover'] > 0 ) {
		update_post_meta( $target, '_thumbnail_id', (int) $e['old_cover'] );
	} else {
		delete_post_meta( $target, '_thumbnail_id' );
	}
	if ( ! empty( $e['set_off'] ) ) {
		if ( null === $e['old_off'] ) {
			delete_post_meta( $target, 'aw_source_image_off' );
		} else {
			update_post_meta( $target, 'aw_source_image_off', $e['old_off'] );
		}
	}
	return '';
}

/**
 * Undo one applied row.
 *
 * @param int $picture Attachment ID.
 * @return string Why not, or '' when undone.
 */
function aw_media_reuse_undo( $picture ) {
	$log = aw_media_reuse_log();
	if ( empty( $log[ $picture ] ) || ! in_array( $log[ $picture ]['status'], array( 'applied', 'pending' ), true ) ) {
		return 'Picture ' . (int) $picture . ' was not applied.';
	}
	$why = aw_media_reuse_restore( $log[ $picture ], $picture );
	if ( '' !== $why ) {
		return ucfirst( $why ) . '.';
	}
	$log[ $picture ]['status'] = 'undone';
	$log[ $picture ]['time']   = time();
	aw_media_reuse_save_log( $log );
	aw_media_reuse_refresh();
	return '';
}

/** The public duplicate count is kept for 5 minutes and the category photos are assigned by a rebuild: refresh both. */
function aw_media_reuse_refresh() {
	delete_transient( 'aw_thumb_dupes_n' );
	if ( function_exists( 'aw_thumb_schedule' ) ) {
		aw_thumb_schedule();
	}
}

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'upload.php', 'Reuse pictures', 'Reuse pictures', 'manage_options', 'aw-media-reuse', 'aw_media_reuse_screen' );
	}
);

add_action(
	'admin_post_aw_media_reuse',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( 'Not allowed.' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'aw_media_reuse' );
		$do = sanitize_text_field( wp_unslash( $_POST['do'] ?? '' ) );
		if ( preg_match( '/^(apply|undo|check):(\d+)$/', $do, $m ) ) {
			$picture = (int) $m[2];
			$plan    = aw_media_reuse_plan();
			if ( 'apply' === $m[1] ) {
				$why = aw_media_reuse_apply( $picture );
				$msg = '' === $why ? array( 'success', 'Picture ' . $picture . ' is now the cover of page ' . $plan[ $picture ]['target'] . '.' ) : array( 'error', $why );
			} elseif ( 'undo' === $m[1] ) {
				$why = aw_media_reuse_undo( $picture );
				$msg = '' === $why ? array( 'success', 'Picture ' . $picture . ' taken off page ' . ( $plan[ $picture ]['target'] ?? '' ) . '; the old values are back.' ) : array( 'error', $why );
			} else {
				$why = isset( $plan[ $picture ] ) ? aw_media_reuse_why_not( $plan[ $picture ] ) : 'picture ' . $picture . ' is not in the list';
				$msg = '' === $why ? array( 'success', 'Picture ' . $picture . ' can be applied.' ) : array( 'warning', 'Picture ' . $picture . ': ' . $why . '.' );
			}
			set_transient( 'aw_media_reuse_notice_' . get_current_user_id(), $msg, 120 );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'aw-media-reuse' ), admin_url( 'upload.php' ) ) );
		exit;
	}
);

/** The Media -> Reuse pictures screen. */
function aw_media_reuse_screen() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$plan = aw_media_reuse_plan();
	$log  = aw_media_reuse_log();
	echo '<div class="wrap"><h1>Reuse pictures</h1>';
	$key    = 'aw_media_reuse_notice_' . get_current_user_id();
	$notice = get_transient( $key );
	if ( is_array( $notice ) ) {
		delete_transient( $key );
		$class = in_array( $notice[0], array( 'success', 'warning', 'error' ), true ) ? $notice[0] : 'info';
		echo '<div class="notice notice-' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( (string) $notice[1] ) . '</p></div>';
	}
	$applied = 0;
	foreach ( $log as $e ) {
		$applied += 'applied' === $e['status'] ? 1 : 0;
	}
	echo '<p>Pictures already in the Media Library, offered as the cover of a page. Apply sets the cover and, on a news item, Library item or post that shows the publisher image, switches that image off so the cover shows; the weekly check does not switch it back on. Undo puts the old cover back, as long as the page still has what Apply set. Nothing is applied by itself, and no picture is deleted or edited. A picture used by another page, or a page that no longer exists, is refused.</p>';
	echo '<p>' . count( $plan ) . ' pictures offered: ' . (int) $applied . ' applied.</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'aw_media_reuse' );
	echo '<input type="hidden" name="action" value="aw_media_reuse">';
	$rows = array();
	foreach ( $plan as $picture => $row ) {
		$e       = $log[ $picture ] ?? array();
		$status  = $e['status'] ?? '';
		$target  = get_post( $row['target'] );
		$current = $target ? aw_media_reuse_current( $row['target'] ) : array( 'text' => 'Page missing' );
		$image   = wp_get_attachment_image( $picture, array( 60, 60 ) );
		if ( in_array( $status, array( 'applied', 'pending' ), true ) ) {
			$state  = ( 'applied' === $status ? 'Applied ' : 'Stopped half way ' ) . wp_date( 'j M Y', (int) $e['time'] );
			$action = '<button type="submit" name="do" value="undo:' . (int) $picture . '" class="button">Undo</button>';
		} else {
			$state  = 'undone' === $status ? 'Undone ' . wp_date( 'j M Y', (int) $e['time'] ) : 'Not applied';
			$action = '<button type="submit" name="do" value="apply:' . (int) $picture . '" class="button button-primary">Apply</button> <button type="submit" name="do" value="check:' . (int) $picture . '" class="button">Check again</button>';
		}
		$rows[] = array(
			'picture'    => '<a href="' . esc_url( (string) get_edit_post_link( $picture ) ) . '">' . (int) $picture . '</a> ' . esc_html( wp_html_excerpt( (string) get_the_title( $picture ), 40, '…' ) ) . ( $image ? '<br>' . $image : '' ),
			'target'     => $target ? (int) $row['target'] . ' (' . esc_html( $target->post_type ) . ') <a href="' . esc_url( (string) get_permalink( $target ) ) . '">' . esc_html( wp_html_excerpt( (string) get_the_title( $target ), 60, '…' ) ) . '</a>' : (int) $row['target'] . ' (missing)',
			'current'    => esc_html( $current['text'] ),
			'confidence' => esc_html( $row['confidence'] ),
			'note'       => $row['undo_only'] ? 'Undo-only picture: applying it removes its undo copy.' : '',
			'state'      => esc_html( $state ),
			'action'     => $action,
		);
	}
	aw_core_simple_table(
		'aw-media-reuse',
		array(
			'picture'    => 'Picture',
			'target'     => 'Page',
			'current'    => 'Shows now',
			'confidence' => 'Confidence',
			'note'       => 'Note',
			'state'      => 'Status',
			'action'     => 'Action',
		),
		$rows,
		array( 'primary' => 'picture' )
	);
	echo '</form></div>';
}
