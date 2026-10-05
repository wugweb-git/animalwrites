<?php
/**
 * Shared form component: field markup, spam guard and server-side per-field errors.
 *
 * Markup matches the client validator in the theme's aw.js: each field's error lives in
 * <span class="aw-field-error" id="{id}-error"> right after the control, and the summary is
 * .aw-form-summary at the top of the form. With JavaScript off, the server returns the same
 * errors through a short-lived token (?aw_err=), so every visitor sees the same messages.
 *
 * @package animal-writes-core
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
	$token = wp_generate_password( 16, false );
	set_transient( 'aw_form_' . $token, array( 'form' => $form, 'errors' => $errors, 'values' => $values, 'notice' => $notice ), 10 * MINUTE_IN_SECONDS );
	wp_safe_redirect( add_query_arg( 'aw_err', $token, $back ) . '#' . $anchor );
	exit;
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
	$state = aw_form_state( $form );
	$attrs = array_merge(
		array(
			'class'  => 'aw-form',
			'method' => 'post',
			'action' => admin_url( 'admin-post.php' ),
			'id'     => 'aw-form-' . $form,
		),
		$attrs
	);
	$html = '<form';
	foreach ( $attrs as $k => $v ) {
		$html .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
	}
	$html .= '>';
	if ( $state['errors'] || $state['notice'] ) {
		$html .= '<div class="aw-form-summary" role="alert" tabindex="-1">';
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
		. '<p class="aw-hp" aria-hidden="true"><label>Leave this empty <input type="text" name="aw_website" tabindex="-1" autocomplete="off"></label></p>';
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
	$f     = array_merge( array( 'type' => 'text', 'required' => false, 'hint' => '', 'options' => array(), 'attrs' => array(), 'value' => '' ), $f );
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

	$a = array_merge(
		array( 'id' => $id, 'name' => $f['name'], 'aria-describedby' => implode( ' ', $by ) ),
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

	$opt   = $f['required'] ? '' : ' <span class="aw-optional">(optional)</span>';
	$hint  = $f['hint'] ? '<span class="aw-field-hint" id="' . esc_attr( $id ) . '-hint">' . esc_html( $f['hint'] ) . '</span>' : '';
	$error = '<span class="aw-field-error" id="' . esc_attr( $id ) . '-error" aria-live="polite">' . esc_html( $err ) . '</span>';
	$cls   = 'aw-field aw-field--' . $f['type'] . ( $err ? ' is-invalid' : '' );

	if ( 'checkbox' === $f['type'] ) {
		return '<p class="' . esc_attr( $cls ) . '"><label class="aw-check" for="' . esc_attr( $id ) . '">' . $control . ' ' . esc_html( $f['label'] ) . '</label>' . $error . $hint . '</p>';
	}
	return '<p class="' . esc_attr( $cls ) . '"><label for="' . esc_attr( $id ) . '">' . esc_html( $f['label'] ) . $opt . '</label>' . $hint . $control . $error . '</p>';
}

/**
 * Check nonce, honeypot, timing and the per-connection rate limit for a submission.
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
		aw_form_fail( $form, array(), array(), $back, $anchor, 'This form expired. Please check your details and send it again.' );
	}
	if ( ! empty( $_POST['aw_website'] ) || ( time() - (int) ( $_POST['aw_t'] ?? 0 ) ) < 3 ) {
		wp_safe_redirect( $done );
		exit;
	}
	$key = 'aw_rl_' . $form . '_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	if ( (int) get_transient( $key ) >= $per_hour ) {
		aw_form_fail( $form, array(), array(), $back, $anchor, 'Too many submissions from this connection. Please try again in an hour.' );
	}
	return $key;
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
function aw_form_validate( $rules, $values, $labels ) {
	$errors = array();
	foreach ( $rules as $field => $list ) {
		$v     = $values[ $field ] ?? '';
		$label = $labels[ $field ] ?? ucfirst( $field );
		foreach ( $list as $rule ) {
			if ( 'required' === $rule && ( '' === $v || array() === $v ) ) {
				$errors[ $field ] = "{$label}: this is required.";
			} elseif ( '' === $v ) {
				continue;
			} elseif ( 'email' === $rule && ! is_email( $v ) ) {
				$errors[ $field ] = "{$label}: enter an email address like name@example.com.";
			} elseif ( 'phone' === $rule && ! preg_match( '/^\+?[0-9 ]{8,15}$/', $v ) ) {
				$errors[ $field ] = "{$label}: enter 8 to 15 digits, for example +91 98765 43210.";
			} elseif ( 'url' === $rule && ! wp_http_validate_url( $v ) ) {
				$errors[ $field ] = "{$label}: enter a link starting with https://.";
			} elseif ( str_starts_with( $rule, 'max:' ) && mb_strlen( (string) $v ) > (int) substr( $rule, 4 ) ) {
				$errors[ $field ] = "{$label}: keep this under " . (int) substr( $rule, 4 ) . ' characters.';
			}
			if ( isset( $errors[ $field ] ) ) {
				break;
			}
		}
	}
	return $errors;
}
