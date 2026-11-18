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
 * Where a key is set, in words for the screen.
 *
 * @param string $where insightweb_key_where().
 */
function insightweb_key_where_label( string $where ): string {
	return array(
		'config'     => __( 'set in wp-config.php (that one is used)', 'insightweb' ),
		'insightweb' => __( 'set here', 'insightweb' ),
		'old'        => __( 'set under Kits → Keys (old place, still used until it is copied here)', 'insightweb' ),
	)[ $where ] ?? __( 'not set', 'insightweb' );
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
		// Settings API, group insightweb_keys: options.php checks the nonce and manage_options. Not in REST, not exported.
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

// After options.php saves the keys it comes back here with settings-updated=true.
add_action(
	'aw_si_settings_notices',
	function () {
		if ( current_user_can( 'manage_options' ) && 'true' === sanitize_key( wp_unslash( $_GET['settings-updated'] ?? '' ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only; options.php checked the nonce.
			wp_admin_notice( esc_html__( 'Keys saved.', 'insightweb' ), array( 'type' => 'success' ) );
		}
	}
);

/** Settings → Keys: the form (administrators), or the state of each key (anyone else who sees Settings). */
function insightweb_keys_settings_row(): void {
	echo '<tr><th scope="row">' . esc_html__( 'Keys', 'insightweb' ) . '</th><td><ul>';
	foreach ( insightweb_key_fields() as $name => $f ) {
		$where = insightweb_key_where( $name );
		echo '<li><span class="dashicons dashicons-' . ( $where ? 'yes-alt' : 'minus' ) . '" aria-hidden="true"></span> ' . esc_html( $f[0] . ': ' . insightweb_key_where_label( $where ) ) . '</li>';
	}
	echo '</ul>';
	if ( current_user_can( 'manage_options' ) ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '">';
		settings_fields( 'insightweb_keys' );
		foreach ( insightweb_key_fields() as $name => $f ) {
			$where = insightweb_key_where( $name );
			if ( 'config' === $where ) {
				continue; // wp-config.php wins; nothing to type here.
			}
			$id = 'insightweb-key-' . strtolower( $name );
			echo '<p><label for="' . esc_attr( $id ) . '">' . esc_html( $f[0] ) . '</label><br>';
			echo '<input type="text" class="regular-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( INSIGHTWEB_KEYS_OPTION . '[' . $name . ']' ) . '" value="" autocomplete="off" spellcheck="false" placeholder="' . esc_attr( 'insightweb' === $where ? __( 'Saved: leave empty to keep it', 'insightweb' ) : __( 'Paste the key', 'insightweb' ) ) . '"></p>';
			if ( 'insightweb' === $where ) {
				/* translators: %s: key label. */
				echo '<p><label><input type="checkbox" name="' . esc_attr( INSIGHTWEB_KEYS_OPTION . '[__clear][]' ) . '" value="' . esc_attr( $name ) . '"> ' . esc_html( sprintf( __( 'Remove the %s', 'insightweb' ), $f[0] ) ) . '</label></p>';
			}
		}
		submit_button( __( 'Save keys', 'insightweb' ) );
		echo '</form>';
	}
	echo '<p class="description">' . esc_html__( 'Paste a key and save; it is never shown again, only whether it is set. A wp-config.php constant of the same name always wins. Keys still under Kits → Keys keep working until they are copied here.', 'insightweb' ) . '</p></td></tr>';
}
