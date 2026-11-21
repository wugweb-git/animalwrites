<?php
/**
 * Kits Brand Customizer Model: pure data structures and token helpers for multi-tenant branding.
 *
 * Designed for 1 default theme + 3 tenant setups without code changes.
 * Pure PHP: tested without WordPress in tests/brand-customizer-tests.php.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) && 'cli' !== PHP_SAPI ) {
	exit;
}

/**
 * Returns available brand token definitions.
 *
 * @return array<string, array{label: string, category: string, css_var: string, default: string, type: string, description: string, wp_preset?: string}>
 */
function kits_brand_token_definitions(): array {
	return array(
		// --- 1. Brand Colors ---
		'accent' => array(
			'label'       => 'Brand Accent',
			'category'    => 'Colors',
			'css_var'     => '--kit-color-accent',
			'wp_preset'   => '--wp--preset--color--primary',
			'default'     => '#FFBE1A',
			'type'        => 'color',
			'description' => 'Primary accent color for buttons, active pills, badges and key links.',
		),
		'accent_ink' => array(
			'label'       => 'Accent Contrast Ink',
			'category'    => 'Colors',
			'css_var'     => '--kit-color-accent-ink',
			'wp_preset'   => '--wp--preset--color--primary-dark',
			'default'     => '#0062b8',
			'type'        => 'color',
			'description' => 'Darker accent contrast for focus outlines, text links, and hover states.',
		),
		'bg' => array(
			'label'       => 'Canvas Background',
			'category'    => 'Colors',
			'css_var'     => '--kit-color-bg',
			'wp_preset'   => '--wp--preset--color--canvas',
			'default'     => '#F4F4F4',
			'type'        => 'color',
			'description' => 'Base background canvas color behind page sections.',
		),
		'raised' => array(
			'label'       => 'Card / Surface Background',
			'category'    => 'Colors',
			'css_var'     => '--kit-color-raised',
			'wp_preset'   => '--wp--preset--color--background',
			'default'     => '#FFFFFF',
			'type'        => 'color',
			'description' => 'Surface color for cards, panels, search bars, and dropdown menus.',
		),
		'ink' => array(
			'label'       => 'Headings & Strong Ink',
			'category'    => 'Colors',
			'css_var'     => '--kit-color-ink',
			'wp_preset'   => '--wp--preset--color--secondary',
			'default'     => '#191919',
			'type'        => 'color',
			'description' => 'High-contrast ink color for titles, headlines, and bold text.',
		),
		'text' => array(
			'label'       => 'Body Text',
			'category'    => 'Colors',
			'css_var'     => '--kit-color-text',
			'wp_preset'   => '--wp--preset--color--foreground',
			'default'     => '#2E2E2E',
			'type'        => 'color',
			'description' => 'Main body copy, paragraphs, and general reading text.',
		),
		'muted' => array(
			'label'       => 'Muted Text',
			'category'    => 'Colors',
			'css_var'     => '--kit-color-muted',
			'wp_preset'   => '--wp--preset--color--text-muted',
			'default'     => '#636363',
			'type'        => 'color',
			'description' => 'Secondary metadata, dates, breadcrumbs, and subtle captions.',
		),
		'line' => array(
			'label'       => 'Borders & Rules',
			'category'    => 'Colors',
			'css_var'     => '--kit-color-line',
			'wp_preset'   => '--wp--preset--color--border',
			'default'     => '#DFDFDF',
			'type'        => 'color',
			'description' => 'Container borders, table grid lines, and horizontal dividers.',
		),

		// --- 2. Typography ---
		'font_display' => array(
			'label'       => 'Heading / Display Font',
			'category'    => 'Typography',
			'css_var'     => '--kit-font-display',
			'default'     => 'inherit',
			'type'        => 'text',
			'description' => 'Font family stack for page titles, heroes, and Section headlines.',
		),
		'font_body' => array(
			'label'       => 'Body Font',
			'category'    => 'Typography',
			'css_var'     => '--kit-font-body',
			'default'     => 'inherit',
			'type'        => 'text',
			'description' => 'Font family stack for paragraphs, lists, card bodies, and controls.',
		),

		// --- 3. Corner Radii ---
		'radius_control' => array(
			'label'       => 'Controls & Buttons Radius',
			'category'    => 'Shape',
			'css_var'     => '--kit-radius-control',
			'wp_preset'   => '--wp--preset--border-radius--control',
			'default'     => '8px',
			'type'        => 'text',
			'description' => 'Corner radius for inputs, textareas, selects, and standard buttons.',
		),
		'radius_card' => array(
			'label'       => 'Card & Box Radius',
			'category'    => 'Shape',
			'css_var'     => '--kit-radius-card',
			'wp_preset'   => '--wp--preset--border-radius--card',
			'default'     => '12px',
			'type'        => 'text',
			'description' => 'Corner radius for content cards, grid items, and callout boxes.',
		),
		'radius_media' => array(
			'label'       => 'Media & Hero Radius',
			'category'    => 'Shape',
			'css_var'     => '--kit-radius-media',
			'wp_preset'   => '--wp--preset--border-radius--media',
			'default'     => '12px',
			'type'        => 'text',
			'description' => 'Corner radius for featured images, video frames, and hero pictures.',
		),
		'radius_pill' => array(
			'label'       => 'Pills & Badges Radius',
			'category'    => 'Shape',
			'css_var'     => '--kit-radius-pill',
			'wp_preset'   => '--wp--preset--border-radius--pill',
			'default'     => '9999px',
			'type'        => 'text',
			'description' => 'Corner radius for category pills, badge counts, and filter tags.',
		),

		// --- 4. Layout & Spacing ---
		'width_content' => array(
			'label'       => 'Content Max Width',
			'category'    => 'Layout',
			'css_var'     => '--kit-width-content',
			'default'     => '720px',
			'type'        => 'text',
			'description' => 'Maximum line measure for readable editorial text columns.',
		),
		'width_wide' => array(
			'label'       => 'Wide Grid Max Width',
			'category'    => 'Layout',
			'css_var'     => '--kit-width-wide',
			'default'     => '1200px',
			'type'        => 'text',
			'description' => 'Maximum width for headers, navigation, wide cards, and footer grids.',
		),
		'space_section' => array(
			'label'       => 'Section Vertical Padding',
			'category'    => 'Layout',
			'css_var'     => '--kit-space-section-base',
			'default'     => '48px',
			'type'        => 'text',
			'description' => 'Base vertical padding between major page sections.',
		),
	);
}

/**
 * Returns preset tenant configurations (1 Default + 3 Tenants).
 *
 * @return array<string, array{name: string, description: string, tokens: array<string, string>}>
 */
function kits_brand_presets(): array {
	return array(
		'default' => array(
			'name'        => 'Default (Animal Writes / Neutral Kits)',
			'description' => 'Signature warm gold, deep slate and clean rounded cards.',
			'tokens'      => array(
				'accent'         => '#FFBE1A',
				'accent_ink'     => '#0062b8',
				'bg'             => '#F4F4F4',
				'raised'         => '#FFFFFF',
				'ink'            => '#191919',
				'text'           => '#2E2E2E',
				'muted'          => '#636363',
				'line'           => '#DFDFDF',
				'font_display'   => 'inherit',
				'font_body'      => 'inherit',
				'radius_control' => '8px',
				'radius_card'    => '12px',
				'radius_media'   => '12px',
				'radius_pill'    => '9999px',
				'width_content'  => '720px',
				'width_wide'     => '1200px',
				'space_section'  => '48px',
			),
		),
		'tech_modern' => array(
			'name'        => 'Tenant 1: Modern Tech & SaaS',
			'description' => 'Crisp electric blue accent, slate darks, tight 6px corners.',
			'tokens'      => array(
				'accent'         => '#2563EB',
				'accent_ink'     => '#1D4ED8',
				'bg'             => '#F8FAFC',
				'raised'         => '#FFFFFF',
				'ink'            => '#0F172A',
				'text'           => '#334155',
				'muted'          => '#64748B',
				'line'           => '#E2E8F0',
				'font_display'   => "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
				'font_body'      => "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
				'radius_control' => '6px',
				'radius_card'    => '10px',
				'radius_media'   => '10px',
				'radius_pill'    => '9999px',
				'width_content'  => '760px',
				'width_wide'     => '1280px',
				'space_section'  => '56px',
			),
		),
		'editorial_heritage' => array(
			'name'        => 'Tenant 2: Editorial & Publication',
			'description' => 'Warm terracotta, rich warm canvas, classic serif headings, 2px borders.',
			'tokens'      => array(
				'accent'         => '#C2410C',
				'accent_ink'     => '#7C2D12',
				'bg'             => '#FDFBF7',
				'raised'         => '#FFFFFF',
				'ink'            => '#1C1917',
				'text'           => '#44403C',
				'muted'          => '#78716C',
				'line'           => '#E7E5E4',
				'font_display'   => "'Georgia', 'Times New Roman', serif",
				'font_body'      => "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif",
				'radius_control' => '2px',
				'radius_card'    => '4px',
				'radius_media'   => '4px',
				'radius_pill'    => '4px',
				'width_content'  => '680px',
				'width_wide'     => '1160px',
				'space_section'  => '64px',
			),
		),
		'eco_wellness' => array(
			'name'        => 'Tenant 3: Eco, Flora & Community',
			'description' => 'Lush forest emerald, soft mint canvas, organic pill controls and soft 16px cards.',
			'tokens'      => array(
				'accent'         => '#059669',
				'accent_ink'     => '#065F46',
				'bg'             => '#F4FBF7',
				'raised'         => '#FFFFFF',
				'ink'            => '#064E3B',
				'text'           => '#1E3A2F',
				'muted'          => '#4B6F64',
				'line'           => '#D1E7DD',
				'font_display'   => "'Plus Jakarta Sans', system-ui, sans-serif",
				'font_body'      => "system-ui, -apple-system, sans-serif",
				'radius_control' => '9999px',
				'radius_card'    => '16px',
				'radius_media'   => '16px',
				'radius_pill'    => '9999px',
				'width_content'  => '720px',
				'width_wide'     => '1200px',
				'space_section'  => '48px',
			),
		),
	);
}

/**
 * Sanitizes input token values.
 *
 * @param array<string, mixed> $input Raw input.
 * @return array<string, string> Sanitized key => value pairs.
 */
function kits_brand_sanitize_tokens( array $input ): array {
	$defs      = kits_brand_token_definitions();
	$sanitized = array();

	foreach ( $defs as $key => $def ) {
		if ( ! isset( $input[ $key ] ) ) {
			continue;
		}

		$raw = trim( (string) $input[ $key ] );
		if ( '' === $raw ) {
			continue;
		}

		if ( 'color' === $def['type'] ) {
			// Validate 3, 6 or 8-digit hex, or rgb/rgba/hsl.
			if ( preg_match( '/^#([a-f0-9]{3}|[a-f0-9]{6}|[a-f0-9]{8})$/i', $raw ) ||
				preg_match( '/^(rgb|hsl)a?\([^\)]+\)$/i', $raw ) ) {
				$sanitized[ $key ] = $raw;
			}
		} else {
			// CSS unit / font string sanitization: prevent script injection.
			$cleaned = preg_replace( '/[<>{}"\';`]/', '', $raw );
			if ( null !== $cleaned && '' !== trim( $cleaned ) ) {
				$sanitized[ $key ] = trim( $cleaned );
			}
		}
	}

	return $sanitized;
}

/**
 * Builds CSS stylesheet rules from brand token overrides.
 * Overrides both :root --kit-* custom properties and corresponding --wp--preset--* variables.
 *
 * @param array<string, string> $tokens Active token values.
 * @return string CSS text.
 */
function kits_brand_compile_css( array $tokens ): string {
	$defs = kits_brand_token_definitions();
	$css_lines = array();

	foreach ( $defs as $key => $def ) {
		if ( ! isset( $tokens[ $key ] ) ) {
			continue;
		}

		$val = $tokens[ $key ];
		if ( '' === $val || 'inherit' === $val || $val === $def['default'] ) {
			continue; // Do not output redundant defaults
		}

		$css_lines[] = "  {$def['css_var']}: {$val};";
		if ( ! empty( $def['wp_preset'] ) ) {
			$css_lines[] = "  {$def['wp_preset']}: {$val};";
		}
	}

	if ( empty( $css_lines ) ) {
		return '';
	}

	return ":root {\n" . implode( "\n", $css_lines ) . "\n}";
}
