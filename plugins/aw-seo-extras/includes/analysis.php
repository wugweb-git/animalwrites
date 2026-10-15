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
