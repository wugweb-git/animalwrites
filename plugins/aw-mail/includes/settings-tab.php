<?php
/**
 * Mail → Settings (owner, 18 Oct 2026): every mail setting in one place under the Mail menu.
 *
 * - Incoming: the webhook secrets, Mail API token, mailbox IDs, mailboxes and aliases, profiles (mail-in.php,
 *   mailboxes.php, profiles.php) — formerly Settings → Incoming mail.
 * - Sending: the site's own mailbox (SMTP, Kits Core) and the daily sending limit and queue — formerly Settings → Mail.
 * The old Settings pages are hidden from the menu; their addresses (and the redirects their forms make) open the
 * matching tab here, with the same notices.
 * Also: Inbox → Contact messages opens Mail → Website forms (the merged inbox, inbox.php).
 *
 * @package aw-mail
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'aw_mail_settings_tab',
	function () {
		$sub  = 'sending' === sanitize_key( $_GET['sub'] ?? '' ) ? 'sending' : 'incoming'; // phpcs:ignore WordPress.Security.NonceVerification
		$base = admin_url( 'admin.php?page=aw-mail&tab=settings' );
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=aw-mail' ) ) . '">&larr; Mail</a></p><h2 class="nav-tab-wrapper">';
		echo '<a class="nav-tab' . ( 'incoming' === $sub ? ' nav-tab-active' : '' ) . '" href="' . esc_url( $base ) . '">Incoming mail, mailboxes, profiles</a>';
		if ( function_exists( 'kits_mail_screen' ) ) {
			echo '<a class="nav-tab' . ( 'sending' === $sub ? ' nav-tab-active' : '' ) . '" href="' . esc_url( $base . '&sub=sending' ) . '">Site sending (SMTP, daily limit)</a>';
		}
		echo '</h2>';
		if ( 'sending' === $sub && function_exists( 'kits_mail_screen' ) ) {
			kits_mail_screen();
		} elseif ( function_exists( 'aw_mail_in_screen' ) ) {
			aw_mail_in_screen();
		}
	}
);

// The old Settings pages: out of the menu, and their addresses open the tab (query kept, so notices still show).
add_action(
	'admin_menu',
	function () {
		remove_submenu_page( 'options-general.php', 'aw-mail-in' );
		remove_submenu_page( 'options-general.php', 'kits-mail' );
	},
	999
);
foreach ( array( 'aw-mail-in' => 'incoming', 'kits-mail' => 'sending' ) as $aw_mail_old => $aw_mail_sub ) {
	add_action(
		'load-settings_page_' . $aw_mail_old,
		function () use ( $aw_mail_sub ) {
			if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				return;
			}
			$keep = array_diff_key( wp_unslash( $_GET ), array( 'page' => 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
			wp_safe_redirect( add_query_arg( array_map( 'rawurlencode', array_merge( array( 'page' => 'aw-mail', 'tab' => 'settings', 'sub' => $aw_mail_sub ), array_map( 'strval', $keep ) ) ), admin_url( 'admin.php' ) ) );
			exit;
		}
	);
}
unset( $aw_mail_old, $aw_mail_sub );

// Inbox → Contact messages is now Mail → Website forms (one inbox). The list redirects; single items still open.
add_action(
	'admin_menu',
	function () {
		remove_submenu_page( 'aw-inbox', 'edit.php?post_type=aw_message' );
	},
	999
);
add_action(
	'load-edit.php',
	function () {
		if ( 'aw_message' === ( $_GET['post_type'] ?? '' ) && empty( $_GET['post_status'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_safe_redirect( admin_url( 'admin.php?page=aw-mail&folder=forms' ) );
			exit;
		}
	}
);
