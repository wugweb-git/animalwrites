<?php
/**
 * The Kits admin, laid out like a marketplace theme's hub: one item under Appearance, the same tabs on every Kits
 * screen, and one screen per job. Core wp-admin parts only (nav tabs, form tables, postboxes, the Settings API, notices);
 * no admin stylesheet.
 *
 * Six items (docs/admin-ui-spec.md §B; admin-menu.php registers them and redirects the old addresses): Home, Theme
 * Options, Settings, Plugins, Tools, Help. This file holds the parts they are made of: the status table, the plugin
 * list, export and import, the Theme Options fields and sections, the Core Settings switches, the Docs tables.
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
 * Always the five of kits_menu_six(); the kits_admin_tabs filter may change a label or a capability, and a tab it adds
 * is dropped (a site adds sections to the five instead: kits_home_sections, kits_settings_sections, kits_help_sections).
 */
function kits_admin_tabs_unsorted(): array {
	$six = kits_menu_six( KITS_MENU );
	/**
	 * Filters the five Kits tabs (label, address, capability). Only the five keys are kept.
	 *
	 * @param array $tabs Slug => [label, address relative to wp-admin, capability].
	 */
	return kits_menu_tabs_only_six( (array) apply_filters( 'kits_admin_tabs', $six ), $six );
}

/**
 * One order for the Kits menu and the Kits tabs: screen slugs (the page= value). The five in their order; the filter may
 * reorder them.
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
		$submenu[ KITS_MENU ] = array_values( kits_menu_sort( $submenu[ KITS_MENU ], fn( $item ) => $item[2] ?? '' ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- reorders the Kits submenu only (the five, admin-menu.php).
	},
	1000 // After every plugin has added its screens.
);

/**
 * The tabs every Kits screen starts with.
 *
 * @param string $current The screen's slug.
 */
function kits_admin_tabs( string $current ): void {
	if ( kits_admin_embedding() ) {
		return; // Shown as a section of one of the five: that screen has the tabs.
	}
	echo '<nav class="nav-tab-wrapper wp-clearfix" aria-label="' . esc_attr( 'Kits screens' ) . '">';
	foreach ( kits_admin_tabs_list() as $slug => $t ) {
		if ( ! current_user_can( $t[2] ) ) {
			continue;
		}
		$on = $slug === $current || kits_screen_tab( $current ) === $slug;
		echo '<a class="nav-tab' . ( $on ? ' nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( $t[1] ) ) . '"' . ( $on ? ' aria-current="page"' : '' ) . '>' . esc_html( $t[0] ) . '</a>';
	}
	echo '</nav>';
}

/**
 * The icon of a Kits product as a data: address, or '' when it has none. Read from disk, so it shows however the
 * plugin is loaded (Animal Writes Core loads SourceWeb from outside wp-content/plugins, where it has no web address).
 * The Kits theme keeps the suite's icon in assets/images/kits-icon.svg; each plugin keeps its own in assets/icon.svg
 * (docs/brand/plugin-icons/README.md).
 *
 * @param string $name Product name as the screens show it: Kits, Kits Core, RankWeb, SourceWeb, MailWeb, InsightWeb.
 */
function kits_product_icon( string $name ): string {
	/**
	 * Filters where each Kits product's folder is: product name => [constant holding its folder, plugin folder name].
	 *
	 * @param array $folders Product name => [constant, folder].
	 */
	$folders = (array) apply_filters(
		'kits_product_folders',
		array(
			'Kits Core'    => array( 'KITS_CORE_DIR', 'kits-core' ),
			'RankWeb'      => array( 'AW_SEO_EXTRAS_DIR', 'aw-seo-extras' ),
			'SourceWeb'    => array( 'AW_SOURCING_DIR', 'aw-sourcing' ),
			'MailWeb'      => array( 'MAILWEB_DIR', 'mailweb' ),
			'InsightWeb'   => array( 'INSIGHTWEB_DIR', 'insightweb' ),
			'DirectoryWeb' => array( 'DIRECTORYWEB_DIR', 'directoryweb' ),
			'PlanWeb'      => array( 'PLANWEB_DIR', 'planweb' ),
		)
	);
	if ( 'Kits' === $name ) {
		$file = get_template_directory() . '/assets/images/kits-icon.svg';
	} elseif ( isset( $folders[ $name ] ) ) {
		list( $const, $folder ) = array_values( (array) $folders[ $name ] ) + array( '', '' );
		$dir  = '' !== $const && defined( $const ) ? trailingslashit( (string) constant( $const ) ) : WP_PLUGIN_DIR . '/' . $folder . '/';
		$file = $dir . 'assets/icon.svg';
	} else {
		return '';
	}
	if ( ! is_readable( $file ) || filesize( $file ) > 20000 ) {
		return '';
	}
	$svg = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file.
	return '' === $svg ? '' : 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- an image address.
}

/**
 * A product's icon as an image tag before its name, or '' when it has none. The name next to it says what it is, so
 * the image has empty alt text.
 *
 * @param string $name Product name.
 * @param int    $size Width and height in pixels.
 */
function kits_product_icon_img( string $name, int $size = 20 ): string {
	$src = kits_product_icon( $name );
	if ( '' === $src ) {
		return '';
	}
	return '<img src="' . esc_attr( $src ) . '" width="' . $size . '" height="' . $size . '" alt="" style="vertical-align:middle;margin-inline-end:8px"> ';
}

/**
 * A screen's heading, intro and tabs.
 *
 * @param string $slug  The screen's slug.
 * @param string $title Heading.
 * @param string $intro One line under it (plain text).
 */
function kits_admin_head( string $slug, string $title, string $intro = '' ): void {
	if ( kits_admin_embedding() ) {
		echo '<div>'; // A section of one of the five: no second heading or tabs. The screen closes it as it closes .wrap.
		if ( '' !== $intro ) {
			echo '<p>' . esc_html( $intro ) . '</p>';
		}
		return;
	}
	$theme = wp_get_theme( get_template() );
	echo '<div class="wrap"><h1>' . kits_product_icon_img( 'Kits', 32 ) . esc_html( $title ) . '</h1>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kits_product_icon_img() escapes its image address.
	if ( '' !== $intro ) {
		echo '<p>' . esc_html( $intro ) . '</p>';
	}
	echo '<p class="description">' . esc_html( $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) ) . '</p>';
	kits_admin_tabs( $slug );
	settings_errors();
}

/* ---------- Home: versions and limits ---------- */

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

/** Kits → Home → Setup checklist: where to change things. */
function kits_home_start(): void {
	echo '<ul>';
	foreach ( array(
		array( 'Theme Options', 'admin.php?page=kits-options', 'identity, header, footer, layout, each kind of page, share bar' ),
		array( 'Styles', 'site-editor.php?path=/wp_global_styles', 'colours, fonts, sizes, buttons' ),
		array( 'Header & footer', 'site-editor.php?postType=wp_template_part', 'what they hold' ),
		array( 'Templates', 'site-editor.php?postType=wp_template', 'the layout of each kind of page' ),
		array( 'Navigation', 'site-editor.php?path=/navigation', 'menus' ),
		array( 'Patterns', 'site-editor.php?path=/patterns', 'ready-made sections' ),
		array( 'Fonts', 'font-library.php', 'install or remove fonts' ),
	) as $l ) {
		echo '<li><a href="' . esc_url( admin_url( $l[1] ) ) . '"><strong>' . esc_html( $l[0] ) . '</strong></a>: ' . esc_html( $l[2] ) . '</li>';
	}
	echo '</ul>';
}

/** Kits → Home → Versions and limits: the System Status table (kits_status_rows). */
function kits_home_status(): void {
	echo '<p>' . esc_html( 'Anything shown in red needs a change on the server or in wp-config.php.' ) . '</p>';
	echo '<table class="widefat striped"><caption class="screen-reader-text">' . esc_html( 'System status' ) . '</caption><tbody>';
	foreach ( kits_status_rows() as $r ) {
		$label = (string) $r[0];
		echo '<tr><th scope="row">' . kits_product_icon_img( 'Theme version' === $label ? 'Kits' : $label ) . esc_html( $label ) . '</th><td>' . wp_kses_post( $r[1] ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kits_product_icon_img() escapes its image address.
	}
	echo '</tbody></table>';
}

/* ---------- Import / Export ---------- */

/** Kits → Tools: export, check a file, import, restore defaults, put the copy back (was Import / Export). */
function kits_transfer_page() {
	$intro = 'One JSON file with this site\'s Styles, Theme Options, Page Options defaults and synced patterns.';
	$parts = kits_transfer_parts();
	if ( function_exists( 'kits_frame_ready' ) && kits_frame_ready() ) {
		// The default (not &ui=classic): each part as a card in the plugin screens' frame (includes/admin-kits-frame.php).
		kits_frame_open( 'kits-tools', 'Tools', $intro );
		kits_transfer_result();
		foreach ( $parts as $id => $part ) {
			kits_frame_card( $id, $part[0], $part[1] );
		}
		kits_frame_close();
		return;
	}
	kits_admin_head( 'kits-tools', 'Tools', $intro );
	kits_transfer_result();
	foreach ( $parts as $part ) {
		echo '<h2>' . esc_html( $part[0] ) . '</h2>';
		call_user_func( $part[1] );
	}
	echo '</div>';
}

/** The parts of Kits → Tools the user may use, in order: id => [ heading, prints the part ]. */
function kits_transfer_parts(): array {
	$parts = array(
		'export' => array( 'Export', 'kits_transfer_export_part' ),
		'import' => array( 'Import', 'kits_transfer_import_part' ),
	);
	if ( current_user_can( 'manage_options' ) ) {
		$parts['restore'] = array( 'Restore defaults', 'kits_transfer_reset_part' );
		$backup           = get_option( 'kits_settings_backup' );
		if ( is_array( $backup ) && ! empty( $backup['file'] ) ) {
			$parts['undo'] = array( 'Put the last copy back', 'kits_transfer_undo_part' );
		}
	}
	return $parts;
}

/** What the last import, check, restore or put-back did (once, for the user who did it). */
function kits_transfer_result(): void {
	$user   = get_current_user_id();
	$result = get_transient( 'kits_transfer_result_' . $user );
	if ( ! is_array( $result ) ) {
		return;
	}
	delete_transient( 'kits_transfer_result_' . $user );
	$ok = empty( $result['error'] );
	$body = '<p><strong>' . esc_html( (string) $result['title'] ) . '</strong></p>';
	if ( ! $ok ) {
		$body .= '<p>' . esc_html( (string) $result['error'] ) . '</p>';
	} else {
		$body .= '<ul>';
		foreach ( (array) $result['lines'] as $line ) {
			$body .= '<li>' . esc_html( (string) $line ) . '</li>';
		}
		$body .= '</ul>';
	}
	wp_admin_notice( $body, array( 'type' => $ok ? 'success' : 'error', 'dismissible' => true, 'paragraph_wrap' => false ) );
}

/** Tools → Export. */
function kits_transfer_export_part(): void {
	echo '<p>' . esc_html( 'Downloads the file. Keep it as a backup, or import it on another Kits site.' ) . '</p>';
	echo '<p><a class="button button-primary" href="' . esc_url( kits_settings_transfer_url() ) . '">' . esc_html( 'Download the settings file' ) . '</a></p>';
}

/** Tools → Import (and Check the file). */
function kits_transfer_import_part(): void {
	echo '<p>' . esc_html( 'Check a file first: it lists what would change and changes nothing. Importing never deletes; values that are not in the file stay as they are. A copy of the current settings is kept first, to put back below.' ) . '</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data">';
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
}

/** Tools → Restore defaults. */
function kits_transfer_reset_part(): void {
	echo '<p>' . esc_html( 'Clears Theme Options, Settings and the Page Options defaults, so every part works as the theme ships it. Styles, patterns and each page\'s own Page Options stay. A copy is kept first.' ) . '</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'kits_transfer_reset' );
	echo '<input type="hidden" name="action" value="kits_transfer_reset"><p><label><input type="checkbox" name="kits_sure" value="1" required> ' . esc_html( 'Yes, restore the defaults' ) . '</label></p>';
	submit_button( 'Restore defaults', 'delete', 'kits_reset', false );
	echo '</form>';
}

/** Tools → Put the last copy back. */
function kits_transfer_undo_part(): void {
	$backup = (array) get_option( 'kits_settings_backup' );
	echo '<p>' . esc_html( 'The settings as they were before the last import or restore (' . wp_date( 'j M Y, H:i', (int) ( $backup['time'] ?? 0 ) ) . ', ' . (string) ( $backup['why'] ?? '' ) . ').' ) . '</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'kits_transfer_undo' );
	echo '<input type="hidden" name="action" value="kits_transfer_undo">';
	submit_button( 'Put the copy back', 'secondary', 'kits_undo', false );
	echo '</form>';
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
	$back = admin_url( 'admin.php?page=kits-tools' );
	$from = (string) wp_parse_url( (string) wp_get_referer(), PHP_URL_QUERY );
	parse_str( $from, $q );
	if ( 'classic' === ( $q['ui'] ?? '' ) ) {
		$back = add_query_arg( 'ui', 'classic', $back ); // Sent from the earlier screen: come back to it.
	}
	wp_safe_redirect( $back );
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
				'lines' => array( 'Theme Options, Settings and the Page Options defaults are back to the theme\'s. The copy from before is kept below.' ),
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
	echo '<div class="postbox">';
	if ( kits_options_box_titled() ) {
		echo '<div class="postbox-header"><h2 class="hndle">' . esc_html( $title ) . '</h2></div>';
	}
	echo '<div class="inside">';
	if ( '' !== $intro ) {
		echo '<p>' . wp_kses_post( $intro ) . '</p>';
	}
	echo '<table class="form-table" role="presentation"><tbody>';
	$rows();
	echo '</tbody></table></div></div>';
}

/**
 * Whether kits_options_box() prints its heading. Theme Options → Content types turns it off while it prints a box under
 * a heading that already names it.
 *
 * @param bool|null $set Set the flag.
 */
function kits_options_box_titled( ?bool $set = null ): bool {
	static $on = true;
	if ( null !== $set ) {
		$on = $set;
	}
	return $on;
}

/* ---------- Core Settings ---------- */

/** The groups of fields that Core Settings shows, in order (theme-options.php): the speed fixes, then older content. */
function kits_core_settings_groups(): array {
	return array( 'Speed', 'Older content' );
}

/**
 * One Core Settings group as a form of its own (Kits → Settings → Performance or Compatibility). The options save by
 * merging, so a form changes only its own fields.
 *
 * @param string $group A group of kits_core_settings_groups().
 * @param string $also  Also a Theme Options section's fields in the same form (font preload: utility-performance).
 * @param string $back  Section of Kits → Settings to come back to after saving (kits_section=, see admin-menu.php).
 */
function kits_core_settings_form( string $group, string $also = '', string $back = '' ): void {
	echo '<form method="post" action="options.php">';
	kits_options_form_fields( 'admin.php?page=kits-settings' . ( '' !== $back ? '&kits_section=' . rawurlencode( $back ) : '' ) );
	echo '<table class="form-table" role="presentation"><tbody>';
	foreach ( kits_theme_option_fields() as $key => $f ) {
		if ( $group === $f['group'] && 'core' === kits_theme_option_section( $key, $f ) ) {
			kits_theme_option_row( $key, $f );
		}
	}
	if ( '' !== $also ) {
		kits_theme_option_rows_of( $also );
	}
	echo '</tbody></table>';
	submit_button( 'Save ' . $group );
	echo '</form>';
}

/**
 * The hidden fields of a kits_options form (what settings_fields() prints), coming back to a given screen after the
 * save instead of the one the form is on, so a section shown inside another screen comes back to its place.
 *
 * @param string $back Address relative to wp-admin (options.php adds settings-updated=true to it).
 */
function kits_options_form_fields( string $back ): void {
	if ( function_exists( 'kits_shell_ui_next' ) && ! kits_shell_ui_next() ) {
		$back = add_query_arg( 'ui', 'classic', $back ); // Saved from the earlier screen: come back to it.
	}
	echo '<input type="hidden" name="option_page" value="kits_options"><input type="hidden" name="action" value="update">';
	wp_nonce_field( 'kits_options-options', '_wpnonce', false );
	echo '<input type="hidden" name="_wp_http_referer" value="' . esc_attr( wp_make_link_relative( admin_url( $back ) ) ) . '">';
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
		'sticky_header'  => 'general-header',
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
			// Palette, type, buttons, colour sets and spacing are Styles' (owner, 11 Nov 2026: no copies of WordPress).
			'Visual'   => array(
				'visual-cards' => 'Cards',
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
			// The header part and its menu are WordPress's (Editor → Patterns, Navigation): no pickers here (owner, 11 Nov 2026).
			kits_options_box( 'Header settings', fn() => kits_theme_option_rows_of( 'general-header' ), 'What the header holds: ' . kits_options_link( 'Editor → Patterns → Header', 'site-editor.php?postType=wp_template_part' ) . '. Menus: ' . kits_options_link( 'Editor → Navigation', 'site-editor.php?postType=wp_navigation' ) . '.' );
			return true;
		case 'general-layout':
			kits_options_box( 'Layout', fn() => kits_page_default_rows( '_site', array( 'sidebar', 'title', 'crumbs' ) ), 'Site-wide defaults; each kind of page can differ under Single and Archives. Widths and spacing: ' . kits_options_link( 'Styles → Layout', $styles ) . '.' );
			return true;
		case 'general-footer':
			kits_options_box( 'Footer settings', fn() => kits_theme_option_rows_of( 'general-footer' ), 'What the footer holds: ' . kits_options_link( 'Editor → Patterns → Footer', 'site-editor.php?postType=wp_template_part' ) . '.' );
			return true;
		case 'utility-share':
			kits_options_box( 'Share bar', fn() => kits_theme_option_rows_of( 'utility-share' ) );
			return true;
		case 'utility-performance':
			kits_options_box( 'Performance', fn() => kits_theme_option_rows_of( 'utility-performance' ), 'More speed switches: ' . kits_options_link( 'Appearance → Kits → Settings → Performance', 'admin.php?page=kits-settings#performance' ) . '.' );
			return true;
		case 'utility-extra':
			$has = (bool) array_filter( array_keys( kits_theme_option_fields() ), fn( $k ) => 'utility-extra' === kits_theme_option_section( $k, kits_theme_option_fields()[ $k ] ) );
			if ( $has ) {
				kits_options_box( 'Extra', fn() => kits_theme_option_rows_of( 'utility-extra' ) );
			}
			kits_options_links_box(
				'Extra',
				array(
					array( 'Preview access', 'admin.php?page=kits-settings#preview', 'Who sees preview-only items.' ),
					array( 'Design system', 'admin.php?page=kits-help#design-system', 'Every component and its states.' ),
				)
			);
			return $has;
		case 'utility-socials':
			kits_options_socials();
			return false;
		case 'visual-cards':
			kits_options_cards();
			return false;
	}
	if ( 'single-404' === $section ) {
		kits_options_box( '404 page', fn() => kits_page_default_rows( '_404' ), 'The "page not found" page. Its words: ' . kits_options_link( 'Editor → Templates → 404', 'site-editor.php?postType=wp_template' ) . '.' );
		return true;
	}
	if ( 0 === strpos( $section, 'single-' ) ) {
		$type = substr( $section, 7 );
		kits_options_box( $label . ': each item', fn() => kits_page_default_rows( $type ), 'Every ' . $label . ' item uses these unless it picks its own in the editor (Page Options panel). Its layout: ' . kits_options_link( 'Editor → Templates', 'site-editor.php?postType=wp_template' ) . '.' );
		return true; // The share bar's networks for every type are set once, in Share bar.
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
	kits_options_values_box( 'Cards', $rows ? $rows : array( array( esc_html( 'None yet' ), '' ) ), 'The padding, corners and colours are the ' . kits_block_style_where( (string) $card['block'], (string) $card['variation'] ) . '; the Site Editor\'s Styles doesn\'t list them. The tokens are the theme\'s base values (phones): ' . kits_options_link( 'Help → Design system → Tokens', 'admin.php?page=kits-help&tab=tokens#design-system' ) . '.' );
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
	$where = defined( 'RANK_MATH_VERSION' ) ? kits_options_link( 'Rank Math → Titles & Meta → Social Meta', 'admin.php?page=rank-math-options-titles#setting-panel-social' ) : esc_html( 'Rank Math SEO (not active: see Plugins)' );
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

/** Kits → Help → Docs: what the theme and Kits Core hold and where each is changed (was Kits → Docs). */
function kits_docs_page() {
	kits_admin_head( 'kits-help', 'Docs', 'What the theme and Kits Core hold, and where each is changed.' );
	kits_overview_tables();
	echo '</div>';
}
