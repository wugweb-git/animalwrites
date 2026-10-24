<?php
/**
 * Inbox → To review: one list of everything that came in automatically and is waiting for a person — news, Library
 * items, opportunities and press — with why each was held (aw_review). Publish puts an item live; Reject moves it to
 * Drafts with a note (nothing is deleted). One at a time or several together. Replaces "Press to review".
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Types in the queue: post type => label. */
const AW_REVIEW_TYPES = array(
	'aw_news'        => 'News',
	'aw_legal'       => 'Library',
	'aw_opportunity' => 'Opportunity',
	'aw_press'       => 'Press',
);

/**
 * Items waiting: Pending, plus drafts the automatic review held back.
 *
 * @param string $type One type, or '' for all.
 * @return WP_Post[]
 */
function aw_review_items( $type = '' ) {
	$types   = $type && isset( AW_REVIEW_TYPES[ $type ] ) ? array( $type ) : array_keys( AW_REVIEW_TYPES );
	$pending = get_posts( array( 'post_type' => $types, 'post_status' => 'pending', 'numberposts' => 300, 'orderby' => 'date', 'order' => 'DESC' ) );
	$held    = get_posts(
		array(
			'post_type'   => $types,
			'post_status' => 'draft',
			'numberposts' => 300,
			'meta_query'  => array( array( 'key' => 'aw_review', 'value' => 'held:', 'compare' => 'LIKE' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	$all = array_merge( $pending, $held );
	// Only items the relevance check passed (or has not checked): "not relevant" never reaches a person (owner, 10 Oct 2026).
	if ( function_exists( 'aw_relevance_failed' ) ) {
		$all = array_values( array_filter( $all, fn( $p ) => ! aw_relevance_failed( $p->ID ) ) );
	}
	usort( $all, fn( $a, $b ) => strcmp( $b->post_date, $a->post_date ) );
	return $all;
}

/** How many items are waiting (for the menu count). */
function aw_review_count() {
	static $n = null;
	if ( null === $n ) {
		$cached = get_transient( 'aw_review_count' ); // cached 5 minutes; aw_inbox_counts_flush() (admin-ia.php) clears it (audit 9)
		if ( false === $cached ) {
			$cached = count( aw_review_items() );
			set_transient( 'aw_review_count', $cached, 5 * MINUTE_IN_SECONDS );
		}
		$n = (int) $cached;
	}
	return $n;
}

add_action(
	'admin_menu',
	function () {
		$hook = add_submenu_page( 'aw-inbox', 'To review', 'To review' . ( function_exists( 'aw_menu_count' ) ? aw_menu_count( aw_review_count() ) : '' ), 'edit_others_posts', 'aw-review', 'aw_review_page' );
		if ( $hook ) { // Screen options: items per page.
			add_action( 'load-' . $hook, fn() => add_screen_option( 'per_page', array( 'label' => 'Items per page', 'default' => 50, 'option' => 'aw_review_per_page' ) ) );
		}
		remove_submenu_page( 'aw-inbox', 'edit.php?post_type=aw_press&post_status=pending' ); // now part of To review
	},
	21
);

/**
 * Publish or reject items.
 *
 * @param int[]  $ids    Post IDs.
 * @param string $action 'publish' or 'reject'.
 * @return int Items changed.
 */
function aw_review_apply( $ids, $action ) {
	$n    = 0;
	$who  = wp_get_current_user()->display_name;
	$when = current_time( 'Y-m-d H:i' );
	foreach ( array_map( 'intval', $ids ) as $id ) {
		$p = get_post( $id );
		if ( ! $p || ! isset( AW_REVIEW_TYPES[ $p->post_type ] ) || ! in_array( $p->post_status, array( 'pending', 'draft' ), true ) || ! current_user_can( 'edit_post', $id ) ) {
			continue;
		}
		if ( 'publish' === $action ) {
			$src = (string) get_post_meta( $id, 'aw_source_date', true );
			if ( get_post_meta( $id, 'aw_backfill', true ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $src ) && $src <= current_time( 'Y-m-d' ) ) {
				// Backfilled items keep their original date (not the day they were approved).
				wp_update_post( array( 'ID' => $id, 'post_status' => 'publish', 'post_date' => $src . ' 12:00:00', 'post_date_gmt' => get_gmt_from_date( $src . ' 12:00:00' ), 'edit_date' => true ) );
			} else {
				wp_publish_post( $id );
			}
			update_post_meta( $id, 'aw_review', 'approved by ' . $who . ' ' . $when );
		} else {
			wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
			update_post_meta( $id, 'aw_review', 'rejected by ' . $who . ' ' . $when . ' (kept in Drafts)' );
		}
		++$n;
	}
	return $n;
}

add_action(
	'admin_post_aw_review',
	function () {
		if ( ! current_user_can( 'edit_others_posts' ) || ! check_admin_referer( 'aw_review' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		// Two action menus (above and below the list): whichever one was set wins.
		$do     = '' !== (string) ( $_REQUEST['do'] ?? '' ) ? $_REQUEST['do'] : ( $_REQUEST['do2'] ?? '' ); // phpcs:ignore WordPress.Security
		$action = in_array( $do, array( 'publish', 'reject' ), true ) ? sanitize_key( $do ) : '';
		$ids    = array_map( 'intval', (array) ( $_REQUEST['ids'] ?? array() ) );
		if ( ! empty( $_REQUEST['all'] ) ) { // "Publish all N": every item in this view, not only the ticked ones
			$ids = wp_list_pluck( aw_review_items( sanitize_key( $_REQUEST['type'] ?? '' ) ), 'ID' );
		}
		$n      = $action ? aw_review_apply( $ids, $action ) : 0;
		$back   = admin_url( 'admin.php?page=aw-review' . ( ! empty( $_REQUEST['type'] ) ? '&type=' . sanitize_key( $_REQUEST['type'] ) : '' ) );
		// Back to the same view, search, sort and page (the page-number box posts paged).
		$back = remove_query_arg( array( 'done', 'n', 'paged' ), wp_get_referer() && false !== strpos( wp_get_referer(), 'page=aw-review' ) ? wp_get_referer() : $back );
		$page = absint( wp_unslash( $_REQUEST['paged'] ?? 0 ) );
		$back = $page > 1 ? add_query_arg( 'paged', $page, $back ) : $back;
		// A different page number is the page box (navigation); the same page with no action is an empty Apply.
		parse_str( (string) wp_parse_url( (string) wp_get_referer(), PHP_URL_QUERY ), $ref );
		$moved = $page && absint( $ref['paged'] ?? 1 ) !== $page;
		wp_safe_redirect( $action || ! $moved ? add_query_arg( array( 'done' => $action ? $action : 'none', 'n' => $n ), $back ) : $back );
		exit;
	}
);

function aw_review_page() {
	if ( ! class_exists( 'WP_List_Table' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	}
	require_once __DIR__ . '/class-aw-sourcing-review-table.php';
	$table = new AW_Sourcing_Review_Table();
	$table->prepare_items();
	$type = sanitize_key( $_GET['type'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	echo '<div class="wrap"><h1 class="wp-heading-inline">To review</h1><hr class="wp-header-end">';
	echo '<p>Items that came in automatically and need a person: why each was held is shown beside it. Publish puts it live; Reject moves it to Drafts (nothing is deleted).</p>';
	if ( isset( $_GET['done'], $_GET['n'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$n = (int) $_GET['n']; // phpcs:ignore WordPress.Security.NonceVerification
		if ( in_array( $_GET['done'], array( 'publish', 'reject' ), true ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( 'publish' === $_GET['done'] ? _n( '%d item published.', '%d items published.', $n ) : _n( '%d item moved to Drafts.', '%d items moved to Drafts.', $n ), $n ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification
		} else {
			echo '<div class="notice notice-warning is-dismissible"><p>Nothing changed: choose Publish or Reject and tick at least one item.</p></div>';
		}
	}
	$table->views();
	echo '<form method="get"><input type="hidden" name="page" value="aw-review">' . ( $type ? '<input type="hidden" name="type" value="' . esc_attr( $type ) . '">' : '' );
	$table->search_box( 'Search items', 'aw-review' );
	echo '</form>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'aw_review' );
	echo '<input type="hidden" name="action" value="aw_review"><input type="hidden" name="type" value="' . esc_attr( $type ) . '">';
	$table->display();
	echo '</form></div>';
}

// The result notice stays off the list's own links; only on this screen (other screens use done and n too).
add_filter( 'removable_query_args', fn( $args ) => 'aw-review' === sanitize_key( $_GET['page'] ?? '' ) ? array_merge( $args, array( 'done', 'n' ) ) : $args ); // phpcs:ignore WordPress.Security.NonceVerification
add_filter( 'set_screen_option_aw_review_per_page', fn( $keep, $option, $value ) => max( 1, min( 300, (int) $value ) ), 10, 3 );

/* ---------- News and Library lists (edit.php): bulk Publish / Approve on every ticked item, and "Publish all" ---------- */

foreach ( array_keys( AW_REVIEW_TYPES ) as $aw_rt ) {
	add_filter( 'bulk_actions-edit-' . $aw_rt, fn( $a ) => array( 'aw_publish' => 'Publish (approve)' ) + $a );
	add_filter(
		'handle_bulk_actions-edit-' . $aw_rt,
		function ( $back, $action, $ids ) {
			if ( 'aw_publish' !== $action ) {
				return $back;
			}
			return add_query_arg( 'aw_published', aw_review_apply( (array) $ids, 'publish' ), $back );
		},
		10,
		3
	);
}

// "Publish all N in this view" on the Pending and Draft views (every page, not only the one on screen).
add_action(
	'restrict_manage_posts',
	function ( $type, $which ) {
		$status = sanitize_key( $_GET['post_status'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'top' !== $which || ! isset( AW_REVIEW_TYPES[ $type ] ) || ! in_array( $status, array( 'pending', 'draft' ), true ) ) {
			return;
		}
		$n = (int) ( wp_count_posts( $type )->$status ?? 0 );
		if ( $n ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=aw_publish_all&post_type=' . $type . '&status=' . $status ), 'aw_publish_all' );
			echo '<a class="button" href="' . esc_url( $url ) . '" onclick="return confirm(' . esc_attr( wp_json_encode( sprintf( 'Publish all %d items in this view?', $n ) ) ) . ')">' . esc_html( sprintf( 'Publish all %d', $n ) ) . '</a>';
		}
	},
	10,
	2
);
add_action(
	'admin_post_aw_publish_all',
	function () {
		$type   = sanitize_key( $_GET['post_type'] ?? '' );
		$status = sanitize_key( $_GET['status'] ?? '' );
		if ( ! current_user_can( 'edit_others_posts' ) || ! check_admin_referer( 'aw_publish_all' ) || ! isset( AW_REVIEW_TYPES[ $type ] ) || ! in_array( $status, array( 'pending', 'draft' ), true ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$ids = get_posts( array( 'post_type' => $type, 'post_status' => $status, 'fields' => 'ids', 'numberposts' => -1 ) );
		$n   = aw_review_apply( $ids, 'publish' );
		wp_safe_redirect( add_query_arg( array( 'post_type' => $type, 'post_status' => $status, 'aw_published' => $n ), admin_url( 'edit.php' ) ) );
		exit;
	}
);
add_action(
	'admin_notices',
	function () {
		if ( isset( $_GET['aw_published'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$n = (int) $_GET['aw_published']; // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( _n( '%d item published.', '%d items published.', $n ), $n ) ) . '</p></div>';
		}
	}
);
