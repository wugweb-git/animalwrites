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
 * @return array<int,array{i:int,highlight:string,claim:string,source:string,label:string,url:string,archive:string,date:string}>
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
			'i'         => $i,
			'highlight' => trim( (string) get_post_meta( $id, $p . 'highlight', true ) ),
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

/** First name, for headings that speak about the person ("Vishakha's organisation"). */
function kits_profile_first( $f ) {
	$w = preg_split( '/\s+/', trim( $f['name'] ) );
	return $w[0] ?? '';
}

/** An external link that opens in a new tab, with the words for a screen reader. */
function kits_profile_ext_link( $url, $text, $class = '' ) {
	return '<a' . ( '' !== $class ? ' class="' . esc_attr( $class ) . '"' : '' ) . ' href="' . esc_url( $url ) . '" target="_blank" rel="noopener nofollow">' . esc_html( $text ) . ' <span aria-hidden="true">↗</span><span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'kits-by-wugweb' ) . '</span></a>';
}

/**
 * Who this is, first on the page: a dark card with a plain initials tile (no photo until they send one and allow it), their
 * role and city. The tile is decoration, so it is hidden from screen readers; the name is already the page heading.
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

/** One bento tile: a big figure, a small line under it, optionally the whole tile a link. */
function kits_profile_tile( $shade, $figure, $line, $href = '' ) {
	$in = '<strong class="kit-profile-figure">' . esc_html( $figure ) . '</strong><span class="kit-profile-line">' . esc_html( $line ) . '</span>';
	return '<li class="kit-profile-tile is-' . esc_attr( $shade ) . '">' . ( '' !== $href ? '<a href="' . esc_url( $href ) . '">' . $in . '</a>' : $in ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every part is escaped above.
}

/**
 * The identity card, the action row and the highlight tiles, printed before the person's text.
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

	// Highlights: the figures worth seeing at once. Each links down to the dated entry that backs it.
	$shades = array( 'soft', 'plain', 'soft' ); // the dark tiles are the identity card above and "Help" below
	$tiles  = array();
	foreach ( kits_profile_evidence( $id ) as $r ) {
		if ( '' !== $r['highlight'] ) {
			$line    = $r['source'] . ( '' !== $r['source'] && '' !== $r['date'] ? ', ' : '' ) . ( '' !== $r['date'] ? wp_date( 'M Y', strtotime( $r['date'] ) ) : '' );
			$tiles[] = array( $r['highlight'], $line, '#kit-profile-story-' . $r['i'] );
		}
	}
	if ( $org ) {
		$founded = (string) get_post_meta( $org, 'aw_founded', true );
		if ( preg_match( '/^\d{4}$/', $founded ) ) {
			$tiles[] = array( __( 'Since', 'kits-by-wugweb' ) . ' ' . $founded, get_the_title( $org ), get_permalink( $org ) );
		}
	}
	if ( $tiles ) {
		$out .= '<ul class="kit-profile-bento">';
		foreach ( $tiles as $n => $t ) {
			$out .= kits_profile_tile( $shades[ $n % 3 ], $t[0], $t[1], $t[2] );
		}
		$out .= '</ul>';
	}
	return $out;
}

/**
 * Her story with sources (a dated timeline, one entry per claim: date, the claim, who said it, links), then the two ways on:
 * help in their city and the related campaign. Printed after the person's text.
 *
 * @param int $id Post ID.
 * @return string
 */
function kits_profile_tail_html( $id ) {
	$f    = kits_profile_fields( $id );
	$out  = '';
	$name = kits_profile_first( $f );

	$rows = kits_profile_evidence( $id );
	usort(
		$rows,
		function ( $a, $b ) { // oldest first; undated last; the order they were typed breaks ties
			$da = '' === $a['date'] ? '9999-99-99' : $a['date'];
			$db = '' === $b['date'] ? '9999-99-99' : $b['date'];
			return $da === $db ? $a['i'] <=> $b['i'] : strcmp( $da, $db );
		}
	);
	$items = '';
	foreach ( $rows as $r ) {
		$meta  = '<span class="kit-chip">' . esc_html( 'own-words' === $r['label'] ? ( '' !== $name ? sprintf( /* translators: %s: first name. */ __( '%s’s own words', 'kits-by-wugweb' ), $name ) : __( 'In their own words', 'kits-by-wugweb' ) ) : __( 'Reported by', 'kits-by-wugweb' ) ) . '</span>';
		$meta .= ' <span class="kit-profile-source">' . esc_html( $r['source'] ) . '</span>';
		$links = '';
		if ( '' !== $r['url'] ) {
			/* translators: %s: outlet. */
			$links .= kits_profile_ext_link( $r['url'], '' !== $r['source'] ? sprintf( __( 'Read it on %s', 'kits-by-wugweb' ), $r['source'] ) : __( 'Read the source', 'kits-by-wugweb' ) );
		}
		if ( '' !== $r['archive'] ) {
			$links .= kits_profile_ext_link( $r['archive'], __( 'Archive copy', 'kits-by-wugweb' ) );
		}
		$items .= '<li class="kit-profile-entry" id="kit-profile-story-' . (int) $r['i'] . '">' . ( '' !== $r['date'] ? '<span class="kit-profile-when">' . kits_date( $r['date'] ) . '</span>' : '' ) . '<p class="kit-profile-claim">' . esc_html( $r['claim'] ) . '</p><p class="kit-profile-meta">' . $meta . '</p>' . ( '' !== $links ? '<p class="kit-profile-links">' . $links . '</p>' : '' ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every part is escaped above; kits_date() returns escaped markup.
	}
	if ( '' !== $items ) {
		/* translators: %s: first name. */
		$h    = '' !== $name ? sprintf( __( '%s’s story, with sources', 'kits-by-wugweb' ), $name ) : __( 'The story, with sources', 'kits-by-wugweb' );
		$out .= '<section class="kit-profile-story" aria-labelledby="kit-profile-story-h"><h2 id="kit-profile-story-h">' . esc_html( $h ) . '</h2><ol class="kit-profile-timeline">' . $items . '</ol></section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	$next = '';
	$help = kits_profile_help_url( $f['place'] );
	if ( '' !== $help ) {
		$city = trim( explode( ',', $f['place'] )[0] );
		/* translators: %s: city. */
		$next .= '<li class="kit-profile-tile is-ink"><a href="' . esc_url( $help ) . '"><h2 class="kit-profile-figure">' . esc_html( sprintf( __( 'Help in %s', 'kits-by-wugweb' ), $city ) ) . '</h2><span class="kit-profile-line">' . esc_html( sprintf( __( 'Animal rescue and care near you', 'kits-by-wugweb' ) ) ) . ' <span aria-hidden="true">→</span></span></a></li>';
	}
	if ( $f['campaign'] && 'publish' === get_post_status( $f['campaign'] ) ) {
		$next .= '<li class="kit-profile-tile is-soft"><a href="' . esc_url( get_permalink( $f['campaign'] ) ) . '"><h2 class="kit-profile-figure">' . esc_html__( 'Part of a campaign', 'kits-by-wugweb' ) . '</h2><span class="kit-profile-line">' . esc_html( get_the_title( $f['campaign'] ) ) . ' <span aria-hidden="true">→</span></span></a></li>';
	}
	if ( '' !== $next ) {
		$out .= '<ul class="kit-profile-bento kit-profile-next">' . $next . '</ul>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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

