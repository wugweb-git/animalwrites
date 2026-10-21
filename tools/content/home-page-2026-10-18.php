<?php
/**
 * Home page (page 57), owner's revision brief (18 Oct 2026). New section order: hero, the open campaign's band, Need help
 * now, What's happening, From the Library, Guides and research, Find help near you, India writes for its community dogs,
 * Take action (two photo blocks above the footer); the newsletter is the footer's form. Takes out the empty [aw_testimonials] shortcode and the
 * volunteer banner at the bottom (block 12749 itself is unchanged; Take action links to /volunteer/). The ad slots
 * stay between sections; Take action is last, so the page meets the footer with no strip. The old content goes to ~/aw-home-backup-*.json first, and WordPress keeps it as a revision.
 * Run: wp eval-file home-page-2026-10-18.php            (dry run: wp eval-file home-page-2026-10-18.php dry)
 */
$id  = 57;
$dry = in_array( 'dry', (array) ( $args ?? array() ), true );
$p   = get_post( $id );
if ( ! $p || (int) get_option( 'page_on_front' ) !== $id ) {
	WP_CLI::error( 'Page 57 is not the front page; nothing changed' );
}

$content = <<<'HTML'
<!-- wp:pattern {"slug":"animal-writes/hero"} /-->

<!-- wp:shortcode -->
[aw_campaign_band campaign="lab-animals-rules-2026" theme="lab-rules" title="Object to the draft lab animal rules" text="Labs could approve most animal experiments themselves. Objections close 5 November."]
<!-- /wp:shortcode -->

<!-- wp:block {"ref":12750} /-->

<!-- wp:pattern {"slug":"animal-writes/whats-happening"} /-->

<!-- wp:advads/gblock {"itemID":"place_home-top"} /-->

<!-- wp:pattern {"slug":"animal-writes/animal-law"} /-->

<!-- wp:pattern {"slug":"animal-writes/learn"} /-->

<!-- wp:pattern {"slug":"animal-writes/find-help"} /-->

<!-- wp:advads/gblock {"itemID":"place_home-mid"} /-->

<!-- wp:pattern {"slug":"animal-writes/aw1-story"} /-->

<!-- wp:advads/gblock {"itemID":"place_home-bottom"} /-->

<!-- wp:pattern {"slug":"animal-writes/take-action"} /-->
HTML;

// The campaign band's text is whatever the page has now (other sessions own the campaign), not this file's copy.
if ( preg_match( '/\[aw_campaign_band [^\]]*\]/', $p->post_content, $m ) ) {
	$content = preg_replace( '/\[aw_campaign_band [^\]]*\]/', $m[0], $content );
}

if ( $dry ) {
	WP_CLI::log( $content );
	WP_CLI::success( 'Dry run; nothing changed' );
	return;
}
$backup = getenv( 'HOME' ) . '/aw-home-backup-' . gmdate( 'Ymd-His' ) . '.json';
file_put_contents( $backup, wp_json_encode( array( 'ID' => $id, 'post_content' => $p->post_content ), JSON_PRETTY_PRINT ) );
$r = wp_update_post( array( 'ID' => $id, 'post_content' => $content ), true );
if ( is_wp_error( $r ) ) {
	WP_CLI::error( $r->get_error_message() );
}
WP_CLI::success( "Page 57 updated; backup at $backup" );
