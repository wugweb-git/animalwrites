<?php
/**
 * Resources become WordPress Posts (owner, 10 Oct 2026). Run once after deploying the code that treats `post` as
 * Resources: wp eval-file aw_deploy/tools/restructure/resources-to-posts.php. Safe to run again.
 *
 * - Every aw_guide item (all statuses) becomes a post in place: same ID, slug, dates, author, content, excerpt,
 *   featured image, meta, topics and relations.
 * - The Resource types (Guide, How-to, FAQ, Research) become the Category list in place: same term IDs and links.
 * - Guide is the default category; the empty "Uncategorized" goes.
 * - The Resources hub Page and its list pattern query `post`.
 * Nothing else is deleted. URLs stay /resources/{slug}/.
 */

global $wpdb;
$log = array();

// 1. Items: post type in place (revisions and attachments keep their parent ID).
$before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'aw_guide'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$ids    = array_map( 'intval', $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'aw_guide'" ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$clash  = $ids ? $wpdb->get_col( "SELECT g.post_name FROM {$wpdb->posts} g JOIN {$wpdb->posts} p ON p.post_name = g.post_name AND p.post_type = 'post' WHERE g.post_type = 'aw_guide' AND g.post_name <> ''" ) : array(); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
if ( $clash ) {
	WP_CLI::error( 'Slug collision with existing posts: ' . implode( ', ', $clash ) );
}
$moved = $ids ? (int) $wpdb->query( "UPDATE {$wpdb->posts} SET post_type = 'post' WHERE post_type = 'aw_guide'" ) : 0; // phpcs:ignore WordPress.DB.DirectDatabaseQuery
foreach ( $ids as $id ) {
	clean_post_cache( $id );
}
$log[] = "items: $before aw_guide, $moved moved to post";

// 2. Resource types → categories, same terms.
$tt    = (int) $wpdb->query( "UPDATE {$wpdb->term_taxonomy} SET taxonomy = 'category' WHERE taxonomy = 'aw_resource_type'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$log[] = "resource types moved to category: $tt";
$guide = get_term_by( 'slug', 'guide', 'category' );
if ( $guide ) {
	update_option( 'default_category', (int) $guide->term_id );
	$log[] = 'default category: Guide #' . $guide->term_id;
}
foreach ( array( 'how-to' => 'How-to', 'faq' => 'FAQ', 'research' => 'Research' ) as $slug => $name ) {
	if ( ! term_exists( $slug, 'category' ) ) {
		wp_insert_term( $name, 'category', array( 'slug' => $slug ) );
		$log[] = "added category $name";
	}
}
$unc = get_term_by( 'slug', 'uncategorized', 'category' );
if ( $unc && (int) get_option( 'default_category' ) !== (int) $unc->term_id ) {
	wp_update_term_count_now( array( (int) $unc->term_taxonomy_id ), 'category' );
	if ( 0 === (int) get_term( $unc->term_id, 'category' )->count ) {
		wp_delete_term( $unc->term_id, 'category' );
		$log[] = 'removed the empty Uncategorized';
	} else {
		$log[] = 'Uncategorized kept: it has items';
	}
}
clean_taxonomy_cache( 'category' );
$all_tt = array_map( 'intval', $wpdb->get_col( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'category'" ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
wp_update_term_count_now( $all_tt, 'category' );

// 3. The hub Page and its list pattern (blocks name the post type).
$hub = (array) get_option( 'aw_hub_pages', array() );
$hsh = (array) get_option( 'aw_hub_hashes', array() );
if ( isset( $hub['aw_guide'] ) ) {
	$hub['post'] = $hub['aw_guide'];
	unset( $hub['aw_guide'] );
}
foreach ( array( 'p', 'g' ) as $k ) {
	if ( isset( $hsh[ $k . 'aw_guide' ] ) ) {
		$hsh[ $k . 'post' ] = $hsh[ $k . 'aw_guide' ];
		unset( $hsh[ $k . 'aw_guide' ] );
	}
}
foreach ( array( 'pattern' => 'p', 'page' => 'g' ) as $what => $k ) {
	$pid = (int) ( $hub['post'][ $what ] ?? 0 );
	if ( ! $pid ) {
		continue;
	}
	$old  = (string) get_post_field( 'post_content', $pid );
	$mine = md5( $old ) === ( $hsh[ $k . 'post' ] ?? '' );
	$new  = str_replace( array( '"postType":"aw_guide"', '"term":"aw_resource_type"' ), array( '"postType":"post"', '"term":"category"' ), $old );
	if ( $new !== $old ) {
		$wpdb->update( $wpdb->posts, array( 'post_content' => $new ), array( 'ID' => $pid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery (no revision, no modified date change)
		clean_post_cache( $pid );
		$log[] = "$what #$pid: post type in blocks updated";
	}
	if ( $mine ) {
		$hsh[ $k . 'post' ] = md5( $new );
	}
}
update_option( 'aw_hub_pages', $hub, false );
update_option( 'aw_hub_hashes', $hsh, false );

// 4. Caches, thumbnails, rewrite rules.
wp_cache_flush();
delete_transient( 'aw_stats' );
if ( function_exists( 'aw_thumb_rebuild' ) ) {
	aw_thumb_rebuild();
}
flush_rewrite_rules( false );

$after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'post'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$pub   = (int) wp_count_posts( 'post' )->publish;
$log[] = "posts now: $after ($pub published); aw_guide left: " . (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'aw_guide'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
foreach ( get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false ) ) as $t ) {
	$log[] = "category {$t->term_id} {$t->name} ({$t->slug}): {$t->count}";
}
update_option( 'aw_resources_to_posts_report', $log, false );
WP_CLI::log( implode( "\n", $log ) );
