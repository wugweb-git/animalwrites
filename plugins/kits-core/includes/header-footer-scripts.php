<?php
/**
 * Kits Header and Footer Scripts Manager.
 *
 * Benchmark: WPCode / Insert Headers and Footers.
 * Allows administrators to safely inject custom code (Google Tag Manager, Meta Pixel,
 * verification tags, custom CSS/JS) into <head>, wp_body_open, and wp_footer.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'kits_scripts_menu', 25 );

/**
 * Register Scripts page in admin menu.
 */
function kits_scripts_menu() {
	add_submenu_page(
		KITS_MENU,
		__( 'Header & Footer Scripts', 'kits-core' ),
		__( 'Scripts & Pixels', 'kits-core' ),
		'manage_options',
		'kits-scripts',
		'kits_scripts_page'
	);
}

/**
 * Enqueue scripts styles.
 */
add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( false === strpos( $hook, 'kits-scripts' ) ) {
			return;
		}
		if ( wp_style_is( 'kits-shell', 'registered' ) ) {
			wp_enqueue_style( 'kits-shell' );
		}
	}
);

/**
 * Handle scripts save.
 */
add_action(
	'admin_init',
	function () {
		if ( ! isset( $_POST['kits_scripts_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['kits_scripts_nonce'] ) ), 'kits_scripts_save' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'kits-core' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'kits-core' ) );
		}

		$header = isset( $_POST['kits_header_scripts'] ) ? wp_unslash( $_POST['kits_header_scripts'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- raw admin script tags allowed for super admin
		$body   = isset( $_POST['kits_body_scripts'] ) ? wp_unslash( $_POST['kits_body_scripts'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$footer = isset( $_POST['kits_footer_scripts'] ) ? wp_unslash( $_POST['kits_footer_scripts'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		update_option( 'kits_header_scripts', $header );
		update_option( 'kits_body_scripts', $body );
		update_option( 'kits_footer_scripts', $footer );

		wp_safe_redirect( add_query_arg( array( 'page' => 'kits-scripts', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}
);

/**
 * Render Header & Footer Scripts page.
 */
function kits_scripts_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$header = get_option( 'kits_header_scripts', '' );
	$body   = get_option( 'kits_body_scripts', '' );
	$footer = get_option( 'kits_footer_scripts', '' );

	echo '<div class="wrap">';
	echo '<h1>' . esc_html__( 'Header and Footer Scripts & Pixels', 'kits-core' ) . '</h1>';

	if ( isset( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Scripts saved successfully.', 'kits-core' ) . '</p></div>';
	}

	echo '<p class="description">' . esc_html__( 'Inject analytics, tracking pixels, meta tags, and custom verification code directly into your site layout without editing theme files.', 'kits-core' ) . '</p>';

	echo '<form method="post" action="" class="kits-action-list" style="margin-top:20px;max-width:960px;">';
	wp_nonce_field( 'kits_scripts_save', 'kits_scripts_nonce' );

	echo '<div class="kits-action-row" style="flex-direction:column;align-items:flex-start;gap:10px;">';
	echo '<label for="kits_header_scripts" style="font-weight:600;font-size:15px;">' . esc_html__( 'Scripts in <head>', 'kits-core' ) . '</label>';
	echo '<p class="description" style="margin:0;">' . esc_html__( 'These scripts will be printed high in the <head> element. Ideal for Google Tag Manager, Google Analytics 4, and search engine verification tags.', 'kits-core' ) . '</p>';
	echo '<textarea name="kits_header_scripts" id="kits_header_scripts" rows="8" style="width:100%;font-family:monospace;font-size:13px;line-height:1.4;">' . esc_textarea( $header ) . '</textarea>';
	echo '</div>';

	echo '<div class="kits-action-row" style="flex-direction:column;align-items:flex-start;gap:10px;">';
	echo '<label for="kits_body_scripts" style="font-weight:600;font-size:15px;">' . esc_html__( 'Scripts in <body> (wp_body_open)', 'kits-core' ) . '</label>';
	echo '<p class="description" style="margin:0;">' . esc_html__( 'These scripts will be printed immediately after the opening <body> tag. Essential for Google Tag Manager <noscript> fallback snippets.', 'kits-core' ) . '</p>';
	echo '<textarea name="kits_body_scripts" id="kits_body_scripts" rows="6" style="width:100%;font-family:monospace;font-size:13px;line-height:1.4;">' . esc_textarea( $body ) . '</textarea>';
	echo '</div>';

	echo '<div class="kits-action-row" style="flex-direction:column;align-items:flex-start;gap:10px;">';
	echo '<label for="kits_footer_scripts" style="font-weight:600;font-size:15px;">' . esc_html__( 'Scripts in <footer>', 'kits-core' ) . '</label>';
	echo '<p class="description" style="margin:0;">' . esc_html__( 'These scripts will be printed before the closing </body> tag. Suitable for customer support chat widgets and conversion pixels.', 'kits-core' ) . '</p>';
	echo '<textarea name="kits_footer_scripts" id="kits_footer_scripts" rows="8" style="width:100%;font-family:monospace;font-size:13px;line-height:1.4;">' . esc_textarea( $footer ) . '</textarea>';
	echo '</div>';

	echo '<div style="margin-top:20px;">';
	echo '<button type="submit" class="button button-primary button-hero">' . esc_html__( 'Save Scripts', 'kits-core' ) . '</button>';
	echo '</div>';

	echo '</form>';
	echo '</div>';
}

/**
 * Inject scripts into frontend.
 */
add_action(
	'wp_head',
	function () {
		$scripts = get_option( 'kits_header_scripts', '' );
		if ( ! empty( $scripts ) ) {
			echo "\n<!-- Kits Header Scripts -->\n" . $scripts . "\n<!-- /Kits Header Scripts -->\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- deliberate script injection
		}
	},
	1
);

add_action(
	'wp_body_open',
	function () {
		$scripts = get_option( 'kits_body_scripts', '' );
		if ( ! empty( $scripts ) ) {
			echo "\n<!-- Kits Body Scripts -->\n" . $scripts . "\n<!-- /Kits Body Scripts -->\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- deliberate script injection
		}
	},
	1
);

add_action(
	'wp_footer',
	function () {
		$scripts = get_option( 'kits_footer_scripts', '' );
		if ( ! empty( $scripts ) ) {
			echo "\n<!-- Kits Footer Scripts -->\n" . $scripts . "\n<!-- /Kits Footer Scripts -->\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- deliberate script injection
		}
	},
	999
);
