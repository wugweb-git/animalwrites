<?php
/**
 * Profiles (owner, 27 Nov 2026): a Resource (WordPress Post) tagged "profile" tells about a person who makes a
 * difference, with the evidence behind each claim. It uses the one item template (single.html); this file adds what a
 * profile needs around the person's own text (the post content):
 *
 *   before the text   the action row (their organisation's listing, help in their city) and "At a glance"
 *   after the text    Evidence (one card per claim: source, date, label, link, archive copy), then "Help nearby"
 *                     and the related campaign
 *   Sources block     the approval line ("Checked with them on ..." or "Based on public reporting"), see item-head.php
 *
 * Fields are post meta registered in content/meta.php (SCF group_aw_profile: Person, Approval, Evidence). The evidence
 * rows are an SCF repeater, read here from its stored meta so the page does not depend on the field plugin being active.
 * Only the city and state are ever printed: no street address, phone or exact location (owner, 27 Nov 2026).
 *
 * Phone first: one column, cards instead of a table, 44px actions, nothing folded (docs/plans/people-vishakha.md, 12).
 *
 * @package kits-by-wugweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Is this a profile (a Resource tagged "profile")?
 *
 * @param int $id Post ID.
 * @return bool
 */
function kits_is_profile( $id ) {
	return 'post' === get_post_type( $id ) && has_term( 'profile', 'post_tag', $id );
}

/**
 * A profile's person fields.
 *
 * @param int $id Post ID.
 * @return array{prefix:string,name:string,role:string,place:string,org:int,campaign:int,credit:string,approval:string,approved_on:string}
 */
function kits_profile_fields( $id ) {
	return array(
		'prefix'      => (string) get_post_meta( $id, 'kits_profile_prefix', true ),
		'name'        => (string) get_post_meta( $id, 'kits_profile_name', true ),
		'role'        => (string) get_post_meta( $id, 'kits_profile_role', true ),
		'place'       => (string) get_post_meta( $id, 'kits_profile_place', true ),
		'org'         => (int) get_post_meta( $id, 'kits_profile_org', true ),
		'campaign'    => (int) get_post_meta( $id, 'kits_profile_campaign', true ),
		'credit'      => (string) get_post_meta( $id, 'kits_profile_photo_credit', true ),
		'approval'    => (string) get_post_meta( $id, 'kits_profile_approval', true ),
		'approved_on' => (string) get_post_meta( $id, 'kits_profile_approved_on', true ),
	);
}

/**
 * A profile's evidence rows, from the repeater's stored meta (kits_profile_evidence = the row count, then
 * kits_profile_evidence_{n}_{field}). Rows with no claim are left out.
 *
 * @param int $id Post ID.
 * @return array<int,array{claim:string,source:string,label:string,url:string,archive:string,date:string}>
 */
function kits_profile_evidence( $id ) {
	$n    = min( 30, max( 0, (int) get_post_meta( $id, 'kits_profile_evidence', true ) ) );
	$rows = array();
	for ( $i = 0; $i < $n; $i++ ) {
		$p     = 'kits_profile_evidence_' . $i . '_';
		$claim = trim( (string) get_post_meta( $id, $p . 'claim', true ) );
		if ( '' === $claim ) {
			continue;
		}
		$date   = (string) get_post_meta( $id, $p . 'date', true );
		$rows[] = array(
			'claim'   => $claim,
			'source'  => trim( (string) get_post_meta( $id, $p . 'source', true ) ),
			'label'   => 'own-words' === get_post_meta( $id, $p . 'label', true ) ? 'own-words' : 'reported',
			'url'     => esc_url_raw( (string) get_post_meta( $id, $p . 'url', true ) ),
			'archive' => esc_url_raw( (string) get_post_meta( $id, $p . 'archive_url', true ) ),
			'date'    => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : '',
		);
	}
	return $rows;
}

/**
 * The Directory page for "City, State" (e.g. /directory/uttar-pradesh/lucknow/), or '' when the place is not both.
 *
 * @param string $place City and state.
 * @return string
 */
function kits_profile_help_url( $place ) {
	$parts = array_map( 'trim', explode( ',', $place ) );
	if ( count( $parts ) < 2 || '' === $parts[0] || '' === $parts[1] ) {
		return '';
	}
	static $seen = array();
	if ( ! isset( $seen[ $place ] ) ) { // only when the Directory has a listing in that city, so the link never leads to a missing page
		$seen[ $place ] = (bool) get_posts(
			array(
				'post_type'     => 'aw_listing',
				'post_status'   => 'publish',
				'numberposts'   => 1,
				'fields'        => 'ids',
				'no_found_rows' => true,
				'meta_query'    => array( array( 'key' => 'aw_area', 'value' => $parts[0], 'compare' => 'LIKE' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one listing, once per page
			)
		);
	}
	return $seen[ $place ] ? home_url( '/directory/' . sanitize_title( $parts[1] ) . '/' . sanitize_title( $parts[0] ) . '/' ) : '';
}

/** An external link that opens in a new tab, with the words for a screen reader. */
function kits_profile_ext_link( $url, $text, $class = '' ) {
	return '<a' . ( '' !== $class ? ' class="' . esc_attr( $class ) . '"' : '' ) . ' href="' . esc_url( $url ) . '" target="_blank" rel="noopener nofollow">' . esc_html( $text ) . ' <span aria-hidden="true">↗</span><span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'kits-by-wugweb' ) . '</span></a>';
}

/**
 * Who this is, first on the page: a plain initials tile (no photo until they send one and allow it), their role and city.
 * The tile is decoration, so it is hidden from screen readers; the name is already the page heading.
 *
 * @param array $f Fields from kits_profile_fields().
 * @return string
 */
function kits_profile_identity_html( $f ) {
	if ( '' === $f['role'] && '' === $f['place'] ) {
		return '';
	}
	$initials = '';
	foreach ( array_slice( preg_split( '/\s+/', trim( $f['name'] ) ), 0, 2 ) as $word ) {
		$initials .= '' !== $word ? mb_strtoupper( mb_substr( $word, 0, 1 ) ) : '';
	}
	$out  = '<div class="kit-profile-who">';
	$out .= '' !== $initials ? '<span class="kit-profile-initials" aria-hidden="true">' . esc_html( $initials ) . '</span>' : '';
	$out .= '<p class="kit-profile-role">';
	$out .= '' !== $f['role'] ? '<strong>' . esc_html( $f['role'] ) . '</strong>' : '';
	$out .= '' !== $f['place'] ? '<span>' . esc_html( $f['place'] ) . '</span>' : '';
	$out .= '</p></div>';
	return $out;
}

/**
 * The action row and "At a glance", printed before the person's text.
 *
 * @param int $id Post ID.
 * @return string
 */
function kits_profile_head_html( $id ) {
	$f   = kits_profile_fields( $id );
	$org = $f['org'] && 'publish' === get_post_status( $f['org'] ) ? $f['org'] : 0;
	$out = kits_profile_identity_html( $f );

	$acts = '';
	if ( $org ) {
		/* translators: %s: the organisation's name. */
		$acts .= '<a class="wp-element-button kit-btn" href="' . esc_url( get_permalink( $org ) ) . '">' . esc_html( sprintf( __( 'Know more about %s', 'kits-by-wugweb' ), get_the_title( $org ) ) ) . '</a>';
	}
	$help = kits_profile_help_url( $f['place'] );
	if ( '' !== $help ) {
		/* translators: %s: city. */
		$acts .= '<a class="wp-element-button kit-btn is-style-kits-ghost" href="' . esc_url( $help ) . '">' . esc_html( sprintf( __( 'Find help in %s', 'kits-by-wugweb' ), trim( explode( ',', $f['place'] )[0] ) ) ) . '</a>';
	}
	$out .= '' !== $acts ? '<p class="kit-profile-actions">' . $acts . '</p>' : '';

	$rows = array(); // role and place are in the identity row above, so they are not repeated here
	if ( $org ) {
		$rows[ __( 'Organisation', 'kits-by-wugweb' ) ] = '<a href="' . esc_url( get_permalink( $org ) ) . '">' . esc_html( get_the_title( $org ) ) . '</a>';
		$founded = (string) get_post_meta( $org, 'aw_founded', true );
		if ( preg_match( '/^\d{4}$/', $founded ) ) {
			$rows[ __( 'Founded', 'kits-by-wugweb' ) ] = esc_html( $founded );
		}
		$animals = (string) get_post_meta( $org, 'aw_animals', true );
		if ( '' !== $animals ) {
			$rows[ __( 'Cares for', 'kits-by-wugweb' ) ] = esc_html( $animals );
		}
		$site = (string) get_post_meta( $org, 'aw_website', true );
		if ( '' !== $site ) {
			$rows[ __( 'Website', 'kits-by-wugweb' ) ] = kits_profile_ext_link( $site, preg_replace( '#^https?://(www\.)?#', '', untrailingslashit( $site ) ) );
		}
	}
	if ( $rows ) {
		$out .= '<section class="kit-profile-glance" aria-labelledby="kit-profile-glance-h"><h2 id="kit-profile-glance-h">' . esc_html__( 'At a glance', 'kits-by-wugweb' ) . '</h2><dl>';
		foreach ( $rows as $k => $v ) {
			$out .= '<div><dt>' . esc_html( $k ) . '</dt><dd>' . $v . '</dd></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each value is escaped above.
		}
		$out .= '</dl></section>';
	}
	return $out;
}

/**
 * Evidence, "Help nearby" and the related campaign, printed after the person's text.
 *
 * @param int $id Post ID.
 * @return string
 */
function kits_profile_tail_html( $id ) {
	$f   = kits_profile_fields( $id );
	$out = '';

	$cards = '';
	foreach ( kits_profile_evidence( $id ) as $r ) {
		$meta = '<span class="kit-chip">' . esc_html( 'own-words' === $r['label'] ? __( 'In their own words', 'kits-by-wugweb' ) : __( 'Reported by', 'kits-by-wugweb' ) ) . '</span>';
		$meta .= ' <span class="kit-evidence-source">' . esc_html( $r['source'] ) . ( '' !== $r['source'] && '' !== $r['date'] ? ', ' : '' ) . ( '' !== $r['date'] ? kits_date( $r['date'] ) : '' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kits_date() returns escaped markup.
		$links = '';
		if ( '' !== $r['url'] ) {
			/* translators: %s: outlet. */
			$links .= kits_profile_ext_link( $r['url'], '' !== $r['source'] ? sprintf( __( 'Read the %s report', 'kits-by-wugweb' ), $r['source'] ) : __( 'Read the source', 'kits-by-wugweb' ) );
		}
		if ( '' !== $r['archive'] ) {
			$links .= kits_profile_ext_link( $r['archive'], __( 'Archive copy', 'kits-by-wugweb' ) );
		}
		$cards .= '<li class="kit-evidence-card"><h3 class="kit-evidence-claim">' . esc_html( $r['claim'] ) . '</h3><p class="kit-evidence-meta">' . $meta . '</p>' . ( '' !== $links ? '<p class="kit-evidence-links">' . $links . '</p>' : '' ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every part is escaped above.
	}
	if ( '' !== $cards ) {
		$out .= '<section class="kit-evidence" aria-labelledby="kit-evidence-h"><h2 id="kit-evidence-h">' . esc_html__( 'Evidence', 'kits-by-wugweb' ) . '</h2><p class="kit-evidence-lead">' . esc_html__( 'Every claim on this page, with where it comes from.', 'kits-by-wugweb' ) . '</p><ol class="kit-evidence-list">' . $cards . '</ol></section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	$more = '';
	$help = kits_profile_help_url( $f['place'] );
	if ( '' !== $help ) {
		/* translators: %s: city. */
		$more .= '<li><a href="' . esc_url( $help ) . '">' . esc_html( sprintf( __( 'Animal help in %s', 'kits-by-wugweb' ), trim( explode( ',', $f['place'] )[0] ) ) ) . '</a></li>';
	}
	if ( $f['campaign'] && 'publish' === get_post_status( $f['campaign'] ) ) {
		$more .= '<li><a href="' . esc_url( get_permalink( $f['campaign'] ) ) . '">' . esc_html( get_the_title( $f['campaign'] ) ) . '</a></li>';
	}
	if ( '' !== $more ) {
		$out .= '<section class="kit-profile-more" aria-labelledby="kit-profile-more-h"><h2 id="kit-profile-more-h">' . esc_html__( 'Go further', 'kits-by-wugweb' ) . '</h2><ul>' . $more . '</ul></section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	return $out;
}

/** The approval line for the Sources block: who checked it, or that it rests on public reporting. */
function kits_profile_approval_line( $id ) {
	$f    = kits_profile_fields( $id );
	$when = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $f['approved_on'] ) ? kits_date( $f['approved_on'] ) : '';
	if ( 'approved' === $f['approval'] ) {
		return '<span>' . esc_html__( 'Checked with them on', 'kits-by-wugweb' ) . ' ' . $when . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	if ( 'public-reporting' === $f['approval'] ) {
		return '<span>' . esc_html__( 'Based on public reporting.', 'kits-by-wugweb' ) . '</span>';
	}
	return '';
}

// Around the person's own text, on the profile's own page only (the Site Editor and cards in lists are untouched).
add_filter(
	'render_block_core/post-content',
	function ( $html, $block ) {
		$id = (int) ( $block['attrs']['postId'] ?? get_the_ID() );
		if ( ! $id || ! function_exists( 'aw_item_here' ) || ! aw_item_here( $id ) || ! kits_is_profile( $id ) ) {
			return $html;
		}
		return kits_profile_head_html( $id ) . $html . kits_profile_tail_html( $id );
	},
	30,
	2
);

