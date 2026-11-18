<?php
/**
 * WP-CLI: wp planweb verify (reads only, safe on live) and wp planweb import.
 *
 * @package planweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Dates import and its one-for-one check.
 */
class PlanWeb_CLI {

	/**
	 * Compare the Dates option, as stored, with the imported entries, one for one. Reads only.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table (default) or json.
	 *
	 * ## EXAMPLES
	 *
	 *     wp planweb verify
	 *
	 * @param array $args  Positional arguments.
	 * @param array $assoc Options.
	 */
	public function verify( $args, $assoc ) {
		if ( ! planweb_on() ) {
			WP_CLI::error( 'PlanWeb is switched off (Appearance → Kits → Modules).' );
		}
		$v    = planweb_verify();
		$rows = array();
		foreach ( $v['lines'] as $l ) {
			$rows[] = array(
				'check'       => $l['state'],
				'dates_date'  => $l['dates']['date'] ?? '',
				'dates_title' => $l['dates']['title'] ?? '',
				'entry_id'    => $l['entry']['id'] ?? '',
				'entry_date'  => $l['entry']['date'] ?? '',
				'entry_title' => $l['entry']['title'] ?? '',
			);
		}
		WP_CLI\Utils\format_items( $assoc['format'] ?? 'table', $rows, array_keys( $rows ? $rows[0] : array( 'check' => 1 ) ) );
		foreach ( $v['invalid'] as $bad ) {
			WP_CLI::warning( sprintf( 'Dates element %d is not a date and was not imported: %s', (int) $bad['index'] + 1, $bad['value'] ) );
		}
		$line = sprintf( '%d the same, %d different, %d missing, %d extra.', $v['same'], $v['differs'], $v['missing'], $v['extra'] );
		if ( $v['differs'] + $v['missing'] + $v['extra'] ) {
			WP_CLI::error( $line );
		}
		WP_CLI::success( $line );
	}

	/**
	 * Import the Dates option (adds only dates not imported yet; never changes the option).
	 *
	 * ## EXAMPLES
	 *
	 *     wp planweb import
	 */
	public function import() {
		if ( ! planweb_on() ) {
			WP_CLI::error( 'PlanWeb is switched off (Appearance → Kits → Modules).' );
		}
		$r = planweb_import();
		foreach ( (array) ( $r['failed'] ?? array() ) as $f ) {
			WP_CLI::warning( sprintf( 'Date %d: %s', (int) $f['index'] + 1, $f['error'] ) );
		}
		WP_CLI::success( sprintf( '%d dates in Dates, %d entries made, %d already there, %d not dates.', (int) ( $r['stored'] ?? 0 ), (int) ( $r['created'] ?? 0 ), (int) ( $r['skipped'] ?? 0 ), count( (array) ( $r['invalid'] ?? array() ) ) ) );
	}
}

WP_CLI::add_command( 'planweb', 'PlanWeb_CLI' );
