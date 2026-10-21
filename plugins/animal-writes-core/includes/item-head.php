<?php
/**
 * The item page (owner, 20 Oct 2026): one structure for News, Library (sourced and our own documents), Resources
 * (research and guides) and Opportunities, from one template (single.html); Directory listings use rows 1–4.
 *
 *   1 Breadcrumb   animal-writes/breadcrumb   Home › Section › primary category (the BreadcrumbList is the same trail)
 *   2 Title        core/post-title (H1)
 *   3 Meta row     animal-writes/item-meta    one date, Sponsored, type, place, deadline, status, categories
 *   4 Image        core/post-featured-image
 *   5 Content      core/post-excerpt (summary) + core/post-content (document, findings, guide, description) + Apply;
 *                  FAQ moved last. Ads (Advanced Ads, nothing when it is off): "Content" placements after paragraphs
 *                  3 and 9 (article-incontent-1/2) and the "After Content" placement at its end (article-end)
 *   6 Sources      animal-writes/item-sources publisher / issuer / study / "By Animal Writes", the original, notes,
 *                  Editorial policy and Report a mistake
 *   7 Tags         animal-writes/item-tags    labels that are not the type (no categories)
 *   8 Share        animal-writes/item-share   share.php's one-row bar
 *   9 (ad)         none of its own: the end ad is in row 5
 *  10 More         animal-writes/item-more    at most 2 groups of 3 cards (4:3 picture, type, date, title, one line)
 *
 * Rows print nothing when they have nothing. The blocks are registered with the others (hub-blocks.php), so the Site
 * Editor shows and arranges them. docs/item-template.md describes each one.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Item types that use the one item template. */
const AW_ITEM_TYPES = array( 'aw_news', 'aw_legal', 'post', 'aw_opportunity' );

/**
 * An item's primary category: the one chosen in Rank Math, else its first category.
 *
 * @param int $id Post ID.
 * @return WP_Term|null
 */
function aw_primary_category( $id ) {
	$pid = (int) get_post_meta( $id, 'rank_math_primary_category', true );
	$t   = $pid ? get_term( $pid, 'category' ) : null;
	if ( $t instanceof WP_Term && has_term( $t->term_id, 'category', $id ) ) {
		return $t;
	}
	return aw_first_term( $id, 'category' );
}

/**
 * A summary up to its last full sentence (owner, 20 Oct 2026): a tail cut off mid-sentence ("…of Kerala's Th") is
 * dropped, never cut mid-word. Only the first paragraph is checked (Library summaries carry a note on a line of their
 * own). Text without any full sentence is returned as it is.
 *
 * @param string $text Summary.
 * @return string
 */
function aw_whole_sentences( $text ) {
	$lines = explode( "\n", trim( (string) $text ) );
	$first = trim( $lines[0] );
	if ( '' !== $first && ! preg_match( '/[.!?…]["\'”’)\]]*$/u', $first ) && preg_match_all( '/[.!?…]["\'”’)\]]*(?=\s|$)/u', $first, $m, PREG_OFFSET_CAPTURE ) ) {
		$last     = end( $m[0] );
		$lines[0] = substr( $first, 0, $last[1] + strlen( $last[0] ) );
	}
	return implode( "\n", $lines );
}

/** Whether $id is the page's own item (not a card in a list). */
function aw_item_here( $id = 0 ) {
	$q = get_queried_object_id();
	return $q && is_singular( array_merge( AW_ITEM_TYPES, array( 'aw_listing' ) ) ) && ( ! $id || (int) $id === $q );
}

/** In the block editor (a preview): blocks say what they are instead of showing nothing. */
function aw_item_editor_preview( $label ) {
	return defined( 'REST_REQUEST' ) && REST_REQUEST ? '<p class="aw-block-preview">' . esc_html( $label ) . '</p>' : '';
}

/**
 * What an item is: news | library | own-document | research | guide | opportunity | listing.
 *
 * @param int $id Post ID.
 * @return string
 */
function aw_item_kind( $id ) {
	switch ( get_post_type( $id ) ) {
		case 'aw_news':
			return 'news';
		case 'aw_legal': // a document we wrote (templates) has no original to link to
			return has_term( 'template', 'post_tag', $id ) || '' === (string) get_post_meta( $id, 'aw_source_url', true ) ? 'own-document' : 'library';
		case 'post':
			return has_term( 'research', 'post_tag', $id ) ? 'research' : 'guide';
		case 'aw_opportunity':
			return 'opportunity';
		case 'aw_listing':
			return 'listing';
	}
	return '';
}

/** The tags that say what an item is (Library type, Resource type, Opportunity type, organisation type). */
function aw_item_type_terms( $id ) {
	switch ( get_post_type( $id ) ) {
		case 'aw_legal':
			return function_exists( 'aw_legal_kinds' ) ? aw_legal_kinds( $id ) : array();
		case 'aw_listing':
			return function_exists( 'aw_listing_types' ) ? aw_listing_types( $id ) : array();
		case 'post':
		case 'aw_opportunity':
			return function_exists( 'aw_item_tags' ) ? aw_item_tags( $id ) : array();
	}
	return array();
}

/** One chip for a term: its icon (if it has one), then its name, linked to its page. */
function aw_item_chip( $t ) {
	$icon = 'post_tag' === $t->taxonomy && function_exists( 'aw_tag_icon' ) ? aw_tag_icon( $t->slug ) : '';
	return '<a class="kit-chip" href="' . esc_url( kits_term_url( $t ) ) . '" rel="tag">' . $icon . esc_html( $t->name ) . '</a>';
}

/** The item's date: the original's for sourced items, the day posted for opportunities, else the post date (0 for none). */
function aw_item_date_ts( $id ) {
	if ( 'aw_opportunity' === get_post_type( $id ) ) {
		return (int) get_post_time( 'U', true, $id );
	}
	$d = (string) get_post_meta( $id, 'aw_source_date', true );
	if ( $d && $d > '1900' ) {
		return (int) strtotime( $d . ' 12:00:00' );
	}
	return $d ? 0 : (int) get_post_time( 'U', true, $id ); // 0001-01-01: a template with no date of its own
}

/* ---------- 3. Meta row ---------- */

function aw_block_item_meta( $a, $id ) {
	if ( ! aw_item_here( $id ) ) {
		return aw_item_editor_preview( 'Meta row: date, type, place, deadline, status, categories' );
	}
	$kind = aw_item_kind( $id );
	$row  = '';
	if ( 'listing' !== $kind ) {
		$ts = aw_item_date_ts( $id );
		if ( $ts ) {
			$row .= '<span class="aw-item-date">' . aw_date( $ts ) . '</span>';
		}
		// One date (owner, 20 Oct 2026): News, Library and research the original's publication date, Opportunities the
		// day posted (their deadline follows), guides the day published. Never "Updated"; dateModified stays in the schema.
		if ( get_post_meta( $id, 'aw_sponsored', true ) ) {
			$row .= '<span class="kit-tag aw-item-sponsored">' . esc_html( (string) aw_binding_value( 'sponsored_label', $id ) ) . '</span>';
		}
	}
	$types = aw_item_type_terms( $id );
	foreach ( 'listing' === $kind ? array_slice( $types, 0, 3 ) : $types as $t ) { // a listing: at most 3 types, primary first
		$row .= aw_item_chip( $t );
	}
	if ( in_array( $kind, array( 'opportunity', 'listing' ), true ) && function_exists( 'aw_item_places' ) ) {
		foreach ( aw_item_places( $id ) as $p ) {
			$st    = 'district' === $p->kind && function_exists( 'aw_place_state' ) ? aw_place_state( $p->state ) : null;
			$label = $st ? $p->name . ', ' . $st->name : $p->name;
			$row  .= '<a class="aw-item-place" href="' . esc_url( aw_directory_url( $p->slug ) ) . '"><span class="kit-ico kit-ico--map-pin" aria-hidden="true"></span>' . esc_html( $label ) . '</a>';
		}
	}
	if ( 'opportunity' === $kind ) {
		$d    = (string) aw_binding_value( 'deadline', $id ); // "Apply by {date}", or a Closed tag and the date it closed
		$row .= '' !== $d ? '<span class="aw-item-deadline">' . wp_kses_post( $d ) . '</span>' : '';
	}
	if ( 'listing' === $kind ) {
		$bl = (string) aw_binding_value( 'blacklisted_label', $id );
		if ( '' !== $bl ) {
			$row .= '<span class="kit-tag kit-tag--danger">' . esc_html( $bl ) . '</span>';
		}
		if ( get_post_meta( $id, 'aw_verified', true ) ) { // one status: Needs re-check instead of Verified while it is due
			$on   = (string) get_post_meta( $id, 'aw_verified_on', true );
			$row .= get_post_meta( $id, 'aw_needs_reverify', true )
				? '<span class="kit-tag kit-tag--warning">' . esc_html__( 'Needs re-check', 'animal-writes-core' ) . '</span>'
				/* translators: %s: date. */
				: '<span class="kit-tag kit-tag--success aw-item-verified"><span class="kit-ico kit-ico--check" aria-hidden="true"></span>' . ( $on ? sprintf( esc_html__( 'Verified on %s', 'animal-writes-core' ), esc_html( wp_date( 'j M Y', strtotime( $on . ' 12:00:00' ) ) ) ) : esc_html__( 'Verified', 'animal-writes-core' ) ) . '</span>';
		}
		$until = (string) get_post_meta( $id, 'aw_featured_until', true );
		if ( get_post_meta( $id, 'aw_featured', true ) && ( '' === $until || wp_date( 'Y-m-d' ) <= $until ) ) {
			$row .= '<span class="kit-tag kit-tag--featured">' . esc_html__( 'Featured', 'animal-writes-core' ) . '</span>';
		}
	} else {
		$cats = get_the_terms( $id, 'category' );
		foreach ( ( $cats && ! is_wp_error( $cats ) ) ? $cats : array() as $t ) {
			$row .= aw_item_chip( $t );
		}
	}
	return '' !== $row ? '<div class="aw-item-meta">' . $row . '</div>' : '';
}

/* ---------- 5. Content: summary, body, FAQ last, Ad 1 ---------- */

/** Whether the item shows its summary (guides and opportunities are their own text). */
function aw_item_shows_summary( $id ) {
	return in_array( aw_item_kind( $id ), array( 'news', 'library', 'own-document', 'research' ), true ) && '' !== trim( (string) get_post_field( 'post_excerpt', $id ) );
}

// Guides and opportunities: no summary above the text (their excerpt is for search results and cards).
add_filter(
	'render_block_core/post-excerpt',
	function ( $html, $block, $instance ) {
		$pid = (int) ( $instance->context['postId'] ?? 0 );
		if ( isset( $instance->context['queryId'] ) || ! aw_item_here( $pid ) || 'aw_listing' === get_post_type( $pid ) ) {
			return $html; // a listing's bio is below row 4, unchanged
		}
		return aw_item_shows_summary( $pid ) ? $html : '';
	},
	5,
	3
);

/** Request state: the guide's reference list (moved to Sources) and where Ad 1 went ('' | inline | after). */
function aw_item_state( $key, $value = null ) {
	static $s = array( 'refs' => '', 'in_content' => 0 );
	if ( null !== $value ) {
		$s[ $key ] = $value;
	}
	return $s[ $key ];
}

// Only the item's own Content block is rearranged (not the_content run by search descriptions, feeds or excerpts).
add_filter(
	'pre_render_block',
	function ( $pre, $parsed, $parent ) {
		if ( 'core/post-content' === ( $parsed['blockName'] ?? '' ) && aw_item_here() ) {
			aw_item_state( 'in_content', 1 );
		}
		return $pre;
	},
	10,
	3
);

/**
 * The item's text, rearranged before it is rendered: FAQ blocks last; a guide's "Sources" list moved to the Sources
 * block; Ad 1's place marked at the first block boundary after about 150 words (summary included), before the next H2
 * where the text has headings, never before the first paragraph, never inside a block and never after the FAQ starts.
 */
add_filter(
	'the_content',
	function ( $content ) {
		$id = (int) get_the_ID();
		if ( ! aw_item_state( 'in_content' ) || ! aw_item_here( $id ) || 'aw_listing' === get_post_type( $id ) ) {
			return $content;
		}
		aw_item_state( 'in_content', 0 );
		$blocks = array_values( array_filter( parse_blocks( (string) $content ), fn( $b ) => $b['blockName'] || '' !== trim( (string) $b['innerHTML'] ) ) );
		$faq    = array();
		$body   = array();
		foreach ( $blocks as $b ) {
			if ( 'rank-math/faq-block' === $b['blockName'] ) {
				$prev = end( $body );
				if ( $prev && 'core/heading' === $prev['blockName'] ) { // the FAQ's own heading moves with it
					$faq[] = array_pop( $body );
				}
				$faq[] = $b;
			} else {
				$body[] = $b;
			}
		}
		if ( 'guide' === aw_item_kind( $id ) ) {
			foreach ( $body as $i => $b ) {
				if ( 'core/heading' === $b['blockName'] && preg_match( '/^\s*(sources|references|sources and further reading)\s*$/i', wp_strip_all_tags( $b['innerHTML'] ) ) ) {
					$n = $i + 1;
					while ( isset( $body[ $n ] ) && ( 'core/list' === $body[ $n ]['blockName'] || ( 'core/paragraph' === $body[ $n ]['blockName'] && false === strpos( $body[ $n ]['innerHTML'], 'aw-guide-disclaimer' ) ) ) ) {
						++$n;
					}
					aw_item_state( 'refs', serialize_blocks( array_slice( $body, $i + 1, $n - $i - 1 ) ) );
					array_splice( $body, $i, $n - $i );
					break;
				}
			}
		}
		return serialize_blocks( array_merge( $body, $faq ) );
	},
	8
);

// An item with no text of its own prints no empty content box.
add_filter(
	'render_block_core/post-content',
	function ( $html, $block, $instance ) {
		if ( ! aw_item_here( (int) ( $instance->context['postId'] ?? 0 ) ) || is_singular( 'aw_listing' ) ) {
			return $html;
		}
		return '' === trim( wp_strip_all_tags( $html ) ) && ! preg_match( '#<(img|iframe|object|form|video|table|aside)\b#i', $html ) ? '' : $html;
	},
	40,
	3
);

/* ---------- 6. Sources ---------- */

function aw_block_item_sources( $a, $id ) {
	if ( ! aw_item_here( $id ) ) {
		return aw_item_editor_preview( 'Sources: publisher, issuer or study, the original, notes, Editorial policy, Report a mistake' );
	}
	$kind   = aw_item_kind( $id );
	$links  = '<p class="aw-sources-links"><a href="' . esc_url( home_url( '/editorial-policy/' ) ) . '">' . esc_html__( 'Editorial policy', 'animal-writes-core' ) . '</a><a href="' . esc_url( aw_report_url( $id ) ) . '">' . esc_html__( 'Report a mistake', 'animal-writes-core' ) . '</a></p>';
	$hid    = 'aw-sources-' . $id;
	if ( 'listing' === $kind ) { // the listing's data sources with their "as on" dates, and when it was last updated
		$list = function_exists( 'aw_listing_sources_html' ) ? aw_listing_sources_html( $id ) : '';
		$last = function_exists( 'aw_listing_last_updated' ) ? aw_listing_last_updated( $id ) : '';
		return '<section class="aw-sources" aria-labelledby="' . esc_attr( $hid ) . '"><h2 class="aw-sources-head has-x-large-font-size" id="' . esc_attr( $hid ) . '">' . esc_html__( 'Sources', 'animal-writes-core' ) . '</h2>'
			. ( '' !== $list ? '<div class="aw-sources-list">' . $list . '</div>' : '' )
			. ( '' !== $last ? '<p class="aw-sources-updated">' . esc_html__( 'Last updated:', 'animal-writes-core' ) . ' ' . aw_date( $last ) . '</p>' : '' )
			. $links . '</section>';
	}
	$url    = (string) get_post_meta( $id, 'aw_source_url', true );
	$credit = '';
	$note   = '';
	$read   = $url ? '<a class="aw-sources-read" href="' . esc_url( $url ) . '" target="_blank" rel="noopener nofollow">' . esc_html__( 'Read the original', 'animal-writes-core' ) . ' <span aria-hidden="true">↗</span><span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'animal-writes-core' ) . '</span></a>' : '';
	$logo   = get_site_icon_url( 64 );
	$ours   = '<span class="aw-sources-by">' . ( $logo ? '<img class="aw-sources-logo" src="' . esc_url( $logo ) . '" alt="" width="20" height="20" loading="lazy">' : '' );
	$sumnote = '<span>' . esc_html__( 'Summary written by Animal Writes. Read the original before relying on it.', 'animal-writes-core' ) . '</span>';
	switch ( $kind ) {
		case 'news':
		case 'library':
			$credit = do_shortcode( '[aw_source_row link="1"]' ); // the publisher chip (+ "also reported by" logos, same-story.php)
			$iss    = 'library' === $kind && function_exists( 'aw_item_issuer' ) ? aw_item_issuer( $id ) : null;
			if ( $iss && function_exists( 'aw_publisher_of' ) && aw_publisher_of( $id )[0] !== $iss->name ) {
				/* translators: %s: body that issued the document. */
				$note .= '<span>' . esc_html( sprintf( __( 'Issued by %s.', 'animal-writes-core' ), $iss->name ) ) . '</span>';
			}
			$note .= $sumnote;
			break;
		case 'own-document':
			$credit = $ours . esc_html__( 'Created by Animal Writes', 'animal-writes-core' ) . '</span>';
			$read   = '';
			break;
		case 'research': // "Study: <study title> by <authors> in <journal> · Read the original ↗" (owner, 20 Oct 2026)
			$journal = (string) ( get_post_meta( $id, 'aw_journal', true ) ?: get_post_meta( $id, 'aw_source_name', true ) );
			$authors = function_exists( 'aw_research_authors_text' ) ? aw_research_authors_text( $id ) : '';
			$paper   = function_exists( 'aw_research_study_title' ) ? aw_research_study_title( $id ) : '';
			$study   = '' !== $paper ? '<cite class="aw-sources-study-title">' . esc_html( $paper ) . '</cite>' : '';
			if ( '' !== $authors ) {
				/* translators: %s: authors. */
				$study .= ( '' !== $study ? ' ' : '' ) . sprintf( esc_html__( 'by %s', 'animal-writes-core' ), esc_html( $authors ) );
			}
			if ( '' !== $journal ) {
				/* translators: %s: journal. */
				$study .= ( '' !== $study ? ' ' : '' ) . sprintf( esc_html__( 'in %s', 'animal-writes-core' ), '<cite>' . esc_html( $journal ) . '</cite>' );
			}
			$study  = '' !== $study ? esc_html__( 'Study:', 'animal-writes-core' ) . ' ' . $study : '';
			$credit = '' !== $study . $read ? '<span class="aw-sources-study">' . $study . ( '' !== $study && '' !== $read ? ' <span aria-hidden="true">·</span> ' : '' ) . $read . '</span>' : '';
			$read   = ''; // in the study line
			$note   = $sumnote;
			break;
		case 'guide':
			$by = (string) get_post_meta( $id, 'aw_source_name', true );
			if ( $url && '' !== $by ) { // a Resource taken from another body (WHO fact sheet): their credit and link, not ours
				/* translators: %s: organisation that published the original. */
				$credit = '<span class="aw-sources-study">' . sprintf( esc_html__( 'Source: %s', 'animal-writes-core' ), esc_html( $by ) ) . ' <span aria-hidden="true">·</span> ' . $read . '</span>';
				$read   = '';
				$note   = $sumnote;
				break;
			}
			$credit = $ours . esc_html__( 'By Animal Writes', 'animal-writes-core' ) . '</span>';
			$read   = '';
			break;
		case 'opportunity':
			$credit = function_exists( 'aw_block_posted_by' ) ? str_replace( array( '<p class="aw-posted-by kit-meta-item">', '</p>' ), array( '<span class="aw-posted-by">', '</span>' ), aw_block_posted_by( array(), $id ) ) : '';
			$apply  = (string) get_post_meta( $id, 'aw_apply_url', true );
			$read   = $url && untrailingslashit( $url ) !== untrailingslashit( $apply ) ? $read : '';
			break;
	}
	$refs = 'guide' === $kind ? (string) aw_item_state( 'refs' ) : '';
	$out  = '<section class="aw-sources" aria-labelledby="' . esc_attr( $hid ) . '"><h2 class="aw-sources-head has-x-large-font-size" id="' . esc_attr( $hid ) . '">' . esc_html__( 'Sources', 'animal-writes-core' ) . '</h2>';
	$out .= '' !== $credit ? '<div class="aw-sources-credit">' . $credit . '</div>' : '';
	$out .= '' !== $read . $note ? '<p class="aw-sources-note">' . $read . $note . '</p>' : '';
	$out .= '' !== $refs ? '<div class="aw-sources-refs">' . do_blocks( $refs ) . '</div>' : '';
	$out .= $links;
	return $out . '</section>';
}

/* ---------- 7. Tags ---------- */

function aw_block_item_tags( $a, $id ) {
	if ( ! aw_item_here( $id ) ) {
		return aw_item_editor_preview( 'Tags: labels other than the type (no categories)' );
	}
	$tags = get_the_terms( $id, 'post_tag' );
	$type = wp_list_pluck( aw_item_type_terms( $id ), 'term_id' );
	$out  = '';
	foreach ( ( $tags && ! is_wp_error( $tags ) ) ? $tags : array() as $t ) {
		$out .= in_array( $t->term_id, $type, true ) ? '' : aw_item_chip( $t );
	}
	return '' !== $out ? '<div class="kit-tags aw-item-tags">' . $out . '</div>' : '';
}

/* ---------- 8. Share ---------- */

function aw_block_item_share( $a, $id ) {
	if ( ! aw_item_here( $id ) ) {
		return aw_item_editor_preview( 'Share: X, Facebook, LinkedIn, WhatsApp, Email, Copy link' );
	}
	return function_exists( 'aw_share_html' ) ? '<div class="aw-item-share">' . aw_share_html( $id ) . '</div>' : '';
}

/* ---------- 10. More ---------- */

/**
 * At most two groups of three: [heading, IDs] (owner, 20 Oct 2026). Cached for 12 hours, like the related lists.
 *
 * @param int $id Item.
 * @return array
 */
function aw_item_more_groups( $id ) {
	$key    = 'aw_more_' . (int) $id;
	$groups = get_transient( $key );
	if ( is_array( $groups ) ) {
		return $groups;
	}
	$cat = aw_primary_category( $id );
	$in  = $cat ? array( 'category__in' => array( (int) $cat->term_id ) ) : array();
	/* translators: %s: category. */
	$more   = $cat ? sprintf( __( 'More in %s', 'animal-writes-core' ), $cat->name ) : '';
	$groups = array();
	switch ( get_post_type( $id ) ) {
		case 'aw_news':
			$groups[] = array( $more ?: __( 'More news', 'animal-writes-core' ), aw_related_pick( $id, 'aw_news', 3, array(), $in ) );
			$groups[] = array( __( 'From the Library', 'animal-writes-core' ), aw_related_pick( $id, 'aw_legal', 3, aw_related_set( $id, 'aw_rel_legal' ) ) );
			break;
		case 'aw_legal':
			$groups[] = array( $more ?: __( 'More from the Library', 'animal-writes-core' ), aw_related_pick( $id, 'aw_legal', 3, array(), $in ) );
			$groups[] = array( __( 'News about this', 'animal-writes-core' ), aw_related_pick( $id, 'aw_news', 3, aw_related_back( $id, 'aw_rel_legal', 'aw_news' ) ) );
			break;
		case 'post':
			$t        = aw_item_type_terms( $id );
			$plural   = $t ? ( 'research' === $t[0]->slug ? __( 'More research', 'animal-writes-core' ) : sprintf( /* translators: %s: Resource type. */ __( 'More %s', 'animal-writes-core' ), mb_strtolower( $t[0]->name ) . 's' ) ) : __( 'More resources', 'animal-writes-core' );
			$groups[] = array( $plural, aw_related_pick( $id, 'post', 3, array(), $t ? array( 'tag__in' => array( (int) $t[0]->term_id ) ) : array() ) );
			$groups[] = array( __( 'From the Library', 'animal-writes-core' ), aw_related_pick( $id, 'aw_legal', 3, aw_related_set( $id, 'aw_rel_resource' ) ) );
			break;
		case 'aw_opportunity':
			$open     = function_exists( 'aw_open_opportunities_meta_query' ) ? array( 'meta_query' => aw_open_opportunities_meta_query() ) : array(); // phpcs:ignore WordPress.DB.SlowDBQuery
			$groups[] = array( __( 'More opportunities', 'animal-writes-core' ), aw_related_pick( $id, 'aw_opportunity', 3, array(), $open ) );
			break;
		case 'aw_listing': // its district first, filled from the state up to 6 (same type in the nearest districts first)
			$p = function_exists( 'aw_item_place' ) ? aw_item_place( $id ) : null;
			if ( $p ) {
				$local = aw_related_pick( $id, 'aw_listing', 6, array(), array( 'orderby' => 'title', 'order' => 'ASC', 'posts_per_page' => 300 ), true );
				$fill  = 'district' === $p->kind && count( $local ) < 6 ? aw_related_state_fill( $id, $p, 6 - count( $local ), $local ) : array();
				/* translators: %s: district or state. */
				$groups[] = array( sprintf( __( 'More organisations in %s', 'animal-writes-core' ), $p->name ), array_merge( $local, $fill ) );
			}
			break;
	}
	$groups = array_slice( array_values( array_filter( $groups, fn( $g ) => ! empty( $g[1] ) ) ), 0, 2 );
	set_transient( $key, $groups, 12 * HOUR_IN_SECONDS );
	return $groups;
}
add_action( 'save_post', fn( $id ) => delete_transient( 'aw_more_' . (int) $id ) );

/**
 * A card's 4:3 picture through core's Post Featured Image block, so it gets the cards' own handling (the item's
 * picture, else its primary category's fallback; never an unrelated stock photo).
 */
function aw_item_card_media( $pid ) {
	$b = new WP_Block(
		array( 'blockName' => 'core/post-featured-image', 'attrs' => array( 'aspectRatio' => '4/3', 'scale' => 'cover', 'sizeSlug' => 'medium_large', 'className' => 'kit-story-media aw-more-media' . ( 'aw_opportunity' === get_post_type( $pid ) ? ' aw-more-media--logo' : '' ) ), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ),
		array( 'postId' => (int) $pid, 'postType' => get_post_type( $pid ) )
	);
	return (string) $b->render();
}

/** A small card: 4:3 picture, type and date, title, one line (no category chips). */
function aw_item_more_card( $pid ) {
	$type = aw_item_type_terms( $pid );
	$name = $type ? $type[0]->name : ( 'aw_news' === get_post_type( $pid ) ? __( 'News', 'animal-writes-core' ) : '' );
	if ( 'aw_listing' === get_post_type( $pid ) ) { // an organisation: its logo whole in the 4:3 box (or its initials), type and place
		$logo  = get_the_post_thumbnail( $pid, 'medium', array( 'alt' => '', 'loading' => 'lazy' ) );
		$words = array_values( array_filter( preg_split( '/\s+/', preg_replace( '/[^\p{L}\s]/u', '', get_the_title( $pid ) ) ) ) );
		$mark  = $logo ? $logo : '<span class="aw-more-initials" aria-hidden="true">' . esc_html( mb_strtoupper( mb_substr( $words[0] ?? '', 0, 1 ) . mb_substr( $words[1] ?? '', 0, 1 ) ) ) . '</span>';
		$place = function_exists( 'aw_item_place' ) ? aw_item_place( $pid ) : null;
		$line  = wp_strip_all_tags( (string) get_the_excerpt( $pid ) );
		return '<li><article class="kit-card aw-more-card aw-more-card--org"><div class="aw-more-media aw-more-logo">' . $mark . '</div><div class="aw-more-body"><p class="aw-more-meta kit-meta-row">' . ( '' !== $name ? '<span class="aw-more-type">' . esc_html( $name ) . '</span>' : '' ) . ( $place ? '<span class="kit-meta-item">' . aw_i( 'map-pin' ) . esc_html( $place->name ) . '</span>' : '' ) . '</p>'
			. '<h3 class="aw-more-title"><a href="' . esc_url( get_permalink( $pid ) ) . '">' . esc_html( get_the_title( $pid ) ) . '</a></h3>'
			. ( '' !== $line ? '<p class="aw-more-line">' . esc_html( $line ) . '</p>' : '' ) . '</div></article></li>';
	}
	$ts   = aw_item_date_ts( $pid );
	$line = wp_strip_all_tags( (string) get_the_excerpt( $pid ) );
	return '<li><article class="kit-card aw-more-card">' . aw_item_card_media( $pid ) . '<div class="aw-more-body"><p class="aw-more-meta kit-meta-row">' . ( '' !== $name ? '<span class="aw-more-type">' . esc_html( $name ) . '</span>' : '' ) . ( $ts ? aw_date( $ts ) : '' ) . '</p>'
		. '<h3 class="aw-more-title"><a href="' . esc_url( get_permalink( $pid ) ) . '">' . esc_html( get_the_title( $pid ) ) . '</a></h3>'
		. ( '' !== $line ? '<p class="aw-more-line">' . esc_html( $line ) . '</p>' : '' ) . '</div></article></li>';
}

function aw_block_item_more( $a, $id ) {
	if ( ! aw_item_here( $id ) || 'publish' !== get_post_status( $id ) ) {
		return aw_item_editor_preview( 'More: up to two groups of three items (listings: one group of up to six organisations)' );
	}
	$out = '';
	foreach ( aw_item_more_groups( $id ) as $n => $g ) {
		$hid  = 'aw-more-' . $n;
		$out .= '<section class="aw-more-group" aria-labelledby="' . $hid . '"><h2 class="aw-more-head has-x-large-font-size" id="' . $hid . '">' . esc_html( $g[0] ) . '</h2><ul class="aw-more-list">' . implode( '', array_map( 'aw_item_more_card', $g[1] ) ) . '</ul></section>';
	}
	return '' !== $out ? '<div class="aw-more">' . $out . '</div>' : '';
}

/* ---------- Library: contents list ---------- */

/**
 * Library document text with 5 or more headings: a collapsible contents list before it (owner, 20 Oct 2026). Headings
 * without an id get one, so the list can link to them.
 */
add_filter(
	'render_block_core/post-content',
	function ( $html ) {
		static $done = false;
		if ( $done || ! is_singular( 'aw_legal' ) || get_the_ID() !== get_queried_object_id() ) {
			return $html;
		}
		$done = true;
		$p    = new WP_HTML_Tag_Processor( $html );
		$list = array();
		$used = array();
		while ( $p->next_tag() ) {
			if ( ! in_array( $p->get_tag(), array( 'H2', 'H3' ), true ) ) {
				continue;
			}
			$level = $p->get_tag();
			$id    = (string) $p->get_attribute( 'id' );
			$p->set_bookmark( 'h' );
			$text = '';
			while ( $p->next_token() ) {
				if ( '#tag' === $p->get_token_type() && $level === $p->get_tag() && $p->is_tag_closer() ) {
					break;
				}
				if ( '#text' === $p->get_token_type() ) {
					$text .= $p->get_modifiable_text();
				}
			}
			$text = trim( wp_strip_all_tags( $text ) );
			if ( '' === $text ) {
				continue;
			}
			if ( '' === $id ) {
				$id = sanitize_title( $text );
				$id = isset( $used[ $id ] ) ? $id . '-' . ( ++$used[ $id ] ) : $id;
				$p->seek( 'h' );
				$p->set_attribute( 'id', $id );
			}
			$used[ $id ] = $used[ $id ] ?? 1;
			$list[]      = array( $level, $id, $text );
		}
		if ( count( $list ) < 5 ) {
			return $html;
		}
		$items = '';
		foreach ( $list as list( $level, $id, $text ) ) {
			$items .= '<li' . ( 'H3' === $level ? ' class="is-sub"' : '' ) . '><a href="#' . esc_attr( $id ) . '">' . esc_html( $text ) . '</a></li>';
		}
		return '<details class="kit-toc aw-doc-toc"><summary>' . esc_html__( 'Contents', 'animal-writes-core' ) . '</summary><ol>' . $items . '</ol></details>' . $p->get_updated_html();
	},
	5
);

