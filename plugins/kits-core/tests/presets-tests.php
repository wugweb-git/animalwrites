<?php
/**
 * Tests that need no WordPress (owner rule: no local WordPress or MySQL): the content-type presets (includes/presets.php).
 * Every preset is off by default and registers nothing; a switched-on preset registers its type with core category and
 * post_tag only, its fields with sanitize and auth callbacks, its binding keys on kits_post_fields and its schema on
 * kits_schema_map; the saved switch list keeps only known presets; testimonials show nothing before consent.
 *
 * Usage: php plugins/kits-core/tests/presets-tests.php
 *
 * @package kits-core
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

// phpcs:disable -- a command-line test runner with WordPress stubs.

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['t_hooks']    = array();
$GLOBALS['t_did']      = array();
$GLOBALS['t_options']  = array();
$GLOBALS['t_meta']     = array();
$GLOBALS['t_types']    = array();
$GLOBALS['t_cpts']     = array();
$GLOBALS['t_postmeta'] = array();
$GLOBALS['t_settings'] = array();
$GLOBALS['t_can']      = false;
$GLOBALS['t_pages']    = array();

function add_filter( $hook, $cb, $prio = 10, $args = 1 ) {
	$GLOBALS['t_hooks'][ $hook ][ $prio ][] = array( $cb, $args );
	return true;
}
function add_action( $hook, $cb, $prio = 10, $args = 1 ) {
	return add_filter( $hook, $cb, $prio, $args );
}
function apply_filters( $hook, $value, ...$rest ) {
	$all = $GLOBALS['t_hooks'][ $hook ] ?? array();
	ksort( $all );
	foreach ( $all as $cbs ) {
		foreach ( $cbs as $c ) {
			$value = call_user_func_array( $c[0], array_slice( array_merge( array( $value ), $rest ), 0, $c[1] ) );
		}
	}
	return $value;
}
function do_action( $hook, ...$rest ) {
	$GLOBALS['t_did'][ $hook ] = 1;
	apply_filters( $hook, ...array_merge( array( null ), $rest ) );
}
function did_action( $hook ) {
	return $GLOBALS['t_did'][ $hook ] ?? 0;
}
function __( $s ) {
	return $s;
}
function sanitize_key( $s ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) );
}
function sanitize_text_field( $s ) {
	return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) );
}
function sanitize_textarea_field( $s ) {
	return trim( strip_tags( (string) $s ) );
}
function esc_url_raw( $s ) {
	return preg_match( '#^https?://#i', (string) $s ) ? (string) $s : '';
}
function esc_url( $s ) {
	return str_replace( array( '"', '<', '>' ), '', (string) $s );
}
function esc_html( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( $s ) {
	return esc_html( $s );
}
function wp_kses_post( $s ) {
	return preg_replace( '#<script\b.*?</script>#is', '', (string) $s );
}
function absint( $n ) {
	return abs( (int) $n );
}
function get_option( $k, $d = false ) {
	$defaults = array(
		'date_format' => 'j M Y',
		'time_format' => 'H:i',
	);
	return $GLOBALS['t_options'][ $k ] ?? ( $defaults[ $k ] ?? $d );
}
function delete_option( $k ) {
	unset( $GLOBALS['t_options'][ $k ] );
	return true;
}
function get_post_meta( $id, $k ) {
	return $GLOBALS['t_meta'][ $id ][ $k ] ?? '';
}
function get_post_type( $id = 0 ) {
	return $GLOBALS['t_types'][ $id ?: ( $GLOBALS['t_current'] ?? 0 ) ] ?? 'post';
}
function is_singular() {
	return ! empty( $GLOBALS['t_current'] );
}
function is_admin() {
	return false;
}
function wp_doing_ajax() {
	return false;
}
function checked( $a, $b, $echo ) {
	return $a === $b ? ' checked=\'checked\'' : '';
}
function get_page_by_path( $path ) {
	return $GLOBALS['t_pages'][ $path ] ?? null;
}
class WP_Post {
	public $ID;
	public $post_type;
	public $post_status = 'publish';
	public $post_title  = '';
	public function __construct( $id, $type, $title = '' ) {
		$this->ID         = $id;
		$this->post_type  = $type;
		$this->post_title = $title;
	}
}
class WP_REST_Response {
	private $data;
	public function __construct( $data ) {
		$this->data = $data;
	}
	public function get_data() {
		return $this->data;
	}
	public function set_data( $data ) {
		$this->data = $data;
	}
}
function get_the_ID() {
	return 1;
}
function wp_get_attachment_url( $id ) {
	return 'https://example.test/uploads/' . (int) $id . '.png';
}
function wp_timezone() {
	return new DateTimeZone( 'UTC' );
}
function wp_date( $fmt, $ts ) {
	return gmdate( $fmt, $ts );
}
function wp_parse_url( $url, $part = -1 ) {
	return parse_url( $url, $part );
}
function current_user_can( $cap ) {
	return $GLOBALS['t_can'];
}
function register_post_type( $type, $args ) {
	$GLOBALS['t_cpts'][ $type ] = $args;
}
function register_post_meta( $type, $key, $args ) {
	$GLOBALS['t_postmeta'][ $type ][ $key ] = $args;
}
function register_setting( $group, $name, $args ) {
	$GLOBALS['t_settings'][ $name ] = array( $group, $args );
}
function register_block_bindings_source( $name, $args ) {
}

$GLOBALS['t_fail'] = 0;
function t_check( $ok, $name ) {
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $name . "\n";
	if ( ! $ok ) {
		$GLOBALS['t_fail'] = 1;
	}
}

require dirname( __DIR__ ) . '/includes/bindings.php';
require dirname( __DIR__ ) . '/includes/presets.php';

$all = array( 'kits-portfolio', 'kits-card', 'kits-infographic', 'kits-case-study', 'kits-event', 'kits-person', 'kits-testimonial' );

// Before init nothing is kept (labels are translated on init or later), then the seven are known.
t_check( $all === array_keys( kits_presets() ), 'seven presets, in order' );

// Off by default: init registers no type and no field, and no binding key or schema is added.
do_action( 'init' );
t_check( array() === kits_presets_enabled(), 'every preset off by default' );
t_check( array() === $GLOBALS['t_cpts'] && array() === $GLOBALS['t_postmeta'], 'off: no post type, no meta registered' );
t_check( array() === apply_filters( 'kits_post_fields', array() ), 'off: no binding keys' );
t_check( array() === apply_filters( 'kits_schema_map', array() ), 'off: no schema map' );
t_check( isset( $GLOBALS['t_settings']['kits_presets_enabled'] ) && 'kits_presets' === $GLOBALS['t_settings']['kits_presets_enabled'][0] && array() === $GLOBALS['t_settings']['kits_presets_enabled'][1]['default'], 'switch list registered with the Settings API, default empty' );

// Naming: kits_ types and keys, no aw, type slugs within 20 characters, only core category and post_tag.
foreach ( kits_presets() as $id => $p ) {
	$keys = array_keys( $p['fields'] );
	t_check( 0 === strpos( $p['post_type'], 'kits_' ) && strlen( $p['post_type'] ) <= 20, "$id: kits_ type slug, max 20" );
	t_check( ! preg_grep( '/(^|_)aw(_|$)/', array_merge( array( $id, $p['post_type'] ), $keys ) ), "$id: no aw in names" );
	t_check( array() === array_filter( $keys, fn( $k ) => 0 !== strpos( $k, $p['post_type'] . '_' ) ), "$id: field keys start with the type slug" );
	t_check( array( 'category', 'post_tag' ) === kits_preset_type_args( $p )['taxonomies'], "$id: core category and post_tag only" );
	t_check( '' !== ( $p['schema']['type'] ?? '' ) && array() === array_diff( array_values( $p['schema']['fields'] ?? array() ), $keys ), "$id: schema type set, its fields are the preset's keys" );
}
$own_tax                       = kits_presets()['kits-event'];
$own_tax['args']['taxonomies'] = array( 'post_tag', 'kits_event_type' );
t_check( array( 'post_tag' ) === kits_preset_type_args( $own_tax )['taxonomies'], 'a preset cannot attach a taxonomy of its own' );

// The saved list: known ids only, in the presets' order, each once.
t_check( array( 'kits-card', 'kits-event' ) === kits_presets_clean( array( 'kits-event', 'nope', 'kits-card', 'kits-event', array( 'x' ) ) ), 'saved list: known ids, ordered, unique' );
t_check( array() === kits_presets_clean( null ) && array() === kits_presets_clean( 'kits-event' ), 'saved list: nothing sent = all off' );

// Switch two on (as the settings screen saves them).
$GLOBALS['t_options']['kits_presets_enabled'] = array( 'kits-event', 'kits-testimonial' );
$GLOBALS['t_options']['rewrite_rules']        = array( 'old' );
do_action( 'update_option_kits_presets_enabled' );
t_check( ! isset( $GLOBALS['t_options']['rewrite_rules'] ), 'a change of the switches makes WordPress rebuild its rewrite rules' );
$GLOBALS['t_cpts'] = array();
apply_filters( 'init', null ); // The init callbacks again, with two on.
t_check( array( 'kits_event', 'kits_testimonial' ) === array_keys( $GLOBALS['t_cpts'] ), 'on: exactly the two types registered' );
$ev = $GLOBALS['t_cpts']['kits_event'];
t_check( true === $ev['show_in_rest'] && 'events' === $ev['rewrite']['slug'] && in_array( 'custom-fields', $ev['supports'], true ) && 'Events' === $ev['labels']['name'], 'event: REST, slug, custom-fields, labels' );
$m = $GLOBALS['t_postmeta']['kits_event']['kits_event_start'];
t_check( 'string' === $m['type'] && true === $m['single'] && true === $m['show_in_rest'] && is_callable( $m['sanitize_callback'] ) && is_callable( $m['auth_callback'] ), 'event start: native meta with sanitize and auth callbacks' );
t_check( '2026-12-01 18:30' === $m['sanitize_callback']( '2026-12-01T18:30' ) && '' === $m['sanitize_callback']( '2026-02-30' ) && '' === $m['sanitize_callback']( '2026-12-01 25:00' ) && '' === $m['sanitize_callback']( 'soon' ), 'datetime: kept when real, else empty' );
t_check( false === $m['auth_callback']( true, 'kits_event_start', 5 ), 'auth: needs edit_post' );
$url = $GLOBALS['t_postmeta']['kits_event']['kits_event_online_url']['sanitize_callback'];
t_check( 'https://x.test/a' === $url( ' https://x.test/a ' ) && '' === $url( 'javascript:alert(1)' ), 'url field: http(s) only' );
$consent = $GLOBALS['t_postmeta']['kits_testimonial']['kits_testimonial_consent'];
t_check( false === $consent['show_in_rest'], 'testimonial consent date: not in the REST API' );

// Binding keys and values.
$fields = kits_post_fields();
t_check( isset( $fields['kits_event_start'], $fields['kits_testimonial_quote'] ) && ! isset( $fields['kits_testimonial_consent'] ) && ! isset( $fields['kits_card_line'] ), 'binding keys: public fields of switched-on presets only' );
t_check( 'url' === $fields['kits_event_online_url']['type'] && 'text' === $fields['kits_event_start']['type'], 'binding types follow the format' );
$GLOBALS['t_types'][20] = 'kits_event';
$GLOBALS['t_meta'][20]  = array(
	'kits_event_start' => '2026-12-01 18:30',
	'kits_event_end'   => '2026-12-02',
	'kits_event_place' => 'Pune',
);
t_check( '1 Dec 2026 18:30' === kits_post_field_value( 'kits_event_start', 20 ) && '2 Dec 2026' === kits_post_field_value( 'kits_event_end', 20 ), 'dates shown in the site format (time only when stored)' );
t_check( 'Pune' === kits_post_field_output( 'kits_event_place', 20 ) && '' === kits_post_field_value( 'kits_event_online_url', 20 ), 'text shown; empty stays empty' );
$GLOBALS['t_types'][21] = 'post';
$GLOBALS['t_meta'][21]  = array( 'kits_event_place' => 'Leak' );
t_check( '' === kits_post_field_value( 'kits_event_place', 21 ), 'a key shows nothing on another type' );

// Testimonials: nothing shown before consent.
$GLOBALS['t_types'][30] = 'kits_testimonial';
$GLOBALS['t_meta'][30]  = array(
	'kits_testimonial_quote' => 'Great work.',
	'kits_testimonial_name'  => 'A. Person',
);
t_check( '' === kits_post_field_value( 'kits_testimonial_quote', 30 ) && '' === kits_post_field_value( 'kits_testimonial_name', 30 ), 'testimonial: hidden without consent' );
$GLOBALS['t_types'][31] = 'kits_testimonial';
$GLOBALS['t_meta'][31]  = $GLOBALS['t_meta'][30] + array( 'kits_testimonial_consent' => '2026-11-01' );
t_check( 'Great work.' === kits_post_field_value( 'kits_testimonial_quote', 31 ), 'testimonial: shown once consent is recorded' );

// Schema contract: only the switched-on types, Kits prints no JSON-LD.
$map = apply_filters( 'kits_schema_map', array() );
t_check( array( 'kits_event', 'kits_testimonial' ) === array_keys( $map ) && 'Event' === $map['kits_event']['type'] && 'kits_event_start' === $map['kits_event']['fields']['startDate'], 'schema map: switched-on types' );
t_check( in_array( 'itemReviewed', $map['kits_testimonial']['requires'], true ), 'schema: Review only with a reviewed item' );
t_check( 'Custom' === apply_filters( 'kits_schema_map', array( 'kits_event' => array( 'type' => 'Custom' ) ) )['kits_event']['type'], 'schema: a site\'s own map for a type wins' );
$src = (string) file_get_contents( dirname( __DIR__ ) . '/includes/presets.php' );
t_check( false === strpos( $src, 'application/ld+json' ) && false === strpos( $src, 'wp_head' ), 'no JSON-LD and no head output in the loader' );

// Schema values for machines: ISO 8601 dates, and the map says to read them that way.
t_check( '2026-12-01T18:30:00+00:00' === kits_preset_schema_value( 'kits_event_start', 20 ) && '2026-12-02' === kits_preset_schema_value( 'kits_event_end', 20 ), 'schema value: dates in ISO 8601' );
t_check( 'kits_preset_schema_value' === $map['kits_event']['value_callback'], 'schema map: value_callback set' );
t_check( '' === kits_preset_schema_value( 'kits_testimonial_quote', 30 ) && 'Great work.' === kits_preset_schema_value( 'kits_testimonial_quote', 31 ) && '' === kits_preset_schema_value( 'kits_testimonial_consent', 31 ), 'schema value: consent kept, private field never given' );

// Unconsented testimonials leave every query for people who may not edit them; editors keep them.
$q = array( new WP_Post( 30, 'kits_testimonial' ), new WP_Post( 31, 'kits_testimonial' ), new WP_Post( 20, 'kits_event' ) );
t_check( array( 31, 20 ) === array_map( fn( $p ) => $p->ID, apply_filters( 'the_posts', $q, null ) ), 'queries: unconsented testimonial left out for visitors' );
$GLOBALS['t_can'] = true;
t_check( 3 === count( apply_filters( 'the_posts', $q, null ) ), 'queries: editors still see it' );
$GLOBALS['t_can'] = false;

// A single item in REST: title, content, excerpt and fields blanked without consent.
$rest = new WP_REST_Response(
	array(
		'title'   => array( 'rendered' => 'A. Person' ),
		'content' => array(
			'rendered'  => '<blockquote>Great work.</blockquote>',
			'protected' => false,
		),
		'meta'    => array( 'kits_testimonial_quote' => 'Great work.', 'other' => 1 ),
	)
);
$out = apply_filters( 'rest_prepare_kits_testimonial', $rest, new WP_Post( 30, 'kits_testimonial' ) )->get_data();
t_check( '' === $out['title']['rendered'] && '' === $out['content']['rendered'] && false === $out['content']['protected'] && array( 'other' => 1 ) === $out['meta'], 'REST: unconsented item blanked' );
$ok = apply_filters( 'rest_prepare_kits_testimonial', new WP_REST_Response( array( 'title' => array( 'rendered' => 'B' ) ) ), new WP_Post( 31, 'kits_testimonial' ) )->get_data();
t_check( 'B' === $ok['title']['rendered'], 'REST: consented item untouched' );

// Display formats: a picture as its address, profile links as links.
$GLOBALS['t_options']['kits_presets_enabled'] = array( 'kits-card', 'kits-person' );
$GLOBALS['t_types'][40] = 'kits_person';
$GLOBALS['t_meta'][40]  = array(
	'kits_person_photo' => 12,
	'kits_person_links' => "https://www.example.org/a\nhttps://x.test/b",
);
$person = kits_presets()['kits-person'];
t_check( 'https://example.test/uploads/12.png' === kits_preset_field_display( $person, 'kits_person_photo', 40 ), 'attachment shown as its address' );
t_check( '<a href="https://www.example.org/a">example.org</a> · <a href="https://x.test/b">x.test</a>' === kits_preset_field_display( $person, 'kits_person_links', 40 ), 'profile links shown as links' );

// Cards: viewable for Query Loop, but no list page, noindex and out of the sitemap.
$card_args = kits_preset_type_args( kits_presets()['kits-card'] );
t_check( false === $card_args['has_archive'] && true === $card_args['exclude_from_search'], 'card: no list page, not in search' );
t_check( array( 'kits_card' ) === kits_presets_noindex_types(), 'card: noindex type' );
$GLOBALS['t_current']   = 41;
$GLOBALS['t_types'][41] = 'kits_card';
t_check( ! empty( apply_filters( 'wp_robots', array() )['noindex'] ) && array( 'post' => 1 ) === apply_filters( 'wp_sitemaps_post_types', array( 'post' => 1, 'kits_card' => 1 ) ), 'card: robots noindex and no sitemap' );
$GLOBALS['t_current'] = 0;

// An address a page already has: the page keeps it, the type gets no list page, and the screen says so.
$GLOBALS['t_pages']['people'] = new WP_Post( 50, 'page', 'Our people' );
$GLOBALS['t_cpts']            = array();
apply_filters( 'init', null );
t_check( false === $GLOBALS['t_cpts']['kits_person']['has_archive'], 'slug taken by a page: no archive' );
ob_start();
kits_presets_field( array( 'preset' => 'kits-person' ) );
$html = ob_get_clean();
t_check( false !== strpos( $html, 'address /people/' ) && false !== strpos( $html, 'The page &quot;Our people&quot; already has /people/' ), 'screen: address and the page that has it' );

// The switch when code overrides it: shown disabled; a stored "on" is kept by a hidden field.
add_filter( 'kits_presets_enabled', fn( $ids ) => array_values( array_diff( $ids, array( 'kits-person' ) ) ), 20 );
ob_start();
kits_presets_field( array( 'preset' => 'kits-person' ) );
$html = ob_get_clean();
t_check( false !== strpos( $html, ' disabled' ) && false !== strpos( $html, 'type="hidden" name="kits_presets_enabled[]" value="kits-person"' ) && false !== strpos( $html, 'switched off in this site' ), 'screen: code override shown, stored value kept' );
ob_start();
kits_presets_field( array( 'preset' => 'kits-card' ) );
t_check( false === strpos( ob_get_clean(), 'disabled' ), 'screen: a normal switch is not disabled' );
$GLOBALS['t_hooks']['kits_presets_enabled'] = array();
$GLOBALS['t_options']['kits_presets_enabled'] = array( 'kits-event', 'kits-testimonial' );

// Code can switch a preset on; a broken definition is dropped.
add_filter( 'kits_presets_enabled', fn( $ids ) => array_merge( $ids, array( 'kits-card', 'unknown' ) ) );
t_check( array( 'kits-card', 'kits-event', 'kits-testimonial' ) === kits_presets_enabled(), 'kits_presets_enabled: code adds a preset; unknown ids ignored' );
t_check( array() === kits_preset_normalise( 'x', array( 'post_type' => 'a_type_slug_that_is_too_long' ) ) && array() === kits_preset_normalise( 'x', array( 'post_type' => 'Bad Type' ) ), 'invalid post type slugs refused' );

exit( $GLOBALS['t_fail'] );
