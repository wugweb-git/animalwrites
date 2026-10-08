<?php
/**
 * Form kit (S5): the field dictionary (one definition per field, used by every form), clean-up helpers, the source
 * tag on every entry, a fresh form key for cached pages, and two patterns: the expandable form (S5.6) and the
 * multi-step form (S5.7). Field markup, the spam guard and messages live in forms.php; behaviour in kit.js.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Field definitions: key => label, type, icon, placeholder, attrs, rules, messages, required.
 * A site sets its own example values with the kits_field_dict filter.
 *
 * @return array
 */
function kits_field_dict() {
	$phone = array( 'autocomplete' => 'tel', 'inputmode' => 'tel', 'maxlength' => 16, 'data-rule' => 'phone', 'data-msg-phone' => 'Enter a 10-digit phone number, like 98765 43210' );
	$d     = array(
		'name'         => array( 'label' => 'Your name', 'icon' => 'name', 'placeholder' => 'Asha', 'attrs' => array( 'autocomplete' => 'name', 'maxlength' => 80 ), 'rules' => array( 'max:80' ), 'messages' => array( 'required' => 'Enter your name' ) ),
		'email'        => array( 'label' => 'Email', 'type' => 'email', 'icon' => 'mail', 'placeholder' => 'you@example.com', 'required' => true, 'attrs' => array( 'autocomplete' => 'email', 'inputmode' => 'email', 'maxlength' => 254 ), 'rules' => array( 'email' ), 'messages' => array( 'required' => 'Enter your email address', 'email' => 'Enter a full email address, like you@example.com' ) ),
		'phone'        => array( 'label' => 'Phone', 'type' => 'tel', 'icon' => 'phone', 'placeholder' => '98765 43210', 'attrs' => $phone, 'rules' => array( 'phone' ), 'messages' => array( 'required' => 'Enter your phone number', 'phone' => 'Enter a 10-digit phone number, like 98765 43210' ) ),
		'whatsapp'     => array( 'label' => 'WhatsApp', 'type' => 'tel', 'icon' => 'brand-whatsapp', 'placeholder' => '98765 43210', 'attrs' => $phone, 'rules' => array( 'phone' ), 'messages' => array( 'phone' => 'Enter a 10-digit phone number, like 98765 43210' ) ),
		'organisation' => array( 'label' => 'Organisation', 'icon' => 'building', 'placeholder' => 'Example Animal Trust', 'attrs' => array( 'autocomplete' => 'organization', 'maxlength' => 120 ), 'rules' => array( 'max:120' ) ),
		'role'         => array( 'label' => 'Your role here', 'type' => 'select', 'required' => true, 'messages' => array( 'required' => 'Choose your role' ) ),
		'pincode'      => array( 'label' => 'PIN code', 'icon' => 'map-pin', 'placeholder' => '682011', 'attrs' => array( 'inputmode' => 'numeric', 'maxlength' => 6, 'autocomplete' => 'postal-code', 'pattern' => '[1-9][0-9]{5}', 'data-pin-lookup' => '1', 'data-msg-pattern' => 'Enter a 6-digit PIN code, like 682011' ), 'rules' => array( 'pin' ) ),
		'state'        => array( 'label' => 'State', 'type' => 'select', 'attrs' => array( 'autocomplete' => 'address-level1' ), 'messages' => array( 'required' => 'Choose a state' ) ),
		'district'     => array( 'label' => 'District', 'type' => 'select', 'attrs' => array( 'autocomplete' => 'address-level2' ), 'messages' => array( 'required' => 'Choose a district' ) ),
		'area'         => array( 'label' => 'Area or locality', 'placeholder' => 'Edappally', 'attrs' => array( 'autocomplete' => 'address-level3', 'maxlength' => 120 ), 'rules' => array( 'max:120' ) ),
		'website'      => array( 'label' => 'Website', 'type' => 'url', 'icon' => 'world', 'placeholder' => 'https://example.org', 'attrs' => array( 'autocomplete' => 'url', 'inputmode' => 'url', 'maxlength' => 200, 'data-rule' => 'url', 'data-msg-url' => 'Enter a web address, like https://example.org' ), 'rules' => array( 'url' ), 'messages' => array( 'url' => 'Enter a web address, like https://example.org' ) ),
		'reason'       => array( 'label' => 'What\'s this about?', 'type' => 'select', 'required' => true, 'messages' => array( 'required' => 'Choose what this is about' ) ),
		'message'      => array( 'label' => 'Message', 'type' => 'textarea', 'attrs' => array( 'maxlength' => 3000, 'rows' => 5, 'data-counter' => 2500 ), 'rules' => array( 'max:3000' ), 'messages' => array( 'required' => 'Enter your message' ) ),
		'logo'         => array( 'label' => 'Logo', 'type' => 'file', 'attrs' => array( 'accept' => 'image/png,image/jpeg,image/webp', 'data-max-bytes' => 2 * MB_IN_BYTES, 'data-msg-file' => 'Use a PNG, JPG or WebP under 2 MB.' ) ),
		'consent'      => array( 'label' => '', 'type' => 'checkbox', 'required' => true, 'messages' => array( 'required' => 'Tick the box to agree' ) ),
	);
	return (array) apply_filters( 'kits_field_dict', $d );
}

/**
 * A field from the dictionary, with this form's changes (name, label, required, options, value, hint, messages…).
 *
 * @param string $form Form key.
 * @param string $key  Dictionary key.
 * @param array  $over Changes for this form.
 * @return string
 */
function kits_dict_field( $form, $key, $over = array() ) {
	$d     = kits_field_dict()[ $key ] ?? array();
	$attrs = array_merge( (array) ( $d['attrs'] ?? array() ), isset( $d['placeholder'] ) ? array( 'placeholder' => $d['placeholder'] ) : array(), (array) ( $over['attrs'] ?? array() ) );
	$msgs  = array_merge( (array) ( $d['messages'] ?? array() ), (array) ( $over['messages'] ?? array() ) );
	unset( $over['attrs'], $over['messages'] );
	$f = array_merge(
		array(
			'name'     => $key,
			'label'    => $d['label'] ?? ucfirst( $key ),
			'type'     => $d['type'] ?? 'text',
			'icon'     => $d['icon'] ?? '',
			'required' => ! empty( $d['required'] ),
		),
		$over
	);
	$f['attrs']    = $attrs;
	$f['messages'] = $msgs;
	return aw_field( $form, $f );
}

/**
 * Server rules and messages for a set of fields: [field => dictionary key], with the required ones listed.
 *
 * @return array{0: array, 1: array} rules, messages.
 */
function kits_dict_rules( $fields, $required = array() ) {
	$dict  = kits_field_dict();
	$rules = array();
	$msgs  = array();
	foreach ( $fields as $name => $key ) {
		$name           = is_int( $name ) ? $key : $name;
		$rules[ $name ] = array_merge( in_array( $name, $required, true ) ? array( 'required' ) : array(), (array) ( $dict[ $key ]['rules'] ?? array() ) );
		$msgs[ $name ]  = (array) ( $dict[ $key ]['messages'] ?? array() );
	}
	return array( $rules, $msgs );
}

/** A phone number as 10 digits (a leading +91 or 0 is dropped), or '' when it is not one. */
function kits_clean_phone( $v ) {
	$d = preg_replace( '/\D/', '', (string) $v );
	if ( 12 === strlen( $d ) && str_starts_with( $d, '91' ) ) {
		$d = substr( $d, 2 );
	} elseif ( 11 === strlen( $d ) && str_starts_with( $d, '0' ) ) {
		$d = substr( $d, 1 );
	}
	return 10 === strlen( $d ) ? $d : '';
}

/** A web address with https:// added when it has none. */
function kits_clean_url( $v ) {
	$v = trim( (string) $v );
	return ( '' === $v || preg_match( '#^https?://#i', $v ) ) ? $v : 'https://' . $v;
}

/** Where a form is on the site (S5.8): the page path ("home" on the front page). A page can set data-source. */
function kits_form_source() {
	$path = trim( (string) wp_parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	return '' === $path ? 'home' : $path;
}

/** The source a submission came with (hidden field aw_source). */
function kits_submitted_source() {
	return substr( sanitize_text_field( wp_unslash( $_POST['aw_source'] ?? '' ) ), 0, 120 ); // phpcs:ignore WordPress.Security.NonceVerification
}

// A fresh form key when a cached page's key has expired: kit.js asks once, then sends again.
add_action( 'wp_ajax_nopriv_kits_form_nonce', 'kits_form_nonce_json' );
add_action( 'wp_ajax_kits_form_nonce', 'kits_form_nonce_json' );
function kits_form_nonce_json() {
	nocache_headers();
	$form = sanitize_key( wp_unslash( $_GET['form'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	wp_send_json( array( 'nonce' => $form ? wp_create_nonce( $form ) : '' ) );
}

/**
 * Expandable form (S5.6): <details> that opens in place (works without JavaScript); the label becomes "Close" when
 * open; #id opens it and puts focus in the first field; in a group only one is open at a time.
 *
 * @param string $id      Anchor, e.g. "volunteer".
 * @param string $label   Button label.
 * @param string $content The form.
 * @param string $variant '' (primary) or 'secondary'.
 * @param string $group   Group name: one open at a time.
 * @return string
 */
function kits_disclosure( $id, $label, $content, $variant = '', $group = '' ) {
	$open = ! empty( $_GET['aw_err'] ) && false !== strpos( $content, 'aria-invalid' ); // phpcs:ignore WordPress.Security.NonceVerification -- reopen after a failed plain POST
	return '<details class="kit-disclosure" id="' . esc_attr( $id ) . '"' . ( $group ? ' data-group="' . esc_attr( $group ) . '"' : '' ) . ( $open ? ' open' : '' ) . '>'
		. '<summary class="kit-btn' . ( $variant ? ' kit-btn--' . esc_attr( $variant ) : '' ) . '"><span class="kit-disclosure-open">' . esc_html( $label ) . '</span><span class="kit-disclosure-close">Close</span></summary>'
		. '<div class="kit-disclosure-body">' . $content . '</div></details>';
}

/**
 * Multi-step form (S5.7): "Step 1 of 2", Back / Next, values kept between steps, the last step sends.
 * Without JavaScript every step shows and the form sends as one.
 *
 * @param array  $steps  List of [title, fields HTML].
 * @param string $submit Send button label.
 * @return string
 */
function kits_form_steps( $steps, $submit ) {
	$steps = array_values( $steps );
	$n     = count( $steps );
	$out   = '<div class="kit-steps" data-steps="' . (int) $n . '">';
	foreach ( $steps as $i => $s ) {
		$last = $i === $n - 1;
		$out .= '<fieldset class="kit-step" data-step="' . ( $i + 1 ) . '"><legend><span class="kit-step-count">Step ' . ( $i + 1 ) . ' of ' . (int) $n . '</span> ' . esc_html( $s[0] ) . '</legend>' . $s[1]
			. '<div class="kit-step-nav">' . ( $last ? '<button type="submit" class="kit-btn">' . esc_html( $submit ) . '</button>' : '<button type="button" class="kit-btn" data-step-next>Next</button>' )
			. ( $i > 0 ? '<button type="button" class="kit-btn kit-btn--secondary" data-step-back>Back</button>' : '' ) . '</div></fieldset>';
	}
	return $out . '</div>';
}
