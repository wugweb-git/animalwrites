<?php
/**
 * Shared external-services registry for the Wugweb plugin suite.
 *
 * Every plugin that talks to an outside service bundles an identical copy of this file and registers each service it
 * uses through the static API (Wugweb_External_Services::register()). The class and the version constant are guarded,
 * so when several plugins that bundle it are active only the first copy defines them and the rest reuse it. All copies
 * are kept byte-for-byte identical at one version; a later version raises WUGWEB_EXTSVC_VERSION and every plugin
 * updates its copy together.
 *
 * It is suite-neutral: it holds no plugin-specific data of its own — a plugin calls register() for each service, and
 * readme_section() renders the registered services as a readme "== External services ==" block (and feeds any
 * in-admin disclosure). Nothing here makes a network request; it only records and formats what a plugin declares.
 *
 * @package InsightWeb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WUGWEB_EXTSVC_VERSION' ) ) {
	define( 'WUGWEB_EXTSVC_VERSION', '1.0.0' );
}

if ( ! class_exists( 'Wugweb_External_Services' ) ) {

	/**
	 * The suite's external-services registry: a plugin registers each outside service it uses, and the registry renders
	 * them for the readme and for an in-admin disclosure.
	 */
	class Wugweb_External_Services {

		/**
		 * Registered services, keyed by id (later registrations of the same id overwrite — so all copies agree).
		 *
		 * @var array<string, array<string, mixed>>
		 */
		private static $services = array();

		/**
		 * Register one external service.
		 *
		 * @param string               $id      Stable id, e.g. 'google-site-kit'.
		 * @param array<string, mixed> $service name (required), summary (what is read or sent and when), hosts (string[]),
		 *                                       terms (URL), privacy (URL), optional (bool: used only when a key/connection is set).
		 * @return void
		 */
		public static function register( string $id, array $service ): void {
			$id = sanitize_key( $id );
			if ( '' === $id || empty( $service['name'] ) ) {
				return;
			}
			self::$services[ $id ] = array(
				'name'     => (string) $service['name'],
				'summary'  => (string) ( $service['summary'] ?? '' ),
				'hosts'    => array_values( array_filter( array_map( 'strval', (array) ( $service['hosts'] ?? array() ) ) ) ),
				'terms'    => (string) ( $service['terms'] ?? '' ),
				'privacy'  => (string) ( $service['privacy'] ?? '' ),
				'optional' => ! empty( $service['optional'] ),
			);
		}

		/**
		 * Every registered service, ordered by name.
		 *
		 * @return array<string, array<string, mixed>>
		 */
		public static function all(): array {
			$s = self::$services;
			uasort(
				$s,
				static function ( $a, $b ) {
					return strcasecmp( (string) $a['name'], (string) $b['name'] );
				}
			);
			return $s;
		}

		/**
		 * The registered services as a readme.txt "== External services ==" block (plain text, one bullet per service).
		 *
		 * @return string
		 */
		public static function readme_section(): string {
			$lines = array( '== External services ==', '' );
			if ( ! self::$services ) {
				$lines[] = 'This plugin makes no external requests.';
				return implode( "\n", $lines ) . "\n";
			}
			$lines[] = 'This plugin uses the following external services. Each is listed with what is exchanged and when, and links to its terms and privacy policy.';
			$lines[] = '';
			foreach ( self::all() as $service ) {
				$bits = '* **' . $service['name'] . '**';
				if ( $service['hosts'] ) {
					$bits .= ' (' . implode( ', ', $service['hosts'] ) . ')';
				}
				if ( '' !== $service['summary'] ) {
					$bits .= ' — ' . $service['summary'];
				}
				if ( $service['optional'] ) {
					$bits .= ' Used only when its key or connection is set.';
				}
				$refs = array();
				if ( '' !== $service['terms'] ) {
					$refs[] = 'Terms: ' . $service['terms'];
				}
				if ( '' !== $service['privacy'] ) {
					$refs[] = 'Privacy: ' . $service['privacy'];
				}
				if ( $refs ) {
					$bits .= ' ' . implode( ' ', $refs );
				}
				$lines[] = $bits;
			}
			return implode( "\n", $lines ) . "\n";
		}
	}
}
