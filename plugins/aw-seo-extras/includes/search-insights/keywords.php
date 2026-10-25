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

// The role constants, AW_SI_BRAND, AW_SI_NOT_PLURAL, aw_si_roles(), aw_si_is_brand(), aw_si_norm() and aw_si_lang() moved
// to Animal Writes Core with the keyword table (includes/keyword-model.php and keyword-table.php; owner's decision 3,
// 25 Oct 2026), so AW Sourcing reads and writes the table without this plugin.

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
		// Variants and plural forms share one record already (same norm); group_id points a new record at itself until
		// the owner groups Hindi / Hinglish forms under an English parent.
		$wpdb->query( "UPDATE {$t} SET group_id = id WHERE group_id = 0" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
	$ids = array_intersect_key( $map, $want );
	foreach ( array_chunk( array_values( $ids ), 500 ) as $chunk ) {
		$in = implode( ',', array_map( 'intval', $chunk ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET roles = roles | %d WHERE id IN ({$in}) AND roles & %d = 0", $role, $role ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integers.
	}
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
 * @return bool Whether the link was written.
 */
function aw_si_link_page( int $kw_id, string $obj_type, int $obj_id, string $path, string $role, string $src ): bool {
	global $wpdb;
	return false !== $wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
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
 * Store many page links at once (insert or refresh).
 *
 * @param array $links Each [kw_id, obj_type, obj_id, path, role, src].
 */
function aw_si_link_pages( array $links ): void {
	global $wpdb;
	$t   = aw_si_table( 'kw_pages' );
	$now = aw_si_now();
	foreach ( array_chunk( $links, 300 ) as $chunk ) {
		$place = array();
		$args  = array();
		foreach ( $chunk as $l ) {
			$place[] = '(%d, %s, %d, %s, %s, %s, %s, %s)';
			array_push( $args, (int) $l[0], (string) $l[1], (int) $l[2], mb_substr( (string) $l[3], 0, 255 ), md5( $l[1] . ':' . $l[2] . ':' . $l[3] ), (string) $l[4], (string) $l[5], $now );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- one placeholder group per row, built above.
		$wpdb->query( $wpdb->prepare( "REPLACE INTO {$t} (kw_id, obj_type, obj_id, path, path_key, role, src, updated) VALUES " . implode( ', ', $place ), $args ) );
	}
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

/**
 * The seed file. Removed after the live seed (25 Oct 2026, 07:12); it and its build tool
 * (tools/search-insights/build_seed.py) are in git history. Without it a fresh seed reports "seed file missing".
 */
function aw_si_seed_data(): array {
	$file = (string) apply_filters( 'aw_si_seed_file', AW_SEO_EXTRAS_DIR . 'data/search-insights-seed.json' );
	$json = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
	return is_array( $json ) ? $json : array();
}

/**
 * Seed the keyword table once from the keyword map (plan §2). After this the table is the source of truth; the seed
 * never runs again unless the owner deletes the data. It is marked done only when every write succeeded, so a seed
 * cut short or hit by a database error runs again with the next daily run (each write replaces, so a repeat is safe).
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
	$k = aw_si_table( 'keywords' );
	// One lookup for every seeded phrase (creating the records), instead of one per row.
	$all   = array_merge( array_column( $seed['map'], 'keyword' ), array_column( (array) ( $seed['gaps'] ?? array() ), 'keyword' ), array_column( (array) ( $seed['rankings'] ?? array() ), 'keyword' ) );
	$ids   = aw_si_kw_ids( $all, AW_SI_ROLE_TRACKED );
	$count = array(
		'map'      => 0,
		'gaps'     => 0,
		'rankings' => 0,
		'pages'    => 0,
		'failed'   => 0,
	);
	$fail  = 0; // Writes that did not succeed, and phrases that got no record: the seed then runs again next time.
	foreach ( $seed['map'] as $row ) {
		$id = (int) ( $ids[ aw_si_norm( $row['keyword'] ) ] ?? 0 );
		if ( ! $id ) {
			++$fail;
			continue;
		}
		++$count['map'];
		$set = array(
			'page_type' => $row['page_type'],
			'intent'    => mb_substr( (string) $row['intent'], 0, 16 ),
			'entity'    => '{organisation name}' === $row['keyword'] ? 1 : 0,
		);
		if ( 'primary' === $row['kind'] && $row['priority'] ) {
			$set['priority'] = $row['priority']; // The 21 Oct priority until aw_si_compute() recalculates it.
		}
		if ( 'topic' === $row['page_type'] && 0 !== strpos( (string) $row['page']['label'], '/' ) ) { // A path (/categories/) is a page, not a topic.
			$set['topic'] = mb_substr( (string) $row['page']['label'], 0, 100 );
		}
		$fail                     += false === $wpdb->update( $k, $set, array( 'id' => $id ) ) ? 1 : 0; // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		list( $type, $obj, $path ) = aw_si_resolve_page( (array) $row['page'], (string) $row['page_type'] );
		$fail                     += aw_si_link_page( $id, $type, $obj, $path, 'primary' === $row['kind'] ? 'owner' : 'secondary', 'seed' ) ? 0 : 1;
		++$count['pages'];
		if ( null !== $row['trends'] ) {
			$fail += aw_si_obs_put( $id, '', 'trends', 'index', '12m', '2026-10-15', array( 'value' => (float) $row['trends'] ) ) ? 0 : 1;
		}
		if ( null !== $row['rank'] ) {
			$num   = is_int( $row['rank'] ) ? (float) $row['rank'] : null;
			$fail += aw_si_obs_put(
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
			) ? 0 : 1;
		}
	}
	foreach ( (array) ( $seed['gaps'] ?? array() ) as $g ) {
		$id = (int) ( $ids[ aw_si_norm( $g['keyword'] ) ] ?? 0 );
		if ( ! $id ) {
			++$fail;
		} else {
			$fail += false === $wpdb->query( $wpdb->prepare( "UPDATE {$k} SET note = %s WHERE id = %d AND note = ''", mb_substr( 'Gap (15 Oct): ' . $g['gap'], 0, 255 ), $id ) ) ? 1 : 0; // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			++$count['gaps'];
		}
	}
	foreach ( (array) ( $seed['rankings'] ?? array() ) as $r ) {
		$id = (int) ( $ids[ aw_si_norm( $r['keyword'] ) ] ?? 0 );
		if ( ! $id ) {
			++$fail;
			continue;
		}
		++$count['rankings'];
		$fail += aw_si_obs_put(
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
		) ? 0 : 1;
		if ( '' !== (string) $r['top3'] ) {
			$fail += aw_si_obs_put( $id, '', 'live', 'top3', 'month', $r['date'], array( 'txt' => (string) $r['top3'] ) ) ? 0 : 1;
		}
		if ( '' !== (string) $r['paa'] ) {
			$fail += aw_si_obs_put( $id, '', 'live', 'paa', 'month', $r['date'], array( 'txt' => (string) $r['paa'] ) ) ? 0 : 1;
		}
		if ( '' !== (string) $r['url'] ) {
			$fail += aw_si_link_page( $id, 'path', 0, aw_si_path( (string) $r['url'] ), 'live', 'seed' ) ? 0 : 1;
		}
	}
	$count['failed'] = $fail;
	if ( ! $fail ) {
		update_option( 'aw_si_seeded', time(), false ); // Only a complete seed is marked; every write replaces, so a repeat is safe.
	}
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
 * @return bool Whether the row was written.
 */
function aw_si_obs_put( int $kw_id, string $page, string $src, string $metric, string $period, string $d, array $m ): bool {
	global $wpdb;
	return false !== $wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
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
 * - sourcing: AW Sourcing's Include list (aw_relevance()['strong']), when AW Sourcing is active and before its phase 2
 *   (from phase 2 the owner sets Include on the record and this leaves it alone);
 * - brand: AW_SI_BRAND.
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
	// Load every linked post and term in a few queries before building their links.
	_prime_post_caches( array_map( 'intval', wp_list_pluck( (array) $rows, 'ID' ) ), false, false );
	_prime_term_caches( array_map( 'intval', wp_list_pluck( (array) $terms, 'ID' ) ), false );
	$rows_out = array();
	foreach ( $links as $l ) {
		$id = $ids[ aw_si_norm( $l[0] ) ] ?? 0;
		if ( $id ) {
			$rows_out[] = array( $id, $l[1], $l[2], aw_si_object_path( $l[1], $l[2] ), $l[3], 'focus' );
		}
	}
	aw_si_link_pages( $rows_out );

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

	// Sourcing: read only, and only when AW Sourcing is active (the section hides otherwise). From sourcing phase 2 the
	// Include role is set on the records themselves (Settings → Sourcing → Keywords) and is never rebuilt here.
	$sourcing = array();
	if ( (int) get_option( 'aw_sourcing_phase', 0 ) < 2 ) { // the option itself: AW Sourcing may be off while this runs
		$wpdb->query( $wpdb->prepare( "UPDATE {$k} SET roles = roles & ~%d", AW_SI_ROLE_SOURCING ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sourcing = function_exists( 'aw_relevance' ) ? (array) ( aw_relevance()['strong'] ?? array() ) : array();
		if ( $sourcing ) {
			aw_si_kw_ids( $sourcing, AW_SI_ROLE_SOURCING );
		}
	} else {
		$sourcing = (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$k} WHERE roles & %d", AW_SI_ROLE_SOURCING ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- counted for the run log only
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

/**
 * Category of a post: its Rank Math primary category, else its first category other than the default one.
 *
 * @param int $post_id Post ID.
 * @return string Category name, or ''.
 */
function aw_si_post_topic( int $post_id ): string {
	$primary = (int) get_post_meta( $post_id, 'rank_math_primary_category', true );
	if ( $primary ) {
		$term = get_term( $primary, 'category' );
		if ( $term && ! is_wp_error( $term ) ) {
			return wp_specialchars_decode( (string) $term->name, ENT_QUOTES ); // Stored as &amp;; kept as plain text.
		}
	}
	$default = (int) get_option( 'default_category' );
	foreach ( (array) get_the_terms( $post_id, 'category' ) as $term ) {
		if ( $term instanceof WP_Term && (int) $term->term_id !== $default ) {
			return wp_specialchars_decode( (string) $term->name, ENT_QUOTES );
		}
	}
	return '';
}

/**
 * Topic of each keyword (plan §8, the Keywords topic filter): the site category it belongs to, from
 * 1. a category page that owns or carries it;
 * 2. else the category of the post that owns it, then of a post that carries it;
 * 3. else, for a keyword with no topic yet, the category of the post Google shows most for it (up to 1,500
 *    different pages a run; a topic found this way is kept, so later runs only look up new keywords).
 * A keyword with none of these keeps the topic it has (the keyword map's). Writes only the keyword table.
 *
 * @return array{set: int, from_links: int, from_search: int} Records changed, and how many topics came from where.
 */
function aw_si_sync_topics(): array {
	global $wpdb;
	$k     = aw_si_table( 'keywords' );
	$p     = aw_si_table( 'kw_pages' );
	$topic = array(); // kw_id => [rank, name]; the lowest rank wins.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	$links = (array) $wpdb->get_results( "SELECT kw_id, obj_type, obj_id, role FROM {$p} WHERE role IN ('owner', 'secondary') AND obj_type IN ('post', 'term') AND obj_id > 0" );
	$posts = array_unique( array_map( 'intval', wp_list_pluck( wp_list_filter( $links, array( 'obj_type' => 'post' ) ), 'obj_id' ) ) );
	_prime_post_caches( $posts, true, true );
	$names = array();
	foreach ( $links as $l ) {
		$rank = 'term' === $l->obj_type ? 0 : ( 'owner' === $l->role ? 1 : 2 );
		$key  = $l->obj_type . ':' . $l->obj_id;
		if ( ! isset( $names[ $key ] ) ) {
			$term          = 'term' === $l->obj_type ? get_term( (int) $l->obj_id ) : null;
			$names[ $key ] = 'term' === $l->obj_type
				? ( $term && ! is_wp_error( $term ) && 'category' === $term->taxonomy ? wp_specialchars_decode( (string) $term->name, ENT_QUOTES ) : '' )
				: aw_si_post_topic( (int) $l->obj_id );
		}
		$id = (int) $l->kw_id;
		if ( '' !== $names[ $key ] && ( ! isset( $topic[ $id ] ) || $rank < $topic[ $id ][0] ) ) {
			$topic[ $id ] = array( $rank, $names[ $key ] );
		}
	}
	$linked = count( $topic );

	// Keywords with no linked category: the post Google shows most (the page column the daily calculation fills).
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	$shown = (array) $wpdb->get_results( "SELECT id, page FROM {$k} WHERE topic = '' AND page LIKE '/%' AND page NOT LIKE '%{%'" );
	$paths = array();
	foreach ( $shown as $r ) {
		if ( ! isset( $topic[ (int) $r->id ] ) ) {
			$paths[ (string) $r->page ][] = (int) $r->id;
		}
	}
	uasort( $paths, fn( $a, $b ) => count( $b ) <=> count( $a ) );
	foreach ( array_slice( $paths, 0, 1500, true ) as $path => $ids ) {
		$post = url_to_postid( home_url( (string) $path ) );
		$name = $post ? aw_si_post_topic( $post ) : '';
		if ( '' === $name ) {
			$term = get_term_by( 'slug', basename( untrailingslashit( (string) $path ) ), 'category' );
			$name = $term && 0 === strpos( (string) $path, '/category/' ) ? wp_specialchars_decode( (string) $term->name, ENT_QUOTES ) : '';
		}
		if ( '' !== $name ) {
			foreach ( $ids as $id ) {
				$topic[ $id ] = array( 3, $name );
			}
		}
	}

	$by_name = array();
	foreach ( $topic as $id => $t ) {
		$by_name[ mb_substr( $t[1], 0, 100 ) ][] = (int) $id;
	}
	// A path stored as a topic by the first seed (the /categories/ hub) is not a topic.
	$set = (int) $wpdb->query( "UPDATE {$k} SET topic = '' WHERE topic LIKE '/%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	foreach ( $by_name as $name => $ids ) {
		foreach ( array_chunk( $ids, 500 ) as $chunk ) {
			$in   = implode( ',', array_map( 'intval', $chunk ) );
			$set += (int) $wpdb->query( $wpdb->prepare( "UPDATE {$k} SET topic = %s WHERE id IN ({$in}) AND topic <> %s", $name, $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integers.
		}
	}
	return array(
		'set'         => $set,
		'from_links'  => $linked,
		'from_search' => count( $topic ) - $linked,
	);
}
