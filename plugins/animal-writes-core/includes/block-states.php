<?php
/**
 * Animal Writes blocks in their states, on the states sheet (/kits-demo/, administrators only): each block rendered
 * from a real item that is in that state, so the sheet shows what visitors see. A state with no such item yet says so.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * First published item matching a query, or 0.
 *
 * @param array $q WP_Query arguments.
 * @return int
 */
function aw_state_item( $q ) {
	$ids = get_posts( array_merge( array( 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids', 'no_found_rows' => true ), $q ) );
	return $ids ? (int) $ids[0] : 0;
}

add_filter(
	'kits_demo_sections',
	function ( $html ) {
		$row = function ( $state, $id, $render ) {
			$out = $id ? (string) call_user_func( $render, $id ) : '';
			return '<div class="aw-state-row"><p class="aw-state-name">' . esc_html( $state ) . ( $id ? ' <a href="' . esc_url( get_permalink( $id ) ) . '">(item)</a>' : '' ) . '</p><div class="aw-state-out">'
				. ( $id ? ( '' !== trim( wp_strip_all_tags( $out, true ) ) || preg_match( '/<img|kit-ico|kit-ico|<svg/', $out ) ? $out : '<em>(prints nothing in this state)</em>' ) : '<em>No item in this state yet.</em>' ) . '</div></div>';
		};
		$blocks = array(
			'Document date (animal-writes/doc-date)' => array(
				array( 'Act or Rule: the year', aw_state_item( array( 'post_type' => 'aw_legal', 'tax_query' => array( array( 'taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => array( 'act', 'rule' ) ) ) ) ), fn( $id ) => aw_block_doc_date( array(), $id ) ),
				array( 'Dated document: day and month', aw_state_item( array( 'post_type' => 'aw_legal', 'meta_key' => 'aw_source_date', 'tax_query' => array( array( 'taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => array( 'act', 'rule', 'template' ), 'operator' => 'NOT IN' ) ) ) ), fn( $id ) => aw_block_doc_date( array(), $id ) ),
				array( 'Template: icon', aw_state_item( array( 'post_type' => 'aw_legal', 'tax_query' => array( array( 'taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => 'template' ) ) ) ), fn( $id ) => aw_block_doc_date( array(), $id ) ),
			),
			'Law date, one line (animal-writes/law-date)' => array(
				array( 'Own date', aw_state_item( array( 'post_type' => 'aw_legal', 'meta_key' => 'aw_source_date' ) ), fn( $id ) => aw_block_law_date( array(), $id ) ),
				array( 'Act or Rule: the year', aw_state_item( array( 'post_type' => 'aw_legal', 'tax_query' => array( array( 'taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => 'act' ) ) ) ), fn( $id ) => aw_block_law_date( array(), $id ) ),
			),
			'Issued by (animal-writes/issuer)' => array(
				array( 'A court (dark)', aw_state_item( array( 'post_type' => 'aw_legal', 'meta_query' => array( array( 'key' => 'aw_issuer', 'value' => AW_COURT_ISSUERS, 'compare' => 'IN' ) ) ) ), fn( $id ) => aw_block_issuer( array(), $id ) ),
				array( 'A board or ministry', aw_state_item( array( 'post_type' => 'aw_legal', 'meta_query' => array( array( 'key' => 'aw_issuer', 'value' => AW_COURT_ISSUERS, 'compare' => 'NOT IN' ) ) ) ), fn( $id ) => aw_block_issuer( array(), $id ) ),
				array( 'Template', aw_state_item( array( 'post_type' => 'aw_legal', 'tax_query' => array( array( 'taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => 'template' ) ) ) ), fn( $id ) => aw_block_issuer( array(), $id ) ),
			),
			'Checked and deadline (animal-writes/deadline)' => array(
				array( 'With a deadline', aw_state_item( array( 'post_type' => 'aw_opportunity', 'meta_query' => array( array( 'key' => 'aw_deadline', 'value' => '', 'compare' => '!=' ) ) ) ), fn( $id ) => aw_block_deadline( array(), $id ) ),
				array( 'No deadline', aw_state_item( array( 'post_type' => 'aw_opportunity', 'meta_query' => array( 'relation' => 'OR', array( 'key' => 'aw_deadline', 'compare' => 'NOT EXISTS' ), array( 'key' => 'aw_deadline', 'value' => '' ) ) ) ), fn( $id ) => aw_block_deadline( array(), $id ) ),
			),
			'Opportunity buttons (animal-writes/opp-actions)' => array(
				array( 'With an apply link', aw_state_item( array( 'post_type' => 'aw_opportunity', 'meta_query' => array( array( 'key' => 'aw_apply_url', 'value' => '', 'compare' => '!=' ) ) ) ), fn( $id ) => aw_block_opp_actions( array(), $id ) ),
				array( 'No apply link', aw_state_item( array( 'post_type' => 'aw_opportunity', 'meta_query' => array( 'relation' => 'OR', array( 'key' => 'aw_apply_url', 'compare' => 'NOT EXISTS' ), array( 'key' => 'aw_apply_url', 'value' => '' ) ) ) ), fn( $id ) => aw_block_opp_actions( array(), $id ) ),
			),
			'Posted by (animal-writes/posted-by)' => array(
				array( 'Linked to a Directory profile', aw_state_item( array( 'post_type' => 'aw_opportunity', 'meta_query' => array( array( 'key' => 'aw_organisation', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ) ) ) ), fn( $id ) => aw_block_posted_by( array(), $id ) ),
				array( 'Name only', aw_state_item( array( 'post_type' => 'aw_opportunity', 'meta_query' => array( array( 'key' => 'aw_org_name', 'value' => '', 'compare' => '!=' ), array( 'key' => 'aw_organisation', 'compare' => 'NOT EXISTS' ) ) ) ), fn( $id ) => aw_block_posted_by( array(), $id ) ),
			),
			'Organisation logo (animal-writes/org-mark)' => array(
				array( 'Logo', aw_state_item( array( 'post_type' => 'aw_listing', 'meta_key' => '_thumbnail_id' ) ), fn( $id ) => aw_block_org_mark( array(), $id ) ),
				array( 'Initials (no logo)', aw_state_item( array( 'post_type' => 'aw_listing', 'meta_query' => array( array( 'key' => '_thumbnail_id', 'compare' => 'NOT EXISTS' ) ) ) ), fn( $id ) => aw_block_org_mark( array(), $id ) ),
			),
			'Badges and labels (animal-writes/badges)' => array(
				array( 'Verified', aw_state_item( array( 'post_type' => 'aw_listing', 'meta_query' => array( array( 'key' => 'aw_verified', 'value' => array( '', '0' ), 'compare' => 'NOT IN' ) ) ) ), fn( $id ) => aw_block_badges( array(), $id ) ),
				array( 'Featured', aw_state_item( array( 'post_type' => 'aw_listing', 'meta_query' => array( array( 'key' => 'aw_featured', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ) ) ) ), fn( $id ) => aw_block_badges( array(), $id ) ),
				array( 'Not confirmed (types and source only)', aw_state_item( array( 'post_type' => 'aw_listing', 'meta_query' => array( 'relation' => 'OR', array( 'key' => 'aw_verified', 'compare' => 'NOT EXISTS' ), array( 'key' => 'aw_verified', 'value' => array( '', '0' ), 'compare' => 'IN' ) ) ) ), fn( $id ) => aw_block_badges( array(), $id ) ),
			),
			'Contact icons (animal-writes/contact)' => array(
				array( 'Phone, email and website', aw_state_item( array( 'post_type' => 'aw_listing', 'meta_query' => array( array( 'key' => 'aw_phone', 'value' => '', 'compare' => '!=' ), array( 'key' => 'aw_email', 'value' => '', 'compare' => '!=' ), array( 'key' => 'aw_website', 'value' => '', 'compare' => '!=' ) ) ) ), fn( $id ) => aw_block_contact( array(), $id ) ),
				array( 'Phone only', aw_state_item( array( 'post_type' => 'aw_listing', 'meta_query' => array( array( 'key' => 'aw_phone', 'value' => '', 'compare' => '!=' ), array( 'key' => 'aw_email', 'compare' => 'NOT EXISTS' ) ) ) ), fn( $id ) => aw_block_contact( array(), $id ) ),
				array( 'No contact details', aw_state_item( array( 'post_type' => 'aw_listing', 'meta_query' => array( array( 'key' => 'aw_phone', 'compare' => 'NOT EXISTS' ), array( 'key' => 'aw_email', 'compare' => 'NOT EXISTS' ) ) ) ), fn( $id ) => aw_block_contact( array(), $id ) ),
			),
			'Organisation buttons (animal-writes/org-actions)' => array(
				array( 'With a phone: Call', aw_state_item( array( 'post_type' => 'aw_listing', 'meta_query' => array( array( 'key' => 'aw_phone', 'value' => '', 'compare' => '!=' ) ) ) ), fn( $id ) => aw_block_org_actions( array(), $id ) ),
				array( 'No phone', aw_state_item( array( 'post_type' => 'aw_listing', 'meta_query' => array( array( 'key' => 'aw_phone', 'compare' => 'NOT EXISTS' ) ) ) ), fn( $id ) => aw_block_org_actions( array(), $id ) ),
			),
			'Place (animal-writes/place)' => array(
				array( 'District and state', aw_state_item( array( 'post_type' => 'aw_listing', 'meta_query' => array( array( 'key' => 'aw_district_code', 'value' => '', 'compare' => '!=' ) ) ) ), fn( $id ) => aw_block_place( array(), $id ) ),
				array( 'State only', aw_state_item( array( 'post_type' => 'aw_opportunity', 'meta_query' => array( array( 'key' => 'aw_state_code', 'value' => '', 'compare' => '!=' ), array( 'key' => 'aw_district_code', 'compare' => 'NOT EXISTS' ) ) ) ), fn( $id ) => aw_block_place( array(), $id ) ),
			),
			'Press thumbnail and details (animal-writes/press-media, press-meta)' => array(
				array( 'With a picture', aw_state_item( array( 'post_type' => 'aw_press', 'meta_key' => '_thumbnail_id' ) ), fn( $id ) => aw_block_press_media( array(), $id ) . aw_block_press_meta( array(), $id ) ),
				array( 'No picture', aw_state_item( array( 'post_type' => 'aw_press', 'meta_query' => array( array( 'key' => '_thumbnail_id', 'compare' => 'NOT EXISTS' ) ) ) ), fn( $id ) => aw_block_press_media( array(), $id ) . aw_block_press_meta( array(), $id ) ),
			),
			'Is this your organisation? (animal-writes/listing-help)' => array(
				array( 'Not confirmed: note, Claim and Report', aw_state_item( array( 'post_type' => 'aw_listing', 'meta_query' => array( 'relation' => 'OR', array( 'key' => 'aw_verified', 'compare' => 'NOT EXISTS' ), array( 'key' => 'aw_verified', 'value' => array( '', '0' ), 'compare' => 'IN' ) ) ) ), fn( $id ) => aw_block_listing_help( array(), $id ) ),
			),
		);
		$out = '<h2>Animal Writes blocks in their states</h2><p>Each block drawn from a real item in that state. Form fields, buttons and dropdowns in every state are in the panels above.</p><style>.aw-state-grid{display:grid;gap:var(--s-6);grid-template-columns:repeat(auto-fill,minmax(22rem,1fr))}.aw-state-row{padding:var(--s-3) 0;border-top:1px solid var(--kit-color-line)}.aw-state-name{margin:0 0 var(--s-2);font-size:var(--fluid-label);color:var(--kit-color-muted)}</style><div class="aw-state-grid">';
		foreach ( $blocks as $name => $states ) {
			$out .= '<div><h3 class="kit-demo-h">' . esc_html( $name ) . '</h3>';
			foreach ( $states as $s ) {
				$out .= $row( $s[0], $s[1], $s[2] );
			}
			$out .= '</div>';
		}
		// Forms: closed, open and sent (the sent message as visitors see it after sending).
		$out .= '<div><h3 class="kit-demo-h">Volunteer form (animal-writes/volunteer)</h3>'
			. '<div class="aw-state-row"><p class="aw-state-name">Closed (behind the button)</p><div class="aw-state-out">' . kits_disclosure( 'aw-demo-vol', 'Register as a volunteer', '<p>The form.</p>' ) . '</div></div>'
			. '<div class="aw-state-row"><p class="aw-state-name">Sent</p><div class="aw-state-out"><div class="kit-form-done" role="status"><p class="aw-form-done-title">' . esc_html__( 'Thank you for signing up.', 'animal-writes-core' ) . '</p><p>' . esc_html__( 'We will contact you when there is work in your area. You can ask us to delete your details at any time.', 'animal-writes-core' ) . '</p></div></div></div></div>';
		$out .= '</div>';
		// Cards at each width: layout and data states together. Media queries follow the viewport, so each list is its
		// real page in a frame of that width (scaled to fit); scroll inside a frame for more items and states.
		$cards = array(
			'News: lead and rows'         => array( '/news/', '.wp-block-post-template' ),
			'Library card'                => array( '/library/', '.wp-block-post-template' ),
			'Resources card'              => array( '/resources/', '.wp-block-post-template' ),
			'Opportunity card'            => array( '/opportunities/', '.wp-block-post-template' ),
			'Directory card'              => array( '/directory/', '.wp-block-post-template' ),
			'Media & PR card'             => array( '/media/', '.wp-block-post-template' ),
			'Category page: News rows'    => array( '/category/community-dogs/', '.aw-hub-news' ),
			'Search results'              => array( '/search/?q=dog', 'main h3' ),
			'Home: News'                  => array( '/', '.aw-ed-news' ),
			'Home: From the Library'      => array( '/', '.aw-ed-law' ),
			'Home: Guides'                => array( '/', '.aw-ed-learn' ),
			'Home: Animal Writes 1.0'     => array( '/', '.kit-ed-story' ),
		);
		$out .= '<h2>Cards at each width</h2><p>Each list in its real page at 375, 768 and 1440 pixels: the layout state and the data states of the items together. Scroll inside a frame for more items.</p>'
			. '<style>.aw-bp-set{display:grid;gap:var(--s-4);grid-template-columns:repeat(auto-fill,minmax(min(100%,18rem),1fr));margin-bottom:var(--s-6)}.aw-bp{margin:0}.aw-bp-frame{position:relative;overflow:hidden;height:32rem;border:1px solid var(--kit-color-line);border-radius:var(--kit-radius-card);background:var(--kit-color-canvas)}.aw-bp-frame iframe{position:absolute;inset:0 auto auto 0;border:0;transform-origin:0 0}.aw-bp figcaption{font-size:var(--fluid-label);color:var(--kit-color-muted);margin-top:var(--s-2)}</style>';
		foreach ( $cards as $name => $c ) {
			$out .= '<h3 class="kit-demo-h">' . esc_html( $name ) . '</h3><div class="aw-bp-set">';
			foreach ( array( 375, 768, 1440 ) as $w ) {
				$out .= '<figure class="aw-bp"><div class="aw-bp-frame"><iframe loading="lazy" tabindex="-1" title="' . esc_attr( $name . ', ' . $w . ' pixels' ) . '" width="' . (int) $w . '" data-w="' . (int) $w . '" data-at="' . esc_attr( $c[1] ) . '" src="' . esc_url( home_url( $c[0] ) ) . '"></iframe></div><figcaption>' . esc_html( $w . ' px' ) . '</figcaption></figure>';
			}
			$out .= '</div>';
		}
		$out .= '<script>(function(){function fit(f){var b=f.parentNode,k=b.clientWidth/f.dataset.w;f.style.transform="scale("+k+")";f.style.height=(b.clientHeight/k)+"px";}'
			. 'document.querySelectorAll(".aw-bp iframe").forEach(function(f){fit(f);f.addEventListener("load",function(){try{var t=f.contentDocument.querySelector(f.dataset.at);if(t)t.scrollIntoView();}catch(e){}});});'
			. 'addEventListener("resize",function(){document.querySelectorAll(".aw-bp iframe").forEach(fit);});})();</script>';
		return $html . $out;
	}
);
