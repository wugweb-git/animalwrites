<?php
/**
 * Mailbox profiles (owner, 18 Oct 2026): each mailbox's display name, signature and picture, as set in Hostinger
 * webmail (the Mail API has no endpoint for them, so they are copied in here: Settings → Incoming mail → Profiles).
 *
 * - Mailweb: the picture and name head each mailbox on the Mail page and widget.
 * - Site emails: an email the site sends from one of these addresses (normally noreply@) leaves with that mailbox's
 *   display name, and its signature is added at the end (HTML emails get the HTML signature, plain-text emails a text
 *   version). Only for emails to people outside: notices to our own addresses (contact@, the admin email) get none.
 *   FluentCRM emails are left alone, as is anything sent with the header "X-AW-No-Signature: 1".
 * Empty fields change nothing.
 *
 * @package aw-mail
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Every address with a profile: the mailboxes and their aliases (contact@), key => address. */
function aw_mail_profile_addresses() {
	$out = aw_mail_all_boxes();
	foreach ( array_keys( function_exists( 'aw_mail_aliases' ) ? aw_mail_aliases() : array() ) as $a ) {
		$out[ 'alias-' . sanitize_key( strstr( $a, '@', true ) ) ] = $a;
	}
	return $out;
}

/** One address's profile (mailbox or alias): name, signature (HTML), picture (attachment ID). */
function aw_mail_profile( $address ) {
	$all = (array) get_option( 'aw_mail_profiles', array() );
	return wp_parse_args( (array) ( $all[ strtolower( (string) $address ) ] ?? array() ), array( 'name' => '', 'signature' => '', 'picture' => 0 ) );
}

/** The profile picture as an <img> ('' when none). */
function aw_mail_profile_picture( $address, $size = 32 ) {
	$id = (int) aw_mail_profile( $address )['picture'];
	return $id ? wp_get_attachment_image( $id, array( $size, $size ), false, array( 'style' => 'border-radius:50%;vertical-align:middle;margin-right:8px;width:' . (int) $size . 'px;height:' . (int) $size . 'px;object-fit:cover', 'alt' => '' ) ) : '';
}

/* ---------- Site emails: display name and signature of the sending mailbox ---------- */

/** Is the email being sent a FluentCRM one? */
function aw_mail_profile_is_crm() {
	foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 40 ) as $f ) { // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		if ( 0 === strpos( (string) ( $f['class'] ?? '' ), 'FluentCrm\\' ) ) {
			return true;
		}
	}
	return false;
}

add_filter(
	'wp_mail_from_name',
	function ( $name ) {
		$p = aw_mail_profile( apply_filters( 'wp_mail_from', '' ) );
		return '' !== trim( $p['name'] ) && ! aw_mail_profile_is_crm() ? $p['name'] : $name;
	},
	20
);

/**
 * The site's email layout (owner, 20 Oct 2026: every email styled, with the signature): the message in a white card,
 * then the sending mailbox's signature (logo) and the open campaign's strip.
 *
 * @param string $inner Message HTML.
 * @param string $sig   Signature HTML ('' for none).
 */
function aw_mail_layout( $inner, $sig ) {
	$block = ( '' !== $sig ? '<div class="signature" style="margin-top:28px">' . wp_kses_post( $sig ) . '</div>' : '' ) . ( function_exists( 'aw_mail_campaign_strip_html' ) ? aw_mail_campaign_strip_html() : '' );
	return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
		. '<body style="margin:0;padding:24px 12px;background:#f4f4f4">'
		. '<div style="max-width:600px;margin:0 auto;background:#ffffff;padding:28px 24px;font:15px/1.6 Arial,Helvetica,sans-serif;color:#1a1a1a">'
		. $inner . $block . '</div></body></html>';
}

/** Plain text as HTML: escaped, links clickable, paragraphs kept. */
function aw_mail_text_to_html( $text ) {
	return wpautop( make_clickable( esc_html( str_replace( "\r\n", "\n", (string) $text ) ) ) );
}

add_filter(
	'wp_mail',
	function ( $atts ) {
		$h     = $atts['headers'] ?? '';
		$lines = is_array( $h ) ? $h : explode( "\n", str_replace( "\r\n", "\n", (string) $h ) );
		$keep  = array();
		$skip  = false;
		$html  = false;
		foreach ( $lines as $line ) {
			if ( is_string( $line ) && preg_match( '/^X-AW-No-Signature:/i', trim( $line ) ) ) {
				$skip = true;
				continue;
			}
			if ( is_string( $line ) && preg_match( '/^Content-Type:\s*text\/html/i', trim( $line ) ) ) {
				$html = true;
			}
			$keep[] = $line;
		}
		if ( $skip ) {
			$atts['headers'] = is_array( $h ) ? $keep : implode( "\n", $keep );
			return $atts;
		}
		if ( ! empty( $GLOBALS['aw_mail_flushing'] ) ) {
			return $atts; // a queued email was laid out when it was queued (mail-cap.php)
		}
		// Every email, to anyone (notices to ourselves too, FluentCRM too), carries the signature (owner, 20 Oct 2026).
		$sig = trim( (string) aw_mail_profile( apply_filters( 'wp_mail_from', '' ) )['signature'] );
		$msg = (string) ( $atts['message'] ?? '' );
		if ( false !== strpos( $msg, 'class="signature"' ) ) {
			return $atts; // already signed (inbox, or a second pass)
		}
		$html = $html || 'text/html' === apply_filters( 'wp_mail_content_type', 'text/plain' ) || (bool) preg_match( '/<(html|body|p|div|table|br)\b/i', $msg );
		if ( $html && false !== stripos( $msg, '</body>' ) ) {
			// A complete HTML email with its own design (FluentCRM, Fluent Forms): signature and strip before </body>.
			$block           = ( '' !== $sig ? '<div class="signature" style="margin:28px auto 0;max-width:600px;font:15px/1.6 Arial,Helvetica,sans-serif">' . wp_kses_post( $sig ) . '</div>' : '' ) . ( function_exists( 'aw_mail_campaign_strip_html' ) ? aw_mail_campaign_strip_html() : '' );
			$atts['message'] = str_ireplace( '</body>', $block . '</body>', $msg );
			return $atts;
		}
		// A fragment or plain text: in the site's layout, sent as HTML.
		$atts['message'] = aw_mail_layout( $html ? $msg : aw_mail_text_to_html( $msg ), $sig );
		if ( ! $html ) {
			$keep            = array_values( array_filter( $keep, fn( $l ) => ! is_string( $l ) || ! preg_match( '/^Content-Type:/i', trim( $l ) ) ) );
			$keep[]          = 'Content-Type: text/html; charset=UTF-8';
			$atts['headers'] = is_array( $h ) ? $keep : implode( "\n", $keep );
		}
		return $atts;
	},
	15
);

/* ---------- Settings → Incoming mail: profiles ---------- */

add_action(
	'aw_mail_settings_after',
	function () {
		wp_enqueue_media();
		$saved = isset( $_GET['aw_profile'] ); // phpcs:ignore WordPress.Security.NonceVerification
		echo '<h2 id="profiles">Profiles</h2>';
		if ( $saved ) {
			echo '<div class="notice notice-success inline"><p>Profiles saved.</p></div>';
		}
		echo '<p>Each mailbox\'s display name, signature and picture, as set in Hostinger webmail (Settings → Identities / Signature). The Mail page shows them; an email the site sends from that address uses the name and ends with the signature (FluentCRM emails excepted).</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'aw_mail_profile' );
		echo '<input type="hidden" name="action" value="aw_mail_profile">';
		foreach ( aw_mail_profile_addresses() as $key => $addr ) {
			$p = aw_mail_profile( $addr );
			echo '<h3>' . aw_mail_profile_picture( $addr ) . esc_html( $addr ) . '</h3><table class="form-table">'; // phpcs:ignore WordPress.Security.EscapeOutput -- image markup from core
			echo '<tr><th scope="row"><label for="aw-p-name-' . esc_attr( $key ) . '">Display name</label></th><td><input class="regular-text" id="aw-p-name-' . esc_attr( $key ) . '" name="p[' . esc_attr( $key ) . '][name]" value="' . esc_attr( $p['name'] ) . '"></td></tr>';
			echo '<tr><th scope="row"><label for="aw-p-sig-' . esc_attr( $key ) . '">Signature (HTML)</label></th><td><textarea class="large-text code" rows="5" id="aw-p-sig-' . esc_attr( $key ) . '" name="p[' . esc_attr( $key ) . '][signature]">' . esc_textarea( $p['signature'] ) . '</textarea></td></tr>';
			echo '<tr><th scope="row"><label for="aw-p-pic-' . esc_attr( $key ) . '">Picture</label></th><td><input type="number" min="0" class="small-text" id="aw-p-pic-' . esc_attr( $key ) . '" name="p[' . esc_attr( $key ) . '][picture]" value="' . (int) $p['picture'] . '"> <button type="button" class="button" data-aw-pick="aw-p-pic-' . esc_attr( $key ) . '">Choose from Media</button><p class="description">Media library item (0 = none).</p></td></tr>';
			echo '</table>';
		}
		submit_button( 'Save profiles' );
		echo '</form>';
		echo "<script>document.querySelectorAll('[data-aw-pick]').forEach(function(b){b.addEventListener('click',function(){var f=wp.media({title:'Picture',library:{type:'image'},multiple:false});f.on('select',function(){document.getElementById(b.dataset.awPick).value=f.state().get('selection').first().id;});f.open();});});</script>";
	},
	7
);

add_action(
	'admin_post_aw_mail_profile',
	function () {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'aw_mail_profile' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$boxes = aw_mail_profile_addresses();
		$all   = (array) get_option( 'aw_mail_profiles', array() );
		foreach ( (array) wp_unslash( $_POST['p'] ?? array() ) as $key => $v ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- each field sanitised below
			if ( isset( $boxes[ $key ] ) && is_array( $v ) ) {
				$all[ $boxes[ $key ] ] = array(
					'name'      => sanitize_text_field( (string) ( $v['name'] ?? '' ) ),
					'signature' => wp_kses_post( (string) ( $v['signature'] ?? '' ) ),
					'picture'   => absint( $v['picture'] ?? 0 ),
				);
			}
		}
		update_option( 'aw_mail_profiles', $all, false );
		wp_safe_redirect( admin_url( 'options-general.php?page=aw-mail-in&aw_profile=1#profiles' ) );
		exit;
	}
);
