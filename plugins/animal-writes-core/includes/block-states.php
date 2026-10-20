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
				. ( $id ? ( '' !== trim( wp_strip_all_tags( $out, true ) ) || preg_match( '/<img|kit-ico|kit-ico|<svg/', $out ) ? '<section aria-label="' . esc_attr( 'Demo: ' . $state ) . '">' . $out . '</section>' : '<em>(prints nothing in this state)</em>' ) : '<em>No item in this state yet.</em>' ) . '</div></div>';
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
			'Apply by (animal-writes/deadline)' => array(
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
				array( 'Phone, email and website', aw_state_item( array( 'post_type' => 'aw_listing', 'meta_query' => array( array( 'key' => 'aw_phones', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ), array( 'key' => 'aw_email', 'value' => '', 'compare' => '!=' ), array( 'key' => 'aw_website', 'value' => '', 'compare' => '!=' ) ) ) ), fn( $id ) => aw_block_contact( array(), $id ) ),
				array( 'Phone only', aw_state_item( array( 'post_type' => 'aw_listing', 'meta_query' => array( array( 'key' => 'aw_phones', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ), array( 'key' => 'aw_email', 'compare' => 'NOT EXISTS' ) ) ) ), fn( $id ) => aw_block_contact( array(), $id ) ),
				array( 'No contact details', aw_state_item( array( 'post_type' => 'aw_listing', 'meta_query' => array( array( 'relation' => 'OR', array( 'key' => 'aw_phones', 'compare' => 'NOT EXISTS' ), array( 'key' => 'aw_phones', 'value' => array( '', '0' ), 'compare' => 'IN' ) ), array( 'key' => 'aw_email', 'compare' => 'NOT EXISTS' ) ) ) ), fn( $id ) => aw_block_contact( array(), $id ) ),
			),
			'Organisation buttons (animal-writes/org-actions)' => array(
				array( 'With a phone: Call', aw_state_item( array( 'post_type' => 'aw_listing', 'meta_query' => array( array( 'key' => 'aw_phones', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ) ) ) ), fn( $id ) => aw_block_org_actions( array(), $id ) ),
				array( 'No phone', aw_state_item( array( 'post_type' => 'aw_listing', 'meta_query' => array( array( 'relation' => 'OR', array( 'key' => 'aw_phones', 'compare' => 'NOT EXISTS' ), array( 'key' => 'aw_phones', 'value' => array( '', '0' ), 'compare' => 'IN' ) ) ) ) ), fn( $id ) => aw_block_org_actions( array(), $id ) ),
			),
			'Place (animal-writes/place)' => array(
				array( 'District and state', aw_state_item( array( 'post_type' => 'aw_listing', 'meta_query' => array( array( 'key' => 'aw_district_code', 'value' => '', 'compare' => '!=' ) ) ) ), fn( $id ) => aw_block_place( array(), $id ) ),
				array( 'State only', aw_state_item( array( 'post_type' => 'aw_opportunity', 'meta_query' => array( array( 'key' => 'aw_state_code', 'value' => '', 'compare' => '!=' ), array( 'key' => 'aw_district_code', 'compare' => 'NOT EXISTS' ) ) ) ), fn( $id ) => aw_block_place( array(), $id ) ),
			),
			'Press date and format (animal-writes/press-meta)' => array(
				array( 'Date and format', aw_state_item( array( 'post_type' => 'aw_press', 'meta_query' => array( array( 'key' => 'aw_press_date', 'value' => '', 'compare' => '!=' ), array( 'key' => 'aw_press_format', 'value' => '', 'compare' => '!=' ) ) ) ), fn( $id ) => aw_block_press_meta( array(), $id ) ),
				array( 'Video format (badge)', aw_state_item( array( 'post_type' => 'aw_press', 'meta_query' => array( array( 'key' => 'aw_press_format', 'value' => array( 'facebook-video', 'instagram-reel', 'youtube-video' ), 'compare' => 'IN' ) ) ) ), fn( $id ) => aw_block_press_meta( array(), $id ) ),
				array( 'Social account handle', aw_state_item( array( 'post_type' => 'aw_press', 'meta_query' => array( array( 'key' => 'aw_press_account', 'value' => '', 'compare' => '!=' ) ) ) ), fn( $id ) => aw_block_press_meta( array(), $id ) ),
				array( 'Date only', aw_state_item( array( 'post_type' => 'aw_press', 'meta_query' => array( array( 'key' => 'aw_press_date', 'value' => '', 'compare' => '!=' ), array( 'relation' => 'OR', array( 'key' => 'aw_press_format', 'compare' => 'NOT EXISTS' ), array( 'key' => 'aw_press_format', 'value' => '' ) ) ) ) ), fn( $id ) => aw_block_press_meta( array(), $id ) ),
				array( 'Format only (no date)', aw_state_item( array( 'post_type' => 'aw_press', 'meta_query' => array( array( 'relation' => 'OR', array( 'key' => 'aw_press_date', 'compare' => 'NOT EXISTS' ), array( 'key' => 'aw_press_date', 'value' => '' ) ), array( 'key' => 'aw_press_format', 'value' => '', 'compare' => '!=' ) ) ) ), fn( $id ) => aw_block_press_meta( array(), $id ) ),
			),
			'Press thumbnail and details (animal-writes/press-media, press-meta)' => array(
				array( 'With a picture', aw_state_item( array( 'post_type' => 'aw_press', 'meta_key' => '_thumbnail_id' ) ), fn( $id ) => aw_block_press_media( array(), $id ) . aw_block_press_meta( array(), $id ) ),
				array( 'No picture', aw_state_item( array( 'post_type' => 'aw_press', 'meta_query' => array( array( 'key' => '_thumbnail_id', 'compare' => 'NOT EXISTS' ) ) ) ), fn( $id ) => aw_block_press_media( array(), $id ) . aw_block_press_meta( array(), $id ) ),
			),
			'Is this your organisation? (animal-writes/listing-help)' => array(
				array( 'Not confirmed: note, Claim and Report', aw_state_item( array( 'post_type' => 'aw_listing', 'meta_query' => array( 'relation' => 'OR', array( 'key' => 'aw_verified', 'compare' => 'NOT EXISTS' ), array( 'key' => 'aw_verified', 'value' => array( '', '0' ), 'compare' => 'IN' ) ) ) ), fn( $id ) => aw_block_listing_help( array(), $id ) ),
			),
			'Publisher (animal-writes/publisher)' => array(
				array( 'Media & PR: its own logo', aw_state_item( array( 'post_type' => 'aw_press', 'meta_query' => array( array( 'key' => 'aw_press_logo', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ) ) ) ), fn( $id ) => aw_block_publisher( array(), $id ) ),
				array( 'Media & PR: no logo (letter mark)', aw_state_item( array( 'post_type' => 'aw_press', 'meta_query' => array( 'relation' => 'OR', array( 'key' => 'aw_press_logo', 'compare' => 'NOT EXISTS' ), array( 'key' => 'aw_press_logo', 'value' => '' ) ) ) ), fn( $id ) => aw_block_publisher( array(), $id ) ),
				array( 'News story', aw_state_item( array( 'post_type' => 'aw_news' ) ), fn( $id ) => aw_block_publisher( array(), $id ) ),
				array( 'Library item', aw_state_item( array( 'post_type' => 'aw_legal' ) ), fn( $id ) => aw_block_publisher( array(), $id ) ),
			),
			'Type and date (animal-writes/type-date)' => array(
				array( 'Act or Rule', aw_state_item( array( 'post_type' => 'aw_legal', 'tax_query' => array( array( 'taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => array( 'act', 'rule' ) ) ) ) ), fn( $id ) => aw_block_type_date( array(), $id ) ),
				array( 'Dated document', aw_state_item( array( 'post_type' => 'aw_legal', 'meta_key' => 'aw_source_date' ) ), fn( $id ) => aw_block_type_date( array(), $id ) ),
				array( 'Template', aw_state_item( array( 'post_type' => 'aw_legal', 'tax_query' => array( array( 'taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => 'template' ) ) ) ), fn( $id ) => aw_block_type_date( array(), $id ) ),
			),
			'Document link (animal-writes/doc-link)' => array(
				array( 'Hosted PDF', aw_state_item( array( 'post_type' => 'aw_legal', 'meta_query' => array( array( 'key' => 'aw_file_id', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ) ) ) ), fn( $id ) => aw_block_doc_link( array(), $id ) ),
				array( 'Preview only (not hosted)', aw_state_item( array( 'post_type' => 'aw_legal', 'meta_query' => array( array( 'key' => 'aw_doc_preview', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ), array( 'key' => 'aw_file_id', 'compare' => 'NOT EXISTS' ) ) ) ), fn( $id ) => aw_block_doc_link( array(), $id ) ),
				array( 'Source link only', aw_state_item( array( 'post_type' => 'aw_legal', 'meta_query' => array( array( 'key' => 'aw_file_id', 'compare' => 'NOT EXISTS' ), array( 'key' => 'aw_doc_preview', 'compare' => 'NOT EXISTS' ), array( 'key' => 'aw_source_url', 'value' => '', 'compare' => '!=' ) ) ) ), fn( $id ) => aw_block_doc_link( array(), $id ) ),
			),
			'Resource icon (animal-writes/task-icon)' => array(
				array( 'Guide', aw_state_item( array( 'post_type' => 'post', 'tax_query' => array( array( 'taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => 'guide' ) ) ) ), fn( $id ) => aw_block_task_icon( array(), $id ) ),
				array( 'FAQ page', aw_state_item( array( 'post_type' => 'post', 'tax_query' => array( array( 'taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => 'faq' ) ) ) ), fn( $id ) => aw_block_task_icon( array(), $id ) ),
				array( 'Research summary', aw_state_item( array( 'post_type' => 'post', 'tax_query' => array( array( 'taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => 'research' ) ) ) ), fn( $id ) => aw_block_task_icon( array(), $id ) ),
			),
			'Reading meta (animal-writes/read-meta)' => array(
				array( 'Updated after publishing', (int) ( get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids', 'orderby' => 'modified', 'order' => 'DESC' ) )[0] ?? 0 ), fn( $id ) => aw_block_read_meta( array(), $id ) ),
				array( 'Not updated', (int) ( get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids', 'orderby' => 'modified', 'order' => 'ASC' ) )[0] ?? 0 ), fn( $id ) => aw_block_read_meta( array(), $id ) ),
			),
			'Opportunity line (animal-writes/opp-line)' => array(
				array( 'Organisation, place and deadline', aw_state_item( array( 'post_type' => 'aw_opportunity', 'meta_query' => array( array( 'key' => 'aw_deadline', 'value' => '', 'compare' => '!=' ), array( 'key' => 'aw_state_code', 'compare' => 'EXISTS' ) ) ) ), fn( $id ) => aw_block_opp_line( array(), $id ) ),
				array( 'No deadline', aw_state_item( array( 'post_type' => 'aw_opportunity', 'meta_query' => array( 'relation' => 'OR', array( 'key' => 'aw_deadline', 'compare' => 'NOT EXISTS' ), array( 'key' => 'aw_deadline', 'value' => '' ) ) ) ), fn( $id ) => aw_block_opp_line( array(), $id ) ),
				array( 'No place', aw_state_item( array( 'post_type' => 'aw_opportunity', 'meta_query' => array( array( 'key' => 'aw_state_code', 'compare' => 'NOT EXISTS' ) ) ) ), fn( $id ) => aw_block_opp_line( array(), $id ) ),
			),
			'Organisation details (animal-writes/org-details)' => array(
				array( 'Verified', aw_state_item( array( 'post_type' => 'aw_listing', 'meta_query' => array( array( 'key' => 'aw_verified', 'value' => array( '', '0' ), 'compare' => 'NOT IN' ) ) ) ), fn( $id ) => aw_block_org_details( array(), $id ) ),
				array( 'Not confirmed', aw_state_item( array( 'post_type' => 'aw_listing', 'meta_query' => array( 'relation' => 'OR', array( 'key' => 'aw_verified', 'compare' => 'NOT EXISTS' ), array( 'key' => 'aw_verified', 'value' => array( '', '0' ), 'compare' => 'IN' ) ) ) ), fn( $id ) => aw_block_org_details( array(), $id ) ),
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
		// Page-level blocks: they draw from the page they are on (its query, filters, place), so the sheet shows where they render; browse-states works anywhere.
		$page_blocks = array(
			array( 'Breadcrumb (animal-writes/breadcrumb)', 'every page top', '/library/' ),
			array( 'Head band (animal-writes/section-head)', 'Pages, archives, category and Directory place pages', '/about/' ),
			array( 'Section page count (animal-writes/hub-count)', 'section page head bands (prints nothing: the count sits with the filters under the band, owner 20 Oct)', '/news/' ),
			array( 'Section page pictures (animal-writes/hub-visual)', 'section page head bands (prints nothing: no pictures in page top bands, owner 8 Oct)', '/news/' ),
			array( 'List filters (animal-writes/list-filters)', 'section page head bands: dropdowns, Sort; Directory search (list pages: printed under the band with the count)', '/opportunities/' ),
			array( 'Result count and sort (animal-writes/result-head)', 'Directory results: one result, many, filtered', '/directory/telangana/' ),
		);
		$out .= '<h2>Page-level blocks</h2><p>These draw from the page they are on, so each is shown on its page (and in the frames below).</p><div class="aw-state-grid">';
		foreach ( $page_blocks as $pb ) {
			$out .= '<div><h3 class="kit-demo-h">' . esc_html( $pb[0] ) . '</h3><div class="aw-state-row"><p class="aw-state-name">' . esc_html( $pb[1] ) . '</p><div class="aw-state-out"><a href="' . esc_url( home_url( $pb[2] ) ) . '">' . esc_html( $pb[2] ) . '</a></div></div></div>';
		}
		$out .= '<div><h3 class="kit-demo-h">Browse by state (animal-writes/browse-states)</h3><div class="aw-state-row"><p class="aw-state-name">Every state with listings</p><div class="aw-state-out">' . ( function_exists( 'aw_block_browse_states' ) ? '<section aria-label="Demo: browse by state">' . aw_block_browse_states() . '</section>' : '' ) . '</div></div></div></div>';
		// Picture states (owner, 16 Oct 2026): each real image in a 4:3 card frame, masked (cover); logos stay whole.
		$pick = array( 'Landscape photo' => 0, 'Portrait photo' => 0, 'Square photo' => 0 );
		foreach ( get_posts( array( 'post_type' => array( 'aw_news', 'post', 'aw_opportunity' ), 'post_status' => 'publish', 'numberposts' => 120, 'fields' => 'ids', 'meta_key' => '_thumbnail_id' ) ) as $pid ) {
			$m = wp_get_attachment_metadata( (int) get_post_thumbnail_id( $pid ) );
			if ( empty( $m['width'] ) || empty( $m['height'] ) ) {
				continue;
			}
			$r = $m['width'] / $m['height'];
			$k = $r > 1.45 ? 'Landscape photo' : ( $r < 0.9 ? 'Portrait photo' : ( abs( $r - 1 ) < 0.06 ? 'Square photo' : '' ) );
			if ( $k && ! $pick[ $k ] ) {
				$pick[ $k ] = (int) get_post_thumbnail_id( $pid );
			}
		}
		$doc  = (int) get_post_meta( aw_state_item( array( 'post_type' => 'aw_legal', 'meta_key' => 'aw_doc_preview' ) ), 'aw_doc_preview', true );
		$logo = (int) get_post_meta( aw_state_item( array( 'post_type' => 'aw_press', 'meta_query' => array( array( 'key' => 'aw_press_logo', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ) ) ) ), 'aw_press_logo', true );
		$out .= '<h2>Pictures in a card</h2><p>Card pictures are 4:3, masked like WordPress (cover, centred): the whole file is served, the frame hides the edges. A document page is masked from the top. Logos stay whole.</p><div class="aw-state-grid">';
		foreach ( $pick + array( 'Document page' => $doc, 'Logo (stays whole)' => $logo ) as $label => $att ) {
			$img  = $att ? wp_get_attachment_image( $att, 'medium_large', false, array( 'alt' => '', 'loading' => 'lazy', 'class' => 'Logo (stays whole)' === $label ? 'aw-story-logo' : '' ) ) : '';
			$fig  = 'Logo (stays whole)' === $label ? '<figure class="wp-block-post-featured-image kit-card-media kit-story-media"><div class="aw-story-fallback" style="display:flex">' . $img . '</div></figure>' : '<figure class="wp-block-post-featured-image kit-card-media' . ( 'Document page' === $label ? ' kit-doc-cover' : '' ) . '">' . $img . '</figure>';
			$out .= '<div class="aw-state-row"><p class="aw-state-name">' . esc_html( $label ) . '</p><div class="aw-state-out">' . ( $img ? $fig : '<em>No item in this state yet.</em>' ) . '</div></div>';
		}
		$out .= '</div>';
		// Cards at each width: layout and data states together. Media queries follow the viewport, so each list is its
		// real page in a frame of that width (scaled to fit); scroll inside a frame for more items and states.
		$cards = array(
			'News: lead and rows'         => array( '/news/', '.wp-block-post-template', array( 375, 640, 768, 1024, 1200 ) ),
			'Library card'                => array( '/library/', '.wp-block-post-template' ),
			'Resources card'              => array( '/resources/', '.wp-block-post-template' ),
			'Opportunity card'            => array( '/opportunities/', '.wp-block-post-template', array( 375, 640, 768, 1024, 1200 ) ),
			'Directory card'              => array( '/directory/', '.wp-block-post-template' ),
			'Media & PR card'             => array( '/press-coverage/', '.wp-block-post-template', array( 375, 640, 768, 1024, 1200 ) ),
			'Campaign: news grid'         => array( '/campaigns/lab-animals-rules-2026/', '.aw-news-grid', array( 375, 640, 768, 1024, 1200 ) ),
			'Category page: News rows'    => array( '/category/community-dogs/', '.aw-hub-news' ),
			'Search results'              => array( '/search/?q=dog', 'main h3' ),
			'Home: Need help now'         => array( '/', '.aw-ed-help' ),
			'Home: News'                  => array( '/', '.aw-ed-news' ),
			'Home: From the Library'      => array( '/', '.aw-ed-law' ),
			'Home: Guides'                => array( '/', '.aw-ed-learn' ),
			'Home: India writes for its community dogs' => array( '/', '.aw-ed-story' ),
			'Directory listing page'      => array( '/directory/org/wugweb-sample-listing/', 'main' ),
			'Item page: text and sidebar (sidebar empty today)' => array( '/resources/reporting-illegal-pet-shops-and-breeders/', 'main' ),
		);
		$out .= '<h2>Cards at each width</h2><p>Each list in its real page at 375, 768, 1199 and 1200 pixels (the token breakpoints are 768 and 1200): the layout state and the data states of the items together. Card grids that fit two cards from about 600px wide (auto-fill, no extra breakpoint) also show 640 and 1024. Scroll inside a frame for more items.</p>'
			. '<style>.aw-bp-set{display:grid;gap:var(--s-4);grid-template-columns:repeat(auto-fill,minmax(min(100%,18rem),1fr));margin-bottom:var(--s-6)}.aw-bp{margin:0}.aw-bp-frame{position:relative;overflow:hidden;height:32rem;border:1px solid var(--kit-color-line);border-radius:var(--kit-radius-card);background:var(--kit-color-canvas)}.aw-bp-frame iframe{position:absolute;inset:0 auto auto 0;border:0;transform-origin:0 0}.aw-bp figcaption{font-size:var(--fluid-label);color:var(--kit-color-muted);margin-top:var(--s-2)}</style>';
		foreach ( $cards as $name => $c ) {
			$out .= '<h3 class="kit-demo-h">' . esc_html( $name ) . '</h3><div class="aw-bp-set">';
			foreach ( $c[2] ?? array( 375, 768, 1199, 1200 ) as $w ) {
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

/* States sheet (/kits-demo/): the Brand button in each campaign palette, with the palette styles loaded there. */
add_filter(
	'kits_demo_button_palettes',
	function ( $palettes ) {
		return $palettes + array(
			'Campaign: lab rules'      => 'aw-camp--lab-rules',
			'Campaign: community dogs' => 'aw-camp--community-dogs',
		);
	}
);
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! is_page( 'kits-demo' ) ) {
			return;
		}
		foreach ( array( 'aw-campaign' => 'campaign.css', 'aw-campaign-record' => 'campaign-record.css' ) as $handle => $file ) {
			wp_enqueue_style( $handle, plugins_url( 'assets/css/' . $file, AW_CORE_DIR . 'animal-writes-core.php' ), array(), (string) filemtime( AW_CORE_DIR . 'assets/css/' . $file ) );
		}
	},
	40
);

/* Kits system > Components: the site's pages a component is looked for on, when the states sheet does not draw it. */
add_filter(
	'kits_spot_pages',
	function ( $pages ) {
		$pages = array_values( array_filter( $pages, fn( $u ) => false === strpos( $u, '?s=' ) ) ); // search lives at /search/?q= here (the ?s= address redirects and drops the spot)
		$pages[] = home_url( '/search/?q=animal' );
		foreach ( array( 'news', 'library', 'resources', 'opportunities', 'directory', 'press-coverage', 'campaigns', 'about', 'contact', 'volunteer', 'category/community-dogs' ) as $slug ) {
			$pages[] = home_url( '/' . $slug . '/' );
		}
		foreach ( array( 'aw_news', 'aw_legal', 'post', 'aw_opportunity', 'aw_listing', 'aw_campaign' ) as $type ) {
			$id = aw_state_item( array( 'post_type' => $type ) );
			if ( $id ) {
				$pages[] = get_permalink( $id );
			}
		}
		return $pages;
	}
);

/* States sheet: the Animal Writes pieces drawn nowhere else (owner, 18 Oct 2026), each under its registry name. */
add_filter(
	'kits_demo_sections',
	function ( $html ) {
		$h  = fn( $t ) => '<h3 class="kit-demo-h">' . esc_html( $t ) . '</h3>';
		$st = fn( $t ) => '<p class="aw-state-name">' . esc_html( $t ) . '</p>';
		$o  = '<h2>More Animal Writes components</h2>';
		$o .= '<div>' . $h( 'Helplines' ) . $st( 'Default: the first card is the emergency one (red bar)' )
			. '<div class="aw-helplines"><div class="aw-helpline"><h3>Emergency</h3><a class="aw-helpline-num" href="tel:112">112</a><p>Police, fire and ambulance, anywhere in India.</p><p class="aw-helpline-src">Ministry of Home Affairs</p></div>'
			. '<div class="aw-helpline"><h3>Mobile Veterinary Units</h3><a class="aw-helpline-num" href="tel:1962">1962</a><p>A vet comes to the animal, in most states.</p><p class="aw-helpline-src">State animal husbandry departments</p></div></div>'
			. '<p class="aw-emergency">An animal in danger right now: call 112 first.</p></div>';
		$o .= '<div>' . $h( 'Category index' ) . $st( 'Default (every Category with its picture and count)' ) . '<div style="max-height:26rem;overflow:auto">' . do_shortcode( '[aw_categories_index]' ) . '</div></div>';
		$o .= '<div>' . $h( 'Footer extras' ) . $st( 'Default: the Categories as chips on the dark footer' ) . '<div class="kit-demo-ink">' . do_shortcode( '[aw_categories_list]' ) . '</div></div>';
		$o .= '<div>' . $h( 'Buttons (Animal Writes)' ) . $st( 'Default: the home hero buttons (full width on phones)' ) . '<div class="aw-ed-hero-actions" style="display:flex;flex-wrap:wrap;gap:var(--kit-space-3)"><a class="kit-btn aw-btn" href="#">Find help near you</a><a class="kit-btn kit-btn--secondary aw-btn" href="#">Report cruelty</a></div></div>';
		$o .= '<div>' . $h( 'Finder band' ) . $st( 'Default: the band around the place finder' ) . '<section class="aw-finder-band" style="padding-inline:var(--kit-space-4);background:var(--kit-color-surface);border-radius:var(--kit-radius-card)"><h2>Find animal help near you</h2><p>Rescue groups, shelters and government vets by state and district.</p></section></div>';
		$o .= '<div>' . $h( 'Local card' ) . ( function_exists( 'aw_local_card' )
			? $st( 'News: Organisations in {State}' ) . aw_local_card( 'Organisations in Kerala', 'Rescue groups, shelters and government vets you can contact in Kerala.', '<a class="kit-btn kit-btn--secondary" href="#">See all in Kerala</a>' )
				. $st( 'Directory district with fewer than 3 listings: Few listings in {District} yet' ) . aw_local_card( 'Few listings in Wayanad yet', 'Know an organisation here? Add it, or see the whole state.', '<a class="kit-btn kit-btn--secondary" href="#">Add an organisation</a><a class="kit-btn kit-btn--ghost" href="#">See all in Kerala</a>' )
			: '<p><em>Not available.</em></p>' ) . '</div>';
		$sp = function_exists( 'aw_block_sponsored_item' ) ? get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 1, 'meta_key' => 'aw_sponsored', 'meta_value' => '1', 'fields' => 'ids' ) ) : array();
		$o .= '<div>' . $h( 'Sponsored Resource' ) . ( $sp ? $st( 'Resource with Sponsored ticked' ) . aw_block_sponsored_item( array( 'postId' => $sp[0] ) ) : $st( 'Resource with Sponsored ticked' ) . '<p><em>No sponsored Resource yet.</em></p>' )
			. $st( 'Nothing (Resource not sponsored or not published)' ) . '<p><em>(prints nothing)</em></p></div>';
		$pic = (int) get_post_thumbnail_id( $sp ? $sp[0] : 0 );
		if ( ! $pic ) {
			$any = get_posts( array( 'post_type' => 'any', 'post_status' => 'publish', 'numberposts' => 1, 'meta_key' => '_thumbnail_id', 'fields' => 'ids' ) );
			$pic = $any ? (int) get_post_thumbnail_id( $any[0] ) : 0;
		}
		if ( function_exists( 'aw_block_sponsored_banner' ) ) {
			$o .= '<div>' . $h( 'Sponsored banner' )
				. $st( 'Picture + heading + text + button' ) . aw_block_sponsored_banner( array( 'url' => home_url( '/' ), 'heading' => 'Support animal shelters near you', 'text' => 'A partner message shown on Resources.', 'linkText' => 'Learn more', 'mediaId' => $pic, 'sponsor' => 'Example partner' ) )
				. $st( 'Heading as the link (no button text)' ) . aw_block_sponsored_banner( array( 'url' => home_url( '/' ), 'heading' => 'Support animal shelters near you', 'text' => 'A partner message shown on Resources.', 'mediaId' => $pic, 'sponsor' => 'Example partner' ) )
				. $st( 'No picture' ) . aw_block_sponsored_banner( array( 'url' => home_url( '/' ), 'heading' => 'Support animal shelters near you', 'text' => 'A partner message shown on Resources.', 'linkText' => 'Learn more', 'sponsor' => 'Example partner' ) ) . '</div>';
		}
		$o .= '<div>' . $h( 'Map picker (listing edit screen)' ) . $st( 'Lives on the listing edit screen in wp-admin (Directory > edit a listing): no coordinates shows India, a click drops a pin; with coordinates the pin is at the point at zoom 16; typing latitude or longitude moves the pin' )
			. '<div class="aw-map-picker" style="display:grid;place-items:center;min-height:10rem;border:1px dashed var(--kit-color-line);border-radius:var(--kit-radius-card);color:var(--kit-color-muted)">Map (OpenStreetMap) · Latitude · Longitude</div></div>';
		return $html . '<section class="kit-demo-blocks">' . $o . '</section>';
	},
	20
);
