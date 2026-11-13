<?php
/**
 * Directory enrichment in WP Admin:
 * - Sourcing → Enrichment review (Directory → Enrichment review until 25 Oct 2026): its menu item and the filter its
 *   lists use (aw_enrich_where()); the screen is the Kits shell's (sourceweb-ui.php, sourceweb/v1 routes).
 * - The Directory enrichment settings (each source on or off, the live Google block's key and monthly cap): their
 *   options and checks; they are set on SourceWeb → Settings.
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
		? '<img class="kit-src-icon aw-src-icon" src="' . esc_url( $url ) . '" alt="" width="16" height="16" loading="lazy" decoding="async">'
		: '<span class="kit-src-icon kit-src-icon--letter aw-src-icon aw-src-icon--letter" aria-hidden="true">' . esc_html( mb_strtoupper( mb_substr( $name, 0, 1 ) ) ) . '</span>';
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
		echo '<style>.aw-enrich-src{display:inline-flex;align-items:center;gap:4px;margin-right:8px;text-decoration:none;white-space:nowrap}.aw-enrich-src img,.aw-enrich-src .aw-src-icon--letter{width:16px;height:16px;border-radius:3px}.aw-src-icon--letter{display:inline-grid;place-items:center;background:#dcdcde;font-size:10px;font-weight:600}.aw-enrich-prov{margin:4px 0 0;font-size:12px;color:#50575e}</style>';
	}
);

/* ---------- Beside each field on the listing ---------- */

/** Meta key => enrichment field. */
function aw_enrich_meta_fields() {
	$out = array();
	foreach ( aw_enrich_fields_all() as $f => $def ) {
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

// SCF fields (the listing groups): the line under the field.
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

// Every field the worker set, with its source, in one place (also covers the short bio, logo, services): a message
// field at the end of SCF's "Claimed and sources" group.
add_action(
	'acf/render_field/key=field_aw_listing_msg_provenance',
	function () {
		$post = get_post();
		if ( $post && 'aw_listing' === $post->post_type ) {
			aw_enrich_prov_box( $post );
		}
	}
);

function aw_enrich_prov_box( $post ) {
	$rows = '';
	foreach ( aw_enrich_fields_all() as $f => $def ) {
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
	echo '<p class="description">' . ( $chk ? 'Last checked ' . esc_html( wp_date( 'j M Y', strtotime( $chk ) ) ) . '. ' : '' ) . '<a href="' . esc_url( admin_url( 'admin.php?page=aw-enrich-review&listing=' . $post->ID . '&status=all' ) ) . '">Changes and proposals</a></p>';
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

/* ---------- Sourcing → Enrichment review ---------- */

add_action(
	'admin_menu',
	function () {
		$n = 0;
		if ( get_option( 'aw_enrich_db' ) ) {
			global $wpdb;
			$n = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . aw_enrich_table() . " WHERE status = 'pending'" ); // phpcs:ignore WordPress.DB
		}
		add_submenu_page( AW_SRC_MENU, 'Enrichment review', 'Enrichment review' . ( $n && function_exists( 'aw_menu_count' ) ? aw_menu_count( $n ) : '' ), AW_ENRICH_CAP, 'aw-enrich-review', 'aw_enrich_review_page' );
	},
	11 // after To review (review-queue.php, 10)
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

/** Sourcing → Enrichment review: the shell screen (sourceweb-ui.php). */
function aw_enrich_review_page() {
	if ( ! current_user_can( AW_ENRICH_CAP ) ) {
		return;
	}
	sourceweb_ui_page( 'enrichment' );
}

/* ---------- Directory enrichment settings: their options and checks (set on SourceWeb → Settings) ---------- */

add_action(
	'admin_init',
	function () {
		register_setting( 'aw_sourcing_enrich', 'aw_enrich_sources', array( 'type' => 'array', 'sanitize_callback' => 'aw_enrich_sources_sanitize', 'show_in_rest' => false ) );
		register_setting( 'aw_sourcing_enrich', 'aw_enrich_google_key', array( 'type' => 'string', 'sanitize_callback' => 'aw_enrich_google_key_sanitize', 'show_in_rest' => false ) );
		register_setting( 'aw_sourcing_enrich', 'aw_enrich_live_cap', array( 'type' => 'string', 'sanitize_callback' => fn( $v ) => (string) max( 0, (int) $v ) === (string) get_option( 'aw_enrich_live_cap', '20' ) ? get_option( 'aw_enrich_live_cap', '20' ) : (string) max( 0, (int) $v ), 'show_in_rest' => false ) );
	}
);

/** The sources' switches (posted with _aw_tab enrich; logged); any other save passes through. */
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
	aw_src_log( 'enrich', $changed ? implode( ', ', $changed ) : 'Directory enrichment saved' . ( '' !== $key ? ' (new Google key)' : '' ) );
	aw_sourcing_settings_saved();
	return $changed ? $new : get_option( 'aw_enrich_sources' ); // no change: stored as it is
}

/** An empty key field keeps the saved key (the field never shows it). */
function aw_enrich_google_key_sanitize( $value ) {
	$value = trim( sanitize_text_field( (string) $value ) );
	return '' === $value ? get_option( 'aw_enrich_google_key' ) : $value; // as stored (false when there is none: nothing is written)
}
