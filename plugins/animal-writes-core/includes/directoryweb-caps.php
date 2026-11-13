<?php
/**
 * DirectoryWeb's own capability, manage_directoryweb. It opens DirectoryWeb's screens and routes
 * (the menu, Dashboard and its counts) and Claims: Directory → Claims with its review, the new Claims screen (read only:
 * no approve, reject or apply there) and its routes (owner, 13 Nov 2026). Settings keeps manage_options. Volunteers,
 * reports, forms and MailWeb-AW keep edit_aw_volunteers.
 *
 * Nobody loses access:
 * - Granted to administrator and to every role that holds edit_aw_volunteers, on activation and once on the first admin
 *   request after an upgrade (option directoryweb_caps, compared with DIRECTORYWEB_CAP_VERSION). No role loses a
 *   capability.
 * - Until a role has it, user_has_cap gives it to anyone holding edit_aw_volunteers (live, where activation hooks do
 *   not run on deploy). A role that holds it set to false (denied on purpose) is left denied.
 *
 * Self-contained: no other plugin's function or capability helper is used.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'DIRECTORYWEB_CAP' ) ) {
	define( 'DIRECTORYWEB_CAP', 'manage_directoryweb' );
}
const DIRECTORYWEB_CAP_VERSION = 1;

/** The capabilities that opened DirectoryWeb's screens before manage_directoryweb. */
function directoryweb_caps_from(): array {
	return array( 'edit_aw_volunteers' );
}

/** Gives DIRECTORYWEB_CAP to administrator and to every role that holds one of the old capabilities. Never removes any. */
function directoryweb_caps_grant(): void {
	if ( ! function_exists( 'wp_roles' ) ) {
		return;
	}
	foreach ( wp_roles()->role_objects as $name => $role ) {
		if ( $role->has_cap( DIRECTORYWEB_CAP ) ) {
			continue;
		}
		$held = array_filter( directoryweb_caps_from(), array( $role, 'has_cap' ) );
		if ( 'administrator' === $name || $held ) {
			$role->add_cap( DIRECTORYWEB_CAP );
		}
	}
	update_option( 'directoryweb_caps', DIRECTORYWEB_CAP_VERSION, true );
}

register_activation_hook( dirname( __DIR__ ) . '/animal-writes-core.php', 'directoryweb_caps_grant' );

// Upgrade check: once per DIRECTORYWEB_CAP_VERSION, on an admin request.
add_action(
	'admin_init',
	function () {
		if ( (int) get_option( 'directoryweb_caps', 0 ) < DIRECTORYWEB_CAP_VERSION ) {
			directoryweb_caps_grant();
		}
	}
);

/**
 * Fallback: a user who holds an old capability holds DIRECTORYWEB_CAP too, until the role grant has run.
 *
 * @param array $allcaps The user's capabilities.
 */
function directoryweb_caps_fallback( $allcaps ) {
	if ( is_array( $allcaps ) && ! isset( $allcaps[ DIRECTORYWEB_CAP ] ) ) {
		foreach ( directoryweb_caps_from() as $old ) {
			if ( ! empty( $allcaps[ $old ] ) ) {
				$allcaps[ DIRECTORYWEB_CAP ] = true;
				break;
			}
		}
	}
	return $allcaps;
}
add_filter( 'user_has_cap', 'directoryweb_caps_fallback' );
