<?php
/**
 * Rising tab: the rising terms as a core list table (views by status, a source filter, search, sorting, bulk and row
 * actions, screen options, pagination). Noted, Dismiss and Back to new are the only changes it makes, and only to
 * Search Insights' own rising table.
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
 * The Rising tab list table.
 */
class AW_SI_Rising_Table extends WP_List_Table {

	/** Set up the list: one row per rising term. */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'term',
				'plural'   => 'rising',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Request value, sanitised as a key.
	 *
	 * @param string $name Request field.
	 */
	private function arg( string $name ): string {
		return isset( $_REQUEST[ $name ] ) ? sanitize_key( wp_unslash( $_REQUEST[ $name ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- read-only filters.
	}

	/** Columns. */
	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox">',
			'term'       => __( 'Term', 'aw-seo-extras' ),
			'sources'    => __( 'Sources', 'aw-seo-extras' ),
			'growth'     => __( 'Growth', 'aw-seo-extras' ),
			'first_seen' => __( 'First seen', 'aw-seo-extras' ),
			'last_seen'  => __( 'Last seen', 'aw-seo-extras' ),
			'page'       => __( 'Matching page', 'aw-seo-extras' ),
			'action'     => __( 'Suggested action', 'aw-seo-extras' ),
			'status'     => __( 'Status', 'aw-seo-extras' ),
		);
	}

	/** Sortable columns and their first direction. */
	protected function get_sortable_columns() {
		return array(
			'term'       => array( 'term', false ),
			'growth'     => array( 'growth', true ),
			'first_seen' => array( 'first_seen', true ),
			'last_seen'  => array( 'last_seen', true ),
		);
	}

	/** The column that holds the row actions. */
	protected function get_primary_column_name() {
		return 'term';
	}

	/** The owner's decisions: the only change this list makes, to its own table. */
	protected function get_bulk_actions() {
		return array(
			'noted'     => __( 'Mark as noted', 'aw-seo-extras' ),
			'dismissed' => __( 'Dismiss', 'aw-seo-extras' ),
			'new'       => __( 'Back to new', 'aw-seo-extras' ),
		);
	}

	/** Labels of the statuses (the views). */
	private function statuses(): array {
		return array(
			'new'       => __( 'New', 'aw-seo-extras' ),
			'noted'     => __( 'Noted', 'aw-seo-extras' ),
			'dismissed' => __( 'Dismissed', 'aw-seo-extras' ),
		);
	}

	/** The current view: a status, or all; New when none is chosen. */
	private function view(): string {
		$v = $this->arg( 'view' );
		return isset( $this->statuses()[ $v ] ) || 'all' === $v ? $v : 'new';
	}

	/** Views by status, with counts. */
	protected function get_views() {
		global $wpdb;
		$t      = aw_si_table( 'rising' );
		$counts = aw_si_ready() ? array_column( (array) $wpdb->get_results( "SELECT status, COUNT(*) n FROM {$t} GROUP BY status", ARRAY_A ), 'n', 'status' ) : array(); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
		$out    = array();
		$cur    = $this->view();
		foreach ( array_merge( $this->statuses(), array( 'all' => __( 'All', 'aw-seo-extras' ) ) ) as $key => $label ) {
			$n           = 'all' === $key ? array_sum( array_map( 'intval', $counts ) ) : (int) ( $counts[ $key ] ?? 0 );
			$out[ $key ] = '<a href="' . esc_url( add_query_arg( 'view', $key, aw_si_url( 'rising' ) ) ) . '"' . ( $key === $cur ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $label ) . ' <span class="count">(' . esc_html( number_format_i18n( $n ) ) . ')</span></a>';
		}
		return $out;
	}

	/**
	 * The source filter above the list.
	 *
	 * @param string $which top or bottom.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$cur = $this->arg( 'source' );
		echo '<div class="alignleft actions"><label class="screen-reader-text" for="aw-si-rising-source">' . esc_html__( 'Filter by source', 'aw-seo-extras' ) . '</label>';
		echo '<select name="source" id="aw-si-rising-source"><option value="">' . esc_html__( 'All sources', 'aw-seo-extras' ) . '</option>';
		foreach ( aw_si_rising_sources() as $key => $s ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $cur, $key, false ) . '>' . esc_html( $s['label'] ) . '</option>';
		}
		echo '</select>';
		submit_button( __( 'Filter', 'aw-seo-extras' ), '', 'filter_action', false );
		echo '</div>';
	}

	/** Load one page of rows. */
	public function prepare_items() {
		global $wpdb;
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), $this->get_primary_column_name() );
		if ( ! aw_si_ready() ) {
			$this->items = array();
			return;
		}
		$t     = aw_si_table( 'rising' );
		$where = array( '1=1' );
		$args  = array();
		$view  = $this->view();
		if ( 'all' !== $view ) {
			$where[] = 'status = %s';
			$args[]  = $view;
		}
		$source = $this->arg( 'source' );
		if ( isset( aw_si_rising_sources()[ $source ] ) ) {
			$where[] = 'FIND_IN_SET(%s, sources)';
			$args[]  = $source;
		}
		$s = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- read-only search.
		if ( '' !== $s ) {
			$where[] = '(term LIKE %s OR note LIKE %s)';
			$like    = '%' . $wpdb->esc_like( $s ) . '%';
			array_push( $args, $like, $like );
		}
		$orders  = array(
			'term'       => 'term',
			'growth'     => 'growth IS NULL, growth',
			'first_seen' => 'first_seen',
			'last_seen'  => 'last_seen',
		);
		$orderby = $orders[ $this->arg( 'orderby' ) ] ?? '';
		$desc    = 'asc' !== $this->arg( 'order' );
		$dir     = $desc ? 'DESC' : 'ASC';
		if ( '' === $orderby ) {
			$order = 'last_seen DESC, growth IS NULL, growth DESC, id DESC'; // Newest first, then the highest growth.
		} elseif ( 'growth' === $this->arg( 'orderby' ) ) {
			$order = "growth IS NULL, growth {$dir}, id DESC"; // Terms without a ratio always last.
		} else {
			$order = "{$orderby} {$dir}, id DESC";
		}
		$sql      = implode( ' AND ', $where );
		$per_page = $this->get_items_per_page( 'aw_si_rising_per_page', 50 );
		$paged    = $this->get_pagenum();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- conditions and order built above from fixed parts; values are placeholders.
		$total       = (int) ( $args ? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE {$sql}", $args ) ) : $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE {$sql}" ) );
		$this->items = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE {$sql} ORDER BY {$order} LIMIT %d OFFSET %d", array_merge( $args, array( $per_page, ( $paged - 1 ) * $per_page ) ) ) );
		// phpcs:enable
		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);
	}

	/** Message when the list is empty. */
	public function no_items() {
		echo esc_html__( 'No rising terms here yet. The workers send them daily (and weekly for autocomplete and Trends related searches); "In the news" is worked out nightly.', 'aw-seo-extras' );
	}

	/**
	 * Checkbox.
	 *
	 * @param object $item Row.
	 */
	protected function column_cb( $item ) {
		return '<input type="checkbox" name="rising_id[]" value="' . esc_attr( (string) $item->id ) . '">';
	}

	/**
	 * Nonce'd link for a row action.
	 *
	 * @param string $action noted, dismissed or new.
	 * @param int    $id     Row.
	 */
	private function action_url( string $action, int $id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'aw_si_rising' => $action,
					'rising_id[]'  => $id,
				),
				aw_si_url( 'rising' )
			),
			'bulk-rising'
		);
	}

	/**
	 * Term, why it is rising, and the row actions.
	 *
	 * @param object $item Row.
	 */
	protected function column_term( $item ) {
		$labels = array(
			'noted'     => __( 'Noted', 'aw-seo-extras' ),
			'dismissed' => __( 'Dismiss', 'aw-seo-extras' ),
			'new'       => __( 'Back to new', 'aw-seo-extras' ),
		);
		$acts   = array();
		foreach ( $labels as $status => $label ) {
			if ( $status !== $item->status ) {
				$acts[ $status ] = '<a href="' . esc_url( $this->action_url( $status, (int) $item->id ) ) . '">' . esc_html( $label ) . '</a>';
			}
		}
		$out = '<strong>' . esc_html( (string) $item->term ) . '</strong>';
		if ( '' !== (string) $item->note ) {
			$out .= '<br><span class="description">' . esc_html( (string) $item->note ) . '</span>';
		}
		return $out . $this->row_actions( $acts );
	}

	/**
	 * Every source that saw the term.
	 *
	 * @param object $item Row.
	 */
	protected function column_sources( $item ) {
		$all = aw_si_rising_sources();
		$out = array();
		foreach ( array_filter( explode( ',', (string) $item->sources ) ) as $s ) {
			$out[] = esc_html( $all[ $s ]['label'] ?? $s );
		}
		return implode( '<br>', $out );
	}

	/**
	 * Times the usual level.
	 *
	 * @param object $item Row.
	 */
	protected function column_growth( $item ) {
		/* translators: %s: times the usual level, such as 2.6. */
		return null === $item->growth ? '–' : esc_html( sprintf( __( '×%s', 'aw-seo-extras' ), number_format_i18n( (float) $item->growth, 1 ) ) );
	}

	/**
	 * A date column.
	 *
	 * @param object $item        Row.
	 * @param string $column_name first_seen or last_seen.
	 */
	protected function column_default( $item, $column_name ) {
		$v = (string) ( $item->$column_name ?? '' );
		return '' === $v ? '–' : esc_html( mysql2date( 'j M Y', $v ) );
	}

	/**
	 * The page that already covers the term (a site path links to it), or none.
	 *
	 * @param object $item Row.
	 */
	protected function column_page( $item ) {
		$p = (string) $item->page;
		if ( '' === $p ) {
			return '<span class="description">' . esc_html__( 'None', 'aw-seo-extras' ) . '</span>';
		}
		if ( 0 === strpos( $p, '/' ) && false === strpos( $p, '{' ) ) {
			return '<a href="' . esc_url( home_url( $p ) ) . '" target="_blank" rel="noopener">' . esc_html( $p ) . '</a>';
		}
		return esc_html( $p );
	}

	/**
	 * Suggested action.
	 *
	 * @param object $item Row.
	 */
	protected function column_action( $item ) {
		return esc_html( aw_si_actions()[ (string) $item->action ] ?? '–' );
	}

	/**
	 * The owner's status.
	 *
	 * @param object $item Row.
	 */
	protected function column_status( $item ) {
		return esc_html( $this->statuses()[ (string) $item->status ] ?? (string) $item->status );
	}
}
