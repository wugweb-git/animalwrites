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
 * @return array{links:array<string,string>,prefix:string,name:string,role:string,place:string,org:int,campaign:int,credit:string,approval:string,approved_on:string}
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
		'links'       => array_filter(
			array(
				'Instagram' => (string) get_post_meta( $id, 'kits_profile_instagram', true ),
				'LinkedIn'  => (string) get_post_meta( $id, 'kits_profile_linkedin', true ),
				'Facebook'  => (string) get_post_meta( $id, 'kits_profile_facebook', true ),
				'YouTube'   => (string) get_post_meta( $id, 'kits_profile_youtube', true ),
				'X'         => (string) get_post_meta( $id, 'kits_profile_x', true ),
				'WhatsApp'  => (string) get_post_meta( $id, 'kits_profile_whatsapp', true ),
			)
		),
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
			'label'   => in_array( get_post_meta( $id, $p . 'label', true ), array( 'own-words', 'org-words' ), true ) ? get_post_meta( $id, $p . 'label', true ) : 'reported',
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

/** Two capital letters from a name, for the plain tile used until there is a permitted photo. */
function kits_profile_initials( $name ) {
	$out = '';
	foreach ( array_slice( preg_split( '/\s+/', trim( $name ) ), 0, 2 ) as $word ) {
		$out .= '' !== $word ? mb_strtoupper( mb_substr( $word, 0, 1 ) ) : '';
	}
	return $out;
}

/**
 * Sources grouped by page: one group per link (or per outlet when there is no link), newest first, each with every claim
 * that page backs. The highlight figures point at their group.
 *
 * @param int $id Post ID.
 * @return array<int,array{id:string,source:string,url:string,archive:string,date:string,label:string,claims:string[]}>
 */
function kits_profile_sources( $id ) {
	$groups = array();
	foreach ( kits_profile_evidence( $id ) as $r ) {
		$k = '' !== $r['url'] ? $r['url'] : 'n-' . $r['source'] . '-' . $r['i'];
		if ( ! isset( $groups[ $k ] ) ) {
			$groups[ $k ] = array(
				'id'      => 'kit-profile-src-' . $r['i'],
				'source'  => $r['source'],
				'url'     => $r['url'],
				'archive' => $r['archive'],
				'date'    => $r['date'],
				'label'   => $r['label'],
				'claims'  => array(),
				'first'   => $r['i'],
			);
		}
		$groups[ $k ]['claims'][] = $r['claim'];
		if ( '' === $groups[ $k ]['archive'] ) {
			$groups[ $k ]['archive'] = $r['archive'];
		}
	}
	$groups = array_values( $groups );
	usort(
		$groups,
		function ( $a, $b ) { // newest first; undated last; typed order breaks ties
			$da = '' === $a['date'] ? '0000-00-00' : $a['date'];
			$db = '' === $b['date'] ? '0000-00-00' : $b['date'];
			return $da === $db ? $a['first'] <=> $b['first'] : strcmp( $db, $da );
		}
	);
	return $groups;
}

/** "Follow {first name}": their own pages, each a 44px link that opens in a new tab. */
function kits_profile_follow_html( $f ) {
	if ( ! $f['links'] ) {
		return '';
	}
	$first = kits_profile_first( $f );
	/* translators: %s: first name. */
	$label = '' !== $first ? sprintf( __( '%s online', 'kits-by-wugweb' ), $first ) : __( 'Online', 'kits-by-wugweb' );
	$out   = '<nav class="kit-profile-follow" aria-label="' . esc_attr( $label ) . '"><ul>';
	foreach ( $f['links'] as $name => $url ) {
		$out .= '<li><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener nofollow me">' . esc_html( $name ) . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'kits-by-wugweb' ) . '</span></a></li>';
	}
	return $out . '</ul></nav>';
}

/**
 * The hero banner: a dark block with a ghost monogram behind a plain initials tile (no photo until they send one and allow it),
 * their role and city, the figures worth seeing at once (each linking to the page that backs it) and where to follow them.
 * The tile and the monogram are decoration and hidden from screen readers; the name is already the page heading.
 *
 * @param int   $id Post ID.
 * @param array $f  Fields from kits_profile_fields().
 * @return string
 */
function kits_profile_hero_html( $id, $f ) {
	if ( '' === $f['role'] && '' === $f['place'] ) {
		return '';
	}
	$initials = kits_profile_initials( $f['name'] );
	$out      = '<section class="kit-profile-hero">';
	$out     .= '' !== $initials ? '<span class="kit-profile-monogram" aria-hidden="true">' . esc_html( $initials ) . '</span>' : '';
	$out     .= '<div class="kit-profile-who">';
	$out     .= '' !== $initials ? '<span class="kit-profile-initials" aria-hidden="true">' . esc_html( $initials ) . '</span>' : '';
	$out     .= '<p class="kit-profile-role">';
	$out     .= '' !== $f['role'] ? '<strong>' . esc_html( $f['role'] ) . '</strong>' : '';
	$out     .= '' !== $f['place'] ? '<span>' . esc_html( $f['place'] ) . '</span>' : '';
	$out     .= '</p></div>';

	$figs = '';
	foreach ( kits_profile_sources( $id ) as $g ) {
		$h = '';
		foreach ( kits_profile_evidence( $id ) as $r ) {
			if ( '' !== $r['highlight'] && ( ( '' !== $g['url'] && $r['url'] === $g['url'] ) || ( '' === $g['url'] && $r['i'] === $g['first'] ) ) ) {
				$h = $r['highlight'];
				break;
			}
		}
		if ( '' !== $h ) {
			$line  = $g['source'] . ( '' !== $g['source'] && '' !== $g['date'] ? ', ' : '' ) . ( '' !== $g['date'] ? wp_date( 'M Y', strtotime( $g['date'] ) ) : '' );
			$figs .= '<li><a href="#' . esc_attr( $g['id'] ) . '"><strong class="kit-profile-figure">' . esc_html( $h ) . '</strong><span class="kit-profile-line">' . esc_html( $line ) . '</span></a></li>';
		}
	}
	$out .= '' !== $figs ? '<ul class="kit-profile-figures">' . $figs . '</ul>' : '';
	$out .= kits_profile_follow_html( $f );
	return $out . '</section>';
}

/** Their organisation as a card: the Directory listing, with what the listing says about it. */
function kits_profile_org_card( $org, $f ) {
	$first = kits_profile_first( $f );
	$name  = get_the_title( $org );
	$bits  = array();
	$year  = (string) get_post_meta( $org, 'aw_founded', true );
	if ( preg_match( '/^\d{4}$/', $year ) ) {
		/* translators: %s: year. */
		$bits[] = sprintf( __( 'Since %s', 'kits-by-wugweb' ), $year );
	}
	$animals = (string) get_post_meta( $org, 'aw_animals', true );
	if ( '' !== $animals ) {
		$bits[] = $animals;
	}
	/* translators: %s: first name. */
	$h   = '' !== $first ? sprintf( __( '%s’s organisation', 'kits-by-wugweb' ), $first ) : __( 'Their organisation', 'kits-by-wugweb' );
	$out = '<section class="kit-profile-org" aria-labelledby="kit-profile-org-h"><h2 id="kit-profile-org-h">' . esc_html( $h ) . '</h2>';
	$out .= '<a class="kit-profile-orgcard" href="' . esc_url( get_permalink( $org ) ) . '"><span class="kit-profile-initials" aria-hidden="true">' . esc_html( kits_profile_initials( $name ) ) . '</span><span class="kit-profile-orgtext"><strong>' . esc_html( $name ) . '</strong>';
	$out .= $bits ? '<span>' . esc_html( implode( ' · ', $bits ) ) . '</span>' : '';
	$out .= '<span class="kit-profile-orgmore">' . esc_html__( 'See the listing', 'kits-by-wugweb' ) . ' <span aria-hidden="true">→</span></span></span></a></section>';
	return $out;
}

/**
 * The hero banner, the action row and the organisation card, printed before the person's text.
 *
 * @param int $id Post ID.
 * @return string
 */
function kits_profile_head_html( $id ) {
	$f   = kits_profile_fields( $id );
	$org = $f['org'] && 'publish' === get_post_status( $f['org'] ) ? $f['org'] : 0;
	$out = kits_profile_hero_html( $id, $f );

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
	$out .= $org ? kits_profile_org_card( $org, $f ) : '';
	return $out;
}

/** One source tile: the page's name, its date and kind, what it backs, and the links. */
function kits_profile_source_tile( $g, $f, $shade ) {
	$first = kits_profile_first( $f );
	if ( 'own-words' === $g['label'] ) {
		$chip = '' !== $first ? sprintf( /* translators: %s: first name. */ __( '%s’s own words', 'kits-by-wugweb' ), $first ) : __( 'In their own words', 'kits-by-wugweb' );
	} elseif ( 'org-words' === $g['label'] ) {
		$chip = __( 'The organisation’s own site', 'kits-by-wugweb' );
	} else {
		$chip = __( 'Reported', 'kits-by-wugweb' );
	}
	$points = '';
	foreach ( $g['claims'] as $c ) {
		$points .= '<li>' . esc_html( $c ) . '</li>';
	}
	$links = '';
	if ( '' !== $g['url'] ) {
		/* translators: %s: outlet. */
		$links .= kits_profile_ext_link( $g['url'], '' !== $g['source'] ? sprintf( __( 'Read it on %s', 'kits-by-wugweb' ), $g['source'] ) : __( 'Read the source', 'kits-by-wugweb' ) );
	}
	if ( '' !== $g['archive'] ) {
		$links .= kits_profile_ext_link( $g['archive'], __( 'Archive copy', 'kits-by-wugweb' ) );
	}
	return '<li class="kit-profile-tile is-' . esc_attr( $shade ) . '" id="' . esc_attr( $g['id'] ) . '"><div class="kit-profile-src"><h3 class="kit-profile-outlet">' . esc_html( $g['source'] ) . '</h3><p class="kit-profile-meta"><span class="kit-chip">' . esc_html( $chip ) . '</span>' . ( '' !== $g['date'] ? ' ' . kits_date( $g['date'] ) : '' ) . '</p><ul class="kit-profile-points">' . $points . '</ul>' . ( '' !== $links ? '<p class="kit-profile-links">' . $links . '</p>' : '' ) . '</div></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every part is escaped above; kits_date() returns escaped markup.
}

/**
 * Where it all comes from, in three short groups of source tiles (news, their own words, the organisation's own site),
 * then the two ways on: help in their city and the related campaign. Printed after the person's text.
 *
 * @param int $id Post ID.
 * @return string
 */
function kits_profile_tail_html( $id ) {
	$f     = kits_profile_fields( $id );
	$out   = '';
	$first = kits_profile_first( $f );
	$by    = array( 'reported' => array(), 'own-words' => array(), 'org-words' => array() );
	foreach ( kits_profile_sources( $id ) as $g ) {
		$by[ $g['label'] ][] = $g;
	}
	/* translators: %s: first name. */
	$words = '' !== $first ? sprintf( __( 'In %s’s own words', 'kits-by-wugweb' ), $first ) : __( 'In their own words', 'kits-by-wugweb' );
	$heads = array(
		'reported'  => array( 'kit-profile-news-h', __( 'In the news', 'kits-by-wugweb' ) ),
		'own-words' => array( 'kit-profile-words-h', $words ),
		'org-words' => array( 'kit-profile-site-h', __( 'From the organisation’s own site', 'kits-by-wugweb' ) ),
	);
	foreach ( $heads as $label => $h ) {
		if ( ! $by[ $label ] ) {
			continue;
		}
		$tiles = '';
		foreach ( $by[ $label ] as $n => $g ) {
			$tiles .= kits_profile_source_tile( $g, $f, 0 === $n % 2 ? 'plain' : 'soft' );
		}
		$out .= '<section class="kit-profile-sources" aria-labelledby="' . esc_attr( $h[0] ) . '"><h2 id="' . esc_attr( $h[0] ) . '">' . esc_html( $h[1] ) . '</h2><ul class="kit-profile-bento">' . $tiles . '</ul></section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	$next = '';
	$help = kits_profile_help_url( $f['place'] );
	if ( '' !== $help ) {
		$city = trim( explode( ',', $f['place'] )[0] );
		/* translators: %s: city. */
		$next .= '<li class="kit-profile-tile is-ink"><a href="' . esc_url( $help ) . '"><h2 class="kit-profile-figure">' . esc_html( sprintf( __( 'Help in %s', 'kits-by-wugweb' ), $city ) ) . '</h2><span class="kit-profile-line">' . esc_html__( 'Animal rescue and care near you', 'kits-by-wugweb' ) . ' <span aria-hidden="true">→</span></span></a></li>';
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

