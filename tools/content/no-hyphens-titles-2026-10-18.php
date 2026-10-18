<?php
/**
 * No hyphens in titles (owner, 18 Oct 2026, standing: "no extra dashes or hyphens").
 *
 * For every item (listings, News, Library, Resources, Media & PR, Opportunities, campaigns, Pages) in any status:
 *   - post_title: a hyphen or dash joining two words becomes a space (aw_seo_no_hyphens); the slug is never touched;
 *   - Rank Math title: the same, in Title Case; the brand separator " – Animal Writes" stays; the title is marked as
 *     written (_aw_seo_auto title_written), and the automatic 'title' entry follows when it held the old value;
 *   - focus keywords: the same (a hyphen becomes a space), so each still stands in the title word for word.
 * Over 60 characters a News, listing or Opportunity title drops the site name (the earlier title rule).
 * Descriptions are left as written.
 *
 * Run on the server: wp eval-file no-hyphens-titles-2026-10-18.php dry|apply [skip_ids_comma_separated]
 * Writes a JSON report next to the script (credentials and personal data are not involved).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mode  = $args[0] ?? 'dry';
$skip  = array_filter( array_map( 'intval', explode( ',', $args[1] ?? '' ) ) );
$brand = ' – ' . get_bloginfo( 'name' );
$bl    = mb_strlen( $brand );
global $wpdb;

$types = implode( ',', array_map( fn( $t ) => "'" . esc_sql( $t ) . "'", AW_SEO_ITEM_TYPES ) );
$ids   = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ({$types}) AND post_status IN ('publish','draft','private','pending','future')" );

$cased = function ( $t ) { // is a title written in Title Case?
	preg_match_all( '/\b\p{L}{4,}\b/u', $t, $m );
	if ( count( $m[0] ) < 2 ) {
		return true;
	}
	$caps = count( array_filter( $m[0], fn( $w ) => preg_match( '/^\p{Lu}/u', $w ) ) );
	return $caps / count( $m[0] ) >= 0.6;
};

$rows = array();
$rm_all = array();
foreach ( $ids as $id ) {
	$id = (int) $id;
	$p  = get_post( $id );
	$rm = (string) get_post_meta( $id, 'rank_math_title', true );
	$rm_all[ $id ] = $rm;
	if ( in_array( $id, $skip, true ) || false !== strpos( $p->post_title . $rm, '://' ) ) { // an address used as a title is not text (Media & PR items without a headline)
		continue;
	}
	$kw  = (string) get_post_meta( $id, 'rank_math_focus_keyword', true );
	$new = array(
		'post_title' => aw_seo_no_hyphens( $p->post_title, $cased( $p->post_title ) ),
		'rm'         => aw_seo_no_hyphens_title( $rm, $cased( mb_substr( $rm, -$bl ) === $brand ? mb_substr( $rm, 0, -$bl ) : $rm ) ),
		'kw'         => aw_seo_no_hyphens( $kw, false ),
	);
	if ( $new['post_title'] === $p->post_title && $new['rm'] === $rm && $new['kw'] === $kw ) {
		continue;
	}
	// Over 60 characters with the site name: News, listings and Opportunities show the title alone.
	$note = '';
	if ( $new['rm'] !== $rm && mb_strlen( $new['rm'] ) > 60 && mb_substr( $new['rm'], -$bl ) === $brand ) {
		if ( in_array( $p->post_type, array( 'aw_news', 'aw_listing', 'aw_opportunity' ), true ) ) {
			$new['rm'] = mb_substr( $new['rm'], 0, -$bl );
			$note      = 'brand dropped (over 60)';
		} elseif ( 'aw_press' !== $p->post_type ) { // Media & PR items have no page of their own
			$note = 'OVER 60 with brand, check';
		}
	}
	$bare = fn( $s ) => mb_substr( $s, -$bl ) === $brand ? mb_substr( $s, 0, -$bl ) : $s;
	$rows[ $id ] = array(
		'id'        => $id,
		'type'      => $p->post_type,
		'status'    => $p->post_status,
		'title'     => array( $p->post_title, $new['post_title'] ),
		'rm'        => array( $rm, $new['rm'] ),
		'kw'        => array( $kw, $new['kw'] ),
		'kw_in_before' => '' !== $kw && aw_seo_keyword_in_title( trim( explode( ',', $kw )[0] ), $bare( $rm ) ),
		'kw_in_after'  => '' !== $kw && aw_seo_keyword_in_title( trim( explode( ',', $new['kw'] )[0] ), $bare( $new['rm'] ) ),
		'note'      => $note,
	);
}

if ( 'apply' === $mode ) {
	foreach ( $rows as $id => $r ) {
		if ( $r['title'][0] !== $r['title'][1] ) {
			$wpdb->update( $wpdb->posts, array( 'post_title' => $r['title'][1] ), array( 'ID' => $id ) ); // the slug stays
		}
		if ( $r['rm'][0] !== $r['rm'][1] ) {
			update_post_meta( $id, 'rank_math_title', wp_slash( $r['rm'][1] ) );
			$auto = (array) json_decode( (string) get_post_meta( $id, '_aw_seo_auto', true ), true );
			if ( isset( $auto['title'] ) && $auto['title'] === $r['rm'][0] ) {
				$auto['title'] = $r['rm'][1];
			}
			$auto['title_written'] = $r['rm'][1];
			update_post_meta( $id, '_aw_seo_auto', wp_slash( wp_json_encode( $auto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
		}
		if ( $r['kw'][0] !== $r['kw'][1] ) {
			update_post_meta( $id, 'rank_math_focus_keyword', wp_slash( $r['kw'][1] ) );
		}
		clean_post_cache( $id );
	}
}

// Titles that are now the same as another item's (Rank Math title).
$after = array();
foreach ( $rm_all as $id => $v ) {
	$after[ $id ] = isset( $rows[ $id ] ) ? $rows[ $id ]['rm'][1] : $v;
}
$dups = array();
foreach ( array_count_values( array_filter( $after ) ) as $t => $n ) {
	if ( $n > 1 ) {
		$dups[ $t ] = array_keys( $after, $t, true );
	}
}

$out = ( getenv( 'HOME' ) ?: '/tmp' ) . '/no-hyphens-report-' . $mode . '.json';
file_put_contents( $out, wp_json_encode( array( 'rows' => array_values( $rows ), 'duplicates' => $dups ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
$by = array();
foreach ( $rows as $r ) {
	$by[ $r['type'] ] = ( $by[ $r['type'] ] ?? 0 ) + 1;
}
echo wp_json_encode( array( 'mode' => $mode, 'changed' => count( $rows ), 'by_type' => $by, 'duplicates' => count( $dups ), 'report' => $out ) ), "\n";
