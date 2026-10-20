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

add_action(
	'mailweb_settings_tab',
	function () {
		$sub  = 'sending' === sanitize_key( $_GET['sub'] ?? '' ) ? 'sending' : 'incoming'; // phpcs:ignore WordPress.Security.NonceVerification
		$base = admin_url( 'admin.php?page=mailweb&tab=settings' );
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=mailweb' ) ) . '">&larr; ' . esc_html__( 'Mail', 'mailweb' ) . '</a></p><h2 class="nav-tab-wrapper">';
		echo '<a class="nav-tab' . ( 'incoming' === $sub ? ' nav-tab-active' : '' ) . '" href="' . esc_url( $base ) . '">' . esc_html__( 'Incoming mail, mailboxes, profiles', 'mailweb' ) . '</a>';
		if ( function_exists( 'mailweb_smtp_screen' ) ) {
			echo '<a class="nav-tab' . ( 'sending' === $sub ? ' nav-tab-active' : '' ) . '" href="' . esc_url( $base . '&sub=sending' ) . '">' . esc_html__( 'Site sending (SMTP, daily limit)', 'mailweb' ) . '</a>';
		}
		echo '</h2>';
		if ( 'sending' === $sub && function_exists( 'mailweb_smtp_screen' ) ) {
			mailweb_smtp_screen();
		} elseif ( function_exists( 'mailweb_in_screen' ) ) {
			mailweb_in_screen();
		}
	}
);
