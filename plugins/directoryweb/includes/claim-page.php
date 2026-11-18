<?php
/**
 * Claim pages (owner, 10 Oct 2026; moved here from Animal Writes Core `includes/form-blocks.php`, 18 Nov 2026):
 *
 * - Listing card (block animal-writes/listing-help): "Is this your organisation?" with "Claim this listing", linking to
 *   /claim/?listing=<ID> (two steps, logo upload). It names the listing and links back to it.
 * - /claim/ opened on its own: find your organisation by name, then claim it ([aw_claim_page]).
 * - /claim/?listing=<ID>: the listing's own head band.
 *
 * Options, meta keys, slugs and shortcodes are unchanged, and the old function names still work (back-compat wrappers
 * at the end of this file).
 *
 * @package directoryweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- Directory profile card (S7) ---------- */

/** "Is this your organisation?" with Claim (two steps); shown only while the listing is not claimed (listing-template.php). */
function directoryweb_block_listing_help( $a, $id ) {
	$listing = get_post( $id );
	if ( ! $listing || 'aw_listing' !== $listing->post_type ) {
		return '';
	}
	directoryweb_use_style( 'aw-listing' ); // aw-listing-help, aw-claim-link (also on the Kits states sheet)
	$verified = (bool) get_post_meta( $id, 'aw_verified', true );
	return '<section class="aw-listing-help" aria-labelledby="aw-listing-help-h"><h2 id="aw-listing-help-h" class="has-x-large-font-size">Is this your organisation?</h2>'
		. ( $verified ? '' : '<p>This listing comes from public records and hasn\'t been confirmed.</p>' )
		. '<div class="aw-listing-help-actions">'
		. '<a class="kit-btn aw-claim-link" href="' . esc_url( directoryweb_claim_url( $id ) ) . '" rel="nofollow">Claim this listing</a>' // Report a mistake is in the Sources block
		. '</div></section>';
}

/* ---------- Claim and Report pages (owner, 10 Oct 2026) ---------- */

/** /claim/?listing=<ID>. */
function directoryweb_claim_url( $id ) {
	return add_query_arg( 'listing', (int) $id, home_url( '/claim/' ) );
}

/** The published listing a form page is about (?listing=), or null. */
function directoryweb_form_page_listing() {
	$listing = get_post( absint( wp_unslash( $_GET['listing'] ?? 0 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
	return $listing && 'aw_listing' === $listing->post_type && 'publish' === $listing->post_status ? $listing : null;
}

/**
 * /claim/ opened on its own (owner, 12 Oct 2026: a page to register a claim): find your organisation by name, then claim it.
 * Results link to /claim/?listing=ID, which shows the claim form. Not listed yet → add it through the Contact form.
 */
function directoryweb_claim_finder() {
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
				$html .= '<li><a href="' . esc_url( add_query_arg( 'listing', $id, home_url( '/claim/' ) ) ) . '"><strong>' . esc_html( get_the_title( $id ) ) . '</strong>' . ( $place ? ' <span class="aw-place">' . kits_icon( 'directory' ) . esc_html( $place ) . '</span>' : '' ) . '</a></li>';
			}
			$html .= '</ul>';
		} else {
			$html .= '<p role="status">No listing matches “' . esc_html( $q ) . '”.</p>';
		}
	}
	return $html . '<p><a class="kit-btn kit-btn--secondary" href="' . esc_url( home_url( '/contact/?about=listing' ) ) . '">Not listed yet? Add your organisation</a></p>';
}

/**
 * /claim/?listing=<ID>: the listing's own head band (owner, 31 Oct 2026): breadcrumbs … › {Listing} › Claim (the trail
 * is aw-seo-extras' breadcrumbs.php), its name, logo, types and place, then the intro. Without a listing: "Claim a listing".
 */
function directoryweb_claim_head_band() {
	$listing = directoryweb_form_page_listing();
	$crumbs  = function_exists( 'rankweb_breadcrumbs_claim' ) ? rankweb_breadcrumbs_claim() : '';
	if ( ! $listing ) {
		return aw_head_band( array( 'crumbs' => $crumbs, 'title' => 'Claim a listing', 'title_class' => 'wp-block-post-title' ) );
	}
	$id   = $listing->ID;
	$meta = '';
	foreach ( array_slice( function_exists( 'aw_item_type_terms' ) ? aw_item_type_terms( $id ) : array(), 0, 3 ) as $t ) { // as on the listing page: at most 3 types
		$meta .= aw_item_chip( $t );
	}
	foreach ( function_exists( 'aw_item_places' ) ? aw_item_places( $id ) : array() as $p ) {
		$st    = 'district' === $p->kind && function_exists( 'aw_place_state' ) ? aw_place_state( $p->state ) : null;
		$meta .= '<a class="aw-item-place" href="' . esc_url( aw_directory_url( $p->slug ) ) . '"><span class="kit-ico kit-ico--map-pin" aria-hidden="true"></span>' . esc_html( $st ? $p->name . ', ' . $st->name : $p->name ) . '</a>';
	}
	$logo = has_post_thumbnail( $id ) ? get_the_post_thumbnail( $id, 'thumbnail', array( 'class' => 'aw-claim-logo', 'alt' => '', 'loading' => 'eager' ) ) : '';
	return aw_head_band(
		array(
			'crumbs'      => $crumbs,
			'title'       => wp_strip_all_tags( get_the_title( $listing ) ),
			'title_class' => 'wp-block-post-title',
			'icon'        => $logo,
			'note'        => ( '' !== $meta ? '<div class="aw-item-meta aw-claim-meta">' . $meta . '</div>' : '' )
				. '<p class="kit-section-desc">People looking for help find you here. Claim your listing to correct your phone, email, services and logo, so they reach the right place. Free, checked by hand, usually within 4 to 5 days.</p>',
		)
	);
}

add_shortcode(
	'aw_claim_page',
	function () {
		$listing = directoryweb_form_page_listing();
		directoryweb_use_style( 'aw-listing' ); // aw-claim-find, aw-claim-results, aw-claim-form
		return '<div class="aw-form-page" id="claim">' . ( $listing ? aw_claim_form( $listing ) : directoryweb_claim_finder() ) . '</div>'; // the listing is named in the head band
	}
);

/* ---------- Back-compat: the old names (Animal Writes Core, before 18 Nov 2026) ---------- */

if ( ! function_exists( 'aw_block_listing_help' ) ) {
	/** Back-compat: directoryweb_block_listing_help(). */
	function aw_block_listing_help( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return directoryweb_block_listing_help( ...$args );
	}
}
if ( ! function_exists( 'aw_claim_url' ) ) {
	/** Back-compat: directoryweb_claim_url(). */
	function aw_claim_url( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return directoryweb_claim_url( ...$args );
	}
}
if ( ! function_exists( 'aw_form_page_listing' ) ) {
	/** Back-compat: directoryweb_form_page_listing(). */
	function aw_form_page_listing( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return directoryweb_form_page_listing( ...$args );
	}
}
if ( ! function_exists( 'aw_claim_finder' ) ) {
	/** Back-compat: directoryweb_claim_finder(). */
	function aw_claim_finder( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return directoryweb_claim_finder( ...$args );
	}
}
if ( ! function_exists( 'aw_claim_head_band' ) ) {
	/** Back-compat: directoryweb_claim_head_band(). */
	function aw_claim_head_band( ...$args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- back-compat name
		return directoryweb_claim_head_band( ...$args );
	}
}
