<?php
/**
 * Suggestions from Search Insights (owner, 24 Oct 2026): Settings → Sourcing → Keywords lists searches people make that
 * no sourcing word covers, so the owner can add them to the Include or Exclude list by hand. Nothing is added by itself.
 *
 * - The searches come from aw-seo-extras (filter aw_search_demand_terms). Without it there are no suggestions and
 *   sourcing works as before.
 * - Shown: at least 20 impressions in the last 28 days (owner, 24 Oct 2026; Rising is not used for suggestions). Left out: brand searches, searches not in English
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

/** Dismissed searches (from phase 2: the records marked dismissed). */
function aw_sourcing_dismissed() {
	if ( function_exists( 'aw_sourcing_kw_on' ) && aw_sourcing_kw_on() ) {
		global $wpdb;
		$k = aw_si_table( 'keywords' );
		return array_map( 'aw_sourcing_suggest_norm', (array) $wpdb->get_col( "SELECT label FROM {$k} WHERE dismissed = 1" ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	}
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
	$rel       = function_exists( 'aw_sourcing_kw_on' ) && aw_sourcing_kw_on() ? aw_sourcing_kw_lists() : aw_relevance();
	$words     = array_merge( (array) ( $rel['strong'] ?? array() ), (array) ( $rel['exclude'] ?? array() ), (array) ( $rel['search'] ?? array() ) ); // a Search keyword covers it too (phase 2)
	$dismissed = array_flip( aw_sourcing_dismissed() );
	$dis_norm  = array_flip( array_map( 'aw_si_norm', array_keys( $dismissed ) ) );
	$out       = array();
	foreach ( $terms as $t ) {
		$q = aw_sourcing_suggest_norm( $t['q'] ?? '' );
		if ( '' === $q || ! empty( $t['brand'] ) || ! preg_match( '/^[\x20-\x7e]+$/', $q ) ) {
			continue;
		}
		if ( (int) ( $t['impr'] ?? 0 ) < 20 ) { // owner, 24 Oct 2026: 20 impressions in 28 days; Rising does not count until Search Insights has 8 weeks of history
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
			'dismissed' => isset( $dismissed[ $q ] ) || ( function_exists( 'aw_si_norm' ) && isset( $dis_norm[ aw_si_norm( $q ) ] ) ),
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
	if ( function_exists( 'aw_sourcing_kw_on' ) && aw_sourcing_kw_on() ) { // phase 2: roles and the dismissed flag on the record
		$n = 0;
		foreach ( $qs as $q ) {
			if ( in_array( $op, array( 'include', 'exclude', 'search' ), true ) ) {
				$bit = array( 'include' => AW_KW_ROLE_INCLUDE, 'exclude' => AW_KW_ROLE_EXCLUDE, 'search' => AW_KW_ROLE_SEARCH )[ $op ];
				$id  = aw_kw_id_by_norm( aw_si_norm( $q ) );
				$was = $id ? (int) aw_kw_get( $id )['roles'] : 0;
				$r   = aw_sourcing_kw_add( $q, array( 'roles' => $bit, 'sections' => AW_KW_ROLE_EXCLUDE === $bit ? array() : array( 'aw_news' ) ) );
				if ( ! is_wp_error( $r ) && ! ( $was & $bit ) ) {
					++$n;
					kits_sourcing_log( 'keywords', $q . ': ' . ucfirst( $op ) . ' (suggested by Search Insights)', '', ucfirst( $op ) );
				}
				continue;
			}
			$id = aw_kw_id_by_norm( aw_si_norm( $q ) );
			$id = $id ? $id : aw_kw_create( $q );
			$on = 'dismiss' === $op ? 1 : 0;
			if ( $id && true === aw_kw_update( $id, array( 'dismissed' => $on ) ) ) {
				++$n;
				kits_sourcing_log( 'keywords', $q . ': suggestion ' . ( $on ? 'dismissed' : 'shown again' ), (bool) ! $on, (bool) $on );
			}
		}
		return $n;
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
		if ( aw_sourcing_phase() >= 2 && ! ( function_exists( 'aw_sourcing_kw_on' ) && aw_sourcing_kw_on() ) ) { // phase 2 before the table is ready: never the old lists
			wp_die( 'The keyword table is not ready yet. Open Settings → Sourcing again in a moment.', 'Sourcing', array( 'response' => 409, 'back_link' => true ) );
		}
		$ops = array_merge( array( 'include', 'exclude' ), function_exists( 'aw_sourcing_kw_on' ) && aw_sourcing_kw_on() ? array( 'search' ) : array(), array( 'dismiss', 'restore' ) );
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
		$back = wp_get_referer() ? wp_get_referer() : admin_url( 'options-general.php?page=aw-sourcing&tab=keywords&kw=suggestions' );
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
		if ( 'keywords' === sanitize_key( $_GET['tab'] ?? '' ) && 'suggestions' === sanitize_key( $_GET['kw'] ?? '' ) && function_exists( 'aw_sourcing_si_available' ) && aw_sourcing_si_available() ) { // phpcs:ignore WordPress.Security.NonceVerification -- which tab is shown; only when there can be suggestions
			add_screen_option( 'per_page', array( 'label' => 'Suggestions per page', 'default' => 20, 'option' => 'aw_suggestions_per_page' ) );
		}
	}
);
add_filter( 'set_screen_option_aw_suggestions_per_page', fn( $keep, $option, $value ) => max( 1, min( 200, (int) $value ) ), 10, 3 );

/** Settings → Sourcing → Keywords → Suggestions (the tab's second view, &kw=suggestions, so its paging and sorting are its own). */
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
			$msg = array(
				/* translators: %d: number of searches or suggestions. */
				'include' => _n( '%d search added to Include. The workers search it from their next run.', '%d searches added to Include. The workers search them from their next run.', $n ),
				/* translators: %d: number of searches or suggestions. */
				'exclude' => _n( '%d search added to Exclude.', '%d searches added to Exclude.', $n ),
				/* translators: %d: number of searches or suggestions. */
				'search'  => _n( '%d search added as its own Google News search. The workers search it from their next run.', '%d searches added as their own Google News searches. The workers search them from their next run.', $n ),
				/* translators: %d: number of searches or suggestions. */
				'dismiss' => _n( '%d suggestion dismissed.', '%d suggestions dismissed.', $n ),
				/* translators: %d: number of searches or suggestions. */
				'restore' => _n( '%d suggestion shown again.', '%d suggestions shown again.', $n ),
			);
			if ( isset( $msg[ $done ] ) && $n ) {
				echo '<div class="notice notice-success is-dismissible inline"><p>' . esc_html( sprintf( $msg[ $done ], $n ) ) . '</p></div>';
			} else {
				echo '<div class="notice notice-warning is-dismissible inline"><p>Nothing changed: choose an action and at least one search.</p></div>';
			}
		}
		echo '<p>Searches people make (Search Console) with at least 20 impressions in the last 28 days that no Include or Exclude word covers. Brand and non-English searches are not listed. Nothing is added unless you add it.</p>';
		$table->views();
		echo '<form method="get"><input type="hidden" name="page" value="aw-sourcing"><input type="hidden" name="tab" value="keywords"><input type="hidden" name="kw" value="suggestions">';
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
