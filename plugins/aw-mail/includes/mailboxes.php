<?php
/**
 * Every Hostinger mailbox and alias of animalwrites.in (owner, 18 Oct 2026).
 *
 * - Mailboxes: every mailbox the Mail API token can see (vedanshu@, support@, noreply@), read from GET /api/v1/me and
 *   remembered (mail-in.php, aw_mail_box_ids). The Mail menu, badge and widget cover all of them. Filing into the Inbox
 *   (webhook, poll, import) stays with vedanshu@ and support@: noreply@ only sends.
 * - Aliases: the Mail API does not list them, so the map is kept here (Settings → Incoming mail), first filled from
 *   hPanel → Emails → Email Alias: contact@ delivers to vedanshu@. No forwarders or catch-all exist (18 Oct 2026).
 *
 * @package aw-mail
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Aliases as hPanel lists them on 18 Oct 2026 (alias => mailbox). */
const AW_MAIL_ALIASES_DEFAULT = array( 'contact@animalwrites.in' => 'vedanshu@animalwrites.in' );

/** Every mailbox: key (local part) => address. The filing mailboxes first, then the rest the token sees. */
function aw_mail_all_boxes() {
	if ( function_exists( 'aw_mail_box_ids' ) ) {
		aw_mail_box_ids(); // fills aw_mail_box_addresses once
	}
	$all = AW_MAIL_BOXES + (array) get_option( 'aw_mail_box_addresses', array() );
	return array_map( 'strtolower', array_filter( $all, 'is_email' ) );
}

/**
 * Mailboxes the inbox may send from (owner, 20 Oct 2026): never vedanshu@ (personal) or noreply@ (site notices only);
 * support@ (and contact@ once it is a mailbox) answer people.
 */
function aw_mail_send_boxes() {
	$all  = aw_mail_all_boxes();
	$send = array_diff_key( $all, array_flip( array( 'vedanshu', 'noreply' ) ) );
	return $send ? $send : $all;
}

/** Alias map: alias address => mailbox address. */
function aw_mail_aliases() {
	$map = get_option( 'aw_mail_aliases', false );
	return is_array( $map ) ? $map : AW_MAIL_ALIASES_DEFAULT;
}

/** The aliases that deliver to one mailbox. */
function aw_mail_aliases_of( $mailbox ) {
	return array_keys( array_filter( aw_mail_aliases(), fn( $m ) => strtolower( $mailbox ) === $m ) );
}

/** The mailbox an address lands in (itself for a mailbox, its mailbox for an alias), or ''. */
function aw_mail_mailbox_of( $address ) {
	$a = strtolower( trim( (string) $address ) );
	if ( in_array( $a, aw_mail_all_boxes(), true ) ) {
		return $a;
	}
	return (string) ( aw_mail_aliases()[ $a ] ?? '' );
}

/* ---------- Settings → Incoming mail: mailboxes and aliases ---------- */

add_action(
	'aw_mail_settings_after',
	function () {
		$saved = isset( $_GET['aw_alias'] ); // phpcs:ignore WordPress.Security.NonceVerification
		echo '<h2 id="addresses">Mailboxes and aliases</h2>';
		if ( $saved ) {
			echo '<div class="notice notice-success inline"><p>Aliases saved.</p></div>';
		}
		echo '<table class="widefat striped" style="max-width:40rem"><thead><tr><th scope="col">Mailbox</th><th scope="col">Aliases</th><th scope="col">Filed into the Inbox</th></tr></thead><tbody>';
		foreach ( aw_mail_all_boxes() as $key => $addr ) {
			$alias = aw_mail_aliases_of( $addr );
			echo '<tr><td>' . esc_html( $addr ) . '</td><td>' . esc_html( $alias ? implode( ', ', $alias ) : '–' ) . '</td><td>' . ( isset( AW_MAIL_BOXES[ $key ] ) ? 'Yes' : 'No (sending only)' ) . '</td></tr>';
		}
		echo '</tbody></table>';
		$lines = array();
		foreach ( aw_mail_aliases() as $a => $m ) {
			$lines[] = $a . ' = ' . $m;
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'aw_mail_alias' );
		echo '<input type="hidden" name="action" value="aw_mail_alias"><table class="form-table"><tr><th scope="row"><label for="aw-mail-aliases">Aliases</label></th><td>';
		echo '<textarea class="large-text code" rows="4" id="aw-mail-aliases" name="aliases">' . esc_textarea( implode( "\n", $lines ) ) . '</textarea>';
		echo '<p class="description">One per line: alias = mailbox (as in hPanel → Emails → Email Alias). The Mail API does not list aliases, so keep this in step when one is added or removed there.</p></td></tr></table>';
		submit_button( 'Save aliases' );
		echo '</form>';
	},
	5
);

add_action(
	'admin_post_aw_mail_alias',
	function () {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'aw_mail_alias' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$map = array();
		foreach ( preg_split( '/\R/', sanitize_textarea_field( wp_unslash( $_POST['aliases'] ?? '' ) ) ) as $line ) {
			$p = array_map( 'trim', explode( '=', strtolower( $line ), 2 ) );
			if ( 2 === count( $p ) && is_email( $p[0] ) && is_email( $p[1] ) ) {
				$map[ $p[0] ] = $p[1];
			}
		}
		update_option( 'aw_mail_aliases', $map, false );
		wp_safe_redirect( admin_url( 'options-general.php?page=aw-mail-in&aw_alias=1#addresses' ) );
		exit;
	}
);
