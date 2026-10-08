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
		$trail = '<li><a href="' . esc_url( home_url( '/' ) ) . '">Home</a></li>';
		foreach ( array_reverse( get_post_ancestors( $post ) ) as $a ) {
			$trail .= '<li><a href="' . esc_url( get_permalink( $a ) ) . '">' . esc_html( get_the_title( $a ) ) . '</a></li>';
		}
		return '<nav class="kit-crumbs" aria-label="Breadcrumb"><ol>' . $trail . '<li><span aria-current="page">' . esc_html( get_the_title( $post ) ) . '</span></li></ol></nav>';
	}
	return function_exists( 'aw_breadcrumbs_claim' ) ? aw_breadcrumbs_claim() : '';
}

function aw_block_hub_count( $a ) {
	$type = (string) ( $a['postType'] ?? '' );
	if ( ! isset( AW_HUBS[ $type ] ) || in_array( $type, array( 'aw_listing', 'aw_press' ), true ) ) {
		return ''; // the Directory's count sits with its results; Media & PR has none
	}
	$n    = aw_hub_total( $type );
	$noun = array( 'aw_guide' => 'guides', 'aw_opportunity' => 'opportunities', 'aw_campaign' => 'campaigns', 'aw_legal' => 'documents', 'aw_news' => 'stories' );
	$word = 1 === $n ? rtrim( preg_replace( '/ies$/', 'y', $noun[ $type ] ), 's' ) : $noun[ $type ];
	if ( 'aw_campaign' === $type ) {
		$n = (int) wp_count_posts( 'aw_campaign' )->publish; // all campaigns, the 1.0 story included
		$word = 1 === $n ? 'campaign' : 'campaigns';
	}
	return $n ? '<p class="kit-section-count">' . esc_html( number_format_i18n( $n ) . ' ' . $word ) . '</p>' : '';
}

/** Three pictures from the section (campaign photos for Campaigns). */
function aw_block_hub_visual( $a ) {
	$type = (string) ( $a['postType'] ?? '' );
	if ( ! $type ) {
		return '';
	}
	$tiles = '';
	if ( 'aw_listing' === $type ) { // Directory: the photos of three categories it serves
		foreach ( array( 'veterinary-care', 'community-dogs', 'cattle-gaushalas' ) as $slug ) {
			$t   = get_term_by( 'slug', $slug, 'aw_topic' );
			$tid = $t && function_exists( 'aw_topic_image_id' ) ? (int) aw_topic_image_id( $t ) : 0;
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
		return $name ? '<p class="aw-src">' . ( $icon ? $icon : ( $logo ? '<img class="aw-src-icon" src="' . esc_url( $logo ) . '" alt="" width="16" height="16" loading="lazy">' : '' ) ) . '<span class="aw-src-name">' . esc_html( $name ) . '</span></p>' : '';
	}
	return function_exists( 'aw_source_row_html' ) ? aw_source_row_html( $id ) : '';
}

/** Library: the law's year (Acts, Rules), the source date, or the template icon. */
function aw_block_doc_date( $a, $id ) {
	if ( has_term( 'template', 'aw_legal_kind', $id ) ) {
		return '<div class="aw-case-date aw-case-icon">' . aw_i( 'resources' ) . '</div>';
	}
	$year  = has_term( array( 'act', 'rule' ), 'aw_legal_kind', $id ) && preg_match( '/\b(1[89]|20)\d{2}\b/', get_the_title( $id ), $ym ) ? $ym[0] : '';
	$thumb = (int) get_post_thumbnail_id( $id );
	$cover = $thumb ? (string) wp_get_attachment_image( $thumb, 'medium', false, array( 'class' => 'aw-case-cover', 'alt' => '', 'loading' => 'lazy' ) ) : ''; // the document's first page
	$when  = $year ? '<span class="d">' . esc_html( $year ) . '</span>' : '<span class="d">' . esc_html( aw_src_date( $id, 'j M Y' ) ) . '</span>';
	return '<div class="aw-case-date' . ( $cover ? ' has-cover' : '' ) . '">' . $cover . $when . '</div>';
}

/** One-line date of a Library item: its own date ("1st November, 2025"), or the year of an Act or Rule; never the import date. */
function aw_block_law_date( $a, $id ) {
	if ( has_term( 'template', 'aw_legal_kind', $id ) ) {
		return '';
	}
	$d = (string) get_post_meta( $id, 'aw_source_date', true );
	if ( $d ) {
		return '<time class="aw-law-date" datetime="' . esc_attr( $d ) . '">' . esc_html( wp_date( 'jS F, Y', strtotime( $d . ' 12:00:00' ) ) ) . '</time>';
	}
	return preg_match( '/\b(1[89]|20)\d{2}\b/', get_the_title( $id ), $ym ) ? '<time class="aw-law-date" datetime="' . esc_attr( $ym[0] ) . '">' . esc_html( $ym[0] ) . '</time>' : '';
}

/** "Issued by …" (a label, not a filter); "Template" for templates. */
function aw_block_issuer( $a, $id ) {
	if ( has_term( 'template', 'aw_legal_kind', $id ) ) {
		return kits_tag( 'Template', 'accent' );
	}
	$iss = aw_item_issuer( $id );
	return $iss ? kits_tag( 'Issued by ' . $iss->name, in_array( $iss->slug, AW_COURT_ISSUERS, true ) ? 'ink' : 'info' ) : '';
}

/** Source line and the official document (PDF) on Library items. */
function aw_block_doc_link( $a, $id ) {
	if ( has_term( 'template', 'aw_legal_kind', $id ) ) {
		return '';
	}
	$src  = (string) get_post_meta( $id, 'aw_source_name', true );
	$fid  = (int) get_post_meta( $id, 'aw_file_id', true );
	$pdf  = $fid ? (string) wp_get_attachment_url( $fid ) : '';
	$html = ( $src ? 'Source: ' . esc_html( $src ) : '' ) . ( $pdf ? ' <a class="aw-doc" href="' . esc_url( $pdf ) . '">' . aw_i( 'research' ) . ' Official document (PDF)</a>' : '' );
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
	return '<p class="aw-task-meta"><span>' . aw_i( 'calendar' ) . 'Updated <time datetime="' . esc_attr( get_the_modified_date( 'Y-m-d', $id ) ) . '">' . esc_html( get_the_modified_date( 'j M Y', $id ) ) . '</time></span><span>' . aw_i( 'clock' ) . (int) $mins . ' min read</span></p>';
}

/* ---------- Opportunities ---------- */

function aw_block_place( $a, $id ) {
	$p = function_exists( 'aw_item_place' ) ? aw_item_place( $id ) : null;
	return $p ? '<span class="aw-place">' . aw_i( 'directory' ) . esc_html( $p->name ) . '</span>' : '';
}

/** "Checked by Animal Writes on …" and "Apply by …" (validThrough). */
function aw_block_deadline( $a, $id ) {
	$dl = (string) get_post_meta( $id, 'aw_deadline', true );
	return '<p class="aw-opp-trust">' . aw_i( 'check' ) . 'Checked by Animal Writes on ' . esc_html( get_the_modified_date( 'j M Y', $id ) ) . ( $dl ? ' · Apply by <time datetime="' . esc_attr( $dl ) . '">' . esc_html( wp_date( 'j M Y', strtotime( $dl ) ) ) . '</time>' : '' ) . '</p>';
}

function aw_block_opp_actions( $a, $id ) {
	$apply = (string) get_post_meta( $id, 'aw_apply_url', true );
	return '<div class="aw-opp-actions">' . ( $apply ? '<a class="kit-btn kit-btn--sm" href="' . esc_url( $apply ) . '" rel="noopener">Apply or sign up</a>' : '' ) . '<a class="kit-btn kit-btn--sm kit-btn--secondary" href="' . esc_url( get_permalink( $id ) ) . '">Details</a></div>';
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

/** Verified, Featured and Sponsored badges, where the details came from, and up to two types. */
function aw_block_badges( $a, $id ) {
	$out = '';
	if ( get_post_meta( $id, 'aw_verified', true ) ) {
		$out .= kits_badge( 'Verified', 'verified' );
	}
	if ( (int) get_post_meta( $id, 'aw_featured', true ) ) {
		$out .= kits_badge( 'Featured', 'featured' );
	}
	if ( get_post_meta( $id, 'aw_sponsored', true ) ) {
		$out .= kits_badge( 'Sponsored', 'sponsored' );
	}
	$out  .= function_exists( 'aw_source_labels' ) ? aw_source_labels( $id ) : '';
	$types = get_the_terms( $id, 'aw_listing_type' );
	foreach ( ( $types && ! is_wp_error( $types ) ) ? array_slice( $types, 0, 2 ) : array() as $t ) {
		$out .= kits_tag( $t->name );
	}
	return $out ? '<p class="aw-org-tags kit-tags">' . $out . '</p>' : '';
}

/** Ways to reach the organisation, shown only when they exist. */
function aw_block_contact( $a, $id ) {
	$phone = (string) get_post_meta( $id, 'aw_phone', true );
	$email = (string) get_post_meta( $id, 'aw_email', true );
	$site  = (string) get_post_meta( $id, 'aw_website', true );
	if ( ! $phone && ! $email && ! $site ) {
		return '';
	}
	return '<p class="aw-org-contact">' . ( $phone ? aw_i( 'phone' ) . '<span class="screen-reader-text">Phone</span>' : '' ) . ( $email ? aw_i( 'mail' ) . '<span class="screen-reader-text">Email</span>' : '' ) . ( $site ? aw_i( 'arrow-up-right' ) . '<span class="screen-reader-text">Website</span>' : '' ) . '</p>';
}

function aw_block_org_actions( $a, $id ) {
	$phone = (string) get_post_meta( $id, 'aw_phone', true );
	return '<div class="aw-org-actions">' . ( $phone ? '<a class="kit-btn kit-btn--sm kit-btn--secondary" href="' . esc_attr( aw_tel_url( $phone ) ) . '">' . aw_i( 'phone' ) . 'Call</a>' : '' )
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
	return '<p class="aw-story-meta kit-tags">' . ( $handle ? '<span class="aw-press-handle">' . esc_html( $handle ) . '</span>' : '' ) . ( $date ? '<time datetime="' . esc_attr( $date ) . '">' . esc_html( wp_date( 'j M Y', strtotime( $date ) ) ) . '</time>' : '' ) . ( $video ? kits_badge( 'Video', 'new' ) : ( $fmt ? kits_tag( AW_PRESS_FORMATS[ $fmt ] ?? $fmt ) : '' ) ) . '</p>';
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
		return preg_replace( '#<a href="[^"]*"[^>]*>(.*?)</a>#s', '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">$1 ' . aw_i( 'arrow-up-right' ) . '<span class="screen-reader-text"> (opens in a new tab)</span></a>', $html, 1 );
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
