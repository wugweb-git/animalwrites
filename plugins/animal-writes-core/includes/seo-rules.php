<?php
/**
 * Search titles and descriptions (SEO audits, 10 Oct 2026). Rank Math prints them: kits-core asks these rules through
 * kits_seo_title and kits_seo_description, and only where the editor left Rank Math's own field empty.
 *
 * - News, Library and Resources: over 60 characters the title is cut at a word (no "…", no site name); shorter ones
 *   get " – Animal Writes".
 * - Listings: "{Name}, {District} – Animal Writes" ("{Name}, {State}" without a district).
 * - Directory places, the section hubs, About and the home page: fixed titles; descriptions from counts and facts.
 * - Later pages of a list add " – Page N" (kits-core).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Plain text of a title or name: tags removed, entities decoded, spaces collapsed. */
function aw_seo_plain( $text ) {
	return trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
}

/** "{Text} – Animal Writes". */
function aw_seo_branded( $text ) {
	return $text . ' – ' . get_bloginfo( 'name' );
}

/** One rule for news, Library and Resources headlines. */
function aw_seo_headline_title( $title ) {
	$t = aw_seo_plain( $title );
	return mb_strlen( $t ) > 60 ? kits_seo_cut( $t, 60, '' ) : aw_seo_branded( $t );
}

/** "{Name}, {District}" or "{Name}, {State}" (no trailing comma; the place is not repeated when the name has it). */
function aw_seo_listing_name( $id ) {
	$name = preg_replace( '/[\s,;:\x{2013}\x{2014}-]+$/u', '', aw_seo_plain( get_post_field( 'post_title', $id ) ) );
	list( $state, $district ) = function_exists( 'aw_item_place_names' ) ? aw_item_place_names( $id ) : array( '', '' );
	$where = $district ? $district : $state;
	return ( '' !== $where && false === mb_stripos( $name, $where ) ) ? $name . ', ' . $where : $name;
}

/** Fixed titles of the section hubs (post type => title). */
const AW_HUB_TITLES = array(
	'aw_news'        => 'Animal Welfare News from India – Animal Writes',
	'aw_legal'       => 'Animal Law, Court Orders & Rules in India – Animal Writes',
	'post'           => 'Animal Welfare Guides & Research in India – Animal Writes',
	'aw_listing'     => 'Animal Welfare NGOs in India: Directory of 5,000+ Organisations',
	'aw_opportunity' => 'Animal Welfare Volunteer, Internship & Jobs in India',
	'aw_press'       => 'Animal Writes in the News',
);

/** Fixed descriptions of the section hubs that are not built from their intro. */
const AW_HUB_DESCRIPTIONS = array(
	'aw_opportunity' => 'Volunteer, foster or intern with animal welfare organisations across India. Roles, locations and how to apply.',
	'aw_press'       => 'Press coverage of Animal Writes and its citizen campaign for community animals across India.',
	'aw_listing'     => 'Find animal welfare NGOs, shelters, rescuers, gaushalas and government vets in every state and district of India.',
);

add_filter(
	'kits_seo_title',
	function ( $title ) {
		if ( is_404() || is_search() ) {
			return $title;
		}
		if ( is_front_page() ) {
			return 'Animal Writes – Animal welfare news, law and help in India';
		}
		if ( is_singular( array( 'aw_news', 'aw_legal', 'post' ) ) ) {
			return aw_seo_headline_title( get_post_field( 'post_title', get_queried_object_id() ) );
		}
		if ( is_singular( 'aw_listing' ) ) {
			return aw_seo_branded( aw_seo_listing_name( get_queried_object_id() ) );
		}
		if ( is_page( 'about' ) ) {
			return 'About Animal Writes';
		}
		$hub = function_exists( 'aw_hub_type' ) ? aw_hub_type() : '';
		if ( 'aw_listing' === $hub ) {
			$place = aw_current_place();
			if ( aw_current_type_name() || ! empty( $_GET['q'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				return $title; // type pages and filtered lists keep their own titles
			}
			if ( ! $place ) {
				return AW_HUB_TITLES['aw_listing'];
			}
			if ( $place->parent ) {
				return sprintf( 'Animal Welfare NGOs in %1$s, %2$s: Rescue, Shelters, Vets', $place->name, aw_place_parent_state( $place )->name );
			}
			return sprintf( 'Animal Welfare NGOs & Animal Shelters in %s – Animal Writes', $place->name );
		}
		return isset( AW_HUB_TITLES[ $hub ] ) ? AW_HUB_TITLES[ $hub ] : $title;
	}
);

/* ---------- Descriptions ---------- */

/** Plural of a listing type for a sentence: "gaushalas", "ABC centres", "veterinary dispensaries". */
function aw_seo_type_plural( $slug ) {
	$fixed = array(
		'veterinary'             => 'veterinary hospitals and dispensaries',
		'veterinary-office'      => 'government veterinary offices',
		'mobile-veterinary-unit' => 'mobile veterinary units',
		'wildlife-rescue'        => 'wildlife rescue groups',
		'emergency-rescue'       => 'emergency rescue groups',
		'legal-aid'              => 'legal aid groups',
		'boarding-and-foster'    => 'boarding and foster homes',
	);
	if ( isset( $fixed[ $slug ] ) ) {
		return $fixed[ $slug ];
	}
	$name  = trim( preg_replace( '/\s*\(.*?\)\s*/', ' ', aw_listing_type_name( $slug ) ) );
	$words = array_map( fn( $w ) => preg_match( '/^[A-Z]{2,}$/', $w ) ? $w : mb_strtolower( $w ), explode( ' ', $name ) );
	$last  = (string) array_pop( $words );
	if ( preg_match( '/[^aeiou]y$/', $last ) ) {
		$last = substr( $last, 0, -1 ) . 'ies';
	} elseif ( preg_match( '/(s|x|ch|sh)$/', $last ) ) {
		$last .= 'es';
	} elseif ( '' !== $last ) {
		$last .= 's';
	}
	return trim( implode( ' ', $words ) . ' ' . $last );
}

/** The type slug of the current Directory type page ('' elsewhere). */
function aw_seo_dir_type_slug() {
	$slug = (string) get_query_var( 'aw_type_slug' );
	return '' !== $slug ? $slug : '';
}

/**
 * Hub description built from its own intro (the hub Page's excerpt, as shown under its H1) and the list's facts:
 * "{intro} {N stories} on {topic}, {topic} and {topic}." up to 155 characters.
 */
function aw_seo_hub_intro_description( $type ) {
	$page  = get_queried_object();
	$intro = aw_seo_plain( ( $page instanceof WP_Post && '' !== trim( $page->post_excerpt ) ) ? $page->post_excerpt : aw_hub_intro( $type ) );
	$count = function_exists( 'aw_hub_count_label' ) ? aw_hub_count_label( $type ) : '';
	if ( '' === $count ) {
		return $intro;
	}
	$topics = aw_seo_top_topics( $type );
	$best   = $intro . ' ' . $count . '.';
	for ( $n = 1; $n <= count( $topics ); $n++ ) {
		$list = array_slice( $topics, 0, $n );
		$last = array_pop( $list );
		$line = $intro . ' ' . $count . ' on ' . ( $list ? implode( ', ', $list ) . ' and ' : '' ) . $last . '.';
		if ( mb_strlen( $line ) > 155 ) {
			break;
		}
		$best = $line;
	}
	return $best;
}

/** The topics with the most published items of a type, as lower-case names (cached for 12 hours). */
function aw_seo_top_topics( $type ) {
	$key = 'aw_seo_topics_' . $type;
	$out = get_transient( $key );
	if ( false === $out ) {
		global $wpdb;
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT t.name FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id AND tt.taxonomy = 'aw_topic' JOIN {$wpdb->term_relationships} r ON r.term_taxonomy_id = tt.term_taxonomy_id JOIN {$wpdb->posts} p ON p.ID = r.object_id AND p.post_type = %s AND p.post_status = 'publish' GROUP BY t.term_id ORDER BY COUNT(*) DESC LIMIT 4", $type ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$out   = array_map( fn( $n ) => implode( ' ', array_map( fn( $w ) => preg_match( '/^[A-Z]{2,}$/', $w ) ? $w : mb_strtolower( $w ), explode( ' ', html_entity_decode( $n, ENT_QUOTES, 'UTF-8' ) ) ) ), (array) $names );
		set_transient( $key, $out, 12 * HOUR_IN_SECONDS );
	}
	return (array) $out;
}

/** AWBI-recognised listing without a description of its own: one line of its facts ('' when it does not apply). */
function aw_seo_awbi_description( $id, $desc ) {
	$code = trim( (string) get_post_meta( $id, 'aw_awbi_code', true ) );
	if ( '' === $code || has_excerpt( $id ) || ( '' !== trim( (string) $desc ) && ! preg_match( '/^Recognised by the Animal Welfare Board of India/i', trim( wp_strip_all_tags( (string) $desc ) ) ) ) ) {
		return '';
	}
	$types = get_the_terms( $id, 'aw_listing_type' );
	if ( $types && ! is_wp_error( $types ) && count( $types ) > 1 ) { // the specific type (gaushala) before the general one
		usort( $types, fn( $a, $b ) => (int) ( 'animal-welfare-organisation' === $a->slug ) - (int) ( 'animal-welfare-organisation' === $b->slug ) );
	}
	$type  = ( $types && ! is_wp_error( $types ) ) ? implode( ' ', array_map( fn( $w ) => preg_match( '/^[A-Z]{2,}$/', $w ) ? $w : mb_strtolower( $w ), explode( ' ', trim( preg_replace( '/\s*\(.*?\)/', '', $types[0]->name ) ) ) ) ) : 'organisation';
	list( $state, $district ) = aw_item_place_names( $id );
	$where = implode( ', ', array_filter( array( $district, $state ) ) );
	$name  = preg_replace( '/[\s,;:]+$/u', '', aw_seo_plain( get_post_field( 'post_title', $id ) ) );
	$line = sprintf( '%1$s is an AWBI-recognised %2$s%3$s (AWBI code %4$s).', $name, $type, $where ? ' in ' . $where : '', $code );
	return mb_strlen( $line ) <= 120 ? $line . ' Address, contact and how to help.' : $line; // whole sentences only
}

add_filter(
	'kits_seo_description',
	function ( $desc ) {
		if ( is_404() || is_search() ) {
			return $desc;
		}
		if ( is_singular( 'aw_listing' ) ) {
			$awbi = aw_seo_awbi_description( get_queried_object_id(), $desc );
			return '' !== $awbi ? $awbi : $desc;
		}
		if ( is_singular( 'aw_legal' ) && mb_strlen( aw_seo_plain( $desc ) ) < 60 ) { // a one-line note: the summary says more
			$post = get_queried_object();
			foreach ( array( (string) get_post_meta( $post->ID, 'kits_ai_summary', true ), kits_seo_intro( $post ) ) as $better ) {
				if ( mb_strlen( aw_seo_plain( $better ) ) >= 60 ) {
					return $better;
				}
			}
			return $desc;
		}
		$hub = function_exists( 'aw_hub_type' ) ? aw_hub_type() : '';
		if ( 'aw_listing' === $hub ) {
			$place = aw_current_place();
			$slug  = aw_seo_dir_type_slug();
			$n     = number_format_i18n( aw_hub_total( 'aw_listing' ) );
			if ( ! $place ) {
				return ( aw_current_type_name() || ! empty( $_GET['q'] ) ) ? $desc : AW_HUB_DESCRIPTIONS['aw_listing']; // phpcs:ignore WordPress.Security.NonceVerification
			}
			$state = aw_place_parent_state( $place );
			if ( $place->parent && '' !== $slug && '0' !== $n ) {
				return sprintf( '%1$s %2$s in %3$s, %4$s: addresses, contact details and services.', $n, aw_seo_type_plural( $slug ), $place->name, $state->name );
			}
			if ( aw_current_type_name() || ! empty( $_GET['q'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				return $desc; // a filtered list (noindex): as before
			}
			if ( $place->parent ) {
				return sprintf( '%1$s animal rescue groups, shelters, gaushalas and vets in %2$s, %3$s. Phone, address and services.', $n, $place->name, $state->name );
			}
			return sprintf( '%1$s animal welfare NGOs, shelters, rescuers, gaushalas and government vets in %2$s, district by district. Phone, address and services.', $n, $place->name );
		}
		if ( isset( AW_HUB_DESCRIPTIONS[ $hub ] ) ) {
			return AW_HUB_DESCRIPTIONS[ $hub ];
		}
		if ( in_array( $hub, array( 'aw_news', 'aw_legal' ), true ) ) {
			return aw_seo_hub_intro_description( $hub );
		}
		return $desc;
	},
	20
);
