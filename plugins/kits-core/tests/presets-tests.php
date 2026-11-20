<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
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
// An action as WordPress runs one: every callback gets the same arguments (apply_filters() above chains them).
function t_act( $hook, ...$args ) {
	$all = $GLOBALS['t_hooks'][ $hook ] ?? array();
	ksort( $all );
	foreach ( $all as $cbs ) {
		foreach ( $cbs as $c ) {
			call_user_func_array( $c[0], array_slice( $args, 0, $c[1] ) );
		}
	}
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
function update_option( $k, $v ) {
	$GLOBALS['t_options'][ $k ] = $v;
	return true;
}
function wp_json_encode( $v ) {
	return json_encode( $v );
}
function flush_rewrite_rules( $hard = true ) {
	$GLOBALS['t_flushes'] = ( $GLOBALS['t_flushes'] ?? 0 ) + 1;
}
function get_post_meta( $id, $k ) {
	return $GLOBALS['t_meta'][ $id ][ $k ] ?? '';
}
function update_post_meta( $id, $k, $v ) {
	$GLOBALS['t_meta'][ $id ][ $k ] = $v;
	return true;
}
function delete_post_meta( $id, $k ) {
	unset( $GLOBALS['t_meta'][ $id ][ $k ] );
	return true;
}
function get_post( $id ) {
	return isset( $GLOBALS['t_posts'][ $id ] ) ? $GLOBALS['t_posts'][ $id ] : null;
}
// get_posts(): published or scheduled items of a type without the meta key the consent sweep asks about.
function get_posts( $args ) {
	$key = $args['meta_query'][0]['key'];
	$ids = array();
	foreach ( $GLOBALS['t_posts'] ?? array() as $id => $p ) {
		if ( $p->post_type === $args['post_type'] && in_array( $p->post_status, $args['post_status'], true ) && '' === (string) ( $GLOBALS['t_meta'][ $id ][ $key ] ?? '' ) ) {
			$ids[] = $id;
		}
	}
	return array_slice( $ids, 0, $args['posts_per_page'] );
}
function get_post_type_object( $type ) {
	return (object) array( 'cap' => (object) array( 'publish_posts' => 'publish_' . $type ) );
}
// wp_update_post(): the stored post takes the new status, through the same filter and hooks as WordPress.
function wp_update_post( $arr ) {
	$post = $GLOBALS['t_posts'][ $arr['ID'] ];
	$data = apply_filters( 'wp_insert_post_data', array( 'post_type' => $post->post_type, 'post_status' => $arr['post_status'] ), $arr );
	$old  = $post->post_status;
	$post->post_status = $data['post_status'];
	$GLOBALS['t_updates'][] = array( $arr['ID'], $data['post_status'] );
	t_act( 'transition_post_status', $data['post_status'], $old, $post );
	t_act( 'save_post', $arr['ID'], $post );
	return $arr['ID'];
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
	return $GLOBALS['t_can'] || in_array( $cap, $GLOBALS['t_caps'] ?? array(), true );
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

// A site's own presets (kits_presets), for the shared-key and same-type tests (fix 7): a meetup type reusing the event's
// start key, and a second definition of kits_event, later in the list. Neither is switched on until those tests.
add_filter(
	'kits_presets',
	function ( $presets ) {
		$presets['site-meetup']    = array(
			'post_type' => 'site_meetup',
			'fields'    => array( 'kits_event_start' => array( 'label' => 'Start', 'format' => 'datetime' ) ),
			'schema'    => array( 'type' => 'Event', 'fields' => array( 'startDate' => 'kits_event_start' ) ),
		);
		$presets['site-event-two'] = array(
			'post_type' => 'kits_event',
			'fields'    => array( 'kits_event_start' => array( 'label' => 'Start', 'format' => 'datetime' ) ),
			'schema'    => array( 'type' => 'SocialEvent', 'fields' => array( 'startDate' => 'kits_event_start' ) ),
		);
		return $presets;
	}
);

// Off by default: init registers no type and no field, and no binding key or schema is added.
do_action( 'init' );
t_check( array() === kits_presets_enabled(), 'every preset off by default' );
t_check( array() === $GLOBALS['t_cpts'] && array() === $GLOBALS['t_postmeta'], 'off: no post type, no meta registered' );
t_check( array() === apply_filters( 'kits_post_fields', array() ), 'off: no binding keys' );
t_check( array() === apply_filters( 'kits_schema_map', array() ), 'off: no schema map' );
do_action( 'wp_loaded' );
t_check( 0 === ( $GLOBALS['t_flushes'] ?? 0 ) && ! isset( $GLOBALS['t_options']['kits_presets_rewrite'] ), 'fix 5: all off: no rewrite flush and nothing written' );
t_check( empty( $GLOBALS['t_hooks']['the_posts'] ) && empty( $GLOBALS['t_hooks']['rest_prepare_kits_testimonial'] ), 'fixes 2 and 6: no output filtering, so WP-CLI, cron and the admin read items as stored' );
t_check( isset( $GLOBALS['t_settings']['kits_presets_enabled'] ) && 'kits_presets' === $GLOBALS['t_settings']['kits_presets_enabled'][0] && array() === $GLOBALS['t_settings']['kits_presets_enabled'][1]['default'], 'switch list registered with the Settings API, default empty' );

// Naming: kits_ types and keys, no aw, type slugs within 20 characters, only core category and post_tag.
foreach ( array_intersect_key( kits_presets(), array_flip( $all ) ) as $id => $p ) {
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
$GLOBALS['t_cpts'] = array();
apply_filters( 'init', null ); // The init callbacks again, with two on.
do_action( 'wp_loaded' );
t_check( 1 === $GLOBALS['t_flushes'] && '' !== $GLOBALS['t_options']['kits_presets_rewrite'], 'fix 5: switching types on rebuilds the rewrite rules once' );
do_action( 'wp_loaded' );
t_check( 1 === $GLOBALS['t_flushes'], 'fix 5: no rebuild while nothing changed' );
t_check( array( 'kits_event', 'kits_testimonial' ) === array_keys( $GLOBALS['t_cpts'] ), 'on: exactly the two types registered' );
$ev = $GLOBALS['t_cpts']['kits_event'];
t_check( true === $ev['show_in_rest'] && 'events' === $ev['rewrite']['slug'] && in_array( 'custom-fields', $ev['supports'], true ) && 'Events' === $ev['labels']['name'], 'event: REST, slug, custom-fields, labels' );
$m = $GLOBALS['t_postmeta']['kits_event']['kits_event_start'];
t_check( 'string' === $m['type'] && true === $m['single'] && true === $m['show_in_rest'] && is_callable( $m['sanitize_callback'] ) && is_callable( $m['auth_callback'] ), 'event start: native meta with sanitize and auth callbacks' );
t_check( '2026-12-01 18:30' === $m['sanitize_callback']( '2026-12-01T18:30' ) && '' === $m['sanitize_callback']( '2026-02-30' ) && '' === $m['sanitize_callback']( '2026-12-01 25:00' ) && '' === $m['sanitize_callback']( 'soon' ), 'datetime: kept when real, else empty' );
t_check( '2026-12-01 18:30' === $m['sanitize_callback']( '2026-12-01 18:30:00' ) && '2026-12-01' === $m['sanitize_callback']( '20261201' ) && '2026-12-01 18:30' === $m['sanitize_callback']( '01/12/2026 18:30' ) && '' === $m['sanitize_callback']( '2026-12-01 18:60' ), 'fix 3: datetime: SCF date-time and d/m/Y forms kept, normalised' );
$date = $GLOBALS['t_postmeta']['kits_testimonial']['kits_testimonial_consent']['sanitize_callback'];
t_check( '2026-11-01' === $date( '20261101' ) && '2026-11-01' === $date( '01/11/2026' ) && '2026-11-01' === $date( '1.11.2026' ) && '2026-11-01' === $date( '01-11-2026' ) && '2026-11-01' === $date( ' 2026-11-01 ' ) && '2026-11-01' === $date( '2026-11-01 10:00:00' ), 'fix 3: date: Ymd (SCF), d/m/Y, d.m.Y, d-m-Y and Y-m-d kept as Y-m-d' );
t_check( '2026-12-01 18:30' === $m['sanitize_callback']( '2026-12-01T18:30:00.000Z' ) && '2026-12-01 13:00' === $m['sanitize_callback']( '2026-12-01T18:30:00+05:30' ) && '' === $m['sanitize_callback']( '2026-02-30T10:00:00Z' ), 'review: ISO 8601 with a zone kept, in site time' );
t_check( '2026-12-01 18:30' === $m['sanitize_callback']( '01-12-26 18:30' ) && '2026-12-01 18:30' === $m['sanitize_callback']( '01-12-26: 18:30' ) && '2026-12-01 18:30' === $m['sanitize_callback']( '01:12:26 18:30' ) && '2026-12-01' === $date( '01/12/26' ), 'owner format: d-m-y H:i (and dd:mm:yy) accepted' );
t_check( '' === $date( '01-12/26' ) && '' === $date( '29-02-27' ), 'owner format: mixed separators or a day that does not exist = empty' );
t_check( '' === $date( '31/02/2026' ) && '' === $date( '2026-13-01' ) && '' === $date( 'soon' ) && '' === $date( '2026111' ), 'fix 3: date: not a real day = empty' );
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
t_check( '01-12-26 18:30' === kits_post_field_value( 'kits_event_start', 20 ) && '02-12-26' === kits_post_field_value( 'kits_event_end', 20 ), 'dates shown as d-m-y H:i (time only when stored)' );
$GLOBALS['t_types'][22] = 'kits_event';
$GLOBALS['t_meta'][22]  = array( 'kits_event_start' => '20261203' );
t_check( '03-12-26' === kits_post_field_value( 'kits_event_start', 22 ) && '2026-12-03' === kits_preset_schema_value( 'kits_event_start', 22 ), 'fix 3: a date stored in an SCF form is still shown and given to schema' );
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

// Fix 1: an unconsented testimonial cannot be published or scheduled. It is kept pending (a native status), so no
// query, REST list or X-WP-Total, sitemap (WordPress's or Rank Math's) or get_posts() caller can show it.
$ins = function ( $status, $id = 0, $meta = array() ) {
	$data = apply_filters( 'wp_insert_post_data', array( 'post_type' => 'kits_testimonial', 'post_status' => $status ), array( 'ID' => $id, 'meta_input' => $meta ) );
	kits_presets_held_take( $id );
	return $data['post_status'];
};
t_check( 'pending' === $ins( 'publish', 30 ) && 'pending' === $ins( 'future', 30 ) && 'pending' === $ins( 'publish' ), 'fix 1: publish and schedule held as pending without consent' );
t_check( 'publish' === $ins( 'publish', 31 ) && 'future' === $ins( 'future', 31 ) && 'publish' === $ins( 'publish', 0, array( 'kits_testimonial_consent' => '01/11/2026' ) ), 'fix 1: published once consent is stored or given on the same save' );
t_check( 'pending' === $ins( 'publish', 0, array( 'kits_testimonial_consent' => 'soon' ) ), 'fix 1: a value that is not a date is no consent' );
t_check( 'draft' === $ins( 'draft', 30 ) && 'private' === $ins( 'private', 30 ), 'fix 1: draft and private are left as asked' );
t_check( 'publish' === apply_filters( 'wp_insert_post_data', array( 'post_type' => 'kits_event', 'post_status' => 'publish' ), array() )['post_status'], 'fix 1: types without a consent field untouched' );

// The block editor saves the post first, then the meta box (SCF or Custom Fields) with the consent date: the publish
// asked for is remembered and done when consent arrives, by someone who may publish the type.
$p32                    = new WP_Post( 32, 'kits_testimonial' );
$GLOBALS['t_posts'][32] = $p32;
$GLOBALS['t_types'][32] = 'kits_testimonial';
$GLOBALS['t_meta'][32]  = array( 'kits_testimonial_quote' => 'Q' );
apply_filters( 'wp_insert_post_data', array( 'post_type' => 'kits_testimonial', 'post_status' => 'publish' ), array( 'ID' => 32 ) );
$p32->post_status = 'pending';
t_act( 'save_post', 32, $p32 );
t_check( 'publish' === get_post_meta( 32, '_kits_preset_held_status' ) && 'pending' === $p32->post_status, 'fix 1: the publish asked for is remembered on the pending item' );
apply_filters( 'wp_insert_post_data', array( 'post_type' => 'kits_testimonial', 'post_status' => 'publish' ), array( 'ID' => 0 ) ); // Held for a new post whose insert then fails.
$p37                    = new WP_Post( 37, 'kits_testimonial' );
$p37->post_status       = 'pending';
$GLOBALS['t_types'][37] = 'kits_testimonial';
t_act( 'save_post', 37, $p37, true );
t_check( '' === get_post_meta( 37, '_kits_preset_held_status' ), 'review: a hold for another (new) post is not given to an updated one' );
kits_presets_held_take( 0 );
$GLOBALS['t_meta'][32]['kits_testimonial_consent'] = '2026-11-01';
t_act( 'save_post', 32, $p32 );
t_check( 'pending' === $p32->post_status, 'fix 1: consent saved by someone who may not publish: it stays pending' );
$GLOBALS['t_caps'] = array( 'publish_kits_testimonial' );
t_act( 'save_post', 32, $p32 );
t_check( 'publish' === $p32->post_status && '' === get_post_meta( 32, '_kits_preset_held_status' ), 'fix 1: consent saved by a publisher: the held publish is done' );
$GLOBALS['t_meta'][33] = array( '_kits_preset_held_status' => 'publish' );
$GLOBALS['t_types'][33] = 'kits_testimonial';
$ins( 'draft', 33 );
t_check( '' === get_post_meta( 33, '_kits_preset_held_status' ), 'fix 1: saving as a draft forgets the held publish' );

// Published another way: a scheduled item reaching its time (wp_publish_post), or the consent date removed.
$p34                    = new WP_Post( 34, 'kits_testimonial' );
$GLOBALS['t_posts'][34] = $p34;
$GLOBALS['t_types'][34] = 'kits_testimonial';
$p34->post_status = 'future';
t_act( 'publish_future_post', 34 );
t_check( 'pending' === $p34->post_status, 'review: a schedule reaching its time without consent goes to pending before it is published' );
$p34->post_status = 'publish';
t_act( 'transition_post_status', 'publish', 'future', $p34 );
t_check( 'pending' === $p34->post_status, 'fix 1: published by other code without consent: back to pending' );
$p35                    = new WP_Post( 35, 'kits_testimonial' );
$GLOBALS['t_posts'][35] = $p35;
$GLOBALS['t_types'][35] = 'kits_testimonial';
$GLOBALS['t_meta'][35]  = array();
t_act( 'deleted_post_meta', array( 1 ), 35, 'kits_testimonial_quote' );
t_check( 'publish' === $p35->post_status, 'fix 1: another field removed: untouched' );
t_act( 'deleted_post_meta', array( 1 ), 35, 'kits_testimonial_consent' );
t_check( 'pending' === $p35->post_status, 'fix 1: consent date removed from a published item: back to pending' );
$p36                    = new WP_Post( 36, 'kits_testimonial' );
$GLOBALS['t_posts'][36] = $p36;
$GLOBALS['t_types'][36] = 'kits_testimonial';
t_act( 'before_delete_post', 36 );
t_act( 'deleted_post_meta', array( 1 ), 36, 'kits_testimonial_consent' );
t_check( 'publish' === $p36->post_status, 'fix 1: a post being deleted is not unpublished on the way' );
$GLOBALS['t_caps'] = array();

// Review: items published before the rule held for them are swept to pending once, when the consent fields change.
$p38                    = new WP_Post( 38, 'kits_testimonial' );
$p39                    = new WP_Post( 39, 'kits_testimonial' );
$GLOBALS['t_posts']     = array( 38 => $p38, 39 => $p39 );
$GLOBALS['t_types'][38] = 'kits_testimonial';
$GLOBALS['t_types'][39] = 'kits_testimonial';
$GLOBALS['t_meta'][38]  = array( 'kits_testimonial_quote' => 'Old' );
$GLOBALS['t_meta'][39]  = array( 'kits_testimonial_consent' => '2026-10-01' );
t_check( '' !== ( $GLOBALS['t_options']['kits_presets_consent'] ?? '' ), 'review: consent fields recorded when testimonials were switched on' );
unset( $GLOBALS['t_options']['kits_presets_consent'] ); // Kits Core deactivated and back on (kits-core.php forgets it).
do_action( 'wp_loaded' );
t_check( 'pending' === $p38->post_status && 'publish' === $p39->post_status && '' !== $GLOBALS['t_options']['kits_presets_consent'], 'review: sweep sends the unconsented published item to pending, keeps the consented one' );
$p38->post_status = 'publish';
do_action( 'wp_loaded' );
t_check( 'publish' === $p38->post_status, 'review: no sweep while the consent fields are unchanged' );
$p38->post_status = 'pending';

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
$rm = apply_filters( 'rank_math/frontend/robots', array( 'index' => 'index', 'follow' => 'follow' ) );
t_check( 'noindex' === $rm['index'] && 'follow' === $rm['follow'], 'fix 4: card: Rank Math robots noindex' );
t_check( true === apply_filters( 'rank_math/sitemap/exclude_post_type', false, 'kits_card' ) && false === apply_filters( 'rank_math/sitemap/exclude_post_type', false, 'post' ), 'fix 4: card: out of the Rank Math sitemap, other types kept' );
$GLOBALS['t_current'] = 20;
t_check( 'index' === apply_filters( 'rank_math/frontend/robots', array( 'index' => 'index' ) )['index'], 'fix 4: other types: Rank Math robots untouched' );
$GLOBALS['t_current'] = 0;

// An address a page already has: the page keeps it, the type gets no list page, and the screen says so.
$GLOBALS['t_pages']['people'] = new WP_Post( 50, 'page', 'Our people' );
$GLOBALS['t_cpts']            = array();
apply_filters( 'init', null );
t_check( false === $GLOBALS['t_cpts']['kits_person']['has_archive'], 'slug taken by a page: no archive' );
$flushes = $GLOBALS['t_flushes'];
do_action( 'wp_loaded' );
t_check( $flushes + 1 === $GLOBALS['t_flushes'], 'fix 5: other types switched on and a page clash: rules rebuilt' );
unset( $GLOBALS['t_pages']['people'] );
apply_filters( 'init', null );
do_action( 'wp_loaded' );
t_check( $flushes + 2 === $GLOBALS['t_flushes'] && true === $GLOBALS['t_cpts']['kits_person']['has_archive'], 'fix 5: the page left the address: rechecked live, archive back, rules rebuilt' );
$GLOBALS['t_pages']['people'] = new WP_Post( 50, 'page', 'Our people' );
apply_filters( 'init', null );
do_action( 'wp_loaded' );
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

// Fix 7: a key two presets share is read from the post's own type; two presets of one type: the later one wins.
$GLOBALS['t_options']['kits_presets_enabled'] = array( 'kits-event', 'site-meetup' );
$GLOBALS['t_types'][60] = 'site_meetup';
$GLOBALS['t_meta'][60]  = array( 'kits_event_start' => '2026-12-05 10:00' );
t_check( '2026-12-05T10:00:00+00:00' === kits_preset_schema_value( 'kits_event_start', 60 ) && '2026-12-01T18:30:00+00:00' === kits_preset_schema_value( 'kits_event_start', 20 ), 'fix 7: shared key: schema value from each post\'s own type, either order' );
t_check( '05-12-26 10:00' === kits_post_field_value( 'kits_event_start', 60 ), 'fix 7: shared key: the binding shows the second type\'s value' );
$GLOBALS['t_options']['kits_presets_enabled'] = array( 'kits-event', 'site-event-two' );
$map = apply_filters( 'kits_schema_map', array() );
t_check( 'SocialEvent' === $map['kits_event']['type'] && '' === kits_preset_schema_value( 'kits_event_place', 20 ), 'fix 7: two presets of one type: the later one wins (schema and fields)' );
t_check( 'Custom' === apply_filters( 'kits_schema_map', array( 'kits_event' => array( 'type' => 'Custom' ) ) )['kits_event']['type'], 'fix 7: a site\'s own map still wins over every preset' );
$GLOBALS['t_options']['kits_presets_enabled'] = array( 'kits-event', 'kits-testimonial' );

// Code can switch a preset on; a broken definition is dropped.
add_filter( 'kits_presets_enabled', fn( $ids ) => array_merge( $ids, array( 'kits-card', 'unknown' ) ) );
t_check( array( 'kits-card', 'kits-event', 'kits-testimonial' ) === kits_presets_enabled(), 'kits_presets_enabled: code adds a preset; unknown ids ignored' );
t_check( array() === kits_preset_normalise( 'x', array( 'post_type' => 'a_type_slug_that_is_too_long' ) ) && array() === kits_preset_normalise( 'x', array( 'post_type' => 'Bad Type' ) ), 'invalid post type slugs refused' );

exit( $GLOBALS['t_fail'] );
