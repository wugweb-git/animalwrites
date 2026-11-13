<?php
/**
 * RankWeb's own capability, manage_rankweb. It opens RankWeb's screens and routes (Overview, FAQ,
 * Dashboard); RankWeb → Settings keeps manage_options.
 *
 * Nobody loses access:
 * - Granted to administrator and to every role that holds manage_categories, on activation and once on the first admin
 *   request after an upgrade (option rankweb_caps, compared with RANKWEB_CAP_VERSION). No role loses a capability.
 * - Until a role has it, user_has_cap gives it to anyone holding manage_categories (live, where activation hooks do not
 *   run on deploy). A role that holds it set to false (denied on purpose) is left denied.
 *
 * Self-contained: no other plugin's function or capability helper is used.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'RANKWEB_CAP' ) ) {
	define( 'RANKWEB_CAP', 'manage_rankweb' );
}
const RANKWEB_CAP_VERSION = 1;

/** The capabilities that opened RankWeb's screens before manage_rankweb. */
function rankweb_caps_from(): array {
	return array( 'manage_categories' );
}

/** Gives RANKWEB_CAP to administrator and to every role that holds one of the old capabilities. Never removes any. */
function rankweb_caps_grant(): void {
	if ( ! function_exists( 'wp_roles' ) ) {
		return;
	}
	foreach ( wp_roles()->role_objects as $name => $role ) {
		if ( $role->has_cap( RANKWEB_CAP ) ) {
			continue;
		}
		$held = array_filter( rankweb_caps_from(), array( $role, 'has_cap' ) );
		if ( 'administrator' === $name || $held ) {
			$role->add_cap( RANKWEB_CAP );
		}
	}
	update_option( 'rankweb_caps', RANKWEB_CAP_VERSION, true );
}

register_activation_hook( dirname( __DIR__ ) . '/aw-seo-extras.php', 'rankweb_caps_grant' );

// Upgrade check: once per RANKWEB_CAP_VERSION, on an admin request.
add_action(
	'admin_init',
	function () {
		if ( (int) get_option( 'rankweb_caps', 0 ) < RANKWEB_CAP_VERSION ) {
			rankweb_caps_grant();
		}
	}
);

/**
 * Fallback: a user who holds an old capability holds RANKWEB_CAP too, until the role grant has run.
 *
 * @param array $allcaps The user's capabilities.
 */
function rankweb_caps_fallback( $allcaps ) {
	if ( is_array( $allcaps ) && ! isset( $allcaps[ RANKWEB_CAP ] ) ) {
		foreach ( rankweb_caps_from() as $old ) {
			if ( ! empty( $allcaps[ $old ] ) ) {
				$allcaps[ RANKWEB_CAP ] = true;
				break;
			}
		}
	}
	return $allcaps;
}
add_filter( 'user_has_cap', 'rankweb_caps_fallback' );
