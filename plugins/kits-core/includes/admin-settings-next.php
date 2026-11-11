<?php
/**
 * Kits → Settings in the options panel (trial): admin.php?page=kits-settings&ui=next.
 *
 * The panel's pattern (docs/kits-plugin-ui.md, Options panel): a dark bar with the six Kits tabs, the screen's
 * sections listed on the left (Performance, Compatibility, Preview access), each section's settings in groups that fold
 * open and shut, and On/Off toggles for the switches. Without &ui=next the current screen shows; it stays the default
 * until the owner switches.
 *
 * The values are today's: kits_theme_options, the fields of the groups the current screen shows (Speed, the font
 * preload, Older content). Both screens save through the option's one sanitize callback (kits_theme_options_clean), and
 * the new one sends only the fields that were changed, so an unchanged Save writes nothing.
 *
 * Route: kits/v1/settings (GET, POST), manage_options, with the wp_rest nonce (kits_shell_rest_can()).
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** This request asks for the new Settings screen. */
function kits_settings_next_wanted(): bool {
	$page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which screen to show; nothing is saved here.
	return 'kits-settings' === $page && kits_shell_ui_next();
}

/**
 * The new screen's script is enqueued on this request. Set on admin_enqueue_scripts.
 *
 * @param bool|null $set Set the flag.
 */
function kits_settings_next_ready( ?bool $set = null ): bool {
	static $ready = false;
	if ( null !== $set ) {
		$ready = $set;
	}
	return $ready;
}

/**
 * The panel's sections and the field groups in each: section => [ label, note, [ group label => [ field keys ] ] ].
 * The same fields the current screen shows (kits_core_settings_form()).
 */
function kits_settings_next_sections(): array {
	$speed = array();
	$old   = array();
	$font  = array();
	foreach ( kits_theme_option_fields() as $key => $f ) {
		if ( ! in_array( $f['kind'], array( 'switch', 'choice' ), true ) ) {
			continue; // The panel holds On/Off and choices; this screen has no other kind.
		}
		$where = kits_theme_option_section( $key, $f );
		if ( 'utility-performance' === $where ) {
			$font[] = $key;
		} elseif ( 'core' === $where && 'Speed' === $f['group'] ) {
			$speed[] = $key;
		} elseif ( 'core' === $where && 'Older content' === $f['group'] ) {
			$old[] = $key;
		}
	}
	return array(
		'performance'   => array(
			__( 'Performance', 'kits-core' ),
			__( 'Speed switches', 'kits-core' ),
			array_filter(
				array(
					__( 'Scripts and styles', 'kits-core' ) => $speed,
					__( 'Fonts', 'kits-core' ) => $font,
				)
			),
		),
		'compatibility' => array(
			__( 'Compatibility', 'kits-core' ),
			__( 'Older content', 'kits-core' ),
			array_filter( array( __( 'Older content', 'kits-core' ) => $old ) ),
		),
	);
}

/** Every field key the panel may save. */
function kits_settings_next_keys(): array {
	$keys = array();
	foreach ( kits_settings_next_sections() as $s ) {
		foreach ( $s[2] as $list ) {
			$keys = array_merge( $keys, $list );
		}
	}
	return $keys;
}

/** What the screen needs: sections, groups, fields with their values and defaults. */
function kits_settings_next_payload(): array {
	$fields = kits_theme_option_fields();
	$saved  = kits_theme_options_saved();
	$out    = array();
	foreach ( kits_settings_next_sections() as $slug => list( $label, $note, $groups ) ) {
		$g = array();
		foreach ( $groups as $title => $keys ) {
			$list = array();
			foreach ( $keys as $key ) {
				$f       = $fields[ $key ];
				$default = (string) kits_theme_option_inherited( $key );
				$choices = array();
				foreach ( (array) $f['choices'] as $v => $words ) {
					$choices[] = array(
						'value' => (string) $v,
						'label' => (string) $words,
					);
				}
				$list[] = array(
					'key'     => $key,
					'label'   => (string) $f['label'],
					'kind'    => (string) $f['kind'],
					'note'    => (string) $f['note'],
					'choices' => $choices,
					'default' => $default,
					'value'   => isset( $saved[ $key ] ) && is_scalar( $saved[ $key ] ) ? (string) $saved[ $key ] : $default,
				);
			}
			$g[] = array(
				'title'  => (string) $title,
				'fields' => $list,
			);
		}
		$out[] = array(
			'slug'   => $slug,
			'label'  => $label,
			'note'   => $note,
			'groups' => $g,
		);
	}
	return array( 'sections' => $out );
}

/**
 * Save the changed fields through the option's own sanitize callback, which keeps every other field as it is.
 *
 * @param WP_REST_Request $request { values: { key: value } }.
 */
function kits_settings_next_save( WP_REST_Request $request ) {
	$values = $request->get_param( 'values' );
	if ( ! is_array( $values ) ) {
		return new WP_Error( 'kits_bad_values', __( 'Nothing to save.', 'kits-core' ), array( 'status' => 400 ) );
	}
	$allowed = array_flip( kits_settings_next_keys() );
	$send    = array();
	foreach ( $values as $key => $v ) {
		if ( isset( $allowed[ $key ] ) && is_scalar( $v ) ) {
			$send[ (string) $key ] = (string) $v;
		}
	}
	$changed = false;
	if ( $send ) {
		$before  = kits_theme_options_saved();
		$after   = kits_theme_options_clean( $send );
		$changed = $after !== $before;
		if ( $changed ) {
			update_option( 'kits_theme_options', $send ); // The sanitize callback merges $send into the saved values, once.
		}
	}
	return rest_ensure_response( array( 'saved' => $changed ) + kits_settings_next_payload() );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'kits/v1',
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => kits_shell_rest_can( 'manage_options' ),
					'callback'            => fn() => rest_ensure_response( kits_settings_next_payload() ),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => kits_shell_rest_can( 'manage_options' ),
					'callback'            => 'kits_settings_next_save',
					'args'                => array(
						'values' => array(
							'type'     => 'object',
							'required' => true,
						),
					),
				),
			)
		);
	}
);

add_action(
	'admin_enqueue_scripts',
	function () {
		if ( ! kits_settings_next_wanted() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tabs = array();
		foreach ( kits_admin_tabs_list() as $slug => $t ) {
			if ( ! current_user_can( (string) ( $t[2] ?? 'manage_options' ) ) ) {
				continue;
			}
			$tabs[] = array(
				'slug'    => (string) $slug,
				'label'   => (string) $t[0],
				'url'     => 'kits-settings' === $slug ? add_query_arg( 'ui', 'next', admin_url( (string) $t[1] ) ) : admin_url( (string) $t[1] ),
				'current' => 'kits-settings' === $slug,
			);
		}
		$ok = kits_shell_app(
			array(
				'handle'      => 'kits-settings-next',
				'src'         => plugins_url( 'build/settings-next/index.js', KITS_CORE_DIR . 'kits-core.php' ),
				'asset'       => KITS_CORE_DIR . 'build/settings-next/index.asset.php',
				'object'      => 'kitsSettingsNext',
				'text_domain' => 'kits-core',
				'data'        => array(
					'icon'    => plugins_url( 'assets/icon.svg', KITS_CORE_DIR . 'kits-core.php' ),
					'tabs'    => $tabs,
					'route'   => 'kits/v1/settings',
					'current' => admin_url( 'admin.php?page=kits-settings' ),
					'preview' => admin_url( 'admin.php?page=kits-settings#preview' ),
				),
			)
		);
		kits_settings_next_ready( $ok );
	}
);
