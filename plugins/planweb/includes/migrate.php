<?php
/**
 * One-time data moves (owner's yes, 14 Nov 2026), run once by an administrator's admin request after the list values
 * are in place (planweb_setup(), PLANWEB_SETUP 2: Threads and Bluesky). Titles and notes are never changed.
 *
 *   1. Several channels: an entry whose title starts with channels in brackets, such as "[X + LinkedIn] …", gets every
 *      channel named there (added to the one it has). A bracket with a part that is not a channel is left alone.
 *   2. Done: the seven reminders closed on 14 Nov 2026 as Sent (25181 to 25187) move to Done. Each only while it is
 *      still Sent and its note starts "Closed 14 Nov 2026". 25370 (reopened that day as an Idea) is not touched.
 *
 * The result is kept in the option planweb_migrate_1114 (time, channels: entry IDs, done: entry IDs).
 *
 * @package planweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Option recording the run. */
const PLANWEB_MIGRATE = 'planweb_migrate_1114';

/** The entries closed as Sent on 14 Nov 2026 that move to Done. */
const PLANWEB_MIGRATE_DONE_IDS = array( 25181, 25182, 25183, 25184, 25185, 25186, 25187 );

add_action(
	'admin_init',
	function () {
		if ( ! planweb_on() || get_option( PLANWEB_MIGRATE ) || PLANWEB_SETUP !== (string) get_option( 'planweb_setup', '' ) ) {
			return;
		}
		if ( wp_doing_ajax() || ! current_user_can( 'manage_options' ) || ! post_type_exists( PLANWEB_TYPE ) ) {
			return;
		}
		planweb_migrate_1114();
	}
);

/**
 * The moves. Safe to run again: a second run finds nothing to change.
 *
 * @return array The result (also stored).
 */
function planweb_migrate_1114(): array {
	if ( ! add_option( PLANWEB_MIGRATE . '_lock', time(), '', false ) ) {
		if ( time() - (int) get_option( PLANWEB_MIGRATE . '_lock' ) < 10 * MINUTE_IN_SECONDS ) {
			return array(); // Another request is on it.
		}
		update_option( PLANWEB_MIGRATE . '_lock', time(), false ); // A run that died long ago.
	}
	foreach ( array_keys( PLANWEB_CHANNELS ) as $slug ) {
		if ( ! term_exists( $slug, 'planweb_channel' ) ) {
			delete_option( PLANWEB_MIGRATE . '_lock' );
			return array(); // The list values are not in place yet; try again later.
		}
	}
	$result = array(
		'time'     => time(),
		'channels' => array(),
		'done'     => array(),
	);
	foreach ( planweb_entries( '', '', array( 'posts_per_page' => -1 ) ) as $e ) {
		$named = planweb_title_channels( $e['title'] );
		if ( $named && array_diff( $named, $e['channels'] ) ) {
			wp_set_object_terms( $e['id'], $named, 'planweb_channel', true ); // Added to what it has.
			$result['channels'][] = $e['id'];
		}
	}
	foreach ( PLANWEB_MIGRATE_DONE_IDS as $id ) {
		$e = 'publish' === get_post_status( $id ) ? planweb_entry( $id ) : null;
		if ( $e && 'sent' === $e['status'] && str_starts_with( ltrim( $e['note'] ), 'Closed 14 Nov 2026' ) ) {
			update_post_meta( $id, 'planweb_status', 'done' );
			$result['done'][] = $id;
		}
	}
	update_option( PLANWEB_MIGRATE, $result, false );
	delete_option( PLANWEB_MIGRATE . '_lock' );
	planweb_counts_request();
	return $result;
}
