<?php
/**
 * Newsletter growth, consent only (owner, 9 Oct 2026; DPDP: consent per purpose).
 *
 * - Contact and Listing claim forms carry an unticked box with the footer form's wording. Ticked → the
 *   person goes onto the newsletter list as "active" with the form as source (16 Oct 2026). Unticked → nothing is added. (The Report form asks for no email, so it has no box.)
 * - Volunteers get the same unticked box (owner, 20 Oct 2026; was automatic from 16 Oct; volunteers.php). Claimants, contact senders and incoming emails are never added on their own. Newsletter → Not subscribed
 *   lists them (name, email, source, date) with a CSV export, for a one-time opt-in invite sent outside WordPress.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_NL_OPTIN_LABEL = 'Email me Animal Writes updates. I can unsubscribe at any time.';

/** The unticked opt-in box for a form. */
function aw_nl_optin_box( $form ) {
	return aw_field( $form, array( 'name' => 'nl_optin', 'label' => AW_NL_OPTIN_LABEL, 'type' => 'checkbox' ) );
}

/** After a form succeeds: only when the box was ticked, add to the newsletter (site list active, FluentCRM subscribed;
 *  owner, 16 Oct 2026: no double opt-in; the form's own email is the only one sent). */
function aw_nl_optin( $email, $name, $source, $ticked = null, $consent = AW_NL_CONSENT ) {
	$ticked = null === $ticked ? ! empty( $_POST['nl_optin'] ) : (bool) $ticked; // phpcs:ignore WordPress.Security.NonceVerification -- the site's own forms post it; Fluent Forms passes it
	if ( ! $ticked || ! is_email( $email ) || ! function_exists( 'aw_newsletter_join' ) ) {
		return;
	}
	aw_newsletter_join( $email, $name, 'site:' . $source, $consent, array(), array( 'newsletter' ) ); // site list and FluentCRM
}

/** Everyone we hold an email for who is not on the newsletter (active or pending). */
function aw_nl_not_subscribed() {
	global $wpdb;
	$on   = array_flip( (array) $wpdb->get_col( 'SELECT email FROM ' . aw_nl_table() . " WHERE status IN ('active','pending')" ) ); // phpcs:ignore
	$rows = array();
	$add  = function ( $email, $name, $source, $date ) use ( &$rows, $on ) {
		$email = strtolower( trim( (string) $email ) );
		if ( ! is_email( $email ) || isset( $on[ $email ] ) || isset( $rows[ $email ] ) || str_ends_with( $email, '@animalwrites.in' ) ) {
			return;
		}
		$rows[ $email ] = array( 'name' => (string) $name, 'email' => $email, 'source' => $source, 'date' => substr( (string) $date, 0, 10 ) );
	};
	if ( function_exists( 'aw_vol_table' ) ) {
		foreach ( (array) $wpdb->get_results( 'SELECT name, email, source, created_at FROM ' . aw_vol_table() . " WHERE status <> 'deleted' ORDER BY created_at DESC", ARRAY_A ) as $r ) { // phpcs:ignore
			$add( $r['email'], $r['name'], 'volunteer (' . $r['source'] . ')', $r['created_at'] );
		}
	}
	foreach ( get_posts( array( 'post_type' => array( 'aw_message', 'aw_claim' ), 'post_status' => 'any', 'numberposts' => -1 ) ) as $p ) {
		$claim = 'aw_claim' === $p->post_type;
		$add(
			get_post_meta( $p->ID, $claim ? 'aw_c_email' : 'aw_m_email', true ),
			get_post_meta( $p->ID, $claim ? 'aw_c_name' : 'aw_m_name', true ),
			$claim ? 'listing claim' : ( 'Email' === get_post_meta( $p->ID, 'aw_m_tag', true ) ? 'email to ' . get_post_meta( $p->ID, 'aw_m_to', true ) : 'contact form' ),
			$p->post_date
		);
	}
	return array_values( $rows );
}

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'aw-newsletter', 'Not subscribed', 'Not subscribed', AW_VOL_CAP, 'aw-not-subscribed', 'aw_nl_not_subscribed_page' );
	},
	30
);

function aw_nl_not_subscribed_page() {
	$rows = aw_nl_not_subscribed();
	$csv  = wp_nonce_url( admin_url( 'admin-post.php?action=aw_not_subscribed_csv' ), 'aw_not_subscribed_csv' );
	echo '<div class="wrap"><h1>Not subscribed</h1><p>People we have an email for who have not joined the newsletter. They are never added automatically; send them a one-time invite from your own mail if you wish.</p>';
	echo '<p><a class="button button-primary" href="' . esc_url( $csv ) . '">Download CSV (' . (int) count( $rows ) . ')</a></p>';
	echo '<table class="widefat striped"><thead><tr><th>Name</th><th>Email</th><th>Source</th><th>Date</th></tr></thead><tbody>';
	foreach ( array_slice( $rows, 0, 500 ) as $r ) {
		echo '<tr><td>' . esc_html( $r['name'] ) . '</td><td>' . esc_html( $r['email'] ) . '</td><td>' . esc_html( $r['source'] ) . '</td><td>' . esc_html( $r['date'] ) . '</td></tr>';
	}
	echo '</tbody></table></div>';
}

add_action(
	'admin_post_aw_not_subscribed_csv',
	function () {
		if ( ! current_user_can( AW_VOL_CAP ) || ! check_admin_referer( 'aw_not_subscribed_csv' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=not-subscribed-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'name', 'email', 'source', 'date' ) );
		foreach ( aw_nl_not_subscribed() as $r ) {
			fputcsv( $out, array( $r['name'], $r['email'], $r['source'], $r['date'] ) );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}
);
