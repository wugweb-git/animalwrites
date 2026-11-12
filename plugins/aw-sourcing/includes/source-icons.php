<?php
/**
 * Source icons (13 Nov 2026): each source's own site icon, fetched once on the server and kept in the media library,
 * so SourceWeb's lists show a publisher's mark without asking any third-party favicon service.
 *
 * - Runs after the hourly home-page check (source-sites.php), in the background only, 10 hosts a run, never on page
 *   load. It takes the icon the home page declares (<link rel="icon">, kept by that check), else /favicon.ico.
 * - PNG, ICO, JPEG, GIF or WebP up to 100 KB that PHP reads as an image; never SVG (it can carry script).
 * - Stored as an attachment titled "Source icon: host" (file source-icon-host), Media group "Source icon".
 * - Only hosts with no publisher icon in Kits Core (aw_favicon_url()) are fetched. A host that has an icon is not fetched again; one with none is tried again after 7 days. Nothing is deleted.
 * - Option aw_source_icons (autoload off): host => { id, at, src, miss, v }.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Option: host => [ id, at, src, miss ]. */
const AW_SRC_ICONS = 'aw_source_icons';

add_action( AW_SOURCING_SITE_META_CRON, 'aw_src_icons_run', 20 );

/**
 * The icon's address in the media library for a host, or ''. The publisher's icon that Kits Core keeps (aw_favicon_url(),
 * the one the News cards show) comes first; this store only fills the hosts that has none.
 *
 * @param string $host Host as aw_host() keeps it.
 */
function aw_src_icon_url( $host ) {
	$kits = function_exists( 'aw_favicon_url' ) ? (string) aw_favicon_url( (string) $host ) : '';
	if ( '' !== $kits ) {
		return $kits;
	}
	static $icons = null;
	if ( null === $icons ) {
		$icons = get_option( AW_SRC_ICONS, array() );
		$icons = is_array( $icons ) ? $icons : array();
	}
	$id = (int) ( $icons[ (string) $host ]['id'] ?? 0 );
	if ( ! $id ) {
		return '';
	}
	$url = wp_get_attachment_image_url( $id, 'thumbnail' );
	return $url ? (string) $url : '';
}

/**
 * An icon address from a page (relative, protocol-relative or absolute) made absolute against the host, or ''.
 *
 * @param string $href As the page wrote it.
 * @param string $host The site's host.
 */
function aw_src_icon_abs( $href, $host ) {
	$href = trim( (string) $href );
	if ( '' === $href ) {
		return '';
	}
	if ( 0 === strpos( $href, '//' ) ) {
		$href = 'https:' . $href;
	} elseif ( ! preg_match( '#^https?://#i', $href ) ) {
		$href = 'https://' . $host . '/' . ltrim( $href, '/' );
	}
	return wp_http_validate_url( $href ) ? $href : '';
}

/**
 * Fetch one icon and keep it as an attachment.
 *
 * @param string $url  Icon address.
 * @param string $host The source's host (names the file and title).
 * @return int|string Attachment ID, or why not.
 */
function aw_src_icon_fetch( $url, $host ) {
	$r = wp_safe_remote_get(
		$url,
		array(
			'timeout'             => 6,
			'redirection'         => 3,
			'limit_response_size' => 102400,
			'user-agent'          => 'AnimalWrites-SourceCheck/1.0 (+' . home_url( '/' ) . '; site icon of a news source)',
		)
	);
	if ( is_wp_error( $r ) ) {
		return mb_substr( 'error: ' . $r->get_error_message(), 0, 120 );
	}
	$code = (int) wp_remote_retrieve_response_code( $r );
	$body = (string) wp_remote_retrieve_body( $r );
	if ( $code < 200 || $code >= 300 ) {
		return 'HTTP ' . $code;
	}
	if ( '' === $body || strlen( $body ) >= 102400 ) {
		return 'empty or over 100 KB';
	}
	$size = @getimagesizefromstring( $body ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- a non-image is an expected answer, not an error.
	$ext  = array(
		IMAGETYPE_PNG  => 'png',
		IMAGETYPE_ICO  => 'ico',
		IMAGETYPE_JPEG => 'jpg',
		IMAGETYPE_GIF  => 'gif',
		IMAGETYPE_WEBP => 'webp',
	)[ is_array( $size ) ? (int) $size[2] : 0 ] ?? '';
	if ( '' === $ext ) {
		return 'not a PNG, ICO, JPEG, GIF or WebP image';
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$tmp = wp_tempnam( 'source-icon' );
	if ( ! $tmp || false === file_put_contents( $tmp, $body ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions -- a temp file for media_handle_sideload().
		return 'could not write a temp file';
	}
	$name = 'source-icon-' . sanitize_title( str_replace( '.', '-', $host ) ) . '.' . $ext;
	$mime = image_type_to_mime_type( (int) $size[2] );
	// The type was read from the bytes above. WordPress's own check names an ICO image/vnd.microsoft.icon and then
	// refuses it against its list (image/x-icon); for this one file, its type is the one just read.
	$type = function ( $data, $file, $filename ) use ( $name, $ext, $mime ) {
		return $filename === $name ? array( 'ext' => $ext, 'type' => 'ico' === $ext ? 'image/x-icon' : $mime, 'proper_filename' => false ) : $data;
	};
	add_filter( 'wp_check_filetype_and_ext', $type, 99, 3 );
	$id = media_handle_sideload(
		array(
			'name'     => $name,
			'tmp_name' => $tmp,
		),
		0,
		mb_substr( 'Source icon: ' . $host, 0, 100 ),
		array( 'post_content' => mb_substr( 'Site icon of ' . $host . ', fetched from ' . $url, 0, 160 ) )
	);
	remove_filter( 'wp_check_filetype_and_ext', $type, 99 );
	if ( file_exists( $tmp ) ) {
		wp_delete_file( $tmp );
	}
	if ( is_wp_error( $id ) ) {
		return mb_substr( 'error: ' . $id->get_error_message(), 0, 120 );
	}
	if ( taxonomy_exists( 'aw_media_group' ) ) {
		wp_set_object_terms( (int) $id, 'Source icon', 'aw_media_group' );
	}
	return (int) $id;
}

/**
 * One background run: up to $limit listed hosts with no icon yet (never tried, or a miss over 7 days old), hosts with
 * items first. The home-page check's icon address first, then /favicon.ico.
 *
 * @param int $limit Hosts per run.
 */
function aw_src_icons_run( $limit = 10 ) {
	$icons = get_option( AW_SRC_ICONS, array() );
	$icons = is_array( $icons ) ? $icons : array();
	$meta  = get_option( AW_SOURCING_SITE_META, array() );
	$meta  = is_array( $meta ) ? $meta : array();
	$want  = array();
	$pubs  = aw_publishers();
	foreach ( $pubs as $h => $p ) {
		if ( empty( $p['kind'] ) ) {
			$want[ $h ] = (int) ( $p['count'] ?? 0 ) > 0;
		}
	}
	foreach ( aw_sourcing_press_hosts() as $h ) {
		$want[ $h ] = true;
	}
	foreach ( array_keys( $want ) as $h ) {
		if ( (int) ( $pubs[ $h ]['icon'] ?? 0 ) > 0 ) {
			unset( $want[ $h ] ); // Kits Core already keeps this publisher's icon
		}
	}
	$retry = gmdate( 'Y-m-d H:i', time() - 7 * DAY_IN_SECONDS );
	$todo  = array_filter(
		array_keys( $want ),
		fn( $h ) => empty( $icons[ $h ]['id'] ) && ( (int) ( $icons[ $h ]['v'] ?? 1 ) < 2 || (string) ( $icons[ $h ]['at'] ?? '' ) < $retry ) && preg_match( '/^[a-z0-9.-]+\.[a-z0-9-]+$/', $h )
	);
	$v1 = fn( $h ) => isset( $icons[ $h ] ) && (int) ( $icons[ $h ]['v'] ?? 1 ) < 2; // a miss from before ICO files were accepted: first
	usort( $todo, fn( $a, $b ) => ( (int) $v1( $b ) <=> (int) $v1( $a ) ) ?: ( (int) $want[ $b ] <=> (int) $want[ $a ] ) ?: strcmp( (string) ( $icons[ $a ]['at'] ?? '' ), (string) ( $icons[ $b ]['at'] ?? '' ) ) ?: strcmp( $a, $b ) );
	foreach ( array_slice( $todo, 0, max( 1, (int) $limit ) ) as $h ) {
		$tries = array_unique( array_filter( array( aw_src_icon_abs( (string) ( $meta[ $h ]['icon'] ?? '' ), $h ), 'https://' . $h . '/favicon.ico' ) ) );
		$rec   = array( 'id' => 0, 'at' => gmdate( 'Y-m-d H:i' ), 'src' => '', 'miss' => '', 'v' => 2 ); // v2: ICO files accepted (a v1 miss is tried again at once)
		foreach ( $tries as $url ) {
			$got = aw_src_icon_fetch( $url, $h );
			if ( is_int( $got ) ) {
				$rec['id']   = $got;
				$rec['src']  = $url;
				$rec['miss'] = '';
				break;
			}
			$rec['miss'] = $got;
		}
		$icons[ $h ] = $rec;
	}
	update_option( AW_SRC_ICONS, $icons, false ); // hosts that drop out of the list keep their record: the file stays
}
