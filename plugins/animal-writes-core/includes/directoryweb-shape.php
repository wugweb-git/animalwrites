<?php
/**
 * DirectoryWeb screens: the pure parts (no WordPress calls), so tests/directoryweb-tests.php runs them without
 * WordPress. Claim status, the Claims list's filter, sort and pages, a proposed phone list's check, the change-log name
 * of a claim field, the claim form choice, and the top listing types of the Dashboard.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A claim's status from its review: pending, approved or rejected.
 *
 * @param mixed $review The claim's review (aw_claim_review) or an old-form status string.
 */
function directoryweb_claim_status( $review ): string {
	$st = is_array( $review ) ? (string) ( $review['status'] ?? '' ) : (string) $review;
	return in_array( $st, array( 'approved', 'rejected' ), true ) ? $st : 'pending';
}

/**
 * The Claims list: filter, sort and one page of light rows.
 *
 * A row: [ status, sent (Y-m-d H:i:s), listing_title, name, state (code), types (slugs), has (logo, photos, proof) ].
 *
 * @param array $rows  Light rows.
 * @param array $q     status (pending|approved|rejected|all), search, state, type, has, days, sort (sent|listing), dir, page, per, now (time).
 * @return array [ 'rows' => page rows, 'total' => n, 'pages' => n, 'page' => n, 'counts' => [ status => n ] ].
 */
function directoryweb_claims_query( array $rows, array $q ): array {
	$counts = array(
		'pending'  => 0,
		'approved' => 0,
		'rejected' => 0,
		'all'      => 0,
	);
	$words  = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( (string) ( $q['search'] ?? '' ) ) ) : strtolower( trim( (string) ( $q['search'] ?? '' ) ) );
	$days   = (int) ( $q['days'] ?? 0 );
	$since  = $days > 0 ? gmdate( 'Y-m-d H:i:s', (int) ( $q['now'] ?? time() ) - $days * 86400 ) : '';
	$keep   = array();
	foreach ( $rows as $r ) {
		if ( '' !== $words ) {
			$hay = $r['listing_title'] . ' ' . $r['name'];
			$hay = function_exists( 'mb_strtolower' ) ? mb_strtolower( $hay ) : strtolower( $hay );
			if ( false === strpos( $hay, $words ) ) {
				continue;
			}
		}
		if ( ! empty( $q['state'] ) && (int) $q['state'] !== (int) $r['state'] ) {
			continue;
		}
		if ( ! empty( $q['type'] ) && ! in_array( (string) $q['type'], (array) $r['types'], true ) ) {
			continue;
		}
		if ( ! empty( $q['has'] ) && empty( $r['has'][ (string) $q['has'] ] ) ) {
			continue;
		}
		if ( '' !== $since && (string) $r['sent'] < $since ) {
			continue;
		}
		++$counts[ $r['status'] ];
		++$counts['all'];
		$keep[] = $r;
	}
	$status = (string) ( $q['status'] ?? 'pending' );
	if ( 'all' !== $status ) {
		$keep = array_values( array_filter( $keep, fn( $r ) => $r['status'] === $status ) );
	}
	$by  = 'listing' === ( $q['sort'] ?? '' ) ? 'listing_title' : 'sent';
	$dir = 'asc' === ( $q['dir'] ?? '' ) ? 1 : -1;
	usort( $keep, fn( $a, $b ) => $dir * strcasecmp( (string) $a[ $by ], (string) $b[ $by ] ) );
	$per   = max( 1, (int) ( $q['per'] ?? 20 ) );
	$total = count( $keep );
	$pages = max( 1, (int) ceil( $total / $per ) );
	$page  = min( $pages, max( 1, (int) ( $q['page'] ?? 1 ) ) );
	return array(
		'rows'   => array_slice( $keep, ( $page - 1 ) * $per, $per ),
		'total'  => $total,
		'pages'  => $pages,
		'page'   => $page,
		'counts' => $counts,
	);
}

/**
 * A proposed phone list has a line that is not a phone number.
 *
 * @param string   $lines    One "number | label | WhatsApp" a line.
 * @param callable $phone_ok Is one value a phone number.
 */
function directoryweb_phones_bad( string $lines, callable $phone_ok ): bool {
	foreach ( explode( "\n", $lines ) as $l ) {
		if ( '' !== trim( $l ) && ! $phone_ok( explode( '|', $l )[0] ) ) {
			return true;
		}
	}
	return false;
}

/**
 * The change-log field a claim field is logged as (aw_claim_apply_field()).
 *
 * @param string $key    AW_CLAIM_L key, logo or photos.
 * @param array  $fields AW_CLAIM_L.
 */
function directoryweb_enrich_field( string $key, array $fields ): string {
	if ( 'logo' === $key || 'photos' === $key ) {
		return $key;
	}
	$def = $fields[ $key ] ?? null;
	if ( ! $def ) {
		return '';
	}
	return array(
		'phones'   => 'phone',
		'services' => 'services',
		'about'    => 'about',
	)[ $def[1] ] ?? (string) $def[2];
}

/**
 * The form map with a new claim form, or null when nothing changes (an unchanged Save writes nothing).
 *
 * @param array $ids The option aw_ff_forms: key => form ID.
 * @param int   $id  The claim form's ID.
 * @return array|null
 */
function directoryweb_claim_form_change( array $ids, int $id ): ?array {
	if ( $id <= 0 || (int) ( $ids['claim'] ?? 0 ) === $id ) {
		return null;
	}
	$ids['claim'] = $id;
	return $ids;
}

/**
 * The most common listing types, with the rest as one number.
 *
 * @param array $by  slug => count.
 * @param int   $top How many to name.
 * @return array [ 'top' => [ slug => n ], 'others' => n ].
 */
function directoryweb_top_types( array $by, int $top = 5 ): array {
	$by = array_filter( array_map( 'intval', $by ) );
	arsort( $by );
	return array(
		'top'    => array_slice( $by, 0, $top, true ),
		'others' => (int) array_sum( array_slice( $by, $top, null, true ) ),
	);
}

/**
 * Whole days between a date and now.
 *
 * @param string $at  Y-m-d H:i:s (site time).
 * @param int    $now The same clock as $at, as a timestamp.
 */
function directoryweb_age_days( string $at, int $now ): int {
	$t = strtotime( $at );
	return false === $t ? 0 : max( 0, (int) floor( ( $now - $t ) / 86400 ) );
}
