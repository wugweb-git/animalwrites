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
	$state    = '';
	$district = '';
	foreach ( (array) wp_get_object_terms( $id, 'aw_city' ) as $t ) {
		if ( $t->parent ) {
			$district = $t->name;
		} else {
			$state = $t->name;
		}
	}
	return array( $state, $district );
}

/**
 * Organization data for a listing.
 *
 * @param int $id Post ID.
 * @return array
 */
function aw_listing_schema( $id ) {
	list( $state, $district ) = aw_listing_place( $id );
	$org = array(
		'@type' => has_term( array( 'veterinary-hospital', 'veterinary-dispensary', 'mobile-veterinary-unit', 'veterinary-office' ), 'aw_listing_type', $id ) ? 'VeterinaryCare' : 'NGO',
		'@id'   => get_permalink( $id ) . '#organization',
		'name'  => get_the_title( $id ),
		'url'   => get_permalink( $id ),
	);
	$logo = get_the_post_thumbnail_url( $id, 'medium' );
	if ( $logo ) {
		$org['logo'] = $logo;
	}
	if ( $state || $district ) {
		$org['address'] = array_filter( array( '@type' => 'PostalAddress', 'addressLocality' => $district, 'addressRegion' => $state, 'addressCountry' => 'IN' ) );
	}
	$phone = (string) get_post_meta( $id, 'aw_phone', true );
	$email = (string) get_post_meta( $id, 'aw_email', true );
	$site  = (string) get_post_meta( $id, 'aw_website', true );
	if ( $phone ) {
		$org['telephone'] = $phone;
	}
	if ( $email ) {
		$org['email'] = $email;
	}
	if ( $site ) {
		$org['sameAs'] = array( $site );
	}
	$code = (string) get_post_meta( $id, 'aw_awbi_code', true );
	if ( $code ) {
		$org['identifier'] = array( '@type' => 'PropertyValue', 'propertyID' => 'AWBI recognition code', 'value' => $code );
	}
	return $org;
}

add_action(
	'wp_head',
	function () {
		if ( is_singular( 'aw_listing' ) ) {
			aw_jsonld( array( '@context' => 'https://schema.org' ) + aw_listing_schema( get_queried_object_id() ) );
			return;
		}
		if ( function_exists( 'aw_is_directory_view' ) && aw_is_directory_view() ) {
			global $wp_query;
			$items = array();
			$start = (int) max( 0, ( max( 1, (int) get_query_var( 'paged' ) ) - 1 ) * (int) $wp_query->get( 'posts_per_page' ) );
			foreach ( $wp_query->posts as $i => $p ) {
				$items[] = array( '@type' => 'ListItem', 'position' => $start + $i + 1, 'url' => get_permalink( $p ), 'name' => get_the_title( $p ) );
			}
			if ( $items ) {
				aw_jsonld(
					array(
						'@context'        => 'https://schema.org',
						'@type'           => 'ItemList',
						'name'            => wp_strip_all_tags( get_the_archive_title() ),
						'numberOfItems'   => (int) $wp_query->found_posts,
						'itemListElement' => $items,
					)
				);
			}
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
		$iss = function_exists( 'aw_first_term' ) ? aw_first_term( $id, 'aw_source_type' ) : null;
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
		}
		$graph[] = aw_breadcrumb_schema();
		aw_jsonld( array( '@context' => 'https://schema.org', '@graph' => array_values( array_filter( $graph ) ) ) );
	},
	31
);
