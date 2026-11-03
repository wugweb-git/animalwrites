<?php
/**
 * Server tab list table: one row per stored day (aw_si_srv_days() in server.php), newest first, paged. Read only.
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
 * The Server tab list table.
 */
class AW_SI_Server_Table extends WP_List_Table {

	/**
	 * Stored days, Ymd => day.
	 *
	 * @var array
	 */
	private $days;

	/**
	 * One row per day.
	 *
	 * @param array $days Ymd => day, newest first.
	 */
	public function __construct( array $days ) {
		$this->days = $days;
		parent::__construct(
			array(
				'singular' => 'server-day',
				'plural'   => 'server-days',
				'ajax'     => false,
			)
		);
	}

	/** Columns. */
	public function get_columns() {
		return array(
			'day'  => __( 'Day', 'aw-seo-extras' ),
			'bots' => __( 'Crawler requests', 'aw-seo-extras' ),
			'c4'   => __( '4xx', 'aw-seo-extras' ),
			'u4'   => __( 'Top 4xx addresses', 'aw-seo-extras' ),
			'c5'   => __( '5xx', 'aw-seo-extras' ),
			'u5'   => __( 'Top 5xx addresses', 'aw-seo-extras' ),
			'php'  => __( 'PHP errors', 'aw-seo-extras' ),
		);
	}

	/** Rows for this page. */
	public function prepare_items() {
		$per = 30;
		$all = array();
		foreach ( $this->days as $ymd => $d ) {
			$all[] = array( 'ymd' => (string) $ymd ) + (array) $d;
		}
		$page                  = $this->get_pagenum();
		$this->items           = array_slice( $all, ( $page - 1 ) * $per, $per );
		$this->_column_headers = array( $this->get_columns(), array(), array() );
		$this->set_pagination_args(
			array(
				'total_items' => count( $all ),
				'per_page'    => $per,
			)
		);
	}

	/** Shown when nothing is stored yet. */
	public function no_items() {
		esc_html_e( 'Nothing counted yet. Counting starts with the first crawler request or error after this update.', 'aw-seo-extras' );
	}

	/**
	 * Cells.
	 *
	 * @param array  $item   Row.
	 * @param string $column Column.
	 */
	public function column_default( $item, $column ) {
		switch ( $column ) {
			case 'day':
				$t = strtotime( (string) $item['ymd'] );
				return esc_html( $t ? wp_date( 'j M Y', $t ) : (string) $item['ymd'] );
			case 'bots':
				$bots = (array) ( $item['bots'] ?? array() );
				return $bots ? esc_html( number_format_i18n( array_sum( $bots ) ) . ': ' . aw_si_srv_list( $bots, 6 ) ) : '0';
			case 'c4':
			case 'c5':
				return esc_html( number_format_i18n( (int) ( $item['codes'][ 'c4' === $column ? '4xx' : '5xx' ] ?? 0 ) ) );
			case 'u4':
			case 'u5':
				return esc_html( aw_si_srv_list( (array) ( $item['urls'][ 'u4' === $column ? '4xx' : '5xx' ] ?? array() ), 5 ) );
			case 'php':
				$php = (array) ( $item['php'] ?? array() );
				if ( ! $php ) {
					return esc_html__( 'Read the next morning', 'aw-seo-extras' );
				}
				if ( 'ok' !== ( $php['status'] ?? '' ) ) {
					return esc_html__( 'Log not found', 'aw-seo-extras' );
				}
				$kinds = (array) ( $php['kinds'] ?? array() );
				$out   = esc_html( $kinds ? number_format_i18n( array_sum( $kinds ) ) . ': ' . aw_si_srv_list( $kinds, 6 ) : '0' );
				foreach ( array_slice( (array) ( $php['top'] ?? array() ), 0, 3, true ) as $msg => $n ) {
					$out .= '<br><span class="description">' . esc_html( $n . ' × ' . $msg ) . '</span>';
				}
				return $out;
		}
		return '';
	}
}
