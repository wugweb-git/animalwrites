<?php
/**
 * Menus folded into WordPress's own (owner, 11 Nov 2026: "kits to go inside appearance, so will plugins"). A product's
 * menu becomes one item under Appearance or Plugins, as standard WordPress does. Its screens stay registered under the
 * product's menu slug, which WordPress no longer lists, so every admin.php?page= address, hook name and screen id
 * stays as it was. The product's other screens are reached through its tabs (kits_admin_fold_tabs()).
 *
 * How, with core WordPress only:
 * - kits_admin_fold_menu() in place of WordPress's top-level menu call. It keeps the menu's hook prefix ($admin_page_hooks), so the
 *   screens the product then adds as sub-items of $menu keep their hook names (toplevel_page_{menu} for the
 *   screen at the menu's own slug, {prefix}_page_{slug} for the others), and it adds the one item that links to the
 *   first screen.
 * - The item is lit on every screen of the menu. WordPress works out the lit menu again after the parent_file filter
 *   (get_admin_page_parent() in menu-header.php takes the first submenu that lists the screen), so the filter also
 *   lists the screen under Appearance or Plugins with the capability do_not_allow: nobody sees it, and it is added
 *   after WordPress has checked access to the screen.
 * - Access is unchanged. WordPress shows Plugins to a user who may open one of its items (an editor sees Plugins with
 *   only the items they may open, and it links to the first of them), and checks each screen against its own
 *   capability.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/admin-fold-model.php';

/**
 * The folded menus: menu slug => the WordPress menu it sits in (themes.php, plugins.php).
 *
 * @param string $menu Add this menu (kits_admin_fold_menu() only).
 * @param string $into The WordPress menu it sits in.
 */
function kits_admin_folds( string $menu = '', string $into = '' ): array {
	static $folds = array();
	if ( '' !== $menu && '' !== $into ) {
		$folds[ $menu ] = $into;
	}
	return $folds;
}

/**
 * Call on admin_menu in place of the top-level menu call, before the menu's screens are added: the menu becomes one item under
 * $into that opens its first screen (the one at the menu's own slug). Add the screens as sub-items of $menu as before;
 * they keep their addresses and hook names.
 *
 * @param string $menu   The menu's slug (also its first screen's slug).
 * @param string $into   The WordPress menu: themes.php (Appearance) or plugins.php (Plugins).
 * @param string $title  The item's text (may end with a core count bubble).
 * @param string $cap    Who sees the item: the first screen's capability.
 * @param string $prefix The hook prefix of the menu's screens ({prefix}_page_{slug}); the default is the one
 *                       the top-level call would give, the title sanitised.
 */
function kits_admin_fold_menu( string $menu, string $into, string $title, string $cap, string $prefix = '' ): void {
	global $admin_page_hooks;
	// Owner, 12 Nov 2026: every product has its own top-level menu, all in one Wugweb block after the content menus
	// (kits_wugweb_menu_slots()), the same with or without Kits Core. $into is kept for callers; nothing is folded now.
	unset( $into );
	$slot = kits_wugweb_menu_slots()[ $menu ] ?? array( 'dashicons-admin-generic', '58.9' );
	add_menu_page( wp_strip_all_tags( $title ), $title, $cap, $menu, '', $slot[0], $slot[1] );
	// The hook prefix the screens already use ({prefix}_page_{slug}), whatever the menu title says.
	$admin_page_hooks[ $menu ] = '' !== $prefix ? $prefix : sanitize_title( wp_strip_all_tags( $title ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- keeps the screens' hook names.
	kits_wugweb_menu_separator();
}

/**
 * The Wugweb block: menu slug => [ dashicon, position ]. Positions sit just before core's second separator (59), after
 * the content menus. Each plugin uses the same position in its own add_menu_page() call when Kits Core is absent.
 */
function kits_wugweb_menu_slots(): array {
	return array(
		'kits'               => array( 'dashicons-admin-appearance', '58.1' ),
		'directoryweb'       => array( 'dashicons-location-alt', '58.2' ),
		'aw-sourcing-menu'   => array( 'dashicons-rss', '58.3' ),
		'aw-seo-plus'        => array( 'dashicons-search', '58.4' ),
		'aw-search-insights' => array( 'dashicons-chart-area', '58.5' ),
		'mailweb'            => array( 'dashicons-email', '58.6' ),
		'planweb'            => array( 'dashicons-calendar-alt', '58.7' ),
	);
}

/** One separator above the Wugweb block (added once). */
function kits_wugweb_menu_separator(): void {
	global $menu;
	if ( ! isset( $menu['58.05'] ) ) {
		$menu['58.05'] = array( '', 'read', 'separator-wugweb', '', 'wp-menu-separator' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- a menu separator, as core adds its own.
	}
}

/**
 * The folded menu whose item is lit on this screen ('' when none).
 *
 * @param string|null $set Set it (the parent_file filter only).
 */
function kits_admin_fold_lit( $set = null ): string {
	static $lit = '';
	if ( null !== $set ) {
		$lit = (string) $set;
	}
	return $lit;
}

// Light Appearance or Plugins, and the menu's item, on every screen of a folded menu. Last, after the products' own
// parent_file filters (Kits names its menu for the hidden screens it links from a tab).
add_filter(
	'parent_file',
	function ( $parent_file ) {
		global $submenu, $plugin_page;
		$page = is_string( $plugin_page ) ? $plugin_page : '';
		$menu = '' === $page ? '' : kits_fold_owner( (array) $submenu, kits_admin_folds(), $page, (string) $parent_file );
		if ( '' === $menu ) {
			return $parent_file;
		}
		$into = kits_admin_folds()[ $menu ];
		// For get_admin_page_parent() only, which runs next: the screen is found under $into first. Nobody can see it.
		$submenu[ $into ][] = array( '', 'do_not_allow', $page, '' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- an entry no one sees, after the access checks.
		kits_admin_fold_lit( $menu );
		return $into;
	},
	PHP_INT_MAX
);
add_filter(
	'submenu_file',
	function ( $submenu_file ) {
		$menu = kits_admin_fold_lit();
		return '' !== $menu ? kits_fold_link( $menu ) : $submenu_file;
	},
	PHP_INT_MAX
);

/**
 * A folded menu's screens as core tabs, under the screen's heading: every screen of the menu the user may open, in
 * menu order. Nothing when the menu is not folded (it is then a menu of its own) or has one screen.
 *
 * @param string $menu    The menu's slug.
 * @param string $label   The tab row's accessible name, e.g. "MailWeb screens".
 * @param string $current The current item's slug, when it is not the page= value (an item that opens a tab).
 */
function kits_admin_fold_tabs( string $menu, string $label, string $current = '' ): void {
	global $submenu, $plugin_page;
	if ( ! isset( kits_admin_folds()[ $menu ] ) || ( function_exists( 'kits_admin_embedding' ) && kits_admin_embedding() ) ) {
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
