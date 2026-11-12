<?php
/**
 * InsightWeb tests that need no WordPress, for the new Dashboard and Settings:
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
 *
 * Usage: php plugins/insightweb/tests/run.php
 *
 * @package aw-seo-extras
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput, WordPress.PHP.DiscouragedPHPFunctions, WordPress.WP.GlobalVariablesOverride, WordPress.DateTime.RestrictedFunctions, Squiz.Commenting.FunctionComment.MissingParamTag, Universal.Files.SeparateFunctionsFromOO -- a command-line test runner with WordPress stubs.

define( 'ABSPATH', __DIR__ . '/' );
const DAY_IN_SECONDS = 86400;

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
		'on'              => 1,
		'AW_SI_MOVERS'    => 30,
		'AW_SI_HIGH_IMPR' => 50, // Stored though equal to the default: kept as it is.
	),
);
$before                                   = serialize( $GLOBALS['iw_options']['aw_seo_settings'] );
$w                                        = $GLOBALS['iw_writes'];
iw_check( false === aw_si_settings_save( iw_texts() ) && serialize( $GLOBALS['iw_options']['aw_seo_settings'] ) === $before && $GLOBALS['iw_writes'] === $w, 'unchanged Save with values stored writes nothing (byte for byte)' );
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
require dirname( __DIR__ ) . '/includes/insightweb-boxes.php';
$b = insightweb_html_blocks(
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

// 6. Keywords and the other tabs: list tables and forms as parts (insightweb-tabs.php).
if ( ! function_exists( 'sanitize_key' ) ) {
	/** Stub. */
	function sanitize_key( $k ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) );
	}
}
require dirname( __DIR__ ) . '/includes/insightweb-tabs.php';
$list = '<div class="notice notice-success inline"><p>Priority saved.</p></div>'
	. '<ul class="subsubsub"><li><a href="admin.php?page=aw-search-insights&amp;tab=keywords" class="current" aria-current="page">All <span class="count">(1,204)</span></a> |</li><li><a href="admin.php?page=aw-search-insights&amp;tab=keywords&amp;view=p1">P1 <span class="count">(12)</span></a></li></ul>'
	. '<form method="get"><input type="hidden" name="page" value="aw-search-insights"><input type="hidden" name="tab" value="keywords">'
	. '<p class="search-box"><label class="screen-reader-text" for="aw-si-kw-search-input">Search keywords:</label><input type="search" id="aw-si-kw-search-input" name="s" value="dog"><input type="submit" class="button" value="Search keywords"></p>'
	. '<input type="hidden" id="_wpnonce" name="_wpnonce" value="abc123"><input type="hidden" name="_wp_http_referer" value="/wp-admin/admin.php?page=aw-search-insights&amp;tab=keywords">'
	. '<div class="tablenav top"><div class="alignleft actions bulkactions"><label for="bulk-action-selector-top" class="screen-reader-text">Select bulk action</label><select name="action" id="bulk-action-selector-top"><option value="-1">Bulk actions</option><option value="override_p1">Set P1</option></select><input type="submit" class="button action" value="Apply"></div>'
	. '<div class="alignleft actions"><label class="screen-reader-text" for="aw-si-src">Filter by source</label><select name="source" id="aw-si-src"><option value="">All sources</option><option value="gsc" selected="selected">Search Console</option></select><input type="submit" name="filter_action" class="button" value="Filter"></div>'
	. '<div class="tablenav-pages"><span class="displaying-num">1,204 items</span><span class="pagination-links"><span class="paging-input"><input class="current-page" type="text" name="paged" value="2" size="2"> of <span class="total-pages">25</span></span></span></div></div>'
	. '<table class="wp-list-table widefat fixed striped table-view-list keywords"><thead><tr><td id="cb" class="manage-column column-cb check-column"><input id="cb-select-all-1" type="checkbox"></td>'
	. '<th scope="col" id="label" class="manage-column column-label column-primary sorted asc"><a href="admin.php?page=aw-search-insights&amp;tab=keywords&amp;orderby=label&amp;order=desc"><span>Keyword</span><span class="sorting-indicators"></span><span class="screen-reader-text">Sort descending.</span></a></th>'
	. '<th scope="col" id="page" class="manage-column column-page">Page</th></tr></thead>'
	. '<tbody id="the-list"><tr><th scope="row" class="check-column"><input type="checkbox" name="kw[]" value="41"></th><td class="label column-label has-row-actions column-primary" data-colname="Keyword">stray dogs<div class="row-actions"><span class="p1"><a href="admin.php?page=aw-search-insights&amp;tab=keywords&amp;aw_si_kw=override_p1&amp;kw%5B0%5D=41&amp;_wpnonce=abc123">Set P1</a> | </span><span><a href="javascript:void(0)">Bad</a></span></div><button type="button" class="toggle-row"><span class="screen-reader-text">Show more details</span></button></td>'
	. '<td class="page column-page" data-colname="Page"><a href="https://animalwrites.in/news/x/" target="_blank">/news/x/</a></td></tr>'
	. '<tr><th scope="row" class="check-column"></th><td class="label column-label column-primary">dogs (all)</td><td class="page column-page">—</td></tr></tbody></table></form><script>alert(1)</script>';
$p     = insightweb_html_parts( $list );
$types = array_column( $p, 'type' );
iw_check( array( 'notice', 'listtable' ) === $types, 'tab parts: notice, then the list with its views: ' . implode( ', ', $types ) );
$t = $p[1];
iw_check( 2 === count( $t['views'] ) && 'All' === $t['views'][0]['label'] && '1,204' === $t['views'][0]['count'] && $t['views'][0]['current'] && false !== strpos( $t['views'][1]['href'], 'view=p1' ), 'views: label, count, current and address' );
iw_check( 's' === $t['search']['name'] && 'dog' === $t['search']['value'] && 'Search keywords:' === $t['search']['label'], 'search box' );
iw_check( array( array( 'value' => 'override_p1', 'label' => 'Set P1' ) ) === $t['bulk'] && 'kw[]' === $t['cb'], 'bulk actions and the tick box name' );
iw_check( 1 === count( $t['filters'] ) && 'source' === $t['filters'][0]['key'] && 'gsc' === $t['filters'][0]['value'] && 'Filter by source' === $t['filters'][0]['label'], 'filter with its choice' );
iw_check( 1204 === $t['total'] && 2 === $t['page'] && 25 === $t['pages'], 'count and pages' );
iw_check( 'get' === $t['method'] && in_array( array( '_wpnonce', 'abc123' ), $t['hidden'], true ) && in_array( array( 'tab', 'keywords' ), $t['hidden'], true ) && ! in_array( 's', array_column( $t['hidden'], 0 ), true ), 'the form\'s own hidden fields and nonce, not its search' );
iw_check( array( 'label', 'page' ) === array_column( $t['columns'], 'key' ) && 'Keyword' === $t['columns'][0]['label'] && 'asc' === $t['columns'][0]['sorted'] && $t['columns'][0]['primary'] && ! isset( $t['columns'][1]['sortHref'] ), 'columns: key, label without screen-reader words, sort' );
iw_check( '41' === $t['rows'][0]['pick'] && 'stray dogs' === $t['rows'][0]['cells']['label']['text'] && ! isset( $t['rows'][0]['cells']['label']['href'] ), 'row: tick box value, cell text without row actions' );
iw_check( 1 === count( $t['rows'][0]['actions'] ) && 'Set P1' === $t['rows'][0]['actions'][0]['label'] && false !== strpos( $t['rows'][0]['actions'][0]['href'], 'aw_si_kw=override_p1' ), 'row actions: safe links only' );
iw_check( 'https://animalwrites.in/news/x/' === $t['rows'][0]['cells']['page']['href'] && $t['rows'][0]['cells']['page']['external'], 'cell link' );
iw_check( '' === $t['rows'][1]['pick'], 'a row without a tick box cannot be picked' );
iw_check( false === strpos( wp_json_encode_stub( $p ), 'alert' ) && false === strpos( wp_json_encode_stub( $p ), 'javascript' ) && false === strpos( wp_json_encode_stub( $p ), '<' ), 'no script or markup from a tab' );

$f = insightweb_html_parts(
	'<h2>Notes</h2><form method="post" action="https://animalwrites.in/wp-admin/admin-post.php"><input type="hidden" name="action" value="aw_si_note"><input type="hidden" name="_wpnonce" value="n1">'
	. '<p>Add a word.</p><table class="form-table"><tr><th><label for="w">Word</label></th><td><input type="text" id="w" name="word" value="cat" required><p class="description">One per line.</p></td></tr>'
	. '<tr><th>Kind</th><td><label><input type="radio" name="kind" value="a" checked> A</label><label><input type="radio" name="kind" value="b"> B</label></td></tr></table>'
	. '<textarea name="notes" rows="4">x</textarea><select name="src"><option value="1">One</option><option value="2" selected>Two</option></select><label><input type="checkbox" name="on" value="1" checked> Turn on</label>'
	. '<input type="password" name="pw" value="secret"><button type="submit" name="aw_si_cl" value="start" class="button button-primary">Start live check</button>'
	. '<input type="submit" class="button" value="Save"></form>'
);
iw_check( array( 'heading', 'form' ) === array_column( $f, 'type' ), 'heading, then the form' );
$g = $f[1];
iw_check( 'post' === $g['method'] && false !== strpos( $g['action'], 'admin-post.php' ) && array( array( 'action', 'aw_si_note' ), array( '_wpnonce', 'n1' ) ) === $g['hidden'], 'form address, method, hidden fields' );
$by = array_column( $g['fields'], null, 'name' );
iw_check( 'Word' === $by['word']['label'] && 'cat' === $by['word']['value'] && 'One per line.' === $by['word']['help'] && $by['word']['required'], 'text field: label from the row, value, help, required' );
iw_check( 'radio' === $by['kind']['type'] && 'Kind' === $by['kind']['label'] && 'a' === $by['kind']['value'] && 2 === count( $by['kind']['options'] ), 'radio group' );
iw_check( 'x' === $by['notes']['value'] && 4 === $by['notes']['rows'] && '2' === $by['src']['value'] && $by['on']['checked'] && 'Turn on' === $by['on']['label'], 'textarea, select and tick box' );
iw_check( ! isset( $by['pw'] ) && false === strpos( wp_json_encode_stub( $f ), 'secret' ), 'a password field never goes out' );
iw_check( 2 === count( $g['buttons'] ) && 'aw_si_cl' === $g['buttons'][0]['name'] && $g['buttons'][0]['primary'] && 'Save' === $g['buttons'][1]['label'], 'buttons' );
iw_check( array( 'Add a word.' ) === array_column( $g['blocks'], 'text' ), 'the form\'s own words, without its labels' );

exit( $GLOBALS['iw_fail'] );
