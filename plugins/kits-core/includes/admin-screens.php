<?php
/**
 * The Kits admin, laid out like a marketplace theme's hub: one menu second under Dashboard, the same tabs on every Kits
 * screen, and one screen per job. Core wp-admin parts only (nav tabs, form tables, postboxes, the Settings API, notices);
 * no admin stylesheet.
 *
 * Kits → Welcome (system status), Plugins, Import / Export, Fonts (core Font Library), Core Settings, Theme Options,
 * Design system (system-screen.php), Docs (what lives where), Preview (preview.php).
 *
 * Theme Options keep their values where they always were: kits_theme_options (theme-options.php) and kits_page_defaults
 * (page-options.php), so the settings file, kits_option() and kits_page_option() read them as before. Both options save
 * by merging: a section's form changes only its own fields.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Kits screens as tabs, before they are put in order: slug => [tab label, address relative to wp-admin, capability].
 * Kits' own in tab order; a site adds its own through the kits_admin_tabs filter.
 */
function kits_admin_tabs_unsorted(): array {
	return (array) apply_filters(
		'kits_admin_tabs',
		array(
			KITS_MENU            => array( 'Welcome', 'admin.php?page=' . KITS_MENU, 'manage_options' ),
			'kits-plugins'       => array( 'Plugins', 'admin.php?page=kits-plugins', 'manage_options' ),
			'kits-transfer'      => array( 'Import / Export', 'admin.php?page=kits-transfer', 'edit_theme_options' ),
			'font-library.php'   => array( 'Fonts', 'font-library.php', 'edit_theme_options' ),
			'kits-core-settings' => array( 'Core Settings', 'admin.php?page=kits-core-settings', 'manage_options' ),
			'kits-options'       => array( 'Theme Options', 'admin.php?page=kits-options', 'manage_options' ),
			'kits-system'        => array( 'Design system', 'admin.php?page=kits-system', 'manage_options' ),
			'kits-docs'          => array( 'Docs', 'admin.php?page=kits-docs', 'manage_options' ),
			'kits-preview'       => array( 'Preview', 'admin.php?page=kits-preview', 'manage_options' ),
		)
	);
}

/**
 * One order for the Kits menu and the Kits tabs: screen slugs (the page= value, or the file of a core screen such as
 * font-library.php). Kits' own screens in tab order, then the tabs a site adds (in the order kits_admin_tabs gives
 * them). Screens in neither list follow in the order they were added to the menu.
 */
function kits_menu_order(): array {
	/**
	 * Filters the order of the Kits screens in the Kits menu and the tabs, e.g. to place a site's screen between two
	 * of Kits' own.
	 *
	 * @param string[] $slugs Screen slugs in order.
	 */
	$order = (array) apply_filters( 'kits_menu_order', array_keys( kits_admin_tabs_unsorted() ) );
	return array_values( array_unique( array_map( 'strval', $order ) ) );
}

/**
 * Put items in the Kits order: those whose slug is in kits_menu_order() first, in that order, then the rest as they
 * came.
 *
 * @param array    $items   Items.
 * @param callable $slug_of Item => its slug.
 * @return array The items in order (keys kept).
 */
function kits_menu_sort( array $items, callable $slug_of ): array {
	$pos  = array_flip( kits_menu_order() );
	$rank = array();
	$i    = 0;
	foreach ( $items as $key => $item ) {
		$slug         = (string) $slug_of( $item, $key );
		$rank[ $key ] = array( $pos[ $slug ] ?? PHP_INT_MAX, $i++ );
	}
	uksort( $items, fn( $a, $b ) => $rank[ $a ] <=> $rank[ $b ] );
	return $items;
}

/** The Kits screens in tab order (kits_menu_order()): slug => [tab label, address relative to wp-admin, capability]. */
function kits_admin_tabs_list(): array {
	return kits_menu_sort( kits_admin_tabs_unsorted(), fn( $t, $slug ) => $slug );
}

// The Kits menu in the same order as the tabs, once every plugin has added its screens.
add_action(
	'admin_menu',
	function () {
		global $submenu;
		if ( empty( $submenu[ KITS_MENU ] ) || ! is_array( $submenu[ KITS_MENU ] ) ) {
			return;
		}
		$submenu[ KITS_MENU ] = array_values( kits_menu_sort( $submenu[ KITS_MENU ], fn( $item ) => $item[2] ?? '' ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- reorders the Kits submenu only.
	},
	1000 // After every plugin has added its screens.
);

/**
 * Screens that other plugins add under Kits → Plugins (not as top tabs): slug => [name, address relative to wp-admin,
 * capability, one line on what it is]. On those screens the Plugins tab is the active one.
 */
function kits_plugin_screens(): array {
	$list = array();
	foreach ( (array) apply_filters( 'kits_plugin_screens', array() ) as $slug => $s ) {
		if ( is_array( $s ) && isset( $s[0], $s[1], $s[2] ) ) {
			$list[ (string) $slug ] = array( (string) $s[0], (string) $s[1], (string) $s[2], (string) ( $s[3] ?? '' ) );
		}
	}
	return $list;
}

/**
 * The tabs every Kits screen starts with.
 *
 * @param string $current The screen's slug.
 */
function kits_admin_tabs( string $current ): void {
	echo '<nav class="nav-tab-wrapper wp-clearfix" aria-label="' . esc_attr( 'Kits screens' ) . '">';
	foreach ( kits_admin_tabs_list() as $slug => $t ) {
		if ( ! current_user_can( $t[2] ) ) {
			continue;
		}
		$on = $slug === $current || ( 'kits-plugins' === $slug && isset( kits_plugin_screens()[ $current ] ) );
		echo '<a class="nav-tab' . ( $on ? ' nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( $t[1] ) ) . '"' . ( $on ? ' aria-current="page"' : '' ) . '>' . esc_html( $t[0] ) . '</a>';
	}
	echo '</nav>';
}

/**
 * A screen's heading, intro and tabs.
 *
 * @param string $slug  The screen's slug.
 * @param string $title Heading.
 * @param string $intro One line under it (plain text).
 */
function kits_admin_head( string $slug, string $title, string $intro = '' ): void {
	$theme = wp_get_theme( get_template() );
	echo '<div class="wrap"><h1>' . esc_html( $title ) . '</h1>';
	if ( '' !== $intro ) {
		echo '<p>' . esc_html( $intro ) . '</p>';
	}
	echo '<p class="description">' . esc_html( $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) ) . '</p>';
	kits_admin_tabs( $slug );
	settings_errors();
}

add_action(
	'admin_menu',
	function () {
		add_submenu_page( KITS_MENU, 'Plugins', 'Plugins', 'manage_options', 'kits-plugins', 'kits_plugins_page' );
		add_submenu_page( KITS_MENU, 'Import / Export', 'Import / Export', 'edit_theme_options', 'kits-transfer', 'kits_transfer_page' );
		add_submenu_page( KITS_MENU, 'Fonts', 'Fonts', 'edit_theme_options', 'font-library.php' );
		add_submenu_page( KITS_MENU, 'Core Settings', 'Core Settings', 'manage_options', 'kits-core-settings', 'kits_core_settings_page' );
		add_submenu_page( KITS_MENU, 'Theme Options', 'Theme Options', 'manage_options', 'kits-options', 'kits_options_page' );
	},
	9 // After Welcome, before Design system, Docs and Preview.
);

add_action(
	'admin_menu',
	function () {
		add_submenu_page( KITS_MENU, 'Docs', 'Docs', 'manage_options', 'kits-docs', 'kits_docs_page' );
	},
	11 // After Design system.
);

/* ---------- Welcome: system status ---------- */

/**
 * A size such as 256M in bytes.
 *
 * @param string $v Size as PHP writes it.
 */
function kits_bytes( string $v ): int {
	return function_exists( 'wp_convert_hr_to_bytes' ) ? (int) wp_convert_hr_to_bytes( $v ) : (int) $v;
}

/**
 * One status value: plain, or red with what to do.
 *
 * @param string $value The value.
 * @param string $fix   Empty when it is fine; else what to do.
 */
function kits_status_cell( string $value, string $fix = '' ): string {
	if ( '' === $fix ) {
		return esc_html( $value );
	}
	return '<span class="error-message"><span class="dashicons dashicons-warning" aria-hidden="true"></span> ' . esc_html( $value ) . '</span><br><span class="description">' . esc_html( $fix ) . '</span>';
}

/** Rows of the status table: [label, HTML value]. */
function kits_status_rows(): array {
	global $wp_version;
	$master = wp_get_theme( get_template() );
	$active = wp_get_theme();
	$rows   = array();

	// Theme, its update and change log.
	$update = get_site_transient( 'update_themes' );
	$new    = is_object( $update ) && isset( $update->response[ get_template() ]['new_version'] ) ? (string) $update->response[ get_template() ]['new_version'] : '';
	$ver    = esc_html( $master->get( 'Version' ) );
	// The last answer from the update file; read from cache only, so this page asks nothing.
	$last = function_exists( 'kits_update_url' ) && '' !== kits_update_url() ? get_site_transient( 'kits_update_release' ) : false;
	$seen = is_array( $last ) && ! empty( $last['version'] ) ? (string) $last['version'] : '';
	if ( '' === $new && '' !== $seen && version_compare( $seen, (string) $master->get( 'Version' ), '>' ) ) {
		$new = $seen;
	}
	if ( '' !== $new ) {
		$ver .= ' <a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">' . esc_html( 'Update to ' . $new ) . '</a>';
	} elseif ( '' !== $seen ) {
		$ver .= ' ' . esc_html( '(up to date)' );
	} else {
		$ver .= ' ' . esc_html( '(update check off)' );
	}
	if ( function_exists( 'kits_changelog_url' ) && kits_changelog_url() ) {
		$ver .= ' · <a href="' . esc_url( kits_changelog_url() ) . '">' . esc_html( 'Change log' ) . '</a>';
	}
	$rows[] = array( 'Theme version', $ver );

	// Kits Core: on its own, or loaded by another plugin.
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$own    = is_plugin_active( 'kits-core/kits-core.php' );
	$core   = get_file_data( KITS_CORE_DIR . 'kits-core.php', array( 'Version' => 'Version' ) );
	$rows[] = array( 'Kits Core', esc_html( 'Active, ' . ( $core['Version'] ? $core['Version'] : '' ) . ( $own ? '' : ' (loaded by another plugin)' ) ) );

	// Child theme.
	if ( $active->parent() ) {
		$child = 'Active: ' . $active->get( 'Name' ) . ' ' . $active->get( 'Version' ) . '.';
	} else {
		$kids  = array_filter( wp_get_themes(), fn( $t ) => $t->get_template() === get_template() && $t->get_stylesheet() !== get_template() );
		$child = 'None: ' . $master->get( 'Name' ) . ' runs on its own.' . ( $kids ? ' Installed but not active: ' . implode( ', ', array_map( fn( $t ) => $t->get( 'Name' ), $kids ) ) . '.' : '' );
	}
	$rows[] = array( 'Child theme', esc_html( $child ) );

	// WordPress and PHP against the theme's needs.
	$need_wp  = (string) $master->get( 'RequiresWP' );
	$need_php = (string) $master->get( 'RequiresPHP' );
	$rows[]   = array( 'WordPress', kits_status_cell( $wp_version, $need_wp && version_compare( $wp_version, $need_wp, '<' ) ? 'The theme needs WordPress ' . $need_wp . ' or later.' : '' ) );
	$rows[]   = array( 'PHP', kits_status_cell( PHP_VERSION, $need_php && version_compare( PHP_VERSION, $need_php, '<' ) ? 'The theme needs PHP ' . $need_php . ' or later.' : ( version_compare( PHP_VERSION, '8.1', '<' ) ? 'Works; PHP 8.3 is recommended.' : '' ) ) );

	// Limits.
	$mem    = defined( 'WP_MEMORY_LIMIT' ) ? (string) WP_MEMORY_LIMIT : (string) ini_get( 'memory_limit' );
	$rows[] = array( 'WordPress memory limit', kits_status_cell( $mem, kits_bytes( $mem ) > 0 && kits_bytes( $mem ) < 256 * MB_IN_BYTES ? 'Set WP_MEMORY_LIMIT to 256M or more in wp-config.php.' : '' ) );
	$php    = (string) ini_get( 'memory_limit' );
	$rows[] = array( 'PHP memory limit', kits_status_cell( $php, '-1' !== $php && kits_bytes( $php ) < 256 * MB_IN_BYTES ? 'Ask the host for 256M or more.' : '' ) );
	$vars   = (int) ini_get( 'max_input_vars' );
	$rows[] = array( 'PHP max input vars', kits_status_cell( (string) $vars, $vars && $vars < 1000 ? 'Theme Options save in sections, so 1000 is enough; below that a section may not save.' : '' ) );
	$time   = (int) ini_get( 'max_execution_time' );
	$rows[] = array( 'PHP time limit', kits_status_cell( $time ? $time . ' s' : 'none', $time && $time < 30 ? 'Imports may stop early; ask the host for 30 s or more.' : '' ) );
	$rows[] = array( 'Largest upload', esc_html( size_format( wp_max_upload_size() ) ) );

	// Site Health's last run.
	$health = json_decode( (string) get_transient( 'health-check-site-status-result' ), true );
	if ( is_array( $health ) ) {
		$crit   = (int) ( $health['critical'] ?? 0 );
		$text   = sprintf( '%d good, %d recommended, %d critical', (int) ( $health['good'] ?? 0 ), (int) ( $health['recommended'] ?? 0 ), $crit );
		$rows[] = array( 'Site Health', kits_status_cell( $text, $crit ? 'Open Site Health to see the critical items.' : '' ) . ' <a href="' . esc_url( admin_url( 'site-health.php' ) ) . '">' . esc_html( 'Site Health' ) . '</a>' );
	} else {
		$rows[] = array( 'Site Health', esc_html( 'Not run yet. ' ) . '<a href="' . esc_url( admin_url( 'site-health.php' ) ) . '">' . esc_html( 'Run it' ) . '</a>' );
	}
	return (array) apply_filters( 'kits_status_rows', $rows );
}

/** Kits → Welcome. */
function kits_welcome_page() {
	kits_admin_head( KITS_MENU, 'Welcome to Kits', 'Kits by Wugweb is the site\'s design system: the theme draws every page, Kits Core adds the features every Kits site shares. Below: this site\'s setup, then where to change things.' );
	echo '<div id="col-container" class="wp-clearfix"><div id="col-left"><div class="col-wrap">';
	echo '<div class="postbox"><div class="postbox-header"><h2 class="hndle">' . esc_html( 'Start here' ) . '</h2></div><div class="inside"><ul>';
	foreach ( array(
		array( 'Theme Options', 'admin.php?page=kits-options', 'header, footer, each kind of page, share bar' ),
		array( 'Styles', 'site-editor.php?path=/wp_global_styles', 'colours, fonts, sizes, buttons' ),
		array( 'Header & footer', 'site-editor.php?postType=wp_template_part', 'what they hold' ),
		array( 'Templates', 'site-editor.php?postType=wp_template', 'the layout of each kind of page' ),
		array( 'Navigation', 'site-editor.php?path=/navigation', 'menus' ),
		array( 'Patterns', 'site-editor.php?path=/patterns', 'ready-made sections' ),
		array( 'Fonts', 'font-library.php', 'install or remove fonts' ),
	) as $l ) {
		echo '<li><a href="' . esc_url( admin_url( $l[1] ) ) . '"><strong>' . esc_html( $l[0] ) . '</strong></a>: ' . esc_html( $l[2] ) . '</li>';
	}
	echo '</ul></div></div></div></div>';
	echo '<div id="col-right"><div class="col-wrap"><div class="postbox"><div class="postbox-header"><h2 class="hndle">' . esc_html( 'System Status' ) . '</h2></div><div class="inside">';
	echo '<p>' . esc_html( 'Anything shown in red needs a change on the server or in wp-config.php.' ) . '</p>';
	echo '<table class="widefat striped"><caption class="screen-reader-text">' . esc_html( 'System status' ) . '</caption><tbody>';
	foreach ( kits_status_rows() as $r ) {
		echo '<tr><th scope="row">' . esc_html( $r[0] ) . '</th><td>' . wp_kses_post( $r[1] ) . '</td></tr>';
	}
	echo '</tbody></table></div></div></div></div></div></div>';
}

/* ---------- Plugins: required and recommended ---------- */

/** Plugins the theme works with: slug => [name, why, required]. Kits Core comes with the theme. */
function kits_plugin_list(): array {
	return (array) apply_filters(
		'kits_plugins',
		array(
			'kits-core'             => array( 'Kits Core', 'Theme Options, Page Options, forms, the share bar, the settings file and the design system screens.', true ),
			'seo-by-rank-math'      => array( 'Rank Math SEO', 'Titles, descriptions, schema and the social profiles the Socials section shows.', false ),
			'fluentform'            => array( 'Fluent Forms', 'The site\'s forms; Kits styles them and loads their styles only where a form is.', false ),
			'google-site-kit'       => array( 'Site Kit by Google', 'Search Console and Analytics; Kits adds Tag Manager with consent mode.', false ),
			'optimization-detective' => array( 'Optimization Detective', 'Measures which image is largest on each page; Core Settings keeps its priority.', false ),
		)
	);
}

/**
 * The installed plugin file of a slug (its folder), or ''.
 *
 * @param string $slug Plugin folder.
 */
function kits_plugin_file( string $slug ): string {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	foreach ( array_keys( get_plugins() ) as $file ) {
		if ( 0 === strpos( $file, $slug . '/' ) ) {
			return $file;
		}
	}
	return '';
}

/** Kits → Plugins. */
function kits_plugins_page() {
	kits_admin_head( 'kits-plugins', 'Plugins', 'What the theme needs and what it works best with. Install and activate use WordPress\'s own Plugins screens.' );
	$rows = array();
	foreach ( kits_plugin_list() as $slug => list( $name, $why, $required ) ) {
		$file = kits_plugin_file( $slug );
		if ( 'kits-core' === $slug && function_exists( 'kits_option' ) ) {
			$state  = is_plugin_active( 'kits-core/kits-core.php' ) ? 'Active' : 'Active (loaded by another plugin)';
			$action = '';
		} elseif ( $file && is_plugin_active( $file ) ) {
			$state  = 'Active';
			$action = '';
		} elseif ( $file ) {
			$state  = 'Installed, not active';
			$action = current_user_can( 'activate_plugins' ) ? '<a class="button" href="' . esc_url( wp_nonce_url( self_admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $file ) ), 'activate-plugin_' . $file ) ) . '">' . esc_html( 'Activate' ) . '</a>' : '';
		} else {
			$state  = 'Not installed';
			$action = current_user_can( 'install_plugins' ) && 'kits-core' !== $slug ? '<a class="button" href="' . esc_url( wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=' . rawurlencode( $slug ) ), 'install-plugin_' . $slug ) ) . '">' . esc_html( 'Install' ) . '</a>' : '';
		}
		$rows[] = array(
			'plugin' => '<strong>' . esc_html( $name ) . '</strong>',
			'type'   => esc_html( $required ? 'Required' : 'Recommended' ),
			'why'    => esc_html( $why ),
			'state'  => ( 'Active' === substr( $state, 0, 6 ) ? '<span class="dashicons dashicons-yes" aria-hidden="true"></span> ' : '' ) . esc_html( $state ),
			'action' => $action,
		);
	}
	kits_simple_table(
		'kits-plugins',
		array(
			'plugin' => 'Plugin',
			'type'   => 'Type',
			'why'    => 'What it does here',
			'state'  => 'On this site',
			'action' => '',
		),
		$rows,
		array(
			'primary' => 'plugin',
			'foot'    => false,
		)
	);
	$own = array();
	foreach ( kits_plugin_screens() as $s ) {
		if ( current_user_can( $s[2] ) ) {
			$own[] = array(
				'plugin' => '<strong><a href="' . esc_url( admin_url( $s[1] ) ) . '">' . esc_html( $s[0] ) . '</a></strong>',
				'what'   => esc_html( $s[3] ),
			);
		}
	}
	if ( $own ) {
		echo '<h2>' . esc_html( 'This site\'s plugins' ) . '</h2>';
		kits_simple_table(
			'kits-plugin-screens',
			array(
				'plugin' => 'Plugin',
				'what'   => 'What it does here',
			),
			$own,
			array(
				'primary' => 'plugin',
				'foot'    => false,
			)
		);
	}
	echo '</div>';
}

/* ---------- Import / Export ---------- */

/** Kits → Import / Export. */
function kits_transfer_page() {
	kits_admin_head( 'kits-transfer', 'Import / Export', 'One JSON file with this site\'s Styles, Theme Options, Page Options defaults and synced patterns.' );
	$user   = get_current_user_id();
	$result = get_transient( 'kits_transfer_result_' . $user );
	if ( is_array( $result ) ) {
		delete_transient( 'kits_transfer_result_' . $user );
		$ok = empty( $result['error'] );
		echo '<div class="notice ' . ( $ok ? 'notice-success' : 'notice-error' ) . ' is-dismissible"><p><strong>' . esc_html( (string) $result['title'] ) . '</strong></p>';
		if ( ! $ok ) {
			echo '<p>' . esc_html( (string) $result['error'] ) . '</p>';
		} else {
			echo '<ul>';
			foreach ( (array) $result['lines'] as $line ) {
				echo '<li>' . esc_html( (string) $line ) . '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	}
	$post = esc_url( admin_url( 'admin-post.php' ) );

	echo '<h2>' . esc_html( 'Export' ) . '</h2><p>' . esc_html( 'Downloads the file. Keep it as a backup, or import it on another Kits site.' ) . '</p>';
	echo '<p><a class="button button-primary" href="' . esc_url( kits_settings_transfer_url() ) . '">' . esc_html( 'Download the settings file' ) . '</a></p>';

	echo '<h2>' . esc_html( 'Import' ) . '</h2><p>' . esc_html( 'Check a file first: it lists what would change and changes nothing. Importing never deletes; values that are not in the file stay as they are. A copy of the current settings is kept first, to put back below.' ) . '</p>';
	echo '<form method="post" action="' . $post . '" enctype="multipart/form-data">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	wp_nonce_field( 'kits_transfer_import' );
	echo '<input type="hidden" name="action" value="kits_transfer_import"><table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row"><label for="kits-file">' . esc_html( 'Settings file' ) . '</label></th><td><input type="file" id="kits-file" name="kits_file" accept=".json,application/json" required></td></tr>';
	echo '<tr><th scope="row">' . esc_html( 'Parts' ) . '</th><td><fieldset><legend class="screen-reader-text">' . esc_html( 'Parts' ) . '</legend>';
	foreach ( array(
		'global_styles' => 'Styles (colours, fonts, sizes)',
		'options'       => 'Theme Options and Page Options defaults',
		'patterns'      => 'Synced patterns',
	) as $part => $label ) {
		echo '<label><input type="checkbox" name="kits_parts[]" value="' . esc_attr( $part ) . '" checked> ' . esc_html( $label ) . '</label><br>';
	}
	echo '</fieldset></td></tr></tbody></table><p class="submit">';
	submit_button( 'Check the file (changes nothing)', 'secondary', 'kits_dry_run', false );
	echo ' ';
	submit_button( 'Import', 'primary', 'kits_import', false );
	echo '</p></form>';

	if ( ! current_user_can( 'manage_options' ) ) {
		echo '</div>';
		return;
	}
	echo '<h2>' . esc_html( 'Restore defaults' ) . '</h2><p>' . esc_html( 'Clears Theme Options, Core Settings and the Page Options defaults, so every part works as the theme ships it. Styles, patterns and each page\'s own Page Options stay. A copy is kept first.' ) . '</p>';
	echo '<form method="post" action="' . $post . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	wp_nonce_field( 'kits_transfer_reset' );
	echo '<input type="hidden" name="action" value="kits_transfer_reset"><p><label><input type="checkbox" name="kits_sure" value="1" required> ' . esc_html( 'Yes, restore the defaults' ) . '</label></p>';
	submit_button( 'Restore defaults', 'delete', 'kits_reset', false );
	echo '</form>';

	$backup = get_option( 'kits_settings_backup' );
	if ( is_array( $backup ) && ! empty( $backup['file'] ) ) {
		echo '<h2>' . esc_html( 'Put the last copy back' ) . '</h2><p>' . esc_html( 'The settings as they were before the last import or restore (' . wp_date( 'j M Y, H:i', (int) ( $backup['time'] ?? 0 ) ) . ', ' . (string) ( $backup['why'] ?? '' ) . ').' ) . '</p>';
		echo '<form method="post" action="' . $post . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		wp_nonce_field( 'kits_transfer_undo' );
		echo '<input type="hidden" name="action" value="kits_transfer_undo">';
		submit_button( 'Put the copy back', 'secondary', 'kits_undo', false );
		echo '</form>';
	}
	echo '</div>';
}

/**
 * Keep the current settings before a change.
 *
 * @param string $why What is about to happen.
 */
function kits_settings_keep_backup( string $why ): void {
	// The copy from before a restore of the defaults is the one most worth keeping: it moves to its own slot first.
	$last = get_option( 'kits_settings_backup' );
	if ( is_array( $last ) && 'before restoring the defaults' === ( $last['why'] ?? '' ) ) {
		update_option( 'kits_settings_backup_defaults', $last, false );
	}
	$file = kits_settings_export();
	// The options part as stored, so a restore can also put back what a merge would keep.
	foreach ( kits_settings_transfer_options() as $name ) {
		$file['options'][ $name ] = get_option( $name, array() );
	}
	update_option(
		'kits_settings_backup',
		array(
			'time' => time(),
			'why'  => $why,
			'file' => $file,
		),
		false
	);
}

/**
 * The summary of an import as lines for the notice.
 *
 * @param array $s kits_settings_import()'s result.
 */
function kits_transfer_lines( array $s ): array {
	$lines = array();
	if ( ! empty( $s['note'] ) ) {
		$lines[] = (string) $s['note'];
	}
	if ( isset( $s['global_styles'] ) ) {
		$lines[] = 'Styles: ' . ( $s['global_styles']['status'] ?? '' );
	}
	foreach ( array( 'options', 'patterns' ) as $part ) {
		if ( isset( $s[ $part ] ) ) {
			$bits = array();
			foreach ( (array) $s[ $part ] as $k => $v ) {
				if ( $v ) {
					$bits[] = $k . ': ' . implode( ', ', array_map( 'strval', (array) $v ) );
				}
			}
			$lines[] = ucfirst( $part ) . ': ' . ( $bits ? implode( '; ', $bits ) : 'nothing' );
		}
	}
	return $lines;
}

/**
 * Back to the screen with a result.
 *
 * @param array $result title, lines or error.
 */
function kits_transfer_back( array $result ): void {
	set_transient( 'kits_transfer_result_' . get_current_user_id(), $result, 5 * MINUTE_IN_SECONDS );
	wp_safe_redirect( admin_url( 'admin.php?page=kits-transfer' ) );
	exit;
}

add_action(
	'admin_post_kits_transfer_import',
	function () {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( esc_html( 'You cannot import settings.' ), 403 );
		}
		check_admin_referer( 'kits_transfer_import' );
		$dry = isset( $_POST['kits_dry_run'] );
		$tmp = isset( $_FILES['kits_file']['tmp_name'] ) ? (string) $_FILES['kits_file']['tmp_name'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- a temporary path, checked next.
		if ( '' === $tmp || ! is_uploaded_file( $tmp ) || filesize( $tmp ) > 5 * MB_IN_BYTES ) {
			kits_transfer_back(
				array(
					'title' => 'No file imported',
					'error' => 'Choose a settings file (a .json file under 5 MB).',
				)
			);
		}
		$data = json_decode( (string) file_get_contents( $tmp ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local upload.
		if ( ! is_array( $data ) ) {
			kits_transfer_back(
				array(
					'title' => 'No file imported',
					'error' => 'The file is not JSON.',
				)
			);
		}
		$parts = array_map( 'sanitize_key', (array) wp_unslash( $_POST['kits_parts'] ?? array() ) );
		if ( ! $parts ) {
			kits_transfer_back(
				array(
					'title' => 'No file imported',
					'error' => 'Tick at least one part to import.',
				)
			);
		}
		$check = kits_settings_import( $data, $parts, true ); // A file that is not a Kits file stops here, before any copy is made.
		if ( ! $dry && ! is_wp_error( $check ) ) {
			kits_settings_keep_backup( 'before an import' );
		}
		$s = $dry || is_wp_error( $check ) ? $check : kits_settings_import( $data, $parts, false );
		if ( is_wp_error( $s ) ) {
			kits_transfer_back(
				array(
					'title' => 'No file imported',
					'error' => $s->get_error_message(),
				)
			);
		}
		kits_transfer_back(
			array(
				'title' => $dry ? 'Check only: nothing was changed. The file would do this:' : 'Imported.',
				'lines' => kits_transfer_lines( $s ),
			)
		);
	}
);

add_action(
	'admin_post_kits_transfer_reset',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( 'You cannot change these settings.' ), 403 );
		}
		check_admin_referer( 'kits_transfer_reset' );
		if ( empty( $_POST['kits_sure'] ) ) {
			kits_transfer_back(
				array(
					'title' => 'Nothing changed',
					'error' => 'Tick the box to restore the defaults.',
				)
			);
		}
		kits_settings_keep_backup( 'before restoring the defaults' );
		delete_option( 'kits_theme_options' );
		delete_option( 'kits_page_defaults' );
		kits_transfer_back(
			array(
				'title' => 'Defaults restored.',
				'lines' => array( 'Theme Options, Core Settings and the Page Options defaults are back to the theme\'s. The copy from before is kept below.' ),
			)
		);
	}
);

add_action(
	'admin_post_kits_transfer_undo',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( 'You cannot change these settings.' ), 403 );
		}
		check_admin_referer( 'kits_transfer_undo' );
		$backup = get_option( 'kits_settings_backup' );
		if ( ! is_array( $backup ) || empty( $backup['file'] ) ) {
			kits_transfer_back(
				array(
					'title' => 'Nothing changed',
					'error' => 'There is no copy to put back.',
				)
			);
		}
		// Options go back exactly as stored (an import alone would merge); Styles and patterns through the import.
		foreach ( kits_settings_transfer_options() as $name ) {
			if ( array_key_exists( $name, (array) $backup['file']['options'] ) ) {
				delete_option( $name );
				$value = $backup['file']['options'][ $name ];
				if ( array() !== $value && '' !== $value ) {
					update_option( $name, $value );
				}
			}
		}
		$s = kits_settings_import( (array) $backup['file'], array( 'global_styles', 'patterns' ) );
		kits_transfer_back(
			array(
				'title' => 'The copy is back.',
				'lines' => is_wp_error( $s ) ? array( $s->get_error_message() ) : array_merge( array( 'Theme Options and Page Options defaults: exactly as they were.', 'Styles and patterns: the copy\'s values are back; a style setting or pattern that the import added and the copy did not have stays (nothing is deleted).' ), kits_transfer_lines( $s ) ),
			)
		);
	}
);

/* ---------- Theme Options and Core Settings: one field ---------- */

/**
 * A Theme Options field as a form-table row.
 *
 * @param string $key   Field key.
 * @param array  $f     Field (kits_theme_option_fields()).
 * @param array  $types Share bar only: which post types to show (all when empty).
 */
function kits_theme_option_row( string $key, array $f, array $types = array() ): void {
	$id    = 'kits-to-' . $key;
	$name  = 'kits_theme_options[' . $key . ']';
	$saved = kits_theme_options_saved();
	$val   = $saved[ $key ] ?? kits_theme_option_inherited( $key );
	echo '<tr><th scope="row">' . ( 'share' === $f['kind'] ? esc_html( $f['label'] ) : '<label for="' . esc_attr( $id ) . '">' . esc_html( $f['label'] ) . '</label>' ) . '</th><td>';
	if ( 'switch' === $f['kind'] ) {
		// A switch: the hidden Off goes first, a ticked box sends On after it.
		echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="off"><label><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="on"' . checked( 'on', (string) $val, false ) . '> ' . esc_html( 'On' ) . '</label>' . esc_html( ' (default: ' . ( 'on' === $f['default'] ? 'on' : 'off' ) . ')' );
	} elseif ( 'choice' === $f['kind'] ) {
		echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
		foreach ( $f['choices'] as $c => $label ) {
			echo '<option value="' . esc_attr( $c ) . '"' . selected( (string) $val, (string) $c, false ) . '>' . esc_html( $label . ( (string) $c === (string) $f['default'] ? ' (default)' : '' ) ) . '</option>';
		}
		echo '</select>';
	} elseif ( 'share' === $f['kind'] ) {
		$conf = array_merge( kits_theme_share_default(), is_array( $val ) ? $val : array() );
		echo '<fieldset><legend class="screen-reader-text">' . esc_html( $f['label'] ) . '</legend>';
		foreach ( kits_theme_share_types() as $type => $type_label ) {
			if ( $types && ! in_array( $type, $types, true ) ) {
				continue;
			}
			$on = 'none' === ( $conf[ $type ] ?? 'none' ) ? array() : explode( ',', $conf[ $type ] );
			echo '<p><input type="hidden" name="kits_theme_options[share_seen][' . esc_attr( $type ) . ']" value="1">' . ( 1 === count( $types ) ? '' : '<strong>' . esc_html( $type_label ) . ':</strong> ' );
			foreach ( kits_theme_share_networks() as $net => $net_label ) {
				echo '<label><input type="checkbox" name="' . esc_attr( $name . '[' . $type . '][]' ) . '" value="' . esc_attr( $net ) . '"' . checked( in_array( $net, $on, true ), true, false ) . '> ' . esc_html( $net_label ) . '</label> ';
			}
			echo '</p>';
		}
		echo '</fieldset>';
	} else {
		echo '<input type="text"' . ( 'url' === $f['kind'] ? ' inputmode="url"' : '' ) . ' class="regular-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_scalar( $val ) ? (string) $val : '' ) . '"' . ( 'text' === $f['kind'] ? ' maxlength="60"' : '' ) . '>';
	}
	if ( '' !== $f['note'] ) {
		echo '<p class="description">' . esc_html( $f['note'] ) . '</p>';
	}
	echo '</td></tr>';
}

/**
 * Page Options defaults of one context as form-table rows.
 *
 * @param string $ctx  Context key in kits_page_defaults (a post type, archive_<type>, _404, _search, _author, _site).
 * @param array  $keys Which options (all that apply when empty).
 */
function kits_page_default_rows( string $ctx, array $keys = array() ): void {
	$defaults = (array) get_option( 'kits_page_defaults', array() );
	$fields   = kits_page_option_fields();
	$keys     = $keys ? $keys : kits_page_context_keys( $ctx );
	$site     = (array) ( $defaults['_site'] ?? array() );
	echo '<input type="hidden" name="' . esc_attr( 'kits_page_defaults[' . $ctx . '][_seen]' ) . '" value="1">';
	foreach ( $keys as $key ) {
		$choices = kits_page_option_choices( $key );
		if ( ! isset( $fields[ $key ] ) ) {
			continue;
		}
		if ( ! $choices ) {
			$where = array(
				'header'  => 'Make a second header in Appearance → Editor → Patterns → Header to choose it here.',
				'footer'  => 'Make a second footer in Appearance → Editor → Patterns → Footer to choose it here.',
				'colours' => 'Add a Group style in Styles to choose it here.',
			);
			echo '<tr><th scope="row">' . esc_html( $fields[ $key ][0] ) . '</th><td>' . esc_html( 'The theme\'s own. ' . ( $where[ $key ] ?? '' ) ) . '</td></tr>';
			continue;
		}
		$id      = 'kits-pd-' . $ctx . '-' . $key;
		$cur     = (string) ( $defaults[ $ctx ][ $key ] ?? '' );
		$inherit = '_site' === $ctx ? 'Theme' : ( isset( $site[ $key ], $choices[ $site[ $key ] ] ) ? 'Site-wide: ' . $choices[ $site[ $key ] ] : 'Site-wide (theme)' );
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $fields[ $key ][0] ) . '</label></th><td><select id="' . esc_attr( $id ) . '" name="' . esc_attr( 'kits_page_defaults[' . $ctx . '][' . $key . ']' ) . '">';
		echo '<option value="">' . esc_html( $inherit ) . '</option>';
		foreach ( $choices as $v => $l ) {
			echo '<option value="' . esc_attr( $v ) . '"' . selected( $cur, (string) $v, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select>';
		$note = kits_page_option_note( $key );
		if ( '' !== $note ) {
			echo '<p class="description">' . esc_html( $note ) . '</p>';
		}
		echo '</td></tr>';
	}
}

/**
 * A box of rows in a section.
 *
 * @param string   $title Box heading.
 * @param callable $rows  Prints the form-table rows.
 * @param string   $intro Optional line (HTML allowed: links).
 */
function kits_options_box( string $title, callable $rows, string $intro = '' ): void {
	echo '<div class="postbox"><div class="postbox-header"><h2 class="hndle">' . esc_html( $title ) . '</h2></div><div class="inside">';
	if ( '' !== $intro ) {
		echo '<p>' . wp_kses_post( $intro ) . '</p>';
	}
	echo '<table class="form-table" role="presentation"><tbody>';
	$rows();
	echo '</tbody></table></div></div>';
}

/* ---------- Core Settings ---------- */

/** The groups of fields that Core Settings shows, in order (theme-options.php): the speed fixes, then older content. */
function kits_core_settings_groups(): array {
	return array( 'Speed', 'Older content' );
}

/** Kits → Core Settings: the switches for speed fixes and older content, in a box per group, each with what it does. */
function kits_core_settings_page() {
	kits_admin_head( 'kits-core-settings', 'Core Settings', 'Switches for the theme\'s speed fixes and for older content. Leave them on unless you are tracking down a problem.' );
	echo '<form method="post" action="options.php">';
	settings_fields( 'kits_options' );
	foreach ( kits_core_settings_groups() as $group ) {
		kits_options_box(
			$group,
			function () use ( $group ) {
				foreach ( kits_theme_option_fields() as $key => $f ) {
					if ( $group === $f['group'] && 'core' === kits_theme_option_section( $key, $f ) ) {
						kits_theme_option_row( $key, $f );
					}
				}
			}
		);
	}
	submit_button( 'Save Core Settings' );
	echo '</form></div>';
}

/* ---------- Theme Options ---------- */

/**
 * Where a Theme Options field shows: core (Core Settings), or a Theme Options section.
 *
 * @param string $key Field key.
 * @param array  $f   Field.
 */
function kits_theme_option_section( string $key, array $f ): string {
	$map = array(
		'announce'       => 'general-header',
		'sticky_bar'     => 'general-footer',
		'sticky_href'    => 'general-footer',
		'sticky_label'   => 'general-footer',
		'font_preload'   => 'utility-performance',
		'share_networks' => 'utility-share',
	);
	if ( isset( $map[ $key ] ) ) {
		return $map[ $key ];
	}
	return in_array( $f['group'], kits_core_settings_groups(), true ) ? 'core' : 'utility-extra';
}

/** Theme Options sections: group => [slug => label]. */
function kits_options_sections(): array {
	$single = array();
	foreach ( kits_page_option_types() as $type => $label ) {
		$single[ 'single-' . $type ] = $label;
	}
	$single['single-404'] = '404';
	$archive              = array();
	foreach ( kits_archive_types() as $type => $label ) {
		$archive[ 'archive-' . $type ] = $label;
	}
	$archive['archive-search'] = 'Search';
	$archive['archive-author'] = 'Authors';
	return (array) apply_filters(
		'kits_options_sections',
		array(
			'General'  => array(
				'general-header' => 'Header',
				'general-layout' => 'Layout',
				'general-footer' => 'Footer',
			),
			'Single'   => $single,
			'Archives' => $archive,
			'Visual'   => array(
				'visual-palette'    => 'Palette',
				'visual-typography' => 'Typography',
				'visual-buttons'    => 'Buttons',
				'visual-colours'    => 'Colour sets',
				'visual-spacing'    => 'Spacing',
				'visual-cards'      => 'Cards',
			),
			'Utility'  => array(
				'utility-share'       => 'Share bar',
				'utility-socials'     => 'Socials',
				'utility-performance' => 'Performance',
				'utility-extra'       => 'Extra',
			),
		)
	);
}

/**
 * A link into the Site Editor or another admin screen.
 *
 * @param string $label Text.
 * @param string $path  Relative to wp-admin.
 */
function kits_options_link( string $label, string $path ): string {
	return '<a href="' . esc_url( admin_url( $path ) ) . '">' . esc_html( $label ) . '</a>';
}

/** Kits → Theme Options. */
function kits_options_page() {
	if ( kits_admin_app_wanted() ) {
		kits_admin_app_page(); // The new screen (trial), admin-app.php.
		return;
	}
	$sections = kits_options_sections();
	$all      = array_merge( ...array_values( $sections ) );
	$current  = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : 'general-header'; // phpcs:ignore WordPress.Security.NonceVerification -- which section to show.
	if ( ! isset( $all[ $current ] ) ) {
		$current = 'general-header';
	}
	kits_admin_head( 'kits-options', 'Theme Options', 'Site-wide choices, in sections. Colours, fonts and sizes live in Styles; one page can still differ in its Page Options.' );
	echo '<div id="col-container" class="wp-clearfix"><div id="col-left"><div class="col-wrap"><nav class="postbox" aria-label="' . esc_attr( 'Theme Options sections' ) . '"><div class="inside">';
	foreach ( $sections as $group => $items ) {
		echo '<h3>' . esc_html( $group ) . '</h3><ul>';
		foreach ( $items as $slug => $label ) {
			echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=kits-options&section=' . $slug ) ) . '"' . ( $slug === $current ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a></li>';
		}
		echo '</ul>';
	}
	echo '</div></nav></div></div><div id="col-right"><div class="col-wrap">';
	$group = '';
	foreach ( $sections as $g => $items ) {
		if ( isset( $items[ $current ] ) ) {
			$group = $g;
		}
	}
	echo '<h2>' . esc_html( $group . ': ' . $all[ $current ] ) . '</h2>';
	echo '<form method="post" action="options.php">';
	settings_fields( 'kits_options' );
	$saveable = kits_options_section_body( $current, $all[ $current ] );
	if ( $saveable ) {
		submit_button( 'Save ' . $all[ $current ] );
	}
	echo '</form></div></div></div></div>';
}

/**
 * Theme Options fields of one section as rows.
 *
 * @param string $section Section slug.
 */
function kits_theme_option_rows_of( string $section ): void {
	foreach ( kits_theme_option_fields() as $key => $f ) {
		if ( kits_theme_option_section( $key, $f ) === $section ) {
			kits_theme_option_row( $key, $f );
		}
	}
}

/**
 * The body of one section. Returns false when it holds nothing to save (links and values from Styles only).
 *
 * @param string $section Section slug.
 * @param string $label   Section label.
 */
function kits_options_section_body( string $section, string $label ): bool {
	$styles = 'site-editor.php?path=/wp_global_styles';
	switch ( $section ) {
		case 'general-header':
			kits_options_box( 'Header', fn() => kits_page_default_rows( '_site', array( 'header' ) ), 'The header every page uses unless its kind of page or the page itself picks another. What the header holds (logo, menu, buttons): ' . kits_options_link( 'Editor → Patterns → Header', 'site-editor.php?postType=wp_template_part' ) . '.' );
			kits_options_box( 'Announcement bar', fn() => kits_theme_option_rows_of( 'general-header' ) );
			return true;
		case 'general-layout':
			kits_options_box( 'Layout', fn() => kits_page_default_rows( '_site', array( 'sidebar', 'title', 'crumbs' ) ), 'Site-wide defaults; each kind of page can differ under Single and Archives. Widths and spacing: ' . kits_options_link( 'Styles → Layout', $styles ) . '.' );
			return true;
		case 'general-footer':
			kits_options_box( 'Footer', fn() => kits_page_default_rows( '_site', array( 'footer' ) ), 'What the footer holds: ' . kits_options_link( 'Editor → Patterns → Footer', 'site-editor.php?postType=wp_template_part' ) . '.' );
			kits_options_box( 'Phone bar', fn() => kits_theme_option_rows_of( 'general-footer' ) );
			return true;
		case 'utility-share':
			kits_options_box( 'Share bar', fn() => kits_theme_option_rows_of( 'utility-share' ) );
			return true;
		case 'utility-performance':
			kits_options_box( 'Performance', fn() => kits_theme_option_rows_of( 'utility-performance' ), 'More speed switches: ' . kits_options_link( 'Kits → Core Settings', 'admin.php?page=kits-core-settings' ) . '.' );
			return true;
		case 'utility-extra':
			$has = (bool) array_filter( array_keys( kits_theme_option_fields() ), fn( $k ) => 'utility-extra' === kits_theme_option_section( $k, kits_theme_option_fields()[ $k ] ) );
			if ( $has ) {
				kits_options_box( 'Extra', fn() => kits_theme_option_rows_of( 'utility-extra' ) );
			}
			kits_options_links_box(
				'Extra',
				array(
					array( 'Preview', 'admin.php?page=kits-preview', 'Who sees preview-only items.' ),
					array( 'Design system', 'admin.php?page=kits-system', 'Every component, its states and the colour contrast.' ),
				)
			);
			return $has;
		case 'utility-socials':
			kits_options_socials();
			return false;
		case 'visual-palette':
			kits_options_palette();
			return false;
		case 'visual-typography':
			kits_options_typography();
			return false;
		case 'visual-buttons':
			kits_options_buttons();
			return false;
		case 'visual-spacing':
			kits_options_spacing();
			return false;
		case 'visual-cards':
			kits_options_cards();
			return false;
		case 'visual-colours':
			$sets = WP_Block_Styles_Registry::get_instance()->get_registered_styles_for_block( 'core/post-content' );
			kits_options_links_box(
				'Colour sets',
				array_merge(
					array_map( fn( $s ) => array( (string) ( $s['label'] ?? $s['name'] ), '', 'A page picks it in Page Options → Colour set; a kind of page under Single.' ), array_values( $sets ) ),
					array( array( 'Change them', $styles, 'Styles → Blocks → Group: each colour set is a Group style.' ) )
				)
			);
			return false;
	}
	if ( 'single-404' === $section ) {
		kits_options_box( '404 page', fn() => kits_page_default_rows( '_404' ), 'The "page not found" page. Its words: ' . kits_options_link( 'Editor → Templates → 404', 'site-editor.php?postType=wp_template' ) . '.' );
		return true;
	}
	if ( 0 === strpos( $section, 'single-' ) ) {
		$type = substr( $section, 7 );
		kits_options_box( $label . ': each item', fn() => kits_page_default_rows( $type ), 'Every ' . $label . ' item uses these unless it picks its own in the editor (Page Options panel). Its layout: ' . kits_options_link( 'Editor → Templates', 'site-editor.php?postType=wp_template' ) . '.' );
		if ( isset( kits_theme_share_types()[ $type ] ) ) {
			$f = kits_theme_option_fields()['share_networks'];
			kits_options_box( 'Share bar', fn() => kits_theme_option_row( 'share_networks', array_merge( $f, array( 'note' => 'Untick all to hide the bar on ' . $label . '. Copy link always shows.' ) ), array( $type ) ) );
		}
		return true;
	}
	if ( 'archive-search' === $section || 'archive-author' === $section ) {
		$ctx = '_' . substr( $section, 8 );
		kits_options_box( $label . ' page', fn() => kits_page_default_rows( $ctx ) );
		return true;
	}
	if ( 0 === strpos( $section, 'archive-' ) ) {
		$type = substr( $section, 8 );
		kits_options_box( $label . ': the list page', fn() => kits_page_default_rows( 'archive_' . $type ), 'The page that lists every ' . $label . ' item, and its category and tag pages.' );
		return true;
	}
	do_action( 'kits_options_section', $section );
	return false;
}

/**
 * A box of links with a line each.
 *
 * @param string $title Heading.
 * @param array  $links [label, path relative to wp-admin or '', line].
 */
function kits_options_links_box( string $title, array $links ): void {
	echo '<div class="postbox"><div class="postbox-header"><h2 class="hndle">' . esc_html( $title ) . '</h2></div><div class="inside"><table class="widefat striped" role="presentation"><tbody>';
	foreach ( $links as $l ) {
		echo '<tr><th scope="row">' . ( '' !== $l[1] ? kits_options_link( $l[0], $l[1] ) : esc_html( $l[0] ) ) . '</th><td>' . esc_html( $l[2] ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kits_options_link escapes.
	}
	echo '</tbody></table></div></div>';
}

/** Visual → Palette: the colours as Styles holds them now. */
function kits_options_palette(): void {
	$sets = (array) wp_get_global_settings( array( 'color', 'palette' ) );
	$rows = array();
	foreach ( array(
		'custom' => 'Your colours',
		'theme'  => 'Theme colours',
	) as $origin => $title ) {
		foreach ( (array) ( $sets[ $origin ] ?? array() ) as $c ) {
			$hex    = (string) ( $c['color'] ?? '' );
			$rows[] = array( '<span class="dashicons" aria-hidden="true" style="background:' . esc_attr( $hex ) . '"></span> ' . esc_html( (string) ( $c['name'] ?? '' ) ), esc_html( $hex . ' · ' . $title ) );
		}
	}
	kits_options_values_box( 'Palette', $rows, 'Change a colour in ' . kits_options_link( 'Styles → Colors → Palette', 'site-editor.php?path=/wp_global_styles' ) . '; every part that uses it follows. Contrast: ' . kits_options_link( 'Design system → Colour contrast', 'admin.php?page=kits-system&tab=contrast' ) . '.' );
}

/** Visual → Typography: font families and the sizes of text and headings. */
function kits_options_typography(): void {
	$rows  = array();
	$fonts = (array) wp_get_global_settings( array( 'typography', 'fontFamilies' ) );
	foreach ( array_merge( (array) ( $fonts['custom'] ?? array() ), (array) ( $fonts['theme'] ?? array() ) ) as $f ) {
		$rows[] = array( esc_html( (string) ( $f['name'] ?? '' ) ), '<span style="font-family:' . esc_attr( (string) ( $f['fontFamily'] ?? '' ) ) . '">' . esc_html( 'Sphinx of black quartz, judge my vow. 0123456789' ) . '</span>' );
	}
	$styles = wp_get_global_styles();
	$body   = (array) ( $styles['typography'] ?? array() );
	$rows[] = array( esc_html( 'Body text' ), esc_html( trim( (string) ( $body['fontFamily'] ?? '' ) . ' ' . (string) ( $body['fontSize'] ?? '' ) . ' ' . (string) ( $body['lineHeight'] ?? '' ) ) ) );
	foreach ( array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) as $h ) {
		$t = (array) ( $styles['elements'][ $h ]['typography'] ?? array() );
		if ( $t ) {
			$rows[] = array( esc_html( strtoupper( $h ) ), esc_html( trim( (string) ( $t['fontSize'] ?? '' ) . ' · weight ' . (string) ( $t['fontWeight'] ?? '' ) . ' · line ' . (string) ( $t['lineHeight'] ?? '' ) ) ) );
		}
	}
	kits_options_values_box( 'Typography', $rows, 'Change them in ' . kits_options_link( 'Styles → Typography', 'site-editor.php?path=/wp_global_styles' ) . '. Install fonts in ' . kits_options_link( 'Kits → Fonts', 'font-library.php' ) . '.' );
}

/** Visual → Buttons: what Styles → Blocks → Button sets, which every button on the site follows. */
function kits_options_buttons(): void {
	$b    = (array) wp_get_global_styles( array( 'elements', 'button' ) );
	$rows = array(
		array( esc_html( 'Background' ), esc_html( (string) ( $b['color']['background'] ?? '' ) ) ),
		array( esc_html( 'Text' ), esc_html( (string) ( $b['color']['text'] ?? '' ) ) ),
		array( esc_html( 'Corner radius' ), esc_html( (string) ( $b['border']['radius'] ?? '' ) ) ),
		array( esc_html( 'Weight' ), esc_html( (string) ( $b['typography']['fontWeight'] ?? '' ) ) ),
	);
	foreach ( array(
		'kits-secondary' => 'Secondary style',
		'kits-ghost'     => 'Ghost style',
		'kits-brand'     => 'Brand style',
	) as $slug => $label ) {
		$v      = (array) wp_get_global_styles( array( 'variations', $slug ), array( 'block_name' => 'core/button' ) );
		$rows[] = array( esc_html( $label ), esc_html( trim( (string) ( $v['color']['background'] ?? '' ) . ' / ' . (string) ( $v['color']['text'] ?? '' ), ' /' ) ) );
	}
	kits_options_values_box( 'Buttons', $rows, 'Every button on the site (in the content, the header, forms and cards) follows ' . kits_options_link( 'Styles → Blocks → Button', 'site-editor.php?path=/wp_global_styles' ) . ', including its Secondary, Ghost and Brand styles.' );
}

/** Visual → Spacing: the spacing steps Styles holds now, which padding and gaps use. */
function kits_options_spacing(): void {
	$sets = (array) wp_get_global_settings( array( 'spacing', 'spacingSizes' ) );
	$rows = array();
	foreach ( array(
		'custom' => 'Your sizes',
		'theme'  => 'Theme sizes',
	) as $origin => $title ) {
		foreach ( (array) ( $sets[ $origin ] ?? array() ) as $size ) {
			$rows[] = array( esc_html( (string) ( $size['name'] ?? '' ) ), '<code>' . esc_html( '--wp--preset--spacing--' . (string) ( $size['slug'] ?? '' ) ) . '</code> ' . esc_html( (string) ( $size['size'] ?? '' ) . ' · ' . $title ) );
		}
	}
	kits_options_values_box( 'Spacing', $rows ? $rows : array( array( esc_html( 'None yet' ), '' ) ), 'The steps are set in the theme\'s theme.json (<code>settings.spacing.spacingSizes</code>); the Site Editor\'s Styles doesn\'t edit them. Every part that uses a step follows.' );
}

/**
 * The card: its block style in Styles and the theme's card tokens (names without the leading dashes).
 *
 * @return array block, variation, tokens.
 */
function kits_card_parts(): array {
	return (array) apply_filters(
		'kits_card_parts',
		array(
			'block'     => 'core/group',
			'variation' => 'kits-card',
			'tokens'    => array( 'kit-radius-card', 'kit-surface-card', 'kit-card-min', 'kit-card-columns', 'kit-card-media-ratio' ),
		)
	) + array(
		'block'     => '',
		'variation' => '',
		'tokens'    => array(),
	);
}

/** Visual → Cards: the Card style's values in Styles (padding step, corners, colours) and the card tokens. */
function kits_options_cards(): void {
	$card = kits_card_parts();
	$rows = array();
	foreach ( kits_block_style_values( (string) $card['block'], (string) $card['variation'] ) as $label => $v ) {
		$rows[] = array( esc_html( $label ), '<code>' . esc_html( $v[0] ) . '</code> ' . esc_html( $v[1] ) );
	}
	$vals = kits_token_values();
	foreach ( (array) $card['tokens'] as $t ) {
		if ( isset( $vals[ $t ] ) ) {
			$rows[] = array( '<code>--' . esc_html( $t ) . '</code>', '<code>' . esc_html( $vals[ $t ] ) . '</code> ' . esc_html( kits_token_resolve( $t, $vals ) ) );
		}
	}
	kits_options_values_box( 'Cards', $rows ? $rows : array( array( esc_html( 'None yet' ), '' ) ), 'The padding, corners and colours are the ' . kits_block_style_where( (string) $card['block'], (string) $card['variation'] ) . '; the Site Editor\'s Styles doesn\'t list them. The tokens are the theme\'s base values (phones): ' . kits_options_link( 'Design system → Tokens', 'admin.php?page=kits-system&tab=tokens' ) . '.' );
}

/** Utility → Socials: the profiles Rank Math holds, which the footer and schema use. */
function kits_options_socials(): void {
	$rm   = (array) get_option( 'rank-math-options-titles', array() );
	$rows = array();
	foreach ( array(
		'social_url_facebook' => 'Facebook',
		'twitter_author_names' => 'X',
	) as $k => $label ) {
		if ( ! empty( $rm[ $k ] ) ) {
			$rows[] = array( esc_html( $label ), esc_html( (string) $rm[ $k ] ) );
		}
	}
	foreach ( preg_split( '/[\r\n]+/', (string) ( $rm['social_additional_profiles'] ?? '' ) ) as $url ) {
		$url = trim( $url );
		if ( '' !== $url ) {
			$rows[] = array( esc_html( (string) wp_parse_url( $url, PHP_URL_HOST ) ), esc_html( $url ) );
		}
	}
	$where = defined( 'RANK_MATH_VERSION' ) ? kits_options_link( 'Rank Math → Titles & Meta → Social Meta', 'admin.php?page=rank-math-options-titles#setting-panel-social' ) : esc_html( 'Rank Math SEO (not active: see Kits → Plugins)' );
	kits_options_values_box( 'Socials', $rows ? $rows : array( array( esc_html( 'None yet' ), '' ) ), 'The site\'s profiles, read from ' . $where . '. Change them there; search engines read them too.' );
}

/**
 * A box of label/value rows, read only.
 *
 * @param string $title Heading.
 * @param array  $rows  [label HTML, value HTML].
 * @param string $intro Line above (HTML allowed: links).
 */
function kits_options_values_box( string $title, array $rows, string $intro ): void {
	echo '<div class="postbox"><div class="postbox-header"><h2 class="hndle">' . esc_html( $title ) . '</h2></div><div class="inside"><p>' . wp_kses_post( $intro ) . '</p><table class="widefat striped" role="presentation"><tbody>';
	foreach ( $rows as $r ) {
		echo '<tr><th scope="row">' . wp_kses_post( $r[0] ) . '</th><td>' . wp_kses(
			$r[1],
			array(
				'span' => array( 'style' => true ),
				'code' => array(),
			)
		) . '</td></tr>';
	}
	echo '</tbody></table></div></div>';
}

/* ---------- Docs ---------- */

/** Kits → Docs: what the theme and Kits Core hold and where each is changed (was the Overview). */
function kits_docs_page() {
	kits_admin_head( 'kits-docs', 'Docs', 'What the theme and Kits Core hold, and where each is changed.' );
	kits_overview_tables();
	echo '</div>';
}
