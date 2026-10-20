<?php
/**
 * Outgoing mail through the site's own mailbox (SMTP), set in Mail → Settings → Site sending.
 *
 * Until a password is saved, WordPress sends mail the default way. The password can be set in the server environment
 * or wp-config (MAILWEB_SMTP_PASSWORD); one saved on the screen is stored encrypted (crypto.php) and never shown again.
 *
 * @package mailweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Saved settings with defaults (a site can change the defaults with the mailweb_smtp_defaults filter). */
function mailweb_smtp_settings() {
	$defaults = apply_filters(
		'mailweb_smtp_defaults',
		array(
			'host' => 'smtp.hostinger.com',
			'port' => 465,
			'user' => 'noreply@' . wp_parse_url( home_url(), PHP_URL_HOST ),
			'name' => get_bloginfo( 'name' ),
			'pass' => '',
		)
	);
	$s         = array_merge( $defaults, array_filter( (array) get_option( 'mailweb_smtp', array() ), fn( $v ) => '' !== $v ) );
	$s['pass'] = mailweb_decrypt( (string) $s['pass'] );
	$env       = getenv( 'MAILWEB_SMTP_PASSWORD' );
	if ( $env ) {
		$s['pass'] = (string) $env;
	} elseif ( defined( 'MAILWEB_SMTP_PASSWORD' ) && MAILWEB_SMTP_PASSWORD ) {
		$s['pass'] = (string) MAILWEB_SMTP_PASSWORD;
	}
	return $s;
}

// Every message leaves as the site's mailbox (never wordpress@), with the site's name.
add_filter( 'wp_mail_from', fn() => mailweb_smtp_settings()['user'] );
add_filter( 'wp_mail_from_name', fn() => mailweb_smtp_settings()['name'] );

add_action(
	'phpmailer_init',
	function ( $mailer ) {
		$s = mailweb_smtp_settings();
		// A site-wide Reply-To (mailweb_smtp_reply_to filter), unless the message sets its own.
		$reply = (string) apply_filters( 'mailweb_smtp_reply_to', '' );
		if ( $reply && ! $mailer->getReplyToAddresses() ) {
			$mailer->addReplyTo( $reply, $s['name'] );
		}
		if ( '' === $s['pass'] ) {
			return;
		}
		$mailer->isSMTP();
		$mailer->Host       = $s['host']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$mailer->Port       = (int) $s['port']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$mailer->SMTPAuth   = true; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$mailer->SMTPSecure = 465 === (int) $s['port'] ? 'ssl' : 'tls'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$mailer->Username   = $s['user']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$mailer->Password   = $s['pass']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$mailer->setFrom( $s['user'], $s['name'], false );
	}
);

/** Mail → Settings → Site sending: the SMTP mailbox (then the daily limit and queue, mail-cap.php). */
function mailweb_smtp_screen() {
	$s     = mailweb_smtp_settings();
	$note  = sanitize_key( $_GET['mailweb_smtp'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	$fixed = getenv( 'MAILWEB_SMTP_PASSWORD' ) || ( defined( 'MAILWEB_SMTP_PASSWORD' ) && MAILWEB_SMTP_PASSWORD );
	$msgs  = array(
		'saved'  => array( 'success', __( 'Saved.', 'mailweb' ) ),
		/* translators: %s: the administration email address. */
		'sent'   => array( 'success', sprintf( __( 'Test email sent to %s. Check that inbox (and spam).', 'mailweb' ), get_option( 'admin_email' ) ) ),
		/* translators: %s: the error the mail server gave. */
		'failed' => array( 'error', sprintf( __( 'The test email could not be sent: %s', 'mailweb' ), (string) get_transient( 'mailweb_smtp_error' ) ) ),
	);
	echo '<h2>' . esc_html__( 'Site sending', 'mailweb' ) . '</h2>';
	if ( isset( $msgs[ $note ] ) ) {
		echo '<div class="notice notice-' . esc_attr( $msgs[ $note ][0] ) . '"><p>' . esc_html( $msgs[ $note ][1] ) . '</p></div>';
	}
	/* translators: %s: the sending mailbox address. */
	$status = '' === $s['pass'] ? __( 'not set up, using the server default', 'mailweb' ) : sprintf( __( 'sending through %s', 'mailweb' ), $s['user'] );
	/* translators: %s: sending status. */
	echo '<p>' . esc_html__( 'Every email the site sends (form notices, confirmations, newsletters) goes from this mailbox.', 'mailweb' ) . ' <strong>' . esc_html( sprintf( __( 'Status: %s', 'mailweb' ), $status ) ) . '</strong></p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'mailweb_smtp' );
	echo '<input type="hidden" name="action" value="mailweb_smtp_save"><table class="form-table">';
	$rows = array(
		'user' => array( __( 'Mailbox (email address)', 'mailweb' ), 'email', $s['user'] ),
		'pass' => array( __( 'Mailbox password', 'mailweb' ), 'password', '' ),
		'name' => array( __( 'Sender name', 'mailweb' ), 'text', $s['name'] ),
		'host' => array( __( 'SMTP server', 'mailweb' ), 'text', $s['host'] ),
		'port' => array( __( 'Port', 'mailweb' ), 'number', $s['port'] ),
	);
	foreach ( $rows as $k => $r ) {
		echo '<tr><th scope="row"><label for="mailweb-smtp-' . esc_attr( $k ) . '">' . esc_html( $r[0] ) . '</label></th><td>';
		if ( 'pass' === $k && $fixed ) {
			echo '<p>' . esc_html__( 'Set in wp-config or the server environment.', 'mailweb' ) . '</p></td></tr>';
			continue;
		}
		$ph = 'pass' === $k && '' !== $s['pass'] ? ' placeholder="' . esc_attr__( 'Saved. Leave empty to keep it.', 'mailweb' ) . '"' : '';
		echo '<input class="regular-text" id="mailweb-smtp-' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '" type="' . esc_attr( $r[1] ) . '" value="' . esc_attr( (string) $r[2] ) . '"' . $ph . ' autocomplete="' . ( 'pass' === $k ? 'new-password' : 'off' ) . '"></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $ph is escaped above
	}
	echo '</table><p><button class="button button-primary" name="do" value="save">' . esc_html__( 'Save', 'mailweb' ) . '</button> <button class="button" name="do" value="test">' . esc_html__( 'Save and send a test email', 'mailweb' ) . '</button></p></form>';
	do_action( 'mailweb_smtp_screen_after' ); // the daily limit and queue (mail-cap.php)
}

add_action(
	'admin_post_mailweb_smtp_save',
	function () {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'mailweb_smtp' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mailweb' ), 403 );
		}
		$old  = (array) get_option( 'mailweb_smtp', array() );
		$pass = (string) wp_unslash( $_POST['pass'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- a password: any characters
		$new  = array(
			'user' => sanitize_email( wp_unslash( $_POST['user'] ?? '' ) ),
			'name' => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'host' => sanitize_text_field( wp_unslash( $_POST['host'] ?? '' ) ),
			'port' => absint( $_POST['port'] ?? 0 ) ? absint( $_POST['port'] ) : '',
			'pass' => '' !== $pass ? mailweb_encrypt( $pass ) : ( $old['pass'] ?? '' ),
		);
		update_option( 'mailweb_smtp', $new, false );
		$note = 'saved';
		if ( 'test' === sanitize_key( wp_unslash( $_POST['do'] ?? '' ) ) ) {
			$err = '';
			$cb  = function ( $e ) use ( &$err ) {
				$err = $e->get_error_message();
			};
			add_action( 'wp_mail_failed', $cb );
			/* translators: %s: site name. */
			$subject = sprintf( __( 'Test email from %s', 'mailweb' ), get_bloginfo( 'name' ) );
			/* translators: %s: the sending mailbox address. */
			$body = sprintf( __( 'This test email was sent through %s. Mail is working.', 'mailweb' ), mailweb_smtp_settings()['user'] );
			$ok   = wp_mail( get_option( 'admin_email' ), $subject, $body );
			remove_action( 'wp_mail_failed', $cb );
			$note = $ok ? 'sent' : 'failed';
			set_transient( 'mailweb_smtp_error', $err ? $err : __( 'unknown error', 'mailweb' ), 5 * MINUTE_IN_SECONDS );
		}
		wp_safe_redirect( mailweb_settings_url( 'sending', array( 'mailweb_smtp' => $note ) ) );
		exit;
	}
);
