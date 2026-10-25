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
	if ( '' === $form ) { // no form (Fluent Forms off): no empty "Register as a volunteer" button either
		return '';
	}
	if ( ! empty( $a['open'] ) ) {
		return '<div class="aw-volunteer" id="volunteer">' . $form . '</div>';
	}
	return kits_disclosure( 'volunteer', 'Register as a volunteer', $form );
}

/* ---------- Directory profile card (S7) ---------- */

/** "Is this your organisation?" with Claim (two steps); shown only while the listing is not claimed (listing-template.php). */
function aw_block_listing_help( $a, $id ) {
	$listing = get_post( $id );
	if ( ! $listing || 'aw_listing' !== $listing->post_type ) {
		return '';
	}
	$verified = (bool) get_post_meta( $id, 'aw_verified', true );
	return '<section class="aw-listing-help" aria-labelledby="aw-listing-help-h"><h2 id="aw-listing-help-h" class="has-x-large-font-size">Is this your organisation?</h2>'
		. ( $verified ? '' : '<p>This listing comes from public records and hasn\'t been confirmed.</p>' )
		. '<div class="aw-listing-help-actions">'
		. '<a class="kit-btn aw-claim-link" href="' . esc_url( aw_claim_url( $id ) ) . '" rel="nofollow">Claim this listing</a>' // Report a mistake is in the Sources block
		. '</div></section>';
}

/* ---------- Claim and Report pages (owner, 10 Oct 2026) ---------- */

/** /claim/?listing=<ID>. */
function aw_claim_url( $id ) {
	return add_query_arg( 'listing', (int) $id, home_url( '/claim/' ) );
}

/** /report/?item=<ID> for a listing, News, Library or Resource item (owner, 21 Oct 2026); $reason picks what is wrong
 *  (the "Data not yet available" links send "missing"). The Fluent Forms form reads both ({get.item}, {get.reason}). */
function aw_report_url( $id, $reason = '' ) {
	return add_query_arg( array_filter( array( 'item' => (int) $id, 'reason' => sanitize_key( (string) $reason ) ) ), home_url( '/report/' ) );
}

// Older links: /report/?listing=<ID> opens /report/?item=<ID>.
add_action(
	'template_redirect',
	function () {
		if ( is_page( 'report' ) && ! empty( $_GET['listing'] ) && empty( $_GET['item'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_safe_redirect( aw_report_url( absint( $_GET['listing'] ), sanitize_key( wp_unslash( $_GET['reason'] ?? '' ) ) ), 301, 'Animal Writes' ); // phpcs:ignore WordPress.Security.NonceVerification
			exit;
		}
	}
);

/** The published listing a form page is about (?listing=), or null. */
function aw_form_page_listing() {
	$listing = get_post( absint( wp_unslash( $_GET['listing'] ?? 0 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
	return $listing && 'aw_listing' === $listing->post_type && 'publish' === $listing->post_status ? $listing : null;
}

/** Which item the page is about: its name (linked back to it) and, for a listing, its place; otherwise its type. */
function aw_form_page_for( $listing ) {
	$place = 'aw_listing' !== $listing->post_type ? ( AW_REPORT_TYPES[ $listing->post_type ] ?? '' ) : ( function_exists( 'aw_listing_place' ) ? implode( ', ', array_filter( array_reverse( aw_listing_place( $listing->ID ) ) ) ) : '' );
	return '<p class="aw-form-for"><a href="' . esc_url( get_permalink( $listing ) ) . '">' . esc_html( get_the_title( $listing ) ) . '</a>'
		. ( '' !== $place ? '<span>' . esc_html( $place ) . '</span>' : '' ) . '</p>';
}

/**
 * /claim/ opened on its own (owner, 12 Oct 2026: a page to register a claim): find your organisation by name, then claim it.
 * Results link to /claim/?listing=ID, which shows the claim form. Not listed yet → add it through the Contact form.
 */
function aw_claim_finder() {
	$q    = sanitize_text_field( wp_unslash( $_GET['org'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$html = '<form class="kit-form aw-claim-find" method="get" action="' . esc_url( home_url( '/claim/' ) ) . '" role="search">'
		. '<p class="kit-field"><label for="aw-claim-org">Organisation name</label><input id="aw-claim-org" type="search" name="org" value="' . esc_attr( $q ) . '" required minlength="3" autocomplete="organization"></p>'
		. '<p><button class="kit-btn" type="submit">Find your organisation</button></p></form>';
	if ( mb_strlen( $q ) >= 3 ) {
		$ids = get_posts( array( 'post_type' => 'aw_listing', 'post_status' => 'publish', 's' => $q, 'numberposts' => 20, 'fields' => 'ids', 'orderby' => 'relevance' ) );
		if ( $ids ) {
			$html .= '<ul class="aw-claim-results">';
			foreach ( $ids as $id ) {
				$place = function_exists( 'aw_listing_place' ) ? implode( ', ', array_filter( array_reverse( aw_listing_place( $id ) ) ) ) : '';
				$html .= '<li><a href="' . esc_url( add_query_arg( 'listing', $id, home_url( '/claim/' ) ) ) . '"><strong>' . esc_html( get_the_title( $id ) ) . '</strong>' . ( $place ? ' <span class="aw-place">' . aw_i( 'directory' ) . esc_html( $place ) . '</span>' : '' ) . '</a></li>';
			}
			$html .= '</ul>';
		} else {
			$html .= '<p role="status">No listing matches “' . esc_html( $q ) . '”.</p>';
		}
	}
	return $html . '<p><a class="kit-btn kit-btn--secondary" href="' . esc_url( home_url( '/contact/?about=listing' ) ) . '">Not listed yet? Add your organisation</a></p>';
}

add_shortcode(
	'aw_claim_page',
	function () {
		$listing = aw_form_page_listing();
		return $listing ? '<div class="aw-form-page" id="claim">' . aw_form_page_for( $listing ) . aw_claim_form( $listing ) . '</div>' : '<div class="aw-form-page" id="claim">' . aw_claim_finder() . '</div>';
	}
);
add_shortcode(
	'aw_report_page',
	function () {
		$item = aw_report_item( absint( wp_unslash( $_GET['item'] ?? 0 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		return '<div class="aw-form-page" id="report">' . ( $item ? aw_form_page_for( $item ) : '' ) . aw_report_form( $item, sanitize_key( wp_unslash( $_GET['reason'] ?? '' ) ) ) . '</div>'; // phpcs:ignore WordPress.Security.NonceVerification
	}
);

/* ---------- Thank-you page ---------- */

/** Forms that land on /thank-you/: ?form= => [analytics name, success heading, line] (each form's own success message). */
function aw_thanks_forms() {
	return array(
		'contact'     => array( 'contact', 'Message sent.', '' ),
		'remove-data' => array( 'remove_data', 'Message sent.', '' ),
		'volunteer'   => array( 'volunteer', 'Thank you for signing up.', 'We will contact you when there is work in your area. You can ask us to delete your details at any time.' ),
		'newsletter'  => array( 'newsletter', 'You\'re subscribed', 'Thank you for signing up for Animal Writes updates.' ),
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
		// After the footer sign-up (Newsletter #3 sends people here): the full Newsletter form #9, to choose topics.
		$full = (int) get_option( 'aw_nl_ff_form_full' );
		$more = 'newsletter' === $form && $full && defined( 'FLUENTFORM' )
			? '<section class="aw-thanks-more"><h2 class="has-large-font-size">Choose your topics (optional)</h2><div class="kit-form aw-form-ff aw-newsletter-full-form" data-kit-form="newsletter_full">' . do_shortcode( '[fluentform id="' . $full . '"]' ) . '</div></section>'
			: '';
		return '<div class="kit-form-done aw-thanks" role="status" tabindex="-1" data-kit-thanks="' . esc_attr( $forms[ $form ][0] ) . '"><p class="aw-form-done-title">' . esc_html( $title ) . '</p>'
			. ( '' !== $text ? '<p>' . esc_html( $text ) . '</p>' : '' ) . '</div>'
			. ( $links ? '<p class="aw-thanks-links">' . $links . '</p>' : '' ) . $more;
	}
);

// /report/ and /thank-you/ are not indexed and not in the sitemap (Rank Math's robots field, set when they are made);
// /claim/?listing=ID is one indexed page per listing (aw-seo-extras). Never cached when they
// carry a person's message or errors; no ads.
add_action(
	'template_redirect',
	function () {
		if ( is_page( 'thank-you' ) || ( is_page( array( 'claim', 'report' ) ) && ! empty( $_GET['aw_err'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			nocache_headers();
			do_action( 'litespeed_control_set_nocache', 'form page with a personal message' );
		}
	}
);

// The one-time steps that made /claim/, /report/ and /thank-you/ and set /report/'s Rank Math title ran and were removed (audit item 16, flag confirmed on live 25 Oct 2026; see git history).

// The one-time step that made /volunteer/, its redirect and the volunteer banners ran and was removed (audit item 16, flag confirmed on live 25 Oct 2026; see git history).

// Every campaign page ends with the Home page's last section, Take action (Volunteer, Claim your listing), in the
// campaign's own colours (owner, 19 Oct 2026); a page without a campaign class keeps the volunteer banner.
add_filter(
	'the_content',
	function ( $content ) {
		if ( ! is_singular( 'aw_campaign' ) || ! in_the_loop() || ! is_main_query() || false !== strpos( $content, 'aw-vol-banner' ) ) {
			return $content;
		}
		if ( preg_match( '/\baw-camp--([a-z0-9-]+)/', $content, $m ) ) {
			return $content . '<div class="alignfull aw-camp-end aw-camp--' . esc_attr( $m[1] ) . '">' . do_blocks( '<!-- wp:pattern {"slug":"animal-writes/take-action"} /-->' ) . '</div>';
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
