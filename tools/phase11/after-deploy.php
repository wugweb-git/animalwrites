<?php
/**
 * Phase 11: one-time data steps after the deploy. Run as an administrator:
 *   wp eval-file tools/phase11/after-deploy.php --user=<admin>
 * Nothing is deleted except the empty aw_banners option (its earlier values are archived in aw_banners_removed_2026_10_08).
 */
// One-time admin steps (comments off, Hello world → draft, court terms kept as meta, testimonials export,
// contact score for every listing, press items from the old list).
do_action( 'admin_init' );
WP_CLI::log( 'admin steps: ' . wp_json_encode( get_option( 'aw_admin_ia_report' ) ) );
WP_CLI::log( 'press items: ' . count( get_posts( array( 'post_type' => 'aw_press', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) ) );
foreach ( get_posts( array( 'post_type' => 'aw_press', 'post_status' => 'any', 'numberposts' => -1 ) ) as $p ) {
	WP_CLI::log( '  ' . $p->post_title . ' | ' . get_post_meta( $p->ID, 'aw_press_publisher', true ) . ' | ' . get_post_meta( $p->ID, 'aw_press_date', true ) . ' | ' . get_post_meta( $p->ID, 'aw_press_fetch', true ) );
}
// Contact page.
require __DIR__ . '/contact-page.php';
// Private, noindex demo page of the Kits primitives.
if ( ! get_page_by_path( 'kits-demo' ) ) {
	$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'private', 'post_name' => 'kits-demo', 'post_title' => 'Kits demo', 'post_content' => "<!-- wp:shortcode -->\n[kits_demo]\n<!-- /wp:shortcode -->" ) );
	WP_CLI::log( "kits-demo page #$id (private)" );
}
// Newsletter strip placeholder photo: marked for replacement.
$ph = get_post( 8839 );
if ( $ph && false === strpos( (string) $ph->post_content, 'placeholder: replace' ) ) {
	wp_update_post( array( 'ID' => 8839, 'post_content' => trim( $ph->post_content . "\nplaceholder: replace (newsletter strip background, Phase 11 F.2)" ) ) );
}
// Caches that hold counts or lists changed by Phase 11.
foreach ( array( 'aw_dir_districts', 'aw_dir_districts_n', 'aw_state_counts', 'aw_verified_count', 'aw_stats' ) as $t ) {
	delete_transient( $t );
}
WP_CLI::success( 'Phase 11 data steps done' );
