<?php
/**
 * Content model, restructure Phase 2: legal sub-type, court, primary topic, topic → listing types for "Find help",
 * and relations between items (each one shown on both sides in WP Admin).
 *
 * Additive only: no existing item is changed. Front-end display comes in later phases.
 *
 * - Legal sub-type: taxonomy aw_legal_kind (Act, Rule, Order, Judgment, Circular, Notification) on Legal Watch and Research.
 * - Issuer: the existing taxonomy aw_source_type. Court: taxonomy aw_court (Supreme Court, High Courts, NGT).
 *   Both new taxonomies have no public archive pages; they are used for labels and list filters.
 * - Topic: meta aw_primary_topic (term ID) plus up to 2 more topics (secondary) per item.
 * - Topic → listing types: term meta aw_help_types on each topic (Topics screen).
 * - Relations: one post-meta row per linked ID, so the reverse side is a plain meta query.
 * - "Animal Writes" internal organisation: a private listing (meta aw_internal), so it can organise campaigns while
 *   staying out of the directory, search, the sitemap and every count.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_LEGAL_KINDS = array( 'act' => 'Act', 'rule' => 'Rule', 'order' => 'Order', 'judgment' => 'Judgment', 'circular' => 'Circular', 'notification' => 'Notification', 'template' => 'Template' );
const AW_COURTS      = array( 'supreme-court' => 'Supreme Court', 'high-court' => 'High Court', 'national-green-tribunal' => 'National Green Tribunal' );
const AW_SECONDARY_TOPICS = 2;

/**
 * Post-to-post relations: meta key => from types, target post type, label, label on the other side, max links (0 = any).
 */
function aw_relations() {
	return array(
		'aw_rel_legal'    => array( array( 'aw_news' ), 'aw_legal', 'Legal Watch items in this story', 'News about this', 0 ),
		'aw_rel_org'      => array( array( 'aw_news' ), 'aw_listing', 'Organisations in this story', 'News mentions', 0 ),
		'aw_organiser'    => array( array( 'aw_campaign' ), 'aw_listing', 'Organiser', 'Campaigns organised', 1 ),
		'aw_participant'  => array( array( 'aw_campaign' ), 'aw_listing', 'Participating organisations', 'Campaigns joined', 0 ),
		'aw_organisation' => array( array( 'aw_opportunity' ), 'aw_listing', 'Organisation', 'Opportunities', 1 ),
		'aw_rel_resource' => array( array( 'aw_guide' ), 'aw_resource', 'Resources', 'Guides using this', 0 ),
	);
}

/* ---------- Taxonomies ---------- */

add_action(
	'init',
	function () {
		$args = fn( $name, $one ) => array(
			'labels'             => array( 'name' => $name, 'singular_name' => $one ),
			'hierarchical'       => true,
			'public'             => true,
			'publicly_queryable' => false, // no archive pages; used as labels and list filters
			'rewrite'            => false,
			'show_in_rest'       => true,
			'show_admin_column'  => true,
		);
		register_taxonomy( 'aw_legal_kind', array( 'aw_legal', 'aw_research' ), $args( 'Legal types', 'Legal type' ) );
		register_taxonomy( 'aw_court', array( 'aw_legal', 'aw_research', 'aw_news' ), $args( 'Courts', 'Court' ) );
		register_taxonomy( 'aw_resource_type', array( 'aw_resource', 'aw_guide' ), $args( 'Resource types', 'Resource type' ) );
		register_taxonomy( 'aw_language', array( 'aw_resource', 'aw_legal' ), $args( 'Languages', 'Language' ) );
		register_taxonomy_for_object_type( 'aw_city', 'aw_legal' ); // Legal Watch: filter by state
	},
	11
);

/** Seed the fixed terms once (adds terms only; no item is tagged). */
add_action(
	'init',
	function () {
		if ( '1' === get_option( 'aw_model_seeded' ) ) {
			return;
		}
		foreach ( array( 'aw_legal_kind' => AW_LEGAL_KINDS, 'aw_court' => AW_COURTS ) as $tax => $terms ) {
			foreach ( $terms as $slug => $name ) {
				if ( ! term_exists( $slug, $tax ) ) {
					wp_insert_term( $name, $tax, array( 'slug' => $slug ) );
				}
			}
		}
		update_option( 'aw_internal_org', aw_internal_org_id(), true );
		update_option( 'aw_model_seeded', '1', false );
	},
	20
);

/* ---------- Internal organisation ---------- */

/** ID of the private "Animal Writes" listing, created on first use. */
function aw_internal_org_id() {
	$ids = get_posts( array( 'post_type' => 'aw_listing', 'post_status' => 'any', 'meta_key' => 'aw_internal', 'meta_value' => '1', 'fields' => 'ids', 'numberposts' => 1 ) ); // phpcs:ignore
	if ( $ids ) {
		return (int) $ids[0];
	}
	$id = wp_insert_post( array( 'post_type' => 'aw_listing', 'post_status' => 'private', 'post_title' => 'Animal Writes' ) );
	if ( $id && ! is_wp_error( $id ) ) {
		update_post_meta( $id, 'aw_internal', '1' );
		return (int) $id;
	}
	return 0;
}

// Belt and braces: even if someone publishes it, keep it out of every front-end listing query and search.
add_action(
	'pre_get_posts',
	function ( $q ) {
		if ( is_admin() ) {
			return;
		}
		$types = (array) $q->get( 'post_type' );
		if ( in_array( 'aw_listing', $types, true ) || $q->is_search() ) {
			$id = (int) get_option( 'aw_internal_org' ); // set when the model is seeded; never created here (this runs inside queries)
			if ( $id ) {
				$q->set( 'post__not_in', array_merge( (array) $q->get( 'post__not_in' ), array( $id ) ) );
			}
		}
	}
);

/* ---------- Admin: primary topic ---------- */

function aw_model_types() {
	return array_keys( aw_post_types() );
}

add_action(
	'add_meta_boxes',
	function ( $post_type ) {
		if ( in_array( $post_type, aw_model_types(), true ) && 'aw_listing' !== $post_type ) {
			add_meta_box( 'aw_primary_topic', 'Primary topic', 'aw_primary_topic_box', $post_type, 'side', 'high' );
		}
		$rel = array_filter( aw_relations(), fn( $r ) => in_array( $post_type, $r[0], true ) );
		if ( $rel || 'aw_guide' === $post_type ) {
			add_meta_box( 'aw_related', 'Related', 'aw_related_box', $post_type, 'normal', 'default' );
		}
		$back = array_filter( aw_relations(), fn( $r ) => $r[1] === $post_type );
		if ( $back ) {
			add_meta_box( 'aw_linked_from', 'Linked from', 'aw_linked_from_box', $post_type, 'normal', 'low' );
		}
	}
);

function aw_primary_topic_box( $post ) {
	wp_nonce_field( 'aw_model_' . $post->ID, '_aw_model' );
	$cur = (int) get_post_meta( $post->ID, 'aw_primary_topic', true );
	echo '<select name="aw_primary_topic" style="width:100%"><option value="0">None</option>';
	foreach ( get_terms( array( 'taxonomy' => 'aw_topic', 'hide_empty' => false, 'orderby' => 'name' ) ) as $t ) {
		echo '<option value="' . (int) $t->term_id . '"' . selected( $cur, $t->term_id, false ) . '>' . esc_html( $t->name ) . '</option>';
	}
	echo '</select><p class="description">Plus up to ' . (int) AW_SECONDARY_TOPICS . ' more topics in the Topics box (secondary).</p>';
	$n = count( wp_get_object_terms( $post->ID, 'aw_topic', array( 'fields' => 'ids' ) ) );
	if ( $n > 1 + AW_SECONDARY_TOPICS ) {
		echo '<p style="color:#b32d2e">This item has ' . (int) $n . ' topics. Keep 1 primary and up to ' . (int) AW_SECONDARY_TOPICS . ' others.</p>';
	}
}

/* ---------- Admin: relations ---------- */

function aw_rel_ids( $post_id, $key ) {
	return array_values( array_filter( array_map( 'intval', (array) get_post_meta( $post_id, $key, false ) ) ) );
}

/** IDs of items that link to $post_id through $key. */
function aw_rel_back( $post_id, $key, $types ) {
	return get_posts( array( 'post_type' => $types, 'post_status' => 'any', 'numberposts' => 100, 'fields' => 'ids', 'meta_key' => $key, 'meta_value' => (string) $post_id ) ); // phpcs:ignore
}

function aw_related_box( $post ) {
	wp_nonce_field( 'aw_model_' . $post->ID, '_aw_model' );
	echo '<table class="form-table">';
	foreach ( aw_relations() as $key => $r ) {
		if ( ! in_array( $post->post_type, $r[0], true ) ) {
			continue;
		}
		$ids = aw_rel_ids( $post->ID, $key );
		echo '<tr><th>' . esc_html( $r[2] ) . '</th><td><div class="aw-rel" data-type="' . esc_attr( rest_get_route_for_post_type_items( $r[1] ) ) . '" data-max="' . (int) $r[4] . '">';
		echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( implode( ',', $ids ) ) . '"><ul class="aw-rel-list">';
		foreach ( $ids as $id ) {
			echo '<li data-id="' . (int) $id . '">' . esc_html( get_post_field( 'post_title', $id ) ) . ' <a href="#" class="aw-rel-x">Remove</a></li>';
		}
		echo '</ul><input type="search" class="regular-text aw-rel-q" placeholder="Search to add"><ul class="aw-rel-hits"></ul></div></td></tr>';
	}
	if ( 'aw_guide' === $post->post_type ) {
		$cur = array_map( 'intval', (array) get_post_meta( $post->ID, 'aw_rel_listing_type', false ) );
		echo '<tr><th>Listing types</th><td>';
		foreach ( get_terms( array( 'taxonomy' => 'aw_listing_type', 'hide_empty' => false, 'orderby' => 'name' ) ) as $t ) {
			echo '<label style="display:inline-block;min-width:16em"><input type="checkbox" name="aw_rel_listing_type[]" value="' . (int) $t->term_id . '"' . checked( in_array( $t->term_id, $cur, true ), true, false ) . '> ' . esc_html( $t->name ) . '</label> ';
		}
		echo '</td></tr>';
	}
	echo '</table>';
}

function aw_linked_from_box( $post ) {
	$any = false;
	foreach ( aw_relations() as $key => $r ) {
		if ( $r[1] !== $post->post_type ) {
			continue;
		}
		$ids = aw_rel_back( $post->ID, $key, $r[0] );
		if ( ! $ids ) {
			continue;
		}
		$any = true;
		echo '<p><strong>' . esc_html( $r[3] ) . ':</strong> ';
		echo implode( ', ', array_map( fn( $id ) => '<a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html( get_post_field( 'post_title', $id ) ) . '</a>', $ids ) ) . '</p>'; // phpcs:ignore
	}
	if ( ! $any ) {
		echo '<p class="description">Nothing links here yet.</p>';
	}
}

add_action(
	'save_post',
	function ( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || ! isset( $_POST['_aw_model'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_aw_model'] ) ), 'aw_model_' . $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( isset( $_POST['aw_primary_topic'] ) ) {
			$t = absint( $_POST['aw_primary_topic'] );
			if ( $t && term_exists( $t, 'aw_topic' ) ) {
				update_post_meta( $post_id, 'aw_primary_topic', $t );
				wp_add_object_terms( $post_id, $t, 'aw_topic' ); // the primary topic is always one of the item's topics
			} else {
				delete_post_meta( $post_id, 'aw_primary_topic' );
			}
		}
		foreach ( aw_relations() as $key => $r ) {
			if ( ! in_array( $post->post_type, $r[0], true ) || ! isset( $_POST[ $key ] ) ) {
				continue;
			}
			$ids = array_values( array_unique( array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) ) ) ) ) );
			$ids = array_values( array_filter( $ids, fn( $id ) => get_post_type( $id ) === $r[1] && $id !== $post_id ) );
			if ( $r[4] ) {
				$ids = array_slice( $ids, 0, $r[4] );
			}
			delete_post_meta( $post_id, $key );
			foreach ( $ids as $id ) {
				add_post_meta( $post_id, $key, $id );
			}
		}
		if ( 'aw_guide' === $post->post_type ) {
			delete_post_meta( $post_id, 'aw_rel_listing_type' );
			foreach ( array_map( 'absint', (array) wp_unslash( $_POST['aw_rel_listing_type'] ?? array() ) ) as $tid ) {
				if ( term_exists( $tid, 'aw_listing_type' ) ) {
					add_post_meta( $post_id, 'aw_rel_listing_type', $tid );
				}
			}
		}
	},
	10,
	2
);

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		wp_enqueue_script( 'wp-api-fetch' );
		$js = <<<'JS'
jQuery(function($){
				function sync(box){var ids=box.find('.aw-rel-list li').map(function(){return $(this).data('id');}).get();box.find('input[type=hidden]').val(ids.join(','));}
				$(document).on('click','.aw-rel-x',function(e){e.preventDefault();var box=$(this).closest('.aw-rel');$(this).parent().remove();sync(box);});
				var t;$(document).on('input','.aw-rel-q',function(){var q=$(this).val(),box=$(this).closest('.aw-rel');clearTimeout(t);if(q.length<2){box.find('.aw-rel-hits').empty();return;}
					t=setTimeout(function(){wp.apiFetch({path:box.data('type')+'?search='+encodeURIComponent(q)+'&per_page=10&status=publish,private,draft,pending&_fields=id,title'}).then(function(r){
						var ul=box.find('.aw-rel-hits').empty();r.forEach(function(p){$('<li>').append($('<a href="#">').text(p.title.rendered.replace(/<[^>]+>/g,'')).on('click',function(e){e.preventDefault();
							var max=+box.data('max');if(max&&box.find('.aw-rel-list li').length>=max){box.find('.aw-rel-list').empty();}
							if(!box.find('.aw-rel-list li[data-id='+p.id+']').length){$('<li>').attr('data-id',p.id).text(p.title.rendered.replace(/<[^>]+>/g,'')+' ').append($('<a href="#" class="aw-rel-x">Remove</a>')).appendTo(box.find('.aw-rel-list'));}
							ul.empty();box.find('.aw-rel-q').val('');sync(box);})).appendTo(ul);});});},300);});
			});
JS;
		wp_add_inline_script( 'wp-api-fetch', $js );
	}
);

/* ---------- Admin: topic → listing types ("Find help" on topic pages) ---------- */

function aw_topic_help_field( $term ) {
	$cur = $term ? array_map( 'intval', (array) get_term_meta( $term->term_id, 'aw_help_types', true ) ) : array();
	foreach ( get_terms( array( 'taxonomy' => 'aw_listing_type', 'hide_empty' => false, 'orderby' => 'name' ) ) as $t ) {
		echo '<label style="display:inline-block;min-width:16em"><input type="checkbox" name="aw_help_types[]" value="' . (int) $t->term_id . '"' . checked( in_array( $t->term_id, $cur, true ), true, false ) . '> ' . esc_html( $t->name ) . '</label> ';
	}
	echo '<p class="description">Directory listing types shown under "Find help" on this topic\'s page.</p>';
}

add_action(
	'aw_topic_edit_form_fields',
	function ( $term ) {
		echo '<tr class="form-field"><th scope="row">Find help: listing types</th><td>';
		aw_topic_help_field( $term );
		echo '</td></tr>';
	}
);
add_action(
	'aw_topic_add_form_fields',
	function () {
		echo '<div class="form-field"><label>Find help: listing types</label>';
		aw_topic_help_field( null );
		echo '</div>';
	}
);
foreach ( array( 'created_aw_topic', 'edited_aw_topic' ) as $aw_hook ) {
	add_action(
		$aw_hook,
		function ( $term_id ) {
			if ( ! current_user_can( 'manage_categories' ) || ! isset( $_POST['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				return;
			}
			$ids = array_values( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['aw_help_types'] ?? array() ) ), fn( $t ) => term_exists( $t, 'aw_listing_type' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
			update_term_meta( $term_id, 'aw_help_types', $ids );
		}
	);
}

/** Listing types a topic's "Find help" should show. */
function aw_topic_help_types( $term_id ) {
	return array_map( 'intval', (array) get_term_meta( $term_id, 'aw_help_types', true ) );
}

// Listing-type screen: guides that link to this type (the other side of Guide → listing types).
add_action(
	'aw_listing_type_edit_form_fields',
	function ( $term ) {
		$ids = get_posts( array( 'post_type' => 'aw_guide', 'post_status' => 'any', 'numberposts' => 50, 'fields' => 'ids', 'meta_key' => 'aw_rel_listing_type', 'meta_value' => (string) $term->term_id ) ); // phpcs:ignore
		echo '<tr class="form-field"><th scope="row">Guides linking here</th><td>';
		echo $ids ? implode( ', ', array_map( fn( $id ) => '<a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html( get_post_field( 'post_title', $id ) ) . '</a>', $ids ) ) : '<span class="description">None yet.</span>'; // phpcs:ignore
		echo '</td></tr>';
	},
	20
);

/* ---------- Organisation profile (restructure Phase 8) ---------- */

/**
 * [aw_org_links]: on a listing, what links to it — campaigns it organised or joined, its open opportunities and
 * news that mentions it — plus "Claim or update this listing". Each group shows only when it has something.
 */
add_shortcode(
	'aw_org_links',
	function () {
		$id = get_the_ID();
		if ( ! $id || 'aw_listing' !== get_post_type( $id ) ) {
			return '';
		}
		$find  = function ( $key, $type, $extra = array() ) use ( $id ) {
			return get_posts(
				array_merge(
					array( 'post_type' => $type, 'post_status' => 'publish', 'numberposts' => 30, 'meta_key' => $key, 'meta_value' => (string) $id, 'orderby' => 'date', 'order' => 'DESC' ), // phpcs:ignore
					$extra
				)
			);
		};
		$open   = function_exists( 'aw_open_opportunities_meta_query' ) ? array( 'meta_query' => array( aw_open_opportunities_meta_query() ) ) : array(); // phpcs:ignore
		$groups = array(
			'Campaigns organised' => $find( 'aw_organiser', 'aw_campaign' ),
			'Campaigns joined'    => $find( 'aw_participant', 'aw_campaign' ),
			'Open opportunities'  => $find( 'aw_organisation', 'aw_opportunity', $open ),
			'In the news'         => $find( 'aw_rel_org', 'aw_news' ),
		);
		$html = '';
		foreach ( $groups as $label => $posts ) {
			if ( ! $posts ) {
				continue;
			}
			$html .= '<section class="aw-org-links-group"><h2>' . esc_html( $label ) . '</h2><ul>';
			foreach ( $posts as $p ) {
				$html .= '<li><a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a></li>';
			}
			$html .= '</ul></section>';
		}
		$html .= '<p class="aw-org-claim"><a href="' . esc_url( home_url( '/contact/?about=listing' ) ) . '">Claim or update this listing</a></p>';
		return '<div class="aw-org-links">' . $html . '</div>';
	}
);
