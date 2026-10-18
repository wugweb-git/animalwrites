<?php
/**
 * The Mail list as a WordPress list table (checkboxes, bulk actions, row actions, search, pagination), for one mailbox,
 * all mailboxes mixed (newest first, with a Mailbox column), or the Website forms folder.
 *
 * @package aw-mail
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class AW_Mail_List_Table extends WP_List_Table {

	/** @var array Screen state (aw_mail_state()). */
	private $s;

	/** @var bool The mailbox could not be read. */
	private $failed = false;

	public function __construct( $s ) {
		$this->s = $s;
		parent::__construct( array( 'singular' => 'message', 'plural' => 'messages', 'ajax' => false, 'screen' => 'toplevel_page_aw-mail' ) );
	}

	public function get_columns() {
		if ( AW_MAIL_FORMS === $this->s['folder'] ) {
			return array( 'cb' => '<input type="checkbox">', 'from' => 'From', 'subject' => 'About', 'date' => 'Date' );
		}
		$c = array( 'cb' => '<input type="checkbox">', 'from' => 'sent' === $this->s['folder'] ? 'To' : 'From', 'subject' => 'Subject' );
		if ( 'all' === $this->s['box'] ) {
			$c['mailbox'] = 'Mailbox';
		}
		$c['date'] = 'Date';
		return $c;
	}

	protected function get_bulk_actions() {
		if ( AW_MAIL_FORMS === $this->s['folder'] ) {
			return array( 'read' => 'Mark read', 'unread' => 'Mark unread', 'trash' => 'Move to Trash' );
		}
		$a = array( 'read' => 'Mark read', 'unread' => 'Mark unread' );
		if ( ! in_array( $this->s['folder'], array( 'archive', 'trash' ), true ) ) {
			$a['archive'] = 'Archive';
		}
		if ( 'inbox' !== $this->s['folder'] ) {
			$a['inbox'] = 'spam' === $this->s['folder'] ? 'Not spam' : 'Move to Inbox';
		}
		if ( ! in_array( $this->s['folder'], array( 'spam', 'sent' ), true ) ) {
			$a['spam'] = 'Spam';
		}
		if ( 'trash' !== $this->s['folder'] ) {
			$a['trash'] = 'Move to Trash';
		}
		return $a;
	}

	public function no_items() {
		if ( $this->failed ) {
			echo 'The mailbox could not be read just now. Try again in a minute.';
		} else {
			echo '' !== $this->s['q'] ? 'Nothing matches your search.' : 'No messages.';
		}
	}

	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), array(), 'subject' );
		$per                   = AW_MAIL_PER_PAGE;
		$page                  = $this->s['page'];
		if ( AW_MAIL_FORMS === $this->s['folder'] ) {
			$q           = aw_mail_forms_query( $page );
			$this->items = array();
			foreach ( $q->posts as $p ) {
				$name          = trim( (string) get_post_meta( $p->ID, 'aw_m_name', true ) );
				$email         = trim( (string) get_post_meta( $p->ID, 'aw_m_email', true ) );
				$this->items[] = array(
					'id'      => 'form|' . $p->ID,
					'item'    => $p->ID,
					'from'    => '' !== $name ? $name : $email,
					'email'   => $email,
					'subject' => trim( preg_replace( '/^Contact form:\s*/', '', $p->post_title ) ),
					'date'    => $p->post_date,
					'unseen'  => '' === (string) get_post_meta( $p->ID, 'aw_read', true ),
					'files'   => false,
				);
			}
			$this->set_pagination_args( array( 'total_items' => (int) $q->found_posts, 'per_page' => $per ) );
			return;
		}
		$path  = aw_mail_role_path( $this->s['folder'] );
		$rows  = array();
		$total = 0;
		$boxes = aw_mail_view_boxes( $this->s['box'] );
		$mixed = count( $boxes ) > 1;
		// Mixed: each mailbox's newest page*per (up to 100), merged by date; one mailbox: its own page.
		$take = $mixed ? min( 100, $page * $per ) : $per;
		$ask  = $mixed ? 1 : $page;
		$okay = 0;
		foreach ( $boxes as $key => $addr ) {
			if ( ! isset( aw_mail_folders( $key )[ $path ] ) ) {
				continue; // this mailbox has no such folder (yet)
			}
			$qs = array( 'page' => $ask, 'perPage' => $take, 'sort' => '-date' );
			if ( '' !== $this->s['q'] ) {
				list( $code, $j ) = aw_mail_call( 'POST', aw_mail_fpath( $key, $path ) . '/messages/search?' . http_build_query( $qs ), array( 'text' => $this->s['q'] ) );
			} else {
				list( $code, $j ) = aw_mail_call( 'GET', aw_mail_fpath( $key, $path ) . '/messages?' . http_build_query( $qs ) );
			}
			if ( 200 !== $code ) {
				continue;
			}
			++$okay;
			$total += (int) ( $j['pagination']['total'] ?? 0 );
			foreach ( (array) ( $j['data'] ?? array() ) as $m ) {
				$sent   = 'sent' === $this->s['folder'];
				$rows[] = array(
					'id'      => $key . '|' . (int) $m['uid'],
					'box'     => $key,
					'uid'     => (int) $m['uid'],
					'from'    => $sent ? implode( ', ', array_map( fn( $a ) => aw_mail_who( $a, true ), (array) ( $m['to'] ?? array() ) ) ) : aw_mail_who( $m['from'] ?? array(), true ),
					'email'   => $sent ? '' : implode( '', aw_mail_addr_list( array( $m['from'] ?? array() ) ) ),
					'subject' => trim( (string) ( $m['subject'] ?? '' ) ),
					'date'    => (string) ( $m['date'] ?? '' ),
					'unseen'  => ! empty( $m['unseen'] ),
					'files'   => ! empty( $m['attachments'] ),
					'mailbox' => $addr,
				);
			}
		}
		$this->failed = ! $okay && $boxes;
		if ( $mixed ) {
			usort( $rows, fn( $a, $b ) => strcmp( $b['date'], $a['date'] ) );
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
			return add_query_arg( array( 'page' => 'aw-mail', 'box' => $this->s['box'], 'folder' => AW_MAIL_FORMS, 'item' => $item['item'] ), admin_url( 'admin.php' ) );
		}
		return add_query_arg( array( 'page' => 'aw-mail', 'box' => $item['box'], 'folder' => $this->s['folder'], 'uid' => $item['uid'], 'view' => $this->s['box'] ), admin_url( 'admin.php' ) );
	}

	public function column_cb( $item ) {
		return '<label class="screen-reader-text" for="awm-cb-' . esc_attr( md5( $item['id'] ) ) . '">Select</label><input type="checkbox" id="awm-cb-' . esc_attr( md5( $item['id'] ) ) . '" name="message[]" value="' . esc_attr( $item['id'] ) . '">';
	}

	public function column_from( $item ) {
		$who = '' !== $item['from'] ? $item['from'] : '(unknown)';
		return ( $item['unseen'] ? '<span class="screen-reader-text">Unread: </span>' : '' ) . esc_html( $who ) . ( '' !== $item['email'] && $item['email'] !== $item['from'] ? '<br><span class="description">' . esc_html( $item['email'] ) . '</span>' : '' );
	}

	public function column_subject( $item ) {
		$open = $this->open_url( $item );
		$out  = '<a class="row-title" href="' . esc_url( $open ) . '">' . esc_html( '' !== $item['subject'] ? $item['subject'] : '(no subject)' ) . '</a>';
		if ( $item['files'] ) {
			$out .= ' <span class="dashicons dashicons-paperclip" style="font-size:16px;color:#646970" aria-label="Has attachments"></span>';
		}
		$acts = array();
		if ( isset( $item['item'] ) ) {
			if ( is_email( $item['email'] ) ) {
				$acts['reply'] = '<a href="' . esc_url( add_query_arg( 'compose', 'reply', $open ) ) . '">Reply</a>';
			}
			$acts['read']  = '<a href="' . esc_url( aw_mail_act_url( $item['unseen'] ? 'formread' : 'formunread', array( 'item' => $item['item'] ) ) ) . '">' . ( $item['unseen'] ? 'Mark read' : 'Mark unread' ) . '</a>';
			$acts['trash'] = '<a class="submitdelete" href="' . esc_url( aw_mail_act_url( 'formtrash', array( 'item' => $item['item'] ) ) ) . '">Trash</a>';
		} else {
			$u             = array( 'box' => $item['box'], 'folder' => aw_mail_role_path( $this->s['folder'] ), 'uid' => $item['uid'], 'view' => $this->s['box'] );
			$acts['reply'] = '<a href="' . esc_url( add_query_arg( 'compose', 'reply', $open ) ) . '">Reply</a>';
			if ( ! in_array( $this->s['folder'], array( 'archive', 'trash' ), true ) ) {
				$acts['archive'] = '<a href="' . esc_url( aw_mail_act_url( 'archive', $u ) ) . '">Archive</a>';
			}
			$acts['read'] = '<a href="' . esc_url( aw_mail_act_url( $item['unseen'] ? 'read' : 'unread', $u ) ) . '">' . ( $item['unseen'] ? 'Mark read' : 'Mark unread' ) . '</a>';
			if ( 'trash' !== $this->s['folder'] ) {
				$acts['trash'] = '<a class="submitdelete" href="' . esc_url( aw_mail_act_url( 'trash', $u ) ) . '">Trash</a>';
			}
		}
		return $out . $this->row_actions( $acts );
	}

	public function column_mailbox( $item ) {
		return esc_html( strtok( (string) $item['mailbox'], '@' ) . '@' );
	}

	public function column_date( $item ) {
		return aw_mail_day( $item['date'] );
	}
}
