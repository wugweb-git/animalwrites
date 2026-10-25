<?php
/**
 * Settings → Sourcing → Keywords as one core list table.
 *
 * - From phase 2 (owner, 25 Oct 2026; aw_sourcing_kw_on()): one row per keyword record of the keyword table with a
 *   Sourcing or Press role. Views by role (All, Include, Animal, Context, Exclude, Search, Press, Category words) and
 *   Hindi (not English, so no Sourcing role); a category filter (&cat=); search; sortable columns; row actions (Edit,
 *   Delete, add or remove a role) and bulk actions (add or remove a role, map to a category, delete), posting to
 *   admin-post.php (action aw_sourcing_kw) with the table's own bulk-keywords nonce.
 * - Before phase 2: the four word lists (owner, 24 Oct 2026), posting to aw_sourcing_keywords as before.
 * Loaded only when the tab is shown.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The relevance word lists (option aw_relevance: strong, animal, context, exclude). */
class AW_Sourcing_Keywords_Table extends WP_List_Table {

	/** List key => label, as on the tab before (strong is the Include list). */
	const LISTS = array(
		'strong'  => 'Include',
		'animal'  => 'Animal words',
		'context' => 'Context words',
		'exclude' => 'Exclude',
	);

	/** Phase 2 views: slug => [label, role bit (0: the Hindi view)]. */
	const VIEWS = array(
		'include'  => array( 'Include', AW_KW_ROLE_INCLUDE ),
		'animal'   => array( 'Animal', AW_KW_ROLE_ANIMAL ),
		'context'  => array( 'Context', AW_KW_ROLE_CONTEXT ),
		'exclude'  => array( 'Exclude', AW_KW_ROLE_EXCLUDE ),
		'search'   => array( 'Search', AW_KW_ROLE_SEARCH ),
		'press'    => array( 'Press', AW_KW_ROLE_PRESS ),
		'category' => array( 'Category words', AW_KW_ROLE_CATEGORY ),
		'hindi'    => array( 'Hindi (no Sourcing role)', 0 ),
	);

	/** @var array list => terms */
	private $rel;

	/** @var bool Phase 2: rows are keyword records. */
	private $records;

	/** @var array[] Phase 2: every record shown in some view (id => record). */
	private $all = array();

	/** @var array Phase 2: feed id => items fetched / published in 30 days. */
	private $stats = array();

	public function __construct() {
		parent::__construct( array( 'singular' => 'keyword', 'plural' => 'keywords', 'ajax' => false, 'screen' => 'settings_page_aw-sourcing' ) );
		$this->records = function_exists( 'aw_sourcing_kw_on' ) && aw_sourcing_kw_on();
		$this->rel     = $this->records ? array() : aw_relevance();
	}

	/** Phase 2: the view shown ('' for all). */
	public function view() {
		$v = sanitize_key( $_GET['view'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		return isset( self::VIEWS[ $v ] ) ? $v : '';
	}

	/** Phase 2: the base address of the list, with its category filter. */
	private function url( array $args = array() ) {
		$cat = sanitize_title( wp_unslash( $_GET['cat'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		return add_query_arg( $args + ( '' !== $cat ? array( 'cat' => $cat ) : array() ), admin_url( 'options-general.php?page=aw-sourcing&tab=keywords' ) );
	}

	/**
	 * Phase 2: whether a record belongs to a view.
	 *
	 * @param array  $r Record.
	 * @param string $v View.
	 */
	private function in_view( array $r, $v ) {
		if ( 'hindi' === $v ) {
			return 'en' !== $r['lang'];
		}
		if ( 'en' !== $r['lang'] ) {
			return false;
		}
		return '' === $v ? (bool) ( $r['roles'] & AW_KW_WORKER_ROLES ) : (bool) ( $r['roles'] & self::VIEWS[ $v ][1] );
	}

	private function which() {
		$l = sanitize_key( $_GET['list'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		return isset( self::LISTS[ $l ] ) ? $l : '';
	}

	protected function get_views() {
		if ( $this->records ) {
			$out = array();
			$cur = $this->view();
			foreach ( array( '' => array( 'All', 0 ) ) + self::VIEWS as $k => $def ) {
				$n                             = count( array_filter( $this->all, fn( $r ) => $this->in_view( $r, $k ) ) );
				$out[ '' === $k ? 'all' : $k ] = '<a href="' . esc_url( $this->url( '' === $k ? array() : array( 'view' => $k ) ) ) . '"' . ( $cur === $k ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $def[0] ) . ' <span class="count">(' . esc_html( number_format_i18n( $n ) ) . ')</span></a>';
			}
			$out['suggestions'] = '<a href="' . esc_url( admin_url( 'options-general.php?page=aw-sourcing&tab=keywords&kw=suggestions' ) ) . '">Suggestions from Search Insights</a>';
			return $out;
		}
		$base = admin_url( 'options-general.php?page=aw-sourcing&tab=keywords' );
		$all  = 0;
		$out  = array();
		foreach ( self::LISTS as $k => $l ) {
			$all += count( (array) ( $this->rel[ $k ] ?? array() ) );
		}
		foreach ( array( '' => 'All' ) + self::LISTS as $k => $l ) {
			$n         = '' === $k ? $all : count( (array) ( $this->rel[ $k ] ?? array() ) );
			$cur       = $this->which() === $k;
			$out[ '' === $k ? 'all' : $k ] = '<a href="' . esc_url( $k ? add_query_arg( 'list', $k, $base ) : $base ) . '"' . ( $cur ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $l ) . ' <span class="count">(' . esc_html( number_format_i18n( $n ) ) . ')</span></a>';
		}
		return $out;
	}

	public function get_columns() {
		if ( $this->records ) {
			return array(
				'cb'         => '<input type="checkbox" />',
				'label'      => 'Keyword',
				'spellings'  => 'Other spellings',
				'roles'      => 'Roles',
				'categories' => 'Categories',
				'search'     => 'Google News search',
				'items'      => 'Items (30 days)',
				'impr'       => 'Impressions (28 days)',
			);
		}
		return array(
			'cb'   => '<input type="checkbox" />',
			'term' => 'Word',
			'list' => 'List',
			'use'  => 'What it does',
		);
	}

	protected function get_sortable_columns() {
		if ( $this->records ) {
			return array(
				'label' => array( 'label', false ),
				'items' => array( 'items', true ),
				'impr'  => array( 'impr', true ),
			);
		}
		return array(
			'term' => array( 'term', false ),
			'list' => array( 'list', false ),
		);
	}

	protected function get_primary_column_name() {
		return $this->records ? 'label' : 'term';
	}

	protected function get_bulk_actions() {
		if ( $this->records ) {
			$out = array();
			foreach ( aw_sourcing_kw_roles() as $bit => $l ) {
				$out[ 'add_' . $bit ] = 'Add role: ' . $l;
			}
			foreach ( aw_sourcing_kw_roles() as $bit => $l ) {
				$out[ 'remove_' . $bit ] = 'Remove role: ' . $l;
			}
			foreach ( get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false ) ) as $t ) {
				$out[ 'cat_' . $t->slug ] = 'Map to category: ' . $t->name;
			}
			$out['delete'] = 'Delete from sourcing';
			return $out;
		}
		$out = array( 'remove' => 'Remove' );
		foreach ( self::LISTS as $k => $l ) {
			$out[ 'to_' . $k ] = 'Move to ' . $l;
		}
		return $out;
	}

	/** The core bulk menu is named "action", which admin-post.php reads as its own; this one is kw_bulk. */
	protected function bulk_actions( $which = '' ) {
		$two = 'top' === $which ? '' : '2';
		echo '<label for="aw-kw-bulk-' . esc_attr( $which ) . '" class="screen-reader-text">Select bulk action</label>';
		echo '<select name="kw_bulk' . esc_attr( $two ) . '" id="aw-kw-bulk-' . esc_attr( $which ) . '"><option value="">Bulk actions</option>';
		foreach ( $this->get_bulk_actions() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
		}
		echo '</select>';
		submit_button( 'Apply', 'action', '', false, array( 'id' => 'aw-kw-apply-' . $which ) );
	}

	public function prepare_items() {
		if ( $this->records ) {
			$this->prepare_records();
			return;
		}
		$only = $this->which();
		$s    = mb_strtolower( sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$rows = array();
		foreach ( self::LISTS as $k => $l ) {
			if ( $only && $only !== $k ) {
				continue;
			}
			foreach ( (array) ( $this->rel[ $k ] ?? array() ) as $i => $t ) {
				if ( '' === $s || false !== mb_strpos( mb_strtolower( (string) $t ), $s ) ) {
					$rows[] = array( 'term' => (string) $t, 'list' => $k, 'pos' => $i );
				}
			}
		}
		$by = sanitize_key( $_GET['orderby'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $this->get_sortable_columns()[ $by ] ) ) { // otherwise the lists' own order, Include first
			$asc = 'desc' !== strtolower( sanitize_key( $_GET['order'] ?? 'asc' ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$ord = array_flip( array_keys( self::LISTS ) );
			usort(
				$rows,
				function ( $a, $b ) use ( $by, $asc, $ord ) {
					$c = 'list' === $by ? ( $ord[ $a['list'] ] <=> $ord[ $b['list'] ] ) : strcasecmp( $a['term'], $b['term'] );
					$c = $c ? $c : strcasecmp( $a['term'], $b['term'] );
					return $asc ? $c : -$c;
				}
			);
		}
		$per = $this->get_items_per_page( 'aw_keywords_per_page', 100 );
		$this->set_pagination_args( array( 'total_items' => count( $rows ), 'per_page' => $per ) );
		$this->items           = array_slice( $rows, ( $this->get_pagenum() - 1 ) * $per, $per );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'term' );
	}

	/** The id of a row: "list|word" (list keys have no |). */
	private static function id( $item ) {
		return $item['list'] . '|' . $item['term'];
	}

	protected function column_cb( $item ) {
		if ( $this->records ) {
			return '<input type="checkbox" name="kw[]" value="' . (int) $item['id'] . '" id="aw-kw-' . (int) $item['id'] . '"><label for="aw-kw-' . (int) $item['id'] . '" class="screen-reader-text">' . esc_html( 'Select ' . $item['label'] ) . '</label>';
		}
		$id = 'aw-kw-' . md5( self::id( $item ) );
		return '<input type="checkbox" name="kw[]" value="' . esc_attr( self::id( $item ) ) . '" id="' . esc_attr( $id ) . '"><label for="' . esc_attr( $id ) . '" class="screen-reader-text">' . esc_html( 'Select ' . $item['term'] ) . '</label>';
	}

	/** A row action is a submit button of the form: kw_row = "op:list|word". */
	private function op_button( $op, $label, $item ) {
		return '<button type="submit" class="button-link" name="kw_row" value="' . esc_attr( $op . ':' . self::id( $item ) ) . '">' . esc_html( $label ) . '</button>';
	}

	protected function column_term( $item ) {
		$acts = array();
		foreach ( self::LISTS as $k => $l ) {
			if ( $k !== $item['list'] ) {
				$acts[ 'to_' . $k ] = $this->op_button( 'to_' . $k, 'Move to ' . $l, $item );
			}
		}
		$acts['trash'] = $this->op_button( 'remove', 'Remove', $item );
		return '<strong>' . esc_html( $item['term'] ) . '</strong>' . $this->row_actions( $acts );
	}

	protected function column_list( $item ) {
		return esc_html( self::LISTS[ $item['list'] ] );
	}

	protected function column_use( $item ) {
		$english = (bool) preg_match( '/^[\x20-\x7e]+$/', $item['term'] );
		$map     = array(
			'strong'  => $english ? 'Keeps an item on its own; a Google News search in every run' : 'Keeps an item on its own (not a search: not English)',
			'animal'  => 'Keeps an item together with a context word',
			'context' => 'Keeps an item together with an animal word',
			'exclude' => 'Drops an item, whatever else it has',
		);
		return esc_html( $map[ $item['list'] ] );
	}

	public function no_items() {
		echo $this->records ? 'No keywords.' : 'No words.';
	}

	/** Phase 2: the records of the view, searched, filtered, sorted and paged. */
	private function prepare_records() {
		global $wpdb;
		$k         = aw_si_table( 'keywords' );
		$hindi_ids = (array) $wpdb->get_col( "SELECT id FROM {$k} WHERE lang <> 'en' AND src_note IS NOT NULL AND src_note <> ''" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only; the Hindi words the lists had
		$this->all = aw_kw_rows( AW_KW_WORKER_ROLES );
		foreach ( aw_kw_attach_maps( $hindi_ids ? (array) $wpdb->get_results( 'SELECT id, norm, label, lang, roles, variants, query, feed_id, search_on, dismissed, src_note, impr28 FROM ' . $k . ' WHERE id IN (' . implode( ',', array_map( 'intval', $hindi_ids ) ) . ')', ARRAY_A ) : array() ) as $id => $r ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- table name and integers only.
			$this->all[ $id ] = $r;
		}
		foreach ( (array) ( aw_sourcing_keyword_stats( 30 )['rows'] ?? array() ) as $feed => $st ) {
			$this->stats[ $feed ] = array( count( $st['news']['fetched'] ) + count( $st['legal']['fetched'] ), count( $st['news']['published'] ) + count( $st['legal']['published'] ) );
		}
		$by_id = array_column( array_filter( (array) aw_sources_config()['sources'], 'is_array' ), null, 'id' );
		$v     = $this->view();
		$cat   = sanitize_title( wp_unslash( $_GET['cat'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$s     = mb_strtolower( sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$rows  = array();
		foreach ( $this->all as $r ) {
			if ( ! $this->in_view( $r, $v ) || ( '' !== $cat && ! in_array( $cat, $r['categories'], true ) ) ) {
				continue;
			}
			if ( '' !== $s && false === mb_strpos( mb_strtolower( implode( ' ', aw_kw_spellings( $r ) ) ), $s ) ) {
				continue;
			}
			$r['searches'] = aw_kw_searches( $r, $by_id );
			$r['items']    = array( 0, 0 );
			foreach ( $r['searches'] as $x ) {
				$r['items'][0] += $this->stats[ $x['feed'] ][0] ?? 0;
				$r['items'][1] += $this->stats[ $x['feed'] ][1] ?? 0;
			}
			$rows[] = $r;
		}
		$by  = sanitize_key( $_GET['orderby'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		$asc = 'asc' === strtolower( sanitize_key( $_GET['order'] ?? ( 'label' === $by ? 'asc' : 'desc' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $this->get_sortable_columns()[ $by ] ) ) {
			usort(
				$rows,
				function ( $a, $b ) use ( $by, $asc ) {
					$c = 'label' === $by ? strcasecmp( $a['label'], $b['label'] ) : ( 'items' === $by ? $a['items'][0] <=> $b['items'][0] : (int) $a['impr28'] <=> (int) $b['impr28'] );
					$c = $c ? $c : strcasecmp( $a['label'], $b['label'] );
					return $asc ? $c : -$c;
				}
			);
		} else {
			usort( $rows, fn( $a, $b ) => strcasecmp( $a['label'], $b['label'] ) );
		}
		$per = $this->get_items_per_page( 'aw_keywords_per_page', 100 );
		$this->set_pagination_args( array( 'total_items' => count( $rows ), 'per_page' => $per ) );
		$this->items           = array_slice( $rows, ( $this->get_pagenum() - 1 ) * $per, $per );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'label' );
	}

	/** Phase 2: a row action is a submit button of the list's form: kw_row = "op:id". */
	private function kw_button( $op, $label, $id ) {
		return '<button type="submit" class="button-link" name="kw_row" value="' . esc_attr( $op . ':' . (int) $id ) . '">' . esc_html( $label ) . '</button>';
	}

	protected function column_label( $item ) {
		$edit = $this->url( array( 'edit' => (int) $item['id'] ) );
		$acts = array( 'edit' => '<a href="' . esc_url( $edit ) . '">Edit</a>' );
		if ( 'en' === $item['lang'] ) {
			foreach ( aw_sourcing_kw_roles() as $bit => $l ) {
				if ( in_array( $bit, array( AW_KW_ROLE_INCLUDE, AW_KW_ROLE_EXCLUDE ), true ) ) {
					$acts[ 'r' . $bit ] = $item['roles'] & $bit ? $this->kw_button( 'remove_' . $bit, 'Remove ' . $l, $item['id'] ) : $this->kw_button( 'add_' . $bit, 'Add ' . $l, $item['id'] );
				}
			}
			$acts['trash'] = $this->kw_button( 'delete', 'Delete', $item['id'] );
		}
		$state = 'en' !== $item['lang'] ? ' — <span class="post-state">' . esc_html( 'hi' === $item['lang'] ? 'Hindi' : 'Hinglish' ) . '</span>' : '';
		return '<strong><a class="row-title" href="' . esc_url( $edit ) . '">' . esc_html( $item['label'] ) . '</a></strong>' . $state . $this->row_actions( $acts );
	}

	protected function column_spellings( $item ) {
		return $item['variants'] ? esc_html( implode( ', ', $item['variants'] ) ) : '—';
	}

	protected function column_roles( $item ) {
		$names = aw_sourcing_kw_role_names( $item['roles'] );
		return $names ? esc_html( implode( ', ', $names ) ) : '—';
	}

	protected function column_categories( $item ) {
		$out = array();
		foreach ( $item['categories'] as $slug ) {
			$t     = get_term_by( 'slug', $slug, 'category' );
			$out[] = '<a href="' . esc_url( $this->url( array( 'view' => 'category', 'cat' => $slug ) ) ) . '">' . esc_html( $t ? $t->name : $slug ) . '</a>';
		}
		return $out ? implode( ', ', $out ) : '—';
	}

	protected function column_search( $item ) {
		if ( ! $item['searches'] ) {
			return ( $item['roles'] & ( AW_KW_ROLE_INCLUDE | AW_KW_ROLE_SEARCH ) ) && ! $item['search_on'] ? 'Off' : '—';
		}
		return esc_html( 'On: ' . implode( ', ', array_map( fn( $x ) => $x['feed'], $item['searches'] ) ) );
	}

	protected function column_items( $item ) {
		return $item['searches'] ? esc_html( number_format_i18n( $item['items'][0] ) . ' fetched · ' . number_format_i18n( $item['items'][1] ) . ' published' ) : '—';
	}

	protected function column_impr( $item ) {
		return esc_html( number_format_i18n( (int) $item['impr28'] ) );
	}
}
