<?php
/**
 * Restructure Phase 6: the parts the new pages are built from. The pages themselves hold structure only; the owner
 * writes their text (tools/restructure/phase6.php creates them).
 *
 * - [aw_copy_needed what="…"]: a marker for text still to be written, shown to editors only.
 * - [aw_categories_index]: every category with its photo and item count (/categories/).
 * - [aw_contact_form]: the contact form; /contact/?about=featured (listing, opportunity, partner, correction …)
 *   preselects the subject. Messages are private posts (WP Admin > Messages) and are emailed to the team.
 * - /search/ is not indexed.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- Acts & Rules: now the Library's "Act" filter (/library/?tag=act); the old address redirects there ---------- */

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

/* ---------- Categories index ---------- */

add_shortcode(
	'aw_categories_index',
	function () {
		$terms = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false, 'orderby' => 'name' ) );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return '';
		}
		$html = '<ul class="aw-categories-index">';
		foreach ( $terms as $t ) {
			$img  = function_exists( 'aw_category_image_id' ) ? aw_category_image_id( $t ) : 0;
			$html .= '<li><a href="' . esc_url( kits_term_url( $t ) ) . '">'
				. ( $img ? wp_get_attachment_image( $img, 'medium', false, array( 'alt' => '', 'loading' => 'lazy' ) ) : '' )
				. '<span class="aw-categories-name">' . esc_html( $t->name ) . '</span>'
				. '<span class="aw-categories-n">' . (int) $t->count . '</span></a></li>';
		}
		return $html . '</ul>';
	}
);

/* ---------- Contact form ---------- */

const AW_CONTACT_ABOUT = array(
	''            => 'Choose a reason',
	'general'     => 'General question',
	'listing'     => 'List your organisation',
	'featured'    => 'Get featured',
	'opportunity' => 'Post an opportunity',
	'partnership' => 'Partner with us',
	'correction'  => 'Report a mistake',
	'remove-data' => 'Remove my data',
);
/** Reasons with a form of their own (owner, 21 Oct 2026): choosing one opens it; no contact message is made. */
const AW_CONTACT_ELSEWHERE = array(
	'correction'  => '/report/',
	'remove-data' => '/remove-my-data/',
);
/** Message placeholder by reason (Phase 11 H4). */
const AW_CONTACT_PLACEHOLDER = array(
	'listing'    => 'Organisation name, district, services, phone or email',
	'correction' => 'Page link and what\'s wrong',
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
		$to = sanitize_email( wp_unslash( $_GET['to'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		return '<div class="kit-form-done" id="write" role="status" tabindex="-1"><p class="aw-form-done-title">Message sent.</p>'
			. ( $to ? '<p>' . esc_html( sprintf( 'We\'ll reply to %s.', $to ) ) . '</p>' : '' )
			. '<p><a class="kit-btn kit-btn--secondary" href="' . esc_url( get_permalink() ) . '#write">Send another</a></p></div>';
	}
	$ff = apply_filters( 'aw_form_html', '', 'contact', array() ); // the Fluent Forms version when it is set up (forms-fluentforms.php)
	if ( '' !== $ff ) {
		return $ff;
	}
	$form  = 'aw_contact';
	$about = sanitize_key( wp_unslash( $_GET['about'] ?? ( $_GET['type'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$about = 'partner' === $about ? 'partnership' : $about; // older links
	$about = isset( AW_CONTACT_ABOUT[ $about ] ) ? $about : '';
	$state = aw_form_state( $form );
	$pick  = (string) ( $state['values']['about'] ?? $about );
	$html  = aw_form_open( $form, array( 'id' => 'write', 'class' => 'kit-form aw-contact-form', 'data-go' => wp_json_encode( array_map( 'home_url', AW_CONTACT_ELSEWHERE ) ) ) );
	// Reason first; its options carry the message placeholder (aw.js swaps it when the reason changes).
	$id    = aw_field_id( $form, 'about' );
	$opts  = '';
	foreach ( AW_CONTACT_ABOUT as $k => $label ) {
		$opts .= '<option value="' . esc_attr( $k ) . '"' . selected( $pick, $k, false ) . ( isset( AW_CONTACT_PLACEHOLDER[ $k ] ) ? ' data-placeholder="' . esc_attr( AW_CONTACT_PLACEHOLDER[ $k ] ) . '"' : '' ) . '>' . esc_html( $label ) . '</option>';
	}
	$err   = $state['errors']['about'] ?? '';
	$html .= '<div class="kit-field kit-field--select' . ( $err ? ' is-invalid' : '' ) . '"><label for="' . esc_attr( $id ) . '">What\'s this about?</label><div class="kit-field-control"><select id="' . esc_attr( $id ) . '" name="about" required data-msg-required="Choose what this is about" aria-describedby="' . esc_attr( $id ) . '-error"' . ( $err ? ' aria-invalid="true"' : '' ) . '>' . $opts . '</select></div><span class="kit-field-message kit-field-error" id="' . esc_attr( $id ) . '-error" aria-live="polite">' . esc_html( $err ) . '</span></div>';
	$html .= kits_dict_field( $form, 'name', array( 'required' => true ) );
	$html .= kits_dict_field( $form, 'email' );
	$html .= kits_dict_field( $form, 'organisation', array( 'name' => 'org' ) );
	$html .= kits_dict_field( $form, 'message', array( 'required' => true, 'attrs' => array( 'placeholder' => AW_CONTACT_PLACEHOLDER[ $pick ] ?? '' ) ) );
	$html .= function_exists( 'aw_nl_optin_box' ) ? aw_nl_optin_box( $form ) : ''; // unticked; only a tick subscribes
	$html .= '<div class="kit-form-actions"><button type="submit" class="kit-btn">Send message</button></div></form>';
	return $html;
}
add_shortcode( 'aw_contact_form', 'aw_contact_form' );

/** A contact message's values from submitted data (already unslashed): the site's form and Fluent Forms use this. */
function aw_contact_values( $in ) {
	return array(
		'about'   => sanitize_key( (string) ( $in['about'] ?? '' ) ),
		'name'    => trim( sanitize_text_field( (string) ( $in['name'] ?? '' ) ) ),
		'email'   => aw_clean_email( sanitize_text_field( (string) ( $in['email'] ?? '' ) ) ),
		'org'     => trim( sanitize_text_field( (string) ( $in['org'] ?? '' ) ) ),
		'message' => sanitize_textarea_field( (string) ( $in['message'] ?? '' ) ),
	);
}

/** Server rules for a contact message: field => message. */
function aw_contact_errors( $v ) {
	list( $rules, $msgs ) = kits_dict_rules( array( 'about' => 'reason', 'name' => 'name', 'email' => 'email', 'org' => 'organisation', 'message' => 'message' ), array( 'about', 'name', 'email', 'message' ) );
	$errors               = aw_form_validate( $rules, $v, array( 'about' => 'What\'s this about?', 'name' => 'Your name', 'email' => 'Email', 'org' => 'Organisation', 'message' => 'Message' ), $msgs );
	if ( $v['about'] && ! isset( AW_CONTACT_ABOUT[ $v['about'] ] ) ) {
		$errors['about'] = 'Choose what this is about';
	}
	return $errors;
}

/**
 * Save a checked contact message (Inbox > Messages) with its ticks and visit block (kits-core forms.php), email the team,
 * add the sender to the newsletter when the box is ticked, and reach FluentCRM through aw_crm_sync().
 * "Report a mistake" and "Remove my data" make no message: they open their own forms (AW_CONTACT_ELSEWHERE).
 *
 * @return array|false array{url, title, text} for the thank-you page (or the other form), or false when not saved.
 */
function aw_contact_save( $v, $source, $nl, $nl_consent = null ) {
	if ( isset( AW_CONTACT_ELSEWHERE[ $v['about'] ] ) ) {
		return array( 'url' => home_url( AW_CONTACT_ELSEWHERE[ $v['about'] ] ), 'title' => '', 'text' => '' );
	}
	$label = AW_CONTACT_ABOUT[ $v['about'] ];
	$id    = wp_insert_post( array( 'post_type' => 'aw_message', 'post_status' => 'private', 'post_title' => $label . ': ' . $v['name'], 'post_content' => $v['message'] ) );
	if ( is_wp_error( $id ) || ! $id ) {
		return false;
	}
	foreach ( array( 'about', 'name', 'email', 'org' ) as $k ) {
		update_post_meta( $id, 'aw_m_' . $k, $v[ $k ] );
	}
	update_post_meta( $id, 'aw_m_source', $source ); // the page it was sent from (S5.8)
	list( $visit, $ticks ) = aw_form_record( 'contact', array( 'page' => $source ) );
	aw_record_post( $id, $visit, $ticks );
	wp_mail( 'contact@animalwrites.in', 'Website message: ' . $label, "A message came in through the website.\nAbout: {$label}\nRead it in WP Admin > Inbox > Messages.", array( 'Reply-To: ' . $v['name'] . ' <' . $v['email'] . '>' ) );
	$consent = null === $nl_consent ? AW_NL_CONSENT : $nl_consent;
	$sub     = function_exists( 'aw_nl_optin' ) && aw_nl_optin( $v['email'], $v['name'], 'contact', $nl, $consent );
	if ( $sub ) {
		aw_record_row( aw_nl_table(), array( 'email' => strtolower( $v['email'] ) ), $visit, $ticks );
	}
	if ( function_exists( 'aw_crm_sync' ) ) { // FluentCRM (fluentcrm.php): the contact, the message as a note, the Form fields
		aw_crm_sync( $v['email'], $v['name'], 'contact', array( 'subscribe' => $sub, 'ticks' => $ticks, 'visit' => $visit, 'tags' => $sub ? array( 'newsletter' ) : array(), 'source' => 'contact-form', 'message' => $id ) );
	}
	$text = sprintf( 'We\'ll reply to %s.', $v['email'] );
	return array( 'url' => kits_thanks_url( 'contact', array(), 'Message sent.', $text ), 'title' => 'Message sent.', 'text' => $text );
}

function aw_contact_submit() {
	$form = 'aw_contact';
	$back = aw_form_back( '/contact/' );
	$done = kits_thanks_url( 'contact' );
	$rl   = aw_form_guard( $form, $back, 'write', $done );
	$v    = aw_contact_values( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification -- checked in aw_form_guard()
	$errors = aw_contact_errors( $v );
	if ( $errors ) {
		aw_form_fail( $form, $errors, $v, $back, 'write' );
	}
	$res = aw_contact_save( $v, kits_submitted_source(), ! empty( $_POST['nl_optin'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! $res ) {
		aw_form_fail( $form, array(), $v, $back, 'write', aw_form_generic_error( $form ) );
	}
	aw_form_count( $rl );
	aw_form_success( $res['url'], $res['title'], $res['text'], array( 'again' => 'Send another' ) );
}
add_action( 'admin_post_nopriv_aw_contact', 'aw_contact_submit' );
add_action( 'admin_post_aw_contact', 'aw_contact_submit' );

// Contact messages list (owner, 9 Oct 2026): Name · Email · Organisation · Tag · Sent to · Sent from · Received.
add_filter( 'manage_aw_message_posts_columns', fn( $c ) => array( 'cb' => $c['cb'], 'title' => 'Name', 'aw_m_email' => 'Email', 'aw_m_org' => 'Organisation', 'aw_m_tag' => 'Tag', 'aw_m_to' => 'Sent to', 'aw_m_source' => 'Sent from', 'date' => 'Received' ), 30 );
add_action(
	'manage_aw_message_posts_custom_column',
	function ( $col, $id ) {
		$v = (string) get_post_meta( $id, $col, true );
		if ( 'aw_m_tag' === $col && '' === $v ) { // contact form messages: the reason they chose
			$v = AW_CONTACT_ABOUT[ (string) get_post_meta( $id, 'aw_m_about', true ) ] ?? '';
		}
		if ( 'aw_m_source' === $col && 0 === strpos( $v, 'email:' ) ) {
			$v = ''; // emails come from the mailbox, not a page
		}
		if ( 'aw_m_to' === $col && '' === $v && 'Email' !== get_post_meta( $id, 'aw_m_tag', true ) ) {
			$v = 'Contact form';
		}
		$box = (string) get_post_meta( $id, 'aw_m_mailbox', true );
		if ( 'aw_m_to' === $col && $box && $v && $box !== $v ) {
			$v .= ' (in ' . $box . ')'; // the mailbox it arrived in, when that differs from the address it was sent to
		}
		echo esc_html( '' !== $v ? $v : '—' );
	},
	10,
	2
);
// The Name column shows the sender's name (the stored title — "Reason: name" or "Email: subject" — is unchanged).
add_filter(
	'the_title',
	function ( $title, $id = 0 ) {
		if ( ! is_admin() || ! $id || 'aw_message' !== get_post_type( $id ) || ! function_exists( 'get_current_screen' ) || ! get_current_screen() || 'edit-aw_message' !== get_current_screen()->id ) {
			return $title;
		}
		$name = trim( (string) get_post_meta( $id, 'aw_m_name', true ) );
		return '' !== $name ? $name : $title;
	},
	10,
	2
);

/* ---------- Footer categories list ---------- */

add_shortcode(
	'aw_categories_list',
	function ( $a ) {
		$terms = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => true, 'orderby' => 'name' ) );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return '';
		}
		if ( 'links' === ( $a['style'] ?? '' ) ) { // plain text links (footer)
			return '<ul class="kit-footer-inline">' . implode( '', array_map( fn( $t ) => '<li><a href="' . esc_url( kits_term_url( $t ) ) . '">' . esc_html( $t->name ) . '</a></li>', $terms ) ) . '</ul>';
		}
		return '<ul class="aw-footer-list">' . implode( '', array_map( fn( $t ) => '<li>' . kits_chip( $t->name, kits_term_url( $t ), 'on-ink' ) . '</li>', $terms ) ) . '</ul>';
	}
);

// Template parts keep their links in HTML blocks; let those carry our shortcodes too.
add_filter(
	'render_block_core/html',
	fn( $html ) => false !== strpos( $html, '[aw_' ) ? do_shortcode( $html ) : $html
);

/* ---------- Site search at /search/?q= (noindex: Rank Math's robots field on the page) ---------- */

const AW_SEARCH_TYPES = array( 'aw_news', 'aw_legal', 'post', 'aw_campaign', 'aw_opportunity', 'aw_listing', 'page' );

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
		// The Kits search field (as on the 404 page): one rounded field and the search button.
		$html = '<form class="kit-error-search aw-search-form" role="search" method="get" action="' . esc_url( home_url( '/search/' ) ) . '">'
			. '<label class="screen-reader-text" for="aw-search-q">Search</label><input type="search" id="aw-search-q" name="q" value="' . esc_attr( $q ) . '" placeholder="Search" autocomplete="off">'
			. '<button type="submit" class="kit-btn"><span class="kit-ico kit-ico--search" aria-hidden="true"></span>Search</button></form>';
		if ( '' === $q ) {
			return $html;
		}
		$words  = array_values( array_filter( preg_split( '/\s+/u', $q ), fn( $w ) => mb_strlen( $w ) >= 3 ) );
		$groups = array( 'aw_news' => 'News', 'post' => 'Resources', 'aw_legal' => 'Library', 'aw_opportunity' => 'Opportunities', 'aw_campaign' => 'Campaigns', 'aw_listing' => 'Directory', 'page' => 'Pages' );
		$total  = 0;
		$out    = '';
		$shown  = 0;
		foreach ( $groups as $type => $label ) {
			$per = 'aw_listing' === $type ? 8 : 20;
			$res = new WP_Query( array( 's' => $q, 'post_type' => $type, 'post_status' => 'publish', 'posts_per_page' => $per, 'no_found_rows' => false ) );
			if ( ! $res->found_posts ) {
				continue;
			}
			$total += $res->found_posts;
			$out   .= '<section class="aw-search-group"><h2 class="kit-eyebrow aw-search-head">' . esc_html( $label ) . ' ' . kits_count( $res->found_posts ) . '</h2><ol class="aw-search-results">';
			foreach ( $res->posts as $p ) {
				$out .= '<li>' . ( 'aw_legal' === $p->post_type && function_exists( 'aw_legal_kind_tag' ) && ( $k = aw_legal_kind_tag( $p->ID ) ) ? '<p class="kit-tags">' . $k . '</p>' : '' ) . '<h3 class="aw-search-title"><a href="' . esc_url( get_permalink( $p ) ) . '">' . aw_search_mark( get_the_title( $p ), $words ) . '</a></h3>'
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
		if ( ! $total ) { // empty result: what to do next
			$html .= '<div class="kit-empty"><p>Nothing matched “' . esc_html( $q ) . '”. Try fewer or different words, or go to a section.</p><nav class="kit-error-links" aria-label="Sections"><ul><li><a href="/directory/">Directory</a></li><li><a href="/news/">News</a></li><li><a href="/resources/">Resources</a></li><li><a href="/library/">Library</a></li><li><a href="/opportunities/">Opportunities</a></li></ul></nav></div>';
		}
		return $html;
	}
);

/* ---------- Category page (/category/{slug}/) ---------- */

add_shortcode(
	'aw_category_hub',
	function () {
		$term = get_queried_object();
		if ( ! $term || empty( $term->taxonomy ) || 'category' !== $term->taxonomy ) {
			return '';
		}
		$groups = array( 'post' => 'Resources', 'aw_news' => 'News', 'aw_legal' => 'Library', 'aw_campaign' => 'Campaigns' );
		$html   = '';
		$sort   = function_exists( 'aw_active_sort' ) ? aw_active_sort( 'term' ) : 'new'; // the toolbar's Sort: each group's first six in that order
		$by     = array( 'old' => array( 'date' => 'ASC' ), 'az' => array( 'title' => 'ASC' ), 'za' => array( 'title' => 'DESC' ) );
		foreach ( $groups as $type => $label ) {
			$posts = get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'numberposts' => 6, 'orderby' => $by[ $sort ] ?? array( 'date' => 'DESC' ), 'tax_query' => array( array( 'taxonomy' => 'category', 'terms' => $term->term_id ) ) ) ); // phpcs:ignore
			if ( ! $posts ) {
				continue;
			}
			$all   = add_query_arg( array( 'category_name' => $term->slug ) + ( isset( $by[ $sort ] ) ? array( 'sort' => $sort ) : array() ), aw_hub_url( $type ) );
			$html .= '<section class="aw-hub-group"><h2>' . esc_html( $label ) . '</h2><ul' . ( 'aw_news' === $type ? ' class="aw-hub-news"' : '' ) . '>';
			foreach ( $posts as $p ) {
				$html .= '<li>' . ( 'aw_news' === $type ? aw_news_meta_row( $p->ID ) : '' ) . ( 'aw_legal' === $type && ( $k = aw_legal_kind_tag( $p->ID ) ) ? '<p class="kit-tags">' . $k . '</p>' : '' ) . '<a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a></li>'; // news: the same source + date row as the News cards
			}
			$html .= '</ul><p><a href="' . esc_url( $all ) . '">All ' . esc_html( strtolower( $label ) ) . ' in this category</a></p></section>';
		}
		$types = function_exists( 'aw_category_help_types' ) ? aw_category_help_types( $term->term_id ) : array();
		$links = '';
		foreach ( $types as $tid ) {
			$t = get_term( $tid, 'post_tag' );
			if ( $t && ! is_wp_error( $t ) && $t->count ) {
				$links .= '<li><a href="' . esc_url( add_query_arg( 'tag', $t->slug, home_url( '/directory/' ) ) ) . '">' . esc_html( $t->name ) . '</a></li>';
			}
		}
		if ( $links ) {
			$html .= '<section class="aw-hub-group aw-hub-help"><h2>Find help</h2><ul>' . $links . '</ul></section>';
		}
		return '<div class="aw-hub">' . $html . '</div>';
	}
);

/* ---------- Resources: no byline (owner, 20 Oct 2026) ---------- */

// "By Animal Writes" is in the item's Sources block and the dates are in its meta row (item-head.php).

// One date line on a Resource: the byline above carries it, so the template's own date block is not repeated (12 Oct 2026).
add_filter(
	'render_block_core/post-date',
	function ( $html, $block, $instance ) {
		$id = (int) ( $instance->context['postId'] ?? 0 );
		return ( is_singular( 'post' ) && $id === get_queried_object_id() ) ? '' : $html;
	},
	30,
	3
);

/* ---------- Resources: Sponsored (a label, not a Resource type) ---------- */

/** Whether a Resource is sponsored. */
function aw_is_sponsored( $id ) {
	return 'post' === get_post_type( $id ) && (bool) get_post_meta( $id, 'aw_sponsored', true );
}

/** The "Sponsored" badge on a Resource card ('' when not sponsored). */
function aw_sponsored_badge( $id ) {
	return aw_is_sponsored( $id ) && function_exists( 'kits_badge' ) ? kits_badge( 'Sponsored', 'sponsored' ) : '';
}

// Item page: "Sponsored by {name}" is a label in the meta row (item-head.php).

// Every outbound link in a sponsored Resource: rel="sponsored noopener".
add_filter(
	'the_content',
	function ( $content ) {
		if ( ! is_singular( 'post' ) || ! in_the_loop() || ! aw_is_sponsored( get_the_ID() ) || false === stripos( $content, '<a ' ) ) {
			return $content;
		}
		$home = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$tags = new WP_HTML_Tag_Processor( $content );
		while ( $tags->next_tag( 'a' ) ) {
			$host = strtolower( (string) wp_parse_url( (string) $tags->get_attribute( 'href' ), PHP_URL_HOST ) );
			if ( '' === $host || preg_replace( '/^www\./', '', $host ) === preg_replace( '/^www\./', '', $home ) ) {
				continue; // same site
			}
			$rel = preg_split( '/\s+/', strtolower( trim( (string) $tags->get_attribute( 'rel' ) ) ), -1, PREG_SPLIT_NO_EMPTY );
			$tags->set_attribute( 'rel', implode( ' ', array_unique( array_merge( array( 'sponsored', 'noopener' ), $rel ) ) ) );
		}
		return $tags->get_updated_html();
	},
	20
);

// Editing: a Sponsored box beside the editor (fallback when SCF is off; with SCF, the Sponsored tab of "Resource details").
add_action(
	'add_meta_boxes_post',
	function () {
		if ( function_exists( 'aw_scf_active' ) && aw_scf_active() ) {
			return;
		}
		add_meta_box( 'aw-sponsored', 'Sponsored', 'aw_sponsored_box', 'post', 'side', 'default' );
	}
);

function aw_sponsored_box( $post ) {
	wp_nonce_field( 'aw_sponsored_save', 'aw_sponsored_nonce' );
	echo '<p><label><input type="checkbox" name="aw_sponsored" value="1"' . checked( (bool) get_post_meta( $post->ID, 'aw_sponsored', true ), true, false ) . '> Sponsored</label></p>';
	echo '<p><label for="aw-sponsor-name">Sponsor name</label><br><input type="text" id="aw-sponsor-name" name="aw_sponsor_name" value="' . esc_attr( (string) get_post_meta( $post->ID, 'aw_sponsor_name', true ) ) . '" style="width:100%"></p>';
}

add_action(
	'save_post_post',
	function ( $id ) {
		if ( wp_is_post_revision( $id ) || ! isset( $_POST['aw_sponsored_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['aw_sponsored_nonce'] ), 'aw_sponsored_save' ) || ! current_user_can( 'edit_post', $id ) ) {
			return;
		}
		if ( empty( $_POST['aw_sponsored'] ) ) {
			delete_post_meta( $id, 'aw_sponsored' );
		} else {
			update_post_meta( $id, 'aw_sponsored', '1' );
		}
		$name = sanitize_text_field( wp_unslash( $_POST['aw_sponsor_name'] ?? '' ) );
		'' === $name ? delete_post_meta( $id, 'aw_sponsor_name' ) : update_post_meta( $id, 'aw_sponsor_name', $name );
	}
);

/* ---------- Contact: "Elsewhere" links (Phase 11 H5) ---------- */

/** Links the owner adds in Appearance → Contact links: list of [label, url]. Hidden while empty. */
function aw_elsewhere_links() {
	return array_values( array_filter( (array) get_option( 'aw_elsewhere', array() ), fn( $r ) => ! empty( $r['label'] ) && ! empty( $r['url'] ) ) );
}
add_shortcode(
	'aw_elsewhere',
	function () {
		$links = aw_elsewhere_links();
		if ( ! $links ) {
			return '';
		}
		$html = '<section class="aw-elsewhere"><h2>Elsewhere</h2><ul>';
		foreach ( $links as $l ) {
			$host  = (string) wp_parse_url( $l['url'], PHP_URL_HOST );
			$icon  = preg_match( '/instagram\.com$/', $host ) ? 'instagram' : ( preg_match( '/facebook\.com$/', $host ) ? 'facebook' : ( preg_match( '/(^|\.)(x|twitter)\.com$/', $host ) ? 'x-brand' : '' ) );
			$html .= '<li><a href="' . esc_url( $l['url'] ) . '" target="_blank" rel="noopener noreferrer">' . ( $icon ? aw_i( $icon ) : '' ) . esc_html( $l['label'] ) . ' ' . aw_i( 'arrow-up-right' ) . '<span class="screen-reader-text"> (opens in a new tab)</span></a></li>';
		}
		return $html . '</ul></section>';
	}
);
add_action(
	'admin_menu',
	function () {
		add_theme_page( 'Contact links', 'Contact links', 'edit_pages', 'aw-elsewhere', 'aw_elsewhere_screen' ); // Appearance → Contact links
	}
);
function aw_elsewhere_screen() {
	if ( isset( $_POST['aw_elsewhere'] ) && check_admin_referer( 'aw_elsewhere' ) ) {
		$rows = array();
		foreach ( (array) wp_unslash( $_POST['aw_elsewhere'] ) as $r ) {
			$row = array( 'label' => sanitize_text_field( $r['label'] ?? '' ), 'url' => esc_url_raw( $r['url'] ?? '' ) );
			if ( $row['label'] && $row['url'] ) {
				$rows[] = $row;
			}
		}
		update_option( 'aw_elsewhere', $rows, false );
		echo '<div class="notice notice-success"><p>Saved.</p></div>';
	}
	$rows = array_merge( aw_elsewhere_links(), array_fill( 0, 4, array() ) );
	echo '<div class="wrap"><h1>Contact links</h1><p>Shown under "Elsewhere" on the <a href="' . esc_url( home_url( '/contact/' ) ) . '" target="_blank">Contact</a> page; the section stays hidden while this list is empty. Use the platform name as the label (for example Instagram).</p><form method="post">';
	wp_nonce_field( 'aw_elsewhere' );
	echo '<table class="widefat striped"><thead><tr><th>Label</th><th>Link</th></tr></thead><tbody>';
	foreach ( $rows as $i => $r ) {
		echo '<tr><td><input type="text" name="aw_elsewhere[' . (int) $i . '][label]" value="' . esc_attr( (string) ( $r['label'] ?? '' ) ) . '"></td><td><input type="url" class="large-text" name="aw_elsewhere[' . (int) $i . '][url]" value="' . esc_attr( (string) ( $r['url'] ?? '' ) ) . '"></td></tr>';
	}
	echo '</tbody></table>';
	submit_button();
	echo '</form></div>';
}

// /contact/?about=correction and ?about=remove-data (and the older ?type=) open those forms' own pages (owner, 21 Oct
// 2026); /remove-my-data/ is the request form again (privacy-requests.php).
add_action(
	'template_redirect',
	function () {
		$about = sanitize_key( wp_unslash( $_GET['about'] ?? ( $_GET['type'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( is_page( 'contact' ) && isset( AW_CONTACT_ELSEWHERE[ $about ] ) ) {
			wp_safe_redirect( home_url( AW_CONTACT_ELSEWHERE[ $about ] ), 302, 'Animal Writes' );
			exit;
		}
	}
);

// Inbox → Contact messages: an incoming email's "Reply" opens Inbox → Reply for that email (mail-reply.php); a form
// message's "Reply" opens your own mail app with the sender and subject filled in (WordPress sends nothing).
add_filter(
	'post_row_actions',
	function ( $actions, $post ) {
		if ( 'aw_message' !== $post->post_type ) {
			return $actions;
		}
		$email = (string) get_post_meta( $post->ID, 'aw_m_email', true );
		if ( function_exists( 'aw_reply_email_item' ) && aw_reply_email_item( $post->ID ) ) {
			$actions = array( 'aw_reply' => '<a href="' . esc_url( aw_reply_url( $post->ID ) ) . '">Reply</a>' ) + $actions;
		} elseif ( is_email( $email ) ) {
			$subject = preg_replace( '/^Email:\s*/', '', (string) get_post_field( 'post_title', $post->ID ) );
			$actions = array( 'aw_reply' => '<a href="' . esc_url( 'mailto:' . rawurlencode( $email ) . '?subject=' . rawurlencode( 'Re: ' . $subject ) ) . '">Reply</a>' ) + $actions;
		}
		return $actions;
	},
	10,
	2
);

/*
 * Contact page: the "For organisations" rows and "Report a mistake" lead to the contact form on the same page with that
 * reason chosen (/contact/?about=listing#write; aw.js chooses it in place without a reload).
 */
add_filter(
	'render_block_core/heading',
	function ( $html ) {
		if ( ! is_page( 'contact' ) || false !== strpos( $html, '<a ' ) ) {
			return $html;
		}
		$text = trim( html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES ) );
		$key  = array_search( $text, AW_CONTACT_ABOUT, true );
		if ( ! $key || ! in_array( $key, array( 'listing', 'featured', 'opportunity', 'partnership', 'correction' ), true ) ) {
			return $html;
		}
		if ( isset( AW_CONTACT_ELSEWHERE[ $key ] ) ) { // its own form, on its own page
			return (string) preg_replace( '/(<h[1-6][^>]*>)(.*?)(<\/h[1-6]>)/s', '$1<a href="' . esc_url( home_url( AW_CONTACT_ELSEWHERE[ $key ] ) ) . '">$2</a>$3', $html, 1 );
		}
		$url = add_query_arg( 'about', $key, get_permalink() ) . '#write';
		return (string) preg_replace( '/(<h[1-6][^>]*>)(.*?)(<\/h[1-6]>)/s', '$1<a class="aw-contact-pick" href="' . esc_url( $url ) . '" data-about="' . esc_attr( $key ) . '">$2</a>$3', $html, 1 );
	}
);

/*
 * Every date carries the calendar icon (owner, 15–16 Oct 2026), also inside sentences: a date written in a page's,
 * guide's or campaign's text ("29 November 2025", "8 Oct 2026", "November 29, 2025", "October 2026") gets the site's
 * date component (icon + <time datetime>) when shown, written in the one site format (AW_DATE_FORMAT: "29 Nov 2025"; a month alone "Oct 2026"). Only visible text in text blocks, classic content and card excerpts is touched (not headings), never
 * a date already inside <time>; the stored text is not changed.
 */
function aw_inline_dates( $html ) {
	static $re = '/(?<![\w-])(?:(?<from>\d{1,2})(?:st|nd|rd|th)?\s*[–—-]\s*(?=\d))?(?:(?<d1>\d{1,2})(?:st|nd|rd|th)?\s+)?(?<mon>January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|Jun|Jul|Aug|Sept?|Oct|Nov|Dec)\.?(?:\s+(?<d2>\d{1,2})(?:st|nd|rd|th)?,)?\s+(?<yr>(?:19|20)\d{2})(?![\w-])/u'; // a range "20–27 Jan 2025" is one date
	if ( ! preg_match( $re, (string) preg_replace( '/<[^>]+>/', ' ', $html ) ) ) { // a tag counts as a space ("2025<br>Animal")
		return $html;
	}
	$parts = preg_split( '/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
	$skip  = 0; // inside <time>, <script>, <style> or a heading
	foreach ( $parts as $k => $part ) {
		if ( '' === $part ) {
			continue;
		}
		if ( '<' === $part[0] ) {
			if ( preg_match( '#^<(time|script|style|h[1-6])\b#i', $part ) ) {
				++$skip;
			} elseif ( preg_match( '#^</(time|script|style|h[1-6])>#i', $part ) ) {
				$skip = max( 0, $skip - 1 );
			}
			continue;
		}
		if ( $skip ) {
			continue;
		}
		$parts[ $k ] = preg_replace_callback(
			$re,
			function ( $m ) {
				$day  = '' !== ( $m['d1'] ?? '' ) ? $m['d1'] : ( $m['d2'] ?? '' );
				$from = (string) ( $m['from'] ?? '' );
				$ts   = strtotime( ( '' !== $day ? $day : '1' ) . ' ' . $m['mon'] . ' ' . $m['yr'] );
				$ts0  = '' !== $from && $ts ? strtotime( $from . ' ' . $m['mon'] . ' ' . $m['yr'] ) : 0;
				$iso  = $ts ? wp_date( '' !== $day ? 'Y-m-d' : 'Y-m', $ts0 ? $ts0 : $ts ) : '';
				$txt  = $ts ? ( $ts0 ? (int) $from . '–' : '' ) . wp_date( '' !== $day ? AW_DATE_FORMAT : 'M Y', $ts ) : $m[0]; // one format sitewide: "2 Oct 2026", "Nov 2025", a range "20–27 Jan 2025"
				return '<span class="aw-date">' . aw_i( 'calendar' ) . '<time' . ( $iso ? ' datetime="' . esc_attr( $iso ) . '"' : '' ) . '>' . esc_html( $txt ) . '</time></span>';
			},
			$part
		);
	}
	return implode( '', $parts );
}
add_filter(
	'render_block',
	function ( $html, $block ) {
		// Text blocks, classic content (no block name), HTML blocks and card excerpts; headings stay as written.
		$text = array( 'core/paragraph', 'core/list-item', 'core/freeform', 'core/html', 'core/table', 'core/quote', 'core/pullquote', 'core/details', 'core/verse', 'core/post-excerpt' );
		return ( null === $block['blockName'] || in_array( $block['blockName'], $text, true ) ) ? aw_inline_dates( $html ) : $html;
	},
	10,
	2
);
add_filter( 'the_content', fn( $c ) => ( is_admin() || is_feed() ) ? $c : aw_inline_dates( $c ), 20 );

/*
 * Headings in page text keep their wording and get no icon, but a date in them is shown in the one site format too
 * (owner, 16 Oct 2026: one format sitewide): the 1.0 timeline "November 1, 2025 — Campaign Launch" -> "1 Nov 2025 — …".
 * Text nodes only; the stored text is not changed. Post titles are not touched.
 */
function aw_heading_dates( $html ) {
	static $re = '/(?<![\w-])(?:(\d{1,2})(?:st|nd|rd|th)?\s+)?(January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|Jun|Jul|Aug|Sept?|Oct|Nov|Dec)\.?(?:\s+(\d{1,2})(?:st|nd|rd|th)?,)?\s+((?:19|20)\d{2})(?![\w-])/';
	$parts = preg_split( '/(<[^>]+>)/', (string) $html, -1, PREG_SPLIT_DELIM_CAPTURE );
	foreach ( $parts as $k => $part ) {
		if ( '' === $part || '<' === $part[0] ) {
			continue;
		}
		$parts[ $k ] = preg_replace_callback(
			$re,
			function ( $m ) {
				$day = '' !== $m[1] ? $m[1] : ( $m[3] ?? '' );
				$ts  = strtotime( ( '' !== $day ? $day : '1' ) . ' ' . $m[2] . ' ' . $m[4] );
				return $ts ? wp_date( '' !== $day ? AW_DATE_FORMAT : 'M Y', $ts ) : $m[0];
			},
			$part
		);
	}
	return implode( '', $parts );
}
add_filter( 'render_block_core/heading', fn( $html ) => is_admin() ? $html : aw_heading_dates( $html ), 20 ); // content shown outside the block filters (guide sections)

/*
 * Titles and summaries are stored in the one format too (owner, 17 Oct 2026: "we keep only one format in dates"), so
 * a worker, an import or an editor writing "October 15, 2026" or "4th October, 2026" saves "15 Oct 2026" / "4 Oct 2026".
 * The body of a Library document keeps the Gazette's own text (it is shown in the one format, above). Search titles and
 * descriptions are printed in the one format.
 */
add_filter(
	'wp_insert_post_data',
	function ( $data ) {
		foreach ( array( 'post_title', 'post_excerpt' ) as $f ) {
			if ( isset( $data[ $f ] ) && '' !== $data[ $f ] ) {
				$data[ $f ] = wp_slash( aw_heading_dates( wp_unslash( $data[ $f ] ) ) );
			}
		}
		return $data;
	},
	20
);
