<?php
/**
 * Summary rewrite pass (owner, 18 Oct 2026): the workers rewrite every published summary that breaks the current
 * Summary rules, a batch at a time, and write each one here.
 *
 *   GET  aw/v1/summary-rewrite?skip=0&per_page=100   published News, Library and research items whose summary has not
 *        been settled yet (newest first): id, type, title, summary, md5, source_url, source_date, date, publisher, tries.
 *   GET  aw/v1/summary-rewrite?progress=1            counts by state, and the items that stayed below the rules.
 *   POST aw/v1/summary-rewrite {ok:[ids]}            summaries that already follow the rules (settled, nothing written).
 *   POST aw/v1/summary-rewrite {id, before_md5, summary, state: done|short, model, reason, also:[{name,url}]}
 *        write one rewrite: the old text goes to aw_summary_history, the new one straight to the row (post_excerpt
 *        only, so post_date and post_modified never move), entities and the search description follow, only this
 *        item's cache is purged.
 *   POST aw/v1/summary-rewrite {id, before_md5, failed:"reason"}   an attempt that produced nothing usable.
 *   POST aw/v1/summary-rewrite {id, before_md5, settle:"reason"}   the summary stays as it is: nothing better can be said.
 *   GET  aw/v1/summary-rewrite?short=1&per_page=10   'short' items due another try (owner, 25 Oct 2026), oldest first:
 *        why = "rules" when their section's word minimum changed since they were settled (one try per change), or
 *        "recheck" when their source has not been re-read for AW_SUMMARY_RW_RECHECK_DAYS (the worker re-reads it and
 *        rewrites only when it finds more text than src_chars; never padded). The worker asks for them only after the
 *        new and failed items, a few per run, so they never hold up new ones.
 *   POST aw/v1/summary-rewrite {id, before_md5, recheck: chars}   a short item's source re-read, nothing new: only the
 *        re-read day and the source length are kept.
 *   Writes and settles may carry src_chars (the length of the source text read); a 'short' state also keeps the
 *   section's minimum it was judged by (min_words).
 *
 * State lives in the meta aw_summary_rw = {state: ok|done|short|failed, md5, at, tries, model, reason}. A state counts
 * only while the summary still has the md5 it was settled with. Nothing is deleted.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function aw_summary_rw_clean( $id ) {
	return trim( str_replace( '[Needs legal review]', '', wp_strip_all_tags( (string) get_post_field( 'post_excerpt', $id, 'raw' ) ) ) );
}

/** Is this item's summary settled (a state recorded for exactly this text)? */
function aw_summary_rw_settled( $id, $excerpt ) {
	$m = get_post_meta( $id, 'aw_summary_rw', true );
	if ( ! is_array( $m ) || md5( (string) $excerpt ) !== ( $m['md5'] ?? '' ) ) {
		return false;
	}
	return in_array( $m['state'] ?? '', array( 'ok', 'done', 'short' ), true ) || ( 'failed' === ( $m['state'] ?? '' ) && (int) ( $m['tries'] ?? 0 ) >= 2 );
}

/** Days before a short item's source is read again. */
const AW_SUMMARY_RW_RECHECK_DAYS = 14;

/** The word minimum a short item was judged by: stored since 25 Oct 2026, before that read from its reason. */
function aw_summary_rw_short_min( array $m ) {
	if ( isset( $m['min_words'] ) ) {
		return (int) $m['min_words'];
	}
	return preg_match( '/needs at least (\d+) words/', (string) ( $m['reason'] ?? '' ), $x ) ? (int) $x[1] : 0;
}

/**
 * Why a settled short item is due another try ('rules' | 'recheck'), or ''.
 *
 * @param array  $m       Its aw_summary_rw meta (state short, md5 of the current summary).
 * @param string $section news|library|research.
 * @param int    $now     Timestamp.
 * @return string
 */
function aw_summary_rw_short_due( array $m, $section, $now ) {
	$min = (int) ( aw_summary_rules()['sections'][ $section ]['min_words'] ?? 0 );
	if ( $min && aw_summary_rw_short_min( $m ) !== $min && (int) ( $m['retry_min'] ?? -1 ) !== $min ) {
		return 'rules'; // the minimum changed since it was judged: one try per change
	}
	$last = strtotime( (string) ( $m['checked'] ?? $m['at'] ?? '' ) );
	return ( ! $last || $now - $last >= AW_SUMMARY_RW_RECHECK_DAYS * DAY_IN_SECONDS ) ? 'recheck' : '';
}

/** GET ?short=1: the short items due another try, oldest first (the day they were judged or re-read). */
function aw_summary_rw_short_list( $per ) {
	global $wpdb;
	$rows = $wpdb->get_results( "SELECT p.ID, p.post_type, p.post_title, p.post_excerpt, p.post_date, m.meta_value FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'aw_summary_rw' WHERE p.post_status = 'publish' AND p.post_type IN ('aw_news','aw_legal','post') AND m.meta_value LIKE '%short%'" ); // phpcs:ignore WordPress.DB
	$now  = strtotime( current_time( 'mysql' ) ); // the meta's times are local, as current_time( 'mysql' )
	$due  = array();
	foreach ( $rows as $r ) {
		$m = maybe_unserialize( $r->meta_value );
		if ( ! is_array( $m ) || 'short' !== ( $m['state'] ?? '' ) || md5( (string) $r->post_excerpt ) !== ( $m['md5'] ?? '' ) ) {
			continue;
		}
		$id  = (int) $r->ID;
		$sec = aw_summary_section( $id );
		$url = (string) get_post_meta( $id, 'aw_source_url', true );
		if ( '' === $sec || '' === $url ) {
			continue;
		}
		$why = aw_summary_rw_short_due( $m, $sec, $now );
		if ( '' === $why ) {
			continue;
		}
		$due[] = array(
			'id'          => $id,
			'type'        => $r->post_type,
			'section'     => $sec,
			'title'       => html_entity_decode( $r->post_title, ENT_QUOTES ),
			'summary'     => wp_strip_all_tags( $r->post_excerpt ),
			'md5'         => md5( (string) $r->post_excerpt ),
			'source_url'  => $url,
			'source_date' => (string) get_post_meta( $id, 'aw_source_date', true ),
			'date'        => substr( $r->post_date, 0, 10 ),
			'publisher'   => (string) get_post_meta( $id, 'aw_source_name', true ),
			'tries'       => 0,
			'why'         => $why,
			'src_chars'   => isset( $m['src_chars'] ) ? (int) $m['src_chars'] : null,
			'since'       => (string) ( $m['checked'] ?? $m['at'] ?? '' ),
		);
	}
	usort( $due, fn( $a, $b ) => ( 'rules' === $b['why'] ) <=> ( 'rules' === $a['why'] ) ?: strcmp( $a['since'], $b['since'] ) );
	return new WP_REST_Response(
		array(
			'items' => array_slice( $due, 0, $per ),
			'due'   => count( $due ),
		)
	);
}

function aw_summary_rw_set( $id, $excerpt, $state, $extra = array() ) {
	$old   = get_post_meta( $id, 'aw_summary_rw', true );
	$tries = ( is_array( $old ) && md5( (string) $excerpt ) === ( $old['md5'] ?? '' ) ) ? (int) ( $old['tries'] ?? 0 ) : 0;
	update_post_meta(
		$id,
		'aw_summary_rw',
		array_merge(
			array(
				'state' => $state,
				'md5'   => md5( (string) $excerpt ),
				'at'    => current_time( 'mysql' ),
				'tries' => 'failed' === $state ? $tries + 1 : $tries,
			),
			$extra
		)
	);
}

function aw_summary_rw_list( WP_REST_Request $req ) {
	global $wpdb;
	if ( $req->get_param( 'progress' ) ) {
		return aw_summary_rw_progress();
	}
	$per  = max( 1, min( 100, (int) ( $req->get_param( 'per_page' ) ?: 100 ) ) );
	if ( $req->get_param( 'short' ) ) {
		return aw_summary_rw_short_list( $per );
	}
	$skip = max( 0, (int) $req->get_param( 'skip' ) );
	$only = array_map( 'intval', (array) $req->get_param( 'ids' ) );
	$sql  = "SELECT ID, post_type, post_title, post_excerpt, post_date FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ('aw_news','aw_legal','post')";
	if ( $only ) {
		$sql .= ' AND ID IN (' . implode( ',', $only ) . ')';
	}
	$rows = $wpdb->get_results( $sql . ' ORDER BY post_date DESC, ID DESC' ); // phpcs:ignore WordPress.DB
	$out  = array();
	$at   = $skip;
	$n    = count( $rows );
	while ( $at < $n && count( $out ) < $per ) {
		$chunk = array_slice( $rows, $at, 50 );
		update_meta_cache( 'post', wp_list_pluck( $chunk, 'ID' ) );
		foreach ( $chunk as $r ) {
			++$at;
			$id = (int) $r->ID;
			if ( aw_summary_rw_settled( $id, $r->post_excerpt ) ) {
				continue;
			}
			$sec = aw_summary_section( $id );
			if ( '' === $sec || '' === (string) get_post_meta( $id, 'aw_source_url', true ) ) {
				continue; // our own content (templates, guides) has no source: never rewritten (20 Oct 2026: two letter templates got unrelated stories)
			}
			$m     = get_post_meta( $id, 'aw_summary_rw', true );
			$out[] = array(
				'id'          => $id,
				'type'        => $r->post_type,
				'section'     => $sec,
				'title'       => html_entity_decode( $r->post_title, ENT_QUOTES ),
				'summary'     => wp_strip_all_tags( $r->post_excerpt ),
				'md5'         => md5( (string) $r->post_excerpt ),
				'source_url'  => (string) get_post_meta( $id, 'aw_source_url', true ),
				'source_date' => (string) get_post_meta( $id, 'aw_source_date', true ),
				'date'        => substr( $r->post_date, 0, 10 ),
				'publisher'   => (string) get_post_meta( $id, 'aw_source_name', true ),
				'tries'       => ( is_array( $m ) && md5( (string) $r->post_excerpt ) === ( $m['md5'] ?? '' ) ) ? (int) ( $m['tries'] ?? 0 ) : 0,
			);
			if ( count( $out ) >= $per ) {
				break;
			}
		}
	}
	return new WP_REST_Response(
		array(
			'items' => $out,
			'next'  => $at < $n ? $at : null,
		)
	);
}

function aw_summary_rw_progress() {
	global $wpdb;
	$rows  = $wpdb->get_results( "SELECT ID, post_type, post_excerpt FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ('aw_news','aw_legal','post')" ); // phpcs:ignore WordPress.DB
	$count = array();
	$list  = array();
	foreach ( $rows as $r ) {
		$id  = (int) $r->ID;
		$sec = aw_summary_section( $id );
		if ( '' === $sec || '' === (string) get_post_meta( $id, 'aw_source_url', true ) ) {
			continue; // own content: not counted, never rewritten
		}
		$m  = get_post_meta( $id, 'aw_summary_rw', true );
		$st = ( is_array( $m ) && md5( (string) $r->post_excerpt ) === ( $m['md5'] ?? '' ) ) ? ( $m['state'] ?? 'open' ) : 'open';
		if ( 'failed' === $st && (int) ( $m['tries'] ?? 0 ) < 2 ) {
			$st = 'open';
		}
		$count[ $sec ][ $st ] = ( $count[ $sec ][ $st ] ?? 0 ) + 1;
		if ( in_array( $st, array( 'short', 'failed' ), true ) ) {
			$list[] = array(
				'id'      => $id,
				'section' => $sec,
				'state'   => $st,
				'words'   => aw_summary_words( aw_summary_rw_clean( $id ) ),
				'reason'  => (string) ( $m['reason'] ?? '' ),
			);
		}
	}
	return new WP_REST_Response(
		array(
			'counts'  => $count,
			'not_met' => $list,
		)
	);
}

function aw_summary_rw_post( WP_REST_Request $req ) {
	global $wpdb;
	$ok = (array) $req->get_param( 'ok' );
	if ( $ok ) {
		$n = 0;
		foreach ( $ok as $id ) {
			$id = (int) $id;
			if ( 'publish' === get_post_status( $id ) && '' !== aw_summary_section( $id ) ) {
				aw_summary_rw_set( $id, get_post_field( 'post_excerpt', $id, 'raw' ), 'ok' );
				++$n;
			}
		}
		return new WP_REST_Response( array( 'ok' => $n ) );
	}
	$id = (int) $req->get_param( 'id' );
	$p  = get_post( $id );
	$sec = $p ? aw_summary_section( $p ) : '';
	if ( ! $p || 'publish' !== $p->post_status || '' === $sec ) {
		return new WP_Error( 'aw_not_found', 'No such published item', array( 'status' => 404 ) );
	}
	if ( md5( (string) $p->post_excerpt ) !== (string) $req->get_param( 'before_md5' ) ) {
		return new WP_Error( 'aw_changed', 'The summary changed since it was read', array( 'status' => 409 ) );
	}
	$src_chars = null !== $req->get_param( 'src_chars' ) ? max( 0, (int) $req->get_param( 'src_chars' ) ) : null;
	$sec_min   = (int) ( aw_summary_rules()['sections'][ $sec ]['min_words'] ?? 0 );
	$short_by  = array( 'min_words' => $sec_min ) + ( null !== $src_chars ? array( 'src_chars' => $src_chars ) : array() );
	if ( null !== $req->get_param( 'recheck' ) ) { // a short item's source re-read and nothing new found: state kept
		$m = get_post_meta( $id, 'aw_summary_rw', true );
		if ( ! is_array( $m ) || 'short' !== ( $m['state'] ?? '' ) ) {
			return new WP_Error( 'aw_not_short', 'Only a short item is re-checked', array( 'status' => 409 ) );
		}
		$m['checked']   = current_time( 'mysql' );
		$m['src_chars'] = max( 0, (int) $req->get_param( 'recheck' ) );
		update_post_meta( $id, 'aw_summary_rw', $m );
		return new WP_REST_Response( array( 'id' => $id, 'state' => 'short', 'checked' => $m['checked'] ) );
	}
	$failed = trim( (string) $req->get_param( 'failed' ) );
	if ( '' !== $failed ) {
		aw_summary_rw_set( $id, $p->post_excerpt, 'failed', array( 'reason' => mb_substr( sanitize_text_field( $failed ), 0, 300 ) ) );
		return new WP_REST_Response( array( 'id' => $id, 'state' => 'failed' ) );
	}
	$settle = trim( (string) $req->get_param( 'settle' ) );
	if ( '' !== $settle ) { // the best that can be said from the source and other coverage is what is already there
		$old = get_post_meta( $id, 'aw_summary_rw', true );
		$was = is_array( $old ) && 'short' === ( $old['state'] ?? '' ) && md5( (string) $p->post_excerpt ) === ( $old['md5'] ?? '' ) ? $old : array();
		$keep = $was ? array_intersect_key( $was, array_flip( array( 'src_chars', 'model' ) ) ) : array();
		aw_summary_rw_set( $id, $p->post_excerpt, 'short', array_merge( $keep, array( 'reason' => mb_substr( sanitize_text_field( $settle ), 0, 300 ), 'checked' => current_time( 'mysql' ), 'retry_min' => $sec_min ), $short_by ) );
		return new WP_REST_Response( array( 'id' => $id, 'state' => 'short' ) );
	}
	$summary = trim( preg_replace( '/\s+/u', ' ', sanitize_textarea_field( (string) $req->get_param( 'summary' ) ) ) );
	$summary = function_exists( 'aw_whole_sentences' ) ? aw_whole_sentences( $summary ) : $summary; // never cut mid-sentence
	$state   = 'short' === $req->get_param( 'state' ) ? 'short' : 'done';
	$why     = aw_summary_problem( $summary, $sec, 'short' === $state );
	if ( '' !== $why || '' === $summary ) {
		return new WP_Error( 'aw_rules', $why ? $why : 'empty summary', array( 'status' => 422 ) );
	}
	$before   = (string) $p->post_excerpt;
	$old_desc = aw_summary_description( trim( str_replace( '[Needs legal review]', '', wp_strip_all_tags( $before ) ) ) );
	$suffix   = false !== strpos( $before, '[Needs legal review]' ) ? "\n[Needs legal review]" : '';
	$now      = current_time( 'mysql' );
	$hist     = array_values( array_filter( (array) get_post_meta( $id, 'aw_summary_history', true ) ) );
	$hist[]   = array(
		'summary' => $before,
		'at'      => $now,
		'reason'  => 'rewritten under the current summary rules (18 Oct 2026)',
	);
	update_post_meta( $id, 'aw_summary_history', $hist );
	$wpdb->update( $wpdb->posts, array( 'post_excerpt' => $summary . $suffix ), array( 'ID' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	clean_post_cache( $id );
	if ( 'short' === $state ) {
		update_post_meta( $id, 'aw_summary_short', current_time( 'Y-m-d' ) );
	} else {
		delete_post_meta( $id, 'aw_summary_short' );
	}
	foreach ( (array) $req->get_param( 'also' ) as $a ) {
		$url = esc_url_raw( (string) ( $a['url'] ?? '' ) );
		if ( '' === $url || ! in_array( $p->post_type, array( 'aw_news', 'aw_legal' ), true ) ) {
			continue;
		}
		$also = array_values( array_filter( (array) get_post_meta( $id, 'aw_also_reported', true ) ) );
		if ( $url !== (string) get_post_meta( $id, 'aw_source_url', true ) && ! in_array( $url, wp_list_pluck( $also, 'url' ), true ) ) {
			$also[] = array(
				'name' => sanitize_text_field( (string) ( $a['name'] ?? '' ) ) ?: preg_replace( '/^www\./', '', (string) wp_parse_url( $url, PHP_URL_HOST ) ),
				'url'  => $url,
			);
			update_post_meta( $id, 'aw_also_reported', $also );
		}
	}
	if ( function_exists( 'aw_summary_store_entities' ) ) {
		aw_summary_store_entities( $id );
	}
	// The search description is the new lead (Rank Math), unless an editor typed a different one.
	$desc = '';
	if ( function_exists( 'aw_seo_item_description' ) ) {
		$have = trim( (string) get_post_meta( $id, 'rank_math_description', true ) );
		$auto = (array) json_decode( (string) get_post_meta( $id, '_aw_seo_auto', true ), true );
		$desc = aw_seo_item_description( $id );
		if ( '' !== $desc && ( '' === $have || $have === $old_desc || $have === ( $auto['description'] ?? null ) || $have === $desc ) ) {
			update_post_meta( $id, 'rank_math_description', wp_slash( $desc ) );
			$auto['description'] = $desc;
			update_post_meta( $id, '_aw_seo_auto', wp_slash( wp_json_encode( $auto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
		}
	}
	aw_summary_rw_set(
		$id,
		$summary . $suffix,
		$state,
		array_merge(
			array(
				'model'  => sanitize_text_field( (string) $req->get_param( 'model' ) ),
				'reason' => mb_substr( sanitize_text_field( (string) $req->get_param( 'reason' ) ), 0, 300 ),
			),
			'short' === $state ? $short_by + array( 'checked' => current_time( 'mysql' ) ) : array()
		)
	);
	do_action( 'litespeed_purge_post', $id ); // this item only
	return new WP_REST_Response(
		array(
			'id'          => $id,
			'state'       => $state,
			'words'       => aw_summary_words( $summary ),
			'description' => $desc,
		)
	);
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'aw/v1',
			'/summary-rewrite',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => fn() => current_user_can( 'edit_posts' ),
					'callback'            => 'aw_summary_rw_list',
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => fn() => current_user_can( 'edit_posts' ),
					'callback'            => 'aw_summary_rw_post',
				),
			)
		);
	}
);
