<?php
/**
 * Categories → WordPress's own category; Resource types → aw_resource_type (owner, 15 Oct 2026).
 *
 *   wp eval-file tools/categories/migrate.php dry   # what would change, counts per term and type; changes nothing
 *   wp eval-file tools/categories/migrate.php run   # do it (safe to run again: every step checks first)
 *   wp eval-file tools/categories/migrate.php check # compare with the snapshot taken before
 *
 * The terms move in place (their taxonomy changes, nothing is copied or deleted), so every item keeps its terms,
 * every term keeps its ID, name, slug, description, parent and meta (photo, Rank Math title, description, focus
 * keyword, _aw_seo_auto), and IDs stored elsewhere (Rank Math, patterns) stay right:
 * 1. Guide, Research, FAQ and How-to: category → aw_resource_type.
 * 2. The 18 categories: aw_topic → category.
 * Then the names that said "topic": term meta aw_topic_image, option aw_topic_images, the primary-category meta
 * (→ Rank Math's rank_math_primary_category), the Sourcing lists' key, Rank Math settings (Titles & Meta, Sitemap,
 * Primary Taxonomy, llms.txt), the Categories page (/topics/ → /categories/, with a 301) and one synced pattern.
 * Before anything changes, every item's terms are written to ~/backups/categories-before-2026-10-15.csv.
 *
 * @package animal-writes
 */

global $wpdb;

$mode  = $args[0] ?? 'dry';
$dry   = 'run' !== $mode;
$csv   = getenv( 'HOME' ) . '/backups/categories-before-2026-10-15.csv';
$types = array( 'guide', 'research', 'faq', 'how-to' );
$log   = function ( $s ) {
	WP_CLI::log( $s );
};

/** Item → term rows for some taxonomies: [object_id, post_type, post_status, taxonomy, slug]. */
$rows_of = function ( array $taxes ) use ( $wpdb ) {
	$in = "'" . implode( "','", array_map( 'esc_sql', $taxes ) ) . "'";
	return $wpdb->get_results( "SELECT r.object_id, p.post_type, p.post_status, tt.taxonomy, t.slug FROM {$wpdb->term_relationships} r JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = r.term_taxonomy_id JOIN {$wpdb->terms} t ON t.term_id = tt.term_id JOIN {$wpdb->posts} p ON p.ID = r.object_id WHERE tt.taxonomy IN ($in) ORDER BY r.object_id, tt.taxonomy, t.slug", ARRAY_A ); // phpcs:ignore WordPress.DB
};

/** Counts per term and post type: "taxonomy|slug|type" => n. */
$counts = function ( array $rows ) {
	$out = array();
	foreach ( $rows as $r ) {
		$k         = $r['taxonomy'] . '|' . $r['slug'] . '|' . $r['post_type'];
		$out[ $k ] = ( $out[ $k ] ?? 0 ) + 1;
	}
	ksort( $out );
	return $out;
};

if ( 'check' === $mode ) {
	if ( ! file_exists( $csv ) ) {
		WP_CLI::error( 'No snapshot at ' . $csv );
	}
	$fh     = fopen( $csv, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$head   = fgetcsv( $fh );
	$before = array();
	$prim   = array();
	while ( ( $row = fgetcsv( $fh ) ) ) {
		$r = array_combine( $head, $row );
		if ( 'primary' === $r['taxonomy'] ) {
			$prim[ $r['object_id'] ] = $r['slug'];
			continue;
		}
		$r['taxonomy'] = array( 'aw_topic' => 'category', 'category' => 'aw_resource_type' )[ $r['taxonomy'] ];
		$before[]      = $r['object_id'] . '|' . $r['taxonomy'] . '|' . $r['slug'];
	}
	fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$now = array_map( fn( $r ) => $r['object_id'] . '|' . $r['taxonomy'] . '|' . $r['slug'], $rows_of( array( 'category', 'aw_resource_type' ) ) );
	$lost  = array_diff( $before, $now );
	$extra = array_diff( $now, $before );
	$log( sprintf( 'Before: %d item-term links. Now: %d. Missing now: %d. New since: %d.', count( $before ), count( $now ), count( $lost ), count( $extra ) ) );
	foreach ( array_slice( $lost, 0, 20 ) as $l ) {
		$log( '  missing ' . $l );
	}
	foreach ( array_slice( $extra, 0, 20 ) as $l ) {
		$log( '  new ' . $l );
	}
	$bad = 0;
	foreach ( $prim as $id => $slug ) {
		$t = get_term( (int) get_post_meta( (int) $id, 'rank_math_primary_category', true ), 'category' );
		if ( ! $t || is_wp_error( $t ) || $t->slug !== $slug ) {
			$bad++;
			$log( "  primary category differs on $id (was $slug)" );
		}
	}
	$log( sprintf( 'Primary categories: %d before, %d differ now.', count( $prim ), $bad ) );
	return;
}

/* ---------- State and checks ---------- */

$cat_terms = $wpdb->get_results( "SELECT t.term_id, t.slug, tt.term_taxonomy_id, tt.count FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = 'category'" ); // phpcs:ignore WordPress.DB
$topic_n   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'aw_topic'" ); // phpcs:ignore WordPress.DB
$rt_n      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'aw_resource_type'" ); // phpcs:ignore WordPress.DB
$move_rt   = array_filter( $cat_terms, fn( $t ) => in_array( $t->slug, $types, true ) );
$others    = array_filter( $cat_terms, fn( $t ) => ! in_array( $t->slug, $types, true ) );
$log( sprintf( 'Now: %d terms in category (%d Resource types), %d in aw_topic, %d in aw_resource_type. Mode: %s.', count( $cat_terms ), count( $move_rt ), $topic_n, $rt_n, $dry ? 'dry run' : 'run' ) );

if ( $topic_n && $others ) {
	WP_CLI::error( 'category holds terms that are not Resource types: ' . implode( ', ', wp_list_pluck( $others, 'slug' ) ) . '. Stopped.' );
}
if ( $topic_n ) {
	$clash = $wpdb->get_col( "SELECT t.slug FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = 'aw_topic' AND t.slug IN ('" . implode( "','", $types ) . "')" ); // phpcs:ignore WordPress.DB
	if ( $clash ) {
		WP_CLI::error( 'A category has a Resource type slug: ' . implode( ', ', $clash ) . '. Stopped.' );
	}
}

/* ---------- Snapshot before ---------- */

if ( $topic_n ) {
	$before = $rows_of( array( 'aw_topic', 'category' ) );
	$log( 'Item-term links before: ' . count( $before ) );
	foreach ( $counts( $before ) as $k => $n ) {
		list( $tax, $slug, $type ) = explode( '|', $k );
		$log( sprintf( '  %-9s %-28s %-15s %5d  →  %s', $tax, $slug, $type, $n, 'aw_topic' === $tax ? 'category' : 'aw_resource_type' ) );
	}
	if ( ! $dry && ! file_exists( $csv ) ) {
		$fh = fopen( $csv, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fputcsv( $fh, array( 'object_id', 'post_type', 'post_status', 'taxonomy', 'slug' ) );
		foreach ( $before as $r ) {
			fputcsv( $fh, array_values( $r ) );
		}
		foreach ( $wpdb->get_results( "SELECT m.post_id, p.post_type, p.post_status, t.slug FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id JOIN {$wpdb->terms} t ON t.term_id = m.meta_value WHERE m.meta_key = 'aw_primary_topic'" ) as $p ) { // phpcs:ignore WordPress.DB
			fputcsv( $fh, array( $p->post_id, $p->post_type, $p->post_status, 'primary', $p->slug ) );
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$log( 'Snapshot written: ' . $csv );
	}
}

/* ---------- 1 and 2: the terms ---------- */

if ( $move_rt && $topic_n ) {
	$ids = implode( ',', array_map( 'intval', wp_list_pluck( $move_rt, 'term_taxonomy_id' ) ) );
	$log( "Resource types → aw_resource_type: term_taxonomy_id $ids" );
	if ( ! $dry ) {
		$wpdb->query( "UPDATE {$wpdb->term_taxonomy} SET taxonomy = 'aw_resource_type' WHERE taxonomy = 'category' AND term_taxonomy_id IN ($ids)" ); // phpcs:ignore WordPress.DB
	}
}
if ( $topic_n ) {
	$log( "Categories → category: $topic_n terms" );
	if ( ! $dry ) {
		$wpdb->query( "UPDATE {$wpdb->term_taxonomy} SET taxonomy = 'category' WHERE taxonomy = 'aw_topic'" ); // phpcs:ignore WordPress.DB
	}
}

/* ---------- Names ---------- */

$n = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->termmeta} WHERE meta_key = 'aw_topic_image'" ); // phpcs:ignore WordPress.DB
$log( "Term meta aw_topic_image → aw_category_image: $n" );
if ( $n && ! $dry ) {
	$wpdb->query( "UPDATE {$wpdb->termmeta} SET meta_key = 'aw_category_image' WHERE meta_key = 'aw_topic_image'" ); // phpcs:ignore WordPress.DB
}

$old = get_option( 'aw_topic_images', null );
if ( null !== $old ) {
	$log( 'Option aw_topic_images → aw_category_images' );
	if ( ! $dry ) {
		update_option( 'aw_category_images', $old, false );
		delete_option( 'aw_topic_images' ); // renamed: the value is kept under the new name
	}
}

$prim = $wpdb->get_results( "SELECT meta_id, post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'aw_primary_topic'" ); // phpcs:ignore WordPress.DB
$log( 'Primary category meta → rank_math_primary_category: ' . count( $prim ) );
foreach ( $prim as $p ) {
	if ( $dry ) {
		continue;
	}
	if ( '' === (string) get_post_meta( (int) $p->post_id, 'rank_math_primary_category', true ) ) {
		$wpdb->update( $wpdb->postmeta, array( 'meta_key' => 'rank_math_primary_category' ), array( 'meta_id' => (int) $p->meta_id ) ); // phpcs:ignore WordPress.DB
	} else {
		$wpdb->update( $wpdb->postmeta, array( 'meta_key' => '_aw_primary_category_before' ), array( 'meta_id' => (int) $p->meta_id ) ); // phpcs:ignore WordPress.DB
	}
	wp_cache_delete( (int) $p->post_id, 'post_meta' );
}

$src = get_option( 'aw_sources' );
if ( is_array( $src ) && array_key_exists( 'topics', $src ) ) {
	$log( 'Sourcing lists: topics → categories (' . count( (array) $src['topics'] ) . ' categories)' );
	if ( ! $dry ) {
		$src['categories'] = $src['topics'];
		unset( $src['topics'] );
		update_option( 'aw_sources', $src, false );
	}
}

/* ---------- Rank Math ---------- */

$six = array( 'post', 'aw_news', 'aw_legal', 'aw_campaign', 'aw_opportunity' ); // listings keep Listing type as their primary
$t   = get_option( 'rank-math-options-titles', array() );
$s   = get_option( 'rank-math-options-sitemap', array() );
$g   = get_option( 'rank-math-options-general', array() );
$t2  = $t;
$s2  = $s;
$g2  = $g;
if ( array_key_exists( 'tax_aw_topic_title', $t ) ) {
	foreach ( $t as $k => $v ) { // Resource types: what WordPress's category had (no pages, not indexed)
		if ( 0 === strpos( $k, 'tax_category_' ) ) {
			$t2[ 'tax_aw_resource_type_' . substr( $k, 13 ) ] = $v;
		}
	}
	$t2['tax_aw_resource_type_robots']      = array( 'noindex' );
	$t2['tax_aw_resource_type_add_meta_box'] = 'off';
	$t2['remove_aw_resource_type_snippet_data'] = $t['remove_category_snippet_data'] ?? 'off';
	foreach ( $t as $k => $v ) { // Categories: the templates and robots the categories had
		if ( 0 === strpos( $k, 'tax_aw_topic_' ) ) {
			$t2[ 'tax_category_' . substr( $k, 13 ) ] = $v;
			unset( $t2[ $k ] );
		}
	}
	if ( isset( $t['remove_aw_topic_snippet_data'] ) ) {
		$t2['remove_category_snippet_data'] = $t['remove_aw_topic_snippet_data'];
		unset( $t2['remove_aw_topic_snippet_data'] );
	}
}
foreach ( $six as $type ) {
	$t2[ 'pt_' . $type . '_primary_taxonomy' ] = 'category';
}
if ( array_key_exists( 'tax_aw_topic_sitemap', $s ) ) {
	$s2['tax_aw_resource_type_sitemap']       = 'off';
	$s2['tax_aw_resource_type_html_sitemap']  = 'off';
	$s2['tax_aw_resource_type_include_empty'] = 'off';
	foreach ( array( 'sitemap', 'html_sitemap', 'include_empty' ) as $k ) {
		$s2[ 'tax_category_' . $k ] = $s[ 'tax_aw_topic_' . $k ] ?? 'off';
		unset( $s2[ 'tax_aw_topic_' . $k ] );
	}
}
if ( isset( $g['llms_taxonomies'] ) && is_array( $g['llms_taxonomies'] ) ) {
	$g2['llms_taxonomies'] = array_values( array_unique( array_map( fn( $x ) => 'aw_topic' === $x ? 'category' : $x, $g['llms_taxonomies'] ) ) );
}
foreach ( array( 'titles' => array( $t, $t2 ), 'sitemap' => array( $s, $s2 ), 'general' => array( $g, $g2 ) ) as $name => $pair ) {
	$changed = array();
	foreach ( array_unique( array_merge( array_keys( $pair[0] ), array_keys( $pair[1] ) ) ) as $k ) {
		if ( ( $pair[0][ $k ] ?? null ) !== ( $pair[1][ $k ] ?? null ) ) {
			$changed[] = $k . '=' . wp_json_encode( $pair[1][ $k ] ?? '(removed)' );
		}
	}
	$log( "Rank Math $name: " . ( $changed ? implode( '; ', $changed ) : 'no change' ) );
	if ( $changed && ! $dry ) {
		update_option( 'rank-math-options-' . $name, $pair[1] );
	}
}

// Rank Math's old catch-all for WordPress category addresses (they held only Resource types) would catch /category/…
$rr = $wpdb->prefix . 'rank_math_redirections';
foreach ( $wpdb->get_results( "SELECT id, sources FROM {$rr} WHERE status = 'active' AND sources LIKE '%category%'" ) as $r ) { // phpcs:ignore WordPress.DB
	foreach ( (array) maybe_unserialize( $r->sources ) as $src ) {
		if ( 'regex' === ( $src['comparison'] ?? '' ) && 0 === strpos( (string) $src['pattern'], '^category' ) ) {
			$log( "Rank Math redirect {$r->id} ({$src['pattern']}) switched off" );
			if ( ! $dry ) {
				$wpdb->update( $rr, array( 'status' => 'inactive' ), array( 'id' => (int) $r->id ) ); // phpcs:ignore WordPress.DB
			}
		}
	}
}

/* ---------- Pages and patterns ---------- */

$page = get_page_by_path( 'topics' );
if ( $page ) {
	$log( "Page {$page->ID}: /topics/ → /categories/, [aw_topics_index] → [aw_categories_index]" );
	if ( ! $dry ) {
		$wpdb->update( $wpdb->posts, array( 'post_name' => 'categories', 'post_content' => str_replace( '[aw_topics_index]', '[aw_categories_index]', $page->post_content ) ), array( 'ID' => $page->ID ) ); // phpcs:ignore WordPress.DB
		clean_post_cache( $page->ID );
	}
}
if ( ! $dry && function_exists( 'aw_redirect_add' ) ) {
	aw_redirect_add( '/topics/', '/categories/' );
}
foreach ( $wpdb->get_results( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'wp_block' AND post_content LIKE '%\"term\":\"aw_topic\"%'" ) as $b ) { // phpcs:ignore WordPress.DB
	$log( "Synced pattern {$b->ID}: post-terms aw_topic → category" );
	if ( ! $dry ) {
		$c = (string) get_post_field( 'post_content', $b->ID, 'raw' );
		$wpdb->update( $wpdb->posts, array( 'post_content' => str_replace( '"term":"aw_topic"', '"term":"category"', $c ) ), array( 'ID' => $b->ID ) ); // phpcs:ignore WordPress.DB
		clean_post_cache( $b->ID );
	}
}

/* ---------- Counts and caches ---------- */

if ( ! $dry ) {
	update_option( 'default_category', 0 ); // no Uncategorized: nothing is filed under a default category
	foreach ( array( 'category', 'aw_resource_type' ) as $tax ) {
		$tt = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false, 'fields' => 'tt_ids' ) );
		if ( $tt && ! is_wp_error( $tt ) ) {
			wp_update_term_count_now( $tt, $tax );
		}
		clean_taxonomy_cache( $tax );
	}
	delete_option( 'category_children' ); // WordPress's own cache, rebuilt on demand
	foreach ( $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_aw\\_seo\\_topic%' OR option_name LIKE '\\_transient\\_timeout\\_aw\\_seo\\_topic%'" ) as $o ) { // phpcs:ignore WordPress.DB
		delete_option( $o ); // cached counts under the old names
	}
	wp_cache_flush();
	flush_rewrite_rules( false );
	$after = $rows_of( array( 'category', 'aw_resource_type' ) );
	$log( 'Item-term links after: ' . count( $after ) );
	foreach ( $counts( $after ) as $k => $n ) {
		$log( '  ' . str_replace( '|', '  ', $k ) . '  ' . $n );
	}
}
$log( $dry ? 'Dry run: nothing changed.' : 'Done.' );
