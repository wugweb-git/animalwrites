<?php
/**
 * Plugin Name: Kits Core
 * Description: Site features shared by Kits sites: form engine with spam guard and per-field errors, share bar, SEO meta, Google Tag Manager, ad slots and ads.txt, an honest visit counter, source attribution (favicon + source + relative time) and sourcing controls (publisher allow/block, relevance keywords). Configured by the site through filters (kits_*).
 * Version: 0.1.0
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
define( 'KITS_CORE_LOADED', '0.1.0' );
define( 'KITS_CORE_DIR', __DIR__ . '/' );

require_once KITS_CORE_DIR . 'includes/altcha.php';
require_once KITS_CORE_DIR . 'includes/forms.php';
require_once KITS_CORE_DIR . 'includes/form-kit.php';
require_once KITS_CORE_DIR . 'includes/primitives.php';
require_once KITS_CORE_DIR . 'includes/demo.php';
require_once KITS_CORE_DIR . 'includes/system-screen.php';
require_once KITS_CORE_DIR . 'includes/landmarks.php';
require_once KITS_CORE_DIR . 'includes/mail.php';
require_once KITS_CORE_DIR . 'includes/share.php';
require_once KITS_CORE_DIR . 'includes/seo-fields.php';
require_once KITS_CORE_DIR . 'includes/seo.php';
require_once KITS_CORE_DIR . 'includes/analytics.php';
require_once KITS_CORE_DIR . 'includes/ads.php';
require_once KITS_CORE_DIR . 'includes/visits.php';
require_once KITS_CORE_DIR . 'includes/sourcing.php';
require_once KITS_CORE_DIR . 'includes/attribution.php';
