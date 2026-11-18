<?php
/**
 * Standalone engine: the listing post type, its meta, the type list and the soft seams for site-specific features.
 *
 * Everything the Directory needs to run on any WordPress site. These were previously provided by a companion plugin; a
 * host (such as animalwrites.in) can still change any of them through the filters below.
 *
 * @package directoryweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The listing post type's arguments. Filter: directoryweb_listing_args. */
function directoryweb_content_type_args( string $type = 'directoryweb_listing' ): array {
	$args = array(
		'labels'       => array(
			'name'          => __( 'Directory', 'directoryweb' ),
			'singular_name' => __( 'Listing', 'directoryweb' ),
			'menu_name'     => __( 'Directory', 'directoryweb' ),
			'add_new_item'  => __( 'Add listing', 'directoryweb' ),
			'edit_item'     => __( 'Edit listing', 'directoryweb' ),
		),
		'public'       => true,
		'show_in_menu' => false, // listed once, under DirectoryWeb → Listings (no second "Directory" menu)
		'show_in_rest' => true,
		'has_archive'  => 'directory',
		'rewrite'      => array( 'slug' => 'directory/org', 'with_front' => false ), // singles under /directory/org/ so /directory/{place}/{type}/ stays free
		'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ),
		'menu_icon'    => 'dashicons-location',
	);
	return (array) apply_filters( 'directoryweb_listing_args', $args, $type );
}

/** The listing meta keys the plugin reads and writes: key => [ type, description ]. All are text unless marked. */
function directoryweb_meta_keys(): array {
	$text = array(
		'email', 'email_alt', 'website', 'website_alt', 'area', 'address', 'pincode', 'maps_url', 'lat', 'lng', 'services', 'services_source', 'services_on', 'languages',
		'areas_served', 'founded', 'instagram', 'facebook', 'x', 'youtube', 'linkedin', 'whatsapp', 'donate_url', 'adopt_url', 'volunteer_url', 'gallery',
		'registration', 'reg_type', 'reg_date', 'reg_12a', 'reg_80g', 'fcra', 'darpan_id', 'verified_on', 'gov_verified_on', 'state_code', 'district_code',
	);
	$out  = array();
	foreach ( $text as $k ) {
		$out[ 'directoryweb_' . $k ] = array( 'string', $k );
	}
	foreach ( array( 'verified', 'needs_reverify', 'rel_nofollow' ) as $k ) {
		$out[ 'directoryweb_' . $k ] = array( 'boolean', $k );
	}
	$out['directoryweb_phones']        = array( 'integer', 'phones (count; rows are directoryweb_phones_N_number, _label, _whatsapp)' );
	$out['directoryweb_opening_hours'] = array( 'integer', 'opening hours (count; rows are directoryweb_opening_hours_N_day, _open, _close, _closed)' );
	return (array) apply_filters( 'directoryweb_meta_keys', $out );
}

/** Register the listing meta for the REST API and the block editor. */
function directoryweb_register_meta(): void {
	foreach ( directoryweb_meta_keys() as $key => $def ) {
		register_post_meta(
			'directoryweb_listing',
			$key,
			array(
				'type'              => $def[0],
				'single'            => true,
				'show_in_rest'      => true,
				'description'       => $def[1],
				'sanitize_callback' => 'boolean' === $def[0] ? 'rest_sanitize_boolean' : ( 'integer' === $def[0] ? 'absint' : 'sanitize_text_field' ),
				'auth_callback'     => static fn( $allowed, $meta_key, $post_id ) => current_user_can( 'edit_post', $post_id ),
			)
		);
	}
}
add_action( 'init', 'directoryweb_register_meta' );

/** Listing-type slugs (tags on a listing that are not services). Filter: directoryweb_listing_type_slugs. */
function directoryweb_section_tags( $type = 'directoryweb_listing' ): array {
	$types = array_keys( (array) apply_filters( 'directoryweb_listing_types_list', array(
		'animal-shelter' => 'Shelter',
		'vet-clinic'     => 'Clinic or hospital',
		'rescue-group'   => 'Rescue group',
		'non-profit'     => 'Non-profit',
	) ) );
	return array_values( array_merge( $types, directoryweb_service_slugs() ) );
}

/** Listing-type slugs only. */
function directoryweb_listing_type_slugs() {
	return array_values( array_diff( directoryweb_section_tags(), directoryweb_service_slugs() ) );
}

/*
 * Soft seams. Each is declared only if nothing else has, and returns a neutral value, so the plugin runs alone. A
 * host plugin overrides by declaring the function first or by using the filter named in the comment.
 */
if ( ! function_exists( 'directoryweb_use_component_style' ) ) {
	/** A theme-component stylesheet request. No-op here: DirectoryWeb ships its own CSS. */
	function directoryweb_use_component_style( $handle ) {
		do_action( 'directoryweb_use_component_style', $handle );
	}
}
if ( ! function_exists( 'directoryweb_type_icon' ) ) {
	/** Icon markup for a listing type. Filter: directoryweb_type_icon. */
	function directoryweb_type_icon( $slug, $class = '' ) {
		return (string) apply_filters( 'directoryweb_type_icon', '', $slug, $class );
	}
}
if ( ! function_exists( 'directoryweb_theme_file_uri' ) ) {
	function directoryweb_theme_file_uri( $path ) {
		return DIRECTORYWEB_URL . 'assets/' . ltrim( (string) $path, '/' );
	}
}
if ( ! function_exists( 'directoryweb_item_here' ) ) {
	function directoryweb_item_here( $id = 0 ) {
		return is_singular( 'directoryweb_listing' );
	}
}
if ( ! function_exists( 'directoryweb_item_editor_preview' ) ) {
	function directoryweb_item_editor_preview() {
		return (int) apply_filters( 'directoryweb_item_editor_preview', 0 );
	}
}
if ( ! function_exists( 'directoryweb_item_card_media' ) ) {
	function directoryweb_item_card_media( $id ) {
		return (string) apply_filters( 'directoryweb_item_card_media', '', $id );
	}
}
if ( ! function_exists( 'directoryweb_item_more_card' ) ) {
	function directoryweb_item_more_card( $id, $args = array() ) {
		return (string) apply_filters( 'directoryweb_item_more_card', '', $id, $args );
	}
}
if ( ! function_exists( 'directoryweb_block_result_head' ) ) {
	function directoryweb_block_result_head( $title = '', $count = 0 ) {
		return '<h2>' . esc_html( (string) $title ) . '</h2>';
	}
}
if ( ! function_exists( 'directoryweb_hub_type' ) ) {
	function directoryweb_hub_type() {
		return '';
	}
}
if ( ! function_exists( 'directoryweb_hub_url' ) ) {
	function directoryweb_hub_url( $type = '' ) {
		return home_url( '/directory/' );
	}
}
if ( ! function_exists( 'directoryweb_verified_mark' ) ) {
	/** The Verified mark's markup. Filter: directoryweb_verified_mark. */
	function directoryweb_verified_mark( $id = 0 ) {
		return (string) apply_filters( 'directoryweb_verified_mark', '', $id );
	}
}
if ( ! function_exists( 'directoryweb_gov_verified_count' ) ) {
	function directoryweb_gov_verified_count() {
		return (int) apply_filters( 'directoryweb_verified_count', 0 );
	}
}
if ( ! function_exists( 'directoryweb_source_labels' ) ) {
	function directoryweb_source_labels( $id = 0 ) {
		return (array) apply_filters( 'directoryweb_source_labels', array(), $id );
	}
}
if ( ! function_exists( 'directoryweb_source_logo' ) ) {
	function directoryweb_source_logo( $key = '' ) {
		return (string) apply_filters( 'directoryweb_source_logo', '', $key );
	}
}
if ( ! function_exists( 'directoryweb_item_date_ts' ) ) {
	function directoryweb_item_date_ts( $id ) {
		return (int) get_post_time( 'U', true, $id );
	}
}
if ( ! function_exists( 'directoryweb_featured_items' ) ) {
	function directoryweb_featured_items( $id = 0, $limit = 6 ) {
		return (array) apply_filters( 'directoryweb_featured_items', array(), $id, $limit );
	}
}
if ( ! function_exists( 'directoryweb_featured_url' ) ) {
	function directoryweb_featured_url( $id ) {
		return (string) get_permalink( $id );
	}
}
if ( ! function_exists( 'directoryweb_campaign_organiser' ) ) {
	function directoryweb_campaign_organiser( $id = 0 ) {
		return 0;
	}
}
if ( ! function_exists( 'directoryweb_campaign_partner_ids' ) ) {
	function directoryweb_campaign_partner_ids( $id = 0 ) {
		return array();
	}
}
if ( ! function_exists( 'directoryweb_open_opportunities_meta_query' ) ) {
	function directoryweb_open_opportunities_meta_query() {
		return array();
	}
}
if ( ! function_exists( 'directoryweb_place_intro_html' ) ) {
	function directoryweb_place_intro_html( $place = null ) {
		return (string) apply_filters( 'directoryweb_place_intro_html', '', $place );
	}
}
if ( ! function_exists( 'directoryweb_place_intros_url' ) ) {
	function directoryweb_place_intros_url() {
		return '';
	}
}
if ( ! function_exists( 'directoryweb_org_people_from_claim' ) ) {
	function directoryweb_org_people_from_claim( $claim_id = 0, $listing_id = 0 ) {
		do_action( 'directoryweb_claim_approved_people', $claim_id, $listing_id );
	}
}
if ( ! function_exists( 'directoryweb_org_people_link' ) ) {
	function directoryweb_org_people_link( $entry_id = 0, $listing_id = 0, $show_name = false ) {
		do_action( 'directoryweb_claim_people_link', $entry_id, $listing_id, $show_name );
	}
}
if ( ! function_exists( 'directoryweb_sitemap_unreachable_listings' ) ) {
	function directoryweb_sitemap_unreachable_listings() {
		return (array) apply_filters( 'directoryweb_unreachable_listings', array() );
	}
}
if ( ! function_exists( 'directoryweb_si_cap' ) ) {
	function directoryweb_si_cap() {
		return 'manage_options';
	}
}
if ( ! function_exists( 'directoryweb_si_url' ) ) {
	function directoryweb_si_url( $args = array() ) {
		return '';
	}
}
/* Enrichment (a SourceWeb feature): every call is an action or filter, off unless a host handles it. */
if ( ! function_exists( 'directoryweb_enrich_table' ) ) {
	function directoryweb_enrich_table() {
		return '';
	}
}
if ( ! function_exists( 'directoryweb_enrich_add_row' ) ) {
	/** Enrichment seam: filter directoryweb_enrich_add_row; null unless a host answers. */
	function directoryweb_enrich_add_row( ...$args ) {
		return apply_filters( 'directoryweb_enrich_add_row', null, ...$args );
	}
}
if ( ! function_exists( 'directoryweb_enrich_after_change' ) ) {
	/** Enrichment seam: filter directoryweb_enrich_after_change; null unless a host answers. */
	function directoryweb_enrich_after_change( ...$args ) {
		return apply_filters( 'directoryweb_enrich_after_change', null, ...$args );
	}
}
if ( ! function_exists( 'directoryweb_enrich_install' ) ) {
	/** Enrichment seam: filter directoryweb_enrich_install; null unless a host answers. */
	function directoryweb_enrich_install( ...$args ) {
		return apply_filters( 'directoryweb_enrich_install', null, ...$args );
	}
}
if ( ! function_exists( 'directoryweb_enrich_last_applied' ) ) {
	/** Enrichment seam: filter directoryweb_enrich_last_applied; null unless a host answers. */
	function directoryweb_enrich_last_applied( ...$args ) {
		return apply_filters( 'directoryweb_enrich_last_applied', null, ...$args );
	}
}
if ( ! function_exists( 'directoryweb_enrich_profile_extras' ) ) {
	/** Enrichment seam: filter directoryweb_enrich_profile_extras; null unless a host answers. */
	function directoryweb_enrich_profile_extras( ...$args ) {
		return apply_filters( 'directoryweb_enrich_profile_extras', null, ...$args );
	}
}
if ( ! function_exists( 'directoryweb_enrich_progress' ) ) {
	/** Enrichment seam: filter directoryweb_enrich_progress; null unless a host answers. */
	function directoryweb_enrich_progress( ...$args ) {
		return apply_filters( 'directoryweb_enrich_progress', null, ...$args );
	}
}
if ( ! function_exists( 'directoryweb_enrich_snapshot' ) ) {
	/** Enrichment seam: filter directoryweb_enrich_snapshot; null unless a host answers. */
	function directoryweb_enrich_snapshot( ...$args ) {
		return apply_filters( 'directoryweb_enrich_snapshot', null, ...$args );
	}
}
