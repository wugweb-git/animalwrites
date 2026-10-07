<?php
/**
 * Outgoing mail through the site's own mailbox (SMTP), set in WP Admin > Settings > Mail.
 *
 * Until a password is saved, WordPress sends mail the default way. The password is stored in the option
 * kits_mail (not autoloaded) and is never shown again on the screen.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Saved settings with defaults (the site can change the defaults with the kits_mail_defaults filter). */
function kits_mail_settings() {
	$defaults = apply_filters(
		'kits_mail_defaults',
		array(
			'host' => 'smtp.hostinger.com',
			'port' => 465,
			'user' => 'noreply@' . wp_parse_url( home_url(), PHP_URL_HOST ),
			'name' => get_bloginfo( 'name' ),
			'pass' => '',
		)
	);
	return array_merge( $defaults, array_filter( (array) get_option( 'kits_mail', array() ), fn( $v ) => '' !== $v ) );
}

add_action(
	'phpmailer_init',
	function ( $mailer ) {
		$s = kits_mail_settings();
		if ( '' === $s['pass'] ) {
			return;
		}
		$mailer->isSMTP();
		$mailer->Host       = $s['host'];
		$mailer->Port       = (int) $s['port'];
		$mailer->SMTPAuth   = true;
		$mailer->SMTPSecure = 465 === (int) $s['port'] ? 'ssl' : 'tls';
		$mailer->Username   = $s['user'];
		$mailer->Password   = $s['pass'];
		$mailer->setFrom( $s['user'], $s['name'], false );
	}
);

add_action(
	'admin_menu',
	function () {
		add_options_page( 'Mail', 'Mail', 'manage_options', 'kits-mail', 'kits_mail_screen' );
	}
);

function kits_mail_screen() {
	$s    = kits_mail_settings();
	$note = sanitize_key( $_GET['kits_mail'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	$msgs = array(
		'saved'  => array( 'success', 'Saved.' ),
		'sent'   => array( 'success', 'Test email sent to ' . get_option( 'admin_email' ) . '. Check that inbox (and spam).' ),
		'failed' => array( 'error', 'The test email could not be sent: ' . (string) get_transient( 'kits_mail_error' ) ),
	);
	echo '<div class="wrap"><h1>Mail</h1>';
	if ( isset( $msgs[ $note ] ) ) {
		echo '<div class="notice notice-' . esc_attr( $msgs[ $note ][0] ) . '"><p>' . esc_html( $msgs[ $note ][1] ) . '</p></div>';
	}
	echo '<p>Site emails (form notices, confirmations, newsletters) are sent from this mailbox. Status: <strong>'
		. esc_html( '' === $s['pass'] ? 'not set up, using the server default' : 'sending through ' . $s['user'] ) . '</strong></p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'kits_mail' );
	echo '<input type="hidden" name="action" value="kits_mail_save"><table class="form-table">';
	$rows = array(
		'user' => array( 'Mailbox (email address)', 'email', $s['user'] ),
		'pass' => array( 'Mailbox password', 'password', '' ),
		'name' => array( 'Sender name', 'text', $s['name'] ),
		'host' => array( 'SMTP server', 'text', $s['host'] ),
		'port' => array( 'Port', 'number', $s['port'] ),
	);
	foreach ( $rows as $k => $r ) {
		$ph = 'pass' === $k && '' !== $s['pass'] ? ' placeholder="Saved. Leave empty to keep it."' : '';
		echo '<tr><th scope="row"><label for="kits-mail-' . esc_attr( $k ) . '">' . esc_html( $r[0] ) . '</label></th><td><input class="regular-text" id="kits-mail-' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '" type="' . esc_attr( $r[1] ) . '" value="' . esc_attr( (string) $r[2] ) . '"' . $ph . ' autocomplete="' . ( 'pass' === $k ? 'new-password' : 'off' ) . '"></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</table><p><button class="button button-primary" name="do" value="save">Save</button> <button class="button" name="do" value="test">Save and send a test email</button></p></form></div>';
}

add_action(
	'admin_post_kits_mail_save',
	function () {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'kits_mail' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$old  = (array) get_option( 'kits_mail', array() );
		$pass = (string) wp_unslash( $_POST['pass'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$new  = array(
			'user' => sanitize_email( wp_unslash( $_POST['user'] ?? '' ) ),
			'name' => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'host' => sanitize_text_field( wp_unslash( $_POST['host'] ?? '' ) ),
			'port' => absint( $_POST['port'] ?? 0 ) ?: '',
			'pass' => '' !== $pass ? $pass : ( $old['pass'] ?? '' ),
		);
		update_option( 'kits_mail', $new, false );
		$note = 'saved';
		if ( 'test' === ( $_POST['do'] ?? '' ) ) {
			$err = '';
			$cb  = function ( $e ) use ( &$err ) {
				$err = $e->get_error_message();
			};
			add_action( 'wp_mail_failed', $cb );
			$ok = wp_mail( get_option( 'admin_email' ), 'Test email from ' . get_bloginfo( 'name' ), 'This test email was sent through ' . kits_mail_settings()['user'] . '. Mail is working.' );
			remove_action( 'wp_mail_failed', $cb );
			$note = $ok ? 'sent' : 'failed';
			set_transient( 'kits_mail_error', $err ? $err : 'unknown error', 5 * MINUTE_IN_SECONDS );
		}
		wp_safe_redirect( admin_url( 'options-general.php?page=kits-mail&kits_mail=' . $note ) );
		exit;
	}
);
