<?php
/**
 * Clarity tab list table: one row per page over 28 days (aw_si_clarity_pages() in clarity.php), with views, search,
 * sorting and pagination. Read only.
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
 * The Clarity tab list table.
 */
class AW_SI_Clarity_Table extends WP_List_Table {

	/**
	 * All rows, read once.
	 *
	 * @var array|null
	 */
	private $all = null;

	/** One row per page. */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'clarity-page',
				'plural'   => 'clarity-pages',
				'ajax'     => false,
			)
		);
	}

	/** Every row, cached for the screen; empty without the token or the data function. */
	private function all(): array {
		if ( null === $this->all ) {
			$this->all = array();
			if ( function_exists( 'aw_si_clarity_pages' ) && function_exists( 'aw_si_clarity_on' ) && aw_si_clarity_on() ) {
				foreach ( (array) aw_si_clarity_pages( 28 ) as $r ) {
					$r = (array) $r;
					if ( isset( $r['page'] ) && '' !== (string) $r['page'] ) {
						$r['page']      = (string) $r['page'];
						$r['flag_days'] = (int) ( $r['flag_days'] ?? 0 );
						$this->all[]    = $r;
					}
				}
			}
		}
		return $this->all;
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
			'page'      => __( 'Page', 'aw-seo-extras' ),
			'sessions'  => __( 'Sessions', 'aw-seo-extras' ),
			'pps'       => __( 'Pages per session', 'aw-seo-extras' ),
			'scroll'    => __( 'Scroll depth', 'aw-seo-extras' ),
			'active'    => __( 'Active time', 'aw-seo-extras' ),
			'dead'      => __( 'Dead clicks', 'aw-seo-extras' ),
			'rage'      => __( 'Rage clicks', 'aw-seo-extras' ),
			'quickback' => __( 'Quick backs', 'aw-seo-extras' ),
			'xscroll'   => __( 'Excessive scrolling', 'aw-seo-extras' ),
			'jserror'   => __( 'Script errors', 'aw-seo-extras' ),
			'devices'   => __( 'Mobile / PC / Tablet', 'aw-seo-extras' ),
			'flag'      => __( 'Flag', 'aw-seo-extras' ),
		);
	}

	/** Sortable columns and their first direction. */
	protected function get_sortable_columns() {
		return array(
			'page'      => array( 'page', false ),
			'sessions'  => array( 'sessions', true ),
			'pps'       => array( 'pps', true ),
			'scroll'    => array( 'scroll', true ),
			'active'    => array( 'active', true ),
			'dead'      => array( 'dead', true ),
			'rage'      => array( 'rage', true ),
			'quickback' => array( 'quickback', true ),
			'xscroll'   => array( 'xscroll', true ),
			'jserror'   => array( 'jserror', true ),
			'flag'      => array( 'flag_days', true ),
		);
	}

	/** The main column. */
	protected function get_primary_column_name() {
		return 'page';
	}

	/** Row filters of each view. */
	private function view_tests(): array {
		return array(
			'all'     => '__return_true',
			'flagged' => fn( $r ) => ! empty( $r['flagged'] ),
		);
	}

	/** Views with their counts. */
	protected function get_views() {
		$labels  = array(
			'all'     => __( 'All', 'aw-seo-extras' ),
			'flagged' => __( 'Flagged', 'aw-seo-extras' ),
		);
		$current = $this->arg( 'view' ) ? $this->arg( 'view' ) : 'all';
		$out     = array();
		foreach ( $this->view_tests() as $key => $test ) {
			$n           = count( array_filter( $this->all(), $test ) );
			$out[ $key ] = '<a href="' . esc_url( add_query_arg( 'view', $key, aw_si_url( 'clarity' ) ) ) . '"' . ( $key === $current ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $labels[ $key ] ) . ' <span class="count">(' . esc_html( number_format_i18n( $n ) ) . ')</span></a>';
		}
		return $out;
	}

	/** Filter, search, sort and page the rows. */
	public function prepare_items() {
		$rows   = $this->all();
		$tests  = $this->view_tests();
		$rows   = array_filter( $rows, $tests[ $this->arg( 'view' ) ] ?? $tests['all'] );
		$search = isset( $_REQUEST['s'] ) ? trim( sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- read-only search.
		if ( '' !== $search ) {
			$rows = array_filter( $rows, fn( $r ) => false !== stripos( $r['page'], $search ) );
		}
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'sessions'; // phpcs:ignore WordPress.Security.NonceVerification -- read-only sort.
		$allowed = array_column( $this->get_sortable_columns(), 0 );
		$orderby = in_array( $orderby, $allowed, true ) ? $orderby : 'sessions';
		$asc     = 'asc' === $this->arg( 'order' );
		usort(
			$rows,
			function ( $a, $b ) use ( $orderby, $asc ) {
				$x = $a[ $orderby ] ?? null;
				$y = $b[ $orderby ] ?? null;
				if ( null === $x || null === $y ) {
					return ( null === $x ) <=> ( null === $y ); // Empty values last, either way.
				}
				$c = is_string( $x ) ? strcmp( $x, (string) $y ) : (float) $x <=> (float) $y;
				return $asc ? $c : -$c;
			}
		);
		$per                   = $this->get_items_per_page( 'aw_si_clarity_per_page', 50 );
		$paged                 = max( 1, $this->get_pagenum() );
		$this->items           = array_slice( $rows, ( $paged - 1 ) * $per, $per );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'page' );
		$this->set_pagination_args(
			array(
				'total_items' => count( $rows ),
				'per_page'    => $per,
			)
		);
	}

	/** Message for an empty list: no token, or no data yet. */
	public function no_items() {
		echo esc_html( aw_si_clarity_empty_line() );
	}

	/**
	 * Page link.
	 *
	 * @param array $item Row.
	 */
	protected function column_page( $item ) {
		return '<a href="' . esc_url( home_url( $item['page'] ) ) . '" target="_blank" rel="noopener"><strong>' . esc_html( $item['page'] ) . '</strong></a>';
	}

	/**
	 * Sessions by device.
	 *
	 * @param array $item Row.
	 */
	protected function column_devices( $item ) {
		return esc_html( aw_si_clarity_devices_txt( $item['devices'] ?? array() ) );
	}

	/**
	 * Flagged with its days, the days flagged so far, or a dash.
	 *
	 * @param array $item Row.
	 */
	protected function column_flag( $item ) {
		$days = (int) $item['flag_days'];
		if ( ! $days ) {
			return '—';
		}
		if ( ! empty( $item['flagged'] ) ) {
			/* translators: %s: number of days. */
			return '<span class="dashicons dashicons-flag" aria-hidden="true"></span> ' . esc_html( sprintf( _n( 'Flagged (%s day)', 'Flagged (%s days)', $days, 'aw-seo-extras' ), number_format_i18n( $days ) ) );
		}
		/* translators: %s: number of days. */
		return esc_html( sprintf( _n( '%s day', '%s days', $days, 'aw-seo-extras' ), number_format_i18n( $days ) ) );
	}

	/**
	 * Numeric columns: sessions as whole numbers, pages per session with one decimal, time in seconds, shares in per cent.
	 *
	 * @param array  $item Row.
	 * @param string $col  Column key.
	 */
	protected function column_default( $item, $col ) {
		$v = $item[ $col ] ?? null;
		if ( 'sessions' === $col ) {
			return esc_html( is_numeric( $v ) ? number_format_i18n( (int) $v ) : '–' );
		}
		if ( 'pps' === $col ) {
			return esc_html( aw_si_clarity_num( $v ) );
		}
		if ( 'active' === $col ) {
			return esc_html( aw_si_clarity_secs( $v ) );
		}
		return esc_html( aw_si_clarity_pct( $v ) );
	}
}
