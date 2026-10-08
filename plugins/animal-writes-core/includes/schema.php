<?php
/**
 * Structured data (JSON-LD).
 *
 * - Listing pages: Organization (NGO) with name, URL, logo, locality/region (no street address), contact details
 *   when present, the official website as sameAs, and the AWBI recognition code as an identifier.
 * - Directory pages (all, state, district, type): ItemList of the organisations on the page, in order.
 * - Every page: the publisher (Organization) and the site (WebSite with site search), and the breadcrumb trail.
 * - News: NewsArticle. Legal Watch: Legislation for Acts, Rules, Notifications and Circulars, else NewsArticle.
 *   Guides: Article, plus FAQPage when the guide is written as questions. Jobs, internships, fellowships: JobPosting.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Print one JSON-LD block.
 *
 * @param array $data Schema.org data.
 */
function aw_jsonld( $data ) {
	echo "\n" . '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
}

/**
 * State and district names of a listing.
 *
 * @param int $id Post ID.
 * @return array{0: string, 1: string} state, district.
 */
function aw_listing_place( $id ) {
	return aw_item_place_names( $id ); // LGD places (places.php)
}

/**
 * Organization data for a listing.
 *
 * @param int $id Post ID.
 * @return array
 */
function aw_listing_schema( $id ) {
	list( $state, $district ) = aw_listing_place( $id );
	$m    = fn( $k ) => trim( (string) get_post_meta( $id, $k, true ) );
	$vet  = has_term( array( 'veterinary-hospital', 'veterinary-dispensary', 'mobile-veterinary-unit', 'veterinary-office' ), 'aw_listing_type', $id );
	$site = $m( 'aw_website' );
	$org  = array(
		'@type'       => $vet ? 'VeterinaryCare' : 'NGO',
		'@id'         => get_permalink( $id ) . '#organization',
		'name'        => wp_strip_all_tags( (string) get_post_field( 'post_title', $id ) ), // no "Private:" prefix
		'url'         => $site ? $site : get_permalink( $id ),                 // the organisation's own site when known
		'mainEntityOfPage' => get_permalink( $id ),                           // this profile
		'description' => wp_strip_all_tags( (string) get_the_excerpt( $id ) ),
	);
	$logo = get_the_post_thumbnail_url( $id, 'medium' );
	if ( $logo ) {
		$org['logo']  = $logo;
		$org['image'] = $logo;
	}
	if ( $state || $district ) {
		$org['address'] = array_filter( array( '@type' => 'PostalAddress', 'streetAddress' => $m( 'aw_area' ), 'addressLocality' => $district, 'addressRegion' => $state, 'postalCode' => $m( 'aw_pincode' ), 'addressCountry' => 'IN' ) );
		$org['areaServed'] = array_values( array_filter( array( $district ? array( '@type' => 'AdministrativeArea', 'name' => $district ) : null, $state ? array( '@type' => 'State', 'name' => $state ) : null ) ) );
	}
	if ( $m( 'aw_phone' ) ) {
		$org['telephone'] = $m( 'aw_phone' );
	}
	if ( $m( 'aw_email' ) ) {
		$org['email'] = $m( 'aw_email' );
	}
	if ( $m( 'aw_whatsapp' ) ) {
		$org['contactPoint'] = array( '@type' => 'ContactPoint', 'contactType' => 'WhatsApp', 'telephone' => $m( 'aw_whatsapp' ), 'availableLanguage' => array( 'en', 'hi' ) );
	}
	$same = array_values( array_filter( array( $site, $m( 'aw_g_profile' ), $m( 'aw_instagram' ), $m( 'aw_facebook' ), $m( 'aw_x' ), $m( 'aw_youtube' ) ) ) );
	if ( $same ) {
		$org['sameAs'] = $same;
	}
	if ( $m( 'aw_maps_url' ) ) {
		$org['hasMap'] = $m( 'aw_maps_url' );
	}
	if ( is_numeric( $m( 'aw_lat' ) ) && is_numeric( $m( 'aw_lng' ) ) ) {
		$org['geo'] = array( '@type' => 'GeoCoordinates', 'latitude' => (float) $m( 'aw_lat' ), 'longitude' => (float) $m( 'aw_lng' ) );
	}
	// The Google rating is shown on the page only: Google does not accept third-party ratings in an organisation's schema.
	$photos = array_values( array_filter( array_map( fn( $p ) => (string) wp_get_attachment_image_url( (int) $p, 'large' ), array_filter( explode( ',', $m( 'aw_gallery' ) ) ) ) ) );
	if ( $photos ) {
		$org['image'] = array_values( array_unique( array_merge( (array) ( $org['image'] ?? array() ), $photos ) ) );
	}
	if ( $m( 'aw_founded' ) ) {
		$org['foundingDate'] = $m( 'aw_founded' );
	}
	if ( $m( 'aw_languages' ) ) {
		$org['knowsLanguage'] = array_values( array_filter( array_map( 'trim', explode( ',', $m( 'aw_languages' ) ) ) ) );
	}
	$spec = aw_hours_spec( $m( 'aw_hours_spec' ) );
	if ( $spec ) {
		$org['openingHoursSpecification'] = $spec; // exact hours, e.g. from the Google profile
	} elseif ( $m( 'aw_hours' ) && $vet ) {
		$org['openingHours'] = $m( 'aw_hours' ); // free-text hours: places (LocalBusiness) only, so only on VeterinaryCare
	}

	if ( $m( 'aw_services' ) ) {
		$org['knowsAbout'] = array_values( array_filter( array_map( 'trim', preg_split( '/[,;\n]+/', wp_strip_all_tags( $m( 'aw_services' ) ) ) ) ) );
	}
	$types = get_the_terms( $id, 'aw_listing_type' );
	if ( $types && ! is_wp_error( $types ) ) {
		$org['keywords'] = implode( ', ', array_filter( array_merge( wp_list_pluck( $types, 'name' ), array( $m( 'aw_g_category' ) ) ) ) ); // additionalType must be a URL; the type names (and Google's category) go here
	}
	$code = $m( 'aw_awbi_code' );
	if ( $code ) {
		$org['identifier'] = array( '@type' => 'PropertyValue', 'propertyID' => 'AWBI recognition code', 'value' => $code );
	}
	if ( $m( 'aw_registration' ) ) { // Trust/Society number, 12A, 80G, NGO Darpan ID, as the organisation states them
		$org['identifier'] = array_values( array_filter( array( $org['identifier'] ?? null, array( '@type' => 'PropertyValue', 'propertyID' => 'Registration', 'value' => $m( 'aw_registration' ) ) ) ) );
	}
	return array_filter( $org );
}

add_action(
	'wp_head',
	function () {
		if ( is_singular( 'aw_listing' ) ) {
			aw_jsonld( array( '@context' => 'https://schema.org' ) + aw_listing_schema( get_queried_object_id() ) );
			return;
		}
	},
	30
);

/* ---------- Site and content pages ---------- */

/** The publisher (Animal Writes) as schema.org Organization. */
function aw_publisher_schema() {
	$logo = get_site_icon_url( 512 );
	return array_filter(
		array(
			'@type'  => 'Organization',
			'@id'    => home_url( '/#organization' ),
			'name'   => get_bloginfo( 'name' ),
			'url'    => home_url( '/' ),
			'logo'   => $logo ? array( '@type' => 'ImageObject', 'url' => $logo ) : null,
			'sameAs' => array( 'https://www.facebook.com/AnimalWritesIndia/', 'https://www.instagram.com/animalwrites_india/', 'https://x.com/AnimalWrites' ),
		)
	);
}

/**
 * Article-type data for a content post (news, legal, research, guide).
 *
 * @param int $id Post ID.
 * @return array
 */
function aw_article_schema( $id ) {
	$type  = get_post_type( $id );
	$title = wp_strip_all_tags( get_the_title( $id ) );
	$at    = 'Article';
	if ( 'aw_legal' === $type && has_term( array( 'act', 'rule', 'notification', 'circular' ), 'aw_legal_kind', $id ) ) {
		$at = 'Legislation';
	} elseif ( 'aw_legal' === $type && has_term( 'template', 'aw_legal_kind', $id ) ) {
		$at = 'Article';
	} elseif ( in_array( $type, array( 'aw_news', 'aw_legal' ), true ) ) {
		$at = 'NewsArticle';
	}
	$src_date = (string) get_post_meta( $id, 'aw_source_date', true );
	$image    = function_exists( 'aw_item_image' ) ? aw_item_image( $id ) : '';
	$image    = $image ? $image : (string) get_the_post_thumbnail_url( $id, 'large' );
	$src_url  = (string) get_post_meta( $id, 'aw_source_url', true );
	$src_name = (string) get_post_meta( $id, 'aw_source_name', true );
	$data     = array(
		'@type'            => $at,
		'@id'              => get_permalink( $id ) . '#article',
		'mainEntityOfPage' => get_permalink( $id ),
		'url'              => get_permalink( $id ),
		'description'      => function_exists( 'aw_text' ) ? aw_text( $id, 40 ) : '',
		'datePublished'    => $src_date ? $src_date : get_the_date( 'c', $id ),
		'dateModified'     => get_the_modified_date( 'c', $id ),
		'inLanguage'       => 'en-IN',
		'publisher'        => array( '@id' => home_url( '/#organization' ) ),
	);
	$data[ 'Legislation' === $at ? 'name' : 'headline' ] = $title;
	if ( $image ) {
		$data['image'] = array( $image );
	}
	if ( 'Legislation' !== $at ) {
		$data['author'] = array( '@id' => home_url( '/#organization' ) ); // the summary or guide is ours
	} else {
		$data['legislationJurisdiction'] = 'IN';
		$kind = function_exists( 'aw_first_term' ) ? aw_first_term( $id, 'aw_legal_kind' ) : null;
		if ( $kind ) {
			$data['legislationType'] = $kind->name;
		}
		$iss = function_exists( 'aw_item_issuer' ) ? aw_item_issuer( $id ) : null;
		if ( $iss ) {
			$data['legislationPassedBy'] = array( '@type' => 'Organization', 'name' => $iss->name );
		}
	}
	if ( $src_url ) {
		// The original item we summarise or host.
		$data['isBasedOn'] = array_filter( array( '@type' => 'CreativeWork', 'url' => $src_url, 'publisher' => $src_name ? array( '@type' => 'Organization', 'name' => $src_name ) : null ) );
	}
	return array_filter( $data );
}

/** A campaign: a CreativeWork by its organiser (or Animal Writes), with its topic. */
function aw_campaign_schema( $id ) {
	$org   = (int) get_post_meta( $id, 'aw_organiser', true );
	$topic = function_exists( 'aw_first_term' ) ? aw_first_term( $id, 'aw_topic' ) : null;
	$image = (string) get_the_post_thumbnail_url( $id, 'large' );
	return array_filter(
		array(
			'@type'         => 'CreativeWork',
			'@id'           => get_permalink( $id ) . '#campaign',
			'url'           => get_permalink( $id ),
			'name'          => wp_strip_all_tags( get_the_title( $id ) ),
			'description'   => function_exists( 'aw_text' ) ? aw_text( $id, 40 ) : '',
			'datePublished' => get_the_date( 'c', $id ),
			'dateModified'  => get_the_modified_date( 'c', $id ),
			'inLanguage'    => 'en-IN',
			'image'         => $image ? array( $image ) : null,
			'about'         => $topic ? array( '@type' => 'Thing', 'name' => $topic->name ) : null,
			'creator'       => $org && 'publish' === get_post_status( $org ) ? array( '@type' => 'NGO', 'name' => get_the_title( $org ), 'url' => get_permalink( $org ) ) : array( '@id' => home_url( '/#organization' ) ),
			'publisher'     => array( '@id' => home_url( '/#organization' ) ),
		)
	);
}

/** BreadcrumbList for the current page, from the same trail people see (breadcrumbs.php). */
function aw_breadcrumb_schema() {
	$trail = function_exists( 'aw_breadcrumbs' ) ? aw_breadcrumbs() : array();
	if ( ! $trail ) {
		return array();
	}
	$list = array();
	foreach ( $trail as $i => $it ) {
		$list[] = array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => wp_strip_all_tags( $it[0] ), 'item' => $it[1] );
	}
	return array( '@type' => 'BreadcrumbList', 'itemListElement' => $list );
}

/** The site itself, with site search (shown on every page). */
function aw_website_schema() {
	return array(
		'@type'           => 'WebSite',
		'@id'             => home_url( '/#website' ),
		'name'            => get_bloginfo( 'name' ),
		'url'             => home_url( '/' ),
		'inLanguage'      => 'en-IN',
		'publisher'       => array( '@id' => home_url( '/#organization' ) ),
		'potentialAction' => array(
			'@type'       => 'SearchAction',
			'target'      => home_url( '/search/?q={search_term_string}' ),
			'query-input' => 'required name=search_term_string',
		),
	);
}

/** FAQPage for a guide whose headings are questions, each followed by its answer. */
function aw_faq_schema( $id ) {
	$html = (string) apply_filters( 'the_content', get_post_field( 'post_content', $id ) );
	if ( ! preg_match_all( '#<h[23][^>]*>(.*?\?)\s*</h[23]>(.*?)(?=<h[23]|$)#si', $html, $m, PREG_SET_ORDER ) || count( $m ) < 2 ) {
		return array();
	}
	$qs = array();
	foreach ( $m as $row ) {
		$answer = trim( wp_strip_all_tags( $row[2] ) );
		if ( '' !== $answer ) {
			$qs[] = array( '@type' => 'Question', 'name' => wp_strip_all_tags( $row[1] ), 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $answer ) );
		}
	}
	return count( $qs ) >= 2 ? array( '@type' => 'FAQPage', '@id' => get_permalink( $id ) . '#faq', 'mainEntity' => $qs ) : array();
}

/** JobPosting for jobs, internships and fellowships that have a place. */
function aw_job_schema( $id ) {
	if ( ! has_term( array( 'job', 'internship', 'fellowship' ), 'aw_opportunity_type', $id ) ) {
		return array();
	}
	list( $state, $district ) = function_exists( 'aw_listing_place' ) ? aw_listing_place( $id ) : array( '', '' );
	$area = (string) get_post_meta( $id, 'aw_area', true );
	$org_listing = (int) get_post_meta( $id, 'aw_organisation', true );
	if ( ! $state && ! $district && ! $area && $org_listing ) {
		list( $state, $district ) = aw_listing_place( $org_listing ); // the organisation's own location
	}
	if ( ! $state && ! $district && ! $area ) {
		return array(); // a job posting needs a place
	}
	$org      = (string) get_post_meta( $id, 'aw_org_name', true );
	$org_id   = (int) get_post_meta( $id, 'aw_organisation', true );
	$org      = $org ? $org : ( $org_id ? get_post_field( 'post_title', $org_id ) : '' );
	$deadline = function_exists( 'aw_opportunity_closes' ) ? aw_opportunity_closes( $id ) : (string) get_post_meta( $id, 'aw_deadline', true );
	$desc     = trim( (string) get_post_field( 'post_content', $id ) );
	$desc     = $desc ? $desc : ( function_exists( 'aw_text' ) ? aw_text( $id, 60 ) : get_the_title( $id ) );
	$data     = array(
		'@type'              => 'JobPosting',
		'@id'                => get_permalink( $id ) . '#job',
		'title'              => wp_strip_all_tags( get_the_title( $id ) ),
		'description'        => wp_kses_post( $desc ),
		'datePosted'         => get_the_date( 'c', $id ),
		'url'                => get_permalink( $id ),
		'employmentType'     => has_term( 'internship', 'aw_opportunity_type', $id ) ? 'INTERN' : null,
		'hiringOrganization' => $org ? array_filter( array( '@type' => 'Organization', 'name' => $org, 'sameAs' => (string) get_post_meta( $id, 'aw_website', true ) ) ) : null,
		'jobLocation'        => array(
			'@type'   => 'Place',
			'address' => array_filter( array( '@type' => 'PostalAddress', 'addressLocality' => $district ? $district : $area, 'addressRegion' => $state, 'addressCountry' => 'IN' ) ),
		),
		'validThrough'       => $deadline ? $deadline . 'T23:59:59+05:30' : null,
	);
	return array_filter( $data );
}

// One graph per page: the publisher and the site on every page, plus the page's own item and its breadcrumb trail.
add_action(
	'wp_head',
	function () {
		if ( is_404() ) {
			return;
		}
		$graph = array( aw_publisher_schema(), aw_website_schema() );
		if ( is_singular( array( 'aw_news', 'aw_legal', 'aw_guide' ) ) ) {
			$id      = get_queried_object_id();
			$graph[] = aw_article_schema( $id );
			if ( 'aw_guide' === get_post_type( $id ) ) {
				$graph[] = aw_faq_schema( $id );
			}
		} elseif ( is_singular( 'aw_opportunity' ) ) {
			$graph[] = aw_job_schema( get_queried_object_id() );
		} elseif ( is_singular( 'aw_campaign' ) ) {
			$graph[] = aw_campaign_schema( get_queried_object_id() );
		} elseif ( is_page( 'contact' ) ) {
			$graph[] = array(
				'@type'      => 'ContactPage',
				'@id'        => get_permalink() . '#page',
				'url'        => get_permalink(),
				'name'       => get_the_title(),
				'inLanguage' => 'en-IN',
				'isPartOf'   => array( '@id' => home_url( '/#website' ) ),
				'mainEntity' => array(
					'@type'        => 'Organization',
					'@id'          => home_url( '/#organization' ),
					'email'        => 'contact@animalwrites.in',
					'contactPoint' => array( '@type' => 'ContactPoint', 'contactType' => 'customer support', 'email' => 'contact@animalwrites.in' ),
				),
			);
		} elseif ( is_page( 'media' ) && function_exists( 'aw_press_schema' ) ) {
			$graph[] = aw_press_schema();
		} elseif ( is_tax( 'aw_topic' ) ) {
			$term    = get_queried_object();
			$graph[] = array_filter(
				array(
					'@type'       => 'CollectionPage',
					'@id'         => get_term_link( $term ) . '#page',
					'url'         => get_term_link( $term ),
					'name'        => $term->name,
					'description' => wp_strip_all_tags( term_description( $term ) ),
					'inLanguage'  => 'en-IN',
					'isPartOf'    => array( '@id' => home_url( '/#website' ) ),
					'publisher'   => array( '@id' => home_url( '/#organization' ) ),
				)
			);
		}
		$graph[] = aw_breadcrumb_schema();
		aw_jsonld( array( '@context' => 'https://schema.org', '@graph' => array_values( array_filter( $graph ) ) ) );
	},
	31
);

/**
 * Exact opening hours ("Mon-Fri 09:00-18:00", one range per line) as schema.org OpeningHoursSpecification.
 *
 * @param string $text Lines.
 * @return array
 */
function aw_hours_spec( $text ) {
	$days = array( 'mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday' );
	$keys = array_keys( $days );
	$out  = array();
	foreach ( preg_split( '/[\r\n]+/', (string) $text ) as $line ) {
		if ( ! preg_match( '/^\s*([a-z]{3})[a-z]*(?:\s*[-–]\s*([a-z]{3})[a-z]*)?\s+(\d{1,2}:\d{2})\s*[-–]\s*(\d{1,2}:\d{2})/i', $line, $mm ) ) {
			continue;
		}
		$from = array_search( strtolower( $mm[1] ), $keys, true );
		$to   = isset( $mm[2] ) && '' !== $mm[2] ? array_search( strtolower( $mm[2] ), $keys, true ) : $from;
		if ( false === $from || false === $to ) {
			continue;
		}
		$list = array();
		for ( $i = $from; ; $i = ( $i + 1 ) % 7 ) {
			$list[] = $days[ $keys[ $i ] ];
			if ( $i === $to ) {
				break;
			}
		}
		$out[] = array( '@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $list, 'opens' => $mm[3], 'closes' => $mm[4] );
	}
	return $out;
}

