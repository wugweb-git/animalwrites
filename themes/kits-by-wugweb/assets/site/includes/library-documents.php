<?php
/**
 * Library documents on an item's own page: the hosted PDF viewer, the blurred preview of a document we may not host,
 * the letter print button, court links in a new tab, no picture twice and one link to the original. Moved here from
 * Animal Writes Core (includes/media.php) on 18 Nov 2026, next to the card image rules (card-images.php). Which PDFs
 * may be hosted and the mirror that copies them are SourceWeb's (aw-sourcing/includes/library-pdfs.php). Meta keys
 * (aw_file_id, aw_doc_preview, aw_source_url, aw_legal_number) are unchanged.
 *
 * @package kits-by-wugweb
 *
 * Site module: moved from Kits Core to the theme's site layer (19 Nov 2026) and loaded by assets/site/site.php while Kits
 * Core is active. The distributed theme and zips leave it out. Names, options, meta keys, hooks, shortcodes and routes
 * are unchanged.
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
		$file    = $fid ? (string) get_attached_file( $fid ) : '';
		if ( ! $pdf || '' === $file || ! file_exists( $file ) ) { // no copy, or its file is gone from uploads (SourceWeb refetches it): no broken viewer
			return $content . kits_doc_preview_html( $post_id );
		}
		$title  = get_the_title( $post_id );
		$size   = size_format( (int) filesize( $file ), 1 );
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
			esc_html__( 'Your browser cannot show this PDF here. Use the download link below.', 'kits-core' ),
			esc_html( $size )
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
function kits_doc_preview_html( $post_id ) {
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
 * Letter templates: a print button and a clean printed page.
 */
add_filter(
	'render_block_core/post-content',
	function ( $content ) {
		if ( ! ( is_singular( 'aw_legal' ) && has_term( 'template', 'post_tag', get_queried_object_id() ) ) ) {
			return $content;
		}
		$button = '<p class="kit-print"><button type="button" class="wp-element-button" onclick="window.print()">'
			. esc_html__( 'Print this letter', 'kits-core' ) . '</button> <span>'
			. esc_html__( 'Sign it, then post it by Speed Post.', 'kits-core' ) . '</span></p>';
		return $button . $content;
	}
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
		if ( is_singular( 'aw_legal' ) && ! get_post_meta( $post_id, 'aw_file_id', true ) && '' !== kits_doc_preview_html( $post_id ) ) {
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
