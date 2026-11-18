<?php
/**
 * The site's rows on the Kits screens (moved from Animal Writes Core's admin-home.php, 18 Nov 2026; owner, 25 Oct,
 * 10 Nov and 11 Nov 2026; docs/admin-ui-spec.md §B, §C, §E3). There is no site tab or menu item: the site's parts join
 * the Kits screens through Kits' own filters.
 * - Home: Page check, Deployed commit, Joined files, Email sending (each only when the part that prints it is loaded;
 *   Thumbnails and Pictures are added by Kits Core's thumbs.php and SourceWeb's cc0-images.php themselves); Dates is linked from Home and keeps its address (aw-dates) until PlanWeb has it.
 * - Settings: CDN cache times (Performance), and where keys and the optional parts are set.
 * - Theme Options → Socials: Contact links. Help: the site's Overview (Docs, from the kits_site_overview filter),
 *   Edit screens and Theme parts (Developer).
 * - The site's screens that keep their own address (kits_site_screens: Dates, Keys, Modules …) sit after every Kits
 *   screen, in that order, and light the Kits tab they are linked from.
 * The old addresses (animal-writes, aw-site-check, aw-elsewhere, aw-edit-screens, aw-theme-parts) redirect there.
 * Core WordPress only.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The menu the site's screens join: Kits. */
function kits_site_menu_parent(): string {
	return KITS_MENU;
}

/**
 * The site's screens in menu order: slug => [label, capability]. Dates leaves once PlanWeb is on (11 Nov 2026).
 * The first one is the Overview's old address (admin.php?page=animal-writes, now Kits → Help → Docs).
 *
 * @param bool $all Include Dates even when PlanWeb has it, so menu positions stay fixed.
 */
function kits_site_screens( bool $all = false ): array {
	$screens = array(
		'animal-writes'   => array( 'Overview', 'edit_posts' ),
		'aw-dates'        => array( 'Dates', defined( 'AW_DATES_CAP' ) ? AW_DATES_CAP : 'edit_others_posts' ),
		'aw-site-check'   => array( 'Check site', 'manage_options' ),
		'aw-elsewhere'    => array( 'Contact links', 'edit_pages' ),
		'aw-modules'      => array( 'Modules', 'manage_options' ),
		'aw-edit-screens' => array( 'Edit screens', 'manage_options' ),
	);
	if ( ! $all && function_exists( 'aw_team_dates_moved' ) && aw_team_dates_moved() ) {
		unset( $screens['aw-dates'] );
	}
	/**
	 * Filters the site's screens that keep their own address.
	 *
	 * @param array $screens Slug => [label, capability], in menu order.
	 * @param bool  $all     Whether screens that left (Dates under PlanWeb) are included.
	 */
	return (array) apply_filters( 'kits_site_screens', $screens, $all );
}

/**
 * Menu position of a screen among the site's screens: after every Kits screen (those register at 8 to 11).
 *
 * @param string $slug Screen slug.
 */
function kits_site_menu_priority( string $slug ): int {
	$i = array_search( $slug, array_keys( kits_site_screens( true ) ), true );
	return 20 + ( false === $i ? 9 : (int) $i );
}

/**
 * A screen's capability, from kits_site_screens().
 *
 * @param string $slug Screen slug (a key of kits_site_screens()).
 */
function kits_site_screen_cap( string $slug ): string {
	return kits_site_screens()[ $slug ][1] ?? 'manage_options';
}

/**
 * Under a screen's heading: the Kits tabs (Dates lights Home; Modules lights Settings).
 *
 * @param string $current The screen's slug.
 */
function kits_site_screen_nav( string $current ): void {
	kits_admin_tabs( $current );
}

// Kits → Home → Versions and limits: scheduled tasks.
add_filter(
	'kits_status_rows',
	function ( $rows ) {
		$st     = kits_site_status( false );
		$rows[] = array( __( 'Scheduled tasks', 'kits-core' ), $st['cron'] );
		return $rows;
	}
);

// Old addresses (docs/admin-ui-spec.md §E1). Dates and Modules keep theirs; Keys redirects to InsightWeb's own Keys.
add_filter(
	'kits_admin_redirects',
	fn( $map ) => array_merge(
		(array) $map,
		array(
			'animal-writes'   => KITS_MENU,
			'aw-site-check'   => KITS_MENU . '#page-check',
			'aw-elsewhere'    => 'kits-options#socials',
			'aw-edit-screens' => 'kits-help#developer/edit-screens',
			'aw-keys'         => defined( 'INSIGHTWEB_LOADED' ) ? 'aw-search-insights#keys' : 'kits-settings', // the shared Keys screen is retired (19 Nov 2026): each plugin keeps its own keys on its own Settings
			'aw-theme-parts'  => 'kits-help#developer/theme-parts',
			'aw-core'         => KITS_MENU, // 12 Nov 2026: these two answered 403
			'aw-cron-health'  => 'index.php', // the Scheduled tasks box is on the Dashboard
		)
	)
);
// The screens that keep their addresses light the tab they are linked from.
add_filter(
	'kits_screen_tabs',
	fn( $tabs ) => array_merge(
		(array) $tabs,
		array(
			'aw-dates'   => KITS_MENU,
			'aw-modules' => 'kits-settings',
		)
	)
);
add_filter( 'kits_home_sections', 'kits_site_home_sections' );
add_filter( 'kits_settings_sections', 'kits_site_settings_sections' );
add_filter( 'kits_help_sections', 'kits_site_help_sections' );
// Theme Options → Socials: the Contact page's "Elsewhere" links (its own list until the one socials list, §D1.10).
add_action(
	'kits_options_legacy_section',
	function ( $slug ) {
		if ( 'socials' === $slug && function_exists( 'aw_elsewhere_screen' ) && current_user_can( kits_site_screen_cap( 'aw-elsewhere' ) ) ) {
			echo '<h3>' . esc_html( 'Contact links' ) . '</h3>';
			kits_admin_embed( 'aw_elsewhere_screen' );
		}
	}
);

/**
 * A Kits section from a function that prints it.
 *
 * @param string   $title  Heading.
 * @param callable $render Prints it.
 * @param int      $order  Place among the screen's sections (Kits' own: 10, 20, …).
 * @param string   $cap    Who sees it.
 */
function kits_site_section( string $title, callable $render, int $order, string $cap = 'manage_options' ): array {
	return array(
		'title'  => $title,
		'render' => $render,
		'cap'    => $cap,
		'order'  => $order,
	);
}

/**
 * Adds a section whose render function another part defines, only when that part is loaded.
 *
 * @param array  $sections The screen's sections.
 * @param string $id       Section id.
 * @param string $title    Heading.
 * @param string $render   Name of the function that prints it.
 * @param int    $order    Place among the screen's sections.
 */
function kits_site_section_if( array $sections, string $id, string $title, string $render, int $order ): array {
	if ( function_exists( $render ) ) {
		$sections[ $id ] = kits_site_section( $title, $render, $order );
	}
	return $sections;
}

/**
 * Kits → Home: the site's checks (docs/admin-ui-spec.md §B1, §D6). Email stays here until the MailWeb dashboard has it
 * (owner, 11 Nov 2026); Thumbnails and Pictures are added by thumbs.php (Kits Core) and cc0-images.php (SourceWeb). Nothing WordPress or another plugin already shows
 * (owner, 11 Nov 2026): backups are UpdraftPlus's, the theme is Appearance → Themes, dates are PlanWeb's.
 *
 * @param array $sections Kits' sections.
 */
function kits_site_home_sections( $sections ) {
	$sections           = kits_site_section_if( (array) $sections, 'page-check', 'Page check', 'kits_site_check_results', 20 );
	$sections['deploy'] = kits_site_section(
		'Deployed commit',
		function () {
			$st = kits_site_status();
			echo '<p>' . $st['deploy'] . ' ' . $st['cdn'] . ' ' . $st['stage'] . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kits_site_status() escapes.
		},
		40
	);
	$sections           = kits_site_section_if( $sections, 'joined-files', 'Joined files', 'kits_site_check_joined', 50 );
	return kits_site_section_if( $sections, 'email', 'Email sending', 'aw_mail_site_check', 70 );
}

/**
 * Kits → Settings: CDN cache times (Performance), and where the service keys and the optional parts are set now
 * (RankWeb → Settings and SourceWeb → Settings).
 *
 * @param array $sections Kits' sections.
 */
function kits_site_settings_sections( $sections ) {
	$sections                       = kits_site_section_if( (array) $sections, 'cdn', 'CDN cache times', 'kits_cdn_settings_box', 15 );
	$sections['connected-services'] = kits_site_section(
		'Connected services and modules',
		function () {
			echo '<ul>';
			echo '<li>' . kits_site_link( 'InsightWeb → Settings → Keys', 'admin.php?page=aw-search-insights&tab=settings' ) . ': ' . esc_html( 'the service keys Search Insights uses (Clarity, Bing Webmaster, PageSpeed, Google Alerts). Each plugin keeps its own keys.' ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kits_site_link() escapes.
			echo '<li>' . kits_site_link( 'Modules', 'admin.php?page=aw-modules' ) . ': ' . esc_html( 'optional parts of the site (Social posts, the PlanWeb calendar), emails from forms, sample listings, district helplines and issuer short names.' ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kits_site_link() escapes.
			echo '<li>' . kits_site_link( 'RankWeb → Settings', 'admin.php?page=aw-seo-settings' ) . ': ' . esc_html( 'connected services for search.' ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kits_site_link() escapes.
			echo '<li>' . kits_site_link( 'SourceWeb → Settings', 'admin.php?page=aw-sourcing&tab=settings' ) . ': ' . esc_html( 'Social posts, sourcing lists and Directory enrichment.' ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kits_site_link() escapes.
			echo '</ul>';
		},
		40
	);
	return $sections;
}

/**
 * Kits → Help: the site's Overview (Docs), Edit screens and Theme parts (Developer).
 *
 * @param array $sections Kits' sections.
 */
function kits_site_help_sections( $sections ) {
	$sections = (array) $sections;
	$overview = kits_site_overview();
	if ( $overview ) {
		$sections[ 'docs/' . $overview['slug'] ] = kits_site_section( 'Docs: ' . $overview['title'], fn() => kits_admin_embed( 'kits_site_overview_page' ), 15 );
	}
	$sections['developer/edit-screens'] = kits_site_section( 'Developer: edit screens', fn() => kits_admin_embed( 'kits_edit_screens_page' ), 30 );
	if ( function_exists( 'kits_site_theme_parts_screen' ) ) {
		$sections['developer/theme-parts'] = kits_site_section( 'Developer: theme parts', fn() => kits_admin_embed( 'kits_site_theme_parts_screen' ), 31 );
	}
	return $sections;
}

/**
 * Where a site screen is now, relative to wp-admin: its place in the Kits screens.
 *
 * @param string $slug aw-site-check, aw-elsewhere, aw-edit-screens or cdn.
 */
function kits_site_screen_url( string $slug ): string {
	$map = array(
		'aw-site-check'   => 'admin.php?page=' . KITS_MENU . '#page-check',
		'aw-elsewhere'    => 'admin.php?page=kits-options#socials',
		'aw-edit-screens' => 'admin.php?page=kits-help#developer/edit-screens',
		'cdn'             => 'admin.php?page=kits-settings#cdn',
	);
	return $map[ $slug ] ?? 'admin.php?page=' . KITS_MENU;
}

/**
 * A link for the Overview tables.
 *
 * @param string $label Link text.
 * @param string $path  Path relative to wp-admin.
 */
function kits_site_link( string $label, string $path ): string {
	return '<a href="' . esc_url( admin_url( $path ) ) . '">' . esc_html( $label ) . '</a>';
}

/**
 * One status cell: a dashicon (yes / warning) and a short text.
 *
 * @param bool   $ok   Fine or a warning.
 * @param string $text Text.
 */
function kits_site_status_cell( bool $ok, string $text ): string {
	return '<span class="dashicons dashicons-' . ( $ok ? 'yes' : 'warning' ) . '" aria-hidden="true"></span> ' . esc_html( $text );
}

/**
 * Status lines from the checks that already run.
 *
 * @param bool $full False: only backup, check, cron and deploy (Kits → Home).
 */
function kits_site_status( bool $full = true ): array {
	$s           = array();
	$b           = get_option( 'aw_backup_last' );
	$s['backup'] = is_array( $b ) && ! empty( $b['at'] )
		? kits_site_status_cell( isset( $b['db'], $b['code'] ) && strtotime( (string) $b['at'] ) > time() - 2 * DAY_IN_SECONDS, 'Last backup ' . $b['at'] . '.' )
		: kits_site_status_cell( false, 'No backup recorded yet.' );
	$c           = function_exists( 'kits_site_check_summary' ) ? kits_site_check_summary() : array(
		'ok'   => false,
		'text' => 'Not run yet.',
	); // Result, time and commit of the last check (site-check.php).
	$s['check']  = 'Not run yet.' === $c['text'] ? esc_html( $c['text'] ) : kits_site_status_cell( (bool) $c['ok'], (string) $c['text'] );
	$last        = function_exists( 'kits_cron_last' ) ? (int) kits_cron_last() : 0;
	$s['cron']   = $last ? kits_site_status_cell( $last > time() - HOUR_IN_SECONDS, 'Last run ' . wp_date( 'j M Y, g:i a', $last ) . '.' ) : esc_html( 'No run recorded.' );
	$head        = (string) get_option( 'aw_deployed_head', '' );
	$s['deploy'] = $head ? esc_html( 'Deployed commit ' . substr( $head, 0, 7 ) . '; cache purged once for it.' ) : esc_html( 'No deploy recorded.' );
	if ( ! $full ) {
		return $s;
	}
	$ttl        = (int) get_option( 'aw_cdn_ttl', 0 ); // kits_cdn_ttl() is 0 in wp-admin (no header there), so the option itself
	$ttl        = $ttl > 0 ? max( 60, min( 3600, $ttl ) ) : 0;
	$long       = (int) get_option( 'aw_cdn_ttl_long', 3600 );
	$s['cdn']   = esc_html( $ttl ? 'CDN keeps public pages ' . $ttl . ' s' . ( $long > $ttl ? '; listings, Library items and Resources ' . min( 3600, $long ) . ' s.' : '.' ) : 'CDN caching off.' );
	$s['stage'] = esc_html( function_exists( 'kits_site_is_staging' ) && kits_site_is_staging() ? 'This is the staging site.' : 'Live site.' );
	$next       = '';
	if ( function_exists( 'aw_team_dates_moved' ) && aw_team_dates_moved() && function_exists( 'planweb_next' ) ) {
		$next = planweb_next();
		$next = $next ? $next : '';
	} elseif ( function_exists( 'aw_team_dates' ) ) {
		$today = wp_date( 'Y-m-d' );
		foreach ( (array) aw_team_dates() as $d ) {
			if ( ! empty( $d['date'] ) && $d['date'] >= $today && ( '' === $next || $d['date'] < $next[0] ) ) {
				$next = array( $d['date'], (string) ( $d['title'] ?? '' ) );
			}
		}
	}
	$label       = $next && function_exists( 'aw_team_date_label' ) ? aw_team_date_label( $next[0] ) : ( $next ? (string) $next[0] : '' );
	$s['dates']  = esc_html( $next ? 'Next: ' . $label . ( $next[1] ? ', ' . $next[1] : '' ) . '.' : 'Nothing coming up.' );
	$s['review'] = function_exists( 'aw_review_count' ) ? esc_html( (int) aw_review_count() . ' items waiting.' ) : esc_html( 'SourceWeb is off.' );
	return $s;
}

/**
 * The site's Overview (Kits → Help → Docs), from the kits_site_overview filter; empty when the site gives none.
 *
 * @return array{slug:string,title:string,intro:string,groups:array,widgets:array,commands:array}|array{}
 */
function kits_site_overview(): array {
	/**
	 * Filters the site's Overview: slug, title, intro, groups (title => rows [feature, what, where HTML, status HTML]),
	 * widgets (rows widget, what) and commands (rows cmd HTML, what; WP-CLI's own commands are added from
	 * kits_cli_commands(), so a site lists here only what WP-CLI cannot report).
	 *
	 * @param array $overview Empty: no Overview.
	 */
	$o = (array) apply_filters( 'kits_site_overview', array() );
	if ( empty( $o['groups'] ) ) {
		return array();
	}
	return array(
		'slug'     => sanitize_title( (string) ( $o['slug'] ?? 'site' ) ),
		'title'    => (string) ( $o['title'] ?? get_bloginfo( 'name' ) ),
		'intro'    => (string) ( $o['intro'] ?? '' ),
		'groups'   => (array) $o['groups'],
		'widgets'  => (array) ( $o['widgets'] ?? array() ),
		'commands' => (array) ( $o['commands'] ?? array() ),
	);
}

/** The Overview: Kits → Help → Docs. */
function kits_site_overview_page() {
	$o = kits_site_overview();
	if ( ! $o ) {
		return;
	}
	echo '<div class="wrap"><h1>' . esc_html( $o['title'] ) . '</h1>';
	kits_site_screen_nav( 'animal-writes' );
	if ( '' !== $o['intro'] ) {
		echo '<p>' . esc_html( $o['intro'] ) . '</p>';
	}
	kits_site_overview_attention( $o['groups'] );
	$opts = array(
		'primary' => 'feature',
		'foot'    => false,
	);
	foreach ( $o['groups'] as $title => $rows ) {
		echo '<h2>' . esc_html( (string) $title ) . '</h2>';
		kits_simple_table(
			'aw-overview',
			array(
				'feature' => 'Feature',
				'what'    => 'What it does',
				'status'  => 'Status',
			),
			kits_site_overview_cells( (array) $rows ),
			$opts
		);
	}

	if ( $o['widgets'] ) {
		echo '<details><summary><strong>On the dashboard</strong> (' . count( $o['widgets'] ) . ')</summary>';
		kits_simple_table(
			'aw-widgets',
			array(
				'widget' => 'Widget',
				'what'   => 'What it shows',
			),
			$o['widgets'],
			array(
				'primary' => 'widget',
				'foot'    => false,
			)
		);
		echo '</details>';
	}

	// Read from WP-CLI's registry at the last wp run (cli-commands.php); a site's own rows (commands) first.
	foreach ( kits_cli_commands() as $c ) {
		$o['commands'][] = array(
			'cmd'  => '<code>' . esc_html( 'wp ' . (string) ( $c['name'] ?? '' ) ) . '</code>',
			'what' => esc_html( (string) ( $c['what'] ?? '' ) ),
		);
	}
	if ( ! $o['commands'] ) {
		echo '<p>' . esc_html( 'Commands (WP-CLI on the server): listed after the next wp command runs there (any command, for example wp help).' ) . '</p>';
	}
	if ( $o['commands'] ) {
		echo '<details><summary><strong>Commands (WP-CLI on the server)</strong> (' . count( $o['commands'] ) . ')</summary>';
		kits_simple_table(
			'aw-commands',
			array(
				'cmd'  => 'Command',
				'what' => 'What it does',
			),
			$o['commands'],
			array(
				'primary' => 'cmd',
				'foot'    => false,
			)
		);
		echo '<p>Each command lists its options with --help.</p></details>';
	}
	echo '</div>';
}

/**
 * Overview rows [feature, what, where, status] as three cells: the feature with its place under it (as a list
 * table's row links), what it does, and its status.
 *
 * @param array $rows Rows.
 */
function kits_site_overview_cells( array $rows ): array {
	$cells = array();
	foreach ( $rows as $r ) {
		$cells[] = array(
			'feature' => '<strong>' . esc_html( $r[0] ) . '</strong>' . ( '' !== $r[2] && '—' !== $r[2] ? '<div class="row-actions visible">' . $r[2] . '</div>' : '' ),
			'what'    => esc_html( $r[1] ),
			'status'  => $r[3],
		);
	}
	return $cells;
}

/**
 * Rows whose status is a warning, first and together, in a core notice; nothing when every check is fine.
 *
 * @param array $groups Group title => rows [feature, what, where, status].
 */
function kits_site_overview_attention( array $groups ): void {
	$items = array();
	foreach ( $groups as $rows ) {
		foreach ( (array) $rows as $r ) {
			// Check site is on Kits → Home → Page check already; not repeated here.
			if ( 'Check site' !== $r[0] && false !== strpos( (string) $r[3], 'dashicons-warning' ) ) {
				$items[] = '<li><strong>' . esc_html( $r[0] ) . '</strong>: ' . esc_html( trim( wp_strip_all_tags( (string) $r[3] ) ) ) . ( '' !== $r[2] && '—' !== $r[2] ? ' (' . $r[2] . ')' : '' ) . '</li>';
			}
		}
	}
	if ( $items ) {
		wp_admin_notice(
			'<p><strong>Needs attention</strong></p><ul class="ul-disc">' . implode( '', $items ) . '</ul>',
			array(
				'type'               => 'warning',
				'additional_classes' => array( 'inline' ),
				'paragraph_wrap'     => false,
			)
		);
	}
}
