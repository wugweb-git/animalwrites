<?php
/**
 * Sourcing restructure, phase 3 (Rules; owner, 25 Oct 2026): the Rules model as plain functions over the stored arrays,
 * with no WordPress calls, so tests/run.php checks them without WordPress (owner rule: no local WordPress or MySQL).
 *
 * - Sections are the site's content types: every registered public post type except page and attachment, plus the
 *   ones that opt in with add_post_type_support( $type, 'aw-section' ) (aw_press). A new content type is a new section
 *   by itself: its section rule is empty (every category) and its summary rule inherits News.
 * - Section rules stay in aw_sources['sections'] (post type => categories, tags); the migration adds the missing types
 *   with empty lists. Tag rules stay in aw_sources['topic_tags'] (tag slug => any, hold, unless, hold_reason).
 * - Summary rules: aw_summary_rules['sections'] is keyed by post type from phase 3 (aw_news, aw_legal, post), each
 *   with its old name as alias (news, library, research), so aw_summary_rules() and the workers' v1 reader see the same
 *   rules. A section without a row of its own inherits News (inherit: aw_news).
 * - Directory enrichment field rules: option aw_enrich_field_rules {priority: [source keys], fields: {field: rule}},
 *   seeded from the workers' constants (aw_workers/directory/merge.py), which tests/fixtures/enrich_field_rules_seed.json
 *   holds in both repositories. A field rule: on, sources (which may fill it; [] = any), multi (0 = one value; n = up
 *   to n different values), official (the source whose value alone is high), ai (read by the AI: at most medium),
 *   invite (Invite to claim when only Google has it), social (a profile link), apply (the site's apply rule: empty =
 *   fill an empty field, both = keep it next to what is there, review = always a person).
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Summary rule sections of before phase 3, by the post type that now holds them. */
const AW_RULES_SUMMARY_ALIAS = array(
	'aw_news'  => 'news',
	'aw_legal' => 'library',
	'post'     => 'research',
);

/** The post type a section without its own summary rule inherits from. */
const AW_RULES_SUMMARY_INHERIT = 'aw_news';

/** The workers' item type for each post type they file items into (sources.py SITE_TYPES; aw_press: "file as press"). */
const AW_RULES_WORKER_TYPES = array(
	'aw_news'        => 'news',
	'aw_legal'       => 'legal',
	'post'           => 'research',
	'aw_opportunity' => 'opportunity',
	'aw_press'       => 'press',
);

/** Post types that are never sections, even when public. */
const AW_RULES_NOT_SECTIONS = array( 'page', 'attachment' );

/*
 * The workers' constants (aw_workers/directory/merge.py, 25 Oct 2026): the seed of the field rules, and what the
 * workers use when the site sends none.
 */
const AW_ENRICH_SEED_MULTI    = array(
	'phone'     => 2,
	'whatsapp'  => 1,
	'emergency' => 1,
);
const AW_ENRICH_SEED_AI       = array( 'services', 'animals', 'areas_served', 'description', 'reg_type', 'head' );
const AW_ENRICH_SEED_PRIORITY = array( 'claim', 'website', 'darpan', 'wikidata', 'osm', 'social', 'awbi', 'google_places' );
const AW_ENRICH_SEED_INVITE   = array( 'phone', 'hours', 'address', 'website' );
const AW_ENRICH_SEED_SOCIAL   = array( 'instagram', 'facebook', 'x', 'youtube', 'linkedin' );
const AW_ENRICH_SEED_OFFICIAL = array(
	'address' => 'awbi',
	'pincode' => 'awbi',
);

/** Phone fields (one list on the listing), and fields that can keep a second value (enrichment.php AW_ENRICH_ALT, list fields). */
const AW_ENRICH_PHONE_FIELDS = array( 'phone', 'whatsapp', 'emergency' );
const AW_ENRICH_TWO_FIELDS   = array( 'phone', 'whatsapp', 'emergency', 'email', 'website', 'services', 'animals', 'areas_served' );

/** Fields a person always decides (enrichment.php aw_enrich_mode before phase 3). */
const AW_ENRICH_REVIEW_FIELDS = array( 'closed', 'merge_into' );

/** Special stores kept in a listing field by code: store => the ACF field (Phone numbers, Opening hours, Photos). */
const AW_ENRICH_SPECIAL_ACF = array(
	'phones' => 'aw_phones',
	'hours'  => 'aw_opening_hours',
	'photos' => 'aw_gallery',
);

/**
 * ACF field types enrichment can write into a listing field added in wp-admin (one text value in post meta, as the
 * built-in fields are written). Repeaters, galleries, files, images, choices and relations are left to people.
 */
const AW_ENRICH_ACF_WRITABLE = array( 'text', 'textarea', 'url', 'email', 'number', 'date_picker' );

/**
 * Why a listing field cannot be filled by enrichment ('' when it can).
 *
 * @param string $type ACF field type.
 * @return string
 */
function aw_enrich_acf_not_writable( $type ) {
	return in_array( (string) $type, AW_ENRICH_ACF_WRITABLE, true ) ? '' : sprintf( 'a %s field cannot be filled by enrichment (only text, text area, URL, email, number and date fields)', str_replace( '_', ' ', (string) $type ) );
}

/**
 * A value for a listing field from the field group, cleaned by its ACF type ('' when it is not usable). Dates are
 * stored as ACF stores them (Ymd). Uses WordPress's sanitizers (stubbed in tests/rules-tests.php).
 *
 * @param string $type  ACF field type.
 * @param string $value Value.
 * @return string
 */
function aw_enrich_acf_value( $type, $value ) {
	$value = trim( (string) $value );
	switch ( $type ) {
		case 'url':
			return (string) esc_url_raw( $value );
		case 'email':
			return (string) sanitize_email( $value );
		case 'number':
			return is_numeric( $value ) ? (string) ( $value + 0 ) : '';
		case 'date_picker':
			return preg_match( '/^(\d{4})-?(\d{2})-?(\d{2})$/', $value, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $m[1] . $m[2] . $m[3] : '';
		case 'textarea':
			return sanitize_textarea_field( $value );
		case 'text':
			return sanitize_text_field( $value );
		default:
			return ''; // not a type enrichment writes
	}
}

/**
 * A proposal's sources that its field rule allows: all of them when the rule names none, else only the named ones
 * (Google too: a place ID or Maps link from Google is kept only when the rule allows Google).
 *
 * @param array    $srcs  Sources ({key, ...} each).
 * @param string[] $allow The rule's sources ([] = any).
 * @return array
 */
function aw_enrich_allowed_sources( array $srcs, array $allow ) {
	if ( ! $allow ) {
		return $srcs;
	}
	return array_values( array_filter( $srcs, fn( $s ) => in_array( (string) ( $s['key'] ?? '' ), $allow, true ) ) );
}

/** The apply rules a field rule may have. */
const AW_ENRICH_APPLY = array(
	'empty'  => 'Fill an empty field (a value already there waits for review)',
	'both'   => 'Keep it next to the value already there (both unverified)',
	'review' => 'Always wait for review',
);

/**
 * The sections in order: the site's own content types first (aw_post_types() order), then any other public type,
 * then the opted-in ones.
 *
 * @param string[] $public Public post types (get_post_types( public )).
 * @param string[] $opt_in Post types with aw-section support.
 * @param string[] $order  Preferred order (the site's own types).
 * @return string[]
 */
function aw_rules_section_types( array $public, array $opt_in, array $order = array() ) {
	$all = array_values( array_unique( array_merge( array_diff( $public, AW_RULES_NOT_SECTIONS ), $opt_in ) ) );
	$out = array_values( array_intersect( $order, $all ) );
	foreach ( $all as $t ) {
		if ( ! in_array( $t, $out, true ) ) {
			$out[] = $t;
		}
	}
	return $out;
}

/**
 * The summary rule sections keyed by post type: legacy names (news, library, research) become their post type with
 * the old name as alias; every value is kept as stored (sentence counts too). A key that is already a post type, or
 * that has no post type, stays as it is.
 *
 * @param array $sections aw_summary_rules['sections'].
 * @return array
 */
function aw_rules_summary_by_post_type( array $sections ) {
	$by_alias = array_flip( AW_RULES_SUMMARY_ALIAS );
	$out      = array();
	foreach ( $sections as $name => $sec ) {
		if ( isset( $by_alias[ $name ] ) && is_array( $sec ) && ! isset( $sections[ $by_alias[ $name ] ] ) ) {
			$out[ $by_alias[ $name ] ] = array( 'alias' => (string) $name ) + $sec;
		} else {
			$out[ $name ] = $sec;
		}
	}
	return $out;
}

/**
 * The summary rule sections as they were before phase 3 (keyed by the old names, without alias): what verify compares
 * with the backup and rollback puts back. Sections of new post types (no alias) are dropped.
 *
 * @param array $sections aw_summary_rules['sections'].
 * @return array
 */
function aw_rules_summary_legacy( array $sections ) {
	$out = array();
	foreach ( $sections as $name => $sec ) {
		if ( is_array( $sec ) && isset( $sec['alias'] ) && isset( AW_RULES_SUMMARY_ALIAS[ $name ] ) ) {
			$alias = (string) $sec['alias'];
			unset( $sec['alias'] );
			$out[ $alias ] = $sec;
		} elseif ( ! isset( AW_RULES_SUMMARY_ALIAS[ $name ] ) && in_array( $name, AW_RULES_SUMMARY_ALIAS, true ) ) {
			$out[ $name ] = $sec; // still a legacy key
		}
	}
	return $out;
}

/**
 * Each section's summary rule, resolved: aliased sections take the rule in force for their old name; a section with
 * its own stored rule (inherit '') has it over News; any other inherits News.
 *
 * @param array    $v1     aw_summary_rules()['sections'] (news, library, research), the rules in force.
 * @param array    $stored aw_summary_rules option's 'sections' (by post type).
 * @param string[] $labels post type => label, for every section.
 * @return array post type => {label, min_words, max_words, structure, attribution, alias?|inherit?}
 */
function aw_rules_summary_resolve( array $v1, array $stored, array $labels ) {
	$out  = array();
	$base = $v1[ AW_RULES_SUMMARY_ALIAS[ AW_RULES_SUMMARY_INHERIT ] ] ?? array();
	foreach ( $labels as $pt => $label ) {
		if ( isset( AW_RULES_SUMMARY_ALIAS[ $pt ] ) && isset( $v1[ AW_RULES_SUMMARY_ALIAS[ $pt ] ] ) ) {
			$out[ $pt ] = aw_rules_summary_fields( $v1[ AW_RULES_SUMMARY_ALIAS[ $pt ] ] ) + array( 'alias' => AW_RULES_SUMMARY_ALIAS[ $pt ] );
			continue;
		}
		$own = is_array( $stored[ $pt ] ?? null ) ? $stored[ $pt ] : array();
		if ( $own && '' === (string) ( $own['inherit'] ?? AW_RULES_SUMMARY_INHERIT ) ) {
			$rule          = aw_rules_summary_fields( array_merge( $base, array_filter( $own, fn( $x ) => '' !== $x && array() !== $x ) ) );
			$rule['label'] = (string) ( $own['label'] ?? $label );
			$out[ $pt ]    = $rule + array( 'inherit' => '' );
			continue;
		}
		$out[ $pt ] = array_merge(
			aw_rules_summary_fields( $base ),
			array(
				'label'   => (string) $label,
				'inherit' => AW_RULES_SUMMARY_INHERIT,
			)
		);
	}
	return $out;
}

/**
 * The fields of a summary rule, as the workers read them (word counts as numbers, never a minimum above the maximum).
 *
 * @param array $sec A section's rule.
 * @return array
 */
function aw_rules_summary_fields( array $sec ) {
	$out = array(
		'label'       => (string) ( $sec['label'] ?? '' ),
		'min_words'   => (int) ( $sec['min_words'] ?? 0 ),
		'max_words'   => (int) ( $sec['max_words'] ?? 0 ),
		'structure'   => (string) ( $sec['structure'] ?? '' ),
		'attribution' => (string) ( $sec['attribution'] ?? '' ),
	);
	if ( $out['max_words'] > 0 ) {
		$out['min_words'] = min( $out['min_words'], $out['max_words'] );
	}
	return $out;
}

/**
 * A field's rule as the workers' constants give it (the seed).
 *
 * @param string $field Enrichment field key.
 * @return array
 */
function aw_enrich_rule_seed( $field ) {
	if ( in_array( $field, AW_ENRICH_REVIEW_FIELDS, true ) ) {
		$apply = 'review';
	} elseif ( in_array( $field, AW_ENRICH_TWO_FIELDS, true ) ) {
		$apply = 'both';
	} else {
		$apply = 'empty';
	}
	return array(
		'on'       => true,
		'sources'  => array(),
		'multi'    => (int) ( AW_ENRICH_SEED_MULTI[ $field ] ?? 0 ),
		'official' => (string) ( AW_ENRICH_SEED_OFFICIAL[ $field ] ?? '' ),
		'ai'       => in_array( $field, AW_ENRICH_SEED_AI, true ),
		'invite'   => in_array( $field, AW_ENRICH_SEED_INVITE, true ),
		'social'   => in_array( $field, AW_ENRICH_SEED_SOCIAL, true ),
		'apply'    => $apply,
	);
}

/**
 * The seeded option aw_enrich_field_rules: the trust order and one rule per enrichment field.
 *
 * @param string[] $fields Enrichment field keys (aw_enrich_fields()).
 * @return array
 */
function aw_enrich_field_rules_seed( array $fields ) {
	$out = array(
		'priority' => AW_ENRICH_SEED_PRIORITY,
		'fields'   => array(),
	);
	foreach ( $fields as $f ) {
		$out['fields'][ $f ] = aw_enrich_rule_seed( $f );
	}
	return $out;
}

/**
 * A rule for a field that is not an enrichment field (a field added to the listing field group in wp-admin): off.
 *
 * @return array
 */
function aw_enrich_rule_off() {
	return array(
		'on'       => false,
		'sources'  => array(),
		'multi'    => 0,
		'official' => '',
		'ai'       => false,
		'invite'   => false,
		'social'   => false,
		'apply'    => 'review',
	);
}

/**
 * A field rule from a form or the option, every value checked against what it may be.
 *
 * @param string   $field   Field key.
 * @param array    $in      Posted or stored rule.
 * @param string[] $sources Source keys (aw_enrich_sources()).
 * @param array    $base    The rule that missing values come from.
 * @return array
 */
function aw_enrich_rule_clean( $field, array $in, array $sources, array $base ) {
	$out = $base;
	foreach ( array( 'on', 'ai', 'invite', 'social' ) as $k ) {
		if ( array_key_exists( $k, $in ) ) {
			$out[ $k ] = (bool) $in[ $k ];
		}
	}
	if ( array_key_exists( 'multi', $in ) ) {
		$out['multi'] = max( 0, min( 5, (int) $in['multi'] ) );
	}
	if ( array_key_exists( 'official', $in ) ) {
		$out['official'] = in_array( (string) $in['official'], $sources, true ) ? (string) $in['official'] : '';
	}
	if ( array_key_exists( 'sources', $in ) ) {
		$out['sources'] = array_values( array_intersect( $sources, array_map( 'strval', (array) $in['sources'] ) ) );
	}
	if ( array_key_exists( 'apply', $in ) ) {
		$apply        = (string) $in['apply'];
		$out['apply'] = isset( AW_ENRICH_APPLY[ $apply ] ) ? $apply : $out['apply'];
	}
	if ( 'both' === $out['apply'] && ! in_array( $field, AW_ENRICH_TWO_FIELDS, true ) ) {
		$out['apply'] = 'empty'; // only phones, email, website and the list fields can keep two values
	}
	if ( in_array( $field, AW_ENRICH_REVIEW_FIELDS, true ) ) {
		$out['apply'] = 'review'; // closed and merge_into: always a person
	}
	return $out;
}

/**
 * The trust order from a form: the sources given a position, sorted by it (1 = trusted most; on a tie the source that
 * was moved comes first, then the order of before); a source with no position is left out of the order (the workers rank it after every listed source). An
 * empty result keeps the order as it is.
 *
 * @param array    $positions source key => position.
 * @param string[] $sources   Source keys.
 * @param string[] $current   The order now.
 * @return string[]
 */
function aw_enrich_priority_from_positions( array $positions, array $sources, array $current ) {
	$rank = array();
	foreach ( $sources as $i => $k ) {
		$p = isset( $positions[ $k ] ) ? trim( (string) $positions[ $k ] ) : '';
		if ( '' === $p || (int) $p < 1 ) {
			continue;
		}
		$was        = array_search( $k, $current, true );
		$moved      = false === $was || (int) $p !== (int) $was + 1; // a source given a new position goes before one already there
		$rank[ $k ] = array( (int) $p, $moved ? 0 : 1, false === $was ? 100 + $i : (int) $was );
	}
	if ( ! $rank ) {
		return array_values( $current );
	}
	uksort( $rank, fn( $a, $b ) => $rank[ $a ] <=> $rank[ $b ] );
	return array_keys( $rank );
}

/**
 * What the site does with a sourced value (enrichment.php aw_enrich_mode), by the field's apply rule. The listing's
 * facts are callables, so only what the decision needs is read.
 *
 * @param string   $field       Field key.
 * @param string   $apply       The field rule's apply (empty, both, review).
 * @param string   $conf        high, medium or low.
 * @param bool     $protected   A claimed or verified listing.
 * @param callable $is_empty    Whether the field is empty on the listing.
 * @param callable $has_phones  Whether the listing has a phone number.
 * @param callable $alt_empty   Whether the field's second-value slot is empty (email, website).
 * @param callable $place_fills Whether a place value fills the listing's place.
 * @return string apply, both or queue.
 */
function aw_enrich_mode_decide( $field, $apply, $conf, $protected, callable $is_empty, callable $has_phones, callable $alt_empty, callable $place_fills ) {
	if ( $protected || 'low' === $conf || 'review' === $apply || in_array( $field, AW_ENRICH_REVIEW_FIELDS, true ) ) {
		return 'queue'; // closed and merge_into: always a person, whatever the rule says (a merge is the owner's decision)
	}
	if ( 'place' === $field ) {
		return $place_fills() ? 'apply' : 'queue';
	}
	if ( in_array( $field, AW_ENRICH_PHONE_FIELDS, true ) ) {
		if ( ! $has_phones() ) {
			return 'apply';
		}
		return 'both' === $apply ? 'both' : 'queue';
	}
	if ( $is_empty() ) {
		return 'apply';
	}
	if ( 'both' === $apply && in_array( $field, array( 'services', 'animals', 'areas_served' ), true ) ) {
		return 'both'; // the value is the union: it only adds
	}
	if ( 'both' === $apply && in_array( $field, array( 'email', 'website' ), true ) && $alt_empty() ) {
		return 'both';
	}
	return 'queue';
}

/**
 * The Directory schema the workers get with enrich/config: the listing field group's fields (from ACF), the enrichment
 * fields kept in special stores, listing types and services.
 *
 * @param array  $acf_fields The group's fields (acf_get_fields or the local JSON): name, label, type, sub_fields.
 * @param array  $enrich     aw_enrich_fields(): key => [label, store, group].
 * @param string $group      Field group key.
 * @param array  $types      Listing types (tag slugs allowed for aw_listing).
 * @param array  $services   Service slug => name.
 * @return array
 */
function aw_enrich_schema_build( array $acf_fields, array $enrich, $group, array $types, array $services ) {
	$by_store = array(); // listing field name => the enrichment fields that fill it
	foreach ( $enrich as $k => $e ) {
		$store               = (string) $e[1];
		$name                = AW_ENRICH_SPECIAL_ACF[ $store ] ?? $store;
		$by_store[ $name ][] = $k;
	}
	$fields = array();
	foreach ( $acf_fields as $f ) {
		$name = (string) ( $f['name'] ?? '' );
		if ( '' === $name || in_array( $f['type'] ?? '', array( 'tab', 'message', 'accordion' ), true ) ) {
			continue;
		}
		$fields[] = array(
			'name'    => $name,
			'label'   => (string) ( $f['label'] ?? $name ),
			'type'    => (string) ( $f['type'] ?? '' ),
			'repeats' => in_array( $f['type'] ?? '', array( 'repeater', 'gallery', 'flexible_content' ), true ) || ! empty( $f['multiple'] ),
			'enrich'  => (array) ( $by_store[ $name ] ?? array() ),
		);
	}
	$names   = array_column( $fields, 'name' );
	$special = array();
	foreach ( $enrich as $k => $e ) {
		if ( ! in_array( (string) $e[1], $names, true ) ) {
			$special[] = array(
				'field' => $k,
				'label' => (string) $e[0],
				'store' => (string) $e[1],
			);
		}
	}
	return array(
		'group'         => (string) $group,
		'fields'        => $fields,
		'special'       => $special,
		'listing_types' => array_values( array_map( 'strval', $types ) ),
		'services'      => $services,
	);
}

/**
 * Every row of Field rules: each enrichment field with its rule, then each listing field no enrichment field fills
 * (its rule off until set).
 *
 * @param array $schema aw_enrich_schema_build().
 * @param array $enrich aw_enrich_fields().
 * @param array $rules  aw_enrich_field_rules()['fields'].
 * @return array field key => {label, group, store, acf, rule, set}
 */
function aw_enrich_field_rule_rows( array $schema, array $enrich, array $rules ) {
	$acf  = array();
	$rows = array();
	foreach ( $schema['fields'] as $f ) {
		$acf[ $f['name'] ] = $f;
	}
	foreach ( $enrich as $k => $e ) {
		$rows[ $k ] = array(
			'label' => (string) $e[0],
			'group' => (string) $e[2],
			'store' => (string) $e[1],
			'acf'   => isset( $acf[ AW_ENRICH_SPECIAL_ACF[ $e[1] ] ?? $e[1] ] ),
			'rule'  => isset( $rules[ $k ] ) ? $rules[ $k ] : aw_enrich_rule_seed( $k ),
			'set'   => isset( $rules[ $k ] ),
		);
	}
	foreach ( $schema['fields'] as $f ) {
		if ( $f['enrich'] || isset( $rows[ $f['name'] ] ) ) {
			continue;
		}
		$rows[ $f['name'] ] = array(
			'label' => $f['label'],
			'group' => 'Listing field',
			'store' => $f['name'],
			'acf'   => true,
			'rule'  => isset( $rules[ $f['name'] ] ) && '' === aw_enrich_acf_not_writable( $f['type'] ) ? $rules[ $f['name'] ] : aw_enrich_rule_off(),
			'set'   => isset( $rules[ $f['name'] ] ),
			'type'  => $f['type'],
			'why'   => aw_enrich_acf_not_writable( $f['type'] ),
		);
	}
	return $rows;
}

/**
 * Whether a hold pattern compiles and the workers (Python re) can use it. PCRE-only syntax is refused: the escapes
 * h, H, R, K, X, p, P, Q, E, G and z, possessive quantifiers, branch resets, atomic groups and verbs.
 *
 * @param string $p Pattern.
 * @return bool
 */
function aw_rules_pattern_ok( $p ) {
	if ( preg_match( '/[+*?}]\\+|\\(\\?[|>R0-9&+-]|\\(\\?P>|\\(\\?<(?![=!])|\\(\\*/', $p ) ) {
		return false;
	}
	// Escapes: only what Python re compiles (Codex, #77). A letter escape must be one Python knows; \x takes exactly
	// two hex digits, \u four, \U eight (so PCRE's \x{1F600}, \o{..}, \cX, \e, \g, \k and \N without {name} fail).
	if ( preg_match_all( '/\\\\(.)/su', $p, $m, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $m[1] as $e ) {
			$c    = $e[0];
			$rest = substr( $p, $e[1] + strlen( $c ) );
			if ( ! ctype_alpha( $c ) ) {
				continue; // a digit (group reference) or an escaped symbol
			}
			if ( 'x' === $c ) {
				$ok = (bool) preg_match( '/^[0-9a-fA-F]{2}/', $rest );
			} elseif ( 'u' === $c ) {
				$ok = (bool) preg_match( '/^[0-9a-fA-F]{4}/', $rest );
			} elseif ( 'U' === $c ) {
				$ok = (bool) preg_match( '/^[0-9a-fA-F]{8}/', $rest );
			} elseif ( 'N' === $c ) {
				$ok = (bool) preg_match( '/^\\{[A-Z0-9 \\-]+\\}/', $rest );
			} else {
				$ok = false !== strpos( 'AbBdDsSwWZafnrtv', $c );
			}
			if ( ! $ok ) {
				return false;
			}
		}
	}
	return false !== @preg_match( "\x01" . $p . "\x01iu", '' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- testing whether a pattern compiles
}

/**
 * A tag rule from the form: phrases, hold patterns (each a valid regular expression), unless words, the hold reason.
 *
 * @param array $in Posted values (any, hold, unless as text, one per line; hold_reason).
 * @return array{0: array, 1: string[]} The rule, and the hold patterns refused.
 */
function aw_rules_tag_rule_clean( array $in ) {
	$lines = fn( $v ) => array_values( array_unique( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', is_array( $v ) ? implode( "\n", $v ) : (string) $v ) ), 'strlen' ) ) );
	$hold  = array();
	$bad   = array();
	foreach ( $lines( $in['hold'] ?? '' ) as $p ) {
		if ( aw_rules_pattern_ok( $p ) ) {
			$hold[] = $p;
		} else {
			$bad[] = $p;
		}
	}
	$rule = array(
		'any'    => $lines( $in['any'] ?? '' ),
		'hold'   => $hold,
		'unless' => $lines( $in['unless'] ?? '' ),
	);
	$why  = trim( (string) ( $in['hold_reason'] ?? '' ) );
	if ( '' !== $why ) {
		$rule['hold_reason'] = $why;
	}
	return array( $rule, $bad );
}

/* ---------- The phase 3 migration ---------- */

/**
 * What the phase 3 migration writes, from the options as they are.
 *
 * @param array       $sources_opt aw_sources.
 * @param array|false $summary_opt aw_summary_rules (false: none saved; the defaults apply).
 * @param array|false $rules_opt   aw_enrich_field_rules (false: not seeded yet).
 * @param string[]    $types       The sections (aw_rules_section_types).
 * @param string[]    $fields      Enrichment field keys.
 * @return array{sources: array, summary: array|false, field_rules: array, added: string[], rekeyed: array, seeded: bool}
 */
function aw_rules_phase3_plan( array $sources_opt, $summary_opt, $rules_opt, array $types, array $fields ) {
	$secs  = (array) ( $sources_opt['sections'] ?? array() );
	$added = array();
	foreach ( $types as $t ) {
		if ( ! array_key_exists( $t, $secs ) ) {
			$secs[ $t ] = array(
				'categories' => array(),
				'tags'       => array(),
			); // empty: every category
			$added[]    = $t;
		}
	}
	$sources_opt['sections'] = $secs;
	$rekeyed                 = array();
	if ( is_array( $summary_opt ) && isset( $summary_opt['sections'] ) && is_array( $summary_opt['sections'] ) ) {
		$by = aw_rules_summary_by_post_type( $summary_opt['sections'] );
		foreach ( AW_RULES_SUMMARY_ALIAS as $pt => $alias ) {
			if ( isset( $summary_opt['sections'][ $alias ] ) && isset( $by[ $pt ] ) ) {
				$rekeyed[ $alias ] = $pt;
			}
		}
		$summary_opt['sections'] = $by;
	}
	$seeded = ! is_array( $rules_opt ) || empty( $rules_opt['fields'] );
	return array(
		'sources'     => $sources_opt,
		'summary'     => $summary_opt,
		'field_rules' => $seeded ? aw_enrich_field_rules_seed( $fields ) : $rules_opt,
		'added'       => $added,
		'rekeyed'     => $rekeyed,
		'seeded'      => $seeded,
	);
}

/**
 * Verify phase 3 against the backup: the planned changes by name, and any other difference.
 *
 * @param array       $old_sources  aw_sources in the backup.
 * @param array|false $old_summary  aw_summary_rules in the backup.
 * @param array       $new_sources  aw_sources now.
 * @param array|false $new_summary  aw_summary_rules now.
 * @param array|false $field_rules  aw_enrich_field_rules now.
 * @param string[]    $types        The sections now.
 * @param string[]    $fields       Enrichment field keys.
 * @return array{planned: string[], diffs: string[]}
 */
function aw_rules_phase3_compare( array $old_sources, $old_summary, array $new_sources, $new_summary, $field_rules, array $types, array $fields ) {
	$planned = array();
	$diffs   = array();
	$o       = (array) ( $old_sources['sections'] ?? array() );
	$n       = (array) ( $new_sources['sections'] ?? array() );
	foreach ( $o as $t => $sec ) {
		if ( ! array_key_exists( $t, $n ) ) {
			$diffs[] = "section rule $t lost";
		} elseif ( $n[ $t ] !== $sec ) {
			$diffs[] = "section rule $t changed";
		}
	}
	foreach ( array_diff( array_keys( $n ), array_keys( $o ) ) as $t ) {
		if ( array(
			'categories' => array(),
			'tags'       => array(),
		) === $n[ $t ] && in_array( $t, $types, true ) ) {
			$planned[] = "section rule added for $t (empty: every category)";
		} else {
			$diffs[] = "section rule $t added with values";
		}
	}
	foreach ( $types as $t ) {
		if ( ! array_key_exists( $t, $n ) ) {
			$diffs[] = "section $t has no section rule";
		}
	}
	$rest_o = $old_sources;
	$rest_n = $new_sources;
	unset( $rest_o['sections'], $rest_n['sections'] );
	if ( $rest_o !== $rest_n ) {
		$diffs[] = 'aw_sources (other than sections) changed';
	} else {
		$planned[] = 'tag rules unchanged (' . count( (array) ( $old_sources['topic_tags'] ?? array() ) ) . ')';
	}
	if ( is_array( $old_summary ) && isset( $old_summary['sections'] ) ) {
		$legacy = aw_rules_summary_legacy( (array) ( $new_summary['sections'] ?? array() ) );
		if ( $legacy !== $old_summary['sections'] ) {
			$diffs[] = 'summary rules: the sections read by their old names differ from the backup';
		} else {
			$planned[] = 'summary rules keyed by post type (' . implode( ', ', array_map( fn( $a, $p ) => "$a → $p", AW_RULES_SUMMARY_ALIAS, array_keys( AW_RULES_SUMMARY_ALIAS ) ) ) . '), every value kept';
		}
		$go = $old_summary;
		$gn = is_array( $new_summary ) ? $new_summary : array();
		unset( $go['sections'], $gn['sections'] );
		if ( $go !== $gn ) {
			$diffs[] = 'summary rules: a global key changed';
		}
	} elseif ( $old_summary !== $new_summary ) {
		$diffs[] = 'summary rules changed (none were saved)';
	}
	if ( ! is_array( $field_rules ) ) {
		$diffs[] = 'field rules not seeded';
	} else {
		$seed = aw_enrich_field_rules_seed( $fields );
		if ( $field_rules['priority'] !== $seed['priority'] ) {
			$diffs[] = 'trust order differs from the constants';
		}
		foreach ( $seed['fields'] as $f => $r ) {
			if ( ( $field_rules['fields'][ $f ] ?? null ) !== $r ) {
				$diffs[] = "field rule $f differs from the constants";
			}
		}
		if ( count( $seed['fields'] ) === count( (array) $field_rules['fields'] ) ) {
			$planned[] = 'field rules seeded: ' . count( $seed['fields'] ) . ' fields and a trust order of ' . count( $seed['priority'] ) . ' sources, equal to the workers\' constants';
		} else {
			$planned[] = 'field rules: ' . ( count( (array) $field_rules['fields'] ) - count( $seed['fields'] ) ) . ' set since for listing fields';
		}
	}
	return array(
		'planned' => $planned,
		'diffs'   => $diffs,
	);
}

/**
 * Undo phase 3 on the options as they are now: section rules the migration added and nobody filled are removed; the
 * summary rule sections go back to their old names; the field rules are removed (the code's rules apply again).
 *
 * @param array       $sources_opt aw_sources now.
 * @param array|false $summary_opt aw_summary_rules now.
 * @param string[]    $added       The section rules the migration added.
 * @return array{sources: array, summary: array|false, removed: string[], kept: string[]}
 */
function aw_rules_phase3_undo( array $sources_opt, $summary_opt, array $added ) {
	$removed = array();
	$kept    = array();
	foreach ( $added as $t ) {
		if ( array(
			'categories' => array(),
			'tags'       => array(),
		) === ( $sources_opt['sections'][ $t ] ?? null ) ) {
			unset( $sources_opt['sections'][ $t ] );
			$removed[] = $t;
		} elseif ( isset( $sources_opt['sections'][ $t ] ) ) {
			$kept[] = $t; // filled in since: kept (it does no harm to the old code)
		}
	}
	if ( is_array( $summary_opt ) && isset( $summary_opt['sections'] ) && is_array( $summary_opt['sections'] ) ) {
		$summary_opt['sections'] = aw_rules_summary_legacy( $summary_opt['sections'] );
	}
	return array(
		'sources' => $sources_opt,
		'summary' => $summary_opt,
		'removed' => $removed,
		'kept'    => $kept,
	);
}
