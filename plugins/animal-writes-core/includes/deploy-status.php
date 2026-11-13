<?php
/**
 * Deploy status for the deploy gate (3 Nov 2026).
 *
 * Each deploy replaces the deployed folder, so for a few seconds the plugins are missing. A FluentCRM campaign batch
 * that starts in that window sends without mailweb's mailbox and fails. The Deploy check workflow and tools/aw-ship.sh
 * read GET /wp-json/aw/v1/deploy-status (tools/ci/mail_sending_check.py) and do not deploy while it says sending.
 * Public, like /aw/v1/thumb-dupes: it returns only a yes or no, never campaign names, ids or counts.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** True while a FluentCRM campaign is running or scheduled and still has emails waiting to go out. */
function aw_campaign_sending() {
	global $wpdb;
	$c = $wpdb->prefix . 'fc_campaigns';
	$e = $wpdb->prefix . 'fc_campaign_emails';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $c ) ) ) !== $c ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return false; // FluentCRM not installed: nothing can be sending
	}
	// Same rule as the old SSH check in tools/aw-ship.sh.
	return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM %i c JOIN %i e ON e.campaign_id = c.id WHERE c.status IN ('working','scheduled') AND e.status IN ('pending','scheduled','processing') LIMIT 1", $c, $e ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'aw/v1',
			'/deploy-status',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true', // the deploy gate reads it without logging in
				'callback'            => function () {
					$r = new WP_REST_Response( array( 'sending' => aw_campaign_sending() ), 200 );
					$r->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
					$r->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );
					return $r;
				},
			)
		);
	}
);
