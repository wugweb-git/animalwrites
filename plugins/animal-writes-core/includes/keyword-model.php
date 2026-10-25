<?php
/**
 * The keyword table's rules as plain functions (sourcing restructure phase 2, owner 25 Oct 2026): role bits, the
 * normal form and language of a phrase, and the one role check every role change goes through. No WordPress calls
 * (bar wp_strip_all_tags, which the WordPress-free test runner provides), so plugins/aw-sourcing/tests/run.php tests
 * them without WordPress (owner rule: no local WordPress or MySQL).
 *
 * The table itself (wp_aw_si_keywords, the same name as before) and its map table live in keyword-table.php, here in
 * Animal Writes Core, so sourcing never depends on aw-seo-extras (owner's decision 3). Search Insights (aw-seo-extras)
 * reads and writes the same records for its own roles.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Roles (a bit field on each record). 1-16 are Search Insights' (1 was "Sourcing": the Include list); 32 and up
// arrived with phase 2. Include keeps bit 1, so Search Insights' "Sourcing" filter still finds the Include words.
const AW_SI_ROLE_SOURCING = 1;    // Include: keeps an item on its own; an English one is also a Google News search.
const AW_SI_ROLE_FOCUS    = 2;    // A Rank Math focus keyphrase (primary or secondary) on a post or term.
const AW_SI_ROLE_TRACKED  = 4;    // Keyword map target, demand gap or the monthly check list.
const AW_SI_ROLE_BRAND    = 8;    // Matches AW_SI_BRAND.
const AW_SI_ROLE_SEARCHED = 16;   // Seen in Search Console.
const AW_KW_ROLE_INCLUDE  = 1;    // The same bit as AW_SI_ROLE_SOURCING.
const AW_KW_ROLE_ANIMAL   = 32;   // An animal word: keeps an item together with a context word.
const AW_KW_ROLE_CONTEXT  = 64;   // A context word: keeps an item together with an animal word.
const AW_KW_ROLE_EXCLUDE  = 128;  // Drops an item, whatever else it has (always, on the site as in the workers).
const AW_KW_ROLE_SEARCH   = 256;  // Its own Google News search, besides (or instead of) Include.
const AW_KW_ROLE_PRESS    = 512;  // A press-discovery query (coverage of Animal Writes).
const AW_KW_ROLE_CATEGORY = 1024; // Files items into the categories it is mapped to.

/** The Sourcing roles (the ones the workers read, English only). */
const AW_KW_SOURCING_ROLES = AW_KW_ROLE_INCLUDE | AW_KW_ROLE_ANIMAL | AW_KW_ROLE_CONTEXT | AW_KW_ROLE_EXCLUDE | AW_KW_ROLE_SEARCH | AW_KW_ROLE_CATEGORY;

/** Sourcing and Press: the roles the workers receive (lang = en only). */
const AW_KW_WORKER_ROLES = AW_KW_SOURCING_ROLES | AW_KW_ROLE_PRESS;

/** Roles the owner sets by hand: kept when Search Insights' data is deleted, never rebuilt by its daily sync. */
const AW_KW_OWNER_ROLES = AW_KW_WORKER_ROLES | AW_SI_ROLE_TRACKED;

// Brand searches: Animal Writes in any spelling, and the old animalrights.in name.
const AW_SI_BRAND = '/animal\s*-?\s*writ|animalwrit|animal\s*-?\s*rights?\s*\.\s*in|animalrights?\.in|india\s*writes/i';

// Words ending in s that are not plurals: never folded ("news" must not become "new").
const AW_SI_NOT_PLURAL = array( 'news', 'rabies', 'species', 'series', 'scabies', 'diabetes', 'herpes', 'mumps', 'measles', 'always', 'perhaps', 'whereas', 'christmas' );

/**
 * The worker roles by bit: bit => slug the workers read (sourcing policy keywords_v2[].roles).
 *
 * @return array<int, string>
 */
function aw_kw_role_slugs() {
	return array(
		AW_KW_ROLE_INCLUDE  => 'include',
		AW_KW_ROLE_ANIMAL   => 'animal',
		AW_KW_ROLE_CONTEXT  => 'context',
		AW_KW_ROLE_EXCLUDE  => 'exclude',
		AW_KW_ROLE_SEARCH   => 'search',
		AW_KW_ROLE_PRESS    => 'press',
		AW_KW_ROLE_CATEGORY => 'category',
	);
}

/**
 * The worker role slugs a bit field holds, in the order of aw_kw_role_slugs().
 *
 * @param int $roles Bit field.
 * @return string[]
 */
function aw_kw_roles_to_slugs( $roles ) {
	$out = array();
	foreach ( aw_kw_role_slugs() as $bit => $slug ) {
		if ( (int) $roles & $bit ) {
			$out[] = $slug;
		}
	}
	return $out;
}

/**
 * Whether a phrase is a brand search (AW_SI_BRAND).
 *
 * @param string $s Phrase.
 */
function aw_si_is_brand( string $s ): bool {
	return (bool) preg_match( AW_SI_BRAND, $s );
}

/**
 * Normalised form: lower case, dots dropped (g.s.r. → gsr), other punctuation as spaces ({placeholders} kept), single
 * spaces, and each English word's plural folded (dogs → dog, ngos → ngo, gaushalas → gaushala). Variants that differ only
 * in these ways share one record.
 *
 * @param string $s Phrase as written.
 */
function aw_si_norm( string $s ): string {
	$s = mb_strtolower( wp_strip_all_tags( $s ) );
	$s = str_replace( array( '.', "'", '’' ), '', $s );
	$s = (string) preg_replace( '/[^\p{L}\p{M}\p{N}{}]+/u', ' ', $s );
	$w = preg_split( '/\s+/u', trim( $s ) );
	foreach ( (array) $w as $i => $word ) {
		if ( preg_match( '/^[a-z]{4,}s$/', $word ) && ! preg_match( '/(ss|us|is|ous)$/', $word ) && ! in_array( $word, AW_SI_NOT_PLURAL, true ) ) {
			$w[ $i ] = substr( $word, 0, -1 );
		}
	}
	return mb_substr( trim( implode( ' ', (array) $w ) ), 0, 191 );
}

/**
 * Language: hi (Devanagari), hinglish (Latin-script Hindi) or en. Hinglish needs one clear Hindi word, or two of the
 * short ones that are also English-looking ("ke", "se").
 *
 * @param string $s Phrase as written.
 */
function aw_si_lang( string $s ): string {
	if ( preg_match( '/\p{Devanagari}/u', $s ) ) {
		return 'hi';
	}
	static $strong = array( 'kutta', 'kutte', 'kutton', 'billi', 'gaay', 'gai', 'gau', 'janwar', 'jaanwar', 'janwaron', 'pashu', 'pashuon', 'chikitsa', 'aspatal', 'kaatna', 'kaata', 'kaat', 'kaise', 'kare', 'karein', 'kya', 'kahan', 'kab', 'manaya', 'jata', 'hota', 'hoti', 'nahi', 'nahin', 'sarkari', 'niyam', 'kanoon', 'kanun', 'shikayat', 'bachao', 'suchna', 'mein', 'liye' );
	static $weak   = array( 'ke', 'ki', 'ka', 'se', 'hai', 'ko', 'aur', 'par' );
	$words         = preg_split( '/\s+/', mb_strtolower( $s ) );
	$weak_hits     = 0;
	foreach ( (array) $words as $w ) {
		if ( in_array( $w, $strong, true ) ) {
			return 'hinglish';
		}
		if ( in_array( $w, $weak, true ) ) {
			++$weak_hits;
		}
	}
	return $weak_hits >= 2 ? 'hinglish' : 'en';
}

/**
 * The one role check (owner, 25 Oct 2026): every role change of a record goes through it.
 * - Sourcing and Press roles are English only (the standing rule: no Hindi or Hinglish sourcing). A record in another
 *   language keeps its other roles; asking for a Sourcing or Press role is refused.
 * - Include and Exclude on one record is refused.
 *
 * @param int    $roles  The record's roles now.
 * @param int    $add    Bits to add.
 * @param int    $remove Bits to remove.
 * @param string $lang   The record's language (en, hi, hinglish).
 * @return array{0: int, 1: string} The new roles (unchanged when refused), and why it was refused ('' when allowed).
 */
function aw_kw_roles_check( $roles, $add, $remove, $lang ) {
	$roles = (int) $roles;
	$new   = ( $roles | (int) $add ) & ~(int) $remove;
	$gain  = $new & ~$roles;
	if ( 'en' !== (string) $lang && ( $gain & AW_KW_WORKER_ROLES ) ) {
		return array( $roles, 'Sourcing and Press roles are for English keywords only (this one is ' . ( 'hi' === $lang ? 'Hindi' : ( 'hinglish' === $lang ? 'Hinglish' : (string) $lang ) ) . ').' );
	}
	if ( ( $new & AW_KW_ROLE_INCLUDE ) && ( $new & AW_KW_ROLE_EXCLUDE ) ) {
		return array( $roles, 'A keyword cannot be both Include and Exclude.' );
	}
	return array( $new, '' );
}

/**
 * Other spellings as stored (one per line) to a list: trimmed, no empties, no repeats (case-insensitive), in order.
 *
 * @param string|array $text Lines, or a list.
 * @return string[]
 */
function aw_kw_variants_list( $text ) {
	$list = is_array( $text ) ? $text : preg_split( '/\r\n|\r|\n/', (string) $text );
	$out  = array();
	$seen = array();
	foreach ( (array) $list as $v ) {
		$v = trim( (string) preg_replace( '/\s+/u', ' ', (string) $v ) );
		$k = mb_strtolower( $v );
		if ( '' !== $v && ! isset( $seen[ $k ] ) ) {
			$seen[ $k ] = true;
			$out[]      = $v;
		}
	}
	return $out;
}

/**
 * Every spelling of a record that matching uses: its label, then its other spellings (a trailing * on a spelling
 * matches the start of a word).
 *
 * @param array $row Record: label, variants (list or lines).
 * @return string[]
 */
function aw_kw_spellings( array $row ) {
	return aw_kw_variants_list( array_merge( array( (string) ( $row['label'] ?? '' ) ), aw_kw_variants_list( $row['variants'] ?? array() ) ) );
}
