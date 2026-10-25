<?php
/**
 * Sourcing restructure (owner, 25 Oct 2026): back up, migrate, verify and roll back each phase from WP-CLI. Phase 1
 * (Sources): every source gets its publisher host; the relay allow list becomes a switch on each source
 * (aw_publishers[host].relay, a kind=relay record for a site that is not a publisher; substack.com narrowed to
 * peopleforanimals.substack.com, decision 8); the policy adds sources_v2. The method is PR #40's round trip: the
 * backup first, a dry run that prints every count, the migration, then verify rebuilds the old shapes and compares
 * them with the backup. Rollback undoes the migration on the current records (keeping what the ingest gate wrote since)
 * or, with --restore-all, puts the backed-up options back as they were.
 *
 *     wp aw-sourcing backup --phase=1
 *     wp aw-sourcing migrate --phase=1 --dry-run
 *     wp aw-sourcing migrate --phase=1
 *     wp aw-sourcing verify --phase=1
 *     wp aw-sourcing rollback --phase=1 [--restore-all]
 *
 * Backups: option aw_backup_<phase>_<YmdHis> (autoload off) and the file wp-content/uploads/aw-backups/<phase>-<YmdHis>.json
 * (the folder refuses web access). The migration record: option aw_sourcing_phase1_record.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/** Options a phase 1 backup holds. */
const AW_SOURCING_BACKUP_OPTIONS = array( 'aw_publishers', 'aw_sources', 'aw_relay_log', 'kits_sourcing_log', 'aw_sourcing_phase' );

/** The phase asked for (only 1 so far), or an error. */
function aw_sourcing_cli_phase( $assoc ) {
	$phase = (int) ( $assoc['phase'] ?? 0 );
	if ( 1 !== $phase ) {
		WP_CLI::error( 'Only --phase=1 exists so far.' );
	}
	return $phase;
}

/** A value's checksum (sha256 of its JSON). */
function aw_sourcing_cli_sum( $v ) {
	return hash( 'sha256', (string) wp_json_encode( $v ) );
}

/** One line of counts for the options a backup holds. */
function aw_sourcing_cli_counts( array $opts ) {
	$c = (array) ( $opts['aw_sources'] ?? array() );
	return sprintf(
		'aw_publishers %d · aw_sources.sources %d · aw_relay_log %d · kits_sourcing_log %d · phase %d',
		count( (array) ( $opts['aw_publishers'] ?? array() ) ),
		count( (array) ( $c['sources'] ?? array() ) ),
		count( (array) ( $opts['aw_relay_log'] ?? array() ) ),
		count( (array) ( $opts['kits_sourcing_log'] ?? array() ) ),
		(int) ( $opts['aw_sourcing_phase'] ?? 0 )
	);
}

/**
 * The backup of a phase taken before it was migrated: [option name, options], or an error. Migrate uses the newest one;
 * verify and rollback the one the migration recorded. A backup taken after the migration is never used.
 *
 * @param int    $phase Phase.
 * @param string $name  Backup option name ('' for the one to use).
 * @return array
 */
function aw_sourcing_cli_backup( $phase, $name = '' ) {
	if ( '' === $name ) {
		$rec  = get_option( 'aw_sourcing_phase' . $phase . '_record' );
		$name = is_array( $rec ) && ! empty( $rec['backup'] ) && aw_sourcing_phase() >= $phase ? (string) $rec['backup'] : (string) get_option( 'aw_backup_latest_' . $phase, '' );
	}
	$data = '' !== $name ? get_option( $name ) : false;
	if ( ! is_array( $data ) || empty( $data['options'] ) ) {
		WP_CLI::error( "No backup for phase $phase. Run: wp aw-sourcing backup --phase=$phase" );
	}
	if ( (int) ( $data['options']['aw_sourcing_phase'] ?? 0 ) >= $phase ) {
		WP_CLI::error( "Backup $name was taken after phase $phase was migrated; it cannot be used to migrate, verify or roll back. Take a backup before migrating." );
	}
	foreach ( (array) $data['options'] as $k => $v ) {
		if ( ( $data['sums'][ $k ] ?? '' ) !== aw_sourcing_cli_sum( $v ) ) {
			WP_CLI::error( "Backup $name: the checksum of $k does not match." );
		}
	}
	return array( $name, (array) $data['options'] );
}

/** The relay allow list as it was before phase 1, from a backup's aw_sources (as aw_relay_hosts_legacy reads it). */
function aw_sourcing_cli_relay_before( array $sources_option ) {
	return aw_src_relay_legacy( $sources_option, AW_RELAY_DEFAULT );
}

/**
 * Back up the options a phase changes: an option (autoload off) and a server file, with counts and checksums.
 *
 * ## OPTIONS
 *
 * --phase=<n>
 * : The phase (1).
 */
WP_CLI::add_command(
	'aw-sourcing backup',
	function ( $args, $assoc ) {
		$phase = aw_sourcing_cli_phase( $assoc );
		$stamp = gmdate( 'YmdHis' );
		$opts  = array();
		$sums  = array();
		foreach ( AW_SOURCING_BACKUP_OPTIONS as $k ) {
			$opts[ $k ] = get_option( $k, null );
			$sums[ $k ] = aw_sourcing_cli_sum( $opts[ $k ] );
		}
		$data = array( 'phase' => $phase, 'at' => gmdate( 'c' ), 'site' => home_url(), 'options' => $opts, 'sums' => $sums );
		$name = 'aw_backup_' . $phase . '_' . $stamp;
		add_option( $name, $data, '', false ); // autoload off
		if ( get_option( $name ) !== $data ) {
			WP_CLI::error( "The backup option $name could not be written." );
		}
		$up  = wp_upload_dir();
		$dir = trailingslashit( $up['basedir'] ) . 'aw-backups';
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			WP_CLI::error( "Could not create $dir (the option backup $name was written)." );
		}
		// phpcs:disable WordPress.WP.AlternativeFunctions -- a server file written from WP-CLI.
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" );
		}
		if ( ! file_exists( $dir . '/index.php' ) ) {
			file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
		}
		$file = $dir . '/' . $phase . '-' . $stamp . '.json';
		$n    = file_put_contents( $file, (string) wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		// phpcs:enable WordPress.WP.AlternativeFunctions
		clearstatcache();
		if ( ! $n || filesize( $file ) < 100 ) { // an empty file is no backup (owner, 24 Oct 2026)
			WP_CLI::error( "The backup file $file is empty (the option backup $name was written)." );
		}
		if ( aw_sourcing_phase() < $phase ) {
			update_option( 'aw_backup_latest_' . $phase, $name, false ); // the one migrate uses
		} else {
			WP_CLI::warning( "Phase $phase is already migrated: this backup is kept, but migrate, verify and rollback use the one taken before the migration." );
		}
		WP_CLI::log( aw_sourcing_cli_counts( $opts ) );
		foreach ( $sums as $k => $v ) {
			WP_CLI::log( "sha256 $k: $v" );
		}
		WP_CLI::success( "Backup $name, and $file (" . size_format( filesize( $file ) ) . ').' );
	},
	array( 'shortdesc' => 'Back up the sourcing options a restructure phase changes.' )
);

/**
 * Migrate a phase: with --dry-run, print what would change and write nothing.
 *
 * ## OPTIONS
 *
 * --phase=<n>
 * : The phase (1).
 *
 * [--dry-run]
 * : Print every count before and after; write nothing.
 */
WP_CLI::add_command(
	'aw-sourcing migrate',
	function ( $args, $assoc ) {
		$phase = aw_sourcing_cli_phase( $assoc );
		$dry   = ! empty( $assoc['dry-run'] );
		if ( aw_sourcing_phase() >= $phase ) {
			WP_CLI::error( "Phase $phase is already migrated (aw_sourcing_phase = " . aw_sourcing_phase() . '). wp aw-sourcing verify --phase=1 checks it.' );
		}
		$bk = '';
		if ( ! $dry ) {
			list( $bk ) = aw_sourcing_cli_backup( $phase, (string) get_option( 'aw_backup_latest_' . $phase, '' ) ); // refuses without a backup
			WP_CLI::log( "Backup: $bk" );
		}
		$c      = (array) get_option( 'aw_sources', array() );
		$before = aw_sourcing_cli_relay_before( $c );
		$pubs   = aw_publishers();
		$plan   = aw_src_phase1_plan( $pubs, (array) ( $c['sources'] ?? array() ), $before, current_time( 'Y-m-d' ) );
		WP_CLI::log( sprintf( 'Sources: %d before, %d after (feed ids unchanged); host added to %d.', count( (array) ( $c['sources'] ?? array() ) ), count( $plan['sources'] ), count( array_filter( $plan['sources'], fn( $s ) => ! empty( $s['host'] ) ) ) - count( array_filter( (array) ( $c['sources'] ?? array() ), fn( $s ) => ! empty( $s['host'] ) ) ) ) );
		foreach ( $plan['hosts'] as $id => $h ) {
			WP_CLI::log( "  $id → $h" . ( isset( $pubs[ $h ] ) ? '' : ' (no publisher record: shown as a site feeds come from)' ) );
		}
		WP_CLI::log( sprintf( 'Publishers: %d before, %d after.', count( $pubs ), count( $plan['pubs'] ) ) );
		WP_CLI::log( 'Relay hosts before (' . count( $plan['relay_before'] ) . '): ' . implode( ', ', $plan['relay_before'] ) );
		WP_CLI::log( 'Relay hosts after (' . count( $plan['relay_after'] ) . '): ' . implode( ', ', $plan['relay_after'] ) );
		foreach ( $plan['mapped'] as $from => $to ) {
			WP_CLI::log( "  narrowed: $from → $to (owner's decision 8)" );
		}
		WP_CLI::log( 'Relay switched on for: ' . ( $plan['flagged'] ? implode( ', ', $plan['flagged'] ) : '(none)' ) );
		WP_CLI::log( 'Relay-only records added: ' . ( $plan['added'] ? implode( ', ', $plan['added'] ) : '(none)' ) );
		$cmp = aw_src_phase1_compare( $pubs, (array) ( $c['sources'] ?? array() ), $plan['pubs'], $plan['sources'], $before, $plan['relay_after'], (array) get_option( 'aw_relay_log', array() ) );
		foreach ( $cmp['diffs'] as $d ) {
			WP_CLI::warning( 'conflict: ' . $d );
		}
		if ( $dry ) {
			WP_CLI::success( 'Dry run: nothing written. ' . count( $cmp['diffs'] ) . ' conflicts.' );
			return;
		}
		if ( $cmp['diffs'] ) {
			WP_CLI::error( 'Not migrated: resolve the conflicts above first.' );
		}
		// Read-modify-write right before saving (the ingest gate writes aw_publishers too): apply the plan's changes
		// to the records as they are now.
		$now = aw_publishers();
		foreach ( $plan['flagged'] as $h ) {
			if ( isset( $now[ $h ] ) ) {
				$now[ $h ]['relay'] = true;
			}
		}
		foreach ( $plan['added'] as $h ) {
			if ( ! isset( $now[ $h ] ) ) {
				$now[ $h ] = $plan['pubs'][ $h ];
			} else {
				$now[ $h ]['relay'] = true;
			}
		}
		update_option( AW_PUB_OPTION, $now, false );
		$c            = (array) get_option( 'aw_sources', array() );
		$hostplan     = aw_src_phase1_plan( $now, (array) ( $c['sources'] ?? array() ), array(), current_time( 'Y-m-d' ) );
		$c['sources'] = $hostplan['sources'];
		update_option( 'aw_sources', $c, false );
		update_option( 'aw_sourcing_phase1_record', array( 'at' => current_time( 'mysql' ), 'backup' => $bk, 'flagged' => $plan['flagged'], 'added' => $plan['added'], 'hosted' => $hostplan['hosted'], 'mapped' => $plan['mapped'], 'relay_before' => $plan['relay_before'] ), false );
		update_option( 'aw_sourcing_phase', 1, false );
		kits_sourcing_log(
			'sources',
			sprintf( 'Phase 1 migrated (WP-CLI): %d sources got their publisher; relay on for %d sources, %d relay-only record(s) added; relay hosts %d → %d', count( $c['sources'] ), count( $plan['flagged'] ), count( $plan['added'] ), count( $plan['relay_before'] ), count( aw_relay_hosts() ) ),
			$plan['relay_before'],
			aw_relay_hosts()
		);
		WP_CLI::success( 'Phase 1 migrated. Next: wp aw-sourcing verify --phase=1' );
	},
	array( 'shortdesc' => 'Migrate a sourcing restructure phase (--dry-run to see it first).' )
);

/**
 * Verify a migrated phase against its backup: 0 differences apart from the planned changes, listed by name.
 *
 * ## OPTIONS
 *
 * --phase=<n>
 * : The phase (1).
 */
WP_CLI::add_command(
	'aw-sourcing verify',
	function ( $args, $assoc ) {
		$phase            = aw_sourcing_cli_phase( $assoc );
		list( $bk, $old ) = aw_sourcing_cli_backup( $phase );
		WP_CLI::log( "Backup: $bk" );
		WP_CLI::log( 'Backup: ' . aw_sourcing_cli_counts( $old ) );
		$now = array();
		foreach ( AW_SOURCING_BACKUP_OPTIONS as $k ) {
			$now[ $k ] = get_option( $k, null );
		}
		WP_CLI::log( 'Now:    ' . aw_sourcing_cli_counts( $now ) );
		if ( aw_sourcing_phase() < $phase ) {
			WP_CLI::error( "Phase $phase is not migrated (aw_sourcing_phase = " . aw_sourcing_phase() . ').' );
		}
		$c_old = (array) $old['aw_sources'];
		$c_now = (array) $now['aw_sources'];
		$cmp   = aw_src_phase1_compare( (array) $old['aw_publishers'], (array) ( $c_old['sources'] ?? array() ), aw_publishers(), (array) ( $c_now['sources'] ?? array() ), aw_sourcing_cli_relay_before( $c_old ), aw_relay_hosts(), (array) get_option( 'aw_relay_log', array() ) );
		// The rest of aw_sources: unchanged.
		$rest_old = $c_old;
		$rest_now = $c_now;
		unset( $rest_old['sources'], $rest_now['sources'] );
		if ( $rest_old !== $rest_now ) {
			$cmp['diffs'][] = 'aw_sources (other than sources) changed: ' . implode( ', ', array_keys( array_filter( array_unique( array_merge( array_keys( $rest_old ), array_keys( $rest_now ) ) ), fn( $k ) => ( $rest_old[ $k ] ?? null ) !== ( $rest_now[ $k ] ?? null ) ) ) );
		}
		// The policy: sources_v2 is the v1 list plus host, relay, read_by and check; relay_hosts is the switch list.
		$policy = apply_filters( 'kits_sourcing_policy', array() );
		if ( isset( $policy['config']['sources'] ) ) {
			$v1 = (array) $policy['config']['sources'];
			$v2 = (array) ( $policy['config']['sources_v2'] ?? array() );
			$ok = count( $v1 ) === count( $v2 );
			foreach ( $v2 as $i => $e ) {
				unset( $e['relay'], $e['read_by'], $e['check'] );
				$ok = $ok && $e === $v1[ $i ];
			}
			if ( $ok ) {
				$cmp['planned'][] = 'policy: sources_v2 beside the v1 list (' . count( $v2 ) . ' sources, the same ids and keywords)';
			} else {
				$cmp['diffs'][] = 'policy: sources_v2 does not match the v1 sources list';
			}
		} else {
			WP_CLI::log( 'policy: "Workers use these lists" is off, so config (and sources_v2) is not sent.' );
		}
		$rec = (array) get_option( 'aw_sourcing_phase1_record', array() );
		if ( isset( $rec['relay_before'] ) && array_values( (array) $rec['relay_before'] ) !== aw_sourcing_cli_relay_before( $c_old ) ) {
			$cmp['diffs'][] = 'the relay list the migration started from (' . implode( ', ', (array) $rec['relay_before'] ) . ') is not the backup\'s';
		}
		foreach ( $cmp['planned'] as $p ) {
			WP_CLI::log( "planned: $p" );
		}
		foreach ( $cmp['since'] as $p ) {
			WP_CLI::log( "since the backup (not the migration): $p" );
		}
		foreach ( $cmp['diffs'] as $d ) {
			WP_CLI::warning( "difference: $d" );
		}
		if ( $cmp['diffs'] ) {
			WP_CLI::error( count( $cmp['diffs'] ) . ' differences beyond the planned changes.' );
		}
		WP_CLI::success( '0 differences apart from the planned changes.' );
	},
	array( 'shortdesc' => 'Compare a migrated sourcing phase with its backup.' )
);

/**
 * Roll a phase back: undo the migration on the current records (keeping what changed since), or with --restore-all
 * put every backed-up option back as it was in the backup. Either way the phase switch goes back and it is logged.
 *
 * ## OPTIONS
 *
 * --phase=<n>
 * : The phase (1).
 *
 * [--restore-all]
 * : Restore aw_publishers and aw_sources exactly as backed up (loses the ingest gate's counts and new hosts since).
 *
 * [--yes]
 * : Do not ask for confirmation.
 */
WP_CLI::add_command(
	'aw-sourcing rollback',
	function ( $args, $assoc ) {
		$phase            = aw_sourcing_cli_phase( $assoc );
		list( $bk, $old ) = aw_sourcing_cli_backup( $phase );
		$all              = ! empty( $assoc['restore-all'] );
		WP_CLI::confirm( "Roll phase $phase back " . ( $all ? "to backup $bk exactly (aw_publishers and aw_sources as backed up)" : '(undo the migration on the current records)' ) . '?', $assoc );
		if ( $all ) {
			update_option( AW_PUB_OPTION, $old['aw_publishers'], false );
			update_option( 'aw_sources', $old['aw_sources'], false );
			$what = "restored aw_publishers and aw_sources from $bk";
		} else {
			$rec          = get_option( 'aw_sourcing_phase1_record' );
			$c            = (array) get_option( 'aw_sources', array() );
			$undo         = aw_src_phase1_undo( aw_publishers(), (array) ( $c['sources'] ?? array() ), is_array( $rec ) && isset( $rec['hosted'] ) ? (array) $rec['hosted'] : null );
			$c['sources'] = $undo['sources'];
			update_option( AW_PUB_OPTION, $undo['pubs'], false );
			update_option( 'aw_sources', $c, false );
			$what = sprintf( 'relay off on %d, %d relay-only record(s) removed, host taken off the sources', count( $undo['unflagged'] ), count( $undo['removed'] ) );
		}
		if ( null === $old['aw_sourcing_phase'] ) {
			delete_option( 'aw_sourcing_phase' );
		} else {
			update_option( 'aw_sourcing_phase', $old['aw_sourcing_phase'], false );
		}
		delete_option( 'aw_sourcing_phase1_record' );
		delete_option( 'aw_backup_latest_' . $phase ); // a new migration starts from a new backup (the backups themselves stay)
		kits_sourcing_log( 'sources', "Phase $phase rolled back (WP-CLI): $what; relay list back to " . implode( ', ', aw_relay_hosts() ), 'phase ' . $phase, 'phase ' . aw_sourcing_phase() );
		WP_CLI::success( "Rolled back: $what. Relay hosts: " . implode( ', ', aw_relay_hosts() ) . '. The backup stays (' . $bk . ').' );
	},
	array( 'shortdesc' => 'Roll a sourcing restructure phase back.' )
);
