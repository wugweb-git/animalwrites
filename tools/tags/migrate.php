<?php
/**
 * Labels → WordPress's own Tags (owner, 15 Oct 2026): Library types (aw_legal_kind), Resource types
 * (aw_resource_type), Opportunity types (aw_opportunity_type), Listing types (aw_listing_type) and Services
 * (aw_service) become post_tag terms.
 *
 *   wp eval-file tools/tags/migrate.php dry   # what would change, counts per term and type; changes nothing
 *   wp eval-file tools/tags/migrate.php run   # do it (safe to run again: every step checks first)
 *   wp eval-file tools/tags/migrate.php check # compare with the snapshot taken before; writes the after CSV
 *
 * The terms move in place (their taxonomy changes; nothing is copied or deleted), so every item keeps its labels and
 * every term keeps its ID, name, slug, description and meta (photos; the IDs in patterns, "Find help" and guide
 * links stay right). Slugs are already singular. One slug is in two lists: "wildlife-rescue" is a Listing type
 * (60 listings) and a Service (no listings); they mean the same, so the Listing type becomes the tag and the empty
 * Service term stays where it is (aw_service, no longer registered) until the owner says to remove it.
 * Also: each section's allowed categories and tags (Settings → Sourcing → Sections), Rank Math (Titles & Meta and
 * sitemap for Tags, llms.txt, Primary Taxonomy for listings), redirects and links that used ?kind=, and each tag's
 * Rank Math title, description and robots (aw_seo_sync_tags).
 * CSVs: ~/backups/tags-before-2026-10-15.csv and ~/backups/tags-after-2026-10-15.csv.
 *
 * @package animal-writes
 */

global $wpdb;

$mode   = $args[0] ?? 'dry';
$dry    = 'run' !== $mode;
$before = getenv( 'HOME' ) . '/backups/tags-before-2026-10-15.csv';
$after  = getenv( 'HOME' ) . '/backups/tags-after-2026-10-15.csv';
$old    = array( 'aw_legal_kind' => 'aw_legal', 'aw_resource_type' => 'post', 'aw_opportunity_type' => 'aw_opportunity', 'aw_listing_type' => 'aw_listing', 'aw_service' => 'aw_listing' );
$log    = function ( $s ) {
	WP_CLI::log( $s );
};
$in     = "'" . implode( "','", array_keys( $old ) ) . "'";

/** Item → label rows: [object_id, post_type, post_status, taxonomy, slug]. */
$rows_of = function ( $taxes ) use ( $wpdb ) {
	return $wpdb->get_results( "SELECT r.object_id, p.post_type, p.post_status, tt.taxonomy, t.slug FROM {$wpdb->term_relationships} r JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = r.term_taxonomy_id JOIN {$wpdb->terms} t ON t.term_id = tt.term_id JOIN {$wpdb->posts} p ON p.ID = r.object_id WHERE tt.taxonomy IN ($taxes) ORDER BY r.object_id, t.slug", ARRAY_A ); // phpcs:ignore WordPress.DB
};
$write = function ( $file, $rows ) {
	$fh = fopen( $file, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	fputcsv( $fh, array( 'object_id', 'post_type', 'post_status', 'taxonomy', 'slug' ) );
	foreach ( $rows as $r ) {
		fputcsv( $fh, array_values( $r ) );
	}
	fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
};

if ( 'check' === $mode ) {
	if ( ! file_exists( $before ) ) {
		WP_CLI::error( 'No snapshot at ' . $before );
	}
	$fh   = fopen( $before, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$head = fgetcsv( $fh );
	$was  = array();
	while ( ( $row = fgetcsv( $fh ) ) ) {
		$r     = array_combine( $head, $row );
		$was[] = $r['object_id'] . '|' . $r['slug'];
	}
	fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$rows = $rows_of( "'post_tag'" );
	$write( $after, $rows );
	$now  = array_map( fn( $r ) => $r['object_id'] . '|' . $r['slug'], $rows );
	$lost = array_diff( $was, $now );
	$new  = array_diff( $now, $was );
	$log( sprintf( 'Before: %d item-label links. Tags now: %d. Missing now: %d. New since: %d. After CSV: %s', count( $was ), count( $now ), count( $lost ), count( $new ), $after ) );
	foreach ( array_slice( $lost, 0, 20 ) as $l ) {
		$log( '  missing ' . $l );
	}
	foreach ( array_slice( $new, 0, 20 ) as $l ) {
		$log( '  new ' . $l );
	}
	return;
}

/* ---------- State and checks ---------- */

$terms = $wpdb->get_results( "SELECT t.term_id, t.slug, t.name, tt.taxonomy, tt.term_taxonomy_id, tt.count FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy IN ($in, 'post_tag') ORDER BY tt.taxonomy, t.slug" ); // phpcs:ignore WordPress.DB
$by    = array();
foreach ( $terms as $t ) {
	$by[ $t->slug ][] = $t;
}
$keep_out = array(); // term_taxonomy_ids that stay in their old list (a same-meaning duplicate with no items)
foreach ( $by as $slug => $list ) {
	if ( count( $list ) < 2 ) {
		continue;
	}
	$names = implode( ' / ', array_map( fn( $t ) => "{$t->taxonomy} \"{$t->name}\" ({$t->count})", $list ) );
	$tag   = array_values( array_filter( $list, fn( $t ) => 'post_tag' === $t->taxonomy ) );
	$main  = $tag ? $tag[0] : array_values( array_filter( $list, fn( $t ) => 'aw_service' !== $t->taxonomy ) )[0] ?? null;
	foreach ( $list as $t ) {
		if ( $main && $t->term_taxonomy_id !== $main->term_taxonomy_id ) {
			$items = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d", $t->term_taxonomy_id ) ); // phpcs:ignore WordPress.DB
			if ( $items ) {
				WP_CLI::error( "Slug clash with items on both sides: $slug: $names. Stopped." );
			}
			$keep_out[] = (int) $t->term_taxonomy_id;
		}
	}
	$log( "Slug in two lists: $slug: $names → one tag (the empty duplicate stays in its old list)" );
}
$moving = array_filter( $terms, fn( $t ) => 'post_tag' !== $t->taxonomy && ! in_array( (int) $t->term_taxonomy_id, $keep_out, true ) );
$log( sprintf( 'Terms to move: %d. Already tags: %d. Mode: %s.', count( $moving ), count( array_filter( $terms, fn( $t ) => 'post_tag' === $t->taxonomy ) ), $dry ? 'dry run' : 'run' ) );

/* ---------- Sections: what each one uses ---------- */

$sections = array();
foreach ( $old as $tax => $type ) {
	foreach ( $terms as $t ) {
		if ( $t->taxonomy === $tax && ! in_array( (int) $t->term_taxonomy_id, $keep_out, true ) ) {
			$sections[ $type ]['tags'][] = $t->slug;
		}
	}
}
$all_cats = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false, 'fields' => 'slugs' ) );
$dir_cats = array( 'community-dogs', 'cattle-gaushalas', 'wildlife', 'birds', 'veterinary-care', 'animal-birth-control', 'adoption', 'disaster-rescue' );
$opp_cats = array_merge( $dir_cats, array( 'feeders-caregivers', 'campaigns-civic-action', 'policy-government' ) );
foreach ( array( 'aw_news', 'aw_legal', 'post', 'aw_opportunity', 'aw_campaign', 'aw_listing' ) as $type ) {
	$sections[ $type ]['categories'] = 'aw_listing' === $type ? $dir_cats : ( 'aw_opportunity' === $type ? $opp_cats : $all_cats );
	$sections[ $type ]['tags']       = array_values( array_unique( $sections[ $type ]['tags'] ?? array() ) );
}
$src = get_option( 'aw_sources' );
if ( is_array( $src ) && empty( $src['sections'] ) ) {
	foreach ( $sections as $type => $l ) {
		$log( sprintf( 'Section %s: %d categories, tags: %s', $type, count( $l['categories'] ), implode( ', ', $l['tags'] ) ?: '(none)' ) );
	}
	if ( ! $dry ) {
		$src['sections'] = $sections;
		update_option( 'aw_sources', $src, false );
	}
}

/* ---------- Snapshot and the move ---------- */

$rows = $rows_of( $in );
if ( $rows ) {
	$c = array();
	foreach ( $rows as $r ) {
		$k       = $r['taxonomy'] . '  ' . $r['slug'] . '  ' . $r['post_type'];
		$c[ $k ] = ( $c[ $k ] ?? 0 ) + 1;
	}
	ksort( $c );
	$log( 'Item-label links before: ' . count( $rows ) );
	foreach ( $c as $k => $n ) {
		$log( "  $k  $n  →  post_tag" );
	}
	if ( ! $dry && ! file_exists( $before ) ) {
		$write( $before, $rows );
		$log( 'Snapshot written: ' . $before );
	}
}
if ( $moving ) {
	$ids = implode( ',', array_map( fn( $t ) => (int) $t->term_taxonomy_id, $moving ) );
	$log( 'Moving ' . count( $moving ) . ' terms to post_tag' );
	if ( ! $dry ) {
		$wpdb->query( "UPDATE {$wpdb->term_taxonomy} SET taxonomy = 'post_tag' WHERE term_taxonomy_id IN ($ids)" ); // phpcs:ignore WordPress.DB
	}
}

/* ---------- Rank Math ---------- */

$t  = get_option( 'rank-math-options-titles', array() );
$s  = get_option( 'rank-math-options-sitemap', array() );
$g  = get_option( 'rank-math-options-general', array() );
$t2 = array_merge(
	$t,
	array(
		'tax_post_tag_title'                 => '%term% in India %sep% %sitename%', // each tag's own title is filled (aw_seo_sync_tags)
		'tax_post_tag_description'           => '%term_description%',
		'tax_post_tag_robots'                => array( 'index' ), // tags with fewer than 3 items: noindex on the tag (aw_seo_sync_tags)
		'tax_post_tag_custom_robots'         => 'off',
		'tax_post_tag_add_meta_box'          => 'on',
		'tax_post_tag_advanced_robots'       => $t['tax_category_advanced_robots'] ?? array(),
		'tax_post_tag_slack_enhanced_sharing' => 'on',
		'remove_post_tag_snippet_data'       => 'off',
		'pt_aw_listing_primary_taxonomy'     => 'category', // Listing type is a tag now
	)
);
$s2 = array_merge( $s, array( 'tax_post_tag_sitemap' => 'on', 'tax_post_tag_html_sitemap' => 'off', 'tax_post_tag_include_empty' => 'off' ) );
$g2 = $g;
if ( isset( $g['llms_taxonomies'] ) && is_array( $g['llms_taxonomies'] ) ) {
	$g2['llms_taxonomies'] = array_values( array_unique( array_map( fn( $x ) => isset( $old[ $x ] ) ? 'post_tag' : $x, $g['llms_taxonomies'] ) ) );
}
foreach ( array( 'titles' => array( $t, $t2 ), 'sitemap' => array( $s, $s2 ), 'general' => array( $g, $g2 ) ) as $name => $pair ) {
	$changed = array();
	foreach ( array_keys( $pair[1] ) as $k ) {
		if ( ( $pair[0][ $k ] ?? null ) !== $pair[1][ $k ] ) {
			$changed[] = $k . '=' . wp_json_encode( $pair[1][ $k ] );
		}
	}
	$log( "Rank Math $name: " . ( $changed ? implode( '; ', $changed ) : 'no change' ) );
	if ( $changed && ! $dry ) {
		update_option( 'rank-math-options-' . $name, $pair[1] );
	}
}

/* ---------- Links and redirects that used ?kind= / ?type= ---------- */

$rr = $wpdb->prefix . 'rank_math_redirections';
foreach ( $wpdb->get_results( "SELECT id, url_to FROM {$rr} WHERE url_to REGEXP '/(library|resources|opportunities|directory)/[?](kind|type|service)='" ) as $r ) { // phpcs:ignore WordPress.DB
	$to = preg_replace( '#/(library|resources|opportunities|directory)/\?(kind|type|service)=#', '/$1/?tag=', $r->url_to );
	$log( "Rank Math redirect {$r->id}: {$r->url_to} → $to" );
	if ( ! $dry ) {
		$wpdb->update( $rr, array( 'url_to' => $to ), array( 'id' => (int) $r->id ) ); // phpcs:ignore WordPress.DB
	}
}
foreach ( $wpdb->get_results( "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ('page', 'wp_block', 'post') AND post_content REGEXP '/(library|resources|opportunities|directory)/[?](kind|type|service)='" ) as $p ) { // phpcs:ignore WordPress.DB
	$log( "Links in {$p->ID}: ?kind=/?type= → ?tag=" );
	if ( ! $dry ) {
		$wpdb->update( $wpdb->posts, array( 'post_content' => preg_replace( '#/(library|resources|opportunities|directory)/\?(kind|type|service)=#', '/$1/?tag=', $p->post_content ) ), array( 'ID' => (int) $p->ID ) ); // phpcs:ignore WordPress.DB
		clean_post_cache( (int) $p->ID );
	}
}

/* ---------- Counts, caches, the tags' Rank Math fields ---------- */

if ( ! $dry ) {
	$tt = get_terms( array( 'taxonomy' => 'post_tag', 'hide_empty' => false, 'fields' => 'tt_ids' ) );
	if ( $tt && ! is_wp_error( $tt ) ) {
		wp_update_term_count_now( $tt, 'post_tag' );
	}
	clean_taxonomy_cache( 'post_tag' );
	if ( function_exists( 'aw_place_counts_reset' ) ) {
		aw_place_counts_reset();
	}
	foreach ( $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_aw\\_seo\\_%' OR option_name LIKE '\\_transient\\_timeout\\_aw\\_seo\\_%' OR option_name LIKE '\\_transient\\_aw\\_verified%'" ) as $o ) { // phpcs:ignore WordPress.DB
		delete_option( $o ); // cached counts, rebuilt on demand
	}
	wp_cache_flush();
	flush_rewrite_rules( false );
	if ( function_exists( 'aw_seo_sync_tags' ) ) {
		$sync = aw_seo_sync_tags();
		$log( 'Tag titles, descriptions and robots filled: ' . count( $sync ) );
	}
	$log( 'Tags now: ' . (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'post_tag'" ) . ' terms, ' . count( $rows_of( "'post_tag'" ) ) . ' item links.' ); // phpcs:ignore WordPress.DB
}
$log( $dry ? 'Dry run: nothing changed.' : 'Done.' );
