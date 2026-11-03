<?php
/**
 * Plugin Name: AW Mail Guard
 * Description: Keeps site email on the SMTP mailbox while mailweb is missing for a few seconds during a deploy, and holds FluentCRM campaigns until mailweb is back. Install once from Plugins, Add New, Upload; it is not part of the deployed folder.
 * Version: 1.0.0
 * Requires PHP: 8.0
 * Author: Wugweb
 * License: GPL-2.0-or-later
 *
 * Why (3 Nov 2026): Hostinger replaces the deployed folder (aw_deploy) on each pull, and the plugins in wp-content/plugins
 * are links into it. For a few seconds mailweb is not loaded; a request that starts then sends through PHP mail(),
 * which this host refuses ("Could not instantiate mail function": 33 campaign emails failed, 11 left unsigned).
 * This file lives in its own folder in wp-content/plugins, so it is there during a deploy. It does nothing while
 * mailweb is loaded.
 *
 * @package aw-mail-guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** True when mailweb's sending code is loaded in this request. */
function aw_mail_guard_mailweb_loaded() {
	return function_exists( 'mailweb_smtp_settings' );
}

/** The mailbox mailweb uses (same option, same encryption), read without mailweb. Empty password when unreadable. */
function aw_mail_guard_smtp() {
	$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$s    = array_merge(
		array(
			'host' => 'smtp.hostinger.com',
			'port' => 465,
			'user' => 'noreply@' . $host,
			'name' => get_bloginfo( 'name' ),
			'pass' => '',
		),
		array_filter( (array) get_option( 'mailweb_smtp', array() ), fn( $v ) => '' !== $v )
	);
	$pass = (string) $s['pass'];
	if ( str_starts_with( $pass, 'mwenc1:' ) ) {
		$raw  = base64_decode( substr( $pass, 7 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- stored ciphertext
		$pass = '';
		if ( false !== $raw && strlen( $raw ) >= 29 && function_exists( 'openssl_decrypt' ) ) {
			$key  = hash( 'sha256', wp_salt( 'auth' ) . '|mailweb', true );
			$dec  = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ) );
			$pass = false === $dec ? '' : $dec;
		}
	}
	$env = getenv( 'MAILWEB_SMTP_PASSWORD' );
	if ( $env ) {
		$pass = (string) $env;
	} elseif ( defined( 'MAILWEB_SMTP_PASSWORD' ) && MAILWEB_SMTP_PASSWORD ) {
		$pass = (string) MAILWEB_SMTP_PASSWORD;
	}
	$s['pass'] = $pass;
	return $s;
}

// FluentCRM: no campaign batch starts while mailweb is missing (its daily limit and queue live in mailweb).
add_filter( 'fluent_crm/disable_email_processing', fn( $off ) => $off || ! aw_mail_guard_mailweb_loaded(), 99 );

// Any other email in that window: sent through the same mailbox, never through PHP mail().
add_action(
	'phpmailer_init',
	function ( $mailer ) {
		if ( aw_mail_guard_mailweb_loaded() ) {
			return;
		}
		$s = aw_mail_guard_smtp();
		if ( '' === $s['pass'] ) {
			return; // stopped in pre_wp_mail below
		}
		$mailer->isSMTP();
		$mailer->Host       = $s['host']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$mailer->Port       = (int) $s['port']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$mailer->SMTPAuth   = true; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$mailer->SMTPSecure = 465 === (int) $s['port'] ? 'ssl' : 'tls'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$mailer->Username   = $s['user']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$mailer->Password   = $s['pass']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$mailer->setFrom( $s['user'], $s['name'], false );
	},
	PHP_INT_MAX
);

// Without mailweb and without a readable password, refuse rather than hand the email to PHP mail().
add_filter(
	'pre_wp_mail',
	function ( $ret ) {
		if ( null !== $ret || aw_mail_guard_mailweb_loaded() || '' !== aw_mail_guard_smtp()['pass'] ) {
			return $ret;
		}
		do_action( 'wp_mail_failed', new WP_Error( 'aw_mail_guard', 'Mail held back: the site mailbox is not available (deploy in progress).' ) );
		return false;
	},
	1
);
