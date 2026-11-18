<?php
/**
 * MailWeb
 *
 * @package           mailweb
 * @author            Wugweb
 * @copyright         2026 Wugweb
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       MailWeb
 * Description:       Hostinger Mail inside WordPress: read, reply and send from your mailboxes, website form messages in the same inbox, plus SMTP sending with a daily limit and queue and a FluentCRM feed. Own menu: Mail (Inbox, Overview, Settings).
 * Version:           3.3.0
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

define( 'MAILWEB_VERSION', '3.3.0' );
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

// Translations load from WordPress.org automatically (WP 4.6+); no load_plugin_textdomain() call is needed.

require_once MAILWEB_DIR . 'includes/mailweb-caps.php'; // MailWeb's own capability, MAILWEB_CAP = manage_mailweb (granted from edit_others_posts).
require_once MAILWEB_DIR . 'includes/admin-table.php';
require_once MAILWEB_DIR . 'includes/help-tab.php';
require_once MAILWEB_DIR . 'includes/crypto.php';
require_once MAILWEB_DIR . 'includes/providers.php'; // the mail-provider seam (Hostinger adapter today; IMAP later).
require_once MAILWEB_DIR . 'includes/addresses.php';
require_once MAILWEB_DIR . 'includes/mailweb-settings-save.php';
require_once MAILWEB_DIR . 'includes/mail-in.php';
require_once MAILWEB_DIR . 'includes/smtp.php';
require_once MAILWEB_DIR . 'includes/mail-cap.php';
require_once MAILWEB_DIR . 'includes/mailboxes.php';
require_once MAILWEB_DIR . 'includes/profiles.php';
require_once MAILWEB_DIR . 'includes/utm.php'; // the site's own links in its emails tagged (utm_source=site-email).
require_once MAILWEB_DIR . 'includes/inbox.php';
require_once MAILWEB_DIR . 'includes/settings-tab.php';
require_once MAILWEB_DIR . 'includes/crm-sync.php';
require_once MAILWEB_DIR . 'includes/overview.php';
require_once MAILWEB_DIR . 'includes/home.php';
require_once MAILWEB_DIR . 'includes/mailweb-shell.php'; // the bundled admin shell (self-contained; no dependency on any other plugin).
require_once MAILWEB_DIR . 'includes/mailweb-ui.php'; // the screens on the bundled shell.
require_once MAILWEB_DIR . 'includes/mailweb-rest.php';
require_once MAILWEB_DIR . 'includes/mailweb-dashboard.php';

// Deactivation stops MailWeb's scheduled jobs; settings, the queue and the mailboxes are left as they are.
register_deactivation_hook(
	__FILE__,
	function () {
		foreach ( array( 'mailweb_flush', 'mailweb_crm_sync', 'mailweb_dashboard_build' ) as $hook ) {
			wp_unschedule_hook( $hook ); // Every event of the hook, with or without arguments.
		}
	}
);
