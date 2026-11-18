<?php
/**
 * The Rising tab as a core list table (WP_List_Table): status views, search, sortable columns, bulk and row status
 * actions (Noted / Dismiss / Back to new) and pagination. The status is the only change it makes, and only to
 * InsightWeb's own rising table — never to the site.
 *
 * Core markup only, so it inherits the admin styling and accessibility and embeds unchanged as a shell "part". The
 * status-action flow functions live in admin.php.
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
 * The Rising list table.
 */
class InsightWeb_Rising_Table extends WP_List_Table {

	/**
	 * Set up the list: one row per rising term.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'insightweb_rising',
				'plural'   => 'insightweb_rising',
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
			'cb'         => '<input type="checkbox">',
			'term'       => __( 'Term', 'insightweb' ),
			'sources'    => __( 'Sources', 'insightweb' ),
			'growth'     => __( 'Growth', 'insightweb' ),
			'first_seen' => __( 'First seen', 'insightweb' ),
			'last_seen'  => __( 'Last seen', 'insightweb' ),
			'page'       => __( 'Matching page', 'insightweb' ),
			'action'     => __( 'Suggested action', 'insightweb' ),
			'status'     => __( 'Status', 'insightweb' ),
		);
	}

	/**
	 * Sortable columns and their first direction.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	protected function get_sortable_columns() {
		return array(
			'term'       => array( 'term', false ),
			'growth'     => array( 'growth', true ),
			'first_seen' => array( 'first_seen', true ),
			'last_seen'  => array( 'last_seen', true ),
		);
	}

	/**
	 * The column that holds the row actions.
	 *
	 * @return string
	 */
	protected function get_primary_column_name() {
		return 'term';
	}

	/**
	 * Table classes without 'fixed', so the columns size to their content and the table fills the content width.
	 *
	 * @return string[]
	 */
	protected function get_table_classes() {
		return array_values( array_diff( parent::get_table_classes(), array( 'fixed' ) ) );
	}

	/**
	 * Status changes: the only change this list makes, to its own table.
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions() {
		return array(
			'rising_noted'     => __( 'Mark as noted', 'insightweb' ),
			'rising_dismissed' => __( 'Dismiss', 'insightweb' ),
			'rising_new'       => __( 'Back to new', 'insightweb' ),
		);
	}

	/**
	 * Status views, with counts.
	 *
	 * @return array<string, string>
	 */
	protected function get_views() {
		$counts  = insightweb_rising_counts();
		$current = insightweb_list_filters( 'rising' )['view'];
		$labels  = insightweb_rising_statuses() + array( 'all' => __( 'All', 'insightweb' ) );
		$out     = array();
		foreach ( $labels as $key => $label ) {
			$url         = add_query_arg( array( 'view' => $key ), insightweb_url( 'rising' ) );
			$out[ $key ] = sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
				esc_url( $url ),
				$key === $current ? ' class="current" aria-current="page"' : '',
				esc_html( $label ),
				esc_html( number_format_i18n( (int) ( $counts[ $key ] ?? 0 ) ) )
			);
		}
		return $out;
	}

	/**
	 * Query one page of rising terms.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$f                     = insightweb_list_filters( 'rising' );
		$f['per']              = $this->get_items_per_page( 'insightweb_rising_per_page', 50 );
		$f['paged']            = $this->get_pagenum();
		$result                = insightweb_rising_list( $f );
		$this->items           = $result['items'];
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'term' );
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
		esc_html_e( 'No rising terms yet. They appear once a source has reported them.', 'insightweb' );
	}

	/**
	 * Bulk checkbox.
	 *
	 * @param object $item Rising row.
	 * @return string
	 */
	protected function column_cb( $item ) {
		return '<input type="checkbox" name="rising[]" value="' . (int) $item->id . '">';
	}

	/**
	 * Term, its note and the status row actions.
	 *
	 * @param object $item Rising row.
	 * @return string
	 */
	protected function column_term( $item ) {
		$labels = array(
			'rising_noted'     => __( 'Noted', 'insightweb' ),
			'rising_dismissed' => __( 'Dismiss', 'insightweb' ),
			'rising_new'       => __( 'Back to new', 'insightweb' ),
		);
		$out    = '<strong>' . esc_html( (string) $item->term ) . '</strong>';
		if ( '' !== (string) $item->note ) {
			$out .= '<br><span class="description">' . esc_html( (string) $item->note ) . '</span>';
		}
		$actions = array();
		foreach ( $labels as $action => $label ) {
			if ( substr( $action, strlen( 'rising_' ) ) !== (string) $item->status ) {
				$actions[ $action ] = '<a href="' . esc_url( insightweb_rising_action_url( $action, (int) $item->id ) ) . '">' . esc_html( $label ) . '</a>';
			}
		}
		return $out . $this->row_actions( $actions );
	}

	/**
	 * Every source that reported the term.
	 *
	 * @param object $item Rising row.
	 * @return string
	 */
	protected function column_sources( $item ) {
		$out = array();
		foreach ( array_filter( explode( ',', (string) $item->sources ) ) as $s ) {
			$out[] = esc_html( ucwords( str_replace( array( '-', '_' ), ' ', trim( $s ) ) ) );
		}
		return $out ? implode( '<br>', $out ) : '–';
	}

	/**
	 * Times the usual level.
	 *
	 * @param object $item Rising row.
	 * @return string
	 */
	protected function column_growth( $item ) {
		/* translators: %s: times the usual level, such as 2.6. */
		return null === $item->growth ? '–' : esc_html( sprintf( __( '×%s', 'insightweb' ), number_format_i18n( (float) $item->growth, 1 ) ) );
	}

	/**
	 * The page that already covers the term, or none.
	 *
	 * @param object $item Rising row.
	 * @return string
	 */
	protected function column_page( $item ) {
		$p = (string) $item->page;
		if ( '' === $p ) {
			return '<span class="description">' . esc_html__( 'None', 'insightweb' ) . '</span>';
		}
		if ( 0 === strpos( $p, '/' ) && false === strpos( $p, '{' ) ) {
			return '<a href="' . esc_url( home_url( $p ) ) . '" target="_blank" rel="noopener">' . esc_html( $p ) . '</a>';
		}
		return esc_html( $p );
	}

	/**
	 * Suggested action.
	 *
	 * @param object $item Rising row.
	 * @return string
	 */
	protected function column_action( $item ) {
		return esc_html( insightweb_actions()[ (string) $item->action ] ?? '–' );
	}

	/**
	 * Owner status.
	 *
	 * @param object $item Rising row.
	 * @return string
	 */
	protected function column_status( $item ) {
		return esc_html( insightweb_rising_statuses()[ (string) $item->status ] ?? (string) $item->status );
	}

	/**
	 * Date columns.
	 *
	 * @param object $item Rising row.
	 * @param string $col  Column key.
	 * @return string
	 */
	protected function column_default( $item, $col ) {
		if ( 'first_seen' === $col || 'last_seen' === $col ) {
			$v = (string) ( $item->$col ?? '' );
			return '' === $v || '0000-00-00' === $v ? '–' : esc_html( mysql2date( 'j M Y', $v ) );
		}
		return '';
	}
}
