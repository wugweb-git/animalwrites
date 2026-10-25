<?php
/**
 * Directory enrichment in WP Admin:
 * - Directory → Enrichment review: current | proposed | source | confidence, approve / reject one by one or in bulk,
 *   filters (source, confidence, state, field, Invite to claim, Reported closed, Possible duplicate), and the change
 *   log of applied values with Undo.
 * - Settings → Sourcing → Directory enrichment: each source on or off, the live Google block's key and monthly cap,
 *   progress.
 * - On each listing: the source icon and name beside every field the worker set (hover: the page and the date).
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_ENRICH_CAP = 'edit_others_posts';

/* ---------- Source icons ---------- */

/** Register the sources' hosts once in the publisher registry, so the favicon job fetches each icon once. */
function aw_enrich_register_hosts() {
	if ( ! function_exists( 'aw_publishers' ) || get_option( 'aw_enrich_hosts' ) === '1' ) {
		return;
	}
	$pubs = aw_publishers();
	foreach ( aw_enrich_sources() as $k => $s ) {
		$h = $s[1];
		if ( $h && ! isset( $pubs[ $h ] ) ) {
			$pubs[ $h ] = array( 'status' => 'neutral', 'name' => $s[0], 'count' => 1, 'first' => wp_date( 'Y-m-d' ), 'last' => wp_date( 'Y-m-d' ), 'kind' => 'directory-source' );
		}
	}
	foreach ( array( 'facebook.com' => 'Facebook' ) as $h => $n ) {
		if ( ! isset( $pubs[ $h ] ) ) {
			$pubs[ $h ] = array( 'status' => 'neutral', 'name' => $n, 'count' => 1, 'first' => wp_date( 'Y-m-d' ), 'last' => wp_date( 'Y-m-d' ), 'kind' => 'directory-source' );
		}
	}
	update_option( AW_PUB_OPTION, $pubs, false );
	update_option( 'aw_enrich_hosts', '1', false );
}
add_action( 'admin_init', 'aw_enrich_register_hosts' );

/**
 * A source's icon: its site's favicon from the Media Library (fetched once per host), the organisation's logo for its
 * own site and claim, else its first letter.
 *
 * @param array $src {key, name, url}.
 * @param int   $lid Listing ID.
 * @return string
 */
function aw_enrich_icon( $src, $lid = 0 ) {
	$key  = (string) ( $src['key'] ?? '' );
	$host = aw_enrich_sources()[ $key ][1] ?? '';
	if ( 'social' === $key && false !== strpos( (string) ( $src['url'] ?? '' ), 'facebook.com' ) ) {
		$host = 'facebook.com';
	}
	$url = $host && function_exists( 'aw_favicon_url' ) ? aw_favicon_url( $host ) : '';
	if ( ! $url && in_array( $key, array( 'website', 'claim' ), true ) && $lid && has_post_thumbnail( $lid ) ) {
		$url = (string) get_the_post_thumbnail_url( $lid, 'thumbnail' );
	}
	$name = (string) ( $src['name'] ?? $key );
	return $url
		? '<img class="aw-src-icon" src="' . esc_url( $url ) . '" alt="" width="16" height="16" loading="lazy" decoding="async">'
		: '<span class="aw-src-icon aw-src-icon--letter" aria-hidden="true">' . esc_html( mb_strtoupper( mb_substr( $name, 0, 1 ) ) ) . '</span>';
}

/** Icon + name of each source, linked to the exact page, with the date on hover. */
function aw_enrich_sources_html( $sources, $lid = 0 ) {
	$out = array();
	foreach ( (array) $sources as $s ) {
		$when  = ! empty( $s['fetched_at'] ) ? wp_date( 'j M Y, g:i a', strtotime( $s['fetched_at'] ) ) . ' IST' : '';
		$title = trim( ( $s['url'] ?? '' ) . ( $when ? ' · ' . $when : '' ), ' ·' );
		$inner = aw_enrich_icon( $s, $lid ) . ' <span>' . esc_html( $s['name'] ?? '' ) . '</span>';
		$out[] = ! empty( $s['url'] )
			? '<a class="aw-enrich-src" href="' . esc_url( $s['url'] ) . '" target="_blank" rel="noopener nofollow" title="' . esc_attr( $title ) . '">' . $inner . '</a>'
			: '<span class="aw-enrich-src" title="' . esc_attr( $title ) . '">' . $inner . '</span>';
	}
	return implode( ' ', $out );
}

add_action(
	'admin_head',
	function () {
		echo '<style>.aw-enrich-src{display:inline-flex;align-items:center;gap:4px;margin-right:8px;text-decoration:none;white-space:nowrap}.aw-enrich-src img,.aw-enrich-src .aw-src-icon--letter{width:16px;height:16px;border-radius:3px}.aw-src-icon--letter{display:inline-grid;place-items:center;background:#dcdcde;font-size:10px;font-weight:600}.aw-enrich-conf{display:inline-block;padding:1px 6px;border-radius:3px;font-size:11px}.aw-enrich-conf--high{background:#d1f0d9}.aw-enrich-conf--medium{background:#fff2c6}.aw-enrich-conf--low{background:#fbd5d5}.aw-enrich-val{max-width:280px;overflow-wrap:anywhere}.aw-enrich-prov{margin:4px 0 0;font-size:12px;color:#50575e}</style>';
	}
);

/* ---------- Beside each field on the listing ---------- */

/** Meta key => enrichment field. */
function aw_enrich_meta_fields() {
	$out = array();
	foreach ( aw_enrich_fields() as $f => $def ) {
		$key         = array( 'phones' => 'aw_phones', 'hours' => 'aw_opening_hours', 'photos' => 'aw_gallery' )[ $def[1] ] ?? $def[1];
		$out[ $key ] = $out[ $key ] ?? $f;
	}
	return $out;
}

/** "Source: icon name" line for one field of the listing being edited. */
function aw_enrich_prov_line( $lid, $field ) {
	$p = aw_enrich_provenance( $lid, $field );
	if ( ! $p ) {
		return '';
	}
	return '<p class="aw-enrich-prov">Source: ' . aw_enrich_sources_html( $p['sources'], $lid ) . ' <span class="aw-enrich-conf aw-enrich-conf--' . esc_attr( $p['confidence'] ) . '">' . esc_html( $p['confidence'] ) . '</span> · ' . esc_html( 'approved' === $p['status'] ? 'approved' : 'applied automatically' ) . '</p>';
}

// SCF fields (Organisation details group): the line under the field.
add_action(
	'acf/render_field',
	function ( $field ) {
		global $post;
		if ( ! $post || 'aw_listing' !== $post->post_type ) {
			return;
		}
		$map = aw_enrich_meta_fields();
		$f   = $map[ $field['name'] ?? '' ] ?? '';
		if ( $f ) {
			echo aw_enrich_prov_line( $post->ID, $f ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}
);

// Every field the worker set, with its source, in one box (also covers the short bio, logo, services and the box without SCF).
add_action(
	'add_meta_boxes_aw_listing',
	function () {
		add_meta_box( 'aw-enrich-prov', 'Where these details come from', 'aw_enrich_prov_box', 'aw_listing', 'side', 'default' );
	}
);

function aw_enrich_prov_box( $post ) {
	$rows = '';
	foreach ( aw_enrich_fields() as $f => $def ) {
		$p = aw_enrich_provenance( $post->ID, $f );
		if ( $p ) {
			$rows .= '<li><strong>' . esc_html( $def[0] ) . '</strong><br>' . aw_enrich_sources_html( $p['sources'], $post->ID ) . '</li>';
		}
	}
	$flags = aw_enrich_flags( $post->ID );
	$chk   = (string) get_post_meta( $post->ID, 'aw_enrich_checked', true );
	echo $rows ? '<ul>' . $rows . '</ul>' : '<p>Nothing set by the enrichment worker yet.</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
	if ( $flags ) {
		echo '<p>' . esc_html( implode( ' · ', array_map( fn( $k ) => aw_enrich_flag_label( $k ), array_keys( $flags ) ) ) ) . '</p>';
	}
	echo '<p class="description">' . ( $chk ? 'Last checked ' . esc_html( wp_date( 'j M Y', strtotime( $chk ) ) ) . '. ' : '' ) . '<a href="' . esc_url( admin_url( 'edit.php?post_type=aw_listing&page=aw-enrich-review&listing=' . $post->ID . '&status=all' ) ) . '">Changes and proposals</a></p>';
	wp_nonce_field( 'aw_enrich_identity', 'aw_enrich_identity_nonce' );
	echo '<p><label><input type="checkbox" name="aw_enrich_identity" value="1"' . checked( ! empty( $flags['identity'] ), true, false ) . '> Identity unresolved</label></p>';
	echo '<p class="description">Tick when it is not known which organisation this listing is: enrichment then proposes nothing for it.</p>';
}

// Save the Identity unresolved box (the date it was ticked is kept as its detail).
add_action(
	'save_post_aw_listing',
	function ( $id ) {
		if ( ! isset( $_POST['aw_enrich_identity_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['aw_enrich_identity_nonce'] ), 'aw_enrich_identity' ) || ! current_user_can( 'edit_post', $id ) || wp_is_post_revision( $id ) ) {
			return;
		}
		$on  = ! empty( $_POST['aw_enrich_identity'] );
		$was = aw_enrich_identity_unresolved( $id );
		if ( $on !== $was ) {
			aw_enrich_flag( $id, 'identity', $on ? wp_date( 'Y-m-d' ) : false );
		}
	}
);

function aw_enrich_flag_label( $k ) {
	return array( 'invite_claim' => 'Invite to claim', 'reported_closed' => 'Reported closed', 'duplicate' => 'Possible duplicate', 'identity' => 'Identity unresolved' )[ $k ] ?? $k;
}

/* ---------- Directory → Enrichment review ---------- */

add_action(
	'admin_menu',
	function () {
		$n = 0;
		if ( get_option( 'aw_enrich_db' ) ) {
			global $wpdb;
			$n = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . aw_enrich_table() . " WHERE status = 'pending'" ); // phpcs:ignore WordPress.DB
		}
		add_submenu_page( 'edit.php?post_type=aw_listing', 'Enrichment review', 'Enrichment review' . ( $n && function_exists( 'aw_menu_count' ) ? aw_menu_count( $n ) : '' ), AW_ENRICH_CAP, 'aw-enrich-review', 'aw_enrich_review_page' );
	},
	20
);

/** Approve, reject, undo (one, or the ticked rows, or every high one in the current filter). */
add_action(
	'admin_post_aw_enrich_decide',
	function () {
		if ( ! current_user_can( AW_ENRICH_CAP ) || ! check_admin_referer( 'aw_enrich_decide' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$do  = sanitize_key( $_REQUEST['do'] ?? ( $_REQUEST['bulk'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$ids = array_filter( array_map( 'absint', (array) ( $_REQUEST['row'] ?? array() ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( 'approve_high' === $do ) {
			global $wpdb;
			list( $where, $args ) = aw_enrich_where( array_merge( wp_unslash( $_REQUEST ), array( 'status' => 'pending', 'confidence' => 'high' ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT e.id FROM ' . aw_enrich_table() . " e WHERE {$where} AND e.field <> 'merge_into'", $args ) ) ); // phpcs:ignore WordPress.DB -- merges are approved one by one
			$do  = 'approve';
		}
		$done = 0;
		foreach ( $ids as $id ) {
			$fn    = array( 'approve' => 'aw_enrich_approve', 'reject' => 'aw_enrich_reject', 'undo' => 'aw_enrich_undo' )[ $do ] ?? '';
			$done += $fn && $fn( $id ) ? 1 : 0;
		}
		$back = wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=aw_listing&page=aw-enrich-review' );
		wp_safe_redirect( add_query_arg( 'done', $done, remove_query_arg( 'done', $back ) ) );
		exit;
	}
);

/**
 * SQL filter from the request: status, source, confidence, state, field, flag, listing.
 *
 * @return array{0: string, 1: array}
 */
function aw_enrich_where( $q ) {
	global $wpdb;
	$w    = array( '1=1' );
	$args = array();
	$st   = sanitize_key( $q['status'] ?? 'pending' );
	if ( 'applied' === $st ) {
		$w[] = "e.status IN ('auto-applied','approved')";
	} elseif ( 'all' !== $st ) {
		$w[]    = 'e.status = %s';
		$args[] = $st;
	}
	foreach ( array( 'confidence', 'field' ) as $k ) {
		if ( ! empty( $q[ $k ] ) ) {
			$w[]    = "e.{$k} = %s";
			$args[] = sanitize_key( $q[ $k ] );
		}
	}
	if ( ! empty( $q['source'] ) ) {
		$w[]    = 'e.sources LIKE %s';
		$args[] = '%"key":"' . $wpdb->esc_like( sanitize_key( $q['source'] ) ) . '"%';
	}
	if ( ! empty( $q['listing'] ) ) {
		$w[]    = 'e.listing_id = %d';
		$args[] = absint( $q['listing'] );
	}
	if ( ! empty( $q['state'] ) ) {
		$w[]    = "e.listing_id IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'aw_state_code' AND meta_value = %s)";
		$args[] = sanitize_text_field( $q['state'] );
	}
	if ( ! empty( $q['flag'] ) ) {
		$w[]    = "e.listing_id IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'aw_enrich_flags' AND meta_value LIKE %s)";
		$args[] = '%"' . $wpdb->esc_like( sanitize_key( $q['flag'] ) ) . '"%';
	}
	if ( ! $args ) {
		$w[]    = '%d = %d';
		$args[] = 1;
		$args[] = 1;
	}
	return array( implode( ' AND ', $w ), $args );
}

/** A value for the table. */
function aw_enrich_show( $field, $v ) {
	if ( 'place' === $field ) {
		return esc_html( '' !== $v && array() !== $v ? aw_enrich_place_label( $v ) : '' );
	}
	if ( 'merge_into' === $field ) {
		if ( is_array( $v ) ) { // an applied merge's undo record: nothing was there before
			return '';
		}
		return (int) $v ? '<a href="' . esc_url( (string) get_edit_post_link( (int) $v ) ) . '">#' . (int) $v . ' ' . esc_html( html_entity_decode( get_the_title( (int) $v ), ENT_QUOTES ) ) . '</a>' : '';
	}
	if ( 'hours' === $field && is_array( $v ) ) {
		return esc_html( implode( '; ', array_map( fn( $r ) => ( $r['day'] ?? '' ) . ' ' . ( ! empty( $r['closed'] ) ? 'closed' : ( $r['open'] ?? '' ) . '–' . ( $r['close'] ?? '' ) ), $v ) ) );
	}
	if ( in_array( $field, array( 'logo', 'photos' ), true ) ) {
		$out = '';
		foreach ( array_slice( array_filter( (array) $v, 'is_string' ), 0, 6 ) as $u ) {
			$out .= '<img src="' . esc_url( $u ) . '" alt="" width="48" height="48" style="object-fit:cover;margin:0 4px 4px 0" loading="lazy" referrerpolicy="no-referrer">';
		}
		return $out;
	}
	if ( is_array( $v ) ) {
		$v = implode( ', ', array_map( fn( $x ) => is_scalar( $x ) ? (string) $x : wp_json_encode( $x ), $v ) );
	}
	$v = (string) $v;
	return preg_match( '#^https?://#', $v ) ? '<a href="' . esc_url( $v ) . '" target="_blank" rel="noopener nofollow">' . esc_html( $v ) . '</a>' : esc_html( $v );
}

function aw_enrich_review_page() {
	global $wpdb;
	if ( ! get_option( 'aw_enrich_db' ) ) {
		aw_enrich_install();
	}
	$q    = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification
	$st   = sanitize_key( $q['status'] ?? 'pending' );
	$page = max( 1, (int) ( $q['paged'] ?? 1 ) );
	$per  = 50;
	list( $where, $args ) = aw_enrich_where( $q );
	$t     = aw_enrich_table();
	$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} e WHERE {$where}", $args ) ); // phpcs:ignore WordPress.DB
	$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT e.* FROM {$t} e WHERE {$where} ORDER BY FIELD(e.confidence,'high','medium','low'), e.listing_id, e.id DESC LIMIT %d OFFSET %d", array_merge( $args, array( $per, ( $page - 1 ) * $per ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB
	$base  = admin_url( 'edit.php?post_type=aw_listing&page=aw-enrich-review' );
	$prog  = aw_enrich_progress();

	echo '<div class="wrap"><h1>Enrichment review</h1>';
	if ( isset( $q['done'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . (int) $q['done'] . ' updated.</p></div>';
	}
	echo '<p>' . (int) $prog['checked'] . ' of ' . (int) $prog['total'] . ' listings checked · ' . (int) $prog['pending'] . ' proposals waiting. High-confidence values for empty fields of unclaimed listings are applied automatically and listed under Applied (with Undo).</p>';
	echo '<ul class="subsubsub">';
	foreach ( array( 'pending' => 'Waiting', 'applied' => 'Applied', 'rejected' => 'Rejected', 'failed' => 'Failed', 'undone' => 'Undone', 'all' => 'All' ) as $k => $l ) {
		echo '<li><a href="' . esc_url( add_query_arg( 'status', $k, $base ) ) . '"' . ( $st === $k ? ' class="current"' : '' ) . '>' . esc_html( $l ) . '</a> | </li>';
	}
	echo '</ul><br class="clear">';

	// Filters.
	echo '<form method="get" style="margin:8px 0"><input type="hidden" name="post_type" value="aw_listing"><input type="hidden" name="page" value="aw-enrich-review"><input type="hidden" name="status" value="' . esc_attr( $st ) . '">';
	$sel = function ( $name, $opts, $label ) use ( $q ) {
		echo '<select name="' . esc_attr( $name ) . '"><option value="">' . esc_html( $label ) . '</option>';
		foreach ( $opts as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( (string) ( $q[ $name ] ?? '' ), (string) $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select> ';
	};
	$sel( 'source', array_map( fn( $s ) => $s[0], aw_enrich_sources() ), 'All sources' );
	$sel( 'confidence', array( 'high' => 'High', 'medium' => 'Medium', 'low' => 'Low' ), 'Any confidence' );
	$states = array();
	foreach ( function_exists( 'aw_place_states' ) ? aw_place_states() : array() as $s ) {
		$states[ $s->code ] = $s->name;
	}
	if ( $states ) {
		$sel( 'state', $states, 'All states' );
	}
	$sel( 'field', array_map( fn( $f ) => $f[0], aw_enrich_fields() ), 'All fields' );
	$sel( 'flag', array( 'invite_claim' => 'Invite to claim', 'reported_closed' => 'Reported closed', 'duplicate' => 'Possible duplicate', 'identity' => 'Identity unresolved' ), 'Any flag' );
	submit_button( 'Filter', 'secondary', '', false );
	echo '</form>';

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_enrich_decide">';
	wp_nonce_field( 'aw_enrich_decide' );
	foreach ( array( 'source', 'confidence', 'state', 'field', 'flag', 'listing' ) as $k ) {
		if ( ! empty( $q[ $k ] ) ) {
			echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $q[ $k ] ) . '">';
		}
	}
	$bulk   = 'pending' === $st ? array( 'approve' => 'Approve', 'reject' => 'Reject', 'approve_high' => 'Approve all high (this filter)' ) : ( 'failed' === $st ? array() : array( 'undo' => 'Undo' ) ); // a failed row has nothing to undo
	$cells  = array();
	$labels = aw_enrich_fields();
	foreach ( $rows as $r ) {
		$r     = aw_enrich_decode( $r );
		$lid   = (int) $r['listing_id'];
		$flags = aw_enrich_flags( $lid );
		$fl    = $flags ? '<br><small>' . esc_html( implode( ' · ', array_map( 'aw_enrich_flag_label', array_keys( $flags ) ) ) ) . '</small>' : '';
		if ( ! empty( $flags['duplicate'] ) ) {
			$fl .= '<br><small>Same ' . esc_html( implode( ', ', array_unique( (array) $flags['duplicate'] ) ) ) . ' as ' . implode( ', ', array_map( fn( $o ) => '<a href="' . esc_url( get_edit_post_link( (int) $o ) ) . '">' . esc_html( get_the_title( (int) $o ) ) . '</a>', array_keys( (array) $flags['duplicate'] ) ) ) . '</small>';
		}
		$cur = 'pending' === $r['status'] ? aw_enrich_current( $lid, $r['field'] ) : $r['old_value'];
		if ( 'pending' !== $r['status'] && in_array( $r['field'], array( 'logo', 'photos', 'phone', 'whatsapp', 'emergency', 'hours' ), true ) ) {
			$cur = 'logo' === $r['field'] ? ( $cur ? (string) wp_get_attachment_url( (int) $cur ) : '' ) : ( 'photos' === $r['field'] ? array_map( fn( $p ) => (string) wp_get_attachment_url( (int) $p ), array_filter( explode( ',', (string) $cur ) ) ) : ( is_array( $cur ) && isset( $cur[0]['number'] ) ? wp_list_pluck( $cur, 'number' ) : $cur ) );
		}
		$act = array();
		foreach ( 'pending' === $r['status'] ? array( 'approve' => 'Approve', 'reject' => 'Reject' ) : ( in_array( $r['status'], array( 'auto-applied', 'approved' ), true ) ? array( 'undo' => 'Undo' ) : array() ) as $do => $l ) {
			$act[] = '<a class="button button-small' . ( 'approve' === $do ? ' button-primary' : '' ) . '" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=aw_enrich_decide&do=' . $do . '&row[]=' . (int) $r['id'] ), 'aw_enrich_decide' ) ) . '">' . esc_html( $l ) . '</a>';
		}
		$cells[] = array(
			'cb'         => '<input class="aw-er" type="checkbox" name="row[]" value="' . (int) $r['id'] . '">',
			'listing'    => '<a href="' . esc_url( (string) get_edit_post_link( $lid ) ) . '"><strong>' . esc_html( get_the_title( $lid ) ) . '</strong></a> <a href="' . esc_url( (string) get_permalink( $lid ) ) . '" target="_blank" aria-label="View">↗</a>' . wp_kses_post( $fl ),
			'field'      => esc_html( $labels[ $r['field'] ][0] ?? $r['field'] ),
			'current'    => '<div class="aw-enrich-val">' . aw_enrich_show( $r['field'], $cur ) . '</div>',
			'proposed'   => '<div class="aw-enrich-val"><strong>' . aw_enrich_show( $r['field'], $r['value'] ) . '</strong>' . ( $r['note'] ? '<br><small>' . esc_html( $r['note'] ) . '</small>' : '' ) . '</div>',
			'source'     => aw_enrich_sources_html( $r['sources'], $lid ),
			'confidence' => '<span class="aw-enrich-conf aw-enrich-conf--' . esc_attr( $r['confidence'] ) . '">' . esc_html( ucfirst( $r['confidence'] ) ) . '</span>',
			'status'     => esc_html( ucfirst( $r['status'] ) ) . '<br><small>' . esc_html( wp_date( 'j M Y', strtotime( $r['created'] ) ) ) . '</small>',
			'act'        => implode( ' ', $act ),
		);
	}
	if ( ! class_exists( 'WP_List_Table' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	}
	require_once __DIR__ . '/class-aw-sourcing-enrichment-table.php';
	( new AW_Sourcing_Enrichment_Table( $cells, $total, $per, $bulk ) )->display();
	echo '</form></div>';
}

/* ---------- Settings → Sourcing → Directory enrichment (Settings API) ---------- */

add_filter(
	'kits_sourcing_tabs',
	function ( $tabs ) {
		$tabs['enrich'] = array( 'Directory enrichment', 'aw_enrich_settings_tab', true );
		return $tabs;
	}
);

add_action(
	'admin_init',
	function () {
		register_setting( 'aw_sourcing_enrich', 'aw_enrich_sources', array( 'type' => 'array', 'sanitize_callback' => 'aw_enrich_sources_sanitize', 'show_in_rest' => false ) );
		register_setting( 'aw_sourcing_enrich', 'aw_enrich_google_key', array( 'type' => 'string', 'sanitize_callback' => 'aw_enrich_google_key_sanitize', 'show_in_rest' => false ) );
		register_setting( 'aw_sourcing_enrich', 'aw_enrich_live_cap', array( 'type' => 'string', 'sanitize_callback' => fn( $v ) => (string) max( 0, (int) $v ) === (string) get_option( 'aw_enrich_live_cap', '20' ) ? get_option( 'aw_enrich_live_cap', '20' ) : (string) max( 0, (int) $v ), 'show_in_rest' => false ) );
		add_settings_section(
			'aw_enrich',
			'',
			function () {
				$prog = aw_enrich_progress();
				aw_sourcing_settings_marker( 'aw_enrich_sources', 'enrich' );
				echo '<p>The enrichment worker (GitHub, after the SEO scores at 05:15 IST) fills empty listing details from these sources. ' . (int) $prog['checked'] . ' of ' . (int) $prog['total'] . ' listings checked; ' . (int) $prog['pending'] . ' proposals in <a href="' . esc_url( admin_url( 'edit.php?post_type=aw_listing&page=aw-enrich-review' ) ) . '">Enrichment review</a>.</p>';
			},
			'aw-sourcing-enrich'
		);
		foreach ( aw_enrich_sources() as $k => $src ) {
			add_settings_field(
				'aw_enrich_src_' . $k,
				$src[0],
				function () use ( $k, $src ) {
					echo '<label><input type="checkbox" id="aw-es-' . esc_attr( $k ) . '" name="aw_enrich_sources[' . esc_attr( $k ) . ']" value="1"' . checked( aw_enrich_sources_on()[ $k ], true, false ) . '> On</label><p class="description">' . esc_html( $src[3] ) . '</p>';
				},
				'aw-sourcing-enrich',
				'aw_enrich'
			);
		}
		add_settings_field(
			'aw_enrich_google_key',
			'Google Places key (live block)',
			function () {
				$key = (string) get_option( 'aw_enrich_google_key', '' );
				echo '<input type="password" class="regular-text" id="aw-eg-key" name="aw_enrich_google_key" value="" placeholder="' . esc_attr( $key ? 'Saved (' . substr( $key, -4 ) . '). Type a new one to replace it' : 'Not set: the live Google block is hidden' ) . '" autocomplete="off"><p class="description">For the live Google rating, hours and open or closed on listing pages (cached 24 hours, never stored on the listing). Restrict the key to the Places API.</p>';
			},
			'aw-sourcing-enrich',
			'aw_enrich',
			array( 'label_for' => 'aw-eg-key' )
		);
		add_settings_field(
			'aw_enrich_live_cap',
			'Live block: monthly cap (USD)',
			function () {
				echo '<input type="number" min="0" step="1" class="small-text" id="aw-eg-cap" name="aw_enrich_live_cap" value="' . esc_attr( (string) get_option( 'aw_enrich_live_cap', '20' ) ) . '"> <span class="description">Spent this month: $' . esc_html( number_format( aw_enrich_spent( 'live' ), 2 ) ) . ' live, $' . esc_html( number_format( aw_enrich_spent( 'worker' ), 2 ) ) . ' by the worker (its cap is the GitHub variable GOOGLE_PLACES_MONTHLY_BUDGET_USD).</span>';
			},
			'aw-sourcing-enrich',
			'aw_enrich',
			array( 'label_for' => 'aw-eg-cap' )
		);
	}
);

function aw_enrich_settings_tab() {
	echo '<h2>Directory enrichment</h2>';
	aw_sourcing_settings_form( 'aw_sourcing_enrich', 'aw-sourcing-enrich' );
}

/** The sources' switches from the Directory enrichment tab (logged); any other save passes through. */
function aw_enrich_sources_sanitize( $value ) {
	if ( ! aw_sourcing_settings_tab( $value, 'enrich' ) ) {
		return $value;
	}
	$old = aw_enrich_sources_on();
	$new = array();
	foreach ( array_keys( aw_enrich_sources() ) as $k ) {
		$new[ $k ] = ! empty( $value[ $k ] );
	}
	$changed = array();
	foreach ( $new as $k => $v ) {
		if ( $v !== $old[ $k ] ) {
			$changed[] = aw_enrich_sources()[ $k ][0] . ( $v ? ' on' : ' off' );
		}
	}
	$key = trim( sanitize_text_field( wp_unslash( $_POST['aw_enrich_google_key'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification -- options.php checked the group's nonce
	kits_sourcing_log( 'enrich', $changed ? implode( ', ', $changed ) : 'Directory enrichment saved' . ( '' !== $key ? ' (new Google key)' : '' ) );
	aw_sourcing_settings_saved();
	return $changed ? $new : get_option( 'aw_enrich_sources' ); // no change: stored as it is
}

/** An empty key field keeps the saved key (the field never shows it). */
function aw_enrich_google_key_sanitize( $value ) {
	$value = trim( sanitize_text_field( (string) $value ) );
	return '' === $value ? get_option( 'aw_enrich_google_key' ) : $value; // as stored (false when there is none: nothing is written)
}
