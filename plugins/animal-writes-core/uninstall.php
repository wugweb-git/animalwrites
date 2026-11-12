<?php
/**
 * Uninstall Animal Writes Core: deletes nothing. Settings, listings, campaigns, letters, places, form records, media groups and every table it keeps stay, so reinstalling finds everything as it was.
 * Removing data is a separate, deliberate step, never a side effect of deleting the plugin.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
