<?php
/**
 * Taxonomy rename (owner, 18 Nov 2026: "rename aw to kits"): aw_campaign_link → kits_campaign_link and
 * aw_media_group → kits_media_group. Term IDs, slugs, names, term meta, counts and every item's assignment stay as
 * they are; only the taxonomy name changes, on each term_taxonomy row and where it is stored next to it.
 *
 * The code ships first and the data moves only on an administrator's click (Tools → Taxonomy rename), after the
 * screen has shown the counts. Until then everything runs under the old names, after it under the new ones: the
 * active name comes from the marker option kits_taxonomy_rename ('1' = moved; anything else = not moved), so code and
 * data never disagree. KITS_CAMPAIGN_LINK and KITS_MEDIA_GROUP hold the active names; callers use them.
 *
 * What the rename moves (and Undo moves back, from the option kits_taxonomy_rename_backup, saved before each change):
 * - term_taxonomy rows under the old names;
 * - menu items that point at one of the taxonomies (post meta _menu_item_object);
 * - Rank Math settings keyed by the taxonomy name (rank-math-options-titles, rank-math-options-sitemap).
 * What it only lists (the screen counts them; nothing rewrites them): post content and synced patterns that name an
 * old taxonomy (saved Query Loops keep working: kits_taxonomy_current() maps "taxQuery" keys), SCF field groups saved
 * in the database, widgets, LiteSpeed and Rank Math redirection settings, and users' hidden columns and boxes.
 * Neither taxonomy has public addresses (rewrite and query_var are off), so no URL changes and no redirect is needed;
 * only the REST address of campaign links changes, and nothing calls it.
 *
 * WP-CLI (local and staging): `wp kits taxonomy-rename status|run|undo`.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Old taxonomy name => new name.
 *
 * @return array<string, string>
 */
function kits_taxonomy_renames() {
	return array(
		'aw_campaign_link' => 'kits_campaign_link',
		'aw_media_group'   => 'kits_media_group',
	);
}

/**
 * True once the data has been moved to the new names.
 *
 * @return bool
 */
function kits_taxonomy_renamed() {
	return '1' === (string) get_option( 'kits_taxonomy_rename', '' );
}

/**
 * The name a taxonomy is registered under now. Give it the old or the new name; any other name is returned as is.
 *
 * @param string $taxonomy Taxonomy name, old or new.
 * @return string
 */
function kits_taxonomy_current( $taxonomy ) {
	$taxonomy = (string) $taxonomy;
	$map      = kits_taxonomy_renames();
	$old      = array_search( $taxonomy, $map, true );
	$old      = false !== $old ? $old : ( isset( $map[ $taxonomy ] ) ? $taxonomy : '' );
	if ( '' === $old ) {
		return $taxonomy;
	}
	return kits_taxonomy_renamed() ? $map[ $old ] : $old;
}

if ( ! defined( 'KITS_CAMPAIGN_LINK' ) ) {
	define( 'KITS_CAMPAIGN_LINK', kits_taxonomy_current( 'kits_campaign_link' ) );
}
if ( ! defined( 'KITS_MEDIA_GROUP' ) ) {
	define( 'KITS_MEDIA_GROUP', kits_taxonomy_current( 'kits_media_group' ) );
}

/** Rank Math options whose keys can carry a taxonomy name. */
const KITS_TAXONOMY_RENAME_RM_OPTIONS = array( 'rank-math-options-titles', 'rank-math-options-sitemap' );

/**
 * Rename the taxonomy names inside an option's keys (tax_aw_campaign_link_robots → tax_kits_campaign_link_robots).
 *
 * @param array<string, string> $map   Name => replacement.
 * @param array                 $value Option value.
 * @return array
 */
function kits_taxonomy_rename_keys( array $map, array $value ) {
	$out = array();
	foreach ( $value as $k => $v ) {
		$out[ is_string( $k ) ? strtr( $k, $map ) : $k ] = $v;
	}
	return $out;
}

/**
 * What the rename would move and what it only lists, from the database as it is.
 *
 * @return array{move: array<string, int>, list: array<string, int>}
 */
function kits_taxonomy_rename_counts() {
	global $wpdb;
	$move = array();
	$list = array();
	foreach ( kits_taxonomy_renames() as $old => $new ) {
		$like = '%' . $wpdb->esc_like( $old ) . '%';
		$rm   = 0;
		foreach ( KITS_TAXONOMY_RENAME_RM_OPTIONS as $opt ) {
			$rm += count( preg_grep( '/' . preg_quote( $old, '/' ) . '/', array_map( 'strval', array_keys( (array) get_option( $opt, array() ) ) ) ) );
		}
		$move[ "Terms under {$old}" ]                   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $old ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$move[ "Terms under {$new}" ]                   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $new ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$move[ "Menu items for {$old}" ]                = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_menu_item_object' AND meta_value = %s", $old ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$move[ "Rank Math settings for {$old}" ]        = $rm;
		$list[ "Posts and patterns naming {$old}" ]     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_content LIKE %s AND post_type NOT IN ('revision', 'acf-field-group', 'acf-field')", $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$list[ "SCF groups and fields naming {$old}" ]  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_content LIKE %s AND post_type IN ('acf-field-group', 'acf-field')", $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$list[ "Other settings naming {$old}" ]         = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_value LIKE %s AND option_name NOT IN ('rank-math-options-titles', 'rank-math-options-sitemap', 'kits_taxonomy_rename_backup') AND option_name NOT LIKE %s", $like, $wpdb->esc_like( '_transient' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$list[ "Users' screen settings naming {$old}" ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_value LIKE %s", $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$table = $wpdb->prefix . 'rank_math_redirections';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$list[ "Rank Math redirections naming {$old}" ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE sources LIKE %s OR url_to LIKE %s", $like, $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}
	return array(
		'move' => $move,
		'list' => $list,
	);
}

/**
 * Move the data from the old names to the new ones. Every row or setting it changes is in the backup first.
 *
 * @return array<string, int> What moved.
 */
function kits_taxonomy_rename_run() {
	global $wpdb;
	$done   = array();
	$terms  = array();
	$backup = (array) get_option( 'kits_taxonomy_rename_backup', array() );
	foreach ( kits_taxonomy_renames() as $old => $new ) {
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT term_taxonomy_id, term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $old ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$meta  = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT meta_id FROM {$wpdb->postmeta} WHERE meta_key = '_menu_item_object' AND meta_value = %s", $old ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ids   = array_map( 'intval', wp_list_pluck( $rows, 'term_taxonomy_id' ) );
		$terms = array_merge( $terms, array_map( 'intval', wp_list_pluck( $rows, 'term_id' ) ) );

		$backup['terms'][ $old ]     = array_values( array_unique( array_merge( array_map( 'intval', (array) ( $backup['terms'][ $old ] ?? array() ) ), $ids ) ) );
		$backup['menus'][ $old ]     = array_values( array_unique( array_merge( array_map( 'intval', (array) ( $backup['menus'][ $old ] ?? array() ) ), $meta ) ) );
		$done[ "Terms {$old}" ]      = count( $ids );
		$done[ "Menu items {$old}" ] = count( $meta );
	}
	foreach ( KITS_TAXONOMY_RENAME_RM_OPTIONS as $opt ) {
		$value = get_option( $opt, null );
		if ( is_array( $value ) && ! isset( $backup['options'][ $opt ] ) ) {
			$backup['options'][ $opt ] = $value; // The whole value as it was, kept once.
		}
	}
	update_option( 'kits_taxonomy_rename_backup', $backup, false ); // Saved before any change; never deleted here.

	foreach ( kits_taxonomy_renames() as $old => $new ) {
		$wpdb->update( $wpdb->term_taxonomy, array( 'taxonomy' => $new ), array( 'taxonomy' => $old ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$wpdb->postmeta,
			array( 'meta_value' => $new ),
			array(
				'meta_key'   => '_menu_item_object',
				'meta_value' => $old,
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.SlowDBQuery
	}
	foreach ( KITS_TAXONOMY_RENAME_RM_OPTIONS as $opt ) {
		$value = get_option( $opt, null );
		if ( is_array( $value ) ) {
			$new_value                  = kits_taxonomy_rename_keys( kits_taxonomy_renames(), $value );
			$done[ "Rank Math {$opt}" ] = count( array_diff_key( $new_value, $value ) );
			update_option( $opt, $new_value );
		}
	}
	update_option( 'kits_taxonomy_rename', '1', true );
	kits_taxonomy_rename_flush( $terms );
	return $done;
}

/**
 * Put the backed-up rows back under the old names (rows made under the new names since are left alone) and give the
 * Rank Math keys their old names (values changed since are kept).
 *
 * @return array<string, int> What moved back.
 */
function kits_taxonomy_rename_undo() {
	global $wpdb;
	$done   = array();
	$terms  = array();
	$backup = (array) get_option( 'kits_taxonomy_rename_backup', array() );
	foreach ( kits_taxonomy_renames() as $old => $new ) {
		$done[ "Terms {$old}" ]      = 0;
		$done[ "Menu items {$old}" ] = 0;
		foreach ( array_chunk( array_values( array_filter( array_map( 'intval', (array) ( $backup['terms'][ $old ] ?? array() ) ) ) ), 500 ) as $chunk ) {
			$in                      = implode( ',', $chunk ); // Integers only (intval above).
			$terms                   = array_merge( $terms, array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s AND term_taxonomy_id IN ({$in})", $new ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$done[ "Terms {$old}" ] += (int) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->term_taxonomy} SET taxonomy = %s WHERE taxonomy = %s AND term_taxonomy_id IN ({$in})", $old, $new ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		foreach ( array_chunk( array_values( array_filter( array_map( 'intval', (array) ( $backup['menus'][ $old ] ?? array() ) ) ) ), 500 ) as $chunk ) {
			$in                           = implode( ',', $chunk ); // Integers only (intval above).
			$done[ "Menu items {$old}" ] += (int) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE meta_key = '_menu_item_object' AND meta_value = %s AND meta_id IN ({$in})", $old, $new ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}
	foreach ( KITS_TAXONOMY_RENAME_RM_OPTIONS as $opt ) {
		$now = get_option( $opt, null );
		if ( is_array( $now ) ) {
			$back                       = kits_taxonomy_rename_keys( array_flip( kits_taxonomy_renames() ), $now );
			$done[ "Rank Math {$opt}" ] = count( array_diff_key( $back, $now ) );
			update_option( $opt, $back );
		}
	}
	update_option( 'kits_taxonomy_rename', 'undone', true );
	kits_taxonomy_rename_flush( $terms );
	return $done;
}

/**
 * Drop cached terms, menus and pages so nothing is served under the other name.
 *
 * @param int[] $term_ids Term IDs that changed.
 */
function kits_taxonomy_rename_flush( array $term_ids ) {
	if ( $term_ids ) {
		clean_term_cache( array_values( array_unique( $term_ids ) ), '', false );
	}
	foreach ( kits_taxonomy_renames() as $old => $new ) {
		clean_taxonomy_cache( $old );
		clean_taxonomy_cache( $new );
	}
	wp_cache_set_terms_last_changed();
	if ( function_exists( 'wp_cache_set_posts_last_changed' ) ) {
		wp_cache_set_posts_last_changed(); // Menu items are posts.
	}
	do_action( 'litespeed_purge_all' ); // LiteSpeed Cache's own hook (as in deploy-purge.php); nothing happens without it.
}

/* ---------- Tools → Taxonomy rename (administrators only; core admin UI) ---------- */

add_action(
	'admin_menu',
	function () {
		add_management_page( 'Taxonomy rename', 'Taxonomy rename', 'manage_options', 'kits-taxonomy-rename', 'kits_taxonomy_rename_screen' );
	}
);

add_filter(
	'kits_frame_extra_screens',
	function ( $screens ) {
		$screens['kits-taxonomy-rename'] = array( 'manage_options', 'kits-tools' );
		return $screens;
	}
);

/** The screen, in the Kits frame (admin-kits-frame.php). */
function kits_taxonomy_rename_screen() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html( 'Sorry, you are not allowed to do this.' ) );
	}
	kits_frame_screen( 'kits-taxonomy-rename', 'Taxonomy rename', 'kits_taxonomy_rename_body' );
}

/** What the screen prints inside the frame: the counts first, then one button (Rename now, or Undo once renamed). */
function kits_taxonomy_rename_body(): void {
	$counts  = kits_taxonomy_rename_counts();
	$renamed = kits_taxonomy_renamed();
	$did     = isset( $_GET['kits-done'] ) ? sanitize_key( wp_unslash( $_GET['kits-done'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a notice after the redirect; nothing changes here
	if ( 'run' === $did || 'undo' === $did ) {
		printf( '<div class="notice notice-success"><p>%s</p></div>', esc_html( 'run' === $did ? 'Renamed. The counts below are after the change.' : 'Undone. The counts below are after the change.' ) );
	}
	$intro = $renamed
		? 'The data is under the new names (kits_campaign_link, kits_media_group). Undo puts the backed-up rows and settings back under the old names, and the site switches back to them at once; terms made since stay under the new names.'
		: 'The data is still under the old names (aw_campaign_link, aw_media_group), and the site uses them. Rename now moves what is counted under "Moves"; term IDs, slugs, term meta and assignments stay the same, and a backup of every changed row is saved first.';
	printf( '<p>%s</p>', esc_html( $intro ) );
	$parts = array(
		'move' => 'Moves',
		'list' => 'Listed only (not changed; saved Query Loops keep working)',
	);
	foreach ( $parts as $part => $title ) {
		printf( '<h2>%s</h2><table class="widefat striped"><tbody>', esc_html( $title ) );
		foreach ( $counts[ $part ] as $label => $n ) {
			printf( '<tr><td>%s</td><td>%s</td></tr>', esc_html( $label ), esc_html( number_format_i18n( $n ) ) );
		}
		echo '</tbody></table>';
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'kits_taxonomy_rename' );
	printf( '<input type="hidden" name="action" value="kits_taxonomy_rename"><input type="hidden" name="do" value="%s">', esc_attr( $renamed ? 'undo' : 'run' ) );
	submit_button( $renamed ? 'Undo the rename' : 'Rename now', $renamed ? 'secondary' : 'primary' );
	echo '</form>';
}

add_action(
	'admin_post_kits_taxonomy_rename',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( 'Sorry, you are not allowed to do this.' ) );
		}
		check_admin_referer( 'kits_taxonomy_rename' );
		$do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		if ( 'run' === $do && ! kits_taxonomy_renamed() ) {
			kits_taxonomy_rename_run();
		} elseif ( 'undo' === $do && kits_taxonomy_renamed() ) {
			kits_taxonomy_rename_undo();
		} else {
			$do = '';
		}
		wp_safe_redirect( add_query_arg( 'kits-done', $do, admin_url( 'tools.php?page=kits-taxonomy-rename' ) ) );
		exit;
	}
);

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'kits taxonomy-rename',
		/**
		 * Rename aw_campaign_link and aw_media_group to their kits_ names (term IDs and assignments unchanged).
		 *
		 * ## OPTIONS
		 *
		 * <action>
		 * : status (counts only, changes nothing), run or undo.
		 *
		 * @param array $args Positional arguments.
		 */
		function ( $args ) {
			$action = (string) ( $args[0] ?? 'status' );
			$res    = array();
			if ( 'run' === $action ) {
				if ( kits_taxonomy_renamed() ) {
					WP_CLI::error( 'Already renamed.' );
				}
				$res = kits_taxonomy_rename_run();
			} elseif ( 'undo' === $action ) {
				if ( ! kits_taxonomy_renamed() ) {
					WP_CLI::error( 'Not renamed.' );
				}
				$res = kits_taxonomy_rename_undo();
			} elseif ( 'status' !== $action ) {
				WP_CLI::error( 'Use status, run or undo.' );
			}
			foreach ( $res as $label => $n ) {
				WP_CLI::log( sprintf( 'Changed: %s: %d', $label, $n ) );
			}
			foreach ( kits_taxonomy_rename_counts() as $part => $rows ) {
				foreach ( $rows as $label => $n ) {
					WP_CLI::log( sprintf( '%s: %s: %d', 'move' === $part ? 'Moves' : 'Listed', $label, $n ) );
				}
			}
			WP_CLI::log( 'Marker kits_taxonomy_rename: ' . (string) get_option( 'kits_taxonomy_rename', 'not set' ) );
			WP_CLI::success( 'Done.' );
		}
	);
}
