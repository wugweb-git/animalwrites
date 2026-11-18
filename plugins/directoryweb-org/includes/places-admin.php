<?php
/**
 * Place fields on Directory listings, inside the Address and place box (DIRECTORYWEB_PLACE_EDIT_TYPES). Other place types (DIRECTORYWEB_PLACE_TYPES in
 * places.php) keep their stored place, found by auto-detect or set before; it is still read and counted.
 * PIN code → State and District filled from India Post, matched to the LGD file; State → District dropdowns built from the file when there is no PIN or India Post is down.
 * There is no place list in WP Admin and no way to type a place that is not in LGD. Codes that are not in the file
 * are not saved (a notice says so) and the item keeps its previous place.
 *
 * @package directoryweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Post types whose edit screen shows the Place fields (no side box: they are rendered in the Address and place box). */
const DIRECTORYWEB_PLACE_EDIT_TYPES = array( 'directoryweb_listing' );

/** The box. */
function directoryweb_place_box( $post ) {
	list( $states, $districts ) = directoryweb_item_codes( $post->ID );
	$rows = array();
	foreach ( $districts as $c ) {
		$d = directoryweb_place_district( $c );
		if ( $d ) {
			$rows[ $d->state ] = $rows[ $d->state ] ?? array();
			$rows[ $d->state ][] = $d->code;
		}
	}
	foreach ( $states as $c ) {
		if ( ! isset( $rows[ $c ] ) ) {
			$rows[ $c ] = array( 0 );
		}
	}
	$pairs = array();
	foreach ( $rows as $s => $ds ) {
		foreach ( $ds as $d ) {
			$pairs[] = array( (int) $s, (int) $d );
		}
	}
	$first = $pairs ? array_shift( $pairs ) : array( 0, 0 );
	wp_nonce_field( 'directoryweb_place_save', 'directoryweb_place_nonce' );
	$pin  = (string) get_post_meta( $post->ID, 'directoryweb_pincode', true );
	$city = (string) get_post_meta( $post->ID, 'directoryweb_place_city', true );
	echo '<p><label for="directoryweb-pin"><strong>PIN code</strong></label><br><input type="text" id="directoryweb-pin" name="directoryweb_pincode" value="' . esc_attr( $pin ) . '" inputmode="numeric" pattern="[1-9][0-9]{5}" maxlength="6" placeholder="686001" style="width:8em"> <button type="button" class="button" id="directoryweb-pin-go">Fill in</button><br><span id="directoryweb-pin-msg" class="description" aria-live="polite">Fills State and District from India Post.</span></p>';
	echo '<p><label for="directoryweb-state"><strong>State</strong></label><br><select id="directoryweb-state" name="directoryweb_state_code" style="width:100%"><option value="0">Choose a state</option>';
	foreach ( directoryweb_place_states() as $s ) {
		echo '<option value="' . (int) $s->code . '"' . selected( $first[0], $s->code, false ) . '>' . esc_html( $s->name ) . '</option>';
	}
	echo '</select></p>';
	echo '<p><label for="directoryweb-district"><strong>District</strong></label><br><select id="directoryweb-district" name="directoryweb_district_code" style="width:100%"><option value="0">' . ( $first[0] ? 'Whole state' : 'Choose a state first' ) . '</option>';
	foreach ( directoryweb_place_states() as $s ) {
		echo '<optgroup label="' . esc_attr( $s->name ) . '" data-state="' . (int) $s->code . '"' . ( (int) $first[0] === $s->code ? '' : ' hidden disabled' ) . '>';
		foreach ( directoryweb_place_districts( $s->code ) as $d ) {
			echo '<option value="' . (int) $d->code . '"' . selected( $first[1], $d->code, false ) . '>' . esc_html( $d->name ) . '</option>';
		}
		echo '</optgroup>';
	}
	echo '</select></p>';
	if ( $pairs ) {
		echo '<p><strong>Also</strong></p><ul style="margin-top:0">';
		foreach ( $pairs as $p ) {
			$pl = $p[1] ? directoryweb_place_district( $p[1] ) : directoryweb_place_state( $p[0] );
			echo '<li><label><input type="checkbox" name="directoryweb_place_more[]" value="' . esc_attr( $p[0] . ':' . $p[1] ) . '" checked> ' . esc_html( $pl ? ( $p[1] ? $pl->name . ', ' . directoryweb_place_state( $p[0] )->name : $pl->name ) : '' ) . '</label></li>';
		}
		echo '</ul><p class="description">Untick to remove.</p>';
	}
	echo '<p><label for="directoryweb-city"><strong>Town or area</strong> <span class="description">(optional, shown only)</span></label><br><input type="text" id="directoryweb-city" name="directoryweb_place_city" value="' . esc_attr( $city ) . '" maxlength="80" style="width:100%" placeholder="Kottayam town"></p>';
	echo '<p class="description">States and districts: Local Government Directory, ' . esc_html( directoryweb_lgd()['version'] ) . '.</p>';
}

// On a listing the box is inside SCF's "Address and place" group (a message field); same markup, nonce and save.
add_action(
	'acf/render_field/key=field_aw_listing_msg_place',
	function () {
		$post = get_post();
		if ( $post && 'directoryweb_listing' === $post->post_type ) {
			directoryweb_place_box( $post );
		}
	}
);

/** Save: codes must be in the LGD file, a district inside its state, a PIN 6 digits. Otherwise nothing changes. */
add_action(
	'save_post',
	function ( $id, $post ) {
		if ( ! in_array( $post->post_type, DIRECTORYWEB_PLACE_TYPES, true ) || ! isset( $_POST['directoryweb_place_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['directoryweb_place_nonce'] ), 'directoryweb_place_save' ) || ! current_user_can( 'edit_post', $id ) || wp_is_post_revision( $id ) ) {
			return;
		}
		$state    = absint( $_POST['directoryweb_state_code'] ?? 0 );
		$district = absint( $_POST['directoryweb_district_code'] ?? 0 );
		$places   = $state ? array( array( $state, $district ) ) : array();
		foreach ( (array) wp_unslash( $_POST['directoryweb_place_more'] ?? array() ) as $more ) {
			$parts = array_map( 'absint', explode( ':', (string) $more ) );
			if ( ! empty( $parts[0] ) ) {
				$places[] = array( $parts[0], $parts[1] ?? 0 );
			}
		}
		$pin    = preg_replace( '/\D/', '', (string) wp_unslash( $_POST['directoryweb_pincode'] ?? '' ) );
		$city   = sanitize_text_field( wp_unslash( $_POST['directoryweb_place_city'] ?? '' ) );
		$before = directoryweb_item_codes( $id );
		if ( directoryweb_set_item_places( $id, $places, $pin, $city ) ) {
			if ( directoryweb_item_codes( $id ) !== $before ) { // The box posts the place it loaded with: only a change is an editor's choice.
				update_post_meta( $id, 'directoryweb_place_source', 'editor' );
				delete_post_meta( $id, 'directoryweb_place_auto' );
			}
		} else {
			set_transient( 'directoryweb_place_error_' . get_current_user_id(), 'Place not saved: choose the state and district from the lists, and give a 6-digit PIN code (or leave it empty). The previous place is kept.', 60 );
		}
	},
	20,
	2
);

add_action(
	'admin_notices',
	function () {
		$msg = get_transient( 'directoryweb_place_error_' . get_current_user_id() );
		if ( $msg ) {
			delete_transient( 'directoryweb_place_error_' . get_current_user_id() );
			wp_admin_notice( esc_html( $msg ), array( 'type' => 'error' ) );
		}
	}
);

/** PIN lookup for the Place box (editors): see directoryweb_pin_lookup() in places.php. */
add_action(
	'wp_ajax_aw_pin_lookup',
	function () {
		check_ajax_referer( 'directoryweb_place_save', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Not allowed.' );
		}
		$r = directoryweb_pin_lookup( (string) wp_unslash( $_GET['pin'] ?? '' ) );
		is_array( $r ) ? wp_send_json_success( $r ) : wp_send_json_error( $r );
	}
);

add_action(
	'admin_footer-post.php',
	'directoryweb_place_box_js'
);
add_action(
	'admin_footer-post-new.php',
	'directoryweb_place_box_js'
);

/** District follows State; "Fill in" asks India Post. */
function directoryweb_place_box_js() {
	if ( ! in_array( get_post_type(), DIRECTORYWEB_PLACE_EDIT_TYPES, true ) ) {
		return;
	}
	?>
<script>
(function () {
	var st = document.getElementById('directoryweb-state'), di = document.getElementById('directoryweb-district');
	if (!st || !di) { return; }
	function show() {
		var v = st.value;
		di.querySelectorAll('optgroup').forEach(function (g) { var on = g.dataset.state === v; g.hidden = !on; g.disabled = !on; });
		di.options[0].textContent = v !== '0' ? 'Whole state' : 'Choose a state first';
		var cur = di.selectedOptions[0];
		if (cur && cur.parentNode.tagName === 'OPTGROUP' && cur.parentNode.dataset.state !== v) { di.value = '0'; }
	}
	st.addEventListener('change', show);
	var go = document.getElementById('directoryweb-pin-go'), pin = document.getElementById('directoryweb-pin'), msg = document.getElementById('directoryweb-pin-msg');
	function look() {
		msg.textContent = 'Looking up…';
		var u = ajaxurl + '?action=directoryweb_pin_lookup&pin=' + encodeURIComponent(pin.value) + '&nonce=' + encodeURIComponent(document.getElementById('directoryweb_place_nonce').value);
		fetch(u, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (r) {
			if (!r.success) { msg.textContent = r.data; return; }
			st.value = String(r.data.state); show();
			di.value = String(r.data.district || 0);
			msg.textContent = r.data.note || (r.data.district_name + ', ' + r.data.state_name);
		}).catch(function () { msg.textContent = 'India Post did not answer. Choose the state and district below.'; });
	}
	go.addEventListener('click', look);
	pin.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); look(); } });
})();
</script>
	<?php
}
