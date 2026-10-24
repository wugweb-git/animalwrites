<?php
/**
 * Tools → Search Insights (15 Oct 2026; called Search demand until 24 Oct 2026, the address and option keep that name):
 * what people search and how Animal Writes shows up for it.
 *
 * - One table (owner, 15 Oct 2026): every Search Console query of the latest 3 months merged with the keyword map's
 *   target phrases, ranked by Importance; Gaps sit collapsed under it. No filters, no CSV.
 * - Search Console (last 28 days and last 3 months) comes through Site Kit's own search-console module, the same
 *   call the 15 Oct research used. It refreshes daily by WP-Cron (19 Oct 2026; weekly before, so a reload showed
 *   week-old figures) and on "Refresh now". Site Kit keeps the Google
 *   connection; this file never reads its tokens.
 * - The keyword map, gaps, Google Trends, autocomplete and Wikipedia figures are a fixed snapshot of
 *   docs/seo/keyword-map.md and docs/seo/keyword-demand-2026-10-15.md (bump AW_SD_STATIC_VERSION when they change).
 * - Everything sits in one option, aw_search_demand (not autoloaded). Since 24 Oct 2026 it also keeps each page's
 *   clicks and impressions per period (pages), for the per-keyword sourcing figures.
 * - Rising (24 Oct 2026): a search with at least 5 impressions in the last 28 days whose 28 days hold at least twice
 *   its 3-month average for 28 days (new searches qualify).
 * - With AW Sourcing (soft link, either may be off): the sourcing keywords table here (filter aw_sourcing_keyword_items),
 *   and the searches with demand offered to Settings → Sourcing as suggestions (filter aw_search_demand_terms).
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SD_OPTION         = 'aw_search_demand';
const AW_SD_CRON           = 'aw_search_demand_refresh';
const AW_SD_STATIC_VERSION = 1;

/** Brand searches: the site's own names and addresses (not the generic "animal rights"). */
const AW_SD_BRAND = '/animal\s*-?\s*writ|animalwrit|animal\s*-?\s*rights?\s*\.\s*in|animalrights?\.in|india\s*writes/i';

/** The fixed part of the snapshot, from the two 15 Oct 2026 documents. */
function aw_sd_static(): array {
	return array(
		'version'     => AW_SD_STATIC_VERSION,
		'pulled'      => '2026-10-15',
		'sources'     => array(
			'trends'       => array( 'Google Trends (India, past 12 months, "animal welfare" = 100)', '2026-10-15', 'Snapshot. Re-run by hand each October and when the Supreme Court hears the stray dogs case.' ),
			'autocomplete' => array( 'Google autocomplete (India, English), checked against Bing', '2026-10-15', 'Snapshot: 48 seed phrases and 130 map keywords.' ),
			'wikipedia'    => array( 'Wikipedia pageviews (English, Oct 2025 – Sep 2026)', '2026-10-15', 'Snapshot. A rough guide to subject interest.' ),
			'map'          => array( 'Keyword map (docs/seo/keyword-map.md)', '2026-10-15', 'Snapshot of the Demand column and section 8.' ),
		),
		// Search Console figures in the research report: 17 Nov 2025 – 14 Oct 2026 (named queries only for brand split).
		'gsc_snapshot' => array(
			'label'    => '17 Nov 2025 – 14 Oct 2026 (research, 15 Oct 2026)',
			'clicks'   => 20589,
			'impr'     => 46447,
			'brand'    => array( 16880, 22108 ),
			'nonbrand' => array( 78, 4600 ),
		),
		// Section, page, target phrase, Trends, autocomplete, Search Console.
		'map'         => array(
			array( 'Home', 'Home', 'animal welfare in india', 'T 4 ("animal welfare india" T 14, mostly the Animal Welfare Board)', 'Y', 'No or nearly no impressions' ),
			array( 'Hub', '/news/', 'animal welfare news', 'T 5, rising +1,500% ("animal news" T 136)', 'Y', 'Hub 30 impr' ),
			array( 'Hub', '/library/', 'animal laws in india', 'T 2; "supreme court stray dogs" T 16', 'Y (top: "pdf")', 'Hub 48 impr, pos 4.3; rules by name about 70' ),
			array( 'Hub', '/resources/', 'animal welfare guides', 'T 0', '– (secondaries Y: "how to report animal cruelty in india" is top)', 'Hub 20 impr' ),
			array( 'Hub', '/directory/', 'animal welfare ngos in india', '"animal ngo" T 28 ("near me" leads); "gaushala" T 383', 'Y', 'Hub 17; place pages 2,540 impr, pos about 8' ),
			array( 'Hub', '/opportunities/', 'animal welfare volunteer', 'T 0; "ngo jobs" T 67', 'Y ("volunteering near me"); "animal welfare jobs india" Y', 'Hub 0' ),
			array( 'Hub', '/campaigns/', 'animal welfare campaigns', 'T 0', 'Y', 'Hub 18; "petition campaign" 113, "letter petition" 48, "stray dog petition india" 14 (all on Home)' ),
			array( 'Hub', '/press-coverage/', 'animal writes in the news', 'T 0', '–', 'Brand only ("nodogsnovote" 605 on Home)' ),
			array( 'Directory', 'State (/directory/{state}/)', 'animal welfare ngos in {state}', '', 'Mostly – for states (Google suggests cities)', 'State pages about 1,400 impr, pos 8–10' ),
			array( 'Directory', 'District (/directory/{state}/{district}/)', 'animal ngo in {district}', '"animal helpline" T 59, "animal rescue" T 100', 'Y for big cities (Delhi, Noida, Dehradun, Pune, Bangalore)', '"animal helpline number {district}" / "animal rescue {district} contact number" about 240 impr, 0 clicks' ),
			array( 'Directory', 'Type in a place (/directory/{state}/{district}/{type}/)', '{type} in {district}', '"gaushala" T 383; "veterinary hospital" T 315', 'Y ("gaushala in jaipur", "government veterinary hospital {city}")', '"jhajjar gaushala" 22' ),
			array( 'Directory', 'Filtered list (?state=&type=)', '{type} in {state}', '', 'n/a (canonicalised)', 'n/a' ),
			array( 'Directory', 'Listing (/directory/org/{slug}/)', '{organisation name}', '', '"{name} contact number" and "{name} reviews" appear', '2,144 impr over 367 listings' ),
			array( 'Category', '/categories/', 'animal welfare topics', '', 'Y (school-essay intent); "issues in india" –', '' ),
			array( 'Category', 'Community Dogs', 'stray dogs in india', '"stray dogs" T 171 (part anime), "street dogs" T 96, "community dogs" T 11', 'Y', 'Category page 3' ),
			array( 'Category', 'Animal Birth Control', 'animal birth control', 'T about 0', 'Y ("rules 2023" top)', '41 impr, pos 5 (guide)' ),
			array( 'Category', 'Courts and Judgments', 'animal welfare court judgments', '"supreme court stray dogs" T 16', '– ("supreme court stray dogs" Y)', '' ),
			array( 'Category', 'Cruelty and Prosecution', 'animal cruelty cases', '"animal cruelty" T 25', 'Y ("cases in india")', 'Cruelty queries 41 impr' ),
			array( 'Category', 'Laws and Rules', 'animal protection rules india', '"animal laws" T 14', '– ("animal protection laws india" is suggested)', 'Rules by name about 70' ),
			array( 'Category', 'Policy and Government', 'animal welfare policy india', 'T 0; "animal husbandry department" T 304 (broad)', '– ("animal husbandry department" Y)', '' ),
			array( 'Category', 'Wildlife', 'wildlife rescue india', 'T 0; "human wildlife conflict" T 6', 'ext (mostly Indiana); "human wildlife conflict" Y', '' ),
			array( 'Category', 'Cattle and Gaushalas', 'gaushalas in india', '"gaushala" T 383', 'Y', '' ),
			array( 'Category', 'Campaigns and Civic Action', 'animal welfare civic action', '', '– ("animal rights campaigns" Y)', '' ),
			array( 'Category', 'Pets, Breeding and Pet Shops', 'pet shops and breeders', '', '– ("pet shop rules" Y)', '"pet shop rules 2018" 17' ),
			array( 'Category', 'Veterinary Care', 'veterinary care india', '"veterinary care" T 40; "veterinary hospital" T 315', '– ("animal hospital" Y)', '' ),
			array( 'Category', 'Feeders and Caregivers', 'stray animal feeders', 'T 0', 'Y (exact only)', '' ),
			array( 'Category', 'Performing and Captive Animals', 'captive animals india', '', '– ("performing animals rules" ext: 1973/2001)', '4' ),
			array( 'Category', 'Animal Transport and Slaughter', 'animal transport rules', '"cattle transport" T 2', 'Y ("1978")', '"transport of animals rules 1978" about 26' ),
			array( 'Category', 'Rabies and Public Health', 'rabies in india', '"rabies" T 1,375 (health intent)', 'Y', '' ),
			array( 'Category', 'Disaster and Emergency Rescue', 'animal disaster rescue', 'T 4', 'Y (exact only)', '' ),
			array( 'Category', 'Adoption', 'animal adoption india', '"dog adoption" T 67, "adopt a dog" T 7', 'Y (Indiana noise)', '' ),
			array( 'Category', 'Birds', 'bird rescue india', '"bird rescue" T 25', 'Y; "injured bird helpline" Y', '' ),
			array( 'Guide', 'How to file a police complaint (FIR) for animal cruelty', 'file a police complaint', '', 'Y (generic); "fir for animal cruelty" Y', '' ),
			array( 'Guide', 'Feeding community dogs: what the law says', 'feeding community dogs', '', 'Y (weak); "is feeding stray dogs illegal in india" Y', '1' ),
			array( 'Guide', 'RWA or society stopping you from feeding dogs', 'society stopping feeding dogs', '', '– ("feeding stray dogs in housing society" Y)', 'Page 7' ),
			array( 'Guide', 'Injured animal emergency: who to call first', 'injured animal emergency', '', 'Y; "injured animal helpline number" Y', '121 (old URL)' ),
			array( 'Guide', 'Who to call for an animal emergency in India', 'animal emergency in india', '"animal helpline" T 59', '– ("1962 helpline" Y)', '' ),
			array( 'Guide', 'ABC Rules 2023 explained', 'abc rules 2023', '', 'Y (+"pdf")', '"abc rules 2023 pdf" 6' ),
			array( 'Guide', 'Reporting illegal pet shops and breeders', 'illegal pet shops', '', '– ("pet shop rules 2018" Y)', '"pet shop rules 2018" 17' ),
			array( 'Guide', 'Animal welfare: frequently asked questions', 'animal welfare faq', '', '–', '' ),
			array( 'Guide', 'Living alongside animals', 'living alongside animals', '', '– ("dog bite first aid" Y)', '' ),
			array( 'Guide', 'Feeding community animals: good practice', 'feeding community animals', '', '– ("what to feed stray dogs (in india)" Y)', 'Page 13' ),
			array( 'Guide', 'Adopting a dog or cat', 'adopting a dog', '', 'Y', '' ),
			array( 'Guide', 'Animal welfare laws in India, in plain words', 'animal welfare laws', '', 'Y ("in india" top); "pca act 1960" Y', '' ),
			array( 'Guide', 'Helping an injured animal', 'helping an injured animal', '', '– ("injured animal rescue" Y)', '' ),
			array( 'Guide', 'How to report animal cruelty', 'report animal cruelty', '', 'Y', 'Page 336 impr, pos about 5' ),
			array( 'Guide', 'What is Animal Birth Control (ABC)?', 'animal birth control', '', 'Y', 'Page 323 impr, pos about 5' ),
			array( 'Guide', 'Caring for community dogs in your area', 'caring for community dogs', '', '– ("stray dog vaccination" Y)', 'Page 6' ),
		),
		'gaps'        => array(
			array( 'District helpline and rescue numbers', '"animal helpline number {district}" and "animal rescue {district} contact number": about 240 impressions, positions 7–11, 0 clicks. Consider them as secondaries on district Directory pages.' ),
			array( 'Campaign petition searches', '"petition campaign" 113, "letter petition" 48, "stray dog petition india" 14, "letter petition supreme court" 12. They land on Home. "animal welfare campaigns" (the /campaigns/ primary) has no Trends demand. Consider "stray dog petition" as the Campaigns primary.' ),
			array( 'World Animal Day (about 1,060 impressions on a Library notification)', 'There is no lasting page for the day. It is seasonal, every October.' ),
			array( '1962 questions', 'People also ask: "Is 1962 free?", "Why is the animal ambulance number 1962?" Mobile veterinary unit by state: 14 + 9 + 6 impressions. Add them to the "Who to call" guide\'s FAQ.' ),
			array( 'Rules searched with "pdf"', '"pet shop rules 2018 pdf", "transport of animals rules 1978 pdf", "abc rules 2023 pdf", "pca act 1960 pdf". The Library items should link the official PDF and say "PDF".' ),
			array( '"street dogs"', '"street dogs" has T 96, against "community dogs" 11; "street dogs in india" is the top related query. Consider it as a Community Dogs secondary.' ),
			array( '"gaushala": T 383, the largest term in the field', 'Rajasthan, Haryana, Punjab and Delhi lead, and we hold 2,047 listings. Gaushala state and type-in-place pages are the biggest local opportunity.' ),
			array( '"government veterinary hospital {city}" and "veterinary hospital" (T 315)', 'These outrank "veterinary care india" (T 0, no autocomplete) for the Veterinary Care category.' ),
			array( 'Feeding legality phrased as "stray dogs"', '"is feeding stray dogs illegal in india", "awbi guidelines for feeding stray dogs", "feeding stray dogs in housing society", "supreme court stray dogs order/judgement". Our feeding guides say "community dogs", and Search Console shows 1 impression.' ),
			array( 'Animal Welfare Board of India', 'Signals: T 9, the top autocomplete for "animal welfare", a recruitment breakout, Wikipedia 664 views a month. No page targets it.' ),
		),
		// Home phrase: Google Trends, India, past 12 months, same chart.
		'trends'      => array(
			array( 'animal welfare', 51 ),
			array( 'animal rights', 17 ),
			array( 'animal welfare india', 14 ),
			array( 'animal welfare in india', 4 ),
			array( 'animal rights india', 3 ),
		),
		'wikipedia'   => array(
			array( 'Indian pariah dog', 13390 ),
			array( 'Cattle slaughter in India', 8626 ),
			array( 'Free-ranging dog', 6729 ),
			array( 'Animal rights', 6597 ),
			array( 'Animal welfare', 4246 ),
			array( 'Animal Welfare Board of India', 664 ),
			array( 'Animal welfare and rights in India', 405 ),
			array( 'PCA Act 1960', 374 ),
			array( 'Gaushala', 37 ),
		),
	);
}

/** The stored snapshot; seeds (or re-seeds) the fixed part when it is missing or older than this file. */
function aw_sd_get(): array {
	$data = get_option( AW_SD_OPTION, array() );
	$data = is_array( $data ) ? $data : array();
	if ( ( $data['static']['version'] ?? 0 ) < AW_SD_STATIC_VERSION ) {
		$data['static'] = aw_sd_static();
		update_option( AW_SD_OPTION, $data, false );
	}
	return $data;
}

function aw_sd_save( array $data ): void {
	update_option( AW_SD_OPTION, $data, false );
}

/** A date with the site's calendar icon (aw_date from Animal Writes core), in IST. */
function aw_sd_date( $when, string $fmt = 'j M Y' ): string {
	if ( ! $when ) {
		return 'Never';
	}
	return function_exists( 'aw_date' ) ? aw_date( $when, $fmt ) : esc_html( wp_date( $fmt, is_numeric( $when ) ? (int) $when : strtotime( (string) $when ) ) );
}

/**
 * Site Kit's Search Console module, or why it cannot be used.
 *
 * @return array{0: object|null, 1: string, 2: int} Module, problem in plain words ('' if none), owner user ID.
 */
function aw_sd_sitekit(): array {
	if ( ! class_exists( '\Google\Site_Kit\Plugin' ) || ! class_exists( '\Google\Site_Kit\Core\Modules\Modules' ) ) {
		return array( null, 'Site Kit by Google is not active, so Search Console cannot be read.', 0 );
	}
	$settings = get_option( 'googlesitekit_search-console_settings' );
	$owner    = is_array( $settings ) ? (int) ( $settings['ownerID'] ?? 0 ) : 0;
	if ( empty( $settings['propertyID'] ) || ! $owner || ! get_userdata( $owner ) ) {
		return array( null, 'Search Console is not connected in Site Kit. Connect it under Site Kit → Settings.', 0 );
	}
	$was = get_current_user_id();
	wp_set_current_user( $owner ); // Site Kit binds the Google client to the current user when the module is built.
	try {
		if ( defined( 'GOOGLESITEKIT_PLUGIN_MAIN_FILE' ) && class_exists( '\Google\Site_Kit\Context' ) ) {
			$ctx = new \Google\Site_Kit\Context( GOOGLESITEKIT_PLUGIN_MAIN_FILE );
		} else {
			$plugin = \Google\Site_Kit\Plugin::instance();
			$prop   = ( new ReflectionClass( $plugin ) )->getProperty( 'context' );
			$prop->setAccessible( true );
			$ctx = $prop->getValue( $plugin );
		}
		$module = ( new \Google\Site_Kit\Core\Modules\Modules( $ctx ) )->get_module( 'search-console' );
	} catch ( Throwable $e ) {
		wp_set_current_user( $was );
		return array( null, 'Site Kit\'s Search Console module could not be loaded.', 0 );
	}
	wp_set_current_user( $was );
	if ( method_exists( $module, 'is_connected' ) && ! $module->is_connected() ) {
		return array( null, 'Search Console is not connected in Site Kit. Connect it under Site Kit → Settings.', 0 );
	}
	return array( $module, '', $owner );
}

/** Rows of one searchanalytics request as plain arrays: keys, clicks, impressions, position. */
function aw_sd_query( $module, array $dims, string $start, string $end ) {
	try {
		$r = $module->get_data( 'searchanalytics', array( 'dimensions' => $dims, 'startDate' => $start, 'endDate' => $end, 'limit' => 5000 ) );
	} catch ( Throwable $e ) {
		return new WP_Error( 'aw_sd', $e->getMessage() );
	}
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$rows = array();
	foreach ( (array) $r as $row ) {
		if ( is_object( $row ) && method_exists( $row, 'getKeys' ) ) {
			$rows[] = array( (array) $row->getKeys(), (int) $row->getClicks(), (int) $row->getImpressions(), (float) $row->getPosition() );
		} elseif ( is_array( $row ) ) {
			$rows[] = array( (array) ( $row['keys'] ?? array() ), (int) ( $row['clicks'] ?? 0 ), (int) ( $row['impressions'] ?? 0 ), (float) ( $row['position'] ?? 0 ) );
		}
	}
	return $rows;
}

/** Clicks, impressions and impression-weighted position of a set of rows. */
function aw_sd_sum( array $rows ): array {
	$c = 0;
	$i = 0;
	$p = 0.0;
	foreach ( $rows as $r ) {
		$c += $r[1];
		$i += $r[2];
		$p += $r[3] * $r[2];
	}
	return array( 'clicks' => $c, 'impr' => $i, 'pos' => $i ? round( $p / $i, 1 ) : 0 );
}

/** Page rows as path => [clicks, impressions, position]. */
function aw_sd_pages( array $rows, string $home ): array {
	$out = array();
	foreach ( $rows as $r ) {
		$out[ aw_sd_path( (string) ( $r[0][0] ?? '' ), $home ) ] = array( $r[1], $r[2], round( $r[3], 1 ) );
	}
	return $out;
}

/** A URL as the path Search Console rows are kept under. */
function aw_sd_path( string $url, string $home = '' ): string {
	$path = str_replace( array( '' !== $home ? $home : untrailingslashit( home_url() ), 'https://www.animalwrites.in', 'https://animalwrites.in' ), '', $url );
	return '' === $path ? '/' : $path;
}

/** Rising: at least 5 impressions in the last 28 days and at least twice the 3-month average for 28 days. */
function aw_sd_rising( int $impr28, int $impr3m, array $data ): bool {
	$p    = $data['gsc']['periods']['3m'] ?? array();
	$days = ! empty( $p['start'] ) ? max( 28, (int) round( ( strtotime( $p['end'] ) - strtotime( $p['start'] ) ) / DAY_IN_SECONDS ) + 1 ) : 91;
	return $impr28 >= 5 && $impr28 >= 2 * max( $impr3m, $impr28 ) * 28 / $days;
}

/** The last 28 days' searches: normalised query => [clicks, impressions, position]. */
function aw_sd_28d( array $data ): array {
	$out = array();
	foreach ( (array) ( $data['gsc']['periods']['28d']['queries'] ?? array() ) as $r ) {
		$out[ aw_sd_norm( (string) $r[0] ) ] = array( (int) $r[1], (int) $r[2], (float) $r[3] );
	}
	return $out;
}

/**
 * Searches for AW Sourcing's suggestions: every search of the last 3 months (and any newer one), with its figures for
 * the last 28 days.
 *
 * @return array<int, array{q:string, impr:int, clicks:int, pos:float, impr3m:int, brand:bool, rising:bool}>
 */
function aw_sd_demand_terms(): array {
	$data = aw_sd_get();
	$d28  = aw_sd_28d( $data );
	$out  = array();
	$seen = array();
	foreach ( (array) ( $data['gsc']['periods']['3m']['queries'] ?? array() ) as $r ) {
		$q          = aw_sd_norm( (string) $r[0] );
		$m          = $d28[ $q ] ?? array( 0, 0, 0 );
		$seen[ $q ] = true;
		$out[]      = array( 'q' => $q, 'impr' => $m[1], 'clicks' => $m[0], 'pos' => $m[2], 'impr3m' => (int) $r[2], 'brand' => (bool) $r[5], 'rising' => aw_sd_rising( $m[1], (int) $r[2], $data ) );
	}
	foreach ( $d28 as $q => $m ) { // A search of the last 28 days that the 3-month list (5,000 rows) did not reach.
		if ( empty( $seen[ $q ] ) ) {
			$out[] = array( 'q' => $q, 'impr' => $m[1], 'clicks' => $m[0], 'pos' => $m[2], 'impr3m' => $m[1], 'brand' => (bool) preg_match( AW_SD_BRAND, $q ), 'rising' => aw_sd_rising( $m[1], $m[1], $data ) );
		}
	}
	return $out;
}
add_filter( 'aw_search_demand_terms', fn() => aw_sd_demand_terms() );

/**
 * Search Console figures of a set of items, added up over a period ('28d' or '3m').
 *
 * @param int[]      $ids    Post IDs.
 * @param string     $period Period key.
 * @param array|null $data   The stored snapshot (read when not given).
 * @return array{clicks:int, impr:int, pages:int}
 */
function aw_sd_items_metrics( array $ids, string $period = '28d', ?array $data = null ): array {
	$data  = $data ?? aw_sd_get();
	$pages = (array) ( $data['gsc']['periods'][ $period ]['pages'] ?? array() );
	$out   = array( 'clicks' => 0, 'impr' => 0, 'pages' => 0 );
	foreach ( array_unique( array_map( 'intval', $ids ) ) as $id ) {
		$url = get_permalink( $id );
		$m   = $url ? ( $pages[ aw_sd_path( $url ) ] ?? null ) : null;
		if ( $m ) {
			$out['clicks'] += (int) $m[0];
			$out['impr']   += (int) $m[1];
			++$out['pages'];
		}
	}
	return $out;
}

/**
 * Fetch the Search Console part through Site Kit and store it. Runs as the Site Kit owner of Search Console.
 *
 * @return string '' on success, else the problem in plain words.
 */
function aw_sd_refresh(): string {
	@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	$data = aw_sd_get();
	list( $module, $problem, $owner ) = aw_sd_sitekit();
	if ( ! $module ) {
		$data['gsc_error'] = array( 'time' => time(), 'message' => $problem );
		aw_sd_save( $data );
		return $problem;
	}
	$was = get_current_user_id();
	wp_set_current_user( $owner );
	$tz      = wp_timezone();
	$end     = new DateTimeImmutable( 'yesterday', $tz );
	$periods = array(
		'28d' => $end->modify( '-27 days' ),
		'3m'  => $end->modify( '-3 months' )->modify( '+1 day' ),
	);
	$home    = untrailingslashit( home_url() );
	$out     = array();
	$error   = '';
	foreach ( $periods as $key => $start ) {
		$s     = $start->format( 'Y-m-d' );
		$e     = $end->format( 'Y-m-d' );
		$dates = aw_sd_query( $module, array( 'date' ), $s, $e );
		$qs    = is_wp_error( $dates ) ? $dates : aw_sd_query( $module, array( 'query' ), $s, $e );
		$qp    = is_wp_error( $qs ) ? $qs : aw_sd_query( $module, array( 'query', 'page' ), $s, $e );
		$pg    = is_wp_error( $qp ) ? array() : aw_sd_query( $module, array( 'page' ), $s, $e ); // per page, for sourcing keywords
		if ( is_wp_error( $qp ) ) {
			$error = 'Site Kit could not read Search Console: ' . wp_strip_all_tags( $qp->get_error_message() );
			break;
		}
		$page = array(); // Query → [impressions, page] of the page Google showed most.
		foreach ( $qp as $r ) {
			$q = (string) ( $r[0][0] ?? '' );
			if ( ! isset( $page[ $q ] ) || $r[2] > $page[ $q ][0] ) {
				$path       = str_replace( array( $home, 'https://www.animalwrites.in', 'https://animalwrites.in' ), '', (string) ( $r[0][1] ?? '' ) );
				$page[ $q ] = array( $r[2], '' === $path ? '/' : $path );
			}
		}
		$brand    = array();
		$nonbrand = array();
		$queries  = array();
		foreach ( $qs as $r ) {
			$q         = (string) ( $r[0][0] ?? '' );
			$is_brand  = (bool) preg_match( AW_SD_BRAND, $q );
			$queries[] = array( $q, $r[1], $r[2], round( $r[3], 1 ), $page[ $q ][1] ?? '', $is_brand ? 1 : 0 );
			if ( $is_brand ) {
				$brand[] = $r;
			} else {
				$nonbrand[] = $r;
			}
		}
		usort( $queries, fn( $a, $b ) => $b[2] <=> $a[2] ?: $b[1] <=> $a[1] );
		$out[ $key ] = array(
			'start'    => $s,
			'end'      => $e,
			'totals'   => aw_sd_sum( $dates ),
			'named'    => aw_sd_sum( $qs ),
			'brand'    => aw_sd_sum( $brand ),
			'nonbrand' => aw_sd_sum( $nonbrand ),
			'queries'  => $queries, // Query, clicks, impressions, position, page, brand.
			'pages'    => aw_sd_pages( is_wp_error( $pg ) ? array() : $pg, $home ), // Path => [clicks, impressions, position].
		);
	}
	wp_set_current_user( $was );
	if ( $error ) {
		$data['gsc_error'] = array( 'time' => time(), 'message' => $error );
		aw_sd_save( $data );
		return $error;
	}
	$settings    = get_option( 'googlesitekit_search-console_settings' );
	$data['gsc'] = array( 'fetched' => time(), 'property' => (string) ( $settings['propertyID'] ?? '' ), 'periods' => $out );
	unset( $data['gsc_error'] );
	aw_sd_save( $data );
	return '';
}

add_action( AW_SD_CRON, 'aw_sd_refresh' );
add_action(
	'init',
	function () {
		if ( 'daily' !== wp_get_schedule( AW_SD_CRON ) ) {
			wp_clear_scheduled_hook( AW_SD_CRON ); // The weekly event of 15 Oct 2026.
			$next = new DateTimeImmutable( 'tomorrow 07:00', wp_timezone() ); // Daily, 07:00 IST.
			wp_schedule_event( $next->getTimestamp(), 'daily', AW_SD_CRON );
		}
	}
);


/** Capability of the Sourcing screen (Kits Core), so both screens open for the same people. */
function aw_sd_cap(): string {
	return defined( 'AW_SRC_CAP' ) ? AW_SRC_CAP : 'manage_options';
}

/*
 * Tools → Search Insights (owner, 15 Oct 2026: Settings holds configuration, Tools holds reports and jobs). It was under
 * Settings next to Sourcing; the old address redirects here.
 */
add_action(
	'admin_menu',
	function () {
		add_management_page( 'Search Insights', 'Search Insights', aw_sd_cap(), 'aw-search-demand', 'aw_sd_screen' );
	},
	99
);
add_filter( 'aw_admin_moved_pages', fn( $m ) => $m + array( 'aw-search-demand' => 'tools.php' ) ); // Old Settings address → Tools (admin-ia.php).

add_action(
	'admin_post_aw_search_demand_refresh',
	function () {
		if ( ! current_user_can( aw_sd_cap() ) || ! check_admin_referer( 'aw_search_demand_refresh' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$problem = aw_sd_refresh();
		$back    = wp_get_referer() ? remove_query_arg( array( 'refreshed', 'refresh_failed' ), wp_get_referer() ) : admin_url( 'tools.php?page=aw-search-demand' );
		wp_safe_redirect( add_query_arg( $problem ? 'refresh_failed' : 'refreshed', 1, $back ) );
		exit;
	}
);

/** Lowercase, single-spaced. */
function aw_sd_norm( string $s ): string {
	return trim( (string) preg_replace( '/\s+/u', ' ', mb_strtolower( $s ) ) );
}

/**
 * Keyword-map targets, one per phrase: phrase, pages ("Section: Page") and a regex for matching queries (null when the
 * phrase is all placeholder, like "{organisation name}" or "{type} in {district}", which would match nearly anything).
 * A plain phrase matches a query that contains it as whole words; a phrase with {placeholders} must match the whole query.
 */
function aw_sd_targets( array $map ): array {
	$out = array();
	foreach ( $map as $m ) {
		$phrase = aw_sd_norm( (string) $m[2] );
		$page   = $m[0] . ': ' . $m[1];
		if ( isset( $out[ $phrase ] ) ) {
			$out[ $phrase ]['pages'][] = $page;
			continue;
		}
		$re = null;
		if ( false === strpos( $phrase, '{' ) ) {
			$re = '/(?:^|\s)' . preg_quote( $phrase, '/' ) . '(?:\s|$)/u';
		} else {
			$literal = preg_split( '/\s+/', trim( (string) preg_replace( '/\{[^}]*\}/', ' ', $phrase ) ) );
			$words   = array_filter( (array) $literal, fn( $w ) => mb_strlen( $w ) >= 3 && ! in_array( $w, array( 'the', 'and', 'for' ), true ) );
			if ( $words ) {
				$parts = preg_split( '/\{[^}]*\}/', $phrase );
				$re    = '/^' . implode( '.+', array_map( fn( $x ) => preg_quote( $x, '/' ), $parts ) ) . '$/u';
			}
		}
		$out[ $phrase ] = array( 'phrase' => $phrase, 'pages' => array( $page ), 're' => $re );
	}
	return $out;
}

/**
 * The one table: Search Console queries (latest 3 months) merged with the keyword-map targets.
 *
 * Importance (owner, 15 Oct 2026). Each non-brand query weighs its impressions over the latest 3 months, doubled
 * when it sits at average position 5–20 with 0 clicks (Google shows us on page 1–2 and nobody clicks: an opportunity).
 * Brand queries and target phrases with no Search Console data weigh 0. Importance % = weight ÷ sum of all weights
 * × 100, rounded to 2 decimals by largest remainder so the column adds up to exactly 100.
 *
 * @return array<int, array{q:string, clicks:?int, impr:?int, pos:?float, page:string, brand:bool, targets:array, imp:float}>
 */
function aw_sd_table( array $data ): array {
	$targets = aw_sd_targets( $data['static']['map'] ?? array() );
	$d28     = aw_sd_28d( $data );
	$rows    = array();
	$seen    = array();
	foreach ( (array) ( $data['gsc']['periods']['3m']['queries'] ?? array() ) as $r ) {
		$q     = (string) $r[0];
		$nq    = aw_sd_norm( $q );
		$brand = (bool) preg_match( AW_SD_BRAND, $q );
		$hits  = array();
		foreach ( $targets as $t ) {
			if ( $t['phrase'] === $nq || ( $t['re'] && preg_match( $t['re'], $nq ) ) ) {
				$hits[] = $t;
				if ( $t['phrase'] === $nq ) {
					$seen[ $t['phrase'] ] = true;
				}
			}
		}
		usort( $hits, fn( $a, $b ) => ( $b['phrase'] === $nq ) <=> ( $a['phrase'] === $nq ) ); // The exact phrase first.
		$opp    = 0 === (int) $r[1] && $r[3] >= 5 && $r[3] <= 20;
		$rows[] = array(
			'q'       => $q,
			'clicks'  => (int) $r[1],
			'impr'    => (int) $r[2],
			'pos'     => (float) $r[3],
			'page'    => (string) $r[4],
			'brand'   => $brand,
			'rising'  => ! $brand && aw_sd_rising( (int) ( $d28[ $nq ][1] ?? 0 ), (int) $r[2], $data ),
			'targets' => $hits,
			'w'       => $brand ? 0 : (int) $r[2] * ( $opp ? 2 : 1 ),
		);
	}
	foreach ( $targets as $t ) {
		if ( empty( $seen[ $t['phrase'] ] ) ) { // No query is exactly this phrase: it still gets its own row.
			$rows[] = array( 'q' => $t['phrase'], 'clicks' => null, 'impr' => null, 'pos' => null, 'page' => '', 'brand' => false, 'rising' => false, 'targets' => array( $t ), 'w' => 0 );
		}
	}
	// Largest remainder, in hundredths of a per cent.
	$sum   = array_sum( array_column( $rows, 'w' ) );
	$units = array();
	$rest  = array();
	foreach ( $rows as $i => $row ) {
		$exact       = $sum ? $row['w'] * 10000 / $sum : 0;
		$units[ $i ] = (int) floor( $exact );
		$rest[ $i ]  = $exact - $units[ $i ];
	}
	arsort( $rest );
	$left = $sum ? 10000 - array_sum( $units ) : 0;
	foreach ( array_keys( $rest ) as $i ) {
		if ( $left-- <= 0 ) {
			break;
		}
		++$units[ $i ];
	}
	foreach ( $rows as $i => &$row ) {
		$row['imp'] = $units[ $i ] / 100;
	}
	unset( $row );
	usort( $rows, fn( $a, $b ) => $b['imp'] <=> $a['imp'] ?: (int) $b['impr'] <=> (int) $a['impr'] ?: strcmp( $a['q'], $b['q'] ) );
	return $rows;
}

function aw_sd_num( $n ): string {
	return esc_html( number_format_i18n( (float) $n ) );
}

function aw_sd_screen() {
	if ( ! current_user_can( aw_sd_cap() ) ) {
		return;
	}
	$data              = aw_sd_get();
	$gsc               = $data['gsc'] ?? array();
	list( , $problem ) = aw_sd_sitekit();
	$rows              = aw_sd_table( $data );

	echo '<div class="wrap"><h1>Search Insights</h1>';
	if ( isset( $_GET['refreshed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$up_to = $gsc['periods']['28d']['end'] ?? '';
		$count = count( $gsc['periods']['3m']['queries'] ?? array() );
		echo '<div class="notice notice-success is-dismissible"><p>Refreshed at ' . esc_html( wp_date( 'H:i', (int) ( $gsc['fetched'] ?? time() ) ) ) . ' IST: ' . esc_html( aw_sd_num( $count ) ) . ' searches from Search Console, figures up to ' . ( $up_to ? aw_sd_date( $up_to ) : 'yesterday' ) . '. Google adds a new day once a day, so a second refresh the same day shows the same figures.</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- aw_sd_date escapes.
	}
	if ( isset( $_GET['refresh_failed'] ) && ! empty( $data['gsc_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-error"><p>Refresh failed: ' . esc_html( $data['gsc_error']['message'] ) . '</p></div>';
	}
	if ( $problem ) {
		echo '<div class="notice notice-warning"><p>' . esc_html( $problem ) . ' The figures below are the last ones fetched.</p></div>';
	}

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_search_demand_refresh">';
	wp_nonce_field( 'aw_search_demand_refresh' );
	$up_to = $gsc['periods']['28d']['end'] ?? '';
	echo '<p>Search Console figures up to ' . ( $up_to ? aw_sd_date( $up_to ) : 'never fetched' ) . ' · fetched ' . ( ! empty( $gsc['fetched'] ) ? aw_sd_date( $gsc['fetched'] ) . ' ' . esc_html( wp_date( 'H:i', (int) $gsc['fetched'] ) ) . ' IST' : 'never' ) . ' · fetches by itself daily at 07:00 IST '; // phpcs:ignore WordPress.Security.EscapeOutput -- aw_sd_date escapes.
	submit_button( 'Refresh now', 'secondary', 'submit', false );
	echo '</p></form>';

	echo '<h2>Searches and targets <span class="count">(' . aw_sd_num( count( $rows ) ) . ')</span></h2>';
	echo '<p class="description">Importance: each non-brand search\'s share of all non-brand impressions over the last 3 months, counted double when Google shows us at position 5–20 and nobody clicks; the column adds up to 100%. Rising: at least 5 impressions in the last 28 days and at least twice its 3-month average for 28 days.</p>';
	$cols = array( 'Query', 'Importance (%)', 'Clicks', 'Impressions', 'Avg. position', 'Page Google shows', 'Our target?' );
	echo '<table id="aw-sd-table" class="wp-list-table widefat striped"><thead><tr>';
	foreach ( $cols as $n => $label ) {
		$state = 1 === $n ? 'sorted desc' : 'sortable asc';
		echo '<th scope="col" class="manage-column ' . esc_attr( $state ) . '"' . ( 1 === $n ? ' aria-sort="descending"' : '' ) . ' data-col="' . (int) $n . '"><a href="#"><span>' . esc_html( $label ) . '</span><span class="sorting-indicators"><span class="sorting-indicator asc" aria-hidden="true"></span><span class="sorting-indicator desc" aria-hidden="true"></span></span></a></th>';
	}
	echo '</tr></thead><tbody>';
	if ( ! $rows ) {
		echo '<tr><td colspan="7">Not fetched yet. Use Refresh now.</td></tr>';
	}
	$dash = '—';
	foreach ( $rows as $r ) {
		$target = $dash;
		$tsort  = '';
		if ( $r['targets'] ) {
			$t      = $r['targets'][0];
			$tsort  = implode( ', ', $t['pages'] );
			$target = 'Yes: ' . esc_html( $tsort ) . ( aw_sd_norm( $r['q'] ) !== $t['phrase'] ? '<br><span class="description">' . esc_html( '"' . $t['phrase'] . '"' ) . '</span>' : '' );
		}
		$none = null === $r['impr'];
		echo '<tr>'
			. '<td data-v="' . esc_attr( $r['q'] ) . '">' . esc_html( $r['q'] ) . ( $r['brand'] ? ' — <span class="post-state">Brand</span>' : '' ) . ( ! empty( $r['rising'] ) ? ' — <span class="post-state">Rising</span>' : '' ) . '</td>'
			. '<td data-v="' . esc_attr( (string) $r['imp'] ) . '">' . esc_html( number_format_i18n( $r['imp'], 2 ) ) . '</td>'
			. '<td data-v="' . ( $none ? -1 : (int) $r['clicks'] ) . '">' . ( $none ? $dash : aw_sd_num( $r['clicks'] ) ) . '</td>'
			. '<td data-v="' . ( $none ? -1 : (int) $r['impr'] ) . '">' . ( $none ? $dash : aw_sd_num( $r['impr'] ) ) . '</td>'
			. '<td data-v="' . ( $none ? 9999 : esc_attr( (string) $r['pos'] ) ) . '">' . ( $none ? $dash : esc_html( number_format_i18n( $r['pos'], 1 ) ) ) . '</td>'
			. '<td data-v="' . esc_attr( $r['page'] ) . '">' . ( $r['page'] ? '<a href="' . esc_url( home_url( $r['page'] ) ) . '" target="_blank" rel="noopener">' . esc_html( $r['page'] ) . '</a>' : $dash ) . '</td>'
			. '<td data-v="' . esc_attr( $tsort ) . '">' . $target . '</td>' // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
			. '</tr>';
	}
	echo '</tbody></table>';
	?>
<script>
( function () {
	// A browser reload after Refresh now shows the page, not the old "Refreshed" notice again.
	if ( window.history.replaceState && /[?&](refreshed|refresh_failed)=/.test( location.search ) ) {
		window.history.replaceState( null, '', location.href.replace( /[?&](refreshed|refresh_failed)=1/, '' ) );
	}
	var table = document.getElementById( 'aw-sd-table' );
	if ( ! table ) { return; }
	var heads = table.tHead.rows[0].cells, body = table.tBodies[0];
	Array.prototype.forEach.call( heads, function ( th ) {
		th.querySelector( 'a' ).addEventListener( 'click', function ( e ) {
			e.preventDefault();
			var col = +th.getAttribute( 'data-col' ), text = col === 0 || col >= 5;
			var desc = th.getAttribute( 'aria-sort' ) ? th.getAttribute( 'aria-sort' ) === 'ascending' : ! text;
			var rows = Array.prototype.slice.call( body.rows );
			rows.sort( function ( a, b ) {
				var x = a.cells[ col ].getAttribute( 'data-v' ), y = b.cells[ col ].getAttribute( 'data-v' );
				var c = text ? x.localeCompare( y ) : ( +x - +y );
				return desc ? -c : c;
			} );
			rows.forEach( function ( r ) { body.appendChild( r ); } );
			Array.prototype.forEach.call( heads, function ( h ) {
				h.removeAttribute( 'aria-sort' );
				h.className = 'manage-column sortable asc';
			} );
			th.className = 'manage-column sorted ' + ( desc ? 'desc' : 'asc' );
			th.setAttribute( 'aria-sort', desc ? 'descending' : 'ascending' );
		} );
	} );
}() );
</script>
	<?php

	aw_sd_sourcing_section( $data );

	$gaps = $data['static']['gaps'] ?? array();
	echo '<details style="margin-top:1.5em"><summary><strong>Gaps (' . aw_sd_num( count( $gaps ) ) . ')</strong>: searches with demand that no page targets yet. Suggestions only.</summary>';
	echo '<table class="widefat striped" style="margin-top:.5em"><thead><tr><th>#</th><th>Gap</th><th>Detail</th></tr></thead><tbody>';
	foreach ( $gaps as $i => $g ) {
		echo '<tr><td>' . (int) ( $i + 1 ) . '</td><td><strong>' . esc_html( $g[0] ) . '</strong></td><td>' . esc_html( $g[1] ) . '</td></tr>';
	}
	echo '</tbody></table></details>';
	echo '</div>';
}

/**
 * Sourcing keywords (owner, 24 Oct 2026; read-only): per Google News search of the workers, the items it brought in over
 * the last 30 days and how they do in search. Shown only when AW Sourcing is on (filter aw_sourcing_keyword_items).
 */
function aw_sd_sourcing_section( array $data ): void {
	$stats = apply_filters( 'aw_sourcing_keyword_items', null, 30 );
	if ( ! is_array( $stats ) || empty( $stats['rows'] ) ) {
		return;
	}
	if ( ! class_exists( 'WP_List_Table' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	}
	$table = new AW_SD_Sourcing_Table( $stats, $data );
	$table->prepare_items();
	echo '<h2 id="aw-sd-sourcing">Sourcing keywords <span class="count">(' . aw_sd_num( count( $stats['rows'] ) ) . ')</span></h2>';
	echo '<p class="description">Each English Include word in <a href="' . esc_url( admin_url( 'options-general.php?page=aw-sourcing&tab=keywords' ) ) . '">Settings → Sourcing → Keywords</a> is a Google News search in every run. Fetched: the items it brought in since ' . aw_sd_date( (int) $stats['since'] ) . ' (30 days), whatever happened to them since (held, merged, backfilled). Published: those live now. Impressions and clicks: Search Console, last 28 days, for those items\' own pages. Items from before 24 Oct 2026 did not record their search; they are counted by the word in their title or summary and marked estimated, and an item with more than one of the words counts under each. Hindi Include words are not searches and are not listed.</p>'; // phpcs:ignore WordPress.Security.EscapeOutput -- aw_sd_date escapes.
	if ( empty( $data['gsc']['periods']['28d']['pages'] ) ) {
		echo '<div class="notice notice-info inline"><p><span class="dashicons dashicons-info" aria-hidden="true"></span> Impressions and clicks per item come with the next Search Console refresh (daily at 07:00 IST, or Refresh now).</p></div>';
	}
	$table->display();
}

if ( is_admin() ) {
	add_action(
		'admin_init',
		function () {
			if ( ! class_exists( 'WP_List_Table' ) ) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
			}

			/** Tools → Search Insights → Sourcing keywords: a core list table, sortable, read-only. */
			class AW_SD_Sourcing_Table extends WP_List_Table {
				/** @var array */
				private $stats;
				/** @var array */
				private $data;

				public function __construct( array $stats, array $data ) {
					parent::__construct( array( 'singular' => 'keyword', 'plural' => 'keywords', 'ajax' => false, 'screen' => 'tools_page_aw-search-demand' ) );
					$this->stats = $stats;
					$this->data  = $data;
				}

				public function get_columns() {
					return array(
						'term'       => 'Keyword',
						'news_f'     => 'Fetched: News',
						'legal_f'    => 'Fetched: Library',
						'news_p'     => 'Published: News',
						'legal_p'    => 'Published: Library',
						'impr'       => 'Impressions (28 days)',
						'clicks'     => 'Clicks (28 days)',
					);
				}

				protected function get_sortable_columns() {
					$out = array();
					foreach ( array_keys( $this->get_columns() ) as $c ) {
						$out[ $c ] = array( $c, 'term' !== $c );
					}
					return $out;
				}

				protected function get_primary_column_name() {
					return 'term';
				}

				public function prepare_items() {
					$has   = ! empty( $this->data['gsc']['periods']['28d']['pages'] );
					$items = array();
					foreach ( $this->stats['rows'] as $r ) {
						$m       = $has ? aw_sd_items_metrics( array_merge( $r['news']['fetched'], $r['legal']['fetched'] ), '28d', $this->data ) : null;
						$items[] = array(
							'term'      => $r['term'],
							'fixed'     => ! empty( $r['fixed'] ),
							'listed'    => ! empty( $r['listed'] ),
							'estimated' => ! empty( $r['estimated'] ),
							'news_f'    => count( $r['news']['fetched'] ),
							'legal_f'   => count( $r['legal']['fetched'] ),
							'news_p'    => count( $r['news']['published'] ),
							'legal_p'   => count( $r['legal']['published'] ),
							'impr'      => $m ? $m['impr'] : null,
							'clicks'    => $m ? $m['clicks'] : null,
						);
					}
					$by    = sanitize_key( $_GET['orderby'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
					$by    = isset( $this->get_columns()[ $by ] ) ? $by : 'news_f';
					$asc   = 'asc' === strtolower( (string) ( $_GET['order'] ?? ( 'term' === $by ? 'asc' : 'desc' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
					usort(
						$items,
						function ( $a, $b ) use ( $by, $asc ) {
							$c = 'term' === $by ? strcasecmp( $a['term'], $b['term'] ) : ( (int) $a[ $by ] <=> (int) $b[ $by ] );
							return $asc ? $c : -$c;
						}
					);
					$this->items           = $items;
					$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'term' );
				}

				protected function column_term( $item ) {
					$notes = array();
					if ( $item['fixed'] ) {
						$notes[] = 'Fixed search in the workers';
					} elseif ( ! $item['listed'] ) {
						$notes[] = 'No longer in the Include list';
					}
					if ( $item['estimated'] ) {
						$notes[] = 'Estimated';
					}
					return '<strong>' . esc_html( $item['term'] ) . '</strong>' . ( $notes ? ' — <span class="post-state">' . esc_html( implode( ', ', $notes ) ) . '</span>' : '' );
				}

				protected function column_default( $item, $col ) {
					return null === $item[ $col ] ? '—' : aw_sd_num( $item[ $col ] );
				}

				public function no_items() {
					echo 'No searches.';
				}

				protected function display_tablenav( $which ) {} // read-only: no bulk actions or pagination
			}
		}
	);
}
