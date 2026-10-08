<?php
/**
 * Kits primitives (Phase 11 B.2): markup helpers so every page uses the same component for the same job.
 * Styles: themes/kits-by-wugweb/assets/css/kit-primitives.css. Behaviour (sheet, show more): kit.js.
 *
 *  kits_tag()            static metadata (type, language, "Template")          span.kit-tag
 *  kits_chip()           a tag that navigates (topics)                          a.kit-chip
 *  kits_badge()          state: verified, unconfirmed, featured, sponsored, ad, closed, new
 *  kits_eyebrow()        small label that carries information                   span.kit-eyebrow
 *  kits_count()          number beside a label                                  span.kit-count
 *  kits_filter_group()   labelled group of filter pills (links; selected = aria-current)
 *  kits_filter_bar()     groups + phone "Filters" sheet + active filters with remove and "Clear all"
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
 * Filter bar: groups (folded into a "Filters" sheet on phones by kit.js) and the active filters.
 *
 * @param string $groups Groups' HTML (kits_filter_group).
 * @param array  $active List of [label, url without this value].
 * @param string $clear  URL with no filters.
 * @return string
 */
function kits_filter_bar( $groups, $active, $clear ) {
	if ( '' === trim( $groups ) && ! $active ) {
		return '';
	}
	$n    = count( $active );
	$html = '<details class="kit-filter-sheet" open><summary>' . esc_html( 'Filters' ) . ( $n ? ' ' . kits_count( $n ) : '' ) . '</summary>'
		. '<div class="kit-filters" role="group" aria-label="Filters">' . $groups
		. '<div class="kit-filter-sheet-actions"><a class="kit-btn kit-btn--secondary" href="' . esc_url( $clear ) . '">Reset</a><button type="button" class="kit-btn" data-kit-sheet-close>Apply</button></div>'
		. '</div></details>';
	if ( $active ) {
		$html .= '<div class="kit-filter-active" aria-label="Active filters">';
		foreach ( $active as $a ) {
			$html .= '<span class="kit-removable">' . esc_html( $a[0] ) . '<a href="' . esc_url( $a[1] ) . '" aria-label="' . esc_attr( 'Remove ' . $a[0] ) . '">×</a></span>';
		}
		$html .= '<a class="kit-filter-clear" href="' . esc_url( $clear ) . '">Clear all</a></div>';
	}
	return $html;
}
