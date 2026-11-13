<?php
/**
 * The backup uninstall.php writes before it deletes anything (owner, 13 Nov 2026: "Delete data on uninstall - backup").
 *
 * One gzip-compressed JSON-lines file, wp-content/uploads/wugweb-backups/kits-core-uninstall-<YYYYmmdd-His>.jsonl.gz
 * (UTC time):
 *   line 1        {"type":"header","format":1,"plugin":…,"version":…,"site":…,"time":…,"options":[names],"tables":[names]}
 *   then          {"type":"option","name":…,"value":<option_value exactly as stored>,"autoload":…} per option
 *   then          {"type":"row","table":…,"row":{column:value,…}} per table row, read 500 at a time
 * A value that is not valid UTF-8 is stored base64-encoded and named in "b64" (["value"] or the row's column names).
 * Transients are not kept (caches). The folder gets an index.php and an .htaccess "Require all denied".
 *
 * The file is written as <name>.part and renamed when complete. If anything fails (folder, gzopen, a read or a write),
 * the part file is removed, one line goes to error_log() and kits_uninstall_backup() returns false: uninstall.php
 * then deletes nothing.
 *
 * Only uninstall.php and the tests load this file. Kept identical, apart from names, in each Wugweb plugin.
 *
 * @package kits-core
 */

// phpcs:disable WordPress.WP.AlternativeFunctions -- uninstall runs without WP_Filesystem credentials; plain file and gzip functions write the backup.
// phpcs:disable WordPress.WP.AlternativeFunctions.json_encode_json_encode -- exact bytes: wp_json_encode() would rewrite invalid UTF-8; such values are base64-encoded instead.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'KITS_BACKUP_BATCH' ) ) {
	define( 'KITS_BACKUP_BATCH', 500 );
}

/**
 * One JSON line (with its newline), or '' when it cannot be encoded.
 *
 * @param array $data The line's fields.
 */
function kits_backup_json( array $data ): string {
	$json = json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	return is_string( $json ) ? $json . "\n" : '';
}

/**
 * A value as JSON can carry it: [value, base64-encoded?]. Non-strings (null) pass as they are.
 *
 * @param mixed $value A stored value.
 */
function kits_backup_cell( $value ): array {
	if ( is_string( $value ) && 1 !== preg_match( '//u', $value ) ) {
		return array( base64_encode( $value ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- binary-safe copy of a stored value
	}
	return array( $value, false );
}

/**
 * The header line.
 *
 * @param string $plugin  Plugin slug.
 * @param string $version Plugin version.
 * @param string $site    Site address.
 * @param int    $time    Unix time.
 * @param array  $options Option names in the file.
 * @param array  $tables  Table names in the file.
 */
function kits_backup_header( string $plugin, string $version, string $site, int $time, array $options, array $tables ): string {
	return kits_backup_json(
		array(
			'type'    => 'header',
			'format'  => 1,
			'plugin'  => $plugin,
			'version' => $version,
			'site'    => $site,
			'time'    => gmdate( 'Y-m-d\TH:i:s\Z', $time ),
			'options' => array_values( array_map( 'strval', $options ) ),
			'tables'  => array_values( array_map( 'strval', $tables ) ),
		)
	);
}

/**
 * One option's line: its name, its option_value exactly as stored, its autoload.
 *
 * @param string      $name     Option name.
 * @param string|null $value    Raw option_value.
 * @param string      $autoload Autoload column.
 */
function kits_backup_option_line( string $name, $value, string $autoload ): string {
	list( $value, $b64 ) = kits_backup_cell( $value );
	$line                = array(
		'type'     => 'option',
		'name'     => $name,
		'value'    => $value,
		'autoload' => $autoload,
	);
	if ( $b64 ) {
		$line['b64'] = array( 'value' );
	}
	return kits_backup_json( $line );
}

/**
 * One table row's line.
 *
 * @param string $table Table name.
 * @param array  $row   Column => raw value.
 */
function kits_backup_row_line( string $table, array $row ): string {
	$b64 = array();
	foreach ( $row as $col => $value ) {
		list( $row[ $col ], $was ) = kits_backup_cell( $value );
		if ( $was ) {
			$b64[] = (string) $col;
		}
	}
	$line = array(
		'type'  => 'row',
		'table' => $table,
		'row'   => (object) $row,
	);
	if ( $b64 ) {
		$line['b64'] = $b64;
	}
	return kits_backup_json( $line );
}

/**
 * Streams rows in batches: $fetch( $offset, $limit ) gives an array of rows (null on a read error), $write( $row )
 * gives true when written. Stops after a short batch.
 *
 * @param callable $fetch Reads one batch.
 * @param callable $write Writes one row.
 * @param int      $batch Rows per batch.
 * @return int|false Rows written, or false on a read or write error.
 */
function kits_backup_rows( callable $fetch, callable $write, int $batch = KITS_BACKUP_BATCH ) {
	$batch  = max( 1, $batch );
	$offset = 0;
	$done   = 0;
	do {
		$rows = $fetch( $offset, $batch );
		if ( ! is_array( $rows ) ) {
			return false;
		}
		foreach ( $rows as $row ) {
			if ( true !== $write( (array) $row ) ) {
				return false;
			}
			++$done;
		}
		$offset += $batch;
	} while ( count( $rows ) === $batch );
	return $done;
}

/**
 * Makes the folder unreadable from the web: index.php and .htaccess "Require all denied" (kept if already there).
 *
 * @param string $dir The backup folder.
 */
function kits_backup_protect( string $dir ): bool {
	$files = array(
		'index.php' => "<?php\n// Silence is golden.\n",
		'.htaccess' => "Require all denied\n<IfModule !mod_authz_core.c>\n\tDeny from all\n</IfModule>\n",
	);
	foreach ( $files as $name => $body ) {
		$path = $dir . '/' . $name;
		if ( ! is_file( $path ) && strlen( $body ) !== file_put_contents( $path, $body ) ) {
			return false;
		}
	}
	return true;
}

/**
 * The backup file's name.
 *
 * @param string $plugin Plugin slug.
 * @param int    $time   Unix time.
 */
function kits_backup_name( string $plugin, int $time ): string {
	return $plugin . '-uninstall-' . gmdate( 'Ymd-His', $time ) . '.jsonl.gz';
}

/**
 * Writes the backup of exactly what uninstall.php is about to delete.
 *
 * @param string $plugin   Plugin slug.
 * @param string $main     The plugin's main file (for its version).
 * @param array  $names    Option names to be deleted.
 * @param array  $prefixes Option name prefixes to be deleted (not transients).
 * @param array  $tables   Tables to be dropped, with the site prefix.
 * @return bool True when the whole backup is on disk; false means: delete nothing.
 */
function kits_uninstall_backup( string $plugin, string $main, array $names, array $prefixes, array $tables ): bool {
	global $wpdb;
	$fail = static function ( string $why ) use ( $plugin ): bool {
		error_log( $plugin . ' uninstall: backup not written (' . $why . '); nothing deleted.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- the one line the owner asked for
		return false;
	};

	$up = wp_upload_dir( null, false );
	if ( ! empty( $up['error'] ) || empty( $up['basedir'] ) ) {
		return $fail( 'no uploads folder' );
	}
	$dir = rtrim( (string) $up['basedir'], '/\\' ) . '/wugweb-backups';
	if ( ! wp_mkdir_p( $dir ) || ! is_writable( $dir ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- see the file comment
		return $fail( 'folder not writable: ' . $dir );
	}
	if ( ! kits_backup_protect( $dir ) ) {
		return $fail( 'could not protect ' . $dir );
	}

	// Options, read from the table as stored (no unserialize, no filters).
	$opts = array();
	foreach ( array_chunk( array_values( array_unique( array_map( 'strval', $names ) ) ), 100 ) as $chunk ) {
		$in   = implode( ', ', array_fill( 0, count( $chunk ), '%s' ) );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name IN ($in)", $chunk ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- uninstall backup: the raw stored values; $in is placeholders only
		if ( '' !== $wpdb->last_error || ! is_array( $rows ) ) {
			return $fail( 'options could not be read' );
		}
		foreach ( $rows as $row ) {
			$opts[ $row['option_name'] ] = $row;
		}
	}
	foreach ( $prefixes as $start ) {
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( (string) $start ) . '%' ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- uninstall backup: the raw stored values
		if ( '' !== $wpdb->last_error || ! is_array( $rows ) ) {
			return $fail( 'options could not be read' );
		}
		foreach ( $rows as $row ) {
			$opts[ $row['option_name'] ] = $row;
		}
	}
	ksort( $opts );

	// Tables that exist, with their primary key for a stable order.
	$have = array();
	foreach ( $tables as $table ) {
		$table = (string) $table;
		if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- uninstall backup
			continue;
		}
		$keys           = $wpdb->get_results( 'SHOW KEYS FROM `' . esc_sql( $table ) . "` WHERE Key_name = 'PRIMARY'", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- uninstall backup: the plugin's own table name
		$cols           = is_array( $keys ) ? array_map( static fn( $k ) => '`' . esc_sql( (string) $k['Column_name'] ) . '`', $keys ) : array();
		$have[ $table ] = $cols ? ' ORDER BY ' . implode( ', ', $cols ) : '';
	}

	$time = time();
	$file = $dir . '/' . kits_backup_name( $plugin, $time );
	$part = $file . '.part';
	if ( file_exists( $file ) || file_exists( $part ) ) {
		return $fail( 'file already there: ' . $file );
	}
	$gz = gzopen( $part, 'wb6' );
	if ( ! $gz ) {
		return $fail( 'gzopen failed: ' . $part );
	}
	$put = static function ( string $line ) use ( $gz ): bool {
		return '' !== $line && strlen( $line ) === gzwrite( $gz, $line );
	};
	$abort = static function ( string $why ) use ( $gz, $part, $fail ): bool {
		gzclose( $gz );
		if ( is_file( $part ) ) {
			unlink( $part ); // our own incomplete file
		}
		return $fail( $why );
	};

	$data    = get_file_data( $main, array( 'Version' => 'Version' ) );
	$version = (string) ( $data['Version'] ?? '' );
	if ( ! $put( kits_backup_header( $plugin, $version, (string) home_url( '/' ), $time, array_keys( $opts ), array_keys( $have ) ) ) ) {
		return $abort( 'write failed' );
	}
	foreach ( $opts as $name => $row ) {
		if ( ! $put( kits_backup_option_line( (string) $name, $row['option_value'], (string) $row['autoload'] ) ) ) {
			return $abort( 'write failed' );
		}
	}
	foreach ( $have as $table => $order ) {
		$fetch = static function ( int $offset, int $limit ) use ( $wpdb, $table, $order ) {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM `' . esc_sql( $table ) . '`' . $order . ' LIMIT %d OFFSET %d', $limit, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- uninstall backup: the plugin's own table, in batches
			return '' === $wpdb->last_error && is_array( $rows ) ? $rows : null;
		};
		$write = static fn( array $row ): bool => $put( kits_backup_row_line( $table, $row ) );
		if ( false === kits_backup_rows( $fetch, $write ) ) {
			return $abort( 'table ' . $table . ' could not be copied' );
		}
	}
	if ( ! gzclose( $gz ) ) {
		if ( is_file( $part ) ) {
			unlink( $part );
		}
		return $fail( 'gzclose failed' );
	}
	if ( ! rename( $part, $file ) ) {
		return $fail( 'could not rename ' . $part );
	}
	return true;
}
