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
		if ( '' === $claim || '' === trim( (string) get_post_meta( $id, $p . 'source', true ) ) ) {
			continue; // a claim without a named source is not shown
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

/**
 * The organisation's public contact for the hero (owner, 28 Nov 2026: phone and email as icons on top instead of the four
 * social icons): the call and email buttons from its Directory listing (the numbers it already publishes, never one on the
 * never-publish list). Their own social pages stay in the links mosaic. Without a contact the social icons show as before.
 *
 * @param array $f   Profile fields.
 * @param int   $org The organisation's listing ID (0 if none).
 * @return string
 */
function kits_profile_follow_html( $f, $org = 0 ) {
	$items = array();
	if ( $org ) {
		$org_name = get_the_title( $org );
		$phones   = function_exists( 'directoryweb_listing_phones' ) ? directoryweb_listing_phones( $org ) : array();
		$digits   = $phones ? preg_replace( '/\D+/', '', (string) $phones[0]['number'] ) : '';
		if ( 10 === strlen( $digits ) ) {
			$digits = '91' . $digits;
		}
		if ( '' !== $digits ) {
			/* translators: %s: organisation name. */
			$items[] = array( 'tel:+' . $digits, 'phone', sprintf( __( 'Call %s', 'kits-by-wugweb' ), $org_name ), false );
		}
		$mail = sanitize_email( (string) get_post_meta( $org, 'aw_email', true ) );
		if ( '' !== $mail ) {
			/* translators: %s: organisation name. */
			$items[] = array( 'mailto:' . $mail, 'mail', sprintf( __( 'Email %s', 'kits-by-wugweb' ), $org_name ), false );
		}
	}
	$site = $org ? esc_url_raw( (string) get_post_meta( $org, 'aw_website', true ) ) : '';
	if ( '' !== $site && $items ) { // the organisation's website goes on top beside the phone and email (owner, 28 Nov 2026)
		/* translators: %s: organisation name. */
		array_unshift( $items, array( $site, 'world', sprintf( __( '%s website (opens in a new tab)', 'kits-by-wugweb' ), get_the_title( $org ) ), true ) );
	}
	$first = kits_profile_first( $f );
	if ( ! $items ) {
		$icons = array( 'Instagram' => 'instagram', 'LinkedIn' => 'linkedin', 'Facebook' => 'facebook', 'YouTube' => 'youtube', 'X' => 'x-brand', 'WhatsApp' => 'brand-whatsapp' );
		foreach ( $f['links'] as $name => $url ) {
			$items[] = array( $url, $icons[ $name ] ?? 'link', $name . ' ' . __( '(opens in a new tab)', 'kits-by-wugweb' ), true );
		}
	}
	if ( ! $items ) {
		return '';
	}
	/* translators: %s: first name. */
	$label = '' !== $first ? sprintf( __( 'Contact %s’s organisation', 'kits-by-wugweb' ), $first ) : __( 'Contact', 'kits-by-wugweb' );
	$out   = '<nav class="kit-profile-follow" aria-label="' . esc_attr( $label ) . '"><ul>';
	foreach ( $items as $it ) {
		$out .= '<li><a href="' . esc_url( $it[0], array( 'http', 'https', 'tel', 'mailto' ) ) . '"' . ( $it[3] ? ' target="_blank" rel="noopener nofollow me"' : '' ) . '><span class="kit-ico kit-ico--' . esc_attr( $it[1] ) . '" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html( $it[2] ) . '</span></a></li>';
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
	$out     .= '' !== $f['role'] ? '<span class="kit-profile-role">' . esc_html( $f['role'] ) . '</span>' : '';
	$out     .= '' !== $f['place'] ? '<span class="kit-profile-place"><span class="kit-ico kit-ico--map-pin" aria-hidden="true"></span>' . esc_html( $f['place'] ) . '</span>' : '';
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
	$out .= kits_profile_follow_html( $f, (int) $f['org'] );
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
	$h   = '' !== $first ? sprintf( __( 'Where %s works', 'kits-by-wugweb' ), $first ) : __( 'Where they work', 'kits-by-wugweb' );
	// The theme's own organisation card (the same markup as a listing in "More", includes/item-head.php): logo whole in the picture box, meta row, linked title.
	$logo  = get_the_post_thumbnail( $org, 'medium', array( 'alt' => '', 'loading' => 'lazy' ) );
	$mark  = '' !== $logo ? $logo : '<span class="kit-minicard-initials aw-more-initials" aria-hidden="true">' . esc_html( kits_profile_initials( $name ) ) . '</span>';
	$place = function_exists( 'aw_item_place' ) ? aw_item_place( $org ) : null;
	$meta  = ( preg_match( '/^\d{4}$/', $year ) ? '<span class="kit-meta-item">' . kits_icon( 'calendar' ) . esc_html( sprintf( /* translators: %s: year. */ __( 'Since %s', 'kits-by-wugweb' ), $year ) ) . '</span>' : '' )
		. ( $place ? '<span class="kit-meta-item">' . kits_icon( 'map-pin' ) . esc_html( $place->name ) . '</span>' : '' )
		. ( '' !== $animals ? '<span class="kit-meta-item">' . esc_html( ucfirst( $animals ) ) . '</span>' : '' );
	$out   = '<section class="kit-profile-org" aria-labelledby="kit-profile-org-h"><h2 id="kit-profile-org-h">' . esc_html( $h ) . '</h2>';
	$out  .= '<article class="kit-card kit-card--stacked-md kit-minicard aw-more-card aw-more-card--org aw-card-profile"><div class="kit-card-media kit-minicard-media kit-minicard-logo aw-more-media aw-more-logo">' . $mark . '</div><div class="kit-card-body kit-minicard-body aw-more-body">'
		. ( '' !== $meta ? '<p class="kit-card-metarow kit-card-meta kit-minicard-meta aw-more-meta kit-meta-row">' . $meta . '</p>' : '' )
		. '<h3 class="kit-card-title kit-minicard-title aw-more-title"><a href="' . esc_url( get_permalink( $org ) ) . '">' . esc_html( $name ) . '</a></h3>'
		. '</div></article></section>';
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

	// No organisation card here (owner, 28 Nov 2026): the organisation is linked by the tile in the links grid below.
	return $out;
}

/** One mosaic tile: an icon, an arrow, a title and one short line; the whole tile is the link. */
function kits_profile_mosaic_tile( $shade, $icon, $title, $line, $href, $ext = true, $wide = false, $img = '' ) {
	return '<li class="kit-profile-mt is-' . esc_attr( $shade ) . ( $wide ? ' is-wide' : '' ) . '"' . ( '' !== $img ? ' style="--kit-pmt-img:url(\'' . esc_url( $img ) . '\')"' : '' ) . '><a href="' . esc_url( $href ) . '"' . ( $ext ? ' target="_blank" rel="noopener nofollow me"' : '' ) . '><span class="kit-profile-mt-top"><span class="kit-profile-mt-ico" aria-hidden="true"><span class="kit-ico kit-ico--' . esc_attr( $icon ) . '"></span></span><span class="kit-profile-mt-go" aria-hidden="true">' . '↗' . '</span></span><strong class="kit-profile-mt-title">' . esc_html( $title ) . '</strong><span class="kit-profile-mt-line">' . esc_html( $line ) . '</span>' . ( $ext ? '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'kits-by-wugweb' ) . '</span>' : '' ) . '</a></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every part is escaped above.
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
	$help = kits_profile_help_url( $f['place'] );
	if ( '' !== $help ) {
		$city = trim( explode( ',', $f['place'] )[0] );
		/* translators: %s: city. */
		$t[]  = kits_profile_mosaic_tile( 'ink', 'map-pin', sprintf( __( 'Help in %s', 'kits-by-wugweb' ), $city ), __( 'Rescue and care near you', 'kits-by-wugweb' ), $help, false, true );
	}
	if ( $f['campaign'] && 'publish' === get_post_status( $f['campaign'] ) ) {
		$pic = (string) get_the_post_thumbnail_url( $f['campaign'], 'large' ); // the campaign's own picture under a dark shade (owner, 28 Nov 2026), the soft tile when it has none.
		$t[] = kits_profile_mosaic_tile( '' !== $pic ? 'photo' : 'soft', 'megaphone', get_the_title( $f['campaign'] ), __( 'Part of this campaign', 'kits-by-wugweb' ), get_permalink( $f['campaign'] ), false, true, $pic );
	}
	if ( ! $t && ! $f['links'] ) {
		return '';
	}
	/* translators: %s: first name. */
	$h = '' !== $first ? sprintf( __( 'More on %s’s work', 'kits-by-wugweb' ), $first ) : __( 'More on their work', 'kits-by-wugweb' );
	$icons  = array( 'Instagram' => 'instagram', 'LinkedIn' => 'linkedin', 'Facebook' => 'facebook', 'YouTube' => 'youtube', 'X' => 'x-brand', 'WhatsApp' => 'brand-whatsapp' );
	$social = '';
	foreach ( $f['links'] as $name => $url ) { // their own pages: small tiles in one row (owner, 28 Nov 2026: the old layout, smaller, side by side)
		$social .= '<li><a class="kit-profile-st" href="' . esc_url( $url ) . '" target="_blank" rel="noopener nofollow me"><span class="kit-profile-st-top"><span class="kit-profile-st-ico" aria-hidden="true"><span class="kit-ico kit-ico--' . esc_attr( $icons[ $name ] ?? 'link' ) . '"></span></span><span class="kit-profile-st-go" aria-hidden="true">↗</span></span><strong>' . esc_html( $name ) . '</strong><span class="screen-reader-text"> ' . esc_html__( 'Follow', 'kits-by-wugweb' ) . ' · ' . esc_html__( '(opens in a new tab)', 'kits-by-wugweb' ) . '</span></a></li>';
	}
	return '<section class="kit-profile-mosaic" aria-labelledby="kit-profile-mosaic-h"><h2 id="kit-profile-mosaic-h">' . esc_html( $h ) . '</h2><ul class="kit-profile-mt-grid">' . implode( '', $t ) . $social . '</ul></section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every tile is escaped above.
}

/**
 * An outlet's mark for a source row. In order: the site's publisher icon (the News publishers list, which also matches a
 * sister site of the same name, ndtv.in for ndtv.com), the Press list's cached logo, the organisation's own logo for its
 * own site, else the first letter of the name in a small ink tile. Nothing is fetched while the page is drawn.
 *
 * @param string $url  The source's address.
 * @param string $name The source's name.
 * @param int    $org  The person's organisation listing (0 if none).
 * @return string
 */
function kits_profile_source_mark( $url, $name, $org = 0 ) {
	$host = preg_replace( '/^www\./', '', (string) wp_parse_url( $url, PHP_URL_HOST ) );
	$img  = static function ( $aid ) {
		return $aid && wp_attachment_is_image( $aid ) ? wp_get_attachment_image( $aid, 'thumbnail', false, array( 'class' => 'aw-sources-logo', 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async' ) ) : '';
	};
	if ( '' !== $host && function_exists( 'aw_publishers' ) ) {
		$base = strtok( $host, '.' ) === 'm' ? '' : preg_replace( '/\.(co\.in|com|in|org|net|gov\.in)$/', '', $host );
		foreach ( (array) aw_publishers() as $h => $pub ) {
			if ( ! empty( $pub['icon'] ) && ( $h === $host || ( '' !== $base && preg_replace( '/\.(co\.in|com|in|org|net|gov\.in)$/', '', $h ) === $base ) ) ) {
				$out = $img( (int) $pub['icon'] );
				if ( '' !== $out ) {
					return $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core markup for an attachment image.
				}
			}
		}
	}
	$cache = (array) get_option( 'aw_press_logos', array() );
	$out   = $img( isset( $cache[ $host ] ) ? (int) $cache[ $host ] : 0 );
	if ( '' === $out && $org && '' !== $name && false !== stripos( $name, (string) get_the_title( $org ) ) ) {
		$out = $img( (int) get_post_thumbnail_id( $org ) );
	}
	if ( '' !== $out ) {
		return $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core markup for an attachment image.
	}
	$first = '' !== $name ? mb_strtoupper( mb_substr( $name, 0, 1 ) ) : '';
	return '' !== $first ? '<span class="aw-sources-mark" aria-hidden="true">' . esc_html( $first ) . '</span>' : '';
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
	$org   = (int) kits_profile_fields( $id )['org'];
	foreach ( kits_profile_sources( $id ) as $g ) {
		$name = '' !== $g['source'] ? '<strong>' . esc_html( $g['source'] ) . '</strong>' : '';
		$date = '' !== $g['date'] ? ( '' !== $name ? ', ' : '' ) . kits_date( $g['date'] ) : '';
		$read = '' !== $g['url'] ? ' ' . kits_profile_ext_link( $g['url'], __( 'Read it', 'kits-by-wugweb' ) ) : '';
		$arch = '' !== $g['archive'] ? ' ' . kits_profile_ext_link( $g['archive'], __( 'Archive copy', 'kits-by-wugweb' ) ) : '';
		$items .= '<li>' . kits_profile_source_mark( $g['url'], $g['source'], $org ) . $name . $date . '.' . $read . $arch . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the mark is escaped in kits_profile_source_mark().
	}
	return '' !== $items ? '<ol>' . $items . '</ol>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every part is escaped above; kits_date() returns escaped markup.
}

/** The approval line for the Sources block: who checked it, or that it rests on public reporting. */
function kits_profile_approval_line( $id ) {
	$f    = kits_profile_fields( $id );
	$when = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $f['approved_on'] ) ? kits_date( $f['approved_on'] ) : '';
	if ( 'approved' === $f['approval'] ) {
		if ( '' === $when ) {
			return '<span>' . esc_html__( 'Checked with them.', 'kits-by-wugweb' ) . '</span>';
		}
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

/* ---------- Where a profile lives: under About, not in Resources (owner, 28 Nov 2026) ---------- */

// A published profile is at /about/{slug}/. The post is still a Resource (a Post tagged "profile"); only its address and trail change.
// Drafts keep WordPress's ?p= preview address.
add_filter(
	'post_link',
	function ( $url, $post ) {
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || '' === $post->post_name || ! kits_is_profile( $post->ID ) ) {
			return $url;
		}
		return home_url( '/about/' . $post->post_name . '/' );
	},
	10,
	2
);

// /about/{slug}/ has its own rewrite rule (like /resources/{slug}/), ahead of the page rule. A real page under About always wins:
// when no published profile has the slug, the path goes on as a page path (and is a 404 if there is none).
add_action(
	'init',
	function () {
		add_rewrite_rule( '^about/([^/]+)/?$', 'index.php?kits_about_slug=$matches[1]', 'top' );
		if ( '2' !== get_option( 'kits_profile_rules' ) ) { // the new rule is saved once
			flush_rewrite_rules( false );
			update_option( 'kits_profile_rules', '2', false );
		}
	},
	20
);

add_filter(
	'query_vars',
	function ( $vars ) {
		$vars[] = 'kits_about_slug';
		return $vars;
	}
);

add_filter(
	'request',
	function ( $vars ) {
		if ( empty( $vars['kits_about_slug'] ) ) {
			return $vars;
		}
		$slug  = sanitize_title( (string) $vars['kits_about_slug'] );
		$page  = get_page_by_path( 'about/' . $slug ); // an existing page under About keeps its address when a profile has the same slug (Codex review point, PR 180)
		if ( $page && 'publish' === $page->post_status ) {
			unset( $vars['kits_about_slug'] );
			return array( 'pagename' => 'about/' . $slug );
		}
		$found = get_posts(
			array(
				'name'           => $slug,
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'tag'            => 'profile',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		unset( $vars['kits_about_slug'] );
		if ( $found ) {
			return array(
				'name'      => $slug,
				'post_type' => 'post',
			);
		}
		return array( 'pagename' => 'about/' . $slug ); // a page under About, or a 404
	}
);

// The old address (/resources/{slug}/) goes to the new one once the profile is published.
add_action(
	'template_redirect',
	function () {
		if ( ! is_singular( 'post' ) || is_preview() || ! kits_is_profile( get_queried_object_id() ) || 'publish' !== get_post_status( get_queried_object_id() ) ) {
			return;
		}
		$to = get_permalink( get_queried_object_id() );
		if ( $to && 0 === strpos( (string) wp_parse_url( $to, PHP_URL_PATH ), '/about/' ) && 0 !== strpos( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/about/' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- only its path is compared
			wp_safe_redirect( $to, 301 );
			exit;
		}
	}
);

// The trail reads Home > About > the person, not Home > Resources.
add_filter(
	'rank_math/frontend/breadcrumb/items',
	function ( $crumbs ) {
		if ( is_admin() || ! is_singular( 'post' ) || ! kits_is_profile( get_queried_object_id() ) ) {
			return $crumbs;
		}
		$about = get_page_by_path( 'about' );
		if ( ! $about || 'publish' !== $about->post_status ) {
			return $crumbs;
		}
		$id = get_queried_object_id();
		return array(
			array( 'Home', home_url( '/' ) ),
			array( get_the_title( $about ), get_permalink( $about ) ),
			array( apply_filters( 'aw_breadcrumb_label', get_the_title( $id ), $id ), get_permalink( $id ) ),
		);
	},
	30
);



/* ---------- Home and About sections ---------- */

/**
 * Is any profile published? The Home row and the About section show only then; until then the site is as it was
 * (the four law tiles stay in "The law, in plain words").
 *
 * @return bool
 */
function kits_profiles_exist() {
	static $has = null;
	if ( null === $has ) {
		$has = (bool) get_posts(
			array(
				'post_type'        => 'post',
				'post_status'      => 'publish',
				'tag'              => 'profile',
				'numberposts'      => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
			)
		);
	}
	return $has;
}

// The profile query blocks (queryId 45 and 46, namespace animal-writes/profiles) list Resources tagged "profile", newest first. The tag is
// found by its slug, so no tag ID is written into a pattern.
add_filter(
	'query_loop_block_query_vars',
	function ( $query, $block ) {
		// The filter gets the inner post-template block, which carries the query's context (queryId) but not its namespace: the Home row is query 45 and the About band 46.
		if ( in_array( (int) ( $block->context['queryId'] ?? 0 ), array( 45, 46 ), true ) ) {
			$query['post_type'] = 'post';
			$query['tag']       = 'profile';
		}
		return $query;
	},
	10,
	2
);

/**
 * The newest published profile's ID (0 when there is none), for the Home bento.
 *
 * @return int
 */
function kits_profile_newest_id() {
	static $id = null;
	if ( null === $id ) {
		$found = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'tag' => 'profile', 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => true ) ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters
		$id    = $found ? (int) $found[0] : 0;
	}
	return $id;
}

/**
 * The Home bento (owner, 28 Nov 2026): one block. Left, the newest profile as a grey tile, empty where her photo will go (name, role,
 * place, one button); right, the four law tiles 2 by 2 in three shades, with no heading of their own. Each tile is one link.
 *
 * @return string HTML ('' when no profile is published).
 */
function kits_people_bento_html() {
	$id = kits_profile_newest_id();
	if ( ! $id ) {
		return '';
	}
	$f     = kits_profile_fields( $id );
	$name  = trim( $f['prefix'] . ' ' . $f['name'] );
	$first = kits_profile_first( $f );
	/* translators: %s: first name. */
	$cta = '' !== $first ? sprintf( __( 'Read %s’s story', 'kits-by-wugweb' ), $first ) : __( 'Read the story', 'kits-by-wugweb' );
	$out = '<section class="kit-people-bento" aria-labelledby="kit-pb-h"><h2 id="kit-pb-h" class="screen-reader-text">' . esc_html__( 'People who make a difference, and the law', 'kits-by-wugweb' ) . '</h2>';
	$out .= '<a class="kit-pb kit-pb--photo" href="' . esc_url( get_permalink( $id ) ) . '">'; // The photo comes later (owner, 28 Nov 2026): an empty grey box until then.
	$out .= '<span class="kit-pb-label">' . esc_html__( 'People who make a difference', 'kits-by-wugweb' ) . '</span><span class="kit-pb-body"><strong class="kit-pb-name">' . esc_html( $name ) . '</strong>';
	$out .= '' !== $f['role'] ? '<span class="kit-pb-role">' . esc_html( $f['role'] ) . '</span>' : '';
	$out .= '' !== $f['place'] ? '<span class="kit-profile-place"><span class="kit-ico kit-ico--map-pin" aria-hidden="true"></span>' . esc_html( $f['place'] ) . '</span>' : '';
	$out .= '<span class="kit-pb-cta">' . esc_html( $cta ) . ' <span class="kit-ico kit-ico--arrow-right" aria-hidden="true"></span></span></span></a>';
	$tiles = array(
		array( 'ink', 'legal', '/library/?tag=act,rule', __( 'Acts and Rules', 'kits-by-wugweb' ), __( 'The law in full, from the official text.', 'kits-by-wugweb' ) ),
		array( 'white', 'research', '/library/?tag=judgment', __( 'Court orders and judgments', 'kits-by-wugweb' ), __( 'What the courts have ordered, summarised.', 'kits-by-wugweb' ) ),
		array( 'white', 'file', '/library/?tag=circular', __( 'Circulars and notices', 'kits-by-wugweb' ), __( 'From the Animal Welfare Board and government departments.', 'kits-by-wugweb' ) ),
		array( 'grey', 'mail', '/library/?tag=template', __( 'Letters and templates', 'kits-by-wugweb' ), __( 'Letters you can send today.', 'kits-by-wugweb' ) ),
	);
	$out .= '<div class="kit-pb-tiles">';
	foreach ( $tiles as $t ) {
		$out .= '<a class="kit-pb kit-pb--' . esc_attr( $t[0] ) . '" href="' . esc_url( home_url( $t[2] ) ) . '"><span class="kit-ico kit-ico--' . esc_attr( $t[1] ) . '" aria-hidden="true"></span><span class="kit-ico kit-ico--arrow-right" aria-hidden="true"></span><span class="kit-pb-body"><strong>' . esc_html( $t[3] ) . '</strong><span>' . esc_html( $t[4] ) . '</span></span></a>';
	}
	return $out . '</div></section>';
}

/**
 * The profile section: heading, one line, the newest profile as a card, and a link to all profiles.
 *
 * @param string $heading  Section heading.
 * @param string $lead     One line under it.
 * @param int    $per_page Cards shown (1 on Home, up to 3 on About).
 * @param int    $qid      Query ID (unique per pattern).
 * @return string Block markup.
 */
function kits_people_profile_block( $heading, $lead, $per_page, $qid ) {
	if ( ! function_exists( 'aw_card_item' ) ) {
		return '';
	}
	return '<!-- wp:group {"className":"kit-people-profile","layout":{"type":"default"}} -->
<div class="wp-block-group kit-people-profile"><!-- wp:group {"className":"kit-sec-head","layout":{"type":"default"}} -->
<div class="wp-block-group kit-sec-head"><!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:heading -->
<h2 class="wp-block-heading">' . esc_html( $heading ) . '</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>' . esc_html( $lead ) . '</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:query {"queryId":' . (int) $qid . ',"query":{"perPage":' . (int) $per_page . ',"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":false},"namespace":"animal-writes/profiles","className":"aw-hub-list kit-people-list","layout":{"type":"default"}} -->
<div class="wp-block-query aw-hub-list kit-people-list"><!-- wp:post-template {"className":"aw-tasks","layout":{"type":"default"}} -->
' . aw_card_item( 'post', 'stacked-lg', 3 ) . '
<!-- /wp:post-template --></div>
<!-- /wp:query -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-kits-ghost kit-btn--sm"} -->
<div class="wp-block-button is-style-kits-ghost kit-btn--sm"><a class="wp-block-button__link wp-element-button" href="/resources/?tag=profile">' . esc_html__( 'See all profiles', 'kits-by-wugweb' ) . '</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->';
}

/**
 * The About band (owner, 28 Nov 2026): a heading, then four profile slots. Published profiles come first (newest first);
 * the rest are grey placeholders until there are more people. Phones show only the first; two across from 768; four from 1200.
 *
 * @param string $heading Section heading.
 * @param string $lead    One line under it.
 * @return string Block markup ('' when no profile is published).
 */
function kits_people_about_block( $heading, $lead ) {
	$ids = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'tag' => 'profile', 'posts_per_page' => 4, 'fields' => 'ids', 'no_found_rows' => true ) ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters
	if ( ! $ids ) {
		return '';
	}
	if ( function_exists( 'aw_use_component_style' ) ) {
		aw_use_component_style( 'aw-home' ); // The band can sit on any page.
	}
	$li = '';
	foreach ( $ids as $id ) {
		$f     = kits_profile_fields( $id );
		$name  = trim( $f['prefix'] . ' ' . $f['name'] );
		$thumb = has_post_thumbnail( $id ) ? get_the_post_thumbnail( $id, 'large', array( 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async' ) ) : '';
		$li   .= '<li class="kit-pa-item"><a class="kit-pa-card" href="' . esc_url( get_permalink( $id ) ) . '"><span class="kit-pa-media">' . $thumb . '</span><span class="kit-pa-body"><strong>' . esc_html( $name ) . '</strong>';
		$li   .= '' !== $f['role'] ? '<span>' . esc_html( $f['role'] ) . '</span>' : '';
		$li   .= '' !== $f['place'] ? '<span class="kit-profile-place"><span class="kit-ico kit-ico--map-pin" aria-hidden="true"></span>' . esc_html( $f['place'] ) . '</span>' : '';
		$first = kits_profile_first( $f );
		/* translators: %s: first name. */
		$cta   = '' !== $first ? sprintf( __( 'Read %s’s story', 'kits-by-wugweb' ), $first ) : __( 'Read the story', 'kits-by-wugweb' );
		$li   .= '<span class="kit-pa-cta">' . esc_html( $cta ) . ' <span class="kit-ico kit-ico--arrow-right" aria-hidden="true"></span></span>';
		$li   .= '</span></a></li>';
	}
	for ( $i = count( $ids ); $i < 4; $i++ ) { // an empty slot: a grey picture box and two grey lines, not a link, hidden on phones
		$li .= '<li class="kit-pa-item kit-pa-item--empty" aria-hidden="true"><span class="kit-pa-card"><span class="kit-pa-media"></span><span class="kit-pa-body"><span class="kit-pa-bar"></span><span class="kit-pa-bar kit-pa-bar--short"></span><span class="kit-pa-bar kit-pa-bar--cta"></span></span></span></li>';
	}
	$html = '<section class="kit-pa" aria-labelledby="kit-pa-h"><div class="kit-sec-head"><div><h2 id="kit-pa-h">' . esc_html( $heading ) . '</h2><p>' . esc_html( $lead ) . '</p></div></div><ul class="kit-pa-grid">' . $li . '</ul>'
		. '</section>';
	return '<!-- wp:html -->' . "\n" . $html . "\n" . '<!-- /wp:html -->';
}
