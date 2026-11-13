<?php
/**
 * The Sourcing menu and its Overview (owner, 25 Oct 2026: SourceWeb meets the admin standard of the other plugins:
 * one menu, an Overview of every feature and where it lives, links from the Plugins screen; core WordPress only).
 *
 * Plugins → SourceWeb (one item since 11 Nov 2026) opens the Overview (this file). Its screens, at the same addresses
 * as before and shown as tabs on each: Overview, To review (review-queue.php), Enrichment review (enrichment-admin.php),
 * then one per screen of the Sourcing page (admin.php?page=aw-sourcing&tab=…, sourcing-policy.php aw_sourcing_page): the
 * settings screens (Sources, Keywords, Rules, AI models), the reports and jobs (Workers, Rejected, Relay log, Change
 * log), and Settings. Every screen is drawn on the Kits shell (sourceweb-ui.php). The order and the addresses are in
 * menu-model.php. Until 25 Oct 2026 the screens were Settings → Sourcing (tabs), Inbox → To review and
 * Directory → Enrichment review.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Sourcing page's tabs in menu order: slug => [label]. Sources, Keywords and the Change log are here; every other
 * tab comes from the kits_sourcing_tabs filter. Each is a shell screen (sourceweb-ui.php).
 *
 * @return array
 */
function aw_sourcing_tabs() {
	static $memo = null; // the same answer all request: the menu, the current item, the title and the page ask for it
	if ( null !== $memo ) {
		return $memo;
	}
	$all = array(
		'sources'  => array( 'Sources' ),
		'keywords' => array( 'Keywords' ),
	) + (array) apply_filters( 'kits_sourcing_tabs', array() ) + array( 'log' => array( 'Change log' ) );
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
		// Plugins → SourceWeb (owner, 11 Nov 2026: "all plugins" under Plugins), opening the Overview, with the To review
		// count; the other screens are its tabs (the shell header). Without Kits Core, a menu of its own as before.
		// WordPress names the screens under a menu after its hook prefix, which stays "sourcing" (AW_SRC_SCREEN,
		// sourcing_page_…): saved screen options and columns stay.
		$count = current_user_can( AW_REVIEW_CAP ) && function_exists( 'aw_review_count' ) && function_exists( 'aw_menu_count' ) ? aw_menu_count( aw_review_count() ) : '';
		if ( function_exists( 'kits_admin_fold_menu' ) ) {
			kits_admin_fold_menu( AW_SRC_MENU, 'plugins.php', 'SourceWeb' . $count, AW_REVIEW_CAP, 'sourcing', dirname( __DIR__ ) . '/assets/menu-icon.svg' );
		} else {
			add_menu_page( 'SourceWeb', 'SourceWeb', AW_REVIEW_CAP, AW_SRC_MENU, 'aw_sourcing_overview_page', 'dashicons-rss', '58.3' );
			$GLOBALS['admin_page_hooks'][ AW_SRC_MENU ] = 'sourcing'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the menu's hook prefix, as before the rename.
			if ( '' !== $count ) {
				global $menu;
				foreach ( (array) $menu as $i => $item ) {
					if ( AW_SRC_MENU === ( $item[2] ?? '' ) ) {
						$menu[ $i ][0] .= $count; // phpcs:ignore WordPress.WP.GlobalVariablesOverride -- the count bubble, as WordPress adds its own
					}
				}
			}
		}
		add_submenu_page( AW_SRC_MENU, 'SourceWeb', 'Overview', AW_REVIEW_CAP, AW_SRC_MENU, 'aw_sourcing_overview_page' );
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
					error_log( 'SourceWeb: the Sourcing page is ' . $hook . ', not ' . AW_SRC_SCREEN . ' (menu-model.php); list-table settings and columns will not apply.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
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

// Plugins screen: Overview and Settings under the plugin's name (when SourceWeb is listed there as its own plugin).
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
		$tabs[ AW_SRC_SETTINGS_TAB ] = array( 'Settings' );
		return $tabs;
	}
);

// Backfill floor (owner, 9 Oct 2026): the oldest source date a backfill item may have (ingest.php); its field is on
// SourceWeb → Settings (sourceweb_extra_parts()), every save of it is checked.
add_action(
	'admin_init',
	function () {
		register_setting(
			'aw_src_backfill',
			'aw_backfill_floor',
			array(
				'type'              => 'string',
				'default'           => '2025-11-01',
				'sanitize_callback' => 'aw_src_backfill_floor_clean',
				'show_in_rest'      => false,
			)
		);
		add_settings_section( 'aw_src_backfill', 'Backfill', '__return_null', 'aw-src-backfill' );
		add_settings_field( 'aw_backfill_floor', 'Oldest date', 'aw_src_backfill_floor_field', 'aw-src-backfill', 'aw_src_backfill', array( 'label_for' => 'aw_backfill_floor' ) );
	}
);

/** The backfill date field. */
function aw_src_backfill_floor_field(): void {
	printf(
		'<input type="date" id="aw_backfill_floor" name="aw_backfill_floor" value="%1$s"> <p class="description">%2$s</p>',
		esc_attr( (string) get_option( 'aw_backfill_floor', '2025-11-01' ) ),
		esc_html( 'Backfill items dated before this day are dropped. Backfill items are for review only: never published or merged on arrival.' )
	);
}

/**
 * A date as Y-m-d, or the saved one (with a message) when the posted value is not a date.
 *
 * @param mixed $value Posted value.
 */
function aw_src_backfill_floor_clean( $value ): string {
	$value = trim( (string) $value );
	$d     = DateTime::createFromFormat( '!Y-m-d', $value );
	if ( $d && $d->format( 'Y-m-d' ) === $value ) {
		return $value;
	}
	add_settings_error( 'aw_backfill_floor', 'aw_backfill_floor', 'Oldest date: not a date (YYYY-MM-DD); the saved date is kept.' );
	return (string) get_option( 'aw_backfill_floor', '2025-11-01' );
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

/** A link to a Sourcing screen, or the screen's name alone for someone who cannot open it. */
function aw_sourcing_overview_link( $label, $url, $cap = AW_SRC_CAP ) {
	return current_user_can( $cap ) ? '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>' : esc_html( $label . ' (administrators)' );
}

/**
 * The AI keys' status counts, for the Dashboard tile: keys that work, wait, are refused, tool keys, and
 * the providers that work. Read only, from the saved rows as they are; no part of any key.
 *
 * @return array { saved: bool, old: bool, works, wait, bad, tool, providers: string[] }.
 */
function aw_sourcing_ai_counts() {
	$rows = get_option( 'aw_ai_models', null );
	$n    = array(
		'saved'     => is_array( $rows ),
		'old'       => ! is_array( $rows ) && (bool) get_option( 'aw_ai_keys' ),
		'works'     => 0,
		'wait'      => 0,
		'bad'       => 0,
		'tool'      => 0,
		'providers' => array(),
	);
	if ( ! is_array( $rows ) ) {
		return $n;
	}
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
	$provs = array_values( array_filter( $provs ) );
	sort( $provs );
	$n['providers'] = $provs;
	return $n;
}

/** Sourcing → Overview: the shell Dashboard (sourceweb-ui.php). */
function aw_sourcing_overview_page() {
	if ( ! current_user_can( AW_REVIEW_CAP ) ) {
		return;
	}
	sourceweb_ui_page( 'dashboard' );
}
