<?php
/**
 * Newsletter: sign-up with email confirmation, a subscriber list, and sending from WP Admin > Newsletter.
 *
 * Table {prefix}aw_subscribers. Statuses: pending (signed up, not yet confirmed), invited (1.0 volunteer sent the
 * invitation, not yet confirmed), active (confirmed: the only status that receives newsletters), unsubscribed.
 * Every email carries an unsubscribe link; invitations also carry the confirm link. Sending runs in batches through
 * WP-Cron so the mailbox stays under its hourly limit. Nothing is ever sent without an administrator pressing Send.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_NL_DB_VERSION = '1';
const AW_NL_BATCH      = 20; // emails per run, one run every 5 minutes
const AW_NL_CONSENT    = 'v2-newsletter-1';

function aw_nl_table() {
	global $wpdb;
	return $wpdb->prefix . 'aw_subscribers';
}

add_action(
	'init',
	function () {
		if ( AW_NL_DB_VERSION === get_option( 'aw_subscribers_db' ) ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta(
			'CREATE TABLE ' . aw_nl_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			email varchar(190) NOT NULL,
			name varchar(190) NOT NULL DEFAULT '',
			source varchar(20) NOT NULL,
			status varchar(20) NOT NULL,
			token char(32) NOT NULL,
			consent_text_version varchar(40) NOT NULL DEFAULT '',
			consent_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email),
			UNIQUE KEY token (token),
			KEY status (status)
			) {$wpdb->get_charset_collate()};"
		);
		update_option( 'aw_subscribers_db', AW_NL_DB_VERSION, false );
	},
	5
);

function aw_nl_get( $field, $value ) {
	global $wpdb;
	$t = aw_nl_table();
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE {$field} = %s", $value ), ARRAY_A ); // phpcs:ignore
}

/**
 * Add someone, or return their row if they are already there (a confirmed or unsubscribed person is left as they are,
 * except that signing up again on the site after unsubscribing starts a new confirmation).
 */
function aw_nl_add( $email, $name, $source, $status ) {
	global $wpdb;
	$email = strtolower( trim( $email ) );
	$now   = current_time( 'mysql', true );
	$row   = aw_nl_get( 'email', $email );
	if ( ! $row ) {
		$wpdb->insert( // phpcs:ignore
			aw_nl_table(),
			array( 'email' => $email, 'name' => $name, 'source' => $source, 'status' => $status, 'token' => bin2hex( random_bytes( 16 ) ), 'created_at' => $now, 'updated_at' => $now )
		);
		return aw_nl_get( 'email', $email );
	}
	if ( 'site' === $source && in_array( $row['status'], array( 'unsubscribed', 'invited' ), true ) ) {
		$wpdb->update( aw_nl_table(), array( 'status' => 'pending', 'updated_at' => $now ), array( 'id' => (int) $row['id'] ) ); // phpcs:ignore
		$row['status'] = 'pending';
	}
	return $row;
}

function aw_nl_link( $do, $token ) {
	return add_query_arg( array( 'aw_nl' => $do, 't' => $token ), home_url( '/' ) );
}

/** Plain-text email with the unsubscribe line every message carries. */
function aw_nl_mail( $to, $subject, $body, $token ) {
	$body .= "\n\n--\nTo stop these emails: " . aw_nl_link( 'unsubscribe', $token );
	return wp_mail( $to, $subject, $body, array( 'List-Unsubscribe: <' . aw_nl_link( 'unsubscribe', $token ) . '>' ) );
}

/* ---------- Sign-up form: [aw_newsletter] ---------- */

function aw_newsletter_form() {
	$done = sanitize_key( $_GET['aw_done'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	$to   = sanitize_email( wp_unslash( $_GET['to'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$msgs = array(
		'nl_check'     => array( 'Check your inbox', $to ? sprintf( 'We\'ve sent a link to %s. Open it to confirm your subscription.', $to ) : '' ),
		'nl_already'   => array( 'You\'re already subscribed', $to ? sprintf( 'Updates go to %s.', $to ) : '' ),
		'nl_confirmed' => array( 'Confirmed. You are on the list.', '' ),
		'nl_unsub'     => array( 'You are unsubscribed and will not get these emails again.', '' ),
		'nl_badlink'   => array( 'This link has expired or was already used.', '' ),
	);
	if ( isset( $msgs[ $done ] ) ) {
		return '<div class="aw-form-done kit-form-done" role="status" tabindex="-1"><p class="aw-form-done-title">' . esc_html( $msgs[ $done ][0] ) . '</p>' . ( $msgs[ $done ][1] ? '<p>' . esc_html( $msgs[ $done ][1] ) . '</p>' : '' ) . '</div>';
	}
	$form  = 'aw_newsletter';
	$html  = aw_form_open( $form, array( 'class' => 'aw-form aw-newsletter-form' ) );
	$html .= aw_field( $form, array( 'name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'icon' => 'mail', 'attrs' => array( 'autocomplete' => 'email', 'maxlength' => 254, 'placeholder' => 'you@example.com' ), 'messages' => array( 'required' => 'Enter your email address', 'email' => 'Enter a full email address, like you@example.com' ) ) );
	$html .= aw_field( $form, array( 'name' => 'name', 'label' => 'Your name', 'icon' => 'name', 'attrs' => array( 'autocomplete' => 'name', 'maxlength' => 80, 'placeholder' => 'Asha' ) ) );
	$html .= '<div class="aw-nl-submit"><button type="submit" class="kit-btn">Subscribe</button></div>';
	$html .= aw_field( $form, array( 'name' => 'consent', 'label' => 'Email me Animal Writes updates. I can unsubscribe at any time.', 'type' => 'checkbox', 'required' => true, 'messages' => array( 'required' => 'Tick the box to subscribe' ) ) );
	$html .= '</form>';
	return $html;
}
add_shortcode( 'aw_newsletter', 'aw_newsletter_form' );

function aw_newsletter_submit() {
	$form = 'aw_newsletter';
	$back = aw_form_back( '/' );
	$done = add_query_arg( 'aw_done', 'nl_check', $back ) . '#newsletter';
	$rl   = aw_form_guard( $form, $back, 'newsletter', $done );
	$v    = array(
		'email'   => aw_clean_email( sanitize_text_field( wp_unslash( $_POST['email'] ?? '' ) ) ),
		'name'    => trim( sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ) ),
		'consent' => empty( $_POST['consent'] ) ? '' : '1',
	);
	$errors = aw_form_validate(
		array( 'email' => array( 'required', 'email' ), 'name' => array( 'max:80' ), 'consent' => array( 'required' ) ),
		$v,
		array( 'email' => 'Email', 'name' => 'Your name', 'consent' => 'Agreement' ),
		array( 'consent' => array( 'required' => 'Tick the box to subscribe' ) )
	);
	if ( $errors ) {
		aw_form_fail( $form, $errors, $v, $back, 'newsletter' );
	}
	$before = aw_nl_get( 'email', strtolower( $v['email'] ) );
	if ( $before && 'active' === ( $before['status'] ?? '' ) ) {
		aw_form_count( $rl );
		aw_form_success( add_query_arg( array( 'aw_done' => 'nl_already', 'to' => $v['email'] ), $back ) . '#newsletter', 'You\'re already subscribed', sprintf( 'Updates go to %s.', $v['email'] ) );
	}
	$row = aw_nl_add( $v['email'], $v['name'], 'site', 'pending' );
	if ( ! $row ) {
		aw_form_fail( $form, array(), $v, $back, 'newsletter', 'Something went wrong. Your details are still here — try again.' );
	}
	if ( 'pending' === $row['status'] ) { // double opt-in: confirm by email
		wp_mail(
			$row['email'],
			'Confirm your subscription to ' . get_bloginfo( 'name' ),
			"Click this link to confirm that you want Animal Writes updates:\n" . aw_nl_link( 'confirm', $row['token'] ) . "\n\nIf you did not ask for this, ignore this email and you will not hear from us."
		);
	}
	aw_form_count( $rl );
	aw_form_success( add_query_arg( 'to', $v['email'], $done ), 'Check your inbox', sprintf( 'We\'ve sent a link to %s. Open it to confirm your subscription.', $v['email'] ) );
}
add_action( 'admin_post_nopriv_aw_newsletter', 'aw_newsletter_submit' );
add_action( 'admin_post_aw_newsletter', 'aw_newsletter_submit' );

// Confirm and unsubscribe links.
add_action(
	'template_redirect',
	function () {
		$do = sanitize_key( $_GET['aw_nl'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! in_array( $do, array( 'confirm', 'unsubscribe' ), true ) ) {
			return;
		}
		$token = preg_replace( '/[^a-f0-9]/', '', (string) wp_unslash( $_GET['t'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$row   = 32 === strlen( $token ) ? aw_nl_get( 'token', $token ) : null;
		$note  = 'nl_badlink';
		global $wpdb;
		$now = current_time( 'mysql', true );
		if ( $row && 'confirm' === $do && in_array( $row['status'], array( 'pending', 'invited', 'active' ), true ) ) {
			if ( 'active' !== $row['status'] ) {
				$wpdb->update( aw_nl_table(), array( 'status' => 'active', 'consent_text_version' => AW_NL_CONSENT . ( 'invited' === $row['status'] ? '-invite' : '' ), 'consent_at' => $now, 'updated_at' => $now ), array( 'id' => (int) $row['id'] ) ); // phpcs:ignore
			}
			$note = 'nl_confirmed';
		} elseif ( $row && 'unsubscribe' === $do ) {
			$wpdb->update( aw_nl_table(), array( 'status' => 'unsubscribed', 'updated_at' => $now ), array( 'id' => (int) $row['id'] ) ); // phpcs:ignore
			$note = 'nl_unsub';
		}
		nocache_headers();
		wp_safe_redirect( add_query_arg( 'aw_done', $note, home_url( '/' ) ) . '#newsletter' ); // the sign-up sits in the footer of every page
		exit;
	}
);

/* ---------- Sending ---------- */

/** Who an audience reaches right now: list of [email, name, token]. */
function aw_nl_audience( $audience ) {
	global $wpdb;
	$t = aw_nl_table();
	if ( 'active' === $audience ) {
		return $wpdb->get_results( "SELECT email, name, token FROM {$t} WHERE status = 'active' ORDER BY id", ARRAY_A ); // phpcs:ignore
	}
	if ( 'v1_invite' === $audience ) {
		// 1.0 volunteers who are not on the list yet in any form (never re-invite, never email the unsubscribed).
		$v = aw_vol_table();
		return $wpdb->get_results( "SELECT v.email, v.name, '' AS token FROM {$v} v LEFT JOIN {$t} s ON s.email = v.email WHERE v.source = 'v1_campaign' AND v.status = 'active' AND s.id IS NULL ORDER BY v.id", ARRAY_A ); // phpcs:ignore
	}
	return array();
}

add_filter(
	'cron_schedules',
	function ( $s ) {
		$s['aw_5min'] = array( 'interval' => 300, 'display' => 'Every 5 minutes' );
		return $s;
	}
);

/** Send the next batch of the running send, if any. */
function aw_nl_run_batch() {
	$job = get_option( 'aw_nl_job' );
	if ( ! is_array( $job ) || 'sending' !== $job['state'] ) {
		wp_clear_scheduled_hook( 'aw_nl_batch' );
		return;
	}
	$batch = array_splice( $job['queue'], 0, AW_NL_BATCH );
	foreach ( $batch as $p ) {
		if ( 'v1_invite' === $job['audience'] ) {
			$row = aw_nl_add( $p['email'], $p['name'], 'v1_invite', 'invited' );
			if ( ! $row || 'invited' !== $row['status'] ) {
				continue; // they joined some other way meanwhile
			}
			$body = $job['body'] . "\n\nTo get these updates, confirm here:\n" . aw_nl_link( 'confirm', $row['token'] );
			$ok   = aw_nl_mail( $row['email'], $job['subject'], $body, $row['token'] );
		} else {
			$row = aw_nl_get( 'email', $p['email'] );
			if ( ! $row || 'active' !== $row['status'] ) {
				continue; // unsubscribed since the send started
			}
			$ok = aw_nl_mail( $row['email'], $job['subject'], $job['body'], $row['token'] );
		}
		$job[ $ok ? 'sent' : 'failed' ]++;
	}
	if ( ! $job['queue'] ) {
		$job['state']    = 'done';
		$job['finished'] = current_time( 'mysql' );
		wp_clear_scheduled_hook( 'aw_nl_batch' );
	}
	update_option( 'aw_nl_job', $job, false );
}
add_action( 'aw_nl_batch', 'aw_nl_run_batch' );

/* ---------- Admin: WP Admin > Newsletter ---------- */

add_action(
	'admin_menu',
	function () {
		add_menu_page( 'Newsletter', 'Newsletter', AW_VOL_CAP, 'aw-newsletter', 'aw_nl_admin_page', 'dashicons-email-alt', 27 );
	}
);

function aw_nl_admin_page() {
	global $wpdb;
	$t      = aw_nl_table();
	$counts = wp_list_pluck( $wpdb->get_results( "SELECT status, COUNT(*) n FROM {$t} GROUP BY status", ARRAY_A ), 'n', 'status' ); // phpcs:ignore
	$job    = get_option( 'aw_nl_job' );
	$note   = sanitize_key( $_GET['nl'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	$notes  = array(
		'test'    => 'Test sent to ' . wp_get_current_user()->user_email . '.',
		'started' => 'Sending started. It goes out in batches of ' . AW_NL_BATCH . ' every 5 minutes; this page shows progress.',
		'busy'    => 'Another send is still running. Wait for it to finish.',
		'empty'   => 'Nobody to send to in that audience.',
		'stopped' => 'Sending stopped.',
		'missing' => 'Write a subject and a message first.',
	);
	echo '<div class="wrap"><h1>Newsletter</h1>';
	if ( isset( $notes[ $note ] ) ) {
		echo '<div class="notice notice-info"><p>' . esc_html( $notes[ $note ] ) . '</p></div>';
	}
	if ( '' === kits_mail_settings()['pass'] ) {
		echo '<div class="notice notice-warning"><p>Set up the mailbox first in <a href="' . esc_url( admin_url( 'options-general.php?page=kits-mail' ) ) . '">Settings &gt; Mail</a>.</p></div>';
	}
	echo '<p>Confirmed subscribers: <strong>' . (int) ( $counts['active'] ?? 0 ) . '</strong> · Waiting to confirm: ' . (int) ( $counts['pending'] ?? 0 )
		. ' · 1.0 volunteers invited, not yet confirmed: ' . (int) ( $counts['invited'] ?? 0 ) . ' · Unsubscribed: ' . (int) ( $counts['unsubscribed'] ?? 0 )
		. ' · 1.0 volunteers not yet invited: ' . count( aw_nl_audience( 'v1_invite' ) ) . '</p>';
	$export = wp_nonce_url( admin_url( 'admin-post.php?action=aw_nl_export' ), 'aw_nl_export' );
	echo '<p><a class="button" href="' . esc_url( $export ) . '">Export subscribers (CSV)</a></p>';

	if ( is_array( $job ) ) {
		echo '<h2>Last send</h2><p>' . esc_html( $job['subject'] ) . ' (' . esc_html( 'v1_invite' === $job['audience'] ? 'invitation to 1.0 volunteers' : 'confirmed subscribers' ) . '): '
			. esc_html( 'sending' === $job['state'] ? 'sending' : ( 'stopped' === $job['state'] ? 'stopped' : 'finished ' . $job['finished'] ) ) . '. Sent ' . (int) $job['sent'] . ', failed ' . (int) $job['failed'] . ', waiting ' . count( $job['queue'] ) . '.</p>';
		if ( 'sending' === $job['state'] ) {
			echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=aw_nl_stop' ), 'aw_nl_stop' ) ) . '">Stop sending</a></p>';
		}
	}

	$draft = (array) get_option( 'aw_nl_draft', array() );
	echo '<h2>Write</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'aw_nl_send' );
	echo '<input type="hidden" name="action" value="aw_nl_send"><table class="form-table">';
	echo '<tr><th><label for="aw-nl-audience">Send to</label></th><td><select id="aw-nl-audience" name="audience">'
		. '<option value="active"' . selected( $draft['audience'] ?? '', 'active', false ) . '>Confirmed subscribers</option>'
		. '<option value="v1_invite"' . selected( $draft['audience'] ?? '', 'v1_invite', false ) . '>Invitation to 1.0 volunteers (adds a confirm link; only those who confirm get newsletters)</option></select></td></tr>';
	echo '<tr><th><label for="aw-nl-subject">Subject</label></th><td><input class="large-text" id="aw-nl-subject" name="subject" value="' . esc_attr( $draft['subject'] ?? '' ) . '"></td></tr>';
	echo '<tr><th><label for="aw-nl-body">Message</label></th><td><textarea class="large-text" rows="14" id="aw-nl-body" name="body">' . esc_textarea( $draft['body'] ?? '' ) . '</textarea>'
		. '<p class="description">Plain text. The unsubscribe link (and, for invitations, the confirm link) is added at the end automatically.</p></td></tr></table>';
	echo '<p><button class="button" name="do" value="save">Save draft</button> <button class="button" name="do" value="test">Send a test to me</button> '
		. '<button class="button button-primary" name="do" value="send" onclick="return confirm(\'Send this to everyone in the chosen audience now?\')">Send</button></p></form></div>';
}

add_action(
	'admin_post_aw_nl_send',
	function () {
		if ( ! current_user_can( AW_VOL_CAP ) || ! check_admin_referer( 'aw_nl_send' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$draft = array(
			'audience' => 'v1_invite' === ( $_POST['audience'] ?? '' ) ? 'v1_invite' : 'active',
			'subject'  => sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) ),
			'body'     => sanitize_textarea_field( wp_unslash( $_POST['body'] ?? '' ) ),
		);
		update_option( 'aw_nl_draft', $draft, false );
		$do   = sanitize_key( $_POST['do'] ?? 'save' );
		$note = '';
		if ( in_array( $do, array( 'test', 'send' ), true ) && ( '' === $draft['subject'] || '' === $draft['body'] ) ) {
			$note = 'missing';
		} elseif ( 'test' === $do ) {
			$body = $draft['body'] . ( 'v1_invite' === $draft['audience'] ? "\n\nTo get these updates, confirm here:\n(the person's own confirm link)" : '' );
			wp_mail( wp_get_current_user()->user_email, '[Test] ' . $draft['subject'], $body . "\n\n--\nTo stop these emails: (the person's own unsubscribe link)" );
			$note = 'test';
		} elseif ( 'send' === $do ) {
			$job = get_option( 'aw_nl_job' );
			if ( is_array( $job ) && 'sending' === $job['state'] ) {
				$note = 'busy';
			} else {
				$queue = aw_nl_audience( $draft['audience'] );
				if ( ! $queue ) {
					$note = 'empty';
				} else {
					update_option( 'aw_nl_job', $draft + array( 'queue' => $queue, 'sent' => 0, 'failed' => 0, 'state' => 'sending', 'started' => current_time( 'mysql' ), 'finished' => '' ), false );
					if ( ! wp_next_scheduled( 'aw_nl_batch' ) ) {
						wp_schedule_event( time(), 'aw_5min', 'aw_nl_batch' );
					}
					$note = 'started';
				}
			}
		}
		wp_safe_redirect( admin_url( 'admin.php?page=aw-newsletter' . ( $note ? '&nl=' . $note : '' ) ) );
		exit;
	}
);

add_action(
	'admin_post_aw_nl_stop',
	function () {
		if ( ! current_user_can( AW_VOL_CAP ) || ! check_admin_referer( 'aw_nl_stop' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$job = get_option( 'aw_nl_job' );
		if ( is_array( $job ) ) {
			$job['state'] = 'stopped';
			update_option( 'aw_nl_job', $job, false );
		}
		wp_clear_scheduled_hook( 'aw_nl_batch' );
		wp_safe_redirect( admin_url( 'admin.php?page=aw-newsletter&nl=stopped' ) );
		exit;
	}
);

add_action(
	'admin_post_aw_nl_export',
	function () {
		if ( ! current_user_can( AW_VOL_CAP ) || ! check_admin_referer( 'aw_nl_export' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		global $wpdb;
		$t = aw_nl_table();
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="aw-subscribers-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore
		fputcsv( $out, array( 'email', 'name', 'source', 'status', 'consent_text_version', 'consent_at', 'created_at' ) );
		foreach ( $wpdb->get_results( "SELECT email, name, source, status, consent_text_version, consent_at, created_at FROM {$t} ORDER BY id", ARRAY_A ) as $r ) { // phpcs:ignore
			fputcsv( $out, array_map( fn( $v ) => is_string( $v ) && preg_match( '/^[=+\-@]/', $v ) ? "'" . $v : $v, $r ) );
		}
		fclose( $out ); // phpcs:ignore
		exit;
	}
);
