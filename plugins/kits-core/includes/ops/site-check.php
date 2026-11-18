<?php
/**
 * "Check site" (admin bar, administrators): loads one page of every kind, as the deploy check does, and lists each
 * with its status and time. A page fails on anything but 200, a PHP error or WordPress's "critical error" page.
 * Results show on Kits → Home → Page check (Check site, aw-site-check, without Kits), with the time of the check and
 * the commit it ran on. Nothing is changed.
 *
 * It also runs by itself after each deploy (owner, 11 Nov 2026): the request that first sees a new deployed commit
 * (the purge nudge of tools/aw-ship.sh or the Deploy check, deploy-purge.php) schedules a run two minutes later through
 * WordPress's cron (hPanel runs it every 5 minutes), so the result shown is never one from before the deploy. Until it
 * has run, both screens say the last check is from an older commit.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The pages checked: label => path. Generic default: Home, Search and the sitemap, one page of each public post type
 * and one category. A site names its own list with the filter kits_site_check_urls (Animal Writes: the theme's site
 * layer, assets/site/ops.php).
 *
 * @return array<string,string>
 */
function kits_site_check_urls() {
	$own = apply_filters( 'kits_site_check_urls', null ); // a site that names its own list skips the generic queries below
	if ( is_array( $own ) ) {
		return $own;
	}
	$urls = array(
		'Home'    => '/',
		'Search'  => '/search/?q=a',
		'Sitemap' => ( class_exists( '\RankMath\Helper' ) && \RankMath\Helper::is_module_active( 'sitemap' ) ) ? '/sitemap_index.xml' : '/wp-sitemap.xml',
	);
	foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type => $obj ) {
		if ( 'attachment' === $type ) {
			continue;
		}
		$p = get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids' ) );
		if ( $p ) {
			$urls[ $obj->labels->singular_name ] = wp_parse_url( get_permalink( $p[0] ), PHP_URL_PATH );
		}
	}
	$t = get_terms( array( 'taxonomy' => 'category', 'number' => 1, 'hide_empty' => true ) );
	if ( $t && ! is_wp_error( $t ) ) {
		$urls['A category'] = wp_parse_url( function_exists( 'kits_term_url' ) ? kits_term_url( $t[0] ) : get_term_link( $t[0] ), PHP_URL_PATH );
	}
	return $urls;
}

add_action(
	'admin_bar_menu',
	function ( $bar ) {
		if ( current_user_can( 'manage_options' ) ) {
			$bar->add_node( array( 'id' => 'aw-site-check', 'title' => 'Check site', 'href' => wp_nonce_url( admin_url( 'admin-post.php?action=aw_site_check' ), 'aw_site_check' ) ) );
		}
	},
	80
);

/** Load every page in kits_site_check_urls() and save the result (option aw_site_check_last) with its commit. */
function kits_site_check_run() {
	$out = array();
	foreach ( kits_site_check_urls() as $label => $path ) {
		$t0   = microtime( true );
		$r    = wp_remote_get( add_query_arg( 'ck', wp_generate_password( 8, false ), home_url( $path ) ), array( 'timeout' => 30, 'redirection' => 0, 'headers' => array( 'Cache-Control' => 'no-cache' ) ) );
		$ms   = (int) round( ( microtime( true ) - $t0 ) * 1000 );
		$code = is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r );
		$body = is_wp_error( $r ) ? '' : (string) wp_remote_retrieve_body( $r );
		$why  = is_wp_error( $r ) ? $r->get_error_message() : ( 200 !== $code ? 'HTTP ' . $code : ( preg_match( '/critical error|Fatal error|Parse error/i', $body ) ? 'error text on the page' : '' ) );
		$out[] = array( $label, $path, $code, $ms, $why );
	}
	$head = function_exists( 'kits_deployed_head' ) ? kits_deployed_head() : '';
	update_option(
		'aw_site_check_last',
		array(
			'at'   => current_time( 'mysql' ),
			'rows' => $out,
			'head' => $head,
		),
		false
	);
}

/** After a deploy: the run deploy-purge.php schedules (one at a time; a second deploy before it runs moves it). */
function kits_site_check_after_deploy() {
	wp_clear_scheduled_hook( 'aw_site_check_deploy' );
	wp_schedule_single_event( time() + 2 * MINUTE_IN_SECONDS, 'aw_site_check_deploy' );
}
add_action( 'aw_site_check_deploy', 'kits_site_check_run' );

/**
 * One line on the last check: the result, when it ran and on which commit, and whether that is still the deployed
 * commit. For Kits → Home → Page check.
 *
 * @return array{ok:bool,text:string} ok: a check on the deployed commit found no problem.
 */
function kits_site_check_summary(): array {
	$c = get_option( 'aw_site_check_last' );
	if ( ! is_array( $c ) || empty( $c['rows'] ) ) {
		return array(
			'ok'   => false,
			'text' => 'Not run yet.',
		);
	}
	$bad    = count( array_filter( $c['rows'], fn( $r ) => '' !== $r[4] ) );
	$now    = function_exists( 'kits_deployed_head' ) ? kits_deployed_head() : '';
	$ran    = (string) ( $c['head'] ?? '' );
	$result = $bad ? $bad . ' of ' . count( $c['rows'] ) . ' pages had a problem' : 'All ' . count( $c['rows'] ) . ' pages opened cleanly';
	$text   = $result . '. Checked ' . $c['at'] . ( '' !== $ran ? ' on commit ' . substr( $ran, 0, 7 ) : '' ) . '.';
	$stale  = '' !== $now && $ran !== $now;
	if ( $stale ) {
		// The run after a deploy can be missed (a cron request that never came, or one cut short): schedule it again.
		if ( ! wp_next_scheduled( 'aw_site_check_deploy' ) ) {
			kits_site_check_after_deploy();
		}
		$text .= ' The deployed commit is now ' . substr( $now, 0, 7 ) . ': this check is from before it; a new check runs within a few minutes.';
	}
	return array(
		'ok'   => ! $bad && ! $stale,
		'text' => $text,
	);
}

add_action(
	'admin_post_aw_site_check',
	function () {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'aw_site_check' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		kits_site_check_run();
		wp_safe_redirect( kits_site_check_url() );
		exit;
	}
);

/** Where the check's results are: Kits → Home → Page check. */
function kits_site_check_url(): string {
	return admin_url( 'admin.php?page=' . KITS_MENU ) . '#page-check';
}

/** Kits → Home → Page check: the Run button, the last check's time and commit, and each page's status. */
function kits_site_check_results() {
	$last = get_option( 'aw_site_check_last' );
	$run  = wp_nonce_url( admin_url( 'admin-post.php?action=aw_site_check' ), 'aw_site_check' );
	echo '<p>Loads one page of every kind and reports any that do not open cleanly. Nothing is changed. It runs again 2 minutes after each deploy. <a class="button" href="' . esc_url( $run ) . '">Run now</a></p>';
	if ( ! is_array( $last ) ) {
		echo '<p>Not run yet.</p>';
		return;
	}
	$bad = array_filter( $last['rows'], fn( $r ) => '' !== $r[4] );
	$sum = kits_site_check_summary();
	wp_admin_notice( esc_html( $sum['text'] ), array( 'type' => $bad ? 'error' : ( $sum['ok'] ? 'success' : 'warning' ), 'additional_classes' => array( 'inline' ) ) );
	$cells = array();
	foreach ( $last['rows'] as $r ) {
		$cells[] = array(
			'page'    => esc_html( $r[0] ),
			'address' => '<a href="' . esc_url( home_url( $r[1] ) ) . '" target="_blank" rel="noopener">' . esc_html( $r[1] ) . '<span class="screen-reader-text"> (opens in a new tab)</span></a>',
			'status'  => ( '' === $r[4] ? '✓ ' : '✗ ' ) . (int) $r[2],
			'time'    => (int) $r[3] . ' ms',
			'problem' => esc_html( $r[4] ),
		);
	}
	kits_simple_table( 'site-check', array( 'page' => 'Page', 'address' => 'Address', 'status' => 'Status', 'time' => 'Time', 'problem' => 'Problem' ), $cells );
}

/*
 * Joined component stylesheets against their cap (Kits functions.php): a warning near the cap, so pages do not fall
 * back to separate files unnoticed. Shown on Kits → Home → Joined files.
 */
/** Kits → Home → Joined files: joined component stylesheets against their cap. */
function kits_site_check_joined() {
	if ( ! function_exists( 'kits_parts_bundle_status' ) || ! ( function_exists( 'kits_site_on' ) && kits_site_on() ) ) {
		return;
	}
	$s = kits_parts_bundle_status();
	wp_admin_notice( '<strong>' . esc_html( 'Joined component files:' ) . '</strong> ' . esc_html( trim( $s['text'] . '. ' . $s['fix'] ) ), array( 'type' => $s['warn'] ? 'warning' : 'info', 'additional_classes' => array( 'inline' ) ) );
}
add_action( 'aw_site_check_after', 'kits_site_check_joined' );
