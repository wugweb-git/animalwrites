<?php
/**
 * InsightWeb's own service keys (owner, 18 Nov 2026: every Wugweb plugin keeps its own keys, like Yoast, Rank Math and
 * Site Kit; the shared Kits → Keys vault is retired, migrate first and delete second). Plan: docs/keys-per-plugin.md.
 *
 * A key's value, first found wins:
 *   1. a wp-config.php constant of the same name (not empty);
 *   2. InsightWeb's own option insightweb_keys (name => value, not autoloaded), set on Settings → Keys;
 *   3. the old shared option aw_keys (Kits → Keys), read directly, so the live keys keep working until each one is
 *      copied over (the copy step in docs/keys-per-plugin.md, stage 3) and the vault is retired.
 * A value is never printed, logged, exported or sent over REST: the screen only says whether a key is set and where.
 * Administrators only (manage_options), as on Kits → Keys.
 *
 * @package insightweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const INSIGHTWEB_KEYS_OPTION = 'insightweb_keys';

/** The old shared vault's option (Animal Writes Core's includes/keys.php), read only, until it is retired. */
const INSIGHTWEB_KEYS_OLD_OPTION = 'aw_keys';

/** InsightWeb's key names (the wp-config.php constants), as insightweb_key_fields() lists them. */
const INSIGHTWEB_KEY_NAMES = array( 'AW_CLARITY_TOKEN', 'AW_BING_WEBMASTER_KEY', 'AW_PSI_KEY', 'AW_GOOGLE_ALERTS_FEED_1', 'AW_GOOGLE_ALERTS_FEED_2' );

/**
 * InsightWeb's keys: constant name => [label, where to find it]. The names are the wp-config.php constants already in
 * use, so a constant set today keeps working.
 *
 * @return array<string, array{0: string, 1: string}>
 */
function insightweb_key_fields(): array {
	$keys = array(
		'AW_CLARITY_TOKEN'      => array( __( 'Clarity Data Export token', 'insightweb' ), __( 'Clarity → Settings → Data Export → Generate new API token.', 'insightweb' ) ),
		'AW_BING_WEBMASTER_KEY' => array( __( 'Bing Webmaster API key', 'insightweb' ), __( 'Bing Webmaster Tools → Settings (gear) → API access. Not an IndexNow key.', 'insightweb' ) ),
		'AW_PSI_KEY'            => array( __( 'PageSpeed Insights API key (Google, optional)', 'insightweb' ), __( 'Google Cloud → APIs → PageSpeed Insights API → Credentials. Only needed if Site Kit\'s PageSpeed can\'t be used.', 'insightweb' ) ),
	);
	foreach ( array( 'AW_GOOGLE_ALERTS_FEED_1', 'AW_GOOGLE_ALERTS_FEED_2' ) as $i => $name ) {
		/* translators: %d: 1 or 2. */
		$keys[ $name ] = array( sprintf( __( 'Google Alerts feed %d (RSS address)', 'insightweb' ), $i + 1 ), __( 'google.com/alerts → create an alert → Show options → Deliver to: RSS feed → copy the feed address (https://www.google.com/alerts/feeds/…).', 'insightweb' ) );
	}
	return $keys;
}

/**
 * A constant's value when it is defined and not empty, else ''.
 *
 * @param string $name Constant name.
 */
function insightweb_key_constant( string $name ): string {
	return defined( $name ) && '' !== (string) constant( $name ) ? (string) constant( $name ) : '';
}

/**
 * A value saved in an option holding name => value, else ''.
 *
 * @param string $option Option name.
 * @param string $name   Key name.
 */
function insightweb_key_saved( string $option, string $name ): string {
	$saved = get_option( $option, array() );
	return is_array( $saved ) ? (string) ( $saved[ $name ] ?? '' ) : '';
}

/**
 * Where a key is set and its value: ['config' | 'insightweb' | 'old' | '', value], first found wins.
 *
 * @param string $name Constant name, e.g. AW_CLARITY_TOKEN.
 * @return array{0: string, 1: string}
 */
function insightweb_key_found( string $name ): array {
	$sources = array(
		'config'     => fn() => insightweb_key_constant( $name ),
		'insightweb' => fn() => insightweb_key_saved( INSIGHTWEB_KEYS_OPTION, $name ),
		'old'        => fn() => insightweb_key_saved( INSIGHTWEB_KEYS_OLD_OPTION, $name ),
	);
	foreach ( $sources as $where => $read ) {
		$v = $read();
		if ( '' !== $v ) {
			return array( $where, $v );
		}
	}
	return array( '', '' );
}

/**
 * Where a key is set: 'config' (wp-config.php), 'insightweb' (Settings → Keys), 'old' (the old Kits → Keys) or ''.
 *
 * @param string $name Constant name, e.g. AW_CLARITY_TOKEN.
 */
function insightweb_key_where( string $name ): string {
	return insightweb_key_found( $name )[0];
}

/**
 * A key's value: the wp-config.php constant, else InsightWeb's own, else the old Kits → Keys one, else ''. The old
 * place is read directly, also when Animal Writes Core is off, so a key saved there keeps working until it is copied.
 *
 * @param string $name Constant name, e.g. AW_CLARITY_TOKEN.
 */
function insightweb_key( string $name ): string {
	return insightweb_key_found( $name )[1];
}

/**
 * Where a key is set, as the Keys section says it ('' for the shell's own "Saved." / "None saved.").
 *
 * @param string $where insightweb_key_where().
 */
function insightweb_key_where_label( string $where ): string {
	return array(
		'config' => __( 'Set in wp-config.php, which wins: change it there.', 'insightweb' ),
		'old'    => __( 'Saved under Kits → Keys (the old place) and still used until it is copied here. Type one here to replace it.', 'insightweb' ),
	)[ $where ] ?? '';
}

/**
 * Sanitize callback of insightweb_keys. The form sends name => value (empty: keep what is saved) and __clear (names to
 * remove); a whole array from code (the copy command) is saved as given. Unknown names are dropped, values lose their
 * white space and are cut at 512 characters, as on Kits → Keys.
 *
 * @param mixed $value Value to store.
 */
function insightweb_keys_sanitize( $value ): array {
	$value = is_array( $value ) ? $value : array();
	$saved = get_option( INSIGHTWEB_KEYS_OPTION, array() );
	$saved = is_array( $saved ) ? $saved : array();
	$clear = array_map( 'strval', (array) ( $value['__clear'] ?? array() ) );
	$out   = array();
	foreach ( array_keys( insightweb_key_fields() ) as $name ) {
		if ( in_array( $name, $clear, true ) ) {
			continue;
		}
		$in = $value[ $name ] ?? '';
		$v  = is_scalar( $in ) ? mb_substr( (string) preg_replace( '/\s+/', '', sanitize_text_field( (string) $in ) ), 0, 512 ) : '';
		if ( '' !== $v ) {
			$out[ $name ] = $v;
		} elseif ( isset( $saved[ $name ] ) && '' !== (string) $saved[ $name ] ) {
			$out[ $name ] = (string) $saved[ $name ]; // Left empty: the saved key stays.
		}
	}
	return $out;
}

add_action(
	'init',
	function () {
		// Registered for its sanitize callback (every update_option() goes through it). Not in REST (/wp/v2/settings), not
		// exported. The Keys section saves through the write-only route below.
		register_setting(
			'insightweb_keys',
			INSIGHTWEB_KEYS_OPTION,
			array(
				'type'              => 'object',
				'description'       => 'InsightWeb service keys (never shown).',
				'default'           => array(),
				'sanitize_callback' => 'insightweb_keys_sanitize',
				'show_in_rest'      => false,
			)
		);
	}
);

// Kept out of autoload, like aw_keys: read only by the scheduled jobs and the Settings screen.
add_action(
	'add_option_' . INSIGHTWEB_KEYS_OPTION,
	function () {
		if ( function_exists( 'wp_set_option_autoload' ) ) {
			wp_set_option_autoload( INSIGHTWEB_KEYS_OPTION, false );
		}
	}
);

// Kits → Keys keeps listing InsightWeb's keys during the move (its values are the fallback above).
add_filter(
	'aw_keys',
	function ( $keys ) {
		$by = __( 'Search Insights', 'insightweb' );
		foreach ( insightweb_key_fields() as $name => $f ) {
			$keys[ $name ] = array( $f[0], $f[1], $by );
		}
		return $keys;
	}
);

// The old reader aw_key() (Animal Writes Core) answers with InsightWeb's value for InsightWeb's keys.
add_filter(
	'kits_service_key',
	function ( $value, $name ) {
		return in_array( (string) $name, INSIGHTWEB_KEY_NAMES, true ) ? insightweb_key( (string) $name ) : $value;
	},
	10,
	2
);

/**
 * The Keys section's data: per key its label, help and where it is set. Never a value.
 *
 * @return array{keys: array<int, array{name: string, label: string, help: string, where: string, state: string}>}
 */
function insightweb_keys_payload(): array {
	$out = array();
	foreach ( insightweb_key_fields() as $name => $f ) {
		$where = insightweb_key_where( $name );
		$out[] = array(
			'name'  => $name,
			'label' => $f[0],
			'help'  => $f[1],
			'where' => $where,
			'state' => insightweb_key_where_label( $where ),
		);
	}
	return array( 'keys' => $out );
}

/**
 * [A] admin. POST insightweb/v1/keys: save new keys and remove ticked ones. Write-only: the answer is the state again
 * (set / where), never a value. An empty or missing value keeps the saved key; a key set in wp-config.php is left alone.
 *
 * @param WP_REST_Request $request Request: keys (name => new value), clear (names).
 * @return WP_REST_Response
 */
function insightweb_rest_save_keys( WP_REST_Request $request ): WP_REST_Response {
	$in    = array();
	$known = array_keys( insightweb_key_fields() );
	foreach ( (array) $request->get_param( 'keys' ) as $name => $value ) {
		if ( in_array( (string) $name, $known, true ) && is_string( $value ) && 'config' !== insightweb_key_where( (string) $name ) ) {
			$in[ (string) $name ] = $value;
		}
	}
	$in['__clear'] = array_values( array_intersect( array_map( 'strval', (array) $request->get_param( 'clear' ) ), $known ) );
	$changed       = update_option( INSIGHTWEB_KEYS_OPTION, $in, false );
	return rest_ensure_response(
		insightweb_keys_payload() + array(
			'message' => $changed ? __( 'Keys saved.', 'insightweb' ) : __( 'Nothing changed.', 'insightweb' ),
		)
	);
}

add_action(
	'rest_api_init',
	function () {
		if ( ! function_exists( 'kits_shell_rest_can' ) || ! defined( 'INSIGHTWEB_REST' ) ) {
			return;
		}
		// Administrators only, with the wp_rest nonce (kits_shell_rest_can), as Kits → Keys was.
		register_rest_route(
			INSIGHTWEB_REST,
			'/keys',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => kits_shell_rest_can( 'manage_options' ),
					'callback'            => fn() => rest_ensure_response( insightweb_keys_payload() ),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => kits_shell_rest_can( 'manage_options' ),
					'args'                => array(
						'keys'  => array(
							'type'                 => 'object',
							'additionalProperties' => array(
								'type'      => 'string',
								'maxLength' => 2048,
							),
							'default'              => array(),
						),
						'clear' => array(
							'type'    => 'array',
							'items'   => array( 'type' => 'string' ),
							'default' => array(),
						),
					),
					'callback'            => 'insightweb_rest_save_keys',
				),
			)
		);
	}
);
