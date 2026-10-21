<?php
/**
 * Campaign strip under the signature (owner, 18 Oct 2026; design B "Countdown" chosen the same day): days left on a
 * deep-colour tab, the campaign title and closing date on its colour, and a white button to the campaign page. Built when an email is sent, so it follows the campaigns by
 * itself: the campaign whose window (aw_campaign_open … aw_campaign_close) includes today, closing soonest; no open
 * campaign, no strip. Added to replies from the Mail inbox and to site emails that carry a signature.
 *
 * @package animal-writes-core
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

/**
 * The strip as email HTML ('' when no campaign is open). Built for a phone first (owner, 21 Oct 2026): the days left and
 * the campaign's words side by side on the full width, the button under them across the full width at 44px; plain
 * tables with bgcolor as well as background, so Gmail's dark mode keeps the colours and no media query is needed.
 */
function aw_mail_campaign_strip_html() {
	$c = aw_mail_open_campaign();
	if ( ! $c ) {
		return '';
	}
	$close = $c[2] ? wp_date( 'j M Y', strtotime( $c[2] . ' 12:00:00' ) ) : '';
	$days  = aw_mail_campaign_days_left( $c[2] );
	$font  = 'font-family:Arial,Helvetica,sans-serif;';
	$tab   = null === $days ? '' : ( 0 === $days ? '<span style="font-size:20px;font-weight:bold;line-height:1.1">Last</span><br><span style="font-size:11px;letter-spacing:.08em">DAY</span>' : '<span style="font-size:30px;font-weight:bold;line-height:1">' . (int) $days . '</span><br><span style="font-size:11px;letter-spacing:.08em">' . ( 1 === $days ? 'DAY LEFT' : 'DAYS LEFT' ) . '</span>' );
	$words = '<a href="' . esc_url( $c[1] ) . '" style="color:#ffffff;text-decoration:none"><strong style="font-size:17px;line-height:1.3">' . esc_html( $c[0] ) . '</strong></a>'
		. ( '' !== $c[7] ? '<br><span style="font-size:14px;line-height:1.45">' . esc_html( $c[7] ) . '</span>' : '' )
		. ( $close ? '<br><span style="font-size:13px;line-height:1.6">' . esc_html( $c[6] . ' ' . $close ) . '</span>' : '' );
	$cols  = '' !== $tab ? 2 : 1;
	return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" bgcolor="' . esc_attr( $c[3] ) . '" style="border-collapse:separate;margin-top:16px;max-width:480px;background:' . esc_attr( $c[3] ) . ';border-radius:8px">'
		. '<tr>' . ( '' !== $tab ? '<td width="88" bgcolor="' . esc_attr( $c[4] ) . '" style="width:88px;background:' . esc_attr( $c[4] ) . ';border-radius:8px 0 0 0;padding:16px 8px;text-align:center;vertical-align:middle;' . $font . 'color:#ffffff">' . $tab . '</td>' : '' )
		. '<td bgcolor="' . esc_attr( $c[3] ) . '" style="background:' . esc_attr( $c[3] ) . ';' . ( '' === $tab ? 'border-radius:8px 8px 0 0;' : 'border-radius:0 8px 0 0;' ) . 'padding:16px;vertical-align:middle;' . $font . 'color:#ffffff">' . $words . '</td></tr>'
		. '<tr><td colspan="' . (int) $cols . '" bgcolor="' . esc_attr( $c[3] ) . '" style="background:' . esc_attr( $c[3] ) . ';border-radius:0 0 8px 8px;padding:0 16px 16px"><a href="' . esc_url( $c[1] ) . '" style="display:block;background:#141414;color:#ffffff;border:2px solid #ffffff;border-radius:6px;text-decoration:none;text-align:center;' . $font . 'font-size:16px;font-weight:bold;line-height:20px;padding:12px 16px">' . esc_html( $c[5] ) . ' &rarr;</a></td></tr></table>';
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
