<?php
/**
 * Uninstall Mailweb: deletes nothing. Settings, profiles, the queue table and saved secrets stay, so reinstalling
 * finds everything as it was. Nothing in the mailboxes is ever touched.
 *
 * @package mailweb
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
