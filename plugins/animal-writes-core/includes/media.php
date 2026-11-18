<?php
/**
 * Library documents: the hosted PDF viewer, the blurred preview of a document we may not host, which government
 * PDFs we may host and the mirror that copies them, the letter print button, the egazette certificate and court
 * links in a new tab. The card image rules moved to Kits Core (includes/card-images.php) on 18 Nov 2026.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Research and Legal Watch documents: show the mirrored PDF inline (Acts, Rules and judgments may be reproduced
 * under s.52(1)(q) of the Copyright Act, 1957), with download and official-source links.
 */
add_filter(
	'render_block_core/post-excerpt',
	function ( $content, $block = array(), $instance = null ) {
		$post_id = get_queried_object_id();
		// Only the item's own summary: not the excerpts of related-item or other cards on the same page, which
		// otherwise each got this item's document preview or PDF viewer.
		if ( ! is_singular( 'aw_legal' ) || ( $instance && ( isset( $instance->context['queryId'] ) || (int) ( $instance->context['postId'] ?? $post_id ) !== (int) $post_id ) ) ) {
			return $content;
		}
		$fid     = (int) get_post_meta( $post_id, 'aw_file_id', true );
		$pdf     = $fid ? wp_get_attachment_url( $fid ) : '';
		if ( ! $pdf ) {
			return $content . aw_doc_preview_html( $post_id );
		}
		$title  = get_the_title( $post_id );
		$source = (string) get_post_meta( $post_id, 'aw_source_url', true );
		$size   = size_format( (int) filesize( get_attached_file( $fid ) ), 1 );
		$number = trim( (string) get_post_meta( $post_id, 'aw_legal_number', true ) );
		$kinds  = function_exists( 'aw_legal_kinds' ) ? wp_list_pluck( aw_legal_kinds( $post_id ), 'slug' ) : array();
		$head   = '';
		if ( '' !== $number && array_intersect( $kinds, array( 'act', 'rule', 'notification' ) ) ) { // an Act, Rules or notification with its official number: the PDF section is headed by that number (owner, 1 Nov 2026)
			$head = '<h2 class="has-x-large-font-size" id="pdf">' . esc_html( $number . ' PDF' ) . '</h2>';
		}
		$viewer = $head . sprintf(
			'<div class="wp-block-file kit-file aw-pdf"><object class="wp-block-file__embed" data="%1$s#view=FitH" type="application/pdf" aria-label="%2$s"><p>%3$s</p></object>'
			. '<p class="kit-file-actions aw-pdf-actions"><a class="wp-block-file__button wp-element-button" href="%1$s">Download PDF (%4$s)</a></p></div>', // the original is linked in the Sources block (item-head.php). No download attribute (12 Nov 2026): it saved the file with no visible response, so phone readers tapped again (Clarity dead clicks); the link now opens the PDF.
			esc_url( $pdf ),
			esc_attr( $title ),
			esc_html__( 'Your browser cannot show this PDF here. Use the download link below.', 'animal-writes-core' ),
			esc_html( $size ),
			esc_url( $source )
		);
		return $content . $viewer;
	},
	10,
	3
);

/**
 * A document we may not host (no permission yet): a blurred preview of its first page, the reason, and the
 * link to the original.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function aw_doc_preview_html( $post_id ) {
	$preview = (int) get_post_meta( $post_id, 'aw_doc_preview', true );
	$source  = (string) get_post_meta( $post_id, 'aw_source_url', true );
	if ( ! $preview || ! $source ) {
		return '';
	}
	$host   = preg_replace( '/^www\./', '', (string) wp_parse_url( $source, PHP_URL_HOST ) );
	$issuer = function_exists( 'aw_item_issuer' ) ? aw_item_issuer( $post_id ) : null;
	$who    = $issuer ? strtoupper( $issuer->slug ) : $host;
	return '<div class="aw-doc-preview"><a href="' . esc_url( $source ) . '" rel="noopener" tabindex="-1" aria-hidden="true">' // the button below is the one link
		. wp_get_attachment_image( $preview, 'full', false, array( 'alt' => '', 'loading' => 'lazy' ) ) . '</a>'
		. '<div class="aw-doc-preview-text"><p>' . esc_html( sprintf( 'We don\'t have permission from the %s to host this document yet, so it isn\'t shown here.', $who ) ) . '</p>'
		. '<p><a class="wp-element-button" href="' . esc_url( $source ) . '" rel="noopener">' . esc_html( 'Read it on ' . $host ) . '</a></p></div></div>';
}

/**
 * Documents we may host: Acts, Rules and other Gazette matter, and court judgments and orders
 * (s.52(1)(q) of the Copyright Act, 1957). Other government PDFs (circulars, notices) are linked, not copied.
 *
 * @param int    $post_id Post ID.
 * @param string $url     Source URL.
 * @return bool
 */
function aw_doc_hostable( $post_id, $url ) {
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
function aw_doc_refetch( $post_id ) {
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
		return aw_mirror_gov_pdf( $post_id, $url );
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
function aw_doc_host_ok_changed( $meta_id, $post_id, $key, $value ) {
	if ( 'aw_doc_host_ok' === $key && '1' === (string) $value && 'aw_legal' === get_post_type( $post_id ) && current_user_can( 'edit_post', $post_id ) ) {
		aw_doc_refetch( (int) $post_id );
	}
}
add_action( 'added_post_meta', 'aw_doc_host_ok_changed', 10, 4 );
add_action( 'updated_post_meta', 'aw_doc_host_ok_changed', 10, 4 );

/**
 * Letter templates: a print button and a clean printed page.
 */
add_filter(
	'render_block_core/post-content',
	function ( $content ) {
		if ( ! ( is_singular( 'aw_legal' ) && has_term( 'template', 'post_tag', get_queried_object_id() ) ) ) {
			return $content;
		}
		$button = '<p class="kit-print"><button type="button" class="wp-element-button" onclick="window.print()">'
			. esc_html__( 'Print this letter', 'animal-writes-core' ) . '</button> <span>'
			. esc_html__( 'Sign it, then post it by Speed Post.', 'animal-writes-core' ) . '</span></p>';
		return $button . $content;
	}
);

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
		if ( ! file_exists( $bundle ) ) {
			file_put_contents( $bundle, file_get_contents( ABSPATH . WPINC . '/certificates/ca-bundle.crt' ) . "\n" . file_get_contents( AW_CORE_DIR . 'assets/certs/lets-encrypt-yr2-root-yr.pem' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		$args['sslcertificates'] = $bundle;
		return $args;
	},
	10,
	2
);

/*
 * Links to a court's own site open in a new tab: the Delhi High Court's hyperlinking policy asks that its pages open in a
 * new window and are never framed (owner, 25 Oct 2026). Item text only; template links already open in a new tab.
 */
add_filter(
	'the_content',
	function ( $content ) {
		if ( false === stripos( $content, 'court' ) && false === stripos( $content, 'sci.gov.in' ) ) {
			return $content;
		}
		$p = new WP_HTML_Tag_Processor( $content );
		while ( $p->next_tag( 'A' ) ) {
			$host = strtolower( (string) wp_parse_url( (string) $p->get_attribute( 'href' ), PHP_URL_HOST ) );
			if ( '' === $host || ! preg_match( '/(^|\.)(sci\.gov\.in|ecourts\.gov\.in)$|court/', $host ) ) {
				continue;
			}
			$p->set_attribute( 'target', '_blank' );
			$p->set_attribute( 'rel', 'noopener' );
		}
		return $p->get_updated_html();
	},
	20
);

/**
 * Keep a copy of a government PDF (gov.in / nic.in, or the open judgment datasets) we may host (aw_doc_hostable) so it can be read inside the page.
 *
 * @param int    $post_id Post ID.
 * @param string $url     Source URL.
 * @return int Attachment ID or 0.
 */
function aw_mirror_gov_pdf( $post_id, $url ) {
	$host = (string) wp_parse_url( $url, PHP_URL_HOST );
	if ( ! preg_match( '/\.pdf($|\?)/i', $url ) || ! preg_match( '/(^|\.)(gov\.in|nic\.in)$|^indian-(supreme|high)-court-judgments\.s3\.ap-south-1\.amazonaws\.com$/i', $host ) || get_post_meta( $post_id, 'aw_file_id', true ) || ! aw_doc_hostable( $post_id, $url ) ) {
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

/*
 * No picture twice on an item's own page: when the lead picture is the same file the article body already shows (e.g. a
 * Library item's document preview, both above the summary and in the text), the lead picture is left out. Lists and
 * cards are not affected.
 */
add_filter(
	'render_block_core/post-featured-image',
	function ( $content, $block, $instance ) {
		$post_id = (int) ( $instance->context['postId'] ?? 0 );
		if ( '' === trim( $content ) || ! $post_id || ! is_singular() || get_queried_object_id() !== $post_id || ! empty( $block['attrs']['isLink'] ) ) {
			return $content;
		}
		if ( ! preg_match( '/<img[^>]+src="([^"]+)"/', $content, $m ) ) {
			return $content;
		}
		$file = (string) wp_basename( (string) wp_parse_url( html_entity_decode( $m[1] ), PHP_URL_PATH ) );
		$stem = preg_replace( '/(-\d+x\d+|-scaled)?\.[a-z0-9]+$/i', '', $file ); // "name-1024x576.jpg" -> "name"
		$body = (string) get_post_field( 'post_content', $post_id );
		if ( is_singular( 'aw_legal' ) && ! get_post_meta( $post_id, 'aw_file_id', true ) && '' !== aw_doc_preview_html( $post_id ) ) {
			$body .= (string) get_attached_file( (int) get_post_meta( $post_id, 'aw_doc_preview', true ) ); // the document preview under the summary
		}
		return ( strlen( $stem ) > 3 && false !== strpos( $body, $stem ) ) ? '' : $content;
	},
	20,
	3
);

/*
 * One link to the original per item page: when the document preview already ends with "Read it on …" to the source, the
 * template's "Read at <source>" button to the same address is left out (and its empty Buttons row with it).
 */
add_filter(
	'render_block_core/button',
	function ( $content ) {
		if ( ! is_singular() || ! preg_match( '/href="([^"]+)"/', $content, $m ) ) {
			return $content;
		}
		$id = get_queried_object_id();
		if ( ! $id || ! get_post_meta( $id, 'aw_doc_preview', true ) ) {
			return $content;
		}
		$src = (string) get_post_meta( $id, 'aw_source_url', true );
		return ( $src && html_entity_decode( $m[1] ) === $src ) ? '' : $content;
	},
	20
);
add_filter(
	'render_block_core/buttons',
	function ( $content ) {
		// A row with no links is dropped, except an organisation's contact row of greyed buttons (aria-disabled), which
		// stays on listings so a missing phone, email or website reads as not available (owner, 15 Oct 2026).
		if ( is_singular( 'aw_listing' ) && false !== strpos( $content, 'aria-disabled="true"' ) ) {
			return $content;
		}
		return ( is_singular() && false === strpos( $content, '<a' ) && false === strpos( $content, '<button' ) ) ? '' : $content;
	},
	20
);
