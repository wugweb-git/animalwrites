<?php
/**
 * Sourcing restructure phase 4 (Retire; owner, 25 Oct 2026) from WP-CLI, with the commands of phases 1–3
 * (sourcing-migrate.php):
 *
 *     wp aw-sourcing backup --phase=4
 *     wp aw-sourcing migrate --phase=4 --dry-run
 *     wp aw-sourcing migrate --phase=4
 *     wp aw-sourcing verify --phase=4
 *     wp aw-sourcing rollback --phase=4 [--restore-all]
 *
 * The migration (retire-model.php; owner, 25 Oct 2026: "make it, but don't delete the old version"): nothing old is
 * deleted or changed. The old lists (aw_relevance strong / animal / context / exclude, aw_sources categories, keywords
 * and press.queries, and the option aw_sourcing_dismissed) are copied into the archive option aw_sourcing_v1_archive
 * (autoload off, dated, with checksums), the copy is read back and checked, and only then is the phase set to 4: from
 * then on the site stops rebuilding, reading and sending them (the v1 code stays, switched by the phase). It needs phase
 * 3 migrated and refuses while a word of an old list has no keyword record with that role, or a dismissed search no
 * dismissed record.
 *
 * While it runs the option aw_sourcing_migrating is 4; the record aw_sourcing_phase4_record is saved before each step.
 * Rollback sets the phase back to 3 (the options were never changed, so everything is as it was); with --restore-all it
 * also puts the backed-up options back exactly. The archive option stays (a copy, read by nothing). The old lists hold
 * what they held at the migration: the next keyword save at phase 3 rebuilds them from the table, as before.
 *
 * Backups are never deleted here: the aw_backup_* options and the files in wp-content/uploads/aw-backups stay until the
 * owner says otherwise.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/** Options a phase 4 backup holds. */
const AW_SOURCING_BACKUP_OPTIONS_4 = array( 'aw_relevance', 'aw_sources', 'aw_sourcing_dismissed', 'aw_sourcing_v1_archive', 'kits_sourcing_log', 'aw_sourcing_phase' );

/** The archive of the old lists (phase 4). */
const AW_SOURCING_V1_ARCHIVE = 'aw_sourcing_v1_archive';

add_filter( 'aw_sourcing_cli_phases', fn( $p ) => array_values( array_unique( array_merge( (array) $p, array( 4 ) ) ) ) );
add_filter( 'aw_sourcing_cli_backup_options', fn( $o, $phase ) => 4 === (int) $phase ? AW_SOURCING_BACKUP_OPTIONS_4 : $o, 10, 2 );
add_filter( 'aw_sourcing_cli_counts', fn( $line, $phase, $opts ) => 4 === (int) $phase ? aw_sourcing_cli_counts4( (array) $opts ) : $line, 10, 3 );

// Phases 1–3 may not be rolled back while phase 4 is migrated (the phases go back in order).
add_filter(
	'aw_sourcing_cli_refusal',
	function ( $why, $phase, $command ) {
		if ( '' !== $why || 'rollback' !== $command ) {
			return $why;
		}
		return aw_retire_refusal( (int) $phase, aw_sourcing_phase(), (bool) get_option( 'aw_sourcing_phase4_record' ) );
	},
	10,
	3
);

/**
 * One line of counts for a phase 4 backup or for the options now.
 *
 * @param array $opts Option name => value.
 * @return string
 */
function aw_sourcing_cli_counts4( array $opts ) {
	$r = (array) ( $opts['aw_relevance'] ?? array() );
	$c = (array) ( $opts['aw_sources'] ?? array() );
	$n = fn( $v ) => null === $v ? 'gone' : (string) count( (array) $v );
	return sprintf(
		'aw_relevance: Include %s · Animal %s · Context %s · Exclude %s · switches %s · aw_sources: category words %s · seed keywords %s · press queries %s · aw_sourcing_dismissed %s · archive %s · kits_sourcing_log %d · phase %d',
		$n( $r['strong'] ?? null ),
		$n( $r['animal'] ?? null ),
		$n( $r['context'] ?? null ),
		$n( $r['exclude'] ?? null ),
		( empty( $r['enabled'] ) ? 'filter off' : 'filter on' ) . ', ' . ( empty( $r['gnews_allow_only'] ) ? 'aggregators open' : 'aggregators allowed-only' ),
		array_key_exists( 'categories', $c ) ? (string) array_sum( array_map( fn( $w ) => count( (array) $w ), (array) $c['categories'] ) ) : 'none',
		$n( $c['keywords'] ?? null ),
		$n( $c['press']['queries'] ?? null ),
		$n( $opts['aw_sourcing_dismissed'] ?? null ),
		is_array( $opts['aw_sourcing_v1_archive'] ?? null ) ? 'of ' . (string) ( $opts['aw_sourcing_v1_archive']['at'] ?? '?' ) : 'none',
		count( (array) ( $opts['kits_sourcing_log'] ?? array() ) ),
		(int) ( $opts['aw_sourcing_phase'] ?? 0 )
	);
}

/** The options a phase 4 backup holds, as they are now. */
function aw_sourcing_cli_now4() {
	$now = array();
	foreach ( AW_SOURCING_BACKUP_OPTIONS_4 as $k ) {
		$now[ $k ] = get_option( $k, null );
	}
	return $now;
}

/** Every keyword record (with its maps): the old lists are checked against all of them. */
function aw_sourcing_cli_p4_rows() {
	if ( ! function_exists( 'aw_kw_ready' ) || ! aw_kw_ready() ) {
		WP_CLI::error( 'The keyword table is not ready (Animal Writes Core): phase 4 needs it.' );
	}
	return aw_kw_rows( 0 );
}

/**
 * The plan on the options as they are now.
 *
 * @return array aw_retire_plan().
 */
function aw_sourcing_cli_p4_plan() {
	return aw_retire_plan(
		(array) get_option( AW_REL_OPTION, array() ),
		(array) get_option( 'aw_sources', array() ),
		array_values( array_filter( (array) get_option( 'aw_sourcing_dismissed', array() ), 'is_string' ) ),
		aw_sourcing_cli_p4_rows()
	);
}

add_action(
	'aw_sourcing_cli_migrate_4',
	function ( $assoc, $dry ) {
		if ( aw_sourcing_phase() >= 4 ) {
			WP_CLI::error( 'Phase 4 is already migrated (aw_sourcing_phase = ' . aw_sourcing_phase() . '). wp aw-sourcing verify --phase=4 checks it.' );
		}
		if ( ! $dry && get_option( 'aw_sourcing_phase4_record' ) ) {
			WP_CLI::error( 'An unfinished phase 4 migration is recorded (aw_sourcing_phase4_record). Undo it first: wp aw-sourcing rollback --phase=4' );
		}
		if ( 3 !== aw_sourcing_phase() ) {
			$msg = 'Phase 4 runs after phase 3 (aw_sourcing_phase = ' . aw_sourcing_phase() . '): migrate phase 3 first.';
			if ( ! $dry ) {
				WP_CLI::error( $msg );
			}
			WP_CLI::warning( $msg . ' This dry run shows what phase 4 would do to the options as they are.' );
		}
		$bk = '';
		if ( ! $dry ) {
			list( $bk ) = aw_sourcing_cli_backup( 4, (string) get_option( 'aw_backup_latest_4', '' ) ); // refuses without a backup
			WP_CLI::log( "Backup: $bk" );
		}
		$plan = aw_sourcing_cli_p4_plan();
		WP_CLI::log( 'Now:   ' . aw_sourcing_cli_counts4( aw_sourcing_cli_now4() ) );
		foreach ( $plan['counts'] as $where => $n ) {
			WP_CLI::log( sprintf( '  archive %s (%d) into aw_sourcing_v1_archive, then stop using it; the option itself is not changed', $where, $n ) );
		}
		WP_CLI::log( '  still used: aw_relevance enabled and gnews_allow_only; aw_sources press enabled, date_floor, every_days and every other key' );
		WP_CLI::log( '  the policy: no relevance lists; config without keywords, sources, categories, sections and press.queries' );
		WP_CLI::log( '  the old Press queries, Categories and Sections tabs are hidden (a rollback shows them again)' );
		WP_CLI::log( '  nothing is deleted: the options, every aw_backup_* option and every wp-content/uploads/aw-backups file stay' );
		foreach ( $plan['planned'] as $p ) {
			WP_CLI::log( "planned: $p" );
		}
		foreach ( $plan['conflicts'] as $c ) {
			WP_CLI::warning( "conflict: $c" );
		}
		if ( $dry ) {
			WP_CLI::success( 'Dry run: nothing written. ' . count( $plan['conflicts'] ) . ' conflicts.' );
			return;
		}
		if ( $plan['conflicts'] ) {
			WP_CLI::error( 'Not migrated: give every word above a keyword record with its role first (Settings → Sourcing → Keywords).' );
		}

		// The record first; then the archive, read back and checked; only then the phase. No option is changed.
		update_option( 'aw_sourcing_migrating', 4, false );
		$record = array(
			'at'     => current_time( 'mysql' ),
			'backup' => $bk,
			'counts' => $plan['counts'],
			'done'   => array(),
		);
		$save   = function () use ( &$record ) {
			update_option( 'aw_sourcing_phase4_record', $record, false );
		};
		$save();
		$archive = aw_retire_archive( aw_sourcing_cli_p4_plan(), $record['at'], $bk ); // the options as they are now
		update_option( AW_SOURCING_V1_ARCHIVE, $archive, false ); // autoload off
		$check = aw_retire_archive_problems( (array) get_option( AW_SOURCING_V1_ARCHIVE, array() ), aw_sourcing_cli_p4_plan() );
		if ( $check ) {
			WP_CLI::error( 'Not migrated: the archive copy does not match the options (' . implode( '; ', $check ) . '). The phase stays 3; wp aw-sourcing rollback --phase=4 clears the record.' );
		}
		$record['done'][] = AW_SOURCING_V1_ARCHIVE;
		$save();
		update_option( 'aw_sourcing_phase', 4, false );
		$record['complete'] = true;
		$save();
		delete_option( 'aw_sourcing_migrating' );
		$entries = array();
		foreach ( $plan['counts'] as $where => $n ) {
			$entries[] = array( 'keywords', sprintf( 'Phase 4 migrated (WP-CLI): %s archived and no longer used (%d; the keyword table holds them; the option is unchanged)', $where, $n ), 'in use', 'archived' );
		}
		$entries[] = array( 'keywords', 'Phase 4 migrated (WP-CLI): the sourcing policy sends no v1 keys; the old Press queries, Categories and Sections tabs are hidden', 'phase 3', 'phase 4' );
		kits_sourcing_log_many( $entries );
		WP_CLI::success( 'Phase 4 migrated. Next: wp aw-sourcing verify --phase=4' );
	},
	10,
	2
);

add_action(
	'aw_sourcing_cli_verify_4',
	function () {
		list( $bk, $old ) = aw_sourcing_cli_backup( 4 );
		$now              = aw_sourcing_cli_now4();
		WP_CLI::log( "Backup: $bk" );
		WP_CLI::log( 'Backup: ' . aw_sourcing_cli_counts4( $old ) );
		WP_CLI::log( 'Now:    ' . aw_sourcing_cli_counts4( $now ) );
		if ( aw_sourcing_phase() < 4 ) {
			WP_CLI::error( 'Phase 4 is not migrated (aw_sourcing_phase = ' . aw_sourcing_phase() . ').' );
		}
		$diffs   = array();
		$planned = array( 'aw_sourcing_phase 3 → 4' );
		// Nothing old changed: the options exactly as backed up.
		foreach ( array( AW_REL_OPTION, 'aw_sources', 'aw_sourcing_dismissed' ) as $k ) {
			if ( $old[ $k ] !== $now[ $k ] ) {
				WP_CLI::warning( "$k is not as backed up (an edit since the backup? phase 4 changes no option)" );
			} else {
				$planned[] = "$k unchanged";
			}
		}
		// The archive: the old lists exactly as the options hold them (phase 4 never changes them), with its checksums;
		// against the backup only as a warning (a keyword saved before the migration rebuilt them since).
		$plan_old = aw_retire_plan( (array) $old['aw_relevance'], (array) $old['aw_sources'], array_values( array_filter( (array) $old['aw_sourcing_dismissed'], 'is_string' ) ), aw_sourcing_cli_p4_rows() );
		$plan_now = aw_sourcing_cli_p4_plan();
		if ( ! is_array( $now['aw_sourcing_v1_archive'] ) ) {
			$diffs[] = 'aw_sourcing_v1_archive is missing';
		} else {
			foreach ( aw_retire_archive_problems( $now['aw_sourcing_v1_archive'], $plan_now ) as $p ) {
				$diffs[] = $p;
			}
			foreach ( aw_retire_archive_problems( $now['aw_sourcing_v1_archive'], $plan_old ) as $p ) {
				WP_CLI::warning( "against the backup: $p (a keyword saved between the backup and the migration?)" );
			}
			$planned[] = 'aw_sourcing_v1_archive holds the old lists of ' . (string) $now['aw_sourcing_v1_archive']['at'] . ' (' . implode( ', ', array_map( fn( $k, $n ) => "$k $n", array_keys( $plan_old['counts'] ), $plan_old['counts'] ) ) . ')';
		}
		// Nothing only the old lists hold stopped being used: every word has its keyword record and role.
		foreach ( $plan_now['conflicts'] as $c ) {
			$diffs[] = "no longer used: $c";
		}
		$planned = array_merge( $planned, $plan_now['planned'] );
		// The workers' view: the same Google News searches from the keywords as from the backed-up v1 lists.
		if ( function_exists( 'aw_kw_v1_searches' ) ) {
			// As phase 2's verify: v1 sent every source that is on (the open-web searches too) plus the Include words;
			// v2 sends the sources that are not keywords plus the keywords' searches.
			$srcs = (array) ( $now['aw_sources']['sources'] ?? array() );
			$on   = array_values( array_filter( $srcs, fn( $s ) => is_array( $s ) && ! empty( $s['on'] ) ) );
			$v1   = aw_kw_v1_searches( $on, (array) ( $old['aw_relevance']['strong'] ?? array() ) );
			$v2   = aw_kw_v2_searches( array_values( array_filter( $on, fn( $s ) => empty( $s['keyword_id'] ) ) ), aw_kw_payload( aw_kw_rows( AW_KW_WORKER_ROLES, true ), $srcs ) );
			ksort( $v1 );
			ksort( $v2 );
			if ( $v1 === $v2 ) {
				$planned[] = 'Google News searches: the same ' . count( $v2 ) . ' (feed id and query) from the keywords as from the backed-up lists';
			} else {
				WP_CLI::warning( 'Google News searches differ from the backed-up lists (a keyword changed since the backup?): ' . implode( ', ', array_keys( array_diff_assoc( $v1, $v2 ) + array_diff_assoc( $v2, $v1 ) ) ) );
			}
		}
		// The policy: no v1 keys, every v2 block.
		$policy = aw_sourcing_policy_payload();
		if ( ! isset( $policy['config'] ) ) {
			WP_CLI::log( 'policy: "Workers use these lists" is off, so config is not sent.' );
		}
		foreach ( aw_retire_policy_problems( $policy ) as $p ) {
			$diffs[] = "policy: $p";
		}
		if ( ! aw_retire_policy_problems( $policy ) ) {
			$planned[] = 'policy: no v1 keys; sources_v2, keywords, sections_v2, category_names, match and schema sent';
		}
		foreach ( $planned as $p ) {
			WP_CLI::log( "planned: $p" );
		}
		foreach ( $diffs as $d ) {
			WP_CLI::warning( "difference: $d" );
		}
		if ( $diffs ) {
			WP_CLI::error( count( $diffs ) . ' differences beyond the planned changes.' );
		}
		WP_CLI::success( '0 differences apart from the planned changes.' );
	}
);

add_action(
	'aw_sourcing_cli_rollback_4',
	function ( $assoc ) {
		$rec = get_option( 'aw_sourcing_phase4_record' );
		if ( aw_sourcing_phase() < 4 && ! $rec ) {
			WP_CLI::error( 'Phase 4 is not migrated (aw_sourcing_phase = ' . aw_sourcing_phase() . ').' );
		}
		list( $bk, $old ) = aw_sourcing_cli_backup( 4, is_array( $rec ) && ! empty( $rec['backup'] ) ? (string) $rec['backup'] : '' ); // the run's own backup
		$all              = ! empty( $assoc['restore-all'] );
		WP_CLI::confirm( 'Roll phase 4 back ' . ( $all ? "and put aw_relevance, aw_sources and aw_sourcing_dismissed back exactly as in backup $bk" : '(the phase back to 3: the old lists, tabs and policy keys are used again; no option was changed)' ) . '?', $assoc );
		update_option( 'aw_sourcing_migrating', 4, false );
		$what = 'phase back to 3: the old lists, the old tabs and the v1 policy keys are in use again';
		if ( $all ) {
			foreach ( array( AW_REL_OPTION, 'aw_sources', 'aw_sourcing_dismissed' ) as $k ) {
				if ( null === $old[ $k ] ) {
					delete_option( $k ); // it did not exist when the backup was taken
				} else {
					update_option( $k, $old[ $k ], false );
				}
			}
			$what .= "; aw_relevance, aw_sources and aw_sourcing_dismissed restored from $bk";
		}
		if ( null === $old['aw_sourcing_phase'] ) {
			delete_option( 'aw_sourcing_phase' );
		} else {
			update_option( 'aw_sourcing_phase', min( 3, (int) $old['aw_sourcing_phase'] ), false );
		}
		delete_option( 'aw_sourcing_phase4_record' );
		delete_option( 'aw_backup_latest_4' ); // a new migration starts from a new backup (the backups themselves stay)
		delete_option( 'aw_sourcing_migrating' );
		kits_sourcing_log( 'keywords', "Phase 4 rolled back (WP-CLI): $what. The archive aw_sourcing_v1_archive stays.", 'phase 4', 'phase ' . aw_sourcing_phase() );
		WP_CLI::success( "Rolled back: $what. The backup ($bk) and the archive stay." );
	}
);
