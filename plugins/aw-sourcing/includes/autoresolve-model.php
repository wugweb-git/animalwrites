<?php
/**
 * Review-queue rules (owner, 13 and 14 Nov 2026: "you don't have to put me on review"): what to do with a waiting value
 * for a field that already holds a value. No WordPress calls, so tests/autoresolve-tests.php runs it on its own.
 *
 * aw_ar_decide() returns approve (write it), reject (nothing new, or another place or page) or leave (a real
 * difference: the owner decides). A field with no value yet follows the plugin's own rules, not these.
 *
 * - Address: adds nothing (same words, spelling variants, "No." prefixes): reject. Holds everything now there and more:
 *   approve. A different PIN is another place: reject. A low-tier source that differs: keep the current address.
 * - PIN code: the same digits: reject.
 * - Phone: an extra number, never a replacement: approve.
 * - Facebook, Instagram, X, LinkedIn, YouTube: a post, photo or video link is not the page: reject. The same page with a
 *   suffix, query string or www: reject. A current link with no page name: approve. A low-tier different link: reject.
 * - Website, donate link, adopt link: the same page or site: reject. A donate link that names the donation page where the
 *   current one does not: approve; the home page is not a donate link: reject.
 * - Email: the same address: reject. A near-identical one is a typo fix only when the proposal carries the organisation's
 *   own website name and the current one does not: approve; any other near-identical one: reject.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Words an address match ignores. */
const AW_AR_STOP = array( 'india', 'post', 'po', 'p', 'o', 'dist', 'district', 'tal', 'taluka', 'taluk', 'near', 'opp', 'opposite', 'behind', 'the', 'of', 'and', 'at', 'pin', 'pincode', 'state', 'no', 'h', 'hno', 'house', 'plot', 'flat' );

/** Mail domains a typo may be fixed to. */
const AW_AR_MAIL_DOMAINS = array( 'gmail.com', 'yahoo.com', 'yahoo.co.in', 'yahoo.in', 'hotmail.com', 'outlook.com', 'rediffmail.com', 'live.com', 'icloud.com' );

/** First path words that are not a page name on a social site. */
const AW_AR_GENERIC = array( 'people', 'pages', 'pg', 'profile.php', 'groups', 'public', 'p', 'in', 'company', 'user', 'channel', 'c', 'id', 'sharer', 'share.php', 'login', 'home', 'watch' );

/**
 * The 6-digit PIN code in a text, or ''.
 *
 * @param string $s Text.
 * @return string
 */
function aw_ar_pin( $s ) {
	return preg_match( '/\b([1-9]\d{2})\s?(\d{3})\b/', (string) $s, $m ) ? $m[1] . $m[2] : '';
}

/**
 * The words of an address (lower case, PIN and stop words removed, each once).
 *
 * @param string $s Address.
 * @return string[]
 */
function aw_ar_tokens( $s ) {
	$s   = strtolower( html_entity_decode( strip_tags( (string) $s ) ) );
	$s   = preg_replace( '/\b\d{3}\s?\d{3}\b/', ' ', $s );
	$s   = preg_replace( '/[^a-z0-9\/ ]+/', ' ', $s );
	$out = array();
	foreach ( preg_split( '/\s+/', $s ) as $w ) {
		if ( '' !== $w && ! in_array( $w, AW_AR_STOP, true ) && ! in_array( $w, $out, true ) ) {
			$out[] = $w;
		}
	}
	return $out;
}

/**
 * Whether a word is in a set, spelling variants included (84% alike, four letters or more).
 *
 * @param string   $w   Word.
 * @param string[] $set Words.
 * @return bool
 */
function aw_ar_in( $w, array $set ) {
	foreach ( $set as $s ) {
		if ( $w === $s ) {
			return true;
		}
		if ( strlen( $w ) >= 4 && strlen( $s ) >= 4 ) {
			similar_text( $w, $s, $pct );
			if ( $pct >= 84 ) {
				return true;
			}
		}
	}
	return false;
}

/**
 * A link without scheme, www, query string, fragment and closing slash, and without a trailing /about, /posts etc.
 *
 * @param string $u Link.
 * @return string
 */
function aw_ar_url( $u ) {
	$u = strtolower( trim( (string) $u ) );
	$u = preg_replace( '#^https?://(www\.|m\.|mobile\.|web\.)?#', '', $u );
	$u = preg_replace( '/[?#].*$/', '', $u );
	$u = rtrim( $u, '/' );
	return (string) preg_replace( '#/(about|about_us|info|posts|photos|videos|home|featured|community|reviews|contact|contact_us|reels|events)$#', '', $u );
}

/**
 * The path of a link, '' for the home page.
 *
 * @param string $u Link.
 * @return string
 */
function aw_ar_path( $u ) {
	return trim( (string) preg_replace( '#^(https?://)?[^/]+#i', '', (string) $u ), '/' );
}

/**
 * One decision.
 *
 * @param string $field Field key (address, pincode, phone, facebook, instagram, x, linkedin, youtube, website,
 *                      donate_url, adopt_url, email).
 * @param string $now   What the listing holds.
 * @param string $value The waiting value.
 * @param string $conf  high | medium | low.
 * @param array  $ctx   own_site (the value was read on the organisation's own website), site_stem (the first word of
 *                      its website's name, e.g. wildcer), site_host (its host without www, e.g. wildcer.org).
 * @return array{act: string, why: string} act is approve, reject or leave.
 */
function aw_ar_decide( $field, $now, $value, $conf = '', array $ctx = array() ) {
	$now   = trim( (string) $now );
	$value = trim( (string) $value );
	$out   = function ( $act, $why ) {
		return array( 'act' => $act, 'why' => $why );
	};
	if ( 'phone' === $field ) {
		return '' !== $value ? $out( 'approve', 'an extra phone number' ) : $out( 'leave', 'empty' );
	}
	if ( '' === $now || '' === $value ) {
		return $out( 'leave', 'nothing to compare' ); // an empty field follows the plugin's own rules
	}
	switch ( $field ) {
		case 'address':
			$pt = aw_ar_tokens( $value );
			$nt = aw_ar_tokens( $now );
			if ( ! $pt ) {
				return $out( 'reject', 'unusable' );
			}
			$pp = aw_ar_pin( $value );
			$np = aw_ar_pin( $now );
			if ( '' !== $pp && '' !== $np && $pp !== $np ) {
				return $out( 'reject', 'another place (the PIN differs)' ); // before "fuller": a longer address with another PIN is not this place
			}
			if ( ! array_filter( $pt, fn( $w ) => ! aw_ar_in( $w, $nt ) ) ) {
				return $out( 'reject', 'adds nothing' );
			}
			if ( $nt && ! array_filter( $nt, fn( $w ) => ! aw_ar_in( $w, $pt ) ) ) {
				return $out( 'approve', 'fuller address' );
			}
			return 'low' === $conf ? $out( 'reject', 'differs, low-tier source: keep the current address' ) : $out( 'leave', 'a real difference' );
		case 'pincode':
			return preg_replace( '/\D/', '', $value ) === preg_replace( '/\D/', '', $now ) ? $out( 'reject', 'the same PIN' ) : $out( 'leave', 'a different PIN' );
		case 'facebook':
		case 'instagram':
		case 'x':
		case 'linkedin':
		case 'youtube':
			$a = aw_ar_url( $value );
			$b = aw_ar_url( $now );
			if ( preg_match( '#/(posts|photos|videos|permalink|story\.php|share|reels?|watch|events|hashtag)(/|\?|$)#', strtolower( $value ) ) ) {
				return $out( 'reject', 'a post or content link, not the page' );
			}
			if ( $a === $b ) {
				return $out( 'reject', 'the same link' );
			}
			$bp = explode( '/', $b );
			$ap = explode( '/', $a );
			if ( count( $bp ) <= 1 || ( count( $bp ) <= 2 && in_array( $bp[1] ?? '', AW_AR_GENERIC, true ) ) ) {
				return $out( 'approve', 'the current link has no page name' );
			}
			$bh = preg_replace( '/[._\-]/', '', $bp[1] ?? '' );
			if ( '' !== $bh && $bh === preg_replace( '/[._\-]/', '', $ap[1] ?? "\0" ) ) {
				return $out( 'reject', 'the same handle' );
			}
			return 'low' === $conf ? $out( 'reject', 'a different link, low-tier source: keep the current one' ) : $out( 'leave', 'a different link' );
		case 'website':
		case 'donate_url':
		case 'adopt_url':
			$a = aw_ar_url( $value );
			$b = aw_ar_url( $now );
			if ( $a === $b ) {
				return $out( 'reject', 'the same link' );
			}
			if ( 'website' === $field ) {
				return preg_replace( '#/.*$#', '', $a ) === preg_replace( '#/.*$#', '', $b ) ? $out( 'reject', 'the same site' ) : $out( 'leave', 'a different site' );
			}
			if ( '' === aw_ar_path( $value ) ) {
				return $out( 'reject', 'the home page, not a ' . ( 'donate_url' === $field ? 'donate' : 'adopt' ) . ' page' );
			}
			if ( 'donate_url' === $field && false !== stripos( aw_ar_path( $value ), 'donat' ) && false === stripos( aw_ar_path( $now ), 'donat' ) ) {
				return $out( 'approve', 'names the donation page' );
			}
			return $out( 'reject', 'the current link is as good: keep it' );
		case 'email':
			$a = strtolower( $value );
			$b = strtolower( $now );
			if ( $a === $b ) {
				return $out( 'reject', 'the same email' );
			}
			$al = explode( '@', $a, 2 );
			$bl = explode( '@', $b, 2 );
			if ( 2 === count( $al ) && 2 === count( $bl ) ) {
				if ( $al[1] === $bl[1] && strlen( $al[0] ) >= 4 && levenshtein( $al[0], $bl[0] ) <= 2 ) {
					$stem = strtolower( (string) ( $ctx['site_stem'] ?? '' ) );
					if ( ! empty( $ctx['own_site'] ) && strlen( $stem ) >= 4 && false !== strpos( $al[0], $stem ) && false === strpos( $bl[0], $stem ) ) {
						return $out( 'approve', 'a typo fixed from the organisation\'s own website name' );
					}
					return $out( 'reject', 'a near-identical address that is not a clear fix: keep the current one' );
				}
				if ( $al[0] === $bl[0] && levenshtein( $al[1], $bl[1] ) <= 2 ) {
					$known = array_merge( AW_AR_MAIL_DOMAINS, array( strtolower( (string) ( $ctx['site_host'] ?? '' ) ) ) );
					return in_array( $al[1], $known, true ) ? $out( 'approve', 'a typo in the domain' ) : $out( 'leave', 'a different domain' );
				}
			}
			return $out( 'leave', 'a different email' );
	}
	return $out( 'leave', 'no rule for this field' );
}
