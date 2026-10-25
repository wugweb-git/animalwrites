<?php
/**
 * Campaign form (owner, 21 Oct 2026): "Sign it" in the campaign letter tool is the Fluent Forms form "Campaign form"
 * (forms/10-campaign-form.json; its ID in the option aw_ff_campaign_form). The letter tool keeps its own screen (grounds,
 * the letter as it is written, the Gmail / Outlook / email app / copy buttons, which a form cannot do); when a letter is
 * sent, campaign.js submits the signer to Fluent Forms. So every signer is an entry (Fluent Forms → Entries), with the
 * campaign, grounds, route, own line, both boxes, WhatsApp and the visit fields, and the form's FluentCRM link adds them
 * (Newsletter list, newsletter and volunteer tags, the campaign fields). After the link: the campaign-<slug> tag, the
 * boxes as 1/0 and the letter's anonymous statistics row (campaign-letters.php, one row per send_id).
 *
 * The raw copy (owner, 21 Oct 2026: "keep a ready fallback"): every form send (all Fluent Forms forms, and the campaign
 * sign-up) is copied whole into wp_aw_form_raw the moment it reaches the server, before any check. Its status says
 * what happened: received (refused by a check, or failed), saved (with the entry ID), duplicate (a retry of a
 * send already saved). Kept 180 days; "Remove my data"
 * erases it by email. wp aw raw-export writes it as CSV.
 *
 * @package AnimalWritesCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_CF_OPTION   = 'aw_ff_campaign_form';
const AW_RAW_DB      = '1';
const AW_RAW_DAYS    = 180;
const AW_CF_RL_LIMIT = 30; // sends an hour from one connection (colleges and offices share one)

/** The Campaign form's Fluent Forms ID, or 0. */
function aw_cf_id() {
	return defined( 'FLUENTFORM' ) ? (int) get_option( AW_CF_OPTION, 0 ) : 0;
}

/* ---------- The raw copy ---------- */

function aw_raw_table() {
	global $wpdb;
	return $wpdb->prefix . 'aw_form_raw';
}

add_action(
	'init',
	function () {
		if ( AW_RAW_DB === get_option( 'aw_form_raw_db' ) ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta(
			'CREATE TABLE ' . aw_raw_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			received_at datetime NOT NULL,
			kind varchar(20) NOT NULL DEFAULT '',
			form_id bigint(20) unsigned NOT NULL DEFAULT 0,
			email varchar(190) NOT NULL DEFAULT '',
			payload longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'received',
			entry_id bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY email (email),
			KEY received (received_at)
			) {$wpdb->get_charset_collate()};"
		);
		update_option( 'aw_form_raw_db', AW_RAW_DB, false );
	},
	5
);

/** Copies one send as it arrived; returns the row ID (0 when the table is not there). */
function aw_raw_save( $kind, $form_id, $data ) {
	global $wpdb;
	$data = (array) $data;
	unset( $data['altcha'] ); // the bot check's answer: long and of no use later
	$ok = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		aw_raw_table(),
		array(
			'received_at' => current_time( 'mysql', true ),
			'kind'        => substr( (string) $kind, 0, 20 ),
			'form_id'     => (int) $form_id,
			'email'       => substr( strtolower( trim( sanitize_text_field( (string) ( $data['email'] ?? '' ) ) ) ), 0, 190 ),
			'payload'     => (string) wp_json_encode( $data ),
		)
	);
	return $ok ? (int) $wpdb->insert_id : 0;
}

function aw_raw_mark( $id, $status, $entry_id = 0 ) {
	global $wpdb;
	if ( $id ) {
		$wpdb->update( aw_raw_table(), array( 'status' => $status, 'entry_id' => (int) $entry_id ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}

// Every Fluent Forms send, before Fluent Forms looks at it (its own handler runs at priority 10).
$GLOBALS['aw_raw_id'] = 0;
foreach ( array( 'wp_ajax_nopriv_fluentform_submit', 'wp_ajax_fluentform_submit' ) as $aw_hook ) {
	add_action(
		$aw_hook,
		function () {
			$data = array();
			parse_str( (string) wp_unslash( $_POST['data'] ?? '' ), $data ); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput -- copied as sent, read only by admins
			$form = (int) ( $_POST['form_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
			$GLOBALS['aw_raw_id'] = aw_raw_save( $form && $form === aw_cf_id() ? 'campaign' : 'form', $form, $data );
		},
		1
	);
}
add_action(
	'fluentform/submission_inserted',
	function ( $entry_id ) {
		aw_raw_mark( $GLOBALS['aw_raw_id'] ?? 0, 'saved', $entry_id );
	},
	1
);

// Older than 180 days: gone (daily).
add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'aw_raw_prune' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'aw_raw_prune' );
		}
	}
);
add_action(
	'aw_raw_prune',
	function () {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . aw_raw_table() . ' WHERE received_at < %s', gmdate( 'Y-m-d H:i:s', time() - AW_RAW_DAYS * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
);

add_filter(
	'wp_privacy_personal_data_erasers',
	function ( $erasers ) {
		$erasers['aw-form-raw'] = array(
			'eraser_friendly_name' => 'Raw copies of form sends',
			'callback'             => function ( $email ) {
				global $wpdb;
				$n = $wpdb->delete( aw_raw_table(), array( 'email' => strtolower( trim( (string) $email ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				return array( 'items_removed' => (bool) $n, 'items_retained' => false, 'messages' => array(), 'done' => true );
			},
		);
		return $erasers;
	}
);

/* ---------- The Campaign form ---------- */

/** The campaign a send names, when it is published and open; else 0. */
function aw_cf_campaign( $data ) {
	$id = function_exists( 'aw_camp_id' ) ? aw_camp_id( (int) ( $data['campaign_id'] ?? 0 ) ) : 0;
	return $id && 'publish' === get_post_status( $id ) && aw_camp_is_open( $id ) ? $id : 0;
}

/** The ALTCHA answer of a Campaign form send: in the form data, or in the raw post when Fluent Forms left it out (it is not a form field, so it is not kept with the entry). */
function aw_cf_altcha( $data ) {
	if ( isset( $data['altcha'] ) && is_scalar( $data['altcha'] ) ) {
		return (string) $data['altcha'];
	}
	$raw = array();
	parse_str( (string) wp_unslash( $_POST['data'] ?? '' ), $raw ); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput -- checked by kits_altcha_verify
	return is_scalar( $raw['altcha'] ?? null ) ? (string) $raw['altcha'] : '';
}

/** Fluent Forms' answer for a send already saved (a retry after a lost answer): the same reference, no new entry. */
function aw_cf_done( $ref ) {
	aw_raw_mark( $GLOBALS['aw_raw_id'] ?? 0, 'duplicate' );
	wp_send_json(
		array(
			'insert_id' => 0,
			'result'    => array( 'message' => '<p class="aw-form-done-title">Thank you.</p><p class="aw-form-ref">Your reference: <strong data-aw-ref="' . esc_attr( $ref ) . '">' . esc_html( $ref ) . '</strong></p>', 'action' => 'hide_form' ),
			'error'     => '',
		)
	);
}

add_filter(
	'fluentform/validation_errors',
	function ( $errors, $data, $form ) {
		if ( (int) $form->id !== aw_cf_id() || ! aw_cf_id() ) {
			return $errors;
		}
		$send = function_exists( 'aw_cl_send_id' ) ? aw_cl_send_id( $data['send_id'] ?? '' ) : '';
		$ref  = $send ? get_transient( 'aw_cl_s_' . $send ) : '';
		if ( $ref ) {
			aw_cf_done( $ref );
		}
		if ( ! aw_cf_campaign( $data ) ) {
			$errors['campaign_id'] = array( 'aw' => 'This campaign is closed.' );
		}
		$rl = 'aw_rl_aw_campaign_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! $errors && (int) get_transient( $rl ) >= AW_CF_RL_LIMIT ) {
			$errors['aw_form'] = array( 'aw' => 'Too many tries. Wait a minute, then try again.' );
		}
		// The bot check (ALTCHA, audit item 4), checked and used up only when everything else is right.
		if ( ! $errors && ! aw_cl_human( $send, aw_cf_altcha( $data ) ) ) {
			$errors['altcha'] = array( 'aw' => 'Couldn\'t verify you\'re not a bot. Try again.' );
		}
		return $errors;
	},
	20,
	3
);

/** The yes/no boxes on the site's forms: an unticked box sends nothing, so the entry says 0 (owner, 21 Oct 2026). */
const AW_FF_BOXES = array( 'nl_optin', 'wa_optin', 'org_assoc', 'org_show', 'consent' );

add_filter(
	'fluentform/insert_response_data',
	function ( $data, $form_id ) {
		if ( ! ( function_exists( 'aw_ff_key' ) && aw_ff_key( $form_id ) ) && (int) $form_id !== aw_cf_id() ) {
			return $data;
		}
		$form   = \FluentForm\App\Models\Form::find( (int) $form_id );
		$fields = $form ? (string) $form->form_fields : '';
		foreach ( AW_FF_BOXES as $box ) {
			if ( false !== strpos( $fields, '"name":"' . $box . '"' ) && ( empty( $data[ $box ] ) || array( '' ) === (array) $data[ $box ] ) ) {
				$data[ $box ] = '0';
			}
		}
		return $data;
	},
	5,
	2
);

// What the server knows better than the page: the campaign's name and slug, the grounds' labels, the date, the
// reference (AW-L-<n>, the series the letter tool started) and the page fields.
add_filter(
	'fluentform/insert_response_data',
	function ( $data, $form_id ) {
		if ( (int) $form_id !== aw_cf_id() || ! aw_cf_id() ) {
			return $data;
		}
		$id                     = aw_cf_campaign( $data );
		$grounds                = aw_cl_grounds( $data['grounds'] ?? '' );
		$labels                 = aw_cl_ground_labels( $id );
		$data['campaign_id']    = (string) $id;
		$data['campaign']       = (string) get_post_field( 'post_name', $id );
		$data['campaign_title'] = get_the_title( $id );
		$data['grounds']        = $grounds;
		$data['grounds_text']   = implode( '; ', array_map( fn( $n ) => $n . '. ' . ( $labels[ $n ] ?? '' ), array_filter( explode( ',', $grounds ) ) ) );
		$route                  = sanitize_key( (string) ( $data['route'] ?? '' ) );
		$data['route']          = isset( AW_CL_ROUTES[ $route ] ) ? $route : '';
		$device                 = sanitize_key( (string) ( $data['device'] ?? '' ) );
		$data['device']         = isset( AW_CL_DEVICES[ $device ] ) ? $device : '';
		$data['letter_date']    = wp_date( 'Y-m-d', null, new DateTimeZone( 'Asia/Kolkata' ) );
		$data['city']           = aw_cl_city( $data['city'] ?? '' );
		$data['v_page_id']      = (string) $id;
		$data['v_page_title']   = get_the_title( $id );
		$data['ref']            = 'AW-L-' . aw_cl_next_ref();
		$send                   = aw_cl_send_id( $data['send_id'] ?? '' );
		if ( $send ) {
			set_transient( 'aw_cl_s_' . $send, $data['ref'], DAY_IN_SECONDS );
		}
		return $data;
	},
	10,
	2
);

// After the link (priority 10): the campaign-<slug> tag and the rest through the same function the letter tool used
// (it also makes the contact if the link did not run), the boxes as 1/0, the campaign fields, and the statistics row.
add_action(
	'fluentform/submission_inserted',
	function ( $entry_id, $data, $form ) {
		if ( (int) $form->id !== aw_cf_id() || ! aw_cf_id() ) {
			return;
		}
		$rl = 'aw_rl_aw_campaign_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		set_transient( $rl, (int) get_transient( $rl ) + 1, HOUR_IN_SECONDS );
		$id = (int) ( $data['campaign_id'] ?? 0 );
		if ( ! $id ) {
			return;
		}
		$box  = function_exists( 'aw_ff_ticked' ) ? aw_ff_ticked( $data, 'nl_optin' ) : ! empty( $data['nl_optin'] );
		$wa   = function_exists( 'aw_ff_ticked' ) ? aw_ff_ticked( $data, 'wa_optin' ) : ! empty( $data['wa_optin'] );
		$tel  = trim( sanitize_text_field( (string) ( $data['whatsapp'] ?? '' ) ) );
		$tel  = '' !== $tel && function_exists( 'kits_clean_phone' ) && kits_clean_phone( $tel ) ? kits_clean_phone( $tel ) : substr( $tel, 0, 20 );
		$c_id = aw_crm_campaign_signer( (string) ( $data['email'] ?? '' ), sanitize_text_field( (string) ( $data['name'] ?? '' ) ), (string) ( $data['city'] ?? '' ), (string) $data['campaign'], $box, $tel, $wa, (string) ( $data['ref'] ?? '' ), (string) ( $data['consent_version'] ?? AW_CL_CONSENT ) );
		$c    = $c_id ? \FluentCrm\App\Models\Subscriber::find( $c_id ) : null;
		if ( $c ) {
			$c->syncCustomFieldValues(
				array(
					'campaign_last'    => (string) ( $data['campaign_title'] ?? '' ),
					'campaign_grounds' => (string) ( $data['grounds_text'] ?? '' ),
					'campaign_route'   => (string) ( $data['route'] ?? '' ),
					'campaign_date'    => (string) ( $data['letter_date'] ?? '' ),
					'campaign_line'    => mb_substr( sanitize_textarea_field( (string) ( $data['own'] ?? '' ) ), 0, 300 ),
				),
				false
			);
		}
		aw_cl_record_letter(
			$id,
			array(
				'city'     => $data['city'] ?? '',
				'grounds'  => $data['grounds'] ?? '',
				'route'    => $data['route'] ?? '',
				'device'   => $data['device'] ?? '',
				'nl_optin' => $box ? '1' : '0',
				'wa_optin' => $wa ? '1' : '0',
				'send_id'  => $data['send_id'] ?? '',
			)
		);
	},
	20,
	3
);

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	// wp aw raw-export [--since=YYYY-MM-DD] [--status=received|saved] [--kind=form|campaign]: the raw copies as CSV.
	WP_CLI::add_command(
		'aw raw-export',
		function ( $args, $assoc ) {
			global $wpdb;
			$where = array( '1=1' );
			if ( ! empty( $assoc['since'] ) ) {
				$where[] = $wpdb->prepare( 'received_at >= %s', $assoc['since'] );
			}
			foreach ( array( 'status', 'kind' ) as $k ) {
				if ( ! empty( $assoc[ $k ] ) ) {
					$where[] = $wpdb->prepare( "$k = %s", $assoc[ $k ] ); // phpcs:ignore WordPress.DB.PreparedSQL
				}
			}
			$rows = $wpdb->get_results( 'SELECT * FROM ' . aw_raw_table() . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY id', ARRAY_A ); // phpcs:ignore
			$out  = fopen( 'php://stdout', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			fputcsv( $out, array( 'id', 'received_at', 'kind', 'form_id', 'email', 'status', 'entry_id', 'payload' ) );
			foreach ( $rows as $r ) {
				fputcsv( $out, array( $r['id'], $r['received_at'], $r['kind'], $r['form_id'], $r['email'], $r['status'], $r['entry_id'], $r['payload'] ) );
			}
		}
	);
}
