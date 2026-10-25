<?php
/**
 * Settings → Sourcing → Keywords on the keyword table (sourcing restructure phase 2, owner 25 Oct 2026). Active once
 * phase 2 is migrated (aw_sourcing_phase() >= 2); before that the old word lists show as they were.
 *
 * - The screen: the edit-tags layout. "Add New Keyword" on the left, the records with a Sourcing or Press role on
 *   the right (AW_Sourcing_Keywords_Table), views by role, a Press view with its settings (Settings API), Category
 *   words, Hindi (no Sourcing role), and Suggestions from Search Insights. Each keyword has its edit screen
 *   (form-table: other spellings, roles, categories with core's category checklist, sections, language, search).
 * - Every add, edit and delete goes through Core's one role setter (aw_kw_set_roles: English only, never Include with
 *   Exclude) and is logged in the Change log with old → new.
 * - The mirrors: after any keyword change the old lists (aw_relevance strong / animal / context / exclude,
 *   aw_sources.press.queries and .categories, and the old copy of each search that was a feed) are rebuilt from the
 *   table, once per request, so old readers and old workers see the same words until phase 4. Writing them starts
 *   Sources sync, as any list save does.
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
	$rel  = aw_relevance();
	$new  = $rel;
	foreach ( array( 'strong', 'animal', 'context', 'exclude' ) as $k ) {
		$new[ $k ] = $m[ $k ];
	}
	if ( $new !== $rel ) {
		update_option( AW_REL_OPTION, $new, false );
		$done[] = AW_REL_OPTION;
	}
	$c   = (array) get_option( 'aw_sources', array() );
	$cfg = $c;
	if ( isset( $cfg['press'] ) || $m['press'] ) {
		$cfg['press']['queries'] = $m['press'];
	}
	$cfg['categories'] = aw_kw_categories_in_order( $m['categories'], (array) ( $c['categories'] ?? array() ) );
	foreach ( (array) ( $cfg['sources'] ?? array() ) as $i => $s ) { // an open-web search that is a keyword: its old copy follows it
		$id = (int) ( is_array( $s ) ? ( $s['keyword_id'] ?? 0 ) : 0 );
		if ( ! $id ) {
			continue;
		}
		$r  = $rows[ $id ] ?? aw_kw_get( $id );
		$on = $r && ( $r['roles'] & AW_KW_ROLE_SEARCH ) && $r['search_on'] && 'en' === $r['lang'];
		if ( $on && preg_match( '#^https?://#', (string) $r['query'] ) ) {
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

/**
 * Category slugs from core's category checklist (term ids).
 *
 * @param array $ids Term ids.
 * @return string[]
 */
function aw_sourcing_kw_cat_slugs( array $ids ) {
	$out = array();
	foreach ( array_map( 'absint', $ids ) as $id ) {
		$t = $id ? get_term( $id, 'category' ) : null;
		if ( $t instanceof WP_Term ) {
			$out[] = $t->slug;
		}
	}
	return $out;
}

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
		kits_sourcing_log_many( $log );
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
	kits_sourcing_log_many( $log );
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
		kits_sourcing_log( 'keywords', 'added keyword ' . $label, '', $label );
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

/** The keyword screen's forms: add, edit, delete, row and bulk actions. */
add_action(
	'admin_post_aw_sourcing_kw',
	function () {
		$op = sanitize_key( $_POST['kw_op'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification -- picks which nonce is checked next
		$id = absint( $_POST['kw_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$nonce = 'add' === $op ? 'aw_sourcing_kw_add' : ( 'edit' === $op ? 'aw_sourcing_kw_edit_' . $id : 'bulk-keywords' );
		if ( ! current_user_can( AW_SRC_CAP ) || ! check_admin_referer( $nonce ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$base = admin_url( 'options-general.php?page=aw-sourcing&tab=keywords' );
		$back = wp_get_referer() ? remove_query_arg( array( 'kw_done', 'kw_n', 'kw_err' ), wp_get_referer() ) : $base;
		if ( ! aw_sourcing_kw_on() ) {
			wp_safe_redirect( $base );
			exit;
		}
		$err = '';
		$n   = 0;
		if ( 'add' === $op || 'edit' === $op ) {
			$set = array(
				'roles'      => aw_sourcing_kw_bits( array_map( 'absint', (array) wp_unslash( $_POST['kw_roles'] ?? array() ) ) ),
				'variants'   => sanitize_textarea_field( wp_unslash( $_POST['kw_variants'] ?? '' ) ),
				'categories' => aw_sourcing_kw_cat_slugs( array_map( 'absint', (array) wp_unslash( $_POST['post_category'] ?? array() ) ) ),
				'sections'   => array_values( array_intersect( array_map( 'sanitize_key', (array) wp_unslash( $_POST['kw_sections'] ?? array() ) ), array_keys( aw_sourcing_kw_sections() ) ) ),
			);
			if ( 'edit' === $op ) {
				$set['search_on'] = empty( $_POST['kw_search_on'] ) ? 0 : 1; // the add form has no switch: a new record's search is on, an existing one's stays
			}
			if ( 'edit' === $op ) {
				$lang         = sanitize_key( wp_unslash( $_POST['kw_lang'] ?? 'en' ) );
				$set['lang']  = in_array( $lang, array( 'en', 'hi', 'hinglish' ), true ) ? $lang : 'en';
				$q            = trim( (string) wp_unslash( $_POST['kw_query'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized next, as an address or as words
				$set['query'] = preg_match( '#^https?://#i', $q ) ? esc_url_raw( $q ) : sanitize_text_field( $q ); // an address keeps its %XX
				if ( ! empty( $_POST['kw_delete'] ) ) { // the edit screen's Delete: the Sourcing and Press roles go; the record stays for Search Insights
					$set = array( 'roles' => 0 );
				}
				$r = aw_sourcing_kw_apply( $id, $set );
				if ( is_wp_error( $r ) ) {
					wp_safe_redirect( add_query_arg( 'kw_err', rawurlencode( $r->get_error_message() ), $back ) );
					exit;
				}
				wp_safe_redirect( add_query_arg( array( 'kw_done' => ! $r ? 'none' : ( empty( $_POST['kw_delete'] ) ? 'saved' : 'deleted' ), 'kw_n' => (int) $r ), $base ) ); // a Save with no change writes nothing
				exit;
			}
			if ( ! $set['roles'] ) {
				$err = 'Choose at least one role.';
			} else {
				$set['lang'] = sanitize_key( wp_unslash( $_POST['kw_lang'] ?? 'en' ) );
				$label       = sanitize_text_field( wp_unslash( $_POST['kw_label'] ?? '' ) );
				$r           = aw_sourcing_kw_add( $label, $set );
				$err         = is_wp_error( $r ) ? $r->get_error_message() : '';
				$n           = $err ? 0 : 1;
				$there       = $err ? aw_kw_id_by_norm( aw_si_norm( $label ) ) : 0;
				if ( $there ) { // refused on a record that exists (its language, say): open it, where it can be corrected
					wp_safe_redirect( add_query_arg( array( 'edit' => $there, 'kw_err' => rawurlencode( $err ) ), $base ) );
					exit;
				}
			}
			wp_safe_redirect( add_query_arg( $err ? array( 'kw_err' => rawurlencode( $err ) ) : array( 'kw_done' => 'added', 'kw_n' => $n ), $back ) );
			exit;
		}
		// Row and bulk actions of the list (the table's own nonce): kw_row = "op:id", or kw_bulk with ids[].
		$row = sanitize_text_field( wp_unslash( $_POST['kw_row'] ?? '' ) );
		if ( false !== strpos( $row, ':' ) ) {
			list( $op, $one ) = explode( ':', $row, 2 );
			$ids              = array( absint( $one ) );
		} else {
			$op  = sanitize_text_field( wp_unslash( $_POST['kw_bulk'] ?? '' ) );
			$op  = '' !== $op ? $op : sanitize_text_field( wp_unslash( $_POST['kw_bulk2'] ?? '' ) );
			$ids = array_map( 'absint', (array) wp_unslash( $_POST['kw'] ?? array() ) );
		}
		$errs = array();
		foreach ( array_filter( $ids ) as $one ) {
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
			$r      = aw_sourcing_kw_apply( $one, $set );
			if ( is_wp_error( $r ) ) {
				$errs[] = $cur['label'] . ': ' . $r->get_error_message();
			} elseif ( $r ) {
				++$n;
			}
		}
		$args = array( 'kw_done' => $n ? 'changed' : 'none', 'kw_n' => $n );
		if ( $errs ) {
			$args['kw_err'] = rawurlencode( implode( ' ', $errs ) );
		}
		wp_safe_redirect( add_query_arg( $args, $back ) );
		exit;
	}
);
add_filter( 'removable_query_args', fn( $args ) => array_merge( $args, array( 'kw_err' ) ) );

/* ---------- The screen ---------- */

/** The Keywords tab on the table: a keyword's edit screen, or the list with the add form. */
function aw_sourcing_kw_tab() {
	$base = admin_url( 'options-general.php?page=aw-sourcing&tab=keywords' );
	$edit = absint( $_GET['edit'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
	$err  = sanitize_text_field( wp_unslash( $_GET['kw_err'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( '' !== $err ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $err ) . '</p></div>';
	}
	$done = sanitize_key( $_GET['kw_done'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	$msg  = array(
		'added'   => 'Keyword saved. The workers use it from their next run.',
		'saved'   => 'Keyword updated. The workers use it from their next run.',
		'deleted' => 'Keyword deleted from sourcing (its Sourcing and Press roles were removed; Search Insights keeps the record).',
		'changed' => 'Keywords changed. The workers use them from their next run.',
		'none'    => 'Nothing changed.',
	);
	if ( isset( $msg[ $done ] ) ) {
		echo '<div class="notice notice-' . ( 'none' === $done ? 'warning' : 'success' ) . ' is-dismissible"><p>' . esc_html( $msg[ $done ] ) . '</p></div>';
	}
	if ( $edit ) {
		aw_sourcing_kw_edit_form( $edit );
		return;
	}
	if ( ! class_exists( 'WP_List_Table' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	}
	require_once __DIR__ . '/class-aw-sourcing-keywords-table.php';
	$table = new AW_Sourcing_Keywords_Table();
	$table->prepare_items();
	$view = $table->view();

	aw_sourcing_settings_form( 'aw_sourcing_switches', 'aw-sourcing-switches' );
	if ( 'press' === $view ) {
		aw_sourcing_settings_form( 'aw_sourcing_sources', 'aw-sourcing-press' ); // Press: enabled, coverage from, every n days
	}

	echo '<form class="search-form wp-clearfix" method="get"><input type="hidden" name="page" value="aw-sourcing"><input type="hidden" name="tab" value="keywords">' . ( '' !== $view ? '<input type="hidden" name="view" value="' . esc_attr( $view ) . '">' : '' );
	$table->search_box( 'Search keywords', 'aw-kw' );
	echo '</form>';

	echo '<div id="col-container" class="wp-clearfix"><div id="col-left"><div class="col-wrap"><div class="form-wrap">';
	echo '<h2>Add New Keyword</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_sourcing_kw"><input type="hidden" name="kw_op" value="add">';
	wp_nonce_field( 'aw_sourcing_kw_add' );
	echo '<div class="form-field form-required"><label for="aw-kw-label">Keyword</label><input type="text" id="aw-kw-label" name="kw_label" required aria-required="true" aria-describedby="aw-kw-label-description"><p id="aw-kw-label-description">English only for Sourcing and Press. Plurals and dots do not matter: "dogs" adds to "dog".</p></div>';
	echo '<div class="form-field"><label for="aw-kw-variants">Other spellings</label><textarea id="aw-kw-variants" name="kw_variants" rows="3" aria-describedby="aw-kw-variants-description"></textarea><p id="aw-kw-variants-description">One per line. A trailing * matches the start of a word (steriliz* matches sterilized and sterilization).</p></div>';
	echo '<div class="form-field"><label for="aw-kw-lang">Language</label><select id="aw-kw-lang" name="kw_lang"><option value="en">English</option><option value="hinglish">Hinglish</option><option value="hi">Hindi</option></select><p>Sourcing and Press roles are for English keywords only.</p></div>';
	echo '<fieldset class="form-field"><legend>Roles</legend>';
	$pre = array( 'include' => AW_KW_ROLE_INCLUDE, 'animal' => AW_KW_ROLE_ANIMAL, 'context' => AW_KW_ROLE_CONTEXT, 'exclude' => AW_KW_ROLE_EXCLUDE, 'search' => AW_KW_ROLE_SEARCH, 'press' => AW_KW_ROLE_PRESS, 'category' => AW_KW_ROLE_CATEGORY )[ $view ] ?? 0;
	foreach ( aw_sourcing_kw_roles() as $bit => $l ) {
		echo '<label><input type="checkbox" name="kw_roles[]" value="' . (int) $bit . '"' . checked( $pre, $bit, false ) . '> ' . esc_html( $l ) . '</label><br>';
	}
	echo '<p>Include and Exclude cannot go together.</p></fieldset>';
	echo '<fieldset class="form-field"><legend>Categories (for Category words)</legend><ul class="categorychecklist">';
	$cat = sanitize_title( wp_unslash( $_GET['cat'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$t   = '' !== $cat ? get_term_by( 'slug', $cat, 'category' ) : null;
	wp_terms_checklist( 0, array( 'taxonomy' => 'category', 'selected_cats' => $t ? array( (int) $t->term_id ) : array(), 'checked_ontop' => false ) );
	echo '</ul></fieldset>';
	submit_button( 'Add New Keyword', 'primary' );
	echo '</form></div></div></div>';

	echo '<div id="col-right"><div class="col-wrap"><h2 class="screen-reader-text">Keywords</h2>';
	$table->views();
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_sourcing_kw">';
	$table->display();
	echo '</form></div></div></div>';

	$try = sanitize_text_field( wp_unslash( $_GET['try'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	echo '<h2>Test a headline</h2><form method="get"><input type="hidden" name="page" value="aw-sourcing"><input type="hidden" name="tab" value="keywords"><input type="text" name="try" class="large-text" value="' . esc_attr( $try ) . '" placeholder="Paste a headline" aria-label="Headline">';
	submit_button( 'Test', 'secondary', '', false );
	if ( $try ) {
		$v = aw_relevance_verdict( $try );
		echo '<p><strong>' . ( $v[0] ? 'Kept' : 'Dropped' ) . '</strong> (' . esc_html( $v[1] ) . ')</p>';
	}
	echo '</form>';
}

/**
 * One keyword's edit screen (form-table).
 *
 * @param int $id Record id.
 */
function aw_sourcing_kw_edit_form( $id ) {
	$back = admin_url( 'options-general.php?page=aw-sourcing&tab=keywords' );
	$r    = aw_kw_get( $id );
	if ( ! $r ) {
		echo '<div class="notice notice-error inline"><p>No such keyword. <a href="' . esc_url( $back ) . '">Back to Keywords</a></p></div>';
		return;
	}
	echo '<h2>' . esc_html( 'Edit keyword: ' . $r['label'] ) . '</h2>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_sourcing_kw"><input type="hidden" name="kw_op" value="edit"><input type="hidden" name="kw_id" value="' . (int) $id . '">';
	wp_nonce_field( 'aw_sourcing_kw_edit_' . $id );
	echo '<table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row">Keyword</th><td><strong>' . esc_html( $r['label'] ) . '</strong> <code>' . esc_html( $r['norm'] ) . '</code><p class="description">Fixed once saved: it is the record Search Insights keeps its figures on. Add other spellings below.</p></td></tr>';
	echo '<tr><th scope="row"><label for="aw-kw-variants">Other spellings</label></th><td><textarea id="aw-kw-variants" name="kw_variants" rows="5" class="large-text code" aria-describedby="aw-kw-variants-d">' . esc_textarea( implode( "\n", $r['variants'] ) ) . '</textarea><p class="description" id="aw-kw-variants-d">One per line. Every spelling counts for every role. A trailing * matches the start of a word.</p></td></tr>';
	echo '<tr><th scope="row">Roles</th><td><fieldset><legend class="screen-reader-text">Roles</legend>';
	foreach ( aw_sourcing_kw_roles() as $bit => $l ) {
		echo '<label><input type="checkbox" name="kw_roles[]" value="' . (int) $bit . '"' . checked( (bool) ( $r['roles'] & $bit ), true, false ) . '> <strong>' . esc_html( $l ) . '</strong> — ' . esc_html( aw_sourcing_kw_role_help()[ $bit ] ) . '</label><br>';
	}
	$other = array_values( array_filter( array_map( fn( $b, $l ) => ( $r['roles'] & $b ) && ! isset( aw_sourcing_kw_roles()[ $b ] ) ? $l : '', array_keys( aw_si_roles() ), aw_si_roles() ) ) );
	echo '<p class="description">Search Insights roles (not changed here): ' . esc_html( $other ? implode( ', ', $other ) : 'none' ) . '.</p></fieldset></td></tr>';
	echo '<tr><th scope="row">Categories</th><td><fieldset><legend class="screen-reader-text">Categories</legend><ul class="categorychecklist">';
	$sel = array();
	foreach ( $r['categories'] as $slug ) {
		$t = get_term_by( 'slug', $slug, 'category' );
		if ( $t ) {
			$sel[] = (int) $t->term_id;
		}
	}
	wp_terms_checklist( 0, array( 'taxonomy' => 'category', 'selected_cats' => $sel, 'checked_ontop' => false ) );
	echo '</ul><p class="description">With the Category word role, an item that has this keyword gets these categories (the section\'s rule still limits them). An item a search of this keyword found gets them too.</p></fieldset></td></tr>';
	echo '<tr><th scope="row">Sections</th><td><fieldset><legend class="screen-reader-text">Sections</legend>';
	foreach ( aw_sourcing_kw_sections() as $t => $l ) {
		echo '<label><input type="checkbox" name="kw_sections[]" value="' . esc_attr( $t ) . '"' . checked( in_array( $t, $r['sections'], true ), true, false ) . '> ' . esc_html( $l ) . '</label><br>';
	}
	echo '<p class="description">Where items its search finds go (News for a keyword search; Media &amp; PR files them as press).</p></fieldset></td></tr>';
	echo '<tr><th scope="row"><label for="aw-kw-lang">Language</label></th><td><select id="aw-kw-lang" name="kw_lang">';
	foreach ( array( 'en' => 'English', 'hi' => 'Hindi', 'hinglish' => 'Hinglish' ) as $k => $l ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $r['lang'], $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	echo '</select><p class="description">Detected automatically; correct it when an English phrase was taken for Hinglish. Sourcing and Press roles are for English keywords only.</p></td></tr>';
	echo '<tr><th scope="row">Google News search</th><td><label><input type="checkbox" name="kw_search_on" value="1"' . checked( (bool) $r['search_on'], true, false ) . '> On</label><p class="description">An English Include or Search keyword is searched every run. Searches: ';
	$s = aw_kw_searches( $r, array_column( array_filter( (array) aw_sources_config()['sources'], 'is_array' ), null, 'id' ) );
	echo $s ? esc_html( implode( ', ', array_map( fn( $x ) => $x['feed'], $s ) ) ) : 'none';
	echo '.</p></td></tr>';
	echo '<tr><th scope="row"><label for="aw-kw-query">Search query</label></th><td><input type="text" id="aw-kw-query" name="kw_query" class="large-text code" value="' . esc_attr( $r['query'] ) . '"><p class="description">For the Search role: the words searched (the keyword itself when empty), or a whole Google News search address. Feed id: <code>' . esc_html( '' !== $r['feed_id'] ? $r['feed_id'] : '(made from the query)' ) . '</code> (fixed, so the items it found stay counted).</p></td></tr>';
	echo '</tbody></table>';
	submit_button( 'Update', 'primary', 'submit', false );
	echo ' ';
	submit_button( 'Delete', 'delete', 'kw_delete', false, array( 'onclick' => "return confirm('Remove this keyword from sourcing? Its Sourcing and Press roles go; Search Insights keeps the record.');" ) );
	echo ' <a href="' . esc_url( $back ) . '" class="button">Cancel</a></form>';
}

/* ---------- Screen options, help tabs, old addresses ---------- */

add_action(
	'load-settings_page_aw-sourcing',
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
			wp_safe_redirect( admin_url( 'options-general.php?page=aw-sourcing&tab=keywords&view=press' ) );
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
					. '<p>Press keywords are the weekly press-discovery queries (Google News and Google Search), from the date set in the Press view. Results wait in Inbox → Press to review.</p>',
			)
		);
		$screen->add_help_tab(
			array(
				'id'      => 'aw-kw-categories',
				'title'   => 'Categories',
				'content' => '<p>A Category word files an item into the categories it is mapped to when it is in the item\'s title or summary; an item a keyword\'s search found also gets that keyword\'s categories. The AI relevance check confirms the category, and each section\'s rule limits it. Categories themselves are edited in Posts → Categories, whose Keywords column opens the words of each.</p>',
			)
		);
		add_screen_option( 'per_page', array( 'label' => 'Keywords per page', 'default' => 100, 'option' => 'aw_keywords_per_page' ) );
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
		return '<a href="' . esc_url( admin_url( 'options-general.php?page=aw-sourcing&tab=keywords&view=category&cat=' . rawurlencode( $t->slug ) ) ) . '">' . esc_html( number_format_i18n( $n ) ) . '</a>';
	},
	10,
	3
);

/* ---------- The Press view's settings (Settings API, option aw_sources) ---------- */

add_action(
	'admin_init',
	function () {
		if ( ! aw_sourcing_kw_on() ) {
			return;
		}
		add_settings_field(
			'aw_press_enabled',
			'Press discovery',
			function () {
				echo '<label><input type="checkbox" id="aw-pe" name="aw_sources[enabled]" value="1"' . checked( ! empty( aw_sources_config()['press']['enabled'] ), true, false ) . '> On</label>';
			},
			'aw-sourcing-press',
			'aw_press',
			array( 'label_for' => 'aw-pe' )
		);
		add_settings_field(
			'aw_press_every',
			'Every',
			function () {
				echo '<input type="number" min="1" max="60" step="1" class="small-text" id="aw-pd" name="aw_sources[every_days]" value="' . (int) ( aw_sources_config()['press']['every_days'] ?? 7 ) . '"> days';
			},
			'aw-sourcing-press',
			'aw_press',
			array( 'label_for' => 'aw-pd' )
		);
	},
	20
);

/**
 * The Press view's settings: on/off, coverage from, every n days (the queries are the Press keywords).
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
	kits_sourcing_log_many( $log );
	$c['press'] = $new;
	return $c;
}
