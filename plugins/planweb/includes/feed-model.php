<?php
/**
 * The calendar feed's rules that need no WordPress (tests/run.php covers them): which entries a feed address asks
 * for (by type and status) and the iCalendar text (RFC 5545) made from them. One way and read only: date, time,
 * title, type, channels, status and link. Never the note; a title that looks like it holds an email address or a phone
 * number is replaced (old imported entries skipped that check).
 *
 * @package planweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Entry times are IST (store.php, planweb_time). */
const PLANWEB_FEED_TZ = 'Asia/Kolkata';

/** Minutes a timed entry lasts in a calendar app (entries have a start time only). */
const PLANWEB_FEED_MINUTES = 30;

/** Named groups a feed address may use for type: marketing = social, email, campaign, content (no reminders). */
const PLANWEB_FEED_TYPE_GROUPS = array(
	'marketing' => array( 'social', 'email', 'campaign', 'content' ),
);

/** Named groups a feed address may use for status: open = not sent and not done. */
const PLANWEB_FEED_STATUS_GROUPS = array(
	'open' => array( 'idea', 'draft', 'approved', 'due' ),
);

/**
 * A feed filter from the address: slugs joined by commas, and group names. Empty means no filter.
 *
 * @param mixed $value   The parameter as sent.
 * @param array $allowed slug => label.
 * @param array $groups  name => slugs.
 * @return string[]|null The slugs, or null when a part is unknown (the feed answers 400).
 */
function planweb_feed_list( $value, array $allowed, array $groups ): ?array {
	$out = array();
	foreach ( explode( ',', is_scalar( $value ) ? strtolower( (string) $value ) : '' ) as $part ) {
		$part = trim( $part );
		if ( '' === $part ) {
			continue;
		}
		if ( isset( $groups[ $part ] ) ) {
			$out = array_merge( $out, $groups[ $part ] );
		} elseif ( isset( $allowed[ $part ] ) ) {
			$out[] = $part;
		} else {
			return null;
		}
	}
	return array_values( array_unique( $out ) );
}

/**
 * The entries a feed shows.
 *
 * @param array    $entries  planweb_entry() arrays.
 * @param string[] $types    Type slugs, or empty for every type.
 * @param string[] $statuses Status slugs, or empty for every status.
 * @return array[]
 */
function planweb_feed_pick( array $entries, array $types, array $statuses ): array {
	return array_values(
		array_filter(
			$entries,
			fn( $e ) => ( ! $types || in_array( (string) ( $e['type'] ?? '' ), $types, true ) )
				&& ( ! $statuses || in_array( (string) ( $e['status'] ?? 'idea' ), $statuses, true ) )
		)
	);
}

/**
 * Text as an iCalendar TEXT value: backslash, semicolon, comma and line breaks escaped.
 *
 * @param string $text Text.
 */
function planweb_ics_text( string $text ): string {
	$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
	return str_replace( array( '\\', ';', ',', "\n" ), array( '\\\\', '\\;', '\\,', '\\n' ), $text );
}

/**
 * One content line folded at 75 octets (RFC 5545 3.1), never inside a UTF-8 character.
 *
 * @param string $line Line without its line break.
 * @return string The line with CRLF line breaks, ending in CRLF.
 */
function planweb_ics_fold( string $line ): string {
	$out = '';
	$max = 75;
	$len = strlen( $line );
	while ( $len > $max ) {
		$cut = $max;
		while ( $cut > 0 && 0x80 === ( ord( $line[ $cut ] ) & 0xC0 ) ) {
			--$cut; // Back to the start of a UTF-8 character.
		}
		$out .= substr( $line, 0, $cut ) . "\r\n ";
		$line = substr( $line, $cut );
		$len  = strlen( $line );
		$max  = 74; // The leading space counts.
	}
	return $out . $line . "\r\n";
}

/**
 * The lines of one event.
 *
 * @param array $e   planweb_entry() array (id, date, time, title, type, channels, status, link).
 * @param array $ctx host (the site's host, for UID and link checks), stamp (DTSTAMP, UTC "Ymd\THis\Z"), and the
 *                      labels: types, channels, statuses (slug => label).
 * @return string[] Unfolded lines; empty when the entry has no real date.
 */
function planweb_ics_event( array $e, array $ctx ): array {
	if ( ! planweb_is_date( $e['date'] ?? null ) ) {
		return array();
	}
	$title = (string) ( $e['title'] ?? '' );
	if ( '' !== planweb_personal_data_problem( $title ) ) {
		$title = 'Entry ' . (int) $e['id'] . ' (title not shown: check it in PlanWeb)';
	}
	$type     = (string) ( $ctx['types'][ $e['type'] ?? '' ] ?? '' );
	$channels = array();
	foreach ( (array) ( $e['channels'] ?? array() ) as $slug ) {
		if ( isset( $ctx['channels'][ $slug ] ) ) {
			$channels[] = (string) $ctx['channels'][ $slug ];
		}
	}
	$status = (string) ( $ctx['statuses'][ $e['status'] ?? '' ] ?? '' );
	$link   = (string) ( $e['link'] ?? '' );
	if ( '' !== $link && '' !== planweb_link_problem( $link, (string) $ctx['host'] ) ) {
		$link = '';
	}
	$lines   = array(
		'BEGIN:VEVENT',
		'UID:planweb-' . (int) $e['id'] . '@' . $ctx['host'],
		'DTSTAMP:' . $ctx['stamp'],
	);
	$changed = (string) ( $e['modified'] ?? '' );
	if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$/', $changed, $m ) && '0000' !== $m[1] ) {
		$lines[] = 'LAST-MODIFIED:' . $m[1] . $m[2] . $m[3] . 'T' . $m[4] . $m[5] . $m[6] . 'Z'; // Lets calendar apps see a changed entry.
	}
	$day  = str_replace( '-', '', (string) $e['date'] );
	$time = (string) ( $e['time'] ?? '' );
	if ( '' !== $time && planweb_is_time( $time ) ) {
		$start   = new DateTimeImmutable( $e['date'] . ' ' . $time . ':00', new DateTimeZone( PLANWEB_FEED_TZ ) );
		$start   = $start->setTimezone( new DateTimeZone( 'UTC' ) );
		$lines[] = 'DTSTART:' . $start->format( 'Ymd\THis\Z' );
		$lines[] = 'DTEND:' . $start->modify( '+' . PLANWEB_FEED_MINUTES . ' minutes' )->format( 'Ymd\THis\Z' );
	} else {
		$next    = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $e['date'], new DateTimeZone( 'UTC' ) )->modify( '+1 day' );
		$lines[] = 'DTSTART;VALUE=DATE:' . $day;
		$lines[] = 'DTEND;VALUE=DATE:' . $next->format( 'Ymd' );
	}
	$lines[] = 'SUMMARY:' . planweb_ics_text( $title );
	$about   = array_filter(
		array(
			'' !== $type ? 'Type: ' . $type : '',
			$channels ? 'Channels: ' . implode( ', ', $channels ) : '',
			'' !== $status ? 'Status: ' . $status : '',
			'' !== $link ? 'Link: ' . $link : '',
		)
	);
	if ( $about ) {
		$lines[] = 'DESCRIPTION:' . planweb_ics_text( implode( "\n", $about ) );
	}
	$cats = array_filter( array_merge( array( $type ), $channels ) );
	if ( $cats ) {
		$lines[] = 'CATEGORIES:' . implode( ',', array_map( 'planweb_ics_text', $cats ) );
	}
	if ( '' !== $link ) {
		$lines[] = 'URL:' . $link;
	}
	$lines[] = 'TRANSP:TRANSPARENT'; // A plan, not a meeting: never shows the team as busy.
	$lines[] = 'END:VEVENT';
	return $lines;
}

/**
 * The whole feed.
 *
 * @param array[] $entries planweb_entry() arrays.
 * @param array   $ctx     As planweb_ics_event(), plus name (the calendar's name) and version.
 * @return string iCalendar text with CRLF line breaks.
 */
function planweb_ics( array $entries, array $ctx ): string {
	$lines = array(
		'BEGIN:VCALENDAR',
		'VERSION:2.0',
		'PRODID:-//Wugweb//PlanWeb ' . $ctx['version'] . '//EN',
		'CALSCALE:GREGORIAN',
		'METHOD:PUBLISH',
		'X-WR-CALNAME:' . planweb_ics_text( (string) $ctx['name'] ),
		'X-WR-TIMEZONE:' . PLANWEB_FEED_TZ,
		'REFRESH-INTERVAL;VALUE=DURATION:PT1H',
		'X-PUBLISHED-TTL:PT1H',
	);
	foreach ( $entries as $e ) {
		$lines = array_merge( $lines, planweb_ics_event( $e, $ctx ) );
	}
	$lines[] = 'END:VCALENDAR';
	return implode( '', array_map( 'planweb_ics_fold', $lines ) );
}
