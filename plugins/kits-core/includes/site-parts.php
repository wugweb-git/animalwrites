<?php
/**
 * Site parts switched in Theme Options: the phone help bar and the announcement bar.
 * At the defaults (bar off, announcement on) nothing here changes the page.
 *
 * @package Kits_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Phone help bar at the foot of the page (phones only; kit.css and kit.js style and move it).
 * Off unless switched on with both a link and a label. Never on campaign items: they have their own bar.
 * An item can turn it off through the kits_option filter.
 */
add_action(
	'wp_footer',
	function () {
		if ( ! function_exists( 'kits_option' ) || is_admin() || is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		if ( is_singular( 'aw_campaign' ) || 'on' !== kits_option( 'sticky_bar' ) ) {
			return;
		}
		$href  = trim( (string) kits_option( 'sticky_href' ) );
		$label = trim( (string) kits_option( 'sticky_label' ) );
		$ok    = array( 'http', 'https', 'tel', 'mailto' );
		if ( '' === $href || '' === $label || '' === esc_url( $href, $ok ) ) {
			return;
		}
		echo '<div class="kit-sticky-help"><a class="kit-sh-primary" href="' . esc_url( $href, $ok ) . '"><span class="kit-ico kit-ico--helpline" aria-hidden="true"></span>' . esc_html( $label ) . '</a></div>';
	},
	5
);

/**
 * Announcement bar: when switched off, the header's .kit-announce group is not printed.
 */
add_filter(
	'render_block_core/group',
	function ( $content, $block ) {
		if ( empty( $block['attrs']['className'] ) || is_admin() ) {
			return $content;
		}
		if ( ! in_array( 'kit-announce', preg_split( '/\s+/', (string) $block['attrs']['className'] ), true ) ) {
			return $content;
		}
		return function_exists( 'kits_option' ) && 'off' === kits_option( 'announce' ) ? '' : $content;
	},
	10,
	2
);
