<?php
/**
 * Sourcing → Workers as a core list table (owner, 24 Oct 2026): one row per worker, the registry's and the
 * Planned ones. Views (All, each group, Late, Failing, Planned), search, sortable columns, pages (screen option
 * aw_workers_per_page), row actions Edit | Run now | View runs (| Delete for a Planned worker); a late or failing worker
 * gets the plugins list's problem row. It sits in a GET form (search and the page box), so it prints no nonce.
 * Loaded only on that screen.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The workers. */
class AW_Workers_Table extends WP_List_Table {

	/** @var array All rows before views, search and pages. */
	private $all = array();

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'worker',
				'plural'   => 'workers',
				'ajax'     => false,
				'screen'   => AW_SRC_SCREEN,
			)
		);
		$kinds = array();
		$notes = array();
		foreach ( aw_workers_problems() as $k => $msg ) {
			list( $file, $kind )              = explode( ':', $k, 2 );
			$kinds[ aw_worker_id( $file ) ][] = $kind;
			$notes[ aw_worker_id( $file ) ][] = $msg;
		}
		foreach ( aw_workers_all() as $id => $w ) {
			$last = aw_workers_last( $w['file'] );
			$k    = $kinds[ $id ] ?? array();
			if ( $w['planned'] && ! $last ) {
				$status = 'planned';
			} elseif ( array_intersect( $k, array( 'late', 'stuck' ) ) ) {
				$status = 'late';
			} elseif ( $k ) {
				$status = 'failing';
			} else {
				$status = 'active';
			}
			$this->all[] = $w + array(
				'last'   => $last,
				'status' => $status,
				'note'   => implode( ' ', $notes[ $id ] ?? array() ),
				'keys'   => aw_worker_key_count( $id ),
			);
		}
	}

	/** Status => [label, dashicon]. */
	const STATUS = array(
		'active'  => array( 'Active', 'yes-alt' ),
		'late'    => array( 'Late', 'clock' ),
		'failing' => array( 'Failing', 'dismiss' ),
		'planned' => array( 'Planned', 'lightbulb' ),
	);

	/** The current view: '', late, failing, planned or group-<slug>. */
	private function view() {
		$v = sanitize_key( $_GET['wview'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification -- a view in a link
		return isset( $this->views_list()[ $v ] ) ? $v : '';
	}

	/** View => [label, filter]. */
	private function views_list() {
		$out = array( '' => array( 'All', fn( $r ) => true ) );
		foreach ( aw_worker_groups() as $slug => $g ) {
			$out[ 'group-' . $slug ] = array( $g[0], fn( $r ) => $r['group'] === $slug );
		}
		$out['late']    = array( 'Late', fn( $r ) => 'late' === $r['status'] );
		$out['failing'] = array( 'Failing', fn( $r ) => 'failing' === $r['status'] );
		$out['planned'] = array( 'Planned', fn( $r ) => $r['planned'] );
		return $out;
	}

	protected function get_views() {
		$base = admin_url( 'admin.php?page=aw-sourcing&tab=workers' );
		$out  = array();
		foreach ( $this->views_list() as $k => $v ) {
			$n = count( array_filter( $this->all, $v[1] ) );
			if ( $n || '' === $k ) {
				$out[ '' === $k ? 'all' : $k ] = '<a href="' . esc_url( '' === $k ? $base : add_query_arg( 'wview', $k, $base ) ) . '"' . ( $this->view() === $k ? ' class="current" aria-current="page"' : '' ) . '>'
					. esc_html( $v[0] ) . ' <span class="count">(' . esc_html( number_format_i18n( $n ) ) . ')</span></a>';
			}
		}
		return $out;
	}

	public function get_columns() {
		return aw_workers_columns();
	}

	protected function get_sortable_columns() {
		return array(
			'name'     => array( 'name', false ),
			'group'    => array( 'group', false ),
			'last_run' => array( 'last_run', true ),
			'status'   => array( 'status', false ),
		);
	}

	protected function get_primary_column_name() {
		return 'name';
	}

	/** "plugins" so problem rows take the core plugins-list highlight; not "fixed", so the icon column stays narrow. */
	protected function get_table_classes() {
		return array( 'widefat', 'striped', 'plugins', $this->_args['plural'] );
	}

	public function prepare_items() {
		$filter = $this->views_list()[ $this->view() ][1];
		$s      = mb_strtolower( sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification -- a search in a GET form
		$groups = aw_worker_groups();
		$rows   = array_values(
			array_filter(
				$this->all,
				fn( $r ) => $filter( $r ) && ( '' === $s || false !== mb_strpos( mb_strtolower( $r['id'] . ' ' . $r['name'] . ' ' . $r['what'] . ' ' . $r['file'] . ' ' . ( $groups[ $r['group'] ][0] ?? '' ) ), $s ) )
			)
		);
		$by    = sanitize_key( $_GET['orderby'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification -- a sort in a link
		$desc  = 'desc' === sanitize_key( wp_unslash( $_GET['order'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- a sort in a link
		$order = array_flip( array_keys( self::STATUS ) );
		$keyfn = array(
			'name'     => fn( $r ) => strtolower( $r['name'] ),
			'group'    => fn( $r ) => strtolower( $groups[ $r['group'] ][0] ?? $r['group'] ) . ' ' . strtolower( $r['name'] ),
			'last_run' => fn( $r ) => $r['last'] ? (int) $r['last']['started'] : 0,
			'status'   => fn( $r ) => $order[ $r['status'] ],
		);
		if ( isset( $keyfn[ $by ] ) ) {
			usort( $rows, fn( $a, $b ) => ( $desc ? -1 : 1 ) * ( $keyfn[ $by ]( $a ) <=> $keyfn[ $by ]( $b ) ) );
		} else {
			// Late and failing first, then the registry's order, Planned last.
			usort( $rows, fn( $a, $b ) => (int) ! in_array( $a['status'], array( 'late', 'failing' ), true ) <=> (int) ! in_array( $b['status'], array( 'late', 'failing' ), true ) );
		}
		$per                   = $this->get_items_per_page( 'aw_workers_per_page', 20 );
		$this->_column_headers = array( $this->get_columns(), get_hidden_columns( $this->screen ), $this->get_sortable_columns(), 'name' );
		$this->set_pagination_args(
			array(
				'total_items' => count( $rows ),
				'per_page'    => $per,
			)
		);
		$this->items = array_slice( $rows, ( $this->get_pagenum() - 1 ) * $per, $per );
	}

	/** Pages only: no bulk actions, and the GET form must not carry a nonce in its address. */
	protected function display_tablenav( $which ) {
		echo '<div class="tablenav ' . esc_attr( $which ) . '">';
		$this->pagination( $which );
		echo '<br class="clear"></div>';
	}

	/** A late or failing worker: the plugins list's highlight and problem row. */
	public function single_row( $item ) {
		$problem = in_array( $item['status'], array( 'late', 'failing' ), true );
		echo '<tr' . ( $problem ? ' class="active update"' : '' ) . '>';
		$this->single_row_columns( $item );
		echo '</tr>';
		if ( $problem && '' !== $item['note'] ) {
			aw_sourcing_problem_row( $this, $item['note'], 'error' );
		}
	}

	protected function column_icon( $r ) {
		$g = aw_worker_groups()[ $r['group'] ] ?? array( '', 'admin-generic' );
		return '<span class="dashicons dashicons-' . esc_attr( $g[1] ) . '" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html( $g[0] ) . '</span>';
	}

	protected function column_name( $r ) {
		$base = admin_url( 'admin.php?page=aw-sourcing&tab=workers' );
		$acts = array( 'edit' => '<a href="' . esc_url( add_query_arg( 'edit', $r['id'], $base ) ) . '">Edit</a>' );
		if ( aw_workers_can_run( $r ) ) {
			$acts['run'] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=aw_worker_run&worker=' . rawurlencode( $r['id'] ) ), 'aw_worker_run_' . $r['id'] ) ) . '">Run now</a>';
		}
		$acts['runs'] = '<a href="' . esc_url( add_query_arg( 'runs', $r['id'], $base ) ) . '">View runs</a>';
		if ( $r['planned'] ) {
			$acts['delete'] = '<a class="submitdelete" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=aw_worker_delete&worker=' . rawurlencode( $r['id'] ) ), 'aw_worker_delete_' . $r['id'] ) ) . '" onclick="return confirm(' . esc_attr( wp_json_encode( 'Delete the planned worker "' . $r['name'] . '"?' ) ) . ')">Delete</a>';
		}
		return '<strong><a class="row-title" href="' . esc_url( add_query_arg( 'edit', $r['id'], $base ) ) . '">' . esc_html( $r['name'] ) . '</a></strong> <code>' . esc_html( $r['id'] ) . '</code>'
			. ( '' !== $r['what'] ? '<p class="description">' . esc_html( $r['what'] ) . '</p>' : '' )
			. $this->row_actions( $acts );
	}

	protected function column_group( $r ) {
		return esc_html( aw_worker_groups()[ $r['group'] ][0] ?? $r['group'] );
	}

	protected function column_file( $r ) {
		if ( $r['planned'] ) {
			return '<code>' . esc_html( $r['file'] ) . '</code><br><span class="description">Not built yet</span>';
		}
		return '<a href="' . esc_url( 'https://github.com/wugweb-git/animal-writes-workers/blob/main/.github/workflows/' . $r['file'] ) . '" target="_blank" rel="noopener"><code>' . esc_html( $r['file'] ) . '</code></a>';
	}

	protected function column_schedule( $r ) {
		if ( $r['planned'] ) {
			return esc_html( '' !== $r['schedule'] ? 'Wanted: ' . $r['schedule'] : '—' );
		}
		$late = aw_workers_late_words( $r );
		return esc_html( aw_workers_schedule( $r['registry'] + array( 'file' => $r['file'] ) ) ) . ( $late ? '<br><span class="description">' . esc_html( $late ) . '</span>' : '' );
	}

	protected function column_last_run( $r ) {
		$l = $r['last'];
		if ( ! $l ) {
			return $r['planned'] ? '—' : '<span class="dashicons dashicons-clock" aria-hidden="true"></span> No report yet';
		}
		$dur    = ! empty( $l['ended'] ) && ! empty( $l['started'] ) && $l['ended'] >= $l['started'] ? aw_workers_duration( $l['ended'] - $l['started'] ) : '';
		$counts = (array) ( $l['counts'] ?? array() );
		return aw_workers_badge( $l, false )
			. esc_html( wp_date( 'j M, g:i a', (int) $l['started'] ) . ( $dur ? ' · ' . $dur : '' ) )
			. ( $counts ? '<br><span class="description">' . esc_html( implode( ', ', array_map( fn( $k, $v ) => str_replace( '_', ' ', $k ) . ': ' . $v, array_keys( $counts ), $counts ) ) ) . '</span>' : '' );
	}

	protected function column_status( $r ) {
		list( $label, $icon ) = self::STATUS[ $r['status'] ];
		$note                 = 'planned' === $r['status'] ? 'Not built yet' : ( 'active' === $r['status'] && $r['planned'] ? 'Reported, not in the registry yet' : '' );
		return '<span class="dashicons dashicons-' . esc_attr( $icon ) . '" aria-hidden="true"></span> ' . esc_html( $label ) . ( $note ? '<br><span class="description">' . esc_html( $note ) . '</span>' : '' );
	}

	protected function column_keys( $r ) {
		list( $own, $all ) = $r['keys'];
		if ( ! $own ) {
			return 'All keys';
		}
		/* translators: %d: number of keys. */
		return esc_html( sprintf( _n( '%d key attached', '%d keys attached', $own ), $own ) ) . ( $all ? '<br><span class="description">' . esc_html( '+ ' . $all . ' for all workers' ) . '</span>' : '' );
	}

	public function no_items() {
		echo 'No workers found.';
	}
}
