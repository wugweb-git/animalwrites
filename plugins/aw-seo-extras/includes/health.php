<?php
/**
 * Sitemap and llms.txt watchdog (14 Oct 2026: both answered 404 until rewrite rules were flushed).
 *
 * Hourly (event aw_seo_endpoints_watchdog): Rank Math's rewrite rules for /sitemap_index.xml and /llms.txt must be in
 * the stored rules, and a request from the site to each must answer 200. If either fails, rewrite rules are flushed once
 * and both are checked again. If they still fail, the admin email gets a message and the dashboard shows a notice.
 * One email per 12 hours for the same failing addresses; a newly failing address is emailed at once.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Address => rewrite-rule key Rank Math adds for it. */
function aw_seo_watched_endpoints() {
	return array(
		'/sitemap_index.xml' => 'sitemap_index\.xml$',
		'/llms.txt'          => '^llms\.txt$',
	);
}

/**
 * What is wrong right now, as address => sentence (empty when all is well).
 *
 * @return array<string,string>
 */
function aw_seo_endpoint_problems() {
	$rules = (array) get_option( 'rewrite_rules', array() );
	$out   = array();
	foreach ( aw_seo_watched_endpoints() as $path => $rule ) {
		if ( ! isset( $rules[ $rule ] ) ) {
			$out[ $path ] = sprintf( '%s: its rewrite rule is missing.', $path );
			continue;
		}
		$res = wp_remote_get(
			add_query_arg( 'aw_check', time(), home_url( $path ) ), // past page caches
			array( 'timeout' => 20, 'redirection' => 0, 'sslverify' => false, 'headers' => array( 'Cache-Control' => 'no-cache' ) )
		);
		if ( is_wp_error( $res ) ) {
			continue; // the site cannot reach itself; the rule check above stands
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( 200 !== $code ) {
			$out[ $path ] = sprintf( '%s answers %d.', $path, $code );
		}
	}
	return $out;
}

/** Hourly check: flush rewrite rules once on a failure, then email and keep a notice if it still fails. */
function aw_seo_endpoints_watchdog() {
	$problems = aw_seo_endpoint_problems();
	$flushed  = false;
	if ( $problems ) {
		flush_rewrite_rules( false );
		$flushed  = true;
		$problems = aw_seo_endpoint_problems();
	}
	update_option( 'aw_seo_endpoints_last', array( 'at' => time(), 'flushed' => $flushed, 'problems' => $problems ), false );
	if ( ! $problems ) {
		delete_transient( 'aw_seo_endpoints_mailed' );
		return;
	}
	$sent = (string) get_transient( 'aw_seo_endpoints_mailed' );
	if ( $sent && ! array_diff( array_keys( $problems ), explode( ',', $sent ) ) ) {
		return; // the same addresses were emailed in the last 12 hours
	}
	$body = "Animal Writes: search engine files are not answering, even after rewrite rules were flushed:\n\n- " . implode( "\n- ", $problems ) . "\n\nCheck: " . home_url( '/sitemap_index.xml' ) . "\n       " . home_url( '/llms.txt' ) . "\nSettings → Permalinks → Save also flushes the rules.\n";
	if ( wp_mail( get_option( 'admin_email' ), 'Animal Writes: ' . reset( $problems ), $body ) ) {
		set_transient( 'aw_seo_endpoints_mailed', implode( ',', array_keys( $problems ) ), 12 * HOUR_IN_SECONDS );
	}
}
add_action( 'aw_seo_endpoints_watchdog', 'aw_seo_endpoints_watchdog' );
add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'aw_seo_endpoints_watchdog' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'aw_seo_endpoints_watchdog' );
		}
	}
);

add_action(
	'admin_notices',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$last = (array) get_option( 'aw_seo_endpoints_last', array() );
		if ( empty( $last['problems'] ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Sitemap or llms.txt is not answering.', 'aw-seo-extras' ) . '</strong> ' . esc_html( implode( ' ', (array) $last['problems'] ) ) . ' ' . esc_html( sprintf( 'Checked %s.', wp_date( 'j M Y, g:i a', (int) $last['at'] ) ) ) . '</p></div>';
	}
);
