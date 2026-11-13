<?php
/**
 * RankWeb menu and its Overview screen (owner, 25 Oct 2026: one menu, organised like any other plugin).
 *
 * RankWeb opens the Dashboard (this file's page; drawn on the Kits shell, rankweb-ui.php); its tabs are Dashboard, FAQ
 * (faq.php) and Settings (settings.php: every module's rules and switches), each at its own admin.php?page= address.
 * aw_seo_overview_body() lists every module of the plugin: what it does, where it is set up (here or in Rank Math), its
 * status from the checks that already run, the boxes it adds to edit screens, and its WP-CLI commands; the Dashboard
 * shows it through Kits Core's page parts.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/admin-table.php';

const AW_SEO_MENU = 'aw-seo-plus';

add_action(
	'admin_menu',
	function () {
		// RankWeb's own top-level menu in the Wugweb block, opening the Dashboard; FAQ and
		// Settings are its tabs. Without Kits Core, a menu of its own (its screens then say they need Kits Core).
		// Screen ids of the screens under this menu start with its hook prefix; keep the old one (aw-seo-plus_page_…),
		// so saved screen options, box order and hidden columns stay with them.
		// The Wugweb block (owner, 12 Nov 2026): Kits, DirectoryWeb, SourceWeb, RankWeb, InsightWeb, MailWeb, PlanWeb at 58.1-58.7,
		// after one separator (58.05), before WordPress's second separator (59); the same with or without Kits Core.
		// The icon is the plugin's one-colour SVG as a data URI, which WordPress paints in the admin colour scheme.
		$icon = dirname( __DIR__ ) . '/assets/menu-icon.svg';
		$icon = is_readable( $icon ) ? 'data:image/svg+xml;base64,' . base64_encode( (string) file_get_contents( $icon ) ) : 'dashicons-search'; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the plugin's own small SVG file.
		add_menu_page( 'RankWeb', 'RankWeb', 'manage_categories', AW_SEO_MENU, 'aw_seo_overview_page', $icon, '58.4' );
		$GLOBALS['admin_page_hooks'][ AW_SEO_MENU ] = 'aw-seo-plus'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the screens' hook prefix (aw-seo-plus_page_…), whatever the title.
		if ( ! isset( $GLOBALS['menu']['58.05'] ) ) {
			$GLOBALS['menu']['58.05'] = array( '', 'read', 'separator-wugweb', '', 'wp-menu-separator', 'separator-wugweb' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- one separator above the block, as core adds its own.
		}
		add_submenu_page( AW_SEO_MENU, 'RankWeb', 'Overview', 'manage_categories', AW_SEO_MENU, 'aw_seo_overview_page' );
	},
	9 // Before FAQ (10), so Overview is the first item.
);

// Plugins screen: Overview and FAQ under the plugin's name (Search Insights is under InsightWeb's).
add_filter(
	'plugin_action_links_' . plugin_basename( AW_SEO_EXTRAS_DIR . 'aw-seo-extras.php' ),
	function ( $links ) {
		$own = array(
			'<a href="' . esc_url( add_query_arg( 'page', AW_SEO_MENU, admin_url( 'admin.php' ) ) ) . '">Overview</a>',
			'<a href="' . esc_url( add_query_arg( 'page', 'aw-faq', admin_url( 'admin.php' ) ) ) . '">FAQ</a>',
		);
		return array_merge( $own, (array) $links );
	}
);

/** A Rank Math screen's address. */
function aw_seo_rank_math_url( string $page ): string {
	return add_query_arg( 'page', $page, admin_url( 'admin.php' ) );
}

/** One "where to set it up" link. */
function aw_seo_overview_link( string $label, string $url ): string {
	return '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
}

/** Status of the sitemap and llms.txt from the hourly check (health.php). */
function aw_seo_overview_endpoints_status(): string {
	$last = (array) get_option( 'aw_seo_endpoints_last', array() );
	if ( empty( $last['at'] ) ) {
		return esc_html__( 'Not checked yet (hourly check).', 'aw-seo-extras' );
	}
	$when = wp_date( 'j M Y, g:i a', (int) $last['at'] );
	if ( ! empty( $last['problems'] ) ) {
		return '<span class="dashicons dashicons-warning" aria-hidden="true"></span> ' . esc_html( implode( ' ', (array) $last['problems'] ) . ' ' . sprintf( 'Checked %s.', $when ) );
	}
	return '<span class="dashicons dashicons-yes" aria-hidden="true"></span> ' . esc_html( sprintf( 'Both answer. Checked %s.', $when ) );
}

/** Status of Search Insights from its Site Health test. */
function aw_seo_overview_si_status(): string {
	if ( ! function_exists( 'aw_si_health_test' ) ) {
		return '';
	}
	$t = aw_si_health_test();
	return '<span class="dashicons dashicons-' . ( 'good' === $t['status'] ? 'yes' : 'warning' ) . '" aria-hidden="true"></span> ' . esc_html( $t['label'] );
}

/** Status of the FAQ: pages with one and drafts waiting (from the FAQ screen's hourly count, when there is one). */
function aw_seo_overview_faq_status(): string {
	$ix = get_transient( 'aw_faq_index' );
	if ( ! is_array( $ix ) || ! isset( $ix['counts'] ) ) {
		// The count is kept for an hour after RankWeb → FAQ builds it; rebuild it in the background, not on this view.
		if ( function_exists( 'aw_faq_index' ) && ! wp_next_scheduled( 'aw_faq_index_build' ) ) {
			wp_schedule_single_event( time(), 'aw_faq_index_build' );
		}
		return '';
	}
	$faq    = array_sum( wp_list_pluck( $ix['counts'], 'faq' ) );
	$drafts = array_sum( wp_list_pluck( $ix['counts'], 'drafts' ) );
	return esc_html( sprintf( '%s pages show an FAQ. %s', number_format_i18n( $faq ), $drafts ? sprintf( '%d draft questions waiting.', $drafts ) : 'No drafts waiting.' ) );
}

/** RankWeb → Dashboard (rankweb-ui.php). */
function aw_seo_overview_page() {
	rankweb_ui_page( 'dashboard' );
}

/**
 * The Overview: what RankWeb adds, Needs attention, the Modules table (where each is set up, Off state), On edit
 * screens and Commands. The Dashboard draws it through Kits' page parts (rankweb_overview_parts()).
 */
function aw_seo_overview_body() {
	$faq = add_query_arg( 'page', 'aw-faq', admin_url( 'admin.php' ) );
	$si  = function_exists( 'aw_si_url' ) && current_user_can( aw_si_cap() );
	$rm  = array(
		'titles'   => aw_seo_overview_link( 'Rank Math → Titles & Meta', aw_seo_rank_math_url( 'rank-math-options-titles' ) ),
		'sitemap'  => aw_seo_overview_link( 'Rank Math → Sitemap Settings', aw_seo_rank_math_url( 'rank-math-options-sitemap' ) ),
		'general'  => aw_seo_overview_link( 'Rank Math → General Settings', aw_seo_rank_math_url( 'rank-math-options-general' ) ),
		'redirect' => aw_seo_overview_link( 'Rank Math → Redirections', aw_seo_rank_math_url( 'rank-math-redirections' ) ),
	);
	$auto = esc_html__( 'Automatic', 'aw-seo-extras' );

	$rows = array(
		array(
			'module' => '<strong>' . ( function_exists( 'aw_faq_admin_page' ) ? aw_seo_overview_link( 'FAQ', $faq ) : 'FAQ' ) . '</strong>',
			'mods'   => array( 'faq' ),
			'what'   => 'The FAQ box on every edit screen (pages, posts, every public type and taxonomy; automatic questions filled in where the page has data) and the questions with FAQPage schema on the page.',
			'where'  => aw_seo_overview_link( 'Plugins → RankWeb → FAQ', $faq ) . '; each item’s FAQ box',
			'status' => aw_seo_overview_faq_status(),
		),
	);
	// Search Insights is InsightWeb's since 11 Nov 2026: one row, the same link, no RankWeb setting.
	$rows[] = array(
		'module' => '<strong>' . ( $si ? aw_seo_overview_link( 'Search Insights', aw_si_url() ) : 'Search Insights' ) . '</strong>',
		'what'   => 'Search Insights is now its own plugin, InsightWeb.',
		'where'  => '',
		'status' => $si ? aw_seo_overview_si_status() : '',
	);
	$rows = array_merge(
		$rows,
		array(
			array(
				'module' => '<strong>Titles and descriptions</strong>',
			'mods'   => array( 'titles' ),
				'what'   => 'Search titles and descriptions of list pages (hubs, Directory places, filtered lists, categories, tags) and one line of facts on page 1, from their own counts and places.',
				'where'  => $rm['titles'] . ' for single items',
				'status' => $auto,
			),
			array(
				'module' => '<strong>Canonicals, robots and filtered lists</strong>',
			'mods'   => array( 'canonical', 'robots', 'filtered' ),
				'what'   => 'Canonicals Rank Math cannot work out, noindex rules that depend on an item’s state or a list’s size, and filtered hub lists as pages of their own from 3 items.',
				'where'  => $rm['titles'] . ' and each item’s Advanced tab for fixed rules',
				'status' => $auto,
			),
			array(
				'module' => '<strong>Schema</strong>',
			'mods'   => array( 'schema', 'rank-math-schema' ),
				'what'   => 'The site’s own JSON-LD nodes in Rank Math’s graph, and matching Rank Math schema entries so its screens show the schema the page really has.',
				'where'  => 'Each item’s Rank Math Schema tab (read-only for these types)',
				'status' => $auto,
			),
			array(
				'module' => '<strong>Sitemap and llms.txt</strong>',
			'mods'   => array( 'sitemap', 'llms', 'health' ),
				'what'   => 'The Directory sitemap, llms.txt order, descriptions and Directory section; an hourly check that both answer (emails the admin if not).',
				'where'  => $rm['sitemap'] . '; ' . $rm['general'] . ' → Edit llms.txt',
				'status' => aw_seo_overview_endpoints_status(),
			),
			array(
				'module' => '<strong>Share images and Open Graph</strong>',
			'mods'   => array( 'opengraph' ),
				'what'   => 'Share images and texts Rank Math’s settings cannot express; a choice made in Rank Math always wins.',
				'where'  => 'Each item’s Rank Math Social tab',
				'status' => $auto,
			),
			array(
				'module' => '<strong>Breadcrumbs</strong>',
			'mods'   => array( 'breadcrumbs' ),
				'what'   => 'The breadcrumb trail for places and hubs, used by Rank Math and the site’s breadcrumb block.',
				'where'  => $rm['general'] . ' → Breadcrumbs',
				'status' => $auto,
			),
			array(
				'module' => '<strong>Redirects</strong>',
			'mods'   => array( 'redirects' ),
				'what'   => 'Clean-up redirects and author archives; forms, merges and imports add to Rank Math’s one Redirections list.',
				'where'  => $rm['redirect'],
				'status' => $auto,
			),
			array(
				'module' => '<strong>Focus keywords</strong>',
			'mods'   => array( 'focus-keywords' ),
				'what'   => 'Listings get their name as focus keyword when published; other items get one from the workers’ focus-keyword job through the site’s API.',
				'where'  => 'Each item’s Rank Math box',
				'status' => $auto,
			),
			array(
				'module' => '<strong>Content analysis</strong>',
			'mods'   => array( 'analysis' ),
				'what'   => 'Rank Math’s analyzer reads the published page for Pages built from blocks and patterns, so their score counts what visitors see.',
				'where'  => 'Rank Math box in the editor',
				'status' => $auto,
			),
		)
	);

	// Each row: its Settings tab, and Off when its modules are switched off.
	foreach ( $rows as &$row ) {
		$mods = (array) ( $row['mods'] ?? array() );
		unset( $row['mods'] );
		if ( ! $mods || ! current_user_can( 'manage_options' ) ) {
			continue;
		}
		$on           = array_filter( $mods, 'aw_seo_module_on' );
		$row['where'] = ( $on && '' !== $row['where'] ? $row['where'] . '<br>' : '' ) . aw_seo_overview_link( 'Settings', aw_seo_settings_url( $on ? $mods[0] : 'modules' ) ); // Off: the switch is on Modules // an Off module's own screens are gone
		if ( ! $on ) {
			$row['status'] = '<span class="dashicons dashicons-marker" aria-hidden="true"></span> ' . esc_html__( 'Off (switch it on in Settings).', 'aw-seo-extras' );
		} elseif ( count( $on ) < count( $mods ) ) {
			$row['status'] .= ( '' !== $row['status'] ? ' ' : '' ) . esc_html( 'Partly off: ' . implode( ', ', array_map( fn( $k ) => aw_seo_modules()[ $k ]['label'], array_diff( $mods, $on ) ) ) . '.' );
		}
	}
	unset( $row );

	// Three cells per module: the module with where to set it up under it (as a list table's row links), what it
	// does, and its status. Rows whose status is a warning are also listed first, together, in a core notice.
	$cells = array();
	$attn  = array();
	foreach ( $rows as $row ) {
		$cells[] = array(
			'module' => $row['module'] . ( '' !== $row['where'] ? '<div class="row-actions visible">' . $row['where'] . '</div>' : '' ),
			'what'   => esc_html( $row['what'] ),
			'status' => $row['status'],
		);
		if ( false !== strpos( (string) $row['status'], 'dashicons-warning' ) ) {
			$attn[] = '<li>' . $row['module'] . ': ' . esc_html( trim( wp_strip_all_tags( (string) $row['status'] ) ) ) . '</li>';
		}
	}

	echo '<p>' . esc_html__( 'What the plugin adds to Rank Math (free) for animalwrites.in. Rank Math holds everything else; where a module’s settings live in Rank Math, the link goes there.', 'aw-seo-extras' ) . '</p>';
	if ( $attn ) {
		wp_admin_notice( '<p><strong>' . esc_html__( 'Needs attention', 'aw-seo-extras' ) . '</strong></p><ul class="ul-disc">' . implode( '', $attn ) . '</ul>', array( 'type' => 'warning', 'additional_classes' => array( 'inline' ), 'paragraph_wrap' => false ) );
	}
	echo '<h2>' . esc_html__( 'Modules', 'aw-seo-extras' ) . '</h2>';
	aw_seo_extras_simple_table(
		'aw-seo-modules',
		array(
			'module' => 'Module',
			'what'   => 'What it does',
			'status' => 'Status',
		),
		$cells,
		array(
			'primary' => 'module',
			'foot'    => false,
		)
	);

	echo '<details><summary><strong>' . esc_html__( 'On edit screens', 'aw-seo-extras' ) . '</strong> (3)</summary>';
	aw_seo_extras_simple_table(
		'aw-seo-boxes',
		array(
			'screen' => 'Screen',
			'box'    => 'What you edit there',
		),
		array(
			array(
				'screen' => 'Posts and Pages (Resources guides, hub Pages)',
				'box'    => 'FAQ box: the page’s own questions (draft or approved).',
			),
			array(
				'screen' => 'Library documents',
				'box'    => 'FAQ box: the questions. Library item box: Short name, Issued under, Key sections (document fields the questions use).',
			),
			array(
				'screen' => 'Tags, categories, organisation types, services',
				'box'    => 'FAQ box on the term screen: automatic questions (tags) and the term’s own questions.',
			),
		),
		array(
			'primary' => 'screen',
			'foot'    => false,
		)
	);
	echo '</details>';

	echo '<details><summary><strong>' . esc_html__( 'Commands (WP-CLI on the server)', 'aw-seo-extras' ) . '</strong> (4)</summary>';
	aw_seo_extras_simple_table(
		'aw-seo-commands',
		array(
			'cmd'  => 'Command',
			'what' => 'What it does',
		),
		array(
			array(
				'cmd'  => '<code>wp aw-faq</code>',
				'what' => 'report, check, import, convert-guides, undo-guide',
			),
			array(
				'cmd'  => '<code>wp aw-seo rank-math-schema</code>',
				'what' => 'Write Rank Math’s schema entries for published items (--dry-run, --type)',
			),
			array(
				'cmd'  => '<code>wp aw-seo share-jpegs</code>',
				'what' => 'Make JPEG copies of pictures for share images',
			),
			array(
				'cmd'  => '<code>wp aw redirects-list</code>',
				'what' => 'List the active redirects in Rank Math’s Redirections',
			),
		),
		array(
			'primary' => 'cmd',
			'foot'    => false,
		)
	);
	echo '<p>' . esc_html__( 'Each command lists its options with --help.', 'aw-seo-extras' ) . '</p></details>';
}
