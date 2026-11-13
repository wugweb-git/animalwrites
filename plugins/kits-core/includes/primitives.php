<?php
/**
 * A term's address, or '' when WordPress cannot make one (get_term_link returns a WP_Error for a missing term or
 * taxonomy; printed or put in schema it raised notices). Use this instead of get_term_link in templates and schema.
 *
 * @param WP_Term|int|string $term     Term.
 * @param string             $taxonomy Taxonomy, when $term is not an object.
 * @return string
 */
if ( ! function_exists( 'kits_term_url' ) ) { // aw-seo-extras carries a copy for a site without Kits Core
	function kits_term_url( $term, $taxonomy = '' ) {
		$url = get_term_link( $term, $taxonomy );
		return is_wp_error( $url ) ? '' : (string) $url;
	}
}

/**
 * Kits primitives (Phase 11 B.2): markup helpers so every page uses the same component for the same job.
 * Styles: themes/kits-by-wugweb/assets/css/kit-primitives.css. Behaviour (sheet, show more): kit.js.
 *
 *  kits_tag()            static metadata (type, language, "Template")          span.kit-tag
 *  kits_chip()           a tag that navigates (categories)                      a.kit-chip
 *  kits_badge()          state: verified, unconfirmed, featured, sponsored, ad, closed, new
 *  kits_eyebrow()        small label that carries information                   span.kit-eyebrow
 *  kits_count()          number beside a label                                  span.kit-count
 *  kits_datecard()       a highlighted date as a calendar page (timelines, deadlines) time.kit-datecard
 *  kits_datecard_word()  a timeline step with no date yet, on the same calendar page   span.kit-datecard--undated
 *  kits_timeline()       milestones as a timeline: calendar pages, states, connectors   ol.kit-dateline
 *  kits_milestone_state() a milestone's state (past, present, ongoing, future, undated), Asia/Kolkata
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

/**
 * Timeline calendar (owner, 13 Nov 2026: one calendar system for every campaign and editorial timeline). One calendar
 * page, one geometry everywhere; the campaign changes only its colours (--camp-mark, --camp-on-mark, --camp-dark). Styles:
 * kit-primitives.css (.kit-datecard, .kit-dateline).
 *
 * A milestone is an array with these fields (all optional except one of date / word / title):
 *   date        'Y-m-d', 'Y-m' (no day) or 'Y' (year only); or year / month / day as separate fields
 *   end         'Y-m-d': the last day of an ongoing activity (a date range)
 *   state       an editorial override: past, present, ongoing, future (empty: worked out from the date)
 *   current     true: the step that is happening now, though it has no date ("Now")
 *   key         true: the campaign's key date (its motif as the stamp)
 *   word        the step's word when it has no date ("Now", "No date set"), shown above its words, never on the calendar
 *   title       short heading (HTML allowed)
 *   description the words under it (HTML allowed)
 *   html        the step's words as rendered HTML, for lists migrated from content (used as they are)
 *   icon        a kit-ico name, shown before the title
 *   link        URL, with link_text
 *   media       attachment ID: an image shows small, any other file as a link with its title
 *   class       extra li classes kept from the content (is-shown, ...); attrs: extra li attributes as HTML
 *
 * States: past, present, ongoing, future, and undated (no date set: never a made-up date or an empty calendar).
 * The state comes from the date, compared in Asia/Kolkata, unless the milestone sets one.
 */

/**
 * Today in Asia/Kolkata, as Y-m-d (filterable for tests: kits_timeline_today).
 *
 * @return string
 */
function kits_timeline_today() {
	$today = ( new DateTimeImmutable( 'now', new DateTimeZone( 'Asia/Kolkata' ) ) )->format( 'Y-m-d' );
	return (string) apply_filters( 'kits_timeline_today', $today );
}

/**
 * A milestone's date as a range: array( first day, last day, precision ), each Y-m-d; empty strings when it has no date.
 *
 * @param array $m Milestone.
 * @return array{0:string,1:string,2:string} Start, end, precision (day, month, year or '').
 */
function kits_milestone_range( array $m ) {
	$date = trim( (string) ( $m['date'] ?? '' ) );
	if ( '' === $date && ! empty( $m['year'] ) ) {
		$date = sprintf( '%04d', (int) $m['year'] ) . ( ! empty( $m['month'] ) ? sprintf( '-%02d', (int) $m['month'] ) . ( ! empty( $m['day'] ) ? sprintf( '-%02d', (int) $m['day'] ) : '' ) : '' );
	}
	if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $d ) && checkdate( (int) $d[2], (int) $d[3], (int) $d[1] ) ) {
		$start = $date;
		$prec  = 'day';
		$last  = $date;
	} elseif ( preg_match( '/^(\d{4})-(\d{2})$/', $date, $d ) && (int) $d[2] >= 1 && (int) $d[2] <= 12 ) {
		$start = $date . '-01';
		$prec  = 'month';
		$last  = $date . '-' . gmdate( 't', (int) gmmktime( 12, 0, 0, (int) $d[2], 1, (int) $d[1] ) );
	} elseif ( preg_match( '/^\d{4}$/', $date ) ) {
		$start = $date . '-01-01';
		$prec  = 'year';
		$last  = $date . '-12-31';
	} else {
		return array( '', '', '' );
	}
	$end = (string) ( $m['end'] ?? '' );
	if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end ) && $end >= $start ) {
		$last = $end;
	}
	return array( $start, $last, $prec );
}

/**
 * A milestone's state: past, present, ongoing, future or undated. An editorial override (state) wins; otherwise the
 * date decides, in Asia/Kolkata: a range (end) that holds today is ongoing, a day (or month, year) that is today is
 * present, before today past, after today future. With no date: present when it is the current step, else undated.
 *
 * @param array  $m     Milestone.
 * @param string $today Y-m-d (default: today in Asia/Kolkata).
 * @return string
 */
function kits_milestone_state( array $m, $today = '' ) {
	$set = strtolower( trim( (string) ( $m['state'] ?? '' ) ) );
	if ( in_array( $set, array( 'past', 'present', 'ongoing', 'future' ), true ) ) {
		return $set;
	}
	$today = '' !== $today ? (string) $today : kits_timeline_today();
	list( $start, $last ) = kits_milestone_range( $m );
	if ( '' === $start ) {
		return ! empty( $m['current'] ) ? 'present' : 'undated';
	}
	if ( $today >= $start && $today <= $last ) {
		return ! empty( $m['end'] ) && $last > $start ? 'ongoing' : 'present';
	}
	if ( ! empty( $m['current'] ) ) {
		return 'present';
	}
	return $start > $today ? 'future' : 'past';
}

/**
 * The words a screen reader hears for a state (the status is never colour alone: each state also has its own badge and
 * edge, kit-primitives.css).
 *
 * @param string $state State.
 * @return string
 */
function kits_milestone_state_label( $state ) {
	$labels = array(
		'past'    => __( 'Completed', 'kits-core' ),
		'present' => __( 'Current', 'kits-core' ),
		'ongoing' => __( 'Ongoing', 'kits-core' ),
		'future'  => __( 'Upcoming', 'kits-core' ),
		'undated' => __( 'Date not set', 'kits-core' ),
	);
	return $labels[ $state ] ?? '';
}

/**
 * The calendar page (owner, 13 Nov 2026: "when highlighting dates use a calendar icon, artistic, to make a date card;
 * should be universal"): two binder rings, the month on a strip, a perforation, the day large, the year small, the next
 * page under it. One fixed geometry (64 x 80) for every date, 1 to 31; the campaign changes only the colours.
 *
 * @param string|int|array $when    A date strtotime() reads (Y-m-d), Y-m, a Unix time, or a milestone array.
 * @param string           $variant Kept for older callers ('sm'); the size is one everywhere.
 * @param string           $state   past, present, ongoing, future (empty: no state on the card itself).
 * @return string A time element, or '' when the date cannot be read.
 */
function kits_datecard( $when, $variant = '', $state = '' ) {
	unset( $variant ); // one size everywhere (owner, 13 Nov 2026: "same geometry everywhere")
	$m = is_array( $when ) ? $when : array( 'date' => is_numeric( $when ) ? wp_date( 'Y-m-d', (int) $when, new DateTimeZone( 'Asia/Kolkata' ) ) : (string) $when );
	list( $start, , $prec ) = kits_milestone_range( $m );
	if ( '' === $start && ! is_array( $when ) && ! is_numeric( $when ) ) {
		$ts = strtotime( (string) $when . ' 12:00' );
		if ( $ts ) {
			$start = gmdate( 'Y-m-d', $ts );
			$prec  = 'day';
		}
	}
	if ( '' === $start ) {
		return '';
	}
	$ts    = (int) gmmktime( 12, 0, 0, (int) substr( $start, 5, 2 ), (int) substr( $start, 8, 2 ), (int) substr( $start, 0, 4 ) );
	$month = 'year' === $prec ? '' : gmdate( 'M', $ts );
	$day   = 'day' === $prec ? gmdate( 'j', $ts ) : '';
	$iso   = 'day' === $prec ? $start : ( 'month' === $prec ? substr( $start, 0, 7 ) : substr( $start, 0, 4 ) );
	$label = $state ? kits_milestone_state_label( $state ) : '';
	return '<time class="kit-datecard' . ( 'day' !== $prec ? ' kit-datecard--nodate' : '' ) . '"' . ( $state ? ' data-state="' . esc_attr( $state ) . '"' : '' ) . ' datetime="' . esc_attr( $iso ) . '">'
		. '<span class="kit-datecard-month">' . esc_html( $month ) . '</span> '
		. '<span class="kit-datecard-day"' . ( '' === $day ? ' aria-hidden="true">&ndash;' : '>' . esc_html( $day ) ) . '</span> '
		. '<span class="kit-datecard-year">' . esc_html( substr( $start, 0, 4 ) ) . '</span>'
		. ( $label ? '<span class="screen-reader-text">, ' . esc_html( $label ) . '</span>' : '' ) . '</time>';
}

/**
 * The calendar page for a step with no date ("Now", "No date set"): the same page and size, its strip and day left
 * blank (a dash), never a made-up date. The step's word is printed with its words (kits_timeline), so here only a
 * screen reader hears it.
 *
 * @param string $word    The step's word.
 * @param string $variant Kept for older callers.
 * @param string $state   undated (default) or present (the current step).
 * @return string
 */
function kits_datecard_word( $word, $variant = '', $state = 'undated' ) {
	unset( $variant );
	$label = kits_milestone_state_label( $state );
	return '<span class="kit-datecard kit-datecard--undated" data-state="' . esc_attr( $state ) . '">'
		. '<span class="kit-datecard-month" aria-hidden="true"></span>'
		. '<span class="kit-datecard-day" aria-hidden="true">&ndash;</span>'
		. '<span class="kit-datecard-year" aria-hidden="true"></span>'
		. '<span class="screen-reader-text">' . esc_html( trim( $word . ( $label && $label !== $word ? ', ' . $label : '' ), ', ' ) ) . '</span></span>';
}

/**
 * A timeline of milestones (kit-dateline): each step its calendar page, its state, and the line to the next step in
 * that state's style. Phones: one column, the page beside the words, a vertical line. 768 to 1199: two steps a row,
 * the line only inside a row. From 1200: one row, the line across the pages' middles. kit-primitives.css.
 *
 * @param array $items Milestones (see above).
 * @param array $args  class (the list's own classes), when_class, what_class (kept from migrated markup), tag (ol/div).
 * @return string
 */
function kits_timeline( array $items, array $args = array() ) {
	$today = kits_timeline_today();
	$tag   = 'div' === ( $args['tag'] ?? 'ol' ) ? 'div' : 'ol';
	$child = 'ol' === $tag ? 'li' : 'div';
	$out   = '';
	foreach ( $items as $m ) {
		$state = kits_milestone_state( (array) $m, $today );
		$card  = 'undated' === $state || ( 'present' === $state && '' === kits_milestone_range( (array) $m )[0] )
			? kits_datecard_word( (string) ( $m['word'] ?? '' ), '', $state )
			: kits_datecard( (array) $m, '', $state );
		$what = '';
		if ( ! empty( $m['word'] ) ) {
			$what .= '<span class="kit-dateline-word">' . esc_html( (string) $m['word'] ) . '</span> ';
		}
		if ( ! empty( $m['title'] ) ) {
			$what .= '<strong class="kit-dateline-title">' . ( ! empty( $m['icon'] ) ? '<span class="kit-ico kit-ico--' . esc_attr( (string) $m['icon'] ) . '" aria-hidden="true"></span> ' : '' ) . wp_kses_post( (string) $m['title'] ) . '</strong> ';
		}
		if ( ! empty( $m['description'] ) ) {
			$what .= '<span class="kit-dateline-desc">' . wp_kses_post( (string) $m['description'] ) . '</span> ';
		}
		if ( ! empty( $m['html'] ) ) {
			$what .= (string) $m['html']; // already rendered post content (the_content), kept as it is
		}
		if ( ! empty( $m['media'] ) ) {
			$id    = (int) $m['media'];
			$what .= wp_attachment_is_image( $id )
				? ' <span class="kit-dateline-media">' . wp_get_attachment_image( $id, 'thumbnail', false, array( 'loading' => 'lazy' ) ) . '</span>'
				: ' <a class="kit-dateline-doc" href="' . esc_url( (string) wp_get_attachment_url( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a>';
		}
		if ( ! empty( $m['link'] ) ) {
			$what .= ' <a class="kit-dateline-link" href="' . esc_url( (string) $m['link'] ) . '">' . esc_html( (string) ( $m['link_text'] ?? '' ) ) . '</a>';
		}
		$cls  = trim( (string) ( $m['class'] ?? '' ) . ( ! empty( $m['key'] ) ? ' is-key' : '' ) );
		$out .= '<' . $child . ( '' !== $cls ? ' class="' . esc_attr( $cls ) . '"' : '' ) . ' data-state="' . esc_attr( $state ) . '"'
			. ( in_array( $state, array( 'present', 'ongoing' ), true ) ? ' aria-current="step"' : '' )
			. ( ! empty( $m['attrs'] ) ? ' ' . trim( (string) $m['attrs'] ) : '' ) . '>'
			. '<span class="kit-dateline-when' . ( ! empty( $args['when_class'] ) ? ' ' . esc_attr( (string) $args['when_class'] ) : '' ) . '">' . $card . '</span>'
			. '<span class="kit-dateline-what' . ( ! empty( $args['what_class'] ) ? ' ' . esc_attr( (string) $args['what_class'] ) : '' ) . '">' . trim( $what ) . '</span>'
			. '</' . $child . '>';
	}
	$cls = trim( (string) ( $args['class'] ?? '' ) . ' kit-dateline' );
	return '<' . $tag . ' class="' . esc_attr( $cls ) . '" style="--dl-n:' . count( $items ) . '">' . $out . '</' . $tag . '>';
}

function kits_count( $n ) {
	return '<span class="kit-count">' . esc_html( number_format_i18n( (int) $n ) ) . '</span>';
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
 * Inline script placed right after a <details> that phones show folded: folds it while the page is still being parsed,
 * so the first paint already has the phone layout and nothing moves when kit.js runs (it was a 0.16–0.21 layout shift
 * on the hubs, 22 Oct 2026). $kind 'sheet' = the filter sheet (kit.js: below 768px, class is-sheet, closed);
 * 'fold' = details[data-kit-fold] (closed at or below its width unless data-kit-keep-open). Without JavaScript the
 * element stays open, as before.
 *
 * @param string $kind 'sheet' or 'fold'.
 * @return string
 */
function kits_fold_now( $kind ) {
	$js = 'sheet' === $kind
		? 'if(matchMedia("(max-width: 767px)").matches){d.classList.add("is-sheet");d.removeAttribute("open");}'
		: 'if(matchMedia("(max-width: "+(parseInt(d.getAttribute("data-kit-fold"),10)||1199)+"px)").matches&&!d.hasAttribute("data-kit-keep-open")){d.open=false;}';
	return '<script>(function(d){if(d&&window.matchMedia){' . $js . '}})(document.currentScript&&document.currentScript.previousElementSibling);</script>';
}

/**
 * Filter bar: the filters in one row (dropdowns from kits_filter_dropdown), Sort on the
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
			. '</div></details>' . kits_fold_now( 'sheet' );
	}
	$html .= $sort . '</form><p class="screen-reader-text" role="status" aria-live="polite" data-kit-filter-status></p>';
	if ( $active ) {
		$html .= '<div class="kit-filter-active" aria-label="Active filters">';
		foreach ( $active as $a ) {
			$html .= '<span class="kit-removable">' . esc_html( $a[0] ) . '<a href="' . esc_url( $a[1] ) . '" aria-label="' . esc_attr( 'Remove ' . $a[0] ) . '"><span class="kit-ico kit-ico--x" aria-hidden="true"></span></a></span>';
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

