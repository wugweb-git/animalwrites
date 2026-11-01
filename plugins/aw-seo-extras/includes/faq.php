<?php
/**
 * FAQ system (owner-approved spec, 20 Oct 2026; every page, 31 Oct 2026). One FAQ per page, 3 to 6 questions, or none.
 *
 * Every public post type and every public taxonomy gets the FAQ box on its edit screen (AW SEO Plus → FAQ → "FAQ box
 * on": all ticked by default, untick a type to leave it out). A page shows its FAQ when the box holds enough questions to show (the minimum: 3;
 * posts 2).
 *
 * Two kinds of question in the box:
 * - Automatic: built from the item's own fields by a registry (filter aw_faq_registry: post type => entries; each entry a
 *   question template, an answer builder, a priority). The site's registry lives in the site's own plugin. The box shows
 *   them filled in; untouched they follow the data, edited they are the page's own ("edited") and regeneration leaves
 *   them alone. Removing or reordering them is kept too.
 * - Own: written in the box (or proposed as drafts by an import): question, answer, sources, keyword, status
 *   (draft / approved), last reviewed. Only approved ones are shown.
 *
 * Storage (meta aw_faq on the post or term, JSON list, in the box's order): own rows {q, a, sources, keyword, status,
 * reviewed}; automatic rows {auto: id, state: auto | edited | removed} (+ q, a when edited). No automatic rows stored =
 * the default order: automatic by priority, then own.
 *
 * aw_faq_rows() is what the box shows (with why a row is not shown); aw_faq_build() is what the page shows: approved and
 * automatic rows, duplicates of the same fact dropped, at most 6, none when fewer than the minimum. The accordion is
 * rendered once per request and the FAQPage node (schema.php) is parsed from that HTML, so the schema text is the
 * visible text word for word. No page shows a second FAQ: a page with a Rank Math FAQ block keeps that one.
 *
 * Positions: the item-faq block where the template has it; else after the page's content (page 1, no filters).
 * Term pages: the site's term-faq block.
 *
 * WP-CLI: wp aw-faq report | check | import <file> | convert-guides | undo-guide. docs/faq.md describes it all.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/admin-table.php'; // aw_seo_extras_simple_table() for AW SEO Plus → FAQ
require_once __DIR__ . '/faq-overview.php'; // AW SEO Plus → FAQ

// AW_FAQ_MIN, AW_FAQ_MIN_GUIDE, AW_FAQ_MAX, AW_FAQ_WORDS, AW_FAQ_HUBS: AW SEO Plus → Settings (settings.php).

/** Tags allowed in an answer written in the box (the page and the schema print the same HTML). */
const AW_FAQ_ANSWER_HTML = array(
	'a'      => array( 'href' => true ),
	'strong' => array(),
	'em'     => array(),
);

/** The block that places the FAQ in an item template; a template without it gets the FAQ after the content. */
const AW_FAQ_ITEM_BLOCK = 'animal-writes/item-faq';

/**
 * Fewest questions a page shows (and marks up): AW_FAQ_MIN, posts AW_FAQ_MIN_GUIDE (owner, 21 and 25 Oct 2026: 2 for
 * Resources guides).
 *
 * @param array|null $ctx aw_faq_context().
 * @return int
 */
function aw_faq_min( $ctx ) {
	return (int) apply_filters( 'aw_faq_min', is_array( $ctx ) && 'post' === ( $ctx['type'] ?? '' ) ? AW_FAQ_MIN_GUIDE : AW_FAQ_MIN, $ctx );
}

/* ---------- Which types have the box ---------- */

/**
 * Post types and taxonomies without the FAQ box: the ones unticked on AW SEO Plus → FAQ (option aw_faq_off_types), so a
 * type registered later has the box. Read straight from the option: aw_faq_taxonomies() runs on admin_init.
 *
 * @return string[]
 */
function aw_faq_off_types() {
	static $off = null;
	if ( null !== $off ) {
		return $off;
	}
	$off = get_option( 'aw_faq_off_types', null );
	if ( null === $off ) { // once: the former Settings → FAQ list "Types without the FAQ box"
		$old = get_option( 'aw_seo_settings', array() );
		$old = is_array( $old ) ? (array) ( $old['faq']['AW_FAQ_OFF_TYPES'] ?? array() ) : array();
		$off = array_values( array_unique( array_filter( array_map( 'sanitize_key', $old ) ) ) );
		if ( $off ) {
			update_option( 'aw_faq_off_types', $off, false );
		}
	}
	$off = array_values( array_filter( array_map( 'sanitize_key', (array) $off ) ) );
	return $off;
}

/** Post types with the FAQ box: every public type, less those unticked on AW SEO Plus → FAQ. */
function aw_faq_post_types() {
	$types = array_diff( array_values( get_post_types( array( 'public' => true ) ) ), array( 'attachment' ), aw_faq_off_types() );
	return array_values( (array) apply_filters( 'aw_faq_post_types', $types ) );
}

/** Taxonomies with the FAQ box: every public taxonomy with an edit screen, less those unticked on AW SEO Plus → FAQ. */
function aw_faq_taxonomies() {
	$tax = array_diff( array_values( get_taxonomies( array( 'public' => true, 'show_ui' => true ) ) ), aw_faq_off_types() );
	return array_values( (array) apply_filters( 'aw_faq_taxonomies', $tax ) );
}

/* ---------- Small helpers ---------- */

/** Plain text of a title, for a sentence. */
function aw_faq_plain( $s ) {
	return trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( (string) $s ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
}

/** A list in a sentence: "a, b and c". */
function aw_faq_and( array $items ) {
	$items = array_values( array_filter( array_map( 'trim', $items ), 'strlen' ) );
	if ( count( $items ) < 2 ) {
		return (string) ( $items[0] ?? '' );
	}
	$last = array_pop( $items );
	return implode( ', ', $items ) . ' and ' . $last;
}

/** A label in a sentence: first letter lower case unless it starts an acronym ("Sterilisation (ABC)" → "sterilisation (ABC)"). */
function aw_faq_lc( $s ) {
	$s = trim( (string) $s );
	return preg_match( '/^\p{Lu}\p{Ll}/u', $s ) ? mb_strtolower( mb_substr( $s, 0, 1 ) ) . mb_substr( $s, 1 ) : $s;
}

/** A link in an answer. */
function aw_faq_a( $url, $text ) {
	return '<a href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a>';
}

/** Words in an answer (tags left out). */
function aw_faq_word_count( $html ) {
	return count( preg_split( '/\s+/u', aw_faq_plain( $html ), -1, PREG_SPLIT_NO_EMPTY ) );
}

/** Pages whose FAQ heading names a subject (Settings → FAQ): page ID => subject. */
function aw_faq_hub_pages() {
	static $out = null;
	if ( null !== $out ) {
		return $out;
	}
	$out = array();
	foreach ( (array) AW_FAQ_HUBS as $slug => $subject ) {
		$p = get_page_by_path( (string) $slug );
		if ( $p ) {
			$out[ (int) $p->ID ] = (string) $subject;
		}
	}
	return $out;
}

/**
 * The subject a post's FAQ heading names ("Questions about <subject>") and where it came from: the FAQ box's Subject
 * field (meta aw_faq_subject), the pages map (Settings → FAQ), the site's name on the front page, else the title.
 *
 * @param WP_Post $post Post.
 * @return array [ subject, 'meta' | 'map' | 'front' | 'title' ]
 */
function aw_faq_subject( $post ) {
	$meta = aw_faq_plain( (string) get_post_meta( $post->ID, 'aw_faq_subject', true ) );
	if ( '' !== $meta ) {
		return array( $meta, 'meta' );
	}
	$hubs = 'page' === $post->post_type ? aw_faq_hub_pages() : array();
	if ( isset( $hubs[ (int) $post->ID ] ) ) {
		return array( $hubs[ (int) $post->ID ], 'map' );
	}
	if ( (int) get_option( 'page_on_front' ) === (int) $post->ID && '' !== aw_faq_plain( get_bloginfo( 'name' ) ) ) {
		return array( aw_faq_plain( get_bloginfo( 'name' ) ), 'front' );
	}
	return array( aw_faq_plain( get_the_title( $post ) ), 'title' );
}

/**
 * The FAQ head's supporting line and its "see all" link (filter aw_faq_head_parts): [ line, url, label ]. The link is
 * left out on the page it points to.
 *
 * @return array
 */
function aw_faq_head_parts() {
	return (array) apply_filters( 'aw_faq_head_parts', array( 'Quick answers. More in the full FAQ.', home_url( '/resources/animal-welfare-faqs/' ), 'All questions' ) );
}

/**
 * Whether a page shows its FAQ after its content here: page 1, and no search or filter in the address (tracking
 * parameters and page 1 of a core Query Loop aside). Filter aw_faq_show_here for the site's own list views.
 *
 * @return bool
 */
function aw_faq_show_here() {
	$show = ! is_paged();
	foreach ( array_keys( $_GET ) as $k ) { // phpcs:ignore WordPress.Security.NonceVerification -- reading which parameters are present only
		if ( preg_match( '/^query-\d+-page$/', (string) $k ) ) { // core Query Loop pagination: page 1 is not a filter
			if ( (int) sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) > 1 ) { // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- a page number, read only
				$show = false;
			}
		} elseif ( ! preg_match( '/^(utm_[a-z]+|gclid|fbclid|nocache|ref|v)$/', (string) $k ) ) {
			$show = false;
		}
	}
	return (bool) apply_filters( 'aw_faq_show_here', $show );
}

/* ---------- The page's FAQ ---------- */

/**
 * The FAQ context of a post or term: kind (the post type or 'term'; the site's filter names its own kinds), store
 * ('post' | 'term'), object ID, type (post type or taxonomy), entity name, URL, the page node's and the main entity's
 * @id, the H2. Null for a type without the box.
 *
 * @param WP_Post|WP_Term|null $obj Default: the queried object.
 * @return array|null
 */
function aw_faq_context( $obj = null ) {
	$obj = null === $obj ? get_queried_object() : $obj;
	$ctx = null;
	if ( $obj instanceof WP_Post && in_array( $obj->post_type, aw_faq_post_types(), true ) ) {
		$url     = (string) get_permalink( $obj );
		$title   = aw_faq_plain( get_the_title( $obj ) );
		$subject = aw_faq_subject( $obj );
		$ctx     = array(
			'kind'            => 'post' === $obj->post_type ? 'guide' : $obj->post_type,
			'store'           => 'post',
			'id'              => (int) $obj->ID,
			'type'            => $obj->post_type,
			'entity'          => $title,
			'full'            => '',
			'url'             => $url,
			'page_id'         => $url . '#webpage',
			'about'           => $url . '#webpage',
			'head'            => 'Questions about ' . $subject[0],
			'subject_missing' => 'title' === $subject[1] && in_array( $obj->post_type, array( 'page', 'post' ), true ),
		);
		if ( 'map' === $subject[1] ) {
			$ctx['kind']   = 'hub';
			$ctx['entity'] = $subject[0];
		}
	} elseif ( $obj instanceof WP_Term && in_array( $obj->taxonomy, aw_faq_taxonomies(), true ) ) {
		$url  = (string) kits_term_url( $obj );
		$name = aw_faq_plain( $obj->name );
		$ctx  = array( 'kind' => 'term', 'store' => 'term', 'id' => (int) $obj->term_id, 'type' => $obj->taxonomy, 'entity' => $name, 'full' => '', 'url' => $url, 'page_id' => $url . '#webpage', 'about' => $url . '#webpage', 'head' => 'Questions about ' . $name, 'subject_missing' => false );
	}
	return null === $ctx ? null : apply_filters( 'aw_faq_context', $ctx, $obj );
}

/**
 * The automatic question registry: post type or taxonomy => entries. Each entry: key, priority (1 = most searched), q
 * (question template, %s = the entity), needs (fields; documentation), pattern (the search it answers), answer
 * (callable: ($id, $entity) → [answer HTML, fact key, counts?], a list of [q, a, fact] for several questions, or null
 * when the fields are empty; $id is the term ID for a taxonomy), question (optional callable: ($q, $id, $entity, $key)
 * → the question), hidden (optional: true, or a callable ($id, $entity) → bool; with hidden_why, the reason shown in the
 * box): a hidden question stays in the FAQ box, marked Hidden, but is never shown on the page or in the schema and does
 * not count toward the minimum. Empty here: the site adds its own.
 *
 * @return array
 */
function aw_faq_registry() {
	return (array) apply_filters( 'aw_faq_registry', array() );
}

/**
 * The automatic questions an item has from its data now, by id (the registry key; one per section for lists).
 *
 * @param array $ctx aw_faq_context().
 * @return array[] id => [ id, q, a, fact, priority, key, counts, answer, hidden (the reason, or '') ]
 */
function aw_faq_auto( $ctx ) {
	$out = array();
	foreach ( aw_faq_registry()[ $ctx['type'] ] ?? array() as $e ) { // keyed by post type or taxonomy
		$r = call_user_func( $e['answer'], $ctx['id'], $ctx['entity'] );
		if ( ! $r ) {
			continue;
		}
		$h      = $e['hidden'] ?? false;
		$hidden = ( is_callable( $h ) ? call_user_func( $h, $ctx['id'], $ctx['entity'] ) : (bool) $h ) ? (string) ( $e['hidden_why'] ?? 'not shown on the page' ) : '';
		if ( isset( $r[0] ) && is_string( $r[0] ) ) { // one answer: [ html, fact, counts ]
			$q                = ! empty( $e['question'] ) ? call_user_func( $e['question'], $e['q'], $ctx['id'], $ctx['entity'], $e['key'] ) : sprintf( $e['q'], $ctx['entity'] );
			$out[ sanitize_key( $e['key'] ) ] = array( 'id' => sanitize_key( $e['key'] ), 'q' => $q, 'a' => $r[0], 'fact' => $r[1], 'priority' => $e['priority'], 'key' => $e['key'], 'counts' => $r[2] ?? true, 'answer' => $e['answer'], 'hidden' => $hidden );
			continue;
		}
		foreach ( array_values( $r ) as $i => $x ) { // several (key sections)
			$id         = sanitize_key( $e['key'] . '-' . substr( md5( (string) $x['fact'] ), 0, 8 ) ); // stable per section, safe as a key
			$out[ $id ] = array( 'id' => $id, 'q' => $x['q'], 'a' => $x['a'], 'fact' => $x['fact'], 'priority' => $e['priority'] + $i / 100, 'key' => $e['key'], 'counts' => true, 'answer' => $e['answer'], 'hidden' => $hidden );
		}
	}
	return $out;
}

/**
 * One stored row, cleaned: an own row [ q, a, sources[ [name, url] ], keyword, status, reviewed ] or an automatic row
 * [ auto, state, q, a ] (q and a only when edited). Null when it holds nothing.
 *
 * @param array $row Raw row.
 * @return array|null
 */
function aw_faq_clean_row( $row ) {
	$auto = sanitize_key( (string) ( $row['auto'] ?? '' ) );
	$q    = trim( sanitize_text_field( wp_strip_all_tags( (string) ( $row['q'] ?? '' ) ) ) );
	$a    = trim( wp_kses( (string) ( $row['a'] ?? '' ), AW_FAQ_ANSWER_HTML ) );
	if ( '' !== $auto ) {
		$state = in_array( $row['state'] ?? '', array( 'edited', 'removed' ), true ) ? $row['state'] : 'auto';
		if ( 'edited' === $state && ( '' === $q || '' === $a ) ) {
			$state = 'auto'; // an emptied edit goes back to the automatic question
		}
		return 'edited' === $state ? array( 'auto' => $auto, 'state' => $state, 'q' => $q, 'a' => $a ) : array( 'auto' => $auto, 'state' => $state );
	}
	if ( '' === $q || '' === $a ) {
		return null;
	}
	$src = array();
	foreach ( (array) ( $row['sources'] ?? array() ) as $s ) {
		$name = trim( sanitize_text_field( (string) ( is_array( $s ) ? ( $s['name'] ?? '' ) : $s ) ) );
		$url  = esc_url_raw( (string) ( is_array( $s ) ? ( $s['url'] ?? '' ) : '' ) );
		if ( '' !== $name || '' !== $url ) {
			$src[] = array( 'name' => $name, 'url' => $url );
		}
	}
	return array(
		'q'        => $q,
		'a'        => $a,
		'sources'  => $src,
		'keyword'  => trim( sanitize_text_field( (string) ( $row['keyword'] ?? '' ) ) ),
		'status'   => 'approved' === ( $row['status'] ?? '' ) ? 'approved' : 'draft',
		'reviewed' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $row['reviewed'] ?? '' ) ) ? $row['reviewed'] : '',
	);
}

/**
 * The rows stored on a post or term, cleaned, in the box's order.
 *
 * @param string $store 'term' or 'post'.
 * @param int    $id    Term or post ID.
 * @return array[]
 */
function aw_faq_items( $store, $id ) {
	$raw = json_decode( (string) ( 'term' === $store ? get_term_meta( $id, 'aw_faq', true ) : get_post_meta( $id, 'aw_faq', true ) ), true );
	return array_values( array_filter( array_map( 'aw_faq_clean_row', is_array( $raw ) ? $raw : array() ) ) );
}

/**
 * Store a post's or term's rows (the FAQ box, AW SEO Plus → FAQ, wp aw-faq import).
 *
 * @param string $store 'term' or 'post'.
 * @param int    $id    Term or post ID.
 * @param array  $rows  Rows.
 */
function aw_faq_items_save( $store, $id, array $rows ) {
	$clean = array_values( array_filter( array_map( 'aw_faq_clean_row', $rows ) ) );
	$json  = wp_slash( wp_json_encode( $clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	if ( 'term' === $store ) {
		$clean ? update_term_meta( $id, 'aw_faq', $json ) : delete_term_meta( $id, 'aw_faq' );
	} else {
		$clean ? update_post_meta( $id, 'aw_faq', $json ) : delete_post_meta( $id, 'aw_faq' );
	}
	delete_transient( 'aw_faq_index' );
}

/**
 * Every row the item's FAQ box shows, in order, each marked shown or not and why (the page shows exactly the shown
 * ones), plus whether the page shows an FAQ at all.
 *
 * @param array $ctx   aw_faq_context().
 * @param bool  $cache Keep for the request (false for the counts, which walk every item).
 * @return array [ rows[ [ origin auto|edited|own|removed, id, q, a, gen (the automatic text), sources, keyword, status,
 *               reviewed, fact, counts, answer, hidden, shown, why ] ], ok, note ]
 */
function aw_faq_rows( $ctx, $cache = true ) {
	static $memo = array();
	$key = $ctx['store'] . ':' . $ctx['id'];
	if ( $cache && isset( $memo[ $key ] ) ) {
		return $memo[ $key ];
	}
	$auto   = aw_faq_auto( $ctx );
	$stored = aw_faq_items( $ctx['store'], $ctx['id'] );
	uasort( $auto, fn( $a, $b ) => $a['priority'] <=> $b['priority'] );
	$base   = array( 'id' => '', 'key' => 'own', 'gen' => null, 'sources' => array(), 'keyword' => '', 'status' => 'approved', 'reviewed' => '', 'counts' => true, 'answer' => null, 'hidden' => '' );
	$gen    = fn( $origin, $g ) => array( 'origin' => $origin, 'id' => $g['id'], 'q' => $g['q'], 'a' => $g['a'], 'gen' => $g, 'key' => $g['key'], 'fact' => $g['fact'], 'counts' => $g['counts'], 'answer' => $g['answer'], 'hidden' => $g['hidden'] ?? '' ) + $base;
	$placed = array(); // in the stored order: own rows and the automatic rows the box placed
	$custom = false;
	foreach ( $stored as $s ) {
		if ( '' === ( $s['auto'] ?? '' ) ) {
			$placed[] = array( 'origin' => 'own', 'fact' => 'own:' . strtolower( $s['q'] ) ) + $s + $base;
			continue;
		}
		$custom = true;
		$g      = $auto[ $s['auto'] ] ?? null;
		if ( $g ) {
			$auto[ $s['auto'] ]['placed'] = true;
		}
		if ( 'edited' === $s['state'] ) {
			$placed[] = array( 'origin' => 'edited', 'id' => $s['auto'], 'q' => $s['q'], 'a' => $s['a'], 'gen' => $g, 'key' => 'edited', 'fact' => $g ? $g['fact'] : 'edited:' . $s['auto'], 'hidden' => $g['hidden'] ?? '' ) + $base; // the fact it states, so a repeat of it is still dropped; hidden stays hidden when edited
		} elseif ( $g ) { // automatic or removed: only while the data still gives the question
			$placed[] = $gen( 'removed' === $s['state'] ? 'removed' : 'auto', $g );
		}
	}
	$fresh = array(); // automatic questions the box has not placed: by priority (all of them by default; new data later)
	foreach ( $auto as $g ) {
		if ( empty( $g['placed'] ) ) {
			$fresh[] = $gen( 'auto', $g );
		}
	}
	// Default: automatic first, then own. Once the box has placed automatic rows, its order holds and new ones go last.
	$out = aw_faq_rows_mark( $ctx, $custom ? array_merge( $placed, $fresh ) : array_merge( $fresh, $placed ) );
	if ( $cache ) {
		$memo[ $key ] = $out;
	}
	return $out;
}

/**
 * Mark which rows the page shows, in the box's order: removed, hidden and draft rows are not shown; a row stating a fact (or
 * asking a question) already shown is dropped; at most AW_FAQ_MAX; fewer than the minimum counted = no FAQ at all.
 *
 * @param array $ctx  aw_faq_context().
 * @param array $rows aw_faq_rows() rows.
 * @return array [ rows, ok, note, min ]
 */
function aw_faq_rows_mark( $ctx, array $rows ) {
	$min   = aw_faq_min( $ctx );
	$seen  = array();
	$shown = 0;
	$count = 0;
	foreach ( $rows as $i => $r ) {
		$why = '';
		$qk  = 'q:' . strtolower( trim( preg_replace( '/\W+/u', ' ', $r['q'] ) ) );
		if ( 'removed' === $r['origin'] ) {
			$why = 'Removed';
		} elseif ( '' !== ( $r['hidden'] ?? '' ) ) {
			$why = 'Hidden: ' . $r['hidden'];
		} elseif ( 'own' === $r['origin'] && 'approved' !== $r['status'] ) {
			$why = 'Draft: not shown until approved';
		} elseif ( isset( $seen[ 'f:' . $r['fact'] ] ) || isset( $seen[ $qk ] ) ) {
			$why = 'Repeats a question above';
		} else {
			$seen[ 'f:' . $r['fact'] ] = true;
			$seen[ $qk ]               = true;
			if ( $shown >= AW_FAQ_MAX ) {
				$why = sprintf( 'Over the limit of %d questions', AW_FAQ_MAX );
			}
		}
		$rows[ $i ]['shown'] = '' === $why;
		$rows[ $i ]['why']   = '' === $why && ! $r['counts'] ? 'Shown, but not counted toward the minimum (general answer)' : $why;
		if ( '' === $why ) {
			++$shown;
			$count += $r['counts'] ? 1 : 0;
		}
	}
	$ok   = $shown > 0 && $count >= $min;
	$note = $shown > 0 && ! $ok ? sprintf( 'Fewer than %d questions count, so the page shows no FAQ.', $min ) : '';
	if ( 'post' === $ctx['store'] && has_block( 'rank-math/faq-block', get_post( $ctx['id'] ) ) ) {
		$ok   = false; // one FAQ per page (one FAQPage node): the page keeps the FAQ block in its content
		$note = 'This page has a Rank Math FAQ block in its content, so it shows that FAQ and none from this box.';
	}
	return array( 'rows' => $rows, 'ok' => $ok, 'note' => $note, 'min' => $min );
}

/**
 * The questions the page shows, in order, or null for no FAQ. Cached for the request.
 *
 * @param array|null $ctx aw_faq_context(); default the current page.
 * @return array|null [ ctx, items ]
 */
function aw_faq_build( $ctx = null ) {
	static $cache = array();
	$ctx = null === $ctx ? aw_faq_context() : $ctx;
	if ( ! $ctx ) {
		return null;
	}
	$key = $ctx['store'] . ':' . $ctx['id'];
	if ( array_key_exists( $key, $cache ) ) {
		return $cache[ $key ];
	}
	$r = aw_faq_rows( $ctx );
	if ( ! $r['ok'] ) {
		return $cache[ $key ] = null;
	}
	$faq = array_values( array_filter( $r['rows'], fn( $x ) => $x['shown'] ) );
	if ( '' !== $ctx['full'] && $ctx['full'] !== $ctx['entity'] && 'auto' === $faq[0]['origin'] && ! empty( $faq[0]['answer'] ) ) { // the full official title, once, in the first answer (automatic answers only)
		$a = call_user_func( $faq[0]['answer'], $ctx['id'], $ctx['full'] );
		if ( isset( $a[0] ) && is_string( $a[0] ) ) {
			$faq[0]['a'] = $a[0];
		} elseif ( is_array( $a ) ) {
			foreach ( $a as $x ) {
				$faq[0]['a'] = $x['fact'] === $faq[0]['fact'] ? $x['a'] : $faq[0]['a'];
			}
		}
	}
	return $cache[ $key ] = array( 'ctx' => $ctx, 'items' => $faq );
}

/**
 * The accordion: the section head (H2 "Questions about <subject>", one line, the ghost "All questions" link); each
 * question a button (aria-expanded) inside an H3; each answer in the HTML (kit.js closes them after load; without
 * JavaScript every answer shows).
 *
 * @param array|null $faq aw_faq_build().
 * @return string
 */
function aw_faq_html( $faq = null ) {
	static $html = array();
	$faq = null === $faq ? aw_faq_build() : $faq;
	if ( ! $faq ) {
		return '';
	}
	$key = $faq['ctx']['kind'] . ':' . $faq['ctx']['id'];
	if ( isset( $html[ $key ] ) ) {
		return $html[ $key ];
	}
	$body = '';
	foreach ( $faq['items'] as $i => $it ) {
		$n     = $i + 1;
		$body .= '<div class="kit-faq-item"><h3 class="kit-faq-q"><button type="button" class="kit-faq-btn" id="kit-faq-q' . $n . '" aria-expanded="true" aria-controls="kit-faq-a' . $n . '">' . esc_html( $it['q'] ) . '</button></h3>'
			. '<div class="kit-faq-a" id="kit-faq-a' . $n . '" role="region" aria-labelledby="kit-faq-q' . $n . '"><p>' . $it['a'] . '</p></div></div>'; // answers: escaped parts (registry) or kses-cleaned (topic items)
	}
	// wptexturize as the block template does to the whole page (" - " → " – ", "24x7" → "24×7"), so the text the schema is
	// parsed from is the text the page shows; texturizing it again later changes nothing.
	list( $line, $all, $label ) = array_pad( aw_faq_head_parts(), 3, '' );
	$here = untrailingslashit( (string) strtok( (string) $faq['ctx']['url'], '?#' ) ) === untrailingslashit( (string) strtok( (string) $all, '?#' ) );
	$head = '<div class="kit-sec-head"><div><h2 id="kit-faq-h">' . esc_html( $faq['ctx']['head'] ) . '</h2>' . ( '' !== $line ? '<p>' . esc_html( $line ) . '</p>' : '' ) . '</div>'
		. ( '' !== $all && '' !== $label && ! $here ? '<a class="kit-btn kit-btn--ghost kit-btn--sm" href="' . esc_url( $all ) . '">' . esc_html( $label ) . '</a>' : '' ) . '</div>';
	return $html[ $key ] = wptexturize( '<section class="kit-faq" id="faq" aria-labelledby="kit-faq-h" data-faq="' . esc_attr( $faq['ctx']['kind'] ) . '">' . $head . $body . '</section>' );
}

/**
 * The questions and answers as the page shows them, read back from the rendered HTML: [ [ q (text), a (HTML: a, p, b
 * only), a_text ], … ]. The schema and the tests use this, so they hold exactly what the page shows.
 *
 * @param string $html aw_faq_html().
 * @return array[]
 */
function aw_faq_parse( $html ) {
	if ( '' === trim( (string) $html ) ) {
		return array();
	}
	$dom = new DOMDocument();
	libxml_use_internal_errors( true );
	$dom->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	$xp  = new DOMXPath( $dom );
	$out = array();
	foreach ( $xp->query( '//div[contains(concat(" ", normalize-space(@class), " "), " kit-faq-item ")]' ) as $item ) {
		$q = $xp->query( './/button[contains(@class, "kit-faq-btn")]', $item )->item( 0 );
		$a = $xp->query( './/div[contains(@class, "kit-faq-a")]', $item )->item( 0 );
		if ( ! $q || ! $a ) {
			continue;
		}
		$inner = '';
		foreach ( $a->childNodes as $c ) {
			$inner .= $dom->saveHTML( $c );
		}
		$out[] = array(
			'q'      => aw_faq_plain( $q->textContent ),
			'a'      => trim( preg_replace( '/\s+/u', ' ', wp_kses( str_replace( array( '<strong>', '</strong>' ), array( '<b>', '</b>' ), $inner ), array( 'a' => array( 'href' => true ), 'p' => array(), 'b' => array(), 'ul' => array(), 'li' => array() ) ) ) ),
			'a_text' => aw_faq_plain( $a->textContent ),
		);
	}
	return $out;
}

/**
 * The page's FAQPage node, parsed from the rendered accordion (null without one). Put in the page's @graph by
 * aw-seo-extras/schema.php.
 *
 * @return array|null
 */
function aw_faq_schema() {
	$faq = aw_faq_build();
	$faq = $faq && aw_faq_visible_here( $faq['ctx'] ) ? $faq : null; // no FAQPage where the page hides its FAQ (schema text = visible text)
	$qa  = $faq ? aw_faq_parse( aw_faq_html( $faq ) ) : array();
	if ( count( $qa ) < aw_faq_min( $faq['ctx'] ?? null ) ) {
		return null;
	}
	return array(
		'@type'      => 'FAQPage',
		'@id'        => $faq['ctx']['url'] . '#faq',
		'url'        => $faq['ctx']['url'],
		'name'       => $faq['ctx']['head'],
		'inLanguage' => 'en-IN',
		'isPartOf'   => array( '@id' => $faq['ctx']['page_id'] ),
		'about'      => array( '@id' => $faq['ctx']['about'] ),
		'mainEntity' => array_map( fn( $x ) => array( '@type' => 'Question', 'name' => $x['q'], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $x['a'] ) ), $qa ),
	);
}

/* ---------- Placement ---------- */

/**
 * Whether the current view shows the item's FAQ where it is placed: term pages on page 1, item templates always; the
 * after-content position only on page 1 with no filters (aw_faq_show_here). Used by the schema and analytics.
 *
 * @param array $ctx aw_faq_context().
 * @return bool
 */
function aw_faq_visible_here( $ctx ) {
	return ( 'term' === $ctx['store'] && ! is_paged() ) || aw_faq_template_has_block() || aw_faq_show_here(); // a term's FAQ: page 1 of its list only
}

/** The page's FAQ accordion the first time it is asked for, '' after (one FAQ per page). */
function aw_faq_print_once() {
	static $done = false;
	if ( $done ) {
		return '';
	}
	$html = aw_faq_html();
	$done = '' !== $html;
	return $html;
}

/** Whether the page's block template places the FAQ itself (the item-faq block). */
function aw_faq_template_has_block() {
	global $_wp_current_template_content;
	return is_string( $_wp_current_template_content ) && false !== strpos( $_wp_current_template_content, '<!-- wp:' . AW_FAQ_ITEM_BLOCK . ' ' );
}

/** The item FAQ block: the page's own FAQ, where an item template puts it. */
function aw_block_item_faq( $a = array(), $id = 0 ) {
	if ( ! is_singular() || ( $id && (int) $id !== get_queried_object_id() ) ) {
		return function_exists( 'aw_item_editor_preview' ) ? aw_item_editor_preview( 'FAQ: the questions in this page’s FAQ box (none when fewer than the minimum)' ) : '';
	}
	return aw_faq_print_once();
}

add_filter(
	'aw_hub_blocks',
	function ( $blocks ) {
		$blocks['item-faq'] = array( 'Item: FAQ', 'aw_block_item_faq', array() );
		return $blocks;
	}
);

// Every other page: the FAQ follows the page's own content, on page 1 with no filters (aw_faq_show_here). Inside a
// constrained content area it takes the content width; in a full-width one it gets the theme's padding and width.
add_filter(
	'render_block_core/post-content',
	function ( $html, $block, $instance = null ) {
		if ( is_admin() || ! is_singular() || aw_faq_template_has_block() ) {
			return $html;
		}
		$ctx = aw_faq_context();
		if ( ! $ctx || 'post' !== $ctx['store'] || ( $instance instanceof WP_Block && isset( $instance->context['postId'] ) && (int) $instance->context['postId'] !== $ctx['id'] ) || ! aw_faq_show_here() ) {
			return $html;
		}
		$faq = aw_faq_print_once();
		if ( '' === $faq ) {
			return $html;
		}
		$class = 'hub' === $ctx['kind'] ? 'aw-hub-faq alignwide' : ( 'constrained' === ( $block['attrs']['layout']['type'] ?? '' ) ? 'aw-hub-faq' : 'aw-hub-faq has-global-padding is-layout-constrained' );
		return $html . '<div class="' . esc_attr( $class ) . '">' . $faq . '</div>';
	},
	10,
	3
);

/* ---------- Analytics: whether the page shows an FAQ ---------- */

add_filter(
	'kits_page_context',
	function ( $ctx ) {
		$c = ( is_singular() || is_category() || is_tag() || is_tax() ) ? aw_faq_context() : null;
		if ( $c ) {
			// faq | none (31 Oct 2026: no holdout). Same rule as the page: an after-content FAQ is hidden on later pages,
			// filtered lists and the site's own list views (aw_faq_show_here). A page that keeps its Rank Math FAQ block
			// shows that one (ours is suppressed), so it is 'faq' too.
			$rm               = 'post' === $c['store'] && has_block( 'rank-math/faq-block', get_post( $c['id'] ) );
			$ctx['faq_group'] = $rm || ( aw_faq_visible_here( $c ) && aw_faq_build( $c ) ) ? 'faq' : 'none';
		}
		return $ctx;
	}
);

/* ---------- The FAQ box: every post type and taxonomy ---------- */

/** A question and answer as compared and shown in the box (the answer as the box may store it). */
function aw_faq_norm( $q, $a ) {
	return array( trim( sanitize_text_field( (string) $q ) ), trim( preg_replace( '/\s+/u', ' ', wp_kses( (string) $a, AW_FAQ_ANSWER_HTML ) ) ) );
}

/** Fingerprint of a question and answer as the box showed them (an unchanged automatic row stays automatic). */
function aw_faq_print_hash( $q, $a ) {
	return md5( implode( "\n", aw_faq_norm( $q, $a ) ) );
}

/**
 * The FAQ box: the rows the page has (automatic ones filled in), whether each is shown and why, then empty rows to add
 * questions. Order numbers move rows; Remove hides one; an automatic row edited is kept as written.
 *
 * @param array $ctx aw_faq_context().
 */
function aw_faq_editor( $ctx ) {
	$r    = aw_faq_rows( $ctx, false );
	$rows = $r['rows'];
	wp_nonce_field( 'aw_faq_items', 'aw_faq_items_nonce' );
	if ( 'post' === $ctx['store'] ) {
		$post = get_post( $ctx['id'] );
		$fall = $post ? aw_faq_subject( $post ) : array( '', 'title' );
		$own  = (string) get_post_meta( $ctx['id'], 'aw_faq_subject', true );
		echo '<p><label for="aw-faq-subject"><strong>Subject</strong> (the heading reads “Questions about …”)</label><br><input type="text" class="large-text" id="aw-faq-subject" name="aw_faq_subject" value="' . esc_attr( $own ) . '" placeholder="' . esc_attr( 'meta' === $fall[1] ? '' : $fall[0] ) . '"></p>';
		if ( '' === $own && ! empty( $ctx['subject_missing'] ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html( 'No subject: the heading uses the page title, “' . $ctx['head'] . '”.' ) . '</p></div>';
		}
	}
	// The default order as rendered (no automatic rows stored): saving it unchanged stores none, even when the item's
	// own fields changed in the same save (they are saved first, so the questions now may differ from these).
	$stored_auto = array_filter( aw_faq_items( $ctx['store'], $ctx['id'] ), fn( $x ) => '' !== ( $x['auto'] ?? '' ) );
	$rendered    = $stored_auto ? '' : implode( ',', wp_list_pluck( wp_list_filter( $rows, array( 'origin' => 'auto' ) ), 'id' ) );
	echo '<input type="hidden" name="aw_faq_default" value="' . esc_attr( '' === $rendered ? '-' : $rendered ) . '">';
	printf(
		'<p class="description">%s</p>',
		esc_html(
			sprintf(
				'The page shows these questions in this order: at least %1$d, at most %2$d (fewer than %1$d: no FAQ and no FAQ schema). Auto questions come from this page’s own fields (on a tag: from the items tagged) and follow them; edit one and it is kept as written (Back to automatic undoes that). Remove hides a question; Order moves it. Your own questions show once Approved. First sentence answers and names the subject; at most %3$d words; one link (the source or a section of the site); dates as 4 Oct 2026. Answers may use links, bold and italic. Sources: one per line, "Name | URL".',
				$r['min'],
				AW_FAQ_MAX,
				AW_FAQ_WORDS
			)
		)
	);
	$shown = count( array_filter( $rows, fn( $x ) => $x['shown'] ) );
	if ( '' !== $r['note'] ) {
		echo '<div class="notice notice-warning inline"><p>' . esc_html( $r['note'] ) . '</p></div>';
	} elseif ( $r['ok'] ) {
		echo '<div class="notice notice-info inline"><p>' . esc_html( sprintf( 'The page shows %d questions.', $shown ) ) . '</p></div>';
	}
	$labels = array( 'auto' => 'Auto (from this page’s data)', 'edited' => 'Edited (kept as written)', 'removed' => 'Auto, removed', 'own' => 'Own' );
	$blanks = max( 1, 6 - count( $rows ) );
	for ( $j = 0; $j < $blanks; $j++ ) {
		$rows[] = array( 'origin' => 'own', 'id' => '', 'q' => '', 'a' => '', 'gen' => null, 'sources' => array(), 'keyword' => '', 'status' => 'draft', 'reviewed' => '', 'shown' => false, 'why' => '', 'blank' => true );
	}
	foreach ( $rows as $i => $row ) {
		$n    = 'aw_faq[' . (int) $i . ']';
		$auto  = 'own' !== $row['origin'];
		$blank = ! empty( $row['blank'] );
		list( $q, $a ) = $auto && 'edited' !== $row['origin'] ? aw_faq_norm( $row['q'], $row['a'] ) : array( $row['q'], $row['a'] );
		$wc   = '' !== $a ? aw_faq_word_count( $a ) : 0;
		$tag  = $blank ? 'New question' : $labels[ $row['origin'] ] . ( 'own' === $row['origin'] ? ', ' . ( 'approved' === $row['status'] ? 'approved' : 'draft' ) : '' ) . ' · ' . ( $row['shown'] ? ( '' !== $row['why'] ? $row['why'] : 'Shown' ) : 'Not shown: ' . $row['why'] );
		echo '<fieldset style="border:1px solid #c3c4c7;padding:8px 12px;margin:0 0 12px"><legend><strong>' . esc_html( $blank ? 'Add a question' : 'Question ' . ( $i + 1 ) ) . '</strong> — ' . esc_html( $tag ) . ( $wc > AW_FAQ_WORDS ? ' <span style="color:#b32d2e">(' . (int) $wc . ' words: over ' . (int) AW_FAQ_WORDS . ')</span>' : '' ) . '</legend>';
		echo '<p><label>Order <input type="number" class="small-text" min="1" name="' . esc_attr( $n ) . '[order]" value="' . esc_attr( $blank ? '' : (string) ( $i + 1 ) ) . '"></label>';
		if ( ! $blank ) {
			echo ' &nbsp; <label><input type="checkbox" name="' . esc_attr( $n ) . '[remove]" value="1"' . checked( 'removed', $row['origin'], false ) . '> Remove</label>';
		}
		if ( 'edited' === $row['origin'] && $row['gen'] ) {
			echo ' &nbsp; <label><input type="checkbox" name="' . esc_attr( $n ) . '[reset]" value="1"> Back to automatic</label>';
		}
		echo '</p>';
		if ( $auto ) {
			echo '<input type="hidden" name="' . esc_attr( $n ) . '[auto]" value="' . esc_attr( $row['id'] ) . '"><input type="hidden" name="' . esc_attr( $n ) . '[origin]" value="' . esc_attr( $row['origin'] ) . '"><input type="hidden" name="' . esc_attr( $n ) . '[orig]" value="' . esc_attr( aw_faq_print_hash( $q, $a ) ) . '">';
		}
		echo '<p><input type="text" class="large-text" name="' . esc_attr( $n ) . '[q]" value="' . esc_attr( $q ) . '" placeholder="Question" aria-label="' . esc_attr( 'Question ' . ( $i + 1 ) ) . '"></p>';
		echo '<p><textarea class="large-text" rows="3" name="' . esc_attr( $n ) . '[a]" placeholder="Answer" aria-label="' . esc_attr( 'Answer ' . ( $i + 1 ) ) . '">' . esc_textarea( $a ) . '</textarea></p>';
		if ( 'edited' === $row['origin'] && $row['gen'] ) {
			echo '<p class="description">Automatic version: ' . esc_html( $row['gen']['q'] ) . ' — ' . esc_html( aw_faq_plain( $row['gen']['a'] ) ) . '</p>';
		}
		if ( ! $auto ) {
			$src = implode( "\n", array_map( fn( $s ) => trim( $s['name'] . ' | ' . $s['url'], ' |' ), $row['sources'] ) );
			echo '<p><textarea class="large-text" rows="2" name="' . esc_attr( $n ) . '[sources]" placeholder="Sources: Name | URL, one per line" aria-label="' . esc_attr( 'Sources ' . ( $i + 1 ) ) . '">' . esc_textarea( $src ) . '</textarea></p>';
			echo '<p><label>Target keyword <input type="text" name="' . esc_attr( $n ) . '[keyword]" value="' . esc_attr( $row['keyword'] ) . '"></label> ';
			echo '<label>Status <select name="' . esc_attr( $n ) . '[status]"><option value="draft"' . selected( $row['status'], 'draft', false ) . '>Draft</option><option value="approved"' . selected( $row['status'], 'approved', false ) . '>Approved</option></select></label> ';
			echo '<label>Last reviewed <input type="date" name="' . esc_attr( $n ) . '[reviewed]" value="' . esc_attr( $row['reviewed'] ) . '"></label></p>';
		}
		echo '</fieldset>';
	}
}

/** Whether the posted FAQ box may be saved: its nonce. The caller checks the capability for the item. */
function aw_faq_editor_posted() {
	return isset( $_POST['aw_faq_items_nonce'], $_POST['aw_faq'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['aw_faq_items_nonce'] ) ), 'aw_faq_items' );
}

/**
 * Save the posted FAQ box. Rows go in their Order; an automatic row unchanged stays automatic (it follows the data), a
 * changed one is stored as edited, a removed one as removed. When the box is in its default order with nothing
 * edited or removed, no automatic rows are stored, so new data keeps sorting by priority.
 *
 * @param array $ctx aw_faq_context().
 */
function aw_faq_editor_save( $ctx ) {
	$posted = isset( $_POST['aw_faq'] ) && is_array( $_POST['aw_faq'] ) ? wp_unslash( $_POST['aw_faq'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput -- nonce: aw_faq_editor_posted(); each field is cleaned in aw_faq_clean_row()
	$list   = array();
	foreach ( $posted as $i => $p ) {
		$p      = is_array( $p ) ? $p : array();
		$o      = isset( $p['order'] ) && is_numeric( $p['order'] ) ? (float) $p['order'] : 10000 + (int) $i;
		$list[] = array( $o, (int) $i, $p );
	}
	usort( $list, fn( $x, $y ) => array( $x[0], $x[1] ) <=> array( $y[0], $y[1] ) );
	$rows  = array();
	$seq   = array();
	$plain = true; // nothing edited or removed
	foreach ( $list as $item ) {
		$p    = $item[2];
		$auto = sanitize_key( (string) ( $p['auto'] ?? '' ) );
		if ( '' !== $auto ) {
			$origin = sanitize_key( (string) ( $p['origin'] ?? 'auto' ) );
			if ( ! empty( $p['remove'] ) ) {
				$rows[] = array( 'auto' => $auto, 'state' => 'removed' );
				$plain  = false;
				continue;
			}
			$same = aw_faq_print_hash( $p['q'] ?? '', $p['a'] ?? '' ) === (string) ( $p['orig'] ?? '' );
			if ( ! empty( $p['reset'] ) || ( $same && 'edited' !== $origin ) ) {
				$rows[] = array( 'auto' => $auto, 'state' => 'auto' );
			} else {
				$rows[] = array( 'auto' => $auto, 'state' => 'edited', 'q' => (string) ( $p['q'] ?? '' ), 'a' => (string) ( $p['a'] ?? '' ) );
				$plain  = false;
			}
			$seq[] = $auto;
			continue;
		}
		if ( ! empty( $p['remove'] ) ) {
			continue;
		}
		$src = array();
		foreach ( preg_split( '/\R/', (string) ( $p['sources'] ?? '' ) ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			if ( '' !== $parts[0] || '' !== ( $parts[1] ?? '' ) ) {
				$src[] = 1 === count( $parts ) && preg_match( '#^https?://#', $parts[0] ) ? array( 'name' => '', 'url' => $parts[0] ) : array( 'name' => $parts[0], 'url' => $parts[1] ?? '' );
			}
		}
		$p['sources'] = $src;
		unset( $p['auto'], $p['state'] );
		if ( aw_faq_clean_row( $p ) ) {
			$rows[] = $p;
			$seq[]  = '';
		}
	}
	// Stored automatic rows the box did not show (their data is empty now) keep their state, so a removal survives.
	$posted_ids = array_filter( array_map( fn( $x ) => (string) ( $x['auto'] ?? '' ), $rows ), 'strlen' );
	foreach ( aw_faq_items( $ctx['store'], $ctx['id'] ) as $old ) {
		if ( '' !== ( $old['auto'] ?? '' ) && ! in_array( $old['auto'], $posted_ids, true ) ) {
			$rows[] = $old;
			$plain  = $plain && 'auto' === $old['state'];
		}
	}
	// The box was in its default order and is saved as rendered, with nothing edited or removed: store own rows only.
	$default = sanitize_text_field( wp_unslash( $_POST['aw_faq_default'] ?? '-' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- nonce: aw_faq_editor_posted()
	if ( '-' !== $default ) {
		$ids    = array_values( array_filter( explode( ',', $default ), 'strlen' ) );
		$expect = array_merge( $ids, array_fill( 0, count( array_filter( $seq, fn( $x ) => '' === $x ) ), '' ) );
		if ( $plain && $seq === $expect ) {
			$rows = array_values( array_filter( $rows, fn( $x ) => '' === ( $x['auto'] ?? '' ) ) );
		}
	}
	aw_faq_items_save( $ctx['store'], $ctx['id'], $rows );
}

add_action(
	'add_meta_boxes',
	function ( $post_type, $post = null ) {
		if ( ! $post instanceof WP_Post || ! in_array( $post_type, aw_faq_post_types(), true ) ) {
			return;
		}
		add_meta_box(
			'aw-faq',
			'FAQ',
			function ( $p ) {
				$ctx = aw_faq_context( $p );
				if ( $ctx ) {
					aw_faq_editor( $ctx );
				}
			},
			$post_type,
			'normal',
			'low'
		);
	},
	10,
	2
);

add_action(
	'save_post',
	function ( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! empty( $GLOBALS['aw_faq_converting'] ) || ! aw_faq_editor_posted() ) {
			return; // the conversion's own re-save must not overwrite the questions it just moved
		}
		if ( absint( wp_unslash( $_POST['post_ID'] ?? 0 ) ) !== (int) $post_id || ! current_user_can( 'edit_post', $post_id ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- checked in aw_faq_editor_posted()
			return; // only the post whose edit screen sent the box (not another post saved in the same request)
		}
		if ( isset( $_POST['aw_faq_subject'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- checked in aw_faq_editor_posted()
			$subject = sanitize_text_field( wp_unslash( $_POST['aw_faq_subject'] ) ); // phpcs:ignore WordPress.Security.NonceVerification -- checked in aw_faq_editor_posted()
			if ( '' === $subject ) {
				delete_post_meta( $post_id, 'aw_faq_subject' );
			} else {
				update_post_meta( $post_id, 'aw_faq_subject', $subject );
			}
		}
		$ctx = aw_faq_context( $post );
		if ( $ctx ) {
			aw_faq_editor_save( $ctx );
		}
	},
	10,
	2
);

/* ---------- Pages and posts lists: the FAQ subject column ---------- */

/**
 * The FAQ subject column on the Pages and Posts lists: the subject the heading names, "Missing: uses the title" when a
 * page or post shows an FAQ without one, a dash when it shows no FAQ.
 */
add_action(
	'admin_init',
	function () {
		foreach ( array_intersect( array( 'page', 'post' ), aw_faq_post_types() ) as $pt ) {
			add_filter( "manage_{$pt}_posts_columns", fn( $cols ) => array_merge( $cols, array( 'aw_faq_subject' => 'FAQ subject' ) ) );
			add_action(
				"manage_{$pt}_posts_custom_column",
				function ( $col, $post_id ) {
					if ( 'aw_faq_subject' !== $col ) {
						return;
					}
					$post = get_post( $post_id );
					$ctx  = $post ? aw_faq_context( $post ) : null;
					if ( ! $ctx || ! aw_faq_build( $ctx ) ) {
						echo '—';
						return;
					}
					echo esc_html( ! empty( $ctx['subject_missing'] ) ? 'Missing: uses the title' : aw_faq_subject( $post )[0] );
				},
				10,
				2
			);
		}
	}
);

/** The FAQ box on a term's edit screen. */
function aw_faq_term_box( $term ) {
	$ctx = aw_faq_context( $term );
	if ( $ctx ) {
		echo '<tr class="form-field" id="aw-faq"><th scope="row">FAQ</th><td>';
		aw_faq_editor( $ctx );
		echo '</td></tr>';
	}
}

/** Save a term's FAQ box. */
function aw_faq_term_save( $term_id ) {
	$term = get_term( $term_id );
	$ctx  = $term instanceof WP_Term && aw_faq_editor_posted() && current_user_can( 'edit_term', $term_id ) ? aw_faq_context( $term ) : null;
	if ( $ctx ) {
		aw_faq_editor_save( $ctx );
	}
}

add_action(
	'admin_init',
	function () {
		foreach ( aw_faq_taxonomies() as $tax ) {
			add_action( "{$tax}_edit_form_fields", 'aw_faq_term_box' );
			add_action( "edited_{$tax}", 'aw_faq_term_save' );
		}
	}
);

/* ---------- Resources guides: Rank Math FAQ blocks converted on save ---------- */

add_action(
	'save_post_post',
	function ( $post_id ) {
		if ( ! wp_is_post_revision( $post_id ) && empty( $GLOBALS['aw_faq_converting'] ) ) {
			aw_faq_convert_rank_math( $post_id ); // a Rank Math FAQ block added in the editor or by a publish script
		}
	},
	20
);

/**
 * Move a guide's Rank Math FAQ block into its own FAQ (owner, 25 Oct 2026: one FAQ system, ours). Questions and answers
 * are kept word for word and approved (they were already published); the block and the heading just before it leave
 * the content; the removed markup is kept in _aw_faq_rm_block for Undo. A guide that already has FAQ items keeps them
 * and gets the Rank Math questions added after them.
 *
 * @param int  $post_id Post ID.
 * @param bool $dry     Report only.
 * @return array|null [ questions moved, words over 50, answers with more than one link ], or null when there was none.
 */
function aw_faq_convert_rank_math( $post_id, $dry = false ) {
	$post = get_post( $post_id );
	if ( ! $post || 'post' !== $post->post_type || ! has_block( 'rank-math/faq-block', $post ) ) {
		return null;
	}
	$blocks  = parse_blocks( $post->post_content );
	$keep    = array();
	$removed = array();
	$moved   = array();
	foreach ( $blocks as $i => $b ) {
		if ( 'rank-math/faq-block' === $b['blockName'] ) {
			foreach ( (array) ( $b['attrs']['questions'] ?? array() ) as $q ) {
				if ( isset( $q['visible'] ) && ! $q['visible'] ) {
					continue;
				}
				$moved[] = array( 'q' => wp_strip_all_tags( (string) ( $q['title'] ?? '' ) ), 'a' => (string) ( $q['content'] ?? '' ) );
			}
			$prev = end( $keep );
			if ( $prev && 'core/heading' === $prev['blockName'] && preg_match( '/question|faq/i', wp_strip_all_tags( (string) $prev['innerHTML'] ) ) ) {
				$removed[] = array_pop( $keep );
			}
			$removed[] = $b;
			continue;
		}
		$keep[] = $b;
	}
	$long  = count( array_filter( $moved, fn( $m ) => aw_faq_word_count( $m['a'] ) > AW_FAQ_WORDS ) );
	$links = count( array_filter( $moved, fn( $m ) => substr_count( $m['a'], '<a ' ) > 1 ) );
	if ( $dry ) {
		return array( count( $moved ), $long, $links );
	}
	$items  = aw_faq_items( 'post', $post_id );
	$before = (string) get_post_meta( $post_id, 'aw_faq', true ); // the FAQ as it was, for undo
	$have   = array_map( fn( $x ) => strtolower( $x['q'] ), $items );
	foreach ( $moved as $m ) {
		if ( '' !== $m['q'] && ! in_array( strtolower( $m['q'] ), $have, true ) ) {
			$items[] = array( 'q' => $m['q'], 'a' => $m['a'], 'sources' => array(), 'keyword' => '', 'status' => 'approved', 'reviewed' => wp_date( 'Y-m-d' ) );
		}
	}
	aw_faq_items_save( 'post', $post_id, $items );
	// One backup per conversion, newest last: undo reverses only the latest one (block back, FAQ as it was before it).
	$log   = json_decode( (string) get_post_meta( $post_id, '_aw_faq_rm_block', true ), true );
	$log   = is_array( $log ) ? $log : array();
	$log[] = array(
		'at'     => wp_date( 'Y-m-d H:i' ),
		'blocks' => serialize_blocks( $removed ),
		'faq'    => $before,
	);
	update_post_meta( $post_id, '_aw_faq_rm_block', wp_slash( wp_json_encode( $log ) ) );
	$GLOBALS['aw_faq_converting'] = true;
	wp_update_post(
		array(
			'ID'           => $post_id,
			'post_content' => wp_slash( serialize_blocks( $keep ) ),
		)
	);
	$GLOBALS['aw_faq_converting'] = false;
	clean_post_cache( $post_id );
	return array( count( $moved ), $long, $links );
}

/* ---------- WP-CLI ---------- */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * FAQ tools: report (counts by type), check (the spec's tests on every page with an FAQ), import <file> (draft
	 * questions: {"<post type or taxonomy>:<slug>": [ {q, a, sources, keyword} ] }), convert-guides [--dry-run],
	 * undo-guide <id>.
	 */
	WP_CLI::add_command(
		'aw-faq',
		function ( $args, $assoc ) {
			$cmd = $args[0] ?? 'report';
			if ( 'convert-guides' === $cmd ) { // Rank Math FAQ blocks on guides → their own FAQ (owner, 25 Oct 2026)
				$dry = ! empty( $assoc['dry-run'] );
				$tot = array( 0, 0, 0, 0 );
				foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) {
					$r = aw_faq_convert_rank_math( $id, $dry );
					if ( null === $r ) {
						continue;
					}
					WP_CLI::line( sprintf( '%d %s: %d questions%s%s', $id, get_post_field( 'post_name', $id ), $r[0], $r[1] ? ", $r[1] over 50 words" : '', $r[2] ? ", $r[2] with more than one link" : '' ) . ( $r[0] > AW_FAQ_MAX ? ', MORE THAN ' . AW_FAQ_MAX . ': only the first ' . AW_FAQ_MAX . ' show' : '' ) );
					++$tot[0];
					$tot[1] += $r[0];
					$tot[2] += $r[1];
					$tot[3] += $r[2];
				}
				delete_transient( 'aw_faq_index' );
				WP_CLI::success( ( $dry ? 'Would convert ' : 'Converted ' ) . "$tot[0] guides, $tot[1] questions ($tot[2] over 50 words, $tot[3] with more than one link: kept as written)." );
				return;
			}
			if ( 'undo-guide' === $cmd ) { // reverse a guide's latest conversion: its Rank Math block back, its FAQ as it was before
				$id   = (int) ( $args[1] ?? 0 );
				$log  = json_decode( (string) get_post_meta( $id, '_aw_faq_rm_block', true ), true );
				$last = is_array( $log ) && $log ? array_pop( $log ) : null;
				if ( ! $last ) {
					WP_CLI::error( "Nothing to undo for $id." );
				}
				$GLOBALS['aw_faq_converting'] = true;
				wp_update_post(
					array(
						'ID'           => $id,
						'post_content' => wp_slash( get_post_field( 'post_content', $id ) . "\n\n" . $last['blocks'] ),
					)
				);
				$GLOBALS['aw_faq_converting'] = false;
				if ( '' === (string) $last['faq'] ) {
					delete_post_meta( $id, 'aw_faq' );
				} else {
					update_post_meta( $id, 'aw_faq', wp_slash( $last['faq'] ) );
				}
				if ( $log ) {
					update_post_meta( $id, '_aw_faq_rm_block', wp_slash( wp_json_encode( $log ) ) );
				} else {
					delete_post_meta( $id, '_aw_faq_rm_block' );
				}
				WP_CLI::success( "Reversed the conversion of {$last['at']} on $id: Rank Math FAQ block back at the end of the content (converted again on its next save), FAQ as it was before." );
				return;
			}
			if ( 'import' === $cmd ) {
				$data = json_decode( (string) file_get_contents( $args[1] ?? '' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file named on the command line
				if ( ! is_array( $data ) ) {
					WP_CLI::error( 'Not a JSON file.' );
				}
				$n = 0;
				foreach ( $data as $key => $rows ) {
					if ( ! preg_match( '/^([a-z0-9_-]+):([a-z0-9-]+)$/', (string) $key, $m ) || ! is_array( $rows ) ) {
						continue;
					}
					$store = '';
					$id    = 0;
					if ( in_array( $m[1], aw_faq_post_types(), true ) ) {
						$p     = get_page_by_path( $m[2], OBJECT, $m[1] );
						$id    = $p ? (int) $p->ID : 0;
						$store = 'post';
					} elseif ( in_array( $m[1], aw_faq_taxonomies(), true ) ) {
						$t     = get_term_by( 'slug', $m[2], $m[1] );
						$id    = $t ? (int) $t->term_id : 0;
						$store = 'term';
					}
					if ( ! $id ) {
						WP_CLI::warning( "skipped $key" );
						continue;
					}
					$items = aw_faq_items( $store, $id );
					$have  = array_map( fn( $i ) => strtolower( (string) ( $i['q'] ?? '' ) ), $items );
					foreach ( $rows as $r ) {
						if ( ! in_array( strtolower( trim( (string) ( $r['q'] ?? '' ) ) ), $have, true ) ) {
							$items[] = array( 'q' => $r['q'] ?? '', 'a' => $r['a'] ?? '', 'sources' => $r['sources'] ?? array(), 'keyword' => $r['keyword'] ?? '', 'status' => 'draft', 'reviewed' => '' );
							++$n;
						}
					}
					aw_faq_items_save( $store, $id, $items );
				}
				WP_CLI::success( "$n draft questions added." );
				return;
			}
			if ( 'check' === $cmd ) {
				// Spec §9 on every page with an FAQ: minimum to 6 questions; schema text = visible text; no duplicate
				// question; ≤ AW_FAQ_WORDS words; one link. Also: the focus keyphrase in at least 2 questions.
				$fail   = 0;
				$seen   = 0;
				$kwmiss = 0;
				foreach ( aw_faq_index( true )['rows'] as $row ) {
					if ( ! $row['shown'] ) {
						continue;
					}
					$o   = 'term' === $row['store'] ? get_term( $row['id'] ) : get_post( $row['id'] );
					$ctx = $o ? aw_faq_context( $o ) : null;
					$faq = $ctx ? aw_faq_build( $ctx ) : null;
					$id  = $o instanceof WP_Term ? 'term ' . $o->taxonomy . ':' . $o->slug : $row['type'] . ' ' . $row['id'];
					if ( ! $faq ) {
						continue;
					}
					++$seen;
					$qa   = aw_faq_parse( aw_faq_html( $faq ) );
					$errs = array();
					if ( count( $qa ) < aw_faq_min( $faq['ctx'] ) || count( $qa ) > AW_FAQ_MAX ) {
						$errs[] = count( $qa ) . ' questions';
					}
					if ( count( array_unique( array_map( 'strtolower', wp_list_pluck( $qa, 'q' ) ) ) ) !== count( $qa ) ) {
						$errs[] = 'duplicate question';
					}
					foreach ( $qa as $i => $x ) {
						if ( aw_faq_plain( $x['a'] ) !== $x['a_text'] ) {
							$errs[] = 'Q' . ( $i + 1 ) . ' schema text differs';
						}
						if ( aw_faq_word_count( $x['a'] ) > AW_FAQ_WORDS ) {
							$errs[] = 'Q' . ( $i + 1 ) . ' over ' . AW_FAQ_WORDS . ' words';
						}
						if ( 1 !== substr_count( $x['a'], '<a ' ) ) {
							$errs[] = 'Q' . ( $i + 1 ) . ' has ' . substr_count( $x['a'], '<a ' ) . ' links';
						}
					}
					$kw   = strtolower( trim( explode( ',', (string) ( $o instanceof WP_Term ? get_term_meta( $o->term_id, 'rank_math_focus_keyword', true ) : get_post_meta( $o->ID, 'rank_math_focus_keyword', true ) ) )[0] ) );
					$fold = fn( $t ) => preg_replace( '/\s+/u', ' ', str_replace( array( '–', '—', '’', '‘' ), array( '-', '-', "'", "'" ), mb_strtolower( $t ) ) ); // dashes and quotes as typed in a keyphrase
					if ( '' !== $kw && count( array_filter( wp_list_pluck( $qa, 'q' ), fn( $q ) => false !== strpos( $fold( $q ), $fold( $kw ) ) ) ) < 2 ) {
						++$kwmiss;
						if ( ! empty( $assoc['verbose'] ) ) {
							WP_CLI::line( $id . ': focus keyphrase "' . $kw . '" in fewer than 2 questions' );
						}
					}
					if ( $errs ) {
						++$fail;
						WP_CLI::line( $id . ': ' . implode( '; ', $errs ) );
					}
				}
				WP_CLI::line( "$kwmiss pages have their focus keyphrase in fewer than 2 questions (--verbose lists them)." );
				$fail ? WP_CLI::error( "$fail of $seen pages with an FAQ fail." ) : WP_CLI::success( "$seen pages with an FAQ pass." );
				return;
			}
			// report
			$ix = aw_faq_index( true );
			WP_CLI::line( wp_json_encode( $ix['counts'], JSON_PRETTY_PRINT ) );
			$missing = 0;
			foreach ( $ix['rows'] as $row ) { // pages and posts that show an FAQ with no subject: the heading falls back to the title
				$p   = 'post' === $row['store'] && $row['shown'] ? get_post( $row['id'] ) : null;
				$ctx = $p ? aw_faq_context( $p ) : null;
				if ( $ctx && ! empty( $ctx['subject_missing'] ) ) {
					++$missing;
					WP_CLI::line( sprintf( 'No FAQ subject: %s %d %s (heading: "%s")', $row['type'], $row['id'], get_post_field( 'post_name', $p ), $ctx['head'] ) );
				}
			}
			WP_CLI::line( $missing ? "$missing pages and posts show an FAQ without a subject (FAQ box → Subject)." : 'Every page and post with an FAQ has a subject.' );
		}
	);
}
