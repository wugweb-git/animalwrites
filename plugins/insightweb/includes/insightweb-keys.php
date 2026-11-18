<?php
/**
 * InsightWeb's own service keys (owner, 18 Nov 2026: every Wugweb plugin keeps its own keys, like Yoast, Rank Math and
 * Site Kit; the shared Kits → Keys vault is retired, migrate first and delete second). Plan: docs/keys-per-plugin.md.
 *
 * A key's value, first found wins:
 *   1. a wp-config.php constant of the same name (not empty);
 *   2. InsightWeb's own option insightweb_keys (name => value, not autoloaded), set on Settings → Keys;
 *   3. the old shared option aw_keys (the Kits → Keys vault, whose screen and code left Animal Writes Core on 19 Nov
 *      2026), read directly, so the live keys keep working until the copy has run (insightweb_keys_copy(), by itself
 *      or `wp insightweb keys copy`) and the owner deletes the option.
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
		$in = 'config' === insightweb_key_where( $name ) ? '' : ( $value[ $name ] ?? '' ); // wp-config.php wins: nothing typed is kept for it.
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

/**
 * The copy step of the move (docs/keys-per-plugin.md, stage 3): each InsightWeb key that is saved in the old shared
 * vault (option aw_keys) and not yet in InsightWeb's own option is copied over. Guarded: it copies only keys InsightWeb
 * owns, only into an empty place, and never when a wp-config.php constant sets the key. Idempotent: running it again
 * copies nothing. Reversible: it never deletes or changes aw_keys, so removing a key from Settings → Keys (or deleting
 * insightweb_keys) puts things back as they were. It never prints, logs or returns a value: only the names.
 *
 * @param bool $write False for a rehearsal (nothing is written).
 * @return array<string,string> Key name => 'copied' | 'already set' | 'set in wp-config.php' | 'not in the old place'.
 */
function insightweb_keys_copy( bool $write = true ): array {
	$own    = get_option( INSIGHTWEB_KEYS_OPTION, array() );
	$own    = is_array( $own ) ? $own : array();
	$result = array();
	$new    = array();
	foreach ( array_keys( insightweb_key_fields() ) as $name ) {
		if ( '' !== insightweb_key_constant( $name ) ) {
			$result[ $name ] = 'set in wp-config.php';
		} elseif ( isset( $own[ $name ] ) && '' !== (string) $own[ $name ] ) {
			$result[ $name ] = 'already set';
		} elseif ( '' === insightweb_key_saved( INSIGHTWEB_KEYS_OLD_OPTION, $name ) ) {
			$result[ $name ] = 'not in the old place';
		} else {
			$new[ $name ]    = insightweb_key_saved( INSIGHTWEB_KEYS_OLD_OPTION, $name );
			$result[ $name ] = 'copied';
		}
	}
	if ( $write && $new ) {
		update_option( INSIGHTWEB_KEYS_OPTION, array_merge( $own, $new ), false ); // through insightweb_keys_sanitize(); not autoloaded
	}
	return $result;
}

// The copy runs by itself, once, on the first administrator request after this version is deployed (nothing is copied
// when the old vault has none of InsightWeb's keys). The flag only saves reading the old option on every admin request;
// delete the option insightweb_keys_copied to run it again.
add_action(
	'admin_init',
	function () {
		if ( '1' === (string) get_option( 'insightweb_keys_copied', '' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		insightweb_keys_copy( true );
		if ( ! in_array( 'copied', insightweb_keys_copy( false ), true ) ) { // nothing left to copy: it worked (or there was nothing). Otherwise try again next time.
			update_option( 'insightweb_keys_copied', '1', false );
		}
	}
);

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'insightweb keys',
		/**
		 * Where InsightWeb's service keys are set, and the copy from the old Kits → Keys vault. Never prints a value.
		 *
		 * ## OPTIONS
		 *
		 * <action>
		 * : status (where each key is set), or copy (copy keys from the old vault into InsightWeb's own option).
		 *
		 * [--dry-run]
		 * : With copy: say what would be copied and write nothing.
		 */
		function ( $args, $assoc ) {
			$action = (string) ( $args[0] ?? 'status' );
			if ( 'copy' === $action ) {
				foreach ( insightweb_keys_copy( empty( $assoc['dry-run'] ) ) as $name => $what ) {
					WP_CLI::log( $name . ': ' . $what );
				}
				return;
			}
			foreach ( array_keys( insightweb_key_fields() ) as $name ) {
				$where = insightweb_key_where( $name );
				WP_CLI::log( $name . ': set ' . ( '' === $where ? 'no' : 'yes (' . $where . ')' ) );
			}
		}
	);
}

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
	$clear   = array_values( array_intersect( array_map( 'strval', (array) $request->get_param( 'clear' ) ), $known ) );
	$changed = false;
	if ( $clear || array_filter( $in, fn( $v ) => '' !== trim( $v ) ) ) { // Nothing typed or ticked: nothing to write.
		$in['__clear'] = $clear;
		$changed       = update_option( INSIGHTWEB_KEYS_OPTION, $in, false );
	}
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
