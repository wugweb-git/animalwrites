<?php
/**
 * The site's own screens (owner, 25 Oct and 10 Nov 2026: our plugins work like any marketplace plugin: an overview of
 * every feature and where it lives, links from the Plugins screen).
 *
 * With Kits Core (docs/admin-ui-spec.md §B, §C, §E3; owner, 11 Nov 2026) there is no "Animal Writes" tab or menu item:
 * the site adds rows and sections to the six Kits screens through Kits' filters (kits_admin_hooks() below):
 * - Home: Page check, Backup, Deployed commit, Joined files, and until the MailWeb and SourceWeb dashboards have them,
 *   Email sending, Thumbnails and Pictures; Dates is linked from Home and keeps its address (aw-dates) until PlanWeb.
 * - Settings: CDN cache times (Performance); Keys and Modules are linked from Settings and keep their addresses
 *   (aw-keys, aw-modules) until the RankWeb and SourceWeb screens exist.
 * - Theme Options → Socials: Contact links. Help: the Overview below (Docs) and Edit screens and Theme parts
 *   (Developer).
 * The old addresses (animal-writes, aw-site-check, aw-elsewhere, aw-edit-screens, aw-theme-parts) redirect there.
 * Without Kits Core the screens are Plugins → Animal Writes (one item, the Overview), linked from each other.
 * Content menus (Directory, News, Resources, Library, Opportunities, Campaigns, Press coverage), Classification and
 * Sourcing stay where the content is. Core WordPress only.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_CORE_MENU = 'animal-writes'; // the Overview's slug: admin.php?page=animal-writes, as before.

/** The menu the site's screens join: Kits when Kits Core is loaded, else the plugin's own menu. */
function aw_core_menu_parent(): string {
	return defined( 'KITS_MENU' ) && function_exists( 'kits_admin_tabs' ) ? KITS_MENU : AW_CORE_MENU;
}

/**
 * The site's screens in menu order: slug => [label, capability]. Dates leaves once PlanWeb is on (11 Nov 2026).
 *
 * @param bool $all Include Dates even when PlanWeb has it, so menu positions stay fixed.
 */
function aw_core_screens( bool $all = false ): array {
	$screens = array(
		AW_CORE_MENU      => array( 'Overview', 'edit_posts' ),
		'aw-dates'        => array( 'Dates', defined( 'AW_DATES_CAP' ) ? AW_DATES_CAP : 'edit_others_posts' ),
		'aw-site-check'   => array( 'Check site', 'manage_options' ),
		'aw-elsewhere'    => array( 'Contact links', 'edit_pages' ),
		'aw-keys'         => array( 'Keys', 'manage_options' ),
		'aw-modules'      => array( 'Modules', 'manage_options' ),
		'aw-edit-screens' => array( 'Edit screens', 'manage_options' ),
	);
	if ( ! $all && function_exists( 'aw_team_dates_moved' ) && aw_team_dates_moved() ) {
		unset( $screens['aw-dates'] );
	}
	return $screens;
}

/**
 * Menu position of a screen among the site's screens: after every Kits screen (those register at 8 to 11).
 *
 * @param string $slug Screen slug.
 */
function aw_core_menu_priority( string $slug ): int {
	$i = array_search( $slug, array_keys( aw_core_screens( true ) ), true );
	return 20 + ( false === $i ? 9 : (int) $i );
}

/**
 * A screen's capability, from aw_core_screens().
 *
 * @param string $slug Screen slug (a key of aw_core_screens()).
 */
function aw_core_screen_cap( string $slug ): string {
	return aw_core_screens()[ $slug ][1] ?? 'manage_options';
}

// Only without Kits Core: Plugins → Animal Writes, opening the Overview (owner, 11 Nov 2026: "all plugins" under
// Plugins). The other screens stay under the AW_CORE_MENU slug, which WordPress does not list, at their addresses
// and with their hook names (toplevel_page_animal-writes, animal-writes_page_…); aw_core_screen_nav() links them.
add_action(
	'admin_menu',
	function () {
		if ( AW_CORE_MENU === aw_core_menu_parent() ) {
			$GLOBALS['admin_page_hooks'][ AW_CORE_MENU ] = 'animal-writes'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the hook names the top-level menu gave.
			add_submenu_page( 'plugins.php', 'Animal Writes', 'Animal Writes', aw_core_screen_cap( AW_CORE_MENU ), AW_CORE_MENU, 'aw_core_overview_page' );
		}
	},
	aw_core_menu_priority( AW_CORE_MENU )
);
// Its item is lit on the Overview (WordPress lights an admin.php?page= item under Plugins only when told).
add_filter(
	'submenu_file',
	function ( $file, $parent ) {
		global $plugin_page;
		return 'plugins.php' === $parent && AW_CORE_MENU === $plugin_page && AW_CORE_MENU === aw_core_menu_parent() ? AW_CORE_MENU : $file;
	},
	10,
	2
);

/**
 * Under a screen's heading: with Kits Core, the six Kits tabs (Dates lights Home; Keys and Modules light Settings);
 * without it, the site's screens the user can open, as a core sub-list.
 *
 * @param string $current The screen's slug.
 */
function aw_core_screen_nav( string $current ): void {
	if ( AW_CORE_MENU !== aw_core_menu_parent() ) {
		kits_admin_tabs( $current );
		return;
	}
	$links = array();
	foreach ( aw_core_screens() as $slug => $s ) {
		if ( current_user_can( $s[1] ) ) {
			$on      = $slug === $current;
			$links[] = '<li><a href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '"' . ( $on ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $s[0] ) . '</a>';
		}
	}
	if ( count( $links ) > 1 ) {
		echo '<ul class="subsubsub">' . implode( ' |</li> ', $links ) . '</li></ul><br class="clear">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
	}
}

// Kits → Home → Versions and limits: scheduled tasks and the last theme switch.
add_filter(
	'kits_status_rows',
	function ( $rows ) {
		$st     = aw_core_overview_status( false );
		$rows[] = array( 'Scheduled tasks', $st['cron'] );
		if ( current_user_can( 'switch_themes' ) ) {
			$log    = get_option( 'aw_theme_switch_log', array() );
			$last   = is_array( $log ) && $log ? end( $log ) : array();
			$rows[] = array( 'Last theme switch', $last ? esc_html( (string) ( $last['from'] ?? '' ) . ' → ' . (string) ( $last['to'] ?? '' ) . ', ' . ( isset( $last['time'] ) ? wp_date( 'j M Y, H:i', (int) $last['time'] ) : '' ) ) . ' ' . aw_core_link( 'Log', 'admin.php?page=' . KITS_MENU . '#theme-switch' ) : esc_html( 'None yet.' ) );
		}
		return $rows;
	}
);

/** The Kits filters Animal Writes uses to add its parts to the six Kits screens (with Kits Core only). */
function aw_core_kits_hooks(): void {
	// Old addresses (docs/admin-ui-spec.md §E1). Dates, Keys and Modules keep theirs.
	add_filter(
		'kits_admin_redirects',
		fn( $map ) => array_merge(
			(array) $map,
			array(
				AW_CORE_MENU      => KITS_MENU,
				'aw-site-check'   => KITS_MENU . '#page-check',
				'aw-elsewhere'    => 'kits-options#socials',
				'aw-edit-screens' => 'kits-help#developer/edit-screens',
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
				'aw-keys'    => 'kits-settings',
				'aw-modules' => 'kits-settings',
			)
		)
	);
	add_filter( 'kits_home_sections', 'aw_core_home_sections' );
	add_filter( 'kits_settings_sections', 'aw_core_settings_sections' );
	add_filter( 'kits_help_sections', 'aw_core_help_sections' );
	// Theme Options → Socials: the Contact page's "Elsewhere" links (its own list until the one socials list, §D1.10).
	add_action(
		'kits_options_legacy_section',
		function ( $slug ) {
			if ( 'socials' === $slug && current_user_can( aw_core_screen_cap( 'aw-elsewhere' ) ) ) {
				echo '<h3>' . esc_html( 'Contact links' ) . '</h3>';
				kits_admin_embed( 'aw_elsewhere_screen' );
			}
		}
	);
}
add_action(
	'plugins_loaded',
	function () {
		if ( AW_CORE_MENU !== aw_core_menu_parent() ) {
			aw_core_kits_hooks();
		}
	},
	20
);

/**
 * A Kits section from a function that prints it.
 *
 * @param string   $title  Heading.
 * @param callable $render Prints it.
 * @param int      $order  Place among the screen's sections (Kits' own: 10, 20, …).
 * @param string   $cap    Who sees it.
 */
function aw_core_section( string $title, callable $render, int $order, string $cap = 'manage_options' ): array {
	return array(
		'title'  => $title,
		'render' => $render,
		'cap'    => $cap,
		'order'  => $order,
	);
}

/**
 * Kits → Home: the site's checks (docs/admin-ui-spec.md §B1, §D6). Email, thumbnails and pictures stay here until the
 * MailWeb and SourceWeb dashboards have them (owner, 11 Nov 2026). Nothing WordPress or another plugin already shows
 * (owner, 11 Nov 2026): backups are UpdraftPlus's, the theme is Appearance → Themes, dates are PlanWeb's.
 *
 * @param array $sections Kits' sections.
 */
function aw_core_home_sections( $sections ) {
	$sections                 = (array) $sections;
	$sections['page-check']   = aw_core_section( 'Page check', 'aw_site_check_results', 20 );
	$sections['deploy']       = aw_core_section(
		'Deployed commit',
		function () {
			$st = aw_core_overview_status();
			echo '<p>' . $st['deploy'] . ' ' . $st['cdn'] . ' ' . $st['stage'] . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- aw_core_overview_status() escapes.
		},
		40
	);
	$sections['joined-files'] = aw_core_section( 'Joined files', 'aw_site_check_joined', 50 );
	$sections['email']        = aw_core_section( 'Email sending', 'aw_mail_site_check', 70 );
	$sections['thumbnails']   = aw_core_section( 'Thumbnails', 'aw_thumbs_site_check', 71 );
	$sections['pictures']     = aw_core_section( 'Pictures', 'aw_cc0_site_check', 72 );
	return $sections;
}

/**
 * Kits → Settings: CDN cache times (Performance), and where the service keys and the optional parts are set now
 * (RankWeb → Settings and SourceWeb → Settings).
 *
 * @param array $sections Kits' sections.
 */
function aw_core_settings_sections( $sections ) {
	$sections                       = (array) $sections;
	$sections['cdn']                = aw_core_section( 'CDN cache times', 'aw_cdn_settings_box', 15 );
	$sections['connected-services'] = aw_core_section(
		'Connected services and modules',
		function () {
			echo '<ul>';
			echo '<li>' . aw_core_link( 'Keys', 'admin.php?page=aw-keys' ) . ': ' . esc_html( 'the service keys the plugins use (Clarity, Bing Webmaster, PageSpeed).' ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- aw_core_link() escapes.
			echo '<li>' . aw_core_link( 'Modules', 'admin.php?page=aw-modules' ) . ': ' . esc_html( 'optional parts of the site (Social posts, the PlanWeb calendar), emails from forms, sample listings, district helplines and issuer short names.' ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- aw_core_link() escapes.
			echo '<li>' . aw_core_link( 'RankWeb → Settings', 'admin.php?page=aw-seo-settings' ) . ': ' . esc_html( 'connected services for search.' ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- aw_core_link() escapes.
			echo '<li>' . aw_core_link( 'SourceWeb → Settings', 'admin.php?page=aw-sourcing&tab=settings' ) . ': ' . esc_html( 'Social posts, sourcing lists and Directory enrichment.' ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- aw_core_link() escapes.
			echo '</ul>';
		},
		40
	);
	return $sections;
}

/**
 * Kits → Help: the site's Overview and commands (Docs), Edit screens and Theme parts (Developer).
 *
 * @param array $sections Kits' sections.
 */
function aw_core_help_sections( $sections ) {
	$sections                           = (array) $sections;
	$sections['docs/animal-writes']     = aw_core_section( 'Docs: Animal Writes', fn() => kits_admin_embed( 'aw_core_overview_page' ), 15 );
	$sections['developer/edit-screens'] = aw_core_section( 'Developer: edit screens', fn() => kits_admin_embed( 'aw_edit_screens_page' ), 30 );
	$sections['developer/theme-parts']  = aw_core_section( 'Developer: theme parts', fn() => kits_admin_embed( 'aw_theme_parts_screen' ), 31 );
	return $sections;
}

// Plugins screen: Overview under the plugin's name (Kits → Help → Docs: Animal Writes, or its own screen without Kits).
add_filter(
	'plugin_action_links_' . plugin_basename( AW_CORE_DIR . 'animal-writes-core.php' ),
	fn( $links ) => array_merge( array( '<a href="' . esc_url( admin_url( AW_CORE_MENU === aw_core_menu_parent() ? 'admin.php?page=' . AW_CORE_MENU : 'admin.php?page=kits-help#docs/animal-writes' ) ) . '">Overview</a>' ), (array) $links )
);

/**
 * Where a site screen is now, relative to wp-admin: its own address without Kits, else its place in the six.
 *
 * @param string $slug aw-site-check, aw-elsewhere, aw-edit-screens or cdn.
 */
function aw_core_screen_url( string $slug ): string {
	if ( AW_CORE_MENU === aw_core_menu_parent() ) {
		return 'admin.php?page=' . ( 'cdn' === $slug ? 'aw-site-check' : $slug );
	}
	$map = array(
		'aw-site-check'   => 'admin.php?page=' . KITS_MENU . '#page-check',
		'aw-elsewhere'    => 'admin.php?page=kits-options#socials',
		'aw-edit-screens' => 'admin.php?page=kits-help#developer/edit-screens',
		'cdn'             => 'admin.php?page=kits-settings#cdn',
	);
	return $map[ $slug ] ?? 'admin.php?page=' . KITS_MENU;
}

/** A link for the Overview tables. */
function aw_core_link( string $label, string $path ): string {
	return '<a href="' . esc_url( admin_url( $path ) ) . '">' . esc_html( $label ) . '</a>';
}

/** One status cell: a dashicon (yes / warning) and a short text. */
function aw_core_status( bool $ok, string $text ): string {
	return '<span class="dashicons dashicons-' . ( $ok ? 'yes' : 'warning' ) . '" aria-hidden="true"></span> ' . esc_html( $text );
}

/**
 * Status lines from the checks that already run.
 *
 * @param bool $full False: only backup, check, cron and deploy (Kits → Home).
 */
function aw_core_overview_status( bool $full = true ): array {
	$s = array();
	$b = get_option( 'aw_backup_last' );
	$s['backup'] = is_array( $b ) && ! empty( $b['at'] )
		? aw_core_status( isset( $b['db'], $b['code'] ) && strtotime( (string) $b['at'] ) > time() - 2 * DAY_IN_SECONDS, 'Last backup ' . $b['at'] . '.' )
		: aw_core_status( false, 'No backup recorded yet.' );
	$c          = aw_site_check_summary(); // Result, time and commit of the last check (site-check.php).
	$s['check'] = 'Not run yet.' === $c['text'] ? esc_html( $c['text'] ) : aw_core_status( $c['ok'], $c['text'] );
	$last        = function_exists( 'aw_cron_last' ) ? (int) aw_cron_last() : 0;
	$s['cron']   = $last ? aw_core_status( $last > time() - HOUR_IN_SECONDS, 'Last run ' . wp_date( 'j M Y, g:i a', $last ) . '.' ) : esc_html( 'No run recorded.' );
	$head        = (string) get_option( 'aw_deployed_head', '' );
	$s['deploy'] = $head ? esc_html( 'Deployed commit ' . substr( $head, 0, 7 ) . '; cache purged once for it.' ) : esc_html( 'No deploy recorded.' );
	if ( ! $full ) {
		return $s;
	}
	$ttl        = (int) get_option( 'aw_cdn_ttl', 0 ); // aw_cdn_ttl() is 0 in wp-admin (no header there), so the option itself
	$ttl        = $ttl > 0 ? max( 60, min( 3600, $ttl ) ) : 0;
	$long       = (int) get_option( 'aw_cdn_ttl_long', 3600 );
	$s['cdn']   = esc_html( $ttl ? 'CDN keeps public pages ' . $ttl . ' s' . ( $long > $ttl ? '; listings, Library items and Resources ' . min( 3600, $long ) . ' s.' : '.' ) : 'CDN caching off.' );
	$s['stage'] = esc_html( function_exists( 'aw_is_staging' ) && aw_is_staging() ? 'This is the staging site.' : 'Live site.' );
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
	$s['dates'] = esc_html( $next ? 'Next: ' . aw_team_date_label( $next[0] ) . ( $next[1] ? ', ' . $next[1] : '' ) . '.' : 'Nothing coming up.' );
	$s['review'] = function_exists( 'aw_review_count' ) ? esc_html( (int) aw_review_count() . ' items waiting.' ) : esc_html( 'SourceWeb is off.' );
	return $s;
}

/** The Overview: Kits → Help → Docs: Animal Writes (its own screen without Kits). */
function aw_core_overview_page() {
	$st    = aw_core_overview_status();
	$admin = current_user_can( 'manage_options' );
	$auto  = 'Automatic';

	$groups = array(
		'Content and pages' => array(
			array( 'Content types', 'Directory listings, News, Resources (guides and research), Library, Opportunities, Campaigns and Press coverage, each with its own address space, fields and relations shown on both sides.', aw_core_link( 'Directory', 'edit.php?post_type=aw_listing' ) . ', ' . aw_core_link( 'News', 'edit.php?post_type=aw_news' ) . ', ' . aw_core_link( 'Library', 'edit.php?post_type=aw_legal' ) . ' …', $auto ),
			array( 'Classification', 'Categories and Tags shared by every section; category photos, banners and help types.', aw_core_link( 'Classification', 'edit-tags.php?taxonomy=category' ), $auto ),
			array( 'Hub pages and item pages', 'Section hubs as editable Pages with their lists; one item page for News, Library, Resources and Opportunities; the Directory profile; category and tag pages; related items, "Featured in", "Also reported by", linked names.', aw_core_link( 'Pages', 'edit.php?post_type=page' ), $auto ),
			array( 'Places', 'State and District from the Local Government Directory; PIN code fills them on every item with a place.', '—', $auto ),
			array( 'Retention', 'Nothing is deleted: old items are archived and keep their address.', '—', $auto ),
		),
		'Directory' => array(
			array( 'Directory and map', 'State, district and type pages, verified first; the map and "Near me"; district helplines; "Find help near you" links from News, Library and guides.', aw_core_link( 'Directory', 'edit.php?post_type=aw_listing' ), $auto ),
			array( 'Listing profile', 'Organisation details, data sources per value, unverified marks, coordinates with a map picker, volunteers from FluentCRM.', 'Organisation details box on each listing', $auto ),
			array( 'Claims and reports', 'Claim a listing and Report a mistake (Fluent Forms); claim review on the listing.', aw_core_link( 'Fluent Forms → Entries', 'admin.php?page=fluent_forms_all_entries' ), $auto ),
		),
		'Campaigns' => array(
			array( 'Campaign tools', 'Countdown, letter tool, Campaign form, live counts, Real-world impact, campaign record.', aw_core_link( 'Campaigns', 'edit.php?post_type=aw_campaign' ), $auto ),
			array( 'Letter report', 'Anonymous letter statistics per campaign.', aw_core_link( 'Campaigns → Letter report', 'edit.php?post_type=aw_campaign&page=aw-letter-report' ), $auto ),
			array( 'Linked campaigns', 'Which campaign a News, Library, Resources, Press or Opportunities item belongs to.', 'Linked campaigns box on those items', $auto ),
		),
		'People, forms and mail' => array(
			array( 'Forms', 'Contact, volunteer, claim, report, newsletter and campaign forms in Fluent Forms; consent per purpose; Remove my data.', aw_core_link( 'Fluent Forms', 'admin.php?page=fluent_forms' ), $auto ),
			array( 'People', 'One FluentCRM list, "Newsletter"; volunteers are subscribers; old site tables kept as the backup.', aw_core_link( 'FluentCRM', 'admin.php?page=fluentcrm-admin' ), $auto ),
			array( 'Mail', 'Website forms in the Mail inbox, the campaign strip under signatures (MailWeb).', aw_core_link( 'Mail', 'admin.php?page=mailweb' ), $auto ),
		),
		'Pictures and media' => array(
			array( 'Card pictures', 'The article\'s own preview picture or a placard; free CC0 photos when a publisher\'s picture is missing; thumbnails never repeat.', aw_core_link( 'Media', 'upload.php' ), $auto ),
			array( 'Press coverage and sponsored blocks', 'Coverage of Animal Writes on /media/; the Sponsored block group.', aw_core_link( 'Press coverage', 'edit.php?post_type=aw_press' ), $auto ),
		),
		'Running the site' => array(
			function_exists( 'aw_team_dates_moved' ) && aw_team_dates_moved()
				? array( 'Dates', 'Dates moved to PlanWeb: the team\'s calendar, plans and reminders, and the Next 7 days dashboard box.', aw_core_link( 'Plugins → PlanWeb → Calendar', 'admin.php?page=planweb' ), $st['dates'] )
				: array( 'Dates', 'The team\'s calendar (observance days, deadlines) and the Dates dashboard widget.', aw_core_link( 'Dates', 'admin.php?page=aw-dates' ), $st['dates'] ),
			array( 'Check site', 'Loads one page of every kind and reports any that do not open cleanly; also in the admin bar.', $admin ? aw_core_link( 'Appearance → Kits → Home → Page check', aw_core_screen_url( 'aw-site-check' ) ) : 'Administrators', $st['check'] ),
			array( 'Contact links', 'The "Elsewhere" links on the Contact page.', aw_core_link( 'Contact links', aw_core_screen_url( 'aw-elsewhere' ) ), $auto ),
			array( 'Keys', 'Service keys the plugins use (Clarity, Bing Webmaster, PageSpeed): paste once, never shown again; a wp-config.php constant wins.', $admin ? aw_core_link( 'Keys', 'admin.php?page=aw-keys' ) : 'Administrators', $auto ),
			array( 'Edit screens', 'Every box on each edit screen (WordPress, plugins and ours), the keys it stores and where each key is used.', $admin ? aw_core_link( 'Edit screens', aw_core_screen_url( 'aw-edit-screens' ) ) : 'Administrators', $auto ),
			array( 'To review', 'Items waiting for a person (from SourceWeb).', aw_core_link( 'Plugins → SourceWeb → To review', 'admin.php?page=aw-review' ), $st['review'] ),
			array( 'Scheduled tasks', 'Who triggers WP-Cron (Hostinger, GitHub, UptimeRobot) and the longest gap; dashboard widget.', aw_core_link( 'Dashboard', 'index.php' ), $st['cron'] ),
			array( 'Daily backup', 'Database and deployed code into ~/backups on the server, kept 14 days.', $admin ? aw_core_link( 'UpdraftPlus', 'options-general.php?page=updraftplus' ) : '—', $st['backup'] ),
			array( 'Deploys and cache', 'LiteSpeed purged once after each deploy; CDN lifetime for anonymous public pages.', $admin ? aw_core_link( 'CDN cache times', aw_core_screen_url( 'cdn' ) ) : '—', $st['deploy'] . ' ' . $st['cdn'] ),
			array( 'Staging', 'staging.animalwrites.in: same code, database without personal data, marked in the admin bar.', '—', $st['stage'] ),
		),
	);

	echo '<div class="wrap"><h1>Animal Writes</h1>';
	aw_core_screen_nav( AW_CORE_MENU );
	echo '<p>The site\'s own plugin: its content model, Directory, campaigns, forms and the jobs that keep it running. Each row says where to find the feature.</p>';
	aw_core_overview_attention( $groups );
	$opts = array(
		'primary' => 'feature',
		'foot'    => false,
	);
	foreach ( $groups as $title => $rows ) {
		echo '<h2>' . esc_html( $title ) . '</h2>';
		aw_core_simple_table( 'aw-overview', array( 'feature' => 'Feature', 'what' => 'What it does', 'status' => 'Status' ), aw_core_overview_cells( $rows ), $opts );
	}

	echo '<details><summary><strong>On the dashboard</strong> (3)</summary>';
	aw_core_simple_table(
		'aw-widgets',
		array( 'widget' => 'Widget', 'what' => 'What it shows' ),
		array(
			array( 'widget' => 'Dates', 'what' => 'The next dates in the team calendar.' ),
			array( 'widget' => 'Scheduled tasks', 'what' => 'WP-Cron triggers in the last 24 hours (administrators).' ),
			array( 'widget' => 'Campaign letters', 'what' => 'Letters sent per campaign.' ),
		),
		array(
			'primary' => 'widget',
			'foot'    => false,
		)
	);
	echo '</details>';

	$cmds = array(
		array( 'cmd' => '<code>wp aw crm-setup</code>', 'what' => 'FluentCRM sender, list, tags and custom fields' ),
		array( 'cmd' => '<code>wp aw ff-form</code>', 'what' => 'Which Fluent Forms form shows as each site form' ),
		array( 'cmd' => '<code>wp aw geocode</code>', 'what' => 'Listing coordinates for the map' ),
		array( 'cmd' => '<code>wp aw sort-dates</code>', 'what' => 'News and Library sort dates' ),
		array( 'cmd' => '<code>wp aw redirects-import</code>', 'what' => 'Old addresses into Rank Math Redirections' ),
		array( 'cmd' => '<code>wp aw raw-export</code>', 'what' => 'Campaign form sign-ups as CSV' ),
		array( 'cmd' => '<code>wp aw-edit-screens</code>', 'what' => 'Edit-screen boxes, their fields and where they are used (read-only)' ),
		array( 'cmd' => '<code>wp aw-featured scan</code>', 'what' => '"Featured in" links between organisations and items' ),
		array( 'cmd' => '<code>wp aw-places detect</code>, <code>mark-auto</code>', 'what' => 'Places found in items' ),
	);
	echo '<details><summary><strong>Commands (WP-CLI on the server)</strong> (' . count( $cmds ) . ')</summary>';
	$opts = array(
		'primary' => 'cmd',
		'foot'    => false,
	);
	aw_core_simple_table( 'aw-commands', array( 'cmd' => 'Command', 'what' => 'What it does' ), $cmds, $opts );
	echo '<p>Each command lists its options with --help.</p></details></div>';
}

/**
 * Overview rows [feature, what, where, status] as three cells: the feature with its place under it (as a list
 * table's row links), what it does, and its status.
 *
 * @param array $rows Rows.
 */
function aw_core_overview_cells( array $rows ): array {
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
function aw_core_overview_attention( array $groups ): void {
	$items = array();
	foreach ( $groups as $rows ) {
		foreach ( $rows as $r ) {
			// Check site is on Kits → Home → Page check already; not repeated here.
			if ( 'Check site' !== $r[0] && false !== strpos( (string) $r[3], 'dashicons-warning' ) ) {
				$items[] = '<li><strong>' . esc_html( $r[0] ) . '</strong>: ' . esc_html( trim( wp_strip_all_tags( (string) $r[3] ) ) ) . ( '' !== $r[2] && '—' !== $r[2] ? ' (' . $r[2] . ')' : '' ) . '</li>';
			}
		}
	}
	if ( $items ) {
		echo '<div class="notice notice-warning inline"><p><strong>Needs attention</strong></p><ul class="ul-disc">' . implode( '', $items ) . '</ul></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
	}
}
