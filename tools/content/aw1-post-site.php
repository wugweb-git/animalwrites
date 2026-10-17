<?php
/**
 * "India Writes for Its Community Dogs" across the site (17 Oct 2026, owner's brief: no "1.0" anywhere visible).
 *
 *   wp eval-file aw1-post-site.php tags   the campaign tag (the old "Animal Writes 1.0" tag, renamed, its 43 press
 *                                         items kept), allowed for News, Library and Resources, and put on the
 *                                         campaign's items; the LawChakra report as a Media & PR item
 *   wp eval-file aw1-post-site.php move   the campaign's new address with its 301s, and the "1.0" wording on the
 *                                         Campaigns and About pages
 *
 * Every text change is logged before and after. Re-running changes nothing that is already done.
 */

$phase = $args[0] ?? '';
$name  = 'India Writes for Its Community Dogs';
$slug  = 'campaign-india-writes-community-dogs';
$log   = function ( $where, $before, $after ) {
	WP_CLI::log( "REPLACED | $where | before: " . wp_strip_all_tags( $before ) . ' | after: ' . wp_strip_all_tags( $after ) );
};

if ( 'tags' === $phase ) {
	/* The tag: term 1075 renamed, slug and all; the old tag page redirects. */
	$t = get_term_by( 'slug', $slug, 'post_tag' );
	if ( ! $t ) {
		$old = get_term_by( 'slug', 'campaign-animal-writes-1-0', 'post_tag' );
		if ( ! $old ) {
			WP_CLI::error( 'Neither tag found' );
		}
		$r = wp_update_term( $old->term_id, 'post_tag', array( 'name' => $name, 'slug' => $slug ) );
		if ( is_wp_error( $r ) ) {
			WP_CLI::error( $r->get_error_message() );
		}
		$log( 'tag ' . $old->term_id, $old->name . ' (' . $old->slug . ')', $name . ' (' . $slug . ')' );
		$t = get_term( $old->term_id, 'post_tag' );
	}
	if ( function_exists( 'aw_redirect_add' ) ) {
		aw_redirect_add( '/tag/campaign-animal-writes-1-0/', '/tag/' . $slug . '/' );
	}

	/* Sourcing → Sections: the tag may be used on News, Library and Resources items. */
	$src = get_option( 'aw_sources' );
	foreach ( array( 'aw_news', 'aw_legal', 'post' ) as $type ) {
		$tags = (array) ( $src['sections'][ $type ]['tags'] ?? array() );
		if ( ! in_array( $slug, $tags, true ) ) {
			$tags[]                            = $slug;
			$src['sections'][ $type ]['tags'] = array_values( $tags );
			WP_CLI::log( "allowed $slug for $type" );
		}
	}
	update_option( 'aw_sources', $src );

	/* The campaign's items (owner's list, 17 Oct 2026). */
	$items = array(
		'news'      => array( 12176, 12152, 12116, 12090, 11910, 11096, 11100, 10653, 9738, 201, 11028 ), // 11028: Madras High Court, added 17 Oct 2026
		'resources' => array( 13842, 9032, 128, 13800, 9028, 9029, 129, 7600, 12634, 12577, 12527 ),
		'library'   => array( 176, 11, 9, 13, 14, 9561, 118 ),
		'campaign'  => array( 8 ),
	);
	foreach ( $items as $group => $ids ) {
		foreach ( $ids as $id ) {
			if ( ! get_post( $id ) ) {
				WP_CLI::warning( "$group $id not found" );
				continue;
			}
			wp_set_object_terms( $id, array( (int) $t->term_id ), 'post_tag', true );
			clean_post_cache( $id );
		}
		WP_CLI::log( sprintf( 'tagged %d %s', count( $ids ), $group ) );
	}

	/* LawChakra, 1 Dec 2025: the campaign's coverage, through the press ingest like every Media & PR item. */
	$lc_url = 'https://lawchakra.in/legal-updates/indian-supreme-court-letters-stray-dogs/';
	$have   = get_posts( array( 'post_type' => 'aw_press', 'post_status' => 'any', 'meta_key' => 'aw_press_url', 'meta_value' => $lc_url, 'fields' => 'ids', 'numberposts' => 1 ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	if ( ! $have && function_exists( 'aw_press_ingest' ) ) {
		wp_set_current_user( 1 );
		$req = new WP_REST_Request( 'POST', '/aw/v1/ingest' );
		foreach ( array(
			'type'        => 'press',
			'title'       => 'Over 50,000 Indians Flood Supreme Court With Handwritten Letters Opposing Stray Dogs Removal Order',
			'source_url'  => $lc_url,
			'source_name' => 'LawChakra',
			'source_date' => '2025-12-01',
			'format'      => 'article',
			'image_url'   => 'https://i0.wp.com/lawchakra.in/wp-content/uploads/2025/11/webp-06-44.png',
			'tags'        => array( $slug ),
		) as $k => $v ) {
			$req->set_param( $k, $v );
		}
		$r    = aw_press_ingest( $req );
		$data = is_wp_error( $r ) ? array() : $r->get_data();
		$have = ! empty( $data['id'] ) ? array( (int) $data['id'] ) : array();
		WP_CLI::log( 'press ingest: ' . wp_json_encode( $data ) );
	}
	if ( $have ) {
		$pid = (int) $have[0];
		if ( 'publish' !== get_post_status( $pid ) ) {
			wp_update_post( array( 'ID' => $pid, 'post_status' => 'publish', 'post_date' => '2025-12-01 12:00:00', 'edit_date' => true ) );
		}
		if ( ! get_post_meta( $pid, 'aw_press_group', true ) ) {
			update_post_meta( $pid, 'aw_press_group', 'articles' );
		}
		wp_set_object_terms( $pid, array( (int) $t->term_id ), 'post_tag', true );
		WP_CLI::log( "LawChakra press item $pid " . get_post_status( $pid ) );
	}
	WP_CLI::success( 'tags done' );
	return;
}

if ( 'move' === $phase ) {
	$id      = 8;
	$old     = '/campaigns/animal-writes-1-0/';
	$new     = '/campaigns/india-writes-for-its-community-dogs/';
	$new_url = home_url( $new );

	/* The new address; WordPress keeps the old slug too (_wp_old_slug), and Rank Math gets the 301s. */
	if ( 'india-writes-for-its-community-dogs' !== get_post_field( 'post_name', $id ) ) {
		$before = get_permalink( $id );
		wp_update_post( array( 'ID' => $id, 'post_name' => 'india-writes-for-its-community-dogs' ) );
		$log( 'post 8 address', $before, get_permalink( $id ) );
	}
	aw_redirect_add( $old, $id );
	aw_rank_math_redirect( '^campaigns/animal-writes-1-0/.+', $new_url, 'regex' ); // anything below the old address
	global $wpdb;
	$t = $wpdb->prefix . 'rank_math_redirections';
	foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT id, url_to FROM $t WHERE url_to LIKE %s", '%' . $wpdb->esc_like( 'animal-writes-1-0' ) . '%' ) ) as $row ) { // phpcs:ignore WordPress.DB
		$wpdb->update( $t, array( 'url_to' => $new_url ), array( 'id' => (int) $row->id ) ); // no chains: straight to the new address
		$wpdb->delete( $t . '_cache', array( 'redirection_id' => (int) $row->id ) );
		$log( 'Rank Math redirection ' . $row->id, $row->url_to, $new_url );
	}

	/* Pages: the Campaigns page's text and description, the About page's link. Blocks unchanged otherwise. */
	foreach ( array( 8943, 58, 57 ) as $pid ) {
		$c = (string) get_post_field( 'post_content', $pid, 'raw' );
		$n = str_replace( array( 'href="' . $old . '"', 'href="' . home_url( $old ) . '"' ), 'href="' . $new . '"', $c );
		$n = str_replace( 'Animal Writes 1.0', $name, $n );
		if ( $n !== $c ) {
			foreach ( array( 'Animal Writes 1.0', $old ) as $needle ) {
				$at = strpos( $c, $needle );
				if ( false !== $at ) {
					$log( "page $pid content", substr( $c, max( 0, $at - 60 ), 160 ), str_replace( array( $needle, 'Animal Writes 1.0' ), array( 'Animal Writes 1.0' === $needle ? $name : $new, $name ), substr( $c, max( 0, $at - 60 ), 160 ) ) );
				}
			}
			$wpdb->update( $wpdb->posts, array( 'post_content' => $n ), array( 'ID' => $pid ) ); // straight to the row: the page's dates stay
			clean_post_cache( $pid );
		}
		foreach ( array( 'rank_math_description', 'rank_math_title' ) as $k ) {
			$v = (string) get_post_meta( $pid, $k, true );
			if ( false !== strpos( $v, 'Animal Writes 1.0' ) ) {
				$nv = str_replace( 'Animal Writes 1.0', $name, $v );
				update_post_meta( $pid, $k, $nv );
				$log( "page $pid $k", $v, $nv . ' (' . mb_strlen( $nv ) . ' chars)' );
			}
		}
		do_action( 'litespeed_purge_post', $pid );
	}
	do_action( 'litespeed_purge_post', $id );
	do_action( 'litespeed_purge_url', '/llms.txt' );
	do_action( 'litespeed_purge_url', $old );
	WP_CLI::success( 'move done: ' . get_permalink( $id ) );
	return;
}

WP_CLI::error( 'Give a phase: tags or move' );
