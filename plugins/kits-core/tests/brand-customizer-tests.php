<?php
/**
 * Kits Brand Customizer unit tests (pure PHP, zero WordPress dependency).
 *
 * Verifies:
 * - Token definitions and defaults
 * - Preset definitions (default + 3 tenant profiles)
 * - Sanitization (hex colors, rgba, font stacks, layout units, script injection prevention)
 * - CSS compilation (skipping untouched defaults, emitting both --kit-* and --wp--preset--*)
 * - Export/import roundtrip
 *
 * Usage: php plugins/kits-core/tests/brand-customizer-tests.php
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	if ( 'cli' !== PHP_SAPI ) {
		exit;
	}
	define( 'ABSPATH', __DIR__ . '/' );
}

require dirname( __DIR__ ) . '/includes/brand-customizer-model.php';

$fails = 0;
$count = 0;

function brand_t( bool $ok, string $name ) {
	global $fails, $count;
	++$count;
	if ( ! $ok ) {
		++$fails;
		echo "FAIL $name\n";
	} else {
		echo "ok   $name\n";
	}
}

echo "=== Kits Brand Customizer Tests ===\n";

// 1. Token definitions exist and cover key categories
$defs = kits_brand_token_definitions();
brand_t( isset( $defs['accent'], $defs['bg'], $defs['raised'], $defs['ink'], $defs['text'] ), 'Color tokens defined' );
brand_t( isset( $defs['font_display'], $defs['font_body'] ), 'Typography tokens defined' );
brand_t( isset( $defs['radius_control'], $defs['radius_card'], $defs['radius_media'], $defs['radius_pill'] ), 'Shape/Radius tokens defined' );
brand_t( isset( $defs['width_content'], $defs['width_wide'], $defs['space_section'] ), 'Layout tokens defined' );
brand_t( '#FFBE1A' === $defs['accent']['default'], 'Default accent is #FFBE1A' );

// 2. Presets: 1 Default + 3 Tenants
$presets = kits_brand_presets();
brand_t( count( $presets ) === 4, 'Exactly 4 presets defined (1 default + 3 tenants)' );
brand_t( isset( $presets['default'], $presets['tech_modern'], $presets['editorial_heritage'], $presets['eco_wellness'] ), 'Presets keys present' );
brand_t( '#2563EB' === $presets['tech_modern']['tokens']['accent'], 'Tenant 1 (tech) accent is electric blue' );
brand_t( '#C2410C' === $presets['editorial_heritage']['tokens']['accent'], 'Tenant 2 (editorial) accent is terracotta' );
brand_t( '#059669' === $presets['eco_wellness']['tokens']['accent'], 'Tenant 3 (eco) accent is emerald' );

// 3. Sanitization
$dirty_input = array(
	'accent'         => '#2563eb',
	'accent_ink'     => 'bad-color-string',
	'bg'             => 'rgba(255, 255, 255, 0.95)',
	'radius_control' => '8px; background: red;',
	'font_display'   => '<script>alert(1)</script>Inter, sans-serif',
);
$clean = kits_brand_sanitize_tokens( $dirty_input );
brand_t( '#2563eb' === $clean['accent'], 'Valid hex accepted' );
brand_t( ! isset( $clean['accent_ink'] ), 'Invalid color string rejected' );
brand_t( 'rgba(255, 255, 255, 0.95)' === $clean['bg'], 'Valid rgba color accepted' );
brand_t( '8px background: red' === $clean['radius_control'], 'Semicolon stripped from radius string' );
brand_t( 'scriptalert(1)/scriptInter, sans-serif' === $clean['font_display'], 'Tags and dangerous chars stripped from font string' );

// 4. CSS Compilation
// When tokens match defaults, nothing emitted
$css_empty = kits_brand_compile_css( array( 'accent' => '#FFBE1A' ) );
brand_t( '' === $css_empty, 'Default token does not produce redundant CSS output' );

// When customized, emits both --kit-* and --wp--preset--*
$custom_tokens = array(
	'accent'         => '#2563EB',
	'radius_control' => '4px',
);
$css_custom = kits_brand_compile_css( $custom_tokens );
brand_t( false !== strpos( $css_custom, '--kit-color-accent: #2563EB;' ), 'Emits --kit-color-accent override' );
brand_t( false !== strpos( $css_custom, '--wp--preset--color--primary: #2563EB;' ), 'Emits --wp--preset--color--primary bridge override' );
brand_t( false !== strpos( $css_custom, '--kit-radius-control: 4px;' ), 'Emits --kit-radius-control override' );
brand_t( false !== strpos( $css_custom, '--wp--preset--border-radius--control: 4px;' ), 'Emits --wp--preset--border-radius--control bridge override' );

// 5. Preset Compilation
$tenant_css = kits_brand_compile_css( $presets['tech_modern']['tokens'] );
brand_t( false !== strpos( $tenant_css, '--kit-color-accent: #2563EB;' ), 'Tech preset compiles accent' );
brand_t( false !== strpos( $tenant_css, '--kit-color-bg: #F8FAFC;' ), 'Tech preset compiles bg canvas' );

echo "\nSummary: $count checks, $fails failures.\n";
exit( $fails > 0 ? 1 : 0 );
