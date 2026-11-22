<?php
/**
 * Secure Custom Fields (SCF): the editing screens for the meta that meta.php registers.
 *
 * - Field groups are Local JSON in acf-json/ (versioned, deployed with the plugin). Edits made in SCF's own screen are
 *   saved back to the same files, so they can be committed.
 * - Every field's name is an existing meta key: SCF shows and saves the value that is already there; there is no second
 *   copy. Saving keeps each key's stored format (aw_value_guard below): dates stay Y-m-d, photos stay "1,2,3", flags
 *   stay '1', empty fields are removed as before, and values pass meta.php's sanitising.
 * - Fields marked read only are shown and never saved.
 * - SCF stays on while Animal Writes core is on (owner, 1 Nov 2026): no Deactivate link for it in WP Admin and any
 *   deactivation from WP Admin is stopped, because the edit screens' boxes are SCF field groups. Deactivate Animal
 *   Writes core first and SCF can be deactivated as usual. listing-profile.php and pages.php still keep a box for
 *   the case SCF is missing.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** True when SCF (or ACF) is active. Use kits_scf_active(); this one goes once DirectoryWeb (listing-profile.php) has switched. */
function aw_scf_active() {
	return kits_scf_active();
}

/** SCF's plugin file (plugins screen and WP-CLI name it so). */
const AW_SCF_PLUGIN = 'secure-custom-fields/secure-custom-fields.php';

// No Deactivate link for SCF while this plugin is on.
add_filter(
	'plugin_action_links_' . AW_SCF_PLUGIN,
	function ( $links ) {
		unset( $links['deactivate'] );
		return $links;
	}
);

// Stop SCF being deactivated (bulk action, WP-CLI, any caller) unless Kits Core is deactivated first or in
// the same request: the plugins list is in name order, so kits-core comes before SCF in a bulk deactivation.
add_action(
	'deactivate_plugin',
	function ( $plugin ) {
		static $core_off = false;
		if ( 'kits-core/kits-core.php' === $plugin ) {
			$core_off = true;
			return;
		}
		if ( AW_SCF_PLUGIN === $plugin && ! $core_off ) {
			wp_die(
				esc_html__( 'Secure Custom Fields stays on while Kits Core is on: the edit screens use it. Deactivate Kits Core first.', 'kits-core' ),
				esc_html__( 'Secure Custom Fields stays on', 'kits-core' ),
				array(
					'response'  => 403,
					'back_link' => true,
				)
			);
		}
	}
);

/** Our field groups (keys of the files in acf-json/). The Directory's eight listing groups are DirectoryWeb's (its own acf-json/); it adds them through aw_scf_group_keys(). */
const AW_SCF_GROUPS = array( 'group_aw_opportunity', 'group_aw_news', 'group_aw_legal', 'group_aw_legal_document', 'group_aw_campaign', 'group_aw_campaign_dates', 'group_aw_campaign_document', 'group_aw_campaign_orgs', 'group_aw_campaign_outcome', 'group_aw_campaign_share', 'group_aw_resource', 'group_aw_social', 'group_aw_campaign_social' );

// Our acf-json/ folder, through Kits Core's bridge (kits-core/includes/scf.php): read, and our groups saved back to it.
kits_scf_json_folder( kits_site_path( 'acf-json' ), AW_SCF_GROUPS );

// Our groups first, then what plugins add through the older aw_scf_groups filter (DirectoryWeb), then kits_scf_groups.
add_filter( 'kits_scf_groups', fn( $groups ) => array_merge( AW_SCF_GROUPS, (array) apply_filters( 'aw_scf_groups', array() ), (array) $groups ), 5 );

/**
 * Every field group of ours, Core's and the plugins' (DirectoryWeb adds its listing groups through the aw_scf_groups
 * filter). Kits Core's kits_scf_groups(); this name stays for its callers.
 *
 * @return string[]
 */
function aw_scf_group_keys(): array {
	return kits_scf_groups();
}

/** Is this one of our fields (top level or inside one of our repeaters)? */
function aw_scf_ours( $field ) {
	return is_array( $field ) && 0 === strpos( (string) ( $field['key'] ?? '' ), 'field_aw_' );
}

/** A top-level field on an existing key (not a repeater, not a row inside one). */
function aw_scf_top( $field ) {
	return aw_scf_ours( $field ) && 0 !== strpos( (string) ( $field['parent'] ?? '' ), 'field_' ) && empty( $field['parent_repeater'] ) && ! in_array( $field['type'], array( 'repeater', 'tab', 'message' ), true );
}

/** SCF's date picker posts Ymd; the site stores Y-m-d. */
function aw_scf_ymd( $v ) {
	$v = (string) $v;
	return preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $v, $m ) ? $m[1] . '-' . $m[2] . '-' . $m[3] : $v;
}

/**
 * Save one of our top-level fields the way the former boxes did, in the key's own format.
 * Returns true (handled) so SCF writes nothing itself: no field-key reference rows either.
 */
function aw_value_guard( $check, $value, $post_id, $field ) {
	if ( ! aw_scf_top( $field ) || ! is_numeric( $post_id ) ) {
		return $check;
	}
	$id   = (int) $post_id;
	$name = $field['name'];
	if ( ! empty( $field['readonly'] ) ) {
		return true; // shown only
	}
	$cur    = get_post_meta( $id, $name, true );
	$exists = metadata_exists( 'post', $id, $name );

	switch ( $field['type'] ) {
		case 'true_false':
			if ( $value ) {
				if ( '1' !== (string) $cur ) {
					update_post_meta( $id, $name, '1' );
				}
				return true;
			}
			if ( 'aw_verified' === $name ) { // unverified listings store '' (imports)
				if ( $exists && '' !== (string) $cur ) {
					update_post_meta( $id, $name, '' );
				}
			} elseif ( $exists ) {
				delete_post_meta( $id, $name );
			}
			return true;
		case 'date_picker':
			$value = aw_scf_ymd( $value );
			break;
		case 'gallery':
			$value = implode( ',', array_slice( array_filter( array_map( 'absint', (array) $value ) ), 0, 4 ) );
			break;
		case 'file':
		case 'image':
		case 'post_object':
			$value = is_array( $value ) ? ( $value['ID'] ?? reset( $value ) ) : $value;
			$value = absint( $value ) ? (string) absint( $value ) : '';
			break;
		case 'relationship': // stored "1,2,3" like the photos (social posts' related items)
			$value = implode( ',', array_slice( array_values( array_unique( array_filter( array_map( 'absint', (array) $value ) ) ) ), 0, 20 ) );
			break;
	}
	$value = is_scalar( $value ) ? trim( (string) $value ) : '';
	if ( '' === $value ) {
		if ( $exists && '' !== (string) $cur ) {
			delete_post_meta( $id, $name ); // as the former boxes: empty means no value
		}
		return true;
	}
	if ( (string) $cur !== $value ) {
		update_post_meta( $id, $name, wp_slash( $value ) ); // registered keys pass meta.php's sanitising here
	}
	return true;
}
add_filter( 'acf/pre_update_value', 'aw_value_guard', 5, 4 );

// Photos are stored "1,2,3"; SCF's gallery reads a list.
add_filter(
	'acf/load_value/type=gallery',
	fn( $value, $post_id, $field ) => aw_scf_ours( $field ) && is_string( $value ) ? array_values( array_filter( array_map( 'absint', explode( ',', $value ) ) ) ) : $value,
	10,
	3
);

// Related items are stored "1,2,3" too; SCF's relationship field reads a list.
add_filter(
	'acf/load_value/type=relationship',
	fn( $value, $post_id, $field ) => aw_scf_ours( $field ) && is_string( $value ) ? array_values( array_filter( array_map( 'absint', explode( ',', $value ) ) ) ) : $value,
	10,
	3
);

// Dates inside our repeaters are stored Y-m-d too, like every other date on the site.
add_filter(
	'acf/update_value/type=date_picker',
	fn( $value, $post_id, $field ) => aw_scf_ours( $field ) ? aw_scf_ymd( $value ) : $value,
	10,
	3
);

// The same format checks as meta.php's sanitising, shown as a message on the field instead of the value being dropped.
add_filter(
	'acf/validate_value',
	function ( $valid, $value, $field ) {
		if ( true !== $valid || ! aw_scf_top( $field ) || ! empty( $field['readonly'] ) || ! is_scalar( $value ) || '' === trim( (string) $value ) ) {
			return $valid;
		}
		if ( ! in_array( $field['type'], array( 'text', 'textarea', 'date_picker' ), true ) || ! array_key_exists( $field['name'], aw_meta_schema() ) ) {
			return $valid;
		}
		$v = 'date_picker' === $field['type'] ? aw_scf_ymd( $value ) : (string) $value;
		if ( '' === (string) aw_sanitize_meta( $v, $field['name'] ) ) {
			$hint = array( 'aw_lat' => 'Use decimal degrees, e.g. 28.5797.', 'aw_lng' => 'Use decimal degrees, e.g. 77.3167.', 'aw_g_rating' => 'Use a number out of 5, e.g. 4.6.', 'aw_g_reviews' => 'Use a whole number.', 'aw_founded' => 'Use a four-digit year, e.g. 2014.' );
			return $hint[ $field['name'] ] ?? 'This value is not in the expected format.';
		}
		return $valid;
	},
	10,
	3
);

/**
 * get_field() by name finds our field without a stored "_name" reference row: the reference comes from the field
 * group of the item's post type. (Our saves write no reference rows; see aw_value_guard.)
 */
add_filter(
	'acf/pre_load_reference',
	function ( $reference, $field_name, $post_id ) {
		if ( null !== $reference || ! is_numeric( $post_id ) || ( 0 !== strpos( (string) $field_name, 'aw_' ) && 'kits_last_reviewed' !== $field_name ) ) {
			return $reference;
		}
		static $map = null;
		if ( null === $map ) {
			$map = array();
			foreach ( aw_scf_group_keys() as $gk ) {
				$group = acf_get_field_group( $gk );
				if ( ! $group ) {
					continue;
				}
				foreach ( (array) $group['location'] as $rules ) {
					foreach ( $rules as $r ) {
						if ( 'post_type' === $r['param'] ) {
							foreach ( (array) acf_get_fields( $gk ) as $f ) {
								if ( '' !== $f['name'] ) {
									$map[ $r['value'] ][ $f['name'] ] = $f['key'];
								}
							}
						}
					}
				}
			}
		}
		return $map[ get_post_type( (int) $post_id ) ][ $field_name ] ?? $reference;
	},
	10,
	3
);
