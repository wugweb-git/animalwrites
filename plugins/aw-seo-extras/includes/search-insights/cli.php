<?php
/**
 * WP-CLI: wp aw-si status | dry-run | run | fetch <source> [--dry] | links <seed|add> | rollup | delete-data --yes
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
					foreach ( array( 'keywords', 'kw_pages', 'obs', 'rising', 'links' ) as $t ) {
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
			 * One source now: ga4 (last 3 days, or --day), psi (one key page, mobile and desktop, with --dry; the
			 * full weekly queue without it), clarity (2 of its 10 daily calls) or bing (Bing Webmaster links and figures).
			 *
			 * <source>
			 * : ga4, psi, clarity or bing.
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
				} elseif ( 'bing' === $src ) {
					if ( ! aw_si_bing_on() ) {
						WP_CLI::error( 'Bing Webmaster is not set up: add its API key under Animal Writes → Keys.' );
					}
					$out = aw_si_bing_run( array( 'dry' => $dry ) );
				} else {
					WP_CLI::error( 'Source must be ga4, psi, clarity or bing.' );
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
			 * Links tab rows: `seed` adds the 25–26 Oct lists (once; --force runs it again, which only moves dates);
			 * `add` adds one link, e.g. from the monthly Search Console Links read.
			 *
			 * <what>
			 * : seed or add.
			 *
			 * [--from=<url>]
			 * : add: the linking page.
			 *
			 * [--domain=<domain>]
			 * : add: the linking site, when there is no page address (else taken from --from).
			 *
			 * [--to=<url>]
			 * : add: our page it links to (a full URL or a site path).
			 *
			 * [--anchor=<text>]
			 * : add: the anchor text.
			 *
			 * [--source=<text>]
			 * : add: where the link was found. Default: "Search Console Links" (the read date is --seen).
			 *
			 * [--type=<type>]
			 * : add: press, organisation, social, directory, copy or spam (spam is hidden). Default: spam for link-selling
			 * anchors, else unsorted.
			 *
			 * [--seen=<Y-m-d>]
			 * : add: the date seen. Default: today.
			 *
			 * [--links=<n>]
			 * : add: how many links. Default: 1.
			 *
			 * [--note=<text>]
			 * : add: a note.
			 *
			 * [--force]
			 * : seed: run again.
			 *
			 * ## EXAMPLES
			 *
			 *     wp aw-si links seed
			 *     wp aw-si links add --from=https://example.org/post/ --to=/campaigns/lab-animals-rules-2026/ --anchor="Animal Writes" --type=press
			 *
			 * @param array $args  Positional arguments.
			 * @param array $assoc Named arguments.
			 */
			public function links( $args, $assoc ) {
				aw_si_install();
				$what = (string) ( $args[0] ?? '' );
				if ( 'seed' === $what ) {
					$out = aw_si_links_seed( ! empty( $assoc['force'] ) );
					WP_CLI::log( $out ? (string) wp_json_encode( $out ) : 'Already seeded (add --force to run again).' );
					return;
				}
				if ( 'add' !== $what ) {
					WP_CLI::error( 'Say seed or add.' );
				}
				$to = (string) ( $assoc['to'] ?? '' );
				if ( '' !== $to && 0 === strpos( $to, '/' ) ) {
					$to = home_url( $to );
				}
				if ( '' === $to || ( empty( $assoc['from'] ) && empty( $assoc['domain'] ) ) ) {
					WP_CLI::error( 'Give --to and --from (or --domain).' );
				}
				$type = (string) ( $assoc['type'] ?? '' );
				if ( '' !== $type && ! isset( aw_si_link_types()[ $type ] ) ) {
					WP_CLI::error( 'Type must be one of: ' . implode( ', ', array_keys( aw_si_link_types() ) ) . '.' );
				}
				$anchor = (string) ( $assoc['anchor'] ?? '' );
				$seen   = (string) ( $assoc['seen'] ?? wp_date( 'Y-m-d' ) );
				if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $seen ) ) {
					WP_CLI::error( '--seen must be Y-m-d.' );
				}
				$n = aw_si_link_put(
					array(
						'from_url'   => (string) ( $assoc['from'] ?? '' ),
						'domain'     => (string) ( $assoc['domain'] ?? '' ),
						'linked_url' => $to,
						'anchor'     => $anchor,
						'links'      => (int) ( $assoc['links'] ?? 1 ),
						'first_seen' => $seen,
						'last_seen'  => $seen,
						'source'     => (string) ( $assoc['source'] ?? 'Search Console Links' ), // The read date is --seen (last_seen).
						'type'       => '' !== $type ? $type : aw_si_link_auto_type( $anchor ),
						'note'       => (string) ( $assoc['note'] ?? '' ),
					)
				);
				if ( $n < 0 ) {
					WP_CLI::error( 'Not saved (check --from, --domain and --to).' );
				}
				WP_CLI::success( 1 === $n ? 'Link added.' : 'Link was there already: dates updated.' );
			}

			/**
			 * The monthly snapshot to standard output: the CSV, or the rebuilt keyword map.
			 *
			 * <what>
			 * : csv or map.
			 *
			 * ## EXAMPLES
			 *
			 *     wp aw-si snapshot csv > docs/seo/search-insights-2026-11.csv
			 *     wp aw-si snapshot map > docs/seo/keyword-map.md
			 *
			 * @param array $args Positional arguments.
			 */
			public function snapshot( $args ) {
				$what = (string) ( $args[0] ?? '' );
				if ( ! in_array( $what, array( 'csv', 'map' ), true ) ) {
					WP_CLI::error( 'Say csv or map.' );
				}
				echo 'csv' === $what ? aw_si_snapshot_csv() : aw_si_keyword_map_md(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- command-line output.
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
