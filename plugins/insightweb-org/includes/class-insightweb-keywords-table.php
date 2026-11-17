<?php
/**
 * The Keywords tab as a core list table (WP_List_Table): views, role/intent/topic filters, search, sorting, bulk and
 * row priority overrides, screen options and pagination. The one change it makes is a priority override, and only to
 * InsightWeb's own keyword record — never to the site.
 *
 * No hand-built markup and no custom CSS: the table is core WP_List_Table, so it inherits WordPress admin styling and
 * accessibility and, when the shared admin shell (insightweb-shell) is present, renders unchanged as one of the shell's
 * "parts" (admin.php). The columns are the Search-Console / GA4 figures read through Site Kit. The override flow
 * functions live in admin.php.
 *
 * @package InsightWeb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * The Keywords list table.
 */
class InsightWeb_Keywords_Table extends WP_List_Table {

	/**
	 * Set up the list: one row per keyword record.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'insightweb_keyword',
				'plural'   => 'insightweb_keywords',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Request value, sanitised as a key (read-only filters, no state change).
	 *
	 * @param string $name Request field.
	 * @return string
	 */
	private function arg( string $name ): string {
		return isset( $_REQUEST[ $name ] ) ? sanitize_key( wp_unslash( $_REQUEST[ $name ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- read-only filters.
	}

	/**
	 * Columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'cb'       => '<input type="checkbox">',
			'label'    => __( 'Keyword', 'insightweb' ),
			'priority' => __( 'Priority', 'insightweb' ),
			'status'   => __( 'Status', 'insightweb' ),
			'topic'    => __( 'Topic', 'insightweb' ),
			'page'     => __( 'Page', 'insightweb' ),
			'impr28'   => __( 'Impressions (28 days)', 'insightweb' ),
			'clicks28' => __( 'Clicks', 'insightweb' ),
			'pos28'    => __( 'Position', 'insightweb' ),
			'change'   => __( 'Change', 'insightweb' ),
			'live'     => __( 'Live check', 'insightweb' ),
			'trends'   => __( 'Trends', 'insightweb' ),
			'action'   => __( 'Suggested action', 'insightweb' ),
		);
	}

	/**
	 * Sortable columns and their first direction.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	protected function get_sortable_columns() {
		return array(
			'label'    => array( 'label', false ),
			'priority' => array( 'priority', false ),
			'topic'    => array( 'topic', false ),
			'impr28'   => array( 'impr28', true ),
			'clicks28' => array( 'clicks28', true ),
			'pos28'    => array( 'pos28', false ),
			'change'   => array( 'change', true ),
			'trends'   => array( 'trends', true ),
		);
	}

	/**
	 * The column that holds the row actions.
	 *
	 * @return string
	 */
	protected function get_primary_column_name() {
		return 'label';
	}

	/**
	 * Priority overrides: the only change this list makes, to its own table.
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions() {
		return array(
			'override_p1'    => __( 'Set priority P1', 'insightweb' ),
			'override_p2'    => __( 'Set priority P2', 'insightweb' ),
			'override_p3'    => __( 'Set priority P3', 'insightweb' ),
			'override_clear' => __( 'Use the calculated priority', 'insightweb' ),
		);
	}

	/**
	 * Views with their counts.
	 *
	 * @return array<string, string>
	 */
	protected function get_views() {
		$current = $this->arg( 'view' ) ? $this->arg( 'view' ) : 'all';
		$counts  = insightweb_view_counts();
		$labels  = insightweb_view_labels();
		$out     = array();
		foreach ( array_keys( insightweb_view_sql() ) as $key ) {
			$url         = add_query_arg( array( 'view' => $key ), insightweb_url( 'keywords' ) );
			$out[ $key ] = sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
				esc_url( $url ),
				$key === $current ? ' class="current" aria-current="page"' : '',
				esc_html( $labels[ $key ] ?? $key ),
				esc_html( number_format_i18n( (int) ( $counts[ $key ] ?? 0 ) ) )
			);
		}
		return $out;
	}

	/**
	 * Role, intent and topic filters above the list.
	 *
	 * @param string $which top or bottom.
	 * @return void
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		echo '<div class="alignleft actions">';
		$this->dropdown( 'role', __( 'All roles', 'insightweb' ), array_map( 'strval', insightweb_roles() ) );
		$this->dropdown( 'intent', __( 'All intents', 'insightweb' ), insightweb_intents() );
		$topics = insightweb_topics();
		if ( $topics ) {
			$this->dropdown( 'topic', __( 'All topics', 'insightweb' ), array_combine( $topics, $topics ), true );
		}
		submit_button( __( 'Filter', 'insightweb' ), '', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * One filter dropdown.
	 *
	 * @param string                    $name    Request field.
	 * @param string                    $all     Label of the empty choice.
	 * @param array<string|int, string> $options value => label.
	 * @param bool                      $text    Values are text (topic names), not keys.
	 * @return void
	 */
	private function dropdown( string $name, string $all, array $options, bool $text = false ): void {
		$cur = $text
			? ( isset( $_REQUEST[ $name ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $name ] ) ) : '' ) // phpcs:ignore WordPress.Security.NonceVerification -- read-only filter.
			: $this->arg( $name );
		echo '<label class="screen-reader-text" for="insightweb-f-' . esc_attr( $name ) . '">' . esc_html( $all ) . '</label>';
		echo '<select name="' . esc_attr( $name ) . '" id="insightweb-f-' . esc_attr( $name ) . '"><option value="">' . esc_html( $all ) . '</option>';
		foreach ( $options as $value => $label ) {
			echo '<option value="' . esc_attr( (string) $value ) . '"' . selected( $cur, (string) $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Query one page of keyword records for the current view, filters, search and sort.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$f                     = insightweb_kw_filters();
		$f['per']              = $this->get_items_per_page( 'insightweb_keywords_per_page', 50 );
		$f['paged']            = $this->get_pagenum();
		$result                = insightweb_kw_list( $f );
		$this->items           = $result['items'];
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'label' );
		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $f['per'],
			)
		);
	}

	/**
	 * Message for an empty list.
	 *
	 * @return void
	 */
	public function no_items() {
		if ( ! insightweb_kw_ready() ) {
			esc_html_e( 'Setting up — the keyword table has not been created yet.', 'insightweb' );
			return;
		}
		if ( ! insightweb_site_kit_active() ) {
			esc_html_e( 'No keywords yet. Activate and connect Site Kit by Google, and InsightWeb will read Search Console and GA4 on the next daily run.', 'insightweb' );
			return;
		}
		esc_html_e( 'No keywords match. The first daily run reads Search Console and builds this list.', 'insightweb' );
	}

	/**
	 * Bulk checkbox.
	 *
	 * @param object $item Keyword record.
	 * @return string
	 */
	protected function column_cb( $item ) {
		return '<input type="checkbox" name="kw[]" value="' . (int) $item->id . '">';
	}

	/**
	 * Keyword, its roles and note, and the priority row actions.
	 *
	 * @param object $item Keyword record.
	 * @return string
	 */
	protected function column_label( $item ) {
		$out   = '<strong>' . esc_html( $item->label ) . '</strong>';
		$roles = array();
		foreach ( insightweb_roles() as $bit => $name ) {
			if ( (int) $item->roles & $bit ) {
				$roles[] = $name;
			}
		}
		if ( $roles ) {
			$out .= ' — <span class="post-state">' . esc_html( implode( ', ', $roles ) ) . '</span>';
		}
		if ( '' !== (string) $item->note ) {
			$out .= '<br><span class="description">' . esc_html( (string) $item->note ) . '</span>';
		}
		$actions = array();
		foreach ( array( 'P1', 'P2', 'P3' ) as $p ) {
			if ( $p !== $item->priority_override ) {
				/* translators: %s: priority such as P1. */
				$actions[ 'set_' . strtolower( $p ) ] = '<a href="' . esc_url( insightweb_kw_action_url( 'override_' . strtolower( $p ), (int) $item->id ) ) . '">' . esc_html( sprintf( __( 'Set %s', 'insightweb' ), $p ) ) . '</a>';
			}
		}
		if ( '' !== (string) $item->priority_override ) {
			$actions['clear'] = '<a href="' . esc_url( insightweb_kw_action_url( 'override_clear', (int) $item->id ) ) . '">' . esc_html__( 'Use calculated', 'insightweb' ) . '</a>';
		}
		return $out . $this->row_actions( $actions );
	}

	/**
	 * Priority: the owner's override, or the calculated one.
	 *
	 * @param object $item Keyword record.
	 * @return string
	 */
	protected function column_priority( $item ) {
		if ( '' !== (string) $item->priority_override ) {
			/* translators: 1: owner's priority, 2: calculated priority. */
			return esc_html( sprintf( __( '%1$s (owner; calculated %2$s)', 'insightweb' ), $item->priority_override, $item->priority ? $item->priority : '–' ) );
		}
		return esc_html( $item->priority ? $item->priority : '–' );
	}

	/**
	 * Status, and "not ranking" for a tracked keyword with no impressions.
	 *
	 * @param object $item Keyword record.
	 * @return string
	 */
	protected function column_status( $item ) {
		$labels = array(
			'has-page' => __( 'Has page', 'insightweb' ),
			'gap'      => __( 'Gap', 'insightweb' ),
			'conflict' => __( 'Conflict', 'insightweb' ),
		);
		$s      = $labels[ $item->status ] ?? '–';
		if ( ( (int) $item->roles & INSIGHTWEB_ROLE_TRACKED ) && 0 === (int) $item->impr28 ) {
			$s .= ' · ' . __( 'not ranking', 'insightweb' );
		}
		return esc_html( $s );
	}

	/**
	 * Owning page, or the page Google shows most. Linked when it is a local path.
	 *
	 * @param object $item Keyword record.
	 * @return string
	 */
	protected function column_page( $item ) {
		if ( '' === (string) $item->page ) {
			return '–';
		}
		if ( 0 === strpos( (string) $item->page, '/' ) && false === strpos( (string) $item->page, '{' ) ) {
			return '<a href="' . esc_url( home_url( $item->page ) ) . '" target="_blank" rel="noopener">' . esc_html( $item->page ) . '</a>';
		}
		return esc_html( (string) $item->page );
	}

	/**
	 * Position change: the 28 days before against the last 28 days.
	 *
	 * @param object $item Keyword record.
	 * @return string
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
		$text = $d > 0 ? sprintf( __( 'up %s', 'insightweb' ), number_format_i18n( $d, 1 ) ) : sprintf( __( 'down %s', 'insightweb' ), number_format_i18n( -$d, 1 ) );
		return '<span class="dashicons dashicons-arrow-' . ( $d > 0 ? 'up' : 'down' ) . '-alt" aria-hidden="true"></span> ' . esc_html( $text );
	}

	/**
	 * Latest live Google check.
	 *
	 * @param object $item Keyword record.
	 * @return string
	 */
	protected function column_live( $item ) {
		if ( '' === (string) $item->live_txt && null === $item->live_pos ) {
			return '–';
		}
		$pos = null !== $item->live_pos ? '#' . number_format_i18n( (float) $item->live_pos ) : (string) $item->live_txt;
		return esc_html( $pos ) . ( $item->live_date ? '<br><span class="description">' . esc_html( mysql2date( 'j M Y', $item->live_date ) ) . '</span>' : '' );
	}

	/**
	 * Suggested action.
	 *
	 * @param object $item Keyword record.
	 * @return string
	 */
	protected function column_action( $item ) {
		return esc_html( insightweb_actions()[ $item->action ] ?? '' );
	}

	/**
	 * Numeric columns and the topic.
	 *
	 * @param object $item Keyword record.
	 * @param string $col  Column key.
	 * @return string
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
			case 'topic':
				return '' === (string) $item->topic ? '–' : esc_html( (string) $item->topic );
		}
		return '';
	}
}
