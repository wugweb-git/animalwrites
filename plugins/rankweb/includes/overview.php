<?php
/**
 * RankWeb menu and its Overview screen (owner, 25 Oct 2026: one menu, organised like any other plugin).
 *
 * RankWeb opens the Dashboard (this file's page; drawn on the Kits shell, rankweb-ui.php); its tabs are Dashboard, FAQ
 * (faq.php) and Settings (settings.php: every module's rules and switches), each at its own admin.php?page= address.
 * The Modules page lists every module of the plugin from GET rankweb/v1/modules (rankweb-modules.php): its switch, where
 * it is set up (here or in Rank Math) and its status from the checks that already run; the status helpers below feed it.
 * The edit-screen boxes and WP-CLI commands are on the Modules page too ('Where things are', rankweb-rest.php).
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/admin-table.php';

const AW_SEO_MENU = 'aw-seo-plus';

add_action(
	'admin_menu',
	function () {
		// RankWeb's own top-level menu in the Wugweb block, opening the Dashboard; FAQ and
		// Settings are its tabs. Without Kits Core, a menu of its own (its screens then say they need Kits Core).
		// Screen ids of the screens under this menu start with its hook prefix; keep the old one (aw-seo-plus_page_…),
		// so saved screen options, box order and hidden columns stay with them.
		// The Wugweb block (owner, 12 Nov 2026): Kits, DirectoryWeb, SourceWeb, RankWeb, InsightWeb, MailWeb, PlanWeb at 58.1-58.7,
		// after one separator (58.05), before WordPress's second separator (59); the same with or without Kits Core.
		// The icon is the plugin's one-colour SVG as a data URI, which WordPress paints in the admin colour scheme.
		$icon = dirname( __DIR__ ) . '/assets/menu-icon.svg';
		$icon = is_readable( $icon ) ? 'data:image/svg+xml;base64,' . base64_encode( (string) file_get_contents( $icon ) ) : 'dashicons-search'; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the plugin's own small SVG file.
		add_menu_page( 'RankWeb', 'RankWeb', RANKWEB_CAP, AW_SEO_MENU, 'aw_seo_overview_page', $icon, '58.4' );
		$GLOBALS['admin_page_hooks'][ AW_SEO_MENU ] = 'aw-seo-plus'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the screens' hook prefix (aw-seo-plus_page_…), whatever the title.
		if ( ! isset( $GLOBALS['menu']['58.05'] ) ) {
			$GLOBALS['menu']['58.05'] = array( '', 'read', 'separator-wugweb', '', 'wp-menu-separator', 'separator-wugweb' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- one separator above the block, as core adds its own.
		}
		add_submenu_page( AW_SEO_MENU, 'RankWeb', 'Overview', RANKWEB_CAP, AW_SEO_MENU, 'aw_seo_overview_page' );
		// The Dashboard's other two pages (trends and gaps; module switches and reference tables), so no page is long.
		add_submenu_page( AW_SEO_MENU, 'RankWeb insights', 'Insights', RANKWEB_CAP, 'aw-seo-insights', 'aw_seo_insights_page' );
		add_submenu_page( AW_SEO_MENU, 'RankWeb modules', 'Modules', RANKWEB_CAP, 'aw-seo-modules', 'aw_seo_modules_page' );
	},
	9 // Before FAQ (10), so Overview is the first item.
);

// Plugins screen: Overview and FAQ under the plugin's name (Search Insights is under InsightWeb's).
$rankweb_main_file = defined( 'RANKWEB_FILE' ) ? RANKWEB_FILE : ( file_exists( AW_SEO_EXTRAS_DIR . 'rankweb.php' ) ? AW_SEO_EXTRAS_DIR . 'rankweb.php' : AW_SEO_EXTRAS_DIR . 'aw-seo-extras.php' );
add_filter(
	'plugin_action_links_' . plugin_basename( $rankweb_main_file ),
	function ( $links ) {
		$own = array(
			'<a href="' . esc_url( add_query_arg( 'page', AW_SEO_MENU, admin_url( 'admin.php' ) ) ) . '">Overview</a>',
			'<a href="' . esc_url( add_query_arg( 'page', 'aw-faq', admin_url( 'admin.php' ) ) ) . '">FAQ</a>',
		);
		return array_merge( $own, (array) $links );
	}
);

/** A Rank Math screen's address. */
function aw_seo_rank_math_url( string $page ): string {
	return add_query_arg( 'page', $page, admin_url( 'admin.php' ) );
}

/** One "where to set it up" link. */
function aw_seo_overview_link( string $label, string $url ): string {
	return '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
}

/** Status of the sitemap and llms.txt from the hourly check (health.php). */
function aw_seo_overview_endpoints_status(): string {
	$last = (array) get_option( 'aw_seo_endpoints_last', array() );
	if ( empty( $last['at'] ) ) {
		return esc_html__( 'Not checked yet (hourly check).', 'aw-seo-extras' );
	}
	$when = wp_date( 'j M Y, g:i a', (int) $last['at'] );
	if ( ! empty( $last['problems'] ) ) {
		return '<span class="dashicons dashicons-warning" aria-hidden="true"></span> ' . esc_html( implode( ' ', (array) $last['problems'] ) . ' ' . sprintf( 'Checked %s.', $when ) );
	}
	return '<span class="dashicons dashicons-yes" aria-hidden="true"></span> ' . esc_html( sprintf( 'Both answer. Checked %s.', $when ) );
}

/** Status of Search Insights from its Site Health test. */
function aw_seo_overview_si_status(): string {
	if ( ! function_exists( 'aw_si_health_test' ) ) {
		return '';
	}
	$t = aw_si_health_test();
	return '<span class="dashicons dashicons-' . ( 'good' === $t['status'] ? 'yes' : 'warning' ) . '" aria-hidden="true"></span> ' . esc_html( $t['label'] );
}

/** Status of the FAQ: pages with one and drafts waiting (from the FAQ screen's hourly count, when there is one). */
function aw_seo_overview_faq_status(): string {
	$ix = get_transient( 'aw_faq_index' );
	if ( ! is_array( $ix ) || ! isset( $ix['counts'] ) ) {
		// The count is kept for an hour after RankWeb → FAQ builds it; rebuild it in the background, not on this view.
		if ( function_exists( 'aw_faq_index' ) && ! wp_next_scheduled( 'aw_faq_index_build' ) ) {
			wp_schedule_single_event( time(), 'aw_faq_index_build' );
		}
		return '';
	}
	$faq    = array_sum( wp_list_pluck( $ix['counts'], 'faq' ) );
	$drafts = array_sum( wp_list_pluck( $ix['counts'], 'drafts' ) );
	return esc_html( sprintf( '%s pages show an FAQ. %s', number_format_i18n( $faq ), $drafts ? sprintf( '%d draft questions waiting.', $drafts ) : 'No drafts waiting.' ) );
}

/** RankWeb → Dashboard (rankweb-ui.php). */
function aw_seo_overview_page() {
	rankweb_ui_page( 'dashboard' );
}

/** RankWeb → Insights: the trends, where the gaps are, what is kept out of search. */
function aw_seo_insights_page() {
	rankweb_ui_page( 'insights' );
}

/** RankWeb → Modules: the module switches, the modules table, where things are. */
function aw_seo_modules_page() {
	rankweb_ui_page( 'modules' );
}
