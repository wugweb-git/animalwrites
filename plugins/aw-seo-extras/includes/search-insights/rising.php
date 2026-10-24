<?php
/**
 * Search Insights, phase 3: rising terms (plan §3 `rising`, §5.3, §5.5, §7–8; owner, 24 Oct 2026).
 *
 * - REST route aw/v1/search-insights for the workers' search-insights job (animal-writes-workers,
 *   aw_workers/search_insights.py), signed in as the ingest user (aw_seo_api_allowed()):
 *   GET gives the job its lists (English keywords, topics, species, watchlists, seeds); POST takes one source's rising
 *   terms and figures and records that source's run (Trends trending now, Wikipedia, GDELT daily; autocomplete and
 *   Trends related queries weekly; Trends interest monthly).
 * - Sourcing spikes, on the site: daily counts of publishers, states, species and laws across the News items AW Sourcing
 *   fetched, flagged at 3× the 14-day average (daily at 23:40 IST, after the rollup).
 * - The Rising tab (filter aw_si_tabs): the rising terms with their sources, growth, first seen, matching page and
 *   suggested action; the owner marks them Noted or Dismissed. A paste form takes Trends figures when the worker is
 *   refused.
 *
 * Read-only towards everything else: it writes only wp_aw_si_rising and wp_aw_si_obs (never Rank Math or the Sourcing
 * lists). English only until phase 5. The Search Console part of Rising (new queries) stays off until 8 weeks of
 * history (owner, 24 Oct 2026).
 *
 * Figures that belong to a term rather than a keyword record are stored in obs with kw_id 0 and page "#<source>:<norm>"
 * (never a site path, which always starts with "/"), so they stay one row per term, day and metric.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SI_RISING_CRON      = 'aw_si_rising_spikes';
const AW_SI_SPIKE_RATIO      = 3;   // Sourcing spike: 3× the 14-day average or more.
const AW_SI_SPIKE_MIN        = 3;   // And at least 3 items that day.
const AW_SI_SPIKE_WINDOW     = 14;
const AW_SI_RISING_MAX_ROWS  = 500; // Per POST: rising terms.
const AW_SI_RISING_MAX_OBS   = 3000; // Per POST: figures.
const AW_SI_RISING_NEWS_SRCS = array( 'trends_rss', 'gdelt', 'sourcing' ); // Sources that mean "in the news".

/**
 * The rising sources: key => label, schedule and whether the workers send it.
 *
 * @return array<string, array{label: string, every: int, worker: bool}>
 */
function aw_si_rising_sources(): array {
	return array(
		'trends_rss'      => array(
			'label'  => __( 'Trends: trending now', 'aw-seo-extras' ),
			'every'  => DAY_IN_SECONDS,
			'worker' => true,
		),
		'wikipedia'       => array(
			'label'  => __( 'Wikipedia pageviews', 'aw-seo-extras' ),
			'every'  => DAY_IN_SECONDS,
			'worker' => true,
		),
		'gdelt'           => array(
			'label'  => __( 'News (GDELT)', 'aw-seo-extras' ),
			'every'  => DAY_IN_SECONDS,
			'worker' => true,
		),
		'autocomplete'    => array(
			'label'  => __( 'Google autocomplete (English)', 'aw-seo-extras' ),
			'every'  => WEEK_IN_SECONDS,
			'worker' => true,
		),
		'trends_related'  => array(
			'label'  => __( 'Trends: rising related searches', 'aw-seo-extras' ),
			'every'  => WEEK_IN_SECONDS,
			'worker' => true,
		),
		'trends_interest' => array(
			'label'  => __( 'Trends interest (animal welfare = 100)', 'aw-seo-extras' ),
			'every'  => 31 * DAY_IN_SECONDS,
			'worker' => true,
		),
		'sourcing'        => array(
			'label'  => __( 'Sourcing spikes', 'aw-seo-extras' ),
			'every'  => DAY_IN_SECONDS,
			'worker' => false,
		),
	);
}

// Each rising source has its own entry in the run log, the notices and Site Health ("rising_" keeps them apart).
add_filter(
	'aw_si_sources',
	function ( $sources ) {
		foreach ( aw_si_rising_sources() as $key => $s ) {
			$sources[ 'rising_' . $key ] = array(
				'label' => $s['label'],
				'every' => $s['every'],
			);
		}
		return $sources;
	}
);

/** Animal names counted in the Sourcing spikes and sent to the worker's Trends filter (singular; plurals fold). */
function aw_si_species(): array {
	$list = array( 'dog', 'puppy', 'cat', 'kitten', 'cow', 'cattle', 'bull', 'buffalo', 'calf', 'goat', 'sheep', 'pig', 'horse', 'donkey', 'mule', 'camel', 'monkey', 'macaque', 'langur', 'elephant', 'tiger', 'leopard', 'lion', 'cheetah', 'bear', 'deer', 'blackbuck', 'peacock', 'peafowl', 'pigeon', 'parrot', 'bird', 'vulture', 'eagle', 'owl', 'crow', 'snake', 'cobra', 'python', 'crocodile', 'turtle', 'tortoise', 'dolphin', 'whale', 'shark', 'pangolin', 'rhino', 'rhinoceros', 'wolf', 'jackal', 'hyena', 'boar', 'bat', 'squirrel', 'rabbit', 'chicken', 'hen', 'poultry' );
	return array_values( array_unique( array_map( 'strval', (array) apply_filters( 'aw_si_species', $list ) ) ) );
}

/**
 * The lists the worker reads (GET aw/v1/search-insights). Each can be changed with the filter aw_si_rising_lists.
 *
 * @return array<string, mixed>
 */
function aw_si_rising_lists(): array {
	global $wpdb;
	$keywords = array();
	$topics   = array();
	$interest = array();
	if ( aw_si_ready() ) {
		$k     = aw_si_table( 'keywords' );
		$roles = (int) ( AW_SI_ROLE_TRACKED | AW_SI_ROLE_SOURCING | AW_SI_ROLE_FOCUS );
		$eff   = "IF(priority_override <> '', priority_override, priority)";
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name and integers only.
		$keywords = $wpdb->get_col( $wpdb->prepare( "SELECT label FROM {$k} WHERE lang = 'en' AND entity = 0 AND roles & %d AND label NOT LIKE %s ORDER BY impr28 DESC, id LIMIT 3000", $roles, '%{%' ) );
		$topics   = $wpdb->get_col( "SELECT DISTINCT topic FROM {$k} WHERE topic <> '' AND lang = 'en' ORDER BY topic" );
		$interest = $wpdb->get_col( $wpdb->prepare( "SELECT label FROM {$k} WHERE lang = 'en' AND entity = 0 AND roles & %d AND {$eff} IN ('P1', 'P2') AND label NOT LIKE %s ORDER BY {$eff}, impr28 DESC, id LIMIT 40", AW_SI_ROLE_TRACKED, '%{%' ) );
		// phpcs:enable
	}
	$lists = array(
		'keywords'       => array_values( array_filter( array_map( 'strval', (array) $keywords ), fn( $s ) => ! aw_si_is_brand( $s ) ) ),
		'topics'         => array_values( array_map( 'strval', (array) $topics ) ),
		'species'        => aw_si_species(),
		'wikipedia'      => array( 'Rabies', 'Free-ranging dog', 'Indian pariah dog', 'Animal Welfare Board of India', 'Prevention of Cruelty to Animals Act, 1960', 'Wild Life (Protection) Act, 1972', 'Goshala', 'Cattle slaughter in India', 'Cow protection movement', 'Cow vigilante violence in India', 'Animal welfare and rights in India', 'Human–wildlife conflict', 'Project Tiger', 'Dog bite' ), // Canonical titles: redirects count apart.
		'gdelt'          => array( 'stray dogs', 'dog bite', 'rabies', 'animal cruelty', 'gaushala', 'cow vigilante', 'human wildlife conflict', 'leopard attack', 'elephant death', 'animal welfare board' ),
		'autocomplete'   => array_values( array_unique( array_merge( array( 'stray dogs', 'street dogs', 'dog bite', 'rabies', 'animal welfare', 'animal cruelty', 'animal rescue', 'animal shelter', 'gaushala', 'veterinary hospital', 'pet adoption', 'abc rules', 'pca act', 'feeding stray dogs', 'wildlife rescue' ), array_slice( (array) $interest, 0, 15 ) ) ) ),
		'trends_related' => array( 'stray dogs', 'animal welfare', 'rabies', 'animal cruelty', 'gaushala' ),
		'interest'       => array_values( array_map( 'strval', (array) $interest ) ),
		'anchor'         => 'animal welfare',
		// Terms with these words are dropped by the worker: an anime called Bungo Stray Dogs fills the "stray dogs"
		// related searches (dry run, 24 Oct 2026), films and songs the trending list.
		'exclude'        => array( 'anime', 'manga', 'bungo', 'movie', 'film', 'song', 'lyrics', 'season', 'episode', 'cast', 'trailer', 'netflix', 'web series', 'ott', 'box office', 'game', 'meme', 'wallpaper', 'cartoon', 'meaning in' ),
	);
	return (array) apply_filters( 'aw_si_rising_lists', $lists );
}

/**
 * The keyword record a term matches, and its page: the same record (same normal form), else the longest keyword of two
 * or more words the term contains; the page is that keyword's page, else a published page whose title contains the term.
 *
 * @param string $norm Normalised term.
 * @param string $term Term as written.
 * @return array{0: int, 1: string} Keyword ID (0: none) and site path or page label ('' when none).
 */
function aw_si_rising_match( string $norm, string $term ): array {
	global $wpdb;
	static $kw = null;
	$k         = aw_si_table( 'keywords' );
	if ( null === $kw ) {
		$kw = array();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
		foreach ( (array) $wpdb->get_results( "SELECT id, norm, page FROM {$k} WHERE entity = 0 AND lang = 'en'" ) as $r ) {
			$kw[ (string) $r->norm ] = array( (int) $r->id, (string) $r->page );
		}
		uksort( $kw, fn( $a, $b ) => strlen( $b ) <=> strlen( $a ) ); // Longest first: the most specific match wins.
	}
	$hit = $kw[ $norm ] ?? null;
	if ( ! $hit ) {
		$padded = ' ' . $norm . ' ';
		foreach ( $kw as $n => $row ) {
			if ( false !== strpos( (string) $n, ' ' ) && false === strpos( (string) $n, '{' ) && false !== strpos( $padded, ' ' . $n . ' ' ) ) {
				$hit = $row;
				break;
			}
		}
	}
	$id   = $hit ? $hit[0] : 0;
	$page = $hit ? $hit[1] : '';
	if ( '' === $page && mb_strlen( $term ) >= 4 ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one indexed lookup per new term.
		$post = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ('page', 'post', 'aw_legal', 'aw_campaign', 'aw_news') AND post_title LIKE %s ORDER BY post_type = 'aw_news', post_date DESC LIMIT 1", '%' . $wpdb->esc_like( $term ) . '%' ) );
		if ( $post ) {
			$page = aw_si_path( (string) get_permalink( (int) $post ) );
		}
	}
	return array( $id, $page );
}

/**
 * Suggested action for a rising term (plan §7): with a page, update it (or explain the news); without, the gap rule.
 *
 * @param string   $term    Term.
 * @param string[] $sources Its sources.
 * @param string   $page    Matching page, or ''.
 */
function aw_si_rising_action( string $term, array $sources, string $page ): string {
	$news = (bool) array_intersect( $sources, AW_SI_RISING_NEWS_SRCS );
	if ( '' !== $page ) {
		return $news ? 'news-explainer' : 'guide-update';
	}
	return $news && ! aw_si_is_question( $term ) ? 'news-explainer' : aw_si_gap_action( $term );
}

/**
 * Store one sighting of a rising term: a new row, or the existing row with this source added, its dates widened and
 * its growth raised (a growth older than 7 days gives way to the new one). The owner's status is kept.
 *
 * @param string     $term   Term as written.
 * @param string     $source Rising source key.
 * @param float|null $growth Times the usual level, or null when the source has no ratio.
 * @param string     $d      Day seen (Y-m-d).
 * @param string     $note   Why it is rising, in plain words.
 * @return string created, updated or the reason it was skipped.
 */
function aw_si_rising_put( string $term, string $source, ?float $growth, string $d, string $note ): string {
	global $wpdb;
	$term = trim( wp_strip_all_tags( $term ) );
	$norm = aw_si_norm( $term );
	if ( '' === $norm || mb_strlen( $term ) > 255 ) {
		return 'empty';
	}
	if ( 'en' !== aw_si_lang( $term ) ) {
		return 'not-english'; // Hindi and Hinglish wait for phase 5 (owner, 24 Oct 2026).
	}
	$t   = aw_si_table( 'rising' );
	$old = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE norm = %s", $norm ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	if ( null !== $growth && ( ! is_finite( $growth ) || $growth <= 0 ) ) {
		$growth = null;
	}
	$growth = null === $growth ? null : round( min( $growth, 1e6 ), 2 );
	$srcs   = $old ? array_values( array_filter( explode( ',', (string) $old->sources ) ) ) : array();
	if ( ! in_array( $source, $srcs, true ) ) {
		$srcs[] = $source;
	}
	if ( $old ) {
		// The higher growth wins while the old one is at most 7 days old; an older growth gives way to the new one.
		$recent = $old->last_seen && $old->last_seen >= gmdate( 'Y-m-d', strtotime( $d . ' -7 days' ) );
		$old_g  = null === $old->growth ? null : (float) $old->growth;
		if ( $recent && null !== $old_g && ( null === $growth || $old_g > $growth ) ) {
			$growth = $old_g;
		}
		$first = min( (string) $old->first_seen, $d );
		$last  = max( (string) $old->last_seen, $d );
		$note  = $d >= (string) $old->last_seen ? $note : (string) $old->note;
	} else {
		$first = $d;
		$last  = $d;
	}
	list( $kw_id, $page ) = aw_si_rising_match( $norm, $term );
	$row                  = array(
		'norm'       => $norm,
		'term'       => $old ? (string) $old->term : $term,
		'sources'    => implode( ',', $srcs ),
		'growth'     => $growth,
		'first_seen' => $first,
		'last_seen'  => $last,
		'kw_id'      => $kw_id,
		'page'       => mb_substr( $page, 0, 255 ),
		'action'     => aw_si_rising_action( $term, $srcs, $page ),
		'note'       => mb_substr( $note, 0, 255 ),
	);
	if ( $old ) {
		$ok = false !== $wpdb->update( $t, $row, array( 'id' => (int) $old->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	} else {
		$row['status'] = 'new';
		$ok            = false !== $wpdb->insert( $t, $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	if ( ! $ok ) {
		return 'db-error';
	}
	// The sighting itself, so the term's history stays after the row's dates move on.
	aw_si_obs_put(
		$kw_id,
		'#rising:' . $norm,
		'rising',
		$source,
		'day',
		$d,
		array(
			'value' => $growth,
			'txt'   => $term,
		)
	);
	return $old ? 'updated' : 'created';
}

/**
 * Where a worker figure is stored: obs source and metric, and whether it belongs to the keyword record itself.
 *
 * @param string $source Rising source key.
 * @param string $metric Metric the worker sent.
 * @return array{0: string, 1: string, 2: bool}|null
 */
function aw_si_rising_obs_target( string $source, string $metric ): ?array {
	$map = array(
		'trends_rss:traffic'    => array( 'trends', 'traffic', false ),
		'wikipedia:views'       => array( 'wikipedia', 'views', false ),
		'gdelt:articles'        => array( 'gdelt', 'articles', false ),
		'trends_interest:index' => array( 'trends', 'index', true ), // Trends T: the daily calculation reads it for priority.
	);
	return $map[ $source . ':' . $metric ] ?? null;
}

/**
 * Store one source's results from the worker.
 *
 * @param array $body Decoded POST body: source, rising[], obs[], info, error, dry_run.
 * @return array Counts, or error.
 */
function aw_si_rising_receive( array $body ): array {
	$source  = sanitize_key( (string) ( $body['source'] ?? '' ) );
	$sources = aw_si_rising_sources();
	if ( ! isset( $sources[ $source ] ) || ! $sources[ $source ]['worker'] ) {
		return array( 'error' => 'unknown source' );
	}
	$rising = array_slice( array_values( array_filter( (array) ( $body['rising'] ?? array() ), 'is_array' ) ), 0, AW_SI_RISING_MAX_ROWS );
	$obs    = array_slice( array_values( array_filter( (array) ( $body['obs'] ?? array() ), 'is_array' ) ), 0, AW_SI_RISING_MAX_OBS );
	$error  = sanitize_text_field( (string) ( $body['error'] ?? '' ) );
	$out    = array(
		'source'  => $source,
		'rising'  => array(),
		'figures' => 0,
		'skipped' => 0,
		'dry_run' => ! empty( $body['dry_run'] ),
	);
	if ( $out['dry_run'] ) {
		$out['rising']  = count( $rising );
		$out['figures'] = count( $obs );
		return $out; // A dry run is only counted.
	}
	aw_si_install(); // The REST request may come before any admin visit has built the tables.
	if ( get_option( 'aw_si_off' ) ) {
		return array( 'error' => 'Search Insights is switched off (its data was deleted); nothing stored' );
	}
	$key = 'rising_' . $source;
	aw_si_run_start( $key );
	foreach ( $rising as $r ) {
		$d = aw_si_rising_date( (string) ( $r['d'] ?? '' ) );
		if ( ! $d ) {
			++$out['skipped'];
			continue;
		}
		$g                        = isset( $r['growth'] ) && is_numeric( $r['growth'] ) ? (float) $r['growth'] : null;
		$result                   = aw_si_rising_put( (string) ( $r['term'] ?? '' ), $source, $g, $d, sanitize_text_field( (string) ( $r['note'] ?? '' ) ) );
		$out['rising'][ $result ] = ( $out['rising'][ $result ] ?? 0 ) + 1;
	}
	$map = aw_si_kw_map();
	foreach ( $obs as $o ) {
		$target = aw_si_rising_obs_target( $source, sanitize_key( (string) ( $o['metric'] ?? '' ) ) );
		$d      = aw_si_rising_date( (string) ( $o['d'] ?? '' ) );
		$period = in_array( $o['period'] ?? '', array( 'day', '12m' ), true ) ? (string) $o['period'] : '';
		$term   = trim( sanitize_text_field( (string) ( $o['term'] ?? '' ) ) );
		$norm   = aw_si_norm( $term );
		if ( ! $target || ! $d || ! $period || '' === $norm || ! isset( $o['value'] ) || ! is_numeric( $o['value'] ) ) {
			++$out['skipped'];
			continue;
		}
		$kw_id = (int) ( $map[ $norm ] ?? 0 );
		if ( $target[2] && ! $kw_id ) {
			++$out['skipped']; // Trends T only means something on a keyword record.
			continue;
		}
		$page            = $target[2] ? '' : '#' . $source . ':' . $norm;
		$ok              = aw_si_obs_put(
			$kw_id,
			$page,
			$target[0],
			$target[1],
			$period,
			$d,
			array(
				'value' => (float) $o['value'],
				'txt'   => $target[2] ? (string) ( $o['txt'] ?? '' ) : $term,
			)
		);
		$out['figures'] += $ok ? 1 : 0;
		$out['skipped'] += $ok ? 0 : 1;
	}
	$info = array(
		'rows'    => array_sum( $out['rising'] ) + $out['figures'],
		'rising'  => count( $rising ),
		'figures' => $out['figures'],
		'worker'  => array_map( 'sanitize_text_field', array_filter( (array) ( $body['info'] ?? array() ), 'is_scalar' ) ),
	);
	if ( $error && ! $rising && ! $out['figures'] ) {
		aw_si_run_fail( $key, $error ); // Nothing came back: the last good data stays.
	} else {
		aw_si_run_ok( $key, $info );
		if ( $error ) {
			aw_si_run_fail( $key, $error ); // Part of the run came back (a refusal after some requests): stored, and the problem shown.
		}
	}
	return $out;
}

/**
 * A Y-m-d date within the last 400 days (and not in the future), or ''.
 *
 * @param string $d Date from the worker.
 */
function aw_si_rising_date( string $d ): string {
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) || ! checkdate( (int) substr( $d, 5, 2 ), (int) substr( $d, 8, 2 ), (int) substr( $d, 0, 4 ) ) ) {
		return '';
	}
	$today = ( new DateTimeImmutable( 'today', wp_timezone() ) )->format( 'Y-m-d' );
	$floor = ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( '-400 days' )->format( 'Y-m-d' );
	return ( $d <= $today && $d >= $floor ) ? $d : '';
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'aw/v1',
			'/search-insights',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => 'aw_seo_api_allowed',
					'callback'            => fn() => rest_ensure_response( aw_si_rising_lists() ),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => 'aw_seo_api_allowed',
					'callback'            => function ( WP_REST_Request $req ) {
						$body = $req->get_json_params();
						$out  = aw_si_rising_receive( is_array( $body ) ? $body : array() );
						if ( isset( $out['error'] ) ) {
							return new WP_Error( 'aw_si_rising', $out['error'], array( 'status' => 400 ) );
						}
						return rest_ensure_response( $out );
					},
				),
			)
		);
	}
);

/**
 * Counts per day across the News items AW Sourcing fetched (any status but auto-draft): publishers, states, species and
 * laws. Days are IST calendar days of aw_ingested_at.
 *
 * @param string $from First day (Y-m-d).
 * @param string $to   Last day (Y-m-d).
 * @return array<string, array<string, array<string, int>>> day => kind => name => items.
 */
function aw_si_sourcing_counts( string $from, string $to ): array {
	global $wpdb;
	$tz    = wp_timezone();
	$start = ( new DateTimeImmutable( $from . ' 00:00', $tz ) )->getTimestamp();
	$end   = ( new DateTimeImmutable( $to . ' 00:00', $tz ) )->modify( '+1 day' )->getTimestamp();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- a bounded range of one meta key.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT p.ID, p.post_title, p.post_excerpt, CAST(m.meta_value AS UNSIGNED) AS t FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'aw_ingested_at' WHERE p.post_type = 'aw_news' AND p.post_status <> 'auto-draft' AND CAST(m.meta_value AS UNSIGNED) >= %d AND CAST(m.meta_value AS UNSIGNED) < %d", $start, $end ) );
	$out  = array();
	if ( ! $rows ) {
		return $out;
	}
	update_meta_cache( 'post', array_map( 'intval', wp_list_pluck( $rows, 'ID' ) ) );
	$species = array();
	foreach ( aw_si_species() as $s ) {
		$species[ aw_si_norm( $s ) ] = $s;
	}
	$states = array();
	if ( function_exists( 'aw_lgd' ) ) {
		foreach ( (array) ( aw_lgd()['states'] ?? array() ) as $s ) {
			$states[ (string) $s->code ] = (string) $s->name;
		}
	}
	foreach ( $rows as $r ) {
		$day   = wp_date( 'Y-m-d', (int) $r->t, $tz );
		$found = array(
			'publisher' => array(),
			'state'     => array(),
			'species'   => array(),
			'law'       => array(),
		);
		$pub   = trim( (string) get_post_meta( (int) $r->ID, 'aw_source_name', true ) );
		if ( '' === $pub ) {
			$pub = (string) wp_parse_url( (string) get_post_meta( (int) $r->ID, 'aw_source_url', true ), PHP_URL_HOST );
		}
		if ( '' !== $pub ) {
			$found['publisher'][ $pub ] = true;
		}
		$ent = json_decode( (string) get_post_meta( (int) $r->ID, 'aw_entities', true ), true );
		foreach ( (array) ( $ent['places'] ?? array() ) as $p ) {
			$code = (string) ( $p['state'] ?? '' );
			$name = $states[ $code ] ?? ( empty( $p['district'] ) ? (string) ( $p['name'] ?? '' ) : '' );
			if ( '' !== $name ) {
				$found['state'][ $name ] = true; // A district counts for its state.
			}
		}
		foreach ( (array) ( $ent['laws'] ?? array() ) as $l ) {
			if ( ! empty( $l['name'] ) ) {
				$found['law'][ (string) $l['name'] ] = true;
			}
		}
		$words = ' ' . aw_si_norm( $r->post_title . ' ' . $r->post_excerpt ) . ' ';
		foreach ( $species as $n => $label ) {
			if ( false !== strpos( $words, ' ' . $n . ' ' ) || false !== strpos( $words, ' ' . $n . 's ' ) ) {
				$found['species'][ $label ] = true;
			}
		}
		foreach ( $found as $kind => $names ) {
			foreach ( array_keys( $names ) as $name ) {
				$out[ $day ][ $kind ][ (string) $name ] = ( $out[ $day ][ $kind ][ (string) $name ] ?? 0 ) + 1;
			}
		}
	}
	return $out;
}

/**
 * The spikes of one day: names counted at least AW_SI_SPIKE_MIN times and at least 3× their 14-day average.
 *
 * @param array  $counts aw_si_sourcing_counts() for the day and the 14 days before it.
 * @param string $day    Day (Y-m-d).
 * @return array<int, array{kind: string, name: string, count: int, avg: float, growth: float|null}>
 */
function aw_si_sourcing_spikes( array $counts, string $day ): array {
	$out = array();
	foreach ( (array) ( $counts[ $day ] ?? array() ) as $kind => $names ) {
		foreach ( $names as $name => $n ) {
			if ( $n < AW_SI_SPIKE_MIN ) {
				continue;
			}
			$sum = 0;
			for ( $i = 1; $i <= AW_SI_SPIKE_WINDOW; $i++ ) {
				$sum += (int) ( $counts[ gmdate( 'Y-m-d', strtotime( $day . " -{$i} days" ) ) ][ $kind ][ $name ] ?? 0 );
			}
			$avg = $sum / AW_SI_SPIKE_WINDOW;
			if ( $n >= AW_SI_SPIKE_RATIO * $avg ) {
				$out[] = array(
					'kind'   => (string) $kind,
					'name'   => (string) $name,
					'count'  => (int) $n,
					'avg'    => round( $avg, 1 ),
					'growth' => $avg > 0 ? round( $n / $avg, 2 ) : null,
				);
			}
		}
	}
	return $out;
}

/**
 * Count and flag Sourcing spikes for the last $days days (today included, so today's count is provisional until the
 * next run). Writes the daily counts to obs and the spikes to rising, unless $dry.
 *
 * @param int  $days Days to work out.
 * @param bool $dry  Work out only.
 * @return array{days: int, counts: int, spikes: array}
 */
function aw_si_sourcing_spikes_run( int $days = 2, bool $dry = false ): array {
	$tz     = wp_timezone();
	$today  = new DateTimeImmutable( 'today', $tz );
	$first  = $today->modify( '-' . ( $days - 1 ) . ' days' )->format( 'Y-m-d' );
	$counts = aw_si_sourcing_counts( $today->modify( '-' . ( $days - 1 + AW_SI_SPIKE_WINDOW ) . ' days' )->format( 'Y-m-d' ), $today->format( 'Y-m-d' ) );
	$kinds  = array(
		'publisher' => __( 'Publisher', 'aw-seo-extras' ),
		'state'     => __( 'State', 'aw-seo-extras' ),
		'species'   => __( 'Species', 'aw-seo-extras' ),
		'law'       => __( 'Law', 'aw-seo-extras' ),
	);
	$out    = array(
		'days'   => $days,
		'counts' => 0,
		'spikes' => array(),
	);
	for ( $i = 0; $i < $days; $i++ ) {
		$day = ( new DateTimeImmutable( $first, $tz ) )->modify( "+{$i} days" )->format( 'Y-m-d' );
		foreach ( (array) ( $counts[ $day ] ?? array() ) as $kind => $names ) {
			foreach ( $names as $name => $n ) {
				++$out['counts'];
				if ( ! $dry ) {
					aw_si_obs_put(
						0,
						'#sourcing:' . $kind . ':' . aw_si_norm( (string) $name ),
						'sourcing',
						(string) $kind,
						'day',
						$day,
						array(
							'value' => (int) $n,
							'txt'   => (string) $name,
						)
					);
				}
			}
		}
		foreach ( aw_si_sourcing_spikes( $counts, $day ) as $s ) {
			$s['day']        = $day;
			$out['spikes'][] = $s;
			if ( ! $dry ) {
				/* translators: 1: Publisher, State, Species or Law, 2: items, 3: date, 4: average. */
				$note = sprintf( __( 'Sourcing: %1$s in %2$d News items on %3$s, 14-day average %4$s', 'aw-seo-extras' ), $kinds[ $s['kind'] ] ?? $s['kind'], $s['count'], wp_date( 'j M', strtotime( $day . ' 12:00' ), $tz ), number_format_i18n( $s['avg'], 1 ) );
				aw_si_rising_put( $s['name'], 'sourcing', $s['growth'], $day, $note );
			}
		}
	}
	return $out;
}

/** The daily Sourcing spikes run, with its result in the run log. */
function aw_si_sourcing_spikes_cron(): void {
	if ( get_option( 'aw_si_off' ) || ! aw_si_ready() ) {
		return;
	}
	aw_si_run_start( 'rising_sourcing' );
	try {
		$r = aw_si_sourcing_spikes_run();
		aw_si_run_ok(
			'rising_sourcing',
			array(
				'rows'   => $r['counts'],
				'spikes' => count( $r['spikes'] ),
			)
		);
	} catch ( Throwable $e ) {
		aw_si_run_fail( 'rising_sourcing', $e->getMessage() );
	}
}
add_action( AW_SI_RISING_CRON, 'aw_si_sourcing_spikes_cron' );

add_action(
	'init',
	function () {
		if ( get_option( 'aw_si_off' ) ) {
			wp_clear_scheduled_hook( AW_SI_RISING_CRON ); // Switched off by Delete data.
			return;
		}
		if ( ! wp_next_scheduled( AW_SI_RISING_CRON ) ) {
			wp_schedule_event( ( new DateTimeImmutable( 'today 23:40', wp_timezone() ) )->getTimestamp(), 'daily', AW_SI_RISING_CRON );
		}
	}
);

/*
 * The Rising tab.
 */

add_filter( 'aw_si_tabs', fn( $tabs ) => array( 'rising' => __( 'Rising', 'aw-seo-extras' ) ) + (array) $tabs );

// The tab's message flags are dropped from the address after showing, so sort and page links do not repeat them.
add_filter( 'removable_query_args', fn( $args ) => array_merge( (array) $args, array( 'marked', 'pasted', 'skipped' ) ) );

add_filter(
	'set_screen_option_aw_si_rising_per_page',
	function ( $keep, $option, $value ) {
		return max( 10, min( 500, (int) $value ) );
	},
	10,
	3
);

/** Before output: screen options, and the owner's Noted / Dismiss / New (then redirect). */
function aw_si_rising_load(): void {
	add_screen_option(
		'per_page',
		array(
			'label'   => __( 'Terms per page', 'aw-seo-extras' ),
			'default' => 50,
			'option'  => 'aw_si_rising_per_page',
		)
	);
	$action = '';
	foreach ( array( 'aw_si_rising', 'action', 'action2' ) as $field ) {
		$v = isset( $_REQUEST[ $field ] ) ? sanitize_key( wp_unslash( $_REQUEST[ $field ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- checked below before any change.
		if ( in_array( $v, array( 'noted', 'dismissed', 'new' ), true ) ) {
			$action = $v;
			break;
		}
	}
	if ( ! $action ) {
		return;
	}
	check_admin_referer( 'bulk-rising' );
	if ( ! current_user_can( aw_si_cap() ) ) {
		wp_die( esc_html__( 'Not allowed.', 'aw-seo-extras' ), 403 );
	}
	$ids = array_filter( array_map( 'intval', (array) wp_unslash( $_REQUEST['rising_id'] ?? array() ) ) );
	if ( $ids ) {
		global $wpdb;
		$t  = aw_si_table( 'rising' );
		$in = implode( ',', $ids );
		$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET status = %s WHERE id IN ({$in})", $action ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integers.
	}
	$back = remove_query_arg( array( 'aw_si_rising', 'rising_id', '_wpnonce', '_wp_http_referer', 'action', 'action2', 'marked', 'pasted' ), wp_get_referer() ? wp_get_referer() : aw_si_url( 'rising' ) );
	wp_safe_redirect( add_query_arg( 'marked', $ids ? count( $ids ) : 0, $back ) );
	exit;
}
add_action( 'aw_si_load_tab_rising', 'aw_si_rising_load' );

/** The Rising tab: what it shows, the list, then the paste form. */
function aw_si_rising_tab(): void {
	require_once __DIR__ . '/class-aw-si-rising-table.php';
	// phpcs:disable WordPress.Security.NonceVerification -- messages only.
	if ( isset( $_GET['marked'] ) ) {
		$n = absint( wp_unslash( $_GET['marked'] ) );
		echo '<div class="notice ' . ( $n ? 'notice-success' : 'notice-warning' ) . ' inline"><p>' . esc_html( $n ? __( 'Saved.', 'aw-seo-extras' ) : __( 'No term was selected, so nothing changed.', 'aw-seo-extras' ) ) . '</p></div>';
	}
	if ( isset( $_GET['pasted'] ) ) {
		/* translators: 1: lines stored, 2: lines skipped. */
		echo '<div class="notice notice-success inline"><p>' . esc_html( sprintf( __( 'Pasted: %1$d lines stored, %2$d skipped.', 'aw-seo-extras' ), absint( wp_unslash( $_GET['pasted'] ) ), absint( wp_unslash( $_GET['skipped'] ?? 0 ) ) ) ) . '</p></div>';
	}
	// phpcs:enable
	echo '<p>' . esc_html__( 'Terms rising in India from free sources: Trends trending now, Wikipedia pageviews and Indian news (GDELT) daily; Google autocomplete and Trends rising related searches weekly; and spikes in the News that Sourcing fetches. Growth is how many times the usual level (– when the source gives no ratio). Nothing here changes Rank Math or the Sourcing lists: mark each term Noted or Dismissed.', 'aw-seo-extras' ) . '</p>';
	$table = new AW_SI_Rising_Table();
	$table->prepare_items();
	$table->views();
	echo '<form method="get"><input type="hidden" name="page" value="' . esc_attr( AW_SI_SLUG ) . '"><input type="hidden" name="tab" value="rising">';
	$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- navigation only.
	if ( $view ) {
		echo '<input type="hidden" name="view" value="' . esc_attr( $view ) . '">';
	}
	$table->search_box( __( 'Search terms', 'aw-seo-extras' ), 'aw-si-rising' );
	$table->display();
	echo '</form>';
	aw_si_rising_paste_form();
}
add_action( 'aw_si_tab_rising', 'aw_si_rising_tab' );

/** The paste form: Trends figures when the worker was refused (plan §5.3 fallback). */
function aw_si_rising_paste_form(): void {
	echo '<h2>' . esc_html__( 'Paste Trends figures', 'aw-seo-extras' ) . '</h2>';
	echo '<p>' . esc_html__( 'When Google Trends refuses the worker (the notice above says so), copy the figures from trends.google.com (India) and paste them here, one per line: the search, a comma, then the figure.', 'aw-seo-extras' ) . '</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_si_rising_paste">';
	wp_nonce_field( 'aw_si_rising_paste' );
	echo '<table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row">' . esc_html__( 'What it is', 'aw-seo-extras' ) . '</th><td><fieldset>';
	echo '<label><input type="radio" name="kind" value="related" checked> ' . esc_html__( 'Rising related searches: the search, then its rise (+250% or Breakout)', 'aw-seo-extras' ) . '</label><br>';
	echo '<label><input type="radio" name="kind" value="interest"> ' . esc_html__( 'Interest over 12 months: the keyword, then its average with "animal welfare" in the same comparison scaled to 100', 'aw-seo-extras' ) . '</label>';
	echo '</fieldset></td></tr>';
	echo '<tr><th scope="row"><label for="aw-si-paste">' . esc_html__( 'Lines', 'aw-seo-extras' ) . '</label></th><td><textarea id="aw-si-paste" name="lines" rows="8" class="large-text code" placeholder="stray dog bite, +140%"></textarea>';
	echo '<p class="description">' . esc_html__( 'Interest lines are stored only for searches that are keyword records already.', 'aw-seo-extras' ) . '</p></td></tr>';
	echo '</tbody></table>';
	submit_button( __( 'Store the figures', 'aw-seo-extras' ), 'secondary' );
	echo '</form>';
}

/**
 * Parse pasted lines into [term, figure text]. A line without a comma is skipped.
 *
 * @param string $text Pasted text.
 * @return array<int, array{0: string, 1: string}>
 */
function aw_si_rising_paste_lines( string $text ): array {
	$out = array();
	foreach ( preg_split( '/\R/u', $text ) as $line ) {
		$pos = strrpos( $line, ',' );
		if ( false === $pos ) {
			$pos = strrpos( $line, "\t" ); // A copy from a Trends table is tab-separated.
		}
		if ( false === $pos ) {
			continue;
		}
		$term = trim( substr( $line, 0, $pos ) );
		$fig  = trim( substr( $line, $pos + 1 ) );
		if ( '' !== $term && '' !== $fig ) {
			$out[] = array( $term, $fig );
		}
	}
	return array_slice( $out, 0, 500 );
}

add_action(
	'admin_post_aw_si_rising_paste',
	function () {
		if ( ! current_user_can( aw_si_cap() ) || ! check_admin_referer( 'aw_si_rising_paste' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'aw-seo-extras' ), 403 );
		}
		aw_si_install();
		$kind  = isset( $_POST['kind'] ) && 'interest' === $_POST['kind'] ? 'interest' : 'related';
		$lines = aw_si_rising_paste_lines( isset( $_POST['lines'] ) ? sanitize_textarea_field( wp_unslash( $_POST['lines'] ) ) : '' );
		$today = ( new DateTimeImmutable( 'today', wp_timezone() ) )->format( 'Y-m-d' );
		$map   = aw_si_kw_map();
		$done  = 0;
		$skip  = 0;
		foreach ( $lines as list( $term, $fig ) ) {
			if ( 'interest' === $kind ) {
				$id = (int) ( $map[ aw_si_norm( $term ) ] ?? 0 );
				$v  = str_replace( array( ',', '%' ), '', $fig );
				if ( ! $id || ! is_numeric( $v ) ) {
					++$skip;
					continue;
				}
				$ok = aw_si_obs_put(
					$id,
					'',
					'trends',
					'index',
					'12m',
					substr( $today, 0, 8 ) . '01',
					array(
						'value' => (float) $v,
						'txt'   => 'animal welfare = 100 (pasted)',
					)
				);
			} else {
				$breakout = false !== stripos( $fig, 'breakout' );
				$pct      = str_replace( array( '+', ',', '%' ), '', $fig );
				if ( ! $breakout && ! is_numeric( $pct ) ) {
					++$skip;
					continue;
				}
				$growth = $breakout ? null : round( 1 + (float) $pct / 100, 2 );
				/* translators: %s: the figure pasted, such as +250% or Breakout. */
				$ok = in_array( aw_si_rising_put( $term, 'trends_related', $growth, $today, sprintf( __( 'Trends: rising related search, %s (pasted by the owner)', 'aw-seo-extras' ), $fig ) ), array( 'created', 'updated' ), true );
			}
			$done += $ok ? 1 : 0;
			$skip += $ok ? 0 : 1;
		}
		if ( $done ) {
			aw_si_run_ok(
				'interest' === $kind ? 'rising_trends_interest' : 'rising_trends_related',
				array(
					'rows'   => $done,
					'pasted' => true,
				)
			);
		}
		wp_safe_redirect(
			add_query_arg(
				array(
					'pasted'  => $done,
					'skipped' => $skip,
				),
				aw_si_url( 'rising' )
			)
		);
		exit;
	}
);

/**
 * The top rising terms (highest growth first, then the newest), for WP-CLI and the dry run.
 *
 * @param int    $limit Rows.
 * @param string $since Seen on or after this day (Y-m-d); the last 7 days when ''.
 */
function aw_si_rising_top( int $limit = 10, string $since = '' ): array {
	global $wpdb;
	if ( ! aw_si_ready() ) {
		return array();
	}
	$t     = aw_si_table( 'rising' );
	$since = $since ? $since : ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( '-7 days' )->format( 'Y-m-d' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT term, sources, growth, first_seen, last_seen, page, action, status FROM {$t} WHERE last_seen >= %s AND status <> 'dismissed' ORDER BY growth IS NULL, growth DESC, last_seen DESC LIMIT %d", $since, $limit ), ARRAY_A );
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Search Insights rising terms.
	 *
	 * ## EXAMPLES
	 *
	 *     wp aw-si-rising spikes --days=7 --dry-run
	 *     wp aw-si-rising top --limit=10
	 */
	WP_CLI::add_command(
		'aw-si-rising',
		function ( $args, $assoc ) {
			$what = $args[0] ?? 'top';
			if ( 'spikes' === $what ) {
				$r = aw_si_sourcing_spikes_run( max( 1, min( 60, (int) ( $assoc['days'] ?? 7 ) ) ), isset( $assoc['dry-run'] ) );
				WP_CLI::log( sprintf( '%d daily counts over %d days, %d spikes%s', $r['counts'], $r['days'], count( $r['spikes'] ), isset( $assoc['dry-run'] ) ? ' (dry run: nothing stored)' : '' ) );
				WP_CLI\Utils\format_items( 'table', $r['spikes'], array( 'day', 'kind', 'name', 'count', 'avg', 'growth' ) );
				return;
			}
			WP_CLI\Utils\format_items( 'table', aw_si_rising_top( (int) ( $assoc['limit'] ?? 10 ) ), array( 'term', 'sources', 'growth', 'first_seen', 'page', 'action', 'status' ) );
		}
	);
}
