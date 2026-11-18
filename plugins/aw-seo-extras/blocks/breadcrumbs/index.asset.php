<?php
/**
 * Script dependencies of the Breadcrumbs block's editor script (no build step).
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'dependencies' => array( 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-server-side-render', 'wp-data' ),
	'version'      => (string) filemtime( __DIR__ . '/index.js' ),
);
