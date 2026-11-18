<?php
/**
 * The site layer: a site's own templates, template parts, patterns, theme.json additions and files, kept in the
 * theme's assets/site/ folder and served over the theme's own, ranked the way WordPress ranks a child theme's files.
 * A site runs Kits by Wugweb with its own look and no child theme (as an Uncode site runs Uncode with Uncode Core).
 *
 * The distributed theme has no assets/site/ folder (tools/build-dist.py leaves it out), so nothing here runs there:
 * every hook asks kits_site_on() first. A site that has the folder may add assets/site/site.php, loaded first (before
 * the theme's own hooks, where a child theme's functions.php loads), for its styles, scripts and head tags.
 *
 * - Templates and parts (assets/site/templates, assets/site/parts): the get_block_templates and
 *   get_block_file_template filters return each file built by core (_build_block_template_result_from_file: source
 *   "theme", the active theme's id, block hooks applied). A file here takes the place of the theme's file of the same
 *   slug, as a child theme's file does; a template saved in the Site Editor (database) still wins over both, and
 *   "Clear customizations" goes back to the file here.
 * - theme.json (assets/site/theme.json: custom templates, template part areas, settings, styles): merged over the
 *   theme's, as core merges a child theme's theme.json over its parent's.
 * - Patterns (assets/site/patterns): registered on init from the file headers, as core does for a theme's folder.
 * - Design system (assets/site/kits-registry.json, assets/site/css/tokens.css): read by Kits → Help → Design system.
 *
 * @package kits-by-wugweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The site folder, with a trailing slash. */
function kits_site_dir(): string {
	return trailingslashit( get_template_directory() ) . 'assets/site/';
}

/**
 * Whether the site layer runs: the theme has an assets/site/ folder and no child theme is active (a child theme serves
 * its own files). Filter kits_site_on to force it either way.
 */
function kits_site_on(): bool {
	static $dir = null;
	if ( null === $dir ) {
		$dir = is_dir( kits_site_dir() );
	}
	return (bool) apply_filters( 'kits_site_on', $dir && get_stylesheet() === get_template() );
}

/**
 * Path of a file in the site folder.
 *
 * @param string $file Path relative to assets/site/, e.g. 'css/tokens.css'.
 */
function kits_site_path( string $file ): string {
	return kits_site_dir() . ltrim( $file, '/' );
}

/**
 * Address of a file in the site folder.
 *
 * @param string $file Path relative to assets/site/, e.g. 'img/google-maps.png'.
 */
function kits_site_uri( string $file ): string {
	return trailingslashit( get_template_directory_uri() ) . 'assets/site/' . ltrim( $file, '/' );
}

/**
 * The served copy of a site CSS file: its .min.css (tools/ci/css_min.py) when that exists.
 *
 * @param string $file Path relative to assets/site/.
 */
function kits_site_css_served( string $file ): string {
	$min = (string) preg_replace( '/\.css$/', '.min.css', $file );
	return is_readable( kits_site_path( $min ) ) ? $min : $file;
}

/**
 * The site's theme.json, merged over the theme's data the way core merges a child theme's theme.json over its
 * parent's (WP_Theme_JSON_Resolver::get_theme_data()): settings, styles, custom templates and template parts.
 *
 * @param WP_Theme_JSON_Data $theme_json The theme's theme.json data.
 */
function kits_site_theme_json( $theme_json ) {
	if ( ! kits_site_on() || ! is_object( $theme_json ) || ! method_exists( $theme_json, 'update_with' ) ) {
		return $theme_json;
	}
	$file = kits_site_path( 'theme.json' );
	$data = is_readable( $file ) ? wp_json_file_decode( $file, array( 'associative' => true ) ) : null;
	if ( ! is_array( $data ) ) {
		return $theme_json;
	}
	unset( $data['$schema'] );
	return $theme_json->update_with( $data );
}
add_filter( 'wp_theme_json_data_theme', 'kits_site_theme_json' );

/**
 * Folder of the site's template or template part files.
 *
 * @param string $template_type 'wp_template' or 'wp_template_part'.
 */
function kits_site_template_dir( string $template_type ): string {
	return kits_site_path( 'wp_template_part' === $template_type ? 'parts' : 'templates' );
}

/**
 * The .html files in a folder and its subfolders, found as core's _get_block_templates_paths() finds a theme's
 * (sorted, so the result does not depend on the file system's order).
 *
 * @param string $base_directory Folder.
 * @return string[] Full paths.
 */
function kits_site_template_paths( string $base_directory ): array {
	$paths = array();
	if ( is_dir( $base_directory ) ) {
		$nested = new RegexIterator(
			new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base_directory ) ),
			'/^.+\.html$/i',
			RecursiveRegexIterator::GET_MATCH
		);
		foreach ( $nested as $path => $match ) {
			$paths[] = (string) $path;
		}
	}
	sort( $paths );
	return $paths;
}

/**
 * The site's template or part files that match a query, in the form core's _get_block_templates_files() returns
 * (slug, path, theme, type, and title, postTypes or area from theme.json).
 *
 * @param string $template_type 'wp_template' or 'wp_template_part'.
 * @param array  $query         slug__in, slug__not_in, area (parts only). post_type is applied by the caller.
 * @return array[] Keyed by slug.
 */
function kits_site_template_files( string $template_type, array $query = array() ): array {
	if ( 'wp_template' !== $template_type && 'wp_template_part' !== $template_type ) {
		return array();
	}
	static $paths = array();
	if ( ! isset( $paths[ $template_type ] ) ) {
		$paths[ $template_type ] = kits_site_template_paths( kits_site_template_dir( $template_type ) );
	}
	$base    = kits_site_template_dir( $template_type ) . DIRECTORY_SEPARATOR;
	$include = (array) ( $query['slug__in'] ?? array() );
	$skip    = (array) ( $query['slug__not_in'] ?? array() );
	$area    = $query['area'] ?? null;
	$out     = array();
	foreach ( $paths[ $template_type ] as $path ) {
		// As core: the path below the folder, without ".html" (a file in a subfolder keeps the subfolder in its slug).
		$slug = substr( $path, strlen( $base ), -5 );
		if ( ( $include && ! in_array( $slug, $include, true ) ) || ( $skip && in_array( $slug, $skip, true ) ) ) {
			continue;
		}
		$item = array(
			'slug'  => $slug,
			'path'  => $path,
			'theme' => get_stylesheet(),
			'type'  => $template_type,
		);
		if ( 'wp_template_part' === $template_type ) {
			$item = _add_block_template_part_area_info( $item );
			if ( isset( $area ) && $area !== $item['area'] ) {
				continue;
			}
		} else {
			$item = _add_block_template_info( $item );
		}
		$out[ $slug ] = $item;
	}
	return $out;
}

/**
 * Templates and parts lists (Site Editor, the template hierarchy, Page Options): the site's files take the place
 * of Kits' files of the same slug and are added where Kits has none, with core's post type rules
 * (get_block_templates()). Saved templates (source "custom") are kept as they are, marked as having a theme file.
 *
 * @param WP_Block_Template[] $templates     Found templates.
 * @param array               $query         The query; slug__not_in already holds the saved templates' slugs.
 * @param string              $template_type 'wp_template' or 'wp_template_part'.
 */
function kits_site_get_block_templates( $templates, $query, $template_type ) {
	if ( ! kits_site_on() || ! is_array( $templates ) ) {
		return $templates;
	}
	$query     = (array) $query;
	$templates = array_map( 'kits_site_mark_saved', $templates );
	if ( isset( $query['wp_id'] ) ) {
		return $templates;
	}
	$files_query = $query;
	unset( $files_query['post_type'] );
	$files = kits_site_template_files( (string) $template_type, $files_query );
	if ( ! $files ) {
		return $templates;
	}
	// Kits' own file (and a plugin-registered template) of the same slug gives way, as to a child theme's file.
	$templates = array_values(
		array_filter(
			$templates,
			fn( $t ) => ! ( $t instanceof WP_Block_Template && in_array( $t->source, array( 'theme', 'plugin' ), true ) && isset( $files[ $t->slug ] ) )
		)
	);
	$saved     = wp_list_pluck( $templates, 'slug' );
	$defaults  = get_default_block_template_types();
	$post_type = $query['post_type'] ?? null;
	foreach ( $files as $slug => $file ) {
		if ( in_array( $slug, $saved, true ) ) {
			continue;
		}
		if ( ! isset( $post_type ) || ( isset( $file['postTypes'] ) && in_array( $post_type, $file['postTypes'], true ) ) ) {
			$templates[] = _build_block_template_result_from_file( $file, $template_type );
		} elseif ( ! isset( $file['postTypes'] ) && ! isset( $defaults[ $slug ] ) ) {
			// Custom templates with no post types are for every post type (not the default templates).
			$templates[] = _build_block_template_result_from_file( $file, $template_type );
		}
	}
	return $templates;
}
add_filter( 'get_block_templates', 'kits_site_get_block_templates', 1, 3 );

/**
 * One template or part file by id (the template part block, the Site Editor's reset): the site's file when the id
 * is the active theme's and the file is here.
 *
 * @param WP_Block_Template|null $block_template What core found (Kits' file, a registered template or nothing).
 * @param string                 $id            Template id, theme//slug.
 * @param string                 $template_type 'wp_template' or 'wp_template_part'.
 */
function kits_site_get_block_file_template( $block_template, $id, $template_type ) {
	if ( ! kits_site_on() ) {
		return $block_template;
	}
	$parts = explode( '//', (string) $id, 2 );
	if ( 2 !== count( $parts ) || get_stylesheet() !== $parts[0] || 0 !== validate_file( $parts[1] ) ) {
		return $block_template;
	}
	$files = kits_site_template_files( (string) $template_type, array( 'slug__in' => array( $parts[1] ) ) );
	return isset( $files[ $parts[1] ] ) ? _build_block_template_result_from_file( $files[ $parts[1] ], $template_type ) : $block_template;
}
add_filter( 'get_block_file_template', 'kits_site_get_block_file_template', 1, 3 );

/**
 * A saved template or part whose file is here has a theme file (core looks only in the theme folders), so the Site
 * Editor offers to clear its customizations, and a saved template keeps the file's post types.
 *
 * @param WP_Block_Template|mixed $template Template.
 */
function kits_site_mark_saved( $template ) {
	if ( ! kits_site_on() || ! $template instanceof WP_Block_Template || 'custom' !== $template->source || $template->has_theme_file || get_stylesheet() !== $template->theme ) {
		return $template;
	}
	$files = kits_site_template_files( (string) $template->type, array( 'slug__in' => array( $template->slug ) ) );
	if ( isset( $files[ $template->slug ] ) ) {
		$template->has_theme_file = true;
		if ( 'wp_template' === $template->type && isset( $files[ $template->slug ]['postTypes'] ) ) {
			$template->post_types = $files[ $template->slug ]['postTypes'];
		}
	}
	return $template;
}
add_filter( 'get_block_template', 'kits_site_mark_saved', 1 );

/**
 * The .php files in a folder and its subfolders, in the order WP_Theme::scandir() lists a theme's patterns folder
 * (scandir() order, dot files and the excluded folders skipped).
 *
 * @param string $path          Folder, no trailing slash.
 * @param string $relative_path Path below the patterns folder (used when recursing).
 * @return string[] Path below the patterns folder => full path.
 */
function kits_site_pattern_scandir( string $path, string $relative_path = '' ): array {
	if ( ! is_dir( $path ) ) {
		return array();
	}
	$relative_path = '' === $relative_path ? '' : trailingslashit( $relative_path );
	$results       = scandir( $path );
	$files         = array();
	/** This filter is documented in wp-includes/class-wp-theme.php */
	$exclusions = (array) apply_filters( 'theme_scandir_exclusions', array( 'CVS', 'node_modules', 'vendor', 'bower_components' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook, applied as core does for a theme folder
	foreach ( $results ? $results : array() as $result ) {
		if ( '.' === $result[0] || in_array( $result, $exclusions, true ) ) {
			continue;
		}
		if ( is_dir( $path . '/' . $result ) ) {
			$files = array_merge( $files, kits_site_pattern_scandir( $path . '/' . $result, $relative_path . $result ) );
		} elseif ( preg_match( '~\.(php)$~', $result ) ) {
			$files[ $relative_path . $result ] = $path . '/' . $result;
		}
	}
	return $files;
}

/**
 * Pattern headers of the site's patterns folder, parsed as WP_Theme::get_block_patterns() parses a theme's: the
 * same file list filter (theme_block_pattern_files), the same checks and notices, a pattern with an invalid slug
 * still kept (core flags it and goes on). Cached in a site transient with core's expiry for theme pattern data
 * (wp_theme_files_cache_ttl) and only while the file list and dates match; no cache in theme development mode.
 *
 * @return array[] Path below the patterns folder => pattern data.
 */
function kits_site_pattern_data(): array {
	$dirpath = kits_site_path( 'patterns' );
	$files   = kits_site_pattern_scandir( $dirpath );
	/** This filter is documented in wp-includes/class-wp-theme.php */
	$files   = (array) apply_filters( 'theme_block_pattern_files', $files, $dirpath ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook, applied as core does for a theme folder
	$dirpath = trailingslashit( $dirpath );

	// Current files, by path below the patterns folder; only these may come back from the cache.
	$current = array();
	foreach ( $files as $file ) {
		$file = (string) $file;
		$key  = str_replace( $dirpath, '', $file );
		if ( 0 === strpos( $file, $dirpath ) && 0 === validate_file( $key ) && is_file( $file ) ) {
			$current[ $key ] = (int) filemtime( $file );
		}
	}

	$transient = 'kits_site_patterns';
	$can_cache = ! wp_is_development_mode( 'theme' );
	$sig       = md5( (string) wp_json_encode( $current ) );
	$cached    = get_site_transient( $transient );
	if ( is_array( $cached ) ) {
		if ( ! $can_cache ) {
			delete_site_transient( $transient );
		} elseif ( ( $cached['sig'] ?? '' ) === $sig && is_array( $cached['patterns'] ?? null ) ) {
			$patterns = array();
			foreach ( $cached['patterns'] as $key => $pattern ) {
				if ( is_string( $key ) && isset( $current[ $key ] ) && is_array( $pattern ) && isset( $pattern['slug'], $pattern['title'] ) ) {
					$patterns[ $key ] = $pattern;
				}
			}
			if ( count( $patterns ) === count( $cached['patterns'] ) ) {
				return $patterns;
			}
		}
	}

	$headers = array(
		'title'         => 'Title',
		'slug'          => 'Slug',
		'description'   => 'Description',
		'viewportWidth' => 'Viewport Width',
		'inserter'      => 'Inserter',
		'categories'    => 'Categories',
		'keywords'      => 'Keywords',
		'blockTypes'    => 'Block Types',
		'postTypes'     => 'Post Types',
		'templateTypes' => 'Template Types',
	);
	$out     = array();
	foreach ( $files as $file ) {
		$file = (string) $file;
		$key  = str_replace( $dirpath, '', $file );
		if ( ! isset( $current[ $key ] ) ) {
			continue;
		}
		$pattern = get_file_data( $file, $headers );
		if ( empty( $pattern['slug'] ) ) {
			/* translators: %s: file name. */
			_doing_it_wrong( __FUNCTION__, esc_html( sprintf( __( 'Could not register file "%s" as a block pattern ("Slug" field missing)', 'kits-by-wugweb' ), $file ) ), '6.0.0' );
			continue;
		}
		if ( ! preg_match( '/^[A-z0-9\/_-]+$/', $pattern['slug'] ) ) {
			/* translators: 1: file name; 2: slug value found. */
			_doing_it_wrong( __FUNCTION__, esc_html( sprintf( __( 'Could not register file "%1$s" as a block pattern (invalid slug "%2$s")', 'kits-by-wugweb' ), $file, $pattern['slug'] ) ), '6.0.0' );
		}
		if ( ! $pattern['title'] ) {
			/* translators: %s: file name. */
			_doing_it_wrong( __FUNCTION__, esc_html( sprintf( __( 'Could not register file "%s" as a block pattern ("Title" field missing)', 'kits-by-wugweb' ), $file ) ), '6.0.0' );
			continue;
		}
		foreach ( array( 'categories', 'keywords', 'blockTypes', 'postTypes', 'templateTypes' ) as $list ) {
			if ( ! empty( $pattern[ $list ] ) ) {
				$pattern[ $list ] = array_filter( wp_parse_list( (string) $pattern[ $list ] ) );
			} else {
				unset( $pattern[ $list ] );
			}
		}
		if ( ! empty( $pattern['viewportWidth'] ) ) {
			$pattern['viewportWidth'] = (int) $pattern['viewportWidth'];
		} else {
			unset( $pattern['viewportWidth'] );
		}
		if ( ! empty( $pattern['inserter'] ) ) {
			$pattern['inserter'] = in_array( strtolower( $pattern['inserter'] ), array( 'yes', 'true' ), true );
		} else {
			unset( $pattern['inserter'] );
		}
		$out[ $key ] = $pattern;
	}
	if ( $can_cache ) {
		/** This filter is documented in wp-includes/class-wp-theme.php */
		$ttl = (int) apply_filters( 'wp_theme_files_cache_ttl', 1800, 'theme_block_patterns' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook, applied as core does for a theme folder
		set_site_transient(
			$transient,
			array(
				'sig'      => $sig,
				'patterns' => $out,
			),
			$ttl > 0 ? $ttl : 1800
		);
	}
	return $out;
}

/**
 * Registers the site's patterns as core registers a theme's (_register_theme_block_patterns): same slugs and
 * headers, the file rendered when the pattern is used, a slug already registered left alone. Core hooks its run on
 * init at 10 and, in that run, registers a child theme's patterns before its parent's; core's callback is added
 * before any plugin loads, so this runs at 9, just ahead of it, to keep the child's patterns first.
 */
function kits_site_register_patterns() {
	if ( ! kits_site_on() || empty( wp_get_active_and_valid_themes() ) ) {
		return;
	}
	$registry = WP_Block_Patterns_Registry::get_instance();
	$dirpath  = trailingslashit( kits_site_path( 'patterns' ) );
	foreach ( kits_site_pattern_data() as $file => $pattern ) {
		if ( $registry->is_registered( $pattern['slug'] ) ) {
			continue;
		}
		$path = $dirpath . $file;
		if ( 0 !== validate_file( (string) $file ) || ! file_exists( $path ) ) {
			/* translators: %s: file name. */
			_doing_it_wrong( __FUNCTION__, esc_html( sprintf( __( 'Could not register file "%s" as a block pattern as the file does not exist.', 'kits-by-wugweb' ), $file ) ), '6.4.0' );
			delete_site_transient( 'kits_site_patterns' );
			continue;
		}
		$pattern['filePath'] = $path;
		// The child theme's text domain, as core translates a theme's pattern headers.
		$pattern['title'] = translate_with_gettext_context( $pattern['title'], 'Pattern title', 'kits-by-wugweb' ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText,WordPress.WP.I18n.LowLevelTranslationFunction -- pattern headers, translated as core translates a theme's
		if ( ! empty( $pattern['description'] ) ) {
			$pattern['description'] = translate_with_gettext_context( $pattern['description'], 'Pattern description', 'kits-by-wugweb' ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText,WordPress.WP.I18n.LowLevelTranslationFunction -- pattern headers, translated as core translates a theme's
		}
		register_block_pattern( $pattern['slug'], $pattern );
	}
}
add_action( 'init', 'kits_site_register_patterns', 9 );

/**
 * Kits → Help → Design system reads the site's registry and tokens (kits-registry.json, css/tokens.css) from the
 * site folder.
 *
 * @param string[] $dirs Folders with design system files.
 */
function kits_site_system_dirs( $dirs ) {
	if ( kits_site_on() && is_readable( kits_site_path( 'kits-registry.json' ) ) ) {
		$dirs[] = untrailingslashit( kits_site_dir() );
	}
	return array_values( array_unique( (array) $dirs ) );
}
add_filter( 'kits_system_dirs', 'kits_site_system_dirs' );

// The site's own styles, scripts and head tags, before the theme's hooks (where a child theme's functions.php loads).
if ( kits_site_on() && is_readable( kits_site_path( 'site.php' ) ) ) {
	require_once kits_site_path( 'site.php' );
}
