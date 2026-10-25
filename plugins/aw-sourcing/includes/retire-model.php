<?php
/**
 * Sourcing restructure phase 4 (Retire; owner, 25 Oct 2026): the site stops using the old lists that phases 2 and 3
 * kept as read-only copies, and the sourcing policy stops sending the old (v1) keys. Owner, 25 Oct 2026: "make it, but
 * don't delete the old version": nothing old is deleted or changed. The migration copies the old lists into one archive
 * option (aw_sourcing_v1_archive, autoload off, dated, with checksums) and sets the phase to 4; the original options
 * stay exactly as they are, and every v1 code path stays, switched by the phase, so a rollback (the phase back to 3)
 * brings everything back as it was.
 *
 * From phase 4:
 * - option aw_relevance: only its two switches (enabled, gnews_allow_only) are read; its lists strong / animal /
 *   context / exclude are no longer rebuilt from the keyword table or sent (the table's roles hold them since phase 2).
 * - option aw_sources: categories (the Category word role), keywords (the old seed list, read by nothing since phase 2)
 *   and press.queries (the Press role) are no longer rebuilt or sent.
 * - option aw_sourcing_dismissed: not read (the dismissed column on the keyword records since phase 2).
 * - the policy: relevance holds only `enabled`; config loses keywords, sources, categories, sections and press.queries
 *   (sources_v2, the keyword table and sections_v2 carry them).
 * - the old Press queries, Categories and Sections tabs are hidden (their code stays; a rollback shows them again).
 *
 * The migration refuses while a word of an old list has no keyword record with that role (apart from the Hindi and
 * Hinglish words the standing rule keeps without a Sourcing role) or a dismissed search no dismissed record, so nothing
 * only the old lists hold stops being used unnoticed. Pure functions (no database), tested in tests/retire-tests.php;
 * the WP-CLI steps are in sourcing-migrate-retire.php.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The aw_relevance lists no longer used from phase 4 (its switches stay in use). */
const AW_RETIRE_REL_LISTS = array( 'strong', 'animal', 'context', 'exclude' );

/** The aw_sources keys no longer used from phase 4 ('press.queries' is the queries inside press). */
const AW_RETIRE_SOURCES_KEYS = array( 'categories', 'keywords', 'press.queries' );

/** The policy's config keys not sent from phase 4 (the v1 shape; sources_v2, the keyword table and sections_v2 replace them). */
const AW_RETIRE_V1_CONFIG = array( 'keywords', 'sources', 'categories', 'sections' );

/** The role of each old relevance list. */
const AW_RETIRE_LIST_ROLE = array(
	'strong'  => AW_KW_ROLE_INCLUDE,
	'animal'  => AW_KW_ROLE_ANIMAL,
	'context' => AW_KW_ROLE_CONTEXT,
	'exclude' => AW_KW_ROLE_EXCLUDE,
);

/**
 * Every spelling a record answers to, normalised: its label, its other spellings and the spellings the old lists gave it.
 *
 * @param array $r A keyword record (label, variants, src).
 * @return array<string, true>
 */
function aw_retire_norms( array $r ) {
	$out = array();
	$all = aw_kw_spellings( $r );
	foreach ( (array) ( $r['src']['lists'] ?? array() ) as $places ) {
		foreach ( (array) $places as $p ) {
			$all[] = (string) ( $p[1] ?? '' );
		}
	}
	foreach ( $all as $s ) {
		$n = aw_si_norm( rtrim( (string) $s, '*' ) );
		if ( '' !== $n ) {
			$out[ $n ] = true;
		}
	}
	return $out;
}

/**
 * The old lists checked against the keyword records: what phase 4 archives and stops using, and whether anything only
 * the old lists hold would stop being used.
 *
 * @param array $rel       aw_relevance.
 * @param array $cfg       aw_sources.
 * @param array $dismissed aw_sourcing_dismissed.
 * @param array $rows      Keyword records (id, label, variants, roles, lang, categories, dismissed, src).
 * @return array{archive: array, counts: array, conflicts: string[], planned: string[]}
 */
function aw_retire_plan( array $rel, array $cfg, array $dismissed, array $rows ) {
	$by_role = array(); // role bit => norm => true
	$by_cat  = array(); // slug => norm => true
	$any     = array(); // norm => true (every record)
	$dismiss = array(); // norm => true (records marked dismissed)
	foreach ( $rows as $r ) {
		$norms = aw_retire_norms( $r );
		$any  += $norms;
		if ( ! empty( $r['dismissed'] ) ) {
			$dismiss += $norms;
		}
		if ( 'en' !== (string) ( $r['lang'] ?? 'en' ) ) {
			continue; // the standing rule: no Sourcing or Press role on Hindi or Hinglish records
		}
		foreach ( array( AW_KW_ROLE_INCLUDE, AW_KW_ROLE_ANIMAL, AW_KW_ROLE_CONTEXT, AW_KW_ROLE_EXCLUDE, AW_KW_ROLE_PRESS, AW_KW_ROLE_CATEGORY ) as $bit ) {
			if ( (int) $r['roles'] & $bit ) {
				$by_role[ $bit ] = ( $by_role[ $bit ] ?? array() ) + $norms;
			}
		}
		if ( (int) $r['roles'] & AW_KW_ROLE_CATEGORY ) {
			foreach ( (array) ( $r['categories'] ?? array() ) as $slug ) {
				$by_cat[ (string) $slug ] = ( $by_cat[ (string) $slug ] ?? array() ) + $norms;
			}
		}
	}
	$conflicts = array();
	$planned   = array();
	$check     = function ( $where, array $words, array $have ) use ( &$conflicts, &$planned ) {
		$hindi = array();
		$miss  = array();
		foreach ( $words as $w ) {
			$w = trim( (string) $w );
			$n = aw_si_norm( rtrim( $w, '*' ) );
			if ( '' === $n || isset( $have[ $n ] ) ) {
				continue;
			}
			if ( 'en' !== aw_si_lang( $w ) ) {
				$hindi[] = $w;
			} else {
				$miss[] = $w;
			}
		}
		if ( $hindi ) {
			$planned[] = $where . ': ' . implode( ', ', $hindi ) . ' (not English: no Sourcing role, the standing rule; kept in the backup)';
		}
		if ( $miss ) {
			$conflicts[] = $where . ': ' . implode( ', ', $miss ) . ' (no keyword record with that role)';
		}
	};
	$archive   = array(
		'aw_relevance' => array(),
		'aw_sources'   => array(),
	);
	$counts    = array();
	foreach ( AW_RETIRE_LIST_ROLE as $k => $bit ) {
		if ( array_key_exists( $k, $rel ) ) {
			$archive['aw_relevance'][ $k ]  = $rel[ $k ];
			$counts[ 'aw_relevance.' . $k ] = count( (array) $rel[ $k ] );
			$check( 'aw_relevance.' . $k, (array) $rel[ $k ], $by_role[ $bit ] ?? array() );
		}
	}
	if ( array_key_exists( 'categories', $cfg ) ) {
		$archive['aw_sources']['categories'] = $cfg['categories'];
		$counts['aw_sources.categories']     = array_sum( array_map( fn( $w ) => count( (array) $w ), (array) $cfg['categories'] ) );
		foreach ( (array) $cfg['categories'] as $slug => $words ) {
			$check( 'aw_sources.categories.' . $slug, (array) $words, $by_cat[ (string) $slug ] ?? array() );
		}
	}
	if ( array_key_exists( 'keywords', $cfg ) ) { // the old seed list: read by nothing since phase 2
		$archive['aw_sources']['keywords'] = $cfg['keywords'];
		$counts['aw_sources.keywords']     = count( (array) $cfg['keywords'] );
		$gone                              = array_values( array_filter( (array) $cfg['keywords'], fn( $w ) => ! isset( $any[ aw_si_norm( (string) $w ) ] ) ) );
		if ( $gone ) {
			$planned[] = 'aw_sources.keywords (the old seed list, read by nothing since phase 2): ' . implode( ', ', $gone ) . ' have no keyword record (archived)';
		}
	}
	if ( is_array( $cfg['press'] ?? null ) && array_key_exists( 'queries', $cfg['press'] ) ) {
		$archive['aw_sources']['press.queries'] = $cfg['press']['queries'];
		$counts['aw_sources.press.queries']     = count( (array) $cfg['press']['queries'] );
		$check( 'aw_sources.press.queries', (array) $cfg['press']['queries'], $by_role[ AW_KW_ROLE_PRESS ] ?? array() );
	}
	$archive['aw_sourcing_dismissed'] = array_values( $dismissed );
	$counts['aw_sourcing_dismissed']  = count( $dismissed );
	$lost                             = array_values( array_filter( $dismissed, fn( $q ) => ! isset( $dismiss[ aw_si_norm( (string) $q ) ] ) ) );
	if ( $lost ) {
		$conflicts[] = 'aw_sourcing_dismissed: ' . implode( ', ', array_map( 'strval', $lost ) ) . ' (no dismissed keyword record)';
	}
	return array(
		'archive'   => $archive,
		'counts'    => $counts,
		'conflicts' => $conflicts,
		'planned'   => $planned,
	);
}

/**
 * The archive option's value: the old lists exactly as stored, when, from which backup, and a checksum of each.
 *
 * @param array  $plan   aw_retire_plan().
 * @param string $at     When (MySQL time).
 * @param string $backup The phase 4 backup's option name.
 * @return array
 */
function aw_retire_archive( array $plan, $at, $backup ) {
	$sums = array();
	foreach ( $plan['archive'] as $k => $v ) {
		$sums[ $k ] = hash( 'sha256', (string) json_encode( $v ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- a pure function (no WordPress in the test runner)
	}
	return array(
		'at'     => (string) $at,
		'backup' => (string) $backup,
		'note'   => 'Sourcing phase 4: the old lists, kept as they were; the original options are not changed (owner, 25 Oct 2026).',
		'values' => $plan['archive'],
		'sums'   => $sums,
	);
}

/**
 * Whether an archive holds exactly the old lists of the options given (verify; also before the phase is set).
 *
 * @param array $archive aw_retire_archive().
 * @param array $plan    aw_retire_plan() on the options to compare with.
 * @return string[] Differences ([] = the same).
 */
function aw_retire_archive_problems( array $archive, array $plan ) {
	$out = array();
	foreach ( array_unique( array_merge( array_keys( (array) ( $archive['values'] ?? array() ) ), array_keys( $plan['archive'] ) ) ) as $k ) {
		$a = $archive['values'][ $k ] ?? null;
		if ( ( $plan['archive'][ $k ] ?? null ) !== $a ) {
			$out[] = "the archive's $k differs from the option";
		} elseif ( ( $archive['sums'][ $k ] ?? '' ) !== hash( 'sha256', (string) json_encode( $a ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- as aw_retire_archive()
			$out[] = "the archive's $k does not match its checksum";
		}
	}
	return $out;
}

/**
 * The policy without the v1 keys (phase 4): relevance keeps only its switch; config loses the old lists and the old
 * sources and sections (sources_v2, the keyword table and sections_v2 replace them) and press its queries.
 *
 * @param array $data The policy.
 * @return array
 */
function aw_retire_policy( array $data ) {
	if ( isset( $data['relevance'] ) && is_array( $data['relevance'] ) ) {
		$data['relevance'] = array( 'enabled' => ! empty( $data['relevance']['enabled'] ) );
	}
	if ( isset( $data['config'] ) && is_array( $data['config'] ) ) {
		foreach ( AW_RETIRE_V1_CONFIG as $k ) {
			unset( $data['config'][ $k ] );
		}
		if ( is_array( $data['config']['press'] ?? null ) ) {
			unset( $data['config']['press']['queries'] );
		}
	}
	return $data;
}

/**
 * What in a policy is still in the v1 shape, or missing from the v2 one (verify, tests).
 *
 * @param array $data The policy.
 * @return string[] Problems ([] = phase 4 shape).
 */
function aw_retire_policy_problems( array $data ) {
	$out = array();
	foreach ( AW_RETIRE_REL_LISTS as $k ) {
		if ( array_key_exists( $k, (array) ( $data['relevance'] ?? array() ) ) ) {
			$out[] = "relevance.$k is still sent";
		}
	}
	if ( ! isset( $data['config'] ) ) {
		return $out; // "Workers use these lists" is off: no lists are sent at all
	}
	foreach ( AW_RETIRE_V1_CONFIG as $k ) {
		if ( array_key_exists( $k, (array) $data['config'] ) ) {
			$out[] = "config.$k is still sent";
		}
	}
	if ( array_key_exists( 'queries', (array) ( $data['config']['press'] ?? array() ) ) ) {
		$out[] = 'config.press.queries is still sent';
	}
	foreach ( array( 'sources_v2', 'keywords_v2', 'sections_v2', 'category_names', 'match', 'schema' ) as $k ) {
		if ( ! array_key_exists( $k, (array) $data['config'] ) ) {
			$out[] = "config.$k is missing";
		}
	}
	if ( ! isset( $data['keywords'] ) || ! is_array( $data['keywords'] ) ) {
		$out[] = 'keywords (the keyword table) is missing';
	}
	return $out;
}

/**
 * Whether a rollback of an earlier phase may run (phase 4 must be rolled back first: the phases go back in order).
 *
 * @param int  $phase      The phase asked for.
 * @param int  $current    aw_sourcing_phase now.
 * @param bool $p4_record  A phase 4 migration record exists (aw_sourcing_phase4_record, complete or partial).
 * @return string Why not ('' = it may run).
 */
function aw_retire_refusal( $phase, $current, $p4_record ) {
	if ( (int) $phase < 4 && ( (int) $current >= 4 || $p4_record ) ) {
		return 'Phase 4 is migrated or half-migrated: roll it back first (wp aw-sourcing rollback --phase=4).';
	}
	return '';
}
