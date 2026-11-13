<?php
/**
 * The Kits screens: five, which are the tabs on every Kits screen (docs/admin-ui-spec.md §B, approved by the owner on
 * 11 Nov 2026). Home, Theme Options, Settings, Tools, Help; plugins are on WordPress's own Plugins screen. In the
 * WordPress menu they are one item, Kits (its own top-level menu), which opens Home (admin-home.php); the five stay
 * registered under the Kits menu slug, so their addresses and hook names are as before.
 *
 * - The rules (the five, the old addresses, which entries stay) are plain PHP in admin-menu-model.php, tested by
 *   tests/admin-menu-tests.php without WordPress.
 * - Any other screen a plugin adds under Kits leaves the menu but keeps its address (a hidden screen), so a link to it
 *   still opens. A site says which of the five tabs is lit on such a screen through kits_screen_tabs.
 * - Old addresses redirect with a 302 at the end of admin_menu (§E1), only for GET, keeping their other query values. Kits Core's
 *   own are in kits_menu_redirects_core(); a site adds its own through kits_admin_redirects. Kept for two releases.
 * - Home, Settings and Help are made of sections: one box each, listed at the top and opened by #id. Kits Core adds its
 *   own; a site adds more through kits_home_sections, kits_settings_sections and kits_help_sections (§E3). A section
 *   is [ 'title' => …, 'render' => callable, 'cap' => capability, 'order' => int ]. A screen that used to stand on its
 *   own can be a section as it is: kits_admin_embed() prints it without its heading and tabs.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/admin-menu-model.php';
require_once __DIR__ . '/admin-fold-model.php'; // kits_fold_tabs() for kits_admin_fold_tabs() below

add_action(
	'admin_menu',
	function () {
		add_submenu_page( KITS_MENU, 'Theme Options', 'Theme Options', 'manage_options', 'kits-options', 'kits_options_page' );
		add_submenu_page( KITS_MENU, 'Settings', 'Settings', 'manage_options', 'kits-settings', 'kits_settings_page' );
		add_submenu_page( KITS_MENU, 'Tools', 'Tools', 'edit_theme_options', 'kits-tools', 'kits_transfer_page' );
		add_submenu_page( KITS_MENU, 'Help', 'Help', KITS_CAP, 'kits-help', 'kits_help_page' );
	},
	9 // After Home (admin-home.php, 8).
);

// Exactly five items: every other entry under Kits leaves the menu and stays a hidden screen at the same address.
add_action(
	'admin_menu',
	function () {
		global $submenu;
		if ( empty( $submenu[ KITS_MENU ] ) || ! is_array( $submenu[ KITS_MENU ] ) ) {
			return;
		}
		list( $kept, $hidden ) = kits_menu_split( $submenu[ KITS_MENU ], kits_menu_six( KITS_MENU ) );
		$submenu[ KITS_MENU ]  = $kept; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the Kits submenu only.
		foreach ( $hidden as $item ) {
			$slug = (string) ( $item[2] ?? '' );
			if ( '' === $slug || false !== strpos( $slug, '.php' ) ) {
				continue; // A core screen (e.g. font-library.php) keeps its own address without help.
			}
			// The screen's callback was hooked on its Kits hook name; the hidden screen runs it from there.
			$hook = get_plugin_page_hookname( $slug, KITS_MENU );
			add_submenu_page( '', (string) ( $item[3] ?? $item[0] ), '', (string) $item[1], $slug, fn() => do_action( $hook ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- the screen's own WordPress hook name.
			// Registered (address and capability kept), but out of the hidden list: there WordPress would reset the lit
			// menu to none after the parent_file filter below (get_admin_page_parent() in menu-header.php).
			remove_submenu_page( '', $slug );
		}
	},
	999 // After every plugin has added its screens, before the order (admin-screens.php, 1000).
);

/**
 * Which Kits tab is lit on a screen that is not one of them: what a site says through kits_screen_tabs, else none.
 *
 * @param string $slug The screen's slug.
 */
function kits_screen_tab( string $slug ): string {
	/**
	 * Filters which of the five tabs is lit on a screen outside them, e.g. a hidden screen linked from Settings.
	 *
	 * @param array $tabs Screen slug => one of the five slugs.
	 */
	$map = (array) apply_filters( 'kits_screen_tabs', array() );
	$tab = isset( $map[ $slug ] ) ? (string) $map[ $slug ] : '';
	return isset( kits_menu_six( KITS_MENU )[ $tab ] ) ? $tab : '';
}

// A hidden screen linked from one of the six belongs to Kits: Kits is lit on it.
add_filter(
	'parent_file',
	function ( $parent_file ) {
		global $plugin_page;
		return is_string( $plugin_page ) && '' !== kits_screen_tab( $plugin_page ) ? KITS_MENU : $parent_file;
	}
);

/** Old address => new address (slug#section): Kits Core's own and a site's (§E1). */
function kits_admin_redirects(): array {
	/**
	 * Filters the old Kits addresses that redirect: old page slug => new page slug, with an optional #section.
	 *
	 * @param array $map Old slug => new slug#section.
	 */
	return (array) apply_filters( 'kits_admin_redirects', kits_menu_redirects_core() );
}

// Old addresses: a 302 to the new place, on GET only (a form sent to an old address is not turned into a GET). On
// admin_menu, after every screen is added: WordPress refuses an address no screen has before admin_init runs.
add_action(
	'admin_menu',
	function () {
		if ( ! isset( $_GET['page'] ) || 'get' !== sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) || wp_doing_ajax() ) { // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- which screen is asked for; nothing is saved.
			return;
		}
		$query = array();
		foreach ( wp_unslash( $_GET ) as $k => $v ) { // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passed on as query values, escaped by the redirect.
			$k = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $k ); // Names as they were (case kept); values as sent, encoded by the redirect.
			if ( '' !== $k && is_scalar( $v ) ) {
				$query[ $k ] = (string) $v;
			}
		}
		$to = kits_menu_redirect_target( sanitize_key( (string) $query['page'] ), $query, kits_admin_redirects() );
		if ( '' !== $to ) {
			wp_safe_redirect( admin_url( $to ), 302, 'Kits' );
			exit;
		}
	},
	10000 // After the menu is complete (admin-screens.php sorts it at 1000).
);

/**
 * True while a screen prints as a section of one of the five (kits_admin_embed()); kits_admin_head() and
 * kits_admin_tabs() then print no heading or tabs.
 *
 * @param bool|null $set Set the state (kits_admin_embed() only).
 */
function kits_admin_embedding( $set = null ): bool {
	static $on = false;
	if ( null !== $set ) {
		$on = (bool) $set;
	}
	return $on;
}

/**
 * Print a screen as a section: its output without its own .wrap, first <h1> and header line (the section has the
 * heading; the screen around it has the tabs).
 *
 * @param callable $render Prints the screen.
 */
function kits_admin_embed( callable $render ): void {
	$was = kits_admin_embedding();
	kits_admin_embedding( true );
	ob_start();
	try {
		call_user_func( $render );
	} finally {
		$html = (string) ob_get_clean();
		kits_admin_embedding( $was );
	}
	$html = preg_replace( '#<div class="wrap">#', '<div>', $html, 1 );
	$html = preg_replace( '#<h1\b[^>]*>.*?</h1>#s', '', (string) $html, 1 );
	$html = str_replace( '<hr class="wp-header-end">', '', (string) $html );
	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the screen's own output, escaped where it was made.
}

/**
 * The sections of Home, Settings or Help, in order, that the user may see: id => [title, render, cap, order].
 *
 * @param string $screen KITS_MENU, kits-settings or kits-help.
 */
function kits_screen_sections( string $screen ): array {
	$own    = array();
	$filter = '';
	if ( KITS_MENU === $screen ) {
		$filter = 'kits_home_sections';
		// The theme's own dashboard (12 Nov 2026): where each part of the theme is edited. Versions are Tools → Site
		// Health; setup is done once (owner, 11 Nov 2026: no copies of what WordPress does).
		$own = array(
			'theme' => array( 'Edit the theme', 'kits_home_theme', 'edit_theme_options', 10 ),
		);
	} elseif ( 'kits-settings' === $screen ) {
		$filter = 'kits_settings_sections';
		$own    = array(
			'performance'   => array( 'Performance', fn() => kits_core_settings_form( 'Speed', 'utility-performance', 'performance' ), 'manage_options', 10 ),
			'compatibility' => array( 'Compatibility', fn() => kits_core_settings_form( 'Older content', '', 'compatibility' ), 'manage_options', 20 ),
			'preview'       => array( 'Preview access', fn() => kits_admin_embed( 'kits_preview_page' ), 'manage_options', 30 ),
			'analytics'     => array( 'Analytics', 'kits_settings_analytics', 'manage_options', 35 ),
		);
	} elseif ( 'kits-help' === $screen ) {
		$filter = 'kits_help_sections';
		$own    = array(
			'docs'            => array( 'Docs', 'kits_overview_tables', 'manage_options', 10 ),
			'design-system'   => array( 'Design system', fn() => kits_admin_embed( 'kits_system_page' ), 'manage_options', 20 ),
			'developer/extra' => array( 'Developer: extra settings', 'kits_help_extra', 'manage_options', 40 ),
		);
	}
	$list = array();
	foreach ( $own as $id => $s ) {
		$list[ $id ] = array(
			'title'  => $s[0],
			'render' => $s[1],
			'cap'    => $s[2],
			'order'  => $s[3],
		);
	}
	if ( '' !== $filter ) {
		/**
		 * Filters the sections of a Kits screen (kits_home_sections, kits_settings_sections, kits_help_sections).
		 *
		 * @param array $sections Id => [ 'title' => string, 'render' => callable, 'cap' => string, 'order' => int ].
		 */
		$list = (array) apply_filters( $filter, $list ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- one of the three names above.
	}
	$out = array();
	$i   = 0;
	foreach ( $list as $id => $s ) {
		if ( ! is_array( $s ) || empty( $s['title'] ) || empty( $s['render'] ) || ! is_callable( $s['render'] ) ) {
			continue;
		}
		if ( ! current_user_can( (string) ( $s['cap'] ?? 'manage_options' ) ) ) {
			continue;
		}
		$out[ (string) $id ] = array(
			'title'  => (string) $s['title'],
			'render' => $s['render'],
			'order'  => array( (int) ( $s['order'] ?? 50 ), $i++ ),
		);
	}
	uasort( $out, fn( $a, $b ) => $a['order'] <=> $b['order'] );
	return $out;
}

/**
 * A screen made of sections: the frame's header and the five tabs, then a card per section, one under the other (no row
 * of section links). Without the shell's build, one notice.
 *
 * @param string $screen The screen's slug.
 * @param string $title  Heading.
 * @param string $intro  One line under it.
 */
function kits_sections_page( string $screen, string $title, string $intro ): void {
	$sections = kits_screen_sections( $screen );
	if ( ! kits_frame_ready() ) {
		kits_frame_missing( $title );
		return;
	}
	// The sections as cards in the plugin screens' frame (includes/admin-kits-frame.php).
	kits_frame_open( $screen, $title, $intro );
	foreach ( $sections as $id => $s ) {
		kits_frame_card( (string) $id, $s['title'], $s['render'] );
	}
	kits_sections_back( $sections );
	kits_frame_close();
}

/**
 * After a form's save (options.php comes back without the #section): open the section it came from.
 *
 * @param array $sections The screen's sections (kits_screen_sections()).
 */
function kits_sections_back( array $sections ): void {
	$back = isset( $_GET['kits_section'] ) ? sanitize_text_field( wp_unslash( $_GET['kits_section'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- which section to show.
	if ( isset( $sections[ $back ] ) ) {
		wp_print_inline_script_tag( 'if(!location.hash){location.hash=' . wp_json_encode( $back ) . ';}' );
	}
}

/** Kits → Home. */
function kits_home_page() {
	kits_sections_page( KITS_MENU, 'Home', 'Kits by Wugweb is the site\'s design system: the theme draws every page, Kits Core adds the features every Kits site shares. This site\'s versions and checks, then where to change things.' );
}

/** Kits → Home → Edit the theme: the Site Editor screens that hold the theme's settings. */
function kits_home_theme(): void {
	echo '<p>' . esc_html( 'The theme is edited in the Site Editor. Each link opens the place that holds that part.' ) . '</p><ul>';
	foreach ( kits_theme_screens() as $s ) {
		echo '<li>' . kits_link( $s[0], $s[1] ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kits_link() escapes.
	}
	echo '</ul>';
}

/** Kits → Settings. */
function kits_settings_page() {
	kits_sections_page( 'kits-settings', 'Settings', 'Speed switches, older content and who sees preview-only items. Leave the switches on unless you are tracking down a problem.' );
}

/** Kits → Help. */
function kits_help_page() {
	kits_sections_page( 'kits-help', 'Help', 'What the theme and Kits Core hold and where each is changed, the design system, and reference for developers.' );
}

/** Kits → Help → Developer: extra settings (Theme Options → Utility → Extra, §D1.12). */
function kits_help_extra(): void {
	echo '<form method="post" action="options.php">';
	kits_options_form_fields( 'admin.php?page=kits-help&kits_section=' . rawurlencode( 'developer/extra' ) );
	if ( kits_options_section_body( 'utility-extra', 'Extra' ) ) {
		submit_button( 'Save extra settings' );
	}
	echo '</form>';
}

/**
 * Settings → Analytics, read only: where the GA4 tag comes from (analytics.php). The kits_ga4_via_gtm switch is shown
 * here and changed only with WP-CLI, on the owner's word (no GA4 or GTM change without it).
 */
function kits_settings_analytics(): void {
	if ( ! function_exists( 'kits_ga4_in_gtm' ) ) {
		echo '<p>' . esc_html( 'The analytics part of Kits Core is not loaded.' ) . '</p>';
		return;
	}
	// What visitors get: kits_gtm_container() is '' on admin screens and for signed-in staff, so read the settings.
	$on        = (bool) get_option( 'kits_ga4_via_gtm' );
	$sitekit   = (array) get_option( 'googlesitekit_tagmanager_settings', array() );
	$own       = function_exists( 'aw_gtm_id' ) ? (string) aw_gtm_id() : '';
	$container = ! empty( $sitekit['useSnippet'] ) && ! empty( $sitekit['containerID'] ) ? (string) $sitekit['containerID'] . ' (from Site Kit)' : ( '' !== $own ? $own . ' (the site\'s own)' : '' );
	$rows      = array(
		array( 'GA4 through Tag Manager only', $on ? 'On: Site Kit\'s own GA4 tag is held back while a container is placed.' : 'Off: Site Kit places its own GA4 tag.' ),
		array( 'Tag Manager container on visitors\' pages', '' !== $container ? $container : 'None' ),
		array( 'GA4 tag on visitors\' pages', $on && '' !== $container ? 'From the Tag Manager container' : 'From Site Kit' ),
	);
	echo '<table class="widefat striped"><tbody>';
	foreach ( $rows as $r ) {
		echo '<tr><th scope="row">' . esc_html( $r[0] ) . '</th><td>' . esc_html( $r[1] ) . '</td></tr>';
	}
	echo '</tbody></table>';
	echo '<p class="description">' . esc_html( 'Read only. Signed-in staff are never counted. The switch is the option kits_ga4_via_gtm (wp option update kits_ga4_via_gtm 1 or 0); Site Kit\'s settings stay as they are either way.' ) . '</p>';
}

/**
 * A Wugweb product's screens as core tabs, under the screen's heading: every screen of its menu the user may open, in
 * menu order (Dashboard first). Nothing for a menu outside the Wugweb block, inside a Kits frame, or with one screen.
 * (Each product registers its own top-level menu; this only prints the tabs.)
 *
 * @param string $menu    The menu's slug.
 * @param string $label   The tab row's accessible name, e.g. "MailWeb screens".
 * @param string $current The current item's slug, when it is not the page= value (an item that opens a tab).
 */
function kits_admin_fold_tabs( string $menu, string $label, string $current = '' ): void {
	global $submenu, $plugin_page;
	$block = array( 'kits', 'directoryweb', 'aw-sourcing-menu', 'aw-seo-plus', 'aw-search-insights', 'mailweb', 'planweb' );
	if ( ! in_array( $menu, $block, true ) || ( function_exists( 'kits_admin_embedding' ) && kits_admin_embedding() ) ) {
		return;
	}
	$items = array_filter( (array) ( $submenu[ $menu ] ?? array() ), fn( $i ) => is_array( $i ) && isset( $i[1] ) && current_user_can( (string) $i[1] ) );
	$tabs  = kits_fold_tabs( $items, '' !== $current ? $current : ( is_string( $plugin_page ) ? $plugin_page : '' ) );
	if ( count( $tabs ) < 2 ) {
		return;
	}
	echo '<nav class="nav-tab-wrapper wp-clearfix" aria-label="' . esc_attr( $label ) . '">';
	foreach ( $tabs as $t ) {
		echo '<a class="nav-tab' . ( $t[2] ? ' nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( $t[1] ) ) . '"' . ( $t[2] ? ' aria-current="page"' : '' ) . '>' . esc_html( $t[0] ) . '</a>';
	}
	echo '</nav>';
}
