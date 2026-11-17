<?php
/**
 * Internal links, phase 2: the link audit (docs/rankweb-internal-links.md).
 *
 * The scheduled job aw_link_audit (daily, or at once from the screen) works in slices of about 20 seconds and carries on
 * in a one-off event until it is done, so no browser tab has to stay open:
 *   1. collect  each published item of the linked post types is rendered, linked as a visitor would see it (the index
 *               and the settings of the auto-linker) and its links are recorded in {prefix}aw_links (auto and manual);
 *   2. check    addresses on this site that no item owns are asked for once (HEAD, no redirect followed, at most 20
 *               seconds per slice; an answer is kept six days): 404 and 410 are broken, 3xx is a redirect;
 *   3. analyse  AW_Link_Audit (class-aw-link-audit.php) works out links in and out, clicks from the home page, orphans and
 *               the findings of each item, and the result is stored in {prefix}aw_link_audit (one row per item).
 * The home page and the menus (menus, block navigation and template parts saved in the database) are the first click.
 * Rank Math's link counter is Rank Math's: not read or written here.
 *
 * Screen: RankWeb → Internal links (a core list table). REST under rankweb/v1:
 *   GET  links          manage_rankweb  the audit's rows: flag, page, per_page
 *   GET  links/summary  manage_rankweb  the counts and the state of the audit
 *   POST links/audit    manage_options  start an audit now
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-aw-link-audit.php';
require_once __DIR__ . '/admin-table.php';

const AW_LINK_AUDIT_JOB     = 'aw_link_audit';
const AW_LINK_AUDIT_DB      = 'aw_link_audit_db';
const AW_LINK_AUDIT_DB_VER  = '1';
const AW_LINK_AUDIT_STATE   = 'aw_link_audit_state';
const AW_LINK_AUDIT_SUMMARY = 'aw_link_audit_summary';
const AW_LINK_AUDIT_CHECKS  = 'aw_link_audit_checks';
const AW_LINK_AUDIT_SLICE   = 20; // seconds of work in one run of the job
const AW_LINK_AUDIT_KEEP    = 6 * DAY_IN_SECONDS; // an address answer is kept this long

/* ---------- The table ---------- */

/** Create the audit table (its own version option, so the auto-linker's tables are never gated by it). */
function aw_link_audit_install(): void {
	if ( get_option( AW_LINK_AUDIT_DB ) === AW_LINK_AUDIT_DB_VER ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$t = aw_link_table( 'audit' );
	$c = $wpdb->get_charset_collate();
	dbDelta(
		"CREATE TABLE {$t} (
		post_id bigint(20) unsigned NOT NULL DEFAULT 0,
		links_in int(10) unsigned NOT NULL DEFAULT 0,
		links_out int(10) unsigned NOT NULL DEFAULT 0,
		depth smallint(5) unsigned NOT NULL DEFAULT 0,
		flags varchar(191) NOT NULL DEFAULT '',
		detail text NOT NULL,
		checked datetime NOT NULL,
		PRIMARY KEY  (post_id)
		) {$c};"
	);
	update_option( AW_LINK_AUDIT_DB, AW_LINK_AUDIT_DB_VER, true );
}
add_action( 'admin_init', 'aw_link_audit_install' );

/** The findings and their texts. */
function aw_link_audit_labels(): array {
	return array(
		'orphan'    => __( 'Orphan: no link in', 'aw-seo-extras' ),
		'deep'      => __( 'Too many clicks from the home page', 'aw-seo-extras' ),
		'too_many'  => __( 'Too many links in the item', 'aw-seo-extras' ),
		'broken'    => __( 'Broken link', 'aw-seo-extras' ),
		'redirect'  => __( 'Link through a redirect', 'aw-seo-extras' ),
		'nofollow'  => __( 'Internal link set to nofollow', 'aw-seo-extras' ),
		'canonical' => __( 'Link to an item whose canonical is elsewhere', 'aw-seo-extras' ),
		'avoided'   => __( 'Link to a page on the do-not-link list', 'aw-seo-extras' ),
		'silo'      => __( 'Link into another silo\'s supporting item', 'aw-seo-extras' ),
	);
}

/* ---------- The job ---------- */

/** IDs of the items to audit: published, no password, a linked type, not left out. */
function aw_link_audit_ids(): array {
	global $wpdb;
	$types = aw_link_types();
	if ( ! $types ) {
		return array();
	}
	$in  = implode( ',', array_fill( 0, count( $types ), '%s' ) );
	$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one list per audit, kept in the audit's state.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders -- $in is a list of %s.
		$wpdb->prepare( "SELECT ID FROM %i WHERE post_status = 'publish' AND post_password = '' AND post_type IN ( {$in} ) ORDER BY ID", array_merge( array( $wpdb->posts ), $types ) )
	);
	$skip = array_flip( aw_link_excluded() );
	return array_values( array_filter( array_map( 'intval', (array) $ids ), fn( $id ) => ! isset( $skip[ $id ] ) ) );
}

/**
 * The job: one slice of the audit. Starts a new audit when none is running; carries on in a one-off event until done.
 *
 * @param string $why '' (daily), 'now' (asked for on the screen) or 'more' (the next slice).
 */
function aw_link_audit_job( $why = '' ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- the event's argument ('now' or 'more') is for the schedule, not for the work
	if ( get_transient( 'aw_link_audit_lock' ) ) {
		return; // another slice is running
	}
	set_transient( 'aw_link_audit_lock', 1, 2 * MINUTE_IN_SECONDS );
	aw_link_install();
	aw_link_audit_install();
	if ( function_exists( 'aw_silo_install' ) ) {
		aw_silo_install();
	}
	$state = get_option( AW_LINK_AUDIT_STATE );
	if ( ! is_array( $state ) || empty( $state['phase'] ) ) {
		$state = array(
			'phase'   => 'collect',
			'ids'     => aw_link_audit_ids(),
			'pos'     => 0,
			'started' => time(),
		);
	}
	$end = microtime( true ) + AW_LINK_AUDIT_SLICE;
	if ( 'collect' === $state['phase'] ) {
		$state = aw_link_audit_collect( $state, $end );
	}
	if ( 'check' === $state['phase'] ) {
		$state = aw_link_audit_check( $state, $end );
	}
	if ( 'analyse' === $state['phase'] ) {
		aw_link_audit_analyse( (array) $state['ids'], (int) $state['started'] );
		delete_option( AW_LINK_AUDIT_STATE );
	} else {
		update_option( AW_LINK_AUDIT_STATE, $state, false );
		if ( ! wp_next_scheduled( AW_LINK_AUDIT_JOB, array( 'more' ) ) ) {
			wp_schedule_single_event( time() + 30, AW_LINK_AUDIT_JOB, array( 'more' ) );
		}
	}
	delete_transient( 'aw_link_audit_lock' );
}
add_action( AW_LINK_AUDIT_JOB, 'aw_link_audit_job' );

add_action(
	'init',
	function () {
		if ( ( is_admin() || wp_doing_cron() ) && ! wp_next_scheduled( AW_LINK_AUDIT_JOB ) ) {
			wp_schedule_event( time() + 20 * MINUTE_IN_SECONDS, 'daily', AW_LINK_AUDIT_JOB );
		}
	}
);

/**
 * Step 1: render each item as a visitor sees it, link it, record its links.
 *
 * @param array $state The audit's state.
 * @param float $end   When the slice ends (microtime).
 */
function aw_link_audit_collect( array $state, float $end ): array {
	$ids   = (array) $state['ids'];
	$total = count( $ids );
	while ( (int) $state['pos'] < $total && microtime( true ) < $end ) {
		$id   = (int) $ids[ (int) $state['pos'] ];
		$post = get_post( $id );
		++$state['pos'];
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
			continue;
		}
		$html = aw_link_audit_render( $post );
		aw_link_store( $id, aw_link_run( $html, $id ) );
	}
	if ( (int) $state['pos'] >= $total ) {
		$state['phase'] = 'check';
	}
	return $state;
}

/**
 * An item's content as the page shows it.
 *
 * @param WP_Post $post Item.
 */
function aw_link_audit_render( WP_Post $post ): string {
	// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the content filters read the global post, as on a page view; restored below.
	$GLOBALS['post'] = $post;
	setup_postdata( $post );
	$html = (string) apply_filters( 'the_content', $post->post_content );
	wp_reset_postdata();
	return $html;
}

/** The addresses on this site that no item owns, from the links table (without their #fragment). */
function aw_link_audit_unowned(): array {
	global $wpdb;
	$rows = (array) $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT url FROM %i WHERE target_id = 0 AND origin = 'manual'", aw_link_table( 'links' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- RankWeb's own table, once per audit.
	$out  = array();
	foreach ( $rows as $url ) {
		$url = (string) strtok( (string) $url, '#' );
		if ( '' !== $url ) {
			$out[ $url ] = true;
		}
	}
	return array_keys( $out );
}

/**
 * Step 2: ask for each unowned address once. An answer: ok, redirect, broken or unknown (an error or a refusal is
 * never counted as broken).
 *
 * @param array $state The audit's state.
 * @param float $end   When the slice ends (microtime).
 */
function aw_link_audit_check( array $state, float $end ): array {
	$checks = (array) get_option( AW_LINK_AUDIT_CHECKS, array() );
	$now    = time();
	$urls   = aw_link_audit_unowned();
	$checks = array_intersect_key( $checks, array_flip( $urls ) ); // addresses no longer linked are forgotten
	foreach ( $urls as $url ) {
		if ( microtime( true ) >= $end ) {
			update_option( AW_LINK_AUDIT_CHECKS, $checks, false );
			return $state; // the next slice carries on
		}
		if ( isset( $checks[ $url ] ) && $now - (int) $checks[ $url ]['at'] < AW_LINK_AUDIT_KEEP ) {
			continue;
		}
		$res = wp_safe_remote_head(
			$url,
			array(
				'timeout'     => 5,
				'redirection' => 0,
			)
		);
		if ( ! is_wp_error( $res ) && 405 === (int) wp_remote_retrieve_response_code( $res ) ) {
			$res = wp_safe_remote_get(
				$url,
				array(
					'timeout'     => 5,
					'redirection' => 0,
				)
			);
		}
		$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		if ( $code >= 200 && $code < 300 ) {
			$status = 'ok';
		} elseif ( $code >= 300 && $code < 400 ) {
			$status = 'redirect';
		} elseif ( 404 === $code || 410 === $code ) {
			$status = 'broken';
		} else {
			$status = 'unknown';
		}
		$checks[ $url ] = array(
			's'  => $status,
			'at' => $now,
		);
	}
	update_option( AW_LINK_AUDIT_CHECKS, $checks, false );
	$state['phase'] = 'analyse';
	return $state;
}

/**
 * The first click: items the home page and the menus link to.
 *
 * @return int[] Item IDs.
 */
function aw_link_audit_roots(): array {
	$urls   = array();
	$take   = function ( $html ) use ( &$urls ) {
		if ( ! is_string( $html ) || '' === $html || ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
			return;
		}
		$p = new WP_HTML_Tag_Processor( $html );
		while ( $p->next_tag( 'a' ) ) {
			$href = $p->get_attribute( 'href' );
			if ( is_string( $href ) && '' !== $href ) {
				$urls[] = $href;
			}
		}
	};
	$blocks = function ( array $nodes ) use ( &$blocks, &$urls, $take ) {
		foreach ( $nodes as $b ) {
			if ( ! empty( $b['attrs']['url'] ) && is_string( $b['attrs']['url'] ) ) {
				$urls[] = $b['attrs']['url'];
			}
			$take( (string) ( $b['innerHTML'] ?? '' ) );
			if ( ! empty( $b['innerBlocks'] ) ) {
				$blocks( (array) $b['innerBlocks'] );
			}
		}
	};
	// The front page, when it is a page.
	$front = 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 0;
	if ( $front ) {
		$post = get_post( $front );
		if ( $post instanceof WP_Post ) {
			$take( aw_link_audit_render( $post ) );
		}
	}
	// Menus, block navigation and template parts saved in the database.
	foreach ( (array) wp_get_nav_menus() as $menu ) {
		foreach ( (array) wp_get_nav_menu_items( $menu ) as $mi ) {
			if ( ! empty( $mi->url ) ) {
				$urls[] = (string) $mi->url;
			}
		}
	}
	global $wpdb;
	$parts = (array) $wpdb->get_col( $wpdb->prepare( "SELECT post_content FROM %i WHERE post_status = 'publish' AND post_type IN ( 'wp_navigation', 'wp_template_part' )", $wpdb->posts ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- a few rows, once per audit.
	foreach ( $parts as $content ) {
		$blocks( parse_blocks( (string) $content ) );
	}
	$ids  = array();
	$home = home_url();
	foreach ( array_unique( $urls ) as $url ) {
		$abs = 0 === strpos( $url, '/' ) && 0 !== strpos( $url, '//' ) ? home_url( $url ) : $url;
		if ( '' === AW_Linker::url_key( $abs, $home ) || 0 !== strpos( AW_Linker::url_key( $abs, $home ) . '/', AW_Linker::url_key( $home ) . '/' ) ) {
			continue; // another site
		}
		$id = (int) url_to_postid( $abs );
		if ( $id > 0 ) {
			$ids[ $id ] = true;
		}
	}
	return array_keys( $ids );
}

/**
 * Step 3: work out the findings and store them.
 *
 * @param array $ids     Item IDs audited.
 * @param int   $started When the audit started.
 */
function aw_link_audit_analyse( array $ids, int $started ): void {
	global $wpdb;
	$items = array();
	$link  = array();
	foreach ( $ids as $id ) {
		$post = get_post( (int) $id );
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
			continue;
		}
		$perma              = (string) get_permalink( $post );
		$canon              = (string) get_post_meta( (int) $id, 'rank_math_canonical_url', true );
		$same               = '' === $canon || AW_Linker::url_key( $canon, home_url() ) === AW_Linker::url_key( $perma, home_url() );
		$link[ (int) $id ]  = $perma;
		$items[ (int) $id ] = array(
			'url'       => $perma,
			'canonical' => $same ? '' : $canon,
		);
	}
	$checks = (array) get_option( AW_LINK_AUDIT_CHECKS, array() );
	$edges  = array();
	$rows   = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT source_id, target_id, url, rel FROM %i', aw_link_table( 'links' ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- RankWeb's own table, once per audit.
	foreach ( $rows as $r ) {
		$src = (int) $r['source_id'];
		$tgt = (int) $r['target_id'];
		if ( ! isset( $items[ $src ] ) ) {
			continue;
		}
		$url    = (string) $r['url'];
		$status = '';
		if ( 0 === $tgt ) {
			$c      = $checks[ (string) strtok( $url, '#' ) ]['s'] ?? '';
			$status = in_array( $c, array( 'broken', 'redirect' ), true ) ? $c : '';
		} elseif ( isset( $link[ $tgt ] ) && AW_Linker::url_key( $url, home_url() ) !== AW_Linker::url_key( $link[ $tgt ], home_url() ) ) {
			$status = 'redirect'; // written with an old address: the visitor is sent on
		}
		$edges[] = array(
			'source' => $src,
			'target' => $tgt,
			'url'    => $url,
			'rel'    => (string) $r['rel'],
			'status' => $status,
		);
	}
	// Silo links are shown on the page, not saved in the content: they count as links here.
	$silos = function_exists( 'aw_silo_data' ) ? aw_silo_data() : array(
		'silos' => array(),
		'map'   => array(),
	);
	foreach ( $silos['silos'] as $s ) {
		foreach ( array_merge( array( $s['hub'] ), $s['items'] ) as $member ) {
			if ( ! isset( $items[ $member ] ) ) {
				continue;
			}
			foreach ( AW_Silo::links( $s['mode'], $s['hub'], $s['items'], (int) $member, max( 1, (int) AW_SILO_MAX ) ) as $tid ) {
				$edges[] = array(
					'source' => (int) $member,
					'target' => (int) $tid,
					'url'    => (string) get_permalink( $tid ),
					'rel'    => '',
					'status' => '',
				);
			}
		}
	}
	$result = AW_Link_Audit::analyze(
		$items,
		$edges,
		aw_link_audit_roots(),
		array(
			'max_depth' => max( 1, (int) AW_LINK_AUDIT_DEPTH ),
			'max_links' => max( 1, (int) AW_LINK_AUDIT_MAX ),
			'avoid'     => (array) AW_LINK_AUDIT_AVOID,
			'silo'      => $silos['map'],
		)
	);
	$table  = aw_link_table( 'audit' );
	$now    = current_time( 'mysql', true );
	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- RankWeb's own table, replaced whole.
	foreach ( array_chunk( $result, 200, true ) as $chunk ) {
		$sql  = array();
		$args = array( $table );
		foreach ( $chunk as $id => $r ) {
			$sql[] = '(%d,%d,%d,%d,%s,%s,%s)';
			array_push( $args, $id, $r['in'], $r['out'], (int) $r['depth'], $r['flags'] ? ',' . implode( ',', $r['flags'] ) . ',' : '', (string) wp_json_encode( (object) $r['detail'] ), $now );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders -- RankWeb's own table; the placeholders are built above.
		$wpdb->query( $wpdb->prepare( 'INSERT INTO %i (post_id, links_in, links_out, depth, flags, detail, checked) VALUES ' . implode( ',', $sql ), $args ) );
	}
	$sum    = AW_Link_Audit::summary( $result );
	$anchor = function_exists( 'aw_anchor_rebuild' ) ? aw_anchor_rebuild() : array(
		'targets' => 0,
		'warn'    => 0,
	);
	update_option(
		AW_LINK_AUDIT_SUMMARY,
		array(
			'at'          => time(),
			'anchor_warn' => $anchor['warn'],
			'took'        => max( 0, time() - $started ),
			'items'       => $sum['items'],
			'flagged'     => $sum['flagged'],
			'by'          => $sum['by'],
		),
		false
	);
}

/** Start an audit now: drop what a running one had, and run the job in a few seconds. */
function aw_link_audit_start(): void {
	delete_option( AW_LINK_AUDIT_STATE );
	delete_transient( 'aw_link_audit_lock' );
	if ( ! wp_next_scheduled( AW_LINK_AUDIT_JOB, array( 'now' ) ) ) {
		wp_schedule_single_event( time() + 5, AW_LINK_AUDIT_JOB, array( 'now' ) );
	}
}

/** What the audit is doing now: array( running (bool), phase, done, total ), from its state. */
function aw_link_audit_progress(): array {
	$state = get_option( AW_LINK_AUDIT_STATE );
	if ( ! is_array( $state ) || empty( $state['phase'] ) ) {
		return array(
			'running' => false,
			'phase'   => '',
			'done'    => 0,
			'total'   => 0,
		);
	}
	return array(
		'running' => true,
		'phase'   => (string) $state['phase'],
		'done'    => (int) ( $state['pos'] ?? 0 ),
		'total'   => count( (array) ( $state['ids'] ?? array() ) ),
	);
}

/* ---------- REST ---------- */

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'rankweb/v1',
			'/links',
			array(
				'methods'             => 'GET',
				'permission_callback' => rankweb_rest_can( RANKWEB_CAP ),
				'args'                => array(
					'flag'     => array(
						'type'    => 'string',
						'default' => '',
						'enum'    => array_merge( array( '' ), AW_Link_Audit::FLAGS ),
					),
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 25,
						'minimum' => 1,
						'maximum' => 100,
					),
				),
				'callback'            => function ( WP_REST_Request $req ) {
					$page = aw_link_audit_page( (string) $req['flag'], (int) $req['page'], (int) $req['per_page'] );
					return rest_ensure_response( $page + array( 'summary' => aw_link_audit_summary() ) );
				},
			)
		);
		register_rest_route(
			'rankweb/v1',
			'/links/summary',
			array(
				'methods'             => 'GET',
				'permission_callback' => rankweb_rest_can( RANKWEB_CAP ),
				'callback'            => fn() => rest_ensure_response( aw_link_audit_summary() ),
			)
		);
		register_rest_route(
			'rankweb/v1',
			'/links/audit',
			array(
				'methods'             => 'POST',
				'permission_callback' => rankweb_rest_can( 'manage_options' ),
				'callback'            => function () {
					aw_link_audit_start();
					return rest_ensure_response( aw_link_audit_summary() );
				},
			)
		);
	}
);

/** The last audit's counts and what a running one is doing. */
function aw_link_audit_summary(): array {
	$s = (array) get_option( AW_LINK_AUDIT_SUMMARY, array() );
	return array(
		'at'          => (int) ( $s['at'] ?? 0 ),
		'took'        => (int) ( $s['took'] ?? 0 ),
		'items'       => (int) ( $s['items'] ?? 0 ),
		'flagged'     => (int) ( $s['flagged'] ?? 0 ),
		'anchor_warn' => (int) ( $s['anchor_warn'] ?? 0 ),
		'by'          => (object) ( $s['by'] ?? array() ),
		'progress'    => aw_link_audit_progress(),
	);
}

/**
 * One page of the audit's rows.
 *
 * @param string $flag     A finding, or '' for every item with one.
 * @param int    $page     Page, from 1.
 * @param int    $per_page Rows per page.
 * @return array items (post_id, title, url, links_in, links_out, depth, flags, detail), total.
 */
function aw_link_audit_page( string $flag, int $page, int $per_page ): array {
	global $wpdb;
	$table = aw_link_table( 'audit' );
	if ( get_option( AW_LINK_AUDIT_DB ) !== AW_LINK_AUDIT_DB_VER ) {
		return array(
			'items' => array(),
			'total' => 0,
		);
	}
	$like  = '' === $flag ? '%,%' : '%,' . $wpdb->esc_like( $flag ) . ',%';
	$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE flags LIKE %s', $table, $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- RankWeb's own table.
	$rows  = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT a.post_id, a.links_in, a.links_out, a.depth, a.flags, a.detail, p.post_title FROM %i a JOIN %i p ON p.ID = a.post_id WHERE a.flags LIKE %s ORDER BY a.links_in, a.depth DESC, a.post_id LIMIT %d OFFSET %d', $table, $wpdb->posts, $like, $per_page, ( max( 1, $page ) - 1 ) * $per_page ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- RankWeb's own table.
	$items = array();
	foreach ( $rows as $r ) {
		$items[] = array(
			'post_id'   => (int) $r['post_id'],
			'title'     => (string) $r['post_title'],
			'url'       => (string) get_permalink( (int) $r['post_id'] ),
			'links_in'  => (int) $r['links_in'],
			'links_out' => (int) $r['links_out'],
			'depth'     => (int) $r['depth'],
			'flags'     => array_values( array_filter( explode( ',', (string) $r['flags'] ) ) ),
			'detail'    => (object) json_decode( (string) $r['detail'], true ),
		);
	}
	return array(
		'items' => $items,
		'total' => $total,
	);
}

/* ---------- The screen: RankWeb → Internal links ---------- */

add_action(
	'admin_menu',
	function () {
		add_submenu_page( AW_SEO_MENU, __( 'Internal links', 'aw-seo-extras' ), __( 'Internal links', 'aw-seo-extras' ), RANKWEB_CAP, 'aw-links', 'aw_link_audit_page_html' );
	}
);

// "Run the audit now": a form post, administrators only.
add_action(
	'admin_post_aw_link_audit_run',
	function () {
		check_admin_referer( 'aw_link_audit_run' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'aw-seo-extras' ), '', array( 'response' => 403 ) );
		}
		aw_link_audit_start();
		wp_safe_redirect( add_query_arg( 'started', '1', admin_url( 'admin.php?page=aw-links' ) ) );
		exit;
	}
);

/** The screen. */
function aw_link_audit_page_html(): void {
	if ( ! current_user_can( RANKWEB_CAP ) ) {
		wp_die( esc_html__( 'You are not allowed to see this.', 'aw-seo-extras' ), '', array( 'response' => 403 ) );
	}
	$flag   = sanitize_key( wp_unslash( $_GET['flag'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- a filter on a read-only list.
	$flag   = in_array( $flag, AW_Link_Audit::FLAGS, true ) ? $flag : '';
	$paged  = max( 1, absint( wp_unslash( $_GET['paged'] ?? 1 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification -- a filter on a read-only list.
	$per    = 25;
	$labels = aw_link_audit_labels();
	$sum    = aw_link_audit_summary();
	$prog   = $sum['progress'];
	$base   = admin_url( 'admin.php?page=aw-links' );

	$view = sanitize_key( wp_unslash( $_GET['view'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- a tab on a read-only screen.
	$view = 'anchors' === $view && function_exists( 'aw_anchor_screen' ) ? 'anchors' : ( 'silos' === $view && function_exists( 'aw_silo_screen' ) ? 'silos' : 'audit' );
	echo '<div class="wrap"><h1>' . esc_html__( 'Internal links', 'aw-seo-extras' ) . '</h1>';
	echo '<nav class="nav-tab-wrapper"><a href="' . esc_url( $base ) . '" class="nav-tab' . ( 'audit' === $view ? ' nav-tab-active' : '' ) . '">' . esc_html__( 'Audit', 'aw-seo-extras' ) . '</a>';
	if ( function_exists( 'aw_anchor_screen' ) ) {
		echo '<a href="' . esc_url( add_query_arg( 'view', 'anchors', $base ) ) . '" class="nav-tab' . ( 'anchors' === $view ? ' nav-tab-active' : '' ) . '">' . esc_html__( 'Anchor mix', 'aw-seo-extras' ) . '</a>';
	}
	if ( function_exists( 'aw_silo_screen' ) ) {
		echo '<a href="' . esc_url( add_query_arg( 'view', 'silos', $base ) ) . '" class="nav-tab' . ( 'silos' === $view ? ' nav-tab-active' : '' ) . '">' . esc_html__( 'Silos', 'aw-seo-extras' ) . '</a>';
	}
	echo '</nav>';
	if ( 'anchors' === $view || 'silos' === $view ) {
		if ( 'anchors' === $view ) {
			aw_anchor_screen();
		} else {
			aw_silo_screen();
		}
		echo '</div>';
		return;
	}
	if ( isset( $_GET['started'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- only a notice.
		wp_admin_notice( esc_html__( 'The audit starts in a few seconds and runs in the background.', 'aw-seo-extras' ), array( 'type' => 'info' ) );
	}
	if ( ! aw_link_types() ) {
		wp_admin_notice( esc_html__( 'No post type is linked yet, so there is nothing to audit. Add post types under RankWeb → Settings → Internal links.', 'aw-seo-extras' ), array( 'type' => 'warning' ) );
	}
	if ( $prog['running'] ) {
		/* translators: 1: step (collect, check or analyse), 2: items done, 3: items in all. */
		$line = sprintf( __( 'Audit running: %1$s (%2$s of %3$s items).', 'aw-seo-extras' ), 'collect' === $prog['phase'] ? __( 'reading the items', 'aw-seo-extras' ) : ( 'check' === $prog['phase'] ? __( 'checking addresses', 'aw-seo-extras' ) : __( 'working out the results', 'aw-seo-extras' ) ), number_format_i18n( $prog['done'] ), number_format_i18n( $prog['total'] ) );
	} elseif ( $sum['at'] ) {
		/* translators: 1: date and time, 2: items audited, 3: items with a finding. */
		$line = sprintf( __( 'Last audit %1$s: %2$s items, %3$s with a finding.', 'aw-seo-extras' ), wp_date( 'j M Y, g:i a', $sum['at'] ), number_format_i18n( $sum['items'] ), number_format_i18n( $sum['flagged'] ) );
	} else {
		$line = __( 'The audit has not run yet. It runs once a day.', 'aw-seo-extras' );
	}
	echo '<p>' . esc_html( $line ) . '</p>';
	if ( current_user_can( 'manage_options' ) ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="aw_link_audit_run">';
		wp_nonce_field( 'aw_link_audit_run' );
		submit_button( __( 'Run the audit now', 'aw-seo-extras' ), 'secondary', 'submit', false );
		echo '</form>';
	}

	// The findings as core's subsubsub filter.
	$by      = (array) $sum['by'];
	$views   = array();
	$views[] = ( '' === $flag ? '<strong>' : '<a href="' . esc_url( $base ) . '">' ) . esc_html__( 'All findings', 'aw-seo-extras' ) . ' (' . esc_html( number_format_i18n( $sum['flagged'] ) ) . ')' . ( '' === $flag ? '</strong>' : '</a>' );
	foreach ( $labels as $key => $label ) {
		$views[] = ( $key === $flag ? '<strong>' : '<a href="' . esc_url( add_query_arg( 'flag', $key, $base ) ) . '">' ) . esc_html( $label ) . ' (' . esc_html( number_format_i18n( (int) ( $by[ $key ] ?? 0 ) ) ) . ')' . ( $key === $flag ? '</strong>' : '</a>' );
	}
	echo '<ul class="subsubsub"><li>' . implode( ' | </li><li>', $views ) . '</li></ul><br class="clear">'; // phpcs:ignore WordPress.Security.EscapeOutput -- each part is escaped above.

	$data  = aw_link_audit_page( $flag, $paged, $per );
	$cells = array();
	foreach ( $data['items'] as $it ) {
		$issues = array();
		$detail = (array) $it['detail'];
		foreach ( $it['flags'] as $f ) {
			$text     = $labels[ $f ] ?? $f;
			$det      = (array) ( $detail[ $f ] ?? array() );
			$issues[] = esc_html( $text ) . ( $det ? ': ' . esc_html( implode( ', ', array_map( 'esc_url_raw', $det ) ) ) : '' );
		}
		$cells[] = array(
			'item'   => '<a href="' . esc_url( (string) get_edit_post_link( $it['post_id'] ) ) . '">' . esc_html( '' !== $it['title'] ? $it['title'] : __( '(no title)', 'aw-seo-extras' ) ) . '</a> <a href="' . esc_url( $it['url'] ) . '">' . esc_html__( 'View', 'aw-seo-extras' ) . '</a>',
			'in'     => esc_html( number_format_i18n( $it['links_in'] ) ),
			'out'    => esc_html( number_format_i18n( $it['links_out'] ) ),
			'depth'  => $it['depth'] ? esc_html( number_format_i18n( $it['depth'] ) ) : esc_html__( 'Not reached', 'aw-seo-extras' ),
			'issues' => implode( '<br>', $issues ),
		);
	}
	aw_seo_extras_simple_table(
		'aw-link-audit',
		array(
			'item'   => __( 'Item', 'aw-seo-extras' ),
			'in'     => __( 'Links in', 'aw-seo-extras' ),
			'out'    => __( 'Links out', 'aw-seo-extras' ),
			'depth'  => __( 'Clicks from home', 'aw-seo-extras' ),
			'issues' => __( 'Findings', 'aw-seo-extras' ),
		),
		$cells,
		array(
			'per_page' => $per,
			'total'    => $data['total'],
			'primary'  => 'item',
			'empty'    => $sum['at'] ? __( 'Nothing to list here.', 'aw-seo-extras' ) : __( 'The audit has not run yet.', 'aw-seo-extras' ),
		)
	);
	echo '</div>';
}
