<?php
/**
 * Server tab, visitors (owner, 3 Nov 2026): the country and a shortened IP address of each front-end request that
 * reaches WordPress, as daily counts, for the admin only (to spot scrapers).
 *
 * - Counted: front-end requests (not logged in; not wp-admin, the REST API, cron, AJAX or WP-CLI). Pages the CDN or the
 *   server's LiteSpeed page cache answer from a stored copy never reach WordPress, so these are the requests the server itself answered.
 * - Shortened IP: IPv4 with the last number zeroed (203.0.113.0), IPv6 cut to its first 48 bits (2001:db8:1::).
 *   No full address is stored. Shortened addresses are kept 30 days (the daily run deletes older ones); country
 *   counts are kept for good.
 * - Country: a country header from the CDN when one is sent (filter aw_si_vis_country_headers), else the free DB-IP
 *   Lite country file (CC BY 4.0, db-ip.com), downloaded once a month into uploads/aw-geo/ as two small lookup files.
 *   No paid service and no lookup per request leaves the server.
 * - Storage: table {prefix}aw_si_visits (day, kind c = country / i = shortened IP, key, count); one write per request.
 *
 * @package insightweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SI_VIS_DB      = '1'; // Table version.
const AW_SI_VIS_IP_DAYS = 30;
const AW_SI_GEO_CRON    = 'aw_si_geo_refresh';

/** The table name. */
function aw_si_vis_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'aw_si_visits';
}

/** Create or update the table (admin and the daily run only). */
function aw_si_vis_install(): void {
	if ( AW_SI_VIS_DB === get_option( 'aw_si_vis_db' ) ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta(
		'CREATE TABLE ' . aw_si_vis_table() . ' (
			day date NOT NULL,
			kind char(1) NOT NULL,
			k varchar(45) NOT NULL,
			n int(10) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (day,kind,k)
		) ' . $wpdb->get_charset_collate() . ';'
	);
	update_option( 'aw_si_vis_db', AW_SI_VIS_DB, true );
}
add_action( 'admin_init', 'aw_si_vis_install' );

/**
 * An IP address shortened: IPv4 /24, IPv6 /48. '' when not an address.
 *
 * @param string $ip Address.
 */
function aw_si_vis_short_ip( string $ip ): string {
	$bin = filter_var( $ip, FILTER_VALIDATE_IP ) ? inet_pton( $ip ) : false;
	if ( false === $bin ) {
		return '';
	}
	$keep = 4 === strlen( $bin ) ? 3 : 6;
	return (string) inet_ntop( substr( $bin, 0, $keep ) . str_repeat( "\0", strlen( $bin ) - $keep ) );
}

/** Where the country lookup files live. */
function aw_si_geo_dir(): string {
	return trailingslashit( wp_upload_dir( null, false )['basedir'] ) . 'aw-geo/';
}

/**
 * The country of an address from the DB-IP Lite lookup files, or ''.
 * Each file is a sorted list of fixed-size records (start, end, two-letter country), searched by halves.
 *
 * @param string $ip Full address (used for the lookup only, never stored).
 */
function aw_si_geo_country( string $ip ): string {
	$bin = filter_var( $ip, FILTER_VALIDATE_IP ) ? inet_pton( $ip ) : false;
	if ( false === $bin ) {
		return '';
	}
	$w    = strlen( $bin );
	$file = aw_si_geo_dir() . ( 4 === $w ? 'v4.bin' : 'v6.bin' );
	$size = is_readable( $file ) ? (int) filesize( $file ) : 0;
	$rec  = 2 * $w + 2;
	if ( $size < $rec ) {
		return '';
	}
	$fh = fopen( $file, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- a seek-and-read lookup in our own file
	if ( ! $fh ) {
		return '';
	}
	$lo  = 0;
	$hi  = intdiv( $size, $rec ) - 1;
	$out = '';
	while ( $lo <= $hi ) {
		$mid = intdiv( $lo + $hi, 2 );
		fseek( $fh, $mid * $rec );
		$r = (string) fread( $fh, $rec ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		if ( strcmp( $bin, substr( $r, 0, $w ) ) < 0 ) {
			$hi = $mid - 1;
		} elseif ( strcmp( $bin, substr( $r, $w, $w ) ) > 0 ) {
			$lo = $mid + 1;
		} else {
			$out = substr( $r, 2 * $w, 2 );
			break;
		}
	}
	fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	return preg_match( '/^[A-Z]{2}$/', $out ) ? $out : '';
}

/** The visitor's country: the CDN's header when sent, else the lookup files; '--' when unknown. */
function aw_si_vis_country(): string {
	foreach ( (array) apply_filters( 'aw_si_vis_country_headers', array( 'HTTP_CF_IPCOUNTRY', 'HTTP_X_COUNTRY_CODE', 'HTTP_X_GEO_COUNTRY', 'HTTP_X_HCDN_COUNTRY', 'GEOIP_COUNTRY_CODE' ) ) as $h ) {
		$v = isset( $_SERVER[ $h ] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER[ $h ] ) ) ) : '';
		if ( preg_match( '/^[A-Z]{2}$/', $v ) && 'XX' !== $v ) {
			return $v;
		}
	}
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : ''; // LiteSpeed puts the visitor's address here (kits-core preview.php).
	$cc = aw_si_geo_country( $ip );
	return '' !== $cc ? $cc : '--';
}

/** At the end of a front-end request: one more for its country and its shortened address (one query). */
function aw_si_vis_count(): void {
	if ( AW_SI_VIS_DB !== get_option( 'aw_si_vis_db' ) || is_admin() || wp_doing_cron() || wp_doing_ajax() || ( defined( 'WP_CLI' ) && WP_CLI ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_user_logged_in() ) {
		return;
	}
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$sip = aw_si_vis_short_ip( $ip );
	$day = wp_date( 'Y-m-d' );
	global $wpdb;
	$t = aw_si_vis_table();
	if ( '' !== $sip ) {
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$t} (day, kind, k, n) VALUES (%s, 'c', %s, 1), (%s, 'i', %s, 1) ON DUPLICATE KEY UPDATE n = n + 1", $day, aw_si_vis_country(), $day, $sip ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- our own counter table
	} else {
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$t} (day, kind, k, n) VALUES (%s, 'c', %s, 1) ON DUPLICATE KEY UPDATE n = n + 1", $day, aw_si_vis_country() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- our own counter table
	}
}
add_action( 'shutdown', 'aw_si_vis_count', 0 );

/** The daily step: the table in place, shortened addresses older than 30 days deleted, the lookup files refreshed when a month old. */
function aw_si_vis_daily(): void {
	aw_si_vis_install();
	global $wpdb;
	$t = aw_si_vis_table();
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$t} WHERE kind = 'i' AND day < %s", wp_date( 'Y-m-d', time() - AW_SI_VIS_IP_DAYS * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- shortened addresses older than 30 days
	$v4 = aw_si_geo_dir() . 'v4.bin';
	if ( ( ! is_readable( $v4 ) || filemtime( $v4 ) < time() - 30 * DAY_IN_SECONDS ) && ! wp_next_scheduled( AW_SI_GEO_CRON ) ) {
		wp_schedule_single_event( time() + 120, AW_SI_GEO_CRON ); // its own run: the download and conversion take a while.
	}
}
add_action( AW_SI_CRON_DAILY, 'aw_si_vis_daily', 21 );

/**
 * Download this month's DB-IP Lite country file (last month's when this month's is not out yet) and write the two
 * lookup files. The old files stay until the new ones are complete.
 */
function aw_si_geo_refresh(): void {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	$dir = aw_si_geo_dir();
	if ( ! wp_mkdir_p( $dir ) ) {
		return;
	}
	$tmp = '';
	foreach ( array( 'now', 'first day of last month' ) as $when ) {
		$url = 'https://download.db-ip.com/free/dbip-country-lite-' . gmdate( 'Y-m', strtotime( $when ) ) . '.csv.gz';
		$got = download_url( $url, 300 );
		if ( ! is_wp_error( $got ) ) {
			$tmp = $got;
			break;
		}
	}
	if ( '' === $tmp ) {
		return;
	}
	$gz  = gzopen( $tmp, 'rb' );
	$out = array(
		4  => fopen( $dir . 'v4.bin.new', 'wb' ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streamed binary write, too large for one string
		16 => fopen( $dir . 'v6.bin.new', 'wb' ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	);
	$n   = 0;
	while ( $gz && $out[4] && $out[16] && ! gzeof( $gz ) ) {
		$row = explode( ',', trim( (string) gzgets( $gz, 256 ) ) ); // start,end,country: no quoting in this file.
		if ( 3 !== count( $row ) || ! preg_match( '/^[A-Z]{2}$/', $row[2] ) ) {
			continue;
		}
		$a = inet_pton( $row[0] );
		$b = inet_pton( $row[1] );
		if ( false === $a || false === $b || strlen( $a ) !== strlen( $b ) ) {
			continue;
		}
		fwrite( $out[ strlen( $a ) ], $a . $b . $row[2] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		++$n;
	}
	if ( $gz ) {
		gzclose( $gz );
	}
	foreach ( $out as $fh ) {
		if ( $fh ) {
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		}
	}
	wp_delete_file( $tmp );
	if ( $n > 100000 ) { // a whole file (about 600,000 ranges): swap in.
		rename( $dir . 'v4.bin.new', $dir . 'v4.bin' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
		rename( $dir . 'v6.bin.new', $dir . 'v6.bin' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
		update_option( 'aw_si_geo_updated', wp_date( 'Y-m-d' ), false );
	} else {
		wp_delete_file( $dir . 'v4.bin.new' );
		wp_delete_file( $dir . 'v6.bin.new' );
	}
}
add_action( AW_SI_GEO_CRON, 'aw_si_geo_refresh' );
add_filter( 'aw_si_cron_hooks', fn( $h ) => array_merge( (array) $h, array( AW_SI_GEO_CRON ) ) );

/**
 * Rows for the tab.
 *
 * @param string $kind c or i.
 * @param int    $days Days back (0: all days).
 * @param int    $limit Rows.
 * @return array<string,int> Key => count over the days.
 */
function aw_si_vis_top( string $kind, int $days, int $limit ): array {
	global $wpdb;
	$t    = aw_si_vis_table();
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT k, SUM(n) AS n FROM {$t} WHERE kind = %s AND day >= %s GROUP BY k ORDER BY n DESC LIMIT %d", $kind, $days > 0 ? wp_date( 'Y-m-d', time() - ( $days - 1 ) * DAY_IN_SECONDS ) : '1970-01-01', $limit ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- our own counter table
	$out  = array();
	foreach ( (array) $rows as $r ) {
		$out[ (string) $r->k ] = (int) $r->n;
	}
	return $out;
}

/** The visitors section of the Server tab. */
function aw_si_vis_section(): void {
	echo '<h2>' . esc_html__( 'Visitors', 'insightweb' ) . '</h2>';
	if ( AW_SI_VIS_DB !== get_option( 'aw_si_vis_db' ) ) {
		echo '<p>' . esc_html__( 'Counting starts once this page has been opened after the update.', 'insightweb' ) . '</p>';
		return;
	}
	$geo = (string) get_option( 'aw_si_geo_updated' );
	echo '<p class="description">' . esc_html__( 'Front-end requests the server answered (not logged-in users, wp-admin, the REST API or cron; not pages the CDN or the server\'s page cache answered from a stored copy), by country and by shortened address (IPv4 with the last number zeroed, IPv6 to 48 bits). Shortened addresses are kept 30 days, countries for good.', 'insightweb' ) . ' ';
	/* translators: %s: date the country file was downloaded, or "not yet". */
	echo esc_html( sprintf( __( 'Countries: the CDN\'s country header when sent, else IP Geolocation by DB-IP (db-ip.com, CC BY 4.0), file of %s.', 'insightweb' ), '' !== $geo ? $geo : __( 'not yet', 'insightweb' ) ) ) . '</p>';

	global $wpdb;
	$t     = aw_si_vis_table();
	$cells = array();
	for ( $i = 0; $i < 7; $i++ ) {
		$day = wp_date( 'Y-m-d', time() - $i * DAY_IN_SECONDS );
		$c   = $wpdb->get_results( $wpdb->prepare( "SELECT k, n FROM {$t} WHERE day = %s AND kind = 'c' ORDER BY n DESC", $day ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- our own counter table
		$ips = $wpdb->get_results( $wpdb->prepare( "SELECT k, n FROM {$t} WHERE day = %s AND kind = 'i' ORDER BY n DESC LIMIT 5", $day ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- our own counter table
		$cm  = array();
		foreach ( (array) $c as $r ) {
			$cm[ (string) $r->k ] = (int) $r->n;
		}
		$im = array();
		foreach ( (array) $ips as $r ) {
			$im[ (string) $r->k ] = (int) $r->n;
		}
		$cells[] = array(
			'day'       => esc_html( wp_date( 'j M Y', strtotime( $day . ' 12:00:00' ) ) ),
			'requests'  => esc_html( number_format_i18n( array_sum( $cm ) ) ),
			'countries' => esc_html( aw_si_srv_list( $cm, 5 ) ),
			'addresses' => esc_html( aw_si_srv_list( $im, 5 ) ),
			'_sort'     => array(
				'day'      => $day,
				'requests' => array_sum( $cm ),
			),
		);
	}
	aw_si_simple_table(
		'visitor-days',
		array(
			'day'       => esc_html__( 'Day', 'insightweb' ),
			'requests'  => esc_html__( 'Requests', 'insightweb' ),
			'countries' => esc_html__( 'Top countries', 'insightweb' ),
			'addresses' => esc_html__( 'Top shortened addresses', 'insightweb' ),
		),
		$cells,
		array(
			'sortable' => array(
				'day'      => true,
				'requests' => true,
			),
		)
	);

	foreach (
		array(
			'visitor-countries' => array( __( 'Top countries, all days', 'insightweb' ), __( 'Country', 'insightweb' ), aw_si_vis_top( 'c', 0, 10 ) ),
			'visitor-addresses' => array( __( 'Top shortened addresses, 30 days', 'insightweb' ), __( 'Shortened address', 'insightweb' ), aw_si_vis_top( 'i', AW_SI_VIS_IP_DAYS, 10 ) ),
		) as $name => $sec
	) {
		echo '<h3>' . esc_html( $sec[0] ) . '</h3>';
		if ( ! $sec[2] ) {
			echo '<p>' . esc_html__( 'None counted yet.', 'insightweb' ) . '</p>';
			continue;
		}
		$cells = array();
		foreach ( $sec[2] as $k => $n ) {
			$cells[] = array(
				'key'      => esc_html( $k ),
				'requests' => esc_html( number_format_i18n( $n ) ),
				'_sort'    => array(
					'key'      => (string) $k,
					'requests' => $n,
				),
			);
		}
		aw_si_simple_table(
			$name,
			array(
				'key'      => esc_html( $sec[1] ),
				'requests' => esc_html__( 'Requests', 'insightweb' ),
			),
			$cells,
			array(
				'sortable' => array(
					'key'      => false,
					'requests' => true,
				),
			)
		);
	}
}
