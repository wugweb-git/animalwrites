<?php
/**
 * FluentCRM (owner, 16 Oct 2026): one list, "Newsletter"; volunteers are newsletter subscribers.
 *
 * - The footer sign-up (Fluent Forms), the forms' newsletter box and every volunteer sign-up add the person to the
 *   Newsletter list as "subscribed" and to the site's own list ({prefix}aw_subscribers, active). FluentCRM sends no
 *   email for this: the only email is the sign-up confirmation Fluent Forms already sends.
 * - The two lists stay in step on unsubscribes: FluentCRM's unsubscribe marks the site's row unsubscribed, and the
 *   site's unsubscribe link unsubscribes the FluentCRM contact.
 * - Campaigns go out through wp_mail (the site's mailbox, kits-core) under the daily cap in mail-cap.php.
 * - "Remove my data" deletes the FluentCRM contact.
 * Setup (sends nothing): `wp aw crm-setup`, `wp aw crm-import`.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_CRM_LIST = 'newsletter';

function aw_crm_on() {
	return function_exists( 'FluentCrmApi' );
}

/** The Newsletter list ID (created on first use). */
function aw_crm_list_id() {
	if ( ! aw_crm_on() ) {
		return 0;
	}
	$list = FluentCrmApi( 'lists' )->getInstance()->where( 'slug', AW_CRM_LIST )->first();
	if ( ! $list ) {
		$list = FluentCrmApi( 'lists' )->getInstance()->create( array( 'title' => 'Newsletter', 'slug' => AW_CRM_LIST ) );
	}
	return $list ? (int) $list->id : 0;
}

/** Tag IDs for slugs (created if missing). */
function aw_crm_tag_ids( $slugs ) {
	$ids = array();
	foreach ( (array) $slugs as $slug ) {
		$tag = FluentCrmApi( 'tags' )->getInstance()->where( 'slug', $slug )->first();
		$tag = $tag ? $tag : FluentCrmApi( 'tags' )->getInstance()->create( array( 'title' => $slug, 'slug' => $slug ) );
		$ids[] = (int) $tag->id;
	}
	return $ids;
}

/**
 * Add or update a contact on the Newsletter list as subscribed (someone who unsubscribed, bounced or complained keeps
 * that status). Sends nothing.
 *
 * @param string $email Email.
 * @param string $name  Full name (optional).
 * @param array  $extra FluentCRM fields (phone, address_line_1, city, state, postal_code, country, source).
 * @param array  $tags  Tag slugs.
 * @return int Contact ID, or 0.
 */
function aw_crm_subscribe( $email, $name = '', $extra = array(), $tags = array() ) {
	$email = strtolower( trim( (string) $email ) );
	$list  = aw_crm_list_id();
	if ( ! $list || ! is_email( $email ) ) {
		return 0;
	}
	$parts = preg_split( '/\s+/', trim( (string) $name ), 2 );
	$data  = array_filter( $extra ) + array(
		'email'  => $email,
		'status' => 'subscribed',
		'lists'  => array( $list ),
	);
	if ( '' !== ( $parts[0] ?? '' ) ) {
		$data['first_name'] = $parts[0];
		$data['last_name']  = $parts[1] ?? '';
	}
	$have = FluentCrmApi( 'contacts' )->getContact( $email );
	if ( $have && in_array( $have->status, array( 'unsubscribed', 'bounced', 'complained' ), true ) ) {
		$data['status'] = $have->status;
	}
	if ( $tags ) {
		$data['tags'] = aw_crm_tag_ids( $tags );
	}
	$c = FluentCrmApi( 'contacts' )->createOrUpdate( $data );
	return $c ? (int) $c->id : 0;
}

/** A volunteer's details as FluentCRM fields (state and district by place slug). */
function aw_crm_volunteer_fields( $state_slug, $district_slug, $city, $pincode, $phone ) {
	$st = $state_slug ? aw_place_by_slug( $state_slug ) : null;
	$di = $district_slug ? aw_place_by_slug( $district_slug ) : null;
	return array(
		'phone'          => (string) $phone,
		'address_line_1' => $di ? $di->name . ' district' : '',
		'city'           => (string) $city,
		'state'          => $st ? $st->name : '',
		'postal_code'    => (string) $pincode,
		'country'        => 'IN',
	);
}

/** One sign-up onto both lists: the site's (active) and FluentCRM's Newsletter (subscribed). */
function aw_newsletter_join( $email, $name, $source, $consent, $extra = array(), $tags = array() ) {
	$res = function_exists( 'aw_nl_activate' ) ? aw_nl_activate( $email, $name, $source, $consent ) : false;
	if ( aw_crm_on() ) {
		aw_crm_subscribe( $email, $name, $extra + array( 'source' => $source ), $tags );
	}
	return $res;
}

/* ---------- Unsubscribes stay in step ---------- */

add_action(
	'fluentcrm_subscriber_status_to_unsubscribed',
	function ( $subscriber ) {
		global $wpdb;
		$wpdb->update( aw_nl_table(), array( 'status' => 'unsubscribed', 'updated_at' => current_time( 'mysql', true ) ), array( 'email' => strtolower( (string) $subscriber->email ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
);

// The site's own unsubscribe link (in the sign-up confirmation) also unsubscribes the FluentCRM contact.
add_action(
	'template_redirect',
	function () {
		if ( 'unsubscribe' !== sanitize_key( $_GET['aw_nl'] ?? '' ) || ! aw_crm_on() ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$token = preg_replace( '/[^a-f0-9]/', '', (string) wp_unslash( $_GET['t'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$row   = 32 === strlen( $token ) ? aw_nl_get( 'token', $token ) : null;
		$c     = $row ? FluentCrmApi( 'contacts' )->getContact( $row['email'] ) : null;
		if ( $c && 'unsubscribed' !== $c->status ) {
			$c->updateStatus( 'unsubscribed' );
		}
	},
	5 // before newsletter.php's handler redirects
);

/* ---------- "Remove my data": the FluentCRM contact goes too ---------- */

add_filter(
	'wp_privacy_personal_data_erasers',
	function ( $erasers ) {
		$erasers['aw-fluentcrm'] = array(
			'eraser_friendly_name' => 'Newsletter (FluentCRM)',
			'callback'             => function ( $email ) {
				$c = aw_crm_on() ? FluentCrmApi( 'contacts' )->getContact( strtolower( trim( (string) $email ) ) ) : null;
				if ( $c ) {
					\FluentCrm\App\Services\Helper::deleteContacts( array( (int) $c->id ) );
				}
				return array( 'items_removed' => (bool) $c, 'items_retained' => false, 'messages' => array(), 'done' => true );
			},
		);
		return $erasers;
	}
);

/* ---------- Setup commands (send nothing) ---------- */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	// wp aw crm-setup: sender, slowest sending speed, the Newsletter list and the volunteer-1.0 tag.
	WP_CLI::add_command(
		'aw crm-setup',
		function () {
			if ( ! aw_crm_on() ) {
				WP_CLI::error( 'FluentCRM is not active.' );
			}
			$all = (array) get_option( 'fluentcrm-global-settings', array() );
			$em  = \FluentCrm\App\Services\Helper::getGlobalEmailSettings();
			$em['from_name']         = 'Animal Writes';
			$em['from_email']        = function_exists( 'kits_mail_settings' ) ? kits_mail_settings()['user'] : get_option( 'admin_email' );
			$em['emails_per_second'] = 4; // the lowest FluentCRM accepts; the filter in mail-cap.php lowers it to 1
			$all['email_settings']   = $em;
			$biz                     = (array) ( $all['business_settings'] ?? array() );
			$biz['business_name']    = 'Animal Writes';
			$all['business_settings'] = $biz;
			update_option( 'fluentcrm-global-settings', $all );
			WP_CLI::success( 'List ' . aw_crm_list_id() . ', tag ' . implode( ',', aw_crm_tag_ids( array( 'volunteer-1.0' ) ) ) );
		}
	);

	// wp aw crm-import: the volunteers and the site's sign-ups onto both lists as subscribed / active.
	WP_CLI::add_command(
		'aw crm-import',
		function () {
			global $wpdb;
			add_filter( 'pre_wp_mail', '__return_false', 1 ); // no email of any kind during the import
			$n     = array( 'volunteers' => 0, 'signups' => 0, 'failed' => 0 );
			$names = array();
			foreach ( aw_lgd()['ids'] as $t ) {
				$names[ $t->term_id ] = $t;
			}
			foreach ( (array) $wpdb->get_results( 'SELECT * FROM ' . aw_vol_table() . " WHERE status = 'active'", ARRAY_A ) as $r ) { // phpcs:ignore
				$st = $names[ (int) $r['state_id'] ] ?? null;
				$di = $names[ (int) $r['district_id'] ] ?? null;
				$f  = aw_crm_volunteer_fields( $st ? $st->slug : '', $di ? $di->slug : '', $r['city_text'], $r['pincode'], $r['phone'] );
				$id = aw_crm_subscribe( $r['email'], $r['name'], $f + array( 'source' => $r['source'] ), array( 'v1_campaign' === $r['source'] ? 'volunteer-1.0' : 'volunteer' ) );
				$id ? $n['volunteers']++ : $n['failed']++;
			}
			foreach ( (array) $wpdb->get_results( 'SELECT email, name, source, status FROM ' . aw_nl_table() . " WHERE status IN ('pending','invited','active')", ARRAY_A ) as $r ) { // phpcs:ignore
				if ( 'active' !== $r['status'] ) { // owner, 16 Oct 2026: the earlier sign-ups count as subscribed
					aw_nl_activate( $r['email'], $r['name'], $r['source'], 'owner-2026-10-16-signups' );
				}
				if ( 'v1_campaign' === $r['source'] ) {
					continue; // already added as a volunteer
				}
				$id = aw_crm_subscribe( $r['email'], $r['name'], array( 'source' => $r['source'] ), array( 'newsletter' ) );
				$id ? $n['signups']++ : $n['failed']++;
			}
			WP_CLI::success( wp_json_encode( $n ) );
		}
	);
}
