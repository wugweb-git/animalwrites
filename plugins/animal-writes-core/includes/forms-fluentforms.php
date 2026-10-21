<?php
/**
 * The site's five forms in Fluent Forms (owner, 16 Oct 2026: "Hand-made boxes are not a long-term solution"):
 * Contact, Volunteer sign-up, Claim this listing, Report a mistake and Remove my data. Same fields, labels, help text,
 * rules and wording as the site's own forms; those stay installed and come back if Fluent Forms is off.
 *
 * - Each form is a Fluent Forms form (`wp aw ff-form <key>` makes it once and switches the page to it; `wp aw ff-off
 *   <key>` goes back to the site's own form). The pages, blocks and shortcodes are unchanged: aw_form_html swaps the form.
 * - On submit the entry is saved where the site's own form saves it (contact messages, the volunteers table, claims for
 *   review, reports, data removal requests) by the same code (aw_*_save()); the Fluent Forms entry is an extra copy.
 *   The same server rules run (aw_*_errors()), with the spam check (ALTCHA, honeypot, timing) and the per-connection limit.
 * - Emails: none since 21 Oct 2026 (owner): nothing is sent when a Fluent Forms form is submitted (aw_ff_mail_off).
 * - Fields Fluent Forms (free) does not have are its custom fields here: PIN code with State and District (LGD), the
 *   spam check, step breaks (claim), the logo upload (to the private folder) and the listing's services.
 * - After sending: the thank-you page with the form's own message, as before. Analytics: kit.js (data-kit-form names
 *   the form; claim_step from the steps).
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
const AW_FF_EXTRA = array( 'aw_t', 'aw_source', 'kits_hp', 'altcha', 'pincode', 'state', 'district', 'aw_services', 'logo_token' );

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

/** What a form is shown for (listing, source), while it renders. */
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

/* ---------- Custom fields (Fluent Forms free has no such fields) ---------- */

add_action(
	'init',
	function () {
		if ( ! class_exists( '\FluentForm\App\Services\FormBuilder\BaseFieldManager' ) ) {
			return;
		}
		$fields = array(
			'aw_places'   => array( 'PIN code, State and District', 'aw_ff_render_places' ),
			'aw_check'    => array( 'Spam check (ALTCHA)', 'aw_ff_render_check' ),
			'aw_step'     => array( 'Step', 'aw_ff_render_step' ),
			'aw_logo'     => array( 'Logo upload', 'aw_ff_render_logo' ),
			'aw_services' => array( 'Listing services', 'aw_ff_render_services' ),
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

/** PIN code → State and District (LGD), as in places.php: the PIN fills both (aw.js); never a typed state. */
function aw_ff_render_places( $element, $form ) {
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
	$html .= aw_ff_group( $sid, 'State', '<select id="' . esc_attr( $sid ) . '" name="state" class="ff-el-form-control" required data-msg-required="Choose a state" autocomplete="address-level1" data-district-select="' . esc_attr( $did ) . '">' . $opts . '</select>', array( 'required' => true, 'class' => 'aw-form-ff-half' ) );
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

/** The step elements of a form, in order. */
function aw_ff_steps( $form ) {
	return array_values( array_filter( (array) ( $form->fields['fields'] ?? array() ), fn( $f ) => 'aw_step' === ( $f['element'] ?? '' ) ) );
}

/** A step break (S5.7 pattern, as kits_form_steps()): the first opens the steps, each next one closes the step before. */
function aw_ff_render_step( $element, $form ) {
	$steps = aw_ff_steps( $form );
	$n     = count( $steps );
	$i     = 0;
	foreach ( $steps as $k => $s ) {
		if ( ( $s['uniqElKey'] ?? '' ) === ( $element['uniqElKey'] ?? '' ) ) {
			$i = $k;
		}
	}
	$open = '<fieldset class="kit-step" data-step="' . ( $i + 1 ) . '"><legend><span class="kit-step-count">Step ' . ( $i + 1 ) . ' of ' . $n . '</span> ' . esc_html( wp_strip_all_tags( (string) ( $element['settings']['label'] ?? '' ) ) ) . '</legend>';
	if ( 0 === $i ) {
		return '<div class="kit-steps" data-steps="' . $n . '">' . $open;
	}
	return '<div class="kit-step-nav"><button type="button" class="kit-btn" data-step-next>Next</button>' . ( $i > 1 ? '<button type="button" class="kit-btn kit-btn--secondary" data-step-back>Back</button>' : '' ) . '</div></fieldset>' . $open;
}

// The last step holds Send (Fluent Forms' button) and Back.
add_action(
	'fluentform/render_item_submit_button',
	function ( $item, $form ) {
		if ( aw_ff_key( $form ) && aw_ff_steps( $form ) ) {
			echo '<div class="kit-step-nav">';
		}
	},
	5,
	2
);
add_action(
	'fluentform/render_item_submit_button',
	function ( $item, $form ) {
		$n = count( aw_ff_key( $form ) ? aw_ff_steps( $form ) : array() );
		if ( $n ) {
			echo ( $n > 1 ? '<button type="button" class="kit-btn kit-btn--secondary" data-step-back>Back</button>' : '' ) . '</div></fieldset></div>';
		}
	},
	15,
	2
);

/** The logo: picked → checked and sent at once to the private folder (aw.js), the form then carries its token. */
function aw_ff_render_logo( $element, $form ) {
	$id  = 'ff' . (int) $form->id . '-logo';
	$d   = kits_field_dict()['logo'];
	$ctl = '<input type="file" id="' . esc_attr( $id ) . '" accept="' . esc_attr( $d['attrs']['accept'] ) . '" data-max-bytes="' . (int) $d['attrs']['data-max-bytes'] . '" data-msg-file="' . esc_attr( $d['attrs']['data-msg-file'] ) . '" data-aw-upload="' . esc_url( admin_url( 'admin-ajax.php?action=aw_ff_upload' ) ) . '" aria-describedby="' . esc_attr( $id ) . '-error">'
		. '<input type="hidden" name="logo_token" value="">'
		. '<span class="kit-field-message kit-field-error" id="' . esc_attr( $id ) . '-error" aria-live="polite"></span>';
	return aw_ff_group( $id, 'Logo', $ctl, array( 'hint' => 'PNG, JPG or WebP, up to 2 MB.', 'data_name' => 'logo_token' ) );
}

/** Services as tick boxes from the fixed list; the listing's current services start ticked (claims.php). */
function aw_ff_render_services( $element, $form ) {
	$listing = aw_ff_ctx( 'listing' );
	$kept    = $listing ? wp_list_pluck( (array) aw_item_tags( $listing->ID, aw_service_slugs() ), 'slug' ) : array();
	$boxes   = '';
	foreach ( AW_SERVICES as $slug => $name ) {
		$boxes .= '<label class="kit-checkbox"><input type="checkbox" name="aw_services[]" value="' . esc_attr( $slug ) . '"' . checked( in_array( $slug, $kept, true ), true, false ) . '> ' . esc_html( $name ) . '</label>';
	}
	return '<fieldset class="kit-field kit-check-group aw-claim-services"><legend>Services</legend>' . $boxes . '</fieldset>';
}

/* ---------- Fluent Forms fields: the site's look and rules ---------- */

/** Icons inside fields, as the site's own forms have them. */
const AW_FF_ICONS = array( 'name' => 'name', 'email' => 'mail', 'phone' => 'phone', 'org' => 'building', 'aw_phone' => 'phone', 'aw_whatsapp' => 'brand-whatsapp', 'aw_email' => 'mail', 'aw_website' => 'world' );

foreach ( array( 'input_text', 'input_email', 'textarea', 'select', 'input_checkbox', 'input_hidden' ) as $aw_ff_el ) {
	add_filter(
		'fluentform/rendering_field_data_' . $aw_ff_el,
		function ( $data, $form ) {
			$key = aw_ff_key( $form );
			if ( ! $key ) {
				return $data;
			}
			$name  = (string) ( $data['attributes']['name'] ?? '' );
			$rules = (array) ( $data['settings']['validation_rules'] ?? array() );
			$el    = $data['element'] ?? '';
			// The step check (kit.js) uses the browser's rules with the site's words.
			if ( in_array( $el, array( 'input_text', 'input_email', 'textarea', 'select' ), true ) ) {
				if ( ! empty( $rules['required']['value'] ) ) {
					$data['attributes']['required']          = 'required';
					$data['attributes']['data-msg-required'] = (string) $rules['required']['message'];
				}
				if ( ! empty( $rules['email']['value'] ) ) {
					$data['attributes']['data-msg-email'] = (string) $rules['email']['message'];
				}
			}
			$listing = aw_ff_ctx( 'listing' );
			if ( 'input_hidden' === $el && 'listing' === $name && $listing ) {
				$data['attributes']['value'] = (string) $listing->ID;
			}
			// Claim: each field shows what the listing has now ("Not listed" when nothing).
			if ( 'claim' === $key && $listing && isset( AW_CLAIM_FIELDS[ $name ] ) ) {
				$cur                               = aw_claim_current( $listing->ID, $name );
				$data['attributes']['placeholder'] = '' !== $cur ? $cur : 'Not listed';
			}
			// Contact: /contact/?about= (or ?type=) chooses the reason; the message placeholder follows it.
			if ( 'contact' === $key && in_array( $name, array( 'about', 'message' ), true ) ) {
				$about = sanitize_key( wp_unslash( $_GET['about'] ?? ( $_GET['type'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
				$about = 'partner' === $about ? 'partnership' : $about; // older links
				$about = isset( AW_CONTACT_ABOUT[ $about ] ) ? $about : '';
				if ( 'about' === $name && $about ) {
					$data['attributes']['value'] = $about;
				}
				if ( 'message' === $name ) {
					$data['attributes']['placeholder'] = AW_CONTACT_PLACEHOLDER[ $about ] ?? '';
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
			$key = aw_ff_key( $form );
			if ( ! $key ) {
				return $html;
			}
			if ( ! is_array( $data ) ) { // Fluent Forms passes the markup in place of the field for tick boxes
				$data = array( 'element' => false !== strpos( $html, 'ff-el-form-check' ) ? 'input_checkbox' : '', 'attributes' => array( 'name' => preg_match( '/name=[\'"]([a-z_]+)/', $html, $m ) ? $m[1] : '' ) );
			}
			$name = (string) ( $data['attributes']['name'] ?? '' );
			$el   = $data['element'] ?? '';
			if ( 'claim' === $key && 'feature' === rtrim( $name, '[]' ) && aw_ff_ctx( 'listing' ) && get_post_meta( aw_ff_ctx( 'listing' )->ID, 'aw_featured', true ) ) {
				return ''; // already featured: no box
			}
			if ( 'input_checkbox' === $el ) { // the site's tick boxes (and the volunteer cards)
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

/** The listing a claim or report is about, when it is published. */
function aw_ff_listing( $data ) {
	$listing = get_post( (int) ( $data['listing'] ?? 0 ) );
	return $listing && 'aw_listing' === $listing->post_type && 'publish' === $listing->post_status ? $listing : null;
}

/** Was a Fluent Forms tick box ticked? */
function aw_ff_ticked( $data, $name ) {
	return (bool) array_filter( (array) ( $data[ $name ] ?? array() ) );
}

/** A logo uploaded for this form (its token), as a file in the private folder, or ''. */
function aw_ff_logo_file( $token ) {
	$token = (string) $token;
	return preg_match( '/^[a-f0-9]{32}$/', $token ) && is_file( aw_private_dir() . '/tmp-' . $token . '.png' ) ? 'tmp-' . $token . '.png' : '';
}

/** The site's server rules for a form: field => message. */
function aw_ff_errors( $key, $data ) {
	switch ( $key ) {
		case 'contact':
			return aw_contact_errors( aw_contact_values( $data ) );
		case 'volunteer':
			return aw_volunteer_errors( aw_volunteer_values( $data ) );
		case 'claim':
			if ( ! aw_ff_listing( $data ) ) {
				return array( 'aw_form' => 'This listing could not be found.' );
			}
			$token  = (string) ( $data['logo_token'] ?? '' );
			$errors = aw_claim_errors( aw_claim_values( $data ), '' !== $token );
			if ( '' !== $token && ! aw_ff_logo_file( $token ) ) {
				$errors['logo_token'] = 'Use a PNG, JPG or WebP under 2 MB.';
			}
			return $errors;
		case 'report':
			return aw_ff_listing( $data ) ? aw_report_errors( aw_report_values( $data ) ) : array( 'aw_form' => 'This listing could not be found.' );
		case 'remove_data':
			return aw_form_validate( array( 'email' => array( 'required', 'email' ) ), array( 'email' => aw_clean_email( sanitize_text_field( (string) ( $data['email'] ?? '' ) ) ) ), array( 'email' => 'Email' ) );
	}
	return array();
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

// Saved where the site's own form saves it, by the same code; emails as before. Runs before anything else on the entry.
add_action(
	'fluentform/submission_inserted',
	function ( $entry_id, $data, $form ) {
		$key = aw_ff_key( $form );
		if ( ! $key ) {
			return;
		}
		$src = substr( sanitize_text_field( (string) ( $data['aw_source'] ?? '' ) ), 0, 120 );
		$res = false;
		switch ( $key ) {
			case 'contact':
				$res = aw_contact_save( aw_contact_values( $data ), $src, aw_ff_ticked( $data, 'nl_optin' ), AW_FF_NL_CONSENT );
				break;
			case 'volunteer':
				$res = aw_volunteer_save( aw_volunteer_values( $data ), $src );
				break;
			case 'claim':
				$file = aw_ff_logo_file( $data['logo_token'] ?? '' );
				if ( $file ) { // from the upload's temporary name to its own
					$final = wp_generate_password( 20, false ) . '.png';
					$file  = rename( aw_private_dir() . '/' . $file, aw_private_dir() . '/' . $final ) ? $final : '';
				}
				$res = aw_claim_save( aw_claim_values( $data ), aw_ff_listing( $data ), $file, $src, aw_ff_ticked( $data, 'nl_optin' ), AW_FF_NL_CONSENT );
				break;
			case 'report':
				$res = aw_report_save( aw_report_values( $data ), aw_ff_listing( $data ), $src );
				break;
			case 'remove_data':
				$res = aw_remove_data_save( aw_clean_email( sanitize_text_field( (string) ( $data['email'] ?? '' ) ) ) );
				break;
		}
		if ( ! $res ) {
			wp_send_json( array( 'errors' => array( 'aw_form' => array( aw_form_generic_error( 'aw_' . $key ) ) ) ), 422 );
		}
		set_transient( aw_ff_rl( $key ), (int) get_transient( aw_ff_rl( $key ) ) + 1, HOUR_IN_SECONDS );
		$GLOBALS['aw_ff_done'] = $res;
	},
	5,
	3
);

// Afterwards: the thank-you page with the form's own message (as the site's own forms do).
add_filter(
	'fluentform/form_submission_confirmation',
	function ( $c, $data, $form ) {
		if ( ! aw_ff_key( $form ) || empty( $GLOBALS['aw_ff_done']['url'] ) ) {
			return $c;
		}
		return array_merge( (array) $c, array( 'redirectTo' => 'customUrl', 'customUrl' => $GLOBALS['aw_ff_done']['url'], 'messageToShow' => '', 'enable_query_string' => 'no' ) );
	},
	10,
	3
);
add_filter( 'fluentform/will_parse_url_value', fn( $parse, $form ) => aw_ff_key( $form ) ? false : $parse, 10, 2 );

/** The logo upload (claim): checked like the site's own form, kept in the private folder until the claim is sent. */
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

/* ---------- The forms (fields word for word from the site's own forms) ---------- */

function aw_ff_rule( $msg ) {
	return array( 'value' => true, 'message' => $msg, 'global_message' => $msg, 'global' => false );
}

/**
 * One Fluent Forms field.
 *
 * @param string $el    input_text|input_email|textarea|select|input_checkbox|input_hidden|custom_html|aw_*.
 * @param string $name  Field name (as the site's own form posts it).
 * @param string $label Label.
 * @param array  $o     required (message), hint, attrs, options (value => label), placeholder (select), value, class.
 */
function aw_ff_field( $el, $name, $label = '', $o = array() ) {
	$tpl   = array( 'input_text' => 'inputText', 'input_email' => 'inputText', 'textarea' => 'inputTextarea', 'select' => 'select', 'input_checkbox' => 'inputCheckable', 'input_hidden' => 'inputHidden', 'custom_html' => 'customHTML' );
	$title = array( 'input_text' => 'Simple Text', 'input_email' => 'Email Address', 'textarea' => 'Text Area', 'select' => 'Dropdown', 'input_checkbox' => 'Check Box', 'input_hidden' => 'Hidden Field', 'custom_html' => 'Custom HTML' );
	$f     = array( 'index' => 0, 'element' => $el, 'uniqElKey' => 'el_aw_' . $name );
	if ( 0 === strpos( $el, 'aw_' ) ) {
		return $f + array(
			'attributes'     => array( 'name' => $el ),
			'settings'       => array( 'label' => $label, 'container_class' => '', 'html_codes' => '<p>' . esc_html( $label ) . ' (Animal Writes)</p>', 'conditional_logics' => array() ),
			'editor_options' => array( 'title' => $label, 'icon_class' => 'ff-edit-html', 'template' => 'customHTML' ),
		);
	}
	if ( 'custom_html' === $el ) {
		return $f + array( 'attributes' => array(), 'settings' => array( 'html_codes' => $label, 'conditional_logics' => array(), 'container_class' => '' ), 'editor_options' => array( 'title' => 'Custom HTML', 'icon_class' => 'ff-edit-html', 'template' => 'customHTML' ) );
	}
	if ( 'input_hidden' === $el ) {
		return $f + array( 'attributes' => array( 'type' => 'hidden', 'name' => $name, 'value' => (string) ( $o['value'] ?? '' ) ), 'settings' => array( 'admin_field_label' => $label ), 'editor_options' => array( 'title' => 'Hidden Field', 'icon_class' => 'ff-edit-hidden-field', 'template' => 'inputHidden' ) );
	}
	$req   = isset( $o['required'] );
	$rules = array( 'required' => $req ? aw_ff_rule( $o['required'] ) : array( 'value' => false, 'message' => '', 'global_message' => '', 'global' => false ) );
	if ( 'input_email' === $el ) {
		$rules['email'] = aw_ff_rule( 'Enter a full email address, like you@example.com' );
	}
	$attrs = array( 'name' => $name, 'value' => $o['value'] ?? '', 'id' => '', 'class' => '' );
	if ( in_array( $el, array( 'input_text', 'input_email' ), true ) ) {
		$attrs['type'] = 'input_email' === $el ? 'email' : 'text';
	}
	if ( 'input_checkbox' === $el ) {
		$attrs = array( 'type' => 'checkbox', 'name' => $name, 'value' => (array) ( $o['value'] ?? array() ) );
	}
	$attrs    = array_merge( $attrs, (array) ( $o['attrs'] ?? array() ) );
	$opt      = ( $req || '' === $label || 'input_checkbox' === $el ) ? '' : ' <span class="kit-optional">(optional)</span>';
	$settings = array(
		'container_class'    => $o['class'] ?? ( 'input_checkbox' === $el ? 'kit-field' : 'kit-field' ),
		'label'              => $label . $opt,
		'label_placement'    => '',
		'admin_field_label'  => wp_strip_all_tags( $label ),
		'help_message'       => $o['hint'] ?? '',
		'validation_rules'   => $rules,
		'conditional_logics' => array(),
	);
	if ( isset( $o['options'] ) ) {
		$settings['advanced_options'] = array();
		foreach ( $o['options'] as $v => $l ) {
			$settings['advanced_options'][] = array( 'label' => $l, 'value' => (string) $v, 'calc_value' => '', 'image' => '' );
		}
		$settings += array( 'calc_value_status' => false, 'enable_image_input' => false, 'values_visible' => false, 'randomize_options' => 'no', 'enable_select_2' => 'no', 'enable_option_groups' => 'no', 'enable_other_option' => 'no', 'dynamic_default_value' => '' );
	}
	if ( 'select' === $el ) {
		$settings['placeholder'] = $o['placeholder'] ?? '';
	}
	return $f + array( 'attributes' => $attrs, 'settings' => $settings, 'editor_options' => array( 'title' => $title[ $el ], 'icon_class' => 'ff-edit-text', 'template' => $tpl[ $el ] ) );
}

/** A field from the site's field dictionary (kits_field_dict): same label, placeholder, length and messages. */
function aw_ff_dict( $key, $name = '', $over = array() ) {
	$d     = kits_field_dict()[ $key ];
	$type  = $d['type'] ?? 'text';
	$el    = 'email' === $type ? 'input_email' : ( 'textarea' === $type ? 'textarea' : 'input_text' );
	$attrs = array_intersect_key( (array) ( $d['attrs'] ?? array() ), array_flip( array( 'autocomplete', 'inputmode', 'maxlength', 'rows' ) ) );
	if ( isset( $d['placeholder'] ) ) {
		$attrs['placeholder'] = $d['placeholder'];
	}
	$o = array( 'attrs' => array_merge( $attrs, (array) ( $over['attrs'] ?? array() ) ) );
	if ( ! empty( $over['required'] ) || ( ! empty( $d['required'] ) && ! array_key_exists( 'required', $over ) ) ) {
		$o['required'] = (string) ( $d['messages']['required'] ?? 'This is required.' );
	}
	if ( isset( $over['hint'] ) ) {
		$o['hint'] = $over['hint'];
	}
	if ( isset( $over['class'] ) ) {
		$o['class'] = $over['class'];
	}
	return aw_ff_field( $el, $name ? $name : $key, $over['label'] ?? $d['label'], $o );
}

/** The fields of each form, in the order of the site's own form. */
function aw_ff_form_fields( $key ) {
	$nl = aw_ff_field( 'input_checkbox', 'nl_optin', '', array( 'options' => array( '1' => AW_NL_OPTIN_LABEL ), 'value' => array( '1' ) ) ); // ticked (owner, 16 Oct 2026)
	switch ( $key ) {
		case 'contact':
			$about = AW_CONTACT_ABOUT;
			unset( $about[''] );
			$f = array(
				aw_ff_field( 'select', 'about', 'What\'s this about?', array( 'required' => 'Choose what this is about', 'placeholder' => AW_CONTACT_ABOUT[''], 'options' => $about ) ),
				aw_ff_dict( 'name', '', array( 'required' => true ) ),
				aw_ff_dict( 'email' ),
				aw_ff_dict( 'organisation', 'org' ),
				aw_ff_dict( 'message', '', array( 'required' => true ) ),
				$nl,
				aw_ff_field( 'aw_check', 'check', 'Spam check (ALTCHA)' ),
			);
			return array( $f, 'Send message' );
		case 'volunteer':
			$f = array(
				aw_ff_field( 'input_checkbox', 'roles', 'How would you like to help?', array( 'required' => 'Choose how you\'d like to help', 'options' => AW_VOL_ROLES, 'class' => 'kit-field kit-check-group aw-vol-roles' ) ),
				aw_ff_dict( 'name', '', array( 'required' => true, 'class' => 'kit-field aw-form-ff-half' ) ),
				aw_ff_dict( 'email', '', array( 'class' => 'kit-field aw-form-ff-half' ) ),
				aw_ff_dict( 'phone', '', array( 'class' => 'kit-field aw-form-ff-half' ) ),
				aw_ff_field( 'aw_places', 'places', 'PIN code, State and District' ),
				aw_ff_field( 'input_checkbox', 'consent', '', array( 'required' => 'Tick the box to agree', 'options' => array( '1' => AW_VOL_CONSENT_LABEL ) ) ),
				aw_ff_field( 'input_checkbox', 'nl_optin', '', array( 'options' => array( '1' => AW_NL_OPTIN_LABEL ), 'value' => array( '1' ) ) ), // ticked (owner, 20 Oct 2026)
				aw_ff_field( 'aw_check', 'check', 'Spam check (ALTCHA)' ),
			);
			return array( $f, 'Sign up to volunteer' );
		case 'claim':
			$roles = AW_CLAIM_ROLES;
			unset( $roles[''] );
			$f = array(
				aw_ff_field( 'input_hidden', 'listing', 'Listing ID' ),
				aw_ff_field( 'aw_step', 'step1', 'About you' ),
				aw_ff_dict( 'name', '', array( 'required' => true ) ),
				aw_ff_field( 'select', 'role', 'Your role here', array( 'required' => 'Choose your role', 'placeholder' => AW_CLAIM_ROLES[''], 'options' => $roles ) ),
				aw_ff_dict( 'email', '', array( 'hint' => 'We use this only to check this request. It is not shown on the page.' ) ),
				aw_ff_dict( 'phone' ),
				aw_ff_field( 'aw_step', 'step2', 'What should change' ),
				aw_ff_field( 'custom_html', 'keep', '<p class="kit-field-hint">Leave a field empty to keep what is shown now.</p>' ),
				aw_ff_dict( 'phone', 'aw_phone' ),
				aw_ff_dict( 'whatsapp', 'aw_whatsapp' ),
				aw_ff_dict( 'email', 'aw_email', array( 'label' => 'Public email', 'required' => false ) ),
				aw_ff_dict( 'website', 'aw_website' ),
				aw_ff_dict( 'area', 'aw_area' ),
				aw_ff_field( 'aw_services', 'services', 'Listing services' ),
				aw_ff_field( 'aw_logo', 'logo', 'Logo upload' ),
				aw_ff_field( 'textarea', 'notes', 'Anything else we should change', array( 'hint' => 'For example a new address area, a closed branch or a wrong name.', 'attrs' => array( 'maxlength' => 1000, 'rows' => 3 ) ) ),
				aw_ff_field( 'input_checkbox', 'feature', '', array( 'options' => array( '1' => 'Also feature this listing' ) ) ),
				$nl,
				aw_ff_field( 'aw_check', 'check', 'Spam check (ALTCHA)' ),
			);
			return array( $f, 'Send for review' );
		case 'report':
			$f = array(
				aw_ff_field( 'input_hidden', 'listing', 'Listing ID' ),
				aw_ff_field( 'select', 'reason', 'What is wrong?', array( 'required' => 'Choose what is wrong', 'placeholder' => 'Choose one', 'options' => aw_report_reasons() ) ),
				aw_ff_field( 'textarea', 'details', 'Details', array( 'hint' => 'Tell us the correct details if you know them.', 'attrs' => array( 'maxlength' => 1000, 'rows' => 4 ) ) ),
				aw_ff_field( 'aw_check', 'check', 'Spam check (ALTCHA)' ),
			);
			return array( $f, 'Send report' );
		case 'remove_data':
			return array( array( aw_ff_dict( 'email' ), aw_ff_field( 'aw_check', 'check', 'Spam check (ALTCHA)' ) ), 'Send confirmation link' );
	}
	return array( array(), 'Send' );
}

/** Make (or remake) one form in Fluent Forms. Returns its ID. */
function aw_ff_make( $key, $replace_id = 0 ) {
	global $wpdb;
	list( $fields, $send ) = aw_ff_form_fields( $key );
	foreach ( $fields as $i => $f ) {
		$fields[ $i ]['index'] = $i + 1;
	}
	$body = array(
		'fields'       => $fields,
		'submitButton' => array(
			'uniqElKey'      => 'el_aw_submit',
			'element'        => 'button',
			'attributes'     => array( 'type' => 'submit', 'class' => 'kit-btn' ),
			'settings'       => array( 'align' => 'left', 'button_style' => '', 'container_class' => '', 'help_message' => '', 'background_color' => '', 'button_size' => 'md', 'color' => '', 'button_ui' => array( 'type' => 'default', 'text' => $send, 'img_url' => '' ) ),
			'editor_options' => array( 'title' => 'Submit Button' ),
		),
	);
	$now = current_time( 'mysql' );
	$row = array( 'title' => AW_FF_FORMS[ $key ]['title'], 'status' => 'published', 'form_fields' => wp_json_encode( $body ), 'has_payment' => 0, 'type' => 'form', 'created_by' => 1, 'updated_at' => $now );
	if ( $replace_id ) {
		$wpdb->update( "{$wpdb->prefix}fluentform_forms", $row, array( 'id' => $replace_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$id = $replace_id;
		$wpdb->delete( "{$wpdb->prefix}fluentform_form_meta", array( 'form_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the form's settings, made again below
	} else {
		$wpdb->insert( "{$wpdb->prefix}fluentform_forms", $row + array( 'created_at' => $now ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$id = (int) $wpdb->insert_id;
	}
	if ( ! $id ) {
		return 0;
	}
	$settings = array(
		'confirmation' => array( 'redirectTo' => 'samePage', 'messageToShow' => '<p>Sent.</p>', 'customPage' => null, 'samePageFormBehavior' => 'hide_form', 'customUrl' => null ), // replaced by the thank-you page on each send
		'restrictions' => array( 'denyEmptySubmission' => array( 'enabled' => false ) ),
		'layout'       => array( 'labelPlacement' => 'top', 'helpMessagePlacement' => 'after_label', 'errorMessagePlacement' => 'inline', 'asteriskPlacement' => 'none' ),
	);
	$meta = array( 'formSettings' => $settings, '_ff_selected_style' => 'ffs_inherit_theme', 'step_data_persistency_status' => 'no' ); // no notifications: the site's own emails are sent
	if ( 'claim' !== $key && 'report' !== $key ) {
		$meta['_primary_email_field'] = 'email';
	}
	foreach ( $meta as $k => $v ) {
		$wpdb->insert( "{$wpdb->prefix}fluentform_form_meta", array( 'form_id' => $id, 'meta_key' => $k, 'value' => is_string( $v ) ? $v : wp_json_encode( $v ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.SlowDBQuery
	}
	return $id;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	// wp aw ff-form <key> [--remake]: make the Fluent Forms form and show it in place of the site's own one.
	WP_CLI::add_command(
		'aw ff-form',
		function ( $args, $assoc ) {
			global $wpdb;
			$key = (string) ( $args[0] ?? '' );
			if ( ! isset( AW_FF_FORMS[ $key ] ) || ! defined( 'FLUENTFORM' ) ) {
				WP_CLI::error( 'Use one of: ' . implode( ', ', array_keys( AW_FF_FORMS ) ) . ' (Fluent Forms must be active).' );
			}
			$ids  = (array) get_option( AW_FF_OPTION, array() );
			$off  = (array) get_option( AW_FF_OPTION . '_off', array() ); // switched off earlier: the same form comes back
			$have = (int) ( $ids[ $key ] ?? ( $off[ $key ] ?? 0 ) );
			unset( $off[ $key ] );
			update_option( AW_FF_OPTION . '_off', $off, false );
			$have = $have && $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}fluentform_forms WHERE id = %d", $have ) ) ? $have : 0; // phpcs:ignore
			if ( $have && empty( $assoc['remake'] ) ) {
				$ids[ $key ] = $have;
				update_option( AW_FF_OPTION, $ids, false );
				WP_CLI::success( "Set up: {$have}" );
				return;
			}
			$id = aw_ff_make( $key, $have );
			if ( ! $id ) {
				WP_CLI::error( 'Could not make the form.' );
			}
			$ids[ $key ] = $id;
			update_option( AW_FF_OPTION, $ids, false );
			WP_CLI::success( ( $have ? 'Remade' : 'Made' ) . " {$key}: form {$id}" );
		}
	);
	// wp aw ff-off <key>: back to the site's own form (the Fluent Forms form and its entries stay).
	WP_CLI::add_command(
		'aw ff-off',
		function ( $args ) {
			$key = (string) ( $args[0] ?? '' );
			$ids = (array) get_option( AW_FF_OPTION, array() );
			$off = (array) get_option( AW_FF_OPTION . '_off', array() );
			if ( isset( $ids[ $key ] ) ) {
				$off[ $key ] = (int) $ids[ $key ];
				unset( $ids[ $key ] );
			}
			update_option( AW_FF_OPTION, $ids, false );
			update_option( AW_FF_OPTION . '_off', $off, false );
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
