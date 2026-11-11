<?php
/**
 * Kits admin app, Cards section (docs/admin-ui-spec.md D1.8): the data the new Theme Options screen needs for it.
 *
 * The values live in the site's own Global Styles (user origin) under settings.custom.kits.card:
 *   padding, gap, stacked.padding, stacked.gap
 * WordPress prints them as --wp--custom--kits--card--padding and so on, and the theme's kit.css reads each with the
 * theme's token as the fallback. An unset key is the theme's value, so nothing changes until someone picks one.
 * The screen saves them itself (src/theme-options/sections/cards.js) through /wp/v2/global-styles/<id>.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
 * Choices (sizes only from tokens): padding from --kit-space-5 up, because every card has at least 24px padding
 * (docs/design-system.md, Layout rules 2; tools/ci/theme_rules_check.py, kind pad); gaps from the spacing scale.
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
	return array(
		'padding'         => array(
			'label'   => __( 'Card padding', 'kits-core' ),
			'help'    => __( 'Space inside every card, around its text (and around a stacked card’s picture). At least 24px.', 'kits-core' ),
			'theme'   => $token( 'kit-space-5' ),
			'choices' => $pick( array( 'kit-space-5', 'kit-space-6', 'kit-space-7' ) ),
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
			'choices' => $pick( array( 'kit-space-5', 'kit-space-6', 'kit-space-7' ) ),
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
 * The saved settings.custom.kits.card of the site's Global Styles, flattened ('stacked.gap' => value); strings only.
 *
 * @param int   $post_id The user Global Styles post.
 * @param array $fields  kits_cards_fields().
 */
function kits_cards_saved( int $post_id, array $fields ): array {
	$post = $post_id ? get_post( $post_id ) : null;
	$json = $post ? json_decode( (string) $post->post_content, true ) : null;
	$card = is_array( $json ) ? ( $json['settings']['custom']['kits']['card'] ?? array() ) : array();
	$out  = array();
	foreach ( array_keys( $fields ) as $path ) {
		$v = $card;
		foreach ( explode( '.', $path ) as $part ) {
			$v = is_array( $v ) ? ( $v[ $part ] ?? null ) : null;
		}
		if ( is_string( $v ) && '' !== $v ) {
			$out[ $path ] = $v;
		}
	}
	return $out;
}

// After admin-app.php's data (priority 10): add window.kitsAdminApp.cards for the Cards section.
add_action(
	'admin_enqueue_scripts',
	function () {
		if ( ! function_exists( 'kits_admin_app_wanted' ) || ! kits_admin_app_wanted() || ! wp_script_is( 'kits-admin-theme-options', 'enqueued' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_theme_options' ) || ! class_exists( 'WP_Theme_JSON_Resolver' ) || ! wp_is_block_theme() ) {
			return;
		}
		$post_id = (int) WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
		if ( ! $post_id ) {
			return;
		}
		$fields = kits_cards_fields();
		$data   = array(
			'postId' => $post_id,
			'saved'  => (object) kits_cards_saved( $post_id, $fields ),
			'fields' => $fields,
			'rules'  => array(
				array( __( 'Side-by-side cards', 'kits-core' ), __( 'Set by the theme’s CSS; not changed here.', 'kits-core' ) ),
				array( __( 'Meta row', 'kits-core' ), __( 'The date comes first; the chips after it wrap out of sight when the row is full (theme CSS).', 'kits-core' ) ),
			),
			'links'  => array(
				'styles' => admin_url( 'site-editor.php?path=/wp_global_styles' ),
				'tokens' => admin_url( 'admin.php?page=kits-system&tab=tokens' ),
			),
		);
		wp_add_inline_script(
			'kits-admin-theme-options',
			'window.kitsAdminApp = window.kitsAdminApp || {}; window.kitsAdminApp.cards = ' . wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP ) . ';',
			'before'
		);
	},
	11
);

// A Global Styles save (this screen, or Styles in the Site Editor) changes the CSS every page prints inline: empty the
// page cache so the change shows. LiteSpeed Cache's own hook; nothing happens when it is not active.
add_action(
	'rest_after_insert_wp_global_styles',
	function () {
		do_action( 'litespeed_purge_all' );
	}
);
