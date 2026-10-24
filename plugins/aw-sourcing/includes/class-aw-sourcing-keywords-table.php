<?php
/**
 * Settings → Sourcing → Keywords: the four word lists as one core list table (owner, 24 Oct 2026). Views by list,
 * search, sortable columns, pagination with a screen option, row actions (Remove, Move to another list) and bulk
 * actions. Everything posts to admin-post.php (action aw_sourcing_keywords) with the table's own bulk-keywords nonce.
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

	/** @var array list => terms */
	private $rel;

	public function __construct() {
		parent::__construct( array( 'singular' => 'keyword', 'plural' => 'keywords', 'ajax' => false, 'screen' => 'settings_page_aw-sourcing' ) );
		$this->rel = aw_relevance();
	}

	private function which() {
		$l = sanitize_key( $_GET['list'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		return isset( self::LISTS[ $l ] ) ? $l : '';
	}

	protected function get_views() {
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
		return array(
			'cb'   => '<input type="checkbox" />',
			'term' => 'Word',
			'list' => 'List',
			'use'  => 'What it does',
		);
	}

	protected function get_sortable_columns() {
		return array(
			'term' => array( 'term', false ),
			'list' => array( 'list', false ),
		);
	}

	protected function get_primary_column_name() {
		return 'term';
	}

	protected function get_bulk_actions() {
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
		echo 'No words.';
	}
}
