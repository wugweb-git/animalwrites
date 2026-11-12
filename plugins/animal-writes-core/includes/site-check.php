<?php
/**
 * "Check site" (admin bar, administrators): loads one page of every kind, as the deploy check does, and lists each
 * with its status and time. A page fails on anything but 200, a PHP error or WordPress's "critical error" page.
 * Results show on Animal Writes → Check site → Page check, with the time of the check and
 * the commit it ran on. Nothing is changed.
 *
 * It also runs by itself after each deploy (owner, 11 Nov 2026): the request that first sees a new deployed commit
 * (the purge nudge of tools/aw-ship.sh or the Deploy check, deploy-purge.php) schedules a run two minutes later through
 * WordPress's cron (hPanel runs it every 5 minutes), so the result shown is never one from before the deploy. Until it
 * has run, both screens say the last check is from an older commit.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The pages checked: label => path. One of each kind, from what exists now. */
function aw_site_check_urls() {
	$urls = array(
		'Home'                => '/',
		'News'                => '/news/',
		'Library'             => '/library/',
		'Resources'           => '/resources/',
		'Opportunities'       => '/opportunities/',
		'Campaigns'           => '/campaigns/',
		'Directory'           => '/directory/',
		'Directory: a state'  => '/directory/kerala/',
		'Directory: district' => '/directory/kerala/ernakulam/',
		'Media & PR'          => '/press-coverage/',
		'Contact'             => '/contact/',
		'Volunteer'           => '/volunteer/',
		'Search'              => '/search/?q=dog',
		'Sitemap'             => ( class_exists( '\RankMath\Helper' ) && \RankMath\Helper::is_module_active( 'sitemap' ) ) ? '/sitemap_index.xml' : '/wp-sitemap.xml',
	);
	foreach ( array( 'aw_news' => 'A news item', 'aw_legal' => 'A Library item', 'post' => 'A guide', 'aw_opportunity' => 'An opportunity', 'aw_campaign' => 'A campaign', 'aw_listing' => 'An organisation' ) as $type => $label ) {
		$p = get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids' ) );
		if ( $p ) {
			$urls[ $label ] = wp_parse_url( get_permalink( $p[0] ), PHP_URL_PATH );
		}
	}
	$t = get_terms( array( 'taxonomy' => 'category', 'number' => 1, 'hide_empty' => true ) );
	if ( $t && ! is_wp_error( $t ) ) {
		$urls['A category'] = wp_parse_url( kits_term_url( $t[0] ), PHP_URL_PATH );
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

// Animal Writes → Check site (admin-home.php).
add_action(
	'admin_menu',
	function () {
		add_submenu_page( AW_CORE_MENU, 'Check site', 'Check site', aw_core_screen_cap( 'aw-site-check' ), 'aw-site-check', 'aw_site_check_page' );
	},
	aw_core_menu_priority( 'aw-site-check' ) // order: aw_core_screens() (admin-home.php)
);

/** Load every page in aw_site_check_urls() and save the result (option aw_site_check_last) with its commit. */
function aw_site_check_run() {
	$out = array();
	foreach ( aw_site_check_urls() as $label => $path ) {
		$t0   = microtime( true );
		$r    = wp_remote_get( add_query_arg( 'ck', wp_generate_password( 8, false ), home_url( $path ) ), array( 'timeout' => 30, 'redirection' => 0, 'headers' => array( 'Cache-Control' => 'no-cache' ) ) );
		$ms   = (int) round( ( microtime( true ) - $t0 ) * 1000 );
		$code = is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r );
		$body = is_wp_error( $r ) ? '' : (string) wp_remote_retrieve_body( $r );
		$why  = is_wp_error( $r ) ? $r->get_error_message() : ( 200 !== $code ? 'HTTP ' . $code : ( preg_match( '/critical error|Fatal error|Parse error/i', $body ) ? 'error text on the page' : '' ) );
		$out[] = array( $label, $path, $code, $ms, $why );
	}
	$head = function_exists( 'aw_deployed_head' ) ? aw_deployed_head() : '';
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
function aw_site_check_after_deploy() {
	wp_clear_scheduled_hook( 'aw_site_check_deploy' );
	wp_schedule_single_event( time() + 2 * MINUTE_IN_SECONDS, 'aw_site_check_deploy' );
}
add_action( 'aw_site_check_deploy', 'aw_site_check_run' );

/**
 * One line on the last check: the result, when it ran and on which commit, and whether that is still the deployed
 * commit. For Animal Writes → Check site → Page check.
 *
 * @return array{ok:bool,text:string} ok: a check on the deployed commit found no problem.
 */
function aw_site_check_summary(): array {
	$c = get_option( 'aw_site_check_last' );
	if ( ! is_array( $c ) || empty( $c['rows'] ) ) {
		return array(
			'ok'   => false,
			'text' => 'Not run yet.',
		);
	}
	$bad    = count( array_filter( $c['rows'], fn( $r ) => '' !== $r[4] ) );
	$now    = function_exists( 'aw_deployed_head' ) ? aw_deployed_head() : '';
	$ran    = (string) ( $c['head'] ?? '' );
	$result = $bad ? $bad . ' of ' . count( $c['rows'] ) . ' pages had a problem' : 'All ' . count( $c['rows'] ) . ' pages opened cleanly';
	$text   = $result . '. Checked ' . $c['at'] . ( '' !== $ran ? ' on commit ' . substr( $ran, 0, 7 ) : '' ) . '.';
	$stale  = '' !== $now && $ran !== $now;
	if ( $stale ) {
		// The run after a deploy can be missed (a cron request that never came, or one cut short): schedule it again.
		if ( ! wp_next_scheduled( 'aw_site_check_deploy' ) ) {
			aw_site_check_after_deploy();
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
		aw_site_check_run();
		wp_safe_redirect( aw_site_check_url() );
		exit;
	}
);

/** Where the check's results are: Animal Writes → Check site. */
function aw_site_check_url(): string {
	return admin_url( 'admin.php?page=aw-site-check' );
}

/** Animal Writes → Check site: the results, then the other checks (backup, CDN, email, …) through aw_site_check_after. */
function aw_site_check_page() {
	echo '<div class="wrap"><h1>Check site</h1>';
	aw_core_screen_nav( 'aw-site-check' );
	aw_site_check_results();
	do_action( 'aw_site_check_after' ); // e.g. the daily backup line
	echo '</div>';
}

/** Check site → Page check: the Run button, the last check's time and commit, and each page's status. */
function aw_site_check_results() {
	$last = get_option( 'aw_site_check_last' );
	$run  = wp_nonce_url( admin_url( 'admin-post.php?action=aw_site_check' ), 'aw_site_check' );
	echo '<p>Loads one page of every kind and reports any that do not open cleanly. Nothing is changed. It runs again 2 minutes after each deploy. <a class="button" href="' . esc_url( $run ) . '">Run now</a></p>';
	if ( ! is_array( $last ) ) {
		echo '<p>Not run yet.</p>';
		return;
	}
	$bad = array_filter( $last['rows'], fn( $r ) => '' !== $r[4] );
	$sum = aw_site_check_summary();
	echo '<div class="notice ' . esc_attr( $bad ? 'notice-error' : ( $sum['ok'] ? 'notice-success' : 'notice-warning' ) ) . ' inline"><p>' . esc_html( $sum['text'] ) . '</p></div>';
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
	aw_core_simple_table( 'site-check', array( 'page' => 'Page', 'address' => 'Address', 'status' => 'Status', 'time' => 'Time', 'problem' => 'Problem' ), $cells );
}

/*
 * Joined component stylesheets against their cap (Kits functions.php): a warning near the cap, so pages do not fall
 * back to separate files unnoticed. Shown on Animal Writes → Check site → Joined files.
 */
/** Animal Writes → Check site → Joined files: joined component stylesheets against their cap. */
function aw_site_check_joined() {
	if ( ! function_exists( 'kits_parts_bundle_status' ) || ! aw_core_theme_parts_on() ) {
		return;
	}
	$s = kits_parts_bundle_status();
	echo '<div class="notice ' . esc_attr( $s['warn'] ? 'notice-warning' : 'notice-info' ) . ' inline"><p><strong>' . esc_html( 'Joined component files:' ) . '</strong> ' . esc_html( trim( $s['text'] . '. ' . $s['fix'] ) ) . '</p></div>';
}
add_action( 'aw_site_check_after', 'aw_site_check_joined' );
