<?php
/**
 * Labels are WordPress's own Tags (owner, 15 Oct 2026): Library types, Resource types, Opportunity types, Listing types
 * and Services are tags (post_tag) on every section, at /tag/{slug}/, filtered on the hubs by ?tag=.
 *
 * Which tags (and categories) a section uses is a Sourcing setting (Settings → Sourcing → Sections), not a WordPress
 * list: aw_section_tags( 'aw_listing' ) are the Directory's tags, and so on. Listing types are the Directory's tags
 * that are not services.
 *
 * Old addresses keep working with one 301: /directory-type/{slug}/ and /opportunity-type/{slug}/ → /tag/{slug}/;
 * ?kind=, ?type= and ?service= on a list → ?tag=; ?category= → ?category_name=; /?cat=N of an old Resource type →
 * /resources/?tag={slug}.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tags that have their own icon (aw-type-ico--{slug} in the theme's aw.css; Library types use lib-{slug}). Every other
 * tag shows the default icon and is listed on the Tags screen as "Tags without their own icon" (owner, 20 Oct 2026).
 * Services share their organisation type's icon (aw.css): Sheltering = Animal shelter, Ambulance service = Animal ambulance ...
 */
const AW_TAG_ICONS = array(
	// Directory: organisation types.
	'abc-centre', 'adoption-network', 'animal-ambulance', 'animal-shelter', 'animal-welfare-organisation', 'bird-hospital', 'boarding-and-foster', 'emergency-rescue', 'feeder-network', 'gaushala', 'legal-aid', 'mobile-veterinary-unit', 'rescue-group', 'sanctuary', 'veterinary-clinic', 'veterinary-dispensary', 'veterinary-hospital', 'veterinary-office', 'wildlife-rescue',
	// Directory: services.
	'rescue', 'treatment', 'shelter', 'adoption', 'feeding', 'sterilisation', 'vaccination', 'ambulance', 'boarding-foster', 'legal-help', 'cattle-care',
	// Resources, Opportunities, Library.
	'research', 'guide', 'faq', 'how-to', 'job', 'volunteer', 'event', 'internship', 'fellowship', 'grant', 'draft',
);

/* ---------- Registration ---------- */

add_action(
	'init',
	function () {
		foreach ( array_keys( aw_post_types() ) as $type ) {
			register_taxonomy_for_object_type( 'post_tag', $type );
		}
	},
	21
);

add_filter(
	'register_taxonomy_args',
	function ( $args, $tax ) {
		if ( 'post_tag' !== $tax ) {
			return $args;
		}
		$args['rewrite']           = array( 'slug' => 'tag', 'with_front' => false, 'ep_mask' => EP_TAGS );
		$args['show_admin_column'] = true;
		return $args;
	},
	10,
	2
);

/** A tag page lists every section's items (WordPress lists only Posts unless told). */
add_action(
	'pre_get_posts',
	function ( $q ) {
		if ( ! is_admin() && $q->is_main_query() && $q->is_tag() && ! $q->get( 'post_type' ) ) {
			$q->set( 'post_type', array_keys( aw_post_types() ) );
		}
	}
);

/* ---------- Sections: which categories and tags each one uses (Sourcing) ---------- */

/** Every section's lists: type => [ 'categories' => slugs, 'tags' => slugs ]. */
function aw_section_lists() {
	$c = function_exists( 'aw_sources_config' ) ? aw_sources_config() : array();
	return (array) ( $c['sections'] ?? array() );
}

/** Tag slugs a section uses. */
function aw_section_tags( $type ) {
	return array_values( (array) ( aw_section_lists()[ $type ]['tags'] ?? array() ) );
}

/** Category slugs a section uses (every category when the list is empty). */
function aw_section_categories( $type ) {
	$cats = array_values( (array) ( aw_section_lists()[ $type ]['categories'] ?? array() ) );
	return $cats ? $cats : get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false, 'fields' => 'slugs' ) );
}

/** Service slugs (the fixed list; "wildlife-rescue" is also a listing type, and counts as one). */
function aw_service_slugs() {
	return array_values( array_diff( array_keys( AW_SERVICES ), array( 'wildlife-rescue' ) ) );
}

/** Listing-type slugs: the Directory's tags that are not services. */
function aw_listing_type_slugs() {
	return array_values( array_diff( aw_section_tags( 'aw_listing' ), aw_service_slugs() ) );
}

/** The section a tag belongs to (the first that uses it), or ''. */
function aw_tag_section( $slug ) {
	foreach ( aw_section_lists() as $type => $l ) {
		if ( in_array( $slug, (array) ( $l['tags'] ?? array() ), true ) ) {
			return $type;
		}
	}
	return '';
}

/**
 * An item's tags from one list.
 *
 * @param int      $id    Post ID.
 * @param string[] $slugs Allowed slugs ('' = the item's section).
 * @return WP_Term[]
 */
function aw_item_tags( $id, $slugs = null ) {
	$slugs = null === $slugs ? aw_section_tags( (string) get_post_type( $id ) ) : $slugs;
	$terms = get_the_terms( $id, 'post_tag' );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return array();
	}
	return array_values( array_filter( $terms, fn( $t ) => in_array( $t->slug, $slugs, true ) ) );
}

/** A Library item's type tags, from the fixed list (AW_LEGAL_KINDS). Other Library labels, such as a campaign tag, are not types. */
function aw_legal_kinds( $id ) {
	return aw_item_tags( $id, array_keys( AW_LEGAL_KINDS ) );
}

/** A listing's types (tags that are listing types). */
function aw_listing_types( $id ) {
	return aw_item_tags( $id, aw_listing_type_slugs() );
}

/** A listing's services (names). */
function aw_listing_service_names( $id ) {
	return wp_list_pluck( aw_item_tags( $id, aw_service_slugs() ), 'name' );
}

/** Replace an item's tags from one list, keeping its other tags. */
function aw_set_item_tags( $id, array $new, array $list ) {
	$keep = array_diff( wp_get_object_terms( $id, 'post_tag', array( 'fields' => 'slugs' ) ), $list );
	wp_set_object_terms( $id, array_values( array_unique( array_merge( $keep, array_intersect( $new, $list ) ) ) ), 'post_tag' );
}

/** True when a tag has its own icon (not the default). */
function aw_tag_has_icon( $slug ) {
	return ( defined( 'AW_LEGAL_KINDS' ) && isset( AW_LEGAL_KINDS[ $slug ] ) ) || in_array( $slug, AW_TAG_ICONS, true );
}

/** A tag's icon: its own, else the default (a tag added later never shows without one). */
function aw_tag_icon( $slug ) {
	if ( ! function_exists( 'aw_type_icon' ) ) {
		return '';
	}
	if ( defined( 'AW_LEGAL_KINDS' ) && isset( AW_LEGAL_KINDS[ $slug ] ) ) {
		return aw_type_icon( 'lib-' . $slug );
	}
	return aw_type_icon( in_array( $slug, AW_TAG_ICONS, true ) ? $slug : 'default' );
}

// Tag pages: the heading carries the tag's icon.
add_filter(
	'render_block_core/query-title',
	function ( $html ) {
		if ( ! is_tag() || ! ( get_queried_object() instanceof WP_Term ) ) {
			return $html;
		}
		return preg_replace( '#(<h1\b[^>]*>)#', '$1' . aw_tag_icon( get_queried_object()->slug ), $html, 1 );
	}
);

// Settings: the Tags screen lists the tags still on the default icon.
add_action(
	'admin_notices',
	function () {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-post_tag' !== $screen->id || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$missing = array();
		foreach ( get_terms( array( 'taxonomy' => 'post_tag', 'hide_empty' => false ) ) as $t ) {
			if ( ! aw_tag_has_icon( $t->slug ) ) {
				$missing[] = $t->name . ' (' . $t->slug . ')';
			}
		}
		echo '<div class="notice ' . ( $missing ? 'notice-warning' : 'notice-success' ) . '"><p><strong>Tags without their own icon:</strong> ' . ( $missing ? esc_html( implode( ', ', $missing ) ) . '. They show the default icon until one is added in the theme (aw.css) and in AW_TAG_ICONS (tags.php).' : 'none. Every tag has its own icon.' ) . '</p></div>';
	}
);

// Tag labels (the core post-terms block on item pages and cards) carry the tag's icon before the name.
add_filter(
	'render_block_core/post-terms',
	function ( $html, $block, $instance ) {
		if ( 'post_tag' !== ( $block['attrs']['term'] ?? '' ) || '' === $html ) {
			return $html;
		}
		$id    = (int) ( $instance->context['postId'] ?? get_the_ID() );
		$terms = get_the_terms( $id, 'post_tag' );
		foreach ( ( $terms && ! is_wp_error( $terms ) ) ? $terms : array() as $t ) {
			if ( 'aw_listing' === get_post_type( $id ) && in_array( $t->slug, aw_service_slugs(), true ) ) { // services are listed with the details, not under the name
				$html = preg_replace( '#(<span class="wp-block-post-terms__separator">[^<]*</span>)?<a\b[^>]*>' . preg_quote( esc_html( $t->name ), '#' ) . '</a>#u', '', $html, 1 );
				continue;
			}
			$ico = aw_tag_icon( $t->slug );
			if ( '' !== $ico ) {
				$html = preg_replace( '#(<a\b[^>]*>)(' . preg_quote( esc_html( $t->name ), '#' ) . '</a>)#u', '$1' . $ico . '$2', $html, 1 );
			}
		}
		return $html;
	},
	5, // before the breadcrumb trail is put in front (breadcrumbs.php)
	3
);

// Directory listings in a tag page's list carry no date (the date they were added means nothing to a visitor).
add_filter(
	'render_block_core/post-date',
	function ( $html, $block, $instance ) {
		$id = (int) ( $instance->context['postId'] ?? 0 );
		return ( $id && ! is_singular() && 'aw_listing' === get_post_type( $id ) ) ? '' : $html;
	},
	20,
	3
);

/* ---------- Old addresses ---------- */

add_action(
	'parse_request',
	function () {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! in_array( $_SERVER['REQUEST_METHOD'] ?? 'GET', array( 'GET', 'HEAD' ), true ) ) {
			return;
		}
		$uri  = (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		parse_str( (string) wp_parse_url( $uri, PHP_URL_QUERY ), $args );
		$to = '';
		if ( preg_match( '#^/(?:directory-type|opportunity-type)/([^/]+)(/.*)?$#', $path, $m ) ) {
			$to = '/tag/' . $m[1] . '/' . ltrim( (string) ( $m[2] ?? '' ), '/' );
		}
		if ( isset( $args['category'] ) && ! isset( $args['category_name'] ) ) { // the short-lived ?category= of 15 Oct 2026
			$args['category_name'] = $args['category'];
			unset( $args['category'] );
			$to = '' !== $to ? $to : $path;
		}
		if ( isset( $args['cat'] ) && is_numeric( $args['cat'] ) ) {
			$t = get_term( (int) $args['cat'] );
			if ( $t instanceof WP_Term && 'post_tag' === $t->taxonomy ) {
				unset( $args['cat'] );
				$to   = '/resources/';
				$args = array( 'tag' => $t->slug ) + $args;
			}
		}
		// ?kind= (Library, Resources), ?type= (Opportunities, Directory) and ?service= (Directory) are ?tag= now.
		$old = array_values( array_filter( array( 'kind', 'type', 'service' ), fn( $k ) => isset( $args[ $k ] ) && '' !== $args[ $k ] && ! is_array( $args[ $k ] ) ) );
		$hub = (bool) preg_match( '#^/(news|library|resources|opportunities|campaigns|directory)(/|$)#', $path );
		if ( $old && $hub ) {
			$tags = array_filter( explode( ',', (string) ( $args['tag'] ?? '' ) ) );
			foreach ( $old as $k ) {
				$tags = array_merge( $tags, explode( ',', (string) $args[ $k ] ) );
				unset( $args[ $k ] );
			}
			$args['tag'] = implode( ',', array_unique( array_map( 'sanitize_title', $tags ) ) );
			$to          = '' !== $to ? $to : $path;
		}
		if ( '' === $to ) {
			return;
		}
		$q = $args ? '?' . str_replace( array( '%2C', '%5B', '%5D' ), array( ',', '[', ']' ), http_build_query( $args ) ) : '';
		wp_safe_redirect( home_url( $to ) . $q, 301, 'Animal Writes' );
		exit;
	},
	1
);
