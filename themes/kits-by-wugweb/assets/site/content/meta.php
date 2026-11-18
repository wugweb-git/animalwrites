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

	$schema = array(
		// Thin cards: summary lives in the excerpt; this is the attribution.
		'aw_source_url'      => array( 'string', $cards, 'Original source URL' ),
		'aw_source_name'     => array( 'string', $cards, 'Original source name' ),
		'aw_source_date'     => array( 'string', $cards, 'Source publication date (Y-m-d)' ),

		// News key facts: the card above the summary prints when at least one is filled (news-article.php).
		'aw_news_happened'   => array( 'string', array( 'aw_news' ), 'Key facts: what happened (one sentence)' ),
		'aw_news_matters'    => array( 'string', array( 'aw_news' ), 'Key facts: why it matters (one sentence)' ),
		'aw_news_next'       => array( 'string', array( 'aw_news' ), 'Key facts: what is next (one sentence)' ),

		// Monetisation guardrails.
		'aw_sponsored'       => array( 'boolean', $all, 'Sponsored content' ),
		'aw_sponsor_name'    => array( 'string', $all, 'Sponsor name' ),

		// Directory: the listing fields are DirectoryWeb's (directoryweb_meta_schema(), added below). Websites stay here for Opportunities.
		'aw_website'         => array( 'string', array( 'aw_opportunity' ), 'Website' ),

		// Opportunities.
		'aw_org_name'        => array( 'string', array( 'aw_opportunity' ), 'Organisation' ),
		'aw_apply_url'       => array( 'string', array( 'aw_opportunity' ), 'Apply URL' ),
		'aw_deadline'        => array( 'string', array( 'aw_opportunity' ), 'Deadline (Y-m-d)' ),
		'aw_review_until'    => array( 'string', array( 'aw_opportunity' ), 'Review date (Y-m-d): with no deadline, the listing closes after this date unless renewed' ),
		'aw_paid_listing'    => array( 'boolean', array( 'aw_opportunity' ), 'Paid listing' ), // older key, no field: the Opportunity's Sponsored field (aw_sponsored, registered above for every type) replaces it
		'aw_employment_type' => array( 'string', array( 'aw_opportunity' ), 'Employment type as the source states it: FULL_TIME, PART_TIME, CONTRACTOR, TEMPORARY, PER_DIEM, INTERN, VOLUNTEER or OTHER (empty when not stated)' ),

		// Campaigns (read by the Event schema in aw-seo-extras).
		'aw_event_date'      => array( 'string', array( 'aw_campaign', 'aw_opportunity' ), 'Event date (Y-m-d)' ),
		'aw_event_schedule'  => array( 'string', array( 'aw_opportunity' ), 'Repeats, as the source states it: "second Saturday monthly 14:00-16:00" (read by the Event schema)' ),
		'aw_event_place'     => array( 'string', array( 'aw_campaign' ), 'Event place' ),
		'aw_campaign_close'  => array( 'string', array( 'aw_campaign' ), 'Closes on (Y-m-d)' ),
		'aw_campaign_doc'    => array( 'string', array( 'aw_campaign' ), 'Campaign document (Library item ID)' ),
		'aw_campaign_partners' => array( 'string', array( 'aw_campaign' ), 'Partners (Directory listing IDs, comma-separated; only the owner\'s list)' ),
		'aw_campaign_supporters' => array( 'string', array( 'aw_campaign' ), 'Supporters (Directory listing IDs, comma-separated; "Supported by" on the campaign page)' ),
		'aw_campaign_open'   => array( 'string', array( 'aw_campaign' ), 'Opens on (Y-m-d, IST)' ),
		'aw_campaign_outcome' => array( 'string', array( 'aw_campaign' ), 'Outcome after closing: pending, achieved, partial, not, inconclusive' ),
		'aw_campaign_outcome_asof' => array( 'string', array( 'aw_campaign' ), 'Outcome as of (Y-m-d)' ),
		'aw_campaign_state_override' => array( 'string', array( 'aw_campaign' ), 'State override: pre, active or post (needs a reason)' ),
		'aw_campaign_override_note' => array( 'string', array( 'aw_campaign' ), 'Reason for the state override' ),
		'aw_campaign_metric_min' => array( 'string', array( 'aw_campaign' ), 'Show the count from this number' ),
		'aw_campaign_share_text' => array( 'string', array( 'aw_campaign' ), 'Line shared with the link' ),
		'aw_campaign_share_hashtags' => array( 'string', array( 'aw_campaign' ), 'Hashtags for X (comma-separated, without #)' ),
		'aw_campaign_share_image' => array( 'string', array( 'aw_campaign' ), 'Share picture (attachment ID, 1080x1080): one picture for every network' ),
		'aw_campaign_share_story' => array( 'string', array( 'aw_campaign' ), 'Story image (attachment ID, 1080x1920)' ),
		'aw_campaign_share_post' => array( 'string', array( 'aw_campaign' ), 'Post image (attachment ID, 1080x1350)' ),
		'aw_campaign_metric_stale' => array( 'string', array( 'aw_campaign' ), 'Days without a new letter before the count shows an as-of date' ),
		'aw_campaign_short'  => array( 'string', array( 'aw_campaign' ), 'Short link slug: /{slug} redirects to the campaign while published (letters, digits, hyphens)' ),
		'aw_campaign_card_title' => array( 'string', array( 'aw_campaign' ), 'Side card title (short; the campaign title when empty)' ),

		// Resources: the date a guide was last checked (schema lastReviewed).
		'kits_last_reviewed' => array( 'string', array( 'post' ), 'Last reviewed (Y-m-d)' ),

		// Resources.
		'aw_file_url'        => array( 'string', array( 'aw_legal' ), 'Downloadable file URL' ),
		'aw_file_id'         => array( 'string', array( 'aw_legal' ), 'Document file (attachment ID)' ),
		'aw_doc_host_ok'     => array( 'string', array( 'aw_legal' ), 'Host this document here (1 = the owner allows a copy)' ),
		'aw_requires_email'  => array( 'boolean', array( 'aw_legal' ), 'Gate download behind newsletter signup' ),
		'aw_legal_number'    => array( 'string', array( 'aw_legal' ), 'Official number of the text (e.g. G.S.R. 864(E))' ),
		'aw_legal_force'     => array( 'string', array( 'aw_legal' ), 'In force: InForce, NotInForce or PartiallyInForce' ),

		// An explicit cover the owner chose for an item, used ahead of the document preview, the ingested photo and the category photo (attachment ID).
		'aw_cover_image_id'  => array( 'string', array( 'aw_news', 'aw_legal', 'post' ), 'Cover picture override (attachment ID): shown ahead of the document preview and category photo' ),
	);

	// The Directory's fields, registered by DirectoryWeb, listed here too so every reader of the whole schema sees them
	// (scf.php). A key on both sides (aw_website) gets both sides' post types.
	foreach ( function_exists( 'directoryweb_meta_schema' ) ? directoryweb_meta_schema() : array() as $key => $def ) {
		if ( isset( $schema[ $key ] ) ) {
			$schema[ $key ][1] = array_values( array_unique( array_merge( $schema[ $key ][1], $def[1] ) ) );
		} else {
			$schema[ $key ] = $def;
		}
	}
	return $schema;
}

/**
 * Sanitize a meta value by key.
 *
 * @param mixed  $value Raw value.
 * @param string $key   Meta key.
 * @return mixed
 */
function aw_sanitize_meta( $value, $key ) {
	if ( function_exists( 'directoryweb_meta_schema' ) && isset( directoryweb_meta_schema()[ $key ] ) ) {
		return directoryweb_sanitize_meta( $value, $key ); // the Directory's fields: DirectoryWeb's rules
	}
	$url_keys  = array( 'aw_source_url', 'aw_website', 'aw_apply_url', 'aw_file_url' );
	$date_keys = array( 'aw_source_date', 'aw_deadline', 'aw_review_until', 'aw_event_date', 'aw_campaign_close', 'aw_campaign_open', 'aw_campaign_outcome_asof', 'kits_last_reviewed' );

	if ( in_array( $key, $url_keys, true ) ) {
		return esc_url_raw( (string) $value );
	}
	if ( in_array( $key, $date_keys, true ) ) {
		$value = (string) $value;
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
	}
	if ( 'aw_campaign_short' === $key ) { // one path segment (campaign-short.php)
		return function_exists( 'aw_camp_short_clean' ) ? aw_camp_short_clean( $value ) : sanitize_title( (string) $value );
	}
	if ( 'aw_cover_image_id' === $key ) { // an attachment ID, or empty to clear
		return (int) $value ? (string) (int) $value : '';
	}
	if ( 'aw_employment_type' === $key ) { // Google's JobPosting values only
		$value = strtoupper( str_replace( array( '-', ' ' ), '_', trim( (string) $value ) ) );
		$value = preg_replace( '/^(FULL|PART)_?TIME$/', '$1_TIME', $value ); // "Fulltime", "Full time"
		$value = array( 'CONTRACT' => 'CONTRACTOR', 'INTERNSHIP' => 'INTERN', 'TEMP' => 'TEMPORARY' )[ $value ] ?? $value;
		return in_array( $value, array( 'FULL_TIME', 'PART_TIME', 'CONTRACTOR', 'TEMPORARY', 'PER_DIEM', 'INTERN', 'VOLUNTEER', 'OTHER' ), true ) ? $value : '';
	}
	return sanitize_text_field( (string) $value );
}

/**
 * Register meta.
 */
function aw_register_meta() {
	$dw = function_exists( 'directoryweb_meta_schema' ) ? directoryweb_meta_schema() : array(); // registered by DirectoryWeb
	foreach ( aw_meta_schema() as $key => $def ) {
		list( $type, $post_types, $description ) = $def;
		foreach ( $post_types as $post_type ) {
			if ( isset( $dw[ $key ] ) && in_array( $post_type, $dw[ $key ][1], true ) ) {
				continue;
			}
			$args = array(
				'type'          => $type,
				'description'   => $description,
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => function ( $allowed, $key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
			);
			if ( 'string' === $type ) {
				$args['sanitize_callback'] = function ( $value ) use ( $key ) {
					return aw_sanitize_meta( $value, $key );
				};
			}
			register_post_meta( $post_type, $key, $args );
		}
	}
}
add_action( 'init', 'aw_register_meta' );

// The featured-flag backfill and the clean-up of its option ran and were removed (audit item 16; the option is gone on live, 25 Oct 2026).

/*
 * Phone numbers, opening hours and the never-publish list are DirectoryWeb's (includes/listing-meta.php, moved there
 * on 18 Nov 2026; privacy takedown hashes are added there, in DIRECTORYWEB_NEVER_PUBLISH_PHONES). The old names stay
 * as wrappers for SourceWeb, RankWeb, the FAQ feeders, the bindings and the import tools. Without DirectoryWeb they
 * read as empty and write nothing.
 */

/** The days in order, Monday first. */
const AW_WEEKDAYS = array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' );

if ( ! function_exists( 'aw_phone_never_publish' ) ) {
	/** Whether a phone number is on the never-publish list (DirectoryWeb). */
	function aw_phone_never_publish( $raw ) {
		return function_exists( 'directoryweb_phone_never_publish' ) ? directoryweb_phone_never_publish( $raw ) : false;
	}
}

if ( ! function_exists( 'aw_listing_phones' ) ) {
	/** A listing's phone numbers (DirectoryWeb). */
	function aw_listing_phones( $id ) {
		return function_exists( 'directoryweb_listing_phones' ) ? directoryweb_listing_phones( $id ) : array();
	}
}

if ( ! function_exists( 'aw_listing_hours' ) ) {
	/** A listing's opening hours by day (DirectoryWeb). */
	function aw_listing_hours( $id ) {
		return function_exists( 'directoryweb_listing_hours' ) ? directoryweb_listing_hours( $id ) : array();
	}
}

if ( ! function_exists( 'aw_set_listing_list' ) ) {
	/** Write a listing's phone or hours list (DirectoryWeb). */
	function aw_set_listing_list( $id, $key, array $rows ) {
		if ( function_exists( 'directoryweb_set_listing_list' ) ) {
			directoryweb_set_listing_list( $id, $key, $rows );
		} else {
			_doing_it_wrong( __FUNCTION__, 'DirectoryWeb is not loaded: the list was not written.', '1.0.0' );
		}
	}
}

if ( ! function_exists( 'aw_listing_put_phone' ) ) {
	/** Put a phone and/or WhatsApp number into a listing's phone list (DirectoryWeb). */
	function aw_listing_put_phone( $id, $phone = '', $whatsapp = '' ) {
		if ( function_exists( 'directoryweb_listing_put_phone' ) ) {
			directoryweb_listing_put_phone( $id, $phone, $whatsapp );
		} else {
			_doing_it_wrong( __FUNCTION__, 'DirectoryWeb is not loaded: the phone was not written.', '1.0.0' );
		}
	}
}

if ( ! function_exists( 'aw_listing_first_phone' ) ) {
	/** The first number of a listing, or the first marked WhatsApp (DirectoryWeb). */
	function aw_listing_first_phone( $id, $whatsapp = false ) {
		return function_exists( 'directoryweb_listing_first_phone' ) ? directoryweb_listing_first_phone( $id, $whatsapp ) : '';
	}
}

if ( ! function_exists( 'aw_hours_rows_spec' ) ) {
	/** Opening hours as schema.org OpeningHoursSpecification (DirectoryWeb). */
	function aw_hours_rows_spec( $rows ) {
		return function_exists( 'directoryweb_hours_rows_spec' ) ? directoryweb_hours_rows_spec( $rows ) : array();
	}
}
