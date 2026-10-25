<?php
/**
 * Sourcing restructure, phase 2 (owner, 25 Oct 2026): the keyword lists move into the keyword table, as plain
 * functions over arrays (no WordPress calls), so tests/run.php checks them without WordPress (owner rule: no local
 * WordPress or MySQL).
 *
 * - The migration plan (aw_kw_phase2_plan): every word of the Include, Animal, Context and Exclude lists, the press
 *   queries, the category words, the two lab words and the open-web Google News searches becomes a role on the record
 *   of its normal form (aw_si_norm). Roles add up; nothing is removed by a merge. Every original spelling is kept, in
 *   the record's other spellings and, with its list and place, in src_note, so the old lists are rebuilt exactly.
 *   Hindi and Hinglish records get no Sourcing or Press role (the standing rule; owner's decision 13).
 * - The mirrors (aw_kw_mirrors): the old lists rebuilt from the records, written back to the old options on every save
 *   until phase 4 (read-only copies for old readers and old workers).
 * - The workers' keywords block (aw_kw_payload): only records with a Sourcing or Press role, English only.
 * - The whole-word report (aw_kw_word_report): each category word's hits on the site's items with today's matching
 *   and with whole words, and whether a trailing * is added because whole words lose hits (owner's decision 6).
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The old lists and the role each gives: list key => [role bit, where it lives, label]. */
const AW_KW_LISTS = array(
	'strong'  => array( AW_KW_ROLE_INCLUDE, 'aw_relevance.strong', 'Include' ),
	'animal'  => array( AW_KW_ROLE_ANIMAL, 'aw_relevance.animal', 'Animal' ),
	'context' => array( AW_KW_ROLE_CONTEXT, 'aw_relevance.context', 'Context' ),
	'exclude' => array( AW_KW_ROLE_EXCLUDE, 'aw_relevance.exclude', 'Exclude' ),
	'press'   => array( AW_KW_ROLE_PRESS, 'aw_sources.press.queries', 'Press queries' ),
);

/** The category the lab check words belong to, and the lab words no category list had (owner's decision 5). */
const AW_KW_LAB_CATEGORY = 'animal-experimentation';

/** The fixed extra Google News search of before phase 2 (workers run.py EXTRA_SEARCHES, keyword-stats.php). */
const AW_KW_EXTRA_SEARCHES = array( 'stray dogs' );

/** Press settings dropped with Brave (owner's decision 7; kept in the backup). */
const AW_KW_PRESS_DROPPED = array( 'sites', 'monthly_requests', 'weekly_requests', 'backfill_requests' );

/**
 * Whether a rollback may run (Codex, PR #75): a phase rolls back only when no later phase is migrated or half-migrated
 * (a phase 2 migration record, complete or partial, means phase 2 must be rolled back first).
 *
 * @param int  $phase     Phase to roll back.
 * @param int  $current   aw_sourcing_phase now.
 * @param bool $p2_record A phase 2 migration record exists (aw_sourcing_phase2_record).
 * @return string Why not ('' when it may run).
 */
function aw_src_rollback_refusal( $phase, $current, $p2_record ) {
	if ( 1 === (int) $phase && ( (int) $current > 1 || $p2_record ) ) {
		return 'Phase 2 is migrated or half-migrated: roll it back first (wp aw-sourcing rollback --phase=2).';
	}
	if ( 2 === (int) $phase && (int) $current < 2 && ! $p2_record ) {
		return 'Phase 2 is not migrated (aw_sourcing_phase = ' . (int) $current . ').';
	}
	return '';
}

/**
 * The phase marker after a phase 2 rollback: the one before phase 2 (from the backup), never higher than the marker now
 * (a half-done migration never set it to 2, and must not raise it past a phase that was rolled back since).
 *
 * @param int  $backup  aw_sourcing_phase in the phase 2 backup.
 * @param int  $current aw_sourcing_phase now.
 * @param bool $partial The migration stopped before setting the phase.
 * @return int
 */
function aw_src_phase_after_p2_rollback( $backup, $current, $partial ) {
	return $partial ? min( (int) $backup, (int) $current ) : min( (int) $backup, 1 );
}

/**
 * A search's feed id, as the workers make it (gnews-kw-<slug>).
 *
 * @param string $term Search words.
 * @return string
 */
function aw_kw_feed_id( $term ) {
	return 'gnews-kw-' . trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $term ) ), '-' );
}

/**
 * Only printable ASCII (an English search; Hindi words are never searched).
 *
 * @param string $s Text.
 * @return bool
 */
function aw_kw_ascii( $s ) {
	return (bool) preg_match( '/^[\x20-\x7e]+$/', (string) $s );
}

/**
 * The open-web Google News searches among the sources (owner's decision 2: the ones limited to one site stay feeds):
 * source id => keyword label ("Google News – animal welfare India" → "animal welfare").
 *
 * @param array $sources aw_sources.sources.
 * @return array<string, string>
 */
function aw_kw_open_searches( array $sources ) {
	$out = array();
	foreach ( $sources as $s ) {
		if ( ! is_array( $s ) || 'rss' !== ( $s['kind'] ?? '' ) || false === strpos( (string) ( $s['url'] ?? '' ), 'news.google.com/rss/search' ) ) {
			continue;
		}
		if ( '' !== aw_src_search_site( (string) $s['url'] ) || ! empty( $s['keyword_id'] ) ) {
			continue;
		}
		$label = trim( (string) preg_replace( array( '/^\s*Google News\s*[–—-]\s*/u', '/\s+India\s*$/i' ), '', (string) ( $s['name'] ?? '' ) ) );
		if ( '' === $label ) {
			parse_str( (string) parse_url( (string) $s['url'], PHP_URL_QUERY ), $q ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- pure function
			$label = trim( (string) preg_replace( '/\bwhen:\S+|\bIndia\b|["()]/i', ' ', (string) ( $q['q'] ?? '' ) ) );
		}
		$label = trim( (string) preg_replace( '/\s+/', ' ', $label ) );
		if ( '' !== aw_si_norm( $label ) ) { // a search with no words of its own stays a feed
			$out[ (string) $s['id'] ] = $label;
		}
	}
	return $out;
}

/**
 * The label of a new record from the spellings that fold to it: the first one already in its normal form (the
 * singular, as matching allows a plural), else the first.
 *
 * @param string[] $spellings Spellings in list order.
 * @return string
 */
function aw_kw_new_label( array $spellings ) {
	foreach ( $spellings as $sp ) {
		if ( aw_si_norm( $sp ) === trim( (string) preg_replace( '/\s+/u', ' ', mb_strtolower( $sp ) ) ) ) {
			return $sp;
		}
	}
	return (string) reset( $spellings );
}

/**
 * Which of the sources' own keyword lists the migration turns into a keyword check (owner's decisions 4 and 5): a
 * source whose list is the seed list gets "Include role"; one whose list is the lab list (the words of the Google
 * News lab search) gets "keywords mapped to Animal Experimentation". Other lists (WWF, AWBI opportunities) stay.
 *
 * @param array    $sources aw_sources.sources.
 * @param string[] $seed    aw_sources.keywords.
 * @return array<string, array> source id => check {roles, categories}.
 */
function aw_kw_feed_checks( array $sources, array $seed ) {
	$lab = array();
	foreach ( $sources as $s ) {
		if ( is_array( $s ) && 'press' === ( $s['file_as'] ?? '' ) && ! empty( $s['keywords'] ) ) {
			$lab = array_values( (array) $s['keywords'] ); // the lab search's words
		}
	}
	$out = array();
	foreach ( $sources as $s ) {
		if ( ! is_array( $s ) || empty( $s['keywords'] ) || 'opportunity' === ( $s['type'] ?? '' ) ) {
			continue;
		}
		$k = array_values( (array) $s['keywords'] );
		if ( $seed && $k === array_values( $seed ) ) {
			$out[ (string) $s['id'] ] = array( 'roles' => array( 'include' ), 'categories' => array() );
		} elseif ( $lab && $k === $lab ) {
			$out[ (string) $s['id'] ] = array( 'roles' => array(), 'categories' => array( AW_KW_LAB_CATEGORY ) );
		}
	}
	return $out;
}

/**
 * The phase 2 migration, computed (nothing written).
 *
 * @param array $rel       aw_relevance (strong, animal, context, exclude).
 * @param array $cfg       aw_sources (keywords, sources, categories, press).
 * @param array $dismissed aw_sourcing_dismissed.
 * @param array $rows      Keyword records that exist: id, norm, label, lang, roles, variants (list), query, feed_id,
 *                         search_on, dismissed, src (array), categories, sections. Only records whose norm one of the
 *                         lists has are needed.
 * @return array
 */
function aw_kw_phase2_plan( array $rel, array $cfg, array $dismissed, array $rows ) {
	$by_norm = array();
	foreach ( $rows as $r ) {
		$by_norm[ (string) $r['norm'] ] = $r;
	}
	$recs  = array(); // norm => planned record
	$lists = array(); // list => counts
	$hindi = array();
	$touch = function ( $spelling ) use ( &$recs, $by_norm ) {
		$norm = aw_si_norm( (string) $spelling );
		if ( '' === $norm ) {
			return '';
		}
		if ( ! isset( $recs[ $norm ] ) ) {
			$old            = $by_norm[ $norm ] ?? null;
			$recs[ $norm ] = array(
				'id'         => $old ? (int) $old['id'] : 0,
				'norm'       => $norm,
				'label'      => $old ? (string) $old['label'] : '',
				'spellings'  => array(),
				'lang'       => $old ? (string) $old['lang'] : '',
				'roles_was'  => $old ? (int) $old['roles'] : 0,
				'roles'      => $old ? (int) $old['roles'] : 0,
				'variants'   => $old ? aw_kw_variants_list( $old['variants'] ?? array() ) : array(),
				'src'        => $old && is_array( $old['src'] ?? null ) ? (array) $old['src'] : array(),
				'query'      => $old ? (string) ( $old['query'] ?? '' ) : '',
				'feed_id'    => $old ? (string) ( $old['feed_id'] ?? '' ) : '',
				'search_on'  => $old ? (int) ( $old['search_on'] ?? 1 ) : 1,
				'dismissed'  => $old ? (int) ( $old['dismissed'] ?? 0 ) : 0,
				'categories' => $old ? array_values( (array) ( $old['categories'] ?? array() ) ) : array(),
				'sections'   => $old ? array_values( (array) ( $old['sections'] ?? array() ) ) : array(),
				'want'       => 0, // roles asked for (a Hindi record does not get the Sourcing ones)
			);
		}
		if ( ! in_array( (string) $spelling, $recs[ $norm ]['spellings'], true ) ) {
			$recs[ $norm ]['spellings'][] = (string) $spelling;
		}
		return $norm;
	};
	$put = function ( $norm, $list, $pos, $spelling, $role, $cat = '' ) use ( &$recs ) {
		$recs[ $norm ]['want'] |= $role;
		$recs[ $norm ]['src']['lists'][ $list ] = $recs[ $norm ]['src']['lists'][ $list ] ?? array();
		if ( ! in_array( array( $pos, $spelling ), $recs[ $norm ]['src']['lists'][ $list ], true ) ) {
			$recs[ $norm ]['src']['lists'][ $list ][] = array( $pos, $spelling );
		}
		if ( '' !== $cat && ! in_array( $cat, $recs[ $norm ]['categories'], true ) ) {
			$recs[ $norm ]['categories'][] = $cat;
		}
	};
	$count = function ( $list, array $words, $role, $cat = '' ) use ( &$lists, $touch, $put, $by_norm ) {
		$norms = array();
		$pairs = array();
		foreach ( array_values( $words ) as $i => $w ) {
			$w = trim( (string) $w );
			$n = $touch( $w );
			if ( '' === $n ) {
				continue;
			}
			$put( $n, $list, $i, $w, $role, $cat );
			if ( isset( $norms[ $n ] ) ) {
				$pairs[] = $norms[ $n ] . ' / ' . $w;
			} else {
				$norms[ $n ] = $w;
			}
		}
		$lists[ $list ] = array(
			'items'    => count( $words ),
			'distinct' => count( $norms ),
			'existing' => count( array_intersect_key( $norms, $by_norm ) ),
			'merged'   => $pairs,
		);
	};

	foreach ( AW_KW_LISTS as $key => $def ) {
		$words = 'press' === $key ? (array) ( $cfg['press']['queries'] ?? array() ) : (array) ( $rel[ $key ] ?? array() );
		$count( $key, $words, $def[0] );
	}
	foreach ( (array) ( $cfg['categories'] ?? array() ) as $slug => $words ) {
		$count( 'cat:' . $slug, (array) $words, AW_KW_ROLE_CATEGORY, (string) $slug );
	}
	// Lab check words no category list has (owner's decision 5): mapped to Animal Experimentation.
	$catnorms = array();
	foreach ( (array) ( $cfg['categories'][ AW_KW_LAB_CATEGORY ] ?? array() ) as $w ) {
		$catnorms[ aw_si_norm( (string) $w ) ] = true;
	}
	$labwords = array();
	foreach ( (array) ( $cfg['sources'] ?? array() ) as $s ) {
		if ( is_array( $s ) && 'press' === ( $s['file_as'] ?? '' ) ) {
			foreach ( (array) ( $s['keywords'] ?? array() ) as $w ) {
				if ( ! isset( $catnorms[ aw_si_norm( (string) $w ) ] ) ) {
					$labwords[] = (string) $w;
				}
			}
		}
	}
	$count( 'lab', $labwords, AW_KW_ROLE_CATEGORY, AW_KW_LAB_CATEGORY );

	// The open-web Google News searches (owner's decision 2) and the fixed extra search: the Search role, feed ids kept.
	$searches = array();
	foreach ( aw_kw_open_searches( (array) ( $cfg['sources'] ?? array() ) ) as $id => $label ) {
		$src = null;
		foreach ( (array) $cfg['sources'] as $s ) {
			if ( is_array( $s ) && (string) ( $s['id'] ?? '' ) === $id ) {
				$src = $s;
			}
		}
		$n = $touch( $label );
		$put( $n, 'search', count( $searches ), $label, AW_KW_ROLE_SEARCH );
		$recs[ $n ]['feed_id']   = $id;
		$recs[ $n ]['query']     = (string) $src['url'];
		$recs[ $n ]['search_on'] = array_key_exists( 'on', (array) $src ) && empty( $src['on'] ) ? 0 : 1; // a search the owner switched off stays off (Codex)
		$section               = 'press' === ( $src['file_as'] ?? '' ) ? 'aw_press' : ( 'legal' === ( $src['type'] ?? '' ) ? 'aw_legal' : 'aw_news' );
		if ( ! in_array( $section, $recs[ $n ]['sections'], true ) ) {
			$recs[ $n ]['sections'][] = $section;
		}
		$searches[ $id ] = $n;
	}
	foreach ( AW_KW_EXTRA_SEARCHES as $t ) {
		$n = $touch( $t );
		$put( $n, 'search', count( $searches ), $t, AW_KW_ROLE_SEARCH );
		$recs[ $n ]['feed_id'] = aw_kw_feed_id( $t );
		$recs[ $n ]['query']   = $t;
		if ( ! in_array( 'aw_news', $recs[ $n ]['sections'], true ) ) {
			$recs[ $n ]['sections'][] = 'aw_news';
		}
		$searches[ aw_kw_feed_id( $t ) ] = $n;
	}
	$lists['search'] = array(
		'items'    => count( $searches ),
		'distinct' => count( array_unique( $searches ) ),
		'existing' => count( array_intersect_key( array_flip( $searches ), $by_norm ) ),
		'merged'   => array(),
	);

	// Dismissed suggestions: a column on the record (a record with no role is created for one that has none).
	$dis = array();
	foreach ( $dismissed as $q ) {
		$n = $touch( (string) $q );
		if ( '' !== $n ) {
			$dis[] = $n;
		}
	}

	// Each record: label, language, roles, other spellings, the Include search's section.
	$conflicts = array();
	foreach ( $recs as $n => $r ) {
		if ( '' === $r['label'] ) {
			$r['label'] = aw_kw_new_label( $r['spellings'] );
		}
		if ( '' === $r['lang'] ) {
			$r['lang'] = aw_si_lang( $r['label'] );
		}
		$want = (int) $r['want'];
		if ( 'en' !== $r['lang'] && ( $want & AW_KW_WORKER_ROLES ) ) {
			foreach ( (array) ( $r['src']['lists'] ?? array() ) as $list => $places ) {
				foreach ( $places as $p ) {
					$hindi[] = array( $list, $p[1], $r['lang'] );
				}
			}
			$want &= ~AW_KW_WORKER_ROLES;
		}
		if ( 'en' !== $r['lang'] ) {
			$r['roles'] &= ~AW_KW_WORKER_ROLES; // e.g. Include set on a Hindi word by Search Insights' daily sync before phase 2
		}
		list( $roles, $why ) = aw_kw_roles_check( $r['roles'], $want, 0, $r['lang'] );
		if ( '' !== $why ) {
			$conflicts[] = $r['label'] . ': ' . $why;
		}
		$r['roles'] = $roles;
		foreach ( $r['spellings'] as $sp ) {
			if ( mb_strtolower( $sp ) !== mb_strtolower( $r['label'] ) ) {
				$r['variants'][] = $sp;
			}
		}
		$r['variants'] = aw_kw_variants_list( $r['variants'] );
		if ( ( $r['roles'] & AW_KW_ROLE_INCLUDE ) && ! in_array( 'aw_news', $r['sections'], true ) ) {
			$r['sections'][] = 'aw_news'; // a keyword search files items as News, as every keyword search does today
		}
		if ( in_array( $n, $dis, true ) ) {
			$r['dismissed'] = 1;
		}
		if ( ! empty( $r['src']['lists'] ) ) {
			ksort( $r['src']['lists'] );
		}
		$recs[ $n ] = $r;
	}
	$new = array_filter( $recs, fn( $r ) => 0 === $r['id'] );
	return array(
		'records'   => $recs,
		'lists'     => $lists,
		'hindi'     => $hindi,
		'conflicts' => $conflicts,
		'searches'  => $searches,
		'dismissed' => $dis,
		'new'       => count( $new ),
		'existing'  => count( $recs ) - count( $new ),
		'checks'    => aw_kw_feed_checks( (array) ( $cfg['sources'] ?? array() ), (array) ( $cfg['keywords'] ?? array() ) ),
		'cat_maps'  => array_sum( array_map( fn( $r ) => count( array_diff( $r['categories'], (array) ( $by_norm[ $r['norm'] ]['categories'] ?? array() ) ) ), $recs ) ),
	);
}

/**
 * The old lists rebuilt from the records (the mirrors): each list in its old order with its old spellings (from
 * src_note), then records given the role since (by label). A record keeps a list only while it has the role (and, for
 * a category, the mapping). Press: its Press spellings.
 *
 * @param array $rows Records (id, label, roles, src, categories), with a Sourcing or Press role or a src note.
 * @return array{strong: string[], animal: string[], context: string[], exclude: string[], press: string[], categories: array<string, string[]>}
 */
function aw_kw_mirrors( array $rows ) {
	$acc = array();
	$add = function ( $list, $pos, $id, $spelling ) use ( &$acc ) {
		$acc[ $list ][] = array( (float) $pos, (int) $id, (string) $spelling );
	};
	foreach ( $rows as $r ) {
		$roles = (int) $r['roles'];
		$src   = (array) ( $r['src']['lists'] ?? array() );
		foreach ( AW_KW_LISTS as $key => $def ) {
			if ( ! ( $roles & $def[0] ) ) {
				continue;
			}
			if ( ! empty( $src[ $key ] ) ) {
				foreach ( $src[ $key ] as $p ) {
					$add( $key, $p[0], $r['id'], $p[1] );
				}
			} else {
				$add( $key, 1e6, $r['id'], $r['label'] );
			}
		}
		if ( $roles & AW_KW_ROLE_CATEGORY ) {
			foreach ( (array) ( $r['categories'] ?? array() ) as $slug ) {
				$places = $src[ 'cat:' . $slug ] ?? ( AW_KW_LAB_CATEGORY === $slug && ! empty( $src['lab'] ) ? array( array( 1e5, $src['lab'][0][1] ) ) : array() );
				if ( $places ) {
					foreach ( $places as $p ) {
						$add( 'cat:' . $slug, $p[0], $r['id'], $p[1] );
					}
				} else {
					$add( 'cat:' . $slug, 1e6, $r['id'], $r['label'] );
				}
			}
		}
	}
	$out = array( 'strong' => array(), 'animal' => array(), 'context' => array(), 'exclude' => array(), 'press' => array(), 'categories' => array() );
	foreach ( $acc as $list => $items ) {
		usort( $items, fn( $a, $b ) => ( $a[0] <=> $b[0] ) ?: ( $a[1] <=> $b[1] ) );
		$words = array_values( array_unique( array_map( fn( $i ) => $i[2], $items ) ) );
		if ( 0 === strpos( $list, 'cat:' ) ) {
			$out['categories'][ substr( $list, 4 ) ] = $words;
		} else {
			$out[ $list ] = $words;
		}
	}
	return $out;
}

/**
 * The category lists in the old option's order: the slugs as they were, then new ones.
 *
 * @param array $mirror  aw_kw_mirrors()['categories'].
 * @param array $old     aw_sources.categories as it was.
 * @return array<string, string[]>
 */
function aw_kw_categories_in_order( array $mirror, array $old ) {
	$out = array();
	foreach ( array_keys( $old ) as $slug ) {
		if ( ! empty( $mirror[ $slug ] ) ) {
			$out[ $slug ] = $mirror[ $slug ];
		}
	}
	foreach ( $mirror as $slug => $words ) {
		if ( ! isset( $out[ $slug ] ) && $words ) {
			$out[ $slug ] = $words;
		}
	}
	return $out;
}

/**
 * The Google News searches of one record, in the workers' order: its own search (Search role: the query override, a
 * whole URL for a search that was a feed, with that feed's settings), then one per English Include spelling.
 *
 * @param array $r       Record.
 * @param array $sources aw_sources.sources by id (a former feed keeps its name, lookback, keyword list and filing there).
 * @return array[] Each {feed, q} or a source dict with feed and url.
 */
function aw_kw_searches( array $r, array $sources ) {
	if ( empty( $r['search_on'] ) || 'en' !== ( $r['lang'] ?? 'en' ) ) {
		return array();
	}
	$out = array();
	if ( (int) $r['roles'] & AW_KW_ROLE_SEARCH ) {
		$q    = '' !== (string) ( $r['query'] ?? '' ) ? (string) $r['query'] : (string) $r['label'];
		$feed = '' !== (string) ( $r['feed_id'] ?? '' ) ? (string) $r['feed_id'] : aw_kw_feed_id( $q );
		if ( preg_match( '#^https?://#', $q ) ) {
			$base = $sources[ $feed ] ?? array( 'name' => 'Google News – ' . $r['label'], 'kind' => 'rss', 'type' => 'news' );
			unset( $base['on'], $base['filter'], $base['keyword_id'], $base['host'] ); // its keyword check (check) goes with it
			$base['id'] = $feed;
			$out[]      = array_merge( $base, array( 'feed' => $feed, 'url' => $q ) );
		} elseif ( aw_kw_ascii( $q ) ) {
			$out[] = array( 'feed' => $feed, 'q' => $q );
		}
	}
	if ( (int) $r['roles'] & AW_KW_ROLE_INCLUDE ) {
		$spell = array();
		foreach ( (array) ( $r['src']['lists']['strong'] ?? array() ) as $p ) {
			$spell[] = (string) $p[1];
		}
		foreach ( $spell ? $spell : array( (string) $r['label'] ) as $sp ) {
			if ( aw_kw_ascii( $sp ) ) {
				$out[] = array( 'feed' => aw_kw_feed_id( $sp ), 'q' => $sp );
			}
		}
	}
	$seen = array();
	return array_values(
		array_filter(
			$out,
			function ( $s ) use ( &$seen ) {
				if ( isset( $seen[ $s['feed'] ] ) ) {
					return false;
				}
				$seen[ $s['feed'] ] = true;
				return true;
			}
		)
	);
}

/**
 * The workers' keywords block (sourcing policy config.keywords_v2): records with a Sourcing or Press role and lang en
 * only (about 236, never the ~4,700 Search Insights records), Include ones in the old Include order.
 *
 * @param array $rows    Records.
 * @param array $sources aw_sources.sources.
 * @return array[]
 */
function aw_kw_payload( array $rows, array $sources ) {
	$by = array();
	foreach ( $sources as $s ) {
		if ( is_array( $s ) && isset( $s['id'] ) ) {
			$by[ (string) $s['id'] ] = $s;
		}
	}
	$out = array();
	foreach ( $rows as $r ) {
		if ( 'en' !== ( $r['lang'] ?? '' ) || ! ( (int) $r['roles'] & AW_KW_WORKER_ROLES ) ) {
			continue;
		}
		$roles = (int) $r['roles'] & AW_KW_WORKER_ROLES;
		$e     = array(
			'id'         => (int) $r['id'],
			'label'      => (string) $r['label'],
			'variants'   => aw_kw_variants_list( $r['variants'] ?? array() ),
			'roles'      => aw_kw_roles_to_slugs( $roles ),
			'lang'       => 'en',
			'searches'   => aw_kw_searches( $r, $by ),
			'categories' => array_values( (array) ( $r['categories'] ?? array() ) ),
			'sections'   => array_values( (array) ( $r['sections'] ?? array() ) ),
		);
		if ( $roles & AW_KW_ROLE_PRESS ) {
			$q = array_map( fn( $p ) => (string) $p[1], (array) ( $r['src']['lists']['press'] ?? array() ) );
			$q = $q ? $q : array( (string) $r['label'] );
			foreach ( $e['variants'] as $v ) {
				if ( false === strpos( $v, '*' ) ) {
					$q[] = $v;
				}
			}
			$e['queries'] = aw_kw_variants_list( $q );
		}
		$first = 1e6;
		foreach ( array( 'strong', 'search', 'press' ) as $l ) {
			foreach ( (array) ( $r['src']['lists'][ $l ] ?? array() ) as $p ) {
				$first = min( $first, ( 'strong' === $l ? 0 : ( 'search' === $l ? 1e3 : 2e3 ) ) + (float) $p[0] );
			}
		}
		$out[] = array( $first, (int) $r['id'], $e );
	}
	usort( $out, fn( $a, $b ) => ( $a[0] <=> $b[0] ) ?: ( $a[1] <=> $b[1] ) );
	return array_map( fn( $x ) => $x[2], $out );
}

/**
 * Today's category matching in the workers (sources.py categories_for, legacy mode): whole words for ASCII words of up
 * to 4 letters, a plain substring otherwise.
 *
 * @param string $hay  Lowercased text.
 * @param string $word Category word.
 * @return bool
 */
function aw_kw_legacy_match( $hay, $word ) {
	$w = mb_strtolower( (string) $word );
	if ( '' === $w ) {
		return false;
	}
	if ( aw_kw_ascii( $w ) && strlen( $w ) <= 4 ) {
		return (bool) preg_match( '/\b' . preg_quote( $w, '/' ) . '\b/', $hay );
	}
	return false !== mb_strpos( $hay, $w );
}

/**
 * The whole-word report (owner's decision 6): for each word, its hits on the items with today's matching and with whole
 * words, and, when whole words lose hits, the hits with a trailing * (which the migration then adds as a spelling, when
 * it wins hits back).
 *
 * @param string[] $words Words (category words and lab words).
 * @param string[] $texts Items' title and summary.
 * @return array[] word => [before, after, star_after, star] (star: true when * is added).
 */
function aw_kw_word_report( array $words, array $texts ) {
	$hays = array_map( fn( $t ) => mb_strtolower( (string) $t ), $texts );
	$out  = array();
	foreach ( array_values( array_unique( array_map( 'strval', $words ) ) ) as $w ) {
		$before = 0;
		$after  = 0;
		$star   = 0;
		$latin  = aw_kw_ascii( $w ) && false === strpos( $w, '*' );
		foreach ( $hays as $h ) {
			$before += aw_kw_legacy_match( $h, $w ) ? 1 : 0;
			$after  += aw_match_term( $h, $w ) ? 1 : 0;
			$star   += $latin && aw_match_term( $h, $w . '*' ) ? 1 : 0;
		}
		$out[ $w ] = array( $before, $after, $latin ? $star : $after, $latin && $after < $before && $star > $after ); // * only where it wins hits back
	}
	return $out;
}

/**
 * Add the * spellings of the report to the planned records (category words whose whole-word hits drop).
 *
 * @param array $plan   aw_kw_phase2_plan().
 * @param array $report aw_kw_word_report().
 * @return array The plan, with `starred` (word => norm).
 */
function aw_kw_plan_add_stars( array $plan, array $report ) {
	$plan['starred']     = array();
	$plan['not_starred'] = array();
	foreach ( $report as $w => $r ) {
		if ( empty( $r[3] ) ) {
			continue;
		}
		$n = aw_si_norm( (string) $w );
		if ( ! isset( $plan['records'][ $n ] ) || ! ( $plan['records'][ $n ]['roles'] & AW_KW_ROLE_CATEGORY ) ) {
			continue;
		}
		if ( $plan['records'][ $n ]['roles'] & ( AW_KW_ROLE_INCLUDE | AW_KW_ROLE_EXCLUDE ) ) { // a spelling counts for every role: never widen Include or Exclude
			$plan['not_starred'][ (string) $w ] = $n;
			continue;
		}
		$plan['records'][ $n ]['variants'] = aw_kw_variants_list( array_merge( $plan['records'][ $n ]['variants'], array( mb_strtolower( (string) $w ) . '*' ) ) );
		$plan['starred'][ (string) $w ]    = $n;
	}
	return $plan;
}

/**
 * Compare the mirrors rebuilt from the records with the old lists (verify): the same words in the same order, apart
 * from the planned changes (Hindi words that lost their Sourcing role, new lab category words).
 *
 * @param array $old    The old lists: strong, animal, context, exclude, press (queries), categories.
 * @param array $mirror aw_kw_mirrors().
 * @param array $hindi  Hindi spellings the migration left without a Sourcing role: [list, spelling, lang].
 * @return array{diffs: string[], planned: string[]}
 */
function aw_kw_compare_mirrors( array $old, array $mirror, array $hindi ) {
	$diffs   = array();
	$planned = array();
	$gone    = array();
	foreach ( $hindi as $h ) {
		$gone[ $h[0] ][] = $h[1];
	}
	$check = function ( $name, $key, array $was, array $now ) use ( &$diffs, &$planned, $gone ) {
		$expect = array_values( array_diff( $was, (array) ( $gone[ $key ] ?? array() ) ) );
		if ( count( $expect ) !== count( $was ) ) {
			$planned[] = $name . ': ' . implode( ', ', array_diff( $was, $expect ) ) . ' (not English: no Sourcing role)';
		}
		if ( $now === $expect ) {
			return;
		}
		$extra = array_values( array_diff( $now, $expect ) );
		$lost  = array_values( array_diff( $expect, $now ) );
		if ( 'cat:' . AW_KW_LAB_CATEGORY === $key && ! $lost && array_values( array_intersect( $now, $expect ) ) === $expect ) {
			$planned[] = $name . ': lab words added ' . implode( ', ', $extra ) . ' (decision 5)';
			return;
		}
		$diffs[] = $name . ( $lost ? ': missing ' . implode( ', ', $lost ) : '' ) . ( $extra ? ': extra ' . implode( ', ', $extra ) : '' ) . ( ! $lost && ! $extra ? ': order differs' : '' );
	};
	foreach ( array( 'strong', 'animal', 'context', 'exclude', 'press' ) as $k ) {
		$check( AW_KW_LISTS[ $k ][1], $k, array_values( (array) ( $old[ $k ] ?? array() ) ), array_values( (array) ( $mirror[ $k ] ?? array() ) ) );
	}
	$slugs = array_unique( array_merge( array_keys( (array) ( $old['categories'] ?? array() ) ), array_keys( (array) ( $mirror['categories'] ?? array() ) ) ) );
	foreach ( $slugs as $slug ) {
		$check( 'aw_sources.categories.' . $slug, 'cat:' . $slug, array_values( (array) ( $old['categories'][ $slug ] ?? array() ) ), array_values( (array) ( $mirror['categories'][ $slug ] ?? array() ) ) );
	}
	return array( 'diffs' => $diffs, 'planned' => $planned );
}

/**
 * The (feed id, query) list the workers build from the v1 lists (run.py keyword_searches, before phase 2): the
 * Google News feeds, then one search per English Include word and the fixed extra search, skipping a search that is
 * already a feed (by id or by its query). Used to check the keywords block gives the same searches.
 *
 * @param array    $sources The policy's v1 sources (on only).
 * @param string[] $include The Include list.
 * @return array<string, string> feed id => normalised query.
 */
function aw_kw_v1_searches( array $sources, array $include ) {
	$out = array();
	$qs  = array();
	foreach ( $sources as $s ) {
		if ( is_array( $s ) && false !== strpos( (string) ( $s['url'] ?? '' ), 'news.google.com/rss/search' ) ) {
			$q                        = aw_kw_norm_q( (string) $s['url'] );
			$out[ (string) $s['id'] ] = $q;
			$qs[ $q ]                 = true;
		}
	}
	foreach ( array_merge( $include, AW_KW_EXTRA_SEARCHES ) as $kw ) {
		$kw = trim( (string) $kw );
		if ( '' === $kw || ! aw_kw_ascii( $kw ) ) {
			continue;
		}
		$id = aw_kw_feed_id( $kw );
		$q  = aw_kw_norm_q( 'https://news.google.com/rss/search?q=' . rawurlencode( '"' . $kw . '" India' ) );
		if ( isset( $out[ $id ] ) || isset( $qs[ $q ] ) ) {
			continue;
		}
		$out[ $id ] = $q;
		$qs[ $q ]   = true;
	}
	return $out;
}

/**
 * The same list from the keywords block (as the workers build it from phase 2): the feeds that are still feeds, the
 * searches that were feeds, then the keywords' own searches.
 *
 * @param array $sources The policy's v1 sources (on only), less the ones that became keywords.
 * @param array $payload aw_kw_payload().
 * @return array<string, string> feed id => normalised query.
 */
function aw_kw_v2_searches( array $sources, array $payload ) {
	$out = array();
	$qs  = array();
	$all = array();
	foreach ( $payload as $k ) {
		foreach ( $k['searches'] as $s ) {
			$all[] = $s;
		}
	}
	usort( $all, fn( $a, $b ) => (int) isset( $b['url'] ) <=> (int) isset( $a['url'] ) ); // the former feeds first (stable)
	foreach ( $sources as $s ) {
		if ( is_array( $s ) && false !== strpos( (string) ( $s['url'] ?? '' ), 'news.google.com/rss/search' ) ) {
			$q                        = aw_kw_norm_q( (string) $s['url'] );
			$out[ (string) $s['id'] ] = $q;
			$qs[ $q ]                 = true;
		}
	}
	foreach ( $all as $s ) {
		$q = isset( $s['url'] ) ? aw_kw_norm_q( (string) $s['url'] ) : aw_kw_norm_q( 'https://news.google.com/rss/search?q=' . rawurlencode( '"' . $s['q'] . '" India' ) );
		if ( isset( $out[ $s['feed'] ] ) || isset( $qs[ $q ] ) ) {
			continue;
		}
		$out[ (string) $s['feed'] ] = $q;
		$qs[ $q ]                   = true;
	}
	return $out;
}

/**
 * A Google News search's query, lower case and single spaced, with the time window the workers add (when:2d) unless
 * it has one (run.py with_window / norm_q).
 *
 * @param string $url Search URL.
 * @return string
 */
function aw_kw_norm_q( $url ) {
	parse_str( (string) parse_url( (string) $url, PHP_URL_QUERY ), $q ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- pure function
	$s = (string) ( $q['q'] ?? '' );
	if ( false === strpos( $s, 'when:' ) ) {
		$s .= ' when:2d';
	}
	return trim( (string) preg_replace( '/\s+/', ' ', strtolower( $s ) ) );
}

/**
 * The sources after the phase 2 migration: each one the migration gives a keyword check (owner's decisions 4 and 5)
 * stores it as `check`; each open-web search that became a keyword stores that record's id as `keyword_id` (the entry
 * stays as the old workers' copy until phase 4; the workers from phase 2 read it from the keyword instead).
 *
 * @param array $sources aw_sources.sources.
 * @param array $checks  Source id => check (aw_kw_feed_checks()).
 * @param array $kw_ids  Source id => keyword record id.
 * @return array
 */
function aw_kw_apply_to_sources( array $sources, array $checks, array $kw_ids ) {
	foreach ( $sources as $i => $s ) {
		if ( ! is_array( $s ) ) {
			continue;
		}
		$id = (string) ( $s['id'] ?? '' );
		if ( isset( $checks[ $id ] ) ) {
			$sources[ $i ]['check'] = $checks[ $id ];
		}
		if ( ! empty( $kw_ids[ $id ] ) ) {
			$sources[ $i ]['keyword_id'] = (int) $kw_ids[ $id ];
		}
	}
	return $sources;
}

/**
 * The sourcing policy's phase 2 blocks, beside the unchanged v1 keys: sources_v2 (the sources that are still feeds,
 * each with its keyword check), keywords_v2 (aw_kw_payload()), match "word" (whole-word matching in the workers) and
 * the schema versions.
 *
 * @param array    $sources aw_sources.sources (as stored: on, filter, check, keyword_id).
 * @param array    $rows    Keyword records with a Sourcing or Press role.
 * @param string[] $relay   Relay allow list.
 * @return array{schema: array, match: string, sources_v2: array, keywords_v2: array}
 */
function aw_kw_policy_blocks( array $sources, array $rows, array $relay ) {
	$feeds = array();
	foreach ( $sources as $s ) {
		if ( ! is_array( $s ) || empty( $s['on'] ) || ! empty( $s['keyword_id'] ) ) {
			continue;
		}
		unset( $s['on'], $s['filter'], $s['keyword_id'] );
		$feeds[] = $s;
	}
	return array(
		'schema'      => array( 'sources' => 2, 'keywords' => 2 ),
		'match'       => 'word',
		'sources_v2'  => aw_src_sources_v2( $feeds, $relay ),
		'keywords_v2' => aw_kw_payload( $rows, $sources ),
	);
}
