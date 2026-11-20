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
 * Moved from Kits Core on 18 Nov 2026 with the same shortcode, filters, meta keys, option and scheduled event; the
 * functions are named sourceweb_* and keep their aw_* names as aliases (end of this file).
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A source meta value, dual-read through the rename: the new kits_source_* key, falling back to the legacy
 * aw_source_* key that the sourcing ingest (workers repo, out of scope) still writes. Drop the fallback once the
 * workers write the new key.
 *
 * @param int    $id    Post ID.
 * @param string $field url|name|date.
 * @return string
 */
function sourceweb_source_meta( $id, $field ) {
	$v = (string) get_post_meta( $id, 'kits_source_' . $field, true );
	return '' !== $v ? $v : (string) get_post_meta( $id, 'aw_source_' . $field, true );
}

/**
 * Publisher name and host for an item.
 *
 * @param int $id Post ID.
 * @return array{0: string, 1: string} name, host.
 */
function sourceweb_publisher_of( $id ) {
	$url  = (string) sourceweb_source_meta( $id, 'url' );
	$host = aw_host( $url );
	$name = (string) sourceweb_source_meta( $id, 'name' );
	$name = preg_replace( '/\s+[–-]\s+(Energy & Environment|Circulars|Notifications)$/u', '', $name ); // feed labels are not the publisher
	return array( $name ? $name : $host, $host );
}

/**
 * Favicon URL for a host from the media library, or '' while it has not been fetched.
 *
 * @param string $host Host.
 * @return string
 */
function sourceweb_favicon_url( $host ) {
	$p  = aw_publishers();
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
function sourceweb_source_row_html( $id, $link = false ) {
	list( $name, $host ) = sourceweb_publisher_of( $id );
	if ( '' === $name ) {
		return '';
	}
	$icon = $host ? sourceweb_favicon_url( $host ) : '';
	$mark = $icon
		? '<img class="kit-src-icon aw-src-icon" src="' . esc_url( $icon ) . '" alt="" width="16" height="16" loading="lazy" decoding="async">' // the name is printed beside it (kit-src-name), so the icon is decorative
		: '<span class="kit-src-icon kit-src-icon--letter aw-src-icon aw-src-icon--letter" aria-hidden="true">' . esc_html( mb_strtoupper( mb_substr( $name, 0, 1 ) ) ) . '</span>';
	$url  = (string) sourceweb_source_meta( $id, 'url' );
	$text = '<span class="kit-src-name aw-src-name">' . esc_html( $name ) . '</span>';
	if ( $link && $url ) {
		return '<p class="kit-src kit-src--link aw-src aw-src--link"><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener nofollow">' . $mark . $text . '</a></p>';
	}
	return '<p class="kit-src aw-src">' . $mark . $text . '</p>';
}

add_shortcode(
	'aw_source_row',
	function ( $atts ) {
		$a = shortcode_atts( array( 'link' => '0' ), $atts );
		return sourceweb_source_row_html( (int) get_the_ID(), '1' === (string) $a['link'] );
	}
);

/**
 * Best publish timestamp: the ingest time when it is the same day as the source date, else noon on the source date.
 *
 * @param int $id Post ID.
 * @return int
 */
function sourceweb_published_ts( $id ) {
	$src  = (string) sourceweb_source_meta( $id, 'date' );
	$post = (int) get_post_time( 'U', true, $id );
	if ( ! $src ) {
		return $post;
	}
	$day = strtotime( $src . ' 12:00:00 Asia/Kolkata' );
	return ( wp_date( 'Y-m-d', $post ) === $src ) ? min( $post, time() ) : $day;
}

/**
 * Relative time, Google News style: "3 hours ago", "2 days ago", then the date in the site format ("5 Oct 2026").
 *
 * @param int $id Post ID.
 * @return string <time> element.
 */
function sourceweb_rel_time_html( $id ) {
	$ts   = sourceweb_published_ts( $id );
	$diff = time() - $ts;
	if ( $diff < 7 * DAY_IN_SECONDS && $diff >= 0 && ! is_front_page() ) { // the home page shows the date, like its Library cards (owner, 18 Oct 2026)
		/* translators: %s: human time difference, e.g. 3 hours. */
		$label = $diff < MINUTE_IN_SECONDS * 2 ? __( 'Just now', 'aw-sourcing' ) : sprintf( __( '%s ago', 'aw-sourcing' ), human_time_diff( $ts, time() ) );
	} else {
		$label = wp_date( (string) get_option( 'date_format' ), $ts ); // the site's one date format, with the year
	}
	$src   = (string) sourceweb_source_meta( $id, 'date' );
	$exact = ! $src || wp_date( 'Y-m-d', (int) get_post_time( 'U', true, $id ) ) === $src; // only the date is known otherwise
	return '<time class="aw-rel-time" datetime="' . esc_attr( $exact ? gmdate( 'c', $ts ) : $src ) . '" title="' . esc_attr( wp_date( $exact ? get_option( 'date_format' ) . ', ' . get_option( 'time_format' ) : (string) get_option( 'date_format' ), $ts ) ) . '">' . esc_html( $label ) . '</time>';
}

// News card dates (core post-date block inside query loops) use relative time.
add_filter(
	'render_block_core/post-date',
	function ( $html, $block, $instance ) {
		$id = (int) ( $instance->context['postId'] ?? 0 );
		if ( ! $id || sourceweb_news_type() !== get_post_type( $id ) || ( is_singular() && get_queried_object_id() === $id ) ) {
			return $html;
		}
		return '<div class="wp-block-post-date kit-meta-item kit-meta-item--time aw-card-time">' . sourceweb_rel_time_html( $id ) . '</div>';
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

add_action( 'aw_fetch_favicons', 'sourceweb_fetch_favicons_run' ); // the hourly favicon run; a cross-plugin hook (InsightWeb's referrer icons listen on it and book it too) and already booked on live, so it keeps its name

// Switched off: the favicon run comes off (booked again on the next request after it is switched back on, or by
// InsightWeb's referrer icons while InsightWeb is active).
$sourceweb_deact_file = file_exists( dirname( __DIR__ ) . '/sourceweb.php' ) ? dirname( __DIR__ ) . '/sourceweb.php' : dirname( __DIR__ ) . '/aw-sourcing.php';
register_deactivation_hook(
	$sourceweb_deact_file,
	function () {
		wp_unschedule_hook( 'aw_fetch_favicons' );
	}
);

/** Fetch up to 10 publisher favicons into the media library (hourly cron). */
function sourceweb_fetch_favicons_run() {
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

/* ---------- Slugs without the publisher ---------- */

/**
 * Slug without a trailing publisher (domain or name).
 *
 * @param string $slug Slug.
 * @param int    $id   Post ID.
 * @return string
 */
function sourceweb_clean_news_slug( $slug, $id ) {
	list( $name, $host ) = sourceweb_publisher_of( $id );
	$cands = array_filter(
		array_unique(
			array(
				sanitize_title( str_replace( '.', '-', $host ) ),
				sanitize_title( preg_replace( '/\.[a-z.]+$/', '', $host ) ),
				sanitize_title( $name ),
				sanitize_title( str_replace( '.', '-', (string) sourceweb_source_meta( $id, 'name' ) ) ),
			)
		)
	);
	usort( $cands, fn( $a, $b ) => strlen( $b ) - strlen( $a ) );
	$title = sanitize_title( get_post_field( 'post_title', $id ) );
	foreach ( $cands as $c ) {
		// Never cut words that are part of the headline itself (e.g. "... Board of Education of India").
		if ( str_ends_with( $title, '-' . $c ) || $title === $c ) {
			continue;
		}
		if ( strlen( $slug ) > strlen( $c ) + 10 && str_ends_with( $slug, '-' . $c ) ) {
			return substr( $slug, 0, -strlen( $c ) - 1 );
		}
	}
	return $slug;
}

// Kits Core's slug for items published without one (slugs.php): the same rule.
add_filter(
	'kits_publish_slug_base',
	function ( $base, $post ) {
		return in_array( $post->post_type, kits_sourced_types(), true ) ? sourceweb_clean_news_slug( $base, $post->ID ) : $base;
	},
	10,
	2
);

// New items: strip the publisher when the slug is created at publish time.
add_filter(
	'wp_insert_post_data',
	function ( $data, $postarr ) {
		if ( ! in_array( $data['post_type'], kits_sourced_types(), true ) || empty( $postarr['ID'] ) || empty( $data['post_name'] ) ) {
			return $data;
		}
		$clean = sourceweb_clean_news_slug( $data['post_name'], (int) $postarr['ID'] );
		if ( $clean !== $data['post_name'] ) {
			$data['post_name'] = wp_unique_post_slug( $clean, (int) $postarr['ID'], $data['post_status'], $data['post_type'], (int) $data['post_parent'] );
		}
		return $data;
	},
	10,
	2
);

/** The post type whose news cards show relative time (the first sourced type). */
function sourceweb_news_type() {
	$t = kits_sourced_types();
	return (string) reset( $t );
}

/* ---------- The old names (Animal Writes Core, InsightWeb and SourceWeb's own files still call them) ---------- */

if ( ! function_exists( 'aw_publisher_of' ) ) {
	/**
	 * Alias of sourceweb_publisher_of().
	 *
	 * @param int $id Post ID.
	 */
	function aw_publisher_of( $id ) {
		return sourceweb_publisher_of( $id );
	}
}
if ( ! function_exists( 'aw_favicon_url' ) ) {
	/**
	 * Alias of sourceweb_favicon_url().
	 *
	 * @param string $host Host.
	 */
	function aw_favicon_url( $host ) {
		return sourceweb_favicon_url( $host );
	}
}
if ( ! function_exists( 'aw_source_row_html' ) ) {
	/**
	 * Alias of sourceweb_source_row_html().
	 *
	 * @param int  $id   Post ID.
	 * @param bool $link Link to the original.
	 */
	function aw_source_row_html( $id, $link = false ) {
		return sourceweb_source_row_html( $id, $link );
	}
}
if ( ! function_exists( 'aw_published_ts' ) ) {
	/**
	 * Alias of sourceweb_published_ts().
	 *
	 * @param int $id Post ID.
	 */
	function aw_published_ts( $id ) {
		return sourceweb_published_ts( $id );
	}
}
if ( ! function_exists( 'aw_rel_time_html' ) ) {
	/**
	 * Alias of sourceweb_rel_time_html().
	 *
	 * @param int $id Post ID.
	 */
	function aw_rel_time_html( $id ) {
		return sourceweb_rel_time_html( $id );
	}
}
