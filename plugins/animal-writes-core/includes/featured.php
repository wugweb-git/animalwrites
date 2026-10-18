<?php
/**
 * "Featured in" (owner, 18 Oct 2026): the news, Library items, resources, press coverage and campaigns an organisation
 * appears in, linked from its Directory page by title, and both ways in the schema (the organisation's subjectOf, the
 * item's mentions).
 *
 * - By hand: "Organisations featured" (aw_rel_org) in an item's Related box (model.php).
 * - Found automatically: when an item is saved, the organisations whose name appears in its title, summary or text
 *   (aw_rel_org_auto). Only names that pick out one organisation count: at least two words and 12 letters, not shared
 *   by another listing, and not a generic name ("People for Animals", "Gaushala Samiti" …). An editor can untick a
 *   wrong one (aw_rel_org_not); it is not found again.
 * - Backfill: `wp aw-featured scan [--type=aw_news]`.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Item types an organisation can be featured in, with the label shown beside the link. */
const AW_FEATURED_TYPES = array( 'aw_news' => 'News', 'aw_legal' => 'Library', 'post' => 'Resource', 'aw_press' => 'Press', 'aw_campaign' => 'Campaign' );

/** Names too common to mean one organisation, after the words every NGO name shares are taken out. */
const AW_FEATURED_GENERIC = array( 'people for animals', 'animal welfare society', 'animal welfare organisation', 'animal welfare trust', 'animal care society', 'animal care trust', 'animal rescue trust', 'animal rescue', 'blue cross society', 'gaushala samiti', 'shri gaushala', 'shri gaushala samiti', 'gau seva samiti', 'gau sewa samiti', 'jeev daya', 'jivdaya trust', 'panjrapole', 'shri panjrapole', 'animal welfare board', 'animal welfare board of india', 'society for prevention of cruelty to animals', 'spca', 'district spca' );

/** Words common to animal-welfare names: a name of four words or fewer made only of these is an ordinary phrase. */
const AW_FEATURED_COMMON = array( 'a', 'an', 'the', 'of', 'for', 'and', 'in', 'to', 'india', 'indian', 'animal', 'animals', 'welfare', 'care', 'rescue', 'trust', 'foundation', 'society', 'organisation', 'organization', 'centre', 'center', 'rights', 'programme', 'program', 'birth', 'control', 'conservation', 'initiative', 'initiatives', 'stray', 'strays', 'dog', 'dogs', 'cat', 'cats', 'cow', 'cows', 'cattle', 'gau', 'gou', 'go', 'gaushala', 'goshala', 'seva', 'sewa', 'samiti', 'sansthan', 'sanstha', 'people', 'protection', 'prevention', 'cruelty', 'home', 'shelter', 'sanctuary', 'hospital', 'clinic', 'service', 'services', 'help', 'friends', 'lovers', 'association', 'group', 'club', 'charitable', 'mission', 'network', 'wildlife', 'nature', 'environment', 'development', 'education', 'research', 'voice', 'life', 'love', 'hope', 'kindness', 'compassion', 'humane', 'international', 'national', 'mandal', 'sangh', 'sabha', 'nyas', 'kendra', 'shri', 'shree', 'sri', 'jeev', 'jiv', 'daya', 'pashu', 'pakshi', 'bird', 'birds', 'pfa', 'spca', 'aid', 'unit', 'district', 'state', 'board', 'council', 'committee', 'federation', 'world', 'global', 'save', 'our', 'paws', 'tails', 'street', 'community', 'feeding', 'pinjrapole', 'panjrapole', 'pinjrapol', 'panjarapole', 'sadan', 'ashram', 'squad', 'karma' );

/**
 * Distinctive organisation names: lowercased name => listing ID. Cached until a listing is saved.
 *
 * @return array
 */
function aw_featured_names() {
	$names = get_transient( 'aw_featured_names' );
	if ( is_array( $names ) ) {
		return $names;
	}
	global $wpdb;
	$rows  = $wpdb->get_results( "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'aw_listing' AND post_status = 'publish'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$count = array();
	$by    = array();
	foreach ( $rows as $r ) {
		$n = mb_strtolower( trim( preg_replace( '/\s+/u', ' ', preg_replace( '/\s*\([^)]*\)\s*$/u', '', html_entity_decode( $r->post_title, ENT_QUOTES ) ) ) ) );
		$n = trim( preg_replace( '/\s*[–-]\s*$/u', '', $n ), " ,.-–" );
		if ( '' === $n ) {
			continue;
		}
		$count[ $n ] = ( $count[ $n ] ?? 0 ) + 1;
		$by[ $n ]    = (int) $r->ID;
	}
	$names = array();
	foreach ( $by as $n => $id ) {
		if ( 1 !== $count[ $n ] || mb_strlen( $n ) < 12 || str_word_count( $n ) < 2 || in_array( $n, AW_FEATURED_GENERIC, true ) ) {
			continue;
		}
		$words = preg_split( '/[^\p{L}\p{N}]+/u', $n, -1, PREG_SPLIT_NO_EMPTY );
		if ( count( $words ) <= 4 && ! array_diff( $words, AW_FEATURED_COMMON ) ) {
			continue; // e.g. "Animal Birth Control Programme", "Care for Animals": an ordinary phrase, not one organisation
		}
		$names[ $n ] = $id;
	}
	set_transient( 'aw_featured_names', $names, DAY_IN_SECONDS );
	return $names;
}
add_action( 'save_post_aw_listing', fn() => delete_transient( 'aw_featured_names' ) );

/**
 * Find the organisations an item names and store them (aw_rel_org_auto); the ones an editor unticked stay out.
 *
 * @param int $id Item.
 * @return int[] Listing IDs found.
 */
function aw_featured_scan( $id ) {
	$type = get_post_type( $id );
	if ( ! isset( AW_FEATURED_TYPES[ $type ] ) ) {
		return array();
	}
	$text = mb_strtolower( wp_strip_all_tags( get_post_field( 'post_title', $id ) . ' ' . get_post_field( 'post_excerpt', $id ) . ' ' . get_post_field( 'post_content', $id ) ) );
	$text = ' ' . preg_replace( '/[^\p{L}\p{N}&]+/u', ' ', html_entity_decode( $text, ENT_QUOTES ) ) . ' ';
	$not  = array_map( 'intval', (array) get_post_meta( $id, 'aw_rel_org_not', false ) );
	$hits = array();
	foreach ( aw_featured_names() as $name => $lid ) {
		$needle = ' ' . preg_replace( '/[^\p{L}\p{N}&]+/u', ' ', $name ) . ' ';
		if ( false !== strpos( $text, $needle ) && ! in_array( $lid, $not, true ) ) {
			$hits[] = $lid;
		}
	}
	$hits = array_values( array_unique( $hits ) );
	delete_post_meta( $id, 'aw_rel_org_auto' );
	foreach ( $hits as $lid ) {
		add_post_meta( $id, 'aw_rel_org_auto', $lid );
	}
	return $hits;
}

add_action(
	'save_post',
	function ( $id, $post ) {
		if ( wp_is_post_revision( $id ) || ! isset( AW_FEATURED_TYPES[ $post->post_type ] ) || 'auto-draft' === $post->post_status ) {
			return;
		}
		if ( isset( $_POST['_aw_model'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_aw_model'] ) ), 'aw_model_' . $id ) && current_user_can( 'edit_post', $id ) ) {
			$keep = array_map( 'absint', (array) wp_unslash( $_POST['aw_rel_org_auto_keep'] ?? array() ) ); // ticked ones stay
			foreach ( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['aw_rel_org_auto_shown'] ?? '' ) ) ) ) as $lid ) {
				if ( $lid && ! in_array( $lid, $keep, true ) && ! in_array( $lid, array_map( 'intval', (array) get_post_meta( $id, 'aw_rel_org_not', false ) ), true ) ) {
					add_post_meta( $id, 'aw_rel_org_not', $lid ); // unticked: not this organisation
				}
			}
		}
		aw_featured_scan( $id );
	},
	20,
	2
);

/**
 * The organisations an item features (by hand first, then found), without the unticked ones.
 *
 * @param int $id Item.
 * @return int[]
 */
function aw_featured_orgs( $id ) {
	$not = array_map( 'intval', (array) get_post_meta( $id, 'aw_rel_org_not', false ) );
	$ids = array_merge( array_map( 'intval', (array) get_post_meta( $id, 'aw_rel_org', false ) ), array_map( 'intval', (array) get_post_meta( $id, 'aw_rel_org_auto', false ) ) );
	return array_values( array_filter( array_unique( $ids ), fn( $l ) => $l && ! in_array( $l, $not, true ) && 'publish' === get_post_status( $l ) ) );
}

/**
 * The items an organisation is featured in, newest first.
 *
 * @param int $lid Listing.
 * @param int $max How many.
 * @return int[]
 */
function aw_featured_items( $lid, $max = 30 ) {
	$ids = get_posts(
		array(
			'post_type'   => array_keys( AW_FEATURED_TYPES ),
			'post_status' => 'publish',
			'numberposts' => $max * 2,
			'fields'      => 'ids',
			'orderby'     => 'date',
			'order'       => 'DESC',
			'meta_query'  => array( 'relation' => 'OR', array( 'key' => 'aw_rel_org', 'value' => (string) $lid ), array( 'key' => 'aw_rel_org_auto', 'value' => (string) $lid ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	$ids = array_values( array_filter( $ids, fn( $i ) => ! in_array( (int) $lid, array_map( 'intval', (array) get_post_meta( $i, 'aw_rel_org_not', false ) ), true ) ) );
	return array_slice( $ids, 0, $max );
}

/**
 * Where an item's link goes: its own page, or for press coverage (no page here) the article it records.
 *
 * @param int $id Item.
 * @return string
 */
function aw_featured_url( $id ) {
	return 'aw_press' === get_post_type( $id ) ? (string) get_post_meta( $id, 'aw_source_url', true ) : (string) get_permalink( $id );
}

/**
 * The "Featured in" list of a listing.
 *
 * @param int $lid Listing.
 * @return string
 */
function aw_featured_html( $lid ) {
	$items = aw_featured_items( $lid );
	if ( ! $items ) {
		return '';
	}
	$li = '';
	foreach ( $items as $i ) {
		$url = aw_featured_url( $i );
		if ( ! $url ) {
			continue;
		}
		$ext  = 'aw_press' === get_post_type( $i );
		$date = function_exists( 'aw_src_date' ) ? aw_date( (string) get_post_meta( $i, 'aw_source_date', true ) ?: get_post_field( 'post_date', $i ) ) : '';
		$li  .= '<li class="aw-featured-item"><span class="aw-featured-type">' . esc_html( AW_FEATURED_TYPES[ get_post_type( $i ) ] ) . '</span> '
			. '<a href="' . esc_url( $url ) . '"' . ( $ext ? ' rel="noopener nofollow"' : '' ) . '>' . esc_html( html_entity_decode( get_the_title( $i ), ENT_QUOTES ) ) . '</a> ' . $date . '</li>';
	}
	return $li ? '<section class="aw-org-links-group aw-featured"><h2>Featured in</h2><ul>' . $li . '</ul></section>' : '';
}

/* ---------- Schema: the item's mentions, the organisation's subjectOf ---------- */

/**
 * schema.org mentions for an item.
 *
 * @param int $id Item.
 * @return array
 */
function aw_featured_mentions_schema( $id ) {
	return array_map(
		fn( $l ) => array( '@type' => 'Organization', '@id' => get_permalink( $l ) . '#organization', 'name' => html_entity_decode( get_the_title( $l ), ENT_QUOTES ), 'url' => get_permalink( $l ) ),
		aw_featured_orgs( $id )
	);
}

/**
 * schema.org subjectOf for a listing.
 *
 * @param int $lid Listing.
 * @return array
 */
function aw_featured_subject_schema( $lid ) {
	$types = array( 'aw_news' => 'NewsArticle', 'aw_legal' => 'Article', 'post' => 'Article', 'aw_press' => 'NewsArticle', 'aw_campaign' => 'CreativeWork' );
	$out   = array();
	foreach ( aw_featured_items( $lid, 20 ) as $i ) {
		$url = aw_featured_url( $i );
		if ( $url ) {
			$out[] = array_filter( array( '@type' => $types[ get_post_type( $i ) ], 'name' => html_entity_decode( get_the_title( $i ), ENT_QUOTES ), 'url' => $url, 'datePublished' => (string) get_post_meta( $i, 'aw_source_date', true ) ?: get_the_date( 'Y-m-d', $i ) ) );
		}
	}
	return $out;
}

/* ---------- Admin: the found organisations, each with a tick ---------- */

/**
 * Rows for the Related box (model.php): organisations found in the text, ticked; untick a wrong one.
 *
 * @param WP_Post $post Item.
 */
function aw_featured_admin_rows( $post ) {
	if ( ! isset( AW_FEATURED_TYPES[ $post->post_type ] ) ) {
		return;
	}
	$found = array_map( 'intval', (array) get_post_meta( $post->ID, 'aw_rel_org_auto', false ) );
	$not   = array_map( 'intval', (array) get_post_meta( $post->ID, 'aw_rel_org_not', false ) );
	echo '<tr><th>Found in the text</th><td>';
	if ( ! $found && ! $not ) {
		echo '<p class="description">No organisation named in this item yet (found again when it is saved).</p>';
	}
	echo '<input type="hidden" name="aw_rel_org_auto_shown" value="' . esc_attr( implode( ',', $found ) ) . '">';
	foreach ( $found as $l ) {
		echo '<label style="display:block"><input type="checkbox" name="aw_rel_org_auto_keep[]" value="' . (int) $l . '" checked> ' . esc_html( get_the_title( $l ) ) . '</label>';
	}
	if ( $not ) {
		echo '<p class="description">Unticked before (not found again): ' . esc_html( implode( ', ', array_map( 'get_the_title', $not ) ) ) . '</p>';
	}
	echo '<p class="description">Shown on the organisation\'s page under "Featured in". Untick one that is a different organisation.</p></td></tr>';
}

/* ---------- WP-CLI: find the organisations in every item once ---------- */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'aw-featured scan',
		function ( $args, $assoc ) {
			$types = ! empty( $assoc['type'] ) ? array( $assoc['type'] ) : array_keys( AW_FEATURED_TYPES );
			delete_transient( 'aw_featured_names' );
			$items = 0;
			$links = 0;
			foreach ( $types as $t ) {
				foreach ( get_posts( array( 'post_type' => $t, 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) {
					$n = count( aw_featured_scan( $id ) );
					$items += $n ? 1 : 0;
					$links += $n;
				}
			}
			WP_CLI::success( "$links links found in $items items (" . count( aw_featured_names() ) . ' distinctive organisation names)' );
		}
	);
}
