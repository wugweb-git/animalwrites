<?php
/**
 * RankWeb PRO, phase E: list-screen SEO columns, filters, Quick Edit and Bulk Edit (docs/rankweb-pro-audit.md, E).
 *
 * On the post-list screen of each configured type: a "SEO" column (title, description and focus keyword at a glance), a
 * filter (missing title, missing description, missing focus keyword, noindex, or no internal links in), Quick Edit for
 * the title, description and focus keyword, and Bulk Edit for the focus keyword, the link keywords and the robots index
 * rule. The CSV screen is in bulk-csv.php (the same module). Rank Math's own fields (rank_math_title,
 * rank_math_description, rank_math_focus_keyword, rank_math_robots) are read and written; the link keywords are
 * RankWeb's _aw_link_keywords.
 *
 * Module "bulk" (RankWeb → Settings → Modules). No scheduled job.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The post types that get the list-screen SEO tools (the setting; empty: none). */
function aw_bulk_types(): array {
	return array_values( array_filter( array_map( 'sanitize_key', (array) AW_BULK_TYPES ), 'post_type_exists' ) );
}

/** An item's robots flags (Rank Math's rank_math_robots, an array like array( 'noindex' )). */
function aw_bulk_robots( int $id ): array {
	$r = get_post_meta( $id, 'rank_math_robots', true );
	return is_array( $r ) ? array_values( array_filter( array_map( 'sanitize_key', $r ) ) ) : array();
}

/** An item's link keywords as a display string (internal links module). */
function aw_bulk_link_keywords( int $id ): array {
	return function_exists( 'aw_link_keywords' ) ? aw_link_keywords( $id ) : (array) get_post_meta( $id, '_aw_link_keywords', true );
}

/* ---------- The column ---------- */

add_action(
	'admin_init',
	function () {
		foreach ( aw_bulk_types() as $type ) {
			add_filter( "manage_{$type}_posts_columns", 'aw_bulk_column' );
			add_action( "manage_{$type}_posts_custom_column", 'aw_bulk_column_cell', 10, 2 );
		}
	}
);

/**
 * Add the SEO column after the title.
 *
 * @param array $cols Columns.
 */
function aw_bulk_column( array $cols ): array {
	$out = array();
	foreach ( $cols as $key => $label ) {
		$out[ $key ] = $label;
		if ( 'title' === $key ) {
			$out['aw_seo'] = __( 'SEO', 'aw-seo-extras' );
		}
	}
	if ( ! isset( $out['aw_seo'] ) ) {
		$out['aw_seo'] = __( 'SEO', 'aw-seo-extras' );
	}
	return $out;
}

/**
 * The SEO column cell, with the raw values in hidden spans for Quick Edit to read.
 *
 * @param string $col Column key.
 * @param int    $id  Item ID.
 */
function aw_bulk_column_cell( $col, $id ): void {
	if ( 'aw_seo' !== $col ) {
		return;
	}
	$id    = (int) $id;
	$title = (string) get_post_meta( $id, 'rank_math_title', true );
	$desc  = (string) get_post_meta( $id, 'rank_math_description', true );
	$focus = (string) get_post_meta( $id, 'rank_math_focus_keyword', true );
	$none  = '<span style="color:#b32d2e">' . esc_html__( 'missing', 'aw-seo-extras' ) . '</span>';
	echo '<div class="aw-seo-col">';
	echo '<div><strong>' . esc_html__( 'Title', 'aw-seo-extras' ) . ':</strong> ' . ( '' !== $title ? esc_html( $title ) : $none ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $none is a fixed escaped string
	echo '<div><strong>' . esc_html__( 'Desc', 'aw-seo-extras' ) . ':</strong> ' . ( '' !== $desc ? esc_html( wp_html_excerpt( $desc, 60, '…' ) ) : $none ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $none is a fixed escaped string
	echo '<div><strong>' . esc_html__( 'Focus', 'aw-seo-extras' ) . ':</strong> ' . ( '' !== $focus ? esc_html( $focus ) : $none ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $none is a fixed escaped string
	if ( in_array( 'noindex', aw_bulk_robots( $id ), true ) ) {
		echo '<div><em>' . esc_html__( 'noindex', 'aw-seo-extras' ) . '</em></div>';
	}
	echo '<span class="hidden aw-seo-title">' . esc_html( $title ) . '</span>';
	echo '<span class="hidden aw-seo-desc">' . esc_html( $desc ) . '</span>';
	echo '<span class="hidden aw-seo-focus">' . esc_html( $focus ) . '</span>';
	echo '</div>';
}

/* ---------- The filter ---------- */

add_action(
	'restrict_manage_posts',
	function ( $post_type ) {
		if ( ! in_array( (string) $post_type, aw_bulk_types(), true ) ) {
			return;
		}
		$current = isset( $_GET['aw_seo_filter'] ) ? sanitize_key( wp_unslash( $_GET['aw_seo_filter'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- a read-only list filter
		$options = array(
			''         => __( 'SEO: any', 'aw-seo-extras' ),
			'no_title' => __( 'No SEO title', 'aw-seo-extras' ),
			'no_desc'  => __( 'No description', 'aw-seo-extras' ),
			'no_focus' => __( 'No focus keyword', 'aw-seo-extras' ),
			'noindex'  => __( 'Noindex', 'aw-seo-extras' ),
			'orphan'   => __( 'No internal links in', 'aw-seo-extras' ),
		);
		echo '<select name="aw_seo_filter">';
		foreach ( $options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $current, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}
);

add_action(
	'pre_get_posts',
	function ( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit' !== $screen->base || ! in_array( (string) $screen->post_type, aw_bulk_types(), true ) ) {
			return;
		}
		$filter = isset( $_GET['aw_seo_filter'] ) ? sanitize_key( wp_unslash( $_GET['aw_seo_filter'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- a read-only list filter
		if ( '' === $filter ) {
			return;
		}
		if ( 'orphan' === $filter ) {
			$orphans = aw_bulk_orphan_ids( (string) $screen->post_type );
			$query->set( 'post__in', $orphans ? $orphans : array( 0 ) );
			return;
		}
		$map = array(
			'no_title' => 'rank_math_title',
			'no_desc'  => 'rank_math_description',
			'no_focus' => 'rank_math_focus_keyword',
		);
		if ( isset( $map[ $filter ] ) ) {
			$query->set(
				'meta_query', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- an admin list filter the editor chose
				array(
					'relation' => 'OR',
					array(
						'key'     => $map[ $filter ],
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => $map[ $filter ],
						'value'   => '',
						'compare' => '=',
					),
				)
			);
		} elseif ( 'noindex' === $filter ) {
			$query->set(
				'meta_query', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- an admin list filter the editor chose
				array(
					array(
						'key'     => 'rank_math_robots',
						'value'   => 'noindex',
						'compare' => 'LIKE',
					),
				)
			);
		}
	}
);

/**
 * Item IDs of a type the audit marked as orphans (no internal links in).
 *
 * @param string $type Post type.
 * @return int[]
 */
function aw_bulk_orphan_ids( string $type ): array {
	global $wpdb;
	if ( ! function_exists( 'aw_link_table' ) || get_option( 'aw_link_audit_db' ) !== '1' ) {
		return array();
	}
	$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT a.post_id FROM %i a JOIN %i p ON p.ID = a.post_id WHERE p.post_type = %s AND a.flags LIKE %s', aw_link_table( 'audit' ), $wpdb->posts, $type, '%,orphan,%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- RankWeb's own table, an admin list filter
	return array_map( 'intval', (array) $ids );
}

/* ---------- Quick Edit and Bulk Edit ---------- */

add_action(
	'quick_edit_custom_box',
	function ( $col, $post_type ) {
		if ( 'aw_seo' !== $col || ! in_array( (string) $post_type, aw_bulk_types(), true ) ) {
			return;
		}
		wp_nonce_field( 'aw_bulk_quick', 'aw_bulk_quick_nonce' );
		echo '<fieldset class="inline-edit-col-right"><div class="inline-edit-col"><div class="inline-edit-group wp-clearfix">';
		echo '<input type="hidden" name="aw_seo_loaded" value="0">'; // the script sets 1 once it has filled the fields from the row
		echo '<label class="inline-edit-group"><span class="title">' . esc_html__( 'SEO title', 'aw-seo-extras' ) . '</span><input type="text" name="aw_seo_title" class="aw-seo-field" value=""></label>';
		echo '<label class="inline-edit-group"><span class="title">' . esc_html__( 'Description', 'aw-seo-extras' ) . '</span><textarea name="aw_seo_desc" class="aw-seo-field" rows="1"></textarea></label>';
		echo '<label class="inline-edit-group"><span class="title">' . esc_html__( 'Focus keyword', 'aw-seo-extras' ) . '</span><input type="text" name="aw_seo_focus" class="aw-seo-field" value=""></label>';
		echo '</div></div></fieldset>';
	},
	10,
	2
);

add_action(
	'bulk_edit_custom_box',
	function ( $col, $post_type ) {
		if ( 'aw_seo' !== $col || ! in_array( (string) $post_type, aw_bulk_types(), true ) ) {
			return;
		}
		wp_nonce_field( 'aw_bulk_quick', 'aw_bulk_quick_nonce' );
		echo '<fieldset class="inline-edit-col-right"><div class="inline-edit-col">';
		echo '<label class="inline-edit-group"><span class="title">' . esc_html__( 'Focus keyword', 'aw-seo-extras' ) . '</span><input type="text" name="aw_seo_focus_bulk" value="" placeholder="' . esc_attr__( 'leave blank to keep', 'aw-seo-extras' ) . '"></label>';
		echo '<label class="inline-edit-group"><span class="title">' . esc_html__( 'Add link keyword', 'aw-seo-extras' ) . '</span><input type="text" name="aw_seo_linkkw_bulk" value="" placeholder="' . esc_attr__( 'added to each item', 'aw-seo-extras' ) . '"></label>';
		echo '<label class="inline-edit-group"><span class="title">' . esc_html__( 'Search index', 'aw-seo-extras' ) . '</span><select name="aw_seo_robots_bulk"><option value="">' . esc_html__( '— No change —', 'aw-seo-extras' ) . '</option><option value="index">' . esc_html__( 'Index', 'aw-seo-extras' ) . '</option><option value="noindex">' . esc_html__( 'Noindex', 'aw-seo-extras' ) . '</option></select></label>';
		echo '</div></fieldset>';
	},
	10,
	2
);

// Quick Edit (one item) and Bulk Edit (many) both reach save_post through wp_update_post. The nonce covers both; Bulk
// Edit also passes WordPress's own bulk nonce, checked by the core handler before this runs.
add_action(
	'save_post',
	function ( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! in_array( $post->post_type, aw_bulk_types(), true ) ) {
			return;
		}
		if ( ! isset( $_REQUEST['aw_bulk_quick_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['aw_bulk_quick_nonce'] ) ), 'aw_bulk_quick' ) || ! current_user_can( 'edit_post', (int) $post_id ) ) {
			return;
		}
		$bulk = isset( $_REQUEST['bulk_edit'] );
		// Quick Edit: the three text fields, always written (an empty value clears).
		if ( ! $bulk ) {
			// Only when the script filled the fields from the row: with the SEO column hidden (Screen Options) they are
			// empty, and saving would clear the title, description and focus keyword.
			if ( '1' !== ( isset( $_REQUEST['aw_seo_loaded'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['aw_seo_loaded'] ) ) : '' ) ) {
				return;
			}
			if ( isset( $_REQUEST['aw_seo_title'] ) ) {
				update_post_meta( $post_id, 'rank_math_title', sanitize_text_field( wp_unslash( $_REQUEST['aw_seo_title'] ) ) );
			}
			if ( isset( $_REQUEST['aw_seo_desc'] ) ) {
				update_post_meta( $post_id, 'rank_math_description', sanitize_textarea_field( wp_unslash( $_REQUEST['aw_seo_desc'] ) ) );
			}
			if ( isset( $_REQUEST['aw_seo_focus'] ) ) {
				update_post_meta( $post_id, 'rank_math_focus_keyword', sanitize_text_field( wp_unslash( $_REQUEST['aw_seo_focus'] ) ) );
			}
			return;
		}
		// Bulk Edit: only the fields the editor filled in (blank = keep).
		$focus = isset( $_REQUEST['aw_seo_focus_bulk'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['aw_seo_focus_bulk'] ) ) : '';
		if ( '' !== $focus ) {
			update_post_meta( $post_id, 'rank_math_focus_keyword', $focus );
		}
		$add = isset( $_REQUEST['aw_seo_linkkw_bulk'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['aw_seo_linkkw_bulk'] ) ) : '';
		if ( '' !== $add && function_exists( 'aw_link_keywords_clean' ) ) {
			$list = aw_link_keywords_clean( array_merge( aw_bulk_link_keywords( (int) $post_id ), array( $add ) ) );
			update_post_meta( $post_id, '_aw_link_keywords', $list );
		}
		$robots = isset( $_REQUEST['aw_seo_robots_bulk'] ) ? sanitize_key( wp_unslash( $_REQUEST['aw_seo_robots_bulk'] ) ) : '';
		if ( 'index' === $robots || 'noindex' === $robots ) {
			$flags   = array_values( array_diff( aw_bulk_robots( (int) $post_id ), array( 'index', 'noindex' ) ) );
			$flags[] = $robots;
			update_post_meta( $post_id, 'rank_math_robots', $flags );
		}
	},
	10,
	2
);

// Quick Edit JS: copy the hidden column values into the Quick Edit fields when the row opens.
add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( 'edit.php' !== $hook || ! $screen || ! in_array( (string) $screen->post_type, aw_bulk_types(), true ) ) {
			return;
		}
		$js = <<<'JS'
( function() {
	if ( ! window.inlineEditPost || ! window.jQuery ) { return; }
	var $ = window.jQuery, orig = inlineEditPost.edit;
	inlineEditPost.edit = function( id ) {
		orig.apply( this, arguments );
		var postId = typeof id === 'object' ? parseInt( this.getId( id ), 10 ) : id;
		if ( ! postId ) { return; }
		var row = $( '#post-' + postId );
		var ed  = $( '#edit-' + postId );
		ed.find( 'input[name="aw_seo_title"]' ).val( row.find( '.aw-seo-title' ).text() );
		ed.find( 'textarea[name="aw_seo_desc"]' ).val( row.find( '.aw-seo-desc' ).text() );
		ed.find( 'input[name="aw_seo_focus"]' ).val( row.find( '.aw-seo-focus' ).text() );
		ed.find( 'input[name="aw_seo_loaded"]' ).val( row.find( '.aw-seo-title' ).length ? '1' : '0' );
	};
} )();
JS;
		wp_register_script( 'aw-bulk-inline', '', array( 'inline-edit-post', 'jquery' ), '2.1.0', true );
		wp_enqueue_script( 'aw-bulk-inline' );
		wp_add_inline_script( 'aw-bulk-inline', $js );
	}
);
