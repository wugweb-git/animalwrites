<?php
/**
 * Kits primitives (Phase 11 B.2): markup helpers so every page uses the same component for the same job.
 * Styles: themes/kits-by-wugweb/assets/css/kit-primitives.css. Behaviour (sheet, show more): kit.js.
 *
 *  kits_tag()            static metadata (type, language, "Template")          span.kit-tag
 *  kits_chip()           a tag that navigates (categories)                      a.kit-chip
 *  kits_badge()          state: verified, unconfirmed, featured, sponsored, ad, closed, new
 *  kits_eyebrow()        small label that carries information                   span.kit-eyebrow
 *  kits_count()          number beside a label                                  span.kit-count
 *  kits_filter_group()   labelled group of filter pills (links; selected = aria-current)
 *  kits_filter_dropdown() one filter as a dropdown: a button with a checkbox list (several choices, counts)
 *  kits_sort_dropdown()  "Sort" as a dropdown of links (one choice)
 *  kits_filter_bar()     the filters in one row (dropdowns, Sort on the right), a "Filters" sheet on phones, and the
 *                        active filters as removable chips with "Clear all"
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function kits_tag( $text, $variant = '' ) {
	return '<span class="kit-tag' . ( $variant ? ' kit-tag--' . esc_attr( $variant ) : '' ) . '">' . esc_html( $text ) . '</span>';
}

function kits_chip( $text, $url, $variant = '' ) {
	return '<a class="kit-chip' . ( $variant ? ' kit-chip--' . esc_attr( $variant ) : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a>';
}

function kits_badge( $text, $variant ) {
	return '<span class="kit-badge kit-badge--' . esc_attr( $variant ) . '">' . esc_html( $text ) . '</span>';
}

function kits_eyebrow( $text ) {
	return '<span class="kit-eyebrow">' . esc_html( $text ) . '</span>';
}

function kits_count( $n ) {
	return '<span class="kit-count">' . esc_html( number_format_i18n( (int) $n ) ) . '</span>';
}

/**
 * One filter group.
 *
 * @param string $legend  Visible label.
 * @param array  $options List of [label, url, selected (bool), count (int|null)]. The first is usually "All".
 * @param int    $show    Options shown before "Show more".
 * @return string '' when there is at most one real choice and nothing is selected.
 */
function kits_filter_group( $legend, $options, $show = 8 ) {
	$real     = array_filter( $options, fn( $o ) => null !== ( $o[3] ?? null ) );
	$selected = array_filter( $options, fn( $o ) => ! empty( $o[2] ) && null !== ( $o[3] ?? null ) );
	if ( count( $real ) <= 1 && ! $selected ) {
		return '';
	}
	$id   = 'kit-fg-' . sanitize_title( $legend ) . '-' . wp_unique_id();
	$html = '<fieldset class="kit-filter-group" id="' . esc_attr( $id ) . '"><legend>' . esc_html( $legend ) . '</legend><div class="kit-filter-options">';
	foreach ( $options as $o ) {
		$html .= '<a class="kit-filter-pill" href="' . esc_url( $o[1] ) . '"' . ( ! empty( $o[2] ) ? ' aria-current="true"' : '' ) . '>' . esc_html( $o[0] )
			. ( isset( $o[3] ) && null !== $o[3] ? ' ' . kits_count( $o[3] ) : '' ) . '</a>';
	}
	$html .= '</div>';
	if ( count( $options ) > $show ) {
		$html .= '<button type="button" class="kit-filter-more" aria-expanded="false" aria-controls="' . esc_attr( $id ) . '" data-more="' . esc_attr( 'Show all (' . count( $options ) . ')' ) . '" data-less="Show fewer">' . esc_html( 'Show all (' . count( $options ) . ')' ) . '</button>';
	}
	return $html . '</fieldset>';
}

/**
 * One filter as a dropdown: a button (the filter's name, and how many are chosen) opening a list of checkboxes with
 * counts. Values with no items are not listed; a filter with one value and nothing chosen is not shown.
 *
 * @param string $legend  Visible label ("Category").
 * @param string $param   URL parameter ("category"). Several choices are sent as category=a,b (category[]=a&category[]=b without JavaScript).
 * @param array  $options List of [label, value, selected (bool), count (int)].
 * @return string
 */
function kits_filter_dropdown( $legend, $param, $options ) {
	$options  = array_values( array_filter( $options, fn( $o ) => ! empty( $o[2] ) || (int) ( $o[3] ?? 0 ) > 0 ) );
	$selected = count( array_filter( $options, fn( $o ) => ! empty( $o[2] ) ) );
	if ( count( $options ) <= 1 && ! $selected ) {
		return '';
	}
	$id   = 'kit-dd-' . sanitize_key( $param ) . '-' . wp_unique_id();
	$html = kits_dd_open( $id, '' ) . '<button type="button" class="kit-dd-btn' . ( $selected ? ' is-chosen' : '' ) . '" aria-controls="' . esc_attr( $id ) . '" aria-expanded="false" data-wp-bind--aria-expanded="callbacks.isOpen" data-wp-on--click="actions.toggle"><span class="kit-dd-label">' . esc_html( $legend ) . '</span>'
		. ( $selected ? ' ' . kits_count( $selected ) . '<span class="screen-reader-text"> chosen</span>' : '' ) . '<span class="kit-ico kit-ico--chevron-down" aria-hidden="true"></span></button>'
		. '<fieldset class="kit-dd-panel" id="' . esc_attr( $id ) . '" data-wp-bind--hidden="!callbacks.isOpen"><legend class="screen-reader-text">' . esc_html( $legend ) . '</legend><ul class="kit-dd-list">';
	foreach ( $options as $o ) {
		$html .= '<li><label class="kit-dd-opt"><input type="checkbox" class="kit-dd-check" name="' . esc_attr( $param ) . '[]" value="' . esc_attr( $o[1] ) . '"' . checked( ! empty( $o[2] ), true, false ) . '>'
			. ( empty( $o[4] ) ? '' : wp_kses( (string) $o[4], array( 'span' => array( 'class' => true, 'aria-hidden' => true ) ) ) ) . '<span class="kit-dd-text">' . esc_html( $o[0] ) . '</span>' . kits_count( (int) $o[3] ) . '</label></li>';
	}
	return kits_dd_render( $html . '</ul></fieldset></div>' );
}

/**
 * "Sort" as a dropdown of links; the current order is marked.
 *
 * @param array $options List of [label, url, current (bool)].
 * @return string
 */
function kits_sort_dropdown( $options ) {
	if ( count( $options ) < 2 ) {
		return '';
	}
	$cur  = array_values( array_filter( $options, fn( $o ) => ! empty( $o[2] ) ) );
	$id   = 'kit-sort-' . wp_unique_id();
	$html = kits_dd_open( $id, 'kit-dd--sort' ) . '<button type="button" class="kit-dd-btn" aria-controls="' . esc_attr( $id ) . '" aria-expanded="false" data-wp-bind--aria-expanded="callbacks.isOpen" data-wp-on--click="actions.toggle"><span class="kit-dd-label">Sort<span class="kit-dd-current">: ' . esc_html( $cur ? $cur[0][0] : $options[0][0] ) . '</span></span><span class="kit-ico kit-ico--chevron-down" aria-hidden="true"></span></button>'
		. '<ul class="kit-dd-panel kit-dd-list" id="' . esc_attr( $id ) . '" data-wp-bind--hidden="!callbacks.isOpen">';
	foreach ( $options as $o ) {
		$html .= '<li><a class="kit-dd-opt" href="' . esc_url( $o[1] ) . '"' . ( ! empty( $o[2] ) ? ' aria-current="true"' : '' ) . '>' . esc_html( $o[0] ) . '</a></li>';
	}
	return kits_dd_render( $html . '</ul></div>' );
}

/**
 * Filter bar: the filters in one row (pills from kits_filter_group, or dropdowns from kits_filter_dropdown), Sort on the
 * right, folded into a "Filters" sheet on phones by kit.js, and the active filters as removable chips with "Clear all".
 *
 * @param string $groups Filters' HTML.
 * @param array  $active List of [label, url without this value].
 * @param string $clear  URL with no filters (the form's address).
 * @param string $sort   Sort dropdown's HTML (kits_sort_dropdown), or ''.
 * @param array  $keep   Other URL parameters to keep when the filters are sent (name => value).
 * @return string
 */
function kits_filter_bar( $groups, $active, $clear, $sort = '', $keep = array() ) {
	if ( '' === trim( $groups ) && ! $active && '' === $sort ) {
		return '';
	}
	$n      = count( $active );
	$hidden = '';
	foreach ( $keep as $k => $v ) {
		$hidden .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
	}
	$html = '<form class="kit-filterbar" method="get" action="' . esc_url( $clear ) . '" data-kit-filterbar>' . $hidden;
	if ( false !== strpos( $groups, 'kit-dd--menu' ) ) { // one Filters menu: compact already, no phone sheet around it
		$html .= '<div class="kit-filters" role="toolbar" aria-label="Filters">' . $groups . '</div>';
	} elseif ( '' !== trim( $groups ) ) {
		$html .= '<details class="kit-filter-sheet" open><summary><span class="kit-ico kit-ico--sliders" aria-hidden="true"></span>' . esc_html( 'Filters' ) . ( $n ? ' ' . kits_count( $n ) : '' ) . '</summary>'
			. '<div class="kit-filters" role="toolbar" aria-label="Filters">' . $groups
			. '<noscript><button type="submit" class="kit-btn kit-btn--sm">Apply</button></noscript>'
			. '<div class="kit-filter-sheet-actions"><a class="kit-btn kit-btn--secondary" href="' . esc_url( $clear ) . '">Reset</a><button type="submit" class="kit-btn" data-kit-sheet-close>Apply</button></div>'
			. '</div></details>';
	}
	$html .= $sort . '</form><p class="screen-reader-text" role="status" aria-live="polite" data-kit-filter-status></p>';
	if ( $active ) {
		$html .= '<div class="kit-filter-active" aria-label="Active filters">';
		foreach ( $active as $a ) {
			$html .= '<span class="kit-removable">' . esc_html( $a[0] ) . '<a href="' . esc_url( $a[1] ) . '" aria-label="' . esc_attr( 'Remove ' . $a[0] ) . '">×</a></span>';
		}
		$html .= '<a class="kit-filter-clear" href="' . esc_url( $clear ) . '">Clear all</a></div>';
	}
	return $html;
}

/* ---------- Dropdown on the Interactivity API (kits/dropdown, assets/js/dropdown.js) ---------- */

add_action(
	'init',
	function () {
		$f = get_template_directory() . '/assets/js/dropdown.js'; // served by the Kits theme (kits-core has no public URL of its own)
		wp_register_script_module( 'kits-dropdown', get_template_directory_uri() . '/assets/js/dropdown.js', array( '@wordpress/interactivity' ), file_exists( $f ) ? (string) filemtime( $f ) : '1' );
	}
);

/** Opening tag of a dropdown (its own context: the id that is open). */
function kits_dd_open( $id, $class ) {
	return '<div class="kit-dd' . ( $class ? ' ' . esc_attr( $class ) : '' ) . '" data-wp-interactive="kits/dropdown" ' . wp_interactivity_data_wp_context( array( 'id' => $id ) )
		. ' data-wp-class--is-open="callbacks.isOpen" data-wp-on--keydown="actions.keys" data-wp-on-document--click="actions.outside">';
}

/** Process the directives (so the first paint is right) and load the module once. */
function kits_dd_render( $html ) {
	wp_enqueue_script_module( 'kits-dropdown' );
	return function_exists( 'wp_interactivity_process_directives' ) ? wp_interactivity_process_directives( $html ) : $html;
}

/**
 * One filter inside the combined "Filters" menu (used when a list has more than two filters): a fold with the filter's
 * name (and how many are chosen) and its checkboxes.
 */
function kits_filter_section( $legend, $param, $options ) {
	$options  = array_values( array_filter( $options, fn( $o ) => ! empty( $o[2] ) || (int) ( $o[3] ?? 0 ) > 0 ) );
	$selected = count( array_filter( $options, fn( $o ) => ! empty( $o[2] ) ) );
	if ( count( $options ) <= 1 && ! $selected ) {
		return '';
	}
	$html = '<details class="kit-dd-section"' . ( $selected ? ' open' : '' ) . '><summary><span>' . esc_html( $legend ) . '</span>' . ( $selected ? ' ' . kits_count( $selected ) : '' ) . '<span class="kit-ico kit-ico--chevron-down" aria-hidden="true"></span></summary><fieldset><legend class="screen-reader-text">' . esc_html( $legend ) . '</legend><ul class="kit-dd-list">';
	foreach ( $options as $o ) {
		$html .= '<li><label class="kit-dd-opt"><input type="checkbox" class="kit-dd-check" name="' . esc_attr( $param ) . '[]" value="' . esc_attr( $o[1] ) . '"' . checked( ! empty( $o[2] ), true, false ) . '><span class="kit-dd-text">' . esc_html( $o[0] ) . '</span>' . kits_count( (int) $o[3] ) . '</label></li>';
	}
	return $html . '</ul></fieldset></details>';
}

/** The combined "Filters" menu: one button; inside, each filter type as a fold. */
function kits_filter_menu( $sections, $chosen ) {
	if ( '' === trim( $sections ) ) {
		return '';
	}
	$id   = 'kit-dd-filters-' . wp_unique_id();
	$html = kits_dd_open( $id, 'kit-dd--menu' ) . '<button type="button" class="kit-dd-btn' . ( $chosen ? ' is-chosen' : '' ) . '" aria-controls="' . esc_attr( $id ) . '" aria-expanded="false" data-wp-bind--aria-expanded="callbacks.isOpen" data-wp-on--click="actions.toggle"><span class="kit-ico kit-ico--sliders" aria-hidden="true"></span><span class="kit-dd-label">Filters</span>'
		. ( $chosen ? ' ' . kits_count( $chosen ) . '<span class="screen-reader-text"> chosen</span>' : '' ) . '<span class="kit-ico kit-ico--chevron-down" aria-hidden="true"></span></button>'
		. '<div class="kit-dd-panel kit-dd-panel--menu" id="' . esc_attr( $id ) . '" data-wp-bind--hidden="!callbacks.isOpen">' . $sections . '<div class="kit-dd-menu-actions"><button type="submit" class="kit-btn kit-btn--sm">Apply</button></div></div>';
	return kits_dd_render( $html . '</div>' );
}

