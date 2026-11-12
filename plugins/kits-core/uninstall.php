<?php
/**
 * Uninstall Kits Core: deletes nothing. Theme options, form settings, saved keys, publisher records and visit counts stay, so reinstalling finds everything as it was.
 * Removing data is a separate, deliberate step, never a side effect of deleting the plugin.
 *
 * @package kits-core
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
