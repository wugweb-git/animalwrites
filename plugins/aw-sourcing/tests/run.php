<?php
/**
 * Sourcing tests that need no WordPress (owner rule: no local WordPress or MySQL): the shared keyword matcher against
 * tests/fixtures/match_cases.json (the same file as the workers' tests) and the phase 1 Sources model (sources-model.php)
 * against the live shapes of 25 Oct 2026 (tests/fixtures/phase1_live_sample.json). Run by the Deploy check.
 *
 * Usage: php plugins/aw-sourcing/tests/run.php
 *
 * @package aw-sourcing
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput -- a command-line test runner.

define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__ ) . '/includes/sources-model.php';

$fails = 0;
$count = 0;

/**
 * One check.
 *
 * @param bool   $ok   Passed.
 * @param string $what What was checked.
 */
function aw_t( $ok, $what ) {
	global $fails, $count;
	++$count;
	if ( ! $ok ) {
		++$fails;
		fwrite( STDERR, "FAIL: $what\n" );
	}
}

$fx = __DIR__ . '/fixtures/';

// ---- the shared matcher ----
$cases = json_decode( file_get_contents( $fx . 'match_cases.json' ), true )['cases'];
aw_t( count( $cases ) >= 30, 'match_cases.json has its cases' );
foreach ( $cases as $c ) {
	aw_t( aw_match_term( $c['text'], $c['term'] ) === $c['word'], 'match ' . json_encode( $c['term'] ) . ' in ' . json_encode( $c['text'] ) . ' (' . $c['note'] . ')' );
}

// ---- live shapes ----
$live    = json_decode( file_get_contents( $fx . 'phase1_live_sample.json' ), true );
$sources = $live['sources'];
$pubs    = $live['publishers'];
$log     = $live['relay_log'];
$relay   = $live['relay_default'];

// Hosts: the four site-limited Google News searches belong to their publisher (owner's decision 2).
$hosts = array();
foreach ( $sources as $s ) {
	$hosts[ $s['id'] ] = aw_src_source_host( $s );
}
aw_t( 'livelaw.in' === $hosts['court-news-animals'], 'court-news-animals belongs to livelaw.in' );
aw_t( 'barandbench.com' === $hosts['court-news-barandbench'], 'court-news-barandbench belongs to barandbench.com' );
aw_t( 'scobserver.in' === $hosts['court-news-scobserver'], 'court-news-scobserver belongs to scobserver.in' );
aw_t( 'pib.gov.in' === $hosts['pib-releases'], 'pib-releases belongs to pib.gov.in' );
aw_t( 'news.google.com' === $hosts['gnews-animal-welfare'] && 'news.google.com' === $hosts['gnews-lab-animal-rules'], 'open-web searches belong to news.google.com' );
aw_t( 'thehindu.com' === $hosts['the-hindu-environment'] && 'peopleforanimals.substack.com' === $hosts['pfa-substack'] && 'petaindia.com' === $hosts['peta-india'], 'RSS feeds belong to their site' );
aw_t( 'awbi.gov.in' === $hosts['awbi-circulars'] && 'ccsea.gov.in' === $hosts['ccsea-whats-new'] && 'egazette.gov.in' === $hosts['gazette-of-india'], 'code-read sources belong to their site' );
aw_t( '' === aw_src_search_site( 'https://news.google.com/rss/search?q=(site:livelaw.in+OR+site:barandbench.com)+dogs' ), 'a search over two sites belongs to no one site' );
aw_t( 'feed' === aw_src_read_by( $sources[0] ) && 'code' === aw_src_read_by( array( 'kind' => 'awbi' ) ), 'read by feed or code' );
foreach ( array( 'thehindu.com', 'livelaw.in', 'barandbench.com', 'peopleforanimals.substack.com', 'petaindia.com', 'scobserver.in', 'pib.gov.in' ) as $h ) {
	aw_t( isset( $pubs[ $h ] ), "$h has a publisher record already (no Neutral record needs adding)" );
}

// The migration plan: 20 sources stay 20, each gets its host; relay hosts 5 before, the same reach after.
$plan = aw_src_phase1_plan( $pubs, $sources, $relay, '2026-10-26' );
aw_t( 20 === count( $plan['sources'] ), 'sources: 20 before, 20 after' );
aw_t( array_column( $sources, 'id' ) === array_column( $plan['sources'], 'id' ), 'feed ids unchanged, same order' );
$same = true;
foreach ( $plan['sources'] as $i => $s ) {
	$was = $s;
	unset( $was['host'] );
	$same = $same && $was === $sources[ $i ] && '' !== (string) $s['host'];
}
aw_t( $same, 'each source gains only its host' );
aw_t( array( 'ccsea.gov.in' ) === $plan['added'], 'one relay-only record added: ccsea.gov.in' );
aw_t( 'relay' === $plan['pubs']['ccsea.gov.in']['kind'] && 'neutral' === $plan['pubs']['ccsea.gov.in']['status'] && ! empty( $plan['pubs']['ccsea.gov.in']['relay'] ), 'the relay-only record is kind=relay, neutral, relay on' );
aw_t( 'CCSEA – What\'s New' === $plan['pubs']['ccsea.gov.in']['name'] || '' !== $plan['pubs']['ccsea.gov.in']['name'], 'the relay-only record is named after its source' );
sort( $plan['flagged'] );
aw_t( array( 'awbi.gov.in', 'egazette.gov.in', 'peopleforanimals.substack.com', 'zoomtventertainment.com' ) === $plan['flagged'], 'relay switched on for 4 publishers' );
aw_t( array( 'substack.com' => 'peopleforanimals.substack.com' ) === $plan['mapped'], 'substack.com narrowed to PFA\'s Substack (decision 8)' );
aw_t( count( $pubs ) + 1 === count( $plan['pubs'] ), 'publishers: one record more' );
aw_t( 5 === count( $plan['relay_after'] ), 'relay hosts: 5 before, 5 after' );
aw_t( aw_src_host_in( 'peopleforanimals.substack.com', $plan['relay_after'] ), 'PFA Substack still relayed' );
aw_t( ! aw_src_host_in( 'someoneelse.substack.com', $plan['relay_after'] ) && ! aw_src_host_in( 'substack.com', $plan['relay_after'] ), 'other Substack sites no longer relayed' );
aw_t( aw_src_host_in( 'www.zoomtventertainment.com', $plan['relay_after'] ), 'Zoom TV (www.) still relayed' );
foreach ( $plan['pubs'] as $h => $p ) {
	if ( ! in_array( $h, array_merge( $plan['flagged'], $plan['added'] ), true ) ) {
		aw_t( $p === $pubs[ $h ], "publisher $h untouched" );
	}
}

// A second run changes nothing (idempotent).
$again = aw_src_phase1_plan( $plan['pubs'], $plan['sources'], $relay, '2026-10-27' );
aw_t( $again['pubs'] === $plan['pubs'] && $again['sources'] === $plan['sources'] && ! $again['added'] && ! $again['flagged'], 'running the migration twice changes nothing' );

// Compare (verify): no differences apart from the planned ones, also with ingest writing meanwhile.
$after = $plan['pubs'];
++$after['livelaw.in']['count'];
$after['livelaw.in']['last'] = '2026-10-27';
$after['newhost.example']    = array( 'status' => 'neutral', 'name' => 'New', 'count' => 1, 'first' => '2026-10-27', 'last' => '2026-10-27' );
$cmp                         = aw_src_phase1_compare( $pubs, $sources, $after, $plan['sources'], $relay, aw_src_relay_hosts_from_pubs( $after ), $log );
aw_t( array() === $cmp['diffs'], 'verify: 0 differences (' . implode( '; ', $cmp['diffs'] ) . ')' );
aw_t( in_array( 'relay-only record added for ccsea.gov.in', $cmp['planned'], true ) && in_array( 'host added to 20 sources', $cmp['planned'], true ), 'verify lists the planned changes' );
aw_t( (bool) array_filter( $cmp['since'], fn( $x ) => str_starts_with( $x, 'newhost.example' ) ), 'verify reports hosts first seen since the backup apart' );
$bad = $plan['pubs'];
unset( $bad['zoomtventertainment.com']['relay'] );
$bad['thehindu.com']['status'] = 'block';
$cmp                           = aw_src_phase1_compare( $pubs, $sources, $bad, $plan['sources'], $relay, aw_src_relay_hosts_from_pubs( $bad ), $log );
aw_t( 3 === count( $cmp['diffs'] ), 'verify finds a lost relay host (also in the log) and a changed status: ' . implode( '; ', $cmp['diffs'] ) );
$lost = $plan['sources'];
array_pop( $lost );
aw_t( (bool) aw_src_phase1_compare( $pubs, $sources, $plan['pubs'], $lost, $relay, $plan['relay_after'], $log )['diffs'], 'verify finds a lost source' );

// Rollback (undo) gives back the records as they were (and keeps what ingest added since).
$undo = aw_src_phase1_undo( $after, $plan['sources'] );
aw_t( $undo['sources'] === $sources, 'undo: sources as before' );
$exp = $pubs;
$exp['livelaw.in']      = $after['livelaw.in'];
$exp['newhost.example'] = $after['newhost.example'];
aw_t( $undo['pubs'] === $exp, 'undo: publishers as before, with ingest\'s changes kept' );
aw_t( array( 'ccsea.gov.in' ) === $undo['removed'], 'undo removes the relay-only record' );

// sources_v2: the v1 entries unchanged plus host, relay, read_by, check.
$v1 = array();
foreach ( $plan['sources'] as $s ) {
	if ( ! empty( $s['on'] ) ) {
		unset( $s['on'], $s['filter'] );
		$v1[] = $s;
	}
}
$v2 = aw_src_sources_v2( $v1, $plan['relay_after'] );
aw_t( count( $v2 ) === count( $v1 ) && 19 === count( $v2 ), 'sources_v2 has the 19 sources that are on' );
$ok = true;
foreach ( $v2 as $i => $e ) {
	$base = $e;
	unset( $base['relay'], $base['read_by'], $base['check'] );
	$ok = $ok && $base === $v1[ $i ];
}
aw_t( $ok, 'sources_v2 entries are the v1 entries plus relay, read_by, check' );
$by = array_column( $v2, null, 'id' );
aw_t( true === $by['pfa-substack']['relay'] && true === $by['ccsea-whats-new']['relay'] && true === $by['awbi-circulars']['relay'] && false === $by['peta-india']['relay'], 'sources_v2 relay switches' );
aw_t( 'code' === $by['gazette-of-india']['read_by'] && 'feed' === $by['pib-releases']['read_by'], 'sources_v2 read_by' );

// Change log: old and new only when given; the cap.
$l = aw_src_log_push( array(), array( 'at' => 'x', 'user' => 'u', 'tab' => 'sources', 'what' => 'w' ) );
aw_t( ! array_key_exists( 'old', $l[0] ) && ! array_key_exists( 'new', $l[0] ), 'log: no old/new keys when not given' );
$l = aw_src_log_push( $l, array( 'at' => 'y', 'user' => 'u', 'tab' => 'sources', 'what' => 'w2' ), 'neutral', 'block' );
aw_t( 'neutral' === $l[0]['old'] && 'block' === $l[0]['new'] && 2 === count( $l ), 'log: old and new kept, newest first' );
$big = array_fill( 0, 1005, array( 'at' => 'z' ) );
aw_t( 1000 === count( aw_src_log_push( $big, array( 'at' => 'n' ) ) ), 'log: 1,000 kept' );
aw_t( 'on' === aw_src_log_value( true ) && 'a, b' === aw_src_log_value( array( 'a', 'b' ) ) && '(none)' === aw_src_log_value( '' ), 'log values in words' );

// Last relay result: the newest row for the host or a subdomain.
$last = aw_src_relay_last( $log, 'zoomtventertainment.com' );
aw_t( is_array( $last ) && 'www.zoomtventertainment.com' === $last['host'], 'last relay result includes subdomains' );
aw_t( null === aw_src_relay_last( $log, 'thehindu.com' ), 'no relay result for a host never relayed' );

echo ( $fails ? "FAILED: $fails of $count\n" : "OK: $count checks\n" );
exit( $fails ? 1 : 0 );
