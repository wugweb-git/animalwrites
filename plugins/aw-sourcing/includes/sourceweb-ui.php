<?php
/**
 * SourceWeb's screens on the Kits admin shell, the default since 12 Nov 2026, on each earlier screen's own address.
 *
 * Addresses:
 *   admin.php?page=aw-sourcing-menu              Dashboard          (edit_others_posts)
 *   admin.php?page=aw-review                     To review          (edit_others_posts)
 *   admin.php?page=aw-enrich-review              Enrichment review  (edit_others_posts)
 *   admin.php?page=aw-sourcing&tab=settings      Settings           (manage_options)
 *
 * &ui=classic shows the earlier screens. The screens are one script (build/index.js, from src/), drawn with Kits
 * Core's shell parts and fed by the sourceweb/v1 routes (sourceweb-rest.php). When Kits Core or a build file is
 * missing, the earlier screen shows.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Which new screen this request asks for: 'dashboard', 'review', 'enrichment', 'settings' or ''.
 */
function sourceweb_ui_wanted(): string {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- which screen to show; nothing is saved here
	$page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) );
	$ui   = sanitize_key( wp_unslash( $_GET['ui'] ?? '' ) );
	$tab  = sanitize_key( wp_unslash( $_GET['tab'] ?? '' ) );
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	if ( 'classic' === $ui ) {
		return '';
	}
	if ( AW_SRC_PAGE === $page ) {
		return AW_SRC_SETTINGS_TAB === $tab ? 'settings' : '';
	}
	return array(
		AW_SRC_MENU        => 'dashboard',
		'aw-review'        => 'review',
		'aw-enrich-review' => 'enrichment',
	)[ $page ] ?? '';
}

/** The capability each new screen needs. */
function sourceweb_ui_caps(): array {
	return array(
		'dashboard'  => AW_REVIEW_CAP,
		'review'     => AW_REVIEW_CAP,
		'enrichment' => AW_ENRICH_CAP,
		'settings'   => AW_SRC_CAP,
	);
}

/**
 * The new screen is on this request (its script enqueued). Set on admin_enqueue_scripts.
 *
 * @param bool|null $set Set the flag.
 */
function sourceweb_ui_ready( ?bool $set = null ): bool {
	static $ready = false;
	if ( null !== $set ) {
		$ready = $set;
	}
	return $ready;
}

/**
 * The address of a new screen.
 *
 * @param string $screen 'dashboard', 'review', 'enrichment' or 'settings'.
 */
function sourceweb_ui_url( string $screen ): string {
	$q = array(
		'dashboard'  => array( 'page' => AW_SRC_MENU ),
		'review'     => array( 'page' => 'aw-review' ),
		'enrichment' => array( 'page' => 'aw-enrich-review' ),
		'settings'   => array(
			'page' => AW_SRC_PAGE,
			'tab'  => AW_SRC_SETTINGS_TAB,
		),
	)[ $screen ] ?? array( 'page' => AW_SRC_MENU );
	return add_query_arg( $q + array( 'ui' => 'next' ), admin_url( 'admin.php' ) );
}

add_action(
	'admin_enqueue_scripts',
	function () {
		$screen = sourceweb_ui_wanted();
		if ( '' === $screen || ! function_exists( 'kits_shell_app' ) || ! current_user_can( sourceweb_ui_caps()[ $screen ] ) ) {
			return;
		}
		$head   = get_file_data( AW_SOURCING_DIR . 'aw-sourcing.php', array( 'Version' => 'Version' ) );
		$labels = array(
			'dashboard'  => __( 'Dashboard', 'aw-sourcing' ),
			'review'     => __( 'To review', 'aw-sourcing' ),
			'enrichment' => __( 'Enrichment review', 'aw-sourcing' ),
			'settings'   => __( 'Settings', 'aw-sourcing' ),
		);
		$tabs   = array();
		foreach ( sourceweb_ui_caps() as $slug => $cap ) {
			if ( current_user_can( $cap ) ) {
				$tabs[] = array(
					'slug'    => $slug,
					'label'   => $labels[ $slug ],
					'url'     => sourceweb_ui_url( $slug ),
					'current' => $slug === $screen,
				);
			}
		}
		$ok = kits_shell_app(
			array(
				'handle'      => 'sourceweb-admin',
				'src'         => plugins_url( 'build/index.js', AW_SOURCING_DIR . 'aw-sourcing.php' ),
				'asset'       => AW_SOURCING_DIR . 'build/index.asset.php',
				'object'      => 'sourcewebApp',
				'text_domain' => 'aw-sourcing',
				'data'        => array(
					'screen'  => $screen,
					'name'    => 'SourceWeb',
					'version' => (string) $head['Version'],
					'icon'    => plugins_url( 'assets/icon.svg', AW_SOURCING_DIR . 'aw-sourcing.php' ),
					'tabs'    => $tabs,
					'rest'    => SOURCEWEB_REST,
					'isAdmin' => current_user_can( AW_SRC_CAP ),
				),
			)
		);
		sourceweb_ui_ready( $ok );
	}
);

/**
 * Draw the new screen in place of the current one, when it is ready. The page callbacks call this first.
 *
 * @param string $screen 'dashboard', 'review', 'enrichment' or 'settings'.
 * @return bool True when the new screen was drawn.
 */
function sourceweb_ui_page( string $screen ): bool {
	if ( sourceweb_ui_wanted() !== $screen || ! sourceweb_ui_ready() ) {
		return false;
	}
	$titles = array(
		'dashboard'  => __( 'SourceWeb', 'aw-sourcing' ),
		'review'     => __( 'To review', 'aw-sourcing' ),
		'enrichment' => __( 'Enrichment review', 'aw-sourcing' ),
		'settings'   => __( 'SourceWeb settings', 'aw-sourcing' ),
	);
	kits_shell_mount( 'sourceweb-app', $titles[ $screen ] );
	return true;
}
