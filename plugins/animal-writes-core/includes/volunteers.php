<?php
/**
 * Volunteer sign-up: the Fluent Forms form "Volunteer sign-up" (forms-fluentforms.php), the only form since 25 Oct 2026
 * (owner: the site's own form and its handler were removed). Each sign-up is a FluentCRM contact tagged Volunteer, by
 * the form's own FluentCRM link (owner, 24 Oct 2026: FluentCRM is the one volunteers table). The site's volunteers
 * table is a read-only backup (volunteers-db.php); the aw_volunteer post type stays registered for its old items.
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

/** The volunteer form (the block in form-blocks.php): the Fluent Forms form. $source: the page it is on (S6.6). */
function aw_volunteer_form( $source = '' ) {
	return aw_ff_form( 'volunteer', array( 'source' => $source ) );
}

/** A sign-up's values from submitted data (already unslashed); aw_ff_errors() checks the place fields with them. */
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

/* ---------- The update link's page: /update/?t=<token> (owner, 21 Oct 2026; the link: aw_update_link()) ---------- */

/** [aw_volunteer_update]: the Volunteer form filled in from their FluentCRM contact, or why the link does not work. */
add_shortcode(
	'aw_volunteer_update',
	function () {
		$c = function_exists( 'aw_update_link_contact' ) ? aw_update_link_contact( sanitize_text_field( wp_unslash( $_GET['t'] ?? '' ) ) ) : 'invalid'; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! is_object( $c ) ) {
			return '<p>' . esc_html( 'expired' === $c ? 'This link has expired. Ask us for a new one at contact@animalwrites.in.' : 'This link does not work. Check that it is complete, or ask us for a new one at contact@animalwrites.in.' ) . '</p>';
		}
		return aw_ff_id( 'volunteer' ) ? '<p>Check your details and change what is different. Sending the form updates them.</p>' . aw_ff_form( 'volunteer', array( 'vol_contact' => $c ) ) : '<p>This form is not available right now.</p>' . aw_ff_missing( 'Volunteer sign-up', 'wp aw ff-form volunteer <form ID>' );
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

// /update/ was made once (that step ran and was removed (audit item 16, flag confirmed on live 25 Oct 2026; see git history)); it is never cached (it shows one person's details) and never indexed.
add_action(
	'template_redirect',
	function () {
		if ( is_page( 'update' ) ) {
			nocache_headers();
			do_action( 'litespeed_control_set_nocache', 'personal update link' );
			header( 'Referrer-Policy: no-referrer' ); // the token never leaves in a referrer (GA4's copy is stripped in GTM);
			// the server's .htaccess sets its own Referrer-Policy over this header, so the page also says it (wp_head below)
		}
	}
);
// No referrer from /update/ (the browser follows the page's own meta over the server's Referrer-Policy header).
add_action(
	'wp_head',
	function () {
		if ( is_page( 'update' ) ) {
			echo '<meta name="referrer" content="no-referrer">' . "\n";
		}
	},
	0
);
// Microsoft Clarity is not loaded on /update/ at all (owner, 21 Oct 2026): neither the site's own loader (kits-core
// analytics.php, kits_clarity_id) nor the Microsoft Clarity plugin's script (wp_head) or its server-side page view
// (shutdown), which would send the address with its token.
add_filter( 'kits_clarity_id', fn( $id ) => is_page( 'update' ) ? '' : $id, 20 );
add_action(
	'wp',
	function () {
		if ( is_page( 'update' ) ) {
			remove_action( 'wp_head', 'clarity_add_script_to_header' );
			remove_action( 'shutdown', 'clarity_collect_event' );
		}
	}
);
// Microsoft Clarity: strict masking on /update/ as well (kept in case a Clarity script is added another way) (it shows one person's details); set before Clarity loads, which is
// only on the visitor's first scroll, tap or key (kits-core analytics.php).
add_action(
	'wp_body_open',
	function () {
		if ( is_page( 'update' ) ) {
			echo "<script>document.body.setAttribute('data-clarity-mask','True');</script>\n";
		}
	}
);
add_filter( 'aw_cdn_excluded_slugs', fn( $s ) => array_merge( (array) $s, array( 'update' ) ) );

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
