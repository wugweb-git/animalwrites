<?php
/**
 * Check list tab list table: the keywords of the monthly live check, this month's result and the last one, with
 * Remove as a row and bulk action. Changes only the list (option aw_si_checklist).
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
 * The Check list tab list table.
 */
class AW_SI_Checklist_Table extends WP_List_Table {

	/** One row per keyword on the list. */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'checklist-keyword',
				'plural'   => 'checklist',
				'ajax'     => false,
			)
		);
	}

	/** Columns. */
	public function get_columns() {
		return array(
			'cb'     => '<input type="checkbox">',
			'label'  => __( 'Keyword', 'aw-seo-extras' ),
			'month'  => __( 'This month', 'aw-seo-extras' ),
			'last'   => __( 'Last live check', 'aw-seo-extras' ),
			'top3'   => __( 'Top 3 domains', 'aw-seo-extras' ),
			'paa'    => __( 'People also ask', 'aw-seo-extras' ),
			'pos28'  => __( 'Search Console position', 'aw-seo-extras' ),
			'impr28' => __( 'Impressions (28 days)', 'aw-seo-extras' ),
		);
	}

	/** Sortable columns. */
	protected function get_sortable_columns() {
		return array(
			'label'  => array( 'label', false ),
			'pos28'  => array( 'pos28', false ),
			'impr28' => array( 'impr28', true ),
		);
	}

	/** The column with the row actions. */
	protected function get_primary_column_name() {
		return 'label';
	}

	/** Bulk action. */
	protected function get_bulk_actions() {
		return array( 'remove' => __( 'Remove from the list', 'aw-seo-extras' ) );
	}

	/** The list with this month's and the latest live results. */
	public function prepare_items() {
		global $wpdb;
		$ids = aw_si_checklist();
		$per = $this->get_items_per_page( 'aw_si_checklist_per_page', 50 );
		if ( ! $ids || ! aw_si_ready() ) {
			$this->items           = array();
			$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'label' );
			$this->set_pagination_args(
				array(
					'total_items' => 0,
					'per_page'    => $per,
				)
			);
			return;
		}
		$k      = aw_si_table( 'keywords' );
		$o      = aw_si_table( 'obs' );
		$in     = implode( ',', array_map( 'intval', $ids ) );
		$where  = "id IN ({$in})";
		$args   = array();
		$search = isset( $_REQUEST['s'] ) ? trim( sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- read-only search.
		if ( '' !== $search ) {
			$where .= ' AND label LIKE %s';
			$args[] = '%' . $wpdb->esc_like( $search ) . '%';
		}
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'label'; // phpcs:ignore WordPress.Security.NonceVerification -- read-only sort.
		$cols    = array(
			'label'  => 'label',
			'pos28'  => 'pos28 IS NULL, pos28',
			'impr28' => 'impr28',
		);
		$dir     = isset( $_REQUEST['order'] ) && 'desc' === strtolower( sanitize_key( wp_unslash( $_REQUEST['order'] ) ) ) ? 'DESC' : 'ASC'; // phpcs:ignore WordPress.Security.NonceVerification -- read-only sort.
		$by      = ( $cols[ $orderby ] ?? 'label' ) . ' ' . $dir;
		$paged   = max( 1, $this->get_pagenum() );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- integers and fixed parts with placeholders.
		$total = (int) $wpdb->get_var( $args ? $wpdb->prepare( "SELECT COUNT(*) FROM {$k} WHERE {$where}", $args ) : "SELECT COUNT(*) FROM {$k} WHERE {$where}" );
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, label, pos28, impr28 FROM {$k} WHERE {$where} ORDER BY {$by}, label LIMIT %d OFFSET %d", array_merge( $args, array( $per, ( $paged - 1 ) * $per ) ) ) );
		$page  = implode( ',', array_map( 'intval', wp_list_pluck( $rows, 'id' ) ) );
		$live  = $page ? (array) $wpdb->get_results( "SELECT kw_id, metric, d, value, txt FROM {$o} WHERE src = 'live' AND kw_id IN ({$page}) AND metric IN ('position', 'top3', 'paa') ORDER BY d" ) : array();
		// phpcs:enable
		$month = aw_si_live_month();
		$by_kw = array();
		foreach ( $live as $l ) {
			$by_kw[ (int) $l->kw_id ][ $l->metric ] = $l; // Ordered by date: the latest wins.
			if ( 'position' === $l->metric && $l->d >= $month ) {
				$by_kw[ (int) $l->kw_id ]['month'] = $l;
			}
		}
		foreach ( $rows as $r ) {
			$r->live = $by_kw[ (int) $r->id ] ?? array();
		}
		$this->items           = $rows;
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'label' );
		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per,
			)
		);
	}

	/** Message for an empty list. */
	public function no_items() {
		esc_html_e( 'The check list is empty. Add keywords above, or rebuild it from the rules.', 'aw-seo-extras' );
	}

	/**
	 * Bulk checkbox.
	 *
	 * @param object $item Row.
	 */
	protected function column_cb( $item ) {
		return '<input type="checkbox" name="kw[]" value="' . (int) $item->id . '">';
	}

	/**
	 * Keyword and the Remove row action.
	 *
	 * @param object $item Row.
	 */
	protected function column_label( $item ) {
		$url = wp_nonce_url(
			add_query_arg(
				array(
					'aw_si_cl' => 'remove',
					'kw[]'     => (int) $item->id,
				),
				aw_si_url( 'checklist' )
			),
			'bulk-checklist'
		);
		return '<strong>' . esc_html( $item->label ) . '</strong>' . $this->row_actions( array( 'remove' => '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Remove', 'aw-seo-extras' ) . '</a>' ) );
	}

	/**
	 * This month's result, or "not yet".
	 *
	 * @param object $item Row.
	 */
	protected function column_month( $item ) {
		if ( false !== strpos( $item->label, '{' ) ) {
			return '<span class="description">' . esc_html__( 'not searched (Directory pattern)', 'aw-seo-extras' ) . '</span>';
		}
		$m = $item->live['month'] ?? null;
		return $m ? esc_html( (string) $m->txt ) : '<span class="description">' . esc_html__( 'not yet', 'aw-seo-extras' ) . '</span>';
	}

	/**
	 * The latest live result and its month.
	 *
	 * @param object $item Row.
	 */
	protected function column_last( $item ) {
		$p = $item->live['position'] ?? null;
		return $p ? esc_html( (string) $p->txt ) . '<br><span class="description">' . esc_html( mysql2date( 'M Y', $p->d ) ) . '</span>' : '–';
	}

	/**
	 * Top 3 domains of the latest check.
	 *
	 * @param object $item Row.
	 */
	protected function column_top3( $item ) {
		$t = $item->live['top3'] ?? null;
		return $t && '' !== $t->txt ? esc_html( (string) $t->txt ) : '–';
	}

	/**
	 * People also ask, from the latest check.
	 *
	 * @param object $item Row.
	 */
	protected function column_paa( $item ) {
		$t = $item->live['paa'] ?? null;
		return $t && '' !== $t->txt ? esc_html( (string) $t->txt ) : '–';
	}

	/**
	 * Search Console columns.
	 *
	 * @param object $item Row.
	 * @param string $col  Column key.
	 */
	protected function column_default( $item, $col ) {
		if ( 'pos28' === $col ) {
			return null === $item->pos28 ? '–' : esc_html( number_format_i18n( (float) $item->pos28, 1 ) );
		}
		if ( 'impr28' === $col ) {
			return esc_html( number_format_i18n( (int) $item->impr28 ) );
		}
		return '';
	}
}
