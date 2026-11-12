<?php
/**
 * Kits menu tests that need no WordPress (owner rule: no local WordPress or MySQL): the six items and their order,
 * where every old address of docs/admin-ui-spec.md §E1 goes, that other submenu entries leave the menu, and that a
 * tab added through the filter is dropped (includes/admin-menu-model.php). The Deploy check also loads each screen and
 * each old address in a real WordPress (tools/ci/smoke.sh).
 *
 * Usage: php plugins/kits-core/tests/admin-menu-tests.php
 *
 * @package kits-core
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput -- a command-line test runner.

if ( 'cli' !== PHP_SAPI ) {
	exit;
}
define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__ ) . '/includes/admin-menu-model.php';
require dirname( __DIR__ ) . '/includes/admin-fold-model.php';

$fails = 0;
$count = 0;

/**
 * One check.
 *
 * @param bool   $ok   Passed.
 * @param string $name What it checks.
 */
function kits_t( bool $ok, string $name ) {
	global $fails, $count;
	++$count;
	if ( ! $ok ) {
		++$fails;
		echo "FAIL $name\n";
	}
}

// The six, in order (§B), with their capabilities.
$six = kits_menu_six( 'kits' );
kits_t( array( 'kits', 'kits-options', 'kits-settings', 'kits-tools', 'kits-help' ) === array_keys( $six ), 'five items in the spec order (Plugins is WordPress\'s own)' );
kits_t( array( 'Home', 'Theme Options', 'Settings', 'Tools', 'Help' ) === array_column( $six, 0 ), 'five labels' );
kits_t( 'edit_theme_options' === $six['kits-tools'][2], 'Tools: edit_theme_options' );
kits_t( 'manage_options' === $six['kits'][2] && 'manage_options' === $six['kits-help'][2], 'Home and Help: manage_options' );
foreach ( $six as $slug => $t ) {
	kits_t( 'admin.php?page=' . $slug === $t[1], "address of $slug" );
}
kits_t( ! isset( $six['animal-writes'] ), 'no Animal Writes tab' );

// Old addresses (§E1): Kits Core's own plus what Animal Writes adds (admin-home.php), as the filter merges them.
$map = array_merge(
	kits_menu_redirects_core(),
	array(
		'animal-writes'   => 'kits',
		'aw-site-check'   => 'kits#page-check',
		'aw-elsewhere'    => 'kits-options#socials',
		'aw-edit-screens' => 'kits-help#developer/edit-screens',
		'aw-theme-parts'  => 'kits-help#developer/theme-parts',
		'aw-core'         => 'kits',
		'aw-cron-health'  => 'index.php',
	)
);
$want = array(
	'kits-core-settings' => 'admin.php?page=kits-settings',
	'kits-transfer'      => 'admin.php?page=kits-tools',
	'kits-docs'          => 'admin.php?page=kits-help#docs',
	'kits-system'        => 'admin.php?page=kits-help#design-system',
	'kits-preview'       => 'admin.php?page=kits-settings#preview',
	'kits-plugins'       => 'plugins.php',
	'animal-writes'      => 'admin.php?page=kits',
	'aw-site-check'      => 'admin.php?page=kits#page-check',
	'aw-elsewhere'       => 'admin.php?page=kits-options#socials',
	'aw-edit-screens'    => 'admin.php?page=kits-help#developer/edit-screens',
	'aw-theme-parts'     => 'admin.php?page=kits-help#developer/theme-parts',
	'kits-fonts'         => 'font-library.php',
	'kits-design-system' => 'admin.php?page=kits-help#design-system',
	'aw-core'            => 'admin.php?page=kits',
	'aw-cron-health'     => 'index.php',
);
foreach ( $want as $old => $to ) {
	kits_t( kits_menu_redirect_target( $old, array( 'page' => $old ), $map ) === $to, "$old redirects to $to" );
}
// They stay where they are: the six, Keys, Modules, Dates (owner, 11 Nov 2026), and anything unknown.
foreach ( array( 'kits', 'kits-options', 'kits-settings', 'kits-tools', 'kits-help', 'aw-keys', 'aw-modules', 'aw-dates', 'planweb', 'something-else', '' ) as $stay ) {
	kits_t( '' === kits_menu_redirect_target( $stay, array( 'page' => $stay ), $map ), "$stay does not redirect" );
}
// Other query values are kept, the section goes last, and odd values are encoded.
kits_t( 'admin.php?page=kits-help&tab=states#design-system' === kits_menu_redirect_target( 'kits-system', array( 'page' => 'kits-system', 'tab' => 'states' ), $map ), 'tab kept before the #section' );
kits_t( 'admin.php?page=kits-tools&done=1' === kits_menu_redirect_target( 'kits-transfer', array( 'done' => '1' ), $map ), 'a notice value kept' );
kits_t( 'admin.php?page=kits-settings&x=a%20b%26c' === kits_menu_redirect_target( 'kits-core-settings', array( 'x' => 'a b&c' ), $map ), 'values encoded' );
kits_t( 'admin.php?page=kits-help#docs' === kits_menu_redirect_target( 'kits-docs', array( 'arr' => array( 1 ) ), $map ), 'arrays dropped' );
// Never a loop: a slug that points at itself, or at itself with a section, does not redirect.
kits_t( '' === kits_menu_redirect_target( 'kits-options', array(), array( 'kits-options' => 'kits-options#x' ) ), 'no redirect to itself' );
kits_t( '' === kits_menu_redirect_target( 'x', array(), array( 'x' => '#y' ) ), 'no redirect to an empty slug' );
// No old target is itself an old address (one hop each).
foreach ( $map as $old => $to ) {
	$slug = explode( '#', $to )[0];
	kits_t( ! isset( $map[ $slug ] ) || $slug === $old, "$old: one hop" );
}

// Exactly six in the menu: the six stay in their order, every other entry leaves (and stays a hidden screen).
$items = array(
	array( 'Plugins', 'manage_options', 'kits-plugins', 'Plugins' ),
	array( 'Home', 'manage_options', 'kits', 'Home' ),
	array( 'Keys', 'manage_options', 'aw-keys', 'Keys' ),
	array( 'Help', 'manage_options', 'kits-help', 'Help' ),
	array( 'Theme Options', 'manage_options', 'kits-options', 'Theme Options' ),
	array( 'Modules', 'manage_options', 'aw-modules', 'Modules' ),
	array( 'Settings', 'manage_options', 'kits-settings', 'Settings' ),
	array( 'Dates', 'edit_others_posts', 'aw-dates', 'Dates' ),
	array( 'Tools', 'edit_theme_options', 'kits-tools', 'Tools' ),
	array( 'Home again', 'manage_options', 'kits', 'Home again' ),
);
list( $kept, $hidden ) = kits_menu_split( $items, $six );
kits_t( array_keys( $six ) === array_column( $kept, 2 ), 'menu is the five in order' );
kits_t( array( 'kits-plugins', 'aw-keys', 'aw-modules', 'aw-dates', 'kits' ) === array_column( $hidden, 2 ), 'Plugins, Keys, Modules, Dates and a second Home leave the menu' );
list( $kept ) = kits_menu_split( array_slice( $items, 0, 3 ), $six );
kits_t( array( 'kits' ) === array_column( $kept, 2 ), 'a missing item leaves no gap' );

// The tabs filter cannot add a seventh tab; it may change a label.
$filtered                  = $six;
$filtered['animal-writes'] = array( 'Animal Writes', 'admin.php?page=animal-writes', 'edit_posts' );
$filtered['kits-help'][0]  = 'Help and docs';
$tabs                      = kits_menu_tabs_only_six( $filtered, $six );
kits_t( array_keys( $six ) === array_keys( $tabs ), 'a seventh tab is dropped' );
kits_t( 'Help and docs' === $tabs['kits-help'][0], 'a label can change' );
$tabs = kits_menu_tabs_only_six( array( 'kits' => 'broken' ), $six );
kits_t( $six['kits'] === $tabs['kits'], 'a broken tab falls back to the default' );

// Folded menus (owner, 11 Nov 2026: Kits under Appearance, our plugins under Plugins; includes/admin-fold-model.php).
kits_t( 'admin.php?page=kits' === kits_fold_link( 'kits' ), 'a folded menu\'s item opens its first screen at the old address' );
$folds = array(
	'kits'    => 'themes.php',
	'mailweb' => 'plugins.php',
);
$sub   = array(
	'themes.php'  => array( array( 'Themes', 'switch_themes', 'themes.php' ), array( 'Kits', 'manage_options', 'admin.php?page=kits' ) ),
	'plugins.php' => array( array( 'Installed Plugins', 'activate_plugins', 'plugins.php' ), array( 'MailWeb', 'edit_posts', 'admin.php?page=mailweb' ) ),
	'kits'        => array( array( 'Home', 'manage_options', 'kits' ), array( 'Settings', 'manage_options', 'kits-settings' ) ),
	'mailweb'     => array( array( 'Inbox', 'edit_posts', 'mailweb' ), array( 'Settings', 'manage_options', 'mailweb-settings' ) ),
	''            => array( array( 'Keys', 'manage_options', 'aw-keys' ) ),
	'tools.php'   => array( array( 'Tools', 'edit_posts', 'tools.php' ), array( 'Other', 'manage_options', 'other-page' ) ),
);
kits_t( 'kits' === kits_fold_owner( $sub, $folds, 'kits-settings', 'kits' ), 'a Kits screen belongs to Kits' );
kits_t( 'mailweb' === kits_fold_owner( $sub, $folds, 'mailweb-settings', 'kits' ), 'the first submenu that lists a screen wins over a parent another filter named' );
kits_t( 'kits' === kits_fold_owner( $sub, $folds, 'aw-keys', 'kits' ), 'a hidden screen belongs to the folded menu its parent_file filter named' );
kits_t( '' === kits_fold_owner( $sub, $folds, 'aw-keys', '' ), 'a hidden screen no filter claims belongs to none' );
kits_t( '' === kits_fold_owner( $sub, $folds, 'other-page', 'tools.php' ), 'a screen under an ordinary menu belongs to none' );
kits_t( '' === kits_fold_owner( $sub, $folds, '', 'tools.php' ), 'a core screen (no page=) belongs to none' );
$tabs = kits_fold_tabs(
	array(
		array( 'Inbox <span class="awaiting-mod count-3"><span class="pending-count">3</span></span>', 'edit_posts', 'mailweb' ),
		array( 'Settings', 'manage_options', 'mailweb-settings' ),
		array( 'Keywords', 'manage_options', 'admin.php?page=aw-sourcing&tab=keywords' ),
		array( '', 'manage_options', 'no-label' ),
		'broken',
	),
	'admin.php?page=aw-sourcing&tab=keywords'
);
kits_t( array( 'Inbox', 'Settings', 'Keywords', 'no-label' ) === array_column( $tabs, 0 ), 'tab labels: the text before any count bubble, else the slug; broken entries left out' );
kits_t( array( 'admin.php?page=mailweb', 'admin.php?page=mailweb-settings', 'admin.php?page=aw-sourcing&tab=keywords', 'admin.php?page=no-label' ) === array_column( $tabs, 1 ), 'tab addresses: page= for a screen, as is for an address' );
kits_t( array( false, false, true, false ) === array_column( $tabs, 2 ), 'the current tab is the one asked for' );

echo "$count checks, $fails failed\n";
exit( $fails ? 1 : 0 );
