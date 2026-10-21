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
		// The visitor's sort: A–Z stays A–Z; "Oldest first" keeps the source date but ascending.
		$asked = $q->get( 'orderby' );
		if ( is_array( $asked ) && isset( $asked['title'] ) || 'title' === $asked ) {
			return;
		}
		$dir          = is_array( $asked ) && 'ASC' === strtoupper( (string) ( $asked['date'] ?? '' ) ) || 'ASC' === strtoupper( (string) $q->get( 'order' ) ) && 'date' === $asked ? 'ASC' : 'DESC';
		$key          = in_array( 'aw_legal', $types, true ) ? 'aw_doc_sort' : 'aw_source_date'; // Library: the document's date, or its year (Acts, Rules)
		$mq           = (array) $q->get( 'meta_query' ); // keep the list filters (Publisher, State)
		$mq['aw_src'] = array(
			'relation'    => 'OR',
			'source_date' => array( 'key' => $key, 'compare' => 'EXISTS' ),
			'no_date'     => array( 'key' => $key, 'compare' => 'NOT EXISTS' ),
		);
		$q->set( 'meta_query', $mq );
		$q->set( 'orderby', array( 'source_date' => $dir, 'date' => $dir ) );
		$q->set( 'aw_undated_last', true );
	}
);

// The front-page hero preload lives in the theme (functions.php) and in Optimization Detective; this plugin printed a third one
// (and nothing once the hero became a pattern), so it was removed (audit 19, owner 21 Oct 2026).

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
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) && ! has_term( 'template', 'post_tag', $id ) && preg_match( '/\b(1[89]|20)\d{2}\b/', get_the_title( $id ), $ym ) ) {
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


// Undated Library items (templates, sort date 0001-01-01) always come last, whichever way the list is sorted.
add_filter(
	'posts_orderby',
	function ( $orderby, $q ) {
		if ( ! $q->get( 'aw_undated_last' ) || ! $q->meta_query ) {
			return $orderby;
		}
		$c = $q->meta_query->get_clauses();
		$a = $c['source_date']['alias'] ?? '';
		return $a ? "( CAST({$a}.meta_value AS CHAR) = '0001-01-01' ) ASC, " . $orderby : $orderby;
	},
	10,
	2
);

// Research papers keep their summary in the excerpt: the item template shows it as the summary, and the journal and
// DOI in the Sources block (item-head.php), so their body stays empty (owner, 20 Oct 2026).

/** Plain text of a title or name: tags removed, entities decoded, spaces collapsed. */
function aw_seo_plain( $text ) {
	return trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
}
