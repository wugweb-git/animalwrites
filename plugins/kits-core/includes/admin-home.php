<?php
/**
 * Kits menu and Overview (owner, 25 Oct 2026: our plugins and themes work like any marketplace product: one menu, an
 * overview of every feature and where it lives, links from the Plugins screen).
 *
 * Kits → Overview (this file); Navigation, Styles, Templates, Header & footer, Patterns (the Site Editor's screens);
 * Design system (system-screen.php, was Appearance → Kits system); Preview (preview.php, was Settings → Preview). The Overview covers the plugin's features and the design and branding of the active Kits
 * theme and its child (colours, fonts, templates, parts, patterns, style variations, the design registry), read live
 * from WordPress, each linked to where it is edited. Core WordPress only.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KITS_MENU = 'kits';

add_action(
	'admin_menu',
	function () {
		add_menu_page( 'Kits', 'Kits', 'manage_options', KITS_MENU, 'kits_overview_page', 'dashicons-layout', 61 );
		add_submenu_page( KITS_MENU, 'Kits', 'Overview', 'manage_options', KITS_MENU, 'kits_overview_page' );
		// The theme's own settings, each opening its Site Editor screen (owner, 25 Oct 2026: reachable from the menu).
		foreach ( kits_theme_screens() as $slug => $s ) {
			add_submenu_page( KITS_MENU, $s[0], $s[0], 'edit_theme_options', $s[1] );
		}
	},
	9 // before Design system and Preview join the menu
);

/** The theme's settings in the Site Editor: menu label and address (relative to wp-admin), in menu order. */
function kits_theme_screens(): array {
	return array(
		'navigation' => array( 'Navigation', 'site-editor.php?path=/navigation' ),
		'styles'     => array( 'Styles (colours, fonts)', 'site-editor.php?path=/wp_global_styles' ),
		'templates'  => array( 'Templates', 'site-editor.php?postType=wp_template' ),
		'parts'      => array( 'Header & footer', 'site-editor.php?postType=wp_template_part' ),
		'patterns'   => array( 'Patterns', 'site-editor.php?path=/patterns' ),
	);
}

// Plugins screen: Overview under the plugin's name (when Kits Core is active on its own).
add_filter(
	'plugin_action_links_' . plugin_basename( KITS_CORE_DIR . 'kits-core.php' ),
	fn( $links ) => array_merge( array( '<a href="' . esc_url( admin_url( 'admin.php?page=' . KITS_MENU ) ) . '">Overview</a>' ), (array) $links )
);

/** A link for the Overview tables. */
function kits_link( string $label, string $path ): string {
	return '<a href="' . esc_url( admin_url( $path ) ) . '">' . esc_html( $label ) . '</a>';
}

/** Rows: a list of [feature, what, where (HTML), status (HTML)] as a core list table. */
function kits_overview_table( string $name, array $rows ): void {
	$cells = array();
	foreach ( $rows as $r ) {
		$cells[] = array(
			'feature' => '<strong>' . esc_html( $r[0] ) . '</strong>',
			'what'    => $r[1],
			'where'   => $r[2],
			'status'  => $r[3],
		);
	}
	kits_simple_table( $name, array( 'feature' => 'Feature', 'what' => 'What it is', 'where' => 'Where', 'status' => 'On this site' ), $cells, array( 'primary' => 'feature' ) );
}

/** Kits → Overview. */
function kits_overview_page() {
	$theme  = wp_get_theme();
	$parent = $theme->parent();
	$auto   = 'Automatic';

	// Plugin features and their state.
	$gtm     = function_exists( 'kits_gtm_container' ) ? kits_gtm_container() : '';
	$clarity = function_exists( 'aw_clarity_id' ) ? (string) aw_clarity_id() : '';
	$visits  = (int) get_option( 'aw_visits_total', 0 );
	$since   = (string) get_option( 'aw_visits_since', '' );
	$ips     = function_exists( 'kits_preview_ips' ) ? count( kits_preview_ips() ) : count( array_filter( preg_split( '/[\r\n,]+/', (string) get_option( 'kits_preview_ips', '' ) ) ) );
	$devices = function_exists( 'kits_preview_devices' ) ? count( kits_preview_devices() ) : 0;

	echo '<div class="wrap"><h1>Kits</h1>';
	echo '<p>' . esc_html( 'Kits by Wugweb: the design system (master theme Kits by Wugweb with the site\'s child theme) and Kits Core, the site features every Kits site shares. Each row says where to find it.' ) . '</p>';

	echo '<h2>Design and branding</h2>';
	$palette = (array) ( wp_get_global_settings( array( 'color', 'palette', 'theme' ) ) ?? array() );
	$swatch  = implode(
		' ',
		array_map(
			fn( $c ) => '<span title="' . esc_attr( ( $c['name'] ?? '' ) . ' ' . ( $c['color'] ?? '' ) ) . '" style="display:inline-block;width:1.2em;height:1.2em;border:1px solid #c3c4c7;vertical-align:middle;background:' . esc_attr( (string) ( $c['color'] ?? '' ) ) . '"></span>',
			array_slice( $palette, 0, 20 )
		)
	);
	$fonts     = (array) ( wp_get_global_settings( array( 'typography', 'fontFamilies', 'theme' ) ) ?? array() );
	$templates = get_block_templates( array(), 'wp_template' );
	$parts     = get_block_templates( array(), 'wp_template_part' );
	$patterns  = array_filter( WP_Block_Patterns_Registry::get_instance()->get_all_registered(), fn( $p ) => array_intersect( (array) ( $p['categories'] ?? array() ), array( 'animal-writes', 'kits-components', 'kits-lists' ) ) );
	$variants  = class_exists( 'WP_Theme_JSON_Resolver' ) ? WP_Theme_JSON_Resolver::get_style_variations() : array();
	$registry  = function_exists( 'kits_registry' ) ? kits_registry() : array();
	$reg_items = is_array( $registry ) ? count( (array) ( $registry['items'] ?? array() ) ) : 0;
	kits_overview_table(
		'kits-design',
		array(
			array( 'Themes', esc_html( 'Active: ' . $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) . ( $parent ? ', a child of ' . $parent->get( 'Name' ) . ' ' . $parent->get( 'Version' ) : '' ) . '.' ), kits_link( 'Appearance → Themes', 'themes.php' ), $auto ),
			array( 'Colours', esc_html( count( $palette ) . ' brand colours from the theme.' ) . '<br>' . $swatch, kits_link( 'Editor → Styles', 'site-editor.php?path=/wp_global_styles' ) . ', ' . kits_link( 'Design system → Tokens', 'admin.php?page=kits-system&tab=tokens' ), esc_html( 'Contrast worked out in Design system → Contrast.' ) ),
			array( 'Fonts', esc_html( implode( ', ', array_filter( array_map( fn( $f ) => (string) ( $f['name'] ?? '' ), $fonts ) ) ) ), kits_link( 'Editor → Styles → Typography', 'site-editor.php?path=/wp_global_styles' ), $auto ),
			array( 'Templates', esc_html( count( $templates ) . ' page templates (home, item, Directory profile, hubs, category and tag pages, 404 …).' ), kits_link( 'Editor → Templates', 'site-editor.php?postType=wp_template' ), $auto ),
			array( 'Header, footer and sidebar', esc_html( count( $parts ) . ' template parts.' ), kits_link( 'Editor → Patterns → Template parts', 'site-editor.php?postType=wp_template_part' ), $auto ),
			array( 'Patterns', esc_html( count( $patterns ) . ' patterns (heroes, card grids, calls to action, FAQ, newsletter, campaign blocks …).' ), kits_link( 'Editor → Patterns', 'site-editor.php?path=/patterns' ), $auto ),
			array( 'Menus', esc_html( 'Header, footer and Explore menus.' ), kits_link( 'Editor → Navigation', 'site-editor.php?path=/navigation' ), $auto ),
			array( 'Style variations', esc_html( count( $variants ) . ' saved look(s) of the site.' ), kits_link( 'Editor → Styles', 'site-editor.php?path=/wp_global_styles' ), $auto ),
			array( 'Logo and site icon', esc_html( 'The site\'s logo in the header part; the site icon in the browser tab.' ), kits_link( 'Editor → Header', 'site-editor.php?postType=wp_template_part' ) . ', ' . kits_link( 'Settings → General', 'options-general.php' ), $auto ),
			array( 'Design registry', esc_html( $reg_items . ' components, blocks and patterns of the child theme (plus the master\'s) with their states, accessibility and SEO; classes without an entry fail the deploy check.' ), kits_link( 'Kits → Design system', 'admin.php?page=kits-system' ), $auto ),
			array( 'States sheet', esc_html( 'Every component and block in every state, drawn by the site\'s own styles (private page /kits-demo/).' ), kits_link( 'Design system → States sheet', 'admin.php?page=kits-system&tab=states' ), $auto ),
		)
	);

	echo '<h2>Site features (Kits Core)</h2>';
	kits_overview_table(
		'kits-features',
		array(
			array( 'Forms', esc_html( 'One field dictionary for every form, per-field errors, expandable and multi-step forms, a fresh key on cached pages.' ), esc_html( 'Every site form' ), $auto ),
			array( 'Spam guard (ALTCHA)', esc_html( 'Free proof-of-work check on every form: no third-party service, no cookies.' ), esc_html( 'Every site form' ), esc_html( '' !== (string) get_option( 'kits_altcha_key', '' ) ? 'Key set.' : 'Key made on first use.' ) ),
			array( 'Analytics', esc_html( 'Google Tag Manager with consent mode, Microsoft Clarity, the page context for GA4.' ), kits_link( 'Site Kit', 'admin.php?page=googlesitekit-dashboard' ), esc_html( ( $gtm ? 'Tag Manager ' . $gtm : 'No Tag Manager container' ) . ( $clarity ? '; Clarity ' . $clarity : '' ) . '.' ) ),
			array( 'Visit counter', esc_html( 'One count per browser session, bots left out, nothing personal stored; shown in the footer and the dashboard widget.' ), kits_link( 'Dashboard', 'index.php' ), esc_html( number_format_i18n( $visits ) . ' visits' . ( $since ? ' since ' . $since : '' ) . '.' ) ),
			array( 'Preview', esc_html( 'Preview-only items seen only by administrators, preview IP addresses and registered devices.' ), kits_link( 'Kits → Preview', 'admin.php?page=kits-preview' ), esc_html( $ips . ' IP address(es), ' . $devices . ' device(s).' ) ),
			array( 'Share bar', esc_html( 'Plain share links and copy link: no third-party scripts, no tracking.' ), esc_html( 'Item pages' ), $auto ),
			array( 'Source attribution', esc_html( 'Publisher favicon, name and time on News items (Google News pattern).' ), esc_html( 'News items' ), $auto ),
			array( 'Page structure', esc_html( 'One labelled <main>, sticky header, labelled sections, skip link.' ), esc_html( 'Every page' ), $auto ),
			array( 'Page speed', esc_html( 'Scripts at the end of the page, form styles only where a form is.' ), esc_html( 'Every page' ), $auto ),
			array( 'Slugs and image notes', esc_html( 'Every published item gets a slug; an editor-only note per image on the copy it supports.' ), kits_link( 'Media', 'upload.php' ), $auto ),
		)
	);
	echo '</div>';
}
