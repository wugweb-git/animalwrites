<?php
/**
 * Hub Pages (Phase 12 Part D): each section hub is a Page that the owner edits (breadcrumb, H1, intro, banner, body)
 * with its list as a synced pattern of core blocks (Query Loop + Post Template + pagination + empty state).
 *
 * - The post types have no archive (has_archive off); their single URLs are unchanged (/news/{slug}/,
 *   /directory/org/{slug}/ …). /news/, /library/ … are Pages; /news/page/2/ maps to the Page with paged=2.
 * - Each hub's Query Loop has a fixed queryId (AW_HUBS) and gets its items from aw_hub_query_vars(): the list filters
 *   (URL query vars), section rules (open opportunities, news of the last 12 months, Directory order and search) and
 *   the page number. Pagination links use the pretty /page/N/ form.
 * - Directory places (/directory/{state}/…) are LGD-routed (directory.php) to the Directory Page.
 * - Canonical: the Page URL (with /page/N/), without filters. Schema: CollectionPage + ItemList + BreadcrumbList.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hubs: post type => [page slug, queryId, items per page, pattern title].
 */
const AW_HUBS = array(
	'aw_news'        => array( 'news', 101, 13, 'News list' ),
	'post'           => array( 'resources', 102, 12, 'Resources list' ), // 12 a page: 40 large cards made a 22,000px phone page
	'aw_legal'       => array( 'library', 103, 20, 'Library list' ),
	'aw_opportunity' => array( 'opportunities', 104, 24, 'Opportunities list' ),
	'aw_campaign'    => array( 'campaigns', 105, 20, 'Campaigns list' ),
	'aw_listing'     => array( 'directory', 106, 24, 'Directory list' ),
	'aw_press'       => array( 'media', 107, 100, 'Press list' ),
);

/** URL of a hub. */
function aw_hub_url( $type ) {
	return isset( AW_HUBS[ $type ] ) ? home_url( '/' . AW_HUBS[ $type ][0] . '/' ) : home_url( '/' );
}

/** The hub the current page is (post type), or ''. */
function aw_hub_type() {
	static $type = null;
	if ( null !== $type ) {
		return $type;
	}
	$type = '';
	if ( is_page() ) {
		$slug = (string) get_post_field( 'post_name', get_queried_object_id() );
		foreach ( AW_HUBS as $t => $h ) {
			if ( $h[0] === $slug && 0 === (int) wp_get_post_parent_id( get_queried_object_id() ) ) {
				$type = $t;
			}
		}
	}
	return $type;
}

/** The hub of a Query Loop block (by its queryId), or ''. */
function aw_hub_of_query( $query_id ) {
	foreach ( AW_HUBS as $t => $h ) {
		if ( (int) $query_id === $h[1] ) {
			return $t;
		}
	}
	return '';
}

/* ---------- No archives; Pages take the URLs ---------- */

add_filter(
	'register_post_type_args',
	function ( $args, $type ) {
		if ( isset( AW_HUBS[ $type ] ) && 'aw_press' !== $type ) {
			$args['has_archive'] = false; // the hub is a Page; singles keep their rewrite slug
		}
		return $args;
	},
	20,
	2
);

add_action(
	'init',
	function () {
		foreach ( AW_HUBS as $type => $h ) {
			add_rewrite_rule( '^' . $h[0] . '/page/([0-9]+)/?$', 'index.php?pagename=' . $h[0] . '&paged=$matches[1]', 'top' );
			if ( 'aw_press' !== $type && 'aw_listing' !== $type ) {
				// Feeds stay where they were.
				add_rewrite_rule( '^' . $h[0] . '/feed/?$', 'index.php?post_type=' . $type . '&feed=rss2', 'top' );
			}
		}
		if ( '3' !== get_option( 'aw_hub_rewrite' ) ) {
			flush_rewrite_rules( false );
			update_option( 'aw_hub_rewrite', '3', true );
		}
	},
	40
);

// /news/page/2/ is the Page's own pagination: WordPress must not "correct" it to /news/. The one correction kept is the
// trailing slash (/news to /news/, one 301), so a hub has a single address.
add_filter(
	'redirect_canonical',
	function ( $redirect, $requested = '' ) {
		if ( ! aw_hub_type() ) {
			return $redirect;
		}
		$to   = (string) strtok( (string) $redirect, '?' );
		$from = (string) strtok( (string) $requested, '?' );
		return ( '' !== $to && $to !== $from && untrailingslashit( $to ) === untrailingslashit( $from ) ) ? $redirect : false;
	},
	10,
	2
);

/** Page number of a hub Page (from /page/N/). */
function aw_hub_page() {
	return max( 1, (int) get_query_var( 'paged' ) );
}

add_action(
	'template_redirect',
	function () {
		$type = aw_hub_type();
		if ( ! $type ) {
			return;
		}
		$key = 'query-' . AW_HUBS[ $type ][1] . '-page';
		// An old-style ?query-101-page=2 link goes to /news/page/2/.
		if ( isset( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$n    = max( 1, (int) $_GET[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification
			$base = strtok( aw_hub_base_url(), '?' );
			wp_safe_redirect( add_query_arg( array_map( 'rawurlencode', array_diff_key( wp_unslash( $_GET ), array( $key => 1 ) ) ), $n > 1 ? $base . 'page/' . $n . '/' : $base ), 301 ); // phpcs:ignore WordPress.Security.NonceVerification
			exit;
		}
		// Core pagination blocks read the page from this parameter.
		$_GET[ $key ] = (string) aw_hub_page();
		// A page past the end is a 404, as on the old archives.
		if ( aw_hub_page() > 1 ) {
			$q = new WP_Query( array_merge( aw_hub_query_vars( $type, aw_hub_page() ), array( 'fields' => 'ids' ) ) );
			if ( ! $q->posts ) {
				global $wp_query;
				$wp_query->set_404();
				status_header( 404 );
			}
		}
	},
	5
);

/** The hub URL for this request without /page/N/: the Page, or a Directory place. */
function aw_hub_base_url() {
	$path = (string) wp_parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$path = preg_replace( '#/page/\d+/?$#', '/', $path );
	return home_url( user_trailingslashit( $path ) );
}

/* ---------- The hub query ---------- */

/**
 * Query vars of a hub list: filters, section rules and page.
 *
 * @param string $type Post type.
 * @param int    $page Page number.
 * @return array
 */
function aw_hub_query_vars( $type, $page = 1 ) {
	$h = AW_HUBS[ $type ];
	$v = array(
		'post_type'           => $type,
		'post_status'         => 'publish',
		'posts_per_page'      => $h[2],
		'paged'               => max( 1, (int) $page ),
		'ignore_sticky_posts' => true,
	);
	if ( function_exists( 'aw_filter_clauses' ) ) {
		list( $tq, $mq, $in ) = aw_filter_clauses( $type, aw_active_filters( $type ) );
		if ( $tq ) {
			$v['tax_query'] = $tq;
		}
		if ( $mq ) {
			$v['meta_query'] = $mq;
		}
		if ( null !== $in ) {
			$v['post__in'] = $in;
		}
	}
	$sort = function_exists( 'aw_active_sort' ) ? aw_active_sort( $type ) : '';
	if ( 'old' === $sort ) {
		$v['orderby'] = array( 'date' => 'ASC' );
	} elseif ( 'az' === $sort ) {
		$v['orderby'] = array( 'title' => 'ASC' );
	}
	if ( 'aw_opportunity' === $type ) {
		$v['meta_query']            = (array) ( $v['meta_query'] ?? array() );
		$v['meta_query']['aw_open'] = aw_open_opportunities_meta_query();
		if ( 'closing' === $sort ) { // the nearest closing date first; no date last
			$v['meta_query']['closes'] = array( 'key' => 'aw_closes', 'compare' => 'EXISTS' );
			$v['orderby']              = array( 'closes' => 'ASC', 'date' => 'DESC' );
		}
	} elseif ( 'aw_campaign' === $type ) {
		$aw1 = get_page_by_path( 'india-writes-for-its-community-dogs', OBJECT, 'aw_campaign' );
		if ( $aw1 ) {
			$v['post__not_in'] = array( $aw1->ID ); // shown above the list as its own story
		}
	} elseif ( 'aw_listing' === $type ) {
		$v = aw_directory_query_vars( $v );
	} elseif ( 'aw_press' === $type ) {
		// Newest first, undated last.
		$v['meta_query'] = array_merge(
			(array) ( $v['meta_query'] ?? array() ),
			array(
				'relation' => 'AND',
				'pdate'    => array(
					'relation' => 'OR',
					'has'      => array( 'key' => 'aw_press_date', 'compare' => 'EXISTS' ),
					'none'     => array( 'key' => 'aw_press_date', 'compare' => 'NOT EXISTS' ),
				),
			)
		);
		$v['orderby'] = array( 'has' => 'DESC', 'menu_order' => 'old' === $sort ? 'DESC' : 'ASC' );
	}
	return $v;
}

/** Number of items in a hub list with the current filters (cached per request). */
function aw_hub_total( $type ) {
	return max( 0, (int) aw_hub_query( $type )->found_posts - aw_hub_preview_only_count( $type ) );
}

/**
 * How many preview-only items (kits_preview_only_ids, e.g. the sample listing) the current list holds: their cards stay
 * in the HTML but are hidden for visitors, so counts leave them out. Administrators count them.
 */
function aw_hub_preview_only_count( $type ) {
	static $n = array();
	$ids = function_exists( 'kits_preview_only_ids' ) ? kits_preview_only_ids() : array();
	if ( ! $ids || current_user_can( 'manage_options' ) ) {
		return 0;
	}
	if ( ! isset( $n[ $type ] ) ) {
		$v = aw_hub_query_vars( $type, 1 );
		$v['post__in']       = isset( $v['post__in'] ) ? array_values( array_intersect( array_map( 'intval', (array) $v['post__in'] ), $ids ) ) : $ids;
		$v['fields']         = 'ids';
		$v['posts_per_page'] = -1;
		$v['paged']          = 1;
		$v['no_found_rows']  = true;
		$n[ $type ]          = $v['post__in'] ? count( get_posts( $v + array( 'suppress_filters' => false ) ) ) : 0;
	}
	return $n[ $type ];
}

/** The items on the current page of a hub list (for schema). */
function aw_hub_posts( $type ) {
	return aw_hub_query( $type )->posts;
}

/** One query per request for the count line and the schema (the list itself is core's Query Loop). */
function aw_hub_query( $type ) {
	static $q = array();
	if ( ! isset( $q[ $type ] ) ) {
		$q[ $type ] = new WP_Query( aw_hub_query_vars( $type, aw_hub_page() ) );
	}
	return $q[ $type ];
}

// Each hub Query Loop gets its items from the hub query.
add_filter(
	'query_loop_block_query_vars',
	function ( $vars, $block, $page ) {
		$type = aw_hub_of_query( $block->context['queryId'] ?? 0 );
		if ( ! $type || ( $block->context['query']['postType'] ?? '' ) !== $type ) {
			return $vars;
		}
		return aw_hub_query_vars( $type, $page );
	},
	20,
	3
);

// Any other Query Loop of Opportunities (e.g. on the Adoption page) follows the section rule too: open ones only.
add_filter(
	'query_loop_block_query_vars',
	function ( $vars, $block ) {
		if ( 'aw_opportunity' !== ( $block->context['query']['postType'] ?? '' ) || aw_hub_of_query( $block->context['queryId'] ?? 0 ) ) {
			return $vars;
		}
		$vars['meta_query']            = (array) ( $vars['meta_query'] ?? array() );
		$vars['meta_query']['aw_open'] = aw_open_opportunities_meta_query();
		return $vars;
	},
	20,
	2
);

/**
 * Hub list markup: the Query Loop carries the page state, pagination links use /page/N/ and keep the filters,
 * and a list marked "aw-hide-empty" shows nothing when it has no items.
 */
add_filter(
	'render_block_core/query',
	function ( $html, $block ) {
		$type = aw_hub_of_query( $block['attrs']['queryId'] ?? 0 );
		if ( ! $type ) {
			return $html;
		}
		if ( false !== strpos( (string) ( $block['attrs']['className'] ?? '' ), 'aw-hide-empty' ) && false === strpos( $html, 'wp-block-post ' ) && false === strpos( $html, 'wp-block-post"' ) ) {
			return '';
		}
		$first = aw_hub_page() <= 1 ? ' is-first-page' : ' is-paged';
		return preg_replace( '/class="wp-block-query/', 'class="wp-block-query' . $first, $html, 1 );
	},
	10,
	2
);

add_filter(
	'render_block_core/query-pagination',
	function ( $html, $block, $instance ) {
		$type = aw_hub_of_query( $instance->context['queryId'] ?? 0 );
		if ( ! $type ) {
			return $html;
		}
		$key  = 'query-' . AW_HUBS[ $type ][1] . '-page';
		$base = strtok( aw_hub_base_url(), '?' );
		return preg_replace_callback(
			'/href="([^"]*)"/',
			function ( $m ) use ( $key, $base ) {
				$url  = html_entity_decode( $m[1] );
				$args = array();
				wp_parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $args );
				$n = max( 1, (int) ( $args[ $key ] ?? 1 ) );
				unset( $args[ $key ], $args['cst'] ); // cst: added by core pagination, not ours
				$to = $n > 1 ? $base . 'page/' . $n . '/' : $base;
				return 'href="' . esc_url( add_query_arg( array_map( 'rawurlencode', $args ), $to ) ) . '"';
			},
			$html
		);
	},
	10,
	3
);

/* ---------- The Pages and their list patterns (created once; the owner edits them after) ---------- */

/** Intro of each hub (moved verbatim from the old section header). */
function aw_hub_intro( $type ) {
	if ( 'aw_press' === $type ) {
		return 'Press coverage of Animal Writes and its citizen letter campaign for community animals across India.';
	}
	return AW_SECTION_DESC[ $type ] ?? '';
}

/** The section band of a hub Page: breadcrumb, H1, intro, count and pictures, then the filters or search. */
function aw_hub_band( $type ) {
	return '<!-- wp:animal-writes/section-head {"postType":"' . $type . '"} /-->

<!-- wp:pattern {"slug":"kits-by-wugweb/banner"} /-->';
}

/** One Post Template item per hub (core blocks, plus small blocks for data core blocks cannot show). */
function aw_hub_item( $type ) {
	switch ( $type ) {
		case 'aw_news':
			return '<!-- wp:group {"tagName":"article","className":"kit-card aw-story","layout":{"type":"default"}} -->
<article class="wp-block-group kit-card aw-story"><!-- wp:post-featured-image {"isLink":true,"className":"kit-story-media"} /-->

<!-- wp:group {"className":"aw-story-body","layout":{"type":"default"}} -->
<div class="wp-block-group aw-story-body"><!-- wp:animal-writes/publisher /-->

<!-- wp:post-title {"level":2,"isLink":true,"className":"aw-story-title"} /-->

<!-- wp:post-excerpt {"excerptLength":40,"className":"aw-story-sum"} /--></div>
<!-- /wp:group --></article>
<!-- /wp:group -->';
		case 'aw_legal':
			return '<!-- wp:group {"tagName":"article","className":"kit-card kit-case","layout":{"type":"default"}} -->
<article class="wp-block-group kit-card kit-case"><!-- wp:animal-writes/doc-date /-->

<!-- wp:group {"className":"aw-case-body","layout":{"type":"default"}} -->
<div class="wp-block-group aw-case-body"><!-- wp:post-title {"level":2,"isLink":true,"className":"aw-case-title"} /-->

<!-- wp:post-excerpt {"excerptLength":30,"className":"aw-case-sum"} /-->

<!-- wp:group {"className":"aw-case-tags kit-tags","layout":{"type":"default"}} -->
<div class="wp-block-group aw-case-tags kit-tags"><!-- wp:animal-writes/issuer /-->

<!-- wp:post-terms {"term":"category"} /--></div>
<!-- /wp:group -->

<!-- wp:animal-writes/doc-link /--></div>
<!-- /wp:group -->

<!-- wp:html -->
<span class="kit-ico kit-ico--arrow-right" aria-hidden="true"></span>
<!-- /wp:html --></article>
<!-- /wp:group -->';
		case 'post':
			return '<!-- wp:group {"tagName":"article","className":"kit-card kit-task","layout":{"type":"default"}} -->
<article class="wp-block-group kit-card kit-task"><!-- wp:post-featured-image {"isLink":true,"className":"aw-task-media aw-no-fallback"} /-->

<!-- wp:group {"className":"aw-task-body","layout":{"type":"default"}} -->
<div class="wp-block-group aw-task-body"><!-- wp:animal-writes/task-icon /-->

<!-- wp:post-title {"level":2,"isLink":true,"className":"aw-task-title"} /-->

<!-- wp:post-excerpt {"excerptLength":22,"className":"aw-task-sum"} /-->

<!-- wp:animal-writes/read-meta /--></div>
<!-- /wp:group --></article>
<!-- /wp:group -->';
		case 'aw_opportunity':
			return '<!-- wp:group {"tagName":"article","className":"kit-card aw-opp","layout":{"type":"default"}} -->
<article class="wp-block-group kit-card aw-opp"><!-- wp:post-featured-image {"className":"aw-opp-media aw-no-fallback"} /-->

<!-- wp:group {"className":"aw-opp-body","layout":{"type":"default"}} -->
<div class="wp-block-group aw-opp-body"><!-- wp:group {"className":"aw-opp-tags kit-tags","layout":{"type":"default"}} -->
<div class="wp-block-group aw-opp-tags kit-tags"><!-- wp:post-terms {"term":"post_tag"} /-->

<!-- wp:animal-writes/place /--></div>
<!-- /wp:group -->

<!-- wp:post-title {"level":2,"isLink":true,"className":"aw-opp-title"} /-->

<!-- wp:post-excerpt {"excerptLength":22,"className":"aw-opp-sum"} /-->

<!-- wp:animal-writes/deadline /-->

<!-- wp:animal-writes/opp-actions /--></div>
<!-- /wp:group --></article>
<!-- /wp:group -->';
		case 'aw_campaign':
			return '<!-- wp:group {"tagName":"article","className":"aw-story aw-story--card","layout":{"type":"default"}} -->
<article class="wp-block-group aw-story aw-story--card"><!-- wp:post-featured-image {"isLink":true,"className":"kit-story-media"} /-->

<!-- wp:group {"className":"aw-story-body","layout":{"type":"default"}} -->
<div class="wp-block-group aw-story-body"><!-- wp:post-title {"level":2,"isLink":true,"className":"aw-story-title"} /-->

<!-- wp:post-date {"className":"aw-story-meta"} /--></div>
<!-- /wp:group --></article>
<!-- /wp:group -->';
		case 'aw_listing':
			return '<!-- wp:group {"tagName":"article","className":"aw-org","layout":{"type":"default"}} -->
<article class="wp-block-group aw-org"><!-- wp:animal-writes/org-mark /-->

<!-- wp:group {"className":"aw-org-body","layout":{"type":"default"}} -->
<div class="wp-block-group aw-org-body"><!-- wp:post-title {"level":2,"isLink":true,"className":"aw-org-name"} /-->

<!-- wp:paragraph {"className":"aw-org-meta kit-ico--directory","metadata":{"bindings":{"content":{"source":"animal-writes/field","args":{"key":"area"}}}}} -->
<p class="aw-org-meta kit-ico--directory"></p>
<!-- /wp:paragraph -->

<!-- wp:animal-writes/badges /-->

<!-- wp:animal-writes/contact /--></div>
<!-- /wp:group -->

<!-- wp:animal-writes/org-actions /--></article>
<!-- /wp:group -->';
		case 'aw_press':
			return '<!-- wp:group {"tagName":"article","className":"aw-story aw-story--card aw-story--external","layout":{"type":"default"}} -->
<article class="wp-block-group aw-story aw-story--card aw-story--external"><!-- wp:animal-writes/press-media /-->

<!-- wp:group {"className":"aw-story-body","layout":{"type":"default"}} -->
<div class="wp-block-group aw-story-body"><!-- wp:animal-writes/publisher /-->

<!-- wp:post-title {"level":2,"isLink":true,"className":"aw-story-title"} /-->

<!-- wp:animal-writes/press-meta /--></div>
<!-- /wp:group --></article>
<!-- /wp:group -->';
	}
	return '';
}

/** Empty state of each hub list (existing wording). */
function aw_hub_empty( $type ) {
	$t = array(
		'aw_news'        => '<p>No stories in this category yet.</p>',
		'aw_legal'       => '<p>No updates match this filter yet.</p>',
		'post'           => '<p>No guides match this filter yet.</p>',
		'aw_opportunity' => '<p>No open opportunities of this kind right now.</p>',
		'aw_campaign'    => '',
		'aw_listing'     => '<p>No organisations match. Try another district, or clear the filters.</p><p><a class="kit-btn kit-btn--secondary" href="/directory/">Clear filters</a></p>',
		'aw_press'       => '<p><strong>No coverage yet.</strong></p><p>Written about Animal Writes? <a href="/contact/?about=general">Send us the link.</a></p>',
	);
	return $t[ $type ] ? '<!-- wp:query-no-results -->
<!-- wp:html -->
<div class="kit-empty">' . $t[ $type ] . '</div>
<!-- /wp:html -->
<!-- /wp:query-no-results -->' : '';
}

/** The list section (saved as a synced pattern). */
function aw_hub_list( $type ) {
	$h     = AW_HUBS[ $type ];
	$lists = array( 'aw_news' => 'aw-news-list', 'aw_legal' => 'aw-cases', 'post' => 'aw-tasks', 'aw_opportunity' => 'aw-opps', 'aw_campaign' => 'aw-news-grid', 'aw_listing' => 'aw-orgs', 'aw_press' => 'aw-news-grid' );
	$cls   = 'aw-hub-list aw-hub-' . $h[0] . ( 'aw_campaign' === $type ? ' aw-camp-more aw-hide-empty' : '' );
	$head  = 'aw_campaign' === $type ? '<!-- wp:heading -->
<h2 class="wp-block-heading">More campaigns for animals</h2>
<!-- /wp:heading -->

' : ( 'aw_listing' === $type ? '<!-- wp:animal-writes/result-head /-->

' : '' );
	$pager = in_array( $type, array( 'aw_press', 'aw_campaign' ), true ) ? '' : '

<!-- wp:query-pagination {"className":"kit-pager","layout":{"type":"flex","justifyContent":"center"}} -->
<!-- wp:query-pagination-previous {"label":"Previous"} /-->

<!-- wp:query-pagination-numbers /-->

<!-- wp:query-pagination-next {"label":"Next"} /-->
<!-- /wp:query-pagination -->';
	return '<!-- wp:query {"queryId":' . $h[1] . ',"query":{"perPage":' . $h[2] . ',"pages":0,"offset":0,"postType":"' . $type . '","order":"desc","orderBy":"date","inherit":false},"namespace":"animal-writes/hub","className":"' . $cls . '","layout":{"type":"default"}} -->
<div class="wp-block-query ' . $cls . '">' . $head . '<!-- wp:post-template {"className":"' . $lists[ $type ] . '","layout":{"type":"default"}} -->
' . aw_hub_item( $type ) . '
<!-- /wp:post-template -->' . $pager . '

' . aw_hub_empty( $type ) . '</div>
<!-- /wp:query -->';
}

/** Body after the list (static content the owner can edit). */
function aw_hub_after( $type ) {
	if ( 'post' === $type ) {
		return '<!-- wp:html -->
<div class="aw-also"><h2>Also useful</h2><ul class="kit-tiles"><li><a class="kit-tile" href="#helplines"><span class="kit-ico kit-ico--helpline" aria-hidden="true"></span><strong>Helplines</strong><span class="kit-tile-desc">National helplines: 112, 1962 and AWBI.</span><span class="kit-ico kit-ico--arrow-right" aria-hidden="true"></span></a></li><li><a class="kit-tile" href="/directory/"><span class="kit-ico kit-ico--directory" aria-hidden="true"></span><strong>Find an organisation</strong><span class="kit-tile-desc">Shelters, vets and rescuers near you.</span><span class="kit-ico kit-ico--arrow-right" aria-hidden="true"></span></a></li><li><a class="kit-tile" href="/library/?tag=act"><span class="kit-ico kit-ico--research" aria-hidden="true"></span><strong>The law in full</strong><span class="kit-tile-desc">Acts, Rules and judgments, from the official source.</span><span class="kit-ico kit-ico--arrow-right" aria-hidden="true"></span></a></li></ul></div>
<!-- /wp:html -->';
	}
	if ( 'aw_campaign' === $type ) {
		return '<!-- wp:html -->
<section class="aw-camp-next"><div><h2>Bring a campaign to Animal Writes</h2><p>Running a letter drive, petition or day of action for animals? Tell us and we will help you plan, publish and count it.</p></div><a class="kit-btn" href="/contact/">Talk to us</a></section>
<!-- /wp:html -->';
	}
	if ( 'aw_listing' === $type ) {
		return '<!-- wp:animal-writes/browse-states /-->';
	}
	return '';
}

/** A hub Page's content. */
function aw_hub_page_content( $type, $pattern_id ) {
	$before = 'aw_campaign' === $type ? '<!-- wp:pattern {"slug":"animal-writes/aw1-story"} /-->

' : '';
	return aw_hub_band( $type ) . '

<!-- wp:group {"align":"wide","className":"aw-system","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide aw-system">' . $before . '<!-- wp:block {"ref":' . (int) $pattern_id . '} /-->

' . aw_hub_after( $type ) . '</div>
<!-- /wp:group -->';
}

/**
 * Create the hub Pages and list patterns (once per version). A Page or pattern the owner has edited since it was
 * written here is left alone.
 */
const AW_HUBS_VERSION = '2';
add_action(
	'init',
	function () {
		if ( AW_HUBS_VERSION === get_option( 'aw_hubs_version' ) || ! did_action( 'init' ) ) {
			return;
		}
		$titles = array( 'aw_news' => 'News', 'post' => 'Resources', 'aw_legal' => 'Library', 'aw_opportunity' => 'Opportunities', 'aw_campaign' => 'Campaigns', 'aw_listing' => 'Directory', 'aw_press' => 'Media & PR' );
		$made   = (array) get_option( 'aw_hub_pages', array() );
		$hashes = (array) get_option( 'aw_hub_hashes', array() );
		foreach ( AW_HUBS as $type => $h ) {
			// The list pattern (synced).
			$pid  = (int) ( $made[ $type ]['pattern'] ?? 0 );
			$list = aw_hub_list( $type );
			if ( ! $pid || ! get_post( $pid ) ) {
				$pid = wp_insert_post( array( 'post_type' => 'wp_block', 'post_status' => 'publish', 'post_title' => $h[3], 'post_content' => $list ) );
			} elseif ( md5( get_post_field( 'post_content', $pid ) ) === ( $hashes[ 'p' . $type ] ?? '' ) ) {
				wp_update_post( array( 'ID' => $pid, 'post_content' => $list ) );
			}
			$hashes[ 'p' . $type ] = md5( get_post_field( 'post_content', $pid ) );
			// The Page.
			$page    = get_page_by_path( $h[0], OBJECT, 'page' );
			$content = aw_hub_page_content( $type, $pid );
			if ( ! $page ) {
				$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $titles[ $type ], 'post_name' => $h[0], 'post_content' => $content, 'post_excerpt' => aw_hub_intro( $type ) ) );
			} else {
				$id   = $page->ID;
				$mine = md5( $page->post_content ) === ( $hashes[ 'g' . $type ] ?? '' );
				if ( $mine || empty( $made[ $type ]['page'] ) ) { // first time (Media & PR existed) or unchanged since we wrote it
					wp_update_post( array( 'ID' => $id, 'post_content' => $content, 'post_excerpt' => $page->post_excerpt ? $page->post_excerpt : aw_hub_intro( $type ) ) );
				}
			}
			update_post_meta( $id, '_wp_page_template', 'page-hub' );
			$hashes[ 'g' . $type ] = md5( get_post_field( 'post_content', $id ) );
			$made[ $type ]         = array( 'page' => (int) $id, 'pattern' => (int) $pid );
		}
		update_option( 'aw_hub_pages', $made, false );
		update_option( 'aw_hub_hashes', $hashes, false );
		update_option( 'aw_hubs_version', AW_HUBS_VERSION, false );
	},
	99
);

/* ---------- Ads on list Pages (the hubs and search are Pages, not archives) ---------- */

add_filter( 'kits_ads_is_list', fn( $is ) => $is || '' !== aw_hub_type() || is_page( 'search' ) );

// The list's count, centred above its pagination ("23 stories").
add_filter(
	'render_block_core/query-pagination',
	function ( $html, $block, $instance ) {
		$type = aw_hub_of_query( (int) ( $instance->context['queryId'] ?? 0 ) );
		if ( ! $type || 'aw_listing' === $type || ! function_exists( 'aw_hub_count_label' ) ) { // the Directory shows its count with its results
			return $html;
		}
		return '<p class="kit-list-count" role="status">' . esc_html( aw_hub_count_label( $type ) ) . '</p>' . $html;
	},
	10,
	3
);


/*
 * Library hub (owner, 17 Oct 2026): the list has its own heading (for screen readers; nothing visible changes) and each
 * card title is an H3 under it, so headings go H1 Library > H2 list > H3 items. Only the Library Page's list.
 */
function aw_is_library_hub() {
	return ! is_admin() && is_page( 'library' );
}
add_filter(
	'render_block_data',
	function ( $parsed ) {
		if ( 'core/post-title' === ( $parsed['blockName'] ?? '' ) && false !== strpos( (string) ( $parsed['attrs']['className'] ?? '' ), 'aw-case-title' ) && 2 === (int) ( $parsed['attrs']['level'] ?? 2 ) && aw_is_library_hub() ) {
			$parsed['attrs']['level'] = 3;
		}
		return $parsed;
	}
);
add_filter(
	'render_block_core/query',
	function ( $html, $block ) {
		static $done = false;
		if ( $done || ! aw_is_library_hub() || false === strpos( (string) ( $block['attrs']['className'] ?? '' ), 'aw-hub-list' ) ) {
			return $html;
		}
		$done = true;
		return '<h2 class="screen-reader-text">' . esc_html__( 'Library documents', 'animal-writes-core' ) . '</h2>' . $html;
	},
	10,
	2
);
