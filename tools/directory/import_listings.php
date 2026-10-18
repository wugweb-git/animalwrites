<?php
/**
 * Directory import from public lists. Idempotent; never deletes; never overwrites a value a listing already has.
 * Usage: wp eval-file import_listings.php <rows.json> <offset> <limit> [<log.jsonl>]
 *
 * Row kinds:
 * - new listing: key, name, types[], state, district, area, phone, address_private, content, source_name, source_url,
 *   source_date; optional: data_source (registry slug, data-sources.php), email, website, darpan_id, registration,
 *   reg_type, reg_authority, reg_act, reg_date, sectors, places[] (more [state, district] pairs), abc_valid.
 *   Matched by aw_import_key; created published and unverified.
 * - update of an existing listing: post_id, types[] (added, never removed) and any optional field above (filled only
 *   when empty), abc_centres[] (kept private). Verified or claimed listings are left alone (types too).
 *
 * Types are Directory tags (post_tag). A listing has at most 3 types; types it already has are never removed or
 * reordered. Every change is appended to the log (one JSON object per line: post, field, old, new, kind) for
 * undo_import.php.
 */
list( $file, $offset, $limit ) = array( $args[0], (int) $args[1], (int) $args[2] );
$log_file = $args[3] ?? preg_replace( '/\.json$/', '', $file ) . '.log.jsonl';
$rows     = array_slice( json_decode( file_get_contents( $file ), true ), $offset, $limit );
$log      = fopen( $log_file, 'a' );
$note     = function ( $id, $field, $old, $new, $kind ) use ( $log ) {
	fwrite( $log, wp_json_encode( array( 'post' => $id, 'field' => $field, 'old' => $old, 'new' => $new, 'kind' => $kind ) ) . "\n" );
};
$type_slugs = aw_listing_type_slugs();

/** Add types (Directory tags) up to 3 in all; returns the slugs added. */
$add_types = function ( $id, $types ) use ( $type_slugs, $note, &$kind ) {
	$have  = wp_get_post_terms( $id, 'post_tag', array( 'fields' => 'slugs' ) );
	$count = count( array_intersect( $have, $type_slugs ) );
	$add   = array();
	foreach ( (array) $types as $t ) {
		if ( $count >= 3 ) {
			break;
		}
		if ( in_array( $t, $have, true ) || ! in_array( $t, $type_slugs, true ) ) {
			continue; // already there, or not a Directory type (never invented here)
		}
		$add[] = $t;
		$count++;
	}
	if ( $add ) {
		wp_set_object_terms( $id, $add, 'post_tag', true ); // append
		$note( $id, 'types', array(), $add, $kind );
	}
	return $add;
};

/** Fill one meta field only when the listing has none. */
$fill = function ( $id, $key, $value ) use ( $note, &$kind ) {
	$value = is_string( $value ) ? trim( $value ) : $value;
	if ( '' === $value || null === $value || '' !== trim( (string) get_post_meta( $id, $key, true ) ) ) {
		return;
	}
	update_post_meta( $id, $key, $value );
	$note( $id, $key, '', $value, $kind );
};

/** The optional fields a source gives. */
$fields = function ( $id, $r ) use ( $fill, $note, &$kind ) {
	foreach ( array( 'darpan_id', 'registration', 'reg_type', 'reg_authority', 'reg_act', 'reg_date', 'sectors' ) as $k ) {
		if ( ! empty( $r[ $k ] ) ) {
			$fill( $id, 'aw_' . $k, sanitize_text_field( is_array( $r[ $k ] ) ? implode( ', ', $r[ $k ] ) : $r[ $k ] ) );
		}
	}
	if ( ! empty( $r['email'] ) && is_email( $r['email'] ) ) {
		$fill( $id, 'aw_email', sanitize_email( $r['email'] ) );
	}
	if ( ! empty( $r['website'] ) && wp_http_validate_url( $r['website'] ) ) {
		$fill( $id, 'aw_website', esc_url_raw( $r['website'] ) );
	}
	if ( ! empty( $r['phone'] ) && ! aw_listing_phones( $id ) ) {
		aw_listing_put_phone( $id, $r['phone'] ); // into the Phone numbers list (only when it has none)
		$note( $id, 'aw_phones', '', $r['phone'], $kind );
	}
	if ( ! empty( $r['data_source'] ) ) {
		$srcs = array_values( array_filter( (array) get_post_meta( $id, 'aw_data_sources', true ) ) );
		if ( ! in_array( $r['data_source'], $srcs, true ) ) {
			update_post_meta( $id, 'aw_data_sources', array_merge( $srcs, array( $r['data_source'] ) ) );
			$note( $id, 'aw_data_sources', $srcs, $r['data_source'], $kind );
		}
	}
};

wp_defer_term_counting( true );
wp_suspend_cache_invalidation( true );
$made = $updated = $attached = $protected = 0;
foreach ( $rows as $r ) {
	if ( ! empty( $r['post_id'] ) ) {
		$kind = 'extended';
		$id   = (int) $r['post_id'];
		$id   = (int) get_post_meta( $id, 'aw_merged_into', true ) ? (int) get_post_meta( $id, 'aw_merged_into', true ) : $id; // merged: extend the listing it went into
		if ( 'aw_listing' !== get_post_type( $id ) ) {
			continue;
		}
		if ( aw_enrich_is_protected( $id ) ) {
			$protected++; // verified or claimed: the organisation's own details stand
			continue;
		}
		$add_types( $id, $r['types'] ?? array() );
		$fields( $id, $r );
		if ( ! empty( $r['source_name'] ) ) { // where the added details come from, when the listing names no source yet
			foreach ( array( 'source_name', 'source_url', 'source_date' ) as $k ) {
				$fill( $id, 'aw_' . $k, $r[ $k ] ?? '' );
			}
		}
		if ( ! empty( $r['abc_centres'] ) ) {
			update_post_meta( $id, 'aw_abc_centres', wp_json_encode( $r['abc_centres'] ) ); // private: centre addresses
			update_post_meta( $id, 'aw_abc_valid', max( array_column( $r['abc_centres'], 'valid' ) ) );
		}
		$attached++;
		continue;
	}
	$have = get_posts( array( 'post_type' => 'aw_listing', 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ), 'meta_key' => 'aw_import_key', 'meta_value' => $r['key'], 'fields' => 'ids', 'posts_per_page' => 1 ) );
	$post = array(
		'post_type'    => 'aw_listing',
		'post_title'   => $r['name'],
		'post_content' => '<!-- wp:paragraph --><p>' . esc_html( $r['content'] ) . '</p><!-- /wp:paragraph -->',
	);
	if ( $have && get_post_meta( $have[0], 'aw_merged_into', true ) ) {
		continue; // merged into another listing (a draft with a 301): never updated or made again
	}
	if ( $have ) {
		$kind = 'updated';
		$id   = $have[0];
		if ( aw_enrich_is_protected( $id ) ) {
			$protected++;
			continue;
		}
		$post['ID'] = $id;
		wp_update_post( $post ); // keeps its status
		$updated++;
	} else {
		$kind                = 'created';
		$post['post_status'] = 'publish';
		$id                  = wp_insert_post( $post );
		if ( $id && ! is_wp_error( $id ) ) {
			$note( $id, 'post', '', $r['key'], 'created' );
		}
		$made++;
	}
	if ( ! $id || is_wp_error( $id ) ) {
		continue;
	}
	// Place: LGD codes (rows carry state and district codes); unknown codes are refused, never added.
	$places = array_merge( array( array( (int) $r['state'], (int) $r['district'] ) ), (array) ( $r['places'] ?? array() ) );
	if ( ! aw_set_item_places( $id, $places ) && ! aw_set_item_places( $id, array( $places[0] ) ) ) {
		WP_CLI::warning( 'Place not in LGD for ' . $r['name'] . ': ' . $r['state'] . '/' . $r['district'] );
	}
	$add_types( $id, $r['types'] );
	update_post_meta( $id, 'aw_import_key', $r['key'] );
	update_post_meta( $id, 'aw_area', $r['area'] );
	foreach ( array( 'source_name', 'source_url', 'source_date' ) as $k ) {
		update_post_meta( $id, 'aw_' . $k, $r[ $k ] );
	}
	$fields( $id, $r );
	if ( ! empty( $r['address_private'] ) ) {
		update_post_meta( $id, 'aw_import_address', $r['address_private'] ); // for checking claims; not shown publicly
	}
	if ( ! empty( $r['abc_valid'] ) ) {
		update_post_meta( $id, 'aw_abc_valid', $r['abc_valid'] );
	}
	if ( ! metadata_exists( 'post', $id, 'aw_verified' ) ) {
		update_post_meta( $id, 'aw_verified', false );
	}
	if ( ! metadata_exists( 'post', $id, 'aw_featured' ) ) {
		update_post_meta( $id, 'aw_featured', 0 );
	}
}
wp_suspend_cache_invalidation( false );
wp_defer_term_counting( false );
fclose( $log );
delete_transient( 'aw_sitemap_dir_pairs' );
delete_transient( 'aw_seo_dir_data' );
echo "offset $offset: created $made, updated $updated, existing listings extended $attached, verified/claimed left alone $protected (log: $log_file)\n";
