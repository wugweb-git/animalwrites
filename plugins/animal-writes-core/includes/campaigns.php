<?php
/**
 * Campaign kit: countdown, proof-of-posting upload (private), live counts.
 *
 * Shortcodes:
 *   [aw_countdown date="2026-11-29" label="Posting day"]
 *   [aw_receipt_form campaign="slug"]
 *   [aw_count campaign="slug" what="letters|people|cities|volunteers"]
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	function () {
		register_post_type(
			'aw_receipt',
			array(
				'labels'          => array( 'name' => 'Campaign receipts', 'singular_name' => 'Receipt', 'menu_name' => 'Receipts' ),
				'public'          => false,
				'show_ui'         => true,
				'show_in_rest'    => false,
				'menu_icon'       => 'dashicons-email-alt',
				'supports'        => array( 'title' ),
				'capability_type' => array( 'aw_volunteer', 'aw_volunteers' ), // Same admin-only capabilities as volunteers.
				'map_meta_cap'    => true,
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
			)
		);
	}
);

/** Private folder for receipt photos (blocked from the web). */
function aw_private_dir() {
	$up  = wp_upload_dir();
	$dir = trailingslashit( $up['basedir'] ) . 'aw-private';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
		file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore
		file_put_contents( $dir . '/index.html', '' ); // phpcs:ignore
	}
	return $dir;
}

/* ---------- Countdown ---------- */
add_shortcode(
	'aw_countdown',
	function ( $atts ) {
		$a    = shortcode_atts( array( 'date' => '', 'label' => '' ), $atts );
		$date = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $a['date'] ) ? $a['date'] : '';
		if ( ! $date ) {
			return '';
		}
		$ts   = strtotime( $date . ' 00:00:00 Asia/Kolkata' );
		$days = (int) ceil( ( $ts - time() ) / DAY_IN_SECONDS );
		$when = wp_date( 'j F Y', $ts );
		$lbl  = $a['label'] ? esc_html( $a['label'] ) . ': ' : '';
		if ( $days > 1 ) {
			$text = sprintf( '<span class="aw-cd-num">%d</span> <span class="aw-cd-unit">days to go</span>', $days );
		} elseif ( 1 === $days ) {
			$text = '<span class="aw-cd-num">1</span> <span class="aw-cd-unit">day to go</span>';
		} elseif ( 0 === $days ) {
			$text = '<span class="aw-cd-num">Today</span>';
		} else {
			$text = '<span class="aw-cd-unit">Took place on</span>';
		}
		return '<p class="aw-countdown" data-date="' . esc_attr( $date ) . '">' . $text . ' <span class="aw-cd-when">' . $lbl . esc_html( $when ) . '</span></p>';
	}
);

/* ---------- Live counts (real submissions only) ---------- */
function aw_campaign_counts( $campaign ) {
	$key = 'aw_counts_' . md5( $campaign );
	$c   = get_transient( $key );
	if ( false !== $c ) {
		return $c;
	}
	$ids     = get_posts( array( 'post_type' => 'aw_receipt', 'post_status' => 'private', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => 'aw_r_campaign', 'meta_value' => $campaign ) );
	$letters = 0;
	$people  = array();
	$cities  = array();
	foreach ( $ids as $id ) {
		$letters += max( 1, (int) get_post_meta( $id, 'aw_r_letters', true ) );
		$people[ strtolower( (string) get_post_meta( $id, 'aw_r_email', true ) ) ] = 1;
		$city = strtolower( trim( (string) get_post_meta( $id, 'aw_r_city', true ) ) );
		if ( $city ) {
			$cities[ $city . '|' . get_post_meta( $id, 'aw_r_state', true ) ] = 1;
		}
	}
	$c = array(
		'letters'    => $letters,
		'people'     => count( $people ),
		'cities'     => count( $cities ),
		'volunteers' => (int) wp_count_posts( 'aw_volunteer' )->private,
	);
	set_transient( $key, $c, 5 * MINUTE_IN_SECONDS );
	return $c;
}

add_shortcode(
	'aw_count',
	function ( $atts ) {
		$a      = shortcode_atts( array( 'campaign' => '', 'what' => 'letters', 'label' => '' ), $atts );
		$counts = aw_campaign_counts( sanitize_title( $a['campaign'] ) );
		$labels = array( 'letters' => 'letters posted', 'people' => 'people took part', 'cities' => 'cities', 'volunteers' => 'volunteers signed up' );
		$what   = isset( $counts[ $a['what'] ] ) ? $a['what'] : 'letters';
		return '<span class="aw-count"><strong>' . esc_html( number_format_i18n( $counts[ $what ] ) ) . '</strong> ' . esc_html( $a['label'] ? $a['label'] : $labels[ $what ] ) . '</span>';
	}
);

/* ---------- Proof-of-posting upload ---------- */
add_shortcode(
	'aw_receipt_form',
	function ( $atts ) {
		$a        = shortcode_atts( array( 'campaign' => '' ), $atts );
		$campaign = sanitize_title( $a['campaign'] );
		if ( isset( $_GET['posted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return '<div class="aw-form-done" role="status"><h3>Thank you. Your letter is counted.</h3><p>Share the campaign so more people post theirs.</p></div>';
		}
		$error  = isset( $_GET['receipt_error'] ) ? sanitize_key( $_GET['receipt_error'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$msgs   = array(
			'missing' => 'Please add your name, a valid email, your state and a photo.',
			'file'    => 'The photo must be a JPG, PNG or WebP image under 8 MB.',
			'limit'   => 'Too many uploads from this connection. Please try again later.',
			'invalid' => 'Something went wrong. Please try again.',
		);
		$states = get_terms( array( 'taxonomy' => 'aw_city', 'parent' => 0, 'hide_empty' => false, 'orderby' => 'name' ) );
		ob_start();
		?>
		<form class="aw-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php if ( $error && isset( $msgs[ $error ] ) ) : ?><p class="aw-form-error" role="alert"><?php echo esc_html( $msgs[ $error ] ); ?></p><?php endif; ?>
			<input type="hidden" name="action" value="aw_receipt">
			<input type="hidden" name="campaign" value="<?php echo esc_attr( $campaign ); ?>">
			<?php wp_nonce_field( 'aw_receipt', 'aw_r_nonce' ); ?>
			<input type="hidden" name="aw_t" value="<?php echo esc_attr( time() ); ?>">
			<p class="aw-hp" aria-hidden="true"><label>Leave this empty <input type="text" name="aw_website" tabindex="-1" autocomplete="off"></label></p>
			<p><label for="aw-r-photo">Photo of your Speed Post receipt or posted letter</label><input id="aw-r-photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" required capture="environment"></p>
			<p><label for="aw-r-letters">How many letters did you post?</label><input id="aw-r-letters" name="letters" type="number" min="1" max="500" value="1" inputmode="numeric"></p>
			<p><label for="aw-r-name">Your name</label><input id="aw-r-name" name="name" required autocomplete="name" maxlength="120"></p>
			<p><label for="aw-r-email">Email</label><input id="aw-r-email" name="email" type="email" required autocomplete="email" maxlength="160"></p>
			<p><label for="aw-r-state">State or union territory</label><select id="aw-r-state" name="state" required><option value="">Choose a state</option>
				<?php foreach ( $states as $s ) : ?><option value="<?php echo esc_attr( $s->slug ); ?>"><?php echo esc_html( $s->name ); ?></option><?php endforeach; ?>
			</select></p>
			<p><label for="aw-r-city">City or town</label><input id="aw-r-city" name="city" maxlength="80" autocomplete="address-level2"></p>
			<p class="aw-note">Your photo is kept private and is only used to count letters. It is never published.</p>
			<p><button type="submit" class="wp-element-button">Count my letter</button></p>
		</form>
		<?php
		return ob_get_clean();
	}
);

function aw_receipt_submit() {
	$back = wp_get_referer() ? remove_query_arg( array( 'posted', 'receipt_error' ), wp_get_referer() ) : home_url( '/' );
	$fail = function ( $code ) use ( $back ) {
		wp_safe_redirect( add_query_arg( 'receipt_error', $code, $back ) . '#count-my-letter' );
		exit;
	};
	if ( ! isset( $_POST['aw_r_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aw_r_nonce'] ) ), 'aw_receipt' ) ) {
		$fail( 'invalid' );
	}
	if ( ! empty( $_POST['aw_website'] ) || ( time() - (int) ( $_POST['aw_t'] ?? 0 ) ) < 3 ) {
		wp_safe_redirect( add_query_arg( 'posted', 1, $back ) . '#count-my-letter' );
		exit;
	}
	$ip_key = 'aw_rc_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$count  = (int) get_transient( $ip_key );
	if ( $count >= 10 ) {
		$fail( 'limit' );
	}
	$campaign = sanitize_title( wp_unslash( $_POST['campaign'] ?? '' ) );
	$name     = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
	$email    = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$state    = get_term_by( 'slug', sanitize_title( wp_unslash( $_POST['state'] ?? '' ) ), 'aw_city' );
	$city     = sanitize_text_field( wp_unslash( $_POST['city'] ?? '' ) );
	$letters  = min( 500, max( 1, (int) ( $_POST['letters'] ?? 1 ) ) );
	$file     = $_FILES['photo'] ?? null; // phpcs:ignore
	if ( '' === $name || ! is_email( $email ) || ! $state || ! $file || UPLOAD_ERR_OK !== $file['error'] ) {
		$fail( 'missing' );
	}
	$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], array( 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' ) );
	if ( empty( $check['type'] ) || $file['size'] > 8 * MB_IN_BYTES || ! @getimagesize( $file['tmp_name'] ) ) { // phpcs:ignore
		$fail( 'file' );
	}
	$fname = wp_generate_password( 20, false ) . '.' . $check['ext'];
	if ( ! move_uploaded_file( $file['tmp_name'], aw_private_dir() . '/' . $fname ) ) {
		$fail( 'invalid' );
	}
	$id = wp_insert_post( array( 'post_type' => 'aw_receipt', 'post_status' => 'private', 'post_title' => $name . ' (' . $state->name . ')' ), true );
	if ( is_wp_error( $id ) ) {
		$fail( 'invalid' );
	}
	foreach ( array( 'aw_r_campaign' => $campaign, 'aw_r_name' => $name, 'aw_r_email' => $email, 'aw_r_state' => $state->slug, 'aw_r_city' => $city, 'aw_r_letters' => $letters, 'aw_r_file' => $fname ) as $k => $v ) {
		update_post_meta( $id, $k, $v );
	}
	delete_transient( 'aw_counts_' . md5( $campaign ) );
	set_transient( $ip_key, $count + 1, HOUR_IN_SECONDS );
	wp_safe_redirect( add_query_arg( 'posted', 1, $back ) . '#count-my-letter' );
	exit;
}
add_action( 'admin_post_nopriv_aw_receipt', 'aw_receipt_submit' );
add_action( 'admin_post_aw_receipt', 'aw_receipt_submit' );

/* Admin: view a private photo (administrators only). */
add_action(
	'admin_post_aw_receipt_photo',
	function () {
		$id = (int) ( $_GET['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! current_user_can( 'edit_aw_volunteers' ) || ! $id || 'aw_receipt' !== get_post_type( $id ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$path = aw_private_dir() . '/' . basename( (string) get_post_meta( $id, 'aw_r_file', true ) );
		if ( ! is_file( $path ) ) {
			wp_die( 'Missing.', 404 );
		}
		header( 'Content-Type: ' . wp_check_filetype( $path )['type'] );
		header( 'Cache-Control: private, no-store' );
		readfile( $path ); // phpcs:ignore
		exit;
	}
);

add_filter( 'manage_aw_receipt_posts_columns', fn( $c ) => array( 'cb' => $c['cb'], 'title' => 'Name (state)', 'aw_campaign' => 'Campaign', 'aw_city' => 'City', 'aw_letters' => 'Letters', 'aw_photo' => 'Photo', 'date' => 'Uploaded' ) );
add_action(
	'manage_aw_receipt_posts_custom_column',
	function ( $col, $id ) {
		$map = array( 'aw_campaign' => 'aw_r_campaign', 'aw_city' => 'aw_r_city', 'aw_letters' => 'aw_r_letters' );
		if ( isset( $map[ $col ] ) ) {
			echo esc_html( (string) get_post_meta( $id, $map[ $col ], true ) );
		} elseif ( 'aw_photo' === $col ) {
			echo '<a href="' . esc_url( admin_url( 'admin-post.php?action=aw_receipt_photo&id=' . $id ) ) . '" target="_blank">View</a>';
		}
	},
	10,
	2
);

// Deleting a receipt removes its private photo too.
add_action(
	'before_delete_post',
	function ( $id ) {
		if ( 'aw_receipt' === get_post_type( $id ) ) {
			$f = basename( (string) get_post_meta( $id, 'aw_r_file', true ) );
			if ( $f && is_file( aw_private_dir() . '/' . $f ) ) {
				wp_delete_file( aw_private_dir() . '/' . $f );
			}
			delete_transient( 'aw_counts_' . md5( (string) get_post_meta( $id, 'aw_r_campaign', true ) ) );
		}
	}
);
