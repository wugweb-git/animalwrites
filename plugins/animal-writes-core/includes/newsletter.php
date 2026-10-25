<?php
/**
 * Newsletter: sign-up with email confirmation and the sign-up list (WP Admin > Audience > Newsletter, CSV export).
 * Sending happens in an external mailing service, not in WordPress (S2.4).
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

const AW_NL_DB_VERSION = '2'; // 2: source holds the page a sign-up came from
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
			source varchar(120) NOT NULL,
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
		return '<div class="kit-form-done" role="status" tabindex="-1"><p class="aw-form-done-title">' . esc_html( $msgs[ $done ][0] ) . '</p>' . ( $msgs[ $done ][1] ? '<p>' . esc_html( $msgs[ $done ][1] ) . '</p>' : '' ) . '</div>';
	}
	$form  = 'aw_newsletter';
	$html  = aw_form_open( $form, array( 'class' => 'kit-form kit-form--on-dark aw-newsletter-form', 'data-altcha' => 'invisible' ) );
	$html .= kits_dict_field( $form, 'email' );
	$html .= kits_dict_field( $form, 'name' );
	$html .= '<div class="kit-nl-submit"><button type="submit" class="kit-btn">Subscribe</button></div>';
	$html .= kits_dict_field( $form, 'consent', array( 'label' => 'Email me Animal Writes updates. I can unsubscribe at any time.', 'messages' => array( 'required' => 'Tick the box to subscribe' ) ) );
	$html .= '</form>';
	return $html;
}
add_shortcode( 'aw_newsletter', 'aw_newsletter_form' );

function aw_newsletter_submit() {
	$form = 'aw_newsletter';
	$back = aw_form_back( '/' );
	$done = kits_thanks_url( 'newsletter' );
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
		$text = sprintf( 'Updates go to %s.', $v['email'] );
		aw_form_success( kits_thanks_url( 'newsletter', array(), 'You\'re already subscribed', $text ), 'You\'re already subscribed', $text );
	}
	$row = aw_nl_add( $v['email'], $v['name'], 'site:' . kits_submitted_source(), 'pending' ); // where they signed up (S5.8)
	if ( ! $row ) {
		aw_form_fail( $form, array(), $v, $back, 'newsletter', aw_form_generic_error( $form ) );
	}
	if ( 'pending' === $row['status'] ) { // double opt-in: confirm by email
		wp_mail(
			$row['email'],
			'Confirm your subscription to ' . get_bloginfo( 'name' ),
			"Click this link to confirm that you want Animal Writes updates:\n" . aw_nl_link( 'confirm', $row['token'] ) . "\n\nIf you did not ask for this, ignore this email and you will not hear from us."
		);
	}
	aw_form_count( $rl );
	$text = sprintf( 'We\'ve sent a link to %s. Open it to confirm your subscription.', $v['email'] );
	aw_form_success( kits_thanks_url( 'newsletter', array(), 'Check your inbox', $text ), 'Check your inbox', $text );
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

// Newsletters are sent from an external CRM (S2.4, 8 Oct 2026): WordPress keeps the sign-up list only. The old screen's
// last draft and job stay in the options aw_nl_draft, aw_nl_job; its aw_nl_batch job is long gone (none scheduled on
// live, 25 Oct 2026), so the check that cleared it on every load was removed (audit item 17).

/* ---------- Admin: WP Admin > Newsletter ---------- */

add_action(
	'admin_menu',
	function () {
		add_menu_page( 'Newsletter sign-ups', 'Newsletter', AW_VOL_CAP, 'aw-newsletter', 'aw_nl_admin_page', 'dashicons-email-alt', 27 );
	}
);

function aw_nl_admin_page() {
	global $wpdb;
	$t      = aw_nl_table();
	$counts = wp_list_pluck( $wpdb->get_results( "SELECT status, COUNT(*) n FROM {$t} GROUP BY status", ARRAY_A ), 'n', 'status' ); // phpcs:ignore
	$page   = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT email, name, source, status, created_at FROM {$t} ORDER BY id DESC LIMIT 50 OFFSET %d", ( $page - 1 ) * 50 ), ARRAY_A ); // phpcs:ignore
	$total  = (int) array_sum( $counts );
	echo '<div class="wrap"><h1>Newsletter sign-ups</h1>';
	echo '<p>Confirmed: <strong>' . (int) ( $counts['active'] ?? 0 ) . '</strong> · Waiting to confirm: ' . (int) ( $counts['pending'] ?? 0 )
		. ' · Invited, not yet confirmed: ' . (int) ( $counts['invited'] ?? 0 ) . ' · Unsubscribed: ' . (int) ( $counts['unsubscribed'] ?? 0 ) . '</p>';
	echo '<p class="description">Newsletters are sent from the external mailing service, not from WordPress.</p>';
	$export = wp_nonce_url( admin_url( 'admin-post.php?action=aw_nl_export' ), 'aw_nl_export' );
	echo '<p><a class="button" href="' . esc_url( $export ) . '">Export sign-ups (CSV)</a></p>';
	$cells = array();
	foreach ( $rows as $r ) {
		$cells[] = array( 'email' => esc_html( $r['email'] ), 'name' => esc_html( $r['name'] ), 'source' => esc_html( $r['source'] ), 'status' => esc_html( $r['status'] ), 'created' => esc_html( substr( (string) $r['created_at'], 0, 10 ) ) );
	}
	aw_core_simple_table( 'sign-ups', array( 'email' => 'Email', 'name' => 'Name', 'source' => 'Source', 'status' => 'Status', 'created' => 'Signed up' ), $cells, array( 'per_page' => 50, 'total' => $total, 'empty' => 'No sign-ups yet.' ) );
	echo '</div>';
}

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
