<?php
/**
 * Claimant → listing people link (includes/org-people-link.php), without WordPress. Run by the Deploy check.
 *
 * Usage: php plugins/animal-writes-core/tests/org-people-tests.php
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) && 'cli' !== PHP_SAPI ) { // web requests stop here; the Deploy check runs this from the command line
	exit;
}

// phpcs:disable WordPress.Security.EscapeOutput -- a command-line test runner.

define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__ ) . '/includes/org-people-link.php';

$fails = 0;
$count = 0;

/**
 * One check.
 *
 * @param bool   $ok   Passed.
 * @param string $name What was checked.
 */
function check( $ok, $name ) {
	global $fails, $count;
	++$count;
	if ( ! $ok ) {
		++$fails;
		echo 'FAIL: ' . $name . "\n";
	}
}

// A new claimant who did not tick "Show my name": linked, tagged, not shown.
$p = aw_org_people_link_plan( array(), false, 42, false, 'Paws Trust', 'Staff member' );
check( 'linked' === $p['status'], 'new claimant is linked' );
check( true === $p['tag'], 'new claimant gets org-approved' );
check( '42' === $p['sync']['vol_org_listing'], 'listing ID stored' );
check( '' === $p['sync']['vol_org_show'], 'not shown without opt-in' );
check( 'Paws Trust' === $p['sync']['vol_organisation'], 'organisation name stored' );
check( 'Staff member' === $p['sync']['vol_org_role'], 'role stored' );

// Ticked "Show my name": shown.
$p = aw_org_people_link_plan( array(), false, 42, true, 'Paws Trust', '' );
check( '1' === $p['sync']['vol_org_show'], 'shown with opt-in' );
check( ! isset( $p['sync']['vol_org_role'] ), 'empty role not written' );

// Already tagged (a volunteer) with no listing ID: linked, no second tag, an existing "show" kept.
$p = aw_org_people_link_plan( array( 'vol_org_show' => '1' ), true, 42, false, 'Paws Trust', '' );
check( 'linked' === $p['status'] && false === $p['tag'], 'tagged volunteer is linked without retagging' );
check( '1' === $p['sync']['vol_org_show'], 'a name already shown stays shown' );

// Already linked to this listing.
$p = aw_org_people_link_plan(
	array(
		'vol_org_listing' => '42',
		'vol_org_show'    => '',
	),
	true,
	42,
	false,
	'Paws Trust',
	''
);
check( 'already' === $p['status'] && array() === $p['sync'] && false === $p['tag'], 'already linked: nothing changes' );
$p = aw_org_people_link_plan(
	array(
		'vol_org_listing' => '42',
		'vol_org_show'    => '',
	),
	true,
	42,
	true,
	'Paws Trust',
	''
);
check( 'updated' === $p['status'] && array( 'vol_org_show' => '1' ) === $p['sync'], 'already linked, now opted in: shown' );
$p = aw_org_people_link_plan(
	array(
		'vol_org_listing' => '42',
		'vol_org_show'    => '1',
	),
	true,
	42,
	false,
	'Paws Trust',
	''
);
check( 'already' === $p['status'], 'a name already shown is never hidden' );

// Listing ID set but tag missing: relinked with the tag.
$p = aw_org_people_link_plan( array( 'vol_org_listing' => '42' ), false, 42, false, 'Paws Trust', '' );
check( 'linked' === $p['status'] && true === $p['tag'], 'listing set without tag: tag added' );

// Linked to another listing: nothing changes.
$p = aw_org_people_link_plan( array( 'vol_org_listing' => '7' ), true, 42, true, 'Paws Trust', '' );
check( 'elsewhere' === $p['status'] && array() === $p['sync'] && false === $p['tag'], 'linked elsewhere: unchanged' );

// No listing.
$p = aw_org_people_link_plan( array(), false, 0, true, '', '' );
check( 'no-listing' === $p['status'] && array() === $p['sync'], 'no listing: unchanged' );

echo $count . ' checks, ' . $fails . " failed\n";
exit( $fails ? 1 : 0 );
