<?php
/**
 * News attribution, Google News pattern.
 *
 * - [aw_source_row]          favicon (16px) + publisher name, plain text, for cards (the card itself is the link).
 * - [aw_source_row link="1"] the same row on the item page, linked to the original (new tab, noopener nofollow).
 * - Relative time ("3 hours ago", then the date after a week) on news card dates.
 * - Favicons fetched once per publisher host into the media library by a cron job; letter fallback until then.
 * - News and legal slugs never end with the publisher (e.g. -bollyspice-com); old URLs 301 via WordPress's old-slug redirect.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Publisher name and host for an item.
 *
 * @param int $id Post ID.
 * @return array{0: string, 1: string} name, host.
 */
function aw_publisher_of( $id ) {
	$url  = (string) get_post_meta( $id, 'aw_source_url', true );
	$host = function_exists( 'aw_host' ) ? aw_host( $url ) : '';
	$name = (string) get_post_meta( $id, 'aw_source_name', true );
	$name = preg_replace( '/\s+[–-]\s+(Energy & Environment|Circulars|Notifications)$/u', '', $name ); // feed labels are not the publisher
	return array( $name ? $name : $host, $host );
}

/**
 * Favicon URL for a host from the media library, or '' while it has not been fetched.
 *
 * @param string $host Host.
 * @return string
 */
function aw_favicon_url( $host ) {
	$p  = function_exists( 'aw_publishers' ) ? aw_publishers() : array();
	$id = (int) ( $p[ $host ]['icon'] ?? 0 );
	return $id ? (string) wp_get_attachment_image_url( $id, 'thumbnail' ) : '';
}

/**
 * Source row: favicon + publisher.
 *
 * @param int  $id   Post ID.
 * @param bool $link Link to the original.
 * @return string
 */
function aw_source_row_html( $id, $link = false ) {
	list( $name, $host ) = aw_publisher_of( $id );
	if ( '' === $name ) {
		return '';
	}
	$icon = $host ? aw_favicon_url( $host ) : '';
	$mark = $icon
		? '<img class="aw-src-icon" src="' . esc_url( $icon ) . '" alt="" width="16" height="16" loading="lazy" decoding="async">'
		: '<span class="aw-src-icon aw-src-icon--letter" aria-hidden="true">' . esc_html( mb_strtoupper( mb_substr( $name, 0, 1 ) ) ) . '</span>';
	$url  = (string) get_post_meta( $id, 'aw_source_url', true );
	$text = '<span class="aw-src-name">' . esc_html( $name ) . '</span>';
	if ( $link && $url ) {
		return '<p class="aw-src aw-src--link"><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener nofollow">' . $mark . $text . '</a></p>';
	}
	return '<p class="aw-src">' . $mark . $text . '</p>';
}

add_shortcode(
	'aw_source_row',
	function ( $atts ) {
		$a = shortcode_atts( array( 'link' => '0' ), $atts );
		return aw_source_row_html( (int) get_the_ID(), '1' === (string) $a['link'] );
	}
);

/**
 * Best publish timestamp: the ingest time when it is the same day as the source date, else noon on the source date.
 *
 * @param int $id Post ID.
 * @return int
 */
function aw_published_ts( $id ) {
	$src  = (string) get_post_meta( $id, 'aw_source_date', true );
	$post = (int) get_post_time( 'U', true, $id );
	if ( ! $src ) {
		return $post;
	}
	$day = strtotime( $src . ' 12:00:00 Asia/Kolkata' );
	return ( wp_date( 'Y-m-d', $post ) === $src ) ? min( $post, time() ) : $day;
}

/**
 * Relative time, Google News style: "3 hours ago", "2 days ago", then "5 Oct".
 *
 * @param int $id Post ID.
 * @return string <time> element.
 */
function aw_rel_time_html( $id ) {
	$ts   = aw_published_ts( $id );
	$diff = time() - $ts;
	if ( $diff < 7 * DAY_IN_SECONDS && $diff >= 0 ) {
		/* translators: %s: human time difference, e.g. 3 hours. */
		$label = $diff < MINUTE_IN_SECONDS * 2 ? __( 'Just now', 'animal-writes-core' ) : sprintf( __( '%s ago', 'animal-writes-core' ), human_time_diff( $ts, time() ) );
	} else {
		$label = wp_date( wp_date( 'Y' ) === wp_date( 'Y', $ts ) ? 'j M' : 'j M Y', $ts );
	}
	return '<time class="aw-rel-time" datetime="' . esc_attr( gmdate( 'c', $ts ) ) . '" title="' . esc_attr( wp_date( 'j F Y, g:i a', $ts ) ) . '">' . esc_html( $label ) . '</time>';
}

add_shortcode( 'aw_rel_time', fn() => aw_rel_time_html( (int) get_the_ID() ) );

// News card dates (core post-date block inside query loops) use relative time.
add_filter(
	'render_block_core/post-date',
	function ( $html, $block, $instance ) {
		$id = (int) ( $instance->context['postId'] ?? 0 );
		if ( ! $id || 'aw_news' !== get_post_type( $id ) || ( is_singular() && get_queried_object_id() === $id ) ) {
			return $html;
		}
		return '<div class="wp-block-post-date aw-card-time">' . aw_rel_time_html( $id ) . '</div>';
	},
	20,
	3
);

/* ---------- Favicons: once per host, into the media library ---------- */

add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'aw_fetch_favicons' ) ) {
			wp_schedule_event( time() + 60, 'hourly', 'aw_fetch_favicons' );
		}
	}
);

add_action(
	'aw_fetch_favicons',
	function () {
		if ( ! function_exists( 'aw_publishers' ) ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		$pubs = aw_publishers();
		$done = 0;
		foreach ( $pubs as $host => $p ) {
			if ( array_key_exists( 'icon', $p ) || ! (int) ( $p['count'] ?? 0 ) || $done >= 10 ) {
				continue;
			}
			$done++;
			$att = 0;
			foreach ( array( 'https://www.google.com/s2/favicons?sz=64&domain=' . rawurlencode( $host ), 'https://' . $host . '/favicon.ico' ) as $src ) {
				$tmp = download_url( $src, 15 );
				if ( is_wp_error( $tmp ) ) {
					continue;
				}
				$type = wp_get_image_mime( $tmp );
				if ( ! $type || filesize( $tmp ) < 100 ) {
					wp_delete_file( $tmp );
					continue;
				}
				$ext = array( 'image/png' => 'png', 'image/x-icon' => 'ico', 'image/vnd.microsoft.icon' => 'ico', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp' )[ $type ] ?? 'png';
				$id  = media_handle_sideload( array( 'name' => 'favicon-' . sanitize_title( $host ) . '.' . $ext, 'tmp_name' => $tmp ), 0, 'Favicon: ' . $host );
				if ( ! is_wp_error( $id ) ) {
					update_post_meta( $id, '_aw_favicon_host', $host );
					$att = (int) $id;
					break;
				}
			}
			$pubs[ $host ]['icon'] = $att; // 0 = tried, use the letter fallback
		}
		if ( $done ) {
			update_option( AW_PUB_OPTION, $pubs, false );
		}
	}
);

/* ---------- Slugs without the publisher ---------- */

/**
 * Slug without a trailing publisher (domain or name).
 *
 * @param string $slug Slug.
 * @param int    $id   Post ID.
 * @return string
 */
function aw_clean_news_slug( $slug, $id ) {
	list( $name, $host ) = aw_publisher_of( $id );
	$cands = array_filter(
		array_unique(
			array(
				sanitize_title( str_replace( '.', '-', $host ) ),
				sanitize_title( preg_replace( '/\.[a-z.]+$/', '', $host ) ),
				sanitize_title( $name ),
				sanitize_title( str_replace( '.', '-', (string) get_post_meta( $id, 'aw_source_name', true ) ) ),
			)
		)
	);
	usort( $cands, fn( $a, $b ) => strlen( $b ) - strlen( $a ) );
	$title = sanitize_title( get_post_field( 'post_title', $id ) );
	foreach ( $cands as $c ) {
		// Never cut words that are part of the headline itself (e.g. "... Animal Welfare Board of India").
		if ( str_ends_with( $title, '-' . $c ) || $title === $c ) {
			continue;
		}
		if ( strlen( $slug ) > strlen( $c ) + 10 && str_ends_with( $slug, '-' . $c ) ) {
			return substr( $slug, 0, -strlen( $c ) - 1 );
		}
	}
	return $slug;
}

// New items: strip the publisher when the slug is created at publish time.
add_filter(
	'wp_insert_post_data',
	function ( $data, $postarr ) {
		if ( ! in_array( $data['post_type'], array( 'aw_news', 'aw_legal' ), true ) || empty( $postarr['ID'] ) || empty( $data['post_name'] ) ) {
			return $data;
		}
		$clean = aw_clean_news_slug( $data['post_name'], (int) $postarr['ID'] );
		if ( $clean !== $data['post_name'] ) {
			$data['post_name'] = wp_unique_post_slug( $clean, (int) $postarr['ID'], $data['post_status'], $data['post_type'], (int) $data['post_parent'] );
		}
		return $data;
	},
	10,
	2
);
