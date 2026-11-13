<?php
/**
 * Keyword-tool imports (3 Nov 2026): Ubersuggest, vidIQ and OpenSEO figures pasted (or uploaded as CSV) on
 * Search Insights → Settings → Imports, by hand or by a scheduled task that reads the tools.
 *
 * - Each tool has its own rows (src ubersuggest, vidiq or openseo) and its own column on the Keywords tab; nothing here
 *   writes or replaces Search Console, Keyword Planner or Bing figures.
 * - A term that is one of our keywords stores the tool's figure on that keyword (period month, d = first of the month;
 *   OpenSEO's 12-month trend one row per month). Importing the same month again replaces it.
 * - A term that is not a keyword yet goes to Rising (source = the tool), never into the keyword table, with the figure
 *   as its note. English only, as for the other Rising sources.
 * - Seeds for the tools: a list on Settings (option aw_si_tool_seeds), also given to the workers' GET as tool_seeds.
 * - Each import is a run in the run log (Rising source rising_<tool>), listed once the tool has sent something.
 * - No email, nothing fetched from the tools here, no keys stored.
 *
 * @package insightweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SI_TOOL_SEEDS = 'aw_si_tool_seeds';

/**
 * The tools: label, metric stored, what the figure is, how often the routine sends it.
 *
 * @return array<string, array{label: string, metric: string, what: string, every: int}>
 */
function aw_si_tools(): array {
	return array(
		'ubersuggest' => array(
			'label'  => __( 'Ubersuggest', 'insightweb' ),
			'metric' => 'volume',
			'what'   => __( 'Google searches a month, India', 'insightweb' ),
			'every'  => DAY_IN_SECONDS,
		),
		'vidiq'       => array(
			'label'  => __( 'vidIQ', 'insightweb' ),
			'metric' => 'yt-volume',
			'what'   => __( 'YouTube searches a month', 'insightweb' ),
			'every'  => WEEK_IN_SECONDS,
		),
		'openseo'     => array(
			'label'  => __( 'OpenSEO', 'insightweb' ),
			'metric' => 'volume',
			'what'   => __( 'Google searches a month (12-month trend)', 'insightweb' ),
			'every'  => 31 * DAY_IN_SECONDS,
		),
	);
}

add_filter(
	'aw_si_import_kinds',
	function ( $kinds ) {
		$kinds['ubersuggest'] = __( 'Ubersuggest (paste or CSV: keyword, volume; optional: SD, CPC)', 'insightweb' );
		$kinds['vidiq']       = __( 'vidIQ (paste or CSV: keyword, volume; optional: competition, score)', 'insightweb' );
		$kinds['openseo']     = __( 'OpenSEO (paste or CSV: keyword, month, volume; one row per month for the trend)', 'insightweb' );
		return $kinds;
	}
);

add_filter(
	'aw_si_import_run',
	function ( $out, string $kind, string $raw ) {
		return isset( aw_si_tools()[ $kind ] ) ? aw_si_tool_import( $kind, $raw ) : $out;
	},
	10,
	3
);

/**
 * Growth of a 12-month series: the last 3 months against the 9 before (times the usual), or null when too short.
 *
 * @param array<string, float> $by_month Y-m-01 => volume.
 */
function aw_si_tool_growth( array $by_month ): ?float {
	ksort( $by_month );
	$v = array_values( $by_month );
	if ( count( $v ) < 6 ) {
		return null;
	}
	$recent = array_slice( $v, -3 );
	$before = array_slice( $v, 0, -3 );
	$base   = array_sum( $before ) / count( $before );
	return $base > 0 ? round( ( array_sum( $recent ) / 3 ) / $base, 2 ) : null;
}

/**
 * Store one tool import.
 *
 * @param string $tool ubersuggest, vidiq or openseo.
 * @param string $raw  File or pasted text (a header row, then one row per keyword or keyword and month).
 * @return array{rows: int, skipped: int, error: string, rising: int}
 */
function aw_si_tool_import( string $tool, string $raw ): array {
	$t   = aw_si_tools()[ $tool ];
	$out = array(
		'rows'    => 0,
		'skipped' => 0,
		'rising'  => 0,
		'error'   => '',
	);
	list( $head, $rows ) = aw_si_parse_table( $raw, 'keyword' );
	$kcol                = $head ? aw_si_import_col( $head, array( 'keyword', 'query', 'term' ) ) : '';
	$vcol                = $head ? aw_si_import_col( $head, array( 'search volume', 'monthly searches', 'volume', 'searches' ) ) : '';
	if ( '' === $kcol || '' === $vcol ) {
		$out['error'] = __( 'The rows need a header with a keyword column and a volume column.', 'insightweb' );
		return $out;
	}
	$mcol  = (string) current( array_intersect( $head, array( 'month', 'date' ) ) ); // exact: "monthly searches" is the volume
	$extra = array_filter(
		array(
			'SD'          => aw_si_import_col( $head, array( 'seo difficulty' ) ) ? aw_si_import_col( $head, array( 'seo difficulty' ) ) : (string) current( array_intersect( $head, array( 'sd' ) ) ), // "sd" exactly: "cpc (usd)" contains it
			'CPC'         => aw_si_import_col( $head, array( 'cpc' ) ),
			'competition' => aw_si_import_col( $head, array( 'competition' ) ),
			'score'       => aw_si_import_col( $head, array( 'overall', 'score' ) ),
		)
	);
	aw_si_run_start( 'rising_' . $tool );
	$month = wp_date( 'Y-m-01' );
	$map   = aw_si_kw_map(); // Existing keywords only: a tool's idea never becomes a keyword record by itself.
	$terms = array(); // norm => [term, months => volume, txt].
	foreach ( $rows as $r ) {
		$term = sanitize_text_field( (string) ( $r[ $kcol ] ?? '' ) );
		$vol  = aw_si_import_number( (string) ( $r[ $vcol ] ?? '' ) );
		$norm = aw_si_norm( $term );
		if ( '' === $norm || null === $vol ) {
			++$out['skipped'];
			continue;
		}
		$d = $month;
		if ( '' !== $mcol && '' !== trim( (string) $r[ $mcol ] ) ) {
			$ts = strtotime( trim( (string) $r[ $mcol ] ) . ( preg_match( '/^\d{4}-\d{2}$/', trim( (string) $r[ $mcol ] ) ) ? '-01' : '' ) );
			if ( ! $ts ) {
				++$out['skipped'];
				continue;
			}
			$d = gmdate( 'Y-m-01', $ts );
		}
		$txt = array();
		foreach ( $extra as $label => $col ) {
			if ( '' !== trim( (string) ( $r[ $col ] ?? '' ) ) ) {
				$txt[] = $label . ' ' . sanitize_text_field( (string) $r[ $col ] );
			}
		}
		$terms[ $norm ]['term']         = $term;
		$terms[ $norm ]['months'][ $d ] = $vol;
		$terms[ $norm ]['txt']          = implode( ' · ', $txt );
	}
	$today = wp_date( 'Y-m-d' );
	foreach ( $terms as $norm => $x ) {
		ksort( $x['months'] );
		$latest = (float) end( $x['months'] );
		$id     = (int) ( $map[ $norm ] ?? 0 );
		if ( $id ) {
			foreach ( $x['months'] as $d => $vol ) {
				aw_si_obs_put(
					$id,
					'',
					$tool,
					$t['metric'],
					'month',
					$d,
					array(
						'value' => $vol,
						'txt'   => mb_substr( (string) $x['txt'], 0, 255 ),
					)
				) ? ++$out['rows'] : ++$out['skipped'];
			}
			continue;
		}
		/* translators: 1: tool, 2: searches a month, 3: what the figure is, 4: extra figures. */
		$note = sprintf( __( '%1$s: %2$s (%3$s)%4$s', 'insightweb' ), $t['label'], number_format_i18n( $latest ), $t['what'], '' !== $x['txt'] ? ', ' . $x['txt'] : '' );
		$res  = aw_si_rising_put( (string) $x['term'], $tool, aw_si_tool_growth( $x['months'] ), $today, $note );
		in_array( $res, array( 'created', 'updated' ), true ) ? ++$out['rising'] : ++$out['skipped'];
	}
	$out['rows'] += $out['rising'];
	aw_si_run_ok(
		'rising_' . $tool,
		array(
			'rows'    => $out['rows'],
			'kept'    => $out['rising'],
			'pasted'  => true,
			'skipped' => $out['skipped'],
		)
	);
	return $out;
}

/**
 * A keyword's latest figure from each tool, for the Keywords tab.
 *
 * @param int[] $ids Keyword IDs.
 * @return array<int, array<string, object>> id => tool => row (value, txt, d).
 */
function aw_si_tool_figures( array $ids ): array {
	global $wpdb;
	$ids = array_filter( array_map( 'intval', $ids ) );
	if ( ! $ids || ! aw_si_ready() ) {
		return array();
	}
	$o    = aw_si_table( 'obs' );
	$in   = implode( ',', $ids );
	$tools = array_keys( aw_si_tools() );
	$srcs  = implode( ', ', array_fill( 0, count( $tools ), '%s' ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integers and placeholders built above.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT kw_id, src, value, txt, d FROM %i WHERE src IN ({$srcs}) AND period = 'month' AND kw_id IN ({$in}) ORDER BY d", array_merge( array( $o ), $tools ) ) );
	$out  = array();
	foreach ( $rows as $r ) {
		$out[ (int) $r->kw_id ][ (string) $r->src ] = $r; // Ordered by month: the latest wins.
	}
	return $out;
}

/** The seeds the routine uses with the tools, one per line. */
function aw_si_tool_seeds(): array {
	$raw = (string) get_option( AW_SI_TOOL_SEEDS, '' );
	return array_values( array_unique( array_filter( array_map( 'trim', (array) preg_split( '/\r\n|\r|\n/', $raw ) ), 'strlen' ) ) );
}

/**
 * The seeds as stored: one search per line, trimmed, no blanks or repeats, at most 200 of at most 100 characters.
 *
 * @param string $raw Text as typed (already passed through sanitize_textarea_field()).
 */
function aw_si_tool_seeds_clean( string $raw ): string {
	$lines = array_slice( array_values( array_unique( array_filter( array_map( 'trim', (array) preg_split( '/\r\n|\r|\n/', $raw ) ), 'strlen' ) ) ), 0, 200 );
	return implode( "\n", array_map( fn( $l ) => mb_substr( $l, 0, 100 ), $lines ) );
}

/**
 * Save the seeds (Settings → Seeds for keyword tools, and the Settings screen's route). Nothing is written when the
 * cleaned text is what is stored.
 *
 * @param string $raw Text as typed (already passed through sanitize_textarea_field()).
 * @return bool Whether the option was written.
 */
function aw_si_tool_seeds_save( string $raw ): bool {
	$clean = aw_si_tool_seeds_clean( $raw );
	if ( (string) get_option( AW_SI_TOOL_SEEDS, '' ) === $clean ) {
		return false;
	}
	update_option( AW_SI_TOOL_SEEDS, $clean, false );
	return true;
}

add_filter(
	'aw_si_rising_lists',
	function ( $lists ) {
		$lists['tool_seeds'] = aw_si_tool_seeds();
		return $lists;
	}
);

add_action(
	'aw_si_settings_rows',
	function () {
		echo '<tr><th scope="row"><label for="aw-si-tool-seeds">' . esc_html__( 'Seeds for keyword tools', 'insightweb' ) . '</label></th><td>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_si_tool_seeds">';
		wp_nonce_field( 'aw_si_tool_seeds' );
		echo '<textarea id="aw-si-tool-seeds" name="seeds" rows="6" class="large-text">' . esc_textarea( implode( "\n", aw_si_tool_seeds() ) ) . '</textarea>';
		submit_button( __( 'Save seeds', 'insightweb' ), 'secondary', 'submit', false );
		echo '<p class="description">' . esc_html__( 'One search per line. The Ubersuggest, vidIQ and OpenSEO reports start from these (in turn); their results come back through Imports above: figures for our keywords go to the Keywords tab, new terms to Rising.', 'insightweb' ) . '</p>';
		echo '</form></td></tr>';
	},
	20
);

add_action(
	'admin_post_aw_si_tool_seeds',
	function () {
		if ( ! current_user_can( aw_si_cap() ) || ! check_admin_referer( 'aw_si_tool_seeds' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'insightweb' ), 403 );
		}
		$changed = aw_si_tool_seeds_save( isset( $_POST['seeds'] ) ? sanitize_textarea_field( wp_unslash( $_POST['seeds'] ) ) : '' );
		wp_safe_redirect( add_query_arg( 'seeds', $changed ? 'saved' : 'same', aw_si_return_url( 'settings' ) ) );
		exit;
	}
);

// The result of Save seeds, on Settings (12 Nov 2026: the save had no notice).
add_action(
	'aw_si_settings_notices',
	function () {
		$r = isset( $_GET['seeds'] ) ? sanitize_key( wp_unslash( $_GET['seeds'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- message only.
		if ( 'saved' === $r ) {
			/* translators: %s: number of seeds. */
			wp_admin_notice( esc_html( sprintf( _n( 'Seeds saved: %s search.', 'Seeds saved: %s searches.', count( aw_si_tool_seeds() ), 'insightweb' ), number_format_i18n( count( aw_si_tool_seeds() ) ) ) ), array( 'type' => 'success', 'additional_classes' => array( 'inline' ) ) );
		} elseif ( 'same' === $r ) {
			wp_admin_notice( esc_html__( 'Seeds unchanged: nothing to save.', 'insightweb' ), array( 'type' => 'success', 'additional_classes' => array( 'inline' ) ) );
		}
	}
);
