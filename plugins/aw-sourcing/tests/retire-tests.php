<?php
/**
 * Phase 4 (Retire) checks, run by tests/run.php: retire-model.php on the live lists of 25 Oct 2026
 * (fixtures/phase2_live_sample.json, migrated as phase 2 migrates them), and the owner's rule "keep old until new is
 * proven" (25 Oct 2026): phase 4 deletes and changes no old option, hides the old tabs by the phase switch and keeps
 * every v1 path for a rollback.
 *
 * @package aw-sourcing
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput -- a command-line test runner.

$p4  = json_decode( file_get_contents( __DIR__ . '/fixtures/phase2_live_sample.json' ), true );
$p4r = array();
foreach ( $p4['rows'] as $r ) {
	$p4r[] = $r + array( 'variants' => array(), 'src' => array(), 'categories' => array(), 'sections' => array(), 'query' => '', 'feed_id' => '', 'search_on' => 1, 'dismissed' => 0 );
}
$p4plan = aw_kw_phase2_plan( $p4['relevance'], $p4['sources_option'], $p4['dismissed'], $p4r );
$p4id   = 800000;
$p4recs = array();
foreach ( $p4plan['records'] as $r ) {
	$r['id']  = $r['id'] ? $r['id'] : ++$p4id;
	$p4recs[] = $r;
}

// The options as phase 2 left them on live: the lists are the mirrors rebuilt from the table.
$p4mir                     = aw_kw_mirrors( $p4recs );
$p4rel                     = array_merge( $p4['relevance'], array_intersect_key( $p4mir, array_flip( AW_RETIRE_REL_LISTS ) ) );
$p4cfg                     = $p4['sources_option'];
$p4cfg['press']['queries'] = $p4mir['press'];
$p4cfg['categories']       = aw_kw_categories_in_order( $p4mir['categories'], (array) $p4['sources_option']['categories'] );

$rp = aw_retire_plan( $p4rel, $p4cfg, $p4['dismissed'], $p4recs );
aw_t( array() === $rp['conflicts'], 'phase 4 on the live lists: every word has its keyword record and role (' . implode( '; ', $rp['conflicts'] ) . ')' );
aw_t( array( 'aw_relevance.strong', 'aw_relevance.animal', 'aw_relevance.context', 'aw_relevance.exclude', 'aw_sources.categories', 'aw_sources.keywords', 'aw_sources.press.queries', 'aw_sourcing_dismissed' ) === array_keys( $rp['counts'] ), 'phase 4 archives the 4 lists, the category words, the seed keywords, the press queries and the dismissed searches' );
aw_t( 11 === $rp['counts']['aw_sources.press.queries'] && 4 === $rp['counts']['aw_sourcing_dismissed'], 'the 11 press queries and the 4 dismissed searches' );
aw_t( $rp['archive']['aw_relevance']['strong'] === $p4rel['strong'] && $rp['archive']['aw_sources']['categories'] === $p4cfg['categories'], 'the archive holds the lists exactly as stored' );

// The lists as they were before phase 2 (with the Hindi words): those are planned, never conflicts.
$rp1 = aw_retire_plan( $p4['relevance'], $p4['sources_option'], $p4['dismissed'], $p4recs );
aw_t( array() === $rp1['conflicts'] && (bool) array_filter( $rp1['planned'], fn( $x ) => false !== strpos( $x, 'पशु' ) ), 'Hindi words have no Sourcing role (standing rule): planned, not a conflict' );

// What only the old lists hold stops the migration.
$bad           = $p4rel;
$bad['strong'] = array_merge( $bad['strong'], array( 'zebra crossing ban' ) );
aw_t( 1 === count( aw_retire_plan( $bad, $p4cfg, $p4['dismissed'], $p4recs )['conflicts'] ), 'a word only the old list has is a conflict' );
aw_t( 1 === count( aw_retire_plan( $p4rel, $p4cfg, array_merge( $p4['dismissed'], array( 'never dismissed here' ) ), $p4recs )['conflicts'] ), 'a dismissed search with no dismissed record is a conflict' );
$badc                             = $p4cfg;
$badc['categories']['wildlife'][] = 'unmapped word';
aw_t( 1 === count( aw_retire_plan( $p4rel, $badc, $p4['dismissed'], $p4recs )['conflicts'] ), 'a category word with no Category record for that category is a conflict' );

// The archive: checked against the options, and its checksums.
$ar = aw_retire_archive( $rp, '2026-10-27 10:00:00', 'aw_backup_4_x' );
aw_t( array() === aw_retire_archive_problems( $ar, $rp ), 'the archive matches the options it copies' );
$ar2                                       = $ar;
$ar2['values']['aw_relevance']['strong'][] = 'x';
aw_t( (bool) aw_retire_archive_problems( $ar2, $rp ), 'an archive that differs from the options is found' );
$ar3                                  = $ar;
$ar3['sums']['aw_sourcing_dismissed'] = 'bad';
aw_t( (bool) aw_retire_archive_problems( $ar3, $rp ), 'an archive whose checksum does not match is found' );

// The policy from phase 4: no v1 keys, every v2 block; the v1 policy is found as such.
$pol = array(
	'publishers'  => array( 'blocked' => array(), 'allowed' => array(), 'gnews_allow_only' => false ),
	'relevance'   => array( 'enabled' => false, 'strong' => array( 'a' ), 'animal' => array(), 'context' => array(), 'exclude' => array() ),
	'relay_hosts' => array( 'awbi.gov.in' ),
	'keywords'    => array( array( 'label' => 'a' ) ),
	'config'      => array(
		'defaults'       => array(),
		'keywords'       => array( 'a' ),
		'sources'        => array(),
		'categories'     => array(),
		'sections'       => array(),
		'press'          => array( 'enabled' => true, 'date_floor' => '2025-11-01', 'every_days' => 7, 'queries' => array( 'q' ) ),
		'topic_tags'     => array(),
		'category_names' => array(),
		'schema'         => array( 'sources' => 2, 'keywords' => 2, 'rules' => 2 ),
		'match'          => 'word',
		'sources_v2'     => array(),
		'keywords_v2'    => array(),
		'sections_v2'    => array(),
	),
);
aw_t( 9 === count( aw_retire_policy_problems( $pol ) ), 'the v1 policy: 4 relevance lists, 4 config keys and the press queries still sent' );
$p4pol = aw_retire_policy( $pol );
aw_t( array() === aw_retire_policy_problems( $p4pol ), 'the phase 4 policy has no v1 keys and every v2 block' );
aw_t( array( 'enabled' => false ) === $p4pol['relevance'] && array( 'enabled' => true, 'date_floor' => '2025-11-01', 'every_days' => 7 ) === $p4pol['config']['press'] && $p4pol['relay_hosts'] === $pol['relay_hosts'] && $p4pol['publishers'] === $pol['publishers'], 'the switches, publishers, relay hosts, press settings and tag rules stay' );
aw_t( array( 'config.sections_v2 is missing' ) === aw_retire_policy_problems( array_merge( $p4pol, array( 'config' => array_diff_key( $p4pol['config'], array( 'sections_v2' => 1 ) ) ) ) ), 'a missing v2 block is found' );

// Rollback order: phase 1–3 wait while phase 4 is migrated or half-migrated.
aw_t( '' !== aw_retire_refusal( 3, 4, true ) && '' !== aw_retire_refusal( 2, 3, true ) && '' === aw_retire_refusal( 3, 3, false ) && '' === aw_retire_refusal( 4, 4, true ), 'rollback --phase=1..3 refused while phase 4 is migrated' );

// Keep old until new is proven (owner, 25 Oct 2026): nothing old deleted or changed; old tabs and v1 paths stay, switched.
$p4_mg  = (string) file_get_contents( dirname( __DIR__ ) . '/includes/sourcing-migrate-retire.php' );
$p4_mig = substr( $p4_mg, strpos( $p4_mg, "'aw_sourcing_cli_migrate_4'" ), strpos( $p4_mg, "'aw_sourcing_cli_verify_4'" ) - strpos( $p4_mg, "'aw_sourcing_cli_migrate_4'" ) );
aw_t( ! preg_match( "/(update|delete)_option\(\s*(AW_REL_OPTION|'aw_relevance'|'aw_sources'|'aw_sourcing_dismissed')/", $p4_mig ), 'migrate --phase=4 writes and deletes no old option' );
aw_t( false === strpos( $p4_mg, 'aw-backups' ) || ! preg_match( '/unlink|wp_delete_file|delete_option\(\s*\$name/', $p4_mg ), 'phase 4 deletes no backup' );
aw_t( substr_count( $p4_mig, '$save();' ) >= 3 && false !== strpos( $p4_mig, "'aw_sourcing_migrating', 4" ), 'the record is saved before each step and the migrating flag is set' );
$p4_lists = (string) file_get_contents( dirname( __DIR__ ) . '/includes/sourcing-lists.php' );
$p4_kwa   = (string) file_get_contents( dirname( __DIR__ ) . '/includes/keywords-admin.php' );
$p4_pol   = (string) file_get_contents( dirname( __DIR__ ) . '/includes/sourcing-policy.php' );
aw_t( false !== strpos( $p4_lists, "\$tabs['categories'] = array( 'Categories', 'aw_sources_categories_tab', true );" ) && false !== strpos( $p4_lists, 'aw_sourcing_phase() >= 4 ) { // phase 4: hidden' ), 'the old tabs stay in the code, hidden from phase 4 (a rollback shows them)' );
aw_t( false !== strpos( $p4_kwa, '$old  = aw_sourcing_phase() < 4;' ), 'the old lists are no longer rebuilt from phase 4 (rebuilt again after a rollback)' );
aw_t( false !== strpos( $p4_pol, "aw_sourcing_phase() >= 4 && function_exists( 'aw_retire_policy' )" ) && false !== strpos( $p4_lists, "if ( aw_sourcing_phase() >= 4 ) { // phase 4: no v1 keys" ), 'the policy drops the v1 keys only from phase 4; the v1 code stays' );
