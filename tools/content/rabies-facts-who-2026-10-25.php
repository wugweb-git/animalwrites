<?php
/**
 * Fills the owner-approved body and FAQ of the WHO rabies guide (post 125, /resources/rabies-facts-who/), which had no
 * body text (25 Oct 2026; draft in Claude outputs/observance-drafts/rabies-facts-who-draft.md). Only source: WHO Rabies
 * fact sheet, 17 Sep 2026 (read 25 Oct 2026). Title, URL and SEO fields stay as they are; the FAQ goes into the guide's
 * own FAQ (meta aw_faq, approved).
 *
 * Usage: wp eval-file rabies-facts-who-2026-10-25.php [dry]
 */
$dry = ! empty( $args[0] ) && 'dry' === $args[0];
$d   = json_decode( (string) file_get_contents( __DIR__ . '/rabies-facts-who-2026-10-25.json' ), true );
$p   = get_post( 125 );
if ( ! is_array( $d ) || ! $p || 'rabies-facts-who' !== $p->post_name ) {
	WP_CLI::error( 'Data file or post 125 (rabies-facts-who) not found; nothing written.' );
}
if ( $dry ) {
	WP_CLI::log( 'would update 125 | ' . strlen( $d['content'] ) . ' bytes | ' . count( $d['faq'] ) . ' FAQ | current body ' . strlen( trim( wp_strip_all_tags( $p->post_content ) ) ) . ' chars' );
	return;
}
if ( '' !== trim( wp_strip_all_tags( $p->post_content ) ) ) {
	update_post_meta( 125, '_aw_content_backup_2026_10_25', wp_slash( $p->post_content ) ); // keep whatever was there
}
$r = wp_update_post( wp_slash( array( 'ID' => 125, 'post_content' => $d['content'] ) ), true );
if ( is_wp_error( $r ) ) {
	WP_CLI::error( $r->get_error_message() );
}
$rows = array();
foreach ( $d['faq'] as $x ) {
	$rows[] = array( 'q' => $x[0], 'a' => $x[1], 'sources' => array( array( 'name' => 'World Health Organization, Rabies fact sheet, 17 Sep 2026', 'url' => 'https://www.who.int/news-room/fact-sheets/detail/rabies' ) ), 'keyword' => '', 'status' => 'approved', 'reviewed' => '2026-10-25' );
}
aw_faq_items_save( 'post', 125, $rows );
clean_post_cache( 125 );
WP_CLI::success( '125 | ' . get_permalink( 125 ) . ' | ' . count( $rows ) . ' FAQ' );
