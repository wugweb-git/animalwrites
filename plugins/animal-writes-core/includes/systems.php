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
 * Topic is the shared one; issuer, publisher name on cards and organisation are labels, not filters (8 Oct 2026).
 * Several filters can apply at once; every filtered list is canonical to the plain list (indexing.php).
 */
const AW_LIST_FILTERS = array(
	'aw_legal'       => array( array( 'kind', 'aw_legal_kind', 'Library type' ), array( 'topic', 'aw_topic', 'Category' ), array( 'state', 'place', 'State' ) ),
	'aw_news'        => array( array( 'publisher', 'publisher', 'Publisher' ), array( 'topic', 'aw_topic', 'Category' ), array( 'state', 'place', 'Location' ) ),
	'aw_guide'       => array( array( 'kind', 'aw_resource_type', 'Resource type' ), array( 'topic', 'aw_topic', 'Category' ) ),
	'aw_opportunity' => array( array( 'type', 'aw_opportunity_type', 'Opportunity type' ), array( 'topic', 'aw_topic', 'Category' ), array( 'state', 'place', 'Location' ) ),
	'aw_campaign'    => array( array( 'topic', 'aw_topic', 'Category' ) ),
	'aw_press'       => array( array( 'format', 'press-format', 'Format' ) ), // Articles · Videos · Social posts
);
/** Issuer values that are courts (shown darker on Library cards). */
const AW_COURT_ISSUERS = array( 'supreme-court', 'high-court', 'national-green-tribunal' );
/** Sort orders per list: key => label; the first is the default. */
const AW_LIST_SORTS = array(
	'aw_news'        => array( 'new' => 'Newest first', 'old' => 'Oldest first' ),
	'aw_legal'       => array( 'new' => 'Newest first', 'old' => 'Oldest first', 'az' => 'A–Z' ),
	'aw_guide'       => array( 'new' => 'Newest first', 'az' => 'A–Z' ),
	'aw_opportunity' => array( 'closing' => 'Closing soon', 'new' => 'Newest first' ),
	'aw_campaign'    => array( 'new' => 'Newest first', 'old' => 'Oldest first' ),
	'aw_press'       => array( 'new' => 'Newest first', 'old' => 'Oldest first' ),
);
const AW_LIST_SIZES = array( 'aw_legal' => 20, 'aw_news' => 13, 'aw_guide' => 40, 'aw_opportunity' => 24, 'aw_campaign' => 20 );

/** Active filters on this request: param => list of value slugs (topic=a,b or topic[]=a&topic[]=b). */
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

/** Query clauses for active filters: [tax_query, meta_query]. */
function aw_filter_clauses( $type, $active ) {
	$tq = array( 'relation' => 'AND' );
	$mq = array();
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
		} else {
			$tq[] = array( 'taxonomy' => $f[1], 'field' => 'slug', 'terms' => $v, 'include_children' => true );
		}
	}
	return array( count( $tq ) > 1 ? $tq : array(), $mq );
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
		list( $tq, $mq ) = aw_filter_clauses( $type, $others );
		$ids     = get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'tax_query' => $tq, 'meta_query' => $mq ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$options = array();
		foreach ( aw_filter_values( $ids, $source ) as $slug => $v ) {
			$on        = in_array( (string) $slug, $mine, true );
			$options[] = array( $v[0], (string) $slug, $on, $v[1] );
			if ( $on ) {
				$rest    = array_values( array_diff( $mine, array( (string) $slug ) ) );
				$without = $rest ? array_merge( $active, array( $param => $rest ) ) : $others;
				$chips[] = array( $label . ': ' . $v[0], add_query_arg( aw_filter_args( $without ) + $keep, $all ) );
			}
		}
		$groups .= count( AW_LIST_FILTERS[ $type ] ?? array() ) > 2 ? kits_filter_section( $label, $param, $options ) : kits_filter_dropdown( $label, $param, $options ); // more than two filters: one Filters menu
	}
	$sort_opts = array();
	foreach ( $sorts as $k => $l ) {
		$sort_opts[] = array( $l, add_query_arg( aw_filter_args( $active ) + ( array_key_first( $sorts ) === $k ? array() : array( 'sort' => $k ) ), $all ), $k === $sort );
	}
	$sort_html = function_exists( 'kits_sort_dropdown' ) ? kits_sort_dropdown( $sort_opts ) : '';
	if ( count( AW_LIST_FILTERS[ $type ] ?? array() ) > 2 ) {
		$groups = kits_filter_menu( $groups, count( $chips ) );
	}
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
				? array( 'check', 'Verified by Animal Writes' . ( $on ? ' on ' . wp_date( 'j M Y', strtotime( $on ) ) : '' ) )
				: array( 'alert', 'Not yet confirmed by the organisation. Check details before you travel.' );
			if ( $code ) {
				$since  = (string) get_post_meta( $id, 'aw_awbi_recognised', true );
				$until  = (string) get_post_meta( $id, 'aw_awbi_valid', true );
				$rows[] = aw_awbi_lapsed( $id )
					? array( 'alert', 'AWBI recognition (code ' . $code . ') was valid until ' . wp_date( 'j M Y', strtotime( $until ) ) . '. Needs re-check.' )
					: array( 'research', 'Recognised by the Animal Welfare Board of India, code ' . $code . ( $since ? ', since ' . wp_date( 'j M Y', strtotime( $since ) ) : '' ) . ( $until ? ', valid until ' . wp_date( 'j M Y', strtotime( $until ) ) : '' ) . ' (list as on 31 May 2026)' );
			}
			$abc = (string) get_post_meta( $id, 'aw_abc_valid', true );
			if ( $abc ) {
				$rows[] = array( 'research', 'ABC project recognised by the AWBI, valid until ' . wp_date( 'j M Y', strtotime( $abc ) ) );
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
				$rows[] = array( 'link', 'Source: <a href="' . esc_url( $src ) . '" rel="noopener">' . esc_html( $host ) . '</a>', true );
			}
			foreach ( (array) get_post_meta( $id, 'aw_also_reported', true ) as $also ) {
				if ( ! empty( $also['url'] ) ) {
					$rows[] = array( 'link', 'Also reported by <a href="' . esc_url( $also['url'] ) . '" rel="noopener">' . esc_html( $also['name'] ?? wp_parse_url( $also['url'], PHP_URL_HOST ) ) . '</a>', true );
				}
			}
			$rows[] = array( 'calendar', ( get_post_meta( $id, 'aw_source_date', true ) ? 'Published ' . aw_src_date( $id ) . ' · added ' : 'Added ' ) . get_the_date( 'j M Y', $id ) );
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
			$out .= '<li>' . aw_i( $r[0] ) . '<span>' . ( empty( $r[2] ) ? esc_html( $r[1] ) : wp_kses( $r[1], array( 'a' => array( 'href' => true, 'rel' => true, 'target' => true ), 'span' => array( 'class' => true, 'title' => true, 'aria-hidden' => true ) ) ) ) . '</span></li>';
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
