<?php
/**
 * Small front-end display tweaks.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Section pages read "Directory", not "Archives: Directory".
add_filter( 'get_the_archive_title_prefix', '__return_empty_string' );

// Empty sections explain themselves instead of suggesting a search.
add_filter(
	'gettext',
	function ( $translation, $text, $domain ) {
		if ( 'kits-by-wugweb' === $domain && 'Try another search or browse the latest updates.' === $text ) {
			return __( 'New items appear here after review. Know something that belongs here? Write to contact@animalwrites.in.', 'animal-writes-core' );
		}
		return $translation;
	},
	10,
	3
);

// Content is written for India: <html lang="en-IN">.
add_filter(
	'language_attributes',
	function ( $output ) {
		return preg_replace( '/lang="en-US"/', 'lang="en-IN"', $output );
	}
);

/**
 * Cards and single items show when the source published something, not when we added it.
 * Applies to every Post Date block whose post has an aw_source_date.
 */
add_filter(
	'render_block_core/post-date',
	function ( $content, $block, $instance ) {
		$post_id = $instance->context['postId'] ?? get_the_ID();
		$ymd     = $post_id ? (string) get_post_meta( $post_id, 'aw_source_date', true ) : '';
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ymd ) ) {
			return $content;
		}
		$label = function_exists( 'aw_format_date' ) ? aw_format_date( $ymd ) : $ymd;
		$content = preg_replace( '/datetime="[^"]*"/', 'datetime="' . esc_attr( $ymd ) . '"', $content, 1 );
		return preg_replace( '#(<time[^>]*>)(.*?)(</time>)#s', '$1' . esc_html( $label ) . '$3', $content, 1 );
	},
	10,
	3
);

// External links: one arrow, drawn by Kits (kit.js marks them, kit.css draws the icon). The older ↗ text rule was removed.

/**
 * News, Legal Watch and Research lists are ordered by source date (newest first), then by date added.
 * Items without a source date are kept (named meta clause with NOT EXISTS).
 */
add_action(
	'pre_get_posts',
	function ( $q ) {
		if ( is_admin() ) {
			return;
		}
		$types = (array) $q->get( 'post_type' );
		if ( ! $types || array_diff( $types, array( 'aw_news', 'aw_legal' ) ) ) {
			return;
		}
		$key          = in_array( 'aw_legal', $types, true ) ? 'aw_doc_sort' : 'aw_source_date'; // Library: the document's date, or its year (Acts, Rules)
		$mq           = (array) $q->get( 'meta_query' ); // keep the list filters (Publisher, State)
		$mq['aw_src'] = array(
			'relation'    => 'OR',
			'source_date' => array( 'key' => $key, 'compare' => 'EXISTS' ),
			'no_date'     => array( 'key' => $key, 'compare' => 'NOT EXISTS' ),
		);
		$q->set( 'meta_query', $mq );
		$q->set( 'orderby', array( 'source_date' => 'DESC', 'date' => 'DESC' ) );
	}
);

// Homepage: start downloading the hero photo before the body is parsed (largest element on screen).
add_action(
	'wp_head',
	function () {
		if ( ! is_front_page() ) {
			return;
		}
		$post = get_queried_object();
		if ( $post && preg_match( '/<img[^>]+fetchpriority="high"[^>]*>/', (string) $post->post_content, $m ) && preg_match( '/src="([^"]+)"/', $m[0], $src ) ) {
			printf( '<link rel="preload" as="image" href="%s" fetchpriority="high">' . "\n", esc_url( $src[1] ) );
		}
	},
	1
);

// The phone help bar was removed (owner, 8 Oct 2026).

// Empty states sit under the page's h1, so their heading is an h2 (the pattern ships an h3).
add_filter(
	'render_block_core/query-no-results',
	fn( $html ) => preg_replace( array( '/<h3\b/', '/<\/h3>/' ), array( '<h2', '</h2>' ), $html )
);

// Search results list items under the page h1: their titles are h2 (the parent template uses h3).
add_filter(
	'render_block_core/post-title',
	fn( $html ) => is_search() ? preg_replace( array( '/^(\s*)<h3\b/', '/<\/h3>(\s*)$/' ), array( '$1<h2', '</h2>$1' ), $html ) : $html
);

// Lists show published items only, also to signed-in editors (private items such as held duplicates stay out).
add_filter(
	'query_loop_block_query_vars',
	function ( $q ) {
		if ( ! is_admin() ) {
			$q['post_status'] = 'publish';
		}
		return $q;
	},
	99
);

/**
 * Library sort date (aw_doc_sort, Y-m-d): the document's own date, else the year in its title (Acts, Rules: "…, 1960"
 * sorts as 1960-01-01). Kept up to date on save; templates and undated items get 0001-01-01 and sort last.
 */
function aw_doc_sort_sync( $id ) {
	if ( 'aw_legal' !== get_post_type( $id ) ) {
		return;
	}
	$d = (string) get_post_meta( $id, 'aw_source_date', true );
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) && ! has_term( 'template', 'aw_legal_kind', $id ) && preg_match( '/\b(1[89]|20)\d{2}\b/', get_the_title( $id ), $ym ) ) {
		$d = $ym[0] . '-01-01';
	}
	// Every item gets a value (undated ones and templates sort last): rows without the key would sort unpredictably.
	update_post_meta( $id, 'aw_doc_sort', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ? $d : '0001-01-01' );
}
add_action( 'save_post_aw_legal', 'aw_doc_sort_sync', 20 );
foreach ( array( 'added_post_meta', 'updated_post_meta' ) as $aw_hook ) {
	add_action(
		$aw_hook,
		function ( $mid, $id, $key ) {
			if ( 'aw_source_date' === $key ) {
				aw_doc_sort_sync( $id );
			}
		},
		10,
		3
	);
}

