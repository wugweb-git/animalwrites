<?php
/**
 * Dashboard: Sourcing (owner, 3 Nov 2026: one Dashboard box per custom plugin). Cards (to review, workers needing
 * attention, AI keys refused) and one table of the workers with their last run and status, problems first. Read-only:
 * the To review count, the workers' stored run reports and the AI models' saved statuses; no remote calls.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI keys refused or out of credit: the same rule as the Sourcing overview's AI line (aw_sourcing_overview_ai()).
 * Null when the keys are not saved on the AI models screen yet. Counts only; never reads a key's value.
 */
function aw_sourcing_dash_ai_refused(): ?int {
	$rows = get_option( 'aw_ai_models', null );
	if ( ! is_array( $rows ) ) {
		return null;
	}
	$bad = 0;
	foreach ( $rows as $r ) {
		if ( ! is_array( $r ) || empty( $r['key'] ) ) {
			continue;
		}
		$st = (string) ( $r['status'] ?? 'unchecked' );
		if ( ! in_array( $st, array( 'tool', 'works', 'limited', 'unchecked', 'card' ), true ) ) {
			++$bad;
		}
	}
	return $bad;
}

/**
 * One row per worker (aw_workers_all(): the registry's, then Planned), problems first.
 *
 * @return array[] name, last (Unix or 0), status (failed, late, running, ok, off), problem (bool).
 */
function aw_sourcing_dash_workers(): array {
	$kinds = array();
	foreach ( array_keys( aw_workers_problems() ) as $k ) {
		list( $file, $kind )              = explode( ':', $k, 2 );
		$kinds[ aw_worker_id( $file ) ][] = $kind;
	}
	$rows = array();
	foreach ( aw_workers_all() as $id => $w ) {
		$last  = aw_workers_last( $w['file'] );
		$k     = $kinds[ $id ] ?? array();
		$reg   = (array) ( $w['registry'] ?? array() );
		$sched = ! empty( $reg['crons'] ) || ! empty( $reg['after'] );
		if ( array_intersect( $k, array( 'late', 'stuck' ) ) ) {
			$status = 'late';
		} elseif ( $k ) {
			$status = 'failed';
		} elseif ( $w['planned'] || ! $sched ) {
			$status = 'off'; // Planned, or run by hand only
		} elseif ( ! $last ) {
			$status = 'late'; // scheduled but has never reported
		} elseif ( 'running' === (string) ( $last['result'] ?? '' ) ) {
			$status = 'running';
		} else {
			$status = 'ok';
		}
		$rows[] = array(
			'name'    => (string) $w['name'],
			'last'    => $last ? (int) ( $last['started'] ?? 0 ) : 0,
			'status'  => $status,
			'problem' => in_array( $status, array( 'failed', 'late' ), true ),
		);
	}
	$order = array_flip( array( 'failed', 'late', 'running', 'ok', 'off' ) );
	usort( $rows, fn( $a, $b ) => $order[ $a['status'] ] <=> $order[ $b['status'] ] ); // stable: the registry's order within a status
	return $rows;
}

add_action(
	'wp_dashboard_setup',
	function () {
		if ( ! current_user_can( AW_REVIEW_CAP ) ) {
			return; // the Sourcing menu's own capability
		}
		wp_add_dashboard_widget(
			'aw_sourcing_dashboard',
			'Sourcing',
			function () {
				$rows    = aw_sourcing_dash_workers();
				$need    = count( array_filter( $rows, fn( $r ) => $r['problem'] ) );
				$refused = aw_sourcing_dash_ai_refused();
				$review  = aw_review_count();
				$to_rev  = aw_sourcing_overview_link( 'To review', admin_url( 'admin.php?page=aw-review' ), AW_REVIEW_CAP );
				if ( function_exists( 'kits_dash_cards' ) ) {
					echo kits_dash_cards( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
						array(
							array( 'label' => 'To review', 'value' => number_format_i18n( $review ) ),
							array( 'label' => 'Workers needing attention', 'value' => number_format_i18n( $need ) ),
							array( 'label' => 'AI keys refused', 'value' => null === $refused ? '–' : number_format_i18n( $refused ) ),
						)
					);
				}
				/* translators: 1: number of items, 2: link to To review. */
				echo '<p>' . sprintf( esc_html( _n( '%1$d item waiting in %2$s.', '%1$d items waiting in %2$s.', $review ) ), (int) $review, $to_rev ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the link is escaped by aw_sourcing_overview_link().

				$status = array(
					'failed'  => array( 'dismiss', '#b32d2e', 'Failed' ),
					'late'    => array( 'clock', '#b32d2e', 'Late' ),
					'running' => array( 'update', '#50575e', 'Running' ),
					'ok'      => array( 'yes-alt', '#007017', 'OK' ),
					'off'     => array( 'minus', '#50575e', 'Off' ),
				);
				/* translators: 1: number of workers, 2: how many need attention. */
				$summary = sprintf( 'Workers (%1$s, %2$s need attention)', number_format_i18n( count( $rows ) ), number_format_i18n( $need ) );
				echo '<details' . ( $need ? ' open' : '' ) . '><summary>' . esc_html( $summary ) . '</summary>';
				echo '<table class="widefat striped"><thead><tr><th scope="col">Worker</th><th scope="col">Last run</th><th scope="col">Status</th></tr></thead><tbody>';
				foreach ( $rows as $r ) {
					$s = $status[ $r['status'] ];
					echo '<tr><td style="overflow-wrap:anywhere">' . esc_html( $r['name'] ) . '</td><td>' . esc_html( $r['last'] ? wp_date( 'j M, g:i a', $r['last'] ) : 'never' ) . '</td><td><span class="dashicons dashicons-' . esc_attr( $s[0] ) . '" style="color:' . esc_attr( $s[1] ) . '" aria-hidden="true"></span> ' . esc_html( $s[2] ) . '</td></tr>';
				}
				echo '</tbody></table></details>';

				echo '<p>' . aw_sourcing_overview_link( 'SourceWeb → Workers', admin_url( 'admin.php?page=aw-sourcing&tab=workers' ) ) . ' | ' . $to_rev . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by aw_sourcing_overview_link().
			}
		);
	},
	20
);
