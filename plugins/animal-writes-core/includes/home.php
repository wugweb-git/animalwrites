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
