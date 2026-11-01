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

/** True when SCF (or ACF) is active and loads our field groups. */
function aw_scf_active() {
	return function_exists( 'acf_add_local_field_group' );
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

// Stop SCF being deactivated (bulk action, WP-CLI, any caller) unless Animal Writes core is deactivated first or in
// the same request: the plugins list is in name order, so core comes before SCF in a bulk deactivation.
add_action(
	'deactivate_plugin',
	function ( $plugin ) {
		static $core_off = false;
		if ( plugin_basename( AW_CORE_DIR . 'animal-writes-core.php' ) === $plugin ) {
			$core_off = true;
			return;
		}
		if ( AW_SCF_PLUGIN === $plugin && ! $core_off ) {
			wp_die(
				esc_html__( 'Secure Custom Fields stays on while Animal Writes core is on: the edit screens use it. Deactivate Animal Writes core first.', 'animal-writes-core' ),
				esc_html__( 'Secure Custom Fields stays on', 'animal-writes-core' ),
				array(
					'response'  => 403,
					'back_link' => true,
				)
			);
		}
	}
);

/** Our field groups (keys of the files in acf-json/). */
const AW_SCF_GROUPS = array( 'group_aw_listing', 'group_aw_listing_address', 'group_aw_listing_hours', 'group_aw_listing_registrations', 'group_aw_listing_about', 'group_aw_listing_online', 'group_aw_listing_photos', 'group_aw_listing_claimed', 'group_aw_opportunity', 'group_aw_news', 'group_aw_legal', 'group_aw_legal_document', 'group_aw_campaign', 'group_aw_campaign_dates', 'group_aw_campaign_document', 'group_aw_campaign_orgs', 'group_aw_campaign_outcome', 'group_aw_campaign_share', 'group_aw_resource' );

add_filter(
	'acf/settings/load_json',
	function ( $paths ) {
		$paths[] = AW_CORE_DIR . 'acf-json';
		return $paths;
	}
);
foreach ( AW_SCF_GROUPS as $aw_scf_group ) {
	add_filter( 'acf/settings/save_json/key=' . $aw_scf_group, fn() => AW_CORE_DIR . 'acf-json' );
}
unset( $aw_scf_group );

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
		case 'post_object':
			$value = is_array( $value ) ? ( $value['ID'] ?? reset( $value ) ) : $value;
			$value = absint( $value ) ? (string) absint( $value ) : '';
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
			foreach ( AW_SCF_GROUPS as $gk ) {
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
