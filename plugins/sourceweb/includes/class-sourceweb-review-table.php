<?php
/**
 * The review queue as a core WP_List_Table: sortable columns, a search box, pagination, per-row Approve /
 * Reject / View actions and Approve / Reject bulk actions. Core admin chrome only — no custom styling.
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
 * Lists pending candidate items.
 */
class SourceWeb_Review_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'sourceweb_item',
				'plural'   => 'sourceweb_items',
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
			'cb'      => '<input type="checkbox" />',
			'title'   => __( 'Title', 'sourceweb' ),
			'source'  => __( 'Source', 'sourceweb' ),
			'summary' => __( 'Summary', 'sourceweb' ),
			'added'   => __( 'Added', 'sourceweb' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array<string,array>
	 */
	public function get_sortable_columns() {
		return array(
			'title' => array( 'title', false ),
			'added' => array( 'date', true ),
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @return array<string,string>
	 */
	public function get_bulk_actions() {
		return array(
			'bulk-approve' => __( 'Approve', 'sourceweb' ),
			'bulk-reject'  => __( 'Reject', 'sourceweb' ),
		);
	}

	/**
	 * Empty state.
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'Nothing is waiting for review. Configure feeds under Settings, then use “Fetch feeds now”.', 'sourceweb' );
	}

	/**
	 * Checkbox column.
	 *
	 * @param WP_Post $item Item.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="item[]" value="%d" />', (int) $item->ID );
	}

	/**
	 * Title column, with row actions.
	 *
	 * @param WP_Post $item Item.
	 * @return string
	 */
	public function column_title( $item ) {
		$id      = (int) $item->ID;
		$view    = add_query_arg(
			array(
				'page'   => 'sourceweb-review',
				'action' => 'view',
				'item'   => $id,
			),
			admin_url( 'admin.php' )
		);
		$approve = wp_nonce_url(
			add_query_arg(
				array(
					'page'   => 'sourceweb-review',
					'action' => 'approve',
					'item'   => $id,
				),
				admin_url( 'admin.php' )
			),
			'sourceweb-item_' . $id
		);
		$reject  = wp_nonce_url(
			add_query_arg(
				array(
					'page'   => 'sourceweb-review',
					'action' => 'reject',
					'item'   => $id,
				),
				admin_url( 'admin.php' )
			),
			'sourceweb-item_' . $id
		);

		$title   = '<strong><a href="' . esc_url( $view ) . '">' . esc_html( $item->post_title ) . '</a></strong>';
		$actions = array(
			'view'    => '<a href="' . esc_url( $view ) . '">' . esc_html__( 'View', 'sourceweb' ) . '</a>',
			'approve' => '<a href="' . esc_url( $approve ) . '">' . esc_html__( 'Approve', 'sourceweb' ) . '</a>',
			'reject'  => '<a href="' . esc_url( $reject ) . '" class="submitdelete">' . esc_html__( 'Reject', 'sourceweb' ) . '</a>',
		);
		return $title . $this->row_actions( $actions );
	}

	/**
	 * Source column (linked host / name).
	 *
	 * @param WP_Post $item Item.
	 * @return string
	 */
	public function column_source( $item ) {
		$url  = (string) get_post_meta( $item->ID, '_sourceweb_source_url', true );
		$name = (string) get_post_meta( $item->ID, '_sourceweb_source_name', true );
		$text = '' !== $name ? $name : ( '' !== $url ? sourceweb_service_host( $url ) : '' );
		if ( '' === $url ) {
			return esc_html( $text );
		}
		return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( '' !== $text ? $text : $url ) . '</a>';
	}

	/**
	 * Summary column (excerpt).
	 *
	 * @param WP_Post $item Item.
	 * @return string
	 */
	public function column_summary( $item ) {
		return esc_html( wp_trim_words( (string) $item->post_content, 24, '…' ) );
	}

	/**
	 * Added column (date).
	 *
	 * @param WP_Post $item Item.
	 * @return string
	 */
	public function column_added( $item ) {
		return esc_html( get_the_date( '', $item ) );
	}

	/**
	 * Fallback column renderer.
	 *
	 * @param WP_Post $item        Item.
	 * @param string  $column_name Column.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		return '';
	}

	/**
	 * Query, sort, search and paginate the pending items.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$per_page              = 20;
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- list-table display parameters; the state-changing bulk action is nonce-checked in process_bulk_action().
		$search  = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'date';
		$order   = isset( $_REQUEST['order'] ) && 'asc' === strtolower( sanitize_key( wp_unslash( $_REQUEST['order'] ) ) ) ? 'ASC' : 'DESC';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$orderby = in_array( $orderby, array( 'title', 'date' ), true ) ? $orderby : 'date';

		$query = new WP_Query(
			array(
				'post_type'        => 'sourceweb_item',
				'post_status'      => 'pending',
				's'                => $search,
				'orderby'          => $orderby,
				'order'            => $order,
				'posts_per_page'   => $per_page,
				'paged'            => $this->get_pagenum(),
				'suppress_filters' => false,
			)
		);

		$this->items = $query->posts;
		$this->set_pagination_args(
			array(
				'total_items' => (int) $query->found_posts,
				'per_page'    => $per_page,
			)
		);
	}
}
