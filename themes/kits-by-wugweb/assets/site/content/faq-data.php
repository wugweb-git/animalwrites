<?php
/**
 * Library documents: the "Library item" fields (short name, parent Act, key sections) in the SCF "Document" box, and
 * their post meta. They run on every request, whatever RankWeb's FAQ module is, because they are edit-screen fields.
 * The FAQ questions built from them, and the rest of the site's FAQ rules, moved to RankWeb on 18 Nov 2026
 * (aw-seo-extras/includes/faq-data.php); the meta keys are unchanged.
 *
 * @package kits-by-wugweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	function () {
		$auth = fn( $allowed, $key, $post_id ) => current_user_can( 'edit_post', $post_id );
		register_post_meta( 'aw_legal', 'aw_short_name', array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'description' => 'Short name, as people search it (PCA Act 1960)', 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => $auth ) );
		// The Hindi version link is no longer edited or asked about (owner, 1 Nov 2026); stored values are kept.
		register_post_meta( 'aw_legal', 'aw_file_hi_url', array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'description' => 'Hindi version (link to the official Hindi text)', 'sanitize_callback' => 'esc_url_raw', 'auth_callback' => $auth ) );
		register_post_meta( 'aw_legal', 'aw_legal_act', array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'description' => 'Issued under (the parent Act, as the document states it)', 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => $auth ) );
		register_post_meta( 'aw_legal', 'aw_key_sections', array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'description' => 'Key sections (Acts and Rules): one per line, "number | one-line plain summary | link"', 'sanitize_callback' => 'sanitize_textarea_field', 'auth_callback' => $auth ) );
	}
);

/**
 * The Library item fields (short name, parent Act, key sections), shown inside the SCF "Document" box.
 *
 * @param WP_Post $post Library item.
 */
function aw_legal_item_fields( $post ) {
	wp_nonce_field( 'aw_faq_legal', 'aw_faq_legal_nonce' );
	echo '<p><label for="aw-short-name">Short name, as people search it (e.g. PCA Act 1960, ABC Rules 2023). Empty = the focus keyphrase</label><br><input type="text" class="large-text" id="aw-short-name" name="aw_short_name" value="' . esc_attr( get_post_meta( $post->ID, 'aw_short_name', true ) ) . '"></p>';
	echo '<p><label for="aw-legal-act">Issued under (the parent Act, as the document states it)</label><br><input type="text" class="large-text" id="aw-legal-act" name="aw_legal_act" value="' . esc_attr( get_post_meta( $post->ID, 'aw_legal_act', true ) ) . '"></p>';
	echo '<p><label for="aw-key-sections">Key sections (Acts and Rules only): one per line, "number | one-line plain summary | link"</label><br><textarea class="large-text" rows="4" id="aw-key-sections" name="aw_key_sections" placeholder="11 | punishment for treating animals cruelly | https://…">' . esc_textarea( get_post_meta( $post->ID, 'aw_key_sections', true ) ) . '</textarea></p>';
}

// SCF "Document" box (acf-json/group_aw_legal_document.json): the Library item fields and the issuer, each in its message field.
add_action(
	'acf/render_field/key=field_aw_legal_msg_item',
	function () {
		global $post;
		if ( $post instanceof WP_Post && 'aw_legal' === $post->post_type ) {
			aw_legal_item_fields( $post );
		}
	}
);
add_action(
	'acf/render_field/key=field_aw_legal_msg_issued_by',
	function () {
		global $post;
		if ( $post instanceof WP_Post && 'aw_legal' === $post->post_type && function_exists( 'aw_issuer_box' ) ) {
			aw_issuer_box( $post );
		}
	}
);
add_action(
	'save_post_aw_legal',
	function ( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || ! isset( $_POST['aw_faq_legal_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['aw_faq_legal_nonce'] ) ), 'aw_faq_legal' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		foreach ( array( 'aw_short_name' => 'sanitize_text_field', 'aw_legal_act' => 'sanitize_text_field', 'aw_key_sections' => 'sanitize_textarea_field' ) as $k => $clean ) {
			$v = call_user_func( $clean, wp_unslash( (string) ( $_POST[ $k ] ?? '' ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$v = str_replace( "\r\n", "\n", $v ); // browsers post CRLF; stored lines end in \n, so a save without changes changes nothing
			'' !== trim( $v ) ? update_post_meta( $post_id, $k, $v ) : delete_post_meta( $post_id, $k );
		}
	}
);
