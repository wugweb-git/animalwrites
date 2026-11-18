<?php
/**
 * The configured feeds as a core WP_List_Table: one row per feed URL with its host, whether that host is a
 * disclosed-and-enabled external service, and the result of its last fetch. Each row offers "Fetch now".
 * Read-only — adding and removing feeds stays on the Settings screen. Core admin chrome only.
 *
 * @package sourceweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Lists the configured feeds and their last-run status.
 */
class SourceWeb_Feeds_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'sourceweb_feed',
				'plural'   => 'sourceweb_feeds',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns.
	 *
	 * @return array<string,string>
	 */
	public function get_columns() {
		return array(
			'feed'      => __( 'Feed', 'sourceweb' ),
			'host'      => __( 'Host', 'sourceweb' ),
			'disclosed' => __( 'External service', 'sourceweb' ),
			'last_run'  => __( 'Last fetch', 'sourceweb' ),
		);
	}

	/**
	 * Empty state.
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'No feeds configured yet. Add feed URLs under Settings → Feeds.', 'sourceweb' );
	}

	/**
	 * Feed column (URL, with a Fetch-now row action).
	 *
	 * @param array $item Row.
	 * @return string
	 */
	public function column_feed( $item ) {
		$fetch   = wp_nonce_url(
			add_query_arg(
				array(
					'page'   => 'sourceweb-feeds',
					'action' => 'fetch',
					'feed'   => (int) $item['index'],
				),
				admin_url( 'admin.php' )
			),
			'sourceweb-feed_' . (int) $item['index']
		);
		$url     = (string) $item['url'];
		$display = strlen( $url ) > 70 ? substr( $url, 0, 69 ) . '…' : $url;
		$cell    = '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer"><code>' . esc_html( $display ) . '</code></a>';
		$actions = array(
			'fetch' => '<a href="' . esc_url( $fetch ) . '">' . esc_html__( 'Fetch now', 'sourceweb' ) . '</a>',
		);
		return $cell . $this->row_actions( $actions );
	}

	/**
	 * Host column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	public function column_host( $item ) {
		return esc_html( (string) $item['host'] );
	}

	/**
	 * Disclosure column (is the host a registered, enabled external service?).
	 *
	 * @param array $item Row.
	 * @return string
	 */
	public function column_disclosed( $item ) {
		return $item['disclosed']
			? esc_html__( 'Disclosed & enabled', 'sourceweb' )
			: '<strong>' . esc_html__( 'Not enabled — cannot be fetched', 'sourceweb' ) . '</strong>';
	}

	/**
	 * Last-run column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	public function column_last_run( $item ) {
		$run = $item['run'];
		if ( ! $run ) {
			return esc_html__( 'Never', 'sourceweb' );
		}
		if ( '' !== (string) $run['error'] ) {
			return '<strong>' . esc_html(
				sprintf(
					/* translators: 1: how long ago, 2: error message. */
					__( '%1$s ago — error: %2$s', 'sourceweb' ),
					human_time_diff( (int) $run['time'], time() ),
					$run['error']
				)
			) . '</strong>';
		}
		return esc_html(
			sprintf(
				/* translators: 1: how long ago, 2: items fetched, 3: items queued. */
				__( '%1$s ago — %2$d fetched, %3$d queued', 'sourceweb' ),
				human_time_diff( (int) $run['time'], time() ),
				(int) $run['fetched'],
				(int) $run['queued']
			)
		);
	}

	/**
	 * Fallback column renderer.
	 *
	 * @param array  $item        Row.
	 * @param string $column_name Column.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		return '';
	}

	/**
	 * Build the rows from the configured feeds, the external-services registry and the stored last-run results.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), array() );
		$runs                  = sourceweb_feed_runs();
		$rows                  = array();
		$index                 = 0;
		foreach ( sourceweb_feeds() as $url ) {
			$host    = sourceweb_service_host( $url );
			$service = sourceweb_external_service_for_host( $host );
			$rows[]  = array(
				'index'     => $index,
				'url'       => $url,
				'host'      => $host,
				'disclosed' => ( $service && ! empty( $service['enabled'] ) ),
				'run'       => isset( $runs[ $url ] ) ? $runs[ $url ] : null,
			);
			++$index;
		}
		$this->items = $rows;
	}
}
