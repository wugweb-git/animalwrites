<?php
/**
 * Daily backup (S0): the database and the deployed code, once a day, into ~/backups on the server (outside the web
 * root), kept 14 days. Runs on WordPress's scheduler (this host has no user cron). Files: auto-db-YYYY-MM-DD.sql.gz and
 * auto-code-YYYY-MM-DD.tar.gz; only files with the auto- prefix are ever removed by the rotation.
 * Last run and size show on Tools → Check site. Restoring: docs/rollback.md.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_BACKUP_KEEP_DAYS = 14;

/** ~/backups (the account's home, three levels above the web root). */
function aw_backup_dir() {
	return dirname( untrailingslashit( ABSPATH ), 3 ) . '/backups';
}

add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'aw_daily_backup' ) ) {
			$at = strtotime( 'tomorrow 03:30 Asia/Kolkata' ); // a quiet hour
			wp_schedule_event( $at ? $at : time() + HOUR_IN_SECONDS, 'daily', 'aw_daily_backup' );
		}
	}
);
add_action( 'aw_daily_backup', 'aw_run_backup' );

/**
 * Write today's database and code backups and remove automatic ones older than 14 days.
 *
 * @return array What was written.
 */
function aw_run_backup() {
	if ( function_exists( 'set_time_limit' ) ) {
		set_time_limit( 600 );
	}
	$dir = aw_backup_dir();
	if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
		return array( 'error' => 'no backup folder' );
	}
	$day    = wp_date( 'Y-m-d' );
	$report = array( 'at' => current_time( 'mysql' ) );

	// Database: every table, structure and rows, gzipped as it is written.
	global $wpdb;
	$db = $dir . '/auto-db-' . $day . '.sql.gz';
	$gz = gzopen( $db, 'wb6' );
	if ( $gz ) {
		gzwrite( $gz, "-- Animal Writes database backup {$report['at']}\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n" );
		foreach ( $wpdb->get_col( 'SHOW TABLES' ) as $table ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$create = $wpdb->get_row( "SHOW CREATE TABLE `{$table}`", ARRAY_N ); // phpcs:ignore WordPress.DB
			gzwrite( $gz, "\nDROP TABLE IF EXISTS `{$table}`;\n" . $create[1] . ";\n" );
			$off = 0;
			do {
				$rows = $wpdb->get_results( "SELECT * FROM `{$table}` LIMIT 500 OFFSET {$off}", ARRAY_A ); // phpcs:ignore WordPress.DB
				foreach ( $rows as $row ) {
					$vals = array_map( fn( $v ) => null === $v ? 'NULL' : "'" . $wpdb->_real_escape( (string) $v ) . "'", array_values( $row ) );
					gzwrite( $gz, "INSERT INTO `{$table}` VALUES (" . implode( ',', $vals ) . ");\n" );
				}
				$off += 500;
			} while ( count( $rows ) === 500 );
		}
		gzwrite( $gz, "SET FOREIGN_KEY_CHECKS=1;\n" );
		gzclose( $gz );
		$report['db'] = array( basename( $db ), (int) filesize( $db ) );
	}

	// Code: the deployed folder (themes and plugins as they run), without its git history.
	$src = trailingslashit( ABSPATH ) . 'aw_deploy';
	$tar = $dir . '/auto-code-' . $day . '.tar';
	try {
		if ( is_dir( $src ) && class_exists( 'PharData' ) ) {
			@unlink( $tar ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			@unlink( $tar . '.gz' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			$p = new PharData( $tar );
			$p->buildFromDirectory( $src, '#^(?!.*/\.git/).*$#' );
			$p->compress( Phar::GZ );
			unset( $p );
			@unlink( $tar ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			$report['code'] = array( basename( $tar ) . '.gz', (int) filesize( $tar . '.gz' ) );
		}
	} catch ( Exception $e ) {
		$report['code_error'] = $e->getMessage();
	}

	// Keep 14 days of automatic backups (hand-made backups are never touched).
	$removed = array();
	foreach ( (array) glob( $dir . '/auto-*' ) as $f ) {
		if ( filemtime( $f ) < time() - AW_BACKUP_KEEP_DAYS * DAY_IN_SECONDS ) {
			unlink( $f );
			$removed[] = basename( $f );
		}
	}
	$report['removed'] = $removed;
	update_option( 'aw_backup_last', $report, false );
	return $report;
}

// Shown on Tools → Check site.
add_action(
	'aw_site_check_after',
	function () {
		$b = get_option( 'aw_backup_last' );
		$n = wp_next_scheduled( 'aw_daily_backup' );
		echo '<h2>Daily backup</h2><p>';
		if ( is_array( $b ) ) {
			echo 'Last: ' . esc_html( $b['at'] ) . ( isset( $b['db'] ) ? ' · database ' . esc_html( size_format( $b['db'][1] ) ) : ' · database: not written' ) . ( isset( $b['code'] ) ? ' · code ' . esc_html( size_format( $b['code'][1] ) ) : ' · code: not written' ) . '.';
		} else {
			echo 'Not run yet.';
		}
		echo ' Next: ' . esc_html( $n ? wp_date( 'j M Y, g:i a', $n ) : 'not scheduled' ) . '. Kept ' . (int) AW_BACKUP_KEEP_DAYS . ' days in ~/backups on the server.</p>';
	}
);
