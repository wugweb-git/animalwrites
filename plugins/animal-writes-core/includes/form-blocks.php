<?php
/**
 * Form blocks and pages (S6, S7, 8 Oct 2026).
 *
 * - Volunteer (block animal-writes/volunteer): the volunteer form, opening in place behind "Register as a volunteer"
 *   (one per page, id "volunteer"), or open on /volunteer/. Each sign-up records where it came from: home, about,
 *   campaign:{slug}, campaigns, opportunities, contact or volunteer-page.
 * - The "Volunteer banner" pattern (theme) holds it, on Home, About, Contact, the Campaigns and Opportunities hubs and
 *   every campaign; the sitewide form above the footer is gone.
 * - Listing card (block animal-writes/listing-help): "Is this your organisation?" with "Claim this listing" (two steps)
 *   and "Report a mistake", one open at a time, on every directory profile.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Where a volunteer sign-up comes from (S6.6). */
function aw_volunteer_source() {
	if ( is_front_page() ) {
		return 'home';
	}
	if ( is_singular( 'aw_campaign' ) ) {
		return 'campaign:' . get_post_field( 'post_name', get_queried_object_id() );
	}
	if ( is_page( 'volunteer' ) ) {
		return 'volunteer-page';
	}
	foreach ( array( 'about', 'contact', 'campaigns', 'opportunities' ) as $slug ) {
		if ( is_page( $slug ) ) {
			return $slug;
		}
	}
	return function_exists( 'kits_form_source' ) ? kits_form_source() : '';
}

/** The volunteer block: the form behind "Register as a volunteer", or open (the /volunteer/ page). */
function aw_block_volunteer( $a ) {
	static $done = false; // one volunteer form per page
	if ( $done ) {
		return '';
	}
	$done = true;
	$form = aw_volunteer_form( aw_volunteer_source() );
	if ( ! empty( $a['open'] ) || false !== strpos( $form, 'kit-form-done' ) ) {
		return '<div class="aw-volunteer" id="volunteer">' . $form . '</div>';
	}
	return kits_disclosure( 'volunteer', 'Register as a volunteer', $form );
}

/* ---------- Directory profile card (S7) ---------- */

/** "Is this your organisation?" with Claim (two steps) and Report a mistake. */
function aw_block_listing_help( $a, $id ) {
	$listing = get_post( $id );
	if ( ! $listing || 'aw_listing' !== $listing->post_type ) {
		return '';
	}
	$verified = (bool) get_post_meta( $id, 'aw_verified', true );
	return '<section class="aw-listing-help" aria-labelledby="aw-listing-help-h"><h2 id="aw-listing-help-h">Is this your organisation?</h2>'
		. ( $verified ? '' : '<p>This listing comes from public records and hasn\'t been confirmed.</p>' )
		. '<div class="aw-listing-help-actions">'
		. kits_disclosure( 'claim', 'Claim this listing', aw_claim_form( $listing ), '', 'listing' )
		. kits_disclosure( 'report', 'Report a mistake', aw_report_form( $listing ), 'secondary', 'listing' )
		. '</div></section>';
}

/* ---------- Pages, placement and redirects (once) ---------- */

add_action(
	'init',
	function () {
		if ( '1' === get_option( 'aw_forms_s6_done' ) || ! function_exists( 'aw_redirect_add' ) ) {
			return;
		}
		// /volunteer/: the form, open (it was falling through to a guessed opportunity URL).
		if ( ! get_page_by_path( 'volunteer' ) ) {
			wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => 'Register as a volunteer',
					'post_name'    => 'volunteer',
					'post_excerpt' => 'Register as an Animal Writes volunteer: rescue, feed and speak up for animals near you.',
					'post_content' => '<!-- wp:animal-writes/volunteer {"open":true} /-->',
				)
			);
		}
		aw_redirect_add( '/get-involved/', '/volunteer/' ); // was /#volunteer
		// The banner on Home, About, Contact, and the Campaigns and Opportunities hubs (campaign pages get it below).
		$placed = array();
		foreach ( array( 'home', 'about', 'contact', 'campaigns', 'opportunities' ) as $slug ) {
			$p = get_page_by_path( $slug );
			if ( $p && false === strpos( $p->post_content, 'animal-writes/volunteer-banner' ) && false === strpos( $p->post_content, 'wp:animal-writes/volunteer' ) ) {
				wp_update_post( array( 'ID' => $p->ID, 'post_content' => rtrim( $p->post_content ) . "\n\n<!-- wp:pattern {\"slug\":\"animal-writes/volunteer-banner\"} /-->\n" ) );
				$placed[] = $slug;
			}
		}
		// The photo is a placeholder until the owner picks one.
		wp_update_post( array( 'ID' => 8856, 'post_content' => 'placeholder: replace (volunteer banner)' ) );
		update_option( 'aw_forms_s6_report', $placed, false );
		update_option( 'aw_forms_s6_done', '1', false );
	},
	45
);

// Every campaign page ends with the volunteer banner.
add_filter(
	'the_content',
	function ( $content ) {
		if ( ! is_singular( 'aw_campaign' ) || ! in_the_loop() || ! is_main_query() || false !== strpos( $content, 'aw-vol-banner' ) ) {
			return $content;
		}
		return $content . do_blocks( '<!-- wp:pattern {"slug":"animal-writes/volunteer-banner"} /-->' );
	},
	20
);

// /volunteer/ in the breadcrumb is "Volunteer".
add_filter(
	'aw_breadcrumb_label',
	function ( $label, $id ) {
		return 'volunteer' === get_post_field( 'post_name', $id ) ? 'Volunteer' : $label;
	},
	10,
	2
);
