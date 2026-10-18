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
	$mi = fn( $ico, $text ) => '<span class="kit-meta-item"><span class="kit-ico kit-ico--' . $ico . '" aria-hidden="true"></span>' . $text . '</span>';
	$o .= $h( '6b Meta row (date, time, place, author, reading time)' ) . '<p class="kit-meta-row">' . $mi( 'calendar', '<time datetime="2026-10-18">18 Oct 2026</time>' ) . $mi( 'clock', '3 min read' ) . $mi( 'map-pin', 'Pune' ) . $mi( 'user', 'Posted by Blue Cross' ) . '</p>'
		. '<p class="kit-demo-note">One meta row for every such line: text 12 / 13 / 14px at 0 / 768 / 1200 (<code>--kit-meta-size</code>, "Meta" in the editor), weight 500, the muted colour; icons 16px; 6px icon to text, 16px between items. A date inside a sentence keeps the sentence\'s own text.</p>';
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
 * core Button block, on a light and on a dark surface. Hover and active are drawn with demo classes. Every button is a
 * link, as on the site; form buttons (submit) have their own row.
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
		'Disabled' => array( '', ' aria-disabled="true"' ),
		'Loading'  => array( ' is-loading', ' aria-busy="true"' ),
	);
	$ico      = fn( $n ) => '<span class="kit-ico kit-ico--' . $n . '" aria-hidden="true"></span>';
	$surface  = function ( $title, $class ) use ( $variants, $states, $ico ) {
		$o = '<div class="kit-demo-btns ' . $class . '"><p class="kit-demo-btns-h">' . esc_html( $title ) . '</p>';
		foreach ( $variants as $name => $v ) {
			$o .= '<p class="kit-demo-btns-row"><span class="kit-demo-btns-label">' . esc_html( ucfirst( $name ) ) . '</span>';
			foreach ( $states as $state => $s ) {
				$o .= '<a class="kit-btn' . $v[0] . $s[0] . '" href="/demo/"' . $s[1] . '>' . esc_html( $state ) . '</a>';
			}
			$o .= '</p>';
		}
		$o .= '<p class="kit-demo-btns-row"><span class="kit-demo-btns-label">Content</span>'
			. '<a class="kit-btn" href="/demo/">Icon + label</a>'
			. '<a class="kit-btn kit-btn--secondary" href="tel:+910000000000">Call</a>'
			. '<a class="kit-btn kit-btn--ghost" href="https://example.org/">Leaves the site</a>'
			. '<a class="kit-btn kit-btn--secondary kit-btn--label" href="/demo/">Label only</a>'
			. '<a class="kit-btn kit-btn--icon" href="/search/" aria-label="Search">' . $ico( 'search' ) . '</a>'
			. '<a class="kit-btn kit-btn--secondary kit-btn--icon" href="/demo/" aria-label="Copy link">' . $ico( 'link' ) . '</a></p>';
		$o .= '<p class="kit-demo-btns-row"><span class="kit-demo-btns-label">Form buttons (submit)</span>'
			. '<button class="kit-btn" type="submit">Send</button><button class="kit-btn kit-btn--secondary" type="submit">Search</button>'
			. '<button class="kit-btn kit-btn--label" type="submit">Label only</button><button class="kit-btn is-loading" type="submit" aria-busy="true">Sending</button><button class="kit-btn" type="submit" disabled>Disabled</button></p>';
		$o .= '<p class="kit-demo-btns-row"><span class="kit-demo-btns-label">Small</span>'
			. '<a class="kit-btn kit-btn--sm" href="/demo/">Primary</a><a class="kit-btn kit-btn--sm kit-btn--secondary" href="/demo/">Secondary</a>'
			. '<a class="kit-btn kit-btn--sm kit-btn--ghost" href="/demo/">Ghost</a><a class="kit-btn kit-btn--sm kit-btn--brand" href="/demo/">Brand</a>'
			. '<a class="kit-btn kit-btn--sm kit-btn--secondary kit-btn--icon" href="/demo/" aria-label="Share">' . $ico( 'link' ) . '</a></p>';
		$o .= '<div class="kit-demo-btns-row"><span class="kit-demo-btns-label">Block</span><div class="wp-block-buttons is-layout-flex">';
		foreach ( $variants as $name => $v ) {
			$o .= '<div class="wp-block-button' . $v[1] . '"><a class="wp-block-button__link wp-element-button" href="/demo/">' . esc_html( ucfirst( $name ) ) . '</a></div>';
		}
		$o .= '<div class="wp-block-button is-style-kits-secondary kit-btn--ico-mail"><a class="wp-block-button__link wp-element-button" href="/contact/">Email</a></div>'
			. '<div class="wp-block-button kit-btn--sm is-style-kits-secondary"><a class="wp-block-button__link wp-element-button" href="/demo/">Small</a></div>'
			. '<div class="wp-block-button is-style-kits-secondary kit-btn--label"><a class="wp-block-button__link wp-element-button" href="/demo/">Label only</a></div>'
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
				$o .= '<a class="kit-btn kit-btn--brand' . $st[0] . '" href="/demo/"' . $st[1] . '>' . esc_html( $state ) . '</a>';
			}
			$o .= '<a class="kit-btn" href="/demo/">Primary</a><div class="wp-block-buttons"><div class="wp-block-button is-style-kits-brand"><a class="wp-block-button__link wp-element-button" href="/demo/">Block</a></div></div></div>';
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

/**
 * Kits components drawn nowhere else on the sheet (owner, 18 Oct 2026), each under its registry name in its states.
 * Fixed or phone-only pieces (sticky bar, carousel dots) are shown in place here (.kit-demo-static).
 */
function kits_demo_more() {
	$h   = fn( $t ) => '<h3 class="kit-demo-h">' . esc_html( $t ) . '</h3>';
	$st  = fn( $t ) => '<p class="kit-demo-state">' . esc_html( $t ) . '</p>';
	$ico = fn( $n ) => '<span class="kit-ico kit-ico--' . $n . '" aria-hidden="true"></span>';
	$img = '';
	$pic = get_posts( array( 'post_type' => 'any', 'post_status' => 'publish', 'numberposts' => 1, 'meta_key' => '_thumbnail_id', 'fields' => 'ids' ) );
	if ( $pic ) {
		$img = (string) get_the_post_thumbnail_url( $pic[0], 'large' );
	}
	$o  = '<style>.kit-demo-static .kit-sticky-help{position:static!important;display:flex!important;transform:none!important}.kit-demo-static .kit-carousel-dots{display:flex!important}.kit-demo-state{margin:var(--s-3) 0 var(--s-1);font-size:var(--kit-meta-size);color:var(--kit-color-muted)}.kit-demo-grid>span{display:grid;place-items:center;min-height:2.5rem;background:var(--kit-color-surface);border-radius:var(--kit-radius-control);font-size:var(--kit-meta-size)}.kit-demo-surfaces{display:grid;gap:var(--s-3);grid-template-columns:repeat(auto-fill,minmax(10rem,1fr))}.kit-demo-surfaces>div{padding:var(--s-4);border-radius:var(--kit-radius-card);border:1px solid var(--kit-color-line);min-height:5rem}</style>';
	$o .= $h( 'Sticky action bar' ) . $st( 'Default (phones only; fixed to the bottom of the screen, hidden while scrolling down)' )
		. '<div class="kit-demo-static"><div class="kit-sticky-help"><a class="kit-sh-primary" href="#">' . $ico( 'helpline' ) . 'Call 1962</a><a class="kit-sh-secondary" href="#">Find help near you</a></div></div>';
	$o .= $h( 'Banner' ) . $st( 'Default (a section page banner; nothing is printed until it has a picture)' )
		. ( $img ? '<div class="wp-block-cover kit-banner"><span aria-hidden="true" class="wp-block-cover__background has-secondary-background-color has-background-dim-30 has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="' . esc_url( $img ) . '" data-object-fit="cover"><div class="wp-block-cover__inner-container"></div></div>' : '<p><em>No picture on the site yet to draw it with.</em></p>' );
	$share = function ( $copied ) use ( $ico ) {
		return '<div class="kit-share" role="group" aria-label="Share"><span class="kit-share-label">Share</span>'
			. '<a class="kit-share-whatsapp" href="#">' . $ico( 'whatsapp' ) . 'WhatsApp</a><a class="kit-share-facebook" href="#">' . $ico( 'facebook' ) . 'Facebook</a><a class="kit-share-x kit-share-icon" href="#" aria-label="X">' . $ico( 'x-brand' ) . '</a>'
			. '<button type="button" class="kit-share-copy">' . $ico( $copied ? 'check' : 'link' ) . ( $copied ? 'Link copied' : 'Copy link' ) . '</button></div>';
	};
	if ( function_exists( 'aw_share_html' ) && $pic ) { // the real share row, then the copy button in its done state
		$real  = aw_share_html( $pic[0] );
		$share = fn( $copied ) => $copied ? (string) preg_replace( '#(<button[^>]*kit-share-copy[^>]*>)(.*?)(</button>)#s', '$1' . $ico( 'check' ) . 'Link copied$3', $real ) : $real;
	}
	$o .= $h( 'Share' ) . $st( 'Default' ) . $share( false ) . $st( 'Copied (after Copy link)' ) . $share( true );
	$o .= $h( 'Table' ) . $st( 'Default (zebra rows; hover darkens a row; on phones the table scrolls sideways inside its box)' )
		. '<div class="kit-table" style="overflow-x:auto"><table><thead><tr><th>Who approves</th><th>Today</th><th>Draft 2026</th></tr></thead><tbody><tr><td>Small animals</td><td>Lab committee</td><td>Lab committee</td></tr><tr><td>Large animals</td><td>National committee</td><td>Lab committee</td></tr><tr><td>Penalty</td><td>Up to ₹200</td><td>Up to ₹200</td></tr></tbody></table></div>';
	$o .= $h( 'Carousel dots' ) . $st( 'Default and current (phones only, under a card row that scrolls sideways)' )
		. '<div class="kit-demo-static"><div class="kit-carousel-dots" role="group" aria-label="Choose a card"><button type="button" aria-label="Card 1 of 4" aria-current="true"></button><button type="button" aria-label="Card 2 of 4"></button><button type="button" aria-label="Card 3 of 4"></button><button type="button" aria-label="Card 4 of 4"></button></div></div>';
	$o .= $h( 'Claim panel' ) . $st( 'Closed' ) . '<div class="kit-claim"><details><summary>Is this your organisation?</summary><p>Claim this listing to update its details.</p></details></div>'
		. $st( 'Open' ) . '<div class="kit-claim"><details open><summary>Is this your organisation?</summary><p>Claim this listing to update its details.</p><p><a class="kit-btn kit-btn--sm" href="#">Claim this listing</a></p></details></div>';
	$o .= $h( 'More link' ) . $st( 'Default' ) . '<p class="kit-more">More in the Library: <a href="#">all court orders</a></p>';
	$counter = fn( $n, $text ) => '<div class="kit-field"><label>Your message</label><div class="kit-field-control"><textarea rows="3" maxlength="1500" data-counter="1200">' . esc_html( str_repeat( 'Lorem ipsum dolor sit amet. ', $n ) ) . '</textarea></div><span class="kit-field-hint kit-counter" aria-live="polite">' . esc_html( $text ) . '</span></div>';
	$o .= $h( 'Character counter' ) . $st( 'Default (nothing shown below the limit warning)' ) . $counter( 2, '' ) . $st( 'Near the limit (from 1,200 of 1,500 characters)' ) . $counter( 2, '1,240 of 1,500 characters' );
	$o .= $h( 'Print button' ) . $st( 'Default (on a printable letter; hidden when printing)' ) . '<p class="kit-print"><button type="button" class="wp-element-button">Print this letter</button> <span>Sign it, then post it by Speed Post.</span></p>';
	$o .= $h( 'Layout grid' ) . $st( 'This frame shows the grid for its own width: 4 columns on phones, 8 from 768, 12 from 1200' ) . '<div class="kit-grid kit-demo-grid">' . implode( '', array_map( fn( $i ) => '<span>' . $i . '</span>', range( 1, 12 ) ) ) . '</div>';
	$o .= $h( 'Surfaces and overlay' ) . '<div class="kit-demo-surfaces">'
		. '<div style="background:var(--kit-color-bg)">' . $st( 'Page grey' ) . '</div><div class="kit-surface-section">' . $st( 'Section white' ) . '</div><div class="kit-surface-alt">' . $st( 'Section darker grey' ) . '</div><div style="background:var(--kit-surface-card,var(--kit-color-raised))">' . $st( 'Card white' ) . '</div>'
		. '<div class="kit-overlay" style="background:' . ( $img ? 'url(' . esc_url( $img ) . ') center/cover' : 'var(--kit-color-ink)' ) . ';color:var(--kit-color-on-ink)"><p class="kit-demo-state" style="color:inherit">Image with dark overlay</p></div></div>';
	$o .= $h( 'Preview only' ) . $st( 'Shown to administrators and in previews; hidden from visitors' ) . '<div class="kits-preview-only" style="padding:var(--s-3);border:1px dashed var(--kit-color-line)">Preview-only content (for example an empty ad slot or a note to editors).</div>';
	$core = '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#">Primary</a></div><!-- /wp:button --><!-- wp:button {"className":"is-style-kits-secondary"} --><div class="wp-block-button is-style-kits-secondary"><a class="wp-block-button__link wp-element-button" href="#">Secondary</a></div><!-- /wp:button --><!-- wp:button {"className":"is-style-kits-ghost"} --><div class="wp-block-button is-style-kits-ghost"><a class="wp-block-button__link wp-element-button" href="#">Ghost</a></div><!-- /wp:button --></div><!-- /wp:buttons -->'
		. '<!-- wp:quote --><blockquote class="wp-block-quote"><!-- wp:paragraph --><p>Every animal deserves a voice.</p><!-- /wp:paragraph --><cite>Animal Writes</cite></blockquote><!-- /wp:quote -->'
		. '<!-- wp:separator --><hr class="wp-block-separator has-alpha-channel-opacity"/><!-- /wp:separator -->'
		. '<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>A list item</li><!-- /wp:list-item --><!-- wp:list-item --><li>Another list item</li><!-- /wp:list-item --></ul><!-- /wp:list -->'
		. '<!-- wp:table --><figure class="wp-block-table"><table><thead><tr><th>Block</th><th>Style</th></tr></thead><tbody><tr><td>Table</td><td>Kits</td></tr></tbody></table></figure><!-- /wp:table -->';
	$o .= $h( 'Core block styles (Site Editor > Styles > Blocks)' ) . $st( 'Default; hover and focus as on the buttons above; on dark surfaces Primary stays yellow, Secondary and Ghost keep their outline' ) . do_blocks( $core )
		. '<div class="kit-demo-ink">' . do_blocks( '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#">Primary on dark</a></div><!-- /wp:button --><!-- wp:button {"className":"is-style-kits-secondary"} --><div class="wp-block-button is-style-kits-secondary"><a class="wp-block-button__link wp-element-button" href="#">Secondary on dark</a></div><!-- /wp:button --></div><!-- /wp:buttons -->' ) . '</div>';
	return $o;
}

add_shortcode(
	'kits_demo',
	function () {
		if ( ! current_user_can( 'edit_pages' ) && ! defined( 'KITS_DEMO_RENDER' ) ) {
			return '';
		}
		$pattern = sanitize_text_field( wp_unslash( $_GET['kits_pattern'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' !== $pattern && WP_Block_Patterns_Registry::get_instance()->is_registered( $pattern ) ) { // one pattern drawn on its own (Kits system > Components)
			return do_blocks( '<!-- wp:pattern {"slug":"' . esc_attr( $pattern ) . '"} /-->' );
		}
		return '<style>.kit-demo{display:grid;gap:var(--s-8)}@media(min-width:1200px){.kit-demo{grid-template-columns:repeat(2,minmax(0,1fr))}}.kit-demo>section{padding:var(--s-6);border:1px solid var(--kit-color-line);border-radius:var(--kit-radius-card);background:var(--kit-color-bg);color:var(--kit-color-text)}.kit-demo-h{font-size:var(--fluid-body-md);margin:var(--s-6) 0 var(--s-2)}.kit-demo-ink{background:var(--kit-color-ink);padding:var(--s-3);border-radius:var(--kit-radius-control);margin-top:var(--s-2)}.kit-demo-fields{display:grid;gap:0 var(--s-4);grid-template-columns:repeat(auto-fill,minmax(14rem,1fr))}'
			. '.kit-demo-btns{padding:var(--s-4);border-radius:var(--kit-radius-control);margin-top:var(--s-3);background:var(--kit-color-bg)}.kit-demo-btns.kit-dark,.kit-demo-btns-dark{background:var(--kit-color-ink);color:var(--kit-color-on-ink)}.kit-demo-btns-dark{padding:var(--s-3);border-radius:var(--kit-radius-control)}.kit-demo-btns-h{margin:0 0 var(--s-2);font-weight:var(--kit-weight-bold)}.kit-demo-btns-row{display:flex;flex-wrap:wrap;align-items:center;gap:var(--s-2);margin:0 0 var(--s-3)}.kit-demo-btns-row .wp-block-buttons{display:flex;flex-wrap:wrap;gap:var(--s-2);margin:0}.kit-demo-btns-label{flex:0 0 100%;font-size:var(--fluid-caption);text-transform:uppercase;letter-spacing:.06em;opacity:.75}.kit-btn.is-demo-hover{background:var(--_bg-h);border-color:var(--_line-h);color:var(--_fg-h)}.kit-btn.is-demo-active{transform:translateY(var(--kit-btn-press))}.is-demo-focus{outline:var(--kit-focus-width) solid var(--kit-color-focus)!important;outline-offset:var(--kit-focus-offset)}.kit-chip.is-demo-hover,.kit-filter-pill.is-demo-hover{border-color:var(--kit-color-ink)}.kit-chip.is-demo-active{background:var(--kit-color-surface)}.kit-demo-dd .kit-dd-panel{position:static;margin-top:var(--s-1)}.kit-demo-adbox{display:grid;place-items:center;height:5rem;border:1px dashed var(--kit-color-line);color:var(--kit-color-muted)}.kit-demo-icons{list-style:none;padding:0;display:grid;grid-template-columns:repeat(auto-fill,minmax(8rem,1fr));gap:var(--s-2)}.kit-demo-icons li{display:flex;align-items:center;gap:var(--s-2);font-size:var(--fluid-label)}.kit-demo-blocks{grid-column:1/-1}</style>'
			. '<div class="kit-demo"><section style="' . esc_attr( kits_demo_master_vars() ) . '"><h2>Kits (master palette)</h2>' . kits_demo_panel( '-master' ) . '</section>'
			. '<section><h2>' . esc_html( get_bloginfo( 'name' ) ) . ' palette</h2>' . kits_demo_panel( '-site' ) . '</section>'
			. '<section class="kit-demo-blocks"><h2>More Kits components</h2>' . kits_demo_more() . '</section>'
			. ( ( $extra = (string) apply_filters( 'kits_demo_sections', '' ) ) ? '<section class="kit-demo-blocks">' . $extra . '</section>' : '' ) . '</div>'; // a site's own blocks, in their states
	}
);

// The states sheet lives in Appearance > Kits system (owner, 18 Oct 2026): opening the page directly goes there; inside
// that screen the page is shown without the site's header, footer and ads.
add_action(
	'template_redirect',
	function () {
		if ( is_page( 'kits-demo' ) && ! isset( $_GET['kits_embed'] ) && current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_safe_redirect( admin_url( 'themes.php?page=kits-system&tab=states' ) );
			exit;
		}
	}
);
add_action(
	'wp_head',
	function () {
		if ( is_page( 'kits-demo' ) && isset( $_GET['kits_embed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<style>html{margin-top:0!important}#wpadminbar,.wp-site-blocks>header,.wp-site-blocks>footer,header.wp-block-template-part,footer.wp-block-template-part,.kit-ad,.kit-ad-item{display:none!important}</style>';
		}
	}
);

// Kits system > Components shows each entry's own visual: the states sheet keeps only the sections drawing that entry
// (its classes, or its block named in the heading); an entry the sheet does not draw is shown on a live page instead,
// scrolled to its first use and outlined (kits_spot).
/** Sample pages read daily to find where each entry is first used (kits_spot_map); a site adds its own (filter kits_spot_pages). */
function kits_spot_pages() {
	return array_values( array_unique( (array) apply_filters( 'kits_spot_pages', array( home_url( '/' ), home_url( '/?s=animal' ), home_url( '/kits-no-such-page/' ) ) ) ) );
}

add_action(
	'wp_footer',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( is_page( 'kits-demo' ) && ( isset( $_GET['kits_only_classes'] ) || isset( $_GET['kits_only_block'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<script>(function(){var q=new URLSearchParams(location.search),cls=(q.get("kits_only_classes")||"").split(",").filter(Boolean),blk=q.get("kits_only_block")||"",nm=q.get("kits_only_name")||"";'
				. 'var sel=cls.map(function(c){return "[class~=\""+c+"\"],[class*=\" "+c+"-\"],[class^=\""+c+"-\"]";}).join(",");'
				. 'var root=document.querySelector(".kit-demo");if(!root)return;var first=root.querySelector(":scope>section");if(first&&first.nextElementSibling)first.hidden=true;'
				. 'root.querySelectorAll("h2").forEach(function(h){h.hidden=true;if(h.nextElementSibling&&h.nextElementSibling.tagName==="P")h.nextElementSibling.hidden=true;});var keep=0;'
				. 'root.querySelectorAll("h3.kit-demo-h").forEach(function(h){if(h.closest("[hidden]"))return;var g=[h],n=h.nextElementSibling;while(n&&!n.matches("h3.kit-demo-h,h2")){g.push(n);n=n.nextElementSibling;}'
				. 'var hit=(nm&&h.textContent.trim().indexOf(nm)===0)||(blk&&h.textContent.indexOf(blk)>-1)||(sel&&g.some(function(x){return x!==h&&(x.matches(sel)||x.querySelector(sel));}));g.forEach(function(x){x.hidden=!hit;});if(hit)keep++;});'
				. 'if(!keep){var u=q.get("kits_spot_url")||"none";if(u==="none"){root.insertAdjacentHTML("afterbegin","<p style=\\"padding:1em;background:#fcf0f1;font:14px sans-serif\\">Not drawn on the states sheet and not found on the sample pages: it may appear only in a state, a form step or wp-admin.</p>");}else{location.replace(u+(u.indexOf("?")>-1?"&":"?")+"kits_spot="+encodeURIComponent(sel||(".wp-block-"+blk.replace("/","-"))));}}})();</script>';
		}
		if ( isset( $_GET['kits_spot'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<style>html{margin-top:0!important}#wpadminbar{display:none!important}.kits-spot{outline:3px solid #2271b1!important;outline-offset:4px}</style>'
				. '<script>(function(){var s=new URLSearchParams(location.search).get("kits_spot"),e;try{e=document.querySelector(s);}catch(x){}if(e){e.classList.add("kits-spot");e.scrollIntoView({block:"center"});}document.body.insertAdjacentHTML("afterbegin","<p class=\\"kits-spot-note\\" style=\\"padding:.5em 1em;background:#f0f6fc;font:13px sans-serif\\">"+(e?"Shown where it is used: ":"Not found on ")+location.pathname+"</p>");})();</script>';
		}
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
