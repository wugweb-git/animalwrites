<?php
/**
 * WP Admin arrangement (moved from Animal Writes Core's admin-ia.php, 18 Nov 2026; Phase 11 J). Nothing is deleted:
 * menus move and labels match the site. The site's own values (its content menus, labels, retired types and
 * shortcodes, and comments off) come from the tenant through the filters below. Without them this file changes only
 * the shared Classification menu and the menu order of what exists.
 *
 * - Comments: off sitewide when kits_comments_off says so (the menu, the admin bar, comment feeds and post type support go).
 * - Categories, Tags and Linked campaigns are shared by every section: shown once, under "Classification"
 *   (kits_admin_shared_taxonomies).
 * - Labels and retired menus: kits_admin_post_type_labels, kits_admin_hidden_post_types (their items keep their data).
 * - Retired shortcodes still in old content print nothing: kits_retired_shortcodes.
 * - Opening an item marks it read: kits_admin_read_marks (post type => meta key).
 * - Menu order: Dashboard, the site's content (kits_admin_content_menus), Pages, Media, Classification, the Wugweb
 *   block (each product its own menu, owner 12 Nov 2026; each plugin registers itself at 58.1–58.7 in the same order),
 *   then WordPress's own screens. Third-party menus follow. The whole list: kits_admin_menu_order.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The Classification menu's slug: admin.php?page=aw-classification, the address it has had since Phase 11 J. */
const KITS_CLASSIFICATION_MENU = 'aw-classification';

/** Whether comments are off sitewide (the tenant says so with the kits_comments_off filter). */
function kits_comments_off(): bool {
	return (bool) apply_filters( 'kits_comments_off', false );
}

/**
 * Taxonomies on every section, listed once under Classification: Categories, Tags and, when the site has it, Linked
 * campaigns (12 Nov 2026).
 *
 * @return string[]
 */
function kits_admin_shared_taxonomies(): array {
	$list = array( 'category', 'post_tag' );
	if ( defined( 'KITS_CAMPAIGN_LINK' ) && '' !== (string) KITS_CAMPAIGN_LINK ) {
		$list[] = (string) KITS_CAMPAIGN_LINK;
	}
	return array_values( array_filter( array_map( 'strval', (array) apply_filters( 'kits_admin_shared_taxonomies', $list ) ) ) );
}

/* ---------- Comments off ---------- */

add_filter( 'comments_open', fn( $open ) => kits_comments_off() ? false : $open, 20 );
add_filter( 'pings_open', fn( $open ) => kits_comments_off() ? false : $open, 20 );
add_filter( 'comments_array', fn( $comments ) => kits_comments_off() ? array() : $comments, 20 );
// No comment feeds: not linked in the head, and the old addresses go to the home page.
add_filter( 'feed_links_show_comments_feed', fn( $show ) => kits_comments_off() ? false : $show );
add_filter( 'feed_links_extra_show_post_comments_feed', fn( $show ) => kits_comments_off() ? false : $show );
add_action(
	'template_redirect',
	function () {
		if ( kits_comments_off() && is_comment_feed() ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}
);
add_action(
	'init',
	function () {
		if ( ! kits_comments_off() ) {
			return;
		}
		foreach ( get_post_types() as $type ) {
			remove_post_type_support( $type, 'comments' );
			remove_post_type_support( $type, 'trackbacks' );
		}
	},
	100
);
add_action(
	'wp_before_admin_bar_render',
	function () {
		global $wp_admin_bar;
		if ( kits_comments_off() && $wp_admin_bar ) {
			$wp_admin_bar->remove_node( 'comments' );
		}
	}
);

// Retired shortcodes still present in old content print nothing.
add_action(
	'init',
	function () {
		foreach ( (array) apply_filters( 'kits_retired_shortcodes', array() ) as $tag ) {
			$tag = (string) $tag;
			if ( '' !== $tag ) {
				remove_shortcode( $tag );
				add_shortcode( $tag, '__return_empty_string' );
			}
		}
	},
	20
);

/* ---------- Labels that match the site ---------- */

add_filter(
	'register_taxonomy_args',
	function ( $args, $tax ) {
		if ( in_array( $tax, kits_admin_shared_taxonomies(), true ) ) {
			$args['show_in_menu'] = false; // listed once, under Classification
		}
		return $args;
	},
	10,
	2
);
add_filter(
	'register_post_type_args',
	function ( $args, $type ) {
		$labels = (array) apply_filters( 'kits_admin_post_type_labels', array() );
		if ( ! empty( $labels[ $type ] ) && is_array( $labels[ $type ] ) ) {
			$args['labels'] = array_merge( (array) ( $args['labels'] ?? array() ), $labels[ $type ] );
		}
		if ( in_array( $type, (array) apply_filters( 'kits_admin_hidden_post_types', array() ), true ) ) {
			$args['show_in_menu'] = false; // retired from the menu; items keep their data
		}
		return $args;
	},
	10,
	2
);

/* ---------- Menus ---------- */

/**
 * A count bubble like WordPress's own.
 *
 * @param int $n Count; 0 prints nothing.
 */
function kits_menu_count( $n ) {
	return $n ? ' <span class="awaiting-mod count-' . (int) $n . '"><span class="pending-count">' . (int) $n . '</span></span>' : '';
}

// Opening an item marks it read (kits_admin_read_marks: post type => meta key).
add_action(
	'load-post.php',
	function () {
		$id    = absint( $_GET['post'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$marks = (array) apply_filters( 'kits_admin_read_marks', array() );
		$type  = $id ? (string) get_post_type( $id ) : '';
		if ( '' !== $type && ! empty( $marks[ $type ] ) && current_user_can( 'edit_post', $id ) ) {
			update_post_meta( $id, (string) $marks[ $type ], current_time( 'mysql' ) );
		}
	}
);

add_action(
	'admin_menu',
	function () {
		$taxes = kits_admin_shared_taxonomies();
		if ( ! $taxes ) {
			return;
		}
		add_menu_page( __( 'Classification', 'kits-core' ), __( 'Classification', 'kits-core' ), 'manage_categories', KITS_CLASSIFICATION_MENU, '__return_null', 'dashicons-tag', 25 );
		foreach ( $taxes as $tax ) {
			$t = get_taxonomy( $tax );
			if ( $t ) {
				add_submenu_page( KITS_CLASSIFICATION_MENU, $t->labels->name, $t->labels->name, 'manage_categories', 'edit-tags.php?taxonomy=' . $tax );
			}
		}
	},
	9
);

add_action(
	'admin_menu',
	function () {
		global $submenu;
		if ( kits_comments_off() ) {
			remove_menu_page( 'edit-comments.php' );
		}
		remove_submenu_page( KITS_CLASSIFICATION_MENU, KITS_CLASSIFICATION_MENU );
		// Shared taxonomies: only under Classification.
		$taxes = kits_admin_shared_taxonomies();
		foreach ( array_keys( (array) $submenu ) as $parent ) {
			foreach ( $taxes as $tax ) {
				foreach ( (array) $submenu[ $parent ] as $i => $item ) {
					if ( KITS_CLASSIFICATION_MENU !== $parent && false !== strpos( (string) $item[2], 'taxonomy=' . $tax ) ) {
						unset( $submenu[ $parent ][ $i ] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
					}
				}
			}
		}
	},
	999
);

// The shared taxonomies open under Classification.
add_filter(
	'parent_file',
	function ( $parent ) {
		$screen = get_current_screen();
		if ( $screen && in_array( $screen->taxonomy, kits_admin_shared_taxonomies(), true ) ) {
			return KITS_CLASSIFICATION_MENU;
		}
		return $parent;
	}
);
add_filter(
	'submenu_file',
	function ( $file ) {
		$screen = get_current_screen();
		return ( $screen && in_array( $screen->taxonomy, kits_admin_shared_taxonomies(), true ) ) ? 'edit-tags.php?taxonomy=' . $screen->taxonomy : $file;
	}
);

/**
 * The admin menu order: menu slugs, first to last. Slugs a site does not have are skipped by WordPress.
 *
 * @return string[]
 */
function kits_admin_menu_order(): array {
	$content = (array) apply_filters( 'kits_admin_content_menus', array( 'edit.php' ) );
	$order   = array_merge(
		array( 'index.php' ),
		array_map( 'strval', $content ),
		array(
			'edit.php?post_type=page',
			'upload.php',
			KITS_CLASSIFICATION_MENU,
			'separator-wugweb',
			'kits',
			'directoryweb',
			'aw-sourcing-menu',
			'aw-seo-plus',
			'aw-search-insights',
			'mailweb',
			'planweb',
			'separator2',
			'themes.php',
			'plugins.php',
			'users.php',
			'tools.php',
			'options-general.php',
			'googlesitekit-dashboard',
			'litespeed',
		)
	);
	return array_values( array_unique( array_map( 'strval', (array) apply_filters( 'kits_admin_menu_order', $order ) ) ) );
}

add_filter( 'custom_menu_order', '__return_true', PHP_INT_MAX );
add_filter( 'menu_order', 'kits_admin_menu_order', PHP_INT_MAX );
