<?php
/**
 * Mail → Settings: every mail setting in one place under the Mail menu.
 *
 * - Incoming: the Mail API token, webhook secrets, mailbox IDs, mailboxes and aliases, profiles, FluentCRM (mail-in.php,
 *   mailboxes.php, profiles.php, crm-sync.php).
 * - Sending: the site's SMTP mailbox and the daily sending limit and queue (smtp.php, mail-cap.php).
 *
 * @package mailweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Mail → Settings (its own menu item since 25 Oct 2026). */
function mailweb_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'mailweb' ), 403 );
	}
	$sub = 'sending' === sanitize_key( $_GET['sub'] ?? '' ) ? 'sending' : 'incoming'; // phpcs:ignore WordPress.Security.NonceVerification
	echo '<div class="wrap" id="mailweb-settings"><h1>' . esc_html__( 'Mail settings', 'mailweb' ) . '</h1>';
	mailweb_notice();
	echo '<h2 class="nav-tab-wrapper">';
	echo '<a class="nav-tab' . ( 'incoming' === $sub ? ' nav-tab-active' : '' ) . '" href="' . esc_url( mailweb_settings_url() ) . '">' . esc_html__( 'Incoming mail, mailboxes, profiles', 'mailweb' ) . '</a>';
	if ( function_exists( 'mailweb_smtp_screen' ) ) {
		echo '<a class="nav-tab' . ( 'sending' === $sub ? ' nav-tab-active' : '' ) . '" href="' . esc_url( mailweb_settings_url( 'sending' ) ) . '">' . esc_html__( 'Site sending (SMTP, daily limit)', 'mailweb' ) . '</a>';
	}
	echo '</h2>';
	if ( 'sending' === $sub && function_exists( 'mailweb_smtp_screen' ) ) {
		mailweb_smtp_screen();
	} elseif ( function_exists( 'mailweb_in_screen' ) ) {
		mailweb_in_screen();
	}
	echo '</div>';
}
