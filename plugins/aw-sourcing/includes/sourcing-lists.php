<?php
/**
 * Sourcing, the one place for what the workers fetch (S3, 8 Oct 2026). Kits Core gives the screen with
 * Sources (publishers and press sites), Keywords (the one word list) and the Change log; this adds Feeds (one list of the
 * News feeds: RSS and Google News queries, Library sources: AWBI, PIB, Gazette, courts, and Opportunity sources),
 * Press-discovery queries (Settings API), and registers the Categories and Sections tabs, whose core list-table screens
 * live in sourcing-terms-admin.php.
 *
 * The lists live in the option aw_sources, in the same shape as the workers' sources.yaml (defaults, keywords,
 * sources, categories, press), seeded once from it (data/sources-seed.json). The workers read them at the start of every
 * run from the private, authenticated policy endpoint (aw/v1/sourcing-policy) once "Workers use these lists" is on;
 * until then, and whenever the site cannot be reached, they use sources.yaml. API keys stay in GitHub secrets.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Seed (once): blocked publishers from the owner's list (S3.5). */
add_action(
	'init',
	function () {
		if ( '1' === get_option( 'aw_sourcing_seed_s35' ) || ! function_exists( 'aw_publishers' ) ) {
			return;
		}
		$pubs = aw_publishers();
		foreach ( array( 'bollyspice.com', 'storyboard18.com', 'in.eventfaqs.com' ) as $h ) {
			$pubs[ $h ] = array_merge( array( 'name' => '', 'count' => 0, 'first' => current_time( 'Y-m-d' ), 'last' => '' ), $pubs[ $h ] ?? array(), array( 'status' => 'block' ) );
		}
		update_option( AW_PUB_OPTION, $pubs, false );
		aw_sources_config();
		update_option( 'aw_sourcing_seed_s35', '1', false );
	}
);

/**
 * Seed (once): the High Court judgments source (owner, 25 Oct 2026). The seed file is read only when the lists do not
 * exist yet, so the live lists get it here: added after the Supreme Court source, off, for the owner to turn on in
 * Feeds after checking the workers' dry run. A list that already has it is left as it is.
 */
add_action(
	'init',
	function () {
		if ( '1' === get_option( 'aw_sourcing_seed_hc' ) || ! function_exists( 'aw_sources_config' ) ) {
			return;
		}
		$c       = aw_sources_config();
		$sources = array_values( (array) ( $c['sources'] ?? array() ) );
		$ids     = array_column( $sources, 'id' );
		if ( ! in_array( 'judgments-high-courts', $ids, true ) ) {
			$hc  = array(
				'id'            => 'judgments-high-courts',
				'name'          => 'High Court judgments (open dataset, CC BY 4.0)',
				'kind'          => 'judgments-hc',
				'type'          => 'legal',
				'url'           => 'https://indian-high-court-judgments.s3.ap-south-1.amazonaws.com/',
				'lookback_days' => 60,
				'on'            => false,
				'filter'        => false,
			);
			$at  = array_search( 'judgments-supreme-court', $ids, true );
			$pos = false === $at ? count( $sources ) : $at + 1;
			array_splice( $sources, $pos, 0, array( $hc ) );
			$c['sources'] = $sources;
			update_option( 'aw_sources', $c, false );
		}
		update_option( 'aw_sourcing_seed_hc', '1', false );
	}
);

/** Google News RSS URL for a search query (India, English). */
function aw_gnews_url( $query ) {
	return 'https://news.google.com/rss/search?q=' . rawurlencode( trim( $query ) ) . '&hl=en-IN&gl=IN&ceid=IN:en';
}

/** The query inside a Google News RSS URL, or ''. */
function aw_gnews_query( $url ) {
	if ( false === strpos( (string) $url, 'news.google.com/rss/search' ) ) {
		return '';
	}
	parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $q );
	return (string) ( $q['q'] ?? '' );
}

/**
 * Source types on the Feeds tab: type => [label, the tab their changes are logged under]. The log keeps the tab names
 * of before 24 Oct 2026 (feeds, library, opportunities), when each type had its own tab.
 */
const AW_SOURCE_TYPES = array(
	'news'        => array( 'News', 'feeds' ),
	'legal'       => array( 'Library', 'library' ),
	'opportunity' => array( 'Opportunity', 'opportunities' ),
);

add_filter(
	'kits_sourcing_tabs',
	function ( $tabs ) {
		// Feeds moved into Sources (25 Oct 2026): Sources → Feeds and each source's edit screen; the old address redirects.
		$tabs['press']      = array( 'Press queries', 'aw_sources_press_tab', true ); // Settings API forms (options.php)
		$tabs['categories'] = array( 'Categories', 'aw_sources_categories_tab', true );
		$tabs['sections']   = array( 'Sections', 'aw_sources_sections_tab', true );
		return $tabs;
	}
);
add_filter(
	'kits_sourcing_title_action',
	function ( $add ) {
		if ( function_exists( 'aw_sourcing_sources_screen' ) && 'feeds' === aw_sourcing_sources_screen() ) { // Sources → Feeds
			$add['sources'] = array( 'Add New Feed', admin_url( 'admin.php?page=aw-sourcing&tab=sources&feed=new' ) );
		}
		return $add;
	}
);

/** "Workers use these lists" switch, on Sourcing → Settings (admin-menu.php; it was on top of every tab until 25 Oct 2026). */
function aw_sources_live_switch() {
	$c = aw_sources_config();
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'aw_sourcing' );
	echo '<input type="hidden" name="action" value="aw_sourcing"><input type="hidden" name="tab" value="settings">'
		. '<p><strong>Workers use these lists:</strong> ' . ( empty( $c['live'] ) ? 'off (they use sources.yaml in the workers repository)' : 'on (read at the start of every run)' ) . ' '
		. '<button class="button" name="live" value="' . ( empty( $c['live'] ) ? '1' : '0' ) . '">' . ( empty( $c['live'] ) ? 'Turn on' : 'Turn off' ) . '</button></p></form>';
}
add_action(
	'kits_sourcing_save_settings',
	function () {
		$c         = aw_sources_config();
		$c['live'] = ! empty( $_POST['live'] ); // phpcs:ignore WordPress.Security.NonceVerification -- checked by the Kits save handler
		update_option( 'aw_sources', $c, false );
		kits_sourcing_log( 'log', 'workers use these lists: ' . ( $c['live'] ? 'on' : 'off' ) );
	}
);

/*
 * Sources sync when the lists are saved (owner, 25 Oct 2026), instead of every hour: a save of the source lists or the
 * keywords starts sources-sync.yml once, 2 minutes later, so a run of edits is one copy. Its daily run stays as the
 * fallback. Only while "Workers use these lists" is on, and only with AW_GITHUB_DISPATCH_TOKEN (aw_dispatch_workflow
 * logs the missing token); without it the daily run copies the lists.
 */
foreach ( array( 'aw_sources', 'aw_relevance' ) as $aw_sync_option ) {
	add_action( 'add_option_' . $aw_sync_option, 'aw_sources_sync_soon' );
	add_action( 'update_option_' . $aw_sync_option, 'aw_sources_sync_soon' );
}
unset( $aw_sync_option );

/** Queue one Sources sync start, 2 minutes from now (a no-op if one is already queued). */
function aw_sources_sync_soon() {
	if ( ! wp_next_scheduled( 'aw_sources_sync_dispatch' ) ) {
		wp_schedule_single_event( time() + 2 * MINUTE_IN_SECONDS, 'aw_sources_sync_dispatch' );
	}
}

add_action(
	'aw_sources_sync_dispatch',
	function () {
		if ( ! empty( aw_sources_config()['live'] ) && function_exists( 'aw_dispatch_workflow' ) ) {
			aw_dispatch_workflow( 'sources-sync.yml' );
		}
	}
);

/**
 * Sources → Feeds: every source (News, Library, Opportunity) in one list with the publisher each belongs to; on a
 * source's edit screen, only that site's feeds. The list posts as the old Feeds tab did (tab feeds).
 *
 * @param string $host Only this site's feeds ('' for all).
 */
function aw_sources_tab( $host = '' ) {
	if ( ! class_exists( 'WP_List_Table' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	}
	require_once __DIR__ . '/class-aw-sourcing-sources-table.php';
	$table = new AW_Sourcing_Sources_Table( $host );
	$table->prepare_items();
	if ( '' === $host ) {
		$table->views();
		echo '<form method="get"><input type="hidden" name="page" value="aw-sourcing"><input type="hidden" name="tab" value="sources"><input type="hidden" name="view" value="feeds">';
		foreach ( array( 'type' => array_keys( AW_SOURCE_TYPES ), 'state' => array( 'on', 'off' ) ) as $k => $ok ) {
			$v = sanitize_key( $_GET[ $k ] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
			if ( in_array( $v, $ok, true ) ) {
				echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
			}
		}
		$table->search_box( 'Search feeds', 'aw-src' );
		echo '</form>';
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_sourcing"><input type="hidden" name="tab" value="feeds">';
	wp_nonce_field( 'aw_sourcing' );
	$table->display();
	echo '</form>';
}

/**
 * Edit one source, or add a feed (feed=new; the type is chosen on the form), in the core form-table layout. Saves
 * through the old Feeds tab's save hook (tab feeds), then goes back to the screen it was opened from.
 *
 * @param string $id   Source id, or 'new'.
 * @param string $type Source type from the address ('' for the first source with that id).
 * @param string $host The site a new feed is added to ('' to take it from the address).
 */
function aw_sources_edit_form( $id, $type, $host = '' ) {
	$src = null;
	foreach ( (array) aw_sources_config()['sources'] as $s ) {
		if ( ! $src && ( $s['id'] ?? '' ) === $id && ( '' === $type || ( $s['type'] ?? '' ) === $type ) ) {
			$src = $s;
		}
	}
	$new  = 'new' === $id;
	$home = $new ? $host : ( $src ? aw_src_source_host( $src ) : '' );
	$back = '' !== $home && ! empty( $_GET['host'] ) // phpcs:ignore WordPress.Security.NonceVerification -- opened from a source's screen
		? add_query_arg( array( 'page' => 'aw-sourcing', 'tab' => 'sources', 'edit' => rawurlencode( $home ) ), admin_url( 'admin.php' ) )
		: admin_url( 'admin.php?page=aw-sourcing&tab=sources&view=feeds' );
	if ( ! $new && ! $src ) {
		echo '<div class="notice notice-error inline"><p>No such feed.</p></div><p><a href="' . esc_url( $back ) . '">Back to Feeds</a></p>';
		return;
	}
	if ( $new && 'opportunity' === $type ) {
		echo '<div class="notice notice-warning inline"><p>New kinds of opportunity site need code.</p></div><p><a href="' . esc_url( $back ) . '">Back to Feeds</a></p>';
		return;
	}
	$src = $src ? $src : array( 'id' => '', 'name' => '', 'kind' => 'rss', 'type' => in_array( $type, array( 'news', 'legal' ), true ) ? $type : 'news', 'url' => '', 'on' => true, 'filter' => true );
	$rss = 'rss' === $src['kind'];
	$q   = aw_gnews_query( $src['url'] );
	echo '<h2>' . esc_html( $new ? 'Add New Feed' : 'Edit Feed' ) . '</h2>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_sourcing"><input type="hidden" name="tab" value="feeds"><input type="hidden" name="src_back" value="' . esc_attr( $back ) . '">';
	wp_nonce_field( 'aw_sourcing' );
	echo '<input type="hidden" name="src[0][id]" value="' . esc_attr( $src['id'] ) . '"><table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row"><label for="aw-src-type">Type</label></th><td>';
	if ( $new ) {
		echo '<select id="aw-src-type" name="src[0][type]"><option value="news"' . selected( $src['type'], 'news', false ) . '>News</option><option value="legal"' . selected( $src['type'], 'legal', false ) . '>Library</option></select><p class="description">News: news items. Library: Library documents and legal news. New kinds of opportunity site need code.</p>';
	} else {
		echo '<input type="hidden" name="src[0][type]" value="' . esc_attr( (string) $src['type'] ) . '">' . esc_html( AW_SOURCE_TYPES[ $src['type'] ][0] ?? (string) $src['type'] ) . ( $rss ? '' : ' <span class="description">(read by code)</span>' );
	}
	echo '</td></tr>';
	echo '<tr><th scope="row"><label for="aw-src-name">Name</label></th><td><input type="text" id="aw-src-name" class="regular-text" name="src[0][name]" value="' . esc_attr( $src['name'] ) . '" required></td></tr>';
	echo '<tr><th scope="row"><label for="aw-src-url">Feed URL or Google News search</label></th><td>'
		. ( $rss ? '<input type="text" id="aw-src-url" class="large-text" name="src[0][url]" value="' . esc_attr( $q ? $q : $src['url'] ) . '" placeholder="https://… feed, or Google News search words"' . ( $new ? ' required' : '' ) . '><p class="description">For a Google News search, write only the search words.</p>' : '<code>' . esc_html( $src['url'] ) . '</code><p class="description">' . esc_html( $src['kind'] ) . ': needs code to change.</p>' )
		. '</td></tr>';
	echo '<tr><th scope="row"><label for="aw-src-pub">Publisher</label></th><td>';
	if ( $rss ) {
		echo '<input type="text" id="aw-src-pub" class="regular-text" name="src[0][host]" value="' . esc_attr( (string) ( $src['host'] ?? $home ) ) . '" placeholder="example.com"><p class="description">The site this feed belongs to. Empty: the one site a Google News search is limited to (site:…), else the feed\'s own site.</p>';
	} else {
		echo '<code>' . esc_html( aw_src_source_host( $src ) ) . '</code>';
	}
	echo '</td></tr>';
	echo '<tr><th scope="row">Status</th><td><label><input type="checkbox" name="src[0][on]" value="1"' . checked( ! empty( $src['on'] ), true, false ) . '> On</label></td></tr>';
	if ( $rss ) {
		echo '<tr><th scope="row">Keyword check</th><td><label><input type="checkbox" name="src[0][filter]" value="1"' . checked( ! empty( $src['filter'] ), true, false ) . '> Keep only items with a word from the Keywords tab</label></td></tr>';
	}
	echo '</tbody></table>';
	submit_button( $new ? 'Add Feed' : 'Update', 'primary', 'submit', false );
	echo ' <a href="' . esc_url( $back ) . '" class="button">Cancel</a></form>';
}

// Screen options: sources per page (Sources → Feeds).
add_action(
	'load-' . AW_SRC_SCREEN,
	function () {
		if ( 'sources' === sanitize_key( $_GET['tab'] ?? '' ) && 'feeds' === sanitize_key( $_GET['view'] ?? '' ) && empty( $_GET['feed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			add_screen_option( 'per_page', array( 'label' => 'Sources per page', 'default' => 50, 'option' => 'aw_sources_per_page' ) );
		}
	}
);
add_filter( 'set_screen_option_aw_sources_per_page', fn( $keep, $option, $value ) => max( 1, min( 200, (int) $value ) ), 10, 3 );

/**
 * Row and bulk actions of the Feeds list: on / off, keyword check on / off, and delete (a feed; a source read by code
 * needs code, so it is only turned off). A row is "type|id" (an id alone, from a form of before the merge, matches any
 * type). Logged per type under the tab of before (feeds, library, opportunities), with old → new. Returns true when it
 * handled the request (the edit form's rows are then not read).
 */
function aw_sources_list_action() {
	$row = sanitize_text_field( wp_unslash( $_POST['src_row'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- checked by the Kits save handler
	if ( false !== strpos( $row, ':' ) ) {
		list( $op, $one ) = explode( ':', $row, 2 );
		$ids              = array( $one );
	} else {
		$op  = sanitize_key( $_POST['src_bulk'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		$op  = '' !== $op ? $op : sanitize_key( $_POST['src_bulk2'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		$ids = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['ids'] ?? array() ) ); // phpcs:ignore WordPress.Security.NonceVerification
	}
	$op = sanitize_key( $op );
	if ( ! in_array( $op, array( 'on', 'off', 'filter_on', 'filter_off', 'delete' ), true ) ) {
		if ( isset( $_POST['src_bulk'] ) && ! isset( $_POST['src'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- the list without a chosen action (or the page box): nothing saved
			aw_sources_list_back( false );
		}
		return false;
	}
	$want = array();
	foreach ( $ids as $one ) {
		$parts  = explode( '|', (string) $one, 2 );
		$want[] = 2 === count( $parts ) ? array( sanitize_key( $parts[0] ), sanitize_key( $parts[1] ) ) : array( '', sanitize_key( $parts[0] ) );
	}
	$c    = aw_sources_config();
	$what = array();
	$keep = array();
	foreach ( $c['sources'] as $s ) {
		$t   = (string) ( $s['type'] ?? '' );
		$hit = false;
		foreach ( $want as $w ) {
			$hit = $hit || ( $w[1] === $s['id'] && ( '' === $w[0] || $w[0] === $t ) );
		}
		if ( $hit && 'delete' === $op && 'rss' === ( $s['kind'] ?? '' ) ) {
			$what[ $t ][] = array( 'deleted ' . $s['name'] . ' (' . $s['id'] . ')', $s, '' );
			continue; // a feed deleted (sources read by code are kept: they need code)
		}
		if ( $hit && ( 'on' === $op || 'off' === $op ) && ( 'on' === $op ) !== ! empty( $s['on'] ) ) {
			$s['on']      = 'on' === $op;
			$what[ $t ][] = array( $s['name'] . ' ' . $op, ! $s['on'], $s['on'] );
		} elseif ( $hit && in_array( $op, array( 'filter_on', 'filter_off' ), true ) && 'rss' === ( $s['kind'] ?? '' ) && ( 'filter_on' === $op ) !== ! empty( $s['filter'] ) ) {
			$s['filter']  = 'filter_on' === $op;
			$what[ $t ][] = array( $s['name'] . ' keyword check ' . ( $s['filter'] ? 'on' : 'off' ), ! $s['filter'], $s['filter'] );
		}
		$keep[] = $s;
	}
	$c['sources'] = $keep;
	update_option( 'aw_sources', $c, false );
	$log = array();
	foreach ( $what as $t => $w ) {
		foreach ( $w as $e ) {
			$log[] = array( AW_SOURCE_TYPES[ $t ][1] ?? 'feeds', $e[0], $e[1], $e[2] );
		}
	}
	kits_sourcing_log_many( $log );
	aw_sources_list_back( (bool) $what );
	return true;
}

/** After a list action: back to the same view, search and page; "Saved" only when something changed. */
function aw_sources_list_back( $changed ) {
	$back = remove_query_arg( array( 'updated', 'paged' ), wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=aw-sourcing' ) );
	$page = absint( wp_unslash( $_POST['paged'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification -- checked by the Kits save handler
	$back = $page > 1 ? add_query_arg( 'paged', $page, $back ) : $back;
	wp_safe_redirect( $changed ? add_query_arg( 'updated', 1, $back ) : $back );
	exit;
}

// Sources → Feeds (the old Feeds tab's saves): the list's row and bulk actions, or the Add New / Edit Feed form.
add_action(
	'kits_sourcing_save_feeds',
	function () {
		if ( aw_sources_list_action() ) {
			return; // a row or bulk action of the list
		}
		$c    = aw_sources_config();
		$rows = (array) wp_unslash( $_POST['src'] ?? array() ); // phpcs:ignore WordPress.Security.NonceVerification -- checked by the Kits save handler
		$what = array();
		foreach ( $rows as $r ) {
			$id   = sanitize_key( $r['id'] ?? '' );
			$type = sanitize_key( $r['type'] ?? '' );
			$name = sanitize_text_field( $r['name'] ?? '' );
			$url  = trim( sanitize_text_field( $r['url'] ?? '' ) );
			$on   = ! empty( $r['on'] );
			$flt  = ! empty( $r['filter'] );
			$raw  = array_key_exists( 'host', (array) $r ) ? trim( sanitize_text_field( (string) $r['host'] ) ) : null; // null: the form had no Publisher field
			$host = null === $raw ? null : aw_sourcing_norm_host( $raw );
			if ( null !== $raw && '' !== $raw && '' === $host ) {
				$host = null; // not a host (a typo): the publisher stays as it is
			}
			if ( '' === $id ) { // a new feed: News or Library (new kinds of opportunity site need code)
				$type = in_array( $type, array( 'news', 'legal' ), true ) ? $type : 'news';
				if ( '' === $name || '' === $url ) {
					continue;
				}
				$url = preg_match( '#^https?://#', $url ) ? esc_url_raw( $url ) : aw_gnews_url( $url );
				$new = array( 'id' => sanitize_title( ( false !== strpos( $url, 'news.google.com' ) ? 'gnews-' : '' ) . $name ), 'name' => $name, 'kind' => 'rss', 'type' => $type, 'url' => $url, 'on' => $on, 'filter' => $flt );
				if ( 'legal' === $type ) {
					$new['source_type'] = 'auto-court';
				}
				$derived = aw_src_source_host( $new );
				if ( ( null !== $host && '' !== $host && $host !== $derived ) || aw_sourcing_phase() >= 1 ) { // every source has its host from phase 1
					$new['host'] = null !== $host && '' !== $host ? $host : $derived;
				}
				$c['sources'][]  = $new;
				$what[ $type ][] = array( 'added ' . $name . ' (' . $new['id'] . ')', '', $new );
				continue;
			}
			$at = null;
			foreach ( $c['sources'] as $n => $s ) {
				if ( null === $at && $s['id'] === $id && ( '' === $type || ( $s['type'] ?? '' ) === $type ) ) {
					$at = $n;
				}
			}
			if ( null === $at ) {
				continue;
			}
			$s     = &$c['sources'][ $at ];
			$t     = (string) ( $s['type'] ?? '' );
			$shown = aw_src_source_host( $s ); // the Publisher the form showed (before the address below changes)
			if ( ! empty( $s['on'] ) !== $on ) {
				$what[ $t ][] = array( $s['name'] . ( $on ? ' on' : ' off' ), ! empty( $s['on'] ), $on );
			}
			$s['on'] = $on;
			if ( '' !== $name && $name !== $s['name'] ) {
				$what[ $t ][] = array( $s['name'] . ' renamed ' . $name, $s['name'], $name );
				$s['name']    = $name;
			}
			if ( 'rss' === $s['kind'] && '' !== $url ) {
				$url = preg_match( '#^https?://#', $url ) ? esc_url_raw( $url ) : aw_gnews_url( $url );
				if ( $url !== $s['url'] ) {
					$what[ $t ][] = array( $s['name'] . ' address changed', $s['url'], $url );
					$s['url']     = $url;
				}
				if ( ! empty( $s['filter'] ) !== $flt ) {
					$what[ $t ][] = array( $s['name'] . ' keyword check ' . ( $flt ? 'on' : 'off' ), ! empty( $s['filter'] ), $flt );
					$s['filter']  = $flt;
				}
			}
			if ( 'rss' === $s['kind'] && null !== $host && $host !== $shown ) { // the Publisher field, changed on the form
				$plain   = $s;
				unset( $plain['host'] );
				$derived = aw_src_source_host( $plain ); // from the (new) address
				if ( '' === $host ) {
					unset( $s['host'] );
					$what[ $t ][] = array( $s['name'] . ' publisher → ' . $derived . ' (from its address)', $shown, $derived );
				} else {
					$what[ $t ][] = array( $s['name'] . ' publisher → ' . $host, $shown, $host );
					$s['host']    = $host;
				}
			}
			unset( $s );
		}
		update_option( 'aw_sources', $c, false );
		$log = array();
		foreach ( $what as $t => $w ) {
			foreach ( $w as $e ) {
				$log[] = array( AW_SOURCE_TYPES[ $t ][1] ?? 'feeds', $e[0], $e[1], $e[2] );
			}
		}
		kits_sourcing_log_many( $log );
	}
);

/*
 * Press queries, Categories and Sections (and Relay, relay.php) save through the Settings API: one option, aw_sources,
 * in the group aw_sourcing_sources. Each form sends its tab in aw_sources[_aw_tab]; aw_sources_sanitize() hands the
 * posted part to that tab's filter (aw_sources_save_<tab>) and keeps the rest of the option as it is. The list tables'
 * own saves (sources on / off, edit, "Workers use these lists") pass through unchanged.
 */

add_action(
	'admin_init',
	function () {
		register_setting( 'aw_sourcing_sources', 'aw_sources', array( 'type' => 'array', 'sanitize_callback' => 'aw_sources_sanitize', 'show_in_rest' => false ) );

		// Press queries.
		add_settings_section(
			'aw_press',
			'',
			function () {
				aw_sourcing_settings_marker( 'aw_sources', 'press' );
				if ( function_exists( 'aw_sourcing_kw_on' ) && aw_sourcing_kw_on() ) { // phase 2: the queries are the Press keywords below
					echo '<p>Weekly searches for coverage of Animal Writes through Google News and Google Search: each keyword in this view (the Press role) is a query. Results wait in Sourcing → To review.</p>';
					return;
				}
				echo '<p>Searches for coverage of Animal Writes (Brave Search, weekly). Each query runs on each press site and on the open web. Results wait in Sourcing → To review. The press sites are chosen on the <a href="' . esc_url( admin_url( 'admin.php?page=aw-sourcing&tab=sources&status=press' ) ) . '">Sources</a> tab ("Search here for coverage").</p>';
			},
			'aw-sourcing-press'
		);
		$press = fn( $k ) => (array) ( aw_sources_config()['press'][ $k ] ?? array() );
		if ( ! function_exists( 'aw_sourcing_kw_on' ) || ! aw_sourcing_kw_on() ) { // phase 2: Press keywords
			add_settings_field( 'aw_press_queries', 'Queries', fn( $a ) => aw_sourcing_textarea_field( $a + array( 'value' => $press( 'queries' ) ) ), 'aw-sourcing-press', 'aw_press', array( 'label_for' => 'aw-pq', 'name' => 'aw_sources[queries]', 'rows' => 10, 'help' => 'One per line.' ) );
		}
		add_settings_field(
			'aw_press_floor',
			'Coverage from',
			function () {
				echo '<input type="date" id="aw-pf" name="aw_sources[date_floor]" value="' . esc_attr( (string) ( aw_sources_config()['press']['date_floor'] ?? '' ) ) . '">';
			},
			'aw-sourcing-press',
			'aw_press',
			array( 'label_for' => 'aw-pf' )
		);
	}
);

/**
 * Sanitize aw_sources: a Sourcing form's part through its tab's filter; any other save passes through.
 *
 * @param mixed $value Posted part (with _aw_tab) or the whole option.
 * @return mixed
 */
function aw_sources_sanitize( $value ) {
	$tab = aw_sourcing_settings_tab( $value );
	if ( '' === $tab ) {
		return $value;
	}
	if ( ! has_filter( 'aw_sources_save_' . $tab ) ) { // a form of a tab that no longer exists: never replace the lists with it
		return get_option( 'aw_sources' );
	}
	$was = aw_sources_config();
	$c   = apply_filters( 'aw_sources_save_' . $tab, $was, $value );
	aw_sourcing_settings_saved();
	return $c === $was ? get_option( 'aw_sources' ) : $c; // Save with no change writes nothing (same keys, values and types)
}

/** Press-discovery queries (Media & PR). */
function aw_sources_press_tab() {
	aw_sourcing_settings_form( 'aw_sourcing_sources', 'aw-sourcing-press' );
}
add_filter(
	'aw_sources_save_press',
	function ( $c, $in ) {
		if ( aw_sourcing_phase() >= 2 ) { // phase 2: the queries are Press keywords; this form keeps on/off, from and every n days
			return function_exists( 'aw_sourcing_kw_save_press' ) && aw_sourcing_kw_on() ? aw_sourcing_kw_save_press( $c, (array) $in ) : $c;
		}
		$old                   = $c['press'];
		$c['press']['queries'] = aw_sourcing_lines( $in['queries'] ?? '' );
		if ( array_key_exists( 'sites', (array) $in ) ) { // the Sites field moved to the Sources tab: kept as stored unless posted
			$c['press']['sites'] = aw_sourcing_lines( $in['sites'] );
		}
		$d                     = sanitize_text_field( (string) ( $in['date_floor'] ?? '' ) );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) {
			$c['press']['date_floor'] = $d;
		}
		kits_sourcing_log( 'press', implode( ' · ', array_filter( array( kits_sourcing_diff( 'Queries', $old['queries'], $c['press']['queries'] ), kits_sourcing_diff( 'Sites', $old['sites'] ?? array(), $c['press']['sites'] ?? array() ), $old['date_floor'] !== $c['press']['date_floor'] ? 'from ' . $c['press']['date_floor'] : '' ) ) ) );
		return $c;
	},
	10,
	2
);

/**
 * The workers' copy of the lists (same shape as sources.yaml), when "Workers use these lists" is on. Sources that are
 * off are left out; a source with "Keyword check" gets the feed keywords.
 *
 * Sourcing restructure (owner, 25 Oct 2026): new blocks go beside the unchanged v1 keys, inside config so Sources sync
 * copies them into sources.yaml: category_names (slug => name, the workers' fallback when the site cannot be reached)
 * and, once phase 1 is migrated, schema {sources: 2} and sources_v2 (each v1 source plus its publisher host, relay
 * switch, read_by and keyword check). Workers before W1 ignore them; W1 reads v2 and falls back to v1.
 */
add_filter(
	'kits_sourcing_policy',
	function ( $data ) {
		$c = aw_sources_config();
		if ( empty( $c['live'] ) ) {
			return $data;
		}
		$rel     = function_exists( 'aw_relevance' ) ? aw_relevance() : array();
		$words   = array_values( array_unique( array_merge( (array) ( $rel['strong'] ?? array() ), (array) ( $rel['animal'] ?? array() ) ) ) );
		$sources = array();
		foreach ( (array) $c['sources'] as $s ) {
			if ( empty( $s['on'] ) ) {
				continue;
			}
			if ( ! empty( $s['filter'] ) ) {
				$s['keywords'] = $words; // one keyword list: the Keywords tab's include and animal words
			}
			unset( $s['on'], $s['filter'], $s['check'], $s['keyword_id'] ); // phase 2 keys: sent in sources_v2 and keywords, never in v1
			$sources[] = $s;
		}
		// Search words: the Keywords tab's Include list. Each English one is a Google News search per run; the animal words
		// (dog, cat, pet ...) are for checking feed items, never searches (77 words would be 71 searches, 20 Oct 2026).
		$search = array_values( array_unique( (array) ( $rel['strong'] ?? array() ) ) );
		$data['config'] = array( 'defaults' => $c['defaults'], 'keywords' => $search, 'sources' => $sources, 'categories' => $c['categories'], 'sections' => $c['sections'], 'press' => $c['press'], 'topic_tags' => (array) ( $c['topic_tags'] ?? array() ) );
		if ( function_exists( 'aw_relevance_categories' ) ) {
			$data['config']['category_names'] = (object) aw_relevance_categories(); // an object in JSON even when empty
		}
		if ( function_exists( 'aw_sourcing_kw_on' ) && aw_sourcing_kw_on() ) {
			// Phase 2 (plan §6): sources_v2 with each source's keyword check and without the searches that are keywords
			// now; the keywords with a Sourcing or Press role (English only) at the top level (`keywords`, the plan's
			// name) and in config as keywords_v2 (config.keywords is the v1 word list, so Sources sync copies the block
			// into sources.yaml under that name); match "word" switches the workers to whole-word matching.
			$blocks                        = aw_kw_policy_blocks( (array) $c['sources'], aw_sourcing_kw_worker_rows(), function_exists( 'aw_relay_hosts' ) ? aw_relay_hosts() : array() );
			$data['keywords']              = $blocks['keywords_v2'];
			$data['config']['schema']      = $blocks['schema'];
			$data['config']['match']       = $blocks['match'];
			$data['config']['sources_v2']  = $blocks['sources_v2'];
			$data['config']['keywords_v2'] = $blocks['keywords_v2'];
		} elseif ( function_exists( 'aw_sourcing_phase' ) && aw_sourcing_phase() >= 1 ) {
			$data['config']['schema']     = array( 'sources' => 2 );
			$data['config']['sources_v2'] = aw_src_sources_v2( $sources, function_exists( 'aw_relay_hosts' ) ? aw_relay_hosts() : array() );
		}
		return $data;
	}
);
