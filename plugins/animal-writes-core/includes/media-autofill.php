<?php
/**
 * Media Library autofill for new image uploads: fills the fields that are empty, never overwrites a value.
 *
 * - Name: a file or camera name (IMG_2034, DSC00012) becomes plain words, at most 100 characters, never ending in a stop word.
 * - Alternative Text: the name, up to 160 characters.
 * - Caption = SEO title (up to 100 characters): the name, or "host favicon | Animal Writes" / "host logo | Animal Writes".
 * - Description = SEO description: at least 100 and up to 160 characters, written for favicons, logos, source images and any other image.
 * - Image context (kits_image_context): the page the file is attached to or later used on (featured or source image), and its Rank Math focus keyword, up to 160 characters.
 * No admin screen, no options, no CSS.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cut text to a maximum length at a word boundary, never ending in a stop word.
 *
 * @param string $text Text.
 * @param int    $max  Maximum characters.
 * @return string
 */
function aw_media_autofill_cut( $text, $max ) {
	$text = trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
	if ( mb_strlen( $text ) > $max ) {
		$head = mb_substr( $text, 0, $max );
		if ( ' ' !== mb_substr( $text, $max, 1 ) && false !== mb_strrpos( $head, ' ' ) ) {
			$head = mb_substr( $head, 0, mb_strrpos( $head, ' ' ) );
		}
		$text = $head;
	}
	$stop = 'in|of|to|for|and|the|a|an|on|at|by|with|as|from|over|after|into|that|is|are|how|what|or|but';
	do {
		$before = $text;
		$text   = preg_replace( '/(?:^|[\s,;:\-\x{2013}\x{2014}]+)(?:' . $stop . ')$/iu', '', $text );
		$text   = rtrim( $text, " \t,;:-\xE2\x80\x93\xE2\x80\x94" );
	} while ( $text !== $before );
	return $text;
}

/**
 * Turn a title into a readable name: file and camera names become words, entities are decoded.
 *
 * @param string $title Attachment title.
 * @return string
 */
function aw_media_autofill_name( $title ) {
	$title = trim( html_entity_decode( (string) $title, ENT_QUOTES, 'UTF-8' ) );
	$looks = preg_match( '/^(IMG|DSC|PXL|photo-|image|screenshot|whatsapp)\b/i', $title )
		|| false !== strpos( $title, '_' )
		|| preg_match( '/^(?=.*\d)[0-9a-f\s-]+$/i', $title );
	if ( $looks ) {
		$title = ucfirst( trim( preg_replace( '/[\s_\-]+/', ' ', $title ) ) );
	}
	return aw_media_autofill_cut( $title, 100 );
}

/**
 * Favicon or logo details from a name such as "Favicon: host" or "host logo".
 *
 * @param string $name Name.
 * @return array|null Array with kind and host, or null.
 */
function aw_media_autofill_brand( $name ) {
	if ( preg_match( '/^favicon:\s*(\S+)$/i', $name, $m ) ) {
		return array( 'kind' => 'favicon', 'host' => $m[1] );
	}
	if ( preg_match( '/^(\S+)\s+logo$/i', $name, $m ) ) {
		return array( 'kind' => 'logo', 'host' => $m[1] );
	}
	return null;
}

/**
 * SEO title (caption) for a name.
 *
 * @param string $name Name.
 * @return string
 */
function aw_media_autofill_caption( $name ) {
	$brand = aw_media_autofill_brand( $name );
	if ( $brand ) {
		return $brand['host'] . ' ' . $brand['kind'] . ' | Animal Writes';
	}
	return aw_media_autofill_cut( $name, 100 );
}

/**
 * SEO description, at most 160 characters.
 *
 * @param string $name      Name.
 * @param bool   $is_source True when the file name ends in -source.
 * @return string
 */
function aw_media_autofill_description( $name, $is_source ) {
	$brand = aw_media_autofill_brand( $name );
	if ( $brand ) {
		$base = sprintf( '%1$s %2$s, shown beside news from %1$s on Animal Writes', $brand['host'], $brand['kind'] );
		$text = $base . ", India's tracker of animal welfare news, law and help.";
		if ( mb_strlen( $text ) > 160 ) {
			$text = $base . '.';
		}
	} else {
		$short = aw_media_autofill_cut( $name, 45 );
		$text  = $is_source
			? sprintf( 'Source image for the news item “%s”. Animal Writes, India.', $short )
			: sprintf( "Image for “%s” on Animal Writes, India's tracker of animal welfare news, law and help.", $short );
	}
	if ( mb_strlen( $text ) < 100 ) {
		$text .= ' Animal welfare news, law and help in India.'; // Reaches the 100-character minimum the Media Library SEO check expects.
	}
	return mb_substr( $text, 0, 160 );
}

/**
 * Image context line from the page the file is attached to, at most 160 characters.
 *
 * @param int $parent_id Parent post ID.
 * @return string Empty when there is no usable parent.
 */
function aw_media_autofill_context( $parent_id ) {
	$parent = $parent_id ? get_post( $parent_id ) : null;
	if ( ! $parent ) {
		return '';
	}
	$type  = get_post_type_object( $parent->post_type );
	$label = $type ? $type->labels->singular_name : $parent->post_type;
	$text  = sprintf( 'Used on: %1$s: %2$s (ID %3$d).', $label, aw_media_autofill_cut( html_entity_decode( $parent->post_title, ENT_QUOTES, 'UTF-8' ), 50 ), $parent->ID );
	$kw    = trim( (string) strtok( (string) get_post_meta( $parent->ID, 'rank_math_focus_keyword', true ), ',' ) );
	if ( '' === $kw ) {
		return $text;
	}
	$room = 160 - mb_strlen( $text ) - mb_strlen( ' Focus keyword: .' );
	$kw   = $room > 0 ? aw_media_autofill_cut( $kw, $room ) : '';
	return '' !== $kw ? $text . ' Focus keyword: ' . $kw . '.' : $text; // The keyword part is cut or dropped first so the 160-character cap holds.
}

/**
 * Fill the empty fields of a new image attachment.
 *
 * @param int $post_id Attachment ID.
 */
function aw_media_autofill_run( $post_id ) {
	if ( ! function_exists( 'wp_attachment_is_image' ) || ! wp_attachment_is_image( $post_id ) ) {
		return;
	}
	global $wpdb;
	$post = get_post( $post_id );
	$name = $post ? aw_media_autofill_name( $post->post_title ) : '';
	if ( '' === $name ) {
		return;
	}
	$set = array();
	if ( $name !== $post->post_title ) {
		$set['post_title'] = $name;
	}
	if ( '' === trim( $post->post_excerpt ) ) {
		$set['post_excerpt'] = aw_media_autofill_caption( $name );
	}
	if ( '' === trim( $post->post_content ) ) {
		$file                = (string) get_attached_file( $post_id );
		$set['post_content'] = aw_media_autofill_description( $name, '-source' === substr( pathinfo( $file, PATHINFO_FILENAME ), -7 ) );
	}
	if ( $set ) {
		$wpdb->update( $wpdb->posts, $set, array( 'ID' => $post_id ) );
		clean_post_cache( $post_id );
	}
	if ( '' === trim( (string) get_post_meta( $post_id, '_wp_attachment_image_alt', true ) ) ) {
		update_post_meta( $post_id, '_wp_attachment_image_alt', aw_media_autofill_cut( $name, 160 ) );
	}
	if ( '' === trim( (string) get_post_meta( $post_id, 'kits_image_context', true ) ) ) {
		$context = aw_media_autofill_context( (int) $post->post_parent );
		if ( '' !== $context ) {
			update_post_meta( $post_id, 'kits_image_context', $context );
		}
	}
}
add_action( 'add_attachment', 'aw_media_autofill_run' );

/**
 * Fill the image context when a picture is used on a page after it was uploaded (featured image or news source image).
 *
 * @param int    $meta_id  Meta ID (unused).
 * @param int    $post_id  Post the meta belongs to.
 * @param string $meta_key Meta key.
 * @param mixed  $value    Meta value (the attachment ID).
 */
function aw_media_autofill_used( $meta_id, $post_id, $meta_key, $value ) {
	if ( ! in_array( $meta_key, array( '_thumbnail_id', 'aw_source_image_att' ), true ) || ! is_numeric( $value ) ) {
		return;
	}
	$att = (int) $value;
	if ( $att < 1 || 'attachment' === get_post_type( $post_id ) || ! wp_attachment_is_image( $att ) ) {
		return;
	}
	if ( '' !== trim( (string) get_post_meta( $att, 'kits_image_context', true ) ) ) {
		return;
	}
	$context = aw_media_autofill_context( (int) $post_id );
	if ( '' !== $context ) {
		update_post_meta( $att, 'kits_image_context', $context );
	}
}
add_action( 'added_post_meta', 'aw_media_autofill_used', 10, 4 );
add_action( 'updated_post_meta', 'aw_media_autofill_used', 10, 4 );
