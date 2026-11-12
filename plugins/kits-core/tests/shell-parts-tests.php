<?php
/**
 * Page parts tests that need no WordPress (owner rule: no local WordPress or MySQL): a classic screen's output as the
 * plain parts the shell draws (includes/admin-shell-parts.php): text, notices, a list table with its views, search,
 * filters, bulk and row actions and pages, and a form with its fields and buttons; no markup, script or password
 * goes out. The Deploy check also draws InsightWeb's tabs from it in a real WordPress (tools/ci/smoke-ui-insightweb.sh).
 *
 * Usage: php plugins/kits-core/tests/shell-parts-tests.php
 *
 * @package kits-core
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput -- a command-line test runner.

if ( 'cli' !== PHP_SAPI ) {
	exit;
}
define( 'ABSPATH', __DIR__ . '/' );

/** Stub. */
function esc_url_raw( $u ) {
	return (string) $u;
}
/** Stub. */
function sanitize_key( $k ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) );
}
/** Stub. */
function wp_strip_all_tags( $h ) {
	return trim( strip_tags( (string) $h ) );
}
require dirname( __DIR__ ) . '/includes/admin-shell-parts.php';

$GLOBALS['kits_parts_fail'] = 0;

/**
 * One check.
 *
 * @param bool   $ok   Passed.
 * @param string $what What it checks.
 */
function kits_parts_check( bool $ok, string $what ): void {
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $what . "\n";
	if ( ! $ok ) {
		$GLOBALS['kits_parts_fail'] = 1;
	}
}

$b = kits_shell_html_blocks( '<p>Hi <a href="https://x.test/">there</a></p><form><input name="x"></form><script>alert(1)</script>' );
kits_parts_check( array( 'p' ) === array_column( $b, 'type' ) && 'Hi there' === $b[0]['text'] && 'https://x.test/' === $b[0]['href'], 'plain parts: a paragraph with its link; form and script dropped' );
kits_parts_check( array() === kits_shell_html_blocks( '  ' ), 'nothing in, nothing out' );

$list  = '<div class="notice notice-success inline"><p>Priority saved.</p></div>'
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
$p     = kits_shell_html_parts( $list );
$types = array_column( $p, 'type' );
kits_parts_check( array( 'notice', 'listtable' ) === $types, 'tab parts: notice, then the list with its views: ' . implode( ', ', $types ) );
$t = $p[1];
kits_parts_check( 2 === count( $t['views'] ) && 'All' === $t['views'][0]['label'] && '1,204' === $t['views'][0]['count'] && $t['views'][0]['current'] && false !== strpos( $t['views'][1]['href'], 'view=p1' ), 'views: label, count, current and address' );
kits_parts_check( 's' === $t['search']['name'] && 'dog' === $t['search']['value'] && 'Search keywords:' === $t['search']['label'], 'search box' );
kits_parts_check(
	array(
		array(
			'value' => 'override_p1',
			'label' => 'Set P1',
		),
	) === $t['bulk'] && 'kw[]' === $t['cb'],
	'bulk actions and the tick box name'
);
kits_parts_check( 1 === count( $t['filters'] ) && 'source' === $t['filters'][0]['key'] && 'gsc' === $t['filters'][0]['value'] && 'Filter by source' === $t['filters'][0]['label'], 'filter with its choice' );
kits_parts_check( 1204 === $t['total'] && 2 === $t['page'] && 25 === $t['pages'], 'count and pages' );
kits_parts_check( 'get' === $t['method'] && in_array( array( '_wpnonce', 'abc123' ), $t['hidden'], true ) && in_array( array( 'tab', 'keywords' ), $t['hidden'], true ) && ! in_array( 's', array_column( $t['hidden'], 0 ), true ), 'the form\'s own hidden fields and nonce, not its search' );
kits_parts_check( array( 'label', 'page' ) === array_column( $t['columns'], 'key' ) && 'Keyword' === $t['columns'][0]['label'] && 'asc' === $t['columns'][0]['sorted'] && $t['columns'][0]['primary'] && ! isset( $t['columns'][1]['sortHref'] ), 'columns: key, label without screen-reader words, sort' );
kits_parts_check( '41' === $t['rows'][0]['pick'] && 'stray dogs' === $t['rows'][0]['cells']['label']['text'] && ! isset( $t['rows'][0]['cells']['label']['href'] ), 'row: tick box value, cell text without row actions' );
kits_parts_check( 1 === count( $t['rows'][0]['actions'] ) && 'Set P1' === $t['rows'][0]['actions'][0]['label'] && false !== strpos( $t['rows'][0]['actions'][0]['href'], 'aw_si_kw=override_p1' ), 'row actions: safe links only' );
kits_parts_check( 'https://animalwrites.in/news/x/' === $t['rows'][0]['cells']['page']['href'] && $t['rows'][0]['cells']['page']['external'], 'cell link' );
kits_parts_check( '' === $t['rows'][1]['pick'], 'a row without a tick box cannot be picked' );
kits_parts_check( false === strpos( json_encode( $p ), 'alert' ) && false === strpos( json_encode( $p ), 'javascript' ) && false === strpos( json_encode( $p ), '<' ), 'no script or markup from a tab' );

$f = kits_shell_html_parts(
	'<h2>Notes</h2><form method="post" action="https://animalwrites.in/wp-admin/admin-post.php"><input type="hidden" name="action" value="aw_si_note"><input type="hidden" name="_wpnonce" value="n1">'
	. '<p>Add a word.</p><table class="form-table"><tr><th><label for="w">Word</label></th><td><input type="text" id="w" name="word" value="cat" required><p class="description">One per line.</p></td></tr>'
	. '<tr><th>Kind</th><td><label><input type="radio" name="kind" value="a" checked> A</label><label><input type="radio" name="kind" value="b"> B</label></td></tr></table>'
	. '<textarea name="notes" rows="4">x</textarea><select name="src"><option value="1">One</option><option value="2" selected>Two</option></select><label><input type="checkbox" name="on" value="1" checked> Turn on</label><label><input type="checkbox" name="off" value="1" disabled> Locked</label>'
	. '<input type="password" name="pw" value="secret"><button type="submit" name="aw_si_cl" value="start" class="button button-primary">Start live check</button>'
	. '<input type="submit" class="button" value="Save"></form>'
);
kits_parts_check( array( 'heading', 'form' ) === array_column( $f, 'type' ), 'heading, then the form' );
$g = $f[1];
kits_parts_check( 'post' === $g['method'] && false !== strpos( $g['action'], 'admin-post.php' ) && array( array( 'action', 'aw_si_note' ), array( '_wpnonce', 'n1' ) ) === $g['hidden'], 'form address, method, hidden fields' );
$by = array_column( $g['fields'], null, 'name' );
kits_parts_check( 'Word' === $by['word']['label'] && 'cat' === $by['word']['value'] && 'One per line.' === $by['word']['help'] && $by['word']['required'], 'text field: label from the row, value, help, required' );
kits_parts_check( 'radio' === $by['kind']['type'] && 'Kind' === $by['kind']['label'] && 'a' === $by['kind']['value'] && 2 === count( $by['kind']['options'] ), 'radio group' );
kits_parts_check( 'x' === $by['notes']['value'] && 4 === $by['notes']['rows'] && '2' === $by['src']['value'] && $by['on']['checked'] && 'Turn on' === $by['on']['label'], 'textarea, select and tick box' );
kits_parts_check( ! isset( $by['pw'] ) && false === strpos( json_encode( $f ), 'secret' ), 'a password field never goes out' );
kits_parts_check( $by['off']['disabled'] && ! $by['on']['disabled'], 'a disabled field stays disabled' );
kits_parts_check( 2 === count( $g['buttons'] ) && 'aw_si_cl' === $g['buttons'][0]['name'] && $g['buttons'][0]['primary'] && 'Save' === $g['buttons'][1]['label'], 'buttons' );
kits_parts_check( array( 'Add a word.' ) === array_column( $g['blocks'], 'text' ), 'the form\'s own words, without its labels' );

exit( $GLOBALS['kits_parts_fail'] );
