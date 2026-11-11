<?php
/**
 * Kits admin app, Cards section (docs/admin-ui-spec.md D1.8): the field data the new Theme Options screen needs.
 *
 * The values live in the site's own Global Styles (user origin) under settings.custom.kits.card:
 *   padding, gap, stacked.padding, stacked.gap
 * WordPress prints them as --wp--custom--kits--card--padding and so on, and the theme's kit.css reads each with the
 * theme's token as the fallback. An unset key is the theme's value. The screen reads and saves them through the
 * app's one Global Styles store (admin-app-global-styles.php, src/theme-options/global-styles.js).
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'kits_admin_app_data', 'kits_admin_app_cards_data' );

/**
 * The spacing tokens a card value can be (kit-tokens.css :root, --kit-space-1 to --kit-space-7), with their size.
 *
 * @return array [ 'kit-space-N' => '16px' ].
 */
function kits_cards_space_tokens(): array {
	$vals = function_exists( 'kits_token_values' ) ? kits_token_values() : array();
	$out  = array();
	for ( $i = 1; $i <= 7; $i++ ) {
		$name         = 'kit-space-' . $i;
		$out[ $name ] = isset( $vals[ $name ] ) && function_exists( 'kits_token_resolve' ) ? kits_token_resolve( $name, $vals ) : '';
	}
	return $out;
}

/**
 * The Cards fields: label, help, the theme's value (shown as "Theme value") and the tokens it may pick.
 *
 * Sizes only from tokens. Padding: 16px (--kit-space-4) by default and at least that (owner, 11 Nov 2026;
 * docs/design-system.md, Layout rules 2). Gaps from the spacing scale.
 *
 * @return array Keyed by the path under settings.custom.kits.card ('stacked.gap' = stacked → gap).
 */
function kits_cards_fields(): array {
	$space = kits_cards_space_tokens();
	$token = function ( string $name ) use ( $space ): string {
		return '--' . $name . ( '' !== ( $space[ $name ] ?? '' ) ? ' (' . $space[ $name ] . ')' : '' );
	};
	$pick  = function ( array $names ) use ( $token ): array {
		$out = array();
		foreach ( $names as $n ) {
			$out[] = array(
				'value' => 'var(--' . $n . ')',
				'label' => $token( $n ),
			);
		}
		return $out;
	};
	$pads  = array( 'kit-space-4', 'kit-space-5', 'kit-space-6', 'kit-space-7' );
	return array(
		'padding'         => array(
			'label'   => __( 'Card padding', 'kits-core' ),
			'help'    => __( 'Space inside every card, around its text (and around a stacked card’s picture).', 'kits-core' ),
			'theme'   => $token( 'kit-space-4' ),
			'choices' => $pick( $pads ),
		),
		'gap'             => array(
			'label'   => __( 'Card gap', 'kits-core' ),
			'help'    => __( 'Space between the cards of a list.', 'kits-core' ),
			/* translators: %s: token name. */
			'theme'   => sprintf( __( '%s, which grows with the screen width', 'kits-core' ), '--kit-space-grid' ),
			'choices' => $pick( array( 'kit-space-4', 'kit-space-5', 'kit-space-6', 'kit-space-7' ) ),
		),
		'stacked.padding' => array(
			'label'   => __( 'Stacked large card: padding', 'kits-core' ),
			'help'    => __( 'The wide card with the picture on the left (kit-card--stacked-lg).', 'kits-core' ),
			'theme'   => __( 'the Card padding', 'kits-core' ),
			'choices' => $pick( $pads ),
		),
		'stacked.gap'     => array(
			'label'   => __( 'Stacked large card: gap', 'kits-core' ),
			'help'    => __( 'Space between the picture and the text.', 'kits-core' ),
			/* translators: %s: token name. */
			'theme'   => sprintf( __( '%s, which grows with the screen width on some sites', 'kits-core' ), '--kit-card-stacked-gap' ),
			'choices' => $pick( array( 'kit-space-3', 'kit-space-4', 'kit-space-5', 'kit-space-6' ) ),
		),
	);
}

/**
 * Adds cards: { fields, rules, links } to window.kitsAdminApp.
 *
 * @param array $data window.kitsAdminApp.
 */
function kits_admin_app_cards_data( array $data ): array {
	$data['cards'] = array(
		'fields' => kits_cards_fields(),
		'rules'  => array(
			array( __( 'Side-by-side cards', 'kits-core' ), __( 'Set by the theme’s CSS; not changed here.', 'kits-core' ) ),
			array( __( 'Meta row', 'kits-core' ), __( 'The date comes first; the row wraps to a second line when it is full (theme CSS).', 'kits-core' ) ),
		),
		'links'  => array(
			'tokens' => admin_url( 'admin.php?page=kits-system&tab=tokens' ),
		),
	);
	return $data;
}
