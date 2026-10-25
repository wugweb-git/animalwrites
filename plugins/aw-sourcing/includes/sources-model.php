<?php
/**
 * Sourcing restructure, phase 1 (owner, 25 Oct 2026): the Sources model as plain functions over the stored arrays, with
 * no WordPress calls, so tests/run.php checks them without WordPress (owner rule: no local WordPress or MySQL).
 *
 * - Every feed and code-read source belongs to a publisher host (aw_sources.sources[].host). A Google News search
 *   limited to one site (site:livelaw.in ...) belongs to that site (owner's decision 2); an open-web search to
 *   news.google.com.
 * - The relay switch lives on the publisher record (aw_publishers[host].relay). A host that is not a publisher but must
 *   be relayed (ccsea.gov.in) gets a record with kind=relay, as icon-only hosts have kind=directory-source.
 * - The relay allow list is built from the switches (aw_src_relay_hosts_from_pubs), once phase 1 is migrated.
 * - The keyword matcher (aw_match_term) is the workers' aw_workers/matcher.py; both repositories test the same cases
 *   (tests/fixtures/match_cases.json).
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Change log entries kept (owner, 25 Oct 2026: was 300). */
const AW_SOURCING_LOG_CAP = 1000;

/**
 * Relay hosts mapped at the phase 1 migration: the built-in substack.com becomes PFA's Substack only (owner's
 * decision 8, 25 Oct 2026: relay peopleforanimals.substack.com, not every Substack site).
 */
const AW_SRC_RELAY_MAP = array( 'substack.com' => 'peopleforanimals.substack.com' );

/**
 * Lowercase host of a URL without www., or ''.
 *
 * @param string $url URL.
 * @return string
 */
function aw_src_url_host( $url ) {
	$h = strtolower( (string) parse_url( (string) $url, PHP_URL_HOST ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- pure function, tested without WordPress
	return (string) preg_replace( '/^www\./', '', $h );
}

/**
 * The one site a Google News search is limited to (site:livelaw.in ...), or '' (open web, several sites, not a search).
 *
 * @param string $url Feed URL.
 * @return string
 */
function aw_src_search_site( $url ) {
	if ( false === strpos( (string) $url, 'news.google.com/rss/search' ) ) {
		return '';
	}
	parse_str( (string) parse_url( (string) $url, PHP_URL_QUERY ), $q ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- pure function
	preg_match_all( '/(?<![\w.-])site:([a-z0-9.-]+\.[a-z]{2,})/i', (string) ( $q['q'] ?? '' ), $m );
	$sites = array_values( array_unique( array_map( fn( $s ) => (string) preg_replace( '/^www\./', '', strtolower( $s ) ), $m[1] ) ) );
	return 1 === count( $sites ) ? $sites[0] : '';
}

/**
 * The publisher host a source belongs to: its stored host, else the one site its Google News search is limited to,
 * else its URL's host.
 *
 * @param array $s Source (aw_sources.sources entry).
 * @return string
 */
function aw_src_source_host( array $s ) {
	if ( ! empty( $s['host'] ) ) {
		return strtolower( (string) $s['host'] );
	}
	$site = aw_src_search_site( (string) ( $s['url'] ?? '' ) );
	return '' !== $site ? $site : aw_src_url_host( (string) ( $s['url'] ?? '' ) );
}

/**
 * "feed" for a feed the workers read as RSS (an address that can change here), "code" for a source read by code.
 *
 * @param array $s Source.
 * @return string
 */
function aw_src_read_by( array $s ) {
	return 'rss' === ( $s['kind'] ?? '' ) ? 'feed' : 'code';
}

/**
 * True when the host is one of the list's hosts or a subdomain of one.
 *
 * @param string   $host Host.
 * @param string[] $list Hosts.
 * @return bool
 */
function aw_src_host_in( $host, array $list ) {
	$host = strtolower( trim( (string) $host, '.' ) );
	if ( '' === $host ) {
		return false;
	}
	foreach ( $list as $h ) {
		$h = strtolower( trim( (string) $h, '.' ) );
		if ( '' !== $h && ( $host === $h || str_ends_with( $host, '.' . $h ) ) ) {
			return true;
		}
	}
	return false;
}

/**
 * The relay allow list from the publishers' switches (in the records' order).
 *
 * @param array $pubs aw_publishers.
 * @return string[]
 */
function aw_src_relay_hosts_from_pubs( array $pubs ) {
	$out = array();
	foreach ( $pubs as $h => $p ) {
		if ( is_array( $p ) && ! empty( $p['relay'] ) ) {
			$out[] = strtolower( (string) $h );
		}
	}
	return $out;
}

/**
 * Per host, the newest relay request (the log row) for that host or one of its subdomains, from the newest
 * row back.
 *
 * @param array  $log  aw_relay_log rows (oldest first).
 * @param string $host Host.
 * @return array|null
 */
function aw_src_relay_last( array $log, $host ) {
	$best = null;
	foreach ( $log as $r ) {
		if ( ! is_array( $r ) || ! aw_src_host_in( (string) ( $r['host'] ?? '' ), array( $host ) ) ) {
			continue;
		}
		if ( null === $best || (int) ( $r['at'] ?? 0 ) >= (int) ( $best['at'] ?? 0 ) ) {
			$best = $r;
		}
	}
	return $best;
}

/**
 * A change log entry added at the front, the newest $cap kept. `old` and `new` are stored only when given.
 *
 * @param array $log   Entries (newest first).
 * @param array $entry at, user, tab, what.
 * @param mixed $old   Value before (null: none).
 * @param mixed $new   Value after (null: none).
 * @param int   $cap   Entries kept.
 * @return array
 */
function aw_src_log_push( array $log, array $entry, $old = null, $new = null, $cap = AW_SOURCING_LOG_CAP ) {
	if ( null !== $old ) {
		$entry['old'] = $old;
	}
	if ( null !== $new ) {
		$entry['new'] = $new;
	}
	array_unshift( $log, $entry );
	return array_slice( $log, 0, max( 1, (int) $cap ) );
}

/**
 * A logged value in words: lists joined, true/false as on/off, records as JSON.
 *
 * @param mixed $v Value.
 * @return string
 */
function aw_src_log_value( $v ) {
	if ( null === $v ) {
		return '';
	}
	if ( is_bool( $v ) ) {
		return $v ? 'on' : 'off';
	}
	if ( is_array( $v ) ) {
		if ( ! $v ) {
			return '(none)';
		}
		if ( array_keys( $v ) === range( 0, count( $v ) - 1 ) && ! array_filter( $v, 'is_array' ) ) {
			return implode( ', ', array_map( 'strval', $v ) );
		}
		return (string) json_encode( $v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- pure function
	}
	$s = (string) $v;
	return '' === $s ? '(none)' : $s;
}

/**
 * The phase 1 migration, computed (nothing written): every source gets its publisher host; the relay hosts become
 * switches on publisher records (a host without a record gets one with kind=relay). Hosts are mapped first
 * (AW_SRC_RELAY_MAP).
 *
 * @param array    $pubs    aw_publishers.
 * @param array    $sources aw_sources.sources.
 * @param string[] $relay   The relay allow list before (stored relay_hosts or the built-in list).
 * @param string   $today   Y-m-d, for a new record's first-seen date.
 * @param array    $map     Host => host to switch on instead.
 * @return array{pubs: array, sources: array, flagged: string[], added: string[], hosts: array, relay_before: string[], relay_after: string[], mapped: array}
 */
function aw_src_phase1_plan( array $pubs, array $sources, array $relay, $today, array $map = AW_SRC_RELAY_MAP ) {
	$hosts = array();
	foreach ( $sources as $i => $s ) {
		if ( ! is_array( $s ) ) {
			continue;
		}
		$h = aw_src_source_host( $s );
		if ( '' !== $h && empty( $s['host'] ) ) {
			$sources[ $i ]['host'] = $h;
		}
		$hosts[ (string) ( $s['id'] ?? $i ) ] = $h;
	}
	$flagged = array();
	$added   = array();
	$mapped  = array();
	foreach ( $relay as $h ) {
		$h = strtolower( trim( (string) $h, '.' ) );
		if ( '' === $h ) {
			continue;
		}
		$t = $map[ $h ] ?? $h;
		if ( $t !== $h ) {
			$mapped[ $h ] = $t;
		}
		if ( isset( $pubs[ $t ] ) && is_array( $pubs[ $t ] ) ) {
			if ( empty( $pubs[ $t ]['relay'] ) ) {
				$pubs[ $t ]['relay'] = true;
				$flagged[]           = $t;
			}
			continue;
		}
		$name = '';
		foreach ( $sources as $s ) {
			if ( is_array( $s ) && aw_src_host_in( aw_src_source_host( $s ), array( $t ) ) ) {
				$name = (string) ( $s['name'] ?? '' );
				break;
			}
		}
		$pubs[ $t ] = array( 'status' => 'neutral', 'name' => $name, 'count' => 0, 'first' => (string) $today, 'last' => '', 'kind' => 'relay', 'relay' => true );
		$added[]    = $t;
	}
	return array(
		'pubs'         => $pubs,
		'sources'      => $sources,
		'flagged'      => $flagged,
		'added'        => $added,
		'hosts'        => $hosts,
		'relay_before' => array_values( array_map( 'strval', $relay ) ),
		'relay_after'  => aw_src_relay_hosts_from_pubs( $pubs ),
		'mapped'       => $mapped,
	);
}

/**
 * Undo the phase 1 migration on the current records (keeps everything else that changed since: counts, new
 * publishers, feeds added or edited): relay switches off, kind=relay records removed (unless the ingest gate has
 * since made one a publisher), the host key taken off every source.
 *
 * @param array $pubs    aw_publishers now.
 * @param array $sources aw_sources.sources now.
 * @return array{pubs: array, sources: array, unflagged: string[], removed: string[]}
 */
function aw_src_phase1_undo( array $pubs, array $sources ) {
	$unflagged = array();
	$removed   = array();
	foreach ( $pubs as $h => $p ) {
		if ( ! is_array( $p ) ) {
			continue;
		}
		if ( 'relay' === ( $p['kind'] ?? '' ) ) {
			unset( $pubs[ $h ] );
			$removed[] = (string) $h;
			continue;
		}
		if ( array_key_exists( 'relay', $p ) ) {
			unset( $pubs[ $h ]['relay'] );
			$unflagged[] = (string) $h;
		}
	}
	foreach ( $sources as $i => $s ) {
		if ( is_array( $s ) ) {
			unset( $sources[ $i ]['host'] );
		}
	}
	return array( 'pubs' => $pubs, 'sources' => $sources, 'unflagged' => $unflagged, 'removed' => $removed );
}

/**
 * Compare the migrated store with the backup. Ingest keeps writing publisher records (count, last, an empty name,
 * the icon), so those keys and hosts first seen since the backup are reported as "since the backup", never as
 * differences. Everything the migration changed on purpose is listed as planned.
 *
 * @param array    $bk_pubs      aw_publishers in the backup.
 * @param array    $bk_sources   aw_sources.sources in the backup.
 * @param array    $pubs         aw_publishers now.
 * @param array    $sources      aw_sources.sources now.
 * @param string[] $relay_before Relay allow list before.
 * @param string[] $relay_after  Relay allow list now.
 * @param array    $relay_log    aw_relay_log rows.
 * @param array    $map          Host mapping of the migration.
 * @return array{diffs: string[], planned: string[], since: string[]}
 */
function aw_src_phase1_compare( array $bk_pubs, array $bk_sources, array $pubs, array $sources, array $relay_before, array $relay_after, array $relay_log, array $map = AW_SRC_RELAY_MAP ) {
	$diffs   = array();
	$planned = array();
	$since   = array();
	$soft    = array( 'count', 'last', 'icon', 'first' );

	// Sources: the same list in the same order; only the host key added.
	$strip = function ( array $list ) {
		$out = array();
		foreach ( $list as $s ) {
			if ( is_array( $s ) ) {
				unset( $s['host'] );
			}
			$out[] = $s;
		}
		return $out;
	};
	if ( $strip( $sources ) !== $strip( $bk_sources ) ) {
		$ids_now = array_map( fn( $s ) => is_array( $s ) ? (string) ( $s['id'] ?? '' ) : '', $sources );
		$ids_bk  = array_map( fn( $s ) => is_array( $s ) ? (string) ( $s['id'] ?? '' ) : '', $bk_sources );
		$diffs[] = 'sources differ from the backup beyond the host key (backup ' . count( $bk_sources ) . ': ' . implode( ', ', $ids_bk ) . '; now ' . count( $sources ) . ': ' . implode( ', ', $ids_now ) . ')';
	}
	$with_host = 0;
	foreach ( $sources as $i => $s ) {
		if ( is_array( $s ) && ! empty( $s['host'] ) && empty( $bk_sources[ $i ]['host'] ) ) {
			++$with_host;
		}
	}
	if ( $with_host ) {
		$planned[] = 'host added to ' . $with_host . ' sources';
	}

	// Publishers.
	foreach ( $bk_pubs as $h => $old ) {
		if ( ! isset( $pubs[ $h ] ) ) {
			$diffs[] = 'publisher ' . $h . ' is missing';
			continue;
		}
		$now = (array) $pubs[ $h ];
		$old = (array) $old;
		if ( ! empty( $now['relay'] ) && empty( $old['relay'] ) ) {
			$planned[] = 'relay on for ' . $h;
		}
		foreach ( array_unique( array_merge( array_keys( $old ), array_keys( $now ) ) ) as $k ) {
			if ( 'relay' === $k || ( $old[ $k ] ?? null ) === ( $now[ $k ] ?? null ) ) {
				continue;
			}
			if ( in_array( $k, $soft, true ) || ( 'name' === $k && '' === (string) ( $old['name'] ?? '' ) ) ) {
				$since[] = $h . ' ' . $k . ' (ingest)';
				continue;
			}
			if ( 'kind' === $k && 'relay' === ( $old['kind'] ?? '' ) && ! isset( $now['kind'] ) ) {
				$since[] = $h . ' became a publisher (ingest)';
				continue;
			}
			$diffs[] = 'publisher ' . $h . ' ' . $k . ': ' . aw_src_log_value( $old[ $k ] ?? null ) . ' → ' . aw_src_log_value( $now[ $k ] ?? null );
		}
	}
	foreach ( $pubs as $h => $p ) {
		if ( isset( $bk_pubs[ $h ] ) ) {
			continue;
		}
		if ( 'relay' === ( $p['kind'] ?? '' ) ) {
			$planned[] = 'relay-only record added for ' . $h;
		} else {
			$since[] = $h . ' first seen since the backup';
		}
	}

	// Relay: every host allowed before is still allowed, except the planned mappings; every host relayed with success
	// in the log is still allowed.
	foreach ( $relay_before as $h ) {
		$h = strtolower( (string) $h );
		if ( isset( $map[ $h ] ) ) {
			$planned[] = 'relay ' . $h . ' narrowed to ' . $map[ $h ] . ( aw_src_host_in( $map[ $h ], $relay_after ) ? '' : ' (NOT on the list now)' );
			if ( ! aw_src_host_in( $map[ $h ], $relay_after ) ) {
				$diffs[] = 'relay host ' . $map[ $h ] . ' (for ' . $h . ') is not allowed';
			}
			continue;
		}
		if ( ! aw_src_host_in( $h, $relay_after ) ) {
			$diffs[] = 'relay host ' . $h . ' is no longer allowed';
		}
	}
	$seen = array();
	foreach ( $relay_log as $r ) {
		$host = strtolower( (string) ( $r['host'] ?? '' ) );
		if ( '' === $host || isset( $seen[ $host ] ) || ! is_int( $r['status'] ?? null ) || $r['status'] >= 400 || ! aw_src_host_in( $host, $relay_before ) ) {
			continue; // refused or failed requests, and hosts never allowed, are not a use to keep
		}
		$seen[ $host ] = true;
		if ( ! aw_src_host_in( $host, $relay_after ) ) {
			$diffs[] = 'relayed host ' . $host . ' (in the relay log) is no longer allowed';
		}
	}
	return array( 'diffs' => $diffs, 'planned' => $planned, 'since' => $since );
}

/**
 * The policy's sources_v2 block: each source as the workers' v1 list has it (already resolved: no on / filter
 * switches, keyword lists in place) plus its publisher host, relay switch, how it is read and its keyword check (the
 * check names roles and categories from phase 2; empty in phase 1, so the source's own keywords apply).
 *
 * @param array    $v1    The policy's config.sources (resolved).
 * @param string[] $relay Relay allow list.
 * @return array
 */
function aw_src_sources_v2( array $v1, array $relay ) {
	$out = array();
	foreach ( $v1 as $s ) {
		if ( ! is_array( $s ) ) {
			continue;
		}
		$h     = aw_src_source_host( $s );
		$out[] = array_merge(
			$s,
			array(
				'host'    => $h,
				'relay'   => aw_src_host_in( $h, $relay ),
				'read_by' => aw_src_read_by( $s ),
				'check'   => array( 'roles' => array(), 'categories' => array() ),
			)
		);
	}
	return $out;
}

/**
 * Does the text contain the keyword? The shared matcher (the workers' aw_workers/matcher.py): Latin words and phrases
 * match whole words with a plural (s/es) allowed, a trailing * matches the start of a word, other scripts (Hindi)
 * match as substrings; case and repeated spaces do not matter.
 *
 * @param string $hay  Text.
 * @param string $term Keyword.
 * @return bool
 */
function aw_match_term( $hay, $term ) {
	$t = trim( (string) preg_replace( '/\s+/u', ' ', mb_strtolower( (string) $term ) ) );
	if ( '' === $t ) {
		return false;
	}
	$prefix = str_ends_with( $t, '*' );
	if ( $prefix ) {
		$t = rtrim( rtrim( $t, '*' ) );
		if ( '' === $t ) {
			return false;
		}
	}
	$hay = mb_strtolower( (string) $hay );
	if ( preg_match( '/[^\x00-\x7F]/', $t ) ) {
		return false !== mb_strpos( $hay, $t ); // other scripts have no word boundaries
	}
	$body = implode( '\s+', array_map( fn( $p ) => preg_quote( $p, '/' ), explode( ' ', $t ) ) );
	return (bool) preg_match( '/(?<![a-z0-9])' . $body . ( $prefix ? '' : '(?:s|es)?(?![a-z0-9])' ) . '/u', $hay );
}
