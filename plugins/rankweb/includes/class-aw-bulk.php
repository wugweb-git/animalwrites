<?php
/**
 * Bulk SEO work: the CSV format and the find-and-replace. Pure PHP, no WordPress, so tests/BulkTest.php runs it.
 * bulk-csv.php reads and writes the item fields; this class only turns rows into CSV and back and does the text
 * replacement, with no idea of the database.
 *
 * The CSV columns (in this order): id, title, description, focus_keyword, link_keywords, canonical. focus_keyword is a
 * comma list (Rank Math's own form); link_keywords is a pipe (|) list, so a keyword may contain a comma.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// This class builds and reads CSV in an in-memory php://temp stream (WordPress's WP_Filesystem cannot open a stream),
// so the file-operation sniffs do not apply here.
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_fclose

/** CSV and find-and-replace for bulk SEO work. */
final class AW_Bulk {

	/** The CSV columns, in order. */
	const COLUMNS = array( 'id', 'title', 'description', 'focus_keyword', 'link_keywords', 'canonical' );

	/**
	 * Rows to a CSV string (with the header row). A value for a column not in COLUMNS is ignored; a missing one is ''.
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
	 * A CSV string to rows keyed by header column. The header must include "id"; unknown columns are dropped, so an
	 * export from a spreadsheet with extra columns still imports. A BOM and CRLF line endings are handled.
	 *
	 * @param string $text CSV text.
	 * @return array{header: array<int,string>, rows: array<int,array<string,string>>, error: string}
	 */
	public static function from_csv( string $text ): array {
		$text = preg_replace( '/^\xEF\xBB\xBF/', '', $text ); // strip a UTF-8 BOM
		if ( '' === trim( (string) $text ) ) {
			return array(
				'header' => array(),
				'rows'   => array(),
				'error'  => 'The file is empty.',
			);
		}
		$fh = fopen( 'php://temp', 'r+' );
		fwrite( $fh, (string) $text );
		rewind( $fh );
		$header = fgetcsv( $fh, 0, ',', '"', '' );
		if ( ! is_array( $header ) ) {
			fclose( $fh );
			return array(
				'header' => array(),
				'rows'   => array(),
				'error'  => 'The file has no header row.',
			);
		}
		$header = array_map( fn( $h ) => strtolower( trim( (string) $h ) ), $header );
		if ( ! in_array( 'id', $header, true ) ) {
			fclose( $fh );
			return array(
				'header' => $header,
				'rows'   => array(),
				'error'  => 'The header must have an "id" column.',
			);
		}
		$keep = array_values( array_intersect( self::COLUMNS, $header ) );
		$rows = array();
		while ( false !== ( $line = fgetcsv( $fh, 0, ',', '"', '' ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- the standard fgetcsv read loop
			if ( array( null ) === $line || ( 1 === count( $line ) && null === $line[0] ) ) {
				continue; // a blank line
			}
			$assoc = array();
			foreach ( $header as $i => $col ) {
				if ( in_array( $col, $keep, true ) ) {
					$assoc[ $col ] = (string) ( $line[ $i ] ?? '' );
				}
			}
			if ( '' !== trim( (string) ( $assoc['id'] ?? '' ) ) ) {
				$rows[] = $assoc;
			}
		}
		fclose( $fh );
		return array(
			'header' => $header,
			'rows'   => $rows,
			'error'  => '',
		);
	}

	/**
	 * Find and replace in a string.
	 *
	 * @param string $text        The text.
	 * @param string $search      The search (plain text, or a regular expression without delimiters when $regex).
	 * @param string $replacement The replacement.
	 * @param bool   $regex       Treat $search as a regular expression.
	 * @param bool   $ci          Case-insensitive.
	 * @return array{text: string, count: int, error: string}
	 */
	public static function replace( string $text, string $search, string $replacement, bool $regex = false, bool $ci = false ): array {
		if ( '' === $search ) {
			return array(
				'text'  => $text,
				'count' => 0,
				'error' => 'Nothing to search for.',
			);
		}
		if ( ! $regex ) {
			$count = 0;
			$out   = $ci ? str_ireplace( $search, $replacement, $text, $count ) : str_replace( $search, $replacement, $text, $count );
			return array(
				'text'  => (string) $out,
				'count' => (int) $count,
				'error' => '',
			);
		}
		$pattern = '~' . str_replace( '~', '\~', $search ) . '~u' . ( $ci ? 'i' : '' );
		if ( false === @preg_match( $pattern, '' ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a bad pattern is reported, not fatal
			return array(
				'text'  => $text,
				'count' => 0,
				'error' => 'The regular expression is not valid.',
			);
		}
		$count = 0;
		$out   = preg_replace( $pattern, $replacement, $text, -1, $count );
		if ( null === $out ) {
			return array(
				'text'  => $text,
				'count' => 0,
				'error' => 'The regular expression could not be run.',
			);
		}
		return array(
			'text'  => (string) $out,
			'count' => (int) $count,
			'error' => '',
		);
	}
}

// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_fclose
