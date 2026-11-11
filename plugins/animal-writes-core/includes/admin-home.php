<?php
/**
 * The site's own screens, as the site's section of the Kits menu (owner, 25 Oct and 10 Nov 2026: our plugins work like
 * any marketplace plugin: an overview of every feature and where it lives, links from the Plugins screen).
 *
 * Kits → Site: Animal Writes (the Overview, this file), Dates (team-dates.php), Check site (site-check.php), Contact
 * links (pages.php), Keys (keys.php), Modules (social-posts.php), Edit screens (edit-screens.php), after Kits' own
 * screens. One "Animal Writes" tab in the Kits tabs opens them; each screen lists the others under the tabs.
 * Without Kits Core the same screens form their own "Animal Writes" menu, as before.
 * Content menus (Directory, News, Resources, Library, Opportunities, Campaigns, Press coverage), Classification and
 * Sourcing stay where the content is. Core WordPress only.
 *
 * Editors: the Kits menu needs manage_options, but WordPress shows a menu to anyone who can open one of its screens and
 * points it at the first one they can open (here the Overview); the tabs and the list below them show only what the
 * user can open.
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

add_action(
	'admin_menu',
	function () {
		if ( AW_CORE_MENU === aw_core_menu_parent() ) {
			add_menu_page( 'Animal Writes', 'Animal Writes', aw_core_screen_cap( AW_CORE_MENU ), AW_CORE_MENU, 'aw_core_overview_page', 'dashicons-pets', 28 );
			add_submenu_page( AW_CORE_MENU, 'Animal Writes', 'Overview', aw_core_screen_cap( AW_CORE_MENU ), AW_CORE_MENU, 'aw_core_overview_page' );
			return;
		}
		add_submenu_page( KITS_MENU, 'Animal Writes', 'Site: Animal Writes', aw_core_screen_cap( AW_CORE_MENU ), AW_CORE_MENU, 'aw_core_overview_page' ); // Kits → Site: Animal Writes
	},
	aw_core_menu_priority( AW_CORE_MENU )
);

// Kits tabs: one tab for the site's screens; editors see it because they can open the Overview.
add_filter(
	'kits_admin_tabs',
	function ( $tabs ) {
		$tabs[ AW_CORE_MENU ] = array( 'Animal Writes', 'admin.php?page=' . AW_CORE_MENU, aw_core_screen_cap( AW_CORE_MENU ) );
		return $tabs;
	}
);

/**
 * Under a screen's heading: the Kits tabs with "Animal Writes" selected (when Kits Core is loaded), then the site's
 * screens the user can open, as a core sub-list.
 *
 * @param string $current The screen's slug.
 */
function aw_core_screen_nav( string $current ): void {
	if ( AW_CORE_MENU !== aw_core_menu_parent() ) {
		kits_admin_tabs( AW_CORE_MENU );
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

// Kits → Welcome, System Status: the site's last check, backup and deploy, linked to Check site.
add_filter(
	'kits_status_rows',
	function ( $rows ) {
		$st   = aw_core_overview_status( false );
		$link = ' ' . aw_core_link( 'Check site', 'admin.php?page=aw-site-check' );
		foreach ( array(
			'check'  => 'Animal Writes: pages',
			'backup' => 'Animal Writes: backup',
			'deploy' => 'Animal Writes: deploy',
		) as $k => $label ) {
			$rows[] = array( $label, $st[ $k ] . $link );
		}
		return $rows;
	}
);

// Plugins screen: Overview under the plugin's name (the same address in both menus).
add_filter(
	'plugin_action_links_' . plugin_basename( AW_CORE_DIR . 'animal-writes-core.php' ),
	fn( $links ) => array_merge( array( '<a href="' . esc_url( admin_url( 'admin.php?page=' . AW_CORE_MENU ) ) . '">Overview</a>' ), (array) $links )
);

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
 * @param bool $full False: only backup, check, cron and deploy (Kits → Welcome).
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

/** Kits → Site: Animal Writes (the Overview). */
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
				? array( 'Dates', 'Dates moved to PlanWeb: the team\'s calendar, plans and reminders, and the Next 7 days dashboard box.', aw_core_link( 'PlanWeb → Calendar', 'admin.php?page=planweb' ), $st['dates'] )
				: array( 'Dates', 'The team\'s calendar (observance days, deadlines) and the Dates dashboard widget.', aw_core_link( 'Kits → Dates', 'admin.php?page=aw-dates' ), $st['dates'] ),
			array( 'Check site', 'Loads one page of every kind and reports any that do not open cleanly; also in the admin bar.', $admin ? aw_core_link( 'Kits → Check site', 'admin.php?page=aw-site-check' ) : 'Administrators', $st['check'] ),
			array( 'Contact links', 'The "Elsewhere" links on the Contact page.', aw_core_link( 'Kits → Contact links', 'admin.php?page=aw-elsewhere' ), $auto ),
			array( 'Keys', 'Service keys the plugins use (Clarity, Bing Webmaster, PageSpeed): paste once, never shown again; a wp-config.php constant wins.', $admin ? aw_core_link( 'Kits → Keys', 'admin.php?page=aw-keys' ) : 'Administrators', $auto ),
			array( 'Edit screens', 'Every box on each edit screen (WordPress, plugins and ours), the keys it stores and where each key is used.', $admin ? aw_core_link( 'Kits → Edit screens', 'admin.php?page=aw-edit-screens' ) : 'Administrators', $auto ),
			array( 'To review', 'Items waiting for a person (from SourceWeb).', aw_core_link( 'SourceWeb → To review', 'admin.php?page=aw-review' ), $st['review'] ),
			array( 'Scheduled tasks', 'Who triggers WP-Cron (Hostinger, GitHub, UptimeRobot) and the longest gap; dashboard widget.', aw_core_link( 'Dashboard', 'index.php' ), $st['cron'] ),
			array( 'Daily backup', 'Database and deployed code into ~/backups on the server, kept 14 days.', $admin ? aw_core_link( 'Check site', 'admin.php?page=aw-site-check' ) : '—', $st['backup'] ),
			array( 'Deploys and cache', 'LiteSpeed purged once after each deploy; CDN lifetime for anonymous public pages.', $admin ? aw_core_link( 'Check site → Shared cache (CDN)', 'admin.php?page=aw-site-check' ) : '—', $st['deploy'] . ' ' . $st['cdn'] ),
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
			if ( false !== strpos( (string) $r[3], 'dashicons-warning' ) ) {
				$items[] = '<li><strong>' . esc_html( $r[0] ) . '</strong>: ' . esc_html( trim( wp_strip_all_tags( (string) $r[3] ) ) ) . ( '' !== $r[2] && '—' !== $r[2] ? ' (' . $r[2] . ')' : '' ) . '</li>';
			}
		}
	}
	if ( $items ) {
		echo '<div class="notice notice-warning inline"><p><strong>Needs attention</strong></p><ul class="ul-disc">' . implode( '', $items ) . '</ul></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
	}
}
