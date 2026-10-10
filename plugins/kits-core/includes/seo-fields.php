<?php
/**
 * Editable SEO fields, no SEO plugin needed.
 *
 * - Categories and topics (every public taxonomy): SEO title and meta description, on the term edit screen.
 * - Pages and posts: SEO title, meta description, share title, share description and share image (media ID),
 *   in an "SEO" box on the edit screen.
 * Empty fields fall back to what seo.php generates. A title without the site name gets " – {site}" added.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KITS_SEO_POST_FIELDS = array(
	'kits_seo_title' => 'SEO title',
	'kits_seo_desc'  => 'Meta description',
	'kits_og_title'  => 'Share title',
	'kits_og_desc'   => 'Share description',
	'kits_og_image'  => 'Share image (media ID, 1200×630)',
	// Search and AI answers (owner, 8 Oct 2026)
	'kits_noindex'          => 'Hide from search engines',
	'kits_canonical'        => 'Canonical URL (only if another address is the original)',
	'kits_focus_kw'         => 'Focus keyword',
	'kits_secondary_kw'     => 'Secondary keywords (comma separated)',
	'kits_search_intent'    => 'Search intent',
	'kits_breadcrumb_label' => 'Breadcrumb label (empty: the title)',
	'kits_ai_summary'       => 'Summary for AI answers (40–60 words, shown under the title)',
	'kits_takeaways'        => 'Key takeaways (3–5, one per line, shown under the summary)',
	'kits_faqs'             => 'FAQs (Q: question / A: answer, one per line; shown at the end and as FAQ schema)',
	'kits_schema_type'      => 'Schema type',
	'kits_last_reviewed'    => 'Last reviewed (date)',
);

/** Fields that Rank Math holds when it is active (seo-rankmath.php); the box then leaves them out. */
const KITS_SEO_RANK_MATH_FIELDS = array( 'kits_seo_title', 'kits_seo_desc', 'kits_og_title', 'kits_og_desc', 'kits_og_image', 'kits_noindex', 'kits_canonical', 'kits_focus_kw', 'kits_secondary_kw' );

/** The fields of the SEO box: all of them, or without Rank Math's when it is active. */
function kits_seo_post_fields() {
	$fields = KITS_SEO_POST_FIELDS;
	if ( function_exists( 'kits_rank_math' ) && kits_rank_math() ) {
		$fields = array_diff_key( $fields, array_flip( KITS_SEO_RANK_MATH_FIELDS ) );
	}
	return $fields;
}

/** Field kinds for the box: key => checkbox | url | textarea | date | select options. */
function kits_seo_field_kinds() {
	return array(
		'kits_seo_desc'      => 'textarea',
		'kits_og_desc'       => 'textarea',
		'kits_noindex'       => 'checkbox',
		'kits_canonical'     => 'url',
		'kits_ai_summary'    => 'textarea',
		'kits_takeaways'     => 'textarea',
		'kits_faqs'          => 'textarea',
		'kits_last_reviewed' => 'date',
		'kits_search_intent' => array( '' => '—', 'informational' => 'Informational', 'commercial' => 'Commercial', 'navigational' => 'Navigational', 'transactional' => 'Transactional' ),
		'kits_schema_type'   => (array) apply_filters( 'kits_schema_types', array( '' => 'Automatic', 'WebPage' => 'WebPage', 'AboutPage' => 'AboutPage', 'ContactPage' => 'ContactPage', 'CollectionPage' => 'CollectionPage', 'Article' => 'Article', 'NewsArticle' => 'NewsArticle', 'BlogPosting' => 'BlogPosting', 'HowTo' => 'HowTo', 'Legislation' => 'Legislation', 'CreativeWork' => 'CreativeWork' ) ),
	);
}

/** The override for the current view: key => value. */
function kits_seo_override( $key ) {
	if ( is_singular() ) {
		return trim( (string) get_post_meta( get_queried_object_id(), $key, true ) );
	}
	if ( ( is_tax() || is_category() || is_tag() ) && in_array( $key, array( 'kits_seo_title', 'kits_seo_desc' ), true ) ) {
		return trim( (string) get_term_meta( get_queried_object_id(), $key, true ) );
	}
	return '';
}

add_filter(
	'pre_get_document_title',
	function ( $title ) {
		$t = ( function_exists( 'kits_rank_math' ) && kits_rank_math() ) ? '' : kits_seo_override( 'kits_seo_title' ); // Rank Math holds the SEO title
		if ( '' === $t || is_front_page() ) {
			return $title;
		}
		$site = get_bloginfo( 'name' );
		return false !== stripos( $t, $site ) ? $t : $t . ' – ' . $site;
	},
	20
);

/* ---------- Term screens ---------- */

add_action(
	'admin_init',
	function () {
		if ( function_exists( 'kits_rank_math' ) && kits_rank_math() ) {
			return; // Rank Math has the term SEO title and description
		}
		foreach ( get_taxonomies( array( 'public' => true ) ) as $tax ) {
			add_action(
				"{$tax}_edit_form_fields",
				function ( $term ) {
					foreach ( array( 'kits_seo_title' => 'SEO title', 'kits_seo_desc' => 'Meta description' ) as $k => $label ) {
						$v = (string) get_term_meta( $term->term_id, $k, true );
						echo '<tr class="form-field"><th scope="row"><label for="' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label></th><td>'
							. ( 'kits_seo_desc' === $k ? '<textarea id="' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '" rows="3">' . esc_textarea( $v ) . '</textarea>' : '<input type="text" id="' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">' )
							. '<p class="description">' . esc_html( 'kits_seo_desc' === $k ? 'About 150 characters. Empty: the description above is used.' : 'Empty: the name is used.' ) . '</p></td></tr>';
					}
				},
				30
			);
			add_action(
				"edited_{$tax}",
				function ( $term_id ) {
					if ( ! current_user_can( 'manage_categories' ) || ! isset( $_POST['kits_seo_title'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
						return;
					}
					update_term_meta( $term_id, 'kits_seo_title', sanitize_text_field( wp_unslash( $_POST['kits_seo_title'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
					update_term_meta( $term_id, 'kits_seo_desc', sanitize_textarea_field( wp_unslash( $_POST['kits_seo_desc'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
				}
			);
		}
	}
);

/* ---------- Page and post screens ---------- */

add_action(
	'add_meta_boxes',
	function ( $type ) {
		$obj = get_post_type_object( $type );
		if ( $obj && $obj->public ) {
			add_meta_box( 'kits_seo', ( function_exists( 'kits_rank_math' ) && kits_rank_math() ) ? 'AI answers, FAQs and schema' : 'SEO', 'kits_seo_box', $type, 'normal', 'low' );
		}
	}
);

function kits_seo_box( $post ) {
	wp_nonce_field( 'kits_seo_' . $post->ID, '_kits_seo' );
	echo '<table class="form-table">';
	$kinds = kits_seo_field_kinds();
	foreach ( kits_seo_post_fields() as $k => $label ) {
		$v    = (string) get_post_meta( $post->ID, $k, true );
		$kind = $kinds[ $k ] ?? 'text';
		if ( is_array( $kind ) ) {
			$ctl = '<select id="' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '">';
			foreach ( $kind as $ov => $ol ) {
				$ctl .= '<option value="' . esc_attr( $ov ) . '"' . selected( $v, $ov, false ) . '>' . esc_html( $ol ) . '</option>';
			}
			$ctl .= '</select>';
		} elseif ( 'checkbox' === $kind ) {
			$ctl = '<input type="checkbox" id="' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '" value="1"' . checked( $v, '1', false ) . '>';
		} elseif ( 'textarea' === $kind ) {
			$ctl = '<textarea class="large-text" rows="' . ( in_array( $k, array( 'kits_faqs', 'kits_takeaways' ), true ) ? 5 : 2 ) . '" id="' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '">' . esc_textarea( $v ) . '</textarea>';
		} else {
			$ctl = '<input class="large-text" type="' . ( 'date' === $kind ? 'date' : ( 'url' === $kind ? 'url' : 'text' ) ) . '" id="' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
		}
		echo '<tr><th><label for="' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label></th><td>' . $ctl . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</table>' . ( ( function_exists( 'kits_rank_math' ) && kits_rank_math() ) ? '' : '<p class="description">Empty fields use the page title, excerpt and featured image.</p>' );
}

add_action(
	'save_post',
	function ( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || ! isset( $_POST['_kits_seo'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_kits_seo'] ) ), 'kits_seo_' . $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		foreach ( array_keys( kits_seo_post_fields() ) as $k ) {
			$v = wp_unslash( $_POST[ $k ] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$kind = kits_seo_field_kinds()[ $k ] ?? 'text';
			if ( 'kits_og_image' === $k ) {
				$v = (string) absint( $v );
			} elseif ( 'textarea' === $kind ) {
				$v = sanitize_textarea_field( $v );
			} elseif ( 'url' === $kind ) {
				$v = esc_url_raw( $v );
			} elseif ( 'checkbox' === $kind ) {
				$v = $v ? '1' : '';
			} elseif ( 'date' === $kind ) {
				$v = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $v ) ? $v : '';
			} elseif ( is_array( $kind ) ) {
				$v = array_key_exists( (string) $v, $kind ) ? (string) $v : '';
			} else {
				$v = sanitize_text_field( $v );
			}
			if ( '' === $v || '0' === $v ) {
				delete_post_meta( $post_id, $k );
			} else {
				update_post_meta( $post_id, $k, $v );
			}
		}
	}
);

/* ---------- Output of the search and AI-answer fields ---------- */

// Hide from search engines.
add_filter(
	'wp_robots',
	function ( $r ) {
		if ( is_singular() && '1' === kits_seo_override( 'kits_noindex' ) ) {
			$r['noindex'] = true;
			$r['follow']  = true;
		}
		return $r;
	}
);

// Items hidden from search engines are left out of the sitemap too.
add_filter(
	'wp_sitemaps_posts_query_args',
	function ( $args ) {
		$args['meta_query']   = (array) ( $args['meta_query'] ?? array() );
		$args['meta_query'][] = array(
			'relation' => 'OR',
			array( 'key' => 'kits_noindex', 'compare' => 'NOT EXISTS' ),
			array( 'key' => 'kits_noindex', 'value' => '1', 'compare' => '!=' ),
		);
		return $args;
	}
);

// Canonical URL override.
add_filter(
	'get_canonical_url',
	function ( $url, $post ) {
		$c = trim( (string) get_post_meta( $post->ID, 'kits_canonical', true ) );
		return $c ? $c : $url;
	},
	10,
	2
);

/** The FAQ pairs of a post: list of [question, answer]. */
function kits_seo_faqs( $id ) {
	$out = array();
	$q   = '';
	foreach ( preg_split( '/\R/', (string) get_post_meta( $id, 'kits_faqs', true ) ) as $line ) {
		if ( preg_match( '/^\s*Q:\s*(.+)$/i', $line, $m ) ) {
			$q = trim( $m[1] );
		} elseif ( $q && preg_match( '/^\s*A:\s*(.+)$/i', $line, $m ) ) {
			$out[] = array( $q, trim( $m[1] ) );
			$q     = '';
		}
	}
	return $out;
}

// AI summary and key takeaways under the page title; FAQs at the end of the content (visible, as the schema needs).
add_filter(
	'render_block_core/post-title',
	function ( $html, $block ) {
		static $done = false;
		if ( $done || ! is_singular() || 1 !== (int) ( $block['attrs']['level'] ?? 2 ) || ! in_the_loop() && ! is_main_query() ) {
			return $html;
		}
		$done = true;
		$id   = get_queried_object_id();
		$sum  = trim( (string) get_post_meta( $id, 'kits_ai_summary', true ) );
		$tk   = array_filter( array_map( 'trim', preg_split( '/\R/', (string) get_post_meta( $id, 'kits_takeaways', true ) ) ) );
		$out  = $sum ? '<p class="kit-ai-summary">' . esc_html( $sum ) . '</p>' : '';
		if ( $tk ) {
			$out .= '<section class="kit-takeaways" aria-labelledby="key-takeaways-title"><h2 id="key-takeaways-title" class="kit-takeaways-title">Key takeaways</h2><ul>' . implode( '', array_map( fn( $t ) => '<li>' . esc_html( $t ) . '</li>', array_slice( $tk, 0, 5 ) ) ) . '</ul></section>';
		}
		return $html . $out;
	},
	10,
	2
);
add_filter(
	'the_content',
	function ( $content ) {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$faqs = kits_seo_faqs( get_the_ID() );
		if ( ! $faqs ) {
			return $content;
		}
		$html = '<section class="kit-faqs" id="faqs" aria-labelledby="faqs-title"><h2 id="faqs-title">Questions</h2>';
		foreach ( $faqs as $f ) {
			$html .= '<details class="kit-faq"><summary>' . esc_html( $f[0] ) . '</summary><p>' . esc_html( $f[1] ) . '</p></details>';
		}
		return $content . $html . '</section>';
	},
	30
);
// FAQPage JSON-LD from the FAQ field (sites with their own schema can use kits_seo_faqs() instead).
add_action(
	'wp_head',
	function () {
		if ( ! is_singular() || ! apply_filters( 'kits_seo_print_faq_schema', true ) || ( function_exists( 'kits_rank_math' ) && kits_rank_math() ) ) {
			return; // with Rank Math the FAQPage joins its graph (seo-rankmath.php)
		}
		$faqs = kits_seo_faqs( get_queried_object_id() );
		if ( ! $faqs ) {
			return;
		}
		$data = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => array_map( fn( $f ) => array( '@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $f[1] ) ), $faqs ),
		);
		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	},
	35
);

// Breadcrumb label override (sites with their own breadcrumbs read kits_breadcrumb_label too).
add_filter(
	'aw_breadcrumb_label',
	function ( $label, $id ) {
		$b = trim( (string) get_post_meta( (int) $id, 'kits_breadcrumb_label', true ) );
		return $b ? $b : $label;
	},
	20,
	2
);

/* ---------- Component-level fields ---------- */

// imageContext: an editor-facing note on the copy an image supports; never shown on the site.
add_filter(
	'attachment_fields_to_edit',
	function ( $fields, $post ) {
		$fields['kits_image_context'] = array( 'label' => 'Image context', 'input' => 'textarea', 'value' => (string) get_post_meta( $post->ID, 'kits_image_context', true ), 'helps' => 'Note for editors: the copy this image supports. Not shown on the site.' );
		return $fields;
	},
	10,
	2
);
add_filter(
	'attachment_fields_to_save',
	function ( $post, $att ) {
		if ( isset( $att['kits_image_context'] ) ) {
			update_post_meta( $post['ID'], 'kits_image_context', sanitize_textarea_field( $att['kits_image_context'] ) );
		}
		return $post;
	},
	10,
	2
);

