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
		if ( MAILWEB_FORMS === $this->s['folder'] ) {
			return array( 'read' => __( 'Mark read', 'mailweb' ), 'unread' => __( 'Mark unread', 'mailweb' ), 'trash' => __( 'Move to Trash', 'mailweb' ) );
		}
		$a = array( 'read' => __( 'Mark read', 'mailweb' ), 'unread' => __( 'Mark unread', 'mailweb' ) );
		if ( ! in_array( $this->s['folder'], array( 'archive', 'trash' ), true ) ) {
			$a['archive'] = __( 'Archive', 'mailweb' );
		}
		if ( 'inbox' !== $this->s['folder'] ) {
			$a['inbox'] = 'spam' === $this->s['folder'] ? __( 'Not spam', 'mailweb' ) : __( 'Move to Inbox', 'mailweb' );
		}
		if ( ! in_array( $this->s['folder'], array( 'spam', 'sent' ), true ) ) {
			$a['spam'] = __( 'Spam', 'mailweb' );
		}
		if ( 'trash' !== $this->s['folder'] ) {
			$a['trash'] = __( 'Move to Trash', 'mailweb' );
		}
		return $a;
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
		$per                   = MAILWEB_PER_PAGE;
		$page                  = $this->s['page'];
		if ( MAILWEB_FORMS === $this->s['folder'] ) {
			$res         = mailweb_forms() ? (array) call_user_func( mailweb_forms()['list'], $page, $per ) : array();
			$this->items = array();
			foreach ( (array) ( $res['items'] ?? array() ) as $f ) {
				$name          = trim( (string) ( $f['name'] ?? '' ) );
				$email         = trim( (string) ( $f['email'] ?? '' ) );
				$this->items[] = array(
					'id'      => 'form|' . (int) $f['id'],
					'item'    => (int) $f['id'],
					'from'    => '' !== $name ? $name : $email,
					'email'   => $email,
					'subject' => (string) ( $f['subject'] ?? '' ),
					'date'    => (string) ( $f['date'] ?? '' ),
					'unseen'  => ! empty( $f['unread'] ),
					'files'   => false,
				);
			}
			$this->set_pagination_args( array( 'total_items' => (int) ( $res['total'] ?? 0 ), 'per_page' => $per ) );
			return;
		}
		$path  = mailweb_role_path( $this->s['folder'] );
		$rows  = array();
		$total = 0;
		$boxes = mailweb_view_boxes( $this->s['box'] );
		$mixed = count( $boxes ) > 1;
		// Mixed: each mailbox's newest page*per (at most $cap), read in API pages of up to 100, merged by date.
		// One mailbox: its own page.
		$cap  = 500;
		$need = $mixed ? min( $page * $per, $cap ) : $per;
		$size = $mixed ? min( 100, $need ) : $per;
		$asks = $mixed ? (int) ceil( $need / $size ) : 1;
		$okay = 0;
		$sent = 'sent' === $this->s['folder'];
		foreach ( $boxes as $key => $addr ) {
			if ( ! isset( mailweb_folders( $key )[ $path ] ) ) {
				continue; // this mailbox has no such folder (yet)
			}
			$ok  = false;
			$got = 0;
			$sum = 0;
			for ( $p = 1; $p <= $asks; $p++ ) {
				$qs = array( 'page' => $mixed ? $p : $page, 'perPage' => $size, 'sort' => '-date' );
				if ( '' !== $this->s['q'] ) {
					list( $code, $j ) = mailweb_call( 'POST', mailweb_fpath( $key, $path ) . '/messages/search?' . http_build_query( $qs ), array( 'text' => $this->s['q'] ) );
				} else {
					list( $code, $j ) = mailweb_call( 'GET', mailweb_fpath( $key, $path ) . '/messages?' . http_build_query( $qs ) );
				}
				if ( 200 !== $code ) {
					break;
				}
				$ok   = true;
				$sum  = (int) ( $j['pagination']['total'] ?? 0 );
				$data = (array) ( $j['data'] ?? array() );
				foreach ( $data as $m ) {
					$rows[] = array(
						'id'      => $key . '|' . (int) $m['uid'],
						'box'     => $key,
						'uid'     => (int) $m['uid'],
						'from'    => $sent ? implode( ', ', array_map( fn( $a ) => mailweb_who( $a, true ), (array) ( $m['to'] ?? array() ) ) ) : mailweb_who( $m['from'] ?? array(), true ),
						'email'   => $sent ? '' : implode( '', mailweb_addresses( array( $m['from'] ?? array() ) ) ),
						'subject' => trim( (string) ( $m['subject'] ?? '' ) ),
						'date'    => (string) ( $m['date'] ?? '' ),
						'unseen'  => ! empty( $m['unseen'] ),
						'files'   => ! empty( $m['attachments'] ),
						'mailbox' => $addr,
					);
				}
				$got += count( $data );
				if ( count( $data ) < $size || $got >= $sum ) {
					break; // this mailbox has no more
				}
			}
			if ( ! $ok ) {
				continue;
			}
			++$okay;
			// Mixed: count only what can be shown, so the pager offers no pages that cannot be filled.
			$total += $mixed ? min( $sum, $cap ) : $sum;
		}
		$this->failed = ! $okay && $boxes;
		if ( $mixed ) {
			usort( $rows, fn( $a, $b ) => (int) strtotime( $b['date'] ) <=> (int) strtotime( $a['date'] ) ); // newest first; unreadable dates last
			$rows = array_slice( $rows, ( $page - 1 ) * $per, $per );
		}
		$this->items = $rows;
		$this->set_pagination_args( array( 'total_items' => $total, 'per_page' => $per ) );
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
