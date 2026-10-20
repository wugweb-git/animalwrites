<?php
/**
 * Research papers' credit (owner, 20 Oct 2026): "Study by <authors> in <journal>" in the item's Sources block, and the
 * ScholarlyArticle it is based on in its schema.
 *
 * - aw_authors (array of names as printed) and aw_journal (string) on a research post (Resources, tag "research").
 * - The research worker sends authors and journal with each paper (ingest.php saves them). A paper that arrives
 *   without authors gets them from doi.org (CSL-JSON) in the background (aw_research_citation).
 * - aw_research_citation_backfill() fills every research post that has none yet (run once with WP-CLI).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The DOI in a research post's source URL ('' when it is not a doi.org link). */
function aw_research_doi( $id ) {
	$url = (string) get_post_meta( $id, 'aw_source_url', true );
	return preg_match( '~^https?://(?:dx\.)?doi\.org/(10\.[^\s?#]+)~i', $url, $m ) ? rawurldecode( $m[1] ) : '';
}

/**
 * Save a paper's authors and journal.
 *
 * @param int    $id      Post ID.
 * @param array  $authors Names.
 * @param string $journal Journal title.
 */
function aw_research_save_citation( $id, $authors, $journal ) {
	$names = array_values( array_unique( array_filter( array_map( fn( $a ) => trim( sanitize_text_field( (string) $a ) ), (array) $authors ) ) ) );
	if ( $names ) {
		update_post_meta( $id, 'aw_authors', array_slice( $names, 0, 100 ) );
	}
	$journal = trim( sanitize_text_field( (string) $journal ) );
	if ( '' !== $journal ) {
		update_post_meta( $id, 'aw_journal', $journal );
	}
}

/**
 * Authors and journal from doi.org (CSL-JSON). Returns true when something was saved.
 *
 * @param int $id Post ID.
 * @return bool
 */
function aw_research_citation_fetch( $id ) {
	$doi = aw_research_doi( $id );
	update_post_meta( $id, 'aw_citation_tried', current_time( 'mysql' ) );
	if ( '' === $doi ) {
		return false;
	}
	$r = wp_remote_get(
		'https://doi.org/' . implode( '/', array_map( 'rawurlencode', explode( '/', $doi ) ) ),
		array(
			'timeout'     => 20,
			'redirection' => 5,
			'headers'     => array( 'Accept' => 'application/vnd.citationstyles.csl+json' ),
			'user-agent'  => 'AnimalWrites/1.0 (+https://animalwrites.in; contact@animalwrites.in)',
		)
	);
	if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
		return false;
	}
	$d = json_decode( (string) wp_remote_retrieve_body( $r ), true );
	if ( ! is_array( $d ) ) {
		return false;
	}
	$names = array();
	foreach ( (array) ( $d['author'] ?? array() ) as $a ) {
		$n = trim( ( $a['given'] ?? '' ) . ' ' . ( $a['family'] ?? '' ) );
		$names[] = '' !== $n ? $n : (string) ( $a['literal'] ?? $a['name'] ?? '' );
	}
	$journal = $d['container-title'] ?? '';
	$journal = is_array( $journal ) ? (string) ( $journal[0] ?? '' ) : (string) $journal;
	aw_research_save_citation( $id, $names, html_entity_decode( $journal, ENT_QUOTES ) );
	return (bool) $names || '' !== $journal;
}
add_action( 'aw_research_citation', 'aw_research_citation_fetch' );

/**
 * Fill every published research post that has no authors yet. Returns [checked, filled].
 *
 * @return int[]
 */
function aw_research_citation_backfill() {
	$ids    = get_posts( array( 'post_type' => 'post', 'tag' => 'research', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) );
	$filled = 0;
	foreach ( $ids as $id ) {
		if ( get_post_meta( $id, 'aw_authors', true ) ) {
			continue;
		}
		$filled += aw_research_citation_fetch( $id ) ? 1 : 0;
		usleep( 300000 ); // doi.org: gently
	}
	return array( count( $ids ), $filled );
}

/** The credit line's names: up to three, then "et al.". */
function aw_research_authors_text( $id ) {
	$a = array_values( (array) get_post_meta( $id, 'aw_authors', true ) );
	if ( ! $a ) {
		return '';
	}
	return count( $a ) > 3 ? implode( ', ', array_slice( $a, 0, 3 ) ) . ' et al.' : ( count( $a ) > 1 ? implode( ', ', array_slice( $a, 0, -1 ) ) . ' and ' . end( $a ) : $a[0] );
}
