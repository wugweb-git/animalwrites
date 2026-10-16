<?php
/**
 * Campaign 2.0 (Draft Lab Animal Rules 2026): Library imports, 16 Oct 2026. Run with
 *   wp eval-file campaign2-library.php <dir with l1.html, l3.html, l3-amend.html>
 * Re-running updates the same items (by slug). L1 (the draft) stays a draft until the campaign page is published;
 * L3 (the rules in force) is published. The PCA Act (post 117) only gets the campaign tag.
 * Texts: word for word from ccsea.gov.in (G.S.R. 864(E) PDF; 1998 Rules PDF; CPCSEA Compendium for 2001/2006).
 */
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$dir = rtrim( (string) ( $args[0] ?? '' ), '/' );
if ( ! is_readable( "$dir/l1.html" ) ) {
	WP_CLI::error( 'Pass the folder with l1.html, l3.html, l3-amend.html' );
}

/* Issuer and tags. */
$added = (array) get_option( 'aw_issuers_added', array() );
if ( empty( $added['ccsea'] ) ) {
	$added['ccsea'] = 'Committee for Control and Supervision of Experiments on Animals (CCSEA)';
	update_option( 'aw_issuers_added', $added );
}
$tag = function ( $slug, $name ) {
	$t = get_term_by( 'slug', $slug, 'post_tag' );
	return $t ? (int) $t->term_id : (int) wp_insert_term( $name, 'post_tag', array( 'slug' => $slug ) )['term_id'];
};
$t_campaign = $tag( 'campaign-lab-animals-2026', 'Lab animal rules 2026' );
$t_draft    = $tag( 'draft', 'Draft' );
$t_rule     = $tag( 'rule', 'Rule' );

/** A PDF from its official address, stored here once (by source URL). */
function c2_pdf( $url, $post_id, $title ) {
	$found = get_posts( array( 'post_type' => 'attachment', 'meta_key' => 'aw_source_pdf', 'meta_value' => $url, 'fields' => 'ids', 'posts_per_page' => 1 ) );
	if ( $found ) {
		return (int) $found[0];
	}
	$tmp = download_url( $url, 120 );
	if ( is_wp_error( $tmp ) ) {
		WP_CLI::warning( "PDF not fetched: $url" );
		return 0;
	}
	$file = array( 'name' => sanitize_file_name( $title ) . '.pdf', 'tmp_name' => $tmp );
	$att  = media_handle_sideload( $file, $post_id, $title );
	if ( is_wp_error( $att ) ) {
		@unlink( $tmp ); // phpcs:ignore
		WP_CLI::warning( $att->get_error_message() );
		return 0;
	}
	update_post_meta( $att, 'aw_source_pdf', $url );
	return (int) $att;
}

function c2_note( $text ) {
	return "<!-- wp:paragraph {\"className\":\"aw-doc-status\"} -->\n<p class=\"aw-doc-status\">$text</p>\n<!-- /wp:paragraph -->\n\n";
}
function c2_h( $text, $id ) {
	return "<!-- wp:heading {\"anchor\":\"$id\"} -->\n<h2 class=\"wp-block-heading\" id=\"$id\">$text</h2>\n<!-- /wp:heading -->\n\n";
}

$docs = array(
	array(
		'slug'     => 'draft-laboratory-animals-rules-2026',
		'status'   => 'draft',
		'title'    => 'Draft Prevention of Cruelty to Animals (Control and Supervision of Experiment, Breeding and Trading of Laboratory Animals) Rules, 2026',
		'excerpt'  => 'Draft rules published in the Gazette on 6 October 2026 to replace the 1968 and 1998 rules on experiments on animals in India. Objections can be sent to CCSEA until 5 November 2026.',
		'rm_title' => 'Draft Lab Animal Rules 2026, G.S.R. 864(E) – Animal Writes',
		'focus'    => 'draft laboratory animals rules 2026,ccsea draft rules,g.s.r. 864(e)',
		'date'     => '2026-10-06',
		'number'   => 'G.S.R. 864(E)',
		'force'    => 'NotInForce',
		'tags'     => array( $t_rule, $t_draft, $t_campaign ),
		'src'      => 'https://ccsea.gov.in/WriteReadData/LnPdf/DraftPreventionofCrueltytoAnimals(ControlandSupervisionofExperiment,BreedingandTradingofLaboratoryAnimals)Rules,2026.pdf',
		'src_name' => 'CCSEA',
		'content'  => c2_note( '<strong>Draft.</strong> Objections and suggestions can be sent to CCSEA until 5 November 2026: cpcsea-mef@gov.in, or Member Secretary (CCSEA), Office of CCSEA, Near State Bank of India, Delhi Milk Scheme Complex, Shadipur, Delhi – 110008.' )
			. c2_note( 'English text of the notification as published in the Gazette of India, Extraordinary, No. 793, 6 October 2026. The forms in Schedules I to VII are in the PDF.' )
			. file_get_contents( "$dir/l1.html" ),
	),
	array(
		'slug'     => 'breeding-experiments-animals-rules-1998',
		'status'   => 'publish',
		'title'    => 'Breeding of and Experiments on Animals (Control and Supervision) Rules, 1998 (amended 2001 and 2006)',
		'excerpt'  => 'The rules in force on registering animal houses, ethics committees, where animals for experiments come from and who gives permission, with the 2001 and 2006 amendments. The 2026 draft rules would replace them.',
		'rm_title' => 'Animal Experiments Rules 1998 Amended 2006 – Animal Writes',
		'focus'    => 'breeding of and experiments on animals rules 1998,cpcsea rules 1998',
		'date'     => '1998-12-15',
		'number'   => 'S.O. 1074',
		'force'    => 'InForce',
		'tags'     => array( $t_rule, $t_campaign ),
		'src'      => 'https://ccsea.gov.in/WriteReadData/userfiles/file/1998.pdf',
		'src_name' => 'CCSEA',
		'content'  => c2_note( 'Text of the 1998 Rules (S.O. 1074, 15 December 1998), followed by the amendments of 2001 (S.O. 134(E)) and 2006 (S.O. 1818(E)), as published by CCSEA. The amendments change the rules they name; read them together.' )
			. c2_h( 'Rules, 1998 (S.O. 1074, 15 December 1998)', 'rules-1998' )
			. file_get_contents( "$dir/l3.html" ) . "\n" . file_get_contents( "$dir/l3-amend.html" ),
	),
);

foreach ( $docs as $d ) {
	$existing = get_page_by_path( $d['slug'], OBJECT, 'aw_legal' );
	$post     = array(
		'post_type'    => 'aw_legal',
		'post_name'    => $d['slug'],
		'post_title'   => $d['title'],
		'post_excerpt' => $d['excerpt'],
		'post_content' => $d['content'],
		'post_status'  => $existing ? $existing->post_status : $d['status'], // never un-publish or publish on re-run
	);
	if ( $existing ) {
		$post['ID'] = $existing->ID;
	}
	$id = wp_insert_post( wp_slash( $post ), true );
	if ( is_wp_error( $id ) ) {
		WP_CLI::error( $id->get_error_message() );
	}
	wp_set_object_terms( $id, $d['tags'], 'post_tag', true );
	wp_set_object_terms( $id, array( 'laws-rules', 'policy-government' ), 'category', false );
	foreach ( array(
		'aw_source_date'          => $d['date'],
		'aw_doc_sort'             => $d['date'],
		'aw_issuer'               => 'ccsea',
		'aw_source_url'           => $d['src'],
		'aw_source_name'          => $d['src_name'],
		'aw_legal_number'         => $d['number'],
		'aw_legal_force'          => $d['force'],
		'rank_math_title'         => $d['rm_title'],
		'rank_math_description'   => $d['excerpt'],
		'rank_math_focus_keyword' => $d['focus'],
		'rank_math_primary_category' => (string) get_term_by( 'slug', 'laws-rules', 'category' )->term_id,
	) as $k => $v ) {
		update_post_meta( $id, $k, $v );
	}
	if ( ! get_post_meta( $id, 'aw_file_id', true ) ) {
		$att = c2_pdf( $d['src'], $id, $d['slug'] );
		if ( $att ) {
			update_post_meta( $id, 'aw_file_id', $att );
		}
	}
	if ( function_exists( 'aw_rm_schema_sync' ) ) {
		aw_rm_schema_sync( $id );
	}
	WP_CLI::log( sprintf( '%s %d %s %s', $existing ? 'updated' : 'created', $id, get_post_status( $id ), get_permalink( $id ) ) );
}

/* The PCA Act already in the Library: tag only. */
wp_set_object_terms( 117, array( $t_campaign ), 'post_tag', true );
WP_CLI::success( 'Library imports done' );
