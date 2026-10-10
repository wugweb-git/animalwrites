<?php
/**
 * Form blocks and pages (S6, S7, 8 Oct 2026).
 *
 * - Volunteer (block animal-writes/volunteer): the volunteer form, opening in place behind "Register as a volunteer"
 *   (one per page, id "volunteer"), or open on /volunteer/. Each sign-up records where it came from: home, about,
 *   campaign:{slug}, campaigns, opportunities, contact or volunteer-page.
 * - The "Volunteer banner" pattern (theme) holds it, on Home, About, Contact, the Campaigns and Opportunities hubs and
 *   every campaign; the sitewide form above the footer is gone.
 * - Listing card (block animal-writes/listing-help): "Is this your organisation?" with "Claim this listing" and
 *   "Report a mistake", links to their own pages (owner, 10 Oct 2026): /claim/?listing=<ID> (two steps, logo upload)
 *   and /report/?listing=<ID>. Both name the listing and link back to it; noindex and out of the sitemap.
 * - /thank-you/?form=<name>[&listing=<ID>]: where every form goes when it is sent (contact, remove-data, volunteer,
 *   newsletter, claim, report), with the form's own success message and links back. noindex, not in the sitemap.
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
		. '<a class="kit-btn aw-claim-link" href="' . esc_url( aw_claim_url( $id ) ) . '" rel="nofollow">Claim this listing</a>'
		. '<a class="kit-btn kit-btn--secondary aw-report-link" href="' . esc_url( aw_report_url( $id ) ) . '" rel="nofollow">Report a mistake</a>'
		. '</div></section>';
}

/* ---------- Claim and Report pages (owner, 10 Oct 2026) ---------- */

/** /claim/?listing=<ID>. */
function aw_claim_url( $id ) {
	return add_query_arg( 'listing', (int) $id, home_url( '/claim/' ) );
}

/** /report/?listing=<ID>. */
function aw_report_url( $id ) {
	return add_query_arg( 'listing', (int) $id, home_url( '/report/' ) );
}

/** The published listing a form page is about (?listing=), or null. */
function aw_form_page_listing() {
	$listing = get_post( absint( wp_unslash( $_GET['listing'] ?? 0 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
	return $listing && 'aw_listing' === $listing->post_type && 'publish' === $listing->post_status ? $listing : null;
}

/** Which listing the page is about: its name (linked back to it) and place. */
function aw_form_page_for( $listing ) {
	$place = function_exists( 'aw_listing_place' ) ? implode( ', ', array_filter( array_reverse( aw_listing_place( $listing->ID ) ) ) ) : '';
	return '<p class="aw-form-for"><a href="' . esc_url( get_permalink( $listing ) ) . '">' . esc_html( get_the_title( $listing ) ) . '</a>'
		. ( '' !== $place ? '<span>' . esc_html( $place ) . '</span>' : '' ) . '</p>';
}

/** Without a published listing: the message and a link to the Directory. */
function aw_form_page_missing() {
	return '<p>This listing could not be found.</p><p><a class="kit-btn kit-btn--secondary" href="' . esc_url( home_url( '/directory/' ) ) . '">Directory</a></p>';
}

add_shortcode(
	'aw_claim_page',
	function () {
		$listing = aw_form_page_listing();
		return $listing ? '<div class="aw-form-page" id="claim">' . aw_form_page_for( $listing ) . aw_claim_form( $listing ) . '</div>' : aw_form_page_missing();
	}
);
add_shortcode(
	'aw_report_page',
	function () {
		$listing = aw_form_page_listing();
		return $listing ? '<div class="aw-form-page" id="report">' . aw_form_page_for( $listing ) . aw_report_form( $listing ) . '</div>' : aw_form_page_missing();
	}
);

/* ---------- Thank-you page ---------- */

/** Forms that land on /thank-you/: ?form= => [analytics name, success heading, line] (each form's own success message). */
function aw_thanks_forms() {
	return array(
		'contact'     => array( 'contact', 'Message sent.', '' ),
		'remove-data' => array( 'remove_data', 'Message sent.', '' ),
		'volunteer'   => array( 'volunteer', 'Thank you for signing up.', 'We will contact you when there is work in your area. You can ask us to delete your details at any time.' ),
		'newsletter'  => array( 'newsletter', 'Check your inbox', '' ),
		'claim'       => array( 'claim', 'Request sent.', '' ),
		'report'      => array( 'report', 'Thanks — we\'ll check it.', '' ),
	);
}

add_shortcode(
	'aw_thank_you',
	function () {
		$form  = kits_thanks_form();
		$forms = aw_thanks_forms();
		$links = '';
		if ( ! isset( $forms[ $form ] ) ) {
			return '<p class="aw-thanks-links"><a class="kit-btn kit-btn--secondary" href="' . esc_url( home_url( '/' ) ) . '">Home</a></p>';
		}
		$msg   = kits_thanks_message();
		$title = $msg ? (string) $msg['title'] : $forms[ $form ][1];
		$text  = $msg ? (string) $msg['text'] : $forms[ $form ][2];
		if ( in_array( $form, array( 'claim', 'report' ), true ) ) {
			$listing = aw_form_page_listing();
			$links  .= $listing ? '<a class="kit-btn kit-btn--secondary" href="' . esc_url( get_permalink( $listing ) ) . '">' . esc_html( 'Back to ' . get_the_title( $listing ) ) . '</a>' : '';
		} else {
			$links .= '<a class="kit-btn kit-btn--secondary" href="' . esc_url( home_url( '/' ) ) . '">Home</a>';
			$from   = kits_thanks_from();
			if ( $from && untrailingslashit( $from ) !== untrailingslashit( home_url( '/' ) ) ) {
				$pid    = url_to_postid( $from );
				$links .= '<a class="kit-btn kit-btn--secondary" href="' . esc_url( $from ) . '">' . esc_html( $pid ? 'Back to ' . get_the_title( $pid ) : 'Back' ) . '</a>';
			}
		}
		return '<div class="kit-form-done aw-thanks" role="status" tabindex="-1" data-kit-thanks="' . esc_attr( $forms[ $form ][0] ) . '"><p class="aw-form-done-title">' . esc_html( $title ) . '</p>'
			. ( '' !== $text ? '<p>' . esc_html( $text ) . '</p>' : '' ) . '</div>'
			. ( $links ? '<p class="aw-thanks-links">' . $links . '</p>' : '' );
	}
);

// The three pages are not indexed and not in the sitemap (kits_noindex, set when they are made); never cached when they
// carry a person's message or errors; no ads.
add_filter(
	'wp_robots',
	function ( $robots ) {
		if ( is_page( array( 'claim', 'report', 'thank-you' ) ) ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	},
	20
);
add_action(
	'template_redirect',
	function () {
		if ( is_page( 'thank-you' ) || ( is_page( array( 'claim', 'report' ) ) && ! empty( $_GET['aw_err'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			nocache_headers();
			do_action( 'litespeed_control_set_nocache', 'form page with a personal message' );
		}
	}
);

/** Make /claim/, /report/ and /thank-you/ once. */
add_action(
	'init',
	function () {
		if ( '1' === get_option( 'aw_form_pages_done' ) ) {
			return;
		}
		foreach ( array( 'claim' => array( 'Claim this listing', 'aw_claim_page' ), 'report' => array( 'Report a mistake', 'aw_report_page' ), 'thank-you' => array( 'Thank you', 'aw_thank_you' ) ) as $slug => $p ) {
			$page = get_page_by_path( $slug );
			$id   = $page ? $page->ID : wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $p[0],
					'post_name'    => $slug,
					'post_content' => '<!-- wp:shortcode -->[' . $p[1] . ']<!-- /wp:shortcode -->',
				)
			);
			if ( $id && ! is_wp_error( $id ) ) {
				update_post_meta( $id, 'kits_noindex', '1' );
			}
		}
		update_option( 'aw_form_pages_done', '1', false );
	},
	46
);

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
