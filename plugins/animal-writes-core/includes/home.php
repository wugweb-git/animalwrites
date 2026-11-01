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

// The FAQ section prints nothing when the home page has too few questions for an FAQ.
add_filter(
	'render_block_core/group',
	function ( $html, $block ) {
		if ( false === strpos( (string) ( $block['attrs']['className'] ?? '' ), 'aw-ed-faq' ) || false !== strpos( $html, 'kit-faq' ) ) {
			return $html;
		}
		return '';
	},
	10,
	2
);

/* ---------- Hero ---------- */

/** The campaign that takes the hero's place while it is open (the draft lab animal rules, 2026). */
const AW_HOME_CAMPAIGN = 13848;

/** Whether the home hero is the campaign's (published and open). Its own state is read, never changed. */
function aw_home_hero_is_campaign() {
	static $on = null;
	if ( null === $on ) {
		$on = function_exists( 'aw_camp_is_open' ) && 'publish' === get_post_status( AW_HOME_CAMPAIGN ) && aw_camp_is_open( AW_HOME_CAMPAIGN );
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
 * Home: hero block. While the campaign is open: its hero (the campaign band's design and colours, the page's H1, the
 * countdown and the two buttons). Otherwise the standard hero (pattern animal-writes/hero). The page cache keeps the
 * campaign hero only until the campaign closes, and the closing job purges the cache (campaign-tools.php).
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
	$open  = aw_camp_open_ts( $id );
	aw_camp_cache_until( $id );

	$art = (int) ( $band['art'] ?? 0 );
	$art = $art ? $art : (int) get_post_thumbnail_id( $id );
	$pic = '';
	if ( $art && wp_attachment_is_image( $art ) ) {
		$phone = (int) ( $band['art_phone'] ?? 0 );
		$meta  = $phone ? wp_get_attachment_metadata( $phone ) : array();
		$pic   = '<picture class="aw-camp-band-art">'
			. ( $phone ? '<source media="(max-width: 767px)" srcset="' . esc_attr( (string) wp_get_attachment_image_srcset( $phone, 'full' ) ) . '" sizes="100vw" width="' . (int) ( $meta['width'] ?? 0 ) . '" height="' . (int) ( $meta['height'] ?? 0 ) . '">' : '' )
			. wp_get_attachment_image( $art, 'full', false, array( 'alt' => '', 'sizes' => '100vw', 'loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async' ) )
			. '</picture>';
	}

	$data = ' data-campaign-slug="' . esc_attr( (string) get_post_field( 'post_name', $id ) ) . '" data-campaign-title="' . esc_attr( get_the_title( $id ) ) . '"'; // campaign_action promo_click (aw-analytics.js)
	$out  = '<section class="alignfull aw-camp aw-camp-band aw-ed-hero aw-ed-hero--campaign' . ( $pic ? ' has-art' : '' ) . ( $theme ? ' aw-camp--' . $theme : '' ) . '" aria-labelledby="aw-hero-t"' . $data . '><div class="aw-camp-band-inner"><div class="aw-camp-band-text">';
	$out .= '<p class="aw-camp-band-pill">Draft rules G.S.R. 864(E) · objections close 5 Nov 2026</p>';
	$out .= '<h1 id="aw-hero-t" class="aw-camp-band-title">Object to the draft lab animal rules</h1>';
	$out .= '<p>Labs could approve most animal experiments themselves. Your objection counts until 5 November.</p>';
	$out .= do_shortcode( '[aw_countdown date="' . esc_attr( $day ) . '" label="Last day"]' );
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

/* ---------- Guides and From the Library ---------- */

/** Query Loop IDs of the home sections that pick their items (patterns learn.php and animal-law.php). */
const AW_HOME_GUIDES_QUERY  = 44;
const AW_HOME_LIBRARY_QUERY = 43;

/** Library types that can lead the home Library section: the law itself and what the courts ordered. */
const AW_HOME_LIBRARY_LEAD = array( 'act', 'rule', 'order', 'judgment' );

/**
 * Guides on Home: the three newest guides, with at most one state "report cruelty" guide
 * (/resources/animal-cruelty-complaint-{state}/), so one kind does not fill the row.
 *
 * @param array $q The Query Loop's query vars.
 * @return int[]
 */
function aw_home_guide_ids( $q ) {
	$ids   = get_posts( array_merge( $q, array( 'posts_per_page' => 12, 'paged' => 1, 'offset' => 0, 'fields' => 'ids', 'no_found_rows' => true ) ) );
	$out   = array();
	$state = false;
	foreach ( $ids as $id ) {
		$is_state = 0 === strpos( (string) get_post_field( 'post_name', $id ), 'animal-cruelty-complaint-' );
		if ( $is_state && $state ) {
			continue;
		}
		$state = $state || $is_state;
		$out[] = (int) $id;
		if ( 3 === count( $out ) ) {
			break;
		}
	}
	return $out;
}

/**
 * From the Library on Home: the newest Act, rule, order or judgment leads, then the two newest other items, an official
 * release after the rest.
 *
 * @param array $q The Query Loop's query vars.
 * @return int[]
 */
function aw_home_library_ids( $q ) {
	$ids  = array_map( 'intval', get_posts( array_merge( $q, array( 'posts_per_page' => 20, 'paged' => 1, 'offset' => 0, 'fields' => 'ids', 'no_found_rows' => true ) ) ) );
	$kind = fn( $id ) => function_exists( 'aw_legal_kinds' ) ? wp_list_pluck( aw_legal_kinds( $id ), 'slug' ) : array();
	$lead = 0;
	foreach ( $ids as $id ) {
		if ( array_intersect( $kind( $id ), AW_HOME_LIBRARY_LEAD ) ) {
			$lead = $id;
			break;
		}
	}
	$lead = $lead ? $lead : (int) ( $ids[0] ?? 0 );
	$rest = array_slice( array_values( array_diff( $ids, array( $lead ) ) ), 0, 2 );
	usort( $rest, fn( $a, $b ) => (int) in_array( 'official-release', $kind( $a ), true ) <=> (int) in_array( 'official-release', $kind( $b ), true ) ); // stable (PHP 8): newest first otherwise
	return $lead ? array_merge( array( $lead ), $rest ) : array();
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
