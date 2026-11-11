<?php
/**
 * Kits admin app, Colours section (docs/admin-ui-spec.md D1.5): the theme's palette, the colour sets (section styles
 * in the theme's styles/ folder that have blockTypes), the site colour set (kits_page_defaults._site.colours) and what
 * the live contrast table needs (the design registry's text/background pairs and the token values they resolve
 * through). Edits are saved in Global Styles (admin-app-global-styles.php) and kits_page_defaults.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'kits_admin_app_data', 'kits_admin_app_colours_data' );

/**
 * Adds colours: { palette, sets, pairs, tokens, contrastUrl } and the 'colours' page default.
 *
 * @param array $data window.kitsAdminApp.
 */
function kits_admin_app_colours_data( array $data ): array {
	if ( ! class_exists( 'WP_Theme_JSON_Resolver' ) ) {
		return $data;
	}
	$theme   = WP_Theme_JSON_Resolver::get_theme_data()->get_settings();
	$palette = array();
	foreach ( (array) ( $theme['color']['palette']['theme'] ?? array() ) as $c ) {
		if ( isset( $c['slug'], $c['color'] ) ) {
			$palette[] = array(
				'slug'  => (string) $c['slug'],
				'name'  => (string) ( $c['name'] ?? $c['slug'] ),
				'color' => (string) $c['color'],
			);
		}
	}

	// Colour sets: the labels a page or a Group shows for them.
	$labels = array();
	foreach ( WP_Block_Styles_Registry::get_instance()->get_registered_styles_for_block( 'core/post-content' ) as $name => $style ) {
		$labels[ $name ] = (string) ( $style['label'] ?? $name );
	}
	$sets = array();
	foreach ( (array) WP_Theme_JSON_Resolver::get_style_variations( 'block' ) as $v ) {
		$slug  = (string) ( $v['slug'] ?? '' );
		$types = array_values( array_filter( (array) ( $v['blockTypes'] ?? array() ), 'is_string' ) );
		$st    = (array) ( $v['styles'] ?? array() );
		if ( '' === $slug || ! $types || ! isset( $st['color'] ) ) {
			continue; // Not a colour set (a style with no colours).
		}
		$sets[] = array(
			'slug'       => $slug,
			'label'      => $labels[ $slug ] ?? (string) ( $v['title'] ?? $slug ),
			'blockTypes' => $types,
			'theme'      => array(
				'background' => (string) ( $st['color']['background'] ?? '' ),
				'text'       => (string) ( $st['color']['text'] ?? '' ),
				'heading'    => (string) ( $st['elements']['heading']['color']['text'] ?? '' ),
				'link'       => (string) ( $st['elements']['link']['color']['text'] ?? '' ),
			),
		);
	}

	// Contrast pairs from the design registry, and the token values they reach through var().
	$pairs = array();
	$want  = array();
	$reg   = function_exists( 'kits_registry' ) ? kits_registry() : array();
	foreach ( (array) ( $reg['items'] ?? array() ) as $it ) {
		$pair = $it['a11y']['contrast'] ?? null;
		if ( is_array( $pair ) && isset( $pair[0], $pair[1] ) ) {
			$want[]  = (string) $pair[0];
			$want[]  = (string) $pair[1];
			$pairs[] = array(
				'name' => (string) ( $it['name'] ?? '' ),
				'text' => (string) $pair[0],
				'on'   => (string) $pair[1],
			);
		}
	}
	$vals   = function_exists( 'kits_token_values' ) ? kits_token_values() : array();
	$tokens = array();
	while ( $want ) {
		$name = array_pop( $want );
		if ( isset( $tokens[ $name ] ) || ! isset( $vals[ $name ] ) ) {
			continue;
		}
		$tokens[ $name ] = (string) $vals[ $name ];
		if ( preg_match_all( '/var\(\s*--([a-z0-9-]+)/i', $tokens[ $name ], $m ) ) {
			$want = array_merge( $want, $m[1] );
		}
	}

	$data['colours'] = array(
		'palette'     => $palette,
		'sets'        => $sets,
		'pairs'       => $pairs,
		'tokens'      => (object) $tokens,
		'contrastUrl' => admin_url( 'admin.php?page=kits-help&tab=contrast' ) . '#design-system',
	);

	// The site colour set: a page default like header and footer, drawn by the app's page-default field.
	if ( function_exists( 'kits_page_option_fields' ) && isset( kits_page_option_fields()['colours'] ) ) {
		$pd                      = (array) ( $data['pageDefaults'] ?? array() );
		$pd['fields']            = (array) ( $pd['fields'] ?? array() );
		$pd['fields']['colours'] = array(
			'label'   => kits_page_option_fields()['colours'][0],
			'note'    => __( 'The colour set of every page’s content, unless a kind of page or the page picks its own.', 'kits-core' ),
			'choices' => (object) kits_page_option_choices( 'colours' ),
			'empty'   => __( 'None yet. Colour sets are section styles in the theme’s styles folder.', 'kits-core' ),
		);
		$site  = (array) ( $pd['site'] ?? array() );
		$saved = (array) get_option( 'kits_page_defaults', array() );
		if ( isset( $saved['_site']['colours'] ) ) {
			$site['colours'] = (string) $saved['_site']['colours'];
		}
		$pd['site']           = (object) $site;
		$data['pageDefaults'] = $pd;
	}
	return $data;
}
