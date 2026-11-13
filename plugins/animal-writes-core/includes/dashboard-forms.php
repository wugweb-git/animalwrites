<?php
/**
 * Dashboard: Form entries (owner, 3 Nov 2026: "improve this too - fluent stuff"). Replaces Fluent Forms' own
 * "Latest Form Submissions" box with cards (unread, new in 7 days) and one table: form, unread, last 7 days, total,
 * each form linking to its entries in Fluent Forms. Read-only: counts from Fluent Forms' own submissions table.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Whether the current user may see entry counts: Fluent Forms' own rule, else administrators. */
function aw_forms_widget_allowed(): bool {
	if ( class_exists( '\FluentForm\App\Modules\Acl\Acl' ) ) {
		$acl = '\FluentForm\App\Modules\Acl\Acl';
		return (bool) $acl::getCurrentUserCapability() && (bool) $acl::hasPermission( array( 'fluentform_entries_viewer', 'fluentform_manage_entries' ) );
	}
	return current_user_can( 'manage_options' );
}

/** Per form: id, title, unread, week, total (trashed entries left out), busiest first. */
function aw_forms_widget_rows(): array {
	global $wpdb;
	$s    = $wpdb->prefix . 'fluentform_submissions';
	$f    = $wpdb->prefix . 'fluentform_forms';
	$since = gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS );
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT f.id, f.title, COUNT(s.id) AS total, COALESCE(SUM(s.status = 'unread'), 0) AS unread, COALESCE(SUM(s.created_at >= %s), 0) AS week FROM %i f LEFT JOIN %i s ON s.form_id = f.id AND s.status <> 'trashed' GROUP BY f.id, f.title ORDER BY total DESC, f.title", $since, $f, $s ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

add_action(
	'wp_dashboard_setup',
	function () {
		if ( ! defined( 'FLUENTFORM' ) && ! class_exists( '\FluentForm\Framework\Foundation\Application' ) ) {
			return; // Fluent Forms is not active
		}
		if ( ! aw_forms_widget_allowed() ) {
			return;
		}
		wp_add_dashboard_widget(
			'aw_form_entries',
			'Form entries',
			function () {
				$rows = aw_forms_widget_rows();
				if ( ! $rows ) {
					echo '<p>No forms yet.</p>';
					return;
				}
				$unread = array_sum( array_map( fn( $r ) => (int) $r->unread, $rows ) );
				$week   = array_sum( array_map( fn( $r ) => (int) $r->week, $rows ) );
				if ( function_exists( 'kits_dash_cards' ) ) {
					echo kits_dash_cards( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
						array(
							array( 'label' => 'Unread', 'value' => number_format_i18n( $unread ) ),
							array( 'label' => 'New in the last 7 days', 'value' => number_format_i18n( $week ) ),
						)
					);
				}
				$cells = array();
				foreach ( $rows as $r ) {
					$url     = admin_url( 'admin.php?page=fluent_forms&route=entries&form_id=' . (int) $r->id );
					$u       = (int) $r->unread;
					$cells[] = array(
						'form'   => '<a href="' . esc_url( $url ) . '">' . esc_html( (string) $r->title ) . '</a>',
						'unread' => $u ? '<strong>' . esc_html( number_format_i18n( $u ) ) . '</strong>' : '0',
						'week'   => esc_html( number_format_i18n( (int) $r->week ) ),
						'total'  => esc_html( number_format_i18n( (int) $r->total ) ),
					);
				}
				aw_core_simple_table(
					'aw-form-entries',
					array(
						'form'   => 'Form',
						'unread' => 'Unread',
						'week'   => 'Last 7 days',
						'total'  => 'Total',
					),
					$cells,
					array( 'foot' => false ) // a short table in a Dashboard widget: no repeated header row
				);
				echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=fluent_forms_all_entries' ) ) . '">All entries</a></p>';
			}
		);
	},
	20
);

// Fluent Forms' own box shows the same counts: ours replaces it.
add_action(
	'wp_dashboard_setup',
	function () {
		remove_meta_box( 'fluentform_stat_widget', 'dashboard', 'normal' );
		remove_meta_box( 'fluentform_stat_widget', 'dashboard', 'side' );
	},
	99
);
