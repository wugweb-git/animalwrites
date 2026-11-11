<?php
/**
 * The Kits menu's rules as plain PHP (no WordPress calls), so tests/admin-menu-tests.php can run them without
 * WordPress: the six items, which old addresses go where, and which submenu entries stay in the menu.
 *
 * The WordPress side (registering the screens, the redirect on admin_init, the filters) is admin-menu.php.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The six Kits screens, in menu and tab order (docs/admin-ui-spec.md §B): slug => [label, address relative to
 * wp-admin, capability]. Home has the menu's own slug.
 *
 * Theme Options keeps manage_options for now: it saves through /wp/v2/settings and options.php, which both need it.
 *
 * @param string $home The menu's slug (KITS_MENU).
 */
function kits_menu_six( string $home = 'kits' ): array {
	return array(
		$home           => array( 'Home', 'admin.php?page=' . $home, 'manage_options' ),
		'kits-options'  => array( 'Theme Options', 'admin.php?page=kits-options', 'manage_options' ),
		'kits-settings' => array( 'Settings', 'admin.php?page=kits-settings', 'manage_options' ),
		'kits-plugins'  => array( 'Plugins', 'admin.php?page=kits-plugins', 'manage_options' ),
		'kits-tools'    => array( 'Tools', 'admin.php?page=kits-tools', 'edit_theme_options' ),
		'kits-help'     => array( 'Help', 'admin.php?page=kits-help', 'manage_options' ),
	);
}

/**
 * Kits Core's own old addresses and where they went (§E1): old page slug => new page slug, with an optional #section.
 * Kept for two releases after the six-item menu (Kits Core 1.0.0 and the next); a site adds its own through the
 * kits_admin_redirects filter.
 */
function kits_menu_redirects_core(): array {
	return array(
		'kits-core-settings' => 'kits-settings',
		'kits-transfer'      => 'kits-tools',
		'kits-docs'          => 'kits-help#docs',
		'kits-system'        => 'kits-help#design-system',
		'kits-preview'       => 'kits-settings#preview',
	);
}

/**
 * Where an old address goes: the new address relative to wp-admin, or '' when it stays. Every other query value is
 * kept (a tab, a notice such as saved=1), the page is replaced and the section goes last as #section.
 *
 * @param string $page  The page= value asked for.
 * @param array  $query The other query values (page left out or not).
 * @param array  $map   Old slug => new slug#section (kits_menu_redirects_core() and the filter's additions).
 */
function kits_menu_redirect_target( string $page, array $query, array $map ): string {
	if ( '' === $page || ! isset( $map[ $page ] ) ) {
		return '';
	}
	$to   = (string) $map[ $page ];
	$hash = '';
	$cut  = strpos( $to, '#' );
	if ( false !== $cut ) {
		$hash = substr( $to, $cut );
		$to   = substr( $to, 0, $cut );
	}
	if ( '' === $to || $to === $page ) {
		return ''; // Same screen (e.g. kits-options): nothing to do, and never a loop.
	}
	unset( $query['page'] );
	$args = array( 'page' => $to );
	foreach ( $query as $k => $v ) {
		if ( is_scalar( $v ) ) {
			$args[ (string) $k ] = (string) $v;
		}
	}
	return 'admin.php?' . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 ) . $hash;
}

/**
 * Split the Kits submenu into what stays in the menu (the six, in their order) and what leaves it (every other
 * entry, kept as a hidden screen so its address still opens).
 *
 * @param array $items Submenu entries as WordPress keeps them: [menu title, capability, slug, page title].
 * @param array $six   kits_menu_six().
 * @return array [ kept entries in the six's order, entries that leave the menu ].
 */
function kits_menu_split( array $items, array $six ): array {
	$order  = array_flip( array_keys( $six ) );
	$kept   = array();
	$hidden = array();
	foreach ( $items as $item ) {
		$slug = (string) ( $item[2] ?? '' );
		if ( isset( $order[ $slug ] ) && ! isset( $kept[ $order[ $slug ] ] ) ) {
			$kept[ $order[ $slug ] ] = $item;
		} else {
			$hidden[] = $item;
		}
	}
	ksort( $kept );
	return array( array_values( $kept ), $hidden );
}

/**
 * The tabs after the kits_admin_tabs filter: only the six keep a tab, in their order. A filter may change a tab's
 * label or capability; a tab it adds is dropped (the menu is six items, §B).
 *
 * @param array $filtered The filtered tabs.
 * @param array $six      kits_menu_six().
 */
function kits_menu_tabs_only_six( array $filtered, array $six ): array {
	$out = array();
	foreach ( $six as $slug => $t ) {
		$f            = $filtered[ $slug ] ?? $t;
		$out[ $slug ] = is_array( $f ) && isset( $f[0], $f[1], $f[2] ) ? array( (string) $f[0], (string) $f[1], (string) $f[2] ) : $t;
	}
	return $out;
}
