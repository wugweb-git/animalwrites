<?php
/**
 * Kits admin app: the new Theme Options screen (docs/admin-ui-spec.md, phase 1), built with WordPress packages from
 * src/theme-options/ into build/theme-options/ (npm run build in this plugin's folder; build/ is committed).
 *
 * For now it is a trial: Kits → Theme Options opens it only with &ui=next, and the current screen stays the default.
 * It edits the fields of kits_theme_options (theme-options.php) and saves them through /wp/v2/settings, which runs
 * the same clean-up as the current screen (kits_theme_options_clean). The share bar stays on the current screen.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** True on Kits → Theme Options when the new screen is asked for. */
function kits_admin_app_wanted(): bool {
	return isset( $_GET['page'], $_GET['ui'] ) && 'kits-options' === $_GET['page'] && 'next' === $_GET['ui']; // phpcs:ignore WordPress.Security.NonceVerification -- which screen to show; nothing is saved here.
}

// The new screen's script, only on that screen.
add_action(
	'admin_enqueue_scripts',
	function () {
		if ( ! kits_admin_app_wanted() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$dir   = KITS_CORE_DIR . 'build/theme-options/';
		$asset = $dir . 'index.asset.php';
		if ( ! is_readable( $asset ) ) {
			return;
		}
		$a   = include $asset;
		$url = plugins_url( 'build/theme-options/index.js', KITS_CORE_DIR . 'kits-core.php' );
		wp_enqueue_script( 'kits-admin-theme-options', $url, $a['dependencies'], $a['version'], true );
		wp_enqueue_style( 'wp-components' );
		wp_set_script_translations( 'kits-admin-theme-options', 'kits-core' );

		$saved     = kits_theme_options_saved();
		$fields    = kits_theme_option_fields();
		$inherited = array();
		foreach ( $fields as $key => $f ) {
			$inherited[ $key ] = kits_theme_option_inherited( $key );
		}
		$tabs = array();
		foreach ( kits_admin_tabs_list() as $slug => $t ) {
			if ( current_user_can( $t[2] ) ) {
				$tabs[] = array(
					'slug'    => (string) $slug,
					'label'   => (string) $t[0],
					'url'     => admin_url( $t[1] ),
					'current' => 'kits-options' === $slug,
				);
			}
		}
		$theme = wp_get_theme( get_template() );
		$core  = get_file_data( KITS_CORE_DIR . 'kits-core.php', array( 'Version' => 'Version' ) );
		$data  = array(
			'site'      => get_bloginfo( 'name' ),
			'theme'     => $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ),
			'core'      => (string) $core['Version'],
			'tabs'      => $tabs,
			'fields'    => $fields,
			'saved'     => (object) $saved,
			'inherited' => (object) $inherited,
			'oldUrl'    => admin_url( 'admin.php?page=kits-options' ),
			'shareUrl'  => admin_url( 'admin.php?page=kits-options&section=utility-share' ),
		);
		wp_add_inline_script( 'kits-admin-theme-options', 'window.kitsAdminApp = ' . wp_json_encode( $data ) . ';', 'before' );
	}
);

/** The new screen's mount point (kits_options_page() calls this when &ui=next). */
function kits_admin_app_page(): void {
	echo '<div class="wrap"><div id="kits-admin-app"><h1>' . esc_html__( 'Theme Options', 'kits-core' ) . '</h1>';
	echo '<noscript><p>' . esc_html__( 'This screen needs JavaScript.', 'kits-core' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=kits-options' ) ) . '">' . esc_html__( 'Open the current Theme Options', 'kits-core' ) . '</a></p></noscript>';
	echo '</div></div>';
}
