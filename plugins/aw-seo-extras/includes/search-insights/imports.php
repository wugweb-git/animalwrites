<?php
/**
 * Imports and paste forms on the Settings tab (plan §5.4): Keyword Planner volumes, Ahrefs Webmaster Tools keywords and
 * backlinks, Trends averages when the worker can't reach Trends, and data.gov.in / IDSP figures for guides. CSV files
 * go through core upload handling (wp_handle_upload) and are deleted once read; pastes work the same way. Only the
 * owner's files are read; nothing is fetched from those services.
 *
 * Rows in obs, period month, d = first of the import month:
 * - kp: volume (value = lower bound of the range, txt = the range as shown), per keyword;
 * - ahrefs: volume and position per keyword (txt = the ranking URL's path); backlinks per page (value = count, txt =
 *   the top referring domains);
 * - trends (period 12m, d = import day): index per keyword, the same rows the worker writes, so priority uses it;
 * - gov: one figure per state and measure (txt = "state | measure | source | as of"), page_key = md5 of state|measure.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The import kinds and their labels. */
function aw_si_import_kinds(): array {
	return array(
		'kp'       => __( 'Keyword Planner (CSV export: Keyword, Avg. monthly searches)', 'aw-seo-extras' ),
		'ahrefs'   => __( 'Ahrefs Webmaster Tools: organic keywords (Keyword, Volume, Position, URL)', 'aw-seo-extras' ),
		'backlink' => __( 'Ahrefs Webmaster Tools: backlinks (Referring page URL, Target URL)', 'aw-seo-extras' ),
		'trends'   => __( 'Google Trends averages (paste: keyword, value; "animal welfare" = 100)', 'aw-seo-extras' ),
		'gov'      => __( 'data.gov.in / IDSP figures (paste: state, measure, value, as of, source URL)', 'aw-seo-extras' ),
	);
}

/**
 * Rows of a CSV or TSV text: header (lower case) and rows as header => value. Reads UTF-16 exports (Keyword Planner),
 * skips title lines before the header, and finds the delimiter.
 *
 * @param string $raw       File or pasted text.
 * @param string $must_have A word the header row contains (e.g. keyword).
 * @return array{0: string[], 1: array<int, array<string, string>>}
 */
function aw_si_parse_table( string $raw, string $must_have = '' ): array {
	if ( 0 === strpos( $raw, "\xFF\xFE" ) || 0 === strpos( $raw, "\xFE\xFF" ) || 20 < substr_count( substr( $raw, 0, 200 ), "\0" ) ) {
		$from = str_starts_with( $raw, "\xFE\xFF" ) ? 'UTF-16BE' : 'UTF-16LE';
		$raw  = (string) mb_convert_encoding( $raw, 'UTF-8', $from );
	}
	$raw   = preg_replace( '/^\xEF\xBB\xBF/', '', $raw );
	$lines = preg_split( '/\r\n|\r|\n/', trim( (string) $raw ) );
	$head  = null;
	$delim = ',';
	$rows  = array();
	foreach ( (array) $lines as $line ) {
		if ( '' === trim( $line ) ) {
			continue;
		}
		if ( null === $head ) {
			$delim = substr_count( $line, "\t" ) > substr_count( $line, ',' ) ? "\t" : ',';
			$cells = array_map( fn( $c ) => strtolower( trim( (string) $c ) ), str_getcsv( $line, $delim, '"', '\\' ) );
			// The header has two columns or more, one of them naming $must_have (title lines such as Keyword Planner's
			// "Keyword Stats …" have one).
			if ( count( $cells ) >= 2 && ( '' === $must_have || preg_grep( '/' . preg_quote( $must_have, '/' ) . '/', $cells ) ) ) {
				$head = $cells;
			}
			continue;
		}
		$cells = str_getcsv( $line, $delim, '"', '\\' );
		$row   = array();
		foreach ( $head as $i => $h ) {
			$row[ $h ] = trim( (string) ( $cells[ $i ] ?? '' ) );
		}
		$rows[] = $row;
	}
	return array( (array) $head, $rows );
}

/**
 * The first column whose name contains one of the words.
 *
 * @param string[] $head  Header.
 * @param string[] $words Words to look for, in order.
 */
function aw_si_import_col( array $head, array $words ): string {
	foreach ( $words as $w ) {
		foreach ( $head as $h ) {
			if ( false !== strpos( $h, $w ) ) {
				return $h;
			}
		}
	}
	return '';
}

/**
 * A search volume as a number: "1,300" → 1300, "1K – 10K" → 1000 (the lower bound), "2.4K" → 2400.
 *
 * @param string $v Cell text.
 */
function aw_si_import_number( string $v ): ?float {
	if ( ! preg_match( '/([0-9][0-9,.]*)\s*([kKmM]?)/', $v, $m ) ) {
		return null;
	}
	$n = (float) str_replace( ',', '', $m[1] );
	return $n * ( 'k' === strtolower( $m[2] ) ? 1000 : ( 'm' === strtolower( $m[2] ) ? 1000000 : 1 ) );
}

/**
 * Store one import.
 *
 * @param string $kind kp, ahrefs, backlink, trends or gov.
 * @param string $raw  File or pasted text.
 * @return array{rows: int, skipped: int, error: string}
 */
function aw_si_import( string $kind, string $raw ): array {
	$month = wp_date( 'Y-m-01' );
	$out   = array(
		'rows'    => 0,
		'skipped' => 0,
		'error'   => '',
	);
	$put   = function ( bool $ok ) use ( &$out ) {
		$ok ? ++$out['rows'] : ++$out['skipped'];
	};
	if ( 'trends' === $kind || 'gov' === $kind ) {
		$lines = array_filter( array_map( 'trim', (array) preg_split( '/\r\n|\r|\n/', $raw ) ) );
		foreach ( $lines as $line ) {
			$cells = array_map( 'trim', str_getcsv( $line, substr_count( $line, "\t" ) ? "\t" : ',', '"', '\\' ) );
			if ( 'trends' === $kind ) {
				$v = isset( $cells[1] ) && is_numeric( $cells[1] ) ? (float) $cells[1] : null;
				if ( '' === ( $cells[0] ?? '' ) || null === $v ) {
					++$out['skipped'];
					continue;
				}
				$id = (int) current( aw_si_kw_ids( array( sanitize_text_field( $cells[0] ) ), 0 ) );
				$put(
					$id && aw_si_obs_put(
						$id,
						'',
						'trends',
						'index',
						'12m',
						wp_date( 'Y-m-d' ),
						array(
							'value' => $v,
							'txt'   => 'import', // Marks pasted rows apart from the workers job's.
						)
					)
				);
				continue;
			}
			$v = isset( $cells[2] ) ? aw_si_import_number( $cells[2] ) : null;
			if ( count( $cells ) < 3 || null === $v || '' === $cells[0] || '' === $cells[1] ) {
				++$out['skipped'];
				continue;
			}
			$key = sanitize_text_field( $cells[0] ) . '|' . sanitize_text_field( $cells[1] );
			$txt = implode( ' | ', array( sanitize_text_field( $cells[0] ), sanitize_text_field( $cells[1] ), esc_url_raw( $cells[4] ?? '' ), sanitize_text_field( $cells[3] ?? '' ) ) );
			$put(
				aw_si_obs_put(
					0,
					$key,
					'gov',
					'figure',
					'month',
					$month,
					array(
						'value' => $v,
						'txt'   => $txt,
					)
				)
			);
		}
		return $out;
	}
	list( $head, $rows ) = aw_si_parse_table( $raw, 'backlink' === $kind ? 'referring' : 'keyword' );
	if ( ! $head ) {
		$out['error'] = __( 'No header row was found. Check that the file is the right export.', 'aw-seo-extras' );
		return $out;
	}
	if ( 'backlink' === $kind ) {
		$from = aw_si_import_col( $head, array( 'referring page url', 'referring page', 'source url' ) );
		$to   = aw_si_import_col( $head, array( 'target url', 'target' ) );
		if ( '' === $from || '' === $to ) {
			$out['error'] = __( 'The backlinks export needs "Referring page URL" and "Target URL" columns.', 'aw-seo-extras' );
			return $out;
		}
		$pages = array();
		foreach ( $rows as $r ) {
			$path = aw_si_path( (string) $r[ $to ] );
			$host = preg_replace( '/^www\./', '', (string) wp_parse_url( (string) $r[ $from ], PHP_URL_HOST ) );
			if ( '' === $path || '' === $host ) {
				++$out['skipped'];
				continue;
			}
			$pages[ $path ][ $host ] = ( $pages[ $path ][ $host ] ?? 0 ) + 1;
		}
		foreach ( $pages as $path => $hosts ) {
			arsort( $hosts );
			$put(
				aw_si_obs_put(
					0,
					$path,
					'ahrefs',
					'backlinks',
					'month',
					$month,
					array(
						'value' => (float) array_sum( $hosts ),
						'txt'   => implode( ', ', array_slice( array_keys( $hosts ), 0, 3 ) ),
					)
				)
			);
		}
		return $out;
	}
	$kcol = aw_si_import_col( $head, array( 'keyword' ) );
	$vcol = aw_si_import_col( $head, 'kp' === $kind ? array( 'avg. monthly searches', 'monthly searches', 'volume' ) : array( 'volume' ) );
	$pcol = 'ahrefs' === $kind ? aw_si_import_col( $head, array( 'current position', 'position' ) ) : '';
	$ucol = 'ahrefs' === $kind ? aw_si_import_col( $head, array( 'current url', 'url' ) ) : '';
	if ( '' === $kcol || '' === $vcol ) {
		$out['error'] = __( 'The file needs a keyword column and a volume column.', 'aw-seo-extras' );
		return $out;
	}
	$labels = array_filter( array_map( fn( $r ) => sanitize_text_field( (string) $r[ $kcol ] ), $rows ) );
	$ids    = aw_si_kw_ids( $labels, 0 );
	foreach ( $rows as $r ) {
		$id  = (int) ( $ids[ aw_si_norm( sanitize_text_field( (string) $r[ $kcol ] ) ) ] ?? 0 );
		$vol = aw_si_import_number( (string) $r[ $vcol ] );
		if ( ! $id || null === $vol ) {
			++$out['skipped'];
			continue;
		}
		$put(
			aw_si_obs_put(
				$id,
				'',
				$kind,
				'volume',
				'month',
				$month,
				array(
					'value' => $vol,
					'txt'   => mb_substr( (string) $r[ $vcol ], 0, 40 ),
				)
			)
		);
		if ( '' !== $pcol && is_numeric( $r[ $pcol ] ?? '' ) ) {
			aw_si_obs_put(
				$id,
				'',
				'ahrefs',
				'position',
				'month',
				$month,
				array(
					'value' => (float) $r[ $pcol ],
					'txt'   => '' !== $ucol ? aw_si_path( (string) $r[ $ucol ] ) : '',
				)
			);
		}
	}
	return $out;
}

/** Settings tab rows: imports and paste forms (added to the form-table through aw_si_settings_rows). */
function aw_si_import_rows(): void {
	echo '<tr><th scope="row"><label for="aw-si-import-kind">' . esc_html__( 'Imports', 'aw-seo-extras' ) . '</label></th><td>';
	echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_si_import">';
	wp_nonce_field( 'aw_si_import' );
	echo '<p><select id="aw-si-import-kind" name="kind">';
	foreach ( aw_si_import_kinds() as $k => $label ) {
		echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $label ) . '</option>';
	}
	echo '</select></p>';
	echo '<p><label for="aw-si-import-file">' . esc_html__( 'CSV file', 'aw-seo-extras' ) . '</label> <input type="file" id="aw-si-import-file" name="file" accept=".csv,.tsv,.txt,text/csv,text/plain"></p>';
	echo '<p><label for="aw-si-import-paste">' . esc_html__( 'Or paste', 'aw-seo-extras' ) . '</label><br><textarea id="aw-si-import-paste" name="paste" rows="5" class="large-text"></textarea></p>';
	submit_button( __( 'Import', 'aw-seo-extras' ), 'secondary', 'submit', false );
	echo '<p class="description">' . esc_html__( 'Files are read once and deleted. Volumes and positions are stored for this month; a second import in the same month replaces the first.', 'aw-seo-extras' ) . '</p>';
	echo '</form>';
	global $wpdb;
	if ( aw_si_ready() ) {
		$o    = aw_si_table( 'obs' );
		$last = (array) $wpdb->get_results( "SELECT src, MAX(fetched) f, COUNT(*) n FROM {$o} WHERE src IN ('kp', 'ahrefs', 'gov') OR (src = 'trends' AND txt = 'import') GROUP BY src" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
		if ( $last ) {
			echo '<ul>';
			foreach ( $last as $l ) {
				/* translators: 1: source, 2: rows, 3: date and time. */
				echo '<li>' . esc_html( sprintf( __( '%1$s: %2$s rows, last import %3$s', 'aw-seo-extras' ), $l->src, number_format_i18n( (int) $l->n ), mysql2date( 'j M Y, H:i', $l->f ) ) ) . '</li>';
			}
			echo '</ul>';
		}
	}
	echo '</td></tr>';
}
add_action( 'aw_si_settings_rows', 'aw_si_import_rows' );

add_action(
	'admin_post_aw_si_import',
	function () {
		if ( ! current_user_can( aw_si_cap() ) || ! check_admin_referer( 'aw_si_import' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'aw-seo-extras' ), 403 );
		}
		$kind = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : '';
		if ( ! isset( aw_si_import_kinds()[ $kind ] ) ) {
			wp_die( esc_html__( 'Unknown import.', 'aw-seo-extras' ), 400 );
		}
		$raw = isset( $_POST['paste'] ) ? (string) wp_unslash( $_POST['paste'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parsed as CSV; each value is sanitised where it is stored.
		if ( ! empty( $_FILES['file']['name'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			// The extension is checked here; WordPress's content sniffing is skipped because Keyword Planner's UTF-16
			// exports are often detected as binary and refused. The file is only read as text and deleted at once.
			$name = sanitize_file_name( wp_unslash( $_FILES['file']['name'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised here.
			if ( ! in_array( strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ), array( 'csv', 'tsv', 'txt' ), true ) ) {
				wp_die( esc_html__( 'Upload a .csv, .tsv or .txt file.', 'aw-seo-extras' ), '', array( 'back_link' => true ) );
			}
			$up = wp_handle_upload(
				$_FILES['file'], // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- core upload handling checks it.
				array(
					'test_form' => false,
					'test_type' => false,
				)
			);
			if ( ! empty( $up['error'] ) ) {
				wp_die( esc_html( $up['error'] ), '', array( 'back_link' => true ) );
			}
			$raw = (string) file_get_contents( $up['file'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the uploaded file.
			wp_delete_file( $up['file'] );
		}
		$res = '' === trim( $raw ) ? array(
			'rows'    => 0,
			'skipped' => 0,
			'error'   => __( 'Nothing to import: choose a file or paste the rows.', 'aw-seo-extras' ),
		) : aw_si_import( $kind, $raw );
		set_transient( 'aw_si_import_result_' . get_current_user_id(), $res + array( 'kind' => $kind ), 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'imported', 1, aw_si_url( 'settings' ) ) );
		exit;
	}
);

add_action(
	'aw_si_settings_notices',
	function () {
		if ( ! isset( $_GET['imported'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- message only.
			return;
		}
		$r = get_transient( 'aw_si_import_result_' . get_current_user_id() );
		if ( ! is_array( $r ) ) {
			return;
		}
		if ( '' !== $r['error'] ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( $r['error'] ) . '</p></div>';
			return;
		}
		/* translators: 1: import kind, 2: rows stored, 3: rows skipped. */
		echo '<div class="notice notice-success inline"><p>' . esc_html( sprintf( __( '%1$s: %2$s rows stored, %3$s skipped.', 'aw-seo-extras' ), aw_si_import_kinds()[ $r['kind'] ] ?? $r['kind'], number_format_i18n( (int) $r['rows'] ), number_format_i18n( (int) $r['skipped'] ) ) ) . '</p></div>';
	}
);

add_filter(
	'aw_si_sources',
	function ( $s ) {
		$s['live'] = array(
			'label' => __( 'Live Google check (bookmarklet)', 'aw-seo-extras' ),
			'every' => MONTH_IN_SECONDS,
		);
		return $s;
	}
);
