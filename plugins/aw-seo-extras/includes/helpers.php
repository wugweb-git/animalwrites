<?php
/**
 * Shared helpers: text cutting, page numbers, a page's own intro, the site's naming rules, and share images.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cut text at a word boundary to at most $max characters, adding $more ("…") when cut.
 *
 * @param string $text Plain text.
 * @param int    $max  Maximum length.
 * @param string $more Added when the text was cut ('' for none).
 * @return string
 */
function aw_seo_cut( $text, $max, $more = '…' ) {
	$text = trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
	if ( mb_strlen( $text ) <= $max ) {
		return $text;
	}
	$room = $max - mb_strlen( $more );
	$cut  = mb_substr( $text, 0, $room );
	$sp   = mb_strrpos( $cut, ' ' );
	if ( ' ' !== mb_substr( $text, $room, 1 ) && false !== $sp && $sp > $max * 0.6 ) { // never end inside a word
		$cut = mb_substr( $cut, 0, $sp );
	}
	$cut = preg_replace( '/[\s,;:\x{2013}\x{2014}-]+$/u', '', $cut );
	if ( '' === $more && substr_count( $cut, '(' ) > substr_count( $cut, ')' ) ) {
		$cut = preg_replace( '/[\s,;:\x{2013}\x{2014}-]+$/u', '', mb_substr( $cut, 0, mb_strrpos( $cut, '(' ) ) ); // no half bracket
	}
	return $cut . $more;
}

/** Page number of a list view (hub Page, Directory place, category): 1 on the first page. */
function aw_seo_page_number() {
	return max( 1, (int) get_query_var( 'paged' ), is_singular() ? 1 : (int) get_query_var( 'page' ) );
}

/** " – Page N" for the second and later pages of a list, '' otherwise. */
function aw_seo_page_suffix() {
	$n = aw_seo_page_number();
	return $n > 1 ? ' – Page ' . $n : '';
}

/** $text with the page suffix, unless it already names the page. */
function aw_seo_with_page( $text ) {
	$suffix = aw_seo_page_suffix();
	return ( '' === $suffix || false !== stripos( $text, 'Page ' . aw_seo_page_number() ) ) ? $text : $text . $suffix;
}

/**
 * The page's own intro: its first meaningful paragraph(s), skipping boilerplate ("Last updated…", short labels and
 * button text), headings and shortcodes. Empty when the page has no such paragraph.
 *
 * @param WP_Post $post Post.
 * @return string
 */
function aw_seo_intro( $post ) {
	$content = (string) $post->post_content;
	$skip    = '/^(last\s+(updated|reviewed|modified)|updated( on)?|effective( from| date)?|published( on)?|date)\b/i';
	if ( false === strpos( $content, '<!-- wp:' ) && false === stripos( $content, '<p' ) ) {
		return trim( wp_strip_all_tags( strip_shortcodes( $content ) ) ); // plain text: all of it
	}
	$out = '';
	if ( preg_match_all( '#<p\b[^>]*>(.*?)</p>#si', strip_shortcodes( $content ), $m ) ) {
		foreach ( $m[1] as $para ) {
			$t = trim( html_entity_decode( wp_strip_all_tags( $para ), ENT_QUOTES, 'UTF-8' ) );
			if ( mb_strlen( $t ) < 40 || preg_match( $skip, $t ) ) {
				continue;
			}
			$out = trim( $out . ' ' . $t );
			if ( mb_strlen( $out ) >= 120 ) {
				break;
			}
		}
	}
	return $out;
}

/**
 * What the editor typed in Rank Math for the current view: post or term meta "rank_math_{$key}" ('' when empty).
 *
 * @param string $key Field, e.g. title, description, canonical_url, facebook_image_id.
 * @return string
 */
function aw_seo_typed( $key ) {
	$id = get_queried_object_id();
	if ( ! $id ) {
		return '';
	}
	if ( is_singular() ) {
		$typed = trim( (string) get_post_meta( $id, 'rank_math_' . $key, true ) );
		if ( '' !== $typed && in_array( $key, array( 'title', 'description' ), true ) && aw_seo_is_hub_page( $id ) ) {
			$auto = (array) json_decode( (string) get_post_meta( $id, '_aw_seo_auto', true ), true );
			if ( ( $auto[ $key ] ?? null ) === $typed ) {
				return ''; // a hub's stored copy of its own page-1 title (titles.php): built live, not typed
			}
		}
		return $typed;
	}
	if ( is_category() || is_tag() || is_tax() ) {
		$typed = trim( (string) get_term_meta( $id, 'rank_math_' . $key, true ) );
		$auto  = (array) json_decode( (string) get_term_meta( $id, '_aw_seo_auto', true ), true );
		return ( '' !== $typed && ( $auto[ $key ] ?? null ) === $typed ) ? '' : $typed; // a category's stored copy (programmatic.php): built live, not typed
	}
	return '';
}

/**
 * Whether the current view is a list (a section hub Page, a Directory place or type page, an archive) rather than one
 * item. Items keep their title and description in Rank Math's own fields; lists are built here.
 *
 * @return bool
 */
function aw_seo_is_list_view() {
	return ! is_singular() || ( function_exists( 'aw_hub_type' ) && aw_hub_type() );
}

/** An item hidden from search engines (Rank Math's robots field). */
function aw_is_noindex_item( $id ) {
	return in_array( 'noindex', (array) get_post_meta( $id, 'rank_math_robots', true ), true );
}

/* ---------- Naming rules (SEO audits, 10 Oct 2026) ---------- */

/** "{Text} – Animal Writes". */
function aw_seo_branded( $text ) {
	return $text . ' – ' . get_bloginfo( 'name' );
}

/** Words a cut title must not end on ("… rescued in", "… rabies prevention &"). */
const AW_SEO_DANGLING = '&|\+|and|or|but|nor|of|the|a|an|to|in|on|at|by|for|with|from|into|onto|over|under|after|before|amid|as|via|per|than|that|which|who|whose|its|their|his|her|is|are|was|were|be|has|have|had|will|can|against|between|near|about|across|during|without|within|vs|v|every|each|all|some|any|this|these|those|our|your|my|what|how|why|when|where|whether|if|not|also|so|very|more|most|such|both|says|said';

/**
 * A title cut to at most $max characters at a word boundary that leaves a whole phrase: no dangling connector
 * ("and", "of", "to", "&" …), no trailing comma, colon or dash, no half bracket.
 */
function aw_seo_cut_title( $text, $max = 60 ) {
	$cut  = aw_seo_cut( $text, $max, '' );
	$full = trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
	if ( $cut === $full ) {
		return $cut;
	}
	do {
		$before = $cut;
		$cut    = preg_replace( '/(?:\s+(?:' . AW_SEO_DANGLING . ')|[\s,;:|\/\x{2013}\x{2014}-]+)$/iu', '', $cut );
	} while ( $cut !== $before && mb_strlen( $cut ) > $max * 0.5 );
	if ( substr_count( $cut, '(' ) > substr_count( $cut, ')' ) ) {
		$cut = preg_replace( '/[\s,;:\x{2013}\x{2014}-]+$/u', '', mb_substr( $cut, 0, mb_strrpos( $cut, '(' ) ) );
	}
	foreach ( array( '“' => '”', '‘' => '’' ) as $open => $close ) { // no half quote: an opening mark after the last closing one
		$o = mb_strrpos( $cut, $open );
		$c = mb_strrpos( $cut, $close );
		if ( false !== $o && ( false === $c || $o > $c ) ) {
			$cut = trim( mb_substr( $cut, 0, $o ) . mb_substr( $cut, $o + 1 ) );
		}
	}
	if ( 1 === substr_count( $cut, '"' ) % 2 ) {
		$o   = mb_strrpos( $cut, '"' );
		$cut = trim( mb_substr( $cut, 0, $o ) . mb_substr( $cut, $o + 1 ) );
	}
	return $cut;
}

/**
 * One rule for news, Library and Resources headlines, kept within 60 characters: "{Headline} – Animal Writes" when
 * that fits, the headline alone when only it fits, else the headline cut at a whole phrase (aw_seo_cut_title).
 */
function aw_seo_headline_title( $title ) {
	$t = aw_seo_plain( $title );
	if ( mb_strlen( aw_seo_branded( $t ) ) <= 60 ) {
		return aw_seo_branded( $t );
	}
	return mb_strlen( $t ) <= 60 ? $t : aw_seo_cut_title( $t, 60 );
}

/** The first of $titles within $max characters (the last one, cut at a whole phrase, when none fits). */
function aw_seo_fit( array $titles, $max = 60 ) {
	foreach ( $titles as $t ) {
		if ( mb_strlen( $t ) <= $max ) {
			return $t;
		}
	}
	return aw_seo_cut_title( (string) end( $titles ), $max );
}

/** "{Name}, {District}" or "{Name}, {State}" (no trailing comma; the place is not repeated when the name has it). */
function aw_seo_listing_name( $id ) {
	$name = preg_replace( '/[\s,;:\x{2013}\x{2014}-]+$/u', '', aw_seo_plain( get_post_field( 'post_title', $id ) ) );
	list( $state, $district ) = function_exists( 'aw_item_place_names' ) ? aw_item_place_names( $id ) : array( '', '' );
	$where = $district ? $district : $state;
	return ( '' !== $where && false === mb_stripos( $name, $where ) ) ? $name . ', ' . $where : $name;
}

/** Fixed titles of the section hubs (post type => title). */
const AW_HUB_TITLES = array(
	'aw_news'        => 'Animal Welfare News from India – Animal Writes',
	'aw_legal'       => 'Animal Law, Court Orders & Rules in India – Animal Writes',
	'post'           => 'Animal Welfare Guides & Research in India – Animal Writes',
	'aw_listing'     => 'Animal Welfare NGOs in India: Directory of 5,000+ Groups',
	'aw_opportunity' => 'Animal Welfare Volunteer, Internship & Jobs in India',
	'aw_press'       => 'Animal Writes in the News',
	'aw_campaign'    => 'Animal Welfare Campaigns in India – Animal Writes',
);

/** Fixed descriptions of the section hubs that are not built from their intro. */
const AW_HUB_DESCRIPTIONS = array(
	'aw_opportunity' => 'Volunteer, foster, intern or find a job with animal welfare organisations across India. Each opportunity lists its role, location and how to apply.',
	'aw_press'       => 'Press coverage of Animal Writes and its citizen letter campaign for community animals across India.',
	'aw_listing'     => 'Find %s animal welfare NGOs, shelters, rescuers, Gaushalas and government vets in every state and district of India, with phone, address and services.',
);

/** Descriptions of the content types' own archives (post type => description). */
const AW_ARCHIVE_DESCRIPTIONS = array(
	'aw_news'        => 'Animal-welfare news from across India, summarised with a link to every source.',
	'aw_legal'       => 'Court orders, notifications and rules that affect animals in India, explained in plain words.',
	'post'           => 'Practical guides: what to do for an injured animal, how to report cruelty, Animal Birth Control and more.',
	'aw_campaign'    => 'Citizen campaigns for animals in India, starting with Animal Writes 1.0.',
	'aw_opportunity' => 'Volunteer, foster, internship and job opportunities in animal welfare in India.',
	'aw_listing'     => 'Animal welfare organisations, Gaushalas and government vets across India.',
);

/** Plural of a listing type for a sentence: "Gaushalas" (always capitalised, owner 15 Oct 2026), "ABC centres", "veterinary dispensaries". */
function aw_seo_type_plural( $slug ) {
	$fixed = array(
		'veterinary'             => 'veterinary hospitals and dispensaries',
		'veterinary-office'      => 'government veterinary offices',
		'mobile-veterinary-unit' => 'mobile veterinary units',
		'wildlife-rescue'        => 'wildlife rescue groups',
		'emergency-rescue'       => 'emergency rescue groups',
		'legal-aid'              => 'legal aid groups',
		'boarding-and-foster'    => 'boarding and foster homes',
	);
	if ( isset( $fixed[ $slug ] ) ) {
		return $fixed[ $slug ];
	}
	$name  = trim( preg_replace( '/\s*\(.*?\)\s*/', ' ', aw_listing_type_name( $slug ) ) );
	$words = array_map( fn( $w ) => preg_match( '/^[A-Z]{2,}$/', $w ) || 0 === stripos( $w, 'gaushala' ) ? ucfirst( $w ) : mb_strtolower( $w ), explode( ' ', $name ) );
	$last  = (string) array_pop( $words );
	if ( preg_match( '/[^aeiou]y$/', $last ) ) {
		$last = substr( $last, 0, -1 ) . 'ies';
	} elseif ( preg_match( '/(s|x|ch|sh)$/', $last ) ) {
		$last .= 'es';
	} elseif ( '' !== $last ) {
		$last .= 's';
	}
	return trim( implode( ' ', $words ) . ' ' . $last );
}

/** The categories with the most published items of a type, as lower-case names (cached for 12 hours). */
function aw_seo_top_categories( $type ) {
	$key = 'aw_seo_categories_' . $type;
	$out = get_transient( $key );
	if ( false === $out ) {
		global $wpdb;
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT t.name FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id AND tt.taxonomy = %s JOIN {$wpdb->term_relationships} r ON r.term_taxonomy_id = tt.term_taxonomy_id JOIN {$wpdb->posts} p ON p.ID = r.object_id AND p.post_type = %s AND p.post_status = 'publish' GROUP BY t.term_id ORDER BY COUNT(*) DESC LIMIT 4", aw_seo_category_tax(), $type ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$out   = array_map( fn( $n ) => implode( ' ', array_map( fn( $w ) => preg_match( '/^[A-Z]{2,}$/', $w ) || 0 === stripos( $w, 'gaushala' ) ? ucfirst( $w ) : mb_strtolower( $w ), explode( ' ', html_entity_decode( $n, ENT_QUOTES, 'UTF-8' ) ) ) ), (array) $names );
		set_transient( $key, $out, 12 * HOUR_IN_SECONDS );
	}
	return (array) $out;
}

/**
 * Hub description built from its own intro (the hub Page's excerpt, as shown under its H1) and the list's facts:
 * "{intro} {N stories} on {category}, {category} and {category}." up to 155 characters.
 */
function aw_seo_hub_intro_description( $type ) {
	$page  = get_queried_object();
	$intro = aw_seo_plain( ( $page instanceof WP_Post && '' !== trim( $page->post_excerpt ) ) ? $page->post_excerpt : aw_hub_intro( $type ) );
	$count = 'post' === $type ? aw_seo_resources_count_label() : ''; // guides and research summaries counted apart
	$count = '' !== $count ? $count : ( function_exists( 'aw_hub_count_label' ) ? aw_hub_count_label( $type ) : '' );
	if ( '' === $count ) {
		return $intro;
	}
	$cats = array_values( array_filter( aw_seo_top_categories( $type ), fn( $t ) => false === strpos( $t, ' and ' ) ) ); // "laws and rules" would blur the list
	$best   = $intro . ' ' . $count . '.';
	for ( $n = 1; $n <= count( $cats ); $n++ ) {
		$list = array_slice( $cats, 0, $n );
		$last = array_pop( $list );
		$line = $intro . ' ' . $count . ' on ' . ( $list ? implode( ', ', $list ) . ' and ' : '' ) . $last . '.';
		if ( mb_strlen( $line ) > 155 ) {
			break;
		}
		$best = $line;
	}
	return $best;
}

/** AWBI-recognised listing without a description of its own: one line of its facts ('' when it does not apply). */
function aw_seo_awbi_description( $id, $desc ) {
	$code = trim( (string) get_post_meta( $id, 'aw_awbi_code', true ) );
	if ( '' === $code || has_excerpt( $id ) || ( '' !== trim( (string) $desc ) && ! preg_match( '/^Recognised by the Animal Welfare Board of India/i', trim( wp_strip_all_tags( (string) $desc ) ) ) ) ) {
		return '';
	}
	$types = function_exists( 'aw_listing_types' ) ? aw_listing_types( $id ) : array();
	if ( $types && count( $types ) > 1 ) { // the specific type (gaushala) before the general one
		usort( $types, fn( $a, $b ) => (int) ( 'animal-welfare-organisation' === $a->slug ) - (int) ( 'animal-welfare-organisation' === $b->slug ) );
	}
	$type  = $types ? implode( ' ', array_map( fn( $w ) => preg_match( '/^[A-Z]{2,}$/', $w ) || 0 === stripos( $w, 'gaushala' ) ? ucfirst( $w ) : mb_strtolower( $w ), explode( ' ', trim( preg_replace( '/\s*\(.*?\)/', '', $types[0]->name ) ) ) ) ) : 'organisation';
	list( $state, $district ) = aw_item_place_names( $id );
	$where = implode( ', ', array_filter( array( $district, $state ) ) );
	$name  = preg_replace( '/[\s,;:]+$/u', '', aw_seo_plain( get_post_field( 'post_title', $id ) ) );
	$line  = sprintf( '%1$s is an AWBI-recognised %2$s%3$s (AWBI code %4$s).', $name, $type, $where ? ' in ' . $where : '', $code );
	return mb_strlen( $line ) <= 120 ? $line . ' Address, contact and how to help.' : $line; // whole sentences only
}

/** "{Listing type} in {District}, {State}. {Services}. Contact details, location and how to help." ('' without type or place). */
function aw_listing_fact_description( $id ) {
	$types = function_exists( 'aw_listing_types' ) ? aw_listing_types( $id ) : array();
	$type  = $types ? $types[0]->name : '';
	list( $state, $district ) = function_exists( 'aw_item_place_names' ) ? aw_item_place_names( $id ) : array( '', '' );
	$where = implode( ', ', array_filter( array( $district, $state ) ) );
	if ( '' === $type && '' === $where ) {
		return '';
	}
	$lead = $type ? $type . ( $where ? ' in ' . $where : '' ) : 'In ' . $where;
	$svc  = function_exists( 'aw_listing_service_names' ) ? aw_listing_service_names( $id ) : array();
	$svc  = $svc ? implode( ', ', array_slice( $svc, 0, 4 ) ) : '';
	return $lead . '. ' . ( $svc ? ucfirst( $svc ) . '. ' : '' ) . 'Contact details, location and how to help.';
}

/* ---------- Share images (og:image and the article schema use the same one) ---------- */

/**
 * [id, url, width, height] of an attachment at share size, or null.
 *
 * @param int    $att  Attachment ID.
 * @param string $size Image size.
 * @return array|null
 */
function aw_seo_attachment( $att, $size = 'large' ) {
	$src = $att ? wp_get_attachment_image_src( (int) $att, $size ) : false;
	if ( ! $src || empty( $src[0] ) ) {
		return null;
	}
	if ( empty( $src[1] ) || empty( $src[2] ) ) {
		$meta   = wp_get_attachment_metadata( (int) $att );
		$src[1] = (int) ( $meta['width'] ?? 0 );
		$src[2] = (int) ( $meta['height'] ?? 0 );
	}
	return array( 'id' => (int) $att, 'url' => (string) $src[0], 'width' => (int) $src[1], 'height' => (int) $src[2] );
}

/** The site's default share image (Settings), as aw_seo_attachment(). */
function aw_seo_default_image() {
	return aw_seo_attachment( (int) get_option( 'aw_default_share_image' ), 'full' ); // made at share size (1200×630)
}

/** Post types whose share image is the publisher's own image, linked (News, Library, research, Opportunities). */
const AW_SEO_SOURCE_IMAGE_TYPES = array( 'aw_news', 'aw_legal', 'post', 'aw_opportunity' );

/**
 * The item's own image (owner, 15 Oct 2026: ingested items use the publisher's image, linked; never a category photo
 * in place of the item's own):
 *  1. the share image chosen in Rank Math;
 *  2. News, Library, research and Opportunities: the publisher's image (aw_source_image, checked in cc0-images.php;
 *     read from the source page's og:image when ingest stored none);
 *  3. the featured image (a guide's photo, a listing's logo, an ingested item's own CC0 photo);
 *  4. Library: the hosted PDF's first page;
 *  5. Pages: the first picture in the page's own content (the Volunteer band photo, for example).
 * Null when the item has none (the site share image is used then).
 *
 * @param int $id Post ID.
 * @return array|null [id, url, width, height]; id 0 for a linked publisher image.
 */
function aw_seo_item_image( $id ) {
	$img = aw_seo_attachment( (int) get_post_meta( $id, 'rank_math_facebook_image_id', true ) );
	if ( $img ) {
		return $img;
	}
	$type = get_post_type( $id );
	if ( in_array( $type, AW_SEO_SOURCE_IMAGE_TYPES, true ) && function_exists( 'aw_publisher_image' ) ) {
		$url = aw_publisher_image( $id );
		if ( '' !== $url ) {
			return array( 'id' => 0, 'url' => $url, 'width' => (int) get_post_meta( $id, 'aw_source_image_w', true ), 'height' => (int) get_post_meta( $id, 'aw_source_image_h', true ) );
		}
	}
	$cands = array( (int) get_post_thumbnail_id( $id ) );
	if ( 'aw_legal' === $type ) {
		$cands[] = (int) get_post_meta( $id, 'aw_file_id', true );
		$cands[] = (int) get_post_meta( $id, 'aw_doc_preview', true );
	}
	if ( 'page' === $type && preg_match( '/<!-- wp:(?:image|cover|media-text) \{[^}]*?"(?:id|mediaId)":(\d+)/', (string) get_post_field( 'post_content', $id ), $m ) ) {
		$cands[] = (int) $m[1];
	}
	foreach ( $cands as $att ) {
		$img = aw_seo_attachment( $att );
		if ( $img ) {
			return $img;
		}
	}
	return null;
}

/** The share image of a category page: its category photo (term meta aw_category_image), or null. */
function aw_seo_term_image() {
	if ( ! aw_seo_is_category_page() || ! function_exists( 'aw_category_image_id' ) ) {
		return null;
	}
	$att = (int) get_term_meta( get_queried_object_id(), 'rank_math_facebook_image_id', true );
	return aw_seo_attachment( $att ? $att : (int) aw_category_image_id( get_queried_object() ) );
}

/**
 * The share image of the current view (og:image and article schema): the item's own image when it is landscape (or of
 * unknown shape), a category page's category photo, else the site share image, else the site icon. Tags keep the site
 * share image (they have no picture of their own).
 *
 * @return array|null
 */
function aw_seo_image() {
	static $img = false;
	if ( false !== $img ) {
		return $img;
	}
	$img = is_singular() ? aw_seo_item_image( get_queried_object_id() ) : aw_seo_term_image();
	if ( $img && $img['height'] > $img['width'] && ! ( is_singular() && get_post_meta( get_queried_object_id(), 'rank_math_facebook_image_id', true ) ) ) {
		$img = null; // portrait: share cards are landscape, so the site image is shared instead (the page keeps its photo)
	}
	if ( ! $img ) {
		$img = aw_seo_default_image();
	}
	if ( ! $img ) {
		$img = aw_seo_attachment( (int) get_option( 'site_icon' ) );
	}
	return $img;
}

if ( ! function_exists( 'aw_seo_category_tax' ) ) {
	/**
	 * The taxonomy that holds the site's categories: WordPress's own category (since 15 Oct 2026).
	 *
	 * @return string
	 */
	function aw_seo_category_tax() {
		return 'category';
	}
}

/** Whether the current view is a category page. */
function aw_seo_is_category_page() {
	$tax = aw_seo_category_tax();
	return 'category' === $tax ? is_category() : is_tax( $tax );
}

/** The page that lists every category (/categories/), or ''. */
function aw_seo_categories_url() {
	foreach ( array( 'categories' ) as $slug ) {
		$page = get_page_by_path( $slug );
		if ( $page && 'publish' === $page->post_status ) {
			return get_permalink( $page );
		}
	}
	return '';
}
