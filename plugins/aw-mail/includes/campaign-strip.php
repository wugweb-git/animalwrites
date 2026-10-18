<?php
/**
 * Campaign strip under the signature (owner, 18 Oct 2026; design B "Countdown" chosen the same day): days left on a
 * deep-colour tab, the campaign title and closing date on its colour, and a white button to the campaign page. Built when an email is sent, so it follows the campaigns by
 * itself: the campaign whose window (aw_campaign_open … aw_campaign_close) includes today, closing soonest; no open
 * campaign, no strip. Added to replies from the Mail inbox and to site emails that carry a signature.
 *
 * @package aw-mail
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Campaign looks (campaign.css, .aw-camp--<name>), by campaign page slug: colour, deep colour, button, closing label,
 * and the second line: what the campaign is about, in the campaign page's own words (owner, 18 Oct 2026).
 */
const AW_MAIL_CAMPAIGN_LOOKS = array(
	'lab-animals-rules-2026'              => array( '#a3122f', '#6b0f22', 'Object now', 'Objections close', 'Labs could approve most animal tests themselves' ), // "Gazette crimson" (owner, 17 Oct 2026)
	'india-writes-for-its-community-dogs' => array( '#141414', '#000000', 'Take part', 'Closes', '' ),
);

/** The open campaign: [title, url, close date (Y-m-d), colour, deep colour, button, closing label], or null. */
function aw_mail_open_campaign() {
	$today = current_time( 'Y-m-d' );
	$q     = get_posts(
		array(
			'post_type'   => 'aw_campaign',
			'post_status' => 'publish',
			'numberposts' => 1,
			'meta_key'    => 'aw_campaign_close', // phpcs:ignore WordPress.DB.SlowDBQuery
			'orderby'     => 'meta_value',
			'order'       => 'ASC',
			'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array( 'key' => 'aw_campaign_open', 'value' => $today, 'compare' => '<=', 'type' => 'DATE' ),
				array( 'key' => 'aw_campaign_close', 'value' => $today, 'compare' => '>=', 'type' => 'DATE' ),
			),
		)
	);
	if ( ! $q ) {
		return null;
	}
	$p = $q[0];
	$look = AW_MAIL_CAMPAIGN_LOOKS[ $p->post_name ] ?? array( '#141414', '#000000', 'Take part', 'Closes', '' );
	return array( get_the_title( $p ), get_permalink( $p ), (string) get_post_meta( $p->ID, 'aw_campaign_close', true ), $look[0], $look[1], $look[2], $look[3], (string) ( $look[4] ?? '' ) );
}

/** Days left until the campaign closes (0 on the closing day), or null. */
function aw_mail_campaign_days_left( $close ) {
	if ( ! $close ) {
		return null;
	}
	$today = new DateTimeImmutable( current_time( 'Y-m-d' ) );
	$end   = new DateTimeImmutable( $close );
	return max( 0, (int) $today->diff( $end )->format( '%r%a' ) );
}

/** The strip as email HTML ('' when no campaign is open). */
function aw_mail_campaign_strip_html() {
	$c = aw_mail_open_campaign();
	if ( ! $c ) {
		return '';
	}
	$close = $c[2] ? wp_date( 'j M Y', strtotime( $c[2] . ' 12:00:00' ) ) : '';
	$days  = aw_mail_campaign_days_left( $c[2] );
	$tab   = null === $days ? '' : ( 0 === $days ? '<span style="font-size:13px;font-weight:bold;line-height:1.1">Last</span><br><span style="font-size:9px;letter-spacing:.06em">DAY</span>' : '<span style="font-size:18px;font-weight:bold;line-height:1">' . (int) $days . '</span><br><span style="font-size:9px;letter-spacing:.06em">' . ( 1 === $days ? 'DAY LEFT' : 'DAYS LEFT' ) . '</span>' );
	$font  = 'font-family:Arial,Helvetica,sans-serif;';
	return '<table cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;margin-top:12px;width:460px;max-width:100%"><tr>'
		. ( '' !== $tab ? '<td style="background:' . esc_attr( $c[4] ) . ';border-radius:4px 0 0 4px;padding:6px 12px;text-align:center;vertical-align:middle;' . $font . 'color:#ffffff;white-space:nowrap">' . $tab . '</td>' : '' )
		. '<td style="background:' . esc_attr( $c[3] ) . ';' . ( '' === $tab ? 'border-radius:4px 0 0 4px;' : '' ) . 'padding:7px 12px;' . $font . 'font-size:12px;line-height:1.35;color:#ffffff;vertical-align:middle"><a href="' . esc_url( $c[1] ) . '" style="color:#ffffff;text-decoration:none"><strong>' . esc_html( $c[0] ) . '</strong></a>' . ( '' !== $c[7] ? '<br><span style="font-size:11px">' . esc_html( $c[7] . ( $c[2] ? ' · closes ' . wp_date( 'j M', strtotime( $c[2] . ' 12:00:00' ) ) : '' ) ) . '</span>' : ( $close ? '<br><span style="font-size:11px">' . esc_html( $c[6] . ' ' . $close ) . '</span>' : '' ) ) . '</td>'
		. '<td style="background:' . esc_attr( $c[3] ) . ';border-radius:0 4px 4px 0;padding:7px 10px 7px 0;text-align:right;vertical-align:middle;white-space:nowrap"><a href="' . esc_url( $c[1] ) . '" style="display:inline-block;background:#ffffff;color:' . esc_attr( $c[3] ) . ';text-decoration:none;' . $font . 'font-size:11px;font-weight:bold;padding:6px 10px;border-radius:3px">' . esc_html( $c[5] ) . ' &rarr;</a></td>'
		. '</tr></table>';
}

/** The strip as one plain-text line ('' when none). */
function aw_mail_campaign_strip_text() {
	$c = aw_mail_open_campaign();
	if ( ! $c ) {
		return '';
	}
	$days = aw_mail_campaign_days_left( $c[2] );
	$left = null === $days ? '' : ( 0 === $days ? ' · last day' : ' · ' . $days . ( 1 === $days ? ' day left' : ' days left' ) );
	return $c[0] . ( '' !== $c[7] ? ' (' . strtolower( $c[7][0] ) . substr( $c[7], 1 ) . ')' : '' ) . $left . ( $c[2] ? ' · ' . strtolower( $c[6] ) . ' ' . wp_date( 'j M Y', strtotime( $c[2] . ' 12:00:00' ) ) : '' ) . ': ' . $c[1];
}

/** Put the strip right under the signature in an HTML email (or at the end when there is no signature block). */
function aw_mail_add_campaign_strip( $html ) {
	$strip = aw_mail_campaign_strip_html();
	if ( '' === $strip || false !== strpos( $html, $strip ) ) {
		return $html;
	}
	$pos = strpos( $html, 'class="signature"' );
	$end = false !== $pos ? strpos( $html, '</table>', $pos ) : false;
	return false !== $end ? substr_replace( $html, '</table>' . $strip, $end, 8 ) : $html . $strip;
}
