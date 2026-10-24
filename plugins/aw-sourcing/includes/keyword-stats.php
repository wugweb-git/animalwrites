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
	$feed = (string) $feed;
	if ( 0 !== strpos( $feed, 'gnews-kw-' ) ) {
		return '';
	}
	$s = aw_sourcing_searches();
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
	$include  = array_map( 'strval', (array) ( aw_relevance()['strong'] ?? array() ) );
	$rows     = array();
	$row      = function ( $feed, $term ) use ( &$rows, $include ) {
		if ( ! isset( $rows[ $feed ] ) ) {
			$fixed          = in_array( $term, AW_SOURCING_EXTRA_SEARCHES, true ) && ! in_array( $term, $include, true );
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

// For aw-seo-extras (Tools → Search Insights): null there means AW Sourcing is off.
add_filter( 'aw_sourcing_keyword_items', fn( $v, $days = 30 ) => aw_sourcing_keyword_stats( $days ), 10, 2 );
