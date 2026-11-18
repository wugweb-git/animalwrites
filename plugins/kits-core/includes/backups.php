<?php
/**
 * Daily backup (S0): the database and the deployed code, once a day, into ~/backups on the server (outside the web
 * root), kept 14 days. Runs on WordPress's scheduler (this host has no user cron). Files: auto-db-YYYY-MM-DD.sql.gz and
 * auto-code-YYYY-MM-DD.tar.gz; only files with the auto- prefix are ever removed by the rotation.
 * Last run and size show on Kits → Home → Backup. Restoring: docs/rollback.md.
 *
 * Moved whole from Animal Writes Core (18 Nov 2026): the option aw_backup_last, the cron hook aw_daily_backup and the
 * REST route aw/v1/backup/(db|code) keep their names (the workers repository's off-server copy calls the route). The
 * old aw_ functions stay at the end of this file as thin wrappers until nothing calls them.
 *
 * Off unless the site turns it on (owner, 18 Nov 2026): Kits → Settings → Backup (option kits_daily_backup) or the
 * filter kits_daily_backup. A site where this backup already ran (option aw_backup_last, or the event aw_daily_backup
 * booked) is switched on once, by itself, the first time this file loads there, so Animal Writes keeps its backup with
 * no manual step. When off: nothing is booked or written, the download route is not registered, and an event booked
 * earlier is taken off. Backup files already in ~/backups are never touched by switching it off.
 * The UpdraftPlus alert at the end of this file only reads UpdraftPlus's own options and is not part of the switch.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KITS_BACKUP_KEEP_DAYS = 14;

/** ~/backups (the account's home, three levels above the web root). */
function kits_daily_backup_dir() {
	return dirname( untrailingslashit( ABSPATH ), 3 ) . '/backups';
}

/** The switch: '1' on, '0' off; missing until the one-time choice below has run. */
const KITS_DAILY_BACKUP_OPTION = 'kits_daily_backup';

/**
 * Whether the daily backup runs on this site. Off by default. The first time it is asked on a site that has no
 * choice stored yet, it stores one: on where this backup already ran (aw_backup_last) or is booked (aw_daily_backup),
 * off everywhere else. A site can force it with the filter kits_daily_backup.
 *
 * @return bool
 */
function kits_daily_backup_on(): bool {
	$stored = get_option( KITS_DAILY_BACKUP_OPTION, null );
	if ( null === $stored ) {
		$stored = ( get_option( 'aw_backup_last' ) || wp_next_scheduled( 'aw_daily_backup' ) ) ? '1' : '0';
		add_option( KITS_DAILY_BACKUP_OPTION, $stored ); // autoloaded: read on every request, so no extra query.
	}
	return (bool) apply_filters( 'kits_daily_backup', '1' === (string) $stored );
}

add_action(
	'init',
	function () {
		$booked = wp_next_scheduled( 'aw_daily_backup' );
		if ( ! kits_daily_backup_on() ) {
			if ( $booked ) {
				wp_clear_scheduled_hook( 'aw_daily_backup' );
			}
			return;
		}
		if ( ! $booked ) {
			$at = strtotime( 'tomorrow 03:30 Asia/Kolkata' ); // A quiet hour.
			wp_schedule_event( $at ? $at : time() + HOUR_IN_SECONDS, 'daily', 'aw_daily_backup' );
		}
	}
);
add_action( 'aw_daily_backup', 'kits_daily_backup_run' );

// Kits → Settings → Backup: the switch (core Settings API, its own form like Uninstall).
add_action(
	'init',
	function () {
		register_setting(
			'kits_daily_backup',
			KITS_DAILY_BACKUP_OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => static fn( $v ): string => rest_sanitize_boolean( $v ) ? '1' : '0',
				'show_in_rest'      => false,
			)
		);
	}
);
add_action(
	'admin_init',
	function () {
		add_settings_section( 'kits_daily_backup', '', '__return_null', 'kits-backup' );
		add_settings_field( KITS_DAILY_BACKUP_OPTION, 'Daily backup', 'kits_daily_backup_field', 'kits-backup', 'kits_daily_backup', array( 'label_for' => KITS_DAILY_BACKUP_OPTION ) );
	}
);
add_filter(
	'kits_settings_sections',
	function ( $sections ) {
		$sections           = (array) $sections;
		$sections['backup'] = array(
			'title'  => 'Backup',
			'render' => 'kits_daily_backup_form',
			'cap'    => 'manage_options',
			'order'  => 85,
		);
		return $sections;
	}
);

/** The Backup box. */
function kits_daily_backup_field(): void {
	$stored = '1' === (string) get_option( KITS_DAILY_BACKUP_OPTION, '0' );
	$forced = kits_daily_backup_on() !== $stored ? ' This site sets it in code (filter kits_daily_backup), so the box does not decide.' : '';
	printf(
		'<input type="checkbox" id="%1$s" name="%1$s" value="1"%2$s> <p class="description">%3$s</p>',
		esc_attr( KITS_DAILY_BACKUP_OPTION ),
		checked( $stored, true, false ),
		esc_html( 'Every day at 03:30 (India time), a copy of the database and of the deployed code goes to the backups folder in the hosting account, outside the website, kept ' . KITS_BACKUP_KEEP_DAYS . ' days. Off: nothing is written; backups already there stay.' . $forced )
	);
	if ( function_exists( 'kits_daily_backup_site_check' ) ) {
		kits_daily_backup_site_check();
	}
}

/** Kits → Settings → Backup: its own form, coming back to its section after the save. */
function kits_daily_backup_form(): void {
	echo '<form method="post" action="options.php">';
	echo '<input type="hidden" name="option_page" value="kits_daily_backup"><input type="hidden" name="action" value="update">';
	wp_nonce_field( 'kits_daily_backup-options', '_wpnonce', false );
	echo '<input type="hidden" name="_wp_http_referer" value="' . esc_attr( wp_make_link_relative( admin_url( 'admin.php?page=kits-settings&kits_section=backup' ) ) ) . '">';
	do_settings_sections( 'kits-backup' );
	submit_button( 'Save backup setting' );
	echo '</form>';
}

/**
 * Write today's database and code backups and remove automatic ones older than 14 days.
 *
 * @return array What was written.
 */
function kits_daily_backup_run() {
	if ( ! kits_daily_backup_on() ) {
		return array( 'error' => 'off' ); // Switched off after the event was booked.
	}
	if ( function_exists( 'set_time_limit' ) ) {
		set_time_limit( 600 );
	}
	$dir = kits_daily_backup_dir();
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
			$create = $wpdb->get_row( $wpdb->prepare( 'SHOW CREATE TABLE %i', $table ), ARRAY_N ); // phpcs:ignore WordPress.DB
			gzwrite( $gz, "\nDROP TABLE IF EXISTS `{$table}`;\n" . $create[1] . ";\n" );
			$off = 0;
			do {
				$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i LIMIT 500 OFFSET %d', $table, $off ), ARRAY_A ); // phpcs:ignore WordPress.DB
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
			wp_delete_file( $tar );
			wp_delete_file( $tar . '.gz' );
			$p = new PharData( $tar );
			$p->buildFromDirectory( $src, '#^(?!.*/\.git/).*$#' );
			$p->compress( Phar::GZ );
			unset( $p );
			wp_delete_file( $tar );
			$report['code'] = array( basename( $tar ) . '.gz', (int) filesize( $tar . '.gz' ) );
		}
	} catch ( Exception $e ) {
		$report['code_error'] = $e->getMessage();
	}

	// Keep 14 days of automatic backups (hand-made backups are never touched).
	$removed = array();
	$old_files = glob( $dir . '/auto-*' );
	foreach ( is_array( $old_files ) ? $old_files : array() as $f ) { // glob() gives false on failure.
		if ( filemtime( $f ) < time() - KITS_BACKUP_KEEP_DAYS * DAY_IN_SECONDS ) {
			wp_delete_file( $f );
			$removed[] = basename( $f );
		}
	}
	$report['removed'] = $removed;
	update_option( 'aw_backup_last', $report, false );
	return $report;
}

// Shown on Kits → Home → Backup.
/** Kits → Home → Backup (and Check site without Kits): the last daily backup and the next run. */
function kits_daily_backup_site_check() {
	$b = get_option( 'aw_backup_last' );
	$n = wp_next_scheduled( 'aw_daily_backup' );
	echo '<h2>Daily backup</h2><p>';
	if ( ! kits_daily_backup_on() ) {
		echo 'Off (Kits → Settings → Backup).</p>';
		return;
	}
	if ( is_array( $b ) ) {
		echo 'Last: ' . esc_html( $b['at'] ) . ( isset( $b['db'] ) ? ' · database ' . esc_html( size_format( $b['db'][1] ) ) : ' · database: not written' ) . ( isset( $b['code'] ) ? ' · code ' . esc_html( size_format( $b['code'][1] ) ) : ' · code: not written' ) . '.';
	} else {
		echo 'Not run yet.';
	}
	echo ' Next: ' . esc_html( $n ? wp_date( 'j M Y, g:i a', $n ) : 'not scheduled' ) . '. Kept ' . (int) KITS_BACKUP_KEEP_DAYS . ' days in ~/backups on the server.</p>';
}
add_action( 'aw_site_check_after', 'kits_daily_backup_site_check' );

/*
 * Off-server copy (owner's choice, 8 Oct 2026: GitHub). The workers repository (private) downloads the latest
 * automatic backup once a day and keeps it as a private release for 14 days. Only an application-password request
 * from an account that can publish others' posts may download; nothing is listed or linked anywhere.
 */
add_action(
	'rest_api_init',
	function () {
		if ( ! kits_daily_backup_on() ) {
			return;
		}
		register_rest_route(
			'aw/v1',
			'/backup/(?P<kind>db|code)',
			array(
				'methods'             => 'GET',
				'permission_callback' => fn() => current_user_can( 'edit_others_posts' ) && did_action( 'application_password_did_authenticate' ),
				'callback'            => function ( WP_REST_Request $req ) {
					$files = (array) glob( kits_daily_backup_dir() . '/auto-' . $req['kind'] . '-*.gz' );
					rsort( $files ); // Newest date first.
					$f = $files[0] ?? '';
					if ( ! $f || ! is_readable( $f ) ) {
						return new WP_Error( 'aw_no_backup', 'No backup yet.', array( 'status' => 404 ) );
					}
					nocache_headers();
					header( 'Content-Type: application/gzip' );
					header( 'Content-Disposition: attachment; filename="' . basename( $f ) . '"' );
					header( 'Content-Length: ' . filesize( $f ) );
					readfile( $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions
					exit;
				},
			)
		);
	}
);

/*
 * Remote backup alert (owner, 25 Oct 2026): backups are UpdraftPlus's job (database daily, files weekly, to Google
 * Drive). Its newest backup sent to a remote store older than 36 hours, or its last run ending with errors, is a
 * Workers problem (aw_workers_problems, SourceWeb workers.php): shown on Sourcing → Workers, in the dashboard box and
 * Site Health, and emailed once per new set of problems. Read from UpdraftPlus's own options; nothing is written.
 */
const KITS_BACKUP_REMOTE_STALE = 36 * HOUR_IN_SECONDS;

/**
 * Unix time of UpdraftPlus's newest backup that went to a remote store (not 'none'), or 0.
 *
 * @return int
 */
function kits_backup_remote_last() {
	$last = 0;
	foreach ( (array) get_option( 'updraft_backup_history', array() ) as $at => $b ) {
		$services = array_diff( (array) ( $b['service'] ?? array() ), array( '', 'none' ) );
		if ( $services && ( isset( $b['db'] ) || isset( $b['plugins'] ) ) ) {
			$last = max( $last, (int) $at );
		}
	}
	return $last;
}

/**
 * The backup problem as a sentence, or '' (also '' when UpdraftPlus is not active).
 *
 * @param int|null $now Unix time (tests).
 * @return string
 */
function kits_backup_problem( $now = null ) {
	if ( ! class_exists( 'UpdraftPlus' ) ) {
		return '';
	}
	$now  = $now ? $now : time();
	$last = kits_backup_remote_last();
	if ( ! $last ) {
		return 'UpdraftPlus has no backup in a remote store (Google Drive) yet.';
	}
	if ( $now - $last > KITS_BACKUP_REMOTE_STALE ) {
		return sprintf( 'The newest UpdraftPlus backup in Google Drive is %d hours old (%s); one is expected daily.', floor( ( $now - $last ) / HOUR_IN_SECONDS ), wp_date( 'j M, g:i a', $last ) );
	}
	$run = (array) get_option( 'updraft_last_backup', array() );
	if ( $run && ( empty( $run['success'] ) || ! empty( $run['errors'] ) ) ) {
		return sprintf( 'The last UpdraftPlus backup run (%s) ended with errors%s', wp_date( 'j M, g:i a', (int) ( $run['backup_time'] ?? 0 ) ), ! empty( $run['errors'] ) && is_array( $run['errors'] ) ? ': ' . mb_substr( wp_strip_all_tags( (string) reset( $run['errors'] ) ), 0, 160 ) : '.' );
	}
	return '';
}

/*
 * The old names (Animal Writes Core), kept as thin wrappers until nothing calls them.
 */
if ( ! function_exists( 'aw_backup_dir' ) ) {
	/** Old name of kits_daily_backup_dir(). */
	function aw_backup_dir() {
		return kits_daily_backup_dir();
	}
}
if ( ! function_exists( 'aw_run_backup' ) ) {
	/**
	 * Old name of kits_daily_backup_run().
	 *
	 * @return array What was written.
	 */
	function aw_run_backup() {
		return kits_daily_backup_run();
	}
}
if ( ! function_exists( 'aw_backup_site_check' ) ) {
	/** Old name of kits_daily_backup_site_check(). */
	function aw_backup_site_check() {
		kits_daily_backup_site_check();
	}
}
if ( ! function_exists( 'aw_backup_remote_last' ) ) {
	/**
	 * Old name of kits_backup_remote_last().
	 *
	 * @return int
	 */
	function aw_backup_remote_last() {
		return kits_backup_remote_last();
	}
}
if ( ! function_exists( 'aw_backup_problem' ) ) {
	/**
	 * Old name of kits_backup_problem().
	 *
	 * @param int|null $now Unix time (tests).
	 * @return string
	 */
	function aw_backup_problem( $now = null ) {
		return kits_backup_problem( $now );
	}
}
