<?php
/**
 * Plugin Name: Kits Core
 * Description: Kits by Wugweb, the site features every Kits site shares: form engine with spam guard (ALTCHA) and per-field errors, share bar, Google Tag Manager with consent mode, an honest visit counter, preview-only items, source attribution and the publisher records the sourcing plugin uses; plus the design system index of the Kits theme and its child. Own menu: Kits (Overview, Design system, Preview). Configured by the site through filters (kits_*).
 * Version: 1.0.0
 * Author: Wugweb
 * License: GPL-2.0-or-later
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

require_once KITS_CORE_DIR . 'includes/admin-table.php';
require_once KITS_CORE_DIR . 'includes/admin-home.php';
require_once KITS_CORE_DIR . 'includes/altcha.php';
require_once KITS_CORE_DIR . 'includes/forms.php';
require_once KITS_CORE_DIR . 'includes/form-kit.php';
require_once KITS_CORE_DIR . 'includes/primitives.php';
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
require_once KITS_CORE_DIR . 'includes/visits.php';
require_once KITS_CORE_DIR . 'includes/sourcing.php';
require_once KITS_CORE_DIR . 'includes/attribution.php';
