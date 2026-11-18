<?php
/**
 * Front-end scripts for the features Kits Core prints: share and copy link (share.php), forms with the ALTCHA spam
 * guard (forms.php, form-kit.php) and analytics events (analytics.php). They were part of the Kits theme's kit.js until
 * 18 Nov 2026; the theme keeps its own interactions (header, menu, motion, filters) there.
 *
 * Each loads on every front-end page, deferred in the footer, before the theme's kit.js (wp_enqueue_scripts 14; the
 * theme enqueues kit.js at 15), the order they ran in when they were one file. window.kitShareCopy (share.js) and
 * window.kitsTrack (analytics.js) stay the names other scripts call.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Kits Core's front-end scripts: handle => file in assets/js/.
 *
 * @return array<string,string>
 */
function kits_core_frontend_scripts(): array {
	return array(
		'kits-core-share'     => 'share.js',
		'kits-core-forms'     => 'forms.js',
		'kits-core-analytics' => 'analytics.js',
	);
}

/** Enqueues the front-end scripts. */
function kits_core_enqueue_frontend_scripts() {
	foreach ( kits_core_frontend_scripts() as $handle => $file ) {
		$path = KITS_CORE_DIR . 'assets/js/' . $file;
		if ( ! is_readable( $path ) ) {
			continue;
		}
		wp_enqueue_script(
			$handle,
			plugins_url( 'assets/js/' . $file, KITS_CORE_DIR . 'kits-core.php' ),
			array(),
			(string) filemtime( $path ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}
}
add_action( 'wp_enqueue_scripts', 'kits_core_enqueue_frontend_scripts', 14 );
