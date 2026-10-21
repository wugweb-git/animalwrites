<?php
/**
 * The site's forms in Fluent Forms (owner, 21 Oct 2026: built in Fluent Forms and FluentCRM, not generated in code).
 * Contact #4, Volunteer sign-up #5, Claim this listing #6, Report a mistake #7, Remove my data #8 (and the Newsletter
 * form #3, newsletter-fluentforms.php). Each form's fields, conditions, hidden fields, confirmation and FluentCRM link
 * are Fluent Forms settings, versioned in /forms/<id>-<name>.json and imported with tools/forms/forms.php.
 *
 * What this file still does, because the plugins cannot:
 * - Three custom fields: the spam check (ALTCHA), PIN code → State → District (LGD) and the private upload (logo, proof).
 * - Checks Fluent Forms cannot make (an Indian mobile, a real State and District, the claimed listing, an upload, a page
 *   on this site), with the spam check, the per-connection limit and the honeypot.
 * - Country and city from the IP, found on this server (DB-IP Lite), into the hidden fields v_country and v_city.
 * - One small hook per form: the entry also goes where the rest of the site reads it (aw_*_save()).
 * - The site's look on Fluent Forms fields (icons, tick-box styles, the browser rules kit.js checks steps with).
 * - aw_form_html shows the Fluent Forms form in the place of the site's own one (which stays, for Fluent Forms off).
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

/** What a form is shown for (listing, item, source, the volunteer row of an update link), while it renders. */
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
			$attrs = ' id="write" data-placeholders="' . esc_attr( wp_json_encode( AW_CONTACT_PLACEHOLDER ) ) . '" data-go="' . esc_attr( wp_json_encode( array_map( 'home_url', AW_CONTACT_ELSEWHERE ) ) ) . '"';
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
			return aw_ff_update_values( $data, $form );
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
add_filter( 'fluentform/white_listed_fields', fn( $f, $form_id ) => aw_ff_key( $form_id ) || (int) $form_id === (int) get_option( 'aw_nl_ff_form' ) ? array_merge( $f, AW_FF_EXTRA ) : $f, 10, 2 );

/** The listing a claim is about, when it is published. */
function aw_ff_listing( $data ) {
	$listing = get_post( (int) ( $data['listing'] ?? 0 ) );
	return $listing && 'aw_listing' === $listing->post_type && 'publish' === $listing->post_status ? $listing : null;
}

/** Was a Fluent Forms tick ticked? A Yes/No tick is "1" or "0"; a tick box is a list. */
function aw_ff_ticked( $data, $name ) {
	return (bool) array_filter( (array) ( $data[ $name ] ?? array() ), fn( $v ) => '' !== (string) $v && '0' !== (string) $v );
}

/** One tick's wording version from the form's hidden consent_version ("nl_optin=v3-…;wa_optin=v1-…"). */
function aw_ff_consent( $data, $tick, $default = AW_FF_NL_CONSENT ) {
	return preg_match( '/(?:^|;)' . preg_quote( $tick, '/' ) . '=([a-z0-9-]{1,40})/i', (string) ( $data['consent_version'] ?? '' ), $m ) ? $m[1] : $default;
}

/** A Yes/No tick as 1 or 0. */
function aw_ff_tick( $data, $name ) {
	return aw_ff_ticked( $data, $name ) ? 1 : 0;
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

/** A WhatsApp number as given (the volunteer form's "Same as my phone" takes the phone). */
function aw_ff_whatsapp( $data ) {
	$how = (string) ( $data['wa_number'] ?? 'other' );
	return 'none' === $how ? '' : trim( sanitize_text_field( (string) ( 'same' === $how ? ( $data['phone'] ?? '' ) : ( $data['whatsapp'] ?? '' ) ) ) );
}

/** A submission's reference: the form's prefix and Fluent Forms' serial number for the entry (AW-12, AW-C-3 …). */
function aw_ff_ref( $key, $entry_id ) {
	$prefix = array( 'contact' => 'AW-', 'volunteer' => 'AW-V-', 'claim' => 'AW-C-', 'report' => 'AW-R-', 'remove_data' => 'AW-D-' );
	$serial = (int) \FluentForm\App\Models\Submission::where( 'id', (int) $entry_id )->value( 'serial_number' );
	return $serial ? ( $prefix[ $key ] ?? 'AW-' ) . $serial : '';
}

/**
 * What Fluent Forms cannot check itself: an Indian mobile number, a real State and District, the listing a claim is
 * about, an upload that arrived, and a page on this site. Required fields, emails and web addresses are Fluent Forms' own.
 */
function aw_ff_errors( $key, $data ) {
	$errors = array();
	$wa     = aw_ff_whatsapp( $data );
	if ( '' !== $wa && '' === kits_clean_phone( $wa ) ) {
		$errors['whatsapp'] = 'Enter a 10-digit mobile number, like 98765 43210';
	}
	if ( '' !== trim( (string) ( $data['phone'] ?? '' ) ) && '' === kits_clean_phone( (string) $data['phone'] ) ) {
		$errors['phone'] = 'Enter a 10-digit phone number, like 98765 43210';
	}
	switch ( $key ) {
		case 'volunteer':
			$v      = aw_volunteer_values( $data );
			$errors = array_intersect_key( aw_volunteer_errors( $v ), array_flip( array( 'state', 'district', 'pincode' ) ) ) + $errors;
			break;
		case 'claim':
			if ( ! aw_ff_listing( $data ) ) {
				return array( 'aw_form' => 'This listing could not be found.' );
			}
			foreach ( array( 'logo_token', 'proof_token' ) as $up ) {
				if ( '' !== (string) ( $data[ $up ] ?? '' ) && ! aw_ff_logo_file( $data[ $up ] ) ) {
					$errors[ $up ] = 'Use a PNG, JPG or WebP under 2 MB.';
				}
			}
			$errors = array_intersect_key( aw_claim_errors( aw_claim_values( $data ), '' !== (string) ( $data['logo_token'] ?? '' ) ), array_flip( array( 'aw_phone', 'aw_whatsapp', 'aw_website', 'notes' ) ) ) + $errors;
			break;
		case 'report':
			$item = aw_report_item( (int) ( $data['item'] ?? 0 ) );
			$page = trim( (string) ( $data['page'] ?? '' ) );
			if ( ! $item && '' === $page ) {
				$errors['page'] = 'Add the link to the page';
			} elseif ( ! $item && wp_parse_url( $page, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
				$errors['page'] = 'Use the link of a page on ' . wp_parse_url( home_url(), PHP_URL_HOST );
			}
			break;
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

// Country and city from the IP, found on this server (DB-IP Lite; no IP leaves the server), into the hidden fields
// v_country and v_city of every site form and the Newsletter form; "same as my phone" fills the WhatsApp number.
add_filter(
	'fluentform/insert_response_data',
	function ( $data, $form_id ) {
		if ( ! aw_ff_key( $form_id ) && (int) $form_id !== (int) get_option( 'aw_nl_ff_form' ) ) {
			return $data;
		}
		$geo = aw_geoip( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- LiteSpeed puts the client address here
		if ( array_key_exists( 'v_country', $data ) ) {
			$data['v_country'] = $geo['country'];
			$data['v_city']    = $geo['city'];
		}
		if ( isset( $data['wa_number'] ) ) {
			$data['whatsapp'] = aw_ff_whatsapp( $data );
		}
		return $data;
	},
	10,
	2
);

/*
 * One small hook per form: the entry is also saved where the rest of the site reads it (Inbox → Messages, the
 * volunteers table that Near me and the listing's people box read, Listing claims, Reports, data requests and their
 * Dashboard notice), by the site's own save functions. Priority 5: a record that cannot be saved stops the submission.
 * FluentCRM gets the person from each form's own Fluent Forms → FluentCRM link (lists, tags, conditions in /forms/*.json);
 * what a link cannot do runs after it, at priority 20: the message's note on the contact, the volunteer's details,
 * and a "transactional" contact for a claimant who left the newsletter unticked (owner, 21 Oct 2026).
 */
add_action(
	'fluentform/submission_inserted',
	function ( $entry_id, $data, $form ) {
		$key = aw_ff_key( $form );
		if ( ! $key ) {
			return;
		}
		$GLOBALS['aw_ff_handling'] = true; // aw_newsletter_join(): FluentCRM is the form's own link
		$src = substr( sanitize_text_field( (string) ( $data['aw_source'] ?? '' ) ), 0, 120 );
		$ref = aw_ff_ref( $key, $entry_id );
		$res = false;
		switch ( $key ) {
			case 'contact':
				if ( isset( AW_CONTACT_ELSEWHERE[ sanitize_key( (string) ( $data['about'] ?? '' ) ) ] ) ) {
					$res = array( 'skip' => true ); // Report a mistake, Remove my data: their own forms; no message
					break;
				}
				$res = aw_contact_save( aw_contact_values( array( 'org' => (string) ( $data['org_name'] ?? '' ) ?: (string) ( $data['org'] ?? '' ) ) + $data ), $src, aw_ff_ticked( $data, 'nl_optin' ), aw_ff_consent( $data, 'nl_optin' ), array( 'ref' => $ref, 'entry' => $entry_id, 'fields' => aw_contact_reason_fields( $data ), 'whatsapp' => aw_ff_whatsapp( $data ), 'wa_optin' => aw_ff_tick( $data, 'wa_optin' ) ) );
				break;
			case 'volunteer':
				$res = aw_volunteer_save( aw_volunteer_values( $data ), $src, aw_volunteer_extra( $data ) + array( 'entry' => $entry_id ) );
				break;
			case 'claim':
				$res = aw_claim_save( aw_claim_values( $data ), aw_ff_listing( $data ), aw_ff_keep_upload( $data['logo_token'] ?? '' ), $src, aw_ff_ticked( $data, 'nl_optin' ), aw_ff_consent( $data, 'nl_optin' ), array( 'ref' => $ref, 'entry' => $entry_id, 'proof' => aw_ff_keep_upload( $data['proof_token'] ?? '' ), 'whatsapp' => aw_ff_whatsapp( $data ), 'wa_optin' => aw_ff_tick( $data, 'wa_optin' ) ) );
				break;
			case 'report':
				// Never FluentCRM: the email is optional and only for replying about this report.
				$res = aw_report_save( aw_report_values( $data ), aw_report_item( (int) ( $data['item'] ?? 0 ) ), $src, array( 'ref' => $ref, 'entry' => $entry_id ) );
				break;
			case 'remove_data':
				// Never FluentCRM: this person asks us to hold less, so no contact is added or updated.
				$res = aw_remove_data_save( aw_clean_email( sanitize_text_field( (string) ( $data['email'] ?? '' ) ) ), array( 'ref' => $ref, 'entry' => $entry_id, 'name' => sanitize_text_field( (string) ( $data['name'] ?? '' ) ), 'scope' => sanitize_key( (string) ( $data['scope'] ?? '' ) ), 'reason' => sanitize_textarea_field( (string) ( $data['reason'] ?? '' ) ) ) );
				break;
		}
		unset( $GLOBALS['aw_ff_handling'] );
		if ( ! $res ) {
			wp_send_json( array( 'errors' => array( 'aw_form' => array( aw_form_generic_error( 'aw_' . $key ) ) ) ), 422 );
		}
		set_transient( aw_ff_rl( $key ), (int) get_transient( aw_ff_rl( $key ) ) + 1, HOUR_IN_SECONDS );
		$GLOBALS['aw_ff_done'] = $res;
	},
	5,
	3
);
add_action(
	'fluentform/submission_inserted',
	function ( $entry_id, $data, $form ) {
		$key = aw_ff_key( $form );
		$res = (array) ( $GLOBALS['aw_ff_done'] ?? array() );
		if ( ! $key || ! aw_crm_on() || ! empty( $res['skip'] ) ) {
			return;
		}
		$email = strtolower( trim( (string) ( $data['email'] ?? '' ) ) );
		if ( 'contact' === $key && ! empty( $res['id'] ) ) {
			aw_crm_message_note( (int) $res['id'] ); // the contact (transactional when new), tag Contact form, the note
		} elseif ( 'volunteer' === $key ) {
			aw_crm_volunteer_sync( $email ); // the volunteer custom fields from the volunteers table
		} elseif ( 'claim' === $key && ! aw_ff_ticked( $data, 'nl_optin' ) ) {
			aw_crm_contact_for( $email, (string) ( $data['name'] ?? '' ), array(), 'claim' ); // a contact, not a subscriber
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

/* ---------- Country and city from an IP, on this server (DB-IP Lite; no IP leaves the server) ---------- */

/** The DB-IP Lite city file (private uploads folder, not served). */
function aw_geoip_file() {
	return aw_private_dir() . '/geoip/dbip-city-lite.mmdb';
}

/**
 * Country and city of an IP: Cloudflare's CF-IPCountry / CF-IPCity headers when present, otherwise the DB-IP Lite
 * file read here. Private or local addresses, or no file yet, give empty values.
 *
 * @return array{country:string, city:string}
 */
function aw_geoip( $ip ) {
	static $reader = null;
	$h = fn( $k ) => sanitize_text_field( wp_unslash( (string) ( $_SERVER[ $k ] ?? '' ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( '' !== $h( 'HTTP_CF_IPCOUNTRY' ) && 'XX' !== $h( 'HTTP_CF_IPCOUNTRY' ) ) {
		return array( 'country' => $h( 'HTTP_CF_IPCOUNTRY' ), 'city' => $h( 'HTTP_CF_IPCITY' ) );
	}
	$none = array( 'country' => '', 'city' => '' );
	if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) || ! is_readable( aw_geoip_file() ) ) {
		return $none;
	}
	try {
		if ( null === $reader ) {
			aw_geoip_load_reader();
			$reader = new \MaxMind\Db\Reader( aw_geoip_file() );
		}
		$d = (array) $reader->get( $ip );
		return array( 'country' => (string) ( $d['country']['names']['en'] ?? ( $d['country']['iso_code'] ?? '' ) ), 'city' => (string) ( $d['city']['names']['en'] ?? '' ) );
	} catch ( \Throwable $e ) {
		return $none;
	}
}

/** The vendored reader (DB-IP files are in the MaxMind DB format; vendor/maxmind-db, Apache 2.0). */
function aw_geoip_load_reader() {
	if ( ! class_exists( '\MaxMind\Db\Reader' ) ) {
		foreach ( array( 'Reader/InvalidDatabaseException', 'Reader/Util', 'Reader/Metadata', 'Reader/Decoder', 'Reader' ) as $f ) {
			require_once AW_CORE_DIR . 'vendor/maxmind-db/src/MaxMind/Db/' . $f . '.php';
		}
	}
}

/** Download this month's DB-IP Lite city file (last month's early in a month); the old file stays on any failure. */
function aw_geoip_update() {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	aw_geoip_load_reader();
	wp_mkdir_p( dirname( aw_geoip_file() ) );
	foreach ( array( gmdate( 'Y-m' ), gmdate( 'Y-m', strtotime( 'first day of last month' ) ) ) as $month ) {
		$gz = download_url( 'https://download.db-ip.com/free/dbip-city-lite-' . $month . '.mmdb.gz', 600 );
		if ( is_wp_error( $gz ) ) {
			continue;
		}
		$new = aw_geoip_file() . '.new';
		$in  = gzopen( $gz, 'rb' );
		$out = fopen( $new, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		while ( $in && $out && ! gzeof( $in ) ) {
			fwrite( $out, (string) gzread( $in, 1048576 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		$in && gzclose( $in );
		$out && fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		wp_delete_file( $gz );
		try {
			( new \MaxMind\Db\Reader( $new ) )->close(); // a broken file throws
			rename( $new, aw_geoip_file() );
			update_option( 'aw_geoip_updated', current_time( 'mysql' ) . ' ' . $month, false );
			return $month;
		} catch ( \Throwable $e ) {
			wp_delete_file( $new );
		}
	}
	return '';
}

// Monthly refresh (DB-IP publishes each month's file at its start).
add_filter( 'cron_schedules', fn( $s ) => $s + array( 'aw_monthly' => array( 'interval' => 30 * DAY_IN_SECONDS, 'display' => 'Monthly' ) ) );
add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'aw_geoip_monthly' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'aw_monthly', 'aw_geoip_monthly' );
		}
	}
);
add_action( 'aw_geoip_monthly', 'aw_geoip_update' );

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
	// wp aw geoip-update: this month's DB-IP Lite city file (also monthly by cron). wp aw geoip-test <ip>.
	WP_CLI::add_command( 'aw geoip-update', fn() => ( $m = aw_geoip_update() ) ? WP_CLI::success( 'DB-IP Lite ' . $m . ', ' . size_format( (int) filesize( aw_geoip_file() ) ) ) : WP_CLI::error( 'Download failed; the old file (if any) stays.' ) );
	WP_CLI::add_command( 'aw geoip-test', fn( $args ) => WP_CLI::log( wp_json_encode( aw_geoip( (string) ( $args[0] ?? '' ) ) ) ) );
}

// Pages whose own content shows a form (contact, volunteer, claim, report): Fluent Forms' stylesheets keep blocking there,
// so the form near the top never shows unstyled (kits-core performance.php).
add_filter(
	'kits_page_has_form',
	fn( $has ) => $has || ( is_singular() && preg_match( '/\[aw_(contact_form|claim_page|report_page|volunteer_update)|wp:animal-writes\/volunteer/', (string) get_post_field( 'post_content', get_queried_object_id() ) ) )
);
