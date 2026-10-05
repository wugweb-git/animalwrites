<?php
/**
 * Volunteer sign-up: [aw_volunteer_form]. Entries are private posts visible to administrators only.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_VOL_ROLES = array(
	'coordinate' => 'Coordinate volunteers in my city',
	'letters'    => 'Write and post letters, and get others to',
	'rescue'     => 'Rescue, foster or transport animals',
	'creative'   => 'Design, writing, photos or social media',
	'legal'      => 'Legal or policy work',
);

add_action(
	'init',
	function () {
		register_post_type(
			'aw_volunteer',
			array(
				'labels'          => array( 'name' => 'Volunteers', 'singular_name' => 'Volunteer', 'menu_name' => 'Volunteers' ),
				'public'          => false,
				'show_ui'         => true,
				'show_in_rest'    => false,
				'menu_icon'       => 'dashicons-groups',
				'supports'        => array( 'title' ),
				'capability_type' => array( 'aw_volunteer', 'aw_volunteers' ),
				'map_meta_cap'    => true,
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
			)
		);
		// Only administrators may read or manage volunteer data.
		$admin = get_role( 'administrator' );
		if ( $admin && ! $admin->has_cap( 'edit_aw_volunteers' ) ) {
			foreach ( array( 'edit', 'read', 'delete' ) as $verb ) {
				foreach ( array( 'aw_volunteer', 'aw_volunteers', 'others_aw_volunteers', 'private_aw_volunteers', 'published_aw_volunteers' ) as $obj ) {
					$admin->add_cap( "{$verb}_{$obj}" );
				}
			}
			$admin->add_cap( 'publish_aw_volunteers' );
		}
	}
);

/**
 * Form markup.
 *
 * @return string
 */
function aw_volunteer_form() {
	if ( isset( $_GET['joined'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return '<div class="aw-form-done" role="status"><h3>' . esc_html__( 'Thank you for signing up.', 'animal-writes-core' ) . '</h3><p>'
			. esc_html__( 'We will contact you when there is work in your area. You can ask us to delete your details at any time.', 'animal-writes-core' ) . '</p></div>';
	}
	$error  = isset( $_GET['vol_error'] ) ? sanitize_key( $_GET['vol_error'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$states = get_terms( array( 'taxonomy' => 'aw_city', 'parent' => 0, 'hide_empty' => false, 'orderby' => 'name' ) );
	ob_start();
	?>
	<form class="aw-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php if ( $error ) : ?>
			<p class="aw-form-error" role="alert"><?php echo 'limit' === $error ? esc_html__( 'Too many sign-ups from this connection. Please try again later.', 'animal-writes-core' ) : esc_html__( 'Please fill in your name, a valid email and your state.', 'animal-writes-core' ); ?></p>
		<?php endif; ?>
		<input type="hidden" name="action" value="aw_volunteer">
		<?php wp_nonce_field( 'aw_volunteer', 'aw_vol_nonce' ); ?>
		<input type="hidden" name="aw_t" value="<?php echo esc_attr( time() ); ?>">
		<p class="aw-hp" aria-hidden="true"><label>Leave this empty <input type="text" name="aw_website" tabindex="-1" autocomplete="off"></label></p>
		<p><label for="aw-v-name"><?php esc_html_e( 'Your name', 'animal-writes-core' ); ?></label><input id="aw-v-name" name="name" required autocomplete="name" maxlength="120"></p>
		<p><label for="aw-v-email"><?php esc_html_e( 'Email', 'animal-writes-core' ); ?></label><input id="aw-v-email" name="email" type="email" required autocomplete="email" maxlength="160"></p>
		<p><label for="aw-v-phone"><?php esc_html_e( 'Phone or WhatsApp (optional)', 'animal-writes-core' ); ?></label><input id="aw-v-phone" name="phone" type="tel" autocomplete="tel" maxlength="20" inputmode="tel"></p>
		<p><label for="aw-v-state"><?php esc_html_e( 'State or union territory', 'animal-writes-core' ); ?></label><select id="aw-v-state" name="state" required><option value=""><?php esc_html_e( 'Choose a state', 'animal-writes-core' ); ?></option>
			<?php foreach ( $states as $s ) : ?><option value="<?php echo esc_attr( $s->slug ); ?>"><?php echo esc_html( $s->name ); ?></option><?php endforeach; ?>
		</select></p>
		<p><label for="aw-v-city"><?php esc_html_e( 'City or district', 'animal-writes-core' ); ?></label><input id="aw-v-city" name="city" maxlength="80" autocomplete="address-level2"></p>
		<fieldset><legend><?php esc_html_e( 'How would you like to help?', 'animal-writes-core' ); ?></legend>
			<?php foreach ( AW_VOL_ROLES as $k => $label ) : ?>
				<label class="aw-check"><input type="checkbox" name="roles[]" value="<?php echo esc_attr( $k ); ?>"> <?php echo esc_html( $label ); ?></label>
			<?php endforeach; ?>
		</fieldset>
		<?php /* Phase 2: explicit consent checkbox (DPDP Act). */ ?>
		<p><button type="submit" class="wp-element-button"><?php esc_html_e( 'Sign up to volunteer', 'animal-writes-core' ); ?></button></p>
	</form>
	<?php
	return ob_get_clean();
}
add_shortcode( 'aw_volunteer_form', 'aw_volunteer_form' );

/**
 * Handle a submission.
 */
function aw_volunteer_submit() {
	$back = wp_get_referer() ? remove_query_arg( array( 'joined', 'vol_error' ), wp_get_referer() ) : home_url( '/get-involved/' );
	$fail = function ( $code ) use ( $back ) {
		wp_safe_redirect( add_query_arg( 'vol_error', $code, $back ) . '#volunteer' );
		exit;
	};
	if ( ! isset( $_POST['aw_vol_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aw_vol_nonce'] ) ), 'aw_volunteer' ) ) {
		$fail( 'invalid' );
	}
	// Bots: honeypot filled, or submitted in under 3 seconds. Pretend success.
	if ( ! empty( $_POST['aw_website'] ) || ( time() - (int) ( $_POST['aw_t'] ?? 0 ) ) < 3 ) {
		wp_safe_redirect( add_query_arg( 'joined', 1, $back ) . '#volunteer' );
		exit;
	}
	$ip_key = 'aw_vol_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$count  = (int) get_transient( $ip_key );
	if ( $count >= 5 ) {
		$fail( 'limit' );
	}
	$name    = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
	$email   = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$phone   = preg_replace( '/[^0-9+ ]/', '', (string) wp_unslash( $_POST['phone'] ?? '' ) );
	$state   = sanitize_title( wp_unslash( $_POST['state'] ?? '' ) );
	$city    = sanitize_text_field( wp_unslash( $_POST['city'] ?? '' ) );
	$roles   = array_values( array_intersect( array_keys( AW_VOL_ROLES ), array_map( 'sanitize_key', (array) wp_unslash( $_POST['roles'] ?? array() ) ) ) );
	$term    = $state ? get_term_by( 'slug', $state, 'aw_city' ) : null;
	if ( '' === $name || ! is_email( $email ) || ! $term ) {
		$fail( 'missing' );
	}
	$id = wp_insert_post(
		array( 'post_type' => 'aw_volunteer', 'post_status' => 'private', 'post_title' => $name . ' (' . $term->name . ')' ),
		true
	);
	if ( is_wp_error( $id ) ) {
		$fail( 'invalid' );
	}
	foreach ( array( 'aw_v_name' => $name, 'aw_v_email' => $email, 'aw_v_phone' => $phone, 'aw_v_state' => $term->name, 'aw_v_city' => $city, 'aw_v_roles' => implode( ', ', $roles ) ) as $k => $v ) {
		update_post_meta( $id, $k, $v );
	}
	set_transient( $ip_key, $count + 1, HOUR_IN_SECONDS );
	wp_mail( 'contact@animalwrites.in', 'New volunteer: ' . $term->name, "A new volunteer signed up.\nState: {$term->name}\nCity: {$city}\nView in WP Admin > Volunteers." );
	wp_safe_redirect( add_query_arg( 'joined', 1, $back ) . '#volunteer' );
	exit;
}
add_action( 'admin_post_nopriv_aw_volunteer', 'aw_volunteer_submit' );
add_action( 'admin_post_aw_volunteer', 'aw_volunteer_submit' );

// Admin list: useful columns, no personal data in the title beyond name and state.
add_filter( 'manage_aw_volunteer_posts_columns', fn( $c ) => array( 'cb' => $c['cb'], 'title' => 'Name (state)', 'aw_email' => 'Email', 'aw_phone' => 'Phone', 'aw_city' => 'City', 'aw_roles' => 'Wants to', 'date' => 'Signed up' ) );
add_action(
	'manage_aw_volunteer_posts_custom_column',
	function ( $col, $id ) {
		$map = array( 'aw_email' => 'aw_v_email', 'aw_phone' => 'aw_v_phone', 'aw_city' => 'aw_v_city', 'aw_roles' => 'aw_v_roles' );
		if ( isset( $map[ $col ] ) ) {
			echo esc_html( (string) get_post_meta( $id, $map[ $col ], true ) );
		}
	},
	10,
	2
);
