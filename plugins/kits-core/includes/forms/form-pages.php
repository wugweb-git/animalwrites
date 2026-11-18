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
 * - /thank-you/?form=<name>[&listing=<ID>]: where every form goes when it is sent (contact, volunteer,
 *   newsletter, claim, report), with the form's own success message and links back. noindex, not in the sitemap.
 *
 * Moved from Animal Writes Core `includes/form-blocks.php` (18 Nov 2026); options, meta keys, tags, hooks and
 * slugs are unchanged, and the old function names still work (back-compat wrappers).
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Where a volunteer sign-up comes from (S6.6). */
function kits_volunteer_source() {
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
function kits_block_volunteer( $a ) {
	static $done = false; // one volunteer form per page
	if ( $done ) {
		return '';
	}
	$done = true;
	$form = kits_volunteer_form( kits_volunteer_source() );
	if ( '' === $form ) { // no form (Fluent Forms off): no empty "Register as a volunteer" button either
		return '';
	}
	kits_ff_style( 'aw-volunteer' ); // #volunteer and its disclosure
	if ( ! empty( $a['open'] ) ) {
		return '<div class="aw-volunteer" id="volunteer">' . $form . '</div>';
	}
	return kits_disclosure( 'volunteer', 'Register as a volunteer', $form );
}

/** The listing fields a "Suggest an update" link can name (the Contact form's Field). */
const KITS_CORRECTION_FIELDS = array( 'picture', 'about', 'phone', 'email', 'address', 'hours' );

/**
 * Report a mistake and Suggest an update link to the Contact form (owner, 12 Nov 2026: no Report a mistake reason in the
 * menu): /contact/?about=general&item=<ID> for a listing, News, Library or Resource item, and &field= for an empty listing
 * field. The form fills the Message box from the link and saves the item's ID and path in the hidden fields report_item
 * and report_path (reports.php). Escape the result once, where it is printed.
 */
function kits_report_url( $id, $field = '' ) {
	$field = in_array( $field, KITS_CORRECTION_FIELDS, true ) ? $field : '';
	return add_query_arg( array_filter( array( 'about' => 'general', 'item' => (int) $id, 'field' => $field ) ), home_url( '/contact/' ) );
}
// /report/ and /remove-my-data/ are Rank Math redirects to the Contact reasons; Rank Math keeps the query string, so
// /report/?item=<ID> and the older /report/?listing=<ID> still name the item (reports.php reads both).
/** Which item the page is about: its name (linked back to it) and, for a listing, its place; otherwise its type. */
function kits_form_page_for( $listing ) {
	kits_ff_style( 'aw-forms' ); // aw-form-for
	$place = 'aw_listing' !== $listing->post_type ? ( KITS_REPORT_TYPES[ $listing->post_type ] ?? '' ) : ( function_exists( 'aw_listing_place' ) ? implode( ', ', array_filter( array_reverse( aw_listing_place( $listing->ID ) ) ) ) : '' );
	return '<p class="aw-form-for"><a href="' . esc_url( get_permalink( $listing ) ) . '">' . esc_html( get_the_title( $listing ) ) . '</a>'
		. ( '' !== $place ? '<span>' . esc_html( $place ) . '</span>' : '' ) . '</p>';
}

add_shortcode(
	'aw_report_page',
	function () {
		$item = kits_report_item( absint( wp_unslash( $_GET['item'] ?? 0 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		return '<div class="aw-form-page" id="report">' . ( $item ? kits_form_page_for( $item ) : '' ) . kits_report_form( $item, sanitize_key( wp_unslash( $_GET['reason'] ?? '' ) ) ) . '</div>'; // phpcs:ignore WordPress.Security.NonceVerification
	}
);

/* ---------- Thank-you page ---------- */

/** Forms that land on /thank-you/: ?form= => [analytics name, success heading, line] (each form's own success message). */
function kits_ff_thanks_forms() {
	return array(
		'contact'     => array( 'contact', 'Message sent.', '' ),
		'volunteer'   => array( 'volunteer', 'Thank you for signing up.', 'We will contact you when there is work in your area. You can ask us to delete your details at any time.' ),
		'newsletter'  => array( 'newsletter', 'You\'re subscribed', 'Thank you for signing up for Animal Writes updates.' ),
		'claim'       => array( 'claim', 'Request sent.', '' ),
	);
}

/** The thank-you page's head (owner, 31 Oct 2026): the form's success heading as the H1 and its line as the description; null for no known form. */
function kits_ff_thanks_head() {
	$forms = kits_ff_thanks_forms();
	$form  = function_exists( 'kits_thanks_form' ) ? kits_thanks_form() : '';
	if ( ! isset( $forms[ $form ] ) ) {
		return null;
	}
	$msg = function_exists( 'kits_thanks_message' ) ? kits_thanks_message() : null;
	return array( 'title' => $msg ? (string) $msg['title'] : $forms[ $form ][1], 'text' => $msg ? (string) $msg['text'] : $forms[ $form ][2] );
}

add_shortcode(
	'aw_thank_you',
	function () {
		kits_ff_style( 'aw-hubs' ); // aw-thanks-links
		$form  = kits_thanks_form();
		$forms = kits_ff_thanks_forms();
		$links = '';
		if ( ! isset( $forms[ $form ] ) ) {
			return '<p class="aw-thanks-links"><a class="kit-btn kit-btn--secondary" href="' . esc_url( home_url( '/' ) ) . '">Home</a></p>';
		}
		if ( 'claim' === $form ) {
			$listing = function_exists( 'directoryweb_form_page_listing' ) ? directoryweb_form_page_listing() : null;
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
		if ( '' !== $more ) {
			kits_ff_style( 'aw-forms' ); // the full newsletter form carries aw-form-ff
		}
		// The heading and its line are the head band (kits_ff_thanks_head()); the page keeps the analytics marker only.
		return '<div class="aw-thanks" data-kit-thanks="' . esc_attr( $forms[ $form ][0] ) . '" hidden></div>' . $more
			. ( $links ? '<p class="aw-thanks-links">' . $links . '</p>' : '' );
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

// Every campaign page ends with the Home page's Take part band (Volunteer, Run an organisation?, Get updates; owner,
// 2 Nov 2026; was Take action, 19 Oct 2026); a page without a campaign class keeps the volunteer banner.
add_filter(
	'the_content',
	function ( $content ) {
		if ( ! is_singular( 'aw_campaign' ) || ! in_the_loop() || ! is_main_query() || false !== strpos( $content, 'aw-vol-banner' ) ) {
			return $content;
		}
		if ( preg_match( '/\baw-camp--([a-z0-9-]+)/', $content, $m ) ) {
			return $content . '<div class="alignfull aw-camp-end aw-camp--' . esc_attr( $m[1] ) . '">' . do_blocks( '<!-- wp:pattern {"slug":"animal-writes/take-part"} /-->' ) . '</div>';
		}
		return $content . do_blocks( '<!-- wp:pattern {"slug":"animal-writes/volunteer-banner"} /-->' );
	},
	20
);

// Take part cards (pattern take-part-cards): a page that is already one card's job leaves that card out. /volunteer/
// drops Volunteer (the page is that card) and an unclaimed listing drops Run an organisation? (it has its own claim box).
add_filter(
	'render_block_core/cover',
	function ( $html, $block ) {
		$c = (string) ( $block['attrs']['className'] ?? '' );
		if ( false === strpos( $c, 'aw-take-part-card--' ) ) {
			return $html;
		}
		if ( false !== strpos( $c, 'aw-take-part-card--vol' ) && is_page( 'volunteer' ) ) {
			return '';
		}
		if ( false !== strpos( $c, 'aw-take-part-card--claim' ) && is_singular( 'aw_listing' ) && ! get_post_meta( get_queried_object_id(), 'aw_verified', true ) ) {
			return '';
		}
		kits_ff_style( 'aw-single' ); // aw-take-part, aw-take-part-card
		return $html;
	},
	10,
	2
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
