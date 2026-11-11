<?php
/**
 * Linking an approved claimant to the listing's people (owner, 18 Oct 2026; fixed 11 Nov 2026): the decision only, with
 * no WordPress or FluentCRM calls, so tests/org-people-tests.php can run it without WordPress.
 *
 * A claimant is linked privately: tag org-approved and the listing's ID in "Volunteer: organisation's listing ID". Their
 * name shows on the listing only when they ticked "Show my name" (vol_org_show = 1).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What linking a claimant to a listing changes on their FluentCRM contact.
 *
 * - Linked to another listing already: nothing changes ("elsewhere"); the contact holds one organisation, and the owner
 *   decides.
 * - Linked to this listing already: nothing, except that a claimant who now ticked "Show my name" is shown ("updated").
 * - Otherwise: tag org-approved, this listing, its name and the role; shown only if they ticked "Show my name" or had
 *   already chosen to be shown ("linked").
 * - A name already shown is never hidden by a claim.
 *
 * @param array  $fields  The contact's custom fields (vol_org_listing, vol_org_show, ...).
 * @param bool   $has_tag The contact is tagged org-approved.
 * @param int    $lid     The listing.
 * @param bool   $show    The claimant ticked "Show my name".
 * @param string $title   The listing's name.
 * @param string $role    Their role from the claim (may be empty).
 * @return array{status: string, tag: bool, sync: array<string, string>}
 */
function aw_org_people_link_plan( array $fields, $has_tag, $lid, $show, $title, $role ) {
	$lid   = (int) $lid;
	$cur   = (int) ( $fields['vol_org_listing'] ?? 0 );
	$shown = '1' === (string) ( $fields['vol_org_show'] ?? '' );
	$nochg = array(
		'tag'  => false,
		'sync' => array(),
	);
	if ( $lid <= 0 ) {
		return array( 'status' => 'no-listing' ) + $nochg;
	}
	if ( $cur && $cur !== $lid ) {
		return array( 'status' => 'elsewhere' ) + $nochg;
	}
	if ( $cur === $lid && $has_tag ) {
		if ( $show && ! $shown ) {
			return array(
				'status' => 'updated',
				'tag'    => false,
				'sync'   => array( 'vol_org_show' => '1' ),
			);
		}
		return array( 'status' => 'already' ) + $nochg;
	}
	$sync = array(
		'vol_org_listing'  => (string) $lid,
		'vol_organisation' => (string) $title,
		'vol_org_show'     => $show || $shown ? '1' : '',
	);
	if ( '' !== trim( (string) $role ) ) {
		$sync['vol_org_role'] = trim( (string) $role );
	}
	return array(
		'status' => 'linked',
		'tag'    => ! $has_tag,
		'sync'   => $sync,
	);
}
