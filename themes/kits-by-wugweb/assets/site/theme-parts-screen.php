<?php
/**
 * Kits → Help → Developer → Theme parts (owner, 10 Nov 2026): what the animal-writes child theme used to hold, where each piece lives now
 * that Kits by Wugweb runs alone, and where to change it in wp-admin. Read-only: nothing here writes an option,
 * meta or post. Added to Kits → Help by this plugin, so Kits Core itself stays free of Animal Writes parts.
 *
 * Two core list tables: the child theme's functions (functions.php and theme.json), each with its file in the Kits
 * theme's site folder (assets/site/) or in Kits Core and whether it runs on this site; and the site folder's
 * templates, parts and patterns, read from the folder, each with a link to its Site Editor screen.
 *
 * @package kits-by-wugweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Kits → Help → Developer → Theme parts: a section added in admin-home.php (kits_help_sections); its old address
// aw-theme-parts redirects there.

/**
 * A wp-admin link.
 *
 * @param string $label Link text.
 * @param string $path  Path under wp-admin.
 */
function kits_site_theme_parts_link( string $label, string $path ): string {
	return '<a href="' . esc_url( admin_url( $path ) ) . '">' . esc_html( $label ) . '</a>';
}

/**
 * Status cell: core dashicon and a word.
 *
 * @param bool   $on  Runs on this site.
 * @param string $off Text when it does not.
 */
function kits_site_theme_parts_state( bool $on, string $off = 'Off: the active theme is not Kits by Wugweb alone' ): string {
	return $on
		? '<span class="dashicons dashicons-yes" aria-hidden="true"></span> Runs'
		: '<span class="dashicons dashicons-minus" aria-hidden="true"></span> ' . esc_html( $off );
}

/** Rows for the child theme's functions: function, what it does, now in, change it in, status. */
function kits_site_theme_parts_function_rows(): array {
	$parts = ( function_exists( 'kits_site_on' ) && kits_site_on() );
	$fonts = function_exists( 'kits_inter_hooks_on' ) && kits_inter_hooks_on();
	$here  = 'Kits theme, site folder';
	$kits  = 'Kits Core';
	$repo  = 'In the repository only (file editing is off in wp-admin)';
	$perf  = kits_site_theme_parts_link( 'Appearance → Kits → Settings → Performance', 'admin.php?page=kits-settings#performance' );
	$core  = kits_site_theme_parts_link( 'Appearance → Kits → Settings', 'admin.php?page=kits-settings' );
	$items = array(
		array( 'Site styles and script', 'Loads tokens.css and aw.js over the Kits styles on every page. The site-wide rules are in the Kits theme (kits.css); the rules only Animal Writes uses are in the Animal Writes style variation, saved in the global styles.', $here, 'assets/site/site.php', $repo, $parts ),
		array( 'Editor uses the site styles', 'The block editor and the classic editor show content with tokens.css and the component files.', $here, 'assets/site/site.php', 'Automatic', $parts ),
		array( 'Animal Writes pattern category', 'The "Animal Writes" group in the block inserter.', $here, 'assets/site/site.php', kits_site_theme_parts_link( 'Appearance → Editor → Patterns', 'site-editor.php?path=/patterns' ), $parts ),
		array( 'Home hero photo preload', 'The home page starts loading its hero photograph at once (not when a campaign hero shows).', $here, 'assets/site/site.php', 'Automatic', $parts ),
		array( 'tokens.css inline', 'The five brand values are printed in the page instead of a separate file.', $here, 'assets/site/site.php', 'Automatic', $parts ),
		array( 'Brand settings (theme.json)', 'Colours, fonts, spacing and the custom templates, merged over the Kits theme.json as a child theme\'s would be.', $here, 'assets/site/theme.json, inc/site-layer.php', kits_site_theme_parts_link( 'Appearance → Editor → Styles', 'site-editor.php?path=/wp_global_styles' ), $parts ),
		array( 'Inter preload and Inter Fallback', 'Wide screens preload Inter; until it arrives, text shows in Arial sized to Inter, so lines do not jump.', $kits, 'includes/fonts.php', $perf . ' → Font preload', $fonts ),
		array( 'One copy of Inter', 'Leaves out the Font Library\'s duplicate Inter files.', $kits, 'includes/fonts.php', $core . ' → One copy of Inter', $fonts ),
	);
	$rows  = array();
	foreach ( $items as $i ) {
		$rows[] = array(
			'item'   => '<strong>' . esc_html( $i[0] ) . '</strong>',
			'what'   => esc_html( $i[1] ),
			'now'    => esc_html( $i[2] ) . '<br><code>' . esc_html( $i[3] ) . '</code>',
			'change' => wp_kses_post( $i[4] ),
			'state'  => kits_site_theme_parts_state( $i[5] ),
		);
	}
	return $rows;
}

/**
 * Titles of the custom templates in the site folder's theme.json, by name.
 */
function kits_site_theme_parts_template_titles(): array {
	$file = function_exists( 'kits_site_path' ) ? kits_site_path( 'theme.json' ) : '';
	$data = is_readable( $file ) ? wp_json_file_decode( $file, array( 'associative' => true ) ) : null;
	$out  = array();
	foreach ( (array) ( $data['customTemplates'] ?? array() ) as $t ) {
		if ( ! empty( $t['name'] ) ) {
			$out[ (string) $t['name'] ] = (string) ( $t['title'] ?? $t['name'] );
		}
	}
	return $out;
}

/**
 * A template's or part's name when theme.json gives none: WordPress's own name for the standard templates (Pages,
 * Single Posts, Front Page), "Single: <type>" for single-<post type>, "Page: <name>" for page-<slug>, else the slug in words.
 *
 * @param string $slug     File name without .html.
 * @param bool   $template True for a template, false for a template part.
 */
function kits_site_theme_parts_template_name( string $slug, bool $template ): string {
	$words = ucfirst( str_replace( array( '-', '_' ), ' ', $slug ) );
	if ( ! $template ) {
		return $words;
	}
	$types = function_exists( 'get_default_block_template_types' ) ? get_default_block_template_types() : array();
	if ( ! empty( $types[ $slug ]['title'] ) ) {
		return (string) $types[ $slug ]['title'];
	}
	if ( 0 === strpos( $slug, 'single-' ) ) {
		$obj = get_post_type_object( substr( $slug, 7 ) );
		return 'Single: ' . ( $obj ? $obj->labels->singular_name : substr( $words, 7 ) );
	}
	if ( 0 === strpos( $slug, 'page-' ) ) {
		return 'Page: ' . ucfirst( str_replace( '-', ' ', substr( $slug, 5 ) ) );
	}
	return $words;
}

/** Rows for the files in the theme's site folder: file, kind, change it in, status. */
function kits_site_theme_parts_file_rows(): array {
	$parts  = ( function_exists( 'kits_site_on' ) && kits_site_on() );
	$theme  = get_stylesheet();
	$titles = kits_site_theme_parts_template_titles();
	$rows   = array();
	$kinds  = array(
		'templates' => array( 'Template', '*.html', 'wp_template' ),
		'parts'     => array( 'Template part', '*.html', 'wp_template_part' ),
		'patterns'  => array( 'Pattern', '*.php', '' ),
	);
	foreach ( $kinds as $dir => $k ) {
		$files = function_exists( 'kits_site_path' ) ? glob( kits_site_path( $dir . '/' . $k[1] ) ) : array();
		foreach ( is_array( $files ) ? $files : array() as $f ) {
			$slug = basename( $f, ( '.html' === substr( $k[1], 1 ) ) ? '.html' : '.php' );
			if ( '' !== $k[2] ) {
				$name = $titles[ $slug ] ?? kits_site_theme_parts_template_name( $slug, 'wp_template' === $k[2] );
				$link = kits_site_theme_parts_link( 'Open in the Site Editor', 'site-editor.php?postType=' . $k[2] . '&postId=' . rawurlencode( $theme . '//' . $slug ) . '&canvas=edit' );
			} else {
				$head = get_file_data( $f, array( 'title' => 'Title' ) );
				$name = '' !== $head['title'] ? $head['title'] : $slug;
				$link = kits_site_theme_parts_link( 'Appearance → Editor → Patterns', 'site-editor.php?path=/patterns' );
			}
			$rows[] = array(
				'item'   => '<strong>' . esc_html( $name ) . '</strong><br><code>assets/site/' . esc_html( $dir . '/' . basename( $f ) ) . '</code>',
				'kind'   => esc_html( $k[0] ),
				'change' => $link,
				'state'  => kits_site_theme_parts_state( $parts ),
			);
		}
	}
	return $rows;
}

/** The screen. */
function kits_site_theme_parts_screen() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'kits-by-wugweb' ), 403 );
	}
	$intro = 'What the Animal Writes child theme held, where each part lives now that Kits by Wugweb runs on its own, and where to change it. The Kits theme serves these parts from its site folder (assets/site/), which the distributed theme leaves out.';
	if ( function_exists( 'kits_admin_head' ) ) {
		kits_admin_head( 'kits-help', 'Theme parts', $intro ); // Opens .wrap (inside Help: a plain box).
	} else {
		echo '<div class="wrap"><h1>' . esc_html( 'Theme parts' ) . '</h1><p>' . esc_html( $intro ) . '</p>';
	}
	if ( ! ( function_exists( 'kits_site_on' ) && kits_site_on() ) ) {
		wp_admin_notice( esc_html( 'The active theme is not Kits by Wugweb alone, so the theme does not serve these parts.' ), array( 'type' => 'warning', 'additional_classes' => array( 'inline' ) ) );
	}
	echo '<h2>' . esc_html( 'Functions' ) . '</h2>';
	kits_simple_table(
		'aw-theme-functions',
		array(
			'item'   => 'Function',
			'what'   => 'What it does',
			'now'    => 'Now in',
			'change' => 'Change it in',
			'state'  => 'On this site',
		),
		kits_site_theme_parts_function_rows()
	);
	echo '<h2>' . esc_html( 'Templates, parts and patterns' ) . '</h2>';
	kits_simple_table(
		'aw-theme-files',
		array(
			'item'   => 'File',
			'kind'   => 'Kind',
			'change' => 'Change it in',
			'state'  => 'On this site',
		),
		kits_site_theme_parts_file_rows(),
		array( 'empty' => 'No files found in assets/site/.' )
	);
	echo '</div>';
}
