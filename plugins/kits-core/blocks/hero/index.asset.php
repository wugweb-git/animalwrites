<?php
/**
 * Script dependencies of the Hero block's editor script (no build step).
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'dependencies' => array( 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-i18n' ),
	'version'      => (string) filemtime( __DIR__ . '/index.js' ),
);
