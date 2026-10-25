<?php
/**
 * Sourcing → To review as a core list table (owner, 24 Oct 2026): views by type, search, sortable columns, pagination with
 * a screen option, row actions (Edit, Source, Publish, Reject) and bulk actions (Publish, Reject) plus "Publish all N".
 * The form keeps its aw_review nonce, so the table prints none of its own. Loaded only on that screen.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Items waiting for a person. */
class AW_Sourcing_Review_Table extends WP_List_Table {

	/** @var string One type, or '' for all. */
	private $type;

	public function __construct() {
		parent::__construct( array( 'singular' => 'review', 'plural' => 'reviews', 'ajax' => false ) );
		$t          = sanitize_key( $_GET['type'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		$this->type = isset( AW_REVIEW_TYPES[ $t ] ) ? $t : '';
	}

	private function base() {
		return admin_url( 'admin.php?page=aw-review' );
	}

	protected function get_views() {
		$out = array( 'all' => '<a href="' . esc_url( $this->base() ) . '"' . ( '' === $this->type ? ' class="current" aria-current="page"' : '' ) . '>All <span class="count">(' . esc_html( number_format_i18n( count( aw_review_items() ) ) ) . ')</span></a>' );
		foreach ( AW_REVIEW_TYPES as $t => $label ) {
			$c = count( aw_review_items( $t ) );
			if ( $c ) {
				$out[ $t ] = '<a href="' . esc_url( add_query_arg( 'type', $t, $this->base() ) ) . '"' . ( $t === $this->type ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $label ) . ' <span class="count">(' . esc_html( number_format_i18n( $c ) ) . ')</span></a>';
			}
		}
		return $out;
	}

	public function get_columns() {
		return array(
			'cb'       => '<input type="checkbox" />',
			'title'    => 'Item',
			'type'     => 'Type',
			'source'   => 'Source',
			'why'      => 'Why it is waiting',
			'received' => 'Received',
		);
	}

	protected function get_sortable_columns() {
		return array( 'title' => array( 'title', false ), 'type' => array( 'type', false ), 'received' => array( 'received', true ) );
	}

	protected function get_primary_column_name() {
		return 'title';
	}

	protected function get_bulk_actions() {
		return array( 'publish' => 'Publish', 'reject' => 'Reject (move to Drafts)' );
	}

	/** The handler reads its two menus as do / do2 (the core name "action" would be admin-post.php's own). */
	protected function bulk_actions( $which = '' ) {
		$name = 'top' === $which ? 'do' : 'do2';
		echo '<label for="aw-review-' . esc_attr( $name ) . '" class="screen-reader-text">Action for the ticked items</label>';
		echo '<select name="' . esc_attr( $name ) . '" id="aw-review-' . esc_attr( $name ) . '"><option value="">Bulk actions</option>';
		foreach ( $this->get_bulk_actions() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
		}
		echo '</select>';
		submit_button( 'Apply', 'action', '', false, array( 'id' => 'aw-review-apply-' . $which ) );
	}

	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which || ! $this->has_items() ) {
			return;
		}
		$n   = count( aw_review_items( $this->type ) ); // the whole view, as the handler publishes it (not only a search)
		$all = wp_nonce_url( admin_url( 'admin-post.php?action=aw_review&do=publish&all=1&type=' . $this->type ), 'aw_review' );
		echo '<div class="alignleft actions"><a class="button" href="' . esc_url( $all ) . '" onclick="return confirm(' . esc_attr( wp_json_encode( sprintf( 'Publish all %d items in this view?', $n ) ) ) . ')">' . esc_html( sprintf( 'Publish all %d', $n ) ) . '</a></div>';
	}

	/** As core, without the table's own nonce: the form carries aw_review. */
	protected function display_tablenav( $which ) {
		echo '<div class="tablenav ' . esc_attr( $which ) . '">';
		if ( $this->has_items() ) {
			echo '<div class="alignleft actions bulkactions">';
			$this->bulk_actions( $which );
			echo '</div>';
		}
		$this->extra_tablenav( $which );
		$this->pagination( $which );
		echo '<br class="clear"></div>';
	}

	public function prepare_items() {
		$items = aw_review_items( $this->type );
		$s     = mb_strtolower( sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' !== $s ) {
			$items = array_values( array_filter( $items, fn( $p ) => false !== strpos( mb_strtolower( $p->post_title . ' ' . $p->post_excerpt . ' ' . $this->source_url( $p ) ), $s ) ) ); // the source address, or a Press item's coverage address
		}
		$by = sanitize_key( $_GET['orderby'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( in_array( $by, array( 'title', 'type' ), true ) || ( 'received' === $by && 'asc' === sanitize_key( $_GET['order'] ?? '' ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$asc = 'desc' !== sanitize_key( $_GET['order'] ?? 'asc' ); // phpcs:ignore WordPress.Security.NonceVerification
			usort(
				$items,
				function ( $a, $b ) use ( $by, $asc ) {
					$c = 'title' === $by ? strcasecmp( $a->post_title, $b->post_title ) : ( 'type' === $by ? strcmp( AW_REVIEW_TYPES[ $a->post_type ], AW_REVIEW_TYPES[ $b->post_type ] ) : strcmp( $a->post_date, $b->post_date ) );
					return $asc ? $c : -$c;
				}
			); // default: newest first, as aw_review_items() returns them
		}
		$per = $this->get_items_per_page( 'aw_review_per_page', 50 );
		$this->set_pagination_args( array( 'total_items' => count( $items ), 'per_page' => $per ) );
		$this->items           = array_slice( $items, ( $this->get_pagenum() - 1 ) * $per, $per );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'title' );
	}

	protected function column_cb( $p ) {
		return '<label class="screen-reader-text" for="aw-rv-' . (int) $p->ID . '">' . esc_html( 'Select ' . get_the_title( $p ) ) . '</label><input type="checkbox" name="ids[]" value="' . (int) $p->ID . '" id="aw-rv-' . (int) $p->ID . '">';
	}

	private function source_url( $p ) {
		$url = (string) get_post_meta( $p->ID, 'aw_source_url', true );
		return $url ? $url : (string) get_post_meta( $p->ID, 'aw_press_url', true );
	}

	protected function column_title( $p ) {
		$act  = fn( $do ) => esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=aw_review&do=' . $do . '&ids[]=' . $p->ID . '&type=' . $this->type ), 'aw_review' ) );
		$acts = array( 'edit' => '<a href="' . esc_url( get_edit_post_link( $p->ID ) ) . '">Edit</a>' );
		if ( $this->source_url( $p ) ) {
			$acts['view'] = '<a href="' . esc_url( $this->source_url( $p ) ) . '" target="_blank" rel="noopener">Source<span class="screen-reader-text"> (opens in a new tab)</span></a>';
		}
		$acts['publish'] = '<a href="' . $act( 'publish' ) . '">Publish</a>';
		$acts['trash']   = '<a href="' . $act( 'reject' ) . '">Reject</a>';
		$excerpt         = wp_trim_words( wp_strip_all_tags( $p->post_excerpt ? $p->post_excerpt : $p->post_content ), 24 );
		$title           = get_the_title( $p ) ? get_the_title( $p ) : '(no title)';
		return '<strong><a class="row-title" href="' . esc_url( get_edit_post_link( $p->ID ) ) . '">' . esc_html( $title ) . '</a></strong>'
			. ( $excerpt ? '<p class="description">' . esc_html( $excerpt ) . '</p>' : '' ) . $this->row_actions( $acts );
	}

	protected function column_type( $p ) {
		return esc_html( AW_REVIEW_TYPES[ $p->post_type ] );
	}

	protected function column_source( $p ) {
		$url = $this->source_url( $p );
		return $url ? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( (string) wp_parse_url( $url, PHP_URL_HOST ) ) . '</a>' : '—';
	}

	protected function column_why( $p ) {
		$why = (string) get_post_meta( $p->ID, 'aw_review', true );
		return esc_html( $why ? preg_replace( '/^held:\s*/', '', $why ) : ( 'pending' === $p->post_status ? 'waiting for a first review' : '' ) );
	}

	protected function column_received( $p ) {
		return esc_html( get_the_date( 'j M Y', $p ) );
	}

	public function no_items() {
		echo 'Nothing is waiting. New items that pass the checks go live on their own.';
	}
}
