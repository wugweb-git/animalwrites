<?php
/**
 * Shared list helpers, the list filters and the trust panel.
 *
 *   Lists         the hub Pages' Query Loops (hubs.php, hub-blocks.php); the filters below drive them.
 *   TRUST         [aw_trust]  source, dates and verification on every item page
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- Shared helpers ---------- */

/**
 * Icon span.
 *
 * @param string $name Icon name (see aw.css 4.7).
 * @return string
 */
function aw_i( $name ) {
	return '<span class="kit-ico kit-ico--' . esc_attr( $name ) . '" aria-hidden="true"></span>';
}

/**
 * The one date component (owner, 14 Oct 2026): the calendar icon directly before the date, nothing between them.
 * Every visible date on the site goes through here (core post-date blocks get the same icon from CSS).
 *
 * @param string     $when Any date strtotime() reads (Y-m-d, a full timestamp string) or a Unix time.
 * @param string     $fmt  Display format.
 * @return string
 */
function aw_date( $when, $fmt = 'j M Y' ) {
	$ts = is_numeric( $when ) ? (int) $when : strtotime( (string) $when );
	if ( ! $ts ) {
		return '';
	}
	return '<span class="aw-date">' . aw_i( 'calendar' ) . '<time datetime="' . esc_attr( wp_date( 'Y-m-d', $ts ) ) . '">' . esc_html( wp_date( $fmt, $ts ) ) . '</time></span>';
}

/**
 * Date of the original publication when known, else the post date.
 *
 * @param int    $id     Post ID.
 * @param string $format Date format.
 * @return string
 */
function aw_src_date( $id, $format = 'j M Y' ) {
	$d = (string) get_post_meta( $id, 'aw_source_date', true );
	return $d ? wp_date( $format, strtotime( $d . ' 12:00:00' ) ) : get_the_date( $format, $id );
}

/**
 * First term name of a taxonomy.
 *
 * @param int    $id  Post ID.
 * @param string $tax Taxonomy.
 * @return WP_Term|null
 */
function aw_first_term( $id, $tax ) {
	$t = get_the_terms( $id, $tax );
	return ( $t && ! is_wp_error( $t ) ) ? $t[0] : null;
}

/**
 * Plain-text excerpt.
 *
 * @param int $id    Post ID.
 * @param int $words Word limit.
 * @return string
 */
function aw_text( $id, $words = 28 ) {
	return wp_trim_words( wp_strip_all_tags( get_the_excerpt( $id ) ), $words );
}

/**
 * List filters: post type => list of [query parameter, source, label]. The source is a taxonomy, "place" (the state,
 * from the LGD codes; places.php) or "publisher" (the news publisher's site). One type per section is the main filter;
 * Category is the shared one; issuer, publisher name on cards and organisation are labels, not filters (8 Oct 2026).
 * Several filters can apply at once; every filtered list is canonical to the plain list (indexing.php).
 */
const AW_LIST_FILTERS = array(
	'aw_legal'       => array( array( 'tag', 'post_tag', 'Library type' ), array( 'category_name', 'category', 'Category' ), array( 'state', 'place', 'State' ) ),
	'aw_news'        => array( array( 'publisher', 'publisher', 'Publisher' ), array( 'category_name', 'category', 'Category' ), array( 'state', 'place', 'Location' ) ),
	'post'           => array( array( 'tag', 'resource-kind', 'Resource type' ), array( 'category_name', 'category', 'Category' ) ), // resource-kind: the Resource type, plus "Sponsored" (a label) when an item has it
	'aw_opportunity' => array( array( 'tag', 'post_tag', 'Opportunity type' ), array( 'category_name', 'category', 'Category' ), array( 'state', 'place', 'Location' ) ),
	'aw_campaign'    => array( array( 'category_name', 'category', 'Category' ) ),
	'aw_press'       => array( array( 'format', 'press-format', 'Format' ) ), // Articles · Videos · Social posts
);
/** Issuer values that are courts (shown darker on Library cards). */
const AW_COURT_ISSUERS = array( 'supreme-court', 'high-court', 'national-green-tribunal' );
/** Sort orders per list: key => label; the first is the default. */
const AW_LIST_SORTS = array(
	'aw_news'        => array( 'new' => 'Newest first', 'old' => 'Oldest first' ),
	'aw_legal'       => array( 'new' => 'Newest first', 'old' => 'Oldest first', 'az' => 'A–Z' ),
	'post'           => array( 'new' => 'Newest first', 'az' => 'A–Z' ),
	'aw_opportunity' => array( 'closing' => 'Closing soon', 'new' => 'Newest first' ),
	'aw_campaign'    => array( 'new' => 'Newest first', 'old' => 'Oldest first' ),
	'aw_press'       => array( 'new' => 'Newest first', 'old' => 'Oldest first' ),
);
const AW_LIST_SIZES = array( 'aw_legal' => 20, 'aw_news' => 13, 'post' => 40, 'aw_opportunity' => 24, 'aw_campaign' => 20 );

/** Active filters on this request: param => list of value slugs (tag=a,b or tag[]=a&tag[]=b; WordPress's own query vars tag and category_name). */
function aw_active_filters( $type ) {
	$out = array();
	foreach ( AW_LIST_FILTERS[ $type ] ?? array() as $f ) {
		$raw = wp_unslash( $_GET[ $f[0] ] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		$v   = array_values( array_unique( array_filter( array_map( 'sanitize_title', is_array( $raw ) ? $raw : explode( ',', (string) $raw ) ) ) ) );
		if ( $v ) {
			$out[ $f[0] ] = $v;
		}
	}
	return $out;
}

/** The sort order on this request (a key of AW_LIST_SORTS), the list's default when none or unknown. */
function aw_active_sort( $type ) {
	$sorts = AW_LIST_SORTS[ $type ] ?? array();
	$s     = sanitize_key( wp_unslash( $_GET['sort'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	return isset( $sorts[ $s ] ) ? $s : (string) array_key_first( $sorts );
}

/** URL parameters for a set of filters (lists joined with commas). */
function aw_filter_args( $active ) {
	return array_map( fn( $v ) => implode( ',', (array) $v ), $active );
}

/** Publisher key of a news item: its site (no www.), as a URL value. Stored as aw_publisher when the source is saved. */
function aw_publisher_key( $id ) {
	$host = function_exists( 'aw_host' ) ? aw_host( (string) get_post_meta( $id, 'aw_source_url', true ) ) : '';
	return sanitize_title( $host );
}

/** Query clauses for active filters: [tax_query, meta_query, post__in or null]. */
function aw_filter_clauses( $type, $active ) {
	$tq = array( 'relation' => 'AND' );
	$mq = array();
	$in = null;
	foreach ( AW_LIST_FILTERS[ $type ] ?? array() as $f ) {
		$v = (array) ( $active[ $f[0] ] ?? array() ); // several values of one filter: any of them
		if ( ! $v ) {
			continue;
		}
		if ( 'place' === $f[1] ) {
			$any = array( 'relation' => 'OR' );
			foreach ( $v as $slug ) {
				$p     = aw_place_by_slug( $slug );
				$any[] = $p ? aw_place_meta_clause( $p ) : array( 'key' => 'aw_state_code', 'value' => '-1' );
			}
			$mq[] = $any;
		} elseif ( 'publisher' === $f[1] ) {
			$mq[] = array( 'key' => 'aw_publisher', 'value' => $v, 'compare' => 'IN' );
		} elseif ( 'press-format' === $f[1] ) {
			$mq[] = array( 'key' => 'aw_press_group', 'value' => $v, 'compare' => 'IN' );
		} elseif ( 'resource-kind' === $f[1] ) { // Resource types, or Sponsored (a label, not a type): any of them
			$cats    = array_values( array_diff( $v, array( 'sponsored' ) ) );
			$sponsor = array( 'key' => 'aw_sponsored', 'value' => '1' );
			$by_type = array( 'taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => $cats );
			if ( count( $cats ) === count( $v ) ) {
				$tq[] = $by_type;
			} elseif ( ! $cats ) {
				$mq[] = $sponsor;
			} else {
				$q  = array( 'post_type' => $type, 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' );
				$in = array_values( array_unique( array_merge( get_posts( $q + array( 'tax_query' => array( $by_type ) ) ), get_posts( $q + array( 'meta_query' => array( $sponsor ) ) ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
				$in = $in ? $in : array( 0 );
			}
		} else {
			$tq[] = array( 'taxonomy' => $f[1], 'field' => 'slug', 'terms' => $v, 'include_children' => true );
		}
	}
	return array( count( $tq ) > 1 ? $tq : array(), $mq, $in );
}

/**
 * Values of one filter among some items, with how many items have each: slug => [name, count].
 *
 * @param int[]  $ids    Post IDs.
 * @param string $source Taxonomy, "place" or "publisher".
 * @return array
 */
function aw_filter_values( $ids, $source ) {
	$seen = array();
	$name = array();
	if ( ! $ids ) {
		return array();
	}
	if ( 'place' === $source ) {
		foreach ( $ids as $id ) {
			foreach ( aw_item_codes( $id )[0] as $c ) {
				$s = aw_place_state( $c );
				if ( $s ) {
					$seen[ $s->slug ][ $id ] = 1;
					$name[ $s->slug ]        = $s->name;
				}
			}
		}
	} elseif ( 'publisher' === $source ) {
		$pubs = function_exists( 'aw_publishers' ) ? aw_publishers() : array();
		foreach ( $ids as $id ) {
			$k = (string) get_post_meta( $id, 'aw_publisher', true );
			if ( '' !== $k ) {
				$host            = aw_host( (string) get_post_meta( $id, 'aw_source_url', true ) );
				$seen[ $k ][ $id ] = 1;
				$name[ $k ]      = $name[ $k ] ?? ( ! empty( $pubs[ $host ]['name'] ) ? $pubs[ $host ]['name'] : ( function_exists( 'aw_publisher_of' ) ? aw_publisher_of( $id )[0] : $host ) );
			}
		}
	} elseif ( 'press-format' === $source ) {
		foreach ( $ids as $id ) {
			$k = (string) get_post_meta( $id, 'aw_press_group', true );
			if ( isset( AW_PRESS_GROUPS[ $k ] ) ) {
				$seen[ $k ][ $id ] = 1;
				$name[ $k ]        = AW_PRESS_GROUPS[ $k ];
			}
		}
	} else {
		if ( 'resource-kind' === $source ) { // "Sponsored" only when an item is sponsored
			update_meta_cache( 'post', $ids );
			foreach ( $ids as $id ) {
				if ( get_post_meta( $id, 'aw_sponsored', true ) ) {
					$seen['sponsored'][ $id ] = 1;
					$name['sponsored']        = 'Sponsored';
				}
			}
			$source = 'post_tag';
		}
		foreach ( wp_get_object_terms( $ids, $source, array( 'fields' => 'all_with_object_id' ) ) as $t ) {
			$seen[ $t->slug ][ $t->object_id ] = 1;
			$name[ $t->slug ]                  = $t->name;
		}
	}
	$out = array();
	foreach ( $seen as $slug => $set ) {
		$out[ $slug ] = array( $name[ $slug ], count( $set ) );
	}
	uasort( $out, fn( $a, $b ) => strcasecmp( $a[0], $b[0] ) );
	return $out;
}

/**
 * The filter bar for a list: one dropdown per filter (several choices; counts respect the other active filters; values
 * with no items hidden; a filter with one value hidden), Sort on the right, and the active filters as removable chips.
 */
function aw_list_filters( $type ) {
	static $shown = array();
	if ( isset( $shown[ $type ] ) ) {
		return ''; // already in the section band
	}
	$shown[ $type ] = true;
	$active = aw_active_filters( $type );
	$sort   = aw_active_sort( $type );
	$sorts  = AW_LIST_SORTS[ $type ] ?? array();
	$keep   = $sort !== array_key_first( $sorts ) ? array( 'sort' => $sort ) : array();
	$all    = aw_hub_url( $type );
	$groups = '';
	$chips  = array();
	foreach ( AW_LIST_FILTERS[ $type ] ?? array() as $f ) {
		list( $param, $source, $label ) = $f;
		$mine   = $active[ $param ] ?? array();
		$others = array_diff_key( $active, array( $param => '' ) );
		// Items matching the other filters: the counts for this filter.
		list( $tq, $mq, $in ) = aw_filter_clauses( $type, $others );
		$ids     = get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'tax_query' => $tq, 'meta_query' => $mq ) + ( null === $in ? array() : array( 'post__in' => $in ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$options = array();
		$allow   = in_array( $source, array( 'post_tag', 'resource-kind' ), true ) ? array_merge( aw_section_tags( $type ), array( 'sponsored' ) ) : ( 'category' === $source ? aw_section_categories( $type ) : null ); // only this section's lists (Sourcing)
		foreach ( aw_filter_values( $ids, $source ) as $slug => $v ) {
			if ( null !== $allow && ! in_array( (string) $slug, $allow, true ) ) {
				continue;
			}
			$on        = in_array( (string) $slug, $mine, true );
			$options[] = array( $v[0], (string) $slug, $on, $v[1] );
			if ( $on ) {
				$rest    = array_values( array_diff( $mine, array( (string) $slug ) ) );
				$without = $rest ? array_merge( $active, array( $param => $rest ) ) : $others;
				$chips[] = array( $label . ': ' . $v[0], add_query_arg( aw_filter_args( $without ) + $keep, $all ) );
			}
		}
		$groups .= kits_filter_dropdown( $label, $param, $options ); // every hub the same (owner, 14 Oct 2026): one button per filter, Sort beside them; a "Filters" sheet on phones
	}
	$sort_opts = array();
	if ( (int) ( wp_count_posts( $type )->publish ?? 0 ) < 2 ) {
		$sorts = array(); // nothing to order with fewer than 2 items
	}
	foreach ( $sorts as $k => $l ) {
		$sort_opts[] = array( $l, add_query_arg( aw_filter_args( $active ) + ( array_key_first( $sorts ) === $k ? array() : array( 'sort' => $k ) ), $all ), $k === $sort );
	}
	$sort_html = function_exists( 'kits_sort_dropdown' ) ? kits_sort_dropdown( $sort_opts ) : '';
	return function_exists( 'kits_filter_bar' ) ? kits_filter_bar( $groups, $chips, $all, $sort_html, $keep ) : '';
}

/** Keep the publisher key current when a news item's source changes. */
function aw_publisher_sync( $mid, $id, $key ) {
	if ( 'aw_source_url' === $key && 'aw_news' === get_post_type( $id ) ) {
		$k = aw_publisher_key( $id );
		'' === $k ? delete_post_meta( $id, 'aw_publisher' ) : update_post_meta( $id, 'aw_publisher', $k );
	}
}
add_action( 'updated_post_meta', 'aw_publisher_sync', 10, 3 );
add_action( 'added_post_meta', 'aw_publisher_sync', 10, 3 );
// Fill the publisher key on every news item once.
add_action(
	'init',
	function () {
		if ( '1' === get_option( 'aw_publisher_key_done' ) ) {
			return;
		}
		foreach ( get_posts( array( 'post_type' => 'aw_news', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) {
			$k = aw_publisher_key( $id );
			if ( '' !== $k ) {
				update_post_meta( $id, 'aw_publisher', $k );
			}
		}
		update_option( 'aw_publisher_key_done', '1', false );
	},
	30
);

/* ---------- TRUST: source, dates, verification ---------- */

add_shortcode(
	'aw_trust',
	function () {
		$id   = get_the_ID();
		$type = get_post_type( $id );
		$rows = array();
		if ( 'aw_listing' === $type ) {
			$verified = (bool) get_post_meta( $id, 'aw_verified', true );
			$on       = (string) get_post_meta( $id, 'aw_verified_on', true );
			$code     = (string) get_post_meta( $id, 'aw_awbi_code', true );
			$rows[]   = $verified
				? array( 'check', 'Verified by Animal Writes' . ( $on ? ' on ' . aw_date( $on ) : '' ), true )
				: array( 'alert', 'Not yet confirmed by the organisation. Check details before you travel.' );
			if ( $code ) {
				$since  = (string) get_post_meta( $id, 'aw_awbi_recognised', true );
				$until  = (string) get_post_meta( $id, 'aw_awbi_valid', true );
				$rows[] = aw_awbi_lapsed( $id )
					? array( 'alert', 'AWBI recognition (code ' . esc_html( $code ) . ') was valid until ' . aw_date( $until ) . '. Needs re-check.', true )
					: array( 'research', 'Recognised by the Animal Welfare Board of India, code ' . esc_html( $code ) . ( $since ? ', since ' . aw_date( $since ) : '' ) . ( $until ? ', valid until ' . aw_date( $until ) : '' ) . ' (list as on ' . aw_date( '2026-05-31' ) . ')', true );
			}
			$abc = (string) get_post_meta( $id, 'aw_abc_valid', true );
			if ( $abc ) {
				$rows[] = array( 'research', 'ABC project recognised by the AWBI, valid until ' . aw_date( $abc ), true );
			}
			$labels = aw_source_labels( $id, true );
			if ( $labels ) {
				$rows[] = array( 'link', 'Details from: ' . $labels, true );
			}
			$cls = $verified ? 'is-verified' : 'is-unverified';
		} else {
			$src  = (string) get_post_meta( $id, 'aw_source_url', true );
			$name = (string) get_post_meta( $id, 'aw_source_name', true );
			$iss  = aw_item_issuer( $id );
			if ( $iss ) {
				$rows[] = array( 'legal', 'Issued by ' . $iss->name );
			}
			if ( $src ) {
				$host   = $name ? $name : wp_parse_url( $src, PHP_URL_HOST );
				// Library pages have no source row above the title: the "also reported by" logos follow the publisher here.
				$also   = 'aw_legal' === $type && function_exists( 'aw_also_logos_html' ) ? aw_also_logos_html( $id ) : '';
				$rows[] = array( 'link', 'Source: <a href="' . esc_url( $src ) . '" rel="noopener">' . esc_html( $host ) . '</a>' . $also, true );
			}
			// "Also reported by" is shown as publisher logos in the source row (same-story.php), not as text lines here.
			$t      = fn( $iso, $text ) => aw_date( $iso ); // the one date component
			$pub    = get_post_meta( $id, 'aw_source_date', true ) ? aw_src_date( $id, 'Y-m-d' ) : '';
			$added  = get_the_date( 'Y-m-d', $id );
			$rows[] = array(
				'',
				$pub === $added ? 'Published ' . $t( $pub, '' ) // same day: one date (owner, 15 Oct 2026)
					: ( $pub ? 'Published ' . $t( $pub, '' ) . ' · added ' : 'Added ' ) . $t( $added, '' ),
				true,
			);
			if ( get_post_meta( $id, 'aw_file_id', true ) ) {
				$rows[] = array( 'research', 'Official document attached below' );
			} elseif ( get_post_meta( $id, 'aw_doc_preview', true ) ) {
				$rows[] = array( 'research', 'Official document linked below' );
			}
			if ( in_array( $type, array( 'aw_news', 'aw_legal' ), true ) ) {
				$rows[] = array( 'knowledge', 'Summary written by Animal Writes. Read the original before relying on it. <a href="/editorial-policy/">Editorial policy</a>', true );
			}
			$cls = 'is-sourced';
		}
		if ( ! $rows ) {
			return '';
		}
		$out = '<aside class="kit-trust ' . $cls . '" aria-label="About this information"><ul>';
		foreach ( $rows as $r ) {
			$out .= '<li>' . ( $r[0] ? aw_i( $r[0] ) : '' ) . '<span>' . ( empty( $r[2] ) ? esc_html( $r[1] ) : wp_kses( $r[1], array( 'a' => array( 'href' => true, 'rel' => true, 'target' => true, 'class' => true, 'title' => true, 'aria-label' => true ), 'span' => array( 'class' => true, 'title' => true, 'aria-hidden' => true ), 'time' => array( 'datetime' => true ), 'ul' => array( 'class' => true, 'aria-label' => true ), 'li' => array(), 'img' => array( 'class' => true, 'src' => true, 'alt' => true, 'width' => true, 'height' => true, 'loading' => true, 'decoding' => true ) ) ) ) . '</span></li>';
		}
		return $out . '</ul></aside>';
	}
);

/*
 * Query blocks filtered by a taxonomy that has no public pages (e.g. the Library's "kind": act, rule, template).
 * WordPress drops such filters ("taxQuery" needs a viewable taxonomy), so the home page's "Laws and rules" and
 * "Letters and templates" columns showed the same items. Apply those filters here.
 */
add_filter(
	'query_loop_block_query_vars',
	function ( $vars, $block ) {
		$in = (array) ( $block->context['query']['taxQuery'] ?? array() );
		$in = array_diff_key( $in, array( 'include' => 1, 'exclude' => 1 ) ) ? $in : (array) ( $in['include'] ?? array() );
		foreach ( $in as $tax => $terms ) {
			if ( taxonomy_exists( $tax ) && ! is_taxonomy_viewable( $tax ) && $terms ) {
				$vars['tax_query']   = (array) ( $vars['tax_query'] ?? array() );
				$vars['tax_query'][] = array( 'taxonomy' => $tax, 'terms' => array_filter( array_map( 'intval', (array) $terms ) ), 'include_children' => false );
			}
		}
		return $vars;
	},
	10,
	2
);
