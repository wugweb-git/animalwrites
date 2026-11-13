<?php
/**
 * Search Insights, phase 3: rising terms (plan §3 `rising`, §5.3, §5.5, §7–8; owner, 24 Oct 2026).
 *
 * - REST route aw/v1/search-insights for the workers' search-insights job (animal-writes-workers,
 *   aw_workers/search_insights.py), signed in as the ingest user (aw_si_api_allowed()):
 *   GET gives the job its lists (English keywords, topics, species, watchlists, seeds); POST takes one source's rising
 *   terms and figures and records that source's run (Trends trending now, Wikipedia, GDELT daily; autocomplete and
 *   Trends related queries weekly; Trends interest monthly).
 * - News spikes (counts across our own News items) were removed on 3 Nov 2026; their Noted rows stay as history.
 * - The Rising tab (filter aw_si_tabs): the rising terms with their sources, growth, first seen, matching page and
 *   suggested action; the owner marks them Noted or Dismissed. A paste form takes Trends figures when the worker is
 *   refused.
 *
 * Read-only towards everything else: it writes only wp_aw_si_rising and wp_aw_si_obs (never Rank Math or the Sourcing
 * lists). English only until phase 5. The Search Console part of Rising (new queries) stays off until 8 weeks of
 * history (owner, 24 Oct 2026).
 *
 * Figures that belong to a term rather than a keyword record are stored in obs with page "#<source>:<norm>" (never a site
 * path, which always starts with "/") and the matching keyword's ID (0 when none), so they stay one row per term, day
 * and metric; nothing reads them by keyword ID (Search Console and Trends T rows have their own sources).
 *
 * @package insightweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SI_RISING_CRON      = 'aw_si_rising_spikes'; // The removed News spikes' event, kept only to clear it.
const AW_SI_RISING_MAX_ROWS  = 500; // Per POST: rising terms.
const AW_SI_RISING_MAX_OBS   = 3000; // Per POST: figures.
const AW_SI_RISING_NEWS_SRCS = array( 'trends_rss', 'gdelt', 'paste_gnews' ); // Sources that mean "in the news".

require_once __DIR__ . '/rising-drops.php'; // what each run dropped and why (12 Nov 2026)

/**
 * The rising sources: key => label, schedule and whether the workers send it.
 *
 * @return array<string, array{label: string, every: int, worker: bool}>
 */
function aw_si_rising_sources(): array {
	return array(
		'trends_rss'      => array(
			'label'  => __( 'Trends: trending now', 'insightweb' ),
			'every'  => DAY_IN_SECONDS,
			'worker' => true,
		),
		'wikipedia'       => array(
			'label'  => __( 'Wikipedia pageviews', 'insightweb' ),
			'every'  => DAY_IN_SECONDS,
			'worker' => true,
		),
		'gdelt'           => array(
			'label'  => __( 'News (GDELT)', 'insightweb' ),
			'every'  => DAY_IN_SECONDS,
			'worker' => true,
		),
		'autocomplete'    => array(
			'label'  => __( 'Google autocomplete (English)', 'insightweb' ),
			'every'  => WEEK_IN_SECONDS,
			'worker' => true,
		),
		'trends_related'  => array(
			'label'  => __( 'Trends: rising related searches', 'insightweb' ),
			'every'  => WEEK_IN_SECONDS,
			'worker' => true,
		),
		'trends_interest' => array(
			'label'  => __( 'Trends interest (animal welfare = 100)', 'insightweb' ),
			'every'  => 31 * DAY_IN_SECONDS,
			'worker' => true,
		),
		// Keyword tools (tool-imports.php): pasted on Settings → Imports by hand or by the scheduled task.
		'ubersuggest'     => array(
			'label'  => __( 'Ubersuggest (import)', 'insightweb' ),
			'every'  => DAY_IN_SECONDS,
			'worker' => false,
			'import' => true,
		),
		'vidiq'           => array(
			'label'  => __( 'vidIQ (import)', 'insightweb' ),
			'every'  => WEEK_IN_SECONDS,
			'worker' => false,
			'import' => true,
		),
		'openseo'         => array(
			'label'  => __( 'OpenSEO (import)', 'insightweb' ),
			'every'  => 31 * DAY_IN_SECONDS,
			'worker' => false,
			'import' => true,
		),
		// Pasted by hand from what a person read (owner, 12 Nov 2026: "put it"): each its own source, never Trends data,
		// never late. Each line carries the link it was read at; the day read is the paste form's date.
		'paste_x'         => array(
			'label'  => __( 'X (pasted)', 'insightweb' ),
			'every'  => WEEK_IN_SECONDS,
			'worker' => false,
			'manual' => true,
		),
		'paste_gnews'     => array(
			'label'  => __( 'Google News (pasted)', 'insightweb' ),
			'every'  => WEEK_IN_SECONDS,
			'worker' => false,
			'manual' => true,
		),
		'paste_instagram' => array(
			'label'  => __( 'Instagram (pasted)', 'insightweb' ),
			'every'  => WEEK_IN_SECONDS,
			'worker' => false,
			'manual' => true,
		),
		'paste_facebook'  => array(
			'label'  => __( 'Facebook (pasted)', 'insightweb' ),
			'every'  => WEEK_IN_SECONDS,
			'worker' => false,
			'manual' => true,
		),
		'paste_other'     => array(
			'label'  => __( 'Other source (pasted)', 'insightweb' ),
			'every'  => WEEK_IN_SECONDS,
			'worker' => false,
			'manual' => true,
		),
		'sourcing'        => array( // Removed 3 Nov 2026: only its old rows show, as history.
			'label'   => __( 'Our News counts (stopped 3 Nov 2026)', 'insightweb' ),
			'every'   => DAY_IN_SECONDS,
			'worker'  => false,
			'history' => true,
		),
	);
}

/**
 * Whether the GDELT source is used (1 Nov 2026: off by default). GDELT answers HTTP 429 to the workers' and the site's
 * addresses alike (its own throttling), so while it is off its runs are not stored and it is neither late nor failing.
 * Settings → News (GDELT) switches it back on (option aw_si_gdelt_on).
 */
function aw_si_gdelt_on(): bool {
	return (bool) get_option( 'aw_si_gdelt_on', 0 );
}

/**
 * Switch GDELT on or off (Settings → News (GDELT), and the Settings screen's route). Nothing is written when it is
 * already so.
 *
 * @param bool $on On.
 * @return bool Whether the option was written.
 */
function aw_si_gdelt_save( bool $on ): bool {
	if ( aw_si_gdelt_on() === $on ) {
		return false;
	}
	update_option( 'aw_si_gdelt_on', $on ? 1 : 0, false );
	return true;
}

/** Whether the last Trends interest run failed with nothing stored after it (the Keywords list then says "source failed"). */
function aw_si_trends_failed(): bool {
	static $f = null;
	if ( null === $f ) {
		$r = aw_si_runs()['rising_trends_interest'] ?? array();
		$f = ! empty( $r['error'] ) && (int) ( $r['error']['time'] ?? 0 ) >= (int) ( $r['ok'] ?? 0 );
	}
	return $f;
}

/**
 * Whether a rising source's last problem is the service refusing our servers (HTTP 429 or 403) with nothing stored
 * since (2 Nov 2026). Google Trends and GDELT do this to the workers' and the site's addresses alike, so it is a known
 * limit of the free source, not a fault: the panel says so in one line and Site Health does not count it as failing.
 *
 * @param string $src Source key in the run log (rising_*).
 */
function aw_si_rising_blocked( string $src ): bool {
	if ( 0 !== strpos( $src, 'rising_' ) ) {
		return false;
	}
	$r = aw_si_runs()[ $src ] ?? array();
	return ! empty( $r['error'] ) && (int) ( $r['error']['time'] ?? 0 ) >= (int) ( $r['ok'] ?? 0 ) && (bool) preg_match( '/HTTP (429|403)\b/', (string) ( $r['error']['message'] ?? '' ) );
}

// Each rising source has its own entry in the run log, the notices and Site Health ("rising_" keeps them apart). A source
// that has not reported yet joins once twice its schedule has passed since this code first loaded, so a deploy does not
// show seven sources "late" against the date phase 1 was installed.
add_filter(
	'aw_si_sources',
	function ( $sources ) {
		$since = (int) get_option( 'aw_si_rising_since' );
		if ( ! $since ) {
			$since = time();
			add_option( 'aw_si_rising_since', $since, '', false );
		}
		$runs = aw_si_runs();
		foreach ( aw_si_rising_sources() as $key => $s ) {
			if ( empty( $runs[ 'rising_' . $key ] ) && time() - $since <= 2 * $s['every'] ) {
				continue;
			}
			if ( ! empty( $s['history'] ) || ! empty( $s['manual'] ) ) {
				continue; // A removed source, or one pasted by hand: never late or failing.
			}
			if ( ! empty( $s['import'] ) && empty( $runs[ 'rising_' . $key ] ) ) {
				continue; // An import is listed once it has sent something, so it is never late before.
			}
			if ( 'gdelt' === $key && ! aw_si_gdelt_on() ) {
				continue; // switched off: not late, not failing (Settings → News (GDELT))
			}
			$sources[ 'rising_' . $key ] = array(
				'label' => $s['label'],
				'every' => $s['every'],
			);
		}
		return $sources;
	}
);

/** Animal names counted in the Sourcing spikes and sent to the worker's Trends filter (singular; plurals fold). Words that are
 * mostly something else (bat, python, kite, bull, shark) are left out. */
function aw_si_species(): array {
	$list = array( 'dog', 'puppy', 'cat', 'kitten', 'cow', 'cattle', 'buffalo', 'calf', 'goat', 'sheep', 'pig', 'horse', 'donkey', 'mule', 'camel', 'monkey', 'macaque', 'langur', 'elephant', 'tiger', 'leopard', 'lion', 'cheetah', 'bear', 'deer', 'blackbuck', 'peacock', 'peafowl', 'pigeon', 'parrot', 'bird', 'vulture', 'eagle', 'owl', 'crow', 'snake', 'cobra', 'crocodile', 'turtle', 'tortoise', 'dolphin', 'whale', 'shark', 'pangolin', 'rhino', 'rhinoceros', 'wolf', 'jackal', 'hyena', 'boar', 'squirrel', 'rabbit', 'chicken', 'hen', 'poultry' );
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
		$eff   = aw_si_eff_sql();
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
		'exclude'        => array( 'anime', 'manga', 'bungo', 'movie', 'film', 'song', 'lyrics', 'season', 'episode', 'cast', 'trailer', 'netflix', 'web series', 'ott', 'box office', 'game', 'meme', 'wallpaper', 'cartoon', 'meaning in', 'cricket', 'ipl', 'vs', 'odi', 't20', 'match', 'score', 'shroff' ),
	);
	return (array) apply_filters( 'aw_si_rising_lists', $lists );
}

/**
 * The keyword record a term matches, and its page: the same record (same normal form), else the longest keyword of two
 * or more words the term contains; the page is that keyword's page, else a published page whose title contains the term.
 *
 * @param string $norm          Normalised term.
 * @param string $term          Term as written.
 * @param bool   $look_up_posts Also search published titles when no keyword has a page (new terms only).
 * @return array{0: int, 1: string, 2: string} Keyword ID (0: none), site path or page label ('' when none), and the
 *                                             keyword's suggested action from the daily calculation (aw_si_decide).
 */
function aw_si_rising_match( string $norm, string $term, bool $look_up_posts = true ): array {
	global $wpdb;
	static $kw = null;
	$k         = aw_si_table( 'keywords' );
	if ( null === $kw ) {
		$kw = array();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
		foreach ( (array) $wpdb->get_results( "SELECT id, norm, page, action FROM {$k} WHERE entity = 0 AND lang = 'en'" ) as $r ) {
			$kw[ (string) $r->norm ] = array( (int) $r->id, (string) $r->page, (string) $r->action );
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
	$act  = $hit ? $hit[2] : '';
	if ( '' === $page && $look_up_posts && mb_strlen( $term ) >= 4 ) {
		// A title search (a scan of published posts' titles): only for a term seen for the first time.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$post = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ('page', 'post', 'aw_legal', 'aw_campaign', 'aw_news') AND post_title LIKE %s ORDER BY post_type = 'aw_news', post_date DESC LIMIT 1", '%' . $wpdb->esc_like( $term ) . '%' ) );
		if ( $post ) {
			$page = aw_si_path( (string) get_permalink( (int) $post ) );
		}
	}
	// The page as it is reached now: an old address that redirects counts as its target (the same rule as Keywords).
	return array( $id, '' !== $page ? aw_si_final_path( $page ) : '', $act );
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
	list( $kw_id, $page, $kw_action ) = aw_si_rising_match( $norm, $term, ! $old );
	if ( $old && '' === $page ) {
		$page = (string) $old->page; // The title search ran when the term was new.
	}
	$row = array(
		'norm'       => $norm,
		'term'       => $old ? (string) $old->term : $term,
		'sources'    => implode( ',', $srcs ),
		'growth'     => $growth,
		'first_seen' => $first,
		'last_seen'  => $last,
		'kw_id'      => $kw_id,
		'page'       => mb_substr( $page, 0, 255 ),
		// A term that is one of our keywords takes that keyword's action from the daily calculation (aw_si_decide), so
		// Rising and Keywords never suggest two different things for one search; other terms use the rising rule.
		'action'     => '' !== $kw_action ? $kw_action : aw_si_rising_action( $term, $srcs, $page ),
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
	if ( 'gdelt' === $source && ! aw_si_gdelt_on() ) {
		return array(
			'source'  => $source,
			'off'     => true, // answered without an error, so the worker does not report a failure
			'rising'  => array(),
			'figures' => 0,
			'skipped' => 0,
		);
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
	if ( get_option( 'aw_si_off' ) ) {
		return array( 'error' => 'Search Insights is switched off (its data was deleted); nothing stored' );
	}
	aw_si_install(); // The REST request may come before any admin visit has built the tables.
	$key = 'rising_' . $source;
	aw_si_run_start( $key );
	$refused = array(); // terms the site itself refused: listed before the worker's own drops, so the cap never hides them
	foreach ( $rising as $r ) {
		$term = is_scalar( $r['term'] ?? '' ) ? sanitize_text_field( (string) ( $r['term'] ?? '' ) ) : '';
		$d    = is_scalar( $r['d'] ?? '' ) ? aw_si_rising_date( (string) ( $r['d'] ?? '' ) ) : '';
		if ( ! $d || ! is_scalar( $r['term'] ?? '' ) || ! is_scalar( $r['note'] ?? '' ) ) {
			++$out['skipped'];
			$refused[] = array( $term, 'bad-row' );
			continue;
		}
		$g                        = isset( $r['growth'] ) && is_numeric( $r['growth'] ) ? (float) $r['growth'] : null;
		$result                   = aw_si_rising_put( $term, $source, $g, $d, sanitize_text_field( (string) ( $r['note'] ?? '' ) ) );
		$out['rising'][ $result ] = ( $out['rising'][ $result ] ?? 0 ) + 1;
		if ( ! in_array( $result, array( 'created', 'updated' ), true ) ) {
			$refused[] = array( $term, $result ); // not-english, empty or db-error
		}
	}
	$map = aw_si_kw_map();
	foreach ( $obs as $o ) {
		if ( ! is_scalar( $o['metric'] ?? '' ) || ! is_scalar( $o['d'] ?? '' ) || ! is_scalar( $o['term'] ?? '' ) || ! is_scalar( $o['txt'] ?? '' ) ) {
			++$out['skipped'];
			continue;
		}
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
				'txt'   => $target[2] ? sanitize_text_field( (string) ( $o['txt'] ?? '' ) ) : $term,
			)
		);
		$out['figures'] += $ok ? 1 : 0;
		$out['skipped'] += $ok ? 0 : 1;
	}
	$info = array(
		'rows'    => (int) ( $out['rising']['created'] ?? 0 ) + (int) ( $out['rising']['updated'] ?? 0 ) + $out['figures'],
		'rising'  => count( $rising ),
		'figures' => $out['figures'],
		'worker'  => array_map( 'sanitize_text_field', array_filter( (array) ( $body['info'] ?? array() ), 'is_scalar' ) ),
		'dropped' => array_slice( array_merge( $refused, aw_si_rising_worker_dropped( $body['dropped'] ?? array() ) ), 0, AW_SI_RISING_MAX_DROPPED ), // shown on the Rising tab (12 Nov 2026)
	);
	// What 0 rows means (1 Nov 2026): the source refused ("source failed"), the run only recorded the usual level of each
	// term ("baseline"), or the source answered and nothing passed the filters ("no rows"). Never "no demand".
	$info['status'] = $info['rows'] ? 'ok' : ( $error ? 'source failed' : ( (int) ( $info['worker']['baseline'] ?? 0 ) > 0 ? 'baseline' : 'no rows' ) );
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

/** Where a hand-added term was seen: form value => label (the paste sources, paste_<value>). */
function aw_si_rising_add_where(): array {
	return aw_si_rising_paste_kinds();
}

/** The Add term form on the Rising tab (12 Nov 2026): one term seen by a person, stored like any rising term. */
function aw_si_rising_add_form(): void {
	$today = ( new DateTimeImmutable( 'today', wp_timezone() ) )->format( 'Y-m-d' );
	echo '<h2>' . esc_html__( 'Add term', 'insightweb' ) . '</h2>';
	echo '<p>' . esc_html__( 'A term you saw rising on X, Instagram or elsewhere. It shows in the list above as New, under the source you pick.', 'insightweb' ) . '</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_si_rising_add">';
	wp_nonce_field( 'aw_si_rising_add' );
	echo '<table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row"><label for="aw-si-add-term">' . esc_html__( 'Term', 'insightweb' ) . '</label></th><td><input type="text" id="aw-si-add-term" name="term" class="regular-text" maxlength="255" required></td></tr>';
	echo '<tr><th scope="row"><label for="aw-si-add-where">' . esc_html__( 'Seen on', 'insightweb' ) . '</label></th><td><select id="aw-si-add-where" name="where">';
	foreach ( aw_si_rising_add_where() as $k => $label ) {
		echo '<option value="' . esc_attr( $k ) . '">' . esc_html( ucfirst( $label ) ) . '</option>';
	}
	echo '</select></td></tr>';
	echo '<tr><th scope="row"><label for="aw-si-add-label">' . esc_html__( 'Source label', 'insightweb' ) . '</label></th><td><input type="text" id="aw-si-add-label" name="label" class="regular-text" maxlength="60" placeholder="' . esc_attr__( 'X scan', 'insightweb' ) . '"><p class="description">' . esc_html__( 'How it was found, such as X scan or Instagram scan.', 'insightweb' ) . '</p></td></tr>';
	echo '<tr><th scope="row"><label for="aw-si-add-note">' . esc_html__( 'Note', 'insightweb' ) . '</label></th><td><input type="text" id="aw-si-add-note" name="note" class="large-text" maxlength="160"><p class="description">' . esc_html__( 'Why it matters, or the link it was seen at. Never personal data.', 'insightweb' ) . '</p></td></tr>';
	echo '<tr><th scope="row"><label for="aw-si-add-day">' . esc_html__( 'Date seen', 'insightweb' ) . '</label></th><td><input type="date" id="aw-si-add-day" name="day" value="' . esc_attr( $today ) . '" max="' . esc_attr( $today ) . '"></td></tr>';
	echo '</tbody></table>';
	submit_button( __( 'Add term', 'insightweb' ), 'secondary' );
	echo '</form>';
}

/**
 * Store a hand-added term.
 *
 * @param string $term  Term.
 * @param string $where A key of aw_si_rising_add_where().
 * @param string $label Source label, such as X scan.
 * @param string $note  Note.
 * @param string $day   Date seen (Y-m-d), within the last 400 days.
 * @return string created, updated, or why it was not stored (where, day, not-english, empty, db-error).
 */
function aw_si_rising_add( string $term, string $where, string $label, string $note, string $day ): string {
	$kinds = aw_si_rising_add_where();
	if ( ! isset( $kinds[ $where ] ) ) {
		return 'where';
	}
	$day = aw_si_rising_date( $day );
	if ( '' === $day ) {
		return 'day';
	}
	$label = '' !== trim( $label ) ? trim( $label ) : $kinds[ $where ];
	/* translators: 1: source label, 2: date seen, 3: note. */
	$text   = trim( sprintf( __( 'Added by hand (%1$s, %2$s): %3$s', 'insightweb' ), $label, wp_date( 'j M Y', (int) strtotime( $day . ' 12:00:00' ) ), $note ), ': ' );
	$result = aw_si_rising_put( $term, 'paste_' . $where, null, $day, $text );
	if ( in_array( $result, array( 'created', 'updated' ), true ) ) {
		aw_si_run_ok(
			'rising_paste_' . $where,
			array(
				'rows'   => 1,
				'pasted' => true,
			)
		);
	}
	return $result;
}

add_action(
	'admin_post_aw_si_rising_add',
	function () {
		if ( ! current_user_can( aw_si_cap() ) || ! check_admin_referer( 'aw_si_rising_add' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'insightweb' ), 403 );
		}
		if ( get_option( 'aw_si_off' ) ) {
			wp_die(
				esc_html__( 'Search Insights is off (its data was deleted), so nothing was stored. Settings → Run the daily update starts it again.', 'insightweb' ),
				'',
				array(
					'back_link' => true,
					'response'  => 409,
				)
			);
		}
		aw_si_install();
		$get   = fn( string $k ) => isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked above.
		$result = aw_si_rising_add( mb_substr( $get( 'term' ), 0, 255 ), sanitize_key( $get( 'where' ) ), mb_substr( $get( 'label' ), 0, 60 ), mb_substr( $get( 'note' ), 0, 160 ), $get( 'day' ) );
		$ok     = in_array( $result, array( 'created', 'updated' ), true );
		wp_safe_redirect( add_query_arg( $ok ? 'added' : 'not_added', $ok ? $result : sanitize_key( $result ), aw_si_return_url( 'rising' ) ) );
		exit;
	}
);

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'aw/v1',
			'/search-insights',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => 'aw_si_api_allowed',
					'callback'            => fn() => rest_ensure_response( aw_si_rising_lists() ),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => 'aw_si_api_allowed',
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

// News spikes ("In the news": species, states and laws frequent in our own News) were removed on 3 Nov 2026 (owner):
// they measured our feed, not search demand. Their rows stay as history (Noted rows included); the old daily event is
// cleared.
add_action(
	'init',
	function () {
		if ( wp_next_scheduled( AW_SI_RISING_CRON ) ) {
			wp_clear_scheduled_hook( AW_SI_RISING_CRON );
		}
	}
);

/*
 * The Rising tab.
 */

add_filter( 'aw_si_tabs', fn( $tabs ) => array( 'rising' => __( 'Rising', 'insightweb' ) ) + (array) $tabs );

// A month of rising sightings keeps its last growth, never a sum of ratios (rollup.php).
add_filter(
	'aw_si_last_value_metrics',
	function ( $metrics ) {
		foreach ( array_keys( aw_si_rising_sources() ) as $key ) {
			$metrics[] = 'rising:' . $key;
		}
		return $metrics;
	}
);

// Deactivating the plugin removes the spikes event, as it does Search Insights' own (aw-seo-extras.php).
add_filter( 'aw_si_cron_hooks', fn( $h ) => array_merge( (array) $h, array( AW_SI_RISING_CRON ) ) );

// The tab's message flags are dropped from the address after showing, so sort and page links do not repeat them.
add_filter( 'removable_query_args', fn( $args ) => array_merge( (array) $args, array( 'marked', 'pasted', 'skipped', 'added', 'not_added' ) ) );

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
			'label'   => __( 'Terms per page', 'insightweb' ),
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
		wp_die( esc_html__( 'Not allowed.', 'insightweb' ), 403 );
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
		wp_admin_notice( esc_html( $n ? __( 'Saved.', 'insightweb' ) : __( 'No term was selected, so nothing changed.', 'insightweb' ) ), array( 'type' => $n ? 'success' : 'warning', 'additional_classes' => array( 'inline' ) ) );
	}
	if ( isset( $_GET['pasted'] ) ) {
		/* translators: 1: lines stored, 2: lines skipped. */
		wp_admin_notice( esc_html( sprintf( __( 'Pasted: %1$d lines stored, %2$d skipped.', 'insightweb' ), absint( wp_unslash( $_GET['pasted'] ) ), absint( wp_unslash( $_GET['skipped'] ?? 0 ) ) ) ), array( 'type' => 'success', 'additional_classes' => array( 'inline' ) ) );
	}
	if ( isset( $_GET['added'] ) ) {
		$msg = 'updated' === sanitize_key( wp_unslash( $_GET['added'] ) ) ? __( 'Term added to the one already in the list (its source and dates updated).', 'insightweb' ) : __( 'Term added: it shows under New.', 'insightweb' );
		wp_admin_notice( esc_html( $msg ), array( 'type' => 'success', 'additional_classes' => array( 'inline' ) ) );
	}
	if ( isset( $_GET['not_added'] ) ) {
		$why = array(
			'day'         => __( 'the date seen must be today or within the last 400 days', 'insightweb' ),
			'where'       => __( 'pick where it was seen', 'insightweb' ),
			'empty'       => __( 'the term is empty or longer than 255 characters', 'insightweb' ),
			'not-english' => __( 'only English terms are kept for now', 'insightweb' ),
		);
		$key = sanitize_key( wp_unslash( $_GET['not_added'] ) );
		/* translators: %s: the reason. */
		wp_admin_notice( esc_html( sprintf( __( 'Term not added: %s.', 'insightweb' ), $why[ $key ] ?? __( 'it could not be stored', 'insightweb' ) ) ), array( 'type' => 'warning', 'additional_classes' => array( 'inline' ) ) );
	}
	// phpcs:enable
	echo '<p>' . esc_html__( 'Terms rising in India from free sources: Trends trending now, Wikipedia pageviews and Indian news (GDELT) daily; Google autocomplete and Trends rising related searches weekly. Growth is how many times the usual level (– when the source gives no ratio). Nothing here changes Rank Math or the Sourcing lists: mark each term Noted or Dismissed.', 'insightweb' ) . '</p>';
	$table = new AW_SI_Rising_Table();
	$table->prepare_items();
	$table->views();
	echo '<form method="get"><input type="hidden" name="page" value="' . esc_attr( AW_SI_SLUG ) . '"><input type="hidden" name="tab" value="rising">';
	$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- navigation only.
	if ( $view ) {
		echo '<input type="hidden" name="view" value="' . esc_attr( $view ) . '">';
	}
	$table->search_box( __( 'Search terms', 'insightweb' ), 'aw-si-rising' );
	$table->display();
	echo '</form>';
	aw_si_rising_drops_section();
	aw_si_rising_add_form();
	aw_si_rising_paste_form();
}
add_action( 'aw_si_tab_rising', 'aw_si_rising_tab' );

/** The paste form: Trends figures when the worker was refused (plan §5.3 fallback), and topics read elsewhere. */
function aw_si_rising_paste_form(): void {
	echo '<h2>' . esc_html__( 'Paste Trends figures or topics read elsewhere', 'insightweb' ) . '</h2>';
	echo '<p>' . esc_html__( 'When Google Trends refuses the worker (the notice above says so), copy the figures from trends.google.com (India) and paste them here, one per line: the search, a comma, then the figure.', 'insightweb' ) . '</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_si_rising_paste">';
	wp_nonce_field( 'aw_si_rising_paste' );
	echo '<table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row">' . esc_html__( 'What it is', 'insightweb' ) . '</th><td><fieldset>';
	echo '<label><input type="radio" name="kind" value="related" checked> ' . esc_html__( 'Rising related searches: the search, then its rise (+250% or Breakout)', 'insightweb' ) . '</label><br>';
	echo '<label><input type="radio" name="kind" value="interest"> ' . esc_html__( 'Interest over 12 months: the keyword, then its average with "animal welfare" in the same comparison scaled to 100', 'insightweb' ) . '</label>';
	foreach ( aw_si_rising_paste_kinds() as $key => $label ) {
		/* translators: %s: where it was read, such as X or Google News. */
		echo '<br><label><input type="radio" name="kind" value="' . esc_attr( $key ) . '"> ' . esc_html( sprintf( __( 'Read on %s: the topic, then the link it was read at (never Trends figures)', 'insightweb' ), $label ) ) . '</label>';
	}
	echo '</fieldset></td></tr>';
	echo '<tr><th scope="row"><label for="aw-si-paste-day">' . esc_html__( 'Day read', 'insightweb' ) . '</label></th><td><input type="date" id="aw-si-paste-day" name="day" value="' . esc_attr( ( new DateTimeImmutable( 'today', wp_timezone() ) )->format( 'Y-m-d' ) ) . '"> <span class="description">' . esc_html__( 'For the X, Google News, Instagram, Facebook and other lines.', 'insightweb' ) . '</span></td></tr>';
	echo '<tr><th scope="row"><label for="aw-si-paste">' . esc_html__( 'Lines', 'insightweb' ) . '</label></th><td><textarea id="aw-si-paste" name="lines" rows="8" class="large-text code" placeholder="stray dog bite, +140%&#10;stray dog bite, https://x.com/…"></textarea>';
	echo '<p class="description">' . esc_html__( 'Interest lines are stored only for searches that are keyword records already.', 'insightweb' ) . '</p></td></tr>';
	echo '</tbody></table>';
	submit_button( __( 'Store the figures', 'insightweb' ), 'secondary' );
	echo '</form>';
}

/** The pasted kinds that are not Trends: form value => where it was read. Their source key is "paste_" + value. */
function aw_si_rising_paste_kinds(): array {
	return array(
		'x'         => __( 'X', 'insightweb' ),
		'gnews'     => __( 'Google News', 'insightweb' ),
		'instagram' => __( 'Instagram', 'insightweb' ),
		'facebook'  => __( 'Facebook', 'insightweb' ),
		'other'     => __( 'another source', 'insightweb' ),
	);
}

/**
 * Parse pasted lines into [topic, link]: the topic, then a comma or tab, then an http(s) link at the end of the line.
 * Lines without a link are skipped (every sighting keeps where it was read).
 *
 * @param string $text Pasted text.
 * @return array<int, array{0: string, 1: string}>
 */
function aw_si_rising_paste_link_lines( string $text ): array {
	$out = array();
	foreach ( preg_split( '/\R/u', $text ) as $line ) {
		if ( preg_match( '#^(.*?)(?:\t+|\s*,\s*)(https?://\S+)\s*$#iu', trim( $line ), $m ) && '' !== trim( $m[1] ) ) {
			$url = esc_url_raw( $m[2], array( 'http', 'https' ) );
			if ( '' !== $url ) {
				$out[] = array( trim( $m[1] ), $url );
			}
		}
	}
	return array_slice( $out, 0, 500 );
}

/**
 * Parse pasted lines into [term, figure text]: the search, then a comma or tab, then the figure at the end of the line
 * (+1,250%, 140, Breakout). The first separator before a whole figure wins, so a thousands comma stays in the figure
 * and a comma inside the search stays in the search. Other lines are skipped.
 *
 * @param string $text Pasted text.
 * @return array<int, array{0: string, 1: string}>
 */
function aw_si_rising_paste_lines( string $text ): array {
	$out = array();
	foreach ( preg_split( '/\R/u', $text ) as $line ) {
		if ( preg_match( '/^(.*?)(?:\t+|\s*,\s*)([+\-]?\d[\d,.]*\s*%?|breakout)\s*$/iu', trim( $line ), $m ) && '' !== trim( $m[1] ) ) {
			$out[] = array( trim( $m[1] ), trim( $m[2] ) );
		}
	}
	return array_slice( $out, 0, 500 );
}

add_action(
	'admin_post_aw_si_rising_paste',
	function () {
		if ( ! current_user_can( aw_si_cap() ) || ! check_admin_referer( 'aw_si_rising_paste' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'insightweb' ), 403 );
		}
		if ( get_option( 'aw_si_off' ) ) {
			wp_die(
				esc_html__( 'Search Insights is off (its data was deleted), so nothing was stored. Settings → Run the daily update starts it again.', 'insightweb' ),
				'',
				array(
					'back_link' => true,
					'response'  => 409,
				)
			);
		}
		aw_si_install();
		$kind  = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : 'related';
		$today = ( new DateTimeImmutable( 'today', wp_timezone() ) )->format( 'Y-m-d' );
		$kinds = aw_si_rising_paste_kinds();
		if ( isset( $kinds[ $kind ] ) ) { // read on X, Google News, Instagram, Facebook or elsewhere: its own source, no figure
			$day  = isset( $_POST['day'] ) ? sanitize_text_field( wp_unslash( $_POST['day'] ) ) : '';
			$day  = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) && $day <= $today ? $day : $today;
			$done = 0;
			$skip = 0;
			$raw  = isset( $_POST['lines'] ) ? sanitize_textarea_field( wp_unslash( $_POST['lines'] ) ) : '';
			$rows = aw_si_rising_paste_link_lines( $raw );
			$skip = max( 0, count( array_filter( preg_split( '/\R/u', $raw ), fn( $l ) => '' !== trim( $l ) ) ) - count( $rows ) );
			foreach ( $rows as list( $term, $url ) ) {
				/* translators: 1: where it was read, 2: day read, 3: link. */
				$note = sprintf( __( 'Read on %1$s, %2$s: %3$s', 'insightweb' ), $kinds[ $kind ], wp_date( 'j M Y', strtotime( $day . ' 12:00' ) ), $url );
				$ok   = in_array( aw_si_rising_put( $term, 'paste_' . $kind, null, $day, $note ), array( 'created', 'updated' ), true );
				$done += $ok ? 1 : 0;
				$skip += $ok ? 0 : 1;
			}
			if ( $done ) {
				aw_si_run_ok(
					'rising_paste_' . $kind,
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
					aw_si_return_url( 'rising' )
				)
			);
			exit;
		}
		$kind  = 'interest' === $kind ? 'interest' : 'related';
		$lines = aw_si_rising_paste_lines( isset( $_POST['lines'] ) ? sanitize_textarea_field( wp_unslash( $_POST['lines'] ) ) : '' );
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
				$ok = in_array( aw_si_rising_put( $term, 'trends_related', $growth, $today, sprintf( __( 'Trends: rising related search, %s (pasted by the owner)', 'insightweb' ), $fig ) ), array( 'created', 'updated' ), true );
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
				aw_si_return_url( 'rising' )
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

/** Overview box: the ten fastest-rising terms seen in the last 7 days (not dismissed), with what to do. */
function aw_si_box_rising_week(): void {
	$rows = aw_si_rising_top( 10 );
	if ( ! $rows ) {
		echo '<p>' . esc_html__( 'No rising terms in the last 7 days.', 'insightweb' ) . '</p>';
		return;
	}
	$all = aw_si_rising_sources();
	echo '<table class="widefat fixed striped"><thead><tr><th scope="col">' . esc_html__( 'Term', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'Growth', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'Sources', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'Our page', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'Suggested action', 'insightweb' ) . '</th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		$src = array();
		foreach ( array_filter( explode( ',', (string) $r['sources'] ) ) as $s ) {
			$src[] = (string) ( $all[ $s ]['label'] ?? $s );
		}
		/* translators: %s: times the usual level, such as 2.6. */
		$growth = null === $r['growth'] ? '–' : sprintf( __( '×%s', 'insightweb' ), number_format_i18n( (float) $r['growth'], 1 ) );
		$page   = (string) $r['page'];
		echo '<tr><td>' . esc_html( (string) $r['term'] ) . '</td><td>' . esc_html( $growth ) . '</td><td>' . esc_html( implode( ', ', $src ) ) . '</td><td>' . ( '' !== $page && '/' === $page[0] ? '<a href="' . esc_url( home_url( $page ) ) . '">' . esc_html( $page ) . '</a>' : '–' ) . '</td><td>' . esc_html( aw_si_actions()[ (string) $r['action'] ] ?? '–' ) . '</td></tr>';
	}
	echo '</tbody></table>';
	echo '<p><a href="' . esc_url( aw_si_url( 'rising' ) ) . '">' . esc_html__( 'All rising terms', 'insightweb' ) . '</a></p>';
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Search Insights rising terms (old name of `wp aw-si rising`, kept as an alias).
	 *
	 * ## EXAMPLES
	 *
	 *     wp aw-si rising --limit=10
	 */
	WP_CLI::add_command(
		'aw-si-rising',
		function ( $args, $assoc ) {
			WP_CLI\Utils\format_items( 'table', aw_si_rising_top( (int) ( $assoc['limit'] ?? 10 ) ), array( 'term', 'sources', 'growth', 'first_seen', 'page', 'action', 'status' ) );
		}
	);
}

add_action(
	'aw_si_settings_rows',
	function () {
		$on = aw_si_gdelt_on();
		echo '<tr><th scope="row">' . esc_html__( 'News (GDELT)', 'insightweb' ) . '</th><td>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_si_gdelt">';
		wp_nonce_field( 'aw_si_gdelt' );
		echo '<label><input type="checkbox" name="on" value="1"' . checked( $on, true, false ) . '> ' . esc_html__( 'Use GDELT for Rising (Indian news counts, daily)', 'insightweb' ) . '</label> ';
		submit_button( __( 'Save', 'insightweb' ), 'secondary', 'submit', false );
		echo '</form>';
		echo '<p class="description">' . esc_html__( 'Off by default: GDELT refuses requests from the workers\' and the site\'s addresses (HTTP 429). While it is off its results are not stored, and it is not shown as late or failing.', 'insightweb' ) . '</p></td></tr>';
	}
);

add_action(
	'admin_post_aw_si_gdelt',
	function () {
		if ( ! current_user_can( aw_si_cap() ) || ! check_admin_referer( 'aw_si_gdelt' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'insightweb' ), 403 );
		}
		aw_si_gdelt_save( ! empty( $_POST['on'] ) );
		wp_safe_redirect( aw_si_return_url( 'settings' ) );
		exit;
	}
);
