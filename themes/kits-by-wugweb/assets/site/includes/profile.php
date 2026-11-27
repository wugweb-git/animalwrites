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
	$icons = array( 'Instagram' => 'instagram', 'LinkedIn' => 'linkedin', 'Facebook' => 'facebook', 'YouTube' => 'youtube', 'X' => 'x-brand', 'WhatsApp' => 'brand-whatsapp' );
	foreach ( $f['links'] as $name => $url ) {
		$out .= '<li><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener nofollow me"><span class="kit-ico kit-ico--' . esc_attr( $icons[ $name ] ?? 'link' ) . '" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html( $name ) . ' ' . esc_html__( '(opens in a new tab)', 'kits-by-wugweb' ) . '</span></a></li>';
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
	$photo    = has_post_thumbnail( $id ) ? get_the_post_thumbnail( $id, 'large', array( 'class' => 'kit-profile-photo', 'alt' => trim( $f['prefix'] . ' ' . $f['name'] ), 'loading' => 'eager', 'decoding' => 'async' ) ) : '';
	$out      = '<section class="kit-profile-hero' . ( '' !== $photo ? ' has-photo' : '' ) . '">';
	$out     .= '' !== $photo ? '<figure class="kit-profile-media">' . $photo . '</figure>' : ( '' !== $initials ? '<span class="kit-profile-monogram" aria-hidden="true">' . esc_html( $initials ) . '</span>' : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core markup for the featured image.
	$out     .= '<div class="kit-profile-herotext"><p class="kit-profile-tags">';
	$out     .= '' !== $f['role'] ? '<span class="kit-profile-tag">' . esc_html( $f['role'] ) . '</span>' : '';
	$out     .= '' !== $f['place'] ? '<span class="kit-profile-tag is-solid">' . esc_html( $f['place'] ) . '</span>' : '';
	$out     .= '</p>';

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
			$num   = $h;
			$label = '';
			if ( preg_match( '/^((?:about\s+)?[\d][\d,.]*\+?)\s+(.+)$/iu', $h, $mm ) ) {
				$num   = $mm[1];
				$label = $mm[2];
			}
			$figs .= '<li><a href="#' . esc_attr( $g['id'] ) . '"><strong class="kit-profile-figure">' . esc_html( $num ) . '</strong>' . ( '' !== $label ? '<span class="kit-profile-line">' . esc_html( $label ) . '</span>' : '' ) . '<span class="screen-reader-text"> (' . esc_html( $line ) . ')</span></a></li>';
		}
	}
	$out .= '' !== $figs ? '<ul class="kit-profile-figures">' . $figs . '</ul>' : '';
	$out .= kits_profile_follow_html( $f );
	$out .= '' !== $photo && '' !== $f['credit'] ? '<p class="kit-profile-credit">' . esc_html( sprintf( /* translators: %s: who took or allowed the photo. */ __( 'Photo: %s', 'kits-by-wugweb' ), $f['credit'] ) ) . '</p>' : '';
	return $out . '</div></section>';
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

	$out .= $org ? kits_profile_org_card( $org, $f ) : '';
	return $out;
}

/** One mosaic tile: an icon, an arrow, a title and one short line; the whole tile is the link. */
function kits_profile_mosaic_tile( $shade, $icon, $title, $line, $href, $ext = true, $wide = false ) {
	return '<li class="kit-profile-mt is-' . esc_attr( $shade ) . ( $wide ? ' is-wide' : '' ) . '"><a href="' . esc_url( $href ) . '"' . ( $ext ? ' target="_blank" rel="noopener nofollow me"' : '' ) . '><span class="kit-profile-mt-top"><span class="kit-profile-mt-ico" aria-hidden="true"><span class="kit-ico kit-ico--' . esc_attr( $icon ) . '"></span></span><span class="kit-profile-mt-go" aria-hidden="true">' . ( $ext ? '↗' : '→' ) . '</span></span><strong class="kit-profile-mt-title">' . esc_html( $title ) . '</strong><span class="kit-profile-mt-line">' . esc_html( $line ) . '</span>' . ( $ext ? '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'kits-by-wugweb' ) . '</span>' : '' ) . '</a></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every part is escaped above.
}

/**
 * Their own links in one mosaic: their organisation, their own pages and website, help in their city and the related
 * campaign. The pages that wrote about them are in the Sources block, so they are not repeated here (section height rule). Tiles come in two widths and three shades (ink, soft, plain). Printed after the person's text.
 *
 * @param int $id Post ID.
 * @return string
 */
function kits_profile_tail_html( $id ) {
	$f     = kits_profile_fields( $id );
	$org   = $f['org'] && 'publish' === get_post_status( $f['org'] ) ? $f['org'] : 0;
	$first = kits_profile_first( $f );
	$t     = array();

	if ( $org ) {
		$t[] = kits_profile_mosaic_tile( 'ink', 'home', get_the_title( $org ), __( 'Directory listing', 'kits-by-wugweb' ), get_permalink( $org ), false, true );
	}
	$icons = array( 'Instagram' => 'instagram', 'LinkedIn' => 'linkedin', 'Facebook' => 'facebook', 'YouTube' => 'youtube', 'X' => 'x-brand', 'WhatsApp' => 'brand-whatsapp' );
	$n     = 0;
	foreach ( $f['links'] as $name => $url ) {
		$t[] = kits_profile_mosaic_tile( 0 === $n++ % 2 ? 'plain' : 'soft', $icons[ $name ] ?? 'link', $name, __( 'Follow', 'kits-by-wugweb' ), $url );
	}
	$site = $org ? (string) get_post_meta( $org, 'aw_website', true ) : '';
	if ( '' !== $site ) {
		$t[] = kits_profile_mosaic_tile( 'soft', 'link', __( 'Website', 'kits-by-wugweb' ), preg_replace( '#^https?://(www\.)?#', '', untrailingslashit( $site ) ), $site, true, true );
	}
	$help = kits_profile_help_url( $f['place'] );
	if ( '' !== $help ) {
		$city = trim( explode( ',', $f['place'] )[0] );
		/* translators: %s: city. */
		$t[]  = kits_profile_mosaic_tile( 'ink', 'map-pin', sprintf( __( 'Help in %s', 'kits-by-wugweb' ), $city ), __( 'Rescue and care near you', 'kits-by-wugweb' ), $help, false, true );
	}
	if ( $f['campaign'] && 'publish' === get_post_status( $f['campaign'] ) ) {
		$t[] = kits_profile_mosaic_tile( 'soft', 'megaphone', get_the_title( $f['campaign'] ), __( 'Part of this campaign', 'kits-by-wugweb' ), get_permalink( $f['campaign'] ), false, true );
	}
	if ( ! $t ) {
		return '';
	}
	/* translators: %s: first name. */
	$h = '' !== $first ? sprintf( __( '%s’s links', 'kits-by-wugweb' ), $first ) : __( 'Links', 'kits-by-wugweb' );
	return '<section class="kit-profile-mosaic" aria-labelledby="kit-profile-mosaic-h"><h2 id="kit-profile-mosaic-h">' . esc_html( $h ) . '</h2><ul class="kit-profile-mt-grid">' . implode( '', $t ) . '</ul></section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every tile is escaped in kits_profile_mosaic_tile().
}

/**
 * The profile's sources as the list inside the site's Sources block (item-head.php): the page, its date, the link and the
 * archive copy. Newest first.
 *
 * @param int $id Post ID.
 * @return string
 */
function kits_profile_sources_html( $id ) {
	$items = '';
	foreach ( kits_profile_sources( $id ) as $g ) {
		$name = '' !== $g['source'] ? '<strong>' . esc_html( $g['source'] ) . '</strong>' : '';
		$date = '' !== $g['date'] ? ( '' !== $name ? ', ' : '' ) . kits_date( $g['date'] ) : '';
		$read = '' !== $g['url'] ? ' ' . kits_profile_ext_link( $g['url'], __( 'Read it', 'kits-by-wugweb' ) ) : '';
		$arch = '' !== $g['archive'] ? ' ' . kits_profile_ext_link( $g['archive'], __( 'Archive copy', 'kits-by-wugweb' ) ) : '';
		$items .= '<li>' . $name . $date . '.' . $read . $arch . '</li>';
	}
	return '' !== $items ? '<ol>' . $items . '</ol>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every part is escaped above; kits_date() returns escaped markup.
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

// The hero shows the photo, so the item template's own image row is left out on the profile's page (cards in lists are untouched).
add_filter(
	'render_block_core/post-featured-image',
	function ( $html, $block ) {
		$id = (int) ( $block['attrs']['postId'] ?? get_the_ID() );
		return $id && function_exists( 'aw_item_here' ) && aw_item_here( $id ) && kits_is_profile( $id ) ? '' : $html;
	},
	30,
	2
);
