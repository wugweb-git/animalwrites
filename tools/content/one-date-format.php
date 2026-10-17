<?php
/**
 * One date format (owner, 17 Oct 2026: "we keep only one format in dates"): every written date becomes j M Y
 * ("4th October, 2026", "October 15, 2026", "28 September 2026" -> "4 Oct 2026", "15 Oct 2026", "28 Sep 2026").
 *
 *   wp eval-file tools/content/one-date-format.php          dry run: counts and examples, nothing written
 *   AW_APPLY=1 wp eval-file tools/content/one-date-format.php
 *
 * Fields: title, summary (excerpt), body, Rank Math title and description, of every published item. The body of a
 * Library document (aw_legal) is left as it is: it is the Gazette's own text. Rows are written directly, so the
 * published and modified dates do not change; every old value goes to a backup file first (~/aw-date-backup-*.json).
 *
 * @package animal-writes-core
 */

global $wpdb;
$apply = '1' === getenv( 'AW_APPLY' );

/** A written date in j M Y; other text unchanged. */
function aw_one_date_format( $s ) {
	$m  = 'January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|Jun|Jul|Aug|Sept|Sep|Oct|Nov|Dec';
	$mo = fn( $x ) => substr( ucfirst( strtolower( $x ) ), 0, 3 );
	$s  = preg_replace_callback( '/\b(\d{1,2})(?:st|nd|rd|th)?(?:\s+day\s+of)?\s+(' . $m . ')\.?,?\s+(\d{4})\b/', fn( $x ) => (int) $x[1] . ' ' . $mo( $x[2] ) . ' ' . $x[3], $s );
	return preg_replace_callback( '/\b(' . $m . ')\.?\s+(\d{1,2})(?:st|nd|rd|th)?,?\s+(\d{4})\b/', fn( $x ) => (int) $x[2] . ' ' . $mo( $x[1] ) . ' ' . $x[3], $s );
}

$types = array_values( array_diff( get_post_types( array(), 'names' ), array( 'revision', 'attachment', 'nav_menu_item', 'wp_global_styles', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_font_face', 'wp_font_family', 'wp_navigation' ) ) );
$ids   = get_posts( array( 'post_type' => $types, 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) );
$backup = array();
$count  = array();
$shown  = array();
$todo   = array();
foreach ( $ids as $id ) {
	$p      = get_post( $id );
	$fields = array( 'post_title' => $p->post_title, 'post_excerpt' => $p->post_excerpt );
	if ( 'aw_legal' !== $p->post_type ) {
		$fields['post_content'] = $p->post_content;
	}
	$meta = array();
	foreach ( array( 'rank_math_title', 'rank_math_description' ) as $k ) {
		$meta[ $k ] = (string) get_post_meta( $id, $k, true );
	}
	$row = array();
	foreach ( $fields as $f => $old ) {
		$new = aw_one_date_format( (string) $old );
		if ( $new !== $old ) {
			$row[ $f ] = $new;
		}
	}
	$mrow = array();
	foreach ( $meta as $k => $old ) {
		$new = '' === $old ? $old : aw_one_date_format( $old );
		if ( $new !== $old ) {
			$mrow[ $k ] = $new;
		}
	}
	if ( ! $row && ! $mrow ) {
		continue;
	}
	$backup[ $id ] = array( 'type' => $p->post_type, 'fields' => array_intersect_key( $fields, $row ), 'meta' => array_intersect_key( $meta, $mrow ) );
	foreach ( array_keys( $row + $mrow ) as $f ) {
		$k           = $p->post_type . ' ' . $f;
		$count[ $k ] = ( $count[ $k ] ?? 0 ) + 1;
		if ( ( $shown[ $k ] ?? 0 ) < 2 && in_array( $f, array( 'post_title', 'post_excerpt', 'rank_math_title' ), true ) ) {
			$shown[ $k ] = ( $shown[ $k ] ?? 0 ) + 1;
			$was         = $fields[ $f ] ?? $meta[ $f ];
			WP_CLI::log( "  #$id $f: " . mb_substr( $was, 0, 110 ) . "\n     -> " . mb_substr( $row[ $f ] ?? $mrow[ $f ], 0, 110 ) );
		}
	}
	$todo[ $id ] = array( $row, $mrow );
}
ksort( $count );
foreach ( $count as $k => $n ) {
	WP_CLI::log( "$k: $n" );
}
if ( $apply ) {
	$file = getenv( 'HOME' ) . '/aw-date-backup-' . gmdate( 'Ymd-His' ) . '.json';
	if ( ! file_put_contents( $file, wp_json_encode( $backup ) ) ) {
		WP_CLI::error( 'Backup not written; nothing changed.' );
	}
	foreach ( $todo as $id => list( $row, $mrow ) ) {
		if ( $row ) {
			$wpdb->update( $wpdb->posts, $row, array( 'ID' => $id ) );
		}
		foreach ( $mrow as $k => $v ) {
			update_post_meta( $id, $k, $v );
		}
		clean_post_cache( $id );
	}
	WP_CLI::success( count( $backup ) . ' items changed; old values in ' . $file );
} else {
	WP_CLI::success( 'DRY RUN: ' . count( $backup ) . ' items would change; nothing written' );
}
