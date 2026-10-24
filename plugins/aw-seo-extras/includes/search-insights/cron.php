<?php
/**
 * Schedule (plan §6, IST): the daily run at 07:10 (after the Search demand refresh at 07:00) and the rollup at 23:30.
 * Each step records its own result in the run log, so one failure never hides the others.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SI_CRON_DAILY  = 'aw_si_daily';
const AW_SI_CRON_ROLLUP = 'aw_si_rollup';

/**
 * The daily run: tables, the one-time seed, Search Console, roles, then the calculation. Skipped while the owner has
 * switched Search Insights off by deleting its data, unless started by hand.
 *
 * @param bool $by_hand Started from Settings or WP-CLI: switches Search Insights on again.
 */
function aw_si_daily( bool $by_hand = false ): array {
	if ( get_option( 'aw_si_off' ) ) {
		if ( ! $by_hand ) {
			return array( 'off' => true );
		}
		delete_option( 'aw_si_off' );
	}
	aw_si_install();
	aw_si_run_start( 'gsc' );
	$out        = array( 'seed' => aw_si_seed() );
	$gsc        = aw_si_gsc_run();
	$out['gsc'] = $gsc;
	aw_si_run_start( 'roles' );
	try {
		$out['roles'] = aw_si_sync_roles();
		aw_si_run_ok( 'roles', $out['roles'] );
	} catch ( Throwable $e ) {
		aw_si_run_fail( 'roles', $e->getMessage() );
	}
	$out['compute'] = aw_si_compute();
	return $out;
}
add_action( AW_SI_CRON_DAILY, fn() => aw_si_daily() );

/** The nightly rollup, with its result in the run log. */
function aw_si_rollup_cron(): void {
	if ( ! aw_si_ready() ) {
		return;
	}
	aw_si_run_start( 'rollup' );
	try {
		aw_si_run_ok( 'rollup', aw_si_rollup() );
	} catch ( Throwable $e ) {
		aw_si_run_fail( 'rollup', $e->getMessage() );
	}
}
add_action( AW_SI_CRON_ROLLUP, 'aw_si_rollup_cron' );

add_action(
	'init',
	function () {
		if ( get_option( 'aw_si_off' ) ) {
			return; // Switched off by Delete data.
		}
		$tz = wp_timezone();
		if ( ! wp_next_scheduled( AW_SI_CRON_DAILY ) ) {
			wp_schedule_event( ( new DateTimeImmutable( 'tomorrow 07:10', $tz ) )->getTimestamp(), 'daily', AW_SI_CRON_DAILY );
		}
		if ( ! wp_next_scheduled( AW_SI_CRON_ROLLUP ) ) {
			wp_schedule_event( ( new DateTimeImmutable( 'today 23:30', $tz ) )->getTimestamp(), 'daily', AW_SI_CRON_ROLLUP );
		}
	}
);
