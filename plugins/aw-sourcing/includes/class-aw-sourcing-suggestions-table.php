<?php
/**
 * The suggestions list (Settings → Sourcing → Keywords) as a core list table. Loaded only when the tab is shown.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Suggestions from Search Insights. */
class AW_Sourcing_Suggestions_Table extends WP_List_Table {

	/** @var array All suggestions, dismissed included. */
	private $all;

	public function __construct( array $rows ) {
		parent::__construct( array( 'singular' => 'suggestion', 'plural' => 'suggestions', 'ajax' => false, 'screen' => 'settings_page_aw-sourcing' ) );
		$this->all = $rows;
	}

	/** The Dismissed view is open. */
	public function is_dismissed_view() {
		return 'dismissed' === sanitize_key( $_GET['suggestions'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	private function url( array $args = array() ) {
		return add_query_arg( $args, admin_url( 'options-general.php?page=aw-sourcing&tab=keywords&kw=suggestions' ) );
	}

	protected function get_views() {
		$open = count( array_filter( $this->all, fn( $r ) => ! $r['dismissed'] ) );
		$gone = count( $this->all ) - $open;
		$dis  = $this->is_dismissed_view();
		return array(
			'all'       => '<a href="' . esc_url( $this->url() . '#aw-suggestions' ) . '"' . ( $dis ? '' : ' class="current" aria-current="page"' ) . '>All <span class="count">(' . number_format_i18n( $open ) . ')</span></a>',
			'dismissed' => '<a href="' . esc_url( $this->url( array( 'suggestions' => 'dismissed' ) ) . '#aw-suggestions' ) . '"' . ( $dis ? ' class="current" aria-current="page"' : '' ) . '>Dismissed <span class="count">(' . number_format_i18n( $gone ) . ')</span></a>',
		);
	}

	public function get_columns() {
		return array(
			'cb'     => '<input type="checkbox" />',
			'q'      => 'Search',
			'impr'   => 'Impressions (28 days)',
			'clicks' => 'Clicks (28 days)',
			'pos'    => 'Avg. position',
		);
	}

	protected function get_sortable_columns() {
		return array(
			'q'      => array( 'q', false ),
			'impr'   => array( 'impr', true ),
			'clicks' => array( 'clicks', true ),
			'pos'    => array( 'pos', false ),
		);
	}

	protected function get_primary_column_name() {
		return 'q';
	}

	protected function get_bulk_actions() {
		return $this->is_dismissed_view()
			? array( 'restore' => 'Show again' )
			: array( 'include' => 'Add to Include', 'exclude' => 'Add to Exclude' ) + ( function_exists( 'aw_sourcing_kw_on' ) && aw_sourcing_kw_on() ? array( 'search' => 'Add as Search' ) : array() ) + array( 'dismiss' => 'Dismiss' );
	}

	/** The core bulk menu is named "action", which admin-post.php reads as its own; these are aw_bulk / aw_bulk2. */
	protected function bulk_actions( $which = '' ) {
		$actions = $this->get_bulk_actions();
		$two     = 'top' === $which ? '' : '2';
		echo '<label for="aw-bulk-' . esc_attr( $which ) . '" class="screen-reader-text">Select bulk action</label>';
		echo '<select name="aw_bulk' . esc_attr( $two ) . '" id="aw-bulk-' . esc_attr( $which ) . '"><option value="-1">Bulk actions</option>';
		foreach ( $actions as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
		}
		echo '</select>';
		submit_button( 'Apply', 'action', '', false, array( 'id' => 'aw-bulk-apply-' . $which ) );
	}

	/** Next to the actions: how big the Include list is and what one more word costs. */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$include = count( (array) ( aw_relevance()['strong'] ?? array() ) );
		echo '<div class="alignleft actions"><span class="dashicons dashicons-search" aria-hidden="true"></span> Include list: <strong>' . esc_html( number_format_i18n( $include ) ) . ' words</strong> · about <strong>' . esc_html( number_format_i18n( aw_sourcing_suggest_search_count() ) ) . ' Google News searches per run</strong>. Each English word added to Include is one more search per run (up to 40 new items); Exclude adds none.</div>';
	}

	public function prepare_items() {
		$dis  = $this->is_dismissed_view();
		$s    = aw_sourcing_suggest_norm( sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$rows = array_values( array_filter( $this->all, fn( $r ) => $r['dismissed'] === $dis && ( '' === $s || false !== strpos( $r['q'], $s ) ) ) );
		$by   = sanitize_key( $_GET['orderby'] ?? 'impr' ); // phpcs:ignore WordPress.Security.NonceVerification
		$by   = in_array( $by, array( 'q', 'impr', 'clicks', 'pos' ), true ) ? $by : 'impr';
		$asc  = 'asc' === strtolower( sanitize_key( $_GET['order'] ?? ( in_array( $by, array( 'q', 'pos' ), true ) ? 'asc' : 'desc' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		usort(
			$rows,
			function ( $a, $b ) use ( $by, $asc ) {
				$c = 'q' === $by ? strcmp( $a['q'], $b['q'] ) : ( $a[ $by ] <=> $b[ $by ] );
				$c = $c ? $c : strcmp( $a['q'], $b['q'] );
				return $asc ? $c : -$c;
			}
		);
		$per = $this->get_items_per_page( 'aw_suggestions_per_page', 20 );
		$this->set_pagination_args( array( 'total_items' => count( $rows ), 'per_page' => $per ) );
		$this->items           = array_slice( $rows, ( $this->get_pagenum() - 1 ) * $per, $per );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'q' );
	}

	protected function column_cb( $item ) {
		return '<input type="checkbox" name="q[]" value="' . esc_attr( $item['q'] ) . '" id="aw-sg-' . esc_attr( md5( $item['q'] ) ) . '"><label for="aw-sg-' . esc_attr( md5( $item['q'] ) ) . '" class="screen-reader-text">' . esc_html( 'Select ' . $item['q'] ) . '</label>';
	}

	/** A row action is a submit button of the table's form (POST to admin-post.php with the form's nonce). */
	private function action_button( $op, $label, $q ) {
		return '<button type="submit" class="button-link" name="aw_suggest" value="' . esc_attr( $op . ':' . $q ) . '">' . esc_html( $label ) . '</button>';
	}

	protected function column_q( $item ) {
		$acts = $item['dismissed']
			? array( 'restore' => $this->action_button( 'restore', 'Show again', $item['q'] ) )
			: array(
				'include' => $this->action_button( 'include', 'Add to Include', $item['q'] ),
				'exclude' => $this->action_button( 'exclude', 'Add to Exclude', $item['q'] ),
			) + ( function_exists( 'aw_sourcing_kw_on' ) && aw_sourcing_kw_on() ? array( 'search' => $this->action_button( 'search', 'Add as Search', $item['q'] ) ) : array() ) + array(
				'trash'   => $this->action_button( 'dismiss', 'Dismiss', $item['q'] ),
			);
		return '<strong>' . esc_html( $item['q'] ) . '</strong>' . ( $item['rising'] ? ' — <span class="post-state">Rising</span>' : '' ) . $this->row_actions( $acts );
	}

	protected function column_default( $item, $col ) {
		if ( 'pos' === $col ) {
			return $item['pos'] ? esc_html( number_format_i18n( $item['pos'], 1 ) ) : '—';
		}
		return esc_html( number_format_i18n( (int) $item[ $col ] ) );
	}

	public function no_items() {
		echo $this->is_dismissed_view() ? 'No dismissed suggestions.' : 'No suggestions: every search with demand is covered.';
	}
}
