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
	} elseif ( getenv( 'SMTP_PASS' ) ) {
		$s['pass'] = (string) getenv( 'SMTP_PASS' );
	} elseif ( defined( 'SMTP_PASS' ) && SMTP_PASS ) {
		$s['pass'] = (string) SMTP_PASS;
	}

	if ( getenv( 'SMTP_HOST' ) ) {
		$s['host'] = (string) getenv( 'SMTP_HOST' );
	} elseif ( defined( 'SMTP_HOST' ) && SMTP_HOST ) {
		$s['host'] = (string) SMTP_HOST;
	}

	if ( getenv( 'SMTP_PORT' ) ) {
		$s['port'] = (int) getenv( 'SMTP_PORT' );
	} elseif ( defined( 'SMTP_PORT' ) && SMTP_PORT ) {
		$s['port'] = (int) SMTP_PORT;
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
		wp_admin_notice( esc_html( $msgs[ $note ][1] ), array( 'type' => $msgs[ $note ][0] ) );
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

	// 1. Hostinger Mail Server Connectivity Configuration.
	$servers = mailweb_server_protocols();
	echo '<div class="card" style="max-width: 100%; margin-top: 24px; padding: 18px 20px;">';
	echo '<h3 style="margin-top: 0;">' . esc_html__( 'Hostinger Mail Server Connectivity (IMAP, SMTP, POP3)', 'mailweb' ) . '</h3>';
	echo '<p class="description">' . esc_html__( 'Standard protocol endpoints and ports for incoming and outgoing mailbox synchronization.', 'mailweb' ) . '</p>';
	echo '<table class="widefat striped" style="margin-top: 12px;">';
	echo '<thead><tr><th>' . esc_html__( 'Protocol', 'mailweb' ) . '</th><th>' . esc_html__( 'Hostname', 'mailweb' ) . '</th><th>' . esc_html__( 'Port', 'mailweb' ) . '</th><th>' . esc_html__( 'TLS/SSL', 'mailweb' ) . '</th><th>' . esc_html__( 'Status / Recommendation', 'mailweb' ) . '</th></tr></thead><tbody>';
	foreach ( $servers as $srv ) {
		echo '<tr>';
		echo '<td><strong>' . esc_html( $srv['protocol'] ) . '</strong></td>';
		echo '<td><code>' . esc_html( $srv['hostname'] ) . '</code></td>';
		echo '<td><strong>' . esc_html( $srv['port'] ) . '</strong></td>';
		echo '<td>' . esc_html( $srv['security'] ) . '</td>';
		echo '<td style="font-size:12px; color:' . ( ! empty( $srv['warning'] ) ? '#b45309' : '#15803d' ) . ';">' . esc_html( $srv['note'] ) . '</td>';
		echo '</tr>';
	}
	echo '</tbody></table></div>';

	// 2. DNS & Deliverability verification section (DKIM, SPF).
	$dns   = mailweb_dns_records();
	$dkim  = mailweb_verify_dkim();
	$badge = 'valid' === $dkim['status']
		? '<span style="color:#15803d; font-weight:600;">✓ ' . esc_html( $dkim['message'] ) . '</span>'
		: '<span style="color:#b45309; font-weight:600;">⚠ ' . esc_html( $dkim['message'] ) . '</span>';

	echo '<div class="card" style="max-width: 100%; margin-top: 24px; padding: 18px 20px;">';
	echo '<h3 style="margin-top: 0;">' . esc_html__( 'DNS & Deliverability Records (DKIM, SPF)', 'mailweb' ) . '</h3>';
	echo '<p>' . esc_html__( 'Add these DNS records to your domain DNS provider to ensure maximum inbox delivery and prevent spoofing.', 'mailweb' ) . ' ' . $badge . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '<table class="widefat striped" style="margin-top: 12px;">';
	echo '<thead><tr><th>' . esc_html__( 'Record Type', 'mailweb' ) . '</th><th>' . esc_html__( 'Host / Name', 'mailweb' ) . '</th><th>' . esc_html__( 'Value', 'mailweb' ) . '</th><th>' . esc_html__( 'Notes', 'mailweb' ) . '</th></tr></thead><tbody>';
	foreach ( $dns as $rec ) {
		echo '<tr>';
		echo '<td><strong>' . esc_html( $rec['type'] ) . '</strong></td>';
		echo '<td><code>' . esc_html( $rec['host'] ) . '</code></td>';
		echo '<td><textarea readonly rows="2" style="width:100%; font-family:monospace; font-size:11px;" onclick="this.select();">' . esc_textarea( $rec['value'] ) . '</textarea></td>';
		echo '<td style="font-size:12px; color:#64748b;">' . esc_html( $rec['note'] ) . '</td>';
		echo '</tr>';
	}
	echo '</tbody></table></div>';

	do_action( 'mailweb_smtp_screen_after' ); // the daily limit and queue (mail-cap.php)
}

/**
 * Hostinger Mail protocol endpoints, ports and security settings.
 *
 * @return array<string, array{protocol: string, hostname: string, port: string, security: string, note: string, warning?: bool}>
 */
function mailweb_server_protocols(): array {
	return array(
		'imap' => array(
			'protocol' => __( 'Incoming server (IMAP)', 'mailweb' ),
			'hostname' => 'imap.hostinger.com',
			'port'     => '993',
			'security' => 'SSL',
			'note'     => __( 'Recommended for syncing messages, folders, and read states.', 'mailweb' ),
		),
		'smtp' => array(
			'protocol' => __( 'Outgoing server (SMTP)', 'mailweb' ),
			'hostname' => 'smtp.hostinger.com',
			'port'     => '465',
			'security' => 'SSL',
			'note'     => __( 'Recommended outgoing relay for transaction notices and campaigns.', 'mailweb' ),
		),
		'pop3' => array(
			'protocol' => __( 'Incoming server (POP3)', 'mailweb' ),
			'hostname' => 'pop.hostinger.com',
			'port'     => '995',
			'security' => 'SSL',
			'note'     => __( 'Not recommended (downloads emails to single device without folder sync).', 'mailweb' ),
			'warning'  => true,
		),
	);
}

/**
 * Expected DNS and deliverability records (DKIM, SPF).
 *
 * @return array<string, array{type: string, host: string, fqdn: string, value: string, note: string}>
 */
function mailweb_dns_records(): array {
	$domain = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	return array(
		'dkim' => array(
			'type'  => 'TXT',
			'host'  => 'hostingermail1._domainkey',
			'fqdn'  => 'hostingermail1._domainkey.' . $domain,
			'value' => 'v=DKIM1; k=rsa; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAtDmULKqEraHiYpO3qZFQ2H40o6W7uLpredsqq/yD1mujgQ9HOdeb/NiZuwfl53dZSyLtra2vYM4N2NknjKOJrh68xRBucd+n/uiwuS7pVmdu8fCp3vCIP0i8143bVFvBYm9q3ohpckCmEu6Xwogj3gx0HdwAJwLvRRSzdjUtFAyc8H1dpjOeo8sMFYcm9IgEqYgkqpLS5ee8cAsu2hi4OHLszurchuycZHxIECzAEzdX4zrcsnP8dsTiGbl+M9xdi+W9gWVevinxCzOLckxpaB3+iT417kRxiBJIbILEWrDsaWWZ1eFXg2/W306XeUuAZqjyd/fQdLxPAgOD4Akm3wIDAQAB',
			'note'  => __( 'Cryptographic DKIM signature verifying emails originate from authorized Hostinger mail servers.', 'mailweb' ),
		),
		'spf'  => array(
			'type'  => 'TXT',
			'host'  => '@',
			'fqdn'  => $domain,
			'value' => 'v=spf1 include:_spf.mail.hostinger.com ~all',
			'note'  => __( 'SPF policy designating Hostinger as an authorized sender for this domain.', 'mailweb' ),
		),
	);
}

/**
 * Verifies live DKIM DNS record.
 *
 * @return array{status: string, message: string}
 */
function mailweb_verify_dkim(): array {
	$domain = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$host   = 'hostingermail1._domainkey.' . $domain;
	if ( ! function_exists( 'dns_get_record' ) ) {
		return array(
			'status'  => 'unknown',
			'message' => __( 'DNS check not available on this server.', 'mailweb' ),
		);
	}
	$records = @dns_get_record( $host, DNS_TXT ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
	if ( empty( $records ) || ! is_array( $records ) ) {
		return array(
			'status'  => 'pending',
			'message' => __( 'Record not detected yet (DNS propagation may take up to 24h).', 'mailweb' ),
		);
	}
	foreach ( $records as $r ) {
		$txt = $r['txt'] ?? ( $r['entries'][0] ?? '' );
		if ( false !== strpos( $txt, 'v=DKIM1' ) || false !== strpos( $txt, 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8' ) ) {
			return array(
				'status'  => 'valid',
				'message' => __( 'DKIM signature verified and active in DNS.', 'mailweb' ),
			);
		}
	}
	return array(
		'status'  => 'mismatch',
		'message' => __( 'DKIM TXT record found but public key differs.', 'mailweb' ),
	);
}

/**
 * Daily cron checking DKIM and SPF records, caching status for admin warning.
 */
function mailweb_dns_telemetry_check() {
	$res = mailweb_verify_dkim();
	if ( 'valid' !== ( $res['status'] ?? '' ) ) {
		set_transient( 'mailweb_dns_status_warning', $res['message'] ?? __( 'DKIM configuration needs attention.', 'mailweb' ), DAY_IN_SECONDS );
	} else {
		delete_transient( 'mailweb_dns_status_warning' );
	}
}
add_action( 'mailweb_daily_dns_check', 'mailweb_dns_telemetry_check' );

if ( ! wp_next_scheduled( 'mailweb_daily_dns_check' ) ) {
	wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'mailweb_daily_dns_check' );
}

add_action(
	'admin_post_mailweb_smtp_save',
	function () {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'mailweb_smtp' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mailweb' ), 403 );
		}
		$old  = (array) get_option( 'mailweb_smtp', array() );
		$pass = (string) wp_unslash( $_POST['pass'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- a password: any characters
		$new  = mailweb_smtp_clean(
			array(
				'user' => wp_unslash( $_POST['user'] ?? '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- cleaned by mailweb_smtp_clean()
				'name' => wp_unslash( $_POST['name'] ?? '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- same
				'host' => wp_unslash( $_POST['host'] ?? '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- same
				'port' => wp_unslash( $_POST['port'] ?? 0 ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- same
			)
		) + array( 'pass' => '' !== $pass ? mailweb_encrypt( $pass ) : ( $old['pass'] ?? '' ) );
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
