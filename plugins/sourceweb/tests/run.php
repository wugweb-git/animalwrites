<?php
/**
 * SourceWeb tests that need no WordPress: the keyword matcher against tests/fixtures/match_cases.json and the
 * relevance verdict against explicit keyword lists.
 *
 * Usage: php plugins/sourceweb/tests/run.php
 *
 * @package sourceweb
 */

if ( ! defined( 'ABSPATH' ) && 'cli' !== PHP_SAPI ) {
	exit;
}

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput -- a command-line test runner.

define( 'ABSPATH', __DIR__ . '/' );

require dirname( __DIR__ ) . '/includes/matcher.php';
require dirname( __DIR__ ) . '/includes/relevance.php';

$fails = 0;
$count = 0;

/**
 * Assert equality.
 *
 * @param mixed  $expected Expected.
 * @param mixed  $actual   Actual.
 * @param string $label    Label.
 */
function sourceweb_assert( $expected, $actual, $label ) {
	global $fails, $count;
	++$count;
	if ( $expected !== $actual ) {
		++$fails;
		echo "FAIL: {$label}\n  expected: " . var_export( $expected, true ) . "\n  actual:   " . var_export( $actual, true ) . "\n";
	}
}

// 1. The shared matcher against the fixture (the "word" field is the expected result).
$data = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/match_cases.json' ), true );
foreach ( (array) ( $data['cases'] ?? array() ) as $i => $c ) {
	sourceweb_assert(
		(bool) $c['word'],
		sourceweb_match_term( (string) $c['text'], (string) $c['term'] ),
		"match_term #{$i}: '{$c['term']}' in '{$c['text']}' ({$c['note']})"
	);
}

// 2. The relevance verdict with explicit lists.
$lists = array(
	'enabled' => true,
	'strong'  => array( 'animal cruelty', 'rabies' ),
	'animal'  => array( 'dog', 'cat', 'elephant' ),
	'context' => array( 'rescue', 'court', 'bite' ),
	'exclude' => array( 'top dog', 'party animal' ),
);

$verdict = static function ( $text ) use ( $lists ) {
	return sourceweb_relevance_verdict( $text, $lists );
};

sourceweb_assert( true, $verdict( 'Animal cruelty case filed in Delhi' )[0], 'strong term keeps' );
sourceweb_assert( true, $verdict( 'Stray dog taken to court after a bite' )[0], 'animal + context keeps' );
sourceweb_assert( false, $verdict( 'Dog rescued from a drain' )[0], 'whole-word: "rescued" does not match term "rescue"' );
sourceweb_assert( false, $verdict( 'Dog show dazzles the crowd' )[0], 'animal without context rejected' );
sourceweb_assert( false, $verdict( 'He is the top dog at the firm' )[0], 'exclude term rejects' );
sourceweb_assert( false, $verdict( 'Quarterly results beat estimates' )[0], 'no term rejected' );
sourceweb_assert( false, $verdict( 'Party animal spotted; dog rescue later' )[0], 'exclude beats a later keep' );

// 3. Host normalisation (needs no WordPress — sources.php uses parse_url when wp_parse_url is absent).
require dirname( __DIR__ ) . '/includes/sources.php';
sourceweb_assert( 'thehindu.com', sourceweb_norm_host( 'https://www.thehindu.com/news/x' ), 'norm_host strips www' );
sourceweb_assert( 'example.org', sourceweb_norm_host( 'http://Example.org/' ), 'norm_host lowercases' );

// 4. External-services registry (minimal WordPress-sanitiser stubs so the pure helpers run on the CLI).
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $str ) ) );
	}
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return trim( (string) $url );
	}
}
require dirname( __DIR__ ) . '/includes/external-services.php';

$svc = sourceweb_normalize_service( 'Test Host', array( 'host' => 'https://www.API.Example.com/v1', 'purpose' => 'x', 'terms_url' => 'https://e.com/t' ), 'admin' );
sourceweb_assert( 'testhost', $svc['id'], 'service id is sanitised to a key' );
sourceweb_assert( array( 'api.example.com' ), $svc['hosts'], 'single host normalised into hosts[] (scheme/path/www stripped, lowercased)' );
sourceweb_assert( 'https://e.com/t', $svc['terms_url'], 'terms_url kept' );
sourceweb_assert( false, $svc['enabled'], 'admin service defaults to disabled (opt-in)' );

// hosts[] with several hosts; legacy data/terms/privacy aliases accepted; id derived from the first host when blank.
$multi = sourceweb_normalize_service( '', array( 'hosts' => 'Google.com, www.googleapis.com', 'data' => 'a url', 'terms' => 'https://g/t', 'privacy' => 'https://g/p' ), 'admin' );
sourceweb_assert( array( 'google.com', 'googleapis.com' ), $multi['hosts'], 'hosts[] parses a comma list and normalises each' );
sourceweb_assert( 'google-com', $multi['id'], 'id derived from the first host when none given' );
sourceweb_assert( 'a url', $multi['data_sent'], 'legacy data alias maps to data_sent' );
sourceweb_assert( 'https://g/p', $multi['privacy_url'], 'legacy privacy alias maps to privacy_url' );

sourceweb_assert( null, sourceweb_normalize_service( 'x', array(), 'admin' ), 'missing hosts => null' );

$services = array(
	'openai' => sourceweb_normalize_service( 'openai', array( 'hosts' => array( 'api.openai.com', 'api.openai.example' ), 'purpose' => 'AI summaries', 'data_sent' => 'text + key', 'terms_url' => 'https://openai.com/terms', 'privacy_url' => 'https://openai.com/privacy', 'enabled' => true ), 'code' ),
);
sourceweb_assert( 'openai', sourceweb_external_service_for_host( 'https://api.openai.com/v1/chat', $services )['id'], 'host lookup matches the first host by URL' );
sourceweb_assert( 'openai', sourceweb_external_service_for_host( 'www.API.OpenAI.com', $services )['id'], 'host lookup matches a bare, cased, www host' );
sourceweb_assert( 'openai', sourceweb_external_service_for_host( 'api.openai.example', $services )['id'], 'host lookup matches a second host in hosts[]' );
sourceweb_assert( null, sourceweb_external_service_for_host( 'evil.example', $services ), 'unregistered host => null' );

$readme = sourceweb_external_services_readme( $services );
sourceweb_assert( true, false !== strpos( $readme, '== External services ==' ), 'readme has the section header' );
sourceweb_assert( true, false !== strpos( $readme, 'api.openai.com, api.openai.example' ), 'readme lists all hosts' );
sourceweb_assert( true, false !== strpos( $readme, 'https://openai.com/privacy' ), 'readme lists the privacy URL' );
sourceweb_assert( true, false !== strpos( sourceweb_external_services_readme( array() ), 'does not contact any external service' ), 'empty registry => no-services disclosure' );

// 5. The pure decision pipeline (source status + exclude + relevance), no side effects.
$dlists = array(
	'enabled' => true,
	'strong'  => array( 'rabies' ),
	'animal'  => array( 'dog' ),
	'context' => array( 'court' ),
	'exclude' => array( 'top dog' ),
);
$dsources = array(
	'blocked.example' => array( 'status' => 'block' ),
	'trusted.example' => array( 'status' => 'allow' ),
);
sourceweb_assert( false, sourceweb_decide( 'https://blocked.example/x', 'Dog taken to court', $dlists, $dsources )['keep'], 'blocked source rejected even when relevant' );
sourceweb_assert( true, sourceweb_decide( 'https://trusted.example/x', 'Anything at all', $dlists, $dsources )['keep'], 'allow source skips the relevance filter' );
sourceweb_assert( false, sourceweb_decide( 'https://trusted.example/x', 'He is the top dog here', $dlists, $dsources )['keep'], 'exclude term rejects even an allow source' );
sourceweb_assert( true, sourceweb_decide( 'https://neutral.example/x', 'Dog taken to court', $dlists, $dsources )['keep'], 'neutral source kept when relevant' );
sourceweb_assert( false, sourceweb_decide( 'https://neutral.example/x', 'Quarterly results', $dlists, $dsources )['keep'], 'neutral source rejected when not relevant' );
sourceweb_assert( true, sourceweb_decide( 'https://neutral.example/x', 'Quarterly results', array( 'enabled' => false ) + $dlists, $dsources )['keep'], 'filter off => kept (exclude still applies)' );

// 6. Feeds: cleaning, host extraction and auto-registration as an external service.
require dirname( __DIR__ ) . '/includes/feeds.php';
$feeds = sourceweb_clean_feed_list( "https://news.example.com/rss\nnot-a-url\nhttp://FEEDS.Example.com/a\nhttps://news.example.com/rss\n" );
sourceweb_assert( 2, count( $feeds ), 'feed list drops the invalid line and the duplicate' );
sourceweb_assert( array( 'news.example.com', 'feeds.example.com' ), sourceweb_feed_hosts( $feeds ), 'feed hosts are normalised and de-duplicated' );

// 7. Title normalisation for duplicate detection (pure).
require dirname( __DIR__ ) . '/includes/queue.php';
sourceweb_assert( 'stray dogs attacked a child', sourceweb_norm_title( 'Stray dogs attacked a child' ), 'norm_title lowercases' );
sourceweb_assert( sourceweb_norm_title( 'Dog rescued from a drain' ), sourceweb_norm_title( 'Dog rescued from a drain - The Hindu' ), 'norm_title drops a trailing " - Publisher"' );
sourceweb_assert( 'a b c rules upheld', sourceweb_norm_title( 'A.B.C. rules upheld!' ), 'norm_title turns punctuation into spaces' );

// 8. Feed parsing (pure, SimpleXML) — RSS 2.0 and Atom.
require dirname( __DIR__ ) . '/includes/parse.php';

$rss = '<?xml version="1.0"?><rss version="2.0"><channel><title>Feed</title>'
	. '<item><title>Dog rescued</title><link>https://news.example.com/a</link><description>A dog was rescued.</description></item>'
	. '<item><title>Cat adopted</title><link>https://news.example.com/b</link><description><![CDATA[A cat found a home.]]></description></item>'
	. '</channel></rss>';
$rss_items = sourceweb_parse_feed( $rss );
sourceweb_assert( 2, count( $rss_items ), 'RSS: two items parsed' );
sourceweb_assert( 'Dog rescued', $rss_items[0]['title'], 'RSS: first title' );
sourceweb_assert( 'https://news.example.com/a', $rss_items[0]['url'], 'RSS: first link' );
sourceweb_assert( 'A cat found a home.', $rss_items[1]['summary'], 'RSS: CDATA description parsed' );

$atom = '<?xml version="1.0"?><feed xmlns="http://www.w3.org/2005/Atom"><title>Feed</title>'
	. '<entry><title>Rabies alert</title><link rel="alternate" href="https://site.example/x"/><link rel="edit" href="https://site.example/edit"/><summary>Summary here.</summary></entry>'
	. '</feed>';
$atom_items = sourceweb_parse_feed( $atom );
sourceweb_assert( 1, count( $atom_items ), 'Atom: one entry parsed' );
sourceweb_assert( 'Rabies alert', $atom_items[0]['title'], 'Atom: title' );
sourceweb_assert( 'https://site.example/x', $atom_items[0]['url'], 'Atom: rel=alternate link chosen over rel=edit' );
sourceweb_assert( 'Summary here.', $atom_items[0]['summary'], 'Atom: summary' );

sourceweb_assert( array(), sourceweb_parse_feed( 'not xml at all' ), 'garbage input => no items' );
sourceweb_assert( 1, count( sourceweb_parse_feed( $atom, 1 ) ), 'limit is respected' );

// 9. Summary rules (pure): strip phrases + trim to N words at a sentence boundary.
require dirname( __DIR__ ) . '/includes/rules.php';
sourceweb_assert( 'One two three…', sourceweb_apply_summary_rules( 'One two three four five', array( 'max_words' => 3 ) ), 'trim to 3 words (no sentence end) ends with an ellipsis' );
sourceweb_assert( 'One two three four.', sourceweb_apply_summary_rules( 'One two three four. Five six seven.', array( 'max_words' => 6 ) ), 'trim ends on the last complete sentence within the limit' );
sourceweb_assert( 'Breaking a dog was rescued', sourceweb_apply_summary_rules( 'Breaking: ADVERTISEMENT a dog was rescued', array( 'strip' => array( 'ADVERTISEMENT', ':' ) ) ), 'strip phrases removed, whitespace collapsed' );
sourceweb_assert( 'Keep all of this text intact', sourceweb_apply_summary_rules( 'Keep all of this text intact', array( 'max_words' => 0 ) ), 'max_words 0 leaves text untouched' );
sourceweb_assert( true, '…' === mb_substr( sourceweb_apply_summary_rules( 'alpha beta gamma delta epsilon', array( 'max_words' => 2 ) ), -1 ), 'ellipsis appended when no sentence end within the limit' );

// 10. Same-story detection (pure): exact normalised match or token-overlap similarity.
require dirname( __DIR__ ) . '/includes/same-story.php';
sourceweb_assert( true, sourceweb_is_same_story( 'Stray dog rescued in Pune', 'Stray dog rescued in Pune - PTI' ), 'same story: identical after norm (publisher suffix dropped)' );
sourceweb_assert( true, sourceweb_is_same_story( 'Leopard rescued from a well in Nashik district', 'Leopard rescued from well in Nashik' ), 'same story: high token overlap' );
sourceweb_assert( false, sourceweb_is_same_story( 'Leopard rescued from a well in Nashik', 'Supreme Court hears stray dog case today' ), 'different stories: low overlap' );
sourceweb_assert( true, sourceweb_title_similarity( 'cats and dogs in the city', 'cats and dogs in the city' ) >= 0.99, 'identical titles => similarity ~1' );

// 11. Stats aggregation (pure): date range, series zero-fill, totals, dedupe rate, period delta, queue snapshot, trim.
require dirname( __DIR__ ) . '/includes/stats.php';
$sw_today = '2026-11-10';
$sw_stats = array(
	'days' => array(
		'2026-11-08' => array(
			'fetched'        => 10,
			'queued'         => 4,
			'skipped_dedupe' => 3,
			'skipped_gate'   => 2,
			'skipped_other'  => 1,
			'approved'       => 2,
			'rejected'       => 1,
			'queue_size'     => 5,
		),
		'2026-11-09' => array(
			'fetched'        => 6,
			'queued'         => 2,
			'skipped_dedupe' => 1,
			'skipped_gate'   => 0,
			'skipped_other'  => 0,
			'approved'       => 1,
			'rejected'       => 0,
			'queue_size'     => 6,
		),
		'2026-11-10' => array(
			'fetched'        => 0,
			'queued'         => 0,
			'skipped_dedupe' => 0,
			'skipped_gate'   => 0,
			'skipped_other'  => 0,
			'approved'       => 3,
			'rejected'       => 2,
			'queue_size'     => 1,
		),
	),
);

sourceweb_assert( array( '2026-11-08', '2026-11-09', '2026-11-10' ), sourceweb_stats_date_range( 3, $sw_today ), 'date range: 3 ascending days ending today' );

$sw_series = sourceweb_stats_series( $sw_stats, 'fetched', 3, $sw_today );
sourceweb_assert( 3, count( $sw_series ), 'series: one point per day' );
sourceweb_assert( array( 10, 6, 0 ), array_map( static function ( $p ) { return $p['value']; }, $sw_series ), 'series: values in date order' );
sourceweb_assert( '2026-11-08', $sw_series[0]['date'], 'series: first date is the window start' );

$sw_series5 = sourceweb_stats_series( $sw_stats, 'fetched', 5, $sw_today );
sourceweb_assert( array( 0, 0, 10, 6, 0 ), array_map( static function ( $p ) { return $p['value']; }, $sw_series5 ), 'series: days with no bucket are zero-filled' );

$sw_totals = sourceweb_stats_totals( $sw_stats, 3, $sw_today );
sourceweb_assert( 16, $sw_totals['fetched'], 'totals: fetched summed over window' );
sourceweb_assert( 6, $sw_totals['queued'], 'totals: queued summed over window' );
sourceweb_assert( 6, $sw_totals['approved'], 'totals: approved summed over window' );

sourceweb_assert( 0.4, sourceweb_stats_dedupe_rate( $sw_stats, 3, $sw_today ), 'dedupe rate = dupes / (queued + dupes)' );
sourceweb_assert( 0.0, sourceweb_stats_dedupe_rate( array( 'days' => array() ), 3, $sw_today ), 'dedupe rate: 0 when nothing to measure' );

$sw_delta = sourceweb_stats_period_delta( $sw_stats, 'fetched', 3, $sw_today );
sourceweb_assert( 16, $sw_delta['current'], 'period delta: current window total' );
sourceweb_assert( 0, $sw_delta['previous'], 'period delta: previous window empty' );
sourceweb_assert( 16, $sw_delta['change'], 'period delta: change = current - previous' );

sourceweb_assert( 1, sourceweb_stats_queue_latest( $sw_stats, $sw_today ), 'queue latest: most recent snapshot' );
sourceweb_assert( 5, sourceweb_stats_queue_latest( $sw_stats, '2026-11-08' ), 'queue latest: respects the as-of date' );
sourceweb_assert( null, sourceweb_stats_queue_latest( array( 'days' => array() ), $sw_today ), 'queue latest: null when never recorded' );

$sw_trimmed = sourceweb_stats_trim( $sw_stats, 1, $sw_today );
sourceweb_assert( array( '2026-11-09', '2026-11-10' ), array_keys( $sw_trimmed['days'] ), 'trim: days older than the floor are dropped' );

sourceweb_assert( 'skipped_dedupe', sourceweb_stats_skip_metric( 'duplicate' ), 'skip metric: duplicate -> dedupe' );
sourceweb_assert( 'skipped_gate', sourceweb_stats_skip_metric( 'blocked' ), 'skip metric: blocked -> gate' );
sourceweb_assert( 'skipped_gate', sourceweb_stats_skip_metric( 'filtered' ), 'skip metric: filtered -> gate' );
sourceweb_assert( 'skipped_other', sourceweb_stats_skip_metric( 'no title' ), 'skip metric: anything else -> other' );

echo $fails ? "\nFAILED: {$fails} of {$count} checks\n" : "OK: {$count} checks\n";
exit( $fails ? 1 : 0 );
