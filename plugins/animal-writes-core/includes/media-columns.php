<?php
/**
 * Media Library list columns: name, slug, where each file is used and what to optimise.
 *
 * - Every SEO field of a file is visible: Caption = SEO title, Description = SEO description, Image context, and
 *   Alternative Text (Rank Math's own column), each as its own column.
 * - No Author column (the site has one author).
 * - "Uploaded to" keeps what WordPress prints (parent post, Attach / Detach) and lists every other place the file is used
 *   as extra rows below it: featured image, source image, document preview, logos, social image, a category or tag
 *   photo, the site icon, and the source a favicon or logo belongs to. Sorting still goes through the core parent order.
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
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the IN lists are only %d / %s placeholders built with array_fill
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

add_filter(
	'manage_media_columns',
	function ( $columns ) {
		$ours   = array(
			'aw_file'    => isset( $columns['title'] ) ? $columns['title'] : 'File',
			'aw_slug'    => 'Slug',
			'aw_caption' => 'SEO title',
			'aw_desc'    => 'SEO description',
			'aw_context' => 'Image context',
			'aw_parent'  => 'Uploaded to',
			'aw_opt'     => 'Optimisation',
		);
		$order  = array( 'cb', 'aw_file', 'taxonomy-aw_media_group', 'aw_slug', 'aw_caption', 'aw_desc', 'rank_math_image_alt', 'aw_context', 'aw_parent', 'aw_opt', 'date' );
		$remove = array( 'title', 'author', 'rank_math_image_title', 'imgoptm', 'parent' );
		$out    = array();
		foreach ( $order as $key ) {
			if ( isset( $columns[ $key ] ) ) {
				$out[ $key ] = $columns[ $key ];
			} elseif ( isset( $ours[ $key ] ) ) {
				$out[ $key ] = $ours[ $key ];
			}
		}
		foreach ( $columns as $key => $label ) {
			if ( ! isset( $out[ $key ] ) && ! in_array( $key, $remove, true ) ) {
				$out[ $key ] = $label;
			}
		}
		return $out;
	},
	99
);

add_filter(
	'manage_upload_sortable_columns',
	function ( $columns ) {
		$columns['aw_parent'] = 'parent';
		$columns['aw_file']   = 'title';
		return $columns;
	}
);

// The File column is the primary column, so the row actions (Edit, Delete Permanently, View) print under it.
add_filter(
	'list_table_primary_column',
	function ( $default, $screen_id ) {
		return 'upload' === $screen_id ? 'aw_file' : $default;
	},
	10,
	2
);

add_action(
	'manage_media_custom_column',
	function ( $column, $post_id ) {
		if ( ! in_array( $column, array( 'aw_file', 'aw_slug', 'aw_caption', 'aw_desc', 'aw_context', 'aw_parent', 'aw_opt' ), true ) ) {
			return;
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}
		if ( 'aw_file' === $column ) {
			// WordPress's own File cell (thumbnail, name link, states) without the file-name line, which the Slug column repeats.
			if ( isset( $GLOBALS['wp_list_table'] ) && is_object( $GLOBALS['wp_list_table'] ) && method_exists( $GLOBALS['wp_list_table'], 'column_title' ) ) {
				ob_start();
				$GLOBALS['wp_list_table']->column_title( $post );
				$html = ob_get_clean();
				echo preg_replace( '#<p class="filename">.*?</p>#s', '', $html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core's own escaped markup
			} else {
				echo '<a href="' . esc_url( get_edit_post_link( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a>';
			}
			return;
		}
		if ( 'aw_slug' === $column ) {
			echo esc_html( $post->post_name );
			return;
		}
		if ( 'aw_caption' === $column ) {
			$caption = trim( wp_strip_all_tags( (string) $post->post_excerpt ) );
			echo '' !== $caption ? esc_html( $caption ) : '—';
			return;
		}
		if ( 'aw_desc' === $column ) {
			$desc = trim( wp_strip_all_tags( (string) $post->post_content ) );
			echo '' !== $desc ? esc_html( $desc ) : '—';
			return;
		}
		if ( 'aw_context' === $column ) {
			$ctx = trim( wp_strip_all_tags( (string) get_post_meta( $post_id, 'kits_image_context', true ) ) );
			echo '' !== $ctx ? esc_html( $ctx ) : '—';
			return;
		}
		$page_ids = ! empty( $GLOBALS['wp_query']->posts ) ? wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) : array( $post_id );
		$use      = aw_media_usage( $page_ids )[ (int) $post_id ] ?? array(
			'uses'  => array(),
			'pages' => array(),
			'kw'    => '',
		);
		if ( 'aw_parent' === $column ) {
			if ( isset( $GLOBALS['wp_list_table'] ) && is_object( $GLOBALS['wp_list_table'] ) && method_exists( $GLOBALS['wp_list_table'], 'column_parent' ) ) {
				$GLOBALS['wp_list_table']->column_parent( $post );
			} elseif ( (int) $post->post_parent > 0 ) {
				echo '<a href="' . esc_url( get_edit_post_link( (int) $post->post_parent ) ) . '">' . esc_html( get_the_title( (int) $post->post_parent ) ) . '</a>';
			} else {
				echo '(Unattached)';
			}
			// Every other use, one row each; the parent post is already shown above.
			$uses = $use['uses'];
			if ( (int) $post->post_parent > 0 ) {
				$uses = array_diff_key( $uses, array_flip( preg_grep( '/^p' . (int) $post->post_parent . '(?!\d)/', array_map( 'strval', array_keys( $uses ) ) ) ) );
			}
			if ( $uses ) {
				$shown = array_slice( array_values( $uses ), 0, 5 );
				echo '<br>' . wp_kses( implode( '<br>', $shown ), array( 'a' => array( 'href' => array() ), 'br' => array() ) );
				if ( count( $uses ) > 5 ) {
					echo '<br>+' . (int) ( count( $uses ) - 5 ) . ' more';
				}
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
		$format = strtoupper( pathinfo( (string) get_attached_file( $post_id ), PATHINFO_EXTENSION ) );
		if ( 'JPG' === $format ) {
			$format = 'JPEG';
		}
		if ( '' === $format ) {
			$format = strtoupper( (string) preg_replace( '#^image/#', '', (string) $post->post_mime_type ) );
		}
		$bits[] = $format;
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
		$attached = get_attached_file( $post_id );
		if ( $attached && 'webp' !== strtolower( pathinfo( $attached, PATHINFO_EXTENSION ) ) ) {
			$parts = pathinfo( $attached );
			if ( is_file( $attached . '.webp' ) || is_file( $parts['dirname'] . '/' . $parts['filename'] . '.webp' ) ) {
				echo '<br>WebP copy: yes';
			}
		}
	},
	10,
	2
);
