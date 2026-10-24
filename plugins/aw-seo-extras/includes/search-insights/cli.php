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
				global $wpdb;
				$seed = aw_si_seed_data();
				$norm = array();
				foreach ( array_merge( (array) ( $seed['map'] ?? array() ), (array) ( $seed['gaps'] ?? array() ), (array) ( $seed['rankings'] ?? array() ) ) as $r ) {
					$norm[ aw_si_norm( $r['keyword'] ) ] = true;
				}
				WP_CLI::log( sprintf( 'Seed: %d map keywords, %d gap phrases, %d ranked queries → %d keyword records after normalising.', count( $seed['map'] ?? array() ), count( $seed['gaps'] ?? array() ), count( $seed['rankings'] ?? array() ), count( $norm ) ) );
				$prio = array_count_values( array_filter( array_column( array_filter( (array) ( $seed['map'] ?? array() ), fn( $r ) => 'primary' === $r['kind'] ), 'priority' ) ) );
				ksort( $prio );
				WP_CLI::log( 'Seeded priorities (21 Oct): ' . wp_json_encode( $prio ) );

				$types  = defined( 'AW_SEO_ITEM_TYPES' ) ? array_values( array_diff( AW_SEO_ITEM_TYPES, array( 'aw_listing' ) ) ) : array( 'post', 'page' );
				$in     = implode( ',', array_fill( 0, count( $types ), '%s' ) );
				$focus  = $wpdb->get_col( $wpdb->prepare( "SELECT m.meta_value FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'rank_math_focus_keyword' WHERE p.post_status = 'publish' AND p.post_type IN ({$in}) AND m.meta_value <> ''", $types ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- one %s per type.
				$terms  = $wpdb->get_col( "SELECT meta_value FROM {$wpdb->termmeta} WHERE meta_key = 'rank_math_focus_keyword' AND meta_value <> ''" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$fk     = array();
				$owners = array();
				foreach ( array_merge( (array) $focus, (array) $terms ) as $f ) {
					list( $p, $sec ) = aw_si_focus_parts( (string) $f );
					if ( '' !== $p ) {
						$n            = aw_si_norm( $p );
						$fk[ $n ]     = true;
						$owners[ $n ] = ( $owners[ $n ] ?? 0 ) + 1;
					}
					foreach ( $sec as $s ) {
						$fk[ aw_si_norm( $s ) ] = true;
					}
				}
				$dupes = count( array_filter( $owners, fn( $c ) => $c > 1 ) );
				WP_CLI::log( sprintf( 'Rank Math: %d items and %d terms with a focus keyphrase → %d distinct phrases; %d primaries used by more than one page (conflicts).', count( (array) $focus ), count( (array) $terms ), count( $fk ), $dupes ) );
				$listings = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'rank_math_focus_keyword' WHERE p.post_type = 'aw_listing' AND p.post_status = 'publish' AND m.meta_value <> ''" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				WP_CLI::log( sprintf( 'Listings with a name keyphrase: %d (entities: shown as one aggregate, not one record each).', $listings ) );
				$sourcing = function_exists( 'aw_relevance' ) ? (array) ( aw_relevance()['strong'] ?? array() ) : null;
				WP_CLI::log( null === $sourcing ? 'AW Sourcing: not active (the Sourcing role and tab stay empty).' : sprintf( 'AW Sourcing: %d Google News searches (Include list).', count( $sourcing ) ) );
				$langs = array_count_values( array_map( 'aw_si_lang', array_merge( array_keys( $fk ), (array) $sourcing ) ) );
				WP_CLI::log( 'Languages: ' . wp_json_encode( $langs ) );

				$day = (string) ( $assoc['day'] ?? ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( '-3 days' )->format( 'Y-m-d' ) );
				$rep = aw_si_gsc_run(
					array(
						'days' => array( $day ),
						'dry'  => true,
					)
				);
				if ( ! empty( $rep['error'] ) ) {
					WP_CLI::warning( 'Search Console: ' . $rep['error'] );
				} else {
					$d = $rep['days'][ $day ] ?? array();
					WP_CLI::log( sprintf( 'Search Console %s (3 calls, as the module owner): %d query rows, %d query+page rows, site impressions %d, named-query impressions %d (%s%%).', $day, $d['q'] ?? 0, $d['qp'] ?? 0, $d['impr'] ?? 0, $d['named'] ?? 0, ! empty( $d['impr'] ) ? number_format( 100 * $d['named'] / $d['impr'], 1 ) : '0' ) );
					WP_CLI::log( 'Truncated (5,000 rows): ' . ( $rep['truncated'] ? wp_json_encode( $rep['truncated'] ) : 'none' ) );
					WP_CLI::log( sprintf( 'Rows a day in obs: about %d; 90 days: about %s.', $rep['rows'], number_format( $rep['rows'] * 90 ) ) );
					foreach ( (array) ( $rep['sample'][ $day ] ?? array() ) as $r ) {
						WP_CLI::log( sprintf( '  e.g. "%s": %d impressions, position %.1f', $r[0][1] ?? '', $r[2], $r[3] ) );
					}
				}
				WP_CLI::success( 'Dry run done. Nothing was written.' );
			}

			/**
			 * The daily run now: tables, seed (once), Search Console, roles, priorities.
			 */
			public function run() {
				$out = aw_si_daily();
				WP_CLI::log( wp_json_encode( array_diff_key( $out, array( 'gsc' => 1 ) ) ) );
				WP_CLI::log( 'gsc: ' . wp_json_encode( array_intersect_key( (array) $out['gsc'], array_flip( array( 'rows', 'truncated', 'error' ) ) ) ) . ' days ' . implode( ', ', array_keys( (array) ( $out['gsc']['days'] ?? array() ) ) ) );
				$this->status();
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
