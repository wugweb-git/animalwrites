<?php
/**
 * Media & PR (Phase 11 I): coverage of Animal Writes as admin-editable items (aw_press), shown on /media/ as cards.
 *
 * Fields: url* · headline · publisher · publisher logo · date · format (Article / Video / TV / Podcast) · thumbnail
 * (featured image) · thumbnail source URL · video embed URL · notes.
 * On save (and with "Refresh preview") the article's own metadata fills the EMPTY fields only: og:title, og:image,
 * twitter:image, og:type, og:video, article:published_time, og:site_name and JSON-LD NewsArticle / VideoObject.
 * The image is downloaded into the Media Library (never hotlinked); its source URL is kept. When there is no image,
 * the publisher's logo (apple-touch-icon or favicon, fetched once per site) is used. Owner input is never overwritten.
 * Cards: one external link each, newest first, undated last. No article text is copied.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_PRESS_FORMATS = array(
	'article'         => 'Article',
	'video'           => 'Video',
	'tv'              => 'TV',
	'podcast'         => 'Podcast',
	'instagram-post'  => 'Instagram post',
	'instagram-reel'  => 'Instagram reel',
	'facebook-post'   => 'Facebook post',
	'facebook-video'  => 'Facebook video',
	'x-post'          => 'X post',
	'youtube-video'   => 'YouTube video',
	'reddit-thread'   => 'Reddit thread',
);
/** The Media & PR filter groups (S4.5): format => group. */
const AW_PRESS_GROUPS = array( 'articles' => 'Articles', 'videos' => 'Videos', 'social' => 'Social posts' );

/** Filter group of a format: articles, videos or social. */
function aw_press_group( $format ) {
	if ( in_array( $format, array( 'video', 'tv', 'instagram-reel', 'facebook-video', 'youtube-video' ), true ) ) {
		return 'videos';
	}
	if ( in_array( $format, array( 'instagram-post', 'facebook-post', 'x-post', 'reddit-thread' ), true ) ) {
		return 'social';
	}
	return 'articles';
}

// Keep the group with the format, and fill it once for existing items.
add_action( 'updated_post_meta', 'aw_press_group_sync', 10, 4 );
add_action( 'added_post_meta', 'aw_press_group_sync', 10, 4 );
function aw_press_group_sync( $mid, $id, $key, $value ) {
	if ( 'aw_press_format' === $key ) {
		update_post_meta( $id, 'aw_press_group', aw_press_group( (string) $value ) );
	}
}
add_action(
	'init',
	function () {
		if ( '1' === get_option( 'aw_press_group_done' ) ) {
			return;
		}
		foreach ( get_posts( array( 'post_type' => 'aw_press', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) {
			update_post_meta( $id, 'aw_press_group', aw_press_group( (string) get_post_meta( $id, 'aw_press_format', true ) ) );
		}
		update_option( 'aw_press_group_done', '1', false );
	},
	30
);
const AW_PRESS_UA      = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';
// Instagram, Facebook and Threads give a browser a login wall (og:image = the platform logo); their link-preview crawlers
// get the post's own picture (owner, 18 Oct 2026). Tried in this order.
const AW_PRESS_SOCIAL_UA = array( 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)', 'Twitterbot/1.0' );

/** A platform's own logo or generic share picture, never a post's thumbnail. */
function aw_press_is_platform_image( $img ) {
	return (bool) preg_match( '#static\.cdninstagram\.com|static\.xx\.fbcdn\.net|/rsrc\.php/|(instagram|facebook|threads|meta)[^/]*logo|/logo[^/]*\.(png|svg|webp|jpe?g)|-logo\.(png|webp|jpe?g)#i', (string) $img );
}

/**
 * Download a picture into the Media Library (CDN addresses of social posts are signed and expire, so never hotlink).
 * An Instagram post is named by its shortcode; alt text "<author>: <post title>".
 *
 * @return int Attachment ID, or 0.
 */
function aw_press_sideload( $image, $id ) {
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$url  = (string) get_post_meta( $id, 'aw_press_url', true );
	$name = preg_match( '#instagram\.com/(?:[^/]+/)?(?:reel|p|tv)/([A-Za-z0-9_-]+)#', $url, $m ) ? 'instagram-' . $m[1]
		: ( preg_match( '#facebook\.com/.*?(\d{8,})/?$#', $url, $m ) ? 'facebook-' . $m[1] : 'press-' . $id );
	$tmp  = aw_source_download( $image ); // cc0-images.php; core download_url() fails fatally on a doubled content-type
	if ( is_wp_error( $tmp ) ) {
		return 0;
	}
	$type = (string) wp_get_image_mime( $tmp );
	$ext  = array( 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/avif' => 'avif' )[ $type ] ?? '';
	if ( ! $ext ) {
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return 0;
	}
	$att = media_handle_sideload( array( 'name' => sanitize_file_name( $name . '.' . $ext ), 'tmp_name' => $tmp ), $id, get_the_title( $id ) );
	if ( is_wp_error( $att ) ) {
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return 0;
	}
	$who = trim( (string) ( get_post_meta( $id, 'aw_press_publisher', true ) ?: get_post_meta( $id, 'aw_press_account', true ) ) );
	update_post_meta( $att, '_wp_attachment_image_alt', ( '' !== $who ? $who . ': ' : '' ) . wp_strip_all_tags( get_the_title( $id ) ) );
	return (int) $att;
}

add_action(
	'init',
	function () {
		register_post_type(
			'aw_press',
			array(
				'labels'       => array( 'name' => 'Media & PR', 'singular_name' => 'Press item', 'add_new_item' => 'Add press item', 'edit_item' => 'Edit press item', 'menu_name' => 'Media & PR' ),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'edit.php?post_type=page',
				'supports'     => array( 'title', 'thumbnail' ),
				'taxonomies'   => array( 'post_tag' ), // campaign tags (campaign-*): which campaign the coverage is about; the Media & PR Campaign filter
			)
		);
	}
);

/** Meta fields: key => label. */
function aw_press_fields() {
	return array(
		'aw_press_url'        => 'Link to the coverage (required)',
		'aw_press_publisher'  => 'Publisher',
		'aw_press_logo'       => 'Publisher logo (media ID)',
		'aw_press_date'       => 'Date (YYYY-MM-DD)',
		'aw_press_format'     => 'Format',
		'aw_press_platform'   => 'Platform',
		'aw_press_account'    => 'Account handle (e.g. @name, r/sub)',
		'aw_press_thumb_src'  => 'Thumbnail source URL',
		'aw_press_video'      => 'Video embed URL',
		'aw_press_notes'      => 'Notes (not shown)',
	);
}

add_action(
	'add_meta_boxes_aw_press',
	function () {
		add_meta_box(
			'aw_press_box',
			'Coverage',
			function ( $post ) {
				wp_nonce_field( 'aw_press_save', 'aw_press_nonce' );
				echo '<p>The headline is the title above. Empty fields are filled from the article\'s own preview data when you save. The thumbnail is the featured image; replace it if it is wrong.</p><table class="form-table">';
				foreach ( aw_press_fields() as $k => $label ) {
					$v = (string) get_post_meta( $post->ID, $k, true );
					echo '<tr><th><label for="' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label></th><td>';
					if ( 'aw_press_format' === $k ) {
						echo '<select id="' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '"><option value="">—</option>';
						foreach ( AW_PRESS_FORMATS as $fk => $fl ) {
							echo '<option value="' . esc_attr( $fk ) . '"' . selected( $v, $fk, false ) . '>' . esc_html( $fl ) . '</option>';
						}
						echo '</select>';
					} elseif ( 'aw_press_platform' === $k ) {
						echo '<select id="' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '">';
						foreach ( AW_PRESS_PLATFORMS as $pk => $pl ) {
							echo '<option value="' . esc_attr( $pk ) . '"' . selected( $v ? $v : 'web', $pk, false ) . '>' . esc_html( 'web' === $pk ? 'Website' : $pl ) . '</option>';
						}
						echo '</select>';
					} elseif ( 'aw_press_notes' === $k ) {
						echo '<textarea class="large-text" rows="2" id="' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '">' . esc_textarea( $v ) . '</textarea>';
					} else {
						echo '<input class="large-text" type="text" id="' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
					}
					echo '</td></tr>';
				}
				$status = (string) get_post_meta( $post->ID, 'aw_press_fetch', true );
				echo '</table><p><label><input type="checkbox" name="aw_press_refresh" value="1"> Refresh preview (fetch the article\'s metadata again and fill empty fields)</label></p>'
					. ( $status ? '<p class="description">Last fetch: ' . esc_html( $status ) . '</p>' : '' );
			},
			'aw_press',
			'normal',
			'high'
		);
	}
);

add_action(
	'save_post_aw_press',
	function ( $id ) {
		if ( wp_is_post_revision( $id ) || ! isset( $_POST['aw_press_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aw_press_nonce'] ) ), 'aw_press_save' ) || ! current_user_can( 'edit_post', $id ) ) {
			return;
		}
		foreach ( array_keys( aw_press_fields() ) as $k ) {
			$raw = wp_unslash( $_POST[ $k ] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$v   = in_array( $k, array( 'aw_press_url', 'aw_press_thumb_src', 'aw_press_video' ), true ) ? esc_url_raw( trim( $raw ) ) : ( 'aw_press_notes' === $k ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw ) );
			if ( 'aw_press_date' === $k && $v && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ) {
				$v = '';
			}
			if ( 'aw_press_logo' === $k ) {
				$v = (string) absint( $v );
				$v = '0' === $v ? '' : $v;
			}
			'' === $v ? delete_post_meta( $id, $k ) : update_post_meta( $id, $k, $v );
		}
		if ( ! empty( $_POST['aw_press_refresh'] ) || ! get_post_meta( $id, 'aw_press_fetch', true ) ) {
			remove_all_actions( 'save_post_aw_press' ); // aw_press_fetch updates the post
			aw_press_fetch( $id );
		}
	}
);

/**
 * Read the coverage page's metadata and fill empty fields; download the image (or the publisher's logo).
 *
 * @param int $id Press item.
 * @return array What was found (for the report).
 */
function aw_press_fetch( $id ) {
	$url = (string) get_post_meta( $id, 'aw_press_url', true );
	if ( ! $url ) {
		return array();
	}
	$found = array();
	$uas   = array_merge( AW_PRESS_SOCIAL_UA, array( AW_PRESS_UA ) ); // crawlers, then a browser (owner, 17–18 Oct 2026)
	foreach ( $uas as $ua ) { // a social post: the next crawler when the first gives no picture or the platform logo
		$res   = wp_remote_get( $url, array( 'timeout' => 20, 'redirection' => 5, 'user-agent' => $ua, 'headers' => array( 'Accept-Language' => 'en-IN,en;q=0.9' ) ) );
		$html  = is_wp_error( $res ) ? '' : (string) wp_remote_retrieve_body( $res );
		$meta  = array();
		if ( $html && preg_match_all( '/<meta\s[^>]*(?:property|name)=["\']([^"\']+)["\'][^>]*content=["\']([^"\']*)["\'][^>]*>|<meta\s[^>]*content=["\']([^"\']*)["\'][^>]*(?:property|name)=["\']([^"\']+)["\'][^>]*>/i', $html, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $x ) {
				$k = strtolower( $x[1] ? $x[1] : $x[4] );
				$v = html_entity_decode( $x[1] ? $x[2] : $x[3], ENT_QUOTES );
				if ( ! isset( $meta[ $k ] ) ) {
					$meta[ $k ] = $v;
				}
			}
		}
		// JSON-LD NewsArticle / VideoObject.
		$ld = array();
		if ( $html && preg_match_all( '#<script[^>]+application/ld\+json[^>]*>(.*?)</script>#is', $html, $s ) ) {
			foreach ( $s[1] as $json ) {
				$d    = json_decode( trim( $json ), true );
				$list = isset( $d['@graph'] ) ? $d['@graph'] : ( isset( $d[0] ) ? $d : array( $d ) );
				foreach ( (array) $list as $node ) {
					$t = (array) ( $node['@type'] ?? array() );
					if ( array_intersect( $t, array( 'NewsArticle', 'Article', 'VideoObject', 'ReportageNewsArticle' ) ) ) {
						$ld = array_merge( $node, $ld );
					}
				}
			}
		}
		$title = $meta['og:title'] ?? ( $ld['headline'] ?? '' );
		$image = $meta['og:image'] ?? ( $meta['twitter:image'] ?? ( is_array( $ld['thumbnailUrl'] ?? null ) ? $ld['thumbnailUrl'][0] : ( $ld['thumbnailUrl'] ?? '' ) ) );
		if ( $image && ! aw_press_is_platform_image( $image ) ) {
			break;
		}
	}
	if ( aw_press_is_platform_image( $image ) ) {
		$image = ''; // never a platform logo as a thumbnail
	}
	$date  = $meta['article:published_time'] ?? ( $ld['datePublished'] ?? ( $ld['uploadDate'] ?? '' ) );
	$site  = $meta['og:site_name'] ?? '';
	$video = $meta['og:video:url'] ?? ( $meta['og:video'] ?? ( $ld['embedUrl'] ?? '' ) );
	$type  = strtolower( (string) ( $meta['og:type'] ?? '' ) );
	$fill  = function ( $k, $v ) use ( $id, &$found ) {
		if ( '' !== (string) $v && '' === (string) get_post_meta( $id, $k, true ) ) {
			update_post_meta( $id, $k, $v );
			$found[] = $k;
		}
	};
	if ( $title && '' === trim( get_the_title( $id ) ) ) {
		wp_update_post( array( 'ID' => $id, 'post_title' => wp_strip_all_tags( $title ) ) );
		$found[] = 'headline';
	}
	$fill( 'aw_press_publisher', $site );
	$fill( 'aw_press_date', $date ? gmdate( 'Y-m-d', strtotime( $date ) ) : '' );
	$fill( 'aw_press_video', $video );
	$fill( 'aw_press_format', $video || false !== strpos( $type, 'video' ) ? 'video' : 'article' );
	if ( ! has_post_thumbnail( $id ) && $image && preg_match( '#^https?://#', $image ) ) {
		$att = aw_press_sideload( $image, $id );
		if ( $att ) {
			set_post_thumbnail( $id, $att );
			update_post_meta( $id, 'aw_press_thumb_src', $image );
			$found[] = 'thumbnail';
		}
	}
	if ( ! get_post_meta( $id, 'aw_press_logo', true ) ) { // the small publisher logo on every card, as on News (owner, 10 Oct 2026)
		$logo = aw_press_site_logo( $url );
		if ( $logo ) {
			update_post_meta( $id, 'aw_press_logo', $logo );
			$found[] = 'publisher logo';
		}
	}
	$status = ( $html ? 'page read' : 'fetch failed' ) . ', ' . wp_date( 'j M Y H:i' ) . ( $found ? '; filled: ' . implode( ', ', $found ) : '; nothing new' ) . ( ! has_post_thumbnail( $id ) ? '; no image (logo shown)' : '' );
	update_post_meta( $id, 'aw_press_fetch', $status );
	return $found;
}

/**
 * The publisher's logo (apple-touch-icon, else favicon), fetched once per site into the Media Library.
 *
 * @param string $url Any page on the site.
 * @return int Attachment ID or 0.
 */
function aw_press_site_logo( $url ) {
	$host  = preg_replace( '/^www\./', '', (string) wp_parse_url( $url, PHP_URL_HOST ) );
	// A social platform's icon is not a publisher logo (owner, 20 Oct 2026: the 22 Instagram reels kept the Instagram
	// logo as their fallback); those cards fall back to the account's name.
	if ( preg_match( '/(^|\.)(instagram|facebook|fb|threads|x|twitter|linkedin|youtube|youtu|reddit|tiktok|pinterest|whatsapp)\.(com|be|net)$/i', $host ) ) {
		return 0;
	}
	$cache = (array) get_option( 'aw_press_logos', array() );
	if ( isset( $cache[ $host ] ) ) {
		return (int) $cache[ $host ];
	}
	$base = wp_parse_url( $url, PHP_URL_SCHEME ) . '://' . wp_parse_url( $url, PHP_URL_HOST );
	$res  = wp_remote_get( $base . '/', array( 'timeout' => 15, 'user-agent' => AW_PRESS_UA ) );
	$html = is_wp_error( $res ) ? '' : (string) wp_remote_retrieve_body( $res );
	$icon = '';
	if ( preg_match( '/<link[^>]+rel=["\'][^"\']*apple-touch-icon[^"\']*["\'][^>]*href=["\']([^"\']+)/i', $html, $m ) || preg_match( '/<link[^>]+rel=["\'](?:shortcut )?icon["\'][^>]*href=["\']([^"\']+)/i', $html, $m ) ) {
		$icon = WP_Http::make_absolute_url( html_entity_decode( $m[1] ), $base . '/' );
	}
	$icon = $icon ? $icon : $base . '/favicon.ico';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$tmp = download_url( $icon, 15 );
	if ( is_wp_error( $tmp ) || ! wp_get_image_mime( $tmp ) || filesize( $tmp ) < 100 ) { // the site refused: Google's favicon service, as on News
		if ( ! is_wp_error( $tmp ) ) {
			wp_delete_file( $tmp );
		}
		$icon = 'https://www.google.com/s2/favicons?sz=64&domain=' . rawurlencode( $host ) . '&.png';
		$tmp  = download_url( $icon, 15 );
	}
	$id  = 0;
	if ( ! is_wp_error( $tmp ) ) {
		$ext = preg_match( '/\.(png|jpe?g|ico|svg|webp)(\?|$)/i', $icon, $e ) ? strtolower( $e[1] ) : 'png';
		$id  = media_handle_sideload( array( 'name' => sanitize_title( $host ) . '-logo.' . $ext, 'tmp_name' => $tmp ), 0, $host . ' logo' );
		$id  = is_wp_error( $id ) ? 0 : (int) $id;
		if ( ! $id ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
	}
	$cache[ $host ] = $id;
	update_option( 'aw_press_logos', $cache, false );
	return $id;
}

/** Press items for the page: newest first, undated last. */
function aw_press_list() {
	$items = get_posts( array( 'post_type' => 'aw_press', 'post_status' => 'publish', 'numberposts' => -1 ) );
	usort(
		$items,
		function ( $a, $b ) {
			$da = (string) get_post_meta( $a->ID, 'aw_press_date', true );
			$db = (string) get_post_meta( $b->ID, 'aw_press_date', true );
			if ( $da === $db ) {
				return $a->menu_order <=> $b->menu_order;
			}
			return '' === $da ? 1 : ( '' === $db ? -1 : strcmp( $db, $da ) );
		}
	);
	return $items;
}

// The Media & PR list is the hub Query Loop on the Media & PR Page (hubs.php, hub-blocks.php).

/** One-time move of the old Settings list (option aw_press) into press items; the option is kept as an archive. */
add_action(
	'admin_init',
	function () {
		if ( '1' === get_option( 'aw_press_migrated' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		foreach ( (array) get_option( 'aw_press', array() ) as $i => $r ) {
			if ( empty( $r['url'] ) ) {
				continue;
			}
			$id = wp_insert_post( array( 'post_type' => 'aw_press', 'post_status' => 'publish', 'post_title' => $r['headline'] ?? '', 'menu_order' => $i ) );
			if ( $id && ! is_wp_error( $id ) ) {
				update_post_meta( $id, 'aw_press_url', esc_url_raw( $r['url'] ) );
				$pub = ucwords( strtolower( (string) ( $r['outlet'] ?? '' ) ) ); // the old list had outlets in capitals
				if ( $pub ) {
					update_post_meta( $id, 'aw_press_publisher', $pub );
				}
				if ( ! empty( $r['date'] ) ) {
					update_post_meta( $id, 'aw_press_date', $r['date'] );
				}
				if ( ! empty( $r['logo'] ) ) {
					update_post_meta( $id, 'aw_press_logo', (int) $r['logo'] );
				}
				aw_press_fetch( $id );
			}
		}
		update_option( 'aw_press_migrated', '1', false );
	}
);


/* ---------- Press discovery (worker) → "Press: to review" ---------- */

/** Platforms the worker reports. */
const AW_PRESS_PLATFORMS = array( 'web' => 'Article', 'youtube' => 'YouTube', 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'x' => 'X', 'threads' => 'Threads', 'linkedin' => 'LinkedIn', 'reddit' => 'Reddit' );

/**
 * A candidate from the press-discovery worker: kept as a pending press item (never published automatically).
 * Duplicates by URL are refused, including items already rejected (trashed), so a rejected link never comes back.
 *
 * @param WP_REST_Request $req Request: url, title, publisher, account, platform, format, date, thumbnail, query.
 * @return WP_REST_Response
 */
function aw_press_ingest( WP_REST_Request $req ) {
	$url = esc_url_raw( (string) $req->get_param( 'source_url' ) );
	if ( ! $url ) {
		return new WP_REST_Response( array( 'status' => 'error', 'message' => 'source_url is required' ), 400 );
	}
	$dupe = get_posts( array( 'post_type' => 'aw_press', 'post_status' => array( 'publish', 'pending', 'draft', 'private', 'future', 'trash' ), 'meta_key' => 'aw_press_url', 'meta_value' => $url, 'fields' => 'ids', 'numberposts' => 1 ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	if ( ! $dupe && function_exists( 'aw_find_by_title' ) ) { // same normalised title within a day either side
		$dupe = aw_find_by_title( array( 'aw_press' ), (string) $req->get_param( 'title' ), (string) $req->get_param( 'source_date' ), 'aw_press_date' );
	}
	if ( $dupe || ( function_exists( 'aw_find_by_source' ) && aw_find_by_source( 'aw_news', $url ) ) ) {
		return new WP_REST_Response( array( 'status' => 'duplicate' ), 200 );
	}
	$id = wp_insert_post( array( 'post_type' => 'aw_press', 'post_status' => 'pending', 'post_title' => sanitize_text_field( (string) $req->get_param( 'title' ) ) ), true );
	if ( is_wp_error( $id ) ) {
		return new WP_REST_Response( array( 'status' => 'error' ), 500 );
	}
	$platform = sanitize_key( (string) $req->get_param( 'platform' ) );
	$meta     = array(
		'aw_press_url'       => $url,
		'aw_press_publisher' => sanitize_text_field( (string) $req->get_param( 'source_name' ) ),
		'aw_press_date'      => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $req->get_param( 'source_date' ) ) ? (string) $req->get_param( 'source_date' ) : '',
		'aw_press_format'    => isset( AW_PRESS_FORMATS[ sanitize_title( (string) $req->get_param( 'format' ) ) ] ) ? sanitize_title( (string) $req->get_param( 'format' ) ) : '', // "Instagram reel" → instagram-reel
		'aw_press_platform'  => isset( AW_PRESS_PLATFORMS[ $platform ] ) ? $platform : 'web',
		'aw_press_account'   => sanitize_text_field( (string) ( $req->get_param( 'account_handle' ) ?: $req->get_param( 'account' ) ) ),
		'aw_press_query'     => sanitize_text_field( (string) $req->get_param( 'query' ) ),
		'aw_press_thumb_src' => esc_url_raw( (string) $req->get_param( 'image_url' ) ),
	);
	foreach ( $meta as $k => $v ) {
		if ( '' !== $v ) {
			update_post_meta( $id, $k, $v );
		}
	}
	if ( function_exists( 'aw_relevance_store' ) ) {
		aw_relevance_store( $id, $req->get_param( 'relevance' ) );
	}
	aw_press_campaign_tags( $id, (array) $req->get_param( 'tags' ) );
	aw_press_fetch( $id ); // fill what the page itself says (thumbnail downloaded, never hotlinked)
	if ( ! has_post_thumbnail( $id ) && $meta['aw_press_thumb_src'] && ! aw_press_is_platform_image( $meta['aw_press_thumb_src'] ) ) {
		$att = aw_press_sideload( $meta['aw_press_thumb_src'], $id );
		if ( $att ) {
			set_post_thumbnail( $id, $att );
		}
	}
	return new WP_REST_Response( array( 'status' => 'created', 'id' => $id ), 201 );
}

/**
 * A press item's campaign: the workers send the topic slugs they matched (campaign-lab-animals-2026 ...); each existing
 * one becomes a campaign link (campaign-links.php; a campaign is not a tag, owner, 20 Oct 2026). Others are ignored.
 */
function aw_press_campaign_tags( $id, $tags ) {
	if ( function_exists( 'aw_set_campaign_links' ) ) {
		aw_set_campaign_links( $id, $tags );
	}
}

// Inbox → "Press to review": the pending items, with a count. Publish = approve; Move to Trash = reject (never comes back).
add_action(
	'admin_menu',
	function () {
		$n = (int) ( wp_count_posts( 'aw_press' )->pending ?? 0 );
		add_submenu_page( 'aw-inbox', 'Press to review', 'Press to review' . ( function_exists( 'aw_menu_count' ) ? aw_menu_count( $n ) : '' ), 'edit_pages', 'edit.php?post_type=aw_press&post_status=pending' );
	},
	20
);
add_filter( 'manage_aw_press_posts_columns', fn( $c ) => array( 'cb' => $c['cb'], 'aw_thumb' => '', 'title' => 'Headline', 'aw_platform' => 'Platform', 'aw_account' => 'Publisher / account', 'aw_date' => 'Date', 'aw_query' => 'Found by', 'aw_link' => 'Link' ) );
add_action(
	'manage_aw_press_posts_custom_column',
	function ( $col, $id ) {
		switch ( $col ) {
			case 'aw_thumb':
				echo has_post_thumbnail( $id ) ? get_the_post_thumbnail( $id, array( 80, 45 ) ) : '—';
				break;
			case 'aw_platform':
				echo esc_html( AW_PRESS_PLATFORMS[ get_post_meta( $id, 'aw_press_platform', true ) ?: 'web' ] ?? 'Article' );
				break;
			case 'aw_account':
				echo esc_html( trim( get_post_meta( $id, 'aw_press_publisher', true ) . ' ' . get_post_meta( $id, 'aw_press_account', true ) ) );
				break;
			case 'aw_date':
				echo esc_html( (string) get_post_meta( $id, 'aw_press_date', true ) );
				break;
			case 'aw_query':
				echo esc_html( (string) get_post_meta( $id, 'aw_press_query', true ) );
				break;
			case 'aw_link':
				$u = (string) get_post_meta( $id, 'aw_press_url', true );
				echo $u ? '<a href="' . esc_url( $u ) . '" target="_blank" rel="noopener noreferrer">Open</a>' : '';
				break;
		}
	},
	10,
	2
);
