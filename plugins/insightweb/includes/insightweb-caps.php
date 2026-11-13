<?php
/**
 * InsightWeb's own capability, manage_insightweb. It opens InsightWeb's screens and routes;
 * aw_si_cap() returns it. Settings saves keep manage_options.
 *
 * Nobody loses access:
 * - Granted to administrator and to every role that holds manage_options (or AW_SRC_CAP, when a site defines it), on
 *   activation and once on the first admin request after an upgrade (option insightweb_caps, compared with
 *   INSIGHTWEB_CAP_VERSION). No role loses a capability.
 * - Until a role has it, user_has_cap gives it to anyone holding manage_options (or AW_SRC_CAP, when a site defines it)
 *   (live, where activation hooks do not run on deploy). A role that holds it set to false (denied on purpose) is left
 *   denied.
 *
 * Self-contained: no other plugin's function or capability helper is used.
 *
 * @package insightweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'INSIGHTWEB_CAP' ) ) {
	define( 'INSIGHTWEB_CAP', 'manage_insightweb' );
}
const INSIGHTWEB_CAP_VERSION = 1;

/** The capabilities that opened InsightWeb's screens before manage_insightweb. */
function insightweb_caps_from(): array {
	return array_values( array_unique( array( 'manage_options', defined( 'AW_SRC_CAP' ) ? (string) AW_SRC_CAP : 'manage_options' ) ) );
}

/** Gives INSIGHTWEB_CAP to administrator and to every role that holds one of the old capabilities. Never removes any. */
function insightweb_caps_grant(): void {
	if ( ! function_exists( 'wp_roles' ) ) {
		return;
	}
	foreach ( wp_roles()->role_objects as $name => $role ) {
		if ( $role->has_cap( INSIGHTWEB_CAP ) ) {
			continue;
		}
		$held = array_filter( insightweb_caps_from(), array( $role, 'has_cap' ) );
		if ( 'administrator' === $name || $held ) {
			$role->add_cap( INSIGHTWEB_CAP );
		}
	}
	update_option( 'insightweb_caps', INSIGHTWEB_CAP_VERSION, true );
}

register_activation_hook( dirname( __DIR__ ) . '/insightweb.php', 'insightweb_caps_grant' );

// Upgrade check: once per INSIGHTWEB_CAP_VERSION, on an admin request.
add_action(
	'admin_init',
	function () {
		if ( (int) get_option( 'insightweb_caps', 0 ) < INSIGHTWEB_CAP_VERSION ) {
			insightweb_caps_grant();
		}
	}
);

/**
 * Fallback: a user who holds an old capability holds INSIGHTWEB_CAP too, until the role grant has run.
 *
 * @param array $allcaps The user's capabilities.
 */
function insightweb_caps_fallback( $allcaps ) {
	if ( is_array( $allcaps ) && ! isset( $allcaps[ INSIGHTWEB_CAP ] ) ) {
		foreach ( insightweb_caps_from() as $old ) {
			if ( ! empty( $allcaps[ $old ] ) ) {
				$allcaps[ INSIGHTWEB_CAP ] = true;
				break;
			}
		}
	}
	return $allcaps;
}
add_filter( 'user_has_cap', 'insightweb_caps_fallback' );
