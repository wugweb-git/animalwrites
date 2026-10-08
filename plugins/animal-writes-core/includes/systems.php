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

/**
 * Filter chips from a taxonomy (links that keep the current archive).
 *
 * @param string $tax    Taxonomy.
 * @param string $param  Query parameter.
 * @param string $label  Accessible label.
 * @param string $type   Post type counted.
 * @return string
 */
function aw_filter_chips( $tax, $param, $label, $type ) {
	$args = array( 'taxonomy' => $tax, 'hide_empty' => true, 'object_ids' => get_posts( array( 'post_type' => $type, 'numberposts' => -1, 'fields' => 'ids' ) ) );
	$terms = get_terms( $args );
	if ( 'aw_city' === $tax && $terms && ! is_wp_error( $terms ) ) {
		// Filter by state: a state shows when it or one of its districts has items.
		$ids = array();
		foreach ( $terms as $t ) {
			$ids[] = $t->parent ? $t->parent : $t->term_id;
		}
		$terms = get_terms( array( 'taxonomy' => 'aw_city', 'include' => array_unique( $ids ), 'orderby' => 'name', 'hide_empty' => false ) );
	}
	if ( ! $terms || is_wp_error( $terms ) ) {
		return '';
	}
	$cur  = sanitize_title( wp_unslash( $_GET[ $param ] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	// Keep the other active filters when one row changes.
	$base = add_query_arg( array_diff_key( function_exists( 'aw_active_filters' ) ? aw_active_filters( $type ) : array(), array( $param => '' ) ), get_post_type_archive_link( $type ) );
	$out  = '<nav class="aw-chips-nav" aria-label="' . esc_attr( $label ) . '"><a href="' . esc_url( $base ) . '"' . ( $cur ? '' : ' aria-current="true"' ) . '>All</a>';
	foreach ( $terms as $t ) {
		$out .= '<a href="' . esc_url( add_query_arg( $param, $t->slug, $base ) ) . '"' . ( $cur === $t->slug ? ' aria-current="true"' : '' ) . '>' . esc_html( $t->name ) . '</a>';
	}
	return $out . '</nav>';
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
 * List filters: post type => list of [query parameter, taxonomy, label]. Several can apply at once; every filtered
 * list is canonical to the plain list (indexing.php). Places filter by state (children included).
 */
const AW_LIST_FILTERS = array(
	'aw_legal'       => array( array( 'kind', 'aw_legal_kind', 'Filter by type' ), array( 'issuer', 'aw_source_type', 'Filter by issuer' ), array( 'court', 'aw_court', 'Filter by court' ), array( 'state', 'aw_city', 'Filter by state' ), array( 'lang', 'aw_language', 'Filter by language' ), array( 'topic', 'aw_topic', 'Filter by topic' ) ),
	'aw_news'        => array( array( 'topic', 'aw_topic', 'Filter by topic' ) ),
	'aw_guide'       => array( array( 'kind', 'aw_resource_type', 'Filter by type' ), array( 'topic', 'aw_topic', 'Filter by topic' ) ),
	'aw_opportunity' => array( array( 'type', 'aw_opportunity_type', 'Filter by kind of opportunity' ), array( 'state', 'aw_city', 'Filter by location' ) ),
	'aw_campaign'    => array( array( 'topic', 'aw_topic', 'Filter by topic' ) ),
);
const AW_LIST_SIZES = array( 'aw_legal' => 20, 'aw_news' => 13, 'aw_guide' => 40, 'aw_opportunity' => 24, 'aw_campaign' => 20 );

/** Active filters on this request: param => term slug. */
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
			$tq = array();
			foreach ( AW_LIST_FILTERS[ $type ] as $f ) {
				$v = aw_active_filters( $type )[ $f[0] ] ?? '';
				if ( $v ) {
					$tq[] = array( 'taxonomy' => $f[1], 'field' => 'slug', 'terms' => $v, 'include_children' => true );
				}
			}
			if ( $tq ) {
				$q->set( 'tax_query', array_merge( array( 'relation' => 'AND' ), $tq ) );
			}
		}
	},
	30
);

/** Every filter row for a list (rows with no terms in use are left out). */
function aw_list_filters( $type ) {
	$out = '';
	foreach ( AW_LIST_FILTERS[ $type ] ?? array() as $f ) {
		$out .= aw_filter_chips( $f[1], $f[0], $f[2], $type );
	}
	return $out;
}

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
			$sname = (string) get_post_meta( $id, 'aw_source_name', true );
			if ( $sname ) {
				$surl   = (string) get_post_meta( $id, 'aw_source_url', true );
				$sdate  = (string) get_post_meta( $id, 'aw_source_date', true );
				$label  = $surl ? '<a href="' . esc_url( $surl ) . '" rel="noopener nofollow">' . esc_html( $sname ) . '</a>' : esc_html( $sname );
				$rows[] = array( 'arrow-up-right', 'Source: ' . $label . ( $sdate ? esc_html( ' (list as on ' . wp_date( 'j M Y', strtotime( $sdate ) ) . ')' ) : '' ), true );
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
			$out .= '<li>' . aw_i( $r[0] ) . '<span>' . ( empty( $r[2] ) ? esc_html( $r[1] ) : wp_kses( $r[1], array( 'a' => array( 'href' => true, 'rel' => true ) ) ) ) . '</span></li>';
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
				$lang  = aw_first_term( $id, 'aw_language' );
				$out  .= '<article class="aw-case aw-case--template">'
					. '<div class="aw-case-date aw-case-icon">' . aw_i( 'resources' ) . '</div>'
					. '<div class="aw-case-body"><div class="aw-case-tags"><span class="aw-issuer aw-issuer--template">Template</span>'
					. ( $lang ? '<span class="aw-case-topic">' . esc_html( $lang->name ) . '</span>' : '' ) . '</div>'
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
				. '<div class="aw-case-body"><div class="aw-case-tags">'
				. ( $iss ? '<span class="aw-issuer aw-issuer--' . esc_attr( $iss->slug ) . '">' . esc_html( $iss->name ) . '</span>' : '' )
				. ( $topic ? '<span class="aw-case-topic">' . esc_html( $topic->name ) . '</span>' : '' ) . '</div>'
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
		if ( ! $wp_query->have_posts() ) {
			return aw_empty( 'No organisations match. Try another district, or clear the filters.' );
		}
		$out = '<div class="aw-orgs">';
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
				. '<p class="aw-org-tags">' . ( $verified ? '<span class="aw-state aw-state--ok">' . aw_i( 'check' ) . 'Verified</span>' : '<span class="aw-state">From ' . esc_html( (string) get_post_meta( $id, 'aw_source_name', true ) ?: 'AWBI' ) . ' records</span>' )
				. ( $featured ? '<span class="aw-state aw-state--feat">Featured</span>' : '' );
			foreach ( ( $types && ! is_wp_error( $types ) ) ? array_slice( $types, 0, 2 ) : array() as $t ) {
				$out .= '<span class="aw-type">' . esc_html( $t->name ) . '</span>';
			}
			$out .= '</p></div><div class="aw-org-actions">'
				. ( $phone ? '<a class="aw-btn aw-btn--sm aw-btn--outline" href="' . esc_attr( aw_tel_url( $phone ) ) . '">' . aw_i( 'phone' ) . 'Call</a>' : '' )
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
			$city  = aw_first_term( $id, 'aw_city' );
			$img   = get_the_post_thumbnail_url( $id, 'medium_large' );
			$out  .= '<article class="aw-opp">'
				. ( $img ? '<div class="aw-opp-media"><img src="' . esc_url( $img ) . '" alt="" loading="lazy"></div>' : '' )
				. '<div class="aw-opp-body"><p class="aw-opp-tags">' . ( $type ? '<span class="aw-kind">' . esc_html( $type->name ) . '</span>' : '' ) . ( $city ? '<span>' . aw_i( 'directory' ) . esc_html( $city->name ) . '</span>' : '' ) . '</p>'
				. '<h2 class="aw-opp-title"><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></h2>'
				. ( $org ? '<p class="aw-opp-org">' . esc_html( $org ) . '</p>' : '' )
				. '<p class="aw-opp-sum">' . esc_html( aw_text( $id, 22 ) ) . '</p>'
				. '<p class="aw-opp-trust">' . aw_i( 'check' ) . 'Checked by Animal Writes on ' . esc_html( get_the_modified_date( 'j M Y' ) ) . ( $dl ? ' · Apply by ' . esc_html( wp_date( 'j M Y', strtotime( $dl ) ) ) : '' ) . '</p>'
				. '<div class="aw-opp-actions">' . ( $apply ? '<a class="aw-btn aw-btn--sm" href="' . esc_url( $apply ) . '" rel="noopener">Apply or sign up</a>' : '' ) . '<a class="aw-btn aw-btn--sm aw-btn--outline" href="' . esc_url( get_permalink() ) . '">Details</a></div>'
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
		$out .= '<section class="aw-camp-next"><div><h2>Bring a campaign to Animal Writes</h2><p>Running a letter drive, petition or day of action for animals? Tell us and we will help you plan, publish and count it.</p></div><a class="aw-btn aw-btn--lg" href="/contact/">Talk to us</a></section>';
		return $out;
	}
);
