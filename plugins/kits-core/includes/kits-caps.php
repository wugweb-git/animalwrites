<?php
/**
 * Kits' own capability, manage_kits. It opens the Kits menu, Home and Help. Theme Options and Settings keep
 * manage_options (they save through options.php and /wp/v2/settings); Tools keeps edit_theme_options.
 *
 * Nobody loses access:
 * - Granted to administrator and to every role that holds manage_options, on activation and once on the first admin
 *   request after an upgrade (option kits_caps, compared with KITS_CAP_VERSION). No role loses a capability.
 * - Until a role has it, user_has_cap gives it to anyone holding manage_options (live, where activation hooks do not
 *   run on deploy). A role that holds it set to false (denied on purpose) is left denied.
 *
 * Self-contained: no other plugin's function or capability helper is used.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'KITS_CAP' ) ) {
	define( 'KITS_CAP', 'manage_kits' );
}
const KITS_CAP_VERSION = 1;

/** The capabilities that opened Kits's screens before manage_kits. */
function kits_caps_from(): array {
	return array( 'manage_options' );
}

/** Gives KITS_CAP to administrator and to every role that holds one of the old capabilities. Never removes any. */
function kits_caps_grant(): void {
	if ( ! function_exists( 'wp_roles' ) ) {
		return;
	}
	foreach ( wp_roles()->role_objects as $name => $role ) {
		if ( $role->has_cap( KITS_CAP ) ) {
			continue;
		}
		$held = array_filter( kits_caps_from(), array( $role, 'has_cap' ) );
		if ( 'administrator' === $name || $held ) {
			$role->add_cap( KITS_CAP );
		}
	}
	update_option( 'kits_caps', KITS_CAP_VERSION, true );
}

register_activation_hook( dirname( __DIR__ ) . '/kits-core.php', 'kits_caps_grant' );

// Upgrade check: once per KITS_CAP_VERSION, on an admin request.
add_action(
	'admin_init',
	function () {
		if ( (int) get_option( 'kits_caps', 0 ) < KITS_CAP_VERSION ) {
			kits_caps_grant();
		}
	}
);

/**
 * Fallback: a user who holds an old capability holds KITS_CAP too, until the role grant has run.
 *
 * @param array $allcaps The user's capabilities.
 */
function kits_caps_fallback( $allcaps ) {
	if ( is_array( $allcaps ) && ! isset( $allcaps[ KITS_CAP ] ) ) {
		foreach ( kits_caps_from() as $old ) {
			if ( ! empty( $allcaps[ $old ] ) ) {
				$allcaps[ KITS_CAP ] = true;
				break;
			}
		}
	}
	return $allcaps;
}
add_filter( 'user_has_cap', 'kits_caps_fallback' );
