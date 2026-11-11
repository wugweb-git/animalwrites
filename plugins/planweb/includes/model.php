<?php
/**
 * PlanWeb's rules that need no WordPress: the fixed lists, the field checks (link, time, no personal data), and the
 * mapping from Dates (option aw_team_dates) to entries with its one-for-one comparison. tests/run.php covers them.
 *
 * @package planweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Types: slug => label (plan 1.1). Old Dates entries become reminder. */
const PLANWEB_TYPES = array(
	'content'  => 'Content',
	'social'   => 'Social',
	'email'    => 'Email',
	'campaign' => 'Campaign',
	'reminder' => 'Reminder',
);

/** Channels: slug => label. */
const PLANWEB_CHANNELS = array(
	'site'       => 'Site',
	'x'          => 'X',
	'linkedin'   => 'LinkedIn',
	'instagram'  => 'Instagram',
	'facebook'   => 'Facebook',
	'newsletter' => 'Newsletter',
	'whatsapp'   => 'WhatsApp',
	'reddit'     => 'Reddit',
	'other'      => 'Other',
);

/** Owners (team labels, never people): slug => label. */
const PLANWEB_OWNERS = array(
	'owner'     => 'Owner',
	'main'      => 'Main',
	'wordpress' => 'WordPress',
	'martech'   => 'Martech',
	'team'      => 'Team',
);

/** Statuses people pick. "due" is set only by the scheduler (phase 3). */
const PLANWEB_STATUSES = array(
	'idea'     => 'Idea',
	'draft'    => 'Draft',
	'approved' => 'Approved',
	'due'      => 'Due',
	'sent'     => 'Sent',
);

/** Statuses only administrators may set (capability planweb_approve): the owner's yes. */
const PLANWEB_APPROVE_STATUSES = array( 'approved', 'sent' );

/** Hosts a Link may point to besides the site's own (subdomains included). */
const PLANWEB_SOCIAL_HOSTS = array( 'x.com', 'twitter.com', 'linkedin.com', 'instagram.com', 'facebook.com', 'threads.net', 'threads.com', 'reddit.com', 'whatsapp.com' );

/**
 * Whether a date is a real Y-m-d date.
 *
 * @param mixed $ymd Value.
 */
function planweb_is_date( $ymd ): bool {
	if ( ! is_string( $ymd ) || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m ) ) {
		return false;
	}
	return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
}

/**
 * Whether a time is empty or a real H:i (24 h, IST).
 *
 * @param mixed $hm Value.
 */
function planweb_is_time( $hm ): bool {
	return '' === $hm || ( is_string( $hm ) && 1 === preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $hm ) );
}

/**
 * Why a Link is refused, or '' when it is allowed (empty is allowed). Only https/http links to the site's own host or
 * a social host; never wa.me, tel: or mailto: (they carry phone numbers or addresses).
 *
 * @param string $url       Link.
 * @param string $site_host The site's host (home_url()).
 */
function planweb_link_problem( string $url, string $site_host ): string {
	$url = trim( $url );
	if ( '' === $url ) {
		return '';
	}
	$parts  = parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- also runs without WordPress (tests).
	$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
	$host   = strtolower( (string) ( $parts['host'] ?? '' ) );
	if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || '' === $host || isset( $parts['user'] ) ) {
		return 'The link must be a web address (https://…).';
	}
	$host  = preg_replace( '/^www\./', '', $host );
	$site  = preg_replace( '/^www\./', '', strtolower( $site_host ) );
	$hosts = array_merge( array( $site ), PLANWEB_SOCIAL_HOSTS );
	foreach ( $hosts as $h ) {
		if ( '' !== $h && ( $host === $h || str_ends_with( $host, '.' . $h ) ) ) {
			if ( 'whatsapp.com' === $h && ! preg_match( '#^/channel/#', (string) ( $parts['path'] ?? '' ) ) ) {
				return 'WhatsApp links may only be channel links (whatsapp.com/channel/…).';
			}
			return '';
		}
	}
	return 'The link must go to this site or to X, LinkedIn, Instagram, Facebook, Threads, Reddit or a WhatsApp channel.';
}

/**
 * Why a text is refused, or '' when it is fine: no email address and no phone number in any field (plan 1.1).
 * Phone: 10 or more digits in a run that may hold spaces, dots, dashes or brackets, optionally after +. Case numbers
 * such as "SMWP (C) 5/2025" or "diary no. 41706/2025" pass: a slash ends the run.
 *
 * @param string $text Title or note.
 */
function planweb_personal_data_problem( string $text ): string {
	if ( preg_match( '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $text ) ) {
		return 'Remove the email address: PlanWeb holds no personal data.';
	}
	$text = (string) preg_replace( '/\b\d{4}-\d{2}-\d{2}\b/', '/', $text ); // Y-m-d dates are not phone digits.
	if ( preg_match_all( '/\+?\d[\d\s().-]{8,}\d/', $text, $m ) ) {
		foreach ( $m[0] as $run ) {
			if ( strlen( (string) preg_replace( '/\D/', '', $run ) ) >= 10 ) {
				return 'Remove the phone number: PlanWeb holds no personal data.';
			}
		}
	}
	return '';
}

/**
 * The import key of one Dates element: its date, title and note, and which identical copy it is (1, 2, …), so two
 * identical elements stay two entries and a second import adds nothing.
 *
 * @param array $d Element (date, title, note).
 * @param int   $n Copy number.
 */
function planweb_source_key( array $d, int $n ): string {
	return sha1( (string) ( $d['date'] ?? '' ) . '|' . (string) ( $d['title'] ?? '' ) . '|' . (string) ( $d['note'] ?? '' ) . '|' . $n );
}

/**
 * The stored Dates list mapped to the entries an import makes, in stored order.
 *
 * @param mixed $stored get_option( 'aw_team_dates' ) as stored.
 * @return array{rows: array<int, array>, invalid: array<int, array>} rows: key, date, title, note, index; invalid:
 *               index and the element as text.
 */
function planweb_map_dates( $stored ): array {
	$rows    = array();
	$invalid = array();
	$seen    = array();
	foreach ( is_array( $stored ) ? array_values( $stored ) : array() as $i => $d ) {
		if ( ! is_array( $d ) || ! planweb_is_date( $d['date'] ?? null ) || '' === trim( (string) ( $d['title'] ?? '' ) ) ) {
			$invalid[] = array(
				'index' => $i,
				'value' => is_scalar( $d ) ? (string) $d : (string) json_encode( $d ), // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- also runs without WordPress (tests).
			);
			continue;
		}
		$d    = array(
			'date'  => (string) $d['date'],
			'title' => (string) $d['title'],
			'note'  => (string) ( $d['note'] ?? '' ),
		);
		$base = $d['date'] . '|' . $d['title'] . '|' . $d['note'];
		$n    = ( $seen[ $base ] ?? 0 ) + 1;

		$seen[ $base ] = $n;
		$rows[]        = $d + array(
			'key'   => planweb_source_key( $d, $n ),
			'index' => $i,
		);
	}
	return array(
		'rows'    => $rows,
		'invalid' => $invalid,
	);
}

/**
 * The one-for-one comparison: each stored element against the entry made from it.
 *
 * @param array $rows    planweb_map_dates()['rows'].
 * @param array $entries Imported entries by key: key => [date, title, note, id].
 * @return array{lines: array<int, array>, same: int, differs: int, missing: int, extra: int}
 */
function planweb_compare( array $rows, array $entries ): array {
	$out  = array(
		'lines'   => array(),
		'same'    => 0,
		'differs' => 0,
		'missing' => 0,
		'extra'   => 0,
	);
	$keys = array();
	foreach ( $rows as $r ) {
		$keys[ $r['key'] ] = true;
		$e                 = $entries[ $r['key'] ] ?? null;
		if ( null === $e ) {
			$state = 'missing';
		} elseif ( $e['date'] === $r['date'] && $e['title'] === $r['title'] && $e['note'] === $r['note'] ) {
			$state = 'same';
		} else {
			$state = 'differs';
		}
		++$out[ $state ];
		$out['lines'][] = array(
			'state' => $state,
			'dates' => $r,
			'entry' => $e,
		);
	}
	foreach ( $entries as $k => $e ) {
		if ( ! isset( $keys[ $k ] ) ) {
			++$out['extra'];
			$out['lines'][] = array(
				'state' => 'extra',
				'dates' => null,
				'entry' => $e,
			);
		}
	}
	return $out;
}
