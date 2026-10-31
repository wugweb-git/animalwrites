<?php
/**
 * Mailweb
 *
 * @package           mailweb
 * @author            Wugweb
 * @copyright         2026 Wugweb
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Mailweb
 * Description:       Hostinger Mail inside WordPress: read, reply and send from your mailboxes, website form messages in the same inbox, plus SMTP sending with a daily limit and queue and a FluentCRM feed. Own menu: Mail (Inbox, Overview, Settings).
 * Version:           3.1.1
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Wugweb
 * Author URI:        https://wugweb.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mailweb
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MAILWEB_VERSION', '3.1.1' );
define( 'MAILWEB_FILE', __FILE__ );
define( 'MAILWEB_DIR', plugin_dir_path( __FILE__ ) );
define( 'MAILWEB_WEBMAIL', 'https://mail.hostinger.com' );
define( 'MAILWEB_API_HOST', 'https://api.mail.hostinger.com' );

// The 5-minute schedule the queue and the FluentCRM feed use.
add_filter(
	'cron_schedules',
	function ( $s ) {
		$s['mailweb_5min'] = $s['mailweb_5min'] ?? array(
			'interval' => 300,
			'display'  => __( 'Every 5 minutes', 'mailweb' ),
		);
		return $s;
	}
);

add_action(
	'init',
	function () {
		load_plugin_textdomain( 'mailweb', false, dirname( plugin_basename( MAILWEB_FILE ) ) . '/languages' ); // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- sold outside WordPress.org: translations ship in /languages
	}
);

require_once MAILWEB_DIR . 'includes/admin-table.php';
require_once MAILWEB_DIR . 'includes/crypto.php';
require_once MAILWEB_DIR . 'includes/addresses.php';
require_once MAILWEB_DIR . 'includes/mail-in.php';
require_once MAILWEB_DIR . 'includes/smtp.php';
require_once MAILWEB_DIR . 'includes/mail-cap.php';
require_once MAILWEB_DIR . 'includes/mailboxes.php';
require_once MAILWEB_DIR . 'includes/profiles.php';
require_once MAILWEB_DIR . 'includes/inbox.php';
require_once MAILWEB_DIR . 'includes/settings-tab.php';
require_once MAILWEB_DIR . 'includes/crm-sync.php';
require_once MAILWEB_DIR . 'includes/overview.php';
require_once MAILWEB_DIR . 'includes/home.php';

// Deactivation stops Mailweb's scheduled jobs; settings, the queue and the mailboxes are left as they are.
register_deactivation_hook(
	__FILE__,
	function () {
		foreach ( array( 'mailweb_flush', 'mailweb_crm_sync' ) as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}
);
