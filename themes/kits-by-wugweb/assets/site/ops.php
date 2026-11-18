<?php
/**
 * Animal Writes' values for Kits Core's site operations (plugins/kits-core/includes/ops/, moved there from Animal Writes
 * Core on 19 Nov 2026): the pages Check site loads, the sections the 404 page lists, the CDN's never-cached pages and
 * longer-lived page types, the workers repository, and the LiteSpeed crawler window. Site configuration, not code: this
 * folder is the site's and never ships in a distributed theme. Loaded by site.php.
 *
 * @package kits-by-wugweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- Check site ---------- */

/**
 * The pages Check site loads: one of each kind this site has (label => path), from what exists now.
 *
 */
function kits_site_check_pages() {
	$urls = array(
		'Home'                => '/',
		'News'                => '/news/',
		'Library'             => '/library/',
		'Resources'           => '/resources/',
		'Opportunities'       => '/opportunities/',
		'Campaigns'           => '/campaigns/',
		'Directory'           => '/directory/',
		'Directory: a state'  => '/directory/kerala/',
		'Directory: district' => '/directory/kerala/ernakulam/',
		'Media & PR'          => '/press-coverage/',
		'Contact'             => '/contact/',
		'Volunteer'           => '/volunteer/',
		'Search'              => '/search/?q=dog',
		'Sitemap'             => ( class_exists( '\RankMath\Helper' ) && \RankMath\Helper::is_module_active( 'sitemap' ) ) ? '/sitemap_index.xml' : '/wp-sitemap.xml',
	);
	foreach ( array( 'aw_news' => 'A news item', 'aw_legal' => 'A Library item', 'post' => 'A guide', 'aw_opportunity' => 'An opportunity', 'aw_campaign' => 'A campaign', 'aw_listing' => 'An organisation' ) as $type => $label ) {
		$p = get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids' ) );
		if ( $p ) {
			$urls[ $label ] = wp_parse_url( get_permalink( $p[0] ), PHP_URL_PATH );
		}
	}
	$t = get_terms( array( 'taxonomy' => 'category', 'number' => 1, 'hide_empty' => true ) );
	if ( $t && ! is_wp_error( $t ) ) {
		$urls['A category'] = wp_parse_url( function_exists( 'kits_term_url' ) ? kits_term_url( $t[0] ) : get_term_link( $t[0] ), PHP_URL_PATH );
	}
	return $urls;
}
add_filter( 'kits_site_check_urls', 'kits_site_check_pages' );

/* ---------- CDN cache ---------- */

// Pages that never go in the CDN: the help-now and crisis guides (config.php).
add_filter( 'kits_cdn_excluded_ids', fn( $ids ) => array_merge( (array) $ids, defined( 'AW_CRISIS_GUIDES' ) ? AW_CRISIS_GUIDES : array() ) );
// Pages that change rarely get the longer lifetime: Directory listings, Library items and Resources.
add_filter( 'kits_cdn_long_lived_types', fn() => array( 'aw_listing', 'aw_legal', 'post' ) );

/* ---------- The 404 page ---------- */

// Sections whose missing items get the newest-items list (a trashed News or Library item answers 410).
add_filter(
	'kits_not_found_sections',
	fn() => array(
		'aw_news'        => array( 'label' => 'News', 'slug' => 'news', 'gone' => true ),
		'aw_legal'       => array( 'label' => 'Library', 'slug' => 'library', 'gone' => true ),
		'aw_opportunity' => array( 'label' => 'Opportunities', 'slug' => 'opportunities' ),
	)
);
add_filter(
	'kits_not_found_gone_lead',
	fn( $lead, $type ) => 'aw_news' === $type ? 'We took this story off Animal Writes. Here’s the latest from News instead.' : 'We took this item off Animal Writes. Here’s the latest from the Library instead.',
	10,
	2
);
add_action(
	'kits_not_found_section_styles',
	function ( $type ) {
		kits_use_component_style( 'aw-lists' ); // aw-hub-group, aw-hub-news, aw-card-meta
		if ( 'aw_news' === $type ) {
			kits_use_component_style( 'aw-hubs' ); // aw-hub-news
		}
	}
);
add_filter( 'kits_not_found_markup', fn( $m, $type ) => array( 'section' => 'aw-hub-group', 'list' => 'aw_news' === $type ? 'aw-hub-news' : '' ), 10, 2 );
add_filter(
	'kits_not_found_item_before',
	function ( $before, $p ) {
		if ( 'aw_news' === $p->post_type && function_exists( 'aw_news_meta_row' ) ) {
			return $before . aw_news_meta_row( $p->ID );
		}
		if ( 'aw_legal' === $p->post_type && function_exists( 'aw_legal_kind_tag' ) ) {
			$k = aw_legal_kind_tag( $p->ID );
			return $k ? $before . '<p class="kit-tags">' . $k . '</p>' : $before;
		}
		return $before;
	},
	10,
	2
);
add_filter( 'kits_not_found_links', fn() => array( '/directory/' => array( 'Directory', 'directory' ), '/news/' => array( 'News', 'news' ), '/resources/' => array( 'Resources', 'knowledge' ), '/library/' => array( 'Library', 'legal' ), '/opportunities/' => array( 'Opportunities', 'opportunities' ), '/contact/' => array( 'Contact', 'mail' ), '/' => array( 'Home', 'home' ) ) );

/* ---------- Scheduled tasks ---------- */

// The repository whose workflows "Run now" and the server's workers tick start (the token is AW_GITHUB_DISPATCH_TOKEN in wp-config.php).
add_filter( 'kits_dispatch_repo', fn() => 'wugweb-git/animal-writes-workers' );

/**
 * LiteSpeed cache warmer: only between 02:00 and 05:00 India time (owner, 17 Oct 2026). Outside that window its
 * scheduled run does nothing; the full sitemap is still warmed each week.
 */
function kits_site_litespeed_crawler_night_only() {
	if ( ! wp_doing_cron() ) {
		return;
	}
	$hour = (int) ( new DateTime( 'now', new DateTimeZone( 'Asia/Kolkata' ) ) )->format( 'G' );
	if ( $hour < 2 || $hour >= 5 ) {
		remove_all_actions( 'litespeed_task_crawler' );
	}
}
add_action( 'init', 'kits_site_litespeed_crawler_night_only', PHP_INT_MAX );

/* ---------- Dashboard ---------- */

// Our Activity box (cards and one table of the newest items) replaces WordPress's own.
add_filter( 'kits_dashboard_activity', '__return_true' );
