<?php
/**
 * Plugin Name: Kits Core
 * Description: Kits by Wugweb, the site features every Kits site shares: form engine with spam guard (ALTCHA) and per-field errors, share bar, Google Tag Manager with consent mode, an honest visit counter, preview-only items, source attribution and the publisher records the sourcing plugin uses; plus the design system index of the Kits theme and its child. Own menu: Kits (Overview, Design system, Preview). Configured by the site through filters (kits_*).
 * Version: 1.0.0
 * Author: Wugweb
 * License: GPL-2.0-or-later
 * Update URI: https://wugweb.com/plugins/kits-core/
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// A site plugin may load Kits Core itself; never load twice.
if ( defined( 'KITS_CORE_LOADED' ) ) {
	return;
}
define( 'KITS_CORE_LOADED', '1.0.0' );
define( 'KITS_CORE_DIR', __DIR__ . '/' );

// When a site plugin loads Kits Core, WordPress does not know Kits Core's folder. If that folder is a symlink in
// the plugins directory, __DIR__ is the link's target, and plugins_url() (block.json assets, scripts) would build
// an address from the target's absolute path. Map the target back to the plugins directory, as WordPress does for
// active plugins; if Kits Core is active itself, this sets the same mapping again. Without a kits-core folder or
// link in the plugins directory, its files have no public address and nothing here changes.
if ( defined( 'WP_PLUGIN_DIR' ) && function_exists( 'wp_register_plugin_realpath' ) ) {
	$kits_core_link = WP_PLUGIN_DIR . '/' . basename( __DIR__ ) . '/kits-core.php';
	if ( file_exists( $kits_core_link ) && realpath( $kits_core_link ) === __FILE__ ) {
		wp_register_plugin_realpath( $kits_core_link );
	}
	unset( $kits_core_link );
}

require_once KITS_CORE_DIR . 'includes/admin-table.php';
require_once KITS_CORE_DIR . 'includes/dashboard-cards.php';
require_once KITS_CORE_DIR . 'includes/admin-home.php';
require_once KITS_CORE_DIR . 'includes/altcha.php';
require_once KITS_CORE_DIR . 'includes/forms.php';
require_once KITS_CORE_DIR . 'includes/form-kit.php';
require_once KITS_CORE_DIR . 'includes/primitives.php';
require_once KITS_CORE_DIR . 'includes/blocks.php';
require_once KITS_CORE_DIR . 'includes/demo.php';
require_once KITS_CORE_DIR . 'includes/system-screen.php';
require_once KITS_CORE_DIR . 'includes/landmarks.php';
require_once KITS_CORE_DIR . 'includes/sections.php';
require_once KITS_CORE_DIR . 'includes/share.php';
require_once KITS_CORE_DIR . 'includes/image-context.php';
require_once KITS_CORE_DIR . 'includes/slugs.php';
require_once KITS_CORE_DIR . 'includes/analytics.php';
require_once KITS_CORE_DIR . 'includes/performance.php';
require_once KITS_CORE_DIR . 'includes/preview.php';
require_once KITS_CORE_DIR . 'includes/sourcing.php';
require_once KITS_CORE_DIR . 'includes/attribution.php';
require_once KITS_CORE_DIR . 'includes/theme-updates.php';
require_once KITS_CORE_DIR . 'includes/page-options.php';
require_once KITS_CORE_DIR . 'includes/settings-transfer.php';
require_once KITS_CORE_DIR . 'includes/theme-options.php';
require_once KITS_CORE_DIR . 'includes/admin-screens.php';
require_once KITS_CORE_DIR . 'includes/admin-app.php';
require_once KITS_CORE_DIR . 'includes/admin-app-global-styles.php';
require_once KITS_CORE_DIR . 'includes/admin-app-colours.php';
require_once KITS_CORE_DIR . 'includes/admin-app-buttons.php';
require_once KITS_CORE_DIR . 'includes/site-parts.php';
require_once KITS_CORE_DIR . 'includes/fonts.php';
