<?php
/**
 * Rank Math's own schema entries for Pages, Library items, Resources and campaigns, so its screens show the schema the
 * page really has.
 *
 * Rank Math (free) offers no CollectionPage, AboutPage or ContactPage type, so its Pages list said "Schema: Off" and its
 * Schema tab was empty although the live graph (schema.php) carries the right node. Each published Page now holds one
 * Rank Math schema entry (post meta rank_math_schema_{Type}, Rank Math's own format) naming the page's schema.org type:
 *
 * - CollectionPage: the section hubs, Categories, Media & PR (a list of items: CollectionPage + ItemList in the graph).
 * - AboutPage: About. ContactPage: Contact, and Remove my data (it is the contact form, /contact/?type=remove-data).
 * - WebPage: the home page, the policy pages (privacy, terms, disclaimer, editorial policy, corrections), Claim this
 *   listing (a search for one's own listing and a claim request, not a way to contact us), Report a mistake (a
 *   correction request about one listing) and Register as a volunteer (WebPage with a JoinAction: the form joins the
 *   volunteer network).
 * - None: Search and Thank you (noindex, no page node).
 *
 * The entry is a label for Rank Math's screens; the live node stays the one built from the site's data. On the page,
 * the entry Rank Math would print as a separate node is removed (one graph, no duplicates). When an editor changes
 * the entry in Rank Math's Schema tab, the changed properties are put on the page's node (the editor wins); an entry
 * the editor deleted is not added back. Entries are written when a Page is saved and in bulk with
 * `wp aw-seo rank-math-schema [--dry-run] [--type=page,aw_legal,post,aw_campaign]`.
 *
 * Library and Resources items carry the type of their own node (aw_article_nodes in schema.php): Legislation for
 * Acts, Rules and Notifications, DigitalDocument for templates, Article for the rest (a guide's HowTo stays a part of
 * it). A campaign carries Event (CreativeWork when no day is stored), as aw_campaign_schema() builds it.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Post meta holding the meta_id of the entry this plugin wrote (Rank Math updates entries in place, keeping it). */
const AW_RM_SCHEMA_MID = '_aw_rm_schema_mid';

/**
 * The schema.org type of a Page, '' for none.
 *
 * @param int $id Page ID.
 * @return string
 */
function aw_rm_page_schema_type( $id ) {
	$post = get_post( $id );
	if ( ! $post || 'page' !== $post->post_type ) {
		return '';
	}
	$slug = $post->post_name;
	if ( in_array( $slug, array( 'search', 'thank-you' ), true ) ) {
		return '';
	}
	if ( in_array( $slug, array( 'categories', 'media' ), true ) || 'page-hub' === get_page_template_slug( $post ) ) {
		return 'CollectionPage';
	}
	$map = array(
		'about'          => 'AboutPage',
		'contact'        => 'ContactPage',
		'remove-my-data' => 'ContactPage',
	);
	return $map[ $slug ] ?? 'WebPage';
}

/** Post types whose items get a Rank Math schema entry. */
const AW_RM_SCHEMA_TYPES = array( 'page', 'aw_legal', 'post', 'aw_campaign' );

/**
 * The schema.org type of a Page, Library item, Resource or campaign: the type of its own live node; '' for none.
 *
 * @param int $id Post ID.
 * @return string
 */
function aw_rm_schema_type( $id ) {
	$type = get_post_type( $id );
	if ( 'page' === $type ) {
		return aw_rm_page_schema_type( $id );
	}
	if ( in_array( $type, array( 'aw_legal', 'post' ), true ) && function_exists( 'aw_article_nodes' ) ) {
		$nodes = aw_article_nodes( $id );
		return (string) ( $nodes[0]['@type'] ?? '' );
	}
	if ( 'aw_campaign' === $type && function_exists( 'aw_campaign_schema' ) ) {
		return (string) ( aw_campaign_schema( $id )['@type'] ?? '' );
	}
	return '';
}

/**
 * Extra properties a page's node carries beyond its type (the volunteer form's action).
 *
 * @param int $id Page ID.
 * @return array
 */
function aw_rm_page_schema_extra( $id ) {
	if ( 'volunteer' === get_post_field( 'post_name', $id ) ) {
		return array(
			'potentialAction' => array(
				'@type'  => 'JoinAction',
				'name'   => aw_seo_plain( get_the_title( $id ) ),
				'target' => get_permalink( $id ),
				'object' => array( '@id' => home_url( '/#organization' ) ),
			),
		);
	}
	return array();
}

/**
 * The Rank Math schema entry for a page type (Rank Math's own format: @type, metadata, properties with its variables).
 *
 * @param string $type      schema.org type.
 * @param string $shortcode Existing shortcode id to keep.
 * @return array
 */
function aw_rm_schema_entry( $type, $shortcode = '' ) {
	return array(
		'@type'       => $type,
		'metadata'    => array(
			'title'     => $type,
			'type'      => 'template',
			'shortcode' => '' !== $shortcode ? $shortcode : uniqid( 's-' ),
			'isPrimary' => true,
		),
		'name'        => '%seo_title%',
		'description' => '%seo_description%',
	);
}

/**
 * The entry this plugin wrote on a post: [meta_id, meta_key, value], or null when there is none (never written, or
 * deleted by an editor).
 *
 * @param int $id Post ID.
 * @return array|null
 */
function aw_rm_schema_own( $id ) {
	$mid = (int) get_post_meta( $id, AW_RM_SCHEMA_MID, true );
	if ( ! $mid ) {
		return null;
	}
	$row = get_metadata_by_mid( 'post', $mid );
	if ( ! $row || (int) $row->post_id !== (int) $id || 0 !== strpos( $row->meta_key, 'rank_math_schema_' ) ) {
		return null;
	}
	return array( $mid, $row->meta_key, maybe_unserialize( $row->meta_value ) );
}

/**
 * Properties of an entry an editor changed from what this plugin wrote (metadata and @type aside).
 *
 * @param array $entry Stored entry.
 * @return array Property names.
 */
function aw_rm_schema_edited( $entry ) {
	if ( ! is_array( $entry ) ) {
		return array();
	}
	$base    = aw_rm_schema_entry( (string) ( is_array( $entry['@type'] ?? '' ) ? $entry['@type'][0] : ( $entry['@type'] ?? '' ) ) );
	$changed = array();
	foreach ( $entry as $k => $v ) {
		if ( in_array( $k, array( '@type', 'metadata', '@id' ), true ) ) {
			continue;
		}
		if ( ! array_key_exists( $k, $base ) || $base[ $k ] !== $v ) {
			$changed[] = $k;
		}
	}
	return $changed;
}

/**
 * Write (or refresh) a page's Rank Math schema entry.
 *
 * @param int  $id  Page ID.
 * @param bool $dry Report only.
 * @return string What was done.
 */
function aw_rm_schema_sync( $id, $dry = false ) {
	if ( ! in_array( get_post_type( $id ), AW_RM_SCHEMA_TYPES, true ) || 'publish' !== get_post_status( $id ) ) {
		return 'skipped';
	}
	$type = aw_rm_schema_type( $id );
	$own  = aw_rm_schema_own( $id );
	if ( ! $own && get_post_meta( $id, AW_RM_SCHEMA_MID, true ) ) {
		return 'kept: removed by an editor';
	}
	if ( '' === $type ) {
		return $own ? 'kept: entry present on a page without one' : 'none';
	}
	if ( $own ) {
		list( $mid, $key, $value ) = $own;
		if ( aw_rm_schema_edited( $value ) ) {
			return 'kept: edited in Rank Math';
		}
		$want = aw_rm_schema_entry( $type, (string) ( $value['metadata']['shortcode'] ?? '' ) );
		if ( $value === $want && 'rank_math_schema_' . $type === $key ) {
			return 'unchanged ' . $type;
		}
		if ( ! $dry ) {
			update_metadata_by_mid( 'post', $mid, $want, 'rank_math_schema_' . $type );
		}
		return 'updated ' . $type;
	}
	if ( ! $dry ) {
		$entry = aw_rm_schema_entry( $type );
		$mid   = add_post_meta( $id, 'rank_math_schema_' . $type, $entry );
		if ( $mid ) {
			add_post_meta( $id, 'rank_math_shortcode_schema_' . $entry['metadata']['shortcode'], $mid ); // Rank Math's own shortcut row
			update_post_meta( $id, AW_RM_SCHEMA_MID, $mid );
		}
	}
	return 'added ' . $type;
}

add_action(
	'wp_after_insert_post',
	function ( $id, $post ) {
		if ( in_array( $post->post_type, AW_RM_SCHEMA_TYPES, true ) && ! wp_is_post_revision( $id ) && ! wp_is_post_autosave( $id ) ) {
			aw_rm_schema_sync( $id ); // after terms and meta are saved: the type depends on them
		}
	},
	100,
	2
);

// On the page: Rank Math's copy of our entry is not printed (the page's node is built in schema.php); an editor's
// changes to it go onto that node. Runs after schema.php (999).
add_filter(
	'rank_math/json_ld',
	function ( $data ) {
		if ( ! is_singular( AW_RM_SCHEMA_TYPES ) || ! is_array( $data ) || ! $data ) {
			return $data;
		}
		$id   = get_queried_object_id();
		$own  = aw_rm_schema_own( $id );
		$type = aw_rm_schema_type( $id );
		if ( $own ) {
			$rm   = 'schema-' . $own[0];
			$node = isset( $data[ $rm ] ) && is_array( $data[ $rm ] ) ? $data[ $rm ] : array();
			unset( $data[ $rm ] );
			$edit = array_intersect_key( $node, array_flip( aw_rm_schema_edited( $own[2] ) ) );
			if ( $edit ) {
				foreach ( $data as $k => $n ) {
					if ( is_array( $n ) && in_array( $type, (array) ( $n['@type'] ?? array() ), true ) ) {
						$data[ $k ] = array_merge( $n, $edit ); // the editor's values win
						break;
					}
				}
			}
		}
		$extra = is_page() ? aw_rm_page_schema_extra( $id ) : array();
		if ( $extra && isset( $data['WebPage'] ) && is_array( $data['WebPage'] ) ) {
			$data['WebPage'] = array_merge( $data['WebPage'], array_diff_key( $extra, $data['WebPage'] ) );
		}
		return aw_rm_absolute_urls( $data );
	},
	1000
);

/**
 * Rank Math takes a page's first content image as its primary image as written in the content, which can be a
 * site-relative address (/wp-content/...); schema addresses must be absolute.
 *
 * @param mixed $node Graph or node.
 * @return mixed
 */
function aw_rm_absolute_urls( $node ) {
	if ( ! is_array( $node ) ) {
		return $node;
	}
	foreach ( $node as $k => $v ) {
		if ( is_string( $v ) && in_array( $k, array( '@id', 'url', 'contentUrl' ), true ) && 0 === strpos( $v, '/' ) && 0 !== strpos( $v, '//' ) ) {
			$node[ $k ] = home_url( $v );
		} elseif ( is_array( $v ) ) {
			$node[ $k ] = aw_rm_absolute_urls( $v );
		}
	}
	return $node;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Write Rank Math schema entries for every published Page, Library item, Resource and campaign.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Report only.
	 *
	 * [--type=<types>]
	 * : Comma-separated post types (default: all four).
	 */
	WP_CLI::add_command(
		'aw-seo rank-math-schema',
		function ( $args, $assoc ) {
			$dry   = ! empty( $assoc['dry-run'] );
			$types = array_intersect( isset( $assoc['type'] ) ? explode( ',', $assoc['type'] ) : AW_RM_SCHEMA_TYPES, AW_RM_SCHEMA_TYPES );
			foreach ( get_posts( array( 'post_type' => array_values( $types ), 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $p ) {
				WP_CLI::log( sprintf( '%d %s %s: %s', $p->ID, $p->post_type, $p->post_name, aw_rm_schema_sync( $p->ID, $dry ) ) );
			}
		}
	);
}
