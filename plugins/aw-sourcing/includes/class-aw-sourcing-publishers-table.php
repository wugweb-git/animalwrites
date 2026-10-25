<?php
/**
 * Sourcing → Sources as a core list table (owner, 24 Oct 2026; was the Publishers tab): one row per host,
 * every publisher (aw_publishers records without kind) and every press-coverage site. Views by status and Press sites,
 * search, sortable columns, pagination with a screen option (aw_publishers_per_page), row actions (Edit, Allow,
 * Neutral, Block, Search here for coverage / Stop searching here) and the same as bulk actions. The short name is
 * edited in place and saved with "Save short names". Everything posts to admin-post.php (action aw_sourcing, tab
 * sources) with the screen's aw_sourcing nonce (the table prints none of its own). Loaded only when the tab is shown.
 *
 * A press site that is not a publisher shows "—" for status and gets no Allow / Neutral / Block: giving it a status
 * would create a publisher record, which only the Add New / Edit form does, on purpose.
 *
 * Sourcing restructure phase 1 (owner, 25 Oct 2026): Feeds and Relay moved in. Each row also counts its feeds and
 * sources read by code (aw_sources.sources by publisher host) and shows its relay switch with the last result. Rows
 * also cover a relay-only site (record kind=relay) and a site only feeds come from (no record). Views: With feeds,
 * Read by code, Relay, and the link to the Feeds screen (the Relay log is Sourcing → Relay log). Row actions: Relay on / off (once phase 1
 * is migrated) and Delete (a source with no items, feeds or press search; core's warning first).
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Publishers and press sites, one row per host. */
class AW_Sourcing_Publishers_Table extends WP_List_Table {

	/** The list's views (status= in the address). */
	const VIEWS = array( 'allow', 'neutral', 'block', 'press', 'feeds', 'code', 'relay' );

	/** @var array host => row */
	private $all = array();

	/** @var bool Search Console figures are there (a run within 28 days). */
	private $si = false;

	/** @var bool Search Console ran once, but not within 28 days. */
	private $stale = false;

	public function __construct() {
		parent::__construct( array( 'singular' => 'source', 'plural' => 'sources', 'ajax' => false, 'screen' => AW_SRC_SCREEN ) );
		$stats       = aw_sourcing_host_stats();
		$this->si    = ! empty( $stats['si'] );
		$this->stale = ! empty( $stats['stale'] );
		$meta        = get_option( AW_SOURCING_SITE_META, array() );
		$meta        = is_array( $meta ) ? $meta : array();
		$press       = array_flip( aw_sourcing_press_hosts() );
		$pubs        = aw_publishers();
		$feeds       = aw_sourcing_feeds_by_host();
		$relay       = aw_relay_hosts();
		$log         = aw_src_relay_index( (array) get_option( 'aw_relay_log', array() ) ); // newest row per host named
		// A host kept only for its icon (kind=directory-source) is not listed; a relay-only one (kind=relay) is.
		$hosts = array_keys( array_filter( $pubs, fn( $p ) => empty( $p['kind'] ) || 'relay' === $p['kind'] ) );
		foreach ( array_unique( array_merge( $hosts, array_keys( $press ), array_keys( $feeds ) ) ) as $h ) {
			if ( '' === (string) $h ) {
				continue;
			}
			$p   = $pubs[ $h ] ?? array();
			$pub = isset( $pubs[ $h ] ) && empty( $p['kind'] );
			$s   = $stats['hosts'][ $h ] ?? array();
			$m   = $meta[ $h ] ?? array();
			$fl  = $feeds[ $h ] ?? array();
			$rel = aw_src_host_in( $h, $relay );
			$this->all[ $h ] = array(
				'host'    => $h,
				'pub'     => $pub,
				'kind'    => $pub ? 'publisher' : ( 'relay' === ( $p['kind'] ?? '' ) ? 'relay' : ( $fl ? 'feeds' : 'press' ) ),
				'feeds'   => count( $fl ),
				'feeds_on' => count( array_filter( $fl, fn( $x ) => ! empty( $x['on'] ) ) ),
				'code_n'  => count( array_filter( $fl, fn( $x ) => 'code' === aw_src_read_by( $x ) ) ), // sources read by code ('code' is the SEO HTTP status below)
				'fnames'  => implode( ' ', array_map( fn( $x ) => (string) ( $x['name'] ?? '' ), $fl ) ),
				'relay'   => $rel,
				'relay_own' => ! empty( $p['relay'] ),
				'relay_last' => aw_src_relay_last( $log, $h ),
				'count'   => (int) ( $p['count'] ?? 0 ),
				'press'   => isset( $press[ $h ] ),
				'name'    => $pub || 'relay' === ( $p['kind'] ?? '' ) ? (string) ( $p['name'] ?? '' ) : '',
				'short'   => $pub ? (string) ( $p['short'] ?? '' ) : '',
				'status'  => $pub ? (string) ( $p['status'] ?? 'neutral' ) : '',
				'rec'     => $pub ? $p : array(),
				'dr'      => $pub && isset( $p['dr'] ) && is_numeric( $p['dr'] ) ? (int) $p['dr'] : null,
				'dr_at'   => $pub ? (string) ( $p['dr_at'] ?? '' ) : '',
				'f30'     => (int) ( $s['f30'] ?? 0 ),
				'pub_n'   => (int) ( $s['pub'] ?? 0 ),
				'impr'    => (int) ( $s['impr'] ?? 0 ),
				'clicks'  => (int) ( $s['clicks'] ?? 0 ),
				'impr3'   => (int) ( $s['impr3'] ?? 0 ),
				'clicks3' => (int) ( $s['clicks3'] ?? 0 ),
				'title'   => (string) ( $m['title'] ?? '' ),
				'desc'    => (string) ( $m['desc'] ?? '' ),
				'code'    => (string) ( $m['code'] ?? '' ),
				'at'      => (string) ( $m['at'] ?? '' ),
				'last'    => $pub ? (string) ( $p['last'] ?? '' ) : '',
			);
		}
	}

	/** Search Console ran, but not within 28 days (the screen says so once). */
	public function search_stale() {
		return $this->stale;
	}

	private function view() {
		$s = sanitize_key( $_GET['status'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		return in_array( $s, self::VIEWS, true ) ? $s : '';
	}

	/** The screen shown: '' (this list) or feeds (its own screen, with these views on top). */
	private function screen_view() {
		return function_exists( 'aw_sourcing_sources_screen' ) ? aw_sourcing_sources_screen() : '';
	}

	/** True when a row is in a view. */
	private function in_view( array $r, $v ) {
		switch ( $v ) {
			case 'press':
				return $r['press'];
			case 'feeds':
				return $r['feeds'] > 0;
			case 'code':
				return $r['code_n'] > 0;
			case 'relay':
				return $r['relay'];
			default:
				return '' === $v || $r['status'] === $v;
		}
	}

	protected function get_views() {
		$base  = admin_url( 'admin.php?page=aw-sourcing&tab=sources' );
		$shown = $this->screen_view();
		$out   = array();
		$views = array( '' => 'All', 'allow' => 'Allowed', 'neutral' => 'Neutral', 'block' => 'Blocked', 'press' => 'Press sites', 'feeds' => 'With feeds', 'code' => 'Read by code', 'relay' => 'Relay' );
		if ( aw_sourcing_phase() >= 2 ) { // press sites went with Brave (phase 2, owner's decision 7)
			unset( $views['press'] );
		}
		foreach ( $views as $k => $l ) {
			$n                             = count( array_filter( $this->all, fn( $r ) => $this->in_view( $r, $k ) ) );
			$cur                           = '' === $shown && $this->view() === $k;
			$out[ '' === $k ? 'all' : $k ] = '<a href="' . esc_url( $k ? add_query_arg( 'status', $k, $base ) : $base ) . '"' . ( $cur ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $l ) . ' <span class="count">(' . esc_html( number_format_i18n( $n ) ) . ')</span></a>';
		}
		$all_feeds = array_sum( array_column( $this->all, 'feeds' ) );
		foreach ( array( 'feeds' => array( 'all-feeds', 'Feeds', $all_feeds ) ) as $v => $d ) {
			$out[ $d[0] ] = '<a href="' . esc_url( add_query_arg( 'view', $v, $base ) ) . '"' . ( $shown === $v ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $d[1] ) . ' <span class="count">(' . esc_html( number_format_i18n( $d[2] ) ) . ')</span></a>';
		}
		return $out;
	}

	public function get_columns() {
		return array(
			'cb'     => '<input type="checkbox" />',
			'host'   => 'Site',
			'short'  => 'Short name',
			'status' => 'Status',
			'rating' => 'Rating',
			'dr'     => 'Domain Rating',
			'feeds'  => 'Feeds',
			'relay'  => 'Relay',
			'items'  => 'Items (30 days / published)',
			'search' => 'Search value',
			'seo'    => 'SEO',
			'last'   => 'Last seen',
		);
	}

	protected function get_sortable_columns() {
		return array(
			'host'   => array( 'host', false ),
			'status' => array( 'status', false ),
			'dr'     => array( 'dr', true ),
			'feeds'  => array( 'feeds', true ),
			'items'  => array( 'items', true ),
			'search' => array( 'search', true ),
			'last'   => array( 'last', true ),
		);
	}

	protected function get_primary_column_name() {
		return 'host';
	}

	protected function get_bulk_actions() {
		return array(
			'allow'        => 'Change to Allowed',
			'neutral'      => 'Change to Neutral',
			'block'        => 'Change to Blocked',
		) + ( aw_sourcing_phase() < 2 ? array(
			'press_add'    => 'Search here for coverage',
			'press_remove' => 'Stop searching here',
		) : array() ) + ( aw_sourcing_phase() >= 1 ? array(
			'relay_on'  => 'Relay on (fetch through this site)',
			'relay_off' => 'Relay off',
		) : array() );
	}

	/** The core bulk menu is named "action", which admin-post.php reads as its own; this one is bulk_status. */
	protected function bulk_actions( $which = '' ) {
		$two = 'top' === $which ? '' : '2';
		echo '<label for="aw-pub-bulk-' . esc_attr( $which ) . '" class="screen-reader-text">Select bulk action</label>';
		echo '<select name="bulk_status' . esc_attr( $two ) . '" id="aw-pub-bulk-' . esc_attr( $which ) . '"><option value="">Bulk actions</option>';
		foreach ( $this->get_bulk_actions() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
		}
		echo '</select>';
		submit_button( 'Apply', 'action', '', false, array( 'id' => 'aw-pub-apply-' . $which ) );
	}

	/** As core, without the table's own nonce: the screen's form already carries aw_sourcing, which the handler checks. */
	protected function display_tablenav( $which ) {
		echo '<div class="tablenav ' . esc_attr( $which ) . '">';
		if ( $this->has_items() ) {
			echo '<div class="alignleft actions bulkactions">';
			$this->bulk_actions( $which );
			echo '</div>';
		}
		$this->extra_tablenav( $which );
		$this->pagination( $which );
		echo '<br class="clear"></div>';
	}

	protected function extra_tablenav( $which ) {
		if ( 'top' === $which && $this->has_items() ) {
			echo '<div class="alignleft actions">';
			submit_button( 'Save short names', 'secondary', 'save_short', false );
			echo '</div>';
		}
	}

	public function prepare_items() {
		$v    = $this->view();
		$s    = mb_strtolower( sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$rows = array();
		foreach ( $this->all as $r ) {
			if ( ! $this->in_view( $r, $v ) ) {
				continue;
			}
			if ( '' !== $s && false === strpos( mb_strtolower( $r['host'] . ' ' . $r['name'] . ' ' . $r['short'] . ' ' . $r['title'] . ' ' . $r['fnames'] ), $s ) ) {
				continue;
			}
			$rows[] = $r;
		}
		$by  = sanitize_key( $_GET['orderby'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		$by  = isset( $this->get_sortable_columns()[ $by ] ) ? $by : '';
		$asc = 'asc' === strtolower( sanitize_key( $_GET['order'] ?? 'desc' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		usort(
			$rows,
			function ( $a, $b ) use ( $by, $asc ) {
				if ( '' === $by ) { // most items first (fetched, then published), then the latest seen
					return ( $b['f30'] <=> $a['f30'] ) ?: ( $b['pub_n'] <=> $a['pub_n'] ) ?: strcmp( $b['last'], $a['last'] ) ?: strcmp( $a['host'], $b['host'] );
				}
				switch ( $by ) {
					case 'dr':
						$c = ( $a['dr'] ?? -1 ) <=> ( $b['dr'] ?? -1 );
						break;
					case 'items':
						$c = ( $a['f30'] <=> $b['f30'] ) ?: ( $a['pub_n'] <=> $b['pub_n'] );
						break;
					case 'feeds':
						$c = ( $a['feeds'] <=> $b['feeds'] ) ?: ( $a['feeds_on'] <=> $b['feeds_on'] );
						break;
					case 'search':
						$c = ( $a['impr'] <=> $b['impr'] ) ?: ( $a['impr3'] <=> $b['impr3'] );
						break;
					default:
						$c = strcasecmp( (string) $a[ $by ], (string) $b[ $by ] );
				}
				$c = $c ? $c : strcmp( $a['host'], $b['host'] );
				return $asc ? $c : -$c;
			}
		);
		$per = $this->get_items_per_page( 'aw_publishers_per_page', 50 );
		$this->set_pagination_args( array( 'total_items' => count( $rows ), 'per_page' => $per ) );
		$this->items           = array_slice( $rows, ( $this->get_pagenum() - 1 ) * $per, $per );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'host' );
	}

	/** A blocked publisher gets the plugins list's problem row under it. */
	public function single_row( $item ) {
		parent::single_row( $item );
		if ( 'block' === $item['status'] ) {
			aw_sourcing_problem_row( $this, 'Blocked: new items from ' . $item['host'] . ' are refused when the workers send them. Items already on the site are unchanged.', 'error' );
		}
	}

	protected function column_cb( $item ) {
		$id = 'aw-h-' . md5( $item['host'] );
		return '<input type="checkbox" name="hosts[]" value="' . esc_attr( $item['host'] ) . '" id="' . esc_attr( $id ) . '"><label for="' . esc_attr( $id ) . '" class="screen-reader-text">' . esc_html( 'Select ' . $item['host'] ) . '</label>';
	}

	/** A row action is a submit button of the form: row_status = "status:host", row_press = "add|remove:host". */
	private function button( $name, $value, $label ) {
		return '<button type="submit" class="button-link" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</button>';
	}

	protected function column_host( $item ) {
		$h    = $item['host'];
		$edit = add_query_arg( array( 'page' => 'aw-sourcing', 'tab' => 'sources', 'edit' => rawurlencode( $h ) ), admin_url( 'admin.php' ) );
		$acts = array( 'edit' => '<a href="' . esc_url( $edit ) . '" aria-label="' . esc_attr( 'Edit ' . $h ) . '">Edit</a>' );
		if ( $item['pub'] ) {
			foreach ( array( 'allow' => 'Allow', 'neutral' => 'Neutral', 'block' => 'Block' ) as $st => $l ) {
				if ( $item['status'] !== $st ) {
					$acts[ 'block' === $st ? 'trash' : $st ] = $this->button( 'row_status', $st . ':' . $h, $l );
				}
			}
		}
		if ( aw_sourcing_phase() < 2 ) {
			$acts['press'] = $item['press'] ? $this->button( 'row_press', 'remove:' . $h, 'Stop searching here' ) : $this->button( 'row_press', 'add:' . $h, 'Search here for coverage' );
		}
		if ( aw_sourcing_phase() >= 1 ) {
			$acts['relay'] = $item['relay_own'] ? $this->button( 'row_relay', 'off:' . $h, 'Relay off' ) : $this->button( 'row_relay', 'on:' . $h, 'Relay on' );
		}
		if ( $this->deletable( $item ) ) { // core's "are you sure" warning (common.js showNotice) before a delete
			$acts['delete'] = '<button type="submit" class="button-link submitdelete" name="row_delete" value="' . esc_attr( $h ) . '" onclick="return showNotice.warn();">Delete</button>';
		}
		$icon          = function_exists( 'aw_favicon_url' ) ? aw_favicon_url( $h ) : '';
		$mark          = $icon ? '<img src="' . esc_url( $icon ) . '" alt="" width="16" height="16" loading="lazy" decoding="async"> ' : '<span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span> ';
		return $mark . '<strong><a class="row-title" href="' . esc_url( $edit ) . '">' . esc_html( $h ) . '</a></strong>'
			. ( '' !== $item['name'] ? '<br>' . esc_html( $item['name'] ) : '' )
			. $this->row_actions( $acts );
	}

	/** A source with nothing attached (no items, feeds or press search): a publisher record or a relay-only one. */
	private function deletable( array $item ) {
		return in_array( $item['kind'], array( 'publisher', 'relay' ), true ) && ! $item['feeds'] && ! $item['press']
			&& ! $item['count'] && ! $item['f30'] && ! $item['pub_n'];
	}

	protected function column_feeds( $item ) {
		if ( ! $item['feeds'] ) {
			return '<span aria-label="No feeds">—</span>';
		}
		$edit = add_query_arg( array( 'page' => 'aw-sourcing', 'tab' => 'sources', 'edit' => rawurlencode( $item['host'] ) ), admin_url( 'admin.php' ) );
		/* translators: 1: feeds, 2: feeds that are on. */
		$out = '<a href="' . esc_url( $edit ) . '">' . esc_html( sprintf( _n( '%1$s feed (%2$s on)', '%1$s feeds (%2$s on)', $item['feeds'] ), number_format_i18n( $item['feeds'] ), number_format_i18n( $item['feeds_on'] ) ) ) . '</a>';
		return $out . ( $item['code_n'] ? '<br><span class="description">' . esc_html( number_format_i18n( $item['code_n'] ) . ' read by code' ) . '</span>' : '' );
	}

	protected function column_relay( $item ) {
		$last = $item['relay_last'] ? aw_relay_result_html( $item['relay_last'] ) : '';
		$on   = $item['relay'] ? '<span class="dashicons dashicons-update" aria-hidden="true"></span> On' : '<span aria-label="Off">—</span>';
		return $on . ( '' !== $last ? '<br><span class="description">Last: </span>' . $last : ( $item['relay'] ? '<br><span class="description">Not used yet</span>' : '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in aw_relay_result_html
	}

	protected function column_short( $item ) {
		if ( ! $item['pub'] ) {
			return '<span aria-label="Not a publisher">—</span>';
		}
		return '<input type="text" name="short[' . esc_attr( $item['host'] ) . ']" value="' . esc_attr( $item['short'] ) . '" maxlength="24" size="12" aria-label="' . esc_attr( 'Short name for ' . $item['host'] ) . '">';
	}

	protected function column_status( $item ) {
		$not = array( 'relay' => 'Relay only', 'feeds' => 'Feeds only', 'press' => '' )[ $item['kind'] ] ?? '';
		$out = $item['pub'] ? aw_publisher_status_html( $item['status'] ) : '<span aria-label="Not a publisher">—</span>' . ( '' !== $not ? ' <span class="description">' . esc_html( $not . ' (not a publisher)' ) . '</span>' : '' );
		return $out . ( $item['press'] ? '<br><span class="dashicons dashicons-search" aria-hidden="true"></span> Searched for coverage' : '' );
	}

	protected function column_rating( $item ) {
		return $item['pub'] ? aw_publisher_rating_html( $item['rec'] ) : '<span aria-label="Not rated">–</span>';
	}

	protected function column_dr( $item ) {
		if ( null === $item['dr'] ) {
			return '<span aria-label="Not looked up">—</span>';
		}
		return esc_html( (string) $item['dr'] ) . ( '' !== $item['dr_at'] ? '<br><span class="description">' . esc_html( $item['dr_at'] ) . '</span>' : '' );
	}

	protected function column_items( $item ) {
		return esc_html( number_format_i18n( $item['f30'] ) . ' / ' . number_format_i18n( $item['pub_n'] ) );
	}

	protected function column_search( $item ) {
		if ( ! $this->si ) {
			return '<span aria-label="No Search Console figures">—</span>';
		}
		$n = 'number_format_i18n';
		return esc_html( '28 days: ' . $n( $item['impr'] ) . ' impr. · ' . $n( $item['clicks'] ) . ' clicks' ) . '<br>'
			. esc_html( '3 months: ' . $n( $item['impr3'] ) . ' impr. · ' . $n( $item['clicks3'] ) . ' clicks' );
	}

	protected function column_seo( $item ) {
		if ( '' === $item['at'] ) {
			return '<span class="description">Not checked yet</span>';
		}
		$ok  = (int) $item['code'] >= 200 && (int) $item['code'] < 300;
		$out = '';
		if ( '' !== $item['title'] ) {
			$out .= esc_html( $item['title'] ) . '<br>';
		}
		if ( '' !== $item['desc'] ) {
			$out .= '<span class="description">' . esc_html( $item['desc'] ) . '</span><br>';
		}
		if ( $ok && '' === $item['title'] && '' === $item['desc'] ) {
			$out .= '<span class="description">No title or description found.</span><br>';
		}
		$res = ctype_digit( $item['code'] ) ? 'HTTP ' . $item['code'] : $item['code'];
		return $out . '<span class="description">' . esc_html( 'Checked ' . substr( $item['at'], 0, 10 ) . ' · ' . $res ) . '</span>';
	}

	protected function column_last( $item ) {
		return '' !== $item['last'] ? esc_html( $item['last'] ) : '—';
	}

	public function no_items() {
		echo 'No sources.';
	}
}
