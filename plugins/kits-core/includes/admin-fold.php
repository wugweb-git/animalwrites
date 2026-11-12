<?php
/**
 * The Wugweb menu block (owner, 12 Nov 2026: every product has its own top-level menu, grouped). Kits and each Wugweb
 * plugin call kits_admin_fold_menu() in place of WordPress's top-level menu call; it adds the product's top-level menu at its fixed
 * place in one block after the content menus, with one separator above it. Each plugin uses the same position in its
 * own top-level menu call when Kits Core is absent, so the layout is the same either way. Every admin.php?page=
 * address, hook name and screen id stays as it was.
 *
 * (11-12 Nov 2026 the menus were folded under Appearance and Plugins; the name kits_admin_fold_menu() is kept so the
 * plugins' calls do not change.)
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/admin-fold-model.php'; // kits_fold_tabs() for the product tabs below

/**
 * Call on admin_menu in place of the top-level menu call, before the menu's screens are added: the product's top-level menu in
 * the Wugweb block. Add the screens as sub-items of $menu as before; they keep their addresses and hook names.
 *
 * @param string $menu   The menu's slug (also its first screen's slug).
 * @param string $into   Unused (it named the WordPress menu the product was folded into, 11-12 Nov 2026).
 * @param string $title  The item's text (may end with a core count bubble).
 * @param string $cap    Who sees the item: the first screen's capability.
 * @param string $prefix The hook prefix of the menu's screens ({prefix}_page_{slug}); the default is the one
 *                       the top-level call would give, the title sanitised.
 * @param string $icon_file The product's one-colour menu mark (an SVG file; WordPress paints it in the admin colour
 *                       scheme). Empty or unreadable: the Dashicon in kits_wugweb_menu_slots().
 */
function kits_admin_fold_menu( string $menu, string $into, string $title, string $cap, string $prefix = '', string $icon_file = '' ): void {
	global $admin_page_hooks;
	unset( $into );
	$slot = kits_wugweb_menu_slots()[ $menu ] ?? array( 'dashicons-admin-generic', '58.9' );
	$icon = $slot[0];
	if ( '' !== $icon_file && is_readable( $icon_file ) ) {
		// A data URI is how WordPress takes an SVG menu icon (add_menu_page); the file is the plugin's own, a few hundred bytes.
		$icon = 'data:image/svg+xml;base64,' . base64_encode( (string) file_get_contents( $icon_file ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- see above.
	}
	add_menu_page( wp_strip_all_tags( $title ), $title, $cap, $menu, '', $icon, $slot[1] );
	// The hook prefix the screens already use ({prefix}_page_{slug}), whatever the menu title says.
	$admin_page_hooks[ $menu ] = '' !== $prefix ? $prefix : sanitize_title( wp_strip_all_tags( $title ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- keeps the screens' hook names.
	kits_wugweb_menu_separator();
}

/**
 * The Wugweb block: menu slug => [ dashicon, position ]. Positions sit just before core's second separator (59), after
 * the content menus. Each plugin uses the same position in its own top-level menu call when Kits Core is absent.
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
		$menu['58.05'] = array( '', 'read', 'separator-wugweb', '', 'wp-menu-separator', 'separator-wugweb' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- a menu separator, as core adds its own.
	}
}

/**
 * A product's screens as core tabs, under the screen's heading: every screen of its menu the user may open, in menu
 * order (Dashboard first). Nothing for a menu outside the Wugweb block, inside a Kits frame, or with one screen.
 *
 * @param string $menu    The menu's slug.
 * @param string $label   The tab row's accessible name, e.g. "MailWeb screens".
 * @param string $current The current item's slug, when it is not the page= value (an item that opens a tab).
 */
function kits_admin_fold_tabs( string $menu, string $label, string $current = '' ): void {
	global $submenu, $plugin_page;
	if ( ! isset( kits_wugweb_menu_slots()[ $menu ] ) || ( function_exists( 'kits_admin_embedding' ) && kits_admin_embedding() ) ) {
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
