<?php
/**
 * WP Admin information architecture (Phase 11 J). Nothing is deleted: menus move, labels match the site.
 *
 * - Posts are Resources (content-types.php); Comments hidden and off sitewide. Testimonials (1.0 leftover) hidden.
 * - Categories are shared by every section: shown once, under "Classification".
 * - "Inbox": Messages and Listing claims, with unread counts. Volunteers and the Newsletter live in FluentCRM (owner,
 *   17 Oct 2026): the site's Volunteers and Newsletter screens are hidden from the menu (their pages and tables stay).
 * - Menu order follows the site: Directory · News · Resources · Library · Opportunities · Campaigns · Pages · Media …
 * - Labels: Library types → Kind, Source types → Issuers, Campaign Hub → Campaigns (slugs and URLs unchanged).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SHARED_TAXONOMIES = array( 'category', 'post_tag' ); // WordPress's own Categories and Tags, on every section // places are not a list any more (LGD file, places.php)

/* ---------- Comments off ---------- */

add_filter( 'comments_open', '__return_false', 20 );
add_filter( 'pings_open', '__return_false', 20 );
add_filter( 'comments_array', '__return_empty_array', 20 );
// No comment feeds: not linked in the head, and the old addresses go to the home page.
add_filter( 'feed_links_show_comments_feed', '__return_false' );
add_filter( 'feed_links_extra_show_post_comments_feed', '__return_false' );
add_action(
	'template_redirect',
	function () {
		if ( is_comment_feed() ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}
);
add_action(
	'init',
	function () {
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
		$wp_admin_bar->remove_node( 'comments' );
	}
);

// Retired shortcodes still present in old content (Home): they print nothing. Testimonials were a 1.0 leftover
// (0 items, exported to aw-private on 8 Oct 2026); [aw_banner] was replaced by the Banner pattern.
add_action(
	'init',
	function () {
		foreach ( array( 'aw_testimonials', 'aw_banner' ) as $tag ) {
			remove_shortcode( $tag );
			add_shortcode( $tag, '__return_empty_string' );
		}
	},
	20
);

/* ---------- Labels that match the site ---------- */

add_filter(
	'register_taxonomy_args',
	function ( $args, $tax ) {
		$names = array();
		if ( isset( $names[ $tax ] ) ) {
			$args['labels'] = array_merge( (array) ( $args['labels'] ?? array() ), array( 'name' => $names[ $tax ][0], 'singular_name' => $names[ $tax ][1], 'menu_name' => $names[ $tax ][0] ) );
		}
		if ( in_array( $tax, AW_SHARED_TAXONOMIES, true ) ) {
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
		if ( 'aw_campaign' === $type ) {
			$args['labels']['name']      = 'Campaigns';
			$args['labels']['menu_name'] = 'Campaigns';
		}
		if ( 'aw_press' === $type ) { // its own menu item, after Campaigns
			$args['show_in_menu'] = true;
			$args['menu_icon']    = 'dashicons-megaphone';
			$args['labels']       = array_merge( (array) ( $args['labels'] ?? array() ), array( 'name' => 'Press coverage', 'menu_name' => 'Press coverage', 'all_items' => 'All coverage', 'add_new' => 'Add coverage', 'add_new_item' => 'Add coverage' ) );
		}
		if ( 'aw_message' === $type ) { // the contact form's messages
			$args['labels'] = array_merge( (array) ( $args['labels'] ?? array() ), array( 'name' => 'Contact messages', 'menu_name' => 'Contact messages', 'all_items' => 'Contact messages' ) );
		}
		if ( in_array( $type, array( 'aw_message', 'aw_claim' ), true ) ) {
			$args['show_in_menu'] = false; // retired: website forms are in Mail, claims in Fluent Forms; items keep their data
		}
		return $args;
	},
	10,
	2
);
// Courts are issuers (model.php); the Court list is no longer registered.

/* ---------- Menus ---------- */

/** Forget the cached To-review count on the Inbox menu (a review item changed). */
function aw_inbox_counts_flush() {
	delete_transient( 'aw_review_count' );
}
add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( in_array( $post->post_type, array( 'aw_news', 'aw_legal', 'aw_opportunity', 'aw_press' ), true ) ) {
		aw_inbox_counts_flush();
	}
}, 10, 3 );
foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $aw_ia_hook ) {
	add_action( $aw_ia_hook, function ( $mid, $id, $key ) {
		if ( 'aw_review' === $key ) {
			aw_inbox_counts_flush();
		}
	}, 10, 3 );
}
unset( $aw_ia_hook );

/** A count bubble like WordPress's own. */
function aw_menu_count( $n ) {
	return $n ? ' <span class="awaiting-mod count-' . (int) $n . '"><span class="pending-count">' . (int) $n . '</span></span>' : '';
}

// Opening a message marks it read.
add_action(
	'load-post.php',
	function () {
		$id = (int) ( $_GET['post'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( $id && 'aw_message' === get_post_type( $id ) ) {
			update_post_meta( $id, 'aw_read', current_time( 'mysql' ) );
		}
	}
);

add_action(
	'admin_menu',
	function () {
		// Inbox counts what is waiting in To review. Website forms are in Mail; form entries in Fluent Forms.
		add_menu_page( 'Inbox', 'Inbox' . aw_menu_count( function_exists( 'aw_review_count' ) ? aw_review_count() : 0 ), AW_VOL_CAP, 'aw-inbox', '__return_null', 'dashicons-feedback', 26 );
		add_menu_page( 'Classification', 'Classification', 'manage_categories', 'aw-classification', '__return_null', 'dashicons-tag', 25 );
		foreach ( AW_SHARED_TAXONOMIES as $tax ) {
			$t = get_taxonomy( $tax );
			if ( $t ) {
				add_submenu_page( 'aw-classification', $t->labels->name, $t->labels->name, 'manage_categories', 'edit-tags.php?taxonomy=' . $tax );
			}
		}
	},
	9
);

add_action(
	'admin_menu',
	function () {
		global $submenu, $menu;
		// Hidden: Comments.
		remove_menu_page( 'edit-comments.php' );
		// Volunteers and Newsletter (with Not subscribed) are in FluentCRM now (owner, 17 Oct 2026): hidden from the menu
		// only; their pages keep their URLs and the site's tables stay as the backup.
		remove_menu_page( 'aw-volunteers' );
		remove_menu_page( 'aw-newsletter' );
		remove_submenu_page( 'aw-classification', 'aw-classification' );
		remove_submenu_page( 'aw-inbox', 'aw-inbox' );
		// Categories: only under Classification.
		foreach ( array_keys( (array) $submenu ) as $parent ) {
			foreach ( AW_SHARED_TAXONOMIES as $tax ) {
				foreach ( (array) $submenu[ $parent ] as $i => $item ) {
					if ( 'aw-classification' !== $parent && false !== strpos( (string) $item[2], 'taxonomy=' . $tax ) ) {
						unset( $submenu[ $parent ][ $i ] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
					}
				}
			}
		}
	},
	999
);

// Categories and Tags open under Classification.
add_filter(
	'parent_file',
	function ( $parent ) {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return $parent;
		}
		if ( in_array( $screen->taxonomy, AW_SHARED_TAXONOMIES, true ) ) {
			return 'aw-classification';
		}
		return $parent;
	}
);
add_filter(
	'submenu_file',
	function ( $file ) {
		$screen = get_current_screen();
		return ( $screen && in_array( $screen->taxonomy, AW_SHARED_TAXONOMIES, true ) ) ? 'edit-tags.php?taxonomy=' . $screen->taxonomy : $file;
	}
);

// Menu order: the site's order, then WordPress's own screens.
add_filter( 'custom_menu_order', '__return_true', PHP_INT_MAX );
add_filter(
	'menu_order',
	function () {
		return array(
			'index.php',
			'edit.php?post_type=aw_listing',
			'edit.php?post_type=aw_news',
			'edit.php',
			'edit.php?post_type=aw_legal',
			'edit.php?post_type=aw_opportunity',
			'edit.php?post_type=aw_campaign',
			'edit.php?post_type=aw_press',
			'edit.php?post_type=page',
			'upload.php',
			'aw-classification',
			'aw-inbox',
			'mailweb', // Mail (Mailweb plugin): unread count and latest messages
			'separator2',
			'themes.php',
			'plugins.php',
			'users.php',
			'tools.php',
			'options-general.php',
			'googlesitekit-dashboard',
			'litespeed',
		);
	},
	PHP_INT_MAX
);

// The one-time data steps (comments closed by default, Hello world to draft, news court terms kept as meta, testimonials
// exported) ran and were removed (audit item 16, flag confirmed on live 25 Oct 2026; see git history).
