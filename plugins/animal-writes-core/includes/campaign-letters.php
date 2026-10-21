<?php
/**
 * Campaign letters: anonymous statistics and the opt-in at send (owner, 17 Oct 2026; Privacy Policy section 7,
 * "Campaign actions and sign-ups"). The letter itself still goes from the visitor's own email; we never receive it.
 *
 * - Anonymous statistics: each time a letter is sent from the letter tool ([aw_letter_tool], campaign.js), one row in
 *   {prefix}aw_camp_letters: campaign, the city as typed (trimmed, title case), the grounds ticked, the route (gmail,
 *   outlook, mailto, copy), the device type (phone, tablet, desktop, from the screen width), the date, and the
 *   newsletter box and the WhatsApp tick as 1/0 (owner, 21 Oct 2026). No name, email or IP address is read or kept.
 *   Kept to report on campaigns.
 * - At send: name, city and email are required, WhatsApp is optional with its own tick. Every sender joins the
 *   newsletter and the volunteers in FluentCRM (Newsletter list, tags newsletter, volunteer, campaign-<slug>), whatever
 *   the box says (owner, 18 Oct 2026), through FluentCRM's own API (aw_crm_campaign_signer), with the box as 1/0, the
 *   WhatsApp number and tick, and a reference (AW-L-<n>) shown to them after sending.
 * - WP Admin → Campaigns → Letter report: totals, by city, by ground, by route, by device and by day, with a CSV export
 *   of the anonymous rows.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_CL_DB_VERSION = '3'; // 2: nl_optin and wa_optin (owner, 21 Oct 2026); 3: send_id, one row per letter from either call
const AW_CL_OPTIN      = 'Email me updates on this campaign and future Animal Writes campaigns, and add me as a volunteer. I can unsubscribe at any time.';
const AW_CL_CONSENT    = 'v3-campaign-optin-volunteer-preticked'; // the wording above; ticked by default, newsletter and volunteers (owner, 18 Oct 2026)
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
			nl_optin tinyint(1) NULL,
			wa_optin tinyint(1) NULL,
			send_id char(16) NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY send_id (send_id),
			KEY campaign (campaign_id, sent_on)
			) {$wpdb->get_charset_collate()};"
		);
		update_option( 'aw_camp_letters_db', AW_CL_DB_VERSION, false );
	},
	5
);

/** The next signer reference number (AW-L-<n>), one series for every campaign. */
function aw_cl_next_ref() {
	global $wpdb;
	add_option( 'aw_cl_ref', 0, '', false );
	$wpdb->query( "UPDATE {$wpdb->options} SET option_value = option_value + 1 WHERE option_name = 'aw_cl_ref'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	wp_cache_delete( 'aw_cl_ref', 'options' );
	return (int) $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'aw_cl_ref'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/** A box sent by campaign.js ('1' / '0') as 1 or 0. */
function aw_cl_tick( $v ) {
	return '1' === (string) $v ? 1 : 0;
}

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

/** A random id campaign.js makes for one letter (16 hex characters), or '' when it sent none (an older cached script). */
function aw_cl_send_id( $v ) {
	return preg_match( '/^[a-f0-9]{16}$/', (string) $v ) ? (string) $v : '';
}

/**
 * The anonymous row for one letter. Both calls (letter and optin) carry it, so the row is saved when either arrives; the
 * send_id keeps it to one row (INSERT IGNORE on its unique key). The send_id is never stored with the person.
 */
function aw_cl_record_letter( $id, $req ) {
	global $wpdb;
	$route  = sanitize_key( (string) $req['route'] );
	$device = sanitize_key( (string) $req['device'] );
	$send   = aw_cl_send_id( $req['send_id'] );
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			'INSERT IGNORE INTO ' . aw_cl_table() . ' (campaign_id, city, grounds, route, device, sent_on, nl_optin, wa_optin, send_id) VALUES (%d, %s, %s, %s, %s, %s, %d, %d, ' . ( $send ? '%s' : 'NULL' ) . ')',
			array_merge(
				array(
					$id,
					aw_cl_city( $req['city'] ),
					aw_cl_grounds( $req['grounds'] ),
					isset( AW_CL_ROUTES[ $route ] ) ? $route : '',
					isset( AW_CL_DEVICES[ $device ] ) ? $device : '',
					wp_date( 'Y-m-d', null, new DateTimeZone( 'Asia/Kolkata' ) ),
					aw_cl_tick( $req['nl_optin'] ),
					aw_cl_tick( $req['wa_optin'] ),
				),
				$send ? array( $send ) : array()
			)
		)
	);
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
					$id = $open( $req['id'] );
					if ( ! $id ) {
						return new WP_Error( 'aw_closed', 'Not open', array( 'status' => 404 ) );
					}
					aw_cl_record_letter( $id, $req );
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
					$raw = function_exists( 'aw_raw_save' ) ? aw_raw_save( 'campaign-old', (int) $req['id'], $req->get_params() ) : 0; // the raw copy, before any check (campaign-form.php)
					$id    = $open( $req['id'] );
					$email = sanitize_email( (string) $req['email'] );
					if ( ! $id ) {
						return new WP_Error( 'aw_closed', 'Not open', array( 'status' => 404 ) );
					}
					if ( ! is_email( $email ) || ! function_exists( 'aw_crm_campaign_signer' ) ) {
						return new WP_Error( 'aw_optin', 'Check the email address.', array( 'status' => 400 ) );
					}
					$name    = mb_substr( sanitize_text_field( (string) $req['name'] ), 0, 80 );
					$city    = aw_cl_city( $req['city'] );
					$slug    = get_post_field( 'post_name', $id );
					// Every sender joins the newsletter and the volunteers, box ticked or not (owner, 18 Oct 2026); the box is
					// recorded as 1/0 (it was dropped before 21 Oct 2026). FluentCRM's own API; no team email.
					$wa  = trim( sanitize_text_field( (string) $req['whatsapp'] ) );
					$wa  = '' !== $wa && kits_clean_phone( $wa ) ? kits_clean_phone( $wa ) : substr( $wa, 0, 20 );
					// the letter's row too, in case its own call was lost (only when this script sent the letter's fields)
					$send = aw_cl_send_id( $req['send_id'] );
					if ( $send && '' !== (string) $req['route'] ) {
						aw_cl_record_letter( $id, $req );
					}
					// a retried call (the first answer lost) gets the same reference, not a new one
					$ref = $send ? get_transient( 'aw_cl_s_' . $send ) : '';
					if ( $ref ) {
						return array( 'ok' => true, 'ref' => $ref );
					}
					$ref = 'AW-L-' . aw_cl_next_ref();
					if ( $send ) {
						set_transient( 'aw_cl_s_' . $send, $ref, DAY_IN_SECONDS );
					}
					aw_crm_campaign_signer( $email, $name, $city, $slug, 1 === aw_cl_tick( $req['consent'] ), $wa, 1 === aw_cl_tick( $req['wa_optin'] ), $ref, AW_CL_CONSENT );
					if ( $raw ) {
						aw_raw_mark( $raw, 'saved' );
					}
					return array( 'ok' => true, 'ref' => $ref );
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

/**
 * Dashboard box "Campaign letters" (owner, 19 Oct 2026): for the newest campaign with letters, the total, the last
 * 7 days (IST), the top 3 grounds and cities, and a link to the full Letter report.
 */
add_action(
	'wp_dashboard_setup',
	function () {
		if ( current_user_can( 'edit_posts' ) && aw_cl_campaigns() ) {
			wp_add_dashboard_widget( 'aw_cl_widget', 'Campaign letters', 'aw_cl_widget' );
		}
	}
);

function aw_cl_widget() {
	global $wpdb;
	$all   = aw_cl_campaigns();
	$cid   = (int) array_key_first( $all );
	$t     = aw_cl_table();
	$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE campaign_id = %d", $cid ) ); // phpcs:ignore
	$week  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE campaign_id = %d AND sent_on >= %s", $cid, wp_date( 'Y-m-d', strtotime( '-6 days' ) ) ) ); // phpcs:ignore
	$labels = aw_cl_ground_labels( $cid );
	$ground = array();
	foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT grounds FROM {$t} WHERE campaign_id = %d", $cid ) ) as $g ) { // phpcs:ignore
		foreach ( array_filter( explode( ',', (string) $g ) ) as $n ) {
			$ground[ (int) $n ] = ( $ground[ (int) $n ] ?? 0 ) + 1;
		}
	}
	arsort( $ground );
	$cities = wp_list_pluck( (array) $wpdb->get_results( $wpdb->prepare( "SELECT city AS k, COUNT(*) AS n FROM {$t} WHERE campaign_id = %d AND city <> '' GROUP BY city ORDER BY n DESC LIMIT 3", $cid ) ), 'n', 'k' ); // phpcs:ignore
	echo '<p><strong>' . esc_html( $all[ $cid ] ) . '</strong></p>';
	echo '<p style="font-size:14px">' . esc_html( sprintf( '%s sent · %s in the last 7 days', number_format_i18n( $total ), number_format_i18n( $week ) ) ) . '</p>';
	$list = function ( $title, $rows ) {
		echo '<p style="margin:12px 0 4px"><strong>' . esc_html( $title ) . '</strong></p>';
		if ( ! $rows ) {
			echo '<p class="description">None yet.</p>';
			return;
		}
		echo '<table class="widefat striped"><tbody>';
		foreach ( $rows as $label => $n ) {
			echo '<tr><td>' . esc_html( $label ) . '</td><td style="width:4rem;text-align:right">' . esc_html( number_format_i18n( $n ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	};
	$top = array();
	foreach ( array_slice( $ground, 0, 3, true ) as $n => $c ) {
		$top[ $n . '. ' . ( $labels[ $n ] ?? '' ) ] = $c;
	}
	$list( 'Top grounds', $top );
	$list( 'Top cities', $cities );
	echo '<p style="margin-top:12px"><a href="' . esc_url( admin_url( 'edit.php?post_type=aw_campaign&page=aw-letter-report&campaign=' . $cid ) ) . '">Full letter report →</a></p>';
}
