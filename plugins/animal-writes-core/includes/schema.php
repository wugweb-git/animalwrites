<?php
/**
 * Structured data (JSON-LD).
 *
 * - Listing pages: Organization (NGO) with name, URL, logo, locality/region (no street address), contact details
 *   when present, the official website as sameAs, and the AWBI recognition code as an identifier.
 * - Directory pages (all, state, district, type): ItemList of the organisations on the page, in order.
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
	if ( in_array( $type, array( 'aw_news', 'aw_legal' ), true ) ) {
		$at = 'NewsArticle';
	} elseif ( 'aw_research' === $type && preg_match( '/\b(Act|Rules?)\b/', $title ) ) {
		$at = 'Legislation';
	}
	$src_date = (string) get_post_meta( $id, 'aw_source_date', true );
	$image    = (string) get_post_meta( $id, 'aw_source_image', true );
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
	}
	if ( $src_url ) {
		// The original item we summarise or host.
		$data['isBasedOn'] = array_filter( array( '@type' => 'CreativeWork', 'url' => $src_url, 'publisher' => $src_name ? array( '@type' => 'Organization', 'name' => $src_name ) : null ) );
	}
	return array_filter( $data );
}

/** Home > Section > Page. */
function aw_breadcrumb_schema( $id ) {
	$type  = get_post_type( $id );
	$items = array( array( 'Home', home_url( '/' ) ) );
	$arch  = get_post_type_archive_link( $type );
	$obj   = get_post_type_object( $type );
	if ( $arch && $obj ) {
		$items[] = array( $obj->labels->name, $arch );
	}
	$items[] = array( wp_strip_all_tags( get_the_title( $id ) ), get_permalink( $id ) );
	$list    = array();
	foreach ( $items as $i => $it ) {
		$list[] = array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => $it[0], 'item' => $it[1] );
	}
	return array( '@type' => 'BreadcrumbList', 'itemListElement' => $list );
}

add_action(
	'wp_head',
	function () {
		if ( is_front_page() ) {
			aw_jsonld(
				array(
					'@context' => 'https://schema.org',
					'@graph'   => array(
						aw_publisher_schema(),
						array(
							'@type'           => 'WebSite',
							'@id'             => home_url( '/#website' ),
							'name'            => get_bloginfo( 'name' ),
							'url'             => home_url( '/' ),
							'inLanguage'      => 'en-IN',
							'publisher'       => array( '@id' => home_url( '/#organization' ) ),
							'potentialAction' => array(
								'@type'       => 'SearchAction',
								'target'      => home_url( '/?s={search_term_string}' ),
								'query-input' => 'required name=search_term_string',
							),
						),
					),
				)
			);
			return;
		}
		if ( is_singular( array( 'aw_news', 'aw_legal', 'aw_research', 'aw_guide' ) ) ) {
			$id = get_queried_object_id();
			aw_jsonld( array( '@context' => 'https://schema.org', '@graph' => array( aw_article_schema( $id ), aw_breadcrumb_schema( $id ), aw_publisher_schema() ) ) );
		} elseif ( is_singular( array( 'aw_listing', 'aw_opportunity', 'aw_campaign', 'aw_resource' ) ) ) {
			aw_jsonld( array( '@context' => 'https://schema.org' ) + aw_breadcrumb_schema( get_queried_object_id() ) );
		}
	},
	31
);
