<?php
/**
 * Focus keywords (Rank Math's rank_math_focus_keyword). Listings get their name when they are published (titles.php).
 * Other items get a short search phrase from the workers' focus-keyword job (animal-writes-workers,
 * aw_workers/focus_keywords.py), which reads and writes through this API:
 *
 * GET  /wp-json/aw/v1/seo-items?type=aw_news&page=1&per_page=100&missing=1
 *      → {items: [{id, title, seo_title, excerpt, focus, has_focus, in_title}], total, pages}
 * POST /wp-json/aw/v1/seo-focus {items: [{id, keyword}], replace: false}
 *      → writes the keyword where the field is empty and it passes aw_seo_clean_keyword() and is a phrase of the item's
 *        SEO title (aw_seo_keyword_in_title); with replace, also over a keyword that is not in the SEO title.
 *        A keyphrase another published page already has (its focus keyphrase, or the owner of the keyword record in
 *        Search Insights) is refused, and a News item never takes one an evergreen page owns (keyphrase-model.php).
 *        With dry: true, nothing is written and `written` counts what would be.
 *        {written, skipped: [{id, why, owner?}], dry}
 *
 * The SEO title is the source of truth (owner, 15 Oct 2026): the primary focus keyword is words that stand together in
 * it, never a title changed to fit a keyword.
 *
 * Allowed for administrators and the ingest user (the same rule as /aw/v1/ai-keys).
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/keyphrase-model.php';

/** Words that alone make no search phrase ("save animals", "animal welfare india"). */
// AW_SEO_GENERIC_WORDS: AW SEO Plus → Settings (settings.php).

/**
 * A keyword as stored: lower case, plain, no quotes, single spaces. '' when it is unusable:
 * - not 2 to 4 words, or over 60 characters;
 * - the site's own name, unless the item's title names the site itself (a campaign or press item about Animal Writes);
 * - ending on a connector ("india writes for", "rescued in");
 * - only generic words ("save animals", "animal welfare");
 * - sharing no word with the item's title or its own text (excerpt or first paragraph).
 *
 * The workers' focus-keyword job applies the same rules before it sends a phrase (aw_workers/focus_keywords.py).
 *
 * @param string $kw    Proposed keyword.
 * @param string $title Item title.
 * @param string $text  The item's own text (excerpt or intro), optional.
 * @return string
 */
function aw_seo_clean_keyword( $kw, $title, $text = '' ) {
	$kw = aw_seo_no_hyphens( mb_strtolower( trim( wp_strip_all_tags( (string) $kw ) ) ), false ); // a hyphen joining two words becomes a space: titles have none (owner, 18 Oct 2026)
	$kw = trim( preg_replace( array( '/["“”`#]+/u', "/['‘’](?![\\p{L}\\p{N}])|(?<![\\p{L}\\p{N}])['‘’]/u", '/\s+/u' ), array( '', '', ' ' ), $kw ), " \t\n.,;:'-" ); // an apostrophe inside a word stays ("taneja's")
	$parts = explode( ' ', $kw );
	$n     = count( $parts );
	$title = aw_seo_plain( $title );
	if ( '' === $kw || $n < 2 || $n > 4 || mb_strlen( $kw ) > 60 ) {
		return '';
	}
	if ( false !== strpos( $kw, 'animal writes' ) && false === mb_stripos( $title, 'animal writes' ) && false === mb_stripos( $title, 'animalwrites' ) ) {
		return '';
	}
	if ( preg_match( '/^(?:' . AW_SEO_DANGLING . ')$/iu', (string) end( $parts ) ) ) {
		return '';
	}
	if ( ! array_diff( $parts, AW_SEO_GENERIC_WORDS ) ) {
		return '';
	}
	$words = fn( $s ) => array_filter( preg_split( '/[^\p{L}\p{N}]+/u', mb_strtolower( $s ) ), fn( $w ) => mb_strlen( $w ) >= 3 );
	$stem  = fn( $w ) => mb_substr( $w, 0, 5 ); // "dogs" / "dog", "cruelty" / "cruel"
	$own   = array_map( $stem, $words( $title . ' ' . aw_seo_plain( $text ) ) );
	foreach ( $words( $kw ) as $w ) {
		if ( in_array( $stem( $w ), $own, true ) ) {
			return $kw;
		}
	}
	return '';
}

/** The SEO title of an item as printed, without the site name: Rank Math's stored title, else the title rule. */
function aw_seo_title_for_keyword( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	$t = trim( (string) get_post_meta( $post->ID, 'rank_math_title', true ) );
	if ( '' !== $t && false !== strpos( $t, '%' ) && class_exists( '\RankMath\Helper' ) ) {
		$t = \RankMath\Helper::replace_vars( $t, $post );
	}
	if ( '' === $t && function_exists( 'aw_seo_item_title' ) ) {
		$t = aw_seo_item_title( $post );
	}
	$t = aw_seo_plain( '' !== $t ? $t : $post->post_title );
	return trim( preg_replace( '/\s*[\x{2013}\x{2014}|-]\s*' . preg_quote( get_bloginfo( 'name' ), '/' ) . '$/iu', '', $t ) );
}

/**
 * Words of a text for phrase matching, as Rank Math's analyzer reads them: lower case; a hyphen or apostrophe inside a
 * word stays part of it ("animal-free", "taneja's"), since Rank Math finds "animal free" or "tanejas" nowhere in
 * "Animal-free" or "Taneja’s" (checked with its analyzer, 16 Oct 2026). Curly apostrophes count as straight ones here; a stored keyword keeps the title's own character.
 */
function aw_seo_phrase_words( $text ) {
	$text = str_replace( array( '’', '‘', '`' ), "'", mb_strtolower( (string) $text ) );
	preg_match_all( "/[\\p{L}\\p{N}]+(?:['-][\\p{L}\\p{N}]+)*/u", $text, $m );
	return $m[0];
}

/** Whether the keyword's words stand together, in order, in the SEO title (the workers check the same). */
function aw_seo_keyword_in_title( $kw, $seo_title ) {
	$k = aw_seo_phrase_words( $kw );
	$t = aw_seo_phrase_words( $seo_title );
	$n = count( $k );
	if ( ! $n ) {
		return false;
	}
	for ( $i = 0; $i + $n <= count( $t ); $i++ ) {
		if ( array_slice( $t, $i, $n ) === $k ) {
			return true;
		}
	}
	return false;
}

/** The item's own text the keyword may draw on: its excerpt, else its first paragraph. */
function aw_seo_keyword_text( $post ) {
	return aw_seo_plain( has_excerpt( $post ) ? $post->post_excerpt : aw_seo_intro( $post ) );
}

/**
 * Other published pages that already have this keyphrase: as their primary Rank Math focus keyphrase (posts and
 * terms), or as the owner of its keyword record in Search Insights (wp_aw_si_kw_pages, role owner).
 *
 * @param string $kw Keyphrase, as aw_seo_clean_keyword() returns it.
 * @param int    $id The page asking for it (never in the list).
 * @return array Each [ 'type' => post type or 'term', 'id' => int ].
 */
function aw_seo_keyphrase_owners( $kw, $id ) {
	global $wpdb;
	$kw  = mb_strtolower( trim( (string) $kw ) );
	$out = array();
	if ( '' === $kw ) {
		return $out;
	}
	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	$posts = (array) $wpdb->get_results( $wpdb->prepare( "SELECT p.ID, p.post_type FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = 'rank_math_focus_keyword' AND p.post_status = 'publish' AND p.ID <> %d AND LOWER( TRIM( SUBSTRING_INDEX( m.meta_value, ',', 1 ) ) ) = %s LIMIT 5", (int) $id, $kw ) );
	foreach ( $posts as $p ) {
		$out[ 'post' . $p->ID ] = array( 'type' => (string) $p->post_type, 'id' => (int) $p->ID );
	}
	$terms = (array) $wpdb->get_col( $wpdb->prepare( "SELECT term_id FROM {$wpdb->termmeta} WHERE meta_key = 'rank_math_focus_keyword' AND LOWER( TRIM( SUBSTRING_INDEX( meta_value, ',', 1 ) ) ) = %s LIMIT 5", $kw ) );
	foreach ( $terms as $t ) {
		$out[ 'term' . $t ] = array( 'type' => 'term', 'id' => (int) $t );
	}
	if ( function_exists( 'aw_si_ready' ) && aw_si_ready() && function_exists( 'aw_si_norm' ) ) {
		$k    = aw_si_table( 'keywords' );
		$pg   = aw_si_table( 'kw_pages' );
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT l.obj_type, l.obj_id FROM {$pg} l JOIN {$k} k ON k.id = l.kw_id WHERE k.norm = %s AND l.role = 'owner' AND l.obj_type IN ('post', 'term') AND l.obj_id > 0 AND NOT ( l.obj_type = 'post' AND l.obj_id = %d ) LIMIT 5", aw_si_norm( $kw ), (int) $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names only.
		foreach ( $rows as $r ) {
			if ( 'post' === $r->obj_type ) {
				if ( 'publish' === get_post_status( (int) $r->obj_id ) ) {
					$out[ 'post' . $r->obj_id ] = array( 'type' => (string) get_post_type( (int) $r->obj_id ), 'id' => (int) $r->obj_id );
				}
			} else {
				$out[ 'term' . $r->obj_id ] = array( 'type' => 'term', 'id' => (int) $r->obj_id );
			}
		}
	}
	// phpcs:enable WordPress.DB.DirectDatabaseQuery
	return array_values( $out );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'aw/v1',
			'/seo-items',
			array(
				'methods'             => 'GET',
				'permission_callback' => 'aw_seo_api_allowed',
				'args'                => array(
					'type'     => array( 'required' => true, 'type' => 'string', 'enum' => AW_SEO_ITEM_TYPES ),
					'page'     => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
					'per_page' => array( 'type' => 'integer', 'default' => 100, 'minimum' => 1, 'maximum' => 200 ),
					'missing'  => array( 'type' => 'boolean', 'default' => false ),
				),
				'callback'            => function ( WP_REST_Request $req ) {
					do_action( 'litespeed_control_set_nocache', 'focus-keyword API' );
					$args = array(
						'post_type'           => $req['type'],
						'post_status'         => 'publish',
						'posts_per_page'      => (int) $req['per_page'],
						'paged'               => (int) $req['page'],
						'orderby'             => 'ID',
						'order'               => 'DESC',
						'ignore_sticky_posts' => true,
					);
					if ( $req['missing'] ) { // by SQL: the site's list rules (pre_get_posts) replace a meta_query
						global $wpdb;
						$ids              = $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'rank_math_focus_keyword' WHERE p.post_type = %s AND p.post_status = 'publish' GROUP BY p.ID HAVING COALESCE( MAX( m.meta_value ), '' ) = ''", $req['type'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						$args['post__in'] = $ids ? array_map( 'intval', $ids ) : array( 0 );
					}
					$q   = new WP_Query( $args );
					$out = array();
					foreach ( $q->posts as $p ) {
						if ( aw_seo_is_hub_page( $p ) ) {
							continue;
						}
						$focus = trim( (string) get_post_meta( $p->ID, 'rank_math_focus_keyword', true ) );
						$first = trim( (string) strtok( $focus, ',' ) ); // Rank Math keeps extra keywords after commas; the first is the primary one
						$seo   = aw_seo_title_for_keyword( $p );
						$out[] = array(
							'id'        => $p->ID,
							'title'     => aw_seo_plain( $p->post_title ),
							'seo_title' => $seo,
							'excerpt'   => aw_seo_cut( aw_seo_keyword_text( $p ), 400 ),
							'focus'     => $focus,
							'has_focus' => '' !== $focus,
							'in_title'  => '' !== $first && aw_seo_keyword_in_title( $first, $seo ),
						);
					}
					return array( 'items' => $out, 'total' => (int) $q->found_posts, 'pages' => (int) $q->max_num_pages );
				},
			)
		);
		register_rest_route(
			'aw/v1',
			'/seo-focus',
			array(
				'methods'             => 'POST',
				'permission_callback' => 'aw_seo_api_allowed',
				'callback'            => function ( WP_REST_Request $req ) {
					$written = 0;
					$skipped = array();
					$replace = (bool) $req->get_param( 'replace' );
					$dry     = (bool) $req->get_param( 'dry' );
					foreach ( array_slice( (array) $req->get_param( 'items' ), 0, 200 ) as $it ) {
						$id   = absint( $it['id'] ?? 0 );
						$post = $id ? get_post( $id ) : null;
						if ( ! $post || 'publish' !== $post->post_status || ! in_array( $post->post_type, AW_SEO_ITEM_TYPES, true ) ) {
							$skipped[] = array( 'id' => $id, 'why' => 'not a published item' );
							continue;
						}
						$have = trim( (string) get_post_meta( $id, 'rank_math_focus_keyword', true ) );
						$seo  = aw_seo_title_for_keyword( $post );
						$rest = '';
						if ( '' !== $have ) {
							$parts = array_map( 'trim', explode( ',', $have ) );
							if ( ! $replace || aw_seo_keyword_in_title( $parts[0], $seo ) ) {
								$skipped[] = array( 'id' => $id, 'why' => 'already set' );
								continue;
							}
							$rest = implode( ', ', array_slice( $parts, 1 ) ); // extra keywords stay after the new primary one
						}
						$kw = aw_seo_clean_keyword( (string) ( $it['keyword'] ?? '' ), $post->post_title, aw_seo_keyword_text( $post ) );
						if ( '' === $kw ) {
							$skipped[] = array( 'id' => $id, 'why' => 'unusable keyword' );
							continue;
						}
						if ( ! aw_seo_keyword_in_title( $kw, $seo ) ) {
							$skipped[] = array( 'id' => $id, 'why' => 'not a phrase of the SEO title' );
							continue;
						}
						$owners = aw_seo_keyphrase_owners( $kw, $id );
						$why    = aw_seo_kp_refusal( $post->post_type, $owners );
						if ( '' !== $why ) {
							$skipped[] = array( 'id' => $id, 'why' => $why, 'owner' => $owners );
							continue;
						}
						if ( $dry ) {
							++$written;
							continue;
						}
						if ( '' !== $have ) {
							$hist = (array) get_post_meta( $id, '_aw_focus_history', true );
							$hist[] = array( 'keyword' => $have, 'replaced' => gmdate( 'c' ) );
							update_post_meta( $id, '_aw_focus_history', $hist ); // the keyword it replaced
						}
						update_post_meta( $id, 'rank_math_focus_keyword', wp_slash( '' !== $rest ? $kw . ', ' . $rest : $kw ) );
						++$written;
					}
					return array( 'written' => $written, 'skipped' => $skipped, 'dry' => $dry );
				},
			)
		);
	}
);
