<?php
/**
 * Block Bindings keys for Animal Writes: the values (aw_binding_value) and their rules. The source itself, the key
 * registry and the "leave out an empty bound block" rule are Kits Core's (kits-core/includes/bindings.php, source
 * "kits/post-field"); "animal-writes/field" stays registered as the same source under its saved name.
 *
 * Usage in a template:
 * <!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"animal-writes/field","args":{"key":"verified_label"}}}}} -->
 *
 * Blocks bound to an empty value are removed from the output, except the contact buttons on organisation pages, which
 * show greyed out (aria-disabled, not a link).
 *
 * @package kits-by-wugweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Format a Y-m-d date with the site's date format.
 *
 * @param string $ymd Date.
 * @return string
 */
function aw_format_date( $ymd ) {
	if ( ! $ymd ) {
		return '';
	}
	$ts = strtotime( $ymd );
	return $ts ? date_i18n( AW_DATE_FORMAT, $ts ) : '';
}

/**
 * Digits-only phone, Indian numbers normalised to 91XXXXXXXXXX.
 *
 * @param string $raw Raw number.
 * @return string
 */
function aw_normalise_phone( $raw ) {
	$digits = preg_replace( '/\D+/', '', (string) $raw );
	if ( '' === $digits ) {
		return '';
	}
	if ( strlen( $digits ) <= 5 ) {
		return $digits; // short helpline code such as 1962: dialled as is
	}
	$digits = ltrim( $digits, '0' ); // trunk prefix of STD landlines and mobiles
	if ( 10 === strlen( $digits ) ) {
		$digits = '91' . $digits;
	}
	return $digits;
}

/**
 * tel: link for a phone number (short codes stay local, others get +91).
 *
 * @param string $raw Raw number.
 * @return string
 */
function aw_tel_url( $raw ) {
	$d = aw_normalise_phone( $raw );
	if ( '' === $d ) {
		return '';
	}
	return 'tel:' . ( strlen( $d ) <= 5 ? '' : '+' ) . $d;
}

/**
 * Resolve a binding key to a display value.
 *
 * @param string $key     Field key.
 * @param int    $post_id Post ID.
 * @return string
 */
function aw_binding_value( $key, $post_id ) {
	$m = function ( $k ) use ( $post_id ) {
		return (string) get_post_meta( $post_id, $k, true );
	};

	switch ( $key ) {
		case 'source_url':
			return $m( 'aw_source_url' );
		case 'source_link_label':
			$name = $m( 'aw_source_name' );
			/* translators: %s: source name. */
			return $m( 'aw_source_url' ) ? ( $name ? sprintf( __( 'Read at %s', 'kits-by-wugweb' ), $name ) : __( 'Read at source', 'kits-by-wugweb' ) ) : '';
		case 'source_meta':
			return trim( implode( ', ', array_filter( array( $m( 'aw_source_name' ), aw_format_date( $m( 'aw_source_date' ) ) ) ) ) ); // no typed "·" (owner, 20 Oct 2026)
		case 'sponsored_label':
			if ( ! get_post_meta( $post_id, 'aw_sponsored', true ) ) {
				return '';
			}
			$sponsor = $m( 'aw_sponsor_name' );
			/* translators: %s: sponsor name. */
			return $sponsor ? sprintf( __( 'Sponsored by %s', 'kits-by-wugweb' ), $sponsor ) : __( 'Sponsored', 'kits-by-wugweb' );
		case 'verified_label':
			if ( ! get_post_meta( $post_id, 'aw_verified', true ) ) {
				return '';
			}
			if ( get_post_meta( $post_id, 'aw_needs_reverify', true ) ) {
				return __( 'Needs re-check', 'kits-by-wugweb' );
			}
			$on = aw_format_date( $m( 'aw_verified_on' ) );
			/* translators: %s: date. */
			return $on ? sprintf( __( 'Claimed on %s', 'kits-by-wugweb' ), $on ) : __( 'Claimed', 'kits-by-wugweb' ); // aw_verified = an approved claim
		case 'blacklisted_label':
			return get_post_meta( $post_id, 'aw_darpan_blacklisted_on', true ) ? __( 'Blacklisted on NGO Darpan', 'kits-by-wugweb' ) : ''; // the trust panel says by whom and from when
		case 'area':
			// Cards show the town, district and state only, never the street address (owner, 12 Oct 2026); the full
			// address is on the profile.
			$parts = function_exists( 'aw_listing_place' ) ? array_reverse( (array) aw_listing_place( $post_id ) ) : array();
			array_unshift( $parts, $m( 'aw_place_city' ) );
			$seen = array();
			foreach ( $parts as $p ) {
				$p = trim( (string) $p );
				if ( '' !== $p && ! in_array( mb_strtolower( $p ), array_map( 'mb_strtolower', $seen ), true ) ) {
					$seen[] = $p;
				}
			}
			return implode( ', ', $seen );
		case 'services':
			// Services are the organisation's own description (claim) or a named list's; never ours. Shown with who said it.
			if ( '' === $m( 'aw_services' ) ) {
				return '';
			}
			$src = $m( 'aw_services_source' );
			$on  = $m( 'aw_services_on' ) ? aw_format_date( $m( 'aw_services_on' ) ) : '';
			return 'Services: ' . $m( 'aw_services' ) . ( $src ? ' (as stated by ' . $src . ( $on ? ', ' . $on : '' ) . ')' : '' ); // escaped by the binding source
		case 'phone': // the first number of the Phone numbers list
			return function_exists( 'aw_listing_first_phone' ) ? aw_listing_first_phone( $post_id ) : '';
		case 'phone_url':
			return function_exists( 'aw_listing_first_phone' ) ? aw_tel_url( aw_listing_first_phone( $post_id ) ) : '';
		case 'whatsapp_url': // the first number marked WhatsApp
			$d = function_exists( 'aw_listing_first_phone' ) ? aw_normalise_phone( aw_listing_first_phone( $post_id, true ) ) : '';
			return strlen( $d ) > 5 ? 'https://wa.me/' . $d : '';
		case 'email_url':
			$e = $m( 'aw_email' );
			return $e ? 'mailto:' . $e : '';
		case 'website':
			return $m( 'aw_website' );
		case 'org_name':
			return $m( 'aw_org_name' );
		case 'apply_url': // empty once the opportunity is closed, so the Apply button is removed (owner, 18 Oct 2026)
			return function_exists( 'aw_retention_state' ) && 'closed' === aw_retention_state( $post_id ) ? '' : $m( 'aw_apply_url' );
		case 'deadline_label':
			$d = aw_format_date( $m( 'aw_deadline' ) );
			/* translators: %s: date. */
			return $d ? sprintf( __( 'Apply by %s', 'kits-by-wugweb' ), $d ) : '';
		case 'deadline':
			// "Apply by" with the one date component (aw_date: calendar icon directly before the date). HTML, kept by
			// the source's wp_kses_post. Empty without a deadline, so the bound block is removed.
			$d = $m( 'aw_deadline' );
			if ( $d && function_exists( 'aw_date' ) && function_exists( 'aw_retention_state' ) && 'closed' === aw_retention_state( $post_id ) ) {
				// Past its deadline: a visible "Closed" tag and the date it closed (owner, 18 Oct 2026).
				return '<span class="kit-tag kit-tag--closed">' . esc_html__( 'Closed', 'kits-by-wugweb' ) . '</span> ' . __( 'Applications closed on', 'kits-by-wugweb' ) . ' ' . aw_date( $d . ' 12:00:00' );
			}
			return $d ? __( 'Apply by', 'kits-by-wugweb' ) . ' ' . kits_date( $d . ' 12:00:00', AW_DATE_FORMAT, 'aw-date' ) : '';
		case 'file_url':
			return $m( 'aw_file_url' );
	}
	return '';
}

/*
 * The keys above, registered on Kits Core's binding registry (kits-core/includes/bindings.php), so templates can bind
 * them with the Kits source "kits/post-field". Same values and escaping as before: URLs esc_url, the deadline (the
 * date component) wp_kses_post, the rest esc_html.
 */
add_filter(
	'kits_post_fields',
	function ( $fields ) {
		$types = array(
			'source_url'        => 'url',
			'source_link_label' => 'text',
			'source_meta'       => 'text',
			'sponsored_label'   => 'text',
			'verified_label'    => 'text',
			'blacklisted_label' => 'text',
			'area'              => 'text',
			'services'          => 'text',
			'phone'             => 'text',
			'phone_url'         => 'url',
			'whatsapp_url'      => 'url',
			'email_url'         => 'url',
			'website'           => 'url',
			'org_name'          => 'text',
			'apply_url'         => 'url',
			'deadline_label'    => 'text',
			'deadline'          => 'html',
			'file_url'          => 'url',
		);
		$cb = fn( $post_id, $k ) => aw_binding_value( $k, $post_id );
		foreach ( $types as $key => $type ) {
			$fields[ $key ] = array(
				'callback' => $cb,
				'type'     => $type,
			);
		}
		return $fields;
	}
);

/*
 * "animal-writes/field", the source name saved in templates and patterns since Oct 2026: kept for good, reading the
 * same keys through Kits Core, and sharing Kits Core's rule that a block bound to an empty value is left out.
 */
add_filter( 'kits_post_field_sources', fn( $sources ) => array_merge( (array) $sources, array( 'animal-writes/field' ) ) );
add_action(
	'init',
	function () {
		if ( ! function_exists( 'register_block_bindings_source' ) ) {
			return;
		}
		register_block_bindings_source(
			'animal-writes/field',
			array(
				'label'              => __( 'Animal Writes field', 'kits-by-wugweb' ),
				'uses_context'       => array( 'postId' ),
				'get_value_callback' => fn( $args, $block ) => kits_post_field_output( (string) ( $args['key'] ?? '' ), kits_post_field_post_id( $block ) ),
			)
		);
	}
);

/** Organisation contact buttons: binding key => the action they name (data-act), and the action => its button word. */
const ANIMAL_WRITES_CONTACT_ACTS  = array( 'phone_url' => 'phone', 'whatsapp_url' => 'whatsapp', 'email_url' => 'email', 'website' => 'website' );
const ANIMAL_WRITES_CONTACT_NAMES = array( 'phone' => 'Call', 'whatsapp' => 'WhatsApp', 'email' => 'Email', 'website' => 'Website' );

/*
 * Organisation contact buttons with no value stay in place, greyed out and not a link, so every profile has the same
 * row (owner, 15 Oct 2026). Kits Core asks what an empty bound block shows (kits_post_field_empty).
 */
add_filter(
	'kits_post_field_empty',
	function ( $out, $content, $block, $key, $post_id ) {
		$act = ANIMAL_WRITES_CONTACT_ACTS[ $key ] ?? '';
		if ( $act && 'core/button' === ( $block['blockName'] ?? '' ) && 'aw_listing' === get_post_type( $post_id ) ) {
			$name = ANIMAL_WRITES_CONTACT_NAMES[ $act ];
			return preg_replace( array( '#<a\b[^>]*>#', '#</a>#' ), array( '<span class="wp-block-button__link wp-element-button" role="link" aria-disabled="true" aria-label="' . esc_attr( $name . ': not available yet' ) . '" title="Not available yet" data-act="' . $act . '">', '</span>' ), $content, 1 );
		}
		return $out;
	},
	10,
	5
);

/*
 * Filled contact buttons name their action too (data-act), for the Directory analytics (aw-analytics.js). Runs after
 * Kits Core's empty rule (priority 10); a greyed button has no <a> left, so it is not touched twice.
 */
add_filter(
	'render_block',
	function ( $content, $block, $instance = null ) {
		$bindings = $block['attrs']['metadata']['bindings'] ?? array();
		if ( ! $bindings || 'core/button' !== ( $block['blockName'] ?? '' ) || 'aw_listing' !== get_post_type( kits_post_field_post_id( $instance ) ) ) {
			return $content;
		}
		$sources = kits_post_field_sources(); // the same sources as the greyed rule, so both behave alike
		foreach ( $bindings as $binding ) {
			$act = ANIMAL_WRITES_CONTACT_ACTS[ $binding['args']['key'] ?? '' ] ?? '';
			if ( $act && in_array( $binding['source'] ?? '', $sources, true ) ) {
				return preg_replace( '#<a\b#', '<a data-act="' . $act . '"', $content, 1 );
			}
		}
		return $content;
	},
	11,
	3
);

/**
 * Organisation contact row (Call, WhatsApp, Email, Website): when any is missing, the greyed buttons are followed by a
 * visible note naming them ("Not available yet: Call, Email") and the Suggest an update link (owner's rule, 15 Oct
 * 2026: missing details show greyed, disabled buttons or a clear "not available" note).
 *
 * @param string $content Rendered core/buttons block.
 * @return string
 */
function aw_profile_actions_note( $content ) {
	if ( false === strpos( $content, 'aw-profile-actions' ) || ! is_singular( 'aw_listing' ) || ! preg_match_all( '#aria-disabled="true"[^>]*data-act="([a-z]+)"#', $content, $m ) ) {
		return $content;
	}
	$list  = implode( ', ', array_map( fn( $a ) => ANIMAL_WRITES_CONTACT_NAMES[ $a ] ?? $a, $m[1] ) );
	$id    = (int) get_queried_object_id();
	$field = 1 === count( $m[1] ) ? ( array( 'phone' => 'phone', 'email' => 'email' )[ $m[1][0] ] ?? '' ) : ''; // one missing: the form names it
	return $content . '<p class="aw-profile-actions-na"><span>' . esc_html( 'Not available yet: ' . $list ) . '</span>'
		. ( function_exists( 'aw_report_url' ) ? ' <a href="' . esc_url( aw_report_url( $id, $field ) ) . '" rel="nofollow">Suggest an update</a>' : '' ) . '</p>';
}
add_filter( 'render_block_core/buttons', 'aw_profile_actions_note', 15 );
