<?php
/**
 * Uninstall PlanWeb: deletes nothing. Settings and every calendar entry stay, so reinstalling finds everything as it was.
 * Removing data is a separate, deliberate step, never a side effect of deleting the plugin.
 *
 * @package planweb
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
