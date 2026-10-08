<?php
/**
 * Shared form component (S5): the field dictionary, field markup, spam guard, server-side per-field errors, the
 * expandable and multi-step patterns, and one set of messages for every form.
 *
 * Markup matches the client validator in the theme's aw.js: each field's error lives in
 * <span class="kit-field-error" id="{id}-error"> right after the control, and the summary is
 * .kit-form-summary at the top of the form. With JavaScript off, the server returns the same
 * errors through a short-lived token (?aw_err=), so every visitor sees the same messages.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Errors and submitted values for $form from a failed submission, if this request carries them.
 *
 * @param string $form Form key.
 * @return array{errors: array<string,string>, values: array<string,mixed>, notice: string}
 */
function aw_form_state( $form ) {
	static $cache = null;
	if ( null === $cache ) {
		$token = isset( $_GET['aw_err'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', (string) wp_unslash( $_GET['aw_err'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$cache = $token ? get_transient( 'aw_form_' . $token ) : false;
	}
	if ( is_array( $cache ) && ( $cache['form'] ?? '' ) === $form ) {
		return $cache;
	}
	return array( 'errors' => array(), 'values' => array(), 'notice' => '' );
}

/**
 * Send the visitor back with per-field errors and what they typed (kept 10 minutes, never logged).
 *
 * @param string $form   Form key.
 * @param array  $errors field name => message.
 * @param array  $values field name => submitted value.
 * @param string $back   URL to return to.
 * @param string $anchor Fragment of the form.
 * @param string $notice Optional form-level message.
 */
function aw_form_fail( $form, $errors, $values, $back, $anchor, $notice = '' ) {
	if ( aw_form_is_fetch() ) { // submitted with fetch(): answer in JSON, the page keeps what was typed
		wp_send_json( array( 'ok' => false, 'errors' => (object) $errors, 'notice' => $notice ) );
	}
	$token = wp_generate_password( 16, false );
	set_transient( 'aw_form_' . $token, array( 'form' => $form, 'errors' => $errors, 'values' => $values, 'notice' => $notice ), 10 * MINUTE_IN_SECONDS );
	wp_safe_redirect( add_query_arg( 'aw_err', $token, $back ) . '#' . $anchor );
	exit;
}

/** The form-level message when sending fails (a form can set its own with the kits_form_generic_error filter). */
function aw_form_generic_error( $form ) {
	return (string) apply_filters( 'kits_form_generic_error', 'Couldn\'t send. Your details are still here — try again.', $form );
}

/** Was this submitted by kit.js with fetch() (X-AW-Fetch header)? */
function aw_form_is_fetch() {
	return ! empty( $_SERVER['HTTP_X_AW_FETCH'] );
}

/**
 * Success: JSON for fetch() (the form is replaced by the message in place), otherwise the usual redirect.
 *
 * @param string $url   Where a normal POST goes (with ?aw_done=...).
 * @param string $title Heading of the success message.
 * @param string $text  Line under it.
 * @param array  $more  Extra fields for the page script (e.g. a "Send another" label).
 */
function aw_form_success( $url, $title, $text = '', $more = array() ) {
	if ( aw_form_is_fetch() ) {
		wp_send_json( array_merge( array( 'ok' => true, 'title' => $title, 'text' => $text ), $more ) );
	}
	wp_safe_redirect( $url );
	exit;
}

/** Email cleaned as the server checks it: trimmed, domain in lower case. */
function aw_clean_email( $email ) {
	$email = trim( (string) $email );
	$at    = strrpos( $email, '@' );
	return false === $at ? $email : substr( $email, 0, $at + 1 ) . strtolower( substr( $email, $at + 1 ) );
}

/**
 * Where to send the visitor afterwards: the page they came from, without our query args.
 *
 * @param string $fallback Path if there is no referer.
 * @return string
 */
function aw_form_back( $fallback ) {
	$ref = wp_get_referer();
	return $ref ? remove_query_arg( array( 'aw_err', 'aw_done', 'joined', 'vol_error', 'sent', 'r_error' ), $ref ) : home_url( $fallback );
}

/**
 * Opening tag, hidden plumbing (action, nonce, timing, honeypot) and the error summary.
 *
 * @param string $form  Form key; also the admin-post action and nonce action.
 * @param array  $attrs Extra attributes for <form>.
 * @return string
 */
function aw_form_open( $form, $attrs = array() ) {
	$state  = aw_form_state( $form );
	$altcha = (string) ( $attrs['data-altcha'] ?? '' ); // "invisible": the check runs on send, no check box
	$attrs = array_merge(
		array(
			'class'  => 'kit-form',
			'method' => 'post',
			'action' => admin_url( 'admin-post.php' ),
			'id'     => 'aw-form-' . $form,
			'data-generic-error' => aw_form_generic_error( $form ),
		),
		$attrs
	);
	$html = '<form';
	foreach ( $attrs as $k => $v ) {
		$html .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
	}
	$html .= '>';
	// One status region per form: form-level errors and success (filled by the server or by kit.js).
	$html .= '<div class="kit-form-status" role="status" aria-live="polite" tabindex="-1">' . ( $state['notice'] && ! $state['errors'] ? esc_html( $state['notice'] ) : '' ) . '</div>';
	if ( count( $state['errors'] ) > 1 ) { // a summary only when there is more than one error
		$html .= '<div class="kit-form-summary" role="alert" tabindex="-1">';
		if ( $state['notice'] ) {
			$html .= '<strong>' . esc_html( $state['notice'] ) . '</strong>';
		}
		if ( $state['errors'] ) {
			$n     = count( $state['errors'] );
			$html .= '<strong>' . esc_html( 1 === $n ? 'Please fix this before sending:' : "Please fix these {$n} before sending:" ) . '</strong><ul>';
			foreach ( $state['errors'] as $name => $msg ) {
				$html .= '<li><a href="#' . esc_attr( aw_field_id( $form, $name ) ) . '">' . esc_html( $msg ) . '</a></li>';
			}
			$html .= '</ul>';
		}
		$html .= '</div>';
	}
	$html .= '<input type="hidden" name="action" value="' . esc_attr( $form ) . '">'
		. wp_nonce_field( $form, '_aw_nonce', true, false )
		. '<input type="hidden" name="aw_t" value="' . esc_attr( (string) time() ) . '">'
		. '<input type="hidden" name="aw_source" value="' . esc_attr( (string) ( $attrs['data-source'] ?? kits_form_source() ) ) . '">'
		. '<p class="kit-hp" aria-hidden="true"><label>Leave this empty <input type="text" name="aw_website" tabindex="-1" autocomplete="off"></label></p>'
		. kits_altcha_widget( $altcha );
	return $html;
}

/**
 * Stable id for a field.
 *
 * @param string $form Form key.
 * @param string $name Field name.
 * @return string
 */
function aw_field_id( $form, $name ) {
	return sanitize_html_class( str_replace( '_', '-', $form ) . '-' . rtrim( $name, '[]' ) );
}

/**
 * One form field: label, control, hint and error slot, with server-side state applied.
 *
 * @param string $form Form key.
 * @param array  $f    name, label, type (text|email|tel|url|textarea|select|file|checkbox),
 *                     required, hint, options (value => label), attrs (extra attributes), value.
 * @return string
 */
function aw_field( $form, $f ) {
	$f     = array_merge( array( 'type' => 'text', 'required' => false, 'hint' => '', 'options' => array(), 'attrs' => array(), 'value' => '', 'icon' => '', 'messages' => array() ), $f );
	$state = aw_form_state( $form );
	$key   = rtrim( $f['name'], '[]' );
	$id    = aw_field_id( $form, $f['name'] );
	$err   = $state['errors'][ $key ] ?? '';
	$val   = array_key_exists( $key, $state['values'] ) ? $state['values'][ $key ] : $f['value'];
	$by    = array();
	if ( $f['hint'] ) {
		$by[] = $id . '-hint';
	}
	$by[] = $id . '-error';

	// Messages the page script shows (same words as the server): data-msg-required, data-msg-email, ...
	$msgs = array();
	foreach ( $f['messages'] as $rule => $text ) {
		$msgs[ 'data-msg-' . $rule ] = $text;
	}
	$a = array_merge(
		array( 'id' => $id, 'name' => $f['name'], 'aria-describedby' => implode( ' ', $by ) ),
		$msgs,
		$f['required'] ? array( 'required' => true ) : array(),
		$err ? array( 'aria-invalid' => 'true' ) : array(),
		$f['attrs']
	);
	$attr = '';
	foreach ( $a as $k => $v ) {
		$attr .= ' ' . esc_attr( $k ) . ( true === $v ? '' : '="' . esc_attr( $v ) . '"' );
	}

	switch ( $f['type'] ) {
		case 'textarea':
			$control = '<textarea' . $attr . '>' . esc_textarea( (string) $val ) . '</textarea>';
			break;
		case 'select':
			$control = '<select' . $attr . '>';
			foreach ( $f['options'] as $v => $label ) {
				$control .= '<option value="' . esc_attr( $v ) . '"' . selected( (string) $val, (string) $v, false ) . '>' . esc_html( $label ) . '</option>';
			}
			$control .= '</select>';
			break;
		case 'checkbox':
			$control = '<input type="checkbox" value="1"' . $attr . checked( ! empty( $val ), true, false ) . '>';
			break;
		case 'file':
			$control = '<input type="file"' . $attr . '>';
			break;
		default:
			$control = '<input type="' . esc_attr( $f['type'] ) . '" value="' . esc_attr( (string) $val ) . '"' . $attr . '>';
	}

	$opt   = $f['required'] ? '' : ' <span class="kit-optional">(optional)</span>';
	$hint  = $f['hint'] ? '<span class="kit-field-hint" id="' . esc_attr( $id ) . '-hint">' . esc_html( $f['hint'] ) . '</span>' : '';
	// The message line is always there (reserved space), so showing an error never moves the layout.
	$error = '<span class="kit-field-message kit-field-error" id="' . esc_attr( $id ) . '-error" aria-live="polite">' . esc_html( $err ) . '</span>';
	$cls   = 'kit-field kit-field--' . $f['type'] . ( $err ? ' is-invalid' : '' );
	$icon  = $f['icon'] ? '<span class="kit-ico kit-ico--' . esc_attr( $f['icon'] ) . ' kit-field-icon" aria-hidden="true"></span>' : '';

	if ( 'checkbox' === $f['type'] ) {
		return '<div class="' . esc_attr( $cls ) . '"><label class="kit-checkbox kit-check" for="' . esc_attr( $id ) . '">' . $control . ' <span>' . esc_html( $f['label'] ) . '</span></label>' . $error . $hint . '</div>';
	}
	return '<div class="' . esc_attr( $cls ) . '"><label for="' . esc_attr( $id ) . '">' . esc_html( $f['label'] ) . $opt . '</label>' . $hint . '<div class="kit-field-control">' . $icon . $control . '</div>' . $error . '</div>';
}

/**
 * Check nonce, honeypot, timing, the ALTCHA answer and the per-connection rate limit for a submission.
 * Bots get a fake success; people over the limit get a form-level message.
 *
 * @param string $form    Form key.
 * @param string $back    Return URL.
 * @param string $anchor  Fragment of the form.
 * @param string $done    URL to send bots to (looks like success).
 * @param int    $per_hour Submissions allowed per connection per hour.
 * @return string Rate-limit key; call aw_form_count() after a successful save.
 */
function aw_form_guard( $form, $back, $anchor, $done, $per_hour = 5 ) {
	if ( ! isset( $_POST['_aw_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_aw_nonce'] ) ), $form ) ) {
		if ( aw_form_is_fetch() ) {
			wp_send_json( array( 'ok' => false, 'code' => 'expired', 'errors' => (object) array(), 'notice' => 'This page has been open a while. Reload it and send again.' ) ); // kit.js gets a fresh key and sends once more
		}
		aw_form_fail( $form, array(), aw_form_kept_values(), $back, $anchor, 'This page has been open a while. Reload it and send again.' );
	}
	if ( ! empty( $_POST['aw_website'] ) || ( time() - (int) ( $_POST['aw_t'] ?? 0 ) ) < 3 ) {
		aw_form_success( $done, '' ); // bots see a success
	}
	if ( ! kits_altcha_verify( wp_unslash( $_POST['altcha'] ?? '' ) ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		aw_form_fail( $form, array(), aw_form_kept_values(), $back, $anchor, 'Couldn\'t verify you\'re not a bot. Try again.' );
	}
	$key = 'aw_rl_' . $form . '_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	if ( (int) get_transient( $key ) >= $per_hour ) {
		aw_form_fail( $form, array(), aw_form_kept_values(), $back, $anchor, 'Too many tries. Wait a minute, then try again.' );
	}
	return $key;
}

/**
 * What the visitor typed, so a failed check doesn't empty the form (text fields only; never files).
 *
 * @return array<string,mixed>
 */
function aw_form_kept_values() {
	$skip = array( 'action', '_aw_nonce', '_wp_http_referer', 'aw_t', 'aw_website', 'altcha', 'aw_source' );
	$out  = array();
	foreach ( wp_unslash( $_POST ) as $k => $v ) { // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! in_array( $k, $skip, true ) ) {
			$out[ $k ] = is_array( $v ) ? array_map( 'sanitize_text_field', $v ) : sanitize_textarea_field( (string) $v );
		}
	}
	return $out;
}

/**
 * Count one successful submission against the rate limit.
 *
 * @param string $key Key from aw_form_guard().
 */
function aw_form_count( $key ) {
	set_transient( $key, (int) get_transient( $key ) + 1, HOUR_IN_SECONDS );
}

/**
 * Shared server-side rules. Returns field => message for each failure.
 *
 * @param array $rules  field => list of rules: required, email, phone, url, max:N.
 * @param array $values field => value.
 * @param array $labels field => label, for messages.
 * @return array<string,string>
 */
function aw_form_validate( $rules, $values, $labels, $messages = array() ) {
	$errors = array();
	foreach ( $rules as $field => $list ) {
		$v     = $values[ $field ] ?? '';
		$label = $labels[ $field ] ?? ucfirst( $field );
		foreach ( $list as $rule ) {
			if ( 'required' === $rule && ( '' === $v || array() === $v ) ) {
				$errors[ $field ] = $messages[ $field ]['required'] ?? ( in_array( 'email', $list, true ) ? 'Enter your email address' : 'Enter your ' . strtolower( preg_replace( '/^Your /i', '', $label ) ) );
			} elseif ( '' === $v ) {
				continue;
			} elseif ( 'email' === $rule && ( ! is_email( $v ) || strlen( $v ) > 254 ) ) {
				$errors[ $field ] = $messages[ $field ]['email'] ?? 'Enter a full email address, like you@example.com';
			} elseif ( 'phone' === $rule && '' === kits_clean_phone( $v ) ) {
				$errors[ $field ] = $messages[ $field ]['phone'] ?? 'Enter a 10-digit phone number, like 98765 43210';
			} elseif ( 'pin' === $rule && ! preg_match( '/^[1-9][0-9]{5}$/', $v ) ) {
				$errors[ $field ] = $messages[ $field ]['pin'] ?? 'Enter a 6-digit PIN code, like 682011';
			} elseif ( 'url' === $rule && ! wp_http_validate_url( kits_clean_url( $v ) ) ) {
				$errors[ $field ] = $messages[ $field ]['url'] ?? 'Enter a web address, like https://example.org';
			} elseif ( str_starts_with( $rule, 'max:' ) && mb_strlen( (string) $v ) > (int) substr( $rule, 4 ) ) {
				$errors[ $field ] = "{$label}: keep this under " . number_format_i18n( (int) substr( $rule, 4 ) ) . ' characters.';
			}
			if ( isset( $errors[ $field ] ) ) {
				break;
			}
		}
	}
	return $errors;
}
