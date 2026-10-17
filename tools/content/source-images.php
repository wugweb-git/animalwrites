<?php
/**
 * Source pictures for every item (owner, 17–18 Oct 2026): re-read the source page's og:image / twitter:image for items
 * with no picture of their own (link-preview crawler User-Agents first, then a browser; YouTube's own cover; platform
 * logos and a site's default picture rejected), and keep every publisher picture in the Media Library (no hotlinking).
 *
 *   wp eval-file tools/content/source-images.php            dry run: counts per type, nothing written
 *   AW_APPLY=1 wp eval-file tools/content/source-images.php
 *
 * 1. Media & PR items with no thumbnail: aw_press_fetch() (press.php) reads the page and stores the picture.
 * 2. News, Library, guides/research, Opportunities with no usable publisher picture: the picture found is stored as
 *    aw_source_image (the old one kept in aw_source_image_prev) and checked.
 * 3. Every linked publisher picture is copied into the Media Library (aw_source_localise(), cc0-images.php).
 * Items still without a picture are listed; no picture is made up for them.
 *
 * @package animal-writes-core
 */

global $wpdb;
$apply = '1' === getenv( 'AW_APPLY' );
$host  = (string) wp_parse_url( home_url(), PHP_URL_HOST );
$count = array();
$miss  = array();
$add   = function ( $type, $k ) use ( &$count ) {
	$count[ $type ][ $k ] = ( $count[ $type ][ $k ] ?? 0 ) + 1;
};

// How many items share each linked picture: one used by three or more items is a site's default picture.
$shared = array();
foreach ( $wpdb->get_results( "SELECT meta_value v, COUNT(*) n FROM {$wpdb->postmeta} WHERE meta_key IN ('aw_source_image','aw_source_image_orig') AND meta_value LIKE 'https://%' GROUP BY meta_value HAVING n >= 3" ) as $r ) { // phpcs:ignore WordPress.DB
	$shared[ $r->v ] = (int) $r->n;
}

// 1. Media & PR.
foreach ( get_posts( array( 'post_type' => 'aw_press', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) {
	if ( has_post_thumbnail( $id ) ) {
		continue;
	}
	$url = (string) get_post_meta( $id, 'aw_press_url', true );
	$add( 'aw_press', 'without a picture' );
	if ( $apply ) {
		aw_press_fetch( $id );
		$ok = has_post_thumbnail( $id );
	} else {
		$img = aw_source_og_image( $url );
		$ok  = '' !== $img && ! isset( $shared[ $img ] );
	}
	$add( 'aw_press', $ok ? 'found' : 'still missing' );
	if ( $ok && $apply ) {
		WP_CLI::log( sprintf( '  press #%d %s -> attachment %d', $id, preg_match( '#/(?:reel|p|tv)/([A-Za-z0-9_-]+)#', $url, $m ) ? $m[1] : wp_parse_url( $url, PHP_URL_HOST ), get_post_thumbnail_id( $id ) ) );
	}
	if ( ! $ok ) {
		$miss[] = array( $id, 'aw_press', get_the_title( $id ), $url );
	}
}

// 2. Ingested items with no usable publisher picture.
$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB
	"SELECT p.ID FROM {$wpdb->posts} p
	JOIN {$wpdb->postmeta} su ON su.post_id = p.ID AND su.meta_key = 'aw_source_url' AND su.meta_value LIKE 'http%'
	LEFT JOIN {$wpdb->postmeta} si ON si.post_id = p.ID AND si.meta_key = 'aw_source_image'
	LEFT JOIN {$wpdb->postmeta} ok ON ok.post_id = p.ID AND ok.meta_key = 'aw_source_image_ok'
	WHERE p.post_status = 'publish' AND p.post_type IN ('aw_news','aw_legal','post','aw_opportunity')
	AND ( si.meta_value IS NULL OR si.meta_value = '' OR ok.meta_value = '0' )
	ORDER BY p.post_date DESC"
);
foreach ( $ids as $id ) {
	$type = get_post_type( $id );
	$src  = (string) get_post_meta( $id, 'aw_source_url', true );
	$add( $type, 'without a usable picture' );
	if ( get_post_meta( $id, 'aw_doc_preview', true ) || get_post_meta( $id, 'aw_file_id', true ) ) {
		$add( $type, 'has its own document page (kept)' );
		continue;
	}
	$img  = aw_source_og_image( $src );
	$have = (string) get_post_meta( $id, 'aw_source_image', true );
	$ok   = '' !== $img && $img !== $have && ! isset( $shared[ $img ] );
	if ( $ok && $apply ) {
		if ( '' !== $have ) {
			update_post_meta( $id, 'aw_source_image_prev', $have );
		}
		update_post_meta( $id, 'aw_source_image', $img );
		$why = aw_cc0_check_source( (int) $id );
		update_post_meta( $id, 'aw_source_image_ok', '' === $why ? 1 : 0 );
		update_post_meta( $id, 'aw_source_image_checked', current_time( 'mysql', true ) );
		update_post_meta( $id, 'aw_source_image_why', $why );
		$ok = '' === $why;
	}
	if ( $apply ) {
		update_post_meta( $id, 'aw_source_og_tried', current_time( 'mysql', true ) );
	}
	$add( $type, $ok ? 'found' : 'still missing' );
	if ( ! $ok ) {
		$miss[] = array( $id, $type, get_the_title( $id ), $src );
	}
	usleep( 300000 );
}

// 3. Linked publisher pictures into the Media Library.
$linked = $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} si ON si.post_id = p.ID AND si.meta_key = 'aw_source_image' AND si.meta_value LIKE 'https://%%' AND si.meta_value NOT LIKE %s LEFT JOIN {$wpdb->postmeta} ok ON ok.post_id = p.ID AND ok.meta_key = 'aw_source_image_ok' WHERE p.post_status = 'publish' AND ( ok.meta_value IS NULL OR ok.meta_value <> '0' ) ORDER BY p.post_date DESC", '%' . $wpdb->esc_like( $host ) . '%' ) ); // phpcs:ignore WordPress.DB
foreach ( $linked as $id ) {
	$type = get_post_type( $id );
	$add( $type, 'linked picture to store' );
	if ( $apply ) {
		update_post_meta( $id, 'aw_source_localise_tried', current_time( 'mysql', true ) );
		$add( $type, aw_source_localise( (int) $id ) ? 'stored' : 'store failed' );
	}
}

foreach ( $count as $t => $c ) {
	WP_CLI::log( $t . ': ' . wp_json_encode( $c ) );
}
WP_CLI::log( 'Still without a picture (' . count( $miss ) . '):' );
foreach ( $miss as $m ) {
	WP_CLI::log( sprintf( '  #%d %s | %s | %s', $m[0], $m[1], mb_substr( wp_strip_all_tags( $m[2] ), 0, 70 ), $m[3] ) );
}
WP_CLI::success( $apply ? 'APPLIED' : 'DRY RUN: nothing written' );
