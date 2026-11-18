<?php
/**
 * Kits Core Performance: joined stylesheets. The Kits theme's five core files become one file
 * (uploads/kits-cache/kits-{hash}.css), and the component files a page prints (the theme's kits_component_styles
 * registry) become one file per kind of page (uploads/kits-cache/parts/{set}-{hash}.css). Switched in Kits → Settings →
 * Performance (Joined stylesheet, Joined files cap). Moved from the Kits theme's functions.php on 18 Nov 2026 (code
 * unchanged); the theme keeps the registry and loads the separate files when Kits Core is not active.
 *
 * Runs only with the Kits theme (its kits_component_styles() registry). Its state (content hashes, page kinds, the
 * release sweep) is kept in the option kits_core_joined, no longer in the theme's option.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * An entry of the joined-files state (option kits_core_joined).
 *
 * @param string $key      Entry.
 * @param mixed  $fallback What to return when the entry is not set.
 * @return mixed
 */
function kits_core_joined_store( string $key, $fallback = null ) {
	$all = get_option( 'kits_core_joined', array() );
	return is_array( $all ) && array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
}

/**
 * Sets an entry of the joined-files state.
 *
 * @param string $key   Entry.
 * @param mixed  $value Value.
 */
function kits_core_joined_store_set( string $key, $value ): void {
	$all         = get_option( 'kits_core_joined', array() );
	$all         = is_array( $all ) ? $all : array();
	$all[ $key ] = $value;
	update_option( 'kits_core_joined', $all, true );
}

/**
 * Deletes an entry of the joined-files state.
 *
 * @param string $key Entry.
 */
function kits_core_joined_store_delete( string $key ): void {
	$all = get_option( 'kits_core_joined', array() );
	if ( is_array( $all ) && array_key_exists( $key, $all ) ) {
		unset( $all[ $key ] );
		update_option( 'kits_core_joined', $all, true );
	}
}

/** Whether the Kits theme's component registry is there (the joiners need it). */
function kits_core_joined_theme_ready(): bool {
	return function_exists( 'kits_component_styles' ) && function_exists( 'kits_component_style_flags' );
}


/** The Joined stylesheet switch: Theme Options (css_bundle), then the kits_bundle_css filter. */
function kits_bundle_css_switch(): bool {
	return (bool) apply_filters( 'kits_bundle_css', 'off' !== ( function_exists( 'kits_option' ) ? kits_option( 'css_bundle' ) : 'on' ) );
}

/**
 * Stored content hash of a joined file, if its parts are unchanged.
 *
 * The hash names the file, so its address changes only when the CSS does. It is kept with the parts' signature
 * (path, mtime, size), so the parts are read again only when one of them changes (after a deploy, for example).
 *
 * @param string $key Option name.
 * @param string $sig Signature of the parts.
 * @return string Hash, or '' when the parts changed or nothing is stored.
 */
function kits_joined_hash( string $key, string $sig ): string {
	$memo = kits_core_joined_store( $key );
	return is_array( $memo ) && isset( $memo['sig'], $memo['hash'] ) && $memo['sig'] === $sig ? (string) $memo['hash'] : '';
}

/**
 * Store the content hash of a joined file with its parts' signature.
 *
 * @param string $key  Option name.
 * @param string $sig  Signature of the parts.
 * @param string $hash Content hash.
 */
function kits_joined_hash_save( string $key, string $sig, string $hash ): void {
	if ( kits_joined_hash( $key, $sig ) !== $hash ) {
		kits_core_joined_store_set( $key, compact( 'sig', 'hash' ) );
	}
}

/**
 * Write a joined file: a temp file in the same folder, then a rename, so a reader never sees half a file.
 * A failed write is remembered for an hour (transient $fail: one per joiner), so pages do not retry on every view.
 *
 * @param string $path Target path.
 * @param string $css  Contents.
 * @param string $fail Transient that remembers a failed write.
 * @return bool Whether the file is in place.
 */
function kits_joined_write( string $path, string $css, string $fail = 'kits_joined_write_fail' ): bool {
	$dir = dirname( $path );
	$tmp = $path . '.' . wp_generate_password( 6, false ) . '.tmp';
	global $wp_filesystem;
	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	// The WordPress Filesystem API, direct method only: front-end pages never ask for FTP credentials.
	if ( wp_mkdir_p( $dir ) && wp_is_writable( $dir ) && 'direct' === get_filesystem_method( array(), $dir ) && WP_Filesystem( false, $dir )
		&& $wp_filesystem->put_contents( $tmp, $css, FS_CHMOD_FILE ) && $wp_filesystem->move( $tmp, $path, true ) ) {
		return true;
	}
	wp_delete_file( $tmp );
	set_transient( $fail, time(), HOUR_IN_SECONDS );
	return false;
}

/**
 * Older versions of one joined file: each is deleted once the version that replaced it has been in place for a day,
 * so cached pages that still link to it keep it that long. A file's mtime is when it was written (or last touched
 * while newest), so the next newer file's mtime is when this one was replaced.
 *
 * @param array<string,int> $files Path => mtime, newest first.
 */
function kits_joined_prune( array $files ): void {
	$paths = array_keys( $files );
	$count = count( $paths );
	for ( $i = 1; $i < $count; $i++ ) {
		if ( $files[ $paths[ $i - 1 ] ] < time() - DAY_IN_SECONDS ) {
			wp_delete_file( $paths[ $i ] );
		}
	}
}

/**
 * One stylesheet instead of five: the Kits files (kits-v42, kit-tokens, kits, kit, kit-primitives) are joined, in the
 * same order, into uploads/kits-cache/kits-{hash}.css. The hash is of the joined CSS, so the address changes only when
 * a file's contents change. The five handles stay registered (child themes depend on them) but print nothing. If the
 * bundle cannot be written, the five files load as before.
 */
function kits_bundle_core_css() {
	$styles  = wp_styles();
	$handles = array( 'kits-v42', 'kits-tokens', 'kits', 'kits-components', 'kits-primitives' );
	$dir     = get_template_directory();
	$uri     = get_template_directory_uri();
	$files   = array();
	foreach ( $handles as $h ) {
		$src = isset( $styles->registered[ $h ] ) ? (string) $styles->registered[ $h ]->src : '';
		if ( ( ! wp_style_is( $h, 'enqueued' ) && ! wp_style_is( $h, 'registered' ) ) || 0 !== strpos( $src, $uri ) || ! empty( $styles->registered[ $h ]->extra['after'] ) || ! empty( $styles->registered[ $h ]->extra['before'] ) ) {
			return; // Not ours, or carries inline CSS: leave the files as they are.
		}
		$files[ $h ] = $dir . substr( strtok( $src, '?' ), strlen( $uri ) );
	}
	$sig   = md5( implode( ',', array_map( fn( $f ) => $f . ':' . ( file_exists( $f ) ? filemtime( $f ) . ':' . filesize( $f ) : 0 ), $files ) ) );
	$up    = wp_upload_dir();
	$cache = trailingslashit( $up['basedir'] ) . 'kits-cache';
	$hash  = kits_joined_hash( 'kits_bundle_ver', $sig );
	if ( '' === $hash || ! file_exists( $cache . '/kits-' . $hash . '.css' ) ) {
		if ( get_transient( 'kits_joined_write_fail' ) ) {
			return;
		}
		$css = '';
		foreach ( $files as $h => $f ) {
			$part = file_exists( $f ) ? (string) file_get_contents( $f ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( '' === $part ) {
				return;
			}
			$css .= "/* {$h} */\n" . $part . "\n";
		}
		$hash = substr( md5( $css ), 0, 12 );
		$path = $cache . '/kits-' . $hash . '.css';
		if ( ! file_exists( $path ) && ! kits_joined_write( $path, $css ) ) {
			return;
		}
		kits_joined_hash_save( 'kits_bundle_ver', $sig, $hash );
		$list = array();
		foreach ( (array) glob( $cache . '/kits-*.css' ) as $f ) {
			$list[ $f ] = (int) filemtime( $f );
		}
		arsort( $list );
		kits_joined_prune( $list );
	}
	$first = $styles->registered[ $handles[0] ];
	wp_register_style( 'kits-bundle', trailingslashit( $up['baseurl'] ) . 'kits-cache/kits-' . $hash . '.css', $first->deps, null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- the file name carries the hash.
	foreach ( $handles as $h ) {
		$styles->registered[ $h ]->src  = false; // An alias: prints nothing, keeps its place for dependants.
		$styles->registered[ $h ]->deps = array( 'kits-bundle' );
	}
}

/**
 * Folder of the joined component stylesheets: {set}-{hash}.css. Its own folder, so the Kits clean-up never sees it.
 *
 * @param bool $create Passed to wp_upload_dir(): create the month folder.
 * @return array{path:string,url:string}
 */
function kits_parts_bundle_dir( bool $create = false ): array {
	$up = wp_upload_dir( null, $create );
	return array(
		'path' => trailingslashit( $up['basedir'] ) . 'kits-cache/parts',
		'url'  => trailingslashit( $up['baseurl'] ) . 'kits-cache/parts',
	);
}

/**
 * The joined component files on disk, by set.
 *
 * @param string $dir Folder.
 * @return array<string,array<string,int>> set => [ path => mtime ], newest first.
 */
function kits_parts_bundle_sets( string $dir ): array {
	$sets = array();
	foreach ( (array) glob( $dir . '/*.css' ) as $f ) {
		if ( preg_match( '/^([0-9a-f]{10})-[0-9a-f]{10}\.css$/', basename( (string) $f ), $m ) ) {
			$sets[ $m[1] ][ $f ] = (int) filemtime( $f );
		}
	}
	foreach ( $sets as &$files ) {
		arsort( $files );
	}
	return $sets;
}

/**
 * The joined sets the cap governs: every set except the kinds' own (a kind's set is named after md5( 'kind:' . kind ),
 * for each kind in the kits_parts_kinds option). Kind sets are always written; only sets keyed on a page's own
 * components (a kits_parts_page_kind filter that returned '') count against kits_parts_bundle_cap().
 *
 * @param array $sets kits_parts_bundle_sets().
 * @return array The other sets, keyed as given.
 */
function kits_parts_bundle_other_sets( array $sets ): array {
	$kinds = kits_core_joined_store( 'kits_parts_kinds', array() );
	foreach ( array_keys( is_array( $kinds ) ? $kinds : array() ) as $kind ) {
		unset( $sets[ substr( md5( 'kind:' . $kind ), 0, 10 ) ] );
	}
	return $sets;
}

/**
 * Clean-up of the joined component files, on each rebuild and when the cap is reached.
 *
 * - Any file no page has used for kits_parts_bundle_max_age() goes (files in use are touched hourly): an older version
 *   of a set once no cached page can still link to it, a whole set once no page makes it any more.
 * - Temp files left by a failed write go after an hour.
 *
 * @param string $dir Folder.
 */
function kits_parts_bundle_clean( string $dir ): void {
	clearstatcache();
	$old = time() - kits_parts_bundle_max_age();
	foreach ( kits_parts_bundle_sets( $dir ) as $set => $list ) {
		$left = count( $list );
		foreach ( $list as $f => $m ) {
			if ( $m < $old ) {
				wp_delete_file( $f );
				--$left;
			}
		}
		if ( ! $left ) {
			kits_core_joined_store_delete( 'kits_parts_ver_' . $set );
		}
	}
	foreach ( (array) glob( $dir . '/*.tmp' ) as $t ) {
		if ( filemtime( $t ) < time() - HOUR_IN_SECONDS ) {
			wp_delete_file( $t );
		}
	}
}

/**
 * Sweep of the joined component files after a release, for a plugin to start from its deploy step. Files no page has
 * used since now are deleted once $delay has passed, so sets no page uses any more stop counting against the cap.
 * Each page served before then touches the file it links to, so pages a cache made after the release keep theirs;
 * $delay must outlast the longest time a cache keeps a page made before the release.
 *
 * @param int $delay Seconds before the sweep runs.
 */
function kits_parts_bundle_sweep_start( int $delay ): void {
	kits_core_joined_store_delete( 'kits_parts_kinds' ); // Each kind's list starts again from what the release's pages print.
	kits_core_joined_store_set(
		'kits_parts_sweep',
		array(
			'at'  => time(),
			'run' => time() + max( 0, $delay ),
		)
	);
}

/**
 * The sweep kits_parts_bundle_sweep_start() asked for, once its time has come: files untouched since the release go,
 * with the version option of a set left with no file.
 *
 * @param string $dir Folder.
 */
function kits_parts_bundle_sweep( string $dir ): void {
	$sweep = kits_core_joined_store( 'kits_parts_sweep' );
	if ( ! is_array( $sweep ) || time() < (int) ( $sweep['run'] ?? 0 ) ) {
		return;
	}
	kits_core_joined_store_delete( 'kits_parts_sweep' );
	clearstatcache();
	foreach ( kits_parts_bundle_sets( $dir ) as $set => $list ) {
		$left = count( $list );
		foreach ( $list as $f => $m ) {
			if ( $m < (int) $sweep['at'] ) {
				wp_delete_file( $f );
				--$left;
			}
		}
		if ( ! $left ) {
			kits_core_joined_store_delete( 'kits_parts_ver_' . $set );
		}
	}
}

/**
 * How long a joined component file no page has used is kept (kits_parts_bundle_max_age filter): 7 days, plus 3 hours
 * so that a page a cache made just before the file's last use (the touch is hourly) and kept for 7 days, with a CDN
 * copy on top, never links to a removed file. Never less than that: raise it if a page cache keeps pages longer.
 */
function kits_parts_bundle_max_age(): int {
	$min = 7 * DAY_IN_SECONDS + 3 * HOUR_IN_SECONDS;
	return max( $min, (int) apply_filters( 'kits_parts_bundle_max_age', $min ) );
}

/**
 * The kind of page this is, for the joined component file: pages of one kind share one file, which holds every
 * component that kind has needed so far (kits_parts_bundle_members). A filter returning '' keys the file on this page's
 * own components instead.
 * Filter: kits_parts_page_kind (a site can split a kind, e.g. one of its own views).
 */
function kits_parts_page_kind(): string {
	$kind = '';
	if ( is_front_page() ) {
		$kind = 'front';
	} elseif ( is_home() ) {
		$kind = 'posts';
	} elseif ( is_page() ) {
		$tpl  = (string) get_page_template_slug();
		$kind = 'page' . ( '' !== $tpl && 'default' !== $tpl ? '-' . $tpl : '' );
	} elseif ( is_singular() ) {
		$kind = 'single-' . get_post_type();
	} elseif ( is_post_type_archive() ) {
		$kind = 'archive-' . (string) get_query_var( 'post_type' );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();
		$kind = 'term-' . ( $term instanceof WP_Term ? $term->taxonomy : 'any' );
	} elseif ( is_author() ) {
		$kind = 'author';
	} elseif ( is_date() ) {
		$kind = 'date';
	} elseif ( is_search() ) {
		$kind = 'search';
	} elseif ( is_404() ) {
		$kind = '404';
	} else {
		$kind = 'other'; // Feeds, embeds and routes a plugin adds: one shared file.
	}
	$kind = (string) apply_filters( 'kits_parts_page_kind', $kind );
	return (string) preg_replace( '/[^a-z0-9_-]/', '', strtolower( $kind ) );
}

/**
 * The components of this kind of page's joined file: the ones this page needs, plus every one an earlier page of the
 * kind needed (kept in the kits_parts_kinds option, in registry order). A kind's list grows until the next release
 * (kits_parts_bundle_sweep_start empties it, so a component no page prints any more drops out), so its file changes
 * only when the kind first meets a component; registry entries that are gone drop out at once. Two pages adding at
 * the same moment can lose one addition; the next page that needs it adds it again.
 *
 * @param string $kind     Page kind ('' = none).
 * @param array  $members  Handles this page needs, in registry order.
 * @param array  $registry Component registry.
 * @return array Handles, in registry order.
 */
function kits_parts_bundle_members( string $kind, array $members, array $registry ): array {
	if ( '' === $kind ) {
		return $members;
	}
	$kinds = kits_core_joined_store( 'kits_parts_kinds', array() );
	$kinds = is_array( $kinds ) ? $kinds : array();
	$known = array_values( array_filter( (array) ( $kinds[ $kind ] ?? array() ), fn( $h ) => is_string( $h ) && isset( $registry[ $h ] ) ) );
	$all   = array_values( array_filter( array_keys( $registry ), fn( $h ) => in_array( $h, $known, true ) || in_array( $h, $members, true ) ) );
	if ( $all !== $known ) {
		$kinds[ $kind ] = $all;
		kits_core_joined_store_set( 'kits_parts_kinds', $kinds );
	}
	return $all;
}

/** Most joined component files kept (Kits → Settings → Performance → Joined files cap; kits_parts_bundle_cap filter). */
function kits_parts_bundle_cap(): int {
	$cap = function_exists( 'kits_option' ) ? (int) kits_option( 'css_parts_cap' ) : 0;
	return max( 1, (int) apply_filters( 'kits_parts_bundle_cap', $cap > 0 ? $cap : 50 ) );
}

/**
 * Path on disk of a registered stylesheet address, if it is a .css file of this site's plugins, themes or wp-content.
 *
 * @param string $src Registered src.
 */
function kits_style_src_file( string $src ): string {
	$bare = fn( $u ) => (string) preg_replace( '#^(?:https?:)?//#i', '//', (string) $u );
	$src  = $bare( (string) preg_replace( '/[?#].*$/', '', $src ) );
	foreach ( array(
		WP_PLUGIN_DIR    => plugins_url(),
		get_theme_root() => get_theme_root_uri(),
		WP_CONTENT_DIR   => content_url(),
	) as $dir => $url ) {
		$url = trailingslashit( $bare( untrailingslashit( $url ) ) );
		if ( 0 !== strpos( $src, $url ) ) {
			continue;
		}
		$rel = rawurldecode( substr( $src, strlen( $url ) ) );
		if ( '.css' !== substr( $rel, -4 ) || preg_match( '#(^|/)\.\.(/|$)#', $rel ) ) {
			return '';
		}
		return trailingslashit( $dir ) . $rel;
	}
	return '';
}

/**
 * Relative url() addresses in a moved stylesheet, made root-relative against the folder it came from (no scheme or
 * host, so the joined file is the same whichever address the site answers on).
 *
 * @param string $css  CSS.
 * @param string $base URL of the file's folder.
 */
function kits_css_absolute_urls( string $css, string $base ): string {
	$base = trailingslashit( wp_make_link_relative( $base ) );
	return (string) preg_replace_callback(
		'/url\(\s*([\'"]?)([^\'")]+)\1\s*\)/i',
		function ( $m ) use ( $base ) {
			$u = trim( $m[2] );
			if ( preg_match( '#^(?:[a-z][a-z0-9+.-]*:|/|\#)#i', $u ) ) {
				return $m[0]; // Data:, http(s):, root-relative and fragment addresses stay.
			}
			return 'url(' . $m[1] . $base . $u . $m[1] . ')';
		},
		$css
	);
}

/**
 * Handles that will print: the queue (or the given handles) and everything they depend on.
 *
 * @param WP_Styles  $styles Styles.
 * @param array|null $from   Handles to start from; null for the queue.
 * @return array<string,bool>
 */
function kits_styles_needed( WP_Styles $styles, ?array $from = null ): array {
	$seen = array();
	$todo = array_map( fn( $h ) => explode( '?', (string) $h )[0], null === $from ? (array) $styles->queue : $from );
	while ( $todo ) {
		$h = array_shift( $todo );
		if ( isset( $seen[ $h ] ) || ! isset( $styles->registered[ $h ] ) ) {
			continue;
		}
		$seen[ $h ] = true;
		$todo       = array_merge( $todo, (array) $styles->registered[ $h ]->deps );
	}
	return $seen;
}

/**
 * One component stylesheet per page: the component stylesheets this page loads (kits_component_styles, in
 * registry order) are joined into uploads/kits-cache/parts/{set}-{hash}.css. {set} hashes the kind of page
 * (kits_parts_page_kind), so pages of one kind share one file holding every component that kind has needed; {hash}
 * hashes the joined CSS, so the address changes only when the CSS does. The handles stay registered as aliases that print
 * nothing, with deps = [ kits-parts ], so the joined file prints where the first of them would.
 *
 * The separate files load instead when one of them carries inline CSS, another media, an RTL copy or an @import, when
 * one of their outside deps is missing or needs one of them, when the file cannot be written, or when the cap on
 * joined files is reached for a set that has none yet.
 */
function kits_bundle_component_css() {
	$registry = kits_component_styles();
	if ( ! $registry ) {
		return;
	}
	$styles  = wp_styles();
	$needed  = kits_styles_needed( $styles );
	$members = array_values( array_filter( array_keys( $registry ), fn( $h ) => isset( $needed[ $h ] ) ) );
	if ( ! $members ) {
		return;
	}
	$kind = kits_parts_page_kind();
	foreach ( kits_parts_bundle_members( $kind, $members, $registry ) as $h ) {
		if ( ! in_array( $h, $members, true ) && kits_component_style_register( $h ) ) {
			$members[] = $h; // Another page of this kind needed it: in the file too, so the kind shares one file.
		}
	}
	$members = array_values( array_filter( array_keys( $registry ), fn( $h ) => in_array( $h, $members, true ) ) );
	$deps    = array();
	foreach ( $members as $h ) {
		foreach ( (array) $styles->registered[ $h ]->deps as $d ) {
			if ( ! in_array( $d, $members, true ) && ! in_array( $d, $deps, true ) ) {
				if ( ! isset( $styles->registered[ $d ] ) ) {
					return; // A missing dep would stop the joined file printing at all: separate files.
				}
				$deps[] = $d;
			}
		}
	}
	if ( array_intersect( $members, array_keys( kits_styles_needed( $styles, $deps ) ) ) ) {
		return; // An outside dep that needs a member would make a loop through kits-parts.
	}
	$files = array();
	$bases = array();
	foreach ( $members as $h ) {
		$o = $styles->registered[ $h ];
		if ( ! is_string( $o->src ) || '' === $o->src || ! empty( $o->extra['after'] ) || ! empty( $o->extra['before'] ) || ! empty( $o->extra['conditional'] ) || ! empty( $o->extra['alt'] ) || ( is_rtl() && ! empty( $o->extra['rtl'] ) ) || ! in_array( (string) $o->args, array( '', 'all' ), true ) ) {
			return; // Inline CSS, a condition, another media or an RTL copy: leave the files as they are.
		}
		$f = kits_style_src_file( $o->src );
		if ( '' === $f || ! is_readable( $f ) ) {
			$f = (string) $registry[ $h ]['file'];
		}
		if ( '' === $f || ! is_readable( $f ) ) {
			return;
		}
		$files[ $h ] = $f;
		$bases[ $h ] = dirname( (string) preg_replace( '/[?#].*$/', '', $o->src ) );
	}
	$set   = substr( md5( '' !== $kind ? 'kind:' . $kind : implode( ',', $members ) ), 0, 10 );
	$sig   = md5( implode( ',', array_map( fn( $f ) => $f . ':' . filemtime( $f ) . ':' . filesize( $f ), $files ) ) );
	$dir   = kits_parts_bundle_dir();
	$key   = 'kits_parts_ver_' . $set;
	$hash  = kits_joined_hash( $key, $sig );
	$path  = $dir['path'] . '/' . $set . '-' . $hash . '.css';
	$clean = false;
	if ( '' === $hash || ! file_exists( $path ) ) {
		if ( get_transient( 'kits_parts_write_fail' ) ) {
			return; // Uploads could not be written lately: separate files, no retry on every view.
		}
		$sets = kits_parts_bundle_sets( $dir['path'] );
		// A kind's set is always written (kinds are few, set by the page types); the cap holds back only sets keyed on a
		// page's own components, from a kits_parts_page_kind filter that returned ''.
		$capped = '' === $kind && ! isset( $sets[ $set ] ) && count( kits_parts_bundle_other_sets( $sets ) ) >= kits_parts_bundle_cap();
		if ( $capped && false === get_transient( 'kits_parts_cap_clean' ) ) {
			set_transient( 'kits_parts_cap_clean', 1, HOUR_IN_SECONDS ); // At most once an hour: unused sets go, then count again.
			kits_parts_bundle_clean( $dir['path'] );
			$sets = kits_parts_bundle_sets( $dir['path'] );
		}
		if ( $capped && count( kits_parts_bundle_other_sets( $sets ) ) >= kits_parts_bundle_cap() ) {
			if ( false === get_transient( 'kits_parts_cap_hit' ) ) {
				set_transient( 'kits_parts_cap_hit', time(), WEEK_IN_SECONDS ); // Shown on Home and Site check.
			}
			return; // Cap reached: this set loads its separate files, nothing new is written.
		}
		$css = '';
		foreach ( $files as $h => $f ) {
			$part = file_get_contents( $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( false === $part || preg_match( '/@import\b/i', $part ) ) {
				return; // An @import must open a stylesheet; joined, it would be dropped.
			}
			$part = (string) preg_replace( '/@charset\s+[^;]+;/i', '', $part );
			$css .= '/* ' . str_replace( '*/', '', $h ) . " */\n" . kits_css_absolute_urls( $part, $bases[ $h ] ) . "\n";
		}
		$hash = substr( md5( $css ), 0, 10 );
		$path = $dir['path'] . '/' . $set . '-' . $hash . '.css';
		if ( ! file_exists( $path ) && ! kits_joined_write( $path, $css, 'kits_parts_write_fail' ) ) {
			return;
		}
		kits_joined_hash_save( $key, $sig, $hash );
		$clean = true;
	}
	clearstatcache( true, $path );
	$mtime = file_exists( $path ) ? (int) filemtime( $path ) : 0;
	if ( ! $mtime ) {
		return; // Removed since the check above: separate files for this view.
	}
	$sweep = kits_core_joined_store( 'kits_parts_sweep' );
	$since = is_array( $sweep ) ? (int) ( $sweep['at'] ?? 0 ) : 0;
	if ( ( $mtime < time() - HOUR_IN_SECONDS || $mtime < $since ) && wp_is_writable( $path ) ) {
		touch( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- in use: the unused-age clean-up and the release sweep keep it.
	}
	if ( $since ) {
		kits_parts_bundle_sweep( $dir['path'] );
	}
	if ( $clean ) {
		kits_parts_bundle_clean( $dir['path'] );
	}
	wp_register_style( 'kits-parts', $dir['url'] . '/' . $set . '-' . $hash . '.css', $deps, null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- the file name carries the hash.
	foreach ( $members as $h ) {
		$styles->registered[ $h ]->src  = false; // An alias: prints nothing, keeps its place for dependants.
		$styles->registered[ $h ]->deps = array( 'kits-parts' );
	}
}

/**
 * The joined component files against the cap, for Kits → Home and a site's own checks. The kinds' sets are counted
 * apart: the cap, the near-the-cap warning and the cap-reached notice concern only the other sets.
 *
 * @return array{on:bool,kinds:int,count:int,cap:int,warn:bool,hit:int,fail:int,text:string,fix:string}
 */
function kits_parts_bundle_status(): array {
	$on    = kits_bundle_css_switch();
	$cap   = kits_parts_bundle_cap();
	$sets  = kits_parts_bundle_sets( kits_parts_bundle_dir()['path'] );
	$count = count( kits_parts_bundle_other_sets( $sets ) ); // What the cap governs.
	$kinds = count( $sets ) - $count;
	$hit   = (int) get_transient( 'kits_parts_cap_hit' );
	$fail  = (int) get_transient( 'kits_parts_write_fail' );
	if ( $hit && $count < $cap ) {
		delete_transient( 'kits_parts_cap_hit' ); // Room again (cap raised or files removed).
		$hit = 0;
	}
	$warn = $on && ( $fail > 0 || $hit > 0 || $count >= (int) ceil( $cap * 0.8 ) );
	$text = $on ? sprintf(
		/* translators: 1: page kinds with a joined file, 2: other joined sets, 3: the cap on other sets. */
		__( '%1$d page kinds · %2$d of %3$d other sets', 'kits-core' ),
		$kinds,
		$count,
		$cap
	) : __( 'Joined stylesheet is off: every component file loads on its own', 'kits-core' );
	$fix = '';
	if ( $warn && $fail ) {
		/* translators: %s: date and time of the failed write. */
		$fix = sprintf( __( 'uploads/kits-cache/parts/ could not be written on %s: pages load separate files. Make the folder writable; the next try is an hour after the failure.', 'kits-core' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $fail ) ) . ' ';
	}
	if ( $warn && ( $hit || $count >= (int) ceil( $cap * 0.8 ) ) ) {
		$fix .= ( $hit
			/* translators: %s: date the cap was reached. */
			? sprintf( __( 'The cap was reached on %s: a page outside the page kinds with a new set of components loads separate files.', 'kits-core' ), wp_date( get_option( 'date_format' ), $hit ) )
			: __( 'Near the cap: once it is reached, a page outside the page kinds with a new set of components loads separate files.', 'kits-core' ) ) . ' ';
		$fix .= __( 'Raise the cap in Kits → Settings → Performance (Joined files cap), or empty uploads/kits-cache/parts/ (the files are made again as pages are visited).', 'kits-core' );
	}
	return compact( 'on', 'kinds', 'count', 'cap', 'warn', 'hit', 'fail', 'text', 'fix' );
}

// Kits → Welcome: one status row, shown once a plugin registers component styles.
add_filter(
	'kits_status_rows',
	function ( $rows ) {
		if ( ! kits_core_joined_theme_ready() || ! kits_component_styles() ) {
			return $rows;
		}
		$s      = kits_parts_bundle_status();
		$rows[] = array( __( 'Joined component files', 'kits-core' ), function_exists( 'kits_status_cell' ) ? kits_status_cell( $s['text'], $s['fix'] ) : esc_html( trim( $s['text'] . ' ' . $s['fix'] ) ) );
		return $rows;
	}
);

/*
 * Both joiners run late (1000), after every plugin queued its files and the component flags became enqueues (20).
 * Turned off together in Kits → Settings → Performance (Joined stylesheet) or with add_filter( 'kits_bundle_css', '__return_false' ).
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( is_admin() || ! kits_core_joined_theme_ready() || ! kits_bundle_css_switch() ) {
			return;
		}
		kits_bundle_core_css();
		kits_bundle_component_css();
	},
	1000
);
