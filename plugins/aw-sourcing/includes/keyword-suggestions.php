<?php
/**
 * Suggestions from Search Insights (owner, 24 Oct 2026): Settings → Sourcing → Keywords lists searches people make that
 * no sourcing word covers, so the owner can add them to the Include or Exclude list by hand. Nothing is added by itself.
 *
 * - The searches come from aw-seo-extras (filter aw_search_demand_terms). Without it there are no suggestions and
 *   sourcing works as before.
 * - Shown: at least 20 impressions in the last 28 days, or Rising. Left out: brand searches, searches not in English
 *   (they never become Google News searches; Search Insights shows them), and searches an Include or Exclude word
 *   already matches, as the keyword check matches a headline.
 * - A core list table: views All / Dismissed, search, sortable columns, pagination with a screen option, row and bulk
 *   actions (Add to Include, Add to Exclude, Dismiss, Show again) posting to admin-post.php with a nonce.
 * - Dismissed searches: option aw_sourcing_dismissed (not autoloaded).
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SOURCING_DISMISSED = 'aw_sourcing_dismissed';

/** Lowercase, single-spaced. */
function aw_sourcing_suggest_norm( $s ) {
	return trim( (string) preg_replace( '/\s+/u', ' ', mb_strtolower( (string) $s ) ) );
}

/** Dismissed searches. */
function aw_sourcing_dismissed() {
	return array_values( array_filter( (array) get_option( AW_SOURCING_DISMISSED, array() ), 'is_string' ) );
}

/** Google News searches per run: the English Include words and the workers' fixed "stray dogs" (run.py EXTRA_SEARCHES). */
function aw_sourcing_suggest_search_count() {
	if ( function_exists( 'aw_sourcing_searches' ) ) {
		return count( aw_sourcing_searches() );
	}
	$ids = array();
	foreach ( array_merge( (array) ( aw_relevance()['strong'] ?? array() ), array( 'stray dogs' ) ) as $t ) {
		if ( '' !== trim( (string) $t ) && preg_match( '/^[\x20-\x7e]+$/', (string) $t ) ) {
			$ids[ trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $t ) ), '-' ) ] = true;
		}
	}
	return count( $ids );
}

/**
 * Searches with demand that no sourcing word covers.
 *
 * @return array|null Rows (q, impr, clicks, pos, rising, dismissed), or null when aw-seo-extras gives no data.
 */
function aw_sourcing_suggestions() {
	$terms = apply_filters( 'aw_search_demand_terms', null );
	if ( ! is_array( $terms ) ) {
		return null;
	}
	$rel       = aw_relevance();
	$words     = array_merge( (array) ( $rel['strong'] ?? array() ), (array) ( $rel['exclude'] ?? array() ) );
	$dismissed = array_flip( aw_sourcing_dismissed() );
	$out       = array();
	foreach ( $terms as $t ) {
		$q = aw_sourcing_suggest_norm( $t['q'] ?? '' );
		if ( '' === $q || ! empty( $t['brand'] ) || ! preg_match( '/^[\x20-\x7e]+$/', $q ) ) {
			continue;
		}
		if ( (int) ( $t['impr'] ?? 0 ) < 20 && empty( $t['rising'] ) ) {
			continue;
		}
		foreach ( $words as $w ) {
			if ( aw_has_term( $q, $w ) ) {
				continue 2;
			}
		}
		$out[ $q ] = array(
			'q'         => $q,
			'impr'      => (int) ( $t['impr'] ?? 0 ),
			'clicks'    => (int) ( $t['clicks'] ?? 0 ),
			'pos'       => (float) ( $t['pos'] ?? 0 ),
			'rising'    => ! empty( $t['rising'] ),
			'dismissed' => isset( $dismissed[ $q ] ),
		);
	}
	return array_values( $out );
}

/** Do one action on some searches; returns how many changed. */
function aw_sourcing_suggest_apply( $op, array $qs ) {
	$qs = array_values( array_unique( array_filter( array_map( 'aw_sourcing_suggest_norm', $qs ) ) ) );
	if ( ! $qs ) {
		return 0;
	}
	if ( 'include' === $op || 'exclude' === $op ) {
		$rel   = aw_relevance();
		$list  = 'include' === $op ? 'strong' : 'exclude';
		$have  = array_map( 'aw_sourcing_suggest_norm', (array) ( $rel[ $list ] ?? array() ) );
		$added = array_values( array_diff( $qs, $have ) );
		if ( $added ) {
			$rel[ $list ] = array_merge( (array) ( $rel[ $list ] ?? array() ), $added );
			update_option( AW_REL_OPTION, $rel, false );
			kits_sourcing_log( 'keywords', ( 'include' === $op ? 'Include' : 'Exclude' ) . ': added ' . implode( ', ', $added ) . ' (suggested by Search Insights)' );
			delete_transient( 'aw_sourcing_kw_stats_30' );
		}
		return count( $added );
	}
	$d   = aw_sourcing_dismissed();
	$new = 'dismiss' === $op ? array_values( array_unique( array_merge( $d, $qs ) ) ) : array_values( array_diff( $d, $qs ) );
	update_option( AW_SOURCING_DISMISSED, $new, false );
	return abs( count( $new ) - count( $d ) );
}

add_action(
	'admin_post_aw_sourcing_suggest',
	function () {
		if ( ! current_user_can( AW_SRC_CAP ) || ! check_admin_referer( 'bulk-suggestions' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$ops = array( 'include', 'exclude', 'dismiss', 'restore' );
		$op  = '';
		$qs  = array();
		$one = sanitize_text_field( wp_unslash( $_POST['aw_suggest'] ?? '' ) ); // a row action: "op:search"
		if ( false !== strpos( $one, ':' ) ) {
			list( $op, $q ) = explode( ':', $one, 2 );
			$qs             = array( $q );
		} else { // bulk action, from the top or the bottom of the table
			$op = sanitize_key( wp_unslash( $_POST['aw_bulk'] ?? '-1' ) );
			$op = '-1' === $op || '' === $op ? sanitize_key( wp_unslash( $_POST['aw_bulk2'] ?? '' ) ) : $op;
			$qs = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['q'] ?? array() ) );
		}
		$back = wp_get_referer() ? wp_get_referer() : admin_url( 'options-general.php?page=aw-sourcing&tab=keywords' );
		$back = remove_query_arg( array( 'aw_suggested', 'aw_n', 'updated' ), $back );
		if ( ! in_array( $op, $ops, true ) && isset( $_POST['paged'] ) ) { // Enter in the page number box: go to that page
			wp_safe_redirect( add_query_arg( 'paged', max( 1, (int) $_POST['paged'] ), $back ) . '#aw-suggestions' );
			exit;
		}
		$n = in_array( $op, $ops, true ) ? aw_sourcing_suggest_apply( $op, $qs ) : 0;
		wp_safe_redirect( add_query_arg( array( 'aw_suggested' => in_array( $op, $ops, true ) ? $op : 'none', 'aw_n' => $n ), $back ) . '#aw-suggestions' );
		exit;
	}
);

// The result notice's arguments are dropped from the list's own links (sorting, paging, views), as core does.
add_filter( 'removable_query_args', fn( $args ) => array_merge( $args, array( 'aw_suggested', 'aw_n' ) ) );

// Screen options: suggestions per page.
add_action(
	'load-settings_page_aw-sourcing',
	function () {
		if ( 'keywords' === sanitize_key( $_GET['tab'] ?? '' ) && has_filter( 'aw_search_demand_terms' ) ) { // only when there can be suggestions // phpcs:ignore WordPress.Security.NonceVerification
			add_screen_option( 'per_page', array( 'label' => 'Suggestions per page', 'default' => 20, 'option' => 'aw_suggestions_per_page' ) );
		}
	}
);
add_filter( 'set_screen_option_aw_suggestions_per_page', fn( $keep, $option, $value ) => max( 1, min( 200, (int) $value ) ), 10, 3 );

/** Settings → Sourcing → Keywords: the suggestions, under the lists. */
add_action(
	'aw_sourcing_keywords_tab_end',
	function () {
		$rows = aw_sourcing_suggestions();
		if ( null === $rows ) {
			return; // aw-seo-extras is off: nothing to suggest
		}
		if ( ! class_exists( 'WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}
		require_once __DIR__ . '/class-aw-sourcing-suggestions-table.php';
		$table = new AW_Sourcing_Suggestions_Table( $rows );
		$table->prepare_items();

		echo '<h2 id="aw-suggestions">Suggestions from Search Insights</h2>';
		$done = sanitize_key( $_GET['aw_suggested'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( $done ) {
			$n   = absint( wp_unslash( $_GET['aw_n'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification
			/* translators: %d: number of searches or suggestions. */
			$msg = array(
				'include' => _n( '%d search added to Include. The workers search it from their next run.', '%d searches added to Include. The workers search them from their next run.', $n ),
				'exclude' => _n( '%d search added to Exclude.', '%d searches added to Exclude.', $n ),
				'dismiss' => _n( '%d suggestion dismissed.', '%d suggestions dismissed.', $n ),
				'restore' => _n( '%d suggestion shown again.', '%d suggestions shown again.', $n ),
			);
			if ( isset( $msg[ $done ] ) && $n ) {
				echo '<div class="notice notice-success is-dismissible inline"><p>' . esc_html( sprintf( $msg[ $done ], $n ) ) . '</p></div>';
			} else {
				echo '<div class="notice notice-warning is-dismissible inline"><p>Nothing changed: choose an action and at least one search.</p></div>';
			}
		}
		echo '<p>Searches people make (Search Console) with at least 20 impressions in the last 28 days, or Rising, that no Include or Exclude word covers. Brand and non-English searches are not listed. Nothing is added unless you add it.</p>';
		$table->views();
		echo '<form method="get"><input type="hidden" name="page" value="aw-sourcing"><input type="hidden" name="tab" value="keywords">';
		if ( $table->is_dismissed_view() ) {
			echo '<input type="hidden" name="suggestions" value="dismissed">';
		}
		$table->search_box( 'Search suggestions', 'aw-suggestion' );
		echo '</form>';
		// The list table prints its own nonce (bulk-suggestions) in this form; the handler checks it.
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_sourcing_suggest">';
		$table->display();
		echo '</form>';
	}
);
