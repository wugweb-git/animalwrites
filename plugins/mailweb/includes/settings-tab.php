<?php
/**
 * MailWeb → Settings: the shell screen (mailweb-ui.php), fed by the mailweb/v1 routes (mailweb-rest.php).
 *
 * &sub=sending is drawn here: Site sending, with the SMTP test email and "Send queued emails now", which the shell
 * screen links to (smtp.php, mail-cap.php).
 *
 * @package mailweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** MailWeb → Settings. */
function mailweb_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'mailweb' ), 403 );
	}
	if ( mailweb_ui_page( 'settings' ) ) {
		return;
	}
	if ( 'sending' !== sanitize_key( $_GET['sub'] ?? '' ) || ! function_exists( 'mailweb_smtp_screen' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		mailweb_ui_missing( __( 'MailWeb settings', 'mailweb' ) );
		return;
	}
	echo '<div class="wrap" id="mailweb-settings"><h1 class="wp-heading-inline">' . esc_html__( 'Mail settings', 'mailweb' ) . '</h1> <a class="page-title-action" href="' . esc_url( mailweb_ui_url( 'settings' ) ) . '">' . esc_html__( 'All settings', 'mailweb' ) . '</a><hr class="wp-header-end">';
	mailweb_screen_tabs();
	mailweb_smtp_screen();
	echo '</div>';
}
