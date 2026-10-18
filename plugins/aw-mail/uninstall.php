<?php
/**
 * Uninstall Wug Mail: deletes nothing by default. Inbox items, settings, webhook secrets and the block list stay, so
 * reinstalling (or animal-writes-core) finds everything as it was.
 *
 * @package aw-mail
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
