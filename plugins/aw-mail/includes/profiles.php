<?php
/**
 * Mailbox profiles (owner, 18 Oct 2026): each mailbox's display name, signature and picture, as set in Hostinger
 * webmail (the Mail API has no endpoint for them, so they are copied in here: Settings → Incoming mail → Profiles).
 *
 * - Wug Mail: the picture and name head each mailbox on the Mail page and widget.
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

/** One mailbox's profile: name, signature (HTML), picture (attachment ID). */
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
		$sig = trim( (string) aw_mail_profile( apply_filters( 'wp_mail_from', '' ) )['signature'] );
		if ( '' === $sig || ! empty( $GLOBALS['aw_mail_flushing'] ) || aw_mail_profile_is_crm() ) { // a queued email already has it (mail-cap.php)
			return $atts;
		}
		$to       = function_exists( 'aw_mail_addresses' ) ? aw_mail_addresses( $atts['to'] ?? '' ) : array();
		$internal = array_filter( $to, fn( $a ) => str_ends_with( $a, '@animalwrites.in' ) || strtolower( (string) get_option( 'admin_email' ) ) === $a );
		if ( ! $to || count( $internal ) === count( $to ) ) {
			return $atts; // a notice to ourselves
		}
		$msg  = (string) ( $atts['message'] ?? '' );
		$html = $html || 'text/html' === apply_filters( 'wp_mail_content_type', 'text/plain' ) || (bool) preg_match( '/<(html|body|p|div|table|br)\b/i', $msg );
		if ( $html ) {
			$block           = '<div class="signature" style="margin-top:24px">' . wp_kses_post( $sig ) . '</div>';
			$atts['message'] = false !== stripos( $msg, '</body>' ) ? str_ireplace( '</body>', $block . '</body>', $msg ) : $msg . $block;
		} else {
			$text            = trim( html_entity_decode( wp_strip_all_tags( preg_replace( '/<br\s*\/?>|<\/p>|<\/div>/i', "\n", $sig ) ), ENT_QUOTES ) );
			$atts['message'] = rtrim( $msg ) . "\n\n-- \n" . $text . "\n";
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
		foreach ( aw_mail_all_boxes() as $key => $addr ) {
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
		$boxes = aw_mail_all_boxes();
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
