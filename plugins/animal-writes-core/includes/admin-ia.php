<?php
/**
 * WP Admin information architecture (Phase 11 J). Nothing is deleted: menus move, labels match the site.
 *
 * - Posts are Resources (content-types.php); Comments hidden and off sitewide. Testimonials (1.0 leftover) hidden.
 * - Categories are shared by every section: shown once, under "Classification".
 * - "Inbox": Messages and Listing claims, with unread counts. "Audience": Newsletter and Volunteers.
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
			$args['show_in_menu'] = 'aw-inbox';
		}
		return $args;
	},
	10,
	2
);
// Courts are issuers (model.php); the Court list is no longer registered.

/* ---------- Menus ---------- */

/** Unread messages (no aw_read flag yet) and claims waiting for a decision. */
function aw_inbox_counts() {
	$messages = count( get_posts( array( 'post_type' => 'aw_message', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => 'aw_read', 'compare' => 'NOT EXISTS' ) ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	$claims   = count( get_posts( array( 'post_type' => 'aw_claim', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => 'aw_c_status', 'value' => 'pending' ) ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	return array( $messages, $claims );
}

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
		global $submenu;
		list( $msg, $claims ) = aw_inbox_counts();
		add_menu_page( 'Inbox', 'Inbox' . aw_menu_count( $msg + $claims + ( function_exists( 'aw_review_count' ) ? aw_review_count() : 0 ) ), AW_VOL_CAP, 'aw-inbox', '__return_null', 'dashicons-feedback', 26 );
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
		// Volunteer sign-ups go under Inbox; Newsletter stays its own item with its Subscribers list (pages keep their URLs).
		foreach ( $menu as $i => $item ) {
			if ( 'aw-volunteers' === $item[2] ) {
				unset( $menu[ $i ] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
				$submenu['aw-inbox'][] = array( 'Volunteer sign-ups', AW_VOL_CAP, 'aw-volunteers', 'Volunteer sign-ups' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			}
		}
		$submenu['aw-newsletter'] = array( array( 'Subscribers', AW_VOL_CAP, 'aw-newsletter', 'Subscribers' ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		remove_submenu_page( 'aw-classification', 'aw-classification' );
		// Inbox: the two post-type lists (registered with show_in_menu = aw-inbox), with their own counts.
		list( $msg, $claims ) = aw_inbox_counts();
		foreach ( (array) ( $submenu['aw-inbox'] ?? array() ) as $i => $item ) {
			if ( 'edit.php?post_type=aw_message' === $item[2] ) {
				$submenu['aw-inbox'][ $i ][0] .= aw_menu_count( $msg ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			} elseif ( 'edit.php?post_type=aw_claim' === $item[2] ) {
				$submenu['aw-inbox'][ $i ][0] .= aw_menu_count( $claims ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			}
		}
		remove_submenu_page( 'aw-inbox', 'aw-inbox' );
		// Inbox order (S2.3): Contact messages · Listing claims · Reports · Volunteer sign-ups · To review (was Press to review).
		$rank = array( 'edit.php?post_type=aw_message' => 1, 'edit.php?post_type=aw_claim' => 2, 'edit.php?post_type=aw_report' => 3, 'aw-volunteers' => 4, 'aw-review' => 5 );
		if ( isset( $submenu['aw-inbox'] ) ) {
			usort( $submenu['aw-inbox'], fn( $x, $y ) => ( $rank[ $x[2] ] ?? 9 ) <=> ( $rank[ $y[2] ] ?? 9 ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		}
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

// Keep the right top-level item open on the moved screens.
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
		if ( in_array( $screen->post_type, array( 'aw_message', 'aw_claim', 'aw_report' ), true ) ) {
			return 'aw-inbox';
		}
		if ( 'aw-volunteers' === ( $_GET['page'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return 'aw-inbox';
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
			'aw-newsletter',
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

/* ---------- One-time data steps (no deletion) ---------- */

add_action(
	'admin_init',
	function () {
		if ( '1' === get_option( 'aw_admin_ia_done' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		update_option( 'default_comment_status', 'closed' );
		update_option( 'default_ping_status', 'closed' );
		// The default "Hello world!" post → draft.
		foreach ( get_posts( array( 'post_type' => 'post', 'name' => 'hello-world', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) { // only that one: Posts are Resources now
			wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
		}
		// News items with a court: keep the value as meta before the taxonomy leaves news.
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT tr.object_id id, t.slug FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'aw_court' JOIN {$wpdb->terms} t ON t.term_id = tt.term_id JOIN {$wpdb->posts} p ON p.ID = tr.object_id AND p.post_type = 'aw_news'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( $rows as $r ) {
			add_post_meta( (int) $r->id, 'aw_court_legacy', $r->slug );
		}
		// Testimonials: export to the private folder (they stay in the database too).
		$t = array();
		foreach ( get_posts( array( 'post_type' => 'aw_testimonial', 'post_status' => 'any', 'numberposts' => -1 ) ) as $p ) {
			$t[] = array( 'id' => $p->ID, 'status' => $p->post_status, 'title' => $p->post_title, 'content' => $p->post_content, 'meta' => get_post_meta( $p->ID ) );
		}
		if ( function_exists( 'aw_private_dir' ) ) {
			file_put_contents( aw_private_dir() . '/testimonials-export-' . gmdate( 'Y-m-d' ) . '.json', wp_json_encode( $t ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		update_option( 'aw_admin_ia_report', array( 'news_court_terms' => count( $rows ), 'testimonials' => count( $t ) ), false );
		update_option( 'aw_admin_ia_done', '1', false );
	}
);
