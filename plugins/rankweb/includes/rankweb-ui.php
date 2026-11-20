<?php
/**
 * RankWeb's screens on the Kits admin shell: admin.php?page=aw-seo-plus (Dashboard: the counts and the tools),
 * aw-seo-insights (Insights: trends and gaps), aw-seo-modules (Modules: switches, the modules table, where things
 * are), aw-faq (FAQ, from its own output through Kits Core's page parts) and aw-seo-settings (Settings). The screens are one
 * script (build/index.js, from src/), drawn with Kits Core's shell parts (kits-core/includes/admin-shell.php) and fed
 * by the rankweb/v1 routes (rankweb-rest.php).
 *
 * When Kits Core or a build file is missing, the screen shows a notice that it needs Kits Core. The front end (titles,
 * schema, sitemap and the rest) never needs Kits Core.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Which screen this request asks for: 'dashboard', 'insights', 'modules', 'faq', 'settings' or ''.
 */
function rankweb_ui_wanted(): string {
	$page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which screen to show; nothing is saved here
	return array(
		AW_SEO_MENU       => 'dashboard',
		'aw-seo-insights' => 'insights',
		'aw-seo-modules'  => 'modules',
		'aw-faq'          => 'faq',
		'aw-seo-settings' => 'settings',
	)[ $page ] ?? '';
}

/**
 * The screen is on this request (its script enqueued). Set on admin_enqueue_scripts.
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
 * The address of a screen.
 *
 * @param string $screen 'dashboard', 'insights', 'modules' or 'settings'.
 */
function rankweb_ui_url( string $screen ): string {
	$page = array(
		'settings' => 'aw-seo-settings',
		'insights' => 'aw-seo-insights',
		'modules'  => 'aw-seo-modules',
	)[ $screen ] ?? AW_SEO_MENU;
	return add_query_arg( 'page', $page, admin_url( 'admin.php' ) );
}

/**
 * RankWeb's working screens (the classic pages under the RankWeb menu) the current user can open, for the Dashboard's
 * Tools card. Read from the menu itself, so a screen of a module switched off is not listed.
 *
 * @return array[] [ { label, desc, url } ].
 */
function rankweb_ui_tools(): array {
	$about = array(
		'aw-links'     => __( 'The internal-link audit, anchor mix and silos.', 'aw-seo-extras' ),
		'aw-images'    => __( 'Find and replace alt text, titles and captions across the media library.', 'aw-seo-extras' ),
		'aw-seo-csv'   => __( 'Export and import SEO fields as a CSV file.', 'aw-seo-extras' ),
		'aw-redirects' => __( 'Redirect imports, exports and the 404 log.', 'aw-seo-extras' ),
	);
	$items = (array) ( $GLOBALS['submenu'][ AW_SEO_MENU ] ?? array() );
	$tools = array();
	foreach ( $about as $slug => $desc ) {
		foreach ( $items as $item ) {
			if ( ( $item[2] ?? '' ) === $slug && current_user_can( (string) $item[1] ) ) {
				$tools[] = array(
					'label' => wp_strip_all_tags( (string) $item[0] ),
					'desc'  => $desc,
					'url'   => add_query_arg( 'page', $slug, admin_url( 'admin.php' ) ),
				);
				break;
			}
		}
	}
	return $tools;
}

add_action(
	'admin_enqueue_scripts',
	function () {
		$screen = rankweb_ui_wanted();
		if ( '' === $screen || ! function_exists( 'kits_shell_app' ) || ! is_readable( AW_SEO_EXTRAS_DIR . 'build/index.js' ) || ! current_user_can( 'settings' === $screen ? 'manage_options' : RANKWEB_CAP ) ) {
			return;
		}
		$main_file = defined( 'RANKWEB_FILE' ) ? RANKWEB_FILE : ( file_exists( AW_SEO_EXTRAS_DIR . 'rankweb.php' ) ? AW_SEO_EXTRAS_DIR . 'rankweb.php' : AW_SEO_EXTRAS_DIR . 'aw-seo-extras.php' );
		$head      = get_file_data( $main_file, array( 'Version' => 'Version' ) );
		$tabs = array(
			array(
				'slug'    => 'dashboard',
				'label'   => __( 'Dashboard', 'aw-seo-extras' ),
				'url'     => rankweb_ui_url( 'dashboard' ),
				'current' => 'dashboard' === $screen,
			),
			array(
				'slug'    => 'insights',
				'label'   => __( 'Insights', 'aw-seo-extras' ),
				'url'     => rankweb_ui_url( 'insights' ),
				'current' => 'insights' === $screen,
			),
			array(
				'slug'    => 'modules',
				'label'   => __( 'Modules', 'aw-seo-extras' ),
				'url'     => rankweb_ui_url( 'modules' ),
				'current' => 'modules' === $screen,
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
				'src'         => plugins_url( 'build/index.js', $main_file ),
				'asset'       => AW_SEO_EXTRAS_DIR . 'build/index.asset.php',
				'object'      => 'rankwebApp',
				'text_domain' => 'rankweb',
				'data'        => array(
					'screen'    => $screen,
					'name'      => 'RankWeb',
					'version'   => (string) $head['Version'],
					'icon'      => plugins_url( 'assets/icon.svg', $main_file ),
					'tabs'      => $tabs,
					'rest'      => RANKWEB_REST,
					'faqUrl'    => add_query_arg( 'page', 'aw-faq', admin_url( 'admin.php' ) ),
					// The Dashboard's module switches save through settings/modules (manage_options).
					'canSwitch' => current_user_can( 'manage_options' ),
					// The FAQ screen's own output as parts, after its load hook ran (Kits Core's page parts).
					'parts'     => 'faq' === $screen ? rankweb_faq_parts() : array(),
					// The Dashboard's Tools card.
					'tools'     => 'dashboard' === $screen ? rankweb_ui_tools() : array(),
				),
			)
		);
		rankweb_ui_ready( $ok );
		if ( $ok ) {
			// Layout-only overrides on RankWeb's own shell screens (full-width data tables, dashboard chart grids).
			// Scoped to the body class below, so no other Kits plugin's shell is affected; the shell package is untouched.
			wp_enqueue_style(
				'rankweb-admin-css',
				plugins_url( 'assets/admin.css', $main_file ),
				array( 'kits-shell' ),
				(string) $head['Version']
			);
			add_filter( 'admin_body_class', 'rankweb_ui_body_class' );
		}
	}
);

/**
 * The body class that scopes RankWeb's layout overrides (assets/admin.css) to its own shell screens.
 *
 * @param string $classes Admin body classes.
 */
function rankweb_ui_body_class( $classes ) {
	return trim( (string) $classes . ' rankweb-shell-screen' );
}

/**
 * Draw a screen: the shell when its script is enqueued, else a notice that it needs Kits Core. The page callbacks
 * call this.
 *
 * @param string $screen 'dashboard', 'insights', 'modules', 'faq' or 'settings'.
 */
function rankweb_ui_page( string $screen ): void {
	$title = array(
		'settings' => __( 'RankWeb settings', 'aw-seo-extras' ),
		'faq'      => __( 'FAQ', 'aw-seo-extras' ),
		'insights' => __( 'RankWeb insights', 'aw-seo-extras' ),
		'modules'  => __( 'RankWeb modules', 'aw-seo-extras' ),
	)[ $screen ] ?? __( 'RankWeb', 'aw-seo-extras' );
	if ( rankweb_ui_ready() && function_exists( 'kits_shell_mount' ) ) {
		kits_shell_mount( 'rankweb-app', $title );
		return;
	}
	echo '<div class="wrap"><h1>' . esc_html( $title ) . '</h1>';
	wp_admin_notice( esc_html__( 'RankWeb needs Kits Core (active, with its build) to show this screen.', 'aw-seo-extras' ), array( 'type' => 'error' ) );
	echo '</div>';
}

/** The FAQ screen's output (aw_faq_admin_body()) as page parts for the shell screen. */
function rankweb_faq_parts(): array {
	if ( ! function_exists( 'aw_faq_admin_body' ) || ! function_exists( 'kits_shell_html_parts' ) ) {
		return array();
	}
	ob_start();
	aw_faq_admin_body();
	return kits_shell_html_parts( (string) ob_get_clean() );
}
