<?php
/**
 * A read-only core list table for Kits Core's plain admin tables (owner, 24 Oct 2026: WP_List_Table, no
 * hand-built tables), on the pattern of AW_Sourcing_Simple_Table. Rows come ready as cells of escaped HTML; a 'cb'
 * column takes its cell as the row's checkbox. Optional pages: all rows at once (per_page), or one page the screen
 * already fetched (per_page and total). It prints no nonce and no bulk actions, so it may sit inside the screen's own form.
 * Loaded by kits_simple_table() (admin-table.php) only when a screen renders one.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Plain rows in a core list table. */
class Kits_Simple_Table extends WP_List_Table {

	/** @var array column => label */
	private $cols;

	/** @var array rows: column => escaped HTML, plus _after => a whole row of escaped HTML printed under it */
	private $rows;

	/** @var array per_page (0: all on one page), total (rows are already that page), primary, fixed (bool), empty */
	private $opts;

	/**
	 * @param string $plural Table name (CSS class and screen-reader texts).
	 * @param array  $cols   Column => label.
	 * @param array  $rows   Rows.
	 * @param array  $opts   Options (see $opts).
	 */
	public function __construct( $plural, array $cols, array $rows, array $opts = array() ) {
		parent::__construct( array( 'singular' => $plural, 'plural' => $plural, 'ajax' => false, 'screen' => get_current_screen() ) );
		$this->cols = $cols;
		$this->rows = $rows;
		$this->opts = $opts + array( 'per_page' => 0, 'total' => null, 'primary' => (string) array_key_first( $cols ), 'fixed' => false, 'empty' => 'Nothing yet.' );
	}

	public function get_columns() {
		return $this->cols;
	}

	protected function get_primary_column_name() {
		return $this->opts['primary'];
	}

	protected function get_table_classes() {
		$c = parent::get_table_classes();
		return $this->opts['fixed'] ? $c : array_values( array_diff( $c, array( 'fixed' ) ) ); // content-wide columns unless asked
	}

	/** No bulk actions and no nonce: nothing here posts. */
	protected function display_tablenav( $which ) {
		if ( ! $this->opts['per_page'] ) {
			return;
		}
		echo '<div class="tablenav ' . esc_attr( $which ) . '">';
		$this->pagination( $which );
		echo '<br class="clear"></div>';
	}

	public function prepare_items() {
		$rows = $this->rows;
		$per  = (int) $this->opts['per_page'];
		if ( $per ) {
			$total = null === $this->opts['total'] ? count( $rows ) : (int) $this->opts['total'];
			$this->set_pagination_args( array( 'total_items' => $total, 'per_page' => $per ) );
			if ( null === $this->opts['total'] ) {
				$rows = array_slice( $rows, ( $this->get_pagenum() - 1 ) * $per, $per );
			}
		}
		$this->items           = $rows;
		$this->_column_headers = array( $this->get_columns(), array(), array(), $this->opts['primary'] );
	}

	/** A row with _after gets that row (ready HTML) under it, e.g. the Design system's visual row. */
	public function single_row( $item ) {
		parent::single_row( $item );
		if ( ! empty( $item['_after'] ) ) {
			echo $item['_after']; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts
		}
	}

	protected function column_cb( $item ) {
		return (string) ( $item['cb'] ?? '' ); // arrives escaped
	}

	protected function column_default( $item, $col ) {
		return (string) ( $item[ $col ] ?? '' ); // cells arrive escaped
	}

	public function no_items() {
		echo esc_html( $this->opts['empty'] );
	}
}
