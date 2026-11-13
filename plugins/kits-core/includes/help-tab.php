<?php
/**
 * Kits Core: an "About Kits Core" Help tab and a sidebar on Kits Core's own screens (WP_Screen::add_help_tab), on every screen.
 * Screens that have their own help keep it; this tab sits beside it.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'current_screen', 'kits_help_tab' );

/**
 * Add the tab when the open admin page is one of Kits Core's.
 *
 * @param WP_Screen $screen The screen.
 */
function kits_help_tab( $screen ) {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which screen is open; nothing is saved.
	if ( '' === $page || ! is_object( $screen ) ) {
		return;
	}
	$ours = in_array( $page, array(  ), true );
	foreach ( array( 'kits' ) as $start ) {
		$ours = $ours || 0 === strpos( $page, $start );
	}
	if ( ! $ours ) {
		return;
	}
	$screens = array(
		__( 'Home', 'kits-core' ) => __( 'site status and the Kits plugins', 'kits-core' ),
		__( 'Theme Options', 'kits-core' ) => __( 'header, socials, contact links and the share bar', 'kits-core' ),
		__( 'Settings', 'kits-core' ) => __( 'forms, analytics and the other shared features', 'kits-core' ),
		__( 'Tools', 'kits-core' ) => __( 'export and import of the Kits settings', 'kits-core' ),
		__( 'Help', 'kits-core' ) => __( 'the design system, the component registry and the docs', 'kits-core' ),
	);
	$list = '';
	foreach ( $screens as $label => $what ) {
		$list .= '<li><strong>' . esc_html( $label ) . '</strong>: ' . esc_html( $what ) . '</li>';
	}
	$screen->add_help_tab(
		array(
			'id'       => 'kits-about',
			/* translators: %s: plugin name. */
			'title'    => sprintf( __( 'About %s', 'kits-core' ), 'Kits Core' ),
			'content'  => '<p>' . esc_html__( 'The features every Kits site shares (forms and their spam guard, the share bar, Google Tag Manager with consent mode, the visit counter) and the Kits admin screens that the other Kits plugins are built on.', 'kits-core' ) . '</p><ul>' . $list . '</ul>',
			'priority' => 5,
		)
	);
	$data = function_exists( 'get_file_data' ) ? get_file_data( KITS_CORE_DIR . 'kits-core.php', array( 'Version' => 'Version' ) ) : array();
	$screen->set_help_sidebar(
		'<p><strong>Kits Core</strong>' . ( empty( $data['Version'] ) ? '' : ' ' . esc_html( $data['Version'] ) ) . '</p>'
		. '<p><a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">' . esc_html__( 'Plugins', 'kits-core' ) . '</a></p>'
		. '<p><a href="https://wugweb.com" target="_blank" rel="noopener noreferrer">Wugweb<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'kits-core' ) . '</span></a></p>'
	);
}
