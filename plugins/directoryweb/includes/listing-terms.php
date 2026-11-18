<?php
/**
 * A listing's types and services (its tags), and the tel: link of a phone number. Copied from Animal Writes Core
 * (tags.php, model.php, bindings.php) on 18 Nov 2026 with the same lists and results, so DirectoryWeb and the plugins
 * that read listings no longer need Core for them. Core's old functions stay until Core is deleted; keep both lists
 * the same until then.
 *
 * @package directoryweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The services a listing can offer: slug => name. The same list as Core's AW_SERVICES (model.php). "wildlife-rescue"
 * and "emergency-rescue" are also listing types, and count as types (directoryweb_service_slugs()).
 */
const DIRECTORYWEB_SERVICES = array(
	'rescue'           => 'Rescue Work',
	'treatment'        => 'Treatment and Vet Care',
	'shelter'          => 'Sheltering',
	'adoption'         => 'Adoption Service',
	'feeding'          => 'Feeding',
	'sterilisation'    => 'Sterilisation (ABC)',
	'vaccination'      => 'Vaccination',
	'ambulance'        => 'Ambulance Service',
	'boarding-foster'  => 'Boarding and Fostering',
	'wildlife-rescue'  => 'Wildlife Rescue',
	'legal-help'       => 'Legal Help',
	'cattle-care'      => 'Cattle Care',
	'animal-welfare'   => 'Animal Welfare',
	'bird-treatment'   => 'Bird Treatment',
	'emergency-rescue' => 'Emergency Rescue',
);

/**
 * The Directory's tag slugs, from the sourcing lists (option aw_sources, section aw_listing): SourceWeb's
 * sourceweb_sources_config() while SourceWeb is loaded (it seeds the option), else the option as stored.
 *
 * @return string[]
 */
function directoryweb_section_tags(): array {
	$c = function_exists( 'sourceweb_sources_config' ) ? sourceweb_sources_config() : get_option( 'aw_sources' );
	$c = is_array( $c ) ? $c : array();
	return array_values( (array) ( $c['sections'][ directoryweb_post_type() ]['tags'] ?? array() ) );
}

/**
 * Service slugs (the fixed list, without the two that are listing types).
 *
 * @return string[]
 */
function directoryweb_service_slugs(): array {
	return array_values( array_diff( array_keys( DIRECTORYWEB_SERVICES ), array( 'wildlife-rescue', 'emergency-rescue' ) ) );
}

/**
 * Listing-type slugs: the Directory's tags that are not services.
 *
 * @return string[]
 */
function directoryweb_listing_type_slugs(): array {
	return array_values( array_diff( directoryweb_section_tags(), directoryweb_service_slugs() ) );
}

/**
 * An item's tags from one list.
 *
 * @param int      $id    Post ID.
 * @param string[] $slugs Allowed slugs.
 * @return WP_Term[]
 */
function directoryweb_item_tags( $id, array $slugs ): array {
	$terms = get_the_terms( $id, 'post_tag' );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return array();
	}
	return array_values( array_filter( $terms, fn( $t ) => in_array( $t->slug, $slugs, true ) ) );
}

/**
 * A listing's types (its tags that are listing types).
 *
 * @param int $id Listing ID.
 * @return WP_Term[]
 */
function directoryweb_listing_types( $id ): array {
	return directoryweb_item_tags( $id, directoryweb_listing_type_slugs() );
}

/**
 * A listing's services (names).
 *
 * @param int $id Listing ID.
 * @return string[]
 */
function directoryweb_listing_service_names( $id ): array {
	return wp_list_pluck( directoryweb_item_tags( $id, directoryweb_service_slugs() ), 'name' );
}

/**
 * Replace an item's tags from one list, keeping its other tags.
 *
 * @param int      $id   Post ID.
 * @param string[] $new  The tags to set.
 * @param string[] $list The list they come from.
 */
function directoryweb_set_item_tags( $id, array $new, array $list ): void {
	$keep = array_diff( wp_get_object_terms( $id, 'post_tag', array( 'fields' => 'slugs' ) ), $list );
	wp_set_object_terms( $id, array_values( array_unique( array_merge( $keep, array_intersect( $new, $list ) ) ) ), 'post_tag' );
}

/**
 * A phone number as digits with the country code: short helpline codes stay as they are, a 10-digit number gets 91.
 *
 * @param string $raw Raw number.
 * @return string
 */
function directoryweb_normalise_phone( $raw ): string {
	$digits = (string) preg_replace( '/\D+/', '', (string) $raw );
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
 * The tel: link of a phone number (short codes stay local, others get +).
 *
 * @param string $raw Raw number.
 * @return string
 */
function directoryweb_tel_url( $raw ): string {
	$d = directoryweb_normalise_phone( $raw );
	if ( '' === $d ) {
		return '';
	}
	return 'tel:' . ( strlen( $d ) <= 5 ? '' : '+' ) . $d;
}

/**
 * This page loads one of the theme's component stylesheets (Kits by Wugweb's kits_use_component_style(); nothing
 * under another theme).
 *
 * @param string $handle Component style handle (aw-directory, aw-single, aw-listing...).
 */
function directoryweb_use_style( string $handle ): void {
	if ( function_exists( 'kits_use_component_style' ) ) {
		kits_use_component_style( $handle );
	}
}
