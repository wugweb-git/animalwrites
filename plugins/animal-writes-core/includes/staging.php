<?php
/**
 * Staging site (staging.animalwrites.in): the same code and a copy of the database without personal data. It is never
 * indexed, only signed-in users can see it, and it sends no email. Nothing here runs on the live site.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Is this the staging site? */
function aw_is_staging() {
	return 0 === strpos( (string) wp_parse_url( home_url(), PHP_URL_HOST ), 'staging.' );
}

if ( aw_is_staging() ) {
	// Never indexed.
	add_filter( 'wp_robots', fn( $r ) => array( 'noindex' => true, 'nofollow' => true ) );
	add_action( 'send_headers', fn() => header( 'X-Robots-Tag: noindex, nofollow' ) );
	add_filter( 'robots_txt', fn() => "User-agent: *\nDisallow: /\n", 99 );
	// Signed-in users only (the deploy check reads the pages with an application password).
	add_action(
		'template_redirect',
		function () {
			if ( ! is_user_logged_in() ) {
				auth_redirect();
			}
		},
		0
	);
	// No email from staging (form tests must not reach anyone).
	add_filter( 'pre_wp_mail', '__return_true' );
	// A visible marker in the admin bar.
	add_action( 'admin_bar_menu', fn( $bar ) => $bar->add_node( array( 'id' => 'aw-staging', 'title' => 'STAGING' ) ), 1 );
}
