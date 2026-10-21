<?php
/**
 * Form entries without email alerts (owner, 21 Oct 2026): the forms send no email, so each form's new entries are a
 * count in WP Admin, and data removal requests stay at the top of the Dashboard until someone marks them handled.
 *
 * - Website messages: Mail → Website forms (Mailweb, mailweb-aw.php), unread count on Mail.
 * - Listing claims: Inbox → Listing claims, claims waiting for a decision (admin-ia.php).
 * - Reports: Inbox → Reports, reports still New.
 * - Volunteer sign-ups: Inbox → Volunteer sign-ups, sign-ups since you last opened the list (counted per person,
 *   from 21 Oct 2026). The list is the site's volunteers table; FluentCRM has the same people (tag Volunteer).
 * - Data removal requests: Inbox → Data removal, requests that are New (waiting for the email link) or Email verified
 *   (waiting for an administrator to erase them in Tools → Erase Personal Data); and a notice on the Dashboard with
 *   "Decline" (owner, 21 Oct 2026: request-only; nothing is erased automatically).
 * The Inbox menu's own count adds these up (admin-ia.php).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_REMOVAL_STATUSES = array( 'request-pending', 'request-confirmed', 'request-failed', 'request-completed' );

/** Reports still New. */
function aw_alert_reports() {
	return count( get_posts( array( 'post_type' => 'aw_report', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => 'aw_r_status', 'meta_value' => 'new' ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
}

/** When the current person last opened the volunteer list (GMT), never before the alerts began. */
function aw_alert_volunteers_seen() {
	$since = (string) get_option( 'aw_vol_alerts_since', '' );
	if ( '' === $since ) {
		$since = current_time( 'mysql', true );
		add_option( 'aw_vol_alerts_since', $since, '', false );
	}
	$seen = (string) get_user_meta( get_current_user_id(), 'aw_vol_seen', true );
	return strcmp( $seen, $since ) > 0 ? $seen : $since;
}

/** Volunteer sign-ups since the current person last opened the list. */
function aw_alert_volunteers() {
	global $wpdb;
	if ( ! function_exists( 'aw_vol_table' ) ) {
		return 0;
	}
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . aw_vol_table() . ' WHERE created_at > %s', aw_alert_volunteers_seen() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

/** Data removal requests that are New or Email verified, newest first. */
function aw_alert_removals() {
	static $open = null;
	return $open ?? $open = get_posts(
		array(
			'post_type'   => 'user_request',
			'name'        => 'remove_personal_data',
			'post_status' => array( 'request-pending', 'request-confirmed' ),
			'numberposts' => 50,
		)
	);
}

/** Everything the Inbox count adds to claims and To review. */
function aw_alert_total() {
	return aw_alert_reports() + aw_alert_volunteers() + count( aw_alert_removals() );
}

// Inbox → Volunteer sign-ups and Inbox → Data removal (links to the existing screens), with their counts.
add_action(
	'admin_menu',
	function () {
		if ( current_user_can( AW_VOL_CAP ) ) {
			add_submenu_page( 'aw-inbox', 'Volunteer sign-ups', 'Volunteer sign-ups' . aw_menu_count( aw_alert_volunteers() ), AW_VOL_CAP, 'admin.php?page=aw-volunteers' );
		}
		if ( current_user_can( 'manage_options' ) ) {
			add_submenu_page( 'aw-inbox', 'Data removal', 'Data removal' . aw_menu_count( count( aw_alert_removals() ) ), 'manage_options', 'erase-personal-data.php' );
		}
	},
	10
);

// Opening the volunteer list marks its sign-ups seen (for this person).
add_action(
	'load-toplevel_page_aw-volunteers',
	function () {
		update_user_meta( get_current_user_id(), 'aw_vol_seen', current_time( 'mysql', true ) );
	}
);

/** Where a request stands, in words. */
function aw_removal_stage( $p ) {
	$stage = array(
		'request-pending'   => 'New: waiting for them to open the confirmation email',
		'request-confirmed' => 'Email verified: review it, then erase in Tools → Erase Personal Data',
		'request-failed'    => get_post_meta( $p->ID, 'aw_dr_declined', true ) ? 'Declined' : 'Failed',
		'request-completed' => 'Done: data erased',
	);
	return $stage[ $p->post_status ] ?? $p->post_status;
}

// Dashboard: data removal requests at the top until marked handled.
add_action(
	'admin_notices',
	function () {
		$screen = get_current_screen();
		if ( ! $screen || 'dashboard' !== $screen->id || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$open = aw_alert_removals();
		if ( ! $open ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>Data removal requests (' . count( $open ) . ')</strong> · <a href="' . esc_url( admin_url( 'erase-personal-data.php' ) ) . '">Tools → Erase Personal Data</a></p><ul>';
		foreach ( $open as $p ) {
			$email = (string) get_post_meta( $p->ID, '_wp_user_request_user_email', true );
			$email = '' !== $email ? $email : $p->post_title;
			$scope = (string) get_post_meta( $p->ID, 'aw_dr_scope', true );
			$ask   = array_filter( array( (string) get_post_meta( $p->ID, 'aw_dr_ref', true ), (string) get_post_meta( $p->ID, 'aw_dr_name', true ), 'remove: ' . ( AW_REMOVAL_SCOPES[ $scope ] ?? 'Everything' ), (string) get_post_meta( $p->ID, 'aw_dr_reason', true ) ) );
			echo '<li><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">'
				. esc_html( $email ) . ' · ' . esc_html( implode( ' · ', $ask ) ) . ' · received ' . esc_html( wp_date( 'j M Y, H:i', strtotime( $p->post_date_gmt . ' UTC' ) ) ) . ' · ' . esc_html( aw_removal_stage( $p ) ) . ' '
				. '<input type="hidden" name="action" value="aw_removal_handled"><input type="hidden" name="request" value="' . (int) $p->ID . '">'
				. wp_nonce_field( 'aw_removal_handled_' . $p->ID, '_wpnonce', true, false )
				. '<button type="submit" class="button button-small" onclick="return confirm(\'Decline this request? Nothing is erased.\')">Decline</button></form></li>';
		}
		echo '</ul></div>';
	}
);

add_action(
	'admin_post_aw_removal_handled',
	function () {
		$id = (int) ( $_POST['request'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification -- checked next
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'aw_removal_handled_' . $id ) || 'user_request' !== get_post_type( $id ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		update_post_meta( $id, 'aw_dr_declined', current_time( 'mysql' ) . ' by ' . wp_get_current_user()->user_login );
		wp_update_post( array( 'ID' => $id, 'post_status' => 'request-failed' ) ); // Declined (shown as Failed in Tools → Erase Personal Data)
		wp_safe_redirect( admin_url( 'index.php' ) );
		exit;
	}
);
