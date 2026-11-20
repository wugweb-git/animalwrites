<?php
/**
 * Tools → Search Insights → SourceWeb keywords: the keyword results report, a core list table, sortable and read-only
 * (owner, 24 Oct 2026; rebuilt as a report on the live keyword list, 17 Nov 2026).
 *
 * One row per keyword RECORD from the live keyword table (aw_sourcing_kw_report): its real roles, whether its Google
 * News search is on, the keyword's own Search Console impressions, the items each brought in over 30 days (News and
 * Library apart) and those published items' pages' Search Console figures. Deduped by record, with no invented roles.
 * Loaded only when the tab is shown.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The keyword results report: per keyword, its roles, search on/off, items fetched and published, and Search Console. */
class AW_Sourcing_SI_Keywords_Table extends WP_List_Table {

	/** @var array From aw_sourcing_kw_report(): {records: bool, rows: array[]}. */
	private $report;

	/** @var array path => figures over 28 days, from Search Insights (empty when it has none yet). */
	private $pages = array();

	/** @var array path => figures over 3 months (90 days). */
	private $pages3 = array();

	/** @var bool The rows carry real roles (phase 2 migrated); the roles column is shown. */
	private $has_roles = false;

	/**
	 * @param array $report aw_sourcing_kw_report() result.
	 */
	public function __construct( array $report ) {
		parent::__construct( array( 'singular' => 'keyword', 'plural' => 'keywords', 'ajax' => false ) );
		$this->report    = $report;
		$this->has_roles = ! empty( $report['records'] );
	}

	/** @var bool Search Insights has stored a Search Console run (page rows exist), so a missing page means 0. */
	private $has_data = false;

	/** @var bool Search Console has run before, but not within 28 days. */
	private $stale = false;

	/** Search Console ran once but not within the 28-day window. */
	public function page_data_stale() {
		return $this->stale;
	}

	/** Search Insights has page data at all (not only for these items). */
	public function has_page_data() {
		return $this->has_data;
	}

	/** Columns. */
	public function get_columns() {
		$cols = array( 'term' => 'Keyword' );
		if ( $this->has_roles ) {
			$cols['roles']  = 'Roles';
			$cols['search'] = 'Search';
		}
		return $cols + array(
			'news_f'  => 'Fetched: News',
			'legal_f' => 'Fetched: Library',
			'news_p'  => 'Published: News',
			'legal_p' => 'Published: Library',
			'kw_impr' => 'Keyword impressions (28 days)',
			'impr'    => 'Page impressions (28 days)',
			'clicks'  => 'Page clicks (28 days)',
			'impr3'   => 'Page impressions (3 months)',
			'clicks3' => 'Page clicks (3 months)',
		);
	}

	/** Numeric columns sort (not the roles/search labels); numbers start high. */
	protected function get_sortable_columns() {
		$out = array();
		foreach ( array_keys( $this->get_columns() ) as $c ) {
			if ( in_array( $c, array( 'roles', 'search' ), true ) ) {
				continue;
			}
			$out[ $c ] = array( $c, 'term' !== $c );
		}
		return $out;
	}

	/** The keyword column carries the notes. */
	protected function get_primary_column_name() {
		return 'term';
	}

	/** The site path Search Insights keys a page by. */
	private function path( $id ) {
		$url = (string) get_permalink( $id );
		if ( function_exists( 'aw_si_path' ) ) {
			return aw_si_path( $url );
		}
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		return '' === $path ? '/' : $path;
	}

	/** Rows with their figures, sorted. */
	public function prepare_items() {
		$rows = (array) ( $this->report['rows'] ?? array() );
		$ids  = array();
		foreach ( $rows as $r ) {
			$ids = array_merge( $ids, (array) ( $r['pub_ids'] ?? array() ) ); // only published items have a page
		}
		$ids   = array_values( array_unique( array_map( 'intval', $ids ) ) );
		$paths = array();
		// Page data only counts when Search Console last ran within the 28-day window; an older run means the window is empty.
		$ok             = function_exists( 'aw_si_runs' ) ? (int) ( aw_si_runs()['gsc']['ok'] ?? 0 ) : 0;
		$this->has_data = function_exists( 'aw_sourcing_si_available' ) && aw_sourcing_si_available() && $ok > time() - 28 * DAY_IN_SECONDS;
		$this->stale    = $ok && ! $this->has_data;
		if ( $ids && $this->has_data && function_exists( 'aw_si_page_metrics' ) ) {
			_prime_post_caches( $ids, false, false ); // one query for every item, not one per permalink
			foreach ( $ids as $id ) {
				$paths[ $id ] = $this->path( $id );
			}
			$list         = array_values( array_filter( array_unique( $paths ) ) );
			$this->pages  = aw_si_page_metrics( $list, 28 );
			$this->pages3 = aw_si_page_metrics( $list, 90 );
		}
		$items = array();
		foreach ( $rows as $r ) {
			$c  = 0;
			$i  = 0;
			$c3 = 0;
			$i3 = 0;
			foreach ( (array) ( $r['pub_ids'] ?? array() ) as $id ) {
				$key = $paths[ (int) $id ] ?? '';
				$m   = $this->pages[ $key ] ?? null;
				$m3  = $this->pages3[ $key ] ?? null;
				$c  += $m ? (int) $m['clicks'] : 0;
				$i  += $m ? (int) $m['impr'] : 0;
				$c3 += $m3 ? (int) $m3['clicks'] : 0;
				$i3 += $m3 ? (int) $m3['impr'] : 0;
			}
			$items[] = array(
				'term'      => (string) $r['label'],
				'roles'     => (int) ( $r['roles'] ?? 0 ),
				'lang'      => (string) ( $r['lang'] ?? 'en' ),
				'is_search' => ! empty( $r['is_search'] ),
				'search_on' => ! empty( $r['search_on'] ),
				'dismissed' => ! empty( $r['dismissed'] ),
				'estimated' => ! empty( $r['estimated'] ),
				'news_f'    => (int) $r['news_f'],
				'legal_f'   => (int) $r['legal_f'],
				'news_p'    => (int) $r['news_p'],
				'legal_p'   => (int) $r['legal_p'],
				'kw_impr'   => null === ( $r['kw_impr'] ?? null ) ? null : (int) $r['kw_impr'],
				'impr'      => $this->has_data ? $i : null,
				'clicks'    => $this->has_data ? $c : null,
				'impr3'     => $this->has_data ? $i3 : null,
				'clicks3'   => $this->has_data ? $c3 : null,
			);
		}
		$by  = sanitize_key( wp_unslash( $_GET['orderby'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- sorting a read-only list
		$by  = isset( $this->get_sortable_columns()[ $by ] ) ? $by : 'news_f';
		$asc = 'asc' === sanitize_key( wp_unslash( $_GET['order'] ?? ( 'term' === $by ? 'asc' : 'desc' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification -- sorting a read-only list
		usort(
			$items,
			function ( $a, $b ) use ( $by, $asc ) {
				$c = 'term' === $by ? strcasecmp( $a['term'], $b['term'] ) : ( (int) $a[ $by ] <=> (int) $b[ $by ] );
				$c = $c ? $c : strcasecmp( $a['term'], $b['term'] );
				return $asc ? $c : -$c;
			}
		);
		$this->items           = $items;
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'term' );
	}

	/**
	 * The keyword and its notes.
	 *
	 * @param array $item Row.
	 */
	protected function column_term( $item ) {
		$notes = array();
		if ( 'en' !== $item['lang'] ) {
			$notes[] = 'Not searched (not English)';
		}
		if ( $item['dismissed'] ) {
			$notes[] = 'Suggestion dismissed';
		}
		if ( $item['estimated'] ) {
			$notes[] = 'Estimated';
		}
		return '<strong>' . esc_html( $item['term'] ) . '</strong>' . ( $notes ? ' — <span class="post-state">' . esc_html( implode( ', ', $notes ) ) . '</span>' : '' );
	}

	/**
	 * The keyword's roles, as the Keywords screen names them.
	 *
	 * @param array $item Row.
	 */
	protected function column_roles( $item ) {
		if ( ! function_exists( 'aw_sourcing_kw_roles' ) ) {
			return '—';
		}
		$names = array();
		foreach ( aw_sourcing_kw_roles() as $bit => $label ) {
			if ( (int) $item['roles'] & $bit ) {
				$names[] = $label;
			}
		}
		return $names ? esc_html( implode( ', ', $names ) ) : '—';
	}

	/**
	 * Whether the keyword's Google News search runs (only Include and Search keywords, English, are searches).
	 *
	 * @param array $item Row.
	 */
	protected function column_search( $item ) {
		if ( ! $item['is_search'] ) {
			return '—';
		}
		return $item['search_on']
			? '<span class="post-state" style="color:#007017">On</span>'
			: '<span class="post-state" style="color:#b32d2e">Off</span>';
	}

	/**
	 * Numbers; a dash when Search Insights has no page figures yet, or the keyword has no impressions figure.
	 *
	 * @param array  $item Row.
	 * @param string $col  Column.
	 */
	protected function column_default( $item, $col ) {
		return null === ( $item[ $col ] ?? null ) ? '—' : esc_html( number_format_i18n( (int) $item[ $col ] ) );
	}

	/** Empty list. */
	public function no_items() {
		echo 'No keywords yet.';
	}

	/**
	 * Read-only: no bulk actions or pagination.
	 *
	 * @param string $which Top or bottom.
	 */
	protected function display_tablenav( $which ) {}
}
