<?php
/**
 * Place box on each item that can have a place (places.php): PIN code → State and District filled from India Post,
 * matched to the LGD file; State → District dropdowns built from the file when there is no PIN or India Post is down.
 * There is no place list in WP Admin and no way to type a place that is not in LGD. Codes that are not in the file
 * are not saved (a notice says so) and the item keeps its previous place.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'add_meta_boxes',
	function () {
		foreach ( AW_PLACE_TYPES as $type ) {
			add_meta_box( 'aw-place', 'Place', 'aw_place_box', $type, 'side', 'default' );
		}
	}
);

/** The box. */
function aw_place_box( $post ) {
	list( $states, $districts ) = aw_item_codes( $post->ID );
	$rows = array();
	foreach ( $districts as $c ) {
		$d = aw_place_district( $c );
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
	wp_nonce_field( 'aw_place_save', 'aw_place_nonce' );
	$pin  = (string) get_post_meta( $post->ID, 'aw_pincode', true );
	$city = (string) get_post_meta( $post->ID, 'aw_place_city', true );
	echo '<p><label for="aw-pin"><strong>PIN code</strong></label><br><input type="text" id="aw-pin" name="aw_pincode" value="' . esc_attr( $pin ) . '" inputmode="numeric" pattern="[1-9][0-9]{5}" maxlength="6" placeholder="686001" style="width:8em"> <button type="button" class="button" id="aw-pin-go">Fill in</button><br><span id="aw-pin-msg" class="description" aria-live="polite">Fills State and District from India Post.</span></p>';
	echo '<p><label for="aw-state"><strong>State</strong></label><br><select id="aw-state" name="aw_state_code" style="width:100%"><option value="0">Choose a state</option>';
	foreach ( aw_place_states() as $s ) {
		echo '<option value="' . (int) $s->code . '"' . selected( $first[0], $s->code, false ) . '>' . esc_html( $s->name ) . '</option>';
	}
	echo '</select></p>';
	echo '<p><label for="aw-district"><strong>District</strong></label><br><select id="aw-district" name="aw_district_code" style="width:100%"><option value="0">' . ( $first[0] ? 'Whole state' : 'Choose a state first' ) . '</option>';
	foreach ( aw_place_states() as $s ) {
		echo '<optgroup label="' . esc_attr( $s->name ) . '" data-state="' . (int) $s->code . '"' . ( (int) $first[0] === $s->code ? '' : ' hidden disabled' ) . '>';
		foreach ( aw_place_districts( $s->code ) as $d ) {
			echo '<option value="' . (int) $d->code . '"' . selected( $first[1], $d->code, false ) . '>' . esc_html( $d->name ) . '</option>';
		}
		echo '</optgroup>';
	}
	echo '</select></p>';
	if ( $pairs ) {
		echo '<p><strong>Also</strong></p><ul style="margin-top:0">';
		foreach ( $pairs as $p ) {
			$pl = $p[1] ? aw_place_district( $p[1] ) : aw_place_state( $p[0] );
			echo '<li><label><input type="checkbox" name="aw_place_more[]" value="' . esc_attr( $p[0] . ':' . $p[1] ) . '" checked> ' . esc_html( $pl ? ( $p[1] ? $pl->name . ', ' . aw_place_state( $p[0] )->name : $pl->name ) : '' ) . '</label></li>';
		}
		echo '</ul><p class="description">Untick to remove.</p>';
	}
	echo '<p><label for="aw-city"><strong>Town or area</strong> <span class="description">(optional, shown only)</span></label><br><input type="text" id="aw-city" name="aw_place_city" value="' . esc_attr( $city ) . '" maxlength="80" style="width:100%" placeholder="Kottayam town"></p>';
	echo '<p class="description">States and districts: Local Government Directory, ' . esc_html( aw_lgd()['version'] ) . '.</p>';
}

/** Save: codes must be in the LGD file, a district inside its state, a PIN 6 digits. Otherwise nothing changes. */
add_action(
	'save_post',
	function ( $id, $post ) {
		if ( ! in_array( $post->post_type, AW_PLACE_TYPES, true ) || ! isset( $_POST['aw_place_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['aw_place_nonce'] ), 'aw_place_save' ) || ! current_user_can( 'edit_post', $id ) || wp_is_post_revision( $id ) ) {
			return;
		}
		$state    = absint( $_POST['aw_state_code'] ?? 0 );
		$district = absint( $_POST['aw_district_code'] ?? 0 );
		$places   = $state ? array( array( $state, $district ) ) : array();
		foreach ( (array) wp_unslash( $_POST['aw_place_more'] ?? array() ) as $more ) {
			$parts = array_map( 'absint', explode( ':', (string) $more ) );
			if ( ! empty( $parts[0] ) ) {
				$places[] = array( $parts[0], $parts[1] ?? 0 );
			}
		}
		$pin    = preg_replace( '/\D/', '', (string) wp_unslash( $_POST['aw_pincode'] ?? '' ) );
		$city   = sanitize_text_field( wp_unslash( $_POST['aw_place_city'] ?? '' ) );
		$before = aw_item_codes( $id );
		if ( aw_set_item_places( $id, $places, $pin, $city ) ) {
			if ( aw_item_codes( $id ) !== $before ) { // The box posts the place it loaded with: only a change is an editor's choice.
				update_post_meta( $id, 'aw_place_source', 'editor' );
				delete_post_meta( $id, 'aw_place_auto' );
			}
		} else {
			set_transient( 'aw_place_error_' . get_current_user_id(), 'Place not saved: choose the state and district from the lists, and give a 6-digit PIN code (or leave it empty). The previous place is kept.', 60 );
		}
	},
	20,
	2
);

add_action(
	'admin_notices',
	function () {
		$msg = get_transient( 'aw_place_error_' . get_current_user_id() );
		if ( $msg ) {
			delete_transient( 'aw_place_error_' . get_current_user_id() );
			echo '<div class="notice notice-error"><p>' . esc_html( $msg ) . '</p></div>';
		}
	}
);

/** PIN lookup for the Place box (editors): see aw_pin_lookup() in places.php. */
add_action(
	'wp_ajax_aw_pin_lookup',
	function () {
		check_ajax_referer( 'aw_place_save', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Not allowed.' );
		}
		$r = aw_pin_lookup( (string) wp_unslash( $_GET['pin'] ?? '' ) );
		is_array( $r ) ? wp_send_json_success( $r ) : wp_send_json_error( $r );
	}
);

add_action(
	'admin_footer-post.php',
	'aw_place_box_js'
);
add_action(
	'admin_footer-post-new.php',
	'aw_place_box_js'
);

/** District follows State; "Fill in" asks India Post. */
function aw_place_box_js() {
	if ( ! in_array( get_post_type(), AW_PLACE_TYPES, true ) ) {
		return;
	}
	?>
<script>
(function () {
	var st = document.getElementById('aw-state'), di = document.getElementById('aw-district');
	if (!st || !di) { return; }
	function show() {
		var v = st.value;
		di.querySelectorAll('optgroup').forEach(function (g) { var on = g.dataset.state === v; g.hidden = !on; g.disabled = !on; });
		di.options[0].textContent = v !== '0' ? 'Whole state' : 'Choose a state first';
		var cur = di.selectedOptions[0];
		if (cur && cur.parentNode.tagName === 'OPTGROUP' && cur.parentNode.dataset.state !== v) { di.value = '0'; }
	}
	st.addEventListener('change', show);
	var go = document.getElementById('aw-pin-go'), pin = document.getElementById('aw-pin'), msg = document.getElementById('aw-pin-msg');
	function look() {
		msg.textContent = 'Looking up…';
		var u = ajaxurl + '?action=aw_pin_lookup&pin=' + encodeURIComponent(pin.value) + '&nonce=' + encodeURIComponent(document.getElementById('aw_place_nonce').value);
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
