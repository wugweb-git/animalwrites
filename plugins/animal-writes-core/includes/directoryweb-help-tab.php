<?php
/**
 * DirectoryWeb: an "About DirectoryWeb" Help tab and a sidebar on DirectoryWeb's own screens (WP_Screen::add_help_tab), on every screen,
 * classic or shell. Screens that have their own help keep it; this tab sits beside it.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'current_screen', 'directoryweb_help_tab' );

/**
 * Add the tab when the open admin page is one of DirectoryWeb's.
 *
 * @param WP_Screen $screen The screen.
 */
function directoryweb_help_tab( $screen ) {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which screen is open; nothing is saved.
	if ( '' === $page || ! is_object( $screen ) ) {
		return;
	}
	$ours = in_array( $page, array( 'aw-claims' ), true );
	foreach ( array( 'directoryweb' ) as $start ) {
		$ours = $ours || 0 === strpos( $page, $start );
	}
	if ( ! $ours ) {
		return;
	}
	$screens = array(
		__( 'Dashboard', 'directoryweb' ) => __( 'listings by state, type and status', 'directoryweb' ),
		__( 'Listings', 'directoryweb' ) => __( 'every listing, with search, filters and its details', 'directoryweb' ),
		__( 'Claims', 'directoryweb' ) => __( 'claims from organisations, read and applied field by field', 'directoryweb' ),
		__( 'Place intros', 'directoryweb' ) => __( 'the intro of each state and district page', 'directoryweb' ),
		__( 'Settings', 'directoryweb' ) => __( 'DirectoryWeb\'s settings', 'directoryweb' ),
	);
	$list = '';
	foreach ( $screens as $label => $what ) {
		$list .= '<li><strong>' . esc_html( $label ) . '</strong>: ' . esc_html( $what ) . '</li>';
	}
	$screen->add_help_tab(
		array(
			'id'       => 'directoryweb-about',
			/* translators: %s: plugin name. */
			'title'    => sprintf( __( 'About %s', 'directoryweb' ), 'DirectoryWeb' ),
			'content'  => '<p>' . esc_html__( 'The Directory for the team: listings with their places and types, claims from organisations, and the intro of each state and district page.', 'directoryweb' ) . '</p><ul>' . $list . '</ul>',
			'priority' => 5,
		)
	);
	$data = function_exists( 'get_file_data' ) ? get_file_data( AW_CORE_DIR . 'animal-writes-core.php', array( 'Version' => 'Version' ) ) : array();
	$screen->set_help_sidebar(
		'<p><strong>DirectoryWeb</strong>' . ( empty( $data['Version'] ) ? '' : ' ' . esc_html( $data['Version'] ) ) . '</p>'
		. '<p><a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">' . esc_html__( 'Plugins', 'directoryweb' ) . '</a></p>'
		. '<p><a href="https://wugweb.com" target="_blank" rel="noopener noreferrer">Wugweb<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'directoryweb' ) . '</span></a></p>'
	);
}
