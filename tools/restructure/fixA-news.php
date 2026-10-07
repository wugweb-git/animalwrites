<?php
/**
 * Fix batch A, news: archive six off-topic items (unpublished, kept) and merge same-event duplicates into one item
 * with "also reported by", 301-ing the merged URLs. Run once: wp eval-file tools/restructure/fixA-news.php
 */
$log = array();

// Archive: unpublished (draft), kept, with the reason recorded.
$archive = array( 81 => 'BollySpice', 7500 => 'in.eventfaqs.com', 82 => 'storyboard18.com', 141 => 'Attenborough feature', 135 => 'YouTuber dog item', 133 => 'Meera Mehta chocolates item' );
foreach ( $archive as $id => $why ) {
	if ( 'publish' === get_post_status( $id ) ) {
		wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
		update_post_meta( $id, 'aw_archived', 'Owner, 7 Oct 2026: ' . $why );
		$log[] = "$id archived ($why)";
	} else {
		$log[] = "$id already " . get_post_status( $id );
	}
}

// Merge: keep the first report; the others become "also reported by" on it, kept private, old URL 301s to it.
$merge = array(
	7495 => array( 8765, 8754 ), // Vantara 'Champions of Care': ANI kept; Hindustan Times, Times of India merged
	139  => array( 138 ),        // Vizag wildlife photography exhibition: first report kept
);
foreach ( $merge as $keep => $others ) {
	$also = (array) get_post_meta( $keep, 'aw_also_reported', true );
	foreach ( $others as $id ) {
		if ( 'private' === get_post_status( $id ) ) {
			$log[] = "$id already merged";
			continue;
		}
		$url  = (string) get_post_meta( $id, 'aw_source_url', true );
		$name = (string) get_post_meta( $id, 'aw_source_name', true );
		if ( $url && ! in_array( $url, wp_list_pluck( $also, 'url' ), true ) ) {
			$also[] = array( 'name' => $name, 'url' => $url );
		}
		$old = get_permalink( $id );
		update_post_meta( $id, 'aw_merged_into', $keep );
		wp_update_post( array( 'ID' => $id, 'post_status' => 'private' ) );
		aw_redirect_add( $old, $keep );
		$log[] = "$id merged into $keep: " . wp_parse_url( $old, PHP_URL_PATH ) . ' -> ' . wp_parse_url( get_permalink( $keep ), PHP_URL_PATH );
	}
	update_post_meta( $keep, 'aw_also_reported', array_values( array_filter( $also ) ) );
}
foreach ( $log as $l ) {
	WP_CLI::log( $l );
}
