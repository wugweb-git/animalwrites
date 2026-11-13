<?php
/**
 * Review-queue rules checks, run by tests/run.php: aw_ar_decide() (autoresolve-model.php) on the real rows the owner
 * pointed at on 13 and 14 Nov 2026, and on the cases around them.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) && 'cli' !== PHP_SAPI ) { // web requests stop here; the Deploy check runs this from the command line
	exit;
}

/**
 * The decision's act.
 *
 * @param string $field Field.
 * @param string $now   Current.
 * @param string $value Waiting.
 * @param string $conf  Confidence.
 * @param array  $ctx   Context.
 * @return string
 */
function aw_ar_act( $field, $now, $value, $conf = 'medium', array $ctx = array() ) {
	return aw_ar_decide( $field, $now, $value, $conf, $ctx )['act'];
}

// ---- addresses ----
aw_t( 'reject' === aw_ar_act( 'address', '214/2/12, New Hyderabad, Hasanganj, Lucknow 226007', '214/2/12, New Hyderabad, Hasanganj, Lucknow' ), 'address: the same address without the PIN adds nothing' );
aw_t( 'approve' === aw_ar_act( 'address', 'Post Mansa 385845', 'Panjarapole Chowk, Mansa, District Gandhinagar, Gujarat (Sajjanpura)' ), 'address: a more specific one that keeps the place is approved' );
aw_t( 'approve' === aw_ar_act( 'address', 'Dist.Vety.Centre Campus, Thevally', 'Dist.Vety.Centre Campus, Olai, Thevally Kollam – 691 009, Kerala, India.', 'low' ), 'address: the fuller one is approved, even from a low-tier source' );
aw_t( 'leave' === aw_ar_act( 'address', 'Dist.Vety.Centre Campus, Thevally', 'Thevally, Amman Nagar 137A, Pattathanam, Kollam' ), 'address: a different street at medium confidence is left for the owner' );
aw_t( 'approve' === aw_ar_act( 'address', '305 Nandini Garden, Fort', 'No.305, Nandhini Garden, Fort, Thiruvananthapuram' ), 'address: a spelling variant (Nandini, Nandhini) and a "No." prefix still count as the same place' );
aw_t( 'reject' === aw_ar_act( 'address', 'SPCA Complex, Ramanayyapeta, 533003', '26-1-17, Temple Street Kakinada – 533001 Andhra Pradesh, India', 'low' ), 'address: a different PIN is another place' );
aw_t( 'reject' === aw_ar_act( 'address', '12 Main Road, Fort 400001', '12 Main Road, Fort, Mumbai Maharashtra 400002' ), 'address: a longer address with another PIN is another place, not a fuller one' );
aw_t( 'reject' === aw_ar_act( 'address', 'Thadepallikudem', '788, Kalaignar Karunanidhi Street Santhoshapuram, Chennai – 601302, Tamil Nadu, India.', 'low' ), 'address: a low-tier source that differs keeps the current address' );
aw_t( 'leave' === aw_ar_act( 'address', 'Thadepallikudem', '788, Kalaignar Karunanidhi Street Santhoshapuram, Chennai', 'medium' ), 'address: a medium-tier difference with no PIN is left' );
aw_t( 'leave' === aw_ar_act( 'address', '', 'Anywhere Road, Pune' ), 'address: an empty field follows the plugin\'s own rules' );

// ---- PIN code ----
aw_t( 'reject' === aw_ar_act( 'pincode', '226007', '226 007' ), 'pincode: the same digits' );
aw_t( 'leave' === aw_ar_act( 'pincode', '226007', '226008' ), 'pincode: different digits are left' );
aw_t( '226007' === aw_ar_pin( 'Hasanganj, Lucknow 226 007' ) && '' === aw_ar_pin( 'No PIN here 12345' ), 'pin: found with or without a space, and a 5-digit number is not a PIN' );

// ---- phone ----
aw_t( 'approve' === aw_ar_act( 'phone', '9880194757, 74831 51270', '+91 90350 40694', 'low' ), 'phone: a low-tier number is added as an extra' );

// ---- social links ----
aw_t( 'approve' === aw_ar_act( 'facebook', 'https://www.facebook.com/people', 'https://www.facebook.com/people/Sri-Morbi-Panjrapol-Trust/100084151012690' ), 'facebook: a current link with no page name is replaced' );
aw_t( 'approve' === aw_ar_act( 'facebook', 'https://www.facebook.com/pages', 'https://www.facebook.com/NESTARPF' ), 'facebook: /pages alone is not a page' );
aw_t( 'reject' === aw_ar_act( 'facebook', 'https://www.facebook.com/missionpossiblepune', 'https://www.facebook.com/missionpossiblepune/about' ), 'facebook: the same page with /about is the same link' );
aw_t( 'reject' === aw_ar_act( 'facebook', 'https://www.facebook.com/drsashanka', 'https://www.facebook.com/drsashanka/posts/thank-you-alia-bhatt' ), 'facebook: a post is not the page' );
aw_t( 'reject' === aw_ar_act( 'instagram', 'https://www.instagram.com/agonda_animal_care', 'https://instagram.com/agonda.animal.care/' ), 'instagram: the same handle with dots or underscores' );
aw_t( 'leave' === aw_ar_act( 'instagram', 'https://www.instagram.com/agondaanimalcare/', 'https://www.instagram.com/agonda_animal_network', 'medium' ), 'instagram: a different handle at medium confidence is left' );
aw_t( 'reject' === aw_ar_act( 'instagram', 'https://www.instagram.com/agondaanimalcare/', 'https://www.instagram.com/other_shelter', 'low' ), 'instagram: a low-tier different handle keeps the current one' );

// ---- website, donate, adopt ----
aw_t( 'reject' === aw_ar_act( 'website', 'https://www.example.org/', 'http://example.org/contact' ), 'website: the same site' );
aw_t( 'approve' === aw_ar_act( 'donate_url', 'https://www.jeevaashraya.com/our-expenses', 'https://www.jeevaashraya.com/donate-now' ), 'donate: the page that names donating replaces one that does not' );
aw_t( 'reject' === aw_ar_act( 'donate_url', 'https://alairescues.in/donate-now/', 'https://alairescues.in/' ), 'donate: the home page is not a donate link' );
aw_t( 'reject' === aw_ar_act( 'donate_url', 'https://www.guardianofangels.org/Home/DonationForm', 'https://www.guardianofangels.org/home/gaudan' ), 'donate: both are donation pages, keep the current one' );
aw_t( 'reject' === aw_ar_act( 'adopt_url', 'https://x.org/adopt', 'https://x.org/adoption' ), 'adopt: a different adopt page keeps the current one' );

// ---- email ----
aw_t( 'approve' === aw_ar_act( 'email', 'wildce4r@gmail.com', 'wildcer@gmail.com', 'medium', array( 'own_site' => true, 'site_stem' => 'wildcer' ) ), 'email: a typo fixed from the organisation\'s own website name' );
aw_t( 'reject' === aw_ar_act( 'email', 'wildce4r@gmail.com', 'wildcer@gmail.com', 'medium', array( 'own_site' => false, 'site_stem' => 'wildcer' ) ), 'email: the same near-identical pair from another source is not a clear fix' );
aw_t( 'reject' === aw_ar_act( 'email', 'contact.worldfornature@gmail.com', '20contact.worldfornature@gmail.com', 'medium', array( 'own_site' => true, 'site_stem' => 'worldfornature' ) ), 'email: a stray "20" prefix does not replace the good address' );
aw_t( 'approve' === aw_ar_act( 'email', 'latha2000bc@yaho.com', 'latha2000bc@yahoo.com' ), 'email: a typo in the domain is fixed' );
aw_t( 'leave' === aw_ar_act( 'email', 'info@a.org', 'office@b.org' ), 'email: a different address is left' );
aw_t( 'leave' === aw_ar_act( 'email', 'someone@gmail.com', 'someone@gmail.co' ), 'email: a near-identical domain that is not a known mail domain is left' );
aw_t( 'approve' === aw_ar_act( 'email', 'info@wildcre.org', 'info@wildcer.org', 'medium', array( 'site_host' => 'wildcer.org' ) ), 'email: a domain typo fixed to the organisation\'s own website domain' );
aw_t( 'reject' === aw_ar_act( 'email', 'Info@A.org', 'info@a.org' ), 'email: the same address in another case' );

// ---- fields without a rule ----
aw_t( 'leave' === aw_ar_act( 'founded', '1998', '1999' ), 'a field without a rule is left' );

// ---- the rules as rows (add, edit, delete, reorder, switch off) ----
$ar_rows = aw_ar_default_rules();
aw_t( 25 === count( $ar_rows ) && 'address-pin_differs' === $ar_rows[1]['id'], 'rules: the built-in list has 25 rows with stable ids' );
$ar_cond = aw_ar_conditions();
$ar_bad  = array_filter( $ar_rows, fn( $r ) => ! isset( $ar_cond[ $r['cond'] ] ) || ! in_array( $r['field'], $ar_cond[ $r['cond'] ]['fields'], true ) || ! isset( AW_AR_ACTIONS[ $r['action'] ] ) );
aw_t( ! $ar_bad, 'rules: every built-in row uses a condition that fits its field and a known action' );
$ar_off = array_map( fn( $r ) => 'address-fuller' === $r['id'] ? array_merge( $r, array( 'on' => false ) ) : $r, $ar_rows );
aw_t( 'leave' === aw_ar_decide( 'address', '305 Nandini Garden, Fort', 'No.305, Nandhini Garden, Fort, Thiruvananthapuram', 'medium', array(), $ar_off )['act'], 'rules: switching a rule off lets the value fall through to a person' );
$ar_edit = array_map( fn( $r ) => 'phone-any' === $r['id'] ? array_merge( $r, array( 'action' => 'leave' ) ) : $r, $ar_rows );
aw_t( 'leave' === aw_ar_decide( 'phone', '9880194757', '+91 90350 40694', 'low', array(), $ar_edit )['act'], 'rules: an edited rule changes the action' );
$ar_none = array_values( array_filter( $ar_rows, fn( $r ) => 'phone' !== $r['field'] ) );
aw_t( 'leave' === aw_ar_decide( 'phone', '9880194757', '+91 90350 40694', 'low', array(), $ar_none )['act'], 'rules: a deleted rule no longer applies' );
$ar_new = array_merge( array( array( 'id' => 'email-any', 'field' => 'email', 'cond' => 'any', 'action' => 'reject', 'on' => true ) ), $ar_rows );
aw_t( 'reject' === aw_ar_decide( 'email', 'info@a.org', 'office@b.org', 'medium', array(), $ar_new )['act'], 'rules: an added rule that comes first decides' );
aw_t( 'leave' === aw_ar_decide( 'email', 'info@a.org', 'office@b.org', 'medium', array(), array_merge( $ar_rows, array( array( 'id' => 'x', 'field' => 'email', 'cond' => 'nonsense', 'action' => 'reject', 'on' => true ) ) ) )['act'], 'rules: a row with an unknown condition is ignored' );
aw_t( 'approve' === aw_ar_decide( 'instagram', 'https://www.instagram.com/p', 'https://www.instagram.com/realpage', 'medium', array(), $ar_rows )['act'], 'rules: the social rules cover Instagram too' );
