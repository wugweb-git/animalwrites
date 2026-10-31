<?php
/**
 * Media Library list columns (owner, 31 Oct 2026): name, slug, where each file is used, an SEO score and what to optimise.
 *
 * - No Author column (the site has one author).
 * - "Uploaded to" stays as WordPress has it (with Attach / Detach); "Also used in" sits beside it and lists every other
 *   place the file is used: featured image, source image, document preview, logos, social image, a category or tag photo,
 *   the site icon, and the source a favicon or logo belongs to. A file used more than once shows all of them.
 * - SEO score (0-100) is worked out per file from its own fields (the fields already on the attachment: Alternative Text,
 *   Caption = SEO title, Description = SEO description, Image context) plus the Rank Math score of the pages that use it.
 *   Rank Math does not score attachments, so the page score is shown next to it, not mixed into it.
 * - Optimisation: size, format and what is missing (no resized copies, over 300 KB, under 64 px).
 * Core list-table columns only: no custom CSS, no scripts. Read only; nothing is saved by this file.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Post meta keys that hold an attachment ID, with the words shown for each. */
function aw_media_post_meta_uses() {
	return array(
		'_thumbnail_id'               => 'Featured image',
		'aw_source_image_att'         => 'Source image',
		'aw_doc_preview'              => 'Document preview',
		'aw_file_id'                  => 'Hosted file',
		'aw_press_logo'               => 'Press logo',
		'aw_org_logo'                 => 'Organisation logo',
		'aw_c_logo'                   => 'Campaign logo',
		'aw_claim_logo'               => 'Claim logo',
		'aw_banner_image'             => 'Banner',
		'rank_math_facebook_image_id' => 'Social image',
		'kits_og_image'               => 'Share image',
	);
}

/** Term meta keys that hold an attachment ID, with the words shown for each. */
function aw_media_term_meta_uses() {
	return array(
		'aw_category_card'            => 'Category card',
		'aw_category_image'           => 'Category photo',
		'aw_category_image_id'        => 'Category photo',
		'aw_tag_icon'                 => 'Tag icon',
		'rank_math_facebook_image_id' => 'Social image',
	);
}

/**
 * Where the files on the current Media Library page are used (one batch of queries per page, cached for the request).
 *
 * @param int[] $ids Attachment IDs.
 * @return array[] Per attachment ID: array( 'uses' => lines (already escaped), 'pages' => post IDs, 'kw' => focus keyword ).
 */
function aw_media_usage( $ids ) {
	global $wpdb;
	static $cache = array();
	$ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $ids ) ) ) );
	$new = array_diff( $ids, array_keys( $cache ) );
	if ( $new ) {
		$new   = array_values( $new );
		$in    = implode( ',', array_fill( 0, count( $new ), '%d' ) );
		$found = array();
		foreach ( $new as $id ) {
			$found[ $id ] = array(
				'uses'  => array(),
				'pages' => array(),
				'kw'    => '',
			);
		}
		$labels = aw_media_post_meta_uses();
		$keys   = array_keys( $labels );
		$kin    = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT m.meta_value AS att, m.meta_key AS k, p.ID AS pid, p.post_title AS title FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key IN ($kin) AND m.meta_value IN ($in) AND p.post_type <> 'attachment' LIMIT 2000", array_merge( $keys, $new ) ) );
		foreach ( (array) $rows as $r ) {
			$found[ (int) $r->att ]['uses'][ 'p' . $r->pid . $r->k ] = esc_html( $labels[ $r->k ] ) . ': <a href="' . esc_url( get_edit_post_link( (int) $r->pid ) ) . '">' . esc_html( wp_html_excerpt( html_entity_decode( $r->title, ENT_QUOTES, 'UTF-8' ), 40, '…' ) ) . '</a>';
			$found[ (int) $r->att ]['pages'][]                       = (int) $r->pid;
		}
		$tlabels = aw_media_term_meta_uses();
		$tkeys   = array_keys( $tlabels );
		$tin     = implode( ',', array_fill( 0, count( $tkeys ), '%s' ) );
		$trows   = $wpdb->get_results( $wpdb->prepare( "SELECT m.meta_value AS att, m.meta_key AS k, t.term_id AS tid, t.name AS name, x.taxonomy AS tax FROM {$wpdb->termmeta} m JOIN {$wpdb->terms} t ON t.term_id = m.term_id JOIN {$wpdb->term_taxonomy} x ON x.term_id = t.term_id WHERE m.meta_key IN ($tin) AND m.meta_value IN ($in) LIMIT 500", array_merge( $tkeys, $new ) ) );
		// phpcs:enable
		foreach ( (array) $trows as $r ) {
			$link = get_edit_term_link( (int) $r->tid, $r->tax );
			$name = esc_html( $r->name );
			$found[ (int) $r->att ]['uses'][ 't' . $r->tid . $r->k ] = esc_html( $tlabels[ $r->k ] ) . ': ' . ( $link ? '<a href="' . esc_url( $link ) . '">' . $name . '</a>' : $name );
		}
		foreach ( array( 'site_icon' => 'Site icon', 'site_logo' => 'Site logo' ) as $option => $label ) {
			$att = (int) get_option( $option );
			if ( isset( $found[ $att ] ) ) {
				$found[ $att ]['uses'][ 'o' . $option ] = esc_html( $label );
			}
		}
		// A source's favicon or logo is named after the host ("Favicon: example.com", "example.com logo").
		foreach ( $new as $id ) {
			$title = (string) get_post_field( 'post_title', $id );
			if ( preg_match( '/^(?:Favicon|Logo):\s*(\S+)$/', $title, $m ) || preg_match( '/^(\S+) logo$/', $title, $m ) ) {
				$found[ $id ]['uses']['s'] = 'Source: ' . esc_html( $m[1] );
			}
		}
		$all_pages = array();
		foreach ( $found as $f ) {
			$all_pages = array_merge( $all_pages, $f['pages'] );
		}
		$all_pages = array_values( array_unique( $all_pages ) );
		if ( $all_pages ) {
			update_meta_cache( 'post', $all_pages );
		}
		foreach ( $found as $id => $f ) {
			$found[ $id ]['pages'] = array_values( array_unique( $f['pages'] ) );
			foreach ( $found[ $id ]['pages'] as $pid ) {
				$kw = (string) get_post_meta( $pid, 'rank_math_focus_keyword', true );
				if ( '' !== $kw ) {
					$found[ $id ]['kw'] = trim( explode( ',', $kw )[0] );
					break;
				}
			}
			$cache[ $id ] = $found[ $id ];
		}
	}
	return array_intersect_key( $cache, array_flip( $ids ) );
}

/**
 * The SEO score of one file, and what to fix.
 *
 * @param WP_Post $post Attachment.
 * @param array   $use  Row from aw_media_usage().
 * @return array array( score 0-100, issues[] ).
 */
function aw_media_seo( $post, $use ) {
	$is_img = wp_attachment_is_image( $post );
	$meta   = $is_img ? wp_get_attachment_metadata( $post->ID ) : array();
	$bytes  = ! empty( $meta['filesize'] ) ? (int) $meta['filesize'] : 0;
	$alt    = trim( (string) get_post_meta( $post->ID, '_wp_attachment_image_alt', true ) );
	$cap    = trim( (string) $post->post_excerpt );
	$desc   = trim( wp_strip_all_tags( (string) $post->post_content ) );
	$ctx    = trim( (string) get_post_meta( $post->ID, 'kits_image_context', true ) );
	$kw     = strtolower( (string) $use['kw'] );
	$score  = 0;
	$issues = array();
	$title  = html_entity_decode( (string) $post->post_title, ENT_QUOTES, 'UTF-8' );
	if ( '' !== $title && mb_strlen( $title ) <= 60 ) {
		$score += 10;
	} else {
		$issues[] = 'name over 60 characters';
	}
	if ( $is_img ) {
		if ( '' === $alt ) {
			$issues[] = 'no alt text';
		} elseif ( mb_strlen( $alt ) > 125 ) {
			$score   += 8;
			$issues[] = 'alt over 125 characters';
		} else {
			$score += 15;
		}
	} else {
		$score += 15; // Alt text does not apply to documents.
	}
	if ( '' !== $cap ) {
		$score += 15;
	} else {
		$issues[] = 'no caption (SEO title)';
	}
	$dl = mb_strlen( $desc );
	if ( $dl >= 100 && $dl <= 155 ) {
		$score += 15;
	} elseif ( $dl > 0 ) {
		$score   += 8;
		$issues[] = 'description not 100-155 characters';
	} else {
		$issues[] = 'no description (SEO description)';
	}
	if ( '' !== $ctx ) {
		$score += 10;
	} else {
		$issues[] = 'no image context';
	}
	if ( '' === $kw || false !== stripos( $cap . ' ' . $desc . ' ' . $alt, $kw ) ) {
		$score += 10; // No focus keyword on the pages that use it: nothing to match.
	} else {
		$issues[] = 'focus keyword "' . $kw . '" not used';
	}
	if ( $is_img ) {
		if ( $bytes > 300000 ) {
			$issues[] = 'over 300 KB';
		} elseif ( empty( $meta['sizes'] ) ) {
			$score   += 7;
			$issues[] = 'no resized copies';
		} else {
			$score += 15;
		}
	} else {
		$score += 15;
	}
	if ( $use['uses'] || (int) $post->post_parent ) {
		$score += 10;
	} else {
		$issues[] = 'not used anywhere';
	}
	return array( $score, $issues );
}

add_filter(
	'manage_media_columns',
	function ( $columns ) {
		unset( $columns['author'] );
		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out['aw_slug'] = 'Slug';
			}
			if ( 'parent' === $key ) {
				$out['aw_used'] = 'Also used in';
			}
		}
		$out['aw_seo'] = 'SEO score';
		$out['aw_opt'] = 'Optimisation';
		return $out;
	}
);

add_action(
	'manage_media_custom_column',
	function ( $column, $post_id ) {
		if ( ! in_array( $column, array( 'aw_slug', 'aw_used', 'aw_seo', 'aw_opt' ), true ) ) {
			return;
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}
		if ( 'aw_slug' === $column ) {
			echo esc_html( $post->post_name );
			return;
		}
		$page_ids = ! empty( $GLOBALS['wp_query']->posts ) ? wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) : array( $post_id );
		$use      = aw_media_usage( $page_ids )[ (int) $post_id ] ?? array(
			'uses'  => array(),
			'pages' => array(),
			'kw'    => '',
		);
		if ( 'aw_used' === $column ) {
			if ( ! $use['uses'] ) {
				echo '—';
				return;
			}
			$shown = array_slice( array_values( $use['uses'] ), 0, 5 );
			echo wp_kses( implode( '<br>', $shown ), array( 'a' => array( 'href' => array() ), 'br' => array() ) );
			if ( count( $use['uses'] ) > 5 ) {
				echo '<br>+' . (int) ( count( $use['uses'] ) - 5 ) . ' more';
			}
			return;
		}
		if ( 'aw_seo' === $column ) {
			list( $score, $issues ) = aw_media_seo( $post, $use );
			echo '<strong>' . (int) $score . '/100</strong>';
			$best = null;
			foreach ( $use['pages'] as $pid ) {
				$s = (int) get_post_meta( $pid, 'rank_math_seo_score', true );
				if ( $s > 0 && ( null === $best || $s < $best ) ) {
					$best = $s;
				}
			}
			if ( null !== $best ) {
				echo '<br>Page score: ' . (int) $best;
			}
			if ( $issues ) {
				echo '<br>' . esc_html( implode( '; ', array_slice( $issues, 0, 3 ) ) );
			}
			return;
		}
		// Optimisation.
		if ( ! wp_attachment_is_image( $post ) ) {
			echo '—';
			return;
		}
		$meta = wp_get_attachment_metadata( $post_id );
		$bits = array();
		if ( ! empty( $meta['width'] ) ) {
			$bits[] = (int) $meta['width'] . '×' . (int) $meta['height'];
		}
		if ( ! empty( $meta['filesize'] ) ) {
			$bits[] = size_format( (int) $meta['filesize'] );
		}
		$bits[] = strtoupper( (string) preg_replace( '#^image/#', '', (string) $post->post_mime_type ) );
		echo esc_html( implode( ' · ', $bits ) );
		$flags = array();
		if ( ! empty( $meta['filesize'] ) && (int) $meta['filesize'] > 1000000 ) {
			$flags[] = 'over 1 MB';
		} elseif ( ! empty( $meta['filesize'] ) && (int) $meta['filesize'] > 300000 ) {
			$flags[] = 'over 300 KB';
		}
		if ( empty( $meta['sizes'] ) ) {
			$flags[] = 'no resized copies';
		}
		if ( ! empty( $meta['width'] ) && (int) $meta['width'] < 64 ) {
			$flags[] = 'under 64 px';
		}
		if ( $flags ) {
			echo '<br>' . esc_html( implode( '; ', $flags ) );
		}
	},
	10,
	2
);
