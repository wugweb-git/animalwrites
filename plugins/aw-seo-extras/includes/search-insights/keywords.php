<?php
/**
 * Keyword records (plan §3): normalising and grouping, roles, the one-time seed from the keyword map and the daily role
 * sync from Rank Math and AW Sourcing. Everything here only reads Rank Math and Sourcing; it never writes to them.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SI_ROLE_SOURCING = 1;  // A Google News search in AW Sourcing's Include list.
const AW_SI_ROLE_FOCUS    = 2;  // A Rank Math focus keyphrase (primary or secondary) on a post or term.
const AW_SI_ROLE_TRACKED  = 4;  // Keyword map target, demand gap or the monthly check list.
const AW_SI_ROLE_BRAND    = 8;  // Matches AW_SD_BRAND.
const AW_SI_ROLE_SEARCHED = 16; // Seen in Search Console.

/** Role bits with their labels. */
function aw_si_roles(): array {
	return array(
		AW_SI_ROLE_TRACKED  => __( 'Tracked', 'aw-seo-extras' ),
		AW_SI_ROLE_FOCUS    => __( 'Focus keyphrase', 'aw-seo-extras' ),
		AW_SI_ROLE_SOURCING => __( 'Sourcing', 'aw-seo-extras' ),
		AW_SI_ROLE_BRAND    => __( 'Brand', 'aw-seo-extras' ),
		AW_SI_ROLE_SEARCHED => __( 'Searched', 'aw-seo-extras' ),
	);
}

/**
 * Whether a phrase is a brand search: the pattern of search-demand.php, or the same rule if that file is not loaded.
 *
 * @param string $s Phrase.
 */
function aw_si_is_brand( string $s ): bool {
	$re = defined( 'AW_SD_BRAND' ) ? AW_SD_BRAND : '/animal\s*-?\s*writ|animalwrit|india\s*writes/i';
	return (bool) preg_match( $re, $s );
}

/**
 * Normalised form: lower case, dots dropped (g.s.r. → gsr), other punctuation as spaces ({placeholders} kept), single
 * spaces, and each English word's plural folded (dogs → dog, ngos → ngo, gaushalas → gaushala). Variants that differ only
 * in these ways share one record.
 *
 * @param string $s Phrase as written.
 */
function aw_si_norm( string $s ): string {
	$s = mb_strtolower( wp_strip_all_tags( $s ) );
	$s = str_replace( array( '.', "'", '’' ), '', $s );
	$s = (string) preg_replace( '/[^\p{L}\p{M}\p{N}{}]+/u', ' ', $s );
	$w = preg_split( '/\s+/u', trim( $s ) );
	foreach ( (array) $w as $i => $word ) {
		if ( preg_match( '/^[a-z]{4,}s$/', $word ) && ! preg_match( '/(ss|us|is|ous)$/', $word ) ) {
			$w[ $i ] = substr( $word, 0, -1 );
		}
	}
	return mb_substr( trim( implode( ' ', (array) $w ) ), 0, 191 );
}

/**
 * Language: hi (Devanagari), hinglish (Latin-script Hindi) or en. Hinglish needs one clear Hindi word, or two of the
 * short ones that are also English-looking ("ke", "se").
 *
 * @param string $s Phrase as written.
 */
function aw_si_lang( string $s ): string {
	if ( preg_match( '/\p{Devanagari}/u', $s ) ) {
		return 'hi';
	}
	static $strong = array( 'kutta', 'kutte', 'kutton', 'billi', 'gaay', 'gai', 'gau', 'janwar', 'jaanwar', 'janwaron', 'pashu', 'pashuon', 'chikitsa', 'aspatal', 'kaatna', 'kaata', 'kaat', 'kaise', 'kare', 'karein', 'kya', 'kahan', 'kab', 'manaya', 'jata', 'hota', 'hoti', 'nahi', 'nahin', 'sarkari', 'niyam', 'kanoon', 'kanun', 'shikayat', 'bachao', 'suchna', 'hindi', 'mein', 'liye' );
	static $weak   = array( 'ke', 'ki', 'ka', 'se', 'hai', 'ko', 'aur', 'par' );
	$words         = preg_split( '/\s+/', mb_strtolower( $s ) );
	$weak_hits     = 0;
	foreach ( (array) $words as $w ) {
		if ( in_array( $w, $strong, true ) ) {
			return 'hinglish';
		}
		if ( in_array( $w, $weak, true ) ) {
			++$weak_hits;
		}
	}
	return $weak_hits >= 2 ? 'hinglish' : 'en';
}

/** Map of norm => id for every keyword record. */
function aw_si_kw_map(): array {
	global $wpdb;
	$t    = aw_si_table( 'keywords' );
	$rows = (array) $wpdb->get_results( "SELECT norm, id FROM {$t}", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	return array_map( 'intval', array_column( $rows, 'id', 'norm' ) );
}

/**
 * IDs for these keyword labels, creating the missing records and adding $role to all of them.
 *
 * @param string[] $labels Keyword labels as written.
 * @param int      $role   Role bit to add.
 * @param array    $extra  Columns to set on records created here (page_type, intent, entity …).
 * @return array<string, int> norm => id.
 */
function aw_si_kw_ids( array $labels, int $role, array $extra = array() ): array {
	global $wpdb;
	$t    = aw_si_table( 'keywords' );
	$map  = aw_si_kw_map();
	$want = array();
	$now  = aw_si_now();
	$new  = array();
	foreach ( $labels as $label ) {
		$label = trim( (string) $label );
		$norm  = aw_si_norm( $label );
		if ( '' === $norm ) {
			continue;
		}
		$want[ $norm ] = true;
		if ( ! isset( $map[ $norm ] ) && ! isset( $new[ $norm ] ) ) {
			$new[ $norm ] = $label;
		}
	}
	foreach ( array_chunk( $new, 200, true ) as $chunk ) {
		$rows = array();
		$args = array();
		foreach ( $chunk as $norm => $label ) {
			$rows[] = '(%s, %s, %s, %d, %s, %s, %d, %s, %s)';
			array_push( $args, (string) $norm, mb_substr( $label, 0, 255 ), aw_si_lang( $label ), $role | ( aw_si_is_brand( $label ) ? AW_SI_ROLE_BRAND : 0 ), (string) ( $extra['page_type'] ?? '' ), (string) ( $extra['intent'] ?? '' ), (int) ( $extra['entity'] ?? 0 ), $now, $now );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- one placeholder group per row, built above.
		$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$t} (norm, label, lang, roles, page_type, intent, entity, created, updated) VALUES " . implode( ', ', $rows ), $args ) );
	}
	if ( $new ) {
		$map = aw_si_kw_map();
	}
	$ids = array_intersect_key( $map, $want );
	foreach ( array_chunk( array_values( $ids ), 500 ) as $chunk ) {
		$in = implode( ',', array_map( 'intval', $chunk ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET roles = roles | %d WHERE id IN ({$in}) AND roles & %d = 0", $role, $role ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integers.
	}
	// Variants and plural forms share one record already (same norm); group_id points a record at itself until the
	// owner groups Hindi / Hinglish forms under an English parent.
	$wpdb->query( "UPDATE {$t} SET group_id = id WHERE group_id = 0" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return $ids;
}

/**
 * Store one page link for a keyword (insert or refresh).
 *
 * @param int    $kw_id    Keyword record.
 * @param string $obj_type post, term, path or label.
 * @param int    $obj_id   Post or term ID (0 for a path or label).
 * @param string $path     Site path, Directory pattern or page label.
 * @param string $role     owner, secondary or live.
 * @param string $src      Where the link comes from: seed or focus.
 */
function aw_si_link_page( int $kw_id, string $obj_type, int $obj_id, string $path, string $role, string $src ): void {
	global $wpdb;
	$wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		aw_si_table( 'kw_pages' ),
		array(
			'kw_id'    => $kw_id,
			'obj_type' => $obj_type,
			'obj_id'   => $obj_id,
			'path'     => mb_substr( $path, 0, 255 ),
			'path_key' => md5( $obj_type . ':' . $obj_id . ':' . $path ),
			'role'     => $role,
			'src'      => $src,
			'updated'  => aw_si_now(),
		),
		array( '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
	);
}

/**
 * Site path of a URL ('/news/x/'), or '' for another host.
 *
 * @param string $url Full URL.
 */
function aw_si_path( string $url ): string {
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	$u    = wp_parse_url( $url );
	if ( ! empty( $u['host'] ) && ! in_array( preg_replace( '/^www\./', '', (string) $u['host'] ), array( preg_replace( '/^www\./', '', (string) $host ), 'animalwrites.in' ), true ) ) {
		return '';
	}
	$path = (string) ( $u['path'] ?? '/' );
	return '' === $path ? '/' : $path;
}

/**
 * Site path of a post or term, or '' when it has none.
 *
 * @param string $type post or term.
 * @param int    $id   Post or term ID.
 */
function aw_si_object_path( string $type, int $id ): string {
	$url = 'term' === $type ? get_term_link( $id ) : get_permalink( $id );
	return is_string( $url ) ? aw_si_path( $url ) : '';
}

/**
 * Resolve a keyword-map page to an object: a post ID, a path on the site, or a topic (category) by name.
 *
 * @param array  $page      Seed page: label, path, post_id.
 * @param string $page_type Seed page type (hub, topic, guide, key-page …).
 * @return array{0: string, 1: int, 2: string} obj_type (post, term, path, label), obj_id, path or label.
 */
function aw_si_resolve_page( array $page, string $page_type ): array {
	$pid = (int) ( $page['post_id'] ?? 0 );
	if ( $pid && get_post( $pid ) ) {
		return array( 'post', $pid, aw_si_object_path( 'post', $pid ) );
	}
	$path = (string) ( $page['path'] ?? '' );
	if ( '' !== $path && false === strpos( $path, '{' ) ) {
		$id = url_to_postid( home_url( $path ) );
		return $id ? array( 'post', $id, $path ) : array( 'path', 0, $path );
	}
	if ( '' !== $path ) {
		return array( 'path', 0, $path ); // A Directory pattern such as /directory/{state}/.
	}
	$label = (string) ( $page['label'] ?? '' );
	if ( 'topic' === $page_type ) {
		$term = get_term_by( 'name', $label, 'category' );
		if ( $term ) {
			return array( 'term', (int) $term->term_id, aw_si_object_path( 'term', (int) $term->term_id ) );
		}
	}
	if ( 'key-page' === $page_type ) {
		$q = get_posts(
			array(
				'post_type'      => 'page',
				'title'          => $label,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		if ( $q ) {
			return array( 'post', (int) $q[0], aw_si_object_path( 'post', (int) $q[0] ) );
		}
	}
	if ( 'guide' === $page_type ) {
		$q = get_posts(
			array(
				'post_type'      => 'post',
				'title'          => $label,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		if ( $q ) {
			return array( 'post', (int) $q[0], aw_si_object_path( 'post', (int) $q[0] ) );
		}
	}
	return array( 'label', 0, $label );
}

/** The seed file shipped with the plugin (built by tools/search-insights/build_seed.py). */
function aw_si_seed_data(): array {
	$file = (string) apply_filters( 'aw_si_seed_file', AW_SEO_EXTRAS_DIR . 'data/search-insights-seed.json' );
	$json = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
	return is_array( $json ) ? $json : array();
}

/**
 * Seed the keyword table once from the keyword map (plan §2). After this the table is the source of truth; the seed
 * never runs again unless the owner deletes the data.
 *
 * @return array Counts, or an empty array when already seeded.
 */
function aw_si_seed(): array {
	if ( get_option( 'aw_si_seeded' ) ) {
		return array();
	}
	global $wpdb;
	$seed = aw_si_seed_data();
	if ( empty( $seed['map'] ) ) {
		return array( 'error' => 'seed file missing' );
	}
	$k     = aw_si_table( 'keywords' );
	$count = array(
		'map'      => 0,
		'gaps'     => 0,
		'rankings' => 0,
		'pages'    => 0,
	);
	foreach ( $seed['map'] as $row ) {
		$ids = aw_si_kw_ids(
			array( $row['keyword'] ),
			AW_SI_ROLE_TRACKED,
			array(
				'page_type' => $row['page_type'],
				'intent'    => $row['intent'],
				'entity'    => '{organisation name}' === $row['keyword'] ? 1 : 0,
			)
		);
		$id  = (int) reset( $ids );
		if ( ! $id ) {
			continue;
		}
		++$count['map'];
		$set = array( 'page_type' => $row['page_type'] );
		if ( 'primary' === $row['kind'] && $row['priority'] ) {
			$set['priority'] = $row['priority']; // The 21 Oct priority until aw_si_compute() recalculates it.
		}
		if ( 'topic' === $row['page_type'] ) {
			$set['topic'] = mb_substr( (string) $row['page']['label'], 0, 100 );
		}
		$wpdb->update( $k, $set, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		list( $type, $obj, $path ) = aw_si_resolve_page( (array) $row['page'], (string) $row['page_type'] );
		aw_si_link_page( $id, $type, $obj, $path, 'primary' === $row['kind'] ? 'owner' : 'secondary', 'seed' );
		++$count['pages'];
		if ( null !== $row['trends'] ) {
			aw_si_obs_put( $id, '', 'trends', 'index', '12m', '2026-10-15', array( 'value' => (float) $row['trends'] ) );
		}
		if ( null !== $row['rank'] ) {
			$num = is_int( $row['rank'] ) ? (float) $row['rank'] : null;
			aw_si_obs_put(
				$id,
				'',
				'live',
				'position',
				'month',
				'2026-10-22',
				array(
					'value' => $num,
					'txt'   => (string) $row['rank'],
				)
			);
		}
	}
	foreach ( (array) ( $seed['gaps'] ?? array() ) as $g ) {
		$ids = aw_si_kw_ids( array( $g['keyword'] ), AW_SI_ROLE_TRACKED );
		$id  = (int) reset( $ids );
		if ( $id ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$k} SET note = %s WHERE id = %d AND note = ''", mb_substr( 'Gap (15 Oct): ' . $g['gap'], 0, 255 ), $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			++$count['gaps'];
		}
	}
	foreach ( (array) ( $seed['rankings'] ?? array() ) as $r ) {
		$ids = aw_si_kw_ids( array( $r['keyword'] ), AW_SI_ROLE_TRACKED );
		$id  = (int) reset( $ids );
		if ( ! $id ) {
			continue;
		}
		++$count['rankings'];
		aw_si_obs_put(
			$id,
			'',
			'live',
			'position',
			'month',
			$r['date'],
			array(
				'value' => null === $r['position'] ? null : (float) $r['position'],
				'txt'   => (string) $r['position_text'],
			)
		);
		if ( '' !== (string) $r['top3'] ) {
			aw_si_obs_put( $id, '', 'live', 'top3', 'month', $r['date'], array( 'txt' => (string) $r['top3'] ) );
		}
		if ( '' !== (string) $r['paa'] ) {
			aw_si_obs_put( $id, '', 'live', 'paa', 'month', $r['date'], array( 'txt' => (string) $r['paa'] ) );
		}
		if ( '' !== (string) $r['url'] ) {
			aw_si_link_page( $id, 'path', 0, aw_si_path( (string) $r['url'] ), 'live', 'seed' );
		}
	}
	update_option( 'aw_si_seeded', time(), false );
	return $count;
}

/**
 * Insert or replace one observation.
 *
 * @param int    $kw_id  Keyword record (0 for a site total).
 * @param string $page   Site path, or '' for keyword-level rows.
 * @param string $src    Source, such as gsc, live or trends.
 * @param string $metric Metric of that source.
 * @param string $period day, month, or a rolling window such as 12m.
 * @param string $d      Date (Y-m-d).
 * @param array  $m      Any of clicks, impr, pos, value, txt.
 */
function aw_si_obs_put( int $kw_id, string $page, string $src, string $metric, string $period, string $d, array $m ): void {
	global $wpdb;
	$wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		aw_si_table( 'obs' ),
		array(
			'kw_id'    => $kw_id,
			'page'     => mb_substr( $page, 0, 255 ),
			'page_key' => '' === $page ? '' : md5( $page ),
			'src'      => $src,
			'metric'   => $metric,
			'period'   => $period,
			'd'        => $d,
			'clicks'   => $m['clicks'] ?? null,
			'impr'     => $m['impr'] ?? null,
			'pos'      => $m['pos'] ?? null,
			'value'    => $m['value'] ?? null,
			'txt'      => mb_substr( (string) ( $m['txt'] ?? '' ), 0, 255 ),
			'fetched'  => aw_si_now(),
		)
	);
}

/**
 * Primary and secondary phrases of a Rank Math focus keyword field ("primary, secondary, …").
 *
 * @param string $field The rank_math_focus_keyword value.
 */
function aw_si_focus_parts( string $field ): array {
	$parts = array_values( array_filter( array_map( 'trim', explode( ',', $field ) ), 'strlen' ) );
	return array( $parts[0] ?? '', array_slice( $parts, 1 ) );
}

/**
 * Daily role sync (read-only towards Rank Math and Sourcing):
 * - focus: Rank Math keyphrases of published items (AW_SEO_ITEM_TYPES without listings) and of terms; listings only mark
 *   matching records as entities (their names are not added one by one: plan, fixed decisions);
 * - sourcing: AW Sourcing's Google News searches (aw_relevance()['strong']), when AW Sourcing is active;
 * - brand: AW_SD_BRAND.
 *
 * @return array Counts.
 */
function aw_si_sync_roles(): array {
	global $wpdb;
	$k     = aw_si_table( 'keywords' );
	$p     = aw_si_table( 'kw_pages' );
	$types = defined( 'AW_SEO_ITEM_TYPES' ) ? array_values( array_diff( AW_SEO_ITEM_TYPES, array( 'aw_listing' ) ) ) : array( 'post', 'page' );
	$in    = implode( ',', array_fill( 0, count( $types ), '%s' ) );
	$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT p.ID, m.meta_value FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'rank_math_focus_keyword' WHERE p.post_status = 'publish' AND p.post_type IN ({$in}) AND m.meta_value <> ''", $types ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- one %s per type.
	$terms = $wpdb->get_results( "SELECT t.term_id AS ID, m.meta_value FROM {$wpdb->terms} t JOIN {$wpdb->termmeta} m ON m.term_id = t.term_id AND m.meta_key = 'rank_math_focus_keyword' WHERE m.meta_value <> ''" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	$labels = array();
	$links  = array(); // [label, obj_type, id, role].
	foreach ( array(
		'post' => $rows,
		'term' => $terms,
	) as $type => $list ) {
		foreach ( (array) $list as $r ) {
			list( $primary, $secondary ) = aw_si_focus_parts( (string) $r->meta_value );
			if ( '' !== $primary ) {
				$labels[] = $primary;
				$links[]  = array( $primary, $type, (int) $r->ID, 'owner' );
			}
			foreach ( $secondary as $s ) {
				$labels[] = $s;
				$links[]  = array( $s, $type, (int) $r->ID, 'secondary' );
			}
		}
	}
	// The focus role is rebuilt from scratch each day so a keyphrase the owner removed loses it.
	$wpdb->query( $wpdb->prepare( "UPDATE {$k} SET roles = roles & ~%d", AW_SI_ROLE_FOCUS ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DELETE FROM {$p} WHERE src = 'focus'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$ids = aw_si_kw_ids( $labels, AW_SI_ROLE_FOCUS );
	foreach ( $links as $l ) {
		$id = $ids[ aw_si_norm( $l[0] ) ] ?? 0;
		if ( ! $id ) {
			continue;
		}
		aw_si_link_page( $id, $l[1], $l[2], aw_si_object_path( $l[1], $l[2] ), $l[3], 'focus' );
	}

	// Listings: mark existing records (from Search Console or the map) whose phrase is a listing's name.
	$names = $wpdb->get_col( "SELECT m.meta_value FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'rank_math_focus_keyword' WHERE p.post_type = 'aw_listing' AND p.post_status = 'publish' AND m.meta_value <> ''" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$map   = aw_si_kw_map();
	$ent   = array();
	foreach ( (array) $names as $n ) {
		$norm = aw_si_norm( aw_si_focus_parts( (string) $n )[0] );
		if ( isset( $map[ $norm ] ) ) {
			$ent[] = (int) $map[ $norm ];
		}
	}
	foreach ( array_chunk( $ent, 500 ) as $chunk ) {
		$in = implode( ',', array_map( 'intval', $chunk ) );
		$wpdb->query( "UPDATE {$k} SET entity = 1 WHERE id IN ({$in})" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integers.
	}

	// Sourcing: read only, and only when AW Sourcing is active (the section hides otherwise).
	$wpdb->query( $wpdb->prepare( "UPDATE {$k} SET roles = roles & ~%d", AW_SI_ROLE_SOURCING ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$sourcing = function_exists( 'aw_relevance' ) ? (array) ( aw_relevance()['strong'] ?? array() ) : array();
	if ( $sourcing ) {
		aw_si_kw_ids( $sourcing, AW_SI_ROLE_SOURCING );
	}

	// Brand: every record whose label matches.
	$brand = array();
	foreach ( (array) $wpdb->get_results( "SELECT id, label FROM {$k}" ) as $r ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( aw_si_is_brand( (string) $r->label ) ) {
			$brand[] = (int) $r->id;
		}
	}
	foreach ( array_chunk( $brand, 500 ) as $chunk ) {
		$in = implode( ',', array_map( 'intval', $chunk ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$k} SET roles = roles | %d WHERE id IN ({$in})", AW_SI_ROLE_BRAND ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integers.
	}

	return array(
		'focus_posts' => count( (array) $rows ),
		'focus_terms' => count( (array) $terms ),
		'focus_kw'    => count( $ids ),
		'entities'    => count( $ent ),
		'sourcing'    => count( $sourcing ),
		'brand'       => count( $brand ),
	);
}
