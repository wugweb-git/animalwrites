<?php
/**
 * Mailboxes and aliases.
 *
 * - Mailboxes: every mailbox the Mail API token can see (GET /api/v1/me, mail-in.php). Nothing is built in.
 * - Sending: per mailbox, whether replies and new messages may go from it (Mail → Settings → Mailboxes and aliases),
 *   and which one is the default "From". A mailbox kept for one person, or one that only sends site notices, can be
 *   left out.
 * - Aliases: the Mail API does not list them, so the map is kept here (alias = mailbox, as in hPanel → Emails → Email
 *   Alias).
 *
 * @package mailweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Every mailbox: key (local part) => address, as the token sees them. */
function mailweb_all_boxes() {
	if ( '' !== mailweb_setting( 'api_token' ) ) {
		mailweb_box_ids(); // fills mailweb_box_addresses once
	}
	return array_map( 'strtolower', array_filter( (array) get_option( 'mailweb_box_addresses', array() ), 'is_email' ) );
}

/** Mailbox settings: key => ['send' => bool]. Mailboxes not listed may send. */
function mailweb_box_settings() {
	return (array) get_option( 'mailweb_mailboxes', array() );
}

/** Mailboxes the inbox may send from (all of them when none is allowed, so sending never breaks). */
function mailweb_send_boxes() {
	$all  = mailweb_all_boxes();
	$set  = mailweb_box_settings();
	$send = array_filter( $all, fn( $k ) => ! isset( $set[ $k ]['send'] ) || $set[ $k ]['send'], ARRAY_FILTER_USE_KEY );
	return $send ? $send : $all;
}

/** The default "From" mailbox key. */
function mailweb_default_from() {
	$send = mailweb_send_boxes();
	$want = (string) get_option( 'mailweb_default_from', '' );
	return isset( $send[ $want ] ) ? $want : (string) array_key_first( $send );
}

/** Alias map: alias address => mailbox address. */
function mailweb_aliases() {
	return (array) get_option( 'mailweb_aliases', array() );
}

/** The aliases that deliver to one mailbox. */
function mailweb_aliases_of( $mailbox ) {
	return array_keys( array_filter( mailweb_aliases(), fn( $m ) => strtolower( $mailbox ) === $m ) );
}

/** The mailbox an address lands in (itself for a mailbox, its mailbox for an alias), or ''. */
function mailweb_mailbox_of( $address ) {
	$a = strtolower( trim( (string) $address ) );
	if ( in_array( $a, mailweb_all_boxes(), true ) ) {
		return $a;
	}
	return (string) ( mailweb_aliases()[ $a ] ?? '' );
}

/** The domains of our own mailboxes and aliases. */
function mailweb_own_domains() {
	$out = array();
	foreach ( array_merge( array_values( mailweb_all_boxes() ), array_keys( mailweb_aliases() ) ) as $a ) {
		$out[] = substr( (string) strrchr( $a, '@' ), 1 );
	}
	return array_values( array_unique( array_filter( $out ) ) );
}

/* ---------- Mail → Settings: mailboxes and aliases ---------- */

add_action(
	'mailweb_settings_after',
	function () {
		$boxes = mailweb_all_boxes();
		echo '<h2 id="addresses">' . esc_html__( 'Mailboxes and aliases', 'mailweb' ) . '</h2>';
		if ( isset( $_GET['mailweb_alias'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Saved.', 'mailweb' ) . '</p></div>';
		}
		if ( ! $boxes ) {
			echo '<p>' . esc_html__( 'No mailboxes yet: save the Mail API token above.', 'mailweb' ) . '</p>';
			return;
		}
		$set  = mailweb_box_settings();
		$def  = mailweb_default_from();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'mailweb_alias' );
		echo '<input type="hidden" name="action" value="mailweb_alias">';
		echo '<table class="widefat striped" style="max-width:48rem"><thead><tr><th scope="col">' . esc_html__( 'Mailbox', 'mailweb' ) . '</th><th scope="col">' . esc_html__( 'Aliases', 'mailweb' ) . '</th><th scope="col">' . esc_html__( 'Send from it', 'mailweb' ) . '</th><th scope="col">' . esc_html__( 'Default From', 'mailweb' ) . '</th></tr></thead><tbody>';
		foreach ( $boxes as $key => $addr ) {
			$alias = mailweb_aliases_of( $addr );
			$send  = ! isset( $set[ $key ]['send'] ) || $set[ $key ]['send'];
			echo '<tr><td>' . esc_html( $addr ) . '</td><td>' . esc_html( $alias ? implode( ', ', $alias ) : '–' ) . '</td>';
			/* translators: %s: mailbox address. */
			echo '<td><label><input type="checkbox" name="send[' . esc_attr( $key ) . ']" value="1"' . checked( $send, true, false ) . '> <span class="screen-reader-text">' . esc_html( sprintf( __( 'Send from %s', 'mailweb' ), $addr ) ) . '</span></label></td>';
			/* translators: %s: mailbox address. */
			echo '<td><label><input type="radio" name="default_from" value="' . esc_attr( $key ) . '"' . checked( $def, $key, false ) . '> <span class="screen-reader-text">' . esc_html( sprintf( __( 'Default From: %s', 'mailweb' ), $addr ) ) . '</span></label></td></tr>';
		}
		echo '</tbody></table>';
		$lines = array();
		foreach ( mailweb_aliases() as $a => $m ) {
			$lines[] = $a . ' = ' . $m;
		}
		echo '<table class="form-table"><tr><th scope="row"><label for="mailweb-aliases">' . esc_html__( 'Aliases', 'mailweb' ) . '</label></th><td>';
		echo '<textarea class="large-text code" rows="4" id="mailweb-aliases" name="aliases">' . esc_textarea( implode( "\n", $lines ) ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'One per line: alias = mailbox (as in hPanel → Emails → Email Alias). The Mail API does not list aliases, so keep this in step when one is added or removed there.', 'mailweb' ) . '</p></td></tr></table>';
		submit_button( __( 'Save mailboxes and aliases', 'mailweb' ) );
		echo '</form>';
	},
	5
);

add_action(
	'admin_post_mailweb_alias',
	function () {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'mailweb_alias' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mailweb' ), 403 );
		}
		$map = array();
		foreach ( preg_split( '/\R/', sanitize_textarea_field( wp_unslash( $_POST['aliases'] ?? '' ) ) ) as $line ) {
			$p = array_map( 'trim', explode( '=', strtolower( $line ), 2 ) );
			if ( 2 === count( $p ) && is_email( $p[0] ) && is_email( $p[1] ) ) {
				$map[ $p[0] ] = $p[1];
			}
		}
		update_option( 'mailweb_aliases', $map, false );
		$send = array_map( 'absint', (array) wp_unslash( $_POST['send'] ?? array() ) );
		$set  = array();
		foreach ( array_keys( mailweb_all_boxes() ) as $key ) {
			$set[ $key ] = array( 'send' => ! empty( $send[ $key ] ) );
		}
		update_option( 'mailweb_mailboxes', $set, false );
		update_option( 'mailweb_default_from', sanitize_key( wp_unslash( $_POST['default_from'] ?? '' ) ), false );
		wp_safe_redirect( mailweb_settings_url( 'incoming', array( 'mailweb_alias' => 1 ), 'addresses' ) );
		exit;
	}
);
