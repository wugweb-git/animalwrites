<?php
/**
 * Search titles and meta descriptions.
 *
 * Items (news, Library, Resources, listings, opportunities, campaigns, press, Pages): the title and description are
 * stored in Rank Math's own fields (rank_math_title, rank_math_description), so the Rank Math box shows what is printed.
 * They are written when an item is published or saved, and only where the field is empty or still holds the value
 * written automatically last time (_aw_seo_auto); a value typed in Rank Math is never replaced.
 *   - News: over 60 characters the headline is cut at a word (no "…", no site name); shorter ones get
 *     " – Animal Writes". A publisher's name appended to the headline and separators joining its parts are dropped.
 *   - The only separator in any title is the " – " before "Animal Writes" (owner, 15 Oct 2026): no colons, pipes,
 *     other dashes, or commas and brackets used as separators (aw_seo_unseparate); later list pages add " Page N"
 *     before the site name.
 *   - Library and Resources: aw_seo_resource_title() (Title Case, the kind added to short ones, never cut inside).
 *   - Listings: "{Name} in {District} – Animal Writes" ("{Name} in {State}" without a district); over 60 characters
 *     without " – Animal Writes", and the name itself is never cut.
 *   - Opportunities: "{Title} – Animal Writes"; over 60 characters the title alone (the role and organisation are
 *     never cut).
 *   - Home and About: fixed titles. Everything else: "{Title} – Animal Writes".
 *   - Description: the excerpt, else the first real paragraph; listings without one get a line of their facts.
 *
 * Lists (section hub Pages, Directory places and type pages, filtered lists, archives) change with their contents and
 * page number, which Rank Math's templates cannot express: they are built here at run time, through Rank Math's
 * title and description filters. A title or description typed in Rank Math for a hub Page still wins.
 *
 * The seven section hub Pages also keep a copy of their page-1 title and description in Rank Math's fields, so the
 * Rank Math box shows what is printed (owner, 14 Oct 2026). The copy is refreshed whenever page 1 is built with a
 * different value (counts change) and is marked as automatic (_aw_seo_auto), so it is never mistaken for a typed one.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Content types whose items carry stored titles, descriptions and focus keywords. */
const AW_SEO_ITEM_TYPES = array( 'aw_listing', 'aw_news', 'post', 'aw_legal', 'aw_press', 'page', 'aw_opportunity', 'aw_campaign' );

/** Whether a post is a section hub Page (a list, not an item). */
function aw_seo_is_hub_page( $post ) {
	$post = get_post( $post );
	if ( ! $post || 'page' !== $post->post_type || $post->post_parent ) {
		return false;
	}
	foreach ( AW_HUBS as $h ) {
		if ( $h[0] === $post->post_name ) {
			return true;
		}
	}
	return false;
}

/**
 * The search title of an item ('' for a hub Page).
 *
 * @param int|WP_Post $post Post.
 * @return string
 */
function aw_seo_item_title( $post ) {
	$post = get_post( $post );
	if ( ! $post || aw_seo_is_hub_page( $post ) ) {
		return '';
	}
	if ( (int) get_option( 'page_on_front' ) === (int) $post->ID ) {
		return 'Animal Writes – Animal welfare news, law and help in India';
	}
	if ( in_array( $post->post_type, array( 'aw_legal', 'post' ), true ) ) {
		return aw_seo_resource_title( $post ); // Library and Resources (helpers.php)
	}
	if ( 'aw_news' === $post->post_type ) {
		return aw_seo_headline_title( aw_seo_unseparate( aw_seo_plain( $post->post_title ), aw_seo_plain( (string) get_post_meta( $post->ID, 'aw_source_name', true ) ) ) );
	}
	if ( 'aw_listing' === $post->post_type ) {
		$name = aw_seo_listing_name( $post->ID );
		return mb_strlen( aw_seo_branded( $name ) ) <= 60 ? aw_seo_branded( $name ) : $name; // over 60: no site name (Google shows it beside the result); the name is never cut (owner, 15 Oct 2026)
	}
	if ( 'aw_opportunity' === $post->post_type ) {
		$t = aw_seo_unseparate( aw_seo_plain( wptexturize( $post->post_title ) ) );
		return mb_strlen( aw_seo_branded( $t ) ) <= 60 ? aw_seo_branded( $t ) : $t; // over 60: no site name; the role and organisation are never cut (owner, 15 Oct 2026)
	}
	if ( 'page' === $post->post_type && 'about' === $post->post_name && ! $post->post_parent ) {
		return 'About Animal Writes';
	}
	return aw_seo_branded( aw_seo_plain( wptexturize( $post->post_title ) ) ); // as WordPress's own document title
}

/**
 * The meta description of an item ('' for a hub Page).
 *
 * @param int|WP_Post $post Post.
 * @return string
 */
function aw_seo_item_description( $post ) {
	$post = get_post( $post );
	if ( ! $post || aw_seo_is_hub_page( $post ) ) {
		return '';
	}
	$desc = has_excerpt( $post ) ? $post->post_excerpt : aw_seo_intro( $post );
	if ( 'aw_listing' === $post->post_type ) {
		if ( '' === trim( wp_strip_all_tags( (string) $desc ) ) ) {
			$desc = aw_listing_fact_description( $post->ID ); // no description of its own: one from its facts
		}
		$awbi = aw_seo_awbi_description( $post->ID, $desc );
		$desc = '' !== $awbi ? $awbi : $desc;
	} elseif ( 'aw_legal' === $post->post_type && mb_strlen( aw_seo_plain( $desc ) ) < 60 ) { // a one-line note: the intro says more
		$better = aw_seo_intro( $post );
		$desc   = mb_strlen( aw_seo_plain( $better ) ) >= 60 ? $better : $desc;
	}
	if ( '' === trim( (string) $desc ) ) {
		$desc = get_bloginfo( 'description' );
	}
	$desc = preg_replace( '/\s+,/u', ',', html_entity_decode( wp_strip_all_tags( (string) $desc ), ENT_QUOTES, 'UTF-8' ) ); // "Jaipur , Rajasthan"
	// A summary's description is the summary condensed (Summary rules): its lead, or the first sentences that fit whole.
	if ( has_excerpt( $post ) && function_exists( 'aw_summary_section' ) && '' !== aw_summary_section( $post ) ) {
		return aw_summary_description( trim( str_replace( '[Needs legal review]', '', $desc ) ) );
	}
	return aw_seo_cut( $desc, 155 );
}

/**
 * Write an item's title and description into Rank Math's fields: where empty, or still the value written
 * automatically last time. Listings also get their name as the focus keyword when that field is empty.
 *
 * @param int $post_id Post ID.
 * @return array What was written: field => value.
 */
function aw_seo_fill_post( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || 'publish' !== $post->post_status || ! in_array( $post->post_type, AW_SEO_ITEM_TYPES, true ) || aw_seo_is_hub_page( $post ) ) {
		return array();
	}
	$auto  = (array) json_decode( (string) get_post_meta( $post->ID, '_aw_seo_auto', true ), true );
	$want  = array(
		'title'       => aw_seo_item_title( $post ),
		'description' => aw_seo_item_description( $post ),
	);
	$wrote = array();
	foreach ( $want as $field => $value ) {
		$have = trim( (string) get_post_meta( $post->ID, 'rank_math_' . $field, true ) );
		if ( '' === $value || $have === $value || ( '' !== $have && $have !== ( $auto[ $field ] ?? null ) ) ) {
			if ( '' !== $have && $have === $value ) {
				$auto[ $field ] = $value;
			}
			continue; // nothing to write, or typed by an editor
		}
		update_post_meta( $post->ID, 'rank_math_' . $field, wp_slash( $value ) );
		$auto[ $field ]  = $value;
		$wrote[ $field ] = $value;
	}
	if ( 'aw_listing' === $post->post_type && '' === trim( (string) get_post_meta( $post->ID, 'rank_math_focus_keyword', true ) ) ) {
		$kw = aw_seo_listing_keyword( $post );
		if ( '' !== $kw ) {
			update_post_meta( $post->ID, 'rank_math_focus_keyword', wp_slash( $kw ) );
			$wrote['focus_keyword'] = $kw;
		}
	}
	update_post_meta( $post->ID, '_aw_seo_auto', wp_slash( wp_json_encode( $auto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
	return $wrote;
}

/** A listing's focus keyword: its name, plain, without a trailing place or bracketed abbreviation clutter. */
function aw_seo_listing_keyword( $post ) {
	$name = aw_seo_plain( get_post_field( 'post_title', $post ) );
	$name = preg_replace( '/[\s,;:\x{2013}\x{2014}-]+$/u', '', $name );
	return trim( str_replace( array( '"', '“', '”' ), '', $name ) );
}

// After every save of a published item (terms and meta are in place by then; also for REST and ingest).
add_action(
	'wp_after_insert_post',
	function ( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || 'publish' !== $post->post_status ) {
			return;
		}
		aw_seo_fill_post( $post_id );
	},
	99,
	2
);

// Daily: items published another way (direct status changes) get their fields too.
add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'aw_seo_fill_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'aw_seo_fill_daily' );
		}
	}
);
add_action(
	'aw_seo_fill_daily',
	function () {
		global $wpdb;
		$types = implode( ',', array_map( fn( $t ) => "'" . esc_sql( $t ) . "'", AW_SEO_ITEM_TYPES ) );
		$ids   = $wpdb->get_col( "SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'rank_math_title' WHERE p.post_type IN ({$types}) AND p.post_status = 'publish' AND m.meta_id IS NULL LIMIT 500" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		foreach ( $ids as $id ) {
			aw_seo_fill_post( $id );
		}
	}
);

/* ---------- Lists: built at run time ---------- */

/** The title rule for the current list view, '' to leave it to WordPress (type pages, filtered lists, archives). */
function aw_seo_list_title() {
	if ( is_404() || is_search() ) {
		return '';
	}
	$hub = function_exists( 'aw_hub_type' ) ? aw_hub_type() : '';
	if ( 'aw_listing' === $hub ) {
		$place = aw_current_place();
		$typed = aw_seo_dir_title(); // district × type pages and lists filtered by type (programmatic.php)
		if ( '' !== $typed ) {
			return $typed;
		}
		if ( aw_current_type_name() || ! empty( $_GET['q'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return ''; // name searches and other filtered lists keep their own titles
		}
		if ( ! $place ) {
			return aw_seo_fit( array( AW_HUB_TITLES['aw_listing'], 'Animal Welfare NGO Directory of India' ), 60 - mb_strlen( aw_seo_page_suffix() ) );
		}
		if ( $place->parent ) { // within 60 characters: the longest of these that fits
			$where = $place->name . ' ' . aw_place_parent_state( $place )->name;
			$phone = aw_seo_has_numbers( $place ) ? array( 'Animal Rescue & NGO Contact Numbers in ' . $where, 'Animal Rescue & NGO Contact Numbers in ' . $place->name ) : array(); // searched as "animal rescue {district} contact number"
			return aw_seo_fit(
				array_merge(
					$phone,
					array(
						'Animal Welfare NGOs Shelters & Vets in ' . $where,
						'Animal Welfare NGOs & Vets in ' . $where,
						'Animal Welfare NGOs in ' . $where,
						'Animal Welfare NGOs in ' . $place->name,
					)
				),
				60 - mb_strlen( aw_seo_page_suffix() )
			);
		}
		// A state: its two biggest listing types, each listing counted once by its most specific type (owner, 15 Oct 2026). "Animal Welfare {Type1} & {Type2} in {State}"
		// (no comma: one separator only); "{Type1} & More" when it has a third type; one type, then "NGOs", when the longer ones pass 60 characters.
		$types = array_map( fn( $b ) => aw_seo_state_type_title( $place, $b[0] ), aw_seo_dir_breakdown( $place ) );
		$first = $types[0] ?? 'NGOs';
		$in    = ' in ' . $place->name;
		$more  = count( $types ) > 2 ? ' & More' : '';
		$try   = array();
		if ( count( $types ) > 1 ) {
			$try[] = 'Animal Welfare ' . $first . ' & ' . $types[1] . $in;
		}
		if ( $more ) {
			$try[] = 'Animal Welfare ' . $first . $more . $in;
		}
		$try[] = 'Animal Welfare ' . $first . $in;
		$try[] = 'Animal Welfare NGOs' . $in;
		$try[] = 'Animal Welfare in ' . preg_replace( '/^The /', '', $place->name ); // a long name ("The Dadra and Nagar Haveli and Daman and Diu"): never cut inside it
		return aw_seo_fit( array_unique( $try ), 60 - mb_strlen( aw_seo_page_suffix() ) );
	}
	if ( ( is_tag() || aw_seo_is_category_page() ) && function_exists( 'aw_seo_term_title' ) ) { // a tag or category page: its written title (programmatic.php)
		$what = aw_seo_term_title( get_queried_object() );
		return aw_seo_page_number() > 1 ? aw_seo_fit( array( $what, preg_replace( '/ – ' . preg_quote( get_bloginfo( 'name' ), '/' ) . '$/u', '', $what ) ), 60 - mb_strlen( aw_seo_page_suffix() ) ) : $what;
	}
	return AW_HUB_TITLES[ $hub ] ?? '';
}

/**
 * A listing type as a state page's title names it, plural and short: "NGOs", "Gaushalas", "ABC Centres". Every
 * veterinary type (hospital, dispensary, clinic, government office, 1962 unit, bird hospital) is counted together and
 * always named "Vets" (owner, 15 Oct 2026).
 *
 * @param object $place State.
 * @param string $key   Type slug or "veterinary".
 * @return string
 */
function aw_seo_state_type_title( $place, $key ) {
	$words = array(
		'animal-welfare-organisation' => 'NGOs',
		'gaushala'                    => 'Gaushalas',
		'abc-centre'                  => 'ABC Centres',
		'wildlife-rescue'             => 'Wildlife Rescuers',
		'veterinary'                  => 'Vets',
	);
	if ( isset( $words[ $key ] ) ) {
		return $words[ $key ];
	}
	return aw_seo_title_case( aw_seo_type_words( $key, 2, true ) );
}

/** True while the WordPress document title of a list is being built (Rank Math's title filter then steps aside). */
function aw_seo_building_title( $set = null ) {
	static $busy = false;
	if ( null !== $set ) {
		$busy = (bool) $set;
	}
	return $busy;
}

/**
 * Keep a hub Page's stored Rank Math title or description equal to what its first page prints (see the header).
 * Only on the hub's own address without query or page number, and only where the field is empty or still holds the
 * automatic copy.
 *
 * @param string $field title|description.
 * @param string $value What page 1 prints.
 */
function aw_seo_mirror_hub( $field, $value ) {
	$post = get_queried_object();
	if ( '' === $value || ! is_singular() || ! ( $post instanceof WP_Post ) || ! aw_seo_is_hub_page( $post ) || aw_seo_page_number() > 1 || ! empty( $_GET ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	$path = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( untrailingslashit( $path ) !== untrailingslashit( (string) wp_parse_url( get_permalink( $post ), PHP_URL_PATH ) ) ) {
		return; // a Directory place or type page shown through the hub Page
	}
	$auto = (array) json_decode( (string) get_post_meta( $post->ID, '_aw_seo_auto', true ), true );
	$have = trim( (string) get_post_meta( $post->ID, 'rank_math_' . $field, true ) );
	if ( $have === $value || ( '' !== $have && $have !== ( $auto[ $field ] ?? null ) ) ) {
		return; // up to date, or typed by an editor
	}
	update_post_meta( $post->ID, 'rank_math_' . $field, wp_slash( $value ) );
	$auto[ $field ] = $value;
	update_post_meta( $post->ID, '_aw_seo_auto', wp_slash( wp_json_encode( $auto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
}

/**
 * Refresh the stored copies of all seven hub Pages without a visitor (daily, and from WP-CLI:
 * `wp eval 'print_r( aw_seo_sync_hubs() );'`): each hub's page 1 is fetched and the title and description it prints are
 * stored (cached pages are never built, so the request-time copy alone would go stale).
 *
 * @return array Hub slug => [title, description] as printed, or the error.
 */
function aw_seo_sync_hubs() {
	$out = array();
	foreach ( AW_HUBS as $h ) {
		$page = get_page_by_path( $h[0] );
		if ( ! $page || 'publish' !== $page->post_status ) {
			continue;
		}
		$res = wp_remote_get( get_permalink( $page ), array( 'timeout' => 30, 'user-agent' => 'aw-seo-hub-sync' ) );
		$doc = is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ? '' : (string) wp_remote_retrieve_body( $res );
		$head = (string) strstr( $doc, '</head>', true );
		if ( ! preg_match( '#<title[^>]*>(.*?)</title>#si', $head, $t ) || ! preg_match( '#<meta name="description" content="([^"]*)"#i', $head, $d ) ) {
			$out[ $h[0] ] = is_wp_error( $res ) ? $res->get_error_message() : 'no title or description';
			continue;
		}
		$printed = array(
			'title'       => trim( html_entity_decode( wp_strip_all_tags( $t[1] ), ENT_QUOTES, 'UTF-8' ) ),
			'description' => trim( html_entity_decode( $d[1], ENT_QUOTES, 'UTF-8' ) ),
		);
		$auto = (array) json_decode( (string) get_post_meta( $page->ID, '_aw_seo_auto', true ), true );
		foreach ( $printed as $field => $value ) {
			$have = trim( (string) get_post_meta( $page->ID, 'rank_math_' . $field, true ) );
			if ( '' === $value || $have === $value || ( '' !== $have && $have !== ( $auto[ $field ] ?? null ) ) ) {
				continue; // up to date, or typed by an editor
			}
			update_post_meta( $page->ID, 'rank_math_' . $field, wp_slash( $value ) );
			$auto[ $field ] = $value;
		}
		update_post_meta( $page->ID, '_aw_seo_auto', wp_slash( wp_json_encode( $auto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
		$out[ $h[0] ] = array_values( $printed );
	}
	return $out;
}
add_action( 'aw_seo_fill_daily', 'aw_seo_sync_hubs', 20 );

// While building, the document title comes from WordPress's own parts (the Directory type pages set theirs).
add_filter( 'pre_get_document_title', fn( $t ) => aw_seo_building_title() ? '' : $t, 16 );
add_filter(
	'pre_get_document_title',
	function ( $title ) {
		if ( ! aw_seo_building_title() ) {
			return $title;
		}
		$ours = aw_seo_list_title();
		return '' !== $ours ? aw_seo_title_with_page( $ours ) : $title;
	},
	25
);

add_filter(
	'rank_math/frontend/title',
	function ( $title ) {
		if ( aw_seo_building_title() || ! aw_seo_is_list_view() || is_404() ) {
			return $title; // items: Rank Math's own field
		}
		if ( '' !== aw_seo_typed( 'title' ) ) {
			return aw_seo_title_with_page( $title ); // typed in Rank Math: used as is (" Page N" on later pages)
		}
		aw_seo_building_title( true );
		$ours = wp_get_document_title();
		aw_seo_building_title( false );
		$ours  = trim( html_entity_decode( wp_strip_all_tags( $ours ), ENT_QUOTES, 'UTF-8' ) );
		$brand = ' – ' . get_bloginfo( 'name' );
		if ( mb_strlen( $ours ) > 60 && aw_seo_page_number() > 1 && false !== mb_strpos( $ours, $brand ) ) {
			$ours = str_replace( $brand, '', $ours ); // later pages: the page number before the site name
		}
		$ours = '' !== $ours ? $ours : $title;
		aw_seo_mirror_hub( 'title', $ours );
		return $ours;
	},
	20
);

/** The description of the current list view. */
function aw_seo_list_description() {
	$desc = '';
	$hub  = function_exists( 'aw_hub_type' ) ? aw_hub_type() : '';
	if ( is_singular() ) { // a hub Page: its excerpt or intro, then the rules below
		$post = get_queried_object();
		$desc = has_excerpt( $post ) ? $post->post_excerpt : aw_seo_intro( $post );
	} elseif ( is_post_type_archive() ) {
		$desc = AW_ARCHIVE_DESCRIPTIONS[ get_query_var( 'post_type' ) ] ?? '';
	} elseif ( is_tag() && '' === trim( (string) term_description() ) && function_exists( 'aw_seo_tag_description' ) ) {
		$desc = aw_seo_tag_description( get_queried_object() ); // what the tag holds, counted (programmatic.php)
	} elseif ( aw_seo_is_category_page() && '' !== aw_seo_category_description( get_queried_object() ) ) {
		$desc = aw_seo_category_description( get_queried_object() ); // what the category holds, counted (programmatic.php)
	} elseif ( is_tax() || is_category() || is_tag() ) {
		$desc = term_description() ?: single_term_title( '', false ) . ' on ' . get_bloginfo( 'name' ) . '.';
	}
	if ( function_exists( 'aw_is_directory_view' ) && aw_is_directory_view() && ! get_query_var( 'aw_dir_type' ) && empty( $_GET['tag'] ) && empty( $_GET['q'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$desc = aw_directory_intro();
	}
	$built = 'aw_listing' === $hub ? aw_seo_dir_description() : ''; // places, district × type pages, lists filtered by type (programmatic.php)
	if ( '' !== $built ) {
		$desc = $built;
	} elseif ( 'aw_listing' === $hub && ! is_404() ) {
		$place = aw_current_place();
		$slug  = (string) get_query_var( 'aw_type_slug' );
		$n     = number_format_i18n( aw_hub_total( 'aw_listing' ) );
		$state = $place ? aw_place_parent_state( $place ) : null;
		if ( ! $place ) {
			$desc = ( aw_current_type_name() || ! empty( $_GET['q'] ) ) ? $desc : sprintf( AW_HUB_DESCRIPTIONS['aw_listing'], $n ); // phpcs:ignore WordPress.Security.NonceVerification
		} elseif ( $place->parent && '' !== $slug && '0' !== $n ) {
			$what = '1' === $n ? 'One ' . preg_replace( '/\bgaushala\b/u', 'Gaushala', mb_strtolower( trim( preg_replace( '/\s*\(.*?\)\s*/', ' ', aw_listing_type_name( $slug ) ) ) ) ) : $n . ' ' . aw_seo_type_plural( $slug );
			$desc = sprintf( '1' === $n ? '%1$s in %2$s, %3$s: address, phone number and services.' : '%1$s in %2$s, %3$s: addresses, phone numbers and services.', $what, $place->name, $state->name );
			$more = ' From the Animal Writes Directory of animal welfare organisations in India.';
			$desc = mb_strlen( $desc . $more ) <= 155 ? $desc . $more : ( mb_strlen( $desc . ' From the Animal Writes Directory.' ) <= 155 ? $desc . ' From the Animal Writes Directory.' : $desc );
		} elseif ( aw_current_type_name() || ! empty( $_GET['q'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$desc = $desc; // a filtered list: as before
		} elseif ( $place->parent ) {
			$desc = '1' === $n
				? sprintf( 'One animal welfare organisation listed in %1$s, %2$s, with its phone, address and services, in the Animal Writes Directory of India.', $place->name, $state->name )
				: sprintf( '%1$s animal welfare NGOs, rescue groups, shelters, Gaushalas and vets in %2$s, %3$s. Phone, address and services of each.', $n, $place->name, $state->name );
		} else {
			$desc = sprintf( '%1$s animal welfare NGOs, shelters, rescuers, Gaushalas and government vets in %2$s, district by district. Phone, address and services.', $n, $place->name );
		}
	} elseif ( isset( AW_HUB_DESCRIPTIONS[ $hub ] ) ) {
		$desc = AW_HUB_DESCRIPTIONS[ $hub ];
	} elseif ( in_array( $hub, array( 'aw_news', 'aw_legal', 'post' ), true ) ) {
		$desc = aw_seo_hub_intro_description( $hub );
	}
	if ( '' === trim( (string) $desc ) ) {
		$desc = get_bloginfo( 'description' );
	}
	$desc  = preg_replace( '/\s+,/u', ',', html_entity_decode( wp_strip_all_tags( (string) $desc ), ENT_QUOTES, 'UTF-8' ) );
	$pages = aw_seo_page_suffix();
	return aw_seo_with_page( aw_seo_cut( $desc, 155 - mb_strlen( $pages ) ) );
}

/**
 * Description of the current view (schema nodes use it): an item's Rank Math description (or what would be stored),
 * a list's built description.
 *
 * @return string
 */
function aw_seo_description() {
	if ( aw_seo_is_list_view() ) {
		return aw_seo_list_description();
	}
	$typed = aw_seo_typed( 'description' );
	return '' !== $typed ? $typed : aw_seo_item_description( get_queried_object_id() );
}

add_filter(
	'rank_math/frontend/description',
	function ( $desc ) {
		if ( is_404() || ! aw_seo_is_list_view() ) {
			return $desc; // items: Rank Math's own field
		}
		if ( '' !== aw_seo_typed( 'description' ) ) {
			return aw_seo_with_page( $desc ); // typed in Rank Math: used as is (" – Page N" on later pages)
		}
		$ours = aw_seo_list_description();
		$ours = '' !== $ours ? $ours : $desc;
		aw_seo_mirror_hub( 'description', $ours );
		return $ours;
	},
	20
);

/* ---------- /claim/?listing=ID: one indexed page per listing, with its own title (owner, 10 Oct 2026) ---------- */

add_filter(
	'rank_math/frontend/description',
	function ( $desc ) {
		$id = function_exists( 'aw_claim_listing' ) ? aw_claim_listing() : 0;
		if ( ! $id ) {
			return $desc;
		}
		$name = aw_seo_plain( get_the_title( $id ) );
		return aw_seo_cut( 'Claim ' . $name . ' in the Animal Writes Directory: send changes to its phone, WhatsApp, email, website, area, services or logo for review.', 160 );
	},
	30
);

add_filter(
	'rank_math/frontend/title',
	function ( $title ) {
		$id = function_exists( 'aw_claim_listing' ) ? aw_claim_listing() : 0;
		return $id ? 'Claim ' . html_entity_decode( get_the_title( $id ), ENT_QUOTES ) . ' – Animal Writes' : $title;
	},
	30
);

/*
 * The title separator as a character: Rank Math writes %sep% as an HTML entity (htmlentities of "–" is "&ndash;"),
 * and its <title> and share titles print that entity as is. Titles are plain text, so entities are decoded last; the
 * output is still escaped by Rank Math.
 */
add_filter( 'rank_math/frontend/title', fn( $t ) => html_entity_decode( (string) $t, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), 9999 );
add_filter( 'rank_math/opengraph/facebook/og_title', fn( $t ) => html_entity_decode( (string) $t, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), 9999 );
add_filter( 'rank_math/opengraph/twitter/twitter_title', fn( $t ) => html_entity_decode( (string) $t, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), 9999 );
