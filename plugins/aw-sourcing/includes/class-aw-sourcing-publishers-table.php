<?php
/**
 * Settings → Sourcing → Publishers as a core list table (owner, 24 Oct 2026): views by status, search, sortable
 * columns, pagination with a screen option, row actions (Allow, Neutral, Block) and bulk actions. The short name is
 * edited in place and saved with "Save short names". Everything posts to admin-post.php (action aw_sourcing, tab
 * publishers) with the list table's nonce. Loaded only when the tab is shown.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Publishers the workers have sent, with their status. */
class AW_Sourcing_Publishers_Table extends WP_List_Table {

	/** @var array host => record */
	private $all;

	public function __construct() {
		parent::__construct( array( 'singular' => 'publisher', 'plural' => 'publishers', 'ajax' => false, 'screen' => 'settings_page_aw-sourcing' ) );
		// A host kept only for its icon (kind, e.g. a directory source) is not a publisher.
		$this->all = array_filter( aw_publishers(), fn( $p ) => empty( $p['kind'] ) );
	}

	private function status() {
		$s = sanitize_key( $_GET['status'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		return in_array( $s, array( 'allow', 'neutral', 'block' ), true ) ? $s : '';
	}

	protected function get_views() {
		$base   = admin_url( 'options-general.php?page=aw-sourcing' );
		$counts = array_count_values( array_map( fn( $p ) => (string) ( $p['status'] ?? 'neutral' ), $this->all ) );
		$out    = array();
		foreach ( array( '' => 'All', 'neutral' => 'Neutral (new finds)', 'allow' => 'Allowed', 'block' => 'Blocked' ) as $k => $l ) {
			$n         = '' === $k ? count( $this->all ) : (int) ( $counts[ $k ] ?? 0 );
			$cur       = $this->status() === $k;
			$out[ '' === $k ? 'all' : $k ] = '<a href="' . esc_url( $k ? add_query_arg( 'status', $k, $base ) : $base ) . '"' . ( $cur ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $l ) . ' <span class="count">(' . esc_html( number_format_i18n( $n ) ) . ')</span></a>';
		}
		return $out;
	}

	public function get_columns() {
		return array(
			'cb'     => '<input type="checkbox" />',
			'host'   => 'Host',
			'name'   => 'Name',
			'short'  => 'Short name',
			'status' => 'Status',
			'rating' => 'Rating',
			'count'  => 'Items',
			'first'  => 'First seen',
			'last'   => 'Last seen',
		);
	}

	protected function get_sortable_columns() {
		return array(
			'host'   => array( 'host', false ),
			'name'   => array( 'name', false ),
			'status' => array( 'status', false ),
			'count'  => array( 'count', true ),
			'first'  => array( 'first', true ),
			'last'   => array( 'last', true ),
		);
	}

	protected function get_primary_column_name() {
		return 'host';
	}

	protected function get_bulk_actions() {
		return array( 'allow' => 'Change to Allowed', 'neutral' => 'Change to Neutral', 'block' => 'Change to Blocked' );
	}

	/** The core bulk menu is named "action", which admin-post.php reads as its own; this one is bulk_status. */
	protected function bulk_actions( $which = '' ) {
		$two = 'top' === $which ? '' : '2';
		echo '<label for="aw-pub-bulk-' . esc_attr( $which ) . '" class="screen-reader-text">Select bulk action</label>';
		echo '<select name="bulk_status' . esc_attr( $two ) . '" id="aw-pub-bulk-' . esc_attr( $which ) . '"><option value="">Bulk actions</option>';
		foreach ( $this->get_bulk_actions() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
		}
		echo '</select>';
		submit_button( 'Apply', 'action', '', false, array( 'id' => 'aw-pub-apply-' . $which ) );
	}

	protected function extra_tablenav( $which ) {
		if ( 'top' === $which ) {
			echo '<div class="alignleft actions">';
			submit_button( 'Save short names', 'secondary', 'save_short', false );
			echo '</div>';
		}
	}

	public function prepare_items() {
		$st   = $this->status();
		$s    = mb_strtolower( sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$rows = array();
		foreach ( $this->all as $h => $p ) {
			if ( ( $st && $st !== ( $p['status'] ?? 'neutral' ) ) || ( '' !== $s && false === strpos( $h . ' ' . mb_strtolower( (string) ( $p['name'] ?? '' ) . ' ' . (string) ( $p['short'] ?? '' ) ), $s ) ) ) {
				continue;
			}
			$rows[] = array( 'host' => $h ) + $p;
		}
		$by  = sanitize_key( $_GET['orderby'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		$by  = isset( $this->get_sortable_columns()[ $by ] ) ? $by : '';
		$asc = 'asc' === strtolower( sanitize_key( $_GET['order'] ?? 'desc' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		usort(
			$rows,
			function ( $a, $b ) use ( $by, $asc ) {
				if ( '' === $by ) { // as before: most items first, then the latest seen
					return ( (int) $b['count'] <=> (int) $a['count'] ) ?: strcmp( (string) $b['last'], (string) $a['last'] );
				}
				$c = 'count' === $by ? ( (int) $a['count'] <=> (int) $b['count'] ) : strcasecmp( (string) ( $a[ $by ] ?? '' ), (string) ( $b[ $by ] ?? '' ) );
				$c = $c ? $c : strcmp( $a['host'], $b['host'] );
				return $asc ? $c : -$c;
			}
		);
		$per = $this->get_items_per_page( 'aw_publishers_per_page', 50 );
		$this->set_pagination_args( array( 'total_items' => count( $rows ), 'per_page' => $per ) );
		$this->items           = array_slice( $rows, ( $this->get_pagenum() - 1 ) * $per, $per );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'host' );
	}

	protected function column_cb( $item ) {
		$id = 'aw-h-' . md5( $item['host'] );
		return '<input type="checkbox" name="hosts[]" value="' . esc_attr( $item['host'] ) . '" id="' . esc_attr( $id ) . '"><label for="' . esc_attr( $id ) . '" class="screen-reader-text">' . esc_html( 'Select ' . $item['host'] ) . '</label>';
	}

	/** A row action is a submit button of the form: row_status = "status:host". */
	private function status_button( $st, $label, $host ) {
		return '<button type="submit" class="button-link" name="row_status" value="' . esc_attr( $st . ':' . $host ) . '">' . esc_html( $label ) . '</button>';
	}

	protected function column_host( $item ) {
		$acts = array();
		foreach ( array( 'allow' => 'Allow', 'neutral' => 'Neutral', 'block' => 'Block' ) as $st => $l ) {
			if ( $st !== ( $item['status'] ?? 'neutral' ) ) {
				$acts[ 'block' === $st ? 'trash' : $st ] = $this->status_button( $st, $l, $item['host'] );
			}
		}
		return '<strong>' . esc_html( $item['host'] ) . '</strong>' . $this->row_actions( $acts );
	}

	protected function column_short( $item ) {
		return '<input type="text" name="short[' . esc_attr( $item['host'] ) . ']" value="' . esc_attr( (string) ( $item['short'] ?? '' ) ) . '" maxlength="24" size="12" aria-label="' . esc_attr( 'Short name for ' . $item['host'] ) . '">';
	}

	protected function column_status( $item ) {
		return aw_publisher_status_html( (string) ( $item['status'] ?? 'neutral' ) );
	}

	protected function column_rating( $item ) {
		return aw_publisher_rating_html( $item );
	}

	protected function column_default( $item, $col ) {
		if ( 'count' === $col ) {
			return esc_html( number_format_i18n( (int) $item['count'] ) );
		}
		return esc_html( (string) ( $item[ $col ] ?? '' ) );
	}

	public function no_items() {
		echo 'No publishers.';
	}
}
