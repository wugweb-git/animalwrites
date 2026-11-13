<?php
/**
 * Sourcing, the one place for what the workers fetch (S3, 8 Oct 2026): the feeds (News feeds: RSS and Google News
 * queries, Library sources: AWBI, PIB, Gazette, courts, and Opportunity sources), their form and saves, the Press
 * settings' save, "Workers use these lists" and the workers' copy of the lists. The screens are the Kits shell's
 * (sourceweb-ui.php).
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
 * Source types of the feeds: type => [label, the tab their changes are logged under]. The log keeps the tab names
 * of before 24 Oct 2026 (feeds, library, opportunities), when each type had its own tab.
 */
const AW_SOURCE_TYPES = array(
	'news'        => array( 'News', 'feeds' ),
	'legal'       => array( 'Library', 'library' ),
	'opportunity' => array( 'Opportunity', 'opportunities' ),
);

/**
 * Save "Workers use these lists" (SourceWeb → Settings) and log it.
 *
 * @param bool $on On or off.
 */
function aw_sources_live_save( $on ) {
	$c         = aw_sources_config();
	$c['live'] = (bool) $on;
	update_option( 'aw_sources', $c, false );
	kits_sourcing_log( 'log', 'workers use these lists: ' . ( $c['live'] ? 'on' : 'off' ) );
}

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
 * Edit one source, or add a feed (feed=new; the type is chosen on the form), in the core form-table layout, drawn on
 * the Sources screen (sourceweb_feed_parts()). Saves through the feeds save hook (tab feeds), then goes back to the
 * screen it was opened from.
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
		wp_admin_notice( 'No such feed.', array( 'type' => 'error', 'additional_classes' => array( 'inline' ) ) );
		echo '<p><a href="' . esc_url( $back ) . '">Back to Feeds</a></p>';
		return;
	}
	if ( $new && 'opportunity' === $type ) {
		wp_admin_notice( 'New kinds of opportunity site need code.', array( 'type' => 'warning', 'additional_classes' => array( 'inline' ) ) );
		echo '<p><a href="' . esc_url( $back ) . '">Back to Feeds</a></p>';
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

/**
 * A feeds list action of the Sources screen (sourceweb-rest-pages.php): on, off, filter_on, filter_off (the keyword check)
 * or delete (feeds only; sources read by code are kept). Logged per feed.
 *
 * @param string $op  The action.
 * @param array  $ids "type|id" (or "id") keys.
 * @return bool Something changed.
 */
function aw_sources_feed_op( $op, array $ids ) {
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
	return (bool) $what;
}

// The Add New / Edit Feed form's save (admin_post_aw_sourcing, tab feeds).
add_action(
	'kits_sourcing_save_feeds',
	function () {
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
 * The Press settings save through aw_sources_sanitize() (the Keywords screen's Press view, sourceweb-rest-pages.php):
 * the posted part carries its tab in _aw_tab, and the tab's filter (aw_sources_save_<tab>) merges it into the option,
 * keeping the rest as it is. Every other save of aw_sources (feeds, sources on / off, "Workers use these lists") passes
 * through unchanged. Forms of tabs that no longer exist (Categories, Sections, Relay) change nothing.
 */

add_action(
	'admin_init',
	function () {
		register_setting( 'aw_sourcing_sources', 'aw_sources', array( 'type' => 'array', 'sanitize_callback' => 'aw_sources_sanitize', 'show_in_rest' => false ) );
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

// Press-discovery settings (Media & PR).
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
 *
 * Phase 4 (owner, 25 Oct 2026; once migrated, aw_sourcing_phase() >= 4): the v1 keys are not built or sent. config
 * holds defaults, press (its switches), topic_tags (the tag rules), category_names and the blocks (schema, match,
 * sources_v2, keywords_v2, and sections_v2 from rules.php). The v1 code stays for a rollback.
 */
add_filter(
	'kits_sourcing_policy',
	function ( $data ) {
		$c = aw_sources_config();
		if ( empty( $c['live'] ) ) {
			return $data;
		}
		if ( aw_sourcing_phase() >= 4 && function_exists( 'aw_sourcing_kw_on' ) && aw_sourcing_kw_on() ) { // phase 4: no v1 keys (without the keyword table the v1 lists still go)
			$press = (array) ( $c['press'] ?? array() );
			unset( $press['queries'] ); // the Press keywords carry them
			$data['config'] = array( 'defaults' => $c['defaults'], 'press' => $press, 'topic_tags' => (array) ( $c['topic_tags'] ?? array() ) );
			if ( function_exists( 'aw_relevance_categories' ) ) {
				$data['config']['category_names'] = (object) aw_relevance_categories();
			}
			$blocks                        = aw_kw_policy_blocks( (array) $c['sources'], aw_sourcing_kw_worker_rows(), function_exists( 'aw_relay_hosts' ) ? aw_relay_hosts() : array() );
			$data['keywords']              = $blocks['keywords_v2'];
			$data['config']['schema']      = $blocks['schema'];
			$data['config']['match']       = $blocks['match'];
			$data['config']['sources_v2']  = $blocks['sources_v2'];
			$data['config']['keywords_v2'] = $blocks['keywords_v2'];
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
