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
 * The image for an item. Ingested items (News, Library, research), in order: the publisher's image, linked (unless the
 * check in cc0-images.php found it missing, broken or a site logo); their own CC0 photo from Openverse (featured
 * image); then, as for everything else, the hosted PDF page, the category photo, and finally the placard (callers).
 * Library items with a hosted government PDF keep its first page first.
 * Category photos are term meta aw_category_image; the option aw_category_images = array( 'default' => IDs ) holds the general ones.
 *
 * @param int    $post_id Post ID.
 * @param string $size    Image size.
 * @return string URL, or '' when there is none (callers then show the placard).
 */
function aw_item_image( $post_id, $size = 'large' ) {
	if ( aw_is_ingested( $post_id ) && ! get_post_meta( $post_id, 'aw_file_id', true ) && ! get_post_meta( $post_id, 'aw_doc_preview', true ) ) {
		$remote = function_exists( 'aw_publisher_image' ) ? aw_publisher_image( $post_id ) : '';
		$stored = (int) get_post_meta( $post_id, 'aw_source_image_att', true ); // the publisher image kept in the Media Library (cc0-images.php)
		if ( '' !== $remote && $stored && wp_attachment_is_image( $stored ) ) {
			return (string) wp_get_attachment_image_url( $stored, $size );
		}
		if ( '' !== $remote ) {
			return $remote;
		}
		$own = (int) get_post_thumbnail_id( $post_id );
		if ( $own ) {
			return (string) wp_get_attachment_image_url( $own, $size );
		}
	}
	$att = aw_item_image_id( $post_id );
	if ( ! $att ) {
		return '';
	}
	$is_doc = 'aw_legal' === get_post_type( $post_id ) && ! get_post_meta( $post_id, 'aw_file_id', true ) && (int) get_post_meta( $post_id, 'aw_doc_preview', true ) === $att;
	return (string) wp_get_attachment_image_url( $att, $is_doc ? 'full' : $size );
}

/**
 * The attachment behind aw_item_image(): the hosted PDF, its preview, or the item's category photo. 0 when there is none.
 *
 * @param int $post_id Post ID.
 * @return int
 */
function aw_item_image_id( $post_id ) {
	$type = get_post_type( $post_id );
	if ( ! in_array( $type, array( 'aw_news', 'aw_legal', 'post' ), true ) ) { // guides: their category's photo
		return 0;
	}
	$fid = (int) get_post_meta( $post_id, 'aw_file_id', true );
	if ( 'aw_legal' === $type && $fid ) {
		return $fid;
	}
	$preview = (int) get_post_meta( $post_id, 'aw_doc_preview', true );
	if ( 'aw_legal' === $type && $preview ) {
		return $preview;
	}
	// An ingested item's own CC0 photo (cc0-images.php).
	$feat = aw_is_ingested( $post_id ) ? (int) get_post_thumbnail_id( $post_id ) : 0;
	if ( $feat ) {
		return $feat;
	}
	// A category photo belongs to one item at a time across the whole site (thumbs.php). Ingested items without their own
	// image may share their category's photo as a last fallback before the placard (owner 12 Oct 2026).
	$own = function_exists( 'aw_thumb_for' ) ? (int) aw_thumb_for( $post_id ) : 0;
	if ( $own || ! aw_is_ingested( $post_id ) || ! function_exists( 'aw_category_image_id' ) ) {
		return $own;
	}
	$cats = wp_get_object_terms( $post_id, 'category' );
	foreach ( is_wp_error( $cats ) ? array() : $cats as $t ) {
		$att = (int) aw_category_image_id( $t );
		if ( $att ) {
			return $att;
		}
	}
	return 0;
}

/** Brought in by the workers (News, Library, research papers), as opposed to written by us. */
function aw_is_ingested( $post_id ) {
	$type = get_post_type( $post_id );
	return in_array( $type, array( 'aw_news', 'aw_legal' ), true ) || ( 'post' === $type && '' !== (string) get_post_meta( $post_id, 'aw_source_url', true ) );
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
	return '<div class="kit-placard" aria-hidden="true"><span>' . esc_html( $label ) . '</span></div>';
}

add_filter(
	'render_block_core/post-featured-image',
	function ( $content, $block, $instance ) {
		$post_id = $instance->context['postId'] ?? get_the_ID();
		// A real featured image exists. Ingested items go through aw_item_image() even then: the publisher's image comes
		// before their CC0 photo, and the card markup stays the same.
		if ( '' !== trim( $content ) && ! ( $post_id && aw_is_ingested( $post_id ) && ! get_post_meta( $post_id, 'aw_file_id', true ) && ! get_post_meta( $post_id, 'aw_doc_preview', true ) ) ) {
			return $content;
		}
		$cls = (string) ( $block['attrs']['className'] ?? '' );
		$org = $post_id && 'aw_opportunity' === get_post_type( $post_id ) && '' === trim( $content ) ? (int) get_post_meta( $post_id, 'aw_org_logo', true ) : 0;
		if ( $org && wp_attachment_is_image( $org ) ) { // an Opportunity with no picture of its own: its organisation's logo, whole (owner, 15 and 18 Oct 2026)
			$logo = '<span class="aw-story-fallback"><img class="aw-story-logo" src="' . esc_url( (string) wp_get_attachment_image_url( $org, 'medium' ) ) . '" alt="' . esc_attr( (string) get_post_meta( $post_id, 'aw_org_name', true ) ) . '" loading="lazy"></span>';
			$logo = ! empty( $block['attrs']['isLink'] ) ? '<a href="' . esc_url( get_permalink( $post_id ) ) . '" tabindex="-1" aria-hidden="true">' . $logo . '</a>' : $logo;
			return '<figure class="wp-block-post-featured-image kit-card-media is-logo' . ( $cls ? ' ' . esc_attr( $cls ) : '' ) . '">' . $logo . '</figure>';
		}
		if ( false !== strpos( $cls, 'aw-no-fallback' ) && '' === trim( $content ) && ! ( $post_id && aw_is_ingested( $post_id ) ) ) {
			return ''; // lists that show a picture only when the item has one (Resources, Opportunities); ingested items (Library) always get theirs
		}
		if ( ! $post_id || ! in_array( get_post_type( $post_id ), array( 'aw_news', 'aw_legal', 'aw_opportunity', 'post' ), true ) ) {
			return $content;
		}
		$link   = ! empty( $block['attrs']['isLink'] );
		$lead   = is_singular() && get_queried_object_id() === $post_id && ! $link; // the picture at the top of the item's own page
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
		$dims   = '';
		$stored = (int) get_post_meta( $post_id, 'aw_source_image_att', true );
		$att    = $url && ! $doc ? ( $stored && function_exists( 'aw_publisher_image' ) && aw_publisher_image( $post_id ) ? $stored : aw_item_image_id( $post_id ) ) : (int) get_post_meta( $post_id, 'aw_file_id', true ); // the stored publisher image, else own CC0 photo, for ingested items
		$src    = $att ? wp_get_attachment_image_src( $att, 'large' ) : false;
		if ( $src && ! empty( $src[1] ) && ! empty( $src[2] ) && $src[0] === $url ) {
			$dims = ' width="' . (int) $src[1] . '" height="' . (int) $src[2] . '"'; // the box is known before the image arrives
			// The smaller copies too: a lazy picture loads the one that fits its box ("auto"), not the large one. Copies wider
			// than the large one are left out, so a picture that loads at once never picks a bigger file than before.
			$set  = implode( ', ', array_filter( array_map( 'trim', explode( ',', (string) wp_get_attachment_image_srcset( $att, 'large' ) ) ), fn( $c ) => preg_match( '/\s(\d+)w$/', $c, $w ) && (int) $w[1] <= (int) $src[1] ) );
			if ( '' !== $set ) {
				// On a list the card is never wider than half the page below 1200px and a third above (the lead card a little
				// more), so a card that loads at once (Optimization Detective drops "auto" from those) no longer picks the
				// 1024px file (owner, 20 Oct 2026). The item's own lead keeps WordPress's sizes.
				$fit   = $lead ? (string) wp_calculate_image_sizes( 'large', null, null, $att ) : '(max-width: 767px) 100vw, (max-width: 1199px) 50vw, 33vw';
				$dims .= ' srcset="' . esc_attr( $set ) . '" sizes="auto, ' . esc_attr( $fit ) . '"';
			}
		} elseif ( $url && ! $doc && (int) get_post_meta( $post_id, 'aw_source_image_w', true ) > 0 && (int) get_post_meta( $post_id, 'aw_source_image_h', true ) > 0 && (string) get_post_meta( $post_id, 'aw_source_image', true ) === $url ) {
			$dims = ' width="' . (int) get_post_meta( $post_id, 'aw_source_image_w', true ) . '" height="' . (int) get_post_meta( $post_id, 'aw_source_image_h', true ) . '"'; // the publisher's image, measured at ingest
		}
		if ( ! $url ) {
			return ''; // no picture after every source was tried: the card is the stacked row (meta, title, summary), as on the Library list (owner, 18 Oct 2026)
		}
		$load   = $lead ? ' fetchpriority="high" loading="eager"' : ' loading="lazy"'; // the item page's lead is its largest picture: it starts at once (owner, 20 Oct 2026)
		$inner  = '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( aw_seo_plain( get_the_title( $post_id ) ) ) . '"' . $dims . $load . ' decoding="async" referrerpolicy="no-referrer" onerror="this.parentNode.classList.add(\'kit-img-failed\')">';
		if ( $url ) {
			$inner .= aw_placard_html( $post_id ); // Shown only if the remote image fails to load.
		}
		$inner = $link ? '<a href="' . esc_url( get_permalink( $post_id ) ) . '" tabindex="-1" aria-hidden="true">' . $inner . '</a>' : $inner;
		$ratio = preg_match( '#^\d+(?:\.\d+)?/\d+(?:\.\d+)?$#', (string) ( $block['attrs']['aspectRatio'] ?? '' ) ) ? $block['attrs']['aspectRatio'] : ''; // the block's own shape, as core prints it (item leads keep 16:9); else the card token
		return '<figure class="wp-block-post-featured-image kit-card-media' . ( $cls ? ' ' . esc_attr( $cls ) : '' ) . ( $url ? ' has-remote' : '' ) . ( $doc ? ' kit-doc-cover' : '' ) . '"' . ( $ratio ? ' style="aspect-ratio:' . esc_attr( $ratio ) . '"' : '' ) . '>' . $inner . '</figure>';
	},
	10,
	3
);

/*
 * The first picture in a list page (a hub, category, tag or search; not Home, whose hero photograph is preloaded) is usually the
 * largest thing on screen on a phone: it loads at once, with high priority, instead of lazily. Every later one stays lazy.
 */
add_filter(
	'render_block_core/post-featured-image',
	function ( $content ) {
		static $done = false;
		if ( $done || is_front_page() || ( is_singular() && ! aw_hub_type() ) || is_admin() || false === strpos( $content, '<img' ) ) { // an item's own page: WordPress already gives its lead picture priority
			return $content;
		}
		$done = true;
		$content = preg_replace( '/<img(?![^>]*fetchpriority)/', '<img fetchpriority="high"', $content, 1 );
		return preg_replace( '/(<img[^>]*?)\sloading="lazy"/', '$1 loading="eager"', $content, 1 );
	},
	40
);

/*
 * Opportunity cards: on wide screens (1200px and up) the picture is at most 233px wide (four to a row), so the browser
 * is told so and picks the 300px file instead of the 1024px original. Narrower screens keep WordPress's sizes.
 */
add_filter(
	'render_block_core/post-featured-image',
	function ( $content, $block ) {
		if ( false === strpos( (string) ( $block['attrs']['className'] ?? '' ), 'aw-opp-media' ) || false === strpos( $content, ' sizes="' ) ) {
			return $content;
		}
		return preg_replace( '/ sizes="(auto,\s*)?/', ' sizes="$1(min-width: 1200px) 240px, ', $content, 1 );
	},
	30,
	2
);

/*
 * In lists the card title is the card's one link (its ::after covers the card). A linked picture to the same page stays
 * clickable but is out of the tab order and hidden from screen readers, as aw_item_image() cards already are.
 */
add_filter(
	'render_block_core/post-featured-image',
	function ( $content, $block, $instance ) {
		$post_id = (int) ( $instance->context['postId'] ?? 0 );
		$own     = is_singular() && get_queried_object_id() === $post_id; // the item's own page, not a card
		if ( empty( $block['attrs']['isLink'] ) || $own || false !== strpos( $content, 'tabindex="-1"' ) ) {
			return $content;
		}
		return preg_replace( '/<a (?=[^>]*href=)/', '<a tabindex="-1" aria-hidden="true" ', $content, 1 );
	},
	30,
	3
);

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
	if ( has_term( array( 'act', 'rule', 'judgment', 'order' ), 'post_tag', $post_id ) ) {
		return true;
	}
	$host = (string) wp_parse_url( $url, PHP_URL_HOST );
	return (bool) preg_match( '/(^|\.)(sci\.gov\.in|egazette\.gov\.in|egazette\.nic\.in|indiacode\.nic\.in|indiacode\.gov\.in|ecourts\.gov\.in)$|^indian-(supreme|high)-court-judgments\.s3\.ap-south-1\.amazonaws\.com$/i', $host );
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
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
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

/**
 * A featured image with no alt text gets the item's own title (as the press, CC0 and Library pictures already do), whatever
 * path set it: the editor, the workers, media_sideload_image() (which stores no alt). An alt someone wrote is never replaced.
 *
 * @param int    $meta_id  Meta ID (unused).
 * @param int    $post_id  Item ID.
 * @param string $meta_key Meta key.
 * @param mixed  $value    Attachment ID.
 */
function aw_featured_alt_from_title( $meta_id, $post_id, $meta_key, $value ) {
	if ( '_thumbnail_id' !== $meta_key || ! (int) $value || 'attachment' !== get_post_type( (int) $value ) ) {
		return;
	}
	if ( '' !== trim( (string) get_post_meta( (int) $value, '_wp_attachment_image_alt', true ) ) ) {
		return;
	}
	$title = trim( wp_strip_all_tags( html_entity_decode( get_the_title( $post_id ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
	if ( '' !== $title ) {
		update_post_meta( (int) $value, '_wp_attachment_image_alt', $title );
	}
}
add_action( 'added_post_meta', 'aw_featured_alt_from_title', 10, 4 );
add_action( 'updated_post_meta', 'aw_featured_alt_from_title', 10, 4 );
