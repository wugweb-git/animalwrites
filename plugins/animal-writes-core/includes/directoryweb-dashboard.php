<?php
/**
 * DirectoryWeb Dashboard counts (docs/directory-plugin-plan.md §2.1): built by the hourly job directoryweb_stats_build,
 * never while a screen loads. The job keeps the newest counts in the transient directoryweb_stats (3 hours). The
 * Dashboard route only formats them; with nothing stored it asks for one single run and shows "Counting…".
 *
 * Each count reads one source: the listings and their tags, the place counts (aw_place_counts) and the LGD file, the
 * claim form's entries, the listings' Claimed and Verified marks, RankWeb's no-contact rule, and SourceWeb's change log.
 * A source that is not there is stored as null, and its tile says which plugin is missing.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const DIRECTORYWEB_STATS      = 'directoryweb_stats';
const DIRECTORYWEB_STATS_JOB  = 'directoryweb_stats_build';
const DIRECTORYWEB_CLAIM_ROWS = 'directoryweb_claim_rows';

add_action( DIRECTORYWEB_STATS_JOB, 'directoryweb_stats_build' );

// Hourly, scheduled from an admin request, so a page view never writes.
add_action(
	'admin_init',
	function () {
		if ( ! wp_next_scheduled( DIRECTORYWEB_STATS_JOB ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', DIRECTORYWEB_STATS_JOB );
		}
	}
);

/**
 * Ask for a count now (the Dashboard's first view, or Count now): one single event, never a count in this request.
 *
 * @return bool Whether a count is waiting to run.
 */
function directoryweb_stats_request(): bool {
	if ( wp_next_scheduled( DIRECTORYWEB_STATS_JOB, array( 'now' ) ) ) {
		return true;
	}
	return false !== wp_schedule_single_event( time(), DIRECTORYWEB_STATS_JOB, array( 'now' ) );
}

/**
 * The job: every count, stored together.
 *
 * @param string $why 'now' when asked for from the Dashboard, '' on the hourly run.
 * @return array The counts.
 */
function directoryweb_stats_build( $why = '' ) {
	unset( $why );
	$c = array(
		'at'         => time(),
		'listings'   => directoryweb_count_listings(),
		'places'     => directoryweb_count_places(),
		'indexing'   => directoryweb_count_indexing(),
		'claims'     => directoryweb_count_claims(),
		'badges'     => directoryweb_count_badges(),
		'enrichment' => directoryweb_count_enrichment(),
	);
	set_transient( DIRECTORYWEB_STATS, $c, 3 * HOUR_IN_SECONDS );
	return $c;
}

/**
 * Listings by post status, and published listings by listing type.
 *
 * @return array [ 'status' => [ publish, draft, pending, private ], 'types' => [ slug => n ] ].
 */
function directoryweb_count_listings(): array {
	global $wpdb;
	$n     = wp_count_posts( 'aw_listing' );
	$out   = array(
		'status' => array(
			'publish' => (int) ( $n->publish ?? 0 ),
			'draft'   => (int) ( $n->draft ?? 0 ),
			'pending' => (int) ( $n->pending ?? 0 ),
			'private' => (int) ( $n->private ?? 0 ),
		),
		'types'  => array(),
	);
	$slugs = function_exists( 'aw_listing_type_slugs' ) ? array_values( array_filter( array_map( 'strval', aw_listing_type_slugs() ) ) ) : array();
	if ( $slugs ) {
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- hourly count in the background
			$wpdb->prepare(
				"SELECT t.slug, COUNT( DISTINCT p.ID ) n FROM {$wpdb->posts} p JOIN {$wpdb->term_relationships} r ON r.object_id = p.ID JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = r.term_taxonomy_id AND tt.taxonomy = 'post_tag' JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE p.post_type = 'aw_listing' AND p.post_status = 'publish' AND t.slug IN ( " . implode( ', ', array_fill( 0, count( $slugs ), '%s' ) ) . ' ) GROUP BY t.slug',
				$slugs
			)
		);
		foreach ( (array) $rows as $r ) {
			$out['types'][ (string) $r->slug ] = (int) $r->n;
		}
	}
	return $out;
}

/**
 * States and districts with a published listing, of the LGD file's totals.
 *
 * @return array|null [ 'states', 'districts', 'states_all', 'districts_all', 'version', 'source' ].
 */
function directoryweb_count_places(): ?array {
	if ( ! function_exists( 'aw_place_counts' ) || ! function_exists( 'aw_lgd' ) ) {
		return null;
	}
	$c   = aw_place_counts();
	$lgd = aw_lgd();
	return array(
		'states'        => count( array_filter( (array) ( $c['state'] ?? array() ) ) ),
		'districts'     => count( array_filter( (array) ( $c['district'] ?? array() ) ) ),
		'states_all'    => count( (array) ( $lgd['states'] ?? array() ) ),
		'districts_all' => count( (array) ( $lgd['districts'] ?? array() ) ),
		'version'       => directoryweb_lgd_head()['version'],
	);
}

/**
 * The place file's source and version (data/lgd.json).
 *
 * @return array [ 'source' => …, 'version' => … ].
 */
function directoryweb_lgd_head(): array {
	static $head = null;
	if ( null === $head ) {
		$raw  = is_readable( AW_CORE_DIR . 'data/lgd.json' ) ? json_decode( (string) file_get_contents( AW_CORE_DIR . 'data/lgd.json' ), true ) : array(); // phpcs:ignore WordPress.WP.AlternativeFunctions -- a bundled file
		$head = array(
			'source'  => (string) ( $raw['source'] ?? '' ),
			'version' => (string) ( $raw['version'] ?? '' ),
		);
	}
	return $head;
}

/**
 * Published listings RankWeb keeps out of the index for having no contact, and the ones kept indexed on purpose.
 *
 * @return array|null Null when RankWeb's rule is not there.
 */
function directoryweb_count_indexing(): ?array {
	if ( ! function_exists( 'aw_sitemap_unreachable_listings' ) ) {
		return null;
	}
	return array(
		'nocontact' => count( (array) aw_sitemap_unreachable_listings() ),
		'kept'      => count( (array) get_option( 'aw_seo_keep_listings', array() ) ),
	);
}

/**
 * Claims by status, with the oldest waiting one: claim form entries and old-form claims.
 *
 * @return array|null Null when there is no claim form and no old-form claim.
 */
function directoryweb_count_claims(): ?array {
	$out  = array(
		'pending'  => 0,
		'approved' => 0,
		'rejected' => 0,
		'oldest'   => '',
		'form'     => function_exists( 'aw_claim_ff_id' ) ? aw_claim_ff_id() : 0,
	);
	$seen = false;
	foreach ( directoryweb_claim_light_rows() as $r ) {
		$seen = true;
		++$out[ $r['status'] ];
		if ( 'pending' === $r['status'] && ( '' === $out['oldest'] || $r['sent'] < $out['oldest'] ) ) {
			$out['oldest'] = $r['sent'];
		}
	}
	return $seen || $out['form'] ? $out : null;
}

/**
 * Published listings marked Claimed, Verified, and Needs re-check.
 *
 * @return array [ 'claimed', 'verified', 'recheck' ].
 */
function directoryweb_count_badges(): array {
	global $wpdb;
	$count  = fn( string $key, string $value ) => (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- hourly count in the background
		$wpdb->prepare(
			"SELECT COUNT( DISTINCT p.ID ) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s WHERE p.post_type = 'aw_listing' AND p.post_status = 'publish' AND m.meta_value = %s",
			$key,
			$value
		)
	);
	$filled = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- hourly count in the background
		"SELECT COUNT( DISTINCT p.ID ) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'aw_gov_verified_on' WHERE p.post_type = 'aw_listing' AND p.post_status = 'publish' AND m.meta_value <> ''"
	);
	return array(
		'claimed'  => $count( 'aw_verified', '1' ),
		'verified' => $filled,
		'recheck'  => $count( 'aw_needs_reverify', '1' ),
	);
}

/**
 * SourceWeb's enrichment queue: waiting proposals, listings waiting for Google, and the newest change-log row.
 *
 * @return array|null Null when SourceWeb is not active.
 */
function directoryweb_count_enrichment(): ?array {
	if ( ! function_exists( 'aw_enrich_progress' ) || ! function_exists( 'aw_enrich_table' ) ) {
		return null;
	}
	global $wpdb;
	$p    = aw_enrich_progress();
	$last = get_option( 'aw_enrich_db' ) ? (string) $wpdb->get_var( 'SELECT MAX( created ) FROM ' . aw_enrich_table() ) : ''; // phpcs:ignore WordPress.DB -- hourly count in the background; the table name is SourceWeb's
	return array(
		'pending' => (int) $p['pending'],
		'checked' => (int) $p['checked'],
		'total'   => (int) $p['total'],
		'google'  => (int) $wpdb->get_var( "SELECT COUNT( DISTINCT post_id ) FROM {$wpdb->postmeta} WHERE meta_key = 'aw_enrich_google_pending' AND meta_value = '1'" ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- hourly count in the background
		'last'    => $last,
	);
}

/**
 * Every claim as a light row (no listing comparison): claim form entries (Fluent Forms) and old-form claims
 * (aw_claim posts). Used by the count job and the Claims list's filter.
 *
 * @return array[] [ id, entry, old, status, sent, listing, name, role, email, phone, has, review ].
 */
function directoryweb_claim_light_rows(): array {
	$fp     = directoryweb_claim_rows_fingerprint();
	$cached = get_transient( DIRECTORYWEB_CLAIM_ROWS );
	if ( is_array( $cached ) && isset( $cached['fp'], $cached['rows'] ) && $fp === $cached['fp'] ) {
		return (array) $cached['rows'];
	}
	$rows = directoryweb_claim_light_rows_read();
	set_transient(
		DIRECTORYWEB_CLAIM_ROWS,
		array(
			'fp'   => $fp,
			'rows' => $rows,
		),
		10 * MINUTE_IN_SECONDS
	);
	return $rows;
}

/**
 * An old-form claim's details or decision changed (claims.php writes them with update_post_meta, which leaves the
 * post's modified date as it is): read the claim rows again.
 *
 * @param int    $meta_id  Unused.
 * @param int    $post_id  Post.
 * @param string $meta_key Meta key.
 */
function directoryweb_claim_rows_forget( $meta_id, $post_id, $meta_key ): void {
	unset( $meta_id );
	if ( 0 === strpos( (string) $meta_key, 'aw_c_' ) && 'aw_claim' === get_post_type( (int) $post_id ) ) {
		delete_transient( DIRECTORYWEB_CLAIM_ROWS );
	}
}
add_action( 'added_post_meta', 'directoryweb_claim_rows_forget', 10, 3 );
add_action( 'updated_post_meta', 'directoryweb_claim_rows_forget', 10, 3 );
add_action( 'deleted_post_meta', 'directoryweb_claim_rows_forget', 10, 3 );

/**
 * What the claim rows depend on, in two small queries: the claim form's entries (how many, the newest, the newest
 * change) and their reviews (the newest review row and change), plus the old-form claims (how many, the newest change).
 * Any new entry, trashed entry, review or old-form edit changes it, so a stored list is never out of date.
 *
 * @return string
 */
function directoryweb_claim_rows_fingerprint(): string {
	global $wpdb;
	$form  = function_exists( 'aw_claim_ff_id' ) ? aw_claim_ff_id() : 0;
	$parts = array( (string) $form );
	if ( $form ) {
		$parts[] = (string) wp_json_encode(
			$wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- a fingerprint of the claim form's entries
				$wpdb->prepare(
					"SELECT COUNT(*) n, MAX( s.id ) last_id, MAX( s.updated_at ) changed, ( SELECT CONCAT( COUNT(*), '|', COALESCE( MAX( m.id ), 0 ), '|', COALESCE( MAX( m.updated_at ), '' ), '|', COALESCE( SUM( CHAR_LENGTH( m.value ) ), 0 ) ) FROM {$wpdb->prefix}fluentform_submission_meta m JOIN {$wpdb->prefix}fluentform_submissions r ON r.id = m.response_id AND r.form_id = %d WHERE m.meta_key = 'aw_claim_review' ) reviews FROM {$wpdb->prefix}fluentform_submissions s WHERE s.form_id = %d AND s.status <> 'trashed'",
					$form,
					$form
				),
				ARRAY_A
			)
		);
	}
	if ( post_type_exists( 'aw_claim' ) ) {
		$parts[] = (string) wp_json_encode(
			$wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- a fingerprint of the old-form claims
				"SELECT COUNT(*) n, MAX( post_modified_gmt ) changed FROM {$wpdb->posts} WHERE post_type = 'aw_claim' AND post_status NOT IN ( 'trash', 'auto-draft' )",
				ARRAY_A
			)
		);
	}
	return md5( implode( '#', $parts ) );
}

/**
 * Every claim as a light row, read from the database (directoryweb_claim_light_rows() keeps it for 10 minutes).
 *
 * @return array[]
 */
function directoryweb_claim_light_rows_read(): array {
	global $wpdb;
	$out  = array();
	$form = function_exists( 'aw_claim_ff_id' ) ? aw_claim_ff_id() : 0;
	if ( $form ) {
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the claim form's entries
			$wpdb->prepare(
				"SELECT s.id, s.serial_number, s.response, s.created_at, m.value review FROM {$wpdb->prefix}fluentform_submissions s LEFT JOIN {$wpdb->prefix}fluentform_submission_meta m ON m.response_id = s.id AND m.meta_key = 'aw_claim_review' WHERE s.form_id = %d AND s.status <> 'trashed' ORDER BY s.id DESC",
				$form
			),
			ARRAY_A
		);
		foreach ( (array) $rows as $r ) {
			$d      = (array) json_decode( (string) $r['response'], true );
			$s      = fn( $k ) => is_array( $d[ $k ] ?? '' ) ? implode( ',', array_map( 'strval', $d[ $k ] ) ) : trim( (string) ( $d[ $k ] ?? '' ) );
			$lid    = absint( $d['listing'] ?? 0 );
			$review = (array) json_decode( (string) $r['review'], true );
			$role   = $s( 'role' );
			$out[]  = array(
				'id'      => 'ff-' . (int) $r['id'],
				'entry'   => (int) $r['id'],
				'ref'     => (int) $r['serial_number'] ? 'AW-C-' . (int) $r['serial_number'] : '', // as aw_ff_ref( 'claim', id )
				'old'     => false,
				'status'  => directoryweb_claim_status( $review ),
				'sent'    => (string) $r['created_at'],
				'listing' => $lid,
				'name'    => $s( 'name' ),
				'role'    => defined( 'AW_CLAIM_ROLES' ) ? ( AW_CLAIM_ROLES[ $role ] ?? $role ) : $role,
				'email'   => $s( 'email' ),
				'phone'   => $s( 'phone' ),
				'has'     => array(
					'logo'   => '' !== $s( 'logo_token' ),
					'photos' => '' !== $s( 'photo_tokens' ),
					'proof'  => '' !== $s( 'proof_token' ),
				),
				'review'  => $review,
			);
		}
	}
	if ( post_type_exists( 'aw_claim' ) ) {
		$ids = get_posts(
			array(
				'post_type'      => 'aw_claim',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		update_meta_cache( 'post', $ids );
		foreach ( $ids as $id ) {
			$role  = (string) get_post_meta( $id, 'aw_c_role', true );
			$out[] = array(
				'id'      => 'old-' . (int) $id,
				'entry'   => (int) $id,
				'ref'     => '',
				'old'     => true,
				'status'  => directoryweb_claim_status( (string) get_post_meta( $id, 'aw_c_status', true ) ),
				'sent'    => (string) get_post_field( 'post_date', $id ),
				'listing' => (int) get_post_meta( $id, 'aw_c_listing', true ),
				'name'    => (string) get_post_meta( $id, 'aw_c_name', true ),
				'role'    => defined( 'AW_CLAIM_ROLES' ) ? ( AW_CLAIM_ROLES[ $role ] ?? $role ) : $role,
				'email'   => (string) get_post_meta( $id, 'aw_c_email', true ),
				'phone'   => (string) get_post_meta( $id, 'aw_c_phone', true ),
				'has'     => array(
					'logo'   => '' !== (string) get_post_meta( $id, 'aw_c_logo', true ),
					'photos' => false,
					'proof'  => false,
				),
				'review'  => array(),
			);
		}
	}
	return $out;
}
