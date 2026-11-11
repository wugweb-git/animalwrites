<?php
/**
 * Import from Dates (plan 2.3). Once PlanWeb is on, every element of the option aw_team_dates (as stored) becomes one
 * entry: date, title and note copied as they are, Type reminder, Status idea. Keyed by date, title, note and copy
 * number, so identical elements stay separate and a second run adds nothing. The option is never written.
 * planweb_verify() compares the two one for one (PlanWeb → Calendar → Import, and wp planweb verify).
 *
 * @package planweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The first admin request after PlanWeb is switched on. Waits for Dates' 11 Nov note rename, so the notes copied
// are the renamed ones (or runs at once when there is no Dates list at all).
add_action(
	'admin_init',
	function () {
		if ( ! planweb_on() || get_option( 'planweb_import_done' ) || ! post_type_exists( PLANWEB_TYPE ) ) {
			return;
		}
		if ( is_array( get_option( 'aw_team_dates', null ) ) && ! get_option( 'aw_team_dates_links_note_1111' ) ) {
			return;
		}
		planweb_import();
	},
	30
);

/**
 * Run the import. Safe to run again: an element already imported (even if its entry is in the Trash) is skipped.
 *
 * @return array The result, also stored in the option planweb_import_result.
 */
function planweb_import(): array {
	if ( ! add_option( 'planweb_import_lock', time(), '', false ) ) {
		$held = (int) get_option( 'planweb_import_lock' );
		if ( $held > time() - 5 * MINUTE_IN_SECONDS ) {
			return (array) get_option( 'planweb_import_result', array() );
		}
		update_option( 'planweb_import_lock', time(), false ); // A run that died long ago.
	}
	$stored = get_option( 'aw_team_dates', null );
	$map    = planweb_map_dates( $stored );
	$have   = array();
	$q      = new WP_Query(
		array(
			'post_type'      => PLANWEB_TYPE,
			'post_status'    => array( 'publish', 'trash' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_key'       => 'planweb_source', // phpcs:ignore WordPress.DB.SlowDBQuery -- once.
			'meta_value'     => 'aw_team_dates', // phpcs:ignore WordPress.DB.SlowDBQuery -- once.
		)
	);
	foreach ( $q->posts as $id ) {
		$have[ (string) get_post_meta( $id, 'planweb_source_key', true ) ] = true;
	}
	$created  = 0;
	$skipped  = 0;
	$failed   = array();
	$personal = array();
	kses_remove_filters(); // Copy title and note exactly (WP-CLI has no user, so KSES would turn & into &amp;).
	foreach ( $map['rows'] as $r ) {
		if ( isset( $have[ $r['key'] ] ) ) {
			++$skipped;
			continue;
		}
		$id = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => PLANWEB_TYPE,
					'post_status'  => 'publish',
					'post_title'   => $r['title'],
					'post_content' => $r['note'],
				)
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			$failed[] = array(
				'index' => $r['index'],
				'error' => $id->get_error_message(),
			);
			continue;
		}
		update_post_meta( $id, 'planweb_date', $r['date'] );
		update_post_meta( $id, 'planweb_status', 'idea' );
		update_post_meta( $id, 'planweb_repeat', 'none' );
		update_post_meta( $id, 'planweb_source', 'aw_team_dates' );
		update_post_meta( $id, 'planweb_source_key', $r['key'] );
		wp_set_object_terms( $id, array( 'reminder' ), 'planweb_type' );
		if ( '' !== planweb_personal_data_problem( $r['title'] . "\n" . $r['note'] ) ) {
			$personal[] = (int) $id;
		}
		$have[ $r['key'] ] = true;
		++$created;
	}
	kses_init();
	$result = array(
		'time'     => time(),
		'found'    => is_array( $stored ),
		'stored'   => is_array( $stored ) ? count( $stored ) : 0,
		'valid'    => count( $map['rows'] ),
		'created'  => $created,
		'skipped'  => $skipped,
		'invalid'  => $map['invalid'],
		'failed'   => $failed,
		'personal' => $personal,
	);
	update_option( 'planweb_import_result', $result, false );
	if ( ! $failed ) {
		update_option( 'planweb_import_done', time(), false );
	}
	delete_option( 'planweb_import_lock' );
	return $result;
}

/**
 * The imported entries (not those in the Trash), by import key.
 *
 * @return array key => [id, date, title, note].
 */
function planweb_imported_entries(): array {
	$out = array();
	if ( ! post_type_exists( PLANWEB_TYPE ) ) {
		return $out;
	}
	$q = new WP_Query(
		array(
			'post_type'      => PLANWEB_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'meta_key'       => 'planweb_source', // phpcs:ignore WordPress.DB.SlowDBQuery -- a check screen.
			'meta_value'     => 'aw_team_dates', // phpcs:ignore WordPress.DB.SlowDBQuery -- a check screen.
		)
	);
	foreach ( $q->posts as $p ) {
		$out[ (string) get_post_meta( $p->ID, 'planweb_source_key', true ) ] = array(
			'id'    => (int) $p->ID,
			'date'  => (string) get_post_meta( $p->ID, 'planweb_date', true ),
			'title' => (string) $p->post_title,
			'note'  => (string) $p->post_content,
		);
	}
	return $out;
}

/**
 * The one-for-one check: the Dates option as stored against the imported entries. Reads only.
 *
 * @return array planweb_compare() plus invalid (elements the import cannot take).
 */
function planweb_verify(): array {
	$map = planweb_map_dates( get_option( 'aw_team_dates', null ) );
	return planweb_compare( $map['rows'], planweb_imported_entries() ) + array( 'invalid' => $map['invalid'] );
}
