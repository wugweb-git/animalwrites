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

const AW_PRESS_FORMATS = array( 'article' => 'Article', 'video' => 'Video', 'tv' => 'TV', 'podcast' => 'Podcast' );
const AW_PRESS_UA      = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';

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
	$res   = wp_remote_get( $url, array( 'timeout' => 20, 'redirection' => 5, 'user-agent' => AW_PRESS_UA, 'headers' => array( 'Accept-Language' => 'en-IN,en;q=0.9' ) ) );
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
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$att = media_sideload_image( $image, $id, get_the_title( $id ), 'id' );
		if ( ! is_wp_error( $att ) ) {
			set_post_thumbnail( $id, $att );
			update_post_meta( $id, 'aw_press_thumb_src', $image );
			$found[] = 'thumbnail';
		}
	}
	if ( ! has_post_thumbnail( $id ) && ! get_post_meta( $id, 'aw_press_logo', true ) ) {
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

/** One card (News card anatomy): thumbnail · publisher + logo · date · headline · format tag. One external link. */
function aw_press_card( $p ) {
	$url   = (string) get_post_meta( $p->ID, 'aw_press_url', true );
	$pub   = (string) get_post_meta( $p->ID, 'aw_press_publisher', true );
	$date  = (string) get_post_meta( $p->ID, 'aw_press_date', true );
	$fmt   = (string) get_post_meta( $p->ID, 'aw_press_format', true );
	$logo  = (int) get_post_meta( $p->ID, 'aw_press_logo', true );
	$logo  = $logo ? (string) wp_get_attachment_image_url( $logo, 'thumbnail' ) : '';
	$thumb = (string) get_the_post_thumbnail_url( $p, 'medium_large' );
	$video = (string) get_post_meta( $p->ID, 'aw_press_video', true );
	$fallback = $logo ? '<img class="aw-press-logo-large" src="' . esc_url( $logo ) . '" alt="" loading="lazy">' : '<span class="aw-story-ph">' . esc_html( $pub ? $pub : 'Media' ) . '</span>';
	$media = $thumb
		? '<img src="' . esc_url( $thumb ) . '" alt="" loading="lazy" decoding="async" onerror="this.parentNode.classList.add(\'is-failed\')">' . '<span class="aw-press-fallback">' . $fallback . '</span>'
		: '<span class="aw-press-fallback">' . $fallback . '</span>';
	return '<article class="aw-story aw-story--card aw-press-card">'
		. '<div class="aw-story-media' . ( $thumb ? '' : ' is-failed' ) . '">' . $media . ( $video || 'video' === $fmt ? '<span class="aw-press-play" aria-hidden="true">▶</span>' : '' ) . '</div>'
		. '<div class="aw-story-body">'
		. ( $pub ? '<p class="aw-src">' . ( $logo ? '<img class="aw-src-icon" src="' . esc_url( $logo ) . '" alt="" width="16" height="16" loading="lazy">' : '' ) . '<span class="aw-src-name">' . esc_html( $pub ) . '</span></p>' : '' )
		. '<h2 class="aw-story-title"><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( get_the_title( $p ) ) . ' ' . aw_i( 'arrow-up-right' ) . '<span class="screen-reader-text"> (opens in a new tab)</span></a></h2>'
		. '<p class="aw-story-meta kit-tags">' . ( $date ? '<time datetime="' . esc_attr( $date ) . '">' . esc_html( wp_date( 'j M Y', strtotime( $date ) ) ) . '</time>' : '' ) . ( $fmt ? kits_tag( AW_PRESS_FORMATS[ $fmt ] ?? $fmt ) : '' ) . '</p>'
		. '</div></article>';
}

// [aw_media_list]: the cards (replaces the old plain list).
add_action(
	'init',
	function () {
		remove_shortcode( 'aw_media_list' );
		add_shortcode(
			'aw_media_list',
			function () {
				$items = aw_press_list();
				if ( ! $items ) {
					return '<div class="aw-empty"><p>' . esc_html( 'No coverage listed yet.' ) . '</p></div>'; // OWNER copy (Phase 11 I5)
				}
				return '<div class="aw-news-grid aw-press-grid">' . implode( '', array_map( 'aw_press_card', $items ) ) . '</div>';
			}
		);
	},
	20
);

/** Media & PR page schema: CollectionPage + ItemList. */
function aw_press_schema() {
	$list = array();
	foreach ( aw_press_list() as $i => $p ) {
		$list[] = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'item'     => array_filter(
				array(
					'@type'         => 'video' === get_post_meta( $p->ID, 'aw_press_format', true ) ? 'VideoObject' : 'NewsArticle',
					'headline'      => get_the_title( $p ),
					'name'          => get_the_title( $p ),
					'url'           => (string) get_post_meta( $p->ID, 'aw_press_url', true ),
					'datePublished' => (string) get_post_meta( $p->ID, 'aw_press_date', true ),
					'publisher'     => get_post_meta( $p->ID, 'aw_press_publisher', true ) ? array( '@type' => 'Organization', 'name' => (string) get_post_meta( $p->ID, 'aw_press_publisher', true ) ) : null,
					'image'         => (string) get_the_post_thumbnail_url( $p, 'large' ),
				)
			),
		);
	}
	return array(
		'@type'      => 'CollectionPage',
		'@id'        => get_permalink() . '#page',
		'url'        => get_permalink(),
		'name'       => get_the_title(),
		'inLanguage' => 'en-IN',
		'isPartOf'   => array( '@id' => home_url( '/#website' ) ),
		'mainEntity' => array( '@type' => 'ItemList', 'itemListElement' => $list ),
	);
}

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
