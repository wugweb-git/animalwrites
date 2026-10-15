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
	return array(
		'breadcrumb'    => array( 'Breadcrumb', 'aw_block_breadcrumb', array() ),
		'hub-count'     => array( 'Hub count', 'aw_block_hub_count', $type_attr ),
		'hub-visual'    => array( 'Hub pictures', 'aw_block_hub_visual', $type_attr ),
		'list-filters'  => array( 'List filters', 'aw_block_list_filters', $type_attr ),
		'publisher'     => array( 'Publisher', 'aw_block_publisher', array() ),
		'doc-date'      => array( 'Document date', 'aw_block_doc_date', array() ),
		'type-date'     => array( 'Type and date (one line)', 'aw_block_type_date', array() ),
		'law-date'      => array( 'Law date (one line)', 'aw_block_law_date', array() ),
		'issuer'        => array( 'Issued by', 'aw_block_issuer', array() ),
		'doc-link'      => array( 'Document source and PDF', 'aw_block_doc_link', array() ),
		'task-icon'     => array( 'Guide icon', 'aw_block_task_icon', array() ),
		'read-meta'     => array( 'Updated and reading time', 'aw_block_read_meta', array() ),
		'place'         => array( 'Place', 'aw_block_place', array() ),
		'deadline'      => array( 'Checked and deadline', 'aw_block_deadline', array() ),
		'opp-actions'   => array( 'Opportunity buttons', 'aw_block_opp_actions', array() ),
		'org-mark'      => array( 'Organisation logo', 'aw_block_org_mark', array() ),
		'badges'        => array( 'Badges and labels', 'aw_block_badges', array() ),
		'contact'       => array( 'Contact icons', 'aw_block_contact', array() ),
		'org-actions'   => array( 'Organisation buttons', 'aw_block_org_actions', array() ),
		'result-head'   => array( 'Result count and sort', 'aw_block_result_head', array() ),
		'browse-states' => array( 'Browse by state', 'aw_block_browse_states', array() ),
		'press-media'   => array( 'Press thumbnail', 'aw_block_press_media', array() ),
		'press-meta'    => array( 'Press date and format', 'aw_block_press_meta', array() ),
		'posted-by'     => array( 'Posted by', 'aw_block_posted_by', array() ),
		'volunteer'     => array( 'Volunteer form', 'aw_block_volunteer', array( 'open' => array( 'type' => 'boolean', 'default' => false ) ) ),
		'listing-help'  => array( 'Is this your organisation?', 'aw_block_listing_help', array() ),
		'org-details'   => array( 'Organisation details', 'aw_block_org_details', array() ),
	);
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
	return ''; // owner, 9 Oct: the count sits above the pagination (hubs.php), not in the band
}

/** "23 stories": the number of items in a hub list with the current filters. */
function aw_hub_count_label( $type ) {
	$noun = array( 'post' => array( 'guide', 'guides' ), 'aw_opportunity' => array( 'opportunity', 'opportunities' ), 'aw_campaign' => array( 'campaign', 'campaigns' ), 'aw_legal' => array( 'document', 'documents' ), 'aw_news' => array( 'story', 'stories' ), 'aw_listing' => array( 'organisation', 'organisations' ), 'aw_press' => array( 'item', 'items' ) );
	if ( ! isset( $noun[ $type ] ) ) {
		return '';
	}
	$n = 'aw_campaign' === $type ? (int) wp_count_posts( 'aw_campaign' )->publish : aw_hub_total( $type ); // all campaigns, the 1.0 story included
	return $n ? number_format_i18n( $n ) . ' ' . $noun[ $type ][ 1 === $n ? 0 : 1 ] : '';
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
	return $out ? '<div class="kit-section-filters">' . $out . '</div>' : '';
}

/* ---------- News, Library, Resources ---------- */

/** Publisher label (favicon + name) for News; publisher and logo for press items. */
function aw_block_publisher( $a, $id ) {
	if ( 'aw_press' === get_post_type( $id ) ) {
		$pub  = (string) get_post_meta( $id, 'aw_press_publisher', true );
		$logo = (int) get_post_meta( $id, 'aw_press_logo', true );
		$logo = $logo ? (string) wp_get_attachment_image_url( $logo, 'thumbnail' ) : '';
		$plat = (string) get_post_meta( $id, 'aw_press_platform', true );
		$icon = in_array( $plat, array( 'instagram', 'facebook', 'x' ), true ) ? aw_i( $plat ) : '';
		$name = $pub ? $pub : ( 'web' !== $plat && $plat ? ( AW_PRESS_PLATFORMS[ $plat ] ?? '' ) : '' );
		// Same row as News: the publisher's small logo (News favicon for that site, else its own logo), else a letter tile.
		$host = function_exists( 'aw_host' ) ? aw_host( (string) get_post_meta( $id, 'aw_press_url', true ) ) : '';
		$fav  = $host && function_exists( 'aw_favicon_url' ) ? aw_favicon_url( $host ) : '';
		$fav  = $fav ? $fav : $logo;
		$mark = $icon ? $icon : ( $fav ? '<img class="aw-src-icon" src="' . esc_url( $fav ) . '" alt="" width="16" height="16" loading="lazy" decoding="async">' : ( $name ? '<span class="aw-src-icon aw-src-icon--letter" aria-hidden="true">' . esc_html( mb_strtoupper( mb_substr( $name, 0, 1 ) ) ) . '</span>' : '' ) );
		return $name ? '<p class="aw-src">' . $mark . '<span class="aw-src-name">' . esc_html( $name ) . '</span></p>' : '';
	}
	return aw_news_meta_row( $id );
}

/**
 * The news card's meta, one row on every news card (News, home, category pages): publisher (logo tile + name) on the
 * left, when it was published (calendar icon, relative time) after it.
 *
 * @param int $id Post ID.
 * @return string
 */
function aw_news_meta_row( $id ) {
	$src  = function_exists( 'aw_source_row_html' ) ? aw_source_row_html( $id ) : '';
	$time = function_exists( 'aw_rel_time_html' ) ? '<span class="wp-block-post-date aw-card-time">' . aw_rel_time_html( $id ) . '</span>' : '';
	return '' === $src . $time ? '' : '<div class="aw-card-meta">' . $src . $time . '</div>';
}

/** Library: the law's year (Acts, Rules), the source date, or the template icon. */
function aw_block_doc_date( $a, $id ) {
	if ( has_term( 'template', 'aw_legal_kind', $id ) ) {
		return '<div class="aw-case-date aw-case-icon">' . aw_i( 'resources' ) . '</div>';
	}
	$year  = has_term( array( 'act', 'rule' ), 'aw_legal_kind', $id ) && preg_match( '/\b(1[89]|20)\d{2}\b/', get_the_title( $id ), $ym ) ? $ym[0] : '';
	$thumb = (int) get_post_thumbnail_id( $id );
	$cover = $thumb ? (string) wp_get_attachment_image( $thumb, 'medium', false, array( 'class' => 'aw-case-cover', 'alt' => '', 'loading' => 'lazy' ) ) : ''; // the document's first page
	$pub   = function_exists( 'aw_is_ingested' ) && aw_is_ingested( $id ) && ! get_post_meta( $id, 'aw_file_id', true ) ? (string) aw_item_image( $id, 'medium' ) : '';
	if ( $pub && ( ! $thumb || false === strpos( $cover, $pub ) ) ) { // ingested Library items: the publisher's image first, like News (owner, 14 Oct 2026)
		$cover = '<img class="aw-case-cover aw-case-cover--photo" src="' . esc_url( $pub ) . '" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="this.remove()">';
	}
	$kind  = wp_get_object_terms( $id, 'aw_legal_kind', array( 'fields' => 'names' ) );
	$kind  = is_wp_error( $kind ) || ! $kind ? '' : $kind[0];
	$date  = $year ? $year : aw_src_date( $id, 'j M Y' );
	$src   = function_exists( 'aw_source_row_html' ) && get_post_meta( $id, 'aw_source_name', true ) ? aw_source_row_html( $id ) : ''; // publisher logo + name first, as on News
	$text  = $year ? aw_date( $year . '-01-01', 'Y' ) : aw_date( aw_src_date( $id, 'Y-m-d' ) ); // publisher, then the date; the type is a label with the others (owner, 14 Oct 2026)
	$when  = '<span class="d">' . $src . ( $text ? '<span class="aw-kind-date">' . $text . '</span>' : '' ) . '</span>';
	return '<div class="aw-case-date' . ( $cover ? ' has-cover' : '' ) . '">' . $cover . $when . '</div>';
}

/** "{Type} · {Date}" above a card title: a Resource's type and when it was last updated, or a Library item's kind and date. */
function aw_block_type_date( $a, $id ) {
	$type = get_post_type( $id );
	$tax  = 'post' === $type ? 'aw_resource_type' : ( 'aw_legal' === $type ? 'aw_legal_kind' : '' );
	$name = '';
	if ( $tax ) {
		$t    = wp_get_object_terms( $id, $tax, array( 'fields' => 'names' ) );
		$name = is_wp_error( $t ) || ! $t ? '' : $t[0];
	}
	$date = aw_date( 'post' === $type ? get_the_modified_date( 'Y-m-d', $id ) : aw_src_date( $id, 'Y-m-d' ) );
	$text = ( $name ? esc_html( $name ) . ( $date ? ' · ' : '' ) : '' ) . $date;
	$badge = function_exists( 'aw_sponsored_badge' ) ? aw_sponsored_badge( $id ) : '';
	return $text || $badge ? '<p class="aw-case-meta">' . $badge . ( $badge && $text ? ' ' : '' ) . $text . '</p>' : '';
}

/** One-line date of a Library item: its own date ("1st November, 2025"), or the year of an Act or Rule; never the import date. */
function aw_block_law_date( $a, $id ) {
	if ( has_term( 'template', 'aw_legal_kind', $id ) ) {
		return '';
	}
	$d = (string) get_post_meta( $id, 'aw_source_date', true );
	if ( $d ) {
		return aw_date( $d . ' 12:00:00' );
	}
	return preg_match( '/\b(1[89]|20)\d{2}\b/', get_the_title( $id ), $ym ) ? aw_date( $ym[0] . '-01-01 12:00:00', 'Y' ) : '';
}

/** "Issued by …" (a label, not a filter); "Template" for templates. */
function aw_block_issuer( $a, $id ) {
	if ( has_term( 'template', 'aw_legal_kind', $id ) ) {
		return aw_legal_kind_tag( $id );
	}
	$iss  = aw_item_issuer( $id );
	$kind = aw_legal_kind_tag( $id ); // the Library type (Official release, Circular, Judgment ...) as a label
	return $kind . ( $iss ? kits_tag( $iss->name, in_array( $iss->slug, AW_COURT_ISSUERS, true ) ? 'ink' : 'info' ) : '' ); // just the court / body (owner, 14 Oct 2026)
}

/**
 * A Library item's type label: its icon, then its name (owner, 15 Oct 2026), the same everywhere the label appears.
 * Templates keep their accent tint. Never a logo; never in a logo slot.
 *
 * @param int $id Library item.
 * @return string '' when the item has no type.
 */
function aw_legal_kind_tag( $id ) {
	$t = wp_get_object_terms( $id, 'aw_legal_kind' );
	if ( is_wp_error( $t ) || ! $t ) {
		return '';
	}
	$t = $t[0];
	return '<span class="kit-tag' . ( 'template' === $t->slug ? ' kit-tag--accent' : '' ) . '">' . aw_type_icon( 'lib-' . $t->slug ) . esc_html( $t->name ) . '</span>';
}

/** Source line and the official document (PDF) on Library items. */
function aw_block_doc_link( $a, $id ) {
	if ( has_term( 'template', 'aw_legal_kind', $id ) ) {
		return '';
	}
	$fid  = (int) get_post_meta( $id, 'aw_file_id', true );
	$pdf  = $fid ? (string) wp_get_attachment_url( $fid ) : '';
	$html = ( $pdf ? ' <a class="aw-doc" href="' . esc_url( $pdf ) . '">' . aw_i( 'research' ) . ' Official document (PDF)</a>' : '' );
	return '' !== trim( $html ) ? '<p class="aw-case-src">' . $html . '</p>' : '';
}

/** Resources: the guide's icon (by subject). */
function aw_block_task_icon( $a, $id ) {
	$icons = array( 'injur' => 'injured', 'cruel' => 'alert', 'birth' => 'knowledge', 'abc' => 'knowledge', 'caring' => 'opportunities', 'communit' => 'opportunities' );
	$slug  = (string) get_post_field( 'post_name', $id );
	$ico   = 'knowledge';
	foreach ( $icons as $k => $v ) {
		if ( false !== strpos( $slug, $k ) ) {
			$ico = $v;
			break;
		}
	}
	return aw_i( $ico );
}

function aw_block_read_meta( $a, $id ) {
	$mins = max( 1, (int) round( str_word_count( wp_strip_all_tags( (string) get_post_field( 'post_content', $id ) ) ) / 200 ) );
	$badge = function_exists( 'aw_sponsored_badge' ) ? aw_sponsored_badge( $id ) : '';
	return '<p class="aw-task-meta">' . $badge . '<span>Updated ' . aw_date( get_the_modified_date( 'Y-m-d', $id ) ) . '</span><span>' . aw_i( 'clock' ) . (int) $mins . ' min read</span></p>';
}

/* ---------- Opportunities ---------- */

function aw_block_place( $a, $id ) {
	$p = function_exists( 'aw_item_place' ) ? aw_item_place( $id ) : null;
	return $p ? '<span class="aw-place">' . aw_i( 'directory' ) . esc_html( $p->name ) . '</span>' : '';
}

/** "Checked by Animal Writes on …" and "Apply by …" (validThrough). */
function aw_block_deadline( $a, $id ) {
	$dl = (string) get_post_meta( $id, 'aw_deadline', true );
	return '<p class="aw-opp-trust">' . aw_i( 'check' ) . 'Checked by Animal Writes on ' . aw_date( get_the_modified_date( 'Y-m-d', $id ) ) . ( $dl ? ' · Apply by ' . aw_date( $dl ) . '' : '' ) . '</p>';
}

function aw_block_opp_actions( $a, $id ) {
	$apply = (string) get_post_meta( $id, 'aw_apply_url', true );
	return '<div class="aw-opp-actions">' . ( $apply ? '<a class="kit-btn kit-btn--sm" href="' . esc_url( $apply ) . '" rel="noopener" aria-label="' . esc_attr( 'Apply or sign up: ' . get_the_title( $id ) ) . '">Apply or sign up</a>' : '' ) . '<a class="kit-btn kit-btn--sm kit-btn--secondary" href="' . esc_url( get_permalink( $id ) ) . '" aria-label="' . esc_attr( 'Details: ' . get_the_title( $id ) ) . '">Details</a></div>';
}

/** Single opportunity: "Posted by {organisation}", linked to its Directory profile when there is one. */
function aw_block_posted_by( $a, $id ) {
	$name    = (string) get_post_meta( $id, 'aw_org_name', true );
	$listing = (int) get_post_meta( $id, 'aw_organisation', true );
	if ( $listing && 'publish' === get_post_status( $listing ) ) {
		$name = $name ? $name : get_the_title( $listing );
		return '<p class="aw-posted-by">Posted by <a href="' . esc_url( get_permalink( $listing ) ) . '">' . esc_html( $name ) . '</a></p>';
	}
	return $name ? '<p class="aw-posted-by">Posted by ' . esc_html( $name ) . '</p>' : '';
}

/* ---------- Directory ---------- */

function aw_block_org_mark( $a, $id ) {
	$logo     = get_the_post_thumbnail_url( $id, 'thumbnail' );
	$words    = array_values( array_filter( preg_split( '/\s+/', preg_replace( '/[^\p{L}\s]/u', '', get_the_title( $id ) ) ) ) ); // "1962 Mobile Veterinary Unit" → MV
	$initials = mb_strtoupper( mb_substr( $words[0] ?? '', 0, 1 ) . mb_substr( $words[1] ?? '', 0, 1 ) );
	return '<div class="aw-org-mark">' . ( $logo ? '<img src="' . esc_url( $logo ) . '" alt="" loading="lazy">' : '<span aria-hidden="true">' . esc_html( $initials ) . '</span>' ) . '</div>';
}

/** Up to two listing types (the card's labels). */
function aw_block_badges( $a, $id ) {
	$out = '';
	// Cards show the type only (owner, 12 Oct 2026): Verified sits on the name, sources are on the profile (Details from).
	$types = get_the_terms( $id, 'aw_listing_type' );
	foreach ( ( $types && ! is_wp_error( $types ) ) ? array_slice( $types, 0, 2 ) : array() as $t ) {
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

// Organisation page: the listing type label carries the same icon as on the Directory cards.
add_filter(
	'render_block_core/post-terms',
	function ( $html, $block, $instance ) {
		if ( 'aw_listing_type' !== ( $block['attrs']['term'] ?? '' ) || '' === $html ) {
			return $html;
		}
		$id    = (int) ( $instance->context['postId'] ?? get_the_ID() );
		$terms = get_the_terms( $id, 'aw_listing_type' );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return $html;
		}
		foreach ( $terms as $t ) {
			$html = preg_replace( '#(<a\b[^>]*>)(' . preg_quote( esc_html( $t->name ), '#' ) . '</a>)#u', '$1' . aw_type_icon( $t->slug ) . '$2', $html, 1 );
		}
		return $html;
	},
	5, // before the breadcrumb trail is put in front (breadcrumbs.php)
	3
);

/** Ways to reach the organisation, shown only when they exist. */
function aw_block_contact( $a, $id ) {
	return ''; // not on the Directory index (owner, 12 Oct 2026): the card's Call button and the profile carry the contact details
	$phone = (string) get_post_meta( $id, 'aw_phone', true );
	$email = (string) get_post_meta( $id, 'aw_email', true );
	$site  = (string) get_post_meta( $id, 'aw_website', true );
	if ( ! $phone && ! $email ) { // the website arrow is not shown on cards (owner, 12 Oct 2026)
		return '';
	}
	return '<p class="aw-org-contact">' . ( $phone ? aw_i( 'phone' ) . '<span class="screen-reader-text">Phone</span>' : '' ) . ( $email ? aw_i( 'mail' ) . '<span class="screen-reader-text">Email</span>' : '' ) . '' . '</p>';
}

function aw_block_org_actions( $a, $id ) {
	$phone = (string) get_post_meta( $id, 'aw_phone', true );
	return '<div class="aw-org-actions">' // no Call button on cards (owner, 12 Oct 2026)
		. '<a class="aw-org-go" href="' . esc_url( get_permalink( $id ) ) . '" aria-label="' . esc_attr( 'View ' . get_the_title( $id ) ) . '">' . aw_i( 'arrow-right' ) . '</a></div>';
}

/** Directory: "976 results for Kerala" and the sort (A–Z / Most details first). */
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
	return '<div class="aw-dir-results-head"><p role="status">' . esc_html( $line ) . '</p>'
		. '<nav class="kit-segment" aria-label="Sort"><a href="' . esc_url( $here ) . '"' . ( $sort ? '' : ' aria-current="true"' ) . '>A–Z</a><a href="' . esc_url( add_query_arg( 'sort', 'details', $here ) ) . '"' . ( $sort ? ' aria-current="true"' : '' ) . '>Most details first</a></nav></div>';
}

function aw_block_browse_states() {
	return function_exists( 'aw_browse_states' ) ? aw_browse_states() : '';
}

/* ---------- Media & PR ---------- */

/** Thumbnail with the publisher logo as fallback, and a play mark on videos. */
function aw_block_press_media( $a, $id ) {
	$pub      = (string) get_post_meta( $id, 'aw_press_publisher', true );
	$fmt      = (string) get_post_meta( $id, 'aw_press_format', true );
	$logo     = (int) get_post_meta( $id, 'aw_press_logo', true );
	$logo     = $logo ? (string) wp_get_attachment_image_url( $logo, 'thumbnail' ) : '';
	$thumb    = (string) get_the_post_thumbnail_url( $id, 'medium_large' );
	$video    = (string) get_post_meta( $id, 'aw_press_video', true );
	$fallback = $logo ? '<img class="aw-story-logo" src="' . esc_url( $logo ) . '" alt="" loading="lazy">' : '<span class="aw-story-ph">' . esc_html( $pub ? $pub : 'Media' ) . '</span>';
	$media    = $thumb
		? '<img src="' . esc_url( $thumb ) . '" alt="" loading="lazy" decoding="async" onerror="this.parentNode.classList.add(\'is-failed\')"><span class="aw-story-fallback">' . $fallback . '</span>'
		: '<span class="aw-story-fallback">' . $fallback . '</span>';
	return '<div class="kit-story-media' . ( $thumb ? '' : ' is-failed' ) . '">' . $media . ( $video || 'video' === $fmt ? '<span class="aw-story-play" aria-hidden="true">▶</span>' : '' ) . '</div>';
}

/** Date and format tag (Article, Video …). */
function aw_block_press_meta( $a, $id ) {
	$date = (string) get_post_meta( $id, 'aw_press_date', true );
	$fmt  = (string) get_post_meta( $id, 'aw_press_format', true );
	$handle = (string) get_post_meta( $id, 'aw_press_account', true );
	$video  = $fmt && 'videos' === aw_press_group( $fmt );
	return '<p class="aw-story-meta kit-tags">' . ( $handle ? '<span class="aw-press-handle">' . esc_html( $handle ) . '</span>' : '' ) . ( $date ? aw_date( $date ) : '' ) . ( $video ? kits_badge( 'Video', 'new' ) : ( $fmt ? kits_tag( AW_PRESS_FORMATS[ $fmt ] ?? $fmt ) : '' ) ) . '</p>';
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
