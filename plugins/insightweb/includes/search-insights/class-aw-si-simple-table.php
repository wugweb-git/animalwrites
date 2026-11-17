<?php
/**
 * A read-only core list table for InsightWeb's plain admin tables (WP_List_Table, no hand-built tables). Rows come
 * ready as cells of escaped HTML; a 'cb' column takes its cell as the row's checkbox. Optional pages: all rows at
 * once (per_page), or one page the screen already fetched (per_page and total). Optional sorting of the rows it holds
 * (sortable). It prints no nonce and no bulk actions, so it may sit inside the screen's own form.
 * The same pattern as the other Wugweb plugins' *_simple_table() helpers, each plugin with its own copy and prefix.
 * Loaded by aw_si_simple_table() (admin-table.php) only when a screen renders one.
 *
 * @package insightweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Plain rows in a core list table. */
class AW_SI_Simple_Table extends WP_List_Table {

	/** @var array column => label */
	private $cols;

	/** @var array rows: column => escaped HTML, plus _sort => column => raw value to sort by, _after => a whole row of escaped HTML printed under it */
	private $rows;

	/**
	 * @var array per_page (0: all on one page), total (rows are already that page), primary, fixed (bool), empty
	 * (text when there are no rows; null: core's "No items found."), foot (bool: the footer header row), sortable
	 * (column => true to sort descending first, false ascending first; the rows keep their own order until a heading
	 * is clicked), caption (a screen-reader caption).
	 */
	private $opts;

	/**
	 * @param string $plural Table name (CSS class, screen-reader texts, and the prefix of its sort and header ids).
	 * @param array  $cols   Column => label.
	 * @param array  $rows   Rows.
	 * @param array  $opts   Options (see $opts).
	 */
	public function __construct( $plural, array $cols, array $rows, array $opts = array() ) {
		parent::__construct( array( 'singular' => $plural, 'plural' => $plural, 'ajax' => false, 'screen' => get_current_screen() ) );
		$this->cols = $cols;
		$this->rows = $rows;
		$this->opts = $opts + array( 'per_page' => 0, 'total' => null, 'primary' => (string) array_key_first( $cols ), 'fixed' => false, 'empty' => null, 'foot' => true, 'sortable' => array(), 'caption' => '' );
	}

	/**
	 * Core's table, in a box of its own (so the shell's page parts read its pages, not a neighbour's), with header ids
	 * unique to it and no repeated tbody id; with opts foot false, without the footer row that repeats the headings.
	 */
	public function display() {
		echo '<div>';
		$this->display_tablenav( 'top' );
		$this->screen->render_screen_reader_content( 'heading_list' );
		echo '<table class="wp-list-table ' . esc_attr( implode( ' ', $this->get_table_classes() ) ) . '">';
		$this->print_table_description();
		echo '<thead><tr>';
		$this->print_column_headers();
		echo '</tr></thead><tbody>';
		$this->display_rows_or_placeholder();
		echo '</tbody>';
		if ( $this->opts['foot'] ) {
			echo '<tfoot><tr>';
			$this->print_column_headers( false );
			echo '</tr></tfoot>';
		}
		echo '</table>';
		$this->display_tablenav( 'bottom' );
		echo '</div>';
	}

	/** The caption: the screen's own (opts caption), else core's sort description. */
	public function print_table_description() {
		if ( '' !== (string) $this->opts['caption'] ) {
			echo '<caption class="screen-reader-text">' . esc_html( $this->opts['caption'] ) . '</caption>';
			return;
		}
		parent::print_table_description();
	}

	/**
	 * Core's header row, with ids unique to this table: core prints each heading as id='column', so two tables on one
	 * screen repeat the same ids. Each id (and the select-all checkbox's) gets the table's name in front.
	 *
	 * @param bool $with_id Print the ids (core: the header row yes, the footer row no).
	 */
	public function print_column_headers( $with_id = true ) {
		ob_start();
		parent::print_column_headers( $with_id );
		$html   = (string) ob_get_clean();
		$prefix = $this->_args['plural'] . '-'; // Core keeps plural as sanitize_key().
		if ( $with_id ) {
			foreach ( array_keys( $this->get_columns() ) as $key ) {
				$html = str_replace( "id='" . $key . "'", "id='" . esc_attr( $prefix . $key ) . "'", $html );
			}
		}
		echo str_replace( 'cb-select-all-', esc_attr( $prefix ) . 'cb-select-all-', $html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core's own header markup.
	}

	public function get_columns() {
		return $this->cols;
	}

	/** Sortable columns: orderby is the table's name, a hyphen and the column, so two tables on one screen sort apart. */
	protected function get_sortable_columns() {
		$out = array();
		foreach ( (array) $this->opts['sortable'] as $col => $desc_first ) {
			if ( isset( $this->cols[ $col ] ) ) {
				$out[ $col ] = array( $this->_args['plural'] . '-' . $col, (bool) $desc_first );
			}
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
		$per  = (int) $this->opts['per_page'];
		if ( null === $this->opts['total'] ) {
			$rows = $this->sorted( $rows ); // a page the screen fetched itself keeps the screen's order
		}
		if ( $per ) {
			$total = null === $this->opts['total'] ? count( $rows ) : (int) $this->opts['total'];
			$this->set_pagination_args( array( 'total_items' => $total, 'per_page' => $per ) );
			if ( null === $this->opts['total'] ) {
				$rows = array_slice( $rows, ( $this->get_pagenum() - 1 ) * $per, $per );
			}
		}
		$this->items           = $rows;
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), $this->opts['primary'] );
	}

	/**
	 * The rows in the order asked for by orderby and order (read-only sort), else as given. Numbers compare as
	 * numbers, the rest naturally and without case; each row's _sort value for the column, else its cell's text.
	 *
	 * @param array $rows Rows.
	 */
	private function sorted( array $rows ): array {
		$by     = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only sort.
		$prefix = $this->_args['plural'] . '-';
		$col    = 0 === strpos( $by, $prefix ) ? substr( $by, strlen( $prefix ) ) : '';
		if ( '' === $col || ! isset( $this->get_sortable_columns()[ $col ] ) ) {
			return $rows;
		}
		$desc = isset( $_GET['order'] ) && 'desc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only sort.
		$val  = fn( $r ) => array_key_exists( $col, (array) ( $r['_sort'] ?? array() ) ) ? $r['_sort'][ $col ] : trim( wp_strip_all_tags( (string) ( $r[ $col ] ?? '' ) ) );
		usort(
			$rows,
			function ( $a, $b ) use ( $val, $desc ) {
				$x = $val( $a );
				$y = $val( $b );
				$c = is_numeric( $x ) && is_numeric( $y ) ? (float) $x <=> (float) $y : strnatcasecmp( (string) $x, (string) $y );
				return $desc ? -$c : $c;
			}
		);
		return $rows;
	}

	/**
	 * A row, then the row in its _after (ready HTML) under it, e.g. Kits' Design system visual row.
	 *
	 * @param array $item Row.
	 */
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
		if ( null === $this->opts['empty'] ) {
			parent::no_items();
			return;
		}
		echo esc_html( $this->opts['empty'] );
	}
}
