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
check( '' === planweb_personal_data_problem( 'Recheck 2026-11-13 2026-11-14' ), 'two Y-m-d dates are not a phone' );
check( '' !== planweb_personal_data_problem( 'On 2026-11-13 call 98765 43210' ), 'phone next to a date still refused' );
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
	), // An identical copy.
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

// The new screens (includes/ui-model.php): the counts, the tiles, the list's arguments, the switch. WordPress's
// translation and number functions are stood in for.
require dirname( __DIR__ ) . '/includes/ui-model.php';
if ( ! function_exists( '__' ) ) {
	/**
	 * Stand-in.
	 *
	 * @param string $t Text.
	 */
	function __( $t ) {
		return $t;
	}
	/**
	 * Stand-in.
	 *
	 * @param string $one  Singular.
	 * @param string $many Plural.
	 * @param int    $n    Number.
	 */
	function _n( $one, $many, $n ) {
		return 1 === $n ? $one : $many;
	}
	/**
	 * Stand-in.
	 *
	 * @param int $n Number.
	 */
	function number_format_i18n( $n ) {
		return number_format( $n );
	}
}

check( '2026-12-01' === planweb_add_days( '2026-11-30', 1 ) && '2026-10-12' === planweb_add_days( '2026-11-11', -30 ), 'days added across months' );
$today = '2026-11-11';
$es    = array(
	array(
		'id'     => 1,
		'date'   => '2026-11-11',
		'time'   => '',
		'title'  => 'Today',
		'type'   => 'social',
		'status' => 'draft',
	),
	array(
		'id'     => 2,
		'date'   => '2026-11-17',
		'time'   => '10:00',
		'title'  => 'Rankings',
		'type'   => 'content',
		'status' => 'approved',
	),
	array(
		'id'     => 3,
		'date'   => '2026-12-05',
		'time'   => '',
		'title'  => 'Jharkhand',
		'type'   => 'campaign',
		'status' => 'idea',
	),
	array(
		'id'     => 4,
		'date'   => '2026-11-07',
		'time'   => '',
		'title'  => 'Lab rules',
		'type'   => 'reminder',
		'status' => 'idea',
	),
	array(
		'id'     => 5,
		'date'   => '2026-11-01',
		'time'   => '',
		'title'  => 'Sent one',
		'type'   => 'email',
		'status' => 'sent',
	),
	array(
		'id'     => 6,
		'date'   => '2027-03-01',
		'time'   => '',
		'title'  => 'Far',
		'type'   => 'bogus',
		'status' => 'bogus',
	),
);
$c     = planweb_count_entries( $es, $today, array( 'same' => 4 ) );
check( 6 === $c['total'] && 1 === $c['today'], 'total and today counted' );
check( 2 === $c['ahead'][7]['total'], 'next 7 days: today and 17 Nov' );
check( 3 === $c['ahead'][30]['total'] && 3 === $c['ahead'][90]['total'], 'next 30 and 90 days' );
check( 1 === $c['ahead'][30]['types']['campaign'] && 0 === $c['ahead'][30]['types']['email'], 'by type' );
check( 2 === $c['types']['reminder'], 'unknown type counts as reminder' );
check( 1 === $c['overdue']['total'] && '2026-11-07' === $c['overdue']['oldest'], 'overdue: open and past only, not sent' );
check( 1 === $c['waiting'] && 1 === $c['ready'], 'waiting (draft) and ready (approved)' );
check( 3 === $c['statuses']['idea'] && 1 === $c['statuses']['sent'], 'by status; unknown status counts as idea' );
check( 1 === $c['next']['id'] && ! isset( $c['next']['key'] ), 'next date is today\'s entry' );
check(
	array(
		'same'    => 4,
		'differs' => 0,
		'missing' => 0,
		'extra'   => 0,
	) === $c['check'],
	'import check kept'
);
check( planweb_is_overdue( array( 'flag' => 'overdue' ), $today ), 'scheduler flag is overdue' );
$empty = planweb_count_entries( array(), $today );
check( 0 === $empty['total'] && null === $empty['next'] && array() === $empty['check'], 'no entries, zero counts' );

$seen_urls = array();
$tiles     = planweb_dashboard_tiles(
	$c + array( 'at' => 1 ),
	7,
	function ( array $a ) use ( &$seen_urls ) {
		$seen_urls[] = $a;
		return 'u?' . http_build_query( $a );
	},
	fn( $d ) => 'L' . $d
);
$keys      = array_column( $tiles, 'key' );
check( array( 'ahead', 'overdue', 'waiting', 'ready', 'next', 'today', 'total', 'check' ) === $keys, 'eight tiles in order' );
check( '2' === $tiles[0]['value'] && 'Coming up in 7 days' === $tiles[0]['label'] && 'u?when=next7' === $tiles[0]['href'], 'coming-up tile for the range' );
check( '1 social, 1 content' === $tiles[0]['sub'] || '1 content, 1 social' === $tiles[0]['sub'], 'coming-up sub-line by type' );
check( 'warn' === $tiles[1]['tone'] && 'Oldest from L2026-11-07' === $tiles[1]['sub'], 'overdue tile warns with the oldest date' );
check( 'L2026-11-11' === $tiles[4]['value'] && 'Today' === $tiles[4]['sub'], 'next date tile' );
check( 'Matches' === $tiles[7]['value'] && '' === $tiles[7]['tone'], 'import check tile' );
$t90 = planweb_dashboard_tiles( $empty, 99, fn() => '', fn( $d ) => $d );
check( 'Coming up in 30 days' === $t90[0]['label'] && 7 === count( $t90 ) && '' === $t90[1]['tone'], 'unknown range is 30; no check tile without an import' );

$a = planweb_list_args( array(), $today );
check( 'from30' === $a['when'] && '2026-10-12' === $a['from'] && '' === $a['to'] && 'date' === $a['sort'] && 'asc' === $a['dir'] && 1 === $a['page'], 'list defaults: from 30 days ago, by date' );
$a = planweb_list_args(
	array(
		'when'   => 'next7',
		'type'   => 'social',
		'status' => 'nope',
		'sort'   => 'title',
		'dir'    => 'desc',
		'page'   => '3',
	),
	$today
);
check( '2026-11-11' === $a['from'] && '2026-11-17' === $a['to'] && 'social' === $a['type'] && '' === $a['status'] && 'title' === $a['sort'] && 'desc' === $a['dir'] && 3 === $a['page'], 'list arguments checked' );
$a = planweb_list_args( array( 'view' => 'overdue' ), $today );
check( 'all' === $a['when'] && '' === $a['from'] && $today === $a['before'], 'overdue view: before today, any date' );
$a = planweb_list_args( array( 'when' => 'past' ), $today );
check( $today === $a['before'] && '' === $a['from'], 'before today' );
$a = planweb_list_args(
	array(
		'when' => 'all',
		'view' => 'x',
	),
	$today
);
check( '' === $a['from'] . $a['to'] . $a['before'] && 'all' === $a['view'], 'all dates; unknown view is all' );

// Save only what changed: the switch is written only when its stored form changes.
check( null === planweb_switch_change( true, '1' ) && null === planweb_switch_change( false, '' ), 'unchanged switch writes nothing' );
check( '' === planweb_switch_change( false, '1' ) && '1' === planweb_switch_change( true, '' ), 'changed switch gives the new stored value' );
check( '1' === planweb_on_clean( '1' ) && '' === planweb_on_clean( '' ) && '' === planweb_on_clean( 0 ), 'the same clean-up as Kits → Modules' );

echo $fails ? "$fails of $count checks failed.\n" : "All $count checks passed.\n";
exit( $fails ? 1 : 0 );
