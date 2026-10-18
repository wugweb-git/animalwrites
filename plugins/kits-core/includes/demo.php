<?php
/**
 * [kits_demo]: every Kits primitive and component in every state, on the master palette and on the site's own palette
 * side by side (Phase 11 B.3), then the site's own blocks in their states (filter kits_demo_sections). Used on the private, noindex page /kits-demo/ (administrators only).
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Inline custom properties that put the master (Kits default) palette back inside one box. */
function kits_demo_master_vars() {
	$file = get_template_directory() . '/tokens/tokens.compiled.json';
	$json = file_exists( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : array(); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$css  = '';
	foreach ( (array) ( $json['tokens'] ?? array() ) as $name => $t ) {
		if ( 0 === strpos( $name, 'color-' ) || 0 === strpos( $name, 'radius-' ) ) {
			$css .= '--kit-' . $name . ':' . $t['sm'] . ';';
		}
	}
	return $css;
}

/** The primitives, once. */
function kits_demo_panel( $suffix = '' ) {
	$h  = fn( $t ) => '<h3 class="kit-demo-h">' . esc_html( $t ) . '</h3>';
	$o  = '';
	$o .= $h( '1 Tag (static)' ) . '<div class="kit-tags">' . kits_tag( 'Neutral' ) . kits_tag( 'Accent', 'accent' ) . kits_tag( 'Ink', 'ink' ) . kits_tag( 'Success', 'success' ) . kits_tag( 'Warning', 'warning' ) . kits_tag( 'Danger', 'danger' ) . kits_tag( 'Info', 'info' ) . kits_tag( 'Template', 'accent' ) . kits_tag( 'Hindi' ) . '</div>';
	$o .= $h( '2 Link chip' ) . '<div class="kit-tags">' . kits_chip( 'Default', '#' ) . '<a class="kit-chip is-demo-hover" href="#">Hover</a><a class="kit-chip is-demo-focus" href="#">Focus</a><a class="kit-chip is-demo-active" href="#">Active</a></div>'
		. '<div class="kit-tags kit-demo-ink">' . kits_chip( 'On ink', '#', 'on-ink' ) . '</div>';
	$o .= $h( '3 Filter pill' ) . '<div class="kit-filter-options"><a class="kit-filter-pill" href="#">Default ' . kits_count( 9 ) . '</a><a class="kit-filter-pill is-demo-hover" href="#">Hover</a><a class="kit-filter-pill is-demo-focus" href="#">Focus</a><a class="kit-filter-pill" aria-current="true" href="#">Selected ' . kits_count( 4 ) . '</a><a class="kit-filter-pill" aria-disabled="true" href="#">Disabled ' . kits_count( 0 ) . '</a><a class="kit-filter-pill is-loading" href="#">Loading</a></div>';
	$opts = array( array( 'All', '#', false, null ), array( 'Act', '#', true, 9 ), array( 'Rule', '#', false, 7 ), array( 'Judgment', '#', false, 1 ) );
	$o .= $h( '4 Filter bar (dropdowns, Sort) · 5 Active filters' ) . kits_filter_bar( kits_filter_dropdown( 'Kind', 'kind', array( array( 'Act', 'act', true, 9 ), array( 'Rule', 'rule', false, 7 ), array( 'Judgment', 'judgment', false, 1 ) ) ) . kits_filter_dropdown( 'Category', 'category', array( array( 'Rabies', 'rabies', false, 3 ), array( 'Wildlife', 'wildlife', false, 5 ) ) ), array( array( 'Kind: Act', '#' ) ), '#', kits_sort_dropdown( array( array( 'Newest first', '#', true ), array( 'Oldest first', '#', false ) ) ) );
	$o .= $h( '6 Badge' ) . '<div class="kit-tags">' . kits_badge( 'Verified', 'verified' ) . kits_badge( 'Not yet confirmed', 'unconfirmed' ) . kits_badge( 'Featured', 'featured' ) . kits_badge( 'Sponsored', 'sponsored' ) . kits_badge( 'Advertisement', 'ad' ) . kits_badge( 'Closed', 'closed' ) . kits_badge( 'New', 'new' ) . '</div>';
	$o .= $h( '7 Eyebrow · 8 Count' ) . '<p>' . kits_eyebrow( 'Resources' ) . '</p><p>Act ' . kits_count( 1234 ) . '</p>';
	$o .= $h( '9 Segmented control' ) . '<nav class="kit-segment" aria-label="Demo"><a href="#" aria-current="true">A–Z</a><a href="#">Most details first</a></nav>';
	$o .= $h( '10 Button' ) . kits_demo_buttons();
	$f  = function ( $label, $state, $control, $msg = '' ) {
		return '<div class="kit-field"><label>' . esc_html( $label ) . ( 'Optional' === $state ? ' <span class="kit-optional">(optional)</span>' : '' ) . '</label><div class="kit-field-control">' . $control . '</div><span class="kit-field-message">' . esc_html( $msg ) . '</span></div>';
	};
	$o .= $h( '11 Form field' ) . '<div class="kit-demo-fields">'
		. $f( 'Default', '', '<span class="kit-ico kit-ico--mail kit-field-icon" aria-hidden="true"></span><input type="email" placeholder="you@example.com">' )
		. $f( 'Optional', 'Optional', '<span class="kit-ico kit-ico--name kit-field-icon" aria-hidden="true"></span><input type="text" placeholder="Asha">' )
		. $f( 'Focus', '', '<input type="text" class="is-demo-focus" value="Focused">' )
		. $f( 'Filled', '', '<input type="text" value="Blue Cross of India">' )
		. $f( 'Invalid', '', '<input type="email" aria-invalid="true" value="asha@">', 'Enter a full email address, like you@example.com' )
		. $f( 'Valid', '', '<input type="email" class="is-valid" value="asha@example.com">' )
		. $f( 'Disabled', '', '<select disabled><option>Choose a state first</option></select>' )
		. $f( 'Read-only', '', '<input type="text" readonly value="AWBI code KL024/2002">' )
		. $f( 'Select', '', '<select><option>What\'s this about?</option><option>General question</option></select>' )
		. $f( 'Textarea', '', '<textarea placeholder="Page link and what\'s wrong"></textarea>' )
		. '</div>';
	$o .= $h( '12 Checkbox' ) . '<label class="kit-checkbox"><input type="checkbox"> <span>Unchecked (consent starts unchecked)</span></label><label class="kit-checkbox"><input type="checkbox" checked> <span>Checked</span></label><label class="kit-checkbox"><input type="checkbox" aria-invalid="true"> <span>Invalid</span></label>';
	$o .= $h( '13 Form status' ) . '<div class="kit-form-status">Something went wrong. Your details are still here — try again.</div><div class="kit-form-status is-success">Message sent.</div>';
	$o .= $h( '14 Banner' ) . '<p>A cover-block pattern (kits-by-wugweb/banner): 16:5 wide, 4:3 on phones. Insert it from Editor → Patterns.</p>';
	$o .= $h( '15 Breadcrumb' ) . '<nav class="kit-crumbs" aria-label="Breadcrumb"><ol><li><a href="#">Home</a></li><li><a href="#">Directory</a></li><li><span aria-current="page">Kerala</span></li></ol></nav>';
	$dd   = array( array( 'Act', 'act', false, 9 ), array( 'Rule', 'rule', false, 7 ), array( 'Judgment', 'judgment', false, 1 ) );
	$open = fn( $html ) => preg_replace( array( '/class="kit-dd( [^"]*)?"/', '/(class="kit-dd-panel[^"]*"[^>]*?) hidden(="[^"]*")?/' ), array( 'class="kit-dd$1 is-open"', '$1' ), $html, 1 );
	$o   .= $h( '16 Filter dropdown: closed · chosen · open' ) . '<div class="kit-tags kit-demo-dd">' . kits_filter_dropdown( 'Kind', 'kind', $dd )
		. kits_filter_dropdown( 'Kind', 'kind', array( array( 'Act', 'act', true, 9 ), array( 'Rule', 'rule', true, 7 ), array( 'Judgment', 'judgment', false, 1 ) ) ) . '</div>'
		. '<div class="kit-demo-dd">' . $open( kits_filter_dropdown( 'Kind', 'kind', array( array( 'Act', 'act', true, 9 ), array( 'Rule', 'rule', false, 7 ), array( 'Judgment', 'judgment', false, 1 ) ) ) ) . '</div>';
	$o   .= $h( '17 Sort: closed · open' ) . '<div class="kit-tags kit-demo-dd">' . kits_sort_dropdown( array( array( 'Newest first', '#', true ), array( 'Oldest first', '#', false ) ) ) . '</div>'
		. '<div class="kit-demo-dd">' . $open( kits_sort_dropdown( array( array( 'Newest first', '#', true ), array( 'Oldest first', '#', false ), array( 'A–Z', '#', false ) ) ) ) . '</div>';
	if ( function_exists( 'kits_disclosure' ) ) {
		$o .= $h( '18 Disclosure (a form behind a button): closed · open' ) . kits_disclosure( 'kit-demo-d1' . $suffix, 'Claim this listing', '<p>Form here.</p>' )
			. str_replace( '<details class="kit-disclosure"', '<details class="kit-disclosure" open', kits_disclosure( 'kit-demo-d2' . $suffix, 'Report a mistake', '<p>Form here.</p>', 'secondary' ) );
	}
	if ( function_exists( 'kits_form_steps' ) ) {
		$o .= $h( '19 Steps (step 1 of 2; without JavaScript both show)' ) . kits_form_steps( array( array( 'About you', '<p>Fields.</p>' ), array( 'What to change', '<p>Fields.</p>' ) ), 'Send' );
	}
	$o .= $h( '20 Pagination: current · link · next' ) . '<nav class="kit-pager" aria-label="Pagination"><span class="page-numbers current" aria-current="page">1</span><a class="page-numbers" href="#">2</a><a class="page-numbers" href="#">3</a><a class="page-numbers next" href="#">Next</a></nav>';
	$o .= $h( '21 Empty state' ) . '<div class="kit-empty"><p>Nothing matches these filters. Remove one to see more.</p></div>';
	$o .= $h( '22 Ad slot (label; empty slots print nothing)' ) . '<aside class="kit-ad" aria-label="Advertisement"><p class="kit-ad-label"><span class="kit-badge kit-badge--ad">Advertisement</span></p><div class="kit-demo-adbox">ad unit</div></aside>';
	$o .= $h( '23 Links: internal · external (arrow) · visited' ) . '<p><a href="#">Internal link</a> · <a class="kit-link-ext" href="#">External site</a></p>';
	$icons = array();
	foreach ( array( get_template_directory() . '/assets/css/kit.css' ) as $f ) {
		if ( is_readable( $f ) && preg_match_all( '/\.kit-ico--([a-z0-9-]+) \{/', (string) file_get_contents( $f ), $m ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			$icons = array_unique( $m[1] );
		}
	}
	$o .= $h( '24 Icons (' . count( $icons ) . ')' ) . '<ul class="kit-demo-icons">' . implode( '', array_map( fn( $i ) => '<li><span class="kit-ico kit-ico--' . esc_attr( $i ) . '" aria-hidden="true"></span><span>' . esc_html( $i ) . '</span></li>', $icons ) ) . '</ul>';
	return $o;
}

/**
 * The button matrix (docs/buttons.md): every variant in every state, the content types and sizes, for .kit-btn and the
 * core Button block, on a light and on a dark surface. Hover and active are drawn with demo classes.
 */
function kits_demo_buttons() {
	$variants = array(
		'primary'   => array( '', '' ),
		'secondary' => array( ' kit-btn--secondary', ' is-style-kits-secondary' ),
		'ghost'     => array( ' kit-btn--ghost', ' is-style-kits-ghost' ),
		'brand'     => array( ' kit-btn--brand', ' is-style-kits-brand' ),
	);
	$states   = array(
		'Default'  => array( '', '' ),
		'Hover'    => array( ' is-demo-hover', '' ),
		'Focus'    => array( ' is-demo-focus', '' ),
		'Pressed'  => array( ' is-demo-active', '' ),
		'Disabled' => array( '', ' disabled' ),
		'Loading'  => array( ' is-loading', ' aria-busy="true"' ),
	);
	$ico      = fn( $n ) => '<span class="kit-ico kit-ico--' . $n . '" aria-hidden="true"></span>';
	$surface  = function ( $title, $class ) use ( $variants, $states, $ico ) {
		$o = '<div class="kit-demo-btns ' . $class . '"><p class="kit-demo-btns-h">' . esc_html( $title ) . '</p>';
		foreach ( $variants as $name => $v ) {
			$o .= '<p class="kit-demo-btns-row"><span class="kit-demo-btns-label">' . esc_html( ucfirst( $name ) ) . '</span>';
			foreach ( $states as $state => $s ) {
				$o .= '<button class="kit-btn' . $v[0] . $s[0] . '" type="button"' . $s[1] . '>' . esc_html( $state ) . '</button>';
			}
			$o .= '</p>';
		}
		$o .= '<p class="kit-demo-btns-row"><span class="kit-demo-btns-label">Content</span>'
			. '<a class="kit-btn" href="/demo/">Icon + label (link)</a>'
			. '<a class="kit-btn kit-btn--secondary" href="tel:+910000000000">Call</a>'
			. '<a class="kit-btn kit-btn--ghost" href="https://example.org/">Leaves the site</a>'
			. '<button class="kit-btn kit-btn--secondary kit-btn--label" type="submit">Label only</button>'
			. '<button class="kit-btn kit-btn--icon" type="button" aria-label="Search">' . $ico( 'search' ) . '</button>'
			. '<button class="kit-btn kit-btn--secondary kit-btn--icon" type="button" aria-label="Copy link">' . $ico( 'link' ) . '</button></p>';
		$o .= '<p class="kit-demo-btns-row"><span class="kit-demo-btns-label">Small</span>'
			. '<a class="kit-btn kit-btn--sm" href="/demo/">Primary</a><a class="kit-btn kit-btn--sm kit-btn--secondary" href="/demo/">Secondary</a>'
			. '<a class="kit-btn kit-btn--sm kit-btn--ghost" href="/demo/">Ghost</a><a class="kit-btn kit-btn--sm kit-btn--brand" href="/demo/">Brand</a>'
			. '<button class="kit-btn kit-btn--sm kit-btn--secondary kit-btn--icon" type="button" aria-label="Share">' . $ico( 'link' ) . '</button></p>';
		$o .= '<div class="kit-demo-btns-row"><span class="kit-demo-btns-label">Block</span><div class="wp-block-buttons is-layout-flex">';
		foreach ( $variants as $name => $v ) {
			$o .= '<div class="wp-block-button' . $v[1] . '"><a class="wp-block-button__link wp-element-button" href="/demo/">' . esc_html( ucfirst( $name ) ) . '</a></div>';
		}
		$o .= '<div class="wp-block-button is-style-kits-secondary kit-btn--ico-mail"><a class="wp-block-button__link wp-element-button" href="/contact/">Email</a></div>'
			. '<div class="wp-block-button kit-btn--sm is-style-kits-secondary"><a class="wp-block-button__link wp-element-button" href="/demo/">Small</a></div>'
			. '<div class="wp-block-button kit-btn--icon kit-btn--ico-search"><a class="wp-block-button__link wp-element-button" href="/search/">Search</a></div>'
			. '</div></div></div>';
		return $o;
	};
	$o = $surface( 'On a light surface', 'kit-demo-btns--light' ) . $surface( 'On a dark surface', 'kit-dark' );
	// Brand reads --kit-btn-brand-bg/-fg: the site's brand by default, a palette's own colour where a site sets one.
	$palettes = array( 'Site brand' => '' ) + (array) apply_filters( 'kits_demo_button_palettes', array() ); // label => class
	$o       .= '<div class="kit-demo-btns kit-demo-btns--light"><p class="kit-demo-btns-h">Brand in each palette (light, then dark)</p>';
	foreach ( $palettes as $label => $class ) {
		foreach ( array( '', ' kit-dark kit-demo-btns-dark' ) as $dark ) {
			$o .= '<div class="kit-demo-btns-row ' . esc_attr( $class . $dark ) . '"><span class="kit-demo-btns-label">' . esc_html( $label . ( $dark ? ' · dark' : '' ) ) . '</span>';
			foreach ( $states as $state => $st ) {
				$o .= '<button class="kit-btn kit-btn--brand' . $st[0] . '" type="button"' . $st[1] . '>' . esc_html( $state ) . '</button>';
			}
			$o .= '<button class="kit-btn" type="button">Primary</button><div class="wp-block-buttons"><div class="wp-block-button is-style-kits-brand"><a class="wp-block-button__link wp-element-button" href="/demo/">Block</a></div></div></div>';
		}
	}
	return $o . '</div>';
}

/* The page is private, so WordPress titles it "Private: Kits demo"; the sheet shows its plain title. */
add_filter(
	'private_title_format',
	function ( $format, $post ) {
		return 'kits-demo' === get_post_field( 'post_name', $post ) ? '%s' : $format;
	},
	10,
	2
);

add_shortcode(
	'kits_demo',
	function () {
		if ( ! current_user_can( 'edit_pages' ) && ! defined( 'KITS_DEMO_RENDER' ) ) {
			return '';
		}
		return '<style>.kit-demo{display:grid;gap:var(--s-8)}@media(min-width:1200px){.kit-demo{grid-template-columns:repeat(2,minmax(0,1fr))}}.kit-demo>section{padding:var(--s-6);border:1px solid var(--kit-color-line);border-radius:var(--kit-radius-card);background:var(--kit-color-bg);color:var(--kit-color-text)}.kit-demo-h{font-size:var(--fluid-body-md);margin:var(--s-6) 0 var(--s-2)}.kit-demo-ink{background:var(--kit-color-ink);padding:var(--s-3);border-radius:var(--kit-radius-control);margin-top:var(--s-2)}.kit-demo-fields{display:grid;gap:0 var(--s-4);grid-template-columns:repeat(auto-fill,minmax(14rem,1fr))}'
			. '.kit-demo-btns{padding:var(--s-4);border-radius:var(--kit-radius-control);margin-top:var(--s-3);background:var(--kit-color-bg)}.kit-demo-btns.kit-dark,.kit-demo-btns-dark{background:var(--kit-color-ink);color:var(--kit-color-on-ink)}.kit-demo-btns-dark{padding:var(--s-3);border-radius:var(--kit-radius-control)}.kit-demo-btns-h{margin:0 0 var(--s-2);font-weight:var(--kit-weight-bold)}.kit-demo-btns-row{display:flex;flex-wrap:wrap;align-items:center;gap:var(--s-2);margin:0 0 var(--s-3)}.kit-demo-btns-row .wp-block-buttons{display:flex;flex-wrap:wrap;gap:var(--s-2);margin:0}.kit-demo-btns-label{flex:0 0 100%;font-size:var(--fluid-caption);text-transform:uppercase;letter-spacing:.06em;opacity:.75}.kit-btn.is-demo-hover{background:var(--_bg-h);border-color:var(--_line-h);color:var(--_fg-h)}.kit-btn.is-demo-active{transform:translateY(var(--kit-btn-press))}.is-demo-focus{outline:var(--kit-focus-width) solid var(--kit-color-focus)!important;outline-offset:var(--kit-focus-offset)}.kit-chip.is-demo-hover,.kit-filter-pill.is-demo-hover{border-color:var(--kit-color-ink)}.kit-chip.is-demo-active{background:var(--kit-color-surface)}.kit-demo-dd .kit-dd-panel{position:static;margin-top:var(--s-1)}.kit-demo-adbox{display:grid;place-items:center;height:5rem;border:1px dashed var(--kit-color-line);color:var(--kit-color-muted)}.kit-demo-icons{list-style:none;padding:0;display:grid;grid-template-columns:repeat(auto-fill,minmax(8rem,1fr));gap:var(--s-2)}.kit-demo-icons li{display:flex;align-items:center;gap:var(--s-2);font-size:var(--fluid-label)}.kit-demo-blocks{grid-column:1/-1}</style>'
			. '<div class="kit-demo"><section style="' . esc_attr( kits_demo_master_vars() ) . '"><h2>Kits (master palette)</h2>' . kits_demo_panel( '-master' ) . '</section>'
			. '<section><h2>' . esc_html( get_bloginfo( 'name' ) ) . ' palette</h2>' . kits_demo_panel( '-site' ) . '</section>'
			. ( ( $extra = (string) apply_filters( 'kits_demo_sections', '' ) ) ? '<section class="kit-demo-blocks">' . $extra . '</section>' : '' ) . '</div>'; // a site's own blocks, in their states
	}
);

// The demo page is never indexed.
add_filter(
	'wp_robots',
	function ( $robots ) {
		if ( is_page( 'kits-demo' ) ) {
			$robots['noindex'] = true;
			$robots['nofollow'] = true;
		}
		return $robots;
	}
);
