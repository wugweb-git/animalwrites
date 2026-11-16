<?php
/**
 * RankWeb PRO, phase D: custom JSON-LD, Speakable, about and mentions, and "import schema from a URL"
 * (docs/rankweb-pro-audit.md, D). Rank Math keeps a custom-schema builder for Pro; this is RankWeb's version.
 *
 * On an item of a schema post type, a "Custom schema" box (shown in both editors through the classic meta box) holds:
 * - a JSON-LD block: one object, a list of objects, or a { "@graph": [ … ] }. AW_Schema (class-aw-schema.php) checks it
 *   on save; a broken block is not saved and the reason is shown. Each node is given a stable @id and added to Rank
 *   Math's graph, alongside the node RankWeb already builds for the item.
 * - Speakable CSS selectors, added to the page as a SpeakableSpecification.
 * - "About" and "Mentions": names or URLs, added to the page's main node as Thing references.
 * - "Import from a URL": the page is fetched and its application/ld+json blocks are read into the JSON-LD box to adapt.
 * - A "Test in Google" link to the Rich Results Test for the item's address.
 * A "Dataset" button fills the box with a Dataset starter.
 *
 * Module "schema-plus" (RankWeb → Settings → Modules). No scheduled job.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-aw-schema.php';

const AW_SCHEMA_JSONLD_META    = '_aw_jsonld';
const AW_SCHEMA_SPEAKABLE_META = '_aw_speakable';
const AW_SCHEMA_ABOUT_META     = '_aw_schema_about';
const AW_SCHEMA_MENTIONS_META  = '_aw_schema_mentions';

/** The post types that get the custom-schema box (the setting; empty: none). */
function aw_schema_custom_types(): array {
	return array_values( array_filter( array_map( 'sanitize_key', (array) AW_SCHEMA_TYPES ), 'post_type_exists' ) );
}

/** A list field (one value per line), cleaned. */
function aw_schema_lines( $value ): array {
	$lines = is_array( $value ) ? $value : preg_split( '/[\r\n]+/', (string) $value );
	$out   = array();
	foreach ( (array) $lines as $line ) {
		$line = trim( sanitize_text_field( (string) $line ) );
		if ( '' !== $line ) {
			$out[] = $line;
		}
	}
	return array_values( array_unique( $out ) );
}

/* ---------- The meta box ---------- */

add_action(
	'add_meta_boxes',
	function ( $post_type, $post = null ) {
		if ( ! $post instanceof WP_Post || ! in_array( (string) $post_type, aw_schema_custom_types(), true ) || ! current_user_can( 'edit_post', (int) $post->ID ) ) {
			return;
		}
		add_meta_box(
			'aw-schema-custom',
			__( 'Custom schema (JSON-LD)', 'aw-seo-extras' ),
			'aw_schema_custom_box',
			$post_type,
			'normal',
			'low',
			array( '__back_compat_meta_box' => true ) // shown in the block editor too, as the Link keywords box is
		);
	},
	10,
	2
);

/**
 * The box.
 *
 * @param WP_Post $post Item.
 */
function aw_schema_custom_box( WP_Post $post ): void {
	$id = (int) $post->ID;
	wp_nonce_field( 'aw_schema_custom_' . $id, 'aw_schema_custom_nonce' );
	$err = get_transient( 'aw_seo_schema_err_' . $id );
	if ( is_string( $err ) && '' !== $err ) {
		delete_transient( 'aw_seo_schema_err_' . $id );
		echo '<div class="notice notice-error inline"><p>' . esc_html( $err ) . '</p></div>';
	}
	echo '<p><label for="aw-jsonld"><strong>' . esc_html__( 'JSON-LD', 'aw-seo-extras' ) . '</strong></label><br>';
	echo '<span class="description">' . esc_html__( 'One object, a list of objects, or a { "@graph": [ … ] }. Added to the page\'s schema. Checked when you save.', 'aw-seo-extras' ) . '</span></p>';
	echo '<textarea id="aw-jsonld" name="aw_jsonld" rows="10" class="large-text code" spellcheck="false">' . esc_textarea( (string) get_post_meta( $id, AW_SCHEMA_JSONLD_META, true ) ) . '</textarea>';

	echo '<p><label for="aw-speakable"><strong>' . esc_html__( 'Speakable (CSS selectors)', 'aw-seo-extras' ) . '</strong></label><br>';
	echo '<span class="description">' . esc_html__( 'One CSS selector per line (for example .entry-title or .lead). Added as SpeakableSpecification.', 'aw-seo-extras' ) . '</span></p>';
	echo '<textarea id="aw-speakable" name="aw_speakable" rows="3" class="large-text code">' . esc_textarea( implode( "\n", (array) get_post_meta( $id, AW_SCHEMA_SPEAKABLE_META, true ) ) ) . '</textarea>';

	echo '<div style="display:flex;gap:1em;flex-wrap:wrap"><div style="flex:1;min-width:14em"><p><label for="aw-schema-about"><strong>' . esc_html__( 'About', 'aw-seo-extras' ) . '</strong></label><br><span class="description">' . esc_html__( 'Names or URLs, one per line.', 'aw-seo-extras' ) . '</span></p>';
	echo '<textarea id="aw-schema-about" name="aw_schema_about" rows="3" class="large-text code">' . esc_textarea( implode( "\n", (array) get_post_meta( $id, AW_SCHEMA_ABOUT_META, true ) ) ) . '</textarea></div>';
	echo '<div style="flex:1;min-width:14em"><p><label for="aw-schema-mentions"><strong>' . esc_html__( 'Mentions', 'aw-seo-extras' ) . '</strong></label><br><span class="description">' . esc_html__( 'Names or URLs, one per line.', 'aw-seo-extras' ) . '</span></p>';
	echo '<textarea id="aw-schema-mentions" name="aw_schema_mentions" rows="3" class="large-text code">' . esc_textarea( implode( "\n", (array) get_post_meta( $id, AW_SCHEMA_MENTIONS_META, true ) ) ) . '</textarea></div></div>';

	// Import from a URL and the Dataset starter: their own small forms, so they work with no JavaScript.
	$import = admin_url( 'admin-post.php' );
	echo '<hr><p><strong>' . esc_html__( 'Import JSON-LD from a URL', 'aw-seo-extras' ) . '</strong> ';
	echo '<span class="description">' . esc_html__( 'Reads the page\'s schema into the box above, to adapt. It replaces what is there; Update the item to keep it.', 'aw-seo-extras' ) . '</span></p>';
	echo '<form method="post" action="' . esc_url( $import ) . '" style="display:flex;gap:.5em;flex-wrap:wrap;align-items:center">';
	echo '<input type="hidden" name="action" value="aw_schema_import"><input type="hidden" name="post" value="' . esc_attr( (string) $id ) . '">';
	wp_nonce_field( 'aw_schema_import_' . $id );
	echo '<input type="url" name="url" class="regular-text" placeholder="https://example.org/page/" style="flex:1;min-width:16em">';
	submit_button( __( 'Import', 'aw-seo-extras' ), 'secondary', 'submit', false );
	echo '</form>';

	echo '<form method="post" action="' . esc_url( $import ) . '" style="margin-top:.5em">';
	echo '<input type="hidden" name="action" value="aw_schema_dataset"><input type="hidden" name="post" value="' . esc_attr( (string) $id ) . '">';
	wp_nonce_field( 'aw_schema_dataset_' . $id );
	submit_button( __( 'Fill with a Dataset template', 'aw-seo-extras' ), 'link', 'submit', false );
	echo '</form>';

	if ( 'publish' === $post->post_status ) {
		$test = 'https://search.google.com/test/rich-results?url=' . rawurlencode( (string) get_permalink( $id ) );
		echo '<p><a href="' . esc_url( $test ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Test this page in Google Rich Results →', 'aw-seo-extras' ) . '</a></p>';
	}
}

/* ---------- Saving ---------- */

add_action(
	'save_post',
	function ( $post_id ) {
		if ( ! isset( $_POST['aw_schema_custom_nonce'] ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aw_schema_custom_nonce'] ) ), 'aw_schema_custom_' . $post_id ) || ! current_user_can( 'edit_post', (int) $post_id ) ) {
			return;
		}
		// JSON-LD: checked, and only saved when it is good; a bad block is kept in a notice and the stored value is left.
		$raw    = isset( $_POST['aw_jsonld'] ) ? trim( (string) wp_unslash( $_POST['aw_jsonld'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON-LD text, validated by AW_Schema::parse() below and never echoed unescaped
		$parsed = AW_Schema::parse( $raw );
		if ( '' !== $parsed['error'] ) {
			/* translators: %s: the JSON error. */
			set_transient( 'aw_seo_schema_err_' . $post_id, sprintf( __( 'The custom JSON-LD was not saved: %s', 'aw-seo-extras' ), $parsed['error'] ), 2 * MINUTE_IN_SECONDS );
		} elseif ( '' === $raw ) {
			delete_post_meta( $post_id, AW_SCHEMA_JSONLD_META );
		} else {
			update_post_meta( $post_id, AW_SCHEMA_JSONLD_META, $raw );
		}
		foreach ( array(
			AW_SCHEMA_SPEAKABLE_META => 'aw_speakable',
			AW_SCHEMA_ABOUT_META     => 'aw_schema_about',
			AW_SCHEMA_MENTIONS_META  => 'aw_schema_mentions',
		) as $meta => $field ) {
			$list = isset( $_POST[ $field ] ) ? aw_schema_lines( wp_unslash( $_POST[ $field ] ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each line sanitized in aw_schema_lines()
			if ( $list ) {
				update_post_meta( $post_id, $meta, $list );
			} else {
				delete_post_meta( $post_id, $meta );
			}
		}
	}
);

/* ---------- Import from a URL, and the Dataset starter ---------- */

add_action(
	'admin_post_aw_schema_import',
	function () {
		$id = absint( wp_unslash( $_POST['post'] ?? 0 ) );
		check_admin_referer( 'aw_schema_import_' . $id );
		if ( ! $id || ! current_user_can( 'edit_post', $id ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'aw-seo-extras' ), '', array( 'response' => 403 ) );
		}
		$url = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) );
		$msg = '';
		if ( '' === $url ) {
			$msg = __( 'Enter a URL to import from.', 'aw-seo-extras' );
		} else {
			$res = wp_remote_get(
				$url,
				array(
					'timeout'     => 8,
					'redirection' => 3,
				)
			);
			if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
				$msg = __( 'The page could not be fetched.', 'aw-seo-extras' );
			} else {
				$nodes = AW_Schema::extract_from_html( (string) wp_remote_retrieve_body( $res ) );
				if ( ! $nodes ) {
					$msg = __( 'No JSON-LD was found on that page.', 'aw-seo-extras' );
				} else {
					update_post_meta( $id, AW_SCHEMA_JSONLD_META, (string) wp_json_encode( 1 === count( $nodes ) ? $nodes[0] : array( '@graph' => $nodes ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
					/* translators: %d: number of nodes. */
					$msg = sprintf( _n( 'Imported %d node. Review it and Update the item.', 'Imported %d nodes. Review them and Update the item.', count( $nodes ), 'aw-seo-extras' ), count( $nodes ) );
				}
			}
		}
		set_transient( 'aw_seo_schema_err_' . $id, $msg, 2 * MINUTE_IN_SECONDS );
		wp_safe_redirect( get_edit_post_link( $id, 'url' ) );
		exit;
	}
);

add_action(
	'admin_post_aw_schema_dataset',
	function () {
		$id = absint( wp_unslash( $_POST['post'] ?? 0 ) );
		check_admin_referer( 'aw_schema_dataset_' . $id );
		if ( ! $id || ! current_user_can( 'edit_post', $id ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'aw-seo-extras' ), '', array( 'response' => 403 ) );
		}
		update_post_meta( $id, AW_SCHEMA_JSONLD_META, AW_Schema::dataset_template() );
		set_transient( 'aw_seo_schema_err_' . $id, __( 'Dataset template filled in. Complete it and Update the item.', 'aw-seo-extras' ), 2 * MINUTE_IN_SECONDS );
		wp_safe_redirect( get_edit_post_link( $id, 'url' ) );
		exit;
	}
);

/* ---------- Into the graph ---------- */

add_filter(
	'rank_math/json_ld',
	function ( $data ) {
		$types = aw_schema_custom_types();
		if ( ! is_array( $data ) || ! $types || ! is_singular( $types ) ) {
			return $data;
		}
		$id   = get_queried_object_id();
		$base = (string) get_permalink( $id );

		$raw = (string) get_post_meta( $id, AW_SCHEMA_JSONLD_META, true );
		if ( '' !== $raw ) {
			$parsed = AW_Schema::parse( $raw );
			$n      = 0;
			foreach ( AW_Schema::with_ids( $parsed['nodes'], $base ) as $node ) {
				++$n;
				$data[ 'aw_custom_' . $n ] = $node;
			}
		}

		// Speakable, about and mentions go on the page's WebPage node (the one Rank Math always prints).
		$speak = AW_Schema::speakable( (array) get_post_meta( $id, AW_SCHEMA_SPEAKABLE_META, true ) );
		$about = AW_Schema::things( (array) get_post_meta( $id, AW_SCHEMA_ABOUT_META, true ) );
		$ment  = AW_Schema::things( (array) get_post_meta( $id, AW_SCHEMA_MENTIONS_META, true ) );
		if ( ( $speak || $about || $ment ) && isset( $data['WebPage'] ) && is_array( $data['WebPage'] ) ) {
			if ( $speak ) {
				$data['WebPage']['speakable'] = $speak;
			}
			if ( $about ) {
				$data['WebPage']['about'] = array_merge( (array) ( $data['WebPage']['about'] ?? array() ), $about );
			}
			if ( $ment ) {
				$data['WebPage']['mentions'] = array_merge( (array) ( $data['WebPage']['mentions'] ?? array() ), $ment );
			}
		}
		return function_exists( 'aw_rm_absolute_urls' ) ? aw_rm_absolute_urls( $data ) : $data;
	},
	1100 // after schema.php (1355 is Rank Math-side; this runs after RankWeb's own nodes are in)
);
