<?php
/**
 * Testimonials from real people (shelters, volunteers, vets). Shown only when consent is recorded.
 * Admin: Testimonials > Add New (quote in the editor; name, role/organisation, consent below).
 * Display: [aw_testimonials count="3"] or [aw_testimonials org="Barkville Foundation"].
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
			'aw_testimonial',
			array(
				'labels'       => array( 'name' => 'Testimonials', 'singular_name' => 'Testimonial', 'add_new_item' => 'Add testimonial' ),
				'public'       => false,
				'show_ui'      => true,
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-format-quote',
				'supports'     => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
			)
		);
		foreach ( array( 'aw_t_name' => 'string', 'aw_t_role' => 'string', 'aw_t_org' => 'string', 'aw_t_consent' => 'boolean', 'aw_t_consent_date' => 'string' ) as $k => $type ) {
			register_post_meta( 'aw_testimonial', $k, array( 'type' => $type, 'single' => true, 'show_in_rest' => true, 'auth_callback' => fn() => current_user_can( 'edit_posts' ) ) );
		}
	}
);

add_action(
	'add_meta_boxes_aw_testimonial',
	function () {
		add_meta_box(
			'aw_t_details',
			'Who said it',
			function ( $post ) {
				wp_nonce_field( 'aw_t_save', 'aw_t_nonce' );
				$v = fn( $k ) => esc_attr( (string) get_post_meta( $post->ID, $k, true ) );
				echo '<p><label>Name<br><input class="widefat" name="aw_t_name" value="' . $v( 'aw_t_name' ) . '"></label></p>';
				echo '<p><label>Role (e.g. Founder, Volunteer, Veterinarian)<br><input class="widefat" name="aw_t_role" value="' . $v( 'aw_t_role' ) . '"></label></p>';
				echo '<p><label>Organisation and city<br><input class="widefat" name="aw_t_org" value="' . $v( 'aw_t_org' ) . '"></label></p>';
				echo '<p><label><input type="checkbox" name="aw_t_consent" value="1"' . checked( (bool) get_post_meta( $post->ID, 'aw_t_consent', true ), true, false ) . '> They agreed to be quoted by name on Animal Writes</label></p>';
				echo '<p><label>Consent date<br><input type="date" name="aw_t_consent_date" value="' . $v( 'aw_t_consent_date' ) . '"></label></p>';
				echo '<p class="description">Testimonials without recorded consent are never shown on the site.</p>';
			},
			null,
			'side'
		);
	}
);

add_action(
	'save_post_aw_testimonial',
	function ( $id ) {
		if ( ! isset( $_POST['aw_t_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aw_t_nonce'] ) ), 'aw_t_save' ) || ! current_user_can( 'edit_post', $id ) ) {
			return;
		}
		foreach ( array( 'aw_t_name', 'aw_t_role', 'aw_t_org' ) as $k ) {
			update_post_meta( $id, $k, sanitize_text_field( wp_unslash( $_POST[ $k ] ?? '' ) ) );
		}
		update_post_meta( $id, 'aw_t_consent', ! empty( $_POST['aw_t_consent'] ) );
		$d = sanitize_text_field( wp_unslash( $_POST['aw_t_consent_date'] ?? '' ) );
		update_post_meta( $id, 'aw_t_consent_date', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ? $d : '' );
	}
);

add_shortcode(
	'aw_testimonials',
	function ( $atts ) {
		$a    = shortcode_atts( array( 'count' => 3, 'org' => '' ), $atts );
		$meta = array( array( 'key' => 'aw_t_consent', 'value' => '1' ) );
		if ( $a['org'] ) {
			$meta[] = array( 'key' => 'aw_t_org', 'value' => sanitize_text_field( $a['org'] ), 'compare' => 'LIKE' );
		}
		$items = get_posts( array( 'post_type' => 'aw_testimonial', 'post_status' => 'publish', 'numberposts' => (int) $a['count'], 'meta_query' => $meta ) );
		if ( ! $items ) {
			return '';
		}
		$out = '<div class="aw-testimonials">';
		foreach ( $items as $t ) {
			$who  = array_filter( array( get_post_meta( $t->ID, 'aw_t_role', true ), get_post_meta( $t->ID, 'aw_t_org', true ) ) );
			$out .= '<figure class="aw-testimonial">' . ( has_post_thumbnail( $t ) ? get_the_post_thumbnail( $t, 'thumbnail', array( 'class' => 'aw-t-photo', 'alt' => '' ) ) : '' )
				. '<blockquote>' . wp_kses_post( wpautop( $t->post_content ) ) . '</blockquote><figcaption><strong>'
				. esc_html( (string) get_post_meta( $t->ID, 'aw_t_name', true ) ) . '</strong>' . ( $who ? '<span>' . esc_html( implode( ', ', $who ) ) . '</span>' : '' ) . '</figcaption></figure>';
		}
		return $out . '</div>';
	}
);
