<?php
/**
 * Directory snapshot for the LGD move (read-only): every place page's listing IDs, every district × type count,
 * opportunity places and the directory sitemap URLs. Prints JSON.
 *   wp eval-file tools/lgd/snapshot.php > before.json
 * Works on both models: the WordPress location list (before) and the LGD codes (after).
 */
global $wpdb;
$out = array( 'places' => array(), 'pairs' => array(), 'opps' => array(), 'urls' => array() );
$lgd = function_exists( 'aw_places_ready' ) && aw_places_ready();
if ( $lgd ) {
	foreach ( $wpdb->get_results( "SELECT pm.post_id id, pm.meta_key k, pm.meta_value v, p.post_type t FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_status = 'publish' WHERE pm.meta_key IN ('aw_state_code','aw_district_code')" ) as $r ) {
		$pl = 'aw_state_code' === $r->k ? aw_place_state( (int) $r->v ) : aw_place_district( (int) $r->v );
		if ( ! $pl ) { $out['unknown'][] = array( $r->id, $r->k, $r->v ); continue; }
		if ( 'aw_listing' === $r->t ) { $out['places'][ $pl->slug ][] = (int) $r->id; } else { $out['opps'][ (int) $r->id ][] = $pl->slug; }
	}
} else {
	foreach ( $wpdb->get_results( "SELECT tr.object_id id, t.slug, tt.parent, p.post_type t FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'aw_city' JOIN {$wpdb->terms} t ON t.term_id = tt.term_id JOIN {$wpdb->posts} p ON p.ID = tr.object_id AND p.post_status = 'publish'" ) as $r ) {
		if ( 'aw_listing' === $r->t ) {
			$out['places'][ $r->slug ][] = (int) $r->id;
			if ( $r->parent ) { // a district's listings also count in its state
				$st = get_term( (int) $r->parent, 'aw_city' );
				$out['places'][ $st->slug ][] = (int) $r->id;
			}
		} else {
			$out['opps'][ (int) $r->id ][] = $r->slug;
		}
	}
}
foreach ( $out['places'] as $k => $ids ) { $ids = array_values( array_unique( $ids ) ); sort( $ids ); $out['places'][ $k ] = $ids; }
foreach ( $out['opps'] as $k => $s ) { $s = array_values( array_unique( $s ) ); sort( $s ); $out['opps'][ $k ] = $s; }
ksort( $out['places'] ); ksort( $out['opps'] );
delete_transient( 'aw_sitemap_dir_pairs' );
foreach ( aw_sitemap_directory_pairs() as $p ) { $out['pairs'][] = $p['city'] . '/' . $p['type'] . '=' . $p['n']; }
$urls = array();
$prov = wp_get_sitemap_providers();
foreach ( $prov as $name => $pv ) {
	foreach ( array_keys( $pv->get_object_subtypes() ?: array( '' => 1 ) ) as $sub ) {
		$max = $pv->get_max_num_pages( $sub );
		for ( $i = 1; $i <= $max; $i++ ) {
			foreach ( $pv->get_url_list( $i, $sub ) as $u ) {
				if ( false !== strpos( $u['loc'], '/directory/' ) ) { $urls[] = $u['loc']; }
			}
		}
	}
}
sort( $urls );
$out['urls'] = $urls;
echo wp_json_encode( $out );
