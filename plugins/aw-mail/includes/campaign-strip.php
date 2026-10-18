<?php
/**
 * Campaign strip under the signature (owner, 18 Oct 2026): a thin bar in the open campaign's own colour, with its
 * title and closing date, linking to the campaign page. Built when an email is sent, so it follows the campaigns by
 * itself: the campaign whose window (aw_campaign_open … aw_campaign_close) includes today, closing soonest; no open
 * campaign, no strip. Added to replies from the Mail inbox and to site emails that carry a signature.
 *
 * @package aw-mail
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Campaign colours (campaign.css, .aw-camp--<name>), by campaign page slug. */
const AW_MAIL_CAMPAIGN_COLOURS = array(
	'lab-animals-rules-2026'              => '#a3122f', // "Gazette crimson" (owner, 17 Oct 2026)
	'india-writes-for-its-community-dogs' => '#141414',
);

/** The open campaign: [title, url, close date (Y-m-d), colour], or null. */
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
	return array( get_the_title( $p ), get_permalink( $p ), (string) get_post_meta( $p->ID, 'aw_campaign_close', true ), AW_MAIL_CAMPAIGN_COLOURS[ $p->post_name ] ?? '#141414' );
}

/** The strip as email HTML ('' when no campaign is open). */
function aw_mail_campaign_strip_html() {
	$c = aw_mail_open_campaign();
	if ( ! $c ) {
		return '';
	}
	$close = $c[2] ? wp_date( 'j M Y', strtotime( $c[2] . ' 12:00:00' ) ) : '';
	return '<table cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;max-width:560px;margin-top:14px"><tr><td style="background:' . esc_attr( $c[3] ) . ';border-radius:4px;padding:7px 12px;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.35"><a href="' . esc_url( $c[1] ) . '" style="color:#ffffff;text-decoration:none;display:block"><strong>' . esc_html( $c[0] ) . '</strong>' . ( $close ? ' · Closes ' . esc_html( $close ) : '' ) . ' &rarr;</a></td></tr></table>';
}

/** The strip as one plain-text line ('' when none). */
function aw_mail_campaign_strip_text() {
	$c = aw_mail_open_campaign();
	if ( ! $c ) {
		return '';
	}
	return $c[0] . ( $c[2] ? ' · Closes ' . wp_date( 'j M Y', strtotime( $c[2] . ' 12:00:00' ) ) : '' ) . ': ' . $c[1];
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
