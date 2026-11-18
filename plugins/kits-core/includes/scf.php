<?php
/**
 * Secure Custom Fields (SCF, or ACF) bridge, admin side: the mechanism each Kits plugin uses to ship its own field
 * groups as Local JSON. SCF is a supported editing layer, never a requirement: fields are registered with
 * register_post_meta(), so content and the kits/post-field binding work without SCF.
 *
 * Functions:
 *   kits_scf_active()                 SCF or ACF is active and loads local field groups.
 *   kits_scf_json_folder( $dir, $keys ) a plugin's acf-json/ folder: SCF reads it, and saves each of $keys back to it.
 *   kits_scf_groups()                 every field group key the Kits plugins ship (filter kits_scf_groups).
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * True when SCF (or ACF) is active and loads local field groups.
 *
 * @return bool
 */
function kits_scf_active() {
	return function_exists( 'acf_add_local_field_group' );
}

/**
 * Register a plugin's Local JSON folder: SCF loads the groups in it, and edits made in SCF's own screen to one of
 * $keys are saved back to the same folder (so they can be committed). Call it when the plugin loads.
 *
 * @param string   $dir  Absolute path of the acf-json folder.
 * @param string[] $keys The group keys whose files live there.
 */
function kits_scf_json_folder( $dir, array $keys ) {
	$dir = untrailingslashit( (string) $dir );
	add_filter(
		'acf/settings/load_json',
		function ( $paths ) use ( $dir ) {
			$paths   = (array) $paths;
			$paths[] = $dir;
			return $paths;
		}
	);
	foreach ( $keys as $key ) {
		add_filter( 'acf/settings/save_json/key=' . $key, fn() => $dir );
	}
}

/**
 * Every field group key the Kits plugins ship, in registration order.
 *
 * @return string[]
 */
function kits_scf_groups() {
	return array_values( array_unique( array_map( 'strval', (array) apply_filters( 'kits_scf_groups', array() ) ) ) );
}
