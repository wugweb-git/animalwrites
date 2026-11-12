<?php
/**
 * PlanWeb's new screens: the rules that need no database. The counts the Dashboard shows (worked out by the
 * background job from a list of entries), the tiles made from them, the list's request arguments and the module switch
 * as a "save only what changed" step. tests/run.php covers them.
 *
 * @package planweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The Dashboard's time ranges, in days from today. */
const PLANWEB_RANGES = array( 7, 30, 90 );

/** Statuses that still need something doing: a past entry with one of them is overdue (plan D3). */
const PLANWEB_OPEN_STATUSES = array( 'idea', 'draft', 'approved', 'due' );

/** The list's date windows: slug => days from today (negative: from that many days ago; null: no limit). */
const PLANWEB_WINDOWS = array(
	'from30' => -30,
	'next7'  => 7,
	'next30' => 30,
	'next90' => 90,
	'past'   => 0,
	'all'    => null,
);

/** Rows per page of the list. */
const PLANWEB_PER_PAGE = 20;

/**
 * A date some days after another (UTC, so no clock change moves it).
 *
 * @param string $ymd  Y-m-d.
 * @param int    $days Days (negative for before).
 */
function planweb_add_days( string $ymd, int $days ): string {
	$d = DateTimeImmutable::createFromFormat( '!Y-m-d', $ymd, new DateTimeZone( 'UTC' ) );
	if ( ! $d ) {
		return $ymd;
	}
	return $d->modify( ( $days >= 0 ? '+' : '' ) . $days . ' days' )->format( 'Y-m-d' );
}

/**
 * Whether an entry is overdue: flagged by the scheduler, or its day has passed while it is still open.
 *
 * @param array  $e     Entry (date, status, flag).
 * @param string $today Y-m-d.
 */
function planweb_is_overdue( array $e, string $today ): bool {
	if ( 'overdue' === (string) ( $e['flag'] ?? '' ) ) {
		return true;
	}
	$date = (string) ( $e['date'] ?? '' );
	return '' !== $date && $date < $today && in_array( (string) ( $e['status'] ?? 'idea' ), PLANWEB_OPEN_STATUSES, true );
}

/**
 * The Dashboard's counts from every entry (the background job's work).
 *
 * @param array  $entries planweb_entry() arrays.
 * @param string $today   Y-m-d.
 * @param array  $check   planweb_verify() totals (same, differs, missing, extra), or empty.
 * @return array at-free counts: day, total, today, ahead (per range: total, types), overdue (total, oldest),
 *               waiting, ready, statuses, types, next (date, time, title, id) or null, check.
 */
function planweb_count_entries( array $entries, string $today, array $check = array() ): array {
	$zero = array_fill_keys( array_keys( PLANWEB_TYPES ), 0 );
	$c    = array(
		'day'      => $today,
		'total'    => 0,
		'today'    => 0,
		'ahead'    => array(),
		'overdue'  => array(
			'total'  => 0,
			'oldest' => '',
		),
		'waiting'  => 0,
		'ready'    => 0,
		'statuses' => array_fill_keys( array_keys( PLANWEB_STATUSES ), 0 ),
		'types'    => $zero,
		'next'     => null,
		'check'    => array(),
	);
	foreach ( PLANWEB_RANGES as $r ) {
		$c['ahead'][ $r ] = array(
			'total' => 0,
			'types' => $zero,
			'end'   => planweb_add_days( $today, $r - 1 ),
		);
	}
	foreach ( $entries as $e ) {
		$date   = (string) ( $e['date'] ?? '' );
		$type   = isset( PLANWEB_TYPES[ $e['type'] ?? '' ] ) ? (string) $e['type'] : 'reminder';
		$status = isset( PLANWEB_STATUSES[ $e['status'] ?? '' ] ) ? (string) $e['status'] : 'idea';
		++$c['total'];
		++$c['types'][ $type ];
		++$c['statuses'][ $status ];
		if ( 'draft' === $status ) {
			++$c['waiting'];
		}
		if ( in_array( $status, array( 'approved', 'due' ), true ) ) {
			++$c['ready'];
		}
		if ( $date === $today ) {
			++$c['today'];
		}
		if ( planweb_is_overdue( $e, $today ) ) {
			++$c['overdue']['total'];
			if ( '' === $c['overdue']['oldest'] || $date < $c['overdue']['oldest'] ) {
				$c['overdue']['oldest'] = $date;
			}
		}
		if ( '' === $date || $date < $today ) {
			continue;
		}
		foreach ( PLANWEB_RANGES as $r ) {
			if ( $date <= $c['ahead'][ $r ]['end'] ) {
				++$c['ahead'][ $r ]['total'];
				++$c['ahead'][ $r ]['types'][ $type ];
			}
		}
		$time = (string) ( $e['time'] ?? '' );
		$key  = $date . ' ' . ( '' === $time ? '99:99' : $time ) . ' ' . (string) ( $e['title'] ?? '' );
		if ( null === $c['next'] || $key < $c['next']['key'] ) {
			$c['next'] = array(
				'key'   => $key,
				'date'  => $date,
				'time'  => $time,
				'title' => (string) ( $e['title'] ?? '' ),
				'id'    => (int) ( $e['id'] ?? 0 ),
			);
		}
	}
	if ( $check ) {
		$c['check'] = array(
			'same'    => (int) ( $check['same'] ?? 0 ),
			'differs' => (int) ( $check['differs'] ?? 0 ),
			'missing' => (int) ( $check['missing'] ?? 0 ),
			'extra'   => (int) ( $check['extra'] ?? 0 ),
		);
	}
	if ( null !== $c['next'] ) {
		unset( $c['next']['key'] );
	}
	return $c;
}

/**
 * "3 social, 2 content": the largest Types first, at most three.
 *
 * @param array $types slug => count.
 */
function planweb_types_line( array $types ): string {
	$types = array_filter( $types );
	arsort( $types );
	$parts = array();
	foreach ( array_slice( $types, 0, 3, true ) as $slug => $n ) {
		$parts[] = number_format_i18n( (int) $n ) . ' ' . strtolower( PLANWEB_TYPES[ $slug ] ?? (string) $slug );
	}
	return implode( ', ', $parts );
}

/**
 * The Dashboard's tiles from the stored counts, for one range. Formatting only.
 *
 * @param array    $c     planweb_count_entries() plus at.
 * @param int      $range One of PLANWEB_RANGES.
 * @param callable $url   ( array $list_args ) => the Entries screen's address with those filters.
 * @param callable $label ( string $ymd ) => the date in words ("17 Nov 2026").
 * @return array[] key, label, value, sub, href, tone.
 */
function planweb_dashboard_tiles( array $c, int $range, callable $url, callable $label ): array {
	$range = in_array( $range, PLANWEB_RANGES, true ) ? $range : 30;
	$num   = fn( $n ) => number_format_i18n( (int) $n );
	$ahead = (array) ( $c['ahead'][ $range ] ?? array() );
	$od    = (array) ( $c['overdue'] ?? array() );
	$next  = $c['next'] ?? null;
	$line  = planweb_types_line( (array) ( $ahead['types'] ?? array() ) );
	$tiles = array(
		array(
			'key'   => 'ahead',
			/* translators: %d: number of days. */
			'label' => sprintf( __( 'Coming up in %d days', 'planweb' ), $range ),
			'value' => $num( $ahead['total'] ?? 0 ),
			'sub'   => '' !== $line ? $line : __( 'Nothing planned yet', 'planweb' ),
			'href'  => $url( array( 'when' => 'next' . $range ) ),
			'tone'  => '',
		),
		array(
			'key'   => 'overdue',
			'label' => __( 'Overdue', 'planweb' ),
			'value' => $num( $od['total'] ?? 0 ),
			/* translators: %s: date. */
			'sub'   => ! empty( $od['total'] ) ? sprintf( __( 'Oldest from %s', 'planweb' ), $label( (string) $od['oldest'] ) ) : __( 'Nothing past its day', 'planweb' ),
			'href'  => $url( array( 'view' => 'overdue' ) ),
			'tone'  => ! empty( $od['total'] ) ? 'warn' : '',
		),
		array(
			'key'   => 'waiting',
			'label' => __( 'Waiting for approval', 'planweb' ),
			'value' => $num( $c['waiting'] ?? 0 ),
			'sub'   => __( 'Drafts an administrator can approve', 'planweb' ),
			'href'  => $url( array( 'view' => 'waiting' ) ),
			'tone'  => '',
		),
		array(
			'key'   => 'ready',
			'label' => __( 'Approved, not sent', 'planweb' ),
			'value' => $num( $c['ready'] ?? 0 ),
			'sub'   => __( 'Approved or due', 'planweb' ),
			'href'  => $url(
				array(
					'status' => 'approved',
					'when'   => 'all',
				)
			),
			'tone'  => '',
		),
		array(
			'key'   => 'next',
			'label' => __( 'Next date', 'planweb' ),
			'value' => $next ? $label( (string) $next['date'] ) : __( 'None', 'planweb' ),
			'sub'   => $next ? ( '' !== $next['time'] ? $next['time'] . ' ' : '' ) . $next['title'] : __( 'Nothing from today on', 'planweb' ),
			'href'  => $url( array( 'when' => 'next7' ) ),
			'tone'  => '',
		),
		array(
			'key'   => 'today',
			'label' => __( 'Today', 'planweb' ),
			'value' => $num( $c['today'] ?? 0 ),
			'sub'   => _n( 'entry dated today', 'entries dated today', (int) ( $c['today'] ?? 0 ), 'planweb' ),
			'href'  => $url( array( 'when' => 'next7' ) ),
			'tone'  => '',
		),
		array(
			'key'   => 'total',
			'label' => __( 'All entries', 'planweb' ),
			'value' => $num( $c['total'] ?? 0 ),
			/* translators: 1: ideas, 2: drafts, 3: sent. */
			'sub'   => sprintf( __( '%1$s ideas, %2$s drafts, %3$s sent', 'planweb' ), $num( $c['statuses']['idea'] ?? 0 ), $num( $c['statuses']['draft'] ?? 0 ), $num( $c['statuses']['sent'] ?? 0 ) ),
			'href'  => $url( array( 'when' => 'all' ) ),
			'tone'  => '',
		),
	);
	$check = (array) ( $c['check'] ?? array() );
	if ( $check ) {
		$bad     = (int) $check['differs'] + (int) $check['missing'] + (int) $check['extra'];
		$tiles[] = array(
			'key'   => 'check',
			'label' => __( 'Import check', 'planweb' ),
			'value' => $bad ? $num( $bad ) : __( 'Matches', 'planweb' ),
			'sub'   => $bad
				/* translators: %s: number of dates. */
				? sprintf( _n( '%s date differs from Dates', '%s dates differ from Dates', $bad, 'planweb' ), $num( $bad ) )
				/* translators: %s: number of dates. */
				: sprintf( __( '%s dates the same as in Dates', 'planweb' ), $num( $check['same'] ) ),
			'href'  => function_exists( 'planweb_ui_url' ) && current_user_can( 'manage_options' ) ? planweb_ui_url( 'settings' ) . '#import' : '',
			'tone'  => $bad ? 'warn' : '',
		);
	}
	return $tiles;
}

/**
 * The list's request, checked: unknown values fall back to the defaults (from 30 days ago, by date, oldest first).
 *
 * @param array  $p     Request parameters (page, search, type, channel, owner, status, view, when, sort, dir).
 * @param string $today Y-m-d.
 * @return array The same keys, plus from and to (Y-m-d or '') and before (Y-m-d or '': dates before it only).
 */
function planweb_list_args( array $p, string $today ): array {
	$pick = fn( string $k, array $values ) => isset( $values[ (string) ( $p[ $k ] ?? '' ) ] ) ? (string) $p[ $k ] : '';
	$a    = array(
		'page'    => max( 1, (int) ( $p['page'] ?? 1 ) ),
		'search'  => trim( (string) ( $p['search'] ?? '' ) ),
		'type'    => $pick( 'type', PLANWEB_TYPES ),
		'channel' => $pick( 'channel', PLANWEB_CHANNELS ),
		'owner'   => $pick( 'owner', PLANWEB_OWNERS ),
		'status'  => $pick( 'status', PLANWEB_STATUSES ),
		'view'    => in_array( (string) ( $p['view'] ?? '' ), array( 'overdue', 'waiting' ), true ) ? (string) $p['view'] : 'all',
		'when'    => array_key_exists( (string) ( $p['when'] ?? '' ), PLANWEB_WINDOWS ) ? (string) $p['when'] : 'from30',
		'sort'    => in_array( (string) ( $p['sort'] ?? '' ), array( 'date', 'title' ), true ) ? (string) $p['sort'] : 'date',
		'dir'     => 'desc' === (string) ( $p['dir'] ?? '' ) ? 'desc' : 'asc',
		'from'    => '',
		'to'      => '',
		'before'  => '',
	);
	if ( 'all' !== $a['view'] ) {
		$a['when'] = 'all'; // Overdue is in the past by itself; Waiting counts every draft, whatever its date.
	}
	$days = PLANWEB_WINDOWS[ $a['when'] ];
	if ( 'past' === $a['when'] ) {
		$a['before'] = $today;
	} elseif ( null !== $days && $days < 0 ) {
		$a['from'] = planweb_add_days( $today, $days );
	} elseif ( null !== $days ) {
		$a['from'] = $today;
		$a['to']   = planweb_add_days( $today, $days - 1 );
	}
	if ( 'overdue' === $a['view'] ) {
		$a['before'] = $today;
	}
	return $a;
}

/**
 * The module switch as Settings saves it: the new stored value, or null when nothing changes (an unchanged Save writes
 * nothing).
 *
 * @param mixed  $sent   The switch as sent (true or false).
 * @param string $stored get_option( 'planweb_on' ).
 */
function planweb_switch_change( $sent, string $stored ): ?string {
	$new = planweb_on_clean( $sent );
	return planweb_on_clean( $stored ) === $new ? null : $new;
}

/**
 * The stored form of the switch ('1' on, '' off), as Kits → Modules saves it.
 *
 * @param mixed $v Value.
 */
function planweb_on_clean( $v ): string {
	return $v ? '1' : '';
}
