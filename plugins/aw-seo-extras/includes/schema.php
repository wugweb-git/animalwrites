<?php
/**
 * The site's own JSON-LD nodes, put into Rank Math's one graph (Rank Math's schema templates cannot build these from
 * the site's fields):
 *
 * - Listings: NGO / VeterinaryCare / AnimalShelter / GovernmentOffice (by listing type, several allowed) with
 *   name, URL, logo, locality/region, contact details when present, the official website as sameAs, identifiers
 *   (AWBI code, registration, 12A, 80G, Darpan), services, opening hours, donate and volunteer actions.
 * - News, Library and Resources: one purpose-made node per type and sub-type (aw_article_nodes: NewsArticle,
 *   Legislation, Article on a court ruling / press release / government document, DigitalDocument, Article + HowTo,
 *   Article on a ScholarlyArticle). A guide's FAQPage is Rank Math's own (its FAQ block). Opportunities: JobPosting for
 *   open jobs, internships, fellowships and volunteering (none once closed, none for events or fostering). Campaigns:
 *   Event when the day is stored, else CreativeWork. Rank Math's Titles & Meta hold each type's schema type; these
 *   nodes replace its default, which cannot carry these properties.
 * - Section hubs, Directory places and category pages: CollectionPage + ItemList of the page's items. Categories
 *   page: CollectionPage + ItemList of the categories. Media & PR: CollectionPage of press items. Contact: ContactPage. About: AboutPage.
 * - FAQPage (listings, Library documents, type/service/category pages, four hubs): parsed from the page's own FAQ
 *   accordion (faq.php, in this plugin), so its text is the visible text; isPartOf the page, about its main entity.
 * - Every page: the publisher (Organization, with publishing and corrections policies) and the site (WebSite with the
 *   site's own search address), replacing Rank Math's defaults (same @id). The BreadcrumbList is Rank Math's own
 *   (breadcrumbs.php gives it the trail people see).
 *   Every node's inLanguage is en-IN (Rank Math writes the WordPress locale, en-US, on its WebPage).
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
		'GovernmentOffice'       => array( 'veterinary-office', 'mobile-veterinary-unit' ), // a government office the public visits
	);
	$stype = array();
	foreach ( $map as $t => $slugs ) {
		if ( has_term( $slugs, 'post_tag', $id ) ) {
			$stype[] = $t;
		}
	}
	if ( in_array( 'VeterinaryCare', $stype, true ) && ! in_array( 'GovernmentOffice', $stype, true ) && array_intersect( array( 'gov', 'gujforest' ), wp_list_pluck( aw_listing_sources( $id ), 0 ) ) ) {
		$stype[] = 'GovernmentOffice'; // a government vet (from a government list)
	}
	$srcs = wp_list_pluck( aw_listing_sources( $id ), 0 );
	if ( has_term( 'legal-aid', 'post_tag', $id ) && in_array( 'nalsa', $srcs, true ) ) {
		$stype[] = 'GovernmentOffice'; // a District Legal Services Authority: a government office, not an NGO
	}
	$ngo = array( 'animal-welfare-organisation', 'abc-centre', 'rescue-group', 'adoption-network', 'feeder-network', 'emergency-rescue', 'wildlife-rescue', 'animal-ambulance' );
	if ( ! in_array( 'nalsa', $srcs, true ) ) {
		$ngo[] = 'legal-aid';
	}
	if ( ! $stype || has_term( $ngo, 'post_tag', $id ) ) {
		array_unshift( $stype, 'NGO' );
	}
	$stype = array_values( array_unique( $stype ) );
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
		$street = $m( 'aw_address' ) ? $m( 'aw_address' ) : $m( 'aw_area' ); // the full address when a source gives it
		$rest   = str_ireplace( array_filter( array( $district, $state, 'India' ) ), '', $street );
		if ( '' === trim( preg_replace( '/[\s,.\-–]+/u', '', $rest ) ) ) {
			$street = ''; // only repeats the district and state
		}
		$org['address'] = array_filter( array( '@type' => 'PostalAddress', 'streetAddress' => $street, 'addressLocality' => $district, 'addressRegion' => $state, 'postalCode' => $m( 'aw_pincode' ), 'addressCountry' => 'IN' ) );
		$org['areaServed'] = array_values( array_filter( array( $district ? array( '@type' => 'AdministrativeArea', 'name' => $district ) : null, $state ? array( '@type' => 'State', 'name' => $state ) : null ) ) );
		foreach ( array_filter( array_map( 'trim', explode( ',', $m( 'aw_areas_served' ) ) ) ) as $area ) { // as the organisation states them
			if ( ! in_array( $area, array( $district, $state ), true ) ) {
				$org['areaServed'][] = array( '@type' => 'Place', 'name' => $area );
			}
		}
	}
	// Phone numbers from the list: every number as telephone (one, or a list), and each number marked WhatsApp as a
	// WhatsApp contact point.
	$phones = function_exists( 'aw_listing_phones' ) ? aw_listing_phones( $id ) : array();
	$tels   = array_values( array_unique( wp_list_pluck( $phones, 'number' ) ) );
	if ( $tels ) {
		$org['telephone'] = 1 === count( $tels ) ? $tels[0] : $tels;
	}
	if ( $m( 'aw_email' ) ) {
		$org['email'] = $m( 'aw_email' );
	}
	$wa = array();
	foreach ( $phones as $p ) {
		if ( $p['whatsapp'] ) {
			$wa[] = array( '@type' => 'ContactPoint', 'contactType' => 'WhatsApp', 'telephone' => $p['number'], 'availableLanguage' => array( 'en', 'hi' ) );
		}
	}
	if ( $wa ) {
		$org['contactPoint'] = 1 === count( $wa ) ? $wa[0] : $wa;
	}
	$same = array_values( array_filter( array( $site, $m( 'aw_g_profile' ), $m( 'aw_instagram' ), $m( 'aw_facebook' ), $m( 'aw_x' ), $m( 'aw_youtube' ), $m( 'aw_linkedin' ), $m( 'aw_wikidata' ) ? 'https://www.wikidata.org/wiki/' . $m( 'aw_wikidata' ) : '', $m( 'aw_ngobase_url' ) ) ) );
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
	$own    = function_exists( 'aw_listing_photo_ids' ) ? aw_listing_photo_ids( $id ) : array_filter( explode( ',', $m( 'aw_gallery' ) ) ); // never stock or CC images
	$photos = array_values( array_filter( array_map( fn( $p ) => (string) wp_get_attachment_image_url( (int) $p, 'large' ), $own ) ) );
	if ( $photos ) {
		$org['image'] = array_values( array_unique( array_merge( (array) ( $org['image'] ?? array() ), $photos ) ) );
	}
	if ( $m( 'aw_founded' ) ) {
		$org['foundingDate'] = $m( 'aw_founded' );
	}
	if ( $m( 'aw_languages' ) ) {
		$org['knowsLanguage'] = array_values( array_filter( array_map( 'trim', explode( ',', $m( 'aw_languages' ) ) ) ) );
	}
	// Opening hours by day, from the list.
	$spec = function_exists( 'aw_listing_hours' ) ? aw_hours_rows_spec( aw_listing_hours( $id ) ) : array();
	if ( $spec ) {
		$org['openingHoursSpecification'] = $spec;
	}

	$svc = function_exists( 'aw_listing_service_names' ) ? aw_listing_service_names( $id ) : array();
	if ( $svc ) {
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
	$types = function_exists( 'aw_listing_types' ) ? aw_listing_types( $id ) : array();
	if ( $types ) {
		$org['keywords'] = implode( ', ', array_filter( array_merge( wp_list_pluck( $types, 'name' ), array( $m( 'aw_g_category' ) ) ) ) ); // additionalType must be a URL; the type names (and Google's category) go here
	}
	$code = $m( 'aw_awbi_code' );
	if ( $code ) {
		$org['identifier'] = array( '@type' => 'PropertyValue', 'propertyID' => 'AWBI recognition code', 'value' => $code );
		if ( $m( 'aw_awbi_dropped' ) ) { // on an older AWBI list only
			$org['identifier']['description'] = 'Not on the AWBI list of ' . wp_date( 'j M Y', strtotime( $m( 'aw_awbi_dropped' ) ) ) . '; recognition may have lapsed';
		}
	}
	$ids = isset( $org['identifier'] ) ? array( $org['identifier'] ) : array();
	foreach ( array( 'aw_registration' => 'Registration', 'aw_reg_12a' => '12A', 'aw_reg_80g' => '80G', 'aw_fcra' => 'FCRA', 'aw_darpan_id' => 'NGO Darpan ID' ) as $k => $pid ) {
		if ( $m( $k ) ) { // as the organisation states them; words without a number are a note, not an identifier
			$ids[] = preg_match( '/\d/', $m( $k ) ) ? array( '@type' => 'PropertyValue', 'propertyID' => $pid, 'value' => $m( $k ) ) : array( '@type' => 'PropertyValue', 'propertyID' => $pid, 'description' => $m( $k ) );
		}
	}
	if ( $ids ) {
		$org['identifier'] = 1 === count( $ids ) ? $ids[0] : $ids;
	}
	if ( function_exists( 'aw_featured_subject_schema' ) && aw_featured_subject_schema( $id ) ) {
		$org['subjectOf'] = aw_featured_subject_schema( $id ); // what it is featured in: news, Library, resources, press, campaigns
	}
	if ( function_exists( 'aw_org_people_schema' ) && aw_org_people_schema( $id ) ) {
		$org['member'] = aw_org_people_schema( $id ); // volunteers shown on the page (OrganizationRole, roleName Volunteer)
	}
	return array_filter( $org );
}

/* ---------- Site and content pages ---------- */

/**
 * A Rank Math setting (Titles & Meta → Local SEO / Social Meta), '' when Rank Math does not hold it.
 *
 * @param string $key Setting under "titles.".
 * @return mixed
 */
function aw_seo_rm_setting( $key ) {
	return class_exists( '\RankMath\Helper' ) ? \RankMath\Helper::get_settings( 'titles.' . $key ) : '';
}

/**
 * The publisher's profiles (sameAs): Rank Math's Facebook URL and additional profiles (Titles & Meta → Social Meta),
 * else the profiles the site footer links to. One node for both: Rank Math's own publisher is replaced by this one.
 *
 * @return array
 */
function aw_publisher_profiles() {
	$list = array_merge( array( (string) aw_seo_rm_setting( 'social_url_facebook' ) ), preg_split( '/\s+/', (string) aw_seo_rm_setting( 'social_additional_profiles' ) ) );
	$list = array_values( array_unique( array_filter( array_map( 'trim', $list ), fn( $u ) => (bool) wp_http_validate_url( $u ) ) ) );
	return $list ? $list : array( 'https://www.facebook.com/AnimalWritesIndia/', 'https://www.instagram.com/animalwrites_india/', 'https://x.com/AnimalWrites' );
}

/**
 * Profile links in the graph use x.com: Rank Math builds https://twitter.com/{Twitter username} from its Social Meta
 * setting (kept for the twitter:site tag) next to the x.com address in the additional profiles. Each sameAs list is
 * rewritten to x.com and de-duplicated, in any node.
 *
 * @param mixed $node Graph or node.
 * @return mixed
 */
function aw_schema_x_profiles( $node ) {
	if ( ! is_array( $node ) ) {
		return $node;
	}
	foreach ( $node as $k => $v ) {
		if ( 'sameAs' === $k && ( is_array( $v ) || is_string( $v ) ) ) {
			$list = array_map( fn( $u ) => is_string( $u ) ? preg_replace( '#^https?://(www\.)?twitter\.com/#i', 'https://x.com/', $u ) : $u, (array) $v );
			$node[ $k ] = is_string( $v ) ? $list[0] : array_values( array_unique( $list, SORT_REGULAR ) );
		} elseif ( is_array( $v ) ) {
			$node[ $k ] = aw_schema_x_profiles( $v );
		}
	}
	return $node;
}

/**
 * The publisher (Animal Writes) as schema.org Organization: name, URL, logo, email, description and profiles from
 * Rank Math's Knowledge Graph settings (Titles & Meta → Local SEO, Social Meta), so the settings and the graph agree;
 * the alternate names, policies, categories and country served are added here (Rank Math has no fields for them).
 */
function aw_publisher_schema() {
	$logo  = (string) aw_seo_rm_setting( 'knowledgegraph_logo' );
	$logo  = '' !== $logo ? $logo : get_site_icon_url( 512 );
	$lmeta = wp_get_attachment_metadata( (int) aw_seo_rm_setting( 'knowledgegraph_logo_id' ) );
	$front = (int) get_option( 'page_on_front' );
	$desc  = (string) aw_seo_rm_setting( 'organization_description' );
	$desc  = '' !== trim( $desc ) ? $desc : ( $front ? (string) get_post_meta( $front, 'rank_math_description', true ) : '' ); // the home page's meta description
	$desc  = '' !== trim( $desc ) ? $desc : ( $front ? (string) get_post_field( 'post_excerpt', $front ) : '' );
	$desc  = '' !== trim( $desc ) ? $desc : get_bloginfo( 'description' );
	$email = sanitize_email( (string) aw_seo_rm_setting( 'email' ) );
	$email = '' !== $email ? $email : 'contact@animalwrites.in'; // as on the Contact page
	$name  = trim( (string) aw_seo_rm_setting( 'knowledgegraph_name' ) );
	$about = get_terms( array( 'taxonomy' => aw_seo_category_tax(), 'hide_empty' => true, 'fields' => 'names' ) );
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
				'name'          => '' !== $name ? $name : get_bloginfo( 'name' ),
				'alternateName' => AW_SITE_ALT_NAMES,
				'url'           => home_url( '/' ),
				'description'   => aw_seo_plain( $desc ),
				'logo'          => $logo ? array_filter( array( '@type' => 'ImageObject', 'url' => $logo, 'width' => (int) ( $lmeta['width'] ?? 0 ), 'height' => (int) ( $lmeta['height'] ?? 0 ) ) ) : null,
				'email'         => $email,
				'contactPoint'  => array( '@type' => 'ContactPoint', 'contactType' => 'customer support', 'email' => $email, 'url' => home_url( '/contact/' ) ),
				'foundingDate'  => '2025-11', // the first campaign, India Writes for Its Community Dogs (November 2025)
				'areaServed'    => array( '@type' => 'Country', 'name' => 'India', 'identifier' => 'IN' ),
				'knowsAbout'    => is_array( $about ) ? array_values( array_map( 'aw_seo_plain', $about ) ) : null,
				'sameAs'        => aw_publisher_profiles(),
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
 * ImageObject for an item: the same image its og:image uses (helpers.php: for ingested items the publisher's own
 * image, linked), with its size when known. Null when the site has no image to give.
 *
 * @param int $id Post ID.
 * @return array|null
 */
function aw_schema_image( $id ) {
	$img = ( is_singular() && get_queried_object_id() === (int) $id ) ? aw_seo_image() : ( aw_seo_item_image( $id ) ? aw_seo_item_image( $id ) : aw_seo_default_image() );
	if ( $img && false !== strpos( html_entity_decode( $img['url'] ), '&' ) ) { // printed through wp_kses (& as &amp;): the plain address, as og:image
		$img = array( 'url' => strtok( html_entity_decode( $img['url'] ), '?' ), 'width' => 0, 'height' => 0 );
	}
	return $img ? array_filter( array( '@type' => 'ImageObject', 'url' => $img['url'], 'width' => $img['width'], 'height' => $img['height'] ) ) : null;
}

/**
 * The site's categories on an item, as schema.org Things (about).
 *
 * @param int $id Post ID.
 * @return array|null
 */
function aw_schema_categories( $id ) {
	$names = wp_get_object_terms( $id, aw_seo_category_tax(), array( 'fields' => 'names' ) );
	if ( ! $names || is_wp_error( $names ) ) {
		return null;
	}
	return array_map( fn( $n ) => array( '@type' => 'Thing', 'name' => aw_seo_plain( $n ) ), array_values( $names ) );
}

/** The item's place (district and state, when stored) as a Place, or null. */
function aw_schema_place( $id ) {
	list( $state, $district ) = function_exists( 'aw_item_place_names' ) ? aw_item_place_names( $id ) : array( '', '' );
	if ( ! $state && ! $district ) {
		return null;
	}
	return array(
		'@type'   => 'Place',
		'name'    => implode( ', ', array_filter( array( $district, $state ) ) ),
		'address' => array_filter( array( '@type' => 'PostalAddress', 'addressLocality' => $district, 'addressRegion' => $state, 'addressCountry' => 'IN' ) ),
	);
}

/**
 * The document's PDF as a MediaObject: the file hosted here (aw_file_id), else the source address when it is a PDF.
 *
 * @param int $id Post ID.
 * @return array|null
 */
function aw_schema_pdf( $id ) {
	$att = (int) get_post_meta( $id, 'aw_file_id', true );
	$url = $att && 'application/pdf' === get_post_mime_type( $att ) ? (string) wp_get_attachment_url( $att ) : '';
	if ( '' === $url ) {
		$src = (string) get_post_meta( $id, 'aw_source_url', true );
		$url = preg_match( '/\.pdf$/i', (string) wp_parse_url( $src, PHP_URL_PATH ) ) ? $src : '';
	}
	return $url ? array( '@type' => 'MediaObject', 'contentUrl' => $url, 'encodingFormat' => 'application/pdf' ) : null;
}

/** The issuing body of a Library item (court, ministry, board) as a GovernmentOrganization, or null. */
function aw_schema_issuer( $id ) {
	$iss = function_exists( 'aw_item_issuer' ) ? aw_item_issuer( $id ) : null;
	return $iss ? array( '@type' => 'GovernmentOrganization', 'name' => aw_seo_plain( $iss->name ) ) : null;
}

/**
 * HowTo of a guide whose text is a numbered list of steps (its first numbered list with three or more steps), or null.
 * The steps are the guide's own words.
 *
 * @param int $id Post ID.
 * @return array|null
 */
function aw_schema_howto( $id ) {
	if ( ! preg_match( '#<ol[^>]*>(.*?)</ol>#is', (string) get_post_field( 'post_content', $id ), $ol ) ) {
		return null;
	}
	preg_match_all( '#<li[^>]*>(.*?)</li>#is', $ol[1], $li );
	$steps = array_values( array_filter( array_map( 'aw_seo_plain', $li[1] ) ) );
	if ( count( $steps ) < 3 || count( $steps ) !== count( $li[1] ) ) {
		return null;
	}
	$url = get_permalink( $id );
	return array(
		'@type'    => 'HowTo',
		'@id'      => $url . '#howto',
		'name'     => aw_seo_plain( get_the_title( $id ) ),
		'url'      => $url,
		'isPartOf' => array( '@id' => $url . '#article' ),
		'step'     => array_map( fn( $t, $i ) => array( '@type' => 'HowToStep', 'position' => $i + 1, 'text' => $t ), $steps, array_keys( $steps ) ),
	);
}

/**
 * The item's own schema node, purpose-made by type and sub-type (see the file header). Only stored values are used.
 *
 * - News: NewsArticle; the source article (isBasedOn), its publisher (sourceOrganization), the other reports of the
 *   same event (citation), categories (about), place (contentLocation).
 * - Library: Acts, Rules and Notifications as Legislation (type, date, jurisdiction, the PDF as encoding, the issuing
 *   body as sourceOrganization); judgments and orders as our Article on the court's ruling (a CreativeWork by the
 *   court); official releases as our Article on the issuer's press release; circulars, advisories and other documents
 *   as our Article on the issuer's DigitalDocument; templates as a DigitalDocument.
 * - Resources: guides as Article (plus HowTo when the guide is numbered steps; FAQPage comes from Rank Math's FAQ
 *   block); research summaries as Article based on the ScholarlyArticle (DOI, journal).
 *
 * @param int $id Post ID.
 * @return array List of nodes (the main one first).
 */
function aw_article_nodes( $id ) {
	$type     = get_post_type( $id );
	$url      = get_permalink( $id );
	$title    = aw_seo_plain( get_the_title( $id ) );
	$kind     = 'aw_legal' === $type && function_exists( 'aw_legal_kinds' ) ? ( aw_legal_kinds( $id )[0] ?? null ) : null; // the Library type tag (never a label such as a campaign tag)
	$ks       = $kind ? $kind->slug : '';
	$src_url  = (string) get_post_meta( $id, 'aw_source_url', true );
	$src_name = aw_seo_plain( (string) get_post_meta( $id, 'aw_source_name', true ) );
	$src_date = aw_schema_date( (string) get_post_meta( $id, 'aw_source_date', true ) );
	$reviewed = (string) get_post_meta( $id, 'kits_last_reviewed', true ); // crisis guides: the date they were last checked
	$issuer   = 'aw_legal' === $type ? aw_schema_issuer( $id ) : null;
	$pdf      = 'aw_legal' === $type ? aw_schema_pdf( $id ) : null;
	$us       = array( '@id' => home_url( '/#organization' ) );
	$base     = array(
		'mainEntityOfPage' => $url,
		'url'              => $url,
		'description'      => function_exists( 'aw_text' ) ? aw_seo_plain( aw_text( $id, 40 ) ) : '',
		'datePublished'    => get_the_date( 'c', $id ),
		'dateModified'     => $reviewed ? aw_schema_date( $reviewed ) : get_the_modified_date( 'c', $id ),
		'inLanguage'       => 'en-IN',
		'image'            => aw_schema_image( $id ),
		'about'            => aw_schema_categories( $id ),
	);
	$article = array_merge( array( '@type' => 'Article', '@id' => $url . '#article', 'headline' => $title ), $base, array( 'author' => $us, 'publisher' => $us ) );
	$nodes   = array();

	if ( 'aw_news' === $type ) {
		$node = array_merge( $article, array( '@type' => 'NewsArticle' ) );
		if ( $src_date ) {
			$node['datePublished'] = $src_date; // the day the story was reported
		}
		$outlet = $src_name ? array( '@type' => 'NewsMediaOrganization', 'name' => $src_name ) : null; // references are CreativeWork, so search engines see one article
		if ( $outlet ) {
			$node['sourceOrganization'] = $outlet;
		}
		if ( $src_url ) {
			$node['isBasedOn'] = array_filter( array( '@type' => 'CreativeWork', 'genre' => 'News report', 'url' => $src_url, 'publisher' => $outlet, 'datePublished' => $src_date ) );
		}
		$also = array();
		foreach ( (array) get_post_meta( $id, 'aw_also_reported', true ) as $r ) {
			if ( is_array( $r ) && ! empty( $r['url'] ) ) { // later reports of the same event
				$also[] = array_filter( array( '@type' => 'CreativeWork', 'genre' => 'News report', 'url' => (string) $r['url'], 'publisher' => empty( $r['name'] ) ? null : array( '@type' => 'NewsMediaOrganization', 'name' => aw_seo_plain( $r['name'] ) ) ) );
			}
		}
		if ( $also ) {
			$node['citation'] = $also;
		}
		$node['contentLocation'] = aw_schema_place( $id );
		$nodes[]                 = $node;
	} elseif ( in_array( $ks, array( 'act', 'rule', 'notification' ), true ) ) {
		$node = array_merge(
			array(
				'@type'                   => 'Legislation',
				'@id'                     => $url . '#legislation',
				'name'                    => $title,
				'legislationType'         => aw_seo_plain( $kind->name ),
				'legislationJurisdiction' => 'IN',
			),
			$base
		);
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) get_post_meta( $id, 'aw_source_date', true ) ) ) {
			$node['legislationDate'] = (string) get_post_meta( $id, 'aw_source_date', true ); // the date of the text, as stored
		}
		$number = trim( (string) get_post_meta( $id, 'aw_legal_number', true ) );
		$force  = (string) get_post_meta( $id, 'aw_legal_force', true );
		if ( '' !== $number ) {
			$node['legislationIdentifier'] = aw_seo_plain( $number );
		}
		if ( in_array( $force, array( 'InForce', 'NotInForce', 'PartiallyInForce' ), true ) ) {
			$node['legislationLegalForce'] = 'https://schema.org/' . $force;
		}
		$node['encoding']           = $pdf;
		$node['sourceOrganization'] = $issuer; // the body whose copy this is (it may not be the law-maker)
		$node['isBasedOn']          = $src_url ? array_filter( array( '@type' => 'CreativeWork', 'url' => $src_url, 'publisher' => $issuer ) ) : null;
		$nodes[]                    = $node;
	} elseif ( 'aw_legal' === $type && 'template' === $ks ) {
		$nodes[] = array_merge(
			array( '@type' => 'DigitalDocument', '@id' => $url . '#document', 'name' => $title, 'genre' => aw_seo_plain( $kind->name ), 'encodingFormat' => 'text/html' ),
			$base,
			array( 'author' => $us, 'publisher' => $us )
		);
	} elseif ( 'aw_legal' === $type ) {
		$court = $issuer && function_exists( 'aw_item_issuer' ) && in_array( aw_item_issuer( $id )->slug, AW_COURT_ISSUERS, true );
		$doc   = array_filter(
			array(
				'@type'         => 'CreativeWork',
				'name'          => $title,
				'url'           => $src_url ? $src_url : null,
				'author'        => $issuer,
				'datePublished' => $src_date,
				'encoding'      => $pdf,
			)
		);
		if ( $court || in_array( $ks, array( 'judgment', 'order' ), true ) ) {
			$doc['genre']     = 'order' === $ks ? 'Court order' : 'Court judgment';
			$article['genre'] = $doc['genre'];
			if ( $issuer ) {
				$article['about'] = array_merge( array( $issuer ), (array) $article['about'] );
			}
			$order = aw_court_document_url( $id );
			if ( $order ) {
				$article['citation'] = $order; // the court's own copy
			}
		} elseif ( 'official-release' === $ks ) {
			$doc['genre']     = 'Press release';
			$article['genre'] = 'Press release';
		} else {
			$doc['@type']     = 'DigitalDocument'; // a circular, advisory or other government document
			$doc['genre']     = $kind ? aw_seo_plain( $kind->name ) : null;
			$article['genre'] = $doc['genre'];
		}
		$article['isBasedOn'] = array_filter( $doc );
		$nodes[]              = $article;
	} else { // Resources
		$research = has_term( 'research', 'post_tag', $id ); // the Resource type tag
		if ( $src_url ) {
			$doi                  = preg_match( '#^https?://(?:dx\.)?doi\.org/(10\..+)$#i', $src_url, $mm ) ? rawurldecode( $mm[1] ) : '';
			$article['isBasedOn'] = array_filter(
				array(
					'@type'      => $research || $doi ? 'ScholarlyArticle' : 'CreativeWork',
					'name'       => $research && function_exists( 'aw_research_study_title' ) && '' !== aw_research_study_title( $id ) ? aw_research_study_title( $id ) : $title, // the paper's own title (owner, 20 Oct 2026); else the item's
					'headline'   => $research && function_exists( 'aw_research_study_title' ) && '' !== aw_research_study_title( $id ) ? aw_research_study_title( $id ) : $title,
					'url'        => $src_url,
					'sameAs'     => $doi ? 'https://doi.org/' . $doi : null,
					'identifier' => $doi ? array( '@type' => 'PropertyValue', 'propertyID' => 'DOI', 'value' => $doi ) : null,
					'author'     => $research ? ( array_map( fn( $n ) => array( '@type' => 'Person', 'name' => $n ), array_values( (array) get_post_meta( $id, 'aw_authors', true ) ) ) ?: null ) : null, // research-citation.php
					'isPartOf'   => $doi && ( get_post_meta( $id, 'aw_journal', true ) ?: $src_name ) ? array( '@type' => 'Periodical', 'name' => (string) ( get_post_meta( $id, 'aw_journal', true ) ?: $src_name ) ) : null,
					'publisher'  => ! $doi && $src_name ? array( '@type' => 'Organization', 'name' => $src_name ) : null,
					'datePublished' => $src_date,
				)
			);
		}
		$nodes[] = $article;
		$howto   = $research ? null : aw_schema_howto( $id );
		if ( $howto ) {
			$nodes[0]['hasPart'] = array( '@id' => $howto['@id'] );
			$nodes[]             = $howto;
		}
	}
	return array_map( fn( $n ) => array_filter( $n, fn( $v ) => null !== $v && '' !== $v && array() !== $v ), $nodes );
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

/**
 * A campaign: an Event when its day is stored (aw_event_date, Y-m-d; place in aw_event_place), organised by its
 * organiser or Animal Writes; otherwise a CreativeWork by the organiser. The stored day is the whole event (one day:
 * endDate the same). No offers (only when the campaign states free participation; none does), no performer.
 *
 * @param int $id Post ID.
 * @return array
 */
function aw_campaign_schema( $id ) {
	$org     = (int) get_post_meta( $id, 'aw_organiser', true );
	$image   = (string) get_the_post_thumbnail_url( $id, 'large' );
	$by      = $org && 'publish' === get_post_status( $org ) ? array( '@type' => 'NGO', 'name' => aw_seo_plain( get_the_title( $org ) ), 'url' => get_permalink( $org ) ) : array( '@id' => home_url( '/#organization' ) );
	$day     = (string) get_post_meta( $id, 'aw_event_date', true );
	$place   = aw_seo_plain( (string) get_post_meta( $id, 'aw_event_place', true ) );
	$common  = array(
		'url'         => get_permalink( $id ),
		'name'        => aw_seo_plain( get_the_title( $id ) ),
		'description' => function_exists( 'aw_text' ) ? aw_seo_plain( aw_text( $id, 40 ) ) : '',
		'inLanguage'  => 'en-IN',
		'image'       => $image ? array( $image ) : null,
		'about'       => aw_schema_categories( $id ),
	);
	// A campaign about a law (aw_campaign_doc, campaign-tools.php): it is about that Library item's Legislation node.
	$doc = (int) get_post_meta( $id, 'aw_campaign_doc', true );
	if ( $doc && 'publish' === get_post_status( $doc ) ) {
		$common['about'] = array_merge( array( array( '@id' => get_permalink( $doc ) . '#legislation' ) ), (array) $common['about'] );
	}
	if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) ) {
		return array_filter(
			array_merge(
				array( '@type' => 'Event', '@id' => get_permalink( $id ) . '#event', 'startDate' => $day, 'endDate' => $day, 'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode', 'eventStatus' => 'https://schema.org/EventScheduled' ),
				$common,
				array(
					'location'  => $place ? array( '@type' => 'Place', 'name' => $place, 'address' => array( '@type' => 'PostalAddress', 'addressCountry' => 'IN' ) ) : null,
					'organizer' => $by,
					'performer' => $by, // the organiser runs the day (Search Console asks for a performer)
					'offers'    => array( '@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'INR', 'availability' => 'https://schema.org/InStock', 'url' => get_permalink( $id ) ), // free to take part
				)
			)
		);
	}
	return array_filter(
		array_merge(
			array( '@type' => 'CreativeWork', '@id' => get_permalink( $id ) . '#campaign', 'datePublished' => get_the_date( 'c', $id ), 'dateModified' => get_the_modified_date( 'c', $id ) ),
			$common,
			array( 'creator' => $by, 'publisher' => array( '@id' => home_url( '/#organization' ) ) )
		)
	);
}

/**
 * A campaign page's own lists, read from its text so the markup and the page say the same thing: the rule-change cards
 * (aw-camp-card, ItemList) and the pain-scale tiers (aw-camp-tier, DefinedTermSet). Nothing when the page has neither.
 *
 * @param int $id Campaign ID.
 * @return array
 */
function aw_campaign_list_nodes( $id ) {
	$html  = (string) get_post_field( 'post_content', $id );
	$url   = get_permalink( $id );
	$plain = fn( $h ) => aw_seo_plain( trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( preg_replace( '/\[[^\]]*\]/', '', $h ) ) ) ) );
	$nodes = array();
	$items = array();
	if ( preg_match_all( '#<div (?:id="([^"]+)" )?class="wp-block-group aw-camp-card">(.*?)</div>\s*<!-- /wp:group -->#s', $html, $cards, PREG_SET_ORDER ) ) {
		foreach ( $cards as $card ) {
			$c = $card[2];
			if ( ! preg_match( '#<h[34][^>]*>(.*?)</h[34]>#s', $c, $t ) ) {
				continue;
			}
			preg_match( '#<p>(.*?)</p>#s', $c, $d );
			$items[] = array_filter( array( '@type' => 'ListItem', 'position' => count( $items ) + 1, 'name' => $plain( $t[1] ), 'description' => isset( $d[1] ) ? $plain( $d[1] ) : null, 'url' => $url . '#' . ( $card[1] ? $card[1] : 'why-it-matters' ) ) );
		}
	}
	if ( $items ) {
		$head    = preg_match( '#<h2[^>]*>([^<]*)</h2>\s*<!-- /wp:heading -->\s*<!-- wp:group \{"className":"aw-camp-grid"#', $html, $h ) ? $plain( $h[1] ) : 'What the draft does';
		$nodes[] = array( '@type' => 'ItemList', '@id' => $url . '#rule-changes', 'name' => $head, 'numberOfItems' => count( $items ), 'itemListElement' => $items, 'isPartOf' => array( '@id' => $url . '#campaign' ) );
	}
	$terms = array();
	if ( preg_match_all( '#<li class="aw-camp-tier[^"]*">.*?<h[34]>(.*?)</h[34]>(.*?)</li>#s', $html, $tiers, PREG_SET_ORDER ) ) {
		foreach ( $tiers as $t ) {
			$terms[] = array( '@type' => 'DefinedTerm', 'name' => $plain( $t[1] ), 'description' => $plain( str_replace( '</p><p>', '.</p> <p>', $t[2] ) ), 'inDefinedTermSet' => array( '@id' => $url . '#pain-scale' ) );
		}
	}
	if ( $terms ) {
		$head    = preg_match( '#<h[23][^>]*>([^<]*pain scale[^<]*)</h[23]>#i', $html, $h ) ? $plain( $h[1] ) : 'Pain scale';
		$nodes[] = array( '@type' => 'DefinedTermSet', '@id' => $url . '#pain-scale', 'name' => $head, 'url' => $url . '#pain-scale', 'hasDefinedTerm' => $terms );
	}
	return $nodes;
}

/**
 * A campaign in its Post state (campaign-record.php): its page is a retrospective, an Article about the campaign's own
 * node (Event or CreativeWork) and about the Library records the page lists (the campaign's campaign-* tag on
 * aw_legal items), each by the @id of its own node. Nothing before Post.
 *
 * @param int $id Post ID.
 * @return array
 */
function aw_campaign_record_schema( $id ) {
	if ( ! function_exists( 'aw_camp_state' ) || 'post' !== aw_camp_state( $id ) ) {
		return array();
	}
	$url   = get_permalink( $id );
	$own   = aw_campaign_schema( $id );
	$about = array( array( '@id' => (string) ( $own['@id'] ?? $url . '#campaign' ) ) );
	$tags  = function_exists( 'aw_campaign_links' ) ? wp_list_pluck( aw_campaign_links( $id ), 'term_id' ) : array(); // the campaign's own link (campaign-links.php)
	if ( $tags && function_exists( 'aw_article_nodes' ) ) {
		$docs = get_posts( array( 'post_type' => 'aw_legal', 'post_status' => 'publish', 'numberposts' => 20, 'tax_query' => array( array( 'taxonomy' => 'aw_campaign_link', 'terms' => array( (int) $tags[0] ) ) ), 'orderby' => 'date', 'order' => 'DESC', 'fields' => 'ids' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		foreach ( $docs as $d ) {
			$n = aw_article_nodes( $d );
			if ( ! empty( $n[0]['@id'] ) ) {
				$about[] = array( '@id' => $n[0]['@id'] );
			}
		}
	}
	$image = (string) get_the_post_thumbnail_url( $id, 'large' );
	return array_filter(
		array(
			'@type'            => 'Article',
			'@id'              => $url . '#article',
			'headline'         => aw_seo_plain( get_the_title( $id ) ),
			'url'              => $url,
			'mainEntityOfPage' => array( '@id' => $url . '#webpage' ),
			'description'      => function_exists( 'aw_text' ) ? aw_seo_plain( aw_text( $id, 40 ) ) : '',
			'inLanguage'       => 'en-IN',
			'image'            => $image ? array( $image ) : null,
			'datePublished'    => get_the_date( 'c', $id ),
			'dateModified'     => get_the_modified_date( 'c', $id ),
			'author'           => array( '@id' => home_url( '/#organization' ) ),
			'publisher'        => array( '@id' => home_url( '/#organization' ) ),
			'about'            => $about,
		)
	);
}

/**
 * Every inLanguage in the graph as en-IN: Rank Math writes the WordPress locale (en-US) on its WebPage nodes, while the
 * site, its pages and its items are Indian English. Plain-text values lose their HTML entities (&#039;, &#8217; from
 * WordPress titles), so the graph carries the characters themselves.
 *
 * @param mixed $node Graph or node.
 * @return mixed
 */
function aw_schema_language( $node ) {
	if ( ! is_array( $node ) ) {
		return $node;
	}
	foreach ( $node as $k => $v ) {
		if ( 'inLanguage' === $k && is_string( $v ) && 0 === stripos( $v, 'en' ) ) {
			$node[ $k ] = 'en-IN';
		} elseif ( is_string( $v ) && false === strpos( $v, '<' ) && false !== strpos( $v, '&' ) ) {
			$node[ $k ] = html_entity_decode( $v, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		} elseif ( is_array( $v ) ) {
			$node[ $k ] = aw_schema_language( $v );
		}
	}
	return $node;
}

/** Other names of the site and organisation (owner, 20 Oct 2026); Google reads the WebSite's for the site name in results. */
const AW_SITE_ALT_NAMES = array( 'AnimalWrites', 'animalwrites.in' );

/** The site itself, with site search (shown on every page). */
function aw_website_schema() {
	return array(
		'@type'           => 'WebSite',
		'@id'             => home_url( '/#website' ),
		'name'            => get_bloginfo( 'name' ),
		'alternateName'   => AW_SITE_ALT_NAMES,
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

/**
 * JobPosting for an open job, internship, fellowship or volunteer role: remote ones as TELECOMMUTE (with India as the
 * applicant country), others at each of their places (else India). Only from what the item holds, nothing guessed:
 * - validThrough only from a stored deadline (aw_deadline; a review date is not a deadline). Past it, or past its
 *   review date, the opportunity is closed and has no JobPosting at all.
 * - employmentType from the type the source states (aw_employment_type, e.g. FULL_TIME for "Type: Fulltime") and the
 *   Opportunity type: internship INTERN, volunteer VOLUNTEER, fellowship OTHER. A job that states no hours has none.
 * - jobLocation: one Place per stored district or state. Street and PIN code only when the role is at the hiring
 *   organisation's own place: the opportunity has one place, the same district as the organisation's Directory listing,
 *   and the listing holds them (its area beyond the district and state, its PIN code). The opportunity's own PIN code
 *   comes first. Never the organisation's office for a role elsewhere.
 * - baseSalary: never; no opportunity stores pay, and none is guessed.
 */
function aw_job_schema( $id ) {
	$places = function_exists( 'aw_item_places' ) ? aw_item_places( $id ) : array();
	$area   = trim( (string) get_post_meta( $id, 'aw_area', true ) . ' ' . (string) get_post_meta( $id, 'aw_place_city', true ) );
	$names  = implode( ' ', array_map( fn( $p ) => $p->name, $places ) );
	$org_id = (int) get_post_meta( $id, 'aw_organisation', true );
	$org_id = $org_id && 'publish' === get_post_status( $org_id ) ? $org_id : 0;
	$remote = (bool) preg_match( '/\b(remote|online|anywhere|work from home|wfh|virtual)\b/i', $area . ' ' . $names );
	$org      = (string) get_post_meta( $id, 'aw_org_name', true );
	$org      = $org ? $org : ( $org_id ? get_post_field( 'post_title', $org_id ) : '' );
	$org_site = (string) get_post_meta( $id, 'aw_website', true );
	if ( ! $org_site && $org_id ) {
		$org_site = (string) get_post_meta( $org_id, 'aw_website', true );
	}
	$deadline = (string) get_post_meta( $id, 'aw_deadline', true );
	if ( ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $deadline ) && $deadline < wp_date( 'Y-m-d' ) ) || ( function_exists( 'aw_retention_state' ) && aw_retention_state( $id ) ) ) {
		return array(); // closed (past its deadline or review date): no JobPosting
	}
	$kinds = wp_get_object_terms( $id, 'post_tag', array( 'fields' => 'slugs' ) );
	$kinds = is_wp_error( $kinds ) ? array() : $kinds;
	if ( $kinds && ! array_intersect( array( 'job', 'internship', 'fellowship', 'volunteer' ), $kinds ) ) {
		return array(); // an event, fostering or a grant is not a job
	}
	if ( '' === $org ) {
		return array(); // Google requires hiringOrganization: without the organisation's name there is no JobPosting
	}
	$desc = trim( preg_replace( '/<!--.*?-->/s', '', (string) get_post_field( 'post_content', $id ) ) ); // no block comments
	$desc = $desc ? $desc : ( function_exists( 'aw_text' ) ? aw_text( $id, 60 ) : '' );
	$desc = $desc ? $desc : (string) get_post_field( 'post_excerpt', $id );
	$desc = $desc ? $desc : get_the_title( $id );
	$emp  = array_filter( array( (string) get_post_meta( $id, 'aw_employment_type', true ) ) ); // as the source states it
	foreach ( array( 'internship' => 'INTERN', 'volunteer' => 'VOLUNTEER', 'fellowship' => 'OTHER' ) as $tag => $value ) { // Google has no fellowship value
		if ( in_array( $tag, $kinds, true ) ) {
			$emp[] = $value;
		}
	}
	$emp  = array_values( array_unique( $emp ) );
	$logo = $org_id ? (string) get_the_post_thumbnail_url( $org_id, 'medium' ) : ''; // the organisation's own logo, when its listing has one
	$data = array(
		'@type'              => 'JobPosting',
		'@id'                => get_permalink( $id ) . '#job',
		'title'              => aw_seo_plain( get_the_title( $id ) ),
		'description'        => trim( preg_replace( '/\n{3,}/', "\n\n", wp_kses_post( $desc ) ) ),
		'datePosted'         => get_the_date( 'c', $id ),
		'url'                => get_permalink( $id ),
		'employmentType'     => $emp ? ( 1 === count( $emp ) ? $emp[0] : $emp ) : null,
		'hiringOrganization' => array_filter( array( '@type' => 'Organization', 'name' => aw_seo_plain( $org ), 'sameAs' => $org_site, 'url' => $org_id ? get_permalink( $org_id ) : null, 'logo' => $logo ) ),
		'validThrough'       => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $deadline ) ? $deadline . 'T23:59:59+05:30' : null,
	);
	if ( $remote ) {
		$data['jobLocationType']               = 'TELECOMMUTE';
		$data['applicantLocationRequirements'] = array( '@type' => 'Country', 'name' => 'India' );
		return array_filter( $data );
	}
	$street = '';
	$pin    = (string) get_post_meta( $id, 'aw_pincode', true );
	if ( 1 === count( $places ) && 'district' === $places[0]->kind && $org_id && function_exists( 'aw_item_codes' ) && in_array( (int) $places[0]->code, aw_item_codes( $org_id )[1], true ) ) {
		$state  = aw_place_state( $places[0]->state );
		$street = trim( preg_replace( '/(\s*,\s*)+/', ', ', str_ireplace( array_filter( array( $places[0]->name, $state ? $state->name : '', 'India' ) ), '', (string) get_post_meta( $org_id, 'aw_area', true ) ) ), " ,\t\n" );
		$pin    = $pin ? $pin : (string) get_post_meta( $org_id, 'aw_pincode', true ); // the role is at the organisation's own place
	}
	$locs = array();
	foreach ( $places ? $places : array( null ) as $p ) {
		$state  = $p ? ( 'district' === $p->kind ? aw_place_state( $p->state ) : $p ) : null;
		$locs[] = array(
			'@type'   => 'Place',
			'address' => array_filter(
				array(
					'@type'           => 'PostalAddress',
					'streetAddress'   => $street,
					'addressLocality' => $p && 'district' === $p->kind ? $p->name : ( $p ? null : trim( (string) get_post_meta( $id, 'aw_place_city', true ) ) ),
					'addressRegion'   => $state ? $state->name : null,
					'postalCode'      => 1 === count( $places ) ? $pin : '',
					'addressCountry'  => 'IN', // without a stored place, only the country
				)
			),
		);
	}
	$data['jobLocation'] = 1 === count( $locs ) ? $locs[0] : $locs;
	return array_filter( $data );
}

/**
 * The kind of an Opportunity, from its Opportunity type tags (an event first, then a role, then the rest):
 * event | role | fellowship | grant | course | call | '' (anything else, e.g. fostering alone: the page itself).
 * scholarship, course/training/workshop and award/competition/call-for-submissions have no tags yet (see the report
 * of 17 Oct 2026); their slugs are read here so the right type follows once the owner adds them.
 */
function aw_opportunity_kind( $id ) {
	$kinds = wp_get_object_terms( $id, 'post_tag', array( 'fields' => 'slugs' ) );
	$kinds = is_wp_error( $kinds ) ? array() : $kinds;
	$has   = fn( $list ) => (bool) array_intersect( $list, $kinds );
	if ( $has( array( 'event' ) ) ) {
		return 'event';
	}
	if ( $has( array( 'job', 'internship', 'volunteer' ) ) ) {
		return 'role';
	}
	if ( $has( array( 'fellowship' ) ) ) {
		return 'fellowship';
	}
	if ( $has( array( 'grant', 'scholarship' ) ) ) {
		return 'grant';
	}
	if ( $has( array( 'course', 'training', 'workshop' ) ) ) {
		return 'course';
	}
	if ( $has( array( 'award', 'competition', 'call-for-submissions' ) ) ) {
		return 'call';
	}
	return '';
}

/**
 * The next day an event repeats on, from aw_event_schedule as the source states it ("second Saturday monthly
 * 14:00-16:00", "last Sunday monthly 10:00-16:00"): [Y-m-d, start H:i, end H:i, week of month (1-4, -1 last), day].
 * Null when the text is not in that form.
 */
function aw_opportunity_repeat( $id ) {
	$text  = strtolower( (string) get_post_meta( $id, 'aw_event_schedule', true ) );
	$weeks = array( 'first' => 1, 'second' => 2, 'third' => 3, 'fourth' => 4, 'last' => -1 );
	if ( ! preg_match( '/\b(first|second|third|fourth|last)\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b.*?\bmonthly\b.*?(\d{1,2}:\d{2})\s*[-–]\s*(\d{1,2}:\d{2})/', $text, $m ) ) {
		return null;
	}
	$tz    = wp_timezone();
	$today = new DateTimeImmutable( 'today', $tz );
	for ( $i = 0; $i < 3; $i++ ) {
		$month = $today->modify( 'first day of this month' )->modify( "+$i month" );
		$day   = $month->modify( $m[1] . ' ' . $m[2] . ' of this month' );
		if ( $day >= $today ) {
			return array( $day->format( 'Y-m-d' ), $m[3], $m[4], $weeks[ $m[1] ], ucfirst( $m[2] ) );
		}
	}
	return null;
}

/**
 * The address of an item at the organisation's own place: district and state of its one place, with the street and PIN
 * code of the organisation's Directory listing when the listing is in that district (never an office elsewhere).
 */
function aw_opportunity_org_address( $id, $org_id ) {
	$places = function_exists( 'aw_item_places' ) ? aw_item_places( $id ) : array();
	$p      = 1 === count( $places ) ? $places[0] : null;
	$state  = $p ? ( 'district' === $p->kind ? aw_place_state( $p->state ) : $p ) : null;
	$street = '';
	$pin    = (string) get_post_meta( $id, 'aw_pincode', true );
	if ( $p && 'district' === $p->kind && $org_id && in_array( (int) $p->code, aw_item_codes( $org_id )[1], true ) ) {
		$street = trim( preg_replace( '/(\s*,\s*)+/', ', ', str_ireplace( array_filter( array( $p->name, $state ? $state->name : '', 'India' ) ), '', (string) get_post_meta( $org_id, 'aw_area', true ) ) ), " ,\t\n" );
		$pin    = $pin ? $pin : (string) get_post_meta( $org_id, 'aw_pincode', true );
	}
	return array_filter( array( '@type' => 'PostalAddress', 'streetAddress' => $street, 'addressLocality' => $p && 'district' === $p->kind ? $p->name : null, 'addressRegion' => $state ? $state->name : null, 'postalCode' => $pin, 'addressCountry' => 'IN' ) );
}

/**
 * The schema.org node of an Opportunity, by what it is (owner, 17 Oct 2026: the correct type per item):
 * - a role (job, internship, volunteer role): JobPosting (aw_job_schema);
 * - an event with a stated day, or a stated monthly repeat: Event at the organisation's place, organised by it
 *   (a repeat also as an eventSchedule; startDate is its next day). Without a day: no node;
 * - a fellowship: EducationalOccupationalProgram offered by the organisation (applicationDeadline from aw_deadline);
 * - a grant or scholarship: MonetaryGrant funded by the organisation (no amount: none is stored);
 * - a course, training or workshop: Course by the organisation (with a CourseInstance when it has a day);
 * - an award, competition or call: Event when it has a day, else CreativeWork;
 * - anything else (fostering alone): no node; the page is Rank Math's WebPage.
 * A closed opportunity (past its deadline or review date) has none.
 */
function aw_opportunity_schema( $id ) {
	if ( function_exists( 'aw_retention_state' ) && aw_retention_state( $id ) ) {
		return array();
	}
	$kind = aw_opportunity_kind( $id );
	if ( 'role' === $kind ) {
		return aw_job_schema( $id );
	}
	if ( '' === $kind ) {
		return array();
	}
	$org_id = (int) get_post_meta( $id, 'aw_organisation', true );
	$org_id = $org_id && 'publish' === get_post_status( $org_id ) ? $org_id : 0;
	$name   = (string) get_post_meta( $id, 'aw_org_name', true );
	$name   = $name ? $name : ( $org_id ? get_post_field( 'post_title', $org_id ) : '' );
	$org    = $name ? array_filter( array( '@type' => 'Organization', 'name' => aw_seo_plain( $name ), 'url' => $org_id ? get_permalink( $org_id ) : (string) get_post_meta( $id, 'aw_website', true ) ) ) : null;
	$desc   = function_exists( 'aw_text' ) ? aw_seo_plain( aw_text( $id, 60 ) ) : '';
	$common = array(
		'@id'         => get_permalink( $id ) . '#' . $kind,
		'name'        => aw_seo_plain( get_the_title( $id ) ),
		'description' => '' !== $desc ? $desc : aw_seo_plain( (string) get_post_field( 'post_excerpt', $id ) ),
		'url'         => get_permalink( $id ),
		'inLanguage'  => 'en-IN',
	);
	$day      = (string) get_post_meta( $id, 'aw_event_date', true );
	$day      = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) ? $day : '';
	$repeat   = $day ? null : aw_opportunity_repeat( $id );
	$deadline = (string) get_post_meta( $id, 'aw_deadline', true );
	$deadline = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $deadline ) ? $deadline : '';
	$event    = function () use ( $id, $common, $org, $org_id, $day, $repeat ) {
		if ( ! $day && ! $repeat ) {
			return array();
		}
		$t     = wp_timezone();
		$start = $repeat ? new DateTimeImmutable( $repeat[0] . ' ' . $repeat[1], $t ) : null;
		$end   = $repeat ? new DateTimeImmutable( $repeat[0] . ' ' . $repeat[2], $t ) : null;
		$addr  = aw_opportunity_org_address( $id, $org_id );
		$image = aw_schema_image( $id );
		return array_filter(
			array_merge(
				array( '@type' => 'Event' ),
				$common,
				array(
					'@id'                 => get_permalink( $id ) . '#event',
					'startDate'           => $start ? $start->format( 'c' ) : $day,
					'endDate'             => $end ? $end->format( 'c' ) : $day, // a stated day is one day
					'eventSchedule'       => $repeat ? array( '@type' => 'Schedule', 'repeatFrequency' => 'P1M', 'byDay' => 'https://schema.org/' . $repeat[4], 'byMonthWeek' => $repeat[3], 'startTime' => $repeat[1] . ':00', 'endTime' => $repeat[2] . ':00', 'scheduleTimezone' => 'Asia/Kolkata' ) : null,
					'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
					'eventStatus'         => 'https://schema.org/EventScheduled',
					'location'            => array_filter( array( '@type' => 'Place', 'name' => $org ? $org['name'] : null, 'address' => $addr ) ),
					'organizer'           => $org,
					'image'               => $image ? array( $image ) : null,
				)
			)
		);
	};
	if ( 'event' === $kind ) {
		return $event();
	}
	if ( 'fellowship' === $kind ) {
		return array_filter( array_merge( array( '@type' => 'EducationalOccupationalProgram' ), $common, array( 'provider' => $org, 'applicationDeadline' => $deadline ) ) );
	}
	if ( 'grant' === $kind ) {
		return array_filter( array_merge( array( '@type' => 'MonetaryGrant' ), $common, array( 'funder' => $org ) ) );
	}
	if ( 'course' === $kind ) {
		$instance = $day ? array( '@type' => 'CourseInstance', 'courseMode' => 'onsite', 'startDate' => $day, 'endDate' => $day, 'location' => array( '@type' => 'Place', 'address' => aw_opportunity_org_address( $id, $org_id ) ) ) : null;
		return array_filter( array_merge( array( '@type' => 'Course' ), $common, array( 'provider' => $org, 'hasCourseInstance' => $instance ) ) );
	}
	$ev = $event(); // an award, competition or call
	return $ev ? $ev : array_filter( array_merge( array( '@type' => 'CreativeWork' ), $common, array( 'creator' => $org, 'datePublished' => get_the_date( 'c', $id ) ) ) );
}

/** The Categories page: CollectionPage + ItemList of every category. */
function aw_categories_schema() {
	$url   = get_permalink();
	$items = array();
	foreach ( get_terms( array( 'taxonomy' => aw_seo_category_tax(), 'hide_empty' => true, 'orderby' => 'name' ) ) as $i => $t ) {
		$items[] = array( '@type' => 'ListItem', 'position' => $i + 1, 'url' => kits_term_url( $t ), 'name' => aw_seo_plain( $t->name ) );
	}
	return array(
		array(
			'@type'       => 'CollectionPage',
			'@id'         => $url . '#page',
			'url'         => $url,
			'name'        => aw_seo_plain( get_the_title() ),
			'description' => aw_seo_description(),
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
 * the article, legislation, job, campaign, listing organisation, contact page, hub list or category collection.
 *
 * @return array List of nodes.
 */
function aw_schema_nodes() {
	$nodes = array();
	if ( is_404() ) {
		return $nodes;
	}
	if ( is_singular( array( 'aw_news', 'aw_legal', 'post' ) ) ) {
		$nodes = aw_article_nodes( get_queried_object_id() );
	} elseif ( is_singular( 'aw_listing' ) ) {
		$nodes[] = aw_listing_schema( get_queried_object_id() );
	} elseif ( is_singular( 'aw_opportunity' ) ) {
		$nodes[] = aw_opportunity_schema( get_queried_object_id() ); // JobPosting, Event, programme, grant or course
	} elseif ( is_singular( 'aw_campaign' ) ) {
		$nodes[] = aw_campaign_schema( get_queried_object_id() );
		$nodes   = array_merge( $nodes, aw_campaign_list_nodes( get_queried_object_id() ) );
		$story   = aw_campaign_record_schema( get_queried_object_id() );
		if ( $story ) {
			$nodes[] = $story; // a closed campaign's page is also the record of what happened
		}
	} elseif ( is_page( 'contact' ) ) {
		$nodes[] = array(
			'@type'      => 'ContactPage',
			'@id'        => get_permalink() . '#page',
			'url'        => get_permalink(),
			'name'       => aw_seo_plain( get_the_title() ),
			'inLanguage' => 'en-IN',
			'isPartOf'   => array( '@id' => home_url( '/#website' ) ),
			'mainEntity' => array( '@id' => home_url( '/#organization' ) ), // the publisher node carries the email and contact point
		);
	} elseif ( is_page( 'about' ) ) {
		$nodes[] = array( '@type' => 'AboutPage', '@id' => get_permalink() . '#webpage', 'url' => get_permalink(), 'name' => aw_seo_plain( get_the_title() ), 'inLanguage' => 'en-IN', 'isPartOf' => array( '@id' => home_url( '/#website' ) ), 'mainEntity' => array( '@id' => home_url( '/#organization' ) ) );
	} elseif ( is_page( 'categories' ) ) {
		$nodes = array_merge( $nodes, aw_categories_schema() );
	} elseif ( is_page( array( 'press-coverage', 'media' ) ) && function_exists( 'aw_press_schema' ) ) {
		$nodes[] = aw_press_schema();
	} elseif ( function_exists( 'aw_hub_schema_nodes' ) && aw_hub_schema_nodes() ) {
		$nodes = array_merge( $nodes, aw_hub_schema_nodes() ); // hubs and directory places: CollectionPage + ItemList (hubs.php)
	} elseif ( is_page() && 'page-hub' === get_page_template_slug( get_queried_object_id() ) ) {
		$nodes = array_merge( $nodes, aw_page_lists_schema_nodes( get_queried_object_id() ) ); // a section page of several lists (Adoption)
	} elseif ( aw_seo_is_category_page() || is_tag() ) { // a category or tag page: the same CollectionPage + ItemList
		$nodes = array_merge( $nodes, aw_category_schema_nodes() );
	}
	if ( $nodes && is_singular( array( 'aw_news', 'aw_legal', 'post', 'aw_campaign' ) ) && function_exists( 'aw_featured_mentions_schema' ) ) {
		$mentions = aw_featured_mentions_schema( get_queried_object_id() ); // the organisations it features (featured.php)
		if ( $mentions ) {
			$nodes[0]['mentions'] = array_merge( (array) ( $nodes[0]['mentions'] ?? array() ), $mentions );
		}
	}
	return array_values( array_filter( $nodes ) );
}

/**
 * A category page: CollectionPage + ItemList of the items on the current page (indexed items only).
 *
 * @return array
 */
function aw_category_schema_nodes() {
	global $wp_query;
	$term  = get_queried_object();
	$url   = kits_term_url( $term );
	$per   = max( 1, (int) get_query_var( 'posts_per_page' ) );
	$start = ( max( 1, (int) get_query_var( 'paged' ) ) - 1 ) * $per;
	$items = array();
	foreach ( (array) $wp_query->posts as $i => $p ) {
		if ( ! $p instanceof WP_Post || aw_is_noindex_item( $p->ID ) || ( function_exists( 'kits_preview_only_ids' ) && in_array( (int) $p->ID, kits_preview_only_ids(), true ) ) ) {
			continue;
		}
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $start + $i + 1,
			'url'      => get_permalink( $p ),
			'name'     => aw_seo_plain( get_the_title( $p ) ), // a plain summary list (as the hubs): a typed stub with only a name and url reads as an incomplete Article
		);
	}
	$desc  = aw_seo_plain( aw_seo_description() );
	$nodes = array(
		array_filter(
			array(
				'@type'       => 'CollectionPage',
				'@id'         => $url . '#page',
				'url'         => $url,
				'name'        => aw_seo_plain( $term->name ),
				'description' => '' !== $desc ? $desc : wp_strip_all_tags( term_description( $term ) ),
				'inLanguage'  => 'en-IN',
				'isPartOf'    => array( '@id' => home_url( '/#website' ) ),
				'publisher'   => array( '@id' => home_url( '/#organization' ) ),
				'mainEntity'  => $items ? array( '@id' => $url . '#list' ) : null,
			)
		),
	);
	if ( $items ) {
		$nodes[] = array( '@type' => 'ItemList', '@id' => $url . '#list', 'numberOfItems' => (int) $wp_query->found_posts, 'itemListElement' => $items );
	}
	return $nodes;
}

/**
 * A section page whose lists are Query Loops in its own content (not one hub's list, e.g. Adoption: Directory listings,
 * Opportunities, Resources and News of one category): CollectionPage + ItemList of the items those loops show, in page
 * order. Synced patterns in the content are followed; noindex and preview-only items are left out.
 *
 * @param int $page_id Page ID.
 * @return array
 */
function aw_page_lists_schema_nodes( $page_id ) {
	$url    = get_permalink( $page_id );
	$loops  = array();
	$walk   = function ( $blocks, $depth = 0 ) use ( &$walk, &$loops ) {
		foreach ( $blocks as $b ) {
			if ( 'core/query' === $b['blockName'] ) {
				$loops[] = (array) ( $b['attrs']['query'] ?? array() );
			} elseif ( 'core/block' === $b['blockName'] && ! empty( $b['attrs']['ref'] ) && $depth < 3 ) {
				$walk( parse_blocks( (string) get_post_field( 'post_content', (int) $b['attrs']['ref'] ) ), $depth + 1 );
			}
			if ( ! empty( $b['innerBlocks'] ) ) {
				$walk( $b['innerBlocks'], $depth );
			}
		}
	};
	$walk( parse_blocks( (string) get_post_field( 'post_content', $page_id ) ) );
	$items = array();
	$seen  = array();
	$skip  = function_exists( 'kits_preview_only_ids' ) ? kits_preview_only_ids() : array();
	foreach ( $loops as $query ) {
		$loop = (object) array( 'context' => array( 'query' => $query ) );
		$vars = apply_filters( 'query_loop_block_query_vars', build_query_vars_from_query_block( $loop, 1 ), $loop, 1 ); // the same rules the list follows (open Opportunities only)
		foreach ( get_posts( $vars + array( 'post_status' => 'publish', 'suppress_filters' => false ) ) as $p ) {
			if ( isset( $seen[ $p->ID ] ) || aw_is_noindex_item( $p->ID ) || in_array( (int) $p->ID, $skip, true ) ) {
				continue;
			}
			$seen[ $p->ID ] = true;
			$items[]        = array(
				'@type'    => 'ListItem',
				'position' => count( $items ) + 1,
				'url'      => get_permalink( $p ),
				'name'     => aw_seo_plain( get_the_title( $p ) ), // plain summary list, as the hubs
			);
		}
	}
	$nodes = array(
		array_filter(
			array(
				'@type'       => 'CollectionPage',
				'@id'         => $url . '#page',
				'url'         => $url,
				'name'        => aw_seo_plain( get_the_title( $page_id ) ),
				'description' => aw_seo_description(),
				'inLanguage'  => 'en-IN',
				'isPartOf'    => array( '@id' => home_url( '/#website' ) ),
				'publisher'   => array( '@id' => home_url( '/#organization' ) ),
				'mainEntity'  => $items ? array( '@id' => $url . '#list' ) : null,
			)
		),
	);
	if ( $items ) {
		$nodes[] = array( '@type' => 'ItemList', '@id' => $url . '#list', 'numberOfItems' => count( $items ), 'itemListElement' => $items );
	}
	return $nodes;
}

/**
 * CollectionPage + ItemList of a hub's current page (put in the page's one graph below). Empty off a hub.
 *
 * @return array
 */
function aw_hub_schema_nodes() {
	static $nodes = null;
	if ( null !== $nodes ) {
		return $nodes;
	}
	$type = aw_hub_type();
	if ( ! $type || is_404() || 'aw_press' === $type ) {
		return $nodes = array(); // Media & PR has its own (aw_press_schema)
	}
	$url   = strtok( aw_hub_base_url(), '?' );
	$view  = function_exists( 'aw_seo_filtered_named' ) && aw_seo_filtered_named() ? aw_seo_filtered_view() : null;
	if ( $view ) { // a hub filtered by one category or tag is its own collection (filtered-views.php)
		$url = add_query_arg( $view['key'], rawurlencode( $view['slug'] ), strtok( aw_hub_base_url(), '?' ) );
	}
	$items = array();
	$start = ( aw_hub_page() - 1 ) * AW_HUBS[ $type ][2];
	$posts = aw_hub_posts( $type );
	$extra = 0;
	if ( 'aw_campaign' === $type && 1 === aw_hub_page() ) {
		$lead = get_page_by_path( 'india-writes-for-its-community-dogs', OBJECT, 'aw_campaign' ); // shown above the list as its own story (hubs.php)
		if ( $lead && 'publish' === $lead->post_status ) {
			array_unshift( $posts, $lead );
			$extra = 1;
		}
	}
	foreach ( $posts as $i => $p ) {
		if ( aw_is_noindex_item( $p->ID ) || ( function_exists( 'kits_preview_only_ids' ) && in_array( (int) $p->ID, kits_preview_only_ids(), true ) ) ) {
			continue; // not indexed (or preview only), so not listed
		}
		$items[] = array( '@type' => 'ListItem', 'position' => $start + $i + 1, 'url' => get_permalink( $p ), 'name' => aw_seo_plain( get_the_title( $p ) ) );
	}
	$name  = 'aw_listing' === $type && function_exists( 'aw_directory_h1' ) ? aw_directory_h1() : get_the_title( get_queried_object_id() );
	$name  = $view && 'aw_listing' !== $type ? $view['name'] . ' – ' . get_the_title( get_queried_object_id() ) : $name; // "Wildlife – News"
	if ( ! $items ) { // an empty list: the collection page alone
		return $nodes = array( array( '@type' => 'CollectionPage', '@id' => $url . '#page', 'url' => $url, 'name' => aw_seo_plain( $name ), 'description' => aw_seo_description(), 'inLanguage' => 'en-IN', 'isPartOf' => array( '@id' => home_url( '/#website' ) ), 'publisher' => array( '@id' => home_url( '/#organization' ) ) ) );
	}
	$nodes = array(
		array(
			'@type'       => 'CollectionPage',
			'@id'         => $url . '#page',
			'url'         => $url,
			'name'        => aw_seo_plain( $name ),
			'description' => aw_seo_description(),
			'inLanguage'  => 'en-IN',
			'isPartOf'    => array( '@id' => home_url( '/#website' ) ),
			'publisher'   => array( '@id' => home_url( '/#organization' ) ),
			'mainEntity'  => array( '@id' => $url . '#list' ),
		),
		array(
			'@type'           => 'ItemList',
			'@id'             => $url . '#list',
			'numberOfItems'   => (int) aw_hub_total( $type ) + $extra,
			'itemListElement' => $items,
		),
	);
	return $nodes;
}

/** ImageObject of a press item: its stored thumbnail, else the stored publisher logo (null when neither exists). */
function aw_press_image_schema( $id ) {
	foreach ( array( (int) get_post_thumbnail_id( $id ), (int) get_post_meta( $id, 'aw_press_logo', true ) ) as $att ) {
		$img = $att ? aw_seo_attachment( $att ) : null;
		if ( $img ) {
			return array_filter( array( '@type' => 'ImageObject', 'url' => $img['url'], 'width' => $img['width'], 'height' => $img['height'] ) );
		}
	}
	return null;
}

/** Media & PR page schema: CollectionPage + ItemList. */
function aw_press_schema() {
	$list = array();
	foreach ( aw_press_list() as $i => $p ) {
		$list[] = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'item'     => array_filter(
				array(
					'@type'         => 'Article', // coverage of Animal Writes, at the outlet's address
					'headline'      => aw_seo_plain( get_the_title( $p ) ),
					'about'         => array( '@id' => home_url( '/#organization' ) ),
					'name'          => aw_seo_plain( get_the_title( $p ) ),
					'url'           => (string) get_post_meta( $p->ID, 'aw_press_url', true ),
					'datePublished' => aw_schema_date( (string) get_post_meta( $p->ID, 'aw_press_date', true ) ),
					'publisher'     => get_post_meta( $p->ID, 'aw_press_publisher', true ) ? array( '@type' => 'Organization', 'name' => (string) get_post_meta( $p->ID, 'aw_press_publisher', true ) ) : null,
					'image'         => aw_press_image_schema( $p->ID ),
				)
			),
		);
	}
	return array_filter( array(
		'@type'       => 'CollectionPage',
		'@id'         => get_permalink() . '#page',
		'url'         => get_permalink(),
		'name'        => aw_seo_plain( get_the_title() ),
		'description' => aw_seo_plain( aw_seo_description() ),
		'inLanguage'  => 'en-IN',
		'isPartOf'    => array( '@id' => home_url( '/#website' ) ),
		'mainEntity'  => array( '@type' => 'ItemList', 'itemListElement' => $list ),
	) );
}

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
		if ( $nodes || is_singular( array( 'aw_news', 'aw_legal', 'post', 'aw_listing', 'aw_opportunity', 'aw_campaign' ) ) ) {
			unset( $data['richSnippet'] ); // Rank Math's type default (Titles & Meta) is filled here; a closed opportunity has none
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
		$faq = function_exists( 'aw_faq_schema' ) ? aw_faq_schema() : null; // parsed from the page's own FAQ accordion (animal-writes-core faq.php)
		if ( $faq ) {
			$data['aw_faq'] = $faq;
		}
		if ( ! empty( $data['BreadcrumbList']['@id'] ) ) { // Rank Math's own trail (breadcrumbs.php gives it the places and sections)
			foreach ( $data as $k => $node ) {
				if ( is_array( $node ) && in_array( $node['@type'] ?? '', array( 'WebPage', 'CollectionPage', 'ContactPage', 'AboutPage' ), true ) ) {
					$data[ $k ]['breadcrumb'] = array( '@id' => $data['BreadcrumbList']['@id'] );
				}
			}
		}
		if ( isset( $data['WebPage'] ) && is_array( $data['WebPage'] ) && empty( $data['WebPage']['description'] ) ) {
			$desc = aw_seo_plain( aw_seo_description() ); // the page's own search description, so a quote of the page has its summary
			if ( '' !== $desc ) {
				$data['WebPage']['description'] = $desc;
			}
		}
		return aw_schema_language( aw_schema_x_profiles( $data ) );
	},
	999
);

/**
 * The page's share picture as og:image prints it: an image typed in Rank Math's Social tab, a category's photo, else
 * the share image (helpers.php: a listing's own logo else the Directory band picture; News, Library and research the
 * publisher's image; a hub or Page its band picture; else the site share image). Null on a missing page.
 *
 * @return array|null [url, width, height]
 */
function aw_schema_page_image() {
	if ( is_404() ) {
		return null;
	}
	$typed = (int) aw_seo_typed( 'facebook_image_id' );
	$img   = $typed ? aw_seo_attachment( $typed, 'full' ) : null;
	if ( ! $img && '' !== aw_seo_typed( 'facebook_image' ) ) {
		return array( 'url' => aw_seo_typed( 'facebook_image' ), 'width' => 0, 'height' => 0 );
	}
	return $img ? $img : aw_seo_image();
}

/** Replace every reference {"@id": $from} in a graph with {"@id": $to}. */
function aw_schema_swap_ref( $node, $from, $to ) {
	if ( ! is_array( $node ) ) {
		return $node;
	}
	if ( array( '@id' => $from ) === $node ) {
		return array( '@id' => $to );
	}
	foreach ( $node as $k => $v ) {
		$node[ $k ] = aw_schema_swap_ref( $v, $from, $to );
	}
	return $node;
}

// Every page's own node names its picture (primaryImageOfPage), the one its og:image shares, so Search Console shows
// it beside the page (17 Oct 2026: only the home page had one). Collection, contact and category pages, which replace
// Rank Math's WebPage, get it too; a Directory profile, which had no page node, gets a WebPage about its organisation.
add_filter(
	'rank_math/json_ld',
	function ( $data ) {
		if ( ! is_array( $data ) || ! $data || is_404() ) {
			return $data;
		}
		$img = aw_schema_page_image();
		if ( ! $img || empty( $img['url'] ) ) {
			return $data;
		}
		$url   = html_entity_decode( (string) $img['url'], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		if ( false !== strpos( $url, '&' ) ) { // Rank Math prints the graph through wp_kses, which turns & into &amp;: the
			$url = strtok( $url, '?' );          // picture's plain address, as og:image prints it (its crop size unknown)
			$img = array( 'url' => $url, 'width' => 0, 'height' => 0 );
		}
		$old   = isset( $data['primaryImage']['@id'] ) ? (string) $data['primaryImage']['@id'] : '';
		$image = array_filter( array( '@type' => 'ImageObject', '@id' => $url, 'url' => $url, 'contentUrl' => $url, 'width' => (int) $img['width'], 'height' => (int) $img['height'] ) );
		if ( '' !== $old && $old !== $url ) {
			$data = aw_schema_swap_ref( $data, $old, $url ); // Rank Math's first content picture gives way to the shared one
		}
		$data = array( 'primaryImage' => $image ) + array_diff_key( $data, array( 'primaryImage' => 1 ) );
		$page_types = array( 'WebPage', 'CollectionPage', 'ContactPage', 'AboutPage', 'ItemPage', 'FAQPage', 'SearchResultsPage' );
		$found      = false;
		foreach ( $data as $k => $node ) {
			if ( 'aw_faq' === $k ) {
				continue; // our FAQ is part of the page, not the page itself (a Directory profile still gets its WebPage below)
			}
			if ( is_array( $node ) && array_intersect( (array) ( $node['@type'] ?? array() ), $page_types ) ) {
				$data[ $k ]['primaryImageOfPage'] = array( '@id' => $url );
				$found                            = true;
			}
		}
		if ( ! $found && is_singular() ) { // a Directory profile: the page about the organisation
			$id    = get_queried_object_id();
			$link  = get_permalink( $id );
			$about = null;
			foreach ( $data as $node ) {
				if ( is_array( $node ) && ! empty( $node['@id'] ) && 0 === strpos( (string) $node['@id'], $link ) ) {
					$about = array( '@id' => $node['@id'] );
					break;
				}
			}
			$data['WebPage'] = array_filter(
				array(
					'@type'              => 'WebPage',
					'@id'                => $link . '#webpage',
					'url'                => $link,
					'name'               => aw_seo_plain( wp_get_document_title() ),
					'description'        => aw_seo_plain( aw_seo_description() ),
					'inLanguage'         => 'en-IN',
					'isPartOf'           => array( '@id' => home_url( '/#website' ) ),
					'primaryImageOfPage' => array( '@id' => $url ),
					'breadcrumb'         => ! empty( $data['BreadcrumbList']['@id'] ) ? array( '@id' => $data['BreadcrumbList']['@id'] ) : null,
					'mainEntity'         => $about,
					'datePublished'      => get_the_date( 'c', $id ),
					'dateModified'       => get_the_modified_date( 'c', $id ),
				)
			);
		}
		return $data;
	},
	1001
);

/**
 * Plain text in the printed graph. Rank Math prints the graph through wp_kses_post_deep(), which writes every "&" in a
 * value as "&amp;"; JSON-LD is not HTML, so a title such as "Photo & Video Content" reached search engines as
 * "Photo &amp; Video Content" (and query strings in URLs as "&amp;"). The script Rank Math prints is read back and
 * every text value decoded to plain characters; values that are HTML (a JobPosting description) keep their markup.
 *
 * @param mixed $node Graph or value.
 * @return mixed
 */
function aw_schema_plain_values( $node ) {
	if ( is_array( $node ) ) {
		return array_map( 'aw_schema_plain_values', $node );
	}
	if ( is_string( $node ) && false !== strpos( $node, '&' ) && false === strpos( $node, '<' ) ) {
		return html_entity_decode( $node, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}
	return $node;
}
add_action( 'rank_math/head', fn() => ob_start(), 89 ); // Rank Math prints the graph at 90
add_action(
	'rank_math/head',
	function () {
		$html = (string) ob_get_clean();
		echo preg_replace_callback( // phpcs:ignore WordPress.Security.EscapeOutput -- Rank Math's own output; text values decoded, < and > written as < and >
			'#(<script type="application/ld\+json"[^>]*>)(.*?)(</script>)#s',
			function ( $m ) {
				$data = json_decode( $m[2], true );
				return is_array( $data ) ? $m[1] . wp_json_encode( aw_schema_plain_values( $data ), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG ) . $m[3] : $m[0];
			},
			$html
		);
	},
	91
);
