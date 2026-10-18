<?php
/**
 * Campaign letters: anonymous statistics and the opt-in at send (owner, 17 Oct 2026; Privacy Policy section 7,
 * "Campaign actions and sign-ups"). The letter itself still goes from the visitor's own email; we never receive it.
 *
 * - Anonymous statistics: each time a letter is sent from the letter tool ([aw_letter_tool], campaign.js), one row in
 *   {prefix}aw_camp_letters: campaign, the city as typed (trimmed, title case), the grounds ticked, the route (gmail,
 *   outlook, mailto, copy), the device type (phone, tablet, desktop, from the screen width) and the date. No name,
 *   email or IP address is read or kept. Kept to report on campaigns.
 * - Opt-in at send: a box under the letter preview, ticked by default (owner, 18 Oct 2026) ("Email me updates ..."). Ticked, the email becomes
 *   required, and sending signs the person up through the newsletter path (aw_newsletter_join: the site's list and
 *   FluentCRM's Newsletter, tag campaign-<slug>), with a note on the contact holding the name, city, grounds, date and
 *   the wording agreed to. Unticked, nothing but the anonymous row is kept. "Remove my data" deletes the contact.
 * - WP Admin → Campaigns → Letter report: totals, by city, by ground, by route, by device and by day, with a CSV export
 *   of the anonymous rows.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_CL_DB_VERSION = '1';
const AW_CL_OPTIN      = 'Email me updates on this campaign and future Animal Writes campaigns. I can unsubscribe at any time.';
const AW_CL_CONSENT    = 'v2-campaign-optin-preticked'; // the wording above; the box is ticked by default (owner, 18 Oct 2026)
const AW_CL_ROUTES     = array( 'gmail' => 'Gmail', 'outlook' => 'Outlook', 'mailto' => 'Email app', 'copy' => 'Copied' );
const AW_CL_DEVICES    = array( 'phone' => 'Phone', 'tablet' => 'Tablet', 'desktop' => 'Desktop' );

function aw_cl_table() {
	global $wpdb;
	return $wpdb->prefix . 'aw_camp_letters';
}

add_action(
	'init',
	function () {
		if ( AW_CL_DB_VERSION === get_option( 'aw_camp_letters_db' ) ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta(
			'CREATE TABLE ' . aw_cl_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			campaign_id bigint(20) unsigned NOT NULL,
			city varchar(60) NOT NULL DEFAULT '',
			grounds varchar(120) NOT NULL DEFAULT '',
			route varchar(20) NOT NULL DEFAULT '',
			device varchar(10) NOT NULL DEFAULT '',
			sent_on date NOT NULL,
			PRIMARY KEY  (id),
			KEY campaign (campaign_id, sent_on)
			) {$wpdb->get_charset_collate()};"
		);
		update_option( 'aw_camp_letters_db', AW_CL_DB_VERSION, false );
	},
	5
);

/** A city as typed, tidied: trimmed, single spaces, title case, at most 60 characters. */
function aw_cl_city( $city ) {
	$city = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $city ) ) );
	return mb_substr( mb_convert_case( mb_strtolower( $city ), MB_CASE_TITLE ), 0, 60 );
}

/** Ground numbers as "1,2,6" (1-99, unique, sorted). */
function aw_cl_grounds( $grounds ) {
	$n = array_unique( array_filter( array_map( 'intval', is_array( $grounds ) ? $grounds : explode( ',', (string) $grounds ) ), fn( $g ) => $g > 0 && $g < 100 ) );
	sort( $n );
	return implode( ',', $n );
}

/** The campaign's grounds as written on its page (the list with class aw-letter-grounds): number => short label. */
function aw_cl_ground_labels( $campaign ) {
	$html = (string) get_post_field( 'post_content', $campaign );
	if ( ! preg_match( '#<ol class="wp-block-list aw-letter-grounds">(.*?)</ol>#s', $html, $m ) ) {
		return array();
	}
	preg_match_all( '#<li>(.*?)</li>#s', $m[1], $li );
	$out = array();
	foreach ( $li[1] as $i => $t ) {
		$out[ $i + 1 ] = preg_match( '#<strong>(.*?)</strong>#s', $t, $s ) ? rtrim( wp_strip_all_tags( $s[1] ), ': ' ) : wp_trim_words( wp_strip_all_tags( $t ), 8 );
	}
	return $out;
}

/* ---------- REST: a letter sent (anonymous), and the opt-in ---------- */
add_action(
	'rest_api_init',
	function () {
		$open = function ( $id ) {
			$id = aw_camp_id( (int) $id );
			return $id && 'publish' === get_post_status( $id ) && aw_camp_is_open( $id ) ? $id : 0;
		};
		register_rest_route(
			'aw/v1',
			'/campaign/(?P<id>\d+)/letter',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => function ( WP_REST_Request $req ) use ( $open ) {
					global $wpdb;
					$id = $open( $req['id'] );
					if ( ! $id ) {
						return new WP_Error( 'aw_closed', 'Not open', array( 'status' => 404 ) );
					}
					$route  = sanitize_key( (string) $req['route'] );
					$device = sanitize_key( (string) $req['device'] );
					$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						aw_cl_table(),
						array(
							'campaign_id' => $id,
							'city'        => aw_cl_city( $req['city'] ),
							'grounds'     => aw_cl_grounds( $req['grounds'] ),
							'route'       => isset( AW_CL_ROUTES[ $route ] ) ? $route : '',
							'device'      => isset( AW_CL_DEVICES[ $device ] ) ? $device : '',
							'sent_on'     => wp_date( 'Y-m-d', null, new DateTimeZone( 'Asia/Kolkata' ) ),
						)
					);
					return array( 'ok' => true );
				},
			)
		);
		register_rest_route(
			'aw/v1',
			'/campaign/(?P<id>\d+)/optin',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => function ( WP_REST_Request $req ) use ( $open ) {
					$id    = $open( $req['id'] );
					$email = sanitize_email( (string) $req['email'] );
					if ( ! $id ) {
						return new WP_Error( 'aw_closed', 'Not open', array( 'status' => 404 ) );
					}
					if ( ! is_email( $email ) || '1' !== (string) $req['consent'] || ! function_exists( 'aw_newsletter_join' ) ) {
						return new WP_Error( 'aw_optin', 'Check the email address.', array( 'status' => 400 ) );
					}
					$name    = mb_substr( sanitize_text_field( (string) $req['name'] ), 0, 80 );
					$city    = aw_cl_city( $req['city'] );
					$grounds = aw_cl_grounds( $req['grounds'] );
					$slug    = get_post_field( 'post_name', $id );
					aw_newsletter_join( $email, $name, 'site:campaign:' . $slug, AW_CL_CONSENT, array( 'city' => $city ), array( 'newsletter', 'campaign-' . $slug ) );
					if ( function_exists( 'aw_crm_on' ) && aw_crm_on() ) { // the details the Privacy Policy lists, on the contact
						$c = FluentCrmApi( 'contacts' )->getContact( strtolower( $email ) );
						if ( $c ) {
							\FluentCrm\App\Models\SubscriberNote::create(
								array(
									'subscriber_id' => (int) $c->id,
									'type'          => 'note',
									'title'         => 'Campaign sign-up: ' . get_the_title( $id ),
									'description'   => '<p>Name: ' . esc_html( $name ) . '<br>City: ' . esc_html( $city ) . '<br>Grounds: ' . esc_html( $grounds ) . '<br>Date: ' . esc_html( wp_date( 'j M Y, H:i' ) ) . '</p><p>Agreed to: ' . esc_html( AW_CL_OPTIN ) . ' (' . AW_CL_CONSENT . ')</p>',
									'created_at'    => current_time( 'mysql' ),
								)
							);
						}
					}
					return array( 'ok' => true );
				},
			)
		);
	}
);

/* ---------- WP Admin: Campaigns → Letter report ---------- */
add_action(
	'admin_menu',
	fn() => add_submenu_page( 'edit.php?post_type=aw_campaign', 'Letter report', 'Letter report', 'edit_posts', 'aw-letter-report', 'aw_cl_report_page' )
);

/** Campaigns with letters, newest first: [id => title]. */
function aw_cl_campaigns() {
	global $wpdb;
	$out = array();
	foreach ( (array) $wpdb->get_col( 'SELECT campaign_id FROM ' . aw_cl_table() . ' GROUP BY campaign_id ORDER BY MAX(id) DESC' ) as $cid ) { // phpcs:ignore
		$out[ (int) $cid ] = get_the_title( (int) $cid );
	}
	return $out;
}

function aw_cl_report_page() {
	global $wpdb;
	$all = aw_cl_campaigns();
	$cid = isset( $_GET['campaign'] ) ? absint( $_GET['campaign'] ) : (int) array_key_first( $all ); // phpcs:ignore WordPress.Security.NonceVerification
	$t   = aw_cl_table();
	echo '<div class="wrap"><h1>Letter report</h1>';
	echo '<p class="description">One row each time a letter is sent from a campaign\'s letter tool: the city as typed, the grounds, how it was sent, the device type and the date. No names, emails or IP addresses. "Letters sent" on the campaign counts the "Yes, I sent it" clicks.</p>';
	if ( ! $all ) {
		echo '<p>No letters recorded yet.</p></div>';
		return;
	}
	echo '<form method="get"><input type="hidden" name="post_type" value="aw_campaign"><input type="hidden" name="page" value="aw-letter-report"><label for="aw-cl-c">Campaign </label><select id="aw-cl-c" name="campaign">';
	foreach ( $all as $id => $title ) {
		echo '<option value="' . (int) $id . '"' . selected( $cid, $id, false ) . '>' . esc_html( $title ) . '</option>';
	}
	echo '</select> ' . get_submit_button( 'Show', 'secondary', '', false ) . ' <a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=aw_cl_csv&campaign=' . $cid ), 'aw_cl_csv' ) ) . '">Export CSV</a></form>';
	$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE campaign_id = %d", $cid ) ); // phpcs:ignore
	echo '<h2>' . esc_html( sprintf( _n( '%s letter sent', '%s letters sent', $total, 'animal-writes-core' ), number_format_i18n( $total ) ) ) . '</h2>';
	$table = function ( $title, $rows, $head ) {
		echo '<h2>' . esc_html( $title ) . '</h2><table class="widefat striped" style="max-width:48rem"><thead><tr><th>' . esc_html( $head ) . '</th><th style="width:8rem">Letters</th></tr></thead><tbody>';
		foreach ( $rows as $label => $n ) {
			echo '<tr><td>' . esc_html( '' === (string) $label ? '(not given)' : $label ) . '</td><td>' . esc_html( number_format_i18n( $n ) ) . '</td></tr>';
		}
		echo $rows ? '' : '<tr><td colspan="2">None yet.</td></tr>';
		echo '</tbody></table>';
	};
	$pairs = fn( $sql ) => wp_list_pluck( (array) $wpdb->get_results( $wpdb->prepare( $sql, $cid ) ), 'n', 'k' ); // phpcs:ignore
	$day   = $pairs( "SELECT sent_on AS k, COUNT(*) AS n FROM {$t} WHERE campaign_id = %d GROUP BY sent_on ORDER BY sent_on DESC" );
	$route = array();
	foreach ( $pairs( "SELECT route AS k, COUNT(*) AS n FROM {$t} WHERE campaign_id = %d GROUP BY route ORDER BY n DESC" ) as $k => $n ) {
		$route[ AW_CL_ROUTES[ $k ] ?? $k ] = $n;
	}
	$device = array();
	foreach ( $pairs( "SELECT device AS k, COUNT(*) AS n FROM {$t} WHERE campaign_id = %d GROUP BY device ORDER BY n DESC" ) as $k => $n ) {
		$device[ AW_CL_DEVICES[ $k ] ?? $k ] = $n;
	}
	$labels = aw_cl_ground_labels( $cid );
	$ground = array();
	foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT grounds FROM {$t} WHERE campaign_id = %d", $cid ) ) as $g ) { // phpcs:ignore
		foreach ( array_filter( explode( ',', (string) $g ) ) as $n ) {
			$ground[ (int) $n ] = ( $ground[ (int) $n ] ?? 0 ) + 1;
		}
	}
	arsort( $ground );
	$ground_rows = array();
	foreach ( $ground as $n => $c ) {
		$ground_rows[ $n . '. ' . ( $labels[ $n ] ?? '' ) ] = $c;
	}
	$table( 'By ground', $ground_rows, 'Ground' );
	$table( 'By city (top 50)', $pairs( "SELECT city AS k, COUNT(*) AS n FROM {$t} WHERE campaign_id = %d GROUP BY city ORDER BY n DESC LIMIT 50" ), 'City' );
	$table( 'How it was sent', $route, 'Route' );
	$table( 'Device', $device, 'Device' );
	$table( 'By day (IST)', $day, 'Date' );
	echo '</div>';
}

add_action(
	'admin_post_aw_cl_csv',
	function () {
		global $wpdb;
		check_admin_referer( 'aw_cl_csv' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( 'Not allowed' );
		}
		$cid  = absint( $_GET['campaign'] ?? 0 );
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT sent_on, city, grounds, route, device FROM ' . aw_cl_table() . ' WHERE campaign_id = %d ORDER BY id', $cid ), ARRAY_A ); // phpcs:ignore
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=letters-' . sanitize_file_name( get_post_field( 'post_name', $cid ) ) . '-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'date', 'city', 'grounds', 'route', 'device' ) );
		foreach ( $rows as $r ) {
			fputcsv( $out, array_map( fn( $v ) => preg_match( '/^[=+\-@]/', (string) $v ) ? "'" . $v : $v, $r ) ); // no spreadsheet formulas from typed cities
		}
		exit;
	}
);
