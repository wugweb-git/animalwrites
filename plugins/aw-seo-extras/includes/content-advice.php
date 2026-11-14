<?php
/**
 * RankWeb content panel: what Rank Math keeps for Content AI, from the site's own data (owner, 12 Nov 2026).
 *
 * A block editor sidebar ("RankWeb content", assets/content-panel.js) shows, for the item being edited:
 * - its focus keyphrases and any other published page that already has the same one;
 * - the search queries it was shown for in the last 28 days (Search Insights, Google Search Console), with clicks,
 *   impressions and average position, and whether each query is already in the text;
 * - published pages whose primary focus keyphrase appears in this text but which it does not link to yet;
 * - its length and subheadings next to the median of published items of the same type;
 * - the automatic FAQ questions it has (faq.php).
 * Read only: nothing here writes to the item (the panel's Link keywords save through internal-links.php). Route: GET /wp-json/aw/v1/content-advice?id=N (edit_post on N).
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'aw/v1',
			'/content-advice',
			array(
				'methods'             => 'GET',
				'args'                => array(
					'id' => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1,
					),
				),
				'permission_callback' => function ( WP_REST_Request $req ) {
					return current_user_can( 'edit_post', (int) $req['id'] );
				},
				'callback'            => function ( WP_REST_Request $req ) {
					$post = get_post( (int) $req['id'] );
					if ( ! $post ) {
						return new WP_Error( 'aw_no_post', __( 'No such item.', 'aw-seo-extras' ), array( 'status' => 404 ) );
					}
					return rest_ensure_response( aw_seo_content_advice( $post ) );
				},
			)
		);
	}
);

/**
 * Plain text of an item as visitors read it (the rendered <main> when RankWeb analyses it rendered).
 *
 * @param WP_Post $post Item.
 * @return array{text: string, html: string}
 */
function aw_seo_advice_text( WP_Post $post ) {
	$html = function_exists( 'aw_seo_analysis_content' ) ? (string) aw_seo_analysis_content( $post ) : '';
	if ( '' === $html ) {
		$html = (string) apply_filters( 'the_content', $post->post_content );
	}
	$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $html ) ) );
	return array(
		'text' => $text,
		'html' => $html,
	);
}

/**
 * Words, H2 and H3 counts of an HTML fragment.
 *
 * @param string $html HTML.
 * @return array{words: int, h2: int, h3: int}
 */
function aw_seo_advice_shape( $html ) {
	$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $html ) ) );
	return array(
		'words' => '' === $text ? 0 : count( preg_split( '/\s+/u', $text ) ),
		'h2'    => preg_match_all( '/<h2[\s>]/i', (string) $html ),
		'h3'    => preg_match_all( '/<h3[\s>]/i', (string) $html ),
	);
}

/**
 * Median words and subheadings of up to 12 recent published items, as visitors read them, of a type (cached for a day).
 *
 * @param string $type Post type.
 * @return array{words: int, h2: int, h3: int, n: int}
 */
function aw_seo_advice_type_median( $type ) {
	$key = 'aw_seo_advice_median2_' . sanitize_key( $type );
	$hit = get_transient( $key );
	if ( is_array( $hit ) ) {
		return $hit;
	}
	$ids  = get_posts(
		array(
			'post_type'      => $type,
			'post_status'    => 'publish',
			'posts_per_page' => 12,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	$rows = array();
	foreach ( $ids as $id ) {
		$rows[] = aw_seo_advice_shape( aw_seo_advice_text( get_post( $id ) )['html'] ); // what visitors read, as for the item
	}
	$med = function ( $k ) use ( $rows ) {
		$v = array_column( $rows, $k );
		sort( $v );
		$n = count( $v );
		return $n ? (int) $v[ intdiv( $n, 2 ) ] : 0;
	};
	$out = array(
		'words' => $med( 'words' ),
		'h2'    => $med( 'h2' ),
		'h3'    => $med( 'h3' ),
		'n'     => count( $rows ),
	);
	set_transient( $key, $out, DAY_IN_SECONDS );
	return $out;
}

/**
 * Published items with a primary focus keyphrase: [ phrase => [ id, url ] ], longest phrases first (cached 12 hours).
 *
 * @return array
 */
function aw_seo_advice_focus_index() {
	$hit = get_transient( 'aw_seo_advice_focus_index_v2' );
	if ( is_array( $hit ) ) {
		return $hit;
	}
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one read, cached below.
	$rows = (array) $wpdb->get_results( "SELECT p.ID, p.post_type, LOWER( TRIM( SUBSTRING_INDEX( m.meta_value, ',', 1 ) ) ) AS kw FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = 'rank_math_focus_keyword' AND m.meta_value <> '' AND p.post_status = 'publish' AND p.post_type NOT IN ('attachment', 'revision', 'nav_menu_item') LIMIT 5000" );
	$out  = array();
	foreach ( $rows as $r ) {
		$kw = (string) $r->kw;
		if ( mb_strlen( $kw ) < 8 || isset( $out[ $kw ] ) || substr_count( $kw, ' ' ) < 1 ) {
			continue; // one-word slugs and very short phrases match too much to be link advice
		}
		// Link advice points at evergreen pages on this site only (guides, Library, campaigns, Pages), never News or press.
		if ( function_exists( 'aw_seo_kp_evergreen' ) && ! aw_seo_kp_evergreen( (string) $r->post_type ) ) {
			continue;
		}
		$url = (string) get_permalink( (int) $r->ID );
		if ( wp_parse_url( $url, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
			continue;
		}
		$out[ $kw ] = array( (int) $r->ID, $url );
	}
	uksort( $out, fn( $a, $b ) => mb_strlen( $b ) <=> mb_strlen( $a ) );
	set_transient( 'aw_seo_advice_focus_index_v2', $out, 12 * HOUR_IN_SECONDS ); // v2: evergreen, same site
	return $out;
}

/**
 * The panel's data for one item.
 *
 * @param WP_Post $post Item.
 * @return array
 */
function aw_seo_content_advice( WP_Post $post ) {
	$id    = (int) $post->ID;
	$body  = aw_seo_advice_text( $post );
	$lower = mb_strtolower( $body['text'] );
	$out   = array(
		'focus'     => array(),
		'queries'   => array(),
		'links'     => array(),
		'shape'     => aw_seo_advice_shape( $body['html'] ),
		'typical'   => aw_seo_advice_type_median( $post->post_type ),
		'questions' => array(),
		'insights'  => function_exists( 'aw_si_ready' ) && aw_si_ready(),
		'link_kws'  => function_exists( 'aw_link_panel_data' ) ? aw_link_panel_data( $post ) : null, // internal-links.php
	);

	// 1. Focus keyphrases and who else has them.
	$focus = array_filter( array_map( 'trim', explode( ',', (string) get_post_meta( $id, 'rank_math_focus_keyword', true ) ) ) );
	foreach ( array_values( $focus ) as $i => $kw ) {
		$owners = array();
		if ( 0 === $i && function_exists( 'aw_seo_keyphrase_owners' ) ) {
			foreach ( aw_seo_keyphrase_owners( $kw, $id ) as $o ) {
				$owners[] = 'term' === $o['type']
					? array(
						'title' => (string) ( get_term( $o['id'] )->name ?? '' ),
						'edit'  => (string) get_edit_term_link( $o['id'] ),
					)
					: array(
						'title' => html_entity_decode( wp_strip_all_tags( get_the_title( $o['id'] ) ), ENT_QUOTES, 'UTF-8' ),
						'edit'  => (string) get_edit_post_link( $o['id'], 'raw' ),
					);
			}
		}
		$out['focus'][] = array(
			'phrase'  => $kw,
			'primary' => 0 === $i,
			'in_text' => '' !== $kw && false !== mb_strpos( $lower, mb_strtolower( $kw ) ),
			'owners'  => $owners,
		);
	}

	// 2. Search queries this page was shown for (Search Console, 28 days).
	if ( $out['insights'] && function_exists( 'aw_si_object_path' ) ) {
		$path = aw_si_object_path( 'post', $id );
		if ( '' !== $path ) {
			global $wpdb;
			$o    = aw_si_table( 'obs' );
			$k    = aw_si_table( 'keywords' );
			$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Search Insights' own tables; editor only.
				$wpdb->prepare(
					"SELECT k.label, SUM( o.clicks ) AS clicks, SUM( o.impr ) AS impr, SUM( o.pos * o.impr ) / NULLIF( SUM( o.impr ), 0 ) AS pos FROM %i o JOIN %i k ON k.id = o.kw_id WHERE o.src = 'gsc' AND o.metric = 'qp' AND o.page_key = %s AND o.d >= %s GROUP BY o.kw_id ORDER BY impr DESC LIMIT 15",
					$o,
					$k,
					md5( $path ),
					gmdate( 'Y-m-d', time() - 28 * DAY_IN_SECONDS )
				)
			);
			foreach ( $rows as $r ) {
				$label            = (string) $r->label;
				$out['queries'][] = array(
					'query'   => $label,
					'clicks'  => (int) $r->clicks,
					'impr'    => (int) $r->impr,
					'pos'     => null === $r->pos ? null : round( (float) $r->pos, 1 ),
					'in_text' => false !== mb_strpos( $lower, mb_strtolower( $label ) ),
				);
			}
		}
	}

	// 3. Internal links: pages whose keyphrase is in this text, not linked yet.
	$linked = array();
	if ( preg_match_all( '/href=["\']([^"\']+)["\']/i', $body['html'], $m ) ) {
		foreach ( $m[1] as $href ) {
			$linked[ untrailingslashit( strtok( $href, '?#' ) ) ] = true;
		}
	}
	// With the Internal links module's keyword index built, the advice reads that one index (link keywords of the
	// linked types, longest first); without it, the pages' primary focus keyphrases (aw_seo_advice_focus_index()).
	$index = function_exists( 'aw_link_index' ) ? aw_link_index() : array();
	if ( $index ) {
		$words = ' ' . AW_Linker::normalize( $body['text'] ) . ' ';
		uksort( $index, fn( $a, $b ) => mb_strlen( (string) $b ) <=> mb_strlen( (string) $a ) );
		$focus_index = array();
		foreach ( $index as $kw => $cands ) {
			if ( false === mb_strpos( $words, ' ' . $kw . ' ' ) ) {
				continue; // whole words only, as the auto-linker
			}
			foreach ( $cands as $c ) {
				if ( (int) $c[0] !== $id ) {
					$focus_index[ (string) $kw ] = array( (int) $c[0], (string) get_permalink( (int) $c[0] ) );
					break;
				}
			}
			if ( count( $focus_index ) >= 20 ) {
				break;
			}
		}
	} else {
		$focus_index = aw_seo_advice_focus_index();
	}
	foreach ( $focus_index as $kw => $target ) {
		if ( count( $out['links'] ) >= 10 ) {
			break;
		}
		if ( $target[0] === $id || isset( $linked[ untrailingslashit( $target[1] ) ] ) || ( ! $index && false === mb_strpos( $lower, $kw ) ) ) {
			continue;
		}
		$out['links'][] = array(
			'phrase' => $kw,
			'title'  => html_entity_decode( wp_strip_all_tags( get_the_title( $target[0] ) ), ENT_QUOTES, 'UTF-8' ),
			'url'    => $target[1],
		);
	}

	// 4. Automatic FAQ questions for this item.
	if ( function_exists( 'aw_faq_context' ) && function_exists( 'aw_faq_auto' ) ) {
		$ctx = aw_faq_context( $post );
		if ( $ctx ) {
			foreach ( aw_faq_auto( $ctx ) as $q ) {
				$out['questions'][] = array(
					'q'      => wp_strip_all_tags( (string) $q['q'] ),
					'hidden' => (string) $q['hidden'],
				);
			}
		}
	}
	return $out;
}

// The panel in the block editor, for items that have Rank Math's box.
add_action(
	'enqueue_block_editor_assets',
	function () {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'post' !== $screen->base || ! is_post_type_viewable( (string) $screen->post_type ) ) {
			return;
		}
		$file = dirname( __DIR__ ) . '/assets/content-panel.js';
		wp_enqueue_script(
			'aw-seo-content-panel',
			plugins_url( 'assets/content-panel.js', __DIR__ ),
			array( 'wp-plugins', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-i18n', 'wp-url' ),
			(string) filemtime( $file ),
			true
		);
		wp_set_script_translations( 'aw-seo-content-panel', 'aw-seo-extras' );
	}
);
