<?php
/**
 * Links tab import (12 Nov 2026): Search Console has no links API, so its Links report is read by export. The owner
 * pastes or uploads one of its CSV exports, or a list of their own, on the Links tab:
 *
 * - Top linking sites (Site, Linking pages, Target pages): one row per site; the export names no linked page, so the
 *   home page is used and the note says so.
 * - Top linked pages (Target page, Incoming links, Linking sites): no linking site, so not link rows; kept as a
 *   summary (option aw_si_links_gsc_pages) shown above the list.
 * - Latest links or More sample links (Linking page, Last crawled): one row per linking page, last seen = last crawled.
 * - A list: linking page or site, and optional linked page, anchor, links, DR.
 *
 * Any of them may carry a DR column: Ahrefs' Domain Rating typed by hand from its free checker (no Ahrefs API or
 * export is used), kept per domain with the day read (option aw_si_link_dr) and shown in the list's DR column.
 * Rows go through aw_si_link_put(), so a link read again only moves its dates. Loaded by links.php.
 *
 * @package insightweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SI_LINK_DR_OPTION    = 'aw_si_link_dr';         // domain => [DR 0–100, Y-m-d read]
const AW_SI_LINK_PAGES_OPTION = 'aw_si_links_gsc_pages'; // [ day, rows: [ [url, links, sites], … ] ]
const AW_SI_LINK_READS_OPTION = 'aw_si_links_imports';   // source => [ time of the import, rows, day read ]
const AW_SI_LINK_MAX_ROWS     = 2000;                    // per import
const AW_SI_LINK_MAX_BYTES    = 2097152;                 // 2 MB per file or paste

/** The import's sources: form value => the source name stored on each row (with ", read <date>"). */
function aw_si_links_import_sources(): array {
	return array(
		'gsc'   => __( 'Search Console Links', 'insightweb' ),
		'other' => __( 'Pasted list', 'insightweb' ),
	);
}

/**
 * A source without its read date, so reads of one source group together ("Search Console Links, read 2 Nov 2026" and
 * "Search Console Links (read 1 Dec 2026)" are one source). The same rule as the link key in aw_si_link_put().
 *
 * @param string $source Source as stored.
 */
function aw_si_link_source_key( string $source ): string {
	return trim( (string) preg_replace( '/[\s,]*\(?read\b[^)]*\)?\s*$/i', '', $source ) );
}

/**
 * Domain of a URL without www, or the text itself when it is a bare domain.
 *
 * @param string $url URL or domain.
 */
function aw_si_link_domain( string $url ): string {
	$host = wp_parse_url( false === strpos( $url, '//' ) ? 'https://' . $url : $url, PHP_URL_HOST );
	return strtolower( preg_replace( '/^www\./i', '', (string) $host ) );
}

/**
 * A host without www, lower case, or '' when the text is not a plain domain name.
 *
 * @param string $v URL or domain.
 */
function aw_si_links_import_host( string $v ): string {
	$host = aw_si_link_domain( trim( $v ) );
	// Labels of letters, digits and hyphens; the last one letters, or an IDN in punycode (xn--…).
	return preg_match( '/^(?=.{3,191}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+(?:[a-z]{2,63}|xn--[a-z0-9-]{1,59})$/', $host ) ? $host : '';
}

/**
 * A date cell as Y-m-d (2026-11-02, 2 Nov 2026, Nov 2, 2026), or '' when it is not one, in the future or before 2000.
 *
 * @param string $v     Cell.
 * @param string $today Today (Y-m-d).
 */
function aw_si_links_import_date( string $v, string $today ): string {
	$v = trim( $v );
	if ( '' === $v ) {
		return '';
	}
	$t = strtotime( $v . ( preg_match( '/\d{1,2}:\d{2}/', $v ) ? '' : ' 12:00:00' ) );
	if ( false === $t ) {
		return '';
	}
	$d = gmdate( 'Y-m-d', $t );
	return ( $d <= $today && $d >= '2000-01-01' ) ? $d : '';
}

/**
 * Parse an import into link rows (aw_si_link_put() fields), DR per domain and the linked-pages summary.
 *
 * @param string $raw    CSV or TSV text (aw_si_parse_table()).
 * @param string $home   The site's home address (rows with no linked page use it; a linked page must be on it).
 * @param string $source Source name with its read date.
 * @param string $day    Day read (Y-m-d), the first and last seen of rows without a date of their own.
 * @param string $today  Today (Y-m-d).
 * @return array{kind: string, rows: array, dr: array<string, int>, pages: array, skipped: int, error: string}
 */
function aw_si_links_import_parse( string $raw, string $home, string $source, string $day, string $today ): array {
	$out = array(
		'kind'    => '',
		'rows'    => array(),
		'dr'      => array(),
		'pages'   => array(),
		'skipped' => 0,
		'error'   => '',
	);
	list( $head, $rows ) = aw_si_parse_table( $raw );
	if ( ! $head ) {
		// A one-column list (the shared table reader wants two columns): Site, Linking page, Domain or URL, one per line.
		$lines = array_values( array_filter( array_map( 'trim', (array) preg_split( '/\r\n|\r|\n/', (string) preg_replace( '/^\xEF\xBB\xBF/', '', $raw ) ) ), 'strlen' ) );
		$one   = strtolower( trim( (string) ( $lines[0] ?? '' ), " \t\"," ) );
		if ( in_array( $one, array( 'site', 'linking site', 'domain', 'referring domain', 'linking page', 'url' ), true ) ) {
			$head = array( $one );
			$rows = array_map( fn( $v ) => array( $one => trim( $v, " \t\"," ) ), array_slice( $lines, 1 ) );
		}
	}
	if ( ! $head ) {
		$out['error'] = __( 'No header row was found: the first line must name the columns (as Search Console\'s export does).', 'insightweb' );
		return $out;
	}
	// Column names exactly as written (Search Console's "Linking pages" is a count, not a "Linking page" address).
	$col     = function ( array $names ) use ( $head ): string {
		foreach ( $names as $name ) {
			if ( in_array( $name, $head, true ) ) {
				return $name;
			}
		}
		return '';
	};
	$site    = $col( array( 'site', 'linking site', 'domain', 'referring domain' ) );
	$from    = $col( array( 'linking page', 'source url', 'from url', 'referring page', 'from', 'url' ) );
	$to      = $col( array( 'target page', 'linked page', 'target url', 'to url', 'to', 'page' ) );
	$in      = $col( array( 'incoming links' ) );
	$n       = $col( array( 'linking pages', 'links' ) );
	$targets = $col( array( 'target pages' ) );
	$sites   = $col( array( 'linking sites' ) );
	$when    = $col( array( 'last crawled', 'last seen', 'date' ) );
	$first   = $col( array( 'first seen' ) );
	$anch    = $col( array( 'anchor', 'anchor text' ) );
	$dr      = $col( array( 'dr', 'domain rating' ) );
	$hhost   = aw_si_links_import_host( $home );
	if ( '' !== $to && '' !== $in && '' === $site && '' === $from ) {
		$out['kind'] = 'pages';
	} elseif ( '' !== $site && '' === $from && '' !== $targets ) {
		$out['kind'] = 'sites';
	} elseif ( '' !== $from || '' !== $site ) {
		$out['kind'] = '' !== $from && '' !== $when && '' === $to && '' === $anch ? 'latest' : 'list';
	} else {
		$out['error'] = __( 'The columns were not recognised: Search Console\'s Top linking sites, Top linked pages or Latest links export, or a list with a Linking page or Site column.', 'insightweb' );
		return $out;
	}
	$num = function ( string $v ): int {
		$v = str_replace( array( ',', ' ' ), '', $v );
		return ctype_digit( $v ) ? (int) $v : -1;
	};
	foreach ( array_slice( $rows, 0, AW_SI_LINK_MAX_ROWS ) as $r ) {
		if ( 'pages' === $out['kind'] ) {
			$url = esc_url_raw( (string) $r[ $to ], array( 'http', 'https' ) );
			if ( '' === $url || aw_si_links_import_host( $url ) !== $hhost ) {
				++$out['skipped'];
				continue;
			}
			$out['pages'][] = array( $url, max( 0, $num( (string) $r[ $in ] ) ), '' !== $sites ? max( 0, $num( (string) $r[ $sites ] ) ) : 0 );
			continue;
		}
		$page   = '' !== $from ? esc_url_raw( (string) $r[ $from ], array( 'http', 'https' ) ) : '';
		$domain = aw_si_links_import_host( '' !== $page ? $page : ( '' !== $site ? (string) $r[ $site ] : '' ) );
		if ( '' === $domain || $domain === $hhost ) {
			++$out['skipped']; // not a site name or link address, or our own site
			continue;
		}
		$target = '' !== $to && '' !== trim( (string) $r[ $to ] ) ? esc_url_raw( (string) $r[ $to ], array( 'http', 'https' ) ) : '';
		if ( '' !== $to && '' !== trim( (string) $r[ $to ] ) && ( '' === $target || aw_si_links_import_host( $target ) !== $hhost ) ) {
			++$out['skipped']; // the linked page is not one of ours
			continue;
		}
		$seen  = '' !== $when ? aw_si_links_import_date( (string) $r[ $when ], $today ) : '';
		$since = '' !== $first ? aw_si_links_import_date( (string) $r[ $first ], $today ) : '';
		$links = '' !== $n ? $num( (string) $r[ $n ] ) : 1;
		$note  = '' === $target ? __( 'Linked page not named in the export; home page used.', 'insightweb' ) : '';
		if ( '' !== $targets && $num( (string) $r[ $targets ] ) > 0 ) {
			/* translators: %s: number of our pages it links to. */
			$note = sprintf( __( 'Links to %s of our pages (Search Console); the export names none, so the home page is used.', 'insightweb' ), number_format_i18n( $num( (string) $r[ $targets ] ) ) );
		}
		$out['rows'][] = array(
			'from_url'   => $page,
			'domain'     => $domain,
			'linked_url' => '' !== $target ? $target : $home,
			'anchor'     => '' !== $anch ? mb_substr( sanitize_text_field( (string) $r[ $anch ] ), 0, 255 ) : '',
			'links'      => max( 1, $links ),
			'first_seen' => '' !== $since ? $since : ( '' !== $seen ? $seen : $day ),
			'last_seen'  => '' !== $seen ? $seen : $day,
			'source'     => $source,
			'note'       => $note,
		);
		if ( '' !== $dr && '' !== trim( (string) $r[ $dr ] ) ) {
			$v = $num( (string) $r[ $dr ] );
			if ( $v >= 0 && $v <= 100 ) {
				$out['dr'][ $domain ] = $v;
			}
		}
	}
	$out['skipped'] += max( 0, count( $rows ) - AW_SI_LINK_MAX_ROWS );
	return $out;
}

/**
 * Domain Rating typed in an import, per domain: [DR, day read], or null.
 *
 * @param string $domain Domain without www.
 */
function aw_si_link_dr( string $domain ): ?array {
	static $all = null;
	if ( null === $all ) {
		$all = (array) get_option( AW_SI_LINK_DR_OPTION, array() );
	}
	return isset( $all[ $domain ] ) && is_array( $all[ $domain ] ) ? $all[ $domain ] : null;
}

/**
 * Store an import: link rows, DR per domain, the linked-pages summary and the read in the log of reads.
 *
 * @param array  $parsed aw_si_links_import_parse().
 * @param string $source Source name without the read date.
 * @param string $day    Day read (Y-m-d).
 * @return array{added: int, updated: int, failed: int, skipped: int, dr: int, pages: int, kind: string, error: string}
 */
function aw_si_links_import_store( array $parsed, string $source, string $day ): array {
	$res = array(
		'added'   => 0,
		'updated' => 0,
		'failed'  => 0,
		'skipped' => (int) $parsed['skipped'],
		'dr'      => count( $parsed['dr'] ),
		'pages'   => count( $parsed['pages'] ),
		'kind'    => (string) $parsed['kind'],
		'error'   => (string) $parsed['error'],
	);
	if ( '' !== $res['error'] ) {
		return $res;
	}
	foreach ( $parsed['rows'] as $row ) {
		$row['type'] = aw_si_link_auto_type( (string) $row['anchor'] );
		$n           = aw_si_link_put( $row );
		if ( 1 === $n ) {
			++$res['added'];
		} elseif ( $n >= 0 ) {
			++$res['updated'];
		} else {
			++$res['failed'];
		}
	}
	if ( $parsed['dr'] ) {
		$all = (array) get_option( AW_SI_LINK_DR_OPTION, array() );
		foreach ( $parsed['dr'] as $domain => $v ) {
			if ( (string) ( $all[ $domain ][1] ?? '' ) <= $day ) { // an older read never replaces a newer one
				$all[ $domain ] = array( (int) $v, $day );
			}
		}
		update_option( AW_SI_LINK_DR_OPTION, $all, false );
	}
	if ( $parsed['pages'] ) {
		usort( $parsed['pages'], fn( $a, $b ) => $b[1] <=> $a[1] );
		update_option(
			AW_SI_LINK_PAGES_OPTION,
			array(
				'day'  => $day,
				'rows' => array_slice( $parsed['pages'], 0, 200 ),
			),
			false
		);
	}
	$reads            = (array) get_option( AW_SI_LINK_READS_OPTION, array() );
	$reads[ $source ] = array( time(), $res['added'] + $res['updated'] + $res['pages'], $day );
	update_option( AW_SI_LINK_READS_OPTION, $reads, false );
	return $res;
}

/** The import form on the Links tab: source, day read, a file or a paste. */
function aw_si_links_import_form(): void {
	$today = wp_date( 'Y-m-d' );
	echo '<h2>' . esc_html__( 'Import links', 'insightweb' ) . '</h2>';
	echo '<p>' . esc_html__( 'Search Console has no links API: export a table from Search Console → Links (Top linking sites, Top linked pages, or Export external links → Latest links) and upload the CSV or paste it here. A list of your own works too: a header row with Linking page or Site, and Linked page, Anchor, Links or DR when you have them. DR is Ahrefs\' Domain Rating typed by hand from its free checker; no Ahrefs data is fetched.', 'insightweb' ) . '</p>';
	echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_si_links_import">';
	wp_nonce_field( 'aw_si_links_import' );
	echo '<table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row"><label for="aw-si-li-source">' . esc_html__( 'Source', 'insightweb' ) . '</label></th><td><select id="aw-si-li-source" name="source">';
	foreach ( aw_si_links_import_sources() as $k => $label ) {
		echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $label ) . '</option>';
	}
	echo '</select></td></tr>';
	echo '<tr><th scope="row"><label for="aw-si-li-day">' . esc_html__( 'Day read', 'insightweb' ) . '</label></th><td><input type="date" id="aw-si-li-day" name="day" value="' . esc_attr( $today ) . '" max="' . esc_attr( $today ) . '"><p class="description">' . esc_html__( 'First and last seen for rows without a date of their own.', 'insightweb' ) . '</p></td></tr>';
	echo '<tr><th scope="row"><label for="aw-si-li-file">' . esc_html__( 'CSV file', 'insightweb' ) . '</label></th><td><input type="file" id="aw-si-li-file" name="file" accept=".csv,.txt,text/csv,text/plain"><p class="description">' . esc_html__( 'Up to 2 MB and 2,000 rows. Read once, then deleted.', 'insightweb' ) . '</p></td></tr>';
	echo '<tr><th scope="row"><label for="aw-si-li-paste">' . esc_html__( 'Or paste', 'insightweb' ) . '</label></th><td><textarea id="aw-si-li-paste" name="paste" rows="6" class="large-text code" placeholder="Site,Linking pages,Target pages&#10;example.org,3,1"></textarea></td></tr>';
	echo '</tbody></table>';
	submit_button( __( 'Import links', 'insightweb' ), 'secondary' );
	echo '</form>';
}

add_action(
	'admin_post_aw_si_links_import',
	function () {
		if ( ! current_user_can( aw_si_cap() ) || ! check_admin_referer( 'aw_si_links_import' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'insightweb' ), 403 );
		}
		$back = aw_si_return_url( 'links' );
		$fail = function ( string $msg ) use ( $back ) {
			set_transient( 'aw_si_links_import_' . get_current_user_id(), array( 'error' => $msg ), 5 * MINUTE_IN_SECONDS );
			wp_safe_redirect( add_query_arg( 'links_imported', 1, $back ) );
			exit;
		};
		if ( get_option( 'aw_si_off' ) || ! aw_si_ready() ) {
			$fail( __( 'Search Insights is off (its data was deleted), so nothing was stored. Settings → Run the daily update starts it again.', 'insightweb' ) );
		}
		$sources = aw_si_links_import_sources();
		$src     = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : 'gsc';
		$src     = isset( $sources[ $src ] ) ? $src : 'gsc';
		$today   = wp_date( 'Y-m-d' );
		$day     = isset( $_POST['day'] ) ? sanitize_text_field( wp_unslash( $_POST['day'] ) ) : '';
		$day     = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) && checkdate( (int) substr( $day, 5, 2 ), (int) substr( $day, 8, 2 ), (int) substr( $day, 0, 4 ) ) && $day <= $today ? $day : $today;
		$raw     = isset( $_POST['paste'] ) ? (string) wp_unslash( $_POST['paste'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parsed as CSV; every value is validated and sanitised in aw_si_links_import_parse().
		if ( ! empty( $_FILES['file']['name'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			$size = isset( $_FILES['file']['size'] ) ? absint( $_FILES['file']['size'] ) : 0;
			if ( $size > AW_SI_LINK_MAX_BYTES ) {
				$fail( __( 'The file is larger than 2 MB.', 'insightweb' ) );
			}
			$up = wp_handle_upload(
				$_FILES['file'], // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- core upload handling checks it.
				array(
					'test_form' => false,
					'mimes'     => array(
						'csv' => 'text/csv',
						'txt' => 'text/plain',
					),
				)
			);
			if ( ! empty( $up['error'] ) ) {
				$fail( (string) $up['error'] );
			}
			$raw = (string) file_get_contents( $up['file'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the uploaded file, read once.
			wp_delete_file( $up['file'] );
		}
		if ( '' === trim( $raw ) ) {
			$fail( __( 'Nothing to import: choose a file or paste the rows.', 'insightweb' ) );
		}
		if ( strlen( $raw ) > AW_SI_LINK_MAX_BYTES ) {
			$fail( __( 'The paste is larger than 2 MB.', 'insightweb' ) );
		}
		/* translators: 1: source, 2: date read. */
		$source = sprintf( __( '%1$s, read %2$s', 'insightweb' ), $sources[ $src ], wp_date( 'j M Y', (int) strtotime( $day . ' 12:00:00' ) ) );
		$res    = aw_si_links_import_store( aw_si_links_import_parse( $raw, home_url( '/' ), $source, $day, $today ), $sources[ $src ], $day );
		set_transient( 'aw_si_links_import_' . get_current_user_id(), $res, 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'links_imported', 1, $back ) );
		exit;
	}
);

/** The import's result notice on the Links tab. */
function aw_si_links_import_notice(): void {
	if ( ! isset( $_GET['links_imported'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- message only.
		return;
	}
	$r = get_transient( 'aw_si_links_import_' . get_current_user_id() );
	if ( ! is_array( $r ) ) {
		return;
	}
	delete_transient( 'aw_si_links_import_' . get_current_user_id() );
	if ( '' !== (string) ( $r['error'] ?? '' ) ) {
		wp_admin_notice( esc_html( (string) $r['error'] ), array( 'type' => 'error', 'additional_classes' => array( 'inline' ) ) );
		return;
	}
	$kinds = array(
		'sites'  => __( 'Top linking sites', 'insightweb' ),
		'pages'  => __( 'Top linked pages', 'insightweb' ),
		'latest' => __( 'Latest links', 'insightweb' ),
		'list'   => __( 'List', 'insightweb' ),
	);
	/* translators: 1: kind of export, 2: links added, 3: links already there (dates updated), 4: linked pages, 5: DR values, 6: rows skipped. */
	$msg = sprintf( __( '%1$s imported: %2$s links added, %3$s already there (dates updated), %4$s linked pages, %5$s DR values; %6$s rows skipped.', 'insightweb' ), $kinds[ $r['kind'] ] ?? '', number_format_i18n( (int) $r['added'] ), number_format_i18n( (int) $r['updated'] ), number_format_i18n( (int) $r['pages'] ), number_format_i18n( (int) $r['dr'] ), number_format_i18n( (int) $r['skipped'] + (int) $r['failed'] ) );
	wp_admin_notice( esc_html( $msg ), array( 'type' => 'success', 'additional_classes' => array( 'inline' ) ) );
}

/** The last read of each source: Search Console imports and reads, Bing Webmaster's run, Google Alerts, DR. */
function aw_si_links_reads_section(): void {
	global $wpdb;
	$by = array();
	if ( aw_si_ready() ) {
		$t = aw_si_table( 'links' );
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT source, COUNT(*) n, MAX(last_seen) d FROM %i GROUP BY source', $t ) ) as $r ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$k        = aw_si_link_source_key( (string) $r->source );
			$k        = '' !== $k ? $k : __( 'No source', 'insightweb' );
			$by[ $k ] = array(
				( $by[ $k ][0] ?? 0 ) + (int) $r->n,
				max( (string) ( $by[ $k ][1] ?? '' ), (string) $r->d ),
			);
		}
	}
	$runs  = aw_si_runs();
	$reads = (array) get_option( AW_SI_LINK_READS_OPTION, array() );
	$rows  = array();
	foreach ( $by as $k => $v ) {
		// The day of the read itself when one was logged: rows keep their own (older) last seen from the export.
		$day  = max( $v[1], is_array( $reads[ $k ] ?? null ) ? (string) ( $reads[ $k ][2] ?? '' ) : '' );
		$read = '' !== $day ? mysql2date( 'j M Y', $day ) : '–';
		if ( defined( 'AW_SI_BING_SOURCE' ) && AW_SI_BING_SOURCE === $k && ! empty( $runs['bing']['ok'] ) ) {
			$read = wp_date( 'j M Y', (int) $runs['bing']['ok'] ); // the monthly run itself, even when it found nothing new
		}
		$rows[] = array( $k, number_format_i18n( $v[0] ), $read, $v[0], $day );
	}
	foreach ( $reads as $src => $r ) {
		if ( ! isset( $by[ $src ] ) && is_array( $r ) ) {
			$rows[] = array( (string) $src, '–', mysql2date( 'j M Y', (string) ( $r[2] ?? '' ) ), -1, (string) ( $r[2] ?? '' ) ); // a read with no link rows (Top linked pages only)
		}
	}
	$pages = (array) get_option( AW_SI_LINK_PAGES_OPTION, array() );
	if ( ! empty( $pages['day'] ) ) {
		$rows[] = array( __( 'Search Console Links: top linked pages', 'insightweb' ), number_format_i18n( count( (array) ( $pages['rows'] ?? array() ) ) ), mysql2date( 'j M Y', (string) $pages['day'] ), count( (array) ( $pages['rows'] ?? array() ) ), (string) $pages['day'] );
	}
	$dr = (array) get_option( AW_SI_LINK_DR_OPTION, array() );
	if ( $dr ) {
		$dr_day = (string) max( array_map( fn( $x ) => (string) ( $x[1] ?? '' ), $dr ) );
		$rows[] = array( __( 'DR (Ahrefs, typed by hand)', 'insightweb' ), number_format_i18n( count( $dr ) ), mysql2date( 'j M Y', $dr_day ), count( $dr ), $dr_day );
	}
	echo '<h2>' . esc_html__( 'Last read per source', 'insightweb' ) . '</h2>';
	if ( ! $rows ) {
		echo '<p>' . esc_html__( 'Nothing read yet.', 'insightweb' ) . '</p>';
		return;
	}
	$cells = array();
	foreach ( $rows as $r ) {
		$cells[] = array(
			'source' => esc_html( $r[0] ),
			'rows'   => esc_html( $r[1] ),
			'read'   => esc_html( $r[2] ),
			'_sort'  => array(
				'rows' => $r[3],
				'read' => $r[4],
			),
		);
	}
	aw_si_simple_table(
		'link-reads',
		array(
			'source' => esc_html__( 'Source', 'insightweb' ),
			'rows'   => esc_html__( 'Rows', 'insightweb' ),
			'read'   => esc_html__( 'Last read', 'insightweb' ),
		),
		$cells,
		array(
			'sortable' => array(
				'source' => false,
				'rows'   => true,
				'read'   => true,
			),
		)
	);
	if ( ! empty( $pages['rows'] ) ) {
		/* translators: %s: date read. */
		echo '<h2>' . esc_html( sprintf( __( 'Top linked pages (Search Console, read %s)', 'insightweb' ), mysql2date( 'j M Y', (string) $pages['day'] ) ) ) . '</h2>';
		$cells = array();
		foreach ( array_slice( (array) $pages['rows'], 0, 10 ) as $p ) {
			$path    = aw_si_path( (string) $p[0] );
			$cells[] = array(
				'page'  => '<a href="' . esc_url( (string) $p[0] ) . '" target="_blank" rel="noopener">' . esc_html( '' !== $path ? $path : (string) $p[0] ) . '</a>',
				'links' => esc_html( number_format_i18n( (int) $p[1] ) ),
				'sites' => esc_html( number_format_i18n( (int) $p[2] ) ),
				'_sort' => array(
					'links' => (int) $p[1],
					'sites' => (int) $p[2],
				),
			);
		}
		aw_si_simple_table(
			'linked-pages',
			array(
				'page'  => esc_html__( 'Our page', 'insightweb' ),
				'links' => esc_html__( 'Incoming links', 'insightweb' ),
				'sites' => esc_html__( 'Linking sites', 'insightweb' ),
			),
			$cells,
			array(
				'sortable' => array(
					'page'  => false,
					'links' => true,
					'sites' => true,
				),
			)
		);
	}
}
