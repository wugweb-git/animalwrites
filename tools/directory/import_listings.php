<?php
/**
 * Directory import from public lists. Idempotent; never deletes; never overwrites a value a listing already has, except
 * from a row marked "newer" (the organisation's own website, the newest source: its values replace older ones, logged).
 * Usage: wp eval-file import_listings.php <rows.json> <offset> <limit> [<log.jsonl>]
 *
 * Row kinds:
 * - new listing: key, name, types[], state, district, area, phone, address_private, content, source_name, source_url,
 *   source_date; optional: data_source (registry slug, data-sources.php), email, website, address (a public office's, shown), founded (year), darpan_id, registration,
 *   reg_type, reg_authority, reg_act, reg_date, sectors, awbi_code / awbi_recognised / awbi_renewed / awbi_valid /
 *   awbi_list_on / awbi_dropped (date of the AWBI list it is missing from) / awbi_address (an AWBI list row; the address is kept private), services[] (AW_SERVICES slugs, added
 *   only), places[] (more [state, district] pairs), abc_valid.
 *   Matched by aw_import_key; created published and unverified.
 * - update of an existing listing: post_id, types[] (added, never removed) and any optional field above (filled only
 *   when empty), abc_centres[] (kept private), abc_valid (only a later date: a renewed recognition),
 *   phones[] ({number, label, whatsapp}: the whole list), facebook, instagram, x, youtube, donate_url, volunteer_url, newer. Verified or claimed listings are left alone (types too).
 *
 * Types are Directory tags (post_tag); a listing can have any number (owner, 18 Oct 2026). Types it already has are never removed or
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

/** Add types (Directory tags; no limit on how many); returns the slugs added. */
$add_types = function ( $id, $types ) use ( $type_slugs, $note, &$kind ) {
	$have  = wp_get_post_terms( $id, 'post_tag', array( 'fields' => 'slugs' ) );
	$count = count( array_intersect( $have, $type_slugs ) );
	$add   = array();
	foreach ( (array) $types as $t ) {
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

/** Add services (the fixed services list, AW_SERVICES): added, never removed. */
$add_services = function ( $id, $services ) use ( $note, &$kind ) {
	$ok   = function_exists( 'aw_service_slugs' ) ? aw_service_slugs() : array();
	$have = wp_get_post_terms( $id, 'post_tag', array( 'fields' => 'slugs' ) );
	$add  = array_values( array_diff( array_intersect( (array) $services, $ok ), $have ) );
	if ( $add ) {
		wp_set_object_terms( $id, $add, 'post_tag', true );
		$note( $id, 'services', array(), $add, $kind );
	}
};

/** Fill one meta field only when the listing has none; a newer source ($newer, e.g. the organisation's own website) replaces it. */
$newer = false;
$fill  = function ( $id, $key, $value ) use ( $note, &$kind, &$newer ) {
	$value = is_string( $value ) ? trim( $value ) : $value;
	$old   = trim( (string) get_post_meta( $id, $key, true ) );
	if ( '' === $value || null === $value || $old === $value || ( '' !== $old && ! $newer ) ) {
		return;
	}
	update_post_meta( $id, $key, $value );
	$note( $id, $key, $old, $value, $kind );
};

/** The optional fields a source gives. */
$fields = function ( $id, $r ) use ( $fill, $note, &$kind, &$newer ) {
	foreach ( array( 'darpan_id', 'registration', 'reg_type', 'reg_authority', 'reg_act', 'reg_date', 'sectors', 'awbi_code', 'awbi_recognised', 'awbi_renewed', 'awbi_valid', 'awbi_list_on', 'awbi_dropped', 'awbi_address' ) as $k ) {
		if ( ! empty( $r[ $k ] ) ) {
			$fill( $id, 'aw_' . $k, sanitize_text_field( is_array( $r[ $k ] ) ? implode( ', ', $r[ $k ] ) : $r[ $k ] ) );
		}
	}
	if ( ! empty( $r['address'] ) ) {
		$fill( $id, 'aw_address', sanitize_text_field( $r['address'] ) ); // a public office's address (shown)
	}
	if ( ! empty( $r['founded'] ) && preg_match( '/^(1[89]|20)\d\d$/', (string) $r['founded'] ) ) {
		$fill( $id, 'aw_founded', (string) $r['founded'] ); // year established
	}
	if ( ! empty( $r['email'] ) && is_email( $r['email'] ) ) {
		$fill( $id, 'aw_email', sanitize_email( $r['email'] ) );
	}
	if ( ! empty( $r['website'] ) && wp_http_validate_url( $r['website'] ) ) {
		$fill( $id, 'aw_website', esc_url_raw( $r['website'] ) );
	}
	foreach ( array( 'facebook', 'instagram', 'x', 'youtube', 'donate_url', 'volunteer_url' ) as $k ) {
		if ( ! empty( $r[ $k ] ) && wp_http_validate_url( $r[ $k ] ) ) {
			$fill( $id, 'aw_' . $k, esc_url_raw( $r[ $k ] ) );
		}
	}
	if ( ! empty( $r['phones'] ) && ( $newer || ! aw_listing_phones( $id ) ) ) { // [{number, label, whatsapp}]: the source's whole list
		$old = aw_listing_phones( $id );
		$new = array_map( fn( $p ) => array( 'number' => (string) $p['number'], 'label' => (string) ( $p['label'] ?? '' ), 'whatsapp' => ! empty( $p['whatsapp'] ) ), $r['phones'] );
		if ( wp_list_pluck( $old, 'number' ) !== wp_list_pluck( $new, 'number' ) || wp_list_pluck( $old, 'label' ) !== wp_list_pluck( $new, 'label' ) ) {
			aw_set_listing_list( $id, 'aw_phones', $new );
			$note( $id, 'aw_phones', $old, $new, $kind );
		}
	} elseif ( ! empty( $r['phone'] ) && ! aw_listing_phones( $id ) ) {
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
		$kind  = 'extended';
		$newer = ! empty( $r['newer'] ); // the organisation's own website: its values replace older ones (owner, 18 Oct 2026)
		$id    = (int) $r['post_id'];
		$id   = (int) get_post_meta( $id, 'aw_merged_into', true ) ? (int) get_post_meta( $id, 'aw_merged_into', true ) : $id; // merged: extend the listing it went into
		if ( 'aw_listing' !== get_post_type( $id ) ) {
			continue;
		}
		if ( aw_enrich_is_protected( $id ) ) {
			$protected++; // verified or claimed: the organisation's own details stand
			continue;
		}
		$add_types( $id, $r['types'] ?? array() );
		$add_services( $id, $r['services'] ?? array() );
		$fields( $id, $r );
		if ( ! empty( $r['source_name'] ) ) { // where the added details come from, when the listing names no source yet
			foreach ( array( 'source_name', 'source_url', 'source_date' ) as $k ) {
				$fill( $id, 'aw_' . $k, $r[ $k ] ?? '' );
			}
		}
		if ( ! empty( $r['abc_valid'] ) && empty( $r['abc_centres'] ) && $r['abc_valid'] > (string) get_post_meta( $id, 'aw_abc_valid', true ) ) {
			$note( $id, 'aw_abc_valid', (string) get_post_meta( $id, 'aw_abc_valid', true ), $r['abc_valid'], $kind ); // a renewed ABC recognition
			update_post_meta( $id, 'aw_abc_valid', $r['abc_valid'] );
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
	$add_services( $id, $r['services'] ?? array() );
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
}
wp_suspend_cache_invalidation( false );
wp_defer_term_counting( false );
fclose( $log );
delete_transient( 'aw_sitemap_dir_pairs' );
delete_transient( 'aw_seo_dir_data' );
echo "offset $offset: created $made, updated $updated, existing listings extended $attached, verified/claimed left alone $protected (log: $log_file)\n";
