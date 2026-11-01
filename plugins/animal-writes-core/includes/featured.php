<?php
/**
 * "Featured in" (owner, 18 Oct 2026): the news, Library items, resources, press coverage and campaigns an organisation
 * appears in, linked from its Directory page by title, and both ways in the schema (the organisation's subjectOf, the
 * item's mentions).
 *
 * - By hand: "Organisations featured" (aw_rel_org) in an item's Related box (model.php).
 * - Found automatically when an item is saved (aw_rel_org_auto; aw_featured_scan): a distinctive name (2+ words, 12+
 *   letters, one listing, not generic); an editor's alias ("Also known as", aw_aliases, any length); a link to the
 *   listing's own website or profiles; or a shared/generic name ("People for Animals", "Blue Cross" …) together with
 *   the listing's district or city. All whole-word, case-insensitive. An editor can untick a wrong one
 *   (aw_rel_org_not); it is never found again. Aliases changed: everything is scanned again in the background.
 * - Backfill: `wp aw-featured scan [--type=aw_news]`.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Item types an organisation can be featured in, with the label shown beside the link. */
const AW_FEATURED_TYPES = array( 'aw_news' => 'News', 'aw_legal' => 'Library', 'post' => 'Resource', 'aw_press' => 'Press', 'aw_campaign' => 'Campaign' );

/** Names too common to mean one organisation, after the words every NGO name shares are taken out. */
const AW_FEATURED_GENERIC = array( 'people for animals', 'animal welfare society', 'animal welfare organisation', 'animal welfare trust', 'animal care society', 'animal care trust', 'animal rescue trust', 'animal rescue', 'blue cross society', 'gaushala samiti', 'shri gaushala', 'shri gaushala samiti', 'gau seva samiti', 'gau sewa samiti', 'jeev daya', 'jivdaya trust', 'panjrapole', 'shri panjrapole', 'animal welfare board', 'animal welfare board of india', 'society for prevention of cruelty to animals', 'spca', 'district spca' );

/** Words common to animal-welfare names: a name of four words or fewer made only of these is an ordinary phrase. */
const AW_FEATURED_COMMON = array( 'a', 'an', 'the', 'of', 'for', 'and', 'in', 'to', 'india', 'indian', 'animal', 'animals', 'welfare', 'care', 'rescue', 'trust', 'foundation', 'society', 'organisation', 'organization', 'centre', 'center', 'rights', 'programme', 'program', 'birth', 'control', 'conservation', 'initiative', 'initiatives', 'stray', 'strays', 'dog', 'dogs', 'cat', 'cats', 'cow', 'cows', 'cattle', 'gau', 'gou', 'go', 'gaushala', 'goshala', 'seva', 'sewa', 'samiti', 'sansthan', 'sanstha', 'people', 'protection', 'prevention', 'cruelty', 'home', 'shelter', 'sanctuary', 'hospital', 'clinic', 'service', 'services', 'help', 'friends', 'lovers', 'association', 'group', 'club', 'charitable', 'mission', 'network', 'wildlife', 'nature', 'environment', 'development', 'education', 'research', 'voice', 'life', 'love', 'hope', 'kindness', 'compassion', 'humane', 'international', 'national', 'mandal', 'sangh', 'sabha', 'nyas', 'kendra', 'shri', 'shree', 'sri', 'jeev', 'jiv', 'daya', 'pashu', 'pakshi', 'bird', 'birds', 'pfa', 'spca', 'aid', 'unit', 'district', 'state', 'board', 'council', 'committee', 'federation', 'world', 'global', 'save', 'our', 'paws', 'tails', 'street', 'community', 'feeding', 'pinjrapole', 'panjrapole', 'pinjrapol', 'panjarapole', 'sadan', 'ashram', 'squad', 'karma' );

/**
 * Distinctive organisation names: lowercased name => listing ID. Cached until a listing is saved.
 *
 * @return array
 */
function aw_featured_names() {
	$names = get_transient( 'aw_featured_names' );
	if ( is_array( $names ) ) {
		return $names;
	}
	global $wpdb;
	$rows  = $wpdb->get_results( "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'aw_listing' AND post_status = 'publish'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$count = array();
	$by    = array();
	foreach ( $rows as $r ) {
		$n = mb_strtolower( trim( preg_replace( '/\s+/u', ' ', preg_replace( '/\s*\([^)]*\)\s*$/u', '', html_entity_decode( $r->post_title, ENT_QUOTES ) ) ) ) );
		$n = trim( preg_replace( '/\s*[–-]\s*$/u', '', $n ), " ,.-–" );
		if ( '' === $n ) {
			continue;
		}
		$count[ $n ] = ( $count[ $n ] ?? 0 ) + 1;
		$by[ $n ]    = (int) $r->ID;
	}
	$names = array();
	$skip  = function_exists( 'kits_preview_only_ids' ) ? kits_preview_only_ids() : array(); // sample listings never match
	foreach ( $by as $n => $id ) {
		if ( in_array( (int) $id, $skip, true ) ) {
			continue;
		}
		if ( 1 !== $count[ $n ] || mb_strlen( $n ) < 12 || str_word_count( $n ) < 2 || in_array( $n, AW_FEATURED_GENERIC, true ) ) {
			continue;
		}
		$words = preg_split( '/[^\p{L}\p{N}]+/u', $n, -1, PREG_SPLIT_NO_EMPTY );
		if ( count( $words ) <= 4 && ! array_diff( $words, AW_FEATURED_COMMON ) ) {
			continue; // e.g. "Animal Birth Control Programme", "Care for Animals": an ordinary phrase, not one organisation
		}
		$names[ $n ] = $id;
	}
	set_transient( 'aw_featured_names', $names, DAY_IN_SECONDS );
	return $names;
}
add_action( 'save_post_aw_listing', fn() => function_exists( 'aw_featured_flush' ) ? aw_featured_flush() : delete_transient( 'aw_featured_names' ) );

/** Text for matching: lower case, letters, digits and & only, single spaces, padded with a space each side. */
function aw_featured_norm( $s ) {
	return ' ' . trim( preg_replace( '/[^\p{L}\p{N}&]+/u', ' ', mb_strtolower( html_entity_decode( wp_strip_all_tags( (string) $s ), ENT_QUOTES ) ) ) ) . ' ';
}

/** A listing title as matched (no bracketed suffix, no trailing dash). */
function aw_featured_title_key( $title ) {
	$n = mb_strtolower( trim( preg_replace( '/\s+/u', ' ', preg_replace( '/\s*\([^)]*\)\s*$/u', '', html_entity_decode( (string) $title, ENT_QUOTES ) ) ) ) );
	return trim( preg_replace( '/\s*[–-]\s*$/u', '', $n ), " ,.-–" );
}

/** Place words too common to tell one place in a text (Delhi's "South", "Central" …). */
const AW_FEATURED_PLACE_STOP = array( 'north', 'south', 'east', 'west', 'central', 'north east', 'north west', 'south east', 'south west', 'new', 'city', 'rural', 'urban' );

/** A listing's district and city names, normalised (for shared names: name AND place in the same item). */
function aw_featured_places( $lid ) {
	$out = array();
	$p   = function_exists( 'aw_item_place' ) ? aw_item_place( $lid ) : null;
	foreach ( array( $p && 'district' === $p->kind ? $p->name : '', (string) get_post_meta( $lid, 'aw_place_city', true ) ) as $n ) {
		$n = trim( aw_featured_norm( $n ) );
		if ( '' !== $n && mb_strlen( $n ) >= 4 && ! in_array( $n, AW_FEATURED_PLACE_STOP, true ) ) {
			$out[] = $n;
		}
	}
	return array_values( array_unique( $out ) );
}

/**
 * Shared or generic names (People for Animals, Blue Cross, SPCA units …): normalised name => [[listing ID, places]].
 * Such a name counts only when the listing's district or city is named in the same item. Names of one word count only
 * through an alias. Cached until a listing is saved.
 *
 * @return array
 */
function aw_featured_shared() {
	$shared = get_transient( 'aw_featured_shared' );
	if ( is_array( $shared ) ) {
		return $shared;
	}
	global $wpdb;
	$distinct = aw_featured_names();
	$shared   = array();
	foreach ( $wpdb->get_results( "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'aw_listing' AND post_status = 'publish'" ) as $r ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$n = aw_featured_title_key( $r->post_title );
		if ( '' === $n || isset( $distinct[ $n ] ) || str_word_count( $n ) < 2 || in_array( (int) $r->ID, function_exists( 'kits_preview_only_ids' ) ? kits_preview_only_ids() : array(), true ) ) {
			continue;
		}
		$places = aw_featured_places( (int) $r->ID );
		if ( $places ) {
			$shared[ trim( aw_featured_norm( $n ) ) ][] = array( (int) $r->ID, $places );
		}
	}
	set_transient( 'aw_featured_shared', $shared, DAY_IN_SECONDS );
	return $shared;
}

/** Editor-set aliases ("Also known as", aw_aliases): normalised alias => listing IDs. Any length, whole word. */
function aw_featured_aliases() {
	$al = get_transient( 'aw_featured_aliases' );
	if ( is_array( $al ) ) {
		return $al;
	}
	global $wpdb;
	$al = array();
	foreach ( $wpdb->get_results( "SELECT m.post_id, m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = 'aw_aliases' AND m.meta_value <> '' AND p.post_type = 'aw_listing' AND p.post_status = 'publish'" ) as $r ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( in_array( (int) $r->post_id, function_exists( 'kits_preview_only_ids' ) ? kits_preview_only_ids() : array(), true ) ) {
			continue;
		}
		foreach ( explode( ',', (string) $r->meta_value ) as $a ) {
			$a = trim( aw_featured_norm( $a ) );
			if ( mb_strlen( $a ) >= 2 ) {
				$al[ $a ][] = (int) $r->post_id;
			}
		}
	}
	set_transient( 'aw_featured_aliases', $al, DAY_IN_SECONDS );
	return $al;
}

/** Hosts where one site holds many organisations' pages: the page path tells them apart. */
const AW_FEATURED_PLATFORMS = array( 'facebook.com', 'fb.com', 'instagram.com', 'x.com', 'twitter.com', 'youtube.com', 'linkedin.com', 'maps.google.com', 'google.com', 'maps.app.goo.gl', 'goo.gl', 'g.page', 'linktr.ee', 'sites.google.com', 'wixsite.com', 'blogspot.com', 'wordpress.com', 'business.site', 'ngobase.org', 'ngodarpan.gov.in' );

/**
 * A link's key for matching: an own site by its host; a page on a shared platform by host and its first path parts
 * (and the id or place in a Google or Facebook link). '' for nothing usable.
 *
 * @param string $url URL.
 * @return string
 */
function aw_featured_url_key( $url ) {
	$u = wp_parse_url( trim( (string) $url ) );
	if ( empty( $u['host'] ) ) {
		return '';
	}
	$host = preg_replace( '/^(www|m|mobile|web)\./', '', strtolower( $u['host'] ) );
	$plat = '';
	foreach ( AW_FEATURED_PLATFORMS as $p ) {
		if ( $host === $p || str_ends_with( $host, '.' . $p ) ) {
			$plat = $p;
			break;
		}
	}
	if ( '' === $plat ) {
		return $host;
	}
	$seg = array_values( array_filter( explode( '/', strtolower( trim( (string) ( $u['path'] ?? '' ), '/' ) ) ) ) );
	parse_str( (string) ( $u['query'] ?? '' ), $q );
	$keep = in_array( $seg[0] ?? '', array( 'channel', 'c', 'user', 'company', 'in', 'pages', 'groups', 'school', 'showcase' ), true ) ? 2 : 1;
	$key  = $host . '/' . implode( '/', array_slice( $seg, 0, $keep ) );
	foreach ( array( 'id', 'cid', 'place_id', 'q' ) as $k ) {
		if ( ! empty( $q[ $k ] ) && ( 'profile.php' === ( $seg[0] ?? '' ) || 'maps' === ( $seg[0] ?? '' ) || 'maps.google.com' === $plat ) ) {
			$key .= '?' . $k . '=' . strtolower( (string) $q[ $k ] );
			break;
		}
	}
	return '/' === substr( $key, -1 ) || strlen( $key ) <= strlen( $host ) + 1 ? '' : $key; // a platform's front page means nothing
}

/** The listings' own links (website, socials, Google profile): key => listing ID; keys shared by two listings are dropped. */
function aw_featured_link_keys() {
	$keys = get_transient( 'aw_featured_links' );
	if ( is_array( $keys ) ) {
		return $keys;
	}
	global $wpdb;
	$fields = array( 'aw_website', 'aw_instagram', 'aw_facebook', 'aw_x', 'aw_youtube', 'aw_linkedin', 'aw_g_profile' );
	$rows   = $wpdb->get_results( "SELECT m.post_id, m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key IN ('" . implode( "','", $fields ) . "') AND m.meta_value <> '' AND p.post_type = 'aw_listing' AND p.post_status = 'publish'" ); // phpcs:ignore WordPress.DB
	$by     = array();
	$skip   = function_exists( 'kits_preview_only_ids' ) ? kits_preview_only_ids() : array();
	foreach ( $rows as $r ) {
		if ( in_array( (int) $r->post_id, $skip, true ) ) {
			continue;
		}
		$k = aw_featured_url_key( $r->meta_value );
		if ( '' !== $k ) {
			$by[ $k ][ (int) $r->post_id ] = true;
		}
	}
	$keys = array();
	foreach ( $by as $k => $ids ) {
		if ( 1 === count( $ids ) ) {
			$keys[ $k ] = (int) array_key_first( $ids );
		}
	}
	set_transient( 'aw_featured_links', $keys, DAY_IN_SECONDS );
	return $keys;
}

/** Listings an item links to (its text's links to their website or profiles). */
function aw_featured_link_hits( $html ) {
	if ( ! preg_match_all( '/href=["\']([^"\']+)["\']/i', (string) $html, $m ) ) {
		return array();
	}
	$keys = aw_featured_link_keys();
	$hits = array();
	foreach ( $m[1] as $href ) {
		$k = aw_featured_url_key( html_entity_decode( $href ) );
		if ( '' === $k ) {
			continue;
		}
		if ( isset( $keys[ $k ] ) ) {
			$hits[] = $keys[ $k ];
			continue;
		}
		if ( false === strpos( $k, '/' ) ) { // an own site: a link to a subdomain of it counts too
			$parts = explode( '.', $k );
			while ( count( $parts ) > 2 ) {
				array_shift( $parts );
				if ( isset( $keys[ implode( '.', $parts ) ] ) ) {
					$hits[] = $keys[ implode( '.', $parts ) ];
					break;
				}
			}
		}
	}
	return array_values( array_unique( $hits ) );
}

/**
 * Find the organisations an item names or links to, and store them (aw_rel_org_auto); the ones an editor unticked
 * (aw_rel_org_not) are never added again. Four ways, all whole-word and case-insensitive:
 * 1. a distinctive name (aw_featured_names: 2+ words, 12+ letters, one listing, not generic);
 * 2. an editor's alias ("Also known as", any length);
 * 3. a link in the text to the listing's own website or a profile of it (key unique to one listing);
 * 4. a shared or generic name together with that listing's district or city in the same item.
 *
 * @param int $id Item.
 * @return int[] Listing IDs found.
 */
function aw_featured_scan( $id ) {
	$type = get_post_type( $id );
	if ( ! isset( AW_FEATURED_TYPES[ $type ] ) ) {
		return array();
	}
	$content = (string) get_post_field( 'post_content', $id );
	$text    = aw_featured_norm( get_post_field( 'post_title', $id ) . ' ' . get_post_field( 'post_excerpt', $id ) . ' ' . $content );
	$not     = array_map( 'intval', (array) get_post_meta( $id, 'aw_rel_org_not', false ) );
	$hits    = array();
	foreach ( aw_featured_names() as $name => $lid ) {
		if ( false !== strpos( $text, aw_featured_norm( $name ) ) ) {
			$hits[] = $lid;
		}
	}
	foreach ( aw_featured_aliases() as $alias => $lids ) {
		if ( false !== strpos( $text, ' ' . $alias . ' ' ) ) {
			$hits = array_merge( $hits, $lids );
		}
	}
	$hits = array_merge( $hits, aw_featured_link_hits( $content ) );
	foreach ( aw_featured_shared() as $name => $entries ) {
		if ( false === strpos( $text, ' ' . $name . ' ' ) ) {
			continue;
		}
		foreach ( $entries as list( $lid, $places ) ) {
			foreach ( $places as $pl ) {
				if ( false !== strpos( $text, ' ' . $pl . ' ' ) ) {
					$hits[] = $lid;
					break;
				}
			}
		}
	}
	$hits = array_values( array_diff( array_unique( array_map( 'intval', $hits ) ), $not, array( 0 ) ) );
	delete_post_meta( $id, 'aw_rel_org_auto' );
	foreach ( $hits as $lid ) {
		add_post_meta( $id, 'aw_rel_org_auto', $lid );
	}
	return $hits;
}

/** Forget the matching lists (a listing saved, or its aliases changed). */
function aw_featured_flush() {
	foreach ( array( 'aw_featured_names', 'aw_featured_shared', 'aw_featured_aliases', 'aw_featured_links' ) as $t ) {
		delete_transient( $t );
	}
}

/**
 * Scan every published item again, 300 at a time in the background (after aliases change).
 *
 * @param int $offset Where to start.
 */
function aw_featured_rescan( $offset = 0 ) {
	$ids = get_posts( array( 'post_type' => array_keys( AW_FEATURED_TYPES ), 'post_status' => 'publish', 'numberposts' => 300, 'offset' => (int) $offset, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ) );
	foreach ( $ids as $i ) {
		aw_featured_scan( $i );
	}
	if ( 300 === count( $ids ) ) {
		wp_schedule_single_event( time() + 30, 'aw_featured_rescan', array( (int) $offset + 300 ) );
	}
}
add_action( 'aw_featured_rescan', 'aw_featured_rescan' );

// An alias added, changed or removed: forget the lists and scan everything again.
foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $aw_hook ) {
	add_action(
		$aw_hook,
		function ( $mid, $pid, $key ) {
			if ( 'aw_aliases' === $key ) {
				aw_featured_flush();
				if ( ! wp_next_scheduled( 'aw_featured_rescan', array( 0 ) ) ) {
					wp_schedule_single_event( time() + 60, 'aw_featured_rescan', array( 0 ) );
				}
			}
		},
		10,
		3
	);
}

add_action(
	'save_post',
	function ( $id, $post ) {
		if ( wp_is_post_revision( $id ) || ! isset( AW_FEATURED_TYPES[ $post->post_type ] ) || 'auto-draft' === $post->post_status ) {
			return;
		}
		if ( isset( $_POST['_aw_model'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_aw_model'] ) ), 'aw_model_' . $id ) && current_user_can( 'edit_post', $id ) ) {
			$keep = array_map( 'absint', (array) wp_unslash( $_POST['aw_rel_org_auto_keep'] ?? array() ) ); // ticked ones stay
			foreach ( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['aw_rel_org_auto_shown'] ?? '' ) ) ) ) as $lid ) {
				if ( $lid && ! in_array( $lid, $keep, true ) && ! in_array( $lid, array_map( 'intval', (array) get_post_meta( $id, 'aw_rel_org_not', false ) ), true ) ) {
					add_post_meta( $id, 'aw_rel_org_not', $lid ); // unticked: not this organisation
				}
			}
		}
		aw_featured_scan( $id );
	},
	20,
	2
);

/**
 * The organisations an item features (by hand first, then found), without the unticked ones.
 *
 * @param int $id Item.
 * @return int[]
 */
function aw_featured_orgs( $id ) {
	$not = array_map( 'intval', (array) get_post_meta( $id, 'aw_rel_org_not', false ) );
	$ids = array_merge( array_map( 'intval', (array) get_post_meta( $id, 'aw_rel_org', false ) ), array_map( 'intval', (array) get_post_meta( $id, 'aw_rel_org_auto', false ) ) );
	return array_values( array_filter( array_unique( $ids ), fn( $l ) => $l && ! in_array( $l, $not, true ) && 'publish' === get_post_status( $l ) ) );
}

/**
 * The items an organisation is featured in, newest first.
 *
 * @param int $lid Listing.
 * @param int $max How many.
 * @return int[]
 */
function aw_featured_items( $lid, $max = 30 ) {
	$ids = get_posts(
		array(
			'post_type'   => array_keys( AW_FEATURED_TYPES ),
			'post_status' => 'publish',
			'numberposts' => $max * 2,
			'fields'      => 'ids',
			'orderby'     => 'date',
			'order'       => 'DESC',
			'meta_query'  => array( 'relation' => 'OR', array( 'key' => 'aw_rel_org', 'value' => (string) $lid ), array( 'key' => 'aw_rel_org_auto', 'value' => (string) $lid ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	$ids = array_values( array_filter( $ids, fn( $i ) => ! in_array( (int) $lid, array_map( 'intval', (array) get_post_meta( $i, 'aw_rel_org_not', false ) ), true ) ) );
	return array_slice( $ids, 0, $max );
}

/**
 * Where an item's link goes: its own page, or for press coverage (no page here) the article it records.
 *
 * @param int $id Item.
 * @return string
 */
function aw_featured_url( $id ) {
	return 'aw_press' === get_post_type( $id ) ? (string) get_post_meta( $id, 'aw_source_url', true ) : (string) get_permalink( $id );
}

/**
 * The "Featured in" list of a listing.
 *
 * @param int $lid Listing.
 * @return string
 */
function aw_featured_html( $lid ) {
	$items = aw_featured_items( $lid );
	if ( ! $items ) {
		return '';
	}
	$li = '';
	foreach ( $items as $i ) {
		$url = aw_featured_url( $i );
		if ( ! $url ) {
			continue;
		}
		$ext  = 'aw_press' === get_post_type( $i );
		$date = function_exists( 'aw_src_date' ) ? aw_date( (string) get_post_meta( $i, 'aw_source_date', true ) ?: get_post_field( 'post_date', $i ) ) : '';
		$li  .= '<li class="aw-featured-item"><span class="aw-featured-type">' . esc_html( AW_FEATURED_TYPES[ get_post_type( $i ) ] ) . '</span> '
			. '<a href="' . esc_url( $url ) . '"' . ( $ext ? ' rel="noopener nofollow"' : '' ) . '>' . esc_html( html_entity_decode( get_the_title( $i ), ENT_QUOTES ) ) . '</a> ' . $date . '</li>';
	}
	return $li ? '<section class="aw-org-links-group aw-featured"><h2>Featured in</h2><ul>' . $li . '</ul></section>' : '';
}

/* ---------- Schema: the item's mentions, the organisation's subjectOf ---------- */

/**
 * schema.org mentions for an item: at most 5 organisations (owner, 1 Nov 2026), the ones picked by hand first.
 *
 * @param int $id Item.
 * @return array
 */
function aw_featured_mentions_schema( $id ) {
	return array_map(
		fn( $l ) => array( '@type' => 'Organization', '@id' => get_permalink( $l ) . '#organization', 'name' => html_entity_decode( get_the_title( $l ), ENT_QUOTES ), 'url' => get_permalink( $l ) ),
		array_slice( aw_featured_orgs( $id ), 0, 5 )
	);
}

/**
 * schema.org subjectOf for a listing.
 *
 * @param int $lid Listing.
 * @return array
 */
function aw_featured_subject_schema( $lid ) {
	$types = array( 'aw_news' => 'NewsArticle', 'aw_legal' => 'Article', 'post' => 'Article', 'aw_press' => 'NewsArticle', 'aw_campaign' => 'CreativeWork' );
	$out   = array();
	foreach ( aw_featured_items( $lid, 20 ) as $i ) {
		$url = aw_featured_url( $i );
		if ( $url ) {
			$out[] = array_filter( array( '@type' => $types[ get_post_type( $i ) ], 'name' => html_entity_decode( get_the_title( $i ), ENT_QUOTES ), 'url' => $url, 'datePublished' => (string) get_post_meta( $i, 'aw_source_date', true ) ?: get_the_date( 'Y-m-d', $i ) ) );
		}
	}
	return $out;
}

/* ---------- Admin: the found organisations, each with a tick ---------- */

/**
 * Rows for the Related box (model.php): organisations found in the text, ticked; untick a wrong one.
 *
 * @param WP_Post $post Item.
 */
function aw_featured_admin_rows( $post ) {
	if ( ! isset( AW_FEATURED_TYPES[ $post->post_type ] ) ) {
		return;
	}
	$found = array_map( 'intval', (array) get_post_meta( $post->ID, 'aw_rel_org_auto', false ) );
	$not   = array_map( 'intval', (array) get_post_meta( $post->ID, 'aw_rel_org_not', false ) );
	echo '<tr><th>Found in the text</th><td>';
	if ( ! $found && ! $not ) {
		echo '<p class="description">No organisation named in this item yet (found again when it is saved).</p>';
	}
	echo '<input type="hidden" name="aw_rel_org_auto_shown" value="' . esc_attr( implode( ',', $found ) ) . '">';
	foreach ( $found as $l ) {
		echo '<label style="display:block"><input type="checkbox" name="aw_rel_org_auto_keep[]" value="' . (int) $l . '" checked> ' . esc_html( get_the_title( $l ) ) . '</label>';
	}
	if ( $not ) {
		echo '<p class="description">Unticked before (not found again): ' . esc_html( implode( ', ', array_map( 'get_the_title', $not ) ) ) . '</p>';
	}
	echo '<p class="description">Shown on the organisation\'s page under "Featured in". Untick one that is a different organisation.</p></td></tr>';
}

/* ---------- WP-CLI: find the organisations in every item once ---------- */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'aw-featured scan',
		function ( $args, $assoc ) {
			$types = ! empty( $assoc['type'] ) ? array( $assoc['type'] ) : array_keys( AW_FEATURED_TYPES );
			aw_featured_flush();
			$items = 0;
			$links = 0;
			foreach ( $types as $t ) {
				foreach ( get_posts( array( 'post_type' => $t, 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) {
					$n = count( aw_featured_scan( $id ) );
					$items += $n ? 1 : 0;
					$links += $n;
				}
			}
			WP_CLI::success( "$links links found in $items items (" . count( aw_featured_names() ) . ' distinctive organisation names)' );
		}
	);
}
