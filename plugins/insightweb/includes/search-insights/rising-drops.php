<?php
/**
 * Rising: what each source's latest run kept and dropped, and why (12 Nov 2026: Rising showed New (0) for days with no
 * way to see what the sources had seen). The workers send counts for their own filters (trending, kept, excluded) and
 * may send dropped: [ { term, filter } ]; terms the site itself refuses (not English, empty, no valid date) are
 * always recorded. Kept in the run log (aw_si_runs), at most AW_SI_RISING_MAX_DROPPED terms per run. Loaded by
 * rising.php.
 *
 * @package insightweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SI_RISING_MAX_DROPPED = 100; // Per run: dropped terms kept in the run log, with the filter that dropped each.

/**
 * The terms a worker says its own filters dropped: [ [term, filter], … ], at most AW_SI_RISING_MAX_DROPPED. The worker
 * may send dropped: [ { term, filter } ]; anything else is ignored.
 *
 * @param mixed $list The body's dropped field.
 * @return array<int, array{0: string, 1: string}>
 */
function aw_si_rising_worker_dropped( $list ): array {
	$out = array();
	foreach ( is_array( $list ) ? $list : array() as $r ) {
		if ( ! is_array( $r ) || ! is_scalar( $r['term'] ?? null ) || ! is_scalar( $r['filter'] ?? null ) ) {
			continue;
		}
		$term = mb_substr( trim( sanitize_text_field( (string) $r['term'] ) ), 0, 120 );
		if ( '' !== $term ) {
			$out[] = array( $term, 'worker:' . mb_substr( sanitize_key( (string) $r['filter'] ), 0, 40 ) );
		}
		if ( count( $out ) >= AW_SI_RISING_MAX_DROPPED ) {
			break;
		}
	}
	return $out;
}

/**
 * A dropped term's filter in plain words.
 *
 * @param string $why bad-row, not-english, empty, db-error, or worker:<filter>.
 */
function aw_si_rising_drop_label( string $why ): string {
	$words = array(
		'bad-row'            => __( 'no date within the last 400 days, or not text (site)', 'insightweb' ),
		'not-english'        => __( 'not English: Hindi and Hinglish wait for a later phase (site)', 'insightweb' ),
		'empty'              => __( 'empty, or longer than 255 characters (site)', 'insightweb' ),
		'db-error'           => __( 'could not be stored (database error)', 'insightweb' ),
		'worker:exclude'     => __( 'has an exclude word, such as film, song or cricket (worker)', 'insightweb' ),
		'worker:vocabulary'  => __( 'names none of our species, topics or keywords (worker)', 'insightweb' ),
		'worker:not-english' => __( 'not English (worker)', 'insightweb' ),
		'worker:old'         => __( 'older than the days asked for (worker)', 'insightweb' ),
	);
	/* translators: %s: the filter's name as the worker sent it. */
	return $words[ $why ] ?? ( 0 === strpos( $why, 'worker:' ) ? sprintf( __( 'worker filter: %s', 'insightweb' ), substr( $why, 7 ) ) : $why );
}

/**
 * One source's latest run as counts: terms the source showed, terms the worker kept and sent, terms dropped and by
 * which filter (12 Nov 2026: "New (0)" for days with no way to see why). Workers send counts only; a per-term list
 * shows when the worker sends it, or when the site itself dropped a term.
 *
 * @param array $info The run's info.
 * @return array{seen: int|null, sent: int, worker_dropped: int|null, excluded: int, stored: int, terms: array}
 */
function aw_si_rising_drop_counts( array $info ): array {
	$w        = (array) ( $info['worker'] ?? array() );
	$sent     = (int) ( $info['rising'] ?? 0 );
	$excluded = (int) ( $w['excluded'] ?? 0 );
	$seen     = isset( $w['trending'] ) ? (int) $w['trending'] : null; // trends_rss: every item of the trending list
	$kept     = isset( $w['kept'] ) ? (int) $w['kept'] : null;         // kept by the vocabulary, before the exclude words
	$terms    = array_values( array_filter( (array) ( $info['dropped'] ?? array() ), 'is_array' ) );
	return array(
		'seen'           => $seen,
		'sent'           => $sent,
		'worker_dropped' => null !== $seen && null !== $kept ? max( 0, $seen - $kept ) : null,
		'excluded'       => $excluded,
		'stored'         => max( 0, $sent - count( array_filter( $terms, fn( $t ) => 0 !== strpos( (string) ( $t[1] ?? '' ), 'worker:' ) ) ) ),
		'terms'          => $terms,
	);
}

/** Rising tab section: each worker source's latest run, what it saw, kept and dropped, and why. */
function aw_si_rising_drops_section(): void {
	$runs = aw_si_runs();
	$rows = array();
	foreach ( aw_si_rising_sources() as $key => $s ) {
		$r = $runs[ 'rising_' . $key ] ?? array();
		if ( empty( $s['worker'] ) || empty( $r['ok'] ) || ( 'gdelt' === $key && ! aw_si_gdelt_on() ) ) {
			continue;
		}
		$rows[ $key ] = array( $s['label'], (int) $r['ok'], aw_si_rising_drop_counts( (array) ( $r['info'] ?? array() ) ) );
	}
	echo '<h2>' . esc_html__( 'Latest run per source: kept and dropped', 'insightweb' ) . '</h2>';
	if ( ! $rows ) {
		echo '<p>' . esc_html__( 'No source has sent terms yet.', 'insightweb' ) . '</p>';
		return;
	}
	echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Source', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'Run', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'Terms the source showed', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'Dropped by the worker', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'Sent and stored', 'insightweb' ) . '</th></tr></thead><tbody>';
	$terms = array();
	foreach ( $rows as $key => list( $label, $at, $c ) ) {
		$why = array();
		if ( null !== $c['worker_dropped'] ) {
			/* translators: %s: terms. */
			$why[] = sprintf( _n( '%s names none of our species, topics or keywords, or is not English or too old', '%s name none of our species, topics or keywords, or are not English or too old', (int) $c['worker_dropped'], 'insightweb' ), number_format_i18n( $c['worker_dropped'] ) );
		}
		if ( $c['excluded'] ) {
			/* translators: %s: terms. */
			$why[] = sprintf( _n( '%s has an exclude word (film, song, cricket …)', '%s have an exclude word (film, song, cricket …)', (int) $c['excluded'], 'insightweb' ), number_format_i18n( $c['excluded'] ) );
		}
		/* translators: 1: terms sent, 2: terms stored. */
		$sent = sprintf( __( '%1$s sent, %2$s stored', 'insightweb' ), number_format_i18n( $c['sent'] ), number_format_i18n( $c['stored'] ) );
		echo '<tr><td>' . esc_html( $label ) . '</td><td>' . esc_html( wp_date( 'j M, H:i', $at ) ) . '</td><td>' . esc_html( null === $c['seen'] ? '–' : number_format_i18n( $c['seen'] ) ) . '</td><td>' . esc_html( $why ? implode( '; ', $why ) : '–' ) . '</td><td>' . esc_html( $sent ) . '</td></tr>';
		foreach ( $c['terms'] as $t ) {
			$terms[] = array( $label, (string) ( $t[0] ?? '' ), aw_si_rising_drop_label( (string) ( $t[1] ?? '' ) ) );
		}
	}
	echo '</tbody></table>';
	if ( $terms ) {
		echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Source', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'Dropped term', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'Filter that dropped it', 'insightweb' ) . '</th></tr></thead><tbody>';
		foreach ( $terms as $t ) {
			echo '<tr><td>' . esc_html( $t[0] ) . '</td><td>' . esc_html( '' !== $t[1] ? $t[1] : '–' ) . '</td><td>' . esc_html( $t[2] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
	echo '<p class="description">' . esc_html__( 'The worker sends counts for its own filters; the terms it dropped are listed one by one only once it sends them. Terms the site itself refused are always listed. A term you saw elsewhere can be added by hand below.', 'insightweb' ) . '</p>';
}
