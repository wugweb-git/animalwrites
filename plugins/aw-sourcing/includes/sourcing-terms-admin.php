<?php
/**
 * Section rules' saves (Rules → Section rules, the Kits shell's screen; POST sourceweb/v1/rules/section/save): the
 * ticked category and tag terms' slugs are stored in aw_sources['sections'][type]. A stored slug with no term stays
 * until it is unticked. A save writes nothing when nothing changed (same keys, values, types and order) and is logged
 * in the Change log as before.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One section's ticked terms, applied to the lists. A list whose set of slugs did not change is left as stored.
 *
 * @param array  $c      The lists (aw_sources_config()).
 * @param string $type   Post type.
 * @param array  $ticked categories|tags => term IDs.
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

/**
 * Save one section's lists (aw_sourcing_section_apply()), logged; a Save with no change writes nothing.
 *
 * @param string $type   Post type.
 * @param array  $ticked categories|tags => term IDs.
 * @param array  $gone   categories|tags => ticked stored slugs that have no term.
 * @return string saved or none.
 */
function aw_sourcing_section_save( $type, array $ticked, array $gone ) {
	$was              = (array) ( aw_sources_config()['sections'][ $type ] ?? array() );
	list( $c, $what ) = aw_sourcing_section_apply( aw_sources_config(), $type, $ticked, $gone );
	if ( ! $what ) {
		return 'none';
	}
	update_option( 'aw_sources', $c, false );
	kits_sourcing_log( 'sections', implode( ' · ', $what ), $was, $c['sections'][ $type ] ); // old → new (phase 3: Rules → Section rules)
	return 'saved';
}

/*
 * The old Categories and Sections forms posted the whole list to options.php (aw_sources[_aw_tab] = categories or
 * sections). A copy still open in another browser tab must not replace the option with its text: aw_sources_sanitize()
 * hands such a post here, and it changes nothing (the option is then not written).
 */
add_filter( 'aw_sources_save_categories', fn( $c ) => $c );
add_filter( 'aw_sources_save_sections', fn( $c ) => $c );
