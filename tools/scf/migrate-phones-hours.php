<?php
/**
 * Copy the single phone and hours fields of every listing (all statuses) into the SCF lists.
 *
 *   wp eval-file migrate-phones-hours.php            # dry run: counts and the report, nothing written
 *   wp eval-file migrate-phones-hours.php apply      # write the lists
 *
 * - aw_phone (and aw_whatsapp, as a WhatsApp row; one row when it is the same number) -> aw_phones.
 * - aw_hours_spec, else aw_hours -> aw_opening_hours, only when every part of the text is clearly a day (or day range)
 *   with times, "closed", "24 hours" or "24x7". Anything else is reported and left as it is; no time is ever guessed
 *   (a range without days, times without am/pm or a colon, "noon", notes such as "emergencies" all count as unclear).
 * - The old keys are not touched. A listing that already has rows in a list keeps them (that list is skipped).
 * - Lists are written with update_field() on the field key: SCF's own repeater format.
 * - Report (CSV) to the path in $AW_REPORT, else ./migrate-phones-hours.csv.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) || ! function_exists( 'update_field' ) ) {
	WP_CLI::error( 'Needs WordPress with SCF active.' );
}

$apply  = in_array( 'apply', (array) ( $args ?? array() ), true );
$report = getenv( 'AW_REPORT' ) ? getenv( 'AW_REPORT' ) : 'migrate-phones-hours.csv';

/** "9", "9:30", "09:00", "9 am", "9.30pm" -> "HH:MM" (24 h), or '' when unclear. */
function aw_mig_time( $t ) {
	$t = strtolower( trim( str_replace( '.', ':', $t ) ) );
	if ( ! preg_match( '/^(\d{1,2})(?::(\d{2}))?\s*(am|pm|a:m:|p:m:)?$/', $t, $m ) ) {
		return '';
	}
	$h   = (int) $m[1];
	$min = isset( $m[2] ) && '' !== $m[2] ? (int) $m[2] : 0;
	$ap  = isset( $m[3] ) ? $m[3][0] : '';
	if ( '' === $ap ) {
		if ( ! isset( $m[2] ) || '' === $m[2] ) {
			return ''; // "9" with no am/pm and no minutes: unclear
		}
		if ( $h > 23 ) {
			return '';
		}
	} else {
		if ( $h < 1 || $h > 12 ) {
			return '';
		}
		$h = 'a' === $ap ? ( 12 === $h ? 0 : $h ) : ( 12 === $h ? 12 : $h + 12 );
	}
	return $min > 59 ? '' : sprintf( '%02d:%02d', $h, $min );
}

/** "Mon–Sat", "Monday to Friday", "Sat, Sun", "Daily" -> list of day names, or null when unclear. */
function aw_mig_days( $s ) {
	$s    = strtolower( trim( $s, " \t:,.-" ) );
	$keys = array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' );
	if ( preg_match( '/^(daily|every\s*day|all\s*days|all\s*7\s*days|7\s*days(\s*a\s*week)?|mon(day)?\s*(-|–|—|to)\s*sun(day)?)$/', $s ) ) {
		return AW_WEEKDAYS;
	}
	$out = array();
	foreach ( preg_split( '/\s*(?:,|&|\band\b|\/)\s*/', $s ) as $part ) {
		if ( ! preg_match( '/^([a-z]{3})[a-z]*\.?(?:\s*(?:-|–|—|to)\s*([a-z]{3})[a-z]*\.?)?$/', $part, $m ) ) {
			return null;
		}
		$from = array_search( $m[1], $keys, true );
		$to   = isset( $m[2] ) && '' !== $m[2] ? array_search( $m[2], $keys, true ) : $from;
		if ( false === $from || false === $to ) {
			return null;
		}
		for ( $i = $from; ; $i = ( $i + 1 ) % 7 ) {
			$out[] = AW_WEEKDAYS[ $i ];
			if ( $i === $to ) {
				break;
			}
		}
	}
	return $out ? array_values( array_unique( $out ) ) : null;
}

/** Whole text -> rows [day, open, close, closed], or null when any part is unclear. */
function aw_mig_hours( $text ) {
	$text = trim( str_replace( array( "\r\n", '\\n' ), "\n", (string) $text ) );
	if ( '' === $text ) {
		return null;
	}
	$rows = array();
	$time = '(\d{1,2}(?:[:.]\d{2})?\s*(?:am|pm|a\.m\.|p\.m\.)?)';
	$dash = '\s*(?:-|–|—|to)\s*';
	foreach ( preg_split( '/\s*[;\n|]\s*/', $text ) as $seg ) {
		$seg = trim( $seg, " \t." );
		if ( '' === $seg ) {
			continue;
		}
		$l = strtolower( $seg );
		if ( preg_match( '/^(?:open\s*)?(?:24\s*[x×\/]\s*7|24\s*hours?(?:\s*(?:a\s*day|,?\s*7\s*days(?:\s*a\s*week)?|daily|all\s*days))?)$/', $l ) ) {
			foreach ( AW_WEEKDAYS as $d ) {
				$rows[] = array( $d, '00:00', '23:59', false );
			}
			continue;
		}
		if ( preg_match( '/^(?:closed\s*(?:on\s*)?(.+)|(.+?)\s*:?\s*closed)$/', $l, $m ) ) {
			$days = aw_mig_days( '' !== ( $m[1] ?? '' ) ? $m[1] : $m[2] );
			if ( null === $days ) {
				return null;
			}
			foreach ( $days as $d ) {
				$rows[] = array( $d, '', '', true );
			}
			continue;
		}
		if ( preg_match( '/^(.+?)\s*:?\s*(?:open\s*)?24\s*hours?$/', $l, $m ) && null !== aw_mig_days( $m[1] ) ) {
			foreach ( aw_mig_days( $m[1] ) as $d ) {
				$rows[] = array( $d, '00:00', '23:59', false );
			}
			continue;
		}
		// Days, then one or two time ranges ("Mon-Sat 9 am - 1 pm, 4 pm - 7 pm").
		if ( ! preg_match( '/^(.+?)\s*:?\s*' . $time . $dash . $time . '(?:\s*(?:,|&|and)\s*' . $time . $dash . $time . ')?$/i', $seg, $m ) ) {
			return null;
		}
		$days = aw_mig_days( $m[1] );
		if ( null === $days ) {
			return null;
		}
		$ranges = array( array( $m[2], $m[3] ) );
		if ( ! empty( $m[4] ) ) {
			$ranges[] = array( $m[4], $m[5] );
		}
		foreach ( $ranges as $r ) {
			$o = aw_mig_time( $r[0] );
			$c = aw_mig_time( $r[1] );
			if ( '' === $o || '' === $c || $c <= $o ) {
				return null; // unclear, or closing before opening (overnight or a missing am/pm): not guessed
			}
			foreach ( $days as $d ) {
				$rows[] = array( $d, $o, $c, false );
			}
		}
	}
	if ( ! $rows || count( $rows ) > 14 ) {
		return null;
	}
	$seen = array();
	foreach ( $rows as $r ) { // a day stated twice in conflicting ways (closed and open): unclear
		$seen[ $r[0] ][] = $r[3];
	}
	foreach ( $seen as $flags ) {
		if ( count( array_unique( $flags ) ) > 1 ) {
			return null;
		}
	}
	return $rows;
}

$ids = get_posts( array( 'post_type' => 'aw_listing', 'post_status' => 'any,trash,auto-draft', 'fields' => 'ids', 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) );
$c   = array( 'listings' => count( $ids ), 'phone_lists' => 0, 'phone_rows' => 0, 'whatsapp_merged' => 0, 'whatsapp_own_row' => 0, 'phones_skipped_existing' => 0, 'hours_parsed' => 0, 'hours_rows' => 0, 'hours_unparsed' => 0, 'hours_skipped_existing' => 0 );
$out = fopen( $report, 'w' );
fputcsv( $out, array( 'id', 'status', 'old_aw_phone', 'old_aw_whatsapp', 'new_phone_rows', 'old_aw_hours', 'old_aw_hours_spec', 'new_hours_rows', 'hours_parsed', 'note' ) );

foreach ( $ids as $id ) {
	$phone = trim( (string) get_post_meta( $id, 'aw_phone', true ) );
	$wa    = trim( (string) get_post_meta( $id, 'aw_whatsapp', true ) );
	$hours = trim( (string) get_post_meta( $id, 'aw_hours', true ) );
	$spec  = trim( (string) get_post_meta( $id, 'aw_hours_spec', true ) );
	if ( '' === $phone . $wa . $hours . $spec ) {
		continue;
	}
	$note     = array();
	$p_rows   = array();
	$h_rows   = null;
	$h_parsed = '';

	if ( '' !== $phone . $wa ) {
		if ( (int) get_post_meta( $id, 'aw_phones', true ) > 0 ) {
			++$c['phones_skipped_existing'];
			$note[] = 'phone list already had rows: kept';
		} else {
			$p_rows = aw_phones_from_single( $phone, $wa );
			++$c['phone_lists'];
			$c['phone_rows'] += count( $p_rows );
			if ( '' !== $wa ) {
				'' !== $phone && 1 === count( $p_rows ) ? ++$c['whatsapp_merged'] : ++$c['whatsapp_own_row'];
			}
			if ( $apply ) {
				update_field( 'field_aw_listing_phones', array_map( fn( $r ) => array( 'field_aw_listing_phones_number' => $r['number'], 'field_aw_listing_phones_label' => $r['label'], 'field_aw_listing_phones_whatsapp' => $r['whatsapp'] ? 1 : 0 ), $p_rows ), $id );
			}
		}
	}

	if ( '' !== $hours . $spec ) {
		if ( (int) get_post_meta( $id, 'aw_opening_hours', true ) > 0 ) {
			++$c['hours_skipped_existing'];
			$h_parsed = 'kept';
			$note[]   = 'hours list already had rows: kept';
		} else {
			$from_spec = '' !== $spec ? aw_mig_hours( $spec ) : null;
			$from_text = '' !== $hours ? aw_mig_hours( $hours ) : null;
			$h_rows    = $from_spec ? $from_spec : $from_text;
			if ( $from_spec && '' !== $hours && null === $from_text ) {
				$note[] = 'rows from aw_hours_spec; aw_hours text not parseable, left as it is';
			} elseif ( $from_spec && $from_text && $from_spec !== $from_text ) {
				$note[] = 'rows from aw_hours_spec; aw_hours differs, left as it is';
			} elseif ( ! $h_rows ) {
				$note[] = 'not parseable: left as it is';
			}
			if ( $h_rows ) {
				$h_parsed = 'yes';
				++$c['hours_parsed'];
				$c['hours_rows'] += count( $h_rows );
				if ( $apply ) {
					update_field( 'field_aw_listing_opening_hours', array_map( fn( $r ) => array( 'field_aw_listing_opening_hours_day' => $r[0], 'field_aw_listing_opening_hours_open' => $r[3] ? '' : $r[1] . ':00', 'field_aw_listing_opening_hours_close' => $r[3] ? '' : $r[2] . ':00', 'field_aw_listing_opening_hours_closed' => $r[3] ? 1 : 0 ), $h_rows ), $id );
				}
			} else {
				$h_parsed = 'no';
				++$c['hours_unparsed'];
			}
		}
	}

	fputcsv(
		$out,
		array(
			$id,
			get_post_status( $id ),
			$phone,
			$wa,
			implode( ' | ', array_map( fn( $r ) => $r['number'] . ( $r['label'] ? ' (' . $r['label'] . ')' : '' ) . ( $r['whatsapp'] ? ' [WhatsApp]' : '' ), $p_rows ) ),
			$hours,
			str_replace( "\n", '\\n', $spec ),
			$h_rows ? implode( ' | ', array_map( fn( $r ) => $r[0] . ' ' . ( $r[3] ? 'closed' : $r[1] . '-' . $r[2] ), $h_rows ) ) : '',
			$h_parsed,
			implode( '; ', $note ),
		)
	);
}
fclose( $out );
WP_CLI::log( ( $apply ? 'APPLIED' : 'DRY RUN' ) . ' ' . wp_json_encode( $c ) );
WP_CLI::log( 'report: ' . $report );
