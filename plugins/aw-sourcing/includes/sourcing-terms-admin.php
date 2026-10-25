<?php
/**
 * Sourcing → Categories and Sections on core screens (owner, 24 Oct 2026). Same option, same shape, same
 * tab addresses (&tab=categories, &tab=sections); the tabs stay registered in sourcing-lists.php.
 *
 * - Categories: a core list table, one row per WordPress category plus any slug in aw_sources['categories'] without
 *   one. "Edit words" (&edit=<slug>) is a form-table screen with that category's words, one per line. Saving changes
 *   only that slug's words; the other slugs keep their place.
 * - Sections: a core list table, one row per section (aw_post_types()). "Edit" (&edit=<type>) shows core category and
 *   tag checklists (wp_terms_checklist()); the ticked terms' slugs are stored in aw_sources['sections'][type]. A stored
 *   slug with no term stays until it is unticked.
 * - Both save through admin-post.php with a nonce, write nothing when nothing changed (same keys, values, types and
 *   order), and log the change in the Change log as before.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The Categories tab: the list, or one category's words (&edit=<slug>). */
function aw_sources_categories_tab() {
	$edit = sanitize_title( wp_unslash( $_GET['edit'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	aw_sourcing_terms_notice();
	if ( '' !== $edit ) {
		aw_sourcing_category_words_form( $edit );
		return;
	}
	echo '<p>An item gets every category whose words appear in its title or summary. Hindi works. The categories themselves are WordPress\'s own (name, slug, description and photo are edited there).</p>';
	if ( ! class_exists( 'WP_List_Table' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	}
	require_once __DIR__ . '/class-aw-sourcing-categories-table.php';
	$table = new AW_Sourcing_Categories_Table();
	$table->prepare_items();

	// Add words to a category that has none: core's category dropdown, opening the same edit screen.
	$none = array();
	foreach ( AW_Sourcing_Categories_Table::build() as $row ) {
		if ( $row['term'] && empty( $row['words'] ) ) {
			$none[] = $row['term']->term_id;
		}
	}
	if ( $none ) {
		echo '<form method="get" class="wp-clearfix"><input type="hidden" name="page" value="aw-sourcing"><input type="hidden" name="tab" value="categories"><p><label for="aw-cat-add"><strong>Add words to a category</strong></label> ';
		wp_dropdown_categories( array( 'taxonomy' => 'category', 'hide_empty' => 0, 'include' => $none, 'name' => 'edit', 'id' => 'aw-cat-add', 'value_field' => 'slug', 'orderby' => 'name', 'hierarchical' => true ) );
		submit_button( 'Add words', 'secondary', '', false );
		echo '</p></form>';
	}

	$table->views();
	$view = sanitize_key( $_GET['words'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	echo '<form method="get"><input type="hidden" name="page" value="aw-sourcing"><input type="hidden" name="tab" value="categories">' . ( in_array( $view, array( 'with', 'without' ), true ) ? '<input type="hidden" name="words" value="' . esc_attr( $view ) . '">' : '' );
	$table->search_box( 'Search categories', 'aw-cat' );
	$table->display();
	echo '</form>';
}

/**
 * One category's words in the core form-table layout.
 *
 * @param string $slug Category slug (sanitize_title form).
 */
function aw_sourcing_category_words_form( $slug ) {
	$back  = admin_url( 'admin.php?page=aw-sourcing&tab=categories' );
	$words = (array) aw_sources_config()['categories'];
	$term  = get_term_by( 'slug', $slug, 'category' );
	if ( ! $term && ! isset( $words[ $slug ] ) ) {
		echo '<div class="notice notice-error inline"><p>No such category. <a href="' . esc_url( $back ) . '">Back to Categories</a></p></div>';
		return;
	}
	$name = $term ? $term->name : urldecode( $slug );
	$has  = ! empty( $words[ $slug ] );
	echo '<h2>' . esc_html( ( $has ? 'Edit words: ' : 'Add words: ' ) . $name ) . '</h2>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_sourcing_category_words"><input type="hidden" name="slug" value="' . esc_attr( $slug ) . '">';
	wp_nonce_field( 'aw_sourcing_category_words_' . $slug );
	echo '<table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row">Category</th><td>';
	if ( $term ) {
		$link = get_edit_term_link( $term->term_id, 'category' );
		echo esc_html( $name ) . ' <code>' . esc_html( urldecode( $slug ) ) . '</code>' . ( $link ? ' <a href="' . esc_url( $link ) . '">Edit category</a>' : '' );
	} else {
		echo '<code>' . esc_html( urldecode( $slug ) ) . '</code> <span class="description">(No such category: WordPress has no category with this slug.)</span>';
	}
	echo '</td></tr>';
	echo '<tr><th scope="row"><label for="aw-cat-words">Words</label></th><td><textarea id="aw-cat-words" name="words" rows="12" class="large-text code" aria-describedby="aw-cat-words-description">' . esc_textarea( implode( "\n", (array) ( $words[ $slug ] ?? array() ) ) ) . '</textarea>'
		. '<p class="description" id="aw-cat-words-description">One per line (a comma also separates). Hindi works. An item whose title or summary has one of these words gets this category. Leave empty to stop filing items here by words.</p></td></tr>';
	echo '</tbody></table>';
	submit_button( 'Save words', 'primary', 'submit', false );
	echo ' <a href="' . esc_url( $back ) . '" class="button">Cancel</a></form>';
}

/**
 * A list of words or slugs as stored: trimmed, no empties, no repeats, in order.
 *
 * @param array $list Words.
 * @return string[]
 */
function aw_sourcing_terms_clean( array $list ) {
	return array_values( array_unique( array_filter( array_map( 'trim', array_map( 'strval', $list ) ), 'strlen' ) ) );
}

/**
 * One category's words from the edit screen, applied to the lists.
 *
 * @param array  $c    The lists (aw_sources_config()).
 * @param string $slug Category slug.
 * @param string $text Words, one per line (or comma separated).
 * @return array{0: array, 1: string} The lists, and what changed in words ('' when nothing changed: $c is untouched).
 */
function aw_sourcing_category_words_apply( array $c, $slug, $text ) {
	$old = (array) ( $c['categories'][ $slug ] ?? array() );
	$new = aw_sourcing_terms_clean( (array) preg_split( '/[\r\n,]+/u', (string) $text ) );
	if ( $new === aw_sourcing_terms_clean( $old ) ) {
		return array( $c, '' );
	}
	if ( $new ) {
		$c['categories'][ $slug ] = $new; // an existing slug keeps its place; a new one goes last
	} else {
		unset( $c['categories'][ $slug ] ); // no words: the category is no longer filed by words, as before
	}
	$what = kits_sourcing_diff( $slug, $old, $new );
	return array( $c, '' !== $what ? $what : $slug . ': words reordered' );
}

/** Save one category's words (only that slug in aw_sources['categories']). */
add_action(
	'admin_post_aw_sourcing_category_words',
	function () {
		$slug = sanitize_title( wp_unslash( $_POST['slug'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- the nonce is per slug, checked next
		if ( ! current_user_can( AW_SRC_CAP ) || '' === $slug || ! check_admin_referer( 'aw_sourcing_category_words_' . $slug ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		if ( aw_sourcing_phase() >= 2 ) { // phase 2: the lists are the keyword table's now; these screens are copies until the new ones arrive
			wp_die( 'The keyword lists moved into the keyword table (sourcing phase 2). They are edited on the new Keywords screen; this old form is read-only.', 'Sourcing', array( 'response' => 409, 'back_link' => true ) );
		}
		$c    = aw_sources_config();
		$back = admin_url( 'admin.php?page=aw-sourcing&tab=categories' );
		if ( ! get_term_by( 'slug', $slug, 'category' ) && ! isset( $c['categories'][ $slug ] ) ) {
			wp_safe_redirect( add_query_arg( 'aw_terms_done', 'nosuch', $back ) );
			exit;
		}
		list( $c, $what ) = aw_sourcing_category_words_apply( $c, $slug, sanitize_textarea_field( wp_unslash( $_POST['words'] ?? '' ) ) );
		if ( '' === $what ) { // nothing changed: nothing is written
			wp_safe_redirect( add_query_arg( array( 'aw_terms_done' => 'none', 'aw_terms_for' => rawurlencode( $slug ) ), $back ) );
			exit;
		}
		update_option( 'aw_sources', $c, false );
		kits_sourcing_log( 'categories', $what );
		wp_safe_redirect( add_query_arg( array( 'aw_terms_done' => 'saved', 'aw_terms_for' => rawurlencode( $slug ) ), $back ) );
		exit;
	}
);

/** The Sections tab: the list, or one section's checklists (&edit=<type>). */
function aw_sources_sections_tab() {
	$edit = sanitize_key( $_GET['edit'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	aw_sourcing_terms_notice();
	if ( '' !== $edit ) {
		aw_sourcing_section_form( $edit );
		return;
	}
	echo '<p>Which categories and tags each section uses. The workers pick only from these, and each section\'s filters show only these. A section with no category ticked uses every category.</p>';
	if ( ! class_exists( 'WP_List_Table' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	}
	require_once __DIR__ . '/class-aw-sourcing-sections-table.php';
	$table = new AW_Sourcing_Sections_Table();
	$table->prepare_items();
	$table->display();
}

/**
 * One section's categories and tags as core checklists, in the form-table layout.
 *
 * @param string $type Post type.
 */
function aw_sourcing_section_form( $type ) {
	$back  = aw_rules_url( 'sections' );
	$types = aw_rules_section_labels(); // the sections are the site's content types (rules.php)
	if ( ! isset( $types[ $type ] ) ) {
		echo '<div class="notice notice-error inline"><p>No such section. <a href="' . esc_url( $back ) . '">Back to Sections</a></p></div>';
		return;
	}
	if ( ! function_exists( 'wp_terms_checklist' ) ) {
		require_once ABSPATH . 'wp-admin/includes/template.php';
	}
	$sec = (array) ( aw_sources_config()['sections'][ $type ] ?? array() );
	echo '<h2>' . esc_html( 'Edit section: ' . $types[ $type ] ) . '</h2>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_sourcing_section"><input type="hidden" name="type" value="' . esc_attr( $type ) . '">';
	wp_nonce_field( 'aw_sourcing_section_' . $type );
	echo '<table class="form-table" role="presentation"><tbody>';
	$rows = array(
		'categories' => array( 'category', 'Allowed categories', 'None ticked: every category.', 'no such category' ),
		'tags'       => array( 'post_tag', 'Allowed tags', 'The tags the workers may give this section\'s items, and its filters show.', 'no such tag' ),
	);
	foreach ( $rows as $k => $r ) {
		list( $tax, $label, $help, $missing ) = $r;
		$ids  = array();
		$gone = array();
		foreach ( (array) ( $sec[ $k ] ?? array() ) as $slug ) {
			$t = get_term_by( 'slug', (string) $slug, $tax );
			if ( $t ) {
				$ids[] = (int) $t->term_id;
			} else {
				$gone[] = (string) $slug;
			}
		}
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td><fieldset><legend class="screen-reader-text">' . esc_html( $label ) . '</legend>';
		echo '<div id="taxonomy-' . esc_attr( $tax ) . '" class="categorydiv"><div class="tabs-panel"><ul class="categorychecklist form-no-clear">';
		wp_terms_checklist( 0, array( 'taxonomy' => $tax, 'selected_cats' => $ids, 'checked_ontop' => false ) );
		foreach ( $gone as $slug ) { // kept until unticked
			echo '<li><label class="selectit"><input type="checkbox" name="aw_missing[' . esc_attr( $k ) . '][]" value="' . esc_attr( $slug ) . '" checked> ' . esc_html( urldecode( $slug ) . ' (' . $missing . ')' ) . '</label></li>';
		}
		echo '</ul></div></div><p class="description">' . esc_html( $help ) . '</p></fieldset></td></tr>';
	}
	echo '</tbody></table>';
	submit_button( 'Save section', 'primary', 'submit', false );
	echo ' <a href="' . esc_url( $back ) . '" class="button">Cancel</a></form>';
}

/**
 * One section's ticked terms, applied to the lists. A list whose set of slugs did not change is left as stored.
 *
 * @param array  $c      The lists (aw_sources_config()).
 * @param string $type   Post type.
 * @param array  $ticked categories|tags => term IDs (the checklists' post_category[] and tax_input[post_tag][]).
 * @param array  $gone   categories|tags => ticked stored slugs that have no term.
 * @return array{0: array, 1: string[]} The lists, and what changed in words (empty when nothing changed).
 */
function aw_sourcing_section_apply( array $c, $type, array $ticked, array $gone ) {
	$what = array();
	foreach ( array( 'categories' => 'category', 'tags' => 'post_tag' ) as $k => $tax ) {
		$old = array_values( array_map( 'strval', (array) ( $c['sections'][ $type ][ $k ] ?? array() ) ) );
		$sel = array();
		foreach ( array_filter( array_map( 'absint', (array) ( $ticked[ $k ] ?? array() ) ) ) as $id ) {
			$t = get_term( $id, $tax );
			if ( $t instanceof WP_Term ) {
				$sel[] = sanitize_title( $t->slug );
			}
		}
		foreach ( array_map( 'sanitize_title', array_map( 'strval', (array) ( $gone[ $k ] ?? array() ) ) ) as $slug ) {
			if ( in_array( $slug, $old, true ) && ! get_term_by( 'slug', $slug, $tax ) ) { // only a stored slug with no term
				$sel[] = $slug;
			}
		}
		$sel = array_values( array_unique( $sel ) );
		$a   = array_values( array_unique( $old ) );
		$b   = $sel;
		sort( $a );
		sort( $b );
		if ( $a === $b ) {
			continue; // the same set: the stored list stays as it is
		}
		$new = array_values( array_unique( array_merge( array_intersect( $old, $sel ), array_diff( $sel, $old ) ) ) ); // stored order first, new ones after
		if ( ! isset( $c['sections'][ $type ] ) || ! is_array( $c['sections'][ $type ] ) ) {
			$c['sections'][ $type ] = array( 'categories' => array(), 'tags' => array() );
		}
		$c['sections'][ $type ][ $k ] = $new;
		$what[]                       = kits_sourcing_diff( $type . ' ' . $k, $old, $new );
	}
	return array( $c, array_values( array_filter( $what ) ) );
}

/** Save one section's lists: ticked term IDs → slugs; a list whose set did not change keeps its stored order. */
add_action(
	'admin_post_aw_sourcing_section',
	function () {
		$type = sanitize_key( $_POST['type'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification -- the nonce is per section, checked next
		if ( ! current_user_can( AW_SRC_CAP ) || ! isset( aw_rules_sections()[ $type ] ) || ! check_admin_referer( 'aw_sourcing_section_' . $type ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$slugs            = fn( $v ) => array_map( 'sanitize_title', array_map( 'strval', (array) $v ) );
		$tax_in           = isset( $_POST['tax_input'] ) && is_array( $_POST['tax_input'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['tax_input']['post_tag'] ?? array() ) ) : array();
		$gone             = isset( $_POST['aw_missing'] ) && is_array( $_POST['aw_missing'] ) ? wp_unslash( $_POST['aw_missing'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each list is sanitized just below
		$was              = (array) ( aw_sources_config()['sections'][ $type ] ?? array() );
		list( $c, $what ) = aw_sourcing_section_apply(
			aw_sources_config(),
			$type,
			array(
				'categories' => array_map( 'absint', (array) wp_unslash( $_POST['post_category'] ?? array() ) ),
				'tags'       => $tax_in,
			),
			array(
				'categories' => $slugs( $gone['categories'] ?? array() ),
				'tags'       => $slugs( $gone['tags'] ?? array() ),
			)
		);
		$back = aw_rules_url( 'sections' );
		if ( ! $what ) {
			wp_safe_redirect( add_query_arg( array( 'aw_terms_done' => 'none', 'aw_terms_for' => $type ), $back ) );
			exit;
		}
		update_option( 'aw_sources', $c, false );
		kits_sourcing_log( 'sections', implode( ' · ', $what ), $was, $c['sections'][ $type ] ); // old → new (phase 3: Rules → Section rules)
		wp_safe_redirect( add_query_arg( array( 'aw_terms_done' => 'saved', 'aw_terms_for' => $type ), $back ) );
		exit;
	}
);

/** After a Categories or Sections save: what happened, as a core notice. */
function aw_sourcing_terms_notice() {
	$done = sanitize_key( $_GET['aw_terms_done'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	$for  = urldecode( sanitize_text_field( wp_unslash( $_GET['aw_terms_for'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$tab  = sanitize_key( $_GET['tab'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	$sections = 'sections' === $tab || ( 'rules' === $tab && 'sections' === sanitize_key( $_GET['part'] ?? 'sections' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( $sections && isset( aw_rules_section_labels()[ $for ] ) ) {
		$for = aw_rules_section_labels()[ $for ];
	}
	if ( 'saved' === $done ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( ( $sections ? 'Saved the ' . $for . ' section.' : 'Saved the words for ' . $for . '.' ) . ' The workers use this from their next run.' ) . '</p></div>';
	} elseif ( 'none' === $done ) {
		echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( 'Nothing changed for ' . $for . '.' ) . '</p></div>';
	} elseif ( 'nosuch' === $done ) {
		echo '<div class="notice notice-error is-dismissible"><p>No such category.</p></div>';
	}
}
add_filter( 'removable_query_args', fn( $args ) => 'aw-sourcing' === sanitize_key( $_GET['page'] ?? '' ) ? array_merge( $args, array( 'aw_terms_done', 'aw_terms_for' ) ) : $args ); // phpcs:ignore WordPress.Security.NonceVerification -- only on this screen

// Screen options: categories per page (the list, not the edit screen).
add_action(
	'load-' . AW_SRC_SCREEN,
	function () {
		if ( 'categories' === sanitize_key( $_GET['tab'] ?? '' ) && empty( $_GET['edit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			add_screen_option( 'per_page', array( 'label' => 'Categories per page', 'default' => 50, 'option' => 'aw_categories_per_page' ) );
		}
	}
);
add_filter( 'set_screen_option_aw_categories_per_page', fn( $keep, $option, $value ) => max( 1, min( 500, (int) $value ) ), 10, 3 );

/*
 * The old Categories and Sections forms posted the whole list to options.php (aw_sources[_aw_tab] = categories or
 * sections). A copy still open in another browser tab must not replace the option with its text: aw_sources_sanitize()
 * hands such a post here, and it changes nothing (the option is then not written).
 */
add_filter( 'aw_sources_save_categories', fn( $c ) => $c );
add_filter( 'aw_sources_save_sections', fn( $c ) => $c );
