<?php
/**
 * The site's forms in Fluent Forms (free) and FluentCRM (free), built with their own features (owner, 21 Oct 2026).
 * Newsletter #3, Contact #4, Volunteer sign-up #5, Claim this listing #6, Report a mistake #7, Remove my data #8,
 * Newsletter (full) #9. Fields, conditions, hidden fields, confirmation (with the reference
 * {submission.serial_number}) and each form's Fluent Forms → FluentCRM link are Fluent Forms settings, versioned in
 * /forms/*.json and put on a site with tools/forms/forms.php. Requests are Fluent Forms entries (favourite = needs
 * action, notes = what was done); people are FluentCRM contacts.
 *
 * Custom code here, only where the plugins cannot do it:
 * - Three custom fields: the spam check (ALTCHA, no third party), PIN code → State → District (the official LGD list;
 *   District follows State) and the private upload (claim logo and proof: stored outside the web, opened by an
 *   administrator from the entry's link).
 * - After each form's FluentCRM link: what a link cannot do. A Contact sender who left the newsletter unticked still
 *   becomes a contact (transactional, tag contact-form) with the message as a note; a claimant becomes a contact
 *   (transactional, tag claimant); every contact gets the newsletter box as 1/0 ("Newsletter box (last form)"); a
 *   volunteer's State, District and PIN code from the LGD picker go into their Volunteer fields.
 * - The site's look on Fluent Forms fields (icons, tick-box styles, the browser rules kit.js checks with).
 * - aw_form_html shows the Fluent Forms form in the place of the site's own one (kept for Fluent Forms off).
 * - Emails: none since 21 Oct 2026 (owner): nothing is sent when a Fluent Forms form is submitted (aw_ff_mail_off).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// No email from a Fluent Forms submission, to anyone (owner, 21 Oct 2026): Fluent Forms' own email notifications are
// switched off, and while a submission is handled every wp_mail is dropped (the contact@ alerts for messages,
// volunteers and claims, the newsletter confirmation, any opt-in email). Entries are still saved where they always are.
// One exception: the "Remove my data" confirmation link to the person asking (privacy-requests.php).
// On again: wp option update aw_ff_send_email 1
function aw_ff_mail_off() {
	return '1' !== (string) get_option( 'aw_ff_send_email', '0' );
}
add_filter(
	'fluentform/global_notification_active_types',
	function ( $keys ) {
		if ( aw_ff_mail_off() ) {
			unset( $keys['notifications'] );
		}
		return $keys;
	},
	999
);
add_action(
	'fluentform/before_insert_submission',
	function () {
		if ( aw_ff_mail_off() ) {
			add_filter( 'pre_wp_mail', '__return_true', 999 ); // "handled": nothing is sent
		}
	},
	0
);

const AW_FF_OPTION = 'aw_ff_forms'; // key => Fluent Forms form ID
const AW_FF_FORMS  = array(
	'contact'     => array( 'title' => 'Contact', 'thanks' => 'contact', 'limit' => 5 ),
	'volunteer'   => array( 'title' => 'Volunteer sign-up', 'thanks' => 'volunteer', 'limit' => 5 ),
	'claim'       => array( 'title' => 'Claim this listing', 'thanks' => 'claim', 'limit' => 5 ),
	'report'      => array( 'title' => 'Report a mistake', 'thanks' => 'report', 'limit' => 10 ),
	'remove_data' => array( 'title' => 'Remove my data', 'thanks' => 'remove-data', 'limit' => 3 ),
);
/** Consent version for the newsletter box on Contact and Claim (owner, 16 Oct 2026: ticked by default). */
const AW_FF_NL_CONSENT = 'v2-optin-preticked-1';
/** What the custom fields post (Fluent Forms keeps only fields it knows, plus these). */
const AW_FF_EXTRA = array( 'aw_t', 'aw_source', 'kits_hp', 'altcha', 'pincode', 'state', 'district', 'logo_token', 'proof_token' );

/** The Fluent Forms form ID for a form key, or 0 (Fluent Forms off, or the form not set up: the site's own form shows). */
function aw_ff_id( $key ) {
	if ( ! defined( 'FLUENTFORM' ) ) {
		return 0;
	}
	return (int) ( ( (array) get_option( AW_FF_OPTION, array() ) )[ $key ] ?? 0 );
}

/** The form key of a Fluent Forms form, or '' when it is not one of these five. */
function aw_ff_key( $form ) {
	$id = (int) ( is_object( $form ) ? $form->id : $form );
	foreach ( (array) get_option( AW_FF_OPTION, array() ) as $k => $fid ) {
		if ( $id && (int) $fid === $id && isset( AW_FF_FORMS[ $k ] ) ) {
			return $k;
		}
	}
	return '';
}

/** What a form is shown for (listing, item, source), while it renders. */
function aw_ff_ctx( $k = null ) {
	$c = (array) ( $GLOBALS['aw_ff_ctx'] ?? array() );
	return null === $k ? $c : ( $c[ $k ] ?? null );
}

/* ---------- Showing the form ---------- */

/** The Fluent Forms form in the place of the site's own one (same wrapper classes, so the same look and analytics). */
add_filter(
	'aw_form_html',
	function ( $html, $key, $ctx ) {
		$id = aw_ff_id( $key );
		if ( ! $id ) {
			return $html;
		}
		$GLOBALS['aw_ff_ctx'] = (array) $ctx + array( 'key' => $key );
		$form                 = do_shortcode( '[fluentform id="' . $id . '"]' );
		$GLOBALS['aw_ff_ctx'] = array();
		$attrs                = '';
		if ( 'contact' === $key ) { // the message placeholder follows the reason (aw.js); #write is the contact form's anchor
			$attrs = ' id="write" data-placeholders="' . esc_attr( wp_json_encode( AW_CONTACT_PLACEHOLDER ) ) . '"';
		}
		$listing = $ctx['listing'] ?? null;
		if ( $listing ) {
			$attrs .= ' data-key="' . esc_attr( (string) $listing->ID ) . '"';
		}
		return '<div class="kit-form aw-form-ff aw-' . esc_attr( str_replace( '_', '-', $key ) ) . '-form" data-kit-form="' . esc_attr( $key ) . '"' . $attrs . '>' . $form . '</div>';
	},
	10,
	3
);

// No browser bubbles: Fluent Forms and the site's messages show the errors.
add_filter( 'fluentform/html_attributes', fn( $a, $form ) => aw_ff_key( $form ) ? $a + array( 'novalidate' => 'novalidate' ) : $a, 10, 2 );

/** The page path a submission comes from (S5.8): the listing's page for claims and reports, else the page. */
function aw_ff_source() {
	$listing = aw_ff_ctx( 'listing' );
	if ( $listing ) {
		return trim( wp_make_link_relative( (string) get_permalink( $listing ) ), '/' );
	}
	$src = (string) aw_ff_ctx( 'source' );
	return '' !== $src ? $src : kits_form_source();
}

/** A Fluent Forms-shaped field group around our own control (so its error messages land in the same place). */
function aw_ff_group( $id, $label, $control, $o = array() ) {
	$opt  = empty( $o['required'] ) ? ' <span class="kit-optional">(optional)</span>' : '';
	$hint = empty( $o['hint'] ) ? '' : '<div class="ff-el-help-message ff_ahm">' . esc_html( $o['hint'] ) . '</div>';
	$icon = empty( $o['icon'] ) ? '' : '<span class="kit-ico kit-ico--' . esc_attr( $o['icon'] ) . ' kit-field-icon" aria-hidden="true"></span>';
	return '<div class="ff-el-group kit-field ' . esc_attr( $o['class'] ?? '' ) . '">'
		. ( '' !== $label ? '<div class="ff-el-input--label"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . $opt . '</label></div>' : '' )
		. '<div class="ff-el-input--content"' . ( empty( $o['data_name'] ) ? '' : ' data-name="' . esc_attr( $o['data_name'] ) . '"' ) . '>' . $hint
		. ( $icon ? '<div class="kit-field-control">' . $icon . $control . '</div>' : $control ) . '</div></div>';
}

/* ---------- The custom fields Fluent Forms (free) has no equivalent for ---------- */

// Three, used in the forms' JSON like any field: the spam check (ALTCHA: no third party, no cookies), PIN code → State
// → District from the official LGD list (District follows State), and the private upload (claim logo and proof).
add_action(
	'init',
	function () {
		if ( ! class_exists( '\FluentForm\App\Services\FormBuilder\BaseFieldManager' ) ) {
			return;
		}
		$fields = array(
			'aw_places' => array( 'PIN code, State and District', 'aw_ff_render_places' ),
			'aw_check'  => array( 'Spam check (ALTCHA)', 'aw_ff_render_check' ),
			'aw_logo'   => array( 'Private upload (logo, proof)', 'aw_ff_render_logo' ),
		);
		foreach ( $fields as $key => $f ) {
			new class( $key, $f[0], $f[1] ) extends \FluentForm\App\Services\FormBuilder\BaseFieldManager {
				private $cb;
				public function __construct( $key, $title, $cb ) {
					$this->cb = $cb;
					parent::__construct( $key, $title, array( 'animal writes' ), 'advanced' );
				}
				public function pushFormInputType( $types ) {
					return $types; // posts its own inputs (see AW_FF_EXTRA); not an entry column of its own
				}
				public function getComponent() {
					return array(
						'index'          => 90,
						'element'        => $this->key,
						'attributes'     => array( 'name' => $this->key ),
						'settings'       => array( 'label' => $this->title, 'container_class' => '', 'html_codes' => '<p>' . esc_html( $this->title ) . ' (Animal Writes)</p>', 'conditional_logics' => array() ),
						'editor_options' => array( 'title' => $this->title, 'icon_class' => 'ff-edit-html', 'template' => 'customHTML' ),
					);
				}
				public function getGeneralEditorElements() {
					return array( 'label' );
				}
				public function getAdvancedEditorElements() {
					return array( 'container_class' );
				}
				public function render( $element, $form ) {
					echo call_user_func( $this->cb, $element, $form ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped where it is built
				}
			};
		}
	},
	5
);

/** PIN code → State and District (LGD), as in places.php: the PIN fills both (aw.js); never a typed state. Setting
 *  "required": "no" (the Newsletter form) makes the state optional. */
function aw_ff_render_places( $element, $form ) {
	$need = 'no' !== ( $element['settings']['required'] ?? 'yes' );
	$f    = 'ff' . (int) $form->id;
	$dict = kits_field_dict();
	$pin  = $dict['pincode'];
	$pid  = $f . '-pincode';
	$a    = '';
	foreach ( array_merge( $pin['attrs'], array( 'placeholder' => $pin['placeholder'] ) ) as $k => $v ) {
		$a .= ' ' . esc_attr( $k ) . '="' . esc_attr( (string) $v ) . '"';
	}
	$html  = aw_ff_group( $pid, 'PIN code', '<input type="text" id="' . esc_attr( $pid ) . '" name="pincode" class="ff-el-form-control" aria-describedby="' . esc_attr( $pid ) . '-error"' . $a . '><span class="kit-field-message kit-field-error" id="' . esc_attr( $pid ) . '-error" aria-live="polite"></span>', array( 'icon' => $pin['icon'], 'class' => 'aw-form-ff-half' ) );
	$sid   = $f . '-state';
	$did   = $f . '-district';
	$opts  = '<option value="">Choose a state</option>';
	foreach ( aw_place_states() as $st ) {
		$opts .= '<option value="' . esc_attr( $st->slug ) . '">' . esc_html( $st->name ) . '</option>';
	}
	$html .= aw_ff_group( $sid, 'State', '<select id="' . esc_attr( $sid ) . '" name="state" class="ff-el-form-control"' . ( $need ? ' required data-msg-required="Choose a state"' : '' ) . ' autocomplete="address-level1" data-district-select="' . esc_attr( $did ) . '">' . $opts . '</select>', array( 'required' => $need, 'class' => 'aw-form-ff-half' ) );
	$opts  = '<option value="" data-none="Choose a state first" data-all="Choose a district">Choose a state first</option>';
	foreach ( aw_place_states() as $st ) {
		$opts .= '<optgroup label="' . esc_attr( $st->name ) . '" data-state="' . esc_attr( $st->slug ) . '" hidden disabled>';
		foreach ( aw_place_districts( $st->code ) as $d ) {
			$opts .= '<option value="' . esc_attr( $d->slug ) . '">' . esc_html( $d->name ) . '</option>';
		}
		$opts .= '</optgroup>';
	}
	$html .= aw_ff_group( $did, 'District', '<select id="' . esc_attr( $did ) . '" name="district" class="ff-el-form-control" autocomplete="address-level2" disabled>' . $opts . '</select>', array( 'class' => 'aw-form-ff-half' ) );
	return $html;
}

/** The spam check: ALTCHA (no third party, no cookies), plus the site's timing, honeypot and source fields. */
function aw_ff_render_check( $element, $form ) {
	$widget = str_replace( '<div class="kit-captcha"', '<div class="kit-captcha" data-placed', kits_altcha_widget() ); // stays here, above Send
	return '<div class="ff-el-group aw-form-ff-check"><div class="ff-el-input--content" data-name="altcha">'
		. '<input type="hidden" name="aw_t" value="' . esc_attr( (string) time() ) . '">'
		. '<input type="hidden" name="aw_source" value="' . esc_attr( aw_ff_source() ) . '">'
		. '<p class="kit-hp" aria-hidden="true"><label>Leave this empty <input type="text" name="kits_hp" tabindex="-1" autocomplete="off"></label></p>'
		. $widget . '</div></div>';
}

/** A private upload: picked → checked and sent at once to the private folder (aw.js), the form then carries its token.
 *  The field's name (logo_token, proof_token), label and help come from the form. */
function aw_ff_render_logo( $element, $form ) {
	$name = in_array( $element['attributes']['name'] ?? '', array( 'logo_token', 'proof_token' ), true ) ? $element['attributes']['name'] : 'logo_token';
	$id   = 'ff' . (int) $form->id . '-' . $name;
	$d    = kits_field_dict()['logo'];
	$ctl  = '<input type="file" id="' . esc_attr( $id ) . '" accept="' . esc_attr( $d['attrs']['accept'] ) . '" data-max-bytes="' . (int) $d['attrs']['data-max-bytes'] . '" data-msg-file="' . esc_attr( $d['attrs']['data-msg-file'] ) . '" data-aw-upload="' . esc_url( admin_url( 'admin-ajax.php?action=aw_ff_upload' ) ) . '" aria-describedby="' . esc_attr( $id ) . '-error">'
		. '<input type="hidden" name="' . esc_attr( $name ) . '" value="">'
		. '<span class="kit-field-message kit-field-error" id="' . esc_attr( $id ) . '-error" aria-live="polite"></span>';
	$label = wp_strip_all_tags( (string) ( $element['settings']['label'] ?? 'Logo' ) );
	$hint  = (string) ( $element['settings']['help_message'] ?? 'PNG, JPG or WebP, up to 2 MB.' );
	return aw_ff_group( $id, $label, $ctl, array( 'hint' => $hint, 'data_name' => $name ) );
}

/* ---------- Fluent Forms fields: the site's look ---------- */

/** Icons inside fields, as the site's own forms have them. */
const AW_FF_ICONS = array( 'name' => 'name', 'email' => 'mail', 'phone' => 'phone', 'whatsapp' => 'brand-whatsapp', 'org' => 'building', 'org_name' => 'building', 'aw_phone' => 'phone', 'aw_whatsapp' => 'brand-whatsapp', 'aw_email' => 'mail', 'aw_website' => 'world' );

foreach ( array( 'input_text', 'input_email', 'input_url', 'textarea', 'select', 'input_checkbox', 'input_radio' ) as $aw_ff_el ) {
	// The step check (kit.js) uses the browser's rules with the site's words.
	add_filter(
		'fluentform/rendering_field_data_' . $aw_ff_el,
		function ( $data, $form ) {
			if ( ! aw_ff_key( $form ) ) {
				return $data;
			}
			$rules = (array) ( $data['settings']['validation_rules'] ?? array() );
			if ( in_array( $data['element'] ?? '', array( 'input_text', 'input_email', 'input_url', 'textarea', 'select' ), true ) && empty( $data['settings']['conditional_logics']['status'] ) ) {
				if ( ! empty( $rules['required']['value'] ) ) {
					$data['attributes']['required']          = 'required';
					$data['attributes']['data-msg-required'] = (string) $rules['required']['message'];
				}
				if ( ! empty( $rules['email']['value'] ) ) {
					$data['attributes']['data-msg-email'] = (string) $rules['email']['message'];
				}
			}
			return $data;
		},
		10,
		2
	);
	add_filter(
		'fluentform/rendering_field_html_' . $aw_ff_el,
		function ( $html, $data, $form ) {
			if ( ! aw_ff_key( $form ) ) {
				return $html;
			}
			if ( ! is_array( $data ) ) { // Fluent Forms passes the markup in place of the field for tick boxes
				$data = array( 'element' => false !== strpos( $html, 'ff-el-form-check' ) ? 'input_checkbox' : '', 'attributes' => array( 'name' => preg_match( '/name=[\'"]([a-z_]+)/', $html, $m ) ? $m[1] : '' ) );
			}
			$name = (string) ( $data['attributes']['name'] ?? '' );
			$el   = $data['element'] ?? '';
			if ( in_array( $el, array( 'input_checkbox', 'input_radio' ), true ) || false !== strpos( $html, 'ff-el-form-check' ) ) { // the site's tick boxes (and the volunteer cards)
				$cls  = 'roles' === rtrim( $name, '[]' ) ? ' kit-checkbox aw-vol-role' : ' kit-checkbox';
				$html = str_replace( "class='ff-el-form-check-label", "class='ff-el-form-check-label" . $cls, $html );
			}
			$icon = AW_FF_ICONS[ $name ] ?? '';
			if ( $icon && in_array( $el, array( 'input_text', 'input_email' ), true ) ) {
				$html = preg_replace( '/(<input\b[^>]*>)/', '<div class="kit-field-control"><span class="kit-ico kit-ico--' . $icon . ' kit-field-icon" aria-hidden="true"></span>$1</div>', $html, 1 );
			}
			return $html;
		},
		10,
		3
	);
}

/* ---------- Submitting ---------- */

// The custom fields' inputs are kept with the entry.
add_filter( 'fluentform/white_listed_fields', fn( $f, $form_id ) => aw_ff_key( $form_id ) ? array_merge( $f, AW_FF_EXTRA ) : $f, 10, 2 );

/** The listing a claim is about, when it is published. */
function aw_ff_listing( $data ) {
	$listing = get_post( (int) ( $data['listing'] ?? 0 ) );
	return $listing && 'aw_listing' === $listing->post_type && 'publish' === $listing->post_status ? $listing : null;
}

/** Was a Fluent Forms tick box ticked? Fluent Forms saves nothing for an unticked box: empty is 0. */
function aw_ff_ticked( $data, $name ) {
	return (bool) array_filter( (array) ( $data[ $name ] ?? array() ), fn( $v ) => '' !== (string) $v && '0' !== (string) $v );
}

/** An upload for this form (its token), as a file in the private folder, or ''. */
function aw_ff_logo_file( $token ) {
	$token = (string) $token;
	return preg_match( '/^[a-f0-9]{32}$/', $token ) && is_file( aw_private_dir() . '/tmp-' . $token . '.png' ) ? 'tmp-' . $token . '.png' : '';
}

/** An upload moved from its temporary name to its own, or ''. */
function aw_ff_keep_upload( $token ) {
	$file = aw_ff_logo_file( $token );
	if ( ! $file ) {
		return '';
	}
	$final = wp_generate_password( 20, false ) . '.png';
	return rename( aw_private_dir() . '/' . $file, aw_private_dir() . '/' . $final ) ? $final : '';
}

/** A submission's reference: the form's prefix and Fluent Forms' serial number for the entry (AW-12, AW-C-3 …). */
function aw_ff_ref( $key, $entry_id ) {
	$prefix = array( 'contact' => 'AW-', 'volunteer' => 'AW-V-', 'claim' => 'AW-C-', 'report' => 'AW-R-', 'remove_data' => 'AW-D-' );
	$serial = (int) \FluentForm\App\Models\Submission::where( 'id', (int) $entry_id )->value( 'serial_number' );
	return $serial ? ( $prefix[ $key ] ?? 'AW-' ) . $serial : '';
}

/**
 * The custom fields' own checks: a real State and District from the LGD picker (the Volunteer form needs a state) and
 * an upload that arrived. Everything else (required, email, web address) is Fluent Forms' own validation.
 */
function aw_ff_errors( $key, $data ) {
	$errors = array();
	if ( 'volunteer' === $key ) {
		$errors = array_intersect_key( aw_volunteer_errors( aw_volunteer_values( $data ) ), array_flip( array( 'state', 'district', 'pincode' ) ) );
	}
	foreach ( array( 'logo_token', 'proof_token' ) as $up ) {
		if ( '' !== (string) ( $data[ $up ] ?? '' ) && ! aw_ff_logo_file( $data[ $up ] ) ) {
			$errors[ $up ] = 'Use a PNG, JPG or WebP under 2 MB.';
		}
	}
	return $errors;
}

/** Rate-limit key: the same one as the site's own form, so both count together. */
function aw_ff_rl( $key ) {
	return 'aw_rl_aw_' . $key . '_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
}

add_filter(
	'fluentform/validation_errors',
	function ( $errors, $data, $form, $fields ) {
		$key = aw_ff_key( $form );
		if ( ! $key ) {
			return $errors;
		}
		// Bots (honeypot filled, or sent within 3 seconds) see a success; nothing is saved.
		if ( ! empty( $data['kits_hp'] ) || ( time() - (int) ( $data['aw_t'] ?? 0 ) ) < 3 ) {
			wp_send_json_success( array( 'insert_id' => 0, 'result' => array( 'redirectUrl' => kits_thanks_url( AW_FF_FORMS[ $key ]['thanks'] ), 'message' => '' ) ) );
		}
		foreach ( aw_ff_errors( $key, $data ) as $field => $msg ) {
			if ( empty( $errors[ $field ] ) ) {
				$errors[ $field ] = array( 'aw' => $msg );
			}
		}
		if ( $errors ) {
			return $errors; // the ALTCHA answer is checked (and used up) only when everything else is right
		}
		if ( (int) get_transient( aw_ff_rl( $key ) ) >= AW_FF_FORMS[ $key ]['limit'] ) {
			return array( 'aw_form' => array( 'aw' => 'Too many tries. Wait a minute, then try again.' ) );
		}
		if ( ! kits_altcha_verify( (string) ( $data['altcha'] ?? '' ) ) ) {
			return array( 'altcha' => array( 'aw' => 'Couldn\'t verify you\'re not a bot. Try again.' ) );
		}
		return $errors;
	},
	20,
	4
);

// The private uploads (claim logo and proof): on submit the file moves from its temporary name to its own, and the entry
// keeps a link only an administrator can open (aw_ff_file), in place of the upload's token.
add_filter(
	'fluentform/insert_response_data',
	function ( $data, $form_id ) {
		if ( ! aw_ff_key( $form_id ) ) {
			return $data;
		}
		foreach ( array( 'logo_token', 'proof_token' ) as $up ) {
			$file = '' !== (string) ( $data[ $up ] ?? '' ) ? aw_ff_keep_upload( $data[ $up ] ) : '';
			if ( $file ) {
				$data[ $up ] = add_query_arg( array( 'action' => 'aw_ff_file', 'f' => $file ), admin_url( 'admin-post.php' ) );
			}
		}
		return $data;
	},
	10,
	2
);
add_action(
	'admin_post_aw_ff_file',
	function () {
		$file = basename( sanitize_file_name( wp_unslash( $_GET['f'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification -- administrators only, read only
		$path = aw_private_dir() . '/' . $file;
		if ( ! current_user_can( 'edit_aw_volunteers' ) || ! preg_match( '/^[A-Za-z0-9]{20}\.png$/', $file ) || ! is_file( $path ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		nocache_headers();
		header( 'Content-Type: image/png' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}
);

/*
 * After each form's FluentCRM link (it runs at priority 10): what a link cannot do. A link only makes "subscribed"
 * contacts, only when its condition holds, and cannot add a note, so: every Contact sender becomes a contact
 * (transactional when new, tag contact-form) with the message as a note; every claimant a contact (transactional when
 * new, tag claimant); and every Contact, Claim and Volunteer contact gets the newsletter box as 1/0. Report a mistake
 * and Remove my data never reach FluentCRM: a report's email is only for replying about it, and a removal request asks
 * us to hold less. Also counts the submission for the per-connection limit.
 */
add_action(
	'fluentform/submission_inserted',
	function ( $entry_id, $data, $form ) {
		$key = aw_ff_key( $form );
		if ( ! $key ) {
			return;
		}
		set_transient( aw_ff_rl( $key ), (int) get_transient( aw_ff_rl( $key ) ) + 1, HOUR_IN_SECONDS );
		if ( ! in_array( $key, array( 'contact', 'claim', 'volunteer' ), true ) || ! aw_crm_on() ) {
			return;
		}
		$email = strtolower( trim( (string) ( $data['email'] ?? '' ) ) );
		$name  = sanitize_text_field( (string) ( $data['name'] ?? '' ) );
		$tags  = array( 'contact' => array( 'contact-form' ), 'claim' => array( 'claimant' ), 'volunteer' => array() )[ $key ];
		$c     = 'volunteer' === $key ? FluentCrmApi( 'contacts' )->getContact( $email ) : aw_crm_contact_for( $email, $name, $tags, 'contact' === $key ? 'contact-form' : 'claim' );
		if ( ! $c ) {
			return;
		}
		aw_crm_nl_box( $c, aw_ff_ticked( $data, 'nl_optin' ), AW_FF_FORMS[ $key ]['title'], (string) ( $data['consent_version'] ?? '' ) );
		if ( 'volunteer' === $key ) { // the LGD picker's values (a link only maps Fluent Forms' own fields): names, not slugs
			$st = ! empty( $data['state'] ) ? aw_place_by_slug( sanitize_title( (string) $data['state'] ) ) : null;
			$di = ! empty( $data['district'] ) ? aw_place_by_slug( sanitize_title( (string) $data['district'] ) ) : null;
			$c->syncCustomFieldValues( array_filter( array( 'vol_state' => $st ? $st->name : '', 'vol_district' => $di ? $di->name : '', 'vol_pincode' => preg_replace( '/\D/', '', (string) ( $data['pincode'] ?? '' ) ) ) ), false );
		}
		if ( 'contact' === $key ) {
			aw_crm_entry_note( $c, $entry_id, $data, (int) $form->id, aw_ff_ref( $key, $entry_id ) );
		}
	},
	20,
	3
);

/** The upload (claim logo and proof): checked like the site's own form, kept in the private folder until sent. */
function aw_ff_upload() {
	nocache_headers();
	$rl = 'aw_rl_aw_ff_upload_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( (int) get_transient( $rl ) >= 10 ) {
		wp_send_json_error( 'Too many tries. Wait a minute, then try again.' );
	}
	$logo = $_FILES['logo'] ?? null; // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput -- checked in aw_claim_logo_ok()
	if ( ! aw_claim_logo_ok( $logo ) ) {
		wp_send_json_error( 'Use a PNG, JPG or WebP under 2 MB.' );
	}
	foreach ( (array) glob( aw_private_dir() . '/tmp-*.png' ) as $old ) { // uploads never sent with a claim go after a day
		if ( $old && filemtime( $old ) < time() - DAY_IN_SECONDS ) {
			wp_delete_file( $old );
		}
	}
	$token = bin2hex( random_bytes( 16 ) );
	if ( ! aw_claim_logo_store( $logo['tmp_name'], 'tmp-' . $token . '.png' ) ) {
		wp_send_json_error( 'Use a PNG, JPG or WebP under 2 MB.' );
	}
	set_transient( $rl, (int) get_transient( $rl ) + 1, HOUR_IN_SECONDS );
	wp_send_json_success( array( 'token' => $token ) );
}
add_action( 'wp_ajax_nopriv_aw_ff_upload', 'aw_ff_upload' );
add_action( 'wp_ajax_aw_ff_upload', 'aw_ff_upload' );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	// wp aw ff-form <key> <id>: show the Fluent Forms form <id> in the place of the site's own <key> form. The forms
	// themselves are built in Fluent Forms and versioned in /forms/*.json (tools/forms/forms.php).
	WP_CLI::add_command(
		'aw ff-form',
		function ( $args ) {
			$key = (string) ( $args[0] ?? '' );
			$id  = (int) ( $args[1] ?? 0 );
			if ( ! isset( AW_FF_FORMS[ $key ] ) || ! $id ) {
				WP_CLI::error( 'Use: wp aw ff-form <' . implode( '|', array_keys( AW_FF_FORMS ) ) . '> <form ID>' );
			}
			$ids         = (array) get_option( AW_FF_OPTION, array() );
			$ids[ $key ] = $id;
			update_option( AW_FF_OPTION, $ids, false );
			WP_CLI::success( "{$key}: form {$id}" );
		}
	);
	// wp aw ff-off <key>: back to the site's own form (the Fluent Forms form and its entries stay).
	WP_CLI::add_command(
		'aw ff-off',
		function ( $args ) {
			$ids = (array) get_option( AW_FF_OPTION, array() );
			unset( $ids[ (string) ( $args[0] ?? '' ) ] );
			update_option( AW_FF_OPTION, $ids, false );
			WP_CLI::success( 'The site\'s own form shows again.' );
		}
	);
}

// Pages whose own content shows a form (contact, volunteer, claim, report): Fluent Forms' stylesheets keep blocking there,
// so the form near the top never shows unstyled (kits-core performance.php).
add_filter(
	'kits_page_has_form',
	fn( $has ) => $has || ( is_singular() && preg_match( '/\[aw_(contact_form|claim_page|report_page)|wp:animal-writes\/volunteer/', (string) get_post_field( 'post_content', get_queried_object_id() ) ) )
);
