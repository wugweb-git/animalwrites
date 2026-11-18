<?php
/**
 * Card images: the article's own preview image (linked, not copied) or a placard-style fallback. Moved from Animal
 * Writes Core (includes/media.php) on 18 Nov 2026; Library document pages (PDF viewer, preview, hosting) stayed there.
 * Meta keys, option names and CSS classes are unchanged. The site's own helpers (component styles, plain titles, hub
 * pages) are used when they are loaded.
 *
 * @package kits-core
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
function kits_item_image( $post_id, $size = 'large' ) {
	if ( kits_is_ingested( $post_id ) && ! get_post_meta( $post_id, 'aw_file_id', true ) && ! get_post_meta( $post_id, 'aw_doc_preview', true ) ) {
		$remote = function_exists( 'sourceweb_publisher_image' ) ? sourceweb_publisher_image( $post_id ) : '';
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
	$att = kits_item_image_id( $post_id );
	if ( ! $att ) {
		return '';
	}
	$is_doc = 'aw_legal' === get_post_type( $post_id ) && ! get_post_meta( $post_id, 'aw_file_id', true ) && (int) get_post_meta( $post_id, 'aw_doc_preview', true ) === $att;
	return (string) wp_get_attachment_image_url( $att, $is_doc ? 'full' : $size );
}

/**
 * The attachment behind kits_item_image(): the hosted PDF, its preview, or the item's category photo. 0 when there is none.
 *
 * @param int $post_id Post ID.
 * @return int
 */
function kits_item_image_id( $post_id ) {
	$type = get_post_type( $post_id );
	if ( ! in_array( $type, array( 'aw_news', 'aw_legal', 'post' ), true ) ) { // guides: their category's photo
		return 0;
	}
	// A cover the owner chose by hand wins over every automatic source (meta.php).
	$cover = (int) get_post_meta( $post_id, 'aw_cover_image_id', true );
	if ( $cover ) {
		return $cover;
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
	$feat = kits_is_ingested( $post_id ) ? (int) get_post_thumbnail_id( $post_id ) : 0;
	if ( $feat ) {
		return $feat;
	}
	// A category photo belongs to one item at a time across the whole site (thumbs.php). Ingested items without their own
	// image may share their category's photo as a last fallback before the placard (owner 12 Oct 2026).
	$own = function_exists( 'kits_thumb_for' ) ? (int) kits_thumb_for( $post_id ) : 0;
	if ( ! kits_is_ingested( $post_id ) ) {
		return $own; // our own content: a category photo used once site-wide
	}
	// Ingested items with no picture: their primary category's own card fallback (owner, 20 Oct 2026; may repeat).
	$card = kits_category_card_id( $post_id );
	return $card ? $card : $own;
}

/**
 * The card fallback of an item's primary category (Rank Math's primary category first, then the others): the category's
 * own 4:3 picture (term meta aw_category_card, its phone banner; owner, 20 Oct 2026), else its photo. 0 when none.
 *
 * @param int $post_id Post ID.
 * @return int Attachment ID.
 */
function kits_category_card_id( $post_id ) {
	$cats = wp_get_object_terms( $post_id, 'category' );
	$cats = is_wp_error( $cats ) ? array() : $cats;
	$main = (int) get_post_meta( $post_id, 'rank_math_primary_category', true );
	usort( $cats, fn( $a, $b ) => (int) ( $b->term_id === $main ) <=> (int) ( $a->term_id === $main ) );
	foreach ( $cats as $t ) {
		$att = (int) get_term_meta( $t->term_id, 'aw_category_card', true );
		$att = $att ? $att : ( function_exists( 'kits_category_image_id' ) ? (int) kits_category_image_id( $t ) : 0 );
		if ( $att ) {
			return $att;
		}
	}
	return 0;
}

/** Brought in by the workers (News, Library, research papers), as opposed to written by us. */
function kits_is_ingested( $post_id ) {
	$type = get_post_type( $post_id );
	return in_array( $type, array( 'aw_news', 'aw_legal' ), true ) || ( 'post' === $type && '' !== (string) get_post_meta( $post_id, 'aw_source_url', true ) );
}

/**
 * Placard fallback: the site's fallback picture (the logo in grey); the source name as a text tile only without it.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function kits_placard_html( $post_id ) {
	// The site's fallback picture is always the Animal Writes logo in grey (owner, 21 Oct 2026): option
	// aw_category_images['default'], attachment 17878. The text tile below is only for a site without it.
	$def  = (array) ( get_option( 'aw_category_images', array() )['default'] ?? array() );
	$logo = $def ? wp_get_attachment_image_src( (int) $def[0], 'medium_large' ) : false;
	if ( $logo ) {
		return '<div class="kit-placard kit-placard--logo" aria-hidden="true"><img src="' . esc_url( $logo[0] ) . '" width="' . (int) $logo[1] . '" height="' . (int) $logo[2] . '" alt="" loading="lazy" decoding="async"></div>';
	}
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
		// A real featured image exists. Ingested items go through kits_item_image() even then: the publisher's image comes
		// before their CC0 photo, and the card markup stays the same.
		if ( '' !== trim( $content ) && ! ( $post_id && kits_is_ingested( $post_id ) && ! get_post_meta( $post_id, 'aw_file_id', true ) && ! get_post_meta( $post_id, 'aw_doc_preview', true ) ) ) {
			return $content;
		}
		$cls = (string) ( $block['attrs']['className'] ?? '' );
		$org = $post_id && 'aw_opportunity' === get_post_type( $post_id ) && '' === trim( $content ) ? (int) get_post_meta( $post_id, 'aw_org_logo', true ) : 0;
		if ( $org && wp_attachment_is_image( $org ) ) { // an Opportunity with no picture of its own: its organisation's logo, whole (owner, 15 and 18 Oct 2026)
			$src  = wp_get_attachment_image_src( $org, 'medium' );
			$wh   = $src && ! empty( $src[1] ) && ! empty( $src[2] ) ? ' width="' . (int) $src[1] . '" height="' . (int) $src[2] . '"' : ''; // its box is known before it arrives: the card does not grow (layout shift, 20 Oct 2026)
			if ( function_exists( 'kits_use_component_style' ) ) {
				kits_use_component_style( 'aw-single' ); // aw-story-fallback, aw-story-logo
				kits_use_component_style( 'aw-lists' ); // kit-card-media is-logo
			}
			$logo = '<span class="aw-story-fallback"><img class="aw-story-logo" src="' . esc_url( $src ? $src[0] : '' ) . '"' . $wh . ' alt="' . esc_attr( (string) get_post_meta( $post_id, 'aw_org_name', true ) ) . '" loading="lazy"></span>';
			$logo = ! empty( $block['attrs']['isLink'] ) ? '<a href="' . esc_url( get_permalink( $post_id ) ) . '" tabindex="-1" aria-hidden="true">' . $logo . '</a>' : $logo;
			return '<figure class="wp-block-post-featured-image kit-card-media is-logo' . ( $cls ? ' ' . esc_attr( $cls ) : '' ) . '">' . $logo . '</figure>';
		}
		if ( false !== strpos( $cls, 'aw-no-fallback' ) && '' === trim( $content ) && ! ( $post_id && kits_is_ingested( $post_id ) ) ) {
			return ''; // lists that show a picture only when the item has one (Resources, Opportunities); ingested items (Library) always get theirs
		}
		if ( ! $post_id || ! in_array( get_post_type( $post_id ), array( 'aw_news', 'aw_legal', 'aw_opportunity', 'post' ), true ) ) {
			return $content;
		}
		$link   = ! empty( $block['attrs']['isLink'] );
		$lead   = is_singular() && get_queried_object_id() === $post_id && ! $link; // the picture at the top of the item's own page
		$url    = kits_item_image( $post_id );
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
		$att    = $url && ! $doc ? ( $stored && function_exists( 'sourceweb_publisher_image' ) && sourceweb_publisher_image( $post_id ) ? $stored : kits_item_image_id( $post_id ) ) : (int) get_post_meta( $post_id, 'aw_file_id', true ); // the stored publisher image, else own CC0 photo, for ingested items
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
		$inner  = '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( function_exists( 'aw_seo_plain' ) ? aw_seo_plain( get_the_title( $post_id ) ) : wp_strip_all_tags( get_the_title( $post_id ) ) ) . '"' . $dims . $load . ' decoding="async" referrerpolicy="no-referrer" onerror="this.parentNode.classList.add(\'kit-img-failed\')">';
		if ( $url ) {
			$inner .= kits_placard_html( $post_id ); // Shown only if the remote image fails to load.
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
		if ( $done || is_front_page() || ( is_singular() && ! ( function_exists( 'aw_hub_type' ) && aw_hub_type() ) ) || is_admin() || false === strpos( $content, '<img' ) ) { // an item's own page: WordPress already gives its lead picture priority
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
 * clickable but is out of the tab order and hidden from screen readers, as kits_item_image() cards already are.
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
 * A featured image with no alt text gets the item's own title (as the press, CC0 and Library pictures already do), whatever
 * path set it: the editor, the workers, media_sideload_image() (which stores no alt). An alt someone wrote is never replaced.
 *
 * @param int    $meta_id  Meta ID (unused).
 * @param int    $post_id  Item ID.
 * @param string $meta_key Meta key.
 * @param mixed  $value    Attachment ID.
 */
function kits_featured_alt_from_title( $meta_id, $post_id, $meta_key, $value ) {
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
add_action( 'added_post_meta', 'kits_featured_alt_from_title', 10, 4 );
add_action( 'updated_post_meta', 'kits_featured_alt_from_title', 10, 4 );
