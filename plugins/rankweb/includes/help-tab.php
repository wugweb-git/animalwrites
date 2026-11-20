<?php
/**
 * RankWeb: an "About RankWeb" Help tab and a sidebar on RankWeb's own screens (WP_Screen::add_help_tab), on every screen,
 * on the Kits shell or not. Screens that have their own help keep it; this tab sits beside it.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'current_screen', 'aw_seo_help_tab' );

/**
 * Add the tab when the open admin page is one of RankWeb's.
 *
 * @param WP_Screen $screen The screen.
 */
function aw_seo_help_tab( $screen ) {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which screen is open; nothing is saved.
	if ( '' === $page || ! is_object( $screen ) ) {
		return;
	}
	$ours = in_array( $page, array( 'aw-faq' ), true );
	foreach ( array( 'aw-seo' ) as $start ) {
		$ours = $ours || 0 === strpos( $page, $start );
	}
	if ( ! $ours ) {
		return;
	}
	$screens = array(
		__( 'Overview', 'aw-seo-extras' ) => __( 'every module, where it is set up and its status', 'aw-seo-extras' ),
		__( 'FAQ', 'aw-seo-extras' ) => __( 'which content has the FAQ box, and questions waiting for approval', 'aw-seo-extras' ),
		__( 'Settings', 'aw-seo-extras' ) => __( 'every rule, by module, with its default', 'aw-seo-extras' ),
	);
	$list = '';
	foreach ( $screens as $label => $what ) {
		$list .= '<li><strong>' . esc_html( $label ) . '</strong>: ' . esc_html( $what ) . '</li>';
	}
	$screen->add_help_tab(
		array(
			'id'       => 'aw_seo-about',
			/* translators: %s: plugin name. */
			'title'    => sprintf( __( 'About %s', 'aw-seo-extras' ), 'RankWeb' ),
			'content'  => '<p>' . esc_html__( 'Everything Rank Math does not do, or keeps for Premium: the FAQ system, canonical rules, breadcrumbs, content analysis on what visitors see, focus keywords and the content panel in the editor. It works only while Rank Math is active.', 'aw-seo-extras' ) . '</p><ul>' . $list . '</ul>',
			'priority' => 5,
		)
	);
	$data = function_exists( 'get_file_data' ) ? get_file_data( AW_SEO_EXTRAS_DIR . 'aw-seo-extras.php', array( 'Version' => 'Version' ) ) : array();
	$screen->set_help_sidebar(
		'<p><strong>RankWeb</strong>' . ( empty( $data['Version'] ) ? '' : ' ' . esc_html( $data['Version'] ) ) . '</p>'
		. '<p><a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">' . esc_html__( 'Plugins', 'aw-seo-extras' ) . '</a></p>'
		. '<p><a href="https://wugweb.com" target="_blank" rel="noopener noreferrer">Wugweb<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'aw-seo-extras' ) . '</span></a></p>'
	);
}
