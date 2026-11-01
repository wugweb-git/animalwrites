<?php
/**
 * Home page. Its sections and their order live in the theme template (templates/front-page.html), each a pattern; the
 * front page's own content is not shown. Its FAQ box still holds the home page's questions (item-faq block, last).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The FAQ section prints nothing when the home page has too few questions for an FAQ (front page only; the class itself).
add_filter(
	'render_block_core/group',
	function ( $html, $block ) {
		if ( ! is_front_page() || ! in_array( 'aw-ed-faq', preg_split( '/\s+/', (string) ( $block['attrs']['className'] ?? '' ) ), true ) || false !== strpos( $html, 'kit-faq' ) ) {
			return $html;
		}
		return '';
	},
	10,
	2
);

// Home sections named by their own H2 (aria-labelledby on the <section>): the core Group block has no such attribute.
// The class alone decides: a shortcode's H2 may only be printed after the blocks are rendered.
const AW_HOME_SECTION_HEADS = array(
	'aw-ed-help' => 'aw-help-h', // Need help now? (pattern help-now, the H2's anchor)
	'aw-ed-find' => 'aw-find-h', // Find help near you: the H2 is printed by [aw_find_help compact="1"] (sections.php)
);
add_filter(
	'render_block_core/group',
	function ( $html, $block ) {
		$cls = preg_split( '/\s+/', (string) ( $block['attrs']['className'] ?? '' ) );
		foreach ( AW_HOME_SECTION_HEADS as $class => $h ) {
			if ( in_array( $class, $cls, true ) && ! preg_match( '/^\s*<section\b[^>]*aria-labelledby=/', $html ) ) {
				return (string) preg_replace( '/^(\s*<section\b)/', '$1 aria-labelledby="' . $h . '"', $html, 1 );
			}
		}
		return $html;
	},
	20,
	2
);

/* ---------- Hero ---------- */

/** The campaign the home hero shows (the draft lab animal rules, 2026). */
const AW_HOME_CAMPAIGN = 13848;

/**
 * Whether the home hero is the campaign's: whenever it is published, open or closed (no automatic
 * switch when it closes). Its own state is read, never changed.
 */
function aw_home_hero_is_campaign() {
	static $on = null;
	if ( null === $on ) {
		$on = function_exists( 'aw_camp_open_ts' ) && 'publish' === get_post_status( AW_HOME_CAMPAIGN );
	}
	return $on;
}

/**
 * The artwork and colours the earlier home band used ([aw_campaign_band] in the front page's content, which is kept but
 * not shown): art, art_phone, theme. Empty when it is not there.
 *
 * @return array
 */
function aw_home_band_atts() {
	$content = (string) get_post_field( 'post_content', (int) get_option( 'page_on_front' ) );
	if ( ! preg_match( '/\[aw_campaign_band(\s[^\]]*)\]/', $content, $m ) ) {
		return array();
	}
	$a = shortcode_parse_atts( trim( $m[1] ) );
	return is_array( $a ) ? $a : array();
}

/**
 * Home: hero block. While campaign 13848 is published (open or closed): its hero (the campaign band's design and
 * colours, the page's H1, the countdown and the two buttons); after it closes the countdown shows what it shows, nothing
 * is added. Only if the campaign is not published: the Hero pattern (animal-writes/hero). The page cache keeps the hero
 * at most until the next midnight in India, so the day count moves (aw_camp_cache_until, campaign-tools.php).
 *
 * @return string
 */
function aw_block_home_hero() {
	if ( ! aw_home_hero_is_campaign() ) {
		return do_blocks( '<!-- wp:pattern {"slug":"animal-writes/hero"} /-->' );
	}
	$id    = AW_HOME_CAMPAIGN;
	$url   = (string) get_permalink( $id );
	$band  = aw_home_band_atts();
	$theme = sanitize_html_class( (string) ( $band['theme'] ?? 'lab-rules' ) );
	$day   = (string) get_post_meta( $id, 'aw_campaign_close', true );
	$day   = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) ? $day : '';
	$open  = aw_camp_open_ts( $id );
	aw_camp_cache_until( $id );

	// The band's own artwork only (no featured image: it is not drawn for the band). The phone picture only when it is an image with sizes.
	$art = (int) ( $band['art'] ?? 0 );
	$pic = '';
	if ( $art && wp_attachment_is_image( $art ) ) {
		$phone  = (int) ( $band['art_phone'] ?? 0 );
		$srcset = $phone && wp_attachment_is_image( $phone ) ? (string) wp_get_attachment_image_srcset( $phone, 'full' ) : '';
		$meta   = $srcset ? wp_get_attachment_metadata( $phone ) : array();
		$pic    = '<picture class="aw-camp-band-art">'
			. ( $srcset ? '<source media="(max-width: 767px)" srcset="' . esc_attr( $srcset ) . '" sizes="100vw" width="' . (int) ( $meta['width'] ?? 0 ) . '" height="' . (int) ( $meta['height'] ?? 0 ) . '">' : '' )
			. wp_get_attachment_image( $art, 'full', false, array( 'alt' => '', 'sizes' => '100vw', 'loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async' ) )
			. '</picture>';
	}

	$data = ' data-campaign-slug="' . esc_attr( (string) get_post_field( 'post_name', $id ) ) . '" data-campaign-title="' . esc_attr( get_the_title( $id ) ) . '"'; // campaign_action promo_click (aw-analytics.js)
	$out  = '<section class="alignfull aw-camp aw-camp-band aw-ed-hero aw-ed-hero--campaign' . ( $pic ? ' has-art' : '' ) . ( $theme ? ' aw-camp--' . $theme : '' ) . '" aria-labelledby="aw-hero-t"' . $data . '><div class="aw-camp-band-inner"><div class="aw-camp-band-text">';
	$out .= '<p class="aw-camp-band-pill">Draft rules G.S.R. 864(E) · objections close 5 Nov 2026</p>';
	$out .= '<h1 id="aw-hero-t" class="aw-camp-band-title">Object to the draft lab animal rules</h1>';
	$out .= '<p>Labs could approve most animal experiments themselves. Your objection counts until 5 November.</p>';
	$out .= $day ? do_shortcode( '[aw_countdown date="' . $day . '" label="Last day"]' ) : ''; // $day is Y-m-d (checked above)
	if ( $open && $day ) {
		$out .= '<p class="aw-camp-band-range kit-meta-row"><span class="kit-meta-item">Opened ' . aw_date( $open ) . '</span><span class="kit-meta-item">Last day ' . aw_date( $day ) . '</span></p>'; // aw_date() escapes
	}
	$out .= '<p class="aw-camp-band-actions"><a class="wp-element-button" data-track="home_campaign_click" href="' . esc_url( $url . '#send' ) . '">Send your objection</a> <a class="wp-element-button aw-camp-band-ghost" data-track="home_campaign_click" href="' . esc_url( $url . '#rules' ) . '">Read what the rules say</a></p>';
	$out .= '<p class="aw-ed-hero-help">Need help for an animal now? <a href="#need-help">Find help near you</a></p>';
	// The artwork after the text, as in the band: placed first it moved down after the first paint (layout shift).
	return $out . '</div></div>' . $pic . '</section>';
}

add_filter(
	'aw_hub_blocks',
	function ( $blocks ) {
		$blocks['home-hero'] = array( 'Home: hero', 'aw_block_home_hero', array() );
		return $blocks;
	}
);

/* ---------- AW Content ---------- */

/** Photograph for the AW Content card: the community dogs letter drive (aw1-im4), not used elsewhere on Home. */
const AW_CONTENT_PHOTO = 20;

/**
 * Home: AW Content block (pattern animal-writes/aw-content). A light card: the photograph on top, H2, one line, two
 * buttons (no destination yet, #) and the trust line, whose count is the live organisation count to the nearest hundred.
 *
 * @return string
 */
function aw_block_home_content() {
	$n   = function_exists( 'aw_listing_public_count' ) ? aw_listing_public_count() : 0;
	$n   = $n >= 100 ? number_format_i18n( round( $n / 100 ) * 100 ) . '+' : ( $n ? number_format_i18n( $n ) : '' ); // 6,796 shows 6,800+
	$img = wp_attachment_is_image( AW_CONTENT_PHOTO ) ? wp_get_attachment_image( AW_CONTENT_PHOTO, 'large', false, array( 'class' => 'aw-content-img', 'alt' => '', 'sizes' => '(min-width: 768px) 50vw, 100vw', 'loading' => 'lazy', 'decoding' => 'async' ) ) : '';

	$out  = '<section class="aw-content" aria-labelledby="aw-content-t">' . $img . '<div class="aw-content-body">';
	$out .= '<h2 id="aw-content-t">Animal welfare information and help across India</h2>';
	$out .= '<p class="aw-content-lead">Directory of organisations &amp; vets · Practical guides · Laws &amp; news · Ways to act</p>';
	$out .= '<p class="aw-content-actions"><a class="kit-btn kit-btn--brand kit-btn--ico-map-pin" href="#">Find help near you</a><a class="kit-btn kit-btn--secondary" href="#">Claim or update your organisation</a></p>';
	$out .= '<p class="aw-content-trust">' . ( $n ? esc_html( $n ) . ' organisations · ' : '' ) . 'Free to search and claim · Source-linked</p>';
	return $out . '</div></section>';
}

add_filter(
	'aw_hub_blocks',
	function ( $blocks ) {
		$blocks['home-content'] = array( 'Home: AW Content', 'aw_block_home_content', array() );
		return $blocks;
	}
);

/* ---------- Guides and From the Library ---------- */

/** Query Loop IDs of the home sections that pick their items (patterns learn.php and animal-law.php). */
const AW_HOME_GUIDES_QUERY  = 44;
const AW_HOME_LIBRARY_QUERY = 43;

/** Library types that can lead the home Library section: the law itself and what the courts ordered. */
const AW_HOME_LIBRARY_LEAD = array( 'act', 'rule', 'order', 'judgment' );

/**
 * Whether a guide is a state "report cruelty" guide: /resources/animal-cruelty-complaint-{state}/, {state} one of the
 * site's state slugs (places.php).
 *
 * @param int $id Post ID.
 * @return bool
 */
function aw_home_is_state_guide( $id ) {
	static $re = null;
	if ( null === $re ) {
		$slugs = array();
		foreach ( function_exists( 'aw_place_states' ) ? aw_place_states() : array() as $st ) { // a state's slug ("chandigarh-ut" too, as "chandigarh") and its name
			$slugs[] = preg_replace( '/-ut$/', '', (string) $st->slug );
			$slugs[] = sanitize_title( (string) $st->name );
		}
		$slugs = array_unique( array_filter( $slugs ) );
		$re    = $slugs ? '/^animal-cruelty-complaint-(' . implode( '|', array_map( fn( $s ) => preg_quote( (string) $s, '/' ), $slugs ) ) . ')$/' : '';
	}
	return $re && preg_match( $re, (string) get_post_field( 'post_name', $id ) );
}

/**
 * Guides on Home: the three newest guides, with at most one state "report cruelty" guide, so one kind does not fill
 * the row. When the first candidates do not give three, more are read (up to 48 in all).
 *
 * @param array $q The Query Loop's query vars.
 * @return int[]
 */
function aw_home_guide_ids( $q ) {
	$out   = array();
	$state = false;
	for ( $page = 1; $page <= 4 && count( $out ) < 3; $page++ ) {
		$ids = array_map( 'intval', get_posts( array_merge( $q, array( 'posts_per_page' => 12, 'paged' => 1, 'offset' => ( $page - 1 ) * 12, 'fields' => 'ids', 'no_found_rows' => true ) ) ) );
		if ( ! $ids ) {
			break;
		}
		_prime_post_caches( $ids, true, false );
		foreach ( $ids as $id ) {
			$is_state = aw_home_is_state_guide( $id );
			if ( $is_state && $state ) {
				continue;
			}
			$state = $state || $is_state;
			$out[] = $id;
			if ( 3 === count( $out ) ) {
				break;
			}
		}
		if ( count( $ids ) < 12 ) {
			break;
		}
	}
	return $out;
}

/**
 * From the Library on Home: the newest Act, rule, order or judgment leads, then the newest other items (the list's
 * size less one), an official release after the rest (newest first otherwise).
 *
 * @param array $q The Query Loop's query vars.
 * @return int[]
 */
function aw_home_library_ids( $q ) {
	$ids = array_map( 'intval', get_posts( array_merge( $q, array( 'posts_per_page' => 20, 'paged' => 1, 'offset' => 0, 'fields' => 'ids', 'no_found_rows' => true ) ) ) );
	if ( ! $ids ) {
		return array();
	}
	_prime_post_caches( $ids, true, false );
	$kind = fn( $id ) => function_exists( 'aw_legal_kinds' ) ? wp_list_pluck( aw_legal_kinds( $id ), 'slug' ) : array();
	$lead = 0;
	foreach ( $ids as $id ) {
		if ( array_intersect( $kind( $id ), AW_HOME_LIBRARY_LEAD ) ) {
			$lead = $id;
			break;
		}
	}
	$lead = $lead ? $lead : $ids[0];
	$rest = array_slice( array_values( array_diff( $ids, array( $lead ) ) ), 0, max( 0, (int) ( $q['posts_per_page'] ?? 4 ) - 1 ) );
	// Official releases last; stable on every PHP version (usort is stable only from PHP 8): the list index breaks ties.
	$keyed = array();
	foreach ( $rest as $i => $id ) {
		$keyed[] = array( (int) in_array( 'official-release', $kind( $id ), true ), $i, $id );
	}
	sort( $keyed );
	return array_merge( array( $lead ), array_column( $keyed, 2 ) );
}

add_filter(
	'query_loop_block_query_vars',
	function ( $q, $block ) {
		$qid = (int) ( $block->context['queryId'] ?? 0 );
		if ( ! is_front_page() || ! in_array( $qid, array( AW_HOME_GUIDES_QUERY, AW_HOME_LIBRARY_QUERY ), true ) ) {
			return $q;
		}
		$ids = AW_HOME_GUIDES_QUERY === $qid ? aw_home_guide_ids( $q ) : aw_home_library_ids( $q );
		if ( $ids ) {
			$q['post__in']       = $ids;
			$q['orderby']        = 'post__in';
			$q['posts_per_page'] = count( $ids );
			$q['offset']         = 0;
		}
		return $q;
	},
	20,
	2
);

// Block templates run shortcodes before patterns are expanded, so a Shortcode block inside a home pattern (Find help near
// you) would print as text. Run it when the block renders.
add_filter(
	'render_block_core/shortcode',
	function ( $html ) {
		return is_front_page() ? do_shortcode( shortcode_unautop( $html ) ) : $html;
	}
);
