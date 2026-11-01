<?php
/**
 * The plugin's own menu and Overview (owner, 25 Oct 2026: our plugins work like any marketplace plugin: one menu, an
 * overview of every feature and where it lives, links from the Plugins screen).
 *
 * Animal Writes → Overview (this file), Dates (team-dates.php), Check site (site-check.php), Contact links (pages.php),
 * Keys (keys.php), Edit screens (edit-screens.php).
 * Content menus (Directory, News, Resources, Library, Opportunities, Campaigns, Press coverage), Classification and
 * Inbox stay where the content is. Core WordPress only.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_CORE_MENU = 'animal-writes';

add_action(
	'admin_menu',
	function () {
		add_menu_page( 'Animal Writes', 'Animal Writes', 'edit_posts', AW_CORE_MENU, 'aw_core_overview_page', 'dashicons-pets', 28 );
		add_submenu_page( AW_CORE_MENU, 'Animal Writes', 'Overview', 'edit_posts', AW_CORE_MENU, 'aw_core_overview_page' );
	},
	9 // before the screens that join this menu, in this order: Dates (10), Check site (11), Contact links (12)
);

// Plugins screen: Overview under the plugin's name.
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

/** Status lines from the checks that already run. */
function aw_core_overview_status(): array {
	$s = array();
	$b = get_option( 'aw_backup_last' );
	$s['backup'] = is_array( $b ) && ! empty( $b['at'] )
		? aw_core_status( isset( $b['db'], $b['code'] ) && strtotime( (string) $b['at'] ) > time() - 2 * DAY_IN_SECONDS, 'Last backup ' . $b['at'] . '.' )
		: aw_core_status( false, 'No backup recorded yet.' );
	$c = get_option( 'aw_site_check_last' );
	if ( is_array( $c ) && ! empty( $c['rows'] ) ) {
		$bad          = count( array_filter( $c['rows'], fn( $r ) => '' !== $r[4] ) );
		$s['check'] = aw_core_status( 0 === $bad, ( $bad ? $bad . ' of ' . count( $c['rows'] ) . ' pages had a problem' : 'All ' . count( $c['rows'] ) . ' pages opened cleanly' ) . ' (' . $c['at'] . ').' );
	} else {
		$s['check'] = esc_html( 'Not run yet.' );
	}
	$last        = function_exists( 'aw_cron_last' ) ? (int) aw_cron_last() : 0;
	$s['cron']   = $last ? aw_core_status( $last > time() - HOUR_IN_SECONDS, 'Last run ' . wp_date( 'j M Y, g:i a', $last ) . '.' ) : esc_html( 'No run recorded.' );
	$head        = (string) get_option( 'aw_deployed_head', '' );
	$s['deploy'] = $head ? esc_html( 'Deployed commit ' . substr( $head, 0, 7 ) . '; cache purged once for it.' ) : esc_html( 'No deploy recorded.' );
	$ttl         = function_exists( 'aw_cdn_ttl' ) ? (int) aw_cdn_ttl() : 0;
	$s['cdn']    = esc_html( $ttl ? 'CDN keeps public pages ' . $ttl . ' s.' : 'CDN caching off.' );
	$s['stage']  = esc_html( function_exists( 'aw_is_staging' ) && aw_is_staging() ? 'This is the staging site.' : 'Live site.' );
	$next        = '';
	if ( function_exists( 'aw_team_dates' ) ) {
		$today = wp_date( 'Y-m-d' );
		foreach ( (array) aw_team_dates() as $d ) {
			if ( ! empty( $d['date'] ) && $d['date'] >= $today && ( '' === $next || $d['date'] < $next[0] ) ) {
				$next = array( $d['date'], (string) ( $d['title'] ?? '' ) );
			}
		}
	}
	$s['dates'] = esc_html( $next ? 'Next: ' . aw_team_date_label( $next[0] ) . ( $next[1] ? ', ' . $next[1] : '' ) . '.' : 'Nothing coming up.' );
	$s['review'] = function_exists( 'aw_review_count' ) ? esc_html( (int) aw_review_count() . ' items waiting.' ) : esc_html( 'AW Sourcing is off.' );
	return $s;
}

/** Animal Writes → Overview. */
function aw_core_overview_page() {
	$st    = aw_core_overview_status();
	$admin = current_user_can( 'manage_options' );
	$auto  = 'Automatic';

	$groups = array(
		'Content and pages' => array(
			array( 'Content types', 'Directory listings, News, Resources (guides and research), Library, Opportunities, Campaigns and Press coverage, each with its own address space, fields and relations shown on both sides.', aw_core_link( 'Directory', 'edit.php?post_type=aw_listing' ) . ', ' . aw_core_link( 'News', 'edit.php?post_type=aw_news' ) . ', ' . aw_core_link( 'Library', 'edit.php?post_type=aw_legal' ) . ' …', $auto ),
			array( 'Classification', 'Categories and Tags shared by every section; category photos, banners and help types.', aw_core_link( 'Classification', 'edit-tags.php?taxonomy=category' ), $auto ),
			array( 'Hub pages and item pages', 'Section hubs as editable Pages with their lists; one item page for News, Library, Resources and Opportunities; the Directory profile; category and tag pages; related items, "Featured in", "Also reported by", linked names.', aw_core_link( 'Pages', 'edit.php?post_type=page' ), $auto ),
			array( 'Places', 'State and District from the Local Government Directory; PIN code fills them on every item with a place.', 'Place box on each item', $auto ),
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
			array( 'Mail', 'Website forms in the Mail inbox, the campaign strip under signatures (Mailweb).', aw_core_link( 'Mail', 'admin.php?page=mailweb' ), $auto ),
		),
		'Pictures and media' => array(
			array( 'Card pictures', 'The article\'s own preview picture or a placard; free CC0 photos when a publisher\'s picture is missing; thumbnails never repeat.', aw_core_link( 'Media', 'upload.php' ), $auto ),
			array( 'Press coverage and sponsored blocks', 'Coverage of Animal Writes on /media/; the Sponsored block group.', aw_core_link( 'Press coverage', 'edit.php?post_type=aw_press' ), $auto ),
		),
		'Running the site' => array(
			array( 'Dates', 'The team\'s calendar (observance days, deadlines) and the Dates dashboard widget.', aw_core_link( 'Animal Writes → Dates', 'admin.php?page=aw-dates' ), $st['dates'] ),
			array( 'Check site', 'Loads one page of every kind and reports any that do not open cleanly; also in the admin bar.', $admin ? aw_core_link( 'Animal Writes → Check site', 'admin.php?page=aw-site-check' ) : 'Administrators', $st['check'] ),
			array( 'Contact links', 'The "Elsewhere" links on the Contact page.', aw_core_link( 'Animal Writes → Contact links', 'admin.php?page=aw-elsewhere' ), $auto ),
			array( 'Keys', 'Service keys the plugins use (Clarity, Bing Webmaster, PageSpeed): paste once, never shown again; a wp-config.php constant wins.', $admin ? aw_core_link( 'Animal Writes → Keys', 'admin.php?page=aw-keys' ) : 'Administrators', $auto ),
			array( 'Edit screens', 'Every box on each edit screen (WordPress, plugins and ours), the keys it stores and where each key is used.', $admin ? aw_core_link( 'Animal Writes → Edit screens', 'admin.php?page=aw-edit-screens' ) : 'Administrators', $auto ),
			array( 'To review', 'Items waiting for a person (from AW Sourcing).', aw_core_link( 'Sourcing → To review', 'admin.php?page=aw-review' ), $st['review'] ),
			array( 'Scheduled tasks', 'Who triggers WP-Cron (Hostinger, GitHub, UptimeRobot) and the longest gap; dashboard widget.', aw_core_link( 'Dashboard', 'index.php' ), $st['cron'] ),
			array( 'Daily backup', 'Database and deployed code into ~/backups on the server, kept 14 days.', $admin ? aw_core_link( 'Check site', 'admin.php?page=aw-site-check' ) : '—', $st['backup'] ),
			array( 'Deploys and cache', 'LiteSpeed purged once after each deploy; CDN lifetime for anonymous public pages.', '—', $st['deploy'] . ' ' . $st['cdn'] ),
			array( 'Staging', 'staging.animalwrites.in: same code, database without personal data, marked in the admin bar.', '—', $st['stage'] ),
		),
	);

	echo '<div class="wrap"><h1>Animal Writes</h1>';
	echo '<p>The site\'s own plugin: its content model, Directory, campaigns, forms and the jobs that keep it running. Each row says where to find the feature.</p>';
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
		aw_core_simple_table( 'aw-overview', array( 'feature' => 'Feature', 'what' => 'What it does', 'where' => 'Where', 'status' => 'Status' ), $cells, array( 'primary' => 'feature' ) );
	}

	echo '<h2>On the dashboard</h2>';
	aw_core_simple_table(
		'aw-widgets',
		array( 'widget' => 'Widget', 'what' => 'What it shows' ),
		array(
			array( 'widget' => 'Dates', 'what' => 'The next dates in the team calendar.' ),
			array( 'widget' => 'Scheduled tasks', 'what' => 'WP-Cron triggers in the last 24 hours (administrators).' ),
			array( 'widget' => 'Campaign letters', 'what' => 'Letters sent per campaign.' ),
		),
		array( 'primary' => 'widget' )
	);

	echo '<h2>Commands (WP-CLI on the server)</h2>';
	aw_core_simple_table(
		'aw-commands',
		array( 'cmd' => 'Command', 'what' => 'What it does' ),
		array(
			array( 'cmd' => '<code>wp aw crm-setup</code>', 'what' => 'FluentCRM sender, list, tags and custom fields' ),
			array( 'cmd' => '<code>wp aw ff-form</code>', 'what' => 'Which Fluent Forms form shows as each site form' ),
			array( 'cmd' => '<code>wp aw geocode</code>', 'what' => 'Listing coordinates for the map' ),
			array( 'cmd' => '<code>wp aw sort-dates</code>', 'what' => 'News and Library sort dates' ),
			array( 'cmd' => '<code>wp aw redirects-import</code>', 'what' => 'Old addresses into Rank Math Redirections' ),
			array( 'cmd' => '<code>wp aw raw-export</code>', 'what' => 'Campaign form sign-ups as CSV' ),
			array( 'cmd' => '<code>wp aw-edit-screens</code>', 'what' => 'Edit-screen boxes, their fields and where they are used (read-only)' ),
			array( 'cmd' => '<code>wp aw-featured scan</code>', 'what' => '"Featured in" links between organisations and items' ),
			array( 'cmd' => '<code>wp aw-places detect</code>, <code>mark-auto</code>', 'what' => 'Places found in items' ),
		),
		array( 'primary' => 'cmd' )
	);
	echo '<p>Each command lists its options with --help.</p></div>';
}
