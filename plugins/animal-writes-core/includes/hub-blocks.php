<?php
/**
 * Small blocks for what core blocks cannot show (Phase 12 Part D, D4). Each is a registered, server-rendered block
 * (no shortcodes), editable in the block editor (preview via ServerSideRender), used in the hub Pages and their list
 * patterns. Inside a Query Loop they read the item from the block context (postId).
 *
 * Band: breadcrumb · hub-count · hub-visual · list-filters (Kits filter bar or the Directory search bar).
 * Lists: publisher · doc-date · issuer ("Issued by …" label) · doc-link (PDF) · task-icon · read-meta · place ·
 * deadline (validThrough) · opp-actions · org-mark · badges (Verified/Featured/Sponsored + sources + types) ·
 * contact (icons only when the contact exists) · org-actions · result-head (count + sort) · browse-states ·
 * press-media · press-meta (date + format tag).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Block name => [title, render callback, attributes]. */
function aw_hub_blocks() {
	$type_attr = array( 'postType' => array( 'type' => 'string', 'default' => '' ) );
	return (array) apply_filters( 'aw_hub_blocks', array( // term-page.php adds the category and tag page blocks
		'breadcrumb'    => array( 'Breadcrumb', 'aw_block_breadcrumb', array() ),
		'section-head'  => array( 'Head band', 'aw_block_section_head', $type_attr ),
		'hub-count'     => array( 'Section page count', 'aw_block_hub_count', $type_attr ),
		'hub-visual'    => array( 'Section page pictures', 'aw_block_hub_visual', $type_attr ),
		'list-filters'  => array( 'List filters', 'aw_block_list_filters', $type_attr ),
		'law-date'      => array( 'Law date (one line)', 'aw_block_law_date', array() ),
		'read-meta'     => array( 'Card meta row (date, read time, place)', 'aw_block_read_meta', array() ),
		'place'         => array( 'Place', 'aw_block_place', array() ),
		'opp-actions'   => array( 'Opportunity buttons', 'aw_block_opp_actions', array() ),
		'org-mark'      => array( 'Organisation logo', 'aw_block_org_mark', array() ),
		'badges'        => array( 'Card chips (source, type, category)', 'aw_block_badges', array( 'state' => array( 'type' => 'string', 'default' => 'block-md' ) ) ),
		'contact'       => array( 'Contact icons', 'aw_block_contact', array() ),
		'org-actions'   => array( 'Organisation buttons', 'aw_block_org_actions', array() ),
		'result-head'   => array( 'Result count and sort', 'aw_block_result_head', array() ),
		'browse-states' => array( 'Browse by state', 'aw_block_browse_states', array() ),
		'posted-by'     => array( 'Posted by', 'aw_block_posted_by', array() ),
		'volunteer'     => array( 'Volunteer form', 'aw_block_volunteer', array( 'open' => array( 'type' => 'boolean', 'default' => false ) ) ),
		'listing-help'  => array( 'Is this your organisation?', 'aw_block_listing_help', array() ),
		'org-details'   => array( 'Organisation details', 'aw_block_org_details', array() ),
		// The item page's rows (item-head.php, docs/item-template.md).
		'item-meta'     => array( 'Item: meta row', 'aw_block_item_meta', array() ),
		'item-sources'  => array( 'Item: sources', 'aw_block_item_sources', array() ),
		'item-tags'     => array( 'Item: tags', 'aw_block_item_tags', array() ),
		'item-share'    => array( 'Item: share', 'aw_block_item_share', array() ),
		'item-more'     => array( 'Item: more', 'aw_block_item_more', array() ),
		'current-campaign'      => array( 'Current campaign', 'aw_block_current_campaign', array() ), // campaign-tools.php: the sidebar's campaign card
		// The Directory profile's groups (listing-template.php, docs/listing-template.md).
		'listing-about'         => array( 'Listing: logo and description', 'aw_block_listing_about', array() ),
		'listing-registrations' => array( 'Listing: registrations', 'aw_block_listing_registrations', array() ),
		'listing-contact'       => array( 'Listing: contact', 'aw_block_listing_contact', array() ),
		'listing-location'      => array( 'Listing: location', 'aw_block_listing_location', array() ),
		'listing-services'      => array( 'Listing: services', 'aw_block_listing_services', array() ),
		'listing-organisation'  => array( 'Listing: organisation', 'aw_block_listing_organisation', array() ),
		'listing-profiles'      => array( 'Listing: online profiles', 'aw_block_listing_profiles', array() ),
		'listing-support'       => array( 'Listing: support', 'aw_block_listing_support', array() ),
		'listing-photos'        => array( 'Listing: photos', 'aw_block_listing_photos', array() ),
		'listing-activity'      => array( 'Listing: activity', 'aw_block_listing_activity', array() ),
		'listing-press'         => array( 'Listing: in the press', 'aw_block_listing_press', array() ),
		'listing-opportunities' => array( 'Listing: opportunities', 'aw_block_listing_opportunities', array() ),
		'listing-campaigns'     => array( 'Listing: campaigns', 'aw_block_listing_campaigns', array() ),
		'claim-cta'             => array( 'Claim your listing', 'aw_block_claim_cta', array() ), // the sidebar's claim box
	) );
}

add_action(
	'init',
	function () {
		wp_register_script( 'aw-hub-blocks', plugins_url( 'assets/js/hub-blocks.js', AW_CORE_DIR . 'animal-writes-core.php' ), array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-server-side-render' ), AW_CORE_VERSION . '-2', true );
		$list = array();
		foreach ( aw_hub_blocks() as $name => $b ) {
			register_block_type(
				'animal-writes/' . $name,
				array(
					'api_version'          => 3,
					'title'                => $b[0],
					'category'             => 'widgets',
					'attributes'           => $b[2],
					'uses_context'         => array( 'postId', 'postType', 'queryId' ),
					'render_callback'      => function ( $attrs, $content, $block ) use ( $b ) {
						return (string) call_user_func( $b[1], $attrs, (int) ( $block->context['postId'] ?? get_the_ID() ), $block );
					},
					'editor_script_handles' => array( 'aw-hub-blocks' ),
					'supports'             => array( 'html' => false ),
				)
			);
			$list[] = array( 'name' => 'animal-writes/' . $name, 'title' => $b[0] );
		}
		wp_add_inline_script( 'aw-hub-blocks', 'window.awHubBlocks = ' . wp_json_encode( $list ) . ';', 'before' );
	}
);

/* ---------- Band ---------- */

function aw_block_breadcrumb() {
	global $post;
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST && $post ) { // editor preview: the trail of this page
		$trail = '<li class="kit-crumbs-home"><a href="' . esc_url( home_url( '/' ) ) . '" aria-label="Home"><span class="kit-ico kit-ico--home" aria-hidden="true"></span></a></li>';
		foreach ( array_reverse( get_post_ancestors( $post ) ) as $a ) {
			$trail .= '<li><a href="' . esc_url( get_permalink( $a ) ) . '">' . esc_html( get_the_title( $a ) ) . '</a></li>';
		}
		return '<nav class="kit-crumbs" aria-label="Breadcrumb"><ol>' . $trail . '<li><span aria-current="page">' . esc_html( get_the_title( $post ) ) . '</span></li></ol></nav>';
	}
	return function_exists( 'aw_breadcrumbs_claim' ) ? aw_breadcrumbs_claim() : '';
}

function aw_block_hub_count( $a ) {
	return ''; // the count sits with the filters, under the band (aw_block_list_filters; owner, 20 Oct 2026)
}

/** "23 stories": the number of items in a hub list with the current filters. */
function aw_hub_count_label( $type ) {
	$noun = array( 'post' => array( 'guide', 'guides' ), 'aw_opportunity' => array( 'opportunity', 'opportunities' ), 'aw_campaign' => array( 'campaign', 'campaigns' ), 'aw_legal' => array( 'document', 'documents' ), 'aw_news' => array( 'story', 'stories' ), 'aw_listing' => array( 'organisation', 'organisations' ), 'aw_press' => array( 'item', 'items' ) );
	if ( ! isset( $noun[ $type ] ) ) {
		return '';
	}
	$n = 'aw_campaign' === $type ? (int) wp_count_posts( 'aw_campaign' )->publish : aw_hub_total( $type ); // all campaigns, the 1.0 story included
	if ( 'post' === $type && $n ) { // Resources holds guides, FAQ pages and research summaries: say which
		$label = aw_resources_kinds_label( aw_hub_query_vars( 'post', 1 ), $n );
		if ( '' !== $label ) {
			return $label;
		}
	}
	return $n ? number_format_i18n( $n ) . ' ' . $noun[ $type ][ 1 === $n ? 0 : 1 ] : '';
}

/**
 * What a Resources list holds, counted live by its type tags: "15 guides, 1 FAQ page and 143 research summaries".
 * $vars are the list's query vars (its filters); $total is the list's count. Items without a type tag are "other resources".
 */
function aw_resources_kinds_label( $vars, $total ) {
	static $cache = array();
	$key = md5( wp_json_encode( array( $vars, $total ) ) );
	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}
	$kinds = array(
		'guide'    => array( 'guide', 'guides' ),
		'faq'      => array( 'FAQ page', 'FAQ pages' ),
		'research' => array( 'research summary', 'research summaries' ),
	);
	$parts = array();
	$seen  = 0;
	foreach ( $kinds as $slug => $noun ) {
		$v = array_merge( $vars, array( 'fields' => 'ids', 'posts_per_page' => 1, 'paged' => 1, 'no_found_rows' => false ) );
		$tag = array( 'taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => $slug );
		$v['tax_query'] = empty( $vars['tax_query'] ) ? array( $tag ) : array( 'relation' => 'AND', $vars['tax_query'], $tag );
		$n = (int) ( new WP_Query( $v ) )->found_posts;
		if ( $n ) {
			$parts[] = number_format_i18n( $n ) . ' ' . $noun[ 1 === $n ? 0 : 1 ];
			$seen   += $n;
		}
	}
	$rest = (int) $total - $seen; // an item carries one type tag; anything untagged is named plainly
	if ( $rest > 0 && $parts ) {
		$parts[] = number_format_i18n( $rest ) . ' ' . ( 1 === $rest ? 'other resource' : 'other resources' );
	}
	$last = array_pop( $parts );
	return $cache[ $key ] = $parts ? implode( ', ', $parts ) . ' and ' . $last : (string) $last;
}

/** Three pictures from the section (campaign photos for Campaigns). */
function aw_block_hub_visual( $a ) {
	return ''; // owner, 8 Oct 2026: no supporting pictures in page top bands
	$type = (string) ( $a['postType'] ?? '' );
	if ( ! $type ) {
		return '';
	}
	$tiles = '';
	if ( 'aw_listing' === $type ) { // Directory: the photos of three categories it serves
		foreach ( array( 'veterinary-care', 'community-dogs', 'cattle-gaushalas' ) as $slug ) {
			$t   = get_term_by( 'slug', $slug, 'category' );
			$tid = $t && function_exists( 'aw_category_image_id' ) ? (int) aw_category_image_id( $t ) : 0;
			$tiles .= $tid ? (string) wp_get_attachment_image( $tid, 'medium_large', false, array( 'alt' => '', 'loading' => 'lazy' ) ) : '';
		}
	} elseif ( 'aw_press' === $type ) { // Media & PR: the latest coverage pictures
		foreach ( get_posts( array( 'post_type' => 'aw_press', 'post_status' => 'publish', 'numberposts' => 3, 'meta_key' => '_thumbnail_id', 'fields' => 'ids' ) ) as $pid ) { // phpcs:ignore WordPress.DB.SlowDBQuery
			$tiles .= (string) get_the_post_thumbnail( $pid, 'medium_large', array( 'alt' => '', 'loading' => 'lazy' ) );
		}
	} elseif ( 'aw_campaign' === $type ) {
		foreach ( array( 'aw1-im8-768x1024.jpeg', 'aw1-im10-461x1024.jpeg', 'aw1-im12-768x1024.jpeg' ) as $f ) {
			$tiles .= '<img src="/wp-content/uploads/2026/10/' . esc_attr( $f ) . '" alt="" loading="lazy">';
		}
	} else {
		$pool = get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'numberposts' => 12 ) );
		$seen = array();
		$with = array();
		foreach ( $pool as $p ) {
			$key = aw_item_image( $p->ID ) ?: ( get_post_meta( $p->ID, 'aw_file_id', true ) ? 'doc' . $p->ID : ( has_post_thumbnail( $p ) ? get_the_post_thumbnail_url( $p ) : '' ) );
			if ( $key && ! isset( $seen[ $key ] ) ) {
				$seen[ $key ] = true;
				$with[]       = $p;
			}
		}
		$rest = array_filter( $pool, fn( $p ) => ! in_array( $p, $with, true ) && ! aw_item_image( $p->ID ) );
		foreach ( array_slice( array_merge( $with, $rest ), 0, 3 ) as $p ) {
			$tiles .= aw_section_tile( $p );
		}
	}
	return $tiles ? '<div class="kit-section-visual" aria-hidden="true">' . $tiles . '</div>' : '';
}

/** Kits filter bar driving the hub Query Loop through URL query vars; the Directory gets its search bar. */
function aw_block_list_filters( $a ) {
	$type = (string) ( $a['postType'] ?? '' );
	$out  = 'aw_listing' === $type ? aw_directory_filter() : ( function_exists( 'aw_list_filters' ) && isset( AW_LIST_FILTERS[ $type ] ) ? aw_list_filters( $type ) : '' );
	if ( function_exists( 'aw_list_page' ) && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) && aw_list_page( $type, false ) ) { // list pages: filters › count under the band (owner, 20 Oct 2026); the Directory's count stays with its results
		$count                    = 'aw_listing' !== $type ? aw_hub_count_label( $type ) : '';
		$GLOBALS['aw_head_below'] = aw_head_below( $out, $count, 'aw_listing' !== $type ? $type : '' );
		return '';
	}
	return $out ? '<div class="kit-section-filters">' . $out . '</div>' : '';
}

/* ---------- News, Library, Resources ---------- */

/**
 * The news card's meta, one row on every news card (News, home, category pages): publisher (logo tile + name) on the
 * left, when it was published (calendar icon, relative time) after it.
 *
 * @param int $id Post ID.
 * @return string
 */
function aw_news_meta_row( $id ) {
	$src  = function_exists( 'aw_source_row_html' ) ? aw_source_row_html( $id ) : '';
	$time = function_exists( 'aw_rel_time_html' ) ? '<span class="wp-block-post-date aw-card-time kit-meta-item">' . aw_rel_time_html( $id ) . '</span>' : '';
	return '' === $src . $time ? '' : '<div class="aw-card-meta kit-meta-row">' . $src . $time . '</div>';
}

/** One-line date of a Library item: its own date ("1st November, 2025"), or the year of an Act or Rule; never the import date. */
function aw_block_law_date( $a, $id ) {
	if ( has_term( 'template', 'post_tag', $id ) ) {
		return '';
	}
	$d = (string) get_post_meta( $id, 'aw_source_date', true );
	$date = $d ? aw_date( $d . ' 12:00:00' ) : ( preg_match( '/\b(1[89]|20)\d{2}\b/', get_the_title( $id ), $ym ) ? aw_date( $ym[0] . '-01-01 12:00:00', 'Y' ) : '' );
	return '' !== $date ? '<span class="kit-meta-item">' . $date . '</span>' : '';
}

/**
 * A Library item's type label: its icon, then its name (owner, 15 Oct 2026), the same everywhere the label appears.
 * Templates keep their accent tint. Never a logo; never in a logo slot.
 *
 * @param int $id Library item.
 * @return string '' when the item has no type.
 */
function aw_legal_kind_tag( $id ) {
	$t = aw_legal_kinds( $id );
	if ( ! $t ) {
		return '';
	}
	$t = $t[0];
	return '<span class="kit-tag' . ( 'template' === $t->slug ? ' kit-tag--accent' : '' ) . '">' . aw_type_icon( 'lib-' . $t->slug ) . esc_html( $t->name ) . '</span>';
}

function aw_block_read_meta( $a, $id ) {
	return aw_card_meta( $id );
}

/* ---------- Opportunities ---------- */

function aw_block_place( $a, $id ) {
	$p = function_exists( 'aw_item_place' ) ? aw_item_place( $id ) : null;
	return $p ? '<span class="aw-place kit-meta-item">' . aw_i( 'directory' ) . esc_html( $p->name ) . '</span>' : '';
}

/** The place for the opportunity line: in a list for one state ("near you"), that state's district or the state; else the first place. */
function aw_opp_line_place( $id ) {
	$places = function_exists( 'aw_item_places' ) ? aw_item_places( $id ) : array();
	$want   = (int) ( $GLOBALS['aw_near_state_code'] ?? 0 );
	$p      = $places ? $places[0] : null;
	foreach ( $want ? $places : array() as $q ) {
		if ( ( 'district' === $q->kind && (int) $q->state === $want ) || ( 'state' === $q->kind && (int) $q->code === $want ) ) {
			$p = $q;
			break;
		}
	}
	return $p ? '<span class="aw-place kit-meta-item">' . aw_i( 'directory' ) . esc_html( $p->name ) . '</span>' : '';
}

function aw_block_opp_actions( $a, $id ) {
	$apply = (string) get_post_meta( $id, 'aw_apply_url', true ); // the card itself links to the item page (title ::after)
	return $apply ? '<div class="aw-opp-actions"><a class="kit-btn kit-btn--sm" href="' . esc_url( $apply ) . '" rel="noopener" aria-label="' . esc_attr( 'Apply or sign up: ' . get_the_title( $id ) ) . '">Apply</a></div>' : '';
}

/** Single opportunity: "Posted by {organisation}", linked to its Directory profile when there is one. */
function aw_block_posted_by( $a, $id ) {
	$name    = (string) get_post_meta( $id, 'aw_org_name', true );
	$listing = (int) get_post_meta( $id, 'aw_organisation', true );
	if ( $listing && 'publish' === get_post_status( $listing ) ) {
		$name = $name ? $name : get_the_title( $listing );
		return '<p class="aw-posted-by kit-meta-item">Posted by <a href="' . esc_url( get_permalink( $listing ) ) . '">' . esc_html( $name ) . '</a></p>';
	}
	return $name ? '<p class="aw-posted-by kit-meta-item">Posted by ' . esc_html( $name ) . '</p>' : '';
}

/* ---------- Directory ---------- */

function aw_block_org_mark( $a, $id ) {
	$logo     = get_the_post_thumbnail_url( $id, 'thumbnail' );
	if ( ! $logo && function_exists( 'aw_item_here' ) && aw_item_here( $id ) ) {
		return ''; // the profile's own row 4: the logo, or nothing (no initials placeholder; owner, 20 Oct 2026)
	}
	$words    = array_values( array_filter( preg_split( '/\s+/', preg_replace( '/[^\p{L}\s]/u', '', get_the_title( $id ) ) ) ) ); // "1962 Mobile Veterinary Unit" → MV
	$initials = mb_strtoupper( mb_substr( $words[0] ?? '', 0, 1 ) . mb_substr( $words[1] ?? '', 0, 1 ) );
	return '<div class="aw-org-mark">' . ( $logo ? '<img src="' . esc_url( $logo ) . '" alt="" loading="lazy">' : '<span aria-hidden="true">' . esc_html( $initials ) . '</span>' ) . '</div>';
}

/** Up to two listing types (the card's labels); on every other card, its chips (aw_card_chips()). */
function aw_block_badges( $a, $id ) {
	if ( 'aw_listing' !== get_post_type( $id ) ) {
		return aw_card_chips( $id, (string) ( $a['state'] ?? 'block-md' ) );
	}
	$out = '';
	// Cards show the type only (owner, 12 Oct 2026): Verified sits on the name, sources are on the profile (Details from).
	foreach ( array_slice( aw_listing_types( $id ), 0, 2 ) as $t ) {
		$out .= '<span class="kit-tag">' . aw_type_icon( $t->slug ) . esc_html( $t->name ) . '</span>'; // the type's icon, then its name
	}
	return $out ? '<p class="aw-org-tags kit-tags">' . $out . '</p>' : '';
}

/**
 * A listing type's icon (aw-type-ico): it goes before the type's name wherever that label appears, and never stands in
 * for a logo (owner, 15 Oct 2026: a logo slot shows the organisation's own logo or its initials only).
 *
 * @param string $slug Listing type slug.
 * @return string
 */
function aw_type_icon( $slug ) {
	return '<span class="aw-type-ico aw-type-ico--' . esc_attr( $slug ) . '" aria-hidden="true"></span>';
}

// Tag labels carry their icon wherever WordPress prints them (tags.php, term_links-post_tag).

/** Ways to reach the organisation, shown only when they exist. */
function aw_block_contact( $a, $id ) {
	return ''; // not on the Directory index (owner, 12 Oct 2026): the card's Call button and the profile carry the contact details
	$phone = function_exists( 'aw_listing_first_phone' ) ? aw_listing_first_phone( $id ) : '';
	$email = (string) get_post_meta( $id, 'aw_email', true );
	$site  = (string) get_post_meta( $id, 'aw_website', true );
	if ( ! $phone && ! $email ) { // the website arrow is not shown on cards (owner, 12 Oct 2026)
		return '';
	}
	return '<p class="aw-org-contact">' . ( $phone ? aw_i( 'phone' ) . '<span class="screen-reader-text">Phone</span>' : '' ) . ( $email ? aw_i( 'mail' ) . '<span class="screen-reader-text">Email</span>' : '' ) . '' . '</p>';
}

function aw_block_org_actions( $a, $id ) {
	$phone = function_exists( 'aw_listing_first_phone' ) ? aw_listing_first_phone( $id ) : '';
	return '<div class="aw-org-actions">' // no Call button on cards (owner, 12 Oct 2026)
		. '<a class="aw-org-go" href="' . esc_url( get_permalink( $id ) ) . '" aria-label="' . esc_attr( 'View ' . get_the_title( $id ) ) . '">' . aw_i( 'arrow-right' ) . '</a></div>';
}

/**
 * The collection toolbar (owner, 21 Oct 2026), one component for every list: the count on the left, the controls on the
 * right (filters, Sort, and on the Directory View and Near me). Under 768 the controls wrap under the count. Only
 * controls that work in that list are passed in (aw_sort_segment() for the sorts a section has).
 *
 * @param string $count    Count text ("1,104 stories"), plain.
 * @param string $controls Controls' HTML.
 * @return string
 */
function aw_toolbar( $count, $controls = '' ) {
	return '' === $count . $controls ? '' : '<div class="aw-toolbar">' . ( '' !== $count ? '<p class="aw-toolbar-count" role="status">' . esc_html( $count ) . '</p>' : '' ) . $controls . '</div>';
}

/**
 * Directory toolbar (owner, 21 Oct 2026): one row above the list, the count on the left ("976 results for Kerala") and
 * the controls on the right: Sort (A–Z / Most details first), View (List / Map) and Near me. Their panels (Near me,
 * the map) open under the row; the list follows.
 */
function aw_block_result_head() {
	if ( ! aw_is_directory_view() ) {
		return '';
	}
	$n = aw_hub_total( 'aw_listing' );
	if ( ! $n ) {
		return '';
	}
	$q     = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$where = array_filter( array( $q ? '“' . $q . '”' : '', aw_current_place() ? aw_current_place()->name : '', aw_current_type_name() ) );
	$line  = $where ? sprintf( _n( '%1$s result for %2$s', '%1$s results for %2$s', $n ), number_format_i18n( $n ), implode( ', ', $where ) ) : sprintf( _n( '%s organisation', '%s organisations', $n ), number_format_i18n( $n ) );
	$sort  = 'details' === ( $_GET['sort'] ?? '' ) ? 'details' : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$here  = add_query_arg( array_map( 'rawurlencode', array_diff_key( wp_unslash( $_GET ), array( 'sort' => 1, 'paged' => 1, 'query-106-page' => 1 ) ) ), strtok( aw_hub_base_url(), '?' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$sortnav = '<nav class="kit-segment" aria-label="Sort"><a href="' . esc_url( $here ) . '"' . ( $sort ? '' : ' aria-current="true"' ) . '>A–Z</a><a href="' . esc_url( add_query_arg( 'sort', 'details', $here ) ) . '"' . ( $sort ? ' aria-current="true"' : '' ) . '>Most details first</a></nav>';
	return aw_toolbar( $line, $sortnav . ( function_exists( 'aw_dir_tools_html' ) ? aw_dir_tools_html() : '' ) ) // List / Map and Near me (directory-map.php)
		. ( function_exists( 'aw_dir_panels_html' ) ? aw_dir_panels_html() : '' );
}

function aw_block_browse_states() {
	return function_exists( 'aw_browse_states' ) ? aw_browse_states() : '';
}

// Hub Pages in the editor: the H1 in the band is the title (no second title field above it).
add_action(
	'enqueue_block_assets',
	function () {
		$id = is_admin() ? (int) ( $_GET['post'] ?? 0 ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $id && 'page-hub' === get_page_template_slug( $id ) ) {
			wp_register_style( 'aw-hub-editor', false, array(), AW_CORE_VERSION );
			wp_enqueue_style( 'aw-hub-editor' );
			wp_add_inline_style( 'aw-hub-editor', '.editor-visual-editor__post-title-wrapper{display:none}' );
		}
	}
);

// A press item's title links to the coverage itself (new tab), not to an item page (press items have none).
add_filter(
	'render_block_core/post-title',
	function ( $html, $block, $instance ) {
		$id = (int) ( $instance->context['postId'] ?? 0 );
		if ( ! $id || 'aw_press' !== get_post_type( $id ) ) {
			return $html;
		}
		$url = (string) get_post_meta( $id, 'aw_press_url', true );
		$p = new WP_HTML_Tag_Processor( $html );
		if ( ! $url || ! $p->next_tag( 'A' ) ) {
			return $html;
		}
		$p->set_attribute( 'href', esc_url( $url ) );
		$p->set_attribute( 'target', '_blank' );
		$p->set_attribute( 'rel', 'noopener noreferrer' );
		$p->set_attribute( 'aria-label', aw_seo_plain( get_the_title( $id ) ) . ' (opens in a new tab)' ); // the note is not part of the heading text
		$html = $p->get_updated_html();
		$end  = strpos( $html, '</a>' );
		return false === $end ? $html : substr_replace( $html, ' ' . aw_i( 'arrow-up-right' ), $end, 0 );
	},
	10,
	3
);

// A featured Directory listing is marked on its row.
add_filter(
	'post_class',
	function ( $classes, $cls, $id ) {
		if ( 'aw_listing' === get_post_type( $id ) && (int) get_post_meta( $id, 'aw_featured', true ) ) {
			$classes[] = 'is-featured';
		}
		return $classes;
	},
	10,
	3
);

/** A listing NGO Darpan has blacklisted: a red label after its name on cards, lists and the profile (owner, 18 Oct 2026). */
add_filter(
	'render_block_core/post-title',
	function ( $html, $block, $instance ) {
		$id = (int) ( $instance->context['postId'] ?? get_the_ID() );
		if ( ! $id || 'aw_listing' !== get_post_type( $id ) || ! get_post_meta( $id, 'aw_darpan_blacklisted_on', true ) || ( is_singular( 'aw_listing' ) && get_queried_object_id() === $id ) ) {
			return $html; // its own page has the badge in the header instead
		}
		$end = strrpos( $html, '</h' );
		$tag = ' <span class="kit-tag kit-tag--danger aw-blacklisted-mark" title="' . esc_attr( 'NGO Darpan (NITI Aayog) lists this organisation as blacklisted' ) . '">Blacklisted on NGO Darpan</span>';
		return false === $end ? $html . $tag : substr_replace( $html, $tag, $end, 0 );
	},
	26,
	3
);

/** The Verified mark on a listing's name, on cards and on the profile (owner, 12 Oct 2026). */
function aw_verified_mark() {
	return ' <span class="aw-verified-mark" title="Verified by Animal Writes">' . aw_i( 'check' ) . '<span class="screen-reader-text">Verified</span></span>';
}
add_filter(
	'render_block_core/post-title',
	function ( $html, $block, $instance ) {
		$id = (int) ( $instance->context['postId'] ?? get_the_ID() );
		if ( ! $id || 'aw_listing' !== get_post_type( $id ) || ! get_post_meta( $id, 'aw_verified', true ) ) {
			return $html;
		}
		$end = strrpos( $html, '</a>' );
		$end = false !== $end ? $end : strrpos( $html, '</h' );
		return false === $end ? $html : substr_replace( $html, aw_verified_mark(), $end, 0 );
	},
	25,
	3
);

/* ---------- Card parts: one card for every list (kit-card kit-card--{state}, kit.css 4.2) ----------
 * States: block-lg · block-md · block-sm (picture on top) and stacked-lg · stacked-md · stacked-sm (picture left, or none;
 * stacked-sm is the reference list). Anatomy: picture 4:3 › meta row (read-meta) › chips (badges) › title › summary ›
 * action. The chips: source (short name), type, primary category; lg 3 · md 2 · sm 1 (owner, 20 Oct 2026). */

/** Chips per card size. */
function aw_card_chip_cap( $state ) {
	return array( 'lg' => 3, 'md' => 2, 'sm' => 1 )[ substr( (string) $state, -2 ) ] ?? 2;
}

/** The date a card shows: when the item was published at its source (Unix time), else when it was posted here. */
function aw_card_ts( $id ) {
	$type = get_post_type( $id );
	if ( 'aw_press' === $type ) {
		$d = (string) get_post_meta( $id, 'aw_press_date', true );
		return $d ? (int) strtotime( $d . ' 12:00:00 Asia/Kolkata' ) : (int) get_post_time( 'U', true, $id );
	}
	if ( 'post' === $type && ! aw_is_ingested( $id ) ) {
		return (int) get_post_modified_time( 'U', true, $id ); // our own guides: when they were last updated
	}
	return function_exists( 'aw_published_ts' ) ? (int) aw_published_ts( $id ) : (int) get_post_time( 'U', true, $id );
}

// The meta row and the chips share one row on a card (owner, 21 Oct 2026): date (read time, place), then the chips; a chip
// that does not fit still wraps out of sight, so the source stays.
add_filter(
	'render_block_core/group',
	function ( $html, $block ) {
		if ( false === strpos( (string) ( $block['attrs']['className'] ?? '' ), 'kit-card-body' ) || false === strpos( $html, 'kit-card-chips' ) ) {
			return $html;
		}
		return preg_replace( '#(<p class="kit-card-meta kit-meta-row">.*?</p>)\s*(<p class="kit-card-chips kit-tags">.*?</p>)#s', '<div class="kit-card-metarow">$1$2</div>', $html, 1 );
	},
	10,
	2
);

/** Meta row: date (calendar icon; "3 hours ago" within 7 days), read time (Resources), place. One row, plain text. */
function aw_card_meta( $id ) {
	$ts   = aw_card_ts( $id );
	$diff = time() - $ts;
	/* translators: %s: time difference, e.g. 3 hours. */
	$when = ( $diff >= 0 && $diff < 7 * DAY_IN_SECONDS ) ? ( $diff < 2 * MINUTE_IN_SECONDS ? __( 'Just now', 'animal-writes-core' ) : sprintf( __( '%s ago', 'animal-writes-core' ), human_time_diff( $ts, time() ) ) ) : wp_date( AW_DATE_FORMAT, $ts );
	$out  = '<span class="kit-meta-item aw-date">' . aw_i( 'calendar' ) . '<time datetime="' . esc_attr( wp_date( 'Y-m-d', $ts ) ) . '" title="' . esc_attr( wp_date( AW_DATE_FORMAT, $ts ) ) . '">' . esc_html( $when ) . '</time></span>';
	if ( 'post' === get_post_type( $id ) && ! aw_is_ingested( $id ) ) {
		$mins = max( 1, (int) round( str_word_count( wp_strip_all_tags( (string) get_post_field( 'post_content', $id ) ) ) / 200 ) );
		$out .= '<span class="kit-meta-item">' . aw_i( 'clock' ) . (int) $mins . ' min read</span>';
	}
	$place = in_array( get_post_type( $id ), array( 'aw_news', 'aw_legal', 'aw_opportunity' ), true ) && function_exists( 'aw_item_place' ) ? aw_item_place( $id ) : null;
	if ( 'aw_opportunity' === get_post_type( $id ) && function_exists( 'aw_opp_line_place' ) ) {
		$out .= str_replace( aw_i( 'directory' ), aw_i( 'map-pin' ), aw_opp_line_place( $id ) );
	} elseif ( $place ) {
		$out .= '<span class="kit-meta-item">' . aw_i( 'map-pin' ) . esc_html( $place->name ) . '</span>';
	}
	return '<p class="kit-card-meta kit-meta-row">' . $out . '</p>';
}

/**
 * A short source name for a chip: the publisher record's short name (Settings → Sourcing), an issuer's short name, or one
 * made from the full name ("The Sentinel – of this Land, for its People" → "Sentinel"; "… (CCSEA)" → "CCSEA").
 * The full name stays in the item page's Sources block.
 */
function aw_short_name( $name ) {
	$name = trim( preg_replace( '/\s+/u', ' ', (string) $name ) );
	$known = array(
		'the times of india' => 'TOI', 'times of india' => 'TOI', 'live law' => 'LiveLaw', 'the new indian express' => 'TNIE',
		'the indian express' => 'Indian Express', 'free press journal' => 'FPJ', 'hindustan times' => 'HT', 'the economic times' => 'ET',
		'animal welfare board of india' => 'AWBI', 'press information bureau' => 'PIB', 'press trust of india' => 'PTI',
		'united news of india' => 'UNI', 'ani news' => 'ANI', 'national green tribunal' => 'NGT', 'supreme court of india' => 'Supreme Court',
		'gazette of india' => 'Gazette', 'mathrubhumi english' => 'Mathrubhumi', 'thewire.in' => 'The Wire', 'yourstory.com' => 'YourStory',
		'people for the ethical treatment of animals (peta)' => 'PETA', 'abc news - breaking news, latest news and videos' => 'ABC News',
		'fundsforngos - grants and resources for sustainability' => 'fundsforNGOs', 'committee for control and supervision of experiments on animals' => 'CCSEA',
	);
	$base = trim( preg_replace( '/\s+(?:[–—-]|·|\|)\s+.*$|\s*\((?:via|text on)\b[^)]*\)$/iu', '', $name ) ); // "The Hindu – Energy & Environment", "Gazette of India (via CCSEA)"
	if ( isset( $known[ mb_strtolower( $base ) ] ) ) {
		return $known[ mb_strtolower( $base ) ];
	}
	if ( preg_match( '/\(([A-Z][A-Za-z]{1,9})\)\s*$/u', $base, $m ) ) { // "Committee for … Animals (CCSEA)" → CCSEA
		return $m[1];
	}
	$base = preg_replace( '/^(?:www\.)|\.(?:com|in|org|net)$/i', '', $base );
	$base = preg_replace( '/^The\s+(?=\S)/u', '', $base );
	if ( mb_strlen( $base ) > 24 ) { // a long journal or body name: its initials ("International Journal of Research in Medical Sciences" → IJRMS)
		$init = implode( '', array_map( fn( $w ) => mb_strtoupper( mb_substr( $w, 0, 1 ) ), array_filter( preg_split( '/[\s:,&()]+/u', $base ), fn( $w ) => '' !== $w && ! in_array( mb_strtolower( $w ), array( 'of', 'and', 'the', 'for', 'in', 'on', 'a', 'an', 'to' ), true ) ) ) );
		return mb_strlen( $init ) >= 2 ? $init : mb_substr( $base, 0, 24 );
	}
	return $base;
}

/** An item's source for its chip: [short name, full name, favicon URL]. Empty name when it has none (our own content). */
function aw_card_source( $id ) {
	$type = get_post_type( $id );
	if ( 'aw_press' === $type ) {
		$name = (string) get_post_meta( $id, 'aw_press_publisher', true );
		$host = aw_host( (string) get_post_meta( $id, 'aw_press_url', true ) );
	} elseif ( 'aw_opportunity' === $type ) {
		$name = (string) get_post_meta( $id, 'aw_org_name', true );
		$host = aw_host( (string) get_post_meta( $id, 'aw_apply_url', true ) );
		return array( aw_short_name( $name ), $name, '' );
	} else {
		list( $name, $host ) = function_exists( 'aw_publisher_of' ) ? aw_publisher_of( $id ) : array( '', '' );
		$name = (string) get_post_meta( $id, 'aw_source_name', true ) ? $name : '';
		if ( '' === $name && 'aw_legal' === $type && function_exists( 'aw_item_issuer' ) && ( $iss = aw_item_issuer( $id ) ) ) { // Library: the issuer when there is no publisher
			return array( aw_issuer_short( $iss->slug ), $iss->name, '' );
		}
	}
	if ( '' === $name ) {
		return array( '', '', '' );
	}
	$rec   = $host ? ( aw_publishers()[ $host ] ?? array() ) : array();
	$short = '' !== (string) ( $rec['short'] ?? '' ) ? (string) $rec['short'] : aw_short_name( $name );
	return array( $short, $name, $host && function_exists( 'aw_favicon_url' ) ? aw_favicon_url( $host ) : '' );
}

/** The campaign an item belongs to, for its label: [short name, colour, text colour], or null. */
function aw_card_campaign( $id ) {
	$t = function_exists( 'aw_campaign_links' ) ? aw_campaign_links( $id ) : array();
	if ( ! $t || 'aw_campaign' === get_post_type( $id ) ) {
		return null;
	}
	$page  = (int) get_term_meta( $t[0]->term_id, 'aw_campaign_page', true );
	$slug  = $page ? (string) get_post_field( 'post_name', $page ) : '';
	$look  = defined( 'AW_MAIL_CAMPAIGN_LOOKS' ) ? ( AW_MAIL_CAMPAIGN_LOOKS[ $slug ] ?? null ) : null;
	$short = (string) get_term_meta( $t[0]->term_id, 'aw_campaign_short', true );
	return array( '' !== $short ? $short : $t[0]->name, $look ? $look[0] : '#141414', '#ffffff' );
}

/** The campaign label as a chip. */
function aw_card_campaign_label( $id ) {
	$c = aw_card_campaign( $id );
	return $c ? '<span class="kit-tag kit-card-label" style="' . esc_attr( '--kit-card-label-bg:' . $c[1] . ';--kit-card-label-fg:' . $c[2] ) . '">' . esc_html( $c[0] ) . '</span>' : '';
}

/**
 * Chips: source (favicon + short name), type (with its icon), primary category, capped by size. The source shows only here.
 * The campaign label leads the row only on a card without a picture (stacked-sm); it is outside the cap.
 */
function aw_card_chips( $id, $state = 'block-md' ) {
	$type  = get_post_type( $id );
	$chips = array();
	list( $short, $full, $icon ) = aw_card_source( $id );
	if ( '' !== $short ) {
		$mark    = $icon ? '<img class="aw-src-icon" src="' . esc_url( $icon ) . '" alt="" width="16" height="16" loading="lazy" decoding="async">' : '';
		$chips[] = '<span class="kit-tag kit-tag--source"' . ( $full !== $short ? ' title="' . esc_attr( $full ) . '"' : '' ) . '>' . $mark . '<span class="kit-tag-text">' . esc_html( $short ) . '</span></span>';
	}
	$kind = array();
	if ( 'aw_legal' === $type ) {
		$kind = aw_legal_kinds( $id );
	} elseif ( in_array( $type, array( 'post', 'aw_opportunity' ), true ) ) {
		$kind = aw_item_tags( $id );
	}
	if ( $kind ) {
		$chips[] = '<span class="kit-tag kit-tag--type">' . aw_tag_icon( $kind[0]->slug ) . '<span class="kit-tag-text">' . esc_html( $kind[0]->name ) . '</span></span>';
	} elseif ( 'aw_press' === $type ) { // a press item's format, except the generic "Article"
		$fmt = (string) get_post_meta( $id, 'aw_press_format', true );
		if ( $fmt && 'article' !== $fmt ) {
			$chips[] = '<span class="kit-tag kit-tag--type">' . ( 'videos' === aw_press_group( $fmt ) ? aw_i( 'youtube' ) : '' ) . '<span class="kit-tag-text">' . esc_html( AW_PRESS_FORMATS[ $fmt ] ?? $fmt ) . '</span></span>';
		}
	}
	$cat = function_exists( 'aw_primary_category' ) ? aw_primary_category( $id ) : null;
	if ( $cat ) {
		$chips[] = '<span class="kit-tag kit-tag--category"><span class="kit-tag-text">' . esc_html( $cat->name ) . '</span></span>';
	}
	$lead = ( 'stacked-sm' === $state ? aw_card_campaign_label( $id ) : '' ) . ( function_exists( 'aw_sponsored_badge' ) ? aw_sponsored_badge( $id ) : '' );
	$chips = array_slice( $chips, 0, aw_card_chip_cap( $state ) );
	if ( '' !== $lead && $chips ) { // the label and the first chip (the source) share the first place, so the source never wraps away
		$lead = '<span class="kit-card-chips-lead">' . $lead . array_shift( $chips ) . '</span>';
	}
	$row  = $lead . implode( '', $chips );
	return '' !== $row ? '<p class="kit-card-chips kit-tags">' . $row . '</p>' : '';
}

/**
 * A card picture when the item has none of its own: its primary category's card picture (never a text tile or a
 * blurred document preview). URL, or ''.
 */
function aw_card_fallback_image( $id ) {
	$att = function_exists( 'aw_category_card_id' ) ? (int) aw_category_card_id( $id ) : 0;
	if ( ! $att ) {
		$def = (array) ( get_option( 'aw_category_images', array() )['default'] ?? array() );
		$att = (int) ( $def ? $def[ $id % count( $def ) ] : 0 );
	}
	return $att ? (string) wp_get_attachment_image_url( $att, 'large' ) : '';
}

/** Whether a block sits in a card (a kit-card group's picture, title or summary in a list). */
function aw_in_card( $block ) {
	return false !== strpos( (string) ( $block['attrs']['className'] ?? '' ), 'kit-card-' );
}

// Card summaries are plain text (no icons or dates inside).
add_filter(
	'render_block_core/post-excerpt',
	function ( $html, $block ) {
		if ( ! aw_in_card( $block ) ) {
			return $html;
		}
		return preg_replace_callback( '#(<p class="wp-block-post-excerpt__excerpt">)(.*?)(</p>)#s', fn( $m ) => $m[1] . esc_html( trim( html_entity_decode( wp_strip_all_tags( $m[2] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ) . $m[3], $html );
	},
	50,
	2
);

/*
 * Card pictures (kit-card-media): 4:3; an item with no picture gets its primary category's card picture, and so does a
 * picture that fails to load or a blurred document preview (never a text tile). A press item without a picture shows its
 * publisher's logo whole. The campaign label sits top-left on the picture.
 */
add_filter(
	'render_block_core/post-featured-image',
	function ( $html, $block, $instance ) {
		if ( ! aw_in_card( $block ) ) {
			return $html;
		}
		$id   = (int) ( $instance->context['postId'] ?? 0 );
		$fall = $id ? aw_card_fallback_image( $id ) : '';
		$prev = 'aw_legal' === get_post_type( $id ) && ! get_post_meta( $id, 'aw_file_id', true ) ? (int) get_post_meta( $id, 'aw_doc_preview', true ) : 0;
		if ( $prev && false !== strpos( $html, (string) wp_basename( (string) get_attached_file( $prev ) ) ) ) {
			$html = ''; // a blurred document preview is not a card picture
		}
		if ( '' === trim( $html ) ) {
			$logo = 'aw_press' === get_post_type( $id ) ? (int) get_post_meta( $id, 'aw_press_logo', true ) : 0;
			$src  = $logo ? (string) wp_get_attachment_image_url( $logo, 'medium' ) : $fall;
			if ( '' === $src ) {
				return '';
			}
			$img  = '<img src="' . esc_url( $src ) . '" alt="" loading="lazy" decoding="async">';
			$img  = ! empty( $block['attrs']['isLink'] ) && 'aw_press' !== get_post_type( $id ) ? '<a href="' . esc_url( get_permalink( $id ) ) . '" tabindex="-1" aria-hidden="true">' . $img . '</a>' : $img;
			$html = '<figure class="wp-block-post-featured-image kit-card-media' . ( $logo ? ' is-logo' : '' ) . ' ' . esc_attr( (string) $block['attrs']['className'] ) . '">' . $img . '</figure>';
		}
		$html = preg_replace( '#<div class="kit-placard[^"]*"[^>]*>.*?</div>#s', '', $html ); // no fallback tile behind the picture
		if ( '' !== $fall ) { // a picture that fails: the category's picture
			$html = preg_replace( '/onerror="[^"]*"/', 'onerror="this.onerror=null;this.removeAttribute(\'srcset\');this.src=\'' . esc_url( $fall ) . '\'"', $html, 1 );
			$html = false === strpos( $html, 'onerror=' ) ? preg_replace( '/<img /', '<img onerror="this.onerror=null;this.removeAttribute(\'srcset\');this.src=\'' . esc_url( $fall ) . '\'" ', $html, 1 ) : $html;
		}
		$label = aw_card_campaign_label( $id );
		return '' !== $label ? preg_replace( '#</figure>\s*$#', str_replace( 'kit-tag kit-card-label', 'kit-card-label', $label ) . '</figure>', $html, 1 ) : $html;
	},
	25,
	3
);
