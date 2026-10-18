<?php
/**
 * Volunteers of an organisation (owner, 18 Oct 2026): people from the volunteers table (volunteers-db.php) linked to a
 * Directory listing. For now every person's role is "Volunteer".
 *
 * - WP Admin → a listing → "Volunteers" box (administrators only, like the volunteers table): search the volunteers by
 *   name or email to add one; a "Show on the page" tick per person (off by default); remove.
 * - An approved claim adds the claimant (claims.php): they are added to the volunteers table if not there yet, linked
 *   privately.
 * - Shown people: their name on the organisation's page under "Volunteers", and in the schema as member (Person).
 *   Email and phone are never shown.
 * Stored on the listing: aw_people (JSON list of {vid, public, role, on, via}) and aw_people_vid (one row per person,
 * to find a person's organisations).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A listing's people.
 *
 * @param int $lid Listing.
 * @return array list of {vid, public, role, on, via}
 */
function aw_org_people( $lid ) {
	$rows = json_decode( (string) get_post_meta( $lid, 'aw_people', true ), true );
	return is_array( $rows ) ? array_values( array_filter( $rows, fn( $r ) => ! empty( $r['vid'] ) ) ) : array();
}

/**
 * Store a listing's people.
 *
 * @param int   $lid  Listing.
 * @param array $rows People.
 */
function aw_org_people_save( $lid, $rows ) {
	$rows = array_values( array_reduce( $rows, function ( $c, $r ) { $c[ (int) $r['vid'] ] = $r; return $c; }, array() ) ); // one row per person
	update_post_meta( $lid, 'aw_people', wp_slash( wp_json_encode( $rows ) ) );
	delete_post_meta( $lid, 'aw_people_vid' );
	foreach ( $rows as $r ) {
		add_post_meta( $lid, 'aw_people_vid', (int) $r['vid'] );
	}
}

/**
 * Add one volunteer to a listing (kept if already there).
 *
 * @param int    $lid    Listing.
 * @param int    $vid    Volunteer (volunteers table id).
 * @param bool   $public Shown on the page.
 * @param string $via    'admin' or 'claim'.
 */
function aw_org_people_add( $lid, $vid, $public = false, $via = 'admin' ) {
	$rows = aw_org_people( $lid );
	foreach ( $rows as $r ) {
		if ( (int) $r['vid'] === (int) $vid ) {
			return;
		}
	}
	$rows[] = array( 'vid' => (int) $vid, 'public' => $public ? 1 : 0, 'role' => 'volunteer', 'on' => wp_date( 'Y-m-d' ), 'via' => $via );
	aw_org_people_save( $lid, $rows );
}

/**
 * Volunteer rows by id.
 *
 * @param int[] $ids Ids.
 * @return array id => row
 */
function aw_org_people_volunteers( $ids ) {
	global $wpdb;
	$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
	if ( ! $ids || ! function_exists( 'aw_vol_table' ) ) {
		return array();
	}
	$t    = aw_vol_table();
	$rows = $wpdb->get_results( "SELECT id, name, email, city_text FROM {$t} WHERE id IN (" . implode( ',', $ids ) . ')', OBJECT_K ); // phpcs:ignore WordPress.DB
	return $rows ? $rows : array();
}

/* ---------- On the page and in the schema ---------- */

/**
 * The shown volunteers' names.
 *
 * @param int $lid Listing.
 * @return string[]
 */
function aw_org_people_public_names( $lid ) {
	$rows = array_filter( aw_org_people( $lid ), fn( $r ) => ! empty( $r['public'] ) );
	$vols = aw_org_people_volunteers( wp_list_pluck( $rows, 'vid' ) );
	$out  = array();
	foreach ( $rows as $r ) {
		if ( isset( $vols[ $r['vid'] ] ) && '' !== trim( $vols[ $r['vid'] ]->name ) ) {
			$out[] = trim( $vols[ $r['vid'] ]->name );
		}
	}
	return $out;
}

/**
 * "Volunteers" on the organisation's page.
 *
 * @param int $lid Listing.
 * @return string
 */
function aw_org_people_html( $lid ) {
	$names = aw_org_people_public_names( $lid );
	if ( ! $names ) {
		return '';
	}
	return '<section class="aw-org-links-group aw-org-people"><h2>Volunteers</h2><ul>' . implode( '', array_map( fn( $n ) => '<li>' . esc_html( $n ) . '</li>', $names ) ) . '</ul></section>';
}

/**
 * schema.org member for a listing: the shown volunteers.
 *
 * @param int $lid Listing.
 * @return array
 */
function aw_org_people_schema( $lid ) {
	return array_map( fn( $n ) => array( '@type' => 'OrganizationRole', 'roleName' => 'Volunteer', 'member' => array( '@type' => 'Person', 'name' => $n ) ), aw_org_people_public_names( $lid ) );
}

/* ---------- WP Admin: the Volunteers box on a listing ---------- */

add_action(
	'add_meta_boxes_aw_listing',
	function () {
		if ( current_user_can( 'edit_aw_volunteers' ) ) {
			add_meta_box( 'aw_org_people', 'Volunteers', 'aw_org_people_box', 'aw_listing', 'normal', 'low' );
		}
	}
);

/**
 * The box.
 *
 * @param WP_Post $post Listing.
 */
function aw_org_people_box( $post ) {
	wp_nonce_field( 'aw_org_people_' . $post->ID, '_aw_org_people' );
	$rows = aw_org_people( $post->ID );
	$vols = aw_org_people_volunteers( wp_list_pluck( $rows, 'vid' ) );
	echo '<table class="widefat striped" style="margin-bottom:8px"><thead><tr><th>Volunteer</th><th>Email</th><th>Added</th><th>Show on the page</th><th>Remove</th></tr></thead><tbody>';
	if ( ! $rows ) {
		echo '<tr><td colspan="5">No volunteers linked yet.</td></tr>';
	}
	foreach ( $rows as $r ) {
		$v = $vols[ $r['vid'] ] ?? null;
		echo '<tr><td>' . esc_html( $v ? $v->name : '(no longer in the volunteers table)' ) . '<input type="hidden" name="aw_people_vid[]" value="' . (int) $r['vid'] . '"></td><td>' . esc_html( $v ? $v->email : '' ) . '</td><td>'
			. esc_html( ( $r['on'] ?? '' ) . ( 'claim' === ( $r['via'] ?? '' ) ? ' (claim)' : '' ) ) . '</td><td><input type="checkbox" name="aw_people_public[]" value="' . (int) $r['vid'] . '"' . checked( ! empty( $r['public'] ), true, false ) . '></td>'
			. '<td><input type="checkbox" name="aw_people_remove[]" value="' . (int) $r['vid'] . '"></td></tr>';
	}
	echo '</tbody></table><p><label for="aw-people-q">Add a volunteer</label><br><input type="search" id="aw-people-q" class="regular-text" placeholder="Name or email" autocomplete="off"> <span class="description">Role: Volunteer. Their name is shown only when "Show on the page" is ticked; email and phone never are.</span></p>'
		. '<ul id="aw-people-hits"></ul><input type="hidden" name="aw_people_add" id="aw-people-add" value="">';
	?>
	<script>
	( function () {
		var q = document.getElementById( 'aw-people-q' ), hits = document.getElementById( 'aw-people-hits' ), add = document.getElementById( 'aw-people-add' ), t;
		q.addEventListener( 'input', function () {
			clearTimeout( t );
			t = setTimeout( function () {
				if ( q.value.length < 2 ) { hits.innerHTML = ''; return; }
				fetch( ajaxurl + '?action=aw_people_search&_wpnonce=<?php echo esc_js( wp_create_nonce( 'aw_people_search' ) ); ?>&q=' + encodeURIComponent( q.value ) ).then( function ( r ) { return r.json(); } ).then( function ( d ) {
					hits.innerHTML = '';
					( d.data || [] ).forEach( function ( v ) {
						var li = document.createElement( 'li' ), b = document.createElement( 'button' );
						b.type = 'button'; b.className = 'button button-small'; b.textContent = 'Add';
						b.onclick = function () { add.value = ( add.value ? add.value + ',' : '' ) + v.id; li.textContent = v.name + ' (' + v.email + ') — added when you update the listing'; };
						li.textContent = v.name + ' · ' + v.email + ( v.city ? ' · ' + v.city : '' ) + ' '; li.appendChild( b ); hits.appendChild( li );
					} );
				} );
			}, 250 );
		} );
	} )();
	</script>
	<?php
}

add_action(
	'wp_ajax_aw_people_search',
	function () {
		check_ajax_referer( 'aw_people_search' );
		if ( ! current_user_can( 'edit_aw_volunteers' ) ) {
			wp_send_json_error( array(), 403 );
		}
		global $wpdb;
		$q    = '%' . $wpdb->esc_like( sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) ) ) . '%';
		$t    = aw_vol_table();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, name, email, city_text AS city FROM {$t} WHERE name LIKE %s OR email LIKE %s ORDER BY name LIMIT 15", $q, $q ) ); // phpcs:ignore WordPress.DB
		wp_send_json_success( $rows );
	}
);

add_action(
	'save_post_aw_listing',
	function ( $lid ) {
		if ( ! isset( $_POST['_aw_org_people'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_aw_org_people'] ) ), 'aw_org_people_' . $lid ) || ! current_user_can( 'edit_aw_volunteers' ) ) {
			return;
		}
		$public = array_map( 'absint', (array) wp_unslash( $_POST['aw_people_public'] ?? array() ) );
		$remove = array_map( 'absint', (array) wp_unslash( $_POST['aw_people_remove'] ?? array() ) );
		$rows   = array();
		foreach ( aw_org_people( $lid ) as $r ) {
			if ( in_array( (int) $r['vid'], $remove, true ) ) {
				continue;
			}
			$r['public'] = in_array( (int) $r['vid'], $public, true ) ? 1 : 0;
			$rows[]      = $r;
		}
		aw_org_people_save( $lid, $rows );
		foreach ( array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['aw_people_add'] ?? '' ) ) ) ) ) as $vid ) {
			if ( aw_org_people_volunteers( array( $vid ) ) ) {
				aw_org_people_add( $lid, $vid );
			}
		}
	}
);

/* ---------- An approved claim adds the claimant ---------- */

/**
 * Add an approved claim's person to the volunteers table (if new) and link them to the listing, not shown.
 *
 * @param int $claim Claim post.
 * @param int $lid   Listing.
 */
function aw_org_people_from_claim( $claim, $lid ) {
	global $wpdb;
	$email = strtolower( trim( (string) get_post_meta( $claim, 'aw_c_email', true ) ) );
	if ( ! is_email( $email ) || ! function_exists( 'aw_vol_upsert' ) ) {
		return;
	}
	$t   = aw_vol_table();
	$vid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE email = %s", $email ) ); // phpcs:ignore WordPress.DB
	if ( ! $vid ) {
		aw_vol_upsert(
			array(
				'name'          => (string) get_post_meta( $claim, 'aw_c_name', true ),
				'email'         => $email,
				'phone'         => (string) get_post_meta( $claim, 'aw_c_phone', true ),
				'source'        => 'claim',
				'consent_scope' => 'listing_claim', // they claimed a listing; not a volunteering sign-up, no newsletter
				'page_source'   => get_permalink( $lid ),
			)
		);
		$vid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE email = %s", $email ) ); // phpcs:ignore WordPress.DB
	}
	if ( $vid ) {
		aw_org_people_add( $lid, $vid, false, 'claim' );
	}
}
