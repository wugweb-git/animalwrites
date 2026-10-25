<?php
/**
 * Ingested items (News, Library, research) whose publisher image is missing or won't load get a free CC0 / public
 * domain photo from Openverse that matches their category (owner, 12 Oct 2026). No credit is shown; the source and
 * licence are stored on the attachment (aw_cc0_source, aw_cc0_license) for our records.
 *
 * Every 15 minutes (WP-Cron, aw_cc0_run), newest items first:
 *  0. Items (News, Library, research, Opportunities) whose ingest stored no publisher image, or whose image failed the
 *     check: the source page's og:image (else twitter:image) is read once (aw_source_og_tried; one page a second,
 *     descriptive User-Agent) and, when found, stored as aw_source_image and checked at once (owner, 15 Oct 2026:
 *     ingested items use the publisher's own image; a category photo never stands in for it in shares).
 *  1. Check publisher images (aw_source_image): GET → 200, an image content type, at least 200×200 when the size is
 *     readable. A publisher's logo or stock picture is still the publisher's image and is used (owner, 14 Oct 2026). Stored as
 *     aw_source_image_ok = 1/0 and aw_source_image_checked = date; failures are re-checked weekly.
 *  2. Items with no usable publisher image and no featured image: search Openverse (cc0, pdm; WordPress Photo
 *     Directory first, then Flickr / rawpixel / Wikimedia) for the animal named in the title, else the category's animal.
 *     Each Openverse image is used once on the whole site (aw_openverse_id and the file's md5 are checked).
 *     The photo is resized to 1600 px wide, added to the Media Library and set as the item's featured image.
 *     No match: tried again after 7 days, at most 3 times (aw_cc0_status, aw_cc0_attempts).
 *
 * Openverse: no key, a descriptive User-Agent, one request per 2 s, at most AW_CC0_DAILY_REQUESTS a day; result
 * pages are cached for a week. Counts and the log: Tools → Check site.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_CC0_UA             = 'AnimalWrites/1.0 (+https://animalwrites.in; admin@wugweb.com) image finder';
const AW_CC0_BATCH          = 30;
const AW_CC0_BUDGET         = 150; // seconds per run
const AW_CC0_DAILY_REQUESTS = 300;
const AW_CC0_MAX_ATTEMPTS   = 3;

add_filter(
	'cron_schedules',
	function ( $s ) {
		$s['aw_15min'] = array( 'interval' => 15 * MINUTE_IN_SECONDS, 'display' => 'Every 15 minutes' );
		return $s;
	}
);
add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'aw_cc0_run' ) ) {
			wp_schedule_event( time() + 120, 'aw_15min', 'aw_cc0_run' );
		}
	}
);
add_action( 'aw_cc0_run', 'aw_cc0_run' );

/** Append a line to the log (last 200 lines). */
function aw_cc0_log( $msg ) {
	$log   = (array) get_option( 'aw_cc0_log', array() );
	$log[] = wp_date( 'Y-m-d H:i' ) . ' ' . $msg;
	update_option( 'aw_cc0_log', array_slice( $log, -200 ), false );
}

/** The publisher image to show, or '' when there is none or it was found unusable. */
function aw_publisher_image( $post_id ) {
	$url = (string) get_post_meta( $post_id, 'aw_source_image', true );
	if ( '' === $url || ! preg_match( '#^https://[^/]+/#i', $url ) ) {
		return '';
	}
	return '0' === (string) get_post_meta( $post_id, 'aw_source_image_ok', true ) ? '' : $url;
}

/** SQL fragment: published ingested items without their own document page. */
function aw_cc0_items_sql() {
	global $wpdb;
	return "FROM {$wpdb->posts} p
		JOIN {$wpdb->postmeta} su ON su.post_id = p.ID AND su.meta_key = 'aw_source_url' AND su.meta_value <> ''
		LEFT JOIN {$wpdb->postmeta} fi ON fi.post_id = p.ID AND fi.meta_key = 'aw_file_id'
		LEFT JOIN {$wpdb->postmeta} dp ON dp.post_id = p.ID AND dp.meta_key = 'aw_doc_preview'
		WHERE p.post_status = 'publish' AND p.post_type IN ('aw_news','aw_legal','post')
		AND ( fi.meta_value IS NULL OR fi.meta_value IN ('','0') ) AND ( dp.meta_value IS NULL OR dp.meta_value IN ('','0') )";
}

/** One batch: check publisher images, then find photos. */
function aw_cc0_run() {
	if ( get_transient( 'aw_cc0_lock' ) ) {
		return;
	}
	set_transient( 'aw_cc0_lock', 1, 10 * MINUTE_IN_SECONDS );
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( AW_CC0_BUDGET + 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	$start = time();
	global $wpdb;

	// 0. Publisher images read from the source page when ingest stored none (or a broken one).
	$fetched = aw_source_og_run( 40 );

	// 0b. Linked publisher images stored in the Media Library (no hotlinking).
	$local = aw_source_localise_run( 25, 60 );
	// 0c. Documents whose source is a PDF: the first page as their picture.
	foreach ( $wpdb->get_col( "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} su ON su.post_id = p.ID AND su.meta_key = 'aw_source_url' AND su.meta_value LIKE '%.pdf%' WHERE p.post_status = 'publish' AND p.post_type IN ('aw_legal','post','aw_news') AND NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} d WHERE d.post_id = p.ID AND d.meta_key IN ('aw_doc_preview','aw_file_id','aw_pdf_cover_tried')) ORDER BY p.post_date DESC LIMIT 5" ) as $pid ) { // phpcs:ignore WordPress.DB
		update_post_meta( $pid, 'aw_pdf_cover_tried', current_time( 'mysql', true ) );
		aw_source_pdf_cover( (int) $pid );
	}

	// 1. Publisher images: unchecked first, then failures older than a week.
	$week = gmdate( 'Y-m-d H:i:s', time() - WEEK_IN_SECONDS );
	$ids  = $wpdb->get_col( $wpdb->prepare( "SELECT p.ID " . aw_cc0_items_sql() . " AND EXISTS (SELECT 1 FROM {$wpdb->postmeta} si WHERE si.post_id = p.ID AND si.meta_key = 'aw_source_image' AND si.meta_value <> '')
		AND ( NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} c WHERE c.post_id = p.ID AND c.meta_key = 'aw_source_image_checked')
		   OR EXISTS (SELECT 1 FROM {$wpdb->postmeta} o JOIN {$wpdb->postmeta} c2 ON c2.post_id = o.post_id AND c2.meta_key = 'aw_source_image_checked'
		              WHERE o.post_id = p.ID AND o.meta_key = 'aw_source_image_ok' AND o.meta_value = '0' AND c2.meta_value < %s) )
		ORDER BY p.post_date DESC LIMIT %d", $week, 60 ) ); // phpcs:ignore WordPress.DB
	$checked = 0;
	foreach ( $ids as $id ) {
		if ( time() - $start > 60 ) {
			break;
		}
		$why = aw_cc0_check_source( (int) $id );
		update_post_meta( $id, 'aw_source_image_ok', '' === $why ? 1 : 0 );
		update_post_meta( $id, 'aw_source_image_checked', current_time( 'mysql', true ) );
		update_post_meta( $id, 'aw_source_image_why', $why );
		++$checked;
	}

	// 2. Items with no usable picture of their own.
	$ids  = $wpdb->get_col( $wpdb->prepare( "SELECT p.ID " . aw_cc0_items_sql() . "
		AND NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} t WHERE t.post_id = p.ID AND t.meta_key = '_thumbnail_id' AND t.meta_value <> '' AND t.meta_value <> '0')
		AND ( NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} si WHERE si.post_id = p.ID AND si.meta_key = 'aw_source_image' AND si.meta_value LIKE 'https://%%')
		   OR EXISTS (SELECT 1 FROM {$wpdb->postmeta} o WHERE o.post_id = p.ID AND o.meta_key = 'aw_source_image_ok' AND o.meta_value = '0') )
		AND NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} a WHERE a.post_id = p.ID AND a.meta_key = 'aw_cc0_attempts' AND a.meta_value >= %d)
		AND NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} tr WHERE tr.post_id = p.ID AND tr.meta_key = 'aw_cc0_tried' AND tr.meta_value > %s)
		ORDER BY p.post_date DESC LIMIT %d", AW_CC0_MAX_ATTEMPTS, $week, AW_CC0_BATCH ) ); // phpcs:ignore WordPress.DB
	$done = 0;
	$none = 0;
	foreach ( $ids as $id ) {
		if ( time() - $start > AW_CC0_BUDGET || get_transient( 'aw_ov_pause' ) || ! aw_ov_quota_left() ) {
			break;
		}
		$r = aw_cc0_assign( (int) $id );
		if ( $r ) {
			++$done;
		} elseif ( null !== $r ) {
			++$none;
		}
	}
	// 2b. Our own guides with no picture (owner, 18 Oct 2026): a CC0 photo of their own, used once site-wide. A guide
	// linked to a campaign (campaign-links.php) is left to the campaign's own pictures (its motif), never a stock photo.
	$own = $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p
		WHERE p.post_status = 'publish' AND p.post_type = 'post'
		AND NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} su WHERE su.post_id = p.ID AND su.meta_key = 'aw_source_url' AND su.meta_value <> '')
		AND NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} t WHERE t.post_id = p.ID AND t.meta_key = '_thumbnail_id' AND t.meta_value NOT IN ('','0'))
		AND NOT EXISTS (SELECT 1 FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'aw_campaign_link' JOIN {$wpdb->terms} te ON te.term_id = tt.term_id AND te.slug LIKE 'campaign-%%' WHERE tr.object_id = p.ID)
		AND NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} a WHERE a.post_id = p.ID AND a.meta_key = 'aw_cc0_attempts' AND a.meta_value >= %d)
		AND NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} tr2 WHERE tr2.post_id = p.ID AND tr2.meta_key = 'aw_cc0_tried' AND tr2.meta_value > %s)
		ORDER BY p.post_date DESC LIMIT 5", AW_CC0_MAX_ATTEMPTS, $week ) ); // phpcs:ignore WordPress.DB
	foreach ( $own as $id ) {
		if ( time() - $start > AW_CC0_BUDGET || get_transient( 'aw_ov_pause' ) || ! aw_ov_quota_left() ) {
			break;
		}
		$r = aw_cc0_assign( (int) $id );
		if ( $r ) {
			++$done;
		} elseif ( null !== $r ) {
			++$none;
		}
	}

	if ( $fetched || $local || $checked || $done || $none ) {
		aw_cc0_log( sprintf( 'run: %d publisher images read from source pages, %d stored locally, %d checked, %d photos added, %d without a match (%ds)', $fetched, $local, $checked, $done, $none, time() - $start ) );
	}
	if ( $done && function_exists( 'aw_thumb_schedule' ) ) {
		aw_thumb_schedule();
	}
	delete_transient( 'aw_cc0_lock' );
}

/**
 * Check a publisher image. '' when usable, else the reason.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function aw_cc0_check_source( $post_id ) {
	$url = (string) get_post_meta( $post_id, 'aw_source_image', true );
	if ( ! preg_match( '#^https://[^/]+\.[^/]+/#i', $url ) ) {
		return 'bad address';
	}
	$r = wp_remote_get(
		$url,
		array(
			'timeout'             => 10,
			'redirection'         => 5,
			'user-agent'          => 'Mozilla/5.0 (compatible; AnimalWritesImageCheck/1.0; +https://animalwrites.in)',
			'limit_response_size' => 262144,
			'headers'             => array( 'Accept' => 'image/avif,image/webp,image/png,image/jpeg,image/*;q=0.8' ),
		)
	);
	if ( is_wp_error( $r ) ) {
		return 'not reachable: ' . $r->get_error_message();
	}
	$code = (int) wp_remote_retrieve_response_code( $r );
	if ( 200 !== $code ) {
		return 'HTTP ' . $code;
	}
	$type = wp_remote_retrieve_header( $r, 'content-type' );
	$type = strtolower( (string) ( is_array( $type ) ? end( $type ) : $type ) ); // a header sent twice comes back as a list
	$size = @getimagesizefromstring( (string) wp_remote_retrieve_body( $r ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	if ( 0 !== strpos( $type, 'image/' ) && ! $size ) { // the file's own bytes decide when the server names the type wrongly (text/plain, binary/octet-stream, "png")
		return 'not an image (' . ( $type ? $type : 'no type' ) . ')';
	}
	if ( $size && ! empty( $size[0] ) && ! empty( $size[1] ) ) {
		update_post_meta( $post_id, 'aw_source_image_w', (int) $size[0] ); // og:image:width / height (aw-seo-extras)
		update_post_meta( $post_id, 'aw_source_image_h', (int) $size[1] );
		if ( $size[0] < 200 || $size[1] < 200 ) {
			return 'too small (' . $size[0] . '×' . $size[1] . ')';
		}
	}
	return '';
}

/**
 * The image a source page names for sharing (og:image, else twitter:image, else link rel=image_src): an absolute
 * https URL, or ''.
 *
 * @param string $page Source page URL.
 * @return string
 */
function aw_source_og_image( $page ) {
	if ( ! preg_match( '#^https?://#i', $page ) ) {
		return '';
	}
	// YouTube: the video's own cover (owner, 17 Oct 2026).
	if ( preg_match( '#(?:youtube\.com/(?:watch\?(?:.*&)?v=|shorts/|embed/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})#', $page, $yt ) ) {
		return 'https://i.ytimg.com/vi/' . $yt[1] . '/hqdefault.jpg';
	}
	// PIB: every release declares the same site picture; the release's own first photo is the item's (owner, 18 Oct 2026).
	if ( preg_match( '#^https?://(www\.)?pib\.gov\.in/#i', $page ) ) {
		$r = wp_remote_get( $page, array( 'timeout' => 15, 'user-agent' => AW_PRESS_UA ) ); // a browser's agent: PIB refuses bot-style ones
		$h = is_wp_error( $r ) ? '' : (string) wp_remote_retrieve_body( $r );
		return preg_match( '#<img[^>]+src=["\'](https://static\.pib\.gov\.in/WriteReadData/userfiles/image/[^"\']+)#i', $h, $m ) ? esc_url_raw( $m[1] ) : '';
	}
	// Link-preview crawlers first (social sites and many publishers give them the real picture), then a browser.
	foreach ( array( 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)', 'Twitterbot/1.0', AW_PRESS_UA ) as $ua ) { // then a browser (owner, 17 Oct 2026)
		$url = aw_source_og_image_as( $page, $ua );
		if ( '' !== $url && ! ( function_exists( 'aw_press_is_platform_image' ) && aw_press_is_platform_image( $url ) ) ) {
			return $url;
		}
	}
	return '';
}

/** og:image (else twitter:image, image_src) of a page read with one User-Agent, or ''. */
function aw_source_og_image_as( $page, $ua ) {
	$r = wp_remote_get(
		$page,
		array(
			'timeout'             => 12,
			'redirection'         => 5,
			'user-agent'          => $ua,
			'limit_response_size' => 524288,
			'headers'             => array( 'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.5' ),
		)
	);
	if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) || false === stripos( (string) wp_remote_retrieve_header( $r, 'content-type' ), 'html' ) ) {
		return '';
	}
	$html = (string) wp_remote_retrieve_body( $r );
	$head = substr( $html, 0, (int) ( stripos( $html, '</head>' ) ?: strlen( $html ) ) );
	foreach ( array( 'og:image:secure_url', 'og:image:url', 'og:image', 'twitter:image', 'twitter:image:src' ) as $prop ) {
		foreach ( array( '/<meta[^>]+(?:property|name)=["\']' . preg_quote( $prop, '/' ) . '["\'][^>]*content=["\']([^"\']+)["\']/i', '/<meta[^>]+content=["\']([^"\']+)["\'][^>]*(?:property|name)=["\']' . preg_quote( $prop, '/' ) . '["\']/i' ) as $re ) {
			if ( preg_match( $re, $head, $m ) ) {
				$url = html_entity_decode( trim( $m[1] ), ENT_QUOTES );
				break 2;
			}
		}
	}
	if ( empty( $url ) && preg_match( '/<link[^>]+rel=["\']image_src["\'][^>]*href=["\']([^"\']+)["\']/i', $head, $m ) ) {
		$url = html_entity_decode( trim( $m[1] ), ENT_QUOTES );
	}
	if ( empty( $url ) ) {
		return '';
	}
	if ( 0 === strpos( $url, '//' ) ) {
		$url = 'https:' . $url;
	} elseif ( '/' === $url[0] ) {
		$p   = wp_parse_url( $page );
		$url = 'https://' . $p['host'] . $url;
	}
	$url = preg_replace( '#^http://#i', 'https://', $url );
	return preg_match( '#^https://[^/]+\.[^/]+/#i', $url ) ? esc_url_raw( $url ) : '';
}

/**
 * Step 0: read the publisher image from the source page for items without a usable one. Each item's page is read
 * once (aw_source_og_tried = date, aw_source_og_result = what was found). A different image found for a broken one
 * replaces it (the old address is kept in aw_source_image_prev). Returns how many images were stored.
 *
 * @param int $limit Pages to read in this run.
 * @return int
 */
function aw_source_og_run( $limit = 40 ) {
	global $wpdb;
	$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB
		$wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			JOIN {$wpdb->postmeta} su ON su.post_id = p.ID AND su.meta_key = 'aw_source_url' AND su.meta_value LIKE 'http%%'
			LEFT JOIN {$wpdb->postmeta} si ON si.post_id = p.ID AND si.meta_key = 'aw_source_image'
			LEFT JOIN {$wpdb->postmeta} ok ON ok.post_id = p.ID AND ok.meta_key = 'aw_source_image_ok'
			WHERE p.post_status = 'publish' AND p.post_type IN ('aw_news','aw_legal','post','aw_opportunity')
			AND ( si.meta_value IS NULL OR si.meta_value = '' OR ok.meta_value = '0' )
			AND NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} t WHERE t.post_id = p.ID AND t.meta_key = 'aw_source_og_tried')
			ORDER BY p.post_date DESC LIMIT %d",
			$limit
		)
	);
	$stored = 0;
	$start  = time();
	foreach ( $ids as $id ) {
		if ( time() - $start > 90 ) {
			break;
		}
		$found = aw_source_og_image( (string) get_post_meta( $id, 'aw_source_url', true ) );
		$found = '' !== $found ? $found : aw_source_also_image( (int) $id ); // another report of the same story
		$have  = (string) get_post_meta( $id, 'aw_source_image', true );
		update_post_meta( $id, 'aw_source_og_tried', current_time( 'mysql', true ) );
		update_post_meta( $id, 'aw_source_og_result', '' === $found ? 'none' : ( $found === $have ? 'same as the broken one' : $found ) );
		if ( '' !== $found && $found !== $have ) {
			if ( '' !== $have ) {
				update_post_meta( $id, 'aw_source_image_prev', $have );
			}
			update_post_meta( $id, 'aw_source_image', $found );
			$why = aw_cc0_check_source( (int) $id );
			update_post_meta( $id, 'aw_source_image_ok', '' === $why ? 1 : 0 );
			update_post_meta( $id, 'aw_source_image_checked', current_time( 'mysql', true ) );
			update_post_meta( $id, 'aw_source_image_why', $why );
			$stored += '' === $why ? 1 : 0;
		}
		sleep( 1 ); // one source page a second
	}
	return $stored;
}

/**
 * The picture of a story reported by several publishers: the first other report whose page has one (owner, 18 Oct
 * 2026: a story from more than one source takes its picture from another when its own has none).
 */
function aw_source_also_image( $post_id ) {
	foreach ( array_filter( (array) get_post_meta( $post_id, 'aw_also_reported', true ) ) as $a ) {
		$url = is_array( $a ) ? (string) ( $a['url'] ?? '' ) : (string) $a;
		$img = '' !== $url ? aw_source_og_image( $url ) : '';
		if ( '' !== $img ) {
			return $img;
		}
	}
	return '';
}

/**
 * A document whose source is a PDF (AWBI circulars and the like): its first page as its picture, the Library's
 * "preview only (not hosted)" state (aw_doc_preview). Replaces a stand-in CC0 photo; the old one is kept in aw_cc0_prev.
 *
 * @return int Attachment ID, or 0.
 */
function aw_source_pdf_cover( $post_id ) {
	$src = (string) get_post_meta( $post_id, 'aw_source_url', true );
	if ( ! in_array( get_post_type( $post_id ), array( 'aw_legal', 'post', 'aw_news' ), true ) ) {
		return 0; // documents only: a Directory listing's source PDF (the AWBI list) is not its picture
	}
	if ( ! preg_match( '~^https?://[^?#]+\.pdf(\?|$)~i', $src ) || get_post_meta( $post_id, 'aw_file_id', true ) || get_post_meta( $post_id, 'aw_doc_preview', true ) || ! class_exists( 'Imagick' ) ) {
		return 0;
	}
	$pdf = aw_source_download( $src );
	if ( is_wp_error( $pdf ) || '%PDF' !== (string) file_get_contents( $pdf, false, null, 0, 4 ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
		is_string( $pdf ) && @unlink( $pdf ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return 0;
	}
	try {
		$im = new Imagick();
		$im->setResolution( 150, 150 );
		$im->readImage( $pdf . '[0]' );
		$im->setImageBackgroundColor( 'white' );
		$im = $im->mergeImageLayers( Imagick::LAYERMETHOD_FLATTEN );
		if ( $im->getImageWidth() > 1600 ) {
			$im->resizeImage( 1600, 0, Imagick::FILTER_LANCZOS, 1 );
		}
		$im->setImageFormat( 'jpeg' );
		$im->setImageCompressionQuality( 82 );
		$jpg = wp_tempnam( 'aw-page' );
		$im->writeImage( $jpg );
		$im->clear();
	} catch ( Exception $e ) {
		@unlink( $pdf ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return 0;
	}
	@unlink( $pdf ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$att = media_handle_sideload( array( 'name' => substr( sanitize_title( get_post_field( 'post_name', $post_id ) ?: 'document-' . $post_id ), 0, 80 ) . '-page-1.jpg', 'tmp_name' => $jpg ), $post_id, get_the_title( $post_id ) . ', page 1' );
	if ( is_wp_error( $att ) ) {
		@unlink( $jpg ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return 0;
	}
	update_post_meta( $att, '_wp_attachment_image_alt', 'Page 1 of ' . wp_strip_all_tags( get_the_title( $post_id ) ) );
	$old = (int) get_post_thumbnail_id( $post_id );
	if ( $old ) {
		update_post_meta( $post_id, 'aw_cc0_prev', $old );
	}
	set_post_thumbnail( $post_id, $att );
	update_post_meta( $post_id, 'aw_doc_preview', (int) $att );
	return (int) $att;
}

/**
 * Download a picture to a temporary file. Not core download_url(): it fails fatally on a content-type header sent
 * twice (it indexes an array with it), which some publishers' CDNs do. Publisher, CC0, press and enrichment pictures
 * use it (plugin audit 22). Like download_url(), only public addresses are fetched (wp_safe_remote_get). A file cut off
 * at $max is refused, never kept half.
 *
 * @param string $url     Address.
 * @param int    $timeout Seconds.
 * @param int    $max     Largest file accepted, in bytes.
 * @return string|WP_Error Temporary file path.
 */
function aw_source_download( $url, $timeout = 30, $max = 15 * MB_IN_BYTES ) {
	$r = wp_safe_remote_get( $url, array( 'timeout' => (int) $timeout, 'redirection' => 5, 'user-agent' => 'Mozilla/5.0 (compatible; AnimalWritesImageCheck/1.0; +https://animalwrites.in)', 'limit_response_size' => (int) $max ) );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	if ( 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
		return new WP_Error( 'aw_http', 'HTTP ' . (int) wp_remote_retrieve_response_code( $r ) );
	}
	if ( strlen( (string) wp_remote_retrieve_body( $r ) ) >= (int) $max ) {
		return new WP_Error( 'aw_too_big', 'over ' . size_format( (int) $max ) );
	}
	if ( ! function_exists( 'wp_tempnam' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	$tmp = wp_tempnam( 'aw-source' );
	if ( ! $tmp || false === file_put_contents( $tmp, (string) wp_remote_retrieve_body( $r ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
		return new WP_Error( 'aw_tmp', 'could not write a temporary file' );
	}
	return $tmp;
}

/**
 * A publisher image kept in our Media Library instead of linked (owner, 17 Oct 2026: no hotlinking; publisher CDN
 * addresses change and expire). The address shown everywhere (aw_source_image) becomes the local copy; the original is
 * kept in aw_source_image_orig and the attachment in aw_source_image_att. Alt text: the item's title.
 *
 * @param int $post_id Item.
 * @return int Attachment ID, or 0.
 */
function aw_source_localise( $post_id ) {
	$url  = (string) get_post_meta( $post_id, 'aw_source_image', true );
	$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	if ( '' === $url || false !== strpos( (string) wp_parse_url( $url, PHP_URL_HOST ), $host ) || '0' === (string) get_post_meta( $post_id, 'aw_source_image_ok', true ) ) {
		return 0;
	}
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$tmp = aw_source_download( $url );
	if ( is_wp_error( $tmp ) ) {
		update_post_meta( $post_id, 'aw_source_localise', 'download failed: ' . $tmp->get_error_message() );
		return 0;
	}
	$type = (string) wp_get_image_mime( $tmp );
	$ext  = array( 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/avif' => 'avif', 'image/gif' => 'gif' )[ $type ] ?? '';
	if ( '' === $ext ) {
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		update_post_meta( $post_id, 'aw_source_localise', 'not an image' );
		return 0;
	}
	$name = sanitize_title( get_post_field( 'post_name', $post_id ) ?: 'item-' . $post_id );
	$att  = media_handle_sideload( array( 'name' => substr( $name, 0, 80 ) . '-source.' . $ext, 'tmp_name' => $tmp ), $post_id, get_the_title( $post_id ) );
	if ( is_wp_error( $att ) ) {
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		update_post_meta( $post_id, 'aw_source_localise', 'sideload failed: ' . $att->get_error_message() );
		return 0;
	}
	update_post_meta( $att, '_wp_attachment_image_alt', wp_strip_all_tags( get_the_title( $post_id ) ) );
	update_post_meta( $att, 'aw_source_image_from', $url );
	$local = (string) wp_get_attachment_image_url( $att, 'full' );
	$meta  = wp_get_attachment_metadata( $att );
	update_post_meta( $post_id, 'aw_source_image_orig', $url );
	update_post_meta( $post_id, 'aw_source_image_att', (int) $att );
	update_post_meta( $post_id, 'aw_source_image', $local );
	if ( ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
		update_post_meta( $post_id, 'aw_source_image_w', (int) $meta['width'] );
		update_post_meta( $post_id, 'aw_source_image_h', (int) $meta['height'] );
	}
	update_post_meta( $post_id, 'aw_source_localise', 'stored ' . current_time( 'mysql', true ) );
	return (int) $att;
}

/** Store linked publisher images locally, newest first; each item is tried once a day. Returns how many were stored. */
function aw_source_localise_run( $limit = 25, $budget = 60 ) {
	global $wpdb;
	$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$ids  = $wpdb->get_col( // phpcs:ignore WordPress.DB
		$wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			JOIN {$wpdb->postmeta} si ON si.post_id = p.ID AND si.meta_key = 'aw_source_image' AND si.meta_value LIKE 'https://%%' AND si.meta_value NOT LIKE %s
			LEFT JOIN {$wpdb->postmeta} ok ON ok.post_id = p.ID AND ok.meta_key = 'aw_source_image_ok'
			LEFT JOIN {$wpdb->postmeta} lt ON lt.post_id = p.ID AND lt.meta_key = 'aw_source_localise_tried'
			WHERE p.post_status = 'publish' AND ( ok.meta_value IS NULL OR ok.meta_value <> '0' ) AND ( lt.meta_value IS NULL OR lt.meta_value < %s )
			ORDER BY p.post_date DESC LIMIT %d",
			'%' . $wpdb->esc_like( $host ) . '%',
			gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ),
			$limit
		)
	);
	$stored = 0;
	$start  = time();
	foreach ( $ids as $id ) {
		if ( time() - $start > $budget ) {
			break;
		}
		update_post_meta( $id, 'aw_source_localise_tried', current_time( 'mysql', true ) );
		$stored += aw_source_localise( (int) $id ) ? 1 : 0;
	}
	return $stored;
}

/* ---------- Openverse ---------- */

/** True while today's request allowance lasts. */
function aw_ov_quota_left() {
	$q = (array) get_option( 'aw_ov_quota', array() );
	return ( $q['d'] ?? '' ) !== gmdate( 'Y-m-d' ) || (int) ( $q['n'] ?? 0 ) < AW_CC0_DAILY_REQUESTS;
}

/**
 * One page of Openverse results (cached a week). Null on failure.
 *
 * @param string $q      Query.
 * @param string $source Comma-separated sources.
 * @param int    $page   Page.
 * @return array|null
 */
function aw_ov_search( $q, $source, $page ) {
	$key    = 'aw_ov_' . md5( $q . '|' . $source . '|' . $page );
	$cached = get_transient( $key );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	if ( get_transient( 'aw_ov_pause' ) || ! aw_ov_quota_left() ) {
		return null;
	}
	$last = (float) get_option( 'aw_ov_last', 0 );
	$wait = 2.0 - ( microtime( true ) - $last );
	if ( $wait > 0 ) {
		usleep( (int) ( $wait * 1e6 ) );
	}
	$url = add_query_arg(
		array(
			'q'         => rawurlencode( $q ),
			'license'   => 'cc0,pdm',
			'mature'    => 'false',
			'page_size' => 20,
			'page'      => (int) $page,
			'source'    => $source,
		),
		'https://api.openverse.org/v1/images/'
	);
	$r = wp_remote_get( $url, array( 'timeout' => 20, 'user-agent' => AW_CC0_UA ) );
	update_option( 'aw_ov_last', microtime( true ), false );
	$qd = (array) get_option( 'aw_ov_quota', array() );
	$qd = ( $qd['d'] ?? '' ) === gmdate( 'Y-m-d' ) ? $qd : array( 'd' => gmdate( 'Y-m-d' ), 'n' => 0 );
	++$qd['n'];
	update_option( 'aw_ov_quota', $qd, false );
	$code = is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r );
	if ( 429 === $code ) {
		set_transient( 'aw_ov_pause', 1, HOUR_IN_SECONDS );
		aw_cc0_log( 'Openverse: rate limited (429), pausing for an hour' );
		return null;
	}
	if ( 200 !== $code ) {
		if ( 400 === $code || 404 === $code ) { // past the last page
			set_transient( $key, array(), WEEK_IN_SECONDS );
			return array();
		}
		aw_cc0_log( 'Openverse: ' . ( is_wp_error( $r ) ? $r->get_error_message() : 'HTTP ' . $code ) . ' for "' . $q . '"' );
		return null;
	}
	$d    = json_decode( (string) wp_remote_retrieve_body( $r ), true );
	$rows = array();
	foreach ( (array) ( $d['results'] ?? array() ) as $x ) {
		$rows[] = array(
			'id'      => (string) ( $x['id'] ?? '' ),
			'title'   => (string) ( $x['title'] ?? '' ),
			'tags'    => implode( ', ', array_map( fn( $t ) => (string) ( $t['name'] ?? '' ), (array) ( $x['tags'] ?? array() ) ) ),
			'url'     => (string) ( $x['url'] ?? '' ),
			'landing' => (string) ( $x['foreign_landing_url'] ?? '' ),
			'license' => (string) ( $x['license'] ?? '' ),
			'lurl'    => (string) ( $x['license_url'] ?? '' ),
			'source'  => (string) ( $x['source'] ?? '' ),
			'w'       => (int) ( $x['width'] ?? 0 ),
			'h'       => (int) ( $x['height'] ?? 0 ),
		);
	}
	$rows['_pages'] = (int) ( $d['page_count'] ?? 1 );
	set_transient( $key, $rows, WEEK_IN_SECONDS );
	return $rows;
}

/** Animals named in titles: regex => query noun. Order matters (first match in the title wins by position). */
function aw_cc0_nouns() {
	return array(
		'(?:stray|street|community|pet)?\s*(?:dogs?|pupp(?:y|ies)|canines?|strays)' => 'dog',
		'(?:cats?|kittens?)'                                => 'cat',
		'(?:cows?|cattle|calf|calves|bulls?|bullocks?|oxen|gaushalas?|bovines?)' => 'cow',
		'buffalo(?:es)?'                                     => 'buffalo',
		'goats?'                                             => 'goat',
		'sheep'                                              => 'sheep',
		'pigs?|piglets?'                                     => 'pig',
		'horses?|pon(?:y|ies)|mules?'                        => 'horse',
		'donkeys?'                                           => 'donkey',
		'camels?'                                            => 'camel',
		'elephants?|tuskers?|jumbos?|ivory'                        => 'elephant',
		'tigers?|tigress'                                    => 'tiger',
		'leopards?'                                          => 'leopard',
		'lions?|lioness'                                     => 'lion',
		'cheetahs?'                                          => 'cheetah',
		'sloth bears?|bears? cubs?|himalayan bears?'         => 'bear',
		'monkeys?|macaques?|langurs?|primates?'              => 'monkey',
		'snakes?|cobras?|pythons?|vipers?|kraits?'           => 'snake',
		'crocodiles?|gharials?|muggers?'                     => 'crocodile',
		'turtles?|tortoises?'                                => 'turtle',
		'peacocks?|peafowl|peahens?'                         => 'peacock',
		'parrots?|parakeets?'                                => 'parrot',
		'pigeons?'                                           => 'pigeon',
		'vultures?'                                          => 'vulture',
		'owls?'                                              => 'owl',
		'eagles?'                                            => 'eagle',
		'flamingos?'                                         => 'flamingo',
		'chickens?|chicks?|poultry|hens?|broilers?'                  => 'chicken',
		'birds?|avian'                                       => 'bird',
		'deer|chital|sambar|blackbucks?'                     => 'deer',
		'rhinos?|rhinoceros'                                 => 'rhinoceros',
		'wolf|wolves'                                        => 'wolf',
		'jackals?'                                           => 'jackal',
		'hyenas?'                                            => 'hyena',
		'wild boars?|boars?'                                 => 'wild boar',
		'nilgai'                                             => 'nilgai',
		'gaur|bison'                                         => 'gaur',
		'pangolins?'                                         => 'pangolin',
		'otters?'                                            => 'otter',
		'dolphins?'                                          => 'dolphin',
		'whales?'                                            => 'whale',
		'sharks?'                                            => 'shark',
		'fish(?:es)?'                                        => 'fish',
		'frogs?'                                             => 'frog',
		'lizards?|geckos?'                                   => 'lizard',
		'rabbits?'                                           => 'rabbit',
		'squirrels?'                                         => 'squirrel',
		'bees?|honeybees?'                                   => 'bee',
		'butterfl(?:y|ies)'                                  => 'butterfly',
		'rats?|rodents?'                                     => 'rat',
	);
}

/** Category => query, when the title names no animal. */
function aw_cc0_category_query( $slug ) {
	$m = array(
		'community-dogs'             => 'dog',
		'animal-birth-control'       => 'dog',
		'feeders-caregivers'         => 'dog',
		'rabies-public-health'       => 'dog',
		'adoption'                   => 'puppy',
		'pets-breeding'              => 'puppy',
		'cattle-gaushalas'           => 'cow',
		'transport-slaughter'        => 'cow',
		'wildlife'                   => 'wildlife',
		'birds'                      => 'bird',
		'performing-captive-animals' => 'elephant',
		'veterinary-care'            => 'dog',
		'disaster-rescue'            => 'dog',
	);
	return $m[ $slug ] ?? '';
}

/**
 * Searches for an item: array of [query, source, required-word regex].
 *
 * @param int $post_id Post ID.
 * @return array
 */
function aw_cc0_queries( $post_id ) {
	$title  = wp_strip_all_tags( html_entity_decode( get_the_title( $post_id ), ENT_QUOTES ) );
	$best   = null;
	$at     = PHP_INT_MAX;
	$stray  = false;
	foreach ( aw_cc0_nouns() as $re => $noun ) {
		if ( preg_match( '/\b(?:' . $re . ')\b/i', $title, $m, PREG_OFFSET_CAPTURE ) && $m[0][1] < $at ) {
			$at   = $m[0][1];
			$best = $noun;
		}
	}
	$cats = wp_get_object_terms( $post_id, 'category', array( 'fields' => 'slugs' ) );
	$cats = is_wp_error( $cats ) ? array() : $cats;
	if ( ! $best ) { // words that point to one animal
		foreach ( array( '/\b(rabi[ed]s|anti-rabies|dog[- ]bites?|animal bites?|bite cases?|sterili[sz]ation|abc programme)\b/i' => 'dog', '/\b(meat|dairy|milk|beef|bovine|jallikattu|kambala|slaughter\w*)\b/i' => 'cow', '/\b(poach\w*|wildlife|forest|tiger reserve|national park|sanctuary|zoo)\b/i' => 'wildlife' ) as $re => $n ) {
			if ( preg_match( $re, $title ) ) {
				$best = $n;
				break;
			}
		}
	}
	if ( ! $best ) {
		foreach ( $cats as $s ) {
			$best = aw_cc0_category_query( $s );
			if ( $best ) {
				break;
			}
		}
	}
	// No animal named: the animals these stories are most often about (rotated by item, so neighbours differ).
	$best = $best ? $best : array( 'dog', 'cow', 'cat', 'donkey', 'goat', 'buffalo' )[ $post_id % 6 ];
	if ( 'wildlife' === $best ) {
		$best = array( 'elephant', 'tiger', 'leopard', 'deer', 'peacock', 'monkey' )[ $post_id % 6 ];
	}
	if ( in_array( $best, array( 'dog', 'puppy' ), true ) ) {
		$stray = (bool) preg_match( '/\b(stray|street|community|abc|sterili[sz]|feed(?:er|ing)|rabies|bite)/i', $title ) || array_intersect( $cats, array( 'community-dogs', 'animal-birth-control', 'feeders-caregivers', 'rabies-public-health' ) );
	}
	$word = array(
		'dog'        => 'dogs?|pupp(?:y|ies)|canine',
		'puppy'      => 'pupp(?:y|ies)|dogs?',
		'cow'        => 'cows?|cattle|calf|calves|bulls?|oxen|zebu',
		'monkey'     => 'monkeys?|macaques?|langurs?',
		'bird'       => 'birds?',
		'peacock'    => 'peacocks?|peafowl|peahens?',
		'cat'        => 'cats?|kittens?',
		'rhinoceros' => 'rhinos?|rhinoceros',
		'turtle'     => 'turtles?|tortoises?',
		'deer'       => 'deer|chital|sambar|antelope',
	)[ $best ] ?? preg_quote( $best, '/' ) . '(?:e?s)?';
	$main   = 'wordpress';
	$others = 'flickr,rawpixel,wikimedia';
	$q      = array();
	if ( $stray ) {
		$q[] = array( 'stray dog', $main, $word );
		$q[] = array( 'street dog', $main, $word );
	}
	$q[] = array( 'puppy' === $best ? 'puppy' : $best, $main, $word );
	if ( 'puppy' === $best ) {
		$q[] = array( 'dog', $main, $word );
	}
	if ( $stray ) {
		$q[] = array( 'stray dog', $others, $word );
	}
	$q[] = array( $best, $others, $word );
	return $q;
}

/**
 * Whether a result may be used. Score (higher is better) or -1.
 *
 * @param array $x      Result row.
 * @param string $word  Regex of words that must appear in title or tags.
 * @param bool   $injury Whether injury pictures are allowed (veterinary care).
 * @return int
 */
function aw_cc0_score( $x, $word, $injury ) {
	$text = strtolower( $x['title'] . ' | ' . $x['tags'] );
	if ( '' === $x['id'] || ! preg_match( '#^https://#', $x['url'] ) || ! in_array( $x['license'], array( 'cc0', 'pdm' ), true ) ) {
		return -1;
	}
	if ( preg_match( '/\.(png|gif|svg|tiff?)(\?|$)/i', $x['url'] ) || $x['w'] < 1000 || $x['h'] < 560 ) {
		return -1;
	}
	if ( ! preg_match( '/\b(?:' . $word . ')\b/i', $x['title'] ) && ! preg_match( '/(^|, )(?:' . $word . ')(,|$)/i', $x['tags'] ) ) {
		return -1; // the animal is named in the title, or is a tag on its own
	}
	// People, non-photos and graphic pictures are never used.
	if ( preg_match( '/\b(person|people|persons|man|woman|men|women|girls?|boys?|child|children|kids|lady|ladies|portrait|selfie|wedding|bride|groom|crowd|tourists?|farmers?|owner|hands?|legs|feet|fingers|holding|family portrait|festival|nepalphotofestival\d*)\b/i', $text ) ) {
		return -1;
	}
	if ( preg_match( '/\b(clip ?art|png|illustrations?|drawings?|vectors?|sketch|icons?|logos?|cartoons?|paintings?|posters?|sculptures?|statues?|toys?|figurines?|stamps?|coins?|maps?|diagrams?|signs?|text|prints?|patterns?|vintage|engravings?|lithographs?|book|page|plate|museum|skeletons?|fossils?|mural|graffiti|plush|costume|mask|emoji|art|bag|dispenser|fabric|textile|machine|stuffed|manhole|cover|rod|landscape|scenery|ornament|decoration|carving|bronze|ceramic|cutout|silhouette|coop|shed|barn|hut|kennel sign|enclosure sign)\b/i', $text ) ) {
		return -1;
	}
	if ( preg_match( '/\b(dead|death|died|blood|bloody|carcass(es)?|slaughter\w*|meat|butcher\w*|hunt\w*|kill\w*|skulls?|bones?|taxiderm\w*|roadkill|corpse|attack\w*|fight\w*|eating a|prey|hide|leather|fur coat|food|dish|cooked|curry|market)\b/i', $text ) ) {
		return -1;
	}
	if ( ! $injury && preg_match( '/\b(injur\w*|wound\w*|sick|disease\w*|mange|surgery)\b/i', $text ) ) {
		return -1;
	}
	$s  = array( 'wordpress' => 30, 'rawpixel' => 15, 'flickr' => 12, 'wikimedia' => 10 )[ $x['source'] ] ?? 0;
	$s += $x['w'] >= $x['h'] * 1.2 ? 10 : ( $x['w'] >= $x['h'] ? 4 : 0 );
	$s += $x['w'] >= 1600 ? 3 : 0;
	$s += preg_match( '/\b(?:' . $word . ')\b/i', $x['title'] ) ? 5 : 0;
	$s += preg_match( '/\b(india|indian|kerala|delhi|mumbai|bengal|karnataka|rajasthan|assam|goa|tamil|nepal)\b/i', $text ) ? 4 : 0;
	return $s;
}

/** Openverse ids and file hashes already in the Media Library. */
function aw_cc0_used() {
	global $wpdb;
	return array(
		'ids' => array_flip( $wpdb->get_col( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'aw_openverse_id'" ) ), // phpcs:ignore WordPress.DB
		'md5' => array_flip( $wpdb->get_col( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key IN ('aw_openverse_md5','aw_md5')" ) ), // phpcs:ignore WordPress.DB
	);
}

/**
 * Find and attach a photo. True on success, false when nothing suitable was found, null when the run should stop
 * (quota, rate limit, network) without counting an attempt.
 *
 * @param int $post_id Post ID.
 * @return bool|null
 */
function aw_cc0_assign( $post_id ) {
	$used   = aw_cc0_used();
	$injury = has_term( 'veterinary-care', 'category', $post_id );
	$tried  = array();
	foreach ( aw_cc0_queries( $post_id ) as $qq ) {
		list( $q, $source, $word ) = $qq;
		for ( $page = 1; $page <= 12; $page++ ) {
			$rows = aw_ov_search( $q, $source, $page );
			if ( null === $rows ) {
				return null;
			}
			$pages = (int) ( $rows['_pages'] ?? 1 );
			unset( $rows['_pages'] );
			$cands = array();
			foreach ( $rows as $x ) {
				if ( isset( $used['ids'][ $x['id'] ] ) || isset( $tried[ $x['id'] ] ) ) {
					continue;
				}
				$s = aw_cc0_score( $x, $word, $injury );
				if ( $s >= 0 ) {
					$cands[] = array( $s, $x );
				}
			}
			usort( $cands, fn( $a, $b ) => $b[0] <=> $a[0] );
			foreach ( array_slice( $cands, 0, 3 ) as $c ) {
				$tried[ $c[1]['id'] ] = 1;
				$att                  = aw_cc0_sideload( $post_id, $c[1], $q, $used );
				if ( $att ) {
					set_post_thumbnail( $post_id, $att );
					update_post_meta( $post_id, 'aw_cc0_status', 'done' );
					update_post_meta( $post_id, 'aw_cc0_tried', current_time( 'mysql', true ) );
					aw_cc0_log( '#' . $post_id . ' ← "' . $c[1]['title'] . '" (' . $c[1]['source'] . ', ' . $c[1]['license'] . ', q: ' . $q . ')' );
					return true;
				}
			}
			if ( $page >= $pages || ! $rows ) {
				break;
			}
		}
	}
	update_post_meta( $post_id, 'aw_cc0_status', 'none' );
	update_post_meta( $post_id, 'aw_cc0_attempts', (int) get_post_meta( $post_id, 'aw_cc0_attempts', true ) + 1 );
	update_post_meta( $post_id, 'aw_cc0_tried', current_time( 'mysql', true ) );
	aw_cc0_log( '#' . $post_id . ' no suitable photo (' . implode( '; ', array_map( fn( $x ) => $x[0] . '@' . $x[1], aw_cc0_queries( $post_id ) ) ) . ')' );
	return false;
}

/**
 * Download one Openverse image, resize to 1600 px wide and add it to the Media Library. Attachment ID or 0.
 *
 * @param int    $post_id Item.
 * @param array  $x       Result row.
 * @param string $q       Query used.
 * @param array  $used    Used ids / hashes (updated).
 * @return int
 */
function aw_cc0_sideload( $post_id, $x, $q, &$used ) {
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$tmp = aw_source_download( $x['url'], 40, 26 * MB_IN_BYTES ); // over 25 MB is refused below
	if ( is_wp_error( $tmp ) ) {
		aw_cc0_log( '#' . $post_id . ' download failed: ' . $x['url'] . ' (' . $tmp->get_error_message() . ')' );
		return 0;
	}
	$size = @getimagesize( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	$md5  = (string) md5_file( $tmp );
	if ( ! $size || ! in_array( $size[2], array( IMAGETYPE_JPEG, IMAGETYPE_WEBP ), true ) || $size[0] < 1000 || filesize( $tmp ) > 25 * MB_IN_BYTES || isset( $used['md5'][ $md5 ] ) ) {
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return 0;
	}
	$ed = wp_get_image_editor( $tmp );
	if ( is_wp_error( $ed ) ) {
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return 0;
	}
	$ed->resize( 1600, null, false );
	$ed->set_quality( 82 );
	$saved = $ed->save( $tmp . '-1600.jpg', 'image/jpeg' );
	@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	if ( is_wp_error( $saved ) ) {
		return 0;
	}
	$title = wp_strip_all_tags( html_entity_decode( get_the_title( $post_id ), ENT_QUOTES ) );
	$name  = substr( sanitize_title( $title ), 0, 80 ) . '.jpg';
	$att   = media_handle_sideload( array( 'name' => $name, 'tmp_name' => $saved['path'] ), $post_id, $title, array( 'post_excerpt' => '', 'post_content' => '' ) );
	if ( is_wp_error( $att ) ) {
		@unlink( $saved['path'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		aw_cc0_log( '#' . $post_id . ' could not be added to the Media Library: ' . $att->get_error_message() );
		return 0;
	}
	update_post_meta( $att, '_wp_attachment_image_alt', function_exists( 'aw_seo_plain' ) ? aw_seo_plain( $title ) : $title );
	update_post_meta( $att, 'aw_cc0_source', esc_url_raw( $x['landing'] ) );
	update_post_meta( $att, 'aw_cc0_license', $x['license'] . ' ' . $x['lurl'] );
	update_post_meta( $att, 'aw_openverse_id', $x['id'] );
	update_post_meta( $att, 'aw_openverse_md5', $md5 );
	update_post_meta( $att, 'aw_openverse_query', $q );
	update_post_meta( $att, 'aw_openverse_title', $x['title'] );
	$used['ids'][ $x['id'] ] = 1;
	$used['md5'][ $md5 ]     = 1;
	if ( function_exists( 'aw_thumb_hash' ) ) {
		$used['md5'][ aw_thumb_hash( $att ) ] = 1;
	}
	return (int) $att;
}

/** Counts for Tools → Check site. */
function aw_cc0_counts() {
	global $wpdb;
	$from = aw_cc0_items_sql();
	$row  = $wpdb->get_row( // phpcs:ignore WordPress.DB
		"SELECT COUNT(*) total,
			SUM( si.meta_value LIKE 'https://%' AND COALESCE(ok.meta_value,'') = '1' ) pub_ok,
			SUM( si.meta_value LIKE 'https://%' AND ok.meta_value IS NULL ) pub_unchecked,
			SUM( si.meta_value <> '' AND ( ok.meta_value = '0' OR si.meta_value NOT LIKE 'https://%' ) ) pub_failed,
			SUM( si.meta_value IS NULL OR si.meta_value = '' ) pub_none,
			SUM( cc.meta_value IS NOT NULL ) cc0,
			SUM( th.meta_value IS NULL AND ( si.meta_value IS NULL OR si.meta_value NOT LIKE 'https://%' OR ok.meta_value = '0' ) ) placard,
			SUM( th.meta_value IS NULL AND st.meta_value = 'none' AND at.meta_value >= " . (int) AW_CC0_MAX_ATTEMPTS . " ) gave_up
		" . str_replace(
			'WHERE',
			"LEFT JOIN {$wpdb->postmeta} si ON si.post_id = p.ID AND si.meta_key = 'aw_source_image'
			LEFT JOIN {$wpdb->postmeta} ok ON ok.post_id = p.ID AND ok.meta_key = 'aw_source_image_ok'
			LEFT JOIN {$wpdb->postmeta} th ON th.post_id = p.ID AND th.meta_key = '_thumbnail_id'
			LEFT JOIN {$wpdb->postmeta} cc ON cc.post_id = th.meta_value AND cc.meta_key = 'aw_cc0_source'
			LEFT JOIN {$wpdb->postmeta} st ON st.post_id = p.ID AND st.meta_key = 'aw_cc0_status'
			LEFT JOIN {$wpdb->postmeta} at ON at.post_id = p.ID AND at.meta_key = 'aw_cc0_attempts'
			WHERE",
			$from
		),
		ARRAY_A
	);
	return array_map( 'intval', (array) $row );
}

add_action(
	'aw_site_check_after',
	function () {
		$c = aw_cc0_counts();
		echo '<h2>Pictures on News, Library and research items</h2><table class="form-table" role="presentation"><tbody>';
		foreach (
			array(
				'Items (published, without a document page)'          => $c['total'] ?? 0,
				'Publisher image checked and loading'                 => $c['pub_ok'] ?? 0,
				'Publisher image not checked yet'                     => $c['pub_unchecked'] ?? 0,
				'Publisher image failed (broken or not an image)'     => $c['pub_failed'] ?? 0,
				'No publisher image'                                  => $c['pub_none'] ?? 0,
				'CC0 photo assigned (Openverse)'                      => $c['cc0'] ?? 0,
				'Still no photo of its own (category photo or placard)'  => $c['placard'] ?? 0,
				'No suitable CC0 photo after ' . AW_CC0_MAX_ATTEMPTS . ' tries' => $c['gave_up'] ?? 0,
			) as $k => $v
		) {
			echo '<tr><th scope="row">' . esc_html( $k ) . '</th><td>' . (int) $v . '</td></tr>';
		}
		$q = (array) get_option( 'aw_ov_quota', array() );
		echo '</tbody></table><p>Openverse requests today: ' . (int) ( ( $q['d'] ?? '' ) === gmdate( 'Y-m-d' ) ? $q['n'] : 0 ) . ' of ' . (int) AW_CC0_DAILY_REQUESTS . '. Next run: ' . esc_html( wp_next_scheduled( 'aw_cc0_run' ) ? wp_date( 'j M H:i', wp_next_scheduled( 'aw_cc0_run' ) ) : 'not scheduled' ) . '.</p>';
		$log = array_slice( (array) get_option( 'aw_cc0_log', array() ), -15 );
		if ( $log ) {
			echo '<details><summary>Last log lines</summary><pre style="white-space:pre-wrap;max-width:900px">' . esc_html( implode( "\n", array_reverse( $log ) ) ) . '</pre></details>';
		}
	}
);
