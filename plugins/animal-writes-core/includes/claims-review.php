<?php
/**
 * Claim form #6 (owner, 31 Oct 2026): the listing's details pre-filled in the form, and the review of a claim field by
 * field. Nothing a claimant sends is published until it is approved here.
 *
 * - Pre-fill: one fluentform/rendering_field_data_* filter fills every l_* field from the listing (public data only)
 *   and names the listing in "{Listing}" labels.
 * - Directory → Claims: the claim entries (Fluent Forms); a claim's screen shows now → proposed for the fields that
 *   differ, each with its own tick. Approve writes the ticked values to the listing, each logged in the enrichment
 *   change log (source "Claimed by organisation", today) with Undo there, and marks the listing claimed (aw_verified,
 *   aw_verified_on). Opening hours are shown, not written: they are entered in the listing's Opening hours list.
 * - "Show my name on this listing" = Yes and approved: the claimant's FluentCRM contact gets org-approved and the
 *   listing (vol_org_listing, vol_org_show, vol_org_role), so [aw_org_people] shows their name and role.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The claim form's listing fields: form key => label, where it lives (meta key, or phones | hours | services | about),
 * the enrichment field it is logged as, and whether it is public (pre-filled) or kept for the team only.
 */
const AW_CLAIM_L = array(
	'l_email'      => array( 'Listing email', 'aw_email', 'email', true ),
	'phones'       => array( 'Phone numbers', 'phones', 'phone', true ),
	'l_area'       => array( 'Area or landmark', 'aw_area', 'area', true ),
	'l_address'    => array( 'Full address', 'aw_address', 'address', true ),
	'l_maps'       => array( 'Google Maps link', 'aw_maps_url', 'maps_url', true ),
	'l_hours'      => array( 'Opening hours', 'hours', '', true ),
	'l_about'      => array( 'About', 'about', 'about', true ),
	'l_aliases'    => array( 'Also known as', 'aw_aliases', 'aliases', true ),
	'l_founded'    => array( 'Year established', 'aw_founded', 'founded', true ),
	'l_head'       => array( 'Head of the organisation', 'aw_head', 'head', true ),
	'l_animals'    => array( 'Animals helped', 'aw_animals', 'animals', true ),
	'l_areas'      => array( 'Areas served', 'aw_areas_served', 'areas_served', true ),
	'l_services'   => array( 'Services', 'services', 'services', true ),
	'l_languages'  => array( 'Languages', 'aw_languages', 'languages', true ),
	'l_website'    => array( 'Website', 'aw_website', 'website', true ),
	'l_instagram'  => array( 'Instagram', 'aw_instagram', 'instagram', true ),
	'l_facebook'   => array( 'Facebook', 'aw_facebook', 'facebook', true ),
	'l_x'          => array( 'X', 'aw_x', 'x', true ),
	'l_youtube'    => array( 'YouTube', 'aw_youtube', 'youtube', true ),
	'l_linkedin'   => array( 'LinkedIn', 'aw_linkedin', 'linkedin', true ),
	'l_reg'        => array( 'Registration no.', 'aw_registration', 'registration', true ),
	'l_12a'        => array( '12A', 'aw_reg_12a', 'reg_12a', true ),
	'l_80g'        => array( '80G', 'aw_reg_80g', 'reg_80g', true ),
	'l_darpan'     => array( 'NGO Darpan ID', 'aw_darpan_id', 'darpan_id', true ),
	'l_reg_type'   => array( 'Registered as', 'aw_reg_type', 'reg_type', true ),
	'l_reg_auth'   => array( 'Registration authority', 'aw_reg_authority', 'reg_authority', false ),
	'l_reg_act'    => array( 'Registration act', 'aw_reg_act', 'reg_act', false ),
	'l_reg_date'   => array( 'Date of registration', 'aw_reg_date', 'reg_date', true ),
	'l_fcra'       => array( 'FCRA', 'aw_fcra', 'fcra', true ),
	'l_awbi_code'  => array( 'AWBI code', 'aw_awbi_code', 'awbi_code', true ),
	'l_awbi_rec'   => array( 'AWBI recognised on', 'aw_awbi_recognised', 'awbi_recognised', true ),
	'l_awbi_ren'   => array( 'AWBI renewed on', 'aw_awbi_renewed', 'awbi_renewed', true ),
	'l_awbi_valid' => array( 'AWBI valid until', 'aw_awbi_valid', 'awbi_valid', true ),
	'l_donate'     => array( 'Donate link', 'aw_donate_url', 'donate_url', true ),
	'l_volunteer'  => array( 'Volunteer link', 'aw_volunteer_url', 'volunteer_url', true ),
	'l_adopt'      => array( 'Adopt link', 'aw_adopt_url', 'adopt_url', true ),
	'l_upi'        => array( 'UPI ID', 'aw_upi', 'upi', true ),
);

/** The claim form's Fluent Forms ID (0 when it is not set up). */
function aw_claim_ff_id() {
	return function_exists( 'aw_ff_id' ) ? (int) aw_ff_id( 'claim' ) : 0;
}

/** A listing's opening hours as one line, as the form shows them ("Mon 09:00–18:00, Sun closed"). */
function aw_claim_hours_text( $lid ) {
	$out = array();
	foreach ( aw_listing_hours( $lid ) as $r ) {
		$out[] = substr( $r['day'], 0, 3 ) . ' ' . ( $r['closed'] ? 'closed' : $r['open'] . '–' . $r['close'] );
	}
	return implode( ', ', $out );
}

/** The listing's service tags (slugs) from the claim form's list, sorted. */
function aw_claim_service_slugs( $lid ) {
	$slugs = array_values( array_intersect( (array) wp_get_object_terms( $lid, 'post_tag', array( 'fields' => 'slugs' ) ), array_keys( AW_SERVICES ) ) );
	sort( $slugs );
	return $slugs;
}

/** The listing's About text as the form shows it: its body, or its short bio when the body is empty. */
function aw_claim_about( $lid ) {
	$raw  = (string) get_post_field( 'post_content', $lid );
	$body = trim( wp_strip_all_tags( $raw ) );
	// As the listing page shows it (aw_block_listing_about): the short bio when the body is empty or an import's template intro.
	$bio = has_excerpt( $lid ) && ( '' === $body || ( function_exists( 'aw_listing_is_template_intro' ) && aw_listing_is_template_intro( trim( $raw ) ) ) );
	return $bio ? trim( (string) get_post_field( 'post_excerpt', $lid ) ) : $body;
}

/**
 * What the listing has now for one claim field, as text (phones: one "number | label | WhatsApp" line each; services:
 * slugs joined with a comma).
 *
 * @param int    $lid    Listing ID.
 * @param string $key    AW_CLAIM_L key.
 * @param bool   $public True: '' for fields kept for the team only (the form is a public page).
 * @return string
 */
function aw_claim_now( $lid, $key, $public = true ) {
	$def = AW_CLAIM_L[ $key ] ?? null;
	if ( ! $def || ( $public && ! $def[3] ) ) {
		return '';
	}
	switch ( $def[1] ) {
		case 'phones':
			return implode( "\n", array_map( fn( $r ) => $r['number'] . ( '' !== $r['label'] ? ' | ' . $r['label'] : '' ) . ( $r['whatsapp'] ? ' | WhatsApp' : '' ), aw_listing_phones( $lid ) ) );
		case 'hours':
			return aw_claim_hours_text( $lid );
		case 'services':
			return implode( ',', aw_claim_service_slugs( $lid ) );
		case 'about':
			return aw_claim_about( $lid );
		default:
			return trim( (string) get_post_meta( $lid, $def[1], true ) );
	}
}

/* ---------- Pre-fill (b) ---------- */

foreach ( array( 'input_text', 'input_email', 'input_url', 'textarea', 'input_checkbox', 'input_radio', 'select' ) as $aw_claim_el ) {
	add_filter(
		'fluentform/rendering_field_data_' . $aw_claim_el,
		function ( $data, $form ) {
			if ( ! aw_claim_ff_id() || (int) $form->id !== aw_claim_ff_id() ) {
				return $data;
			}
			$listing = function_exists( 'aw_form_page_listing' ) ? aw_form_page_listing() : null;
			$name    = $listing ? wp_strip_all_tags( get_the_title( $listing ) ) : 'this organisation';
			// "{Listing}" in a label or an option (role, consent) names the listing.
			if ( isset( $data['settings']['label'] ) ) {
				$data['settings']['label'] = str_replace( '{Listing}', esc_html( $name ), (string) $data['settings']['label'] );
			}
			foreach ( (array) ( $data['settings']['advanced_options'] ?? array() ) as $i => $o ) {
				$data['settings']['advanced_options'][ $i ]['label'] = str_replace( '{Listing}', esc_html( $name ), (string) ( $o['label'] ?? '' ) );
			}
			$field = (string) ( $data['attributes']['name'] ?? '' );
			if ( ! $listing || 0 !== strpos( $field, 'l_' ) ) {
				return $data;
			}
			$lid = $listing->ID;
			if ( preg_match( '/^l_phone([1-3])(_label|_wa)?$/', $field, $m ) ) {
				$row = aw_listing_phones( $lid )[ (int) $m[1] - 1 ] ?? null;
				if ( $row ) {
					$part                        = $m[2] ?? '';
					$data['attributes']['value'] = '_wa' === $part ? ( $row['whatsapp'] ? array( '1' ) : array() ) : ( '_label' === $part ? $row['label'] : $row['number'] );
				}
				return $data;
			}
			if ( 'l_services' === $field ) {
				$data['attributes']['value'] = aw_claim_service_slugs( $lid );
				return $data;
			}
			if ( isset( AW_CLAIM_L[ $field ] ) ) {
				$data['attributes']['value'] = aw_claim_now( $lid, $field, true );
			}
			return $data;
		},
		20,
		2
	);
}

/* ---------- A claim entry, read ---------- */

/**
 * One claim entry: its data, what it proposes for the listing (AW_CLAIM_L keys => text), the person, uploads, status.
 * Entries from the earlier form (aw_phone, aw_whatsapp, aw_email, aw_website, aw_area, aw_services) are read too.
 *
 * @param int $entry_id Fluent Forms entry ID.
 * @return array|null
 */
function aw_claim_entry( $entry_id ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, form_id, serial_number, response, created_at FROM {$wpdb->prefix}fluentform_submissions WHERE id = %d AND form_id = %d", $entry_id, aw_claim_ff_id() ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	if ( ! $row ) {
		return null;
	}
	$d       = (array) json_decode( (string) $row['response'], true );
	$s       = fn( $k ) => is_array( $d[ $k ] ?? '' ) ? implode( ',', array_map( 'strval', $d[ $k ] ) ) : trim( (string) ( $d[ $k ] ?? '' ) );
	$lid     = absint( $d['listing'] ?? 0 );
	$lid     = $lid && 'aw_listing' === get_post_type( $lid ) ? $lid : 0; // the hidden listing field comes from the page: only a real listing
	$propose = array();
	$legacy  = isset( $d['aw_email'] ) || isset( $d['aw_phone'] ) || isset( $d['aw_services'] );
	if ( $legacy ) { // the form before 31 Oct 2026: only what was typed; an empty box meant "keep"
		foreach ( array( 'aw_email' => 'l_email', 'aw_website' => 'l_website', 'aw_area' => 'l_area' ) as $old => $new ) {
			if ( '' !== $s( $old ) ) {
				$propose[ $new ] = $s( $old );
			}
		}
		if ( '' !== $s( 'aw_services' ) ) {
			$sv = array_intersect( explode( ',', $s( 'aw_services' ) ), array_keys( AW_SERVICES ) );
			sort( $sv );
			$propose['l_services'] = implode( ',', $sv );
		}
		$add = array_filter( array( $s( 'aw_phone' ) => false, $s( 'aw_whatsapp' ) => true ), fn( $k ) => '' !== (string) $k, ARRAY_FILTER_USE_KEY );
		if ( $add && $lid ) {
			$rows = aw_listing_phones( $lid );
			foreach ( $add as $num => $wa ) {
				$rows[] = array( 'number' => (string) $num, 'label' => '', 'whatsapp' => $wa );
			}
			$propose['phones'] = implode( "\n", array_map( fn( $r ) => $r['number'] . ( '' !== $r['label'] ? ' | ' . $r['label'] : '' ) . ( $r['whatsapp'] ? ' | WhatsApp' : '' ), $rows ) );
		}
	} else {
		foreach ( AW_CLAIM_L as $key => $def ) {
			if ( 'phones' === $key ) {
				$lines = array();
				for ( $i = 1; $i <= 3; $i++ ) {
					$num = $s( "l_phone{$i}" );
					if ( '' !== $num ) {
						$lab     = $s( "l_phone{$i}_label" );
						$lines[] = $num . ( '' !== $lab ? ' | ' . $lab : '' ) . ( function_exists( 'aw_ff_ticked' ) && aw_ff_ticked( $d, "l_phone{$i}_wa" ) ? ' | WhatsApp' : '' );
					}
				}
				foreach ( array_slice( $lid ? aw_listing_phones( $lid ) : array(), 3 ) as $r ) { // numbers past the form's three stay as they are
					$lines[] = $r['number'] . ( '' !== $r['label'] ? ' | ' . $r['label'] : '' ) . ( $r['whatsapp'] ? ' | WhatsApp' : '' );
				}
				$propose['phones'] = implode( "\n", $lines );
				continue;
			}
			if ( 'l_services' === $key ) {
				$sv = array_intersect( (array) ( $d['l_services'] ?? array() ), array_keys( AW_SERVICES ) );
				sort( $sv );
				$propose[ $key ] = implode( ',', $sv );
				continue;
			}
			if ( ! $def[3] && '' === $s( $key ) ) {
				continue; // a team-only field the form never showed filled: empty is "nothing said"
			}
			$propose[ $key ] = $s( $key );
		}
	}
	$file = fn( $url ) => preg_match( '/[?&]f=([A-Za-z0-9]{20}\.png)/', (string) $url, $m ) ? $m[1] : '';
	$meta = class_exists( '\FluentForm\App\Helpers\Helper' ) ? \FluentForm\App\Helpers\Helper::getSubmissionMeta( (int) $row['id'], 'aw_claim_review', '' ) : '';
	return array(
		'id'      => (int) $row['id'],
		'ref'     => function_exists( 'aw_ff_ref' ) ? aw_ff_ref( 'claim', (int) $row['id'] ) : '',
		'sent'    => (string) $row['created_at'],
		'listing' => $lid,
		'name'    => $s( 'name' ),
		'role'    => AW_CLAIM_ROLES[ $s( 'role' ) ] ?? $s( 'role' ),
		'email'   => $s( 'email' ),
		'phone'   => $s( 'phone' ),
		'show'    => 'yes' === $s( 'show_on_listing' ),
		'notes'   => $s( 'notes' ),
		'logo'    => $file( $d['logo_token'] ?? '' ),
		'photos'  => array_values( array_filter( array_map( $file, explode( ',', (string) ( $d['photo_tokens'] ?? '' ) ) ) ) ),
		'proof'   => (string) ( $d['proof_token'] ?? '' ),
		'propose' => $propose,
		'review'  => is_array( $meta ) ? $meta : (array) json_decode( (string) $meta, true ), // stored as JSON (aw_claim_ff_decide)
	);
}

/**
 * The fields where the claim differs from the listing now: key => [label, now, proposed]. Compared without regard to
 * spacing; email and links also without regard to case, and links without scheme, www and a closing slash.
 *
 * @param array $c aw_claim_entry().
 * @return array
 */
function aw_claim_changes( $c ) {
	$norm = function ( $v, $loose ) {
		$v = trim( preg_replace( '/\s+/', ' ', (string) $v ) );
		return $loose ? rtrim( preg_replace( '#^https?://(www\.)?#', '', strtolower( $v ) ), '/' ) : $v; // links and email without regard to case
	};
	$out = array();
	foreach ( $c['propose'] as $key => $new ) {
		$now   = aw_claim_now( $c['listing'], $key, false );
		$loose = 'l_email' === $key || 0 === strpos( AW_CLAIM_L[ $key ][1], 'aw_' ) && preg_match( '/url|website|instagram|facebook|_x$|youtube|linkedin/', AW_CLAIM_L[ $key ][1] );
		if ( $norm( $now, $loose ) !== $norm( $new, $loose ) ) {
			$out[ $key ] = array( AW_CLAIM_L[ $key ][0], $now, $new );
		}
	}
	return $out;
}

/* ---------- Directory → Claims ---------- */

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'edit.php?post_type=aw_listing', 'Claims', 'Claims', 'edit_aw_volunteers', 'aw-claims', 'aw_claims_screen' );
	}
);

/** Directory → Claims: the list, or one claim (&entry=). */
function aw_claims_screen() {
	if ( ! current_user_can( 'edit_aw_volunteers' ) ) {
		wp_die( 'Not allowed.', 403 );
	}
	$entry = absint( wp_unslash( $_GET['entry'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification -- read only
	echo '<div class="wrap">';
	if ( ! aw_claim_ff_id() ) {
		echo '<h1>Claims</h1><div class="notice notice-warning"><p>The claim form is not set up (Fluent Forms off, or <code>wp aw ff-form claim &lt;ID&gt;</code>).</p></div></div>';
		return;
	}
	if ( $entry ) {
		aw_claim_review_screen( $entry );
		echo '</div>';
		return;
	}
	echo '<h1>Claims</h1>';
	$done = sanitize_key( wp_unslash( $_GET['done'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( '' !== $done ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( 'approved' === $done ? 'Claim approved: the ticked fields are on the listing (Undo in Sourcing → Enrichment review).' : 'Claim rejected.' ) . '</p></div>';
	}
	global $wpdb;
	$per   = 20;
	$paged = max( 1, absint( wp_unslash( $_GET['paged'] ?? 1 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification -- read only
	$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}fluentform_submissions WHERE form_id = %d AND status <> 'trashed'", aw_claim_ff_id() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}fluentform_submissions WHERE form_id = %d AND status <> 'trashed' ORDER BY id DESC LIMIT %d OFFSET %d", aw_claim_ff_id(), $per, ( $paged - 1 ) * $per ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$rows  = array();
	foreach ( $ids as $id ) {
		$c = aw_claim_entry( (int) $id );
		if ( ! $c ) {
			continue;
		}
		$st     = (string) ( $c['review']['status'] ?? 'pending' );
		$url    = add_query_arg( array( 'post_type' => 'aw_listing', 'page' => 'aw-claims', 'entry' => $c['id'] ), admin_url( 'edit.php' ) );
		$rows[] = array(
			'listing' => '<strong><a href="' . esc_url( $url ) . '">' . esc_html( $c['listing'] ? get_the_title( $c['listing'] ) : '(no listing)' ) . '</a></strong>',
			'from'    => esc_html( $c['name'] . ( '' !== $c['role'] ? ', ' . $c['role'] : '' ) ),
			'changes' => esc_html( (string) count( aw_claim_changes( $c ) ) ),
			'status'  => esc_html( ucfirst( $st ) ),
			'ref'     => esc_html( $c['ref'] ),
			'sent'    => esc_html( mysql2date( 'j M Y', $c['sent'] ) ),
		);
	}
	aw_core_simple_table( 'aw-claims', array( 'listing' => 'Listing', 'from' => 'From', 'changes' => 'Changes', 'status' => 'Status', 'ref' => 'Reference', 'sent' => 'Sent' ), $rows, array( 'per_page' => $per, 'total' => $total, 'empty' => 'No claims yet.' ) );
	echo '</div>';
}

/** One claim: who sent it, the changed fields with a tick each, uploads, and Approve / Reject. */
function aw_claim_review_screen( $entry_id ) {
	$c = aw_claim_entry( $entry_id );
	if ( ! $c ) {
		echo '<h1>Claim</h1><p>Not found.</p>';
		return;
	}
	$lid    = $c['listing'];
	$status = (string) ( $c['review']['status'] ?? 'pending' );
	$back   = add_query_arg( array( 'post_type' => 'aw_listing', 'page' => 'aw-claims' ), admin_url( 'edit.php' ) );
	echo '<h1>Claim: ' . esc_html( $lid ? get_the_title( $lid ) : '(no listing)' ) . ' <span class="subtitle">' . esc_html( $c['ref'] ) . '</span></h1>';
	echo '<p><a href="' . esc_url( $back ) . '">All claims</a>' . ( $lid ? ' · <a href="' . esc_url( (string) get_edit_post_link( $lid ) ) . '">Edit listing</a> · <a href="' . esc_url( (string) get_permalink( $lid ) ) . '" target="_blank">View listing</a>' : '' ) . '</p>';
	$who = array(
		'From'                 => $c['name'] . ( '' !== $c['role'] ? ', ' . $c['role'] : '' ),
		'Email'                => $c['email'],
		'Phone'                => $c['phone'],
		'Show name on listing' => $c['show'] ? 'Yes' : 'No',
		'Anything else'        => $c['notes'],
		'Sent'                 => mysql2date( 'j M Y, H:i', $c['sent'] ),
		'Status'               => ucfirst( $status ),
	);
	echo '<table class="form-table" role="presentation"><tbody>';
	foreach ( $who as $k => $v ) {
		echo '<tr><th scope="row">' . esc_html( $k ) . '</th><td>' . nl2br( esc_html( '' !== (string) $v ? (string) $v : '—' ) ) . '</td></tr>';
	}
	echo '<tr><th scope="row">Proof</th><td>' . ( '' !== $c['proof'] ? '<a href="' . esc_url( $c['proof'] ) . '" target="_blank">Open the document</a>' : '—' ) . '</td></tr>';
	echo '</tbody></table>';

	$changes = aw_claim_changes( $c );
	$pending = 'pending' === $status && $lid;
	echo '<h2>Changes</h2>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'aw_claim_ff_decide_' . $c['id'] );
	echo '<input type="hidden" name="action" value="aw_claim_ff_decide"><input type="hidden" name="entry" value="' . (int) $c['id'] . '">';
	$rows = array();
	$file = fn( $f ) => add_query_arg( array( 'action' => 'aw_ff_file', 'f' => $f ), admin_url( 'admin-post.php' ) );
	foreach ( $changes as $key => $ch ) {
		$hand   = 'l_hours' === $key; // entered by hand in the listing's Opening hours
		$rows[] = array(
			'cb'    => $pending && ! $hand ? '<input type="checkbox" name="fields[]" value="' . esc_attr( $key ) . '" checked>' : '',
			'field' => esc_html( $ch[0] ) . ( $hand ? '<br><span class="description">Enter by hand in the listing\'s Opening hours.</span>' : '' ),
			'now'   => nl2br( esc_html( '' !== $ch[1] ? $ch[1] : '—' ) ),
			'new'   => nl2br( esc_html( '' !== $ch[2] ? $ch[2] : '(remove)' ) ),
		);
	}
	if ( '' !== $c['logo'] ) {
		$rows[] = array(
			'cb'    => $pending ? '<input type="checkbox" name="fields[]" value="logo" checked>' : '',
			'field' => 'Logo',
			'now'   => $lid && has_post_thumbnail( $lid ) ? get_the_post_thumbnail( $lid, array( 80, 80 ) ) : '—',
			'new'   => '<img src="' . esc_url( $file( $c['logo'] ) ) . '" alt="" width="80" height="80" style="object-fit:contain">',
		);
	}
	if ( $c['photos'] ) {
		$rows[] = array(
			'cb'    => $pending ? '<input type="checkbox" name="fields[]" value="photos" checked>' : '',
			'field' => 'Photos (added before the current ones, 6 at most)',
			'now'   => esc_html( (string) count( array_filter( explode( ',', (string) get_post_meta( $lid, 'aw_gallery', true ) ) ) ) . ' now' ),
			'new'   => implode( ' ', array_map( fn( $f ) => '<img src="' . esc_url( $file( $f ) ) . '" alt="" width="80" height="80" style="object-fit:cover">', $c['photos'] ) ),
		);
	}
	aw_core_simple_table( 'aw-claim-changes', array( 'cb' => '<span class="screen-reader-text">Approve</span>', 'field' => 'Field', 'now' => 'Now', 'new' => 'Proposed' ), $rows, array( 'primary' => 'field', 'empty' => 'Nothing differs from the listing.' ) );
	if ( $pending ) {
		echo '<p class="description">Approve writes the ticked fields to the listing (source "Claimed by organisation", today) and marks it claimed. Each field can be undone in Sourcing → Enrichment review.</p>';
		echo '<p>';
		submit_button( 'Approve ticked fields', 'primary', 'do_approve', false );
		echo ' ';
		submit_button( 'Reject', 'secondary', 'do_reject', false );
		echo '</p>';
	} elseif ( ! empty( $c['review']['fields'] ) ) {
		echo '<p>Approved fields: ' . esc_html( implode( ', ', (array) $c['review']['fields'] ) ) . '.</p>';
	}
	echo '</form>';
}

/** A claimed value cleaned for its listing field ('' when it cannot be used). */
function aw_claim_clean( $meta_key, $v ) {
	$v = trim( (string) $v );
	switch ( $meta_key ) {
		case 'aw_email':
			return (string) sanitize_email( $v );
		case 'aw_website':
		case 'aw_maps_url':
		case 'aw_donate_url':
		case 'aw_volunteer_url':
		case 'aw_adopt_url':
			return (string) esc_url_raw( $v );
		case 'aw_instagram':
		case 'aw_facebook':
		case 'aw_x':
		case 'aw_youtube':
		case 'aw_linkedin':
			if ( preg_match( '/^@?([A-Za-z0-9_.\-]{2,100})$/', $v, $h ) && false === strpos( $h[1], '.' ) || preg_match( '/^@([A-Za-z0-9_.\-]{2,100})$/', $v, $h ) ) { // a handle, not a link
				$base = array( 'aw_instagram' => 'https://www.instagram.com/%s/', 'aw_facebook' => 'https://www.facebook.com/%s', 'aw_x' => 'https://x.com/%s', 'aw_youtube' => 'https://www.youtube.com/@%s', 'aw_linkedin' => 'https://www.linkedin.com/company/%s' )[ $meta_key ];
				return sprintf( $base, rawurlencode( $h[1] ) );
			}
			return (string) esc_url_raw( $v );
		case 'aw_upi':
			return preg_match( '/^[\w.\-]{2,256}@[a-zA-Z]{2,64}$/', $v ) ? $v : '';
		case 'aw_founded':
			return preg_match( '/^(18|19|20)\d{2}$/', $v ) ? $v : '';
		case 'aw_reg_date':
		case 'aw_awbi_recognised':
		case 'aw_awbi_renewed':
		case 'aw_awbi_valid':
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : '';
		default:
			return sanitize_text_field( $v );
	}
}

/** A private upload (claim logo or photo) into the media library for the listing; attachment ID or 0. */
function aw_claim_sideload( $file, $lid, $title ) {
	$path = aw_private_dir() . '/' . $file;
	if ( ! preg_match( '/^[A-Za-z0-9]{20}\.png$/', (string) $file ) || ! is_file( $path ) ) {
		return 0;
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$tmp = wp_tempnam( $file );
	if ( ! $tmp || ! copy( $path, $tmp ) ) {
		return 0;
	}
	$att = media_handle_sideload( array( 'name' => sanitize_title( get_the_title( $lid ) ) . '-' . wp_generate_password( 4, false, false ) . '.png', 'tmp_name' => $tmp ), $lid, $title );
	if ( is_wp_error( $att ) ) {
		wp_delete_file( $tmp );
		return 0;
	}
	update_post_meta( $att, '_wp_attachment_image_alt', $title );
	return (int) $att;
}

/**
 * Write one approved field to the listing, logged first in the enrichment change log (with what was there, for Undo).
 *
 * @param array  $c   aw_claim_entry().
 * @param string $key AW_CLAIM_L key, logo or photos.
 * @return bool
 */
function aw_claim_apply_field( $c, $key ) {
	$lid = $c['listing'];
	$src = array( array( 'key' => 'claim', 'name' => 'Claimed by organisation', 'url' => '', 'fetched_at' => wp_date( 'c' ) ) );
	$on   = function_exists( 'aw_enrich_add_row' ) && function_exists( 'aw_enrich_snapshot' ) && function_exists( 'aw_enrich_install' );
	$snap = fn( $field ) => $on ? aw_enrich_snapshot( $lid, $field ) : null;
	$log  = function ( $field, $value, $old = null ) use ( $lid, $src, $c, $on ) {
		if ( $on ) {
			aw_enrich_install();
			aw_enrich_add_row( $lid, $field, $value, null !== $old ? $old : aw_enrich_snapshot( $lid, $field ), $src, 'high', 'approved', 'claim-' . $c['id'], 'Claimed by organisation' );
		}
	};
	if ( 'logo' === $key ) {
		$old = $snap( 'logo' );
		$att = aw_claim_sideload( $c['logo'], $lid, get_the_title( $lid ) . ' logo' );
		if ( ! $att || ! set_post_thumbnail( $lid, $att ) ) {
			return false;
		}
		$log( 'logo', 'claim upload', $old ); // logged once it worked, with what was there before
		return true;
	}
	if ( 'photos' === $key ) {
		$old = $snap( 'photos' );
		$ids = array();
		foreach ( array_slice( $c['photos'], 0, 5 ) as $f ) {
			$att = aw_claim_sideload( $f, $lid, get_the_title( $lid ) );
			if ( $att ) {
				$ids[] = $att;
			}
		}
		if ( ! $ids ) {
			return false;
		}
		$have = array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $lid, 'aw_gallery', true ) ) ) );
		update_post_meta( $lid, 'aw_gallery', implode( ',', array_slice( array_merge( $ids, $have ), 0, 6 ) ) );
		$log( 'photos', 'claim upload', $old );
		return true;
	}
	$def = AW_CLAIM_L[ $key ] ?? null;
	if ( ! $def || 'l_hours' === $key || ! array_key_exists( $key, $c['propose'] ) ) {
		return false;
	}
	$new = (string) $c['propose'][ $key ];
	switch ( $def[1] ) {
		case 'phones':
			$rows = array();
			foreach ( array_filter( explode( "\n", $new ) ) as $line ) {
				$p      = array_map( 'trim', explode( '|', $line ) );
				$rows[] = array( 'number' => sanitize_text_field( $p[0] ), 'label' => sanitize_text_field( 'WhatsApp' !== ( $p[1] ?? '' ) ? ( $p[1] ?? '' ) : '' ), 'whatsapp' => in_array( 'WhatsApp', $p, true ) );
			}
			$log( 'phone', wp_list_pluck( $rows, 'number' ) );
			aw_set_listing_list( $lid, 'aw_phones', $rows );
			return true;
		case 'services':
			$log( 'services', array_filter( explode( ',', $new ) ) );
			aw_set_item_tags( $lid, array_filter( explode( ',', $new ) ), aw_service_slugs() );
			update_post_meta( $lid, 'aw_services_source', 'organisation' );
			update_post_meta( $lid, 'aw_services_on', wp_date( 'Y-m-d' ) );
			return true;
		case 'about':
			$log( 'about', $new );
			wp_update_post( array( 'ID' => $lid, 'post_content' => wpautop( sanitize_textarea_field( $new ) ) ) );
			return true;
		default:
			$val = aw_claim_clean( $def[1], $new );
			if ( '' === $val && '' !== $new ) {
				return false; // not usable for this field (a link, a date or a UPI ID that does not match)
			}
			$log( $def[2], $val ); // the snapshot keeps a second email or website too, for Undo
			'' === $val ? delete_post_meta( $lid, $def[1] ) : update_post_meta( $lid, $def[1], $val );
			$alt = array( 'aw_email' => 'aw_email_alt', 'aw_website' => 'aw_website_alt' )[ $def[1] ] ?? '';
			if ( '' !== $alt ) {
				delete_post_meta( $lid, $alt ); // the organisation has given its own: an unverified second value goes, as for earlier claims
			}
			return true;
	}
}

add_action(
	'admin_post_aw_claim_ff_decide',
	function () {
		$id = absint( wp_unslash( $_POST['entry'] ?? 0 ) );
		if ( ! current_user_can( 'edit_aw_volunteers' ) || ! check_admin_referer( 'aw_claim_ff_decide_' . $id ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$c = aw_claim_entry( $id );
		if ( ! $c || ! $c['listing'] || 'pending' !== (string) ( $c['review']['status'] ?? 'pending' ) ) {
			wp_die( 'This claim was already decided, or its listing is missing.', 400 );
		}
		$lid    = $c['listing'];
		$review = array( 'by' => get_current_user_id(), 'at' => current_time( 'mysql' ) );
		if ( isset( $_POST['do_approve'] ) ) {
			$want = array_map( 'sanitize_key', (array) wp_unslash( $_POST['fields'] ?? array() ) );
			$ok   = array();
			foreach ( $want as $key ) {
				if ( aw_claim_apply_field( $c, $key ) ) {
					$ok[] = $key;
				}
			}
			delete_post_meta( $lid, 'aw_unverified' ); // the organisation has spoken: nothing on the listing is "unverified" any more
			update_post_meta( $lid, 'aw_verified', true );
			update_post_meta( $lid, 'aw_verified_on', wp_date( 'Y-m-d' ) );
			update_post_meta( $lid, 'aw_needs_reverify', false );
			delete_transient( 'aw_verified_count' );
			if ( $c['show'] && function_exists( 'aw_crm_on' ) && aw_crm_on() && is_email( $c['email'] ) ) { // their name and role on the listing
				$contact = FluentCrmApi( 'contacts' )->getContact( strtolower( $c['email'] ) );
				if ( $contact ) {
					$contact->attachTags( aw_crm_tag_ids( array( 'org-approved' ) ) );
					$contact->syncCustomFieldValues( array( 'vol_org_listing' => (string) $lid, 'vol_org_show' => '1', 'vol_organisation' => get_the_title( $lid ), 'vol_org_role' => $c['role'] ), false );
				}
			}
			if ( function_exists( 'aw_enrich_after_change' ) ) {
				aw_enrich_after_change( $lid );
			}
			$review += array( 'status' => 'approved', 'fields' => $ok );
		} else {
			$review += array( 'status' => 'rejected' );
		}
		\FluentForm\App\Helpers\Helper::setSubmissionMeta( $id, 'aw_claim_review', wp_json_encode( $review ), aw_claim_ff_id() );
		wp_safe_redirect( add_query_arg( array( 'post_type' => 'aw_listing', 'page' => 'aw-claims', 'done' => $review['status'] ), admin_url( 'edit.php' ) ) );
		exit;
	}
);
