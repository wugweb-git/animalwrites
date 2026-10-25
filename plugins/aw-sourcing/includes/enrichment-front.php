<?php
/**
 * Directory enrichment on the listing page:
 * - "Details from" (the trust box) also lists the enrichment worker's sources, each with its site's icon.
 * - The live Google block: rating, open or closed now, hours, phone and the newest review's date, straight from
 *   Google Places with Google's attribution. Fetched after the page loads, cached 24 hours per place, never written to
 *   the listing; hidden when no key is saved or this month's cap is reached (Sourcing → Directory enrichment).
 *   Not in the schema (no aggregateRating from Google).
 * - Signals: activity on Instagram (followers, posts, latest post) and the News items that name the organisation.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Google Places (New) Place Details with reviews: Enterprise + Atmosphere, USD per request. */
const AW_GLIVE_COST = 0.025;

/* ---------- Sources line ---------- */

add_filter(
	'aw_listing_sources',
	function ( $out, $id ) {
		if ( ! function_exists( 'aw_enrich_listing_source_keys' ) ) {
			return $out;
		}
		$have = wp_list_pluck( $out, 0 );
		$regs = aw_enrich_sources();
		foreach ( aw_enrich_listing_source_keys( $id ) as $key => $src ) {
			$on = ! empty( $src['fetched_at'] ) ? wp_date( 'Y-m-d', strtotime( $src['fetched_at'] ) ) : '';
			if ( 'google_places' === $key ) {
				if ( ! in_array( 'gmaps', $have, true ) ) {
					$out[] = array( 'gmaps', 'MAPS', 'Google Maps', $on, (string) get_post_meta( $id, 'aw_maps_url', true ) );
				}
				continue;
			}
			if ( 'awbi' === $key && in_array( 'awbi', $have, true ) ) {
				continue;
			}
			if ( 'claim' === $key ) {
				if ( ! in_array( 'organisation', $have, true ) ) {
					$out[] = array( 'organisation', 'ORG', 'The organisation', $on, '' );
				}
				continue;
			}
			$name  = 'website' === $key ? 'The organisation\'s website' : ( $regs[ $key ][0] ?? $key );
			$link  = 'website' === $key ? (string) get_post_meta( $id, 'aw_website', true ) : (string) ( $src['url'] ?? '' );
			$name  = 'osm' === $key ? 'OpenStreetMap contributors (ODbL)' : $name;
			$out[] = array( 'enrich-' . $key, strtoupper( substr( $key, 0, 4 ) ), $name, $on, $link );
		}
		return $out;
	},
	10,
	2
);

/* ---------- Extras under the details ---------- */

/** Live Google block slot, activity signals and news mentions. */
function aw_enrich_profile_extras( $id ) {
	$m   = fn( $k ) => trim( (string) get_post_meta( $id, $k, true ) );
	$out = '';
	if ( $m( 'aw_place_id' ) && aw_glive_ready() ) {
		$api  = esc_url( rest_url( 'aw/v1/listing-live?id=' . (int) $id ) );
		$out .= '<div class="aw-glive-slot" data-src="' . $api . '"></div>'
			. '<script>(function(){var s=document.currentScript.previousElementSibling;if(!s||!window.fetch)return;fetch(s.dataset.src).then(function(r){return r.json()}).then(function(d){if(d&&d.html){s.insertAdjacentHTML("afterend",d.html);}s.remove();}).catch(function(){s.remove();});})();</script>';
	}
	$sig = array();
	if ( $m( 'aw_ig_followers' ) || $m( 'aw_last_post' ) ) {
		$bits = array();
		if ( $m( 'aw_ig_followers' ) ) {
			$bits[] = number_format_i18n( (int) $m( 'aw_ig_followers' ) ) . ' followers';
		}
		if ( $m( 'aw_ig_posts' ) ) {
			$bits[] = number_format_i18n( (int) $m( 'aw_ig_posts' ) ) . ' posts';
		}
		$sig[] = '<div class="aw-org-fact"><dt><span class="aw-org-fact-ico">' . aw_i( 'instagram' ) . '</span>On Instagram</dt><dd>' . esc_html( implode( ' · ', $bits ) ) . ( $m( 'aw_last_post' ) ? ( $bits ? ' · ' : '' ) . 'latest post ' . aw_date( $m( 'aw_last_post' ) ) : '' ) . '</dd></div>';
	}
	$news = aw_enrich_news_mentions( $id );
	if ( $news ) {
		$li = '';
		foreach ( $news as $nid ) {
			$li .= '<li><a href="' . esc_url( get_permalink( $nid ) ) . '">' . esc_html( get_the_title( $nid ) ) . '</a> ' . aw_date( get_the_date( 'Y-m-d', $nid ) ) . '</li>';
		}
		$sig[] = '<div class="aw-org-fact aw-org-fact--wide"><dt><span class="aw-org-fact-ico">' . aw_i( 'news' ) . '</span>In the news</dt><dd><ul class="aw-org-news">' . $li . '</ul></dd></div>';
	}
	if ( $sig ) {
		$out .= '<h2 class="aw-profile-h">Activity</h2><dl class="aw-org-facts">' . implode( '', $sig ) . '</dl>';
	}
	return $out;
}

/** Up to three News items that name the organisation (exact name, names of 2+ words and 10+ letters only), cached a day. */
function aw_enrich_news_mentions( $id ) {
	$name = trim( html_entity_decode( get_the_title( $id ), ENT_QUOTES ) );
	if ( str_word_count( $name ) < 2 || strlen( $name ) < 10 ) {
		return array();
	}
	$key = 'aw_newsm_' . $id;
	$ids = get_transient( $key );
	if ( false === $ids ) {
		$ids = get_posts( array( 'post_type' => 'aw_news', 'post_status' => 'publish', 's' => $name, 'sentence' => true, 'posts_per_page' => 3, 'fields' => 'ids', 'no_found_rows' => true, 'orderby' => 'date' ) );
		set_transient( $key, $ids, DAY_IN_SECONDS );
	}
	return (array) $ids;
}

/* ---------- Live Google block ---------- */

/** A key is saved and this month's cap leaves room for one more request. */
function aw_glive_ready() {
	$cap = (float) get_option( 'aw_enrich_live_cap', '20' );
	return '' !== (string) get_option( 'aw_enrich_google_key', '' ) && aw_enrich_spent( 'live' ) + AW_GLIVE_COST <= $cap && ! empty( aw_enrich_sources_on()['google_places'] );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'aw/v1',
			'/listing-live',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => function ( WP_REST_Request $req ) {
					$id = (int) $req['id'];
					if ( 'aw_listing' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
						return array( 'html' => '' );
					}
					$data = aw_glive_data( (string) get_post_meta( $id, 'aw_place_id', true ) );
					return array( 'html' => $data ? aw_glive_html( $data ) : '' );
				},
			)
		);
	}
);

/** Place details for the live block: from the 24-hour cache, else Google (not for crawlers, not past the cap). */
function aw_glive_data( $place_id ) {
	if ( ! preg_match( '/^[A-Za-z0-9_-]{10,}$/', $place_id ) ) {
		return null;
	}
	$key    = 'aw_glive_' . md5( $place_id );
	$cached = get_transient( $key );
	if ( is_array( $cached ) ) {
		return $cached ? $cached : null;
	}
	$ua = (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( '' === $ua || preg_match( '/bot|crawl|spider|slurp|preview|fetch|headless|lighthouse/i', $ua ) || ! aw_glive_ready() ) {
		return null;
	}
	$resp = wp_remote_get(
		'https://places.googleapis.com/v1/places/' . rawurlencode( $place_id ) . '?languageCode=en&regionCode=IN',
		array(
			'timeout' => 8,
			'headers' => array(
				'X-Goog-Api-Key'   => (string) get_option( 'aw_enrich_google_key', '' ),
				'X-Goog-FieldMask' => 'businessStatus,rating,userRatingCount,currentOpeningHours.openNow,regularOpeningHours.weekdayDescriptions,googleMapsUri,nationalPhoneNumber,reviews.publishTime',
			),
		)
	);
	aw_enrich_spend( AW_GLIVE_COST, 'live' );
	if ( is_wp_error( $resp ) || 200 !== wp_remote_retrieve_response_code( $resp ) ) {
		set_transient( $key, array(), HOUR_IN_SECONDS ); // a failure is not retried for an hour
		return null;
	}
	$d      = (array) json_decode( wp_remote_retrieve_body( $resp ), true );
	$newest = '';
	foreach ( (array) ( $d['reviews'] ?? array() ) as $r ) {
		$newest = max( $newest, (string) ( $r['publishTime'] ?? '' ) );
	}
	$data = array(
		'status'  => (string) ( $d['businessStatus'] ?? '' ),
		'rating'  => isset( $d['rating'] ) ? (float) $d['rating'] : null,
		'count'   => (int) ( $d['userRatingCount'] ?? 0 ),
		'open'    => $d['currentOpeningHours']['openNow'] ?? null,
		'hours'   => array_values( (array) ( $d['regularOpeningHours']['weekdayDescriptions'] ?? array() ) ),
		'maps'    => (string) ( $d['googleMapsUri'] ?? '' ),
		'phone'   => (string) ( $d['nationalPhoneNumber'] ?? '' ),
		'review'  => $newest ? substr( $newest, 0, 10 ) : '',
		'fetched' => time(),
	);
	set_transient( $key, $data, DAY_IN_SECONDS ); // Google's data is kept for 24 hours at most
	return $data;
}

/** The live block. */
function aw_glive_html( $d ) {
	$rows = array();
	if ( null !== $d['rating'] && $d['count'] ) {
		$rows[] = array( 'star', 'Rating', esc_html( number_format_i18n( $d['rating'], 1 ) . ' out of 5 (' . number_format_i18n( $d['count'] ) . ' reviews)' ) );
	}
	$state = array( 'CLOSED_TEMPORARILY' => 'Temporarily closed', 'CLOSED_PERMANENTLY' => 'Permanently closed' )[ $d['status'] ] ?? '';
	if ( $state ) {
		$rows[] = array( 'alert', 'Status', esc_html( $state ) );
	} elseif ( null !== $d['open'] ) {
		$rows[] = array( 'clock', 'Now', $d['open'] ? 'Open now' : 'Closed now' );
	}
	if ( $d['hours'] ) {
		$rows[] = array( 'clock', 'Hours', '<ul class="aw-glive-hours">' . implode( '', array_map( fn( $h ) => '<li>' . esc_html( $h ) . '</li>', $d['hours'] ) ) . '</ul>' );
	}
	if ( $d['phone'] && function_exists( 'aw_tel_url' ) && aw_tel_url( $d['phone'] ) ) {
		$rows[] = array( 'phone', 'Phone', '<a href="' . esc_url( aw_tel_url( $d['phone'] ) ) . '" data-act="phone">' . esc_html( $d['phone'] ) . '</a>' );
	}
	if ( $d['review'] ) {
		$rows[] = array( 'calendar', 'Latest review', aw_date( $d['review'] ) );
	}
	if ( ! $rows ) {
		return '';
	}
	$facts = '';
	foreach ( $rows as $r ) {
		$facts .= '<div class="aw-org-fact' . ( 'Hours' === $r[1] ? ' aw-org-fact--wide' : '' ) . '"><dt><span class="aw-org-fact-ico">' . aw_i( $r[0] ) . '</span>' . esc_html( $r[1] ) . '</dt><dd>' . $r[2] . '</dd></div>';
	}
	$attr = '<p class="aw-glive-attr">' . ( $d['maps'] ? '<a href="' . esc_url( $d['maps'] ) . '" rel="noopener nofollow" target="_blank">View on Google Maps</a> · ' : '' ) . 'Live from Google Maps, ' . aw_date( (int) $d['fetched'] ) . '</p>';
	return '<section class="aw-glive" aria-label="Live from Google Maps"><h2 class="aw-profile-h">On Google Maps</h2><dl class="aw-org-facts">' . $facts . '</dl>' . $attr . '</section>';
}
