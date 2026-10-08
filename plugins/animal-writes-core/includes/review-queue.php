<?php
/**
 * Inbox → To review: one list of everything that came in automatically and is waiting for a person — news, Library
 * items, opportunities and press — with why each was held (aw_review). Publish puts an item live; Reject moves it to
 * Drafts with a note (nothing is deleted). One at a time or several together. Replaces "Press to review".
 *
 * @package animal-writes-core
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
	usort( $all, fn( $a, $b ) => strcmp( $b->post_date, $a->post_date ) );
	return $all;
}

/** How many items are waiting (for the menu count). */
function aw_review_count() {
	static $n = null;
	if ( null === $n ) {
		$n = count( aw_review_items() );
	}
	return $n;
}

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'aw-inbox', 'To review', 'To review' . ( function_exists( 'aw_menu_count' ) ? aw_menu_count( aw_review_count() ) : '' ), 'edit_others_posts', 'aw-review', 'aw_review_page' );
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
			wp_publish_post( $id );
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
		$action = in_array( $_REQUEST['do'] ?? '', array( 'publish', 'reject' ), true ) ? sanitize_key( $_REQUEST['do'] ) : '';
		$ids    = array_map( 'intval', (array) ( $_REQUEST['ids'] ?? array() ) );
		$n      = $action ? aw_review_apply( $ids, $action ) : 0;
		$back   = admin_url( 'admin.php?page=aw-review' . ( ! empty( $_REQUEST['type'] ) ? '&type=' . sanitize_key( $_REQUEST['type'] ) : '' ) );
		wp_safe_redirect( add_query_arg( array( 'done' => $action, 'n' => $n ), $back ) );
		exit;
	}
);

function aw_review_page() {
	$type  = sanitize_key( $_GET['type'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	$items = aw_review_items( $type );
	$base  = admin_url( 'admin.php?page=aw-review' );
	echo '<div class="wrap"><h1 class="wp-heading-inline">To review</h1><hr class="wp-header-end">';
	echo '<p>Items that came in automatically and need a person: why each was held is shown beside it. Publish puts it live; Reject moves it to Drafts (nothing is deleted).</p>';
	if ( isset( $_GET['done'], $_GET['n'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$n = (int) $_GET['n']; // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( 'publish' === $_GET['done'] ? _n( '%d item published.', '%d items published.', $n ) : _n( '%d item moved to Drafts.', '%d items moved to Drafts.', $n ), $n ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification
	}
	// Views: All · News · Library · Opportunity · Press, with counts.
	$views = array( '<a href="' . esc_url( $base ) . '"' . ( '' === $type ? ' class="current" aria-current="page"' : '' ) . '>All <span class="count">(' . count( aw_review_items() ) . ')</span></a>' );
	foreach ( AW_REVIEW_TYPES as $t => $label ) {
		$c = count( aw_review_items( $t ) );
		if ( $c ) {
			$views[] = '<a href="' . esc_url( add_query_arg( 'type', $t, $base ) ) . '"' . ( $t === $type ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $label ) . ' <span class="count">(' . $c . ')</span></a>';
		}
	}
	echo '<ul class="subsubsub"><li>' . implode( ' | </li><li>', $views ) . '</li></ul>'; // phpcs:ignore WordPress.Security.EscapeOutput
	if ( ! $items ) {
		echo '<p style="clear:both;padding-top:1em">Nothing is waiting. New items that pass the checks go live on their own.</p></div>';
		return;
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="clear:both">';
	wp_nonce_field( 'aw_review' );
	echo '<input type="hidden" name="action" value="aw_review"><input type="hidden" name="type" value="' . esc_attr( $type ) . '">';
	$bulk = '<div class="tablenav"><div class="alignleft actions bulkactions"><label class="screen-reader-text" for="aw-review-do">Action for the ticked items</label><select name="do" id="aw-review-do"><option value="">With the ticked items…</option><option value="publish">Publish</option><option value="reject">Reject (move to Drafts)</option></select> <button class="button action">Apply</button></div></div>';
	echo $bulk; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<table class="wp-list-table widefat fixed striped"><thead><tr><td class="manage-column column-cb check-column"><input type="checkbox" aria-label="Tick all"></td><th style="width:9%">Type</th><th>Item</th><th style="width:16%">Source</th><th style="width:22%">Why it is waiting</th><th style="width:10%">Received</th><th style="width:15%">Decide</th></tr></thead><tbody>';
	foreach ( $items as $p ) {
		$url    = (string) get_post_meta( $p->ID, 'aw_source_url', true );
		$url    = $url ? $url : (string) get_post_meta( $p->ID, 'aw_press_url', true );
		$host   = $url ? (string) wp_parse_url( $url, PHP_URL_HOST ) : '';
		$why    = (string) get_post_meta( $p->ID, 'aw_review', true );
		$why    = $why ? preg_replace( '/^held:\s*/', '', $why ) : ( 'pending' === $p->post_status ? 'waiting for a first review' : '' );
		$act    = fn( $do, $label, $cls ) => '<a class="button ' . $cls . '" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=aw_review&do=' . $do . '&ids[]=' . $p->ID . '&type=' . $type ), 'aw_review' ) ) . '">' . esc_html( $label ) . '</a>';
		$excerpt = wp_trim_words( wp_strip_all_tags( $p->post_excerpt ? $p->post_excerpt : $p->post_content ), 24 );
		echo '<tr><th scope="row" class="check-column"><input type="checkbox" name="ids[]" value="' . (int) $p->ID . '" aria-label="' . esc_attr( 'Tick ' . get_the_title( $p ) ) . '"></th>'
			. '<td>' . esc_html( AW_REVIEW_TYPES[ $p->post_type ] ) . '</td>'
			. '<td><strong><a href="' . esc_url( get_edit_post_link( $p->ID ) ) . '">' . esc_html( get_the_title( $p ) ? get_the_title( $p ) : '(no title)' ) . '</a></strong>' . ( $excerpt ? '<br><span style="color:#50575e">' . esc_html( $excerpt ) . '</span>' : '' ) . '</td>'
			. '<td>' . ( $url ? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $host ) . '</a>' : '—' ) . '</td>'
			. '<td>' . esc_html( $why ) . '</td>'
			. '<td>' . esc_html( get_the_date( 'j M Y', $p ) ) . '</td>'
			. '<td>' . $act( 'publish', 'Publish', 'button-primary' ) . ' ' . $act( 'reject', 'Reject', '' ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</tbody></table>' . $bulk . '</form></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
}
