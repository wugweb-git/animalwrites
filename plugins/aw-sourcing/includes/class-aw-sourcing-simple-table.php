<?php
/**
 * A read-only core list table for the Sourcing screens' plain tables (owner, 24 Oct 2026: WP_List_Table, no
 * hand-built tables): the Change log, the Rejected tab, AI models "Daily caps on free models" and "Keys by group",
 * Summary rules "Length per section", Relay requests. Rows come ready as cells of escaped HTML (_problem: plain text
 * for the plugins-list problem row under it); optional search, sorting and pages. It prints no nonce and no bulk actions (nothing to post); it may sit inside a settings form.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Plain rows in a core list table. */
class AW_Sourcing_Simple_Table extends WP_List_Table {

	/** @var array column => label */
	private $cols;

	/** @var array rows: column => escaped HTML, plus _sort => [column => raw value] and _find => searchable text */
	private $rows;

	/** @var array per_page (0: all on one page), sortable (columns), primary, empty, search (bool), fixed (bool) */
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
		$this->opts = $opts + array( 'per_page' => 0, 'sortable' => array(), 'primary' => (string) array_key_first( $cols ), 'empty' => 'Nothing yet.', 'search' => false, 'fixed' => false );
	}

	public function get_columns() {
		return $this->cols;
	}

	protected function get_sortable_columns() {
		$out = array();
		foreach ( (array) $this->opts['sortable'] as $c ) {
			$out[ $c ] = array( $c, false );
		}
		return $out;
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
		if ( $this->opts['search'] ) {
			$s = mb_strtolower( sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
			if ( '' !== $s ) {
				$rows = array_values( array_filter( $rows, fn( $r ) => false !== mb_strpos( mb_strtolower( (string) ( $r['_find'] ?? '' ) ), $s ) ) );
			}
		}
		$by = sanitize_key( $_GET['orderby'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $this->get_sortable_columns()[ $by ] ) ) { // otherwise the order given
			$asc = 'desc' !== strtolower( sanitize_key( $_GET['order'] ?? 'asc' ) ); // phpcs:ignore WordPress.Security.NonceVerification
			usort(
				$rows,
				function ( $a, $b ) use ( $by, $asc ) {
					$x = $a['_sort'][ $by ] ?? wp_strip_all_tags( (string) ( $a[ $by ] ?? '' ) );
					$y = $b['_sort'][ $by ] ?? wp_strip_all_tags( (string) ( $b[ $by ] ?? '' ) );
					$c = is_numeric( $x ) && is_numeric( $y ) ? ( $x <=> $y ) : strcasecmp( (string) $x, (string) $y );
					return $asc ? $c : -$c;
				}
			);
		}
		$per = (int) $this->opts['per_page'];
		if ( $per ) {
			$this->set_pagination_args( array( 'total_items' => count( $rows ), 'per_page' => $per ) );
			$rows = array_slice( $rows, ( $this->get_pagenum() - 1 ) * $per, $per );
		}
		$this->items           = $rows;
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), $this->opts['primary'] );
	}

	/** A row with a _problem text gets the plugins list's problem row under it. */
	public function single_row( $item ) {
		parent::single_row( $item );
		if ( ! empty( $item['_problem'] ) ) {
			aw_sourcing_problem_row( $this, (string) $item['_problem'] );
		}
	}

	protected function column_default( $item, $col ) {
		return (string) ( $item[ $col ] ?? '' ); // cells arrive escaped
	}

	public function no_items() {
		echo esc_html( $this->opts['empty'] );
	}
}

