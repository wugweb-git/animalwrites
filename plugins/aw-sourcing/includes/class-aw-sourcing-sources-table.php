<?php
/**
 * Sourcing → Sources → Feeds as a core list table (owner, 24 Oct 2026; was News feeds, Library sources and
 * Opportunity sources; moved into Sources on 25 Oct 2026): every source of aw_sources in one list with the publisher
 * each belongs to, views All / News / Library / Opportunity / On / Off, search, sortable name, type, publisher and
 * status, pagination (screen option aw_sources_per_page), row actions (Edit, Turn on/off, Keyword check on/off,
 * Delete a feed) and the same as bulk actions. On a source's edit screen it lists only that site's feeds. Inside a
 * POST form (action aw_sourcing, tab feeds, nonce aw_sourcing), so the table prints no nonce of its own.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Every source: News, Library and Opportunity. */
class AW_Sourcing_Sources_Table extends WP_List_Table {

	/** @var array Every source, in the lists' own order. */
	private $all;

	/** @var string Only this site's sources ('' for all). */
	private $host;

	/**
	 * @param string $host Only this site's sources ('' for all).
	 */
	public function __construct( $host = '' ) {
		parent::__construct( array( 'singular' => 'source', 'plural' => 'sources', 'ajax' => false, 'screen' => AW_SRC_SCREEN ) );
		$this->host = (string) $host;
		$this->all  = array_values( array_filter( (array) aw_sources_config()['sources'], fn( $s ) => is_array( $s ) && ( '' === $this->host || aw_src_source_host( $s ) === $this->host ) ) );
	}

	private function url( array $args = array() ) {
		return add_query_arg( $args, admin_url( 'admin.php?page=aw-sourcing&tab=sources&view=feeds' ) );
	}

	/** A source's edit screen (from a source's screen: back there afterwards). */
	private function edit_url( array $item ) {
		$args = array( 'page' => 'aw-sourcing', 'tab' => 'sources', 'feed' => $item['id'], 'type' => (string) ( $item['type'] ?? '' ) );
		if ( '' !== $this->host ) {
			$args['host'] = rawurlencode( $this->host );
		}
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/** The view from the address: [type, state]. */
	private function view() {
		$t = sanitize_key( $_GET['type'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		$s = sanitize_key( $_GET['state'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		return array( isset( AW_SOURCE_TYPES[ $t ] ) ? $t : '', in_array( $s, array( 'on', 'off' ), true ) ? $s : '' );
	}

	protected function get_views() {
		list( $vt, $vs ) = $this->view();
		$on              = count( array_filter( $this->all, fn( $s ) => ! empty( $s['on'] ) ) );
		$types           = array_count_values( array_map( fn( $s ) => (string) ( $s['type'] ?? '' ), $this->all ) );
		$list            = array( 'all' => array( 'All', count( $this->all ), array(), '' === $vt && '' === $vs ) );
		foreach ( AW_SOURCE_TYPES as $t => $d ) {
			$list[ $t ] = array( $d[0], (int) ( $types[ $t ] ?? 0 ), array( 'type' => $t ), $t === $vt && '' === $vs );
		}
		$list['on']  = array( 'On', $on, array( 'state' => 'on' ), 'on' === $vs && '' === $vt );
		$list['off'] = array( 'Off', count( $this->all ) - $on, array( 'state' => 'off' ), 'off' === $vs && '' === $vt );
		$out         = array();
		foreach ( $list as $k => $v ) {
			$out[ $k ] = '<a href="' . esc_url( $this->url( $v[2] ) ) . '"' . ( $v[3] ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $v[0] ) . ' <span class="count">(' . esc_html( number_format_i18n( $v[1] ) ) . ')</span></a>';
		}
		return $out;
	}

	public function get_columns() {
		return array(
			'cb'     => '<input type="checkbox" />',
			'name'   => 'Name',
			'type'   => 'Type',
			'url'    => 'Feed URL or Google News search',
			'host'   => 'Publisher',
			'on'     => 'Status',
			'filter' => 'Keyword check',
		);
	}

	protected function get_sortable_columns() {
		return array( 'name' => array( 'name', false ), 'type' => array( 'type', false ), 'host' => array( 'host', false ), 'on' => array( 'on', false ) );
	}

	protected function get_primary_column_name() {
		return 'name';
	}

	protected function get_bulk_actions() {
		return array( 'on' => 'Turn on', 'off' => 'Turn off', 'filter_on' => 'Keyword check on', 'filter_off' => 'Keyword check off', 'delete' => 'Delete feeds (not sources read by code)' );
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

	/** As core, without the table's own nonce: the tab's form already carries aw_sourcing. */
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
		list( $vt, $vs ) = $this->view();
		$s               = mb_strtolower( sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$rows            = array_values(
			array_filter(
				$this->all,
				fn( $x ) => ( '' === $vt || ( $x['type'] ?? '' ) === $vt )
					&& ( '' === $vs || ( 'on' === $vs ) === ! empty( $x['on'] ) )
					&& ( '' === $s || false !== strpos( mb_strtolower( ( $x['name'] ?? '' ) . ' ' . ( $x['url'] ?? '' ) . ' ' . aw_src_source_host( $x ) ), $s ) )
			)
		);
		$by = sanitize_key( $_GET['orderby'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $this->get_sortable_columns()[ $by ] ) ) { // otherwise the lists' own order, as the workers read them
			$asc = 'desc' !== strtolower( sanitize_key( $_GET['order'] ?? 'asc' ) ); // phpcs:ignore WordPress.Security.NonceVerification
			usort(
				$rows,
				function ( $a, $b ) use ( $by, $asc ) {
					if ( 'on' === $by ) {
						$c = (int) ! empty( $a['on'] ) <=> (int) ! empty( $b['on'] );
					} elseif ( 'host' === $by ) {
						$c = strcmp( aw_src_source_host( $a ), aw_src_source_host( $b ) );
					} else {
						$c = strcasecmp( (string) ( $a[ $by ] ?? '' ), (string) ( $b[ $by ] ?? '' ) );
					}
					$c = $c ? $c : strcasecmp( (string) $a['name'], (string) $b['name'] );
					return $asc ? $c : -$c;
				}
			);
		}
		$per = $this->get_items_per_page( 'aw_sources_per_page', 50 );
		$this->set_pagination_args( array( 'total_items' => count( $rows ), 'per_page' => $per ) );
		$this->items           = array_slice( $rows, ( $this->get_pagenum() - 1 ) * $per, $per );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'name' );
	}

	/** A source that is off gets the plugins list's problem row under it. */
	public function single_row( $item ) {
		parent::single_row( $item );
		if ( empty( $item['on'] ) ) {
			aw_sourcing_problem_row( $this, 'Off: the workers skip ' . $item['name'] . ' until it is turned on.', 'warning' );
		}
	}

	/** Row key: type|id (ids are unique per type). */
	private function key( $item ) {
		return (string) ( $item['type'] ?? '' ) . '|' . (string) $item['id'];
	}

	protected function column_cb( $item ) {
		$id = 'aw-src-' . ( $item['type'] ?? '' ) . '-' . $item['id'];
		return '<input type="checkbox" name="ids[]" value="' . esc_attr( $this->key( $item ) ) . '" id="' . esc_attr( $id ) . '"><label for="' . esc_attr( $id ) . '" class="screen-reader-text">' . esc_html( 'Select ' . $item['name'] ) . '</label>';
	}

	/** A row action is a submit button of the tab's form: src_row = "op:type|id". */
	private function op_button( $op, $label, $item ) {
		return '<button type="submit" class="button-link" name="src_row" value="' . esc_attr( $op . ':' . $this->key( $item ) ) . '">' . esc_html( $label ) . '</button>';
	}

	protected function column_name( $item ) {
		$rss  = 'rss' === ( $item['kind'] ?? '' );
		$edit = $this->edit_url( $item );
		$acts = array( 'edit' => '<a href="' . esc_url( $edit ) . '" aria-label="' . esc_attr( 'Edit ' . $item['name'] ) . '">Edit</a>' );
		$acts[ empty( $item['on'] ) ? 'on' : 'trash' ] = $this->op_button( empty( $item['on'] ) ? 'on' : 'off', empty( $item['on'] ) ? 'Turn on' : 'Turn off', $item );
		if ( $rss ) {
			$acts['filter'] = $this->op_button( empty( $item['filter'] ) ? 'filter_on' : 'filter_off', empty( $item['filter'] ) ? 'Keyword check on' : 'Keyword check off', $item );
			// core's "are you sure" warning (common.js showNotice) before a delete
			$acts['delete'] = '<button type="submit" class="button-link submitdelete" name="src_row" value="' . esc_attr( 'delete:' . $this->key( $item ) ) . '" onclick="return showNotice.warn();">Delete</button>';
		}
		$host = preg_match( '#^https?://#', (string) ( $item['url'] ?? '' ) ) ? aw_host( $item['url'] ) : '';
		$icon = $host && function_exists( 'aw_favicon_url' ) ? aw_favicon_url( $host ) : '';
		$mark = $icon ? '<img src="' . esc_url( $icon ) . '" alt="" width="16" height="16" loading="lazy" decoding="async"> ' : '<span class="dashicons dashicons-rss" aria-hidden="true"></span> ';
		return $mark . '<strong><a class="row-title" href="' . esc_url( $edit ) . '">' . esc_html( $item['name'] ) . '</a></strong>' . $this->row_actions( $acts );
	}

	protected function column_type( $item ) {
		$t = (string) ( $item['type'] ?? '' );
		return '<a href="' . esc_url( $this->url( array( 'type' => $t ) ) ) . '">' . esc_html( AW_SOURCE_TYPES[ $t ][0] ?? $t ) . '</a>';
	}

	protected function column_url( $item ) {
		if ( 'rss' !== ( $item['kind'] ?? '' ) ) {
			return '<code>' . esc_html( $item['url'] ) . '</code> <span class="description">(' . esc_html( (string) $item['kind'] ) . '; needs code to change)</span>';
		}
		$q = aw_gnews_query( $item['url'] );
		return $q ? esc_html( $q ) . '<br><span class="description">Google News search</span>' : '<code>' . esc_html( $item['url'] ) . '</code>';
	}

	protected function column_host( $item ) {
		$h    = aw_src_source_host( $item );
		$edit = add_query_arg( array( 'page' => 'aw-sourcing', 'tab' => 'sources', 'edit' => rawurlencode( $h ) ), admin_url( 'admin.php' ) );
		return '' === $h ? '—' : '<a href="' . esc_url( $edit ) . '">' . esc_html( $h ) . '</a>' . ( 'code' === aw_src_read_by( $item ) ? '<br><span class="description">Read by code</span>' : '' );
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
		echo 'No feeds.';
	}
}
