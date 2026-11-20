<?php
/**
 * InsightWeb tests that need no WordPress, for the Dashboard and Settings:
 *
 * 1. The numbers: an unchanged Save writes nothing (option absent, or holding values in any order, beside RankWeb's
 *    own keys); a changed number is stored; a number back at its default is taken out; Restore defaults; a field not
 *    sent keeps its value.
 * 2. GDELT and the seeds: an unchanged Save writes nothing; a change is written once.
 * 3. The Dashboard shaper: six tiles, a change only against a fully stored window before, click rate and position
 *    worked out from the stored sums, page addresses only for site paths.
 * 4. The health shaper: the import tile and the jobs tile with their tones.
 * 5. The Overview boxes as plain parts: paragraphs, notes, lists with links, tables, notices; no markup or script
 *    goes out, and only http(s) or relative links are kept.
 * 6. Index status: the URL Inspection queue's not-reached line and estimate, and the Search Console totals.
 * 7. Rising: the terms a run dropped and the filter that dropped each.
 * 8. Links import: Search Console's Links exports (top linking sites, top linked pages, latest links) and a list
 *    with DR, validated row by row.
 *
 * Usage: php plugins/insightweb/tests/run.php
 *
 * @package insightweb
 */

if ( ! defined( 'ABSPATH' ) && 'cli' === PHP_SAPI ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput, WordPress.PHP.DiscouragedPHPFunctions, WordPress.WP.GlobalVariablesOverride, WordPress.DateTime.RestrictedFunctions, Squiz.Commenting.FunctionComment.MissingParamTag, Universal.Files.SeparateFunctionsFromOO -- a command-line test runner with WordPress stubs.
const DAY_IN_SECONDS    = 86400;
const MINUTE_IN_SECONDS = 60;

$GLOBALS['iw_options'] = array();
$GLOBALS['iw_writes']  = 0;

/** Stub: options in memory. */
function get_option( $name, $fallback = false ) {
	return array_key_exists( $name, $GLOBALS['iw_options'] ) ? $GLOBALS['iw_options'][ $name ] : $fallback;
}

/** Stub: counts writes. */
function update_option( $name, $value, $autoload = null ) {
	unset( $autoload );
	++$GLOBALS['iw_writes'];
	$GLOBALS['iw_options'][ $name ] = $value;
	return true;
}

/** JSON for the checks. */
function wp_json_encode_stub( $v ) {
	return json_encode( $v );
}

/** Stubs: hooks are not run here. */
function add_action() {}
/** Stub. */
function add_filter() {}
/** Stub: default passthrough (no filters registered in the test). */
function apply_filters( $hook, $value = null ) {
	return $value;
}
/** Stub. */
function __( $s ) {
	return $s;
}
/** Stub. */
function _n( $one, $many, $n ) {
	return 1 === (int) $n ? $one : $many;
}
/** Stub. */
function number_format_i18n( $n, $d = 0 ) {
	return number_format( (float) $n, $d );
}
/** Stub. */
function wp_date( $f, $t ) {
	return gmdate( $f, (int) $t );
}
/** Stub. */
function home_url( $p = '' ) {
	return 'https://example.invalid' . $p;
}
/** Stub. */
function wp_list_pluck( $items, $key ) {
	return array_map( fn( $x ) => $x[ $key ], $items );
}

require dirname( __DIR__ ) . '/includes/settings.php';
require dirname( __DIR__ ) . '/includes/insightweb-rest.php';

/** Stubs for the moved Search Insights functions the saves use (same bodies). */
function aw_si_gdelt_on(): bool {
	return (bool) get_option( 'aw_si_gdelt_on', 0 );
}
/** Same as rising.php. */
function aw_si_gdelt_save( bool $on ): bool {
	if ( aw_si_gdelt_on() === $on ) {
		return false;
	}
	update_option( 'aw_si_gdelt_on', $on ? 1 : 0, false );
	return true;
}

$GLOBALS['iw_fail'] = 0;

/** One check. */
function iw_check( bool $ok, string $what ): void {
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $what . "\n";
	if ( ! $ok ) {
		$GLOBALS['iw_fail'] = 1;
	}
}

/** The numbers as the screen shows them (text per field). */
function iw_texts(): array {
	$out = array();
	foreach ( insightweb_numbers_group( aw_si_settings_saved() )['blocks'] as $b ) {
		foreach ( $b['fields'] as $f ) {
			$out[ $f['const'] ] = $f['text'];
		}
	}
	return $out;
}

// 1. Numbers.
$w = $GLOBALS['iw_writes'];
iw_check( false === aw_si_settings_save( iw_texts() ) && $GLOBALS['iw_writes'] === $w && ! array_key_exists( 'aw_seo_settings', $GLOBALS['iw_options'] ), 'unchanged Save with nothing stored writes nothing' );
$GLOBALS['iw_options']['aw_seo_settings'] = array(
	'titles'          => array( 'AW_SEO_X' => 3 ),
	'search-insights' => array(
		'on'           => 1,
		'AW_SI_MOVERS' => 30, // A real (non-default) value; only these are stored.
	),
);
$before                                   = serialize( $GLOBALS['iw_options']['aw_seo_settings'] );
$w                                        = $GLOBALS['iw_writes'];
iw_check( false === aw_si_settings_save( iw_texts() ) && serialize( $GLOBALS['iw_options']['aw_seo_settings'] ) === $before && $GLOBALS['iw_writes'] === $w, 'unchanged Save with values stored writes nothing (byte for byte)' );
// A value stored though equal to its default (a stale key) is taken out on the next Save, so a later default change is
// followed (docs/pending.md, fixed b3cd3a0d). The Save writes once; the other stored key is kept.
$GLOBALS['iw_options']['aw_seo_settings']['search-insights']['AW_SI_HIGH_IMPR'] = 50; // Equal to the default.
$w = $GLOBALS['iw_writes'];
iw_check( true === aw_si_settings_save( iw_texts() ) && ! isset( $GLOBALS['iw_options']['aw_seo_settings']['search-insights']['AW_SI_HIGH_IMPR'] ) && 30 === $GLOBALS['iw_options']['aw_seo_settings']['search-insights']['AW_SI_MOVERS'] && $GLOBALS['iw_writes'] === $w + 1, 'a stored value equal to the default is taken out on the next Save (follows a later default change)' );
iw_check( false === aw_si_settings_save( array() ), 'a Save sending nothing writes nothing' );
iw_check( aw_si_settings_save( array( 'AW_SI_QUICK_MIN' => '15' ) ) && 15 === $GLOBALS['iw_options']['aw_seo_settings']['search-insights']['AW_SI_QUICK_MIN'] && 30 === $GLOBALS['iw_options']['aw_seo_settings']['search-insights']['AW_SI_MOVERS'], 'a changed number is stored; one not sent keeps its value' );
iw_check( aw_si_settings_save( array( 'AW_SI_MOVERS' => '20' ) ) && ! isset( $GLOBALS['iw_options']['aw_seo_settings']['search-insights']['AW_SI_MOVERS'] ), 'a number set back to its default is taken out' );
iw_check( 3 === $GLOBALS['iw_options']['aw_seo_settings']['titles']['AW_SEO_X'], "RankWeb's own keys are untouched" );
aw_si_settings_save( array(), true );
iw_check( array( 'on' => 1 ) === $GLOBALS['iw_options']['aw_seo_settings']['search-insights'], "Restore defaults keeps only RankWeb's old switch" );
iw_check( '90' === iw_texts()['AW_SI_KEEP_DAYS'], 'the screen shows the default as text' );

// 2. GDELT.
$w = $GLOBALS['iw_writes'];
iw_check( false === aw_si_gdelt_save( false ) && $GLOBALS['iw_writes'] === $w, 'GDELT: unchanged Save writes nothing' );
iw_check( aw_si_gdelt_save( true ) && false === aw_si_gdelt_save( true ), 'GDELT: a change is written once' );

// 3. Dashboard shaper.
$range = array(
	'days'       => 7,
	'stored'     => 7,
	'prevStored' => 7,
	'clicks'     => 110,
	'impr'       => 2000,
	'pos'        => 12.4,
	'prevClicks' => 100,
	'prevImpr'   => 2000,
	'prevPos'    => 13.0,
	'pages'      => 40,
	'queries'    => 300,
	'prevPages'  => 40,
	'prevQuery'  => 250,
	'chart'      => array(
		'2026-11-01' => 10,
		'2026-11-02' => 20,
	),
	'topPages'   => array( array( '/news/x/', 30, 400, 4.2 ), array( 'https://other.invalid/', 1, 2, null ) ),
	'topQueries' => array( array( 'stray dogs', 12, 100, 6.0 ) ),
);
$s     = insightweb_dash_shape(
	$range,
	array(
		'pages'    => 'P',
		'keywords' => 'K',
	)
);
$t     = array_column( $s['tiles'], null, 'key' );
iw_check( array( 'clicks', 'impr', 'ctr', 'pos', 'pages', 'queries' ) === array_keys( $t ), 'six tiles in order' );
iw_check( '+10% on the 7 days before' === $t['clicks']['delta'] && '0% on the 7 days before' === $t['impr']['delta'], 'changes against the 7 days before' );
iw_check( '5.5%' === $t['ctr']['value'], 'click rate from the sums' );
iw_check( '-0.6 on the 7 days before' === $t['pos']['delta'], 'position change as a difference' );
iw_check( 'https://example.invalid/news/x/' === $s['topPages'][0]['url'] && '' === $s['topPages'][1]['url'] && '–' === $s['topPages'][1]['pos'], 'page addresses only for site paths' );
iw_check( 2 === count( $s['chart'] ) && 20 === $s['chart'][1]['value'], 'clicks per day' );
$range['prevStored'] = 3;
$s2                  = insightweb_dash_shape( $range, array() );
iw_check( '' === $s2['tiles'][0]['delta'] && false === $s2['compared'], 'no change against a window not fully stored' );
$range['stored'] = 4;
iw_check( '4 of 7 days stored so far' === insightweb_dash_shape( $range, array() )['tiles'][0]['sub'], 'days stored so far' );

// 4. Health shaper.
$h = insightweb_health_shape(
	array(
		array(
			'name'    => 'GA4',
			'last'    => 0,
			'status'  => 'failed',
			'problem' => true,
			'why'     => 'HTTP 403',
		),
		array(
			'name'    => 'Search Console',
			'last'    => 1762800000,
			'status'  => 'ok',
			'problem' => false,
		),
	),
	array(
		'ok'   => 1762800000,
		'info' => array( 'up_to' => '2026-11-08' ),
	),
	'ok',
	array()
);
iw_check( 'Figures up to 8 Nov 2026' === $h['tiles'][0]['sub'] && '' === $h['tiles'][0]['tone'], 'import tile: date of the figures, no tone when working' );
iw_check( 'error' === $h['tiles'][1]['tone'] && '1 needs attention' === $h['tiles'][1]['value'] && 'GA4' === $h['tiles'][1]['sub'], 'jobs tile names the failed source' );
iw_check( 'Failed' === $h['sources'][0]['status'] && 'Never' === $h['sources'][0]['last'], 'sources list in plain words' );
iw_check( 'HTTP 403' === $h['sources'][0]['why'] && '' === $h['sources'][1]['why'], 'a failed source says why' );

// 5. The Overview boxes as plain parts.
if ( ! function_exists( 'esc_url_raw' ) ) {
	/** Stub. */
	function esc_url_raw( $u ) {
		return (string) $u;
	}
}
require dirname( __DIR__, 2 ) . '/kits-core/includes/admin-shell-parts.php'; // the page parts the boxes use
require dirname( __DIR__ ) . '/includes/insightweb-boxes.php';
$b     = insightweb_html_blocks(
	'<p>Hello <strong>there</strong></p><p class="description">A note</p><ul><li><a href="https://x.test/k">P1</a>: 3</li><li><a href="javascript:alert(1)">bad</a></li></ul>'
	. '<table class="widefat striped"><thead><tr><th scope="col">Keyword</th><th scope="col">Page</th></tr></thead><tbody><tr><td>dog</td><td><a href="/news/x/" target="_blank">/news/x/</a></td></tr></tbody></table>'
	. '<div class="notice notice-warning inline"><p>Careful</p></div><form><input name="x"></form><script>alert(1)</script><details><summary>More</summary><p>Inside</p></details>'
);
$types = array_column( $b, 'type' );
iw_check( array( 'p', 'note', 'list', 'table', 'notice', 'heading', 'p' ) === $types, 'box parts in order: ' . implode( ', ', $types ) );
iw_check( 'Hello there' === $b[0]['text'] && 'A note' === $b[1]['text'], 'paragraph and note as text' );
iw_check( 'https://x.test/k' === $b[2]['items'][0]['href'] && 'P1: 3' === $b[2]['items'][0]['text'] && ! isset( $b[2]['items'][1]['href'] ), 'list links kept for http(s) only' );
iw_check( array( 'Keyword', 'Page' ) === $b[3]['head'] && '/news/x/' === $b[3]['rows'][0][1]['href'] && true === $b[3]['rows'][0][1]['external'], 'table head, rows and a relative link' );
iw_check( 'warn' === $b[4]['tone'] && 'Careful' === $b[4]['text'], 'notice tone and text' );
iw_check( false === strpos( wp_json_encode_stub( $b ), 'alert' ) && false === strpos( wp_json_encode_stub( $b ), '<' ), 'no script, form or markup goes out' );
iw_check( array() === insightweb_html_blocks( '   ' ), 'an empty box has no parts' );

// 6. Index status (12 Nov 2026): the URL Inspection queue's info, the estimate, and the Search Console totals.
require dirname( __DIR__ ) . '/includes/search-insights/gsc-types.php';
$qi = aw_si_inspect_info(
	array(
		'd'       => '2026-11-11',
		'todo'    => array( '/a/', '/b/', '/c/' ),
		'never'   => 2500,
		'done'    => 1000,
		'indexed' => 300,
		'errors'  => array(),
		'refused' => '',
	)
);
iw_check( 3 === $qi['left'] && 1500 === $qi['never_left'] && 1000 === $qi['rows'] && 300 === $qi['indexed'], 'inspect info: pages left, never-inspected left, done' );
iw_check( 0 === strpos( $qi['warnings'][0], '3 pages not reached that day: the time budget' ), 'not reached: says the time budget when Google did not refuse' );
$qr = aw_si_inspect_info(
	array(
		'd'       => '2026-11-11',
		'todo'    => array( '/a/' ),
		'done'    => 5,
		'indexed' => 1,
		'errors'  => array(),
		'refused' => 'Google refused URL Inspection (HTTP 429), so it stops for today.',
		'quota'   => true,
	)
);
iw_check( false !== strpos( $qr['warnings'][0], 'its quota' ) && 2 === count( $qr['warnings'] ) && ! isset( $qr['never_left'] ), 'not reached: says the quota when Google refused; no estimate without the never count' );
iw_check( '' === aw_si_inspect_info_eta( $qr ) && false !== strpos( aw_si_inspect_info_eta( $qi ), 'by about 13 Nov 2026' ), 'estimate from a run: none without the never count' );
$qe = aw_si_inspect_info(
	array(
		'todo'    => array( '/a/' ),
		'done'    => 2,
		'refused' => 'Site Kit is not connected.',
	)
);
iw_check( false !== strpos( $qe['warnings'][0], 'could not be reached' ) && false === strpos( $qe['warnings'][0], 'quota' ), 'not reached: another error is not called the quota' );
$ql = aw_si_inspect_queue_lines( $qi );
iw_check( 2 === count( $ql ) && $qi['warnings'][0] === $ql[0] && false !== strpos( $ql[1], 'by about 13 Nov 2026' ) && array() === aw_si_inspect_queue_lines( array( 'left' => 0 ) ), 'queue lines: the not-reached reason, then the estimate; none for a finished old run' );
iw_check( false !== strpos( $qi['warnings'][0], '15 s of calls per step, a step every 5 min' ) && false !== strpos( $qi['warnings'][0], '2,000-a-day' ), 'not reached: the budget, gap and cap come from the constants' );
iw_check(
	array() === aw_si_inspect_info(
		array(
			'todo' => array(),
			'done' => 4,
		)
	)['warnings'],
	'a finished queue has no not-reached line'
);
iw_check( false !== strpos( aw_si_inspect_eta( 1500, 1000, '2026-11-11' ), 'by about 13 Nov 2026' ), 'estimate: 1,500 pages at 1,000 a day end two days on' );
iw_check( 0 === strpos( aw_si_inspect_eta( 0, 1000, '2026-11-11' ), 'every page has been inspected' ) && '' === aw_si_inspect_eta( 10, 0, '2026-11-11' ), 'estimate: done, or nothing without a rate' );
iw_check(
	array( 4390, 4803, '2026-11-12', false ) === aw_si_coverage_totals(
		array(
			'indexed'     => 4390,
			'not_indexed' => 4803,
			'discovered'  => 10,
			'day'         => '2026-11-12',
		)
	),
	'Search Console totals: the typed Not indexed total wins'
);
iw_check(
	array( 10, 7, '', true ) === aw_si_coverage_totals(
		array(
			'indexed'    => 10,
			'discovered' => 3,
			'crawled'    => 4,
		)
	) && null === aw_si_coverage_totals( array( 'crawled' => 4 ) ),
	'Search Console totals: reasons added up when no total; none without Indexed'
);

// 7. Rising (12 Nov 2026): what a run dropped and why.
/** Stub. */
function sanitize_text_field( $s ) {
	return trim( strip_tags( (string) $s ) );
}
/** Stub. */
function sanitize_key( $s ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) );
}
require dirname( __DIR__ ) . '/includes/search-insights/rising-drops.php';
$wd = aw_si_rising_worker_dropped(
	array(
		array(
			'term'   => 'Tiger Shroff film',
			'filter' => 'exclude',
		),
		array( 'term' => '' ),
		'x',
		array( 'term' => 'ok' ),
	)
);
iw_check( array( array( 'Tiger Shroff film', 'worker:exclude' ) ) === $wd, 'worker drops: only rows with a term and a filter' );
iw_check(
	101 > count(
		aw_si_rising_worker_dropped(
			array_fill(
				0,
				500,
				array(
					'term'   => 't',
					'filter' => 'old',
				)
			)
		)
	),
	'worker drops: capped'
);
iw_check( array() === aw_si_rising_worker_dropped( 'nonsense' ), 'worker drops: anything but a list is ignored' );
$dc = aw_si_rising_drop_counts(
	array(
		'rising'  => 2,
		'worker'  => array(
			'trending' => '20',
			'kept'     => '3',
			'excluded' => '1',
		),
		'dropped' => array( array( 'चाय', 'not-english' ), array( 'x', 'worker:vocabulary' ) ),
	)
);
iw_check( 20 === $dc['seen'] && 17 === $dc['worker_dropped'] && 1 === $dc['excluded'] && 2 === $dc['sent'] && 1 === $dc['stored'] && 2 === count( $dc['terms'] ), 'drop counts: seen, dropped by the worker, excluded, sent and stored' );
iw_check( null === aw_si_rising_drop_counts( array( 'rising' => 0 ) )['seen'] && null === aw_si_rising_drop_counts( array() )['worker_dropped'], 'drop counts: unknown without the worker counts' );
iw_check( false !== strpos( aw_si_rising_drop_label( 'not-english' ), 'not English' ) && 'worker filter: odd' === aw_si_rising_drop_label( 'worker:odd' ), 'drop labels in plain words' );

// 8. Links import (12 Nov 2026): Search Console's Links exports and a pasted list with DR.
/** Stub. */
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}
require dirname( __DIR__ ) . '/includes/search-insights/imports.php';
require dirname( __DIR__ ) . '/includes/search-insights/links-import.php';
$home = 'https://animalwrites.in/';
$src  = 'Search Console Links, read 12 Nov 2026';
$ls   = aw_si_links_import_parse( "\xEF\xBB\xBFSite,Linking pages,Target pages\nexample.org,\"1,204\",3\nwww.News.example.in,2,1\nnot a domain,1,1\nanimalwrites.in,9,9\n", $home, $src, '2026-11-12', '2026-11-12' );
iw_check( 'sites' === $ls['kind'] && 2 === count( $ls['rows'] ) && 2 === $ls['skipped'], 'top linking sites: two sites kept; a bad name and our own site skipped' );
iw_check( 'example.org' === $ls['rows'][0]['domain'] && 1204 === $ls['rows'][0]['links'] && $home === $ls['rows'][0]['linked_url'] && false !== strpos( $ls['rows'][0]['note'], '3 of our pages' ) && 'news.example.in' === $ls['rows'][1]['domain'], 'top linking sites: domain without www, links, home page with a note' );
iw_check( '2026-11-12' === $ls['rows'][0]['last_seen'] && $src === $ls['rows'][0]['source'], 'top linking sites: the day read and the source' );
$lp = aw_si_links_import_parse( "Target page,Incoming links,Linking sites\nhttps://animalwrites.in/campaigns/x/,40,7\nhttps://other.example/,5,1\n", $home, $src, '2026-11-12', '2026-11-12' );
iw_check( 'pages' === $lp['kind'] && array() === $lp['rows'] && array( array( 'https://animalwrites.in/campaigns/x/', 40, 7 ) ) === $lp['pages'] && 1 === $lp['skipped'], 'top linked pages: a summary of our pages, no link rows' );
$ll = aw_si_links_import_parse( "Linking page,Last crawled\nhttps://blog.example.com/post/,2026-11-02\nhttps://blog.example.com/b/,2027-01-01\n", $home, $src, '2026-11-12', '2026-11-12' );
iw_check( 'latest' === $ll['kind'] && '2026-11-02' === $ll['rows'][0]['last_seen'] && '2026-11-12' === $ll['rows'][1]['last_seen'] && 'https://blog.example.com/post/' === $ll['rows'][0]['from_url'], 'latest links: last crawled as last seen; a date in the future falls back to the day read' );
$lx = aw_si_links_import_parse( "Linking page\tLinked page\tAnchor\tDR\nhttps://a.example.org/x\thttps://animalwrites.in/about/\tAnimal Writes\t54\nhttps://b.example.org/\thttps://evil.example/\tx\t20\nhttps://c.example.org/\t\t\t140\n", $home, 'Pasted list, read 12 Nov 2026', '2026-11-12', '2026-11-12' );
iw_check( 'list' === $lx['kind'] && 2 === count( $lx['rows'] ) && 1 === $lx['skipped'] && 'https://animalwrites.in/about/' === $lx['rows'][0]['linked_url'] && 'Animal Writes' === $lx['rows'][0]['anchor'], 'list: tab separated; a linked page not ours is skipped' );
iw_check( array( 'a.example.org' => 54 ) === $lx['dr'], 'list: DR kept per domain, only 0–100' );
iw_check( '' !== aw_si_links_import_parse( "Keyword,Volume\ndog,10\n", $home, $src, '2026-11-12', '2026-11-12' )['error'] && '' !== aw_si_links_import_parse( 'just text', $home, $src, '2026-11-12', '2026-11-12' )['error'], 'unknown columns or no header: an error, nothing stored' );
iw_check( 'Search Console Links' === aw_si_link_source_key( 'Search Console Links, read 2 Nov 2026' ) && 'Bing Webmaster' === aw_si_link_source_key( 'Bing Webmaster' ), 'reads group by source without the read date' );
$l1 = aw_si_links_import_parse( "Site\nexample.org\nwww.xn--p1ai.xn--p1ai\nanimalwrites.in\n", $home, $src, '2026-11-12', '2026-11-12' );
iw_check( 'list' === $l1['kind'] && 2 === count( $l1['rows'] ) && 'xn--p1ai.xn--p1ai' === $l1['rows'][1]['domain'] && 1 === $l1['skipped'], 'one-column list; an IDN (punycode) domain is a domain' );
$lr = aw_si_links_import_parse( "\"Linking page\",\"Last crawled\"\r\n\"https://blog.example.com/post/\",\"Nov 2, 2026\"\r\n\"https://blog.example.com/post/\",\"Nov 2, 2026\"\r\n\"https://www.animalwrites.in/x/\",\"Nov 3, 2026\"\r\n", $home, $src, '2026-11-12', '2026-11-12' );
iw_check( 'latest' === $lr['kind'] && 2 === count( $lr['rows'] ) && '2026-11-02' === $lr['rows'][0]['last_seen'] && 1 === $lr['skipped'], 'a real Latest links export: quoted, CRLF, US dates; our own site with www skipped; a repeated row stays one link (same key in aw_si_link_put)' );
iw_check( '2026-11-02' === aw_si_links_import_date( '2 Nov 2026', '2026-11-12' ) && '' === aw_si_links_import_date( '1999-12-31', '2026-11-12' ) && '' === aw_si_links_import_date( 'soon', '2026-11-12' ), 'import dates: read, too old, not a date' );
iw_check( 'example.org' === aw_si_link_domain( 'https://WWW.Example.org/x' ) && '' === aw_si_links_import_host( 'not a domain' ), 'one domain rule for links and the import' );
$GLOBALS['iw_options']['aw_si_link_dr'] = array( 'a.example.org' => array( 60, '2026-11-20' ) );
$dr_in                                  = array(
	'rows'    => array(),
	'dr'      => array(
		'a.example.org' => 40,
		'b.example.org' => 10,
	),
	'pages'   => array(),
	'skipped' => 0,
	'kind'    => 'list',
	'error'   => '',
);
aw_si_links_import_store( $dr_in, 'Pasted list', '2026-11-12' );
iw_check( array( 60, '2026-11-20' ) === $GLOBALS['iw_options']['aw_si_link_dr']['a.example.org'] && array( 10, '2026-11-12' ) === $GLOBALS['iw_options']['aw_si_link_dr']['b.example.org'], 'DR: an older read never replaces a newer one' );


// Backup before uninstall deletes (includes/uninstall-backup.php): the lines, the 500-row batches, the folder guard.
require_once dirname( __DIR__ ) . '/includes/uninstall-backup.php';
$ub_t = static function ( bool $ok, string $what ): void {
	iw_check( $ok, $what );
};
$ub_h = json_decode( insightweb_backup_header( 'insightweb', '1.2.3', 'https://example.org/', 1763000000, array( 'a_opt', 'b_opt' ), array( 'wp_x' ) ), true );
$ub_t( 'header' === $ub_h['type'] && 'insightweb' === $ub_h['plugin'] && '1.2.3' === $ub_h['version'] && 'https://example.org/' === $ub_h['site'], 'backup: header names the plugin, version and site' );
$ub_t( '2025-11-13T02:13:20Z' === $ub_h['time'] && array( 'a_opt', 'b_opt' ) === $ub_h['options'] && array( 'wp_x' ) === $ub_h['tables'], 'backup: header has the time, the options and the tables' );
$ub_l = insightweb_backup_option_line( 'a_opt', 'a:1:{s:1:"k";s:3:"é/";}', 'yes' );
$ub_o = json_decode( $ub_l, true );
$ub_t( "\n" === substr( $ub_l, -1 ) && 1 === substr_count( $ub_l, "\n" ), 'backup: one line per option' );
$ub_t( 'option' === $ub_o['type'] && 'a:1:{s:1:"k";s:3:"é/";}' === $ub_o['value'] && 'yes' === $ub_o['autoload'] && ! isset( $ub_o['b64'] ), 'backup: option value kept exactly as stored' );
$ub_o = json_decode( insightweb_backup_option_line( 'bin', "\xff\x00\xfe", 'no' ), true );
$ub_t( array( 'value' ) === $ub_o['b64'] && "\xff\x00\xfe" === base64_decode( $ub_o['value'] ), 'backup: a value that is not UTF-8 is kept base64-encoded' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- the test reads the value back
$ub_r = json_decode(
	insightweb_backup_row_line(
		'wp_x',
		array(
			'id'   => '7',
			'note' => null,
			'blob' => "\x80",
		)
	),
	true
);
$ub_t( 'row' === $ub_r['type'] && 'wp_x' === $ub_r['table'] && '7' === $ub_r['row']['id'] && null === $ub_r['row']['note'] && array( 'blob' ) === $ub_r['b64'], 'backup: row line keeps every column, null and binary' );
$ub_t( '{}' === substr( trim( insightweb_backup_row_line( 'wp_x', array() ) ), -3, 2 ), 'backup: an empty row is an object' );
$ub_t( 500 === INSIGHTWEB_BACKUP_BATCH, 'backup: tables are read 500 rows at a time' );
$ub_calls = array();
$ub_seen  = 0;
$ub_fetch = static function ( int $offset, int $limit ) use ( &$ub_calls ) {
	$ub_calls[] = array( $offset, $limit );
	return array_fill( 0, max( 0, min( $limit, 1203 - $offset ) ), array( 'id' => '1' ) );
};
$ub_n     = insightweb_backup_rows(
	$ub_fetch,
	static function ( array $row ) use ( &$ub_seen ): bool {
		++$ub_seen;
		return true;
	}
);
$ub_t( 1203 === $ub_n && 1203 === $ub_seen && array( array( 0, 500 ), array( 500, 500 ), array( 1000, 500 ) ) === $ub_calls, 'backup: 1203 rows in batches of 500' );
$ub_calls = array();
$ub_n     = insightweb_backup_rows( static fn( int $o, int $l ) => $o < 1000 ? array_fill( 0, $l, array() ) : array(), static fn( array $r ): bool => true );
$ub_t( 1000 === $ub_n, 'backup: an exact multiple of 500 ends on the empty batch' );
$ub_t( false === insightweb_backup_rows( static fn( int $o, int $l ) => null, static fn( array $r ): bool => true ), 'backup: a read error stops the backup' );
$ub_w = 0;
$ub_n = insightweb_backup_rows(
	static fn( int $o, int $l ) => array_fill( 0, $l, array() ),
	static function ( array $r ) use ( &$ub_w ): bool {
		return ++$ub_w < 3;
	}
);
$ub_t( false === $ub_n && 3 === $ub_w, 'backup: a write error stops the backup at once' );
$ub_t( 1 === preg_match( '/^insightweb-uninstall-\d{8}-\d{6}\.jsonl\.gz$/', insightweb_backup_name( 'insightweb', 1763000000 ) ) && 'insightweb-uninstall-20251113-021320.jsonl.gz' === insightweb_backup_name( 'insightweb', 1763000000 ), 'backup: file name' );
$ub_dir = sys_get_temp_dir() . '/insightweb-ub-' . getmypid();
@mkdir( $ub_dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions -- a temporary folder for the test
file_put_contents( $ub_dir . '/index.php', 'kept' ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- the test's own file
$ub_t( insightweb_backup_protect( $ub_dir ) && false !== strpos( (string) file_get_contents( $ub_dir . '/.htaccess' ), 'Require all denied' ) && 'kept' === file_get_contents( $ub_dir . '/index.php' ), 'backup: folder guarded by .htaccess and index.php, an existing file kept' ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- the test's own files
$ub_gz = gzopen( $ub_dir . '/t.jsonl.gz', 'wb6' ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- the test's own file
gzwrite( $ub_gz, insightweb_backup_header( 'insightweb', '1', 's', 0, array( 'a' ), array() ) . insightweb_backup_option_line( 'a', 'v', 'yes' ) );
gzclose( $ub_gz );
$ub_lines = gzfile( $ub_dir . '/t.jsonl.gz' );
$ub_t( 2 === count( $ub_lines ) && 'header' === json_decode( $ub_lines[0], true )['type'] && 'v' === json_decode( $ub_lines[1], true )['value'], 'backup: gzip JSON lines read back' );
foreach ( array( 'index.php', '.htaccess', 't.jsonl.gz' ) as $ub_f ) {
	unlink( $ub_dir . '/' . $ub_f ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- the test's own files
}
rmdir( $ub_dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- the test's own folder
$ub_dir = sys_get_temp_dir() . '/insightweb-ub-ro-' . getmypid();
@mkdir( $ub_dir, 0555 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions -- a read-only folder for the test
$ub_t( 0 === getmyuid() || ! @insightweb_backup_protect( $ub_dir ), 'backup: a folder that cannot be written is refused' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- the warning is the expected failure
rmdir( $ub_dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- the test's own folder

// Keyword scoring, recommendations, focus-keyphrase reconciliation and the canonical-owner ladder (score.php).
require dirname( __DIR__ ) . '/includes/search-insights/compute.php'; // aw_si_is_news() for intent
require dirname( __DIR__ ) . '/includes/search-insights/score.php';

iw_check( 0 === aw_si_score_demand( 0, 0 ) && 100 === aw_si_score_demand( 5000, 0 ) && 46 === aw_si_score_demand( 50, 0 ), 'demand sub-score: 0 / full at the cap / log-scaled between' );
iw_check( 0 === aw_si_score_opportunity( null ) && 20 === aw_si_score_opportunity( 2.0 ) && 100 === aw_si_score_opportunity( 12.0 ) && 65 === aw_si_score_opportunity( 35.0 ) && 12 === aw_si_score_opportunity( 80.0 ), 'opportunity sub-score: 0 unranked, 20 top-3, 100 in 5-20, tapering past 20' );
iw_check( 0 === aw_si_score_engagement( 0, 0 ) && 100 === aw_si_score_engagement( 5, 50 ) && 20 === aw_si_score_engagement( 1, 50 ), 'engagement sub-score: CTR, 10% = full' );
iw_check( 100 === aw_si_score_momentum( 5.0, null ) && 50 === aw_si_score_momentum( 2.5, null ) && 50 === aw_si_score_momentum( null, 50 ) && 0 === aw_si_score_momentum( null, null ), 'momentum sub-score: growth first, then trends' );
$sc = aw_si_score(
	array(
		'impr'   => 5000,
		'clicks' => 500,
		'pos'    => 12.0,
		'growth' => 5.0,
	)
);
iw_check( 100 === $sc['score'] && 100 === $sc['demand'] && 100 === $sc['opportunity'], 'score: all signals at full = 100' );
$sc = aw_si_score(
	array(
		'impr'   => 50,
		'clicks' => 1,
		'pos'    => 80.0,
	)
);
iw_check( 23 === $sc['score'], 'score: weak keyword scores low (weighted blend)' );

iw_check(
	array( 'exclude' ) === aw_si_recommended_use(
		array(
			'roles' => 128,
			'score' => 90,
		)
	),
	'recommended-use: the Exclude role wins outright'
);
iw_check(
	array( 'focus-page' ) === aw_si_recommended_use(
		array(
			'score'    => 70,
			'pos'      => 12.0,
			'has_page' => true,
			'impr'     => 100,
		)
	),
	'recommended-use: owning page at a winnable position → focus keyphrase'
);
iw_check(
	array( 'write-page' ) === aw_si_recommended_use(
		array(
			'score'    => 70,
			'pos'      => null,
			'has_page' => false,
			'impr'     => 100,
		)
	),
	'recommended-use: demand, no page → write a new page'
);
iw_check(
	array( 'secondary' ) === aw_si_recommended_use(
		array(
			'score'    => 60,
			'pos'      => 2.0,
			'has_page' => true,
		)
	),
	'recommended-use: already top-3 → secondary keyphrase'
);
iw_check(
	array( 'focus-page', 'sourcing:library' ) === aw_si_recommended_use(
		array(
			'score'    => 70,
			'pos'      => 12.0,
			'has_page' => true,
			'impr'     => 100,
			'topic'    => 'Rabies',
		),
		array( 'rabies' => 'library' )
	),
	'recommended-use: a sourcing topic adds a sourcing candidate of that type'
);

iw_check( 'poor-rank' === aw_si_focus_health( true, false ) && 'should-be' === aw_si_focus_health( false, true ) && '' === aw_si_focus_health( true, true ) && '' === aw_si_focus_health( false, false ), 'focus health: in-A-not-B / in-B-not-A / agree' );
iw_check( aw_si_is_focus_candidate( 60, 12.0, true ) && ! aw_si_is_focus_candidate( 40, 12.0, true ) && ! aw_si_is_focus_candidate( 60, 2.0, true ) && ! aw_si_is_focus_candidate( 60, 12.0, false ), 'focus candidate: strong score + winnable position + a page' );

iw_check( 'evergreen' === aw_si_query_intent( 'rabies symptoms' ) && 'dated' === aw_si_query_intent( 'dog attack 2024' ) && 'local' === aw_si_query_intent( 'animal shelter near me' ) && 'campaign' === aw_si_query_intent( 'donate to animals' ), 'query intent: evergreen / dated / local / campaign' );
iw_check( 'library' === aw_si_page_class( '/x/', 'aw_legal' ) && 'resource' === aw_si_page_class( '/resources/y/', 'page' ) && 'news' === aw_si_page_class( '/news/z/', '' ) && 'directory' === aw_si_page_class( '/d/', 'aw_listing' ), 'page class from post type and path' );

$own = aw_si_canonical_owner(
	array(
		array(
			'path'      => '/news/x',
			'post_type' => 'aw_news',
			'pos'       => 3.0,
			'impr'      => 100,
		),
		array(
			'path'      => '/resources/y',
			'post_type' => 'page',
			'pos'       => 8.0,
			'impr'      => 60,
		),
	),
	'evergreen'
);
iw_check( '/resources/y' === $own['owner']['path'] && 'news-should-be-evergreen' === $own['flag'], 'canonical owner: evergreen query a news page holds → owner is the resource, flagged' );
$own = aw_si_canonical_owner(
	array(
		array(
			'path'      => '/a/',
			'post_type' => 'post',
			'pos'       => 6.0,
			'impr'      => 50,
		),
		array(
			'path'      => '/b/',
			'post_type' => 'post',
			'pos'       => 7.0,
			'impr'      => 50,
		),
	),
	'evergreen'
);
iw_check( 'no-owner' === $own['flag'], 'canonical owner: two pages split the query with no majority → no canonical owner' );

// The stored score row the recompute writes (aw_si_score_row) and the sourcing-types filter.
$row = aw_si_score_row(
	array(
		'impr'     => 5000,
		'clicks'   => 500,
		'pos'      => 12.0,
		'growth'   => 5.0,
		'has_page' => true,
		'roles'    => 2,
		'topic'    => '',
	)
);
iw_check( 100 === $row['score'] && 'focus-page' === $row['recommended'] && '' === $row['focus_health'], 'score row: strong owned keyword → full score, focus-page, agrees with Rank Math' );
$row = aw_si_score_row(
	array(
		'impr'     => 5000,
		'clicks'   => 300,
		'pos'      => null,
		'growth'   => 5.0,
		'has_page' => false,
		'roles'    => 0,
	)
);
iw_check( $row['score'] >= 55 && 'write-page' === $row['recommended'], 'score row: demand, no page, not ranking → write a new page' );
$row = aw_si_score_row(
	array(
		'impr'     => 100,
		'clicks'   => 5,
		'pos'      => 2.0,
		'has_page' => true,
		'roles'    => 2,
	)
);
iw_check( 'poor-rank' === $row['focus_health'] && 'secondary' === $row['recommended'], 'score row: a focus keyphrase already top-3 → poor-rank + secondary' );
$row = aw_si_score_row(
	array(
		'impr'     => 100,
		'clicks'   => 5,
		'pos'      => 12.0,
		'has_page' => true,
		'topic'    => 'rabies',
	),
	array( 'rabies' => 'library' )
);
iw_check( false !== strpos( $row['recommended'], 'sourcing:library' ), 'score row: a sourcing topic adds sourcing:library' );
iw_check( is_array( aw_si_sourcing_types() ), 'sourcing types: a map (empty until SourceWeb fills the filter)' );

// The SourceWeb emit side (sourcing-push.php): threshold + the per-candidate context.
require dirname( __DIR__ ) . '/includes/search-insights/sourcing-push.php';
iw_check( 70 === aw_si_sourcing_push_min(), 'sourcing push: default threshold 70' );
$ctx = aw_si_push_context(
	array(
		'score'       => 80,
		'recommended' => 'write-page,sourcing:news',
		'impr'        => 500,
		'clicks'      => 20,
		'pos'         => 6.0,
		'topic'       => 'Rabies',
		'roles'       => 2,
	)
);
iw_check( 'insightweb' === $ctx['source'] && 80 === $ctx['score'] && 'news' === $ctx['recommended_type'] && 6.0 === $ctx['position'] && 6.0 === $ctx['rank'] && true === $ctx['focus_keyphrase'] && 'Rabies' === $ctx['topic'], 'sourcing push: the context carries score, type, rank and the focus-keyphrase flag' );
$ctx = aw_si_push_context(
	array(
		'score'       => 60,
		'recommended' => 'secondary',
		'impr'        => 100,
		'pos'         => 12.0,
		'roles'       => 0,
	)
);
iw_check( 'news' === $ctx['recommended_type'] && false === $ctx['focus_keyphrase'], 'sourcing push: no sourcing: recommendation → type defaults to news, not a focus keyphrase' );

/* ---------- 9. Keys: the copy from the old vault (insightweb-keys.php) ---------- */

require dirname( __DIR__ ) . '/includes/insightweb-keys.php';
$GLOBALS['iw_options']['aw_keys'] = array( 'AW_CLARITY_TOKEN' => 'old-clarity', 'AW_BING_WEBMASTER_KEY' => 'old-bing', 'AW_OTHER_PLUGIN_KEY' => 'not-ours' );
unset( $GLOBALS['iw_options']['insightweb_keys'] );
$writes = $GLOBALS['iw_writes'];
$res    = insightweb_keys_copy( false );
iw_check( 'copied' === $res['AW_CLARITY_TOKEN'] && 'copied' === $res['AW_BING_WEBMASTER_KEY'] && 'not in the old place' === $res['AW_PSI_KEY'] && $writes === $GLOBALS['iw_writes'] && ! isset( $GLOBALS['iw_options']['insightweb_keys'] ), 'keys copy: a rehearsal names what it would copy and writes nothing' );
$res = insightweb_keys_copy( true );
iw_check( 'copied' === $res['AW_CLARITY_TOKEN'] && 'old-clarity' === ( $GLOBALS['iw_options']['insightweb_keys']['AW_CLARITY_TOKEN'] ?? '' ) && 'old-bing' === ( $GLOBALS['iw_options']['insightweb_keys']['AW_BING_WEBMASTER_KEY'] ?? '' ), 'keys copy: the vault\'s InsightWeb keys are copied into InsightWeb\'s own option' );
iw_check( ! isset( $GLOBALS['iw_options']['insightweb_keys']['AW_OTHER_PLUGIN_KEY'] ) && isset( $GLOBALS['iw_options']['aw_keys']['AW_OTHER_PLUGIN_KEY'] ) && 'old-clarity' === $GLOBALS['iw_options']['aw_keys']['AW_CLARITY_TOKEN'], 'keys copy: a key InsightWeb does not own is not copied, and the old vault is left as it was' );
$writes = $GLOBALS['iw_writes'];
$res    = insightweb_keys_copy( true );
iw_check( 'already set' === $res['AW_CLARITY_TOKEN'] && 'already set' === $res['AW_BING_WEBMASTER_KEY'] && $writes === $GLOBALS['iw_writes'], 'keys copy: run again, it copies nothing and writes nothing' );
$GLOBALS['iw_options']['insightweb_keys']['AW_CLARITY_TOKEN'] = 'new-clarity';
insightweb_keys_copy( true );
iw_check( 'new-clarity' === $GLOBALS['iw_options']['insightweb_keys']['AW_CLARITY_TOKEN'], 'keys copy: a key already set in InsightWeb is never overwritten by the old one' );
iw_check( ! in_array( 'old-clarity', array_map( 'strval', array_values( $res ) ), true ), 'keys copy: the result carries names and words, never a value' );

exit( $GLOBALS['iw_fail'] );
