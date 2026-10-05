<?php
/**
 * Volunteer sign-up: [aw_volunteer_form]. Entries are private posts visible to administrators only.
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
function aw_volunteer_form() {
	if ( isset( $_GET['aw_done'] ) && 'volunteer' === $_GET['aw_done'] ) { // phpcs:ignore WordPress.Security.NonceVerification
		return '<div class="aw-form-done" role="status" tabindex="-1"><h3>' . esc_html__( 'Thank you for signing up.', 'animal-writes-core' ) . '</h3><p>'
			. esc_html__( 'We will contact you when there is work in your area. You can ask us to delete your details at any time.', 'animal-writes-core' ) . '</p></div>';
	}
	$form   = 'aw_volunteer';
	$states = array( '' => 'Choose a state' );
	foreach ( get_terms( array( 'taxonomy' => 'aw_city', 'parent' => 0, 'hide_empty' => false, 'orderby' => 'name' ) ) as $t ) {
		$states[ $t->slug ] = $t->name;
	}
	$state  = aw_form_state( $form );
	$picked = (array) ( $state['values']['roles'] ?? array() );

	$html  = aw_form_open( $form );
	$html .= aw_field( $form, array( 'name' => 'name', 'label' => 'Your name', 'required' => true, 'attrs' => array( 'autocomplete' => 'name', 'maxlength' => 120 ) ) );
	$html .= aw_field( $form, array( 'name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'attrs' => array( 'autocomplete' => 'email', 'maxlength' => 160 ) ) );
	$html .= aw_field( $form, array( 'name' => 'phone', 'label' => 'Phone or WhatsApp', 'type' => 'tel', 'attrs' => array( 'autocomplete' => 'tel', 'inputmode' => 'tel', 'maxlength' => 20, 'pattern' => '\\+?[0-9 ]{8,15}', 'data-error' => 'Enter 8 to 15 digits, for example +91 98765 43210.' ) ) );
	$html .= aw_field( $form, array( 'name' => 'state', 'label' => 'State or union territory', 'type' => 'select', 'required' => true, 'options' => $states ) );
	$html .= aw_field( $form, array( 'name' => 'city', 'label' => 'City or district', 'attrs' => array( 'autocomplete' => 'address-level2', 'maxlength' => 80 ) ) );
	$html .= '<fieldset><legend>' . esc_html__( 'How would you like to help?', 'animal-writes-core' ) . '</legend>';
	foreach ( AW_VOL_ROLES as $k => $label ) {
		$html .= '<label class="aw-check"><input type="checkbox" name="roles[]" value="' . esc_attr( $k ) . '"' . checked( in_array( $k, $picked, true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
	}
	$html .= '</fieldset>';
	/* Phase 2: explicit consent checkbox (DPDP Act). */
	$html .= '<p><button type="submit" class="wp-element-button">' . esc_html__( 'Sign up to volunteer', 'animal-writes-core' ) . '</button></p></form>';
	return $html;
}
add_shortcode( 'aw_volunteer_form', 'aw_volunteer_form' );

/**
 * Handle a submission.
 */
function aw_volunteer_submit() {
	$form = 'aw_volunteer';
	$back = aw_form_back( '/get-involved/' );
	$done = add_query_arg( 'aw_done', 'volunteer', $back ) . '#volunteer';
	$rl   = aw_form_guard( $form, $back, 'volunteer', $done );
	$v    = array(
		'name'  => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
		'email' => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
		'phone' => trim( preg_replace( '/[^0-9+ ]/', '', (string) wp_unslash( $_POST['phone'] ?? '' ) ) ),
		'state' => sanitize_title( wp_unslash( $_POST['state'] ?? '' ) ),
		'city'  => sanitize_text_field( wp_unslash( $_POST['city'] ?? '' ) ),
		'roles' => array_values( array_intersect( array_keys( AW_VOL_ROLES ), array_map( 'sanitize_key', (array) wp_unslash( $_POST['roles'] ?? array() ) ) ) ),
	);
	$errors = aw_form_validate(
		array( 'name' => array( 'required', 'max:120' ), 'email' => array( 'required', 'email' ), 'phone' => array( 'phone' ), 'state' => array( 'required' ), 'city' => array( 'max:80' ) ),
		$v,
		array( 'name' => 'Your name', 'email' => 'Email', 'phone' => 'Phone or WhatsApp', 'state' => 'State or union territory', 'city' => 'City or district' )
	);
	$term = $v['state'] ? get_term_by( 'slug', $v['state'], 'aw_city' ) : null;
	if ( $v['state'] && ! $term ) {
		$errors['state'] = 'State or union territory: choose one from the list.';
	}
	if ( $errors ) {
		aw_form_fail( $form, $errors, $v, $back, 'volunteer' );
	}
	$id = wp_insert_post(
		array( 'post_type' => 'aw_volunteer', 'post_status' => 'private', 'post_title' => $v['name'] . ' (' . $term->name . ')' ),
		true
	);
	if ( is_wp_error( $id ) ) {
		aw_form_fail( $form, array(), $v, $back, 'volunteer', 'Your sign-up could not be saved. Please try again.' );
	}
	foreach ( array( 'aw_v_name' => $v['name'], 'aw_v_email' => $v['email'], 'aw_v_phone' => $v['phone'], 'aw_v_state' => $term->name, 'aw_v_city' => $v['city'], 'aw_v_roles' => implode( ', ', $v['roles'] ) ) as $k => $val ) {
		update_post_meta( $id, $k, $val );
	}
	aw_form_count( $rl );
	wp_mail( 'contact@animalwrites.in', 'New volunteer: ' . $term->name, "A new volunteer signed up.\nState: {$term->name}\nCity: {$v['city']}\nView in WP Admin > Volunteers." );
	wp_safe_redirect( $done );
	exit;
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
