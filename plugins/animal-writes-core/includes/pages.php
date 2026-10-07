<?php
/**
 * Restructure Phase 6: the parts the new pages are built from. The pages themselves hold structure only; the owner
 * writes their text (tools/restructure/phase6.php creates them).
 *
 * - /legal-watch/acts-and-rules/: the Legal Watch list limited to Acts and Rules.
 * - [aw_copy_needed what="…"]: a marker for text still to be written, shown to editors only.
 * - [aw_topics_index]: every topic with its photo and item count (/topics/).
 * - [aw_contact_form]: the contact form; /contact/?about=featured (listing, opportunity, partner, correction …)
 *   preselects the subject. Messages are private posts (WP Admin > Messages) and are emailed to the team.
 * - /search/ is not indexed.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- Acts & Rules ---------- */

add_action(
	'init',
	function () {
		add_rewrite_tag( '%aw_acts%', '1' );
		add_rewrite_rule( '^legal-watch/acts-and-rules/?$', 'index.php?post_type=aw_legal&aw_acts=1', 'top' );
		add_rewrite_rule( '^legal-watch/acts-and-rules/page/([0-9]+)/?$', 'index.php?post_type=aw_legal&aw_acts=1&paged=$matches[1]', 'top' );
	},
	21
);

add_action(
	'pre_get_posts',
	function ( $q ) {
		if ( ! is_admin() && $q->is_main_query() && $q->get( 'aw_acts' ) ) {
			$q->set( 'tax_query', array( array( 'taxonomy' => 'aw_legal_kind', 'field' => 'slug', 'terms' => array( 'act', 'rule' ) ) ) );
			$q->set( 'orderby', 'title' );
			$q->set( 'order', 'ASC' );
		}
	}
);

add_filter(
	'post_type_archive_title',
	fn( $title, $type ) => 'aw_legal' === $type && get_query_var( 'aw_acts' ) ? 'Acts & Rules' : $title,
	10,
	2
);

/* ---------- Placeholders for copy ---------- */

add_shortcode(
	'aw_copy_needed',
	function ( $atts ) {
		if ( ! current_user_can( 'edit_pages' ) ) {
			return '';
		}
		$what = sanitize_text_field( $atts['what'] ?? 'text' );
		return '<p class="aw-copy-needed" style="border:2px dashed currentColor;padding:.75em 1em;opacity:.7">Copy needed: ' . esc_html( $what ) . ' (only editors see this)</p>';
	}
);

/* ---------- Topics index ---------- */

add_shortcode(
	'aw_topics_index',
	function () {
		$terms = get_terms( array( 'taxonomy' => 'aw_topic', 'hide_empty' => false, 'orderby' => 'name' ) );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return '';
		}
		$html = '<ul class="aw-topics-index">';
		foreach ( $terms as $t ) {
			$img  = function_exists( 'aw_topic_image_id' ) ? aw_topic_image_id( $t ) : 0;
			$html .= '<li><a href="' . esc_url( get_term_link( $t ) ) . '">'
				. ( $img ? wp_get_attachment_image( $img, 'medium', false, array( 'alt' => '', 'loading' => 'lazy' ) ) : '' )
				. '<span class="aw-topics-name">' . esc_html( $t->name ) . '</span>'
				. '<span class="aw-topics-n">' . (int) $t->count . '</span></a></li>';
		}
		return $html . '</ul>';
	}
);

/* ---------- Contact form ---------- */

const AW_CONTACT_ABOUT = array(
	''            => 'Choose one',
	'general'     => 'A question',
	'listing'     => 'Add or update a directory listing',
	'featured'    => 'Get featured',
	'opportunity' => 'Post an opportunity',
	'partner'     => 'Partner with us',
	'correction'  => 'A correction',
	'legal'       => 'Share a legal update',
	'other'       => 'Something else',
);

add_action(
	'init',
	function () {
		register_post_type(
			'aw_message',
			array(
				'labels'          => array( 'name' => 'Messages', 'singular_name' => 'Message', 'menu_name' => 'Messages' ),
				'public'          => false,
				'show_ui'         => true,
				'show_in_rest'    => false,
				'menu_icon'       => 'dashicons-email',
				'supports'        => array( 'title', 'editor' ),
				'capability_type' => array( 'aw_volunteer', 'aw_volunteers' ), // administrators only
				'map_meta_cap'    => true,
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
			)
		);
	}
);

function aw_contact_form() {
	if ( isset( $_GET['aw_done'] ) && 'contact' === $_GET['aw_done'] ) { // phpcs:ignore WordPress.Security.NonceVerification
		return '<div class="aw-form-done" id="write" role="status" tabindex="-1"><p>Message sent. We reply by email.</p></div>';
	}
	$form  = 'aw_contact';
	$about = sanitize_key( wp_unslash( $_GET['about'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$about = isset( AW_CONTACT_ABOUT[ $about ] ) ? $about : '';
	$html  = aw_form_open( $form, array( 'id' => 'write' ) );
	$html .= aw_field( $form, array( 'name' => 'about', 'label' => 'About', 'type' => 'select', 'required' => true, 'options' => AW_CONTACT_ABOUT, 'value' => $about ) );
	$html .= aw_field( $form, array( 'name' => 'name', 'label' => 'Your name', 'required' => true, 'attrs' => array( 'autocomplete' => 'name', 'maxlength' => 120 ) ) );
	$html .= aw_field( $form, array( 'name' => 'email', 'label' => 'Your email', 'type' => 'email', 'required' => true, 'attrs' => array( 'autocomplete' => 'email', 'maxlength' => 160 ) ) );
	$html .= aw_field( $form, array( 'name' => 'org', 'label' => 'Organisation', 'attrs' => array( 'autocomplete' => 'organization', 'maxlength' => 160 ) ) );
	$html .= aw_field( $form, array( 'name' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => true, 'attrs' => array( 'maxlength' => 3000, 'rows' => 6 ) ) );
	$html .= '<p><button type="submit" class="wp-element-button">Send message</button></p></form>';
	return $html;
}
add_shortcode( 'aw_contact_form', 'aw_contact_form' );

function aw_contact_submit() {
	$form = 'aw_contact';
	$back = aw_form_back( '/contact/' );
	$done = add_query_arg( 'aw_done', 'contact', $back ) . '#write';
	$rl   = aw_form_guard( $form, $back, 'write', $done );
	$v    = array(
		'about'   => sanitize_key( wp_unslash( $_POST['about'] ?? '' ) ),
		'name'    => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
		'email'   => trim( sanitize_text_field( wp_unslash( $_POST['email'] ?? '' ) ) ),
		'org'     => sanitize_text_field( wp_unslash( $_POST['org'] ?? '' ) ),
		'message' => sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) ),
	);
	$errors = aw_form_validate(
		array( 'about' => array( 'required' ), 'name' => array( 'required', 'max:120' ), 'email' => array( 'required', 'email' ), 'org' => array( 'max:160' ), 'message' => array( 'required', 'max:3000' ) ),
		$v,
		array( 'about' => 'About', 'name' => 'Your name', 'email' => 'Your email', 'org' => 'Organisation', 'message' => 'Message' )
	);
	if ( $v['about'] && ( ! isset( AW_CONTACT_ABOUT[ $v['about'] ] ) || '' === $v['about'] ) ) {
		$errors['about'] = 'About: choose one of the options.';
	}
	if ( $errors ) {
		aw_form_fail( $form, $errors, $v, $back, 'write' );
	}
	$label = AW_CONTACT_ABOUT[ $v['about'] ];
	$id    = wp_insert_post( array( 'post_type' => 'aw_message', 'post_status' => 'private', 'post_title' => $label . ': ' . $v['name'], 'post_content' => $v['message'] ) );
	if ( is_wp_error( $id ) || ! $id ) {
		aw_form_fail( $form, array(), $v, $back, 'write', 'The message could not be saved. Please try again.' );
	}
	foreach ( array( 'about', 'name', 'email', 'org' ) as $k ) {
		update_post_meta( $id, 'aw_m_' . $k, $v[ $k ] );
	}
	aw_form_count( $rl );
	wp_mail( 'contact@animalwrites.in', 'Website message: ' . $label, "A message came in through the website.\nAbout: {$label}\nRead it in WP Admin > Messages.", array( 'Reply-To: ' . $v['name'] . ' <' . $v['email'] . '>' ) );
	wp_safe_redirect( $done );
	exit;
}
add_action( 'admin_post_nopriv_aw_contact', 'aw_contact_submit' );
add_action( 'admin_post_aw_contact', 'aw_contact_submit' );

add_filter( 'manage_aw_message_posts_columns', fn( $c ) => array( 'cb' => $c['cb'], 'title' => 'About: sender', 'aw_m_email' => 'Email', 'aw_m_org' => 'Organisation', 'date' => 'Received' ) );
add_action(
	'manage_aw_message_posts_custom_column',
	function ( $col, $id ) {
		echo esc_html( (string) get_post_meta( $id, $col, true ) );
	},
	10,
	2
);

/* ---------- Search page ---------- */

add_filter(
	'wp_robots',
	function ( $robots ) {
		if ( is_page( 'search' ) ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	},
	20
);

/* ---------- Footer topics list ---------- */

add_shortcode(
	'aw_topics_list',
	function () {
		$terms = get_terms( array( 'taxonomy' => 'aw_topic', 'hide_empty' => true, 'orderby' => 'name' ) );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return '';
		}
		return '<ul class="aw-footer-list">' . implode( '', array_map( fn( $t ) => '<li><a href="' . esc_url( get_term_link( $t ) ) . '">' . esc_html( $t->name ) . '</a></li>', $terms ) ) . '</ul>';
	}
);

// Template parts keep their links in HTML blocks; let those carry our shortcodes too.
add_filter(
	'render_block_core/html',
	fn( $html ) => false !== strpos( $html, '[aw_' ) ? do_shortcode( $html ) : $html
);

/* ---------- Acts & Rules intro (owner-written) ---------- */

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'edit.php?post_type=aw_legal', 'Acts & Rules intro', 'Acts & Rules intro', 'edit_pages', 'aw-acts-intro', 'aw_acts_intro_screen' );
	}
);

function aw_acts_intro_screen() {
	if ( isset( $_POST['aw_acts_intro'] ) && check_admin_referer( 'aw_acts_intro' ) ) {
		update_option( 'aw_acts_intro', sanitize_textarea_field( wp_unslash( $_POST['aw_acts_intro'] ) ), false );
		echo '<div class="notice notice-success"><p>Saved.</p></div>';
	}
	echo '<div class="wrap"><h1>Acts &amp; Rules intro</h1><form method="post">';
	wp_nonce_field( 'aw_acts_intro' );
	echo '<p>Shown under the title of <a href="' . esc_url( home_url( '/legal-watch/acts-and-rules/' ) ) . '" target="_blank">Acts &amp; Rules</a>. Leave empty to show nothing.</p>';
	echo '<textarea name="aw_acts_intro" rows="4" class="large-text">' . esc_textarea( (string) get_option( 'aw_acts_intro', '' ) ) . '</textarea>';
	submit_button();
	echo '</form></div>';
}

// The site search answers at /search/?q= (the address search engines are given); WordPress search runs on ?s=.
add_action(
	'template_redirect',
	function () {
		$q = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' !== $q && is_page( 'search' ) ) {
			wp_safe_redirect( add_query_arg( 's', rawurlencode( $q ), home_url( '/' ) ) );
			exit;
		}
	}
);
