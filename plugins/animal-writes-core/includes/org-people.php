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
				$out[] = array( 'id' => (int) $c->id, 'name' => trim( $c->first_name . ' ' . $c->last_name ), 'show' => '' !== (string) ( $f['vol_org_show'] ?? '' ) && '0' !== (string) $f['vol_org_show'] );
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
	$names = aw_org_people_public_names( $lid );
	if ( ! $names ) {
		return '';
	}
	return '<section class="aw-org-links-group aw-org-people"><h2>Volunteers</h2><ul>' . implode( '', array_map( fn( $n ) => '<li>' . esc_html( $n ) . '</li>', $names ) ) . '</ul></section>';
}

// [aw_org_people] on the listing template: the Volunteers part of [aw_org_links] (whose other parts the template already
// shows as its own blocks): approved contacts who chose to show their name, nothing when there are none.
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
	return array_map( fn( $n ) => array( '@type' => 'OrganizationRole', 'roleName' => 'Volunteer', 'member' => array( '@type' => 'Person', 'name' => $n ) ), aw_org_people_public_names( $lid ) );
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
