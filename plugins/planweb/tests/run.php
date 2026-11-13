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

if ( ! defined( 'ABSPATH' ) && 'cli' !== PHP_SAPI ) { // web requests stop here; the Deploy check runs this from the command line
	exit;
}

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput -- a command-line test runner.

define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__ ) . '/includes/model.php';
require dirname( __DIR__ ) . '/includes/feed-model.php';

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

// Backup before uninstall deletes (includes/uninstall-backup.php): the lines, the 500-row batches, the folder guard.
require_once dirname( __DIR__ ) . '/includes/uninstall-backup.php';
$ub_t = static function ( bool $ok, string $what ): void {
	check( $ok, $what );
};
$ub_h = json_decode( planweb_backup_header( 'planweb', '1.2.3', 'https://example.org/', 1763000000, array( 'a_opt', 'b_opt' ), array( 'wp_x' ) ), true );
$ub_t( 'header' === $ub_h['type'] && 'planweb' === $ub_h['plugin'] && '1.2.3' === $ub_h['version'] && 'https://example.org/' === $ub_h['site'], 'backup: header names the plugin, version and site' );
$ub_t( '2025-11-13T02:13:20Z' === $ub_h['time'] && array( 'a_opt', 'b_opt' ) === $ub_h['options'] && array( 'wp_x' ) === $ub_h['tables'], 'backup: header has the time, the options and the tables' );
$ub_l = planweb_backup_option_line( 'a_opt', 'a:1:{s:1:"k";s:3:"é/";}', 'yes' );
$ub_o = json_decode( $ub_l, true );
$ub_t( "\n" === substr( $ub_l, -1 ) && 1 === substr_count( $ub_l, "\n" ), 'backup: one line per option' );
$ub_t( 'option' === $ub_o['type'] && 'a:1:{s:1:"k";s:3:"é/";}' === $ub_o['value'] && 'yes' === $ub_o['autoload'] && ! isset( $ub_o['b64'] ), 'backup: option value kept exactly as stored' );
$ub_o = json_decode( planweb_backup_option_line( 'bin', "\xff\x00\xfe", 'no' ), true );
$ub_t( array( 'value' ) === $ub_o['b64'] && "\xff\x00\xfe" === base64_decode( $ub_o['value'] ), 'backup: a value that is not UTF-8 is kept base64-encoded' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- the test reads the value back
$ub_r = json_decode( planweb_backup_row_line( 'wp_x', array( 'id' => '7', 'note' => null, 'blob' => "\x80" ) ), true );
$ub_t( 'row' === $ub_r['type'] && 'wp_x' === $ub_r['table'] && '7' === $ub_r['row']['id'] && null === $ub_r['row']['note'] && array( 'blob' ) === $ub_r['b64'], 'backup: row line keeps every column, null and binary' );
$ub_t( '{}' === substr( trim( planweb_backup_row_line( 'wp_x', array() ) ), -3, 2 ), 'backup: an empty row is an object' );
$ub_t( 500 === PLANWEB_BACKUP_BATCH, 'backup: tables are read 500 rows at a time' );
$ub_calls = array();
$ub_seen  = 0;
$ub_fetch = static function ( int $offset, int $limit ) use ( &$ub_calls ) {
	$ub_calls[] = array( $offset, $limit );
	return array_fill( 0, max( 0, min( $limit, 1203 - $offset ) ), array( 'id' => '1' ) );
};
$ub_n     = planweb_backup_rows(
	$ub_fetch,
	static function ( array $row ) use ( &$ub_seen ): bool {
		++$ub_seen;
		return true;
	}
);
$ub_t( 1203 === $ub_n && 1203 === $ub_seen && array( array( 0, 500 ), array( 500, 500 ), array( 1000, 500 ) ) === $ub_calls, 'backup: 1203 rows in batches of 500' );
$ub_calls = array();
$ub_n     = planweb_backup_rows( static fn( int $o, int $l ) => $o < 1000 ? array_fill( 0, $l, array() ) : array(), static fn( array $r ): bool => true );
$ub_t( 1000 === $ub_n, 'backup: an exact multiple of 500 ends on the empty batch' );
$ub_t( false === planweb_backup_rows( static fn( int $o, int $l ) => null, static fn( array $r ): bool => true ), 'backup: a read error stops the backup' );
$ub_w = 0;
$ub_n = planweb_backup_rows(
	static fn( int $o, int $l ) => array_fill( 0, $l, array() ),
	static function ( array $r ) use ( &$ub_w ): bool {
		return ++$ub_w < 3;
	}
);
$ub_t( false === $ub_n && 3 === $ub_w, 'backup: a write error stops the backup at once' );
$ub_t( 1 === preg_match( '/^planweb-uninstall-\d{8}-\d{6}\.jsonl\.gz$/', planweb_backup_name( 'planweb', 1763000000 ) ) && 'planweb-uninstall-20251113-021320.jsonl.gz' === planweb_backup_name( 'planweb', 1763000000 ), 'backup: file name' );
$ub_dir = sys_get_temp_dir() . '/planweb-ub-' . getmypid();
@mkdir( $ub_dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions -- a temporary folder for the test
file_put_contents( $ub_dir . '/index.php', 'kept' ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- the test's own file
$ub_t( planweb_backup_protect( $ub_dir ) && false !== strpos( (string) file_get_contents( $ub_dir . '/.htaccess' ), 'Require all denied' ) && 'kept' === file_get_contents( $ub_dir . '/index.php' ), 'backup: folder guarded by .htaccess and index.php, an existing file kept' ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- the test's own files
$ub_gz = gzopen( $ub_dir . '/t.jsonl.gz', 'wb6' ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- the test's own file
gzwrite( $ub_gz, planweb_backup_header( 'planweb', '1', 's', 0, array( 'a' ), array() ) . planweb_backup_option_line( 'a', 'v', 'yes' ) );
gzclose( $ub_gz );
$ub_lines = gzfile( $ub_dir . '/t.jsonl.gz' );
$ub_t( 2 === count( $ub_lines ) && 'header' === json_decode( $ub_lines[0], true )['type'] && 'v' === json_decode( $ub_lines[1], true )['value'], 'backup: gzip JSON lines read back' );
foreach ( array( 'index.php', '.htaccess', 't.jsonl.gz' ) as $ub_f ) {
	unlink( $ub_dir . '/' . $ub_f ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- the test's own files
}
rmdir( $ub_dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- the test's own folder
$ub_dir = sys_get_temp_dir() . '/planweb-ub-ro-' . getmypid();
@mkdir( $ub_dir, 0555 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions -- a read-only folder for the test
$ub_t( 0 === getmyuid() || ! @planweb_backup_protect( $ub_dir ), 'backup: a folder that cannot be written is refused' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- the warning is the expected failure
rmdir( $ub_dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- the test's own folder

// Several channels (14 Nov 2026).
check( array( 'x', 'linkedin' ) === planweb_title_channels( '[X + LinkedIn] Lab rules in plain words (set 4)' ), 'title channels: X + LinkedIn' );
check( array( 'instagram', 'facebook', 'threads' ) === planweb_title_channels( '[Instagram + Facebook + Threads] Lab rules wait period' ), 'title channels: three, any case' );
check( array() === planweb_title_channels( '[X] One channel' ), 'title channels: one channel is not a bracket list' );
check( array() === planweb_title_channels( '[X + Mastodon] Not a channel' ), 'title channels: an unknown part leaves it alone' );
check( array() === planweb_title_channels( 'Plain title [X + LinkedIn]' ), 'title channels: only at the start' );
check( array( 'x', 'linkedin' ) === planweb_channel_list( 'x, LinkedIn,,x' ), 'channel list from text' );
check( array( 'x' ) === planweb_channel_list( array( 'x', '', 'x' ) ), 'channel list from an array' );
check( array() === planweb_channel_list( array( '' ) ), 'channel list: the form\'s empty box clears' );
check( isset( PLANWEB_CHANNELS['threads'], PLANWEB_CHANNELS['bluesky'], PLANWEB_STATUSES['done'] ), 'Threads, Bluesky and Done are on the lists' );
check( '' === planweb_link_problem( 'https://bsky.app/profile/x', 'animalwrites.in' ), 'Bluesky link allowed' );

// The calendar feed.
check( array( 'social', 'email', 'campaign', 'content' ) === planweb_feed_list( 'marketing', PLANWEB_TYPES, PLANWEB_FEED_TYPE_GROUPS ), 'feed: marketing types' );
check( array( 'approved', 'due' ) === planweb_feed_list( 'approved,due', PLANWEB_STATUSES, PLANWEB_FEED_STATUS_GROUPS ), 'feed: statuses' );
check( array() === planweb_feed_list( '', PLANWEB_TYPES, PLANWEB_FEED_TYPE_GROUPS ), 'feed: no filter' );
check( null === planweb_feed_list( 'social,spam', PLANWEB_TYPES, PLANWEB_FEED_TYPE_GROUPS ), 'feed: unknown value refused' );
$fe = array(
	array(
		'id'       => 1,
		'date'     => '2026-11-20',
		'time'     => '09:30',
		'title'    => 'Post; with, commas',
		'type'     => 'social',
		'channels' => array( 'x', 'linkedin' ),
		'status'   => 'approved',
		'link'     => 'https://x.com/a',
		'note'     => 'SECRET NOTE',
	),
	array(
		'id'       => 2,
		'date'     => '2026-11-21',
		'time'     => '',
		'title'    => 'Call 98765 43210 about it',
		'type'     => 'reminder',
		'channels' => array(),
		'status'   => 'done',
		'link'     => 'https://evil.example/x',
		'note'     => '',
	),
	array(
		'id'       => 3,
		'date'     => '2026-11-22',
		'time'     => '',
		'title'    => str_repeat( 'Gaushālā ', 20 ),
		'type'     => 'content',
		'channels' => array( 'site' ),
		'status'   => 'idea',
		'link'     => '',
		'note'     => '',
	),
);
check( array( 1, 3 ) === array_column( planweb_feed_pick( $fe, PLANWEB_FEED_TYPE_GROUPS['marketing'], array() ), 'id' ), 'feed: marketing only' );
check( array( 1, 3 ) === array_column( planweb_feed_pick( $fe, array(), PLANWEB_FEED_STATUS_GROUPS['open'] ), 'id' ), 'feed: open statuses only (not done)' );
$ics       = planweb_ics(
	$fe,
	array(
		'name'     => 'Animal Writes: PlanWeb',
		'version'  => '0.2.0',
		'host'     => 'animalwrites.in',
		'stamp'    => '20261114T120000Z',
		'types'    => PLANWEB_TYPES,
		'channels' => PLANWEB_CHANNELS,
		'statuses' => PLANWEB_STATUSES,
	)
);
$ics_lines = explode( "\r\n", $ics );
check( '' === end( $ics_lines ) && false === strpos( str_replace( "\r\n", '', $ics ), "\n" ), 'ics: CRLF line ends only' );
check( 0 === count( array_filter( $ics_lines, fn( $l ) => strlen( $l ) > 75 ) ), 'ics: no line over 75 octets' );
check( (bool) mb_check_encoding( $ics, 'UTF-8' ), 'ics: folding keeps UTF-8 whole' );
$ics_un = preg_replace( "/\r\n /", '', $ics );
check( 3 === substr_count( $ics_un, 'BEGIN:VEVENT' ) && 3 === substr_count( $ics_un, 'END:VEVENT' ) && str_starts_with( $ics_un, "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:" ) && str_ends_with( $ics_un, "END:VCALENDAR\r\n" ), 'ics: calendar and events open and close' );
check( str_contains( $ics_un, "DTSTART:20261120T040000Z\r\nDTEND:20261120T043000Z" ), 'ics: 09:30 IST is 04:00 UTC' );
check( str_contains( $ics_un, "DTSTART;VALUE=DATE:20261121\r\nDTEND;VALUE=DATE:20261122" ), 'ics: all-day entry' );
check( str_contains( $ics_un, 'SUMMARY:Post\; with\, commas' ), 'ics: text escaped' );
check( str_contains( $ics_un, 'DESCRIPTION:Type: Social\nChannels: X\, LinkedIn\nStatus: Approved\nLink: https://x.com/a' ), 'ics: type, channels, status, link' );
check( false === strpos( $ics_un, 'SECRET NOTE' ), 'ics: never the note' );
check( false === strpos( $ics_un, '98765' ) && str_contains( $ics_un, 'SUMMARY:Entry 2 (title not shown' ), 'ics: a title with a phone number is not shown' );
check( false === strpos( $ics_un, 'evil.example' ), 'ics: a link to another site is left out' );
check( str_contains( $ics_un, 'UID:planweb-1@animalwrites.in' ) && str_contains( $ics_un, 'DTSTAMP:20261114T120000Z' ), 'ics: UID and DTSTAMP' );
check( str_contains( $ics_un, 'Status: Done' ), 'ics: Done status' );

echo $fails ? "$fails of $count checks failed.\n" : "All $count checks passed.\n";
exit( $fails ? 1 : 0 );
