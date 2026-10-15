<?php
/**
 * Automatic review of ingested news and Legal Watch items (owner's decision, 7 Oct 2026: routine items that pass
 * the checks go live; only doubtful ones wait for a person).
 *
 * An item is published when every check passes; otherwise it stays Pending with the reason in `aw_review`
 * (shown in the admin list). Checks: publisher on the allow list, a category, a real summary, not a thin format, relevant,
 * legal items from a legal source. The same event as a story published in the last 7 days is not published again:
 * its publisher is added to that story as "also reported by" (the workers add the facts it adds: same-story.php) and the
 * item is kept private. Nothing is deleted.
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
 * The workers' relevance check (stored on the item as aw_relevance) said: about animal welfare, and about India.
 *
 * @param int $id Post ID.
 * @return array|null The verdict when it passed, else null.
 */
function aw_ai_passed( $id ) {
	$v = json_decode( (string) get_post_meta( $id, 'aw_relevance', true ), true );
	return is_array( $v ) && ! empty( $v['relevant'] ) && ! empty( $v['india'] ) ? $v : null;
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

	// The owner can be offline for more than a day (12 Oct 2026): an item the AI check passed (animal welfare, India)
	// goes live from a neutral publisher too, without the keyword test. Blocked publishers never do.
	$ai = aw_ai_passed( $id );
	// 0. A publisher on the allow list (News > Sourcing); other publishers wait for a person unless the AI check passed.
	if ( function_exists( 'aw_publishers' ) ) {
		$status = aw_publishers()[ $host ]['status'] ?? 'neutral';
		if ( 'block' === $status ) {
			return 'publisher blocked (' . $host . ')';
		}
		if ( 'allow' !== $status && ! $ai ) {
			return 'publisher not on the allow list (' . $host . ')';
		}
	}
	// 0b. At least one category: the AI check's category when the workers sent none.
	if ( ! wp_get_object_terms( $id, 'category', array( 'fields' => 'ids' ) ) ) {
		$term = $ai && ! empty( $ai['category'] ) ? get_term_by( 'slug', sanitize_title( $ai['category'] ), 'category' ) : null;
		if ( ! $term ) {
			return 'no category';
		}
		wp_set_object_terms( $id, array( (int) $term->term_id ), 'category' );
	}
	// 1. Summary follows the summary rules of its section (Settings → Sourcing → Summary rules): length, plain text.
	$section = function_exists( 'aw_summary_section' ) ? aw_summary_section( $post ) : '';
	if ( '' !== $section ) {
		$why = aw_summary_problem( $post->post_excerpt, $section );
		if ( '' !== $why ) {
			return $why;
		}
	} elseif ( $words < 15 || $words > 90 ) {
		return "summary length ({$words} words)";
	}
	if ( preg_match( '/\b(I cannot|I can\'t|as an AI|unable to (access|summari[sz]e)|no information)\b/i', $post->post_excerpt ) ) {
		return 'summary reads like an error';
	}
	// 2. Thin formats.
	if ( preg_match( '#/(web-?stories|videos?|video-news|short-videos?|photos?|photogallery|gallery|slideshow)/#i', $url ) ) {
		return 'thin format (web story / video / gallery)';
	}
	// 3. Relevance (owner's keyword lists in News → Sourcing), unless the AI check already passed it.
	if ( ! $ai && function_exists( 'aw_relevance_verdict' ) ) {
		list( $ok, $why ) = aw_relevance_verdict( $post->post_title . ' ' . $post->post_excerpt );
		if ( ! $ok ) {
			return 'not clearly about animal welfare (' . $why . ')';
		}
	}
	// 4. Legal Watch: a legal or government source.
	if ( 'aw_legal' === $type ) {
		$legal = (bool) aw_item_issuer( $id );
		foreach ( AW_LEGAL_HOSTS as $h ) {
			$legal = $legal || $host === $h || str_ends_with( (string) $host, '.' . $h );
		}
		if ( ! $legal ) {
			return 'legal item from a non-legal source (' . $host . ')';
		}
	}
	// 5. Near-duplicate of a story published in the last 7 days.
	$mine = aw_title_words( $post->post_title );
	if ( count( $mine ) >= 3 ) {
		$recent = get_posts(
			array(
				'post_type'   => $type,
				'post_status' => 'publish', // compare with what readers already see, not with items still waiting
				'date_query'  => array( array( 'after' => '7 days ago' ) ),
				'exclude'     => array( $id ),
				'numberposts' => 200,
			)
		);
		foreach ( $recent as $r ) {
			$other  = aw_title_words( $r->post_title );
			$shared = count( array_intersect( $mine, $other ) );
			$union  = count( array_unique( array_merge( $mine, $other ) ) );
			if ( $union && $shared / $union >= 0.6 ) { // 0.45 merged different stories ("stray dog attack in X / in Y")
				return 'same story as #' . $r->ID . ' (' . wp_trim_words( $r->post_title, 8 ) . ')';
			}
		}
	}
	return '';
}

/**
 * Which sourcing rule and keyword an item met (S3.4), kept on the item and shown in the admin list:
 * "Publisher allowed (thehindu.com) · strong term: stray dog".
 *
 * @param int $id Post ID.
 */
function aw_record_match( $id ) {
	$host = function_exists( 'aw_host' ) ? aw_host( (string) get_post_meta( $id, 'aw_source_url', true ) ) : '';
	$st   = function_exists( 'aw_publishers' ) ? ( aw_publishers()[ $host ]['status'] ?? 'neutral' ) : '';
	$word = function_exists( 'aw_relevance_verdict' ) ? aw_relevance_verdict( get_the_title( $id ) . ' ' . get_post_field( 'post_excerpt', $id ) )[1] : '';
	update_post_meta( $id, 'aw_match', trim( 'Publisher ' . ( 'allow' === $st ? 'allowed' : ( 'block' === $st ? 'blocked' : 'not yet allowed' ) ) . ( $host ? ' (' . $host . ')' : '' ) . ' · ' . $word, ' ·' ) );
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
	aw_record_match( $id );
	$reason = aw_review_reason( $id );
	// Same event as a published story: credit this publisher on that story instead of publishing a second copy.
	if ( preg_match( '/^same story as #(\d+)/', $reason, $m ) ) {
		// The facts it adds are written by the workers, which have the report's own text (same-story.php).
		$keep = (int) $m[1];
		aw_same_story_fold( $keep, (string) get_post_meta( $id, 'aw_source_url', true ), (string) get_post_meta( $id, 'aw_source_name', true ) );
		wp_update_post( array( 'ID' => $id, 'post_status' => 'private' ) );
		update_post_meta( $id, 'aw_merged_into', $keep );
		update_post_meta( $id, 'aw_review', 'merged into #' . $keep . ' as "also reported by" ' . wp_date( 'Y-m-d H:i' ) );
		return 'merged';
	}
	if ( '' === $reason ) {
		// The story's own date (owner, 12 Oct 2026), never later than now: an older story is not shown as today's.
		$day  = substr( (string) get_post_meta( $id, 'aw_source_date', true ), 0, 10 );
		$now  = current_time( 'mysql' );
		$when = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) ? min( $day . ' ' . substr( $now, 11 ), $now ) : $now;
		wp_update_post( array( 'ID' => $id, 'post_status' => 'publish', 'post_date' => $when, 'post_date_gmt' => get_gmt_from_date( $when ), 'edit_date' => true ) );
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
				$m = (string) get_post_meta( $id, 'aw_match', true );
				if ( '' !== $m ) {
					echo '<br><small>' . esc_html( $m ) . '</small>';
				}
			}
		},
		10,
		2
	);
}
