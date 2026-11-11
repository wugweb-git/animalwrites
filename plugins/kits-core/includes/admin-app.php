<?php
/**
 * Kits admin app: the new Theme Options screen (docs/admin-ui-spec.md, phase 1), built with WordPress packages from
 * src/theme-options/ into build/theme-options/ (npm run build in this plugin's folder; build/ is committed).
 *
 * For now it is a trial: Kits → Theme Options opens it only with &ui=next, and the current screen stays the default.
 * It edits the fields of kits_theme_options (theme-options.php) and saves them through /wp/v2/settings, which runs
 * the same clean-up as the current screen (kits_theme_options_clean). Phase 1b adds Identity (site title, tagline, logo,
 * site icon: WordPress's own options) and the site-wide header, footer and layout choices (kits_page_defaults._site).
 * The share bar stays on the current screen.
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
		wp_enqueue_media(); // Logo and site icon pickers.
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
		$ids   = array(
			'site_logo' => (int) get_option( 'site_logo' ),
			'site_icon' => (int) get_option( 'site_icon' ),
		);
		$urls  = array();
		foreach ( $ids as $id ) {
			if ( $id ) {
				$urls[ $id ] = (string) wp_get_attachment_image_url( $id, 'thumbnail' );
			}
		}
		$pd_saved = (array) get_option( 'kits_page_defaults', array() );
		$pd_site  = array();
		$pd_keys  = array( 'header', 'footer', 'sidebar', 'title', 'crumbs' );
		$pd       = array();
		$names    = kits_page_option_fields();
		$empty    = array(
			'header' => __( 'The theme’s own. Make a second header in Appearance → Editor → Patterns → Header to choose it here.', 'kits-core' ),
			'footer' => __( 'The theme’s own. Make a second footer in Appearance → Editor → Patterns → Footer to choose it here.', 'kits-core' ),
		);
		foreach ( $pd_keys as $key ) {
			$pd[ $key ] = array(
				'label'   => $names[ $key ][0],
				'note'    => kits_page_option_note( $key ),
				'choices' => (object) kits_page_option_choices( $key ),
				'empty'   => $empty[ $key ] ?? '',
			);
			if ( isset( $pd_saved['_site'][ $key ] ) ) {
				$pd_site[ $key ] = (string) $pd_saved['_site'][ $key ];
			}
		}
		$menus = array();
		foreach ( kits_theme_menu_choices() as $menu_id => $title ) {
			$menus[] = array(
				'value' => (string) $menu_id,
				'label' => (string) $title,
			);
		}
		$data = array(
			'site'         => get_bloginfo( 'name' ),
			'theme'        => $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ),
			'coreVersion'  => (string) $core['Version'],
			'tabs'         => $tabs,
			'fields'       => $fields,
			'saved'        => (object) $saved,
			'inherited'    => (object) $inherited,
			'core'         => array_merge(
				array(
					'title'       => (string) get_option( 'blogname' ),
					'description' => (string) get_option( 'blogdescription' ),
				),
				$ids
			),
			'mediaUrls'    => (object) $urls,
			'menus'        => $menus,
			'pageDefaults' => array(
				'fields' => $pd,
				'site'   => (object) $pd_site,
			),
			'layoutInfo'   => array(
				array( __( 'Breakpoints', 'kits-core' ), __( 'phones up to 767 px, tablets 768 to 1199 px, wide screens from 1200 px (fixed in the theme)', 'kits-core' ) ),
			),
			'links'        => array(
				'parts'  => admin_url( 'site-editor.php?postType=wp_template_part' ),
				'menus'  => admin_url( 'site-editor.php?postType=wp_navigation' ),
				'styles' => admin_url( 'site-editor.php?path=/wp_global_styles' ),
			),
			'oldUrl'       => admin_url( 'admin.php?page=kits-options' ),
			'shareUrl'     => admin_url( 'admin.php?page=kits-options&section=utility-share' ),
		);
		wp_add_inline_script( 'kits-admin-theme-options', 'window.kitsAdminApp = ' . wp_json_encode( $data ) . ';', 'before' );
	}
);

/** The new screen's mount point (kits_options_page() calls this when &ui=next). */
function kits_admin_app_page(): void {
	// Notices from other plugins go after .wp-header-end, so they stay out of the app and its pinned header.
	echo '<div class="wrap"><hr class="wp-header-end"><div id="kits-admin-app">';
	echo '<noscript><h1>' . esc_html__( 'Theme Options', 'kits-core' ) . '</h1><p>' . esc_html__( 'This screen needs JavaScript.', 'kits-core' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=kits-options' ) ) . '">' . esc_html__( 'Open the current Theme Options', 'kits-core' ) . '</a></p></noscript>';
	echo '</div></div>';
}
