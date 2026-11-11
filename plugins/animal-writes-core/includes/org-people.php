<?php
/**
 * Volunteers of an organisation, from FluentCRM (owner, 21 Oct 2026; was the site's volunteers table).
 *
 * - The Volunteer form asks "Are you associated with an animal welfare organisation?", the organisation's name and
 *   "Show my name on the organisation's page". Its FluentCRM link tags the contact org-pending.
 * - The owner checks it in FluentCRM and swaps org-pending for org-approved. The contact belongs to the listing whose
 *   title matches the name they gave (ignoring case and punctuation, either containing the other), or to the listing
 *   ID the owner puts in the custom field "Volunteer: organisation's listing ID" when the name does not match.
 * - On the organisation's page, under "Volunteers": the names of approved contacts who ticked "Show my name"; and in
 *   the schema as member (Person). Email and phone are never shown.
 * - WP Admin → a listing → "Volunteers" box: who is approved for it and who is waiting (read-only, with links to
 *   their FluentCRM contacts).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/org-people-link.php'; // aw_org_people_link_plan(): the claimant link decision, tested without WordPress

/** A name for matching: lower case, letters and digits only. */
function aw_org_people_norm( $s ) {
	return trim( preg_replace( '/[^a-z0-9]+/', ' ', strtolower( (string) $s ) ) );
}

/**
 * FluentCRM contacts tagged with $tag whose organisation is this listing.
 *
 * @param int    $lid Listing.
 * @param string $tag org-approved | org-pending.
 * @return array list of array{id, name, show}
 */
function aw_org_people_crm( $lid, $tag = 'org-approved' ) {
	static $cache = array();
	$lid = (int) $lid;
	if ( isset( $cache[ $tag ][ $lid ] ) ) {
		return $cache[ $tag ][ $lid ];
	}
	$out = array();
	$t   = function_exists( 'aw_crm_on' ) && aw_crm_on() ? FluentCrmApi( 'tags' )->getInstance()->where( 'slug', $tag )->first() : null;
	if ( $t ) {
		$title = aw_org_people_norm( get_the_title( $lid ) );
		foreach ( \FluentCrm\App\Models\Subscriber::filterByTags( array( (int) $t->id ) )->limit( 2000 )->get() as $c ) {
			$f    = (array) $c->custom_fields();
			$org  = aw_org_people_norm( $f['vol_organisation'] ?? '' );
			$for  = (int) ( $f['vol_org_listing'] ?? 0 );
			$hits = $for ? $for === $lid : ( strlen( $org ) >= 3 && strlen( $title ) >= 3 && ( false !== strpos( $title, $org ) || false !== strpos( $org, $title ) ) );
			if ( $hits ) {
				$out[] = array( 'id' => (int) $c->id, 'name' => trim( $c->first_name . ' ' . $c->last_name ), 'role' => trim( (string) ( $f['vol_org_role'] ?? '' ) ), 'show' => '' !== (string) ( $f['vol_org_show'] ?? '' ) && '0' !== (string) $f['vol_org_show'] );
			}
		}
	}
	return $cache[ $tag ][ $lid ] = $out;
}

/**
 * The shown volunteers' names.
 *
 * @param int $lid Listing.
 * @return string[]
 */
function aw_org_people_public_names( $lid ) {
	return array_values( array_filter( wp_list_pluck( array_filter( aw_org_people_crm( $lid ), fn( $p ) => $p['show'] ), 'name' ) ) );
}

/**
 * "Volunteers" on the organisation's page.
 *
 * @param int $lid Listing.
 * @return string
 */
function aw_org_people_html( $lid ) {
	$people = array_values( array_filter( aw_org_people_crm( $lid ), fn( $p ) => $p['show'] && '' !== $p['name'] ) );
	if ( ! $people ) {
		return '';
	}
	aw_use_component_style( 'aw-directory' ); // aw-org-links-group, aw-org-links
	// A claimant shows with their role (owner, 31 Oct 2026: claim form #6), a volunteer with their name alone.
	return '<section class="aw-org-links-group aw-org-people"><h2>Volunteers</h2><ul>' . implode( '', array_map( fn( $p ) => '<li>' . esc_html( $p['name'] ) . ( '' !== $p['role'] ? '<span class="aw-org-people-role">, ' . esc_html( $p['role'] ) . '</span>' : '' ) . '</li>', $people ) ) . '</ul></section>';
}

// [aw_org_people] on the listing template: the Volunteers part of the former [aw_org_links] (whose other parts the
// template shows as its own blocks): approved contacts who chose to show their name, nothing when there are none.
add_shortcode(
	'aw_org_people',
	function () {
		$html = 'aw_listing' === get_post_type() ? aw_org_people_html( get_the_ID() ) : '';
		return '' !== $html ? '<div class="aw-org-links">' . $html . '</div>' : '';
	}
);

/**
 * schema.org member for a listing: the shown volunteers.
 *
 * @param int $lid Listing.
 * @return array
 */
function aw_org_people_schema( $lid ) {
	$people = array_values( array_filter( aw_org_people_crm( $lid ), fn( $p ) => $p['show'] && '' !== $p['name'] ) );
	return array_map( fn( $p ) => array( '@type' => 'OrganizationRole', 'roleName' => '' !== $p['role'] ? $p['role'] : 'Volunteer', 'member' => array( '@type' => 'Person', 'name' => $p['name'] ) ), $people );
}

/* ---------- Approved claimants become the listing's people (owner, 18 Oct 2026; fixed 11 Nov 2026) ---------- */

/**
 * Link a claimant's FluentCRM contact to a listing, privately unless they ticked "Show my name"
 * (aw_org_people_link_plan()).
 *
 * @param int    $lid   Listing.
 * @param string $email The claimant's email.
 * @param string $role  Their role (label).
 * @param bool   $show  They ticked "Show my name".
 * @param bool   $apply False: work out what would change and write nothing.
 * @return string crm-off | no-email | no-contact | no-listing | elsewhere | already | updated | linked
 */
function aw_org_people_link( $lid, $email, $role, $show, $apply = true ) {
	if ( ! function_exists( 'aw_crm_on' ) || ! aw_crm_on() ) {
		return 'crm-off';
	}
	$email = strtolower( trim( (string) $email ) );
	if ( ! is_email( $email ) ) {
		return 'no-email';
	}
	$lid = (int) $lid;
	if ( 'aw_listing' !== get_post_type( $lid ) ) {
		return 'no-listing';
	}
	$contact = FluentCrmApi( 'contacts' )->getContact( $email );
	if ( ! $contact ) {
		return 'no-contact'; // the claim form adds every claimant to FluentCRM; an old entry may predate that
	}
	$tag     = FluentCrmApi( 'tags' )->getInstance()->where( 'slug', 'org-approved' )->first();
	$has_tag = $tag && \FluentCrm\App\Models\Subscriber::filterByTags( array( (int) $tag->id ) )->where( 'id', (int) $contact->id )->first();
	$plan    = aw_org_people_link_plan( (array) $contact->custom_fields(), (bool) $has_tag, $lid, (bool) $show, html_entity_decode( get_the_title( $lid ), ENT_QUOTES ), (string) $role );
	if ( $apply ) {
		if ( $plan['tag'] ) {
			$contact->attachTags( aw_crm_tag_ids( array( 'org-approved' ) ) );
		}
		if ( $plan['sync'] ) {
			$contact->syncCustomFieldValues( $plan['sync'], false );
		}
	}
	return $plan['status'];
}

/**
 * An approved claim on the old form (aw_claim post, claims.php): the claimant becomes one of the listing's people,
 * not shown (the old form had no "Show my name").
 *
 * @param int  $claim aw_claim post.
 * @param int  $lid   Listing.
 * @param bool $apply False: write nothing.
 * @return string aw_org_people_link() status.
 */
function aw_org_people_from_claim( $claim, $lid, $apply = true ) {
	$role = (string) get_post_meta( $claim, 'aw_c_role', true );
	$role = defined( 'AW_CLAIM_ROLES' ) && '' !== $role ? ( AW_CLAIM_ROLES[ $role ] ?? $role ) : $role;
	return aw_org_people_link( $lid, (string) get_post_meta( $claim, 'aw_c_email', true ), $role, false, $apply );
}

/**
 * Past approved claims, old form and form #6: link each claimant (dry run unless $apply).
 *
 * @param bool $apply Write the links.
 * @return array{counts: array<string, int>, rows: array<int, array{claim: string, listing: int, status: string}>}
 */
function aw_org_people_backfill( $apply = false ) {
	$todo = array();
	$old  = get_posts(
		array(
			'post_type'      => 'aw_claim',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => 'aw_c_status', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => 'approved', // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	foreach ( $old as $id ) {
		$todo[] = array(
			'claim' => 'old #' . $id,
			'lid'   => (int) get_post_meta( $id, 'aw_c_listing', true ),
			'run'   => fn( $lid, $go ) => aw_org_people_from_claim( $id, $lid, $go ),
		);
	}
	if ( function_exists( 'aw_claim_ff_id' ) && function_exists( 'aw_claim_entry' ) && aw_claim_ff_id() ) {
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}fluentform_submissions WHERE form_id = %d ORDER BY id", aw_claim_ff_id() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( $ids as $eid ) {
			$c = aw_claim_entry( (int) $eid );
			if ( ! $c || 'approved' !== ( $c['review']['status'] ?? '' ) ) {
				continue;
			}
			$todo[] = array(
				'claim' => '' !== $c['ref'] ? $c['ref'] : 'entry #' . $c['id'],
				'lid'   => (int) $c['listing'],
				'run'   => fn( $lid, $go ) => aw_org_people_link( $lid, $c['email'], $c['role'], $c['show'], $go ),
			);
		}
	}
	$counts = array();
	$rows   = array();
	foreach ( $todo as $t ) {
		$status            = ( $t['run'] )( $t['lid'], $apply );
		$counts[ $status ] = ( $counts[ $status ] ?? 0 ) + 1;
		$rows[]            = array(
			'claim'   => $t['claim'],
			'listing' => $t['lid'],
			'status'  => $status,
		);
	}
	ksort( $counts );
	return array(
		'counts' => $counts,
		'rows'   => $rows,
	);
}

/*
 * The same backfill from a signed-in administrator's browser (no SSH write): GET /wp-json/aw/v1/claim-people is the dry
 * run, POST with apply=1 writes the links. Counts and claim references only; no email or name.
 */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'aw/v1',
			'/claim-people',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
				'callback'            => function ( WP_REST_Request $req ) {
					$apply = 'POST' === $req->get_method() && rest_sanitize_boolean( $req->get_param( 'apply' ) );
					return rest_ensure_response( array( 'applied' => $apply ) + aw_org_people_backfill( $apply ) );
				},
			)
		);
	}
);

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/*
	 * wp aw claim-people [--apply]: link the claimants of past approved claims to their listings. A dry run unless
	 * --apply; prints one row per claim (reference, listing, what happens) and the counts. No email or name is printed.
	 */
	WP_CLI::add_command(
		'aw claim-people',
		function ( $args, $assoc ) {
			$apply = ! empty( $assoc['apply'] );
			$out   = aw_org_people_backfill( $apply );
			foreach ( $out['rows'] as $r ) {
				WP_CLI::log( $r['claim'] . "\t" . $r['listing'] . "\t" . $r['status'] );
			}
			WP_CLI::success( ( $apply ? 'Applied. ' : 'Dry run, nothing written. ' ) . wp_json_encode( $out['counts'] ) );
		}
	);
}

/* ---------- WP Admin: the Volunteers box on a listing (read-only) ---------- */

add_action(
	'add_meta_boxes_aw_listing',
	function () {
		if ( current_user_can( 'edit_aw_volunteers' ) ) {
			add_meta_box( 'aw_org_people', 'Volunteers', 'aw_org_people_box', 'aw_listing', 'normal', 'low' );
		}
	}
);

/**
 * The box: approved and waiting contacts for this listing.
 *
 * @param WP_Post $post Listing.
 */
function aw_org_people_box( $post ) {
	$link = fn( $id ) => admin_url( 'admin.php?page=fluentcrm-admin#/subscribers/' . (int) $id );
	foreach ( array( 'org-approved' => 'Approved (tag org-approved)', 'org-pending' => 'Waiting (tag org-pending): approve by swapping the tag for org-approved in FluentCRM' ) as $tag => $head ) {
		$rows = aw_org_people_crm( $post->ID, $tag );
		echo '<p><strong>' . esc_html( $head ) . '</strong></p>';
		if ( ! $rows ) {
			echo '<p class="description">None.</p>';
			continue;
		}
		echo '<ul>';
		foreach ( $rows as $r ) {
			echo '<li><a href="' . esc_url( $link( $r['id'] ) ) . '">' . esc_html( '' !== $r['name'] ? $r['name'] : '(no name)' ) . '</a> · ' . esc_html( $r['show'] ? 'name shown on the page' : 'name not shown' ) . '</li>';
		}
		echo '</ul>';
	}
	echo '<p class="description">From the Volunteer form: contacts whose organisation name matches this listing (or whose "organisation\'s listing ID" is ' . (int) $post->ID . '). Email and phone are never shown.</p>';
}

/*
 * Volunteer form #5, Organisation from the Directory (owner, 31 Oct 2026): typing in "Organisation name" lists up to 8
 * published listings whose name matches, as "name · district, state" (aw.js). Picking one saves the listing in
 * org_listing (FluentCRM vol_org_listing) and its name in org_name; "My organisation isn't listed" keeps a typed name.
 * Public and read-only, like the PIN lookup (places.php), with a per-connection limit.
 */
function aw_org_search_public() {
	nocache_headers();
	$key = 'aw_orgq_rl_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$n   = (int) get_transient( $key );
	if ( $n >= 120 ) {
		wp_send_json_error( 'Too many tries. Wait a minute, then try again.' );
	}
	set_transient( $key, $n + 1, 10 * MINUTE_IN_SECONDS );
	$q = trim( sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( mb_strlen( $q ) < 3 ) {
		wp_send_json_success( array() );
	}
	$ids = get_posts( array( 'post_type' => 'aw_listing', 'post_status' => 'publish', 's' => $q, 'posts_per_page' => 8, 'fields' => 'ids', 'orderby' => 'relevance', 'post__not_in' => function_exists( 'kits_preview_only_ids' ) ? (array) kits_preview_only_ids() : array() ) );
	$out = array();
	foreach ( $ids as $id ) {
		list( $state, $district ) = function_exists( 'aw_item_place_names' ) ? aw_item_place_names( $id ) : array( '', '' );
		$out[] = array( 'id' => (int) $id, 'name' => html_entity_decode( get_the_title( $id ), ENT_QUOTES ), 'place' => implode( ', ', array_filter( array( $district, $state ) ) ) );
	}
	wp_send_json_success( $out );
}
add_action( 'wp_ajax_nopriv_aw_org_search', 'aw_org_search_public' );
add_action( 'wp_ajax_aw_org_search', 'aw_org_search_public' );

/** A picked organisation: the published listing's ID, or 0. */
function aw_org_listing_ok( $id ) {
	$id = absint( $id );
	return $id && 'aw_listing' === get_post_type( $id ) && 'publish' === get_post_status( $id ) ? $id : 0;
}

