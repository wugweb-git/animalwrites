<?php
/**
 * Plugin Name: Staging guard
 * Description: Staging only (staging.animalwrites.in). No email, no outside requests, never indexed, signed-in users only.
 *
 * Installed as a must-use plugin by tools/staging/refresh.sh, so it runs before any plugin, whatever code staging runs.
 * The staging database is a copy of live with its personal data (owner, 11 Nov 2026): FluentCRM queues, MailWeb's
 * mailbox and SourceWeb's worker keys come with it, so nothing may leave this site.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( 0 === strpos( (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_HOST ), 'staging.' ) ) {
	// No email of any kind (wp_mail, FluentCRM, Fluent Forms, MailWeb all go through wp_mail).
	add_filter( 'pre_wp_mail', '__return_true', 0 );

	// No outside requests: workers, Google, GitHub, mail APIs, update checks. Requests to staging itself still work.
	add_filter(
		'pre_http_request',
		function ( $pre, $args, $url ) {
			$host = (string) wp_parse_url( (string) $url, PHP_URL_HOST );
			$self = (string) wp_parse_url( home_url(), PHP_URL_HOST );
			return $host === $self ? $pre : new WP_Error( 'staging_offline', 'Staging makes no outside requests.' );
		},
		0,
		3
	);

	// Never indexed; signed-in users only.
	add_filter( 'pre_option_blog_public', fn() => '0' );
	add_filter( 'wp_robots', fn() => array( 'noindex' => true, 'nofollow' => true ), 99 );
	add_action( 'send_headers', fn() => header( 'X-Robots-Tag: noindex, nofollow' ) );
	add_action(
		'template_redirect',
		function () {
			if ( ! is_user_logged_in() ) {
				auth_redirect();
			}
		},
		0
	);
}
