<?php
/**
 * Settings → Search demand (15 Oct 2026): what people search and how Animal Writes shows up for it.
 *
 * - Search Console (last 28 days and last 3 months) comes through Site Kit's own search-console module, the same
 *   call the 15 Oct research used. It refreshes weekly by WP-Cron and on "Refresh now". Site Kit keeps the Google
 *   connection; this file never reads its tokens.
 * - The keyword map, gaps, Google Trends, autocomplete and Wikipedia figures are a fixed snapshot of
 *   docs/seo/keyword-map.md and docs/seo/keyword-demand-2026-10-15.md (bump AW_SD_STATIC_VERSION when they change).
 * - Everything sits in one option, aw_search_demand (not autoloaded).
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SD_OPTION         = 'aw_search_demand';
const AW_SD_CRON           = 'aw_search_demand_refresh';
const AW_SD_STATIC_VERSION = 1;
const AW_SD_SHOW_ROWS      = 200;

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
			'wikipedia'    => array( 'Wikipedia pageviews (English, Oct 2025 – Sep 2026)', '2026-10-15', 'Snapshot. A rough guide to topic interest.' ),
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
			array( 'Hub', '/media/', 'animal writes in the news', 'T 0', '–', 'Brand only ("nodogsnovote" 605 on Home)' ),
			array( 'Directory', 'State (/directory/{state}/)', 'animal welfare ngos in {state}', '', 'Mostly – for states (Google suggests cities)', 'State pages about 1,400 impr, pos 8–10' ),
			array( 'Directory', 'District (/directory/{state}/{district}/)', 'animal ngo in {district}', '"animal helpline" T 59, "animal rescue" T 100', 'Y for big cities (Delhi, Noida, Dehradun, Pune, Bangalore)', '"animal helpline number {district}" / "animal rescue {district} contact number" about 240 impr, 0 clicks' ),
			array( 'Directory', 'Type in a place (/directory/{state}/{district}/{type}/)', '{type} in {district}', '"gaushala" T 383; "veterinary hospital" T 315', 'Y ("gaushala in jaipur", "government veterinary hospital {city}")', '"jhajjar gaushala" 22' ),
			array( 'Directory', 'Filtered list (?state=&type=)', '{type} in {state}', '', 'n/a (canonicalised)', 'n/a' ),
			array( 'Directory', 'Listing (/directory/org/{slug}/)', '{organisation name}', '', '"{name} contact number" and "{name} reviews" appear', '2,144 impr over 367 listings' ),
			array( 'Topic', '/topics/', 'animal welfare topics', '', 'Y (school-essay intent); "issues in india" –', '' ),
			array( 'Topic', 'Community Dogs', 'stray dogs in india', '"stray dogs" T 171 (part anime), "street dogs" T 96, "community dogs" T 11', 'Y', 'Topic 3' ),
			array( 'Topic', 'Animal Birth Control', 'animal birth control', 'T about 0', 'Y ("rules 2023" top)', '41 impr, pos 5 (guide)' ),
			array( 'Topic', 'Courts and Judgments', 'animal welfare court judgments', '"supreme court stray dogs" T 16', '– ("supreme court stray dogs" Y)', '' ),
			array( 'Topic', 'Cruelty and Prosecution', 'animal cruelty cases', '"animal cruelty" T 25', 'Y ("cases in india")', 'Cruelty queries 41 impr' ),
			array( 'Topic', 'Laws and Rules', 'animal protection rules india', '"animal laws" T 14', '– ("animal protection laws india" is suggested)', 'Rules by name about 70' ),
			array( 'Topic', 'Policy and Government', 'animal welfare policy india', 'T 0; "animal husbandry department" T 304 (broad)', '– ("animal husbandry department" Y)', '' ),
			array( 'Topic', 'Wildlife', 'wildlife rescue india', 'T 0; "human wildlife conflict" T 6', 'ext (mostly Indiana); "human wildlife conflict" Y', '' ),
			array( 'Topic', 'Cattle and Gaushalas', 'gaushalas in india', '"gaushala" T 383', 'Y', '' ),
			array( 'Topic', 'Campaigns and Civic Action', 'animal welfare civic action', '', '– ("animal rights campaigns" Y)', '' ),
			array( 'Topic', 'Pets, Breeding and Pet Shops', 'pet shops and breeders', '', '– ("pet shop rules" Y)', '"pet shop rules 2018" 17' ),
			array( 'Topic', 'Veterinary Care', 'veterinary care india', '"veterinary care" T 40; "veterinary hospital" T 315', '– ("animal hospital" Y)', '' ),
			array( 'Topic', 'Feeders and Caregivers', 'stray animal feeders', 'T 0', 'Y (exact only)', '' ),
			array( 'Topic', 'Performing and Captive Animals', 'captive animals india', '', '– ("performing animals rules" ext: 1973/2001)', '4' ),
			array( 'Topic', 'Animal Transport and Slaughter', 'animal transport rules', '"cattle transport" T 2', 'Y ("1978")', '"transport of animals rules 1978" about 26' ),
			array( 'Topic', 'Rabies and Public Health', 'rabies in india', '"rabies" T 1,375 (health intent)', 'Y', '' ),
			array( 'Topic', 'Disaster and Emergency Rescue', 'animal disaster rescue', 'T 4', 'Y (exact only)', '' ),
			array( 'Topic', 'Adoption', 'animal adoption india', '"dog adoption" T 67, "adopt a dog" T 7', 'Y (Indiana noise)', '' ),
			array( 'Topic', 'Birds', 'bird rescue india', '"bird rescue" T 25', 'Y; "injured bird helpline" Y', '' ),
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
			array( '"government veterinary hospital {city}" and "veterinary hospital" (T 315)', 'These outrank "veterinary care india" (T 0, no autocomplete) for the Veterinary Care topic.' ),
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
		if ( ! wp_next_scheduled( AW_SD_CRON ) ) {
			$next = new DateTimeImmutable( 'next monday 07:00', wp_timezone() ); // Mondays, 07:00 IST.
			wp_schedule_event( $next->getTimestamp(), 'weekly', AW_SD_CRON );
		}
	}
);

add_action(
	'admin_menu',
	function () {
		add_options_page( 'Search demand', 'Search demand', 'manage_options', 'aw-search-demand', 'aw_sd_screen' );
	}
);

add_action(
	'admin_post_aw_search_demand_refresh',
	function () {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'aw_search_demand_refresh' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$problem = aw_sd_refresh();
		wp_safe_redirect( admin_url( 'options-general.php?page=aw-search-demand&' . ( $problem ? 'refresh_failed=1' : 'refreshed=1' ) ) );
		exit;
	}
);

/** Current table filters from the request: period (28d, 3m) and view (nonbrand, opportunities, brand, all). */
function aw_sd_filters(): array {
	$period = sanitize_key( wp_unslash( $_GET['period'] ?? '28d' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$view   = sanitize_key( wp_unslash( $_GET['view'] ?? 'nonbrand' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	return array(
		in_array( $period, array( '28d', '3m' ), true ) ? $period : '28d',
		in_array( $view, array( 'nonbrand', 'opportunities', 'brand', 'all' ), true ) ? $view : 'nonbrand',
	);
}

const AW_SD_PERIODS = array( '28d' => 'Last 28 days', '3m' => 'Last 3 months' );
const AW_SD_VIEWS   = array(
	'nonbrand'      => 'Non-brand',
	'opportunities' => 'Opportunities (position 5–20, 0 clicks)',
	'brand'         => 'Brand',
	'all'           => 'All',
);

/** Query rows of a period, filtered by view. */
function aw_sd_rows( array $data, string $period, string $view ): array {
	$rows = $data['gsc']['periods'][ $period ]['queries'] ?? array();
	return array_values(
		array_filter(
			$rows,
			function ( $r ) use ( $view ) {
				switch ( $view ) {
					case 'brand':
						return 1 === (int) $r[5];
					case 'nonbrand':
						return 0 === (int) $r[5];
					case 'opportunities':
						return 0 === (int) $r[5] && 0 === (int) $r[1] && $r[3] >= 5 && $r[3] <= 20;
				}
				return true;
			}
		)
	);
}

add_action(
	'admin_post_aw_search_demand_csv',
	function () {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'aw_search_demand_csv' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		list( $period, $view ) = aw_sd_filters();
		$rows                  = aw_sd_rows( aw_sd_get(), $period, $view );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="search-demand-' . $view . '-' . $period . '-' . wp_date( 'Y-m-d' ) . '.csv"' );
		$fh   = fopen( 'php://output', 'w' );
		$cell = fn( $v ) => is_string( $v ) && preg_match( '/^[=+\-@\t\r]/', $v ) ? "'" . $v : $v; // Spreadsheets must not run a query as a formula.
		fputcsv( $fh, array( 'Query', 'Clicks', 'Impressions', 'CTR %', 'Position', 'Page Google shows', 'Brand' ) );
		foreach ( $rows as $r ) {
			fputcsv( $fh, array( $cell( $r[0] ), $r[1], $r[2], $r[2] ? round( 100 * $r[1] / $r[2], 1 ) : 0, $r[3], $cell( $r[4] ), $r[5] ? 'yes' : 'no' ) );
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}
);

function aw_sd_num( $n ): string {
	return esc_html( number_format_i18n( (float) $n ) );
}

function aw_sd_ctr( int $clicks, int $impr ): string {
	return $impr ? esc_html( number_format_i18n( 100 * $clicks / $impr, 1 ) . '%' ) : '—';
}

function aw_sd_screen() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$data                  = aw_sd_get();
	$static                = $data['static'];
	list( $period, $view ) = aw_sd_filters();
	list( , $problem )     = aw_sd_sitekit();
	$base                  = admin_url( 'options-general.php?page=aw-search-demand' );

	echo '<div class="wrap"><h1>Search demand</h1>';
	echo '<p>What people search for, and how Animal Writes shows up for it. Search Console figures come through Site Kit and refresh every Monday at 07:00 IST. The keyword map, gaps, Google Trends and autocomplete figures are the snapshot taken on 15 Oct 2026.</p>';
	if ( isset( $_GET['refreshed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success"><p>Search Console figures refreshed.</p></div>';
	}
	if ( isset( $_GET['refresh_failed'] ) && ! empty( $data['gsc_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-error"><p>Refresh failed: ' . esc_html( $data['gsc_error']['message'] ) . '</p></div>';
	}
	if ( $problem ) {
		echo '<div class="notice notice-warning"><p>' . esc_html( $problem ) . ' The figures below are the last ones fetched.</p></div>';
	}

	// (a) Search Console.
	echo '<h2>Search Console</h2>';
	$gsc = $data['gsc'] ?? array();
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_search_demand_refresh">';
	wp_nonce_field( 'aw_search_demand_refresh' );
	echo '<p>' . ( ! empty( $gsc['fetched'] ) ? 'Last fetched ' . aw_sd_date( $gsc['fetched'], 'j M Y, H:i' ) . ' IST' . ( $gsc['property'] ? ' from <code>' . esc_html( $gsc['property'] ) . '</code>' : '' ) . '. ' : 'Not fetched yet. ' );
	$next = wp_next_scheduled( AW_SD_CRON );
	echo $next ? 'Next weekly refresh ' . aw_sd_date( $next, 'j M Y, H:i' ) . ' IST. ' : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- aw_sd_date escapes.
	submit_button( 'Refresh now', 'secondary', 'submit', false );
	echo '</p></form>';

	echo '<table class="widefat striped" style="max-width:60rem"><thead><tr><th>Period</th><th>Clicks</th><th>Impressions</th><th>CTR</th><th>Avg. position</th><th>Brand clicks / impressions</th><th>Non-brand clicks / impressions</th></tr></thead><tbody>';
	foreach ( AW_SD_PERIODS as $k => $label ) {
		$p = $gsc['periods'][ $k ] ?? null;
		if ( ! $p ) {
			echo '<tr><td>' . esc_html( $label ) . '</td><td colspan="6">Not fetched yet.</td></tr>';
			continue;
		}
		echo '<tr><td>' . esc_html( $label ) . '<br><small>' . esc_html( wp_date( 'j M', strtotime( $p['start'] ) ) . ' – ' . wp_date( 'j M Y', strtotime( $p['end'] ) ) ) . '</small></td>'
			. '<td>' . aw_sd_num( $p['totals']['clicks'] ) . '</td><td>' . aw_sd_num( $p['totals']['impr'] ) . '</td>'
			. '<td>' . aw_sd_ctr( $p['totals']['clicks'], $p['totals']['impr'] ) . '</td><td>' . esc_html( (string) $p['totals']['pos'] ) . '</td>'
			. '<td>' . aw_sd_num( $p['brand']['clicks'] ) . ' / ' . aw_sd_num( $p['brand']['impr'] ) . '</td>'
			. '<td>' . aw_sd_num( $p['nonbrand']['clicks'] ) . ' / ' . aw_sd_num( $p['nonbrand']['impr'] ) . '</td></tr>';
	}
	$snap = $static['gsc_snapshot'];
	echo '<tr><td>' . esc_html( $snap['label'] ) . '</td><td>' . aw_sd_num( $snap['clicks'] ) . '</td><td>' . aw_sd_num( $snap['impr'] ) . '</td><td>' . aw_sd_ctr( $snap['clicks'], $snap['impr'] ) . '</td><td>—</td>'
		. '<td>' . aw_sd_num( $snap['brand'][0] ) . ' / ' . aw_sd_num( $snap['brand'][1] ) . '</td><td>' . aw_sd_num( $snap['nonbrand'][0] ) . ' / about ' . aw_sd_num( $snap['nonbrand'][1] ) . '</td></tr>';
	echo '</tbody></table>';
	echo '<p class="description">Totals include searches Google keeps private. The brand split counts only the searches Google names, so brand and non-brand add up to less than the total. Brand means searches for Animal Writes by name or address.</p>';

	echo '<h3>Searches</h3>';
	$links = array();
	foreach ( AW_SD_PERIODS as $k => $label ) {
		$links[] = '<li><a href="' . esc_url( add_query_arg( array( 'period' => $k, 'view' => $view ), $base ) ) . '"' . ( $k === $period ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a></li>';
	}
	echo '<ul class="subsubsub" style="float:none">' . implode( ' | ', $links ) . '</ul>'; // phpcs:ignore WordPress.Security.EscapeOutput
	$links = array();
	foreach ( AW_SD_VIEWS as $k => $label ) {
		$links[] = '<li><a href="' . esc_url( add_query_arg( array( 'period' => $period, 'view' => $k ), $base ) ) . '"' . ( $k === $view ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $label ) . ' <span class="count">(' . aw_sd_num( count( aw_sd_rows( $data, $period, $k ) ) ) . ')</span></a></li>';
	}
	echo '<ul class="subsubsub" style="float:none">' . implode( ' | ', $links ) . '</ul>'; // phpcs:ignore WordPress.Security.EscapeOutput
	$rows = aw_sd_rows( $data, $period, $view );
	$csv  = wp_nonce_url( add_query_arg( array( 'action' => 'aw_search_demand_csv', 'period' => $period, 'view' => $view ), admin_url( 'admin-post.php' ) ), 'aw_search_demand_csv' );
	echo '<p><a class="button" href="' . esc_url( $csv ) . '">Download CSV</a> ';
	echo count( $rows ) > AW_SD_SHOW_ROWS ? 'Showing the top ' . aw_sd_num( AW_SD_SHOW_ROWS ) . ' of ' . aw_sd_num( count( $rows ) ) . ' by impressions; the CSV has them all.' : '';
	echo '</p>';
	if ( 'opportunities' === $view ) {
		echo '<p class="description">Searches where Google already shows a page on page 1 or 2 but nobody clicks: the words to work into that page\'s title, description or first paragraph.</p>';
	}
	echo '<table class="widefat striped"><thead><tr><th>Search</th><th>Clicks</th><th>Impressions</th><th>CTR</th><th>Position</th><th>Page Google shows</th></tr></thead><tbody>';
	if ( ! $rows ) {
		echo '<tr><td colspan="6">' . ( empty( $gsc ) ? 'Not fetched yet. Use Refresh now.' : 'No searches in this view.' ) . '</td></tr>';
	}
	foreach ( array_slice( $rows, 0, AW_SD_SHOW_ROWS ) as $r ) {
		echo '<tr><td>' . esc_html( $r[0] ) . '</td><td>' . aw_sd_num( $r[1] ) . '</td><td>' . aw_sd_num( $r[2] ) . '</td><td>' . aw_sd_ctr( (int) $r[1], (int) $r[2] ) . '</td><td>' . esc_html( (string) $r[3] ) . '</td>'
			. '<td>' . ( $r[4] ? '<a href="' . esc_url( home_url( $r[4] ) ) . '" target="_blank" rel="noopener">' . esc_html( $r[4] ) . '</a>' : '—' ) . '</td></tr>';
	}
	echo '</tbody></table>';

	// (b) Keyword map.
	echo '<h2>Keyword map</h2>';
	echo '<p>The phrase each page targets, with the demand found for it on 15 Oct 2026. <strong>T</strong> is the Google Trends index for India over 12 months, with "animal welfare" = 100: relative interest, not a search count. <strong>Autocomplete</strong>: Y means Google suggests the exact phrase, ext only longer forms, – not at all. <strong>Search Console</strong> is impressions for 17 Nov 2025 – 14 Oct 2026.</p>';
	echo '<table class="widefat striped"><thead><tr><th>Section</th><th>Page</th><th>Target phrase</th><th>Google Trends</th><th>Autocomplete</th><th>Search Console</th></tr></thead><tbody>';
	foreach ( $static['map'] as $m ) {
		echo '<tr><td>' . esc_html( $m[0] ) . '</td><td>' . esc_html( $m[1] ) . '</td><td><strong>' . esc_html( $m[2] ) . '</strong></td><td>' . esc_html( '' !== $m[3] ? $m[3] : '—' ) . '</td><td>' . esc_html( '' !== $m[4] ? $m[4] : '—' ) . '</td><td>' . esc_html( '' !== $m[5] ? $m[5] : '—' ) . '</td></tr>';
	}
	echo '</tbody></table>';

	// (c) Gaps.
	echo '<h2>Gaps</h2>';
	echo '<p>Searches with demand that no page targets yet. Suggestions only; nothing on the site was changed.</p>';
	echo '<table class="widefat striped"><thead><tr><th>#</th><th>Gap</th><th>Detail</th></tr></thead><tbody>';
	foreach ( $static['gaps'] as $i => $g ) {
		echo '<tr><td>' . (int) ( $i + 1 ) . '</td><td><strong>' . esc_html( $g[0] ) . '</strong></td><td>' . esc_html( $g[1] ) . '</td></tr>';
	}
	echo '</tbody></table>';

	echo '<h2>Other signals</h2>';
	echo '<div style="display:flex;flex-wrap:wrap;gap:2rem">';
	echo '<table class="widefat striped" style="width:auto"><caption style="text-align:left"><strong>Home phrase, Google Trends</strong></caption><thead><tr><th>Phrase</th><th>Index</th></tr></thead><tbody>';
	foreach ( $static['trends'] as $t ) {
		echo '<tr><td>' . esc_html( $t[0] ) . '</td><td>' . aw_sd_num( $t[1] ) . '</td></tr>';
	}
	echo '</tbody></table>';
	echo '<table class="widefat striped" style="width:auto"><caption style="text-align:left"><strong>Wikipedia views a month</strong></caption><thead><tr><th>Article</th><th>Views</th></tr></thead><tbody>';
	foreach ( $static['wikipedia'] as $w ) {
		echo '<tr><td>' . esc_html( $w[0] ) . '</td><td>' . aw_sd_num( $w[1] ) . '</td></tr>';
	}
	echo '</tbody></table></div>';

	// (d) Last updated.
	echo '<h2>Last updated</h2>';
	echo '<table class="widefat striped" style="max-width:60rem"><thead><tr><th>Source</th><th>Updated</th><th>How</th></tr></thead><tbody>';
	$sc_status = $problem ? $problem : ( ! empty( $data['gsc_error'] ) ? 'Last refresh failed ' . wp_date( 'j M Y, H:i', $data['gsc_error']['time'] ) . ' IST: ' . $data['gsc_error']['message'] : 'Through Site Kit, weekly (Mondays, 07:00 IST) and on Refresh now.' );
	echo '<tr><td>Search Console</td><td>' . ( ! empty( $gsc['fetched'] ) ? aw_sd_date( $gsc['fetched'], 'j M Y, H:i' ) . ' IST' : 'Never' ) . '</td><td>' . esc_html( $sc_status ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
	foreach ( $static['sources'] as $s ) {
		echo '<tr><td>' . esc_html( $s[0] ) . '</td><td>' . aw_sd_date( $s[1] ) . '</td><td>' . esc_html( $s[2] ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</tbody></table>';
	echo '</div>';
}
