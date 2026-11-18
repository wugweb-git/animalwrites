<?php
/**
 * The Settings tab (WordPress Settings API): the decision thresholds the engine uses and the "delete data on uninstall"
 * opt-in. Stored in the plugin's own options; the thresholds are read into the INSIGHTWEB_HIGH_TRENDS / HIGH_IMPR /
 * QUICK_MIN constants at load, so the decision engine (compute.php) and the daily recompute pick them up.
 *
 * The form posts to WordPress's own options.php (nonce + capability handled by core) and returns to the Settings tab.
 *
 * @package InsightWeb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const INSIGHTWEB_SETTINGS_OPTION = 'insightweb_settings';
const INSIGHTWEB_SETTINGS_GROUP  = 'insightweb_settings';
const INSIGHTWEB_SETTINGS_PAGE   = 'insightweb-settings';

/**
 * The threshold defaults (the same values compute.php falls back to).
 *
 * @return array<string, int>
 */
function insightweb_settings_defaults(): array {
	return array(
		'high_trends' => 20,
		'high_impr'   => 50,
		'quick_min'   => 10,
	);
}

/**
 * The stored thresholds merged over the defaults.
 *
 * @return array<string, int>
 */
function insightweb_settings(): array {
	$stored = get_option( INSIGHTWEB_SETTINGS_OPTION, array() );
	$out    = insightweb_settings_defaults();
	foreach ( $out as $key => $default ) {
		if ( is_array( $stored ) && isset( $stored[ $key ] ) ) {
			$out[ $key ] = max( 0, (int) $stored[ $key ] );
		}
	}
	return $out;
}

// Make the stored thresholds drive the engine: define the constants before compute.php sets its defaults.
$insightweb_thresholds = insightweb_settings();
if ( ! defined( 'INSIGHTWEB_HIGH_TRENDS' ) ) {
	define( 'INSIGHTWEB_HIGH_TRENDS', $insightweb_thresholds['high_trends'] );
}
if ( ! defined( 'INSIGHTWEB_HIGH_IMPR' ) ) {
	define( 'INSIGHTWEB_HIGH_IMPR', $insightweb_thresholds['high_impr'] );
}
if ( ! defined( 'INSIGHTWEB_QUICK_MIN' ) ) {
	define( 'INSIGHTWEB_QUICK_MIN', $insightweb_thresholds['quick_min'] );
}

add_action( 'admin_init', 'insightweb_settings_register' );

/**
 * Register the settings, section and fields.
 *
 * @return void
 */
function insightweb_settings_register(): void {
	register_setting(
		INSIGHTWEB_SETTINGS_GROUP,
		INSIGHTWEB_SETTINGS_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'insightweb_settings_sanitize',
			'default'           => array(),
		)
	);
	register_setting(
		INSIGHTWEB_SETTINGS_GROUP,
		'insightweb_delete_data',
		array(
			'type'              => 'boolean',
			'sanitize_callback' => 'insightweb_settings_sanitize_bool',
			'default'           => 0,
		)
	);

	add_settings_section(
		'insightweb_thresholds',
		__( 'Decision thresholds', 'insightweb' ),
		'insightweb_settings_thresholds_intro',
		INSIGHTWEB_SETTINGS_PAGE
	);
	$fields = array(
		'high_trends' => array( __( 'High-demand Trends index', 'insightweb' ), __( 'A keyword counts as high demand at or above this Google Trends index.', 'insightweb' ) ),
		'high_impr'   => array( __( 'High-demand impressions (28 days)', 'insightweb' ), __( 'Or at or above this many Search Console impressions in 28 days.', 'insightweb' ) ),
		'quick_min'   => array( __( 'Quick-win minimum impressions', 'insightweb' ), __( 'A page ranking 8–20 is a quick win at or above this many impressions.', 'insightweb' ) ),
	);
	foreach ( $fields as $key => $meta ) {
		add_settings_field(
			'insightweb_' . $key,
			esc_html( $meta[0] ),
			'insightweb_settings_number_field',
			INSIGHTWEB_SETTINGS_PAGE,
			'insightweb_thresholds',
			array(
				'key'         => $key,
				'description' => $meta[1],
				'label_for'   => 'insightweb-field-' . $key,
			)
		);
	}

	add_settings_section(
		'insightweb_data',
		__( 'Data', 'insightweb' ),
		'__return_false',
		INSIGHTWEB_SETTINGS_PAGE
	);
	add_settings_field(
		'insightweb_delete_data',
		__( 'On uninstall', 'insightweb' ),
		'insightweb_settings_delete_field',
		INSIGHTWEB_SETTINGS_PAGE,
		'insightweb_data',
		array( 'label_for' => 'insightweb-delete-data' )
	);
}

/**
 * Sanitise the thresholds array: non-negative integers only, known keys only.
 *
 * @param mixed $value Submitted value.
 * @return array<string, int>
 */
function insightweb_settings_sanitize( $value ): array {
	$value = is_array( $value ) ? $value : array();
	$out   = array();
	foreach ( insightweb_settings_defaults() as $key => $default ) {
		$out[ $key ] = isset( $value[ $key ] ) ? max( 0, (int) $value[ $key ] ) : $default;
	}
	return $out;
}

/**
 * Sanitise the delete-data flag to 0 or 1.
 *
 * @param mixed $value Submitted value.
 * @return int
 */
function insightweb_settings_sanitize_bool( $value ): int {
	return empty( $value ) ? 0 : 1;
}

/**
 * Intro line for the thresholds section.
 *
 * @return void
 */
function insightweb_settings_thresholds_intro(): void {
	echo '<p class="description">' . esc_html__( 'These decide a keyword’s priority, status and suggested action. Suggestions only — nothing on your site changes.', 'insightweb' ) . '</p>';
}

/**
 * Render one threshold number field.
 *
 * @param array<string, mixed> $args key, description, label_for.
 * @return void
 */
function insightweb_settings_number_field( array $args ): void {
	$key     = (string) ( $args['key'] ?? '' );
	$current = insightweb_settings();
	printf(
		'<input type="number" min="0" step="1" id="%1$s" name="%2$s[%3$s]" value="%4$s" class="small-text">',
		esc_attr( (string) ( $args['label_for'] ?? '' ) ),
		esc_attr( INSIGHTWEB_SETTINGS_OPTION ),
		esc_attr( $key ),
		esc_attr( (string) ( $current[ $key ] ?? 0 ) )
	);
	if ( ! empty( $args['description'] ) ) {
		echo '<p class="description">' . esc_html( (string) $args['description'] ) . '</p>';
	}
}

/**
 * Render the delete-data checkbox.
 *
 * @param array<string, mixed> $args label_for.
 * @return void
 */
function insightweb_settings_delete_field( array $args ): void {
	printf(
		'<label><input type="checkbox" id="%1$s" name="insightweb_delete_data" value="1" %2$s> %3$s</label>',
		esc_attr( (string) ( $args['label_for'] ?? '' ) ),
		checked( (int) get_option( 'insightweb_delete_data' ), 1, false ),
		esc_html__( 'Delete InsightWeb’s tables and options when the plugin is deleted. Off by default; your data is kept unless you tick this.', 'insightweb' )
	);
}

/**
 * The Settings tab as a block of HTML (the shell draws the same markup as a "part").
 *
 * @return string
 */
function insightweb_settings_html(): string {
	ob_start();
	settings_errors( INSIGHTWEB_SETTINGS_OPTION );
	echo '<form method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '">';
	settings_fields( INSIGHTWEB_SETTINGS_GROUP );
	do_settings_sections( INSIGHTWEB_SETTINGS_PAGE );
	submit_button();
	echo '</form>';
	return (string) ob_get_clean();
}
