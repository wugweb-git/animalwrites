<?php
/**
 * Uninstall RankWeb: deletes nothing. Settings, FAQ records, focus keywords and the scores it has stored stay, so reinstalling finds everything as it was.
 * Removing data is a separate, deliberate step, never a side effect of deleting the plugin.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
