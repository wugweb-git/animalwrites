<?php
/**
 * RankWeb's screens on the Kits admin shell, the default since 12 Nov 2026: admin.php?page=aw-seo-plus (Dashboard),
 * admin.php?page=aw-faq (FAQ, from its own output through Kits Core's page parts) and admin.php?page=aw-seo-settings
 * (Settings); &ui=classic shows the earlier screens. The screens are one script
 * (build/index.js, from src/), drawn with Kits Core's shell parts (kits-core/includes/admin-shell.php) and fed by the
 * rankweb/v1 routes (rankweb-rest.php).
 *
 * When Kits Core or a build file is missing, the earlier screen shows.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Which new screen this request asks for: 'dashboard', 'faq', 'settings' or ''.
 */
function rankweb_ui_wanted(): string {
	$page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which screen to show; nothing is saved here
	$ui   = sanitize_key( wp_unslash( $_GET['ui'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- same
	if ( 'classic' === $ui ) {
		return '';
	}
	return array(
		AW_SEO_MENU       => 'dashboard',
		'aw-faq'          => 'faq',
		'aw-seo-settings' => 'settings',
	)[ $page ] ?? '';
}

/**
 * The new screen is on this request (its script enqueued). Set on admin_enqueue_scripts.
 *
 * @param bool|null $set Set the flag.
 */
function rankweb_ui_ready( ?bool $set = null ): bool {
	static $ready = false;
	if ( null !== $set ) {
		$ready = $set;
	}
	return $ready;
}

/**
 * The address of a new screen.
 *
 * @param string $screen 'dashboard' or 'settings'.
 */
function rankweb_ui_url( string $screen ): string {
	return add_query_arg(
		array(
			'page' => 'settings' === $screen ? 'aw-seo-settings' : AW_SEO_MENU,
			'ui'   => 'next',
		),
		admin_url( 'admin.php' )
	);
}

add_action(
	'admin_enqueue_scripts',
	function () {
		$screen = rankweb_ui_wanted();
		if ( '' === $screen || ! function_exists( 'kits_shell_app' ) || ! current_user_can( 'settings' === $screen ? 'manage_options' : 'manage_categories' ) ) {
			return;
		}
		$head = get_file_data( AW_SEO_EXTRAS_DIR . 'aw-seo-extras.php', array( 'Version' => 'Version' ) );
		$tabs = array(
			array(
				'slug'    => 'dashboard',
				'label'   => __( 'Dashboard', 'aw-seo-extras' ),
				'url'     => rankweb_ui_url( 'dashboard' ),
				'current' => 'dashboard' === $screen,
			),
			array(
				'slug'    => 'faq',
				'label'   => __( 'FAQ', 'aw-seo-extras' ),
				'url'     => add_query_arg( 'page', 'aw-faq', admin_url( 'admin.php' ) ),
				'current' => 'faq' === $screen,
			),
		);
		// No Search Insights tab: it is InsightWeb's, with its own menu (owner, 11 Nov 2026).
		if ( current_user_can( 'manage_options' ) ) {
			$tabs[] = array(
				'slug'    => 'settings',
				'label'   => __( 'Settings', 'aw-seo-extras' ),
				'url'     => rankweb_ui_url( 'settings' ),
				'current' => 'settings' === $screen,
			);
		}
		if ( 'settings' === $screen ) {
			wp_enqueue_media(); // The default share image picker (MediaField).
		}
		$ok = kits_shell_app(
			array(
				'handle'      => 'rankweb-admin',
				'src'         => plugins_url( 'build/index.js', AW_SEO_EXTRAS_DIR . 'aw-seo-extras.php' ),
				'asset'       => AW_SEO_EXTRAS_DIR . 'build/index.asset.php',
				'object'      => 'rankwebApp',
				'text_domain' => 'aw-seo-extras',
				'data'        => array(
					'screen'  => $screen,
					'name'    => 'RankWeb',
					'version' => (string) $head['Version'],
					'icon'    => plugins_url( 'assets/icon.svg', AW_SEO_EXTRAS_DIR . 'aw-seo-extras.php' ),
					'tabs'    => $tabs,
					'rest'    => RANKWEB_REST,
					'faqUrl'  => add_query_arg( 'page', 'aw-faq', admin_url( 'admin.php' ) ),
					// The FAQ screen's own output as parts, after its load hook ran (Kits Core's page parts).
					'parts'   => 'faq' === $screen ? rankweb_faq_parts() : array(),
					// The Dashboard's Modules table and the rest of the Overview, from its own output (Kits' page parts).
					'modules' => 'dashboard' === $screen ? rankweb_overview_parts() : array(),
				),
			)
		);
		rankweb_ui_ready( $ok );
	}
);

/**
 * Draw the new screen in place of the current one, when it is ready. The page callbacks call this first.
 *
 * @param string $screen 'dashboard', 'faq' or 'settings'.
 * @return bool True when the new screen was drawn.
 */
function rankweb_ui_page( string $screen ): bool {
	if ( rankweb_ui_wanted() !== $screen || ! rankweb_ui_ready() ) {
		return false;
	}
	$title = array(
		'settings' => __( 'RankWeb settings', 'aw-seo-extras' ),
		'faq'      => __( 'FAQ', 'aw-seo-extras' ),
	)[ $screen ] ?? __( 'RankWeb', 'aw-seo-extras' );
	kits_shell_mount( 'rankweb-app', $title );
	return true;
}

/** The FAQ screen's output (aw_faq_admin_body()) as page parts for the new screen. */
function rankweb_faq_parts(): array {
	if ( ! function_exists( 'aw_faq_admin_body' ) || ! function_exists( 'kits_shell_html_parts' ) ) {
		return array();
	}
	ob_start();
	aw_faq_admin_body();
	return kits_shell_html_parts( (string) ob_get_clean() );
}

/** The Overview's body (aw_seo_overview_body(): Modules, On edit screens, Commands) as page parts for the Dashboard. */
function rankweb_overview_parts(): array {
	if ( ! function_exists( 'aw_seo_overview_body' ) || ! function_exists( 'kits_shell_html_parts' ) ) {
		return array();
	}
	ob_start();
	aw_seo_overview_body();
	return kits_shell_html_parts( (string) ob_get_clean() );
}
