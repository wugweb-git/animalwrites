<?php
/**
 * Reply to an incoming email (owner, 17 Oct 2026). FluentCRM free has no "Send Email" on a contact's profile, so each
 * incoming email (an Inbox item tagged Email, made by mail-in.php) has its own Reply: Inbox → Contact messages → Reply,
 * the Reply link in the email's note on the sender's FluentCRM contact, and the link at the top of the email's own
 * screen. All open Inbox → Reply (admin.php?page=aw-reply&message=ID): the email, earlier replies to it, and a form.
 *
 * - Only emails get a Reply. Contact form messages, volunteer and campaign sign-ups are not emails and get none; a
 *   contact who never emailed us gets nothing new.
 * - The reply goes to the sender, subject "Re: <that email's subject>", through wp_mail (the site's mailbox), plain
 *   text, with the email quoted below. Reply-To is the address that email was sent to (vedanshu@ / contact@ /
 *   support@), so the answer comes back through the mail webhook as a new email; In-Reply-To and References carry its
 *   Message-ID, so it stays in the same thread in their mail app. X-AW-Priority 0: first in the daily queue
 *   (mail-cap.php). No List-Unsubscribe (a personal reply).
 * - The sent reply is a note on the sender's contact, "Reply sent: <subject>", with its text, so the contact's Notes
 *   hold both sides; the email keeps a list of its replies (time, subject, who sent it, queued or sent).
 * - Who may reply: administrators and FluentCRM users with "manage contacts". The form carries a nonce.
 *
 * Moved to Wug Mail (aw-mail) on 18 Oct 2026 and not loaded: replies happen in Hostinger webmail (owner, 18 Oct 2026).
 * Kept so nothing is lost; aw-mail.php loads it only if the owner turns replies in WordPress back on.
 *
 * @package aw-mail
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** May the current user reply? */
function aw_reply_can() {
	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}
	return class_exists( '\FluentCrm\App\Services\PermissionManager' ) && \FluentCrm\App\Services\PermissionManager::currentUserCan( 'fcrm_manage_contacts' );
}

/** The Inbox item ID if it is an incoming email (not a form message), else 0. */
function aw_reply_email_item( $id ) {
	$id = (int) $id;
	return $id && 'aw_message' === get_post_type( $id ) && 'email' === get_post_meta( $id, 'aw_m_about', true ) && is_email( (string) get_post_meta( $id, 'aw_m_email', true ) ) ? $id : 0;
}

/** The Reply page for one incoming email. */
function aw_reply_url( $id ) {
	return admin_url( 'admin.php?page=aw-reply&message=' . (int) $id );
}

/** An email's subject (its Inbox title without "Email: "). */
function aw_reply_subject_of( $id ) {
	return trim( (string) preg_replace( '/^Email:\s*/', '', (string) get_post_field( 'post_title', $id ) ) );
}

/** "Re: <subject>" (no second "Re:"). */
function aw_reply_re( $subject ) {
	$subject = trim( (string) $subject );
	return preg_match( '/^re:/i', $subject ) ? $subject : 'Re: ' . ( '' !== $subject ? $subject : '(no subject)' );
}

// The page: in the Inbox menu's pages but not listed in it.
add_action(
	'admin_menu',
	function () {
		$hook = add_submenu_page( 'aw-inbox', 'Reply', 'Reply', 'read', 'aw-reply', 'aw_reply_page' );
		if ( $hook ) {
			add_action( 'load-' . $hook, fn() => $GLOBALS['title'] = 'Reply' );
		}
	},
	20
);
add_action( 'admin_head', fn() => remove_submenu_page( 'aw-inbox', 'aw-reply' ) );

/** Inbox → Reply. */
function aw_reply_page() {
	if ( ! aw_reply_can() ) {
		wp_die( 'You cannot reply to emails.', 403 );
	}
	$id = aw_reply_email_item( (int) ( $_GET['message'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification
	echo '<div class="wrap"><h1>Reply</h1>';
	if ( ! $id ) {
		echo '<p>Only an incoming email can be answered here. Open one from Inbox → Contact messages.</p></div>';
		return;
	}
	$done = sanitize_key( $_GET['aw_reply'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( 'sent' === $done ) {
		echo '<div class="notice notice-success"><p>Reply sent. It is on the contact\'s Notes as "Reply sent".</p></div>';
	} elseif ( 'queued' === $done ) {
		echo '<div class="notice notice-success"><p>Reply saved and first in the email queue: the daily email limit is reached, so it goes out as soon as there is room. It is on the contact\'s Notes as "Reply sent".</p></div>';
	} elseif ( 'empty' === $done ) {
		echo '<div class="notice notice-error"><p>Write a message first.</p></div>';
	} elseif ( 'failed' === $done ) {
		echo '<div class="notice notice-error"><p>The reply could not be sent. Nothing was logged; try again.</p></div>';
	}
	$m       = fn( $k ) => trim( (string) get_post_meta( $id, 'aw_m_' . $k, true ) );
	$post    = get_post( $id );
	$subject = aw_reply_subject_of( $id );
	$c       = function_exists( 'aw_crm_on' ) && aw_crm_on() ? FluentCrmApi( 'contacts' )->getContact( strtolower( $m( 'email' ) ) ) : null;
	$rows    = array(
		'From'    => trim( $m( 'name' ) . ' <' . $m( 'email' ) . '>' ),
		'Sent to' => aw_crm_message_to( $id ) . ( $m( 'mailbox' ) && $m( 'mailbox' ) !== aw_crm_message_to( $id ) ? ' (in ' . $m( 'mailbox' ) . ')' : '' ),
		'Sent'    => mysql2date( 'j M Y, g:i a', $post->post_date ),
		'Subject' => '' !== $subject ? $subject : '(no subject)',
	);
	echo '<h2>The email</h2><table class="form-table" role="presentation"><tbody>';
	foreach ( $rows as $k => $v ) {
		echo '<tr><th scope="row">' . esc_html( $k ) . '</th><td>' . esc_html( $v ) . '</td></tr>';
	}
	echo '</tbody></table>';
	$text = trim( (string) $post->post_content );
	echo '<div class="card">' . ( '' !== $text ? wpautop( esc_html( $text ) ) : '<p><em>(no text)</em></p>' ) . '</div>';
	$links = '<a href="' . esc_url( admin_url( 'post.php?post=' . $id . '&action=edit' ) ) . '">Open in Inbox</a>';
	if ( $c ) {
		$links .= ' · <a href="' . esc_url( admin_url( 'admin.php?page=fluentcrm-admin#/subscribers/' . (int) $c->id . '/notes' ) ) . '">Contact\'s notes in FluentCRM</a>';
	}
	echo '<p>' . $links . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts

	$replies = array_filter( (array) get_post_meta( $id, 'aw_m_reply', false ), 'is_array' );
	if ( $replies ) {
		echo '<h2>Replies already sent</h2><ul>';
		foreach ( array_reverse( $replies ) as $r ) {
			echo '<li>' . esc_html( mysql2date( 'j M Y, g:i a', (string) ( $r['at'] ?? '' ) ) . ' · ' . ( $r['subject'] ?? '' ) . ' · ' . ( $r['by'] ?? '' ) . ( ! empty( $r['queued'] ) ? ' · queued' : '' ) ) . '</li>';
		}
		echo '</ul>';
	}

	echo '<h2>Your reply</h2>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="aw_reply_send"><input type="hidden" name="message" value="' . (int) $id . '">';
	wp_nonce_field( 'aw_reply_' . $id, 'aw_reply_nonce' );
	echo '<table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row">To</th><td>' . esc_html( $m( 'email' ) ) . '</td></tr>';
	echo '<tr><th scope="row">Their answer comes back to</th><td>' . esc_html( aw_crm_message_to( $id ) ) . '</td></tr>';
	echo '<tr><th scope="row"><label for="aw-reply-subject">Subject</label></th><td><input type="text" class="large-text" id="aw-reply-subject" name="subject" value="' . esc_attr( aw_reply_re( $subject ) ) . '" required></td></tr>';
	echo '<tr><th scope="row"><label for="aw-reply-text">Message</label></th><td><textarea class="large-text" rows="12" id="aw-reply-text" name="text" required></textarea><p class="description">Plain text. Their email is quoted below your message.</p></td></tr>';
	echo '</tbody></table>';
	submit_button( 'Send reply' );
	echo '</form></div>';
}

/**
 * Send a reply to one incoming email and log it. Returns 'sent', 'queued', or 'failed'.
 *
 * @param int    $id      Inbox item (an incoming email).
 * @param string $subject Subject.
 * @param string $text    Plain text.
 * @return string
 */
function aw_reply_send( $id, $subject, $text ) {
	global $wpdb;
	$id = aw_reply_email_item( $id );
	if ( ! $id || '' === trim( $text ) ) {
		return 'failed';
	}
	$to   = strtolower( trim( (string) get_post_meta( $id, 'aw_m_email', true ) ) );
	$from = aw_crm_message_to( $id );
	$mid  = trim( (string) get_post_meta( $id, 'aw_m_message_id', true ), " \t<>" );
	$post = get_post( $id );
	$name = trim( (string) get_post_meta( $id, 'aw_m_name', true ) );
	$orig = trim( (string) $post->post_content );
	$body = rtrim( $text ) . "\n";
	if ( '' !== $orig ) {
		$body .= "\nOn " . mysql2date( 'j M Y, g:i a', $post->post_date ) . ', ' . ( '' !== $name ? $name : $to ) . " wrote:\n" . preg_replace( '/^/m', '> ', $orig ) . "\n";
	}
	$headers = array(
		'Content-Type: text/plain; charset=UTF-8',
		'Reply-To: Animal Writes <' . $from . '>',
		'X-AW-Priority: 0',
	);
	if ( '' !== $mid ) {
		$headers[] = 'In-Reply-To: <' . $mid . '>';
		$headers[] = 'References: <' . $mid . '>';
	}
	$table  = function_exists( 'aw_mail_table' ) ? aw_mail_table() : '';
	$before = $table ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'queued'" ) : 0; // phpcs:ignore
	if ( ! wp_mail( $to, $subject, $body, $headers ) ) {
		return 'failed';
	}
	$queued = $table && (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'queued'" ) > $before; // phpcs:ignore
	$user   = wp_get_current_user();
	$by     = $user && $user->exists() ? $user->display_name : 'Animal Writes';
	$now    = current_time( 'mysql' );
	$note   = 0;
	$c      = function_exists( 'aw_crm_on' ) && aw_crm_on() ? FluentCrmApi( 'contacts' )->getContact( $to ) : null;
	if ( $c ) {
		$head = array(
			'To'                    => $to,
			'In reply to'           => aw_reply_subject_of( $id ),
			'Their answer comes to' => $from,
			'Sent'                  => mysql2date( 'j M Y, g:i a', $now ) . ( $queued ? ' (queued: daily email limit; goes out first when there is room)' : '' ),
			'Sent by'               => $by,
		);
		$html = '';
		foreach ( $head as $k => $v ) {
			$html .= '<strong>' . esc_html( $k ) . ':</strong> ' . esc_html( $v ) . '<br>';
		}
		$row  = \FluentCrm\App\Models\SubscriberNote::create(
			array(
				'subscriber_id' => (int) $c->id,
				'type'          => 'email',
				'title'         => 'Reply sent: ' . $subject,
				'description'   => '<p>' . $html . '</p>' . wpautop( esc_html( $text ) ) . '<p><a href="' . esc_url( aw_reply_url( $id ) ) . '">Reply again</a></p>',
				'created_at'    => $now,
			)
		);
		$note = $row ? (int) $row->id : 0;
	}
	add_post_meta( $id, 'aw_m_reply', array( 'at' => $now, 'subject' => $subject, 'by' => $by, 'queued' => $queued, 'note' => $note ) );
	return $queued ? 'queued' : 'sent';
}

add_action(
	'admin_post_aw_reply_send',
	function () {
		$id = (int) ( $_POST['message'] ?? 0 );
		if ( ! aw_reply_can() || ! check_admin_referer( 'aw_reply_' . $id, 'aw_reply_nonce' ) ) {
			wp_die( 'You cannot reply to emails.', 403 );
		}
		$subject = sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) );
		$text    = sanitize_textarea_field( wp_unslash( $_POST['text'] ?? '' ) );
		$subject = '' !== $subject ? $subject : aw_reply_re( aw_reply_subject_of( $id ) );
		$res     = '' === trim( $text ) ? 'empty' : aw_reply_send( $id, $subject, $text );
		wp_safe_redirect( add_query_arg( 'aw_reply', $res, aw_reply_url( $id ) ) );
		exit;
	}
);

// The email's own screen (Inbox → Contact messages → the email): a Reply link at the top.
add_action(
	'edit_form_top',
	function ( $post ) {
		if ( aw_reply_email_item( $post->ID ) && aw_reply_can() ) {
			echo '<p><a class="button button-primary" href="' . esc_url( aw_reply_url( $post->ID ) ) . '">Reply to this email</a></p>';
		}
	}
);
