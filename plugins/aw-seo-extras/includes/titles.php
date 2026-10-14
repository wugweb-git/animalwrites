<?php
/**
 * Search titles and meta descriptions.
 *
 * Items (news, Library, Resources, listings, opportunities, campaigns, press, Pages): the title and description are
 * stored in Rank Math's own fields (rank_math_title, rank_math_description), so the Rank Math box shows what is printed.
 * They are written when an item is published or saved, and only where the field is empty or still holds the value
 * written automatically last time (_aw_seo_auto); a value typed in Rank Math is never replaced.
 *   - News, Library and Resources: over 60 characters the headline is cut at a word (no "…", no site name); shorter
 *     ones get " – Animal Writes".
 *   - Listings: "{Name}, {District} – Animal Writes" ("{Name}, {State}" without a district).
 *   - Home and About: fixed titles. Everything else: "{Title} – Animal Writes".
 *   - Description: the excerpt, else the first real paragraph; listings without one get a line of their facts.
 *
 * Lists (section hub Pages, Directory places and type pages, filtered lists, archives) change with their contents and
 * page number, which Rank Math's templates cannot express: they are built here at run time, through Rank Math's
 * title and description filters. A title or description typed in Rank Math for a hub Page still wins.
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
	if ( in_array( $post->post_type, array( 'aw_news', 'aw_legal', 'post' ), true ) ) {
		return aw_seo_headline_title( $post->post_title );
	}
	if ( 'aw_listing' === $post->post_type ) {
		return aw_seo_branded( aw_seo_listing_name( $post->ID ) );
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
		$ids = get_posts(
			array(
				'post_type'      => AW_SEO_ITEM_TYPES,
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'fields'         => 'ids',
				'meta_query'     => array( array( 'key' => 'rank_math_title', 'compare' => 'NOT EXISTS' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
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
		if ( aw_current_type_name() || ! empty( $_GET['q'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return ''; // type pages and filtered lists keep their own titles
		}
		if ( ! $place ) {
			return AW_HUB_TITLES['aw_listing'];
		}
		if ( $place->parent ) {
			return sprintf( 'Animal Welfare NGOs in %1$s, %2$s: Rescue, Shelters, Vets', $place->name, aw_place_parent_state( $place )->name );
		}
		return sprintf( 'Animal Welfare NGOs & Animal Shelters in %s – Animal Writes', $place->name );
	}
	return AW_HUB_TITLES[ $hub ] ?? '';
}

/** True while the WordPress document title of a list is being built (Rank Math's title filter then steps aside). */
function aw_seo_building_title( $set = null ) {
	static $busy = false;
	if ( null !== $set ) {
		$busy = (bool) $set;
	}
	return $busy;
}

// While building, the document title comes from WordPress's own parts (the Directory type pages set theirs).
add_filter( 'pre_get_document_title', fn( $t ) => aw_seo_building_title() ? '' : $t, 16 );
add_filter(
	'pre_get_document_title',
	function ( $title ) {
		if ( ! aw_seo_building_title() ) {
			return $title;
		}
		$ours = aw_seo_list_title();
		return '' !== $ours ? aw_seo_with_page( $ours ) : $title;
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
			return aw_seo_with_page( $title ); // typed in Rank Math: used as is (" – Page N" on later pages)
		}
		aw_seo_building_title( true );
		$ours = wp_get_document_title();
		aw_seo_building_title( false );
		$ours = trim( html_entity_decode( wp_strip_all_tags( $ours ), ENT_QUOTES, 'UTF-8' ) );
		return '' !== $ours ? $ours : $title;
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
	} elseif ( is_tax() || is_category() || is_tag() ) {
		$desc = term_description() ?: single_term_title( '', false ) . ' on ' . get_bloginfo( 'name' ) . '.';
	}
	if ( function_exists( 'aw_is_directory_view' ) && aw_is_directory_view() && ! get_query_var( 'aw_dir_type' ) && empty( $_GET['type'] ) && empty( $_GET['q'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$desc = aw_directory_intro();
	}
	if ( 'aw_listing' === $hub && ! is_404() ) {
		$place = aw_current_place();
		$slug  = (string) get_query_var( 'aw_type_slug' );
		$n     = number_format_i18n( aw_hub_total( 'aw_listing' ) );
		$state = $place ? aw_place_parent_state( $place ) : null;
		if ( ! $place ) {
			$desc = ( aw_current_type_name() || ! empty( $_GET['q'] ) ) ? $desc : AW_HUB_DESCRIPTIONS['aw_listing']; // phpcs:ignore WordPress.Security.NonceVerification
		} elseif ( $place->parent && '' !== $slug && '0' !== $n ) {
			$desc = sprintf( '%1$s %2$s in %3$s, %4$s: addresses, contact details and services.', $n, aw_seo_type_plural( $slug ), $place->name, $state->name );
		} elseif ( aw_current_type_name() || ! empty( $_GET['q'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$desc = $desc; // a filtered list: as before
		} elseif ( $place->parent ) {
			$desc = sprintf( '%1$s animal rescue groups, shelters, gaushalas and vets in %2$s, %3$s. Phone, address and services.', $n, $place->name, $state->name );
		} else {
			$desc = sprintf( '%1$s animal welfare NGOs, shelters, rescuers, gaushalas and government vets in %2$s, district by district. Phone, address and services.', $n, $place->name );
		}
	} elseif ( isset( AW_HUB_DESCRIPTIONS[ $hub ] ) ) {
		$desc = AW_HUB_DESCRIPTIONS[ $hub ];
	} elseif ( in_array( $hub, array( 'aw_news', 'aw_legal' ), true ) ) {
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
		return '' !== $ours ? $ours : $desc;
	},
	20
);

/* ---------- /claim/?listing=ID: one indexed page per listing, with its own title (owner, 10 Oct 2026) ---------- */

add_filter(
	'rank_math/frontend/title',
	function ( $title ) {
		$id = function_exists( 'aw_claim_listing' ) ? aw_claim_listing() : 0;
		return $id ? 'Claim ' . html_entity_decode( get_the_title( $id ), ENT_QUOTES ) . ' – Animal Writes' : $title;
	},
	30
);
