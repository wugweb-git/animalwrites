<?php
/**
 * Restructure Phase 6: create the new pages (structure only; the owner writes the text) and trim Get involved to
 * volunteering. Run once: wp eval-file tools/restructure/phase6.php. Safe to run again.
 * Nothing is deleted: page edits keep a revision of the earlier version.
 */

$copy = fn( $what ) => "<!-- wp:shortcode -->\n[aw_copy_needed what=\"$what\"]\n<!-- /wp:shortcode -->\n";
$h2   = fn( $text, $anchor = '' ) => '<!-- wp:heading {"level":2' . ( $anchor ? ',"anchor":"' . $anchor . '"' : '' ) . "} -->\n<h2 class=\"wp-block-heading\"" . ( $anchor ? " id=\"$anchor\"" : '' ) . ">$text</h2>\n<!-- /wp:heading -->\n";
$sc   = fn( $code ) => "<!-- wp:shortcode -->\n$code\n<!-- /wp:shortcode -->\n";
$btn  = fn( $text, $url ) => "<!-- wp:buttons -->\n<div class=\"wp-block-buttons\"><!-- wp:button -->\n<div class=\"wp-block-button\"><a class=\"wp-block-button__link wp-element-button\" href=\"$url\">$text</a></div>\n<!-- /wp:button --></div>\n<!-- /wp:buttons -->\n";
$list = function ( $items ) {
	$li = '';
	foreach ( $items as $text => $url ) {
		$li .= "<!-- wp:list-item -->\n<li><a href=\"$url\">$text</a></li>\n<!-- /wp:list-item -->\n";
	}
	return "<!-- wp:list -->\n<ul class=\"wp-block-list\">$li</ul>\n<!-- /wp:list -->\n";
};

// The "Call now" numbers already on Helplines, reused as they are.
$helplines = (string) get_post_field( 'post_content', get_page_by_path( 'helplines' ) );
$start     = strpos( $helplines, '<!-- wp:group {"className":"aw-helplines"} -->' );
$calls     = '';
if ( false !== $start ) {
	$depth = 0;
	preg_match_all( '#<!-- (/?)wp:group[^>]*-->#', $helplines, $m, PREG_OFFSET_CAPTURE, $start );
	foreach ( $m[0] as $i => $tag ) {
		$depth += '/' === $m[1][ $i ][0] ? -1 : 1;
		if ( 0 === $depth ) {
			$calls = substr( $helplines, $start, $tag[1] + strlen( $tag[0] ) - $start ) . "\n";
			break;
		}
	}
}

$pages = array(
	'emergency'         => array(
		'Emergency',
		$copy( 'intro' )
		. $h2( 'Call', 'call' ) . $calls . $list( array( 'All helplines' => '/helplines/' ) )
		. $h2( 'Report cruelty', 'report' ) . $copy( 'how to report' ) . $list( array( 'How to report animal cruelty' => '/knowledge/how-to-report-animal-cruelty/', 'Police complaint template' => '/resources/animal-cruelty-police-complaint-template/' ) )
		. $h2( 'Find a vet', 'vet' ) . $copy( 'find a vet' ) . $sc( '[aw_directory_filter]' ),
	),
	'topics'            => array( 'Topics', $copy( 'intro' ) . $sc( '[aw_topics_index]' ) ),
	'corrections'       => array( 'Corrections', $copy( 'corrections policy' ) . $btn( 'Ask for a correction', '/contact/?about=correction' ) ),
	'search'            => array( 'Search', "<!-- wp:search {\"label\":\"Search\",\"buttonText\":\"Search\"} /-->\n" ),
	'for-organisations' => array(
		'For organisations',
		$copy( 'intro' )
		. $h2( 'Get featured', 'featured' ) . $copy( 'featured listings' ) . $btn( 'Get featured', '/contact/?about=featured' )
		. $h2( 'Add or update a listing', 'listing' ) . $copy( 'listings' ) . $btn( 'Add or update a listing', '/contact/?about=listing' )
		. $h2( 'Post an opportunity', 'opportunity' ) . $copy( 'opportunities' ) . $btn( 'Post an opportunity', '/contact/?about=opportunity' )
		. $h2( 'Partner with us', 'partner' ) . $copy( 'partnerships' ) . $btn( 'Partner with us', '/contact/?about=partner' ),
	),
);

foreach ( $pages as $slug => $def ) {
	$page = get_page_by_path( $slug );
	if ( $page ) {
		WP_CLI::log( "/$slug/: exists (#{$page->ID}), left as it is" );
		continue;
	}
	$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $def[0], 'post_content' => $def[1] ), true );
	WP_CLI::log( is_wp_error( $id ) ? "/$slug/: FAILED " . $id->get_error_message() : "/$slug/: created (#$id)" );
}

// Contact: the form, below what is there now.
$contact = get_page_by_path( 'contact' );
if ( $contact && false === strpos( $contact->post_content, '[aw_contact_form]' ) ) {
	wp_update_post( array( 'ID' => $contact->ID, 'post_content' => $contact->post_content . "\n" . $h2( 'Write to us', 'write' ) . $sc( '[aw_contact_form]' ) ) );
	WP_CLI::log( '/contact/: form added' );
}

// Get involved: volunteering only. The newsletter moves to the footer; the other sections stay in the page history.
$gi = get_page_by_path( 'get-involved' );
if ( $gi && false !== strpos( $gi->post_content, '"anchor":"volunteer"' ) ) {
	$c    = $gi->post_content;
	$from = strpos( $c, '<!-- wp:heading {"level":2,"anchor":"volunteer"} -->' );
	$to   = strpos( $c, '[aw_volunteer_form]' );
	$to   = strpos( $c, '<!-- /wp:shortcode -->', $to ) + strlen( '<!-- /wp:shortcode -->' );
	$new  = trim( substr( $c, $from, $to - $from ) ) . "\n";
	if ( $new !== $c ) {
		wp_update_post( array( 'ID' => $gi->ID, 'post_content' => $new ) );
		WP_CLI::log( '/get-involved/: volunteering only (earlier version kept in revisions)' );
	}
}

flush_rewrite_rules( false );
WP_CLI::success( 'Phase 6 pages done.' );
