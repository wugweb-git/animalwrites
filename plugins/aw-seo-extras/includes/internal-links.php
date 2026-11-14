<?php
/**
 * Internal links, phase 1: the auto-linker (docs/rankweb-internal-links.md).
 *
 * - Each item has "Link keywords" (post meta _aw_link_keywords, a list): in the RankWeb content panel of the block
 *   editor and in a "Link keywords" box on the classic editor. Until it is saved, the item's Rank Math focus keywords
 *   stand in for it (the field shows them).
 * - The keyword index, table {prefix}aw_link_keywords (keyword, keyword_norm, target_id, priority), holds the link
 *   keywords of every published item of the linked post types. One item's rows are rewritten when it is saved
 *   (wp_after_insert_post: after the editor has saved the meta); the scheduled job aw_link_index_build rebuilds the
 *   whole index daily and after a Settings save.
 * - On the_content of a singular view of the main query, for an item of a linked type, AW_Linker (class-aw-linker.php)
 *   adds links. The output is cached per item (transient aw_seo_link_<id>), keyed by the item's modified time, the
 *   pages' version (option aw_link_page_version, raised by an address change, a rebuild or a deletion), the settings and the input HTML.
 *   The index is read once into a transient (aw_seo_link_index), so a page view never queries per keyword.
 * - Every link of an item, added or hand-made, is recorded in {prefix}aw_links (source_id, target_id, url, anchor,
 *   anchor_type, origin auto|manual, rel) when its linked output is built.
 *
 * Rank Math's link counter (wp_rank_math_internal_links) stays Rank Math's: it is read, never written.
 * With no linked post types (the default), no link is added.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-aw-linker.php';

const AW_LINK_DB_VERSION = '1';
const AW_LINK_DB_OPTION  = 'aw_link_db';
const AW_LINK_VERSION    = 'aw_link_index_version'; // raised on every index change (the index transient)
const AW_LINK_PAGE_VER   = 'aw_link_page_version'; // raised when pages must be linked again (address changes, rebuild)
const AW_LINK_META       = '_aw_link_keywords';
const AW_LINK_JOB        = 'aw_link_index_build';
const AW_LINK_MAX_WORDS  = 100; // characters per keyword
const AW_LINK_MAX_KWS    = 20; // keywords per item

/* ---------- Tables ---------- */

/**
 * A table name with the site prefix.
 *
 * @param string $name 'keywords' or 'links'.
 */
function aw_link_table( string $name ): string {
	global $wpdb;
	return $wpdb->prefix . ( 'keywords' === $name ? 'aw_link_keywords' : 'aw_links' );
}

/** Create or update the two tables when their version changes (one option read when nothing changed). */
function aw_link_install(): void {
	if ( get_option( AW_LINK_DB_OPTION ) === AW_LINK_DB_VERSION ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$c = $wpdb->get_charset_collate();
	$k = aw_link_table( 'keywords' );
	$l = aw_link_table( 'links' );
	dbDelta(
		"CREATE TABLE {$k} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		keyword varchar(191) NOT NULL DEFAULT '',
		keyword_norm varchar(191) NOT NULL DEFAULT '',
		target_id bigint(20) unsigned NOT NULL DEFAULT 0,
		priority smallint(5) NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		KEY keyword_norm (keyword_norm),
		KEY target_id (target_id)
		) {$c};"
	);
	dbDelta(
		"CREATE TABLE {$l} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		source_id bigint(20) unsigned NOT NULL DEFAULT 0,
		target_id bigint(20) unsigned NOT NULL DEFAULT 0,
		url varchar(255) NOT NULL DEFAULT '',
		anchor varchar(255) NOT NULL DEFAULT '',
		anchor_type varchar(16) NOT NULL DEFAULT '',
		origin varchar(8) NOT NULL DEFAULT '',
		rel varchar(64) NOT NULL DEFAULT '',
		updated datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY source_id (source_id),
		KEY target_id (target_id)
		) {$c};"
	);
	update_option( AW_LINK_DB_OPTION, AW_LINK_DB_VERSION, true );
}

add_action( 'admin_init', 'aw_link_install' );

/* ---------- Settings, read ---------- */

/** The linked post types that exist (the Settings field; empty: no links are added). */
function aw_link_types(): array {
	return array_values( array_filter( array_map( 'sanitize_key', (array) AW_LINK_TYPES ), 'post_type_exists' ) );
}

/** Item IDs never linked from or to. */
function aw_link_excluded(): array {
	return array_values( array_filter( array_map( 'absint', (array) AW_LINK_EXCLUDE ) ) );
}

/** The settings that change an item's linked output, as one string (part of its cache key). */
function aw_link_settings_key(): string {
	return md5( (string) wp_json_encode( array( aw_link_types(), aw_link_excluded(), (int) AW_LINK_MAX, (int) AW_LINK_MAX_PER_TARGET, (int) AW_LINK_FIRST_ONLY, (int) AW_LINK_NO_SELF, (int) AW_LINK_SKIP_LINKED ) ) );
}

/* ---------- Link keywords of an item ---------- */

/**
 * A list of link keywords, cleaned: plain text, trimmed, at most 100 characters each, no repeats (ignoring case),
 * at most 20. Also the meta's sanitize callback.
 *
 * @param mixed $value List, or text with one keyword per line or comma.
 */
function aw_link_keywords_clean( $value ): array {
	if ( ! is_array( $value ) ) {
		$value = preg_split( '/[\r\n,]+/', (string) $value );
	}
	$out = array();
	foreach ( $value as $kw ) {
		$kw = trim( mb_substr( sanitize_text_field( (string) $kw ), 0, AW_LINK_MAX_WORDS ) );
		if ( '' !== $kw && ! isset( $out[ mb_strtolower( $kw ) ] ) ) {
			$out[ mb_strtolower( $kw ) ] = $kw;
		}
	}
	return array_slice( array_values( $out ), 0, AW_LINK_MAX_KWS );
}

/**
 * The item's Rank Math focus keywords.
 *
 * @param int $id Item ID.
 */
function aw_link_focus_keywords( int $id ): array {
	return aw_link_keywords_clean( explode( ',', (string) get_post_meta( $id, 'rank_math_focus_keyword', true ) ) );
}

/** Whether the item's link keywords were saved (until then its focus keywords stand in). */
function aw_link_keywords_saved( int $id ): bool {
	return metadata_exists( 'post', $id, AW_LINK_META );
}

/**
 * The item's link keywords: as saved, else its focus keywords.
 *
 * @param int $id Item ID.
 */
function aw_link_keywords( int $id ): array {
	return aw_link_keywords_saved( $id ) ? aw_link_keywords_clean( get_post_meta( $id, AW_LINK_META, true ) ) : aw_link_focus_keywords( $id );
}

/**
 * Whether a keyword may link: at least 3 characters, and not one word from "Words that alone make no keyword"
 * (Focus keywords settings).
 *
 * @param string $norm Normalised keyword.
 */
function aw_link_keyword_ok( string $norm ): bool {
	if ( mb_strlen( $norm ) < 3 ) {
		return false;
	}
	$generic = defined( 'AW_SEO_GENERIC_WORDS' ) ? array_map( 'mb_strtolower', (array) AW_SEO_GENERIC_WORDS ) : array();
	return false !== strpos( $norm, ' ' ) || ! in_array( $norm, $generic, true );
}

add_action(
	'init',
	function () {
		register_post_meta(
			'',
			AW_LINK_META,
			array(
				'type'              => 'array',
				'single'            => true,
				'description'       => __( 'Link keywords: other items link to this one where these words appear.', 'aw-seo-extras' ),
				'sanitize_callback' => 'aw_link_keywords_clean',
				'auth_callback'     => fn( $allowed, $key, $post_id ) => current_user_can( 'edit_posts' ) && current_user_can( 'edit_post', (int) $post_id ),
				'show_in_rest'      => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type'      => 'string',
							'maxLength' => AW_LINK_MAX_WORDS,
						),
					),
				),
			)
		);
	}
);

/* ---------- The keyword index ---------- */

/**
 * Whether an item is in the index (a link target): published, no password, a linked type, not left out.
 *
 * @param WP_Post $post Item.
 */
function aw_link_is_target( WP_Post $post ): bool {
	return 'publish' === $post->post_status && '' === $post->post_password && in_array( $post->post_type, aw_link_types(), true ) && ! in_array( (int) $post->ID, aw_link_excluded(), true );
}

/**
 * The index rows of one item: array( keyword, keyword_norm, target_id, priority ). The first keyword has priority 100,
 * the next 99, and so on.
 *
 * @param int   $id       Item ID.
 * @param array $keywords Its link keywords.
 */
function aw_link_rows( int $id, array $keywords ): array {
	$rows = array();
	$seen = array();
	foreach ( array_values( $keywords ) as $i => $kw ) {
		$norm = AW_Linker::normalize( (string) $kw );
		if ( '' === $norm || isset( $seen[ $norm ] ) || ! aw_link_keyword_ok( $norm ) || strlen( $norm ) > 191 ) {
			continue;
		}
		$seen[ $norm ] = true;
		$rows[]        = array( mb_substr( (string) $kw, 0, 191 ), $norm, $id, 100 - $i );
	}
	return $rows;
}

/**
 * Insert index rows, 300 at a time.
 *
 * @param array $rows Rows from aw_link_rows().
 */
function aw_link_insert_rows( array $rows ): void {
	global $wpdb;
	$table = aw_link_table( 'keywords' );
	foreach ( array_chunk( $rows, 300 ) as $chunk ) {
		$sql  = array();
		$args = array( $table );
		foreach ( $chunk as $r ) {
			$sql[] = '(%s,%s,%d,%d)';
			array_push( $args, $r[0], $r[1], $r[2], $r[3] );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders -- RankWeb's own table; the placeholders are built above.
		$wpdb->query( $wpdb->prepare( 'INSERT INTO %i (keyword, keyword_norm, target_id, priority) VALUES ' . implode( ',', $sql ), $args ) );
	}
}

/**
 * Raise the index version (the keyword index is read again). With $pages, also the pages' version: every item's cached
 * linked output is built again on its next view. One item's keyword change raises only the index version, so a single
 * edit does not throw away every cached page; the new keyword reaches other pages at the next rebuild (daily, or after
 * a Settings save). An address change or a target gone raises both.
 *
 * @param bool $pages Also build the cached pages again.
 */
function aw_link_bump( bool $pages = false ): void {
	update_option( AW_LINK_VERSION, (int) get_option( AW_LINK_VERSION, 0 ) + 1, true );
	if ( $pages ) {
		update_option( AW_LINK_PAGE_VER, (int) get_option( AW_LINK_PAGE_VER, 0 ) + 1, true );
	}
	delete_transient( 'aw_seo_link_index' );
}

/**
 * Rewrite one item's index rows.
 *
 * @param int $id Item ID.
 */
function aw_link_index_item( int $id ): void {
	global $wpdb;
	aw_link_install();
	$post = get_post( $id );
	$rows = $post instanceof WP_Post && aw_link_is_target( $post ) ? aw_link_rows( $id, aw_link_keywords( $id ) ) : array();
	$have = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT keyword, keyword_norm, target_id, priority FROM %i WHERE target_id = %d ORDER BY priority DESC', aw_link_table( 'keywords' ), $id ), ARRAY_N ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- RankWeb's own table.
	$have = array_map( fn( $r ) => array( (string) $r[0], (string) $r[1], (int) $r[2], (int) $r[3] ), $have );
	if ( $have === $rows ) {
		return; // nothing changed: the cached pages stay
	}
	$wpdb->delete( aw_link_table( 'keywords' ), array( 'target_id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- RankWeb's own table.
	aw_link_insert_rows( $rows );
	aw_link_bump();
}

/** Rebuild the whole index (the scheduled job aw_link_index_build). */
function aw_link_index_build(): void {
	global $wpdb;
	aw_link_install();
	$types = aw_link_types();
	$rows  = array();
	if ( $types ) {
		$in   = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$list = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one read of every target; the job runs daily.
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- three named values, then one %s per type.
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders -- $in is a list of %s.
				"SELECT p.ID, MAX( CASE WHEN m.meta_key = %s THEN m.meta_value END ) AS kws, MAX( CASE WHEN m.meta_key = %s THEN 1 ELSE 0 END ) AS saved, MAX( CASE WHEN m.meta_key = 'rank_math_focus_keyword' THEN m.meta_value END ) AS focus FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key IN ( %s, 'rank_math_focus_keyword' ) WHERE p.post_status = 'publish' AND p.post_password = '' AND p.post_type IN ( {$in} ) GROUP BY p.ID",
				array_merge( array( AW_LINK_META, AW_LINK_META, AW_LINK_META ), $types )
			)
		);
		$skip = array_flip( aw_link_excluded() );
		foreach ( $list as $r ) {
			if ( isset( $skip[ (int) $r->ID ] ) ) {
				continue;
			}
			$kws = (int) $r->saved ? aw_link_keywords_clean( maybe_unserialize( (string) $r->kws ) ) : aw_link_keywords_clean( explode( ',', (string) $r->focus ) );
			foreach ( aw_link_rows( (int) $r->ID, $kws ) as $row ) {
				$rows[] = $row; // appended one by one: array_merge() in a loop copies the whole array each time
			}
		}
	}
	$sum  = md5( (string) wp_json_encode( $rows ) );
	$last = (array) get_option( 'aw_link_index_built', array() );
	$have = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', aw_link_table( 'keywords' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- RankWeb's own table.
	if ( ( $last['sum'] ?? '' ) !== $sum || count( $rows ) !== $have ) {
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', aw_link_table( 'keywords' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- RankWeb's own table, rebuilt whole.
		aw_link_insert_rows( $rows );
		aw_link_bump( true ); // only when the index changed, so the cached pages stay otherwise; the rebuild is when other pages learn a new keyword
	}
	update_option(
		'aw_link_index_built',
		array(
			'time' => time(),
			'sum'  => $sum,
			'rows' => count( $rows ),
		),
		false
	);
}
add_action( AW_LINK_JOB, 'aw_link_index_build' );

/**
 * The index as the linker reads it: keyword_norm => list of array( target_id, priority ), best five first
 * (highest priority, then the lowest ID). One read per index version, kept in a transient.
 */
function aw_link_index(): array {
	static $memo = null;
	$version     = (int) get_option( AW_LINK_VERSION, 0 );
	if ( null !== $memo && $memo['v'] === $version ) {
		return $memo['map'];
	}
	$hit = get_transient( 'aw_seo_link_index' );
	if ( ! is_array( $hit ) || ( $hit['v'] ?? -1 ) !== $version ) {
		global $wpdb;
		$map = array();
		if ( get_option( AW_LINK_DB_OPTION ) === AW_LINK_DB_VERSION ) {
			$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT keyword_norm, target_id, priority FROM %i ORDER BY keyword_norm, priority DESC, target_id', aw_link_table( 'keywords' ) ), ARRAY_N ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- read once per index version, cached below.
			foreach ( $rows as $r ) {
				if ( count( $map[ (string) $r[0] ] ?? array() ) < 5 ) { // the best five targets of a keyword are plenty (rows come best first)
					$map[ (string) $r[0] ][] = array( (int) $r[1], (int) $r[2] );
				}
			}
		}
		$hit = array(
			'v'   => $version,
			'map' => $map,
		);
		set_transient( 'aw_seo_link_index', $hit, DAY_IN_SECONDS );
	}
	$memo = $hit;
	return $hit['map'];
}

/**
 * Remove the links recorded for an item as the page they are in (when it is no longer published, or is deleted).
 *
 * @param int $id Item ID.
 */
function aw_link_forget_source( int $id ): void {
	global $wpdb;
	if ( get_option( AW_LINK_DB_OPTION ) !== AW_LINK_DB_VERSION ) {
		return;
	}
	$wpdb->delete( aw_link_table( 'links' ), array( 'source_id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- RankWeb's own table.
}

// An item saved (after the editor saved its meta and terms), trashed or unpublished: its own rows.
add_action(
	'wp_after_insert_post',
	function ( $post_id, $post, $update, $post_before ) {
		if ( ! $post instanceof WP_Post || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) || 'auto-draft' === $post->post_status ) {
			return;
		}
		aw_link_index_item( (int) $post_id );
		// An item that is not published (draft, trashed, private) is no source of links: its recorded links go.
		if ( 'publish' !== $post->post_status ) {
			aw_link_forget_source( (int) $post_id );
		}
		// A linked item's address changed (slug, parent or status): pages that link to it are built again.
		if ( $post_before instanceof WP_Post && in_array( $post->post_type, aw_link_types(), true ) && ( $post_before->post_name !== $post->post_name || $post_before->post_parent !== $post->post_parent || $post_before->post_status !== $post->post_status ) ) {
			aw_link_bump( true );
		}
	},
	20,
	4
);
// Rank Math saves the focus keyword in its own request after the post: an item still on its focus keywords is
// re-indexed then.
add_action(
	'updated_post_meta',
	function ( $meta_id, $post_id, $key ) {
		if ( 'rank_math_focus_keyword' === $key && ! aw_link_keywords_saved( (int) $post_id ) ) {
			aw_link_index_item( (int) $post_id );
		}
	},
	10,
	3
);
add_action(
	'added_post_meta',
	function ( $meta_id, $post_id, $key ) {
		if ( 'rank_math_focus_keyword' === $key && ! aw_link_keywords_saved( (int) $post_id ) ) {
			aw_link_index_item( (int) $post_id );
		}
	},
	10,
	3
);
add_action(
	'deleted_post',
	function ( $post_id ) {
		global $wpdb;
		if ( get_option( AW_LINK_DB_OPTION ) !== AW_LINK_DB_VERSION ) {
			return;
		}
		$gone = (int) $wpdb->delete( aw_link_table( 'keywords' ), array( 'target_id' => (int) $post_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- RankWeb's own table.
		aw_link_forget_source( (int) $post_id );
		if ( $gone ) {
			aw_link_bump( true ); // pages that linked to it
		}
	}
);

// The daily rebuild (scheduled from admin and cron requests only, so a page view never writes), and one after a
// Settings save that changed the linked types or the items left out.
add_action(
	'init',
	function () {
		if ( ( is_admin() || wp_doing_cron() ) && ! wp_next_scheduled( AW_LINK_JOB ) ) {
			wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'daily', AW_LINK_JOB );
		}
	}
);
add_action(
	'update_option_aw_seo_settings',
	function ( $old, $value ) {
		$part = fn( $v ) => array( $v['internal-links']['AW_LINK_TYPES'] ?? null, $v['internal-links']['AW_LINK_EXCLUDE'] ?? null );
		if ( $part( (array) $old ) !== $part( (array) $value ) && ! wp_next_scheduled( AW_LINK_JOB, array( 'now' ) ) ) {
			wp_schedule_single_event( time() + 10, AW_LINK_JOB, array( 'now' ) );
		}
	},
	10,
	2
);

/* ---------- The front end ---------- */

/**
 * Whether this the_content call is the main item of a singular view of a linked type.
 */
function aw_link_wanted(): bool {
	if ( is_admin() || is_feed() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || doing_filter( 'get_the_excerpt' ) ) {
		return false;
	}
	$types = aw_link_types();
	if ( ! $types || ! is_main_query() || ! is_singular( $types ) ) {
		return false;
	}
	$id = (int) get_the_ID();
	// The block theme runs no loop on single views: the item of the main query is the one being rendered when it is
	// also the queried one.
	return $id > 0 && get_queried_object_id() === $id && ! post_password_required( $id ) && ! in_array( $id, aw_link_excluded(), true );
}

/**
 * The_content: the item's HTML with its links, from the cache when nothing changed.
 *
 * @param string $html Content HTML.
 */
function aw_link_filter_content( $html ) {
	if ( ! is_string( $html ) || '' === $html || ! aw_link_wanted() ) {
		return $html;
	}
	$id   = (int) get_the_ID();
	$post = get_post( $id );
	if ( ! $post instanceof WP_Post ) {
		return $html;
	}
	// One cache entry per input: a page can run the_content more than once on different HTML (the theme's content
	// block, a plugin building a description), so the item keeps up to three entries, all of the same item state.
	$state = md5( $post->post_modified_gmt . '|' . (int) get_option( AW_LINK_PAGE_VER, 0 ) . '|' . aw_link_settings_key() );
	$input = md5( $html );
	$name  = 'aw_seo_link_' . $id;
	$hit   = get_transient( $name );
	$hit   = is_array( $hit ) && ( $hit['s'] ?? '' ) === $state ? $hit : array(
		's' => $state,
		'h' => array(),
	);
	if ( isset( $hit['h'][ $input ] ) ) {
		return 0 === $hit['h'][ $input ] ? $html : (string) $hit['h'][ $input ]; // 0: nothing was linked, the input stands
	}
	$result             = AW_Linker::link(
		$html,
		aw_link_index(),
		function ( int $tid ): string {
			$t = get_post( $tid );
			return $t instanceof WP_Post && aw_link_is_target( $t ) ? (string) get_permalink( $t ) : '';
		},
		array(
			'max_links'      => (int) AW_LINK_MAX,
			'max_per_target' => (int) AW_LINK_MAX_PER_TARGET,
			'first_only'     => (bool) AW_LINK_FIRST_ONLY,
			'self_id'        => $id,
			'no_self'        => (bool) AW_LINK_NO_SELF,
			'skip_linked'    => (bool) AW_LINK_SKIP_LINKED,
			'exclude'        => aw_link_excluded(),
			'home'           => home_url(),
		)
	);
	$hit['h']           = array_slice( $hit['h'], -2, 2, true );
	$hit['h'][ $input ] = $result['links'] ? $result['html'] : 0; // the HTML is kept only when a link was added
	set_transient( $name, $hit, WEEK_IN_SECONDS );
	// The links table, once the page is out, from the longest HTML this request linked (the whole content).
	static $best = array();
	if ( ! isset( $best[ $id ] ) ) {
		add_action(
			'shutdown',
			function () use ( &$best, $id ) {
				aw_link_store( $id, $best[ $id ]['r'] ); // by reference: the entry may be replaced later in the request
			}
		);
	}
	if ( strlen( $html ) >= ( $best[ $id ]['n'] ?? 0 ) ) {
		$best[ $id ] = array(
			'n' => strlen( $html ),
			'r' => $result,
		);
	}
	return $result['html'];
}
add_filter( 'the_content', 'aw_link_filter_content', 25 ); // runs after blocks, wpautop and shortcodes (priorities 9 to 11)

/**
 * Record an item's links: its added links (origin auto) and the links already in it (origin manual), replacing the
 * rows it had. A hand-made link's target is found from its address when it points at this site.
 *
 * @param int   $source Item ID.
 * @param array $result AW_Linker::link() result.
 */
function aw_link_store( int $source, array $result ): void {
	global $wpdb;
	if ( get_option( AW_LINK_DB_OPTION ) !== AW_LINK_DB_VERSION ) {
		return;
	}
	$home = AW_Linker::url_key( home_url() );
	$now  = current_time( 'mysql', true );
	$rows = array();
	$clip = fn( string $s, int $n ) => mb_substr( $s, 0, $n );
	foreach ( $result['links'] as $l ) {
		$rows[] = array( (int) $l['target_id'], $clip( (string) $l['url'], 255 ), $clip( (string) $l['anchor'], 255 ), 'auto', '' );
	}
	foreach ( $result['manual'] as $l ) {
		$key = AW_Linker::url_key( (string) $l['url'], home_url() );
		if ( '' === $key || ( 0 !== strpos( $key . '/', $home . '/' ) ) ) {
			continue; // links to other sites are not internal links
		}
		$abs    = 0 === strpos( (string) $l['url'], '/' ) ? home_url( (string) $l['url'] ) : (string) $l['url'];
		$anchor = '' !== (string) $l['anchor'] ? (string) $l['anchor'] : (string) $l['alt'];
		$rows[] = array( (int) url_to_postid( $abs ), $clip( $abs, 255 ), $clip( $anchor, 255 ), 'manual', $clip( (string) $l['rel'], 64 ) );
	}
	$table = aw_link_table( 'links' );
	// One writer per item: two views building the same page at once would each delete and then insert, leaving every
	// row twice. The named lock is the database's own (freed when the connection ends); a second writer just skips,
	// as the first is writing the same rows.
	$lock = 'aw_link_store_' . $wpdb->prefix . $source;
	if ( 1 !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, 0 )', $lock ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- a named lock.
		return;
	}
	// Nothing written when the rows are the ones already there (the usual rebuild after a version bump).
	$have = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT target_id, url, anchor, origin, rel FROM %i WHERE source_id = %d ORDER BY id', $table, $source ), ARRAY_N ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- RankWeb's own table.
	$have = array_map( fn( $r ) => array( (int) $r[0], (string) $r[1], (string) $r[2], (string) $r[3], (string) $r[4] ), $have );
	if ( $have !== $rows ) {
		$wpdb->delete( $table, array( 'source_id' => $source ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- RankWeb's own table.
		foreach ( $rows as $r ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- RankWeb's own table.
				$table,
				array(
					'source_id'   => $source,
					'target_id'   => $r[0],
					'url'         => $r[1],
					'anchor'      => $r[2],
					'anchor_type' => '',
					'origin'      => $r[3],
					'rel'         => $r[4],
					'updated'     => $now,
				),
				array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
		}
	}
	$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $lock ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- a named lock.
}

/* ---------- The classic editor: a "Link keywords" box ---------- */

add_action(
	'add_meta_boxes',
	function ( $post_type, $post = null ) {
		if ( ! $post instanceof WP_Post || ! is_post_type_viewable( (string) $post_type ) || ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		add_meta_box(
			'aw-link-keywords',
			__( 'Link keywords', 'aw-seo-extras' ),
			'aw_link_meta_box',
			$post_type,
			'side',
			'low',
			array( '__back_compat_meta_box' => true ) // the block editor has the field in the RankWeb content panel
		);
	},
	10,
	2
);

/**
 * The box: one keyword per line.
 *
 * @param WP_Post $post Item.
 */
function aw_link_meta_box( WP_Post $post ): void {
	$saved = aw_link_keywords_saved( (int) $post->ID );
	wp_nonce_field( 'aw_link_keywords_' . $post->ID, 'aw_link_keywords_nonce' );
	echo '<p><label for="aw-link-keywords-field">' . esc_html__( 'Other items link to this one where these words appear. One per line.', 'aw-seo-extras' ) . '</label></p>';
	echo '<textarea id="aw-link-keywords-field" name="aw_link_keywords" rows="5" class="widefat">' . esc_textarea( implode( "\n", aw_link_keywords( (int) $post->ID ) ) ) . '</textarea>';
	if ( ! $saved ) {
		echo '<p class="description">' . esc_html__( 'From the focus keywords until you save this box.', 'aw-seo-extras' ) . '</p>';
	}
	if ( ! in_array( $post->post_type, aw_link_types(), true ) ) {
		echo '<p class="description">' . esc_html__( 'The auto-linker is off for this post type (RankWeb → Settings → Internal links).', 'aw-seo-extras' ) . '</p>';
	}
}

add_action(
	'save_post',
	function ( $post_id ) {
		if ( ! isset( $_POST['aw_link_keywords'], $_POST['aw_link_keywords_nonce'] ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aw_link_keywords_nonce'] ) ), 'aw_link_keywords_' . $post_id ) || ! current_user_can( 'edit_posts' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$list = aw_link_keywords_clean( sanitize_textarea_field( wp_unslash( $_POST['aw_link_keywords'] ) ) );
		// Unchanged focus keywords left as shown: still "from the focus keywords", nothing stored.
		if ( ! aw_link_keywords_saved( (int) $post_id ) && aw_link_focus_keywords( (int) $post_id ) === $list ) {
			return;
		}
		update_post_meta( $post_id, AW_LINK_META, $list );
	}
);

/* ---------- The RankWeb content panel ---------- */

// POST rankweb/v1/links/keywords {id, keywords}: save an item's link keywords from the panel (its own route, as the
// post meta reaches the editor only for types that support custom fields). edit_posts and edit_post on the item;
// a signed-in browser sends the wp_rest nonce (cookie authentication).
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'rankweb/v1',
			'/links/keywords',
			array(
				'methods'             => 'POST',
				'args'                => array(
					'id'       => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1,
					),
					'keywords' => array(
						'type'     => 'array',
						'items'    => array( 'type' => 'string' ),
						'required' => true,
					),
				),
				'permission_callback' => function ( WP_REST_Request $req ) {
					if ( ! is_user_logged_in() ) {
						return new WP_Error( 'rest_not_logged_in', __( 'Sign in to use this.', 'aw-seo-extras' ), array( 'status' => 401 ) );
					}
					return current_user_can( 'edit_posts' ) && current_user_can( 'edit_post', (int) $req['id'] ) && get_post( (int) $req['id'] )
						? true
						: new WP_Error( 'rest_forbidden', __( 'You are not allowed to do this.', 'aw-seo-extras' ), array( 'status' => 403 ) );
				},
				'callback'            => function ( WP_REST_Request $req ) {
					$id   = (int) $req['id'];
					$list = aw_link_keywords_clean( (array) $req['keywords'] );
					update_post_meta( $id, AW_LINK_META, $list );
					aw_link_index_item( $id );
					return rest_ensure_response( aw_link_panel_data( get_post( $id ) ) );
				},
			)
		);
	}
);

/**
 * The panel's "Link keywords" data (content-advice.php adds it to its route).
 *
 * @param WP_Post $post Item.
 */
function aw_link_panel_data( WP_Post $post ): array {
	return array(
		'list'  => aw_link_keywords( (int) $post->ID ),
		'saved' => aw_link_keywords_saved( (int) $post->ID ),
		'on'    => in_array( $post->post_type, aw_link_types(), true ),
	);
}
