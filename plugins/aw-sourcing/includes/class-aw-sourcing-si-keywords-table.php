<?php
/**
 * Tools → Search Insights → Sourcing keywords: a core list table, sortable and read-only (owner, 24 Oct 2026).
 * Loaded only when the tab is shown.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Per Google News search: items fetched and published (News, Library) and their pages' Search Console figures. */
class AW_Sourcing_SI_Keywords_Table extends WP_List_Table {

	/** @var array From aw_sourcing_keyword_stats(). */
	private $stats;

	/** @var array path => figures over 28 days, from Search Insights (empty when it has none yet). */
	private $pages = array();

	/** @var array path => figures over 3 months: 90 days, as long as Search Insights keeps daily rows (rollup.php) (owner, 24 Oct 2026). */
	private $pages3 = array();

	/**
	 * @param array $stats aw_sourcing_keyword_stats() result.
	 */
	public function __construct( array $stats ) {
		parent::__construct( array( 'singular' => 'keyword', 'plural' => 'keywords', 'ajax' => false, 'screen' => 'tools_page_aw-search-insights' ) );
		$this->stats = $stats;
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
		return array(
			'term'    => 'Keyword',
			'news_f'  => 'Fetched: News',
			'legal_f' => 'Fetched: Library',
			'news_p'  => 'Published: News',
			'legal_p' => 'Published: Library',
			'impr'    => 'Impressions (28 days)',
			'clicks'  => 'Clicks (28 days)',
			'impr3'   => 'Impressions (3 months)',
			'clicks3' => 'Clicks (3 months)',
		);
	}

	/** Every column sorts; numbers start high. */
	protected function get_sortable_columns() {
		$out = array();
		foreach ( array_keys( $this->get_columns() ) as $c ) {
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
		$rows = (array) ( $this->stats['rows'] ?? array() );
		$ids  = array();
		foreach ( $rows as $r ) {
			$ids = array_merge( $ids, $r['news']['published'], $r['legal']['published'] ); // only published items have a page (a draft's link is ?p=, which would read as the home page)
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
			foreach ( array_unique( array_merge( $r['news']['published'], $r['legal']['published'] ) ) as $id ) {
				$key = $paths[ (int) $id ] ?? '';
				$m   = $this->pages[ $key ] ?? null;
				$m3  = $this->pages3[ $key ] ?? null;
				$c  += $m ? (int) $m['clicks'] : 0;
				$i  += $m ? (int) $m['impr'] : 0;
				$c3 += $m3 ? (int) $m3['clicks'] : 0;
				$i3 += $m3 ? (int) $m3['impr'] : 0;
			}
			$items[] = array(
				'term'      => $r['term'],
				'fixed'     => ! empty( $r['fixed'] ),
				'listed'    => ! empty( $r['listed'] ),
				'estimated' => ! empty( $r['estimated'] ),
				'news_f'    => count( $r['news']['fetched'] ),
				'legal_f'   => count( $r['legal']['fetched'] ),
				'news_p'    => count( $r['news']['published'] ),
				'legal_p'   => count( $r['legal']['published'] ),
				'impr'      => $this->has_data ? $i : null,
				'clicks'    => $this->has_data ? $c : null,
				'impr3'     => $this->has_data ? $i3 : null,
				'clicks3'   => $this->has_data ? $c3 : null,
			);
		}
		$by  = sanitize_key( wp_unslash( $_GET['orderby'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- sorting a read-only list
		$by  = isset( $this->get_columns()[ $by ] ) ? $by : 'news_f';
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
		if ( $item['fixed'] ) {
			$notes[] = 'Fixed search in the workers';
		} elseif ( ! $item['listed'] ) {
			$notes[] = 'No longer in the Include list';
		}
		if ( $item['estimated'] ) {
			$notes[] = 'Estimated';
		}
		return '<strong>' . esc_html( $item['term'] ) . '</strong>' . ( $notes ? ' — <span class="post-state">' . esc_html( implode( ', ', $notes ) ) . '</span>' : '' );
	}

	/**
	 * Numbers; a dash when Search Insights has no page figures yet.
	 *
	 * @param array  $item Row.
	 * @param string $col  Column.
	 */
	protected function column_default( $item, $col ) {
		return null === $item[ $col ] ? '—' : esc_html( number_format_i18n( (int) $item[ $col ] ) );
	}

	/** Empty list. */
	public function no_items() {
		echo 'No searches yet.';
	}

	/**
	 * Read-only: no bulk actions or pagination.
	 *
	 * @param string $which Top or bottom.
	 */
	protected function display_tablenav( $which ) {}
}
