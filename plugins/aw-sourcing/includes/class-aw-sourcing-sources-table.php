<?php
/**
 * Settings → Sourcing → News feeds, Library sources, Opportunity sources as a core list table (owner, 24 Oct 2026):
 * views On / Off, search, sortable name, pagination, row actions (Edit, Turn on/off, Keyword check on/off) and the
 * same as bulk actions. Inside the screen's own form (action aw_sourcing, nonce aw_sourcing), so the table prints no
 * nonce of its own. Loaded only when one of these tabs is shown.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The sources of one type (news, legal or opportunity). */
class AW_Sourcing_Sources_Table extends WP_List_Table {

	/** @var string Tab slug (feeds, library, opportunities). */
	private $tab;
	/** @var string Source type (news, legal, opportunity). */
	private $type;
	/** @var array Sources of this type. */
	private $all;

	public function __construct( $tab ) {
		parent::__construct( array( 'singular' => 'source', 'plural' => 'sources', 'ajax' => false, 'screen' => 'settings_page_aw-sourcing' ) );
		$this->tab  = $tab;
		$this->type = AW_SOURCE_TABS[ $tab ][1];
		$this->all  = array_values( array_filter( (array) aw_sources_config()['sources'], fn( $s ) => ( $s['type'] ?? '' ) === $this->type ) );
	}

	private function url( array $args = array() ) {
		return add_query_arg( $args, admin_url( 'options-general.php?page=aw-sourcing&tab=' . $this->tab ) );
	}

	private function view() {
		$v = sanitize_key( $_GET['state'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		return in_array( $v, array( 'on', 'off' ), true ) ? $v : '';
	}

	protected function get_views() {
		$on  = count( array_filter( $this->all, fn( $s ) => ! empty( $s['on'] ) ) );
		$out = array();
		foreach ( array( '' => array( 'All', count( $this->all ) ), 'on' => array( 'On', $on ), 'off' => array( 'Off', count( $this->all ) - $on ) ) as $k => $v ) {
			$out[ '' === $k ? 'all' : $k ] = '<a href="' . esc_url( $this->url( $k ? array( 'state' => $k ) : array() ) ) . '"' . ( $this->view() === $k ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $v[0] ) . ' <span class="count">(' . esc_html( number_format_i18n( $v[1] ) ) . ')</span></a>';
		}
		return $out;
	}

	public function get_columns() {
		return array(
			'cb'     => '<input type="checkbox" />',
			'name'   => 'Name',
			'url'    => 'Feed URL or Google News search',
			'on'     => 'Status',
			'filter' => 'Keyword check',
		);
	}

	protected function get_sortable_columns() {
		return array( 'name' => array( 'name', false ), 'on' => array( 'on', false ) );
	}

	protected function get_primary_column_name() {
		return 'name';
	}

	protected function get_bulk_actions() {
		return array( 'on' => 'Turn on', 'off' => 'Turn off', 'filter_on' => 'Keyword check on', 'filter_off' => 'Keyword check off' );
	}

	/** The core bulk menu is named "action", which admin-post.php reads as its own; this one is src_bulk / src_bulk2. */
	protected function bulk_actions( $which = '' ) {
		$two = 'top' === $which ? '' : '2';
		echo '<label for="aw-src-bulk-' . esc_attr( $which ) . '" class="screen-reader-text">Select bulk action</label>';
		echo '<select name="src_bulk' . esc_attr( $two ) . '" id="aw-src-bulk-' . esc_attr( $which ) . '"><option value="">Bulk actions</option>';
		foreach ( $this->get_bulk_actions() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
		}
		echo '</select>';
		submit_button( 'Apply', 'action', '', false, array( 'id' => 'aw-src-apply-' . $which ) );
	}

	/** As core, without the table's own nonce: the screen's form already carries aw_sourcing. */
	protected function display_tablenav( $which ) {
		echo '<div class="tablenav ' . esc_attr( $which ) . '">';
		if ( $this->has_items() ) {
			echo '<div class="alignleft actions bulkactions">';
			$this->bulk_actions( $which );
			echo '</div>';
		}
		$this->pagination( $which );
		echo '<br class="clear"></div>';
	}

	public function prepare_items() {
		$v    = $this->view();
		$s    = mb_strtolower( sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$rows = array_values(
			array_filter(
				$this->all,
				fn( $x ) => ( '' === $v || ( 'on' === $v ) === ! empty( $x['on'] ) ) && ( '' === $s || false !== strpos( mb_strtolower( $x['name'] . ' ' . $x['url'] ), $s ) )
			)
		);
		$by = sanitize_key( $_GET['orderby'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( in_array( $by, array( 'name', 'on' ), true ) ) { // otherwise the lists' own order, as the workers read them
			$asc = 'desc' !== strtolower( sanitize_key( $_GET['order'] ?? 'asc' ) ); // phpcs:ignore WordPress.Security.NonceVerification
			usort(
				$rows,
				function ( $a, $b ) use ( $by, $asc ) {
					$c = 'name' === $by ? strcasecmp( $a['name'], $b['name'] ) : ( (int) ! empty( $a['on'] ) <=> (int) ! empty( $b['on'] ) );
					return $asc ? $c : -$c;
				}
			);
		}
		$per = $this->get_items_per_page( 'aw_sources_per_page', 50 );
		$this->set_pagination_args( array( 'total_items' => count( $rows ), 'per_page' => $per ) );
		$this->items           = array_slice( $rows, ( $this->get_pagenum() - 1 ) * $per, $per );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'name' );
	}

	protected function column_cb( $item ) {
		$id = 'aw-src-' . $item['id'];
		return '<input type="checkbox" name="ids[]" value="' . esc_attr( $item['id'] ) . '" id="' . esc_attr( $id ) . '"><label for="' . esc_attr( $id ) . '" class="screen-reader-text">' . esc_html( 'Select ' . $item['name'] ) . '</label>';
	}

	/** A row action is a submit button of the screen's form: src_row = "op:id". */
	private function op_button( $op, $label, $id ) {
		return '<button type="submit" class="button-link" name="src_row" value="' . esc_attr( $op . ':' . $id ) . '">' . esc_html( $label ) . '</button>';
	}

	protected function column_name( $item ) {
		$rss  = 'rss' === ( $item['kind'] ?? '' );
		$acts = array( 'edit' => '<a href="' . esc_url( $this->url( array( 'edit' => $item['id'] ) ) ) . '">Edit</a>' );
		$acts[ empty( $item['on'] ) ? 'on' : 'trash' ] = $this->op_button( empty( $item['on'] ) ? 'on' : 'off', empty( $item['on'] ) ? 'Turn on' : 'Turn off', $item['id'] );
		if ( $rss ) {
			$acts['filter'] = $this->op_button( empty( $item['filter'] ) ? 'filter_on' : 'filter_off', empty( $item['filter'] ) ? 'Keyword check on' : 'Keyword check off', $item['id'] );
		}
		return '<strong><a class="row-title" href="' . esc_url( $this->url( array( 'edit' => $item['id'] ) ) ) . '">' . esc_html( $item['name'] ) . '</a></strong>' . $this->row_actions( $acts );
	}

	protected function column_url( $item ) {
		if ( 'rss' !== ( $item['kind'] ?? '' ) ) {
			return '<code>' . esc_html( $item['url'] ) . '</code> <span class="description">(' . esc_html( (string) $item['kind'] ) . '; needs code to change)</span>';
		}
		$q = aw_gnews_query( $item['url'] );
		return $q ? esc_html( $q ) . '<br><span class="description">Google News search</span>' : '<code>' . esc_html( $item['url'] ) . '</code>';
	}

	protected function column_on( $item ) {
		return ! empty( $item['on'] )
			? '<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> On'
			: '<span class="dashicons dashicons-marker" aria-hidden="true"></span> Off';
	}

	protected function column_filter( $item ) {
		if ( 'rss' !== ( $item['kind'] ?? '' ) ) {
			return '—';
		}
		return ! empty( $item['filter'] ) ? 'On' : 'Off';
	}

	public function no_items() {
		echo 'No sources.';
	}
}
