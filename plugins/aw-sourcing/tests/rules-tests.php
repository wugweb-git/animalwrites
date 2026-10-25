<?php
/**
 * Phase 3 (Rules) checks, run by tests/run.php: the Rules model (rules-model.php) against the live values of 25 Oct
 * 2026 (fixtures/phase3_live_sample.json), the field rules seed against the workers' constants
 * (fixtures/enrich_field_rules_seed.json, the same file as the workers' tests), and the listing field group's local JSON.
 *
 * @package aw-sourcing
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput -- a command-line test runner.

$p3     = json_decode( file_get_contents( __DIR__ . '/fixtures/phase3_live_sample.json' ), true );
$seedfx = json_decode( file_get_contents( __DIR__ . '/fixtures/enrich_field_rules_seed.json' ), true );
$acf    = json_decode( file_get_contents( dirname( __DIR__, 2 ) . '/animal-writes-core/acf-json/group_aw_listing.json' ), true );
$fields = array_keys( $p3['enrich_fields'] );
$p3_order = array( 'aw_news', 'aw_legal', 'post', 'aw_campaign', 'aw_opportunity', 'aw_listing' ); // The order of aw_post_types().

// ---- sections from the post types ----
$types = aw_rules_section_types( $p3['public_types'], array( 'aw_press' ), $p3_order );
aw_t( array( 'aw_news', 'aw_legal', 'post', 'aw_campaign', 'aw_opportunity', 'aw_listing', 'aw_press' ) === $types, 'sections: the 6 public content types (no page, no attachment) plus aw_press, which opts in' );
aw_t( array( 'aw_news', 'aw_legal', 'post', 'aw_campaign', 'aw_opportunity', 'aw_listing', 'aw_reports', 'aw_press' ) === aw_rules_section_types( array_merge( $p3['public_types'], array( 'aw_reports' ) ), array( 'aw_press' ), $p3_order ), 'a new public content type is a section by itself' );

// ---- the migration plan on live data ----
$src  = array(
	'sections'   => $p3['sections'],
	'topic_tags' => $p3['topic_tags'],
	'live'       => true,
);
$plan = aw_rules_phase3_plan( $src, $p3['summary_rules'], false, $types, $fields );
aw_t( 6 === count( $p3['sections'] ) && 7 === count( $plan['sources']['sections'] ), 'section rules: 6 before, 7 after' );
aw_t(
	array( 'aw_press' ) === $plan['added'] && array(
		'categories' => array(),
		'tags'       => array(),
	) === $plan['sources']['sections']['aw_press'],
	'aw_press added empty (every category)'
);
$same = true;
foreach ( $p3['sections'] as $t => $sec ) {
	$same = $same && $plan['sources']['sections'][ $t ] === $sec;
}
aw_t( $same && array_keys( $p3['sections'] ) === array_slice( array_keys( $plan['sources']['sections'] ), 0, 6 ), 'the 6 section rules copied unchanged, in their order' );
$cats = array_map( fn( $s ) => count( $s['categories'] ), $plan['sources']['sections'] );
$tags = array_map( fn( $s ) => count( $s['tags'] ), $plan['sources']['sections'] );
aw_t(
	array(
		'aw_legal'       => 19,
		'post'           => 19,
		'aw_opportunity' => 11,
		'aw_listing'     => 8,
		'aw_news'        => 19,
		'aw_campaign'    => 19,
		'aw_press'       => 0,
	) === $cats,
	'categories per section: 19/19/11/8/19/19, aw_press every category'
);
aw_t(
	array(
		'aw_legal'       => 9,
		'post'           => 3,
		'aw_opportunity' => 7,
		'aw_listing'     => 29,
		'aw_news'        => 0,
		'aw_campaign'    => 0,
		'aw_press'       => 0,
	) === $tags,
	'tags per section as live'
);
aw_t( $plan['sources']['topic_tags'] === $p3['topic_tags'] && 1 === count( $p3['topic_tags'] ), 'the 1 tag rule unchanged' );
aw_t( array( 'aw_news', 'aw_legal', 'post' ) === array_keys( $plan['summary']['sections'] ), 'summary rules keyed by post type' );
aw_t(
	array(
		'news'     => 'aw_news',
		'library'  => 'aw_legal',
		'research' => 'post',
	) === $plan['rekeyed'],
	'news → aw_news, library → aw_legal, research → post'
);
aw_t( 'news' === $plan['summary']['sections']['aw_news']['alias'] && 100 === $plan['summary']['sections']['aw_news']['min_words'] && 300 === $plan['summary']['sections']['post']['max_words'], 'alias kept, word counts kept (News 100–200, Research 160–300)' );
aw_t( aw_rules_summary_legacy( $plan['summary']['sections'] ) === $p3['summary_rules']['sections'], 'summary sections read by their old names: identical to live (sentence counts too)' );
$g_old = $p3['summary_rules'];
$g_new = $plan['summary'];
unset( $g_old['sections'], $g_new['sections'] );
aw_t( $g_old === $g_new && 15 === count( $g_old ), 'the 15 global summary keys unchanged' );
aw_t( $plan['seeded'] && 42 === count( $plan['field_rules']['fields'] ), 'field rules seeded: 42 fields' );

// The seed equals the workers' constants (the shared fixture) exactly.
aw_t( $seedfx['priority'] === $plan['field_rules']['priority'] && array( 'claim', 'website', 'darpan', 'wikidata', 'osm', 'social', 'awbi', 'google_places' ) === AW_ENRICH_SEED_PRIORITY, 'trust order = PRIORITY' );
aw_t( $seedfx['field_rules'] === $plan['field_rules']['fields'], 'every seeded field rule equals the workers\' constants (enrich_field_rules_seed.json)' );
aw_t( 2 === $plan['field_rules']['fields']['phone']['multi'] && 'awbi' === $plan['field_rules']['fields']['pincode']['official'] && $plan['field_rules']['fields']['head']['ai'] && $plan['field_rules']['fields']['hours']['invite'] && $plan['field_rules']['fields']['linkedin']['social'], 'MULTI, OFFICIAL_AWBI, AI_FIELDS, INVITE_FIELDS, SOCIAL_FIELDS in the seed' );

// Running it twice changes nothing; a stored field rule is kept.
$again = aw_rules_phase3_plan( $plan['sources'], $plan['summary'], $plan['field_rules'], $types, $fields );
aw_t( $again['sources'] === $plan['sources'] && $again['summary'] === $plan['summary'] && ! $again['added'] && ! $again['rekeyed'] && ! $again['seeded'], 'migrating twice changes nothing' );
aw_t( false === aw_rules_phase3_plan( $src, false, false, $types, $fields )['summary'], 'no saved summary rules: nothing to rekey' );

// Verify: 0 differences; lost or changed values found.
$cmp = aw_rules_phase3_compare( $src, $p3['summary_rules'], $plan['sources'], $plan['summary'], $plan['field_rules'], $types, $fields );
aw_t( array() === $cmp['diffs'], 'verify: 0 differences (' . implode( '; ', $cmp['diffs'] ) . ')' );
aw_t( 4 === count( $cmp['planned'] ), 'verify lists the 4 planned changes' );
$bad = $plan;
$bad['sources']['sections']['aw_news']['tags'] = array( 'x' );
unset( $bad['sources']['sections']['post'] );
$bad['summary']['sections']['aw_legal']['max_words'] = 250;
$bad['field_rules']['fields']['phone']['multi']      = 1;
$cmp = aw_rules_phase3_compare( $src, $p3['summary_rules'], $bad['sources'], $bad['summary'], $bad['field_rules'], $types, $fields );
aw_t( 5 === count( $cmp['diffs'] ), 'verify finds a changed and a lost section rule, a missing section, a changed summary rule and a changed field rule: ' . implode( '; ', $cmp['diffs'] ) );

// Rollback (undo) gives back the options of before, keeping a section rule filled in since.
$undo = aw_rules_phase3_undo( $plan['sources'], $plan['summary'], $plan['added'] );
aw_t( $undo['sources'] === $src && $undo['summary'] === $p3['summary_rules'] && array( 'aw_press' ) === $undo['removed'], 'undo: section rules and summary rules as before' );
$filled = $plan['sources'];
$filled['sections']['aw_press']['categories'][] = 'wildlife';
aw_t( array( 'aw_press' ) === aw_rules_phase3_undo( $filled, $plan['summary'], $plan['added'] )['kept'], 'undo keeps a section rule filled in since' );

// ---- summary rules by post type ----
$v1     = $p3['summary_rules']['sections'];
$labels = array(
	'aw_news'        => 'News',
	'aw_legal'       => 'Library',
	'post'           => 'Resources',
	'aw_campaign'    => 'Campaign Hub',
	'aw_opportunity' => 'Opportunities',
	'aw_listing'     => 'Directory',
	'aw_press'       => 'Media & PR',
);
$by     = aw_rules_summary_resolve( $v1, $plan['summary']['sections'], $labels );
aw_t( array_keys( $labels ) === array_keys( $by ), 'by_type: one rule per section' );
aw_t( 'news' === $by['aw_news']['alias'] && 'research' === $by['post']['alias'] && 160 === $by['post']['min_words'], 'the old sections by their post type' );
aw_t( 'aw_news' === $by['aw_press']['inherit'] && 100 === $by['aw_press']['min_words'] && 200 === $by['aw_campaign']['max_words'] && 'Media & PR' === $by['aw_press']['label'], 'a section without its own rule inherits News' );
aw_t( ! isset( $by['aw_news']['min_sentences'] ), 'sentence counts are not sent by type' );
$own = $plan['summary']['sections'] + array(
	'aw_campaign' => array(
		'label'     => 'Campaign Hub',
		'inherit'   => '',
		'min_words' => 40,
		'max_words' => 80,
	),
);
$by2 = aw_rules_summary_resolve( $v1, $own, $labels );
aw_t( '' === $by2['aw_campaign']['inherit'] && 80 === $by2['aw_campaign']['max_words'] && $by2['aw_campaign']['structure'] === $v1['news']['structure'], 'a section with its own rule: its values, the rest from News' );

// ---- the Directory schema from the listing field group ----
$schema = aw_enrich_schema_build( $acf['fields'], $p3['enrich_fields'], 'group_aw_listing', $p3['sections']['aw_listing']['tags'], array( 'rescue' => 'Rescue' ) );
$mapped = array_filter( $schema['fields'], fn( $f ) => (bool) $f['enrich'] );
$direct = array_filter( $p3['enrich_fields'], fn( $e ) => in_array( $e[1], array_column( $schema['fields'], 'name' ), true ) );
aw_t( 30 === count( $direct ) && 12 === count( $schema['special'] ), 'schema: 30 of the 42 enrichment fields stored in an ACF field, 12 in a special store' );
aw_t( 33 === count( $mapped ) && array( 'phone', 'whatsapp', 'emergency' ) === array_column( $schema['fields'], 'enrich', 'name' )['aw_phones'], 'the special stores Phone numbers, Opening hours and Photos are their listing fields too' );
aw_t( array( 'phone', 'whatsapp', 'emergency', 'pincode', 'hours', 'logo', 'photos', 'description', 'services', 'closed', 'place', 'merge_into' ) === array_column( $schema['special'], 'field' ), 'the 12 kept by code' );
aw_t( 29 === count( $schema['listing_types'] ) && ! in_array( 'tab', array_column( $schema['fields'], 'type' ), true ), 'listing types; tabs and messages left out' );
$rows = aw_enrich_field_rule_rows( $schema, $p3['enrich_fields'], $plan['field_rules']['fields'] );
$only = array_filter( $rows, fn( $r ) => 'Listing field' === $r['group'] );
aw_t( 42 + count( $only ) === count( $rows ) && count( $only ) === count( $schema['fields'] ) - 33, 'field rules: 42 enrichment fields plus every listing field no enrichment fills (' . count( $only ) . ')' );
aw_t( ! array_filter( $only, fn( $r ) => $r['rule']['on'] || $r['set'] ), 'listing fields without an enrichment field: rule off, not set' );
$acf2   = $acf['fields'];
$acf2[] = array(
	'name'  => 'aw_tiktok',
	'label' => 'TikTok',
	'type'  => 'url',
);
$rows2  = aw_enrich_field_rule_rows( aw_enrich_schema_build( $acf2, $p3['enrich_fields'], 'g', array(), array() ), $p3['enrich_fields'], $plan['field_rules']['fields'] );
aw_t( isset( $rows2['aw_tiktok'] ) && false === $rows2['aw_tiktok']['rule']['on'], 'a field added in wp-admin shows in Field rules with its rule off' );
aw_t( ! isset( $rows2['aw_gallery'] ) && ! isset( $rows2['aw_email'] ) && isset( $rows2['photos'] ) && isset( $rows2['email'] ), 'a field filled by enrichment shows once, under its enrichment key' );

// ---- field rules from a form ----
$sk = $p3['source_keys'];
$r  = aw_enrich_rule_clean(
	'founded',
	array(
		'apply'    => 'both',
		'official' => 'nowhere',
		'multi'    => 9,
		'sources'  => array( 'wikidata', 'x' ),
	),
	$sk,
	aw_enrich_rule_seed( 'founded' )
);
aw_t( 'empty' === $r['apply'] && '' === $r['official'] && 5 === $r['multi'] && array( 'wikidata' ) === $r['sources'], 'a field rule is checked: no "both" for a one-value field, a known official source, at most 5 values, known sources' );
aw_t( aw_enrich_rule_clean( 'email', aw_enrich_rule_seed( 'email' ), $sk, aw_enrich_rule_seed( 'email' ) ) === aw_enrich_rule_seed( 'email' ), 'a field rule saved unchanged is unchanged' );
$pos = array();
foreach ( AW_ENRICH_SEED_PRIORITY as $i => $k ) {
	$pos[ $k ] = (string) ( $i + 1 );
}
aw_t( AW_ENRICH_SEED_PRIORITY === aw_enrich_priority_from_positions( $pos, $sk, AW_ENRICH_SEED_PRIORITY ), 'trust order saved unchanged is unchanged' );
$pos['osm'] = '1';
aw_t( 'osm' === aw_enrich_priority_from_positions( $pos, $sk, AW_ENRICH_SEED_PRIORITY )[0], 'trust order: a source moved to 1 comes first' );
aw_t( AW_ENRICH_SEED_PRIORITY === aw_enrich_priority_from_positions( array(), $sk, AW_ENRICH_SEED_PRIORITY ), 'trust order: nothing numbered keeps the order' );

// ---- the apply rule: the seeded rules decide exactly as the code did before phase 3 ----
$before = function ( $field, $conf, $protected, $empty, $phones, $alt, $place ) {
	if ( $protected || 'low' === $conf || 'closed' === $field || 'merge_into' === $field ) {
		return 'queue';
	}
	if ( 'place' === $field ) {
		return $place ? 'apply' : 'queue';
	}
	if ( in_array( $field, array( 'phone', 'whatsapp', 'emergency' ), true ) ) {
		return $phones ? 'both' : 'apply';
	}
	if ( $empty ) {
		return 'apply';
	}
	if ( in_array( $field, array( 'services', 'animals', 'areas_served' ), true ) ) {
		return 'both';
	}
	if ( in_array( $field, array( 'email', 'website' ), true ) && $alt ) {
		return 'both';
	}
	return 'queue';
};
$same   = true;
$n      = 0;
foreach ( $fields as $f ) {
	foreach ( array( 'high', 'medium', 'low' ) as $conf ) {
		foreach ( array( false, true ) as $prot ) {
			foreach ( array( false, true ) as $empty ) {
				foreach ( array( false, true ) as $phones ) {
					foreach ( array( false, true ) as $alt ) {
						foreach ( array( false, true ) as $place ) {
							$want = $before( $f, $conf, $prot, in_array( $f, array( 'whatsapp', 'emergency' ), true ) ? true : $empty, $phones, $alt, $place );
							$got  = aw_enrich_mode_decide( $f, $plan['field_rules']['fields'][ $f ]['apply'], $conf, $prot, fn() => in_array( $f, array( 'whatsapp', 'emergency' ), true ) ? true : $empty, fn() => $phones, fn() => $alt, fn() => $place );
							$same = $same && $want === $got;
							++$n;
						}
					}
				}
			}
		}
	}
}
aw_t( $same, "apply rules: the seeded field rules decide as the code did, in all $n cases" );
aw_t( 'queue' === aw_enrich_mode_decide( 'email', 'review', 'high', false, fn() => true, fn() => false, fn() => true, fn() => true ), 'apply rule "review": always a person' );
aw_t( 'queue' === aw_enrich_mode_decide( 'phone', 'empty', 'high', false, fn() => false, fn() => true, fn() => true, fn() => true ), 'apply rule "empty" on phones: a second number waits' );

aw_t( 'queue' === aw_enrich_mode_decide( 'merge_into', 'empty', 'high', false, fn() => true, fn() => false, fn() => true, fn() => true ), 'merge_into: always a person, whatever its rule says' );
aw_t( 'review' === aw_enrich_rule_clean( 'closed', array( 'apply' => 'empty' ), $sk, aw_enrich_rule_seed( 'closed' ) )['apply'], 'closed and merge_into cannot be given another apply rule' );

// ---- tag rules ----
$live_rule        = $p3['topic_tags']['campaign-lab-animals-2026'];
list( $tr, $bad ) = aw_rules_tag_rule_clean(
	array(
		'any'         => implode( "\n", $live_rule['any'] ),
		'hold'        => implode( "\r\n", $live_rule['hold'] ),
		'unless'      => implode( "\n", $live_rule['unless'] ),
		'hold_reason' => $live_rule['hold_reason'],
	)
);
aw_t( $tr === $live_rule && ! $bad, 'the lab campaign tag rule saved unchanged from the form is unchanged' );
list( $tr, $bad ) = aw_rules_tag_rule_clean(
	array(
		'any'  => 'a',
		'hold' => "ok\nbad(",
	)
);
aw_t( array( 'bad(' ) === $bad && array( 'ok' ) === $tr['hold'] && ! isset( $tr['hold_reason'] ), 'a hold pattern that does not compile is refused' );
aw_t( ! aw_rules_pattern_ok( '\\h+lab' ) && ! aw_rules_pattern_ok( 'foo++' ) && ! aw_rules_pattern_ok( '(?>a)' ), 'PCRE-only patterns the workers cannot read are refused' );
aw_t( aw_rules_pattern_ok( 'a\\~b' ) && aw_rules_pattern_ok( 'laboratory animals\\)? rules,? 2026' ) && aw_rules_pattern_ok( 'a~b' ), 'patterns with ~ and the live hold pattern are accepted' );
aw_t( ! aw_rules_pattern_ok( '\\x{1F600}' ) && ! aw_rules_pattern_ok( '\\e' ) && ! aw_rules_pattern_ok( '\\cA' ) && ! aw_rules_pattern_ok( '(?<n>a)' ) && ! aw_rules_pattern_ok( '(?R)' ), 'escapes and groups Python re cannot compile are refused (Codex, #77)' );
aw_t( aw_rules_pattern_ok( '\\x41\\d+\\s\\bG\\.S\\.R\\.' ) && aw_rules_pattern_ok( '\\\\d' ) && aw_rules_pattern_ok( '(?P<n>a)(?<=b)(?<!c)' ), 'Python-compatible escapes and lookbehinds are accepted' );
$en_src = (string) file_get_contents( dirname( __DIR__ ) . '/includes/enrichment.php' );
$pp_at  = strpos( $en_src, 'function aw_enrich_policy_pass' );
$pp_src = false === $pp_at ? '' : substr( $en_src, $pp_at, 2500 );
aw_t( false !== strpos( $pp_src, 'aw_enrich_field_rule( $r[\'field\'] )' ) && false !== strpos( $pp_src, 'aw_enrich_allowed_sources(' ), 'the policy pass rechecks the field rule and its sources before applying queued rows (Codex, #77)' );

// ---- listing fields added in wp-admin: which can be enabled, and how their values are written ----
// WordPress's sanitizers, as plain stand-ins (no WordPress here).
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $v ) { // phpcs:ignore Universal.Files.SeparateFunctionsFromOO.Mixed, WordPress.NamingConventions.PrefixAllGlobals -- a test stand-in for core's function
		return trim( strip_tags( (string) $v ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- test stand-in
	}
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $v ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- a test stand-in for core's function
		return trim( strip_tags( (string) $v ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- test stand-in
	}
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $v ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- a test stand-in for core's function
		return preg_match( '#^https?://#', (string) $v ) ? (string) $v : '';
	}
}
if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $v ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- a test stand-in for core's function
		return filter_var( $v, FILTER_VALIDATE_EMAIL ) ? (string) $v : '';
	}
}
aw_t( '' === aw_enrich_acf_not_writable( 'url' ) && '' !== aw_enrich_acf_not_writable( 'repeater' ) && '' !== aw_enrich_acf_not_writable( 'gallery' ), 'text-like ACF fields can be enabled; repeaters and galleries cannot' );
aw_t( 'https://tiktok.com/@bc' === aw_enrich_acf_value( 'url', ' https://tiktok.com/@bc ' ) && '' === aw_enrich_acf_value( 'url', 'javascript:x' ), 'URL values cleaned' );
aw_t( '20261025' === aw_enrich_acf_value( 'date_picker', '2026-10-25' ) && '' === aw_enrich_acf_value( 'date_picker', '2026-02-30' ), 'dates stored as ACF does (Ymd), impossible dates refused' );
aw_t( '12' === aw_enrich_acf_value( 'number', '12' ) && '' === aw_enrich_acf_value( 'number', 'twelve' ) && '' === aw_enrich_acf_value( 'repeater', 'x' ), 'numbers checked; other types never written' );
$acf3   = $acf['fields'];
$acf3[] = array( 'name' => 'aw_tiktok', 'label' => 'TikTok', 'type' => 'url' );
$acf3[] = array( 'name' => 'aw_branches', 'label' => 'Branches', 'type' => 'repeater' );
$set    = $plan['field_rules']['fields'] + array( 'aw_tiktok' => array_merge( aw_enrich_rule_off(), array( 'on' => true, 'apply' => 'empty' ) ), 'aw_branches' => array_merge( aw_enrich_rule_off(), array( 'on' => true ) ) );
$rows3  = aw_enrich_field_rule_rows( aw_enrich_schema_build( $acf3, $p3['enrich_fields'], 'g', array(), array() ), $p3['enrich_fields'], $set );
aw_t( $rows3['aw_tiktok']['rule']['on'] && '' === $rows3['aw_tiktok']['why'], 'a URL field added in wp-admin can be switched on' );
aw_t( false === $rows3['aw_branches']['rule']['on'] && '' !== $rows3['aw_branches']['why'], 'a repeater added in wp-admin stays off, with the reason shown' );
$g = array( array( 'key' => 'google_places' ), array( 'key' => 'website' ) );
aw_t( array() === aw_enrich_allowed_sources( array( array( 'key' => 'google_places' ) ), array( 'website' ) ) && array( array( 'key' => 'website' ) ) === aw_enrich_allowed_sources( $g, array( 'website' ) ), 'a source allow list without Google drops Google (place ID and Maps link too)' );
aw_t( $g === aw_enrich_allowed_sources( $g, array() ) && 2 === count( aw_enrich_allowed_sources( $g, array( 'google_places', 'website' ) ) ), 'no allow list keeps every source; an allow list naming Google keeps it' );
