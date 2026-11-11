<?php
/**
 * How each MailWeb setting is cleaned before it is stored, shared by the current settings forms (admin-post handlers
 * in mail-cap.php, smtp.php, mailboxes.php, profiles.php, crm-sync.php) and the new Settings screen's routes
 * (mailweb-rest.php), so the two can never store a value differently.
 *
 * The routes save only what changed: the fields sent are put over today's values, both are cleaned, and the option is
 * written only when the two differ. An unchanged Save writes nothing, even when the option was never stored.
 *
 * Nothing here reads a secret back out. Passwords and keys are only ever written.
 *
 * @package mailweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Store a cleaned value only when it differs from today's.
 *
 * @param string   $option  Option name.
 * @param array    $current Today's values, as the screen shows them.
 * @param array    $sent    The fields the screen sent (others keep today's value).
 * @param callable $clean   The option's clean-up: values => what is stored.
 * @return bool Whether the option was written.
 */
function mailweb_save_changed( string $option, array $current, array $sent, callable $clean ): bool {
	$next = array_merge( $current, array_intersect_key( $sent, $current ) );
	$new  = $clean( $next );
	if ( $new === $clean( $current ) ) {
		return false;
	}
	update_option( $option, $new, false );
	return true;
}

/* ---------- Sending: the daily limit (mailweb_cap) ---------- */

/**
 * The limit as stored: budget 1–10,000, reserve a whole number (mailweb_cap_settings() keeps it below the budget when
 * it is read).
 *
 * @param array $in budget, reserve.
 */
function mailweb_cap_clean( array $in ): array {
	return array(
		'budget'  => min( 10000, max( 1, absint( $in['budget'] ?? 90 ) ) ),
		'reserve' => absint( $in['reserve'] ?? 10 ),
	);
}

/** Today's limit as the form shows it (the stored values, or the defaults). */
function mailweb_cap_current(): array {
	$s = array_merge(
		array(
			'budget'  => 90,
			'reserve' => 10,
		),
		(array) get_option( 'mailweb_cap', array() )
	);
	return array(
		'budget'  => (int) $s['budget'],
		'reserve' => (int) $s['reserve'],
	);
}

/* ---------- Site sending: SMTP (mailweb_smtp) ---------- */

/**
 * The SMTP fields as stored, the password left out (it is added by the caller, encrypted, or kept).
 *
 * @param array $in user, name, host, port.
 */
function mailweb_smtp_clean( array $in ): array {
	return array(
		'user' => sanitize_email( (string) ( $in['user'] ?? '' ) ),
		'name' => sanitize_text_field( (string) ( $in['name'] ?? '' ) ),
		'host' => sanitize_text_field( (string) ( $in['host'] ?? '' ) ),
		'port' => absint( $in['port'] ?? 0 ) ? absint( $in['port'] ) : '',
	);
}

/* ---------- Mailboxes and aliases ---------- */

/**
 * Alias lines ("alias = mailbox", one a line) as the stored map. Lines that are not two addresses are left out.
 *
 * @param string $text The lines.
 * @return array alias => mailbox
 */
function mailweb_aliases_clean( string $text ): array {
	$map = array();
	foreach ( preg_split( '/\R/', sanitize_textarea_field( $text ) ) as $line ) {
		$p = array_map( 'trim', explode( '=', strtolower( $line ), 2 ) );
		if ( 2 === count( $p ) && is_email( $p[0] ) && is_email( $p[1] ) ) {
			$map[ $p[0] ] = $p[1];
		}
	}
	return $map;
}

/**
 * The alias map as the lines the form shows.
 *
 * @param array $map alias => mailbox.
 */
function mailweb_aliases_text( array $map ): string {
	$lines = array();
	foreach ( $map as $a => $m ) {
		$lines[] = $a . ' = ' . $m;
	}
	return implode( "\n", $lines );
}

/**
 * "Send from it" per mailbox, as stored: every known mailbox, ticked or not.
 *
 * @param array $send key => truthy when ticked.
 * @param array $keys Every mailbox key.
 * @return array key => [ 'send' => bool ]
 */
function mailweb_mailboxes_clean( array $send, array $keys ): array {
	$set = array();
	foreach ( $keys as $key ) {
		$set[ $key ] = array( 'send' => ! empty( $send[ $key ] ) );
	}
	return $set;
}

/* ---------- Profiles ---------- */

/**
 * One address's profile as stored.
 *
 * @param array $v name, signature (HTML), picture (attachment ID).
 */
function mailweb_profile_clean( array $v ): array {
	return array(
		'name'      => sanitize_text_field( (string) ( $v['name'] ?? '' ) ),
		'signature' => wp_kses_post( (string) ( $v['signature'] ?? '' ) ),
		'picture'   => absint( $v['picture'] ?? 0 ),
	);
}

/* ---------- FluentCRM feed (mailweb_crm) ---------- */

/**
 * The feed's settings as stored. No mailbox ticked is kept as [ '-' ] (no mailbox), since an empty list means all.
 *
 * @param array $in   on, tag, boxes.
 * @param array $keys Every mailbox key.
 */
function mailweb_crm_clean( array $in, array $keys ): array {
	$boxes = array_values( array_intersect( $keys, array_map( 'sanitize_key', (array) ( $in['boxes'] ?? array() ) ) ) );
	return array(
		'on'    => ! empty( $in['on'] ),
		'tag'   => sanitize_text_field( (string) ( $in['tag'] ?? '' ) ),
		'boxes' => $boxes ? $boxes : array( '-' ),
	);
}
