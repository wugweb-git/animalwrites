<?php
/**
 * Animal Writes' values for the Kits Core admin layer (plugins/kits-core/includes/admin-layer/, moved there from this
 * plugin on 18 Nov 2026). Like kits-config.php, this is site configuration, not code: it names the site's content
 * menus, labels, retired types and shortcodes, the edit-screen map and the Overview. Its final home is the site's
 * settings (docs/content-blocks-feasibility.md §22), together with kits-config.php.
 *
 * Moved from Animal Writes Core's admin-config.php (19 Nov 2026) into the theme's site layer; loaded by site.php.
 *
 * @package kits-by-wugweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- Menus and labels (was admin-ia.php) ---------- */

add_filter( 'kits_comments_off', '__return_true' ); // Comments hidden and off sitewide.
// Testimonials were a 1.0 leftover (0 items, exported to aw-private on 8 Oct 2026); [aw_banner] was replaced by the
// Banner pattern. Both still sit in old content (Home) and print nothing.
add_filter( 'kits_retired_shortcodes', fn( $tags ) => array_merge( (array) $tags, array( 'aw_testimonials', 'aw_banner' ) ) );
add_filter(
	'kits_admin_post_type_labels',
	fn( $labels ) => array_merge(
		(array) $labels,
		array(
			'aw_campaign' => array(
				'name'      => 'Campaigns',
				'menu_name' => 'Campaigns',
			),
			'aw_message'  => array( // the contact form's messages
				'name'      => 'Contact messages',
				'menu_name' => 'Contact messages',
				'all_items' => 'Contact messages',
			),
		)
	)
);
// Retired from the menu: website forms are in Mail, claims in Fluent Forms; items keep their data.
add_filter( 'kits_admin_hidden_post_types', fn( $types ) => array_merge( (array) $types, array( 'aw_message', 'aw_claim' ) ) );
// Opening a contact message marks it read.
add_filter( 'kits_admin_read_marks', fn( $marks ) => array_merge( (array) $marks, array( 'aw_message' => 'aw_read' ) ) );
// Menu order follows the site: Directory · News · Resources · Library · Opportunities · Campaigns · Press coverage.
add_filter(
	'kits_admin_content_menus',
	fn() => array(
		'edit.php?post_type=aw_listing',
		'edit.php?post_type=aw_news',
		'edit.php',
		'edit.php?post_type=aw_legal',
		'edit.php?post_type=aw_opportunity',
		'edit.php?post_type=aw_campaign',
		'edit.php?post_type=aw_press',
	)
);

/* ---------- Edit screens and edit fields ---------- */

add_filter( 'kits_edit_screen_map_file', fn() => kits_site_path( 'data/edit-screen-map.json' ) );
add_filter( 'kits_library_post_type', fn() => 'aw_legal' );

/* ---------- The Overview: Kits → Help → Docs: Animal Writes (was admin-home.php) ---------- */

add_filter(
	'kits_site_overview',
	function ( $overview ) {
		if ( ! function_exists( 'kits_site_status' ) ) {
			return (array) $overview;
		}
		$st    = kits_site_status();
		$admin = current_user_can( 'manage_options' );
		$auto  = 'Automatic';
		$link  = 'kits_site_link';
		$url   = 'kits_site_screen_url';

		$groups = array(
			'Content and pages'      => array(
				array( 'Content types', 'Directory listings, News, Resources (guides and research), Library, Opportunities, Campaigns and Press coverage, each with its own address space, fields and relations shown on both sides.', $link( 'Directory', 'edit.php?post_type=aw_listing' ) . ', ' . $link( 'News', 'edit.php?post_type=aw_news' ) . ', ' . $link( 'Library', 'edit.php?post_type=aw_legal' ) . ' …', $auto ),
				array( 'Classification', 'Categories and Tags shared by every section; category photos, banners and help types.', $link( 'Classification', 'edit-tags.php?taxonomy=category' ), $auto ),
				array( 'Hub pages and item pages', 'Section hubs as editable Pages with their lists; one item page for News, Library, Resources and Opportunities; the Directory profile; category and tag pages; related items, "Featured in", "Also reported by", linked names.', $link( 'Pages', 'edit.php?post_type=page' ), $auto ),
				array( 'Places', 'State and District from the Local Government Directory; PIN code fills them on every item with a place.', '—', $auto ),
				array( 'Retention', 'Nothing is deleted: old items are archived and keep their address.', '—', $auto ),
			),
			'Directory'              => array(
				array( 'Directory and map', 'State, district and type pages, verified first; the map and "Near me"; district helplines; "Find help near you" links from News, Library and guides.', $link( 'Directory', 'edit.php?post_type=aw_listing' ), $auto ),
				array( 'Listing profile', 'Organisation details, data sources per value, unverified marks, coordinates with a map picker, volunteers from FluentCRM.', 'Organisation details box on each listing', $auto ),
				array( 'Claims and reports', 'Claim a listing and Report a mistake (Fluent Forms); claim review on the listing.', $link( 'Fluent Forms → Entries', 'admin.php?page=fluent_forms_all_entries' ), $auto ),
			),
			'Campaigns'              => array(
				array( 'Campaign tools', 'Countdown, letter tool, Campaign form, live counts, Real-world impact, campaign record.', $link( 'Campaigns', 'edit.php?post_type=aw_campaign' ), $auto ),
				array( 'Letter report', 'Anonymous letter statistics per campaign.', $link( 'Campaigns → Letter report', 'edit.php?post_type=aw_campaign&page=aw-letter-report' ), $auto ),
				array( 'Linked campaigns', 'Which campaign a News, Library, Resources, Press or Opportunities item belongs to.', 'Linked campaigns box on those items', $auto ),
			),
			'People, forms and mail' => array(
				array( 'Forms', 'Contact, volunteer, claim, report, newsletter and campaign forms in Fluent Forms; consent per purpose; Remove my data.', $link( 'Fluent Forms', 'admin.php?page=fluent_forms' ), $auto ),
				array( 'People', 'One FluentCRM list, "Newsletter"; volunteers are subscribers; old site tables kept as the backup.', $link( 'FluentCRM', 'admin.php?page=fluentcrm-admin' ), $auto ),
				array( 'Mail', 'Website forms in the Mail inbox, the campaign strip under signatures (MailWeb).', $link( 'Mail', 'admin.php?page=mailweb' ), $auto ),
			),
			'Pictures and media'     => array(
				array( 'Card pictures', 'The article\'s own preview picture or a placard; free CC0 photos when a publisher\'s picture is missing; thumbnails never repeat.', $link( 'Media', 'upload.php' ), $auto ),
				array( 'Press coverage and sponsored blocks', 'Coverage of Animal Writes on /media/; the Sponsored block group.', $link( 'Press coverage', 'edit.php?post_type=aw_press' ), $auto ),
			),
			'Running the site'       => array(
				function_exists( 'aw_team_dates_moved' ) && aw_team_dates_moved()
					? array( 'Dates', 'Dates moved to PlanWeb: the team\'s calendar, plans and reminders, and the Next 7 days dashboard box.', $link( 'Plugins → PlanWeb → Calendar', 'admin.php?page=planweb' ), $st['dates'] )
					: array( 'Dates', 'The team\'s calendar (observance days, deadlines) and the Dates dashboard widget.', $link( 'Dates', 'admin.php?page=aw-dates' ), $st['dates'] ),
				array( 'Check site', 'Loads one page of every kind and reports any that do not open cleanly; also in the admin bar.', $admin ? $link( 'Appearance → Kits → Home → Page check', $url( 'aw-site-check' ) ) : 'Administrators', $st['check'] ),
				array( 'Contact links', 'The "Elsewhere" links on the Contact page.', $link( 'Contact links', $url( 'aw-elsewhere' ) ), $auto ),
				array( 'Keys', 'Each plugin keeps its own service keys on its own Settings (InsightWeb: Clarity, Bing Webmaster, PageSpeed, Google Alerts): paste once, never shown again; a wp-config.php constant wins.', $admin ? $link( 'InsightWeb → Settings → Keys', 'admin.php?page=aw-search-insights&tab=settings' ) : 'Administrators', $auto ),
				array( 'Edit screens', 'Every box on each edit screen (WordPress, plugins and ours), the keys it stores and where each key is used.', $admin ? $link( 'Edit screens', $url( 'aw-edit-screens' ) ) : 'Administrators', $auto ),
				array( 'To review', 'Items waiting for a person (from SourceWeb).', $link( 'Plugins → SourceWeb → To review', 'admin.php?page=aw-review' ), $st['review'] ),
				array( 'Scheduled tasks', 'Who triggers WP-Cron (Hostinger, GitHub, UptimeRobot) and the longest gap; dashboard widget.', $link( 'Dashboard', 'index.php' ), $st['cron'] ),
				array( 'Daily backup', 'Database and deployed code into ~/backups on the server, kept 14 days.', $admin ? $link( 'UpdraftPlus', 'options-general.php?page=updraftplus' ) : '—', $st['backup'] ),
				array( 'Deploys and cache', 'LiteSpeed purged once after each deploy; CDN lifetime for anonymous public pages.', $admin ? $link( 'CDN cache times', $url( 'cdn' ) ) : '—', $st['deploy'] . ' ' . $st['cdn'] ),
				array( 'Staging', 'staging.animalwrites.in: same code, database without personal data, marked in the admin bar.', '—', $st['stage'] ),
			),
		);

		return array(
			'slug'     => 'animal-writes',
			'title'    => 'Animal Writes',
			'intro'    => 'The site\'s own plugin: its content model, Directory, campaigns, forms and the jobs that keep it running. Each row says where to find the feature.',
			'groups'   => $groups,
			'widgets'  => array(
				array(
					'widget' => 'Dates',
					'what'   => 'The next dates in the team calendar.',
				),
				array(
					'widget' => 'Scheduled tasks',
					'what'   => 'WP-Cron triggers in the last 24 hours (administrators).',
				),
				array(
					'widget' => 'Campaign letters',
					'what'   => 'Letters sent per campaign.',
				),
			),
		);
	}
);

// Kits → Help → Docs → Commands: this site's commands are named aw and aw-… too (read from WP-CLI, cli-commands.php).
add_filter( 'kits_cli_command_prefixes', fn( $p ) => array_merge( (array) $p, array( 'aw' ) ) );
