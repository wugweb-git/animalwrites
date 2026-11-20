<?php
/**
 * Library PDFs: which government documents we may host, the mirror that copies a gov.in / nic.in PDF (or one from the
 * open judgment datasets) into the Media Library, the refetch when the owner allows hosting (aw_doc_host_ok), and
 * egazette.gov.in's missing certificates. Moved here from Animal Writes Core (includes/media.php) on 18 Nov 2026: it is
 * sourcing. The viewer and preview on the item's page are the site layer's (assets/site/includes/library-documents.php).
 * Meta keys (aw_file_id, aw_file_archived, aw_doc_host_ok, aw_source_url) are unchanged.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Documents we may host: Acts, Rules and other Gazette matter, and court judgments and orders
 * (s.52(1)(q) of the Copyright Act, 1957). Other government PDFs (circulars, notices) are linked, not copied.
 *
 * @param int    $post_id Post ID.
 * @param string $url     Source URL.
 * @return bool
 */
function sourceweb_doc_hostable( $post_id, $url ) {
	if ( has_term( array( 'act', 'rule', 'judgment', 'order' ), 'post_tag', $post_id ) || '1' === (string) get_post_meta( $post_id, 'aw_doc_host_ok', true ) ) { // aw_doc_host_ok: hosted by the owner's decision (AWBI items 142 to 145, 2 Nov 2026)
		return true;
	}
	$host = (string) wp_parse_url( $url, PHP_URL_HOST );
	return (bool) preg_match( '/(^|\.)(sci\.gov\.in|egazette\.gov\.in|egazette\.nic\.in|indiacode\.nic\.in|indiacode\.gov\.in|ecourts\.gov\.in)$|^indian-(supreme|high)-court-judgments\.s3\.ap-south-1\.amazonaws\.com$/i', $host );
}

/**
 * Fetch the PDF of a document the owner allows us to host (aw_doc_host_ok set to 1). When the item keeps an archived
 * attachment whose file is missing (aw_file_archived), the file is put back under that attachment, so no second
 * media item is made; otherwise the PDF is mirrored as a new attachment.
 *
 * @param int $post_id Library item.
 * @return int Attachment ID or 0.
 */
function sourceweb_doc_refetch( $post_id ) {
	$fid = (int) get_post_meta( $post_id, 'aw_file_id', true );
	if ( $fid && file_exists( (string) get_attached_file( $fid ) ) ) {
		return $fid;
	}
	$url  = (string) get_post_meta( $post_id, 'aw_source_url', true );
	$host = (string) wp_parse_url( $url, PHP_URL_HOST );
	if ( ! preg_match( '/\.pdf($|\?)/i', $url ) || ! preg_match( '/(^|\.)(gov\.in|nic\.in)$/i', $host ) ) {
		return 0;
	}
	$old  = (int) get_post_meta( $post_id, 'aw_file_archived', true );
	$path = ( $old && 'application/pdf' === get_post_mime_type( $old ) && (int) wp_get_post_parent_id( $old ) === (int) $post_id ) ? (string) get_attached_file( $old ) : '';
	if ( '' === $path || file_exists( $path ) ) {
		return sourceweb_mirror_gov_pdf( $post_id, $url );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	$tmp = download_url( $url, 60 );
	if ( is_wp_error( $tmp ) ) {
		return 0;
	}
	$ok = '%PDF-' === file_get_contents( $tmp, false, null, 0, 5 ) && wp_mkdir_p( dirname( $path ) ) && copy( $tmp, $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	wp_delete_file( $tmp );
	if ( ! $ok ) {
		return 0;
	}
	update_post_meta( $post_id, 'aw_file_id', $old );
	clean_post_cache( $post_id );
	return $old;
}

/** Setting aw_doc_host_ok to 1 (REST or the edit screen) fetches the PDF at once. */
function sourceweb_doc_host_ok_changed( $meta_id, $post_id, $key, $value ) {
	if ( 'aw_doc_host_ok' === $key && '1' === (string) $value && 'aw_legal' === get_post_type( $post_id ) && current_user_can( 'edit_post', $post_id ) ) {
		sourceweb_doc_refetch( (int) $post_id );
	}
}
add_action( 'added_post_meta', 'sourceweb_doc_host_ok_changed', 10, 4 );
add_action( 'updated_post_meta', 'sourceweb_doc_host_ok_changed', 10, 4 );

/**
 * egazette.gov.in sends its certificate without Let's Encrypt's YR2 intermediate and Root YR, so a plain download fails
 * verification. Add those two public certificates to WordPress's CA bundle for that host only (verification stays on).
 */
add_filter(
	'http_request_args',
	function ( $args, $url ) {
		if ( 'egazette.gov.in' !== wp_parse_url( $url, PHP_URL_HOST ) ) {
			return $args;
		}
		$bundle = trailingslashit( get_temp_dir() ) . 'aw-ca-bundle-egazette.pem';
		if ( ! file_exists( $bundle ) || filesize( $bundle ) < 1024 ) { // a bundle left empty or cut short by an earlier failed write is made again
			$core  = file_get_contents( ABSPATH . WPINC . '/certificates/ca-bundle.crt' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$extra = file_get_contents( AW_SOURCING_DIR . 'assets/certs/lets-encrypt-yr2-root-yr.pem' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( ! $core || ! $extra ) {
				return $args;
			}
			$pem = $core . "\n" . $extra;
			if ( strlen( $pem ) !== file_put_contents( $bundle, $pem ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
				wp_delete_file( $bundle );
				return $args;
			}
		}
		$args['sslcertificates'] = $bundle;
		return $args;
	},
	10,
	2
);

/**
 * Keep a copy of a government PDF (gov.in / nic.in, or the open judgment datasets) we may host (sourceweb_doc_hostable) so it can be read inside the page.
 *
 * @param int    $post_id Post ID.
 * @param string $url     Source URL.
 * @return int Attachment ID or 0.
 */
function sourceweb_mirror_gov_pdf( $post_id, $url ) {
	$host = (string) wp_parse_url( $url, PHP_URL_HOST );
	if ( ! preg_match( '/\.pdf($|\?)/i', $url ) || ! preg_match( '/(^|\.)(gov\.in|nic\.in)$|^indian-(supreme|high)-court-judgments\.s3\.ap-south-1\.amazonaws\.com$/i', $host ) || get_post_meta( $post_id, 'aw_file_id', true ) || ! sourceweb_doc_hostable( $post_id, $url ) ) {
		return 0;
	}
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$tmp = download_url( $url, 60 );
	if ( is_wp_error( $tmp ) ) {
		return 0;
	}
	$title = get_the_title( $post_id );
	$fid   = media_handle_sideload( array( 'name' => sanitize_title( $title ) . '.pdf', 'tmp_name' => $tmp ), $post_id, $title );
	if ( is_wp_error( $fid ) ) {
		wp_delete_file( $tmp );
		return 0;
	}
	update_post_meta( $post_id, 'aw_file_id', (int) $fid );
	return (int) $fid;
}
