<?php
/**
 * PlanWeb tests that need no WordPress (owner rule: no local WordPress or MySQL): the field checks (date, time, link,
 * no personal data) and the Dates import mapping and its one-for-one comparison (includes/model.php). Run by the
 * Deploy check; the import itself is run against a real WordPress there too (tools/ci/smoke.sh).
 *
 * Usage: php plugins/planweb/tests/run.php
 *
 * @package planweb
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput -- a command-line test runner.

define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__ ) . '/includes/model.php';

$fails = 0;
$count = 0;

/**
 * One check.
 *
 * @param bool   $ok   Passed.
 * @param string $name What it checks.
 */
function check( bool $ok, string $name ) {
	global $fails, $count;
	++$count;
	if ( ! $ok ) {
		++$fails;
		echo "FAIL $name\n";
	}
}

// Dates and times.
check( planweb_is_date( '2026-11-17' ), 'a real date' );
check( ! planweb_is_date( '2026-02-30' ), 'no 30 Feb' );
check( ! planweb_is_date( '17 Nov 2026' ), 'only Y-m-d' );
check( ! planweb_is_date( null ), 'null is not a date' );
check( planweb_is_time( '' ) && planweb_is_time( '09:30' ) && planweb_is_time( '23:59' ), 'empty or H:i' );
check( ! planweb_is_time( '24:00' ) && ! planweb_is_time( '9:30' ) && ! planweb_is_time( '09:30:00' ), 'no other times' );

// Links: this site and social sites only.
$site = 'animalwrites.in';
check( '' === planweb_link_problem( '', $site ), 'empty link is fine' );
check( '' === planweb_link_problem( 'https://animalwrites.in/campaigns/x/', $site ), 'own page' );
check( '' === planweb_link_problem( 'https://www.animalwrites.in/', $site ), 'own page with www' );
check( '' === planweb_link_problem( 'https://x.com/animal_writes_/status/1', $site ), 'X' );
check( '' === planweb_link_problem( 'https://www.linkedin.com/company/animalwrites', $site ), 'LinkedIn with www' );
check( '' === planweb_link_problem( 'https://in.linkedin.com/company/animalwrites', $site ), 'LinkedIn subdomain' );
check( '' === planweb_link_problem( 'https://www.whatsapp.com/channel/abc', $site ), 'WhatsApp channel' );
check( '' !== planweb_link_problem( 'https://wa.me/919999999999', $site ), 'no wa.me' );
check( '' !== planweb_link_problem( 'https://api.whatsapp.com/send?phone=919999999999', $site ), 'no WhatsApp send link' );
check( '' !== planweb_link_problem( 'tel:+919999999999', $site ), 'no tel:' );
check( '' !== planweb_link_problem( 'mailto:someone@example.com', $site ), 'no mailto:' );
check( '' !== planweb_link_problem( 'https://example.com/', $site ), 'no other site' );
check( '' !== planweb_link_problem( 'https://animalwrites.in.example.com/', $site ), 'no look-alike host' );
check( '' !== planweb_link_problem( 'https://notx.com/', $site ), 'no host ending in x.com' );
check( '' !== planweb_link_problem( 'https://user@animalwrites.in/', $site ), 'no user in the address' );
check( '' !== planweb_link_problem( 'javascript:alert(1)', $site ), 'no javascript:' );

// No personal data; the real Dates entries pass.
check( '' !== planweb_personal_data_problem( 'Write to someone@example.com' ), 'email refused' );
check( '' !== planweb_personal_data_problem( 'Call +91 98765 43210' ), 'phone refused' );
check( '' !== planweb_personal_data_problem( 'Call 098765-43210' ), 'phone with dash refused' );
check( '' !== planweb_personal_data_problem( 'Call (022) 2345 6789' ), 'landline refused' );
$live = array(
	'Cause-list check, SMWP (C) 5/2025, for 17 Nov',
	'Supreme Court cause list (advance and daily lists for 17 Nov 2026) and case status, diary no. 41706/2025. Repeat on 13 and 14 Nov.',
	'Scheduled tasks at 10:00 (Directory indexing against the 20 Oct baseline) and 11:00 (8 tracked keywords).',
	'Search Insights → Pages: the campaign, the explainer and the Library judgment of 19 May 2026, impressions, clicks and position for 17 Nov–14 Dec against the 1 Nov baseline.',
	'help-now guides 28-day checkpoint: 1962 (13798), who-to-call (9031), injured-animal (126) and dog-bite (17307)',
	'Campaign form #10: 8,089 entries (8,079 yesterday)',
	'Supreme Court stray-dog case: High Court compliance reports due (19 May 2026 judgment)',
);
foreach ( $live as $i => $text ) {
	check( '' === planweb_personal_data_problem( $text ), "live text $i passes" );
}

// The import mapping.
$stored = array(
	array(
		'date'  => '2026-11-05',
		'title' => 'Lab rules objections close',
		'note'  => 'Campaign 13848.',
	),
	array(
		'date'  => '2026-11-17',
		'title' => 'Rankings recheck',
		'note'  => '',
	),
	array(
		'date'  => '2026-11-05',
		'title' => 'Lab rules objections close',
		'note'  => 'Campaign 13848.',
	), // an identical copy
	array(
		'date'  => 'soon',
		'title' => 'Not a date',
	),
	'a string',
	array(
		'date'  => '2026-12-01',
		'title' => '',
	),
	array(
		'date'  => '2026-12-01',
		'title' => 'No note key',
	),
);
$mapped = planweb_map_dates( $stored );
check( 4 === count( $mapped['rows'] ), 'four valid rows' );
check( 3 === count( $mapped['invalid'] ), 'three invalid elements listed' );
check( $mapped['rows'][0]['key'] !== $mapped['rows'][2]['key'], 'identical copies get different keys' );
check( planweb_source_key( $stored[0], 2 ) === $mapped['rows'][2]['key'], 'second copy is copy 2' );
check( '' === $mapped['rows'][3]['note'], 'missing note becomes empty' );
check( 6 === $mapped['rows'][3]['index'], 'stored position kept' );
check( planweb_map_dates( $stored )['rows'] === $mapped['rows'], 'same keys on a second run' );
check( array() === planweb_map_dates( null )['rows'] && array() === planweb_map_dates( 'x' )['rows'], 'no list, no rows' );

// The one-for-one comparison.
$entries = array();
foreach ( $mapped['rows'] as $n => $r ) {
	$entries[ $r['key'] ] = array(
		'id'    => 100 + $n,
		'date'  => $r['date'],
		'title' => $r['title'],
		'note'  => $r['note'],
	);
}
$cmp = planweb_compare( $mapped['rows'], $entries );
check( 4 === $cmp['same'] && 0 === $cmp['differs'] + $cmp['missing'] + $cmp['extra'], 'all the same after an import' );
$k                      = $mapped['rows'][1]['key'];
$entries[ $k ]['title'] = 'Rankings recheck (edited)';
unset( $entries[ $mapped['rows'][0]['key'] ] );
$entries['other'] = array(
	'id'    => 999,
	'date'  => '2026-12-31',
	'title' => 'Not from Dates',
	'note'  => '',
);
$cmp              = planweb_compare( $mapped['rows'], $entries );
check( 1 === $cmp['differs'] && 1 === $cmp['missing'] && 1 === $cmp['extra'] && 2 === $cmp['same'], 'differs, missing and extra counted' );

echo $fails ? "$fails of $count checks failed.\n" : "All $count checks passed.\n";
exit( $fails ? 1 : 0 );
