<?php
/**
 * Rank Math's content analysis on what visitors see.
 *
 * Pages built at run time (hub Pages, Home, and Pages made of shortcodes, patterns, synced blocks or dynamic blocks)
 * hold almost no text of their own, so Rank Math's analyzer found no words, links or pictures in them. For those
 * Pages the analyzer reads the published page's <main> instead (forms, scripts, styles and icons removed):
 * - in the editor, through Rank Math's own JS filter `rank_math_content` (wp.hooks), as its ACF integration does;
 * - in Rank Math → Status & Tools → Database Tools → "Update SEO Scores", through `rank_math/recalculate_score/data`.
 * Pages written as plain text (Privacy Policy, Terms, About…) are analysed on their own text, as before.
 *
 * Library items, Resources, campaigns, News, Opportunities and Directory listings are always analysed on their rendered
 * <main>: their pages are built from the summary, the source link, the facts, the contact details and the categories,
 * which post_content alone does not hold. They are short by nature, so for them the tests that do not fit a summary
 * are removed (600+ words, keyword density, keyword in a subheading, table of contents),
 * through Rank Math's `rank_math/researches/tests`. Rank Math's own "Update SEO Scores" tool reads that filter once
 * per batch, for the batch's first post, so stored scores for these types are worked out item by item instead
 * (the same analyzer, the filter applied per item).
 *
 * The rendered copy is cached for 12 hours and dropped when the Page is saved.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Whether a Page's visible content is built at run time rather than written in the Page itself. */
function aw_seo_page_is_built( $post ) {
	$post = get_post( $post );
	if ( ! $post || 'page' !== $post->post_type ) {
		return false;
	}
	if ( (int) get_option( 'page_on_front' ) === $post->ID || 'page-hub' === get_page_template_slug( $post ) ) {
		return true;
	}
	return (bool) preg_match( '/<!-- wp:(?:shortcode|pattern|block \{"ref"|animal-writes\/(?!breadcrumb)|kits\/)|\[aw_/', (string) $post->post_content );
}

/**
 * The <main> of a published Page as visitors see it, without forms, scripts, styles and icons.
 * '' when the Page is not published, redirects elsewhere or cannot be fetched.
 *
 * @param int  $id    Page ID.
 * @param bool $fresh Fetch again instead of using the cached copy.
 * @return string
 */
function aw_seo_rendered_main( $id, $fresh = false ) {
	$id  = (int) $id;
	$key = 'aw_seo_rendered_' . $id;
	if ( ! $fresh ) {
		$hit = get_transient( $key );
		if ( false !== $hit ) {
			return (string) $hit;
		}
	}
	$html = '';
	if ( 'publish' === get_post_status( $id ) ) {
		$res = wp_remote_get( get_permalink( $id ), array( 'timeout' => 30, 'redirection' => 0, 'user-agent' => 'aw-seo-analysis' ) );
		if ( ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res ) && preg_match( '#<main\b[^>]*>(.*)</main>#is', (string) wp_remote_retrieve_body( $res ), $m ) ) {
			$html = preg_replace( '#<(script|style|svg|noscript|template|form|select|textarea|button)\b.*?</\1>#is', '', $m[1] );
			$html = trim( preg_replace( '/\s+/', ' ', (string) $html ) );
		}
	}
	set_transient( $key, $html, 12 * HOUR_IN_SECONDS );
	return $html;
}

/** Post types analysed on their rendered <main> whatever their content (Library, Resources, campaigns, News, Opportunities, Directory). */
const AW_SEO_RENDERED_TYPES = array( 'aw_legal', 'post', 'aw_campaign', 'aw_news', 'aw_opportunity', 'aw_listing' );

/** Rank Math tests that do not fit a summary page or a profile (the AW_SEO_RENDERED_TYPES). */
const AW_SEO_SUMMARY_SKIP_TESTS = array( 'lengthContent', 'keywordDensity', 'keywordInSubheadings', 'contentHasTOC' );

/** The content Rank Math should analyse: the rendered <main> of a built Page or an item of AW_SEO_RENDERED_TYPES, else ''. */
function aw_seo_analysis_content( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	return ( in_array( $post->post_type, AW_SEO_RENDERED_TYPES, true ) || aw_seo_page_is_built( $post ) ) ? aw_seo_rendered_main( $post->ID ) : '';
}

add_action(
	'save_post',
	function ( $id, $post ) {
		if ( 'page' === $post->post_type || in_array( $post->post_type, AW_SEO_RENDERED_TYPES, true ) ) {
			delete_transient( 'aw_seo_rendered_' . (int) $id );
		}
	},
	10,
	2
);

// Tests that do not fit a summary page are not run on Library, Resources, campaign, News, Opportunity and Directory items.
add_filter(
	'rank_math/researches/tests',
	function ( $tests, $type ) {
		if ( 'post' === $type && in_array( get_post_type(), AW_SEO_RENDERED_TYPES, true ) ) {
			$tests = array_diff_key( $tests, array_flip( AW_SEO_SUMMARY_SKIP_TESTS ) );
		}
		return $tests;
	},
	20,
	2
);

// Rank Math's "Update SEO Scores" tool.
add_filter(
	'rank_math/recalculate_score/data',
	function ( $values, $post_id ) {
		$html = aw_seo_analysis_content( $post_id );
		if ( '' !== $html ) {
			$values['content'] = $html;
		}
		return $values;
	},
	10,
	2
);

// The editor: Rank Math's own content filter (wp.hooks `rank_math_content`).
add_action(
	'rank_math/admin/editor_scripts',
	function () {
		$post = get_post();
		$html = $post ? aw_seo_analysis_content( $post ) : '';
		if ( '' === $html ) {
			return;
		}
		wp_register_script( 'aw-seo-analysis', '', array( 'wp-hooks', 'rank-math-analyzer' ), '1', true );
		wp_enqueue_script( 'aw-seo-analysis' );
		wp_add_inline_script(
			'aw-seo-analysis',
			'(function(){var html=' . wp_json_encode( $html ) . ';wp.hooks.addFilter("rank_math_content","aw-seo-extras/rendered",function(){return html;},20);'
			. 'var n=0,t=setInterval(function(){if(window.rankMathEditor&&rankMathEditor.refresh){rankMathEditor.refresh("content");clearInterval(t);}else if(++n>40){clearInterval(t);}},250);})();'
		);
	}
);

/*
 * Stored scores for new items, every day (owner, 17 Oct 2026). Rank Math works its score out only in a browser, so the
 * workers (animal-writes-workers, seo-scores workflow, after the daily focus keywords) do what its "Update SEO Scores"
 * tool does, unattended: they read the analyzer values below (built by Rank Math's own Update_Score tool, so with the
 * rendered <main> from the filter above, and each item's own test list), run Rank Math's bundled analyzer.js on them
 * and send the numbers back. Only published items with a focus keyword and no stored score; Media & PR items have no
 * page of their own (they 301 to /press-coverage/) and are not scored.
 */

/** Published items with a page and a focus keyword but no stored Rank Math score (newest first). */
function aw_seo_unscored_ids( $limit ) {
	global $wpdb;
	$types = array_values( array_diff( AW_SEO_ITEM_TYPES, array( 'aw_press' ) ) );
	$in    = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders
	return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} k ON k.post_id = p.ID AND k.meta_key = 'rank_math_focus_keyword' AND k.meta_value <> '' LEFT JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = 'rank_math_seo_score' WHERE p.post_status = 'publish' AND p.post_type IN ($in) GROUP BY p.ID HAVING COALESCE( MAX( s.meta_value ), '' ) = '' ORDER BY p.ID DESC LIMIT %d", array_merge( $types, array( (int) $limit ) ) ) ) );
}

/**
 * Rank Math's analyzer values for the given items, as its Update SEO Scores tool builds them, plus each item's own
 * test list (rank_math/researches/tests) and the settings the analyzer reads.
 *
 * @param int[] $ids Item IDs.
 * @return array{rankMath: array, posts: array}
 */
function aw_seo_score_values( $ids ) {
	if ( ! $ids ) {
		return array( 'rankMath' => new stdClass(), 'posts' => new stdClass() );
	}
	if ( ! class_exists( '\RankMath\Tools\Update_Score' ) ) {
		require_once WP_PLUGIN_DIR . '/seo-by-rank-math/includes/modules/database-tools/class-update-score.php';
	}
	$GLOBALS['post'] = get_post( $ids[0] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	$tool            = \RankMath\Tools\Update_Score::get();
	$tool->screen->localize();
	$ref = new ReflectionProperty( get_class( rank_math()->json ), 'data' );
	$ref->setAccessible( true );
	$json = (array) $ref->getValue( rank_math()->json );
	$rm   = array_intersect_key( (array) ( $json['rankMath'] ?? array() ), array_flip( array( 'assessor', 'links', 'localeFull', 'noFollowDomains', 'noFollowExcludeDomains', 'noFollowExternalLinks', 'parentDomain', 'objectType', 'postType' ) ) );
	$only = function ( $q ) use ( $ids ) {
		$q->set( 'post_type', array_values( array_diff( AW_SEO_ITEM_TYPES, array( 'aw_press' ) ) ) );
		$q->set( 'post__in', $ids );
		$q->set( 'posts_per_page', count( $ids ) );
		$q->set( 'offset', 0 );
		$q->set( 'post_status', 'publish' );
	};
	add_action( 'pre_get_posts', $only );
	$req = new WP_REST_Request();
	$req->set_param( 'args', array( 'update_all_scores' => 1, 'offset' => 0 ) );
	$data = ( new \RankMath\Tools\Update_Score() )->update_seo_score( '', $req );
	remove_action( 'pre_get_posts', $only );
	$base = (array) ( $rm['assessor']['researchesTests'] ?? array() );
	$rows = array();
	foreach ( is_array( $data ) ? $data : array() as $id => $v ) {
		$GLOBALS['post'] = get_post( (int) $id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		setup_postdata( $GLOBALS['post'] );
		$v['tests']        = array_values( array_keys( apply_filters( 'rank_math/researches/tests', array_fill_keys( array_unique( array_merge( $base, AW_SEO_SUMMARY_SKIP_TESTS ) ), true ), 'post' ) ) );
		$rows[ (int) $id ] = $v;
	}
	wp_reset_postdata();
	return array( 'rankMath' => $rm, 'posts' => $rows ? $rows : new stdClass() );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'aw/v1',
			'/seo-score-items',
			array(
				'methods'             => 'GET',
				'permission_callback' => 'aw_seo_api_allowed',
				'args'                => array( 'limit' => array( 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 50 ) ),
				'callback'            => function ( WP_REST_Request $req ) {
					do_action( 'litespeed_control_set_nocache', 'seo score API' );
					return aw_seo_score_values( aw_seo_unscored_ids( (int) $req['limit'] ) );
				},
			)
		);
		register_rest_route(
			'aw/v1',
			'/seo-scores',
			array(
				'methods'             => 'POST',
				'permission_callback' => 'aw_seo_api_allowed',
				'callback'            => function ( WP_REST_Request $req ) {
					$written = array();
					foreach ( array_slice( (array) $req->get_param( 'scores' ), 0, 100, true ) as $id => $score ) {
						$id = absint( $id );
						if ( ! $id || 'publish' !== get_post_status( $id ) || ! in_array( get_post_type( $id ), AW_SEO_ITEM_TYPES, true ) || ! is_numeric( $score ) || $score < 0 || $score > 100 ) {
							continue;
						}
						update_post_meta( $id, 'rank_math_seo_score', (int) round( $score ) ); // as Rank Math's own updateSeoScore stores it
						$written[] = $id;
					}
					return array( 'written' => $written );
				},
			)
		);
	}
);
