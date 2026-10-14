<?php
/**
 * "Check site" (admin bar, administrators): loads one page of every kind, as the deploy check does, and lists each
 * with its status and time. A page fails on anything but 200, a PHP error or WordPress's "critical error" page.
 * Results show on Tools → Check site. Nothing is changed.
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
		'Media & PR'          => '/media/',
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
	$t = get_terms( array( 'taxonomy' => 'aw_topic', 'number' => 1, 'hide_empty' => true ) );
	if ( $t && ! is_wp_error( $t ) ) {
		$urls['A category'] = wp_parse_url( get_term_link( $t[0] ), PHP_URL_PATH );
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

add_action(
	'admin_menu',
	function () {
		add_management_page( 'Check site', 'Check site', 'manage_options', 'aw-site-check', 'aw_site_check_page' );
	}
);

add_action(
	'admin_post_aw_site_check',
	function () {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'aw_site_check' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
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
		update_option( 'aw_site_check_last', array( 'at' => current_time( 'mysql' ), 'rows' => $out ), false );
		wp_safe_redirect( admin_url( 'tools.php?page=aw-site-check' ) );
		exit;
	}
);

function aw_site_check_page() {
	$last = get_option( 'aw_site_check_last' );
	$run  = wp_nonce_url( admin_url( 'admin-post.php?action=aw_site_check' ), 'aw_site_check' );
	echo '<div class="wrap"><h1 class="wp-heading-inline">Check site</h1> <a class="page-title-action" href="' . esc_url( $run ) . '">Run the check</a><hr class="wp-header-end">';
	echo '<p>Loads one page of every kind and reports any that do not open cleanly. Nothing is changed.</p>';
	if ( ! is_array( $last ) ) {
		echo '<p>Not run yet.</p>';
		do_action( 'aw_site_check_after' );
		echo '</div>';
		return;
	}
	$bad = array_filter( $last['rows'], fn( $r ) => '' !== $r[4] );
	echo '<div class="notice ' . ( $bad ? 'notice-error' : 'notice-success' ) . ' inline"><p><strong>' . esc_html( $bad ? count( $bad ) . ' of ' . count( $last['rows'] ) . ' pages have a problem.' : 'All ' . count( $last['rows'] ) . ' pages open cleanly.' ) . '</strong> Checked ' . esc_html( $last['at'] ) . '.</p></div>';
	echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th>Page</th><th>Address</th><th>Status</th><th>Time</th><th>Problem</th></tr></thead><tbody>';
	foreach ( $last['rows'] as $r ) {
		echo '<tr><td>' . esc_html( $r[0] ) . '</td><td><a href="' . esc_url( home_url( $r[1] ) ) . '" target="_blank">' . esc_html( $r[1] ) . '</a></td><td>' . ( '' === $r[4] ? '✓ ' : '✗ ' ) . (int) $r[2] . '</td><td>' . (int) $r[3] . ' ms</td><td>' . esc_html( $r[4] ) . '</td></tr>';
	}
	echo '</tbody></table>';
	do_action( 'aw_site_check_after' ); // e.g. the daily backup line
	echo '</div>';
}
