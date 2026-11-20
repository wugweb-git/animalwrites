<?php
/**
 * Sourcing → Keywords on the keyword table (sourcing restructure phase 2, owner 25 Oct 2026). Active once
 * phase 2 is migrated (aw_sourcing_phase() >= 2).
 *
 * - The screen is the Kits shell's (sourceweb-ui.php; its routes in sourceweb-rest-pages.php): the records with a
 *   Sourcing or Press role, views by role, the Press view with its settings, Category words, Hindi (no Sourcing role),
 *   Suggestions from Search Insights, and each keyword's edit form. This file has their saves and help tabs.
 * - Every add, edit and delete goes through Core's one role setter (aw_kw_set_roles: English only, never Include with
 *   Exclude) and is logged in the Change log with old → new.
 * - The mirrors: after any keyword change the old lists (aw_relevance strong / animal / context / exclude,
 *   aw_sources.press.queries and .categories, and the old copy of each search that was a feed) are rebuilt from the
 *   table, once per request, so old readers and old workers see the same words until phase 4. Writing them starts
 *   Sources sync, as any list save does. From phase 4 (owner, 25 Oct 2026) the old lists are archived and no longer
 *   rebuilt; only each open-web search's entry in aw_sources.sources still follows its keyword (the Feeds list shows it,
 *   and the keyword's search takes its settings from it).
 * - The site's checks read the table: the gate's keyword check and "Test a headline" (aw_sourcing_kw_lists()).
 * - The old Categories tab opens Posts → Categories (with a Keywords column); the old Press queries tab opens Keywords →
 *   Press.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Phase 2 is migrated: the keyword table is the lists' home. */
function aw_sourcing_kw_on() {
	return function_exists( 'aw_kw_ready' ) && aw_sourcing_phase() >= 2 && aw_kw_ready();
}

/** The worker roles on this screen: bit => label (the old list names in brackets where they differ). */
function aw_sourcing_kw_roles() {
	return array(
		AW_KW_ROLE_INCLUDE  => 'Include',
		AW_KW_ROLE_ANIMAL   => 'Animal',
		AW_KW_ROLE_CONTEXT  => 'Context',
		AW_KW_ROLE_EXCLUDE  => 'Exclude',
		AW_KW_ROLE_SEARCH   => 'Search',
		AW_KW_ROLE_PRESS    => 'Press',
		AW_KW_ROLE_CATEGORY => 'Category word',
	);
}

/** What each role does, for the form and the help tab. */
function aw_sourcing_kw_role_help() {
	return array(
		AW_KW_ROLE_INCLUDE  => 'Keeps an item on its own. An English one is also a Google News search every run (unless its search is off).',
		AW_KW_ROLE_ANIMAL   => 'Keeps an item together with a Context word.',
		AW_KW_ROLE_CONTEXT  => 'Keeps an item together with an Animal word.',
		AW_KW_ROLE_EXCLUDE  => 'Drops an item, whatever else it has (always, also with the relevance filter off).',
		AW_KW_ROLE_SEARCH   => 'Its own Google News search every run (its query, or the keyword itself).',
		AW_KW_ROLE_PRESS    => 'A weekly press-discovery query (coverage of Animal Writes): Google News and Google Search.',
		AW_KW_ROLE_CATEGORY => 'Files an item into the categories it is mapped to when the keyword is in its title or summary.',
	);
}

/**
 * The records with a Sourcing or Press role (English), cached for the request and in the object cache until a change.
 *
 * @return array[]
 */
function aw_sourcing_kw_worker_rows() {
	$rows = wp_cache_get( 'worker_rows', 'aw_kw' );
	if ( ! is_array( $rows ) ) {
		$rows = aw_kw_rows( AW_KW_WORKER_ROLES, true );
		wp_cache_set( 'worker_rows', $rows, 'aw_kw', HOUR_IN_SECONDS );
	}
	return $rows;
}

/**
 * The keyword lists the site's checks use, from the table: role => every spelling.
 *
 * @return array{strong: string[], animal: string[], context: string[], exclude: string[]}
 */
function aw_sourcing_kw_lists() {
	$out = wp_cache_get( 'lists', 'aw_kw' );
	if ( is_array( $out ) ) {
		return $out;
	}
	$out = array( 'strong' => array(), 'animal' => array(), 'context' => array(), 'exclude' => array(), 'search' => array() );
	$map = array( 'strong' => AW_KW_ROLE_INCLUDE, 'animal' => AW_KW_ROLE_ANIMAL, 'context' => AW_KW_ROLE_CONTEXT, 'exclude' => AW_KW_ROLE_EXCLUDE, 'search' => AW_KW_ROLE_SEARCH );
	foreach ( aw_sourcing_kw_worker_rows() as $r ) {
		foreach ( $map as $k => $bit ) {
			if ( $r['roles'] & $bit ) {
				$out[ $k ] = array_merge( $out[ $k ], aw_kw_spellings( $r ) );
			}
		}
	}
	wp_cache_set( 'lists', $out, 'aw_kw', HOUR_IN_SECONDS );
	return $out;
}

/* ---------- Mirrors: the old lists rebuilt after a change ---------- */

add_action(
	'aw_kw_saved',
	function ( $id = 0, $changed = null ) {
		if ( array( 'dismissed' ) === $changed ) { // a suggestion dismissed or shown again: nothing the workers or the old lists read
			return;
		}
		wp_cache_delete( 'worker_rows', 'aw_kw' );
		wp_cache_delete( 'lists', 'aw_kw' );
		delete_transient( 'aw_sourcing_kw_stats_30' );
		if ( aw_sourcing_kw_on() && ! has_action( 'shutdown', 'aw_sourcing_kw_write_mirrors' ) ) {
			add_action( 'shutdown', 'aw_sourcing_kw_write_mirrors', 1 ); // once per request, after every change
		}
		if ( aw_sourcing_kw_on() && function_exists( 'aw_sources_sync_soon' ) ) {
			aw_sources_sync_soon(); // sources.yaml gets the keywords too (a spelling change leaves the old lists as they are)
		}
	},
	10,
	2
);

/**
 * Rebuild the old lists from the table and write the ones that changed (a save with no change writes nothing).
 *
 * @return string[] Options written.
 */
function aw_sourcing_kw_write_mirrors() {
	if ( ! aw_sourcing_kw_on() ) {
		return array();
	}
	wp_cache_delete( 'worker_rows', 'aw_kw' );
	$rows = aw_kw_rows( AW_KW_WORKER_ROLES );
	$m    = aw_kw_mirrors( $rows );
	$done = array();
	$old  = aw_sourcing_phase() < 4; // phase 4: the old lists are archived and no longer rebuilt (nor changed)
	$rel  = aw_relevance();
	$new  = $rel;
	foreach ( $old ? array( 'strong', 'animal', 'context', 'exclude' ) : array() as $k ) {
		$new[ $k ] = $m[ $k ];
	}
	if ( $new !== $rel ) {
		update_option( AW_REL_OPTION, $new, false );
		$done[] = AW_REL_OPTION;
	}
	$c   = (array) get_option( 'aw_sources', array() );
	$cfg = $c;
	if ( $old && ( isset( $cfg['press'] ) || $m['press'] ) ) {
		$cfg['press']['queries'] = $m['press'];
	}
	if ( $old ) {
		$cfg['categories'] = aw_kw_categories_in_order( $m['categories'], (array) ( $c['categories'] ?? array() ) );
	}
	foreach ( (array) ( $cfg['sources'] ?? array() ) as $i => $s ) { // an open-web search that is a keyword: its old copy follows it
		$id = (int) ( is_array( $s ) ? ( $s['keyword_id'] ?? 0 ) : 0 );
		if ( ! $id ) {
			continue;
		}
		$r  = $rows[ $id ] ?? aw_kw_get( $id );
		$on = $r && ( $r['roles'] & AW_KW_ROLE_SEARCH ) && $r['search_on'] && 'en' === $r['lang'];
		if ( $on && preg_match( '#^https?://#i', (string) $r['query'] ) ) {
			$cfg['sources'][ $i ]['url'] = (string) $r['query'];
		}
		$cfg['sources'][ $i ]['on'] = $on;
	}
	if ( $cfg !== $c ) {
		update_option( 'aw_sources', $cfg, false );
		$done[] = 'aw_sources';
	}
	return $done;
}

/* ---------- Saving ---------- */

/** The sections a keyword can file into: the sections' post types plus Press. */
function aw_sourcing_kw_sections() {
	$out = array();
	foreach ( array_keys( (array) ( aw_sources_config()['sections'] ?? array() ) ) as $t ) {
		$o           = get_post_type_object( $t );
		$out[ $t ] = $o ? $o->labels->name : $t;
	}
	$o                = get_post_type_object( 'aw_press' );
	$out['aw_press'] = $o ? $o->labels->name : 'Media & PR';
	return $out;
}

/**
 * Bits from posted role checkboxes.
 *
 * @param array $in Posted values (bits).
 * @return int
 */
function aw_sourcing_kw_bits( array $in ) {
	$bits = 0;
	foreach ( array_map( 'absint', $in ) as $b ) {
		if ( isset( aw_sourcing_kw_roles()[ $b ] ) ) {
			$bits |= $b;
		}
	}
	return $bits;
}

/**
 * Role names of a bit field, for the log.
 *
 * @param int $bits Roles.
 * @return string[]
 */
function aw_sourcing_kw_role_names( $bits ) {
	return array_values( array_filter( array_map( fn( $b, $l ) => ( $bits & $b ) ? $l : '', array_keys( aw_sourcing_kw_roles() ), aw_sourcing_kw_roles() ) ) );
}

/**
 * Apply an edit to a record and log every field that changed (old → new).
 *
 * @param int   $id  Record id.
 * @param array $set Any of roles (bits, the worker roles wanted), variants, categories, sections, lang, search_on, query.
 * @return bool|WP_Error Whether anything changed.
 */
function aw_sourcing_kw_apply( $id, array $set ) {
	$old = aw_kw_get( $id );
	if ( ! $old ) {
		return new WP_Error( 'aw_kw_missing', 'No such keyword.' );
	}
	$name = $old['label'];
	$log  = array();
	$fail = function ( $err ) use ( &$log ) { // what was written before the refusal is still logged
		aw_src_log_many( $log );
		return $err;
	};
	if ( isset( $set['lang'] ) && $set['lang'] !== $old['lang'] && 'en' === $set['lang'] ) { // to English first, so roles can follow
		$r = aw_kw_update( $id, array( 'lang' => 'en' ) );
		if ( is_wp_error( $r ) ) {
			return $fail( $r );
		}
		$log[] = array( 'keywords', "$name: language", $old['lang'], 'en' );
	}
	if ( isset( $set['roles'] ) ) {
		$want = (int) $set['roles'] & AW_KW_WORKER_ROLES;
		$has  = $old['roles'] & AW_KW_WORKER_ROLES;
		if ( $want !== $has ) {
			$r = aw_kw_set_roles( $id, $want & ~$has, $has & ~$want );
			if ( is_wp_error( $r ) ) {
				return $fail( $r );
			}
			$log[] = array( 'keywords', "$name: roles", aw_sourcing_kw_role_names( $has ), aw_sourcing_kw_role_names( $want ) );
		}
	}
	if ( isset( $set['lang'] ) && $set['lang'] !== $old['lang'] && 'en' !== $set['lang'] ) { // away from English: after the roles went
		$r = aw_kw_update( $id, array( 'lang' => $set['lang'] ) );
		if ( is_wp_error( $r ) ) {
			return $fail( $r );
		}
		$log[] = array( 'keywords', "$name: language", $old['lang'], $set['lang'] );
	}
	$fields = array();
	foreach ( array( 'variants', 'search_on', 'query' ) as $f ) {
		if ( array_key_exists( $f, $set ) ) {
			$fields[ $f ] = $set[ $f ];
		}
	}
	if ( isset( $fields['variants'] ) ) { // spellings the owner adds after the migration are searched too (Include); the migrated ones keep the old search list
		$new_v  = aw_kw_variants_list( $fields['variants'] );
		$src    = is_array( $old['src'] ?? null ) ? (array) $old['src'] : array();
		$added  = array_values( array_intersect( array_unique( array_merge( (array) ( $src['added'] ?? array() ), array_diff( $new_v, $old['variants'] ) ) ), $new_v ) );
		if ( $added !== array_values( (array) ( $src['added'] ?? array() ) ) ) {
			$src['added']         = $added;
			$fields['src_note'] = wp_json_encode( $src );
		}
	}
	if ( $fields && true === aw_kw_update( $id, $fields ) ) {
		if ( isset( $fields['variants'] ) && aw_kw_variants_list( $fields['variants'] ) !== $old['variants'] ) {
			$log[] = array( 'keywords', "$name: other spellings", $old['variants'], aw_kw_variants_list( $fields['variants'] ) );
		}
		if ( isset( $fields['search_on'] ) && (int) $fields['search_on'] !== (int) $old['search_on'] ) {
			$log[] = array( 'keywords', "$name: Google News search", (bool) $old['search_on'], (bool) $fields['search_on'] );
		}
		if ( isset( $fields['query'] ) && (string) $fields['query'] !== (string) $old['query'] ) {
			$log[] = array( 'keywords', "$name: search query", (string) $old['query'], (string) $fields['query'] );
		}
	}
	foreach ( array( 'categories' => 'category', 'sections' => 'section' ) as $key => $kind ) {
		if ( isset( $set[ $key ] ) && aw_kw_map_set( $id, $kind, (array) $set[ $key ] ) ) {
			$log[] = array( 'keywords', "$name: $key", $old[ $key ], array_values( (array) $set[ $key ] ) );
		}
	}
	aw_src_log_many( $log );
	return (bool) $log;
}

/**
 * Add a keyword (or add roles to the record it folds into).
 *
 * @param string $label Keyword as written.
 * @param array  $set   As aw_sourcing_kw_apply(); roles are added to the ones the record has.
 * @return int|WP_Error Record id.
 */
function aw_sourcing_kw_add( $label, array $set ) {
	$label = trim( (string) preg_replace( '/\s+/u', ' ', (string) $label ) );
	if ( '' === aw_si_norm( $label ) ) {
		return new WP_Error( 'aw_kw_empty', 'Type a keyword.' );
	}
	$was  = aw_kw_id_by_norm( aw_si_norm( $label ) );
	$lang = isset( $set['lang'] ) && in_array( $set['lang'], array( 'en', 'hi', 'hinglish' ), true ) ? $set['lang'] : null;
	unset( $set['lang'] ); // a new record's language; an existing record keeps its own (changed on its edit screen)
	$id = aw_kw_create( $label, null === $lang ? array() : array( 'lang' => $lang ) );
	if ( ! $id ) {
		return new WP_Error( 'aw_kw_failed', 'The keyword could not be saved.' );
	}
	$cur = aw_kw_get( $id );
	if ( ! $was ) {
		aw_src_log( 'keywords', 'added keyword ' . $label, '', $label );
	}
	$set['roles'] = ( (int) ( $set['roles'] ?? 0 ) | ( $cur['roles'] & AW_KW_WORKER_ROLES ) );
	foreach ( array( 'categories', 'sections' ) as $k ) {
		if ( isset( $set[ $k ] ) ) {
			$set[ $k ] = array_values( array_unique( array_merge( $cur[ $k ], (array) $set[ $k ] ) ) );
		}
	}
	if ( isset( $set['variants'] ) ) {
		$set['variants'] = array_merge( $cur['variants'], aw_kw_variants_list( $set['variants'] ) );
	}
	$r = aw_sourcing_kw_apply( $id, $set );
	return is_wp_error( $r ) ? $r : $id;
}

/** The Keywords list's views: slug => [label, role bit (0: the Hindi view)]. */
function aw_sourcing_kw_views() {
	return array(
		'include'  => array( 'Include', AW_KW_ROLE_INCLUDE ),
		'animal'   => array( 'Animal', AW_KW_ROLE_ANIMAL ),
		'context'  => array( 'Context', AW_KW_ROLE_CONTEXT ),
		'exclude'  => array( 'Exclude', AW_KW_ROLE_EXCLUDE ),
		'search'   => array( 'Search', AW_KW_ROLE_SEARCH ),
		'press'    => array( 'Press', AW_KW_ROLE_PRESS ),
		'category' => array( 'Category words', AW_KW_ROLE_CATEGORY ),
		'hindi'    => array( 'Hindi (no Sourcing role)', 0 ),
	);
}

/**
 * Whether a record belongs to a view of the Keywords list ('' for All).
 *
 * @param array  $r Record.
 * @param string $v View.
 */
function aw_sourcing_kw_in_view( array $r, $v ) {
	if ( 'hindi' === $v ) {
		return 'en' !== $r['lang'];
	}
	if ( 'en' !== $r['lang'] ) {
		return false;
	}
	$views = aw_sourcing_kw_views();
	return '' === $v ? (bool) ( $r['roles'] & AW_KW_WORKER_ROLES ) : (bool) ( $r['roles'] & ( $views[ $v ][1] ?? 0 ) );
}

/**
 * Every record the Keywords list shows in some view (the worker roles, and the Hindi words the old lists had), each with
 * its searches and the items they fetched and published in 30 days.
 *
 * @return array[] id => record, plus searches and items [fetched, published].
 */
function aw_sourcing_kw_records() {
	global $wpdb;
	$k         = aw_si_table( 'keywords' );
	$hindi_ids = (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM %i WHERE lang <> 'en' AND src_note IS NOT NULL AND src_note <> ''", $k ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the Hindi words the lists had
	$all       = aw_kw_rows( AW_KW_WORKER_ROLES );
	foreach ( aw_kw_attach_maps( $hindi_ids ? (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id, norm, label, lang, roles, variants, query, feed_id, search_on, dismissed, src_note, impr28 FROM %i WHERE id IN (' . implode( ',', array_map( 'intval', $hindi_ids ) ) . ')', $k ), ARRAY_A ) : array() ) as $id => $r ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- table name and integers only.
		$all[ $id ] = $r;
	}
	$stats = array();
	foreach ( (array) ( aw_sourcing_keyword_stats( 30 )['rows'] ?? array() ) as $feed => $st ) {
		$stats[ $feed ] = array( count( $st['news']['fetched'] ) + count( $st['legal']['fetched'] ), count( $st['news']['published'] ) + count( $st['legal']['published'] ) );
	}
	$by_id = array_column( array_filter( (array) aw_sources_config()['sources'], 'is_array' ), null, 'id' );
	foreach ( $all as $id => $r ) {
		$r['searches'] = aw_kw_searches( $r, $by_id );
		$r['items']    = array( 0, 0 );
		foreach ( $r['searches'] as $x ) {
			$r['items'][0] += $stats[ $x['feed'] ][0] ?? 0;
			$r['items'][1] += $stats[ $x['feed'] ][1] ?? 0;
		}
		$all[ $id ] = $r;
	}
	return $all;
}

/**
 * A row or bulk action of the Keywords list on some records: delete (from sourcing), add_<bit>, remove_<bit> or
 * cat_<slug> (map to a category, with the Category word role).
 *
 * @param string $op  Action.
 * @param int[]  $ids Record ids.
 * @return array{0: int, 1: string[]} How many changed, and the refusals.
 */
function aw_sourcing_kw_bulk( $op, array $ids ) {
	$n    = 0;
	$errs = array();
	foreach ( array_filter( array_map( 'absint', $ids ) ) as $one ) {
		$cur = aw_kw_get( $one );
		if ( ! $cur ) {
			continue;
		}
		$set = null;
		if ( 'delete' === $op ) {
			$set = array( 'roles' => 0 );
		} elseif ( preg_match( '/^(add|remove)_(\d+)$/', $op, $m ) && isset( aw_sourcing_kw_roles()[ (int) $m[2] ] ) ) {
			$has = $cur['roles'] & AW_KW_WORKER_ROLES;
			$set = array( 'roles' => 'add' === $m[1] ? $has | (int) $m[2] : $has & ~(int) $m[2] );
		} elseif ( preg_match( '/^cat_([a-z0-9_-]+)$/', $op, $m ) && get_term_by( 'slug', $m[1], 'category' ) ) {
			$set = array(
				'categories' => array_values( array_unique( array_merge( $cur['categories'], array( $m[1] ) ) ) ),
				'roles'      => ( $cur['roles'] & AW_KW_WORKER_ROLES ) | AW_KW_ROLE_CATEGORY,
			);
		}
		if ( null === $set ) {
			continue;
		}
		$r = aw_sourcing_kw_apply( $one, $set );
		if ( is_wp_error( $r ) ) {
			$errs[] = $cur['label'] . ': ' . $r->get_error_message();
		} elseif ( $r ) {
			++$n;
		}
	}
	return array( $n, $errs );
}

/* ---------- Help tabs, old addresses ---------- */

add_action(
	'load-' . AW_SRC_SCREEN,
	function () {
		// phpcs:disable WordPress.Security.NonceVerification -- which screen is shown, nothing saved.
		$tab = sanitize_key( $_GET['tab'] ?? '' );
		if ( ! aw_sourcing_kw_on() ) {
			return;
		}
		if ( 'categories' === $tab ) { // categories are WordPress's own; their keywords show in the Keywords column there
			wp_safe_redirect( admin_url( 'edit-tags.php?taxonomy=category' ) ); // 302: a rollback to phase 1 brings the old tab back
			exit;
		}
		if ( 'press' === $tab ) {
			wp_safe_redirect( admin_url( 'admin.php?page=aw-sourcing&tab=keywords&view=press' ) );
			exit;
		}
		if ( 'keywords' !== $tab || 'suggestions' === sanitize_key( $_GET['kw'] ?? '' ) ) {
			return;
		}
		// phpcs:enable WordPress.Security.NonceVerification
		$screen = get_current_screen();
		$roles  = '';
		foreach ( aw_sourcing_kw_roles() as $bit => $l ) {
			$roles .= '<li><strong>' . esc_html( $l ) . '</strong>: ' . esc_html( aw_sourcing_kw_role_help()[ $bit ] ) . '</li>';
		}
		$screen->add_help_tab(
			array(
				'id'      => 'aw-kw-overview',
				'title'   => 'Overview',
				'content' => '<p>An item from an <strong>allowed</strong> publisher goes live when it has an Include keyword (or an Animal keyword with a Context keyword) and no Exclude keyword; otherwise it waits in review. Blocked publishers are refused. Exclude always applies.</p>'
					. '<p>Every keyword is one record in the keyword table, which Search Insights shares (its figures show here: impressions in 28 days). Only records with a Sourcing or Press role, in English, go to the workers. Deleting a keyword here removes those roles; the record stays for Search Insights.</p>'
					. '<p>Hindi and Hinglish keywords never get a Sourcing role (no Hindi content); they show in the Hindi view.</p>',
			)
		);
		$screen->add_help_tab( array( 'id' => 'aw-kw-roles', 'title' => 'Roles', 'content' => '<ul>' . $roles . '</ul><p>Include and Exclude cannot go together on one keyword.</p>' ) );
		$screen->add_help_tab(
			array(
				'id'      => 'aw-kw-matching',
				'title'   => 'Matching',
				'content' => '<p>Keywords match whole words, with a plural allowed: "dog" matches "dogs" but not "dogma". A trailing * on a spelling matches the start of a word: "steriliz*" matches "sterilized" and "sterilization". Hindi matches anywhere in the text (it has no word boundaries).</p><p>Every spelling of a keyword counts for every role it has.</p>',
			)
		);
		$screen->add_help_tab(
			array(
				'id'      => 'aw-kw-searches',
				'title'   => 'Searches and press',
				'content' => '<p>Each English Include keyword, and each Search keyword, is a Google News search every run (India edition, the last two days, up to 40 new items). The Items column counts what each one brought in over 30 days.</p>'
					. '<p>Press keywords are the weekly press-discovery queries (Google News and Google Search), from the date set in the Press view. Results wait in Plugins → SourceWeb → To review.</p>',
			)
		);
		$screen->add_help_tab(
			array(
				'id'      => 'aw-kw-categories',
				'title'   => 'Categories',
				'content' => '<p>A Category word files an item into the categories it is mapped to when it is in the item\'s title or summary; an item a keyword\'s search found also gets that keyword\'s categories. The AI relevance check confirms the category, and each section\'s rule limits it. Categories themselves are edited in Posts → Categories, whose Keywords column opens the words of each.</p>',
			)
		);
	},
	5
);

// Posts → Categories: how many keywords file into each category, linking to them on the Keywords screen.
add_filter(
	'manage_edit-category_columns',
	function ( $cols ) {
		if ( aw_sourcing_kw_on() && current_user_can( AW_SRC_CAP ) ) {
			$cols['aw_keywords'] = 'Keywords';
		}
		return $cols;
	}
);
add_filter(
	'manage_category_custom_column',
	function ( $out, $col, $term_id ) {
		if ( 'aw_keywords' !== $col || ! aw_sourcing_kw_on() ) {
			return $out;
		}
		static $counts = null;
		if ( null === $counts ) {
			$counts = array();
			foreach ( aw_sourcing_kw_worker_rows() as $r ) {
				if ( $r['roles'] & AW_KW_ROLE_CATEGORY ) {
					foreach ( $r['categories'] as $slug ) {
						$counts[ $slug ] = ( $counts[ $slug ] ?? 0 ) + 1;
					}
				}
			}
		}
		$t = get_term( (int) $term_id, 'category' );
		$n = $t instanceof WP_Term ? (int) ( $counts[ $t->slug ] ?? 0 ) : 0;
		if ( ! $t instanceof WP_Term ) {
			return $out;
		}
		return '<a href="' . esc_url( admin_url( 'admin.php?page=aw-sourcing&tab=keywords&view=category&cat=' . rawurlencode( $t->slug ) ) ) . '">' . esc_html( number_format_i18n( $n ) ) . '</a>';
	},
	10,
	3
);

/**
 * The Press view's settings (aw_sources_save_press, sourcing-lists.php): on/off, coverage from, every n days (the
 * queries are the Press keywords).
 *
 * @param array $c  aw_sources.
 * @param array $in Posted part.
 * @return array
 */
function aw_sourcing_kw_save_press( $c, $in ) {
	$old  = (array) ( $c['press'] ?? array() );
	$new  = $old;
	$d    = sanitize_text_field( (string) ( $in['date_floor'] ?? '' ) );
	$days = absint( $in['every_days'] ?? 0 );
	if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) {
		$new['date_floor'] = $d;
	}
	if ( $days >= 1 && $days <= 60 ) {
		$new['every_days'] = $days;
	}
	$new['enabled'] = ! empty( $in['enabled'] );
	$log            = array();
	foreach ( array( 'enabled' => 'press discovery', 'date_floor' => 'press coverage from', 'every_days' => 'press every (days)' ) as $k => $l ) {
		if ( ( $old[ $k ] ?? null ) !== $new[ $k ] ) {
			$log[] = array( 'keywords', $l, $old[ $k ] ?? '', $new[ $k ] );
		}
	}
	if ( ! $log ) {
		return $c;
	}
	aw_src_log_many( $log );
	$c['press'] = $new;
	return $c;
}
