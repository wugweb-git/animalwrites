<?php
/**
 * Kits by Wugweb — theme setup.
 *
 * @package kits-by-wugweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// A site's own templates, parts, patterns and files (assets/site/, never in the distributed theme): loaded first, where
// a child theme's functions.php would load, so the site's hooks come before the theme's own.
require_once get_template_directory() . '/inc/site-layer.php';

// The theme's code, by purpose (inc/), in the order it runs.
require_once get_template_directory() . '/inc/store.php';
require_once get_template_directory() . '/inc/assets.php';
require_once get_template_directory() . '/inc/blocks.php';
require_once get_template_directory() . '/inc/component-styles.php';
