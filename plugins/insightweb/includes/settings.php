<?php
/**
 * Search Insights' seven numbers and its API rule, as they were in RankWeb (moved 11 Nov 2026, docs/insightweb-plan.md).
 *
 * The numbers stay where RankWeb stored them: option aw_seo_settings, key 'search-insights', only values that differ
 * from the default. The constants are defined here from that key, with the same defaults. The Settings screen writes
 * only that key (aw_si_settings_save()).
 *
 * @package insightweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The seven numbers: constant => [label, help, default]. As RankWeb's module list had them. */
function aw_si_setting_fields(): array {
	return array(
		'AW_SI_HIGH_TRENDS' => array(
			'label'   => 'High demand: Trends at least',
			'help'    => '',
			'default' => 20,
		),
		'AW_SI_HIGH_IMPR'   => array(
			'label'   => 'High demand: impressions in 28 days at least',
			'help'    => '',
			'default' => 50,
		),
		'AW_SI_QUICK_MIN'   => array(
			'label'   => 'Quick win: impressions at least',
			'help'    => 'At position 8 to 20.',
			'default' => 10,
		),
		'AW_SI_MOVERS'      => array(
			'label'   => 'Listing names shown one by one',
			'help'    => 'The rest are one "entity" row.',
			'default' => 20,
		),
		'AW_SI_PSI_FLAG'    => array(
			'label'   => 'PageSpeed mobile score that flags a page (under)',
			'help'    => '',
			'default' => 50,
		),
		'AW_SI_RM_LOW'      => array(
			'label'   => 'Rank Math SEO score that is "low" (under)',
			'help'    => '',
			'default' => 50,
		),
		'AW_SI_KEEP_DAYS'   => array(
			'label'   => 'Daily figures kept (days)',
			'help'    => 'Older days become monthly totals.',
			'default' => 90,
		),
	);
}

/** The saved numbers (only those that differ from the default; the key may also hold RankWeb's old switch, 'on'). */
function aw_si_settings_saved(): array {
	$all = (array) get_option( 'aw_seo_settings', array() );
	return (array) ( $all['search-insights'] ?? array() );
}

// The constants, from the saved numbers or the defaults.
$aw_si_saved = aw_si_settings_saved();
foreach ( aw_si_setting_fields() as $aw_si_const => $aw_si_field ) {
	if ( ! defined( $aw_si_const ) ) {
		define( $aw_si_const, array_key_exists( $aw_si_const, $aw_si_saved ) ? $aw_si_saved[ $aw_si_const ] : $aw_si_field['default'] );
	}
}
unset( $aw_si_const, $aw_si_field, $aw_si_saved );

/** Whether the current request may use the Search Insights APIs: administrators, or the workers' user (aw_worker). */
function aw_si_api_allowed() {
	return current_user_can( 'manage_options' ) || current_user_can( 'aw_worker' );
}

/**
 * Save the numbers. The Settings screen's route and the admin-post action aw_si_settings both come here.
 * - Only values that differ from the default are stored, so a later default change reaches unchanged fields.
 * - A field not sent keeps its stored value.
 * - Nothing is written when nothing changed (the stored values compared, whatever their order).
 *
 * @param array $posted const => number as sent (strings or integers).
 * @param bool  $reset  Restore defaults: every number goes back to its default.
 * @return bool Whether the option was written.
 */
function aw_si_settings_save( array $posted, bool $reset = false ): bool {
	$all  = (array) get_option( 'aw_seo_settings', array() ); // RankWeb's option: every other key stays as it is.
	$had  = (array) ( $all['search-insights'] ?? array() );
	$mine = $had;
	foreach ( aw_si_setting_fields() as $const => $f ) {
		if ( ! $reset && ! array_key_exists( $const, $posted ) ) {
			continue;
		}
		$value = max( 0, (int) ( $posted[ $const ] ?? 0 ) );
		$now   = array_key_exists( $const, $had ) ? (int) $had[ $const ] : $f['default'];
		if ( ! $reset && $value === $now ) {
			continue; // Unchanged: kept exactly as stored.
		}
		unset( $mine[ $const ] );
		if ( $reset ) {
			continue;
		}
		if ( $value !== $f['default'] ) {
			$mine[ $const ] = $value;
		}
	}
	$a = $mine;
	$b = $had;
	ksort( $a );
	ksort( $b );
	if ( $a === $b ) {
		return false;
	}
	if ( $mine ) {
		$all['search-insights'] = $mine;
	} else {
		unset( $all['search-insights'] );
	}
	update_option( 'aw_seo_settings', $all, true ); // read on every request (the constants), so autoloaded.
	return true;
}

add_action(
	'admin_post_aw_si_settings',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		check_admin_referer( 'aw_si_settings' );
		$reset  = isset( $_POST['reset'] );
		$posted = (array) wp_unslash( $_POST['f'] ?? array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each known field is read as an integer in aw_si_settings_save().
		// The form sends every field; one it leaves out goes back to its default, as before.
		foreach ( aw_si_setting_fields() as $const => $f ) {
			if ( ! isset( $posted[ $const ] ) ) {
				$posted[ $const ] = $f['default'];
			}
		}
		aw_si_settings_save( $posted, $reset );
		wp_safe_redirect( add_query_arg( 'saved', $reset ? 'reset' : 'saved', aw_si_url( 'settings' ) ) );
		exit;
	}
);

/* ---------- The options, registered with the Settings API ---------- */

const INSIGHTWEB_DELETE_DATA = 'insightweb_delete_data'; // 1: uninstall.php deletes InsightWeb's own data. Default 0: it keeps everything.

/**
 * The numbers' part of aw_seo_settings (key 'search-insights') as stored: AW_SI_* whole numbers, 'on' a boolean, any
 * other key kept.
 * Values the screen stored come back unchanged.
 *
 * @param mixed $part The key's value.
 */
function aw_si_settings_part_clean( $part ): array {
	$out = array();
	foreach ( (array) $part as $name => $v ) {
		$name = (string) $name;
		if ( 'on' === $name ) {
			$out['on'] = in_array( $v, array( true, false, 0, 1 ), true ) ? $v : (bool) $v; // stored as it was (an older save left 1)
		} elseif ( 0 === strpos( $name, 'AW_SI_' ) ) {
			$out[ $name ] = max( 0, (int) $v );
		} else {
			$out[ $name ] = is_string( $v ) ? sanitize_text_field( $v ) : $v; // a key no field knows: kept
		}
	}
	return $out;
}

/**
 * Sanitize callback of aw_seo_settings when RankWeb is not loaded to register it (RankWeb's own callback cleans the
 * same key the same way). Only the 'search-insights' key is InsightWeb's: the other keys are RankWeb's and stay as
 * they are.
 *
 * @param mixed $value Value to store.
 */
function aw_si_settings_sanitize( $value ): array {
	$value = (array) $value;
	if ( isset( $value['search-insights'] ) ) {
		$value['search-insights'] = aw_si_settings_part_clean( $value['search-insights'] );
		if ( ! $value['search-insights'] ) {
			unset( $value['search-insights'] );
		}
	}
	return $value;
}

/**
 * Every option the InsightWeb Settings screen saves, as register_setting() arguments. The names and stored shapes are
 * the ones used before (no migration); the default is what the code reads when the option is missing.
 */
function insightweb_registered_settings(): array {
	return array(
		'aw_si_gdelt_on'       => array(
			'type'              => 'boolean',
			'description'       => 'Use GDELT for Rising.',
			'default'           => false,
			'sanitize_callback' => fn( $v ) => rest_sanitize_boolean( $v ) ? 1 : 0,
			'show_in_rest'      => array( 'schema' => array( 'type' => 'boolean' ) ),
		),
		'aw_si_tool_seeds'     => array(
			'type'              => 'string',
			'description'       => 'Seeds for keyword tools, one search per line.',
			'default'           => '',
			'sanitize_callback' => fn( $v ) => function_exists( 'aw_si_tool_seeds_clean' ) ? aw_si_tool_seeds_clean( sanitize_textarea_field( (string) $v ) ) : sanitize_textarea_field( (string) $v ),
			'show_in_rest'      => array( 'schema' => array( 'type' => 'string' ) ),
		),
		INSIGHTWEB_DELETE_DATA => array(
			'type'              => 'boolean',
			'description'       => 'Delete InsightWeb\'s own data when the plugin is deleted.',
			'default'           => false,
			'sanitize_callback' => fn( $v ) => rest_sanitize_boolean( $v ) ? 1 : 0,
			'show_in_rest'      => array( 'schema' => array( 'type' => 'boolean' ) ),
		),
	);
}

add_action(
	'init',
	function () {
		foreach ( insightweb_registered_settings() as $name => $args ) {
			register_setting( 'insightweb', $name, $args );
		}
	}
);

// The seven numbers live in RankWeb's option. RankWeb registers it (its callback cleans this key too); without RankWeb,
// InsightWeb registers it, cleaning only its own key.
add_action(
	'init',
	function () {
		if ( isset( get_registered_settings()['aw_seo_settings'] ) ) {
			return;
		}
		register_setting(
			'insightweb',
			'aw_seo_settings',
			array(
				'type'              => 'object',
				'description'       => 'RankWeb settings by module; key search-insights holds InsightWeb\'s numbers.',
				'default'           => array(),
				'sanitize_callback' => 'aw_si_settings_sanitize',
				'show_in_rest'      => array(
					'schema' => array(
						'type'                 => 'object',
						'additionalProperties' => array(
							'type'                 => 'object',
							'additionalProperties' => true,
						),
					),
				),
			)
		);
	},
	20
);

/** Whether InsightWeb's data goes when the plugin is deleted (Settings → Numbers → Keeping data). */
function insightweb_delete_data_on(): bool {
	return (bool) get_option( INSIGHTWEB_DELETE_DATA, 0 );
}

/**
 * Save "Delete this plugin's data when it is deleted". Nothing is written when it is already so.
 *
 * @param bool $on On.
 * @return bool Whether the option was written.
 */
function insightweb_delete_data_save( bool $on ): bool {
	if ( insightweb_delete_data_on() === $on ) {
		return false;
	}
	update_option( INSIGHTWEB_DELETE_DATA, $on ? 1 : 0, false );
	return true;
}
