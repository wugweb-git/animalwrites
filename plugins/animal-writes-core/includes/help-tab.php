<?php
/**
 * Animal Writes Core: an "About Animal Writes Core" Help tab and a sidebar on Animal Writes Core's own screens (WP_Screen::add_help_tab), on every screen,
 * classic or shell. Screens that have their own help keep it; this tab sits beside it.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'current_screen', 'aw_core_help_tab' );

/**
 * Add the tab when the open admin page is one of Animal Writes Core's.
 *
 * @param WP_Screen $screen The screen.
 */
function aw_core_help_tab( $screen ) {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which screen is open; nothing is saved.
	if ( '' === $page || ! is_object( $screen ) ) {
		return;
	}
	$ours = in_array( $page, array( 'animal-writes', 'aw-keys', 'aw-modules', 'aw-site-check', 'aw-elsewhere', 'aw-edit-screens' ), true );
	foreach ( array(  ) as $start ) {
		$ours = $ours || 0 === strpos( $page, $start );
	}
	if ( ! $ours ) {
		return;
	}
	$screens = array(
		__( 'Animal Writes', 'animal-writes-core' ) => __( 'the site overview', 'animal-writes-core' ),
		__( 'Check site', 'animal-writes-core' ) => __( 'the latest checks of every page type', 'animal-writes-core' ),
		__( 'Keys', 'animal-writes-core' ) => __( 'service keys the site uses', 'animal-writes-core' ),
		__( 'Modules', 'animal-writes-core' ) => __( 'which parts of the site are on', 'animal-writes-core' ),
		__( 'Contact links, Edit screens', 'animal-writes-core' ) => __( 'the site\'s contact links, and every box on every edit screen', 'animal-writes-core' ),
	);
	$list = '';
	foreach ( $screens as $label => $what ) {
		$list .= '<li><strong>' . esc_html( $label ) . '</strong>: ' . esc_html( $what ) . '</li>';
	}
	$screen->add_help_tab(
		array(
			'id'       => 'aw_core-about',
			/* translators: %s: plugin name. */
			'title'    => sprintf( __( 'About %s', 'animal-writes-core' ), 'Animal Writes Core' ),
			'content'  => '<p>' . esc_html__( 'The Animal Writes site: content types and fields, the Directory, campaigns and letters, forms and people, card pictures and the jobs that keep the site running.', 'animal-writes-core' ) . '</p><ul>' . $list . '</ul>',
			'priority' => 5,
		)
	);
	$data = function_exists( 'get_file_data' ) ? get_file_data( AW_CORE_DIR . 'animal-writes-core.php', array( 'Version' => 'Version' ) ) : array();
	$screen->set_help_sidebar(
		'<p><strong>Animal Writes Core</strong>' . ( empty( $data['Version'] ) ? '' : ' ' . esc_html( $data['Version'] ) ) . '</p>'
		. '<p><a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">' . esc_html__( 'Plugins', 'animal-writes-core' ) . '</a></p>'
		. '<p><a href="https://wugweb.com" target="_blank" rel="noopener noreferrer">Wugweb<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'animal-writes-core' ) . '</span></a></p>'
	);
}
