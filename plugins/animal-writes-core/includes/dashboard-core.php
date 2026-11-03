<?php
/**
 * Dashboard: one "Animal Writes" box for the core plugin (owner, 3 Nov 2026: "one from core"). Campaign letters, Form
 * entries, Dates, Activity and Scheduled tasks are sections of it, each a <details> whose summary line carries its
 * key figure. A section registers itself with aw_dash_section() on wp_dashboard_setup (after its own permission
 * check), and optionally its summary with aw_dash_summary(); the box is added once all have registered.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The order of the sections in the box (ids not listed come after, in the order they registered). */
const AW_DASH_ORDER = array( 'aw_cl_widget', 'aw_form_entries', 'aw_team_dates', 'aw_activity', 'aw_cron_health' );

/**
 * Register a section of the Animal Writes box (same arguments as wp_add_dashboard_widget).
 *
 * @param string   $id       Section id.
 * @param string   $title    Title.
 * @param callable $callback Prints the section.
 */
function aw_dash_section( string $id, string $title, callable $callback ): void {
	$GLOBALS['aw_dash_sections'][ $id ] = array(
		'title'    => $title,
		'callback' => $callback,
		'summary'  => $GLOBALS['aw_dash_sections'][ $id ]['summary'] ?? null,
	);
}

/**
 * The short line beside a section's title (its key figure), worked out when the box is drawn.
 *
 * @param string   $id      Section id.
 * @param callable $summary Returns a short string.
 */
function aw_dash_summary( string $id, callable $summary ): void {
	$GLOBALS['aw_dash_sections'][ $id ]['summary'] = $summary;
}

add_action(
	'wp_dashboard_setup',
	function () {
		$all = array_filter( (array) ( $GLOBALS['aw_dash_sections'] ?? array() ), fn( $s ) => isset( $s['callback'] ) );
		if ( ! $all ) {
			return;
		}
		uksort(
			$all,
			function ( $a, $b ) {
				$x = array_search( $a, AW_DASH_ORDER, true );
				$y = array_search( $b, AW_DASH_ORDER, true );
				return ( false === $x ? 99 : $x ) <=> ( false === $y ? 99 : $y );
			}
		);
		wp_add_dashboard_widget(
			'aw_core_dashboard',
			'Animal Writes',
			function () use ( $all ) {
				$first = true;
				foreach ( $all as $id => $s ) {
					$sum = isset( $s['summary'] ) && is_callable( $s['summary'] ) ? (string) call_user_func( $s['summary'] ) : '';
					echo '<details id="' . esc_attr( $id ) . '" style="margin:0 0 12px"' . ( $first ? ' open' : '' ) . '><summary style="cursor:pointer;padding:4px 0"><strong>' . esc_html( $s['title'] ) . '</strong>' . ( '' !== $sum ? ' <span class="description">' . esc_html( $sum ) . '</span>' : '' ) . '</summary><div style="margin-top:8px">';
					call_user_func( $s['callback'] );
					echo '</div></details>';
					$first = false;
				}
			}
		);
	},
	50
);
