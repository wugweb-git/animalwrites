<?php
/**
 * Campaign tags (UTM) on the site's own links in the site's emails (owner, 12 Nov 2026), so GA4 can tell a visit from
 * an email from a direct one.
 *
 * Every email the site sends through wp_mail (form confirmations, welcome and claim emails, notices) and every message
 * sent from Mail's Inbox: each link to the site's own address gets
 *   utm_source=site-email, utm_medium=email, utm_campaign={email type}.
 * - The type is the email's own "X-Mailweb-Email-Type: {type}" header when it has one (removed before sending), else
 *   "form-confirmation" for a Fluent Forms email, "claim-update" for one sent from the claims code, "inbox" for a
 *   message from the Inbox, else "site-notice". Filter: mailweb_email_type.
 * - The mailbox signature and what follows it (the after-signature block, quoted text) are left as they are.
 * - FluentCRM's emails are left alone: campaigns and automations carry their own tags (FluentCRM's UTM settings).
 * - A link that already has a utm_source keeps its own tags; links to other sites, mailto: and tel: are left as they
 *   are, and so are FluentCRM's tracking and unsubscribe links and wp-admin links.
 * - Only these fixed words and the type are added: never an address, a name or anything about the person (owner,
 *   21 Oct 2026).
 *
 * @package mailweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The type of the email being sent, from the call stack (as mailweb_priority() reads it).
 *
 * @return string '' for a FluentCRM email (left alone).
 */
function mailweb_email_type_from_stack() {
	$type = 'site-notice';
	foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 40 ) as $f ) { // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		$c = (string) ( $f['class'] ?? '' );
		if ( 0 === strpos( $c, 'FluentCrm\\' ) ) {
			return '';
		}
		if ( 0 === strpos( $c, 'FluentForm\\' ) ) {
			$type = 'form-confirmation';
		} elseif ( 'site-notice' === $type && preg_match( '#/includes/claims[\w-]*\.php$#', (string) ( $f['file'] ?? '' ) ) ) {
			$type = 'claim-update';
		}
	}
	return $type;
}

/**
 * One link with the tags added (unchanged when it is not the site's own, or already tagged).
 *
 * @param string $url  Link.
 * @param string $type Email type.
 * @return string
 */
function mailweb_utm_url( $url, $type ) {
	$raw  = html_entity_decode( (string) $url, ENT_QUOTES );
	$host = wp_parse_url( $raw, PHP_URL_HOST );
	$home = wp_parse_url( home_url(), PHP_URL_HOST );
	if ( ! $host || ! $home || strtolower( preg_replace( '/^www\./i', '', (string) $host ) ) !== strtolower( preg_replace( '/^www\./i', '', (string) $home ) ) ) {
		return $url;
	}
	if ( preg_match( '/[?&](utm_source|fluentcrm|ns_url)=|\/wp-admin\/|\/wp-login\.php|unsubscribe/i', $raw ) ) {
		return $url;
	}
	$tagged = add_query_arg(
		array(
			'utm_source'   => 'site-email',
			'utm_medium'   => 'email',
			'utm_campaign' => sanitize_title( $type ),
		),
		$raw
	);
	return $raw !== (string) $url ? esc_url( $tagged ) : $tagged; // an HTML-escaped link stays escaped
}

/**
 * The site's own links in an email body tagged: href attributes in HTML, bare addresses in plain text.
 *
 * @param string $body Email body.
 * @param string $type Email type.
 * @param bool   $html Whether the body is HTML.
 * @return string
 */
function mailweb_utm_body( $body, $type, $html ) {
	$body = (string) $body;
	if ( '' === $type || '' === $body ) {
		return $body;
	}
	if ( $html ) {
		// The message only: the mailbox signature (locked, owner 12 Nov 2026), what follows it and any quoted text keep
		// their links exactly as they are.
		$cut  = preg_match( '/<div\b[^>]*\bclass\s*=\s*["\']signature["\']/i', $body, $m, PREG_OFFSET_CAPTURE ) ? (int) $m[0][1] : strlen( $body );
		$head = (string) preg_replace_callback( '/(<a\b[^>]*?\bhref\s*=\s*)(["\'])(.*?)\2/is', fn( $m ) => $m[1] . $m[2] . mailweb_utm_url( $m[3], $type ) . $m[2], substr( $body, 0, $cut ) );
		return $head . substr( $body, $cut );
	}
	return (string) preg_replace_callback( '#https?://[^\s<>"\')\]]+#i', fn( $m ) => mailweb_utm_url( rtrim( $m[0], '.,;:' ), $type ) . substr( $m[0], strlen( rtrim( $m[0], '.,;:' ) ) ), $body );
}

// Site emails: after the layout (profiles.php, 15), before the queue (mail-cap.php, pre_wp_mail), so a queued email is
// stored tagged and its later send finds the tags already there.
add_filter(
	'wp_mail',
	function ( $atts ) {
		$h     = $atts['headers'] ?? '';
		$lines = is_array( $h ) ? $h : explode( "\n", str_replace( "\r\n", "\n", (string) $h ) );
		$keep  = array();
		$type  = null;
		$html  = false;
		foreach ( $lines as $line ) {
			if ( is_string( $line ) && preg_match( '/^X-Mailweb-Email-Type:\s*([\w-]{1,40})\s*$/i', trim( $line ), $m ) ) {
				$type = strtolower( $m[1] );
				continue;
			}
			if ( is_string( $line ) && preg_match( '/^Content-Type:\s*text\/html/i', trim( $line ) ) ) {
				$html = true;
			}
			$keep[] = $line;
		}
		if ( null !== $type ) {
			$atts['headers'] = is_array( $h ) ? $keep : implode( "\n", $keep );
		}
		if ( ! empty( $GLOBALS['mailweb_flushing'] ) ) {
			return $atts; // a queued email: tagged when it was queued
		}
		$type = (string) apply_filters( 'mailweb_email_type', null !== $type ? $type : mailweb_email_type_from_stack(), $atts );
		$msg  = (string) ( $atts['message'] ?? '' );
		$html = $html || (bool) preg_match( '/<(html|body|p|div|table|br|a)\b/i', $msg );
		$atts['message'] = mailweb_utm_body( $msg, $type, $html );
		return $atts;
	},
	16
);
