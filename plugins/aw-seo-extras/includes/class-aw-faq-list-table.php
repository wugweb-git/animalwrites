<?php
/**
 * AW SEO Plus → FAQ: the core list table of items with an FAQ (aw_faq_index() rows). Views: "With an FAQ" and
 * "Waiting for approval" (items with draft questions; bulk Approve). Search by name, sort by name, type or questions
 * shown, filter by type. Loaded by aw_faq_admin_page() only.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Items with an FAQ, as a core list table. */
class AW_FAQ_List_Table extends WP_List_Table {

	/** @var array aw_faq_index() rows */
	private $all;

	/** @var string 'all' | 'waiting' */
	public $view;

	/**
	 * @param array $rows aw_faq_index() rows.
	 */
	public function __construct( array $rows ) {
		parent::__construct( array( 'singular' => 'aw-faq-item', 'plural' => 'aw-faq-items', 'ajax' => false, 'screen' => get_current_screen() ) );
		$this->all  = $rows;
		$this->view = 'waiting' === sanitize_key( wp_unslash( $_GET['view'] ?? '' ) ) ? 'waiting' : 'all'; // phpcs:ignore WordPress.Security.NonceVerification -- navigation only
	}

	public function get_columns() {
		return 'waiting' === $this->view
			? array( 'cb' => '<input type="checkbox">', 'name' => 'Name', 'type' => 'Type', 'drafts' => 'Draft questions' )
			: array( 'name' => 'Name', 'type' => 'Type', 'shown' => 'Questions shown', 'mix' => 'Auto / edited / own' );
	}

	protected function get_sortable_columns() {
		return array( 'name' => array( 'name', false ), 'type' => array( 'type', false ), 'shown' => array( 'shown', true ) );
	}

	protected function get_primary_column_name() {
		return 'name';
	}

	protected function get_views() {
		$base  = add_query_arg( 'page', 'aw-faq', admin_url( 'admin.php' ) );
		$n_all = count( array_filter( $this->all, fn( $r ) => $r['shown'] > 0 ) );
		$n_wt  = count( array_filter( $this->all, fn( $r ) => $r['drafts'] > 0 ) );
		return array(
			'all'     => sprintf( '<a href="%s"%s>With an FAQ <span class="count">(%s)</span></a>', esc_url( $base ), 'all' === $this->view ? ' class="current" aria-current="page"' : '', esc_html( number_format_i18n( $n_all ) ) ),
			'waiting' => sprintf( '<a href="%s"%s>Waiting for approval <span class="count">(%s)</span></a>', esc_url( add_query_arg( 'view', 'waiting', $base ) ), 'waiting' === $this->view ? ' class="current" aria-current="page"' : '', esc_html( number_format_i18n( $n_wt ) ) ),
		);
	}

	protected function get_bulk_actions() {
		return 'waiting' === $this->view ? array( 'approve' => 'Approve' ) : array();
	}

	/** The type filter. */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$types = array_unique( wp_list_pluck( $this->all, 'type' ) );
		$cur   = sanitize_key( wp_unslash( $_GET['aw_type'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- a filter
		echo '<div class="alignleft actions"><label class="screen-reader-text" for="aw-faq-type">Filter by type</label><select name="aw_type" id="aw-faq-type"><option value="">All types</option>';
		foreach ( $types as $t ) {
			echo '<option value="' . esc_attr( $t ) . '"' . selected( $cur, $t, false ) . '>' . esc_html( aw_faq_type_label( $t ) ) . '</option>';
		}
		echo '</select>';
		submit_button( 'Filter', '', 'filter_action', false );
		echo '</div>';
	}

	public function prepare_items() {
		// phpcs:disable WordPress.Security.NonceVerification -- search, filter and sort only
		$s       = trim( sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ) );
		$type    = sanitize_key( wp_unslash( $_GET['aw_type'] ?? '' ) );
		$orderby = sanitize_key( wp_unslash( $_GET['orderby'] ?? 'name' ) );
		$order   = 'desc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ?? 'asc' ) ) ) ? -1 : 1;
		// phpcs:enable
		$rows = array_filter( $this->all, fn( $r ) => 'waiting' === $this->view ? $r['drafts'] > 0 : $r['shown'] > 0 );
		if ( '' !== $type ) {
			$rows = array_filter( $rows, fn( $r ) => $r['type'] === $type );
		}
		if ( '' !== $s ) {
			$rows = array_filter( $rows, fn( $r ) => false !== mb_stripos( $r['name'], $s ) );
		}
		$key = in_array( $orderby, array( 'name', 'type', 'shown' ), true ) ? $orderby : 'name';
		usort( $rows, fn( $a, $b ) => $order * ( 'shown' === $key ? $a['shown'] <=> $b['shown'] : strnatcasecmp( (string) $a[ $key ], (string) $b[ $key ] ) ) );
		$per  = 50;
		$page = $this->get_pagenum();
		$this->set_pagination_args( array( 'total_items' => count( $rows ), 'per_page' => $per ) );
		$this->items           = array_slice( $rows, ( $page - 1 ) * $per, $per );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'name' );
	}

	public function no_items() {
		echo esc_html( 'waiting' === $this->view ? 'No draft questions waiting.' : 'No page shows an FAQ yet.' );
	}

	protected function column_cb( $item ) {
		$v = $item['store'] . ':' . $item['id'];
		return '<input type="checkbox" name="item[]" value="' . esc_attr( $v ) . '" aria-label="' . esc_attr( 'Select ' . $item['name'] ) . '">';
	}

	protected function column_name( $item ) {
		$edit = aw_faq_can_edit( $item['store'], $item['id'] ) ? aw_faq_edit_url( $item['store'], $item['id'] ) : '';
		$view = 'term' === $item['store'] ? get_term_link( (int) $item['id'] ) : get_permalink( (int) $item['id'] );
		$name = '' !== $edit ? '<a class="row-title" href="' . esc_url( $edit ) . '">' . esc_html( $item['name'] ) . '</a>' : esc_html( $item['name'] );
		$acts = array();
		if ( '' !== $edit ) {
			$acts['edit'] = '<a href="' . esc_url( $edit ) . '">Edit FAQ</a>';
		}
		if ( $view && ! is_wp_error( $view ) ) {
			$acts['view'] = '<a href="' . esc_url( $view ) . '">View</a>';
		}
		return '<strong>' . $name . '</strong>' . $this->row_actions( $acts );
	}

	protected function column_type( $item ) {
		return esc_html( aw_faq_type_label( $item['type'] ) );
	}

	protected function column_shown( $item ) {
		return esc_html( number_format_i18n( $item['shown'] ) );
	}

	protected function column_mix( $item ) {
		return esc_html( sprintf( '%d / %d / %d', $item['auto'], $item['edited'], $item['own'] ) );
	}

	protected function column_drafts( $item ) {
		return esc_html( number_format_i18n( $item['drafts'] ) ) . ( $item['draft_q'] ? '<br><span class="description">' . implode( '<br>', array_map( 'esc_html', $item['draft_q'] ) ) . '</span>' : '' );
	}
}
