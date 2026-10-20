<?php
/**
 * Category and tag pages (owner, 20 Oct 2026): one archive layout for every term. Head band (breadcrumb, H1 with the
 * icon, intro = the term's description, filters) → list (List – card 1 after item 6) → FAQ → Share → List – card 2 →
 * related terms. The last four are blocks in the tag and category templates (Site Editor):
 * animal-writes/term-faq, animal-writes/term-share, the List – card 2 ad, animal-writes/term-related.
 *
 * FAQ: term meta aw_faq, [ [ 'q' => text, 'a' => HTML ], … ], edited on the term's screen. The page shows every question
 * (answers open and close); FAQPage schema (aw-seo-extras/schema.php) is built from the same aw_term_faq() so the
 * markup never holds a question the page does not show.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Tags allowed in an FAQ answer (the page and the schema print the same HTML). */
const AW_FAQ_ANSWER_HTML = array(
	'a'      => array( 'href' => true ),
	'strong' => array(),
	'em'     => array(),
	'br'     => array(),
);

/** Taxonomies with term pages. */
function aw_term_page_taxonomies() {
	return array( 'post_tag', 'category' );
}

/** The current category or tag page's term, or null. */
function aw_term_page_term() {
	$t = get_queried_object();
	return ( is_tag() || is_category() ) && $t instanceof WP_Term ? $t : null;
}

/**
 * A term's FAQ: complete pairs only.
 *
 * @param WP_Term|int $term Term or term ID.
 * @return array[] [ [ 'q' => string, 'a' => string (HTML) ], … ]
 */
function aw_term_faq( $term ) {
	$id  = $term instanceof WP_Term ? $term->term_id : (int) $term;
	$raw = json_decode( (string) get_term_meta( $id, 'aw_faq', true ), true );
	$out = array();
	foreach ( is_array( $raw ) ? $raw : array() as $row ) {
		$q = trim( wp_strip_all_tags( (string) ( $row['q'] ?? '' ) ) );
		$a = trim( wp_kses( (string) ( $row['a'] ?? '' ), AW_FAQ_ANSWER_HTML ) );
		if ( '' !== $q && '' !== $a ) {
			$out[] = array( 'q' => $q, 'a' => $a );
		}
	}
	return $out;
}

/* ---------- Editing: the term's screen ---------- */

foreach ( aw_term_page_taxonomies() as $aw_tp_tax ) {
	add_action(
		"{$aw_tp_tax}_edit_form_fields",
		function ( $term ) {
			$rows = aw_term_faq( $term );
			$n    = max( 5, count( $rows ) + 2 );
			wp_nonce_field( 'aw_term_faq', 'aw_term_faq_nonce' );
			echo '<tr class="form-field"><th scope="row">FAQ</th><td><p class="description">3 to 5 questions people search, each with a short answer. Shown on the page (answers open and close) and as FAQ schema. Answers may use links, bold and italic. Leave a row empty to remove it.</p>';
			for ( $i = 0; $i < $n; $i++ ) {
				$r = $rows[ $i ] ?? array( 'q' => '', 'a' => '' );
				echo '<p><label for="aw-faq-q-' . (int) $i . '"><strong>Question ' . (int) ( $i + 1 ) . '</strong></label><br><input type="text" class="large-text" id="aw-faq-q-' . (int) $i . '" name="aw_faq[' . (int) $i . '][q]" value="' . esc_attr( $r['q'] ) . '"></p>';
				echo '<p><label for="aw-faq-a-' . (int) $i . '">Answer ' . (int) ( $i + 1 ) . '</label><br><textarea class="large-text" rows="3" id="aw-faq-a-' . (int) $i . '" name="aw_faq[' . (int) $i . '][a]">' . esc_textarea( $r['a'] ) . '</textarea></p>';
			}
			echo '</td></tr>';
		}
	);
	add_action(
		"edited_{$aw_tp_tax}",
		function ( $term_id ) {
			if ( ! isset( $_POST['aw_term_faq_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['aw_term_faq_nonce'] ) ), 'aw_term_faq' ) || ! current_user_can( 'manage_categories' ) ) {
				return;
			}
			aw_term_faq_save( $term_id, (array) wp_unslash( $_POST['aw_faq'] ?? array() ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- cleaned in aw_term_faq_save()
		}
	);
}
unset( $aw_tp_tax );

/**
 * Store a term's FAQ (also used by WP-CLI imports).
 *
 * @param int   $term_id Term ID.
 * @param array $rows    [ [ 'q' => …, 'a' => … ], … ].
 */
function aw_term_faq_save( $term_id, array $rows ) {
	$clean = array();
	foreach ( $rows as $row ) {
		$q = trim( sanitize_text_field( (string) ( $row['q'] ?? '' ) ) );
		$a = trim( wp_kses( (string) ( $row['a'] ?? '' ), AW_FAQ_ANSWER_HTML ) );
		if ( '' !== $q && '' !== $a ) {
			$clean[] = array( 'q' => $q, 'a' => $a );
		}
	}
	if ( $clean ) {
		update_term_meta( $term_id, 'aw_faq', wp_slash( wp_json_encode( $clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
	} else {
		delete_term_meta( $term_id, 'aw_faq' );
	}
}

/* ---------- Blocks ---------- */

add_filter(
	'aw_hub_blocks',
	function ( $blocks ) {
		$blocks['term-faq']     = array( 'Term page: FAQ', 'aw_block_term_faq', array() );
		$blocks['term-share']   = array( 'Term page: share', 'aw_block_term_share', array() );
		$blocks['term-related'] = array( 'Term page: related', 'aw_block_term_related', array() );
		return $blocks;
	}
);

/** The editor's stand-in for a term block (no term in the Site Editor). */
function aw_term_block_preview( $text ) {
	return defined( 'REST_REQUEST' ) && REST_REQUEST ? '<p class="kit-note">' . esc_html( $text ) . '</p>' : '';
}

/** A section with an H2 at the item pages' section-heading size. */
function aw_term_section( $slug, $head, $body ) {
	$hid = 'aw-term-' . $slug . '-h';
	return '<section class="aw-more-group aw-term-' . esc_attr( $slug ) . '" aria-labelledby="' . esc_attr( $hid ) . '"><h2 class="aw-more-head has-x-large-font-size" id="' . esc_attr( $hid ) . '">' . esc_html( $head ) . '</h2>' . $body . '</section>';
}

/** FAQ: every question shown, each answer opens and closes (core Details markup, Kits FAQ style). */
function aw_block_term_faq() {
	$term = aw_term_page_term();
	if ( ! $term ) {
		return aw_term_block_preview( 'FAQ: the questions and answers set on the category or tag.' );
	}
	$faq = is_paged() ? array() : aw_term_faq( $term ); // page 1 only, where the FAQPage schema is
	if ( ! $faq ) {
		return '';
	}
	$body = '';
	foreach ( $faq as $f ) {
		$body .= '<details class="wp-block-details"><summary>' . esc_html( $f['q'] ) . '</summary><p>' . $f['a'] . '</p></details>'; // answer: kses-cleaned in aw_term_faq()
	}
	return aw_term_section( 'faq', __( 'Questions and answers', 'animal-writes-core' ), $body );
}

/** Share: the page's address and its name. */
function aw_block_term_share() {
	$term = aw_term_page_term();
	if ( ! $term ) {
		return aw_term_block_preview( 'Share: X, Facebook, LinkedIn, WhatsApp, Email, Copy link' );
	}
	if ( ! function_exists( 'kits_share_html' ) ) {
		return '';
	}
	$title = function_exists( 'aw_seo_plain' ) && function_exists( 'aw_seo_term_title' ) ? preg_replace( '/ – Animal Writes$/u', '', aw_seo_plain( aw_seo_term_title( $term ) ) ) : html_entity_decode( $term->name, ENT_QUOTES );
	return '<div class="aw-item-share">' . kits_share_html( (string) get_term_link( $term ), $title ) . '</div>';
}

/**
 * The terms a term sits with, and their group's heading: organisation types, services, Library types, a section's other
 * tags, or the other categories.
 *
 * @param WP_Term $term Term.
 * @return array [ heading, slugs ('' = every category) ]
 */
function aw_term_siblings( $term ) {
	if ( 'category' === $term->taxonomy ) {
		return array( __( 'Other categories', 'animal-writes-core' ), '' );
	}
	$types = function_exists( 'aw_listing_type_slugs' ) ? aw_listing_type_slugs() : array();
	if ( in_array( $term->slug, $types, true ) ) {
		return array( __( 'Other organisation types', 'animal-writes-core' ), $types );
	}
	$services = function_exists( 'aw_service_slugs' ) ? aw_service_slugs() : array();
	if ( in_array( $term->slug, $services, true ) ) {
		return array( __( 'Other services', 'animal-writes-core' ), $services );
	}
	if ( defined( 'AW_LEGAL_KINDS' ) && isset( AW_LEGAL_KINDS[ $term->slug ] ) ) {
		return array( __( 'Other document types', 'animal-writes-core' ), array_keys( AW_LEGAL_KINDS ) );
	}
	$sec = function_exists( 'aw_tag_section' ) ? aw_tag_section( $term->slug ) : '';
	return array( __( 'Related tags', 'animal-writes-core' ), $sec ? array_values( array_diff( aw_section_tags( $sec ), $types, $services ) ) : array() );
}

/** Related: the term's group, as chips (most items first; empty terms left out). */
function aw_block_term_related() {
	$term = aw_term_page_term();
	if ( ! $term ) {
		return aw_term_block_preview( 'Related: the other organisation types, services or categories.' );
	}
	list( $head, $slugs ) = aw_term_siblings( $term );
	if ( is_array( $slugs ) && ! $slugs ) {
		return '';
	}
	$args = array( 'taxonomy' => $term->taxonomy, 'hide_empty' => true, 'exclude' => array( $term->term_id ), 'orderby' => 'count', 'order' => 'DESC', 'number' => 12 );
	if ( is_array( $slugs ) ) {
		$args['slug'] = $slugs;
	} else {
		$args['exclude'][] = (int) get_option( 'default_category' );
	}
	$terms = get_terms( $args );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return '';
	}
	$out = '<ul class="kit-tags aw-term-chips">';
	foreach ( $terms as $t ) {
		$ico  = 'post_tag' === $t->taxonomy && function_exists( 'aw_tag_icon' ) ? aw_tag_icon( $t->slug ) : '';
		$out .= '<li><a class="kit-chip" href="' . esc_url( get_term_link( $t ) ) . '">' . $ico . esc_html( html_entity_decode( $t->name, ENT_QUOTES ) ) . '</a></li>';
	}
	return aw_term_section( 'related', $head, $out . '</ul>' );
}

/* ---------- Ads: List – card 2 sits after Share on term pages ---------- */

add_filter( 'kits_ads_list_slots', fn( $slots ) => aw_term_page_term() ? array_slice( $slots, 0, 1, true ) : $slots );
