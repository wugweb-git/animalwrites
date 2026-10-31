<?php
/**
 * AW SEO Plus → FAQ (owner, 31 Oct 2026): the overview only. Questions are written, edited and approved in each item's
 * own FAQ box; this screen lists the items that show an FAQ (one core list table: name, type, questions shown, auto /
 * edited / own; search, sort, filter by type), a "Waiting for approval" view of draft questions with bulk Approve, and
 * the counts by type with Recount.
 *
 * aw_faq_index() walks every item with the box once an hour (transient aw_faq_index): items of a type with automatic
 * questions all, other items only when their box holds questions.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every item that shows an FAQ or has draft questions, and the counts by type. Cached for an hour.
 *
 * @param bool $fresh Count again.
 * @return array [ at, rows[ [ store, id, name, type, shown, auto, edited, own, drafts, draft_q[] ] ], counts[ type => [ store, published, faq, below, drafts ] ] ]
 */
function aw_faq_index( $fresh = false ) {
	$ix = $fresh ? false : get_transient( 'aw_faq_index' );
	if ( is_array( $ix ) && isset( $ix['rows'], $ix['counts'] ) ) {
		return $ix;
	}
	$ix  = array( 'at' => time(), 'rows' => array(), 'counts' => array() );
	$reg = aw_faq_registry();
	$add = function ( $ctx, $name, &$c ) use ( &$ix ) {
		$r      = aw_faq_rows( $ctx, false );
		$shown  = $r['ok'] ? array_values( array_filter( $r['rows'], fn( $x ) => $x['shown'] ) ) : array();
		$drafts = array_values( array_filter( $r['rows'], fn( $x ) => 'own' === $x['origin'] && 'approved' !== $x['status'] ) );
		$c['faq']    += $shown ? 1 : 0;
		$c['below']  += ! $shown && array_filter( $r['rows'], fn( $x ) => $x['shown'] ) ? 1 : 0;
		$c['drafts'] += count( $drafts );
		if ( ! $shown && ! $drafts ) {
			return;
		}
		$by           = array_count_values( wp_list_pluck( $shown, 'origin' ) );
		$ix['rows'][] = array(
			'store'   => $ctx['store'],
			'id'      => $ctx['id'],
			'name'    => $name,
			'type'    => $ctx['type'],
			'shown'   => count( $shown ),
			'auto'    => (int) ( $by['auto'] ?? 0 ),
			'edited'  => (int) ( $by['edited'] ?? 0 ),
			'own'     => (int) ( $by['own'] ?? 0 ),
			'drafts'  => count( $drafts ),
			'draft_q' => array_slice( wp_list_pluck( $drafts, 'q' ), 0, 10 ),
		);
	};
	foreach ( aw_faq_post_types() as $pt ) {
		$c    = array( 'store' => 'post', 'published' => (int) ( wp_count_posts( $pt )->publish ?? 0 ), 'faq' => 0, 'below' => 0, 'drafts' => 0 );
		$args = array( 'post_type' => $pt, 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'no_found_rows' => true, 'orderby' => 'ID', 'order' => 'ASC' );
		if ( empty( $reg[ $pt ] ) ) {
			$args['meta_key'] = 'aw_faq'; // phpcs:ignore WordPress.DB.SlowDBQuery -- hourly count; a type without automatic questions has an FAQ only from its box
		}
		foreach ( array_chunk( get_posts( $args ), 200 ) as $chunk ) {
			foreach ( get_posts( array( 'post_type' => $pt, 'post_status' => 'publish', 'post__in' => $chunk, 'numberposts' => count( $chunk ), 'orderby' => 'post__in', 'no_found_rows' => true ) ) as $p ) { // meta and terms primed per chunk
				$ctx = aw_faq_context( $p );
				if ( $ctx ) {
					$add( $ctx, html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ), $c );
				}
			}
		}
		$ix['counts'][ $pt ] = $c;
	}
	foreach ( aw_faq_taxonomies() as $tax ) {
		$c     = array( 'store' => 'term', 'published' => (int) wp_count_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) ), 'faq' => 0, 'below' => 0, 'drafts' => 0 );
		$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false, 'meta_key' => 'aw_faq' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery -- hourly count
		foreach ( is_wp_error( $terms ) ? array() : $terms as $t ) {
			$ctx = aw_faq_context( $t );
			if ( $ctx ) {
				$add( $ctx, html_entity_decode( $t->name, ENT_QUOTES, 'UTF-8' ), $c );
			}
		}
		$ix['counts'][ $tax ] = $c;
	}
	set_transient( 'aw_faq_index', $ix, HOUR_IN_SECONDS );
	delete_option( 'aw_faq_experiment' ); // the holdout experiment is gone (owner, 31 Oct 2026); so is its old count
	delete_transient( 'aw_faq_counts' );
	return $ix;
}

/** A type's name for the screen: the post type's or taxonomy's singular label. */
function aw_faq_type_label( $type ) {
	$o = post_type_exists( $type ) ? get_post_type_object( $type ) : get_taxonomy( $type );
	return $o ? (string) $o->labels->singular_name : (string) $type;
}

/** The edit screen of an item, at its FAQ box. */
function aw_faq_edit_url( $store, $id ) {
	$url = 'term' === $store ? (string) get_edit_term_link( (int) $id ) : (string) get_edit_post_link( (int) $id, 'raw' );
	return '' !== $url ? $url . '#aw-faq' : '';
}

/** Whether the current user may edit an item's FAQ box. */
function aw_faq_can_edit( $store, $id ) {
	return 'term' === $store ? current_user_can( 'edit_term', (int) $id ) : current_user_can( 'edit_post', (int) $id );
}

add_action(
	'admin_menu',
	function () {
		$hook = add_submenu_page( AW_SEO_MENU, 'FAQ', 'FAQ', 'manage_categories', 'aw-faq', 'aw_faq_admin_page' ); // AW SEO Plus → FAQ (overview.php has the menu)
		if ( $hook ) {
			add_action( 'load-' . $hook, 'aw_faq_admin_load' );
		}
	}
);

/** Before the screen: bulk Approve and Recount, then back to the list. */
function aw_faq_admin_load() {
	$action = sanitize_key( wp_unslash( $_GET['action'] ?? '-1' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- checked below
	if ( '-1' === $action || '' === $action ) {
		$action = sanitize_key( wp_unslash( $_GET['action2'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- checked below
	}
	if ( 'approve' === $action ) {
		check_admin_referer( 'bulk-aw-faq-items' );
		$done = 0;
		foreach ( array_map( 'sanitize_text_field', (array) wp_unslash( $_GET['item'] ?? array() ) ) as $pick ) {
			if ( ! preg_match( '/^(post|term):(\d+)$/', $pick, $m ) || ! aw_faq_can_edit( $m[1], $m[2] ) ) {
				continue;
			}
			$items = aw_faq_items( $m[1], (int) $m[2] );
			foreach ( $items as $i => $it ) {
				if ( '' === ( $it['auto'] ?? '' ) && 'approved' !== $it['status'] ) {
					$items[ $i ]['status']   = 'approved';
					$items[ $i ]['reviewed'] = wp_date( 'Y-m-d' );
					++$done;
				}
			}
			aw_faq_items_save( $m[1], (int) $m[2], $items );
		}
		aw_faq_index( true );
		wp_safe_redirect( add_query_arg( array( 'page' => 'aw-faq', 'view' => 'waiting', 'approved' => $done ), admin_url( 'admin.php' ) ) );
		exit;
	}
	if ( isset( $_GET['recount'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- checked below
		check_admin_referer( 'aw_faq_recount' );
		aw_faq_index( true );
		wp_safe_redirect( add_query_arg( array( 'page' => 'aw-faq', 'counted' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}
}

/** AW SEO Plus → FAQ. */
function aw_faq_admin_page() {
	if ( ! class_exists( 'WP_List_Table' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	}
	require_once __DIR__ . '/class-aw-faq-list-table.php';
	$ix    = aw_faq_index();
	$table = new AW_FAQ_List_Table( $ix['rows'] );
	$table->prepare_items();
	echo '<div class="wrap"><h1 class="wp-heading-inline">FAQ</h1><hr class="wp-header-end">';
	if ( isset( $_GET['approved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- a notice after the redirect
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( '%d questions approved.', (int) $_GET['approved'] ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification
	}
	if ( isset( $_GET['counted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- a notice after the redirect
		echo '<div class="notice notice-success is-dismissible"><p>Counted again.</p></div>';
	}
	echo '<p>Every page’s questions are in its own FAQ box: open it with Edit. A page shows its FAQ when its box has enough questions to show. This list: the pages that show one, and the draft questions waiting for approval.</p>';
	$table->views();
	echo '<form method="get"><input type="hidden" name="page" value="aw-faq">';
	if ( 'waiting' === $table->view ) {
		echo '<input type="hidden" name="view" value="waiting">';
	}
	$table->search_box( 'Search', 'aw-faq' );
	$table->display();
	echo '</form>';

	echo '<h2>Counts</h2>';
	$cells = array();
	foreach ( $ix['counts'] as $type => $c ) {
		if ( ! $c['published'] && ! $c['faq'] ) {
			continue;
		}
		$cells[] = array(
			'type'      => esc_html( aw_faq_type_label( $type ) ) . ( 'term' === $c['store'] ? ' <span class="description">(terms)</span>' : '' ),
			'published' => esc_html( number_format_i18n( $c['published'] ) ),
			'faq'       => esc_html( number_format_i18n( $c['faq'] ) ),
			'below'     => esc_html( number_format_i18n( $c['below'] ) ),
			'drafts'    => esc_html( number_format_i18n( $c['drafts'] ) ),
		);
	}
	aw_seo_extras_simple_table( 'faq-counts', array( 'type' => 'Type', 'published' => 'Published', 'faq' => 'Show an FAQ', 'below' => 'Questions, but fewer than the minimum (no FAQ)', 'drafts' => 'Draft questions waiting' ), $cells );
	echo '<p>' . esc_html( sprintf( 'Counted %s.', wp_date( 'j M Y, H:i', (int) $ix['at'] ) ) ) . ' <a href="' . esc_url( wp_nonce_url( add_query_arg( array( 'page' => 'aw-faq', 'recount' => 1 ), admin_url( 'admin.php' ) ), 'aw_faq_recount' ) ) . '">Recount</a></p></div>';
}
