<?php
/**
 * Volunteers of an organisation, from FluentCRM (owner, 21 Oct 2026; was the site's volunteers table).
 *
 * - The Volunteer form asks "Are you associated with an animal welfare organisation?", the organisation's name and
 *   "Show my name on the organisation's page". Its FluentCRM link tags the contact org-pending.
 * - The owner checks it in FluentCRM and swaps org-pending for org-approved. The contact belongs to the listing whose
 *   title matches the name they gave (ignoring case and punctuation, either containing the other), or to the listing
 *   ID the owner puts in the custom field "Volunteer: organisation's listing ID" when the name does not match.
 * - On the organisation's page, under "Volunteers": the names of approved contacts who ticked "Show my name"; and in
 *   the schema as member (Person). Email and phone are never shown.
 * - WP Admin → a listing → "Volunteers" box: who is approved for it and who is waiting (read-only, with links to
 *   their FluentCRM contacts).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** A name for matching: lower case, letters and digits only. */
function aw_org_people_norm( $s ) {
	return trim( preg_replace( '/[^a-z0-9]+/', ' ', strtolower( (string) $s ) ) );
}

/**
 * FluentCRM contacts tagged with $tag whose organisation is this listing.
 *
 * @param int    $lid Listing.
 * @param string $tag org-approved | org-pending.
 * @return array list of array{id, name, show}
 */
function aw_org_people_crm( $lid, $tag = 'org-approved' ) {
	static $cache = array();
	$lid = (int) $lid;
	if ( isset( $cache[ $tag ][ $lid ] ) ) {
		return $cache[ $tag ][ $lid ];
	}
	$out = array();
	$t   = function_exists( 'aw_crm_on' ) && aw_crm_on() ? FluentCrmApi( 'tags' )->getInstance()->where( 'slug', $tag )->first() : null;
	if ( $t ) {
		$title = aw_org_people_norm( get_the_title( $lid ) );
		foreach ( \FluentCrm\App\Models\Subscriber::filterByTags( array( (int) $t->id ) )->limit( 2000 )->get() as $c ) {
			$f    = (array) $c->custom_fields();
			$org  = aw_org_people_norm( $f['vol_organisation'] ?? '' );
			$for  = (int) ( $f['vol_org_listing'] ?? 0 );
			$hits = $for ? $for === $lid : ( strlen( $org ) >= 3 && strlen( $title ) >= 3 && ( false !== strpos( $title, $org ) || false !== strpos( $org, $title ) ) );
			if ( $hits ) {
				$out[] = array( 'id' => (int) $c->id, 'name' => trim( $c->first_name . ' ' . $c->last_name ), 'role' => trim( (string) ( $f['vol_org_role'] ?? '' ) ), 'show' => '' !== (string) ( $f['vol_org_show'] ?? '' ) && '0' !== (string) $f['vol_org_show'] );
			}
		}
	}
	return $cache[ $tag ][ $lid ] = $out;
}

/**
 * The shown volunteers' names.
 *
 * @param int $lid Listing.
 * @return string[]
 */
function aw_org_people_public_names( $lid ) {
	return array_values( array_filter( wp_list_pluck( array_filter( aw_org_people_crm( $lid ), fn( $p ) => $p['show'] ), 'name' ) ) );
}

/**
 * "Volunteers" on the organisation's page.
 *
 * @param int $lid Listing.
 * @return string
 */
function aw_org_people_html( $lid ) {
	$people = array_values( array_filter( aw_org_people_crm( $lid ), fn( $p ) => $p['show'] && '' !== $p['name'] ) );
	if ( ! $people ) {
		return '';
	}
	// A claimant shows with their role (owner, 31 Oct 2026: claim form #6), a volunteer with their name alone.
	return '<section class="aw-org-links-group aw-org-people"><h2>Volunteers</h2><ul>' . implode( '', array_map( fn( $p ) => '<li>' . esc_html( $p['name'] ) . ( '' !== $p['role'] ? '<span class="aw-org-people-role">, ' . esc_html( $p['role'] ) . '</span>' : '' ) . '</li>', $people ) ) . '</ul></section>';
}

// [aw_org_people] on the listing template: the Volunteers part of the former [aw_org_links] (whose other parts the
// template shows as its own blocks): approved contacts who chose to show their name, nothing when there are none.
add_shortcode(
	'aw_org_people',
	function () {
		$html = 'aw_listing' === get_post_type() ? aw_org_people_html( get_the_ID() ) : '';
		return '' !== $html ? '<div class="aw-org-links">' . $html . '</div>' : '';
	}
);

/**
 * schema.org member for a listing: the shown volunteers.
 *
 * @param int $lid Listing.
 * @return array
 */
function aw_org_people_schema( $lid ) {
	$people = array_values( array_filter( aw_org_people_crm( $lid ), fn( $p ) => $p['show'] && '' !== $p['name'] ) );
	return array_map( fn( $p ) => array( '@type' => 'OrganizationRole', 'roleName' => '' !== $p['role'] ? $p['role'] : 'Volunteer', 'member' => array( '@type' => 'Person', 'name' => $p['name'] ) ), $people );
}

/* ---------- WP Admin: the Volunteers box on a listing (read-only) ---------- */

add_action(
	'add_meta_boxes_aw_listing',
	function () {
		if ( current_user_can( 'edit_aw_volunteers' ) ) {
			add_meta_box( 'aw_org_people', 'Volunteers', 'aw_org_people_box', 'aw_listing', 'normal', 'low' );
		}
	}
);

/**
 * The box: approved and waiting contacts for this listing.
 *
 * @param WP_Post $post Listing.
 */
function aw_org_people_box( $post ) {
	$link = fn( $id ) => admin_url( 'admin.php?page=fluentcrm-admin#/subscribers/' . (int) $id );
	foreach ( array( 'org-approved' => 'Approved (tag org-approved)', 'org-pending' => 'Waiting (tag org-pending): approve by swapping the tag for org-approved in FluentCRM' ) as $tag => $head ) {
		$rows = aw_org_people_crm( $post->ID, $tag );
		echo '<p><strong>' . esc_html( $head ) . '</strong></p>';
		if ( ! $rows ) {
			echo '<p class="description">None.</p>';
			continue;
		}
		echo '<ul>';
		foreach ( $rows as $r ) {
			echo '<li><a href="' . esc_url( $link( $r['id'] ) ) . '">' . esc_html( '' !== $r['name'] ? $r['name'] : '(no name)' ) . '</a> · ' . esc_html( $r['show'] ? 'name shown on the page' : 'name not shown' ) . '</li>';
		}
		echo '</ul>';
	}
	echo '<p class="description">From the Volunteer form: contacts whose organisation name matches this listing (or whose "organisation\'s listing ID" is ' . (int) $post->ID . '). Email and phone are never shown.</p>';
}

/*
 * Volunteer form #5, Organisation from the Directory (owner, 31 Oct 2026): typing in "Organisation name" lists up to 8
 * published listings whose name matches, as "name · district, state" (aw.js). Picking one saves the listing in
 * org_listing (FluentCRM vol_org_listing) and its name in org_name; "My organisation isn't listed" keeps a typed name.
 * Public and read-only, like the PIN lookup (places.php), with a per-connection limit.
 */
function aw_org_search_public() {
	nocache_headers();
	$key = 'aw_orgq_rl_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$n   = (int) get_transient( $key );
	if ( $n >= 120 ) {
		wp_send_json_error( 'Too many tries. Wait a minute, then try again.' );
	}
	set_transient( $key, $n + 1, 10 * MINUTE_IN_SECONDS );
	$q = trim( sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( mb_strlen( $q ) < 3 ) {
		wp_send_json_success( array() );
	}
	$ids = get_posts( array( 'post_type' => 'aw_listing', 'post_status' => 'publish', 's' => $q, 'posts_per_page' => 8, 'fields' => 'ids', 'orderby' => 'relevance', 'post__not_in' => function_exists( 'kits_preview_only_ids' ) ? (array) kits_preview_only_ids() : array() ) );
	$out = array();
	foreach ( $ids as $id ) {
		list( $state, $district ) = function_exists( 'aw_item_place_names' ) ? aw_item_place_names( $id ) : array( '', '' );
		$out[] = array( 'id' => (int) $id, 'name' => html_entity_decode( get_the_title( $id ), ENT_QUOTES ), 'place' => implode( ', ', array_filter( array( $district, $state ) ) ) );
	}
	wp_send_json_success( $out );
}
add_action( 'wp_ajax_nopriv_aw_org_search', 'aw_org_search_public' );
add_action( 'wp_ajax_aw_org_search', 'aw_org_search_public' );

/** A picked organisation: the published listing's ID, or 0. */
function aw_org_listing_ok( $id ) {
	$id = absint( $id );
	return $id && 'aw_listing' === get_post_type( $id ) && 'publish' === get_post_status( $id ) ? $id : 0;
}

