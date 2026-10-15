<?php
/**
 * Places from the Local Government Directory (LGD), not from a WordPress list (8 Oct 2026).
 *
 * - Reference: data/lgd.json, a read-only copy of the LGD export (states and districts with their codes). Nobody edits
 *   it on the site; when LGD changes the file is replaced (tools/lgd/build_lgd.py) and tools/lgd/check.php lists items
 *   whose codes no longer exist. Items are never changed silently.
 * - Each item stores codes, not names: aw_state_code, aw_district_code (an opportunity may have more than one of each),
 *   optional aw_pincode and aw_place_city (free text, display only, never a page).
 * - Entering a place: a PIN code fills State and District (India Post, matched to LGD names, with a spelling map);
 *   without a PIN, State → District dropdowns built from the file. Codes that are not in the file are not saved.
 * - Directory pages /directory/{state}/{district}/ read the slug from the file; URL names are the ones the WordPress
 *   location list had, so every URL stays the same. Counts are stored and recalculated when an item is saved.
 * - The WordPress location list (taxonomy aw_city) was copied into the codes once and is no longer registered;
 *   a backup of its terms and links is in uploads/aw-private/locations-backup-2026-10-08.json.
 *
 * A place is an object shaped like a term: term_id (the ID it had in the location list, kept so volunteer records and
 * caches stay valid), code, slug, name, parent (the state's term_id for a district, 0 for a state), state (state code).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Post types that can have a place. */
const AW_PLACE_TYPES = array( 'aw_listing', 'aw_opportunity', 'aw_campaign', 'aw_news', 'aw_legal' );

/** True once items carry LGD codes (the one-time move has run). */
function aw_places_ready() {
	return '1' === get_option( 'aw_places_migrated' );
}

/* ---------- Reference data ---------- */

/** The LGD file, indexed. */
function aw_lgd() {
	static $d = null;
	if ( null !== $d ) {
		return $d;
	}
	$raw = json_decode( (string) file_get_contents( AW_CORE_DIR . 'data/lgd.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$d   = array( 'states' => array(), 'districts' => array(), 'slugs' => array(), 'ids' => array(), 'by_state' => array(), 'aliases' => $raw['aliases'] ?? array(), 'version' => $raw['version'] ?? '' );
	foreach ( (array) ( $raw['states'] ?? array() ) as $s ) {
		$p = (object) array( 'term_id' => (int) ( $s['id'] ? $s['id'] : 900000 + $s['code'] ), 'code' => (int) $s['code'], 'slug' => $s['slug'], 'name' => $s['name'], 'lgd' => $s['lgd'], 'parent' => 0, 'state' => (int) $s['code'], 'kind' => 'state', 'taxonomy' => 'aw_place' );
		$d['states'][ $p->code ] = $p;
	}
	foreach ( (array) ( $raw['districts'] ?? array() ) as $s ) {
		$st = $d['states'][ (int) $s['state'] ] ?? null;
		if ( ! $st ) {
			continue;
		}
		$p = (object) array( 'term_id' => (int) ( $s['id'] ? $s['id'] : 1000000 + $s['code'] ), 'code' => (int) $s['code'], 'slug' => $s['slug'], 'name' => $s['name'], 'lgd' => $s['lgd'], 'parent' => $st->term_id, 'state' => $st->code, 'kind' => 'district', 'taxonomy' => 'aw_place' );
		$d['districts'][ $p->code ]          = $p;
		$d['by_state'][ $st->code ][ $p->code ] = $p;
	}
	foreach ( array_merge( $d['states'], $d['districts'] ) as $p ) {
		$d['slugs'][ $p->slug ]  = $p;
		$d['ids'][ $p->term_id ] = $p;
	}
	uasort( $d['states'], fn( $a, $b ) => strcmp( $a->name, $b->name ) );
	foreach ( $d['by_state'] as &$list ) {
		uasort( $list, fn( $a, $b ) => strcmp( $a->name, $b->name ) );
	}
	return $d;
}

/** States, A–Z. */
function aw_place_states() {
	return aw_lgd()['states'];
}

/** A state's districts, A–Z. */
function aw_place_districts( $state_code ) {
	return aw_lgd()['by_state'][ (int) $state_code ] ?? array();
}

function aw_place_state( $code ) {
	return aw_lgd()['states'][ (int) $code ] ?? null;
}

function aw_place_district( $code ) {
	return aw_lgd()['districts'][ (int) $code ] ?? null;
}

/** A place by URL name (state or district). */
function aw_place_by_slug( $slug ) {
	return aw_lgd()['slugs'][ (string) $slug ] ?? null;
}

/** A place by its ID (the old location-list ID). */
function aw_place_by_id( $id ) {
	return aw_lgd()['ids'][ (int) $id ] ?? null;
}

/** The state a place belongs to (itself for a state). */
function aw_place_parent_state( $place ) {
	return $place ? aw_place_state( $place->state ) : null;
}

/** Comparison key for names: letters only, lower case, "&" read as "and", spelling map applied. */
function aw_place_key( $name, $kind = 'district' ) {
	$k = trim( preg_replace( '/\s+/', ' ', preg_replace( '/[^a-z ]/', ' ', strtolower( str_replace( '&', 'and', (string) $name ) ) ) ) );
	$k = preg_replace( '/ (district|dist)$/', '', $k );
	$a = aw_lgd()['aliases'][ $kind ] ?? array();
	return str_replace( ' ', '', $a[ $k ] ?? $k );
}

/** A state by name (LGD or display name, or a known other spelling). */
function aw_place_state_by_name( $name ) {
	$k = aw_place_key( $name, 'state' );
	foreach ( aw_place_states() as $s ) {
		if ( $k && ( aw_place_key( $s->name, 'state' ) === $k || aw_place_key( $s->lgd, 'state' ) === $k ) ) {
			return $s;
		}
	}
	return null;
}

/** A district by name, inside a state when one is given; null when the name is unknown or not unique. */
function aw_place_district_by_name( $name, $state = null ) {
	$k = aw_place_key( $name );
	if ( '' === $k ) {
		return null;
	}
	$hits = array();
	foreach ( $state ? aw_place_districts( $state->code ) : aw_lgd()['districts'] as $d ) {
		if ( aw_place_key( $d->name ) === $k || aw_place_key( $d->lgd ) === $k ) {
			$hits[ $d->code ] = $d;
		}
	}
	return 1 === count( $hits ) ? reset( $hits ) : null;
}

/* ---------- Places of an item ---------- */

/** Codes stored on an item: [state codes], [district codes]. */
function aw_item_codes( $id ) {
	return array(
		array_values( array_unique( array_map( 'intval', (array) get_post_meta( $id, 'aw_state_code' ) ) ) ),
		array_values( array_unique( array_map( 'intval', (array) get_post_meta( $id, 'aw_district_code' ) ) ) ),
	);
}

/** An item's places, most precise first: its districts, then any state that has none of them. */
function aw_item_places( $id ) {
	list( $states, $districts ) = aw_item_codes( $id );
	$out = array();
	$has = array();
	foreach ( $districts as $c ) {
		$d = aw_place_district( $c );
		if ( $d ) {
			$out[]               = $d;
			$has[ $d->state ] = true;
		}
	}
	foreach ( $states as $c ) {
		$s = aw_place_state( $c );
		if ( $s && empty( $has[ $s->code ] ) ) {
			$out[] = $s;
		}
	}
	return $out;
}

/** The place shown for an item: its district if it has one, else its state. */
function aw_item_place( $id ) {
	$p = aw_item_places( $id );
	return $p ? $p[0] : null;
}

/** State and district names of an item. */
function aw_item_place_names( $id ) {
	$p = aw_item_place( $id );
	if ( ! $p ) {
		return array( '', '' );
	}
	return 'district' === $p->kind ? array( aw_place_state( $p->state )->name, $p->name ) : array( $p->name, '' );
}

/**
 * Set an item's places. Each entry: [state code, district code or 0]. Codes that are not in the LGD file, or a
 * district outside its state, are refused (returns false and changes nothing).
 *
 * @param int    $id      Post ID.
 * @param array  $places  List of [state, district].
 * @param string $pincode Optional 6-digit PIN code ('' to clear, null to leave as is).
 * @param string $city    Optional free-text city (null to leave as is).
 * @return bool
 */
function aw_set_item_places( $id, $places, $pincode = null, $city = null ) {
	$states    = array();
	$districts = array();
	foreach ( $places as $pl ) {
		$s = aw_place_state( (int) ( $pl[0] ?? 0 ) );
		$d = ! empty( $pl[1] ) ? aw_place_district( (int) $pl[1] ) : null;
		if ( ! $s && $d ) {
			$s = aw_place_state( $d->state );
		}
		if ( ! $s || ( ! empty( $pl[1] ) && ( ! $d || $d->state !== $s->code ) ) ) {
			return false;
		}
		$states[ $s->code ] = $s->code;
		if ( $d ) {
			$districts[ $d->code ] = $d->code;
		}
	}
	if ( null !== $pincode && '' !== $pincode && ! preg_match( '/^[1-9]\d{5}$/', $pincode ) ) {
		return false;
	}
	delete_post_meta( $id, 'aw_state_code' );
	delete_post_meta( $id, 'aw_district_code' );
	foreach ( $states as $c ) {
		add_post_meta( $id, 'aw_state_code', $c );
	}
	foreach ( $districts as $c ) {
		add_post_meta( $id, 'aw_district_code', $c );
	}
	if ( null !== $pincode ) {
		'' === $pincode ? delete_post_meta( $id, 'aw_pincode' ) : update_post_meta( $id, 'aw_pincode', $pincode );
	}
	if ( null !== $city ) {
		'' === trim( $city ) ? delete_post_meta( $id, 'aw_place_city' ) : update_post_meta( $id, 'aw_place_city', sanitize_text_field( $city ) );
	}
	aw_place_counts_reset();
	return true;
}

/**
 * Places named in free text ("Pune/Hyderabad", "Bhopal, Madhya Pradesh", "Kerala"): every part that names a known
 * district or state. Returns a list of [state code, district code or 0].
 */
function aw_places_from_text( $text ) {
	$out   = array();
	$parts = array_filter( array_map( 'trim', preg_split( '#\s*[/,&;]\s*|\s+and\s+#i', (string) $text ) ) );
	$state = null;
	foreach ( $parts as $part ) {
		$state = $state ? $state : aw_place_state_by_name( $part );
	}
	foreach ( $parts as $part ) {
		$d = aw_place_district_by_name( $part, $state ) ?? aw_place_district_by_name( $part );
		if ( $d ) {
			$out[ 'd' . $d->code ] = array( $d->state, $d->code );
		}
	}
	if ( ! $out && $state ) {
		$out[] = array( $state->code, 0 );
	}
	return array_values( $out );
}

/* ---------- Counts (stored; recalculated after an item is saved) ---------- */

/** Mark the counts out of date; they are rebuilt on the next read. */
function aw_place_counts_reset() {
	delete_option( 'aw_place_counts' );
	delete_transient( 'aw_sitemap_dir_pairs' );
	delete_transient( 'aw_stats' );
}

/**
 * Published listings per state code and per district code, and district × type pairs.
 *
 * @return array{state: array<int,int>, district: array<int,int>, pairs: array}
 */
function aw_place_counts() {
	$c = get_option( 'aw_place_counts' );
	if ( is_array( $c ) && isset( $c['state'] ) ) {
		return $c;
	}
	global $wpdb;
	$c    = array( 'state' => array(), 'district' => array(), 'pairs' => array(), 'at' => time() );
	$skip = function_exists( 'kits_preview_only_ids' ) && kits_preview_only_ids() ? ' AND p.ID NOT IN (' . implode( ',', kits_preview_only_ids() ) . ')' : ''; // preview-only items (integers)
	foreach ( array( 'state' => 'aw_state_code', 'district' => 'aw_district_code' ) as $k => $meta ) {
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT pm.meta_value code, COUNT(DISTINCT p.ID) n FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = 'aw_listing' AND p.post_status = 'publish'{$skip} WHERE pm.meta_key = %s GROUP BY pm.meta_value", $meta ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( $rows as $r ) {
			$c[ $k ][ (int) $r->code ] = (int) $r->n;
		}
	}
	$types = "'" . implode( "','", array_map( 'esc_sql', aw_listing_type_slugs() ) ) . "'"; // the listing types (not the services)
	$rows = $wpdb->get_results( "SELECT pm.meta_value code, t.slug type, MAX(p.post_modified_gmt) modified, COUNT(DISTINCT p.ID) n FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = 'aw_district_code' JOIN {$wpdb->term_relationships} rt ON rt.object_id = p.ID JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = rt.term_taxonomy_id AND tt.taxonomy = 'post_tag' JOIN {$wpdb->terms} t ON t.term_id = tt.term_id AND t.slug IN ({$types}) WHERE p.post_type = 'aw_listing' AND p.post_status = 'publish'{$skip} GROUP BY pm.meta_value, t.slug", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	foreach ( $rows as $r ) {
		$c['pairs'][] = array( (int) $r['code'], $r['type'], $r['modified'], (int) $r['n'] );
	}
	update_option( 'aw_place_counts', $c, false );
	return $c;
}

/** Listings in a place (a state's districts included). */
function aw_place_count( $place ) {
	if ( ! $place ) {
		return 0;
	}
	$c = aw_place_counts();
	return (int) ( $c[ 'state' === $place->kind ? 'state' : 'district' ][ $place->code ] ?? 0 );
}

add_action(
	'save_post',
	function ( $id, $post ) {
		if ( in_array( $post->post_type, AW_PLACE_TYPES, true ) ) {
			aw_place_counts_reset();
		}
	},
	10,
	2
);
add_action( 'deleted_post', 'aw_place_counts_reset' );
add_action( 'set_object_terms', fn( $id, $terms, $tt, $tax ) => 'post_tag' === $tax && 'aw_listing' === get_post_type( $id ) && aw_place_counts_reset(), 10, 4 );

/* ---------- Directory and list queries ---------- */

add_filter(
	'query_vars',
	function ( $v ) {
		$v[] = 'aw_place';
		return $v;
	}
);

/** The place the current directory page shows, if any. */
function aw_current_place() {
	$s = (string) get_query_var( 'aw_place' );
	return $s ? aw_place_by_slug( $s ) : null;
}

/** Meta clause for items in a place (a state includes its districts: every item stores its state code too). */
function aw_place_meta_clause( $place ) {
	return 'state' === $place->kind
		? array( 'key' => 'aw_state_code', 'value' => (string) $place->code )
		: array( 'key' => 'aw_district_code', 'value' => (string) $place->code );
}

/* ---------- Old URLs ---------- */

// The location list had archive pages at /location/{slug}/: each goes to its directory page.
// Issuers had pages at /source/{slug}/: an issuer is a label now, so they go to the Library.
add_action(
	'init',
	function () {
		add_rewrite_rule( '^location/([^/]+)/?$', 'index.php?aw_old_location=$matches[1]', 'top' );
		add_rewrite_tag( '%aw_old_location%', '([^/]+)' );
		add_rewrite_rule( '^source/([^/]+)/?$', 'index.php?aw_old_issuer=$matches[1]', 'top' );
		add_rewrite_tag( '%aw_old_issuer%', '([^/]+)' );
		if ( '2' !== get_option( 'aw_places_rewrite' ) ) {
			flush_rewrite_rules( false );
			update_option( 'aw_places_rewrite', '2', true );
		}
	},
	30
);
add_action(
	'template_redirect',
	function () {
		if ( '' !== (string) get_query_var( 'aw_old_issuer' ) ) {
			wp_safe_redirect( home_url( '/library/' ), 301, 'Animal Writes' );
			exit;
		}
		$slug = (string) get_query_var( 'aw_old_location' );
		if ( '' !== $slug ) {
			$p = aw_place_by_slug( sanitize_title( $slug ) );
			wp_safe_redirect( $p ? aw_directory_url( $p->slug ) : home_url( '/directory/' ), 301, 'Animal Writes' );
			exit;
		}
	},
	1
);

/* ---------- PIN code → State and District (India Post, matched to LGD) ---------- */

/**
 * Look up a PIN code: India Post gives the post office's district and state names; they are matched to the LGD file.
 * Returns [state, district, state_slug, district_slug, state_name, district_name, note] or an error message.
 *
 * @param string $pin PIN code.
 * @return array|string
 */
function aw_pin_lookup( $pin ) {
	$pin = preg_replace( '/\D/', '', (string) $pin );
	if ( ! preg_match( '/^[1-9]\d{5}$/', $pin ) ) {
		return 'Enter a 6-digit PIN code, like 682011';
	}
	$data = get_transient( 'aw_pin_' . $pin );
	if ( false === $data ) {
		$r = wp_remote_get( 'https://api.postalpincode.in/pincode/' . $pin, array( 'timeout' => 8 ) );
		if ( is_wp_error( $r ) || 200 !== wp_remote_retrieve_response_code( $r ) ) {
			return 'Couldn\'t find this PIN. Choose your state and district instead.';
		}
		$data = json_decode( wp_remote_retrieve_body( $r ), true );
		set_transient( 'aw_pin_' . $pin, $data, WEEK_IN_SECONDS );
	}
	$po       = $data[0]['PostOffice'][0] ?? null;
	$state    = $po ? aw_place_state_by_name( (string) ( $po['State'] ?? '' ) ) : null;
	$district = $state ? aw_place_district_by_name( (string) ( $po['District'] ?? '' ), $state ) : null;
	if ( ! $state ) {
		return 'Couldn\'t find this PIN. Choose your state and district instead.';
	}
	return array(
		'state'         => $state->code,
		'district'      => $district ? $district->code : 0,
		'state_slug'    => $state->slug,
		'district_slug' => $district ? $district->slug : '',
		'state_name'    => $state->name,
		'district_name' => $district ? $district->name : '',
		'note'          => $district ? '' : 'Choose your district.',
	);
}

// The same lookup for public forms (volunteer, claim): no login, a few per minute per visitor.
add_action( 'wp_ajax_nopriv_aw_pin', 'aw_pin_public' );
add_action( 'wp_ajax_aw_pin', 'aw_pin_public' );
function aw_pin_public() {
	nocache_headers();
	$key = 'aw_pin_rl_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$n   = (int) get_transient( $key );
	if ( $n >= 30 ) {
		wp_send_json_error( 'Too many tries. Wait a minute, then try again.' );
	}
	set_transient( $key, $n + 1, 10 * MINUTE_IN_SECONDS );
	$r = aw_pin_lookup( (string) wp_unslash( $_GET['pin'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	is_array( $r ) ? wp_send_json_success( $r ) : wp_send_json_error( $r );
}

/** State and District fields from the LGD file (District follows State; a PIN field fills both). */
function aw_place_fields( $form, $state_slug = '', $district_slug = '', $required_state = true ) {
	$s_opts = array( '' => 'Choose a state' );
	foreach ( aw_place_states() as $st ) {
		$s_opts[ $st->slug ] = $st->name;
	}
	$state  = aw_form_state( $form );
	$cur_s  = (string) ( $state['values']['state'] ?? $state_slug );
	$cur_d  = (string) ( $state['values']['district'] ?? $district_slug );
	$st_obj = $cur_s ? aw_place_by_slug( $cur_s ) : null;
	$html   = kits_dict_field( $form, 'state', array( 'required' => $required_state, 'options' => $s_opts, 'attrs' => array( 'data-district-select' => aw_field_id( $form, 'district' ) ) ) );
	$id     = aw_field_id( $form, 'district' );
	$opts   = '<option value="">' . ( $st_obj ? 'Choose a district' : 'Choose a state first' ) . '</option>';
	foreach ( aw_place_states() as $st ) {
		$on    = $st_obj && $st_obj->code === $st->code;
		$opts .= '<optgroup label="' . esc_attr( $st->name ) . '" data-state="' . esc_attr( $st->slug ) . '"' . ( $on ? '' : ' hidden disabled' ) . '>';
		foreach ( aw_place_districts( $st->code ) as $d ) {
			$opts .= '<option value="' . esc_attr( $d->slug ) . '"' . selected( $cur_d, $d->slug, false ) . '>' . esc_html( $d->name ) . '</option>';
		}
		$opts .= '</optgroup>';
	}
	$err   = $state['errors']['district'] ?? '';
	$html .= '<div class="kit-field kit-field--select' . ( $err ? ' is-invalid' : '' ) . '"><label for="' . esc_attr( $id ) . '">District <span class="kit-optional">(optional)</span></label><div class="kit-field-control"><select id="' . esc_attr( $id ) . '" name="district" autocomplete="address-level2" data-none="Choose a state first" data-all="Choose a district" aria-describedby="' . esc_attr( $id ) . '-error"' . ( $st_obj ? '' : ' disabled' ) . '>' . $opts . '</select></div><span class="kit-field-message kit-field-error" id="' . esc_attr( $id ) . '-error" aria-live="polite">' . esc_html( $err ) . '</span></div>';
	return $html;
}

/* ---------- Display ---------- */

/** [aw_place_links] An item's places, each linked to its directory page (item pages). */
add_shortcode(
	'aw_place_links',
	function () {
		$id    = get_the_ID();
		$links = array();
		foreach ( aw_item_places( $id ) as $p ) {
			$label   = 'district' === $p->kind ? $p->name . ', ' . aw_place_state( $p->state )->name : $p->name;
			$links[] = function_exists( 'kits_chip' ) ? kits_chip( $label, aw_directory_url( $p->slug ) ) : '<a href="' . esc_url( aw_directory_url( $p->slug ) ) . '">' . esc_html( $label ) . '</a>';
		}
		return $links ? '<div class="taxonomy-aw_place wp-block-post-terms kit-tags">' . implode( '', $links ) . '</div>' : '';
	}
);

/* ---------- One-time move from the WordPress location list ---------- */

/**
 * Copy every item's locations into the code fields and back up the old list. Runs once, before any query.
 * Matching is by the old term ID (each is in lgd.json); a district-only item also gets its state's code.
 */
function aw_places_migrate() {
	global $wpdb;
	if ( '1' === get_option( 'aw_places_migrated' ) || get_transient( 'aw_places_migrating' ) ) {
		return;
	}
	set_transient( 'aw_places_migrating', 1, 5 * MINUTE_IN_SECONDS );
	$rows = $wpdb->get_results( "SELECT tr.object_id id, tt.term_id, tt.parent, t.slug, t.name, tr.term_order FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'aw_city' JOIN {$wpdb->terms} t ON t.term_id = tt.term_id" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$terms = $wpdb->get_results( "SELECT t.term_id, t.slug, t.name, tt.parent, tt.count FROM {$wpdb->term_taxonomy} tt JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE tt.taxonomy = 'aw_city'", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	// Backup first (private folder, not served).
	$dir = aw_private_dir();
	$ok  = file_put_contents( $dir . '/locations-backup-2026-10-08.json', wp_json_encode( array( 'terms' => $terms, 'links' => $rows ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! $ok ) {
		delete_transient( 'aw_places_migrating' );
		return;
	}
	$per       = array();
	$unmatched = array();
	foreach ( $rows as $r ) {
		$p = aw_place_by_id( (int) $r->term_id );
		if ( ! $p ) {
			$unmatched[] = array( (int) $r->id, $r->slug );
			continue;
		}
		$per[ (int) $r->id ]['s'][ $p->state ] = $p->state;
		if ( 'district' === $p->kind ) {
			$per[ (int) $r->id ]['d'][ $p->code ] = $p->code;
		}
	}
	$done = $wpdb->get_col( "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('aw_state_code','aw_district_code')" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$done = array_flip( array_map( 'intval', $done ) );
	$vals = array();
	foreach ( $per as $id => $c ) {
		if ( isset( $done[ $id ] ) ) {
			continue; // already has codes
		}
		foreach ( $c['s'] ?? array() as $code ) {
			$vals[] = $wpdb->prepare( '(%d,%s,%s)', $id, 'aw_state_code', (string) $code );
		}
		foreach ( $c['d'] ?? array() as $code ) {
			$vals[] = $wpdb->prepare( '(%d,%s,%s)', $id, 'aw_district_code', (string) $code );
		}
	}
	foreach ( array_chunk( $vals, 500 ) as $chunk ) {
		$wpdb->query( "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES " . implode( ',', $chunk ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	}
	wp_cache_flush();
	update_option( 'aw_places_unmatched', $unmatched, false );
	update_option( 'aw_places_migrated', '1', true );
	aw_place_counts_reset();
	delete_transient( 'aw_places_migrating' );
}
add_action( 'init', 'aw_places_migrate', 0 );

/**
 * State and district names of a listing.
 *
 * @param int $id Post ID.
 * @return array{0: string, 1: string} state, district.
 */
function aw_listing_place( $id ) {
	return aw_item_place_names( $id ); // LGD places (places.php)
}
