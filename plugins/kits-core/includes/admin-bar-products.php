<?php
/**
 * A "Wugweb" menu in the admin bar with a link to each Wugweb plugin that is active and that the signed-in person can
 * open: Kits, DirectoryWeb, SourceWeb, RankWeb, InsightWeb, MailWeb and PlanWeb (the same order as the admin menu).
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_bar_menu', 'kits_admin_bar_products', 80 );

/**
 * The Wugweb plugins present on this request: [ node id, name, admin page, capability ].
 *
 * @return array<int, array{0: string, 1: string, 2: string, 3: string}>
 */
function kits_admin_bar_product_list(): array {
	$list = array( array( 'kits', 'Kits', 'kits', 'manage_options' ) );
	if ( defined( 'DIRECTORYWEB_PAGE' ) ) {
		$list[] = array( 'directoryweb', 'DirectoryWeb', DIRECTORYWEB_PAGE, 'edit_aw_volunteers' );
	}
	if ( defined( 'AW_SRC_MENU' ) ) {
		$list[] = array( 'sourceweb', 'SourceWeb', AW_SRC_MENU, defined( 'AW_REVIEW_CAP' ) ? AW_REVIEW_CAP : 'edit_others_posts' );
	}
	if ( defined( 'AW_SEO_MENU' ) ) {
		$list[] = array( 'rankweb', 'RankWeb', AW_SEO_MENU, 'manage_categories' );
	}
	if ( defined( 'AW_SI_SLUG' ) ) {
		$list[] = array( 'insightweb', 'InsightWeb', AW_SI_SLUG, function_exists( 'aw_si_cap' ) ? aw_si_cap() : 'manage_options' );
	}
	if ( defined( 'MAILWEB_CAP' ) ) {
		$list[] = array( 'mailweb', 'MailWeb', 'mailweb', MAILWEB_CAP );
	}
	if ( defined( 'PLANWEB_MENU' ) ) {
		$list[] = array( 'planweb', 'PlanWeb', PLANWEB_MENU, 'edit_planweb_entries' );
	}
	return $list;
}

/**
 * Adds the Wugweb menu and its links.
 *
 * @param WP_Admin_Bar $bar The admin bar.
 */
function kits_admin_bar_products( $bar ): void {
	if ( ! is_user_logged_in() ) {
		return;
	}
	$items = array_filter( kits_admin_bar_product_list(), fn( $p ) => current_user_can( $p[3] ) ); // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- each plugin's own capability
	if ( ! $items ) {
		return;
	}
	$bar->add_node(
		array(
			'id'    => 'kits-wugweb',
			'title' => 'Wugweb',
			'href'  => admin_url( 'admin.php?page=' . reset( $items )[2] ),
		)
	);
	foreach ( $items as $p ) {
		$bar->add_node(
			array(
				'parent' => 'kits-wugweb',
				'id'     => 'kits-wugweb-' . $p[0],
				'title'  => esc_html( $p[1] ),
				'href'   => admin_url( 'admin.php?page=' . $p[2] ),
			)
		);
	}
}
