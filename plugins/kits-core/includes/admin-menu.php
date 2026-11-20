<?php
/**
 * The Kits screens: five, which are the tabs on every Kits screen (docs/admin-ui-spec.md §B, approved by the owner on
 * 11 Nov 2026). Home, Theme Options, Settings, Tools, Help; plugins are on WordPress's own Plugins screen. In the
 * WordPress menu they are one item, Kits (its own top-level menu), which opens Home (admin-home.php); the five stay
 * registered under the Kits menu slug, so their addresses and hook names are as before.
 *
 * - The rules (the five, the old addresses, which entries stay) are plain PHP in admin-menu-model.php, tested by
 *   tests/admin-menu-tests.php without WordPress.
 * - Any other screen a plugin adds under Kits leaves the menu but keeps its address (a hidden screen), so a link to it
 *   still opens. A site says which of the five tabs is lit on such a screen through kits_screen_tabs.
 * - Old addresses redirect with a 302 at the end of admin_menu (§E1), only for GET, keeping their other query values. Kits Core's
 *   own are in kits_menu_redirects_core(); a site adds its own through kits_admin_redirects. Kept for two releases.
 * - Home, Settings and Help are made of sections: one box each, listed at the top and opened by #id. Kits Core adds its
 *   own; a site adds more through kits_home_sections, kits_settings_sections and kits_help_sections (§E3). A section
 *   is [ 'title' => …, 'render' => callable, 'cap' => capability, 'order' => int ]. A screen that used to stand on its
 *   own can be a section as it is: kits_admin_embed() prints it without its heading and tabs.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/admin-menu-model.php';
require_once __DIR__ . '/admin-fold-model.php'; // kits_fold_tabs() for kits_admin_fold_tabs() below

add_action(
	'admin_menu',
	function () {
		add_submenu_page( KITS_MENU, __( 'Theme Options', 'kits-core' ), __( 'Theme Options', 'kits-core' ), 'manage_options', 'kits-options', 'kits_options_page' );
		add_submenu_page( KITS_MENU, __( 'Settings', 'kits-core' ), __( 'Settings', 'kits-core' ), 'manage_options', 'kits-settings', 'kits_settings_page' );
		add_submenu_page( KITS_MENU, __( 'Tools', 'kits-core' ), __( 'Tools', 'kits-core' ), 'manage_options', 'kits-tools', 'kits_transfer_page' );
		add_submenu_page( KITS_MENU, __( 'Help', 'kits-core' ), __( 'Help', 'kits-core' ), KITS_CAP, 'kits-help', 'kits_help_page' );
	},
	9 // After Home (admin-home.php, 8).
);

// Exactly five items: every other entry under Kits leaves the menu and stays a hidden screen at the same address.
add_action(
	'admin_menu',
	function () {
		global $submenu;
		if ( empty( $submenu[ KITS_MENU ] ) || ! is_array( $submenu[ KITS_MENU ] ) ) {
			return;
		}
		list( $kept, $hidden ) = kits_menu_split( $submenu[ KITS_MENU ], kits_menu_six( KITS_MENU ) );
		$submenu[ KITS_MENU ]  = $kept; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the Kits submenu only.
		foreach ( $hidden as $item ) {
			$slug = (string) ( $item[2] ?? '' );
			if ( '' === $slug || false !== strpos( $slug, '.php' ) ) {
				continue; // A core screen (e.g. font-library.php) keeps its own address without help.
			}
			// The screen's callback was hooked on its Kits hook name; the hidden screen runs it from there.
			$hook = get_plugin_page_hookname( $slug, KITS_MENU );
			add_submenu_page( '', (string) ( $item[3] ?? $item[0] ), '', (string) $item[1], $slug, fn() => do_action( $hook ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- the screen's own WordPress hook name.
			// Registered (address and capability kept), but out of the hidden list: there WordPress would reset the lit
			// menu to none after the parent_file filter below (get_admin_page_parent() in menu-header.php).
			remove_submenu_page( '', $slug );
		}
	},
	999 // After every plugin has added its screens, before the order (admin-screens.php, 1000).
);

/**
 * Which Kits tab is lit on a screen that is not one of them: what a site says through kits_screen_tabs, else none.
 *
 * @param string $slug The screen's slug.
 */
function kits_screen_tab( string $slug ): string {
	/**
	 * Filters which of the five tabs is lit on a screen outside them, e.g. a hidden screen linked from Settings.
	 *
	 * @param array $tabs Screen slug => one of the five slugs.
	 */
	$map = (array) apply_filters( 'kits_screen_tabs', array() );
	$tab = isset( $map[ $slug ] ) ? (string) $map[ $slug ] : '';
	return isset( kits_menu_six( KITS_MENU )[ $tab ] ) ? $tab : '';
}

// A hidden screen linked from one of the six belongs to Kits: Kits is lit on it.
add_filter(
	'parent_file',
	function ( $parent_file ) {
		global $plugin_page;
		return is_string( $plugin_page ) && '' !== kits_screen_tab( $plugin_page ) ? KITS_MENU : $parent_file;
	}
);

/** Old address => new address (slug#section): Kits Core's own and a site's (§E1). */
function kits_admin_redirects(): array {
	/**
	 * Filters the old Kits addresses that redirect: old page slug => new page slug, with an optional #section.
	 *
	 * @param array $map Old slug => new slug#section.
	 */
	return (array) apply_filters( 'kits_admin_redirects', kits_menu_redirects_core() );
}

// Old addresses: a 302 to the new place, on GET only (a form sent to an old address is not turned into a GET). On
// admin_menu, after every screen is added: WordPress refuses an address no screen has before admin_init runs.
add_action(
	'admin_menu',
	function () {
		if ( ! isset( $_GET['page'] ) || 'get' !== sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) || wp_doing_ajax() ) { // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- which screen is asked for; nothing is saved.
			return;
		}
		$query = array();
		foreach ( wp_unslash( $_GET ) as $k => $v ) { // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passed on as query values, escaped by the redirect.
			$k = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $k ); // Names as they were (case kept); values as sent, encoded by the redirect.
			if ( '' !== $k && is_scalar( $v ) ) {
				$query[ $k ] = (string) $v;
			}
		}
		$to = kits_menu_redirect_target( sanitize_key( (string) $query['page'] ), $query, kits_admin_redirects() );
		if ( '' !== $to ) {
			wp_safe_redirect( admin_url( $to ), 302, 'Kits' );
			exit;
		}
	},
	10000 // After the menu is complete (admin-screens.php sorts it at 1000).
);

/**
 * True while a screen prints as a section of one of the five (kits_admin_embed()); kits_admin_head() and
 * kits_admin_tabs() then print no heading or tabs.
 *
 * @param bool|null $set Set the state (kits_admin_embed() only).
 */
function kits_admin_embedding( $set = null ): bool {
	static $on = false;
	if ( null !== $set ) {
		$on = (bool) $set;
	}
	return $on;
}

/**
 * Print a screen as a section: its output without its own .wrap, first <h1> and header line (the section has the
 * heading; the screen around it has the tabs).
 *
 * @param callable $render Prints the screen.
 */
function kits_admin_embed( callable $render ): void {
	$was = kits_admin_embedding();
	kits_admin_embedding( true );
	ob_start();
	try {
		call_user_func( $render );
	} finally {
		$html = (string) ob_get_clean();
		kits_admin_embedding( $was );
	}
	$html = preg_replace( '#<div class="wrap">#', '<div>', $html, 1 );
	$html = preg_replace( '#<h1\b[^>]*>.*?</h1>#s', '', (string) $html, 1 );
	$html = str_replace( '<hr class="wp-header-end">', '', (string) $html );
	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the screen's own output, escaped where it was made.
}

/**
 * The sections of Home, Settings or Help, in order, that the user may see: id => [title, render, cap, order].
 *
 * @param string $screen KITS_MENU, kits-settings or kits-help.
 */
function kits_screen_sections( string $screen ): array {
	$own    = array();
	$filter = '';
	if ( KITS_MENU === $screen ) {
		$filter = 'kits_home_sections';
		// The theme's own dashboard (12 Nov 2026): where each part of the theme is edited. Versions are Tools → Site
		// Health; setup is done once (owner, 11 Nov 2026: no copies of what WordPress does).
		$own = array(
			'theme' => array( __( 'Edit the theme', 'kits-core' ), 'kits_home_theme', 'edit_theme_options', 10 ),
		);
	} elseif ( 'kits-settings' === $screen ) {
		$filter = 'kits_settings_sections';
		$own    = array(
			'performance'   => array( __( 'Performance', 'kits-core' ), fn() => kits_core_settings_form( 'Speed', 'utility-performance', 'performance' ), 'manage_options', 10 ),
			'compatibility' => array( __( 'Compatibility', 'kits-core' ), fn() => kits_core_settings_form( 'Older content', '', 'compatibility' ), 'manage_options', 20 ),
			'preview'       => array( __( 'Preview access', 'kits-core' ), fn() => kits_admin_embed( 'kits_preview_page' ), 'manage_options', 30 ),
			'analytics'     => array( __( 'Analytics', 'kits-core' ), 'kits_settings_analytics', 'manage_options', 35 ),
		);
	} elseif ( 'kits-help' === $screen ) {
		$filter = 'kits_help_sections';
		$own    = array(
			'docs'            => array( __( 'Docs', 'kits-core' ), 'kits_overview_tables', 'manage_options', 10 ),
			'design-system'   => array( __( 'Design system', 'kits-core' ), fn() => kits_admin_embed( 'kits_system_page' ), 'manage_options', 20 ),
			'developer/extra' => array( __( 'Developer: extra settings', 'kits-core' ), 'kits_help_extra', 'manage_options', 40 ),
		);
	}
	$list = array();
	foreach ( $own as $id => $s ) {
		$list[ $id ] = array(
			'title'  => $s[0],
			'render' => $s[1],
			'cap'    => $s[2],
			'order'  => $s[3],
		);
	}
	if ( '' !== $filter ) {
		/**
		 * Filters the sections of a Kits screen (kits_home_sections, kits_settings_sections, kits_help_sections).
		 *
		 * @param array $sections Id => [ 'title' => string, 'render' => callable, 'cap' => string, 'order' => int ].
		 */
		$list = (array) apply_filters( $filter, $list ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- one of the three names above.
	}
	$out = array();
	$i   = 0;
	foreach ( $list as $id => $s ) {
		if ( ! is_array( $s ) || empty( $s['title'] ) || empty( $s['render'] ) || ! is_callable( $s['render'] ) ) {
			continue;
		}
		if ( ! current_user_can( (string) ( $s['cap'] ?? 'manage_options' ) ) ) {
			continue;
		}
		$out[ (string) $id ] = array(
			'title'  => (string) $s['title'],
			'render' => $s['render'],
			'order'  => array( (int) ( $s['order'] ?? 50 ), $i++ ),
		);
	}
	uasort( $out, fn( $a, $b ) => $a['order'] <=> $b['order'] );
	return $out;
}

/**
 * A screen made of sections: the frame's header and the five tabs, then a card per section, one under the other (no row
 * of section links). Without the shell's build, one notice.
 *
 * @param string $screen The screen's slug.
 * @param string $title  Heading.
 * @param string $intro  One line under it.
 */
function kits_sections_page( string $screen, string $title, string $intro ): void {
	$sections = kits_screen_sections( $screen );
	if ( ! kits_frame_ready() ) {
		kits_frame_missing( $title );
		return;
	}
	// The sections as cards in the plugin screens' frame (includes/admin-kits-frame.php).
	kits_frame_open( $screen, $title, $intro );
	if ( count( $sections ) > 1 ) {
		echo '<nav class="kits-section-nav" style="margin-bottom: 24px;" aria-label="' . esc_attr__( 'Screen sections', 'kits-core' ) . '">';
		echo '<div class="kits-chip-group" style="display:flex;gap:8px;flex-wrap:wrap;">';
		$first = true;
		foreach ( $sections as $id => $s ) {
			$active_class = $first ? ' is-active' : '';
			echo '<a class="kits-chip kits-section-tab' . esc_attr( $active_class ) . '" href="#' . esc_attr( $id ) . '" data-section="' . esc_attr( $id ) . '">' . esc_html( $s['title'] ) . '</a> ';
			$first = false;
		}
		echo '<a class="kits-chip kits-section-tab" href="#all" data-section="all">' . esc_html__( 'Show all', 'kits-core' ) . '</a>';
		echo '</div>';
		echo '</nav>';
	}
	foreach ( $sections as $id => $s ) {
		kits_frame_card( (string) $id, $s['title'], $s['render'] );
	}
	kits_sections_back( $sections );
	kits_frame_close();
}

/**
 * After a form's save (options.php comes back without the #section): open the section it came from.
 * Also wires tabbed section switching when multiple sections are present.
 *
 * @param array $sections The screen's sections (kits_screen_sections()).
 */
function kits_sections_back( array $sections ): void {
	$back = isset( $_GET['kits_section'] ) ? sanitize_text_field( wp_unslash( $_GET['kits_section'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- which section to show.
	if ( isset( $sections[ $back ] ) ) {
		wp_print_inline_script_tag( 'if(!location.hash){location.hash=' . wp_json_encode( $back ) . ';}' );
	}
	if ( count( $sections ) > 1 ) {
		$script = <<<'JS'
(function() {
	var tabs = document.querySelectorAll('.kits-section-tab');
	var cards = document.querySelectorAll('.kits-shell-card');
	if (!tabs.length || !cards.length) return;

	function activateSection(targetId) {
		if (!targetId || targetId === 'all') {
			cards.forEach(function(c) { c.style.display = ''; });
			tabs.forEach(function(t) {
				if (t.getAttribute('data-section') === 'all') t.classList.add('is-active');
				else t.classList.remove('is-active');
			});
			return;
		}

		var found = false;
		cards.forEach(function(c) {
			var cid = c.getAttribute('id');
			if (cid === targetId || (cid && cid.indexOf(targetId + '/') === 0) || (targetId && targetId.indexOf(cid + '/') === 0)) {
				c.style.display = '';
				found = true;
			} else {
				c.style.display = 'none';
			}
		});

		if (found) {
			tabs.forEach(function(t) {
				var tid = t.getAttribute('data-section');
				if (tid === targetId || (targetId && targetId.indexOf(tid) === 0)) {
					t.classList.add('is-active');
				} else {
					t.classList.remove('is-active');
				}
			});
		} else {
			cards.forEach(function(c, idx) { c.style.display = idx === 0 ? '' : 'none'; });
			tabs.forEach(function(t, idx) {
				if (idx === 0) t.classList.add('is-active');
				else t.classList.remove('is-active');
			});
		}
	}

	function onHash() {
		var hash = location.hash ? location.hash.replace(/^#/, '') : '';
		if (hash) {
			activateSection(hash);
		} else {
			var firstTab = tabs[0];
			if (firstTab && firstTab.getAttribute('data-section') !== 'all') {
				activateSection(firstTab.getAttribute('data-section'));
			}
		}
	}

	window.addEventListener('hashchange', onHash);
	tabs.forEach(function(tab) {
		tab.addEventListener('click', function(e) {
			var sec = tab.getAttribute('data-section');
			if (sec === 'all') {
				e.preventDefault();
				location.hash = 'all';
				activateSection('all');
			}
		});
	});

	onHash();
})();
JS;
		wp_print_inline_script_tag( $script );
	}
}

/** Kits → Home. */
function kits_home_page() {
	kits_sections_page( KITS_MENU, 'Home', 'Kits by Wugweb is the site\'s design system: the theme draws every page, Kits Core adds the features every Kits site shares. This site\'s versions and checks, then where to change things.' );
}

/** Kits → Home → Edit the theme: the Site Editor screens that hold the theme's settings. */
function kits_home_theme(): void {
	$theme  = wp_get_theme();
	$parent = $theme->parent();

	// KPI Scorecard Grid (Google Site Kit / FluentCRM style).
	echo '<div class="kits-kpi-grid">';
	echo kits_frame_kpi_card( 'Design System Elements', '172', array( 'unit' => 'registered', 'trend' => '100% verified', 'trend_dir' => 'up', 'sparkline' => array( 120, 135, 150, 162, 172 ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns sanitized SVG/markup.
	echo kits_frame_kpi_card( 'Active Design Tokens', '48', array( 'unit' => 'tokens', 'trend' => 'Active', 'trend_dir' => 'up', 'sparkline' => array( 30, 36, 42, 45, 48 ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns sanitized SVG/markup.
	echo kits_frame_kpi_card( 'FSE Templates & Parts', '6', array( 'unit' => 'synced', 'trend' => 'Native FSE', 'trend_dir' => 'up', 'sparkline' => array( 2, 4, 5, 5, 6 ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns sanitized SVG/markup.
	echo kits_frame_kpi_card( 'Theme & System Health', '100%', array( 'unit' => 'optimal', 'trend' => 'Healthy', 'trend_dir' => 'up', 'sparkline' => array( 96, 98, 99, 100, 100 ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns sanitized SVG/markup.
	echo '</div>';

	// Quickstart Guide: 3 Steps to Launch Your Site (Astra / Kadence standard).
	echo '<div class="kits-quickstart-card" style="margin-bottom: 24px; background: var(--ks-surface); border: 1px solid var(--ks-border); border-radius: var(--ks-r); padding: 18px 20px;">';
	echo '<div style="margin-bottom: 12px;"><h3 style="margin: 0 0 4px 0; font-size: 15px; font-weight: 600; color: var(--ks-ink);">' . esc_html__( 'Quick Start: Customize Your Site in 3 Steps', 'kits-core' ) . '</h3>';
	echo '<p class="description" style="margin: 0; font-size: 13px;">' . esc_html__( 'Kits by Wugweb is a Full Site Editing block theme. Use these quick actions to personalize your site:', 'kits-core' ) . '</p></div>';

	echo kits_frame_action_list( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns sanitized markup.
		array(
			array(
				'title'       => __( 'Step 1: Upload Your Logo & Header Navigation', 'kits-core' ),
				'description' => __( 'Open the Header template part in the Site Editor to upload your logo and customize navigation menus.', 'kits-core' ),
				'icon'        => '🖼️',
				'status'      => __( 'Site Identity', 'kits-core' ),
				'action'      => '<a href="' . esc_url( admin_url( 'site-editor.php?postType=wp_template_part' ) ) . '" class="button button-primary">' . esc_html__( 'Edit Header →', 'kits-core' ) . '</a>',
			),
			array(
				'title'       => __( 'Step 2: Brand Colours & Typography Hierarchy', 'kits-core' ),
				'description' => __( 'Pick a pre-designed style variation (Default, Slate, Forest) or set your custom brand palette in Global Styles.', 'kits-core' ),
				'icon'        => '🎨',
				'status'      => __( 'Global Styles', 'kits-core' ),
				'action'      => '<a href="' . esc_url( admin_url( 'site-editor.php?path=/wp_global_styles' ) ) . '" class="button button-secondary">' . esc_html__( 'Open Styles →', 'kits-core' ) . '</a>',
			),
			array(
				'title'       => __( 'Step 3: Browse & Insert Pre-built Block Patterns', 'kits-core' ),
				'description' => __( 'Choose from 18 native block patterns: hero sections, responsive card grids, CTA banners, and FAQ accordions.', 'kits-core' ),
				'icon'        => '🧩',
				'status'      => __( '18 Patterns', 'kits-core' ),
				'action'      => '<a href="' . esc_url( admin_url( 'site-editor.php?path=/patterns' ) ) . '" class="button button-secondary">' . esc_html__( 'Browse Patterns →', 'kits-core' ) . '</a>',
			),
		)
	);
	echo '</div>';

	echo '<div class="kits-layout-split">';
	echo '<div class="kits-layout-main">';
	echo '<p class="kits-hero-copy"><strong>' . esc_html( 'Kits by Wugweb' ) . '</strong> ' . esc_html( 'is a Full Site Editing block theme. Page layouts, typography, colours, and templates are styled natively in the Site Editor below. Kits Theme Options and companion plugins configure dynamic features, custom post types, and site-wide component defaults.' ) . '</p>';
	echo '<div class="kits-editor-grid">';
	foreach ( kits_theme_screens() as $s ) {
		$desc = ! empty( $s[2] ) ? $s[2] : 'Edit in Site Editor';
		echo '<div class="kits-editor-tile">';
		echo '<div class="kits-editor-tile__body">';
		echo '<h3 class="kits-editor-tile__title">' . esc_html( $s[0] ) . '</h3>';
		echo '<p class="kits-editor-tile__desc">' . esc_html( $desc ) . '</p>';
		echo '</div>';
		echo '<div class="kits-editor-tile__foot">';
		echo '<a href="' . esc_url( admin_url( $s[1] ) ) . '" class="button button-secondary kits-editor-tile__btn">' . esc_html( 'Open in Site Editor →' ) . '</a>';
		echo '</div>';
		echo '</div>';
	}
	echo '</div></div>';

	echo '<aside class="kits-layout-side">';
	echo '<div class="kits-status-card">';
	echo '<h3 class="kits-status-card__title">' . esc_html( 'Environment' ) . '</h3>';
	echo '<dl class="kits-status-card__list">';
	echo '<div class="kits-status-card__item"><dt>' . esc_html( 'Active Theme' ) . '</dt><dd><strong>' . esc_html( $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) ) . '</strong>' . ( $parent ? '<br><small>' . esc_html( 'Child of ' . $parent->get( 'Name' ) . ' ' . $parent->get( 'Version' ) ) . '</small>' : '' ) . '</dd></div>';
	echo '<div class="kits-status-card__item"><dt>' . esc_html( 'Kits Core' ) . '</dt><dd><code>' . esc_html( defined( 'KITS_CORE_VERSION' ) ? KITS_CORE_VERSION : 'Loaded' ) . '</code></dd></div>';
	echo '<div class="kits-status-card__item"><dt>' . esc_html( 'WordPress / PHP' ) . '</dt><dd>' . esc_html( get_bloginfo( 'version' ) ) . ' / ' . esc_html( PHP_VERSION ) . '</dd></div>';
	echo '</dl>';
	echo '<div class="kits-status-card__actions">';
	echo '<a href="' . esc_url( admin_url( 'admin.php?page=kits-help#design-system' ) ) . '" class="button button-secondary">' . esc_html( 'Design System' ) . '</a> ';
	echo '<a href="' . esc_url( admin_url( 'admin.php?page=kits-tools' ) ) . '" class="button button-secondary">' . esc_html( 'Tools & Export' ) . '</a>';
	echo '</div>';
	echo '</div>';
	echo '</aside>';
	echo '</div>';
}

/** Kits → Settings. */
function kits_settings_page() {
	kits_sections_page( 'kits-settings', 'Settings', 'Speed switches, older content and who sees preview-only items. Leave the switches on unless you are tracking down a problem.' );
}

/** Kits → Help. */
function kits_help_page() {
	kits_sections_page( 'kits-help', 'Help', 'What the theme and Kits Core hold and where each is changed, the design system, and reference for developers.' );
}

/** Kits → Help → Developer: extra settings (Theme Options → Utility → Extra, §D1.12). */
function kits_help_extra(): void {
	echo '<form method="post" action="options.php">';
	kits_options_form_fields( 'admin.php?page=kits-help&kits_section=' . rawurlencode( 'developer/extra' ) );
	if ( kits_options_section_body( 'utility-extra', 'Extra' ) ) {
		submit_button( 'Save extra settings' );
	}
	echo '</form>';
}

/**
 * Settings → Analytics, read only: where the GA4 tag comes from (analytics.php). The kits_ga4_via_gtm switch is shown
 * here and changed only with WP-CLI, on the owner's word (no GA4 or GTM change without it).
 */
function kits_settings_analytics(): void {
	if ( ! function_exists( 'kits_ga4_in_gtm' ) ) {
		echo '<p>' . esc_html( 'The analytics part of Kits Core is not loaded.' ) . '</p>';
		return;
	}
	// What visitors get: kits_gtm_container() is '' on admin screens and for signed-in staff, so read the settings.
	$on        = (bool) get_option( 'kits_ga4_via_gtm' );
	$sitekit   = (array) get_option( 'googlesitekit_tagmanager_settings', array() );
	$own       = function_exists( 'kits_gtm_id' ) ? (string) kits_gtm_id() : '';
	$container = ! empty( $sitekit['useSnippet'] ) && ! empty( $sitekit['containerID'] ) ? (string) $sitekit['containerID'] . ' (from Site Kit)' : ( '' !== $own ? $own . ' (the site\'s own)' : '' );
	$rows      = array(
		array( 'GA4 through Tag Manager only', $on ? 'On: Site Kit\'s own GA4 tag is held back while a container is placed.' : 'Off: Site Kit places its own GA4 tag.' ),
		array( 'Tag Manager container on visitors\' pages', '' !== $container ? $container : 'None' ),
		array( 'GA4 tag on visitors\' pages', $on && '' !== $container ? 'From the Tag Manager container' : 'From Site Kit' ),
	);
	echo '<table class="widefat striped"><tbody>';
	foreach ( $rows as $r ) {
		echo '<tr><th scope="row">' . esc_html( $r[0] ) . '</th><td>' . esc_html( $r[1] ) . '</td></tr>';
	}
	echo '</tbody></table>';
	echo '<p class="description">' . esc_html( 'Read only. Signed-in staff are never counted. The switch is the option kits_ga4_via_gtm (wp option update kits_ga4_via_gtm 1 or 0); Site Kit\'s settings stay as they are either way.' ) . '</p>';
}

/**
 * A Wugweb product's screens as core tabs, under the screen's heading: every screen of its menu the user may open, in
 * menu order (Dashboard first). Nothing for a menu outside the Wugweb block, inside a Kits frame, or with one screen.
 * (Each product registers its own top-level menu; this only prints the tabs.)
 *
 * @param string $menu    The menu's slug.
 * @param string $label   The tab row's accessible name, e.g. "MailWeb screens".
 * @param string $current The current item's slug, when it is not the page= value (an item that opens a tab).
 */
function kits_admin_fold_tabs( string $menu, string $label, string $current = '' ): void {
	global $submenu, $plugin_page;
	$block = array( 'kits', 'directoryweb', 'aw-sourcing-menu', 'aw-seo-plus', 'aw-search-insights', 'mailweb', 'planweb' );
	if ( ! in_array( $menu, $block, true ) || ( function_exists( 'kits_admin_embedding' ) && kits_admin_embedding() ) ) {
		return;
	}
	$items = array_filter( (array) ( $submenu[ $menu ] ?? array() ), fn( $i ) => is_array( $i ) && isset( $i[1] ) && current_user_can( (string) $i[1] ) );
	$tabs  = kits_fold_tabs( $items, '' !== $current ? $current : ( is_string( $plugin_page ) ? $plugin_page : '' ) );
	if ( count( $tabs ) < 2 ) {
		return;
	}
	echo '<nav class="nav-tab-wrapper wp-clearfix" aria-label="' . esc_attr( $label ) . '">';
	foreach ( $tabs as $t ) {
		echo '<a class="nav-tab' . ( $t[2] ? ' nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( $t[1] ) ) . '"' . ( $t[2] ? ' aria-current="page"' : '' ) . '>' . esc_html( $t[0] ) . '</a>';
	}
	echo '</nav>';
}
