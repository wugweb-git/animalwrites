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

/** The domains of our own mailboxes and aliases. */
function mailweb_own_domains() {
	$out = array();
	foreach ( array_merge( array_values( mailweb_all_boxes() ), array_keys( mailweb_aliases() ) ) as $a ) {
		$out[] = substr( (string) strrchr( $a, '@' ), 1 );
	}
	return array_values( array_unique( array_filter( $out ) ) );
}
