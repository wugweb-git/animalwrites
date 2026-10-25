<?php
/**
 * AW SEO Plus menu and its Overview screen (owner, 25 Oct 2026: one menu, organised like any other plugin).
 *
 * The menu: AW SEO Plus → Overview (this file), FAQ (faq.php), Search Insights (search-insights/admin.php, admins only),
 * Settings (settings.php: every module's rules and switches).
 * Overview lists every module of the plugin: what it does, where it is set up (here or in Rank Math), its status from
 * the checks that already run, the boxes it adds to edit screens, and its WP-CLI commands. Core WordPress only.
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
		add_menu_page( 'AW SEO Plus', 'AW SEO Plus', 'manage_categories', AW_SEO_MENU, 'aw_seo_overview_page', 'dashicons-chart-line', 81 );
		add_submenu_page( AW_SEO_MENU, 'AW SEO Plus', 'Overview', 'manage_categories', AW_SEO_MENU, 'aw_seo_overview_page' );
	},
	9 // Before FAQ (10) and Search Insights (100), so Overview is the first item.
);

// Plugins screen: Overview, FAQ and Search Insights under the plugin's name.
add_filter(
	'plugin_action_links_' . plugin_basename( AW_SEO_EXTRAS_DIR . 'aw-seo-extras.php' ),
	function ( $links ) {
		$own = array(
			'<a href="' . esc_url( add_query_arg( 'page', AW_SEO_MENU, admin_url( 'admin.php' ) ) ) . '">Overview</a>',
			'<a href="' . esc_url( add_query_arg( 'page', 'aw-faq', admin_url( 'admin.php' ) ) ) . '">FAQ</a>',
		);
		if ( function_exists( 'aw_si_url' ) && current_user_can( aw_si_cap() ) ) {
			$own[] = '<a href="' . esc_url( aw_si_url() ) . '">Search Insights</a>';
		}
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

/** Status of the FAQ: the holdout and waiting drafts (from the FAQ screen's hourly count, when there is one). */
function aw_seo_overview_faq_status(): string {
	$out = array();
	if ( function_exists( 'aw_faq_experiment' ) ) {
		$e     = aw_faq_experiment();
		$end   = wp_date( 'j M Y', strtotime( $e['start'] . ' +' . (int) $e['weeks'] . ' weeks' ) );
		$out[] = ! empty( $e['on'] ) ? sprintf( 'Holdout running (%d%%) until %s.', (int) $e['pct'], $end ) : 'Rolled out to every eligible page.';
	}
	$c = get_transient( 'aw_faq_counts' );
	if ( is_array( $c ) ) {
		$drafts = (int) ( $c['term']['drafts'] ?? 0 ) + (int) ( $c['hub']['drafts'] ?? 0 );
		$out[]  = $drafts ? sprintf( '%d draft questions waiting.', $drafts ) : 'No drafts waiting.';
	}
	return esc_html( implode( ' ', $out ) );
}

/** AW SEO Plus → Overview. */
function aw_seo_overview_page() {
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
			'what'   => 'Questions and answers with FAQPage schema on Directory listings, Library documents, organisation types, categories, hubs and Resources guides.',
			'where'  => aw_seo_overview_link( 'AW SEO Plus → FAQ', $faq ) . '; each item’s FAQ box',
			'status' => aw_seo_overview_faq_status(),
		),
	);
	if ( $si || ! aw_seo_module_on( 'search-insights' ) ) {
		$rows[] = ! $si ? array(
			'module' => '<strong>Search Insights</strong>',
			'mods'   => array( 'search-insights' ),
			'what'   => 'Keywords, Search Console and GA4 figures, rising terms, pages, the monthly check list and imports. Read-only.',
			'where'  => '',
			'status' => '',
		) : array(
			'module' => '<strong>' . aw_seo_overview_link( 'Search Insights', aw_si_url() ) . '</strong>',
			'mods'   => array( 'search-insights' ),
			'what'   => 'Keywords, Search Console and GA4 figures, rising terms, pages, the monthly check list and imports. Read-only.',
			'where'  => aw_seo_overview_link( 'AW SEO Plus → Search Insights → Settings', aw_si_url( 'settings' ) ),
			'status' => aw_seo_overview_si_status(),
		);
	}
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
		$row['where'] = ( $on && '' !== $row['where'] ? $row['where'] . '<br>' : '' ) . aw_seo_overview_link( 'Settings', aw_seo_settings_url( $mods[0] ) ); // an Off module's own screens are gone
		if ( ! $on ) {
			$row['status'] = '<span class="dashicons dashicons-marker" aria-hidden="true"></span> ' . esc_html__( 'Off (switch it on in Settings).', 'aw-seo-extras' );
		} elseif ( count( $on ) < count( $mods ) ) {
			$row['status'] .= ( '' !== $row['status'] ? ' ' : '' ) . esc_html( 'Partly off: ' . implode( ', ', array_map( fn( $k ) => aw_seo_modules()[ $k ]['label'], array_diff( $mods, $on ) ) ) . '.' );
		}
	}
	unset( $row );

	echo '<div class="wrap"><h1>AW SEO Plus</h1>';
	echo '<p>' . esc_html__( 'What the plugin adds to Rank Math (free) for animalwrites.in. Rank Math holds everything else; where a module’s settings live in Rank Math, the link goes there.', 'aw-seo-extras' ) . '</p>';
	echo '<h2>' . esc_html__( 'Modules', 'aw-seo-extras' ) . '</h2>';
	aw_seo_extras_simple_table(
		'aw-seo-modules',
		array(
			'module' => 'Module',
			'what'   => 'What it does',
			'where'  => 'Where to set it up',
			'status' => 'Status',
		),
		$rows,
		array( 'primary' => 'module' )
	);

	echo '<h2>' . esc_html__( 'On edit screens', 'aw-seo-extras' ) . '</h2>';
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
				'box'    => 'FAQ fields box: Short name, Hindi version link, Issued under, Key sections.',
			),
			array(
				'screen' => 'Organisation types, services, categories',
				'box'    => 'FAQ fields on the term screen.',
			),
		),
		array( 'primary' => 'screen' )
	);

	echo '<h2>' . esc_html__( 'Commands (WP-CLI on the server)', 'aw-seo-extras' ) . '</h2>';
	aw_seo_extras_simple_table(
		'aw-seo-commands',
		array(
			'cmd'  => 'Command',
			'what' => 'What it does',
		),
		array(
			array(
				'cmd'  => '<code>wp aw-faq</code>',
				'what' => 'report, check, holdout, import, convert-guides, undo-guide',
			),
			array(
				'cmd'  => '<code>wp aw-si</code>, <code>wp aw-si-rising</code>',
				'what' => 'Search Insights: status, dry-run, run, fetch, snapshot, rollup, delete-data; rising terms and spikes',
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
		array( 'primary' => 'cmd' )
	);
	echo '<p>' . esc_html__( 'Each command lists its options with --help.', 'aw-seo-extras' ) . '</p></div>';
}
