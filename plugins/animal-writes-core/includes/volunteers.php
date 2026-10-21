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
 * $extra (the Fluent Forms form, aw_volunteer_extra()): WhatsApp and its 1/0 tick, availability, travel range, skills,
 * languages, the organisation named and its 1/0 "show my name" tick, the entry. While a Fluent Forms form is handled,
 * FluentCRM is its own link, and the volunteer fields are copied after it (forms-fluentforms.php).
 *
 * @return array array{url, title, text} for the thank-you page.
 */
function aw_volunteer_save( $v, $source, $extra = array() ) {
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
	if ( $extra ) {
		aw_vol_set_extra( $v['email'], $extra );
	}
	wp_mail( 'contact@animalwrites.in', ( $again ? 'Volunteer updated their details: ' : 'New volunteer: ' ) . $v['name'] . ', ' . $term->name, ( $again ? 'A registered volunteer signed up again; their details were updated.' : 'A new volunteer signed up.' ) . "\nName: {$v['name']}\nState: {$term->name}\nDistrict: " . ( $dist ? $dist->name : '' ) . "\nFrom: " . $source . "\nView in FluentCRM > Contacts (tag Volunteer)." );
	if ( function_exists( 'aw_newsletter_join' ) ) { // volunteers join the newsletter (owner, 20 Oct 2026); no email from this
		aw_newsletter_join( $v['email'], $v['name'], 'site:volunteer', AW_VOL_CONSENT_VERSION, aw_crm_volunteer_fields( $term->slug, $dist ? $dist->slug : '', $dist ? $dist->name : '', $v['pincode'], kits_clean_phone( $v['phone'] ) ), array( 'volunteer' ) );
	}
	if ( empty( $GLOBALS['aw_ff_handling'] ) && function_exists( 'aw_crm_volunteer_sync' ) ) { // their volunteer details on the FluentCRM contact (custom fields)
		aw_crm_volunteer_sync( $v['email'] );
	}
	$title = $again ? __( 'You are already registered.', 'animal-writes-core' ) : __( 'Thank you for signing up.', 'animal-writes-core' );
	$text  = $again ? __( 'We have updated your details. We will contact you when there is work in your area. You can ask us to delete your details at any time.', 'animal-writes-core' ) : __( 'We will contact you when there is work in your area. You can ask us to delete your details at any time.', 'animal-writes-core' );
	return array( 'url' => kits_thanks_url( 'volunteer', array(), $title, $text ), 'title' => $title, 'text' => $text );
}

/** The Volunteer form's further answers (Fluent Forms entry data) for aw_vol_set_extra(). */
function aw_volunteer_extra( $data ) {
	return array(
		'whatsapp'     => function_exists( 'aw_ff_whatsapp' ) ? aw_ff_whatsapp( $data ) : '',
		'wa_optin'     => function_exists( 'aw_ff_tick' ) ? aw_ff_tick( $data, 'wa_optin' ) : 0,
		'availability' => (array) ( $data['availability'] ?? array() ),
		'travel_range' => sanitize_key( (string) ( $data['travel_range'] ?? '' ) ),
		'skills'       => (array) ( $data['skills'] ?? array() ),
		'languages'    => (string) ( $data['languages'] ?? '' ),
		'org_name'     => trim( (string) ( $data['org_name'] ?? '' ) ),
		'org_show'     => function_exists( 'aw_ff_tick' ) ? aw_ff_tick( $data, 'org_show' ) : 0,
	);
}

/* ---------- The personal update link: /update/?t=<token> (owner, 21 Oct 2026) ---------- */

/** Days an update link works. */
const AW_VOL_LINK_DAYS = 30;

/**
 * A volunteer's update link: signed (HMAC with the site's own secret), for one row and its email, 30 days. Nothing is
 * sent: an administrator copies it (Volunteers list) or exports the CSV for their own email tool.
 *
 * @param array $row Volunteers table row.
 */
function aw_vol_update_link( $row ) {
	$exp = time() + AW_VOL_LINK_DAYS * DAY_IN_SECONDS;
	$sig = hash_hmac( 'sha256', (int) $row['id'] . '|' . $exp . '|' . strtolower( (string) $row['email'] ), wp_salt( 'auth' ) );
	return add_query_arg( 't', (int) $row['id'] . '.' . $exp . '.' . substr( $sig, 0, 32 ), home_url( '/update/' ) );
}

/**
 * The row an update link is for.
 *
 * @param string $token The t value.
 * @return array|string The row, or 'expired' | 'invalid'.
 */
function aw_vol_link_row( $token ) {
	global $wpdb;
	if ( ! preg_match( '/^(\d+)\.(\d+)\.([a-f0-9]{32})$/', (string) $token, $m ) ) {
		return 'invalid';
	}
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . aw_vol_table() . ' WHERE id = %d', (int) $m[1] ), ARRAY_A ); // phpcs:ignore
	$sig = $row ? substr( hash_hmac( 'sha256', (int) $m[1] . '|' . (int) $m[2] . '|' . strtolower( (string) $row['email'] ), wp_salt( 'auth' ) ), 0, 32 ) : '';
	if ( ! $row || ! hash_equals( $sig, $m[3] ) || 'deleted' === $row['status'] ) {
		return 'invalid';
	}
	return (int) $m[2] < time() ? 'expired' : $row;
}

/** [aw_volunteer_update] on /update/: the Volunteer form filled in with their details, or why the link does not work. */
add_shortcode(
	'aw_volunteer_update',
	function () {
		$row = aw_vol_link_row( sanitize_text_field( wp_unslash( $_GET['t'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! is_array( $row ) ) {
			return '<p>' . esc_html( 'expired' === $row ? 'This link has expired. Ask us for a new one at contact@animalwrites.in.' : 'This link does not work. Check that it is complete, or ask us for a new one at contact@animalwrites.in.' ) . '</p>';
		}
		$html = apply_filters( 'aw_form_html', '', 'volunteer', array( 'vol_row' => $row ) );
		return '' !== $html ? '<p>Check your details and change what is different. Saving updates your sign-up.</p>' . $html : aw_volunteer_form( 'update' );
	}
);

/** On an update link, the Volunteer form's fields start with the row's values (forms-fluentforms.php calls this). */
function aw_ff_update_values( $data, $form ) {
	$row = function_exists( 'aw_ff_ctx' ) ? aw_ff_ctx( 'vol_row' ) : null;
	if ( ! $row || 'volunteer' !== aw_ff_key( $form ) ) {
		return $data;
	}
	$name = (string) ( $data['attributes']['name'] ?? '' );
	$map  = array( 'name' => $row['name'], 'email' => $row['email'], 'phone' => $row['phone'], 'whatsapp' => $row['whatsapp'] ?? '', 'travel_range' => $row['travel_range'] ?? '', 'languages' => $row['languages'] ?? '', 'org_name' => $row['org_name'] ?? '' );
	if ( isset( $map[ $name ] ) && '' !== (string) $map[ $name ] ) {
		$data['attributes']['value'] = (string) $map[ $name ];
	} elseif ( in_array( $name, array( 'availability', 'skills' ), true ) ) {
		$data['attributes']['value'] = (array) json_decode( (string) ( $row[ $name ] ?? '' ), true );
	} elseif ( 'roles' === $name ) {
		$data['attributes']['value'] = array_map( 'trim', explode( ',', (string) $row['role'] ) );
	} elseif ( 'wa_number' === $name && '' !== (string) ( $row['whatsapp'] ?? '' ) ) {
		$data['attributes']['value'] = $row['whatsapp'] === $row['phone'] ? 'same' : 'other';
	} elseif ( in_array( $name, array( 'wa_optin', 'org_show' ), true ) && null !== ( $row[ $name ] ?? null ) ) {
		$data['attributes']['value'] = (string) (int) $row[ $name ];
	}
	return $data;
}

// /update/ is made once, never cached (it carries one person's details) and never indexed.
add_action(
	'init',
	function () {
		if ( '1' === get_option( 'aw_vol_update_page' ) ) {
			return;
		}
		if ( ! get_page_by_path( 'update' ) ) {
			$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Update your volunteer details', 'post_name' => 'update', 'post_content' => '<!-- wp:shortcode -->[aw_volunteer_update]<!-- /wp:shortcode -->' ) );
			if ( $id && ! is_wp_error( $id ) ) {
				update_post_meta( $id, 'rank_math_robots', array( 'noindex', 'nofollow' ) );
			}
		}
		update_option( 'aw_vol_update_page', '1', false );
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
