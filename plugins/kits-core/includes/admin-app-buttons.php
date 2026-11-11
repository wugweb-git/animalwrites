<?php
/**
 * Theme Options → Buttons (docs/admin-ui-spec.md D1.7): the data the Buttons section of the new screen needs, and the
 * hover tokens its hover colours set on the site.
 *
 * Every button style is edited in Global Styles (user origin), so Styles → Blocks → Button shows the same values:
 * - the default style (core "fill", the theme calls it Primary): styles.elements.button and its :hover;
 * - each Button block style the theme registers: styles.blocks.core/button.variations.<slug> (its hover colours in
 *   that variation's elements.button.:hover).
 * Colours pick from the palette, corner radius and padding from the theme's radius and spacing sizes.
 *
 * Buttons read --kit-btn-* tokens (blocks.php maps background, text, border, radius, weight and padding). The theme's
 * hover colours are tokens too (--kit-btn-<style>-bg-hover / -fg-hover); this file prints a chosen hover colour as
 * that token, after blocks.php's tokens.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The button styles this section edits, by key: 'fill' is the default style, the rest are core/button style slugs.
 * 'hover' names the tokens a hover colour sets ([ background token, text token ]); a style without them has no hover
 * fields (Brand: its hover is a darker shade of its own colour).
 *
 * @return array
 */
function kits_admin_buttons_styles(): array {
	$known = array(
		'fill'           => array(
			'label'  => __( 'Primary (fill)', 'kits-core' ),
			'note'   => __( 'Every button that has no style chosen.', 'kits-core' ),
			'fields' => array( 'background', 'text', 'radius', 'padding', 'hover_background', 'hover_text' ),
			'hover'  => array( '--kit-btn-primary-bg-hover', '--kit-btn-primary-fg-hover' ),
			'tokens' => array(
				'background' => '--kit-btn-primary-bg',
				'text'       => '--kit-btn-primary-fg',
			),
		),
		'kits-secondary' => array(
			'note'   => __( 'Outlined buttons (also the older Outline style).', 'kits-core' ),
			'fields' => array( 'background', 'text', 'border', 'hover_background', 'hover_text' ),
			'hover'  => array( '--kit-btn-secondary-bg-hover', '--kit-btn-secondary-fg-hover' ),
			'tokens' => array(
				'background' => '--kit-btn-secondary-bg',
				'text'       => '--kit-btn-secondary-fg',
				'border'     => '--kit-btn-secondary-line',
			),
		),
		'kits-ghost'     => array(
			'note'   => __( 'Text buttons: no background until hover.', 'kits-core' ),
			'fields' => array( 'text', 'border', 'hover_background', 'hover_text' ),
			'hover'  => array( '--kit-btn-ghost-bg-hover', '--kit-btn-ghost-fg-hover' ),
			'tokens' => array(
				'text'   => '--kit-btn-ghost-fg',
				'border' => '--kit-btn-ghost-line',
			),
		),
		'kits-brand'     => array(
			'note'   => __( 'Its border is its background; on hover it turns a shade darker.', 'kits-core' ),
			'fields' => array( 'background', 'text' ),
			'hover'  => array(),
			'tokens' => array(
				'background' => '--kit-btn-brand-bg',
				'text'       => '--kit-btn-brand-fg',
			),
		),
	);

	$out = array( 'fill' => $known['fill'] );
	if ( class_exists( 'WP_Block_Styles_Registry' ) ) {
		foreach ( WP_Block_Styles_Registry::get_instance()->get_registered_styles_for_block( 'core/button' ) as $slug => $style ) {
			// Core's own Fill and Outline are the default style and Secondary (kit-primitives.css section 10).
			if ( in_array( $slug, array( 'fill', 'outline' ), true ) ) {
				continue;
			}
			$row          = $known[ $slug ] ?? array(
				'note'   => '',
				'fields' => array( 'background', 'text', 'border' ),
				'hover'  => array(),
				'tokens' => array(),
			);
			$row['label'] = (string) ( $style['label'] ?? $slug );
			$out[ $slug ] = $row;
		}
	}
	return $out;
}

/**
 * Where each field is kept inside a button style's Global Styles node.
 *
 * @return array field => path.
 */
function kits_admin_buttons_paths(): array {
	return array(
		'background'       => array( 'color', 'background' ),
		'text'             => array( 'color', 'text' ),
		'border'           => array( 'border', 'color' ),
		'radius'           => array( 'border', 'radius' ),
		'padding'          => array( 'spacing', 'padding', 'left' ), // Right is kept the same (both sides).
		'hover_background' => array( ':hover', 'color', 'background' ),
		'hover_text'       => array( ':hover', 'color', 'text' ),
	);
}

/**
 * A button style's node inside Global Styles data (theme or user): elements.button for the default style, the
 * core/button variation otherwise. Hover sits under the node itself (elements.button.:hover) or, for a variation,
 * under its elements.button.
 *
 * @param array  $styles A theme.json 'styles' array.
 * @param string $key    'fill' or a variation slug.
 * @param bool   $hover  True for the node that holds :hover.
 * @return array
 */
function kits_admin_buttons_node( array $styles, string $key, bool $hover = false ): array {
	if ( 'fill' === $key ) {
		$node = $styles['elements']['button'] ?? array();
	} else {
		$node = $styles['blocks']['core/button']['variations'][ $key ] ?? array();
		if ( $hover ) {
			$node = is_array( $node ) ? ( $node['elements']['button'] ?? array() ) : array();
		}
	}
	return is_array( $node ) ? $node : array();
}

/**
 * The values of one button style in Global Styles data, by field ('' fields left out).
 *
 * @param array  $styles A theme.json 'styles' array (the theme's).
 * @param string $key    'fill' or a variation slug.
 * @param array  $fields Field names.
 * @return object field => value (an object, so an empty list is {} in JSON).
 */
function kits_admin_buttons_values( array $styles, string $key, array $fields ): object {
	$paths = kits_admin_buttons_paths();
	$out   = array();
	foreach ( $fields as $field ) {
		$path = $paths[ $field ];
		$node = kits_admin_buttons_node( $styles, $key, ':hover' === $path[0] );
		$v    = _wp_array_get( $node, $path, null );
		if ( is_string( $v ) && '' !== $v ) {
			$out[ $field ] = $v;
		}
	}
	return (object) $out;
}

/**
 * Custom properties from the theme's token files (the first value of each), so the screen's preview can draw a theme
 * value such as var(--kit-btn-secondary-bg). Only the active theme's files are read; none = an empty list.
 *
 * @return array name => value.
 */
function kits_admin_buttons_theme_vars(): array {
	$vars = array();
	foreach ( (array) apply_filters( 'kits_admin_buttons_token_files', array( 'assets/css/kit-tokens.css', 'assets/css/kits-v42.css' ) ) as $rel ) {
		$file = get_template_directory() . '/' . ltrim( (string) $rel, '/' );
		if ( ! is_readable( $file ) ) {
			continue;
		}
		$css = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local theme file.
		$css = (string) preg_replace( '#/\*.*?\*/#s', '', $css );
		if ( preg_match_all( '/(--[\w-]+)\s*:\s*([^;{}]+);/', $css, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $row ) {
				if ( ! isset( $vars[ $row[1] ] ) ) {
					$vars[ $row[1] ] = trim( $row[2] );
				}
			}
		}
	}
	return $vars;
}

/**
 * Preset lists from the site's settings (theme and the site's own): [ [ slug, name, value ], … ].
 *
 * @param array  $path Settings path to the preset list.
 * @param string $key  Value key in each preset ('color' or 'size').
 * @return array
 */
function kits_admin_buttons_presets( array $path, string $key ): array {
	$sets = (array) wp_get_global_settings( $path );
	$out  = array();
	foreach ( array( 'theme', 'custom' ) as $origin ) {
		foreach ( (array) ( $sets[ $origin ] ?? array() ) as $p ) {
			if ( isset( $p['slug'], $p[ $key ] ) ) {
				$out[ (string) $p['slug'] ] = array( (string) $p['slug'], (string) ( $p['name'] ?? $p['slug'] ), (string) $p[ $key ] );
			}
		}
	}
	return array_values( $out );
}

/** Everything the Buttons section needs, or null when the user cannot edit Global Styles. */
function kits_admin_buttons_data(): ?array {
	if ( ! current_user_can( 'edit_theme_options' ) || ! class_exists( 'WP_Theme_JSON_Resolver' ) ) {
		return null;
	}
	// The site's own values come with the app's Global Styles store (admin-app-global-styles.php).
	$theme_raw = WP_Theme_JSON_Resolver::get_theme_data()->get_raw_data();
	$theme_st  = is_array( $theme_raw['styles'] ?? null ) ? $theme_raw['styles'] : array();

	$styles = array();
	foreach ( kits_admin_buttons_styles() as $key => $s ) {
		$styles[] = array(
			'key'    => (string) $key,
			'label'  => $s['label'],
			'note'   => $s['note'],
			'fields' => $s['fields'],
			// Tokens the preview falls back to when neither the theme nor the site sets a value.
			'tokens' => (object) array_merge(
				$s['tokens'],
				$s['hover'] ? array(
					'hover_background' => $s['hover'][0],
					'hover_text'       => $s['hover'][1],
				) : array()
			),
			'theme'  => kits_admin_buttons_values( $theme_st, (string) $key, $s['fields'] ),
		);
	}

	$palette = kits_admin_buttons_presets( array( 'color', 'palette' ), 'color' );
	$spacing = array_values(
		array_filter(
			kits_admin_buttons_presets( array( 'spacing', 'spacingSizes' ), 'size' ),
			fn( $p ) => 'section' !== $p[0] // The section step is for sections, not buttons.
		)
	);
	$radius  = kits_admin_buttons_presets( array( 'border', 'radiusSizes' ), 'size' );

	$vars = kits_admin_buttons_theme_vars();
	foreach ( $palette as $p ) {
		$vars[ '--wp--preset--color--' . $p[0] ] = $p[2];
	}
	foreach ( $spacing as $p ) {
		$vars[ '--wp--preset--spacing--' . $p[0] ] = $p[2];
	}
	foreach ( $radius as $p ) {
		$vars[ '--wp--preset--border-radius--' . $p[0] ] = $p[2];
	}

	return array(
		'styles'  => $styles,
		'palette' => $palette,
		'spacing' => $spacing,
		'radius'  => $radius,
		'vars'    => (object) $vars,
		'link'    => admin_url( 'site-editor.php?path=/wp_global_styles' ),
	);
}

// The section's data in window.kitsAdminApp.buttons (admin-app.php's kits_admin_app_data filter).
add_filter(
	'kits_admin_app_data',
	function ( array $data ): array {
		$buttons = kits_admin_buttons_data();
		if ( null !== $buttons ) {
			$data['buttons'] = $buttons;
		}
		return $data;
	}
);

/**
 * The hover tokens for the hover colours chosen in Styles (or on this screen), e.g. ':root{--kit-btn-primary-bg-hover:…}'.
 *
 * @return string CSS, or ''.
 */
function kits_admin_buttons_hover_css(): string {
	if ( ! function_exists( 'kits_style_value_css' ) ) {
		return '';
	}
	$out = '';
	foreach ( kits_admin_buttons_styles() as $key => $s ) {
		if ( ! $s['hover'] ) {
			continue;
		}
		foreach ( array( 'background', 'text' ) as $i => $prop ) {
			$path = 'fill' === $key
				? array( 'elements', 'button', ':hover', 'color', $prop )
				: array( 'variations', $key, 'elements', 'button', ':hover', 'color', $prop );
			$opts = 'fill' === $key ? array() : array( 'block_name' => 'core/button' );
			$css  = kits_style_value_css( wp_get_global_styles( $path, $opts ) );
			// A value naming a button token could make the tokens cyclic, so it is left out (as in blocks.php).
			if ( '' !== $css && ! preg_match( '/--kit-btn-/', $css ) ) {
				$out .= $s['hover'][ $i ] . ':' . $css . ';';
			}
		}
	}
	return '' !== $out ? ':root{' . $out . '}' : '';
}

// After blocks.php's tokens (priority 20), on the same stylesheet, so a chosen hover colour wins over the derived one.
add_action(
	'wp_enqueue_scripts',
	function () {
		$css = kits_admin_buttons_hover_css();
		if ( '' === $css ) {
			return;
		}
		foreach ( array( apply_filters( 'kits_style_tokens_handle', 'kit-tokens' ), 'global-styles' ) as $handle ) {
			if ( wp_style_is( $handle, 'enqueued' ) ) {
				wp_add_inline_style( $handle, $css );
				return;
			}
		}
	},
	21
);
