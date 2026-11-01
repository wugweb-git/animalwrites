<?php
/**
 * AW SEO Plus → Settings (owner, 25 Oct 2026: settings per module, like Rank Math's). Loaded first.
 *
 * aw_seo_modules() lists every module: label, whether it can be switched off, the files it loads, and its settings.
 * Each setting is one of the plugin's former constants; its default is the value the code had, so nothing changes
 * until a setting is saved. The constants are defined here from option aw_seo_settings ({module: {CONST: value, on}}).
 * Foundation modules (General, Titles, Canonicals, Robots, Filtered lists, Schema) are always on: the others call them.
 * A module switched off is not loaded (aw-seo-extras.php). Core WordPress only (Settings API, form-table, nav tabs).
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SEO_SETTINGS = 'aw_seo_settings';

/** Every module and its settings. */
function aw_seo_modules(): array {
	static $m = null;
	if ( null !== $m ) {
		return $m;
	}
	$m = array(
		'general' => array(
			'label'  => 'General',
			'switch' => false,
			'files'  => array( 'helpers' ),
			'desc'   => 'Shared rules: description length, the section hubs\' titles and descriptions, share images.',
			'fields' => array(
				'AW_SEO_DESC_MAX' => array( 'type' => 'int', 'label' => 'Longest description (characters)', 'help' => 'Search descriptions are cut at a word before this.', 'default' => 155 ),
				'AW_HUB_TITLES' => array( 'type' => 'map', 'label' => 'Section hub titles', 'help' => 'One per line: post type | title.', 'default' => array(
						'aw_news'        => 'Animal Welfare News from India – Animal Writes',
						'aw_legal'       => 'Animal Laws and Court Orders in India – Animal Writes',
						'post'           => 'Animal Welfare Guides & Research in India – Animal Writes',
						'aw_listing'     => 'Directory of 5,000+ Animal Welfare NGOs in India',
						'aw_opportunity' => 'Animal Welfare Volunteering and Jobs in India',
						'aw_press'       => 'Animal Writes in the News',
						'aw_campaign'    => 'Animal Welfare Campaigns in India – Animal Writes',
					) ),
				'AW_HUB_DESCRIPTIONS' => array( 'type' => 'map', 'label' => 'Section hub descriptions', 'help' => 'One per line: post type | description. %s is the number of listings (Directory).', 'default' => array(
						'aw_opportunity' => 'Volunteer, foster, intern or find a job with animal welfare organisations across India. Each opportunity lists its role, location and how to apply.',
						'aw_press'       => 'Press coverage of Animal Writes campaigns for animals in India: news articles, videos and social posts.',
						'aw_listing'     => 'Find %s animal welfare NGOs, shelters, rescuers, Gaushalas and government vets in every state and district of India, with phone, address and services.',
					) ),
				'AW_ARCHIVE_DESCRIPTIONS' => array( 'type' => 'map', 'label' => 'Archive descriptions', 'help' => 'One per line: post type | description.', 'default' => array(
						'aw_news'        => 'Animal-welfare news from across India, summarised with a link to every source.',
						'aw_legal'       => 'Court orders, notifications and rules that affect animals in India, explained in plain words.',
						'post'           => 'Practical guides: what to do for an injured animal, how to report cruelty, Animal Birth Control and more.',
						'aw_campaign'    => 'Citizen campaigns for animals in India: what each one asks for, how to take part and what it achieved.',
						'aw_opportunity' => 'Volunteer, foster, internship and job opportunities in animal welfare in India.',
						'aw_listing'     => 'Animal welfare organisations, Gaushalas and government vets across India.',
					) ),
				'AW_SEO_LISTING_SHARE_IMAGE' => array( 'type' => 'int', 'label' => 'Listing share image (attachment ID)', 'help' => 'Used when a listing has no logo of its own.', 'default' => 8814 ),
				'AW_SEO_SOURCE_IMAGE_TYPES' => array( 'type' => 'list', 'label' => 'Types that share the publisher\'s image', 'help' => 'One post type per line.', 'default' => array( 'aw_news', 'aw_legal', 'post', 'aw_opportunity' ) ),
			),
		),
		'titles' => array(
			'label'  => 'Titles and descriptions',
			'switch' => false,
			'files'  => array( 'titles', 'programmatic' ),
			'desc'   => 'Search titles and descriptions of items and of list pages (hubs, Directory places, categories, tags), built from their own counts and places.',
			'fields' => array(
				'AW_SEO_ITEM_TYPES' => array( 'type' => 'list', 'label' => 'Item types', 'help' => 'Post types whose items get titles, descriptions and focus keywords. One per line.', 'default' => array( 'aw_listing', 'aw_news', 'post', 'aw_legal', 'aw_press', 'page', 'aw_opportunity', 'aw_campaign' ) ),
				'AW_SEO_BREAKDOWN' => array( 'type' => 'list', 'label' => 'Directory place breakdown', 'help' => 'Listing types counted in a place\'s description, in this order. One tag slug per line.', 'default' => array( 'animal-welfare-organisation', 'gaushala', 'veterinary', 'abc-centre', 'wildlife-rescue' ) ),
				'AW_SEO_TERM_TITLES' => array( 'type' => 'map', 'label' => 'Category and tag titles', 'help' => 'One per line: taxonomy:slug | title. Others get the rule-based title.', 'default' => array(
						'category:adoption' => 'Animal Adoption News and Guides for India',
						'category:animal-birth-control' => 'ABC Animal Birth Control Rules and News',
						'category:transport-slaughter' => 'Animal Transport and Slaughter in India',
						'category:birds' => 'Bird Rescue and Welfare News from India',
						'category:campaigns-civic-action' => 'Animal Welfare Campaigns and Civic Action',
						'category:cattle-gaushalas' => 'Cattle Welfare and Gaushala News in India',
						'category:community-dogs' => 'Laws and News on Stray and Community Dogs',
						'category:courts-judgments' => 'Animal Welfare Court Cases and Judgments',
						'category:cruelty-prosecution' => 'Animal Cruelty Cases and Prosecution News',
						'category:disaster-rescue' => 'Animal Rescue in Disasters and Emergencies',
						'category:feeders-caregivers' => 'Rights of People Feeding Street Animals',
						'category:laws-rules' => 'Animal Protection Laws and Rules in India',
						'category:performing-captive-animals' => 'Performing and Captive Animals in India',
						'category:pets-breeding' => 'Pet Shop and Dog Breeding Rules in India',
						'category:policy-government' => 'Animal Welfare Policy and Government News',
						'category:rabies-public-health' => 'Rabies Control and Public Health in India',
						'category:veterinary-care' => 'Veterinary Care and Animal Health in India',
						'category:wildlife' => 'Wildlife Protection and Rescue in India',
						'post_tag:abc-centre' => 'ABC Centres by State and District',
						'post_tag:animal-welfare-organisation' => 'Animal Welfare NGOs by State and District',
						'post_tag:gaushala' => 'Gaushalas by State and District',
						'post_tag:veterinary-office' => 'Government Veterinary Offices by State and District', // "Veterinary", not "Vet" (owner, 20 Oct 2026)
						'post_tag:mobile-veterinary-unit' => '1962 Mobile Veterinary Units by State and District',
						'post_tag:veterinary-dispensary' => 'Veterinary Dispensaries by State and District',
						'post_tag:veterinary-hospital' => 'Veterinary Hospitals by State and District',
						'post_tag:wildlife-rescue' => 'Wildlife Rescue Groups by State and District',
						'post_tag:veterinary-clinic' => 'Veterinary Clinics by State and District',
						'post_tag:bird-hospital' => 'Bird Hospitals by State and District',
						'post_tag:animal-shelter' => 'Animal Shelters by State and District',
						'post_tag:animal-ambulance' => 'Animal Ambulances by State and District',
						'post_tag:adoption-network' => 'Adoption Networks by State and District',
						'post_tag:emergency-rescue' => 'Emergency Rescue by State and District',
						'post_tag:feeder-network' => 'Feeder Networks by State and District',
						'post_tag:legal-aid' => 'Free Legal Aid (DLSA) by State and District',
						'post_tag:rescue-group' => 'Animal Rescue Groups by State and District',
						'post_tag:sanctuary' => 'Animal Sanctuaries by State and District',
						'post_tag:animal-welfare' => 'NGOs Doing Animal Welfare Work in India', // owner-approved 20 Oct 2026
						'post_tag:bird-treatment' => 'NGOs Treating Injured Birds across India',
						'post_tag:adoption' => 'NGOs Offering Animal Adoption across India',
						'post_tag:ambulance' => 'NGOs Running Animal Ambulances in India',
						'post_tag:boarding-foster' => 'NGOs Offering Pet Boarding and Fostering',
						'post_tag:cattle-care' => 'NGOs Offering Cattle Care in Indian States',
						'post_tag:feeding' => 'NGOs Feeding Street Animals across India',
						'post_tag:legal-help' => 'NGOs Offering Animal Legal Help in India',
						'post_tag:rescue' => 'NGOs Offering Animal Rescue across India',
						'post_tag:shelter' => 'NGOs Offering Animal Shelter across India',
						'post_tag:sterilisation' => 'NGOs Offering ABC Sterilisation in India',
						'post_tag:treatment' => 'NGOs Treating Injured Animals across India',
						'post_tag:vaccination' => 'NGOs Offering Animal Vaccination in India',
						'post_tag:act' => 'Animal Welfare Acts of India Summarised',
						'post_tag:advisory' => 'Animal Welfare Advisories Issued in India',
						'post_tag:circular' => 'Animal Welfare Circulars Issued in India',
						'post_tag:judgment' => 'Animal Welfare Court Judgments in India',
						'post_tag:notification' => 'Government Notifications on Animal Welfare',
						'post_tag:official-release' => 'Official Press Releases on Animal Welfare',
						'post_tag:order' => 'Court Orders on Animal Welfare in India',
						'post_tag:rule' => 'Animal Welfare Rules of India Summarised',
						'post_tag:template' => 'Letter Templates for Animal Welfare Cases',
						'post_tag:event' => 'Animal Welfare Event Listings for India',
						'post_tag:fellowship' => 'Animal Welfare Fellowship Opportunities',
						'post_tag:foster' => 'Animal Fostering Opportunities in India',
						'post_tag:grant' => 'Grants for Animal Welfare Work in India',
						'post_tag:internship' => 'Animal Welfare Internship Opportunities',
						'post_tag:job' => 'Jobs in Animal Welfare NGOs across India',
						'post_tag:volunteer' => 'Animal Welfare Volunteer Roles in India',
						'post_tag:faq' => 'Animal Welfare FAQs and Answers on the Law',
						'post_tag:guide' => 'Practical Animal Welfare Guides for India',
						'post_tag:research' => 'Animal Welfare Research Studies from India',
					) ),
			),
		),
		'canonical' => array(
			'label'  => 'Canonicals',
			'switch' => false,
			'files'  => array( 'canonical' ),
			'desc'   => 'Canonical addresses Rank Math cannot work out: hub pages, Directory places, filtered lists.',
			'fields' => array(
				'AW_FILTER_PARAMS' => array( 'type' => 'list', 'label' => 'Filter parameters', 'help' => 'Query parameters that make a filtered list. One per line.', 'default' => array( 'tag', 'state', 'district', 'q', 'category_name', 'verified', 'gov', 'sort', 'publisher', 'court', 'issuer' ) ),
			),
		),
		'robots' => array(
			'label'  => 'Robots rules',
			'switch' => false,
			'files'  => array( 'robots' ),
			'desc'   => 'Noindex rules that depend on an item\'s state or a list\'s size.',
			'fields' => array(
				'AW_THIN_DISTRICT_TYPE' => array( 'type' => 'int', 'label' => 'Thin Directory page (listings or fewer)', 'help' => 'A district × type page with this many listings or fewer is noindex (an empty page always is).', 'default' => 1 ),
				'AW_DIRECTORY_KEEP_INDEXED' => array( 'type' => 'list', 'label' => 'Thin pages kept indexed', 'help' => 'Paths under /directory/ that stay indexed anyway. One per line.', 'default' => array(
						'haryana/jhajjar/animal-welfare-organisation',
						'madhya-pradesh/sheopur/animal-welfare-organisation',
						'maharashtra/pune/animal-welfare-organisation',
						'maharashtra/pune/wildlife-rescue',
						'telangana/kumuram-bheem-asifabad/veterinary-office',
						'telangana/mancherial/veterinary-office',
						'telangana/medchal-malkajgiri/veterinary-office',
						'telangana/nizamabad/animal-welfare-organisation',
						'uttar-pradesh/chandauli/veterinary-office',
						'uttar-pradesh/gautam-buddha-nagar/animal-welfare-organisation',
						'uttar-pradesh/kushinagar/animal-welfare-organisation',
						'uttar-pradesh/mahrajganj/animal-welfare-organisation',
						'uttar-pradesh/siddharthnagar/gaushala',
						'uttarakhand/chamoli/animal-welfare-organisation',
						'west-bengal/purba-medinipur/veterinary-office',
					) ),
			),
		),
		'filtered' => array(
			'label'  => 'Filtered lists',
			'switch' => false,
			'files'  => array( 'filtered-views' ),
			'desc'   => 'A hub filtered by one category or tag is a page of its own (title, description, canonical, sitemap) from a minimum number of items.',
			'fields' => array(
				'AW_FILTERED_MIN_ITEMS' => array( 'type' => 'int', 'label' => 'Fewest items for an indexed filtered list', 'help' => 'Below this a filtered list is noindex.', 'default' => 3 ),
				'AW_FILTERED_CATEGORY_HUBS' => array( 'type' => 'list', 'label' => 'Hubs with filtered pages', 'help' => 'One post type per line.', 'default' => array( 'aw_news', 'aw_legal', 'post', 'aw_opportunity', 'aw_campaign' ) ),
				'AW_FILTERED_TITLE_WORDS' => array( 'type' => 'maplist', 'label' => 'Filtered list titles', 'help' => 'One per line: post type | title | shorter title …, %s is the category or tag.', 'default' => array(
						'aw_news'        => array( '%s News from India', '%s News' ),
						'aw_legal'       => array( '%s Laws and Court Orders in India', '%s Laws and Court Orders', '%s Library Documents in India', '%s Library Documents' ),
						'post'           => array( '%s Guides and Research', '%s Resources' ),
						'aw_opportunity' => array( '%s Volunteer Roles and Jobs', '%s Opportunities' ),
						'aw_campaign'    => array( '%s Campaigns', 'Campaigns on %s' ),
					) ),
				'AW_FILTERED_STATE_NOINDEX' => array( 'type' => 'list', 'label' => 'Hubs whose state views are noindex', 'help' => 'One post type per line.', 'default' => array( 'aw_news', 'aw_legal', 'aw_opportunity' ) ),
			),
		),
		'schema' => array(
			'label'  => 'Schema',
			'switch' => false,
			'files'  => array( 'schema' ),
			'desc'   => 'The site\'s own JSON-LD nodes in Rank Math\'s graph (organisations, Library documents, campaigns, opportunities, lists).',
			'fields' => array(
				'AW_SITE_ALT_NAMES' => array( 'type' => 'list', 'label' => 'Other names of the site', 'help' => 'The WebSite node\'s alternateName. One per line.', 'default' => array( 'AnimalWrites', 'animalwrites.in' ) ),
			),
		),
		'faq' => array(
			'label'  => 'FAQ',
			'switch' => true,
			'files'  => array( 'faq' ),
			'desc'   => 'The FAQ box on the edit screen of every public post type and taxonomy (which ones: AW SEO Plus → FAQ), and the questions and answers with FAQPage schema on the page. Automatic questions from a page\'s own fields fill the box where the site provides them.',
			'fields' => array(
				'AW_FAQ_MIN' => array( 'type' => 'int', 'label' => 'Fewest questions (pages and every other type)', 'help' => 'Fewer than this: no FAQ and no schema.', 'default' => 3 ),
				'AW_FAQ_MIN_GUIDE' => array( 'type' => 'int', 'label' => 'Fewest questions on a post (Resources guides)', 'help' => '', 'default' => 2 ),
				'AW_FAQ_MAX' => array( 'type' => 'int', 'label' => 'Most questions shown', 'help' => '', 'default' => 6 ),
				'AW_FAQ_WORDS' => array( 'type' => 'int', 'label' => 'Longest answer (words)', 'help' => 'Longer answers are flagged by the checks.', 'default' => 50 ),
				'AW_FAQ_HUBS' => array( 'type' => 'map', 'label' => 'Pages with a subject in the FAQ heading', 'help' => 'One per line: page slug | subject (the heading: "Questions about <subject>"). Their FAQ sits under the list, wide. Other pages: the page title.', 'default' => array(
						'directory'     => 'the Directory',
						'volunteer'     => 'volunteering',
						'claim'         => 'claiming a listing',
						'opportunities' => 'Opportunities',
					) ),
			),
		),
		'search-insights' => array(
			'label'  => 'Search Insights',
			'switch' => true,
			'files'  => array( 'search-insights' ),
			'desc'   => 'Keywords, Search Console and GA4 figures, rising terms, pages, the check list and imports. Read-only.',
			'fields' => array(
				'AW_SI_HIGH_TRENDS' => array( 'type' => 'int', 'label' => 'High demand: Trends at least', 'help' => '', 'default' => 20 ),
				'AW_SI_HIGH_IMPR' => array( 'type' => 'int', 'label' => 'High demand: impressions in 28 days at least', 'help' => '', 'default' => 50 ),
				'AW_SI_QUICK_MIN' => array( 'type' => 'int', 'label' => 'Quick win: impressions at least', 'help' => 'At position 8 to 20.', 'default' => 10 ),
				'AW_SI_MOVERS' => array( 'type' => 'int', 'label' => 'Listing names shown one by one', 'help' => 'The rest are one "entity" row.', 'default' => 20 ),
				'AW_SI_PSI_FLAG' => array( 'type' => 'int', 'label' => 'PageSpeed mobile score that flags a page (under)', 'help' => '', 'default' => 50 ),
				'AW_SI_RM_LOW' => array( 'type' => 'int', 'label' => 'Rank Math SEO score that is "low" (under)', 'help' => '', 'default' => 50 ),
				'AW_SI_KEEP_DAYS' => array( 'type' => 'int', 'label' => 'Daily figures kept (days)', 'help' => 'Older days become monthly totals.', 'default' => 90 ),
				'AW_SI_SPIKE_RATIO' => array( 'type' => 'int', 'label' => 'Sourcing spike: times the usual', 'help' => '', 'default' => 3 ),
				'AW_SI_SPIKE_MIN' => array( 'type' => 'int', 'label' => 'Sourcing spike: items that day at least', 'help' => '', 'default' => 3 ),
				'AW_SI_SPIKE_WINDOW' => array( 'type' => 'int', 'label' => 'Sourcing spike: usual = average of days', 'help' => '', 'default' => 14 ),
			),
		),
		'rank-math-schema' => array(
			'label'  => 'Rank Math schema entries',
			'switch' => true,
			'files'  => array( 'rank-math-schema' ),
			'desc'   => 'Rank Math\'s own schema entries naming each item\'s real schema type, so its screens show it.',
			'fields' => array(
				'AW_RM_SCHEMA_TYPES' => array( 'type' => 'list', 'label' => 'Post types', 'help' => 'One per line.', 'default' => array( 'page', 'aw_legal', 'post', 'aw_campaign', 'aw_news', 'aw_opportunity', 'aw_listing' ) ),
			),
		),
		'opengraph' => array(
			'label'  => 'Share images and Open Graph',
			'switch' => true,
			'files'  => array( 'opengraph' ),
			'desc'   => 'Share images and texts Rank Math\'s settings cannot express; a choice made in Rank Math wins.',
			'fields' => array(
				'AW_SEO_ARTICLE_TYPES' => array( 'type' => 'list', 'label' => 'Types shared as articles', 'help' => 'One post type per line.', 'default' => array( 'aw_news', 'aw_legal', 'post', 'aw_campaign' ) ),
			),
		),
		'sitemap' => array(
			'label'  => 'Directory sitemap',
			'switch' => true,
			'files'  => array( 'sitemap' ),
			'desc'   => 'The Directory\'s place pages in Rank Math\'s sitemap.',
			'fields' => array(
			),
		),
		'redirects' => array(
			'label'  => 'Redirects',
			'switch' => true,
			'files'  => array( 'redirects' ),
			'desc'   => 'Clean-up redirects and author archives; forms, merges and imports add to Rank Math\'s Redirections.',
			'fields' => array(
			),
		),
		'breadcrumbs' => array(
			'label'  => 'Breadcrumbs',
			'switch' => true,
			'files'  => array( 'breadcrumbs' ),
			'desc'   => 'The breadcrumb trail for places and hubs.',
			'fields' => array(
			),
		),
		'focus-keywords' => array(
			'label'  => 'Focus keywords',
			'switch' => true,
			'files'  => array( 'focus-keywords' ),
			'desc'   => 'Listings get their name as focus keyword; the workers\' job fills the rest through the site\'s API.',
			'fields' => array(
				'AW_SEO_GENERIC_WORDS' => array( 'type' => 'list', 'label' => 'Words that alone make no keyword', 'help' => 'One per line.', 'default' => array( 'animal', 'animals', 'welfare', 'india', 'indian', 'save', 'help', 'news', 'care', 'update', 'updates', 'issue', 'case', 'story' ) ),
			),
		),
		'health' => array(
			'label'  => 'Sitemap and llms.txt check',
			'switch' => true,
			'files'  => array( 'health' ),
			'desc'   => 'Hourly: both must answer; flush once, then email the admin and show a notice.',
			'fields' => array(
			),
		),
		'llms' => array(
			'label'  => 'llms.txt',
			'switch' => true,
			'files'  => array( 'llms' ),
			'desc'   => 'llms.txt order, descriptions and the Directory section (Rank Math writes the file).',
			'fields' => array(
				'AW_LLMS_PAGE_ORDER' => array( 'type' => 'list', 'label' => 'Page order', 'help' => 'Page slugs in order, one per line; "/" is the home page.', 'default' => array( '', 'news', 'library', 'resources', 'directory', 'opportunities', 'campaigns', 'press-coverage', 'categories', 'about', 'editorial-policy', 'corrections', 'contact', 'privacy-policy', 'terms', 'disclaimer', 'volunteer', 'claim' ) ),
				'AW_LLMS_GUIDE_TYPES' => array( 'type' => 'list', 'label' => 'Guide tags', 'help' => 'Tags whose Resources items are listed as guides. One per line.', 'default' => array( 'guide', 'faq' ) ),
			),
		),
		'analysis' => array(
			'label'  => 'Content analysis',
			'switch' => true,
			'files'  => array( 'analysis' ),
			'desc'   => 'Rank Math\'s analyzer reads the published page for Pages built from blocks.',
			'fields' => array(
				'AW_SEO_RENDERED_TYPES' => array( 'type' => 'list', 'label' => 'Types analysed as rendered', 'help' => 'One post type per line.', 'default' => array( 'aw_legal', 'post', 'aw_campaign', 'aw_news', 'aw_opportunity', 'aw_listing' ) ),
				'AW_SEO_SUMMARY_SKIP_TESTS' => array( 'type' => 'list', 'label' => 'Rank Math tests skipped on summaries', 'help' => 'One test ID per line.', 'default' => array( 'lengthContent', 'keywordDensity', 'keywordInSubheadings', 'contentHasTOC' ) ),
			),
		),
	);
	return $m;
}

/** Saved settings (all modules). */
function aw_seo_saved(): array {
	return (array) get_option( AW_SEO_SETTINGS, array() );
}

/** Whether a module is on (foundation modules always are). */
function aw_seo_module_on( string $key ): bool {
	$mods = aw_seo_modules();
	if ( empty( $mods[ $key ]['switch'] ) ) {
		return true;
	}
	$saved = aw_seo_saved();
	return ! isset( $saved[ $key ]['on'] ) || ! empty( $saved[ $key ]['on'] );
}

/** Files a switched-off module would load: aw-seo-extras.php skips them. */
function aw_seo_files_off(): array {
	$off = array();
	foreach ( aw_seo_modules() as $key => $mod ) {
		if ( ! aw_seo_module_on( $key ) ) {
			$off = array_merge( $off, $mod['files'] );
		}
	}
	return $off;
}

// The constants, from the saved settings or the defaults.
foreach ( aw_seo_modules() as $aw_seo_key => $aw_seo_mod ) {
	$aw_seo_saved = aw_seo_saved()[ $aw_seo_key ] ?? array();
	foreach ( $aw_seo_mod['fields'] as $aw_seo_const => $aw_seo_field ) {
		if ( ! defined( $aw_seo_const ) ) {
			define( $aw_seo_const, array_key_exists( $aw_seo_const, $aw_seo_saved ) ? $aw_seo_saved[ $aw_seo_const ] : $aw_seo_field['default'] );
		}
	}
}
unset( $aw_seo_key, $aw_seo_mod, $aw_seo_saved, $aw_seo_const, $aw_seo_field );

/* ---------- Text in the form and back ---------- */

/** A value as the text in its field. */
function aw_seo_field_text( string $type, $value ): string {
	switch ( $type ) {
		case 'int':
			return (string) (int) $value;
		case 'list':
			return implode( "\n", array_map( fn( $v ) => '' === (string) $v ? '/' : (string) $v, (array) $value ) );
		case 'map':
			return implode( "\n", array_map( fn( $k, $v ) => $k . ' | ' . $v, array_keys( (array) $value ), (array) $value ) );
		case 'maplist':
			return implode( "\n", array_map( fn( $k, $v ) => $k . ' | ' . implode( ' | ', (array) $v ), array_keys( (array) $value ), (array) $value ) );
	}
	return '';
}

/** The text of a field as its value. */
function aw_seo_field_value( string $type, string $text ) {
	$lines = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $text ) ), 'strlen' ) );
	switch ( $type ) {
		case 'int':
			return max( 0, (int) $text );
		case 'list':
			return array_map( fn( $l ) => '/' === $l ? '' : sanitize_text_field( $l ), $lines );
		case 'map':
		case 'maplist':
			$out = array();
			foreach ( $lines as $l ) {
				$parts = array_map( 'trim', explode( '|', $l ) );
				$k     = sanitize_text_field( (string) array_shift( $parts ) );
				if ( '' === $k || ! $parts ) {
					continue;
				}
				$parts     = array_map( 'sanitize_text_field', $parts );
				$out[ $k ] = 'map' === $type ? implode( ' | ', $parts ) : $parts;
			}
			return $out;
	}
	return null;
}

/* ---------- AW SEO Plus → Settings ---------- */

add_action(
	'admin_menu',
	function () {
		add_submenu_page( AW_SEO_MENU, 'AW SEO Plus settings', 'Settings', 'manage_options', 'aw-seo-settings', 'aw_seo_settings_page' );
	},
	200 // last under AW SEO Plus
);

/** Settings tab address. */
function aw_seo_settings_url( string $module = 'general' ): string {
	return add_query_arg( array( 'page' => 'aw-seo-settings', 'tab' => $module ), admin_url( 'admin.php' ) );
}

/** The current tab. */
function aw_seo_settings_tab(): string {
	$tab = sanitize_key( wp_unslash( $_GET['tab'] ?? 'general' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- navigation only
	return isset( aw_seo_modules()[ $tab ] ) ? $tab : 'general';
}

function aw_seo_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.', 403 );
	}
	$mods  = aw_seo_modules();
	$tab   = aw_seo_settings_tab();
	$mod   = $mods[ $tab ];
	$saved = aw_seo_saved()[ $tab ] ?? array();
	echo '<div class="wrap"><h1>AW SEO Plus settings</h1>';
	if ( isset( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success is-dismissible"><p>' . ( 'reset' === $_GET['saved'] ? 'Defaults restored.' : 'Settings saved.' ) . ' A module switched on or off takes effect on the next page load.</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification
	}
	echo '<nav class="nav-tab-wrapper" aria-label="Modules">';
	foreach ( $mods as $k => $m ) {
		$off = ! aw_seo_module_on( $k ) ? ' (off)' : '';
		echo '<a class="nav-tab' . ( $k === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( aw_seo_settings_url( $k ) ) . '"' . ( $k === $tab ? ' aria-current="page"' : '' ) . '>' . esc_html( $m['label'] . $off ) . '</a>';
	}
	echo '</nav>';
	echo '<p>' . esc_html( $mod['desc'] ) . '</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="aw_seo_settings"><input type="hidden" name="module" value="' . esc_attr( $tab ) . '">';
	wp_nonce_field( 'aw_seo_settings_' . $tab );
	echo '<table class="form-table" role="presentation"><tbody>';
	if ( ! empty( $mod['switch'] ) ) {
		echo '<tr><th scope="row">Module</th><td><label><input type="checkbox" name="on" value="1"' . checked( aw_seo_module_on( $tab ), true, false ) . '> ' . esc_html( $mod['label'] ) . ' is on</label></td></tr>';
	} else {
		echo '<tr><th scope="row">Module</th><td>Always on: other modules use it.</td></tr>';
	}
	foreach ( $mod['fields'] as $const => $f ) {
		$value = array_key_exists( $const, $saved ) ? $saved[ $const ] : $f['default'];
		$id    = 'aw-seo-' . strtolower( str_replace( '_', '-', $const ) );
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $f['label'] ) . '</label></th><td>';
		if ( 'int' === $f['type'] ) {
			echo '<input type="number" min="0" class="small-text" id="' . esc_attr( $id ) . '" name="f[' . esc_attr( $const ) . ']" value="' . esc_attr( aw_seo_field_text( 'int', $value ) ) . '">';
		} else {
			$rows = min( 16, max( 3, count( (array) $value ) + 1 ) );
			echo '<textarea class="large-text code" rows="' . (int) $rows . '" id="' . esc_attr( $id ) . '" name="f[' . esc_attr( $const ) . ']">' . esc_textarea( aw_seo_field_text( $f['type'], $value ) ) . '</textarea>';
		}
		$changed = array_key_exists( $const, $saved ) && $saved[ $const ] !== $f['default'];
		echo '<p class="description">' . esc_html( trim( $f['help'] . ( $changed ? ' Changed from the default.' : '' ) ) ) . '</p></td></tr>';
	}
	echo '</tbody></table>';
	submit_button( 'Save changes', 'primary', 'submit', false );
	if ( $saved ) {
		echo ' ';
		submit_button( 'Restore defaults', 'secondary', 'reset', false );
	}
	echo '</form></div>';
}

add_action(
	'admin_post_aw_seo_settings',
	function () {
		$tab  = sanitize_key( wp_unslash( $_POST['module'] ?? '' ) );
		$mods = aw_seo_modules();
		if ( ! isset( $mods[ $tab ] ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		check_admin_referer( 'aw_seo_settings_' . $tab );
		$all = aw_seo_saved();
		if ( isset( $_POST['reset'] ) ) {
			unset( $all[ $tab ] );
			$done = 'reset';
		} else {
			$new = array();
			if ( ! empty( $mods[ $tab ]['switch'] ) ) {
				$new['on'] = ! empty( $_POST['on'] );
			}
			$posted = (array) wp_unslash( $_POST['f'] ?? array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field is sanitised by its type below
			foreach ( $mods[ $tab ]['fields'] as $const => $f ) {
				if ( ! isset( $posted[ $const ] ) ) {
					continue;
				}
				$value = aw_seo_field_value( $f['type'], (string) $posted[ $const ] );
				if ( $value !== $f['default'] ) { // store only what differs, so a later default change reaches unchanged fields
					$new[ $const ] = $value;
				}
			}
			$all[ $tab ] = $new;
			$done        = 'saved';
		}
		update_option( AW_SEO_SETTINGS, array_filter( $all ), false );
		wp_safe_redirect( add_query_arg( 'saved', $done, aw_seo_settings_url( $tab ) ) );
		exit;
	}
);
