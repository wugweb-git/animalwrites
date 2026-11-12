<?php
/**
 * Uninstall SourceWeb: deletes nothing. Sources, keywords, rules, review queues, logs, AI model settings and the source icons in the media library stay, so reinstalling finds everything as it was.
 * Removing data is a separate, deliberate step, never a side effect of deleting the plugin.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
