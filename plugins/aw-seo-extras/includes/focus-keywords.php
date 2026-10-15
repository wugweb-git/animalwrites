<?php
/**
 * Focus keywords (Rank Math's rank_math_focus_keyword). Listings get their name when they are published (titles.php).
 * Other items get a short search phrase from the workers' focus-keyword job (animal-writes-workers,
 * aw_workers/focus_keywords.py), which reads and writes through this API:
 *
 * GET  /wp-json/aw/v1/seo-items?type=aw_news&page=1&per_page=100&missing=1
 *      → {items: [{id, title, excerpt, has_focus}], total, pages}
 * POST /wp-json/aw/v1/seo-focus {items: [{id, keyword}]}
 *      → writes the keyword only where the field is empty and it passes aw_seo_clean_keyword(); {written, skipped: [{id, why}]}
 *
 * Allowed for administrators and the ingest user (the same rule as /aw/v1/ai-keys).
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Whether the current request may use the focus-keyword API. */
function aw_seo_api_allowed() {
	return current_user_can( 'manage_options' ) || 'ingest' === wp_get_current_user()->user_login;
}

/** Words that alone make no search phrase ("save animals", "animal welfare india"). */
const AW_SEO_GENERIC_WORDS = array( 'animal', 'animals', 'welfare', 'india', 'indian', 'save', 'help', 'news', 'care', 'update', 'updates', 'issue', 'case', 'story' );

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
	$kw = mb_strtolower( trim( wp_strip_all_tags( (string) $kw ) ) );
	$kw = trim( preg_replace( array( '/["“”‘’`#]+/u', '/\s+/u' ), array( '', ' ' ), $kw ), " \t\n.,;:'-" );
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

/** The item's own text the keyword may draw on: its excerpt, else its first paragraph. */
function aw_seo_keyword_text( $post ) {
	return aw_seo_plain( has_excerpt( $post ) ? $post->post_excerpt : aw_seo_intro( $post ) );
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
						$out[] = array(
							'id'        => $p->ID,
							'title'     => aw_seo_plain( $p->post_title ),
							'excerpt'   => aw_seo_cut( aw_seo_keyword_text( $p ), 400 ),
							'has_focus' => '' !== trim( (string) get_post_meta( $p->ID, 'rank_math_focus_keyword', true ) ),
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
					foreach ( array_slice( (array) $req->get_param( 'items' ), 0, 200 ) as $it ) {
						$id   = absint( $it['id'] ?? 0 );
						$post = $id ? get_post( $id ) : null;
						if ( ! $post || 'publish' !== $post->post_status || ! in_array( $post->post_type, AW_SEO_ITEM_TYPES, true ) ) {
							$skipped[] = array( 'id' => $id, 'why' => 'not a published item' );
							continue;
						}
						if ( '' !== trim( (string) get_post_meta( $id, 'rank_math_focus_keyword', true ) ) ) {
							$skipped[] = array( 'id' => $id, 'why' => 'already set' );
							continue;
						}
						$kw = aw_seo_clean_keyword( (string) ( $it['keyword'] ?? '' ), $post->post_title, aw_seo_keyword_text( $post ) );
						if ( '' === $kw ) {
							$skipped[] = array( 'id' => $id, 'why' => 'unusable keyword' );
							continue;
						}
						update_post_meta( $id, 'rank_math_focus_keyword', wp_slash( $kw ) );
						++$written;
					}
					return array( 'written' => $written, 'skipped' => $skipped );
				},
			)
		);
	}
);
