<?php
/**
 * Section header for archives: [aw_section_head]. Title, one-line description, live count and a
 * three-image visual built from real content in that section.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SECTION_DESC = array(
	'aw_news'        => 'Animal-welfare news from across India, summarised, with a link to every source.',
	'aw_legal'       => 'Court orders, notifications and rules that affect animals, explained in plain words.',
	'post'           => 'Practical guides for the moments that matter: an injured animal, a cruelty case, a community dog.',
	'aw_campaign'    => 'Citizen campaigns for animals, starting with the letters of India Writes for Its Community Dogs.',
	'aw_opportunity' => 'Volunteer, foster, intern or find a job with animal welfare organisations across India.',
	'aw_listing'     => 'Animal welfare organisations, Gaushalas and government vets across India.', // OWNER to approve (Phase 11 E1)
);

/**
 * One visual tile for a post: remote image, featured image, or a placard.
 *
 * @param WP_Post $p Post.
 * @return string
 */
function aw_section_tile( $p ) {
	$img = function_exists( 'aw_item_image' ) ? aw_item_image( $p->ID, 'medium_large' ) : '';
	if ( ! $img && ( $fid = (int) get_post_meta( $p->ID, 'aw_file_id', true ) ) ) {
		$img = (string) wp_get_attachment_image_url( $fid, 'large' );
	}
	if ( ! $img && has_post_thumbnail( $p ) ) {
		$img = (string) get_the_post_thumbnail_url( $p, 'medium_large' );
	}
	if ( $img ) {
		$fit = 'aw_listing' === $p->post_type ? ' class="aw-tile-logo"' : ''; // an organisation's logo stays whole (aw.css)
		return '<img src="' . esc_url( $img ) . '" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer"' . $fit . '>';
	}
	return function_exists( 'aw_placard_html' ) ? aw_placard_html( $p->ID ) : '';
}

/**
 * The head band: breadcrumb, H1, intro, count, pictures, then the page's filters or search. One renderer for every
 * page top (archives and category pages, Pages, new hub Pages); the markup is what the section band CSS expects.
 *
 * @param array $a crumbs (HTML), title (text), title_class, icon (HTML, before the title), desc (text), note (HTML, after the description), count (text), visual (HTML), filters (HTML), compact (bool), below (bool: filters and count go under the band, above the list; owner 20 Oct 2026).
 * @return string
 */
function aw_head_band( $a ) {
	$a     = wp_parse_args( $a, array( 'crumbs' => '', 'title' => '', 'title_class' => 'wp-block-query-title', 'icon' => '', 'desc' => '', 'note' => '', 'count' => '', 'visual' => '', 'filters' => '', 'compact' => false, 'below' => false, 'type' => '' ) );
	$count = '' !== $a['count'] ? '<p class="kit-section-count" role="status">' . esc_html( $a['count'] ) . '</p>' : '';
	if ( $a['below'] ) { // list pages: the head is breadcrumb › H1 › description; the toolbar (count, filters, sort) opens the list
		$under = aw_head_below( $a['filters'], $a['count'], $a['type'] );
		return aw_head_band( array_merge( $a, array( 'count' => '', 'filters' => '', 'compact' => false, 'below' => false ) ) ) . $under;
	}
	return '<header class="kit-section-band alignfull' . ( $a['compact'] ? ' kit-section-band--compact' : '' ) . '"><div class="kit-section-inner">' . $a['crumbs'] . '<div class="kit-section-head"><div>'
		. '<h1 class="' . esc_attr( $a['title_class'] ) . '">' . $a['icon'] . esc_html( $a['title'] ) . '</h1>'
		. ( '' !== $a['desc'] ? '<p class="kit-section-desc">' . wp_kses( $a['desc'], array( 'a' => array( 'href' => true ) ) ) . '</p>' : '' ) . $a['note'] // plain links kept (a tag description pointing to its page, owner 25 Oct 2026); everything else escaped
		. $count
		. '</div>' . $a['visual'] . '</div>' . ( '' !== $a['filters'] ? '<div class="kit-section-filters">' . $a['filters'] . '</div>' : '' ) . '</div></header>';
}

/** Head band of an archive, category, tag or Directory place page (was the [aw_section_head] shortcode's own markup). */
function aw_section_head_archive() {
		global $wp_query;
		$term_page = is_tax() || is_category() || is_tag();
		$type      = is_category() ? 'aw_news' : ( is_tag() ? ( aw_tag_section( (string) get_queried_object()->slug ) ?: 'aw_news' ) : (string) get_query_var( 'post_type' ) ); // a category page leads with its news; a tag page is its section's
		$type      = $type ? $type : ( $term_page ? (string) ( get_taxonomy( get_queried_object()->taxonomy )->object_type[0] ?? '' ) : '' );
		$title     = $term_page ? single_term_title( '', false ) : post_type_archive_title( '', false );
		if ( ( 'aw_listing' === $type && ! $term_page ) && ( get_query_var( 'aw_place' ) || get_query_var( 'aw_dir_type' ) ) ) {
			$city  = aw_current_place();
			$tname = function_exists( 'aw_current_type_name' ) ? aw_current_type_name() : '';
			$title = $tname ? ( $city ? $tname . ' in ' . $city->name : $tname ) : 'Help in ' . ( $city ? $city->name : '' );
		}
		$desc = $term_page && term_description() ? trim( wp_kses( term_description(), array( 'a' => array( 'href' => true ) ) ) ) : ( AW_SECTION_DESC[ $type ] ?? '' ); // plain links kept (aw_head_band allows them)
		if ( ( 'aw_listing' === $type && ! $term_page ) && function_exists( 'aw_directory_intro' ) ) {
			$desc = aw_directory_intro();
		}
		$count = (int) $wp_query->found_posts;
		$noun  = array( 'aw_listing' => 'organisations', 'post' => 'guides', 'aw_opportunity' => 'opportunities', 'aw_campaign' => 'campaigns', 'aw_legal' => 'documents', 'aw_news' => 'stories' );
		$count_label = number_format_i18n( $count ) . ' ' . ( 1 === $count ? rtrim( preg_replace( '/ies$/', 'y', $noun[ $type ] ?? 'items' ), 's' ) : ( $noun[ $type ] ?? 'items' ) );
		if ( 'post' === $type && $count && function_exists( 'aw_resources_kinds_label' ) ) { // Resources: guides, FAQ pages and research summaries counted apart
			$scope       = array( 'post_type' => 'post', 'post_status' => 'publish' );
			if ( $term_page ) {
				$scope['tax_query'] = array( array( 'taxonomy' => get_queried_object()->taxonomy, 'terms' => get_queried_object_id() ) );
			}
			$count_label = aw_resources_kinds_label( $scope, $count ) ?: $count_label;
		}

		// Visual: campaign photos for campaigns; otherwise the first three items in this view.
		$tiles = '';
		if ( 'aw_campaign' === $type ) {
			foreach ( array( 'aw1-im8-768x1024.jpeg', 'aw1-im10-461x1024.jpeg', 'aw1-im12-768x1024.jpeg' ) as $f ) {
				$tiles .= '<img src="/wp-content/uploads/2026/10/' . esc_attr( $f ) . '" alt="" loading="lazy">';
			}
		} else {
			// Prefer items with a real image; fall back to placards only to fill the three tiles.
			$pool  = get_posts( array( 'post_type' => $type ? $type : 'any', 'post_status' => 'publish', 'numberposts' => 12, 'tax_query' => $term_page ? array( array( 'taxonomy' => get_queried_object()->taxonomy, 'terms' => get_queried_object_id() ) ) : array() ) );
			// Three tiles, each a different picture: one item per distinct image, then placards to fill.
			$seen  = array();
			$with  = array();
			foreach ( $pool as $p ) {
				$key = aw_item_image( $p->ID ) ?: ( get_post_meta( $p->ID, 'aw_file_id', true ) ? 'doc' . $p->ID : ( has_post_thumbnail( $p ) ? get_the_post_thumbnail_url( $p ) : '' ) );
				if ( $key && ! isset( $seen[ $key ] ) ) {
					$seen[ $key ] = true;
					$with[]       = $p;
				}
			}
			// Category and tag pages: no placards (owner, 21 Oct 2026: the same source placard repeated three times on tag
			// pages); the term's own photo, then items' distinct pictures, else no visual.
			$rest  = $term_page ? array() : array_filter( $pool, fn( $p ) => ! in_array( $p, $with, true ) && ! aw_item_image( $p->ID ) );
			$posts = array_slice( array_merge( $with, $rest ), 0, 3 );
			foreach ( $posts as $p ) {
				$tiles .= aw_section_tile( $p );
			}
		}
		// A category page leads with its own photo (set on the category screen in WP Admin).
		$cat_img = ( $term_page && function_exists( 'aw_category_image_id' ) ) ? aw_category_image_id( get_queried_object() ) : 0;
		if ( $cat_img ) {
			$tiles = '<img src="' . esc_url( (string) wp_get_attachment_image_url( $cat_img, 'medium_large' ) ) . '" alt="" loading="lazy">' . implode( '', array_slice( preg_split( '/(?=<img|<div class="kit-placard)/', $tiles, -1, PREG_SPLIT_NO_EMPTY ), 0, 2 ) );
		}
		$visual = $tiles ? '<div class="kit-section-visual" aria-hidden="true">' . $tiles . '</div>' : '';
		if ( ( 'aw_listing' === $type && ! $term_page ) ) {
			// Directory: the search bar is the main element and the count sits with the results (Phase 11 E1, E3).
			$visual = '';
			$count  = 0;
		}
		// The band holds the breadcrumb, the heading and the page's search or filters (Directory search bar; list filters).
		$crumbs  = function_exists( 'aw_breadcrumbs_claim' ) ? aw_breadcrumbs_claim() : '';
		$filters = '';
		if ( ( 'aw_listing' === $type && ! $term_page ) && function_exists( 'aw_directory_filter' ) ) {
			$filters = aw_directory_filter();
		} elseif ( is_post_type_archive() && function_exists( 'aw_list_filters' ) ) {
			$filters = aw_list_filters( $type );
		} elseif ( is_tag() && function_exists( 'aw_directory_filter' ) && in_array( (string) get_queried_object()->slug, aw_listing_type_slugs(), true ) ) {
			$filters = aw_directory_filter( (string) get_queried_object()->slug ); // an organisation type's page: the Directory search with the type chosen (owner, 20 Oct 2026)
		}
		return aw_head_band(
			array(
				'crumbs'  => $crumbs,
				'title'   => $title,
				'icon'    => is_tag() && function_exists( 'aw_tag_icon' ) ? aw_tag_icon( (string) get_queried_object()->slug ) : '', // every tag has an icon (tags.php)
				'desc'    => $desc ? $desc : '',
				'note'    => is_tag() && function_exists( 'aw_service_type_note' ) ? aw_service_type_note( get_queried_object() ) : '',
				'count'   => $count ? $count_label : '',
				'visual'  => $visual,
				'filters' => $filters ? $filters : '',
				'compact' => 'aw_listing' === $type && ! $term_page,
				'below'   => aw_list_page( $type, $term_page ),
				'type'    => $term_page ? ( is_tag() && in_array( (string) get_queried_object()->slug, aw_listing_type_slugs(), true ) ? '' : 'term' ) : $type, // an organisation type's page keeps the Directory's own order
			)
		);
}
add_shortcode( 'aw_section_head', 'aw_section_head_archive' ); // saved content keeps working; templates use the animal-writes/section-head block

/**
 * animal-writes/section-head block: the head band of the page it is on. A Page gets its breadcrumb and title (the
 * default Page template); with postType set (a hub Page made by aw_hub_band) also the hub's intro and filters;
 * on an archive or category page it is the archive band.
 *
 * @param array $a Block attributes (postType).
 * @return string
 */
function aw_block_section_head( $a ) {
	if ( ! is_singular() ) {
		return do_shortcode( '[aw_section_head]' ); // the archive band (it places the breadcrumb itself; a category's banner is added to it)
	}
	if ( is_page( 'claim' ) && function_exists( 'aw_claim_head_band' ) ) {
		return aw_claim_head_band(); // the listing's own head (form-blocks.php)
	}
	$crumbs = function_exists( 'aw_breadcrumbs_claim' ) ? aw_breadcrumbs_claim() : '';
	$type = (string) ( $a['postType'] ?? '' );
	$hub  = '' !== $type;
	$filt = '';
	if ( $hub ) {
		$filt = 'aw_listing' === $type ? ( function_exists( 'aw_directory_filter' ) ? aw_directory_filter() : '' ) : ( function_exists( 'aw_list_filters' ) && defined( 'AW_LIST_FILTERS' ) && isset( AW_LIST_FILTERS[ $type ] ) ? aw_list_filters( $type ) : '' );
	}
	return aw_head_band(
		array(
			'crumbs'      => $crumbs,
			'title'       => wp_strip_all_tags( get_the_title() ),
			'title_class' => $hub ? 'wp-block-query-title wp-block-post-title' : 'wp-block-post-title',
			'desc'        => $hub && function_exists( 'aw_hub_intro' ) ? aw_hub_intro( $type ) : '',
			'filters'     => (string) $filt,
			'count'       => $hub && 'aw_listing' !== $type && aw_list_page( $type, false ) && function_exists( 'aw_hub_count_label' ) ? aw_hub_count_label( $type ) : '', // the Directory's count stays with its results
			'compact'     => 'aw_listing' === $type,
			'below'       => $hub && aw_list_page( $type, false ),
			'type'        => $hub && 'aw_listing' !== $type ? $type : '',
		)
	);
}

/**
 * The toolbar of a list page, under its head band (existing .kit-section-filters, placed below): the count on the left,
 * the filters and the section's sort on the right (aw_toolbar()).
 *
 * @param string $filters Filters HTML.
 * @param string $count   Count text.
 * @param string $type    Post type whose sorts are offered ('' for none: tag and category pages).
 * @return string
 */
function aw_head_below( $filters, $count, $type = '' ) {
	$base = '';
	if ( 'term' === $type ) { // a category or tag page: Sort in a sheet of its own on phones, kept to the page's address
		$base    = (string) kits_term_url( get_queried_object() );
		$group   = function_exists( 'aw_sort_group' ) ? aw_sort_group( 'term' ) : '';
		$filters = '' !== $group && function_exists( 'kits_filter_bar' ) ? kits_filter_bar( $group, array(), $base ) : $filters;
	}
	$sort = '' !== $type && function_exists( 'aw_sort_segment' ) ? aw_sort_segment( $type, $base ) : '';
	$bar  = '' !== $count || '' !== $sort ? aw_toolbar( $count, $filters . $sort ) : $filters;
	return '' !== $bar ? '<div class="kit-section-filters kit-section-filters--below alignwide">' . $bar . '</div>' : '';
}

// Section Pages keep their band as saved blocks: list-filters (hub-blocks.php) holds the filters and count, and the
// band group prints them under itself.
add_filter(
	'render_block_core/group',
	function ( $html, $block ) {
		if ( empty( $GLOBALS['aw_head_below'] ) || false === strpos( (string) ( $block['attrs']['className'] ?? '' ), 'kit-section-band' ) ) {
			return $html;
		}
		$below                    = $GLOBALS['aw_head_below'];
		$GLOBALS['aw_head_below'] = '';
		return $html . $below;
	},
	10,
	2
);

/**
 * List pages, whose filters and count sit under the head band (owner, 20 Oct 2026): News, Library, Resources,
 * Opportunities, Campaigns, Press coverage, Directory place and type pages, tag and category pages. Not the Directory home
 * page.
 *
 * @param string $type      Post type of the page's list.
 * @param bool   $term_page A tag or category page.
 * @return bool
 */
function aw_list_page( $type, $term_page ) {
	if ( $term_page ) {
		return true;
	}
	if ( 'aw_listing' === $type ) {
		return (bool) ( get_query_var( 'aw_place' ) || get_query_var( 'aw_dir_type' ) );
	}
	return '' !== $type;
}

/**
 * [aw_find_help] Directory discovery for the home page (the section heading is a core Heading in the pattern): the total,
 * a search box whose state picker lists every state and union territory, and the twelve states with the most
 * organisations in the Directory's own state index style, with a link to all of them (owner, 18 Oct 2026).
 */
add_shortcode(
	'aw_find_help',
	function () {
		$total  = aw_listing_public_count();
		$opts   = '<option value="">All states and UTs</option>';
		$counts = array();
		foreach ( (array) aw_place_states() as $st ) {
			$n = aw_state_count( $st );
			if ( ! $n ) {
				continue; // states and UTs with no listings are left out, so the list and the count match (Phase 11 A2)
			}
			$counts[] = array( $st, $n );
			$opts    .= '<option value="' . esc_attr( $st->slug ) . '">' . esc_html( $st->name ) . '</option>';
		}
		$with = count( $counts );
		usort( $counts, fn( $a, $b ) => $b[1] <=> $a[1] );
		$top = '';
		foreach ( array_slice( $counts, 0, 12 ) as $c ) {
			$top .= '<li><a href="' . esc_url( aw_directory_url( $c[0]->slug ) ) . '"><span>' . esc_html( $c[0]->name ) . '</span>' . kits_count( $c[1] ) . '</a></li>';
		}
		$dir = aw_hub_url( 'aw_listing' );
		return '<p class="has-large-font-size"><strong>' . esc_html( number_format_i18n( $total ) ) . '</strong> organisations in <strong>' . (int) $with . '</strong> states and union territories</p>'
			. '<form class="aw-ed-search" role="search" method="get" action="' . esc_url( $dir ) . '">'
			. '<input type="hidden" name="aw_filter" value="1">'
			. '<label class="screen-reader-text" for="aw-ed-q">Organisation name</label><span class="kit-ico kit-ico--search" aria-hidden="true"></span><input id="aw-ed-q" type="search" name="q" placeholder="Search by name, for example Blue Cross">'
			. '<label class="screen-reader-text" for="aw-ed-state">State or union territory</label><select id="aw-ed-state" name="state">' . $opts . '</select>'
			. '<button type="submit" class="kit-btn">Search</button></form>'
			. ( $top ? '<nav class="aw-browse-states" aria-labelledby="aw-top-states-h"><h3 id="aw-top-states-h">States with the most organisations</h3><ul>' . $top . '</ul></nav>'
				. '<p class="kit-more"><a href="' . esc_url( $dir ) . '#aw-browse-states-h">All ' . (int) $with . ' states and union territories</a></p>' : '' );
	}
);
