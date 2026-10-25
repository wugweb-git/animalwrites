<?php
/**
 * The Sourcing menu's shape (owner, 25 Oct 2026: AW Sourcing works like any other plugin: one menu, an Overview, links
 * from the Plugins screen). Plain constants and functions with no WordPress, so tests/run.php checks them; the menu
 * itself is registered in admin-menu.php.
 *
 * Sourcing → Overview (the menu's own page), To review, Enrichment review, then one item per screen of the Sourcing
 * page (admin.php?page=aw-sourcing&tab=…): the settings screens in their order, then the reports and jobs (Workers,
 * Rejected, Relay log, Change log), then Settings.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The menu's slug: its Overview (no other screen uses it). */
const AW_SRC_MENU = 'aw-sourcing-menu';

/** The Sourcing page: every settings screen, report and job, one per tab (admin.php?page=aw-sourcing&tab=…). */
const AW_SRC_PAGE = 'aw-sourcing';

/**
 * WordPress's screen id of the Sourcing page under the menu: the menu title "Sourcing" sanitised, "_page_", the page.
 * admin-menu.php registers the menu with exactly that title, so the id stays this.
 */
const AW_SRC_SCREEN = 'sourcing_page_aw-sourcing';

/** The tabs that are reports or jobs, not settings, in their menu order. */
const AW_SRC_REPORT_TABS = array( 'workers', 'rejected', 'relay-log', 'log' );

/** The tab with the plugin's own switches; always the last item. */
const AW_SRC_SETTINGS_TAB = 'settings';

/**
 * The page's tabs in menu order: settings screens as given, then the reports and jobs, then Settings.
 *
 * @param array $tabs Tab => label, in the order they were registered.
 * @return array Tab => label.
 */
function aw_src_menu_order( array $tabs ): array {
	$settings = array();
	$reports  = array();
	foreach ( $tabs as $k => $label ) {
		if ( AW_SRC_SETTINGS_TAB === $k || in_array( $k, AW_SRC_REPORT_TABS, true ) ) {
			continue;
		}
		$settings[ $k ] = $label;
	}
	foreach ( AW_SRC_REPORT_TABS as $k ) {
		if ( isset( $tabs[ $k ] ) ) {
			$reports[ $k ] = $tabs[ $k ];
		}
	}
	$last = isset( $tabs[ AW_SRC_SETTINGS_TAB ] ) ? array( AW_SRC_SETTINGS_TAB => $tabs[ AW_SRC_SETTINGS_TAB ] ) : array();
	return $settings + $reports + $last;
}

/** Is this tab a report or job (not a setting)? */
function aw_src_is_report( string $tab ): bool {
	return in_array( $tab, AW_SRC_REPORT_TABS, true );
}

/**
 * A tab's address relative to wp-admin (also its menu item's slug). Sources is the page's first screen, so its address
 * has no tab.
 *
 * @param string $tab  Tab.
 * @param array  $args More query arguments (already encoded).
 * @return string
 */
function aw_src_tab_path( string $tab = '', array $args = array() ): string {
	$q = array( 'page' => AW_SRC_PAGE );
	if ( '' !== $tab && 'sources' !== $tab ) {
		$q['tab'] = $tab;
	}
	return 'admin.php?' . http_build_query( $q + $args, '', '&' );
}

/** The menu item a tab belongs to: the page's own slug for Sources, the tab's address for the others. */
function aw_src_tab_menu_slug( string $tab ): string {
	return '' === $tab || 'sources' === $tab ? AW_SRC_PAGE : aw_src_tab_path( $tab );
}

/** The Overview's address relative to wp-admin. */
function aw_src_overview_path(): string {
	return 'admin.php?page=' . AW_SRC_MENU;
}

/** The Plugins screen's links under the plugin's name, label => address relative to wp-admin. */
function aw_src_action_link_paths(): array {
	return array(
		'Overview' => aw_src_overview_path(),
		'Settings' => aw_src_tab_path( AW_SRC_SETTINGS_TAB ),
	);
}
