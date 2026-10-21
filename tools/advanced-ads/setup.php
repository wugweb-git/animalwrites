<?php
/**
 * Advanced Ads is the only ad system (owner, 21 Oct 2026); Kits Core no longer prints ads. This sets up Advanced Ads to
 * hold every position the Kits Core system had, under the same names, and moves the rules into it.
 *
 *   wp eval-file tools/advanced-ads/setup.php                 dry run: prints what it would change
 *   wp eval-file tools/advanced-ads/setup.php apply           makes the changes (back up the database first)
 *   wp eval-file tools/advanced-ads/setup.php apply cleanup   also deletes the old Kits Core ad options
 *
 * Idempotent: a second run changes nothing. Needs Advanced Ads active.
 *
 * 1. Placements (one per old slot, slug = old slot name). In-text ads are "Content" placements (after paragraph N), the
 *    end of an article an "After Content" placement, every fixed position a "Manual" placement shown by the Advanced
 *    Ads block in the template or page (advads/gblock {"itemID":"place_<slug>"}). Missing placements are created.
 * 2. Ads. Display conditions sit on ads in the free plugin, so each set of rules is one ad, cloned from the existing
 *    AdSense unit where a new one is needed (home: front page; article: News, Library, Resources, Opportunities;
 *    end: those and Directory profiles; site: everywhere else). Every ad carries the old exclusions: search, the form
 *    pages, crisis guides, Cruelty and Prosecution, government vets and MVUs, volunteer roles, and (as they are today)
 *    sponsored Resources and pages with a sponsored block or a letter tool.
 * 3. Settings: the "Advertisement" label, ads.txt, no content injection on archive pages.
 * 4. Content: kits/ad-slot blocks and [aw_ad] shortcodes in pages, posts, templates and template parts saved in the
 *    database become Advanced Ads blocks for the placement of the same name.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$args    = isset( $args ) && is_array( $args ) ? $args : array();
$apply   = in_array( 'apply', $args, true );
$cleanup = in_array( 'cleanup', $args, true );
$log     = function ( $m ) {
	echo $m . "\n"; // phpcs:ignore
};
$log( $apply ? 'APPLY' : 'DRY RUN (add "apply" to make the changes)' );

if ( ! function_exists( 'wp_advads_get_ad' ) ) {
	$log( 'Advanced Ads is not active; nothing done.' );
	return;
}

/* ---------- The rules (as in the old kits-config.php kits_ads_skip) ---------- */

$ids_of_pages = function ( array $slugs ) {
	$ids = array();
	foreach ( $slugs as $s ) {
		$p = get_page_by_path( $s );
		if ( $p ) {
			$ids[] = (int) $p->ID;
		}
	}
	return $ids;
};
$term_ids     = function ( $tax, array $slugs ) {
	$ids = array();
	foreach ( $slugs as $s ) {
		$t = get_term_by( 'slug', $s, $tax );
		if ( $t ) {
			$ids[] = (int) $t->term_id;
		}
	}
	return $ids;
};
$query_ids    = function ( array $q ) {
	return array_map( 'intval', get_posts( $q + array( 'post_type' => 'any', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ) ) );
};

global $wpdb;
$no_ads_ids = array_values(
	array_unique(
		array_merge(
			$ids_of_pages( array( 'helplines', 'corrections', 'contact', 'volunteer', 'remove-my-data', 'search', 'claim', 'report', 'thank-you' ) ),
			array( 126, 127, 9027, 9028, 9029, 9030, 9031, 9032, 9033 ), // crisis guides (AW_CRISIS_GUIDES)
			$query_ids( array( 'meta_key' => 'aw_sponsored', 'meta_value' => '1' ) ), // sponsored Resources, as they are today
			array_map( 'intval', $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND ( post_content LIKE '%wp:animal-writes/sponsored-%' OR post_content LIKE '%[aw_letter_tool%' )" ) ) // phpcs:ignore WordPress.DB
		)
	)
);
sort( $no_ads_ids );
$no_cat  = $term_ids( 'category', array( 'cruelty-prosecution' ) );
$no_tags = $term_ids( 'post_tag', array( 'veterinary-office', 'mobile-veterinary-unit', 'volunteer' ) );
$log( 'No ads on ' . count( $no_ads_ids ) . ' pages and posts: ' . implode( ',', $no_ads_ids ) );
$vol = get_term_by( 'slug', 'volunteer', 'post_tag' );
if ( $vol ) {
	$other = $query_ids( array( 'post_type' => array( 'aw_news', 'aw_legal', 'post', 'aw_listing' ), 'tag_id' => $vol->term_id ) );
	$log( 'Note: the "volunteer" tag rule (old: volunteer Opportunities) also covers ' . count( $other ) . ' other items with that tag' . ( $other ? ': ' . implode( ',', $other ) : '' ) );
}

$and        = fn( array $c ) => $c + array( 'connector' => 'and' );
$exclusions = array_values(
	array_filter(
		array(
			$and( array( 'type' => 'general', 'value' => array( 'is_front_page', 'is_singular', 'is_archive', 'is_main_query' ) ) ), // not search, 404, feeds
			$no_ads_ids ? $and( array( 'type' => 'postids', 'operator' => 'is_not', 'value' => array_map( 'strval', $no_ads_ids ) ) ) : null,
			$no_cat ? $and( array( 'type' => 'taxonomy_category', 'operator' => 'is_not', 'value' => array_map( 'strval', $no_cat ) ) ) : null,
			$no_tags ? $and( array( 'type' => 'taxonomy_post_tag', 'operator' => 'is_not', 'value' => array_map( 'strval', $no_tags ) ) ) : null,
		)
	)
);
$types   = fn( array $t ) => array( 'type' => 'posttypes', 'operator' => 'is', 'value' => $t );
$article = array( 'aw_news', 'aw_legal', 'post', 'aw_opportunity' );

// role => [ ad slug to reuse (live), title, display conditions ].
$roles = array(
	'home'    => array( 'adsense-homepage-unit', 'AdSense – homepage unit', array( array( 'type' => 'general', 'value' => array( 'is_front_page', 'is_main_query' ) ) ) ),
	'article' => array( 'adsense-article-unit', 'AdSense – article unit', array_merge( array( $types( $article ) ), $exclusions ) ),
	'end'     => array( 'adsense-end-unit', 'AdSense – end of article and profile unit', array_merge( array( $types( array_merge( $article, array( 'aw_listing' ) ) ) ), $exclusions ) ),
	'site'    => array( 'adsense-list-unit', 'AdSense – list unit', $exclusions ),
);

/* ---------- Placements: slug => [ title, type, options, role, where ] ---------- */

$content = fn( $n ) => array( 'position' => 'after', 'index' => $n, 'tag' => 'p' );
$plan    = array(
	'home-top'               => array( 'Homepage – top', 'default', array(), 'home', 'block on the homepage' ),
	'home-mid'               => array( 'Homepage – middle', 'default', array(), 'home', 'block on the homepage' ),
	'home-bottom'            => array( 'Homepage – bottom', 'default', array(), 'home', 'block on the homepage' ),
	'article-incontent-1'    => array( 'Article – in text 1', 'post_content', $content( 3 ), 'article', 'after paragraph 3' ),
	'article-incontent-2'    => array( 'Article – in text 2', 'post_content', $content( 9 ), 'article', 'after paragraph 9' ),
	'article-end'            => array( 'Article – end', 'post_bottom', array(), 'end', 'after the content; block on Directory profiles' ),
	'list-feed-1'            => array( 'List – card 1', 'default', array(), 'site', 'not placed (after list item 6: Advanced Ads Pro)' ),
	'list-feed-2'            => array( 'List – card 2', 'default', array(), 'site', 'block at the end of category and tag pages' ),
	'sidebar'                => array( 'Sidebar', 'default', array(), 'end', 'block in the sidebar template part' ),
	'footer-above'           => array( 'Above the footer', 'default', array(), 'site', 'block in the footer template part' ),
	'directory-feed'         => array( 'Directory – in results', 'default', array(), 'site', 'not placed (after result 5, 11, 17…: Advanced Ads Pro)' ),
	'directory-district-end' => array( 'Directory – end of district page', 'default', array(), 'site', 'not placed (district pages only: owner to decide)' ),
	'profile'                => array( 'Directory profile', 'default', array(), 'end', 'block on Directory profiles' ),
	'opportunity-end'        => array( 'Opportunity – end', 'default', array(), 'end', 'not placed (it was in no template)' ),
	'resources-hub'          => array( 'Resources page', 'default', array(), 'site', 'block on the Resources page' ),
	'media-feed'             => array( 'Media & PR – in list', 'default', array(), 'site', 'not placed (after item 6: Advanced Ads Pro)' ),
);

/* ---------- 2. Ads ---------- */

$ad_by_slug = function ( $slug ) {
	$p = get_page_by_path( $slug, OBJECT, 'advanced_ads' );
	return $p ? (int) $p->ID : 0;
};
$source = 0; // the AdSense unit new ads are cloned from
foreach ( array( 'adsense-article-unit', 'adsense-list-unit', 'adsense-homepage-unit' ) as $s ) {
	$source = $source ? $source : $ad_by_slug( $s );
}
$ad_ids = array();
foreach ( $roles as $role => list( $slug, $title, $conds ) ) {
	$id = $ad_by_slug( $slug );
	if ( ! $id ) {
		$log( "ad $slug: create" . ( $source ? " (clone of #$source)" : ' (plain test ad: no AdSense unit on this site)' ) );
		if ( $apply ) {
			if ( $source ) {
				$src = get_post( $source );
				$id  = (int) wp_insert_post( array( 'post_type' => 'advanced_ads', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_content' => $src->post_content ) );
				foreach ( get_post_meta( $source ) as $k => $vals ) {
					foreach ( $vals as $v ) {
						add_post_meta( $id, $k, maybe_unserialize( $v ) );
					}
				}
			} else {
				$ad = wp_advads_create_new_ad( 'plain' );
				$ad->set_title( $title );
				$ad->set_content( '<div style="background:#ffd;padding:24px;text-align:center">TEST AD ' . esc_html( $slug ) . '</div>' );
				$ad->set_status( 'publish' );
				$id = (int) $ad->save();
				wp_update_post( array( 'ID' => $id, 'post_name' => $slug ) );
			}
		}
	}
	if ( $id ) {
		$ad = wp_advads_get_ad( $id );
		if ( $ad && wp_json_encode( $ad->get_display_conditions() ) !== wp_json_encode( $conds ) ) {
			$log( "ad $slug (#$id): display conditions set" );
			if ( $apply ) {
				$ad->set_display_conditions( $conds );
				$ad->save();
			}
		}
	}
	$ad_ids[ $role ] = $id;
}

/* ---------- 1. Placements ---------- */

foreach ( $plan as $slug => list( $title, $type, $opts, $role, $where ) ) {
	$p    = get_page_by_path( $slug, OBJECT, 'advanced_ads_plcmnt' );
	$id   = $p ? (int) $p->ID : 0;
	$item = $ad_ids[ $role ] ? 'ad_' . $ad_ids[ $role ] : '';
	if ( ! $id ) {
		$log( "placement $slug: create ($type, $where)" );
		if ( ! $apply ) {
			continue;
		}
		$id = (int) wp_insert_post( array( 'post_type' => 'advanced_ads_plcmnt', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug ) );
	}
	$old_opts = (array) get_post_meta( $id, 'options', true );
	$new_opts = array_merge( $old_opts, $opts );
	$diff     = array();
	if ( get_post_meta( $id, 'type', true ) !== $type ) {
		$diff[] = 'type ' . get_post_meta( $id, 'type', true ) . " → $type";
	}
	if ( (string) get_post_meta( $id, 'item', true ) !== $item ) {
		$diff[] = 'ad ' . get_post_meta( $id, 'item', true ) . " → $item";
	}
	if ( $new_opts !== $old_opts ) {
		$diff[] = 'options ' . wp_json_encode( $opts );
	}
	if ( $p && 'publish' !== $p->post_status ) {
		$diff[] = 'published';
	}
	if ( $diff ) {
		$log( "placement $slug (#$id): " . implode( '; ', $diff ) . " ($where)" );
		if ( $apply ) {
			update_post_meta( $id, 'type', $type );
			update_post_meta( $id, 'item', $item );
			update_post_meta( $id, 'options', $new_opts );
			if ( $p && 'publish' !== $p->post_status ) {
				wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
			}
		}
	}
}

/* ---------- 3. Settings ---------- */

$o   = (array) get_option( 'advanced-ads', array() );
$new = array_merge(
	$o,
	array(
		'custom-label'             => array( 'enabled' => '1', 'text' => 'Advertisement', 'html_enabled' => false ),
		'content-injection-enabled' => 'off', // no ads inside excerpts on list pages
	)
);
if ( $new !== $o ) {
	$log( 'settings: ad label "Advertisement"; no content injection on archive pages' );
	if ( $apply ) {
		update_option( 'advanced-ads', $new );
	}
}
$txt  = 'google.com, pub-5562717269522486, DIRECT, f08c47fec0942fa0';
$ot   = (array) get_option( 'advanced_ads_ads_txt', array() );
$newt = array_merge( $ot, array( 'custom' => $txt ) );
if ( $newt !== $ot ) {
	$log( "ads.txt: $txt" );
	if ( $apply ) {
		update_option( 'advanced_ads_ads_txt', $newt );
	}
}

/* ---------- 4. Content saved in the database ---------- */

$rows = $wpdb->get_results( "SELECT ID, post_type, post_name, post_content FROM {$wpdb->posts} WHERE post_status <> 'inherit' AND ( post_content LIKE '%wp:kits/ad-slot%' OR post_content LIKE '%[aw_ad%' )" ); // phpcs:ignore WordPress.DB
foreach ( $rows as $r ) {
	$c = (string) $r->post_content;
	$c = preg_replace( '#<!-- wp:kits/ad-slot \{"slot":"([a-z0-9-]+)"\} /-->#', '<!-- wp:advads/gblock {"itemID":"place_$1"} /-->', $c );
	$c = preg_replace( '#<!-- wp:kits/ad-slot /-->#', '<!-- wp:advads/gblock {"itemID":"place_home-mid"} /-->', $c ); // the block's default slot
	$c = preg_replace( '#<!-- wp:shortcode -->\s*\[aw_ad slot="([a-z0-9-]+)"\]\s*<!-- /wp:shortcode -->#', '<!-- wp:advads/gblock {"itemID":"place_$1"} /-->', $c );
	$c = preg_replace( '#<!-- wp:shortcode -->\s*\[aw_ad\]\s*<!-- /wp:shortcode -->#', '<!-- wp:advads/gblock {"itemID":"place_home-top"} /-->', $c );
	$left = preg_match_all( '#\[aw_ad\b[^\]]*\]#', $c );
	foreach ( array( 'home' => 'home-top', 'incontent' => 'article-incontent-1', 'article' => 'article-end', 'archive' => 'list-feed-1', 'directory' => 'directory-feed' ) as $old => $now ) {
		$c = str_replace( '"itemID":"place_' . $old . '"', '"itemID":"place_' . $now . '"', $c ); // older slot names
	}
	if ( $c !== $r->post_content ) {
		$log( "content {$r->post_type} {$r->post_name} (#{$r->ID}): ad blocks → Advanced Ads blocks" );
		if ( $apply ) {
			$wpdb->update( $wpdb->posts, array( 'post_content' => $c ), array( 'ID' => $r->ID ) ); // phpcs:ignore WordPress.DB
			clean_post_cache( (int) $r->ID );
		}
	}
	if ( $left ) {
		$log( "content {$r->post_type} {$r->post_name} (#{$r->ID}): $left [aw_ad] shortcode(s) inside text, left for the owner" );
	}
}

/* ---------- Old Kits Core ad options ---------- */

$old = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'aw\\_ad\\_%' OR option_name IN ( 'aw_ads_txt', 'kits_ads_off' )" ); // phpcs:ignore WordPress.DB
if ( $old ) {
	$log( 'old Kits Core ad options: ' . implode( ', ', $old ) . ( $cleanup ? '' : ' (kept; add "cleanup" to delete)' ) );
	if ( $apply && $cleanup ) {
		array_map( 'delete_option', $old );
	}
}
if ( $apply ) {
	wp_cache_flush();
}
$log( 'done' );
