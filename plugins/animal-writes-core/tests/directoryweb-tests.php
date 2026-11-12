<?php
/**
 * DirectoryWeb screens' pure parts (includes/directoryweb-shape.php), without WordPress. Run by the Deploy check.
 *
 * Usage: php plugins/animal-writes-core/tests/directoryweb-tests.php
 *
 * @package animal-writes-core
 */

// phpcs:disable WordPress.Security.EscapeOutput -- a command-line test runner.

define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__ ) . '/includes/directoryweb-shape.php';

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

// Status.
check( 'pending' === directoryweb_claim_status( array() ), 'no review is pending' );
check( 'approved' === directoryweb_claim_status( array( 'status' => 'approved' ) ), 'approved review' );
check( 'rejected' === directoryweb_claim_status( 'rejected' ), 'old-form status string' );
check( 'pending' === directoryweb_claim_status( array( 'status' => 'removed' ) ), 'an unknown status reads as pending' );

// The list: views, filters, sort, pages.
$row  = fn( $st, $sent, $title, $name, $state = 0, $types = array(), $has = array() ) => array(
	'status'        => $st,
	'sent'          => $sent,
	'listing_title' => $title,
	'name'          => $name,
	'state'         => $state,
	'types'         => $types,
	'has'           => $has + array(
		'logo'   => false,
		'photos' => false,
		'proof'  => false,
	),
);
$rows = array(
	$row( 'pending', '2026-11-10 10:00:00', 'Paws Trust', 'Asha', 32, array( 'shelter' ), array( 'logo' => true ) ),
	$row( 'pending', '2026-10-01 10:00:00', 'Blue Cross', 'Ravi', 29, array( 'veterinary-clinic' ) ),
	$row( 'approved', '2026-11-05 10:00:00', 'Animal Aid', 'Meera', 32, array( 'shelter' ), array( 'proof' => true ) ),
	$row( 'rejected', '2026-11-09 10:00:00', 'Gau Seva', 'Asha', 8 ),
);
$now  = strtotime( '2026-11-11 12:00:00' );
$q    = directoryweb_claims_query( $rows, array( 'now' => $now ) );
check( 2 === $q['total'], 'default view is the waiting claims' );
check( 'Paws Trust' === $q['rows'][0]['listing_title'], 'newest first' );
check( 4 === $q['counts']['all'] && 2 === $q['counts']['pending'] && 1 === $q['counts']['approved'], 'view counts' );
$q = directoryweb_claims_query(
	$rows,
	array(
		'status' => 'all',
		'search' => 'asha',
		'now'    => $now,
	)
);
check( 2 === $q['total'], 'search by claimant, any case' );
$rows[0]['ref'] = 'AW-C-17';
$q              = directoryweb_claims_query(
	$rows,
	array(
		'status' => 'all',
		'search' => 'aw-c-17',
		'now'    => $now,
	)
);
check( 1 === $q['total'] && 'Paws Trust' === $q['rows'][0]['listing_title'], 'search by reference' );
$q = directoryweb_claims_query(
	$rows,
	array(
		'status' => 'all',
		'state'  => 32,
		'type'   => 'shelter',
		'now'    => $now,
	)
);
check( 2 === $q['total'], 'state and type filters' );
check( 1 === $q['counts']['pending'] && 1 === $q['counts']['approved'], 'view counts follow the filters' );
$q = directoryweb_claims_query(
	$rows,
	array(
		'status' => 'all',
		'has'    => 'proof',
		'now'    => $now,
	)
);
check( 1 === $q['total'] && 'Animal Aid' === $q['rows'][0]['listing_title'], 'sent with proof' );
$q = directoryweb_claims_query(
	$rows,
	array(
		'status' => 'all',
		'days'   => 7,
		'now'    => $now,
	)
);
check( 3 === $q['total'], 'last 7 days' );
$q = directoryweb_claims_query(
	$rows,
	array(
		'status' => 'all',
		'sort'   => 'listing',
		'dir'    => 'asc',
		'per'    => 2,
		'page'   => 2,
		'now'    => $now,
	)
);
check( 2 === $q['pages'] && 2 === $q['page'] && 'Gau Seva' === $q['rows'][0]['listing_title'], 'sort by listing, page 2' );
$q = directoryweb_claims_query(
	$rows,
	array(
		'status' => 'all',
		'page'   => 99,
		'now'    => $now,
	)
);
check( 1 === $q['page'], 'a page past the end shows the last page' );

// A proposed phone list with a line that is not a phone number (the claim's note).
$ok = fn( $v ) => (bool) preg_match( '/^[0-9+ ]{6,}$/', trim( (string) $v ) );
check( directoryweb_phones_bad( "+91 98765 43210 | Office\nnot a number", $ok ), 'a bad phone line is noticed' );
check( ! directoryweb_phones_bad( "+91 98765 43210 | Office | WhatsApp\n", $ok ), 'a good phone list passes' );

// Change-log field names.
$l = array(
	'l_email'    => array( 'Listing email', 'aw_email', 'email', true ),
	'phones'     => array( 'Phone numbers', 'phones', 'phone', true ),
	'l_services' => array( 'Services', 'services', 'services', true ),
	'l_about'    => array( 'About', 'about', 'about', true ),
);
check( 'email' === directoryweb_enrich_field( 'l_email', $l ), 'meta field logs as its enrichment name' );
check( 'phone' === directoryweb_enrich_field( 'phones', $l ), 'phones log as phone' );
check( 'logo' === directoryweb_enrich_field( 'logo', $l ), 'logo' );
check( '' === directoryweb_enrich_field( 'nope', $l ), 'unknown field' );

// The Claims routes only read: nothing decides, applies or undoes a claim from the new screen.
$rest = (string) file_get_contents( dirname( __DIR__ ) . '/includes/directoryweb-rest.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file read by the test
check( false === strpos( $rest, '/decide' ) && false === strpos( $rest, '/undo' ), 'no decide or undo route' );
check( false === strpos( $rest, 'aw_claim_ff_decide' ) && false === strpos( $rest, 'aw_claim_apply_field' ) && false === strpos( $rest, 'aw_enrich_undo(' ), 'no claim or listing write' );
check( 2 === preg_match_all( "/'methods'\s*=>\s*'POST'/", $rest ), 'POST only for dashboard/count and settings/claim-form' );

// The claim form: an unchanged Save writes nothing; a change keeps the other forms.
$ids = array(
	'claim'   => 6,
	'contact' => 4,
);
check( null === directoryweb_claim_form_change( $ids, 6 ), 'same form: nothing to write' );
check( null === directoryweb_claim_form_change( $ids, 0 ), 'no form: nothing to write' );
$n = directoryweb_claim_form_change( $ids, 9 );
check( is_array( $n ) && 9 === $n['claim'] && 4 === $n['contact'], 'new form, other forms kept' );

// Top types.
$t = directoryweb_top_types(
	array(
		'a' => 1,
		'b' => 9,
		'c' => 5,
		'd' => 0,
		'e' => 2,
	),
	2
);
check( array( 'b' => 9, 'c' => 5 ) === $t['top'] && 3 === $t['others'], 'top two, the rest summed, zero left out' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- a short expected value

// Age.
check( 3 === directoryweb_age_days( '2026-11-08 11:00:00', $now ), 'age in whole days' );
check( 0 === directoryweb_age_days( 'not a date', $now ), 'bad date' );

echo "{$count} checks, {$fails} failed\n";
exit( $fails ? 1 : 0 );
