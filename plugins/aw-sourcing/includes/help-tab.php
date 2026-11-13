<?php
/**
 * SourceWeb: an "About SourceWeb" Help tab and a sidebar on every SourceWeb screen (WP_Screen::add_help_tab). Screens
 * that have their own help keep it; this tab sits beside it.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'current_screen', 'aw_src_help_tab' );

/**
 * Add the tab when the open admin page is one of SourceWeb's.
 *
 * @param WP_Screen $screen The screen.
 */
function aw_src_help_tab( $screen ) {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which screen is open; nothing is saved.
	if ( '' === $page || ! is_object( $screen ) ) {
		return;
	}
	$ours = in_array( $page, array( 'aw-review', 'aw-enrich-review' ), true );
	foreach ( array( 'aw-sourcing' ) as $start ) {
		$ours = $ours || 0 === strpos( $page, $start );
	}
	if ( ! $ours ) {
		return;
	}
	$screens = array(
		__( 'Overview', 'aw-sourcing' ) => __( 'what came in, what waits and what the workers did', 'aw-sourcing' ),
		__( 'To review, Enrichment review', 'aw-sourcing' ) => __( 'approve or reject what the workers found', 'aw-sourcing' ),
		__( 'Sources, Keywords, Rules', 'aw-sourcing' ) => __( 'what is fetched and what is kept', 'aw-sourcing' ),
		__( 'AI models', 'aw-sourcing' ) => __( 'which free models do which job, with daily caps', 'aw-sourcing' ),
	);
	$list = '';
	foreach ( $screens as $label => $what ) {
		$list .= '<li><strong>' . esc_html( $label ) . '</strong>: ' . esc_html( $what ) . '</li>';
	}
	$screen->add_help_tab(
		array(
			'id'       => 'aw_src-about',
			/* translators: %s: plugin name. */
			'title'    => sprintf( __( 'About %s', 'aw-sourcing' ), 'SourceWeb' ),
			'content'  => '<p>' . esc_html__( 'What the news workers fetch and how it arrives: sources and feeds, keywords, rules, the review queues and the worker endpoints.', 'aw-sourcing' ) . '</p><ul>' . $list . '</ul>',
			'priority' => 5,
		)
	);
	$data = function_exists( 'get_file_data' ) ? get_file_data( AW_SOURCING_DIR . 'aw-sourcing.php', array( 'Version' => 'Version' ) ) : array();
	$screen->set_help_sidebar(
		'<p><strong>SourceWeb</strong>' . ( empty( $data['Version'] ) ? '' : ' ' . esc_html( $data['Version'] ) ) . '</p>'
		. '<p><a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">' . esc_html__( 'Plugins', 'aw-sourcing' ) . '</a></p>'
		. '<p><a href="https://wugweb.com" target="_blank" rel="noopener noreferrer">Wugweb<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'aw-sourcing' ) . '</span></a></p>'
	);
}
