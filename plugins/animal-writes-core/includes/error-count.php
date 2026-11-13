<?php
/**
 * Daily server-error count. A 500 or 503 page never loads the analytics tag, so analytics cannot see it; the server's
 * own logs can.
 *
 * - Once a day (event aw_error_count_daily) the site counts, for the last 24 hours, the PHP error log lines by kind:
 *   fatal ("PHP Fatal error" / "PHP Parse error"), "PHP Warning", "PHP Notice" and "PHP Deprecated", each counted
 *   apart. (The host gives PHP no access log, so 5xx responses are not counted.)
 * - The result (time, counts per kind, files read, up to 5 samples per kind) is kept in the option aw_error_count (not
 *   autoloaded) and shown in the "Scheduled tasks" dashboard box. A log that cannot be found or read shows
 *   "Not found", never 0.
 * - When the fatal count is above zero, the admin email gets one plain-text message for that run (with the other
 *   kinds' counts). Warnings, notices and deprecations alone send nothing.
 * - Log paths are found at run time. Override them with the constant AW_PHP_ERROR_LOG (a file or a folder) in
 *   wp-config.php, or the filter aw_error_count_php_paths.
 * - `wp aw error-count [--paths]` prints one line per kind, the files read and the most frequent messages (with
 *   --paths, every path tried), without saving or emailing.
 * Large files are read from the end (last 32 MB by default, filter aw_error_count_tail_bytes), line by line; gzipped
 * logs are streamed.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_ERROR_COUNT_SAMPLES = 5;
const AW_ERROR_COUNT_KEYS    = 500; // distinct samples tracked while counting, per kind (memory bound)

/**
 * The kinds counted, key => label. "fatal" covers fatal and parse errors.
 *
 * @return array<string,string>
 */
function aw_error_count_kinds() {
	return array(
		'fatal'      => 'PHP fatal errors',
		'warning'    => 'PHP warnings',
		'notice'     => 'PHP notices',
		'deprecated' => 'PHP deprecated',
	);
}

/**
 * Read one log line: its time, kind and message, or null for a line that is not a dated PHP error line (a
 * continuation line, a stack-trace line or a plain error_log() message from a plugin).
 *
 * @param string $line Line from the log.
 * @return array{t:int,kind:string,msg:string}|null
 */
function aw_error_count_parse_line( $line ) {
	if ( '[' !== substr( (string) $line, 0, 1 ) || ! preg_match( '/^\[([^\]]+)\] (?:PHP (Fatal error|Parse error|Warning|Notice|Deprecated):)?\s*(.*)$/', (string) $line, $m ) ) {
		return null;
	}
	$map = array(
		'Fatal error' => 'fatal',
		'Parse error' => 'fatal',
		'Warning'     => 'warning',
		'Notice'      => 'notice',
		'Deprecated'  => 'deprecated',
	);
	return array(
		't'    => (int) strtotime( $m[1] ),
		'kind' => $map[ $m[2] ] ?? '',
		'msg'  => ( 'Parse error' === $m[2] ? 'Parse error: ' : '' ) . $m[3],
	);
}

/** The site root's parents: the domain folder (…/domains/example.com) and the account's home folder. */
function aw_error_count_dirs() {
	$root   = untrailingslashit( ABSPATH );
	$domain = dirname( $root );
	$home   = dirname( dirname( $domain ) );
	return array( 'root' => $root, 'domain' => $domain, 'home' => $home );
}

/**
 * PHP error-log candidates (files or folders). Every readable one is read (the same file only once).
 *
 * @return string[]
 */
function aw_error_count_php_candidates() {
	$d   = aw_error_count_dirs();
	$out = array();
	if ( defined( 'AW_PHP_ERROR_LOG' ) && AW_PHP_ERROR_LOG ) {
		$out[] = (string) AW_PHP_ERROR_LOG;
	}
	$ini = (string) ini_get( 'error_log' );
	if ( '' !== $ini && 'syslog' !== $ini ) {
		$out[] = $ini;
	}
	if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
		$out[] = is_string( WP_DEBUG_LOG ) ? WP_DEBUG_LOG : WP_CONTENT_DIR . '/debug.log';
	}
	$out[] = WP_CONTENT_DIR . '/debug.log';
	$out[] = $d['root'] . '/error_log';
	$out[] = $d['root'] . '/wp-admin/error_log';
	$host  = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	if ( '' !== $host ) {
		$out[] = $d['home'] . '/.logs/error_log_' . str_replace( array( '.', '-' ), '_', $host ); // Hostinger's log for this domain only (the folder holds every domain's)
	}
	return array_values( array_unique( (array) apply_filters( 'aw_error_count_php_paths', $out ) ) );
}

/**
 * Whether PHP may open the path at all (open_basedir), so a check never raises a warning.
 *
 * @param string $path File or folder.
 * @return bool
 */
function aw_error_count_allowed( $path ) {
	$base = (string) ini_get( 'open_basedir' );
	if ( '' === $base ) {
		return true;
	}
	foreach ( explode( PATH_SEPARATOR, $base ) as $b ) {
		$b = rtrim( $b, '/' );
		if ( '' !== $b && ( $path === $b || 0 === strpos( $path, $b . '/' ) ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Turn candidates into log files: every readable file, and in a folder the files with "error" in the name.
 *
 * @param string[] $candidates Files or folders.
 * @return array{files:string[],tried:array<string,string>,unreadable:bool}
 */
function aw_error_count_resolve( array $candidates ) {
	$files      = array();
	$tried      = array();
	$unreadable = false;
	foreach ( $candidates as $c ) {
		$c = (string) $c;
		if ( ! aw_error_count_allowed( $c ) || ! file_exists( $c ) ) {
			$tried[ $c ] = 'not found';
			continue;
		}
		$found = array();
		if ( is_dir( $c ) ) {
			$names = is_readable( $c ) ? (array) scandir( $c ) : array();
			foreach ( $names as $n ) {
				$p     = $c . '/' . $n;
				$error = false !== stripos( (string) $n, 'error' );
				if ( '.' === $n[0] || ! is_file( $p ) || ! $error ) {
					continue;
				}
				if ( is_readable( $p ) ) {
					$found[] = $p;
				} else {
					$unreadable = true;
				}
			}
			$tried[ $c ] = $found ? 'folder: ' . count( $found ) . ' file(s)' : ( is_readable( $c ) ? 'folder: no matching files' : 'folder not readable' );
			$unreadable  = $unreadable || ! is_readable( $c );
		} elseif ( is_readable( $c ) ) {
			$found[]     = $c;
			$tried[ $c ] = 'found';
		} else {
			$tried[ $c ] = 'not readable';
			$unreadable  = true;
		}
		foreach ( $found as $f ) {
			$real           = (string) realpath( $f );
			$files[ $real ] = $f;
		}
	}
	return array( 'files' => array_values( $files ), 'tried' => $tried, 'unreadable' => $unreadable );
}

/**
 * Call $cb for every line of a log: the last $tail bytes of a plain file, or all of a .gz file (streamed).
 *
 * @param string   $file Path.
 * @param callable $cb   function( string $line ).
 * @return array{lines:int,cut:bool} Lines read; cut = the start of the file was skipped.
 */
function aw_error_count_each_line( $file, callable $cb ) {
	$lines = 0;
	$cut   = false;
	// phpcs:disable WordPress.WP.AlternativeFunctions -- reads server log files line by line; WP_Filesystem cannot stream or seek
	if ( '.gz' === substr( $file, -3 ) ) {
		$h = function_exists( 'gzopen' ) ? gzopen( $file, 'rb' ) : false;
		if ( ! $h ) {
			return array( 'lines' => 0, 'cut' => false );
		}
		while ( ! gzeof( $h ) ) {
			$line = gzgets( $h, 65536 );
			if ( false === $line ) {
				break;
			}
			++$lines;
			$cb( $line );
		}
		gzclose( $h );
		return array( 'lines' => $lines, 'cut' => false );
	}
	$h = fopen( $file, 'rb' );
	if ( ! $h ) {
		return array( 'lines' => 0, 'cut' => false );
	}
	$tail = max( 1048576, (int) apply_filters( 'aw_error_count_tail_bytes', 32 * 1048576 ) );
	$size = (int) filesize( $file );
	if ( $size > $tail ) {
		fseek( $h, $size - $tail );
		fgets( $h, 65536 ); // drop the partial first line
		$cut = true;
	}
	while ( false !== ( $line = fgets( $h, 65536 ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- standard read loop
		++$lines;
		$cb( $line );
	}
	fclose( $h );
	// phpcs:enable WordPress.WP.AlternativeFunctions
	return array( 'lines' => $lines, 'cut' => $cut );
}

/**
 * Add one to a sample's count, keeping the number of distinct samples bounded.
 *
 * @param array  $samples Sample => count (changed in place).
 * @param string $key     Sample.
 */
function aw_error_count_tally( array &$samples, $key ) {
	if ( isset( $samples[ $key ] ) ) {
		++$samples[ $key ];
	} elseif ( count( $samples ) < AW_ERROR_COUNT_KEYS ) {
		$samples[ $key ] = 1;
	}
}

/**
 * The most frequent samples.
 *
 * @param array $samples Sample => count.
 * @return array Sample => count.
 */
function aw_error_count_top( array $samples ) {
	arsort( $samples );
	return array_slice( $samples, 0, AW_ERROR_COUNT_SAMPLES, true );
}

/**
 * A fatal-error message for display: site paths made relative, single line, at most 200 characters.
 *
 * @param string $msg Message from the log.
 * @return string
 */
function aw_error_count_clean_message( $msg ) {
	$root = trailingslashit( aw_error_count_dirs()['root'] );
	$real = realpath( $root );
	$msg  = str_replace( array( $root, $real ? trailingslashit( $real ) : $root ), '', (string) $msg );
	$msg  = trim( preg_replace( '/\s+/', ' ', $msg ) );
	return mb_strlen( $msg ) > 200 ? mb_substr( $msg, 0, 199 ) . '…' : $msg;
}

/**
 * Count PHP error lines since $since in the PHP error logs, by kind.
 *
 * @param int $since Unix time.
 * @return array Result: status ok|not_found|unreadable, count (fatal), samples (fatal), kinds (kind => count and
 *               samples), files (path => lines), partial, tried.
 */
function aw_error_count_php( $since ) {
	$res   = aw_error_count_resolve( aw_error_count_php_candidates() );
	$kinds = array();
	foreach ( array_keys( aw_error_count_kinds() ) as $k ) {
		$kinds[ $k ] = array( 'count' => 0, 'samples' => array() );
	}
	$out = array( 'status' => 'not_found', 'count' => 0, 'files' => array(), 'partial' => false, 'samples' => array(), 'kinds' => $kinds, 'tried' => $res['tried'] );
	if ( ! $res['files'] ) {
		$out['status'] = $res['unreadable'] ? 'unreadable' : 'not_found';
		return $out;
	}
	foreach ( $res['files'] as $f ) {
		if ( (int) filemtime( $f ) < $since ) {
			$out['files'][ $f ] = 0; // nothing logged in the window
			continue;
		}
		$first              = null;
		$read               = aw_error_count_each_line(
			$f,
			function ( $line ) use ( $since, &$kinds, &$first ) {
				$p = aw_error_count_parse_line( $line );
				if ( null === $p ) {
					return;
				}
				if ( null === $first ) {
					$first = $p['t'];
				}
				if ( '' === $p['kind'] || $p['t'] < $since ) {
					return;
				}
				++$kinds[ $p['kind'] ]['count'];
				aw_error_count_tally( $kinds[ $p['kind'] ]['samples'], aw_error_count_clean_message( $p['msg'] ) );
			}
		);
		$out['files'][ $f ] = $read['lines'];
		if ( $read['cut'] && null !== $first && $first >= $since ) {
			$out['partial'] = true;
		}
	}
	foreach ( $kinds as $k => $v ) {
		$kinds[ $k ]['samples'] = aw_error_count_top( $v['samples'] );
	}
	$out['status']  = 'ok';
	$out['kinds']   = $kinds;
	$out['count']   = $kinds['fatal']['count'];
	$out['samples'] = $kinds['fatal']['samples'];
	return $out;
}

/**
 * Count the PHP error log for the last 24 hours.
 *
 * @return array{at:int,since:int,php:array}
 */
function aw_error_count_collect() {
	$now   = time();
	$since = $now - DAY_IN_SECONDS;
	return array(
		'at'    => $now,
		'since' => $since,
		'php'   => aw_error_count_php( $since ),
	);
}

/**
 * "3", "at least 3", or "Not found" / "Not readable".
 *
 * @param array  $r    One log's result.
 * @param string $kind fatal, warning, notice or deprecated.
 * @return string
 */
function aw_error_count_label( array $r, $kind = 'fatal' ) {
	switch ( $r['status'] ?? '' ) {
		case 'ok':
			return ( empty( $r['partial'] ) ? '' : 'at least ' ) . aw_error_count_kind( $r, $kind )['count'];
		case 'unreadable':
			return 'Not readable';
		default:
			return 'Not found';
	}
}

/**
 * One kind's count and samples. A result saved before kinds were counted has only the fatal count.
 *
 * @param array  $r    One log's result.
 * @param string $kind fatal, warning, notice or deprecated.
 * @return array{count:int,samples:array}
 */
function aw_error_count_kind( array $r, $kind ) {
	if ( isset( $r['kinds'][ $kind ] ) && is_array( $r['kinds'][ $kind ] ) ) {
		$k = $r['kinds'][ $kind ];
		return array( 'count' => (int) ( $k['count'] ?? 0 ), 'samples' => (array) ( $k['samples'] ?? array() ) );
	}
	if ( 'fatal' === $kind ) {
		return array( 'count' => (int) ( $r['count'] ?? 0 ), 'samples' => (array) ( $r['samples'] ?? array() ) );
	}
	return array( 'count' => 0, 'samples' => array() );
}

/**
 * Fatal errors found (when the log could be read).
 *
 * @param array $run Result of aw_error_count_collect().
 * @return int
 */
function aw_error_count_total( array $run ) {
	return 'ok' === ( $run['php']['status'] ?? '' ) ? (int) $run['php']['count'] : 0;
}

/** Daily event: count, store, and email the admin address when anything was found. */
function aw_error_count_daily() {
	$run = aw_error_count_collect();
	unset( $run['php']['tried'] ); // only the CLI shows them
	update_option( 'aw_error_count', $run, false );
	$total = aw_error_count_total( $run );
	if ( ! $total || get_transient( 'aw_error_count_mailed' ) ) {
		return; // nothing found, or this day's message is already sent
	}
	$lines = array(
		sprintf( 'Server errors on %s in the 24 hours to %s:', wp_parse_url( home_url(), PHP_URL_HOST ), wp_date( 'j M Y, g:i a', $run['at'] ) ),
		'',
	);
	foreach ( aw_error_count_kinds() as $k => $name ) {
		$lines[] = '- ' . $name . ': ' . aw_error_count_label( $run['php'], $k );
	}
	if ( $run['php']['samples'] ) {
		$lines[] = '';
		$lines[] = 'Most frequent PHP fatal errors:';
		foreach ( $run['php']['samples'] as $s => $n ) {
			$lines[] = sprintf( '- %s (%d)', $s, $n );
		}
	}
	$lines[] = '';
	$lines[] = 'Dashboard: ' . admin_url();
	/* translators: %d: number of errors. */
	$subject = sprintf( _n( 'Animal Writes: %d server error in the last 24 hours', 'Animal Writes: %d server errors in the last 24 hours', $total, 'animal-writes-core' ), $total );
	if ( wp_mail( get_option( 'admin_email' ), $subject, implode( "\n", $lines ) . "\n" ) ) {
		set_transient( 'aw_error_count_mailed', 1, 20 * HOUR_IN_SECONDS );
	}
}
add_action( 'aw_error_count_daily', 'aw_error_count_daily' );
add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'aw_error_count_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'aw_error_count_daily' );
		}
	}
);

// A table under the "Scheduled tasks" dashboard box (cron-health.php).
add_action(
	'wp_dashboard_setup',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		add_action(
			'aw_cron_health_after',
			function () {
				$run = (array) get_option( 'aw_error_count', array() );
				if ( empty( $run['at'] ) ) {
					echo '<p>Server errors: not counted yet (counted once a day).</p>';
					return;
				}
				echo '<p><strong>Server errors, last 24 hours</strong> (counted ' . esc_html( wp_date( 'j M Y, g:i a', (int) $run['at'] ) ) . ')</p>';
				$rows  = array();
				$r     = (array) ( $run['php'] ?? array() );
				$ok    = 'ok' === ( $r['status'] ?? '' );
				$files = array_map( 'wp_basename', array_keys( (array) ( $r['files'] ?? array() ) ) );
				foreach ( aw_error_count_kinds() as $k => $name ) {
					$kind  = aw_error_count_kind( $r, $k );
					$label = aw_error_count_label( $r, $k );
					$top   = array();
					foreach ( $kind['samples'] as $s => $n ) {
						$top[] = esc_html( $s ) . ' (' . (int) $n . ')';
					}
					$rows[] = array(
						'check' => esc_html( $name ),
						'count' => $ok && ! $kind['count'] ? esc_html( $label ) : '<strong>' . esc_html( $label ) . '</strong>',
						'from'  => $files ? esc_html( implode( ', ', $files ) ) : '—',
						'top'   => $top ? implode( '<br>', $top ) : '—',
					);
				}
				aw_core_simple_table( 'error-count', array( 'check' => 'Check', 'count' => 'Count', 'from' => 'Read from', 'top' => 'Most frequent' ), $rows );
			}
		);
	}
);

/* ---------- WP-CLI: wp aw error-count [--paths] ---------- */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'aw error-count',
		/**
		 * Count PHP fatal errors, warnings, notices and deprecations in the last 24 hours and show the files read.
		 * Saves and emails nothing.
		 *
		 * [--paths]
		 * : Also list every path tried and what was found there.
		 */
		function ( $args, $assoc ) {
			$r = aw_error_count_collect()['php'];
			foreach ( aw_error_count_kinds() as $k => $name ) {
				$kind = aw_error_count_kind( $r, $k );
				WP_CLI::log( $name . ': ' . aw_error_count_label( $r, $k ) );
				foreach ( $kind['samples'] as $s => $n ) {
					WP_CLI::log( '  ' . $s . ' (' . (int) $n . ')' );
				}
			}
			foreach ( $r['files'] as $f => $n ) {
				WP_CLI::log( 'read ' . $f . ' (' . (int) $n . ' lines)' );
			}
			if ( ! empty( $assoc['paths'] ) ) {
				foreach ( $r['tried'] as $p => $st ) {
					WP_CLI::log( 'tried ' . $p . ': ' . $st );
				}
			}
		}
	);
}
