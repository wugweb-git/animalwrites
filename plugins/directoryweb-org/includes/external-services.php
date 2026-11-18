<?php
/**
 * External-services registry and the one outbound gate.
 *
 * Every service DirectoryWeb may contact is declared here with its hosts, purpose, what is sent, and its terms and
 * privacy links. All outbound HTTP goes through directoryweb_remote_get(), which refuses a host that is not registered
 * and switched on. Services that need the site owner's say (address lookup) are OFF until switched on in Settings.
 * The readme's "External services" section and the Settings disclosure are generated from this list. The field shape
 * (hosts, purpose, data_sent, terms_url, privacy_url, enabled) is the suite contract used by SourceWeb; this file is
 * replaced by the shared package when that lands.
 *
 * Map tiles and the Near me button run in the visitor's browser and are declared here too, so they are disclosed, but
 * the browser (not the server) makes those requests; they are gated by the `directoryweb_services` option.
 *
 * @package directoryweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The services DirectoryWeb knows. */
function directoryweb_service_defs(): array {
	$defs = array(
		'nominatim'    => array(
			'hosts'       => array( 'nominatim.openstreetmap.org' ),
			'purpose'     => __( 'Turns a listing address into map coordinates (geocoding).', 'directoryweb' ),
			'data_sent'   => __( 'The listing address, PIN or district text, from your server, one request a second. Nothing about visitors.', 'directoryweb' ),
			'terms_url'   => 'https://operations.osmfoundation.org/policies/nominatim/',
			'privacy_url' => 'https://osmfoundation.org/wiki/Privacy_Policy',
			'default'     => false,
			'side'        => 'server',
		),
		'postalpincode' => array(
			'hosts'       => array( 'api.postalpincode.in' ),
			'purpose'     => __( 'Looks up the district and state of an Indian PIN code (optional, India only).', 'directoryweb' ),
			'data_sent'   => __( 'The PIN code a visitor types in Near me or a listing form, from your server. No other data.', 'directoryweb' ),
			'terms_url'   => 'https://www.postalpincode.in/Api-Details',
			'privacy_url' => 'https://www.postalpincode.in/',
			'default'     => false,
			'side'        => 'server',
		),
		'openfreemap'  => array(
			'hosts'       => array( 'tiles.openfreemap.org' ),
			'purpose'     => __( 'Map tiles and style for the public map.', 'directoryweb' ),
			'data_sent'   => __( "The visitor's IP address and the map area viewed, from the visitor's browser, when a map is shown.", 'directoryweb' ),
			'terms_url'   => 'https://openfreemap.org/tos/',
			'privacy_url' => 'https://openfreemap.org/privacy/',
			'default'     => true,
			'side'        => 'browser',
		),
		'osm-tiles'    => array(
			'hosts'       => array( 'tile.openstreetmap.org' ),
			'purpose'     => __( 'Map tiles in the admin picker beside the latitude and longitude fields.', 'directoryweb' ),
			'data_sent'   => __( "The editor's IP address and the area viewed, from the editor's browser, in the listing editor only.", 'directoryweb' ),
			'terms_url'   => 'https://operations.osmfoundation.org/policies/tiles/',
			'privacy_url' => 'https://osmfoundation.org/wiki/Privacy_Policy',
			'default'     => true,
			'side'        => 'browser',
		),
	);
	return (array) apply_filters( 'directoryweb_services_defs', $defs );
}

/** Registered services with their on/off state (option directoryweb_services: id => bool). */
function directoryweb_services(): array {
	$saved = (array) get_option( 'directoryweb_services', array() );
	$out   = array();
	foreach ( directoryweb_service_defs() as $id => $d ) {
		$d['id']      = $id;
		$d['enabled'] = array_key_exists( $id, $saved ) ? (bool) $saved[ $id ] : (bool) $d['default'];
		$out[ $id ]   = $d;
	}
	return $out;
}

/** True when the service is registered and switched on. */
function directoryweb_service_on( string $id ): bool {
	$all = directoryweb_services();
	return ! empty( $all[ $id ]['enabled'] );
}

/** The registered service for a host or URL, or null. */
function directoryweb_service_for_host( string $url ): ?array {
	$host = strtolower( (string) ( wp_parse_url( false === strpos( $url, '//' ) ? '//' . $url : $url, PHP_URL_HOST ) ?: '' ) );
	foreach ( directoryweb_services() as $svc ) {
		if ( in_array( $host, $svc['hosts'], true ) ) {
			return $svc;
		}
	}
	return null;
}

/**
 * The only outbound GET: refuses a host that is not registered and switched on.
 *
 * @param string $url  Request URL.
 * @param array  $args wp_safe_remote_get() arguments.
 * @return array|WP_Error
 */
function directoryweb_remote_get( string $url, array $args = array() ) {
	$svc = directoryweb_service_for_host( $url );
	if ( null === $svc ) {
		/* translators: %s: host name. */
		return new WP_Error( 'directoryweb_unregistered_service', sprintf( __( 'DirectoryWeb blocked a request to %s: the host is not in the external-services registry.', 'directoryweb' ), (string) wp_parse_url( $url, PHP_URL_HOST ) ) );
	}
	if ( empty( $svc['enabled'] ) ) {
		/* translators: %s: host name. */
		return new WP_Error( 'directoryweb_service_off', sprintf( __( 'DirectoryWeb did not contact %s: that service is switched off in Settings.', 'directoryweb' ), (string) wp_parse_url( $url, PHP_URL_HOST ) ) );
	}
	return wp_safe_remote_get( $url, $args );
}

/** The map style URL (the site owner may point it at another provider; that host is then not covered by the registry). */
function directoryweb_map_style_url(): string {
	$url = (string) get_option( 'directoryweb_map_style', 'https://tiles.openfreemap.org/styles/liberty' );
	return (string) apply_filters( 'directoryweb_map_style_url', esc_url_raw( $url ) );
}

/** The readme "External services" section, generated from the registry. */
function directoryweb_services_readme(): string {
	$lines = array( '== External services ==', '', 'DirectoryWeb can contact these third-party services. Address lookup is off until you switch it on.', '' );
	foreach ( directoryweb_service_defs() as $id => $d ) {
		$lines[] = sprintf( '* %s (%s), %s: %s Sent: %s Terms: %s. Privacy: %s.', $id, implode( ', ', $d['hosts'] ), 'browser' === $d['side'] ? 'visitor browser' : 'your server', $d['purpose'], $d['data_sent'], $d['terms_url'], $d['privacy_url'] );
	}
	return implode( "\n", $lines ) . "\n";
}
