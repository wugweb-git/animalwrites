<?php
/**
 * MailWeb: an "About MailWeb" Help tab and a sidebar on each of MailWeb's own screens (WP_Screen::add_help_tab).
 * Screens that have their own help keep it; this tab sits beside it.
 *
 * @package mailweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'current_screen', 'mailweb_help_tab' );

/**
 * Add the tab when the open admin page is one of MailWeb's.
 *
 * @param WP_Screen $screen The screen.
 */
function mailweb_help_tab( $screen ) {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which screen is open; nothing is saved.
	if ( '' === $page || ! is_object( $screen ) ) {
		return;
	}
	$ours = in_array( $page, array(  ), true );
	foreach ( array( 'mailweb' ) as $start ) {
		$ours = $ours || 0 === strpos( $page, $start );
	}
	if ( ! $ours ) {
		return;
	}
	$screens = array(
		__( 'Inbox', 'mailweb' ) => __( 'every mailbox and folder, with reply, forward and bulk actions', 'mailweb' ),
		__( 'Overview', 'mailweb' ) => __( 'sending today, the queue and the mailboxes', 'mailweb' ),
		__( 'Settings', 'mailweb' ) => __( 'mailboxes, profiles and signatures, SMTP, the daily limit and FluentCRM', 'mailweb' ),
	);
	$list = '';
	foreach ( $screens as $label => $what ) {
		$list .= '<li><strong>' . esc_html( $label ) . '</strong>: ' . esc_html( $what ) . '</li>';
	}
	$screen->add_help_tab(
		array(
			'id'       => 'mailweb-about',
			/* translators: %s: plugin name. */
			'title'    => sprintf( __( 'About %s', 'mailweb' ), 'MailWeb' ),
			'content'  => '<p>' . esc_html__( 'Read, reply and send from the site\'s Hostinger mailboxes inside WordPress, and send the site\'s own email through its SMTP mailbox with a daily limit and a queue. Mail stays in the mailboxes; nothing is copied into WordPress.', 'mailweb' ) . '</p><ul>' . $list . '</ul>',
			'priority' => 5,
		)
	);
	$data = function_exists( 'get_file_data' ) ? get_file_data( MAILWEB_FILE, array( 'Version' => 'Version' ) ) : array();
	$screen->set_help_sidebar(
		'<p><strong>MailWeb</strong>' . ( empty( $data['Version'] ) ? '' : ' ' . esc_html( $data['Version'] ) ) . '</p>'
		. '<p><a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">' . esc_html__( 'Plugins', 'mailweb' ) . '</a></p>'
		. '<p><a href="https://wugweb.com" target="_blank" rel="noopener noreferrer">Wugweb<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'mailweb' ) . '</span></a></p>'
	);
}
