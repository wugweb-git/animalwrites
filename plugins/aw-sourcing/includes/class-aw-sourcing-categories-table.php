<?php
/**
 * Sourcing → Categories as a core list table (owner, 24 Oct 2026): one row per WordPress category, plus
 * any slug in aw_sources['categories'] with no category behind it ("No such category"). Views All / With words /
 * Without words, search, sortable name and items, pagination with a screen option, row actions Edit words / Add words
 * and View items. Read-only: the words are edited on their own screen (sourcing-terms-admin.php). Loaded only when the
 * tab is shown.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Categories and the words that file an item under each. */
class AW_Sourcing_Categories_Table extends WP_List_Table {

	/** @var array Every row (see build()). */
	private $all = array();

	public function __construct() {
		parent::__construct( array( 'singular' => 'category', 'plural' => 'categories', 'ajax' => false, 'screen' => AW_SRC_SCREEN ) );
		$this->all = self::build();
	}

	/**
	 * Rows: slug, name, term (WP_Term or null), count, words (null when the category has none), sections (labels).
	 *
	 * @return array
	 */
	public static function build() {
		$c     = aw_sources_config();
		$words = (array) $c['categories'];
		$secs  = (array) $c['sections'];
		$types = aw_post_types();
		$in    = function ( $slug ) use ( $secs, $types ) {
			$out = array();
			foreach ( $types as $type => $def ) {
				$list = (array) ( $secs[ $type ]['categories'] ?? array() );
				if ( ! $list || in_array( $slug, $list, true ) ) { // an empty list is every category
					$out[] = $def[1];
				}
			}
			return $out;
		};
		$rows  = array();
		$terms = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false ) );
		foreach ( is_array( $terms ) ? $terms : array() as $t ) {
			$rows[ $t->slug ] = array(
				'slug'     => $t->slug,
				'name'     => $t->name,
				'term'     => $t,
				'count'    => (int) $t->count,
				'words'    => isset( $words[ $t->slug ] ) ? (array) $words[ $t->slug ] : null,
				'sections' => $in( $t->slug ),
			);
		}
		foreach ( $words as $slug => $list ) {
			$slug = (string) $slug;
			if ( ! isset( $rows[ $slug ] ) ) {
				$rows[ $slug ] = array( 'slug' => $slug, 'name' => $slug, 'term' => null, 'count' => 0, 'words' => (array) $list, 'sections' => $in( $slug ) );
			}
		}
		return array_values( $rows );
	}

	private function url( array $args = array() ) {
		return add_query_arg( $args, admin_url( 'admin.php?page=aw-sourcing&tab=categories' ) );
	}

	private function view() {
		$v = sanitize_key( $_GET['words'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		return in_array( $v, array( 'with', 'without' ), true ) ? $v : '';
	}

	private static function has_words( $row ) {
		return ! empty( $row['words'] );
	}

	protected function get_views() {
		$with = count( array_filter( $this->all, array( __CLASS__, 'has_words' ) ) );
		$out  = array();
		foreach ( array( '' => array( 'All', count( $this->all ) ), 'with' => array( 'With words', $with ), 'without' => array( 'Without words', count( $this->all ) - $with ) ) as $k => $v ) {
			$out[ '' === $k ? 'all' : $k ] = '<a href="' . esc_url( $this->url( $k ? array( 'words' => $k ) : array() ) ) . '"' . ( $this->view() === $k ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $v[0] ) . ' <span class="count">(' . esc_html( number_format_i18n( $v[1] ) ) . ')</span></a>';
		}
		return $out;
	}

	public function get_columns() {
		return array(
			'name'     => 'Category',
			'slug'     => 'Slug',
			'count'    => 'Items',
			'words'    => 'Words',
			'sections' => 'Sections',
		);
	}

	protected function get_sortable_columns() {
		return array( 'name' => array( 'name', false ), 'count' => array( 'count', true ) );
	}

	protected function get_primary_column_name() {
		return 'name';
	}

	/** As core, without a nonce: the list only reads (its GET form carries the page, tab and view). */
	protected function display_tablenav( $which ) {
		echo '<div class="tablenav ' . esc_attr( $which ) . '">';
		$this->pagination( $which );
		echo '<br class="clear"></div>';
	}

	public function prepare_items() {
		$v    = $this->view();
		$s    = mb_strtolower( sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$rows = array_values(
			array_filter(
				$this->all,
				fn( $x ) => ( '' === $v || ( 'with' === $v ) === self::has_words( $x ) )
					&& ( '' === $s || false !== mb_strpos( mb_strtolower( $x['name'] . ' ' . $x['slug'] . ' ' . implode( ' ', (array) $x['words'] ) ), $s ) )
			)
		);
		$by  = sanitize_key( $_GET['orderby'] ?? 'name' ); // phpcs:ignore WordPress.Security.NonceVerification
		$by  = 'count' === $by ? 'count' : 'name';
		$asc = 'desc' !== strtolower( sanitize_key( $_GET['order'] ?? ( 'count' === $by ? 'desc' : 'asc' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		usort(
			$rows,
			function ( $a, $b ) use ( $by, $asc ) {
				$c = 'count' === $by ? ( $a['count'] <=> $b['count'] ) : 0;
				$c = $c ? $c : strcasecmp( $a['name'], $b['name'] );
				return $asc ? $c : -$c;
			}
		);
		$per = $this->get_items_per_page( 'aw_categories_per_page', 50 );
		$this->set_pagination_args( array( 'total_items' => count( $rows ), 'per_page' => $per ) );
		$this->items           = array_slice( $rows, ( $this->get_pagenum() - 1 ) * $per, $per );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'name' );
	}

	/** A slug with no category behind it gets the plugins list's problem row under it. */
	public function single_row( $item ) {
		parent::single_row( $item );
		if ( ! $item['term'] ) {
			aw_sourcing_problem_row( $this, 'No such category: WordPress has no category with the slug "' . urldecode( $item['slug'] ) . '". Add the category, or remove its words.', 'warning' );
		}
	}

	protected function column_name( $item ) {
		$edit = $this->url( array( 'edit' => rawurlencode( $item['slug'] ) ) );
		$acts = array( 'edit' => '<a href="' . esc_url( $edit ) . '">' . ( self::has_words( $item ) ? 'Edit words' : 'Add words' ) . '</a>' );
		if ( $item['term'] ) {
			foreach ( kits_sourced_types() as $type ) {
				$obj = get_post_type_object( $type );
				if ( $obj ) {
					$acts[ 'view_' . $type ] = '<a href="' . esc_url( add_query_arg( array( 'post_type' => $type, 'category_name' => $item['slug'] ), admin_url( 'edit.php' ) ) ) . '">' . esc_html( 'View ' . $obj->labels->name ) . '</a>';
				}
			}
			$link  = get_edit_term_link( $item['term']->term_id, 'category' );
			$title = $link ? '<a class="row-title" href="' . esc_url( $link ) . '">' . esc_html( $item['name'] ) . '</a>' : esc_html( $item['name'] );
		} else {
			$title = esc_html( $item['name'] ) . ' <span class="description">(No such category)</span>';
		}
		return '<strong>' . $title . '</strong>' . $this->row_actions( $acts );
	}

	protected function column_slug( $item ) {
		return esc_html( urldecode( $item['slug'] ) );
	}

	protected function column_count( $item ) {
		return $item['term'] ? esc_html( number_format_i18n( $item['count'] ) ) : '—';
	}

	protected function column_words( $item ) {
		return self::has_words( $item ) ? esc_html( implode( ', ', $item['words'] ) ) : '<span aria-label="None">—</span>';
	}

	protected function column_sections( $item ) {
		if ( count( $item['sections'] ) === count( aw_post_types() ) ) {
			return 'All sections';
		}
		return $item['sections'] ? esc_html( implode( ', ', $item['sections'] ) ) : '<span aria-label="None">—</span>';
	}

	public function no_items() {
		echo 'No categories.';
	}
}
