<?php
/**
 * RankWeb PRO, phase D: custom JSON-LD, Speakable, about and mentions, and "import schema from a URL"
 * (docs/rankweb-pro-audit.md, D). Rank Math keeps a custom-schema builder for Pro; this is RankWeb's version.
 *
 * On an item of a schema post type, a "Custom schema" box (a classic meta box; the block editor hides it) holds:
 * - a JSON-LD block: one object, a list of objects, or a { "@graph": [ … ] }. AW_Schema (class-aw-schema.php) checks it
 *   on save; a broken block is not saved and the reason is shown. Each node is given a stable @id and added to Rank
 *   Math's graph, alongside the node RankWeb already builds for the item.
 * - Speakable CSS selectors, added to the page as a SpeakableSpecification.
 * - "About" and "Mentions": names or URLs, added to the page's main node as Thing references.
 * - "Import from a URL": "Import and save" saves the item, fetches the page and puts its application/ld+json blocks
 *   in the JSON-LD box, to adapt.
 * - A "Test in Google" link to the Rich Results Test for the item's address.
 * "Fill with a Dataset template and save" saves the item with a Dataset starter in the JSON-LD box.
 * The box sits inside the edit screen's post form, so it holds no form of its own and no action or _wpnonce field:
 * the import and the Dataset starter run in the item's own save (save_post), behind the box's nonce.
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
			array( '__back_compat_meta_box' => true ) // classic editor only: core hides back-compat boxes in the block editor
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
	$note = get_transient( 'rankweb_schema_note_' . $id );
	if ( is_string( $note ) && '' !== $note ) {
		delete_transient( 'rankweb_schema_note_' . $id );
		echo '<div class="notice notice-success inline"><p>' . esc_html( $note ) . '</p></div>';
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

	// Import from a URL and the Dataset starter. No inner form (forms cannot nest: its action and _wpnonce fields would
	// join the post form and break Update). Both are part of the item's save instead; the buttons are plain submit
	// buttons of the post form, with their own names.
	echo '<hr><p><label for="rankweb-schema-import-url"><strong>' . esc_html__( 'Import JSON-LD from a URL', 'aw-seo-extras' ) . '</strong></label> ';
	echo '<span class="description">' . esc_html__( 'Import and save reads the page\'s schema into the JSON-LD box above, replacing what is there, to adapt. Your other changes are saved with it.', 'aw-seo-extras' ) . '</span></p>';
	echo '<div style="display:flex;gap:.5em;flex-wrap:wrap;align-items:center">';
	echo '<input type="text" inputmode="url" id="rankweb-schema-import-url" name="rankweb_schema_import_url" class="regular-text code" spellcheck="false" placeholder="https://example.org/page/" style="flex:1;min-width:16em">';
	submit_button( __( 'Import and save', 'aw-seo-extras' ), 'secondary', 'rankweb_schema_import', false );
	echo '</div>';
	echo '<p style="margin-top:.5em">';
	submit_button( __( 'Fill with a Dataset template and save', 'aw-seo-extras' ), 'link', 'rankweb_schema_dataset', false, array( 'onclick' => "return window.confirm('" . esc_js( __( 'Replace the JSON-LD above with a Dataset template?', 'aw-seo-extras' ) ) . "');" ) );
	echo '</p>';

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
		// Once per item per request: another save_post handler may save the same item again (wp_update_post), and a
		// second pass would redo the import and write the typed JSON-LD back over it.
		static $done = array();
		if ( isset( $done[ $post_id ] ) ) {
			return;
		}
		$done[ $post_id ] = true;
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
		// The Dataset starter or an import (only when its own button was clicked) replaces the JSON-LD saved above.
		if ( isset( $_POST['rankweb_schema_dataset'] ) ) {
			update_post_meta( $post_id, AW_SCHEMA_JSONLD_META, AW_Schema::dataset_template() );
			delete_transient( 'aw_seo_schema_err_' . $post_id ); // what was typed in the box is replaced either way
			set_transient( 'rankweb_schema_note_' . $post_id, __( 'Dataset template filled in. Complete it and Update the item.', 'aw-seo-extras' ), 2 * MINUTE_IN_SECONDS );
		} elseif ( isset( $_POST['rankweb_schema_import'] ) ) {
			$url = isset( $_POST['rankweb_schema_import_url'] ) ? esc_url_raw( trim( (string) wp_unslash( $_POST['rankweb_schema_import_url'] ) ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- esc_url_raw() is the sanitizer
			if ( '' === $url ) {
				rankweb_schema_error( (int) $post_id, __( 'Enter a full URL (https://…) to import from.', 'aw-seo-extras' ) );
			} else {
				rankweb_schema_import( (int) $post_id, $url );
			}
		}
	}
);

/**
 * Add a message to the box's error notice for an item, keeping any message already there from this save.
 *
 * @param int    $id  Item.
 * @param string $msg Message.
 */
function rankweb_schema_error( int $id, string $msg ): void {
	$prev = get_transient( 'aw_seo_schema_err_' . $id );
	set_transient( 'aw_seo_schema_err_' . $id, ( is_string( $prev ) && '' !== $prev ? $prev . ' ' : '' ) . $msg, 2 * MINUTE_IN_SECONDS );
}

/* ---------- Import from a URL ---------- */

/**
 * Read a page's JSON-LD into an item's custom JSON-LD; the result is left as a notice for the box.
 * Called from the item's save, after its nonce and edit_post checks.
 *
 * @param int    $id  Item.
 * @param string $url Page to read (already passed through esc_url_raw()).
 */
function rankweb_schema_import( int $id, string $url ): void {
	$res = wp_safe_remote_get(
		$url,
		array(
			'timeout'             => 8,
			'redirection'         => 3,
			'limit_response_size' => 2 * MB_IN_BYTES, // cap the body; the page need only hold its JSON-LD
		)
	);
	if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
		rankweb_schema_error( $id, __( 'The page could not be fetched.', 'aw-seo-extras' ) );
		return;
	}
	$nodes = AW_Schema::extract_from_html( (string) wp_remote_retrieve_body( $res ) );
	if ( ! $nodes ) {
		rankweb_schema_error( $id, __( 'No JSON-LD was found on that page.', 'aw-seo-extras' ) );
		return;
	}
	delete_transient( 'aw_seo_schema_err_' . $id ); // what was typed in the box is replaced by the import
	update_post_meta( $id, AW_SCHEMA_JSONLD_META, (string) wp_json_encode( 1 === count( $nodes ) ? $nodes[0] : array( '@graph' => $nodes ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	/* translators: %d: number of nodes. */
	set_transient( 'rankweb_schema_note_' . $id, sprintf( _n( 'Imported %d node and saved it. Review it below.', 'Imported %d nodes and saved them. Review them below.', count( $nodes ), 'aw-seo-extras' ), count( $nodes ) ), 2 * MINUTE_IN_SECONDS );
}

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
