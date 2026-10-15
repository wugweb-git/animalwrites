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

/** The content Rank Math should analyse for a Page: its rendered <main> when built at run time, else ''. */
function aw_seo_analysis_content( $post ) {
	$post = get_post( $post );
	return $post && aw_seo_page_is_built( $post ) ? aw_seo_rendered_main( $post->ID ) : '';
}

add_action( 'save_post_page', fn( $id ) => delete_transient( 'aw_seo_rendered_' . (int) $id ) );

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
