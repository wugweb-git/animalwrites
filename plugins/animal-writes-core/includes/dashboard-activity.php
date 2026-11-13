<?php
/**
 * Dashboard: Activity (owner, 3 Nov 2026: every Dashboard box simple and consistent). Replaces WordPress's own
 * "Activity" box with cards (published in 7 days, scheduled, awaiting review) and one table of the newest items, plus
 * the next scheduled ones. Covers posts, pages and the public custom post types. Read-only: counts and rows straight
 * from the posts table. No comments part: comments are off on this site.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The post types the box covers: post, page and every public custom post type. */
function aw_activity_types(): array {
	$types = array_merge( array( 'post', 'page' ), array_values( get_post_types( array( 'public' => true, '_builtin' => false ) ) ) );
	return array_values( array_unique( $types ) );
}

/** One query: published in the last 7 days, scheduled, awaiting review. */
function aw_activity_counts( array $types ): array {
	global $wpdb;
	$in    = implode( ',', array_fill( 0, count( $types ), '%s' ) );
	$since = gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS );
	$row   = $wpdb->get_row( $wpdb->prepare( "SELECT COALESCE(SUM(post_status = 'publish' AND post_date_gmt >= %s), 0) AS week, COALESCE(SUM(post_status = 'future'), 0) AS future, COALESCE(SUM(post_status = 'pending'), 0) AS pending FROM {$wpdb->posts} WHERE post_type IN ({$in}) AND post_status IN ('publish', 'future', 'pending')", array_merge( array( $since ), $types ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders only.
	return array(
		'week'    => (int) ( $row->week ?? 0 ),
		'future'  => (int) ( $row->future ?? 0 ),
		'pending' => (int) ( $row->pending ?? 0 ),
	);
}

/** The 5 newest published items, or the next 5 scheduled ones ('future'). */
function aw_activity_items( array $types, string $status ): array {
	global $wpdb;
	$in    = implode( ',', array_fill( 0, count( $types ), '%s' ) );
	$order = 'future' === $status ? 'ASC' : 'DESC';
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_type, post_date_gmt FROM {$wpdb->posts} WHERE post_type IN ({$in}) AND post_status = %s ORDER BY post_date {$order}, ID {$order} LIMIT 5", array_merge( $types, array( 'future' === $status ? 'future' : 'publish' ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders and a fixed ASC/DESC only.
}

/** A table of items: Item (linked to its edit screen when allowed), Type, Date; a core list table. */
function aw_activity_table( array $items, string $caption ): string {
	$cells = array();
	foreach ( $items as $p ) {
		$id    = (int) $p->ID;
		$title = '' !== trim( (string) $p->post_title ) ? (string) $p->post_title : '(no title)';
		$link  = current_user_can( 'edit_post', $id ) ? get_edit_post_link( $id ) : '';
		$obj   = get_post_type_object( (string) $p->post_type );
		$type  = $obj ? (string) $obj->labels->singular_name : (string) $p->post_type;
		$ts    = strtotime( (string) $p->post_date_gmt . ' UTC' );
		$cells[] = array(
			'item' => $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a>' : esc_html( $title ),
			'type' => esc_html( $type ),
			'date' => esc_html( $ts ? (string) wp_date( 'j M', $ts ) : '' ),
		);
	}
	ob_start();
	aw_core_simple_table(
		'aw-activity-' . sanitize_key( $caption ),
		array(
			'item' => 'Item',
			'type' => 'Type',
			'date' => 'Date',
		),
		$cells,
		array(
			'foot'    => false, // a short table in a Dashboard widget: no repeated header row
			'caption' => $caption,
		)
	);
	return (string) ob_get_clean();
}

add_action(
	'wp_dashboard_setup',
	function () {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'aw_activity',
			'Activity',
			function () {
				$types  = aw_activity_types();
				$counts = aw_activity_counts( $types );
				if ( function_exists( 'kits_dash_cards' ) ) {
					echo kits_dash_cards( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
						array(
							array( 'label' => 'Published, last 7 days', 'value' => number_format_i18n( $counts['week'] ) ),
							array( 'label' => 'Scheduled', 'value' => number_format_i18n( $counts['future'] ) ),
							array( 'label' => 'Awaiting review', 'value' => number_format_i18n( $counts['pending'] ) ),
						)
					);
				}
				$recent = aw_activity_items( $types, 'publish' );
				echo '<h3>Recently published</h3>';
				if ( $recent ) {
					echo aw_activity_table( $recent, 'Recently published' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
				} else {
					echo '<p>Nothing published yet.</p>';
				}
				if ( $counts['future'] > 0 ) {
					$next = aw_activity_items( $types, 'future' );
					echo '<details><summary>' . esc_html( 'Scheduled (' . number_format_i18n( $counts['future'] ) . ')' ) . '</summary>';
					echo aw_activity_table( $next, 'Scheduled' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
					echo '</details>';
				}
			}
		);
	},
	20
);

// WordPress's own Activity box: ours replaces it.
add_action(
	'wp_dashboard_setup',
	function () {
		remove_meta_box( 'dashboard_activity', 'dashboard', 'normal' );
	},
	99
);
