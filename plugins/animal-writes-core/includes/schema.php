<?php
/**
 * Structured data (JSON-LD).
 *
 * - Listing pages: NGO / VeterinaryCare / AnimalShelter / GovernmentOrganization (by listing type, several allowed) with name, URL, logo, locality/region (no street address), contact details
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
	// schema.org type by listing type; a listing with several types gets several (@type as a list).
	$map   = array(
		'VeterinaryCare'         => array( 'veterinary-hospital', 'veterinary-clinic', 'veterinary-dispensary', 'bird-hospital', 'mobile-veterinary-unit' ),
		'AnimalShelter'          => array( 'animal-shelter', 'gaushala', 'sanctuary' ),
		'GovernmentOrganization' => array( 'veterinary-office', 'mobile-veterinary-unit' ),
	);
	$stype = array();
	foreach ( $map as $t => $slugs ) {
		if ( has_term( $slugs, 'aw_listing_type', $id ) ) {
			$stype[] = $t;
		}
	}
	if ( in_array( 'VeterinaryCare', $stype, true ) && ! in_array( 'GovernmentOrganization', $stype, true ) && in_array( 'gov', wp_list_pluck( aw_listing_sources( $id ), 0 ), true ) ) {
		$stype[] = 'GovernmentOrganization'; // a government vet (from a government list)
	}
	if ( ! $stype || has_term( array( 'animal-welfare-organisation', 'abc-centre', 'rescue-group', 'adoption-network', 'feeder-network', 'legal-aid', 'emergency-rescue', 'wildlife-rescue', 'animal-ambulance', 'boarding-and-foster' ), 'aw_listing_type', $id ) ) {
		array_unshift( $stype, 'NGO' );
	}
	$stype = array_values( array_unique( $stype ) );
	$vet   = (bool) array_intersect( array( 'VeterinaryCare', 'AnimalShelter' ), $stype ); // places (LocalBusiness): free-text hours allowed
	$site  = $m( 'aw_website' );
	$org   = array(
		'@type'       => 1 === count( $stype ) ? $stype[0] : $stype,
		'@id'         => get_permalink( $id ) . '#organization',
		'name'        => aw_seo_plain( get_post_field( 'post_title', $id ) ), // no "Private:" prefix
		'url'         => $site ? $site : get_permalink( $id ),                 // the organisation's own site when known
		'mainEntityOfPage' => get_permalink( $id ),                           // this profile
		'description' => aw_seo_plain( get_the_excerpt( $id ) ),
	);
	$logo = get_the_post_thumbnail_url( $id, 'medium' );
	if ( $logo ) {
		$org['logo']  = $logo;
		$org['image'] = $logo;
	}
	if ( $state || $district ) {
		$street = $m( 'aw_area' );
		$rest   = str_ireplace( array_filter( array( $district, $state, 'India' ) ), '', $street );
		if ( '' === trim( preg_replace( '/[\s,.\-–]+/u', '', $rest ) ) ) {
			$street = ''; // only repeats the district and state
		}
		$org['address'] = array_filter( array( '@type' => 'PostalAddress', 'streetAddress' => $street, 'addressLocality' => $district, 'addressRegion' => $state, 'postalCode' => $m( 'aw_pincode' ), 'addressCountry' => 'IN' ) );
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

	$svc = wp_get_object_terms( $id, 'aw_service', array( 'fields' => 'names' ) );
	if ( $svc && ! is_wp_error( $svc ) ) {
		$org['knowsAbout'] = array_values( $svc ); // the fixed services list
	} elseif ( $m( 'aw_services' ) ) {
		$org['knowsAbout'] = array_values( array_filter( array_map( 'trim', preg_split( '/[,;\n]+/', wp_strip_all_tags( $m( 'aw_services' ) ) ) ) ) );
	}
	// Everything else the listing states (owner, 12 Oct 2026): services as a catalogue, the head of the organisation,
	// and the donate / volunteer links as actions.
	if ( ! empty( $org['knowsAbout'] ) ) {
		$org['hasOfferCatalog'] = array(
			'@type'           => 'OfferCatalog',
			'name'            => 'Services',
			'itemListElement' => array_map( fn( $s ) => array( '@type' => 'Offer', 'itemOffered' => array( '@type' => 'Service', 'name' => $s ) ), $org['knowsAbout'] ),
		);
	}
	if ( $m( 'aw_head' ) ) { // "Name, Role" as the organisation states it
		$head            = array_map( 'trim', explode( ',', wp_strip_all_tags( $m( 'aw_head' ) ), 2 ) );
		$org['founder']  = null; // only when the stated role says so
		$person          = array_filter( array( '@type' => 'Person', 'name' => $head[0], 'jobTitle' => $head[1] ?? '' ) );
		if ( isset( $head[1] ) && preg_match( '/found/i', $head[1] ) ) {
			$org['founder'] = $person;
		} else {
			$org['employee'] = $person;
		}
		$org = array_filter( $org, fn( $v ) => null !== $v );
	}
	$acts = array();
	if ( $m( 'aw_donate_url' ) ) {
		$acts[] = array( '@type' => 'DonateAction', 'target' => esc_url_raw( $m( 'aw_donate_url' ) ), 'recipient' => array( '@id' => $org['@id'] ?? '' ) );
	}
	if ( $m( 'aw_volunteer_url' ) ) {
		$acts[] = array( '@type' => 'JoinAction', 'name' => 'Volunteer', 'target' => esc_url_raw( $m( 'aw_volunteer_url' ) ) );
	}
	if ( $acts ) {
		$org['potentialAction'] = 1 === count( $acts ) ? $acts[0] : $acts;
	}
	$types = get_the_terms( $id, 'aw_listing_type' );
	if ( $types && ! is_wp_error( $types ) ) {
		$org['keywords'] = implode( ', ', array_filter( array_merge( wp_list_pluck( $types, 'name' ), array( $m( 'aw_g_category' ) ) ) ) ); // additionalType must be a URL; the type names (and Google's category) go here
	}
	$code = $m( 'aw_awbi_code' );
	if ( $code ) {
		$org['identifier'] = array( '@type' => 'PropertyValue', 'propertyID' => 'AWBI recognition code', 'value' => $code );
	}
	$ids = isset( $org['identifier'] ) ? array( $org['identifier'] ) : array();
	foreach ( array( 'aw_registration' => 'Registration', 'aw_reg_12a' => '12A', 'aw_reg_80g' => '80G', 'aw_darpan_id' => 'NGO Darpan ID' ) as $k => $pid ) {
		if ( $m( $k ) ) { // as the organisation states them; words without a number are a note, not an identifier
			$ids[] = preg_match( '/\d/', $m( $k ) ) ? array( '@type' => 'PropertyValue', 'propertyID' => $pid, 'value' => $m( $k ) ) : array( '@type' => 'PropertyValue', 'propertyID' => $pid, 'description' => $m( $k ) );
		}
	}
	if ( $ids ) {
		$org['identifier'] = 1 === count( $ids ) ? $ids[0] : $ids;
	}
	return array_filter( $org );
}


/* ---------- Site and content pages ---------- */

/** The publisher (Animal Writes) as schema.org Organization. */
function aw_publisher_schema() {
	$logo  = get_site_icon_url( 512 );
	$front = (int) get_option( 'page_on_front' );
	$desc  = $front ? (string) get_post_meta( $front, 'rank_math_description', true ) : ''; // the home page's meta description
	$desc  = '' !== trim( $desc ) ? $desc : ( $front ? (string) get_post_field( 'post_excerpt', $front ) : '' );
	$desc  = '' !== trim( $desc ) ? $desc : get_bloginfo( 'description' );
	$about = get_terms( array( 'taxonomy' => 'aw_topic', 'hide_empty' => true, 'fields' => 'names' ) );
	$pages = array();
	foreach ( array( 'publishingPrinciples' => 'editorial-policy', 'correctionsPolicy' => 'corrections' ) as $k => $slug ) {
		$page = get_page_by_path( $slug );
		if ( $page && 'publish' === $page->post_status ) {
			$pages[ $k ] = get_permalink( $page );
		}
	}
	return array_filter(
		array_merge(
			array(
				'@type'         => 'Organization',
				'@id'           => home_url( '/#organization' ),
				'name'          => get_bloginfo( 'name' ),
				'alternateName' => array( 'Animal Writes India', 'AnimalWrites.in' ),
				'url'           => home_url( '/' ),
				'description'   => aw_seo_plain( $desc ),
				'logo'          => $logo ? array( '@type' => 'ImageObject', 'url' => $logo ) : null,
				'email'         => 'contact@animalwrites.in', // as on the Contact page
				'contactPoint'  => array( '@type' => 'ContactPoint', 'contactType' => 'customer support', 'email' => 'contact@animalwrites.in', 'url' => home_url( '/contact/' ) ),
				'areaServed'    => array( '@type' => 'Country', 'name' => 'India', 'identifier' => 'IN' ),
				'knowsAbout'    => is_array( $about ) ? array_values( array_map( 'aw_seo_plain', $about ) ) : null,
				'sameAs'        => array( 'https://www.facebook.com/AnimalWritesIndia/', 'https://www.instagram.com/animalwrites_india/', 'https://x.com/AnimalWrites' ),
			),
			$pages
		)
	);
}

/**
 * A date as full ISO 8601 with offset; a bare date (Y-m-d) becomes 09:00 India time.
 *
 * @param string $date Date.
 * @return string
 */
function aw_schema_date( $date ) {
	$date = trim( (string) $date );
	if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
		return $date . 'T09:00:00+05:30';
	}
	$ts = $date ? strtotime( $date ) : false;
	return $ts ? wp_date( 'c', $ts ) : '';
}

/**
 * ImageObject for an item: the same stored image its og:image uses (kits-core seo.php), with its size. Never the
 * publisher's image. Null when the site has no image to give.
 *
 * @param int $id Post ID.
 * @return array|null
 */
function aw_schema_image( $id ) {
	if ( ! function_exists( 'kits_seo_image' ) ) {
		return null;
	}
	$img = ( is_singular() && get_queried_object_id() === (int) $id ) ? kits_seo_image() : ( kits_seo_item_image( $id ) ? kits_seo_item_image( $id ) : kits_seo_default_image() );
	return $img ? array_filter( array( '@type' => 'ImageObject', 'url' => $img['url'], 'width' => $img['width'], 'height' => $img['height'] ) ) : null;
}

/**
 * Article-type data for a content post (news, legal, research, guide).
 *
 * @param int $id Post ID.
 * @return array
 */
function aw_article_schema( $id ) {
	$type  = get_post_type( $id );
	$title = aw_seo_plain( get_the_title( $id ) );
	$at    = 'Article';
	if ( 'aw_legal' === $type && has_term( array( 'act', 'rule', 'notification', 'circular' ), 'aw_legal_kind', $id ) ) {
		$at = 'Legislation';
	} elseif ( 'aw_legal' === $type && has_term( 'template', 'aw_legal_kind', $id ) ) {
		$at = 'Article';
	} elseif ( in_array( $type, array( 'aw_news', 'aw_legal' ), true ) ) {
		$at = 'NewsArticle';
	}
	$over     = trim( (string) get_post_meta( $id, 'kits_schema_type', true ) ); // editor's choice (SEO box)
	$at       = $over ? $over : $at;
	$image    = aw_schema_image( $id );
	$reviewed = (string) get_post_meta( $id, 'kits_last_reviewed', true );
	$src_url  = (string) get_post_meta( $id, 'aw_source_url', true );
	$src_name = (string) get_post_meta( $id, 'aw_source_name', true );
	$data     = array(
		'@type'            => $at,
		'@id'              => get_permalink( $id ) . ( 'Legislation' === $at ? '#legislation' : '#article' ),
		'mainEntityOfPage' => get_permalink( $id ),
		'url'              => get_permalink( $id ),
		'description'      => function_exists( 'aw_text' ) ? aw_seo_plain( aw_text( $id, 40 ) ) : '',
		'datePublished'    => get_the_date( 'c', $id ),
		'dateModified'     => $reviewed ? aw_schema_date( $reviewed ) : get_the_modified_date( 'c', $id ),
		'inLanguage'       => 'en-IN',
		'publisher'        => array( '@id' => home_url( '/#organization' ) ),
	);
	$data[ 'Legislation' === $at ? 'name' : 'headline' ] = $title;
	if ( $image ) {
		$data['image'] = $image;
	}
	$iss = function_exists( 'aw_item_issuer' ) ? aw_item_issuer( $id ) : null;
	if ( 'Legislation' !== $at ) {
		$data['author'] = array( '@id' => home_url( '/#organization' ) ); // the summary or guide is ours
		if ( 'aw_legal' === $type && $iss && in_array( $iss->slug, AW_COURT_ISSUERS, true ) ) { // a court ruling
			$data['genre'] = 'Court judgment';
			$data['about'] = array( '@type' => 'GovernmentOrganization', 'name' => $iss->name );
			$order         = aw_court_document_url( $id );
			if ( $order ) {
				$data['citation'] = $order;
			}
		}
	} else {
		$data['legislationJurisdiction'] = 'IN';
		$kind = function_exists( 'aw_first_term' ) ? aw_first_term( $id, 'aw_legal_kind' ) : null;
		if ( $kind ) {
			$data['legislationType'] = $kind->name;
		}
		if ( $iss && 'gazette' !== $iss->slug ) { // the Gazette publishes; it does not pass the law
			$data['legislationPassedBy'] = array( '@type' => 'GovernmentOrganization', 'name' => $iss->name );
		}
		$on = (string) get_post_meta( $id, 'aw_source_date', true );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $on ) ) {
			$data['legislationDate'] = $on;
		}
	}
	if ( $src_url ) {
		// The original item we summarise or host.
		$pub = $src_name ? aw_seo_plain( $src_name ) : '';
		if ( 'Legislation' === $at && $iss && 'gazette' === $iss->slug ) {
			$pub = $iss->name;
		}
		$data['isBasedOn'] = array_filter( array( '@type' => 'CreativeWork', 'url' => $src_url, 'publisher' => $pub ? array( '@type' => 'Organization', 'name' => $pub ) : null ) );
		$doi = preg_match( '#^https?://(?:dx\.)?doi\.org/(10\..+)$#i', $src_url, $mm ) ? rawurldecode( $mm[1] ) : '';
		if ( 'post' === $type && $doi ) { // a research paper: its DOI and journal
			$data['isBasedOn'] = array_filter(
				array(
					'@type'      => 'ScholarlyArticle',
					'url'        => $src_url,
					'sameAs'     => 'https://doi.org/' . $doi,
					'identifier' => array( '@type' => 'PropertyValue', 'propertyID' => 'DOI', 'value' => $doi ),
					'isPartOf'   => $pub ? array( '@type' => 'Periodical', 'name' => $pub ) : null,
				)
			);
		}
	}
	return array_filter( $data );
}

/** The court's own copy of a ruling, when stored: the hosted file's official source or a court website / PDF. */
function aw_court_document_url( $id ) {
	foreach ( array( (string) get_post_meta( $id, 'aw_source_url', true ), (string) get_post_meta( $id, 'aw_file_url', true ) ) as $url ) {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( $host && ( preg_match( '/(^|\.)(gov\.in|nic\.in)$/', $host ) || false !== strpos( $host, 'supreme-court-judgments' ) || preg_match( '/\.pdf$/i', (string) wp_parse_url( $url, PHP_URL_PATH ) ) ) ) {
			return $url;
		}
	}
	return '';
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
			'name'          => aw_seo_plain( get_the_title( $id ) ),
			'description'   => function_exists( 'aw_text' ) ? aw_seo_plain( aw_text( $id, 40 ) ) : '',
			'datePublished' => get_the_date( 'c', $id ),
			'dateModified'  => get_the_modified_date( 'c', $id ),
			'inLanguage'    => 'en-IN',
			'image'         => $image ? array( $image ) : null,
			'about'         => $topic ? array( '@type' => 'Thing', 'name' => $topic->name ) : null,
			'creator'       => $org && 'publish' === get_post_status( $org ) ? array( '@type' => 'NGO', 'name' => aw_seo_plain( get_the_title( $org ) ), 'url' => get_permalink( $org ) ) : array( '@id' => home_url( '/#organization' ) ),
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
		$list[] = array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => aw_seo_plain( $it[0] ), 'item' => $it[1] );
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
	if ( function_exists( 'kits_seo_faqs' ) && kits_seo_faqs( $id ) ) {
		return array(); // the FAQ field's own FAQPage schema (kits-core) is printed instead
	}
	$html = (string) apply_filters( 'the_content', get_post_field( 'post_content', $id ) );
	if ( ! preg_match_all( '#<h[23][^>]*>(.*?\?)\s*</h[23]>(.*?)(?=<h[23]|$)#si', $html, $m, PREG_SET_ORDER ) || count( $m ) < 2 ) {
		return array();
	}
	$qs = array();
	foreach ( $m as $row ) {
		$answer = trim( wp_strip_all_tags( $row[2] ) );
		if ( '' !== $answer ) {
			$qs[] = array( '@type' => 'Question', 'name' => aw_seo_plain( $row[1] ), 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => aw_seo_plain( $answer ) ) );
		}
	}
	return count( $qs ) >= 2 ? array( '@type' => 'FAQPage', '@id' => get_permalink( $id ) . '#faq', 'mainEntity' => $qs ) : array();
}

/** JobPosting for every opportunity: remote ones as TELECOMMUTE, others at their place (else India). */
function aw_job_schema( $id ) {
	list( $state, $district ) = function_exists( 'aw_listing_place' ) ? aw_listing_place( $id ) : array( '', '' );
	$area   = trim( (string) get_post_meta( $id, 'aw_area', true ) . ' ' . (string) get_post_meta( $id, 'aw_place_city', true ) );
	$org_id = (int) get_post_meta( $id, 'aw_organisation', true );
	$remote = (bool) preg_match( '/\b(remote|online|anywhere|work from home|wfh|virtual)\b/i', $area . ' ' . $state . ' ' . $district );
	if ( ! $remote && ! $state && ! $district && ! $area && $org_id ) {
		list( $state, $district ) = aw_listing_place( $org_id ); // the organisation's own location
	}
	$org      = (string) get_post_meta( $id, 'aw_org_name', true );
	$org      = $org ? $org : ( $org_id && 'publish' === get_post_status( $org_id ) ? get_post_field( 'post_title', $org_id ) : '' );
	$org_site = (string) get_post_meta( $id, 'aw_website', true );
	if ( ! $org_site && $org_id ) {
		$org_site = (string) get_post_meta( $org_id, 'aw_website', true );
	}
	$deadline = (string) get_post_meta( $id, 'aw_deadline', true );
	if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $deadline ) && $deadline < wp_date( 'Y-m-d' ) ) {
		return array(); // closed: no JobPosting after its validThrough date
	}
	$desc     = trim( preg_replace( '/<!--.*?-->/s', '', (string) get_post_field( 'post_content', $id ) ) ); // no block comments
	$desc     = $desc ? $desc : ( function_exists( 'aw_text' ) ? aw_text( $id, 60 ) : '' );
	$desc     = $desc ? $desc : (string) get_post_field( 'post_excerpt', $id );
	$desc     = $desc ? $desc : get_the_title( $id );
	$emp      = array();
	if ( has_term( 'internship', 'aw_opportunity_type', $id ) ) {
		$emp[] = 'INTERN';
	}
	if ( has_term( 'volunteer', 'aw_opportunity_type', $id ) ) {
		$emp[] = 'VOLUNTEER';
	}
	$data = array(
		'@type'              => 'JobPosting',
		'@id'                => get_permalink( $id ) . '#job',
		'title'              => aw_seo_plain( get_the_title( $id ) ),
		'description'        => trim( preg_replace( '/\n{3,}/', "\n\n", wp_kses_post( $desc ) ) ),
		'datePosted'         => get_the_date( 'c', $id ),
		'url'                => get_permalink( $id ),
		'employmentType'     => $emp ? ( 1 === count( $emp ) ? $emp[0] : $emp ) : null,
		'hiringOrganization' => $org ? array_filter( array( '@type' => 'Organization', 'name' => aw_seo_plain( $org ), 'sameAs' => $org_site, 'url' => $org_id && 'publish' === get_post_status( $org_id ) ? get_permalink( $org_id ) : null ) ) : null,
		'validThrough'       => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $deadline ) ? $deadline . 'T23:59:59+05:30' : null,
	);
	if ( $remote ) {
		$data['jobLocationType']                 = 'TELECOMMUTE';
		$data['applicantLocationRequirements'] = array( '@type' => 'Country', 'name' => 'India' );
	} else {
		$data['jobLocation'] = array(
			'@type'   => 'Place',
			'address' => array_filter( array( '@type' => 'PostalAddress', 'addressLocality' => $district ? $district : ( $area ? $area : null ), 'addressRegion' => $state, 'addressCountry' => 'IN' ) ),
		);
	}
	return array_filter( $data );
}

/** The Topics page: CollectionPage + ItemList of every topic. */
function aw_topics_schema() {
	$url   = get_permalink();
	$items = array();
	foreach ( get_terms( array( 'taxonomy' => 'aw_topic', 'hide_empty' => true, 'orderby' => 'name' ) ) as $i => $t ) {
		$items[] = array( '@type' => 'ListItem', 'position' => $i + 1, 'url' => get_term_link( $t ), 'name' => aw_seo_plain( $t->name ) );
	}
	return array(
		array(
			'@type'       => 'CollectionPage',
			'@id'         => $url . '#page',
			'url'         => $url,
			'name'        => aw_seo_plain( get_the_title() ),
			'description' => function_exists( 'aw_seo_description' ) ? aw_seo_description() : '',
			'inLanguage'  => 'en-IN',
			'isPartOf'    => array( '@id' => home_url( '/#website' ) ),
			'publisher'   => array( '@id' => home_url( '/#organization' ) ),
			'mainEntity'  => array( '@id' => $url . '#list' ),
		),
		array( '@type' => 'ItemList', '@id' => $url . '#list', 'numberOfItems' => count( $items ), 'itemListElement' => $items ),
	);
}

/**
 * The schema.org nodes of the current page's own content (not the publisher, the site or the breadcrumb trail):
 * the article, legislation, job, campaign, listing organisation, contact page, hub list or topic collection.
 *
 * @return array List of nodes.
 */
function aw_schema_nodes() {
	$nodes = array();
	if ( is_404() ) {
		return $nodes;
	}
	if ( is_singular( array( 'aw_news', 'aw_legal', 'post' ) ) ) {
		$id      = get_queried_object_id();
		$nodes[] = aw_article_schema( $id );
		if ( 'post' === get_post_type( $id ) ) {
			$nodes[] = aw_faq_schema( $id );
		}
	} elseif ( is_singular( 'aw_listing' ) ) {
		$nodes[] = aw_listing_schema( get_queried_object_id() );
	} elseif ( is_singular( 'aw_opportunity' ) ) {
		$nodes[] = aw_job_schema( get_queried_object_id() );
	} elseif ( is_singular( 'aw_campaign' ) ) {
		$nodes[] = aw_campaign_schema( get_queried_object_id() );
	} elseif ( is_page( 'contact' ) ) {
		$nodes[] = array(
			'@type'      => 'ContactPage',
			'@id'        => get_permalink() . '#page',
			'url'        => get_permalink(),
			'name'       => aw_seo_plain( get_the_title() ),
			'inLanguage' => 'en-IN',
			'isPartOf'   => array( '@id' => home_url( '/#website' ) ),
			'mainEntity' => array(
				'@type'        => 'Organization',
				'@id'          => home_url( '/#organization' ),
				'email'        => 'contact@animalwrites.in',
				'contactPoint' => array( '@type' => 'ContactPoint', 'contactType' => 'customer support', 'email' => 'contact@animalwrites.in' ),
			),
		);
	} elseif ( is_page( 'about' ) ) {
		$nodes[] = array( '@type' => 'AboutPage', '@id' => get_permalink() . '#webpage', 'url' => get_permalink(), 'name' => aw_seo_plain( get_the_title() ), 'inLanguage' => 'en-IN', 'isPartOf' => array( '@id' => home_url( '/#website' ) ), 'mainEntity' => array( '@id' => home_url( '/#organization' ) ) );
	} elseif ( is_page( 'topics' ) ) {
		$nodes = array_merge( $nodes, aw_topics_schema() );
	} elseif ( is_page( 'media' ) && function_exists( 'aw_press_schema' ) ) {
		$nodes[] = aw_press_schema();
	} elseif ( function_exists( 'aw_hub_schema_nodes' ) && aw_hub_schema_nodes() ) {
		$nodes = array_merge( $nodes, aw_hub_schema_nodes() ); // hubs and directory places: CollectionPage + ItemList (hubs.php)
	} elseif ( is_tax( 'aw_topic' ) ) {
		$term    = get_queried_object();
		$nodes[] = array_filter(
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
	return array_values( array_filter( $nodes ) );
}

// Without Rank Math: one graph per page, the publisher and the site, the page's own nodes and its breadcrumb trail.
add_action(
	'wp_head',
	function () {
		if ( is_404() || ( function_exists( 'kits_rank_math' ) && kits_rank_math() ) ) {
			return;
		}
		$graph = array_merge( array( aw_publisher_schema(), aw_website_schema() ), aw_schema_nodes(), array( aw_breadcrumb_schema() ) );
		aw_jsonld( array( '@context' => 'https://schema.org', '@graph' => array_values( array_filter( $graph ) ) ) );
	},
	31
);

// With Rank Math: its one graph carries ours. Our publisher and site replace its defaults (same @id); our item nodes
// replace its automatic article; our breadcrumb trail is the one people see; a page that is itself a collection or a
// contact page replaces Rank Math's plain WebPage. Schemas an editor adds in Rank Math are kept.
add_filter(
	'rank_math/json_ld',
	function ( $data ) {
		if ( is_404() ) {
			return array(); // a missing page has no structured data (as before)
		}
		$data              = (array) $data;
		$data['publisher'] = aw_publisher_schema();
		$data['WebSite']   = aw_website_schema();
		unset( $data['ProfilePage'] ); // authors are not shown on this site
		$nodes = aw_schema_nodes();
		if ( $nodes ) {
			unset( $data['richSnippet'] );
		}
		foreach ( $nodes as $node ) {
			if ( in_array( $node['@type'] ?? '', array( 'CollectionPage', 'ContactPage' ), true ) ) {
				unset( $data['WebPage'], $data['primaryImage'] );
			}
		}
		foreach ( $nodes as $i => $node ) {
			if ( 'AboutPage' === ( $node['@type'] ?? '' ) && isset( $data['WebPage'] ) && is_array( $data['WebPage'] ) ) {
				$data['WebPage'] = array_merge( $data['WebPage'], array( '@type' => 'AboutPage', 'mainEntity' => $node['mainEntity'] ) ); // Rank Math's page, as an About page
				continue;
			}
			$data[ 'aw_node_' . $i ] = $node;
		}
		if ( isset( $data['kits_faq'] ) ) {
			foreach ( $nodes as $node ) {
				if ( 'FAQPage' === ( $node['@type'] ?? '' ) ) {
					unset( $data['kits_faq'] );
				}
			}
		}
		$crumbs = aw_breadcrumb_schema();
		unset( $data['BreadcrumbList'] );
		if ( $crumbs ) {
			$crumbs['@id']          = ( function_exists( 'kits_seo_canonical' ) ? kits_seo_canonical() : get_permalink() ) . '#breadcrumb';
			$data['BreadcrumbList'] = $crumbs;
			if ( isset( $data['WebPage'] ) && is_array( $data['WebPage'] ) ) {
				$data['WebPage']['breadcrumb'] = array( '@id' => $crumbs['@id'] );
			}
		}
		return $data;
	},
	999
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

