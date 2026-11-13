<?php
/**
 * SourceWeb's screens on the Kits admin shell.
 *
 * Addresses:
 *   admin.php?page=aw-sourcing-menu              Dashboard          (edit_others_posts)
 *   admin.php?page=aw-review                     To review          (edit_others_posts)
 *   admin.php?page=aw-enrich-review              Enrichment review  (edit_others_posts)
 *   admin.php?page=aw-sourcing                   Sources            (manage_options; &edit=<host|new>, &view=feeds,
 *                                                &feed=<id|new>: the feed form, sourceweb_feed_parts())
 *   admin.php?page=aw-sourcing&tab=keywords      Keywords           (manage_options; &edit=<id|new>, &kw=suggestions)
 *   admin.php?page=aw-sourcing&tab=rules         Rules              (manage_options; &part=sections|summary|enrich, &edit=<type>,
 *                                                &tagrule=<tag|new>, &field=<key>)
 *   admin.php?page=aw-sourcing&tab=ai            AI models          (manage_options; &edit=<key id|new>, &overview=1)
 *   admin.php?page=aw-sourcing&tab=workers       Workers            (manage_options; &edit=<id|new>, &runs=<id>)
 *   admin.php?page=aw-sourcing&tab=rejected      Rejected           (manage_options; read-only)
 *   admin.php?page=aw-sourcing&tab=relay-log     Relay log          (manage_options; read-only)
 *   admin.php?page=aw-sourcing&tab=log           Change log         (manage_options; read-only)
 *   admin.php?page=aw-sourcing&tab=settings      Settings           (manage_options)
 *
 * The screens are one script (build/index.js, from src/), drawn with Kits Core's shell parts and fed by the
 * sourceweb/v1 routes (sourceweb-rest.php). When Kits Core or the build is missing, each screen says so (one notice).
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The Sourcing page's tabs. */
const SOURCEWEB_UI_TABS = array( 'sources', 'keywords', 'rules', 'ai', 'workers', 'rejected', 'relay-log', 'log', 'settings' );

/**
 * Which screen this request asks for: 'dashboard', 'review', 'enrichment', a tab of SOURCEWEB_UI_TABS or ''. An
 * unknown tab of the Sourcing page is Sources, its first screen.
 */
function sourceweb_ui_wanted(): string {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- which screen to show; nothing is saved here
	$page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) );
	$tab  = sanitize_key( wp_unslash( $_GET['tab'] ?? '' ) );
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	if ( AW_SRC_PAGE === $page ) {
		return in_array( $tab, SOURCEWEB_UI_TABS, true ) ? $tab : 'sources';
	}
	return array(
		AW_SRC_MENU        => 'dashboard',
		'aw-review'        => 'review',
		'aw-enrich-review' => 'enrichment',
	)[ $page ] ?? '';
}

/** The capability each screen needs. */
function sourceweb_ui_caps(): array {
	return array(
		'dashboard'  => AW_REVIEW_CAP,
		'review'     => AW_REVIEW_CAP,
		'enrichment' => AW_ENRICH_CAP,
		'sources'    => AW_SRC_CAP,
		'keywords'   => AW_SRC_CAP,
		'rules'      => AW_SRC_CAP,
		'ai'         => AW_SRC_CAP,
		'workers'    => AW_SRC_CAP,
		'rejected'   => AW_SRC_CAP,
		'relay-log'  => AW_SRC_CAP,
		'log'        => AW_SRC_CAP,
		'settings'   => AW_SRC_CAP,
	);
}

/**
 * The screen is on this request (its script enqueued). Set on admin_enqueue_scripts.
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
 * The address of a screen.
 *
 * @param string $screen 'dashboard', 'review', 'enrichment' or a tab of SOURCEWEB_UI_TABS.
 */
function sourceweb_ui_url( string $screen ): string {
	$q = array(
		'dashboard'  => array( 'page' => AW_SRC_MENU ),
		'review'     => array( 'page' => 'aw-review' ),
		'enrichment' => array( 'page' => 'aw-enrich-review' ),
		'sources'    => array( 'page' => AW_SRC_PAGE ),
	)[ $screen ] ?? ( in_array( $screen, SOURCEWEB_UI_TABS, true ) ? array(
		'page' => AW_SRC_PAGE,
		'tab'  => $screen,
	) : array( 'page' => AW_SRC_MENU ) );
	return add_query_arg( $q, admin_url( 'admin.php' ) );
}

add_action(
	'admin_enqueue_scripts',
	function () {
		$screen = sourceweb_ui_wanted();
		if ( '' === $screen || ! function_exists( 'kits_shell_app' ) || ! current_user_can( sourceweb_ui_caps()[ $screen ] ) ) {
			return;
		}
		$head   = get_file_data( AW_SOURCING_DIR . 'aw-sourcing.php', array( 'Version' => 'Version' ) );
		// The header's tabs in menu order.
		$order = array(
			'dashboard'  => __( 'Dashboard', 'aw-sourcing' ),
			'review'     => __( 'To review', 'aw-sourcing' ),
			'enrichment' => __( 'Enrichment review', 'aw-sourcing' ),
			'sources'    => __( 'Sources', 'aw-sourcing' ),
			'keywords'   => __( 'Keywords', 'aw-sourcing' ),
			'rules'      => __( 'Rules', 'aw-sourcing' ),
			'ai'         => __( 'AI models', 'aw-sourcing' ),
			'workers'    => __( 'Workers', 'aw-sourcing' ),
			'rejected'   => __( 'Rejected', 'aw-sourcing' ),
			'relay-log'  => __( 'Relay log', 'aw-sourcing' ),
			'log'        => __( 'Change log', 'aw-sourcing' ),
			'settings'   => __( 'Settings', 'aw-sourcing' ),
		);
		$caps  = sourceweb_ui_caps();
		$tabs  = array();
		foreach ( $order as $slug => $label ) {
			if ( ! current_user_can( $caps[ $slug ] ) ) {
				continue;
			}
			$tabs[] = array(
				'slug'    => $slug,
				'label'   => $label,
				'url'     => sourceweb_ui_url( $slug ),
				'current' => $slug === $screen,
			);
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
					'feed'    => 'sources' === $screen ? sourceweb_feed_parts() : array(),
					'extra'   => sourceweb_extra_parts( $screen ),
				),
			)
		);
		sourceweb_ui_ready( $ok );
	}
);

/**
 * Draw a screen. The page callbacks call this after their capability check. Without Kits Core or the build, one
 * notice says so in place of the screen.
 *
 * @param string $screen 'dashboard', 'review', 'enrichment' or a tab of SOURCEWEB_UI_TABS.
 */
function sourceweb_ui_page( string $screen ): void {
	$titles = array(
		'dashboard'  => __( 'SourceWeb', 'aw-sourcing' ),
		'review'     => __( 'To review', 'aw-sourcing' ),
		'enrichment' => __( 'Enrichment review', 'aw-sourcing' ),
		'sources'    => __( 'Sources', 'aw-sourcing' ),
		'keywords'   => __( 'Keywords', 'aw-sourcing' ),
		'rules'      => __( 'Rules', 'aw-sourcing' ),
		'ai'         => __( 'AI models', 'aw-sourcing' ),
		'workers'    => __( 'Workers', 'aw-sourcing' ),
		'rejected'   => __( 'Rejected', 'aw-sourcing' ),
		'relay-log'  => __( 'Relay log', 'aw-sourcing' ),
		'log'        => __( 'Change log', 'aw-sourcing' ),
		'settings'   => __( 'SourceWeb settings', 'aw-sourcing' ),
	);
	$title = $titles[ $screen ] ?? $titles['dashboard'];
	if ( sourceweb_ui_wanted() === $screen && sourceweb_ui_ready() && function_exists( 'kits_shell_mount' ) ) {
		kits_shell_mount( 'sourceweb-app', $title );
		return;
	}
	echo '<div class="wrap"><h1>' . esc_html( $title ) . '</h1>';
	wp_admin_notice( esc_html__( 'SourceWeb needs Kits Core (active, with its build) to show this screen.', 'aw-sourcing' ), array( 'type' => 'error' ) );
	echo '</div>';
}

/**
 * Server-drawn forms a screen shows under its own content, as Kits page parts: on To review, other plugins' intake
 * forms (aw_review_intake; core's social-posts.php adds a social post from its link); on Settings, the backfill date
 * and the uninstall box (Settings API forms, administrators only).
 *
 * @param string $screen The screen.
 */
function sourceweb_extra_parts( string $screen ): array {
	if ( ! function_exists( 'kits_shell_html_parts' ) ) {
		return array();
	}
	ob_start();
	if ( 'review' === $screen ) {
		do_action( 'aw_review_intake' );
	} elseif ( 'settings' === $screen && current_user_can( 'manage_options' ) ) {
		settings_errors( 'aw_backfill_floor' );
		echo '<form method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '">';
		settings_fields( 'aw_src_backfill' );
		do_settings_sections( 'aw-src-backfill' );
		submit_button( 'Save backfill date' );
		echo '</form>';
		echo '<form method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '">';
		settings_fields( 'aw_src_uninstall' );
		do_settings_sections( 'aw-src-uninstall' );
		submit_button( 'Save uninstall setting' );
		echo '</form>';
	}
	$html = trim( (string) ob_get_clean() );
	return '' === $html ? array() : kits_shell_html_parts( $html );
}

/**
 * The feed form (Sources &feed=<id|new>, with &type= and &host=) as Kits page parts: aw_sources_edit_form()'s output, so Add Feed and Update post to the same admin-post handler (kits_sourcing_save_feeds),
 * with its nonce, and come back to the screen the form was opened from. Empty without a feed in the address.
 *
 * @return array[]
 */
function sourceweb_feed_parts(): array {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- which feed to show; nothing is saved here
	$feed = sanitize_key( wp_unslash( $_GET['feed'] ?? '' ) );
	if ( '' === $feed || ! function_exists( 'aw_sources_edit_form' ) || ! function_exists( 'kits_shell_html_parts' ) || ! current_user_can( AW_SRC_CAP ) ) {
		return array();
	}
	ob_start();
	aw_sources_edit_form( $feed, sanitize_key( wp_unslash( $_GET['type'] ?? '' ) ), aw_sourcing_norm_host( sanitize_text_field( wp_unslash( $_GET['host'] ?? '' ) ) ) );
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	$html = (string) ob_get_clean();
	$html = (string) preg_replace( '#\s*<a [^>]*class="button">Cancel</a>#', '', $html ); // the screen's own Back button does this
	return kits_shell_html_parts( $html );
}

// SourceWeb → Settings → Uninstall: whether deleting SourceWeb also deletes its own data (uninstall.php). Off by
// default. Registered on init, so the REST API has it too; the box is a Settings API form (sourceweb_extra_parts()).
add_action(
	'init',
	function () {
		register_setting(
			'aw_src_uninstall',
			'aw_src_delete_data_on_uninstall',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => true,
			)
		);
	}
);
add_action(
	'admin_init',
	function () {
		add_settings_section( 'aw_src_uninstall', 'Uninstall', '__return_null', 'aw-src-uninstall' );
		add_settings_field( 'aw_src_delete_data_on_uninstall', 'Delete this plugin\'s data when it is deleted', 'sourceweb_uninstall_field', 'aw-src-uninstall', 'aw_src_uninstall', array( 'label_for' => 'aw_src_delete_data_on_uninstall' ) );
	}
);

/** The uninstall box. */
function sourceweb_uninstall_field(): void {
	printf(
		'<input type="checkbox" id="aw_src_delete_data_on_uninstall" name="aw_src_delete_data_on_uninstall" value="1"%1$s> <p class="description">%2$s</p>',
		checked( (bool) get_option( 'aw_src_delete_data_on_uninstall', false ), true, false ),
		esc_html( 'Off: everything stays in the database if the plugin is deleted.' )
	);
}
