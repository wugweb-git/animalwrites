<?php
/**
 * Kits → Home, Settings, Tools and Help on the plugin screens' look: admin.php?page=kits, admin.php?page=kits-settings,
 * admin.php?page=kits-tools, admin.php?page=kits-help. Theme Options' own screen (includes/admin-app.php) uses the same
 * header, chips and cards.
 *
 * The same frame as the Kits plugins (docs/kits-plugin-ui.md): the app header with the Kits icon, name and version and
 * the five Kits tabs (yellow bar on the open one), then each section as a card, printed by the section's own function,
 * so every form, button and link works as it is.
 *
 * The frame is printed by PHP with the shell's classes (only the kits-shell stylesheet is loaded; no script is needed).
 * Without the shell's build (build/shell/) the screen shows one notice instead (kits_frame_missing()).
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The joined screens (kits_frame_extra_screens filter), read once per request.
 *
 * @return array slug => [ capability, tab ].
 */
function kits_frame_extra_all(): array {
	static $all = null;
	if ( null === $all ) {
		$all = (array) apply_filters( 'kits_frame_extra_screens', array() );
	}
	return $all;
}

/**
 * The screens drawn in the frame: slug => capability (the menu's).
 *
 * Screens that sit under other menus (Media, Campaigns, Tools) join through the kits_frame_extra_screens filter, see
 * kits_frame_screen(). The five Kits screens come first and cannot be replaced.
 */
function kits_frame_screens(): array {
	$extra = kits_frame_extra_all();
	$caps  = array(
		KITS_MENU       => KITS_CAP,
		'kits-settings' => 'manage_options',
		'kits-tools'    => 'manage_options',
		'kits-help'     => KITS_CAP,
	);
	foreach ( $extra as $slug => $def ) {
		if ( is_string( $slug ) && ! isset( $caps[ $slug ] ) && is_array( $def ) && isset( $def[0] ) ) {
			$caps[ $slug ] = (string) $def[0];
		}
	}
	return $caps;
}

/**
 * The Kits tab a joined screen sits under (its second value in kits_frame_extra_screens), or '' for none.
 *
 * @param string $slug The screen's page= value.
 */
function kits_frame_extra_tab( string $slug ): string {
	$extra = kits_frame_extra_all();
	return isset( $extra[ $slug ][1] ) ? (string) $extra[ $slug ][1] : '';
}

/**
 * A classic screen printed in the Kits frame: the app header and tabs, then the screen's body as one card. Same
 * address, capability, forms and nonces as before; only the presentation moves. Without the shell's build the body is
 * printed in a plain wrap with its heading, so the screen never goes blank.
 *
 * The slug must be registered with the kits_frame_extra_screens filter (slug => [ capability, tab ]) so the frame's
 * stylesheet loads.
 *
 * @param string   $slug  The screen's page= value.
 * @param string   $title The screen's name (card heading).
 * @param callable $body  Prints the body (no wrap, no h1).
 */
function kits_frame_screen( string $slug, string $title, callable $body ): void {
	if ( ! kits_frame_ready() ) {
		echo '<div class="wrap"><h1>' . esc_html( $title ) . '</h1>';
		call_user_func( $body );
		echo '</div>';
		return;
	}
	kits_frame_open( kits_frame_extra_tab( $slug ), $title );
	kits_frame_card( $slug, $title, $body );
	kits_frame_close();
}

/** This request asks for a Kits screen in the frame. */
function kits_frame_wanted(): bool {
	$page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which screen to show; nothing is saved here.
	return isset( kits_frame_screens()[ $page ] );
}

/**
 * The screen when the shell's build is missing: its heading and one notice.
 *
 * @param string $title The screen's name.
 */
function kits_frame_missing( string $title ): void {
	echo '<div class="wrap"><h1>' . esc_html( $title ) . '</h1>';
	wp_admin_notice( esc_html__( 'Kits Core needs its build (build/shell/) to show this screen.', 'kits-core' ), array( 'type' => 'error' ) );
	echo '</div>';
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
			return; // No shell: the screen shows kits_frame_missing().
		}
		wp_enqueue_style( 'kits-shell' );
		add_filter( 'admin_body_class', 'kits_shell_body_class' );
		kits_shell_screen_plain();
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
	echo '</ul></nav></header>';
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
 * @param array    $opts   Optional card options: 'class' (extra CSS classes), 'badge' (badge string), 'hide_header' (bool).
 */
function kits_frame_card( string $id, string $title, callable $render, array $opts = array() ): void {
	$classes = array( 'kits-shell-card' );
	if ( ! empty( $opts['class'] ) ) {
		$classes[] = sanitize_html_class( $opts['class'] );
	}
	$card_class = implode( ' ', $classes );
	echo '<section class="' . esc_attr( $card_class ) . '" id="' . esc_attr( $id ) . '">';
	if ( empty( $opts['hide_header'] ) ) {
		echo '<div class="kits-shell-card__head"><div><h2 class="kits-shell-card__title">' . esc_html( $title ) . '</h2></div>';
		if ( ! empty( $opts['badge'] ) ) {
			echo '<div class="kits-shell-card__badge"><span class="kits-badge">' . esc_html( $opts['badge'] ) . '</span></div>';
		}
		echo '</div>';
	}
	echo '<div class="kits-shell-card__body kits-shell-prose">';
	call_user_func( $render );
	echo '</div></section>';
}

/**
 * One KPI/Metric card (Site Kit / FluentCRM style).
 *
 * @param string $label Label / metric name.
 * @param string $val   Primary formatted value (e.g. "172", "99.4%").
 * @param array  $opts  Optional settings: 'unit', 'trend' (string like "+14%"), 'trend_dir' ('up'|'down'|'neutral'), 'badge', 'sparkline' (array of numbers 0..100).
 */
function kits_frame_kpi_card( string $label, string $val, array $opts = array() ): string {
	$out = '<div class="kits-kpi-card">';
	$out .= '<div class="kits-kpi-card__head"><h3 class="kits-kpi-card__label">' . esc_html( $label ) . '</h3>';
	if ( ! empty( $opts['badge'] ) ) {
		$out .= '<span class="kits-badge">' . esc_html( $opts['badge'] ) . '</span>';
	}
	$out .= '</div>';
	$out .= '<div class="kits-kpi-card__body">';
	$out .= '<span class="kits-kpi-card__val">' . esc_html( $val ) . '</span>';
	if ( ! empty( $opts['unit'] ) ) {
		$out .= '<span class="kits-kpi-card__unit">' . esc_html( $opts['unit'] ) . '</span>';
	}
	if ( ! empty( $opts['trend'] ) ) {
		$dir = in_array( $opts['trend_dir'] ?? '', array( 'up', 'down', 'neutral' ), true ) ? (string) $opts['trend_dir'] : 'up';
		$out .= '<span class="kits-kpi-card__trend is-' . esc_attr( $dir ) . '">' . esc_html( $opts['trend'] ) . '</span>';
	}
	$out .= '</div>';
	if ( ! empty( $opts['sparkline'] ) && is_array( $opts['sparkline'] ) ) {
		$pts = $opts['sparkline'];
		$max = max( 1, ...$pts );
		$count = count( $pts );
		$w = $count > 1 ? 100 / ( $count - 1 ) : 100;
		$d = '';
		foreach ( $pts as $i => $p ) {
			$x = round( $i * $w, 1 );
			$y = round( 32 - ( ( (float) $p / $max ) * 28 ), 1 );
			$d .= ( 0 === $i ? "M $x $y" : " L $x $y" );
		}
		$out .= '<div class="kits-kpi-card__sparkline"><svg viewBox="0 0 100 36" preserveAspectRatio="none"><path d="' . esc_attr( $d ) . '" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" opacity="0.65" /></svg></div>';
	}
	$out .= '</div>';
	return $out;
}

/**
 * A responsive grid of KPI cards.
 *
 * @param array $cards Array of html card strings or card options arrays.
 */
function kits_frame_kpi_grid( array $cards ): void {
	echo '<div class="kits-kpi-grid">';
	foreach ( $cards as $c ) {
		if ( is_string( $c ) ) {
			echo $c; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- card markup from kits_frame_kpi_card()
		} elseif ( is_array( $c ) && isset( $c['label'], $c['val'] ) ) {
			echo kits_frame_kpi_card( (string) $c['label'], (string) $c['val'], (array) ( $c['opts'] ?? array() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
	echo '</div>';
}

/**
 * Structured Action List (replacing raw button piles).
 *
 * @param array $rows Array of [ 'title' => ..., 'desc' => ..., 'status' => ..., 'button' => ..., 'icon' => ... ].
 */
function kits_frame_action_list( array $rows ): void {
	echo '<div class="kits-action-list">';
	foreach ( $rows as $r ) {
		echo '<div class="kits-action-row">';
		echo '<div class="kits-action-row__main">';
		if ( ! empty( $r['icon'] ) ) {
			echo '<div class="kits-action-row__icon">' . esc_html( $r['icon'] ) . '</div>';
		}
		echo '<div class="kits-action-row__info">';
		echo '<h4 class="kits-action-row__title">' . esc_html( $r['title'] ?? '' ) . '</h4>';
		if ( ! empty( $r['desc'] ) ) {
			echo '<p class="kits-action-row__desc">' . esc_html( $r['desc'] ) . '</p>';
		}
		echo '</div></div>';
		echo '<div class="kits-action-row__meta">';
		if ( ! empty( $r['status'] ) ) {
			echo '<span class="kits-action-row__status">' . esc_html( $r['status'] ) . '</span>';
		}
		if ( ! empty( $r['button'] ) ) {
			echo '<div class="kits-action-row__action">' . $r['button'] . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- button element
		}
		echo '</div></div>';
	}
	echo '</div>';
}
