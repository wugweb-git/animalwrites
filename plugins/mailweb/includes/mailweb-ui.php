<?php
/**
 * MailWeb's screens on the Kits admin shell, the default since 12 Nov 2026:
 *
 * - admin.php?page=mailweb-overview   Dashboard (MAILWEB_CAP)
 * - admin.php?page=mailweb            Inbox, the working list (MAILWEB_CAP)
 * - admin.php?page=mailweb-settings   Settings (manage_options)
 *
 * The screens are one script (build/index.js, from src/), drawn with Kits Core's shell parts and fed by the mailweb/v1
 * routes (mailweb-rest.php). When Kits Core or a build file is missing, the page shows a notice instead. The Inbox's
 * message, form item and compose views and Settings → Site sending are server-drawn (inbox.php, settings-tab.php).
 *
 * @package mailweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MAILWEB_REST = 'mailweb/v1';

/** Which shell screen this request asks for: 'dashboard', 'inbox', 'settings' or ''. */
function mailweb_ui_wanted(): string {
	$page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which screen to show; nothing is saved here
	// The Inbox's message, form item and compose views, and Settings → Site sending, are drawn by the page itself.
	if ( 'mailweb' === $page && '' !== mailweb_inbox_view( mailweb_state() ) ) {
		return '';
	}
	if ( 'mailweb-settings' === $page && 'sending' === sanitize_key( wp_unslash( $_GET['sub'] ?? '' ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- same
		return '';
	}
	return array(
		'mailweb-overview' => 'dashboard',
		'mailweb'          => 'inbox',
		'mailweb-settings' => 'settings',
	)[ $page ] ?? '';
}

/**
 * The shell screen is on this request (its script enqueued). Set on admin_enqueue_scripts.
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
 * The address of a shell screen.
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
	return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
}

add_action(
	'admin_enqueue_scripts',
	function () {
		$screen = mailweb_ui_wanted();
		if ( '' === $screen || ! function_exists( 'kits_shell_app' ) || ! current_user_can( 'settings' === $screen ? 'manage_options' : MAILWEB_CAP ) ) {
			return;
		}
		$admin = current_user_can( 'manage_options' );
		if ( 'settings' === $screen ) {
			wp_enqueue_media(); // The Profiles picture picker (MediaField).
		}
		$tabs = array(
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
					'features'   => 'dashboard' === $screen && function_exists( 'mailweb_home_parts' ) ? mailweb_home_parts() : array(),
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
 * Draw the shell screen, when it is ready. The page callbacks call this first.
 *
 * @param string $screen 'dashboard', 'inbox' or 'settings'.
 * @return bool True when the shell screen was drawn.
 */
function mailweb_ui_page( string $screen ): bool {
	if ( mailweb_ui_wanted() !== $screen || ! mailweb_ui_ready() || ! function_exists( 'kits_shell_mount' ) ) {
		return false;
	}
	$titles = array(
		'dashboard' => __( 'MailWeb', 'mailweb' ),
		'inbox'     => __( 'MailWeb inbox', 'mailweb' ),
		'settings'  => __( 'MailWeb settings', 'mailweb' ),
	);
	if ( 'inbox' === $screen && function_exists( 'mailweb_notice' ) ) {
		mailweb_notice(); // the result of a send, move or flag from the message and compose views
	}
	kits_shell_mount( 'mailweb-app', $titles[ $screen ] );
	return true;
}

/**
 * The page in place of a shell screen that could not be drawn (Kits Core inactive, or its build or this plugin's
 * build missing): the title and one error notice.
 *
 * @param string $title Screen title.
 */
function mailweb_ui_missing( string $title ) {
	echo '<div class="wrap"><h1>' . esc_html( $title ) . '</h1>';
	wp_admin_notice( esc_html__( 'MailWeb needs Kits Core (active, with its build) to show this screen.', 'mailweb' ), array( 'type' => 'error' ) );
	echo '</div>';
}
