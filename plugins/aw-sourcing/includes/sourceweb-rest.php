<?php
/**
 * SourceWeb's routes for its new screens, under sourceweb/v1:
 *
 *   GET  dashboard              edit_others_posts  the tiles, from the background counts (sourceweb-dashboard.php)
 *   POST dashboard/count        edit_others_posts  ask for a count now (one background event)
 *   GET  review                 edit_others_posts  one page of To review (type, search, source, why, orderby, order, page)
 *   POST review/decide          edit_others_posts  publish or reject items (aw_review_apply(), as today's screen)
 *   POST review/publish-all     edit_others_posts  publish every item of a view; the count sent must match
 *   GET  enrichment             edit_others_posts  one page of Listing details, one row per listing
 *   POST enrichment/decide      edit_others_posts  approve, reject or undo rows (aw_enrich_approve/reject/undo())
 *   GET  settings               manage_options     the connected services and the groups, with today's values
 *   POST settings/<group>       manage_options     save one group: only the values that changed are written
 *   POST settings/google-key    manage_options     a new Google Places key (write-only: never sent back)
 *
 * Every route checks the capability and, for a signed-in browser, the wp_rest nonce (kits_shell_rest_can() from Kits
 * Core). The aw/v1 routes the workers use are not touched.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SOURCEWEB_REST = 'sourceweb/v1';

/**
 * Permission check for a route: Kits Core's shell check when it is there, else signed in with the capability.
 *
 * @param string $cap Capability.
 * @return callable
 */
function sourceweb_rest_can( string $cap ): callable {
	if ( function_exists( 'kits_shell_rest_can' ) ) {
		return kits_shell_rest_can( $cap );
	}
	return function () use ( $cap ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_not_logged_in', __( 'Sign in to use this.', 'aw-sourcing' ), array( 'status' => 401 ) );
		}
		return current_user_can( $cap ) ? true : new WP_Error( 'rest_forbidden', __( 'You are not allowed to do this.', 'aw-sourcing' ), array( 'status' => 403 ) );
	};
}

add_action(
	'rest_api_init',
	function () {
		$ids    = array(
			'type'    => 'array',
			'items'   => array( 'type' => 'integer' ),
			'default' => array(),
		);
		$text   = array(
			'type'    => 'string',
			'default' => '',
		);
		$routes = array(
			'/dashboard'           => array( 'GET', AW_REVIEW_CAP, fn() => rest_ensure_response( sourceweb_dashboard_payload() ), array() ),
			'/dashboard/count'     => array( 'POST', AW_REVIEW_CAP, fn() => rest_ensure_response( array( 'waiting' => sourceweb_dashboard_request() ) ), array() ),
			'/review'              => array(
				'GET',
				AW_REVIEW_CAP,
				'sourceweb_rest_review',
				array(
					'type'    => $text,
					'search'  => $text,
					'source'  => $text,
					'why'     => $text,
					'orderby' => $text,
					'order'   => $text,
					'page'    => array(
						'type'    => 'integer',
						'default' => 1,
					),
				),
			),
			'/review/decide'       => array(
				'POST',
				AW_REVIEW_CAP,
				'sourceweb_rest_review_decide',
				array(
					'ids'    => $ids,
					'action' => array(
						'type'     => 'string',
						'enum'     => array( 'publish', 'reject' ),
						'required' => true,
					),
				),
			),
			'/review/publish-all'  => array(
				'POST',
				AW_REVIEW_CAP,
				'sourceweb_rest_review_publish_all',
				array(
					'type'  => $text,
					'count' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			),
			'/enrichment'          => array(
				'GET',
				AW_ENRICH_CAP,
				'sourceweb_rest_enrichment',
				array(
					'status'     => array(
						'type'    => 'string',
						'default' => 'pending',
					),
					'source'     => $text,
					'confidence' => $text,
					'field'      => $text,
					'flag'       => $text,
					'state'      => $text,
					'search'     => $text,
					'page'       => array(
						'type'    => 'integer',
						'default' => 1,
					),
				),
			),
			'/enrichment/decide'   => array(
				'POST',
				AW_ENRICH_CAP,
				'sourceweb_rest_enrichment_decide',
				array(
					'ids'        => $ids,
					'listings'   => $ids,
					'do'         => array(
						'type'     => 'string',
						'enum'     => array( 'approve', 'reject', 'undo', 'approve_high' ),
						'required' => true,
					),
					'source'     => $text,
					'confidence' => $text,
					'field'      => $text,
					'flag'       => $text,
					'state'      => $text,
				),
			),
			'/settings'            => array( 'GET', AW_SRC_CAP, fn() => rest_ensure_response( sourceweb_settings_payload() ), array() ),
			'/settings/google-key' => array(
				'POST',
				AW_SRC_CAP,
				'sourceweb_rest_google_key',
				array(
					'key' => $text,
				),
			),
			'/settings/(?P<group>workers|enrichment|social)' => array(
				'POST',
				AW_SRC_CAP,
				'sourceweb_rest_save_group',
				array(
					'values' => array(
						'type'    => 'object',
						'default' => array(),
					),
				),
			),
		);
		foreach ( $routes as $path => $r ) {
			register_rest_route(
				SOURCEWEB_REST,
				$path,
				array(
					'methods'             => $r[0],
					'permission_callback' => sourceweb_rest_can( $r[1] ),
					'callback'            => $r[2],
					'args'                => $r[3],
				)
			);
		}
	}
);

/* ---------- Dashboard ---------- */

/**
 * An address on a current screen (Sources, a tab), for administrators only ('' for others).
 *
 * @param string $tab  Tab ('' for Sources).
 * @param array  $args More query arguments.
 */
function sourceweb_admin_url( string $tab = '', array $args = array() ): string {
	return current_user_can( AW_SRC_CAP ) ? admin_url( aw_src_tab_path( $tab, $args ) ) : '';
}

/**
 * The Dashboard: tiles from the stored counts (formatting only, nothing is counted here), the day log for the time
 * range, the worker runs and the reports.
 */
function sourceweb_dashboard_payload(): array {
	$c        = get_transient( SOURCEWEB_DASH );
	$counting = ! is_array( $c );
	if ( $counting ) {
		sourceweb_dashboard_request(); // The first view after a deploy: count in the background.
		$c = array();
	}
	$admin = current_user_can( AW_SRC_CAP );
	$num   = fn( $n ) => number_format_i18n( (int) $n );
	$day   = fn( $t ) => $t ? wp_date( 'j M, g:i a', (int) $t ) : '';
	$tiles = array();

	$r       = (array) ( $c['review'] ?? array() );
	$types   = defined( 'AW_REVIEW_TYPES' ) ? AW_REVIEW_TYPES : array();
	$top     = ! empty( $r['by'] ) ? (string) array_key_first( (array) $r['by'] ) : '';
	$old     = (int) ( $r['oldest'] ?? 0 );
	$tiles[] = array(
		'key'    => 'review',
		'label'  => __( 'Waiting for review', 'aw-sourcing' ),
		'value'  => $num( $r['total'] ?? 0 ),
		'raw'    => (int) ( $r['total'] ?? 0 ),
		'sub'    => $old
			/* translators: 1: date, 2: type with the most items. */
			? sprintf( __( 'Oldest since %1$s; most are %2$s', 'aw-sourcing' ), wp_date( 'j M', $old ), $types[ $top ] ?? $top )
			: __( 'Nothing is waiting', 'aw-sourcing' ),
		'href'   => sourceweb_ui_url( 'review' ),
		'tone'   => $old && time() - $old > 7 * DAY_IN_SECONDS ? 'warn' : '',
		'metric' => 'review',
	);

	$e       = (array) ( $c['enrich'] ?? array() );
	$tiles[] = array(
		'key'    => 'enrich',
		'label'  => __( 'Enrichment queue', 'aw-sourcing' ),
		'value'  => $num( $e['pending'] ?? 0 ),
		'raw'    => (int) ( $e['pending'] ?? 0 ),
		/* translators: 1: listings, 2: values applied, 3: of them automatically. */
		'sub'    => sprintf( __( 'Proposals for %1$s listings; %2$s applied in 7 days (%3$s automatically)', 'aw-sourcing' ), $num( $e['listings'] ?? 0 ), $num( $e['applied7'] ?? 0 ), $num( $e['auto7'] ?? 0 ) ),
		'href'   => sourceweb_ui_url( 'enrichment' ),
		'tone'   => '',
		'metric' => 'enrich',
	);

	$i = (array) ( $c['intake'] ?? array() );
	if ( empty( $i['last_run'] ) ) {
		$iv = __( 'No report yet', 'aw-sourcing' );
		$is = __( 'The workers have not reported a run', 'aw-sourcing' );
	} else {
		$iv = ! empty( $i['ok'] ) ? __( 'On time', 'aw-sourcing' ) : __( 'Late', 'aw-sourcing' );
		/* translators: 1: date and time, 2: new items, 3: published, 4: sources failed. */
		$is = sprintf( __( 'Last run %1$s: %2$s new, %3$s published, %4$s sources failed', 'aw-sourcing' ), $day( $i['last_run'] ), $num( $i['new'] ?? 0 ), $num( $i['published'] ?? 0 ), $num( $i['failed'] ?? 0 ) );
	}
	$tiles[] = array(
		'key'   => 'intake',
		'label' => __( 'Last run', 'aw-sourcing' ),
		'value' => $counting ? '' : $iv,
		'sub'   => $is,
		'href'  => current_user_can( 'view_site_health_checks' ) ? admin_url( 'site-health.php' ) : '',
		'tone'  => array(
			'critical'    => 'error',
			'recommended' => 'warn',
		)[ (string) ( $i['status'] ?? '' ) ] ?? '',
	);

	$tiles[] = array(
		'key'    => 'rejected',
		'label'  => __( 'Refused today', 'aw-sourcing' ),
		'value'  => $num( $i['rejected_today'] ?? 0 ),
		'raw'    => (int) ( $i['rejected_today'] ?? 0 ),
		/* translators: %s: number of items. */
		'sub'    => sprintf( __( '%s in the Rejected log; never created', 'aw-sourcing' ), $num( $i['rejected_all'] ?? 0 ) ),
		'href'   => sourceweb_admin_url( 'rejected' ),
		'tone'   => '',
		'metric' => 'rejected',
	);

	$s       = (array) ( $c['sources'] ?? array() );
	$tiles[] = array(
		'key'    => 'sources',
		'label'  => __( 'Sources allowed', 'aw-sourcing' ),
		'value'  => $num( $s['allow'] ?? 0 ),
		'raw'    => (int) ( $s['allow'] ?? 0 ),
		/* translators: 1: neutral, 2: blocked, 3: feeds. */
		'sub'    => sprintf( __( '%1$s neutral, %2$s blocked; %3$s feeds', 'aw-sourcing' ), $num( $s['neutral'] ?? 0 ), $num( $s['block'] ?? 0 ), $num( $s['feeds'] ?? 0 ) ) . ' · ' . ( ! empty( $s['live'] ) ? __( 'Workers use these lists', 'aw-sourcing' ) : __( 'Workers use sources.yaml', 'aw-sourcing' ) ),
		'href'   => sourceweb_admin_url(),
		'tone'   => '',
		'metric' => 'sources',
	);

	if ( $admin ) {
		$w = (array) ( $c['workers'] ?? array() );
		if ( $w || $counting ) {
			$p       = (array) ( $w['problems'] ?? array() );
			$tiles[] = array(
				'key'    => 'workers',
				'label'  => __( 'Workers on time', 'aw-sourcing' ),
				/* translators: 1: on time, 2: workflows. */
				'value'  => $counting ? '' : sprintf( __( '%1$s of %2$s', 'aw-sourcing' ), $num( (int) ( $w['total'] ?? 0 ) - count( $p ) ), $num( $w['total'] ?? 0 ) ),
				'raw'    => (int) ( $w['total'] ?? 0 ) - count( $p ),
				'sub'    => $p ? implode( ' ', array_slice( $p, 0, 2 ) ) : __( 'Every workflow that reports is on time', 'aw-sourcing' ),
				'href'   => sourceweb_admin_url( 'workers' ),
				'tone'   => $p ? 'warn' : '',
				'metric' => 'workers',
			);
		}
		$a       = (array) ( $c['ai'] ?? array() );
		$tiles[] = array(
			'key'   => 'ai',
			'label' => __( 'AI keys that work', 'aw-sourcing' ),
			'value' => $num( $a['works'] ?? 0 ),
			'sub'   => empty( $a['saved'] )
				? __( 'No keys saved on the AI models screen yet', 'aw-sourcing' )
				/* translators: 1: waiting, 2: refused. */
				: sprintf( __( '%1$s waiting or not checked, %2$s refused or out of credit', 'aw-sourcing' ), $num( $a['wait'] ?? 0 ), $num( $a['bad'] ?? 0 ) ),
			'href'  => sourceweb_admin_url( 'ai' ),
			'tone'  => ! empty( $a['saved'] ) && empty( $a['works'] ) ? 'warn' : '',
		);
		$g       = (array) ( $c['google'] ?? array() );
		$cap     = (int) ( $g['cap'] ?? 20 );
		$live    = (float) ( $g['live'] ?? 0 );
		$tiles[] = array(
			'key'   => 'google',
			'label' => __( 'Google Maps spend', 'aw-sourcing' ),
			'value' => '$' . number_format_i18n( $live, 2 ),
			'sub'   => empty( $g['key'] )
				? __( 'No key set: the live block is hidden', 'aw-sourcing' )
				/* translators: 1: monthly cap, 2: the worker's spend. */
				: sprintf( __( 'This month, of a $%1$s cap; the worker spent $%2$s', 'aw-sourcing' ), $num( $cap ), number_format_i18n( (float) ( $g['worker'] ?? 0 ), 2 ) ),
			'href'  => sourceweb_ui_url( 'settings' ) . '#services',
			'tone'  => $cap > 0 && $live >= 0.8 * $cap ? 'warn' : '',
		);
	}
	foreach ( (array) ( $c['extra'] ?? array() ) as $x ) {
		if ( ! empty( $x['key'] ) && ! empty( $x['label'] ) ) {
			$tiles[] = array(
				'key'   => sanitize_key( (string) $x['key'] ),
				'label' => (string) $x['label'],
				'value' => (string) ( $x['value'] ?? '' ),
				'sub'   => (string) ( $x['sub'] ?? '' ),
				'href'  => esc_url_raw( (string) ( $x['href'] ?? '' ) ),
				'tone'  => in_array( $x['tone'] ?? '', array( 'warn', 'error' ), true ) ? $x['tone'] : '',
			);
		}
	}

	$runs = array();
	if ( $admin ) {
		foreach ( (array) ( $c['workers']['runs'] ?? array() ) as $run ) {
			$runs[] = array(
				'id'     => (string) $run['file'],
				'title'  => (string) $run['name'],
				'last'   => $run['last'] ? $day( $run['last'] ) : '—',
				'result' => '' === $run['result'] ? __( 'No report yet', 'aw-sourcing' ) : ucfirst( (string) $run['result'] ),
				'ok'     => 'success' === $run['result'],
				/* translators: %s: the workflow it runs after. */
				'next'   => $run['next'] ? $day( $run['next'] ) : ( '' !== $run['after'] ? sprintf( __( 'After "%s"', 'aw-sourcing' ), $run['after'] ) : __( 'By hand', 'aw-sourcing' ) ),
			);
		}
	}
	$reports = array();
	if ( $admin ) {
		foreach (
			array(
				'rejected'  => array( __( 'Rejected', 'aw-sourcing' ), __( 'Items the relevance check refused (never created), with the reason.', 'aw-sourcing' ) ),
				'relay-log' => array( __( 'Relay log', 'aw-sourcing' ), __( 'Requests the workers made through this site (the last 200).', 'aw-sourcing' ) ),
				'log'       => array( __( 'Change log', 'aw-sourcing' ), __( 'Who changed what on these screens and when.', 'aw-sourcing' ) ),
			) as $tab => $t
		) {
			$reports[] = array(
				'label' => $t[0],
				'url'   => sourceweb_admin_url( $tab ),
				'what'  => $t[1],
			);
		}
	}

	return array(
		'counting' => $counting,
		'at'       => (int) ( $c['at'] ?? 0 ),
		'atText'   => ! empty( $c['at'] ) ? wp_date( 'j M Y, g:i a', (int) $c['at'] ) : '',
		'tiles'    => $tiles,
		'log'      => (object) get_option( SOURCEWEB_DASH_LOG, array() ),
		'today'    => wp_date( 'Y-m-d' ),
		'runs'     => $runs,
		'reports'  => $reports,
		'commands' => array(
			array( 'wp aw-sourcing backup|migrate|verify|rollback', __( 'The sourcing restructure phases (--phase=n), with --dry-run.', 'aw-sourcing' ) ),
			array( 'wp aw-sourcing import-dr', __( 'Domain Rating values for the Sources list.', 'aw-sourcing' ) ),
			array( 'wp aw news-titles', __( 'Apply corrected News or Press titles from a CSV file.', 'aw-sourcing' ) ),
			array( 'wp aw workers-tick', __( 'The workers check, every 15 minutes from the server cron.', 'aw-sourcing' ) ),
		),
	);
}

/* ---------- To review ---------- */

/**
 * An item's source address (or a Press item's coverage address, or a social post's own link).
 *
 * @param WP_Post $p Item.
 */
function sourceweb_review_source( WP_Post $p ): string {
	foreach ( array( 'aw_source_url', 'aw_press_url', 'aw_social_url' ) as $k ) {
		$u = (string) get_post_meta( $p->ID, $k, true );
		if ( '' !== $u ) {
			return $u;
		}
	}
	return '';
}

/**
 * One To review row as the list shows it.
 *
 * @param WP_Post $p Item.
 */
function sourceweb_review_row( WP_Post $p ): array {
	$url   = sourceweb_review_source( $p );
	$title = html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' );
	return array(
		'id'        => (int) $p->ID,
		'title'     => '' !== $title ? $title : __( '(no title)', 'aw-sourcing' ),
		'type'      => $p->post_type,
		'typeLabel' => AW_REVIEW_TYPES[ $p->post_type ] ?? $p->post_type,
		'date'      => (string) $p->post_date,
		'dateText'  => (string) get_the_date( 'j M Y', $p ),
		'host'      => $url ? preg_replace( '/^www\./', '', strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) ) : '',
		'url'       => $url,
		'why'       => sourceweb_why_text( (string) get_post_meta( $p->ID, 'aw_review', true ), $p->post_status ),
		'excerpt'   => wp_trim_words( wp_strip_all_tags( $p->post_excerpt ? $p->post_excerpt : $p->post_content ), 24 ),
		'editUrl'   => (string) get_edit_post_link( $p->ID, 'raw' ),
		// A social post needs its staff summary before it goes live (aw_review_apply() skips it).
		'noPublish' => 'aw_social' === $p->post_type && '' === trim( (string) get_post_meta( $p->ID, 'aw_social_summary', true ) ),
	);
}

/**
 * GET review.
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_review( WP_REST_Request $request ) {
	$items = array_map( 'sourceweb_review_row', aw_review_items() );
	$out   = sourceweb_review_list( $items, $request->get_params(), AW_REVIEW_TYPES );
	return rest_ensure_response( $out );
}

/**
 * POST review/decide: Publish or Reject, as today's row and bulk actions.
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_review_decide( WP_REST_Request $request ) {
	$action = (string) $request['action'];
	$ids    = array_filter( array_map( 'absint', (array) $request['ids'] ) );
	$n      = $ids ? aw_review_apply( $ids, $action ) : 0;
	return rest_ensure_response(
		array(
			'done'    => $n,
			'message' => 'publish' === $action
				/* translators: %d: number of items. */
				? sprintf( _n( '%d item published.', '%d items published.', $n, 'aw-sourcing' ), $n )
				/* translators: %d: number of items. */
				: sprintf( _n( '%d item moved to Drafts.', '%d items moved to Drafts.', $n, 'aw-sourcing' ), $n ),
		)
	);
}

/**
 * POST review/publish-all: every item of the view, when the count the person confirmed is still the count.
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_review_publish_all( WP_REST_Request $request ) {
	$type  = sanitize_key( (string) $request['type'] );
	$items = aw_review_items( isset( AW_REVIEW_TYPES[ $type ] ) ? $type : '' );
	if ( count( $items ) !== (int) $request['count'] ) {
		return new WP_Error(
			'sourceweb_count_changed',
			/* translators: %d: number of items. */
			sprintf( __( 'The list changed: %d items wait now. Nothing was published; check the list and try again.', 'aw-sourcing' ), count( $items ) ),
			array( 'status' => 409 )
		);
	}
	$n = aw_review_apply( wp_list_pluck( $items, 'ID' ), 'publish' );
	return rest_ensure_response(
		array(
			'done'    => $n,
			/* translators: %d: number of items. */
			'message' => sprintf( _n( '%d item published.', '%d items published.', $n, 'aw-sourcing' ), $n ),
		)
	);
}

/* ---------- Enrichment review (Listing details) ---------- */

/**
 * A proposal's value as plain text.
 *
 * @param string $field Field.
 * @param mixed  $v     Value.
 */
function sourceweb_enrich_text( string $field, $v ): string {
	if ( 'place' === $field ) {
		return '' !== $v && array() !== $v && function_exists( 'aw_enrich_place_label' ) ? (string) aw_enrich_place_label( $v ) : '';
	}
	if ( 'merge_into' === $field ) {
		return is_array( $v ) || ! (int) $v ? '' : '#' . (int) $v . ' ' . html_entity_decode( get_the_title( (int) $v ), ENT_QUOTES, 'UTF-8' );
	}
	if ( 'hours' === $field && is_array( $v ) ) {
		return implode( '; ', array_map( fn( $r ) => trim( ( $r['day'] ?? '' ) . ' ' . ( ! empty( $r['closed'] ) ? 'closed' : ( $r['open'] ?? '' ) . '–' . ( $r['close'] ?? '' ) ) ), $v ) );
	}
	if ( in_array( $field, array( 'logo', 'photos' ), true ) ) {
		$n = count( array_filter( (array) $v, fn( $x ) => is_string( $x ) && '' !== $x ) );
		/* translators: %d: number of pictures. */
		return $n ? sprintf( _n( '%d picture', '%d pictures', $n, 'aw-sourcing' ), $n ) : '';
	}
	if ( is_array( $v ) ) {
		return implode( ', ', array_map( fn( $x ) => is_scalar( $x ) ? (string) $x : (string) wp_json_encode( $x ), $v ) );
	}
	return (string) $v;
}

/**
 * The value a row replaced (pending: the listing's value now), as today's screen works it out.
 *
 * @param array $r Decoded row.
 * @return mixed
 */
function sourceweb_enrich_before( array $r ) {
	$lid = (int) $r['listing_id'];
	if ( 'pending' === $r['status'] ) {
		return aw_enrich_current( $lid, $r['field'] );
	}
	$cur = $r['old_value'];
	if ( 'logo' === $r['field'] ) {
		return $cur ? (string) wp_get_attachment_url( (int) $cur ) : '';
	}
	if ( 'photos' === $r['field'] ) {
		return array_map( fn( $p ) => (string) wp_get_attachment_url( (int) $p ), array_filter( explode( ',', (string) $cur ) ) );
	}
	if ( in_array( $r['field'], array( 'phone', 'whatsapp', 'emergency' ), true ) && is_array( $cur ) && isset( $cur[0]['number'] ) ) {
		return wp_list_pluck( $cur, 'number' );
	}
	return $cur;
}

/**
 * The filter parts of a request, as aw_enrich_where() reads them.
 *
 * @param WP_REST_Request $request Request.
 * @param string          $status  Status view.
 */
function sourceweb_enrich_query( WP_REST_Request $request, string $status ): array {
	$q = array( 'status' => isset( SOURCEWEB_ENRICH_VIEWS[ $status ] ) ? $status : 'pending' );
	foreach ( array( 'source', 'confidence', 'field', 'flag', 'state' ) as $k ) {
		if ( '' !== (string) $request[ $k ] ) {
			$q[ $k ] = (string) $request[ $k ];
		}
	}
	return $q;
}

/**
 * GET enrichment: one row per listing with proposals in the view, newest decisions last.
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_enrichment( WP_REST_Request $request ) {
	global $wpdb;
	$status  = sanitize_key( (string) $request['status'] );
	$q       = sourceweb_enrich_query( $request, $status );
	$filters = sourceweb_enrich_filters( $q );
	$empty   = array(
		'rows'     => array(),
		'total'    => 0,
		'page'     => 1,
		'pages'    => 1,
		'status'   => $q['status'],
		'views'    => array(),
		'filters'  => $filters,
		'progress' => function_exists( 'aw_enrich_progress' ) ? aw_enrich_progress() : array(),
	);
	if ( ! get_option( 'aw_enrich_db' ) ) {
		return rest_ensure_response( $empty ); // No proposal has been stored yet.
	}
	$t                    = aw_enrich_table();
	list( $where, $args ) = aw_enrich_where( $q );
	$words                = trim( sanitize_text_field( (string) $request['search'] ) );
	if ( '' !== $words ) {
		$where .= " AND e.listing_id IN ( SELECT ID FROM {$wpdb->posts} WHERE post_type = 'aw_listing' AND post_title LIKE %s )";
		$args[] = '%' . $wpdb->esc_like( $words ) . '%';
	}

	// phpcs:disable WordPress.DB -- the plugin's own table; every value is a placeholder
	$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT( DISTINCT e.listing_id ) FROM {$t} e WHERE {$where}", $args ) );
	$per   = SOURCEWEB_ENRICH_PER_PAGE;
	$pages = max( 1, (int) ceil( $total / $per ) );
	$page  = min( $pages, max( 1, (int) $request['page'] ) );
	$lids  = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT e.listing_id FROM {$t} e WHERE {$where} GROUP BY e.listing_id ORDER BY MIN( FIELD( e.confidence, 'high', 'medium', 'low' ) ), e.listing_id LIMIT %d OFFSET %d", array_merge( $args, array( $per, ( $page - 1 ) * $per ) ) ) ) );
	$rows  = $lids ? $wpdb->get_results( $wpdb->prepare( "SELECT e.* FROM {$t} e WHERE {$where} AND e.listing_id IN ( " . implode( ', ', array_fill( 0, count( $lids ), '%d' ) ) . ' ) ORDER BY e.id DESC LIMIT 400', array_merge( $args, $lids ) ), ARRAY_A ) : array();
	$views = array();
	$vq    = $q;
	unset( $vq['status'] );
	$vq['status']           = 'all';
	list( $vwhere, $vargs ) = aw_enrich_where( $vq );
	if ( '' !== $words ) {
		$vwhere .= " AND e.listing_id IN ( SELECT ID FROM {$wpdb->posts} WHERE post_type = 'aw_listing' AND post_title LIKE %s )";
		$vargs[] = '%' . $wpdb->esc_like( $words ) . '%';
	}
	$by = array();
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT e.status, COUNT(*) AS n FROM {$t} e WHERE {$vwhere} GROUP BY e.status", $vargs ) ) as $v ) {
		$by[ (string) $v->status ] = (int) $v->n;
	}
	// phpcs:enable WordPress.DB
	foreach ( SOURCEWEB_ENRICH_VIEWS as $k => $label ) {
		if ( 'applied' === $k ) {
			$n = ( $by['approved'] ?? 0 ) + ( $by['auto-applied'] ?? 0 );
		} elseif ( 'all' === $k ) {
			$n = array_sum( $by );
		} else {
			$n = $by[ $k ] ?? 0;
		}
		$views[] = array(
			'value' => $k,
			'label' => $label,
			'count' => $n,
		);
	}

	$labels = aw_enrich_fields_all() + ( function_exists( 'aw_enrich_claim_fields' ) ? aw_enrich_claim_fields() : array() );
	$fields = array();
	foreach ( (array) $rows as $r ) {
		$r            = aw_enrich_decode( $r );
		$f            = sourceweb_enrich_field(
			array(
				'id'         => (int) $r['id'],
				'field'      => (string) $r['field'],
				'label'      => (string) ( $labels[ $r['field'] ][0] ?? $r['field'] ),
				'current'    => sourceweb_enrich_text( (string) $r['field'], sourceweb_enrich_before( $r ) ),
				'proposed'   => sourceweb_enrich_text( (string) $r['field'], $r['value'] ),
				'status'     => (string) $r['status'],
				'confidence' => (string) $r['confidence'],
				'source'     => implode( ', ', array_filter( array_map( fn( $s ) => (string) ( $s['name'] ?? '' ), (array) $r['sources'] ) ) ),
				'note'       => (string) $r['note'],
				'created'    => wp_date( 'j M Y', strtotime( (string) $r['created'] ) ),
			)
		);
		$f['listing'] = (int) $r['listing_id'];
		$fields[]     = $f;
	}
	$out = array();
	foreach ( sourceweb_enrich_group( $lids, $fields ) as $lid => $g ) {
		$flags = aw_enrich_flags( $lid );
		$conf  = array_values( array_unique( array_filter( wp_list_pluck( array_merge( $g['fields'], $g['others'] ), 'confidence' ) ) ) );
		$out[] = array(
			'id'         => $lid,
			'title'      => html_entity_decode( get_the_title( $lid ), ENT_QUOTES, 'UTF-8' ),
			'editUrl'    => (string) get_edit_post_link( $lid, 'raw' ),
			'viewUrl'    => (string) get_permalink( $lid ),
			'flags'      => array_values( array_map( 'aw_enrich_flag_label', array_keys( $flags ) ) ),
			'waiting'    => $g['waiting'],
			'applied'    => $g['applied'],
			'confidence' => $conf,
			'sources'    => implode( ', ', array_values( array_unique( array_filter( explode( ', ', implode( ', ', wp_list_pluck( array_merge( $g['fields'], $g['others'] ), 'source' ) ) ) ) ) ) ),
			'fields'     => $g['fields'],
			'others'     => $g['others'],
		);
	}
	return rest_ensure_response(
		array_merge(
			$empty,
			array(
				'rows'  => $out,
				'total' => $total,
				'page'  => $page,
				'pages' => $pages,
				'views' => $views,
			)
		)
	);
}

/**
 * The filter selects of Listing details, with the chosen values.
 *
 * @param array $q Query.
 */
function sourceweb_enrich_filters( array $q ): array {
	$list   = fn( array $opts, string $all ) => array_merge(
		array(
			array(
				'value' => '',
				'label' => $all,
			),
		),
		array_map(
			fn( $k ) => array(
				'value' => (string) $k,
				'label' => (string) $opts[ $k ],
			),
			array_keys( $opts )
		)
	);
	$states = array();
	foreach ( function_exists( 'aw_place_states' ) ? (array) aw_place_states() : array() as $s ) {
		$states[ (string) $s->code ] = (string) $s->name;
	}
	$out = array(
		array( 'source', __( 'Source', 'aw-sourcing' ), $list( array_map( fn( $s ) => $s[0], aw_enrich_sources() ), __( 'All sources', 'aw-sourcing' ) ) ),
		array(
			'confidence',
			__( 'Confidence', 'aw-sourcing' ),
			$list(
				array(
					'high'   => __( 'High', 'aw-sourcing' ),
					'medium' => __( 'Medium', 'aw-sourcing' ),
					'low'    => __( 'Low', 'aw-sourcing' ),
				),
				__( 'Any confidence', 'aw-sourcing' )
			),
		),
		array( 'field', __( 'Field', 'aw-sourcing' ), $list( array_map( fn( $f ) => $f[0], aw_enrich_fields_all() ), __( 'All fields', 'aw-sourcing' ) ) ),
		array(
			'flag',
			__( 'Flag', 'aw-sourcing' ),
			$list(
				array(
					'invite_claim'    => __( 'Invite to claim', 'aw-sourcing' ),
					'reported_closed' => __( 'Reported closed', 'aw-sourcing' ),
					'duplicate'       => __( 'Possible duplicate', 'aw-sourcing' ),
					'identity'        => __( 'Identity unresolved', 'aw-sourcing' ),
				),
				__( 'Any flag', 'aw-sourcing' )
			),
		),
	);
	if ( $states ) {
		$out[] = array( 'state', __( 'State', 'aw-sourcing' ), $list( $states, __( 'All states', 'aw-sourcing' ) ) );
	}
	return array_map(
		fn( $f ) => array(
			'key'     => $f[0],
			'label'   => $f[1],
			'options' => $f[2],
			'value'   => (string) ( $q[ $f[0] ] ?? '' ),
		),
		$out
	);
}

/**
 * POST enrichment/decide: rows by id, every waiting row of the ticked listings (merges left out: they are approved one
 * by one, as today), or every high one in the filter. The same functions as today's screen.
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_enrichment_decide( WP_REST_Request $request ) {
	global $wpdb;
	if ( ! get_option( 'aw_enrich_db' ) ) {
		return new WP_Error( 'sourceweb_no_table', __( 'No proposals are stored yet.', 'aw-sourcing' ), array( 'status' => 404 ) );
	}
	$do       = (string) $request['do'];
	$ids      = array_filter( array_map( 'absint', (array) $request['ids'] ) );
	$listings = array_filter( array_map( 'absint', (array) $request['listings'] ) );
	$t        = aw_enrich_table();
	// phpcs:disable WordPress.DB -- the plugin's own table; every value is a placeholder
	if ( 'approve_high' === $do ) {
		$q                    = sourceweb_enrich_query( $request, 'pending' );
		$q['confidence']      = 'high';
		list( $where, $args ) = aw_enrich_where( $q );
		$ids                  = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT e.id FROM {$t} e WHERE {$where} AND e.field <> 'merge_into'", $args ) ) );
		$do                   = 'approve';
	} elseif ( ! $ids && $listings && in_array( $do, array( 'approve', 'reject' ), true ) ) {
		$ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$t} WHERE status = 'pending' AND field <> 'merge_into' AND listing_id IN ( " . implode( ', ', array_fill( 0, count( $listings ), '%d' ) ) . ' )', $listings ) ) );
	}
	// phpcs:enable WordPress.DB
	$fn   = array(
		'approve' => 'aw_enrich_approve',
		'reject'  => 'aw_enrich_reject',
		'undo'    => 'aw_enrich_undo',
	)[ $do ] ?? '';
	$done = 0;
	foreach ( $ids as $id ) {
		$done += $fn && $fn( $id ) ? 1 : 0;
	}
	return rest_ensure_response(
		array(
			'done'    => $done,
			/* translators: %d: number of values. */
			'message' => sprintf( _n( '%d updated.', '%d updated.', $done, 'aw-sourcing' ), $done ),
		)
	);
}

/* ---------- Settings ---------- */

/** "Workers use these lists", as stored (aw_sources_config() would seed the option on a first read). */
function sourceweb_live_now(): bool {
	$c = get_option( 'aw_sources' );
	return is_array( $c ) && ! empty( $c['live'] );
}

/**
 * Each group's values now, as the screen edits them.
 *
 * @return array group => key => value.
 */
function sourceweb_settings_now(): array {
	$out = array(
		'workers'    => array( 'live' => sourceweb_live_now() ),
		'enrichment' => array_map( 'boolval', aw_enrich_sources_on() ) + array( 'live_cap' => (int) get_option( 'aw_enrich_live_cap', '20' ) ),
	);
	if ( function_exists( 'aw_social_on' ) ) {
		$out['social'] = array(
			'aw_social_on'   => '1' === (string) get_option( 'aw_social_on', '' ),
			'aw_social_show' => '1' === (string) get_option( 'aw_social_show', '' ),
		);
	}
	return $out;
}

/** Connected services: each enrichment source and API, its status, and what can be set on its row. */
function sourceweb_services(): array {
	$now   = sourceweb_settings_now();
	$on    = aw_enrich_sources_on();
	$out   = array();
	$tok   = defined( 'AW_GITHUB_DISPATCH_TOKEN' ) && '' !== (string) AW_GITHUB_DISPATCH_TOKEN;
	$prob  = function_exists( 'aw_workers_problems' ) ? count( (array) aw_workers_problems() ) : 0;
	$out[] = array(
		'slug'    => 'github',
		'name'    => __( 'Workers (GitHub Actions)', 'aw-sourcing' ),
		'status'  => $tok ? __( 'Run now token set', 'aw-sourcing' ) : __( 'Run now token not set', 'aw-sourcing' ),
		'tone'    => $tok ? ( $prob ? 'warn' : '' ) : 'warn',
		/* translators: 1: workflows, 2: late or failed. */
		'summary' => sprintf( __( '%1$d workflows; %2$d late or failed. The token is kept in wp-config.php.', 'aw-sourcing' ), defined( 'AW_WORKERS' ) ? count( AW_WORKERS ) : 0, $prob ),
		'links'   => array(
			array(
				'label' => __( 'Workers', 'aw-sourcing' ),
				'url'   => admin_url( aw_src_tab_path( 'workers' ) ),
			),
		),
	);
	$key   = '' !== (string) get_option( 'aw_enrich_google_key', '' );
	$cap   = (int) $now['enrichment']['live_cap'];
	$out[] = array(
		'slug'    => 'google_places',
		'name'    => __( 'Google Maps (Places)', 'aw-sourcing' ),
		'status'  => ( $key ? __( 'Key set', 'aw-sourcing' ) : __( 'Key not set', 'aw-sourcing' ) ) . ' · ' . ( $on['google_places'] ? __( 'On', 'aw-sourcing' ) : __( 'Off', 'aw-sourcing' ) ),
		'tone'    => $key ? '' : 'warn',
		/* translators: 1: spent live this month, 2: monthly cap, 3: the worker's spend. */
		'summary' => sprintf( __( 'This month: $%1$s live of a $%2$d cap; the worker spent $%3$s.', 'aw-sourcing' ), number_format_i18n( aw_enrich_spent( 'live' ), 2 ), $cap, number_format_i18n( aw_enrich_spent( 'worker' ), 2 ) ),
		'source'  => 'google_places',
		'on'      => (bool) $on['google_places'],
		'help'    => aw_enrich_sources()['google_places'][3],
		'key'     => array( 'set' => $key ),
		'cap'     => $cap,
	);
	foreach ( aw_enrich_sources() as $k => $s ) {
		if ( 'google_places' === $k ) {
			continue;
		}
		$out[] = array(
			'slug'    => $k,
			'name'    => (string) $s[0],
			'status'  => $on[ $k ] ? __( 'On', 'aw-sourcing' ) : __( 'Off', 'aw-sourcing' ),
			'tone'    => '',
			'summary' => (string) $s[3],
			'source'  => $k,
			'on'      => (bool) $on[ $k ],
			'help'    => __( 'Used by the enrichment worker from its next run.', 'aw-sourcing' ),
		);
	}
	$ai    = aw_sourcing_ai_counts();
	$out[] = array(
		'slug'    => 'ai',
		'name'    => __( 'AI providers', 'aw-sourcing' ),
		/* translators: %d: keys that work. */
		'status'  => $ai['saved'] ? sprintf( _n( '%d key works', '%d keys work', $ai['works'], 'aw-sourcing' ), $ai['works'] ) : __( 'No keys', 'aw-sourcing' ),
		'tone'    => $ai['saved'] && ! $ai['works'] ? 'warn' : '',
		'summary' => $ai['providers'] ? implode( ', ', $ai['providers'] ) : __( 'Free models only. Keys are set on the AI models screen.', 'aw-sourcing' ),
		'links'   => array(
			array(
				'label' => __( 'AI models', 'aw-sourcing' ),
				'url'   => admin_url( aw_src_tab_path( 'ai' ) ),
			),
		),
	);
	// As aw_relay_hosts(), but from the options as stored: aw_sources_config() would seed aw_sources on a read.
	$stored = get_option( 'aw_sources' );
	if ( function_exists( 'aw_sourcing_phase' ) && aw_sourcing_phase() >= 1 ) {
		$relay = count( (array) aw_src_relay_hosts_from_pubs( function_exists( 'aw_publishers' ) ? aw_publishers() : array() ) );
	} else {
		$relay = count( (array) aw_src_relay_legacy( is_array( $stored ) ? $stored : array(), AW_RELAY_DEFAULT ) );
	}
	$out[] = array(
		'slug'    => 'relay',
		'name'    => __( 'Relay', 'aw-sourcing' ),
		/* translators: %d: hosts. */
		'status'  => sprintf( _n( '%d host allowed', '%d hosts allowed', $relay, 'aw-sourcing' ), $relay ),
		'tone'    => '',
		/* translators: %d: requests logged. */
		'summary' => sprintf( __( 'For publishers that refuse GitHub; %d requests in the log.', 'aw-sourcing' ), count( (array) get_option( 'aw_relay_log', array() ) ) ),
		'links'   => array(
			array(
				'label' => __( 'Relay log', 'aw-sourcing' ),
				'url'   => admin_url( aw_src_tab_path( 'relay-log' ) ),
			),
		),
	);
	return $out;
}

/** Settings: the services, then the groups with today's values. */
function sourceweb_settings_payload(): array {
	$now    = sourceweb_settings_now();
	$groups = array(
		array(
			'slug'        => 'workers',
			'label'       => __( 'Workers', 'aw-sourcing' ),
			'description' => __( 'What the workers read at the start of every run.', 'aw-sourcing' ),
			'fields'      => array(
				array(
					'key'   => 'live',
					'type'  => 'switch',
					'label' => __( 'Workers use these lists', 'aw-sourcing' ),
					'help'  => __( 'Off: the workers use sources.yaml in the workers repository. On: they read the lists on these screens, and Sources sync copies them into sources.yaml.', 'aw-sourcing' ),
					'value' => $now['workers']['live'],
				),
			),
		),
		array(
			'slug'        => 'enrichment',
			'label'       => __( 'Directory enrichment', 'aw-sourcing' ),
			'description' => __( 'The live Google block on listing pages. Each source is switched on or off on its row under Connected services.', 'aw-sourcing' ),
			'fields'      => array(
				array(
					'key'     => 'live_cap',
					'type'    => 'number',
					'label'   => __( 'Live block: monthly cap (USD)', 'aw-sourcing' ),
					'help'    => __( 'The live Google rating and hours stop for the month when this is spent. The worker has its own cap on GitHub.', 'aw-sourcing' ),
					'value'   => $now['enrichment']['live_cap'],
					'default' => 20,
				),
			),
		),
	);
	if ( isset( $now['social'] ) ) {
		$groups[] = array(
			'slug'        => 'social',
			'label'       => __( 'Social posts', 'aw-sourcing' ),
			'description' => __( 'Other people’s public posts about a campaign or an item. Every one waits in To review; nothing is published on its own. The block on pages needs the owner’s yes to its design first.', 'aw-sourcing' ),
			'fields'      => array(
				array(
					'key'   => 'aw_social_on',
					'type'  => 'switch',
					'label' => __( 'Social posts type', 'aw-sourcing' ),
					'help'  => __( 'The Social posts list, its intake and the hashtag box on each campaign. Off: hidden; saved records are kept.', 'aw-sourcing' ),
					'value' => $now['social']['aw_social_on'],
				),
				array(
					'key'   => 'aw_social_show',
					'type'  => 'switch',
					'label' => __( 'Show on pages', 'aw-sourcing' ),
					'help'  => __( 'Approved social posts on the pages they are linked to. Needs the type on.', 'aw-sourcing' ),
					'value' => $now['social']['aw_social_show'],
				),
			),
		);
	}
	$elsewhere = array();
	foreach (
		array(
			''         => __( 'Sources', 'aw-sourcing' ),
			'keywords' => __( 'Keywords', 'aw-sourcing' ),
			'rules'    => __( 'Rules', 'aw-sourcing' ),
			'ai'       => __( 'AI models', 'aw-sourcing' ),
			'workers'  => __( 'Workers', 'aw-sourcing' ),
		) as $tab => $label
	) {
		$elsewhere[] = array(
			'label' => $label,
			'url'   => admin_url( aw_src_tab_path( $tab ) ),
		);
	}
	return array(
		'services'  => sourceweb_services(),
		'groups'    => $groups,
		'elsewhere' => $elsewhere,
	);
}

/**
 * POST settings/<group>: only the values that changed are written, each through its own save path.
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_save_group( WP_REST_Request $request ) {
	$group = (string) $request['group'];
	$now   = sourceweb_settings_now();
	if ( ! isset( $now[ $group ] ) ) {
		return new WP_Error( 'rest_invalid_param', __( 'No such group.', 'aw-sourcing' ), array( 'status' => 400 ) );
	}
	$changed = sourceweb_changed( $now[ $group ], (array) $request['values'] );
	if ( 'workers' === $group && isset( $changed['live'] ) ) {
		aw_sources_live_save( $changed['live'] );
	}
	if ( 'enrichment' === $group ) {
		$cap = $changed['live_cap'] ?? null;
		unset( $changed['live_cap'] );
		$next = sourceweb_enrich_sources_next( aw_enrich_sources_on(), $changed );
		if ( null !== $next ) {
			if ( ! function_exists( 'add_settings_error' ) ) {
				require_once ABSPATH . 'wp-admin/includes/template.php'; // The sanitize callback adds the "Saved" notice.
			}
			update_option( 'aw_enrich_sources', aw_enrich_sources_sanitize( $next + array( '_aw_tab' => 'enrich' ) ) );
		}
		if ( null !== $cap ) {
			$cap = (string) max( 0, (int) $cap );
			update_option( 'aw_enrich_live_cap', $cap );
			kits_sourcing_log( 'enrich', 'Live block monthly cap: $' . $cap );
			$changed['live_cap'] = $cap;
		}
	}
	if ( 'social' === $group ) {
		foreach ( $changed as $k => $v ) {
			update_option( $k, $v ? '1' : '' ); // As the Kits → Modules setting's own clean-up stores it.
			kits_sourcing_log( 'settings', ( 'aw_social_on' === $k ? 'Social posts type' : 'Social posts shown on pages' ) . ': ' . ( $v ? 'on' : 'off' ) );
		}
	}
	return rest_ensure_response(
		array(
			'saved'    => (bool) $changed,
			'message'  => $changed ? __( 'Saved. The workers use this from their next run.', 'aw-sourcing' ) : __( 'Nothing changed.', 'aw-sourcing' ),
			'settings' => sourceweb_settings_payload(),
		)
	);
}

/**
 * POST settings/google-key: a new key replaces the saved one; an empty one keeps it (as today's field).
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_google_key( WP_REST_Request $request ) {
	$key = trim( sanitize_text_field( (string) $request['key'] ) );
	if ( '' !== $key && (string) get_option( 'aw_enrich_google_key', '' ) !== $key ) {
		update_option( 'aw_enrich_google_key', aw_enrich_google_key_sanitize( $key ) );
		kits_sourcing_log( 'enrich', 'Directory enrichment saved (new Google key)' );
		$saved = true;
	}
	return rest_ensure_response(
		array(
			'saved'    => ! empty( $saved ),
			'set'      => '' !== (string) get_option( 'aw_enrich_google_key', '' ),
			'message'  => ! empty( $saved ) ? __( 'Key saved.', 'aw-sourcing' ) : __( 'Nothing changed: the saved key is kept.', 'aw-sourcing' ),
			'settings' => sourceweb_settings_payload(),
		)
	);
}
