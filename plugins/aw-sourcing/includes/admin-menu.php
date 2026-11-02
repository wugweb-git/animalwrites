<?php
/**
 * The Sourcing menu and its Overview (owner, 25 Oct 2026: AW Sourcing meets the admin standard of the other plugins:
 * one menu, an Overview of every feature and where it lives, links from the Plugins screen; core WordPress only).
 *
 * Sourcing → Overview (this file), To review (review-queue.php), Enrichment review (enrichment-admin.php), then one
 * item per screen of the Sourcing page (admin.php?page=aw-sourcing&tab=…, sourcing-policy.php aw_sourcing_page): the
 * settings screens (Sources, Keywords, Rules or their parts, Press queries, Categories, AI models), the reports and
 * jobs (Workers, Rejected, Relay log, Change log), and Settings (the plugin's own switch). The order and the addresses
 * are in menu-model.php. Until 25 Oct 2026 the screens were Settings → Sourcing (tabs), Inbox → To review and
 * Directory → Enrichment review.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Sourcing page's tabs in menu order: slug => [label, render callback, own form?]. Sources, Keywords and the Change
 * log are drawn by aw_sourcing_page() itself; every other tab comes from the kits_sourcing_tabs filter.
 *
 * @return array
 */
function aw_sourcing_tabs() {
	static $memo = null; // the same answer all request: the menu, the current item, the title and the page ask for it
	if ( null !== $memo ) {
		return $memo;
	}
	$all = array(
		'sources'  => array( 'Sources', '', true ),
		'keywords' => array( 'Keywords', '', true ),
	) + (array) apply_filters( 'kits_sourcing_tabs', array() ) + array( 'log' => array( 'Change log', '', true ) );
	// From phase 2 the Press queries and Categories tabs only send you on (keywords-admin.php: Keywords → Press, Posts →
	// Categories), so they get no menu item of their own; their addresses still go there.
	if ( function_exists( 'aw_sourcing_kw_on' ) && aw_sourcing_kw_on() ) {
		unset( $all['press'], $all['categories'] );
	}
	$out = array();
	foreach ( array_keys( aw_src_menu_order( array_map( fn( $t ) => (string) $t[0], $all ) ) ) as $k ) {
		$out[ $k ] = $all[ $k ];
	}
	$memo = $out;
	return $out;
}

/** The tab shown on the Sourcing page (Sources when none or an unknown one is asked for). */
function aw_sourcing_current_tab() {
	$tab = sanitize_key( $_GET['tab'] ?? 'sources' ); // phpcs:ignore WordPress.Security.NonceVerification -- which screen to show
	return isset( aw_sourcing_tabs()[ $tab ] ) ? $tab : 'sources';
}

/** A Sourcing screen's address (tab '' or sources: the Sources screen). */
function aw_sourcing_url( $tab = '', array $args = array() ) {
	return admin_url( aw_src_tab_path( (string) $tab, $args ) );
}

add_action(
	'admin_menu',
	function () {
		// The title stays plain "Sourcing" here: WordPress names the page's screen after it (AW_SRC_SCREEN). The count goes on below.
		add_menu_page( 'Sourcing', 'Sourcing', AW_REVIEW_CAP, AW_SRC_MENU, 'aw_sourcing_overview_page', 'dashicons-rss', 26 );
		add_submenu_page( AW_SRC_MENU, 'Sourcing', 'Overview', AW_REVIEW_CAP, AW_SRC_MENU, 'aw_sourcing_overview_page' );
		if ( current_user_can( AW_REVIEW_CAP ) && function_exists( 'aw_review_count' ) && function_exists( 'aw_menu_count' ) ) {
			global $menu;
			foreach ( (array) $menu as $i => $item ) {
				if ( AW_SRC_MENU === ( $item[2] ?? '' ) ) {
					$menu[ $i ][0] .= aw_menu_count( aw_review_count() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride -- the count bubble, as WordPress adds its own
				}
			}
		}
	},
	9 // before To review (10) and Enrichment review (11)
);

// One item per screen of the Sourcing page; the first (Sources) is the page itself, the others open it on their tab.
add_action(
	'admin_menu',
	function () {
		$first = true;
		foreach ( aw_sourcing_tabs() as $tab => $t ) {
			if ( $first ) {
				$hook = add_submenu_page( AW_SRC_MENU, (string) $t[0], (string) $t[0], AW_SRC_CAP, AW_SRC_PAGE, 'aw_sourcing_page' );
				if ( $hook && AW_SRC_SCREEN !== $hook ) { // the menu title changed: the screens' hooks would not fire, so pass them on and say so
					add_action( 'load-' . $hook, fn() => do_action( 'load-' . AW_SRC_SCREEN ) );
					error_log( 'AW Sourcing: the Sourcing page is ' . $hook . ', not ' . AW_SRC_SCREEN . ' (menu-model.php); list-table settings and columns will not apply.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
				}
				$first = false;
				continue;
			}
			add_submenu_page( AW_SRC_MENU, (string) $t[0], (string) $t[0], AW_SRC_CAP, aw_src_tab_menu_slug( $tab ) );
		}
	},
	12
);

// The menu item of the tab shown is the current one, and the window title names the tab.
add_filter(
	'submenu_file',
	function ( $file, $parent ) {
		return AW_SRC_MENU === $parent && AW_SRC_PAGE === sanitize_key( $_GET['page'] ?? '' ) ? aw_src_tab_menu_slug( aw_sourcing_current_tab() ) : $file; // phpcs:ignore WordPress.Security.NonceVerification -- which item to mark
	},
	10,
	2
);
add_filter(
	'admin_title',
	function ( $admin_title, $title ) {
		if ( AW_SRC_PAGE !== sanitize_key( $_GET['page'] ?? '' ) || '' === (string) $title || 0 !== strpos( (string) $admin_title, (string) $title ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- a title only
			return $admin_title;
		}
		return aw_sourcing_tabs()[ aw_sourcing_current_tab() ][0] . substr( (string) $admin_title, strlen( (string) $title ) );
	},
	10,
	2
);

// Where Inbox was: after Classification, before Mail (Core's menu order lists the site's menus; this one joins it).
add_filter(
	'menu_order',
	function ( $order ) {
		$order = array_values( array_diff( (array) $order, array( AW_SRC_MENU ) ) );
		foreach ( array( 'aw-inbox' => 1, 'mailweb' => 0, 'aw-classification' => 1 ) as $next_to => $after ) {
			$at = array_search( $next_to, $order, true );
			if ( false !== $at ) {
				array_splice( $order, $at + $after, 0, array( AW_SRC_MENU ) );
				return $order;
			}
		}
		$order[] = AW_SRC_MENU;
		return $order;
	},
	PHP_INT_MAX
);

// Plugins screen: Overview and Settings under the plugin's name (when AW Sourcing is listed there as its own plugin).
add_filter(
	'plugin_action_links_' . plugin_basename( AW_SOURCING_DIR . 'aw-sourcing.php' ),
	function ( $links ) {
		$own = array();
		foreach ( aw_src_action_link_paths() as $label => $path ) {
			$own[] = '<a href="' . esc_url( admin_url( $path ) ) . '">' . esc_html( $label ) . '</a>';
		}
		return array_merge( $own, (array) $links );
	}
);

// Sourcing → Settings: the plugin's own switch.
add_filter(
	'kits_sourcing_tabs',
	function ( $tabs ) {
		$tabs[ AW_SRC_SETTINGS_TAB ] = array( 'Settings', 'aw_sourcing_own_settings_tab', true ); // its own form (admin-post, as before)
		return $tabs;
	}
);

/** Sourcing → Settings (aw_sourcing_settings_tab() is another function: the Settings API forms' marker, sourcing-policy.php). */
function aw_sourcing_own_settings_tab() {
	echo '<p>' . esc_html( 'Switches for the whole of Sourcing. What the workers fetch is set on Sources, Keywords and the screens after them.' ) . '</p>';
	if ( function_exists( 'aw_sources_live_switch' ) ) {
		aw_sources_live_switch();
	}
	echo '<p class="description">' . esc_html( 'Off: the workers use sources.yaml in the workers repository. On: they read the lists on these screens at the start of every run, and Sources sync copies them into sources.yaml.' ) . '</p>';
}

/*
 * The screens' addresses before 25 Oct 2026 (Settings → Sourcing, Directory → Enrichment review) open the same screen on
 * the Sourcing menu, with their query arguments: alerts already e-mailed and bookmarks link there. WordPress lets the
 * old Settings address through to admin_init (it finds the page on this menu) and then cannot load it; it refuses the
 * old Directory address before admin_init. So both hooks.
 */
function aw_sourcing_moved_redirect() {
	global $pagenow;
	// phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- a GET redirect, nothing saved; each value is sanitized below.
	$page = sanitize_key( $_GET['page'] ?? '' );
	$get  = 'GET' === sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) );
	$old  = $get && ( ( 'options-general.php' === $pagenow && AW_SRC_PAGE === $page ) || ( 'edit.php' === $pagenow && 'aw-enrich-review' === $page ) );
	if ( ! $old ) {
		return;
	}
	$q = array();
	foreach ( wp_unslash( $_GET ) as $k => $v ) {
		if ( is_scalar( $v ) ) {
			$q[ sanitize_key( $k ) ] = sanitize_text_field( (string) $v );
		}
	}
	// phpcs:enable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	unset( $q['post_type'] );
	wp_safe_redirect( add_query_arg( array_map( 'rawurlencode', $q ), admin_url( 'admin.php' ) ) ); // 302: browsers keep no copy, so a rollback of this change is clean
	exit;
}
add_action( 'admin_init', 'aw_sourcing_moved_redirect', 1 );
add_action( 'admin_page_access_denied', 'aw_sourcing_moved_redirect' );

/* ---------- Sourcing → Overview ---------- */

/** One status cell: a core dashicon (yes / warning) and a short text. */
function aw_sourcing_overview_status( $ok, $text ) {
	return '<span class="dashicons dashicons-' . ( $ok ? 'yes' : 'warning' ) . '" aria-hidden="true"></span> ' . esc_html( $text );
}

/** A link to a Sourcing screen, or the screen's name alone for someone who cannot open it. */
function aw_sourcing_overview_link( $label, $url, $cap = AW_SRC_CAP ) {
	return current_user_can( $cap ) ? '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>' : esc_html( $label . ' (administrators)' );
}

/** The workers' ingest heartbeat, from the Site Health test (site-health.php) and the last report. */
function aw_sourcing_overview_ingest() {
	if ( ! function_exists( 'aw_sourcing_health_test' ) ) {
		return '';
	}
	$t   = aw_sourcing_health_test();
	$hb  = (array) get_option( 'aw_ingest_heartbeat', array() );
	$run = ! empty( $hb['last_run'] ) ? sprintf( ' Last run %1$s: %2$d new, %3$d published, %4$d of %5$d sources failed.', wp_date( 'j M Y, g:i a', (int) $hb['last_run'] ), (int) ( $hb['new'] ?? 0 ), (int) ( $hb['published'] ?? 0 ), (int) ( $hb['sources_failed'] ?? 0 ), (int) ( $hb['sources'] ?? 0 ) ) : '';
	return aw_sourcing_overview_status( 'good' === $t['status'], $t['label'] . '.' . $run );
}

/** Workers on time (workers.php), for administrators. */
function aw_sourcing_overview_workers() {
	if ( ! function_exists( 'aw_workers_problems' ) ) {
		return '';
	}
	$p = aw_workers_problems();
	return aw_sourcing_overview_status( ! $p, $p ? sprintf( _n( '%d problem: ', '%d problems: ', count( $p ) ), count( $p ) ) . implode( ' ', $p ) : 'Every workflow that reports is on time.' );
}

/**
 * The AI keys at a glance: how many model keys are saved and how many work (the AI models screen's own groups,
 * aw_ai_status_badge: works / waiting / broken), the providers that work, and the tool keys apart. Read only: the saved
 * rows as they are (never written, so not aw_ai_rows(), which converts the older option on first read), and no part of
 * any key.
 */
function aw_sourcing_overview_ai() {
	$rows = get_option( 'aw_ai_models', null );
	if ( ! is_array( $rows ) ) {
		return esc_html( get_option( 'aw_ai_keys' ) ? 'Keys saved the old way: opening the AI models screen converts them.' : 'No keys saved on the AI models screen yet.' );
	}
	$n     = array( 'works' => 0, 'wait' => 0, 'bad' => 0, 'tool' => 0 );
	$provs = array();
	foreach ( $rows as $r ) {
		if ( ! is_array( $r ) || empty( $r['key'] ) ) {
			continue;
		}
		$st = (string) ( $r['status'] ?? 'unchecked' );
		$p  = (string) ( $r['provider'] ?? '' );
		if ( 'tool' === $st ) {
			++$n['tool'];
		} elseif ( 'works' === $st ) {
			++$n['works'];
			$provs[ $p ] = defined( 'AW_AI_PROVIDERS' ) && isset( AW_AI_PROVIDERS[ $p ] ) ? AW_AI_PROVIDERS[ $p ][0] : $p;
		} elseif ( in_array( $st, array( 'limited', 'unchecked', 'card' ), true ) ) {
			++$n['wait'];
		} else {
			++$n['bad'];
		}
	}
	$provs = array_filter( $provs );
	sort( $provs );
	$text = sprintf( '%1$d AI keys: %2$d work, %3$d waiting or not checked, %4$d refused or out of credit; %5$d tool keys.', $n['works'] + $n['wait'] + $n['bad'], $n['works'], $n['wait'], $n['bad'], $n['tool'] ) . ( $provs ? ' Working: ' . implode( ', ', $provs ) . '.' : '' );
	return aw_sourcing_overview_status( $n['works'] > 0, $text );
}

/** The last entry of the Change log. */
function aw_sourcing_overview_last_change() {
	$log = (array) get_option( 'kits_sourcing_log', array() );
	$e   = reset( $log );
	return is_array( $e ) ? esc_html( sprintf( 'Last change %1$s by %2$s.', (string) ( $e['at'] ?? '' ), (string) ( $e['user'] ?? '' ) ) ) : esc_html( 'No changes yet.' );
}

/** Sourcing → Overview: every feature, what it does, where it lives and its status from the checks that already run. */
function aw_sourcing_overview_page() {
	if ( ! current_user_can( AW_REVIEW_CAP ) ) {
		return;
	}
	$tabs  = current_user_can( AW_SRC_CAP ) ? aw_sourcing_tabs() : array();
	$auto  = esc_html( 'Automatic' );
	$admin = current_user_can( 'manage_options' );
	$names = array( 'sources' => 'Sources', 'keywords' => 'Keywords', 'rules' => 'Rules', 'sections' => 'Sections', 'summary' => 'Summary rules', 'enrich' => 'Directory enrichment', 'ai' => 'AI models', 'workers' => 'Workers', 'rejected' => 'Rejected', 'relay-log' => 'Relay log', 'log' => 'Change log', AW_SRC_SETTINGS_TAB => 'Settings' );
	$tab   = fn( $k ) => isset( $tabs[ $k ] ) ? aw_sourcing_overview_link( 'Sourcing → ' . $tabs[ $k ][0], aw_sourcing_url( $k ) ) : esc_html( 'Sourcing → ' . ( $names[ $k ] ?? $k ) . ' (administrators)' );
	$pubs  = function_exists( 'aw_publishers' ) ? aw_publishers() : array();
	$by    = array_count_values( array_map( fn( $p ) => (string) ( $p['status'] ?? 'neutral' ), array_filter( $pubs, fn( $p ) => empty( $p['kind'] ) ) ) );
	$cfg   = function_exists( 'aw_sources_config' ) ? aw_sources_config() : array();
	$rel   = function_exists( 'aw_relevance' ) ? aw_relevance() : array();
	$prog  = function_exists( 'aw_enrich_progress' ) ? aw_enrich_progress() : array( 'checked' => 0, 'total' => 0, 'pending' => 0 );
	$rules = function_exists( 'aw_rules_live' ) && aw_rules_live();

	$groups = array(
		'Waiting for a person' => array(
			array( 'To review', 'Items that came in automatically (News, Library, Opportunities, Press, research papers) and wait for a person, with why each was held. Publish or reject one by one or together.', aw_sourcing_overview_link( 'Sourcing → To review', admin_url( 'admin.php?page=aw-review' ), AW_REVIEW_CAP ), esc_html( sprintf( _n( '%d item waiting.', '%d items waiting.', aw_review_count() ), aw_review_count() ) ) ),
			array( 'Enrichment review', 'Proposed details for Directory listings from the enrichment worker: approve, reject or undo; high-confidence values for empty fields are applied automatically.', aw_sourcing_overview_link( 'Sourcing → Enrichment review', admin_url( 'admin.php?page=aw-enrich-review' ), AW_ENRICH_CAP ), esc_html( sprintf( '%1$d of %2$d listings checked; %3$d proposals waiting.', (int) $prog['checked'], (int) $prog['total'], (int) $prog['pending'] ) ) ),
		),
		'What the workers fetch' => array(
			array( 'Sources', 'Every publisher and site the workers read: Allowed, Neutral or Blocked, short names, feeds, press sites and the relay switch.', $tab( 'sources' ), esc_html( sprintf( '%1$d allowed, %2$d neutral, %3$d blocked publishers.', (int) ( $by['allow'] ?? 0 ), (int) ( $by['neutral'] ?? 0 ), (int) ( $by['block'] ?? 0 ) ) ) ),
			array( 'Keywords', 'The words that decide what is kept (include, animal, context, exclude), the searches they make, suggestions from Search Insights and a headline test.', $tab( 'keywords' ), esc_html( 'Relevance filter ' . ( ! empty( $rel['enabled'] ) ? 'on' : 'off' ) . '; aggregator feeds ' . ( ! empty( $rel['gnews_allow_only'] ) ? 'allowed publishers only' : 'any publisher that is not blocked' ) . '.' ) ),
		),
		'Rules for what arrives' => $rules ? array(
			array( 'Rules', 'Section rules (categories and tags each section uses), summary rules, and Directory enrichment (its sources and the rule for each field).', $tab( 'rules' ), $auto ),
		) : array(
			array( 'Sections', 'The categories and tags each section uses.', $tab( 'sections' ), $auto ),
			array( 'Summary rules', 'One rule set for every summary on the site; the workers write and check summaries from it.', $tab( 'summary' ), $auto ),
			array( 'Directory enrichment', 'Which sources the enrichment worker may use, the live Google block\'s key and monthly cap.', $tab( 'enrich' ), $auto ),
		),
		'AI and workers' => array(
			array( 'AI models', 'Free AI keys the workers use for summaries and checks, which worker each is for, and their status. Paid keys are never served.', $tab( 'ai' ), $admin ? aw_sourcing_overview_ai() : '' ),
			array( 'Workers', 'Every workflow of the workers repository: schedule, last run, problems and Run now.', $tab( 'workers' ), $admin ? aw_sourcing_overview_workers() : '' ),
			array( 'Ingest', 'The worker endpoints that receive items (the publisher, keyword and relevance gate on arrival, same story, summaries, enrichment), the hourly watchdog and its e-mail.', esc_html( 'Automatic; Tools → Site Health' ), aw_sourcing_overview_ingest() ),
			array( 'Auto-review', 'Publishes an arriving item that passes every check; holds the others in To review with the reason.', '—', $auto ),
		),
		'Reports' => array(
			array( 'Rejected', 'Items the relevance check refused (never created), with the reason.', $tab( 'rejected' ), $admin ? esc_html( sprintf( '%d in the log.', count( (array) get_option( AW_RELEVANCE_LOG, array() ) ) ) ) : '' ),
			array( 'Relay log', 'Every request the workers made through this site for a publisher that refuses GitHub (the last 200).', $tab( 'relay-log' ), esc_html( sprintf( '%d requests logged.', count( (array) get_option( 'aw_relay_log', array() ) ) ) ) ),
			array( 'Change log', 'Who changed what on these screens and when, with the value before and after.', $tab( 'log' ), aw_sourcing_overview_last_change() ),
		),
		'Settings' => array(
			array( 'Settings', 'Workers use these lists: whether the workers read these screens or sources.yaml.', $tab( AW_SRC_SETTINGS_TAB ), aw_sourcing_overview_status( ! empty( $cfg['live'] ), 'Workers use these lists: ' . ( ! empty( $cfg['live'] ) ? 'on.' : 'off.' ) ) ),
		),
		'Elsewhere' => array(
			array( 'Sourcing keywords', 'Per Google News search: items brought in over 30 days and their pages\' Search Console figures.', function_exists( 'aw_si_url' ) && function_exists( 'aw_si_cap' ) && current_user_can( aw_si_cap() ) ? '<a href="' . esc_url( aw_si_url( 'sourcing' ) ) . '">' . esc_html( 'AW SEO Plus → Search Insights → Sourcing keywords' ) . '</a>' : esc_html( 'AW SEO Plus → Search Insights' ), $auto ),
			array( 'Site Health', 'Two tests: "Sourcing ran on time" (the ingest heartbeat) and "Workers report on time".', current_user_can( 'view_site_health_checks' ) ? '<a href="' . esc_url( admin_url( 'site-health.php' ) ) . '">' . esc_html( 'Tools → Site Health' ) . '</a>' : esc_html( 'Tools → Site Health' ), $auto ),
		),
	);
	// Press queries and Categories: their own screens before phase 2; from phase 2 a Keywords view and WordPress's own
	// categories (with a Keywords column); from phase 4 the old screens are gone.
	$kw_on = function_exists( 'aw_sourcing_kw_on' ) && aw_sourcing_kw_on();
	if ( isset( $tabs['press'] ) || $kw_on ) {
		$groups['What the workers fetch'][] = array( 'Press queries', 'Weekly searches for coverage of Animal Writes; results wait in To review.', $kw_on ? aw_sourcing_overview_link( 'Sourcing → Keywords → Press', aw_sourcing_url( 'keywords', array( 'view' => 'press' ) ) ) : $tab( 'press' ), $auto );
	}
	if ( isset( $tabs['categories'] ) || $kw_on ) {
		$groups['What the workers fetch'][] = array( 'Categories', 'The words that put an item in each category.', $kw_on ? aw_sourcing_overview_link( 'Posts → Categories (Keywords column)', admin_url( 'edit-tags.php?taxonomy=category' ), 'manage_categories' ) : $tab( 'categories' ), $auto );
	}

	echo '<div class="wrap"><h1>' . esc_html( 'Sourcing' ) . '</h1>';
	echo '<p>' . esc_html( sprintf( 'What the workers fetch, how it arrives and what waits for a person. Each row says where the feature lives in this menu. Sourcing restructure phase %d is in place.', function_exists( 'aw_sourcing_phase' ) ? aw_sourcing_phase() : 0 ) ) . '</p>';
	foreach ( $groups as $title => $rows ) {
		echo '<h2>' . esc_html( $title ) . '</h2>';
		$cells = array();
		foreach ( $rows as $r ) {
			$cells[] = array(
				'feature' => '<strong>' . esc_html( $r[0] ) . '</strong>',
				'what'    => esc_html( $r[1] ),
				'where'   => $r[2],
				'status'  => $r[3],
			);
		}
		aw_sourcing_simple_table( 'aw-sourcing-overview', array( 'feature' => 'Feature', 'what' => 'What it does', 'where' => 'Where', 'status' => 'Status' ), $cells, array( 'primary' => 'feature', 'foot' => false ) );
	}

	if ( $admin && function_exists( 'aw_workers_runs' ) && defined( 'AW_WORKERS' ) ) {
		echo '<h2>' . esc_html( 'Last runs' ) . '</h2>';
		$cells = array();
		$now   = time();
		foreach ( aw_workers_runs() as $file => $runs ) {
			$w = function_exists( 'aw_worker_def' ) ? aw_worker_def( $file, AW_WORKERS[ $file ] ) : AW_WORKERS[ $file ];
			krsort( $runs, SORT_NUMERIC );
			$last    = $runs ? reset( $runs ) : null;
			$seen    = $last ? max( (int) ( $last['ended'] ?? 0 ), (int) ( $last['started'] ?? 0 ) ) : 0;
			$next    = ! empty( $w['crons'] ) ? aw_workers_next( (array) $w['crons'], $now ) : 0;
			$cells[] = array(
				'worker' => '<strong>' . esc_html( (string) $w['name'] ) . '</strong>',
				'last'   => esc_html( $seen ? wp_date( 'j M Y, g:i a', $seen ) : '—' ),
				'result' => aw_workers_badge( $last, false ),
				'next'   => esc_html( $next ? wp_date( 'j M, g:i a', $next ) : ( ! empty( $w['after'] ) ? 'After "' . $w['after'] . '"' : 'By hand' ) ),
			);
		}
		aw_sourcing_simple_table( 'aw-sourcing-runs', array( 'worker' => 'Worker', 'last' => 'Last report', 'result' => 'Result', 'next' => 'Next due' ), $cells, array( 'primary' => 'worker', 'empty' => 'No worker has reported yet.', 'foot' => false ) );
		echo '<p>' . aw_sourcing_overview_link( 'Sourcing → Workers', aw_sourcing_url( 'workers' ) ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper
	}

	echo '<h2>' . esc_html( 'Commands (WP-CLI on the server)' ) . '</h2>';
	aw_sourcing_simple_table(
		'aw-sourcing-commands',
		array( 'cmd' => 'Command', 'what' => 'What it does' ),
		array(
			array( 'cmd' => '<code>wp aw-sourcing backup</code>, <code>migrate</code>, <code>verify</code>, <code>rollback</code>', 'what' => esc_html( 'The sourcing restructure phases (--phase=n): back up, migrate (with --dry-run), check and undo.' ) ),
			array( 'cmd' => '<code>wp aw-sourcing import-dr</code>', 'what' => esc_html( 'Domain Rating values for the Sources list.' ) ),
			array( 'cmd' => '<code>wp aw news-titles</code>', 'what' => esc_html( 'Apply corrected News or Press titles from a CSV file (apply <file.csv>, --dry-run).' ) ),
		),
		array( 'primary' => 'cmd', 'foot' => false )
	);
	echo '<p>' . esc_html( 'Each command lists its options with --help.' ) . '</p></div>';
}
