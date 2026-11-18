<?php
/**
 * SCF field groups (Local JSON) for the content-type presets (docs/content-blocks-feasibility.md §12; owner, 18 Nov
 * 2026: SCF is first-class). Each built-in preset has one group in acf-json/{preset id}/group_{preset id}.json, with the
 * same field names as the preset's register_post_meta() keys; the group is only the editing layer, so content and the
 * kits/post-field binding work the same without SCF (then the fields are in the core Custom Fields panel).
 *
 * A preset's folder is given to SCF only while that preset is switched on (Kits → Settings → Content types), so a site
 * with every preset off sees no Kits groups on SCF's screen. Edits made in SCF's own field-group screen are saved back
 * to the same file, to be committed (kits_scf_json_folder()); on a live site a deploy replaces the file, so change a
 * group in development and commit it. tests/presets-scf-tests.php checks that each group matches its preset.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The SCF group key of a preset: group_ and the preset id with underscores (kits-event → group_kits_event).
 *
 * @param string $id Preset id.
 */
function kits_preset_scf_group_key( string $id ): string {
	return 'group_' . str_replace( '-', '_', sanitize_key( $id ) );
}

/**
 * The Local JSON folder of a built-in preset, or '' when it ships none.
 *
 * @param string $id Preset id.
 */
function kits_preset_scf_folder( string $id ): string {
	$dir = KITS_CORE_DIR . 'acf-json/' . sanitize_key( $id );
	return is_readable( $dir . '/' . kits_preset_scf_group_key( $id ) . '.json' ) ? $dir : '';
}

// Before SCF reads its Local JSON (acf/include_fields, on init at 5), and not before init: the preset definitions are
// translated. One folder per switched-on built-in preset.
add_action(
	'init',
	function () {
		if ( ! function_exists( 'kits_scf_json_folder' ) || ! function_exists( 'kits_presets_enabled' ) ) {
			return;
		}
		$keys = array();
		foreach ( array_intersect( kits_presets_enabled(), array_keys( kits_presets_builtin_files() ) ) as $id ) {
			$dir = kits_preset_scf_folder( (string) $id );
			if ( '' !== $dir ) {
				$key = kits_preset_scf_group_key( (string) $id );
				kits_scf_json_folder( $dir, array( $key ) );
				$keys[] = $key;
			}
		}
		if ( $keys ) {
			add_filter( 'kits_scf_groups', fn( $list ) => array_merge( (array) $list, $keys ) );
		}
	},
	1
);
