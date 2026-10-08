<?php
/**
 * Four product systems instead of one stretched archive template.
 *
 *   HELP          [aw_directory_results] [aw_tasks] [aw_resources_index]
 *   INTELLIGENCE  [aw_legal_index] [aw_news_index] [aw_library]
 *   ACTION        [aw_opps_index]
 *   ARCHIVE       [aw_campaigns_index]
 *   TRUST         [aw_trust]  source, dates and verification on every item page
 *
 * Archive views render the main query (so pagination, filters and counts stay correct).
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
	return '<span class="aw-ico aw-ico--' . esc_attr( $name ) . '" aria-hidden="true"></span>';
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

/** Pagination for the main query. */
function aw_pager() {
	$links = paginate_links( array( 'type' => 'array', 'prev_text' => 'Previous', 'next_text' => 'Next' ) );
	$ad = function_exists( 'kits_list_ad' ) ? kits_list_ad() : ''; // section-list ad at the end of the list
	return $ad . ( $links ? '<nav class="aw-pager" aria-label="Pages">' . implode( '', $links ) . '</nav>' : '' );
}

/** Empty state. */
function aw_empty( $text ) {
	return '<div class="aw-empty"><p>' . esc_html( $text ) . '</p></div>';
}

/**
 * List filters: post type => list of [query parameter, source, label]. The source is a taxonomy, "place" (the state,
 * from the LGD codes; places.php) or "publisher" (the news publisher's site). One type per section is the main filter;
 * Topic is the shared one; issuer, publisher name on cards and organisation are labels, not filters (8 Oct 2026).
 * Several filters can apply at once; every filtered list is canonical to the plain list (indexing.php).
 */
const AW_LIST_FILTERS = array(
	'aw_legal'       => array( array( 'kind', 'aw_legal_kind', 'Library type' ), array( 'topic', 'aw_topic', 'Topic' ), array( 'state', 'place', 'State' ) ),
	'aw_news'        => array( array( 'publisher', 'publisher', 'Publisher' ), array( 'topic', 'aw_topic', 'Topic' ), array( 'state', 'place', 'Location' ) ),
	'aw_guide'       => array( array( 'kind', 'aw_resource_type', 'Resource type' ), array( 'topic', 'aw_topic', 'Topic' ) ),
	'aw_opportunity' => array( array( 'type', 'aw_opportunity_type', 'Opportunity type' ), array( 'topic', 'aw_topic', 'Topic' ), array( 'state', 'place', 'Location' ) ),
	'aw_campaign'    => array( array( 'topic', 'aw_topic', 'Topic' ) ),
);
/** Issuer values that are courts (shown darker on Library cards). */
const AW_COURT_ISSUERS = array( 'supreme-court', 'high-court', 'national-green-tribunal' );
const AW_LIST_SIZES = array( 'aw_legal' => 20, 'aw_news' => 13, 'aw_guide' => 40, 'aw_opportunity' => 24, 'aw_campaign' => 20 );

/** Active filters on this request: param => value slug. */
function aw_active_filters( $type ) {
	$out = array();
	foreach ( AW_LIST_FILTERS[ $type ] ?? array() as $f ) {
		$v = sanitize_title( wp_unslash( $_GET[ $f[0] ] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( $v ) {
			$out[ $f[0] ] = $v;
		}
	}
	return $out;
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
		$v = $active[ $f[0] ] ?? '';
		if ( '' === $v ) {
			continue;
		}
		if ( 'place' === $f[1] ) {
			$p    = aw_place_by_slug( $v );
			$mq[] = $p ? aw_place_meta_clause( $p ) : array( 'key' => 'aw_state_code', 'value' => '-1' );
		} elseif ( 'publisher' === $f[1] ) {
			$mq[] = array( 'key' => 'aw_publisher', 'value' => $v );
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

add_action(
	'pre_get_posts',
	function ( $q ) {
		if ( is_admin() || ! $q->is_main_query() ) {
			return;
		}
		foreach ( AW_LIST_SIZES as $type => $size ) {
			if ( ! $q->is_post_type_archive( $type ) ) {
				continue;
			}
			$q->set( 'posts_per_page', $size );
			list( $tq, $mq ) = aw_filter_clauses( $type, aw_active_filters( $type ) );
			if ( $tq ) {
				$q->set( 'tax_query', $tq );
			}
			if ( $mq ) {
				$q->set( 'meta_query', array_merge( (array) $q->get( 'meta_query' ), $mq ) );
			}
		}
	},
	30
);

/**
 * The filter bar for a list: one labelled group per filter, counts that respect the other active filters, values with
 * no items hidden, a group hidden while it has one option, and the active filters with "Remove" and "Clear all".
 */
function aw_list_filters( $type ) {
	static $shown = array();
	if ( isset( $shown[ $type ] ) ) {
		return ''; // already in the section band
	}
	$shown[ $type ] = true;
	$active = aw_active_filters( $type );
	$all    = get_post_type_archive_link( $type );
	$groups = '';
	$chips  = array();
	foreach ( AW_LIST_FILTERS[ $type ] ?? array() as $f ) {
		list( $param, $source, $label ) = $f;
		$others = array_diff_key( $active, array( $param => '' ) );
		$base   = add_query_arg( $others, $all );
		// Items matching the other filters: the counts for this group.
		list( $tq, $mq ) = aw_filter_clauses( $type, $others );
		$ids     = get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'tax_query' => $tq, 'meta_query' => $mq ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$options = array( array( 'All', $base, ! isset( $active[ $param ] ), null ) );
		foreach ( aw_filter_values( $ids, $source ) as $slug => $v ) {
			$on        = ( $active[ $param ] ?? '' ) === (string) $slug;
			$options[] = array( $v[0], add_query_arg( $param, $slug, $base ), $on, $v[1] );
			if ( $on ) {
				$chips[] = array( $label . ': ' . $v[0], $base );
			}
		}
		$groups .= function_exists( 'kits_filter_group' ) ? kits_filter_group( $label, $options ) : '';
	}
	return function_exists( 'kits_filter_bar' ) ? kits_filter_bar( $groups, $chips, $all ) : '';
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
				$rows[] = array( 'research', 'Recognised by the Animal Welfare Board of India, code ' . $code . ' (list as on 31 May 2026)' );
			}
			$abc = (string) get_post_meta( $id, 'aw_abc_valid', true );
			if ( $abc ) {
				$rows[] = array( 'research', 'ABC project recognised by the AWBI, valid until ' . wp_date( 'j M Y', strtotime( $abc ) ) );
			}
			$labels = aw_source_labels( $id, true );
			if ( $labels ) {
				$rows[] = array( 'arrow-up-right', 'Details from: ' . $labels, true );
			}
			$cls = $verified ? 'is-verified' : 'is-unverified';
		} else {
			$src  = (string) get_post_meta( $id, 'aw_source_url', true );
			$name = (string) get_post_meta( $id, 'aw_source_name', true );
			$iss  = aw_first_term( $id, 'aw_source_type' );
			if ( $iss ) {
				$rows[] = array( 'legal', 'Issued by ' . $iss->name );
			}
			if ( $src ) {
				$host   = $name ? $name : wp_parse_url( $src, PHP_URL_HOST );
				$rows[] = array( 'arrow-up-right', 'Source: <a href="' . esc_url( $src ) . '" rel="noopener">' . esc_html( $host ) . '</a>', true );
			}
			foreach ( (array) get_post_meta( $id, 'aw_also_reported', true ) as $also ) {
				if ( ! empty( $also['url'] ) ) {
					$rows[] = array( 'arrow-up-right', 'Also reported by <a href="' . esc_url( $also['url'] ) . '" rel="noopener">' . esc_html( $also['name'] ?? wp_parse_url( $also['url'], PHP_URL_HOST ) ) . '</a>', true );
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
		$out = '<aside class="aw-trust ' . $cls . '" aria-label="About this information"><ul>';
		foreach ( $rows as $r ) {
			$out .= '<li>' . aw_i( $r[0] ) . '<span>' . ( empty( $r[2] ) ? esc_html( $r[1] ) : wp_kses( $r[1], array( 'a' => array( 'href' => true, 'rel' => true, 'target' => true ), 'span' => array( 'class' => true, 'title' => true, 'aria-hidden' => true ) ) ) ) . '</span></li>';
		}
		return $out . '</ul></aside>';
	}
);

/* ---------- INTELLIGENCE: Legal Watch as a case/update register ---------- */

add_shortcode(
	'aw_legal_index',
	function () {
		global $wp_query;
		$out = aw_list_filters( 'aw_legal' );
		if ( ! $wp_query->have_posts() ) {
			return $out . aw_empty( 'No updates match this filter yet.' );
		}
		$out .= '<div class="aw-cases">';
		while ( $wp_query->have_posts() ) {
			$wp_query->the_post();
			$id    = get_the_ID();
			// Templates are tools, not documents: their own card, with no date and no source line.
			if ( has_term( 'template', 'aw_legal_kind', $id ) ) {
				$out  .= '<article class="aw-case aw-case--template">'
					. '<div class="aw-case-date aw-case-icon">' . aw_i( 'resources' ) . '</div>'
					. '<div class="aw-case-body"><div class="aw-case-tags kit-tags">' . kits_tag( 'Template', 'accent' )
					. '</div>'
					. '<h2 class="aw-case-title"><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></h2>'
					. '<p class="aw-case-sum">' . esc_html( aw_text( $id, 30 ) ) . '</p>'
					. '</div>' . aw_i( 'arrow-right' ) . '</article>';
				continue;
			}
			$iss   = aw_first_term( $id, 'aw_source_type' );
			$topic = aw_first_term( $id, 'aw_topic' );
			$src   = (string) get_post_meta( $id, 'aw_source_name', true );
			$doc   = (bool) get_post_meta( $id, 'aw_file_id', true );
			// Acts and Rules show the year of the law (from its title), not the date we added them.
			$year  = has_term( array( 'act', 'rule' ), 'aw_legal_kind', $id ) && preg_match( '/\b(1[89]|20)\d{2}\b/', get_the_title(), $ym ) ? $ym[0] : '';
			$date  = $year ? '<div class="aw-case-date"><span class="d">' . esc_html( $year ) . '</span></div>'
				: '<div class="aw-case-date"><span class="d">' . esc_html( aw_src_date( $id, 'j' ) ) . '</span><span class="m">' . esc_html( aw_src_date( $id, 'M Y' ) ) . '</span></div>';
			$out  .= '<article class="aw-case">'
				. $date
				. '<div class="aw-case-body"><div class="aw-case-tags kit-tags">'
				. ( $iss ? kits_tag( $iss->name, in_array( $iss->slug, AW_COURT_ISSUERS, true ) ? 'ink' : 'info' ) : '' )
				. ( $topic ? kits_tag( $topic->name ) : '' ) . '</div>'
				. '<h2 class="aw-case-title"><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></h2>'
				. '<p class="aw-case-sum">' . esc_html( aw_text( $id, 30 ) ) . '</p>'
				. '<p class="aw-case-src">' . ( $src ? 'Source: ' . esc_html( $src ) : '' ) . ( $doc ? ' <span class="aw-doc">' . aw_i( 'research' ) . ' Official document</span>' : '' ) . '</p>'
				. '</div>' . aw_i( 'arrow-right' ) . '</article>';
		}
		wp_reset_postdata();
		return $out . '</div>' . aw_pager();
	}
);

/* ---------- INTELLIGENCE: News with editorial hierarchy ---------- */

/**
 * News card.
 *
 * @param int    $id   Post ID.
 * @param string $size lead|card.
 * @return string
 */
function aw_story( $id, $size = 'card' ) {
	$img   = aw_item_image( $id, 'lead' === $size ? 'large' : 'medium_large' );
	$img   = $img ? $img : (string) get_the_post_thumbnail_url( $id, 'large' );
	$src   = (string) get_post_meta( $id, 'aw_source_name', true );
	$topic = aw_first_term( $id, 'aw_topic' );
	$media = $img
		? '<img src="' . esc_url( $img ) . '" alt="" loading="' . ( 'lead' === $size ? 'eager' : 'lazy' ) . '" decoding="async" referrerpolicy="no-referrer" onerror="this.parentNode.classList.add(\'is-failed\')">'
		: '';
	return '<article class="aw-story aw-story--' . esc_attr( $size ) . '">'
		. '<div class="aw-story-media' . ( $img ? '' : ' is-failed' ) . '">' . $media . '<span class="aw-story-ph">' . esc_html( $topic ? $topic->name : 'News' ) . '</span></div>'
		. '<div class="aw-story-body">'
		. aw_source_row_html( $id )
		. '<h2 class="aw-story-title"><a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a></h2>'
		. ( 'lead' === $size ? '<p class="aw-story-sum">' . esc_html( aw_text( $id, 40 ) ) . '</p>' : '' )
		. '<p class="aw-story-meta">' . aw_rel_time_html( $id ) . '</p>'
		. '</div></article>';
}

add_shortcode(
	'aw_news_index',
	function () {
		global $wp_query;
		$out = aw_list_filters( 'aw_news' );
		if ( ! $wp_query->have_posts() ) {
			return $out . aw_empty( 'No stories on this topic yet.' );
		}
		$ids  = wp_list_pluck( $wp_query->posts, 'ID' );
		$lead = ( ! is_paged() ) ? array_shift( $ids ) : null;
		if ( $lead ) {
			$out .= '<div class="aw-news-top">' . aw_story( $lead, 'lead' ) . '<div class="aw-news-side">';
			foreach ( array_splice( $ids, 0, 3 ) as $id ) {
				$out .= aw_story( $id, 'row' );
			}
			$out .= '</div></div>';
		}
		if ( $ids ) {
			$out .= '<div class="aw-news-grid">';
			foreach ( $ids as $id ) {
				$out .= aw_story( $id, 'card' );
			}
			$out .= '</div>';
		}
		return $out . aw_pager();
	}
);

/* ---------- INTELLIGENCE: Research as a document library ---------- */


/* ---------- HELP: Knowledge as tasks ---------- */

add_shortcode(
	'aw_tasks',
	function () {
		global $wp_query;
		$icons = array( 'injur' => 'injured', 'cruel' => 'alert', 'birth' => 'knowledge', 'abc' => 'knowledge', 'caring' => 'opportunities', 'communit' => 'opportunities' );
		$out   = aw_list_filters( 'aw_guide' ) . '<div class="aw-tasks">';
		while ( $wp_query->have_posts() ) {
			$wp_query->the_post();
			$id   = get_the_ID();
			$slug = get_post_field( 'post_name', $id );
			$ico  = 'knowledge';
			foreach ( $icons as $k => $v ) {
				if ( false !== strpos( $slug, $k ) ) {
					$ico = $v;
					break;
				}
			}
			$mins = max( 1, (int) round( str_word_count( wp_strip_all_tags( get_post_field( 'post_content', $id ) ) ) / 200 ) );
			$img  = get_the_post_thumbnail_url( $id, 'medium_large' );
			$out .= '<a class="aw-task" href="' . esc_url( get_permalink() ) . '">'
				. ( $img ? '<span class="aw-task-media"><img src="' . esc_url( $img ) . '" alt="" loading="lazy"></span>' : '' )
				. '<span class="aw-task-body">' . aw_i( $ico ) . '<strong>' . esc_html( get_the_title() ) . '</strong><span class="aw-task-sum">' . esc_html( aw_text( $id, 22 ) ) . '</span>'
				. '<span class="aw-task-meta">' . aw_i( 'calendar' ) . ' Updated ' . esc_html( get_the_modified_date( 'j M Y' ) ) . ' · ' . (int) $mins . ' min read</span></span></a>';
		}
		wp_reset_postdata();
		$out .= '</div>';
		$out .= '<div class="aw-also"><h2>Also useful</h2><ul class="aw-tiles">'
			. '<li><a class="aw-tile" href="/helplines/">' . aw_i( 'helpline' ) . '<strong>Helplines</strong><span class="aw-tile-desc">Official numbers for every state.</span>' . aw_i( 'arrow-right' ) . '</a></li>'
			. '<li><a class="aw-tile" href="/directory/">' . aw_i( 'directory' ) . '<strong>Find an organisation</strong><span class="aw-tile-desc">Shelters, vets and rescuers near you.</span>' . aw_i( 'arrow-right' ) . '</a></li>'
			. '<li><a class="aw-tile" href="/library/?kind=act">' . aw_i( 'research' ) . '<strong>The law in full</strong><span class="aw-tile-desc">Acts, Rules and judgments, from the official source.</span>' . aw_i( 'arrow-right' ) . '</a></li>'
			. '</ul></div>';
		return $out;
	}
);

/* ---------- HELP: Resources by format and language ---------- */


/* ---------- HELP: Directory results as scannable rows ---------- */

add_shortcode(
	'aw_directory_results',
	function () {
		global $wp_query;
		$hub = get_post_type_archive_link( 'aw_listing' );
		if ( ! $wp_query->have_posts() ) {
			return '<div class="aw-empty"><p>No organisations match. Try another district, or clear the filters.</p><p><a class="kit-btn kit-btn--secondary" href="' . esc_url( $hub ) . '">Clear filters</a></p></div>';
		}
		// Count line and sort (Phase 11 E3).
		$n      = (int) $wp_query->found_posts;
		$q      = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$where  = array_filter( array( $q ? '“' . $q . '”' : '', aw_current_place() ? aw_current_place()->name : '', function_exists( 'aw_current_type_name' ) ? aw_current_type_name() : '' ) );
		$line   = $where ? sprintf( _n( '%1$s result for %2$s', '%1$s results for %2$s', $n ), number_format_i18n( $n ), implode( ', ', $where ) ) : sprintf( _n( '%s organisation', '%s organisations', $n ), number_format_i18n( $n ) );
		$sort   = 'details' === ( $_GET['sort'] ?? '' ) ? 'details' : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$here   = remove_query_arg( array( 'sort', 'paged' ) );
		$out    = '<div class="aw-dir-results-head"><p role="status">' . esc_html( $line ) . '</p>'
			. '<nav class="kit-segment" aria-label="Sort"><a href="' . esc_url( $here ) . '"' . ( $sort ? '' : ' aria-current="true"' ) . '>A–Z</a><a href="' . esc_url( add_query_arg( 'sort', 'details', $here ) ) . '"' . ( $sort ? ' aria-current="true"' : '' ) . '>Most details first</a></nav></div>';
		$out   .= '<div class="aw-orgs">';
		while ( $wp_query->have_posts() ) {
			$wp_query->the_post();
			$id       = get_the_ID();
			$name     = get_the_title();
			$verified = (bool) get_post_meta( $id, 'aw_verified', true );
			$featured = (int) get_post_meta( $id, 'aw_featured', true );
			$area     = (string) get_post_meta( $id, 'aw_area', true );
			$phone    = (string) get_post_meta( $id, 'aw_phone', true );
			$types    = get_the_terms( $id, 'aw_listing_type' );
			$logo     = get_the_post_thumbnail_url( $id, 'thumbnail' );
			$words    = preg_split( '/\s+/', preg_replace( '/[^\p{L}\s]/u', '', $name ) );
			$initials = mb_strtoupper( mb_substr( $words[0] ?? '', 0, 1 ) . mb_substr( $words[1] ?? '', 0, 1 ) );
			$out     .= '<article class="aw-org' . ( $featured ? ' is-featured' : '' ) . '">'
				. '<div class="aw-org-mark">' . ( $logo ? '<img src="' . esc_url( $logo ) . '" alt="" loading="lazy">' : '<span aria-hidden="true">' . esc_html( $initials ) . '</span>' ) . '</div>'
				. '<div class="aw-org-body"><h2 class="aw-org-name"><a href="' . esc_url( get_permalink() ) . '">' . esc_html( $name ) . '</a></h2>'
				. '<p class="aw-org-meta">' . ( $area ? aw_i( 'directory' ) . esc_html( $area ) : '' ) . '</p>'
				. '<p class="aw-org-tags kit-tags">' . ( $verified ? kits_badge( 'Verified', 'verified' ) : '' ) . ( $featured ? kits_badge( 'Featured', 'featured' ) : '' ) . aw_source_labels( $id );
			foreach ( ( $types && ! is_wp_error( $types ) ) ? array_slice( $types, 0, 2 ) : array() as $t ) {
				$out .= kits_tag( $t->name );
			}
			$email = (string) get_post_meta( $id, 'aw_email', true );
			$site  = (string) get_post_meta( $id, 'aw_website', true );
			$out  .= '</p>' . ( $phone || $email || $site ? '<p class="aw-org-contact">' . ( $phone ? aw_i( 'phone' ) . '<span class="screen-reader-text">Phone</span>' : '' ) . ( $email ? aw_i( 'mail' ) . '<span class="screen-reader-text">Email</span>' : '' ) . ( $site ? aw_i( 'arrow-up-right' ) . '<span class="screen-reader-text">Website</span>' : '' ) . '</p>' : '' ) . '</div><div class="aw-org-actions">'
				. ( $phone ? '<a class="kit-btn kit-btn--sm kit-btn--secondary" href="' . esc_attr( aw_tel_url( $phone ) ) . '">' . aw_i( 'phone' ) . 'Call</a>' : '' )
				. '<a class="aw-org-go" href="' . esc_url( get_permalink() ) . '" aria-label="' . esc_attr( 'View ' . $name ) . '">' . aw_i( 'arrow-right' ) . '</a></div></article>';
		}
		wp_reset_postdata();
		return $out . '</div>' . aw_pager();
	}
);

/* ---------- ACTION: Opportunities with conversion and trust ---------- */

add_shortcode(
	'aw_opps_index',
	function () {
		global $wp_query;
		$out = aw_list_filters( 'aw_opportunity' );
		if ( ! $wp_query->have_posts() ) {
			return $out . aw_empty( 'No open opportunities of this kind right now.' );
		}
		$out .= '<div class="aw-opps">';
		while ( $wp_query->have_posts() ) {
			$wp_query->the_post();
			$id    = get_the_ID();
			$org   = (string) get_post_meta( $id, 'aw_org_name', true );
			$apply = (string) get_post_meta( $id, 'aw_apply_url', true );
			$dl    = (string) get_post_meta( $id, 'aw_deadline', true );
			$type  = aw_first_term( $id, 'aw_opportunity_type' );
			$city  = aw_item_place( $id );
			$img   = get_the_post_thumbnail_url( $id, 'medium_large' );
			$out  .= '<article class="aw-opp">'
				. ( $img ? '<div class="aw-opp-media"><img src="' . esc_url( $img ) . '" alt="" loading="lazy"></div>' : '' )
				. '<div class="aw-opp-body"><p class="aw-opp-tags kit-tags">' . ( $type ? kits_tag( $type->name, 'accent' ) : '' ) . ( $city ? '<span>' . aw_i( 'directory' ) . esc_html( $city->name ) . '</span>' : '' ) . '</p>'
				. '<h2 class="aw-opp-title"><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></h2>'
				. ( $org ? '<p class="aw-opp-org">' . esc_html( $org ) . '</p>' : '' )
				. '<p class="aw-opp-sum">' . esc_html( aw_text( $id, 22 ) ) . '</p>'
				. '<p class="aw-opp-trust">' . aw_i( 'check' ) . 'Checked by Animal Writes on ' . esc_html( get_the_modified_date( 'j M Y' ) ) . ( $dl ? ' · Apply by ' . esc_html( wp_date( 'j M Y', strtotime( $dl ) ) ) : '' ) . '</p>'
				. '<div class="aw-opp-actions">' . ( $apply ? '<a class="kit-btn kit-btn--sm" href="' . esc_url( $apply ) . '" rel="noopener">Apply or sign up</a>' : '' ) . '<a class="kit-btn kit-btn--sm kit-btn--secondary" href="' . esc_url( get_permalink() ) . '">Details</a></div>'
				. '</div></article>';
		}
		wp_reset_postdata();
		return $out . '</div>' . aw_pager();
	}
);

/* ---------- ARCHIVE: Campaigns as documentary ---------- */

add_shortcode(
	'aw_campaigns_index',
	function () {
		global $wp_query;
		$out = do_blocks( '<!-- wp:pattern {"slug":"animal-writes/aw1-story"} /-->' );
		$others = array_filter( $wp_query->posts, fn( $p ) => 'animal-writes-1-0' !== $p->post_name );
		if ( $others ) {
			$out .= '<section class="aw-camp-more"><h2>More campaigns</h2><div class="aw-news-grid">';
			foreach ( $others as $p ) {
				$out .= aw_story( $p->ID, 'card' );
			}
			$out .= '</div></section>';
		}
		$out .= '<section class="aw-camp-next"><div><h2>Bring a campaign to Animal Writes</h2><p>Running a letter drive, petition or day of action for animals? Tell us and we will help you plan, publish and count it.</p></div><a class="kit-btn" href="/contact/">Talk to us</a></section>';
		return $out;
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
