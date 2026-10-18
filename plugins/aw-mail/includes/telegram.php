<?php
/**
 * Telegram reminder (owner, 18 Oct 2026). When a new email to vedanshu@ or support@ is filed (webhook, or the 5-minute
 * poll that backs it up), the owner gets one Telegram message:
 *   New mail to <mailbox> from <name> — <subject> — <first 100 characters>
 *   https://mail.hostinger.com
 * Only for mail from a person: not from Hostinger, not the site's own or other automatic mail (no-reply senders,
 * auto-replies, mailing lists, bounces), not from anyone on the block list (Settings → Incoming mail; known guest-post
 * pitch senders). Not for the one-time import, nor for mail older than 6 hours.
 * Bot token and chat ID come from wp-config: AW_TELEGRAM_BOT_TOKEN, AW_TELEGRAM_CHAT_ID. Nothing extra is stored and
 * no message text is logged.
 *
 * @package aw-mail
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Whether the bot token and chat ID are set in wp-config. */
function aw_mail_tg_ready() {
	return defined( 'AW_TELEGRAM_BOT_TOKEN' ) && AW_TELEGRAM_BOT_TOKEN && defined( 'AW_TELEGRAM_CHAT_ID' ) && AW_TELEGRAM_CHAT_ID;
}

/** The block list: addresses and domains, one per line, lower case. */
function aw_mail_tg_blocklist() {
	$lines = preg_split( '/[\s,]+/', strtolower( (string) get_option( 'aw_mail_tg_block', '' ) ) );
	return array_values( array_filter( array_map( fn( $l ) => ltrim( trim( $l ), '@' ), (array) $lines ) ) );
}

/**
 * Is this email from a person (not Hostinger, not automatic, not blocked)?
 *
 * @param string $email  Sender address.
 * @param string $source Raw message source ('' when not fetched).
 * @return bool
 */
function aw_mail_tg_is_person( $email, $source ) {
	$email = strtolower( trim( (string) $email ) );
	if ( ! is_email( $email ) ) {
		return false;
	}
	$domain = substr( strrchr( $email, '@' ), 1 );
	$local  = strstr( $email, '@', true );
	if ( preg_match( '/(^|\.)hostinger\.[a-z.]+$|(^|\.)hostinger(mail)?\.[a-z.]+$/', $domain ) ) {
		return false; // Hostinger
	}
	if ( 'animalwrites.in' === $domain || str_ends_with( $domain, '.animalwrites.in' ) || $domain === wp_parse_url( home_url(), PHP_URL_HOST ) ) {
		return false; // the site's own mail (notices, forms, WordPress)
	}
	if ( preg_match( '/^(wordpress|no-?reply|do-?not-?reply|donotreply|mailer-daemon|postmaster|bounces?|notifications?|notify|alerts?|newsletter|news|updates?|info-noreply)([+._-].*)?$/', $local ) ) {
		return false; // automatic senders
	}
	foreach ( aw_mail_tg_blocklist() as $b ) {
		if ( $b === $email || $b === $domain || ( ! str_contains( $b, '@' ) && str_ends_with( $domain, '.' . $b ) ) ) {
			return false; // block list
		}
	}
	if ( $source && function_exists( 'aw_mail_header' ) ) {
		$auto = strtolower( aw_mail_header( $source, 'Auto-Submitted' ) );
		$prec = strtolower( aw_mail_header( $source, 'Precedence' ) );
		if ( ( '' !== $auto && 'no' !== $auto ) || in_array( $prec, array( 'bulk', 'list', 'junk', 'auto_reply' ), true ) || '' !== aw_mail_header( $source, 'List-Unsubscribe' ) || '' !== aw_mail_header( $source, 'List-Id' ) ) {
			return false; // auto-replies and mailing lists
		}
	}
	return true;
}

/**
 * Send one message to the owner's Telegram chat. Returns true when Telegram accepted it.
 *
 * @param string $text Message.
 * @return bool
 */
function aw_mail_tg_send( $text ) {
	if ( ! aw_mail_tg_ready() ) {
		return false;
	}
	$r = wp_remote_post(
		'https://api.telegram.org/bot' . AW_TELEGRAM_BOT_TOKEN . '/sendMessage',
		array(
			'timeout' => 8,
			'body'    => array( 'chat_id' => AW_TELEGRAM_CHAT_ID, 'text' => $text, 'disable_web_page_preview' => 'true' ),
		)
	);
	$ok = ! is_wp_error( $r ) && 200 === (int) wp_remote_retrieve_response_code( $r );
	update_option( 'aw_mail_tg_last', array( 'at' => current_time( 'mysql' ), 'ok' => $ok, 'code' => is_wp_error( $r ) ? $r->get_error_code() : (int) wp_remote_retrieve_response_code( $r ) ), false ); // status only, never the text
	return $ok;
}

add_action(
	'aw_mail_received',
	function ( $id, $m ) {
		$sent = ! empty( $m['date'] ) ? strtotime( (string) $m['date'] ) : false;
		if ( ! aw_mail_tg_ready() || ( $sent && $sent < time() - 6 * HOUR_IN_SECONDS ) || ! aw_mail_tg_is_person( $m['email'] ?? '', $m['source'] ?? '' ) ) {
			return;
		}
		$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) ( $m['text'] ?? '' ) ) ) );
		$name = '' !== trim( (string) ( $m['name'] ?? '' ) ) ? trim( (string) $m['name'] ) : (string) $m['email'];
		$subj = '' !== trim( (string) ( $m['subject'] ?? '' ) ) ? (string) $m['subject'] : '(no subject)';
		aw_mail_tg_send( 'New mail to ' . $m['mailbox'] . ' from ' . $name . ' — ' . $subj . ' — ' . mb_substr( $text, 0, 100 ) . ( mb_strlen( $text ) > 100 ? '…' : '' ) . "\n" . AW_MAIL_WEBMAIL );
	},
	10,
	2
);

/* ---------- Settings → Incoming mail: reminder status and the block list ---------- */

add_action(
	'aw_mail_settings_after',
	function () {
		$last  = get_option( 'aw_mail_tg_last' );
		$saved = isset( $_GET['aw_tg'] ); // phpcs:ignore WordPress.Security.NonceVerification
		echo '<h2 id="telegram">Telegram reminder</h2>';
		if ( $saved ) {
			echo '<div class="notice notice-success inline"><p>Block list saved.</p></div>';
		}
		echo '<p>A new email from a person to vedanshu@ or support@ sends the owner a Telegram message with the sender, subject, the first 100 characters and a link to webmail. Mail from Hostinger, the site itself, automatic senders, mailing lists and the addresses below is skipped.</p>';
		echo '<p>Bot token and chat ID (wp-config: AW_TELEGRAM_BOT_TOKEN, AW_TELEGRAM_CHAT_ID): <strong>' . ( aw_mail_tg_ready() ? 'set' : 'not set — no reminders are sent' ) . '</strong>. Last reminder: <strong>' . esc_html( is_array( $last ) ? $last['at'] . ( $last['ok'] ? ' — delivered' : ' — Telegram refused it (' . $last['code'] . ')' ) : 'none yet' ) . '</strong></p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'aw_mail_tg' );
		echo '<input type="hidden" name="action" value="aw_mail_tg"><table class="form-table"><tr><th scope="row"><label for="aw-mail-tg-block">Block list</label></th><td>';
		echo '<textarea class="large-text code" rows="6" id="aw-mail-tg-block" name="block">' . esc_textarea( implode( "\n", aw_mail_tg_blocklist() ) ) . '</textarea>';
		echo '<p class="description">One address (name@example.com) or domain (example.com) per line: known guest-post pitch senders and anyone else who should not trigger a reminder. Their mail still arrives normally.</p></td></tr></table>';
		submit_button( 'Save block list' );
		echo '</form>';
	}
);

add_action(
	'admin_post_aw_mail_tg',
	function () {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'aw_mail_tg' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$lines = preg_split( '/[\s,]+/', strtolower( sanitize_textarea_field( wp_unslash( $_POST['block'] ?? '' ) ) ) );
		$keep  = array_values( array_unique( array_filter( array_map( fn( $l ) => ltrim( trim( $l ), '@' ), (array) $lines ), fn( $l ) => (bool) preg_match( '/^([a-z0-9._%+\-]+@)?[a-z0-9.\-]+\.[a-z]{2,}$/', $l ) ) ) );
		update_option( 'aw_mail_tg_block', implode( "\n", $keep ), false );
		wp_safe_redirect( admin_url( 'options-general.php?page=aw-mail-in&aw_tg=1#telegram' ) );
		exit;
	}
);
