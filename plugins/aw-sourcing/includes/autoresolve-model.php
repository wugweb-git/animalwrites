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

/** The fields the rules cover (the five social fields are one group, "social"). */
const AW_AR_FIELDS = array(
	'address'    => 'Address',
	'pincode'    => 'PIN code',
	'phone'      => 'Phone',
	'social'     => 'Facebook, Instagram, X, LinkedIn, YouTube',
	'website'    => 'Website',
	'donate_url' => 'Donate link',
	'adopt_url'  => 'Adopt link',
	'email'      => 'Email',
);

/** The five fields of the "social" group. */
const AW_AR_SOCIAL = array( 'facebook', 'instagram', 'x', 'linkedin', 'youtube' );

/** What a rule can do with a waiting value. */
const AW_AR_ACTIONS = array(
	'approve' => 'Apply it',
	'reject'  => 'Reject it and keep the current value',
	'leave'   => 'Leave it for a person',
);

/**
 * The conditions a rule can use: key => label and the fields it makes sense for (the order here is the order the form
 * lists them in).
 *
 * @return array<string, array{label: string, fields: string[]}>
 */
function aw_ar_conditions() {
	$social = array( 'social' );
	return array(
		'any'            => array( 'label' => 'always (anything that reaches this rule)', 'fields' => array_keys( AW_AR_FIELDS ) ),
		'low_tier'       => array( 'label' => 'the source is low tier', 'fields' => array_keys( AW_AR_FIELDS ) ),
		'unusable'       => array( 'label' => 'nothing readable is proposed', 'fields' => array( 'address' ) ),
		'pin_differs'    => array( 'label' => 'its PIN differs from the current one (another place)', 'fields' => array( 'address' ) ),
		'adds_nothing'   => array( 'label' => 'it adds nothing (same words, spelling variants, "No." prefixes)', 'fields' => array( 'address' ) ),
		'fuller'         => array( 'label' => 'it holds everything now there, and more', 'fields' => array( 'address' ) ),
		'same_value'     => array( 'label' => 'it is the same value (case and spaces aside)', 'fields' => array( 'pincode', 'email' ) ),
		'same_link'      => array( 'label' => 'it is the same page (suffix, query string or www aside)', 'fields' => array_merge( $social, array( 'website', 'donate_url', 'adopt_url' ) ) ),
		'content_link'   => array( 'label' => 'it is a post, photo or video link, not the page', 'fields' => $social ),
		'bare_link'      => array( 'label' => 'the current link has no page name (facebook.com/people)', 'fields' => $social ),
		'same_handle'    => array( 'label' => 'it is the same handle (dots and underscores aside)', 'fields' => $social ),
		'same_site'      => array( 'label' => 'it is on the same site', 'fields' => array( 'website' ) ),
		'home_page'      => array( 'label' => 'it is only the home page', 'fields' => array( 'donate_url', 'adopt_url' ) ),
		'names_donation' => array( 'label' => 'it names the donation page and the current link does not', 'fields' => array( 'donate_url' ) ),
		'typo_own_site'  => array( 'label' => 'it fixes a typo and carries the organisation\'s own website name', 'fields' => array( 'email' ) ),
		'near_duplicate' => array( 'label' => 'it is almost the same address (one or two letters off)', 'fields' => array( 'email' ) ),
		'domain_typo'    => array( 'label' => 'it fixes a typo in the domain (a known mail domain or the organisation\'s own)', 'fields' => array( 'email' ) ),
	);
}

/**
 * The built-in rules, in the order they are tried (the order within a field matters).
 *
 * @return array[] each array{id, field, cond, action, on}
 */
function aw_ar_default_rules() {
	$rows = array(
		array( 'address', 'unusable', 'reject' ),
		array( 'address', 'pin_differs', 'reject' ),
		array( 'address', 'adds_nothing', 'reject' ),
		array( 'address', 'fuller', 'approve' ),
		array( 'address', 'low_tier', 'reject' ),
		array( 'pincode', 'same_value', 'reject' ),
		array( 'phone', 'any', 'approve' ),
		array( 'social', 'content_link', 'reject' ),
		array( 'social', 'same_link', 'reject' ),
		array( 'social', 'bare_link', 'approve' ),
		array( 'social', 'same_handle', 'reject' ),
		array( 'social', 'low_tier', 'reject' ),
		array( 'website', 'same_link', 'reject' ),
		array( 'website', 'same_site', 'reject' ),
		array( 'donate_url', 'same_link', 'reject' ),
		array( 'donate_url', 'home_page', 'reject' ),
		array( 'donate_url', 'names_donation', 'approve' ),
		array( 'donate_url', 'any', 'reject' ),
		array( 'adopt_url', 'same_link', 'reject' ),
		array( 'adopt_url', 'home_page', 'reject' ),
		array( 'adopt_url', 'any', 'reject' ),
		array( 'email', 'same_value', 'reject' ),
		array( 'email', 'typo_own_site', 'approve' ),
		array( 'email', 'near_duplicate', 'reject' ),
		array( 'email', 'domain_typo', 'approve' ),
	);
	$out  = array();
	foreach ( $rows as $r ) {
		$out[] = array( 'id' => $r[0] . '-' . $r[1], 'field' => $r[0], 'cond' => $r[1], 'action' => $r[2], 'on' => true );
	}
	return $out;
}

/**
 * Whether a condition holds for a waiting value.
 *
 * @param string $cond  Condition key (aw_ar_conditions()).
 * @param string $field Field key.
 * @param string $now   What the listing holds.
 * @param string $value The waiting value.
 * @param string $conf  high | medium | low.
 * @param array  $ctx   own_site, site_stem, site_host.
 * @return bool
 */
function aw_ar_test( $cond, $field, $now, $value, $conf, array $ctx ) {
	switch ( $cond ) {
		case 'any':
			return true;
		case 'low_tier':
			return 'low' === $conf;
		case 'unusable':
			return ! aw_ar_tokens( $value );
		case 'pin_differs':
			$pp = aw_ar_pin( $value );
			$np = aw_ar_pin( $now );
			return '' !== $pp && '' !== $np && $pp !== $np;
		case 'adds_nothing':
			$nt = aw_ar_tokens( $now );
			return ! array_filter( aw_ar_tokens( $value ), fn( $w ) => ! aw_ar_in( $w, $nt ) );
		case 'fuller':
			$pt = aw_ar_tokens( $value );
			$nt = aw_ar_tokens( $now );
			return $nt && ! array_filter( $nt, fn( $w ) => ! aw_ar_in( $w, $pt ) );
		case 'same_value':
			return 'pincode' === $field ? preg_replace( '/\D/', '', $value ) === preg_replace( '/\D/', '', $now ) : strtolower( $value ) === strtolower( $now );
		case 'same_link':
			return aw_ar_url( $value ) === aw_ar_url( $now );
		case 'content_link':
			return (bool) preg_match( '#/(posts|photos|videos|permalink|story\.php|share|reels?|watch|events|hashtag)(/|\?|$)#', strtolower( $value ) );
		case 'bare_link':
			$bp = explode( '/', aw_ar_url( $now ) );
			return count( $bp ) <= 1 || ( count( $bp ) <= 2 && in_array( $bp[1] ?? '', AW_AR_GENERIC, true ) );
		case 'same_handle':
			$bp = explode( '/', aw_ar_url( $now ) );
			$ap = explode( '/', aw_ar_url( $value ) );
			$bh = preg_replace( '/[._\-]/', '', $bp[1] ?? '' );
			return '' !== $bh && $bh === preg_replace( '/[._\-]/', '', $ap[1] ?? "\0" );
		case 'same_site':
			return preg_replace( '#/.*$#', '', aw_ar_url( $value ) ) === preg_replace( '#/.*$#', '', aw_ar_url( $now ) );
		case 'home_page':
			return '' === aw_ar_path( $value );
		case 'names_donation':
			return false !== stripos( aw_ar_path( $value ), 'donat' ) && false === stripos( aw_ar_path( $now ), 'donat' );
		case 'typo_own_site':
		case 'near_duplicate':
		case 'domain_typo':
			$a = explode( '@', strtolower( $value ), 2 );
			$b = explode( '@', strtolower( $now ), 2 );
			if ( 2 !== count( $a ) || 2 !== count( $b ) ) {
				return false;
			}
			if ( 'domain_typo' === $cond ) {
				$known = array_merge( AW_AR_MAIL_DOMAINS, array( strtolower( (string) ( $ctx['site_host'] ?? '' ) ) ) );
				return $a[0] === $b[0] && $a[1] !== $b[1] && levenshtein( $a[1], $b[1] ) <= 2 && in_array( $a[1], $known, true );
			}
			if ( $a[1] !== $b[1] || strlen( $a[0] ) < 4 || levenshtein( $a[0], $b[0] ) > 2 || $a[0] === $b[0] ) {
				return false;
			}
			if ( 'near_duplicate' === $cond ) {
				return true;
			}
			$stem = strtolower( (string) ( $ctx['site_stem'] ?? '' ) );
			return ! empty( $ctx['own_site'] ) && strlen( $stem ) >= 4 && false !== strpos( $a[0], $stem ) && false === strpos( $b[0], $stem );
	}
	return false;
}

/**
 * One decision: the first switched-on rule for the field whose condition holds.
 *
 * @param string     $field Field key (address, pincode, phone, facebook, instagram, x, linkedin, youtube, website,
 *                          donate_url, adopt_url, email).
 * @param string     $now   What the listing holds.
 * @param string     $value The waiting value.
 * @param string     $conf  high | medium | low.
 * @param array      $ctx   own_site (read on the organisation's own website), site_stem (the first word of its
 *                          website's name, e.g. wildcer), site_host (its host without www).
 * @param array|null $rules The rules in order (aw_ar_default_rules() when null).
 * @return array{act: string, why: string} act is approve, reject or leave.
 */
function aw_ar_decide( $field, $now, $value, $conf = '', array $ctx = array(), $rules = null ) {
	$now   = trim( (string) $now );
	$value = trim( (string) $value );
	if ( '' === $value || ( '' === $now && 'phone' !== $field ) ) {
		return array( 'act' => 'leave', 'why' => 'nothing to compare' ); // an empty field follows the plugin's own rules
	}
	$group = in_array( $field, AW_AR_SOCIAL, true ) ? 'social' : $field;
	$conds = aw_ar_conditions();
	foreach ( null === $rules ? aw_ar_default_rules() : $rules as $r ) {
		if ( empty( $r['on'] ) || ( $r['field'] ?? '' ) !== $group || ! isset( $conds[ $r['cond'] ?? '' ] ) || ! isset( AW_AR_ACTIONS[ $r['action'] ?? '' ] ) ) {
			continue;
		}
		if ( aw_ar_test( $r['cond'], $field, $now, $value, $conf, $ctx ) ) {
			return array( 'act' => $r['action'], 'why' => $conds[ $r['cond'] ]['label'] );
		}
	}
	return array( 'act' => 'leave', 'why' => 'no rule applies: a real difference' );
}
