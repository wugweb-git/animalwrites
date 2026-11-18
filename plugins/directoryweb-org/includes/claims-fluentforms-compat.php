<?php
/**
 * Fluent Forms claim helpers (form ids, phone and social normalising). Loaded only when Fluent Forms is active; replaced by the native claim form in a later phase.
 *
 * @package directoryweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


const DIRECTORYWEB_FF_OPTION = 'directoryweb_ff_forms'; // key => Fluent Forms form ID
const DIRECTORYWEB_FF_FORMS  = array(
	'contact'     => array( 'title' => 'Contact', 'thanks' => 'contact', 'limit' => 5 ),
	'volunteer'   => array( 'title' => 'Volunteer sign-up', 'thanks' => 'volunteer', 'limit' => 5 ),
	'claim'       => array( 'title' => 'Claim this listing', 'thanks' => 'claim', 'limit' => 5 ),
	'report'      => array( 'title' => 'Report a mistake', 'thanks' => 'report', 'limit' => 10 ),
);
/** What the custom fields post (Fluent Forms keeps only fields it knows, plus these). */
/** Claim form social fields => [listing field, link before the username] (the username alone is typed). */
const DIRECTORYWEB_FF_SOCIAL = array(
	'l_instagram' => array( 'directoryweb_instagram', 'https://www.instagram.com/%s/' ),
	'l_facebook'  => array( 'directoryweb_facebook', 'https://www.facebook.com/%s' ),
	'l_x'         => array( 'directoryweb_x', 'https://x.com/%s' ),
	'l_youtube'   => array( 'directoryweb_youtube', 'https://www.youtube.com/@%s' ),
	'l_linkedin'  => array( 'directoryweb_linkedin', 'https://www.linkedin.com/company/%s' ),
);

/** Claim form dates: typed and shown in the site's format (e.g. "8 Jan 2025"), saved as Y-m-d. */
const DIRECTORYWEB_FF_DATES = array( 'l_reg_date', 'l_awbi_rec', 'l_awbi_ren', 'l_awbi_valid' );
/** Each social field's own hosts and the path before the username in its links. */
const DIRECTORYWEB_FF_SOCIAL_HOSTS = array(
	'l_instagram' => array( 'instagram\.com', '' ),
	'l_facebook'  => array( '(?:facebook|fb)\.com', '' ),
	'l_x'         => array( '(?:x|twitter)\.com', '' ),
	'l_youtube'   => array( 'youtube\.com', '@' ),
	'l_linkedin'  => array( 'linkedin\.com', 'company/' ), // a person's /in/ profile is not reduced: kept as a link
);
const DIRECTORYWEB_CORRECTION_FIELDS = array( 'picture', 'about', 'phone', 'email', 'address', 'hours' );

/**
 * A form, as Fluent Forms renders it, in the site's wrapper (the same classes, so the same look and analytics).
 * Without Fluent Forms or the form's ID (wp aw ff-form): nothing for visitors, a short note for administrators.
 *
 * @param string $key Form key (DIRECTORYWEB_FF_FORMS).
 * @param array  $ctx What the form is shown for: listing, item, reason, source, vol_contact.
 * @return string
 */
function directoryweb_ff_form( $key, $ctx = array() ) {
	$id = directoryweb_ff_id( $key );
	if ( ! $id ) {
		return directoryweb_ff_missing( DIRECTORYWEB_FF_FORMS[ $key ]['title'] ?? $key, 'wp aw ff-form ' . $key . ' <form ID>' );
	}
	directoryweb_use_component_style( 'directoryweb-forms' ); // the kit-form directoryweb-form-ff wrapper
	if ( 'volunteer' === $key ) {
		directoryweb_use_component_style( 'directoryweb-volunteer' ); // directoryweb-volunteer-form, directoryweb-vol-roles, directoryweb-vol-role
	}
	$GLOBALS['directoryweb_ff_ctx'] = (array) $ctx + array( 'key' => $key );
	$form                 = do_shortcode( '[fluentform id="' . $id . '"]' );
	$GLOBALS['directoryweb_ff_ctx'] = array();
	$attrs                = '';
	if ( 'contact' === $key ) { // the message placeholder follows the reason (aw.js); #write is the contact form's anchor
		$attrs = ' id="write" data-placeholders="' . esc_attr( wp_json_encode( DIRECTORYWEB_CONTACT_PLACEHOLDER ) ) . '"';
	}
	$listing = $ctx['listing'] ?? null;
	if ( $listing ) {
		$attrs .= ' data-key="' . esc_attr( (string) $listing->ID ) . '"';
	}
	return '<div class="kit-form directoryweb-form-ff directoryweb-' . esc_attr( str_replace( '_', '-', $key ) ) . '-form" data-kit-form="' . esc_attr( $key ) . '"' . $attrs . '>' . $form . '</div>';
}


/** The Fluent Forms form ID for a form key, or 0 (Fluent Forms off, or the form not set up: nothing shows). */
function directoryweb_ff_id( $key ) {
	if ( ! defined( 'FLUENTFORM' ) ) {
		return 0;
	}
	return (int) ( ( (array) get_option( DIRECTORYWEB_FF_OPTION, array() ) )[ $key ] ?? 0 );
}


/** An Indian number's 10 digits ('' when it is not one): +91, 91 or 0 before it, spaces and dashes allowed. */
function directoryweb_ff_phone10( $v ) {
	$d = preg_replace( '/\D+/', '', (string) $v );
	if ( 12 === strlen( $d ) && 0 === strpos( $d, '91' ) ) {
		$d = substr( $d, 2 );
	} elseif ( 11 === strlen( $d ) && '0' === $d[0] ) {
		$d = substr( $d, 1 );
	}
	return preg_match( '/^[1-9]\d{9}$/', $d ) ? $d : '';
}


/** A number as the site saves it: "+91 98765 43210"; anything else unchanged. */
function directoryweb_ff_phone_store( $v ) {
	$d = directoryweb_ff_phone10( $v );
	return '' !== $d ? '+91 ' . substr( $d, 0, 5 ) . ' ' . substr( $d, 5 ) : trim( (string) $v );
}


/** A submission's reference: the form's prefix and Fluent Forms' serial number for the entry (AW-12, AW-C-3 …). */
function directoryweb_ff_ref( $key, $entry_id ) {
	$prefix = array( 'contact' => 'DW-', 'volunteer' => 'DW-V-', 'claim' => 'DW-C-', 'report' => 'DW-R-' );
	$serial = (int) \FluentForm\App\Models\Submission::where( 'id', (int) $entry_id )->value( 'serial_number' );
	return $serial ? ( $prefix[ $key ] ?? 'DW-' ) . $serial : '';
}


/** A social link or "@name" as the username alone ('' when it is not a username or a link of that field's platform). */
function directoryweb_ff_social_user( $field, $v ) {
	$v = trim( (string) $v );
	if ( preg_match( '#^(?:https?://)?(?:[a-z]+\.)?[a-z.]+\.[a-z]+/#i', $v ) ) { // a link: only this platform's, in its username form
		$h = DIRECTORYWEB_FF_SOCIAL_HOSTS[ $field ] ?? null;
		if ( ! $h || ! preg_match( '#^(?:https?://)?(?:[a-z]+\.)?' . $h[0] . '/' . preg_quote( $h[1], '#' ) . '([A-Za-z0-9._-]{1,100})/?(?:[?\#].*)?$#i', $v, $m ) ) {
			return '';
		}
		$v = $m[1];
	}
	$v = ltrim( $v, '@' );
	return preg_match( '/^[A-Za-z0-9._-]{1,100}$/', $v ) ? $v : '';
}


/** Was a Fluent Forms tick box ticked? Fluent Forms saves nothing for an unticked box: empty is 0. */
function directoryweb_ff_ticked( $data, $name ) {
	return (bool) array_filter( (array) ( $data[ $name ] ?? array() ), fn( $v ) => '' !== (string) $v && '0' !== (string) $v );
}


/** A form that cannot show (Fluent Forms off, or its ID not set): nothing for visitors, a note for administrators. */
function directoryweb_ff_missing( $title, $fix ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return '';
	}
	return '<p class="directoryweb-form-missing"><strong>Only administrators see this:</strong> the ' . esc_html( $title ) . ' form is missing. '
		. ( defined( 'FLUENTFORM' ) ? 'Set its Fluent Forms ID: <code>' . esc_html( $fix ) . '</code>.' : 'Turn on Fluent Forms.' ) . '</p>';
}


/** The published listing a form page is about (?listing=), or null. */
function directoryweb_form_page_listing() {
	$listing = get_post( absint( wp_unslash( $_GET['listing'] ?? 0 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
	return $listing && 'directoryweb_listing' === $listing->post_type && 'publish' === $listing->post_status ? $listing : null;
}


/**
 * Report a mistake and Suggest an update link to the Contact form (no Report a mistake reason in the
 * menu): /contact/?about=general&item=<ID> for a listing, News, Library or Resource item, and &field= for an empty listing
 * field. The form fills the Message box from the link and saves the item's ID and path in the hidden fields report_item
 * and report_path (reports.php). Escape the result once, where it is printed.
 */
function directoryweb_report_url( $id, $field = '' ) {
	$field = in_array( $field, DIRECTORYWEB_CORRECTION_FIELDS, true ) ? $field : '';
	return add_query_arg( array_filter( array( 'about' => 'general', 'item' => (int) $id, 'field' => $field ) ), home_url( '/contact/' ) );
}

