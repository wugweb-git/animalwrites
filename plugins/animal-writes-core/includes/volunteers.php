<?php
/**
 * Volunteer sign-up form (shared form kit): name, email, phone, PIN code → State and District (LGD), how you'd like to
 * help, consent. Entries go to the volunteers table (Inbox → Volunteer sign-ups) with the page they came from.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_VOL_ROLES = array(
	'coordinate' => 'Coordinate volunteers in my city',
	'letters'    => 'Write and post letters, and get others to',
	'rescue'     => 'Rescue, foster or transport animals',
	'creative'   => 'Design, writing, photos or social media',
	'legal'      => 'Legal or policy work',
);

add_action(
	'init',
	function () {
		register_post_type(
			'aw_volunteer',
			array(
				'labels'          => array( 'name' => 'Volunteers', 'singular_name' => 'Volunteer', 'menu_name' => 'Volunteers' ),
				'public'          => false,
				'show_ui'         => true,
				'show_in_rest'    => false,
				'menu_icon'       => 'dashicons-groups',
				'supports'        => array( 'title' ),
				'capability_type' => array( 'aw_volunteer', 'aw_volunteers' ),
				'map_meta_cap'    => true,
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
			)
		);
		// Only administrators may read or manage volunteer data.
		$admin = get_role( 'administrator' );
		if ( $admin && ! $admin->has_cap( 'edit_aw_volunteers' ) ) {
			foreach ( array( 'edit', 'read', 'delete' ) as $verb ) {
				foreach ( array( 'aw_volunteer', 'aw_volunteers', 'others_aw_volunteers', 'private_aw_volunteers', 'published_aw_volunteers' ) as $obj ) {
					$admin->add_cap( "{$verb}_{$obj}" );
				}
			}
			$admin->add_cap( 'publish_aw_volunteers' );
		}
	}
);

/**
 * Form markup.
 *
 * @return string
 */
function aw_volunteer_form( $source = '' ) {
	if ( isset( $_GET['aw_done'] ) && 'volunteer' === $_GET['aw_done'] ) { // phpcs:ignore WordPress.Security.NonceVerification
		return '<div class="aw-form-done kit-form-done" role="status" tabindex="-1"><p class="aw-form-done-title">' . esc_html__( 'Thank you for signing up.', 'animal-writes-core' ) . '</p><p>'
			. esc_html__( 'We will contact you when there is work in your area. You can ask us to delete your details at any time.', 'animal-writes-core' ) . '</p></div>';
	}
	$form   = 'aw_volunteer';
	$state  = aw_form_state( $form );
	$picked = (array) ( $state['values']['roles'] ?? array() );
	$err    = $state['errors']['roles'] ?? '';
	$html   = aw_form_open( $form, array_filter( array( 'class' => 'aw-form kit-form aw-volunteer-form', 'data-source' => $source ) ) );
	$html  .= '<div class="kit-form-grid">';
	$html  .= kits_dict_field( $form, 'name', array( 'required' => true ) );
	$html  .= kits_dict_field( $form, 'email' );
	$html  .= kits_dict_field( $form, 'phone' );
	$html  .= kits_dict_field( $form, 'pincode' );
	$html  .= aw_place_fields( $form );
	$html  .= '</div>';
	$html  .= '<fieldset class="kit-field kit-check-group' . ( $err ? ' is-invalid' : '' ) . '" data-min="1" data-msg-required="Choose how you\'d like to help" aria-describedby="aw-volunteer-roles-error"><legend>How would you like to help?</legend>';
	foreach ( AW_VOL_ROLES as $k => $label ) {
		$html .= '<label class="kit-checkbox"><input type="checkbox" name="roles[]" value="' . esc_attr( $k ) . '"' . checked( in_array( $k, $picked, true ), true, false ) . '> <span>' . esc_html( $label ) . '</span></label>';
	}
	$html .= '<span class="kit-field-message aw-field-error" id="aw-volunteer-roles-error" aria-live="polite">' . esc_html( $err ) . '</span></fieldset>';
	$html .= kits_dict_field( $form, 'consent', array( 'label' => 'I agree that Animal Writes may keep these details and contact me about volunteering. I can ask for them to be deleted at any time.' ) );
	$html .= '<div class="kit-form-actions"><button type="submit" class="kit-btn">' . esc_html__( 'Sign up to volunteer', 'animal-writes-core' ) . '</button></div></form>';
	return $html;
}
add_shortcode( 'aw_volunteer_form', fn() => aw_volunteer_form() );

/**
 * Handle a submission.
 */
function aw_volunteer_submit() {
	$form = 'aw_volunteer';
	$back = aw_form_back( '/volunteer/' );
	$done = add_query_arg( 'aw_done', 'volunteer', $back ) . '#volunteer';
	$rl   = aw_form_guard( $form, $back, 'volunteer', $done );
	$v    = array(
		'name'     => trim( sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ) ),
		'email'    => aw_clean_email( sanitize_text_field( wp_unslash( $_POST['email'] ?? '' ) ) ),
		'phone'    => trim( sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ) ),
		'pincode'  => preg_replace( '/\D/', '', (string) wp_unslash( $_POST['pincode'] ?? '' ) ),
		'state'    => sanitize_title( wp_unslash( $_POST['state'] ?? '' ) ),
		'district' => sanitize_title( wp_unslash( $_POST['district'] ?? '' ) ),
		'consent'  => empty( $_POST['consent'] ) ? '' : '1', // phpcs:ignore WordPress.Security.NonceVerification
		'roles'    => array_values( array_intersect( array_keys( AW_VOL_ROLES ), array_map( 'sanitize_key', (array) wp_unslash( $_POST['roles'] ?? array() ) ) ) ),
	);
	list( $rules, $msgs ) = kits_dict_rules( array( 'name' => 'name', 'email' => 'email', 'phone' => 'phone', 'pincode' => 'pincode', 'state' => 'state', 'consent' => 'consent' ), array( 'name', 'email', 'state', 'consent' ) );
	$msgs['consent']['required'] = 'Tick the box to agree';
	$errors = aw_form_validate( $rules, $v, array( 'name' => 'Your name', 'email' => 'Email', 'phone' => 'Phone', 'pincode' => 'PIN code', 'state' => 'State', 'consent' => 'Agreement' ), $msgs );
	$term = $v['state'] ? aw_place_by_slug( $v['state'] ) : null;
	$term = $term && 'state' === $term->kind ? $term : null;
	if ( $v['state'] && ! $term ) {
		$errors['state'] = 'Choose a state';
	}
	$dist = $v['district'] ? aw_place_by_slug( $v['district'] ) : null;
	if ( $v['district'] && ( ! $dist || 'district' !== $dist->kind || ! $term || $dist->state !== $term->code ) ) {
		$errors['district'] = 'Choose a district';
	}
	if ( ! $v['roles'] ) {
		$errors['roles'] = 'Choose how you\'d like to help';
	}
	if ( $errors ) {
		aw_form_fail( $form, $errors, $v, $back, 'volunteer' );
	}
	// Stored in the volunteers table (one row per email; signing up again updates it).
	aw_vol_upsert(
		array(
			'name'                 => $v['name'],
			'email'                => $v['email'],
			'phone'                => kits_clean_phone( $v['phone'] ),
			'pincode'              => $v['pincode'],
			'state_slug'           => $term->slug,
			'district_slug'        => $dist ? $dist->slug : '',
			'city_text'            => $dist ? $dist->name : '',
			'page_source'          => kits_submitted_source(),
			'role'                 => implode( ', ', $v['roles'] ),
			'source'               => 'v2_form',
			'consent_scope'        => 'volunteering',
			'consent_text_version' => 'v2-form-consent-1',
		)
	);
	aw_form_count( $rl );
	wp_mail( 'contact@animalwrites.in', 'New volunteer: ' . $term->name, "A new volunteer signed up.\nState: {$term->name}\nDistrict: " . ( $dist ? $dist->name : '' ) . "\nFrom: " . kits_submitted_source() . "\nView in WP Admin > Inbox > Volunteer sign-ups." );
	aw_form_success( $done, __( 'Thank you for signing up.', 'animal-writes-core' ), __( 'We will contact you when there is work in your area. You can ask us to delete your details at any time.', 'animal-writes-core' ) );
}
add_action( 'admin_post_nopriv_aw_volunteer', 'aw_volunteer_submit' );
add_action( 'admin_post_aw_volunteer', 'aw_volunteer_submit' );

// Admin list: useful columns, no personal data in the title beyond name and state.
add_filter( 'manage_aw_volunteer_posts_columns', fn( $c ) => array( 'cb' => $c['cb'], 'title' => 'Name (state)', 'aw_email' => 'Email', 'aw_phone' => 'Phone', 'aw_city' => 'City', 'aw_roles' => 'Wants to', 'date' => 'Signed up' ) );
add_action(
	'manage_aw_volunteer_posts_custom_column',
	function ( $col, $id ) {
		$map = array( 'aw_email' => 'aw_v_email', 'aw_phone' => 'aw_v_phone', 'aw_city' => 'aw_v_city', 'aw_roles' => 'aw_v_roles' );
		if ( isset( $map[ $col ] ) ) {
			echo esc_html( (string) get_post_meta( $id, $map[ $col ], true ) );
		}
	},
	10,
	2
);
