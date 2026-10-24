<?php
/**
 * The read-only rehearsal behind `wp aw-si dry-run`: builds the keyword table in memory from the seed, Rank Math, AW
 * Sourcing and Search Console (through Site Kit, as the module's owner), applies the same rule as the daily calculation
 * (aw_si_decide), and reports the rows each table would get and what each tab would show. Nothing is written: no
 * table, option or Site Kit setting.
 *
 * Search Console calls: 4 for one day (total, query, query + page, page: the daily row counts) and 2 for the last
 * 28 days (query, query + page: the figures the tabs would show once the backfill has run).
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build the report.
 *
 * @param string $day Day for the per-day row counts (Y-m-d).
 * @return array Report sections: seed, rank_math, sourcing, gsc, tables, tabs, error.
 */
function aw_si_dry_report( string $day ): array {
	global $wpdb;
	$rec   = array(); // norm => record.
	$pages = 0;       // kw_pages rows.
	$obs   = array(
		'trends' => 0,
		'live'   => 0,
	);
	$add   = function ( string $label, int $role ) use ( &$rec ) {
		$norm = aw_si_norm( $label );
		if ( '' === $norm ) {
			return '';
		}
		if ( ! isset( $rec[ $norm ] ) ) {
			$rec[ $norm ] = array(
				'label'    => $label,
				'roles'    => aw_si_is_brand( $label ) ? AW_SI_ROLE_BRAND : 0,
				'lang'     => aw_si_lang( $label ),
				'trends'   => null,
				'live'     => null,
				'owners'   => array(),
				'has_page' => false,
				'entity'   => 0,
				'impr'     => 0,
				'clicks'   => 0,
				'pos'      => null,
				'top'      => array( '', 0 ),
			);
		}
		$rec[ $norm ]['roles'] |= $role;
		return $norm;
	};

	// Seed (keyword map, gaps, 21 Oct rankings).
	$seed = aw_si_seed_data();
	foreach ( (array) ( $seed['map'] ?? array() ) as $row ) {
		$n                     = $add( $row['keyword'], AW_SI_ROLE_TRACKED );
		$rec[ $n ]['has_page'] = true;
		$rec[ $n ]['entity']   = '{organisation name}' === $row['keyword'] ? 1 : $rec[ $n ]['entity'];
		list( , , $path )      = aw_si_resolve_page( (array) $row['page'], (string) $row['page_type'] );
		if ( 'primary' === $row['kind'] && 0 === strpos( $path, '/' ) && false === strpos( $path, '{' ) ) {
			$rec[ $n ]['owners'][ $path ] = true;
		}
		++$pages;
		if ( null !== $row['trends'] ) {
			$rec[ $n ]['trends'] = (int) $row['trends'];
			++$obs['trends'];
		}
		if ( null !== $row['rank'] ) {
			$rec[ $n ]['live'] = is_int( $row['rank'] ) ? (float) $row['rank'] : $rec[ $n ]['live'];
			++$obs['live'];
		}
	}
	foreach ( (array) ( $seed['gaps'] ?? array() ) as $g ) {
		$add( $g['keyword'], AW_SI_ROLE_TRACKED );
	}
	foreach ( (array) ( $seed['rankings'] ?? array() ) as $r ) {
		$n = $add( $r['keyword'], AW_SI_ROLE_TRACKED );
		if ( null !== $r['position'] ) {
			$rec[ $n ]['live'] = (float) $r['position'];
		}
		$obs['live'] += 1 + ( '' !== (string) $r['top3'] ? 1 : 0 ) + ( '' !== (string) $r['paa'] ? 1 : 0 );
		$pages       += '' !== (string) $r['url'] ? 1 : 0;
	}
	$out = array(
		'seed' => array(
			'map_rows'      => count( (array) ( $seed['map'] ?? array() ) ),
			'gap_phrases'   => count( (array) ( $seed['gaps'] ?? array() ) ),
			'ranked'        => count( (array) ( $seed['rankings'] ?? array() ) ),
			'records_after' => count( $rec ),
		),
	);

	// Rank Math focus keyphrases (items without listings, and terms).
	$types = defined( 'AW_SEO_ITEM_TYPES' ) ? array_values( array_diff( AW_SEO_ITEM_TYPES, array( 'aw_listing' ) ) ) : array( 'post', 'page' );
	$in    = implode( ',', array_fill( 0, count( $types ), '%s' ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- one %s per type.
	$posts = (array) $wpdb->get_results( $wpdb->prepare( "SELECT p.ID, m.meta_value FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'rank_math_focus_keyword' WHERE p.post_status = 'publish' AND p.post_type IN ({$in}) AND m.meta_value <> ''", $types ) );
	$terms = (array) $wpdb->get_results( "SELECT t.term_id AS ID, m.meta_value FROM {$wpdb->terms} t JOIN {$wpdb->termmeta} m ON m.term_id = t.term_id AND m.meta_key = 'rank_math_focus_keyword' WHERE m.meta_value <> ''" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	_prime_post_caches( array_map( 'intval', wp_list_pluck( $posts, 'ID' ) ), false, false );
	_prime_term_caches( array_map( 'intval', wp_list_pluck( $terms, 'ID' ) ), false );
	$links = 0;
	foreach ( array(
		'post' => $posts,
		'term' => $terms,
	) as $type => $list ) {
		foreach ( $list as $r ) {
			list( $primary, $secondary ) = aw_si_focus_parts( (string) $r->meta_value );
			foreach ( array_merge( array( $primary ), $secondary ) as $i => $phrase ) {
				$n = '' !== $phrase ? $add( $phrase, AW_SI_ROLE_FOCUS ) : '';
				if ( '' === $n ) {
					continue;
				}
				$rec[ $n ]['has_page'] = true;
				if ( 0 === $i ) {
					$path = aw_si_object_path( $type, (int) $r->ID );
					if ( '' !== $path ) {
						$rec[ $n ]['owners'][ $path ] = true;
					}
				}
				++$links;
			}
		}
	}
	$pages           += $links;
	$out['rank_math'] = array(
		'items_with_keyphrase' => count( $posts ),
		'terms_with_keyphrase' => count( $terms ),
		'page_links'           => $links,
	);

	// AW Sourcing (read only).
	$sourcing = function_exists( 'aw_relevance' ) ? (array) ( aw_relevance()['strong'] ?? array() ) : null;
	foreach ( (array) $sourcing as $s ) {
		$add( (string) $s, AW_SI_ROLE_SOURCING );
	}
	$out['sourcing'] = null === $sourcing ? 'AW Sourcing not active: the Sourcing role stays empty' : count( $sourcing ) . ' Google News searches';

	// Search Console through Site Kit: one day for row counts, 28 days for what the tabs would show.
	$end = ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( '-1 day' );
	$s28 = $end->modify( '-27 days' )->format( 'Y-m-d' );
	$e28 = $end->format( 'Y-m-d' );
	$gsc = aw_si_with_module(
		'search-console',
		function ( $module ) use ( $day, $s28, $e28 ) {
			$one = aw_si_gsc_fetch_day( $module, $day );
			if ( is_wp_error( $one ) ) {
				return $one;
			}
			$q  = aw_sd_query( $module, array( 'query' ), $s28, $e28 );
			$qp = is_wp_error( $q ) ? $q : aw_sd_query( $module, array( 'query', 'page' ), $s28, $e28 );
			return is_wp_error( $qp ) ? $qp : array( $one, $q, $qp );
		}
	);
	if ( is_wp_error( $gsc ) ) {
		$out['error'] = $gsc->get_error_message();
		$gsc          = array(
			array(
				'total'     => array(),
				'q'         => array(),
				'qp'        => array(),
				'p'         => array(),
				'truncated' => array(),
			),
			array(),
			array(),
		);
	}
	list( $one, $q28, $qp28 ) = $gsc;
	foreach ( $q28 as $r ) {
		$n                    = $add( (string) ( $r[0][0] ?? '' ), AW_SI_ROLE_SEARCHED );
		$old                  = $rec[ $n ]['impr'];
		$rec[ $n ]['impr']   += $r[2];
		$rec[ $n ]['clicks'] += $r[1];
		$rec[ $n ]['pos']     = $rec[ $n ]['impr'] ? round( ( (float) $rec[ $n ]['pos'] * $old + $r[3] * $r[2] ) / $rec[ $n ]['impr'], 1 ) : null;
	}
	foreach ( $qp28 as $r ) {
		$n = aw_si_norm( (string) ( $r[0][0] ?? '' ) );
		if ( isset( $rec[ $n ] ) && $r[2] > $rec[ $n ]['top'][1] ) {
			$rec[ $n ]['top'] = array( aw_si_path( (string) ( $r[0][1] ?? '' ) ), $r[2] );
		}
	}
	$per_day    = count( $one['total'] ) + count( $one['q'] ) + count( $one['qp'] ) + count( $one['p'] );
	$day_impr   = (int) array_sum( array_column( $one['total'], 2 ) );
	$named      = (int) array_sum( array_column( $one['q'], 2 ) );
	$out['gsc'] = array(
		'day'              => $day,
		'rows'             => array(
			'total' => count( $one['total'] ),
			'q'     => count( $one['q'] ),
			'qp'    => count( $one['qp'] ),
			'p'     => count( $one['p'] ),
		),
		'truncated'        => $one['truncated'],
		'site_impressions' => $day_impr,
		'named_share'      => $day_impr ? round( 100 * $named / $day_impr, 1 ) : 0,
		'queries_28d'      => count( $q28 ),
		'query_pages_28d'  => count( $qp28 ),
		'impressions_28d'  => (int) array_sum( array_column( $q28, 2 ) ),
	);

	// Listings: entities among the records.
	$names = $wpdb->get_col( "SELECT m.meta_value FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'rank_math_focus_keyword' WHERE p.post_type = 'aw_listing' AND p.post_status = 'publish' AND m.meta_value <> ''" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	foreach ( (array) $names as $name ) {
		$n = aw_si_norm( aw_si_focus_parts( (string) $name )[0] );
		if ( isset( $rec[ $n ] ) ) {
			$rec[ $n ]['entity'] = 1;
		}
	}

	// Decide every record with the daily calculation's rule.
	$views = array_fill_keys( array( 'all', 'P1', 'P2', 'P3', 'gap', 'conflict', 'quick', 'notranked', 'entity' ), 0 );
	$roles = array_fill_keys( array_keys( aw_si_roles() ), 0 );
	$langs = array();
	$quick = array();
	$brand = array();
	$acts  = array();
	foreach ( $rec as $r ) {
		$d = aw_si_decide( $r['trends'], $r['impr'], $r['pos'], $r['live'], array_keys( $r['owners'] ), $r['has_page'], $r['top'] );
		++$views['all'];
		++$views[ $d['priority'] ];
		$views['gap']       += 'gap' === $d['status'] ? 1 : 0;
		$views['conflict']  += $d['conflict'] ? 1 : 0;
		$views['notranked'] += ( $r['roles'] & AW_SI_ROLE_TRACKED ) && 0 === $r['impr'] ? 1 : 0;
		$views['entity']    += $r['entity'];
		foreach ( array_keys( $roles ) as $bit ) {
			$roles[ $bit ] += ( $r['roles'] & $bit ) ? 1 : 0;
		}
		$langs[ $r['lang'] ] = ( $langs[ $r['lang'] ] ?? 0 ) + 1;
		if ( $d['action'] ) {
			$acts[ $d['action'] ] = ( $acts[ $d['action'] ] ?? 0 ) + 1;
		}
		if ( aw_si_quick_win( $r['pos'], $r['impr'] ) ) {
			++$views['quick'];
			$quick[] = array( $r['label'], $r['pos'], $r['impr'], $d['owners'] ? $d['owners'][0] : $d['shown'], $d['action'] );
		}
		if ( $r['roles'] & AW_SI_ROLE_BRAND && $r['impr'] ) {
			$brand[] = array( $r['label'], $r['pos'], $r['impr'], $r['clicks'] );
		}
	}
	usort( $quick, fn( $a, $b ) => $b[2] <=> $a[2] );
	usort( $brand, fn( $a, $b ) => $b[2] <=> $a[2] );
	$role_names = array();
	foreach ( aw_si_roles() as $bit => $name ) {
		$role_names[ $name ] = $roles[ $bit ];
	}

	$out['tables'] = array(
		'wp_aw_si_keywords' => count( $rec ),
		'wp_aw_si_kw_pages' => $pages,
		'wp_aw_si_obs'      => array(
			'gsc per day'       => $per_day,
			'gsc after 90 days' => $per_day * 90,
			'trends (seed)'     => $obs['trends'],
			'live check (seed)' => $obs['live'],
		),
		'wp_aw_si_rising'   => 0,
	);
	$seed_targets  = count( (array) ( $seed['map'] ?? array() ) );
	$out['tabs']   = array(
		'Overview' => array(
			'Sources'    => 'Search Console, Keyword roles and Retention, each with its last run and state',
			'Priorities' => array(
				'P1'                   => $views['P1'],
				'P2 (gaps)'            => $views['P2'],
				'Conflicts'            => $views['conflict'],
				'Quick wins'           => $views['quick'],
				'Tracked, not ranking' => $views['notranked'],
				'All keywords'         => $views['all'],
			),
			'Quick wins' => array_slice( $quick, 0, 10 ),
			'Movers'     => 'empty until 56 days are stored (the backfill reaches them in about 4 runs)',
			'Brand'      => array_slice( $brand, 0, 10 ),
		),
		'Keywords' => array(
			'views'     => $views,
			'roles'     => $role_names,
			'languages' => $langs,
			'actions'   => $acts,
		),
		'Searches' => array(
			'targets' => $seed_targets . ' keyword-map rows ("Our target?")',
			'gaps'    => count( array_unique( array_column( (array) ( $seed['gaps'] ?? array() ), 'gap' ) ) ) . ' demand-gap groups from the map, plus every P2 keyword',
		),
		'Settings' => 'schedule, seed date, Run now, Delete data',
	);
	return $out;
}
