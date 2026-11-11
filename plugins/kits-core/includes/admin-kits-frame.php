<?php
/**
 * Kits → Home, Settings, Tools and Help on the plugin screens' look (trial, &ui=next): admin.php?page=kits&ui=next,
 * admin.php?page=kits-settings&ui=next, admin.php?page=kits-tools&ui=next, admin.php?page=kits-help&ui=next.
 * Theme Options' own screen (includes/admin-app.php) uses the same header, chips and cards.
 *
 * The same frame as the Kits plugins (docs/kits-plugin-ui.md): the app header with the Kits icon, name and version and
 * the five Kits tabs (yellow bar on the open one), then each section as a card. The cards hold today's sections, printed
 * by the same functions, so every form, button and link works as on the current screens. Without &ui=next the current
 * screens show; they stay the default until the owner switches.
 *
 * The frame is printed by PHP with the shell's classes (only the kits-shell stylesheet is loaded; no script is needed).
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The screens drawn in the frame: slug => capability (the menu's). */
function kits_frame_screens(): array {
	return array(
		KITS_MENU       => 'manage_options',
		'kits-settings' => 'manage_options',
		'kits-tools'    => 'edit_theme_options',
		'kits-help'     => 'manage_options',
	);
}

/** This request asks for a Kits screen in the frame. */
function kits_frame_wanted(): bool {
	$page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which screen to show; nothing is saved here.
	return isset( kits_frame_screens()[ $page ] ) && kits_shell_ui_next();
}

/**
 * The frame's stylesheet is loaded on this request. Set on admin_enqueue_scripts.
 *
 * @param bool|null $set Set the flag.
 */
function kits_frame_ready( ?bool $set = null ): bool {
	static $ready = false;
	if ( null !== $set ) {
		$ready = $set;
	}
	return $ready;
}

/**
 * The five Kits tabs for a screen on the new look. The new look is the default, so the tabs are the plain addresses.
 *
 * @param string $current The open screen's slug.
 * @return array [ { slug, label, url, current } ].
 */
function kits_frame_tabs( string $current ): array {
	$tabs = array();
	foreach ( kits_admin_tabs_list() as $slug => $t ) {
		if ( ! current_user_can( (string) ( $t[2] ?? 'manage_options' ) ) ) {
			continue;
		}
		$url    = admin_url( (string) $t[1] );
		$tabs[] = array(
			'slug'    => (string) $slug,
			'label'   => (string) $t[0],
			'url'     => $url,
			'current' => $current === (string) $slug,
		);
	}
	return $tabs;
}

/** The Kits icon (the theme's, as on today's Kits screens), name and version the header shows. */
function kits_frame_brand(): array {
	$theme = wp_get_theme( get_template() );
	$icon  = kits_product_icon( 'Kits' );
	return array(
		'icon'    => '' !== $icon ? $icon : plugins_url( 'assets/icon.svg', KITS_CORE_DIR . 'kits-core.php' ),
		'name'    => __( 'Kits', 'kits-core' ),
		'version' => (string) $theme->get( 'Version' ),
	);
}

add_action(
	'admin_enqueue_scripts',
	function () {
		if ( ! kits_frame_wanted() ) {
			return;
		}
		$page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which screen to show; nothing is saved here.
		if ( ! current_user_can( kits_frame_screens()[ $page ] ) || ! wp_style_is( 'kits-shell', 'registered' ) ) {
			return; // No shell: the current screen shows.
		}
		wp_enqueue_style( 'kits-shell' );
		add_filter( 'admin_body_class', 'kits_shell_body_class' );
		kits_frame_ready( true );
	}
);

/**
 * Open the frame: the app header, then the body the cards go in. kits_frame_close() ends it.
 *
 * @param string $current The open screen's slug.
 * @param string $title   The screen's name (a screen-reader heading; the tabs show it).
 * @param string $intro   One line under the header (plain text).
 */
function kits_frame_open( string $current, string $title, string $intro = '' ): void {
	$brand = kits_frame_brand();
	// Notices from other plugins go after .wp-header-end, so they stay out of the app.
	echo '<div class="wrap"><h1 class="screen-reader-text">' . esc_html( $title ) . '</h1><hr class="wp-header-end">';
	settings_errors();
	echo '<div class="kits-shell kits-shell-frame"><header class="kits-shell-header"><div class="kits-shell-header__brand">';
	echo '<img class="kits-shell-header__icon" src="' . esc_url( $brand['icon'], array( 'https', 'http', 'data' ) ) . '" alt="" width="32" height="32">';
	echo '<span class="kits-shell-header__name">' . esc_html( $brand['name'] ) . '</span>';
	if ( '' !== $brand['version'] ) {
		echo '<span class="kits-shell-header__version">' . esc_html( $brand['version'] ) . '</span>';
	}
	echo '</div><nav class="kits-shell-header__tabs" aria-label="' . esc_attr__( 'Screens', 'kits-core' ) . '"><ul>';
	foreach ( kits_frame_tabs( $current ) as $t ) {
		echo '<li><a href="' . esc_url( $t['url'] ) . '" class="kits-shell-tab' . ( $t['current'] ? ' is-current' : '' ) . '"' . ( $t['current'] ? ' aria-current="page"' : '' ) . '>' . esc_html( $t['label'] ) . '</a></li>';
	}
	echo '</ul></nav><div class="kits-shell-header__aside"><a href="' . esc_url( add_query_arg( 'ui', 'classic' ) ) . '">' . esc_html__( 'Open the current screen', 'kits-core' ) . '</a></div></header>';
	echo '<div class="kits-shell__body">';
	if ( '' !== $intro ) {
		echo '<p class="kits-shell-frame__intro">' . esc_html( $intro ) . '</p>';
	}
}

/** Close what kits_frame_open() opened. */
function kits_frame_close(): void {
	echo '</div></div></div>';
}

/**
 * One section as a card. Its content is printed by today's function, inside the card's body.
 *
 * @param string   $id     Anchor (the current screen's section id, so older #links still land).
 * @param string   $title  Heading.
 * @param callable $render Prints the content.
 */
function kits_frame_card( string $id, string $title, callable $render ): void {
	echo '<section class="kits-shell-card" id="' . esc_attr( $id ) . '"><div class="kits-shell-card__head"><div><h2 class="kits-shell-card__title">' . esc_html( $title ) . '</h2></div></div>';
	echo '<div class="kits-shell-card__body kits-shell-prose">';
	call_user_func( $render );
	echo '</div></section>';
}
