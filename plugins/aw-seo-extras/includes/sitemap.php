<?php
/**
 * Rank Math sitemap (/sitemap_index.xml) additions:
 * - a "directory" sitemap (directory-sitemap.xml) of the Directory place pages (/directory/{state}/,
 *   /directory/{state}/{district}/) that have listings, and district × listing-type pages with listings. Places come
 *   from the LGD file (places.php); Rank Math knows nothing of these virtual pages.
 * - a "filtered" sitemap (filtered-sitemap.xml) of the indexable filtered hub lists (filtered-views.php), rebuilt
 *   once a day (event aw_seo_sitemap_daily), never on a request.
 * - both from one provider class (AW_Rank_Math_Sitemap); this module's switch turns both off.
 * - no post-type archive links (the sections are Pages, listed in the page sitemap).
 * - Rank Math's stored sitemaps are cleared after saves through the REST API and WP-CLI too.
 * - a Google News sitemap (news-sitemap.xml): News items from the last 48 hours, listed in the index and robots.txt.
 * - an HTML sitemap for people and crawlers: [aw_html_sitemap] on the /sitemap/ page (made once), linked from the footer.
 * Items kept out of the index by a rule of their state are left out of every sitemap by robots.php, whether this
 * module is on or not.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** District × listing-type pairs with enough listings to be indexed (no state × type pages exist). Stored counts, places.php. */
function aw_sitemap_directory_pairs() {
	$pairs = get_transient( 'aw_sitemap_dir_pairs' );
	if ( false !== $pairs ) {
		return $pairs;
	}
	$pairs = array();
	foreach ( aw_place_counts()['pairs'] as $p ) {
		$d = aw_place_district( $p[0] );
		if ( $d && ( $p[3] >= AW_THIN_DISTRICT_TYPE || aw_directory_keep_indexed( aw_directory_url( $d->slug, $p[1] ) ) ) ) {
			$pairs[] = array( 'city' => $d->slug, 'type' => $p[1], 'modified' => $p[2], 'n' => $p[3] );
		}
	}
	usort( $pairs, fn( $x, $y ) => strcmp( $x['city'] . '/' . $x['type'], $y['city'] . '/' . $y['type'] ) );
	set_transient( 'aw_sitemap_dir_pairs', $pairs, 12 * HOUR_IN_SECONDS );
	return $pairs;
}

/** Directory place pages (states and districts with listings), then district × type pages. */
function aw_sitemap_directory_urls() {
	$urls = array();
	foreach ( aw_place_states() as $st ) {
		if ( aw_place_count( $st ) ) {
			$urls[] = array( 'loc' => aw_directory_url( $st->slug ) );
		}
		foreach ( aw_place_districts( $st->code ) as $d ) {
			if ( aw_place_count( $d ) ) {
				$urls[] = array( 'loc' => aw_directory_url( $d->slug ) );
			}
		}
	}
	foreach ( aw_sitemap_directory_pairs() as $r ) {
		$urls[] = array( 'loc' => aw_directory_url( $r['city'], $r['type'] ), 'lastmod' => gmdate( 'c', strtotime( $r['modified'] . ' UTC' ) ) );
	}
	return $urls;
}

/**
 * The indexable filtered hub lists (filtered-views.php) as the daily job below last stored them. Before its first run
 * they are built on the spot (and not stored).
 *
 * @return array List of ['loc' => URL].
 */
function aw_sitemap_filtered_urls() {
	if ( ! function_exists( 'aw_sitemap_filtered_build' ) ) {
		return array(); // the Filtered views module is off: no filtered lists
	}
	$urls = get_option( 'aw_sitemap_filtered' );
	return is_array( $urls ) ? $urls : aw_sitemap_filtered_build();
}

/** Daily: filtered-sitemap.xml is rebuilt (never on a request), and Rank Math's stored copy of it is cleared. */
function aw_sitemap_filtered_rebuild() {
	if ( ! function_exists( 'aw_sitemap_filtered_build' ) ) {
		return; // the Filtered views module is off
	}
	update_option( 'aw_sitemap_filtered', aw_sitemap_filtered_build(), false );
	if ( class_exists( '\RankMath\Sitemap\Cache_Watcher' ) ) {
		\RankMath\Sitemap\Cache_Watcher::invalidate( 'filtered' );
		add_action( 'shutdown', array( 'RankMath\Sitemap\Cache_Watcher', 'clear_queued' ) );
	}
}
add_action( 'aw_seo_sitemap_daily', 'aw_sitemap_filtered_rebuild' );
add_action(
	'admin_init',
	function () {
		if ( ! wp_next_scheduled( 'aw_seo_sitemap_daily' ) ) {
			wp_schedule_event( time() + 600, 'daily', 'aw_seo_sitemap_daily' );
		}
	}
);

/**
 * One page of a list of URLs as Rank Math's sitemap links.
 *
 * @param array $urls         List of ['loc' => URL, 'lastmod' => date (optional)].
 * @param int   $max_entries  Links per sitemap page.
 * @param int   $current_page Page, from 1.
 * @return array
 */
function aw_sitemap_page_links( array $urls, $max_entries, $current_page ) {
	$max_entries = max( 1, (int) $max_entries );
	$chunk       = array_slice( $urls, ( max( 1, (int) $current_page ) - 1 ) * $max_entries, $max_entries );
	return array_map( fn( $u ) => array_filter( array( 'loc' => $u['loc'], 'mod' => $u['lastmod'] ?? '' ) ), $chunk );
}

add_filter(
	'rank_math/sitemap/providers',
	function ( $providers ) {
		if ( ! interface_exists( '\RankMath\Sitemap\Providers\Provider' ) || ! class_exists( '\RankMath\Sitemap\Router' ) ) {
			return $providers;
		}
		if ( ! class_exists( 'AW_Rank_Math_Sitemap' ) ) {
			/** The site's own sitemaps: directory-sitemap.xml and filtered-sitemap.xml (each paged by Rank Math's entries per sitemap). */
			class AW_Rank_Math_Sitemap implements \RankMath\Sitemap\Providers\Provider {
				/** Sitemap type => function that lists its URLs. */
				const TYPES = array(
					'directory' => 'aw_sitemap_directory_urls',
					'filtered'  => 'aw_sitemap_filtered_urls',
				);

				public function handles_type( $type ) {
					return isset( self::TYPES[ $type ] );
				}

				public function get_index_links( $max_entries ) {
					$max_entries = max( 1, (int) $max_entries );
					$links       = array();
					foreach ( self::TYPES as $type => $source ) {
						$urls  = (array) call_user_func( $source );
						$pages = (int) ceil( count( $urls ) / $max_entries );
						for ( $i = 1; $i <= $pages; $i++ ) {
							$mods    = array_filter( wp_list_pluck( array_slice( $urls, ( $i - 1 ) * $max_entries, $max_entries ), 'lastmod' ) );
							$links[] = array_filter(
								array(
									'loc'     => \RankMath\Sitemap\Router::get_base_url( $type . '-sitemap' . ( $pages > 1 ? $i : '' ) . '.xml' ),
									'lastmod' => $mods ? max( $mods ) : '',
								)
							);
						}
					}
					return $links;
				}

				public function get_sitemap_links( $type, $max_entries, $current_page ) {
					return isset( self::TYPES[ $type ] ) ? aw_sitemap_page_links( (array) call_user_func( self::TYPES[ $type ] ), $max_entries, $current_page ) : array();
				}
			}
		}
		$providers[] = new AW_Rank_Math_Sitemap();
		return $providers;
	}
);

add_filter( 'rank_math/sitemap/post_type_archive_link', '__return_false' );

/*
 * Rank Math keeps each sitemap as a stored file and only clears it from wp-admin screens and cron (its Cache_Watcher is
 * started only there). A post saved through the REST API (the block editor, admin REST writes) or WP-CLI left its
 * type's sitemap as it was, so a new item stayed out until the next admin save (2 Nov 2026: Library item 23678). The
 * same clearing Rank Math does is queued here for those requests and runs at the end of the request.
 */
add_action(
	'save_post',
	function ( $post_id, $post ) {
		if ( is_admin() || wp_doing_cron() || ! class_exists( '\RankMath\Sitemap\Cache_Watcher' ) ) {
			return; // Rank Math's own watcher runs there.
		}
		if ( wp_is_post_revision( $post_id ) || 'auto-draft' === $post->post_status || '' !== (string) $post->post_password ) {
			return; // As Rank Math: no clearing for revisions, auto-drafts or password-protected posts.
		}
		\RankMath\Sitemap\Cache_Watcher::invalidate_post( $post_id );
		add_action( 'shutdown', array( 'RankMath\Sitemap\Cache_Watcher', 'clear_queued' ) ); // the same callback Rank Math adds; added once
	},
	10,
	2
);

/* ---------- Google News sitemap: /news-sitemap.xml (owner, 12 Nov 2026; Rank Math's own is Pro-only) ---------- */

/** Hours a News item stays in the Google News sitemap (Google reads the last two days only). */
const AW_NEWS_SITEMAP_HOURS = 48;

/**
 * News items from the last 48 hours, newest first: [url, title, date]. The date is the one the item's NewsArticle
 * node prints (schema.php, aw_article_nodes(): the day the story was reported when stored, else the publish date), so
 * the two never disagree. Read with $wpdb: front-end News queries are re-sorted by source date.
 *
 * @return array List of ['loc', 'title', 'date' (ISO 8601)].
 */
function aw_news_sitemap_items() {
	global $wpdb;
	$since = gmdate( 'Y-m-d H:i:s', time() - ( AW_NEWS_SITEMAP_HOURS + 24 ) * HOUR_IN_SECONDS ); // a day more: the source date can be later than the publish date
	$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'aw_news' AND post_status = 'publish' AND post_password = '' AND post_date_gmt >= %s ORDER BY post_date_gmt DESC LIMIT 1000", $since ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$cut   = time() - AW_NEWS_SITEMAP_HOURS * HOUR_IN_SECONDS;
	$out   = array();
	foreach ( array_map( 'intval', $ids ) as $id ) {
		if ( function_exists( 'kits_preview_only_ids' ) && in_array( $id, (array) kits_preview_only_ids(), true ) ) {
			continue; // preview-only items are never listed
		}
		$src  = (string) get_post_meta( $id, 'aw_source_date', true );
		$date = '' !== $src && function_exists( 'aw_schema_date' ) && aw_schema_date( $src ) ? aw_schema_date( $src ) : get_the_date( 'c', $id );
		$ts   = strtotime( (string) $date );
		if ( ! $ts || $ts < $cut || $ts > time() + HOUR_IN_SECONDS ) {
			continue;
		}
		$out[] = array( 'loc' => get_permalink( $id ), 'title' => aw_seo_plain( get_the_title( $id ) ), 'date' => (string) $date, 'ts' => $ts );
	}
	usort( $out, fn( $a, $b ) => $b['ts'] <=> $a['ts'] );
	return $out;
}

/** The Google News sitemap XML. */
function aw_news_sitemap_xml() {
	$x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . "\n";
	foreach ( aw_news_sitemap_items() as $r ) {
		$x .= "\t<url>\n\t\t<loc>" . esc_xml( $r['loc'] ) . "</loc>\n\t\t<news:news>\n\t\t\t<news:publication>\n\t\t\t\t<news:name>Animal Writes</news:name>\n\t\t\t\t<news:language>en</news:language>\n\t\t\t</news:publication>\n"
			. "\t\t\t<news:publication_date>" . esc_xml( $r['date'] ) . "</news:publication_date>\n\t\t\t<news:title>" . esc_xml( $r['title'] ) . "</news:title>\n\t\t</news:news>\n\t</url>\n";
	}
	return $x . '</urlset>' . "\n";
}

/** The sitemap's address. */
function aw_news_sitemap_url() {
	return home_url( '/news-sitemap.xml' );
}

// Rank Math's rule sends /news-sitemap.xml to WordPress as sitemap=news; it is answered here, before Rank Math (parse_query).
add_action(
	'parse_request',
	function ( $wp ) {
		if ( 'news' !== ( $wp->query_vars['sitemap'] ?? '' ) ) {
			return;
		}
		do_action( 'litespeed_control_set_ttl', 900 ); // LiteSpeed keeps it 15 minutes at most
		if ( ! headers_sent() ) {
			status_header( 200 );
			header( 'Content-Type: application/xml; charset=UTF-8' );
			header( 'X-Robots-Tag: noindex, follow' );
			header( 'Cache-Control: public, max-age=900' );
		}
		echo aw_news_sitemap_xml(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped per value in aw_news_sitemap_xml()
		exit;
	},
	0
);

// Listed in sitemap_index.xml (Rank Math's own filter for extra index entries, as its Local SEO sitemap does).
add_filter(
	'rank_math/sitemap/index',
	function ( $xml ) {
		$items = aw_news_sitemap_items();
		$mod   = $items ? gmdate( 'c', $items[0]['ts'] ) : '';
		return $xml . "\t<sitemap>\n\t\t<loc>" . esc_xml( aw_news_sitemap_url() ) . "</loc>\n" . ( $mod ? "\t\t<lastmod>" . esc_xml( $mod ) . "</lastmod>\n" : '' ) . "\t</sitemap>\n";
	}
);

// And in robots.txt, after the site's own Sitemap line.
add_filter(
	'robots_txt',
	function ( $txt ) {
		$line = 'Sitemap: ' . aw_news_sitemap_url();
		return false !== strpos( (string) $txt, $line ) ? $txt : rtrim( (string) $txt ) . "\n" . $line . "\n";
	},
	60
);

/* ---------- HTML sitemap: /sitemap/ (indexing work, owner 12 Nov 2026) ---------- */

/** The HTML sitemap page's address. */
const AW_HTML_SITEMAP_SLUG = 'sitemap';

/**
 * Links to every indexable section, item and Directory place, for people and crawlers: [aw_html_sitemap] on the
 * /sitemap/ page. Plain link lists and chips (kit.css), no new styles. Headings are the sections' own page titles and
 * the names of categories, states and districts. Left out: pages kept out of search (Rank Math's noindex), closed
 * opportunities, News items one by one (1,600+: their categories are linked) and listings one by one (their districts
 * are). Cached for 12 hours, worked out again when an item is saved.
 *
 * @return string HTML.
 */
function aw_html_sitemap() {
	$cached = get_transient( 'aw_html_sitemap' );
	if ( is_string( $cached ) && '' !== $cached ) {
		return $cached;
	}
	$list  = function ( array $links, $chips = false ) {
		if ( ! $links ) {
			return '';
		}
		$out = $chips ? '<ul class="kit-tags aw-term-chips">' : '<ul class="wp-block-list kit-links">';
		foreach ( $links as $l ) {
			$out .= '<li><a' . ( $chips ? ' class="kit-chip"' : '' ) . ' href="' . esc_url( $l[0] ) . '">' . esc_html( $l[1] ) . '</a></li>';
		}
		return $out . '</ul>';
	};
	$title = fn( $p ) => html_entity_decode( get_the_title( $p ), ENT_QUOTES );
	$noidx = function ( $id ) {
		$r = get_post_meta( (int) $id, 'rank_math_robots', true );
		return is_array( $r ) && in_array( 'noindex', $r, true );
	};
	$items = function ( $type ) use ( $title, $noidx ) {
		$ids = get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'orderby' => 'title', 'order' => 'ASC', 'suppress_filters' => true ) );
		$ids = array_values( array_diff( array_map( 'intval', $ids ), function_exists( 'aw_seo_preview_ids' ) ? array_map( 'intval', (array) aw_seo_preview_ids() ) : array() ) );
		$out = array();
		foreach ( $ids as $id ) {
			if ( $noidx( $id ) || ( 'aw_opportunity' === $type && function_exists( 'aw_retention_state' ) && aw_retention_state( get_post( $id ) ) ) ) {
				continue;
			}
			$out[] = array( get_permalink( $id ), $title( $id ) );
		}
		$key = fn( $l ) => mb_strtolower( preg_replace( '/^the\s+/i', '', $l[1] ) );
		usort( $out, fn( $a, $b ) => strcmp( $key( $a ), $key( $b ) ) );
		return $out;
	};
	$cats = function ( $type ) {
		$counts = function_exists( 'aw_seo_category_counts' ) ? aw_seo_category_counts() : array();
		$field  = 'post' === $type ? 'guide' : $type;
		$out    = array();
		foreach ( get_terms( array( 'taxonomy' => 'category', 'hide_empty' => true, 'orderby' => 'name' ) ) as $t ) {
			$n = (int) ( $counts[ $t->term_id ][ $field ] ?? 0 ) + ( 'post' === $type ? (int) ( $counts[ $t->term_id ]['research'] ?? 0 ) : 0 );
			if ( $n && 'uncategorized' !== $t->slug ) {
				$out[] = array( get_term_link( $t ), html_entity_decode( $t->name, ENT_QUOTES ) . ' (' . number_format_i18n( $n ) . ')' );
			}
		}
		return $out;
	};
	$head = function ( $id, $text, $url = '' ) {
		$h = $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a>' : esc_html( $text );
		return '<h2 class="aw-more-head has-x-large-font-size" id="aw-sitemap-' . esc_attr( $id ) . '">' . $h . '</h2>';
	};
	$hub_title = function ( $type ) use ( $title ) {
		$p = defined( 'AW_HUBS' ) && isset( AW_HUBS[ $type ] ) ? get_page_by_path( AW_HUBS[ $type ][0] ) : null;
		return $p ? array( get_permalink( $p ), $title( $p ) ) : array( '', '' );
	};

	$html = '';
	// The site's own pages (not the sections, which follow, nor pages kept out of search).
	$hubs  = defined( 'AW_HUBS' ) ? array_column( AW_HUBS, 0 ) : array();
	$skip  = array_merge( $hubs, array( 'home', AW_HTML_SITEMAP_SLUG, 'thank-you', 'search', 'claim', 'update' ) );
	$pages = array();
	foreach ( get_pages( array( 'post_status' => 'publish', 'sort_column' => 'post_title' ) ) as $p ) {
		if ( ! in_array( $p->post_name, $skip, true ) && ! $noidx( $p->ID ) ) {
			$pages[] = array( get_permalink( $p ), $title( $p ) );
		}
	}
	$home  = get_option( 'page_on_front' ) ? $title( (int) get_option( 'page_on_front' ) ) : get_bloginfo( 'name' );
	$html .= '<section class="aw-more-group" aria-labelledby="aw-sitemap-pages">' . $head( 'pages', $home, home_url( '/' ) ) . $list( $pages ) . '</section>';

	foreach ( array( 'aw_news', 'post', 'aw_legal', 'aw_campaign', 'aw_opportunity', 'aw_press' ) as $type ) {
		list( $url, $name ) = $hub_title( $type );
		if ( ! $url ) {
			continue;
		}
		$body = $list( $cats( $type ), true );
		if ( 'aw_legal' === $type && function_exists( 'aw_law_index_html' ) ) {
			$body .= aw_law_index_html();
		}
		if ( in_array( $type, array( 'post', 'aw_legal', 'aw_campaign', 'aw_opportunity' ), true ) ) {
			$body .= $list( $items( $type ) );
		}
		$html .= '<section class="aw-more-group" aria-labelledby="aw-sitemap-' . esc_attr( $type ) . '">' . $head( $type, $name, $url ) . $body . '</section>';
	}

	// Directory: every state, then its districts with listings.
	list( $url, $name ) = $hub_title( 'aw_listing' );
	if ( $url && function_exists( 'aw_seo_dir_children' ) ) {
		$body   = '';
		$states = aw_seo_dir_children( null );
		usort( $states, fn( $a, $b ) => strcmp( $a[0]->name, $b[0]->name ) );
		foreach ( $states as $s ) {
			$state = $s[0];
			$kids  = aw_seo_dir_children( $state );
			usort( $kids, fn( $a, $b ) => strcmp( $a[0]->name, $b[0]->name ) );
			$links = array();
			foreach ( $kids as $d ) {
				$links[] = array( aw_directory_url( $d[0]->slug ), $d[0]->name . ' (' . number_format_i18n( $d[1] ) . ')' );
			}
			$body .= '<h3 id="aw-sitemap-dir-' . esc_attr( $state->slug ) . '"><a href="' . esc_url( aw_directory_url( $state->slug ) ) . '">' . esc_html( $state->name . ' (' . number_format_i18n( $s[1] ) . ')' ) . '</a></h3>' . $list( $links, true );
		}
		$html .= '<section class="aw-more-group" aria-labelledby="aw-sitemap-aw_listing">' . $head( 'aw_listing', $name, $url ) . $body . '</section>';
	}

	set_transient( 'aw_html_sitemap', $html, 12 * HOUR_IN_SECONDS );
	return $html;
}
add_action( 'save_post', fn() => delete_transient( 'aw_html_sitemap' ) );

add_shortcode(
	'aw_html_sitemap',
	function () {
		if ( function_exists( 'aw_use_component_style' ) ) {
			aw_use_component_style( 'aw-single' ); // aw-more-group, aw-more-head
		}
		return aw_html_sitemap();
	}
);

// The /sitemap/ page, made once (published, with the shortcode) when it does not exist. Admin requests only, so a page
// view never writes; never made again once made, even if it is later moved to draft or deleted.
add_action(
	'admin_init',
	function () {
		if ( get_option( 'aw_html_sitemap_page' ) || ! current_user_can( 'publish_pages' ) ) {
			return;
		}
		$page = get_page_by_path( AW_HTML_SITEMAP_SLUG );
		$id   = $page ? (int) $page->ID : (int) wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Sitemap', 'aw-seo-extras' ),
				'post_name'    => AW_HTML_SITEMAP_SLUG,
				'post_content' => '<!-- wp:shortcode -->[aw_html_sitemap]<!-- /wp:shortcode -->',
			)
		);
		update_option( 'aw_html_sitemap_page', $id ?: -1, false );
	}
);
