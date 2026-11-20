<?php
/**
 * Which search brought each item in, and the per-keyword figures (owner, 24 Oct 2026).
 *
 * - Every English Include word is a Google News search in the workers (feed id gnews-kw-<slug>), plus the fixed
 *   searches in AW_SOURCING_EXTRA_SEARCHES. Since 24 Oct 2026 an item keeps the feed that brought it in (aw_feed) and
 *   when it arrived (aw_ingested_at); older items are counted by an Include word in their title or summary and shown
 *   as estimated.
 * - aw_sourcing_keyword_stats(): per search, items fetched (every status: held, merged and backfilled too) and
 *   published, News and Library apart. Offered to aw-seo-extras through the filter aw_sourcing_keyword_items, which
 *   shows them in Tools → Search Insights with Search Console figures.
 * - aw-seo-extras is optional: without it nothing here is shown and sourcing works as before.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Searches the workers always make besides the Include words (aw_workers/run.py EXTRA_SEARCHES; keep both in step). */
const AW_SOURCING_EXTRA_SEARCHES = array( 'stray dogs' );

/** A search's feed id, as the workers make it. */
function aw_sourcing_feed_id( $term ) {
	return 'gnews-kw-' . trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $term ) ), '-' );
}

/** The Google News searches the workers make per run: the English Include words and the fixed ones, once each. */
function aw_sourcing_searches() {
	$out = array();
	if ( function_exists( 'aw_sourcing_kw_on' ) && aw_sourcing_kw_on() ) { // phase 2: every keyword's searches (feed ids kept)
		$by = array_column( array_filter( (array) aw_sources_config()['sources'], 'is_array' ), null, 'id' );
		foreach ( aw_sourcing_kw_worker_rows() as $r ) {
			foreach ( aw_kw_searches( $r, $by ) as $x ) {
				$out[ $x['feed'] ] = $out[ $x['feed'] ] ?? (string) ( $x['q'] ?? $r['label'] );
			}
		}
		return $out;
	}
	foreach ( array_merge( (array) ( aw_relevance()['strong'] ?? array() ), AW_SOURCING_EXTRA_SEARCHES ) as $t ) {
		$t = trim( (string) $t );
		if ( '' !== $t && preg_match( '/^[\x20-\x7e]+$/', $t ) ) {
			$out[ aw_sourcing_feed_id( $t ) ] = $out[ aw_sourcing_feed_id( $t ) ] ?? $t;
		}
	}
	return $out; // feed id => term
}

/** The keyword behind a feed id ('' when the feed is not a keyword search). A word since removed is named from the id. */
function aw_sourcing_feed_keyword( $feed ) {
	static $s = null; // once per request (the stats call this for every item)
	$feed     = (string) $feed;
	$s        = null === $s ? aw_sourcing_searches() : $s;
	if ( isset( $s[ $feed ] ) ) {
		return $s[ $feed ];
	}
	if ( 0 !== strpos( $feed, 'gnews-kw-' ) ) {
		return '';
	}
	return $s[ $feed ] ?? str_replace( '-', ' ', substr( $feed, 9 ) );
}

/**
 * Per search: items fetched and published in the last $days days, News and Library apart.
 *
 * @param int $days Days back.
 * @return array{since:int, rows:array<string, array{term:string, listed:bool, fixed:bool, news:array{fetched:int[], published:int[]}, legal:array{fetched:int[], published:int[]}, estimated:int}>}
 */
function aw_sourcing_keyword_stats( $days = 30 ) {
	$days = max( 1, (int) $days );
	$key  = 'aw_sourcing_kw_stats_' . $days;
	$hit  = get_transient( $key );
	if ( is_array( $hit ) ) {
		return $hit;
	}
	global $wpdb;
	$since    = time() - $days * DAY_IN_SECONDS;
	$searches = aw_sourcing_searches();
	$include  = array_map( 'strval', (array) ( function_exists( 'aw_sourcing_kw_on' ) && aw_sourcing_kw_on() ? aw_sourcing_kw_lists()['strong'] : ( aw_relevance()['strong'] ?? array() ) ) ); // the table from phase 2 (the old list is archived in phase 4)
	$rows     = array();
	$row      = function ( $feed, $term ) use ( &$rows, $include ) {
		if ( ! isset( $rows[ $feed ] ) ) {
			$fixed          = ! ( function_exists( 'aw_sourcing_kw_on' ) && aw_sourcing_kw_on() ) && in_array( $term, AW_SOURCING_EXTRA_SEARCHES, true ) && ! in_array( $term, $include, true );
			$rows[ $feed ] = array(
				'term'      => $term,
				'listed'    => in_array( mb_strtolower( $term ), array_map( 'mb_strtolower', $include ), true ),
				'fixed'     => $fixed,
				'news'      => array( 'fetched' => array(), 'published' => array() ),
				'legal'     => array( 'fetched' => array(), 'published' => array() ),
				'estimated' => 0,
			);
		}
		return $feed;
	};
	foreach ( $searches as $feed => $term ) {
		$row( $feed, $term );
	}
	// Items from the workers (they carry a source URL). Arrival: aw_ingested_at; before 24 Oct 2026 post_date, which is
	// the arrival for items still waiting and the source's date (a day or two earlier) for published ones.
	$items = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"SELECT p.ID, p.post_type, p.post_status, p.post_title, p.post_excerpt, f.meta_value feed, i.meta_value ingested
			FROM {$wpdb->posts} p
			JOIN {$wpdb->postmeta} u ON u.post_id = p.ID AND u.meta_key = 'aw_source_url'
			LEFT JOIN {$wpdb->postmeta} f ON f.post_id = p.ID AND f.meta_key = 'aw_feed'
			LEFT JOIN {$wpdb->postmeta} i ON i.post_id = p.ID AND i.meta_key = 'aw_ingested_at'
			WHERE p.post_type IN ('aw_news', 'aw_legal') AND p.post_status NOT IN ('auto-draft', 'inherit')
			AND ( CAST(i.meta_value AS UNSIGNED) >= %d OR ( i.meta_value IS NULL AND p.post_date >= %s ) )", // post_date: pending items have no GMT date
			$since,
			wp_date( 'Y-m-d H:i:s', $since )
		)
	);
	foreach ( (array) $items as $it ) {
		$side = 'aw_legal' === $it->post_type ? 'legal' : 'news';
		$pub  = 'publish' === $it->post_status;
		$hits = array();
		if ( null !== $it->ingested ) { // since 24 Oct 2026: exactly the search that brought it in, if any
			$kw = aw_sourcing_feed_keyword( (string) $it->feed );
			if ( '' !== $kw ) {
				$hits[] = $row( (string) $it->feed, $kw );
			}
			$est = false;
		} else { // older: every search word in its title or summary
			$hay = mb_strtolower( $it->post_title . ' ' . wp_strip_all_tags( (string) $it->post_excerpt ) );
			foreach ( $searches as $feed => $term ) {
				if ( aw_has_term( $hay, $term ) ) {
					$hits[] = $feed;
				}
			}
			$est = true;
		}
		foreach ( $hits as $feed ) {
			$rows[ $feed ][ $side ]['fetched'][] = (int) $it->ID;
			if ( $pub ) {
				$rows[ $feed ][ $side ]['published'][] = (int) $it->ID;
			}
			$rows[ $feed ]['estimated'] += $est ? 1 : 0;
		}
	}
	uasort( $rows, fn( $a, $b ) => ( count( $b['news']['fetched'] ) + count( $b['legal']['fetched'] ) ) <=> ( count( $a['news']['fetched'] ) + count( $a['legal']['fetched'] ) ) ?: strcmp( $a['term'], $b['term'] ) );
	$out = array( 'since' => $since, 'rows' => $rows );
	set_transient( $key, $out, HOUR_IN_SECONDS );
	return $out;
}

// For aw-seo-extras (Tools → Search Insights): null there means SourceWeb is off.
add_filter( 'aw_sourcing_keyword_items', fn( $v, $days = 30 ) => aw_sourcing_keyword_stats( $days ), 10, 2 );

/**
 * The keyword results report (owner, 17 Nov 2026): one row per keyword RECORD (not per feed), from the LIVE keyword
 * table once phase 2 is migrated — its real roles, whether its Google News search is on, and the keyword's own Search
 * Console impressions — with the items each brought in over $days days. Deduped by the record (so "animal welfare" and
 * "AWBI"/"awbi" are one row each), with no invented roles. Before phase 2 it falls back to one row per Google News
 * search from aw_sourcing_keyword_stats(), with no roles.
 *
 * @param int $days Days back for the item counts.
 * @return array{records: bool, rows: array<int, array{label:string, roles:int, lang:string, is_search:bool, search_on:bool, dismissed:bool, estimated:bool, news_f:int, legal_f:int, news_p:int, legal_p:int, kw_impr:?int, pub_ids:int[]}>}
 */
function aw_sourcing_kw_report( $days = 30 ) {
	$stats = (array) ( aw_sourcing_keyword_stats( $days )['rows'] ?? array() );
	$feed  = array(); // feed id => split counts and the published item ids
	foreach ( $stats as $id => $st ) {
		$feed[ $id ] = array(
			'news_f'  => (array) $st['news']['fetched'],
			'legal_f' => (array) $st['legal']['fetched'],
			'news_p'  => (array) $st['news']['published'],
			'legal_p' => (array) $st['legal']['published'],
			'est'     => (int) $st['estimated'] > 0,
		);
	}
	$rows = array();
	if ( function_exists( 'aw_sourcing_kw_on' ) && aw_sourcing_kw_on() && function_exists( 'aw_sourcing_kw_records' ) ) {
		$en_roles = defined( 'AW_KW_ROLE_INCLUDE' ) ? ( AW_KW_ROLE_INCLUDE | AW_KW_ROLE_SEARCH ) : 0;
		foreach ( aw_sourcing_kw_records() as $r ) {
			$roles = (int) $r['roles'];
			$lang  = (string) ( $r['lang'] ?? 'en' );
			$nf    = array();
			$lf    = array();
			$np    = array();
			$lp    = array();
			$est   = false;
			foreach ( (array) ( $r['searches'] ?? array() ) as $x ) {
				$f = $feed[ (string) ( $x['feed'] ?? '' ) ] ?? null;
				if ( ! $f ) {
					continue;
				}
				$nf  = array_merge( $nf, $f['news_f'] );
				$lf  = array_merge( $lf, $f['legal_f'] );
				$np  = array_merge( $np, $f['news_p'] );
				$lp  = array_merge( $lp, $f['legal_p'] );
				$est = $est || $f['est'];
			}
			$rows[] = array(
				'label'     => (string) $r['label'],
				'roles'     => $roles,
				'lang'      => $lang,
				'is_search' => 'en' === $lang && (bool) ( $roles & $en_roles ),
				'search_on' => 'en' === $lang && (bool) ( $roles & $en_roles ) && ! empty( $r['search_on'] ),
				'dismissed' => ! empty( $r['dismissed'] ),
				'estimated' => $est,
				'news_f'    => count( array_unique( array_map( 'intval', $nf ) ) ),
				'legal_f'   => count( array_unique( array_map( 'intval', $lf ) ) ),
				'news_p'    => count( array_unique( array_map( 'intval', $np ) ) ),
				'legal_p'   => count( array_unique( array_map( 'intval', $lp ) ) ),
				'kw_impr'   => isset( $r['impr28'] ) ? (int) $r['impr28'] : null,
				'pub_ids'   => array_values( array_unique( array_map( 'intval', array_merge( $np, $lp ) ) ) ),
			);
		}
		return array( 'records' => true, 'rows' => $rows );
	}
	// Before phase 2: one row per Google News search, no roles (the keyword table is not the lists' home yet).
	foreach ( $stats as $st ) {
		$rows[] = array(
			'label'     => (string) $st['term'],
			'roles'     => 0,
			'lang'      => 'en',
			'is_search' => true,
			'search_on' => true,
			'dismissed' => false,
			'estimated' => (int) $st['estimated'] > 0,
			'news_f'    => count( $st['news']['fetched'] ),
			'legal_f'   => count( $st['legal']['fetched'] ),
			'news_p'    => count( $st['news']['published'] ),
			'legal_p'   => count( $st['legal']['published'] ),
			'kw_impr'   => null,
			'pub_ids'   => array_values( array_unique( array_map( 'intval', array_merge( (array) $st['news']['published'], (array) $st['legal']['published'] ) ) ) ),
		);
	}
	return array( 'records' => false, 'rows' => $rows );
}
