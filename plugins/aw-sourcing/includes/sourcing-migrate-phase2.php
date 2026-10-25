<?php
/**
 * Sourcing restructure phase 2 (Keywords) from WP-CLI, the phase 1 method (sourcing-migrate.php): backup, dry run,
 * migrate, verify, rollback. The plan is computed by aw_kw_phase2_plan() (keywords-model.php, tested without
 * WordPress); this file reads the stores, prints the report and writes.
 *
 *     wp aw-sourcing backup --phase=2
 *     wp aw-sourcing migrate --phase=2 --dry-run    (every list's counts and the per-word whole-word report)
 *     wp aw-sourcing migrate --phase=2
 *     wp aw-sourcing verify --phase=2
 *     wp aw-sourcing rollback --phase=2
 *
 * What the migration writes:
 * - the keyword table at version 2 (aw_kw_install) and, per record, its roles (through aw_kw_set_roles, the one role
 *   setter), other spellings (with the * spellings of the whole-word report), search (query, feed id), dismissed flag,
 *   src_note (each list and place it came from), category and section maps;
 * - the old lists rebuilt from the records (mirrors): aw_relevance strong / animal / context / exclude (its two switches
 *   untouched), aw_sources.press.queries and aw_sources.categories;
 * - aw_sources.sources: the keyword check on the 7 sources of decisions 4 and 5 (check) and, on the 3 open-web Google
 *   News searches that are keywords now, keyword_id (the entries stay for old workers until phase 4);
 * - aw_sources.press without the press sites and Brave budgets (decision 7);
 * - aw_sourcing_phase = 2, the record aw_sourcing_phase2_record (for verify and rollback), one change log entry per list.
 * Nothing visible changes until the S2b screens; the policy sends the same v1 lists (less the Hindi words) until then.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/** Columns of a keyword record the backup keeps (the owner's and sourcing's; Search Insights' figures are rebuilt daily). */
const AW_SOURCING_P2_COLUMNS = 'id, norm, label, lang, roles, variants, query, feed_id, search_on, dismissed, src_note';

/**
 * The keyword table and map table rows a phase 2 backup keeps.
 *
 * @return array{table:keywords: array[], table:kw_map: array[]}
 */
function aw_sourcing_cli_p2_tables() {
	global $wpdb;
	aw_kw_install();
	$k    = aw_si_table( 'keywords' );
	$m    = aw_si_table( 'kw_map' );
	$cols = AW_SOURCING_P2_COLUMNS;
	return array(
		'table:keywords' => (array) $wpdb->get_results( "SELECT {$cols} FROM {$k} ORDER BY id", ARRAY_A ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table and column names only.
		'table:kw_map'   => (array) $wpdb->get_results( "SELECT kw_id, kind, value FROM {$m} ORDER BY id", ARRAY_A ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	);
}

/**
 * Records with these normal forms (decoded, with their maps).
 *
 * @param string[] $norms Normal forms.
 * @return array[]
 */
function aw_sourcing_cli_p2_rows( array $norms ) {
	global $wpdb;
	$k    = aw_si_table( 'keywords' );
	$cols = AW_SOURCING_P2_COLUMNS;
	$out  = array();
	foreach ( array_chunk( array_values( array_unique( $norms ) ), 200 ) as $chunk ) {
		$in   = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
		$out  = array_merge( $out, (array) $wpdb->get_results( $wpdb->prepare( "SELECT {$cols} FROM {$k} WHERE norm IN ({$in})", $chunk ), ARRAY_A ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- one %s per norm.
	}
	return aw_kw_attach_maps( $out );
}

/**
 * Records with a Sourcing or Press role that none of the lists names (an Include bit Search Insights' daily sync had not
 * cleared yet, or one set on a Hindi word): the migration removes those roles, so the lists stay exactly as they are.
 *
 * @param array $plan The plan.
 * @return array[] id => [label, roles].
 */
function aw_sourcing_cli_p2_stray( array $plan ) {
	global $wpdb;
	$k    = aw_si_table( 'keywords' );
	$ids  = array_flip( array_filter( array_map( fn( $r ) => (int) $r['id'], $plan['records'] ) ) );
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, label, roles FROM {$k} WHERE roles & %d", AW_KW_WORKER_ROLES ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	$out  = array();
	foreach ( $rows as $r ) {
		if ( ! isset( $ids[ (int) $r['id'] ] ) ) {
			$out[ (int) $r['id'] ] = array( (string) $r['label'], (int) $r['roles'] );
		}
	}
	return $out;
}

/** Published News and Library items' title and summary (the whole-word report's texts). */
function aw_sourcing_cli_p2_texts() {
	global $wpdb;
	return array_map( 'strval', (array) $wpdb->get_col( "SELECT CONCAT(post_title, ' ', post_excerpt) FROM {$wpdb->posts} WHERE post_type IN ('aw_news', 'aw_legal') AND post_status = 'publish'" ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/**
 * The plan on the live stores, with the whole-word report's * spellings.
 *
 * @return array [plan, report, texts count]
 */
function aw_sourcing_cli_p2_plan() {
	$rel   = aw_relevance();
	$cfg   = (array) get_option( 'aw_sources', array() );
	$dis   = array_values( array_filter( (array) get_option( 'aw_sourcing_dismissed', array() ), 'is_string' ) );
	$draft = aw_kw_phase2_plan( $rel, $cfg, $dis, array() ); // which records the lists name
	$plan  = aw_kw_phase2_plan( $rel, $cfg, $dis, array_values( aw_sourcing_cli_p2_rows( array_keys( $draft['records'] ) ) ) );
	$words = array();
	foreach ( (array) ( $cfg['categories'] ?? array() ) as $list ) {
		$words = array_merge( $words, array_map( 'strval', (array) $list ) );
	}
	foreach ( $plan['records'] as $r ) {
		foreach ( (array) ( $r['src']['lists']['lab'] ?? array() ) as $p ) {
			$words[] = (string) $p[1];
		}
	}
	$texts  = aw_sourcing_cli_p2_texts();
	$report = aw_kw_word_report( $words, $texts );
	return array( aw_kw_plan_add_stars( $plan, $report ), $report, count( $texts ) );
}

/**
 * The old shapes of a backup (or of the options now) as the lists aw_kw_compare_mirrors() reads.
 *
 * @param array $rel aw_relevance.
 * @param array $cfg aw_sources.
 * @return array
 */
function aw_sourcing_cli_p2_lists( array $rel, array $cfg ) {
	return array(
		'strong'     => array_values( (array) ( $rel['strong'] ?? array() ) ),
		'animal'     => array_values( (array) ( $rel['animal'] ?? array() ) ),
		'context'    => array_values( (array) ( $rel['context'] ?? array() ) ),
		'exclude'    => array_values( (array) ( $rel['exclude'] ?? array() ) ),
		'press'      => array_values( (array) ( $cfg['press']['queries'] ?? array() ) ),
		'categories' => (array) ( $cfg['categories'] ?? array() ),
	);
}

/**
 * The mirrors rebuilt from the table now (records with a Sourcing or Press role), categories in the old option's order.
 *
 * @param array $old_categories aw_sources.categories to order by.
 * @return array
 */
function aw_sourcing_cli_p2_mirrors( array $old_categories ) {
	$m               = aw_kw_mirrors( aw_kw_rows( AW_KW_WORKER_ROLES ) );
	$m['categories'] = aw_kw_categories_in_order( $m['categories'], $old_categories );
	return $m;
}

/**
 * Write the mirrors into the old options (the two switches of aw_relevance and everything else in aw_sources stay).
 *
 * @param array $m      aw_sourcing_cli_p2_mirrors().
 * @param array $cfg    aw_sources to write into.
 * @return array{0: array, 1: array} aw_relevance and aw_sources as written.
 */
function aw_sourcing_cli_p2_write_mirrors( array $m, array $cfg ) {
	$rel = aw_relevance();
	foreach ( array( 'strong', 'animal', 'context', 'exclude' ) as $k ) {
		$rel[ $k ] = $m[ $k ];
	}
	$cfg['press']['queries'] = $m['press'];
	$cfg['categories']       = $m['categories'];
	update_option( AW_REL_OPTION, $rel, false );
	update_option( 'aw_sources', $cfg, false );
	return array( $rel, $cfg );
}

/**
 * Print the plan (the dry run's report).
 *
 * @param array $plan   Plan.
 * @param array $report Whole-word report.
 * @param int   $n      Items the report read.
 * @param int   $total  Keyword records now.
 */
function aw_sourcing_cli_p2_print( array $plan, array $report, $n, $total ) {
	WP_CLI::log( 'Lists → keyword records (matched by normal form: lower case, dots dropped, plurals folded):' );
	$labels = array(
		'strong'  => 'Include (aw_relevance.strong)',
		'animal'  => 'Animal',
		'context' => 'Context',
		'exclude' => 'Exclude',
		'press'   => 'Press queries',
		'lab'     => 'Lab check words not in a category',
		'search'  => 'Open-web Google News searches + the fixed "stray dogs"',
	);
	foreach ( $plan['lists'] as $k => $l ) {
		$name = $labels[ $k ] ?? ( 0 === strpos( $k, 'cat:' ) ? 'Category words: ' . substr( $k, 4 ) : $k );
		WP_CLI::log( sprintf( '  %-58s %3d items, %3d distinct, %3d already in the table%s', $name, $l['items'], $l['distinct'], $l['existing'], $l['merged'] ? '; merged: ' . implode( ', ', $l['merged'] ) : '' ) );
	}
	$worker = count( array_filter( $plan['records'], fn( $r ) => 'en' === $r['lang'] && ( $r['roles'] & AW_KW_WORKER_ROLES ) ) );
	WP_CLI::log( sprintf( 'Records: %d named by the lists; %d already in the table, %d new. Table: %s now, %s after.', count( $plan['records'] ), $plan['existing'], $plan['new'], number_format( $total ), number_format( $total + $plan['new'] ) ) );
	WP_CLI::log( sprintf( 'Keywords the workers will receive (a Sourcing or Press role, English): %d. Category mappings added: %d.', $worker, $plan['cat_maps'] ) );
	WP_CLI::log( 'Searches (Search role, feed ids kept): ' . implode( ', ', array_map( fn( $f, $n ) => "$f → " . $plan['records'][ $n ]['label'], array_keys( $plan['searches'] ), $plan['searches'] ) ) );
	WP_CLI::log( 'Not English, so no Sourcing role (decision 13; kept as records for Search Insights): ' . ( $plan['hindi'] ? implode( ', ', array_map( fn( $h ) => $h[1] . ' (' . $h[0] . ')', $plan['hindi'] ) ) : '(none)' ) );
	WP_CLI::log( 'Include and Exclude on one record: ' . ( $plan['conflicts'] ? implode( '; ', $plan['conflicts'] ) : '0' ) );
	WP_CLI::log( 'Dismissed suggestions moved to the record: ' . count( $plan['dismissed'] ) );
	$stray = aw_sourcing_cli_p2_stray( $plan );
	WP_CLI::log( 'Records with a Sourcing role that no list names (the role is removed): ' . ( $stray ? implode( ', ', array_map( fn( $x ) => $x[0], $stray ) ) : '(none)' ) );
	$cfg  = (array) get_option( 'aw_sources', array() );
	$rel  = aw_relevance();
	$cats = (array) ( $cfg['categories'] ?? array() );
	foreach ( $plan['checks'] as $id => $chk ) {
		$was = array();
		foreach ( (array) $cfg['sources'] as $s ) {
			if ( is_array( $s ) && ( $s['id'] ?? '' ) === $id ) {
				$was = array_map( 'strval', (array) ( $s['keywords'] ?? array() ) );
			}
		}
		$now = array();
		foreach ( $plan['records'] as $r ) {
			if ( 'en' !== $r['lang'] ) {
				continue;
			}
			if ( ( in_array( 'include', $chk['roles'], true ) && ( $r['roles'] & AW_KW_ROLE_INCLUDE ) ) || ( ( $r['roles'] & AW_KW_ROLE_CATEGORY ) && array_intersect( $chk['categories'], $r['categories'] ) ) ) {
				$now = array_merge( $now, aw_kw_spellings( $r ) );
			}
		}
		$wn   = array_map( 'aw_si_norm', $was );
		$nn   = array_map( 'aw_si_norm', $now );
		$add  = array_unique( array_filter( $now, fn( $w ) => ! in_array( aw_si_norm( $w ), $wn, true ) && false === strpos( $w, '*' ) ) );
		$drop = array_unique( array_filter( $was, fn( $w ) => ! in_array( aw_si_norm( $w ), $nn, true ) ) );
		WP_CLI::log( sprintf( 'Keyword check of %s: %s (decision %d). Adds: %s. Drops: %s.', $id, $chk['roles'] ? 'Include role' : 'keywords mapped to ' . implode( ', ', $chk['categories'] ), $chk['roles'] ? 4 : 5, $add ? implode( ', ', $add ) : '(none)', $drop ? implode( ', ', $drop ) : '(none)' ) );
	}
	$press = array_intersect( AW_KW_PRESS_DROPPED, array_keys( (array) ( $cfg['press'] ?? array() ) ) );
	WP_CLI::log( 'Press settings removed with Brave (decision 7, kept in the backup): ' . ( $press ? implode( ', ', $press ) . ( ! empty( $cfg['press']['sites'] ) ? ' (sites: ' . implode( ', ', (array) $cfg['press']['sites'] ) . ')' : '' ) : '(none)' ) );

	// The mirrors the records will give, against the lists now.
	$i    = 1000000000;
	$recs = array();
	foreach ( $plan['records'] as $r ) {
		$r['id'] = $r['id'] ? $r['id'] : ++$i;
		$recs[]  = $r;
	}
	$m               = aw_kw_mirrors( $recs );
	$m['categories'] = aw_kw_categories_in_order( $m['categories'], $cats );
	$cmp             = aw_kw_compare_mirrors( aw_sourcing_cli_p2_lists( $rel, $cfg ), $m, $plan['hindi'] );
	foreach ( $cmp['planned'] as $p ) {
		WP_CLI::log( "Old list after the migration (planned): $p" );
	}
	foreach ( $cmp['diffs'] as $d ) {
		WP_CLI::warning( "Old list would change: $d" );
	}
	$on      = array_values( array_filter( (array) $cfg['sources'], fn( $s ) => is_array( $s ) && ! empty( $s['on'] ) ) );
	$open    = aw_kw_open_searches( (array) $cfg['sources'] );
	$v1      = aw_kw_v1_searches( $on, (array) ( $rel['strong'] ?? array() ) );
	$v2      = aw_kw_v2_searches( array_values( array_filter( $on, fn( $s ) => ! isset( $open[ $s['id'] ] ) ) ), aw_kw_payload( $recs, (array) $cfg['sources'] ) );
	$same    = $v1;
	$same2   = $v2;
	ksort( $same );
	ksort( $same2 );
	WP_CLI::log( sprintf( 'Google News searches per run: %d before, %d from the keywords; %s.', count( $v1 ), count( $v2 ), $same === $same2 ? 'the same feed ids and queries' : 'DIFFERENT' ) );
	if ( $same !== $same2 ) {
		WP_CLI::warning( 'searches only before: ' . implode( ', ', array_keys( array_diff_assoc( $same, $same2 ) ) ) . '; only after: ' . implode( ', ', array_keys( array_diff_assoc( $same2, $same ) ) ) );
	}

	WP_CLI::log( '' );
	WP_CLI::log( sprintf( 'Whole-word report (decision 6): hits of each category and lab word on the %d published News and Library items, today (substring; whole word for ASCII words up to 4 letters) and with whole words (plural allowed). Words that lose hits get a trailing * spelling when it wins them back.', $n ) );
	$rows = array();
	foreach ( $report as $w => $r ) {
		$rows[] = array(
			'word'        => $w,
			'today'       => $r[0],
			'whole words' => $r[1],
			'change'      => $r[1] - $r[0],
			'with *'      => $r[3] ? $r[2] : '',
			'* added'     => $r[3] ? 'yes' : '',
		);
	}
	usort( $rows, fn( $a, $b ) => ( abs( $b['change'] ) <=> abs( $a['change'] ) ) ?: strcmp( $a['word'], $b['word'] ) );
	WP_CLI\Utils\format_items( 'table', $rows, array( 'word', 'today', 'whole words', 'change', 'with *', '* added' ) );
	WP_CLI::log( sprintf( '%d of %d words match differently; * added to %d: %s', count( array_filter( $rows, fn( $r ) => 0 !== $r['change'] ) ), count( $rows ), count( $plan['starred'] ), implode( ', ', array_map( fn( $w ) => $w . '*', array_keys( $plan['starred'] ) ) ) ) );
	if ( $plan['not_starred'] ) {
		WP_CLI::log( 'No * for these, as they are also Include or Exclude words (a spelling counts for every role): ' . implode( ', ', array_keys( $plan['not_starred'] ) ) );
	}
	return $cmp;
}

/**
 * Migrate phase 2 (or, with $dry, print what it would do).
 *
 * @param bool $dry Dry run.
 */
function aw_sourcing_cli_p2_migrate( $dry ) {
	global $wpdb;
	if ( aw_sourcing_phase() >= 2 ) {
		WP_CLI::error( 'Phase 2 is already migrated (aw_sourcing_phase = ' . aw_sourcing_phase() . '). wp aw-sourcing verify --phase=2 checks it.' );
	}
	if ( aw_sourcing_phase() < 1 ) {
		WP_CLI::error( 'Phase 1 first (aw_sourcing_phase = ' . aw_sourcing_phase() . ').' );
	}
	$bk = '';
	if ( ! $dry && get_option( 'aw_sourcing_phase2_record' ) ) {
		WP_CLI::error( 'An unfinished phase 2 migration is recorded (aw_sourcing_phase2_record). Undo it first: wp aw-sourcing rollback --phase=2' );
	}
	if ( ! $dry ) {
		list( $bk ) = aw_sourcing_cli_backup( 2, (string) get_option( 'aw_backup_latest_2', '' ) ); // refuses without a backup
		WP_CLI::log( "Backup: $bk" );
	}
	aw_kw_install(); // version 2 columns and the map table (adds columns; nothing is dropped)
	$total                     = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . aw_si_table( 'keywords' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- table name only.
	list( $plan, $report, $n ) = aw_sourcing_cli_p2_plan();
	$cmp                       = aw_sourcing_cli_p2_print( $plan, $report, $n, $total );
	if ( $plan['conflicts'] ) {
		WP_CLI::warning( count( $plan['conflicts'] ) . ' conflicts: resolve them first.' );
	}
	if ( $dry ) {
		WP_CLI::success( 'Dry run: nothing written. ' . count( $plan['conflicts'] ) . ' conflicts, ' . count( $cmp['diffs'] ) . ' unplanned list changes.' );
		return;
	}
	if ( $plan['conflicts'] || $cmp['diffs'] ) {
		WP_CLI::error( 'Not migrated: resolve the conflicts and list changes above first.' );
	}

	// Search Insights' daily role sync must not rebuild Include from the old list while records are being written
	// (it would put the role back on rows this migration took it from); cleared when phase 2 is set or rolled back.
	update_option( 'aw_sourcing_migrating', 2, false );

	// The records: created or updated, roles through the one role setter, maps replaced by the planned ones.
	$undo    = array();
	$failed  = array();
	$created = 0;
	foreach ( $plan['records'] as $norm => $r ) {
		$fields = array(
			'variants'  => $r['variants'],
			'query'     => $r['query'],
			'feed_id'   => $r['feed_id'],
			'search_on' => $r['search_on'],
			'dismissed' => $r['dismissed'],
			'src_note'  => $r['src'] ? (string) wp_json_encode( $r['src'], JSON_UNESCAPED_UNICODE ) : '',
		);
		$id     = (int) $r['id'] ? (int) $r['id'] : aw_kw_id_by_norm( $norm ); // Search Insights may have added it since the plan was read
		if ( ! $id ) {
			$id = aw_kw_create( $r['label'], $fields + array( 'lang' => $r['lang'] ) );
			if ( ! $id ) {
				$failed[] = $r['label'];
				continue;
			}
			++$created;
			$undo[ $id ] = array( 'created' => true );
		} else {
			$old         = aw_kw_get( $id );
			$undo[ $id ] = array(
				'created'    => false,
				'roles'      => (int) $old['roles'],
				'variants'   => $old['variants'],
				'query'      => (string) $old['query'],
				'feed_id'    => (string) $old['feed_id'],
				'search_on'  => (int) $old['search_on'],
				'dismissed'  => (int) $old['dismissed'],
				'src_note'   => (string) ( $old['src_note'] ?? '' ),
				'categories' => $old['categories'],
				'sections'   => $old['sections'],
			);
			aw_kw_update( $id, $fields );
		}
		$now = aw_kw_get( $id );
		$set = aw_kw_set_roles( $id, $r['roles'] & ~$now['roles'], $now['roles'] & ~$r['roles'] & AW_KW_WORKER_ROLES );
		if ( is_wp_error( $set ) ) {
			$failed[] = $r['label'] . ': ' . $set->get_error_message();
		}
		$undo[ $id ]['added'] = (int) ( $r['roles'] & ~$now['roles'] );
		aw_kw_map_set( $id, 'category', $r['categories'] );
		aw_kw_map_set( $id, 'section', $r['sections'] );
		$plan['records'][ $norm ]['id'] = $id;
	}
	foreach ( aw_sourcing_cli_p2_stray( $plan ) as $id => $x ) { // a Sourcing role no list names: removed, so the lists stay as they are
		$old         = aw_kw_get( $id );
		$undo[ $id ] = array(
			'created'    => false,
			'roles'      => (int) $old['roles'],
			'variants'   => $old['variants'],
			'query'      => (string) $old['query'],
			'feed_id'    => (string) $old['feed_id'],
			'search_on'  => (int) $old['search_on'],
			'dismissed'  => (int) $old['dismissed'],
			'src_note'   => (string) ( $old['src_note'] ?? '' ),
			'categories' => $old['categories'],
			'sections'   => $old['sections'],
			'added'      => 0,
		);
		aw_kw_set_roles( $id, 0, AW_KW_WORKER_ROLES );
	}
	$record = array(
		'at'      => current_time( 'mysql' ),
		'backup'  => $bk,
		'records' => $undo,
		'hindi'   => $plan['hindi'],
		'checks'  => $plan['checks'],
		'kw_ids'  => array(),
		'starred' => $plan['starred'],
		'partial' => true, // until the options are written too
	);
	update_option( 'aw_sourcing_phase2_record', $record, false );
	if ( $failed ) {
		WP_CLI::error( 'Not written: ' . implode( '; ', $failed ) . '. The options and the phase are unchanged; undo the records written: wp aw-sourcing rollback --phase=2' );
	}
	// The lists as the records give them now must be the lists the plan gave (nothing lost on the way).
	$cfg0    = (array) get_option( 'aw_sources', array() );
	$planned = array();
	foreach ( $plan['records'] as $r ) {
		$planned[] = $r;
	}
	$want               = aw_kw_mirrors( $planned );
	$want['categories'] = aw_kw_categories_in_order( $want['categories'], (array) ( $cfg0['categories'] ?? array() ) );
	$got                = aw_sourcing_cli_p2_mirrors( (array) ( $cfg0['categories'] ?? array() ) );
	if ( $got !== $want ) {
		WP_CLI::error( 'The lists rebuilt from the table are not the planned ones; nothing else was written. Undo the records: wp aw-sourcing rollback --phase=2' );
	}

	// The old options: mirrors, checks and keyword ids on the sources, press without Brave.
	$cfg    = (array) get_option( 'aw_sources', array() );
	$kw_ids = array();
	foreach ( aw_kw_open_searches( (array) $cfg['sources'] ) as $feed => $label ) {
		$norm = $plan['searches'][ $feed ] ?? '';
		if ( '' !== $norm && ! empty( $plan['records'][ $norm ]['id'] ) ) {
			$kw_ids[ $feed ] = (int) $plan['records'][ $norm ]['id'];
		}
	}
	$cfg['sources'] = aw_kw_apply_to_sources( (array) $cfg['sources'], $plan['checks'], $kw_ids );
	foreach ( AW_KW_PRESS_DROPPED as $k ) {
		unset( $cfg['press'][ $k ] );
	}
	aw_sourcing_cli_p2_write_mirrors( $got, $cfg );
	$record['kw_ids'] = $kw_ids;
	unset( $record['partial'] );
	update_option( 'aw_sourcing_phase2_record', $record, false );
	update_option( 'aw_sourcing_phase', 2, false );
	delete_option( 'aw_sourcing_migrating' );
	$log = array();
	foreach ( $plan['lists'] as $k => $l ) {
		$log[] = array( 'keywords', sprintf( 'Phase 2 migrated (WP-CLI): %s, %d words → %d keyword records (%d already there)', $k, $l['items'], $l['distinct'], $l['existing'] ) );
	}
	$log[] = array( 'keywords', sprintf( 'Phase 2 migrated (WP-CLI): %d records created, %d updated; * added to %d category words; %d words not English kept without a Sourcing role; press sites and Brave budgets removed (in backup %s)', $created, count( $undo ) - $created, count( $plan['starred'] ), count( $plan['hindi'] ), $bk ), 'phase 1', 'phase 2' );
	kits_sourcing_log_many( $log );
	WP_CLI::success( "Phase 2 migrated: $created records created, " . ( count( $undo ) - $created ) . ' updated. Next: wp aw-sourcing verify --phase=2' );
}

/**
 * Verify phase 2 against its backup.
 *
 * @param string $bk  Backup name.
 * @param array  $old Backed-up options and tables.
 */
function aw_sourcing_cli_p2_verify( $bk, array $old ) {
	WP_CLI::log( "Backup: $bk" );
	WP_CLI::log( 'Backup: ' . aw_sourcing_cli_counts( $old ) );
	$now = array();
	foreach ( AW_SOURCING_BACKUP_OPTIONS_2 as $k ) {
		$now[ $k ] = get_option( $k, null );
	}
	$now += aw_sourcing_cli_p2_tables();
	WP_CLI::log( 'Now:    ' . aw_sourcing_cli_counts( $now ) );
	if ( aw_sourcing_phase() < 2 ) {
		WP_CLI::error( 'Phase 2 is not migrated (aw_sourcing_phase = ' . aw_sourcing_phase() . ').' );
	}
	$rec     = (array) get_option( 'aw_sourcing_phase2_record', array() );
	$diffs   = array();
	$planned = array();
	$since   = array();
	$c_old   = (array) $old['aw_sources'];
	$c_now   = (array) $now['aw_sources'];

	// 1. The old lists rebuilt from the table equal the backup's, apart from the planned changes; the options hold them.
	$m   = aw_sourcing_cli_p2_mirrors( (array) ( $c_old['categories'] ?? array() ) );
	$cmp = aw_kw_compare_mirrors( aw_sourcing_cli_p2_lists( (array) $old['aw_relevance'], $c_old ), $m, (array) ( $rec['hindi'] ?? array() ) );
	$diffs   = array_merge( $diffs, $cmp['diffs'] );
	$planned = array_merge( $planned, $cmp['planned'] );
	$opt     = aw_sourcing_cli_p2_lists( (array) $now['aw_relevance'], $c_now );
	foreach ( array( 'strong', 'animal', 'context', 'exclude', 'press' ) as $k ) {
		if ( $opt[ $k ] !== $m[ $k ] ) {
			$diffs[] = AW_KW_LISTS[ $k ][1] . ' is not the list rebuilt from the table';
		}
	}
	if ( $opt['categories'] !== $m['categories'] ) {
		$diffs[] = 'aw_sources.categories is not the list rebuilt from the table';
	}
	foreach ( array( 'enabled', 'gnews_allow_only' ) as $k ) {
		if ( ! empty( $old['aw_relevance'][ $k ] ) !== ! empty( $now['aw_relevance'][ $k ] ) ) {
			$diffs[] = "aw_relevance.$k changed";
		}
	}

	// 2. aw_sources apart from the lists: the sources gain only check / keyword_id; press loses only the Brave keys.
	$strip = function ( array $list ) {
		foreach ( $list as $i => $s ) {
			if ( is_array( $s ) ) {
				unset( $list[ $i ]['check'], $list[ $i ]['keyword_id'] );
			}
		}
		return $list;
	};
	if ( $strip( (array) ( $c_now['sources'] ?? array() ) ) !== $strip( (array) ( $c_old['sources'] ?? array() ) ) ) {
		$since[] = 'aw_sources.sources changed beyond the keyword checks (a feed edited since the backup?)';
	}
	$planned[] = 'keyword check set on ' . count( (array) ( $rec['checks'] ?? array() ) ) . ' sources: ' . implode( ', ', array_keys( (array) ( $rec['checks'] ?? array() ) ) );
	$planned[] = 'open-web searches that are keywords now: ' . implode( ', ', array_map( fn( $f, $id ) => "$f (keyword #$id)", array_keys( (array) ( $rec['kw_ids'] ?? array() ) ), (array) ( $rec['kw_ids'] ?? array() ) ) );
	$p_old = (array) ( $c_old['press'] ?? array() );
	$p_now = (array) ( $c_now['press'] ?? array() );
	foreach ( array_merge( AW_KW_PRESS_DROPPED, array( 'queries' ) ) as $k ) {
		unset( $p_old[ $k ], $p_now[ $k ] );
	}
	if ( $p_old !== $p_now ) {
		$diffs[] = 'aw_sources.press changed beyond the Brave keys';
	}
	$planned[] = 'press: removed ' . implode( ', ', array_intersect( AW_KW_PRESS_DROPPED, array_keys( (array) ( $c_old['press'] ?? array() ) ) ) );
	$rest_old  = $c_old;
	$rest_now  = $c_now;
	foreach ( array( 'sources', 'press', 'categories' ) as $k ) {
		unset( $rest_old[ $k ], $rest_now[ $k ] );
	}
	if ( $rest_old !== $rest_now ) {
		$diffs[] = 'aw_sources (other keys) changed';
	}

	// 3. The table: records the migration did not touch keep their owner roles; touched ones gained only planned roles.
	$touched = (array) ( $rec['records'] ?? array() );
	$was     = array();
	foreach ( (array) $old['table:keywords'] as $r ) {
		$was[ (int) $r['id'] ] = $r;
	}
	$is = array();
	foreach ( (array) $now['table:keywords'] as $r ) {
		$is[ (int) $r['id'] ] = $r;
	}
	$keep = AW_KW_OWNER_ROLES;
	foreach ( $was as $id => $r ) {
		if ( ! isset( $is[ $id ] ) ) {
			$diffs[] = 'record #' . $id . ' (' . $r['label'] . ') is missing';
			continue;
		}
		if ( isset( $touched[ $id ] ) ) {
			continue;
		}
		if ( ( (int) $r['roles'] & $keep ) !== ( (int) $is[ $id ]['roles'] & $keep ) ) {
			$diffs[] = 'record #' . $id . ' (' . $r['label'] . ') roles ' . (int) $r['roles'] . ' → ' . (int) $is[ $id ]['roles'];
		}
	}
	$new = count( array_filter( $touched, fn( $t ) => ! empty( $t['created'] ) ) );
	foreach ( array_keys( $touched ) as $id ) {
		if ( ! isset( $is[ $id ] ) ) {
			$diffs[] = 'migrated record #' . $id . ' is missing';
		}
	}
	$planned[] = sprintf( 'keyword records: %d created, %d updated', $new, count( $touched ) - $new );
	$extra     = count( $is ) - count( $was ) - $new;
	if ( $extra ) {
		$since[] = sprintf( '%+d records since the backup (Search Insights\' daily run)', $extra );
	}

	// 4. The workers' view: the same Google News searches and press queries from the keywords as from the backup.
	$on      = array_values( array_filter( (array) $c_old['sources'], fn( $s ) => is_array( $s ) && ! empty( $s['on'] ) ) );
	$v1      = aw_kw_v1_searches( $on, (array) ( $old['aw_relevance']['strong'] ?? array() ) );
	$rows    = aw_kw_rows( AW_KW_WORKER_ROLES );
	$payload = aw_kw_payload( $rows, (array) $c_now['sources'] );
	$v2      = aw_kw_v2_searches( array_values( array_filter( (array) $c_now['sources'], fn( $s ) => is_array( $s ) && ! empty( $s['on'] ) && empty( $s['keyword_id'] ) ) ), $payload );
	ksort( $v1 );
	ksort( $v2 );
	if ( $v1 === $v2 ) {
		$planned[] = 'Google News searches: ' . count( $v2 ) . ', the same feed ids and queries as before';
	} else {
		$diffs[] = 'Google News searches differ: only before ' . implode( ', ', array_keys( array_diff_assoc( $v1, $v2 ) ) ) . '; only after ' . implode( ', ', array_keys( array_diff_assoc( $v2, $v1 ) ) );
	}
	$q = array();
	foreach ( $payload as $k ) {
		foreach ( (array) ( $k['queries'] ?? array() ) as $x ) {
			$q[ mb_strtolower( $x ) ] = $x;
		}
	}
	if ( array_values( $q ) !== array_values( (array) ( $c_old['press']['queries'] ?? array() ) ) ) {
		$diffs[] = 'press queries from the keywords (' . implode( ', ', $q ) . ') are not the old ones';
	}
	$hindi = array_filter( $payload, fn( $k ) => 'en' !== $k['lang'] );
	if ( $hindi ) {
		$diffs[] = 'keywords for the workers that are not English: ' . implode( ', ', array_map( fn( $k ) => $k['label'], $hindi ) );
	}
	$planned[] = 'keywords for the workers: ' . count( $payload ) . ' (Sourcing or Press role, English)';
	$dis_old   = count( array_unique( array_map( 'aw_si_norm', array_filter( (array) $old['aw_sourcing_dismissed'], 'is_string' ) ) ) );
	$dis_now   = count( array_filter( (array) $now['table:keywords'], fn( $r ) => (int) $r['dismissed'] ) );
	if ( $dis_now < $dis_old ) {
		$diffs[] = "dismissed suggestions: $dis_old before, $dis_now on records";
	}
	foreach ( $planned as $p ) {
		WP_CLI::log( "planned: $p" );
	}
	foreach ( $since as $p ) {
		WP_CLI::log( "since the backup (not the migration): $p" );
	}
	foreach ( $diffs as $d ) {
		WP_CLI::warning( "difference: $d" );
	}
	if ( $diffs ) {
		WP_CLI::error( count( $diffs ) . ' differences beyond the planned changes.' );
	}
	WP_CLI::success( '0 differences apart from the planned changes.' );
}

/**
 * Roll phase 2 back: the records the migration created are deleted, the ones it changed get back their own columns,
 * Sourcing and Press roles and maps (Search Insights' roles and figures since are kept); the old lists, the sources'
 * keyword checks and the press settings come back from the backup (aw_relevance's switches as they are now).
 *
 * @param string $bk    Backup name.
 * @param array  $old   Backed-up options and tables.
 * @param array  $assoc Flags (--yes).
 */
function aw_sourcing_cli_p2_rollback( $bk, array $old, array $assoc ) {
	global $wpdb;
	$rec = (array) get_option( 'aw_sourcing_phase2_record', array() );
	$why = aw_src_rollback_refusal( 2, aw_sourcing_phase(), (bool) $rec );
	if ( '' !== $why ) {
		WP_CLI::error( $why );
	}
	WP_CLI::confirm( "Roll phase 2 back to backup $bk (records the migration created are deleted; keyword edits made since phase 2 are lost from the old lists)?", $assoc );
	$k    = aw_si_table( 'keywords' );
	$m    = aw_si_table( 'kw_map' );
	$del  = 0;
	$back = 0;
	foreach ( (array) ( $rec['records'] ?? array() ) as $id => $u ) {
		$id = (int) $id;
		if ( ! empty( $u['created'] ) ) {
			$wpdb->delete( $m, array( 'kw_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( $k, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			++$del;
			continue;
		}
		$cur = aw_kw_get( $id );
		if ( ! $cur ) {
			continue;
		}
		$roles = ( (int) $cur['roles'] & ~AW_KW_WORKER_ROLES ) | ( (int) $u['roles'] & AW_KW_WORKER_ROLES );
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the backed-up values, written as they were (no role check: they were stored like this)
			$k,
			array(
				'roles'     => $roles,
				'variants'  => implode( "\n", (array) $u['variants'] ),
				'query'     => (string) $u['query'],
				'feed_id'   => (string) $u['feed_id'],
				'search_on' => (int) $u['search_on'],
				'dismissed' => (int) $u['dismissed'],
				'src_note'  => (string) $u['src_note'],
				'updated'   => aw_si_now(),
			),
			array( 'id' => $id )
		);
		$wpdb->delete( $m, array( 'kw_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( array( 'category' => 'categories', 'section' => 'sections' ) as $kind => $key ) {
			foreach ( (array) $u[ $key ] as $v ) {
				$wpdb->insert( $m, array( 'kw_id' => $id, 'kind' => $kind, 'value' => (string) $v ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
		}
		++$back;
	}
	$rel = aw_relevance();
	foreach ( array( 'strong', 'animal', 'context', 'exclude' ) as $key ) {
		$rel[ $key ] = (array) ( $old['aw_relevance'][ $key ] ?? array() );
	}
	update_option( AW_REL_OPTION, $rel, false );
	$cfg               = (array) get_option( 'aw_sources', array() );
	$c_old             = (array) $old['aw_sources'];
	$cfg['press']      = (array) ( $c_old['press'] ?? array() );
	$cfg['categories'] = (array) ( $c_old['categories'] ?? array() );
	foreach ( (array) ( $cfg['sources'] ?? array() ) as $i => $s ) {
		if ( is_array( $s ) ) {
			unset( $cfg['sources'][ $i ]['check'], $cfg['sources'][ $i ]['keyword_id'] );
		}
	}
	update_option( 'aw_sources', $cfg, false );
	update_option( 'aw_sourcing_dismissed', $old['aw_sourcing_dismissed'] ?? array(), false );
	update_option( 'aw_sourcing_phase', aw_src_phase_after_p2_rollback( (int) ( $old['aw_sourcing_phase'] ?? 1 ), aw_sourcing_phase(), ! empty( $rec['partial'] ) ), false );
	delete_option( 'aw_sourcing_phase2_record' );
	delete_option( 'aw_sourcing_migrating' );
	delete_option( 'aw_backup_latest_2' );
	$what = "$del records deleted, $back restored; lists, keyword checks and press settings from $bk";
	kits_sourcing_log( 'keywords', "Phase 2 rolled back (WP-CLI): $what", 'phase 2', 'phase ' . aw_sourcing_phase() );
	WP_CLI::success( "Rolled back: $what. The backup stays ($bk); the table keeps its version 2 columns (unused in phase 1)." );
}
