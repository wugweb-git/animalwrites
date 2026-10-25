<?php
/**
 * Sourcing restructure, phase 3 (Rules; owner, 25 Oct 2026): the WordPress side of the Rules model (rules-model.php).
 *
 * - Sections come from WordPress: the registered public post types (not page or attachment) and the ones that opt in
 *   with add_post_type_support( $type, 'aw-section' ). Media & PR (aw_press, not public) opts in here (decision 12).
 * - The sourcing policy gains sections_v2 (one entry per section: post type, label, the workers' item type, allowed
 *   categories and tags, the tag rules it may use, the summary rule it follows) and schema.rules = 2.
 * - GET aw/v1/summary-rules gains by_type; GET aw/v1/enrich/config gains schema, priority and field_rules.
 * - All of it from the phase 3 migration on (wp aw-sourcing migrate --phase=3); before it, the site and its payloads are
 *   as they were.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The option holding the Directory enrichment field rules and trust order. */
const AW_ENRICH_RULES_OPTION = 'aw_enrich_field_rules';

/** The ACF field group of a listing's details. */
const AW_ENRICH_LISTING_GROUP = 'group_aw_listing';

// Media & PR is a section (owner's decision 12): it is not public, so it opts in. After press.php registers it.
add_action(
	'init',
	function () {
		if ( post_type_exists( 'aw_press' ) ) {
			add_post_type_support( 'aw_press', 'aw-section' );
		}
	},
	20
);

/** Whether the phase 3 migration has run on this site. */
function aw_rules_live() {
	return function_exists( 'aw_sourcing_phase' ) && aw_sourcing_phase() >= 3;
}

/**
 * The sections: post type => [label, the workers' item type ('' = the workers never file items there)]. From the
 * phase 3 migration, the site's content types (public, or opted in); before it, the six of aw_post_types() as before.
 *
 * @param bool $from_types The content types whatever the phase (the migration's dry run).
 * @return array<string, array{label: string, worker_type: string}>
 */
function aw_rules_sections( $from_types = false ) {
	static $memo = array();
	$live = $from_types || aw_rules_live();
	if ( isset( $memo[ (int) $live ] ) && did_action( 'init' ) ) {
		return $memo[ (int) $live ];
	}
	$order = function_exists( 'aw_post_types' ) ? array_keys( aw_post_types() ) : array();
	$types = $live ? aw_rules_section_types( array_values( get_post_types( array( 'public' => true ) ) ), array_values( get_post_types_by_support( 'aw-section' ) ), $order ) : $order;
	$out   = array();
	foreach ( $types as $t ) {
		$obj       = get_post_type_object( $t );
		$own       = function_exists( 'aw_post_types' ) ? ( aw_post_types()[ $t ][1] ?? '' ) : '';
		$out[ $t ] = array(
			'label'       => '' !== $own ? $own : ( $obj ? (string) $obj->labels->name : $t ),
			'worker_type' => (string) ( AW_RULES_WORKER_TYPES[ $t ] ?? '' ),
		);
	}
	if ( did_action( 'init' ) ) {
		$memo[ (int) $live ] = $out;
	}
	return $out;
}

/**
 * Section post type => label.
 *
 * @param bool $from_types As aw_rules_sections().
 * @return array
 */
function aw_rules_section_labels( $from_types = false ) {
	return array_map( fn( $s ) => $s['label'], aw_rules_sections( $from_types ) );
}

/**
 * The address of a part of the Rules tab (before the phase 3 migration: the old tab).
 *
 * @param string $part sections, summary or enrich.
 * @param array  $args More query arguments.
 * @return string
 */
function aw_rules_url( $part = 'sections', array $args = array() ) {
	$q = aw_rules_live() ? array(
		'tab'  => 'rules',
		'part' => $part,
	) : array( 'tab' => $part );
	return add_query_arg( array_merge( array( 'page' => 'aw-sourcing' ), $q, $args ), admin_url( 'options-general.php' ) );
}

/* ---------- Summary rules by post type ---------- */

/**
 * Each section's summary rule (post type => rule), resolved: the old sections by their post type, and every other
 * section with its own rule or the inherited News rule.
 *
 * @return array
 */
function aw_rules_summary_by_type() {
	$saved = get_option( AW_SUMMARY_RULES_OPTION );
	return aw_rules_summary_resolve( aw_summary_rules()['sections'], is_array( $saved ) ? (array) ( $saved['sections'] ?? array() ) : array(), aw_rules_section_labels() );
}

/* ---------- Directory enrichment field rules and schema ---------- */

/** The enrichment source keys. */
function aw_enrich_source_keys() {
	return array_keys( aw_enrich_sources() );
}

/**
 * The field rules and trust order in force: the option from phase 3, else the seed (today's constants).
 * Every stored rule is read through the same checks as a form.
 *
 * @return array{priority: string[], fields: array}
 */
function aw_enrich_field_rules() {
	static $memo = array();
	$saved = aw_rules_live() ? get_option( AW_ENRICH_RULES_OPTION ) : false; // before phase 3 (or after its rollback): the code's rules
	$key   = md5( (string) wp_json_encode( $saved ) );
	if ( isset( $memo[ $key ] ) ) {
		return $memo[ $key ]; // read once per request (the results endpoint asks for each proposal)
	}
	$seed = aw_enrich_field_rules_seed( array_keys( aw_enrich_fields() ) );
	if ( ! is_array( $saved ) || empty( $saved['fields'] ) ) {
		$memo[ $key ] = $seed;
		return $seed;
	}
	$src = aw_enrich_source_keys();
	$out = array(
		'priority' => array_values( array_intersect( array_map( 'strval', (array) ( $saved['priority'] ?? array() ) ), $src ) ),
		'fields'   => array(),
	);
	if ( ! $out['priority'] ) {
		$out['priority'] = $seed['priority'];
	}
	foreach ( (array) $saved['fields'] as $f => $r ) {
		$base                = $seed['fields'][ $f ] ?? aw_enrich_rule_off();
		$out['fields'][ $f ] = aw_enrich_rule_clean( (string) $f, (array) $r, $src, $base );
	}
	foreach ( $seed['fields'] as $f => $r ) { // an enrichment field added in code later: its seeded rule
		if ( ! isset( $out['fields'][ $f ] ) ) {
			$out['fields'][ $f ] = $r;
		}
	}
	$memo[ $key ] = $out;
	return $out;
}

/**
 * One field's rule (the seed for an enrichment field without one; off for any other field).
 *
 * @param string $field Field key.
 * @return array
 */
function aw_enrich_field_rule( $field ) {
	$rules = aw_enrich_field_rules()['fields'];
	if ( isset( $rules[ $field ] ) ) {
		return $rules[ $field ];
	}
	return isset( aw_enrich_fields()[ $field ] ) ? aw_enrich_rule_seed( $field ) : aw_enrich_rule_off();
}

/**
 * The listing field group's fields: from ACF (Secure Custom Fields) when it is active, so a field added in wp-admin is
 * there; else from the group's local JSON.
 *
 * @return array
 */
function aw_enrich_listing_acf_fields() {
	if ( function_exists( 'acf_get_fields' ) ) {
		$f = acf_get_fields( AW_ENRICH_LISTING_GROUP );
		if ( is_array( $f ) && $f ) {
			return $f;
		}
	}
	$file = ( defined( 'AW_CORE_DIR' ) ? AW_CORE_DIR : WP_PLUGIN_DIR . '/animal-writes-core/' ) . 'acf-json/' . AW_ENRICH_LISTING_GROUP . '.json';
	$json = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions -- a local file
	return is_array( $json ) ? (array) ( $json['fields'] ?? array() ) : array();
}

/** The Directory schema (rules-model.php aw_enrich_schema_build) for enrich/config. */
function aw_enrich_schema() {
	$secs  = function_exists( 'aw_sources_config' ) ? (array) ( aw_sources_config()['sections'] ?? array() ) : array();
	$types = (array) ( $secs['aw_listing']['tags'] ?? array() );
	return aw_enrich_schema_build( aw_enrich_listing_acf_fields(), aw_enrich_fields(), AW_ENRICH_LISTING_GROUP, $types, defined( 'AW_SERVICES' ) ? AW_SERVICES : array() );
}

/* ---------- The sourcing policy: sections_v2 ---------- */

/**
 * One entry per section for the workers (beside the v1 sections dict, built from the same option).
 *
 * @return array[]
 */
function aw_rules_sections_v2() {
	$c    = aw_sources_config();
	$secs = (array) ( $c['sections'] ?? array() );
	$tags = (array) ( $c['topic_tags'] ?? array() );
	$by   = aw_rules_summary_by_type();
	$out  = array();
	foreach ( aw_rules_sections() as $t => $s ) {
		$allowed_tags = array_values( array_map( 'strval', (array) ( $secs[ $t ]['tags'] ?? array() ) ) );
		$out[]        = array(
			'post_type'   => $t,
			'label'       => $s['label'],
			'worker_type' => $s['worker_type'],
			'categories'  => array_values( array_map( 'strval', (array) ( $secs[ $t ]['categories'] ?? array() ) ) ),
			'tags'        => $allowed_tags,
			'tag_rules'   => array_values( array_intersect( array_map( 'strval', array_keys( $tags ) ), $allowed_tags ) ),
			'summary'     => '' !== (string) ( $by[ $t ]['inherit'] ?? '' ) ? (string) $by[ $t ]['inherit'] : $t,
		);
	}
	return $out;
}

add_filter(
	'kits_sourcing_policy',
	function ( $data ) {
		if ( ! aw_rules_live() || ! isset( $data['config'] ) || ! is_array( $data['config'] ) ) {
			return $data;
		}
		$schema                        = isset( $data['config']['schema'] ) && is_array( $data['config']['schema'] ) ? $data['config']['schema'] : array();
		$data['config']['schema']      = $schema + array( 'rules' => 2 );
		$data['config']['sections_v2'] = aw_rules_sections_v2();
		return $data;
	},
	20 // after the lists (sourcing-lists.php) build config
);

/*
 * Sources sync copies the summary rules and the Directory schema too (phase 3): a save of either starts it, as a save
 * of the lists does (sourcing-lists.php aw_sources_sync_soon).
 */
foreach ( array( 'aw_summary_rules', AW_ENRICH_RULES_OPTION ) as $aw_rules_sync_option ) {
	add_action( 'add_option_' . $aw_rules_sync_option, fn() => function_exists( 'aw_sources_sync_soon' ) && aw_rules_live() ? aw_sources_sync_soon() : null );
	add_action( 'update_option_' . $aw_rules_sync_option, fn() => function_exists( 'aw_sources_sync_soon' ) && aw_rules_live() ? aw_sources_sync_soon() : null );
}
unset( $aw_rules_sync_option );
