<?php
/**
 * Automatic review of ingested news and Legal Watch items (owner's decision, 7 Oct 2026: routine items that pass
 * the checks go live; only doubtful ones wait for a person).
 *
 * An item is published when every check passes; otherwise it stays Pending with the reason in `aw_review`
 * (shown in the admin list). Nothing is deleted.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Publishers whose items count as legal sources for Legal Watch. */
const AW_LEGAL_HOSTS = array( 'livelaw.in', 'barandbench.com', 'scobserver.in', 'pib.gov.in', 'awbi.gov.in', 'sci.gov.in', 'indiankanoon.org', 'egazette.gov.in' );

/**
 * Words of a title that carry meaning (for near-duplicate checks).
 *
 * @param string $title Title.
 * @return string[]
 */
function aw_title_words( $title ) {
	$stop  = array( 'the', 'a', 'an', 'of', 'to', 'in', 'on', 'for', 'and', 'with', 'at', 'by', 'from', 'is', 'are', 'as', 'its', 'over', 'after', 'into', 'india', 'indian' );
	$words = preg_split( '/[^\p{L}\p{N}]+/u', mb_strtolower( wp_strip_all_tags( $title ) ), -1, PREG_SPLIT_NO_EMPTY );
	return array_values( array_unique( array_filter( $words, fn( $w ) => mb_strlen( $w ) > 2 && ! in_array( $w, $stop, true ) ) ) );
}

/**
 * Why an item must wait for a person, or '' when it can go live.
 *
 * @param int $id Post ID.
 * @return string
 */
function aw_review_reason( $id ) {
	$post  = get_post( $id );
	$type  = $post->post_type;
	$url   = (string) get_post_meta( $id, 'aw_source_url', true );
	$host  = function_exists( 'aw_host' ) ? aw_host( $url ) : (string) wp_parse_url( $url, PHP_URL_HOST );
	$words = str_word_count( wp_strip_all_tags( $post->post_excerpt ) );

	// 1. Summary looks like a summary.
	if ( $words < 15 || $words > 90 ) {
		return "summary length ({$words} words)";
	}
	if ( preg_match( '/\b(I cannot|I can\'t|as an AI|unable to (access|summari[sz]e)|no information)\b/i', $post->post_excerpt ) ) {
		return 'summary reads like an error';
	}
	// 2. Thin formats.
	if ( preg_match( '#/(web-?stories|videos?|video-news|short-videos?|photos?|photogallery|gallery|slideshow)/#i', $url ) ) {
		return 'thin format (web story / video / gallery)';
	}
	// 3. Relevance (owner's keyword lists in News → Sourcing).
	if ( function_exists( 'aw_relevance_verdict' ) ) {
		list( $ok, $why ) = aw_relevance_verdict( $post->post_title . ' ' . $post->post_excerpt );
		if ( ! $ok ) {
			return 'not clearly about animal welfare (' . $why . ')';
		}
	}
	// 4. Legal Watch: a legal or government source.
	if ( 'aw_legal' === $type ) {
		$legal = (bool) wp_get_object_terms( $id, 'aw_source_type', array( 'fields' => 'ids' ) );
		foreach ( AW_LEGAL_HOSTS as $h ) {
			$legal = $legal || $host === $h || str_ends_with( (string) $host, '.' . $h );
		}
		if ( ! $legal ) {
			return 'legal item from a non-legal source (' . $host . ')';
		}
	}
	// 5. Near-duplicate of a story from the last 7 days (published or already waiting).
	$mine = aw_title_words( $post->post_title );
	if ( count( $mine ) >= 3 ) {
		$recent = get_posts(
			array(
				'post_type'   => $type,
				'post_status' => array( 'publish', 'pending' ),
				'date_query'  => array( array( 'after' => '7 days ago' ) ),
				'exclude'     => array( $id ),
				'numberposts' => 200,
			)
		);
		foreach ( $recent as $r ) {
			$other  = aw_title_words( $r->post_title );
			$shared = count( array_intersect( $mine, $other ) );
			$union  = count( array_unique( array_merge( $mine, $other ) ) );
			if ( $union && $shared / $union >= 0.45 ) {
				return 'same story as #' . $r->ID . ' (' . wp_trim_words( $r->post_title, 8 ) . ')';
			}
		}
	}
	return '';
}

/**
 * Review one ingested item: publish it, or keep it Pending with the reason.
 *
 * @param int $id Post ID.
 * @return string 'published' or the reason it is held.
 */
function aw_auto_review( $id ) {
	if ( ! in_array( get_post_type( $id ), array( 'aw_news', 'aw_legal' ), true ) || 'pending' !== get_post_status( $id ) ) {
		return '';
	}
	$reason = aw_review_reason( $id );
	if ( '' === $reason ) {
		wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
		update_post_meta( $id, 'aw_review', 'auto-published ' . wp_date( 'Y-m-d H:i' ) );
		return 'published';
	}
	update_post_meta( $id, 'aw_review', 'held: ' . $reason );
	return $reason;
}

// Admin list: why an item is waiting.
foreach ( array( 'aw_news', 'aw_legal' ) as $aw_rt ) {
	add_filter( "manage_{$aw_rt}_posts_columns", fn( $c ) => $c + array( 'aw_review' => 'Review' ) );
	add_action(
		"manage_{$aw_rt}_posts_custom_column",
		function ( $col, $id ) {
			if ( 'aw_review' === $col ) {
				echo esc_html( (string) get_post_meta( $id, 'aw_review', true ) );
			}
		},
		10,
		2
	);
}
