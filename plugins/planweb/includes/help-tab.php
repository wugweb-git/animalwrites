<?php
/**
 * PlanWeb: an "About PlanWeb" Help tab and a sidebar on PlanWeb's own screens (WP_Screen::add_help_tab), on every screen,
 * classic or shell. Screens that have their own help keep it; this tab sits beside it.
 *
 * @package planweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'current_screen', 'planweb_help_tab' );

/**
 * Add the tab when the open admin page is one of PlanWeb's.
 *
 * @param WP_Screen $screen The screen.
 */
function planweb_help_tab( $screen ) {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which screen is open; nothing is saved.
	if ( '' === $page || ! is_object( $screen ) ) {
		return;
	}
	$ours = in_array( $page, array( 'aw-dates' ), true );
	foreach ( array( 'planweb' ) as $start ) {
		$ours = $ours || 0 === strpos( $page, $start );
	}
	if ( ! $ours ) {
		return;
	}
	$screens = array(
		__( 'Calendar', 'planweb' ) => __( 'the month grid and the list of entries with filters', 'planweb' ),
		__( 'Entries', 'planweb' ) => __( 'add or edit an entry; only administrators set Approved or Sent', 'planweb' ),
		__( 'Settings', 'planweb' ) => __( 'PlanWeb on or off and its defaults', 'planweb' ),
	);
	$list = '';
	foreach ( $screens as $label => $what ) {
		$list .= '<li><strong>' . esc_html( $label ) . '</strong>: ' . esc_html( $what ) . '</li>';
	}
	$screen->add_help_tab(
		array(
			'id'       => 'planweb-about',
			/* translators: %s: plugin name. */
			'title'    => sprintf( __( 'About %s', 'planweb' ), 'PlanWeb' ),
			'content'  => '<p>' . esc_html__( 'The team\'s calendar: content, social, email, campaign and reminder entries, each with a channel, an owner and a status. Nothing in it posts or sends anything.', 'planweb' ) . '</p><ul>' . $list . '</ul>',
			'priority' => 5,
		)
	);
	$data = function_exists( 'get_file_data' ) ? get_file_data( PLANWEB_DIR . 'planweb.php', array( 'Version' => 'Version' ) ) : array();
	$screen->set_help_sidebar(
		'<p><strong>PlanWeb</strong>' . ( empty( $data['Version'] ) ? '' : ' ' . esc_html( $data['Version'] ) ) . '</p>'
		. '<p><a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">' . esc_html__( 'Plugins', 'planweb' ) . '</a></p>'
		. '<p><a href="https://wugweb.com" target="_blank" rel="noopener noreferrer">Wugweb<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'planweb' ) . '</span></a></p>'
	);
}

// Plugins screen: Calendar and Settings under the plugin's name.
add_filter(
	'plugin_action_links_' . plugin_basename( PLANWEB_DIR . 'planweb.php' ),
	function ( $links ) {
		$own = array( '<a href="' . esc_url( planweb_url() ) . '">' . esc_html__( 'Calendar', 'planweb' ) . '</a>' );
		if ( current_user_can( 'manage_options' ) && function_exists( 'planweb_ui_url' ) ) {
			$own[] = '<a href="' . esc_url( planweb_ui_url( 'settings' ) ) . '">' . esc_html__( 'Settings', 'planweb' ) . '</a>';
		}
		return array_merge( $own, (array) $links );
	}
);
