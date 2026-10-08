<?php
/**
 * Phase 11 H: Contact page content (copy from the prompt, verbatim), meta description placeholder.
 * Run after deploy: wp eval-file tools/phase11/contact-page.php. The previous content stays in revisions.
 */
$page = get_page_by_path( 'contact' );
if ( ! $page ) {
	WP_CLI::error( 'no contact page' );
}
$p    = fn( $html, $cls = '' ) => '<!-- wp:paragraph' . ( $cls ? ' {"className":"' . $cls . '"}' : '' ) . " -->\n<p" . ( $cls ? ' class="' . $cls . '"' : '' ) . ">$html</p>\n<!-- /wp:paragraph -->\n";
$h    = fn( $level, $text ) => '<!-- wp:heading' . ( 2 === $level ? '' : ' {"level":' . $level . '}' ) . " -->\n<h$level class=\"wp-block-heading\">$text</h$level>\n<!-- /wp:heading -->\n";
$row  = fn( $title, $text ) => "<!-- wp:group {\"className\":\"aw-contact-row\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group aw-contact-row\">" . $h( 3, $title ) . $p( $text ) . "</div>\n<!-- /wp:group -->\n";
$sc   = fn( $code ) => "<!-- wp:shortcode -->\n$code\n<!-- /wp:shortcode -->\n";
$body = "<!-- wp:group {\"align\":\"wide\",\"className\":\"aw-contact\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group alignwide aw-contact\">"
	. "<!-- wp:group {\"className\":\"aw-contact-intro\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group aw-contact-intro\">"
	. $p( 'Email <a href="mailto:contact@animalwrites.in">contact@animalwrites.in</a>, or send us a message here.' )
	. "</div>\n<!-- /wp:group -->\n"
	. "<!-- wp:group {\"className\":\"aw-contact-form-col\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group aw-contact-form-col\">" . $sc( '[aw_contact_form]' ) . "</div>\n<!-- /wp:group -->\n"
	. "<!-- wp:group {\"className\":\"aw-contact-org\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group aw-contact-org\">"
	. $h( 2, 'For organisations' )
	. $row( 'List your organisation', 'Not in the Directory yet, or your details have changed? Send your name, district, services and how people can reach you.' )
	. $row( 'Get featured', 'Appear at the top of your district and state pages in the Directory.' )
	. $row( 'Post an opportunity', 'Jobs, volunteer roles, internships. Include the role, location, deadline and how to apply.' )
	. $row( 'Partner with us', 'CSR, sponsorship and advertising.' )
	. "</div>\n<!-- /wp:group -->\n"
	. "<!-- wp:group {\"className\":\"aw-contact-report\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group aw-contact-report\">"
	. $h( 2, 'Report a mistake' ) . $p( 'Found something wrong on the site? Tell us the page and what needs fixing.' )
	. $sc( '[aw_elsewhere]' )
	. "</div>\n<!-- /wp:group -->\n"
	. "</div>\n<!-- /wp:group -->\n";
wp_update_post( array( 'ID' => $page->ID, 'post_title' => 'Contact us', 'post_content' => $body ) );
{
	update_post_meta( $page->ID, 'kits_seo_desc', 'Contact Animal Writes to list your organisation, get featured in the Directory, post an opportunity, partner with us or report a mistake.' ); // owner-approved 8 Oct 2026
}
if ( ! get_post_meta( $page->ID, 'kits_seo_title', true ) ) {
	update_post_meta( $page->ID, 'kits_seo_title', 'Contact us' ); // keeps "Contact us – Animal Writes"
}
WP_CLI::success( 'contact page updated (#' . $page->ID . ')' );
