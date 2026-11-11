<?php
/**
 * The Calendar's list of entries (core WP_List_Table): filters by Type, Channel, Owner and Status, from 30 days ago
 * by default (older dates one click away), 50 a page.
 *
 * @package planweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/** The Calendar's list of entries. */
class PlanWeb_List_Table extends WP_List_Table {

	/** Filters: query key => [taxonomy or meta, values]. */
	const FILTERS = array(
		'type'    => array( 'planweb_type', PLANWEB_TYPES ),
		'channel' => array( 'planweb_channel', PLANWEB_CHANNELS ),
		'owner'   => array( 'planweb_owner', PLANWEB_OWNERS ),
		'status'  => array( 'planweb_status', PLANWEB_STATUSES ),
	);

	/** Set up. */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'entry',
				'plural'   => 'entries',
				'ajax'     => false,
			)
		);
	}

	/**
	 * A filter's value from the address, if it is one of the list's values.
	 *
	 * @param string $key Filter key.
	 */
	private function filter( string $key ): string {
		$v = isset( $_GET[ $key ] ) ? sanitize_key( wp_unslash( $_GET[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a filter.
		return isset( self::FILTERS[ $key ][1][ $v ] ) ? $v : '';
	}

	/** Whether older dates are shown. */
	private function all_dates(): bool {
		return isset( $_GET['when'] ) && 'all' === sanitize_key( wp_unslash( $_GET['when'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a filter.
	}

	/** Columns. */
	public function get_columns() {
		return array(
			'date'    => __( 'Date', 'planweb' ),
			'title'   => __( 'What', 'planweb' ),
			'type'    => __( 'Type', 'planweb' ),
			'channel' => __( 'Channel', 'planweb' ),
			'owner'   => __( 'Owner', 'planweb' ),
			'status'  => __( 'Status', 'planweb' ),
			'link'    => __( 'Link', 'planweb' ),
		);
	}

	/** Load the page of entries. */
	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), array(), 'title' );
		$args                  = array();
		$tax                   = array();
		foreach ( array( 'type', 'channel', 'owner' ) as $k ) {
			if ( '' !== $this->filter( $k ) ) {
				$tax[] = array(
					'taxonomy' => self::FILTERS[ $k ][0],
					'field'    => 'slug',
					'terms'    => $this->filter( $k ),
				);
			}
		}
		if ( $tax ) {
			$args['tax_query'] = $tax; // phpcs:ignore WordPress.DB.SlowDBQuery -- a few hundred entries at most.
		}
		$from  = $this->all_dates() ? '' : wp_date( 'Y-m-d', time() - 30 * DAY_IN_SECONDS );
		$items = planweb_entries( $from, '', $args );
		if ( '' !== $this->filter( 'status' ) ) {
			$items = array_values( array_filter( $items, fn( $e ) => $e['status'] === $this->filter( 'status' ) ) );
		}
		$per  = 50;
		$page = $this->get_pagenum();
		$this->set_pagination_args(
			array(
				'total_items' => count( $items ),
				'per_page'    => $per,
			)
		);
		$this->items = array_slice( $items, ( $page - 1 ) * $per, $per );
	}

	/**
	 * Filters above the list.
	 *
	 * @param string $which top or bottom.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$all = array(
			'type'    => __( 'All types', 'planweb' ),
			'channel' => __( 'All channels', 'planweb' ),
			'owner'   => __( 'All owners', 'planweb' ),
			'status'  => __( 'All statuses', 'planweb' ),
		);
		echo '<div class="alignleft actions">';
		foreach ( $all as $k => $label ) {
			echo '<label class="screen-reader-text" for="planweb-filter-' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label><select name="' . esc_attr( $k ) . '" id="planweb-filter-' . esc_attr( $k ) . '"><option value="">' . esc_html( $label ) . '</option>';
			foreach ( self::FILTERS[ $k ][1] as $slug => $name ) {
				echo '<option value="' . esc_attr( $slug ) . '"' . selected( $this->filter( $k ), $slug, false ) . '>' . esc_html( $name ) . '</option>';
			}
			echo '</select>';
		}
		echo '<label class="screen-reader-text" for="planweb-filter-when">' . esc_html__( 'Dates', 'planweb' ) . '</label><select name="when" id="planweb-filter-when"><option value="">' . esc_html__( 'From 30 days ago', 'planweb' ) . '</option><option value="all"' . selected( $this->all_dates(), true, false ) . '>' . esc_html__( 'All dates', 'planweb' ) . '</option></select>';
		submit_button( __( 'Filter', 'planweb' ), '', 'filter_action', false );
		echo '</div>';
	}

	/** When nothing matches. */
	public function no_items() {
		esc_html_e( 'No entries.', 'planweb' );
	}

	/**
	 * Date and how far off it is; past dates greyed.
	 *
	 * @param array $e Entry.
	 */
	protected function column_date( $e ) {
		$past = $e['date'] < wp_date( 'Y-m-d' );
		return '<span' . ( $past ? ' class="planweb-past"' : '' ) . '>' . esc_html( planweb_date_label( $e['date'] ) . ( '' !== $e['time'] ? ', ' . $e['time'] : '' ) ) . '<br><span class="description">' . esc_html( planweb_when( $e['date'] ) ) . '</span></span>';
	}

	/**
	 * Title, note and the row actions.
	 *
	 * @param array $e Entry.
	 */
	protected function column_title( $e ) {
		$actions = array();
		if ( current_user_can( 'edit_post', $e['id'] ) ) {
			$actions['edit'] = '<a href="' . esc_url(
				planweb_url(
					array(
						'edit' => $e['id'],
						'm'    => substr( $e['date'], 0, 7 ),
					)
				) . '#planweb-form'
			) . '">' . esc_html__( 'Edit', 'planweb' ) . '</a>';
		}
		if ( current_user_can( 'delete_post', $e['id'] ) ) {
			$actions['trash'] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=planweb_trash&id=' . (int) $e['id'] ), 'planweb_trash_' . (int) $e['id'] ) ) . '">' . esc_html__( 'Trash', 'planweb' ) . '</a>';
		}
		$flag = 'overdue' === $e['flag'] ? ' <span class="planweb-flag">' . esc_html__( 'Overdue', 'planweb' ) . '</span>' : '';
		return '<strong>' . esc_html( $e['title'] ) . '</strong>' . $flag . ( '' !== $e['note'] ? '<br><span class="description">' . esc_html( $e['note'] ) . '</span>' : '' ) . $this->row_actions( $actions );
	}

	/**
	 * Type.
	 *
	 * @param array $e Entry.
	 */
	protected function column_type( $e ) {
		return planweb_type_badge( $e['type'] );
	}

	/**
	 * Status.
	 *
	 * @param array $e Entry.
	 */
	protected function column_status( $e ) {
		return planweb_status_badge( $e['status'] );
	}

	/**
	 * Link.
	 *
	 * @param array $e Entry.
	 */
	protected function column_link( $e ) {
		return '' === $e['link'] ? '—' : '<a href="' . esc_url( $e['link'] ) . '">' . esc_html( (string) wp_parse_url( $e['link'], PHP_URL_HOST ) ) . '</a>';
	}

	/**
	 * Channel and Owner.
	 *
	 * @param array  $e   Entry.
	 * @param string $col Column.
	 */
	protected function column_default( $e, $col ) {
		$list = 'channel' === $col ? PLANWEB_CHANNELS : PLANWEB_OWNERS;
		return esc_html( $list[ $e[ $col ] ] ?? '—' );
	}
}
