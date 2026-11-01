<?php
/**
 * Content model, restructure Phase 2: legal sub-type, court, category → listing types for "Find help",
 * and relations between items (each one shown on both sides in WP Admin).
 *
 * Additive only: no existing item is changed. Front-end display comes in later phases.
 *
 * - Library type: a Tag (Act, Rule, Order, Judgment, Circular, Notification, Template, Advisory, Official release).
 * - Issuer: a label on Library items (meta aw_issuer, issuers.php). Courts (Supreme Court, High Courts, NGT) are
 *   issuers; the Court list and the issuer list were merged into the label on 8 Oct 2026 (court kept in aw_court_legacy).
 * - Category: WordPress's own; the primary one is Rank Math's (its Primary Category field, meta rank_math_primary_category).
 * - Category → listing types: term meta aw_help_types on each category (Categories screen).
 * - Relations: one post-meta row per linked ID, so the reverse side is a plain meta query.
 * - "Animal Writes" internal organisation: a private listing (meta aw_internal), so it can organise campaigns while
 *   staying out of the directory, search, the sitemap and every count.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_LEGAL_KINDS = array( 'act' => 'Act', 'rule' => 'Rule', 'order' => 'Order', 'judgment' => 'Judgment', 'circular' => 'Circular', 'notification' => 'Notification', 'template' => 'Template', 'advisory' => 'Advisory', 'official-release' => 'Official Release' ); // government press releases (PIB); reports about cases (LiveLaw, Bar & Bench) are News (owner, 14 Oct 2026)
/** Services a listing offers: a fixed list (owner, 9 Oct 2026). Shown as tags; a Directory filter once 3+ listings use one. Names are activities, title case (owner, 20 Oct 2026, replacing sentence case). */
const AW_SERVICES = array( 'rescue' => 'Rescue Work', 'treatment' => 'Treatment and Vet Care', 'shelter' => 'Sheltering', 'adoption' => 'Adoption Service', 'feeding' => 'Feeding', 'sterilisation' => 'Sterilisation (ABC)', 'vaccination' => 'Vaccination', 'ambulance' => 'Ambulance Service', 'boarding-foster' => 'Boarding and Fostering', 'wildlife-rescue' => 'Wildlife Rescue', 'legal-help' => 'Legal Help', 'cattle-care' => 'Cattle Care', 'animal-welfare' => 'Animal Welfare', 'bird-treatment' => 'Bird Treatment', 'emergency-rescue' => 'Emergency Rescue' ); // Animal welfare, Bird treatment: added 20 Oct 2026 for services implied by type; Emergency rescue (the existing type tag, owner 20 Oct 2026)

/**
 * Post-to-post relations: meta key => from types, target post type, label, label on the other side, max links (0 = any),
 * labels per from type ( type => [ label, label on the other side ] ), and true for an older key that is only read:
 * the Related box no longer offers it and saving leaves its rows as they are; "Linked from" still lists them.
 */
function aw_relations() {
	return array(
		'aw_rel_legal'    => array( array( 'aw_news' ), 'aw_legal', 'Legal Watch items in this story', 'News about this', 0 ),
		'aw_rel_org'      => array( array( 'aw_news', 'aw_legal', 'post', 'aw_press', 'aw_campaign' ), 'aw_listing', 'Organisations featured', 'Featured in', 0 ), // "Featured in" on the organisation's page (featured.php)
		'aw_organiser'    => array( array( 'aw_campaign' ), 'aw_listing', 'Organiser', 'Campaigns organised', 1, array(), true ), // older key: a campaign's organiser is aw_organisation, this is read when that is empty
		'aw_participant'  => array( array( 'aw_campaign' ), 'aw_listing', 'Participating organisations', 'Campaigns joined', 0, array(), true ), // older key: partners are edited in the Organisations box (aw_campaign_partners); rows here are still read
		'aw_organisation' => array( array( 'aw_opportunity', 'aw_campaign' ), 'aw_listing', 'Organisation', 'Opportunities', 1, array( 'aw_campaign' => array( 'Organiser', 'Campaigns organised' ) ) ),
		'aw_rel_resource' => array( array( 'post' ), 'aw_legal', 'Library items (templates, documents)', 'Guides using this', 0 ),
		'aw_rel_guide'    => array( array( 'post' ), 'post', 'Guides on this (shown at the end of the page)', 'Linked from (research)', 0 ), // e.g. dog-bite studies → the dog-bite guide (owner, 22 Oct 2026)
	);
}

/* ---------- Labels: WordPress's own Tags (tags.php) ---------- */

/** The fixed services list (added once; names follow AW_SERVICES). */
add_action(
	'init',
	function () {
		if ( '2' === get_option( 'aw_services_seeded' ) ) { // 2: Animal welfare and Bird treatment added (20 Oct 2026)
			return;
		}
		foreach ( AW_SERVICES as $slug => $name ) {
			if ( ! term_exists( $slug, 'post_tag' ) ) {
				wp_insert_term( $name, 'post_tag', array( 'slug' => $slug ) );
			}
		}
		update_option( 'aw_services_seeded', '2', false );
	},
	21
);

/** Seed the fixed terms once (adds terms only; no item is tagged). */
add_action(
	'init',
	function () {
		if ( '1' === get_option( 'aw_model_seeded' ) ) {
			return;
		}
		foreach ( array( 'post_tag' => AW_LEGAL_KINDS ) as $tax => $terms ) {
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

// The one-time move of four news reports from the Library to News (S1.3, 8 Oct 2026) ran and was removed (audit item 16, flag confirmed on live 25 Oct 2026; see git history).

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

/* ---------- Admin: relations boxes ---------- */

add_action(
	'add_meta_boxes',
	function ( $post_type ) {
		$rel = array_filter( aw_relations(), fn( $r ) => in_array( $post_type, $r[0], true ) );
		if ( $rel || 'post' === $post_type ) {
			add_meta_box( 'aw_related', 'Related', 'aw_related_box', $post_type, 'normal', 'default' );
		}
		$back = array_filter( aw_relations(), fn( $r ) => $r[1] === $post_type );
		if ( $back ) {
			add_meta_box( 'aw_linked_from', 'Linked from', 'aw_linked_from_box', $post_type, 'normal', 'low' );
		}
	}
);

/* ---------- Admin: relations ---------- */

function aw_rel_ids( $post_id, $key ) {
	return array_values( array_filter( array_map( 'intval', (array) get_post_meta( $post_id, $key, false ) ) ) );
}

/** A relation's label for a from type: 2 = this side, 3 = the other side. */
function aw_rel_label( $r, $type, $side = 2 ) {
	return (string) ( $r[5][ $type ][ 2 === $side ? 0 : 1 ] ?? $r[ $side ] );
}

/** A campaign's organiser (listing ID, 0 for none): aw_organisation, else the older aw_organiser. */
function aw_campaign_organiser( $id ) {
	$org = aw_rel_ids( $id, 'aw_organisation' );
	if ( ! $org ) {
		$org = aw_rel_ids( $id, 'aw_organiser' );
	}
	return $org ? $org[0] : 0;
}

/** A campaign's partners (listing IDs, in order, no repeats): the Partners list (aw_campaign_partners), then any older aw_participant rows. */
function aw_campaign_partner_ids( $id ) {
	$csv = array_map( 'intval', explode( ',', (string) get_post_meta( $id, 'aw_campaign_partners', true ) ) );
	return array_values( array_unique( array_filter( array_merge( $csv, aw_rel_ids( $id, 'aw_participant' ) ) ) ) );
}

/** IDs of items that link to $post_id through $key. */
function aw_rel_back( $post_id, $key, $types ) {
	return get_posts( array( 'post_type' => $types, 'post_status' => 'any', 'numberposts' => 100, 'fields' => 'ids', 'meta_key' => $key, 'meta_value' => (string) $post_id ) ); // phpcs:ignore
}

function aw_related_box( $post ) {
	wp_nonce_field( 'aw_model_' . $post->ID, '_aw_model' );
	echo '<table class="form-table">';
	foreach ( aw_relations() as $key => $r ) {
		if ( ! in_array( $post->post_type, $r[0], true ) || ! empty( $r[6] ) ) {
			continue;
		}
		$ids = aw_rel_ids( $post->ID, $key );
		echo '<tr><th>' . esc_html( aw_rel_label( $r, $post->post_type ) ) . '</th><td><div class="aw-rel" data-type="' . esc_attr( rest_get_route_for_post_type_items( $r[1] ) ) . '" data-max="' . (int) $r[4] . '">';
		echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( implode( ',', $ids ) ) . '"><ul class="aw-rel-list">';
		foreach ( $ids as $id ) {
			echo '<li data-id="' . (int) $id . '">' . esc_html( get_post_field( 'post_title', $id ) ) . ' <a href="#" class="aw-rel-x">Remove</a></li>';
		}
		echo '</ul><input type="search" class="regular-text aw-rel-q" placeholder="Search to add"><ul class="aw-rel-hits"></ul></div></td></tr>';
	}
	if ( function_exists( 'aw_featured_admin_rows' ) ) {
		aw_featured_admin_rows( $post ); // organisations found in the text, each with a tick
	}
	if ( 'post' === $post->post_type ) {
		$cur = array_map( 'intval', (array) get_post_meta( $post->ID, 'aw_rel_listing_type', false ) );
		echo '<tr><th>Listing types</th><td>';
		foreach ( get_terms( array( 'taxonomy' => 'post_tag', 'slug' => aw_listing_type_slugs(), 'hide_empty' => false, 'orderby' => 'name' ) ) as $t ) {
			echo '<label style="display:inline-block;min-width:16em"><input type="checkbox" name="aw_rel_listing_type[]" value="' . (int) $t->term_id . '"' . checked( in_array( $t->term_id, $cur, true ), true, false ) . '> ' . esc_html( $t->name ) . '</label> ';
		}
		echo '</td></tr>';
	}
	echo '</table>';
}

function aw_linked_from_box( $post ) {
	$lines = array(); // label on this side => linking IDs (an older key and its newer one share a line)
	foreach ( aw_relations() as $key => $r ) {
		if ( $r[1] !== $post->post_type ) {
			continue;
		}
		$groups = empty( $r[5] ) ? array( $r[3] => $r[0] ) : array();
		foreach ( empty( $r[5] ) ? array() : $r[0] as $type ) {
			$groups[ aw_rel_label( $r, $type, 3 ) ][] = $type;
		}
		foreach ( $groups as $label => $types ) {
			$ids = aw_rel_back( $post->ID, $key, $types );
			if ( $ids ) {
				$lines[ $label ] = array_values( array_unique( array_merge( $lines[ $label ] ?? array(), $ids ) ) );
			}
		}
	}
	foreach ( $lines as $label => $ids ) {
		echo '<p><strong>' . esc_html( $label ) . ':</strong> ';
		echo implode( ', ', array_map( fn( $id ) => '<a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html( get_post_field( 'post_title', $id ) ) . '</a>', $ids ) ) . '</p>'; // phpcs:ignore
	}
	if ( ! $lines ) {
		echo '<p class="description">Nothing links here yet.</p>';
	}
}

add_action(
	'save_post',
	function ( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || ! isset( $_POST['_aw_model'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_aw_model'] ) ), 'aw_model_' . $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		foreach ( aw_relations() as $key => $r ) {
			if ( ! in_array( $post->post_type, $r[0], true ) || ! empty( $r[6] ) || ! isset( $_POST[ $key ] ) ) { // an older key is never written
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
		if ( 'post' === $post->post_type ) {
			delete_post_meta( $post_id, 'aw_rel_listing_type' );
			foreach ( array_map( 'absint', (array) wp_unslash( $_POST['aw_rel_listing_type'] ?? array() ) ) as $tid ) {
				if ( term_exists( $tid, 'post_tag' ) ) {
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

/* ---------- Admin: category → listing types ("Find help" on category pages) ---------- */

function aw_category_help_field( $term ) {
	$cur = $term ? array_map( 'intval', (array) get_term_meta( $term->term_id, 'aw_help_types', true ) ) : array();
	foreach ( get_terms( array( 'taxonomy' => 'post_tag', 'slug' => aw_listing_type_slugs(), 'hide_empty' => false, 'orderby' => 'name' ) ) as $t ) {
		echo '<label style="display:inline-block;min-width:16em"><input type="checkbox" name="aw_help_types[]" value="' . (int) $t->term_id . '"' . checked( in_array( $t->term_id, $cur, true ), true, false ) . '> ' . esc_html( $t->name ) . '</label> ';
	}
	echo '<p class="description">Directory listing types shown under "Find help" on this category\'s page.</p>';
}

add_action(
	'category_edit_form_fields',
	function ( $term ) {
		echo '<tr class="form-field"><th scope="row">Find help: listing types</th><td>';
		aw_category_help_field( $term );
		echo '</td></tr>';
	}
);
add_action(
	'category_add_form_fields',
	function () {
		echo '<div class="form-field"><label>Find help: listing types</label>';
		aw_category_help_field( null );
		echo '</div>';
	}
);
foreach ( array( 'created_category', 'edited_category' ) as $aw_hook ) {
	add_action(
		$aw_hook,
		function ( $term_id ) {
			if ( ! current_user_can( 'manage_categories' ) || ! isset( $_POST['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				return;
			}
			$ids = array_values( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['aw_help_types'] ?? array() ) ), fn( $t ) => term_exists( $t, 'post_tag' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
			update_term_meta( $term_id, 'aw_help_types', $ids );
		}
	);
}

/** Listing types a category's "Find help" should show. */
function aw_category_help_types( $term_id ) {
	return array_map( 'intval', (array) get_term_meta( $term_id, 'aw_help_types', true ) );
}

// Listing-type screen: guides that link to this type (the other side of Guide → listing types).
add_action(
	'post_tag_edit_form_fields',
	function ( $term ) {
		if ( ! in_array( $term->slug, aw_listing_type_slugs(), true ) ) {
			return;
		}
		$ids = get_posts( array( 'post_type' => 'post', 'post_status' => 'any', 'numberposts' => 50, 'fields' => 'ids', 'meta_key' => 'aw_rel_listing_type', 'meta_value' => (string) $term->term_id ) ); // phpcs:ignore
		echo '<tr class="form-field"><th scope="row">Guides linking here</th><td>';
		echo $ids ? implode( ', ', array_map( fn( $id ) => '<a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html( get_post_field( 'post_title', $id ) ) . '</a>', $ids ) ) : '<span class="description">None yet.</span>'; // phpcs:ignore
		echo '</td></tr>';
	},
	20
);
