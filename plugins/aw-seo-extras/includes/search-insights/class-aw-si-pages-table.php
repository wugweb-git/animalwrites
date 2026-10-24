<?php
/**
 * Pages tab list table: views, search, sorting, pagination and a "Clarity note" row action. Read only, except the
 * note form it opens.
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
 * The Pages tab list table.
 */
class AW_SI_Pages_Table extends WP_List_Table {

	/**
	 * All rows (path => figures), read once.
	 *
	 * @var array|null
	 */
	private $all = null;

	/** One row per page. */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'page',
				'plural'   => 'pages',
				'ajax'     => false,
			)
		);
	}

	/** Every row, cached for the screen. */
	private function all(): array {
		if ( null === $this->all ) {
			$this->all = aw_si_pages_data();
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
			'path'     => __( 'Page', 'aw-seo-extras' ),
			'impr'     => __( 'Impressions (28 days)', 'aw-seo-extras' ),
			'clicks'   => __( 'Clicks', 'aw-seo-extras' ),
			'pos'      => __( 'Position', 'aw-seo-extras' ),
			'keywords' => __( 'Keywords owned', 'aw-seo-extras' ),
			'organic'  => __( 'Organic sessions (GA4)', 'aw-seo-extras' ),
			'ai'       => __( 'AI referrals', 'aw-seo-extras' ),
			'clarity'  => __( 'Clarity (28 days)', 'aw-seo-extras' ),
			'psi'      => __( 'PageSpeed (mobile / desktop)', 'aw-seo-extras' ),
			'rm_score' => __( 'SEO score (Rank Math)', 'aw-seo-extras' ),
			'note'     => __( 'Clarity note', 'aw-seo-extras' ),
		);
	}

	/** Sortable columns and their first direction. */
	protected function get_sortable_columns() {
		return array(
			'path'     => array( 'path', false ),
			'impr'     => array( 'impr', true ),
			'clicks'   => array( 'clicks', true ),
			'pos'      => array( 'pos', false ),
			'keywords' => array( 'keywords', true ),
			'organic'  => array( 'organic', true ),
			'ai'       => array( 'ai', true ),
			'clarity'  => array( 'c_sessions', true ),
			'psi'      => array( 'psi_score-mobile', false ),
			'rm_score' => array( 'rm_score', false ),
		);
	}

	/** The column with the row actions. */
	protected function get_primary_column_name() {
		return 'path';
	}

	/** Row filters of each view. */
	private function view_tests(): array {
		return array(
			'all'       => '__return_true',
			'flagged'   => fn( $r ) => ! empty( $r['flagged'] ),
			'ai'        => fn( $r ) => ! empty( $r['ai'] ),
			'conflicts' => fn( $r ) => ! empty( $r['conflicts'] ),
			'noted'     => fn( $r ) => ! empty( $r['note'] ),
			'lowseo'    => fn( $r ) => isset( $r['rm_score'] ) && $r['rm_score'] < AW_SI_RM_LOW,
		);
	}

	/** Views with their counts. */
	protected function get_views() {
		$labels  = array(
			'all'       => __( 'All', 'aw-seo-extras' ),
			'flagged'   => __( 'Page quality flagged', 'aw-seo-extras' ),
			'ai'        => __( 'AI referrals', 'aw-seo-extras' ),
			'conflicts' => __( 'With conflicts', 'aw-seo-extras' ),
			'noted'     => __( 'With Clarity notes', 'aw-seo-extras' ),
			/* translators: %d: score limit, e.g. 50. */
			'lowseo'    => sprintf( __( 'Rank Math score under %d', 'aw-seo-extras' ), AW_SI_RM_LOW ),
		);
		$current = $this->arg( 'view' ) ? $this->arg( 'view' ) : 'all';
		$out     = array();
		foreach ( $this->view_tests() as $key => $test ) {
			$n           = count( array_filter( $this->all(), $test ) );
			$out[ $key ] = '<a href="' . esc_url( add_query_arg( 'view', $key, aw_si_url( 'pages' ) ) ) . '"' . ( $key === $current ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $labels[ $key ] ) . ' <span class="count">(' . esc_html( number_format_i18n( $n ) ) . ')</span></a>';
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
			$rows = array_filter( $rows, fn( $r ) => false !== stripos( $r['path'], $search ) );
		}
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'impr'; // phpcs:ignore WordPress.Security.NonceVerification -- read-only sort.
		$allowed = array_column( $this->get_sortable_columns(), 0 );
		$orderby = in_array( $orderby, $allowed, true ) ? $orderby : 'impr';
		$asc     = 'asc' === $this->arg( 'order' );
		usort(
			$rows,
			function ( $a, $b ) use ( $orderby, $asc ) {
				$x = $a[ $orderby ] ?? null;
				$y = $b[ $orderby ] ?? null;
				if ( null === $x || null === $y ) {
					return ( null === $x ) <=> ( null === $y ); // Empty values last, either way.
				}
				$c = is_string( $x ) ? strcmp( $x, $y ) : $x <=> $y;
				return $asc ? $c : -$c;
			}
		);
		$per                   = $this->get_items_per_page( 'aw_si_pages_per_page', 50 );
		$paged                 = max( 1, $this->get_pagenum() );
		$this->items           = array_slice( $rows, ( $paged - 1 ) * $per, $per );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'path' );
		$this->set_pagination_args(
			array(
				'total_items' => count( $rows ),
				'per_page'    => $per,
			)
		);
	}

	/** Message for an empty list. */
	public function no_items() {
		esc_html_e( 'No pages yet: they appear after the daily runs of Search Console, GA4, Clarity and PageSpeed.', 'aw-seo-extras' );
	}

	/**
	 * Page link, flag and the note row action.
	 *
	 * @param array $item Row.
	 */
	protected function column_path( $item ) {
		$out = '<a href="' . esc_url( home_url( $item['path'] ) ) . '" target="_blank" rel="noopener"><strong>' . esc_html( $item['path'] ) . '</strong></a>';
		if ( ! empty( $item['flagged'] ) ) {
			$why = array();
			if ( ! empty( $item['c_flag'] ) ) {
				$why[] = __( 'Clarity', 'aw-seo-extras' );
			}
			if ( ! empty( $item['psi_flag'] ) ) {
				$why[] = __( 'PageSpeed', 'aw-seo-extras' );
			}
			/* translators: %s: Clarity and/or PageSpeed. */
			$out .= ' — <span class="post-state"><span class="dashicons dashicons-flag" aria-hidden="true"></span> ' . esc_html( sprintf( __( 'Page quality (%s)', 'aw-seo-extras' ), implode( ', ', $why ) ) ) . '</span>';
		}
		$actions = array(
			'note' => '<a href="' . esc_url( add_query_arg( 'note', rawurlencode( $item['path'] ), aw_si_url( 'pages' ) ) ) . '">' . esc_html__( 'Add Clarity note', 'aw-seo-extras' ) . '</a>',
		);
		return $out . $this->row_actions( $actions );
	}

	/**
	 * Keywords owned, and conflicts.
	 *
	 * @param array $item Row.
	 */
	protected function column_keywords( $item ) {
		if ( empty( $item['keywords'] ) ) {
			return '–';
		}
		$out = esc_html( number_format_i18n( (int) $item['keywords'] ) );
		if ( ! empty( $item['conflicts'] ) ) {
			/* translators: %s: number of conflicts. */
			$out .= '<br><span class="description">' . esc_html( sprintf( _n( '%s conflict', '%s conflicts', (int) $item['conflicts'], 'aw-seo-extras' ), number_format_i18n( (int) $item['conflicts'] ) ) ) . '</span>';
		}
		return $out;
	}

	/**
	 * AI referrals and the source that sent most.
	 *
	 * @param array $item Row.
	 */
	protected function column_ai( $item ) {
		if ( empty( $item['ai'] ) ) {
			return '–';
		}
		$by = (array) ( $item['ai_by'] ?? array() );
		arsort( $by );
		return esc_html( number_format_i18n( (int) $item['ai'] ) ) . '<br><span class="description">' . esc_html( implode( ', ', array_slice( array_keys( $by ), 0, 2 ) ) ) . '</span>';
	}

	/**
	 * Clarity: sessions and the shares of sessions with rage clicks, dead clicks and quick-backs.
	 *
	 * @param array $item Row.
	 */
	protected function column_clarity( $item ) {
		if ( ! isset( $item['c_sessions'] ) ) {
			return '–';
		}
		/* translators: %s: sessions. */
		$parts = array( sprintf( __( '%s sessions', 'aw-seo-extras' ), number_format_i18n( (int) $item['c_sessions'] ) ) );
		foreach ( array(
			'c_rage'      => __( 'rage', 'aw-seo-extras' ),
			'c_dead'      => __( 'dead', 'aw-seo-extras' ),
			'c_quickback' => __( 'quick-back', 'aw-seo-extras' ),
			'c_scroll'    => __( 'scroll', 'aw-seo-extras' ),
		) as $k => $label ) {
			if ( isset( $item[ $k ] ) ) {
				/* translators: 1: rage, dead, quick-back or scroll; 2: share of sessions in per cent. */
				$parts[] = sprintf( __( '%1$s %2$s%%', 'aw-seo-extras' ), $label, number_format_i18n( (float) $item[ $k ], 1 ) );
			}
		}
		return esc_html( array_shift( $parts ) ) . ( $parts ? '<br><span class="description">' . esc_html( implode( ' · ', $parts ) ) . '</span>' : '' );
	}

	/**
	 * Latest PageSpeed scores and their date.
	 *
	 * @param array $item Row.
	 */
	protected function column_psi( $item ) {
		if ( ! isset( $item['psi_score-mobile'] ) && ! isset( $item['psi_score-desktop'] ) ) {
			return '–';
		}
		$m = isset( $item['psi_score-mobile'] ) ? number_format_i18n( (float) $item['psi_score-mobile'] ) : '–';
		$d = isset( $item['psi_score-desktop'] ) ? number_format_i18n( (float) $item['psi_score-desktop'] ) : '–';
		return esc_html( $m . ' / ' . $d ) . ( ! empty( $item['psi_d'] ) ? '<br><span class="description">' . esc_html( mysql2date( 'j M', $item['psi_d'] ) ) . '</span>' : '' );
	}

	/**
	 * Latest Clarity note and its date.
	 *
	 * @param array $item Row.
	 */
	protected function column_note( $item ) {
		if ( empty( $item['note'] ) ) {
			return '–';
		}
		return esc_html( $item['note'] ) . '<br><span class="description">' . esc_html( mysql2date( 'j M Y', $item['note_d'] ) ) . '</span>';
	}

	/**
	 * Numeric columns.
	 *
	 * @param array  $item Row.
	 * @param string $col  Column key.
	 */
	protected function column_default( $item, $col ) {
		if ( ! isset( $item[ $col ] ) || null === $item[ $col ] ) {
			return '–';
		}
		return esc_html( 'pos' === $col ? number_format_i18n( (float) $item[ $col ], 1 ) : number_format_i18n( (int) $item[ $col ] ) );
	}
}
