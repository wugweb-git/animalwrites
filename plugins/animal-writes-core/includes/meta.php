<?php
/**
 * Post meta. Registered natively (no ACF) and exposed to the block editor + REST.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Meta schema: key => [ type, post types, description ].
 *
 * @return array
 */
function aw_meta_schema() {
	$cards = array( 'aw_news', 'aw_legal', 'aw_listing', 'aw_opportunity' ); // listings and opportunities: where a record came from
	$all   = array_keys( aw_post_types() );

	return array(
		// Thin cards: summary lives in the excerpt; this is the attribution.
		'aw_source_url'      => array( 'string', $cards, 'Original source URL' ),
		'aw_source_name'     => array( 'string', $cards, 'Original source name' ),
		'aw_source_date'     => array( 'string', $cards, 'Source publication date (Y-m-d)' ),

		// Monetisation guardrails.
		'aw_sponsored'       => array( 'boolean', $all, 'Sponsored content' ),
		'aw_sponsor_name'    => array( 'string', $all, 'Sponsor name' ),

		// Directory.
		'aw_verified'        => array( 'boolean', array( 'aw_listing' ), 'Verified by Animal Writes' ),
		'aw_verified_on'     => array( 'string', array( 'aw_listing' ), 'Last verified date (Y-m-d)' ),
		'aw_needs_reverify'  => array( 'boolean', array( 'aw_listing' ), 'Flagged for re-verification' ),
		'aw_featured'        => array( 'integer', array( 'aw_listing' ), 'Featured (1) or not (0)' ),
		'aw_featured_until'  => array( 'string', array( 'aw_listing' ), 'Featured until (Y-m-d)' ),
		'aw_phone'           => array( 'string', array( 'aw_listing' ), 'Phone' ),
		'aw_whatsapp'        => array( 'string', array( 'aw_listing' ), 'WhatsApp' ),
		'aw_email'           => array( 'string', array( 'aw_listing' ), 'Email' ),
		'aw_website'         => array( 'string', array( 'aw_listing', 'aw_opportunity' ), 'Website' ),
		'aw_area'            => array( 'string', array( 'aw_listing' ), 'Area / locality (no full address)' ),
		'aw_services'        => array( 'string', array( 'aw_listing' ), 'Services offered, as stated by the organisation or a named list (comma separated)' ),
		'aw_services_source' => array( 'string', array( 'aw_listing' ), 'Who stated the services (e.g. "the organisation", or the list name)' ),
		'aw_services_on'     => array( 'string', array( 'aw_listing' ), 'Date the services were stated (YYYY-MM-DD)' ),

		// Opportunities.
		'aw_org_name'        => array( 'string', array( 'aw_opportunity' ), 'Organisation' ),
		'aw_apply_url'       => array( 'string', array( 'aw_opportunity' ), 'Apply URL' ),
		'aw_deadline'        => array( 'string', array( 'aw_opportunity' ), 'Deadline (Y-m-d)' ),
		'aw_review_until'    => array( 'string', array( 'aw_opportunity' ), 'Review date (Y-m-d): with no deadline, the listing closes after this date unless renewed' ),
		'aw_paid_listing'    => array( 'boolean', array( 'aw_opportunity' ), 'Paid listing' ),

		// Resources.
		'aw_file_url'        => array( 'string', array( 'aw_legal' ), 'Downloadable file URL' ),
		'aw_requires_email'  => array( 'boolean', array( 'aw_legal' ), 'Gate download behind newsletter signup' ),
	);
}

/**
 * Sanitize a meta value by key.
 *
 * @param mixed  $value Raw value.
 * @param string $key   Meta key.
 * @return mixed
 */
function aw_sanitize_meta( $value, $key ) {
	$url_keys  = array( 'aw_source_url', 'aw_website', 'aw_apply_url', 'aw_file_url' );
	$date_keys = array( 'aw_source_date', 'aw_verified_on', 'aw_featured_until', 'aw_deadline', 'aw_review_until' );

	if ( in_array( $key, $url_keys, true ) ) {
		return esc_url_raw( (string) $value );
	}
	if ( in_array( $key, $date_keys, true ) ) {
		$value = (string) $value;
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
	}
	if ( 'aw_email' === $key ) {
		return sanitize_email( (string) $value );
	}
	return sanitize_text_field( (string) $value );
}

/**
 * Register meta.
 */
function aw_register_meta() {
	foreach ( aw_meta_schema() as $key => $def ) {
		list( $type, $post_types, $description ) = $def;
		foreach ( $post_types as $post_type ) {
			$args = array(
				'type'          => $type,
				'description'   => $description,
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			);
			if ( 'string' === $type ) {
				$args['sanitize_callback'] = function ( $value ) use ( $key ) {
					return aw_sanitize_meta( $value, $key );
				};
			}
			if ( 'aw_featured' === $key ) {
				$args['default'] = 0;
			}
			register_post_meta( $post_type, $key, $args );
		}
	}
}
add_action( 'init', 'aw_register_meta' );

/**
 * Listings always carry aw_featured so directory ordering never drops a listing.
 *
 * @param int $post_id Post ID.
 */
function aw_ensure_featured_flag( $post_id ) {
	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! metadata_exists( 'post', $post_id, 'aw_featured' ) ) {
		update_post_meta( $post_id, 'aw_featured', 0 );
	}
}
add_action( 'save_post_aw_listing', 'aw_ensure_featured_flag' );

/**
 * Add aw_featured = 0 to any listing missing it (imports, manual DB edits).
 * Runs on activation, daily, and once per plugin version in admin.
 */
function aw_backfill_featured_flag() {
	$ids = get_posts(
		array(
			'post_type'      => 'aw_listing',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'     => 'aw_featured',
					'compare' => 'NOT EXISTS',
				),
			),
		)
	);
	foreach ( $ids as $id ) {
		update_post_meta( $id, 'aw_featured', 0 );
	}
}

add_action(
	'admin_init',
	function () {
		if ( get_option( 'aw_core_backfill_version' ) !== AW_CORE_VERSION ) {
			aw_backfill_featured_flag();
			update_option( 'aw_core_backfill_version', AW_CORE_VERSION );
		}
	}
);
