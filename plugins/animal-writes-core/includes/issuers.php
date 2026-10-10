<?php
/**
 * Issuer: who issued a Library document ("Issued by Supreme Court"). A label on the item, not a list, filter or page
 * (S1.1, 8 Oct 2026). Stored on the item as meta aw_issuer (a slug from the issuer names below). The former list
 * (taxonomy aw_source_type, with courts merged in) was copied into the meta once and is no longer registered; a
 * backup of its links is in uploads/aw-private/issuers-backup-2026-10-08.json. Old /source/{slug}/ URLs 301 to the
 * Library (places.php).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Issuer names: slug => name (the former list's values). An editor can add one from the item's Issuer box. */
function aw_issuers() {
	$base = array(
		'supreme-court'           => 'Supreme Court',
		'high-court'              => 'High Court',
		'national-green-tribunal' => 'National Green Tribunal',
		'awbi'                    => 'Animal Welfare Board of India',
		'central-ministry'        => 'Central Ministry',
		'state-government'        => 'State Government',
		'municipal-body'          => 'Municipal Body',
		'parliament'              => 'Parliament',
		'gazette'                 => 'Gazette of India',
		'pib'                     => 'Press Information Bureau',
		'report'                  => 'Report',
		'research-journal'        => 'Research Journal',
	);
	return array_merge( $base, (array) get_option( 'aw_issuers_added', array() ) );
}

/** An item's issuer: object {slug, name}, or null. */
function aw_item_issuer( $id ) {
	$slug = (string) get_post_meta( $id, 'aw_issuer', true );
	$all  = aw_issuers();
	return isset( $all[ $slug ] ) ? (object) array( 'slug' => $slug, 'name' => $all[ $slug ] ) : null;
}

/** Set an item's issuer by slug or name; unknown values are refused. */
function aw_set_issuer( $id, $value ) {
	$value = trim( (string) $value );
	$all   = aw_issuers();
	$slug  = isset( $all[ sanitize_title( $value ) ] ) ? sanitize_title( $value ) : (string) array_search( strtolower( $value ), array_map( 'strtolower', $all ), true );
	if ( '' === $slug || ! isset( $all[ $slug ] ) ) {
		return false;
	}
	update_post_meta( $id, 'aw_issuer', $slug );
	return true;
}

/* ---------- Editing ---------- */

add_action(
	'add_meta_boxes',
	function () {
		foreach ( array( 'aw_legal', 'post' ) as $type ) {
			add_meta_box( 'aw-issuer', 'Issued by', 'aw_issuer_box', $type, 'side', 'default' );
		}
	}
);

function aw_issuer_box( $post ) {
	wp_nonce_field( 'aw_issuer_save', 'aw_issuer_nonce' );
	$cur = (string) get_post_meta( $post->ID, 'aw_issuer', true );
	echo '<p><select name="aw_issuer" style="width:100%"><option value="">None</option>';
	foreach ( aw_issuers() as $slug => $name ) {
		echo '<option value="' . esc_attr( $slug ) . '"' . selected( $cur, $slug, false ) . '>' . esc_html( $name ) . '</option>';
	}
	echo '</select></p><p><label for="aw-issuer-new">Not in the list? Add it:</label><br><input type="text" id="aw-issuer-new" name="aw_issuer_new" maxlength="80" style="width:100%"></p>';
}

add_action(
	'save_post',
	function ( $id, $post ) {
		if ( ! in_array( $post->post_type, array( 'aw_legal', 'post' ), true ) || ! isset( $_POST['aw_issuer_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['aw_issuer_nonce'] ), 'aw_issuer_save' ) || ! current_user_can( 'edit_post', $id ) ) {
			return;
		}
		$new = sanitize_text_field( wp_unslash( $_POST['aw_issuer_new'] ?? '' ) );
		if ( '' !== $new ) {
			$added                       = (array) get_option( 'aw_issuers_added', array() );
			$added[ sanitize_title( $new ) ] = $new;
			update_option( 'aw_issuers_added', $added, false );
			update_post_meta( $id, 'aw_issuer', sanitize_title( $new ) );
			return;
		}
		$slug = sanitize_title( wp_unslash( $_POST['aw_issuer'] ?? '' ) );
		'' === $slug ? delete_post_meta( $id, 'aw_issuer' ) : aw_set_issuer( $id, $slug );
	},
	10,
	2
);

/* ---------- Display ---------- */

/** [aw_issuer_label] "Issued by …" on a Library item (a label, not a link). */
add_shortcode(
	'aw_issuer_label',
	function () {
		$iss = aw_item_issuer( get_the_ID() );
		return $iss ? '<p class="kit-tags aw-issuer">' . kits_tag( 'Issued by ' . $iss->name ) . '</p>' : '';
	}
);

/* ---------- One-time copy from the former list ---------- */

add_action(
	'init',
	function () {
		if ( '1' === get_option( 'aw_issuers_migrated' ) ) {
			return;
		}
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT tr.object_id id, t.slug, t.name FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'aw_source_type' JOIN {$wpdb->terms} t ON t.term_id = tt.term_id ORDER BY tr.object_id, tr.term_order" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( ! file_put_contents( aw_private_dir() . '/issuers-backup-2026-10-08.json', wp_json_encode( $rows ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			return;
		}
		$n = 0;
		foreach ( $rows as $r ) {
			if ( '' === (string) get_post_meta( (int) $r->id, 'aw_issuer', true ) && aw_set_issuer( (int) $r->id, $r->slug ) ) {
				$n++;
			}
		}
		update_option( 'aw_issuers_report', array( 'links' => count( $rows ), 'copied' => $n ), false );
		update_option( 'aw_issuers_migrated', '1', false );
	},
	5
);
