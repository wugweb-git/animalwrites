<?php
/**
 * Links tab (owner, 31 Oct 2026): who links to us. One row per linking page (or domain), linked page, anchor and
 * source, in wp_aw_si_links: first seen, last seen, how many links, and a type the owner sets (press, organisation,
 * social, directory, copy, spam). Spam is hidden, never deleted; it shows only in the Spam view.
 *
 * Rows come from the one-time seed (links-seed.php), the monthly Bing Webmaster run (bing.php) and
 * `wp aw-si links add` (the monthly Search Console Links read in a Claude Code session).
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Link types and their labels; '' is unsorted. */
function aw_si_link_types(): array {
	return array(
		'press'        => __( 'Press', 'aw-seo-extras' ),
		'organisation' => __( 'Organisation', 'aw-seo-extras' ),
		'social'       => __( 'Social', 'aw-seo-extras' ),
		'directory'    => __( 'Directory', 'aw-seo-extras' ),
		'copy'         => __( 'Copy', 'aw-seo-extras' ),
		'spam'         => __( 'Spam', 'aw-seo-extras' ),
	);
}

/**
 * Type for a new row from an automatic source: spam when the anchor sells links or gambling, else unsorted.
 *
 * @param string $anchor Anchor text.
 */
function aw_si_link_auto_type( string $anchor ): string {
	return preg_match( '/buy backlinks|seo services|casino|poker|betting/i', $anchor ) ? 'spam' : '';
}

/**
 * Domain of a URL without www, or the text itself when it is a bare domain.
 *
 * @param string $url URL or domain.
 */
function aw_si_link_domain( string $url ): string {
	$host = wp_parse_url( false === strpos( $url, '//' ) ? 'https://' . $url : $url, PHP_URL_HOST );
	return strtolower( preg_replace( '/^www\./i', '', (string) $host ) );
}

/**
 * Add a link, or move its dates when it is there already (first_seen only earlier, last_seen only later). The type and
 * hidden flag of a row already there are the owner's and never change here.
 *
 * @param array $l domain or from_url (one is needed), linked_url, anchor, links, first_seen, last_seen (Y-m-d; default
 *                 today), source, type, note.
 * @return int 1 added, 2 updated, 0 unchanged, -1 failed or not valid.
 */
function aw_si_link_put( array $l ): int {
	global $wpdb;
	$from   = esc_url_raw( trim( (string) ( $l['from_url'] ?? '' ) ) );
	$domain = aw_si_link_domain( '' !== trim( (string) ( $l['domain'] ?? '' ) ) ? (string) $l['domain'] : $from );
	$to     = esc_url_raw( trim( (string) ( $l['linked_url'] ?? '' ) ) );
	if ( '' === $domain || '' === $to ) {
		return -1;
	}
	$today  = wp_date( 'Y-m-d' );
	$first  = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $l['first_seen'] ?? '' ) ) ? $l['first_seen'] : $today;
	$last   = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $l['last_seen'] ?? '' ) ) ? $l['last_seen'] : $today;
	$anchor = mb_substr( trim( wp_strip_all_tags( (string) ( $l['anchor'] ?? '' ) ) ), 0, 255 );
	$source = mb_substr( trim( (string) ( $l['source'] ?? '' ) ), 0, 100 );
	$type   = (string) ( $l['type'] ?? '' );
	$type   = isset( aw_si_link_types()[ $type ] ) ? $type : '';
	$key    = md5( strtolower( '' !== $from ? $from : $domain ) . '|' . $to . '|' . $anchor . '|' . $source );
	$t      = aw_si_table( 'links' );
	$n      = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"INSERT INTO {$t} (link_key, domain, from_url, linked_url, anchor, links, first_seen, last_seen, source, type, hidden, note) VALUES (%s, %s, %s, %s, %s, %d, %s, %s, %s, %s, %d, %s) ON DUPLICATE KEY UPDATE first_seen = LEAST(COALESCE(first_seen, VALUES(first_seen)), VALUES(first_seen)), last_seen = GREATEST(COALESCE(last_seen, VALUES(last_seen)), VALUES(last_seen)), links = GREATEST(links, VALUES(links))", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
			$key,
			mb_substr( $domain, 0, 191 ),
			mb_substr( $from, 0, 500 ),
			mb_substr( $to, 0, 255 ),
			$anchor,
			max( 1, (int) ( $l['links'] ?? 1 ) ),
			$first,
			$last,
			$source,
			$type,
			'spam' === $type ? 1 : 0,
			mb_substr( (string) ( $l['note'] ?? '' ), 0, 255 )
		)
	);
	return false === $n ? -1 : (int) $n;
}

/*
 * The Links tab.
 */

add_filter( 'aw_si_tabs', fn( $tabs ) => (array) $tabs + array( 'links' => __( 'Links', 'aw-seo-extras' ) ) );

add_filter( 'removable_query_args', fn( $args ) => array_merge( (array) $args, array( 'linked' ) ) );

add_filter(
	'set_screen_option_aw_si_links_per_page',
	function ( $keep, $option, $value ) {
		return max( 10, min( 500, (int) $value ) );
	},
	10,
	3
);

/** The bulk and row actions: hide, unhide, and set a type. */
function aw_si_link_actions(): array {
	$out = array(
		'link_hide'   => __( 'Hide', 'aw-seo-extras' ),
		'link_unhide' => __( 'Unhide', 'aw-seo-extras' ),
	);
	foreach ( aw_si_link_types() as $key => $label ) {
		/* translators: %s: link type. */
		$out[ 'link_type_' . $key ] = sprintf( __( 'Set type: %s', 'aw-seo-extras' ), $label );
	}
	$out['link_type_unsorted'] = __( 'Set type: Unsorted', 'aw-seo-extras' );
	return $out;
}

/** Before output: screen options, and Hide / Unhide / Set type (then redirect). */
function aw_si_links_load(): void {
	if ( aw_si_ready() && function_exists( 'aw_si_links_seed' ) ) {
		aw_si_links_seed(); // One option read once seeded.
	}
	add_screen_option(
		'per_page',
		array(
			'label'   => __( 'Links per page', 'aw-seo-extras' ),
			'default' => 50,
			'option'  => 'aw_si_links_per_page',
		)
	);
	$action = '';
	foreach ( array( 'aw_si_link', 'action', 'action2' ) as $field ) {
		$v = isset( $_REQUEST[ $field ] ) ? sanitize_key( wp_unslash( $_REQUEST[ $field ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- checked below before any change.
		if ( isset( aw_si_link_actions()[ $v ] ) ) {
			$action = $v;
			break;
		}
	}
	if ( ! $action ) {
		return;
	}
	check_admin_referer( 'bulk-links' );
	if ( ! current_user_can( aw_si_cap() ) ) {
		wp_die( esc_html__( 'Not allowed.', 'aw-seo-extras' ), 403 );
	}
	$ids = array_filter( array_map( 'intval', (array) wp_unslash( $_REQUEST['link_id'] ?? array() ) ) );
	if ( $ids ) {
		global $wpdb;
		$t  = aw_si_table( 'links' );
		$in = implode( ',', $ids );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name and integers.
		if ( 'link_hide' === $action || 'link_unhide' === $action ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET hidden = %d WHERE id IN ({$in})", 'link_hide' === $action ? 1 : 0 ) );
		} else {
			$type = substr( $action, strlen( 'link_type_' ) );
			$type = 'unsorted' === $type ? '' : $type;
			// Spam is hidden; a row moved out of spam shows again.
			$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET hidden = IF(%s = 'spam', 1, IF(type = 'spam', 0, hidden)), type = %s WHERE id IN ({$in})", $type, $type ) );
		}
		// phpcs:enable
	}
	$back = remove_query_arg( array( 'aw_si_link', 'link_id', '_wpnonce', '_wp_http_referer', 'action', 'action2', 'linked' ), wp_get_referer() ? wp_get_referer() : aw_si_url( 'links' ) );
	wp_safe_redirect( add_query_arg( 'linked', $ids ? count( $ids ) : 0, $back ) );
	exit;
}
add_action( 'aw_si_load_tab_links', 'aw_si_links_load' );

/** The Links tab: the Bing key notice, what it shows, then the list. */
function aw_si_links_tab(): void {
	require_once __DIR__ . '/class-aw-si-links-table.php';
	if ( ! function_exists( 'aw_si_bing_on' ) || ! aw_si_bing_on() ) {
		echo '<div class="notice notice-info inline"><p>' . esc_html__( 'No Bing Webmaster API key yet: add it under Animal Writes → Keys.', 'aw-seo-extras' ) . '</p></div>';
	}
	if ( isset( $_GET['linked'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- message only.
		$n = absint( wp_unslash( $_GET['linked'] ) ); // phpcs:ignore WordPress.Security.NonceVerification -- message only.
		echo '<div class="notice ' . ( $n ? 'notice-success' : 'notice-warning' ) . ' inline"><p>' . esc_html( $n ? __( 'Saved.', 'aw-seo-extras' ) : __( 'No link was selected, so nothing changed.', 'aw-seo-extras' ) ) . '</p></div>';
	}
	echo '<p>' . esc_html__( 'Sites that link to us: from Bing Webmaster (monthly, on the 2nd), the monthly Search Console Links read, and the 25–26 Oct lists. Spam is hidden and shows only in the Spam view; nothing here is deleted. Set a type with the bulk actions.', 'aw-seo-extras' ) . '</p>';
	$table = new AW_SI_Links_Table();
	$table->prepare_items();
	$table->views();
	echo '<form method="get"><input type="hidden" name="page" value="' . esc_attr( AW_SI_SLUG ) . '"><input type="hidden" name="tab" value="links">';
	$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- navigation only.
	if ( $view ) {
		echo '<input type="hidden" name="view" value="' . esc_attr( $view ) . '">';
	}
	$table->search_box( __( 'Search links', 'aw-seo-extras' ), 'aw-si-links' );
	$table->display();
	echo '</form>';
}
add_action( 'aw_si_tab_links', 'aw_si_links_tab' );
