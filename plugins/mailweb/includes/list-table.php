<?php
/**
 * The Mail list as a WordPress list table (checkboxes, bulk actions, row actions, search, pagination), for one mailbox,
 * all mailboxes mixed (newest first, with a Mailbox column), or the Website forms folder.
 *
 * @package mailweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Mailweb_List_Table extends WP_List_Table {

	/** @var array Screen state (mailweb_state()). */
	private $s;

	/** @var bool The mailbox could not be read. */
	private $failed = false;

	public function __construct( $s ) {
		$this->s = $s;
		parent::__construct( array( 'singular' => 'message', 'plural' => 'messages', 'ajax' => false, 'screen' => 'toplevel_page_mailweb' ) );
	}

	public function get_columns() {
		if ( MAILWEB_FORMS === $this->s['folder'] ) {
			return array( 'cb' => '<input type="checkbox">', 'from' => __( 'From', 'mailweb' ), 'subject' => __( 'About', 'mailweb' ), 'date' => __( 'Date', 'mailweb' ) );
		}
		$c = array( 'cb' => '<input type="checkbox">', 'from' => 'sent' === $this->s['folder'] ? __( 'To', 'mailweb' ) : __( 'From', 'mailweb' ), 'subject' => __( 'Subject', 'mailweb' ) );
		if ( 'all' === $this->s['box'] ) {
			$c['mailbox'] = __( 'Mailbox', 'mailweb' );
		}
		$c['date'] = __( 'Date', 'mailweb' );
		return $c;
	}

	protected function get_bulk_actions() {
		return mailweb_bulk_actions( $this->s['folder'] );
	}

	public function no_items() {
		if ( $this->failed ) {
			esc_html_e( 'The mailbox could not be read just now. Try again in a minute.', 'mailweb' );
		} else {
			echo '' !== $this->s['q'] ? esc_html__( 'Nothing matches your search.', 'mailweb' ) : esc_html__( 'No messages.', 'mailweb' );
		}
	}

	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), array(), 'subject' );
		$res                   = mailweb_list_rows( $this->s );
		$this->items           = $res['items'];
		$this->failed          = $res['failed'];
		$this->set_pagination_args( array( 'total_items' => $res['total'], 'per_page' => MAILWEB_PER_PAGE ) );
	}

	/** Unread rows carry the class "unread". */
	public function single_row( $item ) {
		echo '<tr' . ( $item['unseen'] ? ' class="unread"' : '' ) . '>';
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	private function open_url( $item ) {
		if ( isset( $item['item'] ) ) {
			return add_query_arg( array( 'page' => 'mailweb', 'box' => $this->s['box'], 'folder' => MAILWEB_FORMS, 'item' => $item['item'] ), admin_url( 'admin.php' ) );
		}
		return add_query_arg( array( 'page' => 'mailweb', 'box' => $item['box'], 'folder' => $this->s['folder'], 'uid' => $item['uid'], 'view' => $this->s['box'] ), admin_url( 'admin.php' ) );
	}

	public function column_cb( $item ) {
		return '<label class="screen-reader-text" for="awm-cb-' . esc_attr( md5( $item['id'] ) ) . '">' . esc_html__( 'Select', 'mailweb' ) . '</label><input type="checkbox" id="awm-cb-' . esc_attr( md5( $item['id'] ) ) . '" name="message[]" value="' . esc_attr( $item['id'] ) . '">';
	}

	public function column_from( $item ) {
		$who = '' !== $item['from'] ? $item['from'] : __( '(unknown)', 'mailweb' );
		return ( $item['unseen'] ? '<span class="screen-reader-text">' . esc_html__( 'Unread:', 'mailweb' ) . ' </span>' : '' ) . esc_html( $who ) . ( '' !== $item['email'] && $item['email'] !== $item['from'] ? '<br><span class="description">' . esc_html( $item['email'] ) . '</span>' : '' );
	}

	public function column_subject( $item ) {
		$open = $this->open_url( $item );
		$out  = '<a class="row-title" href="' . esc_url( $open ) . '">' . esc_html( '' !== $item['subject'] ? $item['subject'] : __( '(no subject)', 'mailweb' ) ) . '</a>';
		if ( $item['files'] ) {
			$out .= ' <span class="dashicons dashicons-paperclip" style="font-size:16px;color:#646970" aria-label="' . esc_attr__( 'Has attachments', 'mailweb' ) . '"></span>';
		}
		$acts = array();
		if ( isset( $item['item'] ) ) {
			if ( is_email( $item['email'] ) ) {
				$acts['reply'] = '<a href="' . esc_url( add_query_arg( 'compose', 'reply', $open ) ) . '">' . esc_html__( 'Reply', 'mailweb' ) . '</a>';
			}
			$acts['read']  = '<a href="' . esc_url( mailweb_act_url( $item['unseen'] ? 'formread' : 'formunread', array( 'item' => $item['item'] ) ) ) . '">' . ( $item['unseen'] ? esc_html__( 'Mark read', 'mailweb' ) : esc_html__( 'Mark unread', 'mailweb' ) ) . '</a>';
			$acts['trash'] = '<a class="submitdelete" href="' . esc_url( mailweb_act_url( 'formtrash', array( 'item' => $item['item'] ) ) ) . '">' . esc_html__( 'Trash', 'mailweb' ) . '</a>';
		} else {
			$u             = array( 'box' => $item['box'], 'folder' => mailweb_role_path( $this->s['folder'] ), 'uid' => $item['uid'], 'view' => $this->s['box'] );
			$acts['reply'] = '<a href="' . esc_url( add_query_arg( 'compose', 'reply', $open ) ) . '">' . esc_html__( 'Reply', 'mailweb' ) . '</a>';
			if ( ! in_array( $this->s['folder'], array( 'archive', 'trash' ), true ) ) {
				$acts['archive'] = '<a href="' . esc_url( mailweb_act_url( 'archive', $u ) ) . '">' . esc_html__( 'Archive', 'mailweb' ) . '</a>';
			}
			$acts['read'] = '<a href="' . esc_url( mailweb_act_url( $item['unseen'] ? 'read' : 'unread', $u ) ) . '">' . ( $item['unseen'] ? esc_html__( 'Mark read', 'mailweb' ) : esc_html__( 'Mark unread', 'mailweb' ) ) . '</a>';
			if ( 'trash' !== $this->s['folder'] ) {
				$acts['trash'] = '<a class="submitdelete" href="' . esc_url( mailweb_act_url( 'trash', $u ) ) . '">' . esc_html__( 'Trash', 'mailweb' ) . '</a>';
			}
		}
		return $out . $this->row_actions( $acts );
	}

	public function column_mailbox( $item ) {
		return esc_html( strtok( (string) $item['mailbox'], '@' ) . '@' );
	}

	public function column_date( $item ) {
		return mailweb_day( $item['date'] );
	}
}
