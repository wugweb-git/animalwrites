<?php
/**
 * Mail → Overview and the Plugins screen links (owner, 25 Oct 2026: our plugins work like any marketplace plugin).
 *
 * Mail menu: Inbox (inbox.php), Overview (this file), Settings (settings-tab.php). Overview lists every feature with
 * where to find it and its state from the plugin's own options; it makes no API call. Core WordPress only.
 *
 * @package mailweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The Overview item is registered with the menu (overview.php), between Inbox and Settings.

// Plugins screen: Inbox, Overview and Settings under the plugin's name.
add_filter(
	'plugin_action_links_' . plugin_basename( MAILWEB_DIR . 'mailweb.php' ),
	function ( $links ) {
		$own = array(
			'<a href="' . esc_url( admin_url( 'admin.php?page=mailweb' ) ) . '">' . esc_html__( 'Inbox', 'mailweb' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=mailweb-overview' ) ) . '">' . esc_html__( 'Overview', 'mailweb' ) . '</a>',
		);
		if ( current_user_can( 'manage_options' ) ) {
			$own[] = '<a href="' . esc_url( mailweb_settings_url() ) . '">' . esc_html__( 'Settings', 'mailweb' ) . '</a>';
		}
		return array_merge( $own, (array) $links );
	}
);

/** A status cell: dashicon and text. */
function mailweb_home_status( $ok, $text ) {
	return '<span class="dashicons dashicons-' . ( $ok ? 'yes' : 'warning' ) . '" aria-hidden="true"></span> ' . esc_html( $text );
}

/** Mail → Overview. */
function mailweb_home_page() {
	if ( ! current_user_can( MAILWEB_CAP ) ) {
		wp_die( esc_html__( 'Not allowed.', 'mailweb' ), 403 );
	}
	$admin   = current_user_can( 'manage_options' );
	$set     = fn( $label, $sub = 'incoming', $anchor = '' ) => $admin ? '<a href="' . esc_url( mailweb_settings_url( $sub, array(), $anchor ) ) . '">' . esc_html( $label ) . '</a>' : esc_html__( 'Administrators', 'mailweb' );
	$boxes   = array_filter( (array) get_option( 'mailweb_box_addresses', array() ), 'is_email' );
	$token   = '' !== mailweb_setting( 'api_token' );
	$unread  = function_exists( 'mailweb_overview_unread' ) ? (int) mailweb_overview_unread() : 0;
	$forms   = function_exists( 'mailweb_forms_unread' ) ? (int) mailweb_forms_unread() : 0;
	$smtp    = function_exists( 'mailweb_smtp_settings' ) ? mailweb_smtp_settings() : array();
	$cap     = function_exists( 'mailweb_cap_settings' ) ? mailweb_cap_settings() : array( 'budget' => 0 );
	$sent    = function_exists( 'mailweb_sent_24h' ) ? (int) mailweb_sent_24h() : 0;
	$queued  = function_exists( 'mailweb_queued' ) ? (int) mailweb_queued() : 0;
	$pause   = function_exists( 'mailweb_paused' ) ? mailweb_paused() : false;
	$crm     = function_exists( 'mailweb_crm_on' ) && mailweb_crm_on();
	$aliases = function_exists( 'mailweb_aliases' ) ? count( mailweb_aliases() ) : 0;

	$rows = array(
		array(
			__( 'Inbox', 'mailweb' ),
			__( 'Read, reply, reply all, forward and write from every mailbox; folders, search, archive, spam, trash, attachments. Website forms appear as their own folder.', 'mailweb' ),
			'<a href="' . esc_url( admin_url( 'admin.php?page=mailweb' ) ) . '">' . esc_html__( 'Mail → Inbox', 'mailweb' ) . '</a>',
			esc_html( sprintf( /* translators: 1: unread emails, 2: unread form messages */ __( '%1$d unread emails, %2$d unread form messages.', 'mailweb' ), $unread, $forms ) ),
		),
		array(
			__( 'Mailboxes', 'mailweb' ),
			__( 'Mailboxes found from the Mail API token; per mailbox "send from it", the default From, aliases.', 'mailweb' ),
			$set( __( 'Settings → Incoming', 'mailweb' ), 'incoming', 'addresses' ),
			$token ? mailweb_home_status( (bool) $boxes, sprintf( /* translators: 1: mailboxes, 2: aliases */ __( '%1$d mailboxes, %2$d aliases.', 'mailweb' ), count( $boxes ), $aliases ) ) : mailweb_home_status( false, __( 'No Mail API token.', 'mailweb' ) ),
		),
		array(
			__( 'Incoming mail (webhook)', 'mailweb' ),
			__( 'Hostinger calls the site when mail arrives; per-mailbox secrets; counts refresh at once.', 'mailweb' ),
			$set( __( 'Settings → Incoming', 'mailweb' ) ),
			esc_html( rest_url( 'mailweb/v1/mail-in' ) ),
		),
		array(
			__( 'Profiles and signatures', 'mailweb' ),
			__( 'Per-mailbox display name, signature and picture; site emails laid out with the signature.', 'mailweb' ),
			$set( __( 'Settings → Incoming → Profiles', 'mailweb' ), 'incoming', 'profiles' ),
			esc_html__( 'Automatic', 'mailweb' ),
		),
		array(
			__( 'Site sending (SMTP)', 'mailweb' ),
			__( 'Every email the site sends goes through one Hostinger mailbox; test email.', 'mailweb' ),
			$set( __( 'Settings → Sending', 'mailweb' ), 'sending' ),
			mailweb_home_status( '' !== (string) ( $smtp['pass'] ?? '' ), sprintf( '%s:%s, %s', (string) ( $smtp['host'] ?? '' ), (string) ( $smtp['port'] ?? '' ), (string) ( $smtp['user'] ?? '' ) ) . ( '' === (string) ( $smtp['pass'] ?? '' ) ? ' — ' . __( 'no password set', 'mailweb' ) : '' ) ),
		),
		array(
			__( 'Daily limit and queue', 'mailweb' ),
			__( 'A daily sending budget with priorities (replies first, newsletters last), retries, and a pause when Hostinger refuses.', 'mailweb' ),
			$set( __( 'Settings → Sending → Daily limit', 'mailweb' ), 'sending', 'cap' ),
			mailweb_home_status( ! $pause, sprintf( /* translators: 1: sent, 2: budget, 3: queued */ __( '%1$d of %2$d sent in 24 h, %3$d queued.', 'mailweb' ), $sent, (int) $cap['budget'], $queued ) . ( $pause ? ' ' . __( 'Sending is paused.', 'mailweb' ) : '' ) ),
		),
		array(
			__( 'FluentCRM feed', 'mailweb' ),
			__( 'Incoming email and replies noted on the sender\'s contact (transactional), every 5 minutes and on each webhook.', 'mailweb' ),
			$set( __( 'Settings → Incoming → FluentCRM', 'mailweb' ), 'incoming', 'fluentcrm' ),
			mailweb_home_status( $crm, $crm ? __( 'On.', 'mailweb' ) : __( 'Off.', 'mailweb' ) ),
		),
		array(
			__( 'Dashboard widget', 'mailweb' ),
			__( 'Unread counts and the last 5 messages per mailbox.', 'mailweb' ),
			'<a href="' . esc_url( admin_url( 'index.php' ) ) . '">' . esc_html__( 'Dashboard', 'mailweb' ) . '</a>',
			esc_html__( 'Automatic', 'mailweb' ),
		),
		array(
			__( 'Command', 'mailweb' ),
			__( 'Run the FluentCRM feed for every mailbox.', 'mailweb' ),
			'<code>wp mailweb crm-sync --all</code>',
			'',
		),
	);
	$cells = array();
	foreach ( $rows as $r ) {
		$cells[] = array(
			'feature' => '<strong>' . esc_html( $r[0] ) . '</strong>',
			'what'    => esc_html( $r[1] ),
			'where'   => $r[2],
			'status'  => $r[3],
		);
	}
	echo '<div class="wrap"><h1>' . esc_html__( 'Mail', 'mailweb' ) . '</h1>';
	echo '<p>' . esc_html__( 'Hostinger Mail inside WordPress, and the site\'s own sending. Secrets are encrypted; nothing is deleted on uninstall.', 'mailweb' ) . '</p>';
	mailweb_simple_table( 'mailweb-overview', array( 'feature' => __( 'Feature', 'mailweb' ), 'what' => __( 'What it does', 'mailweb' ), 'where' => __( 'Where', 'mailweb' ), 'status' => __( 'On this site', 'mailweb' ) ), $cells, array( 'primary' => 'feature' ) );
	echo '</div>';
}
