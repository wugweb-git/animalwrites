<?php
/**
 * Plugin Name:       Wug Mail
 * Plugin URI:        https://animalwrites.in/
 * Description:       Mail for animalwrites.in in WordPress: one inbox for every Hostinger mailbox (vedanshu@ with contact@, support@, noreply@) and the website forms — read, reply, reply all, forward, new message, archive, move, spam, trash, read/unread, attachments — through the Hostinger Mail API, so webmail stays in step. Mail → Settings holds incoming mail, mailboxes, profiles and site sending. The webhook POST /wp-json/aw/v1/mail-in refreshes the counts.
 * Version:           2.2.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Wugweb
 * Author URI:        https://wugweb.com/
 * License:           GPL-2.0-or-later
 * Text Domain:       aw-mail
 *
 * @package aw-mail
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AW_MAIL_VERSION', '2.2.0' );
define( 'AW_MAIL_DIR', plugin_dir_path( __FILE__ ) );
define( 'AW_MAIL_WEBMAIL', 'https://mail.hostinger.com' );

// The 5-minute schedule the mailbox poll uses (also defined by animal-writes-core for its mail queue).
add_filter(
	'cron_schedules',
	function ( $s ) {
		$s['aw_5min'] = $s['aw_5min'] ?? array( 'interval' => 300, 'display' => 'Every 5 minutes' );
		return $s;
	}
);

/*
 * The webhook, Settings → Incoming mail and the import/poll moved here from animal-writes-core. Until the core
 * version without them is deployed, core still defines them: then only the Mail menu, widget and reminder load here,
 * so the webhook URL answers at every moment of the switch.
 */
add_action(
	'plugins_loaded',
	function () {
		if ( ! function_exists( 'aw_mail_in' ) ) {
			require_once AW_MAIL_DIR . 'includes/mail-in.php';
			if ( apply_filters( 'aw_mail_wp_replies', false ) ) { // replies happen in Hostinger webmail (owner, 18 Oct 2026)
				require_once AW_MAIL_DIR . 'includes/mail-reply.php';
			}
		}
		if ( ! function_exists( 'aw_mail_table' ) ) {
			require_once AW_MAIL_DIR . 'includes/mail-cap.php'; // site sending: daily limit and queue (from core, 18 Oct 2026)
		}
		require_once AW_MAIL_DIR . 'includes/mailboxes.php';
		require_once AW_MAIL_DIR . 'includes/profiles.php';
		require_once AW_MAIL_DIR . 'includes/inbox.php';
		require_once AW_MAIL_DIR . 'includes/settings-tab.php';
		require_once AW_MAIL_DIR . 'includes/overview.php';
	},
	0
);
