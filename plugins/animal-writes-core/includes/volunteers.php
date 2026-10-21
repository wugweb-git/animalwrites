<?php
/**
 * Volunteer sign-up form (shared form kit): name, email, phone, PIN code → State and District (LGD), how you'd like to
 * help, consent. Entries go to the volunteers table (the backup) with the page they came from, and to FluentCRM: a
 * contact tagged Volunteer with the details in custom fields (fluentcrm.php).
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
		return '<div class="kit-form-done" role="status" tabindex="-1"><p class="aw-form-done-title">' . esc_html__( 'Thank you for signing up.', 'animal-writes-core' ) . '</p><p>'
			. esc_html__( 'We will contact you when there is work in your area. You can ask us to delete your details at any time.', 'animal-writes-core' ) . '</p></div>';
	}
	$ff = apply_filters( 'aw_form_html', '', 'volunteer', array( 'source' => $source ) ); // the Fluent Forms version when it is set up (forms-fluentforms.php)
	if ( '' !== $ff ) {
		return $ff;
	}
	$form   = 'aw_volunteer';
	$state  = aw_form_state( $form );
	$picked = (array) ( $state['values']['roles'] ?? array() );
	$err    = $state['errors']['roles'] ?? '';
	$html   = aw_form_open( $form, array_filter( array( 'class' => 'kit-form aw-volunteer-form', 'data-source' => $source ) ) );
	$html  .= '<fieldset class="kit-field kit-check-group aw-vol-roles' . ( $err ? ' is-invalid' : '' ) . '" data-min="1" data-msg-required="Choose how you\'d like to help" aria-describedby="aw-volunteer-roles-error"><legend>How would you like to help?</legend>';
	foreach ( AW_VOL_ROLES as $k => $label ) {
		$html .= '<label class="kit-checkbox aw-vol-role"><input type="checkbox" name="roles[]" value="' . esc_attr( $k ) . '"' . checked( in_array( $k, $picked, true ), true, false ) . '> <span>' . esc_html( $label ) . '</span></label>';
	}
	$html .= '<span class="kit-field-message kit-field-error" id="aw-volunteer-roles-error" aria-live="polite">' . esc_html( $err ) . '</span></fieldset>';
	$html  .= '<div class="kit-form-grid aw-vol-details">';
	$html  .= kits_dict_field( $form, 'name', array( 'required' => true ) );
	$html  .= kits_dict_field( $form, 'email' );
	$html  .= kits_dict_field( $form, 'phone' );
	$html  .= kits_dict_field( $form, 'pincode' );
	$html  .= aw_place_fields( $form );
	$html  .= '</div>';
	$html .= kits_dict_field( $form, 'consent', array( 'label' => AW_VOL_CONSENT_LABEL ) );
	$html .= function_exists( 'aw_nl_optin_box' ) ? aw_nl_optin_box( $form, true ) : ''; // updates: their own box, ticked (owner, 20 Oct 2026)
	$html .= '<div class="kit-form-actions"><button type="submit" class="kit-btn">' . esc_html__( 'Sign up to volunteer', 'animal-writes-core' ) . '</button></div></form>';
	return $html;
}
add_shortcode( 'aw_volunteer_form', fn() => aw_volunteer_form() );

/** The consent line on the volunteer form and its version. Updates are a separate, unticked box (owner, 20 Oct 2026; DPDP:
 *  consent per purpose), so this line covers volunteering only. */
const AW_VOL_CONSENT_LABEL   = 'I agree that Animal Writes may keep these details and contact me about volunteering. I can ask for them to be deleted at any time.';
const AW_VOL_CONSENT_VERSION = 'v3-form-consent-volunteering';

/** A sign-up's values from submitted data (already unslashed): the site's form and Fluent Forms use this. */
function aw_volunteer_values( $in ) {
	$roles = $in['roles'] ?? array();
	return array(
		'name'     => trim( sanitize_text_field( (string) ( $in['name'] ?? '' ) ) ),
		'email'    => aw_clean_email( sanitize_text_field( (string) ( $in['email'] ?? '' ) ) ),
		'phone'    => trim( sanitize_text_field( (string) ( $in['phone'] ?? '' ) ) ),
		'pincode'  => preg_replace( '/\D/', '', (string) ( $in['pincode'] ?? '' ) ),
		'state'    => sanitize_title( (string) ( $in['state'] ?? '' ) ),
		'district' => sanitize_title( (string) ( $in['district'] ?? '' ) ),
		'consent'  => empty( $in['consent'] ) ? '' : '1',
		'updates'  => ! empty( $in['nl_optin'] ), // the updates box as sent (recorded only; volunteers join either way)
		'roles'    => array_values( array_intersect( array_keys( AW_VOL_ROLES ), array_map( 'sanitize_key', (array) $roles ) ) ),
	);
}

/** Server rules for a sign-up: field => message. */
function aw_volunteer_errors( $v ) {
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
	return $errors;
}

/**
 * Save a checked sign-up to the volunteers table (one row per email; signing up again updates it), email the team, add
 * the volunteer to FluentCRM (tag Volunteer) and to the newsletter (owner, 20 Oct 2026: they join whether or not the
 * updates box stays ticked).
 *
 * @return array array{url, title, text} for the thank-you page.
 */
function aw_volunteer_save( $v, $source ) {
	$term  = aw_place_by_slug( $v['state'] );
	$dist  = $v['district'] ? aw_place_by_slug( $v['district'] ) : null;
	$saved = aw_vol_upsert(
		array(
			'name'                 => $v['name'],
			'email'                => $v['email'],
			'phone'                => kits_clean_phone( $v['phone'] ),
			'pincode'              => $v['pincode'],
			'state_slug'           => $term->slug,
			'district_slug'        => $dist ? $dist->slug : '',
			'city_text'            => $dist ? $dist->name : '',
			'page_source'          => $source,
			'role'                 => implode( ', ', $v['roles'] ),
			'source'               => 'v2_form',
			'consent_scope'        => 'volunteering',
			'consent_text_version' => AW_VOL_CONSENT_VERSION,
		)
	);
	$again = 'updated' === $saved; // already registered (same email): their details were updated, no second record
	wp_mail( 'contact@animalwrites.in', ( $again ? 'Volunteer updated their details: ' : 'New volunteer: ' ) . $v['name'] . ', ' . $term->name, ( $again ? 'A registered volunteer signed up again; their details were updated.' : 'A new volunteer signed up.' ) . "\nName: {$v['name']}\nState: {$term->name}\nDistrict: " . ( $dist ? $dist->name : '' ) . "\nFrom: " . $source . "\nView in FluentCRM > Contacts (tag Volunteer)." );
	if ( function_exists( 'aw_newsletter_join' ) ) { // volunteers join the newsletter (owner, 20 Oct 2026); no email from this
		aw_newsletter_join( $v['email'], $v['name'], 'site:volunteer', AW_VOL_CONSENT_VERSION, aw_crm_volunteer_fields( $term->slug, $dist ? $dist->slug : '', $dist ? $dist->name : '', $v['pincode'], kits_clean_phone( $v['phone'] ) ), array( 'volunteer' ) );
	}
	if ( function_exists( 'aw_crm_volunteer_sync' ) ) { // their volunteer details on the FluentCRM contact (custom fields); not subscribed unless ticked
		aw_crm_volunteer_sync( $v['email'] );
	}
	$title = $again ? __( 'You are already registered.', 'animal-writes-core' ) : __( 'Thank you for signing up.', 'animal-writes-core' );
	$text  = $again ? __( 'We have updated your details. We will contact you when there is work in your area. You can ask us to delete your details at any time.', 'animal-writes-core' ) : __( 'We will contact you when there is work in your area. You can ask us to delete your details at any time.', 'animal-writes-core' );
	return array( 'url' => kits_thanks_url( 'volunteer', array(), $title, $text ), 'title' => $title, 'text' => $text );
}

/* ---------- The update link's page: /update/?t=<token> (owner, 21 Oct 2026; the link: aw_update_link()) ---------- */

/** [aw_volunteer_update]: the Volunteer form filled in from their FluentCRM contact, or why the link does not work. */
add_shortcode(
	'aw_volunteer_update',
	function () {
		$c = function_exists( 'aw_update_link_contact' ) ? aw_update_link_contact( sanitize_text_field( wp_unslash( $_GET['t'] ?? '' ) ) ) : 'invalid'; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! is_object( $c ) ) {
			return '<p>' . esc_html( 'expired' === $c ? 'This link has expired. Ask us for a new one at contact@animalwrites.in.' : 'This link does not work. Check that it is complete, or ask us for a new one at contact@animalwrites.in.' ) . '</p>';
		}
		$html = apply_filters( 'aw_form_html', '', 'volunteer', array( 'vol_contact' => $c ) );
		return '' !== $html ? '<p>Check your details and change what is different. Sending the form updates them.</p>' . $html : '<p>This form is not available right now.</p>';
	}
);

/** The Volunteer form's fields, filled in from the update link's contact (forms-fluentforms.php calls this). */
function aw_ff_update_values( $data, $form ) {
	$c = function_exists( 'aw_ff_ctx' ) ? aw_ff_ctx( 'vol_contact' ) : null;
	if ( ! $c || 'volunteer' !== aw_ff_key( $form ) ) {
		return $data;
	}
	$f    = (array) $c->custom_fields();
	$list = fn( $k ) => array_values( array_filter( array_map( 'trim', explode( ',', (string) ( $f[ $k ] ?? '' ) ) ) ) );
	$name = (string) ( $data['attributes']['name'] ?? '' );
	$text = array( 'name' => trim( $c->first_name . ' ' . $c->last_name ), 'email' => $c->email, 'phone' => (string) $c->phone, 'whatsapp' => $f['vol_whatsapp'] ?? '', 'travel_range' => $f['vol_travel_range'] ?? '', 'languages' => $f['vol_languages'] ?? '', 'org_name' => $f['vol_organisation'] ?? '' );
	if ( isset( $text[ $name ] ) ) {
		$data['attributes']['value'] = (string) $text[ $name ];
	} elseif ( in_array( $name, array( 'availability', 'skills' ), true ) ) {
		$data['attributes']['value'] = $list( 'vol_' . $name );
	} elseif ( 'roles' === $name ) {
		$data['attributes']['value'] = $list( 'vol_help' );
	} elseif ( 'wa_optin' === $name ) {
		$data['attributes']['value'] = '' !== (string) ( $f['vol_wa_optin'] ?? '' ) ? array( '1' ) : array();
	} elseif ( 'org_assoc' === $name ) {
		$data['attributes']['value'] = '' !== (string) ( $f['vol_organisation'] ?? '' ) ? array( '1' ) : array();
	} elseif ( 'org_show' === $name ) {
		$data['attributes']['value'] = '' !== (string) ( $f['vol_org_show'] ?? '' ) ? array( '1' ) : array();
	}
	return $data;
}

// /update/ is made once, never cached (it shows one person's details) and never indexed.
add_action(
	'init',
	function () {
		if ( '1' === get_option( 'aw_update_page' ) ) {
			return;
		}
		if ( ! get_page_by_path( 'update' ) ) {
			$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Update your volunteer details', 'post_name' => 'update', 'post_content' => '<!-- wp:shortcode -->[aw_volunteer_update]<!-- /wp:shortcode -->' ) );
			if ( $id && ! is_wp_error( $id ) ) {
				update_post_meta( $id, 'rank_math_robots', array( 'noindex', 'nofollow' ) );
			}
		}
		update_option( 'aw_update_page', '1', false );
	},
	47
);
add_action(
	'template_redirect',
	function () {
		if ( is_page( 'update' ) ) {
			nocache_headers();
			do_action( 'litespeed_control_set_nocache', 'personal update link' );
		}
	}
);
add_filter( 'aw_cdn_excluded_slugs', fn( $s ) => array_merge( (array) $s, array( 'update' ) ) );

/**
 * Handle a submission.
 */
function aw_volunteer_submit() {
	$form   = 'aw_volunteer';
	$back   = aw_form_back( '/volunteer/' );
	$done   = kits_thanks_url( 'volunteer' );
	$rl     = aw_form_guard( $form, $back, 'volunteer', $done );
	$v      = aw_volunteer_values( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification -- checked in aw_form_guard()
	$errors = aw_volunteer_errors( $v );
	if ( $errors ) {
		aw_form_fail( $form, $errors, $v, $back, 'volunteer' );
	}
	$res = aw_volunteer_save( $v, kits_submitted_source() );
	aw_form_count( $rl );
	aw_form_success( $res['url'], $res['title'], $res['text'] );
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
