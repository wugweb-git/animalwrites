<?php
/**
 * MailWeb's own capability, manage_mailweb. It opens MailWeb's screens and routes (Inbox,
 * Overview); MAILWEB_CAP is it. MailWeb → Settings keeps manage_options.
 *
 * Nobody loses access:
 * - Granted to administrator and to every role that holds edit_others_posts (the old MAILWEB_CAP), on activation and
 *   once on the first admin request after an upgrade (option mailweb_caps, compared with MAILWEB_CAP_VERSION). No role
 *   loses a capability.
 * - Until a role has it, user_has_cap gives it to anyone holding edit_others_posts (the old MAILWEB_CAP) (live, where
 *   activation hooks do not run on deploy). A role that holds it set to false (denied on purpose) is left denied.
 *
 * Self-contained: no other plugin's function or capability helper is used.
 *
 * @package mailweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'MAILWEB_CAP' ) ) {
	define( 'MAILWEB_CAP', 'manage_mailweb' );
}
const MAILWEB_CAP_VERSION = 1;

/** The capabilities that opened MailWeb's screens before manage_mailweb. */
function mailweb_caps_from(): array {
	return array( 'edit_others_posts' );
}

/** Gives MAILWEB_CAP to administrator and to every role that holds one of the old capabilities. Never removes any. */
function mailweb_caps_grant(): void {
	if ( ! function_exists( 'wp_roles' ) ) {
		return;
	}
	foreach ( wp_roles()->role_objects as $name => $role ) {
		if ( $role->has_cap( MAILWEB_CAP ) ) {
			continue;
		}
		$held = array_filter( mailweb_caps_from(), array( $role, 'has_cap' ) );
		if ( 'administrator' === $name || $held ) {
			$role->add_cap( MAILWEB_CAP );
		}
	}
	update_option( 'mailweb_caps', MAILWEB_CAP_VERSION, true );
}

register_activation_hook( dirname( __DIR__ ) . '/mailweb.php', 'mailweb_caps_grant' );

// Upgrade check: once per MAILWEB_CAP_VERSION, on an admin request.
add_action(
	'admin_init',
	function () {
		if ( (int) get_option( 'mailweb_caps', 0 ) < MAILWEB_CAP_VERSION ) {
			mailweb_caps_grant();
		}
	}
);

/**
 * Fallback: a user who holds an old capability holds MAILWEB_CAP too, until the role grant has run.
 *
 * @param array $allcaps The user's capabilities.
 */
function mailweb_caps_fallback( $allcaps ) {
	if ( is_array( $allcaps ) && ! isset( $allcaps[ MAILWEB_CAP ] ) ) {
		foreach ( mailweb_caps_from() as $old ) {
			if ( ! empty( $allcaps[ $old ] ) ) {
				$allcaps[ MAILWEB_CAP ] = true;
				break;
			}
		}
	}
	return $allcaps;
}
add_filter( 'user_has_cap', 'mailweb_caps_fallback' );
