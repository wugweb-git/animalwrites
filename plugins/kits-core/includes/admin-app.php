<?php
/**
 * Kits admin app: the Theme Options screen (docs/admin-ui-spec.md, phase 1), built with WordPress packages from
 * src/theme-options/ into build/theme-options/ (npm run build in this plugin's folder; build/ is committed).
 *
 * Kits → Theme Options opens it. It edits the fields of kits_theme_options (theme-options.php), Identity (site title,
 * tagline, logo, site icon: WordPress's own options) and the site-wide header, footer and layout choices
 * (kits_page_defaults._site), and saves them through /wp/v2/settings, which runs the same clean-up as options.php
 * (kits_theme_options_clean). Without the build, the screen shows one notice.
 *
 * Sections not built in the app yet (Colours, Typography, Buttons, Cards, Content types, Socials, Share bar) show
 * today's fields under their names: kits_admin_app_legacy() prints them on the page, the app moves each into its
 * section, and each saves with its own button through options.php as before. So no field is out of reach. A site adds
 * to a section through the kits_options_legacy_section action (Animal Writes: Contact links under Socials).
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** True on Kits → Theme Options. */
function kits_admin_app_wanted(): bool {
	return isset( $_GET['page'] ) && 'kits-options' === $_GET['page']; // phpcs:ignore WordPress.Security.NonceVerification -- which screen to show; nothing is saved here.
}

/**
 * The app's sections that still show today's fields: slug => [label, prints the fields]. In the spec's order (§B2);
 * any Theme Options section a plugin added (kits_options_sections) that has no place here goes under "More".
 */
function kits_admin_app_legacy(): array {
	$old  = kits_options_sections();
	$form = function ( string $section, string $label, string $back ) {
		return function () use ( $section, $label, $back ) {
			echo '<form method="post" action="options.php">';
			kits_options_form_fields( 'admin.php?page=kits-options&kits_section=' . rawurlencode( $back ) );
			if ( kits_options_section_body( $section, $label ) ) {
				submit_button( 'Save ' . $label );
			}
			echo '</form>';
		};
	};
	$types = function () use ( $old, $form ) {
		echo '<p>' . esc_html( 'Each kind of page, its list page, 404, Search and Authors. Open one to change it.' ) . '</p>';
		kits_options_box_titled( false ); // The summary names each box.
		foreach ( array( 'Single', 'Archives' ) as $group ) {
			foreach ( (array) ( $old[ $group ] ?? array() ) as $slug => $label ) {
				echo '<details><summary><strong>' . esc_html( ( 'Single' === $group ? 'Single: ' : 'List: ' ) . $label ) . '</strong></summary>';
				$form( (string) $slug, (string) $label, 'content-types' )();
				echo '</details>';
			}
		}
		kits_options_box_titled( true );
	};
	$list = array(
		// Colours, Typography, Buttons and Cards are built in the app (src/theme-options/sections/).
		'content-types' => array( __( 'Content types', 'kits-core' ), $types ),
		'socials'       => array(
			__( 'Socials', 'kits-core' ),
			function () use ( $form ) {
				$form( 'utility-socials', 'Socials', 'socials' )();
				/**
				 * Prints more of a Theme Options section that still shows today's fields (e.g. a site's own list).
				 *
				 * @param string $slug The app section: socials, colours, …
				 */
				do_action( 'kits_options_legacy_section', 'socials' );
			},
		),
		'share'         => array( __( 'Share bar', 'kits-core' ), $form( 'utility-share', 'Share bar', 'share' ) ),
	);
	// Sections a plugin added (kits_options_sections) that have no place above.
	$placed = array( 'General', 'Single', 'Archives', 'Visual', 'Utility' );
	$more   = array();
	foreach ( $old as $group => $items ) {
		if ( ! in_array( $group, $placed, true ) ) {
			foreach ( (array) $items as $slug => $label ) {
				$more[ (string) $slug ] = (string) $label;
			}
		}
	}
	if ( $more ) {
		$list['more'] = array(
			__( 'More', 'kits-core' ),
			function () use ( $more, $form ) {
				foreach ( $more as $slug => $label ) {
					echo '<h3>' . esc_html( $label ) . '</h3>';
					$form( $slug, $label, 'more' )();
				}
			},
		);
	}
	return $list;
}

/**
 * The app section an older Theme Options address (&section=…) means, or '' for the first.
 *
 * @param string $old Older section slug.
 */
function kits_admin_app_section_of( string $old ): string {
	$map = array(
		'general-header'    => 'header',
		'general-footer'    => 'footer',
		'general-layout'    => 'layout',
		'visual-spacing'    => 'layout',
		'visual-cards'      => 'cards',
		'utility-socials'   => 'socials',
		'utility-share'     => 'share',
	);
	if ( isset( $map[ $old ] ) ) {
		return $map[ $old ];
	}
	return ( 0 === strpos( $old, 'single-' ) || 0 === strpos( $old, 'archive-' ) ) ? 'content-types' : '';
}

// An older Theme Options section that moved to another screen: font preload to Settings, Extra to Help.
add_action(
	'admin_init',
	function () {
		if ( ! kits_admin_app_wanted() || ! isset( $_GET['section'] ) || 'get' !== sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- which screen to show.
			return;
		}
		$moved = array(
			'utility-performance' => 'admin.php?page=kits-settings#performance',
			'utility-extra'       => 'admin.php?page=kits-help#developer/extra',
		);
		$old   = sanitize_key( wp_unslash( $_GET['section'] ) ); // phpcs:ignore WordPress.Security.NonceVerification -- which screen to show.
		if ( isset( $moved[ $old ] ) ) {
			wp_safe_redirect( admin_url( $moved[ $old ] ), 302, 'Kits' );
			exit;
		}
	},
	2
);

// The new screen's script, only on that screen.
add_action(
	'admin_enqueue_scripts',
	function () {
		if ( ! kits_admin_app_wanted() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$dir   = KITS_CORE_DIR . 'build/theme-options/';
		$asset = $dir . 'index.asset.php';
		if ( ! is_readable( $asset ) ) {
			return;
		}

		$saved     = kits_theme_options_saved();
		$fields    = kits_theme_option_fields();
		$inherited = array();
		foreach ( $fields as $key => $f ) {
			$inherited[ $key ] = kits_theme_option_inherited( $key );
		}
		$brand = kits_frame_brand();
		$theme = wp_get_theme( get_template() );
		$core  = get_file_data( KITS_CORE_DIR . 'kits-core.php', array( 'Version' => 'Version' ) );
		$pd_saved = (array) get_option( 'kits_page_defaults', array() );
		$pd_site  = array();
		$pd_keys  = array( 'sidebar', 'title', 'crumbs' ); // Header and footer parts are chosen in the Site Editor.
		$pd       = array();
		$names    = kits_page_option_fields();
		foreach ( $pd_keys as $key ) {
			$pd[ $key ] = array(
				'label'   => $names[ $key ][0],
				'note'    => kits_page_option_note( $key ),
				'choices' => (object) kits_page_option_choices( $key ),
			);
			if ( isset( $pd_saved['_site'][ $key ] ) ) {
				$pd_site[ $key ] = (string) $pd_saved['_site'][ $key ];
			}
		}
		$legacy = array();
		foreach ( kits_admin_app_legacy() as $slug => $l ) {
			if ( 'layout-spacing' !== $slug ) {
				$legacy[] = array(
					'slug'  => (string) $slug,
					'label' => (string) $l[0],
				);
			}
		}
		// Fields shown on another screen: the Core Settings groups and font preload are on Kits → Settings.
		$elsewhere = array();
		foreach ( $fields as $key => $f ) {
			if ( in_array( kits_theme_option_section( $key, $f ), array( 'core', 'utility-performance' ), true ) ) {
				$elsewhere[] = (string) $key;
			}
		}
		$asked = isset( $_GET['kits_section'] ) ? sanitize_key( wp_unslash( $_GET['kits_section'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- which section to show.
		if ( '' === $asked && isset( $_GET['section'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- an older address.
			$asked = kits_admin_app_section_of( sanitize_key( wp_unslash( $_GET['section'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification -- an older address.
		}
		$data = array(
			'site'         => get_bloginfo( 'name' ),
			'theme'        => $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ),
			'coreVersion'  => (string) $core['Version'],
			'tabs'         => kits_frame_tabs( 'kits-options' ),
			'icon'         => $brand['icon'],
			'version'      => $brand['version'],
			'fields'       => $fields,
			'saved'        => (object) $saved,
			'inherited'    => (object) $inherited,
			'pageDefaults' => array(
				'fields' => $pd,
				'site'   => (object) $pd_site,
			),
			'layoutInfo'   => array(
				array( __( 'Breakpoints', 'kits-core' ), __( 'phones up to 767 px, tablets 768 to 1199 px, wide screens from 1200 px (fixed in the theme)', 'kits-core' ) ),
			),
			'links'        => array( // The exact Site Editor place for each section (kits_site_editor_path(), admin-screens.php).
				'parts'     => admin_url( 'site-editor.php?postType=wp_template_part' ),
				'header'    => admin_url( kits_site_editor_path( 'wp_template_part', 'header' ) ),
				'footer'    => admin_url( kits_site_editor_path( 'wp_template_part', 'footer' ) ),
				'templates' => admin_url( 'site-editor.php?postType=wp_template' ),
				'menus'     => admin_url( 'site-editor.php?postType=wp_navigation' ),
				'styles'    => admin_url( 'site-editor.php?path=/wp_global_styles' ),
			),
			'legacy'       => $legacy,
			'legacyInto'   => array(), // a built section can show today's fields of another below its own
			'elsewhere'    => $elsewhere,
			'initial'      => $asked,
		);
		$data = (array) apply_filters( 'kits_admin_app_data', $data ); // Sections in their own files add their data.
		// On the Kits plugins' look: the shell's header, chips and cards (docs/kits-plugin-ui.md).
		$ok = kits_shell_app(
			array(
				'handle'      => 'kits-admin-theme-options',
				'src'         => plugins_url( 'build/theme-options/index.js', KITS_CORE_DIR . 'kits-core.php' ),
				'asset'       => $asset,
				'object'      => 'kitsAdminApp',
				'data'        => $data,
				'text_domain' => 'kits-core',
			)
		);
		if ( $ok ) {
			wp_enqueue_style( 'wp-components' );
		}
		kits_admin_app_ready( $ok );
	}
);

/**
 * The screen's script is enqueued on this request. Set on admin_enqueue_scripts.
 *
 * @param bool|null $set Set the flag.
 */
function kits_admin_app_ready( ?bool $set = null ): bool {
	static $ready = false;
	if ( null !== $set ) {
		$ready = $set;
	}
	return $ready;
}

/** The screen's mount point (kits_options_page() calls this), and today's fields of the sections not built yet. */
function kits_admin_app_page(): void {
	if ( ! kits_admin_app_ready() ) {
		// The screen's script or the shell is not built (or a script it needs is missing).
		echo '<div class="wrap"><h1>' . esc_html__( 'Theme Options', 'kits-core' ) . '</h1>';
		wp_admin_notice( esc_html__( 'Kits Core needs its build (build/theme-options/ and build/shell/) to show this screen.', 'kits-core' ), array( 'type' => 'error' ) );
		echo '</div>';
		return;
	}
	// Notices from other plugins go after .wp-header-end, so they stay out of the app and its pinned header.
	echo '<div class="wrap"><h1 class="screen-reader-text">' . esc_html__( 'Theme Options', 'kits-core' ) . '</h1><hr class="wp-header-end">';
	settings_errors(); // "Settings saved." after a section that saves through options.php.
	echo '<div id="kits-admin-app">';
	echo '<noscript><h1>' . esc_html__( 'Theme Options', 'kits-core' ) . '</h1><p>' . esc_html__( 'This screen needs JavaScript.', 'kits-core' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=kits-tools' ) ) . '">' . esc_html__( 'Export the settings', 'kits-core' ) . '</a></p></noscript>';
	echo '</div>';
	// The app moves each of these into its section; until it runs they stay hidden.
	echo '<div id="kits-legacy-holder" hidden>';
	foreach ( kits_admin_app_legacy() as $slug => $l ) {
		echo '<div data-kits-legacy="' . esc_attr( (string) $slug ) . '">';
		call_user_func( $l[1] );
		echo '</div>';
	}
	echo '</div></div>';
}

// The way back: in the Site Editor, the header and footer parts and the page templates link to their Theme Options
// section (assets/js/site-editor-links.js: a document sidebar panel, core components, no build step).
add_action(
	'enqueue_block_editor_assets',
	function () {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'site-editor' !== $screen->base || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$to = fn( string $section ) => admin_url( 'admin.php?page=kits-options#' . $section );
		wp_enqueue_script(
			'kits-site-editor-links',
			plugins_url( 'assets/js/site-editor-links.js', KITS_CORE_DIR . 'kits-core.php' ),
			array( 'wp-plugins', 'wp-editor', 'wp-element', 'wp-data' ),
			(string) filemtime( KITS_CORE_DIR . 'assets/js/site-editor-links.js' ),
			true
		);
		wp_localize_script(
			'kits-site-editor-links',
			'kitsSiteEditorLinks',
			array(
				'title' => __( 'Kits Theme Options', 'kits-core' ),
				'links' => array(
					'header'    => array(
						'text'  => __( 'The header’s switches (announcement bar, header stays at the top) are in', 'kits-core' ),
						'label' => __( 'Kits → Theme Options → Header', 'kits-core' ),
						'url'   => $to( 'header' ),
					),
					'footer'    => array(
						'text'  => __( 'The phone bar at the foot of phone screens is set in', 'kits-core' ),
						'label' => __( 'Kits → Theme Options → Footer', 'kits-core' ),
						'url'   => $to( 'footer' ),
					),
					'templates' => array(
						'text'  => __( 'Sidebar, title and breadcrumbs for each kind of page, and the share bar, are set in', 'kits-core' ),
						'label' => __( 'Kits → Theme Options → Content types', 'kits-core' ),
						'url'   => $to( 'content-types' ),
					),
				),
			)
		);
	}
);
