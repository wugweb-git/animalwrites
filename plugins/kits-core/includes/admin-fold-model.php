<?php
/**
 * Folded menus as plain PHP (no WordPress calls), so tests/admin-menu-tests.php can run them without WordPress: the
 * address of a folded menu's item, which folded menu a screen belongs to, and a folded menu's tabs.
 *
 * The WordPress side (registering the item, lighting it, printing the tabs) is admin-fold.php.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The address of a folded menu's one item under Appearance or Plugins: its first screen, as before.
 *
 * @param string $menu The menu's slug (its first screen's slug).
 */
function kits_fold_link( string $menu ): string {
	return 'admin.php?page=' . $menu;
}

/**
 * Which folded menu a screen belongs to, the way WordPress finds a screen's menu: the first submenu that lists it.
 * A screen listed under no folded menu (a hidden screen) belongs to the menu its parent_file filter named, when that one
 * is folded. '' when none.
 *
 * @param array  $submenu WordPress's submenus: parent slug => list of [title, capability, slug, …].
 * @param array  $folds   Folded menus: menu slug => the WordPress menu it sits in.
 * @param string $page    The screen's slug (the page= value).
 * @param string $parent  The menu the parent_file filter has so far.
 */
function kits_fold_owner( array $submenu, array $folds, string $page, string $parent ): string {
	if ( '' !== $page ) {
		foreach ( $submenu as $key => $items ) {
			foreach ( (array) $items as $item ) {
				if ( is_array( $item ) && isset( $item[2] ) && $page === $item[2] ) {
					if ( isset( $folds[ (string) $key ] ) ) {
						return (string) $key;
					}
					break 2;
				}
			}
		}
	}
	return isset( $folds[ $parent ] ) ? $parent : '';
}

/**
 * A folded menu's tabs, from its submenu (the screens the user may open, in menu order): list of [label, address
 * relative to wp-admin, is it the current one]. A label is the item's text before any count bubble.
 *
 * @param array  $items   The menu's submenu entries: [title, capability, slug, …].
 * @param string $current The current item's slug.
 */
function kits_fold_tabs( array $items, string $current ): array {
	$tabs = array();
	foreach ( $items as $item ) {
		if ( ! is_array( $item ) || ! isset( $item[0], $item[2] ) || '' === (string) $item[2] ) {
			continue;
		}
		$slug   = (string) $item[2];
		$label  = trim( html_entity_decode( explode( '<', (string) $item[0], 2 )[0], ENT_QUOTES, 'UTF-8' ) );
		$tabs[] = array(
			'' !== $label ? $label : $slug,
			false !== strpos( $slug, '.php' ) ? $slug : kits_fold_link( $slug ),
			$current === $slug,
		);
	}
	return $tabs;
}
