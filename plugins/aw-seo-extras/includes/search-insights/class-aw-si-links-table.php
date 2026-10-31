<?php
/**
 * Links tab list table: who links to us, as a core list table (views by type, search, sorting, bulk and row actions,
 * screen options, pagination). Hide, Unhide and Set type are the only changes it makes, and only to wp_aw_si_links.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * The Links tab list table.
 */
class AW_SI_Links_Table extends WP_List_Table {

	/** Set up the list: one row per link. */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'link',
				'plural'   => 'links',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Request value, sanitised as a key.
	 *
	 * @param string $name Request field.
	 */
	private function arg( string $name ): string {
		return isset( $_REQUEST[ $name ] ) ? sanitize_key( wp_unslash( $_REQUEST[ $name ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- read-only filters.
	}

	/** Columns. */
	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox">',
			'domain'     => __( 'Linking site', 'aw-seo-extras' ),
			'linked_url' => __( 'Linked page', 'aw-seo-extras' ),
			'anchor'     => __( 'Anchor text', 'aw-seo-extras' ),
			'links'      => __( 'Links', 'aw-seo-extras' ),
			'first_seen' => __( 'First seen', 'aw-seo-extras' ),
			'last_seen'  => __( 'Last seen', 'aw-seo-extras' ),
			'source'     => __( 'Source', 'aw-seo-extras' ),
			'type'       => __( 'Type', 'aw-seo-extras' ),
		);
	}

	/** Sortable columns and their first direction. */
	protected function get_sortable_columns() {
		return array(
			'domain'     => array( 'domain', false ),
			'linked_url' => array( 'linked_url', false ),
			'links'      => array( 'links', true ),
			'first_seen' => array( 'first_seen', true ),
			'last_seen'  => array( 'last_seen', true ),
			'source'     => array( 'source', false ),
			'type'       => array( 'type', false ),
		);
	}

	/** The column that holds the row actions. */
	protected function get_primary_column_name() {
		return 'domain';
	}

	/** Hide, Unhide and Set type. */
	protected function get_bulk_actions() {
		return aw_si_link_actions();
	}

	/** Views: All (not hidden), one per type, Unsorted, Hidden. Spam rows show only in Spam, even when unhidden. */
	private function views_sql(): array {
		$out = array( 'all' => array( __( 'All', 'aw-seo-extras' ), "hidden = 0 AND type <> 'spam'" ) );
		foreach ( aw_si_link_types() as $key => $label ) {
			$out[ $key ] = array( $label, 'spam' === $key ? "type = 'spam'" : "type = '{$key}' AND hidden = 0" );
		}
		$out['unsorted'] = array( __( 'Unsorted', 'aw-seo-extras' ), "type = '' AND hidden = 0" );
		$out['hidden']   = array( __( 'Hidden (not spam)', 'aw-seo-extras' ), "hidden = 1 AND type <> 'spam'" );
		return $out;
	}

	/** The current view; All when none is chosen. */
	private function view(): string {
		$v = $this->arg( 'view' );
		return isset( $this->views_sql()[ $v ] ) ? $v : 'all';
	}

	/** Views with counts. */
	protected function get_views() {
		global $wpdb;
		$t   = aw_si_table( 'links' );
		$out = array();
		$cur = $this->view();
		foreach ( $this->views_sql() as $key => $v ) {
			$n           = aw_si_ready() ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE {$v[1]}" ) : 0; // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- fixed conditions.
			$out[ $key ] = '<a href="' . esc_url( add_query_arg( 'view', $key, aw_si_url( 'links' ) ) ) . '"' . ( $key === $cur ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $v[0] ) . ' <span class="count">(' . esc_html( number_format_i18n( $n ) ) . ')</span></a>';
		}
		return $out;
	}

	/** Load one page of rows. */
	public function prepare_items() {
		global $wpdb;
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), $this->get_primary_column_name() );
		if ( ! aw_si_ready() ) {
			$this->items = array();
			return;
		}
		$t     = aw_si_table( 'links' );
		$where = array( $this->views_sql()[ $this->view() ][1] );
		$args  = array();
		$s     = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- read-only search.
		if ( '' !== $s ) {
			$where[] = '(domain LIKE %s OR from_url LIKE %s OR linked_url LIKE %s OR anchor LIKE %s OR note LIKE %s OR source LIKE %s)';
			$like    = '%' . $wpdb->esc_like( $s ) . '%';
			array_push( $args, $like, $like, $like, $like, $like, $like );
		}
		$cols    = array_keys( $this->get_sortable_columns() );
		$orderby = in_array( $this->arg( 'orderby' ), $cols, true ) ? $this->arg( 'orderby' ) : '';
		$dir     = 'asc' === $this->arg( 'order' ) ? 'ASC' : 'DESC';
		$order   = '' === $orderby ? 'last_seen DESC, id DESC' : "{$orderby} {$dir}, id DESC";
		$sql     = implode( ' AND ', $where );
		$per     = $this->get_items_per_page( 'aw_si_links_per_page', 50 );
		$paged   = $this->get_pagenum();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- conditions and order built above from fixed parts; values are placeholders.
		$total       = (int) ( $args ? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE {$sql}", $args ) ) : $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE {$sql}" ) );
		$this->items = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE {$sql} ORDER BY {$order} LIMIT %d OFFSET %d", array_merge( $args, array( $per, ( $paged - 1 ) * $per ) ) ) );
		// phpcs:enable
		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per,
				'total_pages' => (int) ceil( $total / $per ),
			)
		);
	}

	/** Message when the list is empty. */
	public function no_items() {
		echo esc_html__( 'No links here.', 'aw-seo-extras' );
	}

	/**
	 * Checkbox.
	 *
	 * @param object $item Row.
	 */
	protected function column_cb( $item ) {
		return '<input type="checkbox" name="link_id[]" value="' . esc_attr( (string) $item->id ) . '">';
	}

	/**
	 * Nonce'd link for a row action.
	 *
	 * @param string $action A key of aw_si_link_actions().
	 * @param int    $id     Row.
	 */
	private function action_url( string $action, int $id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'aw_si_link' => $action,
					'link_id[]'  => $id,
				),
				aw_si_url( 'links' )
			),
			'bulk-links'
		);
	}

	/**
	 * Linking site, its page and note, and Hide / Unhide.
	 *
	 * @param object $item Row.
	 */
	protected function column_domain( $item ) {
		$out = '<strong>' . esc_html( (string) $item->domain ) . '</strong>';
		if ( '' !== (string) $item->from_url ) {
			$out .= '<br><a href="' . esc_url( (string) $item->from_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( wp_html_excerpt( (string) $item->from_url, 70, '…' ) ) . '</a>';
		}
		if ( '' !== (string) $item->note ) {
			$out .= '<br><span class="description">' . esc_html( (string) $item->note ) . '</span>';
		}
		$act  = (int) $item->hidden ? 'link_unhide' : 'link_hide';
		$acts = array( $act => '<a href="' . esc_url( $this->action_url( $act, (int) $item->id ) ) . '">' . esc_html( aw_si_link_actions()[ $act ] ) . '</a>' );
		return $out . $this->row_actions( $acts );
	}

	/**
	 * Our page, as a site path linked to it.
	 *
	 * @param object $item Row.
	 */
	protected function column_linked_url( $item ) {
		$url  = (string) $item->linked_url;
		$path = aw_si_path( $url );
		return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( '' !== $path ? $path : $url ) . '</a>';
	}

	/**
	 * Anchor text.
	 *
	 * @param object $item Row.
	 */
	protected function column_anchor( $item ) {
		return '' === (string) $item->anchor ? '–' : esc_html( (string) $item->anchor );
	}

	/**
	 * Number of links.
	 *
	 * @param object $item Row.
	 */
	protected function column_links( $item ) {
		return esc_html( number_format_i18n( (int) $item->links ) );
	}

	/**
	 * Type, or Unsorted; hidden rows say so.
	 *
	 * @param object $item Row.
	 */
	protected function column_type( $item ) {
		$out = esc_html( aw_si_link_types()[ (string) $item->type ] ?? __( 'Unsorted', 'aw-seo-extras' ) );
		if ( (int) $item->hidden ) {
			$out .= '<br><span class="description">' . esc_html__( 'Hidden', 'aw-seo-extras' ) . '</span>';
		}
		return $out;
	}

	/**
	 * Dates and source.
	 *
	 * @param object $item        Row.
	 * @param string $column_name first_seen, last_seen or source.
	 */
	protected function column_default( $item, $column_name ) {
		$v = (string) ( $item->$column_name ?? '' );
		if ( '' === $v ) {
			return '–';
		}
		return 'source' === $column_name ? esc_html( $v ) : esc_html( mysql2date( 'j M Y', $v ) );
	}
}
