<?php
/**
 * WP-CLI: wp aw-si status | dry-run | run | rollup | delete-data --yes
 *
 * `dry-run` writes nothing: it reads the seed, Rank Math, Sourcing and one day of Search Console (through Site Kit, as
 * the module's owner) and reports what phase 1 would store.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'aw-si',
		new class() {

			/**
			 * Run log per source and table sizes.
			 */
			public function status() {
				global $wpdb;
				foreach ( aw_si_sources() as $src => $s ) {
					$r = aw_si_runs()[ $src ] ?? array();
					WP_CLI::log( sprintf( '%-40s ok %s%s', $s['label'], ! empty( $r['ok'] ) ? wp_date( 'Y-m-d H:i', (int) $r['ok'] ) : 'never', ! empty( $r['error'] ) ? ' | error: ' . $r['error']['message'] : '' ) );
				}
				if ( aw_si_ready() ) {
					foreach ( array( 'keywords', 'kw_pages', 'obs', 'rising' ) as $t ) {
						WP_CLI::log( sprintf( '%-10s %s rows', $t, number_format( (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . aw_si_table( $t ) ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
					}
				} else {
					WP_CLI::log( 'Tables not created yet.' );
				}
			}

			/**
			 * Read-only rehearsal: what phase 1 would store today. Writes nothing.
			 *
			 * [--day=<Y-m-d>]
			 * : Search Console day to read. Default: 3 days ago (final figures).
			 *
			 * @subcommand dry-run
			 *
			 * @param array $args  Positional arguments.
			 * @param array $assoc Named arguments.
			 */
			public function dry_run( $args, $assoc ) {
				$day = (string) ( $assoc['day'] ?? ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( '-3 days' )->format( 'Y-m-d' ) );
				$rep = aw_si_dry_report( $day );
				if ( ! empty( $rep['error'] ) ) {
					WP_CLI::warning( 'Search Console: ' . $rep['error'] );
				}
				unset( $rep['error'] );
				foreach ( $rep as $section => $data ) {
					WP_CLI::log( '== ' . $section );
					WP_CLI::log( (string) wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
				}
				WP_CLI::success( 'Dry run done. Nothing was written.' );
			}

			/**
			 * The daily run now: tables, seed (once), Search Console, roles, priorities.
			 */
			public function run() {
				$out = aw_si_daily( true );
				WP_CLI::log( wp_json_encode( array_diff_key( $out, array( 'gsc' => 1 ) ) ) );
				WP_CLI::log( 'gsc: ' . wp_json_encode( array_intersect_key( (array) $out['gsc'], array_flip( array( 'rows', 'truncated', 'split', 'error' ) ) ) ) . ' days ' . implode( ', ', array_keys( (array) ( $out['gsc']['days'] ?? array() ) ) ) );
				$this->status();
			}

			/**
			 * One phase 2 source now: ga4 (last 3 days, or --day), psi (one key page, mobile and desktop, with --dry; the
			 * full weekly queue without it) or clarity (2 of its 10 daily calls).
			 *
			 * <source>
			 * : ga4, psi or clarity.
			 *
			 * [--day=<Y-m-d>]
			 * : GA4 only: one day.
			 *
			 * [--dry]
			 * : Fetch and report; write nothing.
			 *
			 * @param array $args  Positional arguments.
			 * @param array $assoc Named arguments.
			 */
			public function fetch( $args, $assoc ) {
				$dry = ! empty( $assoc['dry'] );
				$src = (string) ( $args[0] ?? '' );
				if ( 'ga4' === $src ) {
					$out = aw_si_ga4_run(
						array_filter(
							array(
								'dry'  => $dry,
								'days' => isset( $assoc['day'] ) ? array( (string) $assoc['day'] ) : null,
							)
						)
					);
				} elseif ( 'psi' === $src ) {
					$path = aw_si_psi_pages()[0] ?? '/';
					if ( $dry ) {
						$out = array(
							'pages'            => aw_si_psi_pages(),
							$path . ' mobile'  => aw_si_psi_call( $path, 'mobile' ),
							$path . ' desktop' => aw_si_psi_call( $path, 'desktop' ),
						);
					} else {
						aw_si_psi_start(); // The first calls now; WP-cron runs the rest every five minutes.
						$out = get_option( AW_SI_PSI_QUEUE );
					}
				} elseif ( 'clarity' === $src ) {
					$out = aw_si_clarity_run( array( 'dry' => $dry ) );
				} else {
					WP_CLI::error( 'Source must be ga4, psi or clarity.' );
				}
				foreach ( (array) $out as $k => $v ) {
					if ( is_wp_error( $v ) ) {
						$out[ $k ] = 'error: ' . $v->get_error_message();
					}
				}
				WP_CLI::log( (string) wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
				if ( $dry ) {
					WP_CLI::success( 'Dry run done. Nothing was written.' );
				}
			}

			/**
			 * The 90-day rollup now.
			 *
			 * [--dry]
			 * : Count only.
			 *
			 * @param array $args  Positional arguments.
			 * @param array $assoc Named arguments.
			 */
			public function rollup( $args, $assoc ) {
				WP_CLI::log( wp_json_encode( aw_si_rollup( ! empty( $assoc['dry'] ) ) ) );
			}

			/**
			 * Drop the Search Insights tables and options.
			 *
			 * [--yes]
			 * : Required: confirms the deletion.
			 *
			 * @subcommand delete-data
			 *
			 * @param array $args  Positional arguments.
			 * @param array $assoc Named arguments.
			 */
			public function delete_data( $args, $assoc ) {
				if ( empty( $assoc['yes'] ) ) {
					WP_CLI::error( 'Add --yes to delete the Search Insights data.' );
				}
				aw_si_drop_all();
				WP_CLI::success( 'Search Insights data deleted.' );
			}
		}
	);
}
