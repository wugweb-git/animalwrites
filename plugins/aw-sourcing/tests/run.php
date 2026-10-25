<?php
/**
 * Sourcing tests that need no WordPress (owner rule: no local WordPress or MySQL): the shared keyword matcher against
 * tests/fixtures/match_cases.json (the same file as the workers' tests) and the phase 1 Sources model (sources-model.php)
 * against the live shapes of 25 Oct 2026 (tests/fixtures/phase1_live_sample.json), and the phase 2 keyword model
 * (animal-writes-core keyword-model.php, keywords-model.php) against the live lists and keyword records
 * (tests/fixtures/phase2_live_sample.json), and the Sourcing menu's shape (menu-model.php, admin-menu.php). Run by the
 * Deploy check.
 *
 * Usage: php plugins/aw-sourcing/tests/run.php
 *
 * @package aw-sourcing
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput -- a command-line test runner.

define( 'ABSPATH', __DIR__ . '/' );

/**
 * WordPress's tag stripper, for aw_si_norm() (the only WordPress function the models use).
 *
 * @param string $s Text.
 */
function wp_strip_all_tags( $s ) {
	return trim( strip_tags( (string) preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $s ) ) );
}
require dirname( __DIR__, 2 ) . '/animal-writes-core/includes/keyword-model.php';
require dirname( __DIR__ ) . '/includes/sources-model.php';
require dirname( __DIR__ ) . '/includes/keywords-model.php';
require dirname( __DIR__ ) . '/includes/rules-model.php';
require dirname( __DIR__ ) . '/includes/retire-model.php';
require dirname( __DIR__ ) . '/includes/menu-model.php';

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

// Review fixes (25 Oct): a host set by hand before the migration survives a rollback; hosts reached only through the
// narrowed substack.com entry are a planned change, not a conflict; the relay index; the legacy list.
$hand                                = $sources;
$hand[2]['host']                     = 'example.org';
$plan2                               = aw_src_phase1_plan( $pubs, $hand, $relay, '2026-10-26' );
aw_t( ! in_array( $hand[2]['id'], $plan2['hosted'], true ) && 19 === count( $plan2['hosted'] ), 'the plan records which sources it gave a host' );
$undo2 = aw_src_phase1_undo( $plan2['pubs'], $plan2['sources'], $plan2['hosted'] );
aw_t( $undo2['sources'] === $hand, 'undo keeps a host set by hand before the migration' );
$log2   = $log;
$log2[] = array( 'at' => 1792999999, 'host' => 'someoneelse.substack.com', 'path' => '/feed', 'status' => 200, 'bytes' => 10, 'ms' => 5, 'note' => '' );
$cmp2   = aw_src_phase1_compare( $pubs, $sources, $plan['pubs'], $plan['sources'], $relay, $plan['relay_after'], $log2 );
aw_t( array() === $cmp2['diffs'] && (bool) array_filter( $cmp2['planned'], fn( $x ) => str_contains( $x, 'someoneelse.substack.com' ) ), 'another Substack in the log is a planned narrowing, not a conflict' );
$idx = aw_src_relay_index( $log );
aw_t( count( $idx ) < count( $log ) && aw_src_relay_last( $idx, 'peopleforanimals.substack.com' ) === aw_src_relay_last( $log, 'peopleforanimals.substack.com' ), 'relay index gives the same last result' );
aw_t( $relay === aw_src_relay_legacy( array(), $relay ) && array( 'a.com' ) === aw_src_relay_legacy( array( 'relay_hosts' => array( 'a.com' ) ), $relay ), 'legacy relay list: stored, else built in' );

// Last relay result: the newest row for the host or a subdomain.
$last = aw_src_relay_last( $log, 'zoomtventertainment.com' );
aw_t( is_array( $last ) && 'www.zoomtventertainment.com' === $last['host'], 'last relay result includes subdomains' );
aw_t( null === aw_src_relay_last( $log, 'thehindu.com' ), 'no relay result for a host never relayed' );


// ==== Phase 2: the keyword table ====

// The one role check: Sourcing and Press roles English only; never Include with Exclude; removing always allowed.
aw_t( array( 1, '' ) === aw_kw_roles_check( 0, AW_KW_ROLE_INCLUDE, 0, 'en' ), 'roles: Include on an English keyword' );
aw_t( '' !== aw_kw_roles_check( 0, AW_KW_ROLE_INCLUDE, 0, 'hi' )[1] && 0 === aw_kw_roles_check( 0, AW_KW_ROLE_INCLUDE, 0, 'hi' )[0], 'roles: Include refused on a Hindi keyword' );
aw_t( '' !== aw_kw_roles_check( 0, AW_KW_ROLE_PRESS, 0, 'hinglish' )[1], 'roles: Press refused on a Hinglish keyword' );
aw_t( array( AW_SI_ROLE_TRACKED, '' ) === aw_kw_roles_check( AW_SI_ROLE_TRACKED, AW_SI_ROLE_TRACKED, 0, 'hi' ), 'roles: other roles still allowed on Hindi' );
aw_t( array( 0, '' ) === aw_kw_roles_check( AW_KW_ROLE_INCLUDE, 0, AW_KW_ROLE_INCLUDE, 'hi' ), 'roles: a Sourcing role can always be removed' );
aw_t( '' !== aw_kw_roles_check( AW_KW_ROLE_INCLUDE, AW_KW_ROLE_EXCLUDE, 0, 'en' )[1], 'roles: Include + Exclude refused' );
aw_t( array( AW_KW_ROLE_EXCLUDE, '' ) === aw_kw_roles_check( AW_KW_ROLE_INCLUDE, AW_KW_ROLE_EXCLUDE, AW_KW_ROLE_INCLUDE, 'en' ), 'roles: Include moved to Exclude' );
aw_t( 'gaushala' === aw_si_norm( 'Gaushalas' ) && 'dogs' === aw_si_norm( 'dogs' ) && 'gsr 864' === aw_si_norm( 'G.S.R. 864' ) && 'animalwrite' === aw_si_norm( '#animalwrites' ) && 'rabies' === aw_si_norm( 'rabies' ), 'aw_si_norm folds plurals and punctuation' );
aw_t( 'hi' === aw_si_lang( 'पशु' ) && 'hinglish' === aw_si_lang( 'kutta bite' ) && 'en' === aw_si_lang( 'stray dog' ), 'aw_si_lang' );
aw_t( array( 'include', 'search' ) === aw_kw_roles_to_slugs( AW_KW_ROLE_INCLUDE | AW_KW_ROLE_SEARCH | AW_SI_ROLE_BRAND ), 'role slugs for the workers' );
aw_t( array( 'a', 'b*' ) === aw_kw_variants_list( "a\n\n A \nb*\r\n" ), 'other spellings: one per line, no repeats' );

// The migration plan on the live lists and records of 25 Oct 2026.
$p2   = json_decode( file_get_contents( $fx . 'phase2_live_sample.json' ), true );
$rows = array();
foreach ( $p2['rows'] as $r ) {
	$rows[] = $r + array( 'variants' => array(), 'src' => array(), 'categories' => array(), 'sections' => array(), 'query' => '', 'feed_id' => '', 'search_on' => 1, 'dismissed' => 0 );
}
$rel  = $p2['relevance'];
$cfg  = $p2['sources_option'];
$plan = aw_kw_phase2_plan( $rel, $cfg, $p2['dismissed'], $rows );
$L    = $plan['lists'];
aw_t( 26 === $L['strong']['items'] && 26 === $L['strong']['distinct'] && 26 === $L['strong']['existing'], 'Include: 26 words, all already records' );
aw_t( 57 === $L['animal']['items'] && 45 === $L['animal']['distinct'] && 12 === count( $L['animal']['merged'] ), 'Animal: 57 words fold to 45 (12 plural pairs)' );
aw_t( 55 === $L['context']['items'] && 54 === $L['context']['distinct'] && array( 'imports / import' ) === $L['context']['merged'], 'Context: 55 → 54 (imports / import)' );
aw_t( 29 === $L['exclude']['items'] && 28 === $L['exclude']['distinct'] && 0 === $L['exclude']['existing'], 'Exclude: 29 → 28, none in the table yet' );
aw_t( 11 === $L['press']['items'] && 10 === $L['press']['distinct'] && array( 'AnimalWrites / #animalwrites' ) === $L['press']['merged'], 'Press: 11 queries → 10 records' );
aw_t( 2 === $L['lab']['items'], 'two lab words that no category had (G.S.R. 864, Palamur)' );
aw_t( 164 === $plan['new'] && 73 === $plan['existing'], 'records: 164 new, 73 existing (' . $plan['new'] . ', ' . $plan['existing'] . ')' );
aw_t( 115 === $plan['cat_maps'], 'category mappings: 113 + the 2 lab words (' . $plan['cat_maps'] . ')' );
aw_t( array() === $plan['conflicts'], 'no Include + Exclude conflicts' );
aw_t( 10 === count( $plan['hindi'] ), '10 Hindi spellings lose the Sourcing role (decision 13)' );
aw_t( array( 'gnews-animal-welfare', 'gnews-animal-cruelty', 'gnews-lab-animal-rules', 'gnews-kw-stray-dogs' ) === array_keys( $plan['searches'] ), 'searches: the 3 open-web feeds and "stray dogs" keep their feed ids' );
aw_t( 7 === count( $plan['checks'] ) && array( 'include' ) === $plan['checks']['livelaw-top']['roles'] && array( 'animal-experimentation' ) === $plan['checks']['peta-india']['categories'], 'keyword checks: 4 on Include, 3 on Animal Experimentation (decisions 4, 5)' );
$hindi_ok = true;
$kept     = true;
foreach ( $plan['records'] as $r ) {
	if ( 'en' !== $r['lang'] && ( $r['roles'] & AW_KW_WORKER_ROLES ) ) {
		$hindi_ok = false;
	}
	if ( $r['id'] && ( ( $r['roles_was'] & ~AW_KW_WORKER_ROLES ) & ~$r['roles'] ) ) {
		$kept = false; // a merge never removes a role (only Sourcing roles from non-English records)
	}
}
aw_t( $hindi_ok, 'no record that is not English has a Sourcing or Press role' );
aw_t( $kept, 'roles add up: no other role removed' );
$every = true;
foreach ( array( 'strong', 'animal', 'context', 'exclude' ) as $k ) {
	foreach ( $rel[ $k ] as $w ) {
		$r     = $plan['records'][ aw_si_norm( $w ) ] ?? null;
		$every = $every && $r && in_array( mb_strtolower( $w ), array_map( 'mb_strtolower', aw_kw_spellings( $r ) ), true );
	}
}
aw_t( $every, 'every original spelling is kept on its record' );
aw_t( (bool) ( $plan['records']['stray dogs']['roles'] & AW_KW_ROLE_SEARCH ) && 'gnews-kw-stray-dogs' === $plan['records']['stray dogs']['feed_id'], '"stray dogs" is a Search keyword with its feed id' );
aw_t( array( 'aw_press' ) === $plan['records'][ aw_si_norm( 'lab animal rules' ) ]['sections'], 'the lab search files as press' );
aw_t( array( 'courts-judgments', 'campaigns-civic-action' ) === $plan['records']['petition']['categories'], '"petition" is mapped to 2 categories' );
aw_t( 1 === $plan['records'][ aw_si_norm( 'yes' ) ]['dismissed'], 'dismissed suggestions are a column on the record' );

// Ids as the migration gives them; the mirrors equal the old lists apart from the planned Hindi and lab changes.
$i    = 900000;
$recs = array();
foreach ( $plan['records'] as $r ) {
	$r['id'] = $r['id'] ? $r['id'] : ++$i;
	$recs[]  = $r;
}
$old             = array( 'strong' => $rel['strong'], 'animal' => $rel['animal'], 'context' => $rel['context'], 'exclude' => $rel['exclude'], 'press' => $cfg['press']['queries'], 'categories' => $cfg['categories'] );
$mir             = aw_kw_mirrors( $recs );
$mir['categories'] = aw_kw_categories_in_order( $mir['categories'], $cfg['categories'] );
$cmp             = aw_kw_compare_mirrors( $old, $mir, $plan['hindi'] );
aw_t( array() === $cmp['diffs'], 'mirrors: the old lists rebuilt exactly (' . implode( '; ', $cmp['diffs'] ) . ')' );
aw_t( 6 === count( $cmp['planned'] ), 'mirrors: 5 lists lose Hindi words, the lab category gains 2 words' );
aw_t( $mir['press'] === $cfg['press']['queries'], 'press queries rebuilt in order' );
aw_t( array_keys( $mir['categories'] ) === array_keys( $cfg['categories'] ), 'categories in the old order' );
$bad   = $recs;
$bad[] = array( 'id' => 1, 'label' => 'new word', 'roles' => AW_KW_ROLE_INCLUDE, 'src' => array(), 'categories' => array() );
aw_t( array( 'new word' ) === array_slice( aw_kw_mirrors( $bad )['strong'], -1 ), 'a keyword given Include later joins the mirror at the end' );
aw_t( (bool) aw_kw_compare_mirrors( $old, aw_kw_mirrors( $bad ), $plan['hindi'] )['diffs'], 'verify finds an unplanned change in a mirror' );

// The workers' keywords: Sourcing and Press roles, English only; the same searches and press queries as before.
$pay = aw_kw_payload( $recs, $cfg['sources'] );
aw_t( 225 === count( $pay ), 'keywords for the workers: 225 (' . count( $pay ) . ')' );
aw_t( ! array_filter( $pay, fn( $k ) => 'en' !== $k['lang'] || ! $k['roles'] ), 'only English keywords with a Sourcing or Press role' );
$on   = array_values( array_filter( $cfg['sources'], fn( $s ) => ! empty( $s['on'] ) ) );
$open = aw_kw_open_searches( $cfg['sources'] );
$v1   = aw_kw_v1_searches( $on, $rel['strong'] );
$v2   = aw_kw_v2_searches( array_values( array_filter( $on, fn( $s ) => ! isset( $open[ $s['id'] ] ) ) ), $pay );
ksort( $v1 );
ksort( $v2 );
aw_t( 30 === count( $v1 ) && $v1 === $v2, 'acceptance: the same 30 Google News searches (feed id and query) from the keywords' );
$q = array();
foreach ( $pay as $k ) {
	foreach ( (array) ( $k['queries'] ?? array() ) as $x ) {
		$q[ mb_strtolower( $x ) ] = $x;
	}
}
aw_t( array_values( $q ) === $cfg['press']['queries'], 'press queries from the keywords: the same 11' );
$sd = array_values( array_filter( $pay, fn( $k ) => 'animal welfare' === $k['label'] ) )[0];
aw_t( 'gnews-animal-welfare' === $sd['searches'][0]['feed'] && isset( $sd['searches'][0]['url'] ) && 'gnews-kw-animal-welfare' === $sd['searches'][1]['feed'], 'a former feed comes first, with its URL' );

// The sources after the migration: checks stored; the policy's phase 2 blocks.
$kwid    = array( 'gnews-animal-welfare' => 11, 'gnews-animal-cruelty' => 12, 'gnews-lab-animal-rules' => 13 );
$srcs    = aw_kw_apply_to_sources( $cfg['sources'], $plan['checks'], $kwid );
$blocks  = aw_kw_policy_blocks( $srcs, $recs, array( 'awbi.gov.in' ) );
$v2ids   = array_column( $blocks['sources_v2'], null, 'id' );
aw_t( 16 === count( $blocks['sources_v2'] ) && ! isset( $v2ids['gnews-animal-welfare'] ), 'sources_v2: the 16 feeds that are on, without the 3 that are keywords now' );
aw_t( array( 'include' ) === $v2ids['the-hindu-environment']['check']['roles'] && array( 'animal-experimentation' ) === $v2ids['pfa-substack']['check']['categories'], 'sources_v2 carries the stored keyword checks' );
aw_t( 'word' === $blocks['match'] && 2 === $blocks['schema']['keywords'] && 225 === count( $blocks['keywords_v2'] ), 'match "word", schema keywords 2' );
$lab = array_values( array_filter( $blocks['keywords_v2'], fn( $k ) => 'lab animal rules' === $k['label'] ) )[0]['searches'][0];
aw_t( 'press' === $lab['file_as'] && array( 'animal-experimentation' ) === $lab['check']['categories'], 'the lab search keeps its filing and its check' );

// Running the plan again on the migrated records changes nothing (idempotent).
$again = aw_kw_phase2_plan( $rel, $cfg, $p2['dismissed'], $recs );
$same  = 0 === $again['new'];
foreach ( $again['records'] as $n => $r ) {
	$same = $same && $r['roles'] === $plan['records'][ $n ]['roles'] && $r['variants'] === $plan['records'][ $n ]['variants'] && $r['categories'] === $plan['records'][ $n ]['categories'];
}
aw_t( $same, 'running the migration twice changes nothing' );

// The whole-word report and the * spellings (decision 6).
$texts = array( 'Sterilisation drive for street dogs', 'Mass sterilisations planned', 'Minister and administers', 'Dogs adopted', 'Petitions filed' );
$rep   = aw_kw_word_report( array( 'sterilis', 'minister', 'adopt', 'petition', 'पशु' ), $texts );
aw_t( array( 2, 0, 2, true ) === $rep['sterilis'], 'sterilis: 2 → 0 whole words, 2 with *: * added' );
aw_t( array( 1, 1, 1, false ) === $rep['minister'] || ! $rep['minister'][3], 'minister: no * when it wins nothing back' );
aw_t( $rep['petition'][0] === $rep['petition'][1] && ! $rep['petition'][3], 'petition: plural allowed, no change' );
aw_t( ! $rep['पशु'][3], 'Hindi words get no *' );
$starred = aw_kw_plan_add_stars( $plan, array( 'sterilis' => array( 78, 0, 78, true ) ) );
aw_t( in_array( 'sterilis*', $starred['records']['sterili']['variants'] ?? $starred['records'][ aw_si_norm( 'sterilis' ) ]['variants'], true ), 'the * spelling is added to the record' );
aw_t( aw_kw_legacy_match( 'a fir filed', 'fir' ) && ! aw_kw_legacy_match( 'first', 'fir' ) && aw_kw_legacy_match( 'sterilisation', 'sterilis' ), 'today\'s category matching (short words whole, others substring)' );

// Rollback order (Codex, PR #75): phase 1 waits while a phase 2 migration (complete or partial) is recorded; a phase 2
// rollback puts the marker back to the phase before it and never raises it.
aw_t( '' !== aw_src_rollback_refusal( 1, 1, true ), 'rollback --phase=1 refused while a partial phase 2 is recorded' );
aw_t( '' !== aw_src_rollback_refusal( 1, 2, true ) && '' !== aw_src_rollback_refusal( 1, 2, false ), 'rollback --phase=1 refused while phase 2 is migrated' );
aw_t( '' === aw_src_rollback_refusal( 1, 1, false ), 'rollback --phase=1 allowed with no phase 2' );
aw_t( '' === aw_src_rollback_refusal( 2, 1, true ) && '' === aw_src_rollback_refusal( 2, 2, true ) && '' !== aw_src_rollback_refusal( 2, 1, false ), 'rollback --phase=2: a partial or complete record, else refused' );
aw_t( 1 === aw_src_phase_after_p2_rollback( 1, 2, false ) && 1 === aw_src_phase_after_p2_rollback( 1, 1, true ) && 0 === aw_src_phase_after_p2_rollback( 1, 0, true ), 'the phase marker goes back to the phase before 2, never up' );

// "Delete Search Insights data" resets Search Insights' columns on the records it keeps (Codex, PR #75), never the owner's.
$reset = aw_si_reset_sql( AW_KW_OWNER_ROLES );
foreach ( array( 'group_id = id', 'page_type', 'intent', 'priority_override', 'entity = 0', "note = ''", 'impr28 = 0', 'roles = roles & ' . AW_KW_OWNER_ROLES ) as $x ) {
	aw_t( false !== strpos( $reset, $x ), "Search Insights reset sets $x" );
}
foreach ( array( 'label', 'norm', 'lang', 'variants', 'query', 'feed_id', 'search_on', 'dismissed', 'src_note' ) as $x ) {
	aw_t( ! preg_match( '/(^|, )' . $x . ' =/', $reset ), "Search Insights reset keeps the owner's $x" );
}

// Spellings added on the Keywords screen are searched too (Codex, #76); a "word*" prefix is not a search.
$kw_rec = array( 'search_on' => 1, 'lang' => 'en', 'roles' => AW_KW_ROLE_INCLUDE, 'label' => 'animal cruelty', 'query' => '', 'feed_id' => '', 'src' => array( 'lists' => array( 'strong' => array( array( 0, 'animal cruelty' ) ) ), 'added' => array( 'pashu kroorta', 'cruel*' ) ) );
$kw_q   = array_map( fn( $s ) => $s['q'] ?? '', aw_kw_searches( $kw_rec, array() ) );
aw_t( in_array( 'animal cruelty', $kw_q, true ) && in_array( 'pashu kroorta', $kw_q, true ) && ! in_array( 'cruel*', $kw_q, true ), 'added Include spellings are searched, prefixes are not' );
$kw_rec['src'] = array( 'lists' => array( 'strong' => array( array( 0, 'animal cruelty' ) ) ) );
aw_t( 1 === count( aw_kw_searches( $kw_rec, array() ) ), 'migrated spellings only: the search list is unchanged' );

// The old role sync pauses while a phase 2 migration runs or is unfinished (Codex, #75).
$si_kw = (string) file_get_contents( dirname( __DIR__, 2 ) . '/aw-seo-extras/includes/search-insights/keywords.php' );
$p2_mg = (string) file_get_contents( dirname( __DIR__ ) . '/includes/sourcing-migrate-phase2.php' );
aw_t( false !== strpos( $si_kw, "get_option( 'aw_sourcing_migrating' )" ) && false !== strpos( $si_kw, "get_option( 'aw_sourcing_phase2_record' )" ), 'role sync checks the migration flag and record' );
aw_t( substr_count( $p2_mg, "'aw_sourcing_migrating'" ) >= 3, 'migration sets the flag and clears it when done or rolled back' );

// Interrupted runs can roll back: the undo record is saved before rows change; switched-off searches stay off (Codex, #75).
$p2_src = (string) file_get_contents( dirname( __DIR__ ) . '/includes/sourcing-migrate-phase2.php' );
aw_t( substr_count( $p2_src, '$save();' ) >= 5, 'phase 2 saves its undo record before each change' );
$kwm_src = (string) file_get_contents( dirname( __DIR__ ) . '/includes/keywords-model.php' );
aw_t( false !== strpos( $kwm_src, "['search_on'] = array_key_exists( 'on'" ), 'a switched-off open-web search keeps search_on 0' );

// A search address with an upper-case scheme is still an address (Codex, #76).
$kw_up = array( 'search_on' => 1, 'lang' => 'en', 'roles' => AW_KW_ROLE_SEARCH, 'label' => 'x', 'query' => 'HTTPS://news.google.com/rss/search?q=x', 'feed_id' => 'gnews-x', 'src' => array() );
$kw_us = aw_kw_searches( $kw_up, array() );
aw_t( isset( $kw_us[0]['url'] ) && ! isset( $kw_us[0]['q'] ), 'an HTTPS:// search address is a feed URL' );

// ---- the Sourcing menu (owner, 25 Oct 2026: its own menu, Overview first, Plugins screen links, retired addresses gone) ----
$inc   = dirname( __DIR__ ) . '/includes/';
$menu  = (string) file_get_contents( $inc . 'admin-menu.php' );
aw_t( 'aw-sourcing-menu' === AW_SRC_MENU, 'the menu has its own slug' );
aw_t( ! in_array( AW_SRC_MENU, array( AW_SRC_PAGE, 'aw-review', 'aw-enrich-review', 'aw-inbox' ), true ), 'the menu slug is no screen\'s slug' );
aw_t( 'sourcing_page_' . AW_SRC_PAGE === AW_SRC_SCREEN, 'the page\'s screen id follows the menu title' );
aw_t( false !== strpos( $menu, "add_menu_page( 'Sourcing', 'Sourcing', AW_REVIEW_CAP, AW_SRC_MENU, 'aw_sourcing_overview_page'" ), 'the menu is registered with the plain title "Sourcing" (the screen id depends on it) and opens the Overview' );
aw_t( false !== strpos( $menu, "add_submenu_page( AW_SRC_MENU, 'Sourcing', 'Overview', AW_REVIEW_CAP, AW_SRC_MENU, 'aw_sourcing_overview_page' )" ), 'Overview is the first item, at the menu\'s slug' );
aw_t( false !== strpos( (string) file_get_contents( $inc . 'review-queue.php' ), "add_submenu_page( AW_SRC_MENU, 'To review'" ), 'To review is on the Sourcing menu' );
aw_t( false !== strpos( (string) file_get_contents( $inc . 'enrichment-admin.php' ), "add_submenu_page( AW_SRC_MENU, 'Enrichment review'" ), 'Enrichment review is on the Sourcing menu' );
// Phase 2 as on live, 25 Oct 2026 (kits_sourcing_tabs), plus the Relay log and Settings.
$tabs  = array( 'sources' => 'Sources', 'keywords' => 'Keywords', 'press' => 'Press queries', 'categories' => 'Categories', 'sections' => 'Sections', 'summary' => 'Summary rules', 'enrich' => 'Directory enrichment', 'relay-log' => 'Relay log', 'settings' => 'Settings', 'ai' => 'AI models', 'workers' => 'Workers', 'rejected' => 'Rejected', 'log' => 'Change log' );
aw_t( array( 'sources', 'keywords', 'press', 'categories', 'sections', 'summary', 'enrich', 'ai', 'workers', 'rejected', 'relay-log', 'log', 'settings' ) === array_keys( aw_src_menu_order( $tabs ) ), 'menu order: settings screens, then reports and jobs, then Settings' );
$p3 = array( 'sources' => 'Sources', 'keywords' => 'Keywords', 'rules' => 'Rules', 'press' => 'Press queries', 'log' => 'Change log', 'workers' => 'Workers', 'settings' => 'Settings' );
aw_t( array( 'sources', 'keywords', 'rules', 'press', 'workers', 'log', 'settings' ) === array_keys( aw_src_menu_order( $p3 ) ), 'phase 3: Rules after Keywords; a missing report is skipped' );
aw_t( 'admin.php?page=aw-sourcing' === aw_src_tab_path( 'sources' ) && 'admin.php?page=aw-sourcing' === aw_src_tab_path(), 'Sources is the page\'s own address' );
aw_t( 'admin.php?page=aw-sourcing&tab=workers' === aw_src_tab_path( 'workers' ) && 'admin.php?page=aw-sourcing&tab=relay-log' === aw_src_tab_menu_slug( 'relay-log' ), 'a tab\'s address and menu slug' );
aw_t( AW_SRC_PAGE === aw_src_tab_menu_slug( 'sources' ), 'the Sources item is the page itself' );
aw_t( array( 'Overview' => 'admin.php?page=aw-sourcing-menu', 'Settings' => 'admin.php?page=aw-sourcing&tab=settings' ) === aw_src_action_link_paths(), 'Plugins screen links: Overview, Settings' );
aw_t( false !== strpos( $menu, "'plugin_action_links_' . plugin_basename( AW_SOURCING_DIR . 'aw-sourcing.php' )" ), 'the links are added under AW Sourcing on the Plugins screen' );
// No screen is under Settings or Inbox any more, and no old screen id is used.
$all_inc = '';
foreach ( glob( $inc . '*.php' ) as $f ) {
	$all_inc .= (string) file_get_contents( $f );
}
aw_t( false === strpos( $all_inc, 'settings_page_aw-sourcing' ), 'no code uses the old screen id' );
// No function is declared twice across the plugin's files (the site would stop with a fatal error on every request).
preg_match_all( '/^function\s+([a-z0-9_]+)\s*\(/mi', $all_inc, $fn_m );
$fn_dup = array_keys( array_filter( array_count_values( array_map( 'strtolower', $fn_m[1] ) ), fn( $c ) => $c > 1 ) );
aw_t( array() === $fn_dup, 'every function is declared once (' . implode( ', ', $fn_dup ) . ')' );
aw_t( false !== strpos( $menu, "'aw_sourcing_own_settings_tab'" ) && false !== strpos( $menu, 'function aw_sourcing_own_settings_tab()' ), 'Sourcing → Settings draws with its own function' );
aw_t( false === strpos( $all_inc, "options-general.php?page=aw-sourcing" ) && false === strpos( $all_inc, 'add_options_page' ), 'no link or page under Settings' );
aw_t( false === strpos( $all_inc, "add_submenu_page( 'aw-inbox'" ) && false === strpos( $all_inc, 'edit.php?post_type=aw_listing&page=aw-enrich-review' ), 'nothing under Inbox or Directory' );
// Retired addresses (owner, 25 Oct 2026): the old tab redirects and the old POST tab names are gone; the phase switch stays.
$sites  = (string) file_get_contents( $inc . 'source-sites.php' );
$policy = (string) file_get_contents( $inc . 'sourcing-policy.php' );
$rules  = (string) file_get_contents( $inc . 'rules-admin.php' );
aw_t( false === strpos( $sites, "'publishers'    => array( 'tab' => 'sources' )" ) && false === strpos( $sites, ', 301 )' ), 'no 301 map for the old Publishers, Feeds, Library, Opportunities and Relay tabs' );
aw_t( false === strpos( $policy, "array( 'publishers' => 'sources', 'library' => 'feeds', 'opportunities' => 'feeds' )" ), 'no translation of old POST tab names' );
aw_t( false !== strpos( $rules, 'aw_rules_live()' ) && false !== strpos( $rules, "wp_safe_redirect( aw_rules_url( \$tab, array_map( 'rawurlencode', \$q ) ) ); // 302" ), 'the phase 3 switch (old tabs to Rules, 302) stays' );
aw_t( false !== strpos( $rules, "'admin.php' !== \$pagenow" ), 'the phase 3 switch works on the page\'s new address' );

require __DIR__ . '/rules-tests.php'; // phase 3 (Rules)
require __DIR__ . '/retire-tests.php'; // phase 4 (Retire)

echo ( $fails ? "FAILED: $fails of $count\n" : "OK: $count checks\n" );
exit( $fails ? 1 : 0 );
