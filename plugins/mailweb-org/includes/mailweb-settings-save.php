<?php
/**
 * How each MailWeb setting is cleaned before it is stored, shared by the Site sending forms (admin-post handlers in
 * mail-cap.php, smtp.php) and the Settings screen's routes (mailweb-rest.php), so the two can never store a value
 * differently.
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

/* ---------- Writes that come from outside these screens (Kits → Tools import and undo) ---------- */

/**
 * Mailbox keys a stored value may name: today's mailboxes, or, when none are known yet, the keys given.
 *
 * @param array $given Keys in the value.
 */
function mailweb_option_box_keys( array $given ): array {
	$keys = array_keys( mailweb_all_boxes() );
	return $keys ? $keys : array_map( 'sanitize_key', $given );
}

/**
 * The value of a MailWeb option as it may be stored, whoever writes it. It is the option's register_setting() sanitize
 * callback (mailweb_registered_settings()), so it runs on every update_option(): the screens' routes, the Site sending
 * forms, a Kits → Tools import and /wp/v2/settings all store the same shape. Values already cleaned come back unchanged.
 *
 * @param mixed  $value  The value.
 * @param string $option Option name.
 * @return mixed
 */
function mailweb_option_clean( $value, $option ) {
	switch ( $option ) {
		case 'mailweb_cap':
			return mailweb_cap_clean( (array) $value );
		case 'mailweb_default_from':
			return sanitize_key( is_scalar( $value ) ? (string) $value : '' );
		case 'mailweb_aliases':
			$lines = array();
			foreach ( (array) $value as $alias => $box ) {
				if ( is_string( $alias ) && is_string( $box ) ) {
					$lines[ $alias ] = $box;
				}
			}
			return mailweb_aliases_clean( mailweb_aliases_text( $lines ) );
		case 'mailweb_profiles':
			$out = array();
			foreach ( (array) $value as $addr => $p ) {
				if ( is_email( (string) $addr ) && is_array( $p ) ) {
					$out[ (string) $addr ] = mailweb_profile_clean( $p );
				}
			}
			return $out;
		case 'mailweb_mailboxes':
			$out = array();
			$in  = (array) $value;
			foreach ( mailweb_option_box_keys( array_keys( $in ) ) as $key ) {
				if ( isset( $in[ $key ] ) ) {
					$out[ $key ] = array( 'send' => is_array( $in[ $key ] ) ? ! empty( $in[ $key ]['send'] ) : ! empty( $in[ $key ] ) );
				}
			}
			return $out;
		case 'mailweb_crm':
			$in  = (array) $value;
			$out = mailweb_crm_clean( $in, mailweb_option_box_keys( (array) ( $in['boxes'] ?? array() ) ) );
			if ( empty( $in['boxes'] ) ) {
				$out['boxes'] = array(); // An empty list means every mailbox.
			}
			return $out;
	}
	return $value;
}

/* ---------- The options, registered with the Settings API ---------- */

const MAILWEB_DELETE_DATA = 'mailweb_delete_data'; // 1: uninstall.php deletes MailWeb's own data. Default 0: it keeps everything.

/**
 * A sanitize callback for one option: mailweb_option_clean() with the option's name (register_setting() passes the
 * value only).
 *
 * @param string $option Option name.
 */
function mailweb_option_cleaner( string $option ): callable {
	return fn( $value ) => mailweb_option_clean( $value, $option );
}

/**
 * Every option the MailWeb Settings screen saves, as register_setting() arguments. The names and stored shapes are the
 * ones used before (no migration); the default is what the code reads when the option is missing. The options that
 * hold a password, token or webhook secret (stored encrypted) are registered without show_in_rest: /wp/v2/settings
 * never shows them, as the screen never shows them.
 */
function mailweb_registered_settings(): array {
	$secret = array(
		'type'              => 'string',
		'default'           => '',
		'sanitize_callback' => fn( $v ) => is_scalar( $v ) ? (string) $v : '', // already encrypted by mailweb_save_setting()
		'show_in_rest'      => false,
	);
	$set    = array(
		'mailweb_cap'          => array(
			'type'              => 'object',
			'description'       => 'Emails per 24 hours, and how many are kept back from campaigns.',
			'default'           => array(
				'budget'  => 90,
				'reserve' => 10,
			),
			'sanitize_callback' => mailweb_option_cleaner( 'mailweb_cap' ),
			'show_in_rest'      => array(
				'schema' => array(
					'type'       => 'object',
					'properties' => array(
						'budget'  => array( 'type' => 'integer' ),
						'reserve' => array( 'type' => 'integer' ),
					),
				),
			),
		),
		'mailweb_mailboxes'    => array(
			'type'              => 'object',
			'description'       => 'Send from it, per mailbox key.',
			'default'           => array(),
			'sanitize_callback' => mailweb_option_cleaner( 'mailweb_mailboxes' ),
			'show_in_rest'      => array(
				'schema' => array(
					'type'                 => 'object',
					'additionalProperties' => array(
						'type'       => 'object',
						'properties' => array( 'send' => array( 'type' => 'boolean' ) ),
					),
				),
			),
		),
		'mailweb_default_from' => array(
			'type'              => 'string',
			'description'       => 'The default From mailbox key.',
			'default'           => '',
			'sanitize_callback' => mailweb_option_cleaner( 'mailweb_default_from' ),
			'show_in_rest'      => array( 'schema' => array( 'type' => 'string' ) ),
		),
		'mailweb_aliases'      => array(
			'type'              => 'object',
			'description'       => 'Alias address => mailbox address.',
			'default'           => array(),
			'sanitize_callback' => mailweb_option_cleaner( 'mailweb_aliases' ),
			'show_in_rest'      => array(
				'schema' => array(
					'type'                 => 'object',
					'additionalProperties' => array( 'type' => 'string' ),
				),
			),
		),
		'mailweb_profiles'     => array(
			'type'              => 'object',
			'description'       => 'Display name, signature and picture per address.',
			'default'           => array(),
			'sanitize_callback' => mailweb_option_cleaner( 'mailweb_profiles' ),
			'show_in_rest'      => array(
				'schema' => array(
					'type'                 => 'object',
					'additionalProperties' => array(
						'type'       => 'object',
						'properties' => array(
							'name'      => array( 'type' => 'string' ),
							'signature' => array( 'type' => 'string' ),
							'picture'   => array( 'type' => 'integer' ),
						),
					),
				),
			),
		),
		'mailweb_crm'          => array(
			'type'              => 'object',
			'description'       => 'The FluentCRM feed: on, tag, mailbox keys (empty: all).',
			'default'           => array(),
			'sanitize_callback' => mailweb_option_cleaner( 'mailweb_crm' ),
			'show_in_rest'      => array(
				'schema' => array(
					'type'       => 'object',
					'properties' => array(
						'on'    => array( 'type' => 'boolean' ),
						'tag'   => array( 'type' => 'string' ),
						'boxes' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
					),
				),
			),
		),
		'mailweb_smtp'         => array(
			'type'              => 'object',
			'description'       => 'Site sending (SMTP): user, name, host, port and the encrypted password.',
			'default'           => array(),
			'sanitize_callback' => fn( $v ) => mailweb_smtp_clean( (array) $v ) + array( 'pass' => is_scalar( ( (array) $v )['pass'] ?? '' ) ? (string) ( ( (array) $v )['pass'] ?? '' ) : '' ),
			'show_in_rest'      => false,
		),
		'mailweb_api_token'    => $secret,
		MAILWEB_DELETE_DATA    => array(
			'type'              => 'boolean',
			'description'       => 'Delete MailWeb\'s own data when the plugin is deleted.',
			'default'           => false,
			'sanitize_callback' => fn( $v ) => rest_sanitize_boolean( $v ) ? 1 : 0,
			'show_in_rest'      => array( 'schema' => array( 'type' => 'boolean' ) ),
		),
	);
	// One webhook secret per mailbox known so far (the stored list: no Mail API call here).
	foreach ( array_keys( (array) get_option( 'mailweb_box_addresses', array() ) ) as $key ) {
		$key = sanitize_key( (string) $key );
		if ( '' !== $key ) {
			$set[ 'mailweb_secret_' . $key ] = $secret;
		}
	}
	return $set;
}

add_action(
	'init',
	function () {
		foreach ( mailweb_registered_settings() as $name => $args ) {
			register_setting( 'mailweb', $name, $args );
		}
	}
);

/** Whether MailWeb's data goes when the plugin is deleted (Settings → Sending). */
function mailweb_delete_data_on(): bool {
	return (bool) get_option( MAILWEB_DELETE_DATA, 0 );
}

/**
 * Save "Delete this plugin's data when it is deleted". Nothing is written when it is already so.
 *
 * @param bool $on On.
 * @return bool Whether the option was written.
 */
function mailweb_delete_data_save( bool $on ): bool {
	if ( mailweb_delete_data_on() === $on ) {
		return false;
	}
	update_option( MAILWEB_DELETE_DATA, $on ? 1 : 0, false );
	return true;
}

/**
 * "Reset this section": the group's registered settings back to their defaults; nothing is written where they already
 * are. Sending: the daily limit and "Delete this plugin's data". Mailboxes: "Send from it" on for every mailbox and no
 * chosen default From; the alias lines are a hand-made copy of hPanel, not a setting with a default, so they stay.
 * Profiles have no reset (the names and signatures are the owner's own text).
 *
 * @param string $group sending or mailboxes.
 * @return bool Whether anything was written.
 */
function mailweb_settings_reset( string $group ): bool {
	$d     = mailweb_registered_settings();
	$saved = false;
	if ( 'sending' === $group ) {
		if ( mailweb_cap_current() !== $d['mailweb_cap']['default'] ) {
			update_option( 'mailweb_cap', $d['mailweb_cap']['default'], false );
			$saved = true;
		}
		$saved = mailweb_delete_data_save( (bool) $d[ MAILWEB_DELETE_DATA ]['default'] ) || $saved;
	} elseif ( 'mailboxes' === $group ) {
		foreach ( array( 'mailweb_mailboxes', 'mailweb_default_from' ) as $option ) {
			if ( get_option( $option, $d[ $option ]['default'] ) !== $d[ $option ]['default'] ) {
				update_option( $option, $d[ $option ]['default'], false );
				$saved = true;
			}
		}
	}
	return $saved;
}
