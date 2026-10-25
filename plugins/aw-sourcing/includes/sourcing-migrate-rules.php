<?php
/**
 * Sourcing restructure phase 3 (Rules; owner, 25 Oct 2026) from WP-CLI, with the phase 1 commands (sourcing-migrate.php):
 *
 *     wp aw-sourcing backup --phase=3
 *     wp aw-sourcing migrate --phase=3 --dry-run
 *     wp aw-sourcing migrate --phase=3
 *     wp aw-sourcing verify --phase=3
 *     wp aw-sourcing rollback --phase=3 [--restore-all]
 *
 * The migration (rules-model.php aw_rules_phase3_plan):
 * - Section rules: every section (the site's content types, rules.php) gets a rule; the 6 there are kept as they are,
 *   aw_press is added empty (every category). Tag rules (aw_sources['topic_tags']) are unchanged.
 * - Summary rules: the sections are keyed by post type (news → aw_news, library → aw_legal, research → post), each with
 *   its old name as alias; every value is kept, the 15 global keys too.
 * - Field rules: option aw_enrich_field_rules seeded from the workers' constants (42 fields, trust order of 8).
 * It needs phase 2 migrated first (aw_sourcing_phase = 2); a dry run works at any phase.
 *
 * Rollback undoes it on the current options (section rules the migration added and nobody filled, summary keys back to
 * their old names, field rules off) and keeps what it takes away in option aw_sourcing_phase3_rolled_back; with
 * --restore-all it puts the backed-up options back exactly.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/** Options a phase 3 backup holds. */
const AW_SOURCING_BACKUP_OPTIONS_3 = array( 'aw_sources', 'aw_summary_rules', 'aw_enrich_field_rules', 'aw_enrich_sources', 'kits_sourcing_log', 'aw_sourcing_phase' );

add_filter( 'aw_sourcing_cli_phases', fn( $p ) => array_values( array_unique( array_merge( (array) $p, array( 3 ) ) ) ) );
add_filter( 'aw_sourcing_cli_backup_options', fn( $o, $phase ) => 3 === (int) $phase ? AW_SOURCING_BACKUP_OPTIONS_3 : $o, 10, 2 );
add_filter( 'aw_sourcing_cli_counts', fn( $line, $phase, $opts ) => 3 === (int) $phase ? aw_sourcing_cli_counts3( (array) $opts ) : $line, 10, 3 );

/**
 * One line of counts for a phase 3 backup or for the options now.
 *
 * @param array $opts Option name => value.
 * @return string
 */
function aw_sourcing_cli_counts3( array $opts ) {
	$c  = (array) ( $opts['aw_sources'] ?? array() );
	$s  = $opts['aw_summary_rules'] ?? null;
	$fr = $opts['aw_enrich_field_rules'] ?? null;
	return sprintf(
		'section rules %d · tag rules %d · summary sections %d (%s) + %d global keys · field rules %s · enrichment switches %d · kits_sourcing_log %d · phase %d',
		count( (array) ( $c['sections'] ?? array() ) ),
		count( (array) ( $c['topic_tags'] ?? array() ) ),
		is_array( $s ) ? count( (array) ( $s['sections'] ?? array() ) ) : 0,
		is_array( $s ) ? implode( ', ', array_keys( (array) ( $s['sections'] ?? array() ) ) ) : 'none saved: the defaults',
		is_array( $s ) ? count( $s ) - ( isset( $s['sections'] ) ? 1 : 0 ) : 0,
		is_array( $fr ) ? count( (array) ( $fr['fields'] ?? array() ) ) . ' fields, trust order ' . count( (array) ( $fr['priority'] ?? array() ) ) : 'not seeded',
		count( (array) ( $opts['aw_enrich_sources'] ?? array() ) ),
		count( (array) ( $opts['kits_sourcing_log'] ?? array() ) ),
		(int) ( $opts['aw_sourcing_phase'] ?? 0 )
	);
}

/** The options a phase 3 backup holds, as they are now. */
function aw_sourcing_cli_now3() {
	$now = array();
	foreach ( AW_SOURCING_BACKUP_OPTIONS_3 as $k ) {
		$now[ $k ] = get_option( $k, null );
	}
	return $now;
}

/**
 * The summary rules in force (aw_summary_rules()) if the option held $opt.
 *
 * @param array|false|null $opt A value of aw_summary_rules.
 * @return array
 */
function aw_sourcing_cli_summary_with( $opt ) {
	$peek = fn() => is_array( $opt ) ? $opt : false;
	add_filter( 'pre_option_' . AW_SUMMARY_RULES_OPTION, $peek );
	$out  = aw_summary_rules();
	remove_filter( 'pre_option_' . AW_SUMMARY_RULES_OPTION, $peek );
	return $out;
}

/** What the Directory schema holds, in a few lines. */
function aw_sourcing_cli_schema_lines() {
	$schema = aw_enrich_schema();
	$rows   = aw_enrich_field_rule_rows( $schema, aw_enrich_fields(), aw_enrich_field_rules_seed( array_keys( aw_enrich_fields() ) )['fields'] );
	$mapped = array_filter( $schema['fields'], fn( $f ) => '' !== $f['enrich'] );
	$only   = array_filter( $rows, fn( $r ) => 'Listing field' === $r['group'] );
	return array(
		sprintf( 'Directory schema (%s, read %s): %d listing fields; %d of them filled by enrichment, %d enrichment fields kept by code (%s); %d listing fields with no enrichment field (rule off until set).', $schema['group'], function_exists( 'acf_get_fields' ) ? 'from ACF' : 'from the local JSON', count( $schema['fields'] ), count( $mapped ), count( $schema['special'] ), implode( ', ', array_column( $schema['special'], 'field' ) ), count( $only ) ),
		sprintf( 'Listing types (the Directory section\'s tags): %d · services: %d', count( $schema['listing_types'] ), count( (array) $schema['services'] ) ),
	);
}

add_action(
	'aw_sourcing_cli_migrate_3',
	function ( $assoc, $dry ) {
		if ( aw_sourcing_phase() >= 3 ) {
			WP_CLI::error( 'Phase 3 is already migrated (aw_sourcing_phase = ' . aw_sourcing_phase() . '). wp aw-sourcing verify --phase=3 checks it.' );
		}
		if ( 2 !== aw_sourcing_phase() ) {
			$msg = 'Phase 3 runs after phase 2 (aw_sourcing_phase = ' . aw_sourcing_phase() . '): migrate phase 2 first.';
			if ( ! $dry ) {
				WP_CLI::error( $msg );
			}
			WP_CLI::warning( $msg . ' This dry run shows what phase 3 would do to the options as they are.' );
		}
		$bk = '';
		if ( ! $dry ) {
			list( $bk ) = aw_sourcing_cli_backup( 3, (string) get_option( 'aw_backup_latest_3', '' ) ); // refuses without a backup
			WP_CLI::log( "Backup: $bk" );
		}
		$types  = array_keys( aw_rules_sections() );
		$fields = array_keys( aw_enrich_fields() );
		$c      = (array) get_option( 'aw_sources', array() );
		$sum    = get_option( AW_SUMMARY_RULES_OPTION );
		$fr     = get_option( AW_ENRICH_RULES_OPTION );
		$plan   = aw_rules_phase3_plan( $c, $sum, $fr, $types, $fields );

		WP_CLI::log( 'Sections (the site\'s content types): ' . implode( ', ', $types ) );
		$before = (array) ( $c['sections'] ?? array() );
		WP_CLI::log( sprintf( 'Section rules: %d before, %d after.', count( $before ), count( $plan['sources']['sections'] ) ) );
		foreach ( $plan['sources']['sections'] as $t => $sec ) {
			$cats = count( (array) ( $sec['categories'] ?? array() ) );
			WP_CLI::log( sprintf( '  %s: %s, %d tags%s', $t, $cats ? $cats . ' categories' : 'every category', count( (array) ( $sec['tags'] ?? array() ) ), in_array( $t, $plan['added'], true ) ? ' (added, empty)' : ' (unchanged)' ) );
		}
		foreach ( array_diff( array_keys( $before ), $types ) as $t ) {
			WP_CLI::warning( "section rule $t is for a post type that is no section: kept as it is" );
		}
		$tags = (array) ( $c['topic_tags'] ?? array() );
		WP_CLI::log( sprintf( 'Tag rules: %d before, %d after (unchanged; edited from Rules → Section rules): %s', count( $tags ), count( $tags ), implode( ', ', array_keys( $tags ) ) ) );
		if ( is_array( $sum ) ) {
			WP_CLI::log( sprintf( 'Summary rules: %d sections before, %d after; %d global keys unchanged.', count( (array) ( $sum['sections'] ?? array() ) ), count( (array) ( $plan['summary']['sections'] ?? array() ) ), count( $sum ) - ( isset( $sum['sections'] ) ? 1 : 0 ) ) );
			foreach ( (array) ( $plan['summary']['sections'] ?? array() ) as $pt => $sec ) {
				WP_CLI::log( sprintf( '  %s%s: %d–%d words', $pt, isset( $sec['alias'] ) ? ' (was ' . $sec['alias'] . ')' : '', (int) ( $sec['min_words'] ?? 0 ), (int) ( $sec['max_words'] ?? 0 ) ) );
			}
		} else {
			WP_CLI::log( 'Summary rules: none saved (the defaults apply); nothing to rekey.' );
		}
		$labels = aw_rules_section_labels();
		$inh    = array_keys( array_filter( aw_rules_summary_resolve( aw_sourcing_cli_summary_with( $plan['summary'] )['sections'], is_array( $plan['summary'] ) ? (array) ( $plan['summary']['sections'] ?? array() ) : array(), $labels ), fn( $r ) => '' !== (string) ( $r['inherit'] ?? '' ) ) );
		WP_CLI::log( 'Sections that inherit the News summary rule: ' . ( $inh ? implode( ', ', $inh ) : '(none)' ) );
		WP_CLI::log( $plan['seeded'] ? sprintf( 'Field rules: seeded, %d fields and a trust order of %d sources (%s), equal to the workers\' constants.', count( $plan['field_rules']['fields'] ), count( $plan['field_rules']['priority'] ), implode( ' > ', $plan['field_rules']['priority'] ) ) : 'Field rules: already stored, kept as they are.' );
		foreach ( aw_sourcing_cli_schema_lines() as $line ) {
			WP_CLI::log( $line );
		}
		$v1_before = aw_sourcing_cli_summary_with( $sum )['sections'];
		$v1_after  = aw_sourcing_cli_summary_with( $plan['summary'] )['sections'];
		$conflicts = $v1_before === $v1_after ? array() : array( 'the summary rules read by their old names would change' );
		foreach ( $conflicts as $d ) {
			WP_CLI::warning( 'conflict: ' . $d );
		}
		if ( $dry ) {
			WP_CLI::success( 'Dry run: nothing written. ' . count( $conflicts ) . ' conflicts.' );
			return;
		}
		if ( $conflicts ) {
			WP_CLI::error( 'Not migrated: resolve the conflicts above first.' );
		}
		// Read-modify-write right before saving: the plan applied to the options as they are now.
		$now = aw_rules_phase3_plan( (array) get_option( 'aw_sources', array() ), get_option( AW_SUMMARY_RULES_OPTION ), get_option( AW_ENRICH_RULES_OPTION ), $types, $fields );
		update_option( 'aw_sources', $now['sources'], false );
		if ( is_array( $now['summary'] ) ) {
			update_option( AW_SUMMARY_RULES_OPTION, $now['summary'] );
		}
		if ( $now['seeded'] ) {
			update_option( AW_ENRICH_RULES_OPTION, $now['field_rules'], false );
		}
		update_option(
			'aw_sourcing_phase3_record',
			array(
				'at'      => current_time( 'mysql' ),
				'backup'  => $bk,
				'added'   => $now['added'],
				'rekeyed' => $now['rekeyed'],
				'seeded'  => $now['seeded'],
			),
			false
		);
		update_option( 'aw_sourcing_phase', 3, false );
		kits_sourcing_log_many(
			array(
				array( 'sections', sprintf( 'Phase 3 migrated (WP-CLI): section rules %d → %d (added %s, empty: every category); tag rules %d unchanged', count( $before ), count( $now['sources']['sections'] ), $now['added'] ? implode( ', ', $now['added'] ) : 'none', count( $tags ) ), array_keys( $before ), array_keys( $now['sources']['sections'] ) ),
				array( 'summary', 'Phase 3 migrated (WP-CLI): summary rules keyed by post type, every value kept', array_keys( $now['rekeyed'] ), array_values( $now['rekeyed'] ) ),
				array( 'enrich', $now['seeded'] ? sprintf( 'Phase 3 migrated (WP-CLI): field rules seeded from the workers\' constants (%d fields, trust order of %d)', count( $now['field_rules']['fields'] ), count( $now['field_rules']['priority'] ) ) : 'Phase 3 migrated (WP-CLI): field rules kept as stored', '', $now['seeded'] ? count( $now['field_rules']['fields'] ) . ' field rules' : '' ),
			)
		);
		WP_CLI::success( 'Phase 3 migrated. Next: wp aw-sourcing verify --phase=3' );
	},
	10,
	2
);

add_action(
	'aw_sourcing_cli_verify_3',
	function () {
		list( $bk, $old ) = aw_sourcing_cli_backup( 3 );
		$now              = aw_sourcing_cli_now3();
		WP_CLI::log( "Backup: $bk" );
		WP_CLI::log( 'Backup: ' . aw_sourcing_cli_counts3( $old ) );
		WP_CLI::log( 'Now:    ' . aw_sourcing_cli_counts3( $now ) );
		if ( aw_sourcing_phase() < 3 ) {
			WP_CLI::error( 'Phase 3 is not migrated (aw_sourcing_phase = ' . aw_sourcing_phase() . ').' );
		}
		$types  = array_keys( aw_rules_sections() );
		$fields = array_keys( aw_enrich_fields() );
		$cmp    = aw_rules_phase3_compare( (array) $old['aw_sources'], $old['aw_summary_rules'] ?? false, (array) $now['aw_sources'], $now['aw_summary_rules'] ?? false, $now['aw_enrich_field_rules'] ?? false, $types, $fields );
		// The rules every reader of before sees: the summary rules by their old names, exactly as before.
		if ( aw_sourcing_cli_summary_with( $old['aw_summary_rules'] ?? false ) === aw_summary_rules() ) {
			$cmp['planned'][] = 'GET summary-rules: the v1 rules (news, library, research and the global keys) identical to before';
		} else {
			$cmp['diffs'][] = 'GET summary-rules: the v1 rules differ from before';
		}
		$by = aw_summary_rules_payload()['by_type'] ?? array();
		if ( array_keys( $by ) === $types ) {
			$cmp['planned'][] = 'GET summary-rules: by_type for ' . count( $by ) . ' sections (' . count( array_filter( $by, fn( $r ) => '' !== (string) ( $r['inherit'] ?? '' ) ) ) . ' inherit News)';
		} else {
			$cmp['diffs'][] = 'GET summary-rules: by_type does not list every section';
		}
		$cfg = aw_enrich_rest_config();
		if ( isset( $cfg['field_rules'], $cfg['schema'], $cfg['priority'] ) ) {
			$cmp['planned'][] = 'GET enrich/config: schema (' . count( $cfg['schema']['fields'] ) . ' listing fields), priority and field_rules (' . count( (array) $cfg['field_rules'] ) . ')';
		} else {
			$cmp['diffs'][] = 'GET enrich/config: schema, priority or field_rules missing';
		}
		// The policy: sections_v2 holds the same lists as the v1 sections dict.
		$policy = apply_filters( 'kits_sourcing_policy', array() );
		if ( isset( $policy['config']['sections'] ) ) {
			$v1 = (array) $policy['config']['sections'];
			$ok = true;
			foreach ( (array) ( $policy['config']['sections_v2'] ?? array() ) as $e ) {
				$want = $v1[ $e['post_type'] ] ?? null;
				$ok   = $ok && is_array( $want ) && array_values( (array) ( $want['categories'] ?? array() ) ) === $e['categories'] && array_values( (array) ( $want['tags'] ?? array() ) ) === $e['tags'];
			}
			if ( $ok && count( (array) ( $policy['config']['sections_v2'] ?? array() ) ) === count( $types ) ) {
				$cmp['planned'][] = 'policy: sections_v2 beside the v1 sections (' . count( $types ) . ' sections, the same lists)';
			} else {
				$cmp['diffs'][] = 'policy: sections_v2 does not match the v1 sections';
			}
		} else {
			WP_CLI::log( 'policy: "Workers use these lists" is off, so config (and sections_v2) is not sent.' );
		}
		foreach ( $cmp['planned'] as $p ) {
			WP_CLI::log( "planned: $p" );
		}
		foreach ( $cmp['diffs'] as $d ) {
			WP_CLI::warning( "difference: $d" );
		}
		if ( $cmp['diffs'] ) {
			WP_CLI::error( count( $cmp['diffs'] ) . ' differences beyond the planned changes.' );
		}
		WP_CLI::success( '0 differences apart from the planned changes.' );
	}
);

add_action(
	'aw_sourcing_cli_rollback_3',
	function ( $assoc ) {
		list( $bk, $old ) = aw_sourcing_cli_backup( 3 );
		$all              = ! empty( $assoc['restore-all'] );
		WP_CLI::confirm( 'Roll phase 3 back ' . ( $all ? "to backup $bk exactly (aw_sources, aw_summary_rules and aw_enrich_field_rules as backed up)" : '(undo the migration on the current options)' ) . '?', $assoc );
		$c   = (array) get_option( 'aw_sources', array() );
		$sum = get_option( AW_SUMMARY_RULES_OPTION );
		$fr  = get_option( AW_ENRICH_RULES_OPTION );
		// What the rollback takes away is kept (the owner's edits since the migration are not lost).
		update_option(
			'aw_sourcing_phase3_rolled_back',
			array(
				'at'                    => current_time( 'mysql' ),
				'sections'              => $c['sections'] ?? array(),
				'topic_tags'            => $c['topic_tags'] ?? array(),
				'aw_summary_rules'      => $sum,
				'aw_enrich_field_rules' => $fr,
			),
			false
		);
		if ( $all ) {
			update_option( 'aw_sources', $old['aw_sources'], false );
			foreach ( array( AW_SUMMARY_RULES_OPTION, AW_ENRICH_RULES_OPTION ) as $k ) {
				if ( null === $old[ $k ] || false === $old[ $k ] ) {
					delete_option( $k );
				} else {
					update_option( $k, $old[ $k ], AW_SUMMARY_RULES_OPTION === $k ? null : false );
				}
			}
			$what = "restored aw_sources, aw_summary_rules and aw_enrich_field_rules from $bk";
		} else {
			$rec  = (array) get_option( 'aw_sourcing_phase3_record', array() );
			$undo = aw_rules_phase3_undo( $c, $sum, (array) ( $rec['added'] ?? array() ) );
			update_option( 'aw_sources', $undo['sources'], false );
			if ( is_array( $undo['summary'] ) ) {
				update_option( AW_SUMMARY_RULES_OPTION, $undo['summary'] );
			}
			delete_option( AW_ENRICH_RULES_OPTION ); // the code's rules apply again
			$what = sprintf( 'section rules removed for %s%s; summary rules back to their old names; field rules off (kept in aw_sourcing_phase3_rolled_back)', $undo['removed'] ? implode( ', ', $undo['removed'] ) : 'none', $undo['kept'] ? ' (kept, filled in since: ' . implode( ', ', $undo['kept'] ) . ')' : '' );
		}
		if ( null === $old['aw_sourcing_phase'] ) {
			delete_option( 'aw_sourcing_phase' );
		} else {
			update_option( 'aw_sourcing_phase', $old['aw_sourcing_phase'], false );
		}
		delete_option( 'aw_sourcing_phase3_record' );
		delete_option( 'aw_backup_latest_3' ); // a new migration starts from a new backup (the backups themselves stay)
		kits_sourcing_log( 'sections', "Phase 3 rolled back (WP-CLI): $what", 'phase 3', 'phase ' . aw_sourcing_phase() );
		WP_CLI::success( "Rolled back: $what. The backup stays ($bk)." );
	}
);
