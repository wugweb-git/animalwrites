<?php
/**
 * Campaign 2.0 home band (16 Oct 2026): puts [aw_campaign_band] right after the home hero. The band shows only while
 * the campaign is published and open (campaign-tools.php), so it can be placed while the campaign is a draft.
 * Re-running changes nothing once the band is there. WordPress keeps the previous version as a revision.
 * Run: wp eval-file campaign2-home-band.php
 */
$front = (int) get_option( 'page_on_front' );
$old   = (string) get_post_field( 'post_content', $front );
if ( false !== strpos( $old, '[aw_campaign_band' ) ) {
	WP_CLI::success( 'Band already on the home page' );
	return;
}
$hero = '<!-- wp:pattern {"slug":"animal-writes/hero"} /-->';
if ( false === strpos( $old, $hero ) ) {
	WP_CLI::error( 'Home hero not found; nothing changed' );
}
$band = "<!-- wp:shortcode -->\n[aw_campaign_band campaign=\"lab-animals-rules-2026\" theme=\"lab-rules\" title=\"Object to the draft lab animal rules\""
	. " text=\"Labs could approve most animal experiments themselves. Objections close 5 November.\"]\n<!-- /wp:shortcode -->";
$new  = preg_replace( '/' . preg_quote( $hero, '/' ) . '/', $hero . "\n\n" . $band, $old, 1 );
wp_update_post( array( 'ID' => $front, 'post_content' => $new ) );
WP_CLI::success( 'Band placed after the home hero (hidden until the campaign is published)' );
