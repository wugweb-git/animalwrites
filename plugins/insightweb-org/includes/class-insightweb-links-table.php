<?php
/**
 * The Links tab as a core list table (WP_List_Table): the inbound links another site makes to this one — type filter,
 * search, sortable columns and pagination. Read-only: this list changes nothing.
 *
 * Core markup only, so it inherits the admin styling and accessibility and embeds unchanged as a shell "part".
 *
 * @package InsightWeb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * The Links list table.
 */
class InsightWeb_Links_Table extends WP_List_Table {

	/**
	 * Set up the list: one row per inbound link.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'insightweb_link',
				'plural'   => 'insightweb_links',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'domain'     => __( 'Domain', 'insightweb' ),
			'linked_url' => __( 'Links to', 'insightweb' ),
			'anchor'     => __( 'Anchor', 'insightweb' ),
			'links'      => __( 'Links', 'insightweb' ),
			'type'       => __( 'Type', 'insightweb' ),
			'last_seen'  => __( 'Last seen', 'insightweb' ),
		);
	}

	/**
	 * Sortable columns and their first direction.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	protected function get_sortable_columns() {
		return array(
			'domain'    => array( 'domain', false ),
			'links'     => array( 'links', true ),
			'last_seen' => array( 'last_seen', true ),
		);
	}

	/**
	 * The column that holds the row actions.
	 *
	 * @return string
	 */
	protected function get_primary_column_name() {
		return 'domain';
	}

	/**
	 * The type filter above the list.
	 *
	 * @param string $which top or bottom.
	 * @return void
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$types = insightweb_link_types();
		if ( ! $types ) {
			return;
		}
		$cur = insightweb_list_filters( 'links' )['type'];
		echo '<div class="alignleft actions">';
		echo '<label class="screen-reader-text" for="insightweb-links-type">' . esc_html__( 'Filter by type', 'insightweb' ) . '</label>';
		echo '<select name="type" id="insightweb-links-type"><option value="">' . esc_html__( 'All types', 'insightweb' ) . '</option>';
		foreach ( $types as $type ) {
			echo '<option value="' . esc_attr( $type ) . '"' . selected( $cur, $type, false ) . '>' . esc_html( ucwords( str_replace( array( '-', '_' ), ' ', $type ) ) ) . '</option>';
		}
		echo '</select>';
		submit_button( __( 'Filter', 'insightweb' ), '', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * Query one page of inbound links.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$f                     = insightweb_list_filters( 'links' );
		$f['per']              = $this->get_items_per_page( 'insightweb_links_per_page', 50 );
		$f['paged']            = $this->get_pagenum();
		$result                = insightweb_links_list( $f );
		$this->items           = $result['items'];
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'domain' );
		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $f['per'],
			)
		);
	}

	/**
	 * Message for an empty list.
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'No inbound links recorded yet.', 'insightweb' );
	}

	/**
	 * Domain, linking from the page that carries the link.
	 *
	 * @param object $item Link row.
	 * @return string
	 */
	protected function column_domain( $item ) {
		$domain = (string) $item->domain;
		$from   = (string) $item->from_url;
		$out    = '<strong>' . esc_html( '' !== $domain ? $domain : $from ) . '</strong>';
		if ( '' !== $from ) {
			$out .= '<br><a href="' . esc_url( $from ) . '" target="_blank" rel="noopener nofollow">' . esc_html( $from ) . '</a>';
		}
		return $out;
	}

	/**
	 * The page on this site that is linked to.
	 *
	 * @param object $item Link row.
	 * @return string
	 */
	protected function column_linked_url( $item ) {
		$url = (string) $item->linked_url;
		if ( '' === $url ) {
			return '–';
		}
		$href = 0 === strpos( $url, '/' ) ? home_url( $url ) : $url;
		return '<a href="' . esc_url( $href ) . '" target="_blank" rel="noopener">' . esc_html( $url ) . '</a>';
	}

	/**
	 * Default columns (anchor, links count, type, last seen).
	 *
	 * @param object $item Link row.
	 * @param string $col  Column key.
	 * @return string
	 */
	protected function column_default( $item, $col ) {
		switch ( $col ) {
			case 'anchor':
				return '' === (string) $item->anchor ? '–' : esc_html( (string) $item->anchor );
			case 'links':
				return esc_html( number_format_i18n( (int) $item->links ) );
			case 'type':
				return '' === (string) $item->type ? '–' : esc_html( ucwords( str_replace( array( '-', '_' ), ' ', (string) $item->type ) ) );
			case 'last_seen':
				$v = (string) ( $item->last_seen ?? '' );
				return '' === $v || '0000-00-00' === $v ? '–' : esc_html( mysql2date( 'j M Y', $v ) );
		}
		return '';
	}
}
