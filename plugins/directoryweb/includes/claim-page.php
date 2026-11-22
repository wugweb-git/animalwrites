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
	directoryweb_use_style( 'dw-listing' ); // aw-listing-help, aw-claim-link (also on the Kits states sheet)
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
 * Structured listing summary card rendered on /claim/?listing=<ID> above the claim form.
 * Gives the claimant clear visual verification of the organisation they are claiming.
 *
 * @param WP_Post $listing The listing post.
 * @return string HTML.
 */
function directoryweb_claim_listing_summary( $listing ) {
	if ( ! $listing || 'aw_listing' !== $listing->post_type ) {
		return '';
	}
	$id    = $listing->ID;
	$title = wp_strip_all_tags( get_the_title( $listing ) );
	$logo  = has_post_thumbnail( $id )
		? get_the_post_thumbnail( $id, 'thumbnail', array( 'class' => 'aw-claim-summary-logo', 'alt' => '' ) )
		: '';
	if ( ! $logo ) {
		$words    = array_values( array_filter( preg_split( '/\s+/', preg_replace( '/[^\p{L}\s]/u', '', $title ) ) ) );
		$initials = mb_strtoupper( mb_substr( $words[0] ?? '', 0, 1 ) . mb_substr( $words[1] ?? '', 0, 1 ) );
		$logo     = '<span class="aw-org-mark-initials" aria-hidden="true">' . esc_html( $initials ? $initials : 'AW' ) . '</span>';
	}
	$is_verified = (bool) get_post_meta( $id, 'aw_verified', true );
	$status_chip = $is_verified
		? '<span class="kit-badge kit-badge--success"><span class="kit-ico kit-ico--check" aria-hidden="true"></span> Verified Organisation</span>'
		: '<span class="kit-badge kit-badge--neutral"><span class="kit-ico kit-ico--clock" aria-hidden="true"></span> Unclaimed Listing</span>';

	$place_parts = function_exists( 'aw_listing_place' ) ? array_filter( array_reverse( (array) aw_listing_place( $id ) ) ) : array();
	$place_text  = implode( ', ', $place_parts );

	$tags_html = '';
	if ( function_exists( 'aw_item_type_terms' ) ) {
		foreach ( array_slice( aw_item_type_terms( $id ), 0, 3 ) as $t ) {
			$tags_html .= '<span class="kit-tag">' . esc_html( $t->name ) . '</span>';
		}
	}

	$public_url = get_permalink( $id );

	return '<div class="aw-claim-preview kit-card">'
		. '<div class="aw-claim-preview-head">'
		. '<div class="aw-org-mark aw-claim-preview-mark">' . $logo . '</div>'
		. '<div class="aw-claim-preview-info">'
		. '<div class="aw-claim-preview-top">'
		. '<h2 class="aw-claim-preview-title">' . esc_html( $title ) . '</h2>'
		. $status_chip
		. '</div>'
		. '<div class="aw-claim-preview-meta">'
		. ( $place_text ? '<span class="aw-place"><span class="kit-ico kit-ico--map-pin" aria-hidden="true"></span>' . esc_html( $place_text ) . '</span>' : '' )
		. ( $tags_html ? '<div class="aw-claim-preview-tags kit-tags">' . $tags_html . '</div>' : '' )
		. '</div>'
		. '</div>'
		. '</div>'
		. '<div class="aw-claim-preview-actions">'
		. '<a href="' . esc_url( $public_url ) . '" class="kit-btn kit-btn--ghost kit-btn--sm aw-claim-preview-link" target="_blank" rel="noopener">'
		. '<span>View live listing</span> <span class="kit-ico kit-ico--arrow-right" aria-hidden="true"></span>'
		. '</a>'
		. '</div>'
		. '<div class="aw-claim-preview-note">'
		. '<p><strong>Claiming your organisation:</strong> Submit your proof and official contacts below. Submissions are reviewed by hand (usually within 4–5 business days) to protect organisation records.</p>'
		. '</div>'
		. '</div>';
}

/**
 * 3-step accessible progress stepper for the claim form.
 *
 * @param int $current_step 1, 2, or 3.
 * @return string HTML.
 */
function directoryweb_claim_stepper( $current_step = 1 ) {
	$steps = array(
		1 => array( 'label' => 'Who you are', 'desc' => 'Contact details' ),
		2 => array( 'label' => 'Proof & verification', 'desc' => 'Documents or ID' ),
		3 => array( 'label' => 'Review & submit', 'desc' => 'Confirmation' ),
	);
	$items = '';
	foreach ( $steps as $num => $info ) {
		$is_cur    = ( $num === $current_step );
		$is_done   = ( $num < $current_step );
		$state_cls = $is_cur ? 'is-current' : ( $is_done ? 'is-complete' : 'is-upcoming' );
		$items .= '<li class="aw-claim-step ' . $state_cls . '"' . ( $is_cur ? ' aria-current="step"' : '' ) . '>'
			. '<span class="aw-claim-step-num">' . ( $is_done ? '<span class="kit-ico kit-ico--check" aria-hidden="true"></span>' : $num ) . '</span>'
			. '<span class="aw-claim-step-text">'
			. '<strong class="aw-claim-step-title">' . esc_html( $info['label'] ) . '</strong>'
			. '<span class="aw-claim-step-desc">' . esc_html( $info['desc'] ) . '</span>'
			. '</span>'
			. '</li>';
	}
	return '<nav class="aw-claim-stepper" aria-label="Claim verification steps">'
		. '<ol class="aw-claim-stepper-list">' . $items . '</ol>'
		. '</nav>';
}

/**
 * /claim/ opened on its own (owner, 12 Oct 2026: a page to register a claim): find your organisation by name, then claim it.
 * Results link to /claim/?listing=ID, which shows the claim form. Not listed yet → add it through the Contact form.
 */
function directoryweb_claim_finder() {
	$q    = sanitize_text_field( wp_unslash( $_GET['org'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$html = '<div class="aw-claim-find-box">'
		. '<div class="aw-claim-hero">'
		. '<h2 class="aw-claim-hero-title">Find your organisation to claim</h2>'
		. '<p class="aw-claim-hero-desc">Search by organisation name, shelter, veterinary clinic, or trust to take ownership of your listing.</p>'
		. '</div>'
		. '<form class="kit-form aw-claim-find" method="get" action="' . esc_url( home_url( '/claim/' ) ) . '" role="search">'
		. '<div class="kit-field aw-claim-search-field">'
		. '<label for="aw-claim-org" class="screen-reader-text">Organisation name</label>'
		. '<div class="kit-field-control aw-claim-input-wrap">'
		. '<span class="kit-ico kit-ico--search aw-claim-search-ico" aria-hidden="true"></span>'
		. '<input id="aw-claim-org" type="search" name="org" value="' . esc_attr( $q ) . '" required minlength="3" placeholder="e.g. Friendicoes, CUPA, Sanjay Gandhi Animal Care..." autocomplete="organization">'
		. '<button class="kit-btn kit-btn--primary aw-claim-search-submit" type="submit">Search organisation</button>'
		. '</div>'
		. '</div>'
		. '</form>'
		. '</div>';

	if ( mb_strlen( $q ) >= 3 ) {
		$ids = get_posts( array( 'post_type' => 'aw_listing', 'post_status' => 'publish', 's' => $q, 'numberposts' => 20, 'fields' => 'ids', 'orderby' => 'relevance' ) );
		if ( $ids ) {
			$html .= '<div class="aw-claim-results-wrap">'
				. '<div class="aw-claim-results-head">'
				. '<p class="aw-claim-results-count" role="status">Found ' . number_format_i18n( count( $ids ) ) . ' matching organisations for “' . esc_html( $q ) . '”</p>'
				. '</div>'
				. '<div class="aw-claim-results-grid">';
			foreach ( $ids as $id ) {
				$title = get_the_title( $id );
				$logo  = has_post_thumbnail( $id )
					? get_the_post_thumbnail( $id, 'thumbnail', array( 'class' => 'aw-claim-card-thumb', 'alt' => '' ) )
					: '';
				if ( ! $logo ) {
					$words    = array_values( array_filter( preg_split( '/\s+/', preg_replace( '/[^\p{L}\s]/u', '', $title ) ) ) );
					$initials = mb_strtoupper( mb_substr( $words[0] ?? '', 0, 1 ) . mb_substr( $words[1] ?? '', 0, 1 ) );
					$logo     = '<span class="aw-org-mark-initials" aria-hidden="true">' . esc_html( $initials ? $initials : 'AW' ) . '</span>';
				}
				$place = function_exists( 'aw_listing_place' ) ? implode( ', ', array_filter( array_reverse( (array) aw_listing_place( $id ) ) ) ) : '';
				$tags  = '';
				if ( function_exists( 'aw_item_type_terms' ) ) {
					foreach ( array_slice( aw_item_type_terms( $id ), 0, 2 ) as $t ) {
						$tags .= '<span class="kit-tag">' . esc_html( $t->name ) . '</span>';
					}
				}
				$claim_url = esc_url( add_query_arg( 'listing', $id, home_url( '/claim/' ) ) );

				$html .= '<article class="kit-card aw-claim-card">'
					. '<div class="aw-claim-card-main">'
					. '<div class="aw-org-mark aw-claim-card-mark">' . $logo . '</div>'
					. '<div class="aw-claim-card-content">'
					. '<h3 class="aw-claim-card-title"><a href="' . $claim_url . '">' . esc_html( $title ) . '</a></h3>'
					. ( $place ? '<p class="aw-place"><span class="kit-ico kit-ico--map-pin" aria-hidden="true"></span>' . esc_html( $place ) . '</p>' : '' )
					. ( $tags ? '<div class="aw-claim-card-tags kit-tags">' . $tags . '</div>' : '' )
					. '</div>'
					. '</div>'
					. '<div class="aw-claim-card-action">'
					. '<a href="' . $claim_url . '" class="kit-btn kit-btn--sm kit-btn--primary aw-claim-btn">Claim this listing &rarr;</a>'
					. '</div>'
					. '</article>';
			}
			$html .= '</div></div>';
		} else {
			$html .= '<div class="aw-claim-empty kit-card" role="status">'
				. '<div class="aw-claim-empty-icon"><span class="kit-ico kit-ico--search" aria-hidden="true"></span></div>'
				. '<h3 class="aw-claim-empty-title">No listings match “' . esc_html( $q ) . '”</h3>'
				. '<p class="aw-claim-empty-desc">If your organisation is newly founded or not yet present in our national directory, you can submit your details to be added.</p>'
				. '<a class="kit-btn kit-btn--secondary" href="' . esc_url( home_url( '/contact/?about=listing' ) ) . '">Add your organisation &rarr;</a>'
				. '</div>';
		}
	} else {
		$html .= '<div class="aw-claim-onboarding">'
			. '<h3 class="aw-claim-onboarding-title">Why claim your organisation listing?</h3>'
			. '<div class="aw-claim-benefits-grid">'
			. '<div class="aw-claim-benefit-card kit-card">'
			. '<div class="aw-benefit-icon"><span class="kit-ico kit-ico--phone" aria-hidden="true"></span></div>'
			. '<h4>Direct Emergency Helpline</h4>'
			. '<p>Ensure citizens in distress can reach your active emergency phone and WhatsApp numbers without delay.</p>'
			. '</div>'
			. '<div class="aw-claim-benefit-card kit-card">'
			. '<div class="aw-benefit-icon"><span class="kit-ico kit-ico--check" aria-hidden="true"></span></div>'
			. '<h4>Verified Organisation Mark</h4>'
			. '<p>Establish trust with donors, volunteers, and local authorities through a verified organisation badge.</p>'
			. '</div>'
			. '<div class="aw-claim-benefit-card kit-card">'
			. '<div class="aw-benefit-icon"><span class="kit-ico kit-ico--sliders" aria-hidden="true"></span></div>'
			. '<h4>Complete Profile Control</h4>'
			. '<p>Manage your services, animal categories helped, operating hours, official logo, and website links.</p>'
			. '</div>'
			. '</div>'
			. '</div>';
	}

	return $html . '<div class="aw-claim-footer-cta kit-card">'
		. '<div class="aw-claim-footer-cta-text">'
		. '<h3>Not listed in our directory yet?</h3>'
		. '<p>If your shelter, clinic, or animal NGO does not appear in search results, submit a request to create a new directory listing.</p>'
		. '</div>'
		. '<a class="kit-btn kit-btn--secondary" href="' . esc_url( home_url( '/contact/?about=listing' ) ) . '">Add your organisation</a>'
		. '</div>';
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
		directoryweb_use_style( 'dw-listing' ); // aw-claim-find, aw-claim-results, aw-claim-form, aw-claim-preview, aw-claim-stepper
		directoryweb_use_style( 'aw-directory' );

		if ( $listing ) {
			return '<div class="aw-form-page aw-claim-page" id="claim">'
				. directoryweb_claim_listing_summary( $listing )
				. directoryweb_claim_stepper( 1 )
				. aw_claim_form( $listing )
				. '</div>';
		}

		return '<div class="aw-form-page aw-claim-finder-page" id="claim">'
			. directoryweb_claim_finder()
			. '</div>';
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
