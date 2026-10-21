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
 * The phone rules for the Lab Animal Rules strip (767px and under): the three cells stack full width. Gmail reads style
 * rules from the head only, so the Mailweb layout prints this there (`mailweb_head_html`); the strip also carries a copy for
 * clients that read the body.
 */
function aw_mail_strip_css() {
	return '<style>@media only screen and (max-width:767px){table[style*="max-width:560px"] td{display:block !important;width:auto !important;box-sizing:border-box !important;text-align:left !important;padding:12px 14px !important}table[style*="max-width:560px"] td[align="center"]:first-child{text-align:left !important}table[style*="max-width:560px"] td a[style*="inline-block"]{display:block !important;text-align:center !important;font-size:15px !important;padding:12px 14px !important}table[style*="max-width:560px"] td a div{font-size:16px !important;line-height:1.35 !important}table[style*="max-width:560px"] td a div+div{font-size:14px !important}table[style*="max-width:560px"] td:first-child div:first-child{font-size:26px !important}table[style*="max-width:560px"] td:first-child div+div{font-size:11px !important}}</style>';
}

add_filter(
	'mailweb_head_html',
	fn( $html ) => $html . ( aw_mail_open_campaign() && false !== strpos( (string) aw_mail_open_campaign()[1], '/campaigns/lab-animals-rules-2026/' ) ? aw_mail_strip_css() : '' )
);

/**
 * The Lab Animal Rules strip exactly as the owner wrote it (21 Oct 2026: "use the html as is"); not rebuilt or edited here.
 * Other campaigns use the generated strip below.
 */
const AW_MAIL_STRIP_LAB_RULES = <<<'AWHTML'
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:560px;border-collapse:collapse;font-family:Arial,Helvetica,sans-serif;">
  <tr>
    <td width="76" align="center" valign="middle" style="width:76px;background:#6B0F22;color:#FFFFFF;padding:8px 6px;">
      <div style="font-size:20px;font-weight:bold;line-height:1.1;">5 NOV</div>
      <div style="font-size:9px;letter-spacing:1px;margin-top:4px;">DEADLINE</div>
    </td>
    <td valign="middle" style="background:#A3122F;padding:10px 12px;color:#FFFFFF;">
      <a href="https://animalwrites.in/campaigns/lab-animals-rules-2026/" target="_blank" style="color:#FFFFFF;text-decoration:none;">
        <div style="font-size:12px;font-weight:bold;line-height:1.35;">
          Labs could soon approve their own animal experiments.
        </div>
        <div style="font-size:10px;line-height:1.4;margin-top:3px;">
          Object to the Draft Lab Animal Rules 2026
        </div>
      </a>
    </td>
    <td valign="middle" align="center" style="background:#A3122F;padding:8px 10px 8px 0;white-space:nowrap;">
      <a href="https://animalwrites.in/campaigns/lab-animals-rules-2026/" target="_blank" style="display:inline-block;background:#FFFFFF;color:#A3122F;text-decoration:none;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:bold;padding:8px 10px;border-radius:3px;">
        Object now &rarr;
      </a>
    </td>
  </tr>
</table>
AWHTML;

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
	if ( false !== strpos( (string) $c[1], '/campaigns/lab-animals-rules-2026/' ) ) {
		// Only the date block changes, counting down each day (owner, 21 Oct 2026: "the date should change"); the owner's HTML is otherwise untouched.
		$days = aw_mail_campaign_days_left( $c[2] );
		$html = AW_MAIL_STRIP_LAB_RULES;
		if ( null !== $days ) {
			$html = str_replace( array( '>5 NOV<', '>DEADLINE<' ), 0 === $days ? array( '>LAST<', '>DAY<' ) : array( '>' . (int) $days . '<', 1 === $days ? '>DAY LEFT<' : '>DAYS LEFT<' ), $html );
		}
		// On a phone the three cells stack: the date block, the words, the button, each the full width (the markup is not edited; this sheet only restyles it).
		$css = aw_mail_strip_css();
		return '<div style="margin-top:16px">' . $css . $html . '</div>';
	}
	$close = $c[2] ? wp_date( 'j M Y', strtotime( $c[2] . ' 12:00:00' ) ) : '';
	$days  = aw_mail_campaign_days_left( $c[2] );
	$font  = 'font-family:Arial,Helvetica,sans-serif;';
	$tab   = null === $days ? '' : ( 0 === $days ? '<span style="font-size:20px;font-weight:bold;line-height:1.1">Last</span><br><span style="font-size:11px;letter-spacing:.08em">DAY</span>' : '<span style="font-size:30px;font-weight:bold;line-height:1">' . (int) $days . '</span><br><span style="font-size:11px;letter-spacing:.08em">' . ( 1 === $days ? 'DAY LEFT' : 'DAYS LEFT' ) . '</span>' );
	$words = '<a href="' . esc_url( $c[1] ) . '" style="color:#ffffff;text-decoration:none"><strong style="font-size:17px;line-height:1.3">' . esc_html( $c[0] ) . '</strong></a>'
		. ( '' !== $c[7] ? '<br><span style="font-size:14px;line-height:1.45">' . esc_html( $c[7] ) . '</span>' : '' )
		. ( $close ? '<br><span style="font-size:13px;line-height:1.6">' . esc_html( $c[6] . ' ' . $close ) . '</span>' : '' );
	$cols  = '' !== $tab ? 2 : 1;
	return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" bgcolor="' . esc_attr( $c[3] ) . '" style="border-collapse:separate;margin-top:16px;max-width:767px;background:' . esc_attr( $c[3] ) . ';border-radius:8px">'
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
