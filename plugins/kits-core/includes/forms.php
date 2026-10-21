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
 * @param string $url   Where a normal POST goes: the thank-you page (kits_thanks_url()) or a URL with ?aw_done=....
 * @param string $title Heading of the success message.
 * @param string $text  Line under it.
 * @param array  $more  Extra fields for the page script (e.g. a "Send another" label).
 */
function aw_form_success( $url, $title, $text = '', $more = array() ) {
	if ( aw_form_is_fetch() ) {
		// A thank-you page URL: kit.js goes there; otherwise the form is replaced by the message in place.
		$go = kits_is_thanks_url( $url ) ? array( 'redirect' => $url ) : array();
		wp_send_json( array_merge( array( 'ok' => true, 'title' => $title, 'text' => $text ), $more, $go ) );
	}
	wp_safe_redirect( $url );
	exit;
}

/* ---------- Thank-you page (one page for every form's success) ---------- */

/** Path of the site's thank-you page (filter kits_thanks_path). */
function kits_thanks_path() {
	return '/' . trim( (string) apply_filters( 'kits_thanks_path', '/thank-you/' ), '/' ) . '/';
}

/** Is $url the thank-you page? */
function kits_is_thanks_url( $url ) {
	return trailingslashit( (string) wp_parse_url( (string) $url, PHP_URL_PATH ) ) === trailingslashit( (string) wp_parse_url( home_url( kits_thanks_path() ), PHP_URL_PATH ) );
}

/**
 * Where a form goes when it is sent: the thank-you page, ?form=<name>, plus $args (e.g. listing), the page the person
 * came from (?from=, a path on this site) and, when a message is given, a short-lived token for it (?t=), so personal
 * details such as an email address are never in the URL.
 *
 * @param string $form  Form name, e.g. "contact".
 * @param array  $args  Extra query args.
 * @param string $title Message heading (the form's success message).
 * @param string $text  Line under it.
 * @return string
 */
function kits_thanks_url( $form, $args = array(), $title = '', $text = '' ) {
	$q = array( 'form' => sanitize_key( $form ) );
	if ( ! isset( $args['from'] ) ) {
		$ref  = wp_get_referer();
		$path = $ref ? (string) wp_parse_url( $ref, PHP_URL_PATH ) : '';
		if ( $path && ! kits_is_thanks_url( $path ) ) {
			$q['from'] = $path;
		}
	}
	if ( '' !== $title || '' !== $text ) {
		$token = wp_generate_password( 16, false );
		set_transient( 'kits_thanks_' . $token, array( 'form' => $q['form'], 'title' => $title, 'text' => $text ), 30 * MINUTE_IN_SECONDS );
		$q['t'] = $token;
	}
	return add_query_arg( array_merge( $q, $args ), home_url( kits_thanks_path() ) );
}

/** The form name on the thank-you page (?form=). */
function kits_thanks_form() {
	return sanitize_key( wp_unslash( $_GET['form'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
}

/** The message saved for this thank-you page (?t=): array{title: string, text: string}, or null. */
function kits_thanks_message() {
	$token = preg_replace( '/[^A-Za-z0-9]/', '', (string) wp_unslash( $_GET['t'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$msg   = $token ? get_transient( 'kits_thanks_' . $token ) : false;
	return is_array( $msg ) && ( $msg['form'] ?? '' ) === kits_thanks_form() ? $msg : null;
}

/** The page the person came from (?from=), as a URL on this site, or ''. */
function kits_thanks_from() {
	$path = (string) wp_unslash( $_GET['from'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( '' === $path || '/' !== $path[0] || str_starts_with( $path, '//' ) ) {
		return '';
	}
	$url = home_url( $path );
	return wp_validate_redirect( $url, '' ) === $url ? $url : '';
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
	$GLOBALS['aw_form_copies'][ $form ] = ( $GLOBALS['aw_form_copies'][ $form ] ?? 0 ) + 1; // the same form twice on a page (a page's own and the footer's)
	$state  = aw_form_state( $form );
	$altcha = (string) ( $attrs['data-altcha'] ?? '' ); // "invisible": the check runs on send, no check box
	$attrs = array_merge(
		array(
			'class'  => 'kit-form',
			'method' => 'post',
			'action' => admin_url( 'admin-post.php' ),
			'id'     => 'aw-form-' . $form . aw_form_copy_suffix( $form ),
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
		. str_replace( ' id="_aw_nonce"', '', wp_nonce_field( $form, '_aw_nonce', true, false ) ) // several forms on a page: no repeated id
		. '<input type="hidden" name="aw_t" value="' . esc_attr( (string) time() ) . '">'
		. '<input type="hidden" name="aw_source" value="' . esc_attr( (string) ( $attrs['data-source'] ?? kits_form_source() ) ) . '">'
		. '<p class="kit-hp" aria-hidden="true"><label>Leave this empty <input type="text" name="kits_hp" tabindex="-1" autocomplete="off"></label></p>'
		. kits_altcha_widget( $altcha );
	return $html;
}

/**
 * Id suffix for the second and later copies of a form on one page ("-2", "-3"), so no id repeats; '' for the first.
 *
 * @param string $form Form key.
 * @return string
 */
function aw_form_copy_suffix( $form ) {
	$n = (int) ( $GLOBALS['aw_form_copies'][ $form ] ?? 1 );
	return $n > 1 ? '-' . $n : '';
}

/**
 * Stable id for a field (in the form copy being built).
 *
 * @param string $form Form key.
 * @param string $name Field name.
 * @return string
 */
function aw_field_id( $form, $name ) {
	return sanitize_html_class( str_replace( '_', '-', $form ) . '-' . rtrim( $name, '[]' ) . aw_form_copy_suffix( $form ) );
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
	if ( ! empty( $_POST['kits_hp'] ) || ( time() - (int) ( $_POST['aw_t'] ?? 0 ) ) < 3 ) {
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
	$skip = array( 'action', '_aw_nonce', '_wp_http_referer', 'aw_t', 'altcha', 'aw_source', 'kits_hp' );
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

/* ---------- What every form records (owner, 21 Oct 2026) ---------- */
/*
 * Next to each entry a form saves: every tick as 1/0 with its wording version (`aw_ticks`), and one visit block
 * (`aw_visit`). The site's forms call aw_form_record() in their save function and keep both on their record (post meta
 * aw_ticks/aw_visit, or the ticks/visit columns of a table row); WP Admin shows them read-only as "Visit details".
 *
 * Ticks (JSON): name => {value: 1|0 (a group of boxes: option => 1|0), version, label}. A box is recorded whatever the
 * form then does with it. The boxes are the form's own list (filter aw_form_known_boxes), every check box of a Fluent
 * Forms form, and every check box the page script finds in the form (aw_boxes), so a box added later is recorded too.
 * The version is the consent wording's version for consent boxes, otherwise "label-" and a short hash of the wording.
 *
 * Visit (JSON): page URL, referrer, UTM source/medium/campaign (the page script: the page, and the browser session's
 * landing referrer and UTM); IP, city/region/country (Cloudflare's CF-IPCountry/CF-IPCity headers when present,
 * otherwise the DB-IP Lite database read on this server: no IP leaves the server), ISP and ASN (DB-IP ASN Lite),
 * network type (the browser's, when it says); device, browser, OS (user agent); screen size, language, time zone;
 * time, seconds from the form first showing to sending, first-visit date and visit count (first-party cookie aw_fv);
 * GA4 client ID and Clarity session ID only when analytics consent is granted.
 * GeoIP files: `wp aw geoip-update` (and monthly by cron) into the private uploads folder. IP geolocation by DB-IP
 * (https://db-ip.com), CC BY 4.0. Reader: MaxMind DB Reader (vendor/maxmind-db, Apache 2.0).
 */

/** Posted by the page script: the browser's part of the visit block, and the form's check boxes. */
const AW_VISIT_FIELD = 'aw_visit';
const AW_BOXES_FIELD = 'aw_boxes';

/** A private folder in uploads (not served): the site's own (aw_private_dir) when it has one. */
function kits_private_dir() {
	if ( function_exists( 'aw_private_dir' ) ) {
		return aw_private_dir();
	}
	$up  = wp_upload_dir();
	$dir = trailingslashit( $up['basedir'] ) . 'kits-private';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
		file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore
	}
	return $dir;
}

/** A wording version from the wording itself, for a box without a consent version. */
function aw_box_version( $label ) {
	$label = trim( wp_strip_all_tags( (string) $label ) );
	return '' === $label ? '' : 'label-' . substr( md5( $label ), 0, 8 );
}

/** Was a posted box ticked (a value, or a list with any value)? */
function aw_box_on( $v ) {
	return is_array( $v ) ? (bool) array_filter( $v, fn( $x ) => '' !== (string) $x && '0' !== (string) $x ) : ( '' !== (string) $v && '0' !== (string) $v && 'false' !== (string) $v );
}

/**
 * Every tick of a submission as 1/0.
 *
 * @param string $form   Form key.
 * @param array  $posted Submitted values (unslashed).
 * @param array  $ctx    ff (bool); ff_form (Fluent Forms form object); groups (name => option values, for box groups).
 * @return array name => array( value, version, label ).
 */
function aw_form_ticks( $form, $posted, $ctx = array() ) {
	$boxes = array(); // name => array( label, version, options )
	foreach ( (array) apply_filters( 'aw_form_known_boxes', array(), $form, $ctx ) as $name => $b ) {
		$boxes[ $name ] = array( $b[0], $b[1], array() );
	}
	foreach ( (array) ( $ctx['groups'] ?? array() ) as $name => $opts ) {
		$boxes[ $name ] = array( $boxes[ $name ][0] ?? $name, $boxes[ $name ][1] ?? '', array_map( 'strval', (array) $opts ) );
	}
	if ( ! empty( $ctx['ff_form'] ) ) { // every check box in the Fluent Forms form
		foreach ( (array) ( $ctx['ff_form']->fields['fields'] ?? array() ) as $f ) {
			if ( 'input_checkbox' !== ( $f['element'] ?? '' ) ) {
				continue;
			}
			$name = (string) ( $f['attributes']['name'] ?? '' );
			$opts = wp_list_pluck( (array) ( $f['settings']['advanced_options'] ?? array() ), 'label', 'value' );
			if ( '' === $name ) {
				continue;
			}
			$label          = (string) ( $boxes[ $name ][0] ?? ( 1 === count( $opts ) ? reset( $opts ) : wp_strip_all_tags( (string) ( $f['settings']['label'] ?? $name ) ) ) );
			$boxes[ $name ] = array( $label, $boxes[ $name ][1] ?? '', count( $opts ) > 1 ? array_map( 'strval', array_keys( $opts ) ) : array() );
		}
	}
	$seen = json_decode( (string) ( $posted[ AW_BOXES_FIELD ] ?? '' ), true ); // the boxes the page script (aw-analytics.js) found in the form
	foreach ( is_array( $seen ) ? array_slice( $seen, 0, 30, true ) : array() as $name => $b ) {
		$name = preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $name ) );
		if ( '' === $name || isset( $boxes[ $name ] ) ) {
			continue;
		}
		$opts           = array_slice( array_map( fn( $o ) => substr( sanitize_key( (string) $o ), 0, 40 ), (array) ( $b['options'] ?? array() ) ), 0, 30 );
		$boxes[ $name ] = array( substr( sanitize_text_field( (string) ( $b['label'] ?? $name ) ), 0, 300 ), '', count( $opts ) > 1 ? $opts : array() );
	}
	$out = array();
	foreach ( $boxes as $name => $b ) {
		$v = $posted[ $name ] ?? ( $posted[ $name . '[]' ] ?? '' );
		if ( $b[2] ) {
			$on    = array_map( 'strval', (array) $v );
			$value = array();
			foreach ( $b[2] as $opt ) {
				$value[ $opt ] = in_array( $opt, $on, true ) ? 1 : 0;
			}
		} else {
			$value = aw_box_on( $v ) ? 1 : 0;
		}
		$out[ $name ] = array( 'value' => $value, 'version' => '' !== $b[1] ? $b[1] : aw_box_version( $b[0] ), 'label' => $b[0] );
	}
	return $out;
}

/** A tick's value (1/0), or null when the form has no such box. */
function aw_tick( $ticks, $name ) {
	return isset( $ticks[ $name ] ) && ! is_array( $ticks[ $name ]['value'] ) ? (int) $ticks[ $name ]['value'] : null;
}

/* ---------- One submission ---------- */

/** What the form sent: the Fluent Forms entry while one is handled (forms-fluentforms.php), else the posted form. */
function aw_form_posted() {
	return isset( $GLOBALS['aw_ff_data'] ) ? (array) $GLOBALS['aw_ff_data'] : (array) wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification -- read only; each form's guard checks the request
}

/**
 * The visit block and ticks of the submission being handled.
 *
 * @param string $form Form key (aw_form_known_boxes()).
 * @param array  $o    page (site path or URL, when the browser sent none), groups (box groups: name => option values),
 *                     posted (values to read the ticks from, when not the form's own), client (visit block's browser part).
 * @return array{0: array, 1: array} visit, ticks.
 */
function aw_form_record( $form, $o = array() ) {
	$posted = $o['posted'] ?? aw_form_posted();
	if ( isset( $o['client_boxes'] ) ) {
		$posted[ AW_BOXES_FIELD ] = is_string( $o['client_boxes'] ) ? $o['client_boxes'] : wp_json_encode( $o['client_boxes'] );
	} elseif ( ! isset( $posted[ AW_BOXES_FIELD ] ) ) {
		$posted[ AW_BOXES_FIELD ] = aw_form_posted()[ AW_BOXES_FIELD ] ?? '';
	}
	$ff    = isset( $GLOBALS['aw_ff_form'] ) ? $GLOBALS['aw_ff_form'] : null;
	$ticks = aw_form_ticks( $form, $posted, array( 'ff' => (bool) $ff, 'ff_form' => $ff, 'groups' => $o['groups'] ?? array() ) );
	$visit = aw_visit( array( 'page' => $o['page'] ?? '', 'started' => (int) ( aw_form_posted()['aw_t'] ?? 0 ) ) + ( array_key_exists( 'client', $o ) ? array( 'client' => $o['client'] ) : array() ) );
	return array( $visit, $ticks );
}

/**
 * Store a visit block and ticks on a table row (volunteers, newsletter, letters): columns visit and ticks.
 *
 * @param string $table Table.
 * @param array  $where Column => value.
 */
function aw_record_row( $table, $where, $visit, $ticks ) {
	global $wpdb;
	$wpdb->update( $table, array( 'visit' => wp_json_encode( $visit ), 'ticks' => wp_json_encode( (object) $ticks ) ), $where ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/* ---------- The visit block ---------- */

/**
 * The visit block for this request.
 *
 * @param array $o page (URL or site path, when the browser sent none), started (form shown, Unix time; the
 *                 form's aw_t), client (the browser's part, when not in $_POST).
 * @return array
 */
function aw_visit( $o = array() ) {
	$raw = $o['client'] ?? ( aw_form_posted()[ AW_VISIT_FIELD ] ?? '' );
	$c   = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
	$c   = is_array( $c ) ? $c : array();
	$s   = fn( $k, $n = 200 ) => substr( sanitize_text_field( (string) ( $c[ $k ] ?? '' ) ), 0, $n );
	$url = fn( $v ) => substr( esc_url_raw( (string) $v ), 0, 500 );
	$ip  = trim( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- LiteSpeed puts the client address here (kits-core preview.php)
	$ip  = filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	$ua  = substr( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 400 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$geo = aw_visit_geo( $ip );
	$dev = aw_visit_user_agent( $ua );
	$page = $url( $c['page'] ?? '' );
	if ( '' === $page ) {
		$page = aw_visit_page_url( (string) ( $o['page'] ?? '' ) );
	}
	$secs = isset( $c['secs'] ) && is_numeric( $c['secs'] ) ? (int) $c['secs'] : null;
	if ( null === $secs && ! empty( $o['started'] ) ) {
		$secs = max( 0, time() - (int) $o['started'] );
	}
	$consent = 'granted' === ( $c['consent'] ?? '' );
	return array(
		'time'            => wp_date( 'c' ),
		'page'            => $page,
		'referrer'        => $url( $c['referrer'] ?? '' ),
		'utm_source'      => $s( 'utm_source', 100 ),
		'utm_medium'      => $s( 'utm_medium', 100 ),
		'utm_campaign'    => $s( 'utm_campaign', 100 ),
		'ip'              => $ip,
		'city'            => $geo['city'],
		'region'          => $geo['region'],
		'country'         => $geo['country'],
		'geo_source'      => $geo['source'],
		'isp'             => $geo['isp'],
		'asn'             => $geo['asn'],
		'network'         => $s( 'net', 20 ),
		'device'          => $dev['device'],
		'browser'         => $dev['browser'],
		'os'              => $dev['os'],
		'user_agent'      => sanitize_text_field( $ua ),
		'screen'          => preg_match( '/^\d{2,5}x\d{2,5}$/', (string) ( $c['screen'] ?? '' ) ) ? (string) $c['screen'] : '',
		'language'        => $s( 'lang', 35 ),
		'timezone'        => $s( 'tz', 60 ),
		'seconds'         => $secs,
		'first_visit'     => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $c['first'] ?? '' ) ) ? (string) $c['first'] : '',
		'visits'          => isset( $c['visits'] ) && is_numeric( $c['visits'] ) ? (int) $c['visits'] : null,
		'analytics'       => $consent ? 'granted' : ( '' === $s( 'consent', 10 ) ? 'unknown' : 'denied' ),
		'ga_client_id'    => $consent && preg_match( '/^\d+\.\d+$/', (string) ( $c['ga'] ?? '' ) ) ? (string) $c['ga'] : '',
		'clarity_session' => $consent ? substr( preg_replace( '/[^a-z0-9]/i', '', (string) ( $c['clarity'] ?? '' ) ), 0, 40 ) : '',
	);
}

/** A page URL from a URL or a site path ("home" is the front page); this site's referrer when there is none. */
function aw_visit_page_url( $page ) {
	$page = trim( (string) $page );
	if ( '' === $page ) {
		$ref = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return wp_parse_url( $ref, PHP_URL_HOST ) === wp_parse_url( home_url(), PHP_URL_HOST ) ? (string) strtok( $ref, '#' ) : '';
	}
	if ( preg_match( '#^https?://#', $page ) ) {
		return esc_url_raw( $page );
	}
	return 'home' === $page ? home_url( '/' ) : home_url( '/' . trim( $page, '/' ) . '/' );
}

/** Store a visit block and ticks on a post (contact message, claim, report, data request). */
function aw_record_post( $post_id, $visit, $ticks = array() ) {
	update_post_meta( $post_id, 'aw_visit', wp_slash( wp_json_encode( $visit ) ) );
	update_post_meta( $post_id, 'aw_ticks', wp_slash( wp_json_encode( (object) $ticks ) ) );
}

/** A record's visit block and ticks from a post. */
function aw_record_get_post( $post_id ) {
	return array( (array) json_decode( (string) get_post_meta( $post_id, 'aw_visit', true ), true ), (array) json_decode( (string) get_post_meta( $post_id, 'aw_ticks', true ), true ) );
}

/* ---------- Place and network from the IP (no outside service) ---------- */

/** Where the GeoIP files live (private uploads folder, not served). */
function aw_geoip_file( $kind ) {
	return kits_private_dir() . '/geoip/dbip-' . ( 'asn' === $kind ? 'asn' : 'city' ) . '-lite.mmdb';
}

/** A DB-IP reader, or null when the file is not there. */
function aw_geoip_reader( $kind ) {
	static $r = array();
	if ( array_key_exists( $kind, $r ) ) {
		return $r[ $kind ];
	}
	$r[ $kind ] = null;
	$file       = aw_geoip_file( $kind );
	if ( is_readable( $file ) ) {
		aw_geoip_load_reader();
		try {
			$r[ $kind ] = new \MaxMind\Db\Reader( $file );
		} catch ( \Throwable $e ) {
			$r[ $kind ] = null;
		}
	}
	return $r[ $kind ];
}

/**
 * City, region, country, ISP and ASN of an IP: Cloudflare's visitor headers when present, otherwise the local files.
 *
 * @return array{city:string, region:string, country:string, source:string, isp:string, asn:string}
 */
function aw_visit_geo( $ip ) {
	$h   = fn( $k ) => sanitize_text_field( wp_unslash( (string) ( $_SERVER[ $k ] ?? '' ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$out = array( 'city' => '', 'region' => '', 'country' => '', 'source' => '', 'isp' => '', 'asn' => '' );
	if ( '' !== $h( 'HTTP_CF_IPCOUNTRY' ) && 'XX' !== $h( 'HTTP_CF_IPCOUNTRY' ) ) {
		$out = array( 'city' => $h( 'HTTP_CF_IPCITY' ), 'region' => $h( 'HTTP_CF_REGION' ), 'country' => $h( 'HTTP_CF_IPCOUNTRY' ), 'source' => 'cloudflare', 'isp' => '', 'asn' => $h( 'HTTP_CF_ASN' ) ? 'AS' . preg_replace( '/\D/', '', $h( 'HTTP_CF_ASN' ) ) : '' ) + $out;
	}
	if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
		return $out;
	}
	$city = '' === $out['country'] ? aw_geoip_reader( 'city' ) : null;
	if ( $city ) {
		try {
			$d   = (array) $city->get( $ip );
			$out = array(
				'city'    => (string) ( $d['city']['names']['en'] ?? '' ),
				'region'  => (string) ( $d['subdivisions'][0]['names']['en'] ?? '' ),
				'country' => (string) ( $d['country']['names']['en'] ?? ( $d['country']['iso_code'] ?? '' ) ),
				'source'  => $d ? 'geoip' : '',
			) + $out;
		} catch ( \Throwable $e ) {
			unset( $e ); // a bad address or file: no place
		}
	}
	$asn = aw_geoip_reader( 'asn' );
	if ( $asn ) {
		try {
			$d          = (array) $asn->get( $ip );
			$out['isp'] = (string) ( $d['autonomous_system_organization'] ?? '' );
			$out['asn'] = ! empty( $d['autonomous_system_number'] ) ? 'AS' . (int) $d['autonomous_system_number'] : $out['asn'];
		} catch ( \Throwable $e ) {
			unset( $e );
		}
	}
	return $out;
}

/**
 * Download this month's DB-IP Lite files (city, ASN) into the private folder. Keeps the old files on any failure.
 *
 * @return array kind => 'updated' | error message.
 */
function aw_geoip_update() {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	aw_geoip_load_reader();
	$dir = dirname( aw_geoip_file( 'city' ) );
	wp_mkdir_p( $dir );
	$res = array();
	foreach ( array( 'city', 'asn' ) as $kind ) {
		$got = '';
		foreach ( array( gmdate( 'Y-m' ), gmdate( 'Y-m', strtotime( 'first day of last month' ) ) ) as $month ) { // early in a month the new file may not be out yet
			$tmp = download_url( 'https://download.db-ip.com/free/dbip-' . $kind . '-lite-' . $month . '.mmdb.gz', 600 );
			if ( ! is_wp_error( $tmp ) ) {
				$got = $tmp;
				break;
			}
		}
		if ( ! $got ) {
			$res[ $kind ] = 'download failed';
			continue;
		}
		$in  = gzopen( $got, 'rb' );
		$out = fopen( $dir . '/.new-' . $kind, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		while ( $in && $out && ! gzeof( $in ) ) {
			fwrite( $out, (string) gzread( $in, 1048576 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		$in && gzclose( $in );
		$out && fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		wp_delete_file( $got );
		try {
			$check = new \MaxMind\Db\Reader( $dir . '/.new-' . $kind ); // throws on a broken file
			$check->close();
			rename( $dir . '/.new-' . $kind, aw_geoip_file( $kind ) );
			$res[ $kind ] = 'updated';
		} catch ( \Throwable $e ) {
			wp_delete_file( $dir . '/.new-' . $kind );
			$res[ $kind ] = 'broken file: ' . $e->getMessage();
		}
	}
	update_option( 'aw_geoip_updated', array( 'at' => current_time( 'mysql' ), 'result' => $res ), false );
	return $res;
}

// Monthly refresh of the GeoIP files (DB-IP publishes a new month at its start).
add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'aw_geoip_monthly' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'monthly', 'aw_geoip_monthly' );
		}
	}
);
add_filter( 'cron_schedules', fn( $s ) => $s + array( 'monthly' => array( 'interval' => 30 * DAY_IN_SECONDS, 'display' => 'Monthly' ) ) );
add_action(
	'aw_geoip_monthly',
	function () {
		aw_geoip_load_reader();
		aw_geoip_update();
	}
);

/** Load the vendored reader (DB-IP files use the MaxMind DB format). */
function aw_geoip_load_reader() {
	if ( ! class_exists( '\MaxMind\Db\Reader' ) ) {
		foreach ( array( 'Reader/InvalidDatabaseException', 'Reader/Util', 'Reader/Metadata', 'Reader/Decoder', 'Reader' ) as $f ) {
			require_once KITS_CORE_DIR . 'vendor/maxmind-db/src/MaxMind/Db/' . $f . '.php';
		}
	}
}

/**
 * Device, browser and operating system from a user agent (names only).
 *
 * @return array{device:string, browser:string, os:string}
 */
function aw_visit_user_agent( $ua ) {
	$pick    = function ( $list ) use ( $ua ) {
		foreach ( $list as $re => $name ) {
			if ( preg_match( $re, $ua ) ) {
				return $name;
			}
		}
		return '' === $ua ? '' : 'Other';
	};
	$os      = $pick( array( '/Windows/i' => 'Windows', '/iPad/' => 'iPadOS', '/iPhone|iPod/' => 'iOS', '/Android/i' => 'Android', '/CrOS/' => 'ChromeOS', '/Mac OS X|Macintosh/' => 'macOS', '/Linux/i' => 'Linux' ) );
	$browser = $pick( array( '/Edg(e|A|iOS)?\//' => 'Edge', '/OPR\/|Opera/' => 'Opera', '/SamsungBrowser/' => 'Samsung Internet', '/UCBrowser/' => 'UC Browser', '/FxiOS|Firefox\//' => 'Firefox', '/CriOS|Chrome\//' => 'Chrome', '/Safari\//' => 'Safari' ) );
	$device  = '' === $ua ? '' : ( preg_match( '/iPad|Tablet|Android(?!.*Mobile)/i', $ua ) ? 'Tablet' : ( preg_match( '/Mobi|iPhone|iPod|Android/i', $ua ) ? 'Mobile' : 'Desktop' ) );
	return array( 'device' => $device, 'browser' => $browser, 'os' => $os );
}

/* ---------- WP Admin: "Visit details" (read-only) ---------- */

/** Labels for the visit block, in order. */
const AW_VISIT_LABELS = array(
	'time'            => 'Sent',
	'page'            => 'Page',
	'referrer'        => 'Referrer',
	'utm_source'      => 'UTM source',
	'utm_medium'      => 'UTM medium',
	'utm_campaign'    => 'UTM campaign',
	'ip'              => 'IP address',
	'city'            => 'City',
	'region'          => 'Region',
	'country'         => 'Country',
	'geo_source'      => 'Place from',
	'isp'             => 'ISP',
	'asn'             => 'ASN',
	'network'         => 'Network type',
	'device'          => 'Device',
	'browser'         => 'Browser',
	'os'              => 'Operating system',
	'screen'          => 'Screen',
	'language'        => 'Language',
	'timezone'        => 'Time zone',
	'seconds'         => 'Seconds from form open to send',
	'first_visit'     => 'First visit',
	'visits'          => 'Visits',
	'analytics'       => 'Analytics consent',
	'ga_client_id'    => 'GA4 client ID',
	'clarity_session' => 'Clarity session ID',
	'user_agent'      => 'User agent',
);

/** The read-only "Visit details" table: ticks first, then the visit block. */
function aw_visit_details_html( $visit, $ticks ) {
	$visit = (array) $visit;
	$ticks = (array) $ticks;
	if ( ! $visit && ! $ticks ) {
		return '<p class="description">No visit details: sent before 21 Oct 2026, or added by hand.</p>';
	}
	$rows = '';
	foreach ( $ticks as $name => $t ) {
		$v     = $t['value'] ?? '';
		$v     = is_array( $v ) ? implode( ', ', array_map( fn( $k, $x ) => $k . ' ' . (int) $x, array_keys( $v ), $v ) ) : (string) (int) $v;
		$rows .= '<tr><th scope="row">Tick: ' . esc_html( $name ) . '</th><td><strong>' . esc_html( $v ) . '</strong>' . ( empty( $t['version'] ) ? '' : ' · wording ' . esc_html( $t['version'] ) ) . ( empty( $t['label'] ) ? '' : '<br><span class="description">' . esc_html( $t['label'] ) . '</span>' ) . '</td></tr>';
	}
	foreach ( AW_VISIT_LABELS as $k => $label ) {
		$v = $visit[ $k ] ?? '';
		if ( null === $v || '' === (string) $v ) {
			$v = '—';
		} elseif ( 'time' === $k ) {
			$v = wp_date( 'j M Y, H:i:s', strtotime( (string) $v ) );
		}
		$rows .= '<tr><th scope="row">' . esc_html( $label ) . '</th><td style="word-break:break-all">' . esc_html( (string) $v ) . '</td></tr>';
	}
	return '<table class="widefat striped aw-visit-details"><tbody>' . $rows . '</tbody></table><p class="description">Read-only. Place and network: IP geolocation by <a href="https://db-ip.com" rel="noopener">DB-IP</a> (CC BY 4.0), on this server.</p>';
}

/**
 * A table row's "Visit details" as a closed disclosure (volunteers, newsletter, letters lists).
 *
 * @param array $row Row with ticks and visit columns.
 */
function aw_visit_details_disclosure( $row ) {
	$visit = (array) json_decode( (string) ( $row['visit'] ?? '' ), true );
	$ticks = (array) json_decode( (string) ( $row['ticks'] ?? '' ), true );
	if ( ! $visit && ! $ticks ) {
		return '—';
	}
	return '<details><summary>Visit details</summary>' . aw_visit_details_html( $visit, $ticks ) . '</details>';
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	// wp aw geoip-update: this month's DB-IP Lite city and ASN files into the private folder (also monthly by cron).
	WP_CLI::add_command(
		'aw geoip-update',
		function () {
			aw_geoip_load_reader();
			$res = aw_geoip_update();
			WP_CLI::success( wp_json_encode( $res ) . ' · ' . size_format( (int) @filesize( aw_geoip_file( 'city' ) ) ) . ' city, ' . size_format( (int) @filesize( aw_geoip_file( 'asn' ) ) ) . ' ASN' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
	);
	// wp aw geoip-test <ip>: what the visit block would say for an address.
	WP_CLI::add_command( 'aw geoip-test', fn( $args ) => WP_CLI::log( wp_json_encode( aw_visit_geo( (string) ( $args[0] ?? '' ) ) ) ) );
}
