<?php
/**
 * SourceWeb's own capability, manage_sourceweb. It opens SourceWeb's screens and routes (Overview,
 * To review, Enrichment review; AW_REVIEW_CAP and AW_ENRICH_CAP point at it). The settings, reports and jobs screens
 * keep AW_SRC_CAP (manage_options), administrators only, as before.
 *
 * Nobody loses access:
 * - Granted to administrator and to every role that holds edit_others_posts (the old AW_REVIEW_CAP and AW_ENRICH_CAP),
 *   on activation and once on the first admin request after an upgrade (option sourceweb_caps, compared with
 *   SOURCEWEB_CAP_VERSION). No role loses a capability.
 * - Until a role has it, user_has_cap gives it to anyone holding edit_others_posts (the old AW_REVIEW_CAP and
 *   AW_ENRICH_CAP) (live, where activation hooks do not run on deploy). A role that holds it set to false (denied on
 *   purpose) is left denied.
 *
 * Self-contained: no other plugin's function or capability helper is used.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'SOURCEWEB_CAP' ) ) {
	define( 'SOURCEWEB_CAP', 'manage_sourceweb' );
}
const SOURCEWEB_CAP_VERSION = 1;

/** The capabilities that opened SourceWeb's screens before manage_sourceweb. */
function sourceweb_caps_from(): array {
	return array( 'edit_others_posts' );
}

/** Gives SOURCEWEB_CAP to administrator and to every role that holds one of the old capabilities. Never removes any. */
function sourceweb_caps_grant(): void {
	if ( ! function_exists( 'wp_roles' ) ) {
		return;
	}
	foreach ( wp_roles()->role_objects as $name => $role ) {
		if ( $role->has_cap( SOURCEWEB_CAP ) ) {
			continue;
		}
		$held = array_filter( sourceweb_caps_from(), array( $role, 'has_cap' ) );
		if ( 'administrator' === $name || $held ) {
			$role->add_cap( SOURCEWEB_CAP );
		}
	}
	update_option( 'sourceweb_caps', SOURCEWEB_CAP_VERSION, true );
}

register_activation_hook( dirname( __DIR__ ) . '/aw-sourcing.php', 'sourceweb_caps_grant' );

// Upgrade check: once per SOURCEWEB_CAP_VERSION, on an admin request.
add_action(
	'admin_init',
	function () {
		if ( (int) get_option( 'sourceweb_caps', 0 ) < SOURCEWEB_CAP_VERSION ) {
			sourceweb_caps_grant();
		}
	}
);

/**
 * Fallback: a user who holds an old capability holds SOURCEWEB_CAP too, until the role grant has run.
 *
 * @param array $allcaps The user's capabilities.
 */
function sourceweb_caps_fallback( $allcaps ) {
	if ( is_array( $allcaps ) && ! isset( $allcaps[ SOURCEWEB_CAP ] ) ) {
		foreach ( sourceweb_caps_from() as $old ) {
			if ( ! empty( $allcaps[ $old ] ) ) {
				$allcaps[ SOURCEWEB_CAP ] = true;
				break;
			}
		}
	}
	return $allcaps;
}
add_filter( 'user_has_cap', 'sourceweb_caps_fallback' );
