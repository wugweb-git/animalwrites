<?php
/**
 * Admin counts (owner, 1 Nov 2026): what is new, where the team looks.
 *
 * - Directory → Claims shows the claims still waiting for a decision (menu count, like Mail and Sourcing).
 * - The Dashboard box "Fluent Forms Latest Form Submissions" showed unread/total; campaign letters and newsletter sign-ups
 *   are marked read as they arrive (admin-tidy.php), so it read "0/2745" and looked dead. "Form entries" replaces it with
 *   what arrived in the last 24 hours and the last 7 days, the total, and the unread count for the forms someone has to read.
 *
 * Mail's own count (unread emails and Website forms) is Mailweb's; it shows on the Mail menu and its Dashboard box.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Claims still waiting for a decision: not trashed, and no approve or reject saved for them. */
function aw_claims_pending_count() {
	$form = function_exists( 'aw_claim_ff_id' ) ? aw_claim_ff_id() : 0;
	if ( ! $form ) {
		return 0;
	}
	global $wpdb;
	$sub = $wpdb->prefix . 'fluentform_submissions';
	$met = $wpdb->prefix . 'fluentform_submission_meta';
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$sub} s LEFT JOIN {$met} m ON m.response_id = s.id AND m.meta_key = 'aw_claim_review' WHERE s.form_id = %d AND s.status <> 'trashed' AND ( m.id IS NULL OR ( m.value NOT LIKE %s AND m.value NOT LIKE %s ) )", $form, '%"status":"approved"%', '%"status":"rejected"%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/** The count bubble markup core uses in menus (hidden at 0). */
function aw_admin_bubble( $n ) {
	return $n > 0 ? ' <span class="awaiting-mod count-' . (int) $n . '"><span class="pending-count">' . (int) $n . '</span></span>' : '';
}

add_action(
	'admin_menu',
	function () {
		global $menu, $submenu;
		if ( ! current_user_can( 'edit_aw_volunteers' ) ) {
			return;
		}
		$n = aw_claims_pending_count();
		if ( ! $n ) {
			return;
		}
		$parent = 'edit.php?post_type=aw_listing';
		foreach ( (array) ( $submenu[ $parent ] ?? array() ) as $i => $item ) {
			if ( 'aw-claims' === $item[2] ) {
				$submenu[ $parent ][ $i ][0] .= aw_admin_bubble( $n ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			}
		}
		foreach ( (array) $menu as $i => $item ) {
			if ( $parent === ( $item[2] ?? '' ) ) {
				$menu[ $i ][0] .= aw_admin_bubble( $n ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			}
		}
	},
	99
);

/** Dashboard box "Form entries": per form, the last 24 hours, the last 7 days, the total and the unread. */
add_action(
	'wp_dashboard_setup',
	function () {
		if ( ! current_user_can( 'edit_posts' ) || ! class_exists( '\FluentForm\App\Helpers\Helper' ) ) {
			return;
		}
		remove_meta_box( 'fluentform_stat_widget', 'dashboard', 'normal' );
		remove_meta_box( 'fluentform_stat_widget', 'dashboard', 'side' );
		remove_meta_box( 'fluentform_stat_widget', 'dashboard', 'column3' );
		remove_meta_box( 'fluentform_stat_widget', 'dashboard', 'column4' );
		wp_add_dashboard_widget( 'aw_form_entries', 'Form entries', 'aw_form_entries_widget' );
	},
	99
);

function aw_form_entries_widget() {
	global $wpdb;
	$sub  = $wpdb->prefix . 'fluentform_submissions';
	$day  = wp_date( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
	$week = wp_date( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS );
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT f.id, f.title, SUM( s.created_at >= %s ) AS d, SUM( s.created_at >= %s ) AS w, COUNT( s.id ) AS t, SUM( s.status = 'unread' ) AS u FROM {$wpdb->prefix}fluentform_forms f LEFT JOIN {$sub} s ON s.form_id = f.id AND s.status NOT IN ( 'trashed', 'spam' ) WHERE f.status = 'published' GROUP BY f.id, f.title HAVING t > 0 ORDER BY MAX( s.created_at ) DESC", $day, $week ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$cells = array();
	foreach ( (array) $rows as $r ) {
		$url     = add_query_arg( array( 'page' => 'fluent_forms', 'route' => 'entries', 'form_id' => (int) $r->id ), admin_url( 'admin.php' ) );
		$cells[] = array(
			'form'   => '<a href="' . esc_url( $url ) . '">' . esc_html( $r->title ) . '</a>',
			'day'    => esc_html( number_format_i18n( (int) $r->d ) ),
			'week'   => esc_html( number_format_i18n( (int) $r->w ) ),
			'total'  => esc_html( number_format_i18n( (int) $r->t ) ),
			'unread' => (int) $r->u ? '<strong>' . esc_html( number_format_i18n( (int) $r->u ) ) . '</strong>' : '0',
		);
	}
	aw_core_simple_table(
		'form-entries',
		array( 'form' => 'Form', 'day' => 'Last 24 hours', 'week' => 'Last 7 days', 'total' => 'Total', 'unread' => 'Unread' ),
		$cells,
		array( 'fixed' => true, 'empty' => 'No entries yet.' )
	);
	echo '<p class="description">Campaign letters and newsletter sign-ups are read as they arrive (they are handled in FluentCRM), so Unread counts only the forms someone has to answer.</p>';
}
