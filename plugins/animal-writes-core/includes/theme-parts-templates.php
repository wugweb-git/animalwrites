<?php
/**
 * Templates, template parts and patterns of the Animal Writes theme, served by this plugin when Kits by Wugweb runs
 * with no child theme (see theme-parts.php). Nothing here runs while the animal-writes child theme is active.
 *
 * The files are copies of the child theme's (theme/templates, theme/parts, theme/patterns, theme/theme.json) and are
 * served as if they were the active theme's own files, ranked the way WordPress ranks a child theme's files:
 *
 * - Templates and parts: the get_block_templates and get_block_file_template filters return each file built by core
 *   (_build_block_template_result_from_file: source "theme", the active theme's id, block hooks applied). A file here
 *   takes the place of Kits' file of the same slug (page, single, 404, archive and the four parts), as a child
 *   theme's file does; a template saved in the Site Editor (database) still wins over both, and "Clear
 *   customizations" goes back to the file here. register_block_template() is not used: core ranks a theme file above
 *   a plugin template of the same slug, so page, single, 404 and archive would stay Kits', and it has no parts.
 * - theme.json: the custom templates (page-hub, campaign-page) and any template part areas are merged into the theme
 *   data (wp_theme_json_data_theme), as the child theme's theme.json is merged over Kits'.
 * - Patterns: registered on init from the file headers, as core does for a theme's patterns folder.
 * - A template part block saved with "theme":"animal-writes" renders the active theme's part.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Folder of this plugin's template or template part files.
 *
 * @param string $template_type 'wp_template' or 'wp_template_part'.
 */
function aw_core_theme_template_dir( string $template_type ): string {
	return AW_THEME_PARTS_DIR . ( 'wp_template_part' === $template_type ? 'parts' : 'templates' );
}

/**
 * This plugin's template or part files that match a query, in the form core's _get_block_templates_files() returns
 * (slug, path, theme, type, and title, postTypes or area from theme.json).
 *
 * @param string $template_type 'wp_template' or 'wp_template_part'.
 * @param array  $query         slug__in, slug__not_in, area (parts only). post_type is applied by the caller.
 * @return array[] Keyed by slug.
 */
function aw_core_theme_template_files( string $template_type, array $query = array() ): array {
	if ( 'wp_template' !== $template_type && 'wp_template_part' !== $template_type ) {
		return array();
	}
	static $paths = array();
	if ( ! isset( $paths[ $template_type ] ) ) {
		$paths[ $template_type ] = (array) glob( aw_core_theme_template_dir( $template_type ) . '/*.html' );
		sort( $paths[ $template_type ] );
	}
	$include = (array) ( $query['slug__in'] ?? array() );
	$skip    = (array) ( $query['slug__not_in'] ?? array() );
	$area    = $query['area'] ?? null;
	$out     = array();
	foreach ( $paths[ $template_type ] as $path ) {
		$slug = basename( (string) $path, '.html' );
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
 * Templates and parts lists (Site Editor, the template hierarchy, Page Options): this plugin's files take the place
 * of Kits' files of the same slug and are added where Kits has none, with core's post type rules
 * (get_block_templates()). Saved templates (source "custom") are kept as they are, marked as having a theme file.
 *
 * @param WP_Block_Template[] $templates     Found templates.
 * @param array               $query         The query; slug__not_in already holds the saved templates' slugs.
 * @param string              $template_type 'wp_template' or 'wp_template_part'.
 */
function aw_core_theme_get_block_templates( $templates, $query, $template_type ) {
	if ( ! aw_core_theme_parts_on() || ! is_array( $templates ) ) {
		return $templates;
	}
	$query     = (array) $query;
	$templates = array_map( 'aw_core_theme_mark_saved', $templates );
	if ( isset( $query['wp_id'] ) ) {
		return $templates;
	}
	$files_query = $query;
	unset( $files_query['post_type'] );
	$files = aw_core_theme_template_files( (string) $template_type, $files_query );
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
add_filter( 'get_block_templates', 'aw_core_theme_get_block_templates', 1, 3 );

/**
 * One template or part file by id (the template part block, the Site Editor's reset): this plugin's file when the id
 * is the active theme's and the file is here.
 *
 * @param WP_Block_Template|null $block_template What core found (Kits' file, a registered template or nothing).
 * @param string                 $id            Template id, theme//slug.
 * @param string                 $template_type 'wp_template' or 'wp_template_part'.
 */
function aw_core_theme_get_block_file_template( $block_template, $id, $template_type ) {
	if ( ! aw_core_theme_parts_on() ) {
		return $block_template;
	}
	$parts = explode( '//', (string) $id, 2 );
	if ( 2 !== count( $parts ) || get_stylesheet() !== $parts[0] || 0 !== validate_file( $parts[1] ) ) {
		return $block_template;
	}
	$files = aw_core_theme_template_files( (string) $template_type, array( 'slug__in' => array( $parts[1] ) ) );
	return isset( $files[ $parts[1] ] ) ? _build_block_template_result_from_file( $files[ $parts[1] ], $template_type ) : $block_template;
}
add_filter( 'get_block_file_template', 'aw_core_theme_get_block_file_template', 1, 3 );

/**
 * A saved template or part whose file is here has a theme file (core looks only in the theme folders), so the Site
 * Editor offers to clear its customizations, and a saved template keeps the file's post types.
 *
 * @param WP_Block_Template|mixed $template Template.
 */
function aw_core_theme_mark_saved( $template ) {
	if ( ! aw_core_theme_parts_on() || ! $template instanceof WP_Block_Template || 'custom' !== $template->source || $template->has_theme_file || get_stylesheet() !== $template->theme ) {
		return $template;
	}
	$files = aw_core_theme_template_files( (string) $template->type, array( 'slug__in' => array( $template->slug ) ) );
	if ( isset( $files[ $template->slug ] ) ) {
		$template->has_theme_file = true;
		if ( 'wp_template' === $template->type && isset( $files[ $template->slug ]['postTypes'] ) ) {
			$template->post_types = $files[ $template->slug ]['postTypes'];
		}
	}
	return $template;
}
add_filter( 'get_block_template', 'aw_core_theme_mark_saved', 1 );

/**
 * The custom templates and template parts declared in the Animal Writes theme.json, merged over Kits' theme data the
 * way a child theme's theme.json is (same keys, same order).
 *
 * @param WP_Theme_JSON_Data $theme_json Kits' theme.json data.
 */
function aw_core_theme_json_templates( $theme_json ) {
	if ( ! aw_core_theme_parts_on() || ! is_object( $theme_json ) || ! method_exists( $theme_json, 'update_with' ) ) {
		return $theme_json;
	}
	$file = AW_THEME_PARTS_DIR . 'theme.json';
	$data = is_readable( $file ) ? wp_json_file_decode( $file, array( 'associative' => true ) ) : null;
	if ( ! is_array( $data ) ) {
		return $theme_json;
	}
	$add = array_intersect_key( $data, array_flip( array( 'customTemplates', 'templateParts' ) ) );
	if ( ! $add ) {
		return $theme_json;
	}
	$add['version'] = (int) ( $data['version'] ?? WP_Theme_JSON::LATEST_SCHEMA );
	return $theme_json->update_with( $add );
}
add_filter( 'wp_theme_json_data_theme', 'aw_core_theme_json_templates' );

/**
 * A template part block saved while the child theme was active can carry "theme":"animal-writes"; core renders a part
 * only for the active theme, so it is read as the active theme's part.
 *
 * @param array $block Parsed block.
 */
function aw_core_theme_part_block_theme( $block ) {
	if ( 'core/template-part' === ( $block['blockName'] ?? '' ) && 'animal-writes' === ( $block['attrs']['theme'] ?? '' ) && aw_core_theme_parts_on() ) {
		$block['attrs']['theme'] = get_stylesheet();
	}
	return $block;
}
add_filter( 'render_block_data', 'aw_core_theme_part_block_theme', 1 );

/**
 * Pattern headers of this plugin's patterns folder, parsed as WP_Theme::get_block_patterns() parses a theme's
 * (kept until a file changes).
 *
 * @return array[] File name => pattern data.
 */
function aw_core_theme_pattern_data(): array {
	$dir   = AW_THEME_PARTS_DIR . 'patterns/';
	$files = (array) glob( $dir . '*.php' );
	sort( $files );
	$sig    = md5( implode( ',', array_map( fn( $f ) => basename( (string) $f ) . ':' . (int) filemtime( (string) $f ), $files ) ) );
	$cached = get_site_transient( 'aw_core_theme_patterns' );
	if ( is_array( $cached ) && ( $cached['sig'] ?? '' ) === $sig && is_array( $cached['patterns'] ?? null ) ) {
		return $cached['patterns'];
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
		$pattern = get_file_data( (string) $file, $headers );
		if ( empty( $pattern['slug'] ) || ! preg_match( '/^[A-z0-9\/_-]+$/', $pattern['slug'] ) || ! $pattern['title'] ) {
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
		$out[ basename( (string) $file ) ] = $pattern;
	}
	set_site_transient(
		'aw_core_theme_patterns',
		array(
			'sig'      => $sig,
			'patterns' => $out,
		)
	);
	return $out;
}

/**
 * Registers this plugin's patterns as core registers a theme's (_register_theme_block_patterns): same slugs and
 * headers, the file rendered when the pattern is used, a slug already registered left alone. Before core's own run
 * (init 10), as a child theme's patterns come before its parent's.
 */
function aw_core_theme_register_patterns() {
	if ( ! aw_core_theme_parts_on() ) {
		return;
	}
	$registry = WP_Block_Patterns_Registry::get_instance();
	foreach ( aw_core_theme_pattern_data() as $file => $pattern ) {
		$path = AW_THEME_PARTS_DIR . 'patterns/' . $file;
		if ( $registry->is_registered( $pattern['slug'] ) || ! is_readable( $path ) ) {
			continue;
		}
		$pattern['filePath'] = $path;
		// The child theme's text domain, as core translates a theme's pattern headers.
		$pattern['title'] = translate_with_gettext_context( $pattern['title'], 'Pattern title', 'animal-writes' ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText,WordPress.WP.I18n.LowLevelTranslationFunction
		if ( ! empty( $pattern['description'] ) ) {
			$pattern['description'] = translate_with_gettext_context( $pattern['description'], 'Pattern description', 'animal-writes' ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText,WordPress.WP.I18n.LowLevelTranslationFunction
		}
		register_block_pattern( $pattern['slug'], $pattern );
	}
}
add_action( 'init', 'aw_core_theme_register_patterns', 9 );

/**
 * Kits → Design system reads the Animal Writes registry and tokens (kits-registry.json, assets/css/tokens.css) from
 * this plugin's theme folder when there is no child theme.
 *
 * @param string[] $dirs Folders with design system files.
 */
function aw_core_theme_system_dirs( $dirs ) {
	if ( aw_core_theme_parts_on() && is_readable( AW_THEME_PARTS_DIR . 'kits-registry.json' ) ) {
		$dirs[] = untrailingslashit( AW_THEME_PARTS_DIR );
	}
	return array_values( array_unique( (array) $dirs ) );
}
add_filter( 'kits_system_dirs', 'aw_core_theme_system_dirs' );
