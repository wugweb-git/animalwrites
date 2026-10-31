<?php
/**
 * Newsletter: the old sign-up list and its confirm and unsubscribe links. The sign-up form is Fluent Forms
 * (newsletter-fluentforms.php) and the list is FluentCRM; sending happens there, not here (S2.4).
 *
 * Table {prefix}aw_subscribers: the backup of the list from before FluentCRM (no admin screen since 25 Oct 2026; read
 * it with WP-CLI). Nothing creates or upgrades it any more (owner, 25 Oct 2026: the site's own form and its handler were
 * removed); FluentCRM's unsubscribe marks a row
 * (fluentcrm.php). Statuses: pending, invited, active, unsubscribed. Emails sent before carry a confirm and an
 * unsubscribe link with the row's token; those links keep working (below).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_NL_CONSENT = 'v2-newsletter-1';

function aw_nl_table() {
	global $wpdb;
	return $wpdb->prefix . 'aw_subscribers';
}

function aw_nl_get( $field, $value ) {
	global $wpdb;
	$t = aw_nl_table();
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE {$field} = %s", $value ), ARRAY_A ); // phpcs:ignore
}

function aw_nl_link( $do, $token ) {
	return add_query_arg( array( 'aw_nl' => $do, 't' => $token ), home_url( '/' ) );
}

/** What a confirm or unsubscribe link did, shown where the sign-up sits (?aw_done=, set below), or ''. */
function aw_nl_link_note() {
	$msgs = array(
		'nl_confirmed' => 'Confirmed. You are on the list.',
		'nl_unsub'     => 'You are unsubscribed and will not get these emails again.',
		'nl_badlink'   => 'This link has expired or was already used.',
	);
	$done = sanitize_key( wp_unslash( $_GET['aw_done'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	return isset( $msgs[ $done ] ) ? '<div class="kit-form-done" role="status" tabindex="-1"><p class="aw-form-done-title">' . esc_html( $msgs[ $done ] ) . '</p></div>' : '';
}

// Confirm and unsubscribe links (old emails carry them; owner, 25 Oct 2026: keep).
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
			// A sign-up confirmed from an old email joins the FluentCRM list too, so it is managed with everyone else.
			if ( function_exists( 'aw_crm_on' ) && aw_crm_on() ) {
				aw_crm_subscribe( $row['email'], (string) ( $row['name'] ?? '' ), array( 'source' => (string) ( $row['source'] ?? '' ) ), array( 'newsletter' ) );
			}
			$note = 'nl_confirmed';
		} elseif ( $row && 'unsubscribe' === $do ) {
			$wpdb->update( aw_nl_table(), array( 'status' => 'unsubscribed', 'updated_at' => $now ), array( 'id' => (int) $row['id'] ) ); // phpcs:ignore
			if ( function_exists( 'aw_crm_on' ) && aw_crm_on() ) { // and in FluentCRM, where the list is kept
				$c = FluentCrmApi( 'contacts' )->getContact( $row['email'] );
				if ( $c && 'unsubscribed' !== $c->status ) {
					$c->updateStatus( 'unsubscribed' ); // FluentCRM's own status change, so its unsubscribe hooks run
				}
			}
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
