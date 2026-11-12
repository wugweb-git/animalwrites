<?php
/**
 * Search Insights' seven numbers and its API rule, as they were in RankWeb (moved 11 Nov 2026, docs/insightweb-plan.md).
 *
 * The numbers stay where RankWeb stored them: option aw_seo_settings, key 'search-insights', only values that differ
 * from the default. The constants are defined here from that key, with the same defaults. The form on Search Insights →
 * Settings prints the same fields and writes only that key.
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

/** The numbers as a form on Search Insights → Settings, as RankWeb's embedded form printed them. */
function aw_si_settings_form(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$saved = aw_si_settings_saved();
	$any   = false;
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="aw_si_settings">';
	wp_nonce_field( 'aw_si_settings' );
	echo '<table class="form-table" role="presentation"><tbody>';
	foreach ( aw_si_setting_fields() as $const => $f ) {
		$has     = array_key_exists( $const, $saved );
		$value   = $has ? (int) $saved[ $const ] : $f['default'];
		$any     = $any || $has;
		$changed = $has && $value !== $f['default'];
		$id      = 'aw-seo-' . strtolower( str_replace( '_', '-', $const ) );
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $f['label'] ) . '</label></th><td>';
		echo '<input type="number" min="0" class="small-text" id="' . esc_attr( $id ) . '" name="f[' . esc_attr( $const ) . ']" value="' . esc_attr( (string) $value ) . '">';
		echo '<p class="description">' . esc_html( trim( $f['help'] . ( $changed ? ' Changed from the default.' : '' ) ) ) . '</p></td></tr>';
	}
	echo '</tbody></table>';
	submit_button( 'Save changes', 'primary', 'submit', false );
	if ( $any ) {
		echo ' ';
		submit_button( 'Restore defaults', 'secondary', 'reset', false );
	}
	echo '</form>';
}

/**
 * Save the numbers. The form on Search Insights → Settings and the Settings screen's route both come here.
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
