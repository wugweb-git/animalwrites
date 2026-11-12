<?php
/**
 * Search Insights' other tabs on the new screen (12 Nov 2026): Keywords, Pages, Check list, Rising, Clarity, Links,
 * Server, Sourcing keywords and any tab added later through the aw_si_tabs filter.
 *
 * On the tab's own address, after its load hook (which still handles every bulk and row action, then redirects back),
 * the tab draws its usual output into a buffer, and Kits Core's page parts (kits_shell_html_parts(),
 * kits-core/includes/admin-shell-parts.php) hand it to the new screen: text, notices, its list table and its forms.
 * The shell's PageParts sends every change as the same link or form request the current tab sends, so nothing is
 * saved in a new way.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The tabs this file draws: every Search Insights tab but Overview (the Dashboard) and Settings. */
function insightweb_tab_keys(): array {
	return array_merge( array( 'keywords' ), array_keys( aw_si_extra_tabs() ) );
}

/** Each tab's label: key => label. */
function insightweb_tab_labels(): array {
	return array( 'keywords' => __( 'Keywords', 'aw-seo-extras' ) ) + aw_si_extra_tabs();
}

/**
 * The tab's output as parts: the notices above every tab, then the tab itself.
 *
 * @param string $tab A key of insightweb_tab_keys().
 * @return array[]
 */
function insightweb_tab_parts( string $tab ): array {
	if ( ! in_array( $tab, insightweb_tab_keys(), true ) ) {
		return array();
	}
	ob_start();
	aw_si_screen_notices();
	if ( 'keywords' === $tab ) {
		aw_si_keywords_tab();
	} else {
		do_action( 'aw_si_tab_' . $tab );
	}
	$html = (string) ob_get_clean();
	return function_exists( 'kits_shell_html_parts' ) ? kits_shell_html_parts( $html ) : insightweb_html_blocks( $html );
}
