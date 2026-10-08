<?php
/**
 * Block Bindings source "animal-writes/field" so templates can show meta with core blocks.
 *
 * Usage in a template:
 * <!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"animal-writes/field","args":{"key":"verified_label"}}}}} -->
 *
 * Blocks bound to an empty value are removed from the output.
 *
 * @package animal-writes-core
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
	return $ts ? date_i18n( get_option( 'date_format' ), $ts ) : '';
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
			return $m( 'aw_source_url' ) ? ( $name ? sprintf( __( 'Read at %s', 'animal-writes-core' ), $name ) : __( 'Read at source', 'animal-writes-core' ) ) : '';
		case 'source_meta':
			return trim( implode( ' · ', array_filter( array( $m( 'aw_source_name' ), aw_format_date( $m( 'aw_source_date' ) ) ) ) ) );
		case 'sponsored_label':
			if ( ! get_post_meta( $post_id, 'aw_sponsored', true ) ) {
				return '';
			}
			$sponsor = $m( 'aw_sponsor_name' );
			/* translators: %s: sponsor name. */
			return $sponsor ? sprintf( __( 'Sponsored by %s', 'animal-writes-core' ), $sponsor ) : __( 'Sponsored', 'animal-writes-core' );
		case 'verified_label':
			if ( ! get_post_meta( $post_id, 'aw_verified', true ) ) {
				return '';
			}
			$on = aw_format_date( $m( 'aw_verified_on' ) );
			/* translators: %s: date. */
			return $on ? sprintf( __( 'Verified · %s', 'animal-writes-core' ), $on ) : __( 'Verified', 'animal-writes-core' );
		case 'featured_label':
			return (int) get_post_meta( $post_id, 'aw_featured', true ) ? __( 'Featured', 'animal-writes-core' ) : '';
		case 'area':
			return $m( 'aw_area' );
		case 'services':
			// Services are the organisation's own description (claim) or a named list's; never ours. Shown with who said it.
			if ( '' === $m( 'aw_services' ) ) {
				return '';
			}
			$src = $m( 'aw_services_source' );
			$on  = $m( 'aw_services_on' ) ? aw_format_date( $m( 'aw_services_on' ) ) : '';
			return 'Services: ' . $m( 'aw_services' ) . ( $src ? ' (as stated by ' . $src . ( $on ? ', ' . $on : '' ) . ')' : '' ); // escaped by the binding source
		case 'phone':
			return $m( 'aw_phone' );
		case 'phone_url':
			return aw_tel_url( $m( 'aw_phone' ) );
		case 'whatsapp_url':
			$d = aw_normalise_phone( $m( 'aw_whatsapp' ) );
			return $d ? 'https://wa.me/' . $d : '';
		case 'email_url':
			$e = $m( 'aw_email' );
			return $e ? 'mailto:' . $e : '';
		case 'website':
			return $m( 'aw_website' );
		case 'org_name':
			return $m( 'aw_org_name' );
		case 'apply_url':
			return $m( 'aw_apply_url' );
		case 'deadline_label':
			$d = aw_format_date( $m( 'aw_deadline' ) );
			/* translators: %s: date. */
			return $d ? sprintf( __( 'Apply by %s', 'animal-writes-core' ), $d ) : '';
		case 'file_url':
			return $m( 'aw_file_url' );
	}
	return '';
}

/**
 * Register the bindings source.
 */
function aw_register_bindings() {
	if ( ! function_exists( 'register_block_bindings_source' ) ) {
		return;
	}
	register_block_bindings_source(
		'animal-writes/field',
		array(
			'label'              => __( 'Animal Writes field', 'animal-writes-core' ),
			'uses_context'       => array( 'postId' ),
			'get_value_callback' => function ( $args, $block ) {
				$post_id = $block->context['postId'] ?? get_the_ID();
				$value   = aw_binding_value( $args['key'] ?? '', (int) $post_id );
				return in_array( $args['key'] ?? '', array( 'source_url', 'phone_url', 'whatsapp_url', 'email_url', 'website', 'apply_url', 'file_url' ), true ) ? esc_url( $value ) : esc_html( $value );
			},
		)
	);
}
add_action( 'init', 'aw_register_bindings' );

/**
 * Drop blocks whose Animal Writes binding resolves to an empty value.
 *
 * @param string        $content  Rendered block.
 * @param array         $block    Parsed block.
 * @param WP_Block|null $instance Block instance.
 * @return string
 */
function aw_hide_empty_bound_blocks( $content, $block, $instance = null ) {
	$bindings = $block['attrs']['metadata']['bindings'] ?? array();
	if ( ! $bindings ) {
		return $content;
	}
	$post_id = ( $instance && isset( $instance->context['postId'] ) ) ? (int) $instance->context['postId'] : (int) get_the_ID();
	foreach ( $bindings as $binding ) {
		if ( ( $binding['source'] ?? '' ) === 'animal-writes/field' && '' === aw_binding_value( $binding['args']['key'] ?? '', $post_id ) ) {
			return '';
		}
	}
	return $content;
}
add_filter( 'render_block', 'aw_hide_empty_bound_blocks', 10, 3 );
