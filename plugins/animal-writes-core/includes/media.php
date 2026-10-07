<?php
/**
 * Card images: the article's own preview image (linked, not copied) or a placard-style fallback.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Our own image for an item: news and Legal Watch use the photo chosen for their topic (stored in the media library,
 * openly licensed), never the publisher's photo. Legal items with a hosted government PDF keep its first page.
 * Topic photos are set in the option aw_topic_images = array( topic-slug => attachment ID, 'default' => ID ).
 *
 * @param int    $post_id Post ID.
 * @param string $size    Image size.
 * @return string URL, or '' when there is none (callers then show the placard).
 */
function aw_item_image( $post_id, $size = 'large' ) {
	$type = get_post_type( $post_id );
	if ( ! in_array( $type, array( 'aw_news', 'aw_legal' ), true ) ) {
		return '';
	}
	$fid = (int) get_post_meta( $post_id, 'aw_file_id', true );
	if ( 'aw_legal' === $type && $fid ) {
		return (string) wp_get_attachment_image_url( $fid, $size );
	}
	$map    = (array) get_option( 'aw_topic_images', array() );
	$topics = wp_get_object_terms( $post_id, 'aw_topic', array( 'fields' => 'slugs' ) );
	$topics = is_wp_error( $topics ) ? array() : $topics;
	foreach ( $topics as $slug ) {
		if ( ! empty( $map[ $slug ] ) ) {
			return (string) wp_get_attachment_image_url( (int) $map[ $slug ], $size );
		}
	}
	// Items without a topic get the general photo; a topic with no fitting photo yet shows the placard instead.
	// 'default' may list several general photos; items rotate through them so neighbouring cards differ.
	$general = array_values( array_filter( array_map( 'intval', (array) ( $map['default'] ?? array() ) ) ) );
	return ( ! $topics && $general ) ? (string) wp_get_attachment_image_url( $general[ $post_id % count( $general ) ], $size ) : '';
}

/**
 * Placard fallback: the source name, set large on kraft.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function aw_placard_html( $post_id ) {
	$label = get_post_meta( $post_id, 'aw_source_name', true );
	if ( ! $label ) {
		$label = get_post_meta( $post_id, 'aw_org_name', true );
	}
	if ( ! $label ) {
		$label = get_the_title( $post_id );
	}
	return '<div class="aw-placard" aria-hidden="true"><span>' . esc_html( $label ) . '</span></div>';
}

add_filter(
	'render_block_core/post-featured-image',
	function ( $content, $block, $instance ) {
		if ( '' !== trim( $content ) ) {
			return $content; // A real featured image exists.
		}
		$post_id = $instance->context['postId'] ?? get_the_ID();
		if ( ! $post_id || ! in_array( get_post_type( $post_id ), array( 'aw_news', 'aw_legal', 'aw_research', 'aw_opportunity', 'aw_guide' ), true ) ) {
			return $content;
		}
		$link   = ! empty( $block['attrs']['isLink'] );
		$url    = aw_item_image( $post_id );
		// The item's own page leads with a real photo only: a placard would repeat the title,
		// and a document's first page is already shown by the PDF viewer below.
		if ( ! $url && is_singular() && get_queried_object_id() === $post_id && ! $link ) {
			return '';
		}
		$doc    = '';
		if ( ! $url && ! in_array( get_post_type( $post_id ), array( 'aw_news', 'aw_legal' ), true ) ) {
			$fid = (int) get_post_meta( $post_id, 'aw_file_id', true );
			$doc = $fid ? (string) wp_get_attachment_image_url( $fid, 'large' ) : ''; // First page of the official PDF.
			$url = $doc;
		}
		$inner  = $url
			? '<img src="' . esc_url( $url ) . '" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="this.parentNode.classList.add(\'aw-img-failed\')">'
			: aw_placard_html( $post_id );
		if ( $url ) {
			$inner .= aw_placard_html( $post_id ); // Shown only if the remote image fails to load.
		}
		$inner = $link ? '<a href="' . esc_url( get_permalink( $post_id ) ) . '" tabindex="-1" aria-hidden="true">' . $inner . '</a>' : $inner;
		return '<figure class="wp-block-post-featured-image aw-card-media' . ( $url ? ' has-remote' : '' ) . ( $doc ? ' aw-doc-cover' : '' ) . '">' . $inner . '</figure>';
	},
	10,
	3
);

/**
 * Research and Legal Watch documents: show the mirrored PDF inline (Acts, Rules and judgments may be reproduced
 * under s.52(1)(q) of the Copyright Act, 1957), with download and official-source links.
 */
add_filter(
	'render_block_core/post-excerpt',
	function ( $content ) {
		if ( ! is_singular( array( 'aw_research', 'aw_legal' ) ) ) {
			return $content;
		}
		$post_id = get_queried_object_id();
		$fid     = (int) get_post_meta( $post_id, 'aw_file_id', true );
		$pdf     = $fid ? wp_get_attachment_url( $fid ) : '';
		if ( ! $pdf ) {
			return $content;
		}
		$title  = get_the_title( $post_id );
		$source = (string) get_post_meta( $post_id, 'aw_source_url', true );
		$size   = size_format( (int) filesize( get_attached_file( $fid ) ), 1 );
		$viewer = sprintf(
			'<div class="wp-block-file aw-pdf"><object class="wp-block-file__embed" data="%1$s#view=FitH" type="application/pdf" aria-label="%2$s"><p>%3$s</p></object>'
			. '<p class="aw-pdf-actions"><a class="wp-block-file__button wp-element-button" href="%1$s" download>Download PDF (%4$s)</a>'
			. ( $source ? ' <a href="%5$s">Official source</a>' : '' ) . '</p></div>',
			esc_url( $pdf ),
			esc_attr( $title ),
			esc_html__( 'Your browser cannot show this PDF here. Use the download link below.', 'animal-writes-core' ),
			esc_html( $size ),
			esc_url( $source )
		);
		return $content . $viewer;
	}
);

/**
 * Letter templates: a print button and a clean printed page.
 */
add_filter(
	'render_block_core/post-content',
	function ( $content ) {
		if ( ! is_singular( 'aw_resource' ) ) {
			return $content;
		}
		$button = '<p class="aw-print"><button type="button" class="wp-element-button" onclick="window.print()">'
			. esc_html__( 'Print this letter', 'animal-writes-core' ) . '</button> <span>'
			. esc_html__( 'Sign it, then post it by Speed Post.', 'animal-writes-core' ) . '</span></p>';
		return $button . $content;
	}
);

/**
 * Keep a copy of a government PDF (gov.in / nic.in) so it can be read inside the page.
 *
 * @param int    $post_id Post ID.
 * @param string $url     Source URL.
 * @return int Attachment ID or 0.
 */
function aw_mirror_gov_pdf( $post_id, $url ) {
	$host = (string) wp_parse_url( $url, PHP_URL_HOST );
	if ( ! preg_match( '/\.pdf($|\?)/i', $url ) || ! preg_match( '/(^|\.)(gov\.in|nic\.in)$/i', $host ) || get_post_meta( $post_id, 'aw_file_id', true ) ) {
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
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return 0;
	}
	update_post_meta( $post_id, 'aw_file_id', (int) $fid );
	return (int) $fid;
}
