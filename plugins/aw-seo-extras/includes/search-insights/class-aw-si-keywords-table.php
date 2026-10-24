<?php
/**
 * Keywords tab: the keyword records as a core list table (views, filters, search, sorting, bulk and row actions,
 * screen options, pagination). Priority overrides are the only change it makes, and only to Search Insights' own table.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * The Keywords tab list table.
 */
class AW_SI_Keywords_Table extends WP_List_Table {

	/** Set up the list: one row per keyword record. */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'keyword',
				'plural'   => 'keywords',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Request value, sanitised as a key.
	 *
	 * @param string $name Request field.
	 */
	private function arg( string $name ): string {
		return isset( $_REQUEST[ $name ] ) ? sanitize_key( wp_unslash( $_REQUEST[ $name ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- read-only filters.
	}

	/** Columns. */
	public function get_columns() {
		return array(
			'cb'       => '<input type="checkbox">',
			'label'    => __( 'Keyword', 'aw-seo-extras' ),
			'priority' => __( 'Priority', 'aw-seo-extras' ),
			'status'   => __( 'Status', 'aw-seo-extras' ),
			'page'     => __( 'Page', 'aw-seo-extras' ),
			'impr28'   => __( 'Impressions (28 days)', 'aw-seo-extras' ),
			'clicks28' => __( 'Clicks', 'aw-seo-extras' ),
			'pos28'    => __( 'Position', 'aw-seo-extras' ),
			'change'   => __( 'Change', 'aw-seo-extras' ),
			'live'     => __( 'Live check', 'aw-seo-extras' ),
			'trends'   => __( 'Trends', 'aw-seo-extras' ),
			'action'   => __( 'Suggested action', 'aw-seo-extras' ),
		);
	}

	/** Sortable columns and their first direction. */
	protected function get_sortable_columns() {
		return array(
			'label'    => array( 'label', false ),
			'priority' => array( 'priority', false ),
			'impr28'   => array( 'impr28', true ),
			'clicks28' => array( 'clicks28', true ),
			'pos28'    => array( 'pos28', false ),
			'change'   => array( 'change', true ),
			'trends'   => array( 'trends', true ),
		);
	}

	/** The column that holds the row actions. */
	protected function get_primary_column_name() {
		return 'label';
	}

	/** Priority overrides: the only change this list makes, to its own table. */
	protected function get_bulk_actions() {
		return array(
			'override_p1'    => __( 'Set priority P1', 'aw-seo-extras' ),
			'override_p2'    => __( 'Set priority P2', 'aw-seo-extras' ),
			'override_p3'    => __( 'Set priority P3', 'aw-seo-extras' ),
			'override_clear' => __( 'Use the calculated priority', 'aw-seo-extras' ),
		);
	}

	/** SQL condition of each view. */
	private function view_sql(): array {
		$eff = "IF(priority_override <> '', priority_override, priority)";
		return array(
			'all'       => '1=1',
			'p1'        => "{$eff} = 'P1'",
			'p2'        => "{$eff} = 'P2'",
			'p3'        => "{$eff} = 'P3'",
			'gap'       => "status = 'gap'",
			'conflict'  => "status = 'conflict'",
			'quick'     => 'pos28 BETWEEN 8 AND 20 AND impr28 >= ' . (int) AW_SI_QUICK_MIN,
			'notranked' => 'roles & ' . (int) AW_SI_ROLE_TRACKED . ' AND impr28 = 0',
			'entity'    => 'entity = 1',
		);
	}

	/** Labels of the views. */
	private function view_labels(): array {
		return array(
			'all'       => __( 'All', 'aw-seo-extras' ),
			'p1'        => 'P1',
			'p2'        => 'P2',
			'p3'        => 'P3',
			'gap'       => __( 'Gaps', 'aw-seo-extras' ),
			'conflict'  => __( 'Conflicts', 'aw-seo-extras' ),
			'quick'     => __( 'Quick wins', 'aw-seo-extras' ),
			'notranked' => __( 'Tracked, not ranking', 'aw-seo-extras' ),
			'entity'    => __( 'Entities', 'aw-seo-extras' ),
		);
	}

	/** Views with their counts. */
	protected function get_views() {
		global $wpdb;
		$t       = aw_si_table( 'keywords' );
		$current = $this->arg( 'view' ) ? $this->arg( 'view' ) : 'all';
		$out     = array();
		foreach ( $this->view_sql() as $key => $sql ) {
			$n           = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE {$sql}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- fixed conditions.
			$url         = add_query_arg( array( 'view' => $key ), aw_si_url( 'keywords' ) );
			$out[ $key ] = '<a href="' . esc_url( $url ) . '"' . ( $key === $current ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $this->view_labels()[ $key ] ) . ' <span class="count">(' . esc_html( number_format_i18n( $n ) ) . ')</span></a>';
		}
		return $out;
	}

	/**
	 * Role, page type and language filters above the list.
	 *
	 * @param string $which top or bottom.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		echo '<div class="alignleft actions">';
		$this->dropdown( 'role', __( 'All roles', 'aw-seo-extras' ), array_map( 'strval', aw_si_roles() ) );
		$this->dropdown(
			'page_type',
			__( 'All page types', 'aw-seo-extras' ),
			array(
				'hub'       => __( 'Hub', 'aw-seo-extras' ),
				'directory' => __( 'Directory', 'aw-seo-extras' ),
				'topic'     => __( 'Topic', 'aw-seo-extras' ),
				'guide'     => __( 'Guide', 'aw-seo-extras' ),
				'key-page'  => __( 'Key page', 'aw-seo-extras' ),
				'new-row'   => __( 'Added 21 Oct', 'aw-seo-extras' ),
			)
		);
		$this->dropdown(
			'lang',
			__( 'All languages', 'aw-seo-extras' ),
			array(
				'en'       => __( 'English', 'aw-seo-extras' ),
				'hi'       => __( 'Hindi', 'aw-seo-extras' ),
				'hinglish' => __( 'Hinglish', 'aw-seo-extras' ),
			)
		);
		submit_button( __( 'Filter', 'aw-seo-extras' ), '', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * One filter dropdown.
	 *
	 * @param string $name    Request field.
	 * @param string $all     Label of the empty choice.
	 * @param array  $options value => label.
	 */
	private function dropdown( string $name, string $all, array $options ): void {
		$cur = $this->arg( $name );
		echo '<label class="screen-reader-text" for="aw-si-' . esc_attr( $name ) . '">' . esc_html( $all ) . '</label>';
		echo '<select name="' . esc_attr( $name ) . '" id="aw-si-' . esc_attr( $name ) . '"><option value="">' . esc_html( $all ) . '</option>';
		foreach ( $options as $value => $label ) {
			echo '<option value="' . esc_attr( (string) $value ) . '"' . selected( $cur, (string) $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	/** Query one page of keyword records for the current view, filters, search and sort. */
	public function prepare_items() {
		global $wpdb;
		$t       = aw_si_table( 'keywords' );
		$where   = array();
		$args    = array();
		$view    = $this->arg( 'view' );
		$views   = $this->view_sql();
		$where[] = $views[ $view ] ?? '1=1';
		$role    = (int) $this->arg( 'role' );
		if ( $role && isset( aw_si_roles()[ $role ] ) ) {
			$where[] = 'roles & %d';
			$args[]  = $role;
		}
		foreach ( array( 'page_type', 'lang' ) as $col ) {
			if ( '' !== $this->arg( $col ) ) {
				$where[] = "{$col} = %s";
				$args[]  = $this->arg( $col );
			}
		}
		$search = isset( $_REQUEST['s'] ) ? trim( sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- read-only search.
		if ( '' !== $search ) {
			$where[] = 'label LIKE %s';
			$args[]  = '%' . $wpdb->esc_like( $search ) . '%';
		}
		$orderby = $this->arg( 'orderby' );
		$cols    = array(
			'label'    => 'label',
			'priority' => "IF(priority_override <> '', priority_override, priority)",
			'impr28'   => 'impr28',
			'clicks28' => 'clicks28',
			'pos28'    => 'pos28 IS NULL, pos28',
			'change'   => '(pos_prev - pos28)',
			'trends'   => 'trends',
		);
		$order   = 'asc' === $this->arg( 'order' ) ? 'ASC' : 'DESC';
		if ( ! isset( $cols[ $orderby ] ) ) {
			$orderby = 'impr28';
		}
		$by = $cols[ $orderby ];
		if ( 'pos28' === $orderby ) {
			$by = 'pos28 IS NULL, pos28 ' . $order;
		} else {
			$by .= ' ' . $order;
		}
		$per   = $this->get_items_per_page( 'aw_si_keywords_per_page', 50 );
		$paged = max( 1, $this->get_pagenum() );
		$sql   = implode( ' AND ', $where );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- conditions built from fixed parts with placeholders.
		$total       = (int) $wpdb->get_var( $args ? $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE {$sql}", $args ) : "SELECT COUNT(*) FROM {$t} WHERE {$sql}" );
		$query       = "SELECT * FROM {$t} WHERE {$sql} ORDER BY {$by}, label ASC LIMIT %d OFFSET %d";
		$this->items = $wpdb->get_results( $wpdb->prepare( $query, array_merge( $args, array( $per, ( $paged - 1 ) * $per ) ) ) );
		// phpcs:enable
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'label' );
		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per,
			)
		);
	}

	/** Message for an empty list. */
	public function no_items() {
		if ( ! get_option( 'aw_si_seeded' ) ) {
			esc_html_e( 'Nothing here yet: the first daily run (07:10 IST) seeds the keyword map and reads Search Console. Settings → Run now starts it at once.', 'aw-seo-extras' );
			return;
		}
		esc_html_e( 'No keywords match.', 'aw-seo-extras' );
	}

	/**
	 * Bulk checkbox.
	 *
	 * @param object $item Keyword record.
	 */
	protected function column_cb( $item ) {
		return '<input type="checkbox" name="kw[]" value="' . (int) $item->id . '">';
	}

	/**
	 * Keyword, its roles and note, and the priority row actions.
	 *
	 * @param object $item Keyword record.
	 */
	protected function column_label( $item ) {
		$out   = '<strong>' . esc_html( $item->label ) . '</strong>';
		$roles = array();
		foreach ( aw_si_roles() as $bit => $name ) {
			if ( (int) $item->roles & $bit ) {
				$roles[] = $name;
			}
		}
		if ( 'en' !== $item->lang ) {
			$roles[] = 'hi' === $item->lang ? __( 'Hindi', 'aw-seo-extras' ) : __( 'Hinglish', 'aw-seo-extras' );
		}
		if ( (int) $item->entity ) {
			$roles[] = __( 'Entity', 'aw-seo-extras' );
		}
		if ( $roles ) {
			$out .= ' — <span class="post-state">' . esc_html( implode( ', ', $roles ) ) . '</span>';
		}
		if ( '' !== $item->note ) {
			$out .= '<br><span class="description">' . esc_html( $item->note ) . '</span>';
		}
		$actions = array();
		foreach ( array( 'P1', 'P2', 'P3' ) as $p ) {
			if ( $p !== $item->priority_override ) {
				/* translators: %s: priority such as P1. */
				$actions[ 'set_' . strtolower( $p ) ] = '<a href="' . esc_url( aw_si_kw_action_url( 'override_' . strtolower( $p ), (int) $item->id ) ) . '">' . esc_html( sprintf( __( 'Set %s', 'aw-seo-extras' ), $p ) ) . '</a>';
			}
		}
		if ( '' !== $item->priority_override ) {
			$actions['clear'] = '<a href="' . esc_url( aw_si_kw_action_url( 'override_clear', (int) $item->id ) ) . '">' . esc_html__( 'Use calculated', 'aw-seo-extras' ) . '</a>';
		}
		return $out . $this->row_actions( $actions );
	}

	/**
	 * Priority: the owner's override, or the calculated one.
	 *
	 * @param object $item Keyword record.
	 */
	protected function column_priority( $item ) {
		if ( '' !== $item->priority_override ) {
			/* translators: 1: owner's priority, 2: calculated priority. */
			return esc_html( sprintf( __( '%1$s (owner; calculated %2$s)', 'aw-seo-extras' ), $item->priority_override, $item->priority ? $item->priority : '–' ) );
		}
		return esc_html( $item->priority ? $item->priority : '–' );
	}

	/**
	 * Status, and "not ranking" for a tracked keyword with no impressions.
	 *
	 * @param object $item Keyword record.
	 */
	protected function column_status( $item ) {
		$labels = array(
			'has-page' => __( 'Has page', 'aw-seo-extras' ),
			'gap'      => __( 'Gap', 'aw-seo-extras' ),
			'conflict' => __( 'Conflict', 'aw-seo-extras' ),
		);
		$s      = $labels[ $item->status ] ?? '–';
		if ( ( (int) $item->roles & AW_SI_ROLE_TRACKED ) && 0 === (int) $item->impr28 ) {
			$s .= ' · ' . __( 'not ranking', 'aw-seo-extras' );
		}
		return esc_html( $s );
	}

	/**
	 * Owning page, or the page Google shows most.
	 *
	 * @param object $item Keyword record.
	 */
	protected function column_page( $item ) {
		if ( '' === $item->page ) {
			return '–';
		}
		if ( 0 === strpos( $item->page, '/' ) && false === strpos( $item->page, '{' ) ) {
			return '<a href="' . esc_url( home_url( $item->page ) ) . '" target="_blank" rel="noopener">' . esc_html( $item->page ) . '</a>';
		}
		return esc_html( $item->page );
	}

	/**
	 * Position change: the 28 days before against the last 28 days.
	 *
	 * @param object $item Keyword record.
	 */
	protected function column_change( $item ) {
		if ( null === $item->pos28 || null === $item->pos_prev ) {
			return '–';
		}
		$d = round( (float) $item->pos_prev - (float) $item->pos28, 1 );
		if ( abs( $d ) < 0.1 ) {
			return '0';
		}
		/* translators: %s: positions gained or lost. */
		$text = $d > 0 ? sprintf( __( 'up %s', 'aw-seo-extras' ), number_format_i18n( $d, 1 ) ) : sprintf( __( 'down %s', 'aw-seo-extras' ), number_format_i18n( -$d, 1 ) );
		return '<span class="dashicons dashicons-arrow-' . ( $d > 0 ? 'up' : 'down' ) . '-alt" aria-hidden="true"></span> ' . esc_html( $text );
	}

	/**
	 * Latest live Google check.
	 *
	 * @param object $item Keyword record.
	 */
	protected function column_live( $item ) {
		if ( '' === $item->live_txt && null === $item->live_pos ) {
			return '–';
		}
		$pos = null !== $item->live_pos ? '#' . number_format_i18n( (float) $item->live_pos ) : $item->live_txt;
		return esc_html( $pos ) . ( $item->live_date ? '<br><span class="description">' . esc_html( mysql2date( 'j M Y', $item->live_date ) ) . '</span>' : '' );
	}

	/**
	 * Suggested action.
	 *
	 * @param object $item Keyword record.
	 */
	protected function column_action( $item ) {
		return esc_html( aw_si_actions()[ $item->action ] ?? '' );
	}

	/**
	 * Numeric columns.
	 *
	 * @param object $item Keyword record.
	 * @param string $col  Column key.
	 */
	protected function column_default( $item, $col ) {
		switch ( $col ) {
			case 'impr28':
			case 'clicks28':
				return esc_html( number_format_i18n( (int) $item->$col ) );
			case 'pos28':
				return null === $item->pos28 ? '–' : esc_html( number_format_i18n( (float) $item->pos28, 1 ) );
			case 'trends':
				return null === $item->trends ? '–' : esc_html( number_format_i18n( (int) $item->trends ) );
		}
		return '';
	}
}
