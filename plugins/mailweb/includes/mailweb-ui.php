<?php
/**
 * MailWeb's screens on the Kits admin shell, the default since 12 Nov 2026:
 *
 * - admin.php?page=mailweb-overview   Dashboard (MAILWEB_CAP)
 * - admin.php?page=mailweb            Inbox, the working list (MAILWEB_CAP)
 * - admin.php?page=mailweb-settings   Settings (manage_options)
 *
 * &ui=classic shows the earlier screens. The screens are one script (build/index.js, from src/), drawn with Kits
 * Core's shell parts and fed by the mailweb/v1 routes (mailweb-rest.php). When Kits Core or a build file is missing,
 * the earlier screen shows.
 *
 * @package mailweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MAILWEB_REST = 'mailweb/v1';

/** Which new screen this request asks for: 'dashboard', 'inbox', 'settings' or ''. */
function mailweb_ui_wanted(): string {
	$page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which screen to show; nothing is saved here
	$ui   = sanitize_key( wp_unslash( $_GET['ui'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- same
	if ( 'classic' === $ui ) {
		return '';
	}
	// Today's Inbox handles its message, form item and compose views itself.
	if ( 'mailweb' === $page && ( isset( $_GET['uid'] ) || isset( $_GET['item'] ) || isset( $_GET['compose'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- same
		return '';
	}
	return array(
		'mailweb-overview' => 'dashboard',
		'mailweb'          => 'inbox',
		'mailweb-settings' => 'settings',
	)[ $page ] ?? '';
}

/**
 * The new screen is on this request (its script enqueued). Set on admin_enqueue_scripts.
 *
 * @param bool|null $set Set the flag.
 */
function mailweb_ui_ready( ?bool $set = null ): bool {
	static $ready = false;
	if ( null !== $set ) {
		$ready = $set;
	}
	return $ready;
}

/**
 * The address of a new screen.
 *
 * @param string $screen 'dashboard', 'inbox' or 'settings'.
 * @param array  $args   More query arguments.
 */
function mailweb_ui_url( string $screen, array $args = array() ): string {
	$page = array(
		'dashboard' => 'mailweb-overview',
		'inbox'     => 'mailweb',
		'settings'  => 'mailweb-settings',
	)[ $screen ] ?? 'mailweb-overview';
	return add_query_arg(
		array_merge(
			array(
				'page' => $page,
				'ui'   => 'next',
			),
			$args
		),
		admin_url( 'admin.php' )
	);
}

add_action(
	'admin_enqueue_scripts',
	function () {
		$screen = mailweb_ui_wanted();
		if ( '' === $screen || ! function_exists( 'kits_shell_app' ) || ! current_user_can( 'settings' === $screen ? 'manage_options' : MAILWEB_CAP ) ) {
			return;
		}
		$admin = current_user_can( 'manage_options' );
		$tabs  = array(
			array(
				'slug'    => 'dashboard',
				'label'   => __( 'Dashboard', 'mailweb' ),
				'url'     => mailweb_ui_url( 'dashboard' ),
				'current' => 'dashboard' === $screen,
			),
			array(
				'slug'    => 'inbox',
				'label'   => __( 'Inbox', 'mailweb' ),
				'url'     => mailweb_ui_url( 'inbox' ),
				'current' => 'inbox' === $screen,
			),
		);
		if ( $admin ) {
			$tabs[] = array(
				'slug'    => 'settings',
				'label'   => __( 'Settings', 'mailweb' ),
				'url'     => mailweb_ui_url( 'settings' ),
				'current' => 'settings' === $screen,
			);
		}
		$ok = kits_shell_app(
			array(
				'handle'      => 'mailweb-admin',
				'src'         => plugins_url( 'build/index.js', MAILWEB_FILE ),
				'asset'       => MAILWEB_DIR . 'build/index.asset.php',
				'object'      => 'mailwebApp',
				'text_domain' => 'mailweb',
				'lang_path'   => MAILWEB_DIR . 'languages',
				'data'        => array(
					'screen'     => $screen,
					'name'       => 'MailWeb',
					'version'    => MAILWEB_VERSION,
					'icon'       => plugins_url( 'assets/icon.svg', MAILWEB_FILE ),
					'tabs'       => $tabs,
					'oldUrl'     => add_query_arg( 'ui', 'classic' ),
					'rest'       => MAILWEB_REST,
					'admin'      => $admin,
					'inboxUrl'   => mailweb_ui_url( 'inbox' ),
					'composeUrl' => add_query_arg(
						array(
							'page'    => 'mailweb',
							'compose' => 'new',
						),
						admin_url( 'admin.php' )
					),
					'webmail'    => MAILWEB_WEBMAIL,
					'oldSending' => mailweb_settings_url( 'sending', array(), 'cap' ),
				),
			)
		);
		if ( $ok && 'inbox' === $screen && file_exists( MAILWEB_DIR . 'build/index.css' ) ) {
			$asset = include MAILWEB_DIR . 'build/index.asset.php';
			wp_enqueue_style( 'mailweb-admin', plugins_url( 'build/index.css', MAILWEB_FILE ), array( 'kits-shell' ), $asset['version'] ?? MAILWEB_VERSION );
		}
		mailweb_ui_ready( $ok );
	}
);

/**
 * Draw the new screen in place of today's, when it is ready. The page callbacks call this first.
 *
 * @param string $screen 'dashboard', 'inbox' or 'settings'.
 * @return bool True when the new screen was drawn.
 */
function mailweb_ui_page( string $screen ): bool {
	if ( mailweb_ui_wanted() !== $screen || ! mailweb_ui_ready() ) {
		return false;
	}
	$titles = array(
		'dashboard' => __( 'MailWeb', 'mailweb' ),
		'inbox'     => __( 'MailWeb inbox', 'mailweb' ),
		'settings'  => __( 'MailWeb settings', 'mailweb' ),
	);
	kits_shell_mount( 'mailweb-app', $titles[ $screen ], add_query_arg( 'ui', 'classic' ), __( 'Open the current screen', 'mailweb' ) );
	return true;
}
