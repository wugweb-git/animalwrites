<?php
/**
 * Directory → Enrichment review as a core list table (owner, 24 Oct 2026: WP_List_Table, no hand-built tables).
 * The screen fetches one page of rows and builds the cells (escaped HTML); this class adds the checkbox column, the
 * bulk actions and the pages. It sits inside the screen's own POST form to admin-post.php (action aw_enrich_decide,
 * nonce aw_enrich_decide), so the bulk select is named "bulk" (core's "action" would replace admin-post's action) and
 * the table prints no nonce of its own. The top bar shows the item count and page links without the page-number box,
 * so Enter cannot submit the review form.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Enrichment proposals, one page at a time. */
class AW_Sourcing_Enrichment_Table extends WP_List_Table {

	/** @var array bulk action => label */
	private $bulk;

	/**
	 * @param array $rows  Rows: cb, listing, field, current, proposed, source, confidence, status, act (escaped HTML).
	 * @param int   $total Rows matching the filter.
	 * @param int   $per   Rows per page.
	 * @param array $bulk  Bulk action => label.
	 */
	public function __construct( array $rows, $total, $per, array $bulk ) {
		parent::__construct( array( 'singular' => 'proposal', 'plural' => 'proposals', 'ajax' => false, 'screen' => get_current_screen() ) );
		$this->items = $rows;
		$this->bulk  = $bulk;
		$this->set_pagination_args( array( 'total_items' => (int) $total, 'per_page' => (int) $per ) );
		$this->_column_headers = array( $this->get_columns(), array(), array(), 'listing' );
	}

	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox">',
			'listing'    => 'Listing',
			'field'      => 'Field',
			'current'    => 'Current',
			'proposed'   => 'Proposed',
			'source'     => 'Source',
			'confidence' => 'Confidence',
			'status'     => 'Status',
			'act'        => '<span class="screen-reader-text">Actions</span>',
		);
	}

	protected function get_primary_column_name() {
		return 'listing';
	}

	protected function get_table_classes() {
		return array_values( array_diff( parent::get_table_classes(), array( 'fixed' ) ) );
	}

	protected function get_bulk_actions() {
		return $this->bulk;
	}

	/** The bulk select, named "bulk" (see the file comment). */
	protected function bulk_actions( $which = '' ) {
		echo '<label for="aw-enrich-bulk" class="screen-reader-text">Select bulk action</label><select name="bulk" id="aw-enrich-bulk"><option value="">Bulk actions</option>';
		foreach ( $this->bulk as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select> ';
		submit_button( 'Apply', 'action', '', false );
	}

	/** Top: bulk actions and pages; bottom: pages. No nonce: the screen's form has aw_enrich_decide. */
	protected function display_tablenav( $which ) {
		echo '<div class="tablenav ' . esc_attr( $which ) . '">';
		if ( 'top' === $which ) {
			echo '<div class="alignleft actions bulkactions">';
			$this->bulk_actions( $which );
			echo '</div>';
		}
		$this->pagination( 'bottom' ); // the form of the links without the page-number box, on both bars
		echo '<br class="clear"></div>';
	}

	public function prepare_items() {
		// Rows, pages and headers are set in the constructor.
	}

	protected function column_cb( $item ) {
		return (string) ( $item['cb'] ?? '' ); // arrives escaped
	}

	protected function column_default( $item, $col ) {
		return (string) ( $item[ $col ] ?? '' ); // cells arrive escaped
	}

	public function no_items() {
		echo 'Nothing here.';
	}
}
