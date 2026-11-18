<?php
/**
 * RankWeb PRO, phase C: video detection, VideoObject schema and the video sitemap (docs/rankweb-pro-audit.md, C).
 *
 * Rank Math keeps video SEO for Pro. RankWeb does it with no paid API:
 * - On save, an item of a video post type is scanned (AW_Video, class-aw-video.php) for YouTube and Vimeo videos in its
 *   content. Each one's title, thumbnail and (Vimeo) description, duration and upload date are fetched from the
 *   provider's public oEmbed endpoint and kept in post meta _aw_videos. A YouTube thumbnail is the deterministic
 *   i.ytimg.com address, so it needs no request; YouTube's oEmbed gives the title only (its API, which would give the
 *   duration and date, is paid, so those stay empty for YouTube).
 * - On the page, each video is added to Rank Math's JSON-LD graph as a VideoObject.
 * - A video sitemap (video-sitemap.xml) lists every item that has a video, with the Google video extension. It is
 *   served and listed the same way as the News sitemap (sitemap.php): Rank Math's generic <slug>-sitemap.xml rewrite
 *   sends it here, and it is added to the sitemap index and robots.txt.
 *
 * Module "video" (RankWeb → Settings → Modules). Daily backfill job aw_video_sync. With no video post types, nothing runs.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-aw-video.php';

const AW_VIDEO_META  = '_aw_videos';
const AW_VIDEO_JOB   = 'aw_video_sync';
const AW_VIDEO_TTL   = 7 * DAY_IN_SECONDS; // re-fetch a video's data at most weekly
const AW_VIDEO_BATCH = 100; // items the daily backfill does in one run

/** The post types scanned for videos (the setting; empty: nothing runs). */
function aw_video_types(): array {
	return array_values( array_filter( array_map( 'sanitize_key', (array) AW_VIDEO_TYPES ), 'post_type_exists' ) );
}

/* ---------- oEmbed ---------- */

/**
 * One video's data from its provider's public oEmbed endpoint, cached by provider and id in a transient. Returns
 * name, description, thumbnail, duration (seconds), upload_date (ISO 8601). Missing fields stay ''. Never throws.
 *
 * @param array $v A video from AW_Video::find()/parse_url().
 */
function aw_video_fetch( array $v ): array {
	$provider = (string) ( $v['provider'] ?? '' );
	$id       = (string) ( $v['id'] ?? '' );
	$key      = 'aw_seo_video_' . $provider . '_' . $id;
	$hit      = get_transient( $key );
	if ( is_array( $hit ) ) {
		return $hit;
	}
	$data     = array(
		'name'        => '',
		'description' => '',
		'thumbnail'   => 'youtube' === $provider ? 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg' : '',
		'duration'    => 0,
		'upload_date' => '',
	);
	$endpoint = 'youtube' === $provider
		? 'https://www.youtube.com/oembed?format=json&url=' . rawurlencode( (string) $v['watch'] )
		: ( 'vimeo' === $provider ? 'https://vimeo.com/api/oembed.json?url=' . rawurlencode( (string) $v['watch'] ) : '' );
	if ( '' !== $endpoint ) {
		$res = wp_remote_get( $endpoint, array( 'timeout' => 5 ) );
		if ( ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res ) ) {
			$json = json_decode( (string) wp_remote_retrieve_body( $res ), true );
			if ( is_array( $json ) ) {
				$data['name']        = sanitize_text_field( (string) ( $json['title'] ?? '' ) );
				$data['description'] = sanitize_textarea_field( (string) ( $json['description'] ?? '' ) );
				if ( ! empty( $json['thumbnail_url'] ) ) {
					$data['thumbnail'] = esc_url_raw( (string) $json['thumbnail_url'] );
				}
				$data['duration'] = max( 0, (int) ( $json['duration'] ?? 0 ) );
				if ( ! empty( $json['upload_date'] ) ) {
					$ts                  = strtotime( (string) $json['upload_date'] );
					$data['upload_date'] = $ts ? gmdate( 'Y-m-d', $ts ) : '';
				}
			}
		}
	}
	// A short cache when nothing came back, so a provider hiccup is retried soon; the full week when it worked.
	set_transient( $key, $data, '' === $data['name'] ? HOUR_IN_SECONDS : AW_VIDEO_TTL );
	return $data;
}

/* ---------- Per item ---------- */

/**
 * Scan an item and store its videos (detection plus each one's fetched data) in post meta. Returns the stored list.
 *
 * @param int $id Item ID.
 */
function aw_video_sync_item( int $id ): array {
	$post = get_post( $id );
	if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, aw_video_types(), true ) ) {
		delete_post_meta( $id, AW_VIDEO_META );
		return array();
	}
	$videos = array();
	foreach ( AW_Video::find( (string) $post->post_content ) as $v ) {
		$videos[] = array_merge( $v, aw_video_fetch( $v ) );
	}
	if ( $videos ) {
		update_post_meta( $id, AW_VIDEO_META, $videos );
	} else {
		delete_post_meta( $id, AW_VIDEO_META );
	}
	return $videos;
}

/** An item's stored videos. */
function aw_video_get( int $id ): array {
	$v = get_post_meta( $id, AW_VIDEO_META, true );
	return is_array( $v ) ? $v : array();
}

// On save (after the content is in), scan an item of a video type.
add_action(
	'wp_after_insert_post',
	function ( $post_id, $post ) {
		if ( ! $post instanceof WP_Post || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) || 'auto-draft' === $post->post_status ) {
			return;
		}
		if ( in_array( $post->post_type, aw_video_types(), true ) ) {
			aw_video_sync_item( (int) $post_id );
		}
	},
	20,
	2
);

/* ---------- The daily backfill ---------- */

/** Published items of the video types whose video meta is missing: a capped batch, oldest first. */
function aw_video_backfill_ids(): array {
	$types = aw_video_types();
	if ( ! $types ) {
		return array();
	}
	return get_posts(
		array(
			'post_type'        => $types,
			'post_status'      => 'publish',
			'posts_per_page'   => AW_VIDEO_BATCH,
			'orderby'          => 'modified',
			'order'            => 'ASC',
			'fields'           => 'ids',
			'suppress_filters' => true, // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters -- admin/background query over post IDs; filters suppressed so other plugins do not alter this internal set (capped or cached, never a front-end query).
			'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- the daily backfill, capped to AW_VIDEO_BATCH
				array(
					'key'     => AW_VIDEO_META,
					'compare' => 'NOT EXISTS',
				),
			),
		)
	);
}

/** Daily: scan a batch of items that have no stored video data yet. */
function aw_video_sync(): void {
	foreach ( aw_video_backfill_ids() as $id ) {
		aw_video_sync_item( (int) $id );
	}
}
add_action( AW_VIDEO_JOB, 'aw_video_sync' );
add_action(
	'init',
	function () {
		if ( ( is_admin() || wp_doing_cron() ) && aw_video_types() && ! wp_next_scheduled( AW_VIDEO_JOB ) ) {
			wp_schedule_event( time() + 25 * MINUTE_IN_SECONDS, 'daily', AW_VIDEO_JOB );
		}
	}
);

/* ---------- VideoObject in the graph ---------- */

add_filter(
	'rank_math/json_ld',
	function ( $data ) {
		$types = aw_video_types();
		if ( ! is_array( $data ) || ! $types || ! is_singular( $types ) ) {
			return $data;
		}
		$id     = get_queried_object_id();
		$videos = aw_video_get( $id );
		if ( ! $videos ) {
			return $data;
		}
		$fallback = function_exists( 'rankweb_plain_text' ) ? (string) rankweb_plain_text( get_the_excerpt( $id ) ) : wp_strip_all_tags( (string) get_the_excerpt( $id ) );
		$n        = 0;
		foreach ( $videos as $v ) {
			$node = AW_Video::node( (array) $v, $fallback );
			if ( $node ) {
				++$n;
					$data[ 'aw-video-' . $n ] = $node;
			}
		}
		return $data;
	},
	20
);

/* ---------- The video sitemap: video-sitemap.xml ---------- */

/** The sitemap's address. */
function aw_video_sitemap_url(): string {
	return home_url( '/video-sitemap.xml' );
}

/**
 * Published items of the video types that have at least one fetched video, newest first.
 *
 * @return array List of array( loc, mod (ISO 8601), videos (each: a stored video with a name) ).
 */
function aw_video_sitemap_items(): array {
	$types = aw_video_types();
	if ( ! $types ) {
		return array();
	}
	$ids = get_posts(
		array(
			'post_type'        => $types,
			'post_status'      => 'publish',
			'posts_per_page'   => 2000, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- the video sitemap is capped and built on request, then cached by the server

			'orderby'          => 'modified',
			'order'            => 'DESC',
			'fields'           => 'ids',
			'has_password'     => false,
			'suppress_filters' => true, // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters -- admin/background query over post IDs; filters suppressed so other plugins do not alter this internal set (capped or cached, never a front-end query).
			'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- the video sitemap, built on request and cached by the server
				array(
					'key'     => AW_VIDEO_META,
					'compare' => 'EXISTS',
				),
			),
		)
	);
	$out = array();
	foreach ( array_map( 'intval', $ids ) as $id ) {
		if ( function_exists( 'kits_preview_only_ids' ) && in_array( $id, (array) kits_preview_only_ids(), true ) ) {
			continue;
		}
		$videos = array_values( array_filter( aw_video_get( $id ), fn( $v ) => '' !== trim( (string) ( $v['name'] ?? '' ) ) ) );
		if ( ! $videos ) {
			continue;
		}
		$out[] = array(
			'loc'    => get_permalink( $id ),
			'mod'    => get_post_modified_time( 'c', true, $id ),
			'videos' => $videos,
		);
	}
	return $out;
}

/** The video sitemap XML. */
function aw_video_sitemap_xml(): string {
	$fallback = get_bloginfo( 'name' );
	$x        = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";
	foreach ( aw_video_sitemap_items() as $item ) {
		$x .= "\t<url>\n\t\t<loc>" . esc_xml( $item['loc'] ) . "</loc>\n";
		foreach ( $item['videos'] as $v ) {
			$name   = trim( (string) ( $v['name'] ?? '' ) );
			$desc   = trim( (string) ( $v['description'] ?? '' ) );
			$desc   = '' !== $desc ? $desc : $fallback;
			$thumb  = trim( (string) ( $v['thumbnail'] ?? '' ) );
			$player = trim( (string) ( $v['embed'] ?? '' ) );
			if ( '' === $thumb || '' === $player ) {
				continue; // Google needs a thumbnail and a player or content address
			}
			$x  .= "\t\t<video:video>\n"
				. "\t\t\t<video:thumbnail_loc>" . esc_xml( $thumb ) . "</video:thumbnail_loc>\n"
				. "\t\t\t<video:title>" . esc_xml( $name ) . "</video:title>\n"
				. "\t\t\t<video:description>" . esc_xml( $desc ) . "</video:description>\n"
				. "\t\t\t<video:player_loc>" . esc_xml( $player ) . "</video:player_loc>\n";
			$dur = (int) ( $v['duration'] ?? 0 );
			if ( $dur > 0 && $dur <= 28800 ) {
				$x .= "\t\t\t<video:duration>" . (int) $dur . "</video:duration>\n";
			}
			$date = trim( (string) ( $v['upload_date'] ?? '' ) );
			if ( '' !== $date ) {
				$x .= "\t\t\t<video:publication_date>" . esc_xml( $date ) . "</video:publication_date>\n";
			}
			$x .= "\t\t</video:video>\n";
		}
		$x .= "\t</url>\n";
	}
	return $x . '</urlset>' . "\n";
}

// Rank Math's generic <slug>-sitemap.xml rewrite sends video-sitemap.xml here as sitemap=video (as News does).
add_action(
	'parse_request',
	function ( $wp ) {
		if ( 'video' !== ( $wp->query_vars['sitemap'] ?? '' ) || ! aw_video_types() ) {
			return;
		}
		do_action( 'litespeed_control_set_ttl', 1800 );
		if ( ! headers_sent() ) {
			status_header( 200 );
			header( 'Content-Type: application/xml; charset=UTF-8' );
			header( 'X-Robots-Tag: noindex, follow' );
			header( 'Cache-Control: public, max-age=1800' );
		}
		echo aw_video_sitemap_xml(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped per value in aw_video_sitemap_xml()
		exit;
	},
	0
);

// Listed in sitemap_index.xml, only when there is a video to list.
add_filter(
	'rank_math/sitemap/index',
	function ( $xml ) {
		$items = aw_video_sitemap_items();
		if ( ! $items ) {
			return $xml;
		}
		$mods = array_filter( wp_list_pluck( $items, 'mod' ) );
		$mod  = $mods ? max( $mods ) : '';
		return $xml . "\t<sitemap>\n\t\t<loc>" . esc_xml( aw_video_sitemap_url() ) . "</loc>\n" . ( '' !== $mod ? "\t\t<lastmod>" . esc_xml( $mod ) . "</lastmod>\n" : '' ) . "\t</sitemap>\n";
	}
);

// And in robots.txt.
add_filter(
	'robots_txt',
	function ( $txt ) {
		if ( ! aw_video_types() ) {
			return $txt;
		}
		$line = 'Sitemap: ' . aw_video_sitemap_url();
		return false !== strpos( (string) $txt, $line ) ? $txt : rtrim( (string) $txt ) . "\n" . $line . "\n";
	},
	61
);
