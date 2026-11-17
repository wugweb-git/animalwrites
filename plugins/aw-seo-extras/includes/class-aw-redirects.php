<?php
/**
 * Redirect tools: the CSV format, row validation and the .htaccess block. Pure PHP, no WordPress, so
 * tests/RedirectsTest.php runs it. redirect-tools.php reads and writes Rank Math's redirection list; this class only
 * turns rows into CSV and back, checks a row, and builds Apache directives.
 *
 * CSV columns (in order): source, target, code, comparison. code is 301, 302, 307, 308, 410 or 451; comparison is
 * exact or regex. A 410 or 451 ("gone") needs no target.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// In-memory php://temp streams only (WP_Filesystem cannot open a stream); the file-operation sniffs do not apply.
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_fclose

/** Redirect CSV and the .htaccess block. */
final class AW_Redirects {

	/** The CSV columns, in order. */
	const COLUMNS = array( 'source', 'target', 'code', 'comparison' );

	/** The status codes a redirect may carry. */
	const CODES = array( 301, 302, 307, 308, 410, 451 );

	/** The codes that mean "gone" (no target needed). */
	const GONE = array( 410, 451 );

	/**
	 * Rows to a CSV string (with the header).
	 *
	 * @param array<int,array<string,string>> $rows Rows keyed by column.
	 */
	public static function to_csv( array $rows ): string {
		$fh = fopen( 'php://temp', 'r+' );
		fputcsv( $fh, self::COLUMNS, ',', '"', '' );
		foreach ( $rows as $row ) {
			$line = array();
			foreach ( self::COLUMNS as $col ) {
				$line[] = (string) ( $row[ $col ] ?? '' );
			}
			fputcsv( $fh, $line, ',', '"', '' );
		}
		rewind( $fh );
		$csv = (string) stream_get_contents( $fh );
		fclose( $fh );
		return $csv;
	}

	/**
	 * A CSV string to rows keyed by header column. The header must include "source".
	 *
	 * @param string $text CSV text.
	 * @return array{rows: array<int,array<string,string>>, error: string}
	 */
	public static function from_csv( string $text ): array {
		$text = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $text );
		if ( '' === trim( (string) $text ) ) {
			return array(
				'rows'  => array(),
				'error' => 'The file is empty.',
			);
		}
		$fh = fopen( 'php://temp', 'r+' );
		fwrite( $fh, (string) $text );
		rewind( $fh );
		$header = fgetcsv( $fh, 0, ',', '"', '' );
		if ( ! is_array( $header ) ) {
			fclose( $fh );
			return array(
				'rows'  => array(),
				'error' => 'The file has no header row.',
			);
		}
		$header = array_map( fn( $h ) => strtolower( trim( (string) $h ) ), $header );
		if ( ! in_array( 'source', $header, true ) ) {
			fclose( $fh );
			return array(
				'rows'  => array(),
				'error' => 'The header must have a "source" column.',
			);
		}
		$rows = array();
		while ( false !== ( $line = fgetcsv( $fh, 0, ',', '"', '' ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- the standard fgetcsv read loop
			if ( array( null ) === $line || ( 1 === count( $line ) && null === $line[0] ) ) {
				continue;
			}
			$assoc = array();
			foreach ( $header as $i => $col ) {
				if ( in_array( $col, self::COLUMNS, true ) ) {
					$assoc[ $col ] = (string) ( $line[ $i ] ?? '' );
				}
			}
			if ( '' !== trim( (string) ( $assoc['source'] ?? '' ) ) ) {
				$rows[] = $assoc;
			}
		}
		fclose( $fh );
		return array(
			'rows'  => $rows,
			'error' => '',
		);
	}

	/**
	 * Check and tidy one row. Returns source, target, code (int), comparison, and error ('' when it is good).
	 *
	 * @param array $row A row from from_csv().
	 * @return array{source: string, target: string, code: int, comparison: string, error: string}
	 */
	public static function validate( array $row ): array {
		$source     = trim( (string) ( $row['source'] ?? '' ) );
		$target     = trim( (string) ( $row['target'] ?? '' ) );
		$comparison = strtolower( trim( (string) ( $row['comparison'] ?? 'exact' ) ) );
		$comparison = in_array( $comparison, array( 'exact', 'regex' ), true ) ? $comparison : 'exact';
		$code       = (int) ( '' !== trim( (string) ( $row['code'] ?? '' ) ) ? $row['code'] : 301 );
		$out        = array(
			'source'     => $source,
			'target'     => $target,
			'code'       => $code,
			'comparison' => $comparison,
			'error'      => '',
		);
		if ( '' === $source ) {
			$out['error'] = 'No source.';
		} elseif ( ! in_array( $code, self::CODES, true ) ) {
			$out['error'] = 'Unknown status code ' . $code . '.';
		} elseif ( '' === $target && ! in_array( $code, self::GONE, true ) ) {
			$out['error'] = 'No target.';
		}
		return $out;
	}

	/**
	 * Apache / LiteSpeed directives for the exact redirects (regex and "gone" are handled as noted). The caller wraps
	 * these between its own markers with insert_with_markers().
	 *
	 * @param array<int,array> $redirects Validated redirects (as validate() returns, error ''), each with an absolute
	 *                                    or root-relative target path for exact rules.
	 * @return array<int,string> Directive lines.
	 */
	public static function htaccess_lines( array $redirects ): array {
		$lines = array();
		foreach ( $redirects as $r ) {
			$source     = '/' . trim( (string) ( $r['source'] ?? '' ), '/' );
			$code       = (int) ( $r['code'] ?? 301 );
			$comparison = (string) ( $r['comparison'] ?? 'exact' );
			if ( 'exact' !== $comparison ) {
				continue; // regex rules stay in Rank Math (its matching is not Apache's)
			}
			if ( in_array( $code, self::GONE, true ) ) {
				// Apache's own Redirect form for a status with no destination; no regex, so a source with ( or [ cannot break .htaccess.
				$lines[] = 'Redirect ' . $code . ' ' . self::escape( $source );
				continue;
			}
			$target = (string) ( $r['target'] ?? '' );
			if ( '' === $target ) {
				continue;
			}
			$lines[] = 'Redirect ' . $code . ' ' . self::escape( $source ) . ' ' . self::escape( $target );
		}
		return $lines;
	}

	/**
	 * A path or URL made safe for an .htaccess line: no newline, no space or quote that would break the directive.
	 *
	 * @param string $value A path or URL.
	 */
	private static function escape( string $value ): string {
		$value = str_replace( array( "\r", "\n" ), '', $value );
		return str_replace( array( ' ', '"', '\\' ), array( '%20', '%22', '' ), $value );
	}
}

// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_fclose
