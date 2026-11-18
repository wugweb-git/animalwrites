<?php
/**
 * External-services registry and outbound gate.
 *
 * Every third-party service SourceWeb may contact is declared here — one or more hosts, purpose, what data is
 * sent, and Terms + Privacy URLs. Modules register their services in code on the `sourceweb_register_services`
 * action; the administrator can register extra endpoints (e.g. a custom AI provider or feed host) on the admin
 * screen. All outbound HTTP is routed through sourceweb_remote_request(), which refuses any host that is not
 * registered and enabled. The readme's "== External services ==" section and the admin disclosure screen are
 * both generated from this registry, so the external-services disclosure can never drift from what the code can
 * actually contact. A service is declared as `hosts` (an array), `purpose`, `data_sent`, `terms_url`,
 * `privacy_url`, `enabled`.
 *
 * @package sourceweb
 */

if ( ! defined( 'ABSPATH' ) && 'cli' !== PHP_SAPI ) {
	exit;
}

/**
 * Store (and read) the code-registered services. Pass an array to add one; call with no argument to read all.
 *
 * @param array|null $add A normalised service to add, or null to read.
 * @return array<string,array> Services keyed by id.
 */
function sourceweb_service_store( $add = null ) {
	static $items = array();
	if ( is_array( $add ) && isset( $add['id'] ) ) {
		$items[ $add['id'] ] = $add;
	}
	return $items;
}

/**
 * Normalise and sanitise a service declaration into the shared contract shape.
 *
 * @param string $id     Service id (kebab/snake).
 * @param array  $args   hosts[] (or a single host), purpose, data_sent, terms_url, privacy_url, enabled.
 *                       Legacy keys host/data/terms/privacy are accepted as aliases.
 * @param string $origin 'code' or 'admin'.
 * @return array|null The normalised service, or null when id/hosts is missing.
 */
function sourceweb_normalize_service( $id, array $args, $origin ) {
	$id = sanitize_key( $id );

	$raw = array();
	if ( ! empty( $args['hosts'] ) ) {
		$raw = is_array( $args['hosts'] ) ? $args['hosts'] : preg_split( '/[\s,]+/', (string) $args['hosts'] );
	} elseif ( ! empty( $args['host'] ) ) {
		$raw = array( $args['host'] );
	}
	$hosts = array();
	foreach ( (array) $raw as $host ) {
		$host = sourceweb_service_host( $host );
		if ( '' !== $host ) {
			$hosts[] = $host;
		}
	}
	$hosts = array_values( array_unique( $hosts ) );

	if ( empty( $hosts ) ) {
		return null;
	}
	if ( '' === $id ) {
		$id = sanitize_key( str_replace( '.', '-', $hosts[0] ) ); // Derive a sensible id from the first host.
	}
	if ( '' === $id ) {
		return null;
	}

	$pick = static function ( $primary, $alias ) use ( $args ) {
		return (string) ( $args[ $primary ] ?? ( $args[ $alias ] ?? '' ) );
	};

	return array(
		'id'          => $id,
		'hosts'       => $hosts,
		'purpose'     => sanitize_text_field( (string) ( $args['purpose'] ?? '' ) ),
		'data_sent'   => sanitize_text_field( $pick( 'data_sent', 'data' ) ),
		'terms_url'   => esc_url_raw( $pick( 'terms_url', 'terms' ) ),
		'privacy_url' => esc_url_raw( $pick( 'privacy_url', 'privacy' ) ),
		'enabled'     => ! empty( $args['enabled'] ),
		'origin'      => 'admin' === $origin ? 'admin' : 'code',
	);
}

/**
 * The bare, comparable host for a URL or a host string (lower case, no leading "www.").
 *
 * @param string $value A URL or a host.
 * @return string
 */
function sourceweb_service_host( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return '';
	}
	$host = ( false !== strpos( $value, '/' ) || false !== strpos( $value, ':' ) )
		? sourceweb_norm_host( $value )
		: strtolower( $value );
	return (string) preg_replace( '/^www\./', '', $host );
}

/**
 * Register an external service from code. Modules call this on the `sourceweb_register_services` action so a
 * service is declared only when its feature is loaded.
 *
 * @param string $id   Service id.
 * @param array  $args hosts[] (or host), purpose, data_sent, terms_url, privacy_url, enabled (default true).
 * @return void
 */
function sourceweb_register_external_service( $id, array $args ) {
	if ( ! isset( $args['enabled'] ) ) {
		$args['enabled'] = true;
	}
	$svc = sourceweb_normalize_service( $id, $args, 'code' );
	if ( null !== $svc ) {
		sourceweb_service_store( $svc );
	}
}

/**
 * Every registered service: code-registered (via the action) merged with the administrator's own entries
 * (option `sourceweb_services`). Admin entries override a code entry of the same id.
 *
 * @return array<string,array>
 */
function sourceweb_external_services() {
	static $fired = false;
	if ( ! $fired && function_exists( 'do_action' ) ) {
		do_action( 'sourceweb_register_services' );
		$fired = true;
	}
	$services = sourceweb_service_store();
	$admin    = function_exists( 'get_option' ) ? get_option( 'sourceweb_services', array() ) : array();
	foreach ( (array) $admin as $id => $args ) {
		$svc = sourceweb_normalize_service( is_string( $id ) ? $id : ( $args['id'] ?? '' ), (array) $args, 'admin' );
		if ( null !== $svc ) {
			$services[ $svc['id'] ] = $svc;
		}
	}
	return $services;
}

/**
 * The service registered for a host, or null.
 *
 * @param string     $host     A host or URL.
 * @param array|null $services Optional service list (for testing); defaults to the live registry.
 * @return array|null
 */
function sourceweb_external_service_for_host( $host, $services = null ) {
	$host     = sourceweb_service_host( $host );
	$services = null === $services ? sourceweb_external_services() : $services;
	foreach ( $services as $svc ) {
		if ( ! empty( $svc['hosts'] ) && in_array( $host, (array) $svc['hosts'], true ) ) {
			return $svc;
		}
	}
	return null;
}

/**
 * The one outbound HTTP entry point. Refuses any host not registered and enabled in the services registry.
 *
 * @param string $url    Request URL.
 * @param array  $args   Arguments for wp_safe_remote_*().
 * @param string $method 'GET' or 'POST'.
 * @return array|WP_Error The HTTP response, or WP_Error when the host is not allowed.
 */
function sourceweb_remote_request( $url, $args = array(), $method = 'GET' ) {
	$svc = sourceweb_external_service_for_host( $url );
	if ( null === $svc ) {
		return new WP_Error(
			'sourceweb_unregistered_service',
			sprintf(
				/* translators: %s: host name. */
				__( 'SourceWeb blocked an outbound request to %s: the host is not in the external-services registry.', 'sourceweb' ),
				sourceweb_service_host( $url )
			)
		);
	}
	if ( empty( $svc['enabled'] ) ) {
		return new WP_Error(
			'sourceweb_service_disabled',
			sprintf(
				/* translators: %s: host name. */
				__( 'SourceWeb blocked an outbound request to %s: the external service is registered but not enabled.', 'sourceweb' ),
				sourceweb_service_host( $url )
			)
		);
	}
	return 'POST' === strtoupper( (string) $method )
		? wp_safe_remote_post( $url, $args )
		: wp_safe_remote_get( $url, $args );
}

/**
 * Build the readme "== External services ==" section from the registry. Pure: pass a service list to test it.
 *
 * @param array|null $services Optional service list; defaults to the live registry.
 * @return string
 */
function sourceweb_external_services_readme( $services = null ) {
	$services = null === $services ? sourceweb_external_services() : $services;
	if ( empty( $services ) ) {
		return "== External services ==\n\nThis release does not contact any external service. No data leaves your site.\n";
	}
	$lines = array(
		'== External services ==',
		'',
		'SourceWeb contacts the following third-party services, each only when its feature is enabled:',
		'',
	);
	foreach ( $services as $svc ) {
		$lines[] = sprintf(
			'* %s (%s): %s. Data sent: %s. Terms: %s — Privacy: %s',
			$svc['id'],
			implode( ', ', (array) $svc['hosts'] ),
			'' !== $svc['purpose'] ? $svc['purpose'] : 'see the plugin documentation',
			'' !== $svc['data_sent'] ? $svc['data_sent'] : 'a request to the host',
			'' !== $svc['terms_url'] ? $svc['terms_url'] : 'n/a',
			'' !== $svc['privacy_url'] ? $svc['privacy_url'] : 'n/a'
		);
	}
	return implode( "\n", $lines ) . "\n";
}
