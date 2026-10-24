<?php
/**
 * Settings → Sourcing → Sections as a core list table (owner, 24 Oct 2026): one row per section (aw_post_types()) with
 * the categories and tags it may use (aw_sources['sections']). Row action Edit opens the section's checklists
 * (sourcing-terms-admin.php). Read-only. Loaded only when the tab is shown.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The sections and their allowed categories and tags. */
class AW_Sourcing_Sections_Table extends WP_List_Table {

	/** How many tag names a row shows before "…". */
	const FEW = 8;

	public function __construct() {
		parent::__construct( array( 'singular' => 'section', 'plural' => 'sections', 'ajax' => false, 'screen' => 'settings_page_aw-sourcing' ) );
	}

	public function get_columns() {
		return array(
			'section'    => 'Section',
			'categories' => 'Allowed categories',
			'tags'       => 'Allowed tags',
		);
	}

	protected function get_primary_column_name() {
		return 'section';
	}

	/** As core, without a nonce or bulk actions: the list only reads. */
	protected function display_tablenav( $which ) {
		echo '<div class="tablenav ' . esc_attr( $which ) . '"><br class="clear"></div>';
	}

	public function prepare_items() {
		$secs = (array) aw_sources_config()['sections'];
		$rows = array();
		foreach ( aw_post_types() as $type => $def ) {
			$rows[] = array(
				'type'       => $type,
				'label'      => $def[1],
				'categories' => array_values( (array) ( $secs[ $type ]['categories'] ?? array() ) ),
				'tags'       => array_values( (array) ( $secs[ $type ]['tags'] ?? array() ) ),
			);
		}
		$this->items           = $rows;
		$this->_column_headers = array( $this->get_columns(), array(), array(), 'section' );
	}

	/**
	 * Term names for slugs, in the stored order; a slug with no term shows as "slug (no such …)".
	 *
	 * @param string[] $slugs    Slugs.
	 * @param string   $taxonomy category or post_tag.
	 * @return string[]
	 */
	private static function names( array $slugs, $taxonomy ) {
		$out = array();
		foreach ( $slugs as $slug ) {
			$t     = get_term_by( 'slug', $slug, $taxonomy );
			$out[] = $t ? $t->name : urldecode( $slug ) . ( 'category' === $taxonomy ? ' (no such category)' : ' (no such tag)' );
		}
		return $out;
	}

	protected function column_section( $item ) {
		$edit = add_query_arg( array( 'edit' => $item['type'] ), admin_url( 'options-general.php?page=aw-sourcing&tab=sections' ) );
		$acts = array( 'edit' => '<a href="' . esc_url( $edit ) . '">Edit</a>' );
		return '<strong><a class="row-title" href="' . esc_url( $edit ) . '">' . esc_html( $item['label'] ) . '</a></strong>' . $this->row_actions( $acts );
	}

	protected function column_categories( $item ) {
		if ( ! $item['categories'] ) {
			return 'Every category';
		}
		return esc_html( implode( ', ', self::names( $item['categories'], 'category' ) ) );
	}

	protected function column_tags( $item ) {
		$n = count( $item['tags'] );
		if ( ! $n ) {
			return '<span aria-label="None">—</span>';
		}
		$names = self::names( $item['tags'], 'post_tag' );
		if ( $n <= self::FEW ) {
			return esc_html( implode( ', ', $names ) );
		}
		/* translators: %s: number of tags. */
		return '<strong>' . esc_html( sprintf( _n( '%s tag', '%s tags', $n ), number_format_i18n( $n ) ) ) . ':</strong> ' . esc_html( implode( ', ', array_slice( $names, 0, self::FEW ) ) . ', …' );
	}

	public function no_items() {
		echo 'No sections.';
	}
}
