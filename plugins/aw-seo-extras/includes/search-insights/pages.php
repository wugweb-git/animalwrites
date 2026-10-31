<?php
/**
 * Pages tab (plan §8): one row per page over the last 28 days. Search Console clicks, impressions and position, the
 * keywords it owns and their conflicts, GA4 organic sessions and AI referrals, Clarity totals and the page-quality
 * flag, the latest PageSpeed scores and the owner's Clarity note. Added through the aw_si_tabs filter.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'aw_si_tabs',
	function ( $tabs ) {
		return array( 'pages' => __( 'Pages', 'aw-seo-extras' ) ) + (array) $tabs;
	},
	5
);

add_filter(
	'set_screen_option_aw_si_pages_per_page',
	function ( $keep, $option, $value ) {
		return max( 10, min( 500, (int) $value ) );
	},
	10,
	3
);

add_action(
	'aw_si_load_tab_pages',
	function () {
		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Pages per page', 'aw-seo-extras' ),
				'default' => 50,
				'option'  => 'aw_si_pages_per_page',
			)
		);
		require_once __DIR__ . '/class-aw-si-pages-table.php';
	}
);

/**
 * Everything the Pages tab shows, per site path, for the 28 days ending yesterday.
 *
 * @return array<string, array<string, mixed>> path => figures.
 */
function aw_si_pages_data(): array {
	global $wpdb;
	if ( ! aw_si_ready() ) {
		return array();
	}
	$o    = aw_si_table( 'obs' );
	$k    = aw_si_table( 'keywords' );
	$end  = ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( '-1 day' );
	$from = $end->modify( '-27 days' )->format( 'Y-m-d' );
	$to   = $end->format( 'Y-m-d' );
	$out  = array();
	$set  = function ( string $path, string $field, $value ) use ( &$out ) {
		if ( '' === $path ) {
			return;
		}
		$out[ $path ][ $field ] = $value;
	};
	// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names only.
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT page, SUM(clicks) c, SUM(impr) i, SUM(pos * impr) / NULLIF(SUM(impr), 0) p FROM {$o} WHERE src = 'gsc' AND metric = 'p' AND period = 'day' AND d BETWEEN %s AND %s GROUP BY page", $from, $to ) ) as $r ) {
		$set( $r->page, 'clicks', (int) $r->c );
		$set( $r->page, 'impr', (int) $r->i );
		$set( $r->page, 'pos', null === $r->p ? null : round( (float) $r->p, 1 ) );
	}
	// Keywords the page owns (keyword map or Rank Math focus), and how many of them are in conflict.
	$p = aw_si_table( 'kw_pages' );
	foreach ( (array) $wpdb->get_results( "SELECT l.path page, COUNT(DISTINCT l.kw_id) n, COUNT(DISTINCT IF(k.status = 'conflict', k.id, NULL)) c FROM {$p} l JOIN {$k} k ON k.id = l.kw_id WHERE l.role = 'owner' AND l.path LIKE '/%' GROUP BY l.path" ) as $r ) {
		$set( $r->page, 'keywords', (int) $r->n );
		$set( $r->page, 'conflicts', (int) $r->c );
	}
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT page, metric, txt, SUM(value) v FROM {$o} WHERE src = 'ga4' AND metric IN ('organic', 'ai') AND period = 'day' AND d BETWEEN %s AND %s GROUP BY page, metric, txt", $from, $to ) ) as $r ) {
		if ( 'organic' === $r->metric ) {
			$set( $r->page, 'organic', (int) $r->v );
			continue;
		}
		$set( $r->page, 'ai', (int) ( $out[ $r->page ]['ai'] ?? 0 ) + (int) $r->v );
		$out[ $r->page ]['ai_by'][ (string) $r->txt ] = (int) $r->v;
	}
	// Referral and social sessions (owner, 31 Oct 2026), without the GA4 test-event day.
	$skip = aw_si_ga4_skip_sql();
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT page, txt, SUM(value) v FROM {$o} WHERE src = 'ga4' AND metric = 'ref' AND period = 'day' AND d BETWEEN %s AND %s{$skip} GROUP BY page, txt", $from, $to ) ) as $r ) {
		$set( $r->page, 'ref', (int) ( $out[ $r->page ]['ref'] ?? 0 ) + (int) $r->v );
		$out[ $r->page ]['ref_by'][ (string) $r->txt ] = (int) $r->v;
	}
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT page, metric, SUM(value) s, AVG(value) a, MAX(value) m FROM {$o} WHERE src = 'clarity' AND page <> '' AND period = 'day' AND d BETWEEN %s AND %s GROUP BY page, metric", $from, $to ) ) as $r ) {
		$set( $r->page, 'c_' . $r->metric, 'sessions' === $r->metric ? (int) $r->s : ( 'flag' === $r->metric ? (int) $r->m : round( (float) $r->a, 1 ) ) );
	}
	// Latest PageSpeed run, latest Clarity note.
	foreach ( (array) $wpdb->get_results( "SELECT o.page, o.metric, o.value, o.d FROM {$o} o JOIN (SELECT page_key, metric, MAX(d) d FROM {$o} WHERE src = 'psi' AND metric IN ('score-mobile', 'score-desktop', 'flag') GROUP BY page_key, metric) l ON l.page_key = o.page_key AND l.metric = o.metric AND l.d = o.d WHERE o.src = 'psi'" ) as $r ) {
		$set( $r->page, 'psi_' . $r->metric, (float) $r->value );
		$set( $r->page, 'psi_d', $r->d );
	}
	foreach ( (array) $wpdb->get_results( "SELECT o.page, o.txt, o.d FROM {$o} o JOIN (SELECT page_key, MAX(d) d FROM {$o} WHERE src = 'clarity-note' GROUP BY page_key) l ON l.page_key = o.page_key AND l.d = o.d WHERE o.src = 'clarity-note'" ) as $r ) {
		$set( $r->page, 'note', (string) $r->txt );
		$set( $r->page, 'note_d', $r->d );
	}
	// phpcs:enable
	// Rank Math SEO score of the item at that address (rankmath.php).
	foreach ( aw_si_rankmath_scores() as $path => $score ) {
		if ( isset( $out[ $path ] ) ) {
			$out[ $path ]['rm_score'] = $score;
		}
	}
	foreach ( $out as $path => $f ) {
		$out[ $path ]['path']    = $path;
		$out[ $path ]['flagged'] = ! empty( $f['c_flag'] ) || ! empty( $f['psi_flag'] );
	}
	return $out;
}

add_action(
	'aw_si_tab_pages',
	function () {
		if ( isset( $_GET['noted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- message only.
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Clarity note saved.', 'aw-seo-extras' ) . '</p></div>';
		}
		if ( isset( $_GET['not_noted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- message only.
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'The note was empty or the page unknown, so nothing was saved.', 'aw-seo-extras' ) . '</p></div>';
		}
		if ( ! aw_si_clarity_on() ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Clarity figures start once the Clarity token is added under Animal Writes → Keys as AW_CLARITY_TOKEN (Clarity → Settings → Data Export → Generate new API token).', 'aw-seo-extras' ) . '</p></div>';
		}
		$note = isset( $_GET['note'] ) ? aw_si_path( home_url( sanitize_text_field( wp_unslash( $_GET['note'] ) ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- opens a form only.
		if ( '' !== $note ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_si_note"><input type="hidden" name="path" value="' . esc_attr( $note ) . '">';
			wp_nonce_field( 'aw_si_note' );
			echo '<table class="form-table" role="presentation"><tbody><tr><th scope="row"><label for="aw-si-note">';
			/* translators: %s: page path. */
			echo esc_html( sprintf( __( 'Clarity note for %s', 'aw-seo-extras' ), $note ) );
			echo '</label></th><td><textarea id="aw-si-note" name="note" rows="3" class="large-text" maxlength="255"></textarea>';
			echo '<p class="description">' . esc_html__( 'A short summary of what the recordings show (Clarity Copilot or your own), dated today. Never paste personal data.', 'aw-seo-extras' ) . '</p>';
			submit_button( __( 'Save note', 'aw-seo-extras' ), 'primary', 'submit', false );
			echo ' <a class="button" href="' . esc_url( aw_si_url( 'pages' ) ) . '">' . esc_html__( 'Cancel', 'aw-seo-extras' ) . '</a></td></tr></tbody></table></form>';
		}
		$table = new AW_SI_Pages_Table();
		$table->prepare_items();
		$table->views();
		echo '<form method="get"><input type="hidden" name="page" value="' . esc_attr( AW_SI_SLUG ) . '"><input type="hidden" name="tab" value="pages">';
		$table->search_box( __( 'Search pages', 'aw-seo-extras' ), 'aw-si-pages' );
		$table->display();
		echo '</form>';
	}
);
