<?php
/**
 * Plugin Name:       Wug Mail
 * Plugin URI:        https://animalwrites.in/
 * Description:       Incoming mail for vedanshu@ and support@ (Hostinger Mail): the webhook POST /wp-json/aw/v1/mail-in that files each email in Inbox → Contact messages, Settings → Incoming mail, a Mail menu with the unread count and the latest messages, a Mail dashboard widget, and a Telegram reminder to the owner. No mail client: replies are written in Hostinger webmail.
 * Version:           1.0.0
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

define( 'AW_MAIL_VERSION', '1.0.0' );
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
		require_once AW_MAIL_DIR . 'includes/overview.php';
		require_once AW_MAIL_DIR . 'includes/telegram.php';
	},
	0
);
