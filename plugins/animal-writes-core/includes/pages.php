<?php
/**
 * Restructure Phase 6: the parts the new pages are built from. The pages themselves hold structure only; the owner
 * writes their text (tools/restructure/phase6.php creates them).
 *
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

/* ---------- Acts & Rules: now the Library's "Act" filter (/library/?kind=act); the old address redirects there ---------- */

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
	'general'     => 'General',
	'listing'     => 'Listing',
	'featured'    => 'Featured',
	'opportunity' => 'Opportunity',
	'partner'     => 'Partnership',
	'correction'  => 'Correction',
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
	$html .= aw_field( $form, array( 'name' => 'name', 'label' => 'Name', 'required' => true, 'attrs' => array( 'autocomplete' => 'name', 'maxlength' => 120 ) ) );
	$html .= aw_field( $form, array( 'name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'attrs' => array( 'autocomplete' => 'email', 'maxlength' => 160 ) ) );
	$html .= aw_field( $form, array( 'name' => 'org', 'label' => 'Organisation', 'attrs' => array( 'autocomplete' => 'organization', 'maxlength' => 160 ) ) );
	$html .= aw_field( $form, array( 'name' => 'about', 'label' => 'Reason', 'type' => 'select', 'required' => true, 'options' => AW_CONTACT_ABOUT, 'value' => $about ) );
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
		array( 'about' => 'Reason', 'name' => 'Name', 'email' => 'Email', 'org' => 'Organisation', 'message' => 'Message' )
	);
	if ( $v['about'] && ( ! isset( AW_CONTACT_ABOUT[ $v['about'] ] ) || '' === $v['about'] ) ) {
		$errors['about'] = 'Reason: choose one of the options.';
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

/* ---------- Site search at /search/?q= (noindex; see wp_robots above) ---------- */

const AW_SEARCH_TYPES = array( 'aw_news', 'aw_legal', 'aw_guide', 'aw_campaign', 'aw_opportunity', 'aw_listing', 'page' );

// WordPress's own search (?s=) goes to the search page, so there is one search address.
add_action(
	'template_redirect',
	function () {
		if ( is_search() && ! is_admin() ) {
			wp_safe_redirect( add_query_arg( 'q', rawurlencode( (string) get_search_query( false ) ), home_url( '/search/' ) ), 301 );
			exit;
		}
	}
);

/** Escape text, then mark the searched words in it. */
function aw_search_mark( $text, $words ) {
	$html = esc_html( $text );
	if ( $words ) {
		$html = preg_replace( '/(' . implode( '|', array_map( fn( $w ) => preg_quote( esc_html( $w ), '/' ), $words ) ) . ')/iu', '<mark>$1</mark>', $html );
	}
	return $html;
}

add_shortcode(
	'aw_search',
	function () {
		$q    = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$html = '<form class="aw-search-form" role="search" method="get" action="' . esc_url( home_url( '/search/' ) ) . '">'
			. '<label for="aw-search-q">Search</label><input type="search" id="aw-search-q" name="q" value="' . esc_attr( $q ) . '" autocomplete="off"> '
			. '<button type="submit" class="wp-element-button">Search</button></form>';
		if ( '' === $q ) {
			return $html;
		}
		$words  = array_values( array_filter( preg_split( '/\s+/u', $q ), fn( $w ) => mb_strlen( $w ) >= 3 ) );
		$groups = array( 'aw_news' => 'News', 'aw_guide' => 'Resources', 'aw_legal' => 'Library', 'aw_opportunity' => 'Opportunities', 'aw_campaign' => 'Campaigns', 'aw_listing' => 'Directory', 'page' => 'Pages' );
		$total  = 0;
		$out    = '';
		foreach ( $groups as $type => $label ) {
			$per = 'aw_listing' === $type ? 8 : 20;
			$res = new WP_Query( array( 's' => $q, 'post_type' => $type, 'post_status' => 'publish', 'posts_per_page' => $per, 'no_found_rows' => false ) );
			if ( ! $res->found_posts ) {
				continue;
			}
			$total += $res->found_posts;
			$out   .= '<section class="aw-search-group"><h2>' . esc_html( $label ) . ' <span class="aw-search-n">' . esc_html( number_format_i18n( $res->found_posts ) ) . '</span></h2><ol class="aw-search-results">';
			foreach ( $res->posts as $p ) {
				$out .= '<li><a href="' . esc_url( get_permalink( $p ) ) . '">' . aw_search_mark( get_the_title( $p ), $words ) . '</a>'
					. ( 'aw_listing' !== $p->post_type && function_exists( 'aw_text' ) ? '<p>' . aw_search_mark( aw_text( $p->ID, 26 ), $words ) . '</p>' : '' ) . '</li>';
			}
			$out .= '</ol>';
			if ( $res->found_posts > $per ) {
				$more = 'aw_listing' === $type ? add_query_arg( 'q', rawurlencode( $q ), home_url( '/directory/' ) ) : '';
				$out .= $more ? '<p class="aw-search-more"><a href="' . esc_url( $more ) . '">All ' . esc_html( number_format_i18n( $res->found_posts ) ) . ' in the Directory</a></p>' : '<p class="aw-search-more">Showing the first ' . (int) $per . '. Add a word to narrow the search.</p>';
			}
			$out .= '</section>';
		}
		$html .= '<p class="aw-search-count" role="status">' . esc_html( sprintf( _n( '%s result', '%s results', $total ), number_format_i18n( $total ) ) ) . '</p>' . $out;
		return $html;
	}
);

/* ---------- Topic hub (/topic/{slug}/) ---------- */

add_shortcode(
	'aw_topic_hub',
	function () {
		$term = get_queried_object();
		if ( ! $term || empty( $term->taxonomy ) || 'aw_topic' !== $term->taxonomy ) {
			return '';
		}
		$groups = array( 'aw_guide' => 'Resources', 'aw_news' => 'News', 'aw_legal' => 'Library', 'aw_campaign' => 'Campaigns' );
		$html   = '';
		foreach ( $groups as $type => $label ) {
			$posts = get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'numberposts' => 6, 'tax_query' => array( array( 'taxonomy' => 'aw_topic', 'terms' => $term->term_id ) ) ) ); // phpcs:ignore
			if ( ! $posts ) {
				continue;
			}
			$all   = add_query_arg( 'topic', $term->slug, get_post_type_archive_link( $type ) );
			$html .= '<section class="aw-hub-group"><h2>' . esc_html( $label ) . '</h2><ul>';
			foreach ( $posts as $p ) {
				$html .= '<li><a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a></li>';
			}
			$html .= '</ul><p><a href="' . esc_url( $all ) . '">All ' . esc_html( strtolower( $label ) ) . ' on this topic</a></p></section>';
		}
		$types = function_exists( 'aw_topic_help_types' ) ? aw_topic_help_types( $term->term_id ) : array();
		$links = '';
		foreach ( $types as $tid ) {
			$t = get_term( $tid, 'aw_listing_type' );
			if ( $t && ! is_wp_error( $t ) && $t->count ) {
				$links .= '<li><a href="' . esc_url( add_query_arg( 'type', $t->slug, home_url( '/directory/' ) ) ) . '">' . esc_html( $t->name ) . '</a></li>';
			}
		}
		if ( $links ) {
			$html .= '<section class="aw-hub-group aw-hub-help"><h2>Find help</h2><ul>' . $links . '</ul></section>';
		}
		return '<div class="aw-hub">' . $html . '</div>';
	}
);

/* ---------- Resources: byline and last-updated date (a trust signal) ---------- */

add_filter(
	'render_block_core/post-title',
	function ( $html, $block ) {
		if ( ! is_singular( 'aw_guide' ) || 1 !== (int) ( $block['attrs']['level'] ?? 2 ) || get_queried_object_id() !== get_the_ID() ) {
			return $html;
		}
		$id = get_queried_object_id();
		return $html . '<p class="aw-byline">By Animal Writes · Last updated <time datetime="' . esc_attr( get_the_modified_date( 'Y-m-d', $id ) ) . '">' . esc_html( get_the_modified_date( 'j M Y', $id ) ) . '</time></p>';
	},
	20,
	2
);
