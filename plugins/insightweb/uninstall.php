<?php
/**
 * Uninstall InsightWeb: deletes nothing. Settings, keys, keyword tables and every report it has collected stay, so reinstalling finds everything as it was.
 * Removing data is a separate, deliberate step, never a side effect of deleting the plugin.
 *
 * @package insightweb
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
