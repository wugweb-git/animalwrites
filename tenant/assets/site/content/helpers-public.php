<?php
/**
 * Helpers on the Volunteer page (owner, 13 Nov 2026: Volunteer form B4 and B5).
 *
 * - The Volunteer form has two optional ticks: "Show me as a helper on Animal Writes" (B4) and, once the first is
 *   ticked, "OK to contact me to verify my details" (B5, required then). Its FluentCRM link tags the contact
 *   helper-pending and fills the custom fields below.
 * - The owner checks the person in FluentCRM and swaps helper-pending for helper-approved, the same way as org-pending
 *   and org-approved (org-people.php).
 * - [aw_helpers] on the Volunteer page shows only approved contacts who ticked both boxes: first name, district and
 *   state, and the kinds of help they offer. Never a last name, email, phone or vehicle. Nothing shows when there is no
 *   one (the heading is not printed either).
 * - A person asks to be taken off through the Contact form (Remove my data), as for every other detail.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The labels of the Volunteer form's "How would you like to help?" values (forms/5-volunteer-sign-up.json). */
const AW_HELPERS_ROLES = array(
	'coordinate' => 'Coordinating volunteers',
	'letters'    => 'Writing letters',
	'rescue'     => 'Rescue, foster or transport',
	'creative'   => 'Design, writing, photos or social media',
	'legal'      => 'Legal or policy work',
);

/** The labels of the form's "What else can you do?" values. */
const AW_HELPERS_CAN_DO = array(
	'night' => 'Night emergency',
	'hold'  => 'Temporary hold',
	'vet'   => 'Transport to a vet',
);

/**
 * Turn a saved list ("rescue, letters") into labels, dropping anything not in the map.
 *
 * @param string $saved Comma-separated values.
 * @param array  $map   value => label.
 * @return string[]
 */
function aw_helpers_labels( $saved, array $map ) {
	$out = array();
	foreach ( array_filter( array_map( 'trim', explode( ',', (string) $saved ) ) ) as $v ) {
		if ( isset( $map[ $v ] ) ) {
			$out[] = $map[ $v ];
		}
	}
	return $out;
}

/**
 * The helpers to show: approved, both boxes ticked.
 *
 * @return array[] each array{name, place, offers}
 */
function aw_helpers_people() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$cache = array();
	if ( ! function_exists( 'aw_crm_on' ) || ! aw_crm_on() ) {
		return $cache;
	}
	$tag = FluentCrmApi( 'tags' )->getInstance()->where( 'slug', 'helper-approved' )->first();
	if ( ! $tag ) {
		return $cache;
	}
	foreach ( \FluentCrm\App\Models\Subscriber::filterByTags( array( (int) $tag->id ) )->limit( 500 )->get() as $c ) {
		$f = (array) $c->custom_fields();
		if ( '1' !== trim( (string) ( $f['vol_helper_public'] ?? '' ) ) || '1' !== trim( (string) ( $f['vol_verify_ok'] ?? '' ) ) ) {
			continue; // both ticks are needed; a later send with a box unticked sets it to 0 and takes the person off (forms-fluentforms.php)
		}
		$first = trim( (string) $c->first_name );
		if ( '' === $first ) {
			continue;
		}
		$place  = array_filter( array( trim( (string) ( $f['vol_district'] ?? '' ) ), trim( (string) ( $f['vol_state'] ?? '' ) ) ) );
		$offers = array_merge(
			aw_helpers_labels( $f['vol_help'] ?? '', AW_HELPERS_ROLES ),
			aw_helpers_labels( $f['vol_can_do'] ?? '', AW_HELPERS_CAN_DO )
		);
		$cache[] = array(
			'name'   => $first,
			'place'  => implode( ', ', $place ),
			'offers' => $offers,
		);
	}
	usort( $cache, fn( $a, $b ) => strcasecmp( $a['place'] . $a['name'], $b['place'] . $b['name'] ) );
	return $cache;
}

/**
 * The "Helpers" block.
 *
 * @return string HTML, '' when there is no one to show.
 */
function aw_helpers_html() {
	$people = aw_helpers_people();
	if ( ! $people ) {
		return '';
	}
	aw_use_component_style( 'aw-directory' ); // aw-org-links-group, aw-org-links
	$items = '';
	foreach ( array_slice( $people, 0, 60 ) as $p ) {
		$items .= '<li>' . esc_html( $p['name'] )
			. ( '' !== $p['place'] ? ', ' . esc_html( $p['place'] ) : '' )
			. ( $p['offers'] ? ' (' . esc_html( implode( '; ', $p['offers'] ) ) . ')' : '' ) . '</li>';
	}
	return '<div class="aw-org-links"><section class="aw-org-links-group aw-org-people"><h2>Helpers</h2><ul>' . $items . '</ul></section></div>';
}

add_shortcode(
	'aw_helpers',
	function () {
		return aw_helpers_html();
	}
);

/**
 * Once per version: the helper fields in FluentCRM's custom fields and the two tags (a site with no WP-CLI write
 * access cannot run `wp aw crm-setup`). Runs for an administrator in WP Admin only.
 */
add_action(
	'admin_init',
	function () {
		if ( '1' === get_option( 'aw_helpers_setup' ) || ! current_user_can( 'manage_options' ) || ! function_exists( 'aw_crm_on' ) || ! aw_crm_on() || ! function_exists( 'aw_crm_fields_setup' ) ) {
			return;
		}
		aw_crm_fields_setup();
		aw_crm_tag_ids( array( 'helper-pending', 'helper-approved' ) ); // created if missing, so the owner can set helper-approved
		update_option( 'aw_helpers_setup', '1', false );
	}
);
