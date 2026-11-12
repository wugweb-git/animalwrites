<?php
/**
 * InsightWeb: an "About InsightWeb" Help tab and a sidebar on InsightWeb's own screens (WP_Screen::add_help_tab), on every screen,
 * classic or shell. Screens that have their own help keep it; this tab sits beside it.
 *
 * @package insightweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'current_screen', 'aw_si_help_tab' );

/**
 * Add the tab when the open admin page is one of InsightWeb's.
 *
 * @param WP_Screen $screen The screen.
 */
function aw_si_help_tab( $screen ) {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which screen is open; nothing is saved.
	if ( '' === $page || ! is_object( $screen ) ) {
		return;
	}
	$ours = in_array( $page, array(  ), true );
	foreach ( array( 'aw-search-insights' ) as $start ) {
		$ours = $ours || 0 === strpos( $page, $start );
	}
	if ( ! $ours ) {
		return;
	}
	$screens = array(
		__( 'Dashboard', 'insightweb' ) => __( 'the last 28 days at a glance', 'insightweb' ),
		__( 'Keywords', 'insightweb' ) => __( 'every search the site showed for, with its role and topic', 'insightweb' ),
		__( 'Pages, Links, Rising', 'insightweb' ) => __( 'pages, links to the site and searches that are growing', 'insightweb' ),
		__( 'Settings', 'insightweb' ) => __( 'the services and their keys', 'insightweb' ),
	);
	$list = '';
	foreach ( $screens as $label => $what ) {
		$list .= '<li><strong>' . esc_html( $label ) . '</strong>: ' . esc_html( $what ) . '</li>';
	}
	$screen->add_help_tab(
		array(
			'id'       => 'aw_si-about',
			/* translators: %s: plugin name. */
			'title'    => sprintf( __( 'About %s', 'insightweb' ), 'InsightWeb' ),
			'content'  => '<p>' . esc_html__( 'Search Console, GA4, Clarity, PageSpeed and Bing in one place, with the keyword table and rising searches. It reads these services; it changes nothing on them.', 'insightweb' ) . '</p><ul>' . $list . '</ul>',
			'priority' => 5,
		)
	);
	$data = function_exists( 'get_file_data' ) ? get_file_data( INSIGHTWEB_DIR . 'insightweb.php', array( 'Version' => 'Version' ) ) : array();
	$screen->set_help_sidebar(
		'<p><strong>InsightWeb</strong>' . ( empty( $data['Version'] ) ? '' : ' ' . esc_html( $data['Version'] ) ) . '</p>'
		. '<p><a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">' . esc_html__( 'Plugins', 'insightweb' ) . '</a></p>'
		. '<p><a href="https://wugweb.com" target="_blank" rel="noopener noreferrer">Wugweb<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'insightweb' ) . '</span></a></p>'
	);
}
