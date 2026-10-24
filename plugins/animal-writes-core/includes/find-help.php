<?php
/**
 * "Find help near you" (owner-approved plan, 24 Oct 2026; revised by the owner the same day): links from the pages
 * Google crawls every day (News, Library, guides and the lab rules campaign) into the Directory's state and district
 * pages, which Search Console showed as "Discovered – currently not indexed".
 *
 * - Place detection: a News, Library or guide item without a place gets one from the state and district names in its
 *   title and summary (LGD names and the spellings in lgd.json, a few city names). Stored like a place set by hand
 *   (aw_state_code, aw_district_code) with aw_place_auto = 1, on publish and by `wp aw-places detect` for older items.
 *   An editor changes it in the Place box; an item left with no place gets one again on its next save.
 *   Not stored: names that are also ordinary words or people's names, a district name two states share unless its
 *   state is named too, and items that name more than 3 states (national stories).
 * - In More (cross-navigation), for an item with a place: its state and that state's districts with listings, at
 *   most 12, nearest first. Without a place: one line, "Find help near you → All states and districts" and Near me.
 * - Campaign pages: a band of 12 states before the Library band, a window that moves with the campaign's ID.
 * - Only places with at least one listing (stored counts, places.php): those pages are indexed. Same HTML for every
 *   visitor; no query of its own beyond the stored counts.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Links shown at most. */
const AW_FINDHELP_MAX = 12;

/** An item that names more states than this is national: no place is detected. */
const AW_PLACE_DETECT_MAX_STATES = 3;

/** District names that are also ordinary words, people's names or rivers (keys): never detected. Delhi's districts are
 * named for directions ("Central", "North East"); the state is still found from "Delhi". */
const AW_PLACE_DETECT_SKIP = array( 'east', 'west', 'north', 'south', 'central', 'northeast', 'northwest', 'southeast', 'southwest', 'centralnorth', 'anand', 'mon', 'una', 'korea', 'sagar', 'mau', 'dhar', 'guna', 'banda', 'hassan', 'salem', 'pali', 'mandi', 'tapi', 'gomati', 'saran', 'mansa', 'nadia', 'ntr', 'sakti', 'kota', 'siang', 'lohit', 'panna', 'erode', 'noney', 'kamle', 'krishna', 'narmada', 'mahisagar', 'vaishali', 'vidisha', 'kheda' );

/** City names people write for a district (keys as aw_place_key() makes them) → the LGD district name. */
const AW_PLACE_DETECT_CITIES = array(
	'bengaluru'    => 'Bengaluru Urban',
	'noida'        => 'Gautam Buddha Nagar',
	'greaternoida' => 'Gautam Buddha Nagar',
	'navimumbai'   => 'Thane',
	'kochi'        => 'Ernakulam',
	'mangaluru'    => 'Dakshina Kannada',
	'mangalore'    => 'Dakshina Kannada',
	'banaras'      => 'Varanasi',
	'benares'      => 'Varanasi',
	'secunderabad' => 'Hyderabad',
);

/** Whether an item gets the block: News, Library, guides (not research) and campaigns. */
function aw_findhelp_applies( $id ) {
	$type = get_post_type( $id );
	if ( 'publish' !== get_post_status( $id ) ) {
		return false;
	}
	return in_array( $type, array( 'aw_news', 'aw_legal', 'aw_campaign' ), true ) || ( 'post' === $type && 'guide' === aw_item_kind( $id ) );
}

/* ---------- Place detection ---------- */

/** Name keys → places: [states by key, districts by key (a key two districts share lists both)]. */
function aw_place_detect_index() {
	static $ix = null;
	if ( null !== $ix ) {
		return $ix;
	}
	$ix = array( array(), array() );
	foreach ( aw_place_states() as $s ) {
		foreach ( array( $s->name, $s->lgd ) as $n ) {
			$ix[0][ aw_place_key( $n, 'state' ) ] = $s;
		}
	}
	foreach ( aw_lgd()['districts'] as $d ) {
		foreach ( array( $d->name, $d->lgd ) as $n ) {
			$ix[1][ aw_place_key( $n ) ][ $d->code ] = $d;
		}
	}
	foreach ( AW_PLACE_DETECT_CITIES as $k => $name ) {
		$d = aw_place_district_by_name( $name );
		if ( $d ) {
			$ix[1][ $k ] = array( $d->code => $d );
		}
	}
	return $ix;
}

/**
 * Places named in a text: [state code, district code or 0] pairs, at most AW_PLACE_DETECT_MAX_STATES, or [] when it
 * names none or more states than that. Names count only when written with a capital letter; the longest name wins
 * ("West Bengal", not "West").
 *
 * @param string $text Title and summary.
 * @return array
 */
function aw_places_in_text( $text ) {
	list( $states, $districts ) = aw_place_detect_index();
	preg_match_all( '/\p{L}+|&/u', wp_strip_all_tags( (string) $text ), $m );
	$words = $m[0];
	$n     = count( $words );
	$st    = array(); // state code => state
	$ds    = array(); // candidate lists of districts
	for ( $i = 0; $i < $n; $i++ ) {
		if ( ! preg_match( '/^\p{Lu}/u', $words[ $i ] ) ) {
			continue;
		}
		for ( $len = min( 9, $n - $i ); $len >= 1; $len-- ) {
			$phrase = implode( ' ', array_slice( $words, $i, $len ) );
			$ks     = aw_place_key( $phrase, 'state' );
			$kd     = aw_place_key( $phrase );
			if ( in_array( $kd, AW_PLACE_DETECT_SKIP, true ) ) {
				continue;
			}
			if ( isset( $districts[ $kd ] ) ) { // a district first: Chandigarh and Puducherry are both
				$ds[] = $districts[ $kd ];
			} elseif ( isset( $states[ $ks ] ) ) {
				$st[ $states[ $ks ]->code ] = $states[ $ks ];
			} else {
				continue;
			}
			$i += $len - 1;
			break;
		}
	}
	$out = array();
	foreach ( $ds as $cands ) {
		if ( count( $cands ) > 1 ) { // a name two states share: only with its state named too
			$cands = array_filter( $cands, fn( $d ) => isset( $st[ $d->state ] ) );
		}
		if ( 1 === count( $cands ) ) {
			$d                     = reset( $cands );
			$out[ 'd' . $d->code ] = array( $d->state, $d->code );
		}
	}
	foreach ( $st as $code => $s ) {
		if ( ! array_filter( $out, fn( $p ) => (int) $p[0] === (int) $code ) ) {
			$out[ 's' . $code ] = array( $code, 0 );
		}
	}
	$named = array_unique( array_map( fn( $p ) => (int) $p[0], $out ) );
	return count( $named ) > AW_PLACE_DETECT_MAX_STATES ? array() : array_values( $out );
}

/** Whether an item should get a detected place: a News, Library or guide item with no place. */
function aw_place_detect_wanted( $id ) {
	$type = get_post_type( $id );
	if ( ! in_array( $type, array( 'aw_news', 'aw_legal', 'post' ), true ) || ( 'post' === $type && 'guide' !== aw_item_kind( $id ) ) ) {
		return false;
	}
	list( $s, $d ) = aw_item_codes( $id );
	return ! $s && ! $d; // not "removed by an editor": the block editor's Place box posts the empty place it loaded with
}

/**
 * Detect and store an item's place. Returns the places found ([] when none).
 *
 * @param int  $id    Item.
 * @param bool $write Store them (false: only report).
 * @return array
 */
function aw_place_detect( $id, $write = true ) {
	$places = aw_places_in_text( get_the_title( $id ) . ' ' . get_post_field( 'post_excerpt', $id ) );
	if ( $places && $write && aw_set_item_places( $id, $places ) ) {
		update_post_meta( $id, 'aw_place_auto', '1' );
	}
	return $places;
}

// On publish and save: a new item gets its place (after the Place box has saved what an editor chose).
add_action(
	'save_post',
	function ( $id, $post ) {
		if ( 'publish' !== $post->post_status || wp_is_post_revision( $id ) || wp_is_post_autosave( $id ) || ! aw_place_detect_wanted( $id ) ) {
			return;
		}
		aw_place_detect( $id );
	},
	30,
	2
);

/* ---------- Links ---------- */

/**
 * The places to link for an item with a place: its state, then the state's districts with listings, nearest first
 * (to the item's district; by number of listings when the item names only the state). [place, count] pairs.
 *
 * @param int         $id    Item.
 * @param object|null $place Its place (default: the stored one).
 * @return array
 */
function aw_findhelp_places( $id, $place = null ) {
	$place = $place ? $place : aw_item_place( $id );
	$state = $place ? aw_place_parent_state( $place ) : null;
	if ( ! $state || aw_place_count( $state ) < 1 ) {
		return array();
	}
	$out = array( array( $state, aw_place_count( $state ) ) );
	$ds  = array_filter( aw_place_districts( $state->code ), fn( $d ) => aw_place_count( $d ) > 0 );
	if ( 'district' === $place->kind ) {
		$km = array();
		foreach ( $ds as $code => $d ) {
			$km[ $code ] = $code === $place->code ? -1 : ( aw_district_km( $place->code, $code ) ?? PHP_INT_MAX );
		}
		asort( $km );
		$ds = array_map( fn( $c ) => $ds[ $c ], array_keys( $km ) );
	} else {
		uasort( $ds, fn( $a, $b ) => aw_place_count( $b ) <=> aw_place_count( $a ) );
	}
	foreach ( array_slice( $ds, 0, AW_FINDHELP_MAX - 1 ) as $d ) {
		$out[] = array( $d, aw_place_count( $d ) );
	}
	return $out;
}

/** A campaign's 12 states: a window over the states with listings that moves with its ID, A–Z. */
function aw_findhelp_states_window( $id ) {
	$states = array_values( array_filter( aw_place_states(), fn( $s ) => aw_place_count( $s ) > 0 ) ); // A–Z
	$n      = count( $states );
	if ( $n > AW_FINDHELP_MAX ) {
		$start  = ( (int) $id * 7 ) % $n;
		$states = array_map( fn( $i ) => $states[ ( $start + $i ) % $n ], range( 0, AW_FINDHELP_MAX - 1 ) );
		usort( $states, fn( $a, $b ) => strcmp( $a->name, $b->name ) );
	}
	return array_map( fn( $s ) => array( $s, aw_place_count( $s ) ), $states );
}

/**
 * The list of links (name and count, the Browse by state list's look), or the one line when there are none.
 *
 * @param int    $id     Item.
 * @param array  $places [place, count] pairs.
 * @param string $head   Heading classes.
 * @return string
 */
function aw_findhelp_html( $id, $places, $head ) {
	$hid = 'aw-findhelp-' . (int) $id;
	if ( ! $places ) {
		return '<p class="aw-findhelp-line"><strong>' . esc_html__( 'Find help near you', 'animal-writes-core' ) . '</strong>'
			. '<a href="' . esc_url( home_url( '/directory/' ) ) . '">' . esc_html__( 'All states and districts', 'animal-writes-core' ) . '</a>'
			. '<a href="' . esc_url( home_url( '/directory/#near-me' ) ) . '">' . aw_i( 'map-pin' ) . esc_html__( 'Near me', 'animal-writes-core' ) . '</a></p>';
	}
	$items = '';
	foreach ( $places as list( $p, $n ) ) {
		$label  = 'district' === $p->kind ? $p->name : sprintf( /* translators: %s: state. */ __( 'All of %s', 'animal-writes-core' ), $p->name );
		$items .= '<li><a href="' . esc_url( aw_directory_url( $p->slug ) ) . '"><span>' . esc_html( $label ) . '</span>' . kits_count( $n ) . '</a></li>';
	}
	return '<nav class="aw-browse-states aw-findhelp" aria-labelledby="' . esc_attr( $hid ) . '"><h2 class="' . esc_attr( $head ) . '" id="' . esc_attr( $hid ) . '">' . esc_html__( 'Find help near you', 'animal-writes-core' ) . '</h2>'
		. '<p class="aw-findhelp-lede">' . esc_html__( 'Animal welfare organisations, Gaushalas and government vets, with their contact details.', 'animal-writes-core' ) . '</p>'
		. '<ul>' . $items . '</ul>'
		. '<p class="kit-more"><a href="' . esc_url( home_url( '/directory/' ) ) . '">' . esc_html__( 'All states and districts', 'animal-writes-core' ) . '</a></p></nav>';
}

// Item pages: the last part of More.
add_filter(
	'render_block_animal-writes/item-more',
	function ( $html, $block, $instance ) {
		$id = (int) ( $instance->context['postId'] ?? get_queried_object_id() );
		if ( ! aw_item_here( $id ) || 'aw_campaign' === get_post_type( $id ) || ! aw_findhelp_applies( $id ) ) {
			return $html;
		}
		$near = '<div class="aw-findhelp-group">' . aw_findhelp_html( $id, aw_findhelp_places( $id ), 'aw-more-head has-x-large-font-size' ) . '</div>';
		$end  = strrpos( $html, '</div>' );
		return false !== $end && 0 === strpos( ltrim( $html ), '<div class="aw-more">' ) ? substr_replace( $html, $near, $end, 0 ) : $html . '<div class="aw-more">' . $near . '</div>';
	},
	10,
	3
);

// Campaign pages: a band of its own before the Library band (once).
add_filter(
	'render_block_core/group',
	function ( $html, $block ) {
		static $done = false;
		$class = (string) ( $block['attrs']['className'] ?? '' );
		if ( $done || false === strpos( $class, 'aw-camp-slot--library' ) || ! is_singular( 'aw_campaign' ) ) {
			return $html;
		}
		$id = get_queried_object_id();
		if ( ! aw_findhelp_applies( $id ) ) {
			return $html;
		}
		$done   = true;
		$states = aw_findhelp_states_window( $id );
		return $states ? '<section class="wp-block-group alignfull aw-camp-section aw-camp-tint aw-findhelp-band has-global-padding is-layout-constrained" id="find-help" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">' . aw_findhelp_html( $id, $states, 'aw-findhelp-head' ) . '</section>' . $html : $html;
	},
	10,
	2
);

/* ---------- WP-CLI: places for older items ---------- */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Detect places for published News, Library and guides that have none: `wp aw-places detect [--dry-run]
	 * [--type=aw_news] [--samples=10]`. Prints counts per type and samples.
	 */
	WP_CLI::add_command(
		'aw-places detect',
		function ( $args, $assoc ) {
			$dry     = ! empty( $assoc['dry-run'] );
			$types   = ! empty( $assoc['type'] ) ? explode( ',', $assoc['type'] ) : array( 'aw_news', 'aw_legal', 'post' );
			$samples = array();
			$want    = (int) ( $assoc['samples'] ?? 10 );
			foreach ( $types as $t ) {
				$c = array( 'items' => 0, 'had' => 0, 'found' => 0, 'district' => 0, 'states' => array() );
				foreach ( get_posts( array( 'post_type' => $t, 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'DESC' ) ) as $id ) {
					if ( 'post' === $t && 'guide' !== aw_item_kind( $id ) ) {
						continue;
					}
					++$c['items'];
					if ( ! aw_place_detect_wanted( $id ) ) {
						++$c['had'];
						continue;
					}
					$found = aw_place_detect( $id, ! $dry );
					if ( ! $found ) {
						continue;
					}
					++$c['found'];
					$c['district'] += array_filter( $found, fn( $p ) => $p[1] ) ? 1 : 0;
					foreach ( $found as $p ) {
						$c['states'][ $p[0] ] = 1;
					}
					$samples[ $t ][] = $id;
				}
				WP_CLI::log( sprintf( '%s: %d items, %d already had a place, %d %s a place (%d with a district), %d states', $t, $c['items'], $c['had'], $c['found'], $dry ? 'would get' : 'got', $c['district'], count( $c['states'] ) ) );
			}
			foreach ( $samples as $t => $ids ) {
				foreach ( array_slice( $ids, 0, $want ) as $id ) {
					$names = array_map( fn( $p ) => $p[1] ? aw_place_district( $p[1] )->name . ', ' . aw_place_state( $p[0] )->name : aw_place_state( $p[0] )->name, aw_places_in_text( get_the_title( $id ) . ' ' . get_post_field( 'post_excerpt', $id ) ) );
					WP_CLI::log( sprintf( '  %s %d  %s  →  %s', $t, $id, mb_substr( get_the_title( $id ), 0, 90 ), implode( '; ', $names ) ) );
				}
			}
			WP_CLI::success( $dry ? 'Dry run: nothing stored.' : 'Stored.' );
		}
	);
}
